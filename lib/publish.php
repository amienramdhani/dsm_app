<?php
/**
 * Publikasi snapshot — terjemahan 1:1 dari Data.gs (settings_, meta_, publish_, configHash_, summary_)
 * beserta fungsi pendukungnya dari RegionalCore.gs, RegioanlImport.gs, Admin.gs, RgmRule.gs, KpiAdmin.gs,
 * TargetInput.gs, DealerEdit.gs, AccountMerge.gs, SellOut.gs, dan NikRemap.gs.
 *
 * Hasil publish_ identik dengan Apps Script: urutan kunci objek mengikuti aturan JS
 * (kunci angka naik dulu, sisanya urutan masuk) dan objek kosong ditulis {} — lihat pubOut_().
 */
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/api_exec.php';

const SP_VERSION = '4.0.0';
const CHECKPOINT_SHEETS = ['Master_Sales', 'Master_Customer', 'Master_Product', 'Master_Target', 'Target_Periode', 'Target_Input', 'KPI_Config', 'KPI_RGM', 'Region_Map', 'Product_Rules', 'DOS_Config', 'Admin_Access', 'Export_Access', 'Dealer_Region', 'Dealer_Assignment_History', 'Dealer_Brand_Assignment', 'Master_Promotor', 'Master_Grade', 'Period_Locks', 'Informasi', 'Region_Brand', 'Target_Big', '_Auth'];

// ------------------------------------------------------------------
// Bantuan JS: Number(string), keluaran JSON berurutan JS
// ------------------------------------------------------------------

/** Number(x) versi JS (untuk teks/angka sel). Hasil int bila bulat, float bila pecahan, NAN bila tidak valid. */
function js_number_($v) {
    if ($v === null) return 0;
    if ($v === true) return 1;
    if ($v === false) return 0;
    if (is_int($v)) return $v;
    if (is_float($v)) return $v;
    $s = js_trim((string) $v);
    if ($s === '') return 0;
    if (preg_match('/^[+-]?(\d+\.?\d*(e[+-]?\d+)?|\.\d+(e[+-]?\d+)?)$/i', $s)) $f = (float) $s;
    elseif (preg_match('/^[+-]?Infinity$/', $s)) $f = $s[0] === '-' ? -INF : INF;
    elseif (preg_match('/^0x[0-9a-f]+$/i', $s)) $f = (float) hexdec(substr($s, 2));
    elseif (preg_match('/^0o[0-7]+$/i', $s)) $f = (float) octdec(substr($s, 2));
    elseif (preg_match('/^0b[01]+$/i', $s)) $f = (float) bindec(substr($s, 2));
    else return NAN;
    return (is_finite($f) && floor($f) == $f && abs($f) < 9007199254740992) ? (int) $f : $f;
}

/** Objek JS: urutkan kunci sesuai Object.keys dan pastikan di-encode sebagai {} (bukan []). */
function pubObj_($a) {
    if (!is_array($a)) return $a;
    return (object) PortalCore::jsOrdered($a);
}
/** Map dengan nilai map: pubObj_ dua tingkat. */
function pubObj2_($a) {
    if (!is_array($a)) return $a;
    $o = PortalCore::jsOrdered($a);
    foreach ($o as $k => $v) $o[$k] = pubObj_($v);
    return (object) $o;
}
/** meta_() → bentuk JSON JS (customers/products sebagai objek berurutan JS). */
function metaOut_(array $meta): array {
    if (isset($meta['customers'])) $meta['customers'] = pubObj_($meta['customers']);
    if (isset($meta['products'])) $meta['products'] = pubObj_($meta['products']);
    return $meta;
}
/** Snapshot → bentuk JSON JS. */
function pubOut_(array $db): array {
    $db['meta'] = metaOut_($db['meta']);
    foreach (['cubes', 'coverage', 'first', 'unlinked', 'raw'] as $k) if (array_key_exists($k, $db)) $db[$k] = pubObj_($db[$k]);
    if (array_key_exists('life', $db)) $db['life'] = pubObj2_($db['life']);
    if (isset($db['so'])) { $db['so']['raw'] = pubObj_($db['so']['raw'] ?? []); $db['so']['coverage'] = pubObj_($db['so']['coverage'] ?? []); }
    if (isset($db['parts']) && is_array($db['parts'])) {
        $parts = PortalCore::jsOrdered($db['parts']);
        foreach ($parts as $kind => $m) $parts[$kind] = pubObj2_($m);
        $db['parts'] = (object) $parts;
    }
    if (isset($db['routing']) && is_array($db['routing'])) {
        $r = $db['routing'];
        foreach (['customers', 'sales'] as $k) if (array_key_exists($k, $r)) $r[$k] = pubObj_($r[$k]);
        $db['routing'] = $r;
    }
    if (isset($db['stock']) && is_array($db['stock'])) { $db['stock']['owners'] = pubObj_($db['stock']['owners']); $db['stock']['coverage'] = pubObj_($db['stock']['coverage']); }
    return $db;
}
/** JSON.stringify untuk nilai yang berasal dari JSON/parts (urutan kunci JS di semua tingkat). */
function js_json_($v): string {
    $f = function ($x) use (&$f) {
        if (!is_array($x)) return $x;
        if (array_is_list($x)) return array_map($f, $x);
        $o = PortalCore::jsOrdered($x);
        foreach ($o as $k => $y) $o[$k] = $f($y);
        return (object) $o;
    };
    return js_stringify($f($v));
}

