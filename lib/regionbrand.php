<?php
/**
 * Paket 10 (#38): region tampil per brand di Beranda (3 lapis) + target Big Region manual.
 *
 * Sheet baru (dibuat otomatis saat pertama disimpan):
 *   Region_Brand : BIG_REGION | BRAND | MODE (TAMPIL / SEMBUNYI)  — penetapan manual, hanya yang menyimpang dari otomatis
 *   Target_Big   : PERIODE | BIG_REGION | BRAND | METRIK (QTY / DA) | TARGET
 *
 * Aturan tampil per (Big Region × Brand):
 *   1. Ditetapkan manual (TAMPIL / SEMBUNYI)  → ikut penetapan.
 *   2. Otomatis masuk   → ada penjualan ATAU target (target sales atau target Big Region) pada bulan ini atau 2 bulan sebelumnya.
 *   3. Otomatis hilang  → tidak ada penjualan, penetapan, maupun target selama 3 bulan terakhir.
 */
require_once __DIR__ . '/common.php';

const RB_WINDOW = 3;
const RB_HEADS = ['BIG_REGION', 'BRAND', 'MODE'];
const TB_HEADS = ['PERIODE', 'BIG_REGION', 'BRAND', 'METRIK', 'TARGET'];

/** Dipanggil meta_(): baca dua sheet opsional → [regionBrand, targetBig]. */
function rbLoad_(): array {
    $rb = [];
    foreach (optionalTable_('Region_Brand') as $r) {
        $big = text_($r['BIG_REGION'] ?? ''); $br = js_upper(text_($r['BRAND'] ?? '')); $m = js_upper(text_($r['MODE'] ?? ''));
        if ($big === '' || $br === '' || !in_array($m, ['TAMPIL', 'SEMBUNYI'], true)) continue;
        $rb[] = ['big' => $big, 'brand' => $br, 'mode' => $m];
    }
    $tb = [];
    foreach (optionalTable_('Target_Big') as $r) {
        $v = kpiNum_($r['TARGET'] ?? null); $m = js_upper(text_($r['METRIK'] ?? '')); $per = kpiMonth_($r['PERIODE'] ?? '');
        if ($v === null || (is_float($v) && is_nan($v)) || $v < 0 || !preg_match('/^\d{4}-\d{2}$/', $per) || !in_array($m, ['QTY', 'DA'], true)) continue;
        $tb[] = ['period' => $per, 'big' => text_($r['BIG_REGION'] ?? ''), 'brand' => js_upper(text_($r['BRAND'] ?? '')), 'metric' => $m, 'target' => $v];
    }
    return [$rb, $tb];
}

function rbMonths_(string $period, int $n = RB_WINDOW): array {
    $o = []; for ($i = 0; $i < $n; $i++) $o[] = $i ? anaPrev_($period, $i) : $period; return $o;
}

/** Target Big Region manual (null = tidak diisi → pakai jumlah target sales). */
function rbBigTarget_(array $meta, string $period, $big, $brand, string $metric): ?float {
    $bu = js_upper(text_($big)); $br = js_upper(text_($brand));
    foreach ($meta['targetBig'] ?? [] as $t) if ($t['period'] === $period && $t['metric'] === $metric && $t['brand'] === $br && js_upper($t['big']) === $bu) return (float) $t['target'];
    return null;
}
/** NIK sales per Big Region. */
function rbTeams_(array $meta): array {
    $t = []; foreach ($meta['people'] as $p) if (truthy($p['sales'] ?? false) && truthy($p['wilayah'] ?? '')) $t[(string) $p['wilayah']][] = $p['nik'];
    return $t;
}
function rbSalesTarget_(array $meta, string $period, $big, $brand, string $metric): float {
    static $memoMeta = null, $teams = [];
    if ($memoMeta !== count($meta['people'])) { $teams = rbTeams_($meta); $memoMeta = count($meta['people']); }
    $team = $teams[(string) $big] ?? []; if (!$team) return 0.0;
    $t = PortalCore::target($meta, $period, $team, $brand, $metric);
    return (float) js_or($t['value'] ?? null, 0);
}

