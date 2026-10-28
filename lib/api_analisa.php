<?php
/* Analisa v6 — Early Warning (menu Tim) + menu Analisa (Cross-sell, Reaktivasi, Dealer Baru).
   Terjemahan 1:1 Analisa.gs. Hanya MEMBACA data publikasi. */
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/api_tutup.php';

function anaPrev_(string $p, $n = null): string {
    $n = truthy($n) ? (int) $n : 1;
    $t = (int) substr($p, 0, 4) * 12 + ((int) substr($p, 5, 2) - 1) - $n;
    $y = intdiv($t, 12); $m = $t % 12; if ($m < 0) { $m += 12; $y--; }
    return sprintf('%04d-%02d', $y, $m + 1);
}
function anaCtx_($token, $period, $selection): array {
    $period = js_str($period);
    $source = snapshot_(); $u = guard_($token, false, false, $source); $ids = allowed_($source, $u, $selection);
    $db = coverageView_($source, $period, $ids, 'ST', $u);
    $cov = $db['coverage'][$period] ?? null; if (!$cov) fail_('Periode belum tersedia.');
    $people = []; foreach ($db['meta']['people'] as $p) $people[(string) $p['nik']] = $p;
    return ['source' => $source, 'u' => $u, 'ids' => $ids, 'db' => $db, 'cutoff' => $cov['cutoff'], 'closed' => truthy($cov['closed'] ?? false), 'people' => $people];
}
/** people[nik] ala JS (undefined -> null). */
function anaPerson_(array $people, $nik): ?array { return $nik === null ? null : ($people[js_str($nik)] ?? null); }
function anaDeviceBrands_(array $meta): array {
    $s = []; foreach (js_keys($meta['products']) as $k) { $p = $meta['products'][$k]; if (truthy($p['device'] ?? false)) $s[js_str($p['brand'] ?? null)] = true; }
    return js_sort_default(js_keys($s));
}
function anaMonths_(array $db, string $period, int $count): array {
    $out = []; for ($i = $count - 1; $i >= 0; $i--) { $p = $i ? anaPrev_($period, $i) : $period; if (isset($db['cubes'][$p]) || $p === $period) $out[] = $p; }
    return $out;
}

