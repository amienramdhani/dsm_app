<?php
/** API utama: bootstrap, Beranda (apiDashboard), KPI, Peringkat — terjemahan Code.gs & Mobile.gs. */
require_once __DIR__ . '/common.php';

function apiBootstrap($token) {
    $db = snapshot_(); $u = guard_($token, false, false, $db); $demo = demoState_($token, $db);
    $own = PortalCore::scope($db['meta']['people'], $u['nik']); $me = person_($db, $u['nik']);
    if ($u['role'] === 'SALES' && truthy($me['atasan'] ?? '')) {
        $ids = []; foreach ($db['meta']['people'] as $p) if ($p['nik'] === $me['atasan'] || ($p['atasan'] ?? null) === $me['atasan']) $ids[] = $p['nik'];
    } else $ids = $own;
    $exportAccess = $demo['active'] ? array_fill_keys(exportAccessTypeIds_(), false) : exportAccessForUser_($u);
    $set = js_set($ids);
    $periods = js_keys($db['coverage']); sort($periods, SORT_STRING); $periods = array_reverse($periods);
    return ['user' => $u, 'people' => js_filter($db['meta']['people'], fn($p) => isset($set[(string) $p['nik']])), 'periods' => $periods, 'processed' => $db['processed'] ?? null, 'version' => $db['id'], 'exportAccess' => $exportAccess, 'demo' => $demo];
}

