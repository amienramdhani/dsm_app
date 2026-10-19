<?php
/**
 * Transfer Dealer langsung dari UI DSM.
 * Admin/Super Admin dapat mengeksekusi langsung.
 * ASM/RGM hanya mengajukan; ownership baru berubah setelah approval Admin.
 * Eksekusi tetap memakai engine transferRows_ + apiCommitDealerTransfer agar
 * histori effective-date, dealer-brand ownership, rollback, checkpoint, dan audit tetap satu jalur.
 */
require_once __DIR__ . '/bulk.php';

function dtdReqSchema_(): void {
    static $done = false; if ($done) return; $done = true;
    db_()->exec("CREATE TABLE IF NOT EXISTS _dealer_transfer_requests (
        id VARCHAR(64) NOT NULL PRIMARY KEY,
        requester_nik VARCHAR(100) NOT NULL,
        requester_role VARCHAR(40) NOT NULL,
        source_nik VARCHAR(100) NOT NULL,
        dest_nik VARCHAR(100) NOT NULL,
        effective_date VARCHAR(10) NOT NULL,
        reason TEXT NOT NULL,
        items_json LONGTEXT NOT NULL,
        preview_json LONGTEXT NOT NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'PENDING',
        created_ms BIGINT NOT NULL,
        reviewed_by VARCHAR(100) NOT NULL DEFAULT '',
        reviewed_ms BIGINT NOT NULL DEFAULT 0,
        review_note TEXT NOT NULL,
        result_json LONGTEXT NOT NULL,
        KEY idx_status_created (status, created_ms),
        KEY idx_requester_created (requester_nik, created_ms)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function dtdCtx_($token): array {
    $db = snapshot_();
    $u = guard_($token, false, false, $db);
    if (!in_array($u['role'] ?? '', ['ASM','RGM','ADMIN','SUPER ADMIN'], true)) fail_('Transfer Dealer hanya tersedia untuk ASM, RGM, Admin, dan Super Admin.');
    dtdReqSchema_();
    return [$db, $u];
}

function dtdDirect_(array $u): bool { return in_array($u['role'] ?? '', ['ADMIN','SUPER ADMIN'], true); }
function dtdApproval_(array $u): bool { return in_array($u['role'] ?? '', ['ASM','RGM'], true); }

function dtdPersonOk_(array $u, array $db, array $p): bool {
    if (!truthy($p['sales'] ?? null)) return false;
    if (($u['role'] ?? '') === 'SUPER ADMIN') return true;
    if ((string)($p['nik'] ?? '') === (string)($u['nik'] ?? '')) return true;
    if (in_array($u['role'] ?? '', ['ASM','RGM'], true)) {
        // ASM/RGM: Sales di bawahnya (pohon atasan), bukan dirinya saja.
        $k = (string)($u['nik'] ?? ''); if (!isset($GLOBALS['DTD_SCOPE'][$k])) $GLOBALS['DTD_SCOPE'][$k] = array_flip(array_map('strval', PortalCore::scope($db['meta']['people'] ?? [], $k)));
        return isset($GLOBALS['DTD_SCOPE'][$k][(string)($p['nik'] ?? '')]);
    }
    return canManageUser_($u, $p);
}

function dtdSalesList_(array $u, array $db): array {
    $out = [];
    foreach ($db['meta']['people'] ?? [] as $p) {
        if (!dtdPersonOk_($u, $db, $p)) continue;
        $out[] = [
            'nik' => (string)$p['nik'],
            'nama' => text_($p['nama'] ?? null),
            'role' => text_($p['role'] ?? null),
            'region' => js_or(text_($p['portfolio'] ?? null), text_($p['region'] ?? null)),
            'big' => text_($p['wilayah'] ?? null),
        ];
    }
    usort($out, fn($a,$b)=>lc($a['nama'],$b['nama']) ?: strcmp($a['nik'],$b['nik']));
    return $out;
}

function dtdReqList_(array $u, array $db): array {
    dtdReqSchema_();
    if (dtdDirect_($u)) {
        $st = db_()->query("SELECT * FROM _dealer_transfer_requests ORDER BY created_ms DESC LIMIT 200");
    } else {
        $st = db_()->prepare("SELECT * FROM _dealer_transfer_requests WHERE requester_nik=? ORDER BY created_ms DESC LIMIT 100");
        $st->execute([(string)$u['nik']]);
    }
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $people = [];
    foreach ($db['meta']['people'] ?? [] as $p) $people[(string)$p['nik']] = text_($p['nama'] ?? null);
    $out = [];
    foreach ($rows as $r) {
        $pv = json_decode((string)($r['preview_json'] ?? '[]'), true); if (!is_array($pv)) $pv = [];
        $sample = [];
        foreach (array_slice($pv,0,5) as $x) $sample[] = js_or(text_($x['nama'] ?? null), text_($x['dealer'] ?? null));
        $items = json_decode((string)($r['items_json'] ?? '[]'), true); if (!is_array($items)) $items = [];
        $out[] = [
            'id'=>(string)$r['id'], 'status'=>(string)$r['status'], 'created'=>(int)$r['created_ms'],
            'requesterNik'=>(string)$r['requester_nik'], 'requester'=>$people[(string)$r['requester_nik']] ?? (string)$r['requester_nik'], 'requesterRole'=>(string)$r['requester_role'],
            'sourceNik'=>(string)$r['source_nik'], 'source'=>$people[(string)$r['source_nik']] ?? (string)$r['source_nik'],
            'destNik'=>(string)$r['dest_nik'], 'dest'=>$people[(string)$r['dest_nik']] ?? (string)$r['dest_nik'],
            'date'=>(string)$r['effective_date'], 'reason'=>(string)$r['reason'], 'dealers'=>count($items), 'sample'=>$sample,
            'reviewedBy'=>(string)$r['reviewed_by'], 'reviewedMs'=>(int)$r['reviewed_ms'], 'reviewNote'=>(string)$r['review_note'],
        ];
    }
    return $out;
}

function apiDealerTransferDirectBoot($token) {
    [$db,$u] = dtdCtx_($token);
    $req = dtdReqList_($u,$db);
    return [
        'today' => jkt_date('Y-m-d'),
        'period' => jkt_date('Y-m'),
        'role' => $u['role'],
        'directAllowed' => dtdDirect_($u),
        'approvalRequired' => dtdApproval_($u),
        'canApprove' => dtdDirect_($u),
        'sales' => dtdSalesList_($u,$db),
        'brands' => dbaPrimaryBrands_(),
        'requests' => $req,
        'pendingCount' => count(array_filter($req, fn($x)=>($x['status'] ?? '') === 'PENDING')),
    ];
}

function dtdSource_(array $db, array $u, string $nik): array {
    $p = PortalCore::person($db['meta']['people'], $nik);
    if (!$p || !dtdPersonOk_($u,$db,$p)) fail_('Sales sumber tidak ditemukan atau di luar kewenangan Anda.');
    return $p;
}

/** Brand yang pernah punya ST (qty>0) per dealer (induk), dari seluruh cube terpublish: [dealer => [BRAND => true]]. */
function dtdStEver_(array $db): array {
    static $memo = []; $k = (string) ($db['id'] ?? '') ; if ($k !== '' && isset($memo[$k])) return $memo[$k];
    $m = [];
    foreach ($db['cubes'] ?? [] as $period => $rows) foreach ($rows as $r) { if (($r['q'] ?? 0) > 0) { $b = js_upper(text_($r['brand'] ?? '')); $c = (string) ($r['customer'] ?? ''); if ($b !== '' && $c !== '') $m[$c][$b] = true; } }
    if ($k !== '') $memo[$k] = $m; return $m;
}
function dtdOwnedBrands_(array $db, string $dealer, string $nik, string $date, array $idx, array $primary): array {
    $out = [];
    foreach ($primary as $b) {
        $o = dealerBrandOwner_($db,$dealer,$b,$date,$idx);
        if ($o['nik'] === $nik) $out[] = $b;
    }
    return $out;
}

function apiDealerTransferDirectList($token,$sourceNik) {
    [$db,$u] = dtdCtx_($token);
    $sourceNik = text_($sourceNik);
    $src = dtdSource_($db,$u,$sourceNik);
    $today = jkt_date('Y-m-d');
    $period = jkt_date('Y-m');
    $idx = dbaIndex_();
    $primary = dbaPrimaryBrands_();
    $rows = [];

    foreach ($db['meta']['customers'] ?? [] as $id=>$c) {
        $dealer = (string)$id;
        $owned = dtdOwnedBrands_($db,$dealer,$sourceNik,$today,$idx,$primary);
        if (!$owned) continue;
        $rows[$dealer] = [
            'dealer'=>$dealer,
            'nama'=>js_or(text_($c['nama'] ?? null),$dealer),
            'area'=>text_($c['region'] ?? null),
            'brands'=>$owned,
            'actual'=>0.0,
            'actualBy'=>[],
            'omzet'=>0.0,
            'ownerNik'=>$sourceNik,
            'owner'=>text_($src['nama'] ?? null),
            'genericOwner'=>dbaMasterNik_($db,$dealer),
        ];
    }

    $ref = (string)($db['raw'][$period] ?? '');
    if ($ref !== '' && $rows) {
        try {
            foreach (txEffRaw_($db['meta'], dataRows_($ref)) as $t) {
                $date = text_($t['date'] ?? null);
                if (substr($date,0,7) !== $period || $date > $today) continue;
                $dealer = custInduk_($db['meta'], text_($t['customer'] ?? null));
                if (!isset($rows[$dealer])) continue;
                $sku = text_($t['sku'] ?? null);
                $prod = $db['meta']['products'][$sku] ?? null;
                if (!$prod || !truthy($prod['device'] ?? null)) continue;
                $brand = js_upper(text_($prod['brand'] ?? null));
                if (!in_array($brand,$rows[$dealer]['brands'],true)) continue;
                $rows[$dealer]['actualBy'][$brand] = ($rows[$dealer]['actualBy'][$brand] ?? 0) + (float)($t['qty'] ?? 0);
                $rows[$dealer]['actual'] += (float)($t['qty'] ?? 0);
                $rows[$dealer]['omzet'] += (float)($t['amount'] ?? 0);
            }
        } catch (Throwable $e) {}
    }

    $ever=dtdStEver_($db);
    foreach($rows as $dk=>&$ro){$ro['stBrands']=array_values(array_filter($ro['brands'],fn($b)=>isset($ever[$dk][$b])));}unset($ro);
    $out = array_values($rows);
    usort($out, fn($a,$b)=>lc($a['nama'],$b['nama']) ?: strcmp($a['dealer'],$b['dealer']));
    return [
        'source'=>['nik'=>$sourceNik,'nama'=>text_($src['nama'] ?? null),'role'=>text_($src['role'] ?? null),'big'=>text_($src['wilayah'] ?? null)],
        'rows'=>$out,'count'=>count($out),'period'=>$period,'today'=>$today,'brands'=>$primary,
    ];
}

function dtdBuildStage_(array $db,array $u,string $sourceNik,$items,$date,$reason,bool $persist): array {
    dtdSource_($db,$u,$sourceNik);
    $date = PortalCore::iso($date); $reason = text_($reason);
    if (!$date) fail_('Tanggal efektif wajib YYYY-MM-DD.');
    if (strcmp($date,jkt_date('Y-m-d')) > 0) fail_('Tanggal efektif masa depan belum didukung.');
    if (mb_strlen($reason) < 5) fail_('Isi alasan transfer minimal 5 karakter.');
    $items = is_array($items) ? array_values($items) : [];
    if (!$items || count($items) > 5000) fail_('Pilih 1–5.000 dealer per transfer.');

    $primary = dbaPrimaryBrands_(); $idx = dbaIndex_(); $seen=[]; $raw=[]; $dealerCount=0; $destSet=[]; $stEver=dtdStEver_($db); $hints=[];
    foreach ($items as $it) {
        if (!is_array($it)) fail_('Pilihan dealer tidak valid.');
        $dealer=text_($it['dealer']??null); $newNik=text_($it['newNik']??null);
        if ($dealer==='' || isset($seen[$dealer])) fail_('Dealer kosong atau dipilih lebih dari sekali: '.$dealer);
        $seen[$dealer]=true;
        if (!isset($db['meta']['customers'][$dealer])) fail_('Dealer tidak ditemukan: '.$dealer);
        if ($newNik==='' || $newNik===$sourceNik) fail_('Sales tujuan harus berbeda dari Sales sumber untuk '.$dealer.'.');
        $dst=PortalCore::person($db['meta']['people'],$newNik);
        if (!$dst || !dtdPersonOk_($u,$db,$dst)) fail_('Sales tujuan tidak valid / di luar kewenangan: '.$newNik);
        $destSet[$newNik]=true;
        $owned=dtdOwnedBrands_($db,$dealer,$sourceNik,$date,$idx,$primary);
        if (!$owned) fail_('Pada '.$date.', dealer '.$dealer.' sudah tidak memiliki brand yang dipegang Sales sumber. Muat ulang daftar.');
        if (array_key_exists('brands',$it) && is_array($it['brands'])) {
            $pick=array_values(array_unique(array_map(fn($b)=>js_upper(text_($b)),$it['brands'])));
            if (!$pick) fail_('Pilih minimal satu brand untuk dealer '.$dealer.'.');
            foreach ($pick as $b) if (!in_array($b,$owned,true)) fail_('Brand '.$b.' pada dealer '.$dealer.' tidak dipegang Sales sumber pada '.$date.'. Muat ulang daftar.');
            $ownedAll=$owned; $owned=array_values(array_filter($owned,fn($b)=>in_array($b,$pick,true)));
            // Bila semua brand yang pernah ST (dan dipegang Sales sumber) dipilih dan itu membuat dealer berpindah penuh,
            // brand yang belum pernah ST ikut pindah sehingga brand baru kelak masuk ke Sales tujuan.
            $stOf=array_keys($stEver[$dealer]??[]); $stOwned=array_values(array_filter($ownedAll,fn($b)=>in_array($b,$stOf,true)));
            if (!$stOwned || !array_diff($stOwned,$owned)) {
                // Hanya diperluas bila hasilnya dealer berpindah penuh; jika tidak (split), brand yang tidak dipilih tetap di pemilik awal.
                $full=true; foreach ($primary as $pb) { $own2=in_array($pb,$ownedAll,true)?$newNik:dealerBrandOwner_($db,$dealer,$pb,$date,$idx)['nik']; if ($own2!==$newNik) { $full=false; break; } }
                if ($full) $owned=$ownedAll;
            }
        }
        // Konsolidasi: bila setelah transfer SEMUA brand aktif dealer dipegang Sales tujuan, Primary PIC ikut disinkronkan (baris ALL).
        // Bila masih split, assignment per-brand dipertahankan (tidak di-flatten).
        $consolidated=true; foreach ($primary as $pb) { $own=in_array($pb,$owned,true)?$newNik:dealerBrandOwner_($db,$dealer,$pb,$date,$idx)['nik']; if ($own!==$newNik) { $consolidated=false; break; } }
        $canAll=$consolidated || (count($owned)===count($primary) && dbaMasterNik_($db,$dealer)===$sourceNik);
        $hints[$dealer]=['all'=>$canAll,'masterName'=>text_(PortalCore::person($db['meta']['people'],$canAll?$newNik:dbaMasterNik_($db,$dealer))['nama']??'')];
        if ($canAll) $raw[]=['KODE CUSTOMER'=>$dealer,'BRAND'=>'ALL','NEW_SALES_NIK'=>$newNik,'EFFECTIVE_DATE'=>$date,'NOTE'=>$reason];
        else foreach ($owned as $brand) $raw[]=['KODE CUSTOMER'=>$dealer,'BRAND'=>$brand,'NEW_SALES_NIK'=>$newNik,'EFFECTIVE_DATE'=>$date,'NOTE'=>$reason];
        $dealerCount++;
    }
    $preview=transferRows_($raw,$db,$u); $assignments=0; foreach($preview as $r)$assignments+=count($r['brands']??[]);
    $out=['dealers'=>$dealerCount,'assignments'=>$assignments,'rows'=>$preview,'date'=>$date,'reason'=>$reason,'destinations'=>array_keys($destSet),'hints'=>$hints];
    if ($persist) {
        $id=uuid_();
        prop_set_('TRANSFER_STAGE_'.$id,writeJson_('transfer-stage-'.$id,['actor'=>$u['nik'],'base'=>$db['id'],'created'=>now_ms(),'rows'=>$raw,'rejected'=>[],'heads'=>TRANSFER_HEADERS,'direct'=>true,'sourceNik'=>$sourceNik]));
        $out['id']=$id;
    }
    return $out;
}

function dtdReqGet_(string $id): array {
    dtdReqSchema_(); $st=db_()->prepare('SELECT * FROM _dealer_transfer_requests WHERE id=?');$st->execute([$id]);$r=$st->fetch(PDO::FETCH_ASSOC);if(!$r)fail_('Permintaan transfer tidak ditemukan.');return $r;
}

/**
 * Normal: membuat preview stage. Untuk ASM/RGM stage hanya preview (belum mengubah data).
 * $op opsional:
 * - ['action'=>'LIST']
 * - ['action'=>'SUBMIT'] untuk ASM/RGM
 * - ['action'=>'APPROVE','id'=>'...'] untuk Admin
 * - ['action'=>'REJECT','id'=>'...','note'=>'...'] untuk Admin
 */
function apiDealerTransferDirectStage($token,$sourceNik,$items,$date,$reason,$op=null) {
    [$db,$u]=dtdCtx_($token); $action=is_array($op)?js_upper(text_($op['action']??null)):'';
    if ($action==='LIST') return ['requests'=>dtdReqList_($u,$db)];

    if ($action==='REJECT') {
        if (!dtdDirect_($u)) fail_('Hanya Admin yang dapat menolak permintaan.');
        $id=text_($op['id']??null);$note=text_($op['note']??null);if(mb_strlen($note)<3)fail_('Isi catatan penolakan minimal 3 karakter.');
        $r=dtdReqGet_($id);if(($r['status']??'')!=='PENDING')fail_('Permintaan ini sudah diproses.');
        db_()->prepare("UPDATE _dealer_transfer_requests SET status='REJECTED',reviewed_by=?,reviewed_ms=?,review_note=? WHERE id=? AND status='PENDING'")->execute([$u['nik'],now_ms(),$note,$id]);
        audit_($u['nik'],'TRANSFER_DEALER_REJECT',$id.' · '.$note);return ['id'=>$id,'status'=>'REJECTED'];
    }

    if ($action==='APPROVE') {
        if (!dtdDirect_($u)) fail_('Hanya Admin yang dapat menyetujui permintaan.');
        $id=text_($op['id']??null);$r=dtdReqGet_($id);if(($r['status']??'')!=='PENDING')fail_('Permintaan ini sudah diproses.');
        $its=json_decode((string)$r['items_json'],true);if(!is_array($its))fail_('Isi permintaan rusak.');
        $claim=db_()->prepare("UPDATE _dealer_transfer_requests SET status='PROCESSING',reviewed_by=?,reviewed_ms=? WHERE id=? AND status='PENDING'");$claim->execute([$u['nik'],now_ms(),$id]);
        if ($claim->rowCount()!==1) fail_('Permintaan ini sedang/sudah diproses.');
        try {
            // Validasi ulang kewenangan PEMOHON (bukan hanya Admin): sumber & semua tujuan harus tetap dalam scope pemohon.
            $reqP=PortalCore::person($db['meta']['people'],(string)$r['requester_nik']);
            if (!$reqP || !in_array($reqP['role']??'',['ASM','RGM'],true)) fail_('Akun pemohon tidak valid lagi.');
            $srcP=PortalCore::person($db['meta']['people'],(string)$r['source_nik']);
            if (!$srcP || !dtdPersonOk_($reqP,$db,$srcP)) fail_('Sales sumber kini di luar kewenangan pemohon.');
            foreach ($its as $it) { $dP=PortalCore::person($db['meta']['people'],text_($it['newNik']??null)); if (!$dP || !dtdPersonOk_($reqP,$db,$dP)) fail_('Sales tujuan kini di luar kewenangan pemohon.'); }
            $stg=dtdBuildStage_($db,$u,(string)$r['source_nik'],$its,(string)$r['effective_date'],(string)$r['reason'],true);
            // Deteksi stale: isi eksekusi harus sama dengan pratinjau saat diajukan.
            $sig=function($rows){$o=[];foreach((array)$rows as $x){$b=(array)($x['brands']??[]);sort($b);$o[]=($x['dealer']??'').'|'.($x['oldNik']??'').'|'.($x['newNik']??'').'|'.implode(',',$b);}sort($o);return $o;};
            $saved=json_decode((string)$r['preview_json'],true);
            if (is_array($saved) && $sig($saved)!==$sig($stg['rows'])) fail_('Kepemilikan dealer/brand berubah sejak pengajuan dibuat. Pengajuan ditandai gagal; minta pemohon mengajukan ulang.');
            $res=apiCommitDealerTransfer($token,$stg['id'],'Approval '.$id.' · '.(string)$r['reason']);
            db_()->prepare("UPDATE _dealer_transfer_requests SET status='APPROVED',reviewed_by=?,reviewed_ms=?,review_note=?,result_json=? WHERE id=? AND status='PROCESSING'")->execute([$u['nik'],now_ms(),text_($op['note']??'Disetujui'),json_encode($res,JSON_UNESCAPED_UNICODE),$id]);
            audit_($u['nik'],'TRANSFER_DEALER_APPROVE',$id);return ['id'=>$id,'status'=>'APPROVED','result'=>$res];
        } catch (Throwable $e) {
            db_()->prepare("UPDATE _dealer_transfer_requests SET status='FAILED',reviewed_by=?,reviewed_ms=?,review_note=? WHERE id=? AND status='PROCESSING'")->execute([$u['nik'],now_ms(),$e->getMessage(),$id]);
            throw $e;
        }
    }

    $sourceNik=text_($sourceNik);$preview=dtdBuildStage_($db,$u,$sourceNik,$items,$date,$reason,dtdDirect_($u));
    if ($action==='SUBMIT') {
        if (!dtdApproval_($u)) fail_('Akun ini tidak memerlukan approval transfer.');
        $dests=$preview['destinations']??[];if(count($dests)!==1)fail_('Satu pengajuan hanya boleh memiliki satu Sales tujuan.');
        $id='TRF-'.jkt_date('Ymd').'-'.strtoupper(substr(str_replace('-','',uuid_()),0,8));
        db_()->prepare("INSERT INTO _dealer_transfer_requests (id,requester_nik,requester_role,source_nik,dest_nik,effective_date,reason,items_json,preview_json,status,created_ms,reviewed_by,reviewed_ms,review_note,result_json) VALUES (?,?,?,?,?,?,?,?,?,'PENDING',?,'',0,'','')")
            ->execute([$id,$u['nik'],$u['role'],$sourceNik,$dests[0],$preview['date'],$preview['reason'],json_encode(array_values($items),JSON_UNESCAPED_UNICODE),json_encode($preview['rows'],JSON_UNESCAPED_UNICODE),now_ms()]);
        audit_($u['nik'],'TRANSFER_DEALER_REQUEST',$id.' · '.$preview['dealers'].' dealer');
        return ['request'=>true,'id'=>$id,'status'=>'PENDING','dealers'=>$preview['dealers'],'date'=>$preview['date']];
    }
    $preview['requestMode']=dtdApproval_($u); return $preview;
}
