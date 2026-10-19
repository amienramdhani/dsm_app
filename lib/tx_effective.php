<?php
/**
 * Effective Sales (Phase 3, langkah 2).
 *
 * Satu resolver untuk menurunkan Sales efektif tiap baris ST, dipanggil di publish_ sebelum PortalCore::build:
 *   NIK dari file ST -> pemilik dealer-brand menurut tanggal (portfolioResolveRows_) -> koreksi manual per transaksi.
 * Karena dihitung ulang tiap publish, hasilnya tidak hilang saat data ST diupload ulang.
 * Data ST mentah tidak ditulis ulang oleh resolver ini.
 *
 * Kunci transaksi (txKeys_) tidak memuat NIK Sales, supaya koreksi tetap menempel setelah upload ulang.
 * Saklar darurat: prop TXEFF_OFF = '1' mematikan resolver (publish kembali seperti sebelumnya).
 */

function txcSchema_(): void {
    static $done = false; if ($done) return;
    db_()->exec("CREATE TABLE IF NOT EXISTS dsm_txcorr (id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, tx_key CHAR(40) NOT NULL, tx_ref VARCHAR(400) NOT NULL DEFAULT '', period CHAR(7) NOT NULL, raw_nik VARCHAR(100) NOT NULL DEFAULT '', new_nik VARCHAR(100) NOT NULL, reason VARCHAR(500) NOT NULL, actor VARCHAR(100) NOT NULL, created_at VARCHAR(40) NOT NULL, fp VARCHAR(200) NOT NULL DEFAULT '', active TINYINT NOT NULL DEFAULT 1, revoked_by VARCHAR(100) NOT NULL DEFAULT '', revoked_at VARCHAR(40) NOT NULL DEFAULT '', revoke_reason VARCHAR(500) NOT NULL DEFAULT '', KEY idx_key (tx_key, active), KEY idx_period (period, active)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
    $done = true;
}

function txcNum_($v): string {
    $s = number_format((float) $v, 4, '.', '');
    return rtrim(rtrim($s, '0'), '.');
}

/**
 * Kunci stabil per baris. Mengembalikan daftar sejajar dengan $rows: ['key','ref','fp'].
 * - ID_BARIS terisi: badan usaha | no transaksi | ID_BARIS.
 * - ID_BARIS kosong: badan usaha | no transaksi | no SJ | SKU | dealer asli | tanggal | qty | amount | nomor urut kembaran.
 * NIK Sales tidak pernah dipakai.
 */
function txKeys_(array $rows): array {
    $out = []; $seen = [];
    foreach ($rows as $i => $t) {
        $company = text_($t['company'] ?? ''); $doc = text_($t['doc'] ?? ''); $line = text_($t['line'] ?? '');
        $date = text_($t['date'] ?? ''); $sku = text_($t['sku'] ?? '');
        $fp = $date . '|' . $sku . '|' . txcNum_($t['qty'] ?? 0) . '|' . txcNum_($t['amount'] ?? 0);
        if ($line !== '') { $ref = 'L|' . $company . '|' . $doc . '|' . $line; }
        else {
            $cust = text_($t['uniq'] ?? ($t['customer'] ?? ''));
            $base = 'C|' . $company . '|' . $doc . '|' . text_($t['sj'] ?? '') . '|' . $sku . '|' . $cust . '|' . $date . '|' . txcNum_($t['qty'] ?? 0) . '|' . txcNum_($t['amount'] ?? 0);
            $seen[$base] = ($seen[$base] ?? 0) + 1;
            $ref = $base . '|#' . $seen[$base];
        }
        $out[$i] = ['key' => sha1($ref), 'ref' => $ref, 'fp' => $fp];
    }
    return $out;
}

function txcActive_(): array {
    try { txcSchema_(); $q = db_()->query("SELECT id, tx_key, tx_ref, period, raw_nik, new_nik, fp FROM dsm_txcorr WHERE active = 1 ORDER BY id"); return $q->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { return []; }
}

/** Tabel pengecualian atribusi: hanya baris yang punya marker atau perlu ditinjau. Dibangun ulang tiap publish. */
function txAttrSchema_(): void {
    static $done = false; if ($done) return;
    db_()->exec("CREATE TABLE IF NOT EXISTS dsm_attr (tx_key CHAR(40) NOT NULL PRIMARY KEY, period CHAR(7) NOT NULL, tx_date CHAR(10) NOT NULL, company VARCHAR(100) NOT NULL DEFAULT '', doc VARCHAR(150) NOT NULL DEFAULT '', line VARCHAR(150) NOT NULL DEFAULT '', sku VARCHAR(100) NOT NULL DEFAULT '', brand VARCHAR(100) NOT NULL DEFAULT '', customer VARCHAR(100) NOT NULL DEFAULT '', induk VARCHAR(100) NOT NULL DEFAULT '', region VARCHAR(100) NOT NULL DEFAULT '', qty INT NOT NULL DEFAULT 0, amount DOUBLE NOT NULL DEFAULT 0, raw_nik VARCHAR(100) NOT NULL DEFAULT '', owner_nik VARCHAR(100) NOT NULL DEFAULT '', eff_nik VARCHAR(100) NOT NULL DEFAULT '', marker VARCHAR(20) NOT NULL DEFAULT '', review TINYINT NOT NULL DEFAULT 0, code VARCHAR(20) NOT NULL DEFAULT '', corr_id BIGINT NULL, KEY idx_pr (period, review), KEY idx_reg (period, region), KEY idx_doc (doc)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
    $done = true;
}
function txAttrWrite_(array $attr, array $orphans): void {
    txAttrSchema_(); $pdo = db_(); $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM dsm_attr');
        $cols = ['tx_key','period','tx_date','company','doc','line','sku','brand','customer','induk','region','qty','amount','raw_nik','owner_nik','eff_nik','marker','review','code','corr_id'];
        foreach (array_chunk($attr, 400) as $chunk) {
            $vals = []; foreach ($chunk as $a) foreach ($cols as $c) $vals[] = $a[$c] ?? ($c === 'corr_id' ? null : ($c === 'qty' || $c === 'review' ? 0 : ''));
            $ph = implode(',', array_fill(0, count($chunk), '(' . implode(',', array_fill(0, count($cols), '?')) . ')'));
            $pdo->prepare('INSERT INTO dsm_attr (' . implode(',', $cols) . ') VALUES ' . $ph)->execute($vals);
        }
        if ($own) $pdo->commit();
    } catch (Throwable $e) { if ($own && $pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    prop_set_('TXC_ORPHANS', json_encode(array_slice($orphans, 0, 200), JSON_UNESCAPED_UNICODE));
}

function txOwnerInfo_(array $ctx, string $induk, string $brand, string $date): array {
    if ($brand !== '') {
        $a = dbaAt_($ctx['dba'], $induk, $brand, $date);
        if ($a && text_($a['nik'] ?? '') !== '') return [text_($a['nik']), preg_match('/^BASELINE/i', (string) ($a['note'] ?? '')) ? 'BASELINE' : 'TRANSFER'];
    }
    $g = portfolioOwnerAt_($ctx['gen'], $induk, $date, '');
    if ($g !== '') return [$g, 'TRANSFER'];
    return [text_($ctx['meta']['customers'][$induk]['nik'] ?? ''), 'MASTER'];
}

/** Baris ST yang sama seperti yang dilihat publish_ (induk dealer + NIK merge). */
function txPrepRows_(array $meta, array $rows): array { return nikResolveRows_($meta, custResolveRows_($meta, $rows)); }

/**
 * @return array ['rows','ownerChanged','corrected','orphans','notes','attr']
 */
function effectiveRows_(array $meta, array $rows): array {
    $res = ['rows' => $rows, 'ownerChanged' => 0, 'corrected' => 0, 'orphans' => [], 'notes' => [], 'attr' => []];
    if (!$rows) return $res;
    if (prop_get_('TXEFF_OFF') === '1') { if (function_exists('portfolioResolveRows_')) $res['rows'] = portfolioResolveRows_($meta, $rows); return $res; }
    $raw = []; foreach ($rows as $i => $t) $raw[$i] = (string) ($t['nik'] ?? '');
    if (function_exists('portfolioResolveRows_')) $rows = portfolioResolveRows_($meta, $rows);
    $owner = []; foreach ($rows as $i => $t) { $owner[$i] = (string) ($t['nik'] ?? ''); if ($owner[$i] !== $raw[$i]) $res['ownerChanged']++; }
    $keys = txKeys_($rows); $corrOf = [];
    $corr = txcActive_();
    if ($corr) {
        $people = []; foreach ($meta['people'] ?? [] as $p) $people[(string) $p['nik']] = true;
        $byKey = []; foreach ($corr as $c) $byKey[$c['tx_key']] = $c;
        foreach ($keys as $i => $k) {
            $c = $byKey[$k['key']] ?? null; if (!$c) continue;
            unset($byKey[$k['key']]);
            if ($c['fp'] !== '' && $c['fp'] !== $k['fp']) { $res['orphans'][] = ['id' => (int) $c['id'], 'ref' => $c['tx_ref'], 'why' => 'FINGERPRINT']; continue; }
            if (!isset($people[$c['new_nik']])) { $res['orphans'][] = ['id' => (int) $c['id'], 'ref' => $c['tx_ref'], 'why' => 'NIK_TIDAK_AKTIF']; continue; }
            $rows[$i]['nik'] = $c['new_nik']; $corrOf[$i] = (int) $c['id']; $res['corrected']++;
        }
        foreach ($byKey as $c) $res['orphans'][] = ['id' => (int) $c['id'], 'ref' => $c['tx_ref'], 'why' => 'TIDAK_DITEMUKAN'];
    }
    // Pengecualian atribusi (marker + antrean Perlu Ditinjau)
    $ctx = ['meta' => $meta, 'dba' => function_exists('dbaIndex_') ? dbaIndex_() : [], 'gen' => function_exists('portfolioAssignmentIndex_') ? portfolioAssignmentIndex_() : []];
    $cut = function_exists('dbaCutover_') ? dbaCutover_() : '2026-10-01'; $custs = $meta['customers'] ?? []; $prods = $meta['products'] ?? [];
    $origin = [];
    foreach ($rows as $i => $t) { if (($t['qty'] ?? 0) > 0 && !empty($prods[$t['sku'] ?? '']['device'])) $origin[jkey([$t['company'] ?? '', $t['doc'] ?? '', $t['customer'] ?? '', js_upper(text_($prods[$t['sku']]['brand'] ?? ''))])] = (string) ($t['nik'] ?? ''); }
    foreach ($rows as $i => $t) {
        $date = (string) ($t['date'] ?? ''); $induk = (string) ($t['customer'] ?? ''); $sku = (string) ($t['sku'] ?? '');
        $brand = js_upper(text_($prods[$sku]['brand'] ?? '')); $eff = (string) ($t['nik'] ?? '');
        $marker = ''; $review = 0; $code = '';
        if (isset($corrOf[$i])) { $marker = 'KOREKSI'; $code = 'KOREKSI'; }
        else {
            $changed = $owner[$i] !== $raw[$i];
            if ($changed || $date >= $cut) {
                [$expNik, $kind] = txOwnerInfo_($ctx, $induk, $brand, $date);
                if ($changed) { $marker = $kind === 'TRANSFER' ? 'TRANSFER' : 'ST_BERBEDA'; $code = $marker; if ($marker === 'ST_BERBEDA' && $date >= $cut) $review = 1; }
                elseif ($expNik !== '' && $expNik !== $raw[$i]) { $marker = 'ST_BERBEDA'; $code = 'ST_BERBEDA'; $review = 1; }
            }
        }
        if ($induk !== '' && !isset($custs[$induk])) { $review = 1; if ($code === '') $code = 'DEALER_BARU'; }
        if (($t['qty'] ?? 0) < 0 && truthy($t['ref'] ?? null) && !empty($prods[$sku]['device'])) {
            $o = $origin[jkey([$t['company'] ?? '', $t['ref'] ?? '', $induk, $brand])] ?? null;
            if ($o !== null && $o !== $eff) { $review = 1; if ($code === '') $code = 'RETUR'; }
        }
        if ($marker === '' && !$review) continue;
        $res['attr'][] = ['tx_key' => $keys[$i]['key'], 'period' => substr($date, 0, 7), 'tx_date' => $date, 'company' => (string) ($t['company'] ?? ''), 'doc' => (string) ($t['doc'] ?? ''), 'line' => (string) ($t['line'] ?? ''),
            'sku' => $sku, 'brand' => $brand, 'customer' => (string) ($t['uniq'] ?? $induk), 'induk' => $induk, 'region' => (string) ($t['region'] ?? ''), 'qty' => (int) ($t['qty'] ?? 0), 'amount' => (float) ($t['amount'] ?? 0),
            'raw_nik' => $raw[$i], 'owner_nik' => $owner[$i], 'eff_nik' => $eff, 'marker' => $marker, 'review' => $review, 'code' => $code, 'corr_id' => $corrOf[$i] ?? null];
    }
    $res['rows'] = $rows;
    if ($res['ownerChanged']) $res['notes'][] = 'Effective Sales: ' . $res['ownerChanged'] . ' baris disesuaikan ke pemilik dealer-brand menurut tanggal transaksi.';
    if ($res['corrected']) $res['notes'][] = 'Koreksi transaksi diterapkan: ' . $res['corrected'] . ' baris.';
    if ($res['orphans']) $res['notes'][] = count($res['orphans']) . ' koreksi transaksi tidak menempel ke baris mana pun dan tidak diterapkan (perlu ditinjau).';
    return $res;
}

/**
 * Pembaca data mentah (Transaksi, export, audit, preview): kembalikan baris dalam bentuk ASLI
 * (customer tidak diubah ke induk) tetapi nik = Sales efektif (pemilik dealer-brand + koreksi).
 * Jumlah dan urutan baris dijamin sama dengan masukan.
 */
function txEffRaw_(array $meta, array $rows, bool $annot = false): array {
    if (!$rows) return $rows;
    try {
        $prep = txPrepRows_($meta, $rows); $res = effectiveRows_($meta, $prep); $eff = $res['rows'];
        if (count($eff) !== count($rows)) return $rows;
        foreach ($rows as $i => $t) { $n = (string) ($eff[$i]['nik'] ?? ''); if ($n !== '' && $n !== (string) ($t['nik'] ?? '')) { $rows[$i]['rawNik'] = $t['nik'] ?? null; $rows[$i]['nik'] = $n; } }
        if ($annot && $res['attr']) {
            $labels = ['KOREKSI' => 'Koreksi khusus', 'TRANSFER' => 'Dialihkan melalui transfer', 'ST_BERBEDA' => 'Sales ST berbeda'];
            $by = []; foreach ($res['attr'] as $a) $by[$a['tx_key']] = $a;
            $ids = []; foreach ($res['attr'] as $a) if (!empty($a['corr_id'])) $ids[] = (int) $a['corr_id'];
            $why = []; if ($ids) { try { $q = db_()->query('SELECT id, reason FROM dsm_txcorr WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')'); foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $why[(int) $r['id']] = (string) $r['reason']; } catch (Throwable $e) {} }
            foreach (txKeys_($prep) as $i => $k) { $a = $by[$k['key']] ?? null; if (!$a) continue; $rows[$i]['txMarker'] = $labels[$a['marker']] ?? ''; if (!empty($a['corr_id'])) $rows[$i]['txReason'] = $why[(int) $a['corr_id']] ?? ''; }
        }
    } catch (Throwable $e) {}
    return $rows;
}