/** readJson_ tanpa memo (file ST/SO per region bisa besar; jangan ditahan di memori). */
function pubReadJson_(string $id) {
    $p = file_path_($id);
    if (!is_file($p)) fail_('File data tidak ditemukan: ' . $id);
    return json_decode(file_get_contents($p), true, 512, JSON_BIGINT_AS_STRING);
}

// ------------------------------------------------------------------
// Data.gs
// ------------------------------------------------------------------

function settings_(): array {
    $rs = js_number_(js_or(prop_get_('ROLE_SCHEMA'), 1));
    return ['roleSchema' => $rs, 'regions' => table_('Region_Map'), 'products' => table_('Product_Rules'), 'targets' => array_merge(table_('Target_Periode'), targetWide_()), 'kpi' => table_('KPI_Config')];
}

function meta_(): array {
    $s = settings_();
    $meta = PortalCore::normalize(['sales' => table_('Master_Sales'), 'customers' => table_('Master_Customer'), 'products' => table_('Master_Product')], $s);
    $regionList = function () use ($s) {
        $l = [];
        foreach ($s['regions'] as $r) { $v = array_key_exists('WILAYAH_KPI', $r) ? js_str($r['WILAYAH_KPI']) : 'undefined'; if ($v !== '') $l[] = $v; }
        return uniq($l);
    };
    $meta['regionList'] = $regionList();
    $meta['rgmPolicies'] = rgmPolicies_($meta);
    [$meta['regionBrand'], $meta['targetBig']] = rbLoad_();
    $meta['indukLog'] = indukLogRead_();
    $meta['nikMerge'] = nikMergeRead_();
    $meta['regionList'] = $regionList();
    $access = optionalTable_('Admin_Access');
    foreach ($meta['people'] as &$p) {
        if ($p['role'] !== 'ADMIN') continue;
        $l = []; foreach ($access as $r) if (js_str($r['NIK'] ?? null) === $p['nik']) $l[] = js_str($r['WILAYAH'] ?? null);
        $p['adminRegions'] = uniq($l);
    }
    unset($p);
    $meta['dealerRegions'] = array_map(fn($r) => ['customer' => text_($r['KODE CUSTOMER'] ?? null), 'region' => text_($r['WILAYAH'] ?? null), 'from' => PortalCore::iso($r['BERLAKU_MULAI'] ?? null)], optionalTable_('Dealer_Region'));
    return applyDealerContacts_($meta);
}

function configHash_(array $meta): string { return hash_(js_stringify(metaOut_($meta))); }

function summary_(array $rows): array {
    $q = 0; $a = 0; $d = [];
    foreach ($rows as $r) { $q += $r['qty']; $a += $r['amount']; $d[] = $r['date']; }
    $d = js_sort_default($d);
    return ['rows' => count($rows), 'qty' => $q, 'amount' => PortalCore::round($a), 'min' => js_or($d[0] ?? null, null), 'max' => js_or($d ? $d[count($d) - 1] : null, null)];
}

/** Snapshot aktif sebelum publikasi (sama dengan prior di JS). */
function publishPrior_(): ?array { return prop_get_('ACTIVE') ? snapshot_() : null; }

