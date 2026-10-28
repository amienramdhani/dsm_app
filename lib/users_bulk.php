<?php
/** Mobile.gs.gs (user massal) + AccountStatus.gs (status akun massal). */
require_once __DIR__ . '/bulk.php';

function userBulkFix_(string $m, array $u): string {
    $super = $u['role'] === 'SUPER ADMIN';
    if (preg_match('/Region belum dipetakan/', $m)) return 'Tambah region di Admin › Master › Region Map, atau ganti REGION ke region yang ada (bukan Big Region).';
    if (preg_match('/NIK tidak valid/', $m)) return 'NIK hanya huruf/angka/titik/strip (2–60 karakter) dan tidak boleh dobel di file.';
    if (preg_match('#Nama/jabatan#', $m)) return 'Isi NAMA, dan JABATAN/POSISI: SALES, ASM, RGM' . ($super ? ', ADMIN, HEAD OF SALES, dst.' : '.');
    if (preg_match('/luar penugasan/', $m)) return 'Region ini di luar wilayah tugas Anda — minta Super Admin.';
    if (preg_match('/Wilayah Admin/', $m)) return 'Isi WILAYAH ADMIN dengan Big Region yang ada, pisahkan dengan |.';
    if (preg_match('/atasan wajib/', $m)) return 'Isi NIK ATASAN (ASM untuk Sales, RGM untuk ASM).';
    if (preg_match('/Atasan tidak valid/', $m)) return 'NIK ATASAN harus terdaftar (atau ada di file yang sama) dan jabatannya lebih tinggi.';
    return 'Perbaiki baris ini lalu upload ulang.';
}

function validateUserBulk_(array $rows, array $db, array $u): array {
    if (!$rows || count($rows) > 1000) fail_('Isi 1–1.000 user per file.');
    $seen = []; $rm = settings_()['regions']; $existing = [];
    foreach ($db['meta']['people'] as $p) $existing[(string) $p['nik']] = 1;
    $isSuper = $u['role'] === 'SUPER ADMIN';
    $allowedRoles = $isSuper ? array_map('strval', array_keys(PortalCore::LEVEL)) : ['SALES', 'ASM', 'RGM'];
    $known = null; $out = [];
    foreach (array_values($rows) as $i => $r) {
        $nik = text_($r['NIK'] ?? null);
        if (!preg_match('/^[A-Za-z0-9._-]{2,60}$/D', $nik) || isset($seen[$nik])) fail_('NIK tidak valid/duplikat pada baris ' . ($i + 2));
        $seen[$nik] = 1;
        if (isset($existing[$nik])) { $out[] = ['nik' => $nik, 'skip' => true, 'nama' => 'Akun sudah terdaftar', 'row' => $r]; continue; }
        $role = js_upper(text_($r['POSISI'] ?? null)); $nama = text_($r['NAMA'] ?? null); $region = text_($r['REGION'] ?? null);
        $regionMatch = null; $wilayah = '';
        foreach ($rm as $x) if (js_upper(text_($x['REGION'] ?? null)) === js_upper($region)) { $regionMatch = $x['REGION']; $wilayah = js_str(js_or($x['WILAYAH_KPI'] ?? null, '')); break; }
        $atasan = text_($r['NIK_ATASAN'] ?? null);
        $access = array_values(array_filter(array_map('trim', explode('|', text_($r['WILAYAH_ADMIN'] ?? null))), fn($s) => $s !== ''));
        if ($nama === '' || mb_strlen($nama) > 120 || !in_array($role, $allowedRoles, true)) fail_('Nama/jabatan tidak valid: ' . $nik);
        if (in_array($role, ['SALES', 'ASM', 'RGM'], true) && $wilayah === '') fail_('Region belum dipetakan: NIK ' . $nik . ' · REGION "' . $region . '" tidak ada di kolom REGION sheet Region_Map (isi region/sub region seperti BIG PURWOKERTO, bukan Big Region seperti JAWA TENGAH). Tambahkan dulu lewat Bulk master > Region Map.');
        if (!$isSuper && !in_array($wilayah, $u['adminRegions'] ?? [], true)) fail_('Region di luar penugasan: ' . $nik);
        if ($role === 'ADMIN') {
            $known ??= RegionCore::regions($db['meta']);
            if (!$access || array_diff($access, $known)) fail_('Wilayah Admin tidak valid: ' . $nik);
        }
        $out[] = ['nik' => $nik, 'nama' => $nama, 'role' => $role, 'region' => $regionMatch !== null && $regionMatch !== '' ? $regionMatch : $region, 'wilayah' => $wilayah, 'atasan' => $atasan, 'access' => $access, 'row' => $r, 'skip' => false];
    }
    $people = $db['meta']['people'];
    foreach ($out as $r) if (!$r['skip']) $people[] = ['nik' => $r['nik'], 'role' => $r['role'], 'nama' => $r['nama'], 'wilayah' => $r['wilayah'], 'atasan' => $r['atasan']];
    foreach ($out as $r) {
        if ($r['skip']) continue;
        $b = null; foreach ($people as $p) if ($p['nik'] === $r['atasan']) { $b = $p; break; }
        if (in_array($r['role'], ['SALES', 'ASM', 'RGM'], true) && $r['atasan'] === '') fail_('NIK atasan wajib: ' . $r['nik']);
        if ($r['atasan'] !== '' && (!$b || $b['role'] === 'ADMIN' || PortalCore::lv($b['role']) <= PortalCore::lv($r['role']) || (!$isSuper && !canManageUser_($u, $b)))) fail_('Atasan tidak valid/di luar kewenangan: ' . $r['nik']);
    }
    return $out;
}

