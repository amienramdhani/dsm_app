<?php
/**
 * ATURAN STATUS DEALER (Paket 2, 2026-11-17) — SATU fungsi sumber status untuk Dealer, Detail, Beranda, Analisa, export, agent.
 *
 * - Dasar hitung: SELL THRU (cubes), SELURUH transaksi dealer (tidak dibatasi siapa yang membuka).
 * - Status bulan P = pola 6 bulan selesai (P-6 … P-1). Tiap bulan: y = order (≥ N unit), h = di bawah min (1…N-1), n = tidak order (≤ 0).
 * - Aturan = daftar [pola 6 sel, status]; sel 'a' = bebas. Dicek dari atas, yang pertama cocok dipakai; tidak cocok → LAINNYA.
 * - Dihitung per dealer-brand DAN total (semua brand dijumlah per bulan).
 * - Dikunci: begitu bulan P-1 sudah ditutup (coverage closed), hasil disimpan ke storage/status/<P>.json dan tidak berubah lagi
 *   kecuali aturan diubah. Sebelum itu hasilnya "sementara" (dihitung langsung).
 * - Penanda bulan berjalan memakai aturan DA KPI (MIN_QTY_DA / TYPE_FILTER / TYPE_WAJIB / per promotor) — TIDAK mengubah status.
 *
 * TIDAK dipakai oleh perhitungan KPI / achievement / insentif.
 */
require_once __DIR__ . '/common.php';

const DS_PROP = 'DEALER_STATUS_RULES';
const DS_BASE = ['AKTIF', 'DI BAWAH MIN', 'BANGKIT', 'TURUN', 'IDLE', 'PASIF', 'NONAKTIF', 'LAINNYA'];
const DS_NEVER = 'BELUM ORDER';

function dsDefault_(): array {
    return ['min' => 2, 'rules' => [
        [['y', 'n', 'n', 'n', 'n', 'y'], 'BANGKIT'],
        [['a', 'y', 'n', 'n', 'n', 'y'], 'BANGKIT'],
        [['a', 'a', 'a', 'a', 'a', 'y'], 'AKTIF'],
        [['a', 'a', 'a', 'a', 'a', 'h'], 'DI BAWAH MIN'],
        [['a', 'a', 'y', 'y', 'n', 'n'], 'TURUN'],
        [['a', 'a', 'a', 'a', 'y', 'n'], 'IDLE'],
        [['a', 'a', 'a', 'a', 'h', 'n'], 'IDLE'],
        [['n', 'n', 'n', 'n', 'n', 'n'], 'NONAKTIF'],
        [['a', 'a', 'a', 'a', 'n', 'n'], 'PASIF'],
    ], 'extra' => [], 'brands' => [], 'by' => '', 'at' => ''];
}
/** Validasi + rapikan konfigurasi. Gagal = fail_(). */
function dsClean_(array $c): array {
    $min = (int) ($c['min'] ?? 2); if ($min < 1 || $min > 50) fail_('Minimal unit harus 1–50.');
    $extra = [];
    foreach ((array) ($c['extra'] ?? []) as $s) { $s = js_upper(trim((string) $s)); if ($s === '') continue;
        if (mb_strlen($s) > 12) fail_('Nama status maksimal 12 huruf: ' . $s);
        if (!preg_match('/^[A-Z0-9 .\-]+$/', $s)) fail_('Nama status hanya huruf/angka: ' . $s);
        if (!in_array($s, DS_BASE, true) && $s !== DS_NEVER) $extra[] = $s; }
    $extra = array_values(array_unique($extra)); $names = array_merge(DS_BASE, $extra);
    $rules = function ($rs, $where) use ($names) {
        $o = [];
        foreach ((array) $rs as $r) {
            $p = array_values((array) ($r[0] ?? [])); $s = js_upper((string) ($r[1] ?? ''));
            if (count($p) !== 6) fail_("Pola $where harus 6 bulan.");
            foreach ($p as $v) if (!in_array($v, ['y', 'h', 'n', 'a'], true)) fail_("Isi pola $where tidak dikenal.");
            if (!in_array($s, $names, true)) fail_("Status tidak dikenal di $where: $s");
            $o[] = [$p, $s];
        }
        if (count($o) > 30) fail_('Maksimal 30 aturan.');
        return $o;
    };
    $out = ['min' => $min, 'rules' => $rules($c['rules'] ?? [], 'aturan umum'), 'extra' => $extra, 'brands' => []];
    if (!$out['rules']) fail_('Minimal satu aturan.');
    foreach ((array) ($c['brands'] ?? []) as $b => $x) {
        $b = js_upper(trim((string) $b)); if ($b === '') continue;
        $bm = isset($x['min']) && $x['min'] !== '' && $x['min'] !== null ? (int) $x['min'] : null;
        if ($bm !== null && ($bm < 1 || $bm > 50)) fail_("Minimal unit $b harus 1–50.");
        $br = isset($x['rules']) && is_array($x['rules']) && $x['rules'] ? $rules($x['rules'], "brand $b") : null;
        if ($bm === null && $br === null) continue;
        $out['brands'][$b] = ['min' => $bm, 'rules' => $br];
    }
    return $out;
}
function dsConfig_(): array {
    if (isset($GLOBALS['DSM_DSCFG'])) return $GLOBALS['DSM_DSCFG'];
    $raw = prop_get_(DS_PROP); $c = $raw ? json_decode($raw, true) : null;
    $def = dsDefault_();
    if (!is_array($c)) $c = $def; else { try { $c = dsClean_($c) + ['by' => $c['by'] ?? '', 'at' => $c['at'] ?? '']; } catch (Throwable $e) { $c = $def; } }
    return $GLOBALS['DSM_DSCFG'] = $c;
}
function dsHash_(array $c): string { return substr(sha1(json_encode([$c['min'], $c['rules'], $c['brands']])), 0, 12); }
function dsStatuses_(array $c): array { return array_merge(DS_BASE, $c['extra'] ?? []); }
function dsMi_(string $p): int { return (int) substr($p, 0, 4) * 12 + (int) substr($p, 5, 2) - 1; }
function dsPm_(int $i): string { return sprintf('%04d-%02d', intdiv($i, 12), $i % 12 + 1); }
/** 6 bulan pola untuk periode P: P-6 … P-1 (lama → baru). */
function dsMonths_(string $period): array { $m = dsMi_($period); $o = []; for ($i = 6; $i >= 1; $i--) $o[] = dsPm_($m - $i); return $o; }
function dsCell_(float $q, int $min): string { return $q >= $min ? 'y' : ($q > 0 ? 'h' : 'n'); }
/** [status, nomor aturan (1..), null bila LAINNYA] */
function dsMatch_(array $rules, array $cells): array {
    foreach ($rules as $i => [$p, $s]) { $ok = true; foreach ($p as $k => $v) if ($v !== 'a' && $v !== $cells[$k]) { $ok = false; break; } if ($ok) return [$s, $i + 1]; }
    return ['LAINNYA', null];
}
function dsBrandRule_(array $c, string $brand): array {
    $x = $c['brands'][js_upper($brand)] ?? null;
    return [$x['min'] ?? $c['min'], $x['rules'] ?? $c['rules']];
}

