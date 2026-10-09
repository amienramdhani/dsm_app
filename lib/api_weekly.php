<?php
/** Analisa mingguan Brand dan perbandingan Tim — terjemahan Weekly.gs. Seluruh ID selalu dipotong oleh cakupan user. */
require_once __DIR__ . '/common.php';

/** Number(v) versi JS (NaN untuk teks tidak valid). */
function analysisNum_($v) {
    if ($v === null) return 0;
    if (is_bool($v)) return $v ? 1 : 0;
    if (is_int($v) || is_float($v)) return $v;
    if (is_array($v)) return count($v) === 0 ? 0 : (count($v) === 1 ? analysisNum_(array_values($v)[0]) : NAN);
    $s = js_trim((string) $v);
    if ($s === '') return 0;
    if (preg_match('/^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/', $s)) { $f = (float) $s; return (floor($f) == $f && abs($f) < 9007199254740992) ? (int) $f : $f; }
    if (preg_match('/^0[xX][0-9a-fA-F]+$/', $s)) return hexdec(substr($s, 2));
    if (preg_match('/^[+-]?Infinity$/', $s)) return $s[0] === '-' ? -INF : INF;
    return NAN;
}
/** Number(v)||d */
function analysisNumOr_($v, $d) { $n = analysisNum_($v); return truthy($n) ? $n : $d; }

function analysisRange_($period, $week) {
    PortalCore::month($period); $period = text_($period); $week = max(1, min(5, analysisNumOr_($week, 1)));
    $last = (int) substr(PortalCore::end($period), 8); $from = ($week - 1) * 7 + 1; $to = min($last, $week * 7);
    return ['from' => $period . '-' . js_pad2(js_str($from)), 'to' => $period . '-' . js_pad2(js_str($to)), 'label' => 'W' . js_str($week)];
}

function analysisMetric_(array $db, array $ids, array $range, string $metric, string $brand, $type) {
    $rows = js_filter(PortalCore::slice($db, substr($range['from'], 0, 7), $ids), fn($r) => strcmp($r['date'], $range['from']) >= 0 && strcmp($r['date'], $range['to']) <= 0 && ($brand === 'ALL' || $r['brand'] === $brand) && (!truthy($type) || $type === 'ALL' || $r['type'] === $type));
    if ($metric === 'ST') return js_sum($rows, fn($r) => analysisNumOr_($r['q'] ?? null, 0));
    if ($metric === 'OMZET') return PortalCore::round(js_sum($rows, fn($r) => analysisNumOr_($r['a'] ?? null, 0)));
    if ($metric === 'DA') {
        $dealers = []; foreach ($rows as $r) { $k = (string) $r['customer']; $dealers[$k] = js_or($dealers[$k] ?? null, 0) + analysisNumOr_($r['q'] ?? null, 0); }
        return count(js_filter(array_values($dealers), fn($q) => $q >= 2));
    }
    if ($metric === 'NOO') {
        $customers = [];
        foreach ($rows as $r) { $first = $db['first'][PortalCore::key([$r['customer'], $r['brand']])] ?? null; if (truthy($first) && strcmp($first['date'], $range['from']) >= 0 && strcmp($first['date'], $range['to']) <= 0) $customers[] = $r['customer']; }
        return count(uniq($customers));
    }
    if ($metric === 'SO') {
        $n = 0;
        foreach (stockMoves_($db, (string) $range['from'], (string) $range['to'], ['out' => true]) as $m) {
            if (strcmp($m['date'], $range['from']) < 0 || strcmp($m['date'], $range['to']) > 0 || !truthy($m['outQty'] ?? null) || !StockCore::canSee($db, $ids, $m['customer'])) continue;
            $p = $db['meta']['products'][$m['sku']] ?? null;
            $n += (($brand === 'ALL' || ($p['brand'] ?? null) === $brand) && (!truthy($type) || $type === 'ALL' || js_or($p['type'] ?? null, js_or($p['nama'] ?? null, $m['sku'])) === $type)) ? analysisNumOr_($m['outQty'], 0) : 0;
        }
        return $n;
    }
    fail_('Indikator analisa tidak dikenal.');
}

