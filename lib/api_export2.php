<?php
/** BrandExport.gs, DOSExport.gs (data untuk Excel yang dibuat di browser). */
require_once __DIR__ . '/common.php';

function lcn_($a, $b): int { return js_locale_compare(js_str($a), js_str($b), 'id', true, true); }
function sortStr_(array $a): array { $a = array_values($a); sort($a, SORT_STRING); return $a; }
function uniqSorted_(array $a): array { return sortStr_(array_values(array_unique($a))); }

// ---------------- Brand export ----------------
function brandExportDeviceBrands_(array $meta): array {
    $o = []; foreach ($meta['products'] ?? [] as $p) if (truthy($p['device'] ?? null)) { $b = text_($p['brand'] ?? null); if ($b !== '') $o[] = $b; }
    return uniqSorted_($o);
}
function brandExportAccess_(array $db, array $u): array {
    $all = RegionCore::regions($db['meta']); $own = text_($u['wilayah'] ?? null); $big = []; $sp = [];
    if (RegionCore::top($u['role']) || $u['role'] === 'SUPER ADMIN') { $big = $all; $sp = $all; }
    elseif ($u['role'] === 'ADMIN') { $big = array_values(array_filter($u['adminRegions'] ?? [], fn($r) => in_array($r, $all, true))); $sp = $big; }
    elseif ($u['role'] === 'RGM') { $big = $all; $sp = $own !== '' ? [$own] : []; }
    else { $big = ($own !== '' && in_array($own, $all, true)) ? [$own] : []; $sp = $big; }
    return ['big' => uniqSorted_($big), 'subParents' => uniqSorted_($sp)];
}
function brandExportSubRegion_($p): string { $p = is_array($p) ? $p : []; return text_(js_or($p['portfolio'] ?? null, $p['region'] ?? null)); }
function brandExportRgm_(array $meta, $big): string {
    $n = []; foreach ($meta['people'] as $p) if ($p['role'] === 'RGM' && ($p['wilayah'] ?? null) === $big && truthy($p['nama'] ?? null)) $n[] = $p['nama'];
    return js_or(implode(', ', uniqSorted_($n)), '—');
}
function brandExportSalesFor_(array $db, array $u): array {
    $scope = js_set(PortalCore::scope($db['meta']['people'], $u['nik'])); $full = exportAccessAutomatic_($u) || RegionCore::top($u['role']);
    return array_values(js_filter($db['meta']['people'], fn($p) => truthy($p['sales'] ?? null) && ($full || in_set($scope, $p['nik']))));
}
function apiBrandExportOptions($token) {
    $db = snapshot_(); $u = exportGuard_($token, 'BRAND', $db); $access = brandExportAccess_($db, $u); $sales = brandExportSalesFor_($db, $u); $subs = [];
    foreach ($access['subParents'] as $big) { $x = []; foreach ($sales as $p) if (($p['wilayah'] ?? null) === $big) { $s = brandExportSubRegion_($p); if ($s !== '') $x[] = $s; } $subs[$big] = uniqSorted_($x); }
    return ['brands' => brandExportDeviceBrands_($db['meta']), 'bigRegions' => $access['big'], 'subParents' => $access['subParents'], 'subRegions' => js_obj($subs), 'defaultBig' => in_array($u['wilayah'] ?? null, $access['subParents'], true) ? $u['wilayah'] : ($access['subParents'][0] ?? '')];
}
function brandExportParseList_($value, string $label): array {
    if (!is_array($value) || !array_is_list($value)) fail_($label . ' tidak valid.');
    $o = []; foreach ($value as $v) { $t = text_($v); if ($t !== '') $o[] = $t; } return array_values(array_unique($o));
}
function brandExportCutoff_(array $db, string $kind, string $period, array $bigs) {
    if (isset($db['parts']) && truthy($db['parts'])) return RegionCore::common($db['parts'], $kind, $period, $bigs)['cutoff'] ?: null;
    return $kind === 'ST' ? js_or($db['coverage'][$period]['cutoff'] ?? null, null) : js_or($db['stock']['coverage'][$period]['cutoff'] ?? null, null);
}
function brandExportPrevCutoff_(array $db, string $kind, string $period, $current, array $bigs) {
    if (!$current) return null; $prev = PortalCore::prev($period);
    $want = $prev . '-' . str_pad((string) min((int) substr($current, 8), (int) substr(PortalCore::end($prev), 8)), 2, '0', STR_PAD_LEFT);
    $av = brandExportCutoff_($db, $kind, $prev, $bigs); return $av ? ($av < $want ? $av : $want) : null;
}
function brandExportGroups_(array $db, array $u, array $selection): array {
    $access = brandExportAccess_($db, $u); $sales = brandExportSalesFor_($db, $u); $level = strtoupper(text_($selection['level'] ?? null));
    if (!in_array($level, ['BIG', 'SUB'], true)) fail_('Pilih Big Region atau Sub Region.');
    $mk = function ($name, $big, $sub, $ids, $rgm) { return ['name' => $name, 'big' => $big, 'sub' => $sub, 'ids' => $ids, 'rgm' => $rgm, '_set' => js_set($ids)]; };
    if ($level === 'BIG') {
        $bigs = brandExportParseList_($selection['bigRegions'] ?? [], 'Big Region'); if (!$bigs) $bigs = $access['big'];
        $bad = !$bigs; foreach ($bigs as $r) if (!in_array($r, $access['big'], true)) $bad = true;
        if ($bad) fail_('Big Region di luar hak akses.');
        usort($bigs, fn($a, $b) => lc($a, $b));
        return ['level' => $level, 'bigs' => $bigs, 'groups' => array_map(fn($big) => $mk($big, $big, '', array_map(fn($p) => $p['nik'], array_values(js_filter($sales, fn($p) => ($p['wilayah'] ?? null) === $big))), brandExportRgm_($db['meta'], $big)), $bigs)];
    }
    $parent = text_($selection['parentBig'] ?? null);
    if ($parent === '' || !in_array($parent, $access['subParents'], true)) fail_('Pilih Big Region induk yang diizinkan.');
    $k = []; foreach ($sales as $p) if (($p['wilayah'] ?? null) === $parent) { $s = brandExportSubRegion_($p); if ($s !== '') $k[] = $s; } $known = uniqSorted_($k);
    $subs = brandExportParseList_($selection['subRegions'] ?? [], 'Sub Region'); if (!$subs) $subs = $known;
    $bad = !$subs; foreach ($subs as $r) if (!in_array($r, $known, true)) $bad = true;
    if ($bad) fail_('Sub Region di luar Big Region induk atau hak akses.');
    usort($subs, fn($a, $b) => lc($a, $b));
    return ['level' => $level, 'bigs' => [$parent], 'groups' => array_map(fn($sub) => $mk($sub, $parent, $sub, array_map(fn($p) => $p['nik'], array_values(js_filter($sales, fn($p) => ($p['wilayah'] ?? null) === $parent && brandExportSubRegion_($p) === $sub))), brandExportRgm_($db['meta'], $parent)), $subs)];
}
function brandExportRowMatch_(array $r, array $g): bool { return isset($g['_set'][(string) ($r['nik'] ?? '')]) && (!truthy($r['region'] ?? null) || $r['region'] === $g['big']); }
function brandExportStRows_(array $db, string $period, array $g, $cut): array {
    if (!$cut) return [];
    return array_values(js_filter($db['cubes'][$period] ?? [], fn($r) => $r['date'] <= $cut && brandExportRowMatch_($r, $g)));
}
function brandExportAssignmentIndex_(): array {
    $out = [];
    foreach (optionalTable_('Dealer_Assignment_History') as $r) {
        $c = text_($r['KODE CUSTOMER'] ?? null); $n = text_($r['NIK SALES'] ?? null); $f = text_($r['BERLAKU_MULAI'] ?? null); $t = text_($r['BERLAKU_SAMPAI'] ?? null);
        if ($c !== '' && $n !== '') $out[$c][] = ['nik' => $n, 'from' => $f, 'to' => $t];
    }
    foreach ($out as $k => $a) $out[$k] = js_sort($a, fn($x, $y) => lc(js_or($y['from'], ''), js_or($x['from'], '')));
    return $out;
}
function brandExportOwnerAt_(array $db, array $index, $customer, $date) {
    $found = js_find($index[$customer] ?? [], fn($x) => (!truthy($x['from']) || $x['from'] <= $date) && (!truthy($x['to']) || $x['to'] >= $date));
    return js_or($found['nik'] ?? null, js_or($db['stock']['owners'][$customer] ?? null, js_or($db['meta']['customers'][$customer]['nik'] ?? null, '')));
}
function brandExportSoQty_(array $db, array $assign, string $period, array $g, string $brand, $cut, $onlyDate) {
    if (!$cut || !isset($db['stock'])) return null; $set = $g['_set']; $qty = 0;
    foreach (stockMoves_($db, $period . '-01', (string) $cut, ['out' => true]) as $t) {
        if (!truthy($t['outQty'] ?? null) || substr($t['date'], 0, 7) !== $period || $t['date'] > $cut || (truthy($onlyDate) && $t['date'] !== $onlyDate)) continue;
        $p = $db['meta']['products'][$t['sku']] ?? null; if (!$p || $p['brand'] !== $brand) continue;
        $owner = brandExportOwnerAt_($db, $assign, $t['customer'], $t['date']); if (!isset($set[(string) $owner])) continue;
        $person = js_find($db['meta']['people'], fn($x) => $x['nik'] === $owner);
        if ($person && truthy($person['wilayah'] ?? null) && $person['wilayah'] !== $g['big']) continue;
        if (truthy($g['sub']) && brandExportSubRegion_($person ?: []) !== $g['sub']) continue;
        $qty += $t['outQty'];
    }
    return $qty;
}
function brandExportGrowth_($now, $prev) { if ($now === null || $prev === null) return null; if (!$prev) return 0; return min(5, $now / $prev - 1); }
function brandExportTarget_(array $meta, string $period, array $ids, string $brand) { $t = PortalCore::target($meta, $period, $ids, $brand, 'QTY'); if (!$t['exists'] || !empty($t['missing'])) return 0; $v = numJs_(js_or($t['value'] ?? null, 0)); return $v; }
function brandExportJoinSet_(array $db, array $g, string $brand, string $period, $cut): array {
    $set = [];
    foreach (js_keys($db['cubes'] ?? []) as $p) { $p = (string) $p; if (strcmp($p, $period) > 0) continue;
        foreach ($db['cubes'][$p] ?? [] as $r) { if ($r['brand'] !== $brand || !($r['q'] > 0) || ($p === $period && $cut && $r['date'] > $cut) || !brandExportRowMatch_($r, $g)) continue; $set[(string) $r['customer']] = true; } }
    return $set;
}
function brandExportMetricRow_(string $name, string $rgm, $target, array $today, array $now, array $before, int $join, $soToday, $soNow, $soPrev): array {
    return ['region' => $name, 'rgm' => $rgm, 'target' => $target, 'todayST' => $today['qty'], 'mtdST' => $now['qty'], 'lmtdST' => $before['qty'], 'achv' => $target > 0 ? $now['qty'] / $target : 0, 'growthST' => brandExportGrowth_($now['qty'], $before['qty']),
        'todayOmzet' => $today['omzet'], 'mtdOmzet' => $now['omzet'], 'lmtdOmzet' => $before['omzet'], 'growthOmzet' => brandExportGrowth_($now['omzet'], $before['omzet']), 'dealerJoin' => $join,
        'todayDA' => $today['da'], 'mtdDA' => $now['da'], 'lmtdDA' => $before['da'], 'growthDA' => brandExportGrowth_($now['da'], $before['da']), 'todaySO' => $soToday, 'mtdSO' => $soNow, 'lmtdSO' => $soPrev, 'growthSO' => brandExportGrowth_($soNow, $soPrev), 'noo' => $now['noo']];
}
function brandExportMetrics_(array $db, array $assign, string $period, array $g, string $brand, $stCut, $soCut, $prevStCut, $prevSoCut): array {
    $prev = PortalCore::prev($period); $nowRows = brandExportStRows_($db, $period, $g, $stCut); $prevRows = brandExportStRows_($db, $prev, $g, $prevStCut);
    $todayRows = array_values(js_filter($nowRows, fn($r) => $r['date'] === $stCut));
    $now = PortalCore::metrics($nowRows, $db, $period, $brand); $before = PortalCore::metrics($prevRows, $db, $prev, $brand); $today = PortalCore::metrics($todayRows, $db, $period, $brand);
    $target = brandExportTarget_($db['meta'], $period, $g['ids'], $brand);
    return brandExportMetricRow_($g['name'], $g['rgm'], $target, $today, $now, $before, count(brandExportJoinSet_($db, $g, $brand, $period, $stCut)), brandExportSoQty_($db, $assign, $period, $g, $brand, $soCut, $soCut), brandExportSoQty_($db, $assign, $period, $g, $brand, $soCut, ''), brandExportSoQty_($db, $assign, $prev, $g, $brand, $prevSoCut, ''));
}
function brandExportTotal_(array $db, array $assign, string $period, array $groups, string $brand, $stCut, $soCut, $prevStCut, $prevSoCut): array {
    $ids = []; foreach ($groups as $g) foreach ($g['ids'] as $i) $ids[] = $i; $ids = uniq($ids);
    $bigs = array_values(array_unique(array_map(fn($g) => $g['big'], $groups))); $idset = js_set($ids); $prev = PortalCore::prev($period);
    $filter = fn($rows, $cut) => array_values(js_filter($rows, fn($r) => $r['date'] <= $cut && isset($idset[(string) ($r['nik'] ?? '')]) && (!truthy($r['region'] ?? null) || in_array($r['region'], $bigs, true))));
    $nowRows = $stCut ? $filter($db['cubes'][$period] ?? [], $stCut) : []; $prevRows = $prevStCut ? $filter($db['cubes'][$prev] ?? [], $prevStCut) : [];
    $todayRows = array_values(js_filter($nowRows, fn($r) => $r['date'] === $stCut));
    $now = PortalCore::metrics($nowRows, $db, $period, $brand); $before = PortalCore::metrics($prevRows, $db, $prev, $brand); $today = PortalCore::metrics($todayRows, $db, $period, $brand);
    $target = brandExportTarget_($db['meta'], $period, $ids, $brand);
    $soQty = function ($p, $cut, $day) use ($db, $assign, $idset, $brand) {
        if (!$cut || !isset($db['stock'])) return null; $qty = 0;
        foreach (stockMoves_($db, $p . '-01', (string) $cut, ['out' => true]) as $t) {
            if (!truthy($t['outQty'] ?? null) || substr($t['date'], 0, 7) !== $p || $t['date'] > $cut || (truthy($day) && $t['date'] !== $day)) continue;
            $pr = $db['meta']['products'][$t['sku']] ?? null; if (!$pr || $pr['brand'] !== $brand) continue;
            if (isset($idset[(string) brandExportOwnerAt_($db, $assign, $t['customer'], $t['date'])])) $qty += $t['outQty'];
        }
        return $qty;
    };
    $join = []; foreach ($groups as $g) foreach (brandExportJoinSet_($db, $g, $brand, $period, $stCut) as $k => $_) $join[$k] = true;
    return brandExportMetricRow_('GRAND TOTAL', '', $target, $today, $now, $before, count($join), $soQty($period, $soCut, $soCut), $soQty($period, $soCut, ''), $soQty($prev, $prevSoCut, ''));
}
function brandExportTypeData_(array $db, array $assign, string $period, array $scope, string $brand, $stCut, $soCut): array {
    if (!$soCut) fail_('Data SO belum tersedia untuk export Per Type.');
    $cs = array_values(array_filter([$stCut, $soCut], fn($x) => truthy($x))); sort($cs, SORT_STRING); $cut = $cs[0];
    $selected = []; foreach ($scope['groups'] as $g) foreach ($g['ids'] as $i) $selected[(string) $i] = true;
    $t0 = []; foreach ($db['meta']['products'] ?? [] as $p) if (truthy($p['device'] ?? null) && $p['brand'] === $brand) { $x = text_(js_or($p['type'] ?? null, js_or($p['nama'] ?? null, $p['id'] ?? null))); if ($x !== '') $t0[] = $x; }
    $types = uniqSorted_($t0); $map = []; $blank = fn($type) => ['type' => $type, 'st' => [0, 0, 0, 0, 0], 'so' => [0, 0, 0, 0, 0]];
    foreach ($types as $type) $map[$type] = $blank($type);
    foreach ($db['cubes'][$period] ?? [] as $r) {
        if ($r['date'] > $cut || $r['brand'] !== $brand || !truthy($r['q'] ?? null) || !isset($selected[(string) ($r['nik'] ?? '')]) || (truthy($r['region'] ?? null) && !in_array($r['region'], $scope['bigs'], true))) continue;
        $type = js_or(text_($r['type'] ?? null), 'TANPA TYPE'); if (!isset($map[$type])) $map[$type] = $blank($type);
        $w = min(4, (int) floor(((int) substr($r['date'], 8) - 1) / 7)); $map[$type]['st'][$w] += $r['q'];
    }
    foreach (stockMoves_($db, $period . '-01', (string) $cut, ['out' => true]) as $t) {
        if (!truthy($t['outQty'] ?? null) || substr($t['date'], 0, 7) !== $period || $t['date'] > $cut) continue;
        $pr = $db['meta']['products'][$t['sku']] ?? null; if (!$pr || $pr['brand'] !== $brand) continue;
        $owner = brandExportOwnerAt_($db, $assign, $t['customer'], $t['date']); if (!isset($selected[(string) $owner])) continue;
        $person = js_find($db['meta']['people'], fn($p) => $p['nik'] === $owner);
        if ($person && truthy($person['wilayah'] ?? null) && !in_array($person['wilayah'], $scope['bigs'], true)) continue;
        $type = js_or(text_(js_or($pr['type'] ?? null, js_or($pr['nama'] ?? null, $t['sku']))), 'TANPA TYPE'); if (!isset($map[$type])) $map[$type] = $blank($type);
        $w = min(4, (int) floor(((int) substr($t['date'], 8) - 1) / 7)); $map[$type]['so'][$w] += $t['outQty'];
    }
    $rows = js_sort(array_values($map), fn($a, $b) => lc($a['type'], $b['type']));
    $total = ['type' => 'TOTAL', 'st' => [0, 0, 0, 0, 0], 'so' => [0, 0, 0, 0, 0]];
    foreach ($rows as &$r) { $r['totalST'] = array_sum($r['st']); $r['totalSO'] = array_sum($r['so']); foreach ($r['st'] as $i => $n) $total['st'][$i] += $n; foreach ($r['so'] as $i => $n) $total['so'][$i] += $n; } unset($r);
    $total['totalST'] = array_sum($total['st']); $total['totalSO'] = array_sum($total['so']);
    $label = implode(', ', array_map(fn($g) => $g['name'], $scope['groups']));
    return ['cutoff' => $cut, 'scopeLabel' => js_or($label, '—'), 'rows' => $rows, 'total' => $total];
}
function apiBrandExportData($token, $period = null, $selection = null) {
    $db = snapshot_(); $u = exportGuard_($token, 'BRAND', $db); $period = PortalCore::month($period); $selection = is_array($selection) ? $selection : [];
    $scope = brandExportGroups_($db, $u, $selection); $known = brandExportDeviceBrands_($db['meta']);
    $brands = brandExportParseList_($selection['brands'] ?? [], 'Brand'); if (!$brands) $brands = $known;
    foreach ($brands as $b) if (!in_array($b, $known, true)) fail_('Brand export tidak dikenal.');
    usort($brands, fn($a, $b) => lc($a, $b));
    $incS = ($selection['includeSummary'] ?? null) !== false; $incT = ($selection['includeType'] ?? null) !== false; if (!$incS && !$incT) fail_('Pilih minimal satu isi export.');
    $stCut = brandExportCutoff_($db, 'ST', $period, $scope['bigs']); $soCut = brandExportCutoff_($db, 'SO', $period, $scope['bigs']);
    $miss = function ($kind) use ($db, $period, $scope) { $o = []; foreach ($scope['bigs'] as $r) if (empty($db['parts'][$kind][$period][$r]['cutoff'])) $o[] = $r; return $o ? ' Region yang belum ada data ' . $kind . ' bulan ini: ' . implode(', ', $o) . '. Hilangkan centang region tsb atau upload datanya dulu.' : ''; };
    if (!$stCut) fail_('Data ST belum lengkap untuk region pilihan.' . $miss('ST')); if ($incT && !$soCut) fail_('Data SO belum lengkap untuk export Per Type.' . $miss('SO'));
    $prevSt = brandExportPrevCutoff_($db, 'ST', $period, $stCut, $scope['bigs']); $prevSo = $soCut ? brandExportPrevCutoff_($db, 'SO', $period, $soCut, $scope['bigs']) : null; $assign = brandExportAssignmentIndex_();
    $mkSummary = function ($brand) use ($db, $assign, $period, $scope, $stCut, $soCut, $prevSt, $prevSo) {
        $rows = array_map(fn($g) => brandExportMetrics_($db, $assign, $period, $g, $brand, $stCut, $soCut, $prevSt, $prevSo), $scope['groups']);
        $total = brandExportTotal_($db, $assign, $period, $scope['groups'], $brand, $stCut, $soCut, $prevSt, $prevSo);
        // Total target = jumlah target tiap region (sebelumnya 0 bila ada sales di cakupan yang belum punya target)
        $sum = 0; foreach ($rows as $r) $sum += (float) ($r['target'] ?? 0);
        if (!($total['target'] > 0) && $sum > 0) { $total['target'] = $sum; $total['achv'] = $total['mtdST'] / $sum; }
        return ['rows' => $rows, 'total' => $total];
    };
    $sheets = array_map(fn($brand) => ['brand' => $brand, 'summary' => $incS ? $mkSummary($brand) : null, 'type' => $incT ? brandExportTypeData_($db, $assign, $period, $scope, $brand, $stCut, $soCut) : null], $brands);
    audit_($u['nik'], 'BRAND_XLSX_EXPORT', $period . ' ' . $scope['level'] . ' ' . implode('|', $scope['bigs']) . ' ' . implode('|', $brands) . ' ' . ($incS ? 'SUMMARY ' : '') . ($incT ? 'TYPE' : ''));
    return ['name' => 'Brand-Report-' . $period . '-' . $scope['level'] . '.xlsx', 'period' => $period, 'level' => $scope['level'], 'parentBig' => $scope['level'] === 'SUB' ? $scope['bigs'][0] : '', 'stCut' => $stCut, 'soCut' => $soCut, 'prevPeriod' => PortalCore::prev($period), 'prevStCut' => $prevSt, 'prevSoCut' => $prevSo, 'includeSummary' => $incS, 'includeType' => $incT, 'sheets' => $sheets];
}