/* ---------- EARLY WARNING (menu Tim, ikut filter Tim) ---------- */
function apiEarlyWarning($token, $period, $level = null, $brand = null, $metric = null, $selection = null, $filters = null) {
    $c = anaCtx_($token, $period, $selection); $period = js_str($period); $db = $c['db']; $u = $c['u']; $meta = $db['meta'];
    $level = js_str(js_or($level, 'SALES')); $brand = js_str(js_or($brand, 'ALL')); $metric = js_str(js_or($metric, 'QTY')); $filters = truthy($filters) && is_array($filters) ? $filters : [];
    $area = $level === 'BIG REGION' || $level === 'REGION';
    if (!in_array($level, ['SALES', 'ASM', 'RGM', 'BIG REGION', 'REGION'], true) || !in_array($metric, ['QTY', 'DA', 'NOO'], true)) fail_('Pilihan tidak valid.');
    if ($u['role'] === 'SALES' && $level !== 'SALES') fail_('Tingkat tidak diizinkan.');
    if (!$area && $u['role'] !== 'SALES' && isset(PortalCore::LEVEL[$u['role']]) && PortalCore::LEVEL[$level] >= PortalCore::LEVEL[$u['role']]) fail_('Tingkat tidak diizinkan.');
    $big = js_str(js_or($filters['big'] ?? null, 'ALL')); $region = js_str(js_or($filters['region'] ?? null, 'ALL')); $sub = js_str(js_or($filters['sub'] ?? null, 'ALL'));
    $idSet = js_set($c['ids']);
    $salesP = js_filter($meta['people'], function ($p) use ($idSet, $big, $region, $sub) {
        if (!in_set($idSet, $p['nik']) || !truthy($p['sales'] ?? false)) return false;
        $r = js_or($p['portfolio'] ?? null, $p['region'] ?? null);
        return ($big === 'ALL' || ($p['wilayah'] ?? null) === $big) && ($region === 'ALL' || $r === $region) && ($sub === 'ALL' || $r === $sub);
    });
    $salesIds = array_map(fn($p) => $p['nik'], $salesP); $salesSet = js_set($salesIds);
    $groups = [];
    if ($area) {
        $buckets = [];
        foreach ($salesP as $p) { $name = $level === 'BIG REGION' ? js_or($p['wilayah'] ?? null, 'LAINNYA') : js_or($p['portfolio'] ?? null, js_or($p['region'] ?? null, 'LAINNYA')); $buckets[(string) $name][] = $p; }
        foreach (js_sort_default(js_keys($buckets)) as $name) {
            $f = $buckets[$name][0];
            $groups[] = ['nik' => 'AREA:' . $name, 'nama' => $name, 'role' => $level, 'region' => $level === 'BIG REGION' ? 'Nasional' : js_or($f['wilayah'] ?? null, ''), 'big' => '', 'boss' => $level === 'BIG REGION' ? 'Nasional' : js_or($f['wilayah'] ?? null, '—'), 'bossRole' => $level === 'BIG REGION' ? 'Cakupan' : 'Big Region', 'team' => array_map(fn($p) => $p['nik'], $buckets[$name])];
        }
    } else foreach ($meta['people'] as $p) {
        if ($level === 'SALES' ? !in_set($salesSet, $p['nik']) : $p['role'] !== $level) continue;
        $team = $level === 'SALES' ? [$p['nik']] : js_filter(PortalCore::scope($meta['people'], $p['nik']), fn($id) => in_set($salesSet, $id));
        if (!$team) continue;
        $boss = anaPerson_($c['people'], $p['atasan'] ?? null);
        $groups[] = ['nik' => $p['nik'], 'nama' => $p['nama'], 'role' => $p['role'], 'region' => js_or($p['portfolio'] ?? null, js_or($p['region'] ?? null, '')), 'big' => js_or($p['wilayah'] ?? null, ''), 'boss' => $boss ? $boss['nama'] : '—', 'bossRole' => $boss ? $boss['role'] : '', 'team' => $team];
    }
    $brands = anaDeviceBrands_($meta); $wk = anaWork_($period, $c['cutoff'], $c['closed']); $days = $wk['days']; $run = $wk['run'];
    $last7 = array_slice($wk['runDates'], -7);
    $rows = array_map(function ($g) use ($db, $period, $brand, $metric, $meta, $brands, $last7) {
        $slice = PortalCore::slice($db, $period, $g['team']); $m = PortalCore::metrics($slice, $db, $period, $brand);
        $actual = $metric === 'QTY' ? $m['qty'] : ($metric === 'DA' ? $m['da'] : $m['noo']);
        $t = PortalCore::target($meta, $period, $g['team'], $brand, $metric);
        if ($brand === 'ALL' && !$t['exists']) {
            $parts = array_map(fn($b) => PortalCore::target($meta, $period, $g['team'], $b, $metric), $brands);
            $t = ['value' => js_sum($parts, fn($x) => $x['value']), 'exists' => js_some($parts, fn($x) => $x['exists']), 'missing' => []];
            foreach ($parts as $x) foreach ($x['missing'] ?? [] as $id) if (!in_array($id, $t['missing'], true)) $t['missing'][] = $id;
        }
        $daily = array_map(function ($dt) use ($slice, $brand) { $n = 0; foreach ($slice as $r) if ($r['date'] === $dt && ($brand === 'ALL' || $r['brand'] === $brand)) $n += $r['q']; return $n; }, $last7);
        return ['nik' => $g['nik'], 'nama' => $g['nama'], 'role' => $g['role'], 'region' => $g['region'], 'big' => $g['big'], 'boss' => $g['boss'], 'bossRole' => $g['bossRole'], 'teamSize' => count($g['team']),
            'actual' => $actual, 'target' => js_or($t['value'], 0), 'targetComplete' => truthy($t['exists']) && !($t['missing'] ?? []), 'daily7' => $daily, 'qtyMTD' => $m['qty']];
    }, $groups);
    return ['period' => $period, 'cutoff' => $c['cutoff'], 'closed' => $c['closed'], 'days' => $days, 'run' => $run, 'level' => $level, 'brand' => $brand, 'metric' => $metric, 'brands' => $brands, 'last7' => $last7, 'rows' => $rows];
}