function analysisIds_(array $source, array $u, $selection, $filter) {
    $ids = allowed_($source, $u, $selection); $people = $source['meta']['people'];
    $chosen = js_or($filter['sales'] ?? null, js_or($filter['asm'] ?? null, js_or($filter['rgm'] ?? null, '')));
    if (truthy($chosen)) {
        if (!in_array($chosen, $ids, true)) fail_('Filter berada di luar cakupan.');
        $scoped = js_set(PortalCore::scope($people, $chosen)); $ids = js_filter($ids, fn($id) => isset($scoped[(string) $id]));
    }
    $big = js_str(js_or($filter['big'] ?? null, 'ALL')); $region = js_str(js_or($filter['region'] ?? null, 'ALL')); $sub = js_str(js_or($filter['sub'] ?? null, 'ALL'));
    $pp = fn($id) => PortalCore::person($people, $id);
    if ($big !== 'ALL') $ids = js_filter($ids, fn($id) => ($pp($id)['wilayah'] ?? null) === $big);
    if ($region !== 'ALL') $ids = js_filter($ids, function ($id) use ($pp, $region) { $p = $pp($id); return js_or($p['portfolio'] ?? null, $p['region'] ?? null) === $region; });
    if ($sub !== 'ALL') $ids = js_filter($ids, function ($id) use ($pp, $sub) { $p = $pp($id); return js_or($p['portfolio'] ?? null, $p['region'] ?? null) === $sub; });
    return $ids;
}

function analysisPeriodRange_(array $db, $period, $day) {
    $period = PortalCore::month($period);
    $sourceCut = js_or($db['coverage'][$period]['cutoff'] ?? null, PortalCore::end($period)); $last = (int) substr(PortalCore::end($period), 8);
    $cut = min($last, analysisNum_(substr($sourceCut, 8)), analysisNumOr_($day, $last));
    return ['from' => $period . '-01', 'to' => $period . '-' . js_pad2(js_str($cut)), 'label' => $period];
}

function analysisTypes_(array $db, array $ids, string $period, string $brand) {
    $names = [];
    foreach (PortalCore::slice($db, $period, $ids) as $r) if ($brand === 'ALL' || $r['brand'] === $brand) $names[] = $r['type'] ?? null;
    foreach (js_values($db['meta']['products'] ?? []) as $p) if (($brand === 'ALL' || ($p['brand'] ?? null) === $brand) && truthy($p['device'] ?? null)) $names[] = js_or($p['type'] ?? null, $p['nama'] ?? null);
    return js_sort(uniq(js_filter(array_map('text_', $names), 'truthy')), fn($a, $b) => lc_id($a, $b));
}