function apiDashboard($token, $period, $selection) {
    $source = snapshot_(); $u = guard_($token, false, false, $source);
    $period = js_str($period);
    $ids = allowed_($source, $u, $selection); $db = coverageView_($source, $period, $ids, 'ST', $u);
    $set = js_set($ids);
    $regions = [];
    foreach ($db['meta']['people'] as $p) if (isset($set[(string) $p['nik']]) && truthy($p['sales'] ?? false)) $regions[] = $p['wilayah'] ?? null;
    $regions = uniq($regions);
    $result = PortalCore::report($db, $period, $ids, count($regions) === 1 ? $regions[0] : null);
    $result['regionalKpi'] = array_map(function ($region) use ($ids, $db, $period) {
        $n = js_filter($ids, fn($id) => (person_($db, $id)['wilayah'] ?? null) === $region);
        return PortalCore::kpi($db, $period, $n, $region);
    }, $regions);
    $buckets = [];
    foreach ($ids as $id) {
        $p = person_($db, $id); if (!$p) continue;
        $name = js_or(RegionCore::top($u['role']) ? ($p['wilayah'] ?? null) : js_or($p['portfolio'] ?? null, $p['region'] ?? null), 'Lainnya');
        $buckets[(string) $name][] = $id;
    }
    $bnames = js_keys($buckets); $bnames = js_sort_default($bnames);
    $result['regionalBrands'] = [];
    foreach ($bnames as $name) {
        $team = $buckets[$name]; $rows = PortalCore::slice($db, $period, $team);
        if (!$rows && !js_some($team, fn($id) => truthy(person_($db, $id)['sales'] ?? false))) continue;
        foreach ($result['brands'] as $b) $result['regionalBrands'][] = ['region' => $name, 'brand' => $b['brand'], 'total' => PortalCore::metrics($rows, $db, $period, $b['brand']), 'target' => PortalCore::target($db['meta'], $period, $team, $b['brand'], 'QTY'), 'targetDA' => PortalCore::target($db['meta'], $period, $team, $b['brand'], 'DA')];
    }
    $result['so'] = StockCore::monthly($db, $period, $ids); $result['total']['sellout'] = $result['so']['qty'];
    foreach ($result['brands'] as &$b) $b['current']['sellout'] = StockCore::monthly($db, $period, $ids, null, $b['brand'])['qty'];
    unset($b);
    $knownCustomers = []; foreach ($result['customers'] as $c) $knownCustomers[(string) $c['id']] = true;
    $knownTypes = []; foreach ($result['types'] as $t) $knownTypes[jkey([$t['brand'], $t['type']])] = true;
    $soCut = $result['so']['cutoff'];
    foreach (stockMoves_($db, $period . '-01', $soCut ?? PortalCore::end($period), ['out' => true]) as $t) {
        if (!truthy($t['outQty']) || substr($t['date'], 0, 7) !== $period || ($soCut !== null && strcmp($t['date'], $soCut) > 0) || !StockCore::canSee($db, $ids, $t['customer'])) continue;
        $product = $db['meta']['products'][$t['sku']]; $type = js_or($product['type'] ?? '', js_or($product['nama'] ?? '', $t['sku'])); $tk = jkey([$product['brand'], $type]);
        if (!isset($knownTypes[$tk])) { $knownTypes[$tk] = true; $result['types'][] = ['brand' => $product['brand'], 'type' => $type, 'qty' => 0]; }
        if (!isset($knownCustomers[(string) $t['customer']])) {
            $knownCustomers[(string) $t['customer']] = true; $c = $db['meta']['customers'][$t['customer']] ?? null;
            $result['customers'][] = ['id' => $t['customer'], 'nama' => js_or($c['nama'] ?? null, $t['customer'] . ' (belum di master)'), 'nik' => $db['stock']['owners'][$t['customer']] ?? null, 'kota' => js_or($c['kota'] ?? null, ''), 'region' => js_or($c['region'] ?? null, ''), 'alamat' => js_or($c['alamat'] ?? null, ''), 'hp' => js_or($c['hp'] ?? null, ''), 'status' => 'BELUM ORDER', 'last' => null, 'jeda' => null, 'current' => PortalCore::metrics([], $db, $period), 'previous' => PortalCore::metrics([], $db, $result['previousPeriod']), 'brands' => array_map(fn($b) => array_merge(['brand' => $b['brand']], PortalCore::metrics([], $db, $period, $b['brand'])), $result['brands'])];
        }
    }
    // Generic owner remains the Master Customer fallback for backward compatibility.
    // Brand-specific ownership is effective-dated and is what the Dealer page uses when a brand is selected.
    $dbaIdx = dbaIndex_();
    $ownerDate = PortalCore::iso($db['coverage'][$period]['cutoff'] ?? null) ?: PortalCore::end($period);
    $primaryBrands = dbaPrimaryBrands_();

    // PortalCore::report() historically includes dealers from Master_Customer owner + transaction history.
    // An explicit Dealer×Brand assignment may legitimately point to another Sales/region before that PIC
    // has any transaction. Add those assigned dealers with zero metrics so the new PIC can still see them.
    $scopeSet = js_set($ids); $knownAssigned = [];
    foreach ($result['customers'] as $c0) $knownAssigned[(string)$c0['id']] = true;
    foreach ($dbaIdx as $dealerId => $perBrand) {
        if (isset($knownAssigned[(string)$dealerId]) || !isset($db['meta']['customers'][(string)$dealerId])) continue;
        $visible = false;
        foreach ($perBrand as $brandName => $_list) {
            $hit = dbaAt_($dbaIdx, (string)$dealerId, (string)$brandName, $ownerDate);
            if ($hit && isset($scopeSet[(string)$hit['nik']])) { $visible = true; break; }
        }
        if (!$visible) continue;
        $cm = $db['meta']['customers'][(string)$dealerId];
        $result['customers'][] = [
            'id'=>(string)$dealerId, 'nama'=>js_or($cm['nama'] ?? null, 'Master belum tersedia'), 'nik'=>text_($cm['nik'] ?? null),
            'region'=>$cm['region'] ?? '', 'kota'=>$cm['kota'] ?? '', 'alamat'=>$cm['alamat'] ?? '', 'hp'=>$cm['hp'] ?? '',
            'status'=>'BELUM ORDER', 'last'=>null, 'jeda'=>null,
            'current'=>PortalCore::metrics([], $db, $period), 'previous'=>PortalCore::metrics([], $db, $result['previousPeriod']),
            'brands'=>array_map(fn($b)=>array_merge(['brand'=>$b['brand']], PortalCore::metrics([], $db, $period, $b['brand'])), $result['brands'])
        ];
        $knownAssigned[(string)$dealerId] = true;
    }

    foreach ($result['customers'] as &$c) {
        $owner = dealerOwner_($db, $c['id'], $c['nik']);
        $c['bigRegion'] = js_or($owner['region'], ''); $c['subRegion'] = js_or($owner['subregion'], js_or($c['region'], '')); $c['sales'] = js_or($owner['sales'], ''); $c['asm'] = js_or($owner['asm'], '');
        $c['brandOwners'] = [];
        // Keep this map sparse: only explicit overrides travel to the browser; fallback fields above cover the rest.
        foreach ($primaryBrands as $brandName) {
            $bo = dealerBrandOwner_($db, (string)$c['id'], (string)$brandName, $ownerDate, $dbaIdx);
            if ($bo['source'] !== 'ASSIGNMENT') continue;
            $bp = PortalCore::person($db['meta']['people'], $bo['nik']);
            $boss = $bp && !empty($bp['atasan']) ? PortalCore::person($db['meta']['people'], $bp['atasan']) : null;
            $c['brandOwners'][(string)$brandName] = [
                'nik'=>$bo['nik'], 'nama'=>$bo['nama'], 'bigRegion'=>$bo['big'], 'subRegion'=>$bo['region'],
                'asm'=>$boss ? text_($boss['nama'] ?? null) : '',
                'source'=>$bo['source'], 'effectiveFrom'=>$bo['assignment']['from'] ?? ''
            ];
        }
        $c['current']['sellout'] = StockCore::monthly($db, $period, $ids, $c['id'])['qty'];
        foreach ($c['brands'] as &$cb) $cb['sellout'] = StockCore::monthly($db, $period, $ids, $c['id'], $cb['brand'])['qty'];
        unset($cb);
    }
    unset($c);
    promotorAttach_($result['customers']);
    foreach ($result['regionalBrands'] as &$rb) $rb['total']['sellout'] = StockCore::monthly($db, $period, $buckets[$rb['region']], null, $rb['brand'])['qty'];
    unset($rb);
    foreach ($result['types'] as &$t) $t['sellout'] = StockCore::monthly($db, $period, $ids, null, $t['brand'], $t['type'])['qty'];
    unset($t);
    $dev = []; foreach ($db['meta']['products'] as $p) if (truthy($p['device'] ?? false)) $dev[] = $p['brand'];
    $result['deviceBrands'] = js_sort_default(uniq($dev));
    $result['brandTotals'] = ['target' => combinedTarget_(array_map(fn($b) => $b['target'], $result['brands'])), 'targetDA' => combinedTarget_(array_map(fn($b) => $b['targetDA'], $result['brands'])), 'targetNOO' => combinedTarget_(array_map(fn($b) => $b['targetNOO'], $result['brands']))];
    $bh = [];
    foreach ($result['brands'] as $b) {
        $bh[$b['brand']] = array_map(function ($pt) use ($db, $period, $ids, $b) {
            $cmp = PortalCore::comparisonPeriod($db, $pt['period']);
            $qty = PortalCore::metrics($pt['period'] === $period ? PortalCore::slice($db, $pt['period'], $ids) : PortalCore::sliceCmp($db, $pt['period'], $ids, null, $period), $db, $pt['period'], $b['brand'])['qty'];
            $previousQty = $cmp['available'] ? PortalCore::metrics(PortalCore::sliceCmp($db, $cmp['period'], $ids, $cmp['cutoff'], $period), $db, $cmp['period'], $b['brand'])['qty'] : null;
            return array_merge($pt, ['qty' => $qty, 'previousQty' => $previousQty, 'growth' => ($previousQty === null || $previousQty == 0) ? null : ($qty - $previousQty) / $previousQty * 100]);
        }, $result['trend']);
    }
    $result['brandHistory'] = js_obj($bh);
    $result['weeklyPair'] = weeklyPair_($source, $period, $ids, $u);
    $result['regionStatus'] = $db['regionStatus'] ?? [];
    $result['noData'] = !empty($db['noData']); $result['partial'] = !empty($db['partial']);
    $result['processed'] = $db['processed'] ?? null; $result['version'] = $db['id'];
    $result['scopeCount'] = count(js_filter($ids, fn($id) => truthy(person_($db, $id)['sales'] ?? false)));
    return $result;
}