/* ---------- MENU ANALISA ---------- */
function apiAnalisa($token, $period, $selection = null) {
    $c = anaCtx_($token, $period, $selection); $period = js_str($period); $db = $c['db']; $meta = $db['meta']; $ids = $c['ids']; $idSet = js_set($ids);
    $brands = anaDeviceBrands_($meta); $months6 = anaMonths_($db, $period, 6); $months3 = array_slice($months6, -3); $prevP = anaPrev_($period, 1); $p2 = anaPrev_($period, 2);
    $perMonth = []; $lastDate = []; $curRows = [];
    foreach ($months6 as $p) {
        foreach ($p === $period ? PortalCore::slice($db, $p, $ids, $c['cutoff']) : PortalCore::sliceCmp($db, $p, $ids, null, $period) as $r) {
            if ($p === $period) $curRows[] = $r;
            if (!truthy($r['q'])) continue;
            $cu = (string) $r['customer'];
            $perMonth[$cu][$p][$r['brand']] = ($perMonth[$cu][$p][$r['brand']] ?? 0) + $r['q'];
            if ($r['q'] > 0 && (!truthy($lastDate[$cu] ?? null) || strcmp($r['date'], $lastDate[$cu]) > 0)) $lastDate[$cu] = $r['date'];
        }
    }
    $cust = fn($id) => $meta['customers'][$id] ?? ['nama' => $id, 'kota' => '', 'hp' => '', 'nik' => ''];
    $ownerNik = function ($id) use ($cust, $db) { $n = $cust($id)['nik'] ?? null; if (truthy($n)) return $n; $o = !empty($db['stock']) && isset($db['stock']['owners']) && is_array($db['stock']['owners']) ? ($db['stock']['owners'][$id] ?? null) : ''; return js_or($o, ''); };
    $ownerName = function ($id) use ($c, $ownerNik) { $p = anaPerson_($c['people'], $ownerNik($id)); return $p ? $p['nama'] : '—'; };
    $qtyIn = function ($id, $p, $b = null) use (&$perMonth) { $m = $perMonth[(string) $id][$p] ?? []; if (truthy($b)) return $m[$b] ?? 0; $n = 0; foreach ($m as $v) $n += max(0, $v); return $n; };
    $brandsIn = function ($id, $p) use (&$perMonth) { $m = $perMonth[(string) $id][$p] ?? []; $n = 0; foreach ($m as $v) if ($v > 0) $n++; return $n; };
    $cutDays = iso_days($c['cutoff']);
    $ageDays = fn($d) => truthy($d) ? js_mround($cutDays - iso_days($d)) : null;

    /* 1) Cross-sell */
    $tutup = dtClosed_(); $isClosed = fn($id) => truthy($tutup[(string) $id] ?? null) && strcmp($tutup[(string) $id], $c['cutoff']) <= 0;
    $active = js_filter(js_keys($perMonth), fn($id) => !$isClosed($id) && ($qtyIn($id, $period) > 0 || $qtyIn($id, $prevP) > 0));
    $div = count($months3) ?: 1;
    $avgBrand = function ($id, $b) use ($months3, $qtyIn, $div) { $n = 0; foreach ($months3 as $p) $n += max(0, $qtyIn($id, $p, $b)); return $n / $div; };
    $area = anaAreaOf_($meta, $c['people']);
    $peers = anaPeers_($active, $brands, $avgBrand, $area);
    $dealers = array_map(function ($id) use ($months3, $qtyIn, $div, $brands, $avgBrand, $peers, $area, $cust, $ownerNik, $ownerName) {
        $total = 0; foreach ($months3 as $p) $total += $qtyIn($id, $p); $total = $total / $div; $cells = []; $taken = 0;
        foreach ($brands as $b) {
            $v = $avgBrand($id, $b);
            if ($v > 0) { $cells[$b] = ['has' => 1, 'avg' => js_mround($v * 10) / 10]; $taken++; }
            else {
                $pk = anaPeerPick_($peers, $area($id), $b);
                $cells[$b] = ['has' => 0, 'est' => $pk ? max(1, js_mround(min(anaMedian_($pk['list']), max(1, $total * 0.5)))) : 0, 'n' => $pk ? count($pk['list']) : 0, 'basis' => $pk ? $pk['label'] : ''];
            }
        }
        $cu = $cust($id);
        return ['id' => $id, 'nama' => $cu['nama'] ?? null, 'kota' => js_or($cu['kota'] ?? null, ''), 'hp' => js_or($cu['hp'] ?? null, ''), 'nik' => $ownerNik($id), 'sales' => $ownerName($id), 'avg' => js_mround($total * 10) / 10, 'taken' => $taken, 'cells' => js_obj($cells)];
    }, $active);
    $dealers = js_sort($dealers, fn($a, $b) => $b['avg'] - $a['avg']);
    $crossSell = ['months' => $months3, 'brands' => $brands, 'dealers' => array_slice($dealers, 0, 3000)];

    /* 2) Reaktivasi */
    $inScope = [];
    foreach (js_keys($meta['customers']) as $id) if (in_set($idSet, $meta['customers'][$id]['nik'] ?? null)) $inScope[$id] = true;
    foreach (js_keys($perMonth) as $id) $inScope[$id] = true;
    $weights = ['DA BULAN LALU' => 1, 'IDLE' => 0.7, 'PASIF' => 0.4]; $re = []; $back = [];
    foreach (js_keys($inScope) as $id) {
        $series = array_map(fn($p) => $qtyIn($id, $p), $months6); $cur = $qtyIn($id, $period); $prev = $qtyIn($id, $prevP); $prev2 = $qtyIn($id, $p2);
        $earlier = js_some($months6, fn($p) => $p !== $period && $qtyIn($id, $p) > 0);
        if ($cur > 0 && $prev == 0 && $earlier) $back[] = $ownerNik($id);
        if ($cur > 0 || $isClosed($id)) continue;
        $status = $prev > 0 ? 'DA BULAN LALU' : ($prev2 > 0 ? 'IDLE' : (($earlier || truthy($lastDate[$id] ?? null)) ? 'PASIF' : null));
        if (!$status) continue;
        $hist = js_filter(array_slice($series, 0, -1), fn($v) => $v > 0); $avg = $hist ? array_sum($hist) / count($hist) : 0;
        $cu = $cust($id);
        $re[] = ['id' => $id, 'nama' => $cu['nama'] ?? null, 'kota' => js_or($cu['kota'] ?? null, ''), 'hp' => js_or($cu['hp'] ?? null, ''), 'nik' => $ownerNik($id), 'sales' => $ownerName($id), 'status' => $status, 'avg' => js_mround($avg * 10) / 10, 'series' => $series, 'last' => js_or($lastDate[$id] ?? null, null), 'jeda' => $ageDays($lastDate[$id] ?? null), 'score' => js_mround($avg * $weights[$status] * 10) / 10];
    }
    $re = js_sort($re, fn($a, $b) => ($b['score'] - $a['score']) ?: ($b['avg'] - $a['avg']));
    $reactivation = ['months' => $months6, 'reactivated' => $back, 'rows' => array_slice($re, 0, 4000)];

    /* 3) Dealer baru (NOO) */
    $laterCur = [];
    foreach ($curRows as $r) if ($r['q'] > 0) $laterCur[jkey([$r['customer'], $r['brand']])][] = $r['date'];
    $pairs = [];
    $first = $db['first'] ?? [];
    foreach (js_keys($first) as $k) {
        $f = $first[$k]; if (!truthy($f) || !in_set($idSet, $f['nik'] ?? null)) continue;
        $m = substr($f['date'], 0, 7); $idx = array_search($m, $months6, true); if ($idx === false) continue;
        if ($m === $period && strcmp($f['date'], $c['cutoff']) > 0) continue;
        $pk = json_decode($k, true); $pres = [];
        for ($j = $idx; $j < count($months6); $j++) $pres[] = $qtyIn($pk[0], $months6[$j], $pk[1]) > 0 ? 1 : 0;
        $cu = $cust($pk[0]);
        $mm = $perMonth[(string) $pk[0]][$period] ?? []; $bl = []; foreach ($mm as $x => $v) if ($v > 0) $bl[] = (string) $x;
        $pairs[] = ['c' => $pk[0], 'n' => $cu['nama'] ?? null, 'k' => js_or($cu['kota'] ?? null, ''), 'hp' => js_or($cu['hp'] ?? null, ''), 'b' => $pk[1], 'nik' => $f['nik'], 'm' => $m, 'd' => $f['date'], 'pres' => $pres, 'qM' => $qtyIn($pk[0], $m, $pk[1]), 'qCur' => $qtyIn($pk[0], $period, $pk[1]), 'multi' => $brandsIn($pk[0], $period), 'bl' => js_sort_default($bl), 'last' => js_or($lastDate[(string) $pk[0]] ?? null, null),
            'again' => $m === $period ? js_some($laterCur[$k] ?? [], fn($dt) => strcmp($dt, $f['date']) > 0) : null];
    }
    $noo = ['prev' => $prevP, 'current' => $period, 'months' => $months6, 'pairs' => $pairs];

    return ['period' => $period, 'cutoff' => $c['cutoff'], 'closed' => $c['closed'], 'crossSell' => $crossSell, 'reactivation' => $reactivation, 'noo' => $noo];
}

