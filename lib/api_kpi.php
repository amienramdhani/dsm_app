<?php
/** KpiAdmin.gs — Atur KPI & Edit target di aplikasi. */
require_once __DIR__ . '/activity.php';

function kn_or($v, $d) { return ($v === null || $v === 0 || $v === 0.0 || (is_float($v) && is_nan($v))) ? $d : $v; }
function kn_nan($v): bool { return is_float($v) && is_nan($v); }
function kpiNow_(): DateTimeImmutable { return new DateTimeImmutable('now', jkt_tz_()); }

function kpiTree_(array $db): array {
    $g = readGrid_('Region_Map') ?: [[]]; $h = array_map('text_', $g[0]);
    $ri = array_search('REGION', $h, true); $wi = array_search('WILAYAH_KPI', $h, true); $tree = [];
    foreach (array_slice($g, 1) as $r) {
        $reg = text_($ri === false ? null : ($r[$ri] ?? null)); $big = text_($wi === false ? null : ($r[$wi] ?? null));
        if ($reg === '' || $big === '') continue;
        if (!isset($tree[$big])) $tree[$big] = [];
        if (js_upper($reg) !== js_upper($big) && !in_array($reg, $tree[$big], true)) $tree[$big][] = $reg;
    }
    foreach ($db['meta']['regionList'] ?? [] as $b) if (!isset($tree[$b])) $tree[$b] = [];
    $keys = js_sort_default(array_map('strval', array_keys($tree))); $out = [];
    foreach ($keys as $b) $out[] = ['big' => $b, 'subs' => js_sort_default($tree[$b])];
    return $out;
}
function kpiProducts_(array $db): array {
    // Produk INACTIVE tetap ada di meta agar histori/transaksi lama selalu dikenali,
    // tetapi tidak ditawarkan sebagai pilihan TYPE baru di editor KPI.
    $active = [];
    foreach (optionalTable_('Master_Product') as $r) {
        $code = text_($r['KODE BARANG'] ?? null);
        $status = js_upper(js_or(text_($r['STATUS'] ?? null), 'ACTIVE'));
        if ($code !== '' && $status !== 'INACTIVE') $active[$code] = true;
    }
    $brands = []; $types = [];
    foreach ($db['meta']['products'] ?? [] as $code => $x) {
        if (!truthy($x['device'] ?? false) || ($active && !isset($active[(string)$code]))) continue;
        $brands[(string) $x['brand']] = 1; $types[(string) $x['brand']][(string) $x['type']] = 1;
    }
    $t = []; foreach ($types as $b => $m) $t[(string) $b] = js_sort_default(array_map('strval', array_keys($m)));
    return ['brands' => array_merge(['ALL'], js_sort_default(array_map('strval', array_keys($brands)))), 'types' => $t];
}
function kpiPeriods_(array $db): array {
    $s = []; $now = kpiNow_(); $s[$now->format('Y-m')] = 1;
    $d = $now->modify('+1 month'); $s[$d->format('Y-m')] = 1;
    foreach ($db['meta']['policies'] ?? [] as $p) $s[$p['period']] = 1;
    foreach (js_keys($db['coverage'] ?? []) as $p) $s[(string) $p] = 1;
    $k = array_reverse(js_sort_default(array_map('strval', array_keys($s))));
    return array_slice($k, 0, 18);
}
function kpiRgmBigs_(array $db, $nik): array {
    $b = [];
    foreach (PortalCore::scope($db['meta']['people'], $nik) as $id) { $p = PortalCore::person($db['meta']['people'], $id); if ($p && truthy($p['sales'] ?? false) && truthy($p['wilayah'] ?? '')) $b[(string) $p['wilayah']] = 1; }
    return js_sort_default(array_map('strval', array_keys($b)));
}
function kpiSuper_($token): array {
    $db = snapshot_(); $u = guard_($token, true, false, $db);
    if ($u['role'] !== 'SUPER ADMIN') fail_('Khusus Super Admin.');
    return ['db' => $db, 'u' => $u];
}
function kpiMinDa_(array $p) { return $p['metric'] === 'NOO' ? js_or($p['minNOO'] ?? null, 1) : js_or($p['minDA'] ?? null, 2); }