// ---------------- DOS export ----------------
function dosExportGuard_($token, array $db): array { return exportGuard_($token, 'DOS', $db); }
function dosExportSub_($p): string { $p = is_array($p) ? $p : []; return text_(js_or($p['portfolio'] ?? null, $p['region'] ?? null)); }
function dosExportAccess_(array $db, array $u): array {
    $people = $db['meta']['people']; $sales = array_values(js_filter($people, fn($p) => truthy($p['sales'] ?? null))); $allBig = RegionCore::regions($db['meta']);
    $full = in_array($u['role'], ['ADMIN', 'SUPER ADMIN'], true) || RegionCore::top($u['role']);
    $base = $full ? array_map(fn($p) => $p['nik'], $people) : PortalCore::scope($people, $u['nik']); $baseSet = js_set($base);
    $salesIds = array_map(fn($p) => $p['nik'], array_values(js_filter($sales, fn($p) => $full || isset($baseSet[(string) $p['nik']])))); $salesSet = js_set($salesIds);
    $bigs = $full ? $allBig : (truthy($u['wilayah'] ?? null) ? [$u['wilayah']] : []); $subs = [];
    foreach ($bigs as $big) { $x = []; foreach ($sales as $p) if (isset($salesSet[(string) $p['nik']]) && ($p['wilayah'] ?? null) === $big) { $s = dosExportSub_($p); if ($s !== '') $x[] = $s; } $subs[$big] = uniqSorted_($x); }
    return ['bigs' => uniqSorted_($bigs), 'subs' => $subs, 'salesIds' => $salesIds, 'salesSet' => $salesSet];
}
function apiDOSExportOptions($token) {
    $db = snapshot_(); $u = dosExportGuard_($token, $db); $a = dosExportAccess_($db, $u);
    return ['brands' => brandExportDeviceBrands_($db['meta']), 'bigRegions' => $a['bigs'], 'subParents' => $a['bigs'], 'subRegions' => js_obj($a['subs']), 'defaultBig' => in_array($u['wilayah'] ?? null, $a['bigs'], true) ? $u['wilayah'] : ($a['bigs'][0] ?? '')];
}
function dosExportPerson_(array $people, $nik) { return js_find($people, fn($p) => $p['nik'] === $nik); }
function dosExportScope_(array $db, array $u, array $selection): array {
    $a = dosExportAccess_($db, $u); $people = $db['meta']['people']; $level = strtoupper(text_($selection['level'] ?? null));
    if (!in_array($level, ['BIG', 'SUB'], true)) fail_('Pilih Big Region atau Sub Region.');
    if ($level === 'BIG') {
        $bigs = brandExportParseList_($selection['bigRegions'] ?? [], 'Big Region'); if (!$bigs) $bigs = $a['bigs'];
        $bad = !$bigs; foreach ($bigs as $r) if (!in_array($r, $a['bigs'], true)) $bad = true;
        if ($bad) fail_('Big Region di luar hak akses DOS.');
        usort($bigs, fn($x, $y) => lc($x, $y));
        $ids = array_values(array_filter($a['salesIds'], fn($id) => in_array((dosExportPerson_($people, $id)['wilayah'] ?? null), $bigs, true)));
        if (!$ids) fail_('Tidak ada Sales pada cakupan pilihan.');
        $label = $u['role'] === 'ASM' ? js_or(text_($u['region'] ?? null), js_or(text_($u['wilayah'] ?? null), implode(', ', $bigs))) : implode(', ', $bigs);
        return ['level' => $level, 'bigs' => $bigs, 'subs' => [], 'ids' => $ids, 'label' => $label];
    }
    $parent = text_($selection['parentBig'] ?? null);
    if ($parent === '' || !in_array($parent, $a['bigs'], true)) fail_('Big Region induk di luar hak akses DOS.');
    $known = $a['subs'][$parent] ?? []; $subs = brandExportParseList_($selection['subRegions'] ?? [], 'Sub Region'); if (!$subs) $subs = $known;
    $bad = !$subs; foreach ($subs as $r) if (!in_array($r, $known, true)) $bad = true;
    if ($bad) fail_('Sub Region di luar hak akses DOS.');
    usort($subs, fn($x, $y) => lc($x, $y));
    $ids = array_values(array_filter($a['salesIds'], function ($id) use ($people, $parent, $subs) { $p = dosExportPerson_($people, $id); return $p && ($p['wilayah'] ?? null) === $parent && in_array(dosExportSub_($p), $subs, true); }));
    if (!$ids) fail_('Tidak ada Sales pada Sub Region pilihan.');
    return ['level' => $level, 'bigs' => [$parent], 'subs' => $subs, 'ids' => $ids, 'label' => implode(', ', $subs)];
}
function dosExportAsm_(array $people, $nik): string {
    $p = dosExportPerson_($people, $nik); $seen = [];
    while ($p && !isset($seen[(string) $p['nik']])) { $seen[(string) $p['nik']] = true; if ($p['role'] === 'ASM') return js_str(js_or($p['nama'] ?? null, '')); $p = dosExportPerson_($people, $p['atasan'] ?? null); }
    return '';
}
function dosExportBlank_($type): array { return ['type' => $type, 'st' => 0, 'so' => 0, 'stock' => 0, 'so30' => 0, 'dos' => 0, 'bad' => false]; }
function dosExportSum_(array $items): array {
    $x = ['st' => 0, 'so' => 0, 'stock' => 0, 'so30' => 0, 'dos' => 0, 'bad' => false];
    foreach ($items as $i) { $x['st'] += $i['st']; $x['so'] += $i['so']; $x['stock'] += $i['stock']; $x['so30'] += $i['so30']; $x['bad'] = $x['bad'] || $i['bad']; }
    $x['dos'] = !$x['bad'] && $x['so30'] > 0 ? (int) jsRound_($x['stock'] / ($x['so30'] / 30)) : 0; return $x;
}
function dosExportDealerRows_(array $db, string $period, array $scope, string $brand): array {
    $ids = js_set($scope['ids']); $people = $db['meta']['people']; $products = $db['meta']['products'];
    $t0 = []; foreach ($products as $p) if (truthy($p['device'] ?? null) && $p['brand'] === $brand) { $x = text_(js_or($p['type'] ?? null, js_or($p['nama'] ?? null, $p['id'] ?? null))); if ($x !== '') $t0[] = $x; }
    $types = uniqSorted_($t0);
    $dealers = []; foreach ($db['meta']['customers'] as $c) if (isset($ids[(string) ($c['nik'] ?? '')])) $dealers[] = $c;
    $dealers = js_sort($dealers, function ($a, $b) { $r = lcn_(js_or($a['nama'] ?? null, ''), js_or($b['nama'] ?? null, '')); return $r ?: lcn_((string) $a['id'], (string) $b['id']); });
    $dealerSet = []; foreach ($dealers as $c) $dealerSet[(string) $c['id']] = true;
    $asof = $db['coverage'][$period]['cutoff'] ?? null; if (!$asof) fail_('Cutoff DOS belum tersedia untuk periode pilihan.');
    $from = StockCore::shift($asof, -29);
    // Kosong dianggap 0: tidak lagi memblokir (lihat StockCore::calculate).
    $covered = true;
    $windowReady = $from !== '' && $from >= StockCore::BASE;
    $map = []; foreach ($dealers as $c) { $m = []; foreach ($types as $t) $m['k' . $t] = dosExportBlank_($t); $map['k' . $c['id']] = $m; }
    // DSM v5: saldo per dealer × SKU dari MySQL (sejak baseline sampai $asof)
    foreach (stockBalance_($db, $asof, $from) as $t) {
        if (!isset($dealerSet[(string) $t['customer']]) || !$t['n']) continue;
        $p = $products[$t['sku']] ?? null; if (!$p || !truthy($p['device'] ?? null) || $p['brand'] !== $brand) continue;
        $type = js_or(text_(js_or($p['type'] ?? null, js_or($p['nama'] ?? null, $t['sku']))), 'TANPA TYPE');
        $ck = 'k' . $t['customer'];
        if (!isset($map[$ck]['k' . $type])) { $map[$ck]['k' . $type] = dosExportBlank_($type); if (!in_array($type, $types, true)) $types[] = $type; }
        $x = &$map[$ck]['k' . $type]; $x['st'] += $t['st']; $x['so'] += $t['out']; $x['so30'] += $t['sellout']; unset($x);
    }
    $types = sortStr_($types);
    $calc = function (array $x) use ($covered, $windowReady): array { $x['stock'] = $x['st'] - $x['so']; $x['bad'] = $x['stock'] < 0 || !$covered || !$windowReady; $x['dos'] = !$x['bad'] && $x['so30'] > 0 ? (int) jsRound_($x['stock'] / ($x['so30'] / 30)) : 0; return $x; };
    $rows = [];
    foreach ($dealers as $c) {
        $ck = 'k' . $c['id']; $items = [];
        foreach ($types as $type) $items[] = $calc($map[$ck]['k' . $type] ?? dosExportBlank_($type));
        $total = dosExportSum_($items); $owner = dosExportPerson_($people, $c['nik']);
        $note = $total['bad'] ? 'Perlu cek data' : ($total['stock'] === 0 ? 'Stock 0' : '-');
        $rows[] = ['customer' => $c['id'], 'nama' => js_or($c['nama'] ?? null, $c['id']), 'sales' => js_or($owner['nama'] ?? null, ''), 'asm' => dosExportAsm_($people, $c['nik']), 'region' => js_or(dosExportSub_($owner ?: []), js_or($c['region'] ?? null, '')), 'note' => $note, 'types' => $items, 'total' => $total];
    }
    $totalByType = []; $coverageByType = [];
    foreach ($types as $i => $type) {
        $xs = array_map(fn($r) => $r['types'][$i], $rows);
        $totalByType[] = dosExportSum_($xs);
        $coverageByType[] = ['st' => count(array_filter($xs, fn($x) => $x['st'] >= 2)), 'so' => count(array_filter($xs, fn($x) => $x['so'] >= 1)), 'stock' => count(array_filter($xs, fn($x) => $x['stock'] >= 1)), 'so30' => count(array_filter($xs, fn($x) => $x['so30'] >= 1))];
    }
    $total = dosExportSum_(array_map(fn($r) => $r['total'], $rows));
    $coverage = ['st' => count(array_filter($rows, fn($r) => $r['total']['st'] >= 2)), 'so' => count(array_filter($rows, fn($r) => $r['total']['so'] >= 1)), 'stock' => count(array_filter($rows, fn($r) => $r['total']['stock'] >= 1)), 'so30' => count(array_filter($rows, fn($r) => $r['total']['so30'] >= 1))];
    return ['brand' => $brand, 'types' => $types, 'rows' => $rows, 'totalByType' => $totalByType, 'coverageByType' => $coverageByType, 'total' => $total, 'coverage' => $coverage, 'asof' => $asof, 'from' => $from, 'covered' => $covered, 'windowReady' => $windowReady, 'scopeLabel' => $scope['label']];
}
function apiDOSExportData($token, $period = null, $selection = null) {
    $source = snapshot_(); $u = dosExportGuard_($token, $source); $period = PortalCore::month($period); $selection = is_array($selection) ? $selection : [];
    $scope = dosExportScope_($source, $u, $selection); $known = brandExportDeviceBrands_($source['meta']);
    $brands = brandExportParseList_($selection['brands'] ?? [], 'Brand'); if (!$brands) $brands = $known;
    foreach ($brands as $b) if (!in_array($b, $known, true)) fail_('Brand DOS tidak dikenal.');
    usort($brands, fn($a, $b) => lc($a, $b));
    $db = coverageView_($source, $period, $scope['ids'], 'PAIR', null);
    if (truthy($db['missingST'] ?? null) || truthy($db['missingSO'] ?? null)) fail_('Menunggu ST dan SO seluruh region dalam pilihan. Pilih cakupan lebih kecil atau lengkapi data.');
    $sheets = array_map(fn($b) => dosExportDealerRows_($db, $period, $scope, $b), $brands);
    audit_($u['nik'], 'DOS_XLSX_EXPORT', $period . ' ' . $scope['level'] . ' ' . $scope['label'] . ' ' . implode('|', $brands));
    $cut = $db['coverage'][$period]['cutoff'] ?? null;
    return ['name' => 'DOS-' . $period . '-' . $scope['level'] . '.xlsx', 'period' => $period, 'level' => $scope['level'], 'scopeLabel' => $scope['label'], 'asof' => $cut ?: null, 'from' => StockCore::shift($cut ?: $period . '-01', -29), 'sheets' => $sheets];
}
function jsRound_($x) { return floor($x + 0.5); }

