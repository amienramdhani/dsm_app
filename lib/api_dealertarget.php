<?php
declare(strict_types=1);
/**
 * Target SO per dealer (Phase 3 · Tahap A).
 * - Disimpan per dealer (induk) · brand · periode. Belum dipakai menghitung skor KPI (Tahap B).
 * - Upload/ubah hanya ADMIN / SUPER ADMIN (template Excel → preview → commit dengan alasan + audit).
 * - Sales/ASM boleh melihat (read-only) target dealer yang dimiliki dirinya / timnya.
 */

function dtgSchema_(): void {
    static $done = false; if ($done) return;
    db_()->exec("CREATE TABLE IF NOT EXISTS dsm_dealer_target (dealer VARCHAR(100) NOT NULL, brand VARCHAR(100) NOT NULL, period CHAR(7) NOT NULL, value DOUBLE NOT NULL, actor VARCHAR(100) NOT NULL DEFAULT '', updated_at VARCHAR(40) NOT NULL DEFAULT '', PRIMARY KEY (dealer, brand, period), KEY ix_dt_period (period)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db_()->exec("CREATE TABLE IF NOT EXISTS dsm_dealer_target_log (id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, dealer VARCHAR(100) NOT NULL, brand VARCHAR(100) NOT NULL, period CHAR(7) NOT NULL, old_value DOUBLE NULL, new_value DOUBLE NOT NULL, reason VARCHAR(500) NOT NULL DEFAULT '', actor VARCHAR(100) NOT NULL DEFAULT '', batch VARCHAR(64) NOT NULL DEFAULT '', created_at VARCHAR(40) NOT NULL DEFAULT '', KEY ix_dtl_key (dealer, brand, period)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}
const DTG_HEADS = ['KODE CUSTOMER', 'BRAND', 'PERIODE', 'TARGET SO'];

function dtgNorm_($v): string { return preg_replace('/\s+/u', ' ', js_upper(text_($v))); }
function dtgPeriod_($v): string {
    $s = trim(text_($v));
    if (preg_match('/^(\d{4})-(\d{1,2})(?:-\d{1,2})?/', $s, $m)) return sprintf('%04d-%02d', (int) $m[1], (int) $m[2]);
    if (preg_match('/^(\d{1,2})[\/-](\d{4})$/', $s, $m)) return sprintf('%04d-%02d', (int) $m[2], (int) $m[1]);
    return '';
}
function dtgNumber_($v): ?float {
    $s = trim(text_($v)); if ($s === '') return null;
    $s = str_replace(' ', '', $s);
    if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $s)) $s = str_replace(['.', ','], ['', '.'], $s);
    elseif (preg_match('/^\d{1,3},\d{3}$/', $s)) $s = str_replace(',', '', $s);
    elseif (preg_match('/^\d+,\d+$/', $s)) $s = str_replace(',', '.', $s);
    elseif (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $s)) $s = str_replace(',', '', $s);
    return is_numeric($s) ? (float) $s : null;
}
function dtgCurrent_(array $keys): array {
    dtgSchema_(); $out = [];
    $periods = array_values(array_unique(array_map(fn($k) => $k['period'], $keys))); if (!$periods) return $out;
    $q = db_()->prepare('SELECT dealer,brand,period,value FROM dsm_dealer_target WHERE period IN (' . implode(',', array_fill(0, count($periods), '?')) . ')'); $q->execute($periods);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['dealer'] . "\x1f" . $r['brand'] . "\x1f" . $r['period']] = (float) $r['value'];
    return $out;
}
/** @return array ['ok'=>[...], 'rejected'=>[...], 'same'=>int] */
function dtgPlan_(array $db, array $u, array $rows): array {
    $brands = array_map('js_upper', dbaPrimaryBrands_()); $cust = $db['meta']['customers'] ?? [];
    $ok = []; $rej = []; $seen = [];
    $reject = function (array $r, string $why, string $fix) use (&$rej) { $rej[] = ['row' => ['KODE CUSTOMER' => text_($r['KODE CUSTOMER'] ?? null), 'BRAND' => text_($r['BRAND'] ?? null), 'PERIODE' => text_($r['PERIODE'] ?? null), 'TARGET SO' => text_($r['TARGET SO'] ?? null)], 'alasan' => $why, 'solusi' => $fix]; };
    $cur = jkt_date('Y-m');
    foreach ($rows as $r) {
        $code = text_($r['KODE CUSTOMER'] ?? null); $brand = dtgNorm_($r['BRAND'] ?? null); $period = dtgPeriod_($r['PERIODE'] ?? null); $val = dtgNumber_($r['TARGET SO'] ?? null);
        if ($code === '' && $brand === '' && $period === '' && trim(text_($r['TARGET SO'] ?? null)) === '') continue;
        if ($code === '') { $reject($r, 'Kode customer kosong.', 'Isi KODE CUSTOMER.'); continue; }
        $dealer = custInduk_($db['meta'], $code);
        if (!isset($cust[$dealer])) { $reject($r, $code . ': dealer tidak ada di Master Customer.', 'Cek kode atau tambahkan dealer dulu.'); continue; }
        if (!in_array($brand, $brands, true)) { $reject($r, 'Brand ' . ($brand ?: '(kosong)') . ' tidak valid.', 'Pakai brand utama: ' . implode(', ', $brands)); continue; }
        if ($period === '') { $reject($r, 'Periode tidak valid.', 'Format YYYY-MM, contoh 2026-10.'); continue; }
        if ($period < $cur) { $reject($r, 'Periode ' . $period . ' sudah lewat.', 'Target hanya bisa untuk bulan berjalan atau berikutnya.'); continue; }
        if ($period > date('Y-m', strtotime($cur . '-01 +12 months'))) { $reject($r, 'Periode ' . $period . ' terlalu jauh.', 'Maksimal 12 bulan ke depan.'); continue; }
        try { assertPeriodOpen_($period); } catch (Throwable $e) { $reject($r, 'Periode ' . $period . ' dikunci.', 'Minta Super Admin membuka periode.'); continue; }
        if ($val === null || !($val > 0) || $val > 100000000) { $reject($r, 'Target SO harus angka lebih dari 0.', 'Isi qty target, contoh 120.'); continue; }
        if (!dbaCanManageNik_($u, $db, dbaMasterNik_($db, $dealer))) { $reject($r, $dealer . ': di luar wilayah admin Anda.', 'Minta admin wilayahnya.'); continue; }
        $k = $dealer . "\x1f" . $brand . "\x1f" . $period;
        if (isset($seen[$k])) { $reject($r, 'Duplikat dealer + brand + periode dalam file.', 'Sisakan satu baris.'); continue; }
        $seen[$k] = 1; $ok[$k] = ['dealer' => $dealer, 'nama' => text_($cust[$dealer]['nama'] ?? null), 'brand' => $brand, 'period' => $period, 'value' => round($val, 2)];
    }
    $now = dtgCurrent_(array_values($ok)); $out = []; $same = 0;
    foreach ($ok as $k => $p) {
        if (!array_key_exists($k, $now)) { $p['status'] = 'BARU'; $p['old'] = null; }
        elseif (abs($now[$k] - $p['value']) < 0.0001) { $same++; continue; }
        else { $p['status'] = 'UPDATE'; $p['old'] = $now[$k]; }
        $out[] = $p;
    }
    return ['ok' => $out, 'rejected' => $rej, 'same' => $same];
}
function apiDealerTargetTemplate($token) {
    adminGuard_($token);
    return templateXlsx_('Template-Target-SO-Dealer.xlsx', 'Target_SO_Dealer', DTG_HEADS, [['', '', jkt_date('Y-m'), '']], ['1 baris = 1 dealer + 1 brand + 1 periode.', 'BRAND memakai brand utama (mis. OPPO, VIVO). PERIODE format YYYY-MM.', 'TARGET SO = qty target sell out dealer untuk brand dan bulan itu.', 'Hanya bulan berjalan atau berikutnya. Format kolom sebagai Teks.']);
}
function apiDealerTargetStage($form) {
    $form = is_array($form) ? $form : []; $db = snapshot_(); $u = adminGuard_($form['token'] ?? null, $db); demoAssertWritable_($form['token'] ?? null);
    $parsed = bulkReadRows_($form['file'] ?? null, 'Target_SO_Dealer');
    foreach (DTG_HEADS as $h) if (!in_array($h, $parsed['heads'], true)) fail_('Kolom wajib ' . $h . '. Pakai template.');
    if (count($parsed['rows']) > 5000) fail_('Maksimal 5.000 baris.');
    $plan = dtgPlan_($db, $u, $parsed['rows']); $id = uuid_();
    prop_set_('DTG_' . $id, writeJson_('dtg-' . $id, ['actor' => $u['nik'], 'created' => now_ms(), 'rows' => $parsed['rows'], 'rejected' => $plan['rejected'], 'heads' => DTG_HEADS]));
    $cnt = ['BARU' => 0, 'UPDATE' => 0]; foreach ($plan['ok'] as $p) $cnt[$p['status']]++;
    return ['id' => $id, 'baru' => $cnt['BARU'], 'update' => $cnt['UPDATE'], 'same' => $plan['same'], 'total' => count($plan['ok']), 'rejected' => count($plan['rejected']), 'sample' => array_slice($plan['ok'], 0, 100), 'rejSample' => array_map(fn($x) => ['alasan' => $x['alasan'], 'solusi' => $x['solusi']], array_slice($plan['rejected'], 0, 20))];
}
function apiDealerTargetRejected($token, $id) {
    $u = adminGuard_($token); $ptr = prop_get_('DTG_' . js_str($id)); if (!$ptr) fail_('Pratinjau tidak tersedia.');
    $s = readJson_($ptr); if ($s['actor'] !== $u['nik']) fail_('Bukan pratinjau Anda.');
    return rejectBase64_('Target-SO-Dealer-DITOLAK.xlsx', $s['heads'], $s['rejected']);
}
function apiDealerTargetCommit($token, $id, $reason) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    demoAssertWritable_($token);
    return with_lock_(function () use ($token, $id, $reason) {
        $db = snapshot_(); $u = adminGuard_($token, $db); $ptr = prop_get_('DTG_' . js_str($id)); if (!$ptr) fail_('Pratinjau tidak tersedia.');
        $s = readJson_($ptr); if ($s['actor'] !== $u['nik'] || now_ms() - $s['created'] > 3600000) fail_('Pratinjau kedaluwarsa. Upload ulang.');
        $plan = dtgPlan_($db, $u, $s['rows']); if (!$plan['ok']) fail_('Tidak ada baris baru atau berubah yang bisa disimpan.');
        dtgSchema_(); $pdo = db_(); $at = iso_now(); $batch = substr(str_replace('-', '', (string) $id), 0, 32);
        $up = $pdo->prepare('INSERT INTO dsm_dealer_target (dealer,brand,period,value,actor,updated_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value), actor=VALUES(actor), updated_at=VALUES(updated_at)');
        $lg = $pdo->prepare('INSERT INTO dsm_dealer_target_log (dealer,brand,period,old_value,new_value,reason,actor,batch,created_at) VALUES (?,?,?,?,?,?,?,?,?)');
        $pdo->beginTransaction();
        try { foreach ($plan['ok'] as $p) { $up->execute([$p['dealer'], $p['brand'], $p['period'], $p['value'], $u['nik'], $at]); $lg->execute([$p['dealer'], $p['brand'], $p['period'], $p['old'], $p['value'], $reason, $u['nik'], $batch, $at]); } $pdo->commit(); }
        catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        audit_($u['nik'], 'TARGET_SO_DEALER', count($plan['ok']) . ' baris · ' . $reason);
        $cnt = ['BARU' => 0, 'UPDATE' => 0]; foreach ($plan['ok'] as $p) $cnt[$p['status']]++;
        return ['count' => count($plan['ok']), 'baru' => $cnt['BARU'], 'update' => $cnt['UPDATE'], 'same' => $plan['same']];
    });
}
/** Daftar target (read-only). Admin/Super Admin: semua yang boleh dikelola; Sales/ASM: dealer-brand yang dimiliki dirinya/timnya pada hari ini. */
function apiDealerTargetList($token, $period = null, $q = null, $brand = null, $page = null, $size = null, $region = null) {
    $db = snapshot_(); $u = guard_($token, false, false, $db); dtgSchema_();
    $period = dtgPeriod_($period) ?: jkt_date('Y-m'); $q = js_upper(text_($q)); $brand = js_upper(text_($brand)); $region = text_($region); $size = max(10, min(100, (int) ($size ?: 25))); $page = max(1, (int) ($page ?: 1));
    $st = db_()->prepare('SELECT dealer,brand,value,actor,updated_at FROM dsm_dealer_target WHERE period=? ORDER BY dealer,brand'); $st->execute([$period]); $all = $st->fetchAll(PDO::FETCH_ASSOC);
    $admin = in_array($u['role'], ['ADMIN', 'SUPER ADMIN'], true); $scope = $admin ? null : js_set(PortalCore::scope($db['meta']['people'], $u['nik'])); $idx = dbaIndex_(); $today = jkt_date('Y-m-d'); $rows = [];
    $regSet = [];
    foreach ($all as $r) {
        if ($brand !== '' && js_upper($r['brand']) !== $brand) continue;
        $own = dealerBrandOwner_($db, $r['dealer'], $r['brand'], $today, $idx);
        if ($admin) { if (!dbaCanManageNik_($u, $db, $own['nik'] !== '' ? $own['nik'] : dbaMasterNik_($db, $r['dealer']))) continue; }
        elseif (!isset($scope[$own['nik']])) continue;
        $nama = text_($db['meta']['customers'][$r['dealer']]['nama'] ?? null); $reg = text_($db['meta']['customers'][$r['dealer']]['region'] ?? null);
        if ($reg !== '') $regSet[$reg] = 1;
        if ($region !== '' && $reg !== $region) continue;
        if ($q !== '' && !str_contains(js_upper($r['dealer'] . ' ' . $nama . ' ' . $own['nama']), $q)) continue;
        $rows[] = ['dealer' => $r['dealer'], 'nama' => $nama, 'region' => $reg, 'brand' => $r['brand'], 'value' => (float) $r['value'], 'ownerNik' => $own['nik'], 'owner' => $own['nama'], 'actor' => $admin ? $r['actor'] : '', 'at' => $r['updated_at']];
    }
    $regions = array_keys($regSet); sort($regions, SORT_STRING);
    $total = count($rows); $sum = 0.0; foreach ($rows as $x) $sum += $x['value']; $pages = max(1, (int) ceil($total / $size)); $page = min($page, $pages);
    $periods = []; try { foreach (db_()->query('SELECT DISTINCT period FROM dsm_dealer_target ORDER BY period DESC LIMIT 18')->fetchAll(PDO::FETCH_COLUMN) as $p) $periods[] = $p; } catch (Throwable $e) {}
    if (!in_array($period, $periods, true)) array_unshift($periods, $period);
    return ['period' => $period, 'periods' => $periods, 'brands' => dbaPrimaryBrands_(), 'regions' => $regions, 'canEdit' => $admin, 'total' => $total, 'sum' => $sum, 'page' => $page, 'pages' => $pages, 'rows' => array_slice($rows, ($page - 1) * $size, $size)];
}
function apiDealerTargetLog($token, $dealer, $brand, $period) {
    $u = adminGuard_($token); dtgSchema_(); $q = db_()->prepare('SELECT old_value,new_value,reason,actor,created_at FROM dsm_dealer_target_log WHERE dealer=? AND brand=? AND period=? ORDER BY id DESC LIMIT 30'); $q->execute([text_($dealer), js_upper(text_($brand)), dtgPeriod_($period)]);
    return ['items' => array_map(fn($r) => ['old' => $r['old_value'] === null ? null : (float) $r['old_value'], 'new' => (float) $r['new_value'], 'reason' => $r['reason'], 'actor' => $r['actor'], 'at' => $r['created_at']], $q->fetchAll(PDO::FETCH_ASSOC))];
}