function apiKpiGet($token, $period = null) {
    $c = kpiSuper_($token); $db = $c['db']; $periods = kpiPeriods_($db);
    $period = kpiMonth_($period);
    if ($period === '') {
        $period = $periods[0] ?? null;
        foreach ($periods as $p) if (js_some($db['meta']['policies'] ?? [], fn($x) => $x['period'] === $p)) { $period = $p; break; }
    }
    $cfg = [];
    foreach ($db['meta']['policies'] ?? [] as $p) {
        if ($p['period'] !== $period) continue;
        $cfg[(string) $p['region']][] = ['group' => $p['group'], 'metric' => $p['metric'], 'brand' => $p['brand'], 'weight' => $p['weight'], 'cap' => $p['cap'], 'indicator' => $p['indicator'] ?? null, 'label' => $p['label'] ?? null, 'types' => js_or($p['types'] ?? null, []), 'minDA' => kpiMinDa_($p)];
    }
    $rgms = [];
    foreach ($db['meta']['people'] as $p) {
        if ($p['role'] !== 'RGM') continue;
        $rgms[] = ['nik' => $p['nik'], 'nama' => $p['nama'], 'wilayah' => $p['wilayah'] ?? null, 'bigs' => kpiRgmBigs_($db, $p['nik']),
            'rows' => js_filter($db['meta']['rgmPolicies'] ?? [], fn($x) => $x['period'] === $period && $x['nik'] === $p['nik'])];
    }
    $rgms = js_sort($rgms, fn($a, $b) => lc($a['nama'], $b['nama']));
    $pr = kpiProducts_($db);
    // Paket 10: target otomatis indikator RGM = target Big Region (manual, atau jumlah target sales) per Big Region × brand × metrik
    $auto = []; $allBigs = js_sort_default(array_map('strval', $db['meta']['regionList'] ?? []));
    foreach ($allBigs as $bg) foreach ($pr['brands'] as $br) {
        if ($br === 'ALL') continue;
        foreach (['QTY', 'DA'] as $mt) { $v = rbBigTarget_($db['meta'], $period, $bg, $br, $mt) ?? rbSalesTarget_($db['meta'], $period, $bg, $br, $mt); if ($v > 0) $auto[js_upper($bg) . '|' . $br . '|' . $mt] = $v; }
    }
    return ['period' => $period, 'periods' => $periods, 'tree' => kpiTree_($db), 'cfg' => js_obj($cfg), 'rgms' => $rgms, 'brands' => $pr['brands'], 'types' => js_obj($pr['types']), 'allBigs' => $allBigs, 'autoMap' => js_obj($auto)];
}
function kpiCleanRows_($rows): array {
    if (!is_array($rows)) fail_('Data KPI tidak valid.');
    $rows = array_values($rows);
    if (count($rows) > 60) fail_('Maksimal 60 indikator.');
    $used = []; $out = [];
    foreach ($rows as $i => $x) {
        $x = is_array($x) ? $x : [];
        $metric = js_upper(text_($x['metric'] ?? null));
        $r = ['group' => js_upper(text_($x['group'] ?? null)), 'metric' => $metric, 'brand' => js_or(js_upper(text_($x['brand'] ?? null)), 'ALL'), 'weight' => kpiNum_($x['weight'] ?? null), 'cap' => kpiNum_($x['cap'] ?? null),
            'indicator' => preg_replace('/[^A-Z0-9_]/', '_', js_upper(text_($x['indicator'] ?? null))), 'label' => text_($x['label'] ?? null),
            'types' => array_values(array_filter(array_map('text_', is_array($x['types'] ?? null) ? $x['types'] : []), fn($v) => $v !== '')),
            'minDA' => kn_or(kpiNum_($x['minDA'] ?? null), $metric === 'NOO' ? 1 : 2),
            'bigs' => array_values(array_filter(array_map('text_', is_array($x['bigs'] ?? null) ? $x['bigs'] : []), fn($v) => $v !== '')), 'target' => kpiNum_($x['target'] ?? null)];
        $line = 'Indikator ' . ($i + 1) . ': ';
        if ($r['group'] === '') fail_($line . 'kelompok kosong.');
        if (!in_array($r['metric'], ['QTY', 'DA', 'NOO', 'OMZET'], true)) fail_($line . 'pilih metrik.');
        if (!($r['weight'] > 0)) fail_($line . 'bobot harus > 0.');
        if (!($r['cap'] > 0)) $r['cap'] = 120;
        if ($r['minDA'] < 1 || floor($r['minDA']) != $r['minDA']) fail_($line . 'minimal unit harus bilangan bulat ≥ 1.');
        $special = $r['types'] || ($r['metric'] === 'DA' && $r['minDA'] != 2) || ($r['metric'] === 'NOO' && $r['minDA'] != 1);
        if ($special && $r['indicator'] === '') {
            $base = preg_replace('/[^A-Z0-9_]/', '_', $r['metric'] . '_' . $r['brand'] . ($r['types'] ? '_TYPE' : '') . ($special && !$r['types'] ? '_MIN' . js_str($r['minDA']) : ''));
            $k = $base; $n = 2; while (isset($used[$k])) $k = $base . $n++;
            $r['indicator'] = $k;
        }
        if ($r['indicator'] !== '') $used[$r['indicator']] = 1;
        $out[] = $r;
    }
    return $out;
}
function kpiCommit_(array $u, array $db, array $sheets, string $label, string $reason, callable $fn): void {
    $before = []; foreach ($sheets as $s) $before[$s] = readGrid_($s);
    checkpoint_($u['nik'], $label . ' · ' . $reason);
    try {
        $fn();
        $meta = meta_(); validateConfigChange_($db, $meta);
        publish_($meta, $db['raw'], $db['coverage'], $u['nik'], $label . ' · ' . $reason);
    } catch (Throwable $e) {
        foreach ($sheets as $s) { if ($before[$s]) restoreGrid_($s, $before[$s]); else deleteSheet_($s); }
        throw $e;
    }
    audit_($u['nik'], 'KPI_ADMIN', $label . ' · ' . $reason);
}
function kpiMinCell_(array $r) { return (($r['metric'] === 'DA' && $r['minDA'] != 2) || ($r['metric'] === 'NOO' && $r['minDA'] != 1)) ? $r['minDA'] : ''; }