/**
 * Hitung status semua dealer untuk periode P dari data (tanpa kunci).
 * Hasil: ['period','months','hash','total'=>[cust=>[s,cells]], 'brand'=>[cust=>[brand=>[s,cells]]], 'last'=>[cust=>[brand=>'YYYY-MM']]]
 */
function dsComputeRaw_(array $db, string $period, ?array $c = null): array {
    $c = $c ?? dsConfig_(); $months = dsMonths_($period); $mi = array_flip($months);
    $q = []; // q[cust][brand][idx]
    foreach ($months as $i => $m) foreach ($db['cubes'][$m] ?? [] as $r) { $cu = (string) $r['customer']; $b = (string) $r['brand']; $q[$cu][$b][$i] = ($q[$cu][$b][$i] ?? 0) + $r['q']; }
    // dealer-brand yang pernah order sebelum periode (data 'first' = order pertama sepanjang masa)
    $ever = [];
    foreach ($db['first'] ?? [] as $k => $f) {
        $kk = json_decode($k, true); if (!is_array($kk) || count($kk) < 2) continue;
        if (strcmp(substr((string) ($f['date'] ?? ''), 0, 7), $period) < 0) $ever[(string) $kk[0]][(string) $kk[1]] = true;
    }
    foreach ($q as $cu => $bs) foreach ($bs as $b => $_) $ever[$cu][$b] = true;
    $tot = []; $br = [];
    foreach ($ever as $cu => $bs) {
        $sum = [0, 0, 0, 0, 0, 0];
        foreach ($bs as $b => $_) {
            [$min, $rules] = dsBrandRule_($c, $b); $cells = [];
            for ($i = 0; $i < 6; $i++) { $v = $q[$cu][$b][$i] ?? 0; $sum[$i] += $v; $cells[] = dsCell_($v, $min); }
            [$s, $no] = dsMatch_($rules, $cells);
            $br[$cu][$b] = [$s, implode('', $cells), $no];
        }
        $cells = array_map(fn($v) => dsCell_($v, $c['min']), $sum);
        [$s, $no] = dsMatch_($c['rules'], $cells);
        $tot[$cu] = [$s, implode('', $cells), $no];
    }
    return ['period' => $period, 'months' => $months, 'hash' => dsHash_($c), 'total' => $tot, 'brand' => $br];
}

