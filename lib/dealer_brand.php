<?php
/** Dealer × Brand ownership with effective-dated history. */
require_once __DIR__ . '/auth.php';

const DBA_SHEET = 'Dealer_Brand_Assignment';
const DBA_CUTOVER_PROP = 'DEALER_BRAND_CUTOVER';
const DBA_HEADS = ['KODE CUSTOMER','BRAND','NIK SALES','BERLAKU_MULAI','BERLAKU_SAMPAI','STATUS','NOTE','UPDATED_BY','UPDATED_AT'];

/** Parse an optional ISO date used by assignment history. Blank is valid. */
function dbaIsoOptional_($v): string {
    $s = js_slice(text_($v), 0, 10);
    if ($s === '') return '';
    try { return PortalCore::iso($s); } catch (Throwable $e) { return ''; }
}
function dbaCutover_(): string {
    $v = dbaIsoOptional_(prop_get_(DBA_CUTOVER_PROP));
    return $v !== '' ? $v : '2026-10-01';
}
function dbaPrimaryBrands_(): array {
    $out = [];
    foreach (optionalTable_('Product_Rules') as $r) {
        if (!PortalCore::active($r['MASUK_QTY'] ?? null)) continue;
        $b = js_upper(js_or(text_($r['BRAND_UTAMA'] ?? null), text_($r['BRAND'] ?? null)));
        if ($b !== '') $out[] = $b;
    }
    if (!$out) {
        foreach (optionalTable_('Master_Product') as $r) {
            $b = js_upper(text_($r['BRAND'] ?? null)); if ($b !== '') $out[] = $b;
        }
    }
    $out = array_values(array_unique($out)); sort($out, SORT_STRING); return $out;
}
function dbaRows_(): array {
    $rows = optionalTable_(DBA_SHEET); $out = [];
    foreach ($rows as $r) {
        $d = text_($r['KODE CUSTOMER'] ?? null); $b = js_upper(text_($r['BRAND'] ?? null)); $n = text_($r['NIK SALES'] ?? null);
        $from = dbaIsoOptional_($r['BERLAKU_MULAI'] ?? null); $to = dbaIsoOptional_($r['BERLAKU_SAMPAI'] ?? null);
        if ($d === '' || $b === '' || $n === '' || $from === '') continue;
        $out[] = ['dealer'=>$d,'brand'=>$b,'nik'=>$n,'from'=>$from,'to'=>$to ?: '', 'status'=>js_upper(js_or(text_($r['STATUS'] ?? null),'ACTIVE')), 'note'=>text_($r['NOTE'] ?? null), 'by'=>text_($r['UPDATED_BY'] ?? null), 'at'=>text_($r['UPDATED_AT'] ?? null)];
    }
    usort($out, fn($a,$b)=>strcmp($a['dealer'],$b['dealer']) ?: strcmp($a['brand'],$b['brand']) ?: strcmp($a['from'],$b['from']));
    return $out;
}
function dbaIndex_(?array $rows = null): array {
    $idx = []; foreach ($rows ?? dbaRows_() as $r) $idx[$r['dealer']][$r['brand']][] = $r; return $idx;
}
function dbaAt_(array $idx, string $dealer, string $brand, string $date): ?array {
    $list = $idx[$dealer][js_upper($brand)] ?? []; $hit = null;
    foreach ($list as $r) {
        if ($r['status'] !== 'ACTIVE') continue;
        if ($r['from'] <= $date && ($r['to'] === '' || $r['to'] >= $date)) $hit = $r;
    }
    return $hit;
}
function dbaMasterNik_(array $db, string $dealer): string {
    $induk = function_exists('custInduk_') ? custInduk_($db['meta'], $dealer) : $dealer;
    return text_($db['meta']['customers'][$induk]['nik'] ?? $db['meta']['customers'][$dealer]['nik'] ?? '');
}
function dealerBrandOwner_(array $db, string $dealer, string $brand, ?string $date = null, ?array $idx = null): array {
    $date = text_($date) === '' ? jkt_date('Y-m-d') : PortalCore::iso($date); $brand = js_upper($brand); $induk = function_exists('custInduk_') ? custInduk_($db['meta'], $dealer) : $dealer;
    $idx = $idx ?? dbaIndex_(); $a = dbaAt_($idx, $induk, $brand, $date); $nik = $a ? $a['nik'] : dbaMasterNik_($db, $induk);
    $p = PortalCore::person($db['meta']['people'], $nik);
    return ['dealer'=>$induk,'brand'=>$brand,'date'=>$date,'nik'=>$nik,'nama'=>$p ? text_($p['nama'] ?? null) : '', 'big'=>$p ? text_($p['wilayah'] ?? null) : '', 'region'=>$p ? js_or(text_($p['portfolio'] ?? null), text_($p['region'] ?? null)) : '', 'source'=>$a ? 'ASSIGNMENT' : 'MASTER_CUSTOMER', 'assignment'=>$a];
}
function dealerBrandOwnerLabel_(array $x): string { return trim(($x['nik'] ?? '') . (($x['nama'] ?? '') !== '' ? ' — ' . $x['nama'] : '')); }
function dbaCanManageNik_(array $u, array $db, string $nik): bool {
    if ($u['role'] === 'SUPER ADMIN') return true;
    $p = PortalCore::person($db['meta']['people'], $nik); return (bool)($p && canManageUser_($u,$p));
}
function dbaDayBefore_(string $iso): string { return (new DateTimeImmutable($iso.'T00:00:00', jkt_tz_()))->modify('-1 day')->format('Y-m-d'); }
function dbaDayAfter_(string $iso): string { return (new DateTimeImmutable($iso.'T00:00:00', jkt_tz_()))->modify('+1 day')->format('Y-m-d'); }
function dbaWrite_(array $rows): void {
    $out = [];
    foreach ($rows as $r) $out[] = [$r['dealer'],$r['brand'],$r['nik'],$r['from'],$r['to'] ?? '',$r['status'] ?? 'ACTIVE',$r['note'] ?? '',$r['by'] ?? '',$r['at'] ?? ''];
    putTable_(DBA_SHEET, DBA_HEADS, $out);
}
/** Set one effective-dated assignment. Returns before/after owner. */
function dbaSet_(array $db, array $u, string $dealer, string $brand, string $nik, string $from, string $note, string $source = 'MANUAL', bool $allowFuture = false): array {
    $dealer = function_exists('custInduk_') ? custInduk_($db['meta'], text_($dealer)) : text_($dealer); $brand = js_upper(text_($brand)); $nik = text_($nik); $from = PortalCore::iso($from);
    if ($dealer === '' || !isset($db['meta']['customers'][$dealer])) fail_('Dealer tidak ditemukan: ' . $dealer);
    if (!in_array($brand, dbaPrimaryBrands_(), true)) fail_('Brand tidak valid: ' . $brand);
    $p = PortalCore::person($db['meta']['people'], $nik); if (!$p || !truthy($p['sales'] ?? null)) fail_('NIK tujuan bukan Sales/portofolio Sales aktif: ' . $nik);
    if (!$from) fail_('Tanggal efektif wajib YYYY-MM-DD.'); if (!$allowFuture && strcmp($from, jkt_date('Y-m-d')) > 0) fail_('Tanggal efektif masa depan belum didukung.');
    $rows = dbaRows_(); $idx = dbaIndex_($rows); $before = dealerBrandOwner_($db,$dealer,$brand,$from,$idx);
    if (!dbaCanManageNik_($u,$db,$before['nik']) || !dbaCanManageNik_($u,$db,$nik)) fail_('PIC sumber/tujuan di luar kewenangan Admin.');
    if ($u['role'] !== 'SUPER ADMIN' && $before['big'] !== '' && $p && js_upper($before['big']) !== js_upper(text_($p['wilayah'] ?? null))) fail_('Perpindahan lintas Big Region khusus Super Admin.');
    $next = [];
    foreach ($rows as $r) {
        if ($r['dealer'] === $dealer && $r['brand'] === $brand && $r['from'] === $from) continue;
        if ($r['dealer'] === $dealer && $r['brand'] === $brand && $r['status'] === 'ACTIVE' && $r['from'] < $from && ($r['to'] === '' || $r['to'] >= $from)) $r['to'] = dbaDayBefore_($from);
        $next[] = $r;
    }
    foreach ($next as $r) if ($r['dealer']===$dealer && $r['brand']===$brand && $r['status']==='ACTIVE' && $r['from'] > $from && ($r['to']==='' || $r['to'] >= $from)) fail_('Sudah ada assignment masa depan untuk ' . $dealer . ' · ' . $brand . ' mulai ' . $r['from'] . '. Hapus/perbaiki assignment itu dulu.');
    if ($before['source'] === 'MASTER_CUSTOMER' && $before['nik'] !== '') {
        $periods = js_sort_default(array_map('strval', js_keys($db['coverage'] ?? [])));
        $baselineFrom = $periods ? ((string)$periods[0] . '-01') : dbaCutover_();
        foreach ($next as $r) if ($r['dealer']===$dealer && $r['brand']===$brand && $r['status']==='ACTIVE' && $r['to']!=='' && $r['to'] < $from && $r['to'] >= $baselineFrom) $baselineFrom = dbaDayAfter_($r['to']);
        if ($baselineFrom < $from) $next[] = ['dealer'=>$dealer,'brand'=>$brand,'nik'=>$before['nik'],'from'=>$baselineFrom,'to'=>dbaDayBefore_($from),'status'=>'ACTIVE','note'=>'BASELINE MASTER CUSTOMER','by'=>$u['nik'],'at'=>iso_now()];
    }
    $next[] = ['dealer'=>$dealer,'brand'=>$brand,'nik'=>$nik,'from'=>$from,'to'=>'','status'=>'ACTIVE','note'=>trim($source . ($note!=='' ? ' · '.$note : '')),'by'=>$u['nik'],'at'=>iso_now()];
    dbaWrite_($next);
    return ['before'=>$before,'after'=>dealerBrandOwner_($db,$dealer,$brand,$from,dbaIndex_($next))];
}
function dbaSuperOrAdmin_($token, ?array $db = null): array { $db=$db??snapshot_(); $u=adminGuard_($token,$db); return [$db,$u]; }