// ---------------- KPI export ----------------
function kpiExportGuard_($token, array $db): array { return exportGuard_($token, 'KPI', $db); }
function kpiExportSub_($p): string { $p = is_array($p) ? $p : []; return text_(js_or($p['portfolio'] ?? null, $p['region'] ?? null)); }
function kpiExportIsTopTier_(array $u): bool { return RegionCore::top($u['role']); }
function kpiExportFullDetail_(array $u): bool { return $u['role'] === 'SUPER ADMIN'; }
function kpiExportRankAllowed_(array $u): bool { return in_array($u['role'], ['RGM', 'ADMIN', 'SUPER ADMIN'], true) || kpiExportIsTopTier_($u); }
function kpiExportGroupOrder_(array $names): array {
    $pr = function ($n) { return preg_match('/all\s*brand/i', $n) ? 0 : (preg_match('/motopad/i', $n) ? 1 : (preg_match('/realme/i', $n) ? 2 : 3)); };
    $u = []; foreach ($names as $n) { $t = text_($n); if ($t !== '' && !in_array($t, $u, true)) $u[] = $t; }
    return js_sort($u, fn($a, $b) => ($pr($a) - $pr($b)) ?: lcn_($a, $b));
}
function kpiExportPeriods_(array $db): array {
    $o = []; foreach ($db['meta']['policies'] ?? [] as $p) if (truthy($p['period'] ?? null)) $o[] = $p['period'];
    $o = uniqSorted_($o); return array_reverse($o);
}
function kpiExportGroups_(array $db, string $period): array {
    $o = []; foreach ($db['meta']['policies'] ?? [] as $p) if (($p['period'] ?? null) === $period) $o[] = $p['group'] ?? null;
    return kpiExportGroupOrder_($o);
}
function apiKPIExportOptions($token, $period = null) {
    $db = snapshot_(); $u = kpiExportGuard_($token, $db); $periods = kpiExportPeriods_($db); $period = text_($period);
    if (!in_array($period, $periods, true)) $period = $periods[0] ?? $period;
    $groups = truthy($period) ? kpiExportGroups_($db, $period) : [];
    return ['periods' => $periods, 'period' => $period, 'ranking' => kpiExportRankAllowed_($u), 'rankingOnly' => kpiExportIsTopTier_($u), 'groups' => $groups, 'defaultRank' => 'FINAL'];
}
function kpiExportDetailRegions_(array $db, array $u): array {
    $all = RegionCore::regions($db['meta']);
    if (kpiExportIsTopTier_($u)) return [];
    if (kpiExportFullDetail_($u)) return $all;
    if ($u['role'] === 'ADMIN') return uniqSorted_(array_values(array_filter($u['adminRegions'] ?? [], fn($r) => in_array($r, $all, true))));
    return (truthy($u['wilayah'] ?? null) && in_array($u['wilayah'], $all, true)) ? [$u['wilayah']] : [];
}
function kpiExportBaseIds_(array $db, array $u): array {
    if (kpiExportFullDetail_($u)) return array_map(fn($p) => $p['nik'], $db['meta']['people']);
    if ($u['role'] === 'ADMIN') { $al = js_set($u['adminRegions'] ?? []); return array_map(fn($p) => $p['nik'], array_values(js_filter($db['meta']['people'], fn($p) => isset($al[(string) ($p['wilayah'] ?? '')])))); }
    return PortalCore::scope($db['meta']['people'], $u['nik']);
}
function kpiExportAncestor_(array $people, $p, string $role) {
    $x = $p; $seen = [];
    while ($x && !isset($seen[(string) $x['nik']])) { $seen[(string) $x['nik']] = true; if ($x['role'] === $role) return $x; $nx = $x['atasan'] ?? null; $x = js_find($people, fn($q) => $q['nik'] === $nx); }
    return null;
}
function kpiExportBossName_(array $people, $p): string { $a = $p['atasan'] ?? null; $f = js_find($people, fn($x) => $x['nik'] === $a); return js_str(js_or($f['nama'] ?? null, '')); }
function kpiExportSlimKpi_($k): array {
    $k = is_array($k) ? $k : [];
    $groups = array_map(function ($g) {
        $inds = array_map(function ($i) {
            $o = ['indicator' => js_or($i['indicator'] ?? null, ''), 'label' => js_or($i['label'] ?? null, '')];
            foreach (['metric', 'brand', 'weight', 'cap', 'target', 'actual'] as $f) if (array_key_exists($f, $i)) $o[$f] = $i[$f];
            $o['ach'] = $i['ach'] ?? null; $o['score'] = $i['score'] ?? null; $o['state'] = js_or($i['state'] ?? null, ''); $o['types'] = js_or($i['types'] ?? null, []); $o['minDA'] = js_or($i['minDA'] ?? null, 2);
            return $o;
        }, $g['indicators'] ?? []);
        return ['name' => $g['name'] ?? null, 'score' => $g['score'] ?? null, 'pending' => truthy($g['pending'] ?? null), 'na' => truthy($g['na'] ?? null), 'indicators' => $inds];
    }, $k['groups'] ?? []);
    return ['score' => $k['score'] ?? null, 'status' => js_or($k['status'] ?? null, ''), 'count' => js_or($k['count'] ?? null, 0), 'groups' => $groups];
}
function kpiExportGroupDefs_($k): array {
    $k = is_array($k) ? $k : [];
    return array_map(function ($g) {
        return ['name' => $g['name'] ?? null, 'indicators' => array_map(function ($i) {
            return ['indicator' => js_or($i['indicator'] ?? null, ''), 'label' => js_or($i['label'] ?? null, js_str($i['metric'] ?? null) . ' ' . js_str($i['brand'] ?? null)), 'metric' => $i['metric'] ?? null, 'brand' => $i['brand'] ?? null, 'weight' => $i['weight'] ?? null, 'types' => js_or($i['types'] ?? null, []), 'minDA' => js_or($i['minDA'] ?? null, 2)];
        }, $g['indicators'] ?? [])];
    }, $k['groups'] ?? []);
}
function kpiExportRegionSheet_(array $source, array $u, string $period, $big) {
    $people = $source['meta']['people']; $base = js_set(kpiExportBaseIds_($source, $u));
    $allSales = array_values(js_filter($people, fn($p) => truthy($p['sales'] ?? null) && ($p['wilayah'] ?? null) === $big && isset($base[(string) $p['nik']])));
    if (!$allSales) return null;
    $ids = array_map(fn($p) => $p['nik'], $allSales); $db = coverageView_($source, $period, $ids, 'ST', $u['role'] === 'ADMIN' ? $u : null); $cutoff = $db['coverage'][$period]['cutoff'] ?? null;
    $allAsm = $u['role'] !== 'ASM' ? array_values(js_filter($people, fn($p) => $p['role'] === 'ASM' && ($p['wilayah'] ?? null) === $big && isset($base[(string) $p['nik']]))) : [];
    $salesRows = array_map(function ($p) use ($people, $db, $period, $big) {
        $asm = kpiExportAncestor_($people, $p, 'ASM');
        return ['nik' => $p['nik'], 'nama' => $p['nama'] ?? null, 'atasan' => js_or($asm['nama'] ?? null, kpiExportBossName_($people, $p)), 'subRegion' => kpiExportSub_($p), 'acting' => $p['role'] !== 'SALES', 'kpi' => kpiExportSlimKpi_(PortalCore::kpi($db, $period, [$p['nik']], $big))];
    }, $allSales);
    $salesRows = js_sort($salesRows, fn($a, $b) => lcn_($a['subRegion'], $b['subRegion']) ?: lcn_($a['atasan'], $b['atasan']) ?: lcn_($a['nama'], $b['nama']));
    $asmRows = array_map(function ($p) use ($people, $db, $period, $big, $ids) {
        $team = array_values(array_filter(PortalCore::scope($people, $p['nik']), fn($id) => in_array($id, $ids, true)));
        return ['nik' => $p['nik'], 'nama' => $p['nama'] ?? null, 'atasan' => kpiExportBossName_($people, $p), 'subRegion' => kpiExportSub_($p), 'kpi' => kpiExportSlimKpi_(PortalCore::kpi($db, $period, $team, $big))];
    }, $allAsm);
    $asmRows = js_sort($asmRows, fn($a, $b) => lcn_($a['subRegion'], $b['subRegion']) ?: lcn_($a['nama'], $b['nama']));
    $kt = PortalCore::kpi($db, $period, $ids, $big); $total = kpiExportSlimKpi_($kt); $defs = kpiExportGroupDefs_($kt);
    $scopeLabel = $u['role'] === 'ASM' ? js_str($u['nama'] ?? null) . ' · ' . js_or(kpiExportSub_($u), $big) : $big;
    return ['region' => $big, 'scopeLabel' => $scopeLabel, 'cutoff' => $cutoff, 'groups' => $defs, 'salesRows' => $salesRows, 'salesTotal' => $total, 'asmRows' => $asmRows, 'asmTotal' => $asmRows ? $total : null];
}
function kpiExportNationalRows_(array $source, string $period, string $level, string $rankGroup, array $groupNames): array {
    $people = $source['meta']['people']; $allIds = array_map(fn($p) => $p['nik'], $people); $db = coverageView_($source, $period, $allIds, 'ST');
    $rows = [];
    foreach ($people as $p) {
        if ($p['role'] !== $level || $p['nik'] === '-') continue;
        $team = $level === 'ASM' ? PortalCore::scope($people, $p['nik']) : [$p['nik']]; $k = PortalCore::kpi($db, $period, $team, $p['wilayah'] ?? null);
        $scores = []; $states = [];
        foreach ($k['groups'] ?? [] as $g) { $scores['k' . $g['name']] = $g['score'] ?? null; $states['k' . $g['name']] = ['na' => truthy($g['na'] ?? null), 'pending' => truthy($g['pending'] ?? null)]; }
        $gs = $states['k' . $rankGroup] ?? null; $hasSel = $rankGroup === 'FINAL' || ($gs && !$gs['na']);
        $selected = $rankGroup === 'FINAL' ? ($k['score'] ?? null) : ($hasSel ? ($scores['k' . $rankGroup] ?? null) : null); $fallback = $hasSel ? $selected : ($k['score'] ?? null);
        $rows[] = ['nik' => $p['nik'], 'nama' => $p['nama'] ?? null, 'region' => js_or($p['wilayah'] ?? null, ''), 'subRegion' => kpiExportSub_($p), 'atasan' => kpiExportBossName_($people, $p), 'scores' => $scores, 'final' => $k['score'] ?? null, 'status' => js_or($k['status'] ?? null, ''), 'hasSelected' => $hasSel, 'sortScore' => $fallback];
    }
    $rows = js_sort($rows, function ($a, $b) use ($rankGroup) {
        $bA = $rankGroup === 'FINAL' || ($a['hasSelected'] && ($a['scores']['k' . $rankGroup] ?? null) !== null) ? 0 : 1; $bB = $rankGroup === 'FINAL' || ($b['hasSelected'] && ($b['scores']['k' . $rankGroup] ?? null) !== null) ? 0 : 1;
        if ($bA !== $bB) return $bA - $bB; $sa = $a['sortScore']; $sb = $b['sortScore'];
        if ($sa === null && $sb !== null) return 1; if ($sa !== null && $sb === null) return -1;
        if ($sa !== null && $sb !== null && $sb != $sa) return $sb - $sa;
        $fa = $a['final'] ?? -1; $fb = $b['final'] ?? -1; if ($fb != $fa) return $fb - $fa;
        return lcn_($a['nama'], $b['nama']);
    });
    $rankable = array_values(array_filter($rows, fn($r) => $r['sortScore'] !== null)); $hi = count($rankable) < 6 ? 1 : 3;
    $top = js_set(array_map(fn($r) => $r['nik'], array_slice($rankable, 0, $hi))); $bottom = js_set(array_map(fn($r) => $r['nik'], array_slice($rankable, -$hi)));
    $rank = 0; $out = [];
    foreach ($rows as $r) {
        if ($r['sortScore'] !== null) $rank++;
        $gsc = array_map(fn($n) => array_key_exists('k' . $n, $r['scores']) ? $r['scores']['k' . $n] : null, $groupNames);
        $out[] = ['nik' => $r['nik'], 'nama' => $r['nama'], 'region' => $r['region'], 'subRegion' => $r['subRegion'], 'atasan' => $r['atasan'], 'final' => $r['final'], 'status' => $r['status'], 'rank' => $r['sortScore'] === null ? null : $rank, 'highlight' => isset($top[(string) $r['nik']]) ? 'TOP' : (isset($bottom[(string) $r['nik']]) ? 'BOTTOM' : ''), 'groupScores' => $gsc];
    }
    return ['rows' => $out, 'cutoff' => $db['coverage'][$period]['cutoff'] ?? null];
}
function apiKPIExportData($token, $period = null, $selection = null) {
    $source = snapshot_(); $u = kpiExportGuard_($token, $source); $period = PortalCore::month($period); $selection = is_array($selection) ? $selection : [];
    $groups = kpiExportGroups_($source, $period); $rankGroup = js_or(text_($selection['rankGroup'] ?? null), 'FINAL');
    if ($rankGroup !== 'FINAL' && !in_array($rankGroup, $groups, true)) fail_('Kelompok KPI untuk ranking tidak tersedia pada periode ini.');
    $detail = kpiExportDetailRegions_($source, $u); $sheets = array_values(array_filter(array_map(fn($r) => kpiExportRegionSheet_($source, $u, $period, $r), $detail), fn($x) => $x !== null));
    $ranking = kpiExportRankAllowed_($u); $rankingOnly = kpiExportIsTopTier_($u);
    if (!$rankingOnly && !$sheets) fail_('Tidak ada data KPI dalam hak akses untuk periode ini.');
    $res = ['name' => 'KPI-Report-' . $period . '.xlsx', 'period' => $period, 'rankGroup' => $rankGroup, 'groupNames' => $groups, 'ranking' => $ranking, 'rankingOnly' => $rankingOnly, 'sheets' => $sheets];
    if ($ranking) { $res['rankingSales'] = kpiExportNationalRows_($source, $period, 'SALES', $rankGroup, $groups); $res['rankingAsm'] = kpiExportNationalRows_($source, $period, 'ASM', $rankGroup, $groups); }
    audit_($u['nik'], 'KPI_XLSX_EXPORT', $period . ' ' . $rankGroup . ' ' . ($rankingOnly ? 'RANKING_ONLY' : implode('|', $detail)));
    return $res;
}