function publish_(array $meta, $rawMap, $coverage, $actor, $reason, $soOverride = null, $partsOverride = null): array {
    $started = now_ms(); $prior = publishPrior_();
    $parts = truthy($partsOverride) ? $partsOverride : ($prior['parts'] ?? null);
    $tx = []; $soRows = [];
    $so = truthy($soOverride) ? $soOverride : (truthy($prior['so'] ?? null) ? $prior['so'] : ['raw' => [], 'coverage' => []]);
    $rawMap = $rawMap ?? [];
    if (!truthy($parts)) {
        foreach (js_keys($rawMap) as $p) foreach (dataRows_($rawMap[$p]) as $t) $tx[] = $t;
        $routing = RegionCore::routing($meta, $tx, $prior['routing'] ?? null); $shell = ['meta' => $meta, 'routing' => $routing];
        $tx = [];
        $parts = ['ST' => [], 'SO' => []];
        foreach (['ST', 'SO'] as $kind) {
            $map = $kind === 'ST' ? $rawMap : ($so['raw'] ?? []); $cov = $kind === 'ST' ? $coverage : ($so['coverage'] ?? []);
            foreach (js_keys($map) as $p) {
                $groups = [];
                foreach (dataRows_($map[$p]) as $t) {
                    $region = RegionCore::route($shell, $t, $kind);
                    if (!truthy($region)) fail_('Region belum terpetakan: ' . js_str($t['customer'] ?? null));
                    $t['region'] = $region; $groups[(string) $region][] = $t;
                }
                $allRegions = RegionCore::regions($meta); $parts[$kind][$p] = [];
                foreach ($allRegions as $r) $parts[$kind][$p][$r] = ['file' => partWrite_($kind, (string) $p, (string) $r, $groups[(string) $r] ?? []), 'cutoff' => js_or($cov[$p]['cutoff'] ?? null, PortalCore::end($p))];
                unset($groups);
            }
        }
    }
    $tx = []; $raw = []; $soRaw = []; $cov = []; $soCov = [];
    $allRegions = RegionCore::regions($meta);
    foreach (['ST', 'SO'] as $kind) {
        foreach (js_keys($parts[$kind] ?? []) as $p) {
            $rows = [];
            foreach (js_values($parts[$kind][$p]) as $v) foreach (dataRows_((string) $v['file']) as $t) $rows[] = $t;
            $common = RegionCore::common($parts, $kind, $p, $allRegions); $cutoff = js_or($common['cutoff'], $common['latest']);
            if (!truthy($cutoff)) { unset($rows); continue; }
            // DSM v5: data satu bulan tidak lagi disalin ke file baru; cukup penunjuk ke partisi-partisinya.
            $pointer = monthPointer_(js_values($parts[$kind][$p]));
            if ($kind === 'ST') { $raw[$p] = $pointer; $cov[$p] = ['cutoff' => $cutoff, 'closed' => $cutoff === PortalCore::end($p)]; foreach ($rows as $t) $tx[] = $t; }
            else { $soRaw[$p] = $pointer; $soCov[$p] = ['cutoff' => $cutoff]; foreach ($rows as $t) $soRows[] = $t; }
            unset($rows);
        }
    }
    // DSM v5: batas histori ST 150.000 / SO 300.000 baris dihapus — data mentah tersimpan di MySQL.
    $tx = nikResolveRows_($meta, custResolveRows_($meta, $tx)); $soRows = custResolveRows_($meta, $soRows);
    $db = PortalCore::build($meta, $tx, $cov);
    $db['raw'] = $raw; $db['so'] = ['raw' => $soRaw, 'coverage' => $soCov]; $db['parts'] = $parts;
    $db['routing'] = RegionCore::routing($meta, $tx, $prior['routing'] ?? null);
    $db['stock'] = StockCore::build($meta, $tx, ['rows' => $soRows, 'coverage' => $soCov]);
    unset($soRows);
    $db['dosRules'] = dosRules_();
    $db['notes'] = array_merge($db['notes'], $db['stock']['notes']);
    $db['processed'] = iso_now(); $db['version'] = SP_VERSION;
    $rowCount = count($tx); unset($tx);
    if (now_ms() - $started > 240000) fail_('Publikasi mendekati batas waktu; data lama tetap aktif.');
    // DSM v5: mutasi stok (bagian terbesar snapshot) disimpan di tabel dsm_move, bukan di JSON.
    $id = newFileId_();
    movesWrite_($id, $db['stock']['moves']);
    unset($db['stock']['moves']); $db['stock']['moveSnap'] = $id;
    $out = pubOut_($db); $notes = $db['notes']; $processed = $db['processed']; $partsMap = $db['parts']; unset($db);
    writeJsonId_($id, $out); unset($out);
    writeJsonId_($id . '-parts', ['parts' => $partsMap]); // peta partisi kecil untuk Pemulihan regional
    if ($prior) prop_set_('PREVIOUS', $prior['id']);
    if (sheet_exists_('Version_Log')) appendRow_('Version_Log', [$processed, $actor, $reason, $id]);
    prop_set_('ACTIVE', $id);
    audit_(js_str($actor), 'PUBLISH', js_str($reason) . ' ' . $id);
    if ($prior) revokeChanged_($prior['meta']['people'], $meta['people']);
    try { storage_prune_(); } catch (Throwable $e) { error_log('[dsm] prune gagal: ' . $e->getMessage()); }
    return ['version' => $id, 'processed' => $processed, 'notes' => $notes, 'rows' => $rowCount];
}