/** Data dasar status = SELURUH transaksi (bukan potongan region / cakupan pembuka). */
function dsBase_(array $db): array {
    $h = $db['_iddleHistory'] ?? null;
    if (!$h) return $db;
    return ['_vid' => $h['vid'], 'cubes' => $h['cubes'], 'coverage' => $h['coverage'], 'first' => $db['first'] ?? [], 'meta' => $db['meta'], '_tm' => $db['_tm'] ?? null];
}
/** Status terkunci periode P (dari file kunci bila ada & aturan sama; dibuat bila bulan P-1 sudah ditutup). */
function dsLocked_(array $db, string $period): array {
    $db = dsBase_($db); $c = dsConfig_(); $h = dsHash_($c); $key = PortalCore::vid($db) . '|' . $period . '|' . $h;
    return PortalCore::memo('dsLocked', $key, function () use ($db, $period, $c, $h) {
        $file = storage_dir_('status') . '/' . preg_replace('/[^0-9\-]/', '', $period) . '.json';
        $ks = array_keys($db['coverage'] ?? []); sort($ks, SORT_STRING); $latest = $ks ? end($ks) : $period;
        // Periode lampau tetap memakai kunci lamanya walau aturan sudah diubah; periode terbaru ikut aturan baru.
        if (is_file($file)) { $j = json_decode((string) @file_get_contents($file), true); if (is_array($j) && (($j['hash'] ?? '') === $h || strcmp($period, $latest) < 0)) return $j + ['locked' => true]; }
        $raw = dsComputeRaw_($db, $period, $c);
        $pp = dsPm_(dsMi_($period) - 1); $closed = truthy($db['coverage'][$pp]['closed'] ?? false) || dsPeriodLocked_($pp);
        $raw['at'] = gmdate('c'); $raw['locked'] = false;
        // Kunci hanya ditulis dari data penuh (bukan tampilan Telemarketing yang dipotong per pasangan).
        if ($closed) { $raw['locked'] = true; if (empty($db['_tm'])) @file_put_contents($file, json_encode($raw), LOCK_EX); }
        return $raw;
    });
}
function dsPeriodLocked_(string $p): bool {
    if (!function_exists('optionalTable_')) return false;
    try { foreach (optionalTable_('Period_Locks') as $r) if (($r['PERIODE'] ?? null) === $p) return PortalCore::active($r['TERKUNCI'] ?? null); } catch (Throwable $e) {}
    return false;
}
/** Hapus kunci (dipanggil saat aturan disimpan) — periode berjalan dihitung ulang dengan aturan baru. */
function dsUnlock_(string $period): void { @unlink(storage_dir_('status') . '/' . preg_replace('/[^0-9\-]/', '', $period) . '.json'); }

/** Aturan DA KPI untuk brand di periode: [minDA, types, must, perProm]. Ambil yang paling sering dipakai di editor KPI; tidak ada → [2,[],[],0]. */
function dsDaRule_(array $db, string $period, string $brand): array {
    return PortalCore::memo('dsDaRule', PortalCore::vid($db) . '|' . $period . '|' . $brand, function () use ($db, $period, $brand) {
        $cnt = []; $pick = [];
        foreach (['b' => $brand, 'all' => 'ALL'] as $kind => $want) {
            foreach ($db['meta']['policies'] ?? [] as $p) {
                if ($p['period'] !== $period || $p['metric'] !== 'DA' || js_upper((string) $p['brand']) !== js_upper($want)) continue;
                $r = [(float) js_or($p['minDA'] ?? null, 2), array_values($p['types'] ?? []), array_values($p['must'] ?? []), (float) ($p['perProm'] ?? 0)]; $k = json_encode($r);
                $cnt[$k] = ($cnt[$k] ?? 0) + 1; $pick[$k] = $r;
            }
            if ($cnt) { arsort($cnt); return $pick[array_key_first($cnt)]; }
        }
        return [2.0, [], [], 0.0];
    });
}
/**
 * Penanda bulan berjalan per dealer-brand dan total, dari SELURUH transaksi periode P.
 * ['brand'=>[cust=>[brand=>['q'=>, 'da'=>bool, 'noo'=>bool, 'last'=>date]]], 'total'=>[cust=>['q','brands_da'=>n,'da'=>bool]]]
 */