/** Penjualan perangkat per Big Region × brand × bulan (3 bulan terakhir). [big][BRAND][mo] = qty */
function rbActivity_(array $view, string $period): array {
    $meta = $view['meta']; $P = [];
    foreach ($meta['people'] as $p) $P[(string) $p['nik']] = $p;
    $dev = []; foreach (anaDeviceBrands_($meta) as $b) $dev[js_upper($b)] = 1;
    $out = [];
    foreach (rbMonths_($period) as $mo) foreach ($view['cubes'][$mo] ?? [] as $r) {
        $q = $r['q'] ?? 0; if (!truthy($q)) continue;
        $B = js_upper(text_($r['brand'] ?? '')); if (!isset($dev[$B])) continue;
        $p = $P[(string) ($r['nik'] ?? '')] ?? null; $big = $p ? text_($p['wilayah'] ?? '') : ''; if ($big === '') continue;
        $out[$big][$B][$mo] = ($out[$big][$B][$mo] ?? 0) + $q;
    }
    return $out;
}

/** [big][brand] => ['show'=>bool,'mode'=>''|TAMPIL|SEMBUNYI,'auto'=>bool (ada aktivitas 3 bulan),'q'=>qty bulan ini] */
function rbVisibility_(array $view, string $period, ?array $act = null): array {
    $meta = $view['meta']; $act = $act ?? rbActivity_($view, $period); $manual = [];
    foreach ($meta['regionBrand'] ?? [] as $x) $manual[js_upper($x['big']) . '|' . $x['brand']] = $x['mode'];
    $brands = anaDeviceBrands_($meta); $months = rbMonths_($period); $out = [];
    foreach ($meta['regionList'] ?? [] as $big) foreach ($brands as $b) {
        $B = js_upper($b); $mode = $manual[js_upper($big) . '|' . $B] ?? ''; $q = $act[$big][$B] ?? []; $recent = false;
        foreach ($months as $mo) {
            if (($q[$mo] ?? 0) > 0) { $recent = true; break; }
            foreach (['QTY', 'DA'] as $mt) {
                $t = rbBigTarget_($meta, $mo, $big, $b, $mt) ?? rbSalesTarget_($meta, $mo, $big, $b, $mt);
                if ($t > 0) { $recent = true; break 2; }
            }
        }
        $out[$big][$b] = ['show' => $mode === 'TAMPIL' ? true : ($mode === 'SEMBUNYI' ? false : $recent), 'mode' => $mode, 'auto' => $recent, 'q' => (float) ($q[$period] ?? 0)];
    }
    return $out;
}

// ------------------------------------------------------------------
// Admin: satu panel — region tampil per brand + target Big Region
// ------------------------------------------------------------------

function apiRegionBrandGet($token, $period = null) {
    $c = kpiSuper_($token); $src = $c['db']; $periods = kpiPeriods_($src);
    $period = js_or(kpiMonth_($period), kpiNow_()->format('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/D', (string) $period)) fail_('Periode tidak valid.');
    $prev = anaPrev_($period, 1);
    $view = coverageView_($src, $period, allNiks_($src), 'ST', $c['u']); $meta = $view['meta'];
    $vis = rbVisibility_($view, $period); $brands = anaDeviceBrands_($meta); $rows = [];
    foreach ($meta['regionList'] ?? [] as $big) {
        $cells = [];
        foreach ($brands as $b) {
            $v = $vis[$big][$b];
            $cells[$b] = ['mode' => $v['mode'], 'show' => $v['show'], 'auto' => $v['auto'], 'q' => $v['q'],
                'sales' => ['QTY' => rbSalesTarget_($meta, $period, $big, $b, 'QTY'), 'DA' => rbSalesTarget_($meta, $period, $big, $b, 'DA')],
                'big' => ['QTY' => rbBigTarget_($meta, $period, $big, $b, 'QTY'), 'DA' => rbBigTarget_($meta, $period, $big, $b, 'DA')],
                'prev' => ['QTY' => rbBigTarget_($meta, $prev, $big, $b, 'QTY'), 'DA' => rbBigTarget_($meta, $prev, $big, $b, 'DA')]];
        }
        $rows[] = ['big' => $big, 'cells' => js_obj($cells)];
    }
    return ['period' => $period, 'prev' => $prev, 'periods' => $periods, 'brands' => $brands, 'rows' => $rows];
}