// ------------------------------------------------------------------
// RegionalCore.gs
// ------------------------------------------------------------------

function adminGuard_($token, ?array $db = null): array {
    $u = guard_($token, false, false, $db);
    if (!in_array($u['role'], ['ADMIN', 'SUPER ADMIN'], true)) fail_('Akses admin diperlukan.');
    return $u;
}
function canManageUser_(array $actor, array $target): bool {
    return $actor['role'] === 'SUPER ADMIN' || (function_exists('tmCanManage_') && ($target['role'] ?? '') === 'TELEMARKETING' && tmCanManage_($actor, $target['nik'])) || ($actor['role'] === 'ADMIN' && in_array($target['role'], ['SALES', 'ASM', 'RGM'], true) && in_array($target['wilayah'] ?? null, $actor['adminRegions'] ?? [], true));
}
function enforceUser_($token, $nik): array {
    $db = snapshot_(); $u = adminGuard_($token, $db); $target = PortalCore::person($db['meta']['people'], $nik);
    if (!$target || !canManageUser_($u, $target)) fail_('User di luar kewenangan atau jabatan terlindungi.');
    return ['u' => $u, 'target' => $target, 'db' => $db];
}
/** Semua sesi dicabut (di Apps Script: hapus semua property SESSION_*). */
function revokeAll_(): void { db_()->exec("DELETE FROM _sessions WHERE k LIKE 'SESSION\\_%'"); } // DSM v5: daftar perangkat user tidak ikut dihapus
function assertPeriodOpen_($period): void {
    foreach (optionalTable_('Period_Locks') as $r) {
        if (($r['PERIODE'] ?? null) === $period) { if (PortalCore::active($r['TERKUNCI'] ?? null)) fail_('Periode dikunci. Super Admin perlu membuka dengan alasan.'); return; }
    }
}
function checkpoint_($actor, $reason): string {
    $id = prop_get_('ACTIVE'); $sheets = [];
    foreach (CHECKPOINT_SHEETS as $n) { $g = readGrid_($n); if ($g !== null) $sheets[$n] = $g; }
    $file = writeJson_('before-' . now_ms(), ['active' => $id, 'sheets' => js_obj($sheets), 'at' => iso_now(), 'actor' => $actor, 'reason' => $reason]);
    audit_(js_str($actor), 'CHECKPOINT', js_str($reason) . ' ' . $file);
    return $file;
}

// ------------------------------------------------------------------
// RegioanlImport.gs
// ------------------------------------------------------------------

/** Cabut sesi orang yang data master-nya berubah. */
function revokeChanged_(array $before, array $after): void {
    $idx = []; foreach ($after as $x) if (!isset($idx[$x['nik']])) $idx[$x['nik']] = $x;
    $changed = [];
    foreach ($before as $p) { $a = $idx[$p['nik']] ?? null; if ($a === null || js_json_($p) !== js_json_($a)) $changed[] = (string) $p['nik']; }
    foreach (array_chunk($changed, 500) as $chunk) {
        db_()->prepare('DELETE FROM _sessions WHERE nik IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')')->execute($chunk);
    }
}
function validateConfigChange_(array $old, array $meta): void {
    rgmRuleCheck_($old['meta'], $meta);
    $activeSuper = js_filter($meta['people'], fn($p) => $p['role'] === 'SUPER ADMIN' && PortalCore::active(account_($p['nik'])['AKTIF'] ?? null));
    if (!$activeSuper) fail_('Harus ada Super Admin aktif.');
    $cuts = []; foreach (js_values($old['coverage'] ?? []) as $v) $cuts[] = $v['cutoff'];
    $cuts = js_sort_default($cuts); $last = $cuts ? $cuts[count($cuts) - 1] : null;
    $oldDr = array_map('js_json_', $old['meta']['dealerRegions'] ?? []);
    foreach ($meta['dealerRegions'] ?? [] as $r) {
        $existed = in_array(js_json_($r), $oldDr, true);
        if (!$existed && strcmp((string) $r['from'], js_str($last)) <= 0) fail_('Mapping dealer baru harus efektif setelah cutoff publikasi ' . js_str($last));
    }
    $newDr = array_map('js_json_', $meta['dealerRegions'] ?? []);
    foreach ($old['meta']['dealerRegions'] ?? [] as $r) if (!in_array(js_json_($r), $newDr, true)) fail_('Riwayat mapping tidak boleh dihapus/diubah; tambahkan tanggal efektif baru.');
}
function summarySO_(array $rows): array {
    $q = 0; $a = 0;
    foreach ($rows as $r) { $q += $r['qty']; $a += js_or($r['amount'] ?? null, 0); }
    return ['rows' => count($rows), 'qty' => $q, 'amount' => PortalCore::round($a)];
}

