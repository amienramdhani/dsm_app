<?php
/**
 * Upload ST / SO per region (Data.gs, RegioanlImport.gs, SellOut.gs) + dasar halaman Admin (Admin.gs, Auth.gs).
 * Alur: apiStageImport/apiStageSO (periksa file, data aktif belum berubah) → apiPublishImport/apiPublishSO.
 * Tambahan DSM: bila ada baris bermasalah, SEMUA baris bermasalah dikumpulkan ke file DITOLAK (data.rejFile).
 */
require_once __DIR__ . '/publish.php';
require_once __DIR__ . '/sheetio.php';

/** LockService.getScriptLock() → GET_LOCK MySQL (satu publikasi dalam satu waktu). */
function with_lock_(callable $f) {
    $pdo = db_();
    $got = (int) $pdo->query("SELECT GET_LOCK('dsm_publish', 15)")->fetchColumn();
    if (!$got) fail_('Sistem sedang memproses publikasi lain. Coba lagi sebentar.');
    try { return $f(); } finally { $pdo->query("SELECT RELEASE_LOCK('dsm_publish')"); }
}

class RejectError extends AppError {
    public array $data = [];
}
function reject_fail_(string $msg, array $rejected, array $heads, string $fileName): never {
    $e = new RejectError($msg);
    $e->data = ['rejected' => count($rejected), 'rejFile' => rejectBase64_($fileName, $heads, $rejected),
        'sample' => array_map(fn($x) => ['alasan' => $x['alasan'] ?? '', 'solusi' => $x['solusi'] ?? ''], array_slice($rejected, 0, 20))];
    throw $e;
}

// ------------------------------------------------------------------
// RegioanlImport.gs
// ------------------------------------------------------------------

function parseRegions_($value, array $meta): array {
    $list = json_decode(js_str(js_or($value, '[]')), true);
    if ($list === null && js_str(js_or($value, '[]')) !== 'null') fail_('Pilihan region tidak valid.');
    $known = RegionCore::regions($meta);
    if (!is_array($list) || !array_is_list($list) || !$list) fail_('Pilih big region yang sah.');
    foreach ($list as $r) if (!is_string($r) || !in_array($r, $known, true)) fail_('Pilih big region yang sah.');
    return array_values(uniq($list));
}
function importBrandList_(array $meta): array {
    $b = [];
    $off = function_exists('brandInactive_') ? brandInactive_() : [];
    foreach ($meta['products'] ?? [] as $p) if (truthy($p['device'] ?? null) && truthy($p['brand'] ?? null) && !isset($off[$p['brand']])) $b[] = $p['brand'];
    return js_sort_default(uniq($b));
}
function parseImportBrands_($value, array $meta): array {
    $list = json_decode(js_str(js_or($value, '[]')), true);
    if ($list === null && js_str(js_or($value, '[]')) !== 'null') fail_('Pilihan brand tidak valid.');
    $known = importBrandList_($meta);
    if (!is_array($list) || !array_is_list($list) || !$list) return [];
    $list = array_values(uniq(js_filter(array_map('text_', $list), fn($x) => $x !== '')));
    $bad = js_filter($list, fn($b) => !in_array($b, $known, true));
    if ($bad) fail_('Brand upload tidak dikenal: ' . implode(', ', $bad));
    return $list;
}
function importBrand_(array $t, array $meta, $kind = null): string {
    $b = text_($t['brand'] ?? null);
    return $b !== '' ? $b : text_($meta['products'][$t['sku'] ?? ''] ['brand'] ?? null);
}