function apiKpiSaveRegion($token, $period, $region, $rows, $reason = null) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function () use ($token, $period, $region, $rows, $reason) {
        $c = kpiSuper_($token); $db = $c['db']; $period = kpiMonth_($period); $region = text_($region);
        if (!preg_match('/^\d{4}-\d{2}$/D', $period)) fail_('Periode harus YYYY-MM.');
        assertPeriodOpen_($period);
        $tree = kpiTree_($db); $node = null;
        foreach ($tree as $t) if (js_upper($t['big']) === js_upper($region)) { $node = $t; break; }
        $isSub = !$node && js_some($tree, fn($t) => js_some($t['subs'], fn($s) => js_upper($s) === js_upper($region)));
        if (!$node && !$isSub) fail_('Region ' . $region . ' tidak ada di Region Map.');
        $clean = kpiCleanRows_($rows);
        if (!$clean && $node) fail_('KPI Big Region tidak boleh kosong. Kalau mau hapus KPI sub region, buka sub region-nya.');
        kpiCommit_($c['u'], $db, ['KPI_Config'], 'KPI ' . $region . ' ' . $period . ($clean ? ' (' . count($clean) . ' indikator)' : ' dihapus'), $reason, function () use ($clean, $period, $region, $node) {
            $g = kpiGrid_('KPI_Config', KPI_CFG_HEADS);
            $g['rows'] = array_values(js_filter($g['rows'], fn($r) => !(kpiMonth_($r['PERIODE'] ?? null) === $period && js_upper(text_($r['WILAYAH_KPI'] ?? null)) === js_upper($region))));
            foreach ($clean as $r) $g['rows'][] = ['PERIODE' => $period, 'WILAYAH_KPI' => $node ? $node['big'] : $region, 'KELOMPOK_KPI' => $r['group'], 'METRIK' => $r['metric'], 'BRAND' => $r['brand'], 'BOBOT' => $r['weight'], 'BATAS_SKOR' => $r['cap'], 'INDIKATOR_ID' => $r['indicator'], 'LABEL' => $r['label'], 'TYPE_FILTER' => implode('|', $r['types']), 'MIN_QTY_DA' => kpiMinCell_($r), 'AKTIF' => 'TRUE'];
            kpiPut_('KPI_Config', $g);
        });
        return ['ok' => true, 'count' => count($clean)];
    });
}
function apiKpiSaveRgm($token, $period, $nik, $rows, $reason = null) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function () use ($token, $period, $nik, $rows, $reason) {
        $c = kpiSuper_($token); $db = $c['db']; $period = kpiMonth_($period); $nik = text_($nik); assertPeriodOpen_($period);
        $p = PortalCore::person($db['meta']['people'], $nik); if (!$p || $p['role'] !== 'RGM') fail_('NIK ' . $nik . ' bukan RGM.');
        $clean = kpiCleanRows_($rows);
        foreach ($clean as $i => $r) if ($r['target'] !== null && (kn_nan($r['target']) || $r['target'] < 0)) fail_('Indikator ' . ($i + 1) . ': target tidak valid.');
        kpiCommit_($c['u'], $db, ['KPI_RGM'], 'KPI RGM ' . $p['nama'] . ' ' . $period . ($clean ? ' (' . count($clean) . ' indikator)' : ' dihapus → ikut KPI Big Region'), $reason, function () use ($clean, $period, $nik, $p) {
            $g = kpiGrid_('KPI_RGM', KPI_RGM_HEADS);
            $g['rows'] = array_values(js_filter($g['rows'], fn($r) => !(kpiMonth_($r['PERIODE'] ?? null) === $period && text_($r['NIK'] ?? null) === $nik)));
            foreach ($clean as $r) $g['rows'][] = ['PERIODE' => $period, 'NIK' => $nik, 'NAMA' => $p['nama'], 'KELOMPOK_KPI' => $r['group'], 'INDIKATOR_ID' => $r['indicator'], 'LABEL' => $r['label'], 'METRIK' => $r['metric'], 'BRAND' => $r['brand'], 'TYPE_FILTER' => implode('|', $r['types']), 'BIG_REGIONS' => implode('|', $r['bigs']), 'BOBOT' => $r['weight'], 'BATAS_SKOR' => $r['cap'], 'TARGET' => $r['target'] === null ? '' : $r['target'], 'MIN_QTY_DA' => kpiMinCell_($r)];
            kpiPut_('KPI_RGM', $g);
        });
        return ['ok' => true, 'count' => count($clean)];
    });
}
function apiKpiCopy($token, $from, $to, $reason = null) {
    $reason = js_or(text_($reason), 'salin KPI');
    return with_lock_(function () use ($token, $from, $to, $reason) {
        $c = kpiSuper_($token); $db = $c['db']; $from = kpiMonth_($from); $to = kpiMonth_($to);
        if (!preg_match('/^\d{4}-\d{2}$/D', $to) || $from === $to) fail_('Pilih bulan tujuan yang berbeda.');
        assertPeriodOpen_($to);
        if (js_some($db['meta']['policies'] ?? [], fn($p) => $p['period'] === $to)) fail_('KPI ' . $to . ' sudah ada. Edit langsung saja.');
        $n = 0;
        kpiCommit_($c['u'], $db, ['KPI_Config', 'KPI_RGM'], 'Salin KPI ' . $from . ' → ' . $to, $reason, function () use ($from, $to, &$n) {
            $g = kpiGrid_('KPI_Config', KPI_CFG_HEADS);
            foreach (js_filter($g['rows'], fn($r) => kpiMonth_($r['PERIODE'] ?? null) === $from) as $r) { $r['PERIODE'] = $to; $g['rows'][] = $r; $n++; }
            kpiPut_('KPI_Config', $g);
            $h = kpiGrid_('KPI_RGM', KPI_RGM_HEADS);
            if ($h['raw']) {
                foreach (js_filter($h['rows'], fn($r) => kpiMonth_($r['PERIODE'] ?? null) === $from) as $r) { $r['PERIODE'] = $to; $r['TARGET'] = ''; $h['rows'][] = $r; }
                kpiPut_('KPI_RGM', $h);
            }
        });
        return ['ok' => true, 'count' => $n];
    });
}