// ------------------------------------------------------------------
// Admin.gs
// ------------------------------------------------------------------

function restoreGrid_(string $name, ?array $grid): void { if ($grid) putTable_($name, $grid[0], array_slice($grid, 1)); }

// ------------------------------------------------------------------
// RgmRule.gs (rgmMap_ ada di api_exec.php)
// ------------------------------------------------------------------

function rgmConflicts_(array $meta): array {
    $m = rgmMap_($meta); $res = [];
    foreach (js_keys($m) as $big) {
        $l = js_values($m[$big]);
        if (count($l) > 1) $res[$big] = js_sort($l, fn($a, $b) => $b['count'] - $a['count']);
    }
    return $res;
}
function rgmRuleCheck_(array $oldMeta, array $meta): void {
    $before = rgmConflicts_($oldMeta); $after = rgmConflicts_($meta); $errs = [];
    foreach (js_keys($after) as $big) {
        $oldSet = implode('|', js_sort_default(array_map(fn($x) => $x['nik'], $before[$big] ?? [])));
        $newSet = implode('|', js_sort_default(array_map(fn($x) => $x['nik'], $after[$big])));
        if ($oldSet === $newSet) continue;
        $parts = [];
        foreach ($after[$big] as $i => $x) $parts[] = js_str($x['nama']) . ' (' . $x['count'] . ' orang' . ($i !== 0 ? ', lewat ' . implode(', ', $x['via']) : '') . ')';
        $errs[] = $big . ' jadi punya ' . count($after[$big]) . ' RGM: ' . implode(' & ', $parts) . '.';
    }
    if ($errs) fail_('Satu Big Region hanya boleh 1 RGM. ' . implode(' ', $errs) . ' Perbaiki NIK ATASAN / REGION orang tersebut.');
}

// ------------------------------------------------------------------
// KpiAdmin.gs
// ------------------------------------------------------------------

const KPI_CFG_HEADS = ['PERIODE', 'WILAYAH_KPI', 'KELOMPOK_KPI', 'METRIK', 'BRAND', 'BOBOT', 'BATAS_SKOR', 'INDIKATOR_ID', 'LABEL', 'TYPE_FILTER', 'MIN_QTY_DA', 'AKTIF', 'TYPE_WAJIB', 'MIN_QTY_PER_PROMOTOR'];
const KPI_RGM_HEADS = ['PERIODE', 'NIK', 'NAMA', 'KELOMPOK_KPI', 'INDIKATOR_ID', 'LABEL', 'METRIK', 'BRAND', 'TYPE_FILTER', 'BIG_REGIONS', 'BOBOT', 'BATAS_SKOR', 'TARGET', 'MIN_QTY_DA'];
const KPI_METRIC_COL = ['QTY' => 'ST', 'DA' => 'DA', 'NOO' => 'NOO', 'IDDLE' => 'IDDLE', 'OMZET' => 'OMZET'];

function kpiMonth_($v): string {
    $t = text_($v);
    if (!preg_match('/^(\d{4})-(\d{1,2})/', $t, $m)) return $t;
    return $m[1] . '-' . substr('0' . $m[2], -2);
}
/** kpiNum_: '' / null → null; selain itu Number(teks tanpa karakter non-angka); tak hingga → NaN. */
function kpiNum_($v) {
    if ($v === '' || $v === null) return null;
    $n = js_number_(preg_replace('/[^0-9.\-]/', '', js_str($v)));
    return is_float($n) && !is_finite($n) ? NAN : $n;
}
function kpiRowFilled_(array $r): bool { foreach ($r as $v) if ($v !== '' && $v !== null) return true; return false; }
function kpiGrid_(string $sheet, array $heads): array {
    $g = readGrid_($sheet);
    if (!$g) return ['heads' => $heads, 'rows' => [], 'raw' => null];
    $h = array_map('text_', $g[0]); foreach ($heads as $x) if (!in_array($x, $h, true)) $h[] = $x;
    $rows = [];
    foreach (array_slice($g, 1) as $r) { if (!kpiRowFilled_($r)) continue; $o = []; foreach ($h as $i => $k) $o[$k] = $r[$i] ?? ''; $rows[] = $o; }
    return ['heads' => $h, 'rows' => $rows, 'raw' => $g];
}
function kpiPut_(string $sheet, array $g): void { putTable_($sheet, $g['heads'], array_map(fn($r) => array_map(fn($h) => $r[$h] ?? '', $g['heads']), $g['rows'])); }