function userBulkPartition_(array $rows, array $db, array $u): array {
    $rows = array_values($rows); $rejected = [];
    for ($guard = 0; $guard <= 1000; $guard++) {
        if (!$rows) return ['ok' => [], 'rows' => [], 'rejected' => $rejected];
        try { return ['ok' => validateUserBulk_($rows, $db, $u), 'rows' => $rows, 'rejected' => $rejected]; }
        catch (AppError $e) {
            $m = $e->getMessage(); $idx = -1;
            if (preg_match('/baris (\d+)/i', $m, $b)) $idx = (int) $b[1] - 2;
            else {
                $mm = preg_replace('/ · .*/us', '', $m);
                $n = null;
                // DSM: urutan dibalik dari Apps Script — di sana "NIK atasan wajib: 4010" salah menangkap kata "atasan"
                // sehingga satu baris tanpa atasan menggagalkan SELURUH file alih-alih masuk file DITOLAK.
                if (preg_match('/: ([A-Za-z0-9._-]{2,60})\s*$/', $mm, $x)) $n = $x[1];
                elseif (preg_match('/NIK ([A-Za-z0-9._-]{2,60})/', $mm, $x)) $n = $x[1];
                if ($n !== null) foreach ($rows as $k => $r) if (text_($r['NIK'] ?? null) === $n) { $idx = $k; break; }
            }
            if ($idx < 0 || $idx >= count($rows)) throw $e;
            $rejected[] = ['row' => $rows[$idx], 'alasan' => str_replace(' Tambahkan dulu lewat Bulk master > Region Map.', '', $m), 'solusi' => userBulkFix_($m, $u)];
            array_splice($rows, $idx, 1);
        }
    }
    fail_('Terlalu banyak baris salah.');
}

function apiUserTemplate($token) {
    adminGuard_($token);
    return templateXlsx_('Template-User-Massal.xlsx', 'Template_User', USER_BULK_HEADERS, [], []);
}

function apiStageUsers($form) {
    $form = is_array($form) ? $form : [];
    $db = snapshot_(); $u = adminGuard_($form['token'] ?? null, $db); $all = userBulkRows_($form['file'] ?? null);
    if (count($all) > 1000) fail_('Maksimal 1.000 user per file.');
    $P = userBulkPartition_($all, $db, $u); $id = uuid_();
    $name = ($form['file'] ?? null) && method_exists($form['file'], 'getName') ? $form['file']->getName() : 'user.xlsx';
    prop_set_('USER_STAGE_' . $id, writeJson_('user-preview-' . $id, ['actor' => $u['nik'], 'base' => $db['id'], 'config' => configHash_(meta_()), 'created' => now_ms(), 'rows' => $P['rows'], 'rejected' => $P['rejected'], 'name' => $name]));
    return ['id' => $id, 'rejected' => count($P['rejected']),
        'rejectedSample' => array_map(fn($x) => ['nik' => text_($x['row']['NIK'] ?? null), 'nama' => text_($x['row']['NAMA'] ?? null), 'alasan' => $x['alasan'], 'solusi' => $x['solusi']], array_slice($P['rejected'], 0, 50)),
        'rows' => array_map(fn($r) => ['nik' => $r['nik'], 'nama' => $r['nama'], 'role' => $r['role'] ?? '', 'region' => $r['region'] ?? '', 'status' => $r['skip'] ? 'Dilewati (sudah terdaftar)' : 'User baru'], $P['ok'])];
}