function stageRegionImport_(array $form, string $kind): array {
    @ini_set('memory_limit', '2560M'); // file ST/SO sampai 20 MB
    $u = adminGuard_($form['token'] ?? null); $old = snapshot_(); $meta = meta_();
    if (!isset($old['parts'])) fail_('Jalankan Upgrade v4 terlebih dahulu.');
    $regions = parseRegions_($form['regions'] ?? null, $meta); $brands = parseImportBrands_($form['brands'] ?? null, $meta);
    $period = PortalCore::month($form['period'] ?? null); $cutoff = PortalCore::iso($form['cutoff'] ?? null);
    $history = $kind === 'SO' && ($form['mode'] ?? null) === 'HISTORY';
    $multi = ($form['mode'] ?? null) === 'MULTI'; $periods = [$period]; $emptyMonths = [];
    if (substr($cutoff, 0, 7) !== $period) fail_('Cutoff berbeda bulan.');
    assertPeriodOpen_($period);
    if ($multi) {
        $from = PortalCore::month($form['periodFrom'] ?? null);
        if (strcmp($from, $period) >= 0) fail_('Bulan awal harus sebelum bulan akhir. Untuk satu bulan pakai mode "Ganti satu bulan".');
        $periods = monthSequence_($from, $period);
        if (count($periods) > 12) fail_('Upload multi-bulan maksimal 12 bulan.');
        foreach ($periods as $pp) { try { assertPeriodOpen_($pp); } catch (AppError $e) { fail_('Bulan ' . $pp . ' terkunci, upload multi-bulan dibatalkan seluruhnya (tidak ada data yang berubah). ' . $e->getMessage()); } }
        $emptyMonths = json_decode(js_str(js_or($form['emptyMonths'] ?? null, '[]')), true);
        if (!is_array($emptyMonths)) fail_('Deklarasi bulan kosong tidak valid.');
        foreach ($emptyMonths as $m) if (!in_array($m, $periods, true)) fail_('Bulan kosong di luar rentang upload.');
    }
    if ($history && strcmp($cutoff, StockCore::BASE) < 0) fail_('Histori awal minimal Desember 2025.');
    if ($history && $brands) fail_('Mode histori awal SO harus menggunakan Semua Brand.');
    $empty = json_decode(js_str(js_or($form['emptyRegions'] ?? null, '[]')), true);
    if (!is_array($empty)) fail_('Deklarasi region kosong tidak valid.');
    foreach ($empty as $r) if (!in_array($r, $regions, true)) fail_('Deklarasi kosong di luar pilihan.');
    $file = $form['file'] ?? null; $fileName = blob_name($file);
    $objects = $fileName !== '' ? ($kind === 'ST' ? stReadFile_($file) : soReadFile_($file)) : [];
    $heads = $objects ? array_keys($objects[0]) : [];
    $rm = $kind === 'ST' ? stRemapNik_($objects, $meta) : ['count' => 0, 'note' => ''];
    // routing berbasis seluruh ST aktif
    $all = []; foreach (js_keys($old['raw'] ?? []) as $p) foreach (dataRows_((string) $old['raw'][$p]) as $t) $all[] = $t;
    $routing = RegionCore::routing($meta, $all, $old['routing'] ?? null); unset($all);
    $shell = ['meta' => $meta, 'routing' => $routing];
    $parse = fn(array $list) => $kind === 'ST' ? PortalCore::transactions($list, $meta, $multi ? null : $period, $cutoff) : StockCore::parse($list, $meta, ($history || $multi) ? null : $period, $cutoff);
    // seluruh pemeriksaan per baris (format + region + brand); melempar "Baris N: ..." bila ada yang salah
    $full = function (array $list) use ($parse, $kind, $shell, $regions, $brands, $meta, $multi, $periods): array {
        $rows = $parse($list); $by = [];
        foreach ($rows as $i => $t) {
            if ($multi && !in_array(substr($t['date'], 0, 7), $periods, true)) fail_('Baris ' . ($i + 2) . ': tanggal ' . $t['date'] . ' di luar bulan ' . $periods[0] . ' s/d ' . end($periods) . '.');
            $region = RegionCore::route($shell, $t, $kind); $brand = importBrand_($t, $meta, $kind);
            if (!truthy($region) || !in_array($region, $regions, true)) fail_('Baris ' . ($i + 2) . ': dealer ' . js_str($t['customer'] ?? null) . ' masuk ' . (truthy($region) ? $region : 'region belum terpetakan') . ', di luar pilihan.');
            if ($brands && !in_array($brand, $brands, true)) fail_('Baris ' . ($i + 2) . ': brand ' . ($brand !== '' ? $brand : 'tidak dikenal') . ' di luar brand upload ' . implode(', ', $brands) . '.');
            $t['region'] = $region; $by[(string) $region][] = $t; $rows[$i] = $t;
        }
        return [$rows, $by];
    };
    try {
        [$rows, $by] = $full($objects);
    } catch (Throwable $e) {
        if (!$objects) throw $e;
        $sc = strictScan_($kind, $objects, $meta, fn($l) => $full($l));
        if ($sc['rejected']) reject_fail_($e->getMessage() . ' — total ' . count($sc['rejected']) . ' baris bermasalah' . (($sc['stopped'] ?? false) && !empty($sc['error']) ? ' (' . $sc['error'] . ')' : '') . '. File TIDAK diproses; perbaiki SEMUA baris di file DITOLAK lalu upload ulang.', $sc['rejected'], $heads, $fileName);
        throw $e;
    }
    if (count($rows) > ($multi ? 400000 : 150000)) fail_('Maksimal ' . ($multi ? '400.000' : '150.000') . ' baris per impor.');
    $warnings = []; if ($rm['count']) $warnings[] = $rm['note'];
    // DSM v5: dealer yang belum ada di Master Customer tetap masuk total, tapi sekarang diberi tahu di pratinjau.
    $unknown = [];
    foreach ($rows as $t) { $cd = text_($t['customer'] ?? ''); if ($cd !== '' && !isset($meta['customers'][$cd]) && !isset($meta['customers'][custInduk_($meta, $cd)])) $unknown[$cd] = ($unknown[$cd] ?? 0) + 1; }
    if ($unknown) { $ks = array_map('strval', array_keys($unknown)); $warnings[] = array_sum($unknown) . ' baris dari ' . count($ks) . ' dealer belum ada di Master Customer: ' . implode(', ', array_slice($ks, 0, 15)) . (count($ks) > 15 ? ', dst.' : '') . '. Tetap dihitung di total; lengkapi Master Customer supaya masuk ke sales yang benar.'; }
    foreach ($regions as $r) {
        $has = !empty($by[$r]);
        if (!$has && !in_array($r, $empty, true)) fail_('Region ' . $r . ' tidak memiliki baris. Nyatakan nol secara eksplisit.');
        if ($has && in_array($r, $empty, true)) fail_('Region ' . $r . ' dinyatakan kosong tetapi ada data.');
    }
    $perMonth = [];
    if ($multi) {
        foreach ($rows as $t) { $m = substr($t['date'], 0, 7); $perMonth[$m]['n'] = ($perMonth[$m]['n'] ?? 0) + 1; $perMonth[$m]['max'] = max($perMonth[$m]['max'] ?? '', $t['date']); }
        foreach ($periods as $pp) {
            $n = $perMonth[$pp]['n'] ?? 0; $dec = in_array($pp, $emptyMonths, true);
            if (!$n && !$dec) fail_('Bulan ' . $pp . ' tidak memiliki baris. Jika memang kosong, nyatakan bulan ini kosong secara eksplisit.');
            if ($n && $dec) fail_('Bulan ' . $pp . ' dinyatakan kosong tetapi ada data (' . $n . ' baris).');
        }
    }
    if ($multi) $months = $periods; elseif ($history) { $months = uniq(array_merge(array_map(fn($t) => substr($t['date'], 0, 7), $rows), monthSequence_(substr(StockCore::BASE, 0, 7), $period))); } else $months = [$period];
    foreach ($months as $p) assertPeriodOpen_($p);
    if ($history) foreach ($regions as $r) foreach (js_values($old['parts']['SO'] ?? []) as $m) if (isset($m[$r])) fail_('Histori SO region ini sudah ada. Gunakan penggantian bulanan.');
    $controls = [];
    foreach ($multi ? $periods : [$period] as $pp) {
        foreach ($regions as $r) {
            $mine = $multi ? array_values(js_filter($by[$r] ?? [], fn($t) => substr($t['date'], 0, 7) === $pp)) : ($by[$r] ?? []); $tag = $multi ? $pp . ' · ' . $r : $r;
            $entry = $old['parts'][$kind][$pp][$r] ?? null; $oldRows = $entry ? dataRows_((string) $entry['file']) : [];
            $beforeRows = $brands ? js_filter($oldRows, fn($t) => in_array(importBrand_($t, $meta, $kind), $brands, true)) : $oldRows;
            $incoming = summarySO_($mine); $before = summarySO_($beforeRows);
            if ($entry && $pp === $period && strcmp($cutoff, $entry['cutoff']) < 0) $warnings[] = $tag . ': cutoff mundur dari ' . $entry['cutoff'];
            if ($before['qty'] > 0 && $incoming['qty'] < $before['qty'] * .7) $warnings[] = $tag . ': QTY scope upload turun lebih dari 30%; periksa sumber.';
            if ($before['rows'] > 0 && $incoming['rows'] < $before['rows'] * .8) $warnings[] = $tag . ': JUMLAH BARIS turun lebih dari 20% (' . $before['rows'] . ' → ' . $incoming['rows'] . '); pastikan file lengkap dari tanggal 1.';
            $controls[] = ['period' => $pp, 'region' => $r, 'before' => $before, 'incoming' => $incoming, 'oldCutoff' => $entry['cutoff'] ?? null];
        }
    }
    if ($multi) foreach ($periods as $pp) {
        if ($pp === $period) continue; $mx = $perMonth[$pp]['max'] ?? null; $end = PortalCore::end($pp);
        if ($mx && strcmp($mx, StockCore::shift($end, -7)) < 0) $warnings[] = 'Bulan ' . $pp . ': tanggal terakhir di file ' . $mx . ' (akhir bulan ' . $end . '). Bulan ini dianggap LENGKAP sampai akhir bulan; pastikan memang tidak ada transaksi setelahnya.';
        if (in_array($pp, $emptyMonths, true)) $warnings[] = 'Bulan ' . $pp . ' dinyatakan KOSONG: seluruh data bulan itu untuk region/brand pilihan akan dihapus.';
    }
    if ($multi) $warnings[] = 'Multi-bulan ' . $periods[0] . ' s/d ' . $period . ': semua bulan diganti sekaligus (atau tidak sama sekali). Bulan sebelum ' . $period . ' dianggap lengkap sampai akhir bulan.';
    if ($history) $warnings[] = 'Histori awal menyatakan seluruh bulan sejak Desember 2025 lengkap untuk region pilihan.';
    if ($brands) $warnings[] = 'Upload parsial brand: hanya ' . implode(', ', $brands) . ' yang diganti; brand lain pada region pilihan dipertahankan.';
    $id = uuid_();
    $stage = ['kind' => $kind, 'actor' => $u['nik'], 'base' => $old['id'], 'config' => configHash_($meta), 'created' => now_ms(), 'regions' => $regions, 'brands' => $brands, 'period' => $period, 'cutoff' => $cutoff, 'history' => $history, 'months' => $months, 'multi' => $multi, 'rows' => $rows];
    adminGuard_($form['token'] ?? null);
    prop_set_('REGION_STAGE_' . $id, writeJson_('stage-region-' . $id, $stage));
    return ['id' => $id, 'kind' => $kind, 'period' => $period, 'cutoff' => $cutoff, 'history' => $history, 'multi' => $multi, 'months' => $months, 'brands' => $brands, 'controls' => $controls, 'warnings' => $warnings];
}