// ---------------- DOS (menu) ----------------
function aggregateBigDOS_(array $r): array {
    $groups = []; foreach ($r['rows'] ?? [] as $x) $groups['k' . $x['big']][] = $x;
    $keys = array_keys($groups); usort($keys, fn($a, $b) => strcmp(substr($a, 1), substr($b, 1))); $out = [];
    foreach ($keys as $k) {
        $it = $groups[$k]; $big = substr($k, 1); $stock = 0; $sellout = 0; $bad = 0; $dealers = []; $low = 0; $okStock = 0; $okSell = 0;
        foreach ($it as $x) { $stock += $x['stock']; $sellout += $x['sellout']; if ($x['status'] === 'PERLU CEK DATA') $bad++; else { $okStock += $x['stock']; $okSell += $x['sellout']; } if ($x['status'] === 'MENIPIS' || $x['status'] === 'HABIS') $low++; $dealers[(string) $x['customer']] = true; }
        $avg = truthy($r['windowReady'] ?? null) ? $sellout / 30 : null; $okAvg = truthy($r['windowReady'] ?? null) ? $okSell / 30 : null;
        $out[] = ['big' => $big, 'region' => $big, 'stock' => $stock, 'sellout' => $sellout, 'avg' => $avg, 'dos' => (truthy($r['covered'] ?? null) && $okAvg > 0) ? $okStock / $okAvg : null, 'dealerCount' => count($dealers), 'typeCount' => count($it), 'low' => $low, 'bad' => $bad];
    }
    return $out;
}
function apiDOS($token, $period = null, $selection = null, $filter = null, $page = null) {
    $source = snapshot_(); $u = guard_($token, false, false, $source);
    $on = onlineSet_(); $pp = []; foreach ($source['meta']['people'] as $p) $pp[(string) $p['nik']] = $p;
    $ids = array_values(array_filter(allowed_($source, $u, $selection), fn($n) => !isOnlinePerson_($pp[(string) $n] ?? null, $on)));
    $db = coverageView_($source, $period, $ids, 'PAIR', $u); $f = is_array($filter) ? $filter : []; $size = pageSizeOf_($f['pageSize'] ?? null);
    if (truthy($db['missingST'] ?? null) || truthy($db['missingSO'] ?? null)) return ['ready' => false, 'message' => 'Menunggu ST dan SO seluruh region dalam pilihan. Pilih cakupan lebih kecil.', 'regionStatus' => $db['regionStatus'] ?? null];
    $top = RegionCore::top($u['role']);
    $r = StockCore::calculate($db, $period, $ids, $f); $r['regionStatus'] = $db['regionStatus'] ?? [];
    if ($top && truthy($r['ready'] ?? null)) $r['regions'] = aggregateBigDOS_($r);
    if (!truthy($r['ready'] ?? null)) return $r;
    if (($f['view'] ?? null) === 'BRAND') { $r['brands'] = sortRecords_($r['brands'], $f['sort'] ?? null, [fn($t) => $t['nama'], fn($t) => $t['brand'], fn($t) => $t['stock'], fn($t) => $t['sellout'], fn($t) => $t['avg'], fn($t) => $t['dos'], fn($t) => $t['low']]); $count = count($r['brands']); }
    else { $r['rows'] = sortRecords_($r['rows'], $f['sort'] ?? null, [fn($t) => $t['nama'], fn($t) => $t['sales'], fn($t) => $t['brand'], fn($t) => $t['type'], fn($t) => $t['stock'], fn($t) => $t['sellout'], fn($t) => $t['avg'], fn($t) => $t['dos'], fn($t) => $t['status']]); $count = count($r['rows']); }
    $n = pageIdx_($page, $count, $size); $r['count'] = $count; $r['page'] = $n; $r['pageSize'] = $size; $r['rows'] = array_slice($r['rows'], $n * $size, $size);
    if ($u['role'] === 'SALES') $r['regions'] = [];
    return $r;
}