// ===== Edit target =====
function targetAsm_(array $people, array $p): ?array {
    $x = $p; $seen = [];
    while ($x && !isset($seen[(string) $x['nik']])) {
        $seen[(string) $x['nik']] = 1; if ($x['role'] === 'ASM') return $x;
        $x = js_find($people, fn($q) => $q['nik'] === ($x['atasan'] ?? null));
    }
    return null;
}
function targetPolicyFor_(array $db, string $period, array $p): array {
    $sub = js_upper(text_(js_or($p['portfolio'] ?? '', $p['region'] ?? ''))); $all = js_filter($db['meta']['policies'] ?? [], fn($c) => $c['period'] === $period);
    $own = js_filter($all, fn($c) => js_upper(text_($c['region'])) === $sub);
    return $own ?: js_filter($all, fn($c) => $c['region'] === ($p['wilayah'] ?? null));
}
function targetKey_($metric, $brand, $ind): string { return $metric . '|' . $brand . '|' . js_str(js_or($ind, '')); }
function targetScope_(array $db, array $u, $big, $sub): array {
    $big = js_upper(text_($big)); $sub = js_upper(text_($sub));
    return js_filter($db['meta']['people'], function ($p) use ($u, $big, $sub) {
        if (!truthy($p['sales'] ?? false)) return false;
        if ($u['role'] !== 'SUPER ADMIN' && !canManageUser_($u, $p)) return false;
        if ($big !== '' && js_upper(text_($p['wilayah'] ?? null)) !== $big) return false;
        if ($sub !== '' && js_upper(text_(js_or($p['portfolio'] ?? '', $p['region'] ?? ''))) !== $sub) return false;
        return true;
    });
}
function apiTargetGet($token, $period = null, $big = null, $sub = null) {
    $db = snapshot_(); $u = adminGuard_($token, $db); $periods = kpiPeriods_($db); $period = js_or(kpiMonth_($period), $periods[0] ?? null);
    $ppl = targetScope_($db, $u, $big, $sub); $cols = []; $rows = []; $tv = [];
    foreach ($db['meta']['targets'] ?? [] as $t) if ($t['period'] === $period) $tv[(string) $t['nik']][targetKey_($t['metric'], $t['brand'], $t['indicator'] ?? '')] = $t['target'];
    foreach ($ppl as $p) {
        $need = [];
        foreach (targetPolicyFor_($db, $period, $p) as $c) {
            $k = targetKey_($c['metric'], $c['brand'], $c['indicator'] ?? '');
            if (!isset($cols[$k])) $cols[$k] = ['key' => $k, 'metric' => $c['metric'], 'brand' => $c['brand'], 'indicator' => js_or($c['indicator'] ?? '', ''), 'label' => truthy($c['indicator'] ?? '') ? js_or($c['label'] ?? '', $c['indicator']) : (KPI_METRIC_COL[$c['metric']] . ' ' . $c['brand']), 'types' => js_or($c['types'] ?? null, [])];
            $need[] = $k;
        }
        foreach (array_keys($tv[(string) $p['nik']] ?? []) as $k) {
            if (!isset($cols[$k])) { $s = explode('|', $k); $cols[$k] = ['key' => $k, 'metric' => $s[0], 'brand' => $s[1], 'indicator' => $s[2] ?? null, 'label' => js_or($s[2] ?? '', KPI_METRIC_COL[$s[0]] . ' ' . $s[1]), 'extra' => true]; }
        }
        $a = targetAsm_($db['meta']['people'], $p);
        $rows[] = ['nik' => $p['nik'], 'nama' => $p['nama'], 'vac' => (bool) (preg_match('/^VAC/i', (string) $p['nik']) || preg_match('/^VACANT\b/i', (string) $p['nama'])), 'big' => $p['wilayah'] ?? null, 'sub' => text_(js_or($p['portfolio'] ?? '', $p['region'] ?? '')), 'asm' => $a ? $a['nik'] : '', 'asmNama' => $a ? $a['nama'] : '(tanpa ASM)', 'need' => $need, 'vals' => js_obj($tv[(string) $p['nik']] ?? [])];
    }
    // Target per brand berdiri sendiri: semua brand × metrik bisa diisi walau KPI bulan ini belum diatur.
    foreach (kpiProducts_($db)['brands'] as $bn) foreach (array_keys(KPI_METRIC_COL) as $mt) {
        $k = targetKey_($mt, $bn, '');
        if (!isset($cols[$k])) $cols[$k] = ['key' => $k, 'metric' => $mt, 'brand' => $bn, 'indicator' => '', 'label' => KPI_METRIC_COL[$mt] . ' ' . $bn, 'extra' => true, 'free' => true];
    }
    $order = ['QTY' => 0, 'DA' => 1, 'NOO' => 2, 'OMZET' => 3];
    $cl = js_sort(array_values($cols), fn($a, $b) => (truthy($a['indicator']) ? 1 : 0) - (truthy($b['indicator']) ? 1 : 0) ?: $order[$a['metric']] - $order[$b['metric']] ?: ($a['brand'] === 'ALL' ? -1 : ($b['brand'] === 'ALL' ? 1 : lc($a['brand'], $b['brand']))));
    $rows = js_sort($rows, fn($a, $b) => lc($a['sub'], $b['sub']) ?: lc($a['asmNama'], $b['asmNama']) ?: ($a['vac'] ? 1 : 0) - ($b['vac'] ? 1 : 0) ?: lc($a['nama'], $b['nama']));
    $tree = kpiTree_($db);
    if ($u['role'] !== 'SUPER ADMIN') $tree = array_values(js_filter($tree, fn($t) => in_array($t['big'], $u['adminRegions'] ?? [], true)));
    return ['period' => $period, 'periods' => $periods, 'tree' => $tree, 'cols' => $cl, 'rows' => $rows, 'hasKpi' => js_some($db['meta']['policies'] ?? [], fn($c) => $c['period'] === $period)];
}
function apiTargetSave($token, $period, $changes, $reason = null) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function () use ($token, $period, $changes, $reason) {
        $db = snapshot_(); $u = adminGuard_($token, $db); $period = kpiMonth_($period);
        if (!preg_match('/^\d{4}-\d{2}$/D', $period)) fail_('Periode harus YYYY-MM.');
        assertPeriodOpen_($period);
        if (!is_array($changes) || !$changes) fail_('Tidak ada perubahan.');
        $changes = array_values($changes);
        if (count($changes) > 20000) fail_('Terlalu banyak perubahan sekaligus (maks 20.000 sel).');
        $list = [];
        foreach ($changes as $x) {
            $x = is_array($x) ? $x : [];
            $nik = text_($x['nik'] ?? null); $p = PortalCore::person($db['meta']['people'], $nik); $v = kpiNum_($x['value'] ?? null); $m = js_upper(text_($x['metric'] ?? null));
            if (!$p || !truthy($p['sales'] ?? false)) fail_('NIK ' . $nik . ' bukan Sales — target ASM/RGM tidak diisi di sini.');
            if ($u['role'] !== 'SUPER ADMIN' && !canManageUser_($u, $p)) fail_($p['nama'] . ' di luar wilayah admin Anda.');
            if (!isset(KPI_METRIC_COL[$m])) fail_('Metrik tidak valid.');
            if ($v !== null && (kn_nan($v) || $v < 0)) fail_($p['nama'] . ': nilai "' . js_str($x['value'] ?? null) . '" tidak valid.');
            $list[] = ['nik' => $nik, 'nama' => $p['nama'], 'metric' => $m, 'brand' => js_or(js_upper(text_($x['brand'] ?? null)), 'ALL'), 'ind' => text_($x['indicator'] ?? null), 'value' => $v];
        }
        $wide = array_values(js_filter($list, fn($x) => $x['ind'] === '')); $special = array_values(js_filter($list, fn($x) => $x['ind'] !== ''));
        kpiCommit_($u, $db, ['Target_Input', 'Target_Periode'], 'Edit target ' . $period . ' (' . count($list) . ' sel)', $reason, function () use ($list, $wide, $special, $period) {
            $tp = kpiGrid_('Target_Periode', ['PERIODE', 'NIK', 'BRAND', 'METRIK', 'TARGET', 'INDIKATOR_ID']); $hit = [];
            foreach ($list as $x) $hit[implode('|', [$x['nik'], $x['metric'], $x['brand'], $x['ind']])] = $x;
            $tp['rows'] = array_values(js_filter($tp['rows'], fn($r) => !(kpiMonth_($r['PERIODE'] ?? null) === $period && isset($hit[implode('|', [text_($r['NIK'] ?? null), js_upper(text_($r['METRIK'] ?? null)), js_upper(text_($r['BRAND'] ?? null)), text_($r['INDIKATOR_ID'] ?? null)])]))));
            foreach ($special as $x) if ($x['value'] !== null) $tp['rows'][] = ['PERIODE' => $period, 'NIK' => $x['nik'], 'BRAND' => $x['brand'], 'METRIK' => $x['metric'], 'TARGET' => $x['value'], 'INDIKATOR_ID' => $x['ind']];
            kpiPut_('Target_Periode', $tp);
            if ($wide) {
                $g = readGrid_(TARGET_INPUT);
                $heads = $g ? array_map(fn($h) => preg_replace('/\s+/u', ' ', js_upper(text_($h))), $g[0]) : TARGET_INPUT_FIXED;
                $rows = $g ? array_map(fn($r) => array_values($r), array_values(js_filter(array_slice($g, 1), fn($r) => kpiRowFilled_($r)))) : [];
                $pI = array_search('PERIODE TARGET', $heads, true); $nI = array_search('NIK SALES', $heads, true); $mI = array_search('NAMA SALES', $heads, true);
                foreach ($wide as $x) {
                    $h = KPI_METRIC_COL[$x['metric']] . ' ' . $x['brand']; $ci = array_search($h, $heads, true);
                    if ($ci === false) { $heads[] = $h; $ci = count($heads) - 1; foreach ($rows as $k => $_) $rows[$k][$ci] = ''; }
                    $ri = null;
                    foreach ($rows as $k => $r) if (kpiMonth_($r[$pI] ?? null) === $period && text_($r[$nI] ?? null) === $x['nik']) { $ri = $k; break; }
                    if ($ri === null) {
                        if ($x['value'] === null) continue;
                        $row = array_fill(0, count($heads), ''); $row[$pI] = $period; $row[$nI] = $x['nik']; if ($mI !== false) $row[$mI] = $x['nama'];
                        $rows[] = $row; $ri = count($rows) - 1;
                    }
                    $rows[$ri][$ci] = $x['value'] === null ? '' : $x['value'];
                }
                putTable_(TARGET_INPUT, $heads, array_map(function ($r) use ($heads, $pI) { $o = []; foreach ($heads as $i => $_) { $v = $r[$i] ?? null; $o[] = $v === null ? '' : ($i === $pI ? kpiMonth_($v) : $v); } return $o; }, $rows));
            }
        });
        return ['ok' => true, 'count' => count($list)];
    });
}