function publishRegionImport_($token, $id): array {
    @ini_set('memory_limit', '2560M'); // publikasi data besar (±145 ribu baris) butuh ±1,7 GB
    $u = adminGuard_($token);
    return with_lock_(function () use ($token, $id, $u) {
        adminGuard_($token);
        $pointer = prop_get_('REGION_STAGE_' . js_str($id));
        if (!$pointer) fail_('Pratinjau tidak tersedia.');
        $s = readJson_($pointer); $old = snapshot_(); $meta = meta_();
        if ($s['actor'] !== $u['nik'] || $s['base'] !== $old['id'] || $s['config'] !== configHash_($meta) || now_ms() - $s['created'] > 3600000) fail_('Pratinjau kedaluwarsa atau data berubah; periksa ulang.');
        foreach ($s['months'] as $m) assertPeriodOpen_($m);
        $scope = !empty($s['brands']) ? ' [' . implode(', ', $s['brands']) . ']' : '';
        checkpoint_($u['nik'], 'Impor ' . $s['kind'] . (!empty($s['multi']) ? ' multi-bulan ' . $s['months'][0] . '..' . end($s['months']) : '') . ' ' . implode(', ', $s['regions']) . $scope);
        $byRM = [];
        foreach ($s['rows'] as $t) $byRM[(string) $t['region']][substr($t['date'], 0, 7)][] = $t;
        $parts = $old['parts'];
        foreach ($s['months'] as $p) {
            $incoming = [];
            foreach ($s['regions'] as $r) {
                $selected = $byRM[(string) $r][$p] ?? [];
                $cur = $parts[$s['kind']][$p][$r] ?? null; $current = $cur ? dataRows_((string) $cur['file']) : [];
                $merged = !empty($s['brands']) ? array_merge(js_filter($current, fn($t) => !in_array(importBrand_($t, $meta, $s['kind']), $s['brands'], true)), $selected) : $selected;
                $incoming[$r] = ['file' => partWrite_($s['kind'], (string) $p, (string) $r, $merged), 'cutoff' => $p === $s['period'] ? $s['cutoff'] : PortalCore::end($p)];
            }
            $parts = RegionCore::replace($parts, $s['kind'], $p, $s['regions'], $incoming);
        }
        $result = publish_($meta, $old['raw'], $old['coverage'], $u['nik'], $s['kind'] . ' ' . $s['period'] . ' ' . implode(', ', $s['regions']) . $scope, null, $parts);
        prop_delete_('REGION_STAGE_' . $id);
        return $result;
    });
}

