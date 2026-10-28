<?php
/**
 * Riwayat Pemilik (P4).
 *
 * Struktur organisasi (Master_Sales, Region_Map) tidak punya tanggal berlaku, jadi pemilik dealer-brand untuk bulan lama
 * tidak bisa dihitung ulang dengan benar. Solusinya: saat periode DIKUNCI, sistem menyimpan "foto" pemilik tiap
 * dealer x brand (beserta nama, jabatan, Big Region, dan region pemilik saat itu) di dsm_owner_snap.
 * Layar "Riwayat pemilik dealer" (Admin) hanya MEMBACA foto itu; dashboard dan KPI tidak memakainya.
 * Periode yang belum dikunci ditampilkan dari data terkini dan diberi label belum dikunci.
 * Mengunci ulang periode mengganti fotonya; membuka kunci tidak menghapus foto.
 */

function ohSchema_(): void {
    static $done = false; if ($done) return;
    db_()->exec("CREATE TABLE IF NOT EXISTS dsm_owner_snap (period CHAR(7) NOT NULL, dealer VARCHAR(60) NOT NULL, brand VARCHAR(80) NOT NULL, nik VARCHAR(100) NOT NULL DEFAULT '', nama VARCHAR(150) NOT NULL DEFAULT '', role VARCHAR(60) NOT NULL DEFAULT '', big VARCHAR(100) NOT NULL DEFAULT '', region VARCHAR(100) NOT NULL DEFAULT '', atasan VARCHAR(150) NOT NULL DEFAULT '', src VARCHAR(12) NOT NULL DEFAULT '', PRIMARY KEY (period, dealer, brand), KEY idx_nik (period, nik)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
    db_()->exec("CREATE TABLE IF NOT EXISTS dsm_owner_snap_meta (period CHAR(7) NOT NULL PRIMARY KEY, asof CHAR(10) NOT NULL, taken_at VARCHAR(40) NOT NULL, taken_by VARCHAR(100) NOT NULL DEFAULT '', total_rows INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
    $done = true;
}

/** Pemilik satu dealer-brand pada tanggal tertentu, lengkap dengan data orangnya. */
function ohOwner_(array $ctx, string $dealer, string $brand, string $date): array {
    [$nik, $kind] = txOwnerInfo_($ctx, $dealer, $brand, $date);
    $meta = $ctx['meta']; $p = $nik !== '' ? PortalCore::person($meta['people'], $nik) : null; $atasan = '';
    if ($p && text_($p['atasan'] ?? '') !== '') { $a = PortalCore::person($meta['people'], text_($p['atasan'])); $atasan = $a ? text_($a['nama'] ?? '') : ''; }
    return ['nik' => $nik, 'nama' => $p ? text_($p['nama'] ?? '') : '', 'role' => $p ? text_($p['role'] ?? '') : '', 'big' => $p ? text_($p['wilayah'] ?? '') : '',
        'region' => $p ? js_or(text_($p['portfolio'] ?? ''), text_($p['region'] ?? '')) : '', 'atasan' => $atasan, 'src' => $kind];
}
function ohCtx_(array $meta): array {
    return ['meta' => $meta, 'dba' => function_exists('dbaIndex_') ? dbaIndex_() : [], 'gen' => function_exists('portfolioAssignmentIndex_') ? portfolioAssignmentIndex_() : []];
}
function ohAsof_(string $period): string { $end = PortalCore::end($period); $today = jkt_date('Y-m-d'); return strcmp($end, $today) <= 0 ? $end : $today; }

/** Ambil foto semua dealer induk x brand perangkat. Mengganti foto lama periode yang sama. */
function ohTake_(array $db, string $period, string $actor): int {
    ohSchema_(); $meta = $db['meta']; $ctx = ohCtx_($meta); $asof = ohAsof_($period); $brands = function_exists('anaDeviceBrands_') ? anaDeviceBrands_($meta) : [];
    $rows = [];
    foreach ($meta['customers'] ?? [] as $id => $c) {
        $id = (string) $id; if (function_exists('custInduk_') && custInduk_($meta, $id) !== $id) continue;
        foreach ($brands as $b) {
            $b = js_upper(text_($b)); $o = ohOwner_($ctx, $id, $b, $asof);
            if ($o['nik'] === '') continue;
            $rows[] = [$period, $id, $b, $o['nik'], mb_substr($o['nama'], 0, 150), mb_substr($o['role'], 0, 60), mb_substr($o['big'], 0, 100), mb_substr($o['region'], 0, 100), mb_substr($o['atasan'], 0, 150), $o['src']];
        }
    }
    $pdo = db_(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM dsm_owner_snap WHERE period = ?')->execute([$period]);
        foreach (array_chunk($rows, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,?,?,?,?,?)')); $vals = [];
            foreach ($chunk as $r) foreach ($r as $v) $vals[] = $v;
            $pdo->prepare('INSERT INTO dsm_owner_snap (period, dealer, brand, nik, nama, role, big, region, atasan, src) VALUES ' . $ph)->execute($vals);
        }
        $pdo->prepare('REPLACE INTO dsm_owner_snap_meta (period, asof, taken_at, taken_by, total_rows) VALUES (?,?,?,?,?)')->execute([$period, $asof, iso_now(), $actor, count($rows)]);
        if ($own) $pdo->commit();
    } catch (Throwable $e) { if ($own && $pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    return count($rows);
}

function ohGuard_($token, ?array $db = null): array { $db = $db ?: snapshot_(); return adminGuard_($token, $db); }

function apiOwnerHistoryBoot($token) {
    $db = snapshot_(); $u = ohGuard_($token, $db); ohSchema_(); $meta = $db['meta'];
    $snaps = db_()->query('SELECT period, asof, taken_at, taken_by, total_rows FROM dsm_owner_snap_meta ORDER BY period DESC')->fetchAll(PDO::FETCH_ASSOC);
    $cur = jkt_date('Y-m'); $periods = [];
    foreach ($snaps as $s) $periods[$s['period']] = ['period' => $s['period'], 'locked' => true, 'asof' => $s['asof'], 'takenAt' => $s['taken_at'], 'rows' => (int) $s['total_rows']];
    if (!isset($periods[$cur])) $periods[$cur] = ['period' => $cur, 'locked' => false, 'asof' => jkt_date('Y-m-d'), 'takenAt' => '', 'rows' => 0];
    $list = array_values($periods); usort($list, fn($a, $b) => strcmp($b['period'], $a['period']));
    return ['periods' => $list, 'period' => $cur, 'brands' => array_map(fn($b) => js_upper(text_($b)), function_exists('anaDeviceBrands_') ? anaDeviceBrands_($meta) : [])];
}

function ohMatchDealers_(array $meta, string $q): array {
    $q = js_upper(trim($q)); $out = [];
    foreach ($meta['customers'] ?? [] as $id => $c) {
        $id = (string) $id; if (function_exists('custInduk_') && custInduk_($meta, $id) !== $id) continue;
        if (mb_strpos(js_upper($id), $q) !== false || mb_strpos(js_upper(text_($c['nama'] ?? '')), $q) !== false) $out[] = $id;
    }
    sort($out, SORT_STRING); return $out;
}

/** Baris riwayat (dealer x brand) untuk satu periode: dari foto bila periode terkunci, selain itu dari data terkini. */
function ohRows_(array $db, array $u, string $period, array $dealers, string $brandFilter): array {
    ohSchema_(); $meta = $db['meta']; $sc = txcScope_($db, $u); $rows = []; $out = [];
    $m = db_()->prepare('SELECT asof, taken_at FROM dsm_owner_snap_meta WHERE period = ?'); $m->execute([$period]); $sm = $m->fetch(PDO::FETCH_ASSOC);
    if ($sm && $dealers) {
        $ph = implode(',', array_fill(0, count($dealers), '?')); $sql = 'SELECT dealer, brand, nik, nama, role, big, region, atasan, src FROM dsm_owner_snap WHERE period = ? AND dealer IN (' . $ph . ')'; $par = array_merge([$period], $dealers);
        if ($brandFilter !== '') { $sql .= ' AND brand = ?'; $par[] = $brandFilter; }
        $st = db_()->prepare($sql . ' ORDER BY dealer, brand'); $st->execute($par); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $ctx = ohCtx_($meta); $asof = ohAsof_($period); $brands = $brandFilter !== '' ? [$brandFilter] : array_map(fn($b) => js_upper(text_($b)), anaDeviceBrands_($meta));
        foreach ($dealers as $d) foreach ($brands as $b) { $o = ohOwner_($ctx, $d, $b, $asof); if ($o['nik'] === '') continue; $rows[] = ['dealer' => $d, 'brand' => $b] + $o; }
    }
    foreach ($rows as $r) {
        if ($sc !== null && !isset($sc[(string) $r['nik']])) continue;
        $c = $meta['customers'][$r['dealer']] ?? [];
        $out[] = ['dealer' => $r['dealer'], 'dealerNama' => text_($c['nama'] ?? ''), 'kota' => text_($c['kota'] ?? ''), 'brand' => $r['brand'], 'nik' => $r['nik'], 'nama' => $r['nama'], 'role' => $r['role'], 'big' => $r['big'], 'region' => $r['region'], 'atasan' => $r['atasan'], 'src' => $r['src']];
    }
    return ['rows' => $out, 'locked' => (bool) $sm, 'asof' => $sm ? $sm['asof'] : ohAsof_($period), 'takenAt' => $sm ? $sm['taken_at'] : ''];
}

function apiOwnerHistory($token, $f = null) {
    $db = snapshot_(); $u = ohGuard_($token, $db); $f = is_array($f) ? $f : [];
    $period = PortalCore::month($f['period'] ?? jkt_date('Y-m')); $q = trim(text_($f['q'] ?? '')); $brand = js_upper(text_($f['brand'] ?? ''));
    $size = 20; $page = max(1, (int) ($f['page'] ?? 1));
    if (mb_strlen($q) < 2) fail_('Ketik minimal 2 huruf kode atau nama dealer.');
    $all = ohMatchDealers_($db['meta'], $q); $total = count($all); $pageDealers = array_slice($all, ($page - 1) * $size, $size);
    $r = ohRows_($db, $u, $period, $pageDealers, $brand);
    return $r + ['total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $size)), 'period' => $period];
}

function apiOwnerHistoryExport($token, $period = null) {
    $db = snapshot_(); $u = ohGuard_($token, $db); $period = PortalCore::month($period ?? jkt_date('Y-m')); ohSchema_();
    @ini_set('memory_limit', '1024M');
    $dealers = ohMatchDealers_($db['meta'], ''); $csvq = function ($v) { $v = (string) $v; return '"' . str_replace('"', '""', $v) . '"'; };
    $lines = [implode(',', array_map($csvq, ['PERIODE', 'SUMBER', 'KODE DEALER', 'NAMA DEALER', 'KOTA', 'BRAND', 'NIK PEMILIK', 'NAMA PEMILIK', 'JABATAN', 'BIG REGION', 'REGION', 'ATASAN']))]; $info = null;
    foreach (array_chunk($dealers, 400) as $chunk) {
        $r = ohRows_($db, $u, $period, $chunk, ''); $info = $r;
        foreach ($r['rows'] as $x) $lines[] = implode(',', array_map($csvq, [$period, $r['locked'] ? 'FOTO ' . $r['takenAt'] : 'TERKINI', $x['dealer'], $x['dealerNama'], $x['kota'], $x['brand'], $x['nik'], $x['nama'], $x['role'], $x['big'], $x['region'], $x['atasan']]));
    }
    audit_($u['nik'], 'OWNER_HISTORY_EXPORT', $period . ' ' . (count($lines) - 1) . ' baris');
    return ['name' => 'Riwayat-Pemilik-Dealer-' . $period . '.csv', 'mime' => 'text/csv', 'base64' => base64_encode("\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n")];
}