function apiRegionBrandSave($token, $period, $changes, $reason = null) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function () use ($token, $period, $changes, $reason) {
        $c = kpiSuper_($token); $db = $c['db']; $period = kpiMonth_($period);
        if (!preg_match('/^\d{4}-\d{2}$/D', $period)) fail_('Periode harus YYYY-MM.');
        assertPeriodOpen_($period);
        if (!is_array($changes) || !$changes) fail_('Tidak ada perubahan.');
        $changes = array_values($changes); if (count($changes) > 3000) fail_('Terlalu banyak perubahan sekaligus.');
        $bigs = []; foreach ($db['meta']['regionList'] ?? [] as $b) $bigs[js_upper($b)] = $b;
        $brs = []; foreach (anaDeviceBrands_($db['meta']) as $b) $brs[js_upper($b)] = $b;
        $list = [];
        foreach ($changes as $i => $x) {
            $x = is_array($x) ? $x : []; $line = 'Perubahan ' . ($i + 1) . ': ';
            $big = $bigs[js_upper(text_($x['big'] ?? ''))] ?? null; $br = js_upper(text_($x['brand'] ?? ''));
            if ($big === null) fail_($line . 'Big Region tidak ada di Region Map.');
            if (!isset($brs[$br])) fail_($line . 'brand ' . $br . ' bukan brand perangkat.');
            $mode = js_upper(text_($x['mode'] ?? '')); if (!in_array($mode, ['', 'TAMPIL', 'SEMBUNYI'], true)) fail_($line . 'mode harus kosong / TAMPIL / SEMBUNYI.');
            $one = ['big' => $big, 'brand' => $br, 'mode' => $mode];
            foreach (['tQty' => 'QTY', 'tDA' => 'DA'] as $k => $m) {
                if (!array_key_exists($k, $x)) continue;
                $v = kpiNum_($x[$k]); if ($v !== null && (kn_nan($v) || $v < 0)) fail_($line . 'target ' . $m . ' ' . $big . ' ' . $br . ' tidak valid.');
                $one[$k] = $v; $one['has' . $k] = true;
            }
            $one['hasMode'] = array_key_exists('mode', $x);
            $list[] = $one;
        }
        kpiCommit_($c['u'], $db, ['Region_Brand', 'Target_Big'], 'Region per brand & target Big Region ' . $period . ' (' . count($list) . ' sel)', $reason, function () use ($list, $period) {
            $g = kpiGrid_('Region_Brand', RB_HEADS); $hit = [];
            foreach ($list as $x) if ($x['hasMode']) $hit[js_upper($x['big']) . '|' . $x['brand']] = $x;
            $g['rows'] = array_values(js_filter($g['rows'], fn($r) => !isset($hit[js_upper(text_($r['BIG_REGION'] ?? '')) . '|' . js_upper(text_($r['BRAND'] ?? ''))])));
            foreach ($hit as $x) if ($x['mode'] !== '') $g['rows'][] = ['BIG_REGION' => $x['big'], 'BRAND' => $x['brand'], 'MODE' => $x['mode']];
            kpiPut_('Region_Brand', $g);
            $t = kpiGrid_('Target_Big', TB_HEADS); $thit = [];
            foreach ($list as $x) foreach (['tQty' => 'QTY', 'tDA' => 'DA'] as $k => $m) if (!empty($x['has' . $k])) $thit[js_upper($x['big']) . '|' . $x['brand'] . '|' . $m] = ['x' => $x, 'm' => $m, 'v' => $x[$k]];
            $t['rows'] = array_values(js_filter($t['rows'], fn($r) => !(kpiMonth_($r['PERIODE'] ?? null) === $period && isset($thit[js_upper(text_($r['BIG_REGION'] ?? '')) . '|' . js_upper(text_($r['BRAND'] ?? '')) . '|' . js_upper(text_($r['METRIK'] ?? ''))]))));
            foreach ($thit as $h) if ($h['v'] !== null) $t['rows'][] = ['PERIODE' => $period, 'BIG_REGION' => $h['x']['big'], 'BRAND' => $h['x']['brand'], 'METRIK' => $h['m'], 'TARGET' => $h['v']];
            kpiPut_('Target_Big', $t);
        });
        return ['ok' => true, 'count' => count($list)];
    });
}

/**
 * Semua penjualan periode ini per Big Region (nama region huruf besar), dari SEMUA sales — dipakai KPI RGM berbasis region.
 * Memakai tampilan seluruh region supaya hasilnya sama di mana pun KPI RGM dihitung.
 */
function rgmRegionRows_(string $period): array {
    static $memo = [];
    $src = snapshot_(); $k = PortalCore::vid($src) . '|' . $period;
    if (!isset($memo[$k])) {
        $memo = [];
        $ids = allNiks_($src); $v = coverageView_($src, $period, $ids, 'ST'); $by = [];
        foreach (PortalCore::slice($v, $period, $ids) as $r) $by[js_upper(text_($r['region'] ?? ''))][] = $r;
        $memo[$k] = $by;
    }
    return $memo[$k];
}

// ------------------------------------------------------------------
// Paket 10 (#39): panel KPI RGM — semua RGM, ringkasan poin per brand + rincian
// ------------------------------------------------------------------