// ------------------------------------------------------------------
// Tahap B — skor SO berbasis target per dealer
// Skor = Σ min(SO dealer, KK_CAP% × target dealer) / Σ target dealer, dihitung per segmen kepemilikan
// dealer-brand (target dibagi pro-rata hari dalam bulan bila dealer pindah Sales di tengah bulan).
// Dipakai hanya bila subjek (Sales/ASM) punya dealer bertarget pada brand+periode itu; selain itu skor lama (target manual/unggah).
// ------------------------------------------------------------------
function dtgTargets_(string $period, string $brand): array {
    $k = $period . '|' . $brand; if (isset($GLOBALS['DTG_T'][$k])) return $GLOBALS['DTG_T'][$k];
    $m = [];
    try { dtgSchema_(); $q = db_()->prepare('SELECT dealer,value FROM dsm_dealer_target WHERE period=? AND brand=?'); $q->execute([$period, js_upper($brand)]); foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $m[(string) $r['dealer']] = (float) $r['value']; } catch (Throwable $e) {}
    return $GLOBALS['DTG_T'][$k] = $m;
}
function dtgNextDay_(string $d): string { return (new DateTimeImmutable($d . 'T00:00:00', jkt_tz_()))->modify('+1 day')->format('Y-m-d'); }
function dtgPrevDay_(string $d): string { return (new DateTimeImmutable($d . 'T00:00:00', jkt_tz_()))->modify('-1 day')->format('Y-m-d'); }
/** Segmen kepemilikan dealer-brand dalam satu bulan: [[from,to,nik], ...]. */
function dtgSegments_(array $db, array $idx, string $dealer, string $brand, string $start, string $end): array {
    $b = [$start => 1];
    foreach ($idx[$dealer][js_upper($brand)] ?? [] as $r) {
        if (($r['status'] ?? '') !== 'ACTIVE') continue;
        $f = (string) ($r['from'] ?? ''); $t = (string) ($r['to'] ?? '');
        if ($f > $start && $f <= $end) $b[$f] = 1;
        if ($t !== '' && $t >= $start && $t < $end) $b[dtgNextDay_($t)] = 1;
    }
    $ds = array_keys($b); sort($ds, SORT_STRING); $out = [];
    foreach ($ds as $i => $from) { $to = isset($ds[$i + 1]) ? dtgPrevDay_($ds[$i + 1]) : $end; $out[] = [$from, $to, (string) dealerBrandOwner_($db, $dealer, $brand, $from, $idx)['nik']]; }
    return $out;
}
/** Rencana skor untuk subjek: segmen milik $ids dengan target dealer. [] bila subjek tidak punya dealer bertarget. */
function dtgSubjectPlan_(array $db, string $period, array $ids, string $brand, array $cats = []): array {
    $tg = dtgTargets_($period, $brand); if (!$tg) return [];
    $set = array_flip(array_map('strval', $ids)); $idx = $GLOBALS['KK_IDX'] ?? ($GLOBALS['KK_IDX'] = dbaIndex_());
    $start = $period . '-01'; $end = PortalCore::end($period); $mdays = (int) substr($end, 8, 2); $grade = $cats ? attrMap_('GRADE') : []; $cs = $cats ? array_flip($cats) : null; $plan = [];
    foreach ($tg as $dealer => $T) {
        if ($cs !== null) { $gv = null; foreach (($grade[$dealer]['brands'] ?? []) as $bv) if (js_upper((string) $bv[0]) === js_upper($brand)) { $gv = (string) $bv[1]; break; } if ($gv === null || !isset($cs[$gv])) continue; }
        foreach (dtgSegments_($db, $idx, $dealer, $brand, $start, $end) as [$f, $t, $nik]) {
            if (!isset($set[$nik])) continue;
            $days = (int) round((strtotime($t) - strtotime($f)) / 86400) + 1;
            $plan[] = ['dealer' => $dealer, 'from' => $f, 'to' => $t, 'days' => $days, 'target' => $T * $days / $mdays];
        }
    }
    return $plan;
}
/** @return array ['target','actual','contrib','dealers','excluded'] */
function dtgScoreCompute_(array $db, array $plan, string $brand, string $asof, float $cap): array {
    $tt = 0.0; $act = 0.0; $con = 0.0; $dl = []; $ex = 0; $b = js_upper($brand);
    foreach ($plan as $s) {
        $tt += $s['target']; $dl[$s['dealer']] = 1;
        if (strcmp($s['from'], $asof) > 0) continue;
        $to = strcmp($s['to'], $asof) < 0 ? $s['to'] : $asof;
        $pr = kkPairsAll_($db, $to, $s['from'])[$s['dealer'] . "\x1f" . $b] ?? null;
        if (!$pr) continue; if ($pr['bad']) { $ex++; continue; }
        $act += $pr['so']; $con += min((float) $pr['so'], $cap / 100 * $s['target']);
    }
    return ['target' => $tt, 'actual' => $act, 'contrib' => $con, 'dealers' => count($dl), 'excluded' => $ex];
}
