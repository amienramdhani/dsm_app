<?php
/** Fungsi bantu bersama (Code.gs, Pelengkap.gs, Promotor.gs, ExportAccess.gs, Mobile.gs). */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/dealer_brand.php';

function people_(array $db): array { return $db['meta']['people']; }
function person_(array $db, $nik): ?array { return PortalCore::person($db['meta']['people'], $nik); }

function allowed_(array $db, array $user, $selection): array {
    if (($user['role'] ?? '') === 'TELEMARKETING') return tmAllowedIds_($db, $user, $selection);
    $base = PortalCore::scope($db['meta']['people'], $user['nik']);
    if (!truthy($selection) || $selection === 'ALL') return $base;
    if (!in_array((string) $selection, array_map('strval', $base), true)) fail_('Data di luar cakupan.');
    $bs = js_set($base);
    return js_filter(PortalCore::scope($db['meta']['people'], (string) $selection), fn($id) => isset($bs[(string) $id]));
}

function combinedTarget_(array $targets): array {
    $missing = [];
    foreach ($targets as $t) foreach ($t['missing'] ?? [] as $m) $missing[] = $m;
    return ['exists' => count($targets) > 0 && js_every($targets, fn($t) => $t['exists']), 'value' => js_sum($targets, fn($t) => $t['value']), 'missing' => uniq($missing)];
}

function dealerOwner_(array $db, $id, $nikOverride = null): array {
    $c = $db['meta']['customers'][$id] ?? []; $owner = person_($db, js_or($c['nik'] ?? null, $nikOverride));
    $p = $owner; $asm = null; $seen = [];
    while ($p && !isset($seen[(string) $p['nik']])) {
        $seen[(string) $p['nik']] = true;
        if ($p['role'] === 'ASM') { $asm = $p; break; }
        $p = person_($db, $p['atasan'] ?? null);
    }
    return ['region' => js_or($owner['wilayah'] ?? null, ''), 'subregion' => js_or($owner['portfolio'] ?? null, js_or($owner['region'] ?? null, js_or($c['region'] ?? null, ''))), 'asm' => js_or($asm['nama'] ?? null, ''), 'asmNik' => js_or($asm['nik'] ?? null, ''), 'sales' => js_or($owner['nama'] ?? null, ''), 'salesNik' => js_or($owner['nik'] ?? null, '')];
}

// ------------------------------------------------------------------
// Promotor & Grade (Promotor.gs)
// ------------------------------------------------------------------

const DEALER_ATTR = [
    'PROMOTOR' => ['sheet' => 'Master_Promotor', 'prefix' => 'PROMOTOR', 'num' => true, 'label' => 'Master Promotor'],
    'GRADE' => ['sheet' => 'Master_Grade', 'prefix' => 'GRADE', 'num' => false, 'label' => 'Master Grade'],
];
function attrBrandHead_(array $cfg, $h): string {
    $s = preg_replace('/\s+/u', ' ', js_upper(text_($h)));
    return preg_match('/^' . $cfg['prefix'] . '\s+(.+)$/u', $s, $m) ? js_trim($m[1]) : '';
}
function attrVal_(array $cfg, $v) {
    if ($cfg['num']) { $s = text_($v); $n = $s === '' ? 0 : (is_numeric($s) ? $s + 0 : NAN); return (!is_nan((float) $n) && $n > 0) ? $n : null; }
    $v = preg_replace('/\s+/u', ' ', js_upper(text_($v)));
    return $v !== '' ? $v : null;
}
function attrMap_(string $type): array {
    static $memo = [];
    if (isset($memo[$type])) return $memo[$type];
    $cfg = DEALER_ATTR[$type]; $out = [];
    try {
        $grid = readGrid_($cfg['sheet']);
        if ($grid) {
            $heads = array_map('text_', $grid[0]); $ki = array_search('ID DEALER', array_map('js_upper', $heads), true);
            if ($ki !== false) foreach (array_slice($grid, 1) as $r) {
                $id = text_($r[$ki] ?? ''); if ($id === '') continue;
                $brands = [];
                foreach ($heads as $i => $h) { $b = attrBrandHead_($cfg, $h); $v = $b !== '' ? attrVal_($cfg, $r[$i] ?? '') : null; if ($v !== null) $brands[] = [$b, $v]; }
                if (!$cfg['num'] && !$brands) continue;
                $out[$id] = ['total' => $cfg['num'] ? js_sum($brands, fn($x) => $x[1]) : count($brands), 'brands' => $brands];
            }
        }
    } catch (Throwable $e) {}
    return $memo[$type] = $out;
}
function promotorAttach_(array &$customers): void {
    $p = attrMap_('PROMOTOR'); $g = attrMap_('GRADE');
    foreach ($customers as &$c) { $c['promotor'] = $p[$c['id']] ?? null; $c['grade'] = isset($g[$c['id']]) ? $g[$c['id']]['brands'] : null; }
    unset($c);
}

// ------------------------------------------------------------------
// Export access (ExportAccess.gs)
// ------------------------------------------------------------------