/** Dipanggil meta_(): baca KPI_RGM → meta.rgmPolicies */
function rgmPolicies_(array $meta): array {
    $g = readGrid_('KPI_RGM'); if (!$g || count($g) < 2) return [];
    $h = array_map('text_', $g[0]);
    $col = function (array $r, string $k) use ($h) { $i = array_search($k, $h, true); return $i === false ? '' : ($r[$i] ?? null); };
    $out = []; $sum = []; $seen = [];
    $bigs = array_map('js_upper', $meta['regionList'] ?? []);
    foreach (array_slice($g, 1) as $ri => $r) {
        if (!kpiRowFilled_($r)) continue;
        $line = 'KPI_RGM baris ' . ($ri + 2) . ': ';
        $split = fn($s) => array_values(array_filter(array_map('text_', explode('|', text_($s))), fn($x) => $x !== ''));
        $c = ['period' => kpiMonth_($col($r, 'PERIODE')), 'nik' => text_($col($r, 'NIK')), 'group' => text_($col($r, 'KELOMPOK_KPI')), 'indicator' => text_($col($r, 'INDIKATOR_ID')), 'label' => text_($col($r, 'LABEL')),
            'metric' => js_upper(text_($col($r, 'METRIK'))), 'brand' => js_or(text_($col($r, 'BRAND')), 'ALL'),
            'types' => $split($col($r, 'TYPE_FILTER')), 'bigs' => $split($col($r, 'BIG_REGIONS')),
            'weight' => kpiNum_($col($r, 'BOBOT')), 'cap' => kpiNum_($col($r, 'BATAS_SKOR')), 'target' => kpiNum_($col($r, 'TARGET')), 'minDA' => js_or(kpiNum_($col($r, 'MIN_QTY_DA')), js_upper(text_($col($r, 'METRIK'))) === 'IDDLE' ? 1 : 2), 'minNOO' => js_or(kpiNum_($col($r, 'MIN_QTY_DA')), 1)];
        if (!preg_match('/^\d{4}-\d{2}$/', $c['period'])) fail_($line . 'PERIODE harus YYYY-MM.');
        $p = js_find($meta['people'], fn($x) => $x['nik'] === $c['nik']);
        if (!$p || $p['role'] !== 'RGM') fail_($line . 'NIK ' . $c['nik'] . ' bukan RGM.');
        if ($c['group'] === '') fail_($line . 'KELOMPOK_KPI kosong.');
        if (!in_array($c['metric'], ['QTY', 'DA', 'NOO', 'IDDLE', 'OMZET'], true)) fail_($line . 'METRIK harus QTY/DA/NOO/IDDLE/OMZET.');
        $gt0 = fn($x) => $x !== null && !(is_float($x) && is_nan($x)) && $x > 0;
        if (!$gt0($c['weight']) || !$gt0($c['cap'])) fail_($line . 'BOBOT dan BATAS_SKOR harus > 0.');
        if ($c['target'] !== null && ((is_float($c['target']) && is_nan($c['target'])) || $c['target'] < 0)) fail_($line . 'TARGET tidak valid.');
        if ($c['metric'] === 'IDDLE' && $c['minDA'] != 1) fail_($line . 'Dealer IDDLE memakai minimum 1 unit net.');
        if ($c['metric'] === 'IDDLE' && $c['label'] === '') $c['label'] = 'Dealer IDDLE ' . $c['brand'];
        if ($c['types'] && !in_array($c['metric'], ['QTY', 'DA'], true)) fail_($line . 'TYPE_FILTER hanya untuk QTY atau DA.');
        foreach ($c['bigs'] as $b) if (!in_array(js_upper($b), $bigs, true)) fail_($line . 'Big Region ' . $b . ' tidak ada di Region Map.');
        $k = implode('¦', [$c['period'], $c['nik'], $c['group'], $c['metric'], $c['brand'], $c['indicator'], implode('|', $c['bigs']), implode('|', $c['types'])]);
        if (isset($seen[$k])) fail_($line . 'indikator dobel.');
        $seen[$k] = 1;
        $gk = $c['period'] . ' · ' . js_str($p['nama']) . ' · ' . $c['group']; $sum[$gk] = ($sum[$gk] ?? 0) + $c['weight'];
        $out[] = $c;
    }
    foreach (js_keys($sum) as $k) if (abs($sum[$k] - 100) > 0.0001) fail_('KPI RGM ' . $k . ': total bobot harus 100 (sekarang ' . js_str($sum[$k]) . ').');
    return $out;
}

// ------------------------------------------------------------------
// TargetInput.gs
// ------------------------------------------------------------------

const TARGET_INPUT = 'Target_Input';
const TARGET_INPUT_FIXED = ['PERIODE TARGET', 'NIK SALES', 'NAMA SALES'];
const TARGET_INPUT_METRIC = ['ST' => 'QTY', 'DA' => 'DA', 'NOO' => 'NOO', 'IDDLE' => 'IDDLE', 'OMZET' => 'OMZET'];

