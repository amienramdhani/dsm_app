<?php
/** Menu data: Transaksi, Tim, Detail dealer, Stok dealer (Code.gs / Mobile.gs). */
require_once __DIR__ . '/common.php';

function numJs_($v) {
    if ($v === null || $v === false) return 0; if ($v === true) return 1;
    if (is_int($v) || is_float($v)) return $v;
    $s = trim(js_str($v)); if ($s === '') return 0;
    return is_numeric($s) ? $s + 0 : NAN;
}
function sortRecords_(array $rows, $sort, array $getters): array {
    $sort = is_array($sort) ? $sort : null;
    if (!$sort) return $rows;
    $col = numJs_($sort['column'] ?? null);
    if (is_nan((float) $col) || floor($col) != $col || !isset($getters[(int) $col])) return $rows;
    $get = $getters[(int) $col]; $dir = (($sort['dir'] ?? null) === 'desc') ? -1 : 1;
    return js_sort($rows, function ($a, $b) use ($get, $dir) {
        $x = $get($a); $y = $get($b);
        if ($x === null || $x === '') return ($y === null || $y === '') ? 0 : 1;
        if ($y === null || $y === '') return -1;
        return $dir * ((is_int($x) || is_float($x)) && (is_int($y) || is_float($y)) ? $x - $y : js_locale_compare(js_str($x), js_str($y), 'id', true, true));
    });
}
function pageSizeOf_($v): int { $n = numJs_($v); return ($n == 50 || $n == 100 || $n == 200) ? (int) $n : 100; }
function pageIdx_($page, int $total, int $size): int {
    $n = numJs_($page); $req = max(0, (int) floor(is_nan((float) $n) || !$n ? 0 : $n));
    return min($req, max(0, (int) ceil($total / $size) - 1));
}

function apiTransactions($token, $period = null, $selection = null, $customer = null, $page = null, $dateFrom = null, $dateTo = null, $sort = null, $pageSize = null) {
    $source = snapshot_(); $u = guard_($token, false, false, $source); $ids = allowed_($source, $u, $selection);
    $db = coverageView_($source, $period, $ids, 'ST', $u); $size = pageSizeOf_($pageSize);
    PortalCore::month($period); $cut = js_or($db['coverage'][$period]['cutoff'] ?? null, PortalCore::end($period));
    $from = PortalCore::iso(js_or($dateFrom, $period . '-01')); $to = PortalCore::iso(js_or($dateTo, $cut));
    if (substr($from, 0, 7) !== $period || substr($to, 0, 7) !== $period || $from > $to || $to > $cut) fail_('Rentang tanggal harus dalam bulan pilihan, urut, dan tidak melewati cutoff.');
    if (!isset($db['raw'][$period]) || !truthy($db['raw'][$period])) return ['rows' => [], 'total' => 0, 'page' => 0, 'pageSize' => $size, 'sourceRows' => 0, 'totals' => ['qty' => 0, 'amount' => 0], 'from' => $from, 'to' => $to];
    $set = js_set($ids); $sel = isset($db['selectedRegions']) && $db['selectedRegions'] !== null && $db['selectedRegions'] !== false ? $db['selectedRegions'] : null;
    $raw = js_filter(txEffRaw_($db['meta'], dataRows_((string) $db['raw'][$period])), fn($t) => in_set($set, $t['nik'] ?? null) && (!truthy($t['region'] ?? null) || $sel === null || in_array($t['region'], $sel, true)) && (!truthy($customer) || ($t['customer'] ?? null) === $customer || custInduk_($db['meta'], $t['customer'] ?? '') === custInduk_($db['meta'], $customer)) && $t['date'] >= $from && $t['date'] <= $to);
    $all = PortalCore::groupedTransactions($raw, $db['meta']); $n = pageIdx_($page, count($all), $size);
    $all = sortRecords_($all, $sort, [fn($r) => $r['date'], fn($r) => $r['sj'], fn($r) => $r['nama'], fn($r) => $r['brand'], fn($r) => $r['type'], fn($r) => $r['qty'], fn($r) => $r['amount']]);
    $q = 0; $a = 0; foreach ($raw as $t) { $q += $t['qty']; $a += $t['amount']; } $dq = 0; foreach ($all as $g) $dq += (float) ($g['deviceQty'] ?? 0);
    return ['total' => count($all), 'page' => $n, 'pageSize' => $size, 'from' => $from, 'to' => $to, 'totals' => ['qty' => $q, 'amount' => PortalCore::round($a), 'deviceQty' => $dq], 'sourceRows' => count($raw), 'rows' => array_slice($all, $n * $size, $size)];
}
function apiTransactionGroups($token, $period = null, $selection = null, $page = null, $from = null, $to = null, $sort = null, $pageSize = null) {
    $source = snapshot_(); $u = guard_($token, false, false, $source); $ids = allowed_($source, $u, $selection);
    $db = coverageView_($source, $period, $ids, 'ST', $u); $size = pageSizeOf_($pageSize);
    PortalCore::month($period); $cut = js_or($db['coverage'][$period]['cutoff'] ?? null, PortalCore::end($period));
    $from = PortalCore::iso(js_or($from, $period . '-01')); $to = PortalCore::iso(js_or($to, $cut));
    if (substr($from, 0, 7) !== $period || substr($to, 0, 7) !== $period || $from > $to || $to > $cut) fail_('Rentang tanggal tidak valid.');
    $set = js_set($ids); $sel = isset($db['selectedRegions']) && $db['selectedRegions'] ? $db['selectedRegions'] : null;
    $raw = (isset($db['raw'][$period]) && truthy($db['raw'][$period])) ? js_filter(txEffRaw_($db['meta'], dataRows_((string) $db['raw'][$period])), fn($t) => in_set($set, $t['nik'] ?? null) && (!truthy($t['region'] ?? null) || $sel === null || in_array($t['region'], $sel, true)) && $t['date'] >= $from && $t['date'] <= $to) : [];
    $groups = [];
    foreach (PortalCore::groupedTransactions($raw, $db['meta']) as $t) {
        $key = js_stringify([$t['date'], $t['customer']]);
        if (!isset($groups[$key])) $groups[$key] = ['date' => $t['date'], 'customer' => $t['customer'], 'nama' => $t['nama'], 'rows' => [], 'qty' => 0, 'amount' => 0];
        $groups[$key]['rows'][] = $t; $groups[$key]['qty'] += $t['qty']; $groups[$key]['amount'] += $t['amount'];
    }
    $all = js_sort(array_values($groups), fn($a, $b) => lc($b['date'], $a['date']) ?: lc($a['nama'], $b['nama']));
    $all = sortRecords_($all, $sort, [fn($g) => $g['date'], fn($g) => $g['nama']]);
    $n = pageIdx_($page, count($all), $size); $q = 0; $a = 0; foreach ($raw as $r) { $q += $r['qty']; $a += $r['amount']; } $dq = 0; foreach ($groups as $g) foreach ($g['rows'] as $t) $dq += (float) ($t['deviceQty'] ?? 0);
    return ['rows' => array_slice($all, $n * $size, $size), 'total' => count($all), 'page' => $n, 'pageSize' => $size, 'from' => $from, 'to' => $to, 'totals' => ['qty' => $q, 'amount' => $a, 'deviceQty' => $dq]];
}

function apiDealerStock($token, $period = null, $selection = null, $customer = null) {
    $source = snapshot_(); $u = guard_($token, false, false, $source); $ids = allowed_($source, $u, $selection);
    if (!StockCore::canSee($source, $ids, $customer)) fail_('Dealer di luar cakupan.');
    $db = coverageView_($source, $period, $ids, 'PAIR', $u);
    if (!empty($db['missingST']) || !empty($db['missingSO'])) return ['ready' => false, 'message' => 'Menunggu kelengkapan ST / SO.'];
    $r = StockCore::calculate($db, $period, $ids, ['query' => $customer]);
    if (truthy($r['ready'] ?? false)) $r['rows'] = js_filter($r['rows'], fn($t) => js_or($t['customer'] ?? null, $t['id'] ?? null) === $customer);
    return ['ready' => $r['ready'] ?? null, 'message' => $r['message'] ?? null, 'asof' => $r['asof'] ?? null, 'stCut' => $r['stCut'] ?? null, 'soCut' => $r['soCut'] ?? null, 'rows' => $r['rows'] ?? []];
}