/* ---------- v6.19: hari kerja, Hari_Libur, pembanding Sub Region, potensi dealer ---------- */
function anaHol_(): array {
    if (isset($GLOBALS['DSM_HOL'])) return $GLOBALS['DSM_HOL'];
    $out = [];
    try { foreach (optionalTable_('Hari_Libur') as $r) { $d = PortalCore::iso($r['TANGGAL'] ?? null); if (truthy($d) && js_upper(array_key_exists('AKTIF', $r) ? js_str($r['AKTIF']) : 'undefined') !== 'FALSE') $out[] = $d; } } catch (Throwable $e) {}
    $out = js_sort_default(uniq($out));
    return $GLOBALS['DSM_HOL'] = $out;
}
function anaOff_(string $d, array $hol): bool { return gmdate('w', iso_days($d) * 86400) === '0' || in_array($d, $hol, true); }
function anaWork_(string $period, $cutoff, $closed): array {
    $hol = anaHol_(); $last = (int) substr(PortalCore::end($period), 8); $all = []; $run = [];
    for ($i = 1; $i <= $last; $i++) { $d = $period . '-' . js_pad2($i); if (anaOff_($d, $hol)) continue; $all[] = $d; if (truthy($closed) || strcmp($d, (string) $cutoff) <= 0) $run[] = $d; }
    return ['days' => count($all), 'run' => count($run), 'runDates' => $run];
}
function apiHariLibur($token) { guard_($token, false, false, snapshot_()); return anaHol_(); }
function anaMedian_($a) {
    if (!$a) return 0; $s = $a; sort($s, SORT_NUMERIC); $m = intdiv(count($s), 2);
    return count($s) % 2 ? $s[$m] : ($s[$m - 1] + $s[$m]) / 2;
}
function anaAreaOf_(array $meta, array $people): Closure {
    $subBig = [];
    foreach ($meta['people'] as $p) { $s = js_or($p['portfolio'] ?? null, $p['region'] ?? null); if (truthy($s) && truthy($p['wilayah'] ?? null) && !truthy($subBig[(string) $s] ?? null)) $subBig[(string) $s] = $p['wilayah']; }
    return function ($id) use ($meta, $people, $subBig) {
        $cu = $meta['customers'][$id] ?? []; $o = anaPerson_($people, $cu['nik'] ?? null) ?? [];
        $sub = js_or($cu['region'] ?? null, js_or($o['portfolio'] ?? null, js_or($o['region'] ?? null, ''))); $big = js_or($subBig[(string) $sub] ?? null, js_or($o['wilayah'] ?? null, ''));
        return ['sub' => $sub, 'big' => $big];
    };
}
function anaPeers_(array $ids, array $brands, callable $avgFn, callable $area): array {
    $peers = [];
    foreach ($ids as $id) { $a = $area($id); foreach ($brands as $b) { $v = $avgFn($id, $b); if ($v > 0) foreach (['S|' . js_str($a['sub']), 'B|' . js_str($a['big']), 'N|'] as $k) $peers[$k . '|' . $b][] = $v; } }
    return $peers;
}
function anaPeerPick_(array $peers, array $a, $b): ?array {
    $tries = [['S|' . js_str($a['sub']), 'Sub Region ' . js_str($a['sub'])], ['B|' . js_str($a['big']), 'Region ' . js_str($a['big'])], ['N|', 'Nasional']];
    foreach ($tries as $i => $t) { $l = $peers[$t[0] . '|' . $b] ?? null; if ($l && (count($l) >= 5 || $i === 2)) return ['list' => $l, 'label' => $t[1]]; }
    return null;
}
/* Potensi per dealer-brand. */
function apiPotensi($token, $period, $selection = null) {
    $c = anaCtx_($token, $period, $selection); $period = js_str($period); $db = $c['db']; $meta = $db['meta']; $brands = anaDeviceBrands_($meta);
    $months = js_filter([anaPrev_($period, 3), anaPrev_($period, 2), anaPrev_($period, 1)], fn($p) => isset($db['cubes'][$p]));
    $allIds = array_map(fn($p) => $p['nik'], $meta['people']); $per = [];
    foreach ($months as $mi => $p) foreach (PortalCore::slice($db, $p, $allIds, null) as $r) { if (!truthy($r['q'])) continue; $cu = (string) $r['customer']; if (!isset($per[$cu][$r['brand']])) $per[$cu][$r['brand']] = [0, 0, 0]; $per[$cu][$r['brand']][$mi] += $r['q']; }
    $div = count($months) ?: 1;
    $avg = function ($id, $b) use (&$per, $div) { $x = $per[(string) $id][$b] ?? null; return $x ? max(0, $x[0] + $x[1] + $x[2]) / $div : 0; };
    $tutup = dtClosed_();
    $area = anaAreaOf_($meta, $c['people']); $peers = anaPeers_(js_filter(js_keys($per), function ($id) use ($tutup, $c) { $t = $tutup[$id] ?? null; return !(truthy($t) && strcmp($t, $c['cutoff']) <= 0); }), $brands, $avg, $area);
    $mine = []; foreach (PortalCore::slice($db, $period, $c['ids'], $c['cutoff']) as $r) $mine[(string) $r['customer']] = 1;
    foreach ($months as $p) foreach (PortalCore::sliceCmp($db, $p, $c['ids'], null, $period) as $r) $mine[(string) $r['customer']] = 1;
    $map = []; $r1 = fn($v) => js_mround($v * 10) / 10;
    foreach (js_keys($mine) as $id) {
        if (truthy($tutup[$id] ?? null) && strcmp($tutup[$id], $c['cutoff']) <= 0) continue;
        $total = 0; foreach ($brands as $b) $total += $avg($id, $b); if (!($total > 0)) continue;
        $cell = [];
        foreach ($brands as $b) {
            $v = $avg($id, $b);
            if ($v > 0) { $x = $per[$id][$b]; $cell[$b] = ['v' => $r1($v), 'k' => 'h', 'm' => array_map(fn($q) => max(0, $q), array_slice($x, 0, count($months)))]; }
            else { $pk = anaPeerPick_($peers, $area($id), $b); if ($pk) $cell[$b] = ['v' => max(1, js_mround(min(anaMedian_($pk['list']), max(1, $total * 0.5)))), 'k' => 'p', 'n' => count($pk['list']), 'l' => $pk['label']]; }
        }
        $map[$id] = js_obj($cell);
    }
    $wk = anaWork_($period, $c['cutoff'], $c['closed']);
    return ['period' => $period, 'months' => $months, 'days' => $wk['days'], 'run' => $wk['run'], 'map' => js_obj($map)];
}

