<?php
/* Eksekutif.gs — data Mode Eksekutif (HP): nasional, Big Region, Region × brand × indikator.
   Terjemahan 1:1. Hanya MEMBACA data publikasi. Satu panggilan = seluruh isi halaman. */
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/api_tutup.php';
require_once __DIR__ . '/api_analisa.php';
require_once __DIR__ . '/regionbrand.php';

/* RgmRule.gs — rgmMap_: per Big Region, RGM di rantai atasan orang-orang ber-Big Region itu. */
function rgmMap_(array $meta): array {
    $by = []; $out = [];
    foreach ($meta['people'] ?? [] as $p) $by[(string) $p['nik']] = $p;
    foreach ($meta['people'] ?? [] as $p) {
        $big = $p['wilayah'] ?? null; $n = $p['nik']; $seen = [];
        if (!truthy($big) || ($p['role'] ?? null) === 'RGM') continue;
        while (truthy($n) && isset($by[(string) $n]) && !isset($seen[(string) $n])) {
            $seen[(string) $n] = 1; $q = $by[(string) $n];
            if (($q['role'] ?? null) === 'RGM') {
                $bk = (string) $big; $qk = (string) $q['nik'];
                if (!isset($out[$bk][$qk])) $out[$bk][$qk] = ['nik' => $q['nik'], 'nama' => $q['nama'] ?? null, 'count' => 0, 'via' => []];
                $out[$bk][$qk]['count']++;
                if (count($out[$bk][$qk]['via']) < 5) $out[$bk][$qk]['via'][] = js_str($p['nama'] ?? null) . ' (' . js_str($p['role'] ?? null) . ')';
                break;
            }
            $n = $q['atasan'] ?? null;
        }
    }
    return $out;
}

/* Kanal ONLINE: region di Region_Map dengan KANAL = ONLINE. (cache per request) */
function onlineSet_(): array {
    static $memo = null;
    if ($memo !== null) return $memo;
    $out = ['regions' => [], 'bigs' => []];
    try {
        foreach (table_('Region_Map') as $r) {
            if (js_upper(PortalCore::text($r['KANAL'] ?? null)) === 'ONLINE') {
                $rg = PortalCore::text($r['REGION'] ?? null); $bg = PortalCore::text($r['WILAYAH_KPI'] ?? null);
                if ($rg !== '') $out['regions'][$rg] = 1; if ($bg !== '') $out['bigs'][$bg] = 1;
            }
        }
    } catch (Throwable $e) {}
    return $memo = $out;
}
function isOnlinePerson_($p, $on = null): bool {
    $on = $on ?? onlineSet_();
    if (!$p) return false;
    $k1 = (string) js_or($p['portfolio'] ?? null, js_or($p['region'] ?? null, ''));
    $k2 = (string) js_or($p['region'] ?? null, '');
    $k3 = (string) js_or($p['wilayah'] ?? null, '');
    return isset($on['regions'][$k1]) || isset($on['regions'][$k2]) || isset($on['bigs'][$k3]);
}
function allNiks_(array $db): array { return array_map(fn($p) => $p['nik'], $db['meta']['people']); }