function targetInputCols_(array $heads): array {
    $seen = []; $cols = [];
    foreach (array_values($heads) as $i => $h) {
        $h = preg_replace('/\s+/u', ' ', js_upper(text_($h)));
        if ($h === '') continue;
        if (isset($seen[$h])) fail_('Target_Input: kolom "' . $h . '" dobel (kolom ' . $seen[$h] . ' dan ' . ($i + 1) . ').');
        $seen[$h] = $i + 1;
        if (in_array($h, TARGET_INPUT_FIXED, true)) continue;
        if (!preg_match('/^(ST|DA|NOO|IDDLE|OMZET) (.+)$/u', $h, $m)) fail_('Target_Input: header "' . $h . '" tidak dikenali. Pakai format ST <BRAND>, DA <BRAND>, NOO <BRAND>, IDDLE <BRAND>, atau OMZET <BRAND>.');
        $cols[] = ['i' => $i, 'metric' => TARGET_INPUT_METRIC[$m[1]], 'brand' => js_trim($m[2])];
    }
    foreach (['PERIODE TARGET', 'NIK SALES'] as $k) if (!isset($seen[$k])) fail_('Target_Input: kolom wajib ' . $k . ' tidak ada.');
    return ['cols' => $cols, 'pIdx' => $seen['PERIODE TARGET'] - 1, 'nIdx' => $seen['NIK SALES'] - 1];
}
function targetWide_(): array {
    $v = readGrid_(TARGET_INPUT); if (!$v) return [];
    if (count($v) < 2) return [];
    $c = targetInputCols_($v[0]); $out = [];
    foreach (array_slice($v, 1) as $ri => $r) {
        $any = false; foreach ($r as $x) if ($x !== '' && $x !== null) { $any = true; break; }
        if (!$any) continue;
        $p = text_($r[$c['pIdx']] ?? null); $nik = text_($r[$c['nIdx']] ?? null);
        if ($p === '' || $nik === '') fail_('Target_Input baris ' . ($ri + 2) . ': PERIODE TARGET dan NIK SALES wajib diisi.');
        foreach ($c['cols'] as $col) {
            $x = $r[$col['i']] ?? ''; if ($x === '' || $x === null) continue;
            $n = js_number_(preg_replace('/[^0-9.\-]/', '', js_str($x)));
            if ((is_float($n) && !is_finite($n)) || $n < 0) fail_('Target_Input baris ' . ($ri + 2) . ': nilai "' . js_str($x) . '" di kolom ' . ($col['i'] + 1) . ' tidak valid.');
            $out[] = ['PERIODE' => $p, 'NIK' => $nik, 'BRAND' => $col['brand'], 'METRIK' => $col['metric'], 'INDIKATOR_ID' => '', 'TARGET' => $n];
        }
    }
    return $out;
}

// ------------------------------------------------------------------
// DealerEdit.gs — resolver kode uniq → kode induk
// ------------------------------------------------------------------

const UNIQ_COL = 'ID_DEALER';
const UNIQ_LABEL = 'KODE_UNIQ_CUSTOMER';
const INDUK_LOG = 'Customer_Induk_Log';

/** custMap_(meta) → fungsi kode → kode induk. Dimemo selama customers & indukLog meta sama. */
function custMap_(array $meta): Closure {
    static $lastCu = null, $lastLog = null, $fn = null;
    $cu = $meta['customers'] ?? []; $log = $meta['indukLog'] ?? [];
    if ($fn !== null && $lastCu === $cu && $lastLog === $log) return $fn;
    $m = [];
    foreach ($log as $x) if (truthy($x['lama'] ?? null) && truthy($x['baru'] ?? null)) $m[$x['lama']] = (string) $x['baru'];
    foreach (js_keys($cu) as $id) foreach ($cu[$id]['aliases'] ?? [] as $a) if (truthy($a)) $m[$a] = $id;
    foreach (js_keys($cu) as $id) $m[$id] = $id;
    $fn = function ($code) use ($m) {
        $c = (string) $code; $seen = [];
        while (isset($m[$c]) && truthy($m[$c]) && $m[$c] !== $c && !isset($seen[$c])) { $seen[$c] = 1; $c = $m[$c]; }
        return $c;
    };
    $lastCu = $cu; $lastLog = $log;
    return $fn;
}
function custInduk_(array $meta, $code) { $code = text_($code); return $code !== '' ? custMap_($meta)($code) : $code; }
function custResolveRows_(array $meta, ?array $rows): array {
    $f = custMap_($meta); $out = [];
    foreach ($rows ?? [] as $t) {
        $c = $f($t['customer']);
        if ($c === $t['customer']) { $out[] = $t; continue; }
        $t['uniq'] = $t['customer']; $t['customer'] = $c; $out[] = $t;
    }
    return $out;
}
function indukLogRead_(): array {
    $g = readGrid_(INDUK_LOG); if (!$g || count($g) < 2) return [];
    $h = array_map('text_', $g[0]); $li = array_search('KODE INDUK LAMA', $h, true); $bi = array_search('KODE INDUK BARU', $h, true);
    $out = [];
    foreach (array_slice($g, 1) as $r) {
        $x = ['lama' => text_($li === false ? null : ($r[$li] ?? null)), 'baru' => text_($bi === false ? null : ($r[$bi] ?? null))];
        if ($x['lama'] !== '' && $x['baru'] !== '' && $x['lama'] !== $x['baru']) $out[] = $x;
    }
    return $out;
}

