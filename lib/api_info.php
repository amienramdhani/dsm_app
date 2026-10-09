<?php
/** Info.gs — Informasi / pengumuman + lampiran (disimpan privat di storage/info). */
require_once __DIR__ . '/common.php';

const INFO_HEADS = ['ID', 'JUDUL', 'ISI', 'WILAYAH', 'FILE_ID', 'FILE_NAME', 'MIME', 'BYTES', 'PENULIS_NIK', 'PENULIS_NAMA', 'DIBUAT', 'AKTIF', 'WILAYAH_LIST', 'ROLE_LIST'];
const INFO_TYPES = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];

function infoRows_(): array { return sheet_exists_('Informasi') ? table_('Informasi') : []; }
function infoList_($value, $fallback): array {
    if (!truthy($value)) return $fallback;
    if (is_array($value)) $a = $value; else { $a = json_decode(js_str($value), true); if (json_last_error() !== JSON_ERROR_NONE) return []; }
    return (is_array($a) && array_is_list($a)) ? $a : [];
}
function infoRowRegions_($row): array { return infoList_($row['WILAYAH_LIST'] ?? null, [$row['WILAYAH'] ?? null]); }
function infoManage_($user, $region): bool { return $user['role'] === 'SUPER ADMIN' || ($user['role'] === 'RGM' && truthy($user['wilayah'] ?? null) && $region === $user['wilayah']); }
function infoRowManage_($u, $row): bool {
    if ($u['role'] === 'SUPER ADMIN') return true;
    if ($u['role'] !== 'RGM') return false;
    $r = infoRowRegions_($row); if (!$r) return false;
    foreach ($r as $x) if (!infoManage_($u, $x)) return false;
    return true;
}
function infoVisible_($user, $row): bool {
    if ($user['role'] === 'SUPER ADMIN' || (infoRowManage_($user, $row) && js_str($row['PENULIS_NIK'] ?? null) === $user['nik'])) return true;
    $roles = infoList_($row['ROLE_LIST'] ?? null, ['ALL']); $regions = infoRowRegions_($row);
    $mine = $user['role'] === 'ADMIN' ? ($user['adminRegions'] ?? []) : [$user['wilayah'] ?? null];
    if (!(in_array('ALL', $roles, true) || in_array($user['role'], $roles, true))) return false;
    if (in_array('NASIONAL', $regions, true) || PortalCore::LEVEL[$user['role']] >= 4) return true;
    foreach ($regions as $r) if (in_array($r, $mine, true)) return true;
    return false;
}
function infoRegions_(array $db): array {
    $o = []; foreach ($db['meta']['people'] as $p) if (truthy($p['wilayah'] ?? null)) $o[$p['wilayah']] = 1;
    $k = array_map('strval', js_keys($o)); sort($k, SORT_STRING); return $k;
}
function infoAudience_($form, $user): array {
    $regions = infoList_($form['regions'] ?? null, [text_($form['region'] ?? null)]); $roles = infoList_($form['roles'] ?? null, ['ALL']); $known = infoRegions_(snapshot_());
    $bad = !$regions;
    foreach ($regions as $r) if (!in_array($r, array_merge(['NASIONAL'], $known), true) || !infoManage_($user, $r)) $bad = true;
    if ($bad) fail_('Unggah hanya untuk wilayah penerima dalam kewenangan.');
    $badR = !$roles;
    foreach ($roles as $r) if ($r !== 'ALL' && !isset(PortalCore::LEVEL[(string) $r])) $badR = true;
    if ($badR) fail_('Pilih role penerima yang sah.');
    return ['regions' => array_values(array_unique($regions, SORT_REGULAR)), 'roles' => in_array('ALL', $roles, true) ? ['ALL'] : array_values(array_unique($roles, SORT_REGULAR))];
}
function infoFind_($user, $id, bool $includeInactive) {
    $row = js_find(infoRows_(), fn($r) => js_str($r['ID'] ?? null) === js_str($id));
    if (!$row || !infoVisible_($user, $row) || (!PortalCore::active($row['AKTIF'] ?? null) && !($includeInactive && infoRowManage_($user, $row)))) fail_('Informasi tidak tersedia untuk akun ini.');
    return $row;
}
function apiInfo($token, $page = null, $query = null, $region = null) {
    $db = snapshot_(); $user = guard_($token, false, false, $db);
    $pn = js_number_($page); $requested = max(0, (int) floor(is_nan((float) $pn) || !$pn ? 0 : $pn)); $q = js_lower(js_slice(js_str(js_or($query, '')), 0, 100));
    $all = array_values(array_filter(infoRows_(), function ($r) use ($user, $region, $q) {
        return infoVisible_($user, $r) && (PortalCore::active($r['AKTIF'] ?? null) || infoRowManage_($user, $r)) && (!truthy($region) || $region === 'ALL' || in_array($region, infoRowRegions_($r), true))
            && str_contains(js_lower(js_str($r['JUDUL'] ?? null) . ' ' . js_str($r['ISI'] ?? null)), $q);
    }));
    $all = js_sort($all, fn($a, $b) => js_locale_compare(js_str($b['DIBUAT'] ?? null), js_str($a['DIBUAT'] ?? null)) ?: js_locale_compare(js_str($a['ID'] ?? null), js_str($b['ID'] ?? null)));
    $n = min($requested, max(0, (int) ceil(count($all) / 20) - 1)); $regions = infoRegions_($db);
    $visible = PortalCore::LEVEL[$user['role']] >= 4 ? array_merge(['NASIONAL'], $regions) : array_values(array_filter(['NASIONAL', $user['wilayah'] ?? null], fn($x) => truthy($x)));
    $demo = !empty($user['demo']);
    return ['canUpload' => !$demo && ($user['role'] === 'SUPER ADMIN' || $user['role'] === 'RGM'), 'uploadRegions' => $demo ? [] : ($user['role'] === 'SUPER ADMIN' ? array_merge(['NASIONAL'], $regions) : ($user['role'] === 'RGM' ? [$user['wilayah'] ?? null] : [])),
        'regions' => $visible, 'total' => count($all), 'page' => $n,
        'rows' => array_map(fn($r) => ['id' => $r['ID'], 'title' => $r['JUDUL'], 'body' => $r['ISI'], 'region' => implode(', ', infoRowRegions_($r)), 'roles' => infoList_($r['ROLE_LIST'] ?? null, ['ALL']), 'author' => $r['PENULIS_NAMA'], 'created' => $r['DIBUAT'], 'active' => PortalCore::active($r['AKTIF'] ?? null), 'fileName' => $r['FILE_NAME'], 'size' => kn_or(js_number_($r['BYTES'] ?? null), 0), 'hasFile' => truthy($r['FILE_ID'] ?? null), 'canManage' => !$demo && infoRowManage_($user, $r)], array_slice($all, $n * 20, 20))];
}
function infoDir_(): string { return storage_dir_('info'); }
function infoFile_(string $id): string { if (!preg_match('/^[A-Za-z0-9_-]{8,80}$/', $id)) fail_('Lampiran tidak tersedia secara privat. Hubungi admin.'); return infoDir_() . '/' . $id . '.bin'; }
function apiUploadInfo($form) {
    $tk = $form['token'] ?? null; demoAssertWritable_($tk); $user = guard_($tk); $audience = infoAudience_($form, $user); $region = $audience['regions'][0];
    $title = text_($form['title'] ?? null); $body = text_($form['body'] ?? null);
    if (!infoManage_($user, $region)) fail_('Unggah hanya untuk wilayah yang Anda kelola.');
    if (!in_array($region, array_merge(['NASIONAL'], infoRegions_(snapshot_())), true)) fail_('Wilayah tidak dikenal.');
    if ($title === '' || mb_strlen($title) > 140 || mb_strlen($body) > 4000) fail_('Judul wajib, maksimal 140 karakter; isi maksimal 4.000 karakter.');
    $blob = $form['file'] ?? null; $bytes = null; $name = ''; $mime = ''; $fileId = '';
    if ($blob instanceof Blob && $blob->getName() !== '') {
        $bytes = $blob->getBytes(); $name = mb_substr(preg_replace('/[\\\\\/\r\n\x00-\x1f]/', '_', $blob->getName()), 0, 150); $parts = explode('.', $name); $ext = strtolower(end($parts)); $mime = INFO_TYPES[$ext] ?? '';
        if ($mime === '') fail_('File yang didukung: PDF, JPG, PNG, DOCX, XLSX.');
        if (strlen($bytes) === 0 || strlen($bytes) > 5 * 1024 * 1024) fail_('Lampiran harus berisi data, maksimal 5 MB.');
        $h = fn($n) => substr($bytes, 0, $n);
        $valid = $ext === 'pdf' ? $h(5) === '%PDF-' : ($ext === 'png' ? $h(8) === "\x89PNG\r\n\x1a\n" : (in_array($ext, ['jpg', 'jpeg'], true) ? $h(2) === "\xFF\xD8" : $h(2) === 'PK'));
        if (!$valid) fail_('Isi file tidak sesuai jenis file. Ekspor ulang lampiran.');
    }
    return with_lock_(function () use ($tk, $form, $bytes, $mime, $name, $region, $title, $body, $audience) {
        demoAssertWritable_($tk); $current = guard_($tk); infoAudience_($form, $current); checkpoint_($current['nik'], 'Publikasi informasi');
        $fileId = '';
        if ($bytes !== null) { $fileId = 'I' . gmdate('YmdHis') . bin2hex(random_bytes(8)); file_put_contents(infoFile_($fileId), $bytes, LOCK_EX); }
        try {
            ensureTable_('Informasi', INFO_HEADS, []);
            $id = uuid_();
            appendRow_('Informasi', [$id, $title, $body, $region, $fileId, $name, $mime, $bytes !== null ? strlen($bytes) : 0, $current['nik'], $current['nama'] ?? null, iso_now(), true, js_stringify($audience['regions']), js_stringify($audience['roles'])]);
            audit_($current['nik'], 'INFO_PUBLISH', $id . ' ' . $region);
            return ['id' => $id];
        } catch (Throwable $e) { if ($fileId !== '') @unlink(infoFile_($fileId)); throw $e; }
    });
}
function apiSetInfoActive($token, $id = null, $value = null) {
    demoAssertWritable_($token); if (!is_bool($value)) fail_('Status tidak valid.');
    return with_lock_(function () use ($token, $id, $value) {
        demoAssertWritable_($token); $user = guard_($token); $row = infoFind_($user, $id, true);
        if (!infoRowManage_($user, $row)) fail_('Tidak boleh mengelola informasi ini.');
        $rows = sheet_rows_with_id_('Informasi'); $hit = null;
        foreach ($rows as $r) if (js_str($r['values'][0] ?? null) === js_str($id)) { $hit = $r; break; }
        if (!$hit) fail_('Informasi tidak ditemukan.');
        sheet_update_row_('Informasi', $hit['_row'], [$value], 12);
        audit_($user['nik'], 'INFO_ACTIVE', js_str($id) . ' ' . ($value ? 'true' : 'false'));
        return true;
    });
}
function apiDownloadInfo($token, $id = null) {
    $user = guard_($token); $row = infoFind_($user, $id, false);
    if (!truthy($row['FILE_ID'] ?? null)) fail_('Informasi ini tidak memiliki lampiran.');
    $p = infoFile_(js_str($row['FILE_ID']));
    if (!is_file($p)) fail_('Lampiran tidak tersedia secara privat. Hubungi admin.');
    if (filesize($p) > 5 * 1024 * 1024) fail_('Lampiran melewati batas 5 MB.');
    $bytes = (string) file_get_contents($p);
    $latest = infoFind_(guard_($token), $id, false); if (js_str($latest['FILE_ID'] ?? null) !== js_str($row['FILE_ID'])) fail_('Lampiran berubah. Muat ulang.');
    audit_($user['nik'], 'INFO_DOWNLOAD', js_str($id));
    return ['name' => js_str($row['FILE_NAME']), 'mime' => js_str($row['MIME']), 'base64' => base64_encode($bytes)];
}