// ------------------------------------------------------------------
// KPI
// ------------------------------------------------------------------

function apiPersonKpi($token, $period, $nik) {
    $source = snapshot_(); $u = guard_($token, false, false, $source); $period = js_str($period); $nik = js_str($nik);
    $ids = allowed_($source, $u, $nik); $db = coverageView_($source, $period, $ids, 'ST', $u); $p = person_($db, $nik);
    audit_($u['nik'], 'DETAIL_KPI', $nik);
    return PortalCore::kpi($db, $period, $p['role'] === 'SALES' ? [$nik] : $ids, $p['wilayah'] ?? null, $p);
}

function apiTeamKpi($token, $period, $selection) {
    $source = snapshot_(); $u = guard_($token, false, false, $source); $period = js_str($period);
    if (!in_array($u['role'], ['ASM', 'RGM', 'ADMIN', 'SUPER ADMIN'], true) && !RegionCore::top($u['role'])) fail_('Rincian tim KPI tidak tersedia untuk jabatan ini.');
    $ids = allowed_($source, $u, $selection); $chosen = person_($source, $selection); $admin = in_array($u['role'], ['ADMIN', 'SUPER ADMIN'], true);
    $level = (RegionCore::top($u['role']) || ($admin && !in_array($chosen['role'] ?? null, ['ASM', 'RGM', 'SALES'], true))) ? 'ASM' : 'SALES';
    $set = js_set($ids); $out = [];
    foreach ($source['meta']['people'] as $p) {
        if (!isset($set[(string) $p['nik']]) || !($level === 'ASM' ? $p['role'] === 'ASM' : truthy($p['sales'] ?? false))) continue;
        $team = $level === 'ASM' ? js_filter(PortalCore::scope($source['meta']['people'], $p['nik']), fn($id) => isset($set[(string) $id])) : [$p['nik']];
        $db = coverageView_($source, $period, $team, 'ST', $u);
        $out[] = ['nik' => $p['nik'], 'nama' => $p['nama'], 'region' => js_or($p['wilayah'] ?? null, 'Belum dipetakan'), 'area' => js_or($p['portfolio'] ?? null, js_or($p['region'] ?? null, '')), 'acting' => $level === 'SALES' && $p['role'] !== 'SALES', 'kpi' => PortalCore::kpi($db, $period, $team, $p['wilayah'] ?? null, $p), 'cutoff' => $db['coverage'][$period]['cutoff'] ?? null, 'partial' => !empty($db['partial']), 'noData' => !empty($db['noData'])];
    }
    return js_sort($out, fn($a, $b) => lc($a['region'], $b['region']) ?: lc($a['nama'], $b['nama']));
}