function apiDealerDetail($token, $period = null, $selection = null, $customer = null) {
    $source = snapshot_(); $u = guard_($token, false, false, $source); $ids = allowed_($source, $u, $selection);
    $db = coverageView_($source, $period, $ids, 'ST', $u); $set = js_set($ids); $known = $db['meta']['customers'][$customer] ?? null;
    $ownerDate = PortalCore::iso($db['coverage'][$period]['cutoff'] ?? null) ?: PortalCore::end($period);
    $dbaIdx = dbaIndex_(); $picByBrand = []; $brandOwnedInScope = $u['role'] === 'TELEMARKETING' && tmHasDealer_(tmLivePairs_($u['nik']), (string)$customer);
    foreach (dbaPrimaryBrands_() as $picBrand) {
        if ($u['role'] === 'TELEMARKETING' && !tmHasDealer_(tmLivePairs_($u['nik']), (string)$customer, $picBrand)) continue;
        $o = dealerBrandOwner_($db, (string)$customer, $picBrand, $ownerDate, $dbaIdx);
        $picByBrand[] = ['brand'=>$picBrand,'nik'=>$o['nik'],'nama'=>$o['nama'],'bigRegion'=>$o['big'],'region'=>$o['region'],'source'=>$o['source'],'effectiveFrom'=>$o['assignment']['from'] ?? ''];
        if ($o['nik'] !== '' && in_set($set, $o['nik'])) $brandOwnedInScope = true;
    }
    $history = [];
    foreach (js_keys($db['cubes']) as $p) if (strcmp((string) $p, $period) <= 0) foreach (PortalCore::slice($db, (string) $p, $ids) as $r) if ($r['customer'] === $customer) $history[] = $r;
    $soHistory = false;
    foreach (stockMoves_($db, null, PortalCore::end(PortalCore::month($period)), ['customer' => (string) $customer, 'out' => true]) as $t) if ($t['customer'] === $customer && substr($t['date'], 0, 7) <= $period && truthy($t['outQty']) && StockCore::canSee($db, $ids, $customer)) { $soHistory = true; break; }
    if ($u['role'] === 'TELEMARKETING' && !tmHasDealer_(tmLivePairs_($u['nik']), (string)$customer)) fail_('Dealer di luar penugasan.');
    if (!($known && in_set($set, $known['nik'] ?? null)) && !$brandOwnedInScope && !$history && !$soHistory) fail_('Dealer tidak tersedia dalam cakupan Anda.');
    $rows = js_filter(PortalCore::slice($db, $period, $ids), fn($r) => $r['customer'] === $customer);
    $bs = []; foreach ($db['meta']['products'] as $p) $bs[] = $p['brand']; $brands = array_values(array_unique($bs)); sort($brands, SORT_STRING);
    $current = PortalCore::metrics($rows, $db, $period);
    $ds = []; foreach ($history as $r) if ($r['q'] > 0) $ds[] = $r['date']; sort($ds, SORT_STRING); $last = $ds ? end($ds) : null;
    $current['sellout'] = StockCore::monthly($db, $period, $ids, $customer)['qty'];
    $prev = PortalCore::prev($period); $hasPrev = false; foreach ($history as $r) if (substr($r['date'], 0, 7) === $prev && $r['q'] > 0) { $hasPrev = true; break; }
    return ['id' => $customer, 'nama' => js_or($known['nama'] ?? null, $customer . ' (belum di master)'), 'alamat' => js_or($known['alamat'] ?? null, ''), 'kota' => js_or($known['kota'] ?? null, ''), 'region' => js_or($known['region'] ?? null, ''), 'hp' => js_or($known['hp'] ?? null, ''),
        'last' => $last, 'jeda' => $last ? (int) floor(iso_days($db['coverage'][$period]['cutoff']) - iso_days($last)) : null,
        'status' => $current['qty'] >= 2 ? 'AKTIF' : ($current['qty'] > 0 ? 'BELUM MINIMUM DA' : ($hasPrev ? 'DA BULAN LALU' : 'BELUM ORDER')),
        'current' => $current, 'picByBrand'=>$picByBrand,
        'brands' => array_map(fn($brand) => array_merge(['brand' => $brand], PortalCore::metrics($rows, $db, $period, $brand), ['sellout' => StockCore::monthly($db, $period, $ids, $customer, $brand)['qty']]), $brands)];
}

function apiTeam($token, $period = null, $level = null, $brand = null, $selection = null, $filters = null) {
    $source = snapshot_(); $u = guard_($token, false, false, $source); $people = $source['meta']['people'];
    $me = js_find($people, fn($p) => $p['nik'] === $u['nik']);
    $ids = ($u['role'] === 'SALES' && $me && truthy($me['atasan'] ?? null)) ? array_map(fn($p) => $p['nik'], js_filter($people, fn($p) => truthy($p['sales'] ?? null) && ($p['atasan'] ?? null) === $me['atasan'])) : allowed_($source, $u, $selection);
    $db = coverageView_($source, $period, $ids, 'ST', $u); $areaLevel = in_array($level, ['BIG REGION', 'REGION'], true);
    if (!in_array($level, ['SALES', 'ASM', 'RGM', 'BIG REGION', 'REGION'], true) || ($u['role'] === 'SALES' && $level !== 'SALES') || (!$areaLevel && $u['role'] !== 'SALES' && (PortalCore::LEVEL[$level] ?? NAN) >= (PortalCore::LEVEL[$u['role']] ?? NAN))) fail_('Tingkat tim tidak diizinkan.');
    $filters = is_array($filters) ? $filters : [];
    $big = js_str(js_or($filters['big'] ?? null, 'ALL')); $region = js_str(js_or($filters['region'] ?? null, 'ALL')); $sub = js_str(js_or($filters['sub'] ?? null, 'ALL'));
    $set = js_set($ids);
    $sales = array_values(js_filter($db['meta']['people'], fn($p) => in_set($set, $p['nik']) && truthy($p['sales'] ?? null) && ($big === 'ALL' || ($p['wilayah'] ?? null) === $big) && ($region === 'ALL' || js_or($p['portfolio'] ?? null, $p['region'] ?? null) === $region) && ($sub === 'ALL' || js_or($p['portfolio'] ?? null, $p['region'] ?? null) === $sub)));
    $salesIds = array_map(fn($p) => $p['nik'], $sales); $salesSet = js_set($salesIds); $groups = [];
    if ($areaLevel) {
        $buckets = [];
        foreach ($sales as $p) { $name = $level === 'BIG REGION' ? js_or($p['wilayah'] ?? null, 'LAINNYA') : js_or($p['portfolio'] ?? null, js_or($p['region'] ?? null, 'LAINNYA')); $buckets[(string) $name][] = $p['nik']; }
        $names = js_keys($buckets); usort($names, fn($a, $b) => js_locale_compare((string) $a, (string) $b, 'id'));
        foreach ($names as $name) {
            $name = (string) $name; $bs = js_set($buckets[$name]); $first = js_find($sales, fn($p) => in_set($bs, $p['nik']));
            $groups[] = ['nik' => 'AREA:' . $name, 'nama' => $name, 'region' => $level === 'BIG REGION' ? 'Nasional' : js_or($first['wilayah'] ?? null, ''), 'role' => $level, 'boss' => $level === 'BIG REGION' ? 'Nasional' : js_or($first['wilayah'] ?? null, '—'), 'bossRole' => $level === 'BIG REGION' ? 'CAKUPAN' : 'BIG REGION', 'acting' => false, 'team' => $buckets[$name]];
        }
    } else {
        foreach ($db['meta']['people'] as $p) {
            if ($level === 'SALES' ? !in_set($salesSet, $p['nik']) : !($p['role'] === $level && js_some(PortalCore::scope($db['meta']['people'], $p['nik']), fn($id) => in_set($salesSet, $id)))) continue;
            $team = $level === 'SALES' ? [$p['nik']] : js_filter(PortalCore::scope($db['meta']['people'], $p['nik']), fn($id) => in_set($salesSet, $id));
            $boss = ($p['role'] === 'ASM' && $level === 'SALES') ? $p : js_find($db['meta']['people'], fn($x) => $x['nik'] === ($p['atasan'] ?? null));
            $groups[] = ['nik' => $p['nik'], 'nama' => $p['nama'], 'region' => $level === 'SALES' ? ($p['portfolio'] ?? null) : ($p['region'] ?? null), 'role' => $p['role'], 'boss' => $boss ? $boss['nama'] : '—', 'bossRole' => $boss ? $boss['role'] : '', 'acting' => $level === 'SALES' && $p['role'] !== 'SALES', 'team' => $team];
        }
    }
    $cmp = PortalCore::comparisonPeriod($db, $period); $output = [];
    foreach ($groups as $p) {
        $team = $p['team']; $curr = PortalCore::metrics(PortalCore::slice($db, $period, $team), $db, $period, $brand);
        $previous = PortalCore::metrics(PortalCore::sliceCmp($db, $cmp['period'], $team, $cmp['cutoff'], $period), $db, $cmp['period'], $brand);
        $curr['sellout'] = StockCore::monthly($db, $period, $team, null, $brand)['qty'];
        $output[] = ['nik' => $p['nik'], 'nama' => $p['nama'], 'region' => $p['region'], 'role' => $p['role'], 'boss' => $p['boss'], 'bossRole' => $p['bossRole'], 'acting' => $p['acting'], 'total' => $curr, 'previous' => $previous, 'previousAvailable' => $cmp['available'],
            'target' => PortalCore::target($db['meta'], $period, $team, $brand, 'QTY'), 'targetDA' => PortalCore::target($db['meta'], $period, $team, $brand, 'DA'), 'targetNOO' => PortalCore::target($db['meta'], $period, $team, $brand, 'NOO')];
    }
    $un = []; foreach ($groups as $p) foreach ($p['team'] as $id) $un[] = $id; $union = uniq($un);
    $total = PortalCore::metrics(PortalCore::slice($db, $period, $union), $db, $period, $brand); $total['sellout'] = StockCore::monthly($db, $period, $union, null, $brand)['qty'];
    $output[] = ['nik' => '', 'nama' => 'Total', 'region' => 'Seluruh hasil tabel', 'boss' => '', 'bossRole' => '', 'total' => $total,
        'previous' => PortalCore::metrics(PortalCore::sliceCmp($db, $cmp['period'], $union, $cmp['cutoff'], $period), $db, $cmp['period'], $brand), 'previousAvailable' => $cmp['available'],
        'target' => PortalCore::target($db['meta'], $period, $union, $brand, 'QTY'), 'targetDA' => PortalCore::target($db['meta'], $period, $union, $brand, 'DA'), 'targetNOO' => PortalCore::target($db['meta'], $period, $union, $brand, 'NOO')];
    return $output;
}