function apiStageImport($form) { return stageRegionImport_(is_array($form) ? $form : [], 'ST'); }
function apiPublishImport($token, $id) { return publishRegionImport_($token, $id); }
function apiStageSO($form) { return stageRegionImport_(is_array($form) ? $form : [], 'SO'); }
function apiPublishSO($token, $id) { return publishRegionImport_($token, $id); }
function apiPublishConfig($token) {
    $u = guard_($token, true);
    return with_lock_(function () use ($token, $u) {
        guard_($token, true);
        checkpoint_($u['nik'], 'Publikasi konfigurasi');
        $old = snapshot_(); validateConfigChange_($old, meta_());
        return publish_(meta_(), $old['raw'], $old['coverage'], $u['nik'], 'Publikasi konfigurasi');
    });
}
function apiRegionImportTemplate($token, $kind = null) {
    $db = snapshot_(); adminGuard_($token, $db); $kind = js_upper(js_str(js_or($kind, 'ST')));
    if ($kind === 'ST') return templateXlsx_('Template-ST.xlsx', 'Master_Transaksi', ['TANGGAL TRANSAKSI', 'BADAN USAHA', 'NO TRANSAKSI', 'NOMOR SJ', 'KODE GUDANG', 'KODE PRODUK', 'KODE CUSTOMER', 'NIK SALES', 'QTY', 'AMOUNT', 'ID_BARIS', 'REF_TRANSAKSI'], [], ['Isi data sesuai scope Region + Brand yang dipilih di aplikasi.', 'File harus lengkap dari tanggal 1 sampai cutoff untuk scope upload.', 'Brand divalidasi dari KODE PRODUK terhadap Master_Product.', 'ID_BARIS (opsional): nomor baris unik per transaksi — isi kalau ada 2 baris yang memang sama persis (barang sama, qty sama) dalam satu nota.', 'REF_TRANSAKSI (opsional, untuk retur): isi NO TRANSAKSI penjualan asalnya, supaya NOO dealer bisa difinalkan.']);
    if ($kind === 'SO') return templateXlsx_('Template-SO.xlsx', 'Master_SO', ['KODE CUSTOMER', 'TANGGAL SO', 'KODE PRODUK', 'QTY'], [], ['Isi data SELL OUT sesuai scope Region + Brand yang dipilih di aplikasi.', 'Brand divalidasi dari KODE BARANG terhadap Master_Product.']);
    fail_('Jenis data tidak valid.');
}

