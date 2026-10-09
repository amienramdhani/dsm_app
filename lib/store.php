<?php
/**
 * DSM v5 — penyimpanan data besar di MySQL (pengganti file JSON per partisi).
 *
 *  - ST/SO mentah per partisi (jenis × bulan × big region) → tabel dsm_part + dsm_st / dsm_so.
 *    Setiap upload membuat VERSI partisi baru (id baru). Snapshot menunjuk id versinya,
 *    jadi fitur "Pemulihan regional" tetap bisa kembali ke versi lama.
 *    Penunjuk di snapshot:  "P:<id>"  = satu partisi,  "M|<p1>|<p2>|…" = gabungan satu bulan.
 *    Penunjuk lama (id file JSON) tetap terbaca → kompatibel dengan snapshot lama.
 *  - Mutasi stok (ST masuk / SO keluar per tanggal × dealer × SKU) → tabel dsm_move,
 *    diambil per rentang tanggal sesuai kebutuhan halaman (tidak dimuat utuh ke RAM).
 *  - storage_prune_(): bersih-bersih file & versi yang sudah tidak dipakai.
 */

function store_schema_(): void {
    static $done = false; if ($done) return; $done = true;
    $pdo = db_();
    $pdo->exec("CREATE TABLE IF NOT EXISTS dsm_part (id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, jenis CHAR(2) NOT NULL, periode CHAR(7) NOT NULL, region VARCHAR(100) NOT NULL DEFAULT '', baris INT NOT NULL DEFAULT 0, created_ms BIGINT NOT NULL, KEY idx_jpr (jenis, periode, region)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
    $pdo->exec("CREATE TABLE IF NOT EXISTS dsm_st (part_id BIGINT NOT NULL, seq INT NOT NULL, tanggal CHAR(10) NOT NULL, badan_usaha VARCHAR(191) NOT NULL DEFAULT '', no_transaksi VARCHAR(191) NOT NULL DEFAULT '', no_sj VARCHAR(191) NOT NULL DEFAULT '', kode_produk VARCHAR(191) NOT NULL DEFAULT '', kode_customer VARCHAR(191) NOT NULL DEFAULT '', nik_sales VARCHAR(191) NOT NULL DEFAULT '', qty INT NOT NULL, amount DECIMAL(20,4) NOT NULL DEFAULT 0, id_baris VARCHAR(191) NOT NULL DEFAULT '', ref_transaksi VARCHAR(191) NOT NULL DEFAULT '', region VARCHAR(100) NOT NULL DEFAULT '', PRIMARY KEY (part_id, seq), KEY idx_cust (kode_customer)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
    $pdo->exec("CREATE TABLE IF NOT EXISTS dsm_so (part_id BIGINT NOT NULL, seq INT NOT NULL, tanggal CHAR(10) NOT NULL, kode_customer VARCHAR(191) NOT NULL DEFAULT '', kode_produk VARCHAR(191) NOT NULL DEFAULT '', qty INT NOT NULL, region VARCHAR(100) NOT NULL DEFAULT '', PRIMARY KEY (part_id, seq), KEY idx_cust (kode_customer)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
    $pdo->exec("CREATE TABLE IF NOT EXISTS dsm_move (snap VARCHAR(64) NOT NULL, seq INT NOT NULL, tanggal CHAR(10) NOT NULL, region VARCHAR(100) NOT NULL DEFAULT '', kode_customer VARCHAR(191) NOT NULL, kode_produk VARCHAR(191) NOT NULL, in_qty INT NOT NULL DEFAULT 0, out_qty INT NOT NULL DEFAULT 0, PRIMARY KEY (snap, seq), KEY idx_tgl (snap, tanggal), KEY idx_cust (snap, kode_customer)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
}

/** Angka dari kolom DECIMAL: bilangan bulat → int (sama seperti hasil PortalCore::number). */
function store_num_($v) {
    if (is_int($v) || is_float($v)) return $v;
    $f = (float) $v;
    return (floor($f) == $f && abs($f) < 9007199254740992) ? (int) $f : $f;
}

// ------------------------------------------------------------------
// Partisi ST / SO
// ------------------------------------------------------------------

/** Simpan satu versi partisi. Mengembalikan penunjuk "P:<id>". */
function partWrite_(string $kind, string $period, string $region, array $rows): string {
    store_schema_(); $pdo = db_();
    if (!in_array($kind, ['ST', 'SO'], true)) fail_('Jenis data tidak valid.');
    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO dsm_part (jenis, periode, region, baris, created_ms) VALUES (?,?,?,?,?)')->execute([$kind, $period, $region, count($rows), now_ms()]);
        $id = (int) $pdo->lastInsertId();
        $cols = $kind === 'ST'
            ? ['part_id', 'seq', 'tanggal', 'badan_usaha', 'no_transaksi', 'no_sj', 'kode_produk', 'kode_customer', 'nik_sales', 'qty', 'amount', 'id_baris', 'ref_transaksi', 'region']
            : ['part_id', 'seq', 'tanggal', 'kode_customer', 'kode_produk', 'qty', 'region'];
        $n = count($cols); $seq = 0;
        foreach (array_chunk(array_values($rows), 800) as $chunk) {
            $vals = [];
            foreach ($chunk as $t) {
                if ($kind === 'ST') array_push($vals, $id, $seq++, (string) $t['date'], text_($t['company'] ?? ''), text_($t['doc'] ?? ''), text_($t['sj'] ?? ''), text_($t['sku'] ?? ''), text_($t['customer'] ?? ''), text_($t['nik'] ?? ''), (int) $t['qty'], (string) ($t['amount'] ?? 0), text_($t['line'] ?? ''), text_($t['ref'] ?? ''), text_($t['region'] ?? ''));
                else array_push($vals, $id, $seq++, (string) $t['date'], text_($t['customer'] ?? ''), text_($t['sku'] ?? ''), (int) $t['qty'], text_($t['region'] ?? ''));
            }
            $ph = implode(',', array_fill(0, count($chunk), '(' . implode(',', array_fill(0, $n, '?')) . ')'));
            $pdo->prepare('INSERT INTO ' . ($kind === 'ST' ? 'dsm_st' : 'dsm_so') . ' (' . implode(',', $cols) . ') VALUES ' . $ph)->execute($vals);
        }
        if ($own) $pdo->commit();
    } catch (Throwable $e) { if ($own && $pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    return 'P:' . $id;
}

/** Baris satu partisi SQL, urutan & bentuk sama persis dengan isi file JSON lama. */
function partRowsSql_(int $id): array {
    store_schema_();
    $st = db_()->prepare('SELECT jenis FROM dsm_part WHERE id = ?'); $st->execute([$id]);
    $kind = $st->fetchColumn();
    if ($kind === false) fail_('Data partisi tidak ditemukan: P:' . $id);
    $out = [];
    if ($kind === 'ST') {
        $q = db_()->prepare('SELECT tanggal, badan_usaha, no_transaksi, no_sj, kode_produk, kode_customer, nik_sales, qty, amount, id_baris, ref_transaksi, region FROM dsm_st WHERE part_id = ? ORDER BY seq');
        $q->execute([$id]);
        while ($r = $q->fetch(PDO::FETCH_NUM)) $out[] = ['date' => $r[0], 'company' => $r[1], 'doc' => $r[2], 'sj' => $r[3], 'sku' => $r[4], 'customer' => $r[5], 'nik' => $r[6], 'qty' => (int) $r[7], 'amount' => store_num_($r[8]), 'line' => $r[9], 'ref' => $r[10], 'region' => $r[11]];
    } else {
        $q = db_()->prepare('SELECT tanggal, kode_customer, kode_produk, qty, region FROM dsm_so WHERE part_id = ? ORDER BY seq');
        $q->execute([$id]);
        while ($r = $q->fetch(PDO::FETCH_NUM)) $out[] = ['date' => $r[0], 'customer' => $r[1], 'sku' => $r[2], 'qty' => (int) $r[3], 'region' => $r[4]];
    }
    return $out;
}

/** Penunjuk satu bulan = gabungan partisi (urutan region sama dengan sebelumnya). */
function monthPointer_(array $entries): string {
    $ids = [];
    foreach ($entries as $e) { $f = (string) ($e['file'] ?? ''); if ($f !== '') $ids[] = $f; }
    return 'M|' . implode('|', $ids);
}

/** Baca baris dari penunjuk apa pun: "P:<id>", "M|…", atau id file JSON lama. */
function dataRows_(string $ref): array {
    if (str_starts_with($ref, 'P:')) return partRowsSql_((int) substr($ref, 2));
    if (str_starts_with($ref, 'M|')) {
        $out = [];
        foreach (array_filter(explode('|', substr($ref, 2)), fn($x) => $x !== '') as $one) foreach (dataRows_($one) as $t) $out[] = $t;
        return $out;
    }
    return pubReadJsonRaw_($ref);
}

/** readJson_ tanpa memo, untuk file data lama. */
function pubReadJsonRaw_(string $id) {
    $p = file_path_($id);
    if (!is_file($p)) fail_('File data tidak ditemukan: ' . $id);
    return json_decode(file_get_contents($p), true, 512, JSON_BIGINT_AS_STRING);
}

/** Jumlah baris ST & SO milik satu kode dealer di seluruh histori aktif (untuk cek sebelum edit/hapus dealer). */
function dataCustomerCounts_(array $db, string $uniq): ?array {
    $parts = $db['parts'] ?? null; if (!$parts) return null;
    $ids = ['ST' => [], 'SO' => []];
    foreach (['ST', 'SO'] as $k) foreach ($parts[$k] ?? [] as $m) foreach ($m as $e) {
        $f = (string) ($e['file'] ?? '');
        if (!str_starts_with($f, 'P:')) return null; // masih ada data format lama → pakai cara lama
        $ids[$k][] = (int) substr($f, 2);
    }
    store_schema_(); $out = [];
    foreach (['ST' => 'dsm_st', 'SO' => 'dsm_so'] as $k => $tbl) {
        if (!$ids[$k]) { $out[$k] = 0; continue; }
        $st = db_()->prepare('SELECT COUNT(*) FROM ' . $tbl . ' WHERE kode_customer = ? AND part_id IN (' . implode(',', $ids[$k]) . ')');
        $st->execute([$uniq]); $out[$k] = (int) $st->fetchColumn();
    }
    return $out;
}

// ------------------------------------------------------------------
// Mutasi stok
// ------------------------------------------------------------------

/** Simpan mutasi stok hasil StockCore::build untuk satu snapshot. */
function movesWrite_(string $snap, array $moves): void {
    store_schema_(); $pdo = db_();
    $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM dsm_move WHERE snap = ?')->execute([$snap]);
        $seq = 0;
        foreach (array_chunk($moves, 2000) as $chunk) {
            $vals = [];
            foreach ($chunk as $m) array_push($vals, $snap, $seq++, (string) $m['date'], (string) ($m['region'] ?? ''), (string) $m['customer'], (string) $m['sku'], (int) $m['inQty'], (int) $m['outQty']);
            $pdo->prepare('INSERT INTO dsm_move (snap, seq, tanggal, region, kode_customer, kode_produk, in_qty, out_qty) VALUES ' . implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,?,?,?)')))->execute($vals);
        }
        if ($own) $pdo->commit();
    } catch (Throwable $e) { if ($own && $pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

/** Snapshot yang mutasinya tersimpan di SQL (bukan di dalam JSON). */
function movesSnap_(array $db): ?string {
    if (isset($db['stock']['moves'])) return null;
    $s = $db['stock']['moveSnap'] ?? null;
    return truthy($s) ? (string) $s : null;
}

/** Saringan region dari coverageView_ (null = semua). */
function movesRegionOk_(array $db, $region): bool {
    $sel = $db['stock']['_sel'] ?? null;
    if ($sel === null) return true;
    return !truthy($region) || isset($sel[(string) $region]);
}

/**
 * Mutasi stok dalam rentang tanggal [from, to] (null = tanpa batas), urutan sama dengan versi lama.
 * $o: customer (string), out (true = hanya baris dengan outQty ≠ 0).
 */
function stockMoves_(array $db, ?string $from, ?string $to, array $o = []): array {
    if (empty($db['stock'])) return [];
    $snap = movesSnap_($db);
    $cust = isset($o['customer']) ? (string) $o['customer'] : null; $outOnly = !empty($o['out']);
    if ($snap === null) { // snapshot lama: mutasi masih di dalam JSON
        $out = [];
        foreach ($db['stock']['moves'] ?? [] as $t) {
            if (($from !== null && strcmp($t['date'], $from) < 0) || ($to !== null && strcmp($t['date'], $to) > 0)) continue;
            if ($cust !== null && (string) $t['customer'] !== $cust) continue;
            if ($outOnly && !truthy($t['outQty'])) continue;
            $out[] = $t;
        }
        return $out;
    }
    $key = $snap . '|' . ($from ?? '') . '|' . ($to ?? '') . '|' . ($cust ?? "\x00") . '|' . ($outOnly ? 1 : 0);
    $all = PortalCore::memo('mvq', $key, function () use ($snap, $from, $to, $cust, $outOnly) {
        store_schema_();
        $w = ['snap = ?']; $a = [$snap];
        if ($from !== null) { $w[] = 'tanggal >= ?'; $a[] = $from; }
        if ($to !== null) { $w[] = 'tanggal <= ?'; $a[] = $to; }
        if ($cust !== null) { $w[] = 'kode_customer = ?'; $a[] = $cust; }
        if ($outOnly) $w[] = 'out_qty <> 0';
        $q = db_()->prepare('SELECT tanggal, region, kode_customer, kode_produk, in_qty, out_qty FROM dsm_move WHERE ' . implode(' AND ', $w) . ' ORDER BY seq');
        $q->execute($a); $out = [];
        while ($r = $q->fetch(PDO::FETCH_NUM)) $out[] = ['date' => $r[0], 'region' => $r[1], 'customer' => $r[2], 'sku' => $r[3], 'inQty' => (int) $r[4], 'outQty' => (int) $r[5]];
        return $out;
    });
    if (($db['stock']['_sel'] ?? null) === null) return $all;
    $out = [];
    foreach ($all as $t) if (movesRegionOk_($db, $t['region'])) $out[] = $t;
    return $out;
}

/**
 * Ringkasan mutasi per dealer × SKU sampai tanggal $asof (untuk DOS), urutan = kemunculan pertama.
 * Hasil: [customer, sku, stock(sejak BASE), out(sejak BASE), sellout(>= $from), early(bool), st(sejak BASE), n(jumlah mutasi sejak BASE)].
 */
function stockBalance_(array $db, string $asof, string $from): array {
    $snap = movesSnap_($db); $base = StockCore::BASE; $out = [];
    if ($snap === null) {
        foreach ($db['stock']['moves'] ?? [] as $t) {
            if (strcmp($t['date'], $asof) > 0 || !movesRegionOk_($db, $t['region'] ?? '')) continue;
            $k = $t['customer'] . "\x1f" . $t['sku'];
            if (!isset($out[$k])) $out[$k] = ['customer' => $t['customer'], 'sku' => $t['sku'], 'stock' => 0, 'out' => 0, 'sellout' => 0, 'early' => false, 'st' => 0, 'n' => 0];
            if (strcmp($t['date'], $base) < 0) { if (truthy($t['outQty'])) $out[$k]['early'] = true; continue; }
            $out[$k]['stock'] += $t['inQty'] - $t['outQty']; $out[$k]['st'] += $t['inQty']; $out[$k]['out'] += $t['outQty']; $out[$k]['n']++;
            if (strcmp($t['date'], $from) >= 0) $out[$k]['sellout'] += $t['outQty'];
        }
        return array_values($out);
    }
    $sel = $db['stock']['_sel'] ?? null;
    $key = $snap . '|' . $asof . '|' . $from . '|' . ($sel === null ? '*' : md5(implode("\x1f", array_keys($sel))));
    return PortalCore::memo('mvbal', $key, function () use ($snap, $asof, $from, $base, $sel) {
        store_schema_();
        $w = 'snap = ? AND tanggal <= ?';
        if ($sel !== null) {
            $regs = array_map('strval', array_keys($sel));
            $w .= " AND (region = ''" . ($regs ? ' OR region IN (' . implode(',', array_fill(0, count($regs), '?')) . ')' : '') . ')';
        }
        $sql = 'SELECT kode_customer, kode_produk, MIN(seq) s,
                SUM(CASE WHEN tanggal >= ? THEN in_qty - out_qty ELSE 0 END) stok,
                SUM(CASE WHEN tanggal >= ? THEN out_qty ELSE 0 END) keluar,
                SUM(CASE WHEN tanggal >= ? THEN in_qty ELSE 0 END) masuk,
                MAX(CASE WHEN tanggal < ? AND out_qty <> 0 THEN 1 ELSE 0 END) awal,
                SUM(CASE WHEN tanggal >= ? AND tanggal >= ? THEN out_qty ELSE 0 END) jual,
                SUM(CASE WHEN tanggal >= ? THEN 1 ELSE 0 END) jumlah
                FROM dsm_move WHERE ' . $w . ' GROUP BY kode_customer, kode_produk ORDER BY s';
        // urutan placeholder mengikuti posisi "?" di SQL
        $args = [$base, $base, $base, $base, $from, $base, $base, $snap, $asof];
        if ($sel !== null) foreach (array_keys($sel) as $r) $args[] = (string) $r;
        $q = db_()->prepare($sql); $q->execute($args); $out = [];
        while ($r = $q->fetch(PDO::FETCH_NUM)) $out[] = ['customer' => $r[0], 'sku' => $r[1], 'stock' => (int) $r[3], 'out' => (int) $r[4], 'st' => (int) $r[5], 'early' => (int) $r[6] === 1, 'sellout' => (int) $r[7], 'n' => (int) $r[8]];
        return $out;
    });
}

/** Bulan × region yang punya sell out untuk satu brand (peta kelengkapan Admin). */
function stockOutPresence_(array $db, string $brand): array {
    $pres = []; $prod = $db['meta']['products'] ?? [];
    $snap = movesSnap_($db);
    if ($snap === null) {
        foreach ($db['stock']['moves'] ?? [] as $t) {
            if (($t['outQty'] ?? 0) <= 0 || ($prod[$t['sku']]['brand'] ?? null) !== $brand) continue;
            $pres[substr($t['date'], 0, 7) . '|' . ($t['region'] ?? '')] = 1;
        }
        return $pres;
    }
    store_schema_();
    $q = db_()->prepare('SELECT DISTINCT LEFT(tanggal, 7), region, kode_produk FROM dsm_move WHERE snap = ? AND out_qty > 0');
    $q->execute([$snap]);
    while ($r = $q->fetch(PDO::FETCH_NUM)) if (($prod[$r[2]]['brand'] ?? null) === $brand) $pres[$r[0] . '|' . $r[1]] = 1;
    return $pres;
}

// ------------------------------------------------------------------
// Versi snapshot (untuk Pemulihan regional) & bersih-bersih
// ------------------------------------------------------------------

/** Peta partisi milik satu versi snapshot (file kecil pendamping, atau snapshot penuh versi lama). */
function versionParts_(string $version): array {
    $side = $version . '-parts';
    if (preg_match('/^[A-Za-z0-9_.-]{1,190}$/', $side) && is_file(file_path_($side))) return readJson_($side);
    $full = readJson_($version);
    return ['parts' => $full['parts'] ?? []];
}

/**
 * Hapus yang sudah tidak dipakai:
 *  - file JSON di storage/files yang tidak ditunjuk siapa pun (snapshot lama, pratinjau kedaluwarsa, partisi format lama),
 *  - versi partisi SQL yang tidak ditunjuk snapshot yang disimpan,
 *  - mutasi stok snapshot lama, properti login/pratinjau basi.
 * Versi yang tetap bisa dipulihkan: 60 publikasi terakhir (sesuai daftar di Admin).
 */
function storage_prune_(?int $graceMs = null): array {
    $now = now_ms(); $grace = $graceMs ?? 2 * 3600 * 1000; $snapGrace = min($grace, 15 * 60 * 1000); $dir = storage_dir_('files');
    $snapIds = []; foreach (optionalTable_('Version_Log') as $r) { $x = text_($r['SNAPSHOT'] ?? ''); if ($x !== '') $snapIds[$x] = 1; }
    $keep = []; $partKeep = [];
    $props = prop_all_();
    // pratinjau/stage yang basi (> 1 hari) dibuang dari properti
    foreach ($props as $k => $v) {
        if (preg_match('/^(REGION_STAGE_|RESTORE_STAGE_|STATUS_STAGE_|USER_STAGE_|TRANSFER_STAGE_|DEALER_STAGE_|DEALER_UP_|INDUK_BULK_|MASTER_BULK_|CUSTOMER_BULK_|CONTACT_BULK_)/', $k)) {
            $f = @filemtime($dir . '/' . $v . '.json');
            if ($f === false || $now - $f * 1000 > 86400000) { prop_delete_($k); unset($props[$k]); continue; }
        }
        if (str_starts_with($k, 'LOGIN_IP_')) {
            $t = json_decode((string) $v, true);
            if (!is_array($t) || $now - (int) ($t['start'] ?? 0) > 86400000) { prop_delete_($k); unset($props[$k]); continue; }
        }
        if (is_string($v) && preg_match('/^[A-Za-z0-9_.-]{8,200}$/', $v)) $keep[$v] = 1;
    }
    $mark = function ($ref) use (&$keep, &$partKeep, &$mark) {
        $ref = (string) $ref; if ($ref === '') return;
        if (str_starts_with($ref, 'P:')) { $partKeep[(int) substr($ref, 2)] = 1; return; }
        if (str_starts_with($ref, 'M|')) { foreach (explode('|', substr($ref, 2)) as $x) $mark($x); return; }
        $keep[$ref] = 1;
    };
    $markParts = function ($parts) use ($mark) { foreach (['ST', 'SO'] as $k) foreach ((array) ($parts[$k] ?? []) as $m) foreach ((array) $m as $e) $mark($e['file'] ?? ''); };
    // snapshot aktif & sebelumnya: simpan penuh
    $full = [];
    foreach (['ACTIVE', 'PREVIOUS'] as $k) if (!empty($props[$k])) $full[$props[$k]] = 1;
    foreach (array_keys($full) as $id) {
        try {
            $s = pubReadJsonRaw_($id); $markParts($s['parts'] ?? []);
            foreach ((array) ($s['raw'] ?? []) as $x) $mark($x); foreach ((array) ($s['so']['raw'] ?? []) as $x) $mark($x);
            unset($s);
        } catch (Throwable $e) {}
    }
    // 60 versi terakhir: cukup file pendamping peta partisi (dibuat dari snapshot penuh bila belum ada)
    $versions = array_slice(optionalTable_('Version_Log'), -60);
    foreach ($versions as $r) {
        $id = text_($r['SNAPSHOT'] ?? ''); if ($id === '' || !preg_match('/^[A-Za-z0-9_.-]{1,180}$/', $id)) continue;
        $side = $id . '-parts'; $keep[$side] = 1;
        try {
            if (!is_file(file_path_($side)) && is_file(file_path_($id))) { $s = pubReadJsonRaw_($id); writeJsonId_($side, ['parts' => $s['parts'] ?? []]); unset($s); }
            if (is_file(file_path_($side))) $markParts(pubReadJsonRaw_($side)['parts'] ?? []);
        } catch (Throwable $e) {}
    }
    // checkpoint: 60 terakhir (sesuai daftar di Admin)
    try {
        $n = 0;
        foreach (array_reverse(optionalTable_('Audit_Log')) as $r) {
            $vals = array_values($r);
            if (($vals[2] ?? '') !== 'CHECKPOINT') continue;
            if (preg_match('/\s(\S+)$/', (string) ($vals[3] ?? ''), $m)) $keep[$m[1]] = 1;
            if (++$n >= 60) break;
        }
    } catch (Throwable $e) {}
    // hapus file yang tidak ditunjuk
    $deleted = 0; $bytes = 0;
    foreach (glob($dir . '/*.json') ?: [] as $f) {
        $id = basename($f, '.json');
        if (isset($keep[$id]) || isset($full[$id])) continue;
        if ($now - (int) @filemtime($f) * 1000 < (isset($snapIds[$id]) ? $snapGrace : $grace)) continue;
        $bytes += (int) @filesize($f); if (@unlink($f)) $deleted++;
    }
    // versi partisi SQL & mutasi stok
    store_schema_(); $pdo = db_(); $parts = 0;
    $old = $pdo->query('SELECT id FROM dsm_part WHERE created_ms < ' . (int) ($now - $grace))->fetchAll(PDO::FETCH_COLUMN);
    $drop = array_values(array_filter(array_map('intval', $old), fn($id) => !isset($partKeep[$id])));
    foreach (array_chunk($drop, 500) as $ch) {
        $in = implode(',', $ch);
        $pdo->exec('DELETE FROM dsm_st WHERE part_id IN (' . $in . ')'); $pdo->exec('DELETE FROM dsm_so WHERE part_id IN (' . $in . ')'); $pdo->exec('DELETE FROM dsm_part WHERE id IN (' . $in . ')');
        $parts += count($ch);
    }
    $snaps = array_keys($full);
    $pdo->prepare('DELETE FROM dsm_move WHERE snap NOT IN (' . implode(',', array_fill(0, max(1, count($snaps)), '?')) . ')')->execute($snaps ?: ['-']);
    return ['files' => $deleted, 'mb' => round($bytes / 1048576, 1), 'parts' => $parts];
}