/** Peta kelengkapan data (region × bulan), murni informasi untuk Admin. Opsional per brand: ada/tidaknya transaksi brand itu. */
function apiCoverageMatrix($token, $brand = null) {
    $db = snapshot_(); guard_($token, true, false, $db);
    $brand = truthy($brand) ? js_str($brand) : '';
    $parts = $db['parts'] ?? []; $regs = []; $per = [];
    foreach (['ST', 'SO'] as $k) foreach ($parts[$k] ?? [] as $p => $byReg) { $per[$p] = 1; foreach (array_keys($byReg) as $r) $regs[$r] = 1; }
    foreach ($db['meta']['regionList'] ?? [] as $r) $regs[(string) $r] = 1;
    $regs = array_keys($regs); sort($regs, SORT_STRING);
    $last = $per ? max(array_keys($per)) : substr(StockCore::BASE, 0, 7);
    $months = monthSequence_(substr(StockCore::BASE, 0, 7), $last);
    $brands = [];
    foreach ($db['meta']['products'] ?? [] as $x) if (truthy($x['device'] ?? false) && truthy($x['brand'] ?? '')) $brands[(string) $x['brand']] = 1;
    $brands = array_keys($brands); sort($brands, SORT_STRING);
    $pres = ['ST' => [], 'SO' => []];
    if ($brand !== '') {
        foreach ($db['cubes'] ?? [] as $p => $list) foreach ($list as $t) if (($t['brand'] ?? null) === $brand && (float) ($t['q'] ?? 0) != 0) $pres['ST'][$p . '|' . ($t['region'] ?? '')] = 1;
        $prod = $db['meta']['products'] ?? [];
        foreach (stockOutPresence_($db, $brand) as $k => $_) $pres['SO'][$k] = 1;
    }
    $rows = [];
    foreach ($regs as $r) {
        $cells = [];
        foreach ($months as $p) {
            $end = PortalCore::end($p); $c = [];
            foreach (['ST', 'SO'] as $k) {
                $cut = $parts[$k][$p][$r]['cutoff'] ?? null;
                if ($brand !== '') $st = isset($pres[$k][$p . '|' . $r]) ? 'ok' : 'none';
                else $st = !truthy($cut) ? 'none' : (strcmp((string) $cut, $end) >= 0 ? 'ok' : 'part');
                $c[] = $st; $c[] = truthy($cut) ? $cut : null;
            }
            $cells[] = ['st' => $c[0], 'stc' => $c[1], 'so' => $c[2], 'soc' => $c[3]];
        }
        $rows[] = ['region' => $r, 'cells' => $cells];
    }
    return ['brand' => $brand, 'brands' => $brands, 'months' => $months, 'rows' => $rows, 'base' => StockCore::BASE];
}