function apiUsersRejected($token, $id) {
    $db = snapshot_(); $u = adminGuard_($token, $db); $ptr = prop_get_('USER_STAGE_' . js_str($id));
    if (!$ptr) fail_('Pratinjau tidak tersedia.');
    $s = readJson_($ptr); if ($s['actor'] !== $u['nik']) fail_('Pratinjau milik user lain.');
    if (empty($s['rejected'])) fail_('Tidak ada baris ditolak.');
    return rejectBase64_($s['name'] ?? 'user.xlsx', array_keys($s['rejected'][0]['row']), $s['rejected']);
}

function apiCommitUsers($token, $id) {
    return with_lock_(function () use ($token, $id) {
        $db = snapshot_(); $u = adminGuard_($token, $db); $ptr = prop_get_('USER_STAGE_' . js_str($id));
        if (!$ptr) fail_('Pratinjau sudah dipakai/tidak tersedia.');
        $stage = readJson_($ptr);
        if ($stage['actor'] !== $u['nik'] || $stage['base'] !== $db['id'] || $stage['config'] !== configHash_(meta_()) || now_ms() - $stage['created'] > 3600000) fail_('Data berubah/pratinjau kedaluwarsa. Periksa file lagi.');
        if (empty($stage['rows'])) { prop_delete_('USER_STAGE_' . $id); return ['created' => 0, 'skipped' => 0, 'rows' => [], 'download' => false]; }
        $all = validateUserBulk_($stage['rows'], $db, $u); $rows = array_values(array_filter($all, fn($r) => !$r['skip']));
        if (!$rows) { prop_delete_('USER_STAGE_' . $id); return ['created' => 0, 'skipped' => count($all), 'rows' => [], 'download' => $u['role'] === 'SUPER ADMIN']; }
        $grids = ['Master_Sales' => readGrid_('Master_Sales'), 'Admin_Access' => readGrid_('Admin_Access'), '_Auth' => readGrid_('_Auth')];
        $objects = table_('Master_Sales');
        foreach ($rows as $r) $objects[] = ['NIK KARYAWAN' => $r['nik'], 'SALES' => $r['nama'], 'POSISI' => $r['role'], 'REGION' => $r['region'], 'NIK ATASAN' => $r['atasan']];
        PortalCore::normalize(['sales' => $objects, 'customers' => table_('Master_Customer'), 'products' => table_('Master_Product')], settings_());
        checkpoint_($u['nik'], 'Tambah user massal ' . count($rows));
        try {
            $heads = $grids['Master_Sales'][0];
            putTable_('Master_Sales', $heads, array_map(fn($r) => array_map(fn($h) => $r[$h] ?? '', $heads), $objects));
            $access = []; foreach (optionalTable_('Admin_Access') as $r) $access[] = [$r['NIK'], $r['WILAYAH']];
            foreach ($rows as $r) if ($r['role'] === 'ADMIN') foreach ($r['access'] as $a) $access[] = [$r['nik'], $a];
            putTable_('Admin_Access', ['NIK', 'WILAYAH'], $access);
            $result = [];
            foreach ($rows as $r) { $pin = randomPin_(); setPin_($r['nik'], $pin, true); $result[] = ['nik' => $r['nik'], 'nama' => $r['nama'], 'role' => $r['role'], 'region' => $r['region'], 'pin' => $pin]; }
            $meta = meta_(); validateConfigChange_($db, $meta);
            publish_($meta, $db['raw'], $db['coverage'], $u['nik'], 'USER_BULK ' . count($rows));
            prop_delete_('USER_STAGE_' . $id);
            audit_($u['nik'], 'USER_BULK', (string) count($rows));
            return ['created' => count($rows), 'skipped' => count($all) - count($rows), 'rows' => $result, 'download' => $u['role'] === 'SUPER ADMIN'];
        } catch (Throwable $e) { foreach ($grids as $n => $g) restoreGrid_($n, $g); throw $e; }
    });
}

