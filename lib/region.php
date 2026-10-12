<?php
/** RegionCore + coverageView_ (terjemahan RegionalCore.gs & RegioanlImport.gs). */
require_once __DIR__ . '/stock.php';

final class RegionCore {
    public static function top($role): bool { return in_array($role, ['HEAD OF SALES', 'CHIEF OPERATING OFFICER', 'CHIEF COMMERCIAL OFFICER', 'BOARD OF DIRECTOR'], true); }

    public static function regions(array $meta): array {
        $l = [];
        foreach ($meta['people'] as $p) if (truthy($p['wilayah'] ?? '')) $l[] = $p['wilayah'];
        foreach ($meta['regionList'] ?? [] as $r) $l[] = $r;
        return js_sort_default(uniq($l));
    }

    public static function common($parts, string $kind, string $period, array $selected): array {
        $cuts = array_map(fn($r) => js_or($parts[$kind][$period][$r]['cutoff'] ?? null, null), $selected);
        $all = $cuts && js_every($cuts, fn($c) => truthy($c));
        $nn = js_sort_default(js_filter($cuts, fn($c) => truthy($c)));
        $missing = []; $rows = [];
        foreach (array_values($selected) as $i => $r) { if (!truthy($cuts[$i])) $missing[] = $r; $rows[] = ['region' => $r, 'cutoff' => $cuts[$i]]; }
        return ['cutoff' => $all ? js_sort_default($cuts)[0] : null, 'latest' => $nn ? end($nn) : null, 'missing' => $missing, 'rows' => $rows];
    }

    /** RegionCore.route(db, t, kind): region tujuan satu baris ST/SO. */
    public static function route(array $db, array $t, string $kind) {
        if (truthy($t['region'] ?? null)) return $t['region'];
        $cu = custInduk_($db['meta'], $t['customer'] ?? null);
        $edits = [];
        foreach ($db['meta']['dealerRegions'] ?? [] as $r) if (($r['customer'] === $cu || $r['customer'] === ($t['customer'] ?? null)) && strcmp($r['from'], $t['date']) <= 0) $edits[] = $r;
        if ($edits) { $edits = js_sort($edits, fn($a, $b) => lc($b['from'], $a['from'])); return $edits[0]['region']; }
        $rt = $db['routing'] ?? null;
        $v = $rt['customers'][$cu] ?? null;
        if (!truthy($v)) $v = $rt['customers'][$t['customer'] ?? ''] ?? null;
        if (!truthy($v)) $v = $kind === 'ST' ? ($rt['sales'][$t['nik'] ?? ''] ?? null) : null;
        return $v;
    }

    /** RegionCore.routing(meta, tx, previous) */
    public static function routing(array $meta, array $tx, $previous): array {
        $result = $previous ?? ['customers' => [], 'sales' => []];
        $result['customers'] = $result['customers'] ?? []; $result['sales'] = $result['sales'] ?? [];
        foreach ($meta['people'] as $p) if (truthy($p['wilayah'] ?? null) && !truthy($result['sales'][$p['nik']] ?? null)) $result['sales'][$p['nik']] = $p['wilayah'];
        foreach ($meta['customers'] as $c) if (!truthy($result['customers'][$c['id']] ?? null) && truthy($result['sales'][$c['nik']] ?? null)) $result['customers'][$c['id']] = $result['sales'][$c['nik']];
        $choices = []; $f = custMap_($meta);
        foreach ($tx as $t) {
            $code = text_($t['customer'] ?? null); $cu = $code !== '' ? $f($code) : $code;
            $s = $result['sales'][$t['nik']] ?? null;
            if (truthy($s)) $choices[$cu][(string) $s] = $s;
        }
        foreach (js_keys($choices) as $c) if (!truthy($result['customers'][$c] ?? null) && count($choices[$c]) === 1) $result['customers'][$c] = reset($choices[$c]);
        return $result;
    }

    /** RegionCore.replace(parts, kind, period, selected, incoming) */
    public static function replace($parts, string $kind, string $period, array $selected, array $incoming): array {
        $out = $parts ?? [];
        if (!isset($out[$kind])) $out[$kind] = [];
        if (!isset($out[$kind][$period])) $out[$kind][$period] = [];
        foreach ($selected as $r) {
            if (!truthy($incoming[$r] ?? null)) fail_('Region belum memiliki deklarasi data: ' . $r);
            $out[$kind][$period][$r] = $incoming[$r];
        }
        return $out;
    }

    public static function sliceFor(array $db, string $period, array $ids, string $kind): array {
        $set = js_set($ids); $l = []; $every = true;
        foreach ($db['meta']['people'] as $p) {
            if (isset($set[(string) $p['nik']])) {
                foreach ($p['role'] === 'ADMIN' ? ($p['adminRegions'] ?? []) : [$p['wilayah'] ?? null] as $r) if (truthy($r)) $l[] = $r;
            } else $every = false;
        }
        if ($every) return self::regions($db['meta']);
        return uniq($l);
    }
}