// ---------------- Export Summary (XLSX) ----------------
function exportGrowth_($now, $prev, $available) {
    if (!truthy($available) || $prev === null) return null;
    if ($prev == 0) return $now == 0 ? 0 : null;
    return ($now - $prev) / $prev * 100;
}
function apiExportSummary($token, $period = null, $selection = null) {
    $source = snapshot_(); $u = exportGuard_($token, 'SUMMARY', $source); $ids = allowed_($source, $u, $selection); $report = apiDashboard($token, $period, $selection);
    $targetNOO = combinedTarget_(array_map(fn($b) => $b['targetNOO'] ?? null, $report['brands']));
    $pa = truthy($report['previousAvailable'] ?? null);
    // DSM v5: sell out pembanding dihitung sampai tanggal yang sama (MTD vs LMTD), bukan bulan lalu penuh.
    $prevSo = null;
    if ($pa) {
        $pv = coverageView_($source, $report['previousPeriod'], $ids, 'PAIR', $u); $pc = (string) $report['previousCutoff'];
        if (isset($pv['coverage'][$report['previousPeriod']]) && strcmp($pv['coverage'][$report['previousPeriod']]['cutoff'], $pc) > 0) { $pv['coverage'][$report['previousPeriod']]['cutoff'] = $pc; $pv['_vid'] = PortalCore::vid($pv) . '|cut' . $pc; }
        $prevSo = StockCore::monthly($pv, $report['previousPeriod'], $ids)['qty'];
    }
    $sel = (is_string($selection) || is_int($selection)) && truthy($selection) && $selection !== 'ALL' ? js_find($source['meta']['people'], fn($p) => $p['nik'] === $selection) : null;
    $scope = $sel ? ($sel['role'] . ' · ' . $sel['nama']) : ((RegionCore::top($u['role']) || $u['role'] === 'SUPER ADMIN') ? 'NASIONAL' : ($u['role'] === 'ADMIN' ? js_or(implode(', ', $u['adminRegions'] ?? []), 'CAKUPAN ADMIN') : $u['role'] . ' · ' . $u['nama']));
    // DSM v5: target tetap ditampilkan walau ada sales yang belum punya target (dulu jadi kosong); kekurangannya dicatat di baris CATATAN TARGET.
    $missingT = [];
    $tv = function ($t) use (&$missingT) { if (!$t || !truthy($t['exists'] ?? null)) return null; foreach ($t['missing'] ?? [] as $m) $missingT[(string) $m] = 1; return numJs_(js_or($t['value'] ?? null, 0)); };
    $ach = fn($v, $t) => ($t && $t > 0) ? $v / $t * 100 : null;
    $T = $report['total']; $P = $report['previous'] ?? [];
    $tST = $tv($report['brandTotals']['target'] ?? null); $tDA = $tv($report['brandTotals']['targetDA'] ?? null); $tNOO = $tv($targetNOO);
    $rows = [
        ['SELL THRU', $tST, $T['qty'], $ach($T['qty'], $tST), $pa ? $P['qty'] : null, exportGrowth_($T['qty'], $P['qty'] ?? null, $pa)],
        ['DEALER AKTIF', $tDA, $T['da'], $ach($T['da'], $tDA), $pa ? $P['da'] : null, exportGrowth_($T['da'], $P['da'] ?? null, $pa)],
        ['NOO', $tNOO, $T['noo'], $ach($T['noo'], $tNOO), $pa ? $P['noo'] : null, exportGrowth_($T['noo'], $P['noo'] ?? null, $pa)],
        ['SELL OUT', null, $T['sellout'], null, $prevSo, exportGrowth_($T['sellout'], $prevSo, $pa && $prevSo !== null)],
        ['OMZET', null, $T['omzet'], null, $pa ? $P['omzet'] : null, exportGrowth_($T['omzet'], $P['omzet'] ?? null, $pa)],
    ];
    $ss = SpreadsheetApp::create('TMP_SUMMARY_' . now_ms());
    $ss->setSpreadsheetTimeZone('Asia/Jakarta'); $sh = $ss->getSheets()[0]; $sh->setName('Summary');
    $meta = [['PERIODE', $report['period']], ['CAKUPAN', $scope], ['ST LENGKAP SAMPAI', js_or($report['coverage']['cutoff'] ?? null, '')], ['PEMBANDING', $pa ? $report['previousPeriod'] . ' s.d. ' . $report['previousCutoff'] : 'Belum tersedia']];
    if ($missingT) $meta[] = ['CATATAN TARGET', count($missingT) . ' sales belum punya target di sistem; TARGET & % ACHV dihitung dari sales yang sudah punya target.'];
    $meta[] = ['DIEKSPOR', gmdate('Y-m-d H:i:s', time() + 7 * 3600) . ' WIB'];
    $head = ['METRIK', 'TARGET', 'AKTUAL', '% ACHV', 'BULAN LALU / PEMBANDING', 'GROWTH %']; $mc = count($meta);
    $sh->getRange(1, 1, $mc, 2)->setValues($meta)->setFontFamily('Calibri')->setFontSize(11);
    $sh->getRange($mc + 2, 1, 1, count($head))->setValues([$head])->setFontFamily('Calibri')->setFontSize(11)->setFontWeight('bold')->setBackground('#10394E')->setFontColor('#ffffff');
    $sh->getRange($mc + 3, 1, count($rows), count($head))->setValues($rows)->setFontFamily('Calibri')->setFontSize(11);
    $sh->getRange($mc + 3, 2, count($rows), 5)->setNumberFormat('#,##0.00'); $sh->setFrozenRows($mc + 2); $sh->autoResizeColumns(1, count($head));
    $br = $ss->insertSheet('Per Brand');
    $bhead = ['BRAND', 'TARGET ST', 'AKTUAL ST', '% ACHV ST', 'TARGET DA', 'AKTUAL DA', '% ACHV DA', 'TARGET NOO', 'AKTUAL NOO', 'SELL OUT', 'OMZET'];
    $brows = array_map(function ($b) use ($tv, $ach) { $c = $b['current']; $t = $tv($b['target'] ?? null); $d = $tv($b['targetDA'] ?? null);
        return [$b['brand'], $t, $c['qty'], $ach($c['qty'], $t), $d, $c['da'], $ach($c['da'], $d), $tv($b['targetNOO'] ?? null), $c['noo'], $c['sellout'], $c['omzet']]; }, $report['brands']);
    $br->getRange(1, 1, 1, count($bhead))->setValues([$bhead])->setFontFamily('Calibri')->setFontSize(11)->setFontWeight('bold')->setBackground('#10394E')->setFontColor('#ffffff');
    if ($brows) $br->getRange(2, 1, count($brows), count($bhead))->setValues($brows)->setFontFamily('Calibri')->setFontSize(11);
    $br->setFrozenRows(1); $br->autoResizeColumns(1, count($bhead)); SpreadsheetApp::flush();
    $blob = exportGoogleSheetXlsx_($ss); audit_($u['nik'], 'SUMMARY_XLSX_EXPORT', $report['period'] . ' ' . $scope);
    return ['name' => 'Summary-' . $report['period'] . '.xlsx', 'mime' => XLSX_MIME, 'base64' => base64_encode($blob->getBytes())];
}