function apiKpiTree($token, $period, $selection) {
    $source = snapshot_(); $u = guard_($token, false, false, $source); $period = js_str($period);
    if (!in_array($u['role'], ['ASM', 'RGM', 'ADMIN', 'SUPER ADMIN'], true) && !RegionCore::top($u['role'])) fail_('Rincian tim KPI tidak tersedia untuk jabatan ini.');
    $ids = allowed_($source, $u, $selection); $set = js_set($ids); $people = $source['meta']['people'];
    $me = person_($source, $u['nik']) ?? $u; $top = RegionCore::top($u['role']);
    $node = function (array $p, bool $own) use ($people, $set, $source, $period, $u) {
        $team = $own ? [$p['nik']] : js_filter(PortalCore::scope($people, $p['nik']), fn($id) => isset($set[(string) $id]));
        $db = coverageView_($source, $period, $team, 'ST', $u);
        return ['nik' => $p['nik'], 'nama' => $p['nama'], 'role' => $own ? 'SALES' : $p['role'], 'acting' => $own && $p['role'] !== 'SALES', 'region' => js_or($p['wilayah'] ?? null, 'Belum dipetakan'), 'area' => $own ? js_or($p['portfolio'] ?? null, $p['region'] ?? null) : ($p['region'] ?? null), 'kpi' => PortalCore::kpi($db, $period, $team, $p['wilayah'] ?? null, $p), 'cutoff' => $db['coverage'][$period]['cutoff'] ?? null, 'noData' => !empty($db['noData']), 'partial' => !empty($db['partial']), 'children' => []];
    };
    $peerNode = function (array $p) use ($people, $source, $period) {
        $team = PortalCore::scope($people, $p['nik']); $db = coverageView_($source, $period, $team, 'ST');
        return ['nik' => $p['nik'], 'nama' => $p['nama'], 'role' => $p['role'], 'acting' => false, 'region' => js_or($p['wilayah'] ?? null, 'Belum dipetakan'), 'area' => $p['region'] ?? null, 'kpi' => PortalCore::kpi($db, $period, $team, $p['wilayah'] ?? null, $p), 'cutoff' => $db['coverage'][$period]['cutoff'] ?? null, 'noData' => !empty($db['noData']), 'partial' => !empty($db['partial']), 'children' => []];
    };
    // Simpul disimpan di tabel datar; anak disusun belakangan (PHP tidak punya referensi objek seperti JS).
    $store = []; $childrenOf = []; $roots = []; $byId = [];
    $add = function (array $n) use (&$store) { $store[] = $n; return count($store) - 1; };
    $managers = js_filter($people, fn($p) => isset($set[(string) $p['nik']]) && ($top ? $p['role'] === 'ASM' : in_array($p['role'], ['ASM', 'RGM'], true)));
    foreach ($managers as $p) $byId[(string) $p['nik']] = $add($node($p, false));
    $parent = function (array $p) use (&$byId, $people) {
        $seen = [(string) $p['nik'] => true]; $id = $p['atasan'] ?? null;
        while (truthy($id) && !isset($seen[(string) $id])) { $seen[(string) $id] = true; if (isset($byId[(string) $id])) return $byId[(string) $id]; $id = PortalCore::person($people, $id)['atasan'] ?? null; }
        return null;
    };
    foreach ($managers as $p) { $n = $byId[(string) $p['nik']]; $boss = $top ? null : $parent($p); if ($boss !== null) $childrenOf[$boss][] = $n; else $roots[] = $n; }
    if (!$top) foreach ($people as $p) {
        if (!isset($set[(string) $p['nik']]) || !truthy($p['sales'] ?? false)) continue;
        $n = $add($node($p, true)); $boss = $byId[(string) $p['nik']] ?? $parent($p);
        if ($boss !== null) $childrenOf[$boss][] = $n; else $roots[] = $n;
    }
    if ((!truthy($selection) || $selection === 'ALL') && in_array($u['role'], ['ASM', 'RGM'], true)) {
        foreach ($people as $p) if ($p['nik'] !== $u['nik'] && $p['role'] === $u['role'] && ($u['role'] === 'RGM' || js_str($p['atasan'] ?? '') === js_str($me['atasan'] ?? ''))) $roots[] = $add($peerNode($p));
    }
    $build = function (array $list) use (&$build, &$store, &$childrenOf) {
        $nodes = [];
        foreach ($list as $i) { $n = $store[$i]; $n['children'] = $build($childrenOf[$i] ?? []); $nodes[] = $n; }
        return js_sort($nodes, fn($a, $b) => lc($a['region'], $b['region']) ?: lc($a['nama'], $b['nama']));
    };
    return $build($roots);
}