// ------------------------------------------------------------------
// Admin.gs / Auth.gs / ExportAccess.gs
// ------------------------------------------------------------------

function exportAccessTypes_(): array { return [['id' => 'SUMMARY', 'label' => 'Ringkasan'], ['id' => 'BRAND', 'label' => 'Brand'], ['id' => 'KPI', 'label' => 'KPI'], ['id' => 'DOS', 'label' => 'DOS'], ['id' => 'DEALER', 'label' => 'Dealer']]; }
function exportAccessCanManage_($u, ?array $rows = null): bool {
    $role = preg_replace('/\s+/', ' ', js_upper(text_($u['role'] ?? ''))); $access = exportAccessForUser_($u, $rows);
    return $role === 'SUPER ADMIN' && !in_array(false, $access, true);
}
function exportAccessAdminState_($actor): array {
    $rows = exportAccessRows_(); $grants = []; $configured = [];
    foreach ($rows as $r) $configured[] = $r['nik'];
    foreach ($rows as $r) if ($r['active']) $grants[(string) $r['nik']][] = $r['type'];
    foreach ($grants as $k => $v) { sort($v, SORT_STRING); $grants[$k] = $v; }
    return ['types' => exportAccessTypes_(), 'grants' => js_obj($grants), 'configured' => array_values(uniq($configured)), 'canManage' => exportAccessCanManage_($actor, $rows)];
}
function backupStatus_(): array {
    $log = optionalTable_('Backup_Log'); $last = null;
    foreach ($log as $r) if (($r['STATUS'] ?? null) === 'LENGKAP') $last = $r;
    $lastEvent = $log ? end($log) : null;
    return ['running' => false, 'last' => $last, 'lastEvent' => $lastEvent, 'overdue' => !$last || (time() - (strtotime((string) $last['WAKTU']) ?: 0)) > 36 * 3600, 'root' => null, 'retention' => function_exists('backupKeep_') ? backupKeep_() : 14, 'secondary' => null];
}
function partitionStatus_($parts): array {
    $out = [];
    foreach (['ST', 'SO'] as $kind) {
        $per = $parts[$kind] ?? []; $ps = js_keys($per); sort($ps, SORT_STRING);
        foreach ($ps as $period) { $rs = js_keys($per[$period]); sort($rs, SORT_STRING); foreach ($rs as $region) $out[] = ['kind' => $kind, 'period' => (string) $period, 'region' => (string) $region, 'cutoff' => $per[$period][$region]['cutoff'] ?? null]; }
    }
    return $out;
}
function lastSuper_(array $db, $nik): bool {
    foreach ($db['meta']['people'] as $p) if ($p['role'] === 'SUPER ADMIN' && $p['nik'] !== $nik && PortalCore::active(account_($p['nik'])['AKTIF'] ?? null)) return false;
    return true;
}

function apiAdmin($token) {
    $db = snapshot_(); $u = adminGuard_($token, $db); $super = $u['role'] === 'SUPER ADMIN';
    $users = [];
    foreach ($db['meta']['people'] as $p) {
        if ($p['nik'] === '-' || !canManageUser_($u, $p)) continue;
        $a = account_($p['nik']);
        $users[] = array_merge($p, ['active' => $a ? PortalCore::active($a['AKTIF']) : false, 'hasPin' => (bool) truthy($a['PIN_HASH'] ?? null), 'tempPin' => ($a && PortalCore::active($a['WAJIB_GANTI'] ?? null)) ? tempPinGet_((string) $p['nik']) : '']);
    }
    $bosses = [];
    foreach ($db['meta']['people'] as $p) if ($super || (in_array($p['role'], ['ASM', 'RGM'], true) && in_array($p['wilayah'] ?? null, $u['adminRegions'] ?? [], true))) $bosses[] = ['nik' => $p['nik'], 'nama' => $p['nama'], 'role' => $p['role'], 'wilayah' => $p['wilayah'] ?? null];
    $versions = $super ? array_reverse(array_slice(optionalTable_('Version_Log'), -60)) : [];
    return ['superuser' => $super, 'url' => null, 'notes' => $super ? $db['notes'] : [], 'regions' => RegionCore::regions($db['meta']),
        'brands' => importBrandList_($db['meta']), 'regionMap' => settings_()['regions'], 'managedRegions' => $u['adminRegions'] ?? [],
        'periods' => $db['coverage'], 'processed' => $db['processed'], 'parts' => partitionStatus_($db['parts'] ?? null), 'users' => $users, 'bosses' => $bosses,
        'locks' => $super ? array_map(function ($r) { foreach ($r as $k => $v) if ($v === 'TRUE' || $v === 'FALSE') $r[$k] = $v === 'TRUE'; return $r; }, optionalTable_('Period_Locks')) : [], 'versions' => $versions, 'backup' => $super ? backupStatus_() : null,
        'exportAccess' => $super ? exportAccessAdminState_($u) : null];
}