// ------------------------------------------------------------------
// AccountStatus.gs
// ------------------------------------------------------------------
function statusBulkPlan_(array $parsed, array $db, array $u): array {
    $heads = array_map(fn($h) => preg_replace('/\s+/u', ' ', js_upper(text_($h))), $parsed['heads']);
    $ix = array_search('NIK', $heads, true); $is = array_search('STATUS AKUN', $heads, true); $is2 = array_search('STATUS', $heads, true);
    $hn = $ix === false ? null : $parsed['heads'][$ix];
    $hs = $is !== false && truthy($parsed['heads'][$is]) ? $parsed['heads'][$is] : ($is2 === false ? null : $parsed['heads'][$is2]);
    if (!truthy($hn) || !truthy($hs)) fail_('Kolom wajib NIK dan STATUS AKUN. Pakai file dari tombol Export daftar user.');
    if (count($parsed['rows']) > 1000) fail_('Maksimal 1.000 baris per file.');
    $auth = []; foreach (table_('_Auth') as $a) { $k = text_($a['NIK'] ?? null); if ($k !== '' && !isset($auth[$k])) $auth[$k] = $a; }
    $supers = [];
    foreach ($db['meta']['people'] as $p) if ($p['role'] === 'SUPER ADMIN' && isset($auth[$p['nik']]) && PortalCore::active($auth[$p['nik']]['AKTIF'] ?? null)) $supers[] = $p['nik'];
    $seen = []; $changes = []; $rejected = []; $same = 0;
    foreach ($parsed['rows'] as $r) {
        $nik = text_($r[$hn] ?? null); $st = js_upper(text_($r[$hs] ?? null));
        $rej = function ($a, $s) use (&$rejected, $r) { $rejected[] = ['row' => $r, 'alasan' => $a, 'solusi' => $s]; };
        if ($nik === '' && $st === '') continue;
        if ($nik === '') { $rej('NIK kosong.', 'Isi NIK.'); continue; }
        if (isset($seen[$nik])) { $rej('NIK ' . $nik . ' dobel di file.', 'Sisakan satu baris.'); continue; }
        $seen[$nik] = 1;
        if (!in_array($st, ['AKTIF', 'NONAKTIF'], true)) { $rej('STATUS AKUN "' . js_str($r[$hs] ?? null) . '" tidak dikenal.', 'Isi AKTIF atau NONAKTIF.'); continue; }
        $p = null; foreach ($db['meta']['people'] as $x) if ($x['nik'] === $nik) { $p = $x; break; }
        if (!$p) { $rej('NIK ' . $nik . ' tidak terdaftar.', 'Daftarkan dulu lewat Tambah user massal.'); continue; }
        if (!canManageUser_($u, $p)) { $rej('NIK ' . $nik . ' di luar wilayah tugas Anda.', 'Minta Super Admin mengubahnya.'); continue; }
        $a = $auth[$nik] ?? null; $now = (bool) ($a && PortalCore::active($a['AKTIF'] ?? null)); $want = $st === 'AKTIF';
        if ($now === $want && (!$want || ($a && truthy($a['PIN_HASH'] ?? null)))) { $same++; continue; }
        if ($nik === $u['nik'] && !$want) { $rej('Tidak bisa menonaktifkan akun sendiri.', 'Hapus baris ini.'); continue; }
        if (!$want && $p['role'] === 'SUPER ADMIN' && count(array_filter($supers, fn($x) => $x !== $nik)) === 0) { $rej('Super Admin aktif terakhir harus dipertahankan.', 'Hapus baris ini.'); continue; }
        if (!$want) $supers = array_values(array_filter($supers, fn($x) => $x !== $nik));
        if (preg_match('/^VAC/i', $nik) && $want) { $rej('NIK VACANT tidak boleh diaktifkan (tidak untuk login).', 'Biarkan NONAKTIF / tanpa PIN.'); continue; }
        $changes[] = ['nik' => $nik, 'nama' => $p['nama'], 'role' => $p['role'], 'from' => $now ? 'AKTIF' : 'NONAKTIF', 'to' => $st, 'newPin' => $want && !($a && truthy($a['PIN_HASH'] ?? null))];
    }
    return ['changes' => $changes, 'rejected' => $rejected, 'same' => $same, 'heads' => $parsed['heads']];
}

