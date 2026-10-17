<?php
require_once __DIR__ . '/bulk.php';
require_once __DIR__ . '/portfolio_core.php';

function ptEffective_(string $mode): array {
    $cur = jkt_date('Y-m');
    if ($mode === 'NEXT_MONTH') {
        $d = (new DateTimeImmutable($cur . '-01', jkt_tz_()))->modify('+1 month');
        return ['period'=>$d->format('Y-m'),'date'=>$d->format('Y-m-01'),'future'=>true];
    }
    return ['period'=>$cur,'date'=>$cur.'-01','future'=>false];
}
function ptSource_(array $db,array $u,string $nik): array {
    $p = PortalCore::person($db['meta']['people'],$nik);
    if (!$p || !truthy($p['sales'] ?? false)) fail_('User ini tidak memiliki portofolio Sales aktif.');
    if ($u['role'] !== 'SUPER ADMIN' && !canManageUser_($u,$p)) fail_('User di luar kewenangan Admin.');
    return $p;
}
function ptCandidates_(array $db,array $u,array $src): array {
    $out=[];
    foreach ($db['meta']['people'] as $p) {
        if ($p['nik']===$src['nik'] || $p['role']!=='SALES' || !truthy($p['sales']??false)) continue;
        if (js_upper(text_($p['wilayah']??''))!==js_upper(text_($src['wilayah']??''))) continue;
        $a=account_($p['nik']); if($a && !PortalCore::active($a['AKTIF']??null)) continue;
        if($u['role']!=='SUPER ADMIN' && !canManageUser_($u,$p)) continue;
        $out[]=['nik'=>$p['nik'],'nama'=>$p['nama'],'region'=>$p['region']??'','wilayah'=>$p['wilayah']??''];
    }
    usort($out,fn($a,$b)=>lc($a['nama'],$b['nama']));
    return $out;
}
function ptDealers_(array $db,string $nik,string $period): array {
    $today=jkt_date('Y-m-d'); $bi=dbaIndex_(); $gi=portfolioAssignmentIndex_(); $out=[];
    foreach($db['meta']['customers'] as $id=>$c){
        $brands=[]; foreach(dbaPrimaryBrands_() as $b){$o=dealerBrandOwner_($db,(string)$id,$b,$today,$bi);if($o['nik']===$nik)$brands[]=$b;}
        $generic=portfolioOwnerAt_($gi,(string)$id,$today,text_($c['nik']??''));
        if(!$brands && $generic!==$nik) continue;
        $out[(string)$id]=['dealer'=>(string)$id,'nama'=>text_($c['nama']??''),'region'=>text_($c['region']??''),'brands'=>$brands,'qty'=>0.0,'omzet'=>0.0];
    }
    $ref=(string)($db['raw'][$period]??'');
    if($ref!=='') try{
        foreach(dataRows_($ref) as $t){
            if(substr(text_($t['date']??''),0,7)!==$period) continue;
            $d=custInduk_($db['meta'],text_($t['customer']??'')); if(!isset($out[$d])) continue;
            $p=$db['meta']['products'][text_($t['sku']??'')]??null; if(!$p||!truthy($p['device']??false)) continue;
            $b=js_upper(text_($p['brand']??'')); if(!in_array($b,$out[$d]['brands'],true)) continue;
            $out[$d]['qty']+=(float)($t['qty']??0); $out[$d]['omzet']+=(float)($t['amount']??0);
        }
    }catch(Throwable $e){}
    $r=array_values($out);usort($r,fn($a,$b)=>lc($a['nama'],$b['nama'])?:lc($a['dealer'],$b['dealer']));return $r;
}
function ptTargetInputCols_(array $heads): array {
    $n=array_map(fn($h)=>preg_replace('/\s+/u',' ',js_upper(text_($h))),$heads);
    $pi=array_search('PERIODE TARGET',$n,true);$ni=array_search('NIK SALES',$n,true);$nm=array_search('NAMA SALES',$n,true);
    if($pi===false||$ni===false)fail_('Target_Input tidak valid.');
    $cols=[];foreach($n as $i=>$h)if(preg_match('/^(ST|DA|NOO|IDDLE|OMZET|SO)\s+(.+)$/u',$h,$m))$cols[]=['i'=>$i,'metric'=>$m[1],'brand'=>trim($m[2])];
    return ['p'=>$pi,'n'=>$ni,'name'=>$nm,'cols'=>$cols];
}
function ptTargetId_(string $kind,string $p,string $b,string $m,string $i=''):string{return implode('¦',[$kind,$p,$b,$m,$i]);}
function ptTargets_(string $nik,string $from): array {
    $out=[];$g=readGrid_('Target_Input');
    if($g&&count($g)>1){$c=ptTargetInputCols_($g[0]);foreach(array_slice($g,1) as $r){$p=text_($r[$c['p']]??'');if(text_($r[$c['n']]??'')!==$nik||$p===''||strcmp($p,$from)<0)continue;
        foreach($c['cols'] as $x){$v=$r[$x['i']]??'';if($v===''||$v===null)continue;$num=js_number_($v);if(!is_numeric($num)||!is_finite((float)$num))continue;
            $out[]=['id'=>ptTargetId_('WIDE',$p,$x['brand'],$x['metric']),'kind'=>'WIDE','period'=>$p,'brand'=>$x['brand'],'metric'=>$x['metric'],'indicator'=>'','value'=>(float)$num];}}}
    foreach(optionalTable_('Target_Periode') as $r){$p=text_($r['PERIODE']??'');if(text_($r['NIK']??'')!==$nik||$p===''||strcmp($p,$from)<0)continue;
        $b=text_($r['BRAND']??'');$m=js_upper(text_($r['METRIK']??''));$i=text_($r['INDIKATOR_ID']??'');
        $out[]=['id'=>ptTargetId_('LONG',$p,$b,$m,$i),'kind'=>'LONG','period'=>$p,'brand'=>$b,'metric'=>$m,'indicator'=>$i,'value'=>(float)($r['TARGET']??0)];}
    if(function_exists('kkSchema_')){kkSchema_();$q=db_()->prepare('SELECT period,brand,value FROM _kk_target WHERE nik=? AND period>=?');$q->execute([$nik,$from]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$out[]=['id'=>ptTargetId_('KKSO',$r['period'],$r['brand'],'SO','KK_SO_MANUAL'),'kind'=>'KKSO','period'=>$r['period'],'brand'=>$r['brand'],'metric'=>'SO','indicator'=>'KK_SO_MANUAL','value'=>(float)$r['value']];}
    return $out;
}
function apiPortfolioTransferPreview($token,$sourceNik,$mode='CURRENT_MONTH'){
    $db=snapshot_();$u=adminGuard_($token,$db);$src=ptSource_($db,$u,text_($sourceNik));$eff=ptEffective_(js_upper(text_($mode)));
    $dealers=ptDealers_($db,$src['nik'],jkt_date('Y-m'));$targets=ptTargets_($src['nik'],$eff['period']);$qty=0;$om=0;foreach($dealers as $d){$qty+=$d['qty'];$om+=$d['omzet'];}
    return ['source'=>['nik'=>$src['nik'],'nama'=>$src['nama'],'role'=>$src['role'],'wilayah'=>$src['wilayah']??'','acting'=>$src['role']!=='SALES'&&!empty($src['sales'])],
        'effective'=>$eff,'candidates'=>ptCandidates_($db,$u,$src),'dealers'=>$dealers,'targets'=>$targets,'summary'=>['dealers'=>count($dealers),'qty'=>$qty,'omzet'=>$om],'canFuture'=>$src['role']!=='SALES'];
}
function apiPortfolioExitState($token,$nik){$db=snapshot_();$u=adminGuard_($token,$db);$p=PortalCore::person($db['meta']['people'],text_($nik));if(!$p||($u['role']!=='SUPER ADMIN'&&!canManageUser_($u,$p)))fail_('User di luar kewenangan Admin.');$due=portfolioExitDue_($p['nik']);$row=null;foreach(portfolioExitRows_() as $r)if($r['nik']===$p['nik']&&$r['status']==='ACTIVE')$row=$r;return ['nik'=>$p['nik'],'exited'=>$due,'effective'=>$row['date']??null];}
function ptAlloc_(array $targets,$raw,array $cands):array{
    $by=[];foreach(is_array($raw)?$raw:[] as $a)if(is_array($a))$by[text_($a['id']??'')]=$a;$out=[];
    foreach($targets as $t){$a=$by[$t['id']]??null;if(!$a)fail_('Pembagian target belum lengkap: '.$t['metric'].' '.$t['brand'].'.');$sum=0;$parts=[];
        foreach(is_array($a['parts']??null)?$a['parts']:[] as $p){$n=text_($p['nik']??'');$v=(float)($p['value']??0);if(!isset($cands[$n])||$v<0||!is_finite($v))fail_('Pembagian target tidak valid.');$sum+=$v;$parts[]=['nik'=>$n,'value'=>$v];}
        if(abs($sum-(float)$t['value'])>.001)fail_('Total target '.$t['metric'].' '.$t['brand'].' harus '.$t['value'].'.');$out[$t['id']]=['target'=>$t,'parts'=>$parts];}
    return $out;
}
function ptMoveWide_(string $src,array $alloc,array $people):void{
    $g=readGrid_('Target_Input');if(!$g)return;$heads=$g[0];$c=ptTargetInputCols_($heads);$rows=array_values(array_slice($g,1));$names=[];foreach($people as $p)$names[$p['nik']]=$p['nama'];
    $col=[];foreach($c['cols'] as $x)$col[js_upper($x['metric']).'|'.js_upper($x['brand'])]=$x['i'];
    $find=function($per,$nik)use(&$rows,$c){foreach($rows as $i=>$r)if(text_($r[$c['p']]??'')===$per&&text_($r[$c['n']]??'')===$nik)return $i;return null;};
    foreach($alloc as $x){$t=$x['target'];if($t['kind']!=='WIDE')continue;$ci=$col[js_upper($t['metric']).'|'.js_upper($t['brand'])]??null;if($ci===null)fail_('Kolom target tidak ditemukan.');
        $si=$find($t['period'],$src);if($si===null)continue;$rows[$si][$ci]='';
        foreach($x['parts'] as $p){if(!$p['value'])continue;$ri=$find($t['period'],$p['nik']);if($ri===null){$r=array_fill(0,count($heads),'');$r[$c['p']]=$t['period'];$r[$c['n']]=$p['nik'];if($c['name']!==false)$r[$c['name']]=$names[$p['nik']]??'';$rows[]=$r;$ri=count($rows)-1;}
            $rows[$ri][$ci]=(($rows[$ri][$ci]??'')===''?0:(float)$rows[$ri][$ci])+$p['value'];}}
    putTable_('Target_Input',$heads,$rows);
}
function ptMoveLong_(string $src,array $alloc):void{
    $g=readGrid_('Target_Periode');if(!$g)return;$heads=array_map('text_',$g[0]);$rows=optionalTable_('Target_Periode');
    $key=fn($r)=>ptTargetId_('LONG',text_($r['PERIODE']??''),text_($r['BRAND']??''),js_upper(text_($r['METRIK']??'')),text_($r['INDIKATOR_ID']??''));
    $ids=[];foreach($alloc as $x)if($x['target']['kind']==='LONG')$ids[$x['target']['id']]=$x;$next=[];
    foreach($rows as $r)if(!(text_($r['NIK']??'')===$src&&isset($ids[$key($r)])))$next[]=$r;
    foreach($ids as $x){$t=$x['target'];foreach($x['parts'] as $p){if(!$p['value'])continue;$hit=null;foreach($next as $i=>$r)if(text_($r['NIK']??'')===$p['nik']&&$key($r)===$t['id']){$hit=$i;break;}
        if($hit!==null)$next[$hit]['TARGET']=(float)($next[$hit]['TARGET']??0)+$p['value'];else $next[]=['PERIODE'=>$t['period'],'NIK'=>$p['nik'],'BRAND'=>$t['brand'],'METRIK'=>$t['metric'],'TARGET'=>$p['value'],'INDIKATOR_ID'=>$t['indicator']];}}
    putTable_('Target_Periode',$heads,array_map(fn($r)=>array_map(fn($h)=>$r[$h]??'',$heads),$next));
}
function ptMoveKk_(string $src,array $alloc,string $actor):void{
    if(!function_exists('kkSchema_'))return;kkSchema_();foreach($alloc as $x){$t=$x['target'];if($t['kind']!=='KKSO')continue;db_()->prepare('DELETE FROM _kk_target WHERE nik=? AND period=? AND brand=?')->execute([$src,$t['period'],$t['brand']]);
        foreach($x['parts'] as $p){if(!$p['value'])continue;$q=db_()->prepare('SELECT value FROM _kk_target WHERE nik=? AND period=? AND brand=?');$q->execute([$p['nik'],$t['period'],$t['brand']]);$old=$q->fetchColumn();$v=($old===false?0:(float)$old)+$p['value'];
            db_()->prepare('REPLACE INTO _kk_target(nik,period,brand,value,actor,updated_at) VALUES(?,?,?,?,?,?)')->execute([$p['nik'],$t['period'],$t['brand'],$v,$actor,iso_now()]);}}
    if(function_exists('kkReset_'))kkReset_();
}
function ptAssignDealer_(array $db,array $u,string $src,string $dealer,string $to,string $date,string $reason,bool $future):int{
    $n=0;$idx=dbaIndex_();foreach(dbaPrimaryBrands_() as $b){$o=dealerBrandOwner_($db,$dealer,$b,$date,$idx);if($o['nik']!==$src)continue;dbaSet_($db,$u,$dealer,$b,$to,$date,$reason,'PORTFOLIO_TRANSFER',$future);$idx=dbaIndex_();$n++;}
    $gi=portfolioAssignmentIndex_();$master=dbaMasterNik_($db,$dealer);if(portfolioOwnerAt_($gi,$dealer,$date,$master)===$src){$hist=optionalTable_('Dealer_Assignment_History');$next=[];$now=iso_now();
        foreach($hist as $h){if(text_($h['KODE CUSTOMER']??'')!==$dealer){$next[]=$h;continue;}$f=text_($h['BERLAKU_MULAI']??'');$t=text_($h['BERLAKU_SAMPAI']??'');if($f!==''&&$f>$date&&($t===''||$t>=$date))fail_('Sudah ada assignment dealer masa depan: '.$dealer);if($f===$date)continue;if($f!==''&&$f<$date&&($t===''||$t>=$date))$h['BERLAKU_SAMPAI']=dayAfter_($date,-1);$next[]=$h;}
        $hasBase=false;foreach($next as $h)if(text_($h['KODE CUSTOMER']??'')===$dealer&&text_($h['NIK SALES']??'')===$src&&text_($h['BERLAKU_SAMPAI']??'')===dayAfter_($date,-1)){$hasBase=true;break;}
        if(!$hasBase&&$master===$src)$next[]=['KODE CUSTOMER'=>$dealer,'NIK SALES'=>$src,'BERLAKU_MULAI'=>'','BERLAKU_SAMPAI'=>dayAfter_($date,-1),'SUMBER'=>'SEED','NOTE'=>'Owner sebelum alih portofolio','UPDATED_BY'=>$u['nik'],'UPDATED_AT'=>$now];
        $next[]=['KODE CUSTOMER'=>$dealer,'NIK SALES'=>$to,'BERLAKU_MULAI'=>$date,'BERLAKU_SAMPAI'=>'','SUMBER'=>'PORTFOLIO_TRANSFER','NOTE'=>$reason,'UPDATED_BY'=>$u['nik'],'UPDATED_AT'=>$now];
        putTable_('Dealer_Assignment_History',ASSIGNMENT_HEADERS,array_map(fn($r)=>array_map(fn($h)=>$r[$h]??'',ASSIGNMENT_HEADERS),$next));
        if(!$future&&$master===$src){$g=readGrid_('Master_Customer');$heads=array_map('text_',$g[0]);$rows=table_('Master_Customer');foreach($rows as &$r)if(text_($r['KODE CUSTOMER']??'')===$dealer)$r['NIK SALES']=$to;unset($r);putTable_('Master_Customer',$heads,array_map(fn($r)=>array_map(fn($h)=>$r[$h]??'',$heads),$rows));}}
    return $n;
}
function ptPartsForPublish_(array $db,array $meta,string $period): ?array {
    $parts=$db['parts']??null;if(!is_array($parts)||empty($parts['ST'][$period]))return $parts;
    foreach($parts['ST'][$period] as $region=>$entry){$file=(string)($entry['file']??'');if($file==='')continue;$rows=[];foreach(dataRows_($file) as $t)$rows[]=$t;$rows=portfolioResolveRows_($meta,$rows);$parts['ST'][$period][$region]['file']=partWrite_('ST',$period,(string)$region,$rows);}return $parts;
}
function apiPortfolioTransferCommit($token,$payload,$reason=null){
    $payload=is_array($payload)?$payload:[];$reason=text_($reason??($payload['reason']??''));if(mb_strlen($reason)<5)fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function()use($token,$payload,$reason){$db=snapshot_();$u=adminGuard_($token,$db);$src=ptSource_($db,$u,text_($payload['sourceNik']??''));$eff=ptEffective_(js_upper(text_($payload['mode']??'CURRENT_MONTH')));
        if($src['role']==='SALES'&&$eff['future'])fail_('Sales resign harus ambil alih bulan berjalan.');
        $cand=[];foreach(ptCandidates_($db,$u,$src) as $c)$cand[$c['nik']]=$c;$dealers=ptDealers_($db,$src['nik'],jkt_date('Y-m'));$need=[];foreach($dealers as $d)$need[$d['dealer']]=1;$as=[];
        foreach(is_array($payload['assignments']??null)?$payload['assignments']:[] as $a){$d=text_($a['dealer']??'');$to=text_($a['newNik']??'');if(!isset($need[$d])||!isset($cand[$to])||isset($as[$d]))fail_('Assignment dealer tidak valid.');$as[$d]=$to;}
        if(count($as)!==count($need))fail_('Semua dealer harus dialokasikan.');
        $targets=ptTargets_($src['nik'],$eff['period']);$alloc=ptAlloc_($targets,$payload['allocations']??[],$cand);
        $sheets=['Master_Customer','Dealer_Assignment_History',DBA_SHEET,'Target_Input','Target_Periode',PT_EXIT_SHEET,'_Auth'];$before=[];foreach($sheets as $s)$before[$s]=readGrid_($s);
        kkSchema_();$niks=array_values(array_unique(array_merge(array_keys($cand),[$src['nik']])));$ph=implode(',',array_fill(0,count($niks),'?'));$q=db_()->prepare('SELECT nik,period,brand,value,actor,updated_at FROM _kk_target WHERE nik IN('.$ph.') AND period>=?');$q->execute(array_merge($niks,[$eff['period']]));$kk=$q->fetchAll(PDO::FETCH_ASSOC);
        checkpoint_($u['nik'],'Alihkan portofolio '.$src['nik'].' · '.$reason);
        try{$brandCount=0;foreach($as as $d=>$to)$brandCount+=ptAssignDealer_($db,$u,$src['nik'],$d,$to,$eff['date'],$reason,$eff['future']);ptMoveWide_($src['nik'],$alloc,$db['meta']['people']);ptMoveLong_($src['nik'],$alloc);ptMoveKk_($src['nik'],$alloc,$u['nik']);portfolioExitSet_($src['nik'],$eff['date'],$u['nik']);
            $deact=$src['role']==='SALES'&&!$eff['future'];if($deact&&($a=account_($src['nik']))){$a['AKTIF']=false;$a['VERSI']=(int)($a['VERSI']??0)+1;writeAccount_($a);}
            $meta=meta_();validateConfigChange_($db,$meta);$parts=ptPartsForPublish_($db,$meta,$eff['period']);publish_($meta,$db['raw'],$db['coverage'],$u['nik'],'PORTFOLIO_TRANSFER '.$src['nik'].' · '.$reason,null,$parts);
            if($deact)try{revokeAllDevices_($src['nik']);}catch(Throwable $e){}
            audit_($u['nik'],'PORTFOLIO_TRANSFER',$src['nik'].' · '.count($as).' dealer · '.$brandCount.' dealer-brand · '.count($targets).' target · '.$reason);
            return ['ok'=>true,'dealers'=>count($as),'brandAssignments'=>$brandCount,'targets'=>count($targets),'recipients'=>count(array_unique($as)),'effective'=>$eff,'sourceRole'=>$src['role']];
        }catch(Throwable $e){foreach($before as $s=>$g){if($g)restoreGrid_($s,$g);elseif(sheet_exists_($s))deleteSheet_($s);}db_()->prepare('DELETE FROM _kk_target WHERE nik IN('.$ph.') AND period>=?')->execute(array_merge($niks,[$eff['period']]));$ins=db_()->prepare('REPLACE INTO _kk_target(nik,period,brand,value,actor,updated_at) VALUES(?,?,?,?,?,?)');foreach($kk as $r)$ins->execute([$r['nik'],$r['period'],$r['brand'],$r['value'],$r['actor'],$r['updated_at']]);if(function_exists('kkReset_'))kkReset_();throw $e;}
    });
}