// ------------------------------------------------------------------
// Riwayat dealer per brand (Code.gs + Promotor.gs)
// ------------------------------------------------------------------
function historyBrandList_($value, array $brands, string $label): array {
    $l = []; if (is_array($value) && array_is_list($value)) foreach ($value as $v) { $t = text_($v); if ($t !== '') $l[] = $t; }
    $l = array_values(array_unique($l));
    foreach ($l as $b) if (!in_array($b, $brands, true)) fail_($label . ' memuat brand yang tidak dikenal.');
    return $l;
}
function historyIndicatorList_($value): array {
    $allowed = ['ST', 'SO', 'OMZET']; $l = [];
    if (is_array($value) && array_is_list($value)) foreach ($value as $v) { $t = strtoupper(text_($v)); if ($t !== '') $l[] = $t; }
    $l = array_values(array_unique($l));
    foreach ($l as $v) if (!in_array($v, $allowed, true)) fail_('Indikator histori tidak dikenal.');
    return $l ?: $allowed;
}
/** Nama RGM di atas sales (naik lewat atasan sampai role RGM). */
function dealerRgmName_(array $db, $nik): string {
    $p = person_($db, $nik); $seen = [];
    while ($p && !isset($seen[(string) $p['nik']])) { $seen[(string) $p['nik']] = true; if (($p['role'] ?? '') === 'RGM') return (string) js_or($p['nama'] ?? null, ''); $p = person_($db, $p['atasan'] ?? null); }
    return '';
}
function historySortGetters_(array $periods, $indicators, $displayBrands): array {
    // Slot identitas tetap (9): kode, nama, big region, sub region, sales, asm, rgm, kategori(grade), promotor. Klien memetakan kolom yang tampil ke slot ini.
    $g = [fn($r) => $r['id'], fn($r) => $r['nama'], fn($r) => $r['region'], fn($r) => $r['subregion'], fn($r) => $r['sales'], fn($r) => $r['asm'], fn($r) => $r['rgm'] ?? '', fn($r) => truthy($r['gradeText'] ?? null) ? $r['gradeText'] : null, fn($r) => truthy($r['promotor'] ?? null) ? ((truthy($r['promFocus'] ?? null) ? 1000 : 0) + $r['promotor']['total'] + 1) : 0];
    foreach ($displayBrands ?: [] as $brand) foreach ($indicators ?: ['ST', 'SO', 'OMZET'] as $ind) {
        $metric = $ind === 'ST' ? 'st' : ($ind === 'SO' ? 'so' : 'omzet'); $tk = $ind === 'ST' ? 'totalST' : ($ind === 'SO' ? 'totalSO' : 'totalOmzet');
        foreach ($periods as $i => $p) $g[] = fn($r) => $r['brandMetrics'][$brand][$metric][$i] ?? null;
        $g[] = fn($r) => $r['brandMetrics'][$brand][$tk] ?? null;
    }
    return $g;
}
function historyMode_($value): string { $m = strtoupper(js_or(text_($value), 'FULL')); if (!in_array($m, ['FULL', 'TODAY', 'MTD'], true)) fail_('Cakupan tanggal histori tidak valid.'); return $m; }
function historyDateForDay_(string $period, $day) {
    if (!truthy($day)) return null;
    $d = min((float) $day, (float) substr(PortalCore::end($period), 8));
    return $period . '-' . str_pad((string) (int) $d, 2, '0', STR_PAD_LEFT);
}
function historyWindow_(string $period, $sourceCut, string $mode, $referenceDay): array {
    if (!truthy($sourceCut)) return ['ready' => false, 'from' => null, 'to' => null, 'sourceCut' => null];
    if ($mode === 'FULL') return ['ready' => true, 'from' => $period . '-01', 'to' => $sourceCut, 'sourceCut' => $sourceCut];
    $target = historyDateForDay_($period, $referenceDay);
    if (!$target || strcmp($sourceCut, $target) < 0) return ['ready' => false, 'from' => $mode === 'TODAY' ? $target : $period . '-01', 'to' => $target, 'sourceCut' => $sourceCut];
    return ['ready' => true, 'from' => $mode === 'TODAY' ? $target : $period . '-01', 'to' => $target, 'sourceCut' => $sourceCut];
}
function historyAttrApply_(array $rows, array $filters, array $selectedBrands): array {
    $pm = attrMap_('PROMOTOR'); $gm = attrMap_('GRADE'); $pb = count($selectedBrands) === 1 ? js_upper(js_str($selectedBrands[0])) : '';
    $pf = js_or(text_($filters['prom'] ?? null), 'ALL'); $gf = js_or(js_upper(text_($filters['grade'] ?? null)), 'ALL');
    foreach ($rows as &$r) {
        $p = $pm[$r['id']] ?? null; $g = isset($gm[$r['id']]) ? $gm[$r['id']]['brands'] : null;
        $r['promotor'] = $p; $r['grade'] = $g;
        $r['promFocus'] = !!$p && ($pb === '' || js_some($p['brands'], fn($b) => $b[0] === $pb && $b[1] > 0));
        if ($g) {
            if ($pb !== '') { $h = js_find($g, fn($x) => $x[0] === $pb); $r['gradeText'] = js_or($h[1] ?? null, ''); }
            else $r['gradeText'] = implode(' · ', array_map(fn($x) => $x[0] . ' ' . $x[1], $g));
        } else $r['gradeText'] = '';
    }
    unset($r);
    $out = js_filter($rows, function ($r) use ($pf, $pb, $gf) {
        if ($pf === 'YES' && !$r['promFocus']) return false;
        if ($pf === 'NO' && $r['promFocus']) return false;
        if ($pb !== '' && $gf !== 'ALL' && $r['gradeText'] !== $gf) return false;
        return true;
    });
    $opts = [];
    if ($pb !== '') foreach (array_keys($gm) as $id) foreach ($gm[$id]['brands'] as $x) if ($x[0] === $pb && !in_array($x[1], $opts, true)) $opts[] = $x[1];
    usort($opts, fn($a, $b) => js_locale_compare(js_str($a), js_str($b), 'id', true));
    return ['rows' => $out, 'promBrand' => $pb, 'gradeOptions' => $opts];
}
function historyPromCompare_(array $rows, array $months): array {
    $grp = function (bool $want) use ($rows, $months) {
        $list = js_filter($rows, fn($r) => !!$r['promFocus'] === $want);
        return ['count' => count($list), 'sum' => array_map(function ($m, $i) use ($list) {
            if (!truthy($m['stCut'])) return null; $s = 0;
            foreach ($list as $r) { $v = numJs_($r['st'][$i] ?? null); $s += (is_nan((float) $v) || !$v) ? 0 : $v; }
            return $s;
        }, $months, array_keys($months))];
    };
    return ['yes' => $grp(true), 'no' => $grp(false)];
}
function historySum_(array $values) { foreach ($values as $v) if ($v === null) return null; $n = 0; foreach ($values as $v) $n += $v; return $n; }