// ------------------------------------------------------------------
// Peringkat
// ------------------------------------------------------------------

function apiRank($token, $period, $national, $level = null) {
    $source = snapshot_(); $u = guard_($token, false, false, $source); $period = js_str($period);
    $level = js_or($level, 'SALES'); $national = truthy($national);
    if (!in_array($level, ['SALES', 'ASM'], true)) fail_('Pilih peringkat Sales atau ASM.');
    $people = $source['meta']['people'];
    $ids = PortalCore::scope($people, $u['nik']); $set = js_set($ids);
    $db = coverageView_($source, $period, $national ? array_map(fn($p) => $p['nik'], $people) : $ids, 'ST');
    $rows = [];
    foreach ($db['meta']['people'] as $p) {
        if ($p['role'] !== $level || $p['nik'] === '-' || !($national || isset($set[(string) $p['nik']]))) continue;
        $team = $level === 'ASM' ? PortalCore::scope($people, $p['nik']) : [$p['nik']];
        $k = PortalCore::kpi($db, $period, $team, $p['wilayah'] ?? null, $p);
        $ownCut = js_or($source['parts']['ST'][$period][$p['wilayah'] ?? '']['cutoff'] ?? null, null);
        $pending = isset($source['parts']) ? !truthy($ownCut) : !empty($db['missingST']);
        $can = rankDetailAllowed_($source, $u, $p);
        $rows[] = ['nik' => $can ? $p['nik'] : null, 'nama' => $p['nama'], 'role' => $level, 'region' => $p['region'] ?? null, 'wilayah' => $p['wilayah'] ?? null, 'score' => $pending ? null : $k['score'],
            'status' => $pending ? 'Menunggu ST ' . js_or($p['wilayah'] ?? null, 'region') : ((!empty($db['partial']) || !empty($db['missingST']) ? 'Sementara · ' : '') . $k['status'] . ' · s.d. ' . js_or($ownCut, $db['coverage'][$period]['cutoff'] ?? null)),
            'groups' => $k['count'],
            'groupScores' => array_map(fn($g) => ['name' => $g['name'], 'score' => $pending ? null : $g['score'], 'status' => $pending ? 'Menunggu ST' : ($g['na'] ? 'Tidak diwajibkan' : ($g['pending'] ? 'Belum final' : ((!empty($g['partial']) || !empty($k['tentative'])) ? 'Sementara' : 'Berlaku')))], $k['groups']),
            'canOpen' => $can];
    }
    $rows = js_sort($rows, fn($a, $b) => (($b['score'] ?? -1) - ($a['score'] ?? -1)) ?: lc($a['nama'], $b['nama']));
    foreach ($rows as &$r) $r['vacant'] = (bool) (preg_match('/^VAC/i', js_str($r['nik'] ?? '')) || preg_match('/^VACANT\b/i', js_str($r['nama'] ?? '')));
    unset($r);
    $rows = js_sort($rows, fn($a, $b) => ((int) $a['vacant']) - ((int) $b['vacant']));
    $scored = js_filter($rows, fn($p) => $p['score'] !== null && !$p['vacant']);
    return array_map(function ($p) use ($scored) {
        if ($p['vacant']) return array_merge($p, ['rank' => null, 'top' => false, 'bottom' => false, 'achieved' => false]);
        $rank = $p['score'] === null ? null : count(js_filter($scored, fn($x) => $x['score'] > $p['score'])) + 1;
        $bottom = $p['score'] === null ? null : count(js_filter($scored, fn($x) => $x['score'] < $p['score'])) + 1;
        $top = $rank !== null && $rank <= 3;
        return array_merge($p, ['rank' => $rank, 'top' => $top, 'bottom' => $bottom !== null && $bottom <= 3 && !$top, 'achieved' => $p['score'] !== null && $p['score'] >= 90]);
    }, $rows);
}