function apiExec($token, $period, $selection = null) {
    $c = anaCtx_($token, $period, $selection); $period = js_str($period);
    // RGM: summary nasional & antar Big Region terlihat, sub region hanya milik sendiri
    $own = []; foreach ($c['ids'] as $n) $own[(string) $n] = 1;
    $role = $c['u']['role']; $isRgm = $role === 'RGM'; $isAsm = $role === 'ASM'; $isSales = $role === 'SALES'; $low = $isAsm || $isSales;
    $ownBig = []; $ownSub = []; $me = null; $team = [];
    $level = $isSales ? 'SALES' : ($isAsm ? 'ASM' : ($isRgm ? 'RGM' : 'TOP'));
    $uNik = $c['u']['nik'];
    if ($isRgm || $low) {
        foreach ($c['db']['meta']['people'] as $p) {
            $pn = (string) $p['nik'];
            if (isset($own[$pn]) && truthy($p['wilayah'] ?? null)) $ownBig[(string) $p['wilayah']] = 1;
            if (isset($own[$pn]) && truthy($p['sales'] ?? null)) $ownSub[(string) js_or($p['portfolio'] ?? null, js_or($p['region'] ?? null, '(tanpa Region)'))] = 1;
            if ($p['nik'] === $uNik) $me = $p;
        }
        if ($isSales && $me) {
            if (truthy($me['wilayah'] ?? null)) $ownBig[(string) $me['wilayah']] = 1;
            foreach ($c['db']['meta']['people'] as $p) if (truthy($p['sales'] ?? null) && truthy($p['atasan'] ?? null) && $p['atasan'] === ($me['atasan'] ?? null)) $team[(string) $p['nik']] = 1;
            $team[(string) $me['nik']] = 1;
        }
        $all = allNiks_($c['db']);
        try { $v = coverageView_($c['source'], $period, $all, 'ST', $c['u']); $c['db'] = $v; $c['ids'] = $all; foreach ($v['meta']['people'] as $p) $c['people'][(string) $p['nik']] = $p; } catch (Throwable $e) {}
    }
    // Pemimpin kartu
    $LEADB = []; $LEADS = []; $PBY = []; foreach ($c['db']['meta']['people'] as $p) $PBY[(string) $p['nik']] = $p;
    foreach ($c['db']['meta']['people'] as $p) {
        $sub = (truthy($p['sales'] ?? null) || ($p['role'] ?? null) === 'ASM') ? js_or($p['portfolio'] ?? null, js_or($p['region'] ?? null, '')) : '';
        $big = js_or($p['wilayah'] ?? null, ''); $seen = []; $n = $p['nik'];
        while (truthy($n) && isset($PBY[(string) $n]) && !isset($seen[(string) $n])) {
            $seen[(string) $n] = 1; $q = $PBY[(string) $n];
            if ($q['role'] === 'RGM' && truthy($big)) $LEADB[(string) $q['nik']][(string) $big] = 1;
            if ($q['role'] === 'ASM' && truthy($sub)) $LEADS[(string) $q['nik']][(string) $sub] = 1;
            $n = $q['atasan'] ?? null;
        }
    }
    $ON = onlineSet_(); $onl = [];
    foreach ($c['db']['meta']['people'] as $p) if (isOnlinePerson_($p, $ON)) $onl[(string) $p['nik']] = 1;
    if ($low) $c['ids'] = js_filter($c['ids'], fn($n) => !isset($onl[(string) $n]));
    $idSet0 = js_set($c['ids']);
    $detailOK = function (string $k) use ($level, $ownBig, $own, $uNik) {
        if ($level === 'TOP') return true;
        $pr = explode('|', $k);
        if ($level === 'RGM') return $pr[0] === 'N' || $pr[0] === 'B' || ($pr[0] === 'S' && isset($ownBig[$pr[1]]));
        if ($level === 'ASM') return $k === 'O' || ($pr[0] === 'P' && isset($own[$pr[1]])) || ($pr[0] === 'S' && isset($ownBig[$pr[1]]));
        return $k === 'O' || $k === 'P|' . js_str($uNik);
    };
    $fullOK = function (string $k) use ($level, $ownSub, $detailOK) { $pr = explode('|', $k); if ($level === 'ASM' && $pr[0] === 'S') return isset($ownSub[$pr[2] ?? '']); return $detailOK($k); };
    $db = $c['db']; $meta = $db['meta'];
    $prev = anaPrev_($period, 1); $prevEnd = PortalCore::end($prev); $cut = $c['cutoff'];
    $pc = $prev . '-' . substr($cut, 8);
    $prevCut = $c['closed'] ? $prevEnd : (strcmp($pc, $prevEnd) <= 0 ? $pc : $prevEnd);
    $wk = anaWork_($period, $cut, $c['closed']); $brands = anaDeviceBrands_($meta); $devSet = [];
    foreach ($brands as $b) $devSet[$b] = 1;
    $P = $c['people'];
    // Kredit tetap ke penjual + region kartu "TERTENTU": semua penjualan orang itu dihitung di region pilihan (juga targetnya).
    $bigOf = function ($nik) use ($P, $meta, $period) {
        if (function_exists('keepSellerRule_')) { $ru = keepSellerRule_($meta, (string) $nik, $period); if ($ru && $ru['region_mode'] === 'TERTENTU' && $ru['region_value'] !== '') return $ru['region_value']; }
        return js_or($P[(string) $nik]['wilayah'] ?? null, '(tanpa Big Region)');
    };
    $subOf = function ($nik) use ($P) { $p = $P[(string) $nik] ?? []; return js_or($p['portfolio'] ?? null, js_or($p['region'] ?? null, '(tanpa Region)')); };
    $keysOf = function ($nik) use ($bigOf, $subOf, $low, $own, $isAsm, $isSales, $team) {
        $b = js_str($bigOf($nik)); $s = js_str($subOf($nik)); $k = ['N', 'B|' . $b, 'S|' . $b . '|' . $s]; $ns = (string) $nik;
        if ($low && isset($own[$ns])) $k[] = 'O';
        if ($isAsm && isset($own[$ns])) $k[] = 'P|' . $ns;
        if ($isSales && isset($team[$ns])) { $k[] = 'T'; $k[] = 'P|' . $ns; }
        return $k;
    };
    // Region kartu "PEMILIK" untuk penjual kredit-tetap: baris masuk region pemilik dealer-brand, kredit tetap ke penjual.
    $dbaIdx = function_exists('dbaIndex_') ? dbaIndex_() : [];
    $pemOwner = function ($r) use ($meta, $P, $db, $dbaIdx) {
        if (!function_exists('keepSellerRule_')) return '';
        $ru = keepSellerRule_($meta, (string) $r['nik'], substr((string) ($r['date'] ?? ''), 0, 7));
        if (!$ru || $ru['region_mode'] !== 'PEMILIK') return '';
        try { $o = dealerBrandOwner_($db, (string) $r['customer'], (string) $r['brand'], (string) $r['date'], $dbaIdx); } catch (Throwable $e) { return ''; }
        $n = (string) ($o['nik'] ?? ''); return ($n !== '' && isset($P[$n])) ? $n : '';
    };
    $rowKeys = function ($r) use ($keysOf, $pemOwner) {
        $ow = $pemOwner($r); if ($ow === '') return $keysOf($r['nik']);
        $isArea = fn($k) => $k === 'N' || strpos($k, 'B|') === 0 || strpos($k, 'S|') === 0;
        return array_values(array_unique(array_merge(array_values(array_filter($keysOf($ow), $isArea)), array_values(array_filter($keysOf($r['nik']), fn($k) => !$isArea($k))))));
    };
    // Penjualan oleh sales dari luar area (untuk chip di kartu region): [region][brand][penjual] = ['q','d'=>[dealer=>q]]
    $extAcc = [];
    $addExt = function (string $big, string $brand, string $seller, string $dealer, int $q) use (&$extAcc) {
        if ($q <= 0) return; $x = &$extAcc[$big][$brand][$seller]; $x['q'] = ($x['q'] ?? 0) + $q; $x['d'][$dealer] = ($x['d'][$dealer] ?? 0) + $q;
    };
    // Pembanding apple-to-apple
    $covKeys = js_sort_default(js_keys($db['coverage'] ?? [])); $latestP = js_last($covKeys); $cmpOn = $period === $latestP;
    $ownerNow = function ($r) use ($cmpOn, $meta, $P) { if (!$cmpOn) return $r['nik']; if (function_exists('keepSellerCheck_') && keepSellerCheck_($meta, (string) $r['nik'], substr((string) ($r['date'] ?? ''), 0, 7))) return $r['nik']; $cu = $meta['customers'][$r['customer']] ?? null; $n = $cu ? ($cu['nik'] ?? null) : null; return truthy($n) && isset($P[(string) $n]) ? $n : $r['nik']; };
    $remap = function (array $rows) use ($ownerNow) { $o = []; foreach ($rows as $r) { $n = $ownerNow($r); if ($n === $r['nik']) { $o[] = $r; continue; } $r['nik'] = $n; $o[] = $r; } return $o; };
    $N = []; $daily = [];
    $slot = function ($k, $brand) use (&$N) { if (!isset($N[$k][$brand])) $N[$k][$brand] = ['st' => [0, 0, 0, 0], 'om' => [0, 0, 0, 0], 'noo' => [0, 0, 0, 0], 'so' => [0, 0, 0, 0], 'da' => [0, 0, 0], 'p' => [[], [], []]]; };
    $each = function ($nik, $brand, callable $fn) use (&$N, $slot, $keysOf) {
        foreach (is_array($nik) ? $nik : $keysOf($nik) as $k) { $slot($k, $brand); $fn($N[$k][$brand]); $slot($k, 'ALL'); $fn($N[$k]['ALL']); }
    };
    // Hari data sebelumnya (untuk "dibanding kemarin"): tanggal terbesar < cutoff yang punya transaksi.
    $pd = ''; foreach (PortalCore::slice($db, $period, $c['ids'], $cut) as $r0) if (strcmp((string) $r0['date'], (string) $cut) < 0 && strcmp((string) $r0['date'], $pd) > 0) $pd = (string) $r0['date'];
    $addRows = function (array $rows, int $side) use ($pd, $devSet, $cut, $each, $onl, $keysOf, $fullOK, &$daily, $rowKeys, $pemOwner, $bigOf, $addExt) {
        foreach ($rows as $r) {
            $dev = isset($devSet[$r['brand']]); $sides = $side === 0 && $r['date'] === $cut ? [0, 2] : ($side === 0 && $pd !== '' && $r['date'] === $pd ? [0, 3] : [$side]);
            foreach ($sides as $sd) {
                $each($rowKeys($r), $r['brand'], function (&$x) use ($dev, $sd, $r, $onl) {
                    if ($dev) $x['st'][$sd] += $r['q']; $x['om'][$sd] += js_or($r['a'] ?? null, 0);
                    if ($dev && $r['q'] > 0 && !isset($onl[(string) $r['nik']])) { $pk = $r['customer'] . '|' . $r['brand']; $x['p'][$sd][$pk] = ($x['p'][$sd][$pk] ?? 0) + $r['q']; }
                });
            }
            if ($side === 0 && $dev && $r['q'] > 0 && ($ow = $pemOwner($r)) !== '') { $ob = $bigOf($ow); if ($ob !== $bigOf($r['nik'])) $addExt($ob, (string) $r['brand'], (string) $r['nik'], (string) $r['customer'], (int) $r['q']); }
            if ($side === 0 && $dev && truthy($r['q'])) foreach (js_filter($rowKeys($r), $fullOK) as $k) { $daily[$k][$r['date']][$r['brand']] = ($daily[$k][$r['date']][$r['brand']] ?? 0) + $r['q']; }
        }
    };
    $addRows(PortalCore::slice($db, $period, $c['ids'], $cut), 0);
    if (isset($db['cubes'][$prev])) $addRows(js_filter($remap(PortalCore::slice($db, $prev, allNiks_($db), $prevCut)), fn($r) => isset($idSet0[(string) $r['nik']])), 1);
    // Penjualan lintas area oleh penjual non-kredit-tetap (dialihkan ke pemilik dealer): dari tabel atribusi.
    try {
        if (function_exists('txAttrSchema_')) {
            txAttrSchema_();
            $qa = db_()->prepare("SELECT raw_nik, eff_nik, induk, brand, SUM(qty) AS q FROM dsm_attr WHERE period = ? AND tx_date <= ? AND raw_nik <> eff_nik AND marker IN ('TRANSFER','ST_BERBEDA','KOREKSI') AND qty > 0 GROUP BY raw_nik, eff_nik, induk, brand");
            $qa->execute([$period, $cut]);
            foreach ($qa->fetchAll(PDO::FETCH_ASSOC) as $a) {
                if (!isset($devSet[$a['brand']]) || !isset($P[(string) $a['eff_nik']])) continue;
                $eb = $bigOf($a['eff_nik']); $rb = $bigOf($a['raw_nik']);
                if ($eb !== $rb) $addExt($eb, (string) $a['brand'], (string) $a['raw_nik'], (string) $a['induk'], (int) $a['q']);
            }
        }
    } catch (Throwable $e) {}
    // A2: repeat order per node = dealer yang order bulan lalu (penuh) DAN bulan ini (MTD). [dasar bulan lalu, sudah repeat]
    $repOut = [];
    try {
        $mtSet = []; $lmSet = [];
        foreach (PortalCore::slice($db, $period, $c['ids'], $cut) as $r) {
            if (!isset($devSet[$r['brand']]) || !($r['q'] > 0)) continue;
            foreach ($rowKeys($r) as $k) if ($detailOK($k)) $mtSet[$k][(string) $r['customer']] = 1;
        }
        if (isset($db['cubes'][$prev])) foreach (js_filter($remap(PortalCore::slice($db, $prev, allNiks_($db), null)), fn($r) => isset($idSet0[(string) $r['nik']])) as $r) {
            if (!isset($devSet[$r['brand']]) || !($r['q'] > 0)) continue;
            foreach ($rowKeys($r) as $k) if ($detailOK($k)) $lmSet[$k][(string) $r['customer']] = 1;
        }
        foreach ($lmSet as $k => $cs) { $rp = 0; foreach ($cs as $cu => $_) if (isset($mtSet[$k][$cu])) $rp++; $repOut[$k] = [count($cs), $rp]; }
    } catch (Throwable $e) { $repOut = []; }
    // Omzet per badan usaha (total saja, tanpa brand): langsung dari kolom BADAN USAHA transaksi ST, hanya node yang boleh dilihat.
    $coAcc = [];
    try {
        $rawDb = $db['raw'] ?? (function_exists('snapshot_') ? (snapshot_()['raw'] ?? []) : []);
        foreach ([[$period, $cut, 0], [$prev, $prevCut, 1]] as [$mo, $mc, $sd]) {
            if (!isset($rawDb[$mo])) continue;
            foreach (txEffRaw_($meta, dataRows_((string) $rawDb[$mo]), true) as $t) {
                $dt = text_($t['date'] ?? null); if ($dt === '' || strcmp($dt, $mc) > 0 || substr($dt, 0, 7) !== $mo) continue;
                $nk = (string) text_($t['nik'] ?? null);
                if ($sd === 1 && isset($db['cubes'])) { $own2 = $meta['customers'][$t['customer'] ?? ''] ?? null; $n2 = $own2 ? ($own2['nik'] ?? null) : null; if ($cmpOn && truthy($n2) && isset($P[(string) $n2]) && !(function_exists('keepSellerCheck_') && keepSellerCheck_($meta, $nk, $mo))) $nk = (string) $n2; }
                if (!isset($idSet0[$nk]) || !isset($P[$nk])) continue;
                $co = trim((string) text_($t['company'] ?? null)); if ($co === '') $co = '(tanpa badan usaha)';
                $am = (float) ($t['amount'] ?? 0);
                foreach ($keysOf($nk) as $k) {
                    if (!$detailOK($k)) continue;
                    $coAcc[$k][$co][$sd] = ($coAcc[$k][$co][$sd] ?? 0) + $am;
                    if ($sd === 0 && $dt === $cut) $coAcc[$k][$co][2] = ($coAcc[$k][$co][2] ?? 0) + $am;
                }
            }
        }
        foreach ($coAcc as $k => &$cc) foreach ($cc as $co => &$v) $v = [round($v[0] ?? 0), round($v[1] ?? 0), round($v[2] ?? 0)];
        unset($cc, $v);
    } catch (Throwable $e) { $coAcc = []; }
    foreach ($N as $k => &$nb) foreach ($nb as $b => &$x) { foreach ([0, 1, 2] as $sd) { $cnt = 0; foreach ($x['p'][$sd] as $v) if ($v >= 2) $cnt++; $x['da'][$sd] = $cnt; } unset($x['p']); }
    unset($nb, $x);
    $idSet = js_set($c['ids']);
    $first = $db['first'] ?? [];
    foreach (js_keys($first) as $k) {
        $f = $first[$k]; if (!isset($idSet[(string) $f['nik']]) || isset($onl[(string) $f['nik']])) continue; $br = json_decode($k, true)[1]; if (!isset($devSet[$br])) continue;
        $sd = strcmp($f['date'], $period . '-01') >= 0 && strcmp($f['date'], $cut) <= 0 ? 0 : (strcmp($f['date'], $prev . '-01') >= 0 && strcmp($f['date'], $prevCut) <= 0 ? 1 : -1); if ($sd < 0) continue;
        $each($f['nik'], $br, function (&$x) use ($sd, $f, $cut, $pd) { $x['noo'][$sd]++; if ($sd === 0 && $f['date'] === $cut) $x['noo'][2]++; if ($sd === 0 && $pd !== '' && $f['date'] === $pd) $x['noo'][3]++; });
    }
    $soReady = false;
    try {
        $pv = coverageView_($c['source'], $period, $c['ids'], 'PAIR', $c['u']);
        if (!empty($pv['stock']) && !truthy($pv['missingSO'] ?? null)) {
            $soReady = true;
            foreach (stockMoves_($pv, $prev . '-01', $cut, ['out' => true]) as $t) {
                if (!truthy($t['outQty'] ?? null)) continue;
                $sd = strcmp($t['date'], $period . '-01') >= 0 && strcmp($t['date'], $cut) <= 0 ? 0 : (strcmp($t['date'], $prev . '-01') >= 0 && strcmp($t['date'], $prevCut) <= 0 ? 1 : -1); if ($sd < 0) continue;
                $p = $meta['products'][$t['sku']] ?? null; if (!$p || !truthy($p['device'] ?? null)) continue;
                $ow = isset($pv['stock']['owners']) && truthy($pv['stock']['owners']) ? ($pv['stock']['owners'][$t['customer']] ?? null) : '';
                if (!truthy($ow) || !isset($idSet[(string) $ow]) || isset($onl[(string) $ow])) continue;
                $each($ow, $p['brand'], function (&$x) use ($sd, $t, $cut, $pd) { $x['so'][$sd] += $t['outQty']; if ($sd === 0 && $t['date'] === $cut) $x['so'][2] += $t['outQty']; if ($sd === 0 && $pd !== '' && $t['date'] === $pd) $x['so'][3] += $t['outQty']; });
            }
        }
    } catch (Throwable $e) {}
    // target per node
    $sales = js_filter($meta['people'], fn($p) => isset($idSet[(string) $p['nik']]) && truthy($p['sales'] ?? null)); $teams = [];
    foreach ($sales as $p) foreach ($keysOf($p['nik']) as $k) $teams[$k][] = $p['nik'];
    $tgt = function ($team, $b, $m) use ($meta, $period) { $t = PortalCore::target($meta, $period, $team, $b, $m); return js_or($t['value'], 0); };
    foreach ($teams as $k => $tm) {
        $tOff = js_filter($tm, fn($n) => !isset($onl[(string) $n]));
        foreach (array_merge($brands, ['ALL']) as $b) {
            $slot($k, $b);
            if ($b === 'ALL') { $xt = ['st' => 0, 'da' => 0, 'noo' => 0]; foreach ($brands as $bb) foreach (['st', 'da', 'noo'] as $i => $m) $xt[$m] += $tgt($m === 'st' ? $tm : $tOff, $bb, ['QTY', 'DA', 'NOO'][$i]); $N[$k][$b]['t'] = $xt; }
            else $N[$k][$b]['t'] = ['st' => $tgt($tm, $b, 'QTY'), 'da' => $tgt($tOff, $b, 'DA'), 'noo' => $tgt($tOff, $b, 'NOO')];
        }
    }
    // Paket 10: target Big Region manual menggantikan jumlah target sales (hanya QTY & DA, hanya baris Big Region)
    foreach (array_keys($N) as $k) {
        if (strpos((string) $k, 'B|') !== 0 || !isset($N[$k]['ALL']['t'])) continue;
        $bigN = substr((string) $k, 2); $touched = false;
        foreach ($brands as $b) {
            if (!isset($N[$k][$b]['t'])) continue;
            $oq = rbBigTarget_($meta, $period, $bigN, $b, 'QTY'); $od = rbBigTarget_($meta, $period, $bigN, $b, 'DA');
            if ($oq !== null) { $N[$k][$b]['t']['st'] = $oq; $touched = true; }
            if ($od !== null) { $N[$k][$b]['t']['da'] = $od; $touched = true; }
        }
        if ($touched) { $sT = 0; $dT = 0; foreach ($brands as $b) { $sT += $N[$k][$b]['t']['st'] ?? 0; $dT += $N[$k][$b]['t']['da'] ?? 0; } $N[$k]['ALL']['t']['st'] = $sT; $N[$k]['ALL']['t']['da'] = $dT; }
    }
    // chip "dijual sales luar area": ringkasan penjual selalu; rincian dealer hanya untuk yang berhak melihat detail region itu
    $extOut = function (string $big, bool $withDealers) use (&$extAcc, $P, $meta, $bigOf) {
        if (empty($extAcc[$big])) return null; $out = []; $all = [];
        foreach ($extAcc[$big] as $brand => $sellers) foreach ($sellers as $nik => $v) {
            foreach ([$brand, 'ALL'] as $bk) {
                if ($bk === 'ALL') { $all[$nik]['q'] = ($all[$nik]['q'] ?? 0) + $v['q']; foreach ($v['d'] as $d => $q) $all[$nik]['d'][$d] = ($all[$nik]['d'][$d] ?? 0) + $q; }
                else { $out[$brand][$nik] = $v; }
            }
        }
        $out['ALL'] = $all; $res = [];
        foreach ($out as $bk => $sellers) {
            $list = [];
            foreach ($sellers as $nik => $v) {
                $pp = $P[(string) $nik] ?? null; $dl = [];
                if ($withDealers) { arsort($v['d']); foreach ($v['d'] as $dc => $q) $dl[] = ['c' => (string) $dc, 'n' => text_($meta['customers'][$dc]['nama'] ?? ''), 'q' => $q]; }
                $list[] = ['nik' => (string) $nik, 'nama' => $pp ? text_($pp['nama'] ?? '') : (string) $nik, 'role' => $pp ? text_($pp['role'] ?? '') : '', 'area' => $bigOf($nik), 'q' => (int) $v['q'], 'dealers' => $dl];
            }
            usort($list, fn($a, $b) => $b['q'] <=> $a['q']); $res[$bk] = $list;
        }
        return $res;
    };
    // nama & penanggung jawab
    $nodes = []; $rgmM = null;
    foreach ($N as $k => $_) {
        $k = (string) $k; $parts = explode('|', $k); $lead = '';
        if ($isRgm && $parts[0] === 'S' && !isset($ownBig[$parts[1]])) continue;
        if ($low && $parts[0] === 'B' && !isset($ownBig[$parts[1]])) continue;
        if ($low && $parts[0] === 'S' && !($isAsm ? isset($ownBig[$parts[1]]) : isset($ownSub[$parts[2]]))) continue;
        if (!$detailOK($k)) { $slim = []; if (isset($N[$k]['ALL'])) $slim['ALL'] = $N[$k]['ALL']; $N[$k] = $slim; }
        if ($parts[0] === 'B') {
            $rgmM = $rgmM ?? rgmMap_($meta);
            $rgmL = $rgmM[$parts[1]] ?? [];
            $rgmA = js_filter(js_sort(js_values($rgmL), fn($a, $b) => $b['count'] - $a['count']), fn($x) => isset($idSet[(string) $x['nik']]) || $c['u']['role'] !== 'RGM');
            $lead = $rgmA ? js_str($rgmA[0]['nama']) . (count($rgmA) > 1 ? ' ⚠ +' . (count($rgmA) - 1) . ' RGM lain (cek Kesehatan Master)' : '') : '';
        }
        if ($parts[0] === 'B' && $lead === '') { $rgmHere = js_filter($meta['people'], fn($p) => $p['role'] === 'RGM' && ($p['wilayah'] ?? null) === $parts[1]); $lead = $rgmHere ? implode(', ', array_map(fn($p) => js_str($p['nama'] ?? null), $rgmHere)) . ' (belum jadi atasan tim)' : ''; }
        if ($parts[0] === 'S') $lead = implode(', ', array_map(fn($p) => js_str($p['nama'] ?? null), js_filter($meta['people'], fn($p) => $p['role'] === 'ASM' && isset($LEADS[(string) $p['nik']][$parts[2]]))));
        $nm = $parts[0] === 'N' ? 'Nasional' : $parts[count($parts) - 1];
        if ($parts[0] === 'P') $nm = js_or($P[$parts[1]]['nama'] ?? null, $parts[1]);
        if ($k === 'O') $nm = $isSales ? js_or($me['nama'] ?? null, 'Saya') : implode(', ', js_keys($ownSub));
        if ($k === 'T') { $boss = js_find($meta['people'], fn($p) => $me && $p['nik'] === ($me['atasan'] ?? null)); $nm = 'Tim ' . js_str(js_or($boss['nama'] ?? null, '')); }
        $nodes[$k] = ['online' => ($parts[0] === 'B' && isset($ON['bigs'][$parts[1]])) || ($parts[0] === 'S' && (isset($ON['bigs'][$parts[1]]) || isset($ON['regions'][$parts[2]]))), 'name' => $nm,
            'big' => $parts[0] === 'P' ? $bigOf($parts[1]) : js_or($parts[1] ?? null, ''), 'sub' => $parts[0] === 'P' ? $subOf($parts[1]) : js_or($parts[2] ?? null, ''), 'lead' => $lead, 'm' => js_obj($N[$k]), 'ext' => $parts[0] === 'B' ? $extOut($parts[1], $level === 'TOP' || ($level === 'RGM' && isset($ownBig[$parts[1]]))) : null];
    }
    // ASM: proyeksi SELL THRU
    $asm = [];
    foreach ($meta['people'] as $p) {
        $pn = (string) $p['nik'];
        if (!($p['role'] === 'ASM' && !isset($onl[$pn]) && !isset($ON['bigs'][(string) js_or($p['wilayah'] ?? null, '')]) && (($isRgm || $low) ? isset($own[$pn]) : (isset($idSet[$pn]) || $c['u']['role'] === 'SUPER ADMIN')))) continue;
        $tm = js_filter(PortalCore::scope($meta['people'], $p['nik']), fn($n) => (($isRgm || $low) ? isset($own[(string) $n]) : isset($idSet[(string) $n])) && isset($P[(string) $n]) && truthy($P[(string) $n]['sales'] ?? null));
        if (!$tm) continue;
        $a = 0; foreach (PortalCore::slice($db, $period, $tm, $cut) as $r) if (isset($devSet[$r['brand']])) $a += $r['q'];
        $t = 0; foreach ($brands as $b) $t += $tgt($tm, $b, 'QTY');
        $proj = $wk['run'] ? $a / $wk['run'] * $wk['days'] : $a;
        $x = ['nama' => $p['nama'], 'region' => js_or($p['portfolio'] ?? null, js_or($p['region'] ?? null, '')), 'big' => js_or($p['wilayah'] ?? null, ''), 'actual' => $a, 'target' => $t, 'proj' => truthy($t) ? js_mround($proj / $t * 1000) / 10 : null];
        if ($x['proj'] !== null) $asm[] = $x;
    }
    // Heatmap organisasi untuk drill Big Region.
    // RGM: ASM / Sales x Brand. Top-level: Sub Region / ASM x Brand.
    // Paket heatmap menyertakan SELL THRU + DEALER AKTIF + NOO, target, dan LMTD
    // supaya fullscreen bisa menampilkan Target serta Growth vs LMTD tanpa query tambahan.
    $orgHeat = [];
    if ($isRgm || $level === 'TOP') {
        $allowedPerson = function (string $nik) use ($isRgm, $own, $idSet) {
            return $isRgm ? isset($own[$nik]) : isset($idSet[$nik]);
        };
        $allowedBig = function (string $bg) use ($isRgm, $ownBig) {
            return !$isRgm || isset($ownBig[$bg]);
        };

        $heatActual = []; $heatLmtd = [];
        $heatDealerActual = []; $heatDealerLmtd = [];
        $heatNooActual = []; $heatNooLmtd = [];
        $heatAdd = function (array $rows, bool $prevSide) use (&$heatActual, &$heatLmtd, &$heatDealerActual, &$heatDealerLmtd, $allowedPerson, $onl, $devSet) {
            foreach ($rows as $r) {
                $rn = (string) ($r['nik'] ?? '');
                $br = (string) ($r['brand'] ?? '');
                $q = js_or($r['q'] ?? null, 0);
                if (!$allowedPerson($rn) || isset($onl[$rn]) || !isset($devSet[$br]) || !truthy($q)) continue;
                if ($prevSide) $heatLmtd[$rn][$br] = ($heatLmtd[$rn][$br] ?? 0) + $q;
                else $heatActual[$rn][$br] = ($heatActual[$rn][$br] ?? 0) + $q;
                // Ikuti definisi DA existing: dealer-brand aktif bila akumulasi transaksi positif >= 2 unit.
                if ($q > 0) {
                    $cid = (string) ($r['customer'] ?? '');
                    if ($cid !== '') {
                        if ($prevSide) $heatDealerLmtd[$rn][$br][$cid] = ($heatDealerLmtd[$rn][$br][$cid] ?? 0) + $q;
                        else $heatDealerActual[$rn][$br][$cid] = ($heatDealerActual[$rn][$br][$cid] ?? 0) + $q;
                    }
                }
            }
        };
        try {
            $heatAdd(PortalCore::slice($db, $period, $c['ids'], $cut), false);
            if (isset($db['cubes'][$prev])) {
                $pr = $remap(PortalCore::slice($db, $prev, allNiks_($db), $prevCut));
                $heatAdd($pr, true);
            }
        } catch (Throwable $e) {
            $heatActual = []; $heatLmtd = []; $heatDealerActual = []; $heatDealerLmtd = []; $heatNooActual = []; $heatNooLmtd = [];
        }

        // NOO mengikuti definisi existing: first order dealer-brand yang jatuh pada MTD/LMTD.
        try {
            foreach (js_keys($first) as $fk) {
                $f = $first[$fk];
                $rn = (string) ($f['nik'] ?? '');
                if (!$allowedPerson($rn) || isset($onl[$rn])) continue;
                $parts = json_decode($fk, true); $br = (string) ($parts[1] ?? '');
                if ($br === '' || !isset($devSet[$br])) continue;
                $fd = (string) ($f['date'] ?? '');
                if ($fd >= $period . '-01' && $fd <= $cut) $heatNooActual[$rn][$br] = ($heatNooActual[$rn][$br] ?? 0) + 1;
                elseif ($fd >= $prev . '-01' && $fd <= $prevCut) $heatNooLmtd[$rn][$br] = ($heatNooLmtd[$rn][$br] ?? 0) + 1;
            }
        } catch (Throwable $e) { $heatNooActual = []; $heatNooLmtd = []; }

        $sumSt = function (array $tm, string $b, array $src): float {
            $v = 0; foreach ($tm as $sn) $v += js_or($src[(string) $sn][$b] ?? null, 0); return $v;
        };
        $countDa = function (array $tm, string $b, array $src): int {
            $by = [];
            foreach ($tm as $sn) foreach (($src[(string) $sn][$b] ?? []) as $cid => $q) $by[(string) $cid] = ($by[(string) $cid] ?? 0) + $q;
            $n = 0; foreach ($by as $q) if ($q >= 2) $n++; return $n;
        };
        $sumNoo = function (array $tm, string $b, array $src): int {
            $v = 0; foreach ($tm as $sn) $v += (int) ($src[(string) $sn][$b] ?? 0); return $v;
        };
        $heatPack = function (array $tm, string $b) use ($sumSt, $countDa, $sumNoo, $heatActual, $heatLmtd, $heatDealerActual, $heatDealerLmtd, $heatNooActual, $heatNooLmtd, $tgt): array {
            $stA = $sumSt($tm, $b, $heatActual); $stL = $sumSt($tm, $b, $heatLmtd);
            $daA = $countDa($tm, $b, $heatDealerActual); $daL = $countDa($tm, $b, $heatDealerLmtd);
            $nooA = $sumNoo($tm, $b, $heatNooActual); $nooL = $sumNoo($tm, $b, $heatNooLmtd);
            $stT = 0; $daT = 0; $nooT = 0;
            try { $stT = $tgt($tm, $b, 'QTY'); } catch (Throwable $e) { $stT = 0; }
            try { $daT = $tgt($tm, $b, 'DA'); } catch (Throwable $e) { $daT = 0; }
            try { $nooT = $tgt($tm, $b, 'NOO'); } catch (Throwable $e) { $nooT = 0; }
            return ['st' => ['a' => $stA, 'lm' => $stL, 't' => $stT], 'da' => ['a' => $daA, 'lm' => $daL, 't' => $daT], 'noo' => ['a' => $nooA, 'lm' => $nooL, 't' => $nooT]];
        };

        $salesHeat = [];
        $subTeams = [];
        foreach ($meta['people'] as $p) {
            $pn = (string) ($p['nik'] ?? '');
            if (!truthy($p['sales'] ?? null) || !$allowedPerson($pn) || isset($onl[$pn])) continue;
            $bg = (string) js_or($p['wilayah'] ?? null, '');
            if ($bg === '' || !$allowedBig($bg)) continue;
            $sub = (string) js_or($p['portfolio'] ?? null, js_or($p['region'] ?? null, '(tanpa Region)'));
            $mm = [];
            foreach ($brands as $b) $mm[$b] = $heatPack([$pn], $b);
            $salesHeat[$pn] = ['nik' => $pn, 'nama' => js_or($p['nama'] ?? null, $pn), 'big' => $bg,
                'sub' => $sub, 'm' => js_obj($mm)];
            $subTeams[$bg][$sub][] = $pn;
            if ($isRgm) $orgHeat[$bg]['sales'][] = $salesHeat[$pn];
        }

        // Agregasi Sub Region untuk level Nasional / Top Tier.
        foreach ($subTeams as $bg => $subs) {
            foreach ($subs as $sub => $tm) {
                $mm = []; foreach ($brands as $b) $mm[$b] = $heatPack($tm, $b);
                $orgHeat[$bg]['sub'][] = ['nik' => '', 'nama' => $sub, 'big' => $bg, 'sub' => $sub, 'm' => js_obj($mm)];
            }
        }

        foreach ($meta['people'] as $p) {
            $pn = (string) ($p['nik'] ?? '');
            if (($p['role'] ?? null) !== 'ASM' || !$allowedPerson($pn) || isset($onl[$pn])) continue;
            $bg = (string) js_or($p['wilayah'] ?? null, '');
            if ($bg === '' || !$allowedBig($bg)) continue;
            $tm = js_filter(PortalCore::scope($meta['people'], $pn), fn($n) => isset($salesHeat[(string) $n]));
            if (!$tm) continue;
            $mm = []; foreach ($brands as $b) $mm[$b] = $heatPack($tm, $b);
            $orgHeat[$bg]['asm'][] = ['nik' => $pn, 'nama' => js_or($p['nama'] ?? null, $pn), 'big' => $bg,
                'sub' => js_or($p['portfolio'] ?? null, js_or($p['region'] ?? null, '')), 'm' => js_obj($mm)];
        }

        foreach ($orgHeat as &$oh) {
            $levels = $isRgm ? ['asm','sales'] : ['sub','asm'];
            foreach ($levels as $lv) {
                if (!isset($oh[$lv])) $oh[$lv] = [];
                usort($oh[$lv], fn($a,$b) => strcmp((string)($a['nama'] ?? ''), (string)($b['nama'] ?? '')));
            }
        }
        unset($oh);
    }
    // KPI per Big Region
    $kpi = [];
    foreach ($teams as $k => $tm) {
        $k = (string) $k; if (strpos($k, 'B|') !== 0 || isset($ON['bigs'][substr($k, 2)])) continue;
        try { $r = PortalCore::kpi($db, $period, $tm, substr($k, 2)); $kpi[substr($k, 2)] = $r && $r['score'] !== null ? js_mround($r['score'] * 10) / 10 : null; } catch (Throwable $e) { $kpi[substr($k, 2)] = null; }
    }
    // history bulanan
    $hist = ['months' => js_filter(anaMonths_($db, $period, (int) substr($period, 5)), fn($m) => substr($m, 0, 4) === substr($period, 0, 4)), 'v' => [], 't' => []];
    foreach ($hist['months'] as $mo) {
        $endC = $mo === $period ? $cut : null;
        $rows = $mo === $period ? PortalCore::slice($db, $mo, $c['ids'], $endC) : js_filter($remap(PortalCore::slice($db, $mo, allNiks_($db), $endC)), fn($r) => isset($idSet0[(string) $r['nik']]));
        foreach ($rows as $r) {
            if (!isset($devSet[$r['brand']]) || !truthy($r['q'])) continue;
            foreach (js_filter($rowKeys($r), $fullOK) as $k) $hist['v'][$k][$mo][$r['brand']] = ($hist['v'][$k][$mo][$r['brand']] ?? 0) + $r['q'];
        }
        foreach ($teams as $k => $tm) {
            $k = (string) $k; if (!$fullOK($k)) continue;
            $b = []; $all = 0;
            foreach ($brands as $bb) { $v = 0; try { $v = js_or(PortalCore::target($meta, $mo, $tm, $bb, 'QTY')['value'], 0); } catch (Throwable $e) {} if (strpos($k, 'B|') === 0) { $ovh = rbBigTarget_($meta, $mo, substr($k, 2), $bb, 'QTY'); if ($ovh !== null) $v = $ovh; } if (truthy($v)) $b[$bb] = $v; $all += $v; }
            $b['ALL'] = $all; $hist['t'][$k][$mo] = $b;
        }
    }
    // kesehatan dealer
    $lastM = []; $curM = []; $curBQ = []; $prevBQ = [];
    $okNik = fn($n) => !isset($onl[(string) $n]) && (!($isRgm || $low) || isset($own[(string) $n]));
    if (isset($db['cubes'][$prev])) {
        foreach (PortalCore::slice($db, $prev, $c['ids'], null) as $r) if ($r['q'] > 0 && $okNik($r['nik'])) $lastM[(string) $r['customer']] = 1;
        // LMTD Dealer Aktif Uniq: cutoff tanggal yang sama, dealer dihitung unik bila minimal satu brand mencapai 2 unit.
        foreach (PortalCore::slice($db, $prev, $c['ids'], $prevCut) as $r) {
            if ($r['q'] <= 0 || !$okNik($r['nik']) || !isset($devSet[$r['brand']])) continue;
            $cid = (string) $r['customer']; $br = (string) $r['brand'];
            $prevBQ[$cid][$br] = ($prevBQ[$cid][$br] ?? 0) + $r['q'];
        }
    }
    foreach (PortalCore::slice($db, $period, $c['ids'], $cut) as $r) {
        if ($r['q'] > 0 && $okNik($r['nik'])) $curM[(string) $r['customer']] = 1;
        if ($r['q'] <= 0 || !$okNik($r['nik']) || !isset($devSet[$r['brand']])) continue;
        $cid = (string) $r['customer']; $br = (string) $r['brand'];
        $curBQ[$cid][$br] = ($curBQ[$cid][$br] ?? 0) + $r['q'];
    }
    $dealerAktifUniq = 0; foreach ($curBQ as $bs) { foreach ($bs as $q) if ($q >= 2) { $dealerAktifUniq++; break; } }
    $dealerAktifUniqLmtd = 0; foreach ($prevBQ as $bs) { foreach ($bs as $q) if ($q >= 2) { $dealerAktifUniqLmtd++; break; } }
    $tut = []; $pend = 0; $tutNow = 0; $scopeSet = ($isRgm || $low) ? $own : $idSet;
    try {
        $tut = dtClosed_();
        foreach (dtRows_() as $x) { $cu = $meta['customers'][$x['id']] ?? null; if (!$cu || !isset($scopeSet[(string) ($cu['nik'] ?? '')])) continue; if ($x['status'] === 'PENGAJUAN') $pend++; }
        foreach (js_keys($tut) as $id) { $cu = $meta['customers'][$id] ?? null; if ($cu && isset($scopeSet[(string) ($cu['nik'] ?? '')]) && substr($tut[$id], 0, 7) === $period) $tutNow++; }
    } catch (Throwable $e) {}
    $daLalu = 0; foreach (js_keys($lastM) as $id) if (!isset($curM[$id]) && !(truthy($tut[$id] ?? null) && strcmp($tut[$id], $cut) <= 0)) $daLalu++;
    // ===== ASM & SALES: KPI, dealer prioritas =====
    $kpiMe = null; $dealers = [];
    if ($low) {
        $ownIds = js_filter(js_keys($own), fn($n) => isset($P[$n]) && truthy($P[$n]['sales'] ?? null)); $bigName = js_keys($ownBig)[0] ?? '';
        try {
            $kk = PortalCore::kpi($db, $period, $ownIds, $bigName);
            $kpiMe = ['score' => $kk['score'], 'status' => $kk['status'], 'groups' => array_map(fn($g) => ['name' => $g['name'], 'score' => $g['score'], 'ind' => array_map(fn($x) => ['label' => js_or($x['label'] ?? null, $x['metric'] . (truthy($x['brand']) && $x['brand'] !== 'ALL' ? ' ' . $x['brand'] : '')), 'metric' => $x['metric'], 'brand' => $x['brand'], 'weight' => $x['weight'], 'target' => $x['target'], 'actual' => $x['actual'], 'ach' => $x['ach'], 'state' => $x['state']], $g['indicators'])], $kk['groups'] ?? [])];
        } catch (Throwable $e) {}
        $stock = [];
        try { $sc = StockCore::calculate($c['source'], $period, $ownIds, []); if ($sc && truthy($sc['ready'])) foreach ($sc['rows'] ?? [] as $t) { $id = (string) js_or($t['customer'] ?? null, $t['id'] ?? null); $stock[$id] = ($stock[$id] ?? 0) + js_or($t['stock'] ?? null, 0); } } catch (Throwable $e) {}
        $D = [];
        $dl = function ($id) use (&$D, $meta) { $ks = (string) $id; if (!isset($D[$ks])) { $cu = $meta['customers'][$ks] ?? []; $D[$ks] = ['id' => $id, 'nama' => js_or($cu['nama'] ?? null, $id), 'nik' => js_or($cu['nik'] ?? null, ''), 'hp' => js_or($cu['hp'] ?? null, ''), 'kota' => js_or($cu['kota'] ?? null, ''), 'mtd' => 0, 'lmtd' => 0, 'lm' => 0, 'br' => [], 'lmBr' => [], 'n' => 0, 'noo' => null]; } return $ks; };
        foreach (PortalCore::slice($db, $period, $ownIds, $cut) as $r) { if (!isset($devSet[$r['brand']]) || !truthy($r['q'])) continue; $i = $dl($r['customer']); $D[$i]['mtd'] += $r['q']; $D[$i]['br'][$r['brand']] = ($D[$i]['br'][$r['brand']] ?? 0) + $r['q']; $D[$i]['n']++; }
        $ownSet = js_set($ownIds); $prevOwn = fn($cut2) => js_filter($remap(PortalCore::slice($db, $prev, allNiks_($db), $cut2)), fn($r) => isset($ownSet[(string) $r['nik']]));
        if (isset($db['cubes'][$prev])) {
            foreach ($prevOwn($prevCut) as $r) if (isset($devSet[$r['brand']]) && truthy($r['q'])) { $i = $dl($r['customer']); $D[$i]['lmtd'] += $r['q']; }
            foreach ($prevOwn(null) as $r) if (isset($devSet[$r['brand']]) && truthy($r['q'])) { $i = $dl($r['customer']); $D[$i]['lm'] += $r['q']; $D[$i]['lmBr'][$r['brand']] = ($D[$i]['lmBr'][$r['brand']] ?? 0) + $r['q']; }
        }
        foreach (js_keys($first) as $k) { $f = $first[$k]; if (!isset($own[(string) $f['nik']]) || strcmp($f['date'], $period . '-01') < 0 || strcmp($f['date'], $cut) > 0) continue; $id = (string) json_decode($k, true)[0]; if (isset($D[$id]) && (!truthy($D[$id]['noo']) || strcmp($f['date'], $D[$id]['noo']) < 0)) $D[$id]['noo'] = $f['date']; }
        $tutC = []; try { $tutC = dtClosed_(); } catch (Throwable $e) {}
        foreach (js_keys($D) as $id) {
            if (truthy($tutC[$id] ?? null) && strcmp($tutC[$id], $cut) <= 0) continue;
            $x = $D[$id]; $x['br'] = js_obj($x['br']); $x['lmBr'] = js_obj($x['lmBr']); $x['stok'] = isset($stock[$id]) ? max(0, js_mround($stock[$id])) : null; $dealers[] = $x;
        }
    }
    $adminOut = null;
    if (in_array($c['u']['role'], ['ADMIN', 'SUPER ADMIN'], true)) {
        try {
            $ad = ['period' => $period, 'stCut' => text_($db['coverage'][$period]['cutoff'] ?? ''), 'soCut' => text_($db['so']['coverage'][$period]['cutoff'] ?? ''), 'review' => 0, 'corrected' => 0, 'locked' => false, 'lockReason' => '', 'stockOk' => $soReady];
            try { txAttrSchema_(); $q = db_()->prepare('SELECT SUM(review = 1), SUM(marker = ?) FROM dsm_attr WHERE period = ?'); $q->execute(['KOREKSI', $period]); $r0 = $q->fetch(PDO::FETCH_NUM); $ad['review'] = (int) ($r0[0] ?? 0); $ad['corrected'] = (int) ($r0[1] ?? 0); } catch (Throwable $e) {}
            foreach (optionalTable_('Period_Locks') as $r) if (($r['PERIODE'] ?? null) === $period) { $ad['locked'] = truthy($r['TERKUNCI'] ?? null) && $r['TERKUNCI'] !== 'FALSE'; $ad['lockReason'] = text_($r['ALASAN'] ?? ''); }
            $adminOut = $ad;
        } catch (Throwable $e) { $adminOut = null; }
    }
    $visOut = [];
    if ($level === 'TOP' || $level === 'RGM') {
        try { foreach (rbVisibility_($db, $period) as $bigV => $bsV) foreach ($bsV as $bV => $v) $visOut[$bigV][$bV] = [$v['show'] ? 1 : 0, $v['mode'] !== '' ? 1 : 0, $v['q']]; } catch (Throwable $e) { $visOut = []; }
    }
    return ['onlineBigs' => $level === 'TOP' || $level === 'RGM' ? js_keys($ON['bigs']) : [], 'level' => $level, 'me' => $uNik, 'kpiMe' => $kpiMe, 'dealers' => $dealers, 'ownSub' => js_keys($ownSub), 'period' => $period, 'prev' => $prev, 'cutoff' => $cut, 'prevCutoff' => $prevCut, 'closed' => $c['closed'], 'days' => $wk['days'], 'run' => $wk['run'], 'brands' => $brands, 'soReady' => $soReady,
        'vis' => js_obj($visOut), 'pd' => $pd, 'nodes' => js_obj($nodes), 'co' => js_obj($coAcc), 'rep' => js_obj($repOut), 'admin' => $adminOut, 'daily' => js_obj($daily), 'hist' => ['months' => $hist['months'], 'v' => js_obj($hist['v']), 't' => js_obj($hist['t'])], 'ownBig' => ($isRgm || $low) ? js_keys($ownBig) : null, 'asm' => $asm, 'orgHeat' => js_obj($orgHeat), 'kpi' => js_obj($kpi), 'dealer' => ['daLaluBelum' => $daLalu, 'tutupBulanIni' => $tutNow, 'pengajuan' => $pend, 'aktifUniq' => $dealerAktifUniq, 'aktifUniqLmtd' => $dealerAktifUniqLmtd]];
}
