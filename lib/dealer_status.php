<?php
/**
 * ATURAN STATUS DEALER (Paket 3, 2026-11-18: frekuensi order 3 bulan closing) — SATU fungsi sumber status
 * untuk Dealer, Detail, Beranda, Analisa, export, agent.
 *
 * - Dasar hitung: SELL THRU qty (cubes), SELURUH transaksi dealer, mulai bulan basis (default 2026-01).
 * - 1 bulan dihitung order bila qty bersih bulan itu > 0 (banyak invoice = 1 bulan; retur tidak menambah bulan).
 * - Status bulan P dari jendela W bulan closing (P-W … P-1, default 3):
 *     ACTIVE   = order ≥ act bulan (default 2)
 *     PASSIVE  = order 1 … act-1 bulan
 *     IDLE     = tidak order W … inact-1 bulan berturut-turut (default 3–5)
 *     INACTIVE = tidak order ≥ inact bulan berturut-turut (default 6)
 *     PROSPECT = belum pernah order sejak bulan basis (per brand: belum pernah ambil brand itu; TOTAL: brand apa pun)
 * - Dihitung per dealer-brand DAN total (brand yang dimatikan, mis. HONOR, tidak ikut total).
 * - Dikunci: begitu bulan P-1 sudah ditutup, hasil disimpan ke storage/status/<P>.json. Bulan berjalan (MTD) hanya penanda progres.
 *
 * TIDAK dipakai oleh perhitungan KPI / achievement / insentif.
 */
require_once __DIR__ . '/common.php';

const DS_PROP = 'DEALER_STATUS_RULES_V3';
const DS_BASE = ['ACTIVE', 'PASSIVE', 'IDLE', 'INACTIVE'];
const DS_NEVER = 'PROSPECT';

function dsDefault_(): array {
    return ['win' => 3, 'act' => 2, 'inact' => 6, 'since' => '2026-01', 'off' => ['HONOR'], 'by' => '', 'at' => ''];
}
/** Validasi + rapikan konfigurasi. Gagal = fail_(). */
function dsClean_(array $c): array {
    $d = dsDefault_();
    $win = (int) ($c['win'] ?? $d['win']); if ($win < 1 || $win > 12) fail_('Jendela harus 1–12 bulan.');
    $act = (int) ($c['act'] ?? $d['act']); if ($act < 1 || $act > $win) fail_("ACTIVE minimal 1–$win bulan order.");
    $inact = (int) ($c['inact'] ?? $d['inact']); if ($inact <= $win || $inact > 36) fail_('INACTIVE harus lebih dari ' . $win . ' bulan (maks 36).');
    $since = (string) ($c['since'] ?? $d['since']); if (!preg_match('/^20\d\d-(0[1-9]|1[0-2])$/', $since)) fail_('Bulan basis tidak valid (format YYYY-MM).');
    $off = [];
    foreach ((array) ($c['off'] ?? []) as $b) { $b = js_upper(trim((string) $b)); if ($b !== '') $off[] = $b; }
    $off = array_values(array_unique($off)); sort($off, SORT_STRING);
    return ['win' => $win, 'act' => $act, 'inact' => $inact, 'since' => $since, 'off' => $off];
}
function dsConfig_(): array {
    if (isset($GLOBALS['DSM_DSCFG'])) return $GLOBALS['DSM_DSCFG'];
    $raw = prop_get_(DS_PROP); $c = $raw ? json_decode($raw, true) : null;
    $def = dsDefault_();
    if (!is_array($c)) $c = $def; else { try { $c = dsClean_($c) + ['by' => $c['by'] ?? '', 'at' => $c['at'] ?? '']; } catch (Throwable $e) { $c = $def; } }
    return $GLOBALS['DSM_DSCFG'] = $c;
}
function dsHash_(array $c): string { return substr(sha1('v3' . json_encode([$c['win'], $c['act'], $c['inact'], $c['since'], $c['off']])), 0, 12); }
function dsStatuses_(array $c): array { return DS_BASE; }
function dsMi_(string $p): int { return (int) substr($p, 0, 4) * 12 + (int) substr($p, 5, 2) - 1; }
function dsPm_(int $i): string { return sprintf('%04d-%02d', intdiv($i, 12), $i % 12 + 1); }
/** Bulan jendela untuk periode P: P-W … P-1 (lama → baru). */
function dsMonths_(string $period, ?array $c = null): array { $c = $c ?? dsConfig_(); $m = dsMi_($period); $o = []; for ($i = $c['win']; $i >= 1; $i--) $o[] = dsPm_($m - $i); return $o; }
/** Status dari deret qty per bulan (index 0 = bulan basis … terakhir = P-1). null = belum pernah order (PROSPECT). */
function dsClassify_(array $qs, array $c): ?array {
    $n = count($qs); $last = -1; for ($i = $n - 1; $i >= 0; $i--) if (($qs[$i] ?? 0) > 0) { $last = $i; break; }
    if ($last < 0) return null;
    $cells = ''; $cnt = 0;
    for ($i = $n - $c['win']; $i < $n; $i++) { $y = $i >= 0 && ($qs[$i] ?? 0) > 0; $cells .= $y ? 'y' : ($i < 0 ? 'a' : 'n'); if ($y) $cnt++; }
    $gap = $n - 1 - $last; // bulan tanpa order berturut-turut sampai P-1
    $s = $cnt >= $c['act'] ? 'ACTIVE' : ($cnt >= 1 ? 'PASSIVE' : ($gap >= $c['inact'] ? 'INACTIVE' : 'IDLE'));
    return [$s, $cells, $gap];
}