function apiWeeklyAnalysis($token, $period = null, $selection = null, $request = null) {
    $source = snapshot_(); $u = guard_($token, false, false, $source); $filter = is_array($request) ? ($request['filter'] ?? null) : null;
    $ids = analysisIds_($source, $u, $selection, is_array($filter) ? $filter : []);
    $period = js_str($period);
    $db = coverageView_($source, $period, $ids, 'ST', $u);
    $req = is_array($request) ? $request : [];
    $brand = js_str(js_or($req['brand'] ?? null, 'ALL')); $type = js_str(js_or($req['type'] ?? null, 'ALL')); $metric = js_str(js_or($req['metric'] ?? null, 'ST')); $mode = js_upper(js_str(js_or($req['mode'] ?? null, 'WOW')));
    if (!in_array($mode, ['WOW', 'MOM'], true)) fail_('Mode perbandingan tidak dikenal.');
    if (!in_array($metric, ['ST', 'SO', 'OMZET'], true)) fail_('Metrik analisa brand tidak dikenal.');
    $periods = js_keys($source['coverage'] ?? []); sort($periods, SORT_STRING); $periods = array_reverse($periods);
    $cut = analysisNum_(substr(js_or($db['coverage'][$period]['cutoff'] ?? null, PortalCore::end($period)), 8));
    $maxWeek = max(1, min(5, (int) ceil($cut / 7))); $weekOptions = range(1, $maxWeek);
    if ($mode === 'MOM') {
        $monthB = js_str(js_or($req['periodB'] ?? null, $period)); $monthA = js_str(js_or($req['periodA'] ?? null, PortalCore::prev($monthB)));
        if (!in_array($monthB, $periods, true)) $monthB = $period;
        if (!in_array($monthA, $periods, true)) $monthA = js_or(js_find($periods, fn($p) => strcmp($p, $monthB) < 0), PortalCore::prev($monthB));
        $day = min(analysisNum_(substr(js_or($db['coverage'][$monthA]['cutoff'] ?? null, PortalCore::end($monthA)), 8)), analysisNum_(substr(js_or($db['coverage'][$monthB]['cutoff'] ?? null, PortalCore::end($monthB)), 8)));
        $rangeA = analysisPeriodRange_($db, $monthA, $day); $rangeB = analysisPeriodRange_($db, $monthB, $day);
    } else {
        $weekA = max(1, min($maxWeek, analysisNumOr_($req['weekA'] ?? null, max(1, $maxWeek - 1)))); $weekB = max(1, min($maxWeek, analysisNumOr_($req['weekB'] ?? null, $maxWeek)));
        if ($weekA == $weekB && $maxWeek > 1) $weekA = $weekB == 1 ? 2 : $weekB - 1;
        $rangeA = analysisRange_($period, $weekA); $rangeB = analysisRange_($period, $weekB);
        $cap = $period . '-' . js_pad2(js_str($cut)); if (strcmp($rangeA['to'], $cap) > 0) $rangeA['to'] = $cap; if (strcmp($rangeB['to'], $cap) > 0) $rangeB['to'] = $cap;
    }
    $types = analysisTypes_($db, $ids, $period, $brand); $shown = $type === 'ALL' ? $types : js_filter($types, fn($t) => $t === $type);
    $make = function ($key, $label, $isTotal) use ($db, $ids, $rangeA, $rangeB, $metric, $brand) {
        $selectedType = $isTotal ? 'ALL' : $key; $a = analysisMetric_($db, $ids, $rangeA, $metric, $brand, $selectedType); $b = analysisMetric_($db, $ids, $rangeB, $metric, $brand, $selectedType); $delta = $b - $a;
        return ['key' => $key, 'label' => $label, 'total' => (bool) $isTotal, 'a' => $a, 'b' => $b, 'delta' => $delta, 'growth' => truthy($a) ? $delta / $a * 100 : (truthy($b) ? null : 0)];
    };
    $total = $make('TOTAL', 'TOTAL ' . $brand, true); $groups = [$total]; foreach ($shown as $t) $groups[] = $make($t, $t, false);
    $rest = array_slice($groups, 1);
    $ranked = js_sort($rest, fn($a, $b) => ($b['delta'] - $a['delta']) ?: ($b['b'] - $a['b']));
    $contributors = js_sort($rest, fn($a, $b) => $b['b'] - $a['b']);
    $people = $source['meta']['people'];
    $out = ['groups' => $groups, 'types' => $types, 'scopeCount' => count(js_filter($ids, fn($id) => truthy(PortalCore::person($people, $id)['sales'] ?? null))), 'best' => $ranked[0] ?? null, 'worst' => $ranked ? js_last($ranked) : null, 'topContributor' => $contributors[0] ?? null, 'total' => $total, 'metric' => $metric, 'mode' => $mode, 'brand' => $brand, 'type' => $type, 'periodA' => $rangeA['label'], 'periodB' => $rangeB['label'], 'rangeA' => $rangeA, 'rangeB' => $rangeB, 'periods' => $periods, 'weekOptions' => $weekOptions];
    if (isset($db['coverage'][$period]) && array_key_exists('cutoff', $db['coverage'][$period])) $out['cutoff'] = $db['coverage'][$period]['cutoff'];
    return $out;
}