// ---------------- Raw transaction export (Admin / Super Admin) ----------------
function txRawAdmin_($token, array $db): array {
    $u = guard_($token, false, false, $db);
    if (!in_array($u['role'], ['ADMIN','SUPER ADMIN'], true)) fail_('Download transaksi RAW hanya untuk Admin / Super Admin.');
    return $u;
}
function txRawProductMap_(): array {
    $m=[]; foreach(optionalTable_('Master_Product') as $r){$k=text_($r['KODE BARANG']??null);if($k!=='')$m[$k]=$r;} return $m;
}
function txRawPerson_(array $db,string $nik): array {
    $p=PortalCore::person($db['meta']['people'],$nik); return $p?:['nik'=>$nik,'nama'=>'','wilayah'=>'','region'=>'','portfolio'=>''];
}
function txRawMainBrand_(array $db,string $sku): string { return text_($db['meta']['products'][$sku]['brand'] ?? null); }
function txRawSourceBrand_(array $db,string $sku): string { return text_($db['meta']['products'][$sku]['source'] ?? null); }
/** Resolver dealer RAW: transaksi lama/campuran bisa menyimpan ID_DEALER unik atau KODE CUSTOMER induk.
 * Prioritas exact ID_DEALER agar NAMA DEALER menunjuk outlet yang benar; fallback ke kode induk/meta lama. */