function apiDealerBrandSettings($token) {
    [$db,$u]=dbaSuperOrAdmin_($token); $rows=dbaRows_();
    return ['cutover'=>dbaCutover_(),'brands'=>dbaPrimaryBrands_(),'assignments'=>count($rows),'superuser'=>$u['role']==='SUPER ADMIN'];
}
function apiDealerBrandSetCutover($token,$date,$reason=null) {
    $reason=text_($reason); if(mb_strlen($reason)<5) fail_('Isi alasan minimal 5 karakter.');
    [$db,$u]=dbaSuperOrAdmin_($token); if($u['role']!=='SUPER ADMIN') fail_('Tanggal cutover hanya bisa diubah Super Admin.'); $date=PortalCore::iso($date); if(!$date) fail_('Tanggal cutover tidak valid.');
    $old=dbaCutover_(); prop_set_(DBA_CUTOVER_PROP,$date); audit_($u['nik'],'DEALER_BRAND_CUTOVER',$old.' → '.$date.' · '.$reason); return ['old'=>$old,'cutover'=>$date];
}
function apiDealerBrandList($token,$q=null,$brand=null) {
    [$db,$u]=dbaSuperOrAdmin_($token); $q=js_upper(text_($q)); $brand=js_upper(text_($brand)); $idx=dbaIndex_(); $today=jkt_date('Y-m-d'); $rows=[];
    foreach ($db['meta']['customers'] as $id=>$c) {
        $id=(string)$id; foreach (dbaPrimaryBrands_() as $b) {
            if($brand!==''&&$brand!=='ALL'&&$b!==$brand) continue; $o=dealerBrandOwner_($db,$id,$b,$today,$idx);
            if($u['role']!=='SUPER ADMIN'&&!dbaCanManageNik_($u,$db,$o['nik'])) continue;
            $name=text_($c['nama']??null); if($q!==''&&mb_strpos(js_upper($id.' '.$name.' '.$b.' '.$o['nik'].' '.$o['nama']),$q)===false) continue;
            $rows[]=['dealer'=>$id,'nama'=>$name,'lokasi'=>text_($c['region']??null),'brand'=>$b,'nik'=>$o['nik'],'sales'=>$o['nama'],'big'=>$o['big'],'region'=>$o['region'],'source'=>$o['source'],'from'=>$o['assignment']['from']??'','to'=>$o['assignment']['to']??''];
            if(count($rows)>=500) break 2;
        }
    }
    return ['rows'=>$rows,'brands'=>dbaPrimaryBrands_(),'cutover'=>dbaCutover_()];
}

