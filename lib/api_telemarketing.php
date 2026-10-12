<?php
/** Telemarketing: separate ownership, period coverage, KPI and operational records. */
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/target_number.php';

function tmSchema_(): void {
    static $done = false; if ($done) return;
    $sql = [
        "CREATE TABLE IF NOT EXISTS _tm_profile (nik VARCHAR(100) PRIMARY KEY, admin_pic VARCHAR(100) NOT NULL DEFAULT '', stale_days INT NOT NULL DEFAULT 7, revision INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS _tm_assignment (dealer VARCHAR(100) NOT NULL, brand VARCHAR(100) NOT NULL, nik VARCHAR(100) NOT NULL, sales_nik VARCHAR(100) NOT NULL DEFAULT '', assigned_at CHAR(10) NOT NULL, PRIMARY KEY(dealer,brand), KEY idx_tm(nik)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
        "CREATE TABLE IF NOT EXISTS _tm_scope (nik VARCHAR(100) NOT NULL, period CHAR(7) NOT NULL, data LONGTEXT NOT NULL, actor VARCHAR(100) NOT NULL, updated_at VARCHAR(40) NOT NULL, PRIMARY KEY(nik,period)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS _tm_kpi (nik VARCHAR(100) NOT NULL, period CHAR(7) NOT NULL, data LONGTEXT NOT NULL, revision INT NOT NULL DEFAULT 1, actor VARCHAR(100) NOT NULL, updated_at VARCHAR(40) NOT NULL, PRIMARY KEY(nik,period)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS _tm_kpi_actual_log (id VARCHAR(64) PRIMARY KEY, nik VARCHAR(100) NOT NULL, period CHAR(7) NOT NULL, indicator VARCHAR(60) NOT NULL, actual DOUBLE NULL, actor VARCHAR(100) NOT NULL, recorded_at VARCHAR(40) NOT NULL, KEY idx_tm_actual(nik,period,indicator)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS _tm_fu (id VARCHAR(64) PRIMARY KEY, nik VARCHAR(100) NOT NULL, dealer VARCHAR(100) NOT NULL, brand VARCHAR(100) NOT NULL, date CHAR(10) NOT NULL, next_date CHAR(10) NOT NULL DEFAULT '', data LONGTEXT NOT NULL, created_at VARCHAR(40) NOT NULL, KEY idx_tm_pair(nik,dealer,brand,date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
        "CREATE TABLE IF NOT EXISTS _tm_order (id VARCHAR(64) PRIMARY KEY, nik VARCHAR(100) NOT NULL, dealer VARCHAR(100) NOT NULL DEFAULT '', date CHAR(10) NOT NULL, region VARCHAR(100) NOT NULL DEFAULT '', data LONGTEXT NOT NULL, revision INT NOT NULL DEFAULT 1, created_at VARCHAR(40) NOT NULL, updated_at VARCHAR(40) NOT NULL, KEY idx_tm_date(nik,date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS _tm_business (id VARCHAR(64) PRIMARY KEY, name VARCHAR(180) NOT NULL, logo LONGTEXT NULL, active TINYINT NOT NULL DEFAULT 1, revision INT NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    foreach ($sql as $s) db_()->exec($s); $done = true;
}
function tmToday_(): string { return (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d'); }
function tmNextPeriod_(string $p): string { return (new DateTimeImmutable($p . '-01'))->modify('+1 month')->format('Y-m'); }
function tmJson_($x): string { return json_encode($x, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
function tmQuery_(string $sql, array $args = []): array { tmSchema_(); $q = db_()->prepare($sql); $q->execute($args); return $q->fetchAll(PDO::FETCH_ASSOC); }
function tmExec_(string $sql, array $args): void { tmSchema_(); db_()->prepare($sql)->execute($args); }
function tmProfile_(string $nik): array { return tmQuery_('SELECT * FROM _tm_profile WHERE nik=?', [$nik])[0] ?? ['nik'=>$nik,'admin_pic'=>'','stale_days'=>7,'revision'=>0]; }
function tmCanManage_(array $u, string $nik): bool { return $u['role'] === 'SUPER ADMIN' || ($u['role'] === 'ADMIN' && tmProfile_($nik)['admin_pic'] === $u['nik']); }
function tmTarget_(array $db, string $nik): array { $p = person_($db, $nik); if (!$p || $p['role'] !== 'TELEMARKETING') fail_('Akun Telemarketing tidak ditemukan.'); return $p; }
function tmLivePairs_(string $nik): array { return tmQuery_('SELECT dealer,brand,sales_nik,assigned_at FROM _tm_assignment WHERE nik=? ORDER BY dealer,brand', [$nik]); }
function tmScope_(string $nik, string $period): array {
    PortalCore::month($period);
    $r = tmQuery_('SELECT data,period FROM _tm_scope WHERE nik=? AND period<=? ORDER BY period DESC LIMIT 1', [$nik,$period])[0] ?? null;
    return $r ? array_merge(json_decode($r['data'],true),['effectivePeriod'=>$r['period']]) : ['pairs'=>[],'mode'=>'SELECTED','sales'=>[],'effectivePeriod'=>null];
}
function tmPairKey_(string $dealer, string $brand): string { return $dealer . "\x1f" . js_upper($brand); }
function tmPairSet_(array $pairs): array { $s=[]; foreach ($pairs as $p) $s[tmPairKey_((string)$p['dealer'],(string)$p['brand'])]=true; return $s; }
function tmHasDealer_(array $pairs, string $dealer, ?string $brand = null): bool {
    foreach ($pairs as $p) if ((string)$p['dealer'] === $dealer && ($brand === null || js_upper($p['brand']) === js_upper($brand))) return true;
    return false;
}
function tmPairOwner_(array $db, array $pair): array {
    $o=dealerOwner_($db,$pair['dealer'],$pair['sales_nik']??null);$p=person_($db,$pair['sales_nik']??'');if($p){$o['region']=$p['wilayah']??$o['region'];$o['subregion']=$p['portfolio']?:($p['region']??$o['subregion']);$o['sales']=$p['nama'];$o['salesNik']=$p['nik'];}return $o;
}
function tmPairVisible_(array $db, array $u, array $pair): bool {
    if ($u['role'] === 'SUPER ADMIN' || RegionCore::top($u['role'])) return true;
    $owner = tmPairOwner_($db, $pair);
    if ($u['role'] === 'ADMIN') return in_array($owner['region'], $u['adminRegions'] ?? [], true);
    $ids = PortalCore::scope($db['meta']['people'],$u['nik']);
    return in_array($pair['sales_nik'] ?? $owner['salesNik'], $ids, true) || in_array($owner['salesNik'],$ids,true);
}
function tmCanViewPerson_(array $db, array $u, array $p, ?string $period=null): bool {
    if ($u['role'] === 'TELEMARKETING') return $u['nik']===$p['nik'] || (($p['atasan']??'')!=='' && ($u['atasan']??'')===($p['atasan']??''));
    if ($u['role'] === 'SUPER ADMIN' || RegionCore::top($u['role']) || tmCanManage_($u,$p['nik'])) return true;
    if (!in_array($u['role'],['ASM','RGM','ADMIN'],true)) return false;
    if (($p['atasan'] ?? '') === $u['nik']) return true;
    foreach ($period ? tmScope_($p['nik'],$period)['pairs'] : tmLivePairs_($p['nik']) as $pair) if (tmPairVisible_($db,$u,$pair)) return true;
    return false;
}
function tmVisiblePairs_(array $db, array $u, string $nik, array $pairs): array {
    if ($u['role'] === 'TELEMARKETING') { if ($u['nik'] !== $nik) fail_('Riwayat dealer dan aktivitas Telemarketing lain tidak dapat dibuka.'); return $pairs; }
    if (tmCanManage_($u,$nik)) return $pairs;
    return array_values(array_filter($pairs,fn($p)=>tmPairVisible_($db,$u,$p)));
}
function tmGuardManage_($token, string $nik): array {
    demoAssertWritable_($token); $u = guard_($token); tmTarget_(snapshot_(),$nik);
    if (!tmCanManage_($u,$nik)) fail_('Hanya Admin PIC atau Super Admin yang dapat mengelola Telemarketing ini.'); return $u;
}
function tmConfig_(string $nik, string $period): array {
    $r = tmQuery_('SELECT data,revision FROM _tm_kpi WHERE nik=? AND period=?',[$nik,$period])[0] ?? null;
    return $r ? ['indicators'=>json_decode($r['data'],true),'revision'=>(int)$r['revision']] : ['indicators'=>[],'revision'=>0];
}
function tmNumber_($v, string $label): ?float { $n=targetNumber_($v); if ($n !== null && (!is_finite((float)$n) || $n<0)) fail_($label . ': angka tidak valid. Gunakan 6.500 untuk ribuan atau 6,5 untuk desimal.'); return $n; }
function tmDate_(string $d): string { $t=DateTimeImmutable::createFromFormat('!Y-m-d',$d); if (!$t || $t->format('Y-m-d')!==$d) fail_('Tanggal tidak valid.'); return $d; }

/** Request-local filter: legacy Sales views reuse the same UI with server-enforced pair coverage. */
function tmBeginRequest_(string $fn, array $args): void {
    if (!$args || !is_string($args[0]) || str_starts_with($fn,'apiTM') || in_array($fn,['apiLogin','apiLogin2fa','apiLogout'],true)) return;
    $auth = session_get_('SESSION_' . hash_($args[0])); if (!$auth) return;
    $u = guard_($args[0],false,in_array($fn,['apiChangePin','apiBootstrap'],true));
    if ($u['role'] !== 'TELEMARKETING') return;
    $allowed=['apiBootstrap','apiDashboard','apiV6Boot','apiTransactions','apiTransactionGroups','apiDealerDetail','apiDealerHistory','apiDealerHistorySummary','apiDealerStock','apiDOS','apiAnalisa','apiEarlyWarning','apiPotensi','apiWeeklyAnalysis','apiCompare','apiGrowthType','apiHariLibur','apiExec','apiDealerTutupList','apiDealerContact','apiSubmitDealerContact','apiInfo','apiDownloadInfo','apiMyDevices','apiRenameDevice','apiRemoveDevice','apiChangePin','apiStopDemo'];
    if (!in_array($fn,$allowed,true)) fail_('Menu ini tidak tersedia untuk Telemarketing.');
    $db = snapshot_(); $pairs = tmLivePairs_($u['nik']);
    $GLOBALS['DSM_TM_CONTEXT']=['nik'=>$u['nik'],'pairs'=>$pairs,'set'=>tmPairSet_($pairs),'products'=>$db['meta']['products'],'resolve'=>custMap_($db['meta'])];
}
function tmRowMatches_(array $row, array $ctx): bool {
    $brand = $row['brand'] ?? ($ctx['products'][$row['sku'] ?? '']['brand'] ?? '');
    $customer=(string)($row['customer']??'');if(isset($ctx['resolve']))$customer=(string)$ctx['resolve']($customer);return isset($ctx['set'][tmPairKey_($customer,(string)$brand)]);
}
function tmFilterRows_(array $rows): array { $c=$GLOBALS['DSM_TM_CONTEXT']??null; return $c ? array_values(array_filter($rows,fn($t)=>tmRowMatches_($t,$c))) : $rows; }
function tmScopedSnapshot_(array $db): array {
    $c=$GLOBALS['DSM_TM_CONTEXT']??null; if (!$c) return $db;
    $customers=[];$brands=[];$ids=[];$regions=[];
    foreach ($c['pairs'] as $p) {
        $customers[$p['dealer']]=true; $brands[$p['brand']]=true; $ids[$p['sales_nik']]=true;
        $o=tmPairOwner_($db,$p); if ($o['region']) $regions[$o['region']]=true;
    }
    $db['_tm']=['nik'=>$c['nik'],'pairs'=>$c['pairs'],'regions'=>array_keys($regions)];
    $db['_vid']=$db['id'].'|TM|'.$c['nik'].'|'.md5(tmJson_($c['pairs']));
    $db['meta']['customers']=array_filter($db['meta']['customers'],fn($v,$k)=>isset($customers[$k]),ARRAY_FILTER_USE_BOTH);
    $db['meta']['products']=array_filter($db['meta']['products'],fn($p)=>isset($brands[$p['brand']]));
    // Target summaries must not expose full Sales portfolios for a partial assignment.
    $db['meta']['targets']=[]; $db['meta']['policies']=[]; $db['meta']['rgmPolicies']=[];
    foreach ($db['cubes']??[] as $p=>$rows) { $db['cubes'][$p]=tmFilterRows_($rows); foreach($db['cubes'][$p] as $t)$ids[$t['nik']]=true; }
    if (isset($db['stock']['moves'])) $db['stock']['moves']=tmFilterRows_($db['stock']['moves']);
    if (isset($db['stock']['owners'])) $db['stock']['owners']=array_filter($db['stock']['owners'],fn($v,$k)=>isset($customers[$k]),ARRAY_FILTER_USE_BOTH);
    $db['_tm']['ids']=array_values(array_filter(array_keys($ids),fn($x)=>$x!==''));
    return $db;
}
function tmAllowedIds_(array $db, array $u, $selection): array {
    $ids=$db['_tm']['ids']??[];
    if ($selection && $selection !== 'ALL' && $selection !== $u['nik']) {
        if (!in_array((string)$selection,array_map('strval',$ids),true)) fail_('Sales di luar penugasan Telemarketing.');
        return [(string)$selection];
    }
    return $ids;
}
function tmDashboardFinish_(array $result, array $u, string $period): array {
    if ($u['role']!=='TELEMARKETING') return $result;
    $ctx=$GLOBALS['DSM_TM_CONTEXT'];unset($GLOBALS['DSM_TM_CONTEXT']);PortalCore::resetMemo('TM-FULL-'.$u['nik']);
    try { $k=tmKpiCompute_(snapshot_(),$u['nik'],$period); } finally { $GLOBALS['DSM_TM_CONTEXT']=$ctx; PortalCore::resetMemo('TM-LIVE-'.$u['nik']); }
    $result['kpi']=$k; $result['regionalKpi']=[]; $result['tmKpi']=$k;
    $result['scopeCount']=count(array_unique(array_column($ctx['pairs'],'dealer')));
    return $result;
}

function apiTMAdmin($token) {
    $db=snapshot_();$u=adminGuard_($token,$db);$super=$u['role']==='SUPER ADMIN';
    $people=array_values(array_filter($db['meta']['people'],fn($p)=>$p['role']==='TELEMARKETING' && ($super || tmCanManage_($u,$p['nik']))));
    $list=[];foreach($people as $p)$list[]=array_merge($p,['profile'=>tmProfile_($p['nik']),'pairs'=>tmLivePairs_($p['nik'])]);
    $sales=array_values(array_filter($db['meta']['people'],fn($p)=>!empty($p['sales'])));
    $dealers=[];$brandNames=array_values(array_unique(array_column($db['meta']['products'],'brand')));$brandIndex=dbaIndex_();foreach($db['meta']['customers'] as $id=>$c){$o=dealerOwner_($db,$id);$brandSales=[];foreach($brandNames as $brand)$brandSales[$brand]=dealerBrandOwner_($db,(string)$id,$brand,tmToday_(),$brandIndex)['nik'];$dealers[]=array_merge($c,['dealer'=>(string)$id,'sales_nik'=>$o['salesNik'],'sales'=>$o['sales'],'salesByBrand'=>$brandSales,'bigRegion'=>$o['region'],'subRegion'=>$o['subregion']]);}
    $typesByBrand=[];foreach($db['meta']['products'] as $product){$brand=$product['brand']??'';$type=$product['type']??'';if($brand!==''&&$type!=='')$typesByBrand[$brand][$type]=$type;}foreach($typesByBrand as &$brandTypes)$brandTypes=array_values($brandTypes);unset($brandTypes);
    return ['superuser'=>$super,'people'=>$list,'sales'=>$sales,'dealers'=>$dealers,'brands'=>$brandNames,'types'=>array_values(array_unique(array_filter(array_column($db['meta']['products'],'type')))),'typesByBrand'=>$typesByBrand,'admins'=>array_values(array_filter($db['meta']['people'],fn($p)=>in_array($p['role'],['ADMIN','SUPER ADMIN'],true))),'businesses'=>tmBusinesses_()];
}
function apiTMProfileSave($token, $nik, $input) {
    $u=guard_($token,true);demoAssertWritable_($token);tmTarget_(snapshot_(),$nik);$input=(array)$input;
    return with_lock_(function()use($u,$nik,$input){
        $p=tmProfile_($nik);if((int)($input['revision']??-1)!==(int)$p['revision'])fail_('Pengaturan berubah. Muat ulang dahulu.');
        $admin=text_($input['adminPic']??'');$a=person_(snapshot_(),$admin);if(!$a||!in_array($a['role'],['ADMIN','SUPER ADMIN'],true))fail_('Pilih satu Admin PIC.');
        $days=(int)($input['staleDays']??7);if($days<1||$days>365)fail_('Batas FU harus 1–365 hari.');
        tmExec_('INSERT INTO _tm_profile (nik,admin_pic,stale_days,revision) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE admin_pic=VALUES(admin_pic),stale_days=VALUES(stale_days),revision=revision+1',[$nik,$admin,$days]);
        data_rev_bump_('Telemarketing');audit_($u['nik'],'TM_PROFILE',$nik.' PIC '.$admin);return tmProfile_($nik);
    });
}
function apiTMAssignmentSave($token, $nik, $input) {
    $u=tmGuardManage_($token,$nik);$input=(array)$input;
    return with_lock_(function()use($u,$nik,$input){
        $db=snapshot_();$profile=tmProfile_($nik);if((int)($input['revision']??-1)!==(int)$profile['revision'])fail_('Penugasan berubah. Muat ulang dahulu.');
        $mode=$input['mode']??'SELECTED';if(!in_array($mode,['ALL','SELECTED','MIXED'],true))fail_('Mode penugasan tidak valid.');
        $sales=array_values(array_unique(array_map('text_', (array)($input['sales']??[]))));
        $brands=array_values(array_unique(array_map('js_upper',(array)($input['brands']??[]))));
        $knownBrands=array_unique(array_column($db['meta']['products'],'brand'));if(array_diff($brands,$knownBrands))fail_('Brand tidak dikenal.');
        foreach($sales as $s)if(empty(person_($db,$s)['sales']))fail_('Pilih akun pemegang portofolio Sales.');
        $groups=(array)($input['groups']??[]);$requested=[];
        if($groups){
            if(count($groups)>100)fail_('Maksimal 100 kelompok penugasan.');
            foreach($groups as $g){
                $region=text_($g['region']??'');$brand=js_upper(text_($g['brand']??''));$selectedSales=array_values(array_unique(array_map('text_',(array)($g['sales']??[]))));$selection=$g['mode']??'ALL';
                if(!$region||!in_array($brand,$knownBrands,true)||!$selectedSales||!in_array($selection,['ALL','SELECTED'],true))fail_('Lengkapi region, Sales, brand, dan cakupan dealer.');
                foreach($selectedSales as $s){$person=person_($db,$s);if(!$person||empty($person['sales'])||($person['wilayah']??'')!==$region)fail_('Sales tidak sesuai wilayah kelompok.');}
                if($selection==='ALL'){
                    foreach($db['meta']['customers'] as $id=>$dealer){$owner=dealerBrandOwner_($db,(string)$id,$brand,tmToday_(),dbaIndex_());if(in_array($owner['nik'],$selectedSales,true))$requested[]=['dealer'=>(string)$id,'brand'=>$brand];}
                }else foreach((array)($g['dealers']??[]) as $id){$id=text_($id);$owner=dealerBrandOwner_($db,$id,$brand,tmToday_(),dbaIndex_());if(!isset($db['meta']['customers'][$id])||!in_array($owner['nik'],$selectedSales,true))fail_('Dealer pilihan harus milik Sales pada wilayah dan brand kelompok.');$requested[]=['dealer'=>$id,'brand'=>$brand];}
            }
            $mode=count($groups)===1&&($groups[0]['mode']??'')==='ALL'?'ALL':'MIXED';
            $sales=array_values(array_unique(array_merge(...array_map(fn($g)=>(array)($g['sales']??[]),$groups))));
        }else{
            if($mode!=='SELECTED'&&(!$sales||!$brands))fail_('Pilih Sales dan brand untuk cakupan seluruh dealer Sales.');
            $requested=$mode==='ALL'?[]:(array)($input['pairs']??[]);
            if($mode!=='SELECTED')foreach($db['meta']['customers'] as $id=>$dealer)foreach($brands as $brand){$o=dealerBrandOwner_($db,(string)$id,$brand,tmToday_(),dbaIndex_());if(in_array($o['nik'],$sales,true))$requested[]=['dealer'=>(string)$id,'brand'=>$brand];}
        }
        $existing=tmLivePairs_($nik);$old=[];foreach($existing as $x)$old[tmPairKey_($x['dealer'],$x['brand'])]=$x;
        $pairs=[];foreach($requested as $x){$d=text_($x['dealer']??'');$b=js_upper(text_($x['brand']??''));if(!isset($db['meta']['customers'][$d])||!in_array($b,$knownBrands,true))fail_('Dealer atau brand penugasan tidak dikenal.');$owner=dealerBrandOwner_($db,$d,$b,tmToday_(),dbaIndex_());$key=tmPairKey_($d,$b);$pairs[$key]=['dealer'=>$d,'brand'=>$b,'sales_nik'=>$owner['nik'],'assigned_at'=>$old[$key]['assigned_at']??tmToday_()];}
        $pairs=array_values($pairs);
        foreach($pairs as $x){$other=tmQuery_('SELECT nik FROM _tm_assignment WHERE dealer=? AND brand=? AND nik<>?',[$x['dealer'],$x['brand'],$nik]);if($other)fail_($x['dealer'].' / '.$x['brand'].' sudah ditangani Telemarketing '.$other[0]['nik'].'. Lepaskan penugasan lama dahulu.');}
        $current=substr(tmToday_(),0,7);$first=!tmQuery_('SELECT nik FROM _tm_scope WHERE nik=? LIMIT 1',[$nik]);
        $correct=!empty($input['correctCurrent']);$reason=text_($input['reason']??'');if(strlen($reason)<5)fail_('Isi alasan perubahan minimal 5 karakter.');
        $effective=($correct||$first)?$current:tmNextPeriod_($current);
        $requestedSet=tmPairSet_($pairs);
        foreach($db['meta']['people'] as $other){if($other['role']!=='TELEMARKETING'||$other['nik']===$nik)continue;foreach(tmScope_($other['nik'],$current)['pairs'] as $occupied){if(isset($requestedSet[tmPairKey_($occupied['dealer'],$occupied['brand'])])){if($correct)fail_('Pasangan dealer-brand masih masuk KPI bulan ini milik '.$other['nama'].'. Koreksi pelepasan cakupan bulan ini pada pemegang lama terlebih dahulu.');if($first)$effective=tmNextPeriod_($current);}}}
        assertPeriodOpen_($effective);
        $pdo=db_();$pdo->beginTransaction();try{
            tmExec_('DELETE FROM _tm_assignment WHERE nik=?',[$nik]);
            foreach($pairs as $x)tmExec_('INSERT INTO _tm_assignment (dealer,brand,nik,sales_nik,assigned_at) VALUES (?,?,?,?,?)',[$x['dealer'],$x['brand'],$nik,$x['sales_nik'],$x['assigned_at']]);
            tmExec_('DELETE FROM _tm_scope WHERE nik=? AND period>?',[$nik,$effective]);
            $scope=tmJson_(['pairs'=>$pairs,'mode'=>$mode,'sales'=>$sales,'groups'=>$groups,'reason'=>$reason]);
            tmExec_('INSERT INTO _tm_scope (nik,period,data,actor,updated_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE data=VALUES(data),actor=VALUES(actor),updated_at=VALUES(updated_at)',[$nik,$effective,$scope,$u['nik'],iso_now()]);
            tmExec_('INSERT INTO _tm_profile (nik,revision) VALUES (?,1) ON DUPLICATE KEY UPDATE revision=revision+1',[$nik]);$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        data_rev_bump_('Telemarketing');audit_($u['nik'],$correct?'TM_SCOPE_CORRECTION':'TM_ASSIGNMENT',$nik.' KPI '.$effective.' '.$reason);
        return ['pairs'=>$pairs,'effectivePeriod'=>$effective,'profile'=>tmProfile_($nik)];
    });
}
function apiTMKpiSettings($token, $nik, $period) {
    $u=guard_($token);tmTarget_(snapshot_(),$nik);if(!tmCanManage_($u,$nik))fail_('Akses Admin PIC diperlukan.');PortalCore::month($period);
    return array_merge(tmConfig_($nik,$period),['scope'=>tmScope_($nik,$period)]);
}
function apiTMKpiSave($token, $nik, $period, $input) {
    $u=tmGuardManage_($token,$nik);PortalCore::month($period);assertPeriodOpen_($period);$input=(array)$input;
    return with_lock_(function()use($u,$nik,$period,$input){
        $cfg=tmConfig_($nik,$period);if((int)($input['revision']??-1)!==$cfg['revision'])fail_('KPI berubah. Muat ulang dahulu.');
        $ind=(array)($input['indicators']??[]);if(!$ind||count($ind)>100)fail_('Isi 1–100 indikator.');$out=[];$keys=[];$weights=[];
        $products=snapshot_()['meta']['products'];$knownBrands=array_unique(array_column($products,'brand'));$knownTypes=array_unique(array_filter(array_column($products,'type')));
        foreach($ind as $i){$id=text_($i['id']??'');$metric=js_upper(text_($i['metric']??''));$brand=js_upper(text_($i['brand']??'ALL'));$label=text_($i['label']??'');$group=text_($i['group']??'KPI Telemarketing');$source=$i['source']??'AUTO';
            if(!preg_match('/^[A-Za-z0-9_-]{1,60}$/',$id)||isset($keys[$id])||!in_array($metric,['QTY','DA','NOO','IDDLE','OMZET','MANUAL'],true)||!in_array($source,['AUTO','MANUAL'],true)||!$label||!$group||strlen($label)>180)fail_('ID, label, grup, metrik, atau sumber indikator tidak valid.');$keys[$id]=1;
            if($brand!=='ALL'&&!in_array($brand,$knownBrands,true))fail_('Brand KPI tidak dikenal.');if($metric==='MANUAL')$source='MANUAL';
            $weight=tmNumber_($i['weight']??null,'Bobot');$cap=tmNumber_($i['cap']??100,'Batas skor');if($weight===null||$weight>100||$cap===null||$cap>1000)fail_('Bobot 0–100 dan batas skor 0–1000.');$weights[$group]=($weights[$group]??0)+$weight;
            $target=tmNumber_($i['target']??null,'Target');$actual=$source==='MANUAL'?tmNumber_($i['actual']??null,'Aktual'):null;
            $types=array_values(array_unique(array_filter(array_map('text_', (array)($i['types']??[])))));if(array_diff($types,$knownTypes))fail_('TYPE filter harus dipilih dari daftar produk.');if($metric==='IDDLE'&&$types)fail_('Dealer IDDLE menilai seluruh penjualan brand, tanpa filter TYPE.');$requirements=[];
            foreach((array)($i['requirements']??[]) as $r){$rb=js_upper(text_($r['brand']??$brand));$min=tmNumber_($r['minQty']??1,'Minimum aturan');$rt=array_values(array_filter(array_map('text_', (array)($r['types']??[]))));if(($rb!=='ALL'&&!in_array($rb,$knownBrands,true))||array_diff($rt,$knownTypes)||$min===null||$min<=0)fail_('Aturan brand/TYPE/minimum tidak valid.');$requirements[]=['brand'=>$rb,'types'=>$rt,'minQty'=>$min];}
            if(count($requirements)>50||count($types)>100)fail_('Terlalu banyak aturan atau TYPE.');
            $logic=$i['logic']??'AND';if(!in_array($logic,['AND','OR'],true))fail_('Logika aturan tidak valid.');$minQty=tmNumber_($i['minQty']??($metric==='DA'?2:1),'Minimum dealer');
            $variant=$i['variant']??'BASIC';$perProm=tmNumber_($i['perProm']??3,'Minimal ACC per promotor');if(!in_array($variant,['BASIC','DAQ','IOT','KEY'],true)||($variant==='IOT'&&($metric!=='DA'||$brand==='ALL'||!$perProm)))fail_('Varian Dealer Aktif membutuhkan metrik DA dan brand tertentu.');
            $sm=$i['scopeMode']??'PAIR';if(!in_array($sm,['PAIR','SALES','DEALERS'],true))fail_('Cakupan aktual tidak valid.');$sd=array_values(array_unique(array_filter(array_map('text_',(array)($i['scopeDealers']??[])))));if($sm==='DEALERS'&&(!$sd||count($sd)>500))fail_('Isi 1–500 kode dealer untuk cakupan Dealer tertentu.');
            $out[]=['scopeMode'=>$sm,'scopeDealers'=>$sm==='DEALERS'?$sd:[],'id'=>$id,'metric'=>$metric,'label'=>$label,'group'=>$group,'brand'=>$brand,'source'=>$source,'weight'=>$weight,'cap'=>$cap,'target'=>$target,'actual'=>$actual,'types'=>$types,'minQty'=>$metric==='IDDLE'?1:($minQty??1),'requirements'=>$requirements,'logic'=>$logic,'variant'=>$variant,'perProm'=>$perProm,'inherit'=>!empty($i['inherit'])];
        }
        foreach($weights as $g=>$w)if(abs($w-100)>0.001)fail_('Total bobot grup '.$g.' harus 100%.');
        $reason=text_($input['reason']??'');if(strlen($reason)<5)fail_('Isi alasan minimal 5 karakter.');
        tmExec_('INSERT INTO _tm_kpi (nik,period,data,revision,actor,updated_at) VALUES (?,?,?,1,?,?) ON DUPLICATE KEY UPDATE data=VALUES(data),revision=revision+1,actor=VALUES(actor),updated_at=VALUES(updated_at)',[$nik,$period,tmJson_($out),$u['nik'],iso_now()]);
        $before=[];foreach($cfg['indicators'] as $old)$before[$old['id']]=$old;
        foreach($out as $row)if($row['source']==='MANUAL'&&(!isset($before[$row['id']])||$before[$row['id']]['actual']!==$row['actual']))tmExec_('INSERT INTO _tm_kpi_actual_log (id,nik,period,indicator,actual,actor,recorded_at) VALUES (?,?,?,?,?,?,?)',[uuid_(),$nik,$period,$row['id'],$row['actual'],$u['nik'],iso_now()]);
        data_rev_bump_('Telemarketing');audit_($u['nik'],'TM_KPI_SAVE',$nik.' '.$period.' '.$reason);return tmConfig_($nik,$period);
    });
}
/** Official cubes, same net/device quantities and dealer parent identity as existing Sales. */
function tmScopeRows_(array $db, array $pairs): array {
    $set=tmPairSet_($pairs);$out=[];
    // Assignments follow the same parent dealer identity used by the Sales application.
    foreach($db['cubes']??[] as $period=>$rows)foreach($rows as $r)if(isset($set[tmPairKey_((string)$r['customer'],$r['brand'])]))$out[$period][]=$r;
    return $out;
}
function tmRulePass_(array $rows, array $i): bool {
    $checks=[];foreach($i['requirements']??[] as $r){$q=0;foreach($rows as $t)if(($r['brand']==='ALL'||$r['brand']===$t['brand'])&&(!$r['types']||in_array($t['type'],$r['types'],true)))$q+=$t['q'];$checks[]=$q >= $r['minQty'];}
    return !$checks || (($i['logic']??'AND')==='OR' ? in_array(true,$checks,true) : !in_array(false,$checks,true));
}
function tmActual_(array $db, string $period, array $history, array $i): array {
    $cut=$db['coverage'][$period]['cutoff']??PortalCore::end($period);
    $rows=array_values(array_filter($history[$period]??[],fn($t)=>$t['date']<=$cut));$by=[];
    foreach($rows as $t)$by[$t['customer']][]=$t;
    $used=[];$dealers=[];$metric=$i['metric'];
    foreach($by as $id=>$all){
        if(!tmRulePass_($all,$i) && ($i['variant']??'BASIC')!=='IOT')continue;
        $matching=array_values(array_filter($all,fn($t)=>($i['brand']==='ALL'||$i['brand']===$t['brand'])&&(!$i['types']||in_array($t['type'],$i['types'],true))));
        if(!$matching)continue;
        if(in_array($metric,['DA','NOO','IDDLE'],true)){
            $pairs=[];foreach($matching as $t)$pairs[$t['brand']][]=$t;
            foreach($pairs as $brand=>$transactions){
                $q=array_sum(array_column($transactions,'q'));$ok=$q>=($i['minQty']??1);
                if($metric==='DA'&&($i['variant']??'')==='IOT'){$acc=array_sum(array_column($transactions,'aq'));$ok=$acc>=($i['perProm']??3)*max(1,PortalCore::promotorCount($id,$brand));}
                if($metric==='NOO'){
                    $first=$db['first'][jkey([$id,$brand])]??null;$ok=$ok&&$first&&substr($first['date'],0,7)===$period;
                    if($ok){$seen=false;foreach($transactions as $t)if($t['nik']===$first['nik']&&$t['date']===$first['date'])$seen=true;$ok=$seen;}
                }
                if($metric==='IDDLE'){
                    $p1=PortalCore::prev($period);$p2=PortalCore::prev($p1);$p3=PortalCore::prev($p2);
                    $quant=function($p)use($history,$id,$brand){$n=0;foreach($history[$p]??[] as $t)if($t['customer']===$id&&$t['brand']===$brand)$n+=$t['q'];return $n;};
                    $ok=$q>0&&$quant($p1)<=0&&$quant($p2)<=0&&$quant($p3)>0;
                }
                if($ok){foreach($transactions as $t)$used[]=$t;$dealers[]=['dealer'=>(string)$id,'brand'=>$brand,'qty'=>$q];}
            }
        }else{
            foreach($matching as $t)$used[]=$t;$dealers[]=['dealer'=>(string)$id,'qty'=>array_sum(array_column($matching,'q'))];
        }
    }
    $actual=in_array($metric,['DA','NOO','IDDLE'],true)?count($dealers):array_sum(array_column($used,$metric==='OMZET'?'a':'q'));
    return ['actual'=>$actual,'dealers'=>$dealers];
}
function tmValidation_(array $db,string $period,array $scope,array $indicator,array $counted,?array $history=null): array {
    if($indicator['source']==='MANUAL')return ['counted'=>[],'failed'=>[],'note'=>'Aktual dicatat Admin PIC; tidak bersumber dari transaksi dealer.'];
    $cut=$db['coverage'][$period]['cutoff']??PortalCore::end($period);$allowed=[];
    foreach($scope as $p)$allowed[$p['dealer']]=true;
    $orders=[];$srcRows=$history!==null?($history[$period]??[]):($db['cubes'][$period]??[]);foreach($srcRows as $t)if(($t['date']??'')<=$cut&&($history!==null||isset($allowed[(string)$t['customer']])))$orders[(string)$t['customer']][]=$t;
    $byId=[];foreach($counted as $c)$byId[(string)$c['dealer']]=true;
    $out=['counted'=>[],'failed'=>[]];
    foreach($orders as $dealer=>$rows){
        $matched=array_values(array_filter($rows,fn($r)=>($indicator['brand']==='ALL'||$r['brand']===$indicator['brand'])&&(!$indicator['types']||in_array($r['type'],$indicator['types'],true))));
        $types=[];foreach($rows as $r){$key=$r['brand'].' / '.$r['type'];$types[$key]=($types[$key]??0)+$r['q'];}
        $item=['dealer'=>$dealer,'name'=>$db['meta']['customers'][$dealer]['nama']??$dealer,'sales'=>(($ix=array_search($dealer,array_column($scope,'dealer')))!==false?tmPairOwner_($db,['dealer'=>$dealer,'sales_nik'=>($scope[$ix]['sales_nik']??'')])['sales']:(dealerOwner_($db,$dealer)['sales']??'')),'qty'=>array_sum(array_column($matched,'q')),'value'=>array_sum(array_column($matched,'a')),'orderQty'=>array_sum(array_column($rows,'q')),'orderValue'=>array_sum(array_column($rows,'a')),'types'=>$types];
        $item['reason']=!$matched?'Brand / TYPE indikator belum ada':(!tmRulePass_($rows,$indicator)?'TYPE wajib atau minimum aturan belum terpenuhi':($indicator['metric']==='IDDLE'?'Syarat dua bulan kosong belum terpenuhi':($indicator['metric']==='NOO'?'Bukan dealer baru sesuai transaksi resmi':'Minimum unit belum terpenuhi')));
        $out[isset($byId[$dealer])?'counted':'failed'][]=$item;
    }
    return $out;
}
function tmInheritedTarget_(array $db,string $period,array $scope,array $i): ?float {
    if(($scope['mode']??'')!=='ALL'||empty($scope['sales'])||$i['source']==='MANUAL'||empty($i['inherit']))return null;
    if (!empty($i['types']) || !empty($i['requirements']) || ($i['variant']??'BASIC')!=='BASIC' || ($i['metric']==='DA' && ($i['minQty']??2)!=2)) return null;
    $t=PortalCore::target($db['meta'],$period,$scope['sales'],$i['brand'],$i['metric'],'');
    return !empty($t['exists'])&&empty($t['missing'])?(float)$t['value']:null;
}
/** Cakupan aktual per indikator: PAIR (penugasan dealer-brand, default), SALES (seluruh Sales cakupan), DEALERS (dealer tertentu). */
function tmIndHistory_(array $db,array $i,array $scope,array $history,string $nik): array {
    $mode=$i['scopeMode']??'PAIR';if($mode==='PAIR')return $history;
    $out=[];
    if($mode==='DEALERS'){$set=array_fill_keys(array_map(fn($d)=>custInduk_($db['meta'],$d),(array)($i['scopeDealers']??[])),true);foreach($db['cubes']??[] as $p=>$rows)foreach($rows as $r)if(isset($set[(string)$r['customer']]))$out[$p][]=$r;return $out;}
    $ids=$scope['sales']??[];if(!$ids){$me=person_($db,$nik);$ids=PortalCore::scope($db['meta']['people'],$me['atasan']??'');}
    $set=array_fill_keys(array_map('strval',$ids),true);foreach($db['cubes']??[] as $p=>$rows)foreach($rows as $r)if(isset($set[(string)$r['nik']]))$out[$p][]=$r;return $out;
}
function tmKpiCompute_(array $db,string $nik,string $period): array {
    PortalCore::month($period);PortalCore::resetMemo(($db['id']??'').'-TM-KPI-'.$nik.'-'.$period);$scope=tmScope_($nik,$period);$regions=[];foreach($scope['pairs'] as $pair){$owner=tmPairOwner_($db,$pair);if($owner['region'])$regions[$owner['region']]=true;}if(isset($db['parts'])&&$regions){$cov=RegionCore::common($db['parts'],'ST',$period,array_keys($regions));$cut=$cov['cutoff']?:$cov['latest'];if($cut)$db['coverage'][$period]=['cutoff'=>$cut,'closed'=>$cut===PortalCore::end($period)];}
    $cfg=tmConfig_($nik,$period);$history=tmScopeRows_($db,$scope['pairs']);$groups=[];$pending=false;$details=[];
    foreach($cfg['indicators'] as $i){$group=$i['group'];if(!isset($groups[$group]))$groups[$group]=['name'=>$group,'indicators'=>[],'sum'=>0,'weights'=>0,'pending'=>false];
        $auto=tmInheritedTarget_($db,$period,$scope,$i);$target=$auto!==null?$auto:$i['target'];$a=$i['source']==='MANUAL'?['actual'=>$i['actual'],'dealers'=>[]]:tmActual_($db,$period,tmIndHistory_($db,$i,$scope,$history,$nik),$i);$actual=$a['actual'];
        $complete=true;if($i['source']==='AUTO'){$need=$i['metric']==='IDDLE'?4:1;$p=$period;for($j=0;$j<$need;$j++){$regions=[];foreach($scope['pairs'] as $pair){$o=tmPairOwner_($db,$pair);if($o['region'])$regions[$o['region']]=true;}foreach(array_keys($regions) as $region){$end=$j===0?($db['coverage'][$period]['cutoff']??PortalCore::end($period)):PortalCore::end($p);if(isset($db['parts'])&&(($db['parts']['ST'][$p][$region]['cutoff']??'')<$end))$complete=false;}if(!isset($db['coverage'][$p]))$complete=false;$p=PortalCore::prev($p);}}
        if($i['metric']==='IDDLE'&&!$complete)$actual=null;
        $state=$target===null?'TARGET BELUM DIISI':($target==0?'TIDAK DIWAJIBKAN':($actual===null?'AKTUAL BELUM DIISI':(!$complete?'DATA BELUM LENGKAP':'BERLAKU')));
        $ach=$target!==null&&$target>0&&$actual!==null?$actual/$target*100:null;$score=$ach===null?null:min($i['cap'],max(0,$ach));
        if($target!==null&&$target==0){}elseif($target===null||$actual===null||!$complete){$groups[$group]['pending']=true;$pending=true;}else{$groups[$group]['sum']+=$score*$i['weight'];$groups[$group]['weights']+=$i['weight'];}
        $row=array_merge($i,['indicator'=>$i['id'],'target'=>$target,'actual'=>$actual,'ach'=>$ach,'score'=>$score,'state'=>$state,'targetAuto'=>$auto!==null,'dataComplete'=>$complete]);$groups[$group]['indicators'][]=$row;$details[$i['id']]=$a['dealers'];
    }
    $out=[];$scores=[];foreach($groups as $g){$score=$g['weights']?$g['sum']/$g['weights']:null;$out[]=['name'=>$g['name'],'score'=>$score,'pending'=>$g['pending'],'partial'=>$g['pending']&&$score!==null,'na'=>!$g['pending']&&$score===null,'indicators'=>$g['indicators']];if($score!==null)$scores[]=$score;}
    return ['groups'=>$out,'score'=>$scores?array_sum($scores)/count($scores):null,'status'=>!$out?'KEBIJAKAN BELUM DIISI':($pending?'BELUM FINAL':($scores?'FINAL':'TIDAK DIWAJIBKAN')),'count'=>count($scores),'region'=>'Telemarketing','sub'=>'','tentative'=>$pending,'scope'=>$scope,'details'=>$details];
}
function apiTMKpi($token,$period,$nik=null,$brand=null) {
    $db=snapshot_();$u=guard_($token);$nik=$nik?:$u['nik'];$p=tmTarget_($db,$nik);if(!tmCanViewPerson_($db,$u,$p,$period))fail_('Telemarketing di luar kewenangan.');$k=tmKpiCompute_($db,$nik,$period);
    $limited=$u['role']==='TELEMARKETING'&&$u['nik']!==$nik;
    if($limited){unset($k['scope'],$k['details']);foreach($k['groups'] as &$g)foreach($g['indicators'] as &$i){unset($i['requirements'],$i['types'],$i['details'],$i['inherit']);}unset($g,$i);}
    else{$allowed=tmVisiblePairs_($db,$u,$nik,$k['scope']['pairs']);$k['validation']=[];foreach($k['groups'] as $g)foreach($g['indicators'] as $i){$custom=($i['scopeMode']??'PAIR')!=='PAIR';$v=tmValidation_($db,$period,$k['scope']['pairs'],$i,$k['details'][$i['id']]??[],$custom?tmIndHistory_($db,$i,$k['scope'],tmScopeRows_($db,$k['scope']['pairs']),$nik):null);if(!$custom)foreach(['counted','failed'] as $part)$v[$part]=array_values(array_filter($v[$part]??[],fn($r)=>tmHasDealer_($allowed,$r['dealer'])));$k['validation'][$i['id']]=$v;}foreach($k['details'] as $iid=>&$rows){$ind=null;foreach($k['groups'] as $gg)foreach($gg['indicators'] as $ii)if($ii['id']===$iid)$ind=$ii;if(($ind['scopeMode']??'PAIR')!=='PAIR')continue;$rows=array_values(array_filter($rows,fn($r)=>tmHasDealer_($allowed,$r['dealer'],$r['brand']??null)));}unset($rows);unset($k['scope']);}
    if($brand && $brand!=='ALL')foreach($k['groups'] as &$g)$g['indicators']=array_values(array_filter($g['indicators'],fn($i)=>$i['brand']===$brand));unset($g);
    return ['person'=>['nik'=>$p['nik'],'nama'=>$p['nama'],'atasan'=>$p['atasan']],'limited'=>$limited,'kpi'=>$k];
}
function apiTMRank($token,$period,$brand=null,$scope='PEER') {
    $db=snapshot_();$u=guard_($token);if($u['role']==='SALES')fail_('Peringkat Telemarketing terpisah dari Sales.');$out=[];
    if($scope===null||$scope==='')$scope='PEER'; // api.php mengisi argumen kosong dengan null, default PHP tidak berlaku
    if(!in_array($scope,['PEER','NATIONAL'],true))fail_('Cakupan peringkat tidak valid.');
    foreach($db['meta']['people'] as $p){$canOpen=tmCanViewPerson_($db,$u,$p,$period);if($p['role']!=='TELEMARKETING'||(!$canOpen&&$scope!=='NATIONAL'))continue;$a=account_($p['nik']);if($a&&!PortalCore::active($a['AKTIF']))continue;$k=tmKpiCompute_($db,$p['nik'],$period);$score=$k['score'];
        if($brand&&$brand!=='ALL'){$vals=[];foreach($k['groups'] as $g){$sum=0;$w=0;foreach($g['indicators'] as $i)if($i['brand']===$brand&&$i['score']!==null&&$i['state']==='BERLAKU'){$sum+=$i['score']*$i['weight'];$w+=$i['weight'];}if($w)$vals[]=$sum/$w;}$score=$vals?array_sum($vals)/count($vals):null;}
        $out[]=['nik'=>$p['nik'],'nama'=>$p['nama'],'atasan'=>$p['atasan'],'score'=>$score,'status'=>$k['status'],'mine'=>$p['nik']===$u['nik'],'canOpen'=>$canOpen,'ind'=>$canOpen?array_merge([],...array_map(fn($g)=>array_map(fn($i)=>['key'=>$g['name'].'|'.$i['label'],'label'=>$i['label'],'group'=>$g['name'],'weight'=>$i['weight'],'target'=>$i['target'],'actual'=>$i['actual'],'ach'=>$i['ach'],'score'=>$i['score'],'state'=>$i['state']],$g['indicators']),$k['groups'])):[]];}
    usort($out,fn($a,$b)=>($a['score']===null?($b['score']===null?0:1):($b['score']===null?-1:$b['score']<=>$a['score']))?:strcmp($a['nama'],$b['nama']));$rank=0;$last=null;foreach($out as $j=>&$r){if($r['score']!==null&&$r['status']==='FINAL'){if($last===null||abs($r['score']-$last)>0.000001)$rank=$j+1;$r['rank']=$rank;$last=$r['score'];}else $r['rank']=null;}unset($r);return $out;
}
function tmBusinesses_(): array { $r=tmQuery_('SELECT * FROM _tm_business ORDER BY name');foreach($r as &$x){$x['active']=(bool)$x['active'];$x['revision']=(int)$x['revision'];$x['logo']=$x['logo']?json_decode($x['logo'],true):null;}unset($x);return $r; }
function apiTMBusinessSave($token,$input) {
    $u=adminGuard_($token);demoAssertWritable_($token);$input=(array)$input;$id=text_($input['id']??'')?:uuid_();$name=text_($input['name']??'');if(!$name||strlen($name)>180)fail_('Isi nama badan usaha (maksimal 180 karakter).');
    return with_lock_(function()use($u,$input,$id,$name){$old=tmQuery_('SELECT * FROM _tm_business WHERE id=?',[$id])[0]??null;if((int)($input['revision']??0)!==(int)($old['revision']??0))fail_('Badan usaha berubah, muat ulang.');$logo=$input['logo']??null;
        if($logo){$data=$logo['data']??'';if(!preg_match('/^data:image\/jpeg;base64,([A-Za-z0-9+\/=]+)$/',$data,$m))fail_('Logo harus JPEG.');$raw=base64_decode($m[1],true);$info=$raw===false?false:@getimagesizefromstring($raw);if(!$info||$info[2]!==IMAGETYPE_JPEG||strlen($raw)>1048576||$info[0]>1600||$info[1]>1600)fail_('Logo tidak valid atau terlalu besar.');$logo=['data'=>$data,'width'=>$info[0],'height'=>$info[1]];}
        tmExec_('INSERT INTO _tm_business (id,name,logo,active,revision) VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE name=VALUES(name),logo=VALUES(logo),active=VALUES(active),revision=revision+1',[$id,$name,$logo?tmJson_($logo):null,empty($input['active'])?0:1]);data_rev_bump_('Telemarketing');audit_($u['nik'],'TM_BUSINESS',$id.' '.$name);return tmBusinesses_();});
}
function apiTMBoot($token) {
    $db=snapshot_();$u=guard_($token);if($u['role']!=='TELEMARKETING')fail_('Khusus akun Telemarketing.');$pairs=tmLivePairs_($u['nik']);$dealers=[];
    foreach(array_unique(array_column($pairs,'dealer')) as $id){$c=$db['meta']['customers'][$id]??null;if(!$c)continue;$dp=array_values(array_filter($pairs,fn($x)=>$x['dealer']===$id));$o=tmPairOwner_($db,$dp[0]);$dealers[]=array_merge($c,['name'=>$c['nama'],'phone'=>$c['hp']??'','address'=>$c['alamat']??'','owner'=>$c['pic']??'','sales'=>$o['sales'],'salesByBrand'=>array_map(fn($x)=>['brand'=>$x['brand'],'nik'=>$x['sales_nik'],'name'=>person_($db,$x['sales_nik'])['nama']??$x['sales_nik']],$dp),'bigRegion'=>$o['region'],'subRegion'=>$o['subregion'],'brands'=>array_column($dp,'brand')]);}
    $brands=array_unique(array_column($pairs,'brand'));$products=[];foreach($db['meta']['products'] as $id=>$p)if(in_array($p['brand'],$brands,true))$products[]=array_merge($p,['id'=>(string)$id,'name'=>$p['nama'],'colors'=>['Hitam','Putih','Biru','Hijau','Lainnya']]);
    return ['pairs'=>$pairs,'dealers'=>$dealers,'products'=>$products,'brands'=>array_values(array_unique(array_column($db['meta']['products'],'brand'))),'sales'=>array_values(array_filter($db['meta']['people'],fn($p)=>!empty($p['sales']))),'businesses'=>array_values(array_filter(tmBusinesses_(),fn($b)=>$b['active'])),'profile'=>tmProfile_($u['nik'])];
}
function apiOrderBoot($token) {
    $db=snapshot_();$u=guard_($token);if(!in_array($u['role'],['SALES','ASM','RGM','ADMIN','SUPER ADMIN'],true))fail_('Form order tersedia untuk Sales, ASM, RGM, dan Admin.');
    $ids=PortalCore::scope($db['meta']['people'],$u['nik']);$dealers=[];$brands=array_values(array_unique(array_column($db['meta']['products'],'brand')));$index=dbaIndex_();
    foreach($db['meta']['customers'] as $id=>$c){$owner=dealerOwner_($db,(string)$id);$eligible=in_array($owner['salesNik'],$ids,true)||$owner['salesNik']===$u['nik'];if(!$eligible)foreach($brands as $brand){$brandOwner=dealerBrandOwner_($db,(string)$id,$brand,tmToday_(),$index);if(in_array($brandOwner['nik'],$ids,true)){$eligible=true;break;}}if(!$eligible)continue;$dealers[]=array_merge($c,['id'=>(string)$id,'name'=>$c['nama'],'phone'=>$c['hp']??'','address'=>$c['alamat']??'','owner'=>$c['pic']??'','sales'=>$owner['sales'],'bigRegion'=>$owner['region'],'subRegion'=>$owner['subregion']]);}
    return ['dealers'=>$dealers,'brands'=>array_values(array_unique(array_column($db['meta']['products'],'brand'))),'sales'=>array_values(array_filter($db['meta']['people'],fn($p)=>!empty($p['sales'])&&(in_array($p['nik'],$ids,true)||$p['nik']===$u['nik']))),'businesses'=>array_values(array_filter(tmBusinesses_(),fn($b)=>$b['active'])),'regions'=>array_values(array_unique(array_filter(array_map(fn($d)=>$d['subRegion'],$dealers))))];
}
function apiOrderList($token,$period) {
    PortalCore::month($period);$u=guard_($token);if(!in_array($u['role'],['SALES','ASM','RGM','ADMIN','SUPER ADMIN'],true))fail_('Akses ditolak.');
    $out=[];foreach(tmQuery_('SELECT * FROM _tm_order WHERE nik=? AND date LIKE ? ORDER BY updated_at DESC',[$u['nik'],$period.'-%']) as $r)$out[]=array_merge(json_decode($r['data'],true),['revision'=>(int)$r['revision']]);return $out;
}
function apiTMActivity($token,$period,$nik=null,$filters=null) {
    PortalCore::month($period);$db=snapshot_();$u=guard_($token);$filters=(array)$filters;$people=[];
    foreach($db['meta']['people'] as $p)if($p['role']==='TELEMARKETING'&&($u['role']==='TELEMARKETING'?$p['nik']===$u['nik']:tmCanViewPerson_($db,$u,$p)))$people[]=['nik'=>$p['nik'],'nama'=>$p['nama']];
    $nik=$nik?:($u['role']==='TELEMARKETING'?$u['nik']:($people[0]['nik']??''));if(!$nik)return ['people'=>$people,'dealers'=>[],'fu'=>[],'orders'=>[]];$p=tmTarget_($db,$nik);if(!tmCanViewPerson_($db,$u,$p))fail_('Telemarketing di luar kewenangan.');
    $live=tmLivePairs_($nik);$visible=tmVisiblePairs_($db,$u,$nik,$live);$allFU=tmQuery_('SELECT * FROM _tm_fu WHERE nik=? ORDER BY date DESC,created_at DESC',[$nik]);$today=tmToday_();$threshold=(int)tmProfile_($nik)['stale_days'];$queue=[];$fu=[];
    $accept=function($pair)use($db,$filters){$c=$db['meta']['customers'][$pair['dealer']]??[];$o=tmPairOwner_($db,$pair);return (empty($filters['brand'])||$filters['brand']==='ALL'||$filters['brand']===$pair['brand'])&&(empty($filters['big'])||$filters['big']==='ALL'||$filters['big']===$o['region'])&&(empty($filters['region'])||$filters['region']==='ALL'||$filters['region']===$o['subregion'])&&(empty($filters['q'])||mb_stripos(($c['nama']??'').' '.$pair['dealer'].' '.$o['sales'],$filters['q'])!==false);};
    foreach($visible as $pair){if(!$accept($pair))continue;$last=null;foreach($allFU as $r)if($r['dealer']===$pair['dealer']&&$r['brand']===$pair['brand']&&$r['date']<=$today){$last=$r;break;}$base=$last?$last['date']:$pair['assigned_at'];$age=max(0,(int)(new DateTimeImmutable($base))->diff(new DateTimeImmutable($today))->days);$next=$last['next_date']??'';$state=!$last?'NEVER':($next&&$next<$today?'OVERDUE':($next===$today?'TODAY':($age>=$threshold?'STALE':'OK')));$c=$db['meta']['customers'][$pair['dealer']]??[];$o=tmPairOwner_($db,$pair);$row=array_merge($pair,['name'=>$c['nama']??$pair['dealer'],'phone'=>$c['hp']??'','sales'=>$o['sales'],'big'=>$o['region'],'region'=>$o['subregion'],'last'=>$last['date']??null,'next'=>$next,'age'=>$age,'state'=>$state]);if(empty($filters['state'])||$filters['state']==='ALL'||$filters['state']===$state)$queue[]=$row;}
    foreach($allFU as $r)if(substr($r['date'],0,7)===$period&&tmHasDealer_($visible,$r['dealer'],$r['brand'])&&$accept($r))$fu[]=array_merge(json_decode($r['data'],true),['id'=>$r['id'],'dealer'=>$r['dealer'],'brand'=>$r['brand'],'date'=>$r['date'],'next'=>$r['next_date']]);
    usort($queue,fn($a,$b)=>(['OVERDUE'=>0,'TODAY'=>1,'NEVER'=>2,'STALE'=>3,'OK'=>4][$a['state']]<=>['OVERDUE'=>0,'TODAY'=>1,'NEVER'=>2,'STALE'=>3,'OK'=>4][$b['state']])?:$b['age']<=>$a['age']);
    $orders=[];foreach(tmQuery_('SELECT * FROM _tm_order WHERE nik=? AND date LIKE ? ORDER BY updated_at DESC',[$nik,$period.'-%']) as $r){$o=json_decode($r['data'],true);$full=($u['role']==='TELEMARKETING'&&$u['nik']===$nik)||tmCanManage_($u,$nik)||$u['role']==='SUPER ADMIN'||RegionCore::top($u['role']);if(!$full){if(!$r['dealer']||!tmHasDealer_($visible,$r['dealer']))continue;$brands=array_unique(array_column($o['items'],'brand'));if(array_filter($brands,fn($b)=>!tmHasDealer_($visible,$r['dealer'],$b)))continue;}if(!empty($filters['brand'])&&$filters['brand']!=='ALL'&&!in_array($filters['brand'],array_column($o['items'],'brand'),true))continue;if(!empty($filters['region'])&&$filters['region']!=='ALL'&&$o['region']!==$filters['region'])continue;$orders[]=array_merge($o,['id'=>$r['id'],'revision'=>(int)$r['revision']]);}
    return ['people'=>$people,'nik'=>$nik,'dealers'=>$queue,'fu'=>$fu,'orders'=>$orders,'staleDays'=>$threshold,'fullKpiVisible'=>true,'scopeCount'=>count($visible),'totalAssigned'=>count($live)];
}
/** Team overview + daily text report (Telemarketing activity, all roles that may see Telemarketing). */
function tmTeamPeople_(array $db,array $u,string $period): array {
    $ok=in_array($u['role'],['TELEMARKETING','ASM','RGM','ADMIN','SUPER ADMIN'],true)||RegionCore::top($u['role']);
    if(!$ok)fail_('Akses ditolak.');$out=[];
    foreach($db['meta']['people'] as $p){if(($p['role']??'')!=='TELEMARKETING')continue;if($u['role']==='TELEMARKETING'||tmCanViewPerson_($db,$u,$p,$period))$out[]=$p;}
    return $out;
}
/** Sub region + Big Region for one dealer of one Telemarketing (assignment owner first, dealer master second). */
function tmDealerPlace_(array $db,array $pairsOfNik,string $dealer,string $brand,string $manualRegion=''): array {
    static $memo=[];$key=$dealer."\x1f".$brand."\x1f".$manualRegion;
    foreach($pairsOfNik as $p)if($p['dealer']===$dealer&&($brand===''||$p['brand']===$brand)){$o=tmPairOwner_($db,$p);$sub=trim((string)$o['subregion']);$big=trim((string)$o['region']);if($sub!==''||$big!=='')return [$sub!==''?$sub:'Lainnya',$big!==''?js_upper($big):'LAINNYA'];}
    if($dealer!==''&&!empty($db['meta']['customers'][$dealer])){$o=dealerOwner_($db,$dealer);$sub=trim((string)$o['subregion']);$big=trim((string)$o['region']);if($sub!==''||$big!=='')return [$sub!==''?$sub:'Lainnya',$big!==''?js_upper($big):'LAINNYA'];}
    $m=trim($manualRegion);return [$m!==''?$m:'Lainnya','LAINNYA'];
}
function tmOrderQty_(array $o): float { $q=0;foreach((array)($o['items']??[]) as $i)$q+=(float)($i['qty']??0);return $q; }
function tmTeamData_(array $db,array $u,string $period): array {
    $today=tmToday_();$people=tmTeamPeople_($db,$u,$period);$niks=array_map(fn($p)=>$p['nik'],$people);$rows=[];
    if(!$niks)return ['today'=>$today,'rows'=>[],'period'=>$period];
    $in=implode(',',array_fill(0,count($niks),'?'));
    $pairs=[];foreach(tmQuery_("SELECT dealer,brand,nik,sales_nik,assigned_at FROM _tm_assignment WHERE nik IN ($in)",$niks) as $r)$pairs[$r['nik']][]=$r;
    $last=[];foreach(tmQuery_("SELECT nik,dealer,brand,date,next_date FROM _tm_fu WHERE nik IN ($in) AND date<=? AND date>=? ORDER BY date DESC,created_at DESC",array_merge($niks,[$today,date('Y-m-d',strtotime($today.' -400 days'))])) as $r){$k=$r['nik']."\x1f".$r['dealer']."\x1f".$r['brand'];if(!isset($last[$k]))$last[$k]=$r;}
    $dealersFU=[];foreach(tmQuery_("SELECT nik,COUNT(DISTINCT dealer) AS n FROM _tm_fu WHERE nik IN ($in) AND date LIKE ? AND date<=? GROUP BY nik",array_merge($niks,[substr($today,0,7).'-%',$today])) as $r)$dealersFU[$r['nik']]=(int)$r['n'];
    $owner=[];foreach($pairs as $n=>$list)foreach($list as $pr)$owner[tmPairKey_((string)$pr['dealer'],(string)$pr['brand'])]=$n;
    $stMtd=[];$cut=$db['coverage'][$period]['cutoff']??PortalCore::end($period);
    foreach($db['cubes'][$period]??[] as $cr){if(($cr['date']??'')>$cut)continue;$n=$owner[tmPairKey_((string)$cr['customer'],(string)$cr['brand'])]??null;if($n!==null)$stMtd[$n]=($stMtd[$n]??0)+(float)$cr['q'];}
    $fuDone=[];foreach(tmQuery_("SELECT nik,dealer,brand FROM _tm_fu WHERE nik IN ($in) AND date=?",array_merge($niks,[$today])) as $r)$fuDone[$r['nik']][]=$r;
    $orders=[];foreach(tmQuery_("SELECT nik,dealer,date,region,data FROM _tm_order WHERE nik IN ($in) AND (date LIKE ? OR date=?)",array_merge($niks,[$period.'-%',$today])) as $r){$o=json_decode($r['data'],true);if(($o['status']??'')!=='SAVED')continue;$orders[$r['nik']][]=['dealer'=>$r['dealer'],'date'=>$r['date'],'region'=>$r['region'],'qty'=>tmOrderQty_($o)];}
    foreach($people as $p){
        $nik=$p['nik'];$pp=$pairs[$nik]??[];$subCount=[];$dealers=[];$sched=0;
        foreach($pp as $pair){$dealers[$pair['dealer']]=true;$pl=tmDealerPlace_($db,$pp,$pair['dealer'],$pair['brand']);$subCount[$pl[0]]=($subCount[$pl[0]]??0)+1;$l=$last[$nik."\x1f".$pair['dealer']."\x1f".$pair['brand']]??null;if(($l&&$l['next_date']===$today)||(!$l&&($pair['assigned_at']??'')<=$today))$sched++;}
        arsort($subCount);$subs=array_keys($subCount);
        $mtd=count(array_filter($orders[$nik]??[],fn($o)=>strpos($o['date'],$period.'-')===0));$tod=0;$byPlace=[];
        foreach($orders[$nik]??[] as $o)if($o['date']===$today){$tod++;$pl=tmDealerPlace_($db,$pp,$o['dealer'],'',$o['region']);$byPlace[$pl[0]]=['big'=>$pl[1],'st'=>($byPlace[$pl[0]]['st']??0)+$o['qty'],'fu'=>$byPlace[$pl[0]]['fu']??0];}
        foreach($fuDone[$nik]??[] as $f){$pl=tmDealerPlace_($db,$pp,$f['dealer'],$f['brand']);$byPlace[$pl[0]]=['big'=>$pl[1],'st'=>$byPlace[$pl[0]]['st']??0,'fu'=>($byPlace[$pl[0]]['fu']??0)+1];}
        $boss=person_($db,$p['atasan']??null);$score=null;
        try{$k=tmKpiCompute_($db,$nik,$period);$score=$k['score']??null;}catch(Throwable $e){$score=null;}
        $rows[]=['nik'=>$nik,'nama'=>$p['nama'],'atasan'=>$boss['nama']??'','atasanRole'=>$boss['role']??'','subs'=>$subs,'dealers'=>count($dealers),'fuScheduled'=>$sched,'fuDone'=>count($fuDone[$nik]??[]),'orderToday'=>$tod,'orderMtd'=>$mtd,'stToday'=>array_sum(array_column($byPlace,'st')),'fuTotal'=>array_sum(array_column($byPlace,'fu')),'dealersFU'=>$dealersFU[$nik]??0,'stMtd'=>(int)round($stMtd[$nik]??0),'score'=>$score,'places'=>$byPlace];
    }
    usort($rows,fn($a,$b)=>($b['fuScheduled']<=>$a['fuScheduled'])?:strcmp($a['nama'],$b['nama']));
    return ['today'=>$today,'period'=>$period,'rows'=>$rows];
}
function apiTMTeam($token,$period) {
    PortalCore::month($period);$db=snapshot_();$u=guard_($token);$d=tmTeamData_($db,$u,$period);
    foreach($d['rows'] as &$r){$pl=[];foreach($r['places'] as $name=>$x)$pl[]=['sub'=>$name,'st'=>(int)round($x['st']),'fu'=>(int)$x['fu']];usort($pl,fn($a,$b)=>($b['st']<=>$a['st'])?:($b['fu']<=>$a['fu']));$r['places']=$pl;}unset($r);
    $d['canExport']=in_array($u['role'],['ADMIN','SUPER ADMIN'],true);return $d;
}
function apiTMDailyReport($token) {
    $db=snapshot_();$u=guard_($token);$today=tmToday_();$d=tmTeamData_($db,$u,substr($today,0,7));$rows=$d['rows'];
    foreach($rows as &$r){$r['total']=array_sum(array_column($r['places'],'st'));$r['fuTotal']=array_sum(array_column($r['places'],'fu'));}unset($r);
    usort($rows,fn($a,$b)=>($b['total']<=>$a['total'])?:strcmp($a['nama'],$b['nama']));
    $days=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];$mon=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];$t=new DateTimeImmutable($today);
    $n=fn($x)=>number_format((float)$x,0,',','.');
    $L=['DAILY REPORT - TELEMARKETING','===========================',$days[(int)$t->format('w')].', '.(int)$t->format('j').' '.$mon[(int)$t->format('n')].' '.$t->format('Y'),'','NAMA | SUB REGION : Sell Thru - (FU)','==========================='];
    $big=[];$gst=0;$gfu=0;
    foreach($rows as $i=>$r){
        $L[]=$r['nama'];$pl=$r['places'];uasort($pl,fn($a,$b)=>($b['st']<=>$a['st'])?:($b['fu']<=>$a['fu']));
        foreach($pl as $name=>$x){$L[]='* '.$name.' : '.$n($x['st']).' - ('.$n($x['fu']).')';$big[$x['big']]=['st'=>($big[$x['big']]['st']??0)+$x['st'],'fu'=>($big[$x['big']]['fu']??0)+$x['fu']];}
        if(count($pl)!==1)$L[]='Total : '.$n($r['total']).' - ('.$n($r['fuTotal']).')';
        $gst+=$r['total'];$gfu+=$r['fuTotal'];if($i<count($rows)-1)$L[]='';
    }
    $L[]='===========================';uasort($big,fn($a,$b)=>$b['st']<=>$a['st']);
    foreach($big as $name=>$x)$L[]=$name.' : '.$n($x['st']).' - ('.$n($x['fu']).')';
    $L[]='__________________________________';$L[]='GRAND TOTAL : '.$n($gst).' - ('.$n($gfu).')';
    return ['date'=>$today,'text'=>implode("\n",$L),'count'=>count($rows)];
}
function apiTMFollowUpSave($token,$input) {
    demoAssertWritable_($token);$u=guard_($token);if($u['role']!=='TELEMARKETING')fail_('FU dicatat oleh Telemarketing.');$input=(array)$input;
    return with_lock_(function()use($u,$input){$dealer=text_($input['dealer']??'');$brand=js_upper(text_($input['brand']??''));if(!tmHasDealer_(tmLivePairs_($u['nik']),$dealer,$brand))fail_('Dealer dan brand di luar penugasan.');$date=tmDate_(text_($input['date']??''));if($date>tmToday_())fail_('Tanggal FU tidak boleh di masa depan.');$next=text_($input['next']??'');if($next&&tmDate_($next)<$date)fail_('Tanggal FU berikutnya harus sesudah atau sama dengan tanggal FU.');$method=text_($input['method']??'');$result=text_($input['result']??'');$note=text_($input['note']??'');if(!in_array($method,['WhatsApp','Telepon','Kunjungan','Lainnya'],true)||!$result||mb_strlen($result)>180||mb_strlen($note)>4000)fail_('Isi metode dan hasil FU.');$id=uuid_();tmExec_('INSERT INTO _tm_fu (id,nik,dealer,brand,date,next_date,data,created_at) VALUES (?,?,?,?,?,?,?,?)',[$id,$u['nik'],$dealer,$brand,$date,$next,tmJson_(['method'=>$method,'result'=>$result,'note'=>$note]),iso_now()]);data_rev_bump_('Telemarketing');audit_($u['nik'],'TM_FU',$dealer.' '.$brand.' '.$result);return ['id'=>$id];});
}
function apiTMOrderSave($token,$input) {
    demoAssertWritable_($token);$u=guard_($token);if(!in_array($u['role'],['TELEMARKETING','SALES','ASM','RGM','ADMIN','SUPER ADMIN'],true))fail_('Akses form order ditolak.');$input=(array)$input;
    return with_lock_(function()use($u,$input){$db=snapshot_();$id=text_($input['id']??'')?:uuid_();$old=tmQuery_('SELECT * FROM _tm_order WHERE id=?',[$id])[0]??null;if($old&&$old['nik']!==$u['nik'])fail_('Order milik akun lain.');if((int)($input['revision']??0)!==(int)($old['revision']??0))fail_('Order berubah. Buka ulang order.');
        $mode=$input['dealerMode']??'master';$dealer=$mode==='master'?text_($input['dealer']??''):'';if(!in_array($mode,['master','manual'],true))fail_('Mode dealer tidak valid.');
        if($mode==='master'){$allowed=$u['role']==='TELEMARKETING'?array_unique(array_column(tmLivePairs_($u['nik']),'dealer')):array_column(apiOrderBoot($token)['dealers'],'id');if(!in_array($dealer,$allowed,true))fail_('Dealer di luar penugasan.');}
        $business=tmQuery_('SELECT * FROM _tm_business WHERE id=? AND active=1',[text_($input['businessId']??'')])[0]??null;if(!$business)fail_('Pilih badan usaha aktif.');
        $d=$mode==='master'?($db['meta']['customers'][$dealer]??[]):(array)($input['manualDealer']??[]);$edit=(array)($input['dealerSnapshot']??[]);$snap=['id'=>$dealer,'name'=>text_($d['nama']??$d['name']??''),'address'=>text_($edit['address']??$d['alamat']??$d['address']??''),'phone'=>text_($edit['phone']??$d['hp']??$d['phone']??''),'owner'=>text_($edit['owner']??$d['pic']??$d['owner']??'')];if($mode==='manual')$snap['name']=text_($edit['name']??$snap['name']);if(!$snap['name']||strlen($snap['name'])>180||strlen($snap['address'])>1000||strlen($snap['phone'])>40||strlen($snap['owner'])>180)fail_('Identitas dealer tidak valid.');
        $status=$input['status']??'DRAFT';if(!in_array($status,['DRAFT','SAVED'],true))fail_('Status order tidak valid.');$sales=text_($input['sales']??'');$region=text_($input['region']??'');$ref=text_($input['refnote']??'');$notes=text_($input['notes']??'');$date=tmDate_(text_($input['date']??''));if(!$sales||!$region||strlen($sales)>180||strlen($region)>100||strlen($ref)>300||strlen($notes)>4000)fail_('Isi sales person dan region.');
        $items=[];foreach((array)($input['items']??[]) as $i){if(count($items)>=100)fail_('Maksimal 100 barang.');$name=text_($i['name']??'');$brand=js_upper(text_($i['brand']??''));$color=text_($i['color']??'');$custom=text_($i['customColor']??'');$q=tmNumber_($i['qty']??null,'Jumlah');$price=tmNumber_($i['price']??null,'Harga');if(!$name||!$brand||!$color||$q===null||$q<1||floor($q)!=$q||$q>1000000||strlen($name)>180||strlen($brand)>100||strlen($custom)>80||strlen($color)>80||($price!==null&&$price>1e12))fail_('Lengkapi nama barang, brand, warna, dan jumlah bulat minimal 1.');$items[]=['uid'=>text_($i['uid']??'')?:uuid_(),'mode'=>'manual','product'=>'','name'=>$name,'brand'=>$brand,'color'=>$color,'customColor'=>$custom,'qty'=>$q,'price'=>$price===null?'':$price];}
        if(!$items)fail_('Tambahkan minimal satu barang.');$bu=['id'=>$business['id'],'name'=>$business['name'],'logo'=>$business['logo']?json_decode($business['logo'],true):null,'initial'=>mb_substr($business['name'],0,2),'color'=>'#0b5cad'];
        $creditMode=$input['creditMode']??'NOMINAL';$credit=tmNumber_($input['creditNote']??0,'Credit Note')??0;if(!in_array($creditMode,['NOMINAL','PERCENT'],true)||($creditMode==='PERCENT'&&$credit>100))fail_('Credit Note tidak valid.');$subtotal=array_sum(array_map(fn($i)=>(float)$i['qty']*(float)$i['price'],$items));$discount=$creditMode==='PERCENT'?$subtotal*$credit/100:$credit;if($discount>$subtotal)fail_('Credit Note tidak boleh melebihi subtotal.');
        $payment=$input['payment']??'CBD';$days=(int)($input['topDays']??0);if(!in_array($payment,['CBD','COD','TOP'],true)||($payment==='TOP'&&($days<1||$days>365)))fail_('Jenis order / tempo tidak valid.');
        $o=['id'=>$id,'number'=>$old?json_decode($old['data'],true)['number']:($u['role']==='TELEMARKETING'?'TM-':'OR-').str_replace('-','',$date).'-'.strtoupper(substr(str_replace('-','',$id),0,8)),'dealerMode'=>$mode,'dealer'=>$dealer,'dealerSnapshot'=>$snap,'manualDealer'=>$snap,'businessId'=>$bu['id'],'businessSnapshot'=>$bu,'company'=>$bu['name'],'date'=>$date,'region'=>$region,'refnote'=>$ref?:'-','sales'=>$sales,'createdBy'=>$u['nama'],'items'=>$items,'notes'=>$notes,'showPrice'=>!empty($input['showPrice']),'creditMode'=>$creditMode,'creditNote'=>$credit,'payment'=>$payment,'topDays'=>$payment==='TOP'?$days:0,'status'=>$status];
        tmExec_('INSERT INTO _tm_order (id,nik,dealer,date,region,data,revision,created_at,updated_at) VALUES (?,?,?,?,?,?,1,?,?) ON DUPLICATE KEY UPDATE date=VALUES(date),region=VALUES(region),data=VALUES(data),revision=revision+1,updated_at=VALUES(updated_at)',[$id,$u['nik'],$dealer,$date,$region,tmJson_($o),iso_now(),iso_now()]);data_rev_bump_('Telemarketing');audit_($u['nik'],'TM_ORDER',$id.' '.$status);$o['revision']=(int)($old['revision']??0)+1;return $o;});
}

/** Keep operational records and period coverage attached to their original account. */
function tmAccountReferences_(string $nik): array {
    $refs=[];
    $checks=[['_tm_profile','nik=? OR admin_pic=?',[$nik,$nik]],['_tm_assignment','nik=? OR sales_nik=?',[$nik,$nik]],['_tm_scope','nik=?',[$nik]],['_tm_kpi','nik=?',[$nik]],['_tm_kpi_actual_log','nik=? OR actor=?',[$nik,$nik]],['_tm_fu','nik=?',[$nik]],['_tm_order','nik=?',[$nik]]];
    foreach($checks as [$table,$where,$args]){$n=(int)(tmQuery_('SELECT COUNT(*) AS n FROM '.$table.' WHERE '.$where,$args)[0]['n']??0);if($n)$refs[]=['sheet'=>$table,'col'=>'nik','what'=>'referensi Telemarketing','count'=>$n];}
    foreach(tmQuery_('SELECT nik,data FROM _tm_scope WHERE nik<>?',[$nik]) as $row){$scope=json_decode($row['data'],true);foreach($scope['pairs']??[] as $p)if(($p['sales_nik']??'')===$nik){$refs[]=['sheet'=>'_tm_scope','col'=>'sales_nik','what'=>'histori cakupan KPI Telemarketing','count'=>1];break;}}
    return $refs;
}

/** Read-only dealer audit for the signed-in Sales / ASM / RGM KPI. */
function apiKpiValidation($token,$period,$groupIndex,$indicatorIndex,$asmFilter=null,$salesFilter=null,$asOf=null) {
    PortalCore::month($period);$source=snapshot_();$actor=guard_($token,false,false,$source);$asOf=text_($asOf??'');
    if($asOf!==''){
        if(!in_array($actor['role'],['ADMIN','SUPER ADMIN'],true))fail_('Kroscek validasi KPI hanya untuk Admin dan Super Admin.');
        $u=person_($source,$asOf);if(!$u||!in_array($u['role'],['SALES','ASM','RGM'],true))fail_('Validasi KPI hanya untuk Sales, ASM, dan RGM.');
        if(!in_array($actor['role'],['SUPER ADMIN'],true)&&!in_array($u['wilayah']??'',$actor['adminRegions']??[],true))fail_('Di luar wilayah Admin.');
        audit_($actor['nik'],'ADMIN_VALIDASI_KPI',$asOf.' '.$period);
    }else{
        if(!in_array($actor['role'],['SALES','ASM','RGM'],true))fail_('Validasi KPI hanya tersedia untuk KPI sendiri.');
        $u=$actor;
    }
    return kpiValidationCompute_($source,$u,$actor,$period,$groupIndex,$indicatorIndex,$asmFilter,$salesFilter,null);
}
function kpiValidationCompute_($source,$u,$actor,$period,$groupIndex,$indicatorIndex,$asmFilter,$salesFilter,$ctx) {
    $ids=$ctx['ids']??PortalCore::scope($source['meta']['people'],$u['nik']);$db=$ctx['db']??coverageView_($source,$period,$ids,'ST',$actor);
    $k=$ctx['k']??PortalCore::kpi($db,$period,$ids,$u['wilayah']??null,$u);
    $group=$k['groups'][(int)$groupIndex]??null;$i=$group['indicators'][(int)$indicatorIndex]??null;if(!$i)fail_('Indikator KPI tidak ditemukan.');
    $rgmPolicy=$u['role']==='RGM'?array_values(array_filter($db['meta']['rgmPolicies']??[],fn($r)=>$r['period']===$period&&$r['nik']===$u['nik'])):[];
    $policy=$rgmPolicy?:array_values(array_filter($db['meta']['policies']??[],fn($r)=>$r['period']===$period&&$r['region']===(($k['sub']??'')?:($u['wilayah']??''))));
    $configured=array_values(array_filter($policy,fn($r)=>$r['group']===$group['name']))[(int)$indicatorIndex]??[];
    $must=$configured['must']??[];$perProm=(float)($configured['perProm']??0);$minNOO=(float)($configured['minNOO']??1);$bigs=!empty($i['regionBased'])?array_map('js_upper',$i['bigs']??[]):[];
    $cut=$db['coverage'][$period]['cutoff']??PortalCore::end($period);$by=[];$scope=array_fill_keys(array_map('strval',$ids),true);
    $allRows=$bigs?array_merge(...array_map(fn($big)=>rgmRegionRows_($period)[$big]??[],$bigs)):($db['cubes'][$period]??[]);
    foreach($allRows as $r)if(($r['date']??'')<=$cut&&($bigs||isset($scope[(string)$r['nik']])))$by[(string)$r['customer']][]=$r;
    if($bigs)foreach($allRows as $r)$scope[(string)$r['nik']]=true;
    $filterSales=text_($salesFilter??'');$filterAsm=text_($asmFilter??'');$selSales=$filterSales?person_($source,$filterSales):null;if($filterSales&&!isset($scope[$filterSales])&&!($bigs&&$selSales&&in_array(js_upper($selSales['wilayah']??''),$bigs,true)))fail_('Sales di luar tim.');
    if($filterAsm){$asm=person_($source,$filterAsm);if(!$asm||$asm['role']!=='ASM'||(!in_array($filterAsm,$ids,true)&&!($bigs&&in_array(js_upper($asm['wilayah']??''),$bigs,true))))fail_('ASM di luar tim.');}
    $result=['indicator'=>$i,'rule'=>['perProm'=>$perProm,'must'=>array_values($must),'min'=>(float)($i['minDA']??0)],'score'=>$k['score'],'counted'=>[],'failed'=>[],'filters'=>['asms'=>[],'sales'=>[]],'filtered'=>['dealers'=>0,'qty'=>0,'value'=>0]];
    foreach($source['meta']['people'] as $p)if(isset($scope[(string)$p['nik']])||($bigs&&in_array(js_upper($p['wilayah']??''),$bigs,true))){if($p['role']==='ASM')$result['filters']['asms'][]=['nik'=>$p['nik'],'nama'=>$p['nama']];if(!empty($p['sales']))$result['filters']['sales'][]=['nik'=>$p['nik'],'nama'=>$p['nama']];}
    foreach($by as $dealer=>$rows){
        $owner=dealerOwner_($source,$dealer);
        $brand=$i['brand']??'ALL';$types=$i['types']??[];$matched=array_values(array_filter($rows,fn($r)=>($brand==='ALL'||$r['brand']===$brand)&&(!$types||in_array($r['type'],$types,true))));
        $brandOwner=$brand==='ALL'?null:dealerBrandOwner_($source,$dealer,$brand,$cut,dbaIndex_());$salesNik=$brandOwner['nik']??($matched[0]['nik']??$owner['salesNik']);$sales=person_($source,$salesNik);$asmNik='';$cursor=$sales;$seen=[];while($cursor&&!isset($seen[$cursor['nik']])){$seen[$cursor['nik']]=true;if($cursor['role']==='ASM'){$asmNik=$cursor['nik'];break;}$cursor=person_($source,$cursor['atasan']??'');}
        if($filterSales&&$salesNik!==$filterSales)continue;if($filterAsm&&$asmNik!==$filterAsm)continue;
        $q=array_sum(array_column($matched,'q'));$value=array_sum(array_column($matched,'a'));$mustQty=0;foreach($matched as $r)if(in_array($r['type'],$must,true))$mustQty+=$r['q'];
        $metric=$i['metric'];$ok=$metric==='OMZET'?$value>0:$q>0;$reason='Brand / TYPE indikator belum ada';$extra=[];
        if($metric==='DA'){
            $min=(float)($i['minDA']??2);
            if($perProm>0){$acc=array_sum(array_column($matched,'aq'));$prom=PortalCore::promotorCount($dealer,$brand);$threshold=$perProm*max(1,$prom);$ok=$acc>=$threshold;$extra=['acc'=>$acc,'prom'=>$prom,'need'=>$threshold];$reason=$prom?'Kurang '.number_format(max(0,$threshold-$acc),0,',','.').' unit ACC ('.$prom.' promotor × '.number_format($perProm,0,',','.').')':'Tanpa promotor: minimal '.number_format($threshold,0,',','.').' unit ACC, kurang '.number_format(max(0,$threshold-$acc),0,',','.');}
            else{$ok=$q>=$min&&(!$must||$mustQty>=1);$mt=[];foreach($must as $mtp){$mt[$mtp]=0;}foreach($matched as $r)if(isset($mt[$r['type']]))$mt[$r['type']]+=$r['q'];$extra=['mustTypes'=>$mt,'min'=>$min];$reason=$q<$min?'Unit brand kurang '.number_format(max(0,$min-$q),0,',','.').' dari minimum '.$min:'Type wajib belum ada';}
        }elseif($metric==='NOO'){$first=$source['first'][jkey([$dealer,$brand])]??null;$ok=$q>=$minNOO&&$first&&substr($first['date'],0,7)===$period;$reason='Bukan order pertama brand bulan ini / minimum unit belum tercapai';}
        elseif($metric==='IDDLE'){$sum=function($p)use($source,$dealer,$brand,$scope){$n=0;foreach($source['cubes'][$p]??[] as $r)if((string)$r['customer']===$dealer&&($brand==='ALL'||$r['brand']===$brand)&&isset($scope[(string)$r['nik']]))$n+=$r['q'];return $n;};$p1=PortalCore::prev($period);$p2=PortalCore::prev($p1);$p3=PortalCore::prev($p2);$ok=$q>0&&$sum($p1)<=0&&$sum($p2)<=0&&$sum($p3)>0;$reason='Jeda belum tepat dua bulan kosong';}
        $breakdown=[];foreach($rows as $r){$key=$r['brand'].' / '.$r['type'];$breakdown[$key]=($breakdown[$key]??0)+$r['q'];}
        $item=['dealer'=>$dealer,'name'=>$source['meta']['customers'][$dealer]['nama']??$dealer,'sales'=>$sales['nama']??$owner['sales'],'asm'=>person_($source,$asmNik)['nama']??$owner['asm'],'salesNik'=>$salesNik,'asmNik'=>$asmNik,'qty'=>$q,'value'=>$value,'orderQty'=>array_sum(array_column($rows,'q')),'orderValue'=>array_sum(array_column($rows,'a')),'types'=>$breakdown,'reason'=>$reason]+$extra;
        $result[$ok?'counted':'failed'][]=$item;$result['filtered']['dealers']++;$result['filtered']['qty']+=$q;$result['filtered']['value']+=$value;
    }
    return $result;
}

/** Salin kebijakan KPI Telemarketing ke Telemarketing lain pada periode yang sama. */
function apiTMKpiCopy($token,$fromNik,$period,$targets=null,$mode=null,$withTarget=null,$reason=null) {
    $u=tmGuardManage_($token,$fromNik);PortalCore::month($period);assertPeriodOpen_($period);$reason=text_($reason);if(strlen($reason)<5)fail_('Isi alasan minimal 5 karakter.');
    $targets=array_values(array_unique(array_filter(array_map('text_',(array)$targets))));if(!$targets||count($targets)>100)fail_('Pilih 1–100 Telemarketing tujuan.');
    $mode=$mode==='OVERWRITE'?'OVERWRITE':'SKIP';
    return with_lock_(function()use($u,$fromNik,$period,$targets,$mode,$withTarget,$reason){
        $src=tmConfig_($fromNik,$period);if(!$src['indicators'])fail_('KPI sumber masih kosong.');$done=[];$skipped=[];
        foreach($targets as $nik){
            if($nik===$fromNik)continue;tmTarget_(snapshot_(),$nik);if(!tmCanManage_($u,$nik)){$skipped[]=['nik'=>$nik,'why'=>'Di luar kewenangan'];continue;}
            $cur=tmConfig_($nik,$period);if($cur['indicators']&&$mode==='SKIP'){$skipped[]=['nik'=>$nik,'why'=>'Sudah punya KPI'];continue;}
            $rows=array_map(function($r)use($withTarget){if(empty($withTarget))$r['target']=null;if(($r['source']??'AUTO')==='MANUAL')$r['actual']=null;return $r;},$src['indicators']);
            tmExec_('INSERT INTO _tm_kpi (nik,period,data,revision,actor,updated_at) VALUES (?,?,?,1,?,?) ON DUPLICATE KEY UPDATE data=VALUES(data),revision=revision+1,actor=VALUES(actor),updated_at=VALUES(updated_at)',[$nik,$period,tmJson_($rows),$u['nik'],iso_now()]);
            audit_($u['nik'],'TM_KPI_COPY',$fromNik.' -> '.$nik.' '.$period.' '.$reason);$done[]=$nik;
        }
        if($done)data_rev_bump_('Telemarketing');
        return ['copied'=>$done,'skipped'=>$skipped];
    });
}

/** Pencarian Admin untuk kroscek KPI: nama atau NIK, semua Sales/ASM/RGM/Telemarketing dalam kewenangan. */
function apiKpiSearch($token,$period,$q=null,$role=null) {
    PortalCore::month($period);$source=snapshot_();$u=guard_($token,false,false,$source);
    if(!in_array($u['role'],['ADMIN','SUPER ADMIN'],true))fail_('Pencarian kroscek KPI hanya untuk Admin dan Super Admin.');
    $q=js_lower(text_($q??''));$role=text_($role??'');if(strlen($q)<2)return [];
    $people=$source['meta']['people'];$out=[];$ids=[];
    $all=array_map(fn($p)=>$p['nik'],$people);$db=coverageView_($source,$period,$all,'ST',$u);
    foreach($people as $p){
        if(!in_array($p['role'],['SALES','ASM','RGM','TELEMARKETING'],true))continue;if($role&&$role!=='ALL'&&$p['role']!==$role)continue;
        if(strpos(js_lower($p['nama']),$q)===false&&strpos(js_lower((string)$p['nik']),$q)===false)continue;
        if($u['role']==='ADMIN'&&!in_array($p['wilayah']??'',$u['adminRegions']??[],true)&&$p['role']!=='TELEMARKETING')continue;
        $score=null;$status='';
        try{
            if($p['role']==='TELEMARKETING'){$k=tmKpiCompute_($source,$p['nik'],$period);$score=$k['score'];$status=$k['status'];}
            else{$team=in_array($p['role'],['ASM','RGM'],true)?PortalCore::scope($people,$p['nik']):[$p['nik']];$k=PortalCore::kpi($db,$period,$team,$p['wilayah']??null,$p);$score=$k['score'];$status=$k['status'];}
        }catch(\Throwable $e){}
        $out[]=['nik'=>$p['nik'],'nama'=>$p['nama'],'role'=>$p['role'],'wilayah'=>$p['wilayah']??'','score'=>$score,'status'=>$status];
        if(count($out)>=40)break;
    }
    audit_($u['nik'],'ADMIN_CARI_KPI',$q);
    return $out;
}

/** Data export KPI lengkap (Admin/Super Admin). Excel disusun di browser dari data ini. */
function apiKpiExport($token,$period) {
    PortalCore::month($period);$source=snapshot_();$u=guard_($token,false,false,$source);
    if(!in_array($u['role'],['ADMIN','SUPER ADMIN'],true))fail_('Export KPI hanya untuk Admin dan Super Admin.');
    @set_time_limit(280);@ini_set('memory_limit','1024M');
    $people=$source['meta']['people'];$admin=$u['role']==='ADMIN';$regs=$u['adminRegions']??[];
    $inScope=fn($p)=>!$admin||in_array($p['wilayah']??'',$regs,true);
    $all=array_map(fn($p)=>$p['nik'],$people);$db=coverageView_($source,$period,$all,'ST',$u);
    $coverage=$db['coverage'][$period]??null;if(!$coverage)fail_('Periode belum tersedia.');
    $pp=PortalCore::prev($period);
    $cut=truthy($coverage['closed']??false)?PortalCore::end($pp):$pp.'-'.js_pad2(min((int)substr($coverage['cutoff'],8),(int)substr(PortalCore::end($pp),8)));
    $byNik=[];foreach($people as $p)$byNik[(string)$p['nik']]=$p;
    $climb=function($p,$role)use($byNik){$seen=[];$c=$p;while($c&&!isset($seen[$c['nik']])){$seen[$c['nik']]=1;if($c['role']===$role)return $c['nama'];$c=$byNik[(string)($c['atasan']??'')]??null;}return '';};
    $kpiLite=function($k){return ['score'=>$k['score'],'status'=>$k['status'],'groups'=>array_map(fn($g)=>['name'=>$g['name'],'score'=>$g['score'],'indicators'=>array_map(fn($i)=>['label'=>$i['label']??($i['metric'].' '.$i['brand']),'metric'=>$i['metric'],'brand'=>$i['brand'],'weight'=>$i['weight'],'cap'=>$i['cap']??120,'target'=>$i['target'],'actual'=>$i['actual'],'ach'=>$i['ach'],'score'=>$i['score']??null],$g['indicators'])],$k['groups'])];};
    // brand yang punya target di bulan itu
    $bset=[];foreach($source['meta']['targets']??[] as $t)if($t['period']===$period&&!truthy($t['indicator']??'')&&$t['brand']!=='ALL'&&in_array($t['metric']??'QTY',['QTY','DA','NOO'],true))$bset[$t['brand']]=1;
    $brands=array_keys($bset);sort($brands,SORT_STRING);
    $brandData=function($ids)use($db,$period,$pp,$cut,$brands){
        $rows=PortalCore::slice($db,$period,$ids);$prev=PortalCore::sliceCmp($db,$pp,$ids,$cut,$period);$o=[];
        foreach($brands as $b){$c=PortalCore::metrics($rows,$db,$period,$b);$l=PortalCore::metrics($prev,$db,$pp,$b);
            $o[]=['brand'=>$b,'tq'=>PortalCore::target($db['meta'],$period,$ids,$b,'QTY')['value'],'td'=>PortalCore::target($db['meta'],$period,$ids,$b,'DA')['value'],'tn'=>PortalCore::target($db['meta'],$period,$ids,$b,'NOO')['value'],
                'cq'=>$c['qty'],'cd'=>$c['da'],'cn'=>$c['noo'],'lq'=>$l['qty'],'ld'=>$l['da'],'ln'=>$l['noo']];}
        return $o;};
    $sales=[];$asms=[];$rgms=[];$tele=[];$val=[];
    foreach($people as $p){
        if(!$inScope($p))continue;$role=$p['role'];
        $base=['nik'=>$p['nik'],'nama'=>$p['nama'],'region'=>js_or($p['wilayah']??null,'Belum dipetakan'),'area'=>js_or($p['portfolio']??null,js_or($p['region']??null,'')),'asm'=>$role==='ASM'?$p['nama']:$climb($p,'ASM'),'rgm'=>$climb($p,'RGM'),'role'=>$role];
        if(truthy($p['sales']??false)){
            $k=PortalCore::kpi($db,$period,[$p['nik']],$p['wilayah']??null,$p);
            $sales[]=$base+['merangkap'=>$role!=='SALES','kpi'=>$kpiLite($k),'brands'=>$brandData([$p['nik']])];
            if($role==='SALES'){
                $ctx=['ids'=>[$p['nik']],'db'=>$db,'k'=>$k];
                foreach($k['groups'] as $gi=>$g)foreach($g['indicators'] as $ii=>$ind){
                    try{$v=kpiValidationCompute_($source,$p,$u,$period,$gi,$ii,null,null,$ctx);}catch(\Throwable $e){continue;}
                    foreach(['counted'=>'TERHITUNG','failed'=>'BELUM MEMENUHI'] as $key=>$st)foreach($v[$key] as $r)$val[]=[$p['nama'],$ind['label']??($ind['metric'].' '.$ind['brand']),$st,$r['dealer'],$r['name'],$r['qty'],$r['value'],$key==='failed'?$r['reason']:''];
                }
            }
        }
        if($role==='ASM'){$team=PortalCore::scope($people,$p['nik']);$k=PortalCore::kpi($db,$period,$team,$p['wilayah']??null,$p);$asms[]=$base+['kpi'=>$kpiLite($k),'brands'=>$brandData($team)];}
        if($role==='RGM'){$team=PortalCore::scope($people,$p['nik']);$k=PortalCore::kpi($db,$period,$team,$p['wilayah']??null,$p);$own=array_filter($db['meta']['rgmPolicies']??[],fn($c)=>$c['period']===$period&&$c['nik']===$p['nik']);$rgms[]=$base+['kpi'=>$kpiLite($k),'ownPolicy'=>count($own)>0];}
        if($role==='TELEMARKETING'){$a=account_($p['nik']);if($a&&!PortalCore::active($a['AKTIF']))continue;$k=tmKpiCompute_($source,$p['nik'],$period);$boss=$byNik[(string)($p['atasan']??'')]??null;
            $tele[]=['nik'=>$p['nik'],'nama'=>$p['nama'],'atasan'=>$boss['nama']??'Belum ada atasan','region'=>$boss?js_or($boss['wilayah']??null,''):'','area'=>$boss?js_or($boss['portfolio']??null,js_or($boss['region']??null,'')):'','rgm'=>$boss?$climb($boss,'RGM'):'','kpi'=>$kpiLite($k)];}
    }
    $cmp=fn($a,$b)=>strcmp($a['region'],$b['region'])?:(strcmp($a['area']??'',$b['area']??'')?:strcmp($a['nama'],$b['nama']));
    usort($sales,$cmp);usort($asms,$cmp);usort($rgms,$cmp);usort($tele,fn($a,$b)=>strcmp($a['atasan'],$b['atasan'])?:strcmp($a['nama'],$b['nama']));
    audit_($u['nik'],'EXPORT_KPI',$period);
    return ['period'=>$period,'cutoff'=>$coverage['cutoff'],'prevCut'=>$cut,'brands'=>$brands,'sales'=>$sales,'asm'=>$asms,'rgm'=>$rgms,'tele'=>$tele,'validation'=>$val];
}