function dealerHistoryData_($token, $selection, $brand, $from, $to, $query, $filters): array {
    $db = snapshot_(); $u = guard_($token, false, false, $db); $ids = allowed_($db, $u, $selection); $set = js_set($ids);
    $from = PortalCore::month($from); $to = PortalCore::month($to); if ($from > $to) fail_('Bulan awal harus sebelum/sama dengan bulan akhir.');
    if (((int) substr($to, 0, 4) - (int) substr($from, 0, 4)) * 12 + (int) substr($to, 5) - (int) substr($from, 5) > 23) fail_('Pilih maksimal 24 bulan agar tabel tetap ringan.');
    $periods = monthSequence_($from, $to); if (count($periods) > 24) fail_('Pilih maksimal 24 bulan agar tabel tetap ringan.');
    $bs = []; foreach ($db['meta']['products'] as $p) if (truthy($p['device'] ?? null)) $bs[] = $p['brand']; $brands = array_values(array_unique($bs)); sort($brands, SORT_STRING);
    $brand = js_or(text_($brand), 'ALL'); if ($brand !== 'ALL' && !in_array($brand, $brands, true)) fail_('Brand tidak dikenal.');
    $filters = is_array($filters) ? $filters : [];
    $displayBrands = historyBrandList_($filters['displayBrands'] ?? null, $brands, 'Brand tampilan'); if (!$displayBrands && $brand !== 'ALL') $displayBrands = [$brand];
    $selectedBrands = $displayBrands ?: $brands; $indicators = historyIndicatorList_($filters['indicators'] ?? null); $dateMode = historyMode_($filters['dateMode'] ?? null);
    $basis = js_or($filters['basis'] ?? null, 'ST'); $orderBrands = historyBrandList_($filters['orderBrands'] ?? null, $brands, 'Filter sudah order'); $noOrderBrands = historyBrandList_($filters['noOrderBrands'] ?? null, $brands, 'Filter belum order');
    $order = truthy($filters['order'] ?? null) ? PortalCore::month($filters['order']) : ''; $noOrder = truthy($filters['noOrder'] ?? null) ? PortalCore::month($filters['noOrder']) : '';
    if ($orderBrands && $order === '') $order = $to; if ($noOrderBrands && $noOrder === '') $noOrder = $to;
    if (!in_array($basis, ['ST', 'SO'], true)) fail_('Acuan filter harus ST atau SO.');
    foreach ([$order, $noOrder] as $p) if ($p !== '' && !in_array($p, $periods, true)) fail_('Periode order/tidak order harus berada dalam rentang histori.');
    $overlap = array_values(array_filter($orderBrands, fn($b) => in_array($b, $noOrderBrands, true))); if ($overlap) fail_('Brand tidak boleh sekaligus sudah order dan belum order: ' . implode(',', $overlap));
    if ($order !== '' && $noOrder !== '' && $order === $noOrder && !$orderBrands && !$noOrderBrands) fail_('Periode order dan tidak order harus berbeda jika filter multi-brand belum dipilih.');
    $people = js_filter($db['meta']['people'], fn($p) => in_set($set, $p['nik']) && truthy($p['sales'] ?? null));
    $ar = []; foreach ($people as $p) if (truthy($p['wilayah'] ?? null)) $ar[] = $p['wilayah']; $allowedRegions = array_values(array_unique($ar)); sort($allowedRegions, SORT_STRING);
    $region = js_or(text_($filters['region'] ?? null), 'ALL'); if ($region !== 'ALL' && !in_array($region, $allowedRegions, true)) fail_('Region di luar cakupan.');
    $sr = []; foreach ($people as $p) if ($region === 'ALL' || ($p['wilayah'] ?? null) === $region) { $x = js_or($p['portfolio'] ?? null, $p['region'] ?? null); if (truthy($x)) $sr[] = $x; }
    $allowedSubRegions = array_values(array_unique($sr)); sort($allowedSubRegions, SORT_STRING);
    $subRegion = js_or(text_($filters['subRegion'] ?? null), 'ALL'); if ($subRegion !== 'ALL' && !in_array($subRegion, $allowedSubRegions, true)) fail_('Sub Region di luar cakupan.');
    $cvr = coverageView_($db, $to, $ids, 'ST', $u)['selectedRegions'] ?? null; $scopeRegions = truthy($cvr) ? $cvr : $allowedRegions;
    $selected = $region === 'ALL' ? array_values(js_filter($scopeRegions, fn($r) => in_array($r, $allowedRegions, true))) : [$region];
    $q = js_lower(js_slice(text_($query), 0, 100));
    $hasParts = isset($db['parts']) && truthy($db['parts']);
    // Region yang belum punya data ST/SO tidak lagi memblokir seluruh tabel (mode Semua Region): hitung dari region yang sudah ada & catat region yang belum.
    $missingRegions = [];
    $cutOf = function (string $kind, string $p) use ($hasParts, $db, $selected, $region, &$missingRegions) {
        if (!$hasParts) return $kind === 'ST' ? js_or($db['coverage'][$p]['cutoff'] ?? null, null) : js_or($db['stock']['coverage'][$p]['cutoff'] ?? null, null);
        $c = RegionCore::common($db['parts'], $kind, $p, $selected);
        if (!$c['cutoff'] && $region === 'ALL' && $c['missing'] && count($c['missing']) < count($selected)) {
            foreach ($c['missing'] as $m) $missingRegions[$kind][$m] = true;
            $ok = array_values(array_diff($selected, $c['missing']));
            return RegionCore::common($db['parts'], $kind, $p, $ok)['cutoff'];
        }
        return $c['cutoff'];
    };
    $refStCut = $cutOf('ST', $to); $refSoCut = $cutOf('SO', $to);
    $refStDay = $refStCut ? (int) substr($refStCut, 8) : null; $refSoDay = $refSoCut ? (int) substr($refSoCut, 8) : null;
    $calcPeriods = array_merge([PortalCore::prev($from)], $periods); $n = count($calcPeriods); $zero = array_fill(0, $n, 0);
    $E = [];
    $get = function ($id, $nik) use (&$E, $db, $brands, $zero) {
        $k = (string) $id; if (isset($E[$k])) return $k;
        $bc = []; foreach ($brands as $b) $bc[$b] = ['st' => $zero, 'so' => $zero, 'omzet' => $zero];
        $owner = dealerOwner_($db, $id, $nik);
        $E[$k] = array_merge(['id' => $id, 'nama' => js_or($db['meta']['customers'][$id]['nama'] ?? null, $id . ' (belum di master)')], $owner, ['rgm' => dealerRgmName_($db, $owner['salesNik'] ?? '')], ['stCalc' => $zero, 'soCalc' => $zero, 'omzetCalc' => $zero, 'brandCalc' => $bc]);
        return $k;
    };
    foreach ($db['meta']['customers'] as $c) if (in_set($set, $c['nik'] ?? null)) $get($c['id'], $c['nik']);
    $calcMonths = [];
    foreach ($calcPeriods as $i => $p) {
        $stSourceCut = $cutOf('ST', $p); $soSourceCut = $cutOf('SO', $p);
        $stW = historyWindow_($p, $stSourceCut, $dateMode, $refStDay); $soW = historyWindow_($p, $soSourceCut, $dateMode, $refSoDay);
        if ($stW['ready']) foreach (PortalCore::slice($db, $p, $ids, $stW['to']) as $t) {
            if (!($t['date'] >= $stW['from'] && (!truthy($t['region'] ?? null) || in_array($t['region'], $selected, true)) && in_array($t['brand'], $brands, true))) continue;
            $k = $get($t['customer'], $t['nik']); $match = in_array($t['brand'], $selectedBrands, true);
            if (truthy($t['q'] ?? null)) { $E[$k]['brandCalc'][$t['brand']]['st'][$i] += $t['q']; if ($match) $E[$k]['stCalc'][$i] += $t['q']; }
            $a = numJs_($t['a'] ?? null); $a = (is_nan((float) $a) || !$a) ? 0 : $a;
            $E[$k]['brandCalc'][$t['brand']]['omzet'][$i] = PortalCore::round($E[$k]['brandCalc'][$t['brand']]['omzet'][$i] + $a);
            if ($match) $E[$k]['omzetCalc'][$i] = PortalCore::round($E[$k]['omzetCalc'][$i] + $a);
        }
        if ($soW['ready']) foreach (stockMoves_($db, (string) $soW['from'], (string) $soW['to'], ['out' => true]) as $t) {
            if (!(substr($t['date'], 0, 7) === $p && $t['date'] >= $soW['from'] && $t['date'] <= $soW['to'] && (!truthy($t['region'] ?? null) || in_array($t['region'], $selected, true)) && StockCore::canSee($db, $ids, $t['customer']))) continue;
            $product = $db['meta']['products'][$t['sku']] ?? null;
            if ($product && truthy($product['device'] ?? null) && in_array($product['brand'], $brands, true) && truthy($t['outQty'] ?? null)) {
                $k = $get($t['customer'], $db['stock']['owners'][$t['customer']] ?? null);
                $E[$k]['brandCalc'][$product['brand']]['so'][$i] += $t['outQty'];
                if (in_array($product['brand'], $selectedBrands, true)) $E[$k]['soCalc'][$i] += $t['outQty'];
            }
        }
        $calcMonths[] = ['period' => $p, 'stCut' => $stW['ready'] ? $stW['to'] : null, 'soCut' => $soW['ready'] ? $soW['to'] : null, 'stFrom' => $stW['ready'] ? $stW['from'] : null, 'soFrom' => $soW['ready'] ? $soW['from'] : null, 'stSourceCut' => $stSourceCut, 'soSourceCut' => $soSourceCut, 'previousPeriod' => PortalCore::prev($p)];
    }
    $months = array_slice($calcMonths, 1);
    $allRows = array_values(js_filter(array_values($E), fn($c) => (!truthy($filters['customer'] ?? null) || $c['id'] === $filters['customer']) && str_contains(js_lower($c['id'] . ' ' . $c['nama']), $q) && ($region === 'ALL' || $c['region'] === $region) && ($subRegion === 'ALL' || $c['subregion'] === $subRegion)));
    $allRows = js_sort($allRows, fn($a, $b) => lc($a['nama'], $b['nama']) ?: lc($a['id'], $b['id']));
    foreach ($allRows as &$r) {
        foreach ($calcMonths as $i => $m) {
            if (!$m['stCut']) { $r['stCalc'][$i] = null; $r['omzetCalc'][$i] = null; foreach ($brands as $b) { $r['brandCalc'][$b]['st'][$i] = null; $r['brandCalc'][$b]['omzet'][$i] = null; } }
            if (!$m['soCut']) { $r['soCalc'][$i] = null; foreach ($brands as $b) $r['brandCalc'][$b]['so'][$i] = null; }
        }
        $r['st'] = array_slice($r['stCalc'], 1); $r['so'] = array_slice($r['soCalc'], 1); $r['omzet'] = array_slice($r['omzetCalc'], 1); $r['brandMetrics'] = []; $r['byBrand'] = [];
        foreach ($brands as $b) {
            $st = array_slice($r['brandCalc'][$b]['st'], 1); $so = array_slice($r['brandCalc'][$b]['so'], 1); $om = array_slice($r['brandCalc'][$b]['omzet'], 1);
            $r['brandMetrics'][$b] = ['st' => $st, 'so' => $so, 'omzet' => $om, 'totalST' => historySum_($st), 'totalSO' => historySum_($so), 'totalOmzet' => historySum_($om)]; $r['byBrand'][$b] = $st;
        }
        $r['totalST'] = historySum_($r['st']); $r['totalSO'] = historySum_($r['so']); $r['totalOmzet'] = historySum_($r['omzet']);
    }
    unset($r);
    $field = $basis === 'ST' ? 'st' : 'so';
    $at = fn($r, $b, $p) => $r['brandMetrics'][$b][$field][array_search($p, $periods, true)] ?? null;
    $rows = js_filter($allRows, function ($r) use ($order, $noOrder, $orderBrands, $noOrderBrands, $field, $periods, $at) {
        $okOrder = $order === '' || ($orderBrands ? js_every($orderBrands, fn($b) => $at($r, $b, $order) !== null && $at($r, $b, $order) > 0) : (($r[$field][array_search($order, $periods, true)] ?? null) !== null && $r[$field][array_search($order, $periods, true)] > 0));
        $okNo = $noOrder === '' || ($noOrderBrands ? js_every($noOrderBrands, fn($b) => $at($r, $b, $noOrder) !== null && $at($r, $b, $noOrder) <= 0) : (($r[$field][array_search($noOrder, $periods, true)] ?? null) !== null && $r[$field][array_search($noOrder, $periods, true)] <= 0));
        return $okOrder && $okNo;
    });
    $HX = historyAttrApply_($rows, $filters, $selectedBrands);
    return ['db' => $db, 'u' => $u, 'periods' => $periods, 'calcPeriods' => $calcPeriods, 'months' => $months, 'calcMonths' => $calcMonths, 'brands' => $brands, 'brand' => $brand, 'displayBrands' => $displayBrands, 'selectedBrands' => $selectedBrands, 'indicators' => $indicators, 'from' => $from, 'to' => $to, 'basis' => $basis, 'dateMode' => $dateMode, 'region' => $region, 'subRegion' => $subRegion, 'regions' => $allowedRegions, 'subRegions' => $allowedSubRegions,
        'order' => $order, 'noOrder' => $noOrder, 'orderBrands' => $orderBrands, 'noOrderBrands' => $noOrderBrands, 'missingRegions' => ['ST' => array_keys($missingRegions['ST'] ?? []), 'SO' => array_keys($missingRegions['SO'] ?? [])], 'rows' => $HX['rows'], 'promBrand' => $HX['promBrand'], 'gradeOptions' => $HX['gradeOptions'], 'prom' => js_or(text_($filters['prom'] ?? null), 'ALL'), 'grade' => js_or(text_($filters['grade'] ?? null), 'ALL')];
}
function historyAggregateRows_(array $data, array $rows, bool $withGrowth) {
    $growth = fn($c, $p) => ($c === null || $p === null || $p == 0) ? null : ($c - $p) / $p * 100; $out = [];
    foreach ($data['selectedBrands'] as $b) {
        $calc = ['st' => [], 'so' => [], 'omzet' => []];
        foreach ($data['calcMonths'] as $i => $m) foreach (['st', 'so', 'omzet'] as $key) {
            $ready = $key === 'so' ? $m['soCut'] : $m['stCut'];
            if (!$ready) { $calc[$key][$i] = null; continue; }
            $vals = array_map(fn($r) => $r['brandCalc'][$b][$key][$i], $rows); $anyNull = false; foreach ($vals as $v) if ($v === null) $anyNull = true;
            $s = 0; foreach ($vals as $v) $s += $v;
            $calc[$key][$i] = $anyNull ? null : ($key === 'omzet' ? PortalCore::round($s) : $s);
        }
        $item = ['st' => array_slice($calc['st'], 1), 'so' => array_slice($calc['so'], 1), 'omzet' => array_slice($calc['omzet'], 1)];
        $item['totalST'] = historySum_($item['st']); $item['totalSO'] = historySum_($item['so']); $item['totalOmzet'] = historySum_($item['omzet']);
        if ($withGrowth) $item['growth'] = ['st' => array_map(fn($v, $i) => $growth($v, $calc['st'][$i]), $item['st'], array_keys($item['st'])), 'so' => array_map(fn($v, $i) => $growth($v, $calc['so'][$i]), $item['so'], array_keys($item['so'])), 'omzet' => array_map(fn($v, $i) => $growth($v, $calc['omzet'][$i]), $item['omzet'], array_keys($item['omzet']))];
        $out[$b] = $item;
    }
    return js_obj($out);
}
function historySummaryGroup_(array $row, string $level): array {
    if ($level === 'BIG') return ['key' => js_or($row['region'], 'BELUM'), 'label' => js_or($row['region'], 'Belum terpetakan'), 'context' => ''];
    if ($level === 'REGION') return ['key' => js_or($row['region'], 'BELUM') . "\x1f" . js_or($row['subregion'], 'BELUM'), 'label' => js_or($row['subregion'], 'Belum terpetakan'), 'context' => js_or($row['region'], 'Belum terpetakan')];
    if ($level === 'ASM') return ['key' => js_or($row['asmNik'], 'ASM:' . js_str(js_or($row['asm'], 'Belum ada ASM'))), 'label' => js_or($row['asm'], 'Belum ada ASM'), 'context' => js_or($row['region'], 'Belum terpetakan')];
    $ctx = implode(' · ', array_values(array_filter([$row['asm'], js_or($row['subregion'], $row['region'])], fn($x) => truthy($x))));
    return ['key' => js_or($row['salesNik'], 'SALES:' . js_str(js_or($row['sales'], 'Belum ada Sales'))), 'label' => js_or($row['sales'], 'Belum ada Sales'), 'context' => js_or($ctx, 'Belum terpetakan')];
}
function apiDealerHistorySummary($token, $selection = null, $brand = null, $from = null, $to = null, $query = null, $filters = null, $level = null) {
    $level = strtoupper(js_or(text_($level), 'BIG')); if (!in_array($level, ['BIG', 'REGION', 'ASM', 'SALES'], true)) fail_('Level summary histori tidak valid.');
    $data = dealerHistoryData_($token, $selection, $brand, $from, $to, $query, $filters); $groups = [];
    foreach ($data['rows'] as $r) { $g = historySummaryGroup_($r, $level); $k = (string) $g['key']; if (!isset($groups[$k])) $groups[$k] = ['key' => $g['key'], 'label' => $g['label'], 'context' => $g['context'], 'rows' => []]; $groups[$k]['rows'][] = $r; }
    $rows = array_map(fn($g) => ['key' => $g['key'], 'label' => $g['label'], 'context' => $g['context'], 'dealerCount' => count($g['rows']), 'byBrand' => historyAggregateRows_($data, $g['rows'], false)], array_values($groups));
    $rows = js_sort($rows, fn($a, $b) => js_locale_compare($a['label'], $b['label'], 'id', true, true) ?: js_locale_compare($a['context'], $b['context'], 'id', true, true));
    return ['level' => $level, 'months' => $data['months'], 'displayBrands' => $data['selectedBrands'], 'indicators' => $data['indicators'], 'totalDealers' => count($data['rows']), 'rows' => $rows,
        'total' => ['dealerCount' => count($data['rows']), 'byBrand' => historyAggregateRows_($data, $data['rows'], true)],
        'filters' => ['dateMode' => $data['dateMode'], 'region' => $data['region'], 'subRegion' => $data['subRegion'], 'displayBrands' => $data['displayBrands'], 'indicators' => $data['indicators']]];
}
function apiDealerHistory($token, $selection = null, $brand = null, $from = null, $to = null, $page = null, $query = null, $filters = null) {
    $data = dealerHistoryData_($token, $selection, $brand, $from, $to, $query, $filters); $rows = $data['rows']; $filters = is_array($filters) ? $filters : [];
    $rows = sortRecords_($rows, $filters['sort'] ?? null, historySortGetters_($data['periods'], $data['indicators'], $data['selectedBrands']));
    $brandTotals = historyAggregateRows_($data, $rows, true);
    $pageSize = pageSizeOf_($filters['pageSize'] ?? null); $n = pageIdx_($page, count($rows), $pageSize);
    $pub = fn($r) => ['id' => $r['id'], 'nama' => $r['nama'], 'sales' => $r['sales'], 'salesNik' => $r['salesNik'], 'asm' => $r['asm'], 'asmNik' => $r['asmNik'], 'rgm' => $r['rgm'] ?? '', 'region' => $r['region'], 'subregion' => $r['subregion'], 'st' => $r['st'], 'so' => $r['so'], 'omzet' => $r['omzet'], 'byBrand' => js_obj($r['byBrand']), 'brandMetrics' => js_obj($r['brandMetrics']), 'totalST' => $r['totalST'], 'totalSO' => $r['totalSO'], 'totalOmzet' => $r['totalOmzet'], 'promotor' => $r['promotor'], 'promFocus' => $r['promFocus'], 'grade' => $r['grade'], 'gradeText' => $r['gradeText']];
    return ['missingRegions' => $data['missingRegions'], 'months' => $data['months'], 'promBrand' => $data['promBrand'], 'gradeOptions' => $data['gradeOptions'], 'promCompare' => historyPromCompare_($rows, $data['months']), 'brands' => $data['brands'], 'displayBrands' => $data['selectedBrands'], 'regions' => $data['regions'], 'subRegions' => $data['subRegions'], 'brand' => $data['brand'], 'from' => $data['from'], 'to' => $data['to'],
        'filters' => ['basis' => $data['basis'], 'dateMode' => $data['dateMode'], 'region' => $data['region'], 'subRegion' => $data['subRegion'], 'displayBrands' => $data['displayBrands'], 'indicators' => $data['indicators'], 'order' => $data['order'], 'noOrder' => $data['noOrder'], 'orderBrands' => $data['orderBrands'], 'noOrderBrands' => $data['noOrderBrands'], 'prom' => $data['prom'], 'grade' => $data['grade']],
        'total' => count($rows), 'page' => $n, 'pageSize' => $pageSize, 'rows' => array_map($pub, array_slice($rows, $n * $pageSize, $pageSize)), 'totals' => ['byBrand' => $brandTotals]];
}