function monthSequence_(string $from, string $to): array {
    $out = [];
    for ($d = $from . '-01'; strcmp($d, $to . '-01') <= 0; $d = StockCore::shift(PortalCore::end(substr($d, 0, 7)), 1)) $out[] = substr($d, 0, 7);
    return $out;
}

function coverageView_(array $db, string $period, array $ids, string $kind, $actor = null): array {
    PortalCore::month($period); // pengaman: di Apps Script periode rusak bikin loop tanpa akhir
    $selected = !empty($db['_tm']) ? $db['_tm']['regions'] : RegionCore::sliceFor($db, $period, $ids, $kind);
    if (($actor['role'] ?? null) === 'ADMIN') $selected = js_filter($selected, fn($r) => in_array($r, $actor['adminRegions'] ?? [], true));
    if (!isset($db['parts'])) return $db;
    $mk = PortalCore::vid($db) . '|' . $period . '|' . $kind . '|' . js_stringify($selected);
    return PortalCore::memo('view', $mk, function () use ($db, $period, $kind, $selected, $mk) {
        $sel = js_set($selected); $parts = $db['parts'];
        $view = $db; $view['coverage'] = []; $view['cubes'] = []; $view['_vid'] = 'v' . md5($mk);
        // Histori dealer-brand tetap utuh walau PIC/region berubah. Hanya untuk hitungan KPI IDDLE.
        $view['_iddleHistory'] = ['vid' => PortalCore::vid($db), 'cubes' => $db['cubes'], 'coverage' => $db['coverage'], 'parts' => $parts];
        if (!empty($db['stock'])) {
            $view['stock']['coverage'] = [];
            if (isset($db['stock']['moves'])) $view['stock']['moves'] = js_filter($db['stock']['moves'], fn($t) => !truthy($t['region'] ?? '') || isset($sel[(string) $t['region']]));
            else $view['stock']['_sel'] = $sel; // DSM v5: mutasi diambil dari MySQL, disaring region saat dibaca
        } else $view['stock'] = null;
        foreach (js_keys($db['coverage']) as $p) {
            $st = RegionCore::common($parts, 'ST', $p, $selected); $so = RegionCore::common($parts, 'SO', $p, $selected);
            $stC = js_or($st['cutoff'], $st['latest']); $soC = js_or($so['cutoff'], $so['latest']);
            $cut = ($kind === 'PAIR' && truthy($stC) && truthy($soC)) ? js_sort_default([$stC, $soC])[0] : $stC;
            if (truthy($cut)) $view['coverage'][$p] = ['cutoff' => $cut, 'closed' => truthy($st['cutoff']) && $cut === PortalCore::end($p)];
            $cubes = [];
            if (truthy($cut)) foreach ($db['cubes'][$p] ?? [] as $t) if ((!truthy($t['region'] ?? '') || isset($sel[(string) $t['region']])) && strcmp($t['date'], $cut) <= 0) $cubes[] = $t;
            $view['cubes'][$p] = $cubes;
            if ($view['stock'] && truthy($soC)) $view['stock']['coverage'][$p] = ['cutoff' => ($kind === 'PAIR' && truthy($stC)) ? js_sort_default([$stC, $soC])[0] : $soC];
            if ($p === $period) {
                $view['partial'] = !truthy($st['cutoff']) || ($kind === 'PAIR' && !truthy($so['cutoff'])) || (truthy($cut) && $cut !== PortalCore::end($p));
                $view['missingST'] = !truthy($stC); $view['missingSO'] = !truthy($soC);
                $view['partialST'] = !truthy($st['cutoff']) && truthy($stC); $view['partialSO'] = !truthy($so['cutoff']) && truthy($soC);
                $view['missingRegionsST'] = js_filter($selected, fn($r) => empty($parts['ST'][$p][$r]));
                $view['missingRegionsSO'] = js_filter($selected, fn($r) => empty($parts['SO'][$p][$r]));
                $view['regionStatus'] = array_map(fn($r) => ['region' => $r, 'st' => js_or($parts['ST'][$p][$r]['cutoff'] ?? null, null), 'so' => js_or($parts['SO'][$p][$r]['cutoff'] ?? null, null)], $selected);
            }
        }
        if (!isset($view['coverage'][$period])) {
            $view['coverage'][$period] = ['cutoff' => $period . '-01', 'closed' => false];
            $view['noData'] = true; $view['missingST'] = true; $view['partial'] = true;
            $view['regionStatus'] = array_map(fn($r) => ['region' => $r, 'st' => null, 'so' => js_or($parts['SO'][$period][$r]['cutoff'] ?? null, null)], $selected);
        }
        $view['selectedRegions'] = $selected;
        $view['historyComplete'] = js_every(monthSequence_(substr(StockCore::BASE, 0, 7), $period), function ($p) use ($parts, $selected, $period, $view) {
            $c = RegionCore::common($parts, 'ST', $p, $selected);
            $need = $p === $period ? ($view['coverage'][$p]['cutoff'] ?? null) : PortalCore::end($p);
            return truthy($c['cutoff']) && $need !== null && strcmp((string) $c['cutoff'], (string) $need) >= 0;
        });
        return $view;
    });
}