/**
 * Hitung status semua dealer untuk periode P dari data (tanpa kunci).
 * Hasil: ['period','months','hash','total'=>[cust=>[s,cells,gap]], 'brand'=>[cust=>[brand=>[s,cells,gap]]]]
 */
function dsComputeRaw_(array $db, string $period, ?array $c = null): array {
    $c = $c ?? dsConfig_(); $p0 = dsMi_($c['since']); $p1 = dsMi_($period); $n = max(0, $p1 - $p0); $off = array_flip($c['off']);
    $q = []; // q[cust][brand][idx]
    for ($i = 0; $i < $n; $i++) foreach ($db['cubes'][dsPm_($p0 + $i)] ?? [] as $r) { $cu = (string) $r['customer']; $b = (string) $r['brand']; $q[$cu][$b][$i] = ($q[$cu][$b][$i] ?? 0) + $r['q']; }
    $tot = []; $br = [];
    foreach ($q as $cu => $bs) {
        $sum = array_fill(0, $n, 0);
        foreach ($bs as $b => $qs) {
            $row = []; for ($i = 0; $i < $n; $i++) { $row[$i] = $qs[$i] ?? 0; if (!isset($off[js_upper($b)])) $sum[$i] += $row[$i]; }
            if ($x = dsClassify_($row, $c)) $br[$cu][$b] = $x;
        }
        if ($x = dsClassify_($sum, $c)) $tot[$cu] = $x;
    }
    return ['period' => $period, 'months' => dsMonths_($period, $c), 'hash' => dsHash_($c), 'total' => $tot, 'brand' => $br];
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
        $bs[$b] = $lb ? $lb[0] : DS_NEVER;
        $bc[$b] = ['pola' => $lb[1] ?? str_repeat('n', count($L['months'])), 'gap' => $lb[2] ?? null, 'q' => $cb['q'] ?? 0, 'da' => $cb['da'] ?? false, 'noo' => $cb['noo'] ?? false, 'last' => $cb['last'] ?? ''];
    }
    return ['status' => $status, 'pola' => $t[1] ?? str_repeat('n', count($L['months'])), 'gap' => $t[2] ?? null, 'order' => $cur, 'noo' => !$t && $cur['q'] > 0, 'bangkit' => in_array($status, ['IDLE', 'INACTIVE'], true) && $cur['q'] > 0,
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
        else { $s = $L['brand'][$cu][$brand][0] ?? DS_NEVER; $q = $C['brand'][$cu][$brand]['q'] ?? 0; $d = !empty($C['brand'][$cu][$brand]['da']) ? 1 : 0; }
        $by[$s] ??= ['n' => 0, 'order' => 0]; $by[$s]['n']++; if ($q > 0) { $by[$s]['order']++; $order++; }
        $da += $d; if ($q > 0 && in_array($s, ['IDLE', 'INACTIVE'], true)) $bangkit++;
    }
    return ['period' => $period, 'brand' => $brand, 'by' => $by, 'order' => $order, 'da' => $da, 'bangkit' => $bangkit, 'locked' => $L['locked'], 'lockedAt' => $L['at'] ?? null, 'months' => $L['months']];
}

/* ---------- API Admin: Aturan Status Dealer (Super Admin) ---------- */
function dsLatest_(array $db): string { $ks = array_keys($db['coverage'] ?? []); sort($ks, SORT_STRING); return $ks ? end($ks) : gmdate('Y-m'); }
/** Kode dealer master yang ikut dihitung (bukan TUTUP, bukan sedang diajukan tutup). null = master tidak tersedia. */
function dsEligible_(array $db): ?array {
    $cs = $db['meta']['customers'] ?? null; if (!is_array($cs) || !$cs) return null;
    $closed = (function_exists('dealerClosed_') ? dealerClosed_() : []) + (function_exists('dealerPendingClose_') ? dealerPendingClose_() : []);
    $ids = []; foreach ($cs as $k => $x) { $id = (string) (is_array($x) ? ($x['id'] ?? $k) : $k); if (!isset($closed[$id])) $ids[$id] = 1; } return $ids;
}
/** Jumlah dealer master yang ikut dihitung (pembilang PROSPECT). */
function dsDealerCount_(array $db): ?int { $e = dsEligible_($db); return $e === null ? null : count($e); }
/**
 * Hitungan per status. PROSPECT = dealer master yang ikut dihitung ($eligible) dan belum punya status (total / per brand).
 * Dihitung langsung, bukan "jumlah master dikurangi yang berstatus", supaya dealer tutup / diajukan tutup yang pernah order
 * tidak mengurangi PROSPECT dua kali. $eligible boleh int (cara lama) untuk kompatibilitas.
 */