function apiDealerBrandSuggestions($token,$months=3) {
    [$db,$u]=dbaSuperOrAdmin_($token); $months=max(1,min(6,(int)$months)); $periods=js_sort_default(array_map('strval',js_keys($db['raw']??[]))); $periods=array_slice($periods,-$months); $agg=[];$tot=[];$today=jkt_date('Y-m-d'); $primaryBrands=dbaPrimaryBrands_();
    foreach($periods as $period){$ref=(string)($db['raw'][$period]??'');if($ref==='')continue;try{foreach(dataRows_($ref) as $t){$date=PortalCore::iso($t['date']??null);$q=(float)($t['qty']??0);if(!$date||$date>$today||$q<=0)continue;$sku=text_($t['sku']??null);$cust=text_($t['customer']??null);$nik=text_($t['nik']??null);if($cust===''||$nik===''||!isset($db['meta']['products'][$sku]))continue;$brand=js_upper(text_($db['meta']['products'][$sku]['brand']??null));if(!in_array($brand,$primaryBrands,true))continue;$dealer=custInduk_($db['meta'],$cust);$k=$dealer."\x1f".$brand;$nk=$k."\x1f".$nik;if(!isset($agg[$nk]))$agg[$nk]=['dealer'=>$dealer,'brand'=>$brand,'nik'=>$nik,'qty'=>0,'rows'=>0,'first'=>'','last'=>''];$agg[$nk]['qty']+=$q;$agg[$nk]['rows']++;if($agg[$nk]['first']===''||$date<$agg[$nk]['first'])$agg[$nk]['first']=$date;if($date>$agg[$nk]['last'])$agg[$nk]['last']=$date;$tot[$k]=($tot[$k]??0)+$q;}}catch(Throwable $e){}}
    $best=[];foreach($agg as $x){$k=$x['dealer']."\x1f".$x['brand'];if(!isset($best[$k])||$x['qty']>$best[$k]['qty']||($x['qty']===$best[$k]['qty']&&$x['rows']>$best[$k]['rows']))$best[$k]=$x;}
    $idx=dbaIndex_();$out=[];
    foreach($best as $k=>$x){$o=dealerBrandOwner_($db,$x['dealer'],$x['brand'],$today,$idx);if($o['nik']===''||$o['nik']===$x['nik'])continue;if($u['role']!=='SUPER ADMIN'&&!dbaCanManageNik_($u,$db,$o['nik']))continue;$p=PortalCore::person($db['meta']['people'],$x['nik']);$out[]=['dealer'=>$x['dealer'],'brand'=>$x['brand'],'current'=>$o,'candidate'=>['nik'=>$x['nik'],'nama'=>$p?($p['nama']??''):'','qty'=>$x['qty'],'rows'=>$x['rows'],'first'=>$x['first'],'last'=>$x['last']],'share'=>($tot[$k]??0)>0?$x['qty']/($tot[$k]??1):0];}
    usort($out,fn($a,$b)=>($b['candidate']['qty']<=>$a['candidate']['qty']));return ['rows'=>array_slice($out,0,300),'periods'=>$periods,'cutover'=>dbaCutover_()];
}
function apiDealerBrandSet($token,$dealer,$brand,$nik,$from,$reason) {
    $reason=text_($reason); if(mb_strlen($reason)<5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function()use($token,$dealer,$brand,$nik,$from,$reason){[$db,$u]=dbaSuperOrAdmin_($token);$before=readGrid_(DBA_SHEET);checkpoint_($u['nik'],'PIC Dealer Brand '.$dealer.' '.$brand.' · '.$reason);try{$r=dbaSet_($db,$u,text_($dealer),js_upper(text_($brand)),text_($nik),text_($from),$reason,'MANUAL');audit_($u['nik'],'DEALER_BRAND_ASSIGN',text_($dealer).' · '.js_upper(text_($brand)).' · '.dealerBrandOwnerLabel_($r['before']).' → '.dealerBrandOwnerLabel_($r['after']).' · '.$from.' · '.$reason);return $r;}catch(Throwable $e){if($before)restoreGrid_(DBA_SHEET,$before);else if(sheet_exists_(DBA_SHEET))deleteSheet_(DBA_SHEET);throw $e;}});
}