function txRawDealerInfo_(array $db,string $id): array {
    static $uniq=null,$induk=null;
    if($uniq===null){
        $uniq=[];$induk=[];
        foreach(optionalTable_('Master_Customer') as $r){
            $u=text_($r['ID_DEALER']??null);$k=text_($r['KODE CUSTOMER']??null);
            $namaOutlet=js_or(text_($r['NAMA CUSTOMER']??null),text_($r['NAMA INDUK CUSTOMER']??null));
            $namaInduk=js_or(text_($r['NAMA INDUK CUSTOMER']??null),$namaOutlet);
            $base=['region'=>text_($r['REGION']??null),'kota'=>text_($r['KOTA']??null),'induk'=>$k,'nama_induk'=>$namaInduk];
            if($u!=='')$uniq[$u]=array_merge($base,['nama'=>$namaOutlet,'uniq'=>$u]);
            if($k!==''&&!isset($induk[$k]))$induk[$k]=array_merge($base,['nama'=>$namaInduk,'uniq'=>$u]);
        }
    }
    if(isset($uniq[$id]))return $uniq[$id];
    if(isset($induk[$id]))return $induk[$id];
    $k=custInduk_($db['meta'],$id);
    if(isset($induk[$k]))return $induk[$k];
    $c=$db['meta']['customers'][$k]??[];
    return ['nama'=>text_($c['nama']??null),'region'=>text_($c['region']??null),'kota'=>text_($c['kota']??null),'induk'=>$k,'nama_induk'=>text_($c['nama']??null),'uniq'=>$id];
}
function txRawDealerName_(array $db,string $id): string { return text_(txRawDealerInfo_($db,$id)['nama']??null); }
function txRawDealerRegion_(array $db,string $id): string { return text_(txRawDealerInfo_($db,$id)['region']??null); }
function txRawDealerParentCode_(array $db,string $id): string { return text_(txRawDealerInfo_($db,$id)['induk']??null); }
function txRawDealerParentName_(array $db,string $id): string { return text_(txRawDealerInfo_($db,$id)['nama_induk']??null); }
function txRawQueryMatch_(array $db,string $id,string $q): bool {
    if($q==='')return true; $d=txRawDealerInfo_($db,$id);$s=js_upper($id.' '.text_($d['induk']??null).' '.text_($d['nama']??null)); return mb_strpos($s,js_upper($q))!==false;
}
function txRawAddSheet_(Wb $wb,string $name,array $heads,array $rows,array $textHeads=[]): void {
    $sh=$wb->insertSheet($name); $n=max(1,count($rows)+1); $c=count($heads);
    $sh->getRange(1,1,1,$c)->setValues([$heads])->setBackground('#10394E')->setFontColor('#ffffff')->setFontWeight('bold')->setFontFamily('Calibri')->setFontSize(10);
    if($rows)$sh->getRange(2,1,count($rows),$c)->setValues($rows)->setFontFamily('Calibri')->setFontSize(10);
    foreach($heads as $i=>$h) if(in_array($h,$textHeads,true) && $rows) $sh->getRange(2,$i+1,count($rows),1)->setNumberFormat('@');
    $sh->setFrozenRows(1); $sh->setAutoFilter('A1:'.col_letters_($c).$n); $sh->autoResizeColumns(1,$c);
}
function txRawSummarySheet_(Wb $wb,array $meta): void {
    $sh=$wb->getSheets()[0]; $sh->setName('RINGKASAN');
    $rows=[['DOWNLOAD TRANSAKSI RAW',''],['Dibuat',jkt_date('Y-m-d H:i:s')],['User',$meta['user']],['Periode',$meta['period']],['Tanggal',$meta['from'].' s.d. '.$meta['to']],['Jenis',$meta['kind']],['Cakupan',$meta['scope']],['Brand',$meta['brand']],['Dealer / kode',$meta['customer']?:'Semua'],['Big Region PIC',$meta['picBig']],['ST cutoff',$meta['stCut']?:'—'],['SO cutoff',$meta['soCut']?:'—'],['Baris ST',$meta['stRows']],['QTY ST',$meta['stQty']],['Omzet ST',$meta['stAmount']],['Baris SO',$meta['soRows']],['QTY SO',$meta['soQty']],['Catatan','ST memakai NIK transaksi untuk hak akses. SO memakai PIC Dealer × Brand pada tanggal transaksi karena sumber SO tidak memiliki NIK Sales.']];
    $sh->getRange(1,1,count($rows),2)->setValues($rows)->setFontFamily('Calibri')->setFontSize(11); $sh->getRange(1,1,1,2)->setBackground('#10394E')->setFontColor('#fff')->setFontWeight('bold'); $sh->setColumnWidth(1,180);$sh->setColumnWidth(2,540);
}
function txRawMonths_(string $from,string $to): array {
    $a=substr($from,0,7);$b=substr($to,0,7);$out=[];$cur=$a;
    for($guard=0;$guard<120;$guard++){
        $out[]=$cur;if($cur===$b)break;
        [$y,$m]=array_map('intval',explode('-',$cur));$m++;if($m>12){$m=1;$y++;}$cur=sprintf('%04d-%02d',$y,$m);
    }
    if(!$out || end($out)!==$b)fail_('Rentang tanggal terlalu panjang.');
    return $out;
}
function apiTransactionExportRaw($token,$period=null,$selection=null,$kind='BOTH',$from=null,$to=null,$brand='ALL',$customer=null,$picBig='ALL') {
    @ini_set('memory_limit','2048M'); set_time_limit(300);
    require_once __DIR__.'/sheetio.php';
    $db=snapshot_(); $u=txRawAdmin_($token,$db); $period=PortalCore::month($period); $kind=js_upper(text_($kind)); if(!in_array($kind,['ST','SO','BOTH'],true))fail_('Jenis export tidak valid.');
    $defaultCut=text_($db['coverage'][$period]['cutoff']??($db['so']['coverage'][$period]['cutoff']??PortalCore::end($period)));
    $from=PortalCore::iso(js_or($from,$period.'-01'));$to=PortalCore::iso(js_or($to,$defaultCut));
    if($from>$to)fail_('Rentang tanggal tidak valid.');
    $months=txRawMonths_($from,$to);
    $brand=js_upper(js_or(text_($brand),'ALL'));$customer=text_($customer);$picBig=js_upper(js_or(text_($picBig),'ALL'));
    $ids=allowed_($db,$u,$selection);$idSet=js_set($ids);$prod=txRawProductMap_();$dba=dbaIndex_();
    $lineageReason=in_array($u['role']??'',['ADMIN','SUPER ADMIN'],true);
    $stRows=[];$soRows=[];$stQty=0;$stAmt=0;$soQty=0;$stPeriods=[];$soPeriods=[];
    foreach($months as $m){
        $stCut=text_($db['coverage'][$m]['cutoff']??'');
        $soCut=text_($db['so']['coverage'][$m]['cutoff']??'');
        if($kind!=='SO' && isset($db['raw'][$m])){
            $stPeriods[]=$m;
            foreach(txEffRaw_($db['meta'],dataRows_((string)$db['raw'][$m]),true) as $t){
                $date=text_($t['date']??null);if($date<$from||$date>$to||($stCut!==''&&$date>$stCut))continue;$nik=text_($t['nik']??null);if(!in_set($idSet,$nik))continue;$sku=text_($t['sku']??null);$main=txRawMainBrand_($db,$sku);if($brand!=='ALL'&&js_upper($main)!==$brand)continue;$cust=text_($t['customer']??null);if(!txRawQueryMatch_($db,$cust,$customer))continue;
                $pic=dealerBrandOwner_($db,$cust,$main,$date,$dba);if($picBig!=='ALL'&&js_upper($pic['big'])!==$picBig)continue;$p=txRawPerson_($db,$nik);$mp=$prod[$sku]??[];$q=(float)($t['qty']??0);$amt=(float)($t['amount']??0);$stQty+=$q;$stAmt+=$amt;
                $stRows[]=[$date,text_($t['company']??null),text_($t['doc']??null),text_($t['sj']??null),$cust,txRawDealerName_($db,$cust),txRawDealerParentCode_($db,$cust),txRawDealerParentName_($db,$cust),txRawDealerRegion_($db,$cust),text_($t['region']??null),$nik,text_($p['nama']??null),text_($p['wilayah']??null),$main,txRawSourceBrand_($db,$sku),$sku,text_($mp['NAMA BARANG']??null),text_($mp['KATEGORI PRODUK']??null),text_($mp['TYPE']??null),$q,$amt,text_($t['line']??null),text_($t['ref']??null),$pic['nik'],text_($pic['nama']),text_($pic['big']),text_($pic['region']),text_($pic['source']),text_($t['rawNik']??$nik),text_(txRawPerson_($db,text_($t['rawNik']??$nik))['nama']??null),text_($t['txMarker']??null),$lineageReason?text_($t['txReason']??null):''];
                if(count($stRows)>150000)fail_('Hasil ST lebih dari 150.000 baris. Persempit tanggal/brand/dealer sebelum download.');
            }
        }
        if($kind!=='ST' && isset($db['so']['raw'][$m])){
            $soPeriods[]=$m;
            foreach(dataRows_((string)$db['so']['raw'][$m]) as $t){
                $date=text_($t['date']??null);if($date<$from||$date>$to||($soCut!==''&&$date>$soCut))continue;$sku=text_($t['sku']??null);$main=txRawMainBrand_($db,$sku);if($brand!=='ALL'&&js_upper($main)!==$brand)continue;$cust=text_($t['customer']??null);if(!txRawQueryMatch_($db,$cust,$customer))continue;$pic=dealerBrandOwner_($db,$cust,$main,$date,$dba);if($pic['nik']===''||!in_set($idSet,$pic['nik']))continue;if($picBig!=='ALL'&&js_upper($pic['big'])!==$picBig)continue;$mp=$prod[$sku]??[];$q=(float)($t['qty']??0);$soQty+=$q;
                $soRows[]=[$date,$cust,txRawDealerName_($db,$cust),txRawDealerParentCode_($db,$cust),txRawDealerParentName_($db,$cust),txRawDealerRegion_($db,$cust),text_($t['region']??null),$main,txRawSourceBrand_($db,$sku),$sku,text_($mp['NAMA BARANG']??null),text_($mp['KATEGORI PRODUK']??null),text_($mp['TYPE']??null),$q,$pic['nik'],text_($pic['nama']),text_($pic['big']),text_($pic['region']),text_($pic['source'])];
                if(count($soRows)>150000)fail_('Hasil SO lebih dari 150.000 baris. Persempit tanggal/brand/dealer sebelum download.');
            }
        }
    }
    $rangePeriod=substr($from,0,7)===substr($to,0,7)?substr($from,0,7):substr($from,0,7).' s.d. '.substr($to,0,7);
    $stCov=$stPeriods?'Mengikuti cutoff aktif tiap bulan ('.count($stPeriods).' periode)':'—';$soCov=$soPeriods?'Mengikuti cutoff aktif tiap bulan ('.count($soPeriods).' periode)':'—';
    $wb=SpreadsheetApp::create('Transaksi-RAW-'.str_replace('-','',$from).'-'.str_replace('-','',$to).'-'.jkt_date('Ymd-His'));$wb->setSpreadsheetTimeZone('Asia/Jakarta');
    txRawSummarySheet_($wb,['user'=>$u['nik'].' — '.text_($u['nama']??null),'period'=>$rangePeriod,'from'=>$from,'to'=>$to,'kind'=>$kind,'scope'=>js_or(text_($selection),'ALL'),'brand'=>$brand,'customer'=>$customer,'picBig'=>$picBig,'stCut'=>$stCov,'soCut'=>$soCov,'stRows'=>count($stRows),'stQty'=>$stQty,'stAmount'=>$stAmt,'soRows'=>count($soRows),'soQty'=>$soQty]);
    if($kind!=='SO')txRawAddSheet_($wb,'SELL_THRU_RAW',['TANGGAL','BADAN USAHA','NO TRANSAKSI','NO SJ','KODE DEALER','NAMA DEALER','KODE INDUK DEALER','NAMA INDUK DEALER','LOKASI DEALER','REGION TRANSAKSI','NIK SALES TRANSAKSI','NAMA SALES TRANSAKSI','BIG REGION SALES','BRAND UTAMA','KLASIFIKASI','KODE PRODUK','NAMA PRODUK','KATEGORI','TYPE','QTY','OMZET','ID BARIS','REF TRANSAKSI','NIK PIC BRAND','NAMA PIC BRAND','BIG REGION PIC','REGION PIC','SUMBER PIC','NIK SALES FILE ST','NAMA SALES FILE ST','MARKER ATRIBUSI','ALASAN KOREKSI'],$stRows,['NO TRANSAKSI','NO SJ','KODE DEALER','KODE INDUK DEALER','NIK SALES TRANSAKSI','KODE PRODUK','ID BARIS','REF TRANSAKSI','NIK PIC BRAND']);
    if($kind!=='ST')txRawAddSheet_($wb,'SELL_OUT_RAW',['TANGGAL','KODE DEALER','NAMA DEALER','KODE INDUK DEALER','NAMA INDUK DEALER','LOKASI DEALER','REGION DATA','BRAND UTAMA','KLASIFIKASI','KODE PRODUK','NAMA PRODUK','KATEGORI','TYPE','QTY SO','NIK PIC BRAND','NAMA PIC BRAND','BIG REGION PIC','REGION PIC','SUMBER PIC'],$soRows,['KODE DEALER','KODE INDUK DEALER','KODE PRODUK','NIK PIC BRAND']);
    SpreadsheetApp::flush();$blob=exportGoogleSheetXlsx_($wb);return['name'=>$wb->name.'.xlsx','mime'=>XLSX_MIME,'base64'=>base64_encode($blob->getBytes())];
}