function apiStatusBulkStage($form) {
    $form = is_array($form) ? $form : [];
    $db = snapshot_(); $u = adminGuard_($form['token'] ?? null, $db); $parsed = bulkReadRows_($form['file'] ?? null, ''); $plan = statusBulkPlan_($parsed, $db, $u); $id = uuid_();
    $name = ($form['file'] ?? null) && method_exists($form['file'], 'getName') ? $form['file']->getName() : 'status.xlsx';
    prop_set_('STATUS_STAGE_' . $id, writeJson_('status-stage-' . $id, ['actor' => $u['nik'], 'created' => now_ms(), 'plan' => $plan, 'name' => $name]));
    return ['id' => $id, 'same' => $plan['same'], 'changes' => $plan['changes'], 'rejected' => count($plan['rejected']),
        'rejectedSample' => array_map(fn($x) => ['nik' => text_($x['row']['NIK'] ?? null), 'alasan' => $x['alasan'], 'solusi' => $x['solusi']], array_slice($plan['rejected'], 0, 50))];
}
function statusStageLoad_($token, $id): array {
    $db = snapshot_(); $u = adminGuard_($token, $db); $ptr = prop_get_('STATUS_STAGE_' . js_str($id));
    if (!$ptr) fail_('Pratinjau tidak tersedia. Periksa file lagi.');
    $s = readJson_($ptr); if ($s['actor'] !== $u['nik'] || now_ms() - $s['created'] > 3600000) fail_('Pratinjau kedaluwarsa. Periksa file lagi.');
    return ['s' => $s, 'u' => $u, 'db' => $db];
}
function apiStatusBulkRejected($token, $id) {
    $L = statusStageLoad_($token, $id);
    if (empty($L['s']['plan']['rejected'])) fail_('Tidak ada baris ditolak.');
    return rejectBase64_($L['s']['name'], $L['s']['plan']['heads'], $L['s']['plan']['rejected']);
}
function apiStatusBulkCommit($token, $id, $reason = null) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function () use ($token, $id, $reason) {
        $L = statusStageLoad_($token, $id); $ch = $L['s']['plan']['changes']; if (!$ch) fail_('Tidak ada perubahan.');
        checkpoint_($L['u']['nik'], 'Update status akun massal ' . count($ch) . ' · ' . $reason);
        $pins = [];
        foreach ($ch as $c) if (!empty($c['newPin'])) { $pin = randomPin_(); setPin_($c['nik'], $pin, true); $pins[] = ['nik' => $c['nik'], 'nama' => $c['nama'], 'role' => $c['role'], 'pin' => $pin]; }
        foreach ($ch as $c) {
            if (!empty($c['newPin'])) continue;
            $a = account_($c['nik']); if (!$a) continue;
            $a['AKTIF'] = $c['to'] === 'AKTIF'; $a['VERSI'] = (int) PortalCore::number(js_or($a['VERSI'], 0)) + 1;
            writeAccount_($a);
        }
        $n1 = count(array_filter($ch, fn($c) => $c['to'] === 'AKTIF')); $n2 = count(array_filter($ch, fn($c) => $c['to'] === 'NONAKTIF'));
        audit_($L['u']['nik'], 'ACCOUNT_STATUS_BULK', count($ch) . ' akun (' . $n1 . ' aktif / ' . $n2 . ' nonaktif) · ' . $reason);
        prop_delete_('STATUS_STAGE_' . $id);
        $super = $L['u']['role'] === 'SUPER ADMIN';
        return ['updated' => count($ch), 'pins' => $super ? $pins : array_map(fn($p) => ['nik' => $p['nik'], 'nama' => $p['nama'], 'role' => $p['role'], 'pin' => '(hanya Super Admin)'], $pins)];
    });
}

// ------------------------------------------------------------------
// SyncStatus.gs — DSM tidak memakai folder Google Drive. Panel "Status Sync" menampilkan
// riwayat upload/perubahan data terakhir dari Audit_Log (upload hanya lewat halaman Admin).
// ------------------------------------------------------------------
const SYNC_AUDIT_ACTIONS = ['PUBLISH', 'USER_BULK', 'ACCOUNT_STATUS_BULK', 'CUSTOMER_BULK', 'MASTER_BULK', 'DEALER_UPLOAD', 'DEALER_TRANSFER', 'INDUK_GANTI', 'DEALER_HAPUS', 'DEALER_REGION', 'PERIOD_LOCK'];
function apiSyncStatus($token) {
    $db = snapshot_(); guard_($token, true, false, $db);
    $log = [];
    if (sheet_exists_('Audit_Log')) {
        foreach (array_reverse(sheet_tail_('Audit_Log', 3000)) as $r) {
            $v = $r['values'];
            if (!in_array(text_($v[2] ?? ''), SYNC_AUDIT_ACTIONS, true)) continue;
            $log[] = ['WAKTU' => text_($v[0] ?? ''), 'OLEH' => text_($v[1] ?? ''), 'AKSI' => text_($v[2] ?? ''), 'KETERANGAN' => text_($v[3] ?? '')];
            if (count($log) >= 40) break;
        }
    }
    return ['configured' => false, 'masuk' => null, 'selesai' => null, 'gagal' => null, 'folderUrl' => '', 'schedule' => false, 'failed' => [], 'log' => $log];
}