/** Poin tiap indikator = skor × bobot ÷ (total bobot indikator terhitung di kelompoknya) ÷ jumlah kelompok terhitung → jumlah poin = skor KPI akhir. */
function rgmAddPoints_(array &$kpi): void {
    $scoredGroups = 0;
    foreach ($kpi['groups'] as $g) if (!($g['na'] ?? false) && ($g['score'] ?? null) !== null) $scoredGroups++;
    foreach ($kpi['groups'] as &$g) {
        $w = 0.0; foreach ($g['indicators'] as $x) if (($x['score'] ?? null) !== null) $w += (float) $x['weight'];
        foreach ($g['indicators'] as &$x) $x['pts'] = (($x['score'] ?? null) !== null && $w > 0 && $scoredGroups > 0 && ($g['score'] ?? null) !== null) ? (float) $x['score'] * (float) $x['weight'] / $w / $scoredGroups : null;
        unset($x);
    }
    unset($g);
}

function apiKpiRgmPanel($token, $period) {
    $source = snapshot_(); $u = guard_($token, false, false, $source); $period = js_str($period);
    if (PortalCore::lv($u['role'] ?? '') < 3) fail_('Panel KPI RGM hanya untuk RGM ke atas.');
    if (!isset($source['coverage'][$period])) fail_('Periode belum tersedia.');
    $people = $source['meta']['people']; $ids = allNiks_($source);
    $db = coverageView_($source, $period, $ids, 'ST');
    $rows = []; $brandSet = [];
    foreach ($people as $p) {
        if (($p['role'] ?? '') !== 'RGM' || preg_match('/^VAC/i', (string) $p['nik'])) continue;
        $team = PortalCore::scope($people, $p['nik']);
        $own = js_some($db['meta']['rgmPolicies'] ?? [], fn($c) => $c['period'] === $period && $c['nik'] === $p['nik']);
        $bigs = kpiRgmBigs_($db, $p['nik']); $wil = text_($p['wilayah'] ?? '');
        try { $k = PortalCore::kpi($db, $period, $team, $p['wilayah'] ?? null, $p); } catch (Throwable $e) { continue; }
        if (empty($k['groups'])) continue;
        rgmAddPoints_($k);
        $by = [];
        foreach ($k['groups'] as $gi => $g) foreach ($g['indicators'] as $x) {
            $b = js_upper(text_($x['brand'] ?? 'ALL')); if ($b === '' || $b === 'ALL') $b = 'LAINNYA';
            $brandSet[$b] = 1;
            if (($x['pts'] ?? null) !== null) $by[$b] = ($by[$b] ?? 0) + $x['pts']; else $by[$b] = $by[$b] ?? null;
        }
        $rows[] = ['nik' => $p['nik'], 'nama' => $p['nama'], 'own' => $own, 'region' => $bigs ? implode(' · ', $bigs) : ($wil !== '' ? $wil : '—'), 'wilayah' => $wil, 'me' => $p['nik'] === $u['nik'],
            'total' => $k['score'], 'status' => $k['status'], 'byBrand' => $by, 'kpi' => $k];
    }
    $order = array_keys($brandSet); sort($order, SORT_STRING); $order = array_values(array_filter($order, fn($b) => $b !== 'LAINNYA'));
    if (isset($brandSet['LAINNYA'])) $order[] = 'LAINNYA';
    usort($rows, fn($a, $b) => ($b['total'] ?? -1) <=> ($a['total'] ?? -1) ?: strcmp($a['nama'], $b['nama']));
    $rank = 0; $prev = null; $n = 0;
    foreach ($rows as &$r) { if ($r['total'] === null) { $r['rank'] = null; continue; } $n++; if ($prev === null || abs($r['total'] - $prev) > 1e-9) $rank = $n; $prev = $r['total']; $r['rank'] = $rank; }
    unset($r);
    $avg = []; foreach ($order as $b) { $v = []; foreach ($rows as $r) if (isset($r['byBrand'][$b]) && $r['byBrand'][$b] !== null) $v[] = $r['byBrand'][$b]; $avg[$b] = $v ? array_sum($v) / count($v) : null; }
    $tv = array_values(array_filter(array_map(fn($r) => $r['total'], $rows), fn($x) => $x !== null));
    foreach ($rows as &$r) $r['byBrand'] = js_obj($r['byBrand']);
    unset($r);
    return ['period' => $period, 'cutoff' => $db['coverage'][$period]['cutoff'] ?? null, 'partial' => !empty($db['partial']), 'noData' => !empty($db['noData']), 'brands' => $order, 'rows' => $rows, 'avg' => js_obj($avg), 'avgTotal' => $tv ? array_sum($tv) / count($tv) : null];
}