// ------------------------------------------------------------------
// AccountMerge.gs
// ------------------------------------------------------------------

const NIK_MERGE_LOG = 'NIK_Merge_Log';
function nikMergeRead_(): array {
    $g = readGrid_(NIK_MERGE_LOG); if (!$g || count($g) < 2) return [];
    $h = array_map('text_', $g[0]); $l = array_search('NIK LAMA', $h, true); $b = array_search('NIK BARU', $h, true);
    $out = [];
    foreach (array_slice($g, 1) as $r) {
        $x = ['lama' => text_($l === false ? null : ($r[$l] ?? null)), 'baru' => text_($b === false ? null : ($r[$b] ?? null))];
        if ($x['lama'] !== '' && $x['baru'] !== '' && $x['lama'] !== $x['baru']) $out[] = $x;
    }
    return $out;
}
function nikResolveRows_(array $meta, array $rows): array {
    $m = []; foreach ($meta['nikMerge'] ?? [] as $x) $m[$x['lama']] = (string) $x['baru'];
    if (!$m) return $rows;
    $f = function ($n) use ($m) { $n = (string) $n; $seen = []; while (isset($m[$n]) && truthy($m[$n]) && !isset($seen[$n])) { $seen[$n] = 1; $n = $m[$n]; } return $n; };
    foreach ($rows as $i => $t) { $n = $f($t['nik']); if ($n !== $t['nik']) { $rows[$i]['nik'] = $n; } }
    return $rows;
}

// ------------------------------------------------------------------
// SellOut.gs
// ------------------------------------------------------------------

function dosRules_(): array {
    if (!sheet_exists_('DOS_Config')) return [['region' => 'ALL', 'min' => 7, 'max' => 45]];
    $seen = []; $out = [];
    foreach (table_('DOS_Config') as $r) {
        $region = text_($r['WILAYAH'] ?? null); $min = PortalCore::number($r['DOS_MIN'] ?? null); $max = PortalCore::number($r['DOS_MAX'] ?? null);
        if ($region === '' || isset($seen[$region]) || $min < 0 || $max <= $min) fail_('DOS_Config: wilayah unik, DOS_MIN >= 0 dan DOS_MAX > DOS_MIN.');
        $seen[$region] = true; $out[] = ['region' => $region, 'min' => $min, 'max' => $max];
    }
    return $out;
}

// ------------------------------------------------------------------
// NikRemap.gs
// ------------------------------------------------------------------

/** NIK SALES yang tidak ada di master diganti ke pemegang dealer sekarang. Mengubah $objects (seperti JS). */
function stRemapNik_(?array &$objects, array $meta): array {
    $pids = []; foreach ($meta['people'] ?? [] as $p) $pids[$p['nik']] = 1;
    $cust = $meta['customers'] ?? []; $pairs = []; $n = 0;
    foreach ($objects ?? [] as $i => $o) {
        $nik = text_($o['NIK SALES'] ?? null); if (isset($pids[$nik])) continue;
        $code = text_($o['KODE CUSTOMER'] ?? null); $c = $cust[$code] ?? null; if (!$c) $c = $cust[custInduk_($meta, $code)] ?? null;
        if (!$c || !truthy($c['nik'] ?? null) || !isset($pids[$c['nik']])) continue;
        $objects[$i]['NIK SALES'] = $c['nik']; $n++;
        $k = js_or($nik, '(kosong)') . ' → ' . $c['nik']; $pairs[$k] = ($pairs[$k] ?? 0) + 1;
    }
    $list = js_sort(js_keys($pairs), fn($a, $b) => $pairs[$b] - $pairs[$a]);
    $note = '';
    if ($n) $note = $n . ' baris NIK tidak terdaftar diganti ke pemegang dealer sekarang: ' . implode(', ', array_map(fn($k) => $k . ' (' . $pairs[$k] . ')', array_slice($list, 0, 12))) . (count($list) > 12 ? ', dst.' : '');
    return ['count' => $n, 'pairs' => $pairs, 'note' => $note];
}