function dsCurrent_(array $db, string $period): array {
    $db = dsBase_($db);
    return PortalCore::memo('dsCurrent', PortalCore::vid($db) . '|' . $period, function () use ($db, $period) {
        $agg = [];
        foreach ($db['cubes'][$period] ?? [] as $r) {
            $cu = (string) $r['customer']; $b = (string) $r['brand'];
            $a = &$agg[$cu][$b]; $a ??= ['q' => 0, 'tq' => 0, 'mq' => 0, 'aq' => 0, 'last' => '', 'rows' => []];
            [$min, $types, $must, $pp] = dsDaRule_($db, $period, $b);
            $a['q'] += $r['q']; if (!$types || in_array($r['type'], $types, true)) $a['tq'] += $r['q'];
            if ($must && in_array($r['type'], $must, true)) $a['mq'] += $r['q'];
            if ($pp > 0) $a['aq'] += $r['aq'] ?? 0;
            if ($r['q'] > 0 && strcmp($r['date'], $a['last']) > 0) $a['last'] = $r['date'];
            unset($a);
        }
        $out = ['brand' => [], 'total' => []]; $first = $db['first'] ?? [];
        foreach ($agg as $cu => $bs) {
            $tq = 0; $nda = 0;
            foreach ($bs as $b => $a) {
                [$min, $types, $must, $pp] = dsDaRule_($db, $period, $b);
                $da = $pp > 0 ? $a['aq'] >= $pp * max(1, PortalCore::promotorCount($cu, $b)) : ($a['tq'] >= $min && (!$must || $a['mq'] >= 1));
                $f = $first[jkey([$cu, $b])] ?? null; $noo = $a['q'] > 0 && $f && substr($f['date'], 0, 7) === $period;
                $out['brand'][$cu][$b] = ['q' => $a['q'], 'da' => $da, 'noo' => $noo, 'last' => $a['last']]; $tq += $a['q']; if ($da) $nda++;
            }
            $out['total'][$cu] = ['q' => $tq, 'da' => $nda > 0, 'nda' => $nda];
        }
        return $out;
    });
}
/** Status satu dealer (total + per brand) untuk dipakai layar. */
function dsDealer_(array $db, string $period, string $cust): array {
    $L = dsLocked_($db, $period); $C = dsCurrent_($db, $period);
    $t = $L['total'][$cust] ?? null; $cur = $C['total'][$cust] ?? ['q' => 0, 'da' => false, 'nda' => 0];
    $status = $t ? $t[0] : DS_NEVER;
    $bs = []; $bc = [];
    foreach (array_unique(array_merge(array_keys($L['brand'][$cust] ?? []), array_keys($C['brand'][$cust] ?? []))) as $b) {
        $lb = $L['brand'][$cust][$b] ?? null; $cb = $C['brand'][$cust][$b] ?? null;
        $bs[$b] = $lb ? $lb[0] : ($cb && $cb['noo'] ? 'NOO' : DS_NEVER);
        $bc[$b] = ['pola' => $lb[1] ?? 'nnnnnn', 'aturan' => $lb[2] ?? null, 'q' => $cb['q'] ?? 0, 'da' => $cb['da'] ?? false, 'noo' => $cb['noo'] ?? false, 'last' => $cb['last'] ?? ''];
    }
    return ['status' => $status, 'pola' => $t[1] ?? 'nnnnnn', 'aturan' => $t[2] ?? null, 'order' => $cur, 'bangkit' => in_array($status, ['IDLE', 'PASIF', 'TURUN', 'NONAKTIF'], true) && $cur['q'] > 0,
        'bs' => $bs, 'bc' => $bc, 'locked' => $L['locked'], 'months' => $L['months']];
}
/** Ringkasan untuk kumpulan dealer (halaman Dealer / Beranda). $brand = null → TOTAL. */
function dsSummary_(array $db, string $period, array $custIds, ?string $brand = null): array {
    $L = dsLocked_($db, $period); $C = dsCurrent_($db, $period); $cfg = dsConfig_();
    $by = []; foreach (array_merge(dsStatuses_($cfg), [DS_NEVER]) as $s) $by[$s] = ['n' => 0, 'order' => 0];
    $order = 0; $da = 0; $bangkit = 0;
    foreach ($custIds as $cu) {
        $cu = (string) $cu;
        if ($brand === null) { $s = $L['total'][$cu][0] ?? DS_NEVER; $q = $C['total'][$cu]['q'] ?? 0; $d = $C['total'][$cu]['nda'] ?? 0; }
        else { if (!isset($L['brand'][$cu][$brand]) && !isset($C['brand'][$cu][$brand])) continue; $s = $L['brand'][$cu][$brand][0] ?? DS_NEVER; $q = $C['brand'][$cu][$brand]['q'] ?? 0; $d = !empty($C['brand'][$cu][$brand]['da']) ? 1 : 0; }
        $by[$s] ??= ['n' => 0, 'order' => 0]; $by[$s]['n']++; if ($q > 0) { $by[$s]['order']++; $order++; }
        $da += $d; if ($q > 0 && in_array($s, ['IDLE', 'PASIF', 'TURUN', 'NONAKTIF'], true)) $bangkit++;
    }
    return ['period' => $period, 'brand' => $brand, 'by' => $by, 'order' => $order, 'da' => $da, 'bangkit' => $bangkit, 'locked' => $L['locked'], 'lockedAt' => $L['at'] ?? null, 'months' => $L['months']];
}