function exportAccessTypeIds_(): array { return ['SUMMARY', 'BRAND', 'KPI', 'DOS', 'DEALER']; }
function exportAccessRows_(): array {
    $known = exportAccessTypeIds_(); $out = [];
    foreach (optionalTable_('Export_Access') as $r) {
        $x = ['nik' => text_($r['NIK'] ?? ''), 'type' => js_upper(text_($r['JENIS_EXPORT'] ?? '')), 'active' => PortalCore::active($r['AKTIF'] ?? '')];
        if ($x['nik'] !== '' && in_array($x['type'], $known, true)) $out[] = $x;
    }
    return $out;
}
function exportAccessAutomatic_($u): bool { return in_array(preg_replace('/\s+/', ' ', js_upper(text_($u['role'] ?? ''))), ['ADMIN', 'SUPER ADMIN'], true); }
function exportAccessForUser_(array $u, ?array $rows = null): array {
    $userRows = js_filter($rows ?? exportAccessRows_(), fn($r) => $r['nik'] === $u['nik']);
    $granted = $userRows ? array_map(fn($r) => $r['type'], js_filter($userRows, fn($r) => $r['active'])) : (exportAccessAutomatic_($u) ? exportAccessTypeIds_() : []);
    $out = []; foreach (exportAccessTypeIds_() as $t) $out[$t] = in_array($t, $granted, true);
    return $out;
}

// ------------------------------------------------------------------
// Peringkat (Mobile.gs)
// ------------------------------------------------------------------

function rankDetailAllowed_(array $db, array $u, ?array $p): bool {
    if (!$p) return false;
    if ($p['role'] === 'RGM') return in_array($u['role'], ['ADMIN', 'SUPER ADMIN'], true);
    if (!in_array($p['role'], ['SALES', 'ASM'], true)) return false;
    if ($u['role'] === 'SUPER ADMIN' || $u['role'] === 'RGM' || RegionCore::top($u['role'])) return true;
    $ids = array_map('strval', PortalCore::scope($db['meta']['people'], $u['nik']));
    if ($u['role'] === 'ADMIN') return in_array((string) $p['nik'], $ids, true);
    if ($u['role'] === 'ASM') return $p['role'] === 'ASM' || in_array((string) $p['nik'], $ids, true);
    if ($u['role'] === 'SALES') {
        $me = person_($db, $u['nik']);
        return $p['role'] === 'SALES' && ($p['nik'] === $u['nik'] || (truthy($me['atasan'] ?? '') && $me['atasan'] === ($p['atasan'] ?? null) && js_some($db['meta']['people'], fn($x) => $x['nik'] === $me['atasan'] && $x['role'] === 'ASM')));
    }
    return false;
}

// ------------------------------------------------------------------
// weeklyPair_ (Pelengkap.gs) — ST & SO mingguan per TYPE untuk menu Brand
// ------------------------------------------------------------------

function weeklyPair_(array $source, string $period, array $ids, $u): array {
    $db = coverageView_($source, $period, $ids, 'PAIR', $u);
    $cov = $db['coverage'][$period] ?? null;
    if (!$cov || !empty($db['noData']) || !empty($db['missingST']) || !empty($db['missingSO']) || empty($db['stock'])) return ['available' => false];
    $cutoff = $cov['cutoff']; $cutDay = (int) substr($cutoff, 8); $lastDay = (int) substr(PortalCore::end($period), 8);
    $weeks = array_map(function ($w) use ($cutDay, $lastDay) { $from = ($w - 1) * 7 + 1; $to = $w === 5 ? $lastDay : min($w * 7, $lastDay); return ['week' => $w, 'from' => $from, 'to' => $to, 'future' => $from > $cutDay || $from > $lastDay]; }, [1, 2, 3, 4, 5]);
    $idx = fn($date) => min(5, (int) ceil(((int) substr($date, 8)) / 7)) - 1;
    $types = [];
    $slot = function ($brand, $type) use (&$types) { $k = jkey([$brand, $type]); if (!isset($types[$k])) $types[$k] = ['brand' => $brand, 'type' => $type, 'st' => [0, 0, 0, 0, 0], 'so' => [0, 0, 0, 0, 0]]; return $k; };
    foreach ($db['cubes'][$period] ?? [] as $r) { if (!truthy($r['q']) || strcmp($r['date'], $cutoff) > 0) continue; $k = $slot($r['brand'], $r['type']); $types[$k]['st'][$idx($r['date'])] += $r['q']; }
    foreach (stockMoves_($db, $period . '-01', (string) $cutoff, ['out' => true]) as $t) {
        if (!truthy($t['outQty']) || substr($t['date'], 0, 7) !== $period || strcmp($t['date'], $cutoff) > 0) continue;
        if (!StockCore::canSee($db, $ids, $t['customer'])) continue;
        $p = $db['meta']['products'][$t['sku']] ?? null; if (!$p || !truthy($p['device'] ?? false)) continue;
        $k = $slot($p['brand'], js_or($p['type'] ?? '', js_or($p['nama'] ?? '', $t['sku']))); $types[$k]['so'][$idx($t['date'])] += $t['outQty'];
    }
    return ['available' => true, 'cutoff' => $cutoff, 'weeks' => $weeks, 'types' => array_values($types)];
}