/* ---------- v6.21: Hari_Libur (Super Admin) — sisi baca ---------- */
function hlSheet_(): string { ensureTable_('Hari_Libur', ['TANGGAL', 'KETERANGAN', 'AKTIF']); return 'Hari_Libur'; }
function hlGuard_($token): array { $u = guard_($token, false, false, snapshot_()); if ($u['role'] !== 'SUPER ADMIN') fail_('Hanya Super Admin yang bisa mengubah hari libur.'); return $u; }
function hlRows_(): array {
    $v = readGrid_('Hari_Libur'); if ($v === null) return [];
    array_shift($v); $out = [];
    foreach (array_values($v) as $i => $r) { $d = PortalCore::iso($r[0] ?? ''); $out[] = ['row' => $i + 2, 'date' => $d, 'ket' => js_str(js_or($r[1] ?? null, '')), 'aktif' => js_upper(js_str($r[2] ?? 'undefined')) !== 'FALSE']; }
    return js_sort(js_filter($out, fn($x) => truthy($x['date'])), fn($a, $b) => lc($a['date'], $b['date']));
}
function apiHariLiburAdmin($token) { hlGuard_($token); return ['rows' => hlRows_()]; }

/* ---------- v6.21: Pertumbuhan per TYPE per Sales ---------- */
function gtModelMap_(): array {
    static $memo = null;
    if ($memo !== null) return $memo;
    $out = [];
    try { foreach (optionalTable_('Master_Product') as $r) { $t = text_($r['TYPE'] ?? null); $m = text_($r['MODEL'] ?? null); if ($t !== '' && $m !== '' && !truthy($out[$t] ?? null)) $out[$t] = $m; } } catch (Throwable $e) {}
    return $memo = $out;
}
function gtModel_($type, $brand, array $map) {
    if (truthy($map[js_str($type)] ?? null)) return $map[js_str($type)];
    $W = '[' . JS_WS . ']';
    $t = js_trim(preg_replace('/' . $W . '+-' . $W . '+.*$/u', '', js_upper(js_str(js_or($type, ''))))); $b = js_upper(js_str(js_or($brand, '')));
    if ($b !== '' && str_starts_with($t, $b . ' ')) $t = substr($t, strlen($b) + 1);
    $out = [];
    foreach (preg_split('/' . $W . '+/u', $t) as $w) { if (preg_match('/^\d+(GB|TB|G)$/', $w) || preg_match('/^\d+\/\d+$/', $w) || preg_match('/^\d+\+\d+/', $w)) break; $out[] = $w; }
    return js_trim(js_or(implode(' ', $out), $t));
}
/* ---------- v6.22: mesin Perbandingan ---------- */
function anaNum_($v) {
    if ($v === null || $v === false) return 0; if ($v === true) return 1;
    if (is_int($v) || is_float($v)) return $v;
    $s = js_trim(js_str($v)); if ($s === '') return 0;
    return is_numeric($s) ? $s + 0 : NAN;
}
function cmpRanges_(string $period, string $cutoff, $closed, $cmp, $weekA = null, $weekB = null): array {
    $prev = anaPrev_($period, 1); $prevEnd = PortalCore::end($prev); $end = PortalCore::end($period);
    $mon = function ($p) { $n = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']; return $n[(int) substr($p, 5, 2) - 1] . ' ' . substr($p, 2, 2); };
    if ($cmp === 'WTD') {
        $dow = ((int) gmdate('w', iso_days($cutoff) * 86400) + 6) % 7; $m0 = StockCore::shift($cutoff, -$dow);
        return ['A' => ['from' => $m0, 'to' => $cutoff, 'label' => 'Minggu ini'], 'B' => ['from' => StockCore::shift($m0, -7), 'to' => StockCore::shift($cutoff, -7), 'label' => 'Minggu lalu']];
    }
    if ($cmp === 'WK') {
        $wk = function ($w) use ($period, $end, $closed, $cutoff) {
            $n = anaNum_($w); $w = max(1, min(5, (is_float($n) && is_nan($n)) || $n == 0 ? 1 : $n));
            if (is_float($w) && floor($w) == $w) $w = (int) $w;
            $f = $period . '-' . substr('0' . js_str(($w - 1) * 7 + 1), -2);
            $t = $w === 5 ? $end : $period . '-' . substr('0' . js_str(min($w * 7, (int) substr($end, 8))), -2);
            if (strcmp($f, $end) > 0) $f = $end; $run = !truthy($closed) && strcmp($t, $cutoff) > 0;
            return ['from' => $f, 'to' => $run ? $cutoff : $t, 'label' => 'W' . js_str($w), 'running' => $run];
        };
        return ['A' => $wk(js_or($weekA, 2)), 'B' => $wk(js_or($weekB, 1))];
    }
    if ($cmp === 'MOM') {
        $pA = truthy($closed) ? $period : $prev; $pB = anaPrev_($pA, 1);
        return ['A' => ['from' => $pA . '-01', 'to' => PortalCore::end($pA), 'label' => $mon($pA)], 'B' => ['from' => $pB . '-01', 'to' => PortalCore::end($pB), 'label' => $mon($pB)]];
    }
    $bTo = truthy($closed) ? $prevEnd : (strcmp($prev . '-' . substr($cutoff, 8), $prevEnd) <= 0 ? $prev . '-' . substr($cutoff, 8) : $prevEnd);
    return ['A' => ['from' => $period . '-01', 'to' => $cutoff, 'label' => 'MTD'], 'B' => ['from' => $prev . '-01', 'to' => $bTo, 'label' => 'LMTD']];
}
function apiCompare($token, $period, $selection = null, $opts = null) {
    $opts = is_array($opts) ? $opts : [];
    $o = fn($k) => $opts[$k] ?? null;
    $view = $o('view') === 'TYPE' ? 'TYPE' : 'BRAND'; $cmp = in_array($o('cmp'), ['MTD', 'WTD', 'WK', 'MOM'], true) ? $o('cmp') : 'MTD';
    $metric = in_array($o('metric'), ['ST', 'SO', 'OMZET', 'DA', 'NOO'], true) ? $o('metric') : 'ST'; $level = in_array($o('level'), ['SALES', 'ASM', 'RGM'], true) ? $o('level') : 'SALES';
    $brand = text_(js_or($o('brand'), 'ALL')); $c = anaCtx_($token, $period, $selection); $period = js_str($period); $meta = $c['db']['meta'];
    if ($c['u']['role'] === 'SALES') $level = 'SALES';
    $R = cmpRanges_($period, $c['cutoff'], $c['closed'], $cmp, $o('weekA'), $o('weekB'));
    $side = fn($d) => (strcmp($d, $R['A']['from']) >= 0 && strcmp($d, $R['A']['to']) <= 0) ? 0 : ((strcmp($d, $R['B']['from']) >= 0 && strcmp($d, $R['B']['to']) <= 0) ? 1 : -1);
    $anc = function ($nik, $role) use ($c) { $p = anaPerson_($c['people'], $nik); $g = 0; while ($p && $g++ < 10) { if ($p['role'] === $role) return $p; $p = anaPerson_($c['people'], $p['atasan'] ?? null); } return null; };
    $rowKey = function ($nik) use ($level, $anc) { if ($level === 'SALES') return $nik; $a = $anc($nik, $level); return $a ? $a['nik'] : '~' . $level; };
    $colKey = function ($b, $t) use ($view, $brand) { if ($view === 'TYPE') return $b === $brand ? js_or($t, '(tanpa TYPE)') : null; return $brand === 'ALL' || $b === $brand ? $b : null; };
    $acc = []; $pairs = []; $cols = []; $bigCnt = [];
    $noteBig = function ($rk, $b) use (&$bigCnt) { if (!truthy($b)) return; $bigCnt[(string) $rk][(string) $b] = ($bigCnt[(string) $rk][(string) $b] ?? 0) + 1; };
    $put = function ($rk, $ck, $sd, $v) use (&$acc, &$cols) { $rk = (string) $rk; $ck = (string) $ck; if (!isset($acc[$rk][$ck])) $acc[$rk][$ck] = [0, 0]; $acc[$rk][$ck][$sd] += $v; $cols[$ck] = 1; };
    $idSet = js_set($c['ids']);
    if ($metric === 'SO') {
        $pv = coverageView_($c['source'], $period, $c['ids'], 'PAIR', $c['u']);
        if (empty($pv['stock']) || !empty($pv['missingSO'])) fail_('Data SELL OUT belum lengkap untuk cakupan ini.');
        foreach (stockMoves_($pv, strcmp($R['A']['from'], $R['B']['from']) < 0 ? $R['A']['from'] : $R['B']['from'], strcmp($R['A']['to'], $R['B']['to']) > 0 ? $R['A']['to'] : $R['B']['to'], ['out' => true]) as $t) {
            if (!truthy($t['outQty'])) continue; $sd = $side($t['date']); if ($sd < 0) continue;
            $p = $meta['products'][$t['sku']] ?? null; if (!$p || !truthy($p['device'] ?? false)) continue; $ck = $colKey($p['brand'], js_or($p['type'] ?? null, $p['nama'] ?? null)); if (!truthy($ck)) continue;
            $own = isset($pv['stock']['owners']) ? ($pv['stock']['owners'][$t['customer']] ?? null) : ''; if (!truthy($own) || !in_set($idSet, $own)) continue;
            $put($rowKey($own), $ck, $sd, $t['outQty']);
        }
    } elseif ($metric === 'NOO') {
        $first = $c['db']['first'] ?? [];
        foreach (js_keys($first) as $k) {
            $f = $first[$k]; $sd = $side($f['date']); if ($sd < 0 || !in_set($idSet, $f['nik'])) continue; $pr = json_decode($k, true); $ck = $colKey($pr[1], ''); if (!truthy($ck)) continue;
            $put($rowKey($f['nik']), $ck, $sd, 1);
        }
    } else {
        $months = []; foreach ([$R['A']['from'], $R['A']['to'], $R['B']['from'], $R['B']['to']] as $d) $months[substr($d, 0, 7)] = 1;
        foreach (js_keys($months) as $p) {
            if (!isset($c['db']['cubes'][$p])) continue;
            foreach ($p === $period ? PortalCore::slice($c['db'], $p, $c['ids'], null) : PortalCore::sliceCmp($c['db'], $p, $c['ids'], null, $period) as $r) {
                $sd = $side($r['date']); if ($sd < 0) continue; $ck = $colKey($r['brand'], $r['type'] ?? null); if (!truthy($ck)) continue;
                $rk = $rowKey($r['nik']); $noteBig($rk, $r['region'] ?? null);
                if ($metric === 'ST') { if (truthy($r['q'])) $put($rk, $ck, $sd, $r['q']); }
                elseif ($metric === 'OMZET') { if (truthy($r['a'])) $put($rk, $ck, $sd, $r['a']); }
                elseif ($metric === 'DA' && $r['q'] > 0) { $pk = $rk . '|' . $ck . '|' . $sd; $mk = $r['customer'] . '|' . $r['brand']; $pairs[$pk][$mk] = ($pairs[$pk][$mk] ?? 0) + $r['q']; $cols[(string) $ck] = 1; }
            }
        }
        if ($metric === 'DA') foreach (js_keys($pairs) as $pk) {
            $a = explode('|', $pk); $m = $pairs[$pk]; $n = 0; foreach ($m as $x) if ($x >= 2) $n++;
            if ($n) $put($a[0], $a[1], (int) $a[2], $n); elseif (!isset($acc[$a[0]][$a[1]])) $acc[$a[0]][$a[1]] = [0, 0];
        }
    }
    $map = $view === 'TYPE' ? gtModelMap_() : [];
    $accKeys = js_keys($acc);
    $colList = array_map(function ($k) use ($acc, $accKeys, $view, $brand, $map) { $t = [0, 0]; foreach ($accKeys as $rk) { $x = $acc[$rk][$k] ?? null; if ($x) { $t[0] += $x[0]; $t[1] += $x[1]; } } return ['key' => $k, 'model' => $view === 'TYPE' ? gtModel_($k, $brand, $map) : $k, 'a' => $t[0], 'l' => $t[1]]; }, js_keys($cols));
    $colList = js_sort($colList, fn($x, $y) => ($y['a'] + $y['l']) - ($x['a'] + $x['l']));
    $rows = array_map(function ($rk) use ($c, $level, $anc, $bigCnt, $acc) {
        $p = anaPerson_($c['people'], $rk) ?? ['nik' => $rk, 'nama' => substr($rk, 0, 1) === '~' ? '(tanpa ' . $level . ')' : $rk, 'role' => $level]; $asm = $anc($rk, 'ASM'); $rgm = $anc($rk, 'RGM');
        $group = $level === 'SALES' ? ($asm ? $asm['nama'] : '(tanpa ASM)') : ($level === 'ASM' ? ($rgm ? $rgm['nama'] : '(tanpa RGM)') : js_or($p['wilayah'] ?? null, '—'));
        $bc = $bigCnt[$rk] ?? null; $bigTx = $bc ? js_sort(js_keys($bc), fn($x, $y) => $bc[$y] - $bc[$x])[0] : '';
        $cells = []; foreach (js_keys($acc[$rk]) as $k) $cells[$k] = $acc[$rk][$k];
        return ['nik' => $rk, 'nama' => $p['nama'] ?? null, 'role' => js_or($p['role'] ?? null, $level), 'region' => $level === 'RGM' ? js_or($p['wilayah'] ?? null, js_or($bigTx, '')) : js_or($p['portfolio'] ?? null, js_or($p['region'] ?? null, '')), 'big' => js_or($p['wilayah'] ?? null, js_or($bigTx, '(tanpa Big Region)')), 'rgm' => $rgm ? $rgm['nama'] : '', 'asm' => $asm ? $asm['nama'] : '', 'group' => $group, 'self' => $level === 'SALES' && (bool) $asm && $asm['nik'] === $rk, 'cells' => js_obj($cells)];
    }, $accKeys);
    return ['view' => $view, 'cmp' => $cmp, 'metric' => $metric, 'level' => $level, 'brand' => $brand, 'period' => $period, 'cutoff' => $c['cutoff'], 'A' => $R['A'], 'B' => $R['B'], 'cols' => $colList, 'rows' => $rows, 'maxWeek' => min(5, (int) ceil(((int) substr($c['cutoff'], 8)) / 7))];
}
function apiGrowthType($token, $period, $selection = null, $brand = null, $opts = null) { $opts = is_array($opts) ? $opts : []; $opts['view'] = 'TYPE'; $opts['brand'] = $brand; $opts['level'] = 'SALES'; return apiCompare($token, $period, $selection, $opts); }

/* v6.23: data tambahan saat login digabung jadi 1 panggilan (hari libur + dealer tutup). */
function apiV6Boot($token) { $t = null; try { $t = apiDealerTutupList($token); } catch (Throwable $e) {} return ['hol' => anaHol_(), 'tutup' => $t]; }