function apiMyRank($token, $period, $selection) {
    $source = snapshot_(); $u = guard_($token, false, false, $source); $period = PortalCore::month($period);
    $nik = truthy($selection) && $selection !== 'ALL' ? js_str($selection) : $u['nik'];
    if ($nik !== $u['nik']) allowed_($source, $u, $nik);
    $p = person_($source, $nik);
    if (!$p || !in_array($p['role'], ['SALES', 'ASM'], true)) return ['eligible' => false, 'message' => 'Peringkat individu tersedia untuk Sales dan ASM. Pilih cakupan Sales atau ASM untuk melihat posisinya.'];
    $rows = apiRank($token, $period, true, $p['role']); $self = js_find($rows, fn($x) => $x['nik'] === $nik);
    if (!$self) return ['eligible' => false, 'message' => 'Rincian peringkat tidak tersedia dalam izin akun ini.'];
    $regional = js_filter($rows, fn($x) => $x['wilayah'] === ($p['wilayah'] ?? null)); $scored = js_filter($regional, fn($x) => $x['score'] !== null);
    return ['eligible' => true, 'nik' => $nik, 'nama' => $p['nama'], 'role' => $p['role'], 'region' => js_or($p['wilayah'] ?? null, ''), 'score' => $self['score'], 'status' => $self['status'], 'nationalRank' => $self['rank'], 'nationalTotal' => count(js_filter($rows, fn($x) => $x['score'] !== null)),
        'regionRank' => (!truthy($p['wilayah'] ?? null) || $self['score'] === null) ? null : count(js_filter($scored, fn($x) => $x['score'] > $self['score'])) + 1, 'regionTotal' => count($scored)];
}

function apiRankDetail($token, $period, $nik) {
    $source = snapshot_(); $u = guard_($token, false, false, $source); $period = js_str($period); $nik = js_str($nik);
    $p = person_($source, $nik);
    if (!rankDetailAllowed_($source, $u, $p)) fail_('Rincian KPI di luar izin peringkat.');
    $ids = $p['role'] === 'ASM' ? PortalCore::scope($source['meta']['people'], $nik) : [$nik];
    $db = coverageView_($source, $period, $ids, 'ST'); audit_($u['nik'], 'RANK_KPI', $nik);
    return ['nama' => $p['nama'], 'role' => $p['role'], 'region' => $p['wilayah'] ?? null, 'kpi' => PortalCore::kpi($db, $period, $ids, $p['wilayah'] ?? null, $p)];
}