/* ---------- API Admin: Aturan Status Dealer (Super Admin) ---------- */
function dsLatest_(array $db): string { $ks = array_keys($db['coverage'] ?? []); sort($ks, SORT_STRING); return $ks ? end($ks) : gmdate('Y-m'); }
function dsCount_(array $raw, array $statuses): array {
    $t = array_fill_keys($statuses, 0); $b = [];
    foreach ($raw['total'] as $v) $t[$v[0]] = ($t[$v[0]] ?? 0) + 1;
    foreach ($raw['brand'] as $bs) foreach ($bs as $br => $v) { $b[$br] ??= array_fill_keys($statuses, 0); $b[$br][$v[0]] = ($b[$br][$v[0]] ?? 0) + 1; }
    ksort($b, SORT_STRING); return ['total' => $t, 'brand' => $b];
}
function apiDealerStatusRules($token) {
    brandGuard_($token); $db = dsBase_(snapshot_()); $p = dsLatest_($db); $L = dsLocked_($db, $p); $c = dsConfig_();
    $brands = []; foreach ($db['meta']['products'] ?? [] as $x) if (truthy($x['device'] ?? null)) $brands[js_upper((string) $x['brand'])] = true; ksort($brands, SORT_STRING);
    $da = []; foreach (array_keys($brands) as $b) { [$m, $t, $mu, $pp] = dsDaRule_($db, $p, $b); $da[$b] = ['min' => $m, 'types' => $t, 'must' => $mu, 'perProm' => $pp]; }
    return ['config' => $c, 'default' => dsDefault_(), 'statuses' => dsStatuses_($c), 'base' => DS_BASE, 'period' => $p, 'months' => $L['months'], 'locked' => $L['locked'], 'at' => $L['at'] ?? null, 'brands' => array_keys($brands), 'da' => $da, 'count' => dsCount_($L, dsStatuses_($c))];
}
/** Dampak sebelum simpan: jumlah dealer per status (aturan sekarang vs usulan), dari data asli periode terbaru. */
function apiDealerStatusImpact($token, $cfg) {
    brandGuard_($token); $db = dsBase_(snapshot_()); $p = dsLatest_($db);
    $new = dsClean_(is_array($cfg) ? $cfg : []); $old = dsConfig_();
    $st = array_values(array_unique(array_merge(dsStatuses_($old), dsStatuses_($new))));
    return ['period' => $p, 'months' => dsMonths_($p), 'before' => dsCount_(dsComputeRaw_($db, $p, $old), $st), 'after' => dsCount_(dsComputeRaw_($db, $p, $new), $st), 'statuses' => $st];
}
function apiDealerStatusSave($token, $cfg, $reason = '') {
    $u = brandGuard_($token); $new = dsClean_(is_array($cfg) ? $cfg : []);
    $reason = trim((string) $reason); if (mb_strlen($reason) < 5) fail_('Isi alasan perubahan (minimal 5 huruf).');
    $old = dsConfig_(); $new['by'] = $u['nik'] ?? ''; $new['at'] = gmdate('c');
    prop_set_(DS_PROP, json_encode($new)); unset($GLOBALS['DSM_DSCFG']);
    audit_($u['nik'] ?? '', 'ATURAN_STATUS_DEALER', json_encode(['alasan' => $reason, 'lama' => ['min' => $old['min'], 'rules' => $old['rules'], 'brands' => $old['brands'], 'extra' => $old['extra']]]));
    $db = dsBase_(snapshot_()); dsUnlock_(dsLatest_($db));
    data_rev_bump_('DEALER_STATUS'); // cache jawaban Dealer/Beranda ikut berganti
    return ['ok' => true, 'config' => dsConfig_()];
}