function apiSaveUser($token, $input) {
    adminGuard_($token); $input = is_array($input) ? $input : [];
    return with_lock_(function () use ($token, $input) {
        $db = snapshot_(); $u = adminGuard_($token, $db); $isSuper = $u['role'] === 'SUPER ADMIN';
        $nik = text_($input['nik'] ?? null); $original = text_($input['original'] ?? null); $name = text_($input['nama'] ?? null);
        $role = text_($input['role'] ?? null); $region = text_($input['region'] ?? null); $boss = text_($input['atasan'] ?? null);
        $old = PortalCore::person($db['meta']['people'], $nik);
        $allowedRoles = $isSuper ? array_map('strval', array_keys(PortalCore::LEVEL)) : (($old['role'] ?? '') === 'TELEMARKETING' && tmCanManage_($u,$nik) ? ['TELEMARKETING'] : ['SALES', 'ASM', 'RGM']);
        if (!preg_match('/^[A-Za-z0-9._-]{2,60}$/', $nik) || $name === '' || mb_strlen($name) > 120 || !in_array($role, $allowedRoles, true)) fail_('NIK, nama, atau jabatan tidak valid.');
        if ($original !== '' && $original !== $nik) fail_('NIK akun tidak dapat diganti.');
        if (($original !== '' && !$old) || ($original === '' && $old)) fail_('User tidak ditemukan atau NIK sudah terdaftar.');
        if ($old && !canManageUser_($u, $old)) fail_('User di luar kewenangan.');
        if (($old['role']??'')==='TELEMARKETING' && $role!=='TELEMARKETING' && tmAccountReferences_($nik)) fail_('Akun Telemarketing memiliki histori. Nonaktifkan akun dan gunakan NIK baru untuk jabatan lain agar histori tetap utuh.');
        if ($nik === $u['nik'] && ($role !== $old['role'] || $region !== ($old['region'] ?? '') || $boss !== ($old['atasan'] ?? ''))) fail_('Perubahan hak akun sendiri harus dilakukan Super Admin lain.');
        if (($old['role'] ?? null) === 'SUPER ADMIN' && $role !== 'SUPER ADMIN' && lastSuper_($db, $nik)) fail_('Super Admin aktif terakhir harus dipertahankan.');
        $rm = settings_()['regions']; $willRegion = '';
        foreach ($rm as $r) if (js_str($r['REGION'] ?? null) === $region) { $willRegion = js_str(js_or($r['WILAYAH_KPI'] ?? null, '')); break; }
        if (!$isSuper && $role !== 'TELEMARKETING' && !in_array($willRegion, $u['adminRegions'] ?? [], true)) fail_('Region tujuan di luar penugasan.');
        if (!$isSuper && $role !== 'TELEMARKETING' && $old && ($old['wilayah'] ?? null) !== $willRegion) fail_('Perpindahan antar big region melalui Super Admin.');
        if ($role === 'TELEMARKETING' && (!$boss || (person_($db, $boss)['role'] ?? '') !== 'RGM')) fail_('Pilih satu RGM sebagai Atasan Utama Telemarketing.');
        if ($boss !== '') {
            $b = PortalCore::person($db['meta']['people'], $boss);
            if (!$b || PortalCore::lv($b['role']) <= PortalCore::lv($role) || $b['role'] === 'ADMIN' || (!$isSuper && !canManageUser_($u, $b))) fail_('Atasan harus berada pada jabatan lebih tinggi dalam kewenangan.');
        }
        $access = is_array($input['adminRegions'] ?? null) ? array_values(uniq($input['adminRegions'])) : [];
        if ($role === 'ADMIN') { $known = RegionCore::regions($db['meta']); if (!$access || array_diff($access, $known)) fail_('Pilih wilayah tugas Admin.'); }
        $acting = truthy($input['acting'] ?? null) && in_array($role, ['ASM', 'RGM'], true);
        $portfolio = text_($input['portfolio'] ?? null) ?: $region;
        if ($acting) { $pw = null; foreach ($rm as $r) if (js_str($r['REGION'] ?? null) === $portfolio) { $pw = $r['WILAYAH_KPI'] ?? null; break; } if ($pw !== $willRegion) fail_('Portofolio rangkap harus satu big region.'); }
        $grids = ['Master_Sales' => readGrid_('Master_Sales'), 'Admin_Access' => readGrid_('Admin_Access'), '_Auth' => readGrid_('_Auth')];
        $heads = $grids['Master_Sales'][0]; $objects = table_('Master_Sales'); $source = [];
        foreach ($objects as $r) if (js_str($r['NIK KARYAWAN'] ?? null) === $nik) { $source = $r; break; }
        $next = array_values(js_filter($objects, fn($r) => js_str($r['NIK KARYAWAN'] ?? null) !== $nik));
        $next[] = array_merge($source, ['NIK KARYAWAN' => $nik, 'SALES' => $name, 'POSISI' => $role, 'REGION' => $region, 'NIK ATASAN' => $boss]);
        if ($acting) $next[] = array_merge($source, ['NIK KARYAWAN' => $nik, 'SALES' => $name, 'POSISI' => 'SALES', 'REGION' => $portfolio, 'NIK ATASAN' => $nik]);
        PortalCore::normalize(['sales' => $next, 'customers' => table_('Master_Customer'), 'products' => table_('Master_Product')], settings_());
        checkpoint_($u['nik'], 'User ' . $nik); $pin = null;
        try {
            putTable_('Master_Sales', $heads, array_map(fn($r) => array_map(fn($h) => $r[$h] ?? '', $heads), $next));
            $rows = []; foreach (optionalTable_('Admin_Access') as $r) if (js_str($r['NIK'] ?? null) !== $nik) $rows[] = [$r['NIK'], $r['WILAYAH']];
            if ($role === 'ADMIN') foreach ($access as $r) $rows[] = [$nik, $r];
            putTable_('Admin_Access', ['NIK', 'WILAYAH'], $rows);
            if (!$old) { $pin = randomPin_(); setPin_($nik, $pin, true); }
            $meta = meta_(); validateConfigChange_($db, $meta);
            publish_($meta, $db['raw'], $db['coverage'], $u['nik'], 'User ' . $nik);
            audit_($u['nik'], 'SAVE_USER', $nik . ' ' . $role);
            return ['nik' => $nik, 'pin' => $pin];
        } catch (Throwable $e) {
            foreach ($grids as $n => $g) restoreGrid_($n, $g);
            throw $e;
        }
    });
}
function apiPeriodLock($token, $period, $locked, $reason) {
    return with_lock_(function () use ($token, $period, $locked, $reason) {
        $u = guard_($token, true); $period = PortalCore::month($period); $reason = text_($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) fail_('Isi alasan 5–500 karakter.');
        checkpoint_($u['nik'], 'Kunci periode ' . $period);
        $rows = []; foreach (optionalTable_('Period_Locks') as $r) if (($r['PERIODE'] ?? null) !== $period) $rows[] = [$r['PERIODE'], $r['TERKUNCI'], $r['ALASAN'], $r['OLEH'], $r['WAKTU']];
        $rows[] = [$period, truthy($locked), $reason, $u['nik'], iso_now()];
        putTable_('Period_Locks', ['PERIODE', 'TERKUNCI', 'ALASAN', 'OLEH', 'WAKTU'], $rows);
        audit_($u['nik'], 'PERIOD_LOCK', $period . ' ' . (truthy($locked) ? 'true' : 'false') . ' ' . $reason);
        return true;
    });
}
function apiResetPin($token, $nik) {
    enforceUser_($token, $nik);
    return with_lock_(function () use ($token, $nik) {
        $x = enforceUser_($token, $nik); $u = $x['u'];
        checkpoint_($u['nik'], 'Reset PIN ' . $nik);
        $pin = randomPin_(); setPin_($nik, $pin, true); audit_($u['nik'], 'RESET_PIN', $nik);
        return ['nik' => $nik, 'pin' => $pin];
    });
}
function apiSetActive($token, $nik, $isActive) {
    enforceUser_($token, $nik);
    return with_lock_(function () use ($token, $nik, $isActive) {
        $x = enforceUser_($token, $nik); $u = $x['u'];
        if ($nik === $u['nik']) fail_('Tidak dapat mengubah status akun sendiri.');
        if (!truthy($isActive) && $x['target']['role'] === 'SUPER ADMIN' && lastSuper_($x['db'], $nik)) fail_('Super Admin aktif terakhir harus dipertahankan.');
        $a = account_($nik); if (!$a) fail_('Buat PIN terlebih dahulu.');
        if (truthy($isActive) && !truthy($a['PIN_HASH'])) fail_('Buat PIN sementara dahulu.');
        checkpoint_($u['nik'], 'Status akun ' . $nik);
        $a['AKTIF'] = truthy($isActive); $a['VERSI'] = PortalCore::number($a['VERSI']) + 1; writeAccount_($a);
        audit_($u['nik'], 'ACCOUNT_ACTIVE', $nik . ' ' . (truthy($isActive) ? 'true' : 'false'));
        return true;
    });
}
function apiSaveExportAccess($token, $input) {
    return with_lock_(function () use ($token, $input) {
        $db = snapshot_(); $actor = guard_($token, true, false, $db); $rows = exportAccessRows_();
        if (!exportAccessCanManage_($actor, $rows)) fail_('Hanya Super Admin utama dengan seluruh hak export yang dapat mengatur izin export.');
        $input = is_array($input) ? $input : []; $nik = text_($input['nik'] ?? null); $target = $nik !== '-' ? PortalCore::person($db['meta']['people'], $nik) : null;
        if (!$target) fail_('User tidak ditemukan.');
        if ($nik === $actor['nik']) fail_('Hak export akun sendiri tidak dapat diubah. Gunakan Super Admin utama lainnya.');
        $known = exportAccessTypeIds_(); $types = [];
        foreach (is_array($input['types'] ?? null) ? $input['types'] : [] as $x) { $t = js_upper(text_($x)); if ($t !== '') $types[] = $t; }
        $types = array_values(uniq($types));
        foreach ($types as $t) if (!in_array($t, $known, true)) fail_('Jenis export tidak valid.');
        $now = iso_now(); $kept = [];
        foreach (optionalTable_('Export_Access') as $r) if (text_($r['NIK'] ?? null) !== $nik) $kept[] = [$r['NIK'], $r['JENIS_EXPORT'], $r['AKTIF'], $r['UPDATED_BY'] ?? '', $r['UPDATED_AT'] ?? ''];
        foreach ($known as $t) $kept[] = [$nik, $t, in_array($t, $types, true), $actor['nik'], $now];
        putTable_('Export_Access', ['NIK', 'JENIS_EXPORT', 'AKTIF', 'UPDATED_BY', 'UPDATED_AT'], $kept);
        audit_($actor['nik'], 'EXPORT_ACCESS', $nik . ' ' . ($types ? implode('|', $types) : 'NONE'));
        return ['nik' => $nik, 'types' => $types, 'configured' => true];
    });
}
function apiStageRestore($token, $version, $kind, $period, $regions, $reason) {
    $u = guard_($token, true); $db = snapshot_();
    if (!in_array($kind, ['ST', 'SO'], true)) fail_('Jenis data tidak valid.');
    $period = PortalCore::month($period); assertPeriodOpen_($period);
    $regions = parseRegions_(js_stringify($regions), $db['meta']); $reason = text_($reason);
    if (mb_strlen($reason) < 5) fail_('Isi alasan pemulihan.');
    $okV = false; foreach (optionalTable_('Version_Log') as $r) if (($r['SNAPSHOT'] ?? null) === $version) { $okV = true; break; }
    if (!$okV) fail_('Versi bukan riwayat portal ini.');
    $past = versionParts_($version);
    foreach ($regions as $r) if (empty($past['parts'][$kind][$period][$r])) fail_('Versi ini tidak memiliki semua partisi yang dipilih.');
    $entries = []; $controls = [];
    foreach ($regions as $r) {
        $entries[$r] = $past['parts'][$kind][$period][$r]; $cur = $db['parts'][$kind][$period][$r] ?? null;
        $controls[] = ['region' => $r, 'before' => summarySO_($cur ? dataRows_((string) $cur['file']) : []), 'incoming' => summarySO_(dataRows_((string) $entries[$r]['file'])), 'cutoff' => $entries[$r]['cutoff']];
    }
    $id = uuid_();
    prop_set_('RESTORE_STAGE_' . $id, writeJson_('restore-preview-' . $id, ['actor' => $u['nik'], 'created' => now_ms(), 'base' => $db['id'], 'kind' => $kind, 'period' => $period, 'regions' => $regions, 'reason' => $reason, 'entries' => $entries]));
    return ['id' => $id, 'controls' => $controls];
}
function apiCommitRestore($token, $id) {
    return with_lock_(function () use ($token, $id) {
        $u = guard_($token, true); $ptr = prop_get_('RESTORE_STAGE_' . js_str($id));
        if (!$ptr) fail_('Pratinjau tidak tersedia.');
        $s = readJson_($ptr); $db = snapshot_();
        if ($s['actor'] !== $u['nik'] || $s['base'] !== $db['id'] || now_ms() - $s['created'] > 3600000) fail_('Data berubah; periksa ulang.');
        assertPeriodOpen_($s['period']); checkpoint_($u['nik'], 'Pemulihan ' . $s['reason']);
        $parts = RegionCore::replace($db['parts'], $s['kind'], $s['period'], $s['regions'], $s['entries']);
        $r = publish_($db['meta'], $db['raw'], $db['coverage'], $u['nik'], 'RESTORE ' . $s['kind'] . ' ' . $s['period'] . ' ' . implode(', ', $s['regions']) . ' ' . $s['reason'], null, $parts);
        prop_delete_('RESTORE_STAGE_' . $id); revokeAll_();
        return $r;
    });
}