function apiTeamComparison($token, $period = null, $selection = null, $request = null) {
    $source = snapshot_(); $u = guard_($token, false, false, $source); $people = $source['meta']['people']; $me = PortalCore::person($people, $u['nik']);
    if ($u['role'] === 'SALES' && truthy($me['atasan'] ?? null)) { $base = []; foreach ($people as $p) if (truthy($p['sales'] ?? null) && ($p['atasan'] ?? null) === $me['atasan']) $base[] = $p['nik']; }
    else $base = allowed_($source, $u, $selection);
    $req = is_array($request) ? $request : [];
    $filter = js_or($req['filter'] ?? null, []); if (!is_array($filter)) $filter = [];
    $chosen = js_or($filter['sales'] ?? null, js_or($filter['asm'] ?? null, js_or($filter['rgm'] ?? null, '')));
    $chosenScope = js_set(truthy($chosen) ? PortalCore::scope($people, $chosen) : $base);
    $fb = $filter['big'] ?? null; $fr = $filter['region'] ?? null; $fs = $filter['sub'] ?? null;
    $ids = js_filter($base, function ($id) use ($chosenScope, $fb, $fr, $fs, $people) {
        $p = PortalCore::person($people, $id); $reg = js_or($p['portfolio'] ?? null, $p['region'] ?? null);
        return isset($chosenScope[(string) $id]) && ($fb === 'ALL' || !truthy($fb) || ($p['wilayah'] ?? null) === $fb) && ($fr === 'ALL' || !truthy($fr) || $reg === $fr) && ($fs === 'ALL' || !truthy($fs) || $reg === $fs);
    });
    $period = js_str($period);
    $db = coverageView_($source, $period, $ids, 'ST', $u);
    $level = js_str(js_or($req['level'] ?? null, 'SALES')); $metric = js_str(js_or($req['metric'] ?? null, 'ST')); $brand = js_str(js_or($req['brand'] ?? null, 'ALL')); $mode = js_str(js_or($req['mode'] ?? null, 'WOW'));
    if ($mode === 'MOM') {
        $pp = PortalCore::prev($period); $day = analysisNum_(substr(js_or($db['coverage'][$period]['cutoff'] ?? null, PortalCore::end($period)), 8));
        $aRange = ['from' => $pp . '-01', 'to' => $pp . '-' . js_pad2(js_str(min($day, (int) substr(PortalCore::end($pp), 8)))), 'label' => $pp];
        $bRange = ['from' => $period . '-01', 'to' => $period . '-' . js_pad2(js_str($day)), 'label' => $period];
    } else {
        $aRange = analysisRange_($period, js_or($req['weekA'] ?? null, 1)); $bRange = analysisRange_($period, js_or($req['weekB'] ?? null, 2));
        if ($aRange['label'] === $bRange['label']) fail_('Minggu pembanding harus berbeda.');
    }
    $allowedLevels = ['SALES', 'ASM', 'RGM', 'BIG REGION', 'REGION'];
    if (!in_array($level, $allowedLevels, true) || ($u['role'] === 'SALES' && $level !== 'SALES')) fail_('Tingkat analisa tidak diizinkan.');
    $idSet = js_set($ids); $groups = [];
    if ($level === 'BIG REGION' || $level === 'REGION') {
        $sales = js_filter($people, fn($p) => isset($idSet[(string) $p['nik']]) && truthy($p['sales'] ?? null)); $bucket = [];
        foreach ($sales as $p) { $name = $level === 'BIG REGION' ? js_or($p['wilayah'] ?? null, 'LAINNYA') : js_or($p['portfolio'] ?? null, js_or($p['region'] ?? null, 'LAINNYA')); $bucket[(string) $name][] = $p['nik']; }
        foreach (js_keys($bucket) as $name) {
            if ($level === 'BIG REGION') $region = 'Nasional';
            else { $f = js_find($sales, fn($p) => js_or($p['portfolio'] ?? null, js_or($p['region'] ?? null, 'LAINNYA')) === $name); $region = js_or($f['wilayah'] ?? null, ''); }
            $groups[] = ['nik' => '', 'nama' => $name, 'region' => $region, 'team' => $bucket[$name]];
        }
    } else {
        foreach ($people as $p) if (isset($idSet[(string) $p['nik']]) && ($level === 'SALES' ? truthy($p['sales'] ?? null) : ($p['role'] ?? null) === $level))
            $groups[] = ['nik' => $p['nik'], 'nama' => $p['nama'], 'region' => js_or($p['portfolio'] ?? null, js_or($p['region'] ?? null, js_or($p['wilayah'] ?? null, ''))), 'team' => $level === 'SALES' ? [$p['nik']] : js_filter(PortalCore::scope($people, $p['nik']), fn($id) => isset($idSet[(string) $id]))];
    }
    $rows = array_map(function ($p) use ($db, $aRange, $bRange, $metric, $brand) {
        $a = analysisMetric_($db, $p['team'], $aRange, $metric, $brand, 'ALL'); $b = analysisMetric_($db, $p['team'], $bRange, $metric, $brand, 'ALL'); $delta = $b - $a;
        return ['nik' => $p['nik'], 'nama' => $p['nama'], 'region' => $p['region'], 'a' => $a, 'b' => $b, 'delta' => $delta, 'growth' => truthy($a) ? $delta / $a * 100 : (truthy($b) ? null : 0)];
    }, $groups);
    $rows = js_sort($rows, fn($x, $y) => ($y['delta'] - $x['delta']) ?: lc_id($x['nama'], $y['nama']));
    return ['rows' => $rows, 'periodA' => $aRange['label'], 'periodB' => $bRange['label'], 'best' => $rows[0] ?? null, 'worst' => $rows ? js_last($rows) : null, 'metric' => $metric, 'mode' => $mode, 'level' => $level];
}