// ------------------------------------------------------------------
// Export Dealer (Code.gs, Promotor.gs, ExportAccess.gs)
// ------------------------------------------------------------------
function exportGuard_($token, $type, ?array $db = null): array {
    demoAssertWritable_($token); $type = strtoupper(text_($type));
    if (!in_array($type, exportAccessTypeIds_(), true)) fail_('Jenis export tidak valid.');
    $u = guard_($token, false, false, $db);
    if (!(exportAccessForUser_($u)[$type] ?? false)) fail_('Akses export ' . $type . ' belum diberikan oleh Super Admin.');
    return $u;
}
function dealerExportGuard_($token, ?array $source = null): array { return exportGuard_($token, 'DEALER', $source); }
function historyPromDetail_(array $r): string {
    if (!truthy($r['promotor'] ?? null)) return '';
    return $r['promotor']['brands'] ? implode(' · ', array_map(fn($x) => $x[0] . ' ' . $x[1], $r['promotor']['brands'])) : 'brand belum diisi';
}
function dealerSalesOwner_(array $db, $nik): array {
    $people = $db['meta']['people']; $person = js_find($people, fn($p) => $p['nik'] === $nik); $p = $person; $asm = null; $seen = [];
    while ($p && !isset($seen[(string) $p['nik']])) { $seen[(string) $p['nik']] = true; if ($p['role'] === 'ASM') { $asm = $p; break; } $p = js_find($people, fn($x) => $x['nik'] === ($p['atasan'] ?? null)); }
    return ['sales' => js_or($person['nama'] ?? null, $nik), 'asm' => js_or($asm['nama'] ?? null, ''), 'region' => js_or($person['wilayah'] ?? null, ''), 'subregion' => js_or($person['portfolio'] ?? null, js_or($person['region'] ?? null, ''))];
}
function attrExportApply_(array $rows, array $f): array {
    $pm = attrMap_('PROMOTOR'); $gm = attrMap_('GRADE'); $pf = js_or(text_($f['prom'] ?? null), 'ALL'); $gf = js_upper(text_($f['grade'] ?? null)); $pb = js_upper(text_($f['brand'] ?? null));
    if ($pb === 'ALL') $pb = '';
    $hasP = function ($r) use ($pm, $pb) { $x = $pm[$r['id']] ?? null; return !!$x && ($pb === '' || js_some($x['brands'], fn($b) => $b[0] === $pb && $b[1] > 0)); };
    if ($pf === 'YES') $rows = js_filter($rows, $hasP); elseif ($pf === 'NO') $rows = js_filter($rows, fn($r) => !$hasP($r));
    if ($pb !== '' && $gf !== '' && $gf !== 'ALL') $rows = js_filter($rows, function ($r) use ($gm, $pb, $gf) { $x = $gm[$r['id']] ?? null; return !!$x && js_some($x['brands'], fn($b) => $b[0] === $pb && $b[1] === $gf); });
    $gradeBrands = [];
    foreach ($rows as &$r) {
        $p = $pm[$r['id']] ?? null; $g = $gm[$r['id']] ?? null;
        $r['promStatus'] = $p ? 'BER-PROMOTOR' : '-';
        $r['promDetail'] = $p ? ($p['brands'] ? implode(' · ', array_map(fn($x) => $x[0] . ' ' . $x[1], $p['brands'])) : 'brand belum diisi') : '';
        $r['grade'] = [];
        if ($g) foreach ($g['brands'] as $x) { $r['grade'][$x[0]] = $x[1]; if (!in_array($x[0], $gradeBrands, true)) $gradeBrands[] = $x[0]; }
    }
    unset($r);
    sort($gradeBrands, SORT_STRING);
    return ['rows' => $rows, 'gradeBrands' => $gradeBrands];
}
function apiExportDealerCsv($token, $period = null, $selection = null, $filter = null, $sort = null) {
    $source = snapshot_(); $u = dealerExportGuard_($token, $source); PortalCore::month($period); allowed_($source, $u, $selection);
    $report = apiDashboard($token, $period, $selection); $f = is_array($filter) ? $filter : [];
    $q = js_lower(js_slice(text_($f['query'] ?? null), 0, 100)); $region = js_or(text_($f['region'] ?? null), 'ALL'); $subRegion = js_or(text_($f['subRegion'] ?? null), 'ALL');
    $rows = js_filter($report['customers'], function ($c) use ($f, $q, $region, $subRegion) {
        $fb = $f['brand'] ?? null;
        if (!truthy($fb) || $fb === 'ALL') $b = $c['current']; else { $b = js_find($c['brands'], fn($x) => $x['brand'] === $fb); if (!$b) $b = ['qty' => 0, 'noo' => 0]; }
        $st = $f['status'] ?? null; $ci = $f['city'] ?? null; $or = $f['order'] ?? null;
        return str_contains(js_lower($c['id'] . ' ' . $c['nama']), $q) && (!truthy($st) || $st === 'ALL' || $c['status'] === $st) && (!truthy($ci) || $ci === 'ALL' || $c['kota'] === $ci) && ($region === 'ALL' || $c['bigRegion'] === $region) && ($subRegion === 'ALL' || $c['subRegion'] === $subRegion)
            && (!truthy($or) || $or === 'ALL' || ($or === 'YES' && $b['qty'] > 0) || ($or === 'NO' && $b['qty'] <= 0) || ($or === 'NEW' && ($b['noo'] ?? 0) > 0));
    });
    $rows = js_sort($rows, fn($a, $b) => $b['current']['qty'] - $a['current']['qty']);
    $getters = [fn($c) => $c['nama'], fn($c) => $c['bigRegion'], fn($c) => $c['subRegion'], fn($c) => $c['status'], fn($c) => $c['last']];
    foreach ($report['brands'] as $b) { $bn = $b['brand']; $getters[] = function ($c) use ($bn) { $x = js_find($c['brands'], fn($y) => $y['brand'] === $bn); return $x && truthy($x['qty']) ? $x['qty'] : 0; }; }
    $getters[] = fn($c) => $c['current']['qty']; $getters[] = fn($c) => $c['current']['sellout'];
    $rows = sortRecords_($rows, $sort, $getters);
    audit_($u['nik'], 'DEALER_CSV_EXPORT', $period . ' ' . count($rows) . ' dealer');
    $out = [['KODE CUSTOMER', 'NAMA INDUK CUSTOMER', 'REGION', 'SUB REGION', 'KOTA', 'STATUS', 'SELL THRU', 'OMZET']];
    foreach ($rows as $c) $out[] = [$c['id'], $c['nama'], $c['bigRegion'], $c['subRegion'], $c['kota'], $c['status'], $c['current']['qty'], $c['current']['omzet']];
    return ['name' => 'outlet-' . $period . '.csv', 'rows' => $out];
}
function apiExportDealerExcel($token, $selection = null, $from = null, $to = null, $filter = null, $sort = null) {
    $source = snapshot_(); $u = dealerExportGuard_($token, $source); $ids = allowed_($source, $u, $selection); $set = js_set($ids);
    $from = PortalCore::month($from); $to = PortalCore::month($to);
    if ($from > $to) fail_('Bulan awal harus sebelum/sama dengan bulan akhir.');
    $periods = monthSequence_($from, $to); if (count($periods) > 24) fail_('Pilih maksimal 24 bulan.');
    $bs = []; foreach ($source['meta']['products'] as $p) if (truthy($p['device'] ?? null)) $bs[] = $p['brand']; $allBrands = array_values(array_unique($bs)); sort($allBrands, SORT_STRING);
    $f = is_array($filter) ? $filter : []; $brands = historyBrandList_($f['brands'] ?? null, $allBrands, 'Filter brand'); if (!$brands) $brands = $allBrands; if (!$brands) fail_('Brand perangkat belum tersedia.');
    $endDb = coverageView_($source, $to, $ids, 'ST', $u);
    if (truthy($endDb['selectedRegions'] ?? null)) $selected = $endDb['selectedRegions']; else { $sr = []; foreach ($source['meta']['people'] as $p) if (in_set($set, $p['nik']) && truthy($p['sales'] ?? null) && truthy($p['wilayah'] ?? null)) $sr[] = $p['wilayah']; $selected = array_values(array_unique($sr)); }
    $endReport = PortalCore::report($endDb, $to, $ids, null); $statusById = []; foreach ($endReport['customers'] as $c) $statusById[(string) $c['id']] = $c;
    $E = []; $hasParts = isset($source['parts']) && truthy($source['parts']);
    $get = function ($id) use (&$E, $source, $brands, $statusById) {
        $k = (string) $id; if (isset($E[$k])) return $k;
        $c = $source['meta']['customers'][$id] ?? []; $owner = dealerOwner_($source, $id, $c['nik'] ?? null); $m = []; foreach ($brands as $b) $m[$b] = ['st' => 0, 'so' => 0, 'omzet' => 0];
        $E[$k] = ['id' => $id, 'nama' => js_or($c['nama'] ?? null, $id . ' (belum di master)'), 'kota' => js_or($c['kota'] ?? null, ''), 'masterRegion' => js_or($c['region'] ?? null, ''), 'masterNik' => js_or($c['nik'] ?? null, ''), 'ownerRegion' => js_or($owner['region'], ''), 'ownerSubRegion' => js_or($owner['subregion'], ''), 'metrics' => $m, 'salesIds' => [], 'last' => js_or($statusById[$k]['last'] ?? null, null), 'status' => js_or($statusById[$k]['status'] ?? null, 'BELUM ORDER')];
        return $k;
    };
    foreach ($source['meta']['customers'] as $c) if (in_set($set, $c['nik'] ?? null)) $get($c['id']);
    $stComplete = true; $soComplete = true; $monthInfo = [];
    foreach ($periods as $p) {
        $stCut = $hasParts ? RegionCore::common($source['parts'], 'ST', $p, $selected)['cutoff'] : js_or($source['coverage'][$p]['cutoff'] ?? null, null);
        $soCut = $hasParts ? RegionCore::common($source['parts'], 'SO', $p, $selected)['cutoff'] : js_or($source['stock']['coverage'][$p]['cutoff'] ?? null, null);
        $monthInfo[] = ['period' => $p, 'stCut' => $stCut, 'soCut' => $soCut]; if (!$stCut) $stComplete = false; if (!$soCut) $soComplete = false;
        if ($stCut) foreach (PortalCore::slice($source, $p, $ids, $stCut) as $t) {
            if (truthy($t['region'] ?? null) && !in_array($t['region'], $selected, true)) continue;
            $k = $get($t['customer']);
            if (($t['q'] > 0 || $t['a'] > 0) && truthy($t['nik'] ?? null) && !in_array($t['nik'], $E[$k]['salesIds'], true)) $E[$k]['salesIds'][] = $t['nik'];
            if (in_array($t['brand'], $brands, true)) { $E[$k]['metrics'][$t['brand']]['st'] += $t['q']; $E[$k]['metrics'][$t['brand']]['omzet'] = PortalCore::round($E[$k]['metrics'][$t['brand']]['omzet'] + $t['a']); }
        }
        if ($soCut) foreach (stockMoves_($source, $p . '-01', (string) $soCut) as $t) {
            if (!(substr($t['date'], 0, 7) === $p && $t['date'] <= $soCut && (!truthy($t['region'] ?? null) || in_array($t['region'], $selected, true)) && StockCore::canSee($source, $ids, $t['customer']))) continue;
            $product = $source['meta']['products'][$t['sku']] ?? null;
            if ($product && truthy($product['device'] ?? null) && in_array($product['brand'], $brands, true)) { $k = $get($t['customer']); $E[$k]['metrics'][$product['brand']]['so'] += $t['outQty']; }
        }
    }
    $valid = ['AKTIF', 'BELUM MINIMUM DA', 'DA BULAN LALU', 'IDLE', 'PASIF', 'BELUM ORDER']; $legacy = text_($f['status'] ?? null);
    $sl = []; $stIn = $f['statuses'] ?? null;
    if (is_array($stIn) && array_is_list($stIn)) $sl = $stIn; elseif ($legacy !== '' && $legacy !== 'ALL') $sl = [$legacy];
    $statuses = []; foreach ($sl as $x) { $t = text_($x); if ($t !== '') $statuses[] = $t; } $statuses = array_values(array_unique($statuses));
    foreach ($statuses as $s) if (!in_array($s, $valid, true)) fail_('Filter status dealer tidak dikenal.');
    $q = js_lower(js_slice(text_($f['query'] ?? null), 0, 100)); $city = js_or(text_($f['city'] ?? null), 'ALL'); $order = js_or(text_($f['order'] ?? null), 'ALL'); $region = js_or(text_($f['region'] ?? null), 'ALL'); $subRegion = js_or(text_($f['subRegion'] ?? null), 'ALL');
    $sum = function ($row, $key) use ($brands) { $n = 0; foreach ($brands as $b) $n += $row['metrics'][$b][$key]; return $n; };
    $isNew = function ($row) use ($brands, $source, $from, $to, $set) {
        foreach ($brands as $b) { $first = $source['first'][jkey([$row['id'], $b])] ?? null; if ($first && substr($first['date'], 0, 7) >= $from && substr($first['date'], 0, 7) <= $to && in_set($set, $first['nik'] ?? null)) return true; }
        return false;
    };
    $rows = js_filter(array_values($E), fn($r) => str_contains(js_lower($r['id'] . ' ' . $r['nama']), $q) && (!$statuses || in_array($r['status'], $statuses, true)) && ($city === 'ALL' || $r['kota'] === $city) && ($region === 'ALL' || $r['ownerRegion'] === $region) && ($subRegion === 'ALL' || $r['ownerSubRegion'] === $subRegion)
        && ($order === 'ALL' || ($order === 'YES' && $sum($r, 'st') > 0) || ($order === 'NO' && $sum($r, 'st') <= 0) || ($order === 'NEW' && $isNew($r))));
    $ATX = attrExportApply_($rows, $f); $rows = $ATX['rows'];
    $uniq = function (array $a) { $x = array_values(array_unique(array_filter($a, fn($v) => truthy($v)))); sort($x, SORT_STRING); return implode(', ', $x); };
    foreach ($rows as &$r) {
        if (!$r['salesIds'] && truthy($r['masterNik'])) $r['salesIds'][] = $r['masterNik'];
        $owners = array_map(fn($id) => dealerSalesOwner_($source, $id), $r['salesIds']);
        $r['sales'] = $uniq(array_map(fn($x) => $x['sales'], $owners)); $r['asm'] = $uniq(array_map(fn($x) => $x['asm'], $owners));
        $r['region'] = js_or($uniq(array_map(fn($x) => $x['region'], $owners)), $r['ownerRegion']); $r['subregion'] = js_or($uniq(array_map(fn($x) => $x['subregion'], $owners)), js_or($r['ownerSubRegion'], $r['masterRegion']));
        $r['totalST'] = $stComplete ? $sum($r, 'st') : null; $r['totalSO'] = $soComplete ? $sum($r, 'so') : null; $r['totalOmzet'] = $stComplete ? $sum($r, 'omzet') : null;
        if (!$stComplete) foreach ($brands as $b) { $r['metrics'][$b]['st'] = null; $r['metrics'][$b]['omzet'] = null; }
        if (!$soComplete) foreach ($brands as $b) $r['metrics'][$b]['so'] = null;
        foreach (['salesIds', 'masterNik', 'masterRegion', 'ownerRegion', 'ownerSubRegion'] as $k) unset($r[$k]);
        $r['grade'] = js_obj($r['grade']);
    }
    unset($r);
    $rows = js_sort($rows, fn($a, $b) => lc($a['region'], $b['region']) ?: lc($a['subregion'], $b['subregion']) ?: lc($a['asm'], $b['asm']) ?: lc($a['sales'], $b['sales']) ?: lc($a['status'], $b['status']) ?: lc($a['nama'], $b['nama']) ?: lc($a['id'], $b['id']));
    $sp = ($selection && $selection !== 'ALL') ? js_find($source['meta']['people'], fn($p) => $p['nik'] === $selection) : null;
    $scope = $sp ? ($sp['role'] . ' · ' . $sp['nama']) : (RegionCore::top($u['role']) || $u['role'] === 'SUPER ADMIN' ? 'NASIONAL' : ($u['role'] === 'ADMIN' ? js_or(implode(', ', $u['adminRegions'] ?? []), 'CAKUPAN ADMIN') : $u['role'] . ' · ' . $u['nama']));
    $rg = []; $srg = [];
    foreach ($rows as $r) { foreach (explode(', ', js_str($r['region'])) as $x) if ($x !== '') $rg[] = $x; foreach (explode(', ', js_str($r['subregion'])) as $x) if ($x !== '') $srg[] = $x; }
    $regions = array_values(array_unique($rg)); sort($regions, SORT_STRING); $subRegions = array_values(array_unique($srg)); sort($subRegions, SORT_STRING);
    audit_($u['nik'], 'DEALER_XLSX_EXPORT', $from . '..' . $to . ' ' . count($rows) . ' dealer ' . implode('|', $brands));
    return ['name' => 'Daftar-Dealer-' . $from . '_sd_' . $to . '.xlsx', 'periods' => $periods, 'months' => $monthInfo, 'brands' => $brands, 'filters' => ['from' => $from, 'to' => $to, 'scope' => $scope, 'regions' => $regions, 'subRegions' => $subRegions, 'region' => $region, 'subRegion' => $subRegion, 'query' => text_($f['query'] ?? null), 'statuses' => $statuses, 'order' => $order, 'city' => $city, 'brands' => $brands, 'count' => count($rows), 'stComplete' => $stComplete, 'soComplete' => $soComplete, 'prom' => js_or(text_($f['prom'] ?? null), 'ALL'), 'promBrand' => js_or(text_($f['brand'] ?? null), 'ALL'), 'grade' => js_or(text_($f['grade'] ?? null), 'ALL')], 'gradeBrands' => $ATX['gradeBrands'], 'rows' => $rows];
}
function apiExportDealerHistory($token, $selection = null, $brand = null, $from = null, $to = null, $query = null, $filters = null) {
    $source = snapshot_(); $u = dealerExportGuard_($token, $source); $ids = allowed_($source, $u, $selection); $filters = is_array($filters) ? $filters : [];
    $data = dealerHistoryData_($token, $selection, $brand, $from, $to, $query, $filters);
    $requested = historyBrandList_($filters['exportBrands'] ?? null, $data['brands'], 'Brand export'); $exportBrands = $requested ?: $data['selectedBrands'];
    $summaryLevel = strtoupper(js_or(text_($filters['summaryLevel'] ?? null), 'BIG')); if (!in_array($summaryLevel, ['BIG', 'REGION', 'ASM', 'SALES'], true)) fail_('Level summary histori tidak valid.');
    $endDb = coverageView_($source, $data['to'], $ids, 'ST', $u); $statusById = []; foreach (PortalCore::report($endDb, $data['to'], $ids, null)['customers'] as $c) $statusById[(string) $c['id']] = $c['status'];
    $data['rows'] = sortRecords_($data['rows'], $filters['sort'] ?? null, historySortGetters_($data['periods'], $data['indicators'], $data['selectedBrands']));
    $addToday = $data['dateMode'] !== 'TODAY';
    $todayData = $addToday ? dealerHistoryData_($token, $selection, $brand, $data['to'], $data['to'], $query, array_merge($filters, ['dateMode' => 'TODAY', 'order' => '', 'noOrder' => '', 'orderBrands' => [], 'noOrderBrands' => []])) : null;
    $todayRows = []; foreach ($todayData['rows'] ?? [] as $r) $todayRows[(string) $r['id']] = $r;
    $todayByRows = function (array $srcRows) use ($exportBrands, $todayData, $todayRows) {
        $o = [];
        foreach ($exportBrands as $b) {
            $k = [];
            foreach (['st', 'so', 'omzet'] as $key) {
                $ready = $todayData['months'][0][$key === 'so' ? 'soCut' : 'stCut'] ?? null;
                if (!$ready) { $k[$key] = null; continue; }
                $v = 0; foreach ($srcRows as $r) { $x = $todayRows[(string) $r['id']]['brandMetrics'][$b][$key][0] ?? null; $v += $x ?? 0; }
                $k[$key] = $key === 'omzet' ? PortalCore::round($v) : $v;
            }
            $o[$b] = $k;
        }
        return js_obj($o);
    };
    $rows = [];
    foreach ($data['rows'] as $r) {
        $bm = []; foreach ($exportBrands as $b) $bm[$b] = $r['brandMetrics'][$b];
        $rows[] = ['id' => $r['id'], 'nama' => $r['nama'], 'region' => $r['region'], 'subregion' => $r['subregion'], 'asm' => $r['asm'], 'sales' => $r['sales'], 'status' => js_or($statusById[(string) $r['id']] ?? null, 'BELUM ORDER'), 'promStatus' => truthy($r['promotor'] ?? null) ? 'BER-PROMOTOR' : '-', 'promDetail' => historyPromDetail_($r), 'gradeText' => js_or($r['gradeText'] ?? null, ''),
            'brandMetrics' => js_obj($bm), 'todayMetrics' => $addToday ? $todayByRows([$r]) : null,
            'brands' => array_map(fn($b) => ['brand' => $b, 'values' => $r['brandMetrics'][$b]['st'], 'total' => $r['brandMetrics'][$b]['totalST'], 'st' => $r['brandMetrics'][$b]['st'], 'so' => $r['brandMetrics'][$b]['so'], 'omzet' => $r['brandMetrics'][$b]['omzet'], 'totalST' => $r['brandMetrics'][$b]['totalST'], 'totalSO' => $r['brandMetrics'][$b]['totalSO'], 'totalOmzet' => $r['brandMetrics'][$b]['totalOmzet']], $exportBrands)];
    }
    $rows = js_sort($rows, fn($a, $b) => lc($a['region'], $b['region']) ?: lc($a['subregion'], $b['subregion']) ?: lc($a['asm'], $b['asm']) ?: lc($a['sales'], $b['sales']) ?: lc($a['status'], $b['status']) ?: lc($a['nama'], $b['nama']) ?: lc($a['id'], $b['id']));
    $aggData = array_merge($data, ['selectedBrands' => $exportBrands]); $groups = [];
    foreach ($data['rows'] as $r) { $g = historySummaryGroup_($r, $summaryLevel); $k = (string) $g['key']; if (!isset($groups[$k])) $groups[$k] = ['key' => $g['key'], 'label' => $g['label'], 'context' => $g['context'], 'rows' => []]; $groups[$k]['rows'][] = $r; }
    $summaryRows = array_map(fn($g) => ['key' => $g['key'], 'label' => $g['label'], 'context' => $g['context'], 'dealerCount' => count($g['rows']), 'byBrand' => historyAggregateRows_($aggData, $g['rows'], false), 'todayByBrand' => $addToday ? $todayByRows($g['rows']) : null], array_values($groups));
    $summaryRows = js_sort($summaryRows, fn($a, $b) => js_locale_compare($a['label'], $b['label'], 'id', true, true) ?: js_locale_compare($a['context'], $b['context'], 'id', true, true));
    $summary = ['level' => $summaryLevel, 'rows' => $summaryRows, 'total' => ['dealerCount' => count($data['rows']), 'byBrand' => historyAggregateRows_($aggData, $data['rows'], true), 'todayByBrand' => $addToday ? $todayByRows($data['rows']) : null]];
    $sp = ($selection && $selection !== 'ALL') ? js_find($source['meta']['people'], fn($p) => $p['nik'] === $selection) : null;
    $scope = $sp ? ($sp['role'] . ' · ' . $sp['nama']) : (RegionCore::top($u['role']) || $u['role'] === 'SUPER ADMIN' ? 'NASIONAL' : ($u['role'] === 'ADMIN' ? js_or(implode(', ', $u['adminRegions'] ?? []), 'CAKUPAN ADMIN') : $u['role'] . ' · ' . $u['nama']));
    $rg = []; $srg = []; foreach ($rows as $r) { if (truthy($r['region'])) $rg[] = $r['region']; if (truthy($r['subregion'])) $srg[] = $r['subregion']; }
    $regions = array_values(array_unique($rg)); sort($regions, SORT_STRING); $subRegions = array_values(array_unique($srg)); sort($subRegions, SORT_STRING);
    audit_($u['nik'], 'DEALER_HISTORY_XLSX_EXPORT', $from . '..' . $to . ' ' . count($rows) . ' dealer ' . implode('|', $exportBrands));
    return ['name' => 'History-Dealer-' . $from . '_sd_' . $to . '.xlsx', 'periods' => $data['periods'], 'months' => $data['months'], 'brands' => $exportBrands, 'indicators' => $data['indicators'], 'totals' => ['byBrand' => historyAggregateRows_($aggData, $data['rows'], true), 'todayByBrand' => $addToday ? $todayByRows($data['rows']) : null], 'summary' => $summary,
        'filters' => ['from' => $data['from'], 'to' => $data['to'], 'dateMode' => $data['dateMode'], 'region' => $data['region'], 'subRegion' => $data['subRegion'], 'displayBrands' => $data['displayBrands'], 'indicators' => $data['indicators'], 'order' => $data['order'], 'noOrder' => $data['noOrder'], 'orderBrands' => $data['orderBrands'], 'noOrderBrands' => $data['noOrderBrands'], 'exportBrands' => $exportBrands, 'summaryLevel' => $summaryLevel, 'scope' => $scope, 'regions' => $regions, 'subRegions' => $subRegions, 'query' => text_($query), 'count' => count($rows), 'prom' => $data['prom'], 'grade' => $data['grade'], 'promBrand' => $data['promBrand']], 'rows' => $rows];
}