function dsCount_(array $raw, array $statuses, $eligible = null): array {
    $t = array_fill_keys($statuses, 0); $b = [];
    foreach ($raw['total'] as $v) $t[$v[0]] = ($t[$v[0]] ?? 0) + 1;
    foreach ($raw['brand'] as $bs) foreach ($bs as $br => $v) { $b[$br] ??= array_fill_keys($statuses, 0); $b[$br][$v[0]] = ($b[$br][$v[0]] ?? 0) + 1; }
    if (is_int($eligible)) { $t[DS_NEVER] = max(0, $eligible - array_sum($t)); foreach ($b as $br => $x) $b[$br][DS_NEVER] = max(0, $eligible - array_sum($x)); }
    elseif (is_array($eligible)) {
        $t[DS_NEVER] = 0; foreach ($eligible as $id => $_) if (!isset($raw['total'][$id])) $t[DS_NEVER]++;
        foreach ($b as $br => $x) { $n = 0; foreach ($eligible as $id => $_) if (!isset($raw['brand'][$id][$br])) $n++; $b[$br][DS_NEVER] = $n; }
    }
    ksort($b, SORT_STRING); return ['total' => $t, 'brand' => $b];
}
function apiDealerStatusRules($token) {
    brandGuard_($token); $db = dsBase_(snapshot_()); $p = dsLatest_($db); $L = dsLocked_($db, $p); $c = dsConfig_();
    $brands = []; foreach ($db['meta']['products'] ?? [] as $x) if (truthy($x['device'] ?? null)) $brands[js_upper((string) $x['brand'])] = true; ksort($brands, SORT_STRING);
    $da = []; foreach (array_keys($brands) as $b) { [$m, $t, $mu, $pp] = dsDaRule_($db, $p, $b); $da[$b] = ['min' => $m, 'types' => $t, 'must' => $mu, 'perProm' => $pp]; }
    return ['config' => $c, 'default' => dsDefault_(), 'statuses' => DS_BASE, 'base' => DS_BASE, 'never' => DS_NEVER, 'period' => $p, 'months' => $L['months'], 'locked' => $L['locked'], 'at' => $L['at'] ?? null, 'brands' => array_keys($brands), 'da' => $da, 'count' => dsCount_($L, DS_BASE, dsDealerCount_($db))];
}
/** Dampak sebelum simpan: jumlah dealer per status (aturan sekarang vs usulan), dari data asli periode terbaru. */
function apiDealerStatusImpact($token, $cfg) {
    brandGuard_($token); $db = dsBase_(snapshot_()); $p = dsLatest_($db);
    $new = dsClean_(is_array($cfg) ? $cfg : []); $old = dsConfig_();
    $st = array_merge(DS_BASE, [DS_NEVER]); $all = dsEligible_($db);
    return ['period' => $p, 'months' => dsMonths_($p, $new), 'before' => dsCount_(dsComputeRaw_($db, $p, $old), DS_BASE, $all), 'after' => dsCount_(dsComputeRaw_($db, $p, $new), DS_BASE, $all), 'statuses' => $st];
}
function apiDealerStatusSave($token, $cfg, $reason = '') {
    $u = brandGuard_($token); $new = dsClean_(is_array($cfg) ? $cfg : []);
    $reason = trim((string) $reason); if (mb_strlen($reason) < 5) fail_('Isi alasan perubahan (minimal 5 huruf).');
    $old = dsConfig_(); $new['by'] = $u['nik'] ?? ''; $new['at'] = gmdate('c');
    prop_set_(DS_PROP, json_encode($new)); unset($GLOBALS['DSM_DSCFG']);
    audit_($u['nik'] ?? '', 'ATURAN_STATUS_DEALER', json_encode(['alasan' => $reason, 'lama' => array_intersect_key($old, dsDefault_()), 'baru' => $new]));
    $db = dsBase_(snapshot_()); dsUnlock_(dsLatest_($db));
    data_rev_bump_('DEALER_STATUS'); // cache jawaban Dealer/Beranda ikut berganti
    return ['ok' => true, 'config' => dsConfig_()];
}
