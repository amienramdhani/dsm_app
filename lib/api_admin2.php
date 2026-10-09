<?php
/** Hari Libur (tulis) + RegionMap.gs. */
require_once __DIR__ . '/api_kpi.php';

function apiHariLiburSave($token, $tanggal, $ket = null, $aktif = null) {
    $u = hlGuard_($token);
    return with_lock_(function () use ($u, $tanggal, $ket, $aktif) {
        $d = PortalCore::iso($tanggal); if (!truthy($d)) fail_('Tanggal tidak valid.');
        if ((int) gmdate('w', strtotime($d . 'T00:00:00Z')) === 0) fail_('Minggu sudah otomatis libur, tidak perlu ditambahkan.');
        $ket = mb_substr(js_trim(js_str(js_or($ket, ''))), 0, 100); if (mb_strlen($ket) < 2) fail_('Keterangan wajib diisi.');
        hlSheet_(); $cur = js_find(hlRows_(), fn($x) => $x['date'] === $d); $vals = [$d, $ket, $aktif === false ? 'FALSE' : 'TRUE'];
        $g = readGrid_('Hari_Libur'); $heads = $g[0];
        $rows = array_slice($g, 1);
        if ($cur) { $rows[$cur['row'] - 2] = $vals; } else $rows[] = $vals;
        putTable_('Hari_Libur', $heads, array_values($rows));
        unset($GLOBALS['DSM_HOL']);
        audit_($u['nik'], 'HARI_LIBUR_SIMPAN', $d . ' ' . $ket);
        return ['rows' => hlRows_(), 'active' => anaHol_()];
    });
}
function apiHariLiburHapus($token, $tanggal) {
    $u = hlGuard_($token);
    return with_lock_(function () use ($u, $tanggal) {
        $d = PortalCore::iso($tanggal); $cur = js_find(hlRows_(), fn($x) => $x['date'] === $d); if (!$cur) fail_('Tanggal tidak ditemukan.');
        $g = readGrid_('Hari_Libur'); $heads = $g[0]; $rows = array_slice($g, 1);
        array_splice($rows, $cur['row'] - 2, 1);
        putTable_('Hari_Libur', $heads, array_values($rows));
        unset($GLOBALS['DSM_HOL']);
        audit_($u['nik'], 'HARI_LIBUR_HAPUS', $d);
        return ['rows' => hlRows_(), 'active' => anaHol_()];
    });
}

// ===== RegionMap.gs =====
function regionMapUsage_(array $db): array {
    $use = [];
    foreach ($db['meta']['people'] ?? [] as $p) foreach ([$p['region'] ?? null, $p['portfolio'] ?? null] as $r) { $r = js_upper(text_($r)); if ($r !== '') $use[$r] = ($use[$r] ?? 0) + 1; }
    return $use;
}
function apiRegionMapGet($token) {
    $db = snapshot_(); $u = guard_($token, true);
    if ($u['role'] !== 'SUPER ADMIN') fail_('Khusus Super Admin.');
    $grid = readGrid_('Region_Map'); if (!$grid) fail_('Sheet Region_Map tidak ditemukan.');
    $heads = array_map('text_', $grid[0]); $ri = array_search('REGION', $heads, true); $wi = array_search('WILAYAH_KPI', $heads, true); $ki = array_search('KANAL', $heads, true); $use = regionMapUsage_($db);
    $rows = [];
    foreach (array_slice($grid, 1) as $r) {
        $reg = text_($ri === false ? null : ($r[$ri] ?? null)); if ($reg === '') continue;
        $rows[] = ['region' => $reg, 'big' => text_($wi === false ? null : ($r[$wi] ?? null)), 'kanal' => $ki !== false ? js_upper(text_($r[$ki] ?? null)) : '', 'used' => $use[js_upper($reg)] ?? 0];
    }
    $bigs = [];
    foreach ($rows as $x) if ($x['big'] !== '' && !in_array($x['big'], $bigs, true)) $bigs[] = $x['big'];
    return ['rows' => $rows, 'bigs' => js_sort_default($bigs)];
}
function apiRegionMapSave($token, $input, $reason = null) {
    $reason = text_($reason);
    if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function () use ($token, $input, $reason) {
        $db = snapshot_(); $u = guard_($token, true);
        if ($u['role'] !== 'SUPER ADMIN') fail_('Khusus Super Admin.');
        if (!is_array($input) || !$input) fail_('Region Map tidak boleh kosong.');
        $input = array_values($input);
        if (count($input) > 500) fail_('Maksimal 500 region.');
        $errs = []; $seen = []; $rows = [];
        foreach ($input as $i => $x) {
            $x = is_array($x) ? $x : [];
            $reg = preg_replace('/\s+/u', ' ', text_($x['region'] ?? null)); $big = preg_replace('/\s+/u', ' ', text_($x['big'] ?? null)); $kanal = js_upper(text_($x['kanal'] ?? null));
            if ($reg === '' && $big === '') continue;
            $line = 'Baris ' . ($i + 1) . ': ';
            if ($reg === '') { $errs[] = $line . 'REGION kosong.'; continue; }
            if ($big === '') { $errs[] = $line . $reg . ' belum diisi Big Region.'; continue; }
            if (mb_strlen($reg) > 60 || mb_strlen($big) > 60) { $errs[] = $line . 'maksimal 60 karakter.'; continue; }
            if ($kanal !== '' && $kanal !== 'ONLINE') { $errs[] = $line . 'KANAL hanya boleh kosong atau ONLINE.'; continue; }
            if (isset($seen[js_upper($reg)])) { $errs[] = $line . 'REGION ' . $reg . ' dobel.'; continue; }
            $seen[js_upper($reg)] = 1; $rows[] = [$reg, $big, $kanal];
        }
        $cur = readGrid_('Region_Map') ?: [[]]; $ch = array_map('text_', $cur[0]); $cri = array_search('REGION', $ch, true); $inMap = [];
        foreach (array_slice($cur, 1) as $r) { $k = js_upper(text_($cri === false ? null : ($r[$cri] ?? null))); if ($k !== '') $inMap[$k] = 1; }
        $use = regionMapUsage_($db); $gone = [];
        foreach (js_keys($use) as $r) if (isset($inMap[(string) $r]) && !isset($seen[(string) $r])) $gone[] = (string) $r;
        if ($gone) $errs[] = 'Region masih dipakai user, tidak boleh dihapus/diganti nama: ' . implode(', ', array_map(fn($r) => $r . ' (' . $use[$r] . ' user)', $gone)) . '. Pindahkan user-nya dulu.';
        if ($errs) fail_(implode("\n", array_slice($errs, 0, 12)) . (count($errs) > 12 ? "\n… dan " . (count($errs) - 12) . ' lainnya.' : ''));
        $before = readGrid_('Region_Map'); $heads = array_map('text_', $before[0]);
        foreach (['REGION', 'WILAYAH_KPI', 'KANAL'] as $h) if (!in_array($h, $heads, true)) $heads[] = $h;
        $old = []; $oi = array_search('REGION', $heads, true);
        foreach (array_slice($before, 1) as $r) $old[js_upper(text_($r[$oi] ?? null))] = $r;
        $ri = array_search('REGION', $heads, true); $wi = array_search('WILAYAH_KPI', $heads, true); $ki = array_search('KANAL', $heads, true);
        $out = [];
        foreach ($rows as $r) {
            $base = array_values($old[js_upper($r[0])] ?? []); $o = [];
            foreach ($heads as $i => $_) $o[] = ($base[$i] ?? null) === null ? '' : $base[$i];
            $o[$ri] = $r[0]; $o[$wi] = $r[1]; $o[$ki] = $r[2]; $out[] = $o;
        }
        $added = count(array_filter($rows, fn($r) => !isset($old[js_upper($r[0])])));
        $removed = count(array_filter(array_map('strval', array_keys($old)), fn($k) => $k !== '' && !isset($seen[$k])));
        checkpoint_($u['nik'], 'Edit Region Map · ' . $reason);
        try {
            putTable_('Region_Map', $heads, $out);
            $meta = meta_(); validateConfigChange_($db, $meta);
            publish_($meta, $db['raw'], $db['coverage'], $u['nik'], 'REGION_MAP edit ' . $reason);
        } catch (Throwable $e) { restoreGrid_('Region_Map', $before); throw $e; }
        audit_($u['nik'], 'REGION_MAP_EDIT', count($rows) . ' region · +' . $added . ' / -' . $removed . ' · ' . $reason);
        return ['total' => count($rows), 'added' => $added, 'removed' => $removed];
    });
}

/* Banner admin: ringkasan cepat tanpa menjalankan cek penuh. */
function apiAdminBanner($token) {
    $db = snapshot_(); $u = guard_($token, true, false, $db); $out = ['items' => []];
    $sup = $u['role'] === 'SUPER ADMIN'; $now = jkt_date('Y-m');
    $add = function ($lv, $text, $go = '') use (&$out) { $out['items'][] = ['lv' => $lv, 'text' => $text, 'go' => $go]; };
    try {
        $raw = prop_get_('V6_HEALTH_SUM'); $h = $raw ? json_decode($raw, true) : null;
        if ($h && (($h['at'] ?? 0) + 21600000) > (int) (microtime(true) * 1000) && (!empty($h['red']) || !empty($h['orange']))) {
            $add(!empty($h['red']) ? 'r' : 'o', 'Cek Kesehatan Master terakhir: ' . (!empty($h['red']) ? $h['red'] . ' merah' : '') . ((!empty($h['red']) && !empty($h['orange'])) ? ' · ' : '') . (!empty($h['orange']) ? $h['orange'] . ' oranye' : ''), 'kesehatan');
        }
    } catch (Throwable $e) {}
    $cuts = []; foreach (js_keys($db['coverage'] ?? []) as $k) { $c = $db['coverage'][$k]['cutoff'] ?? null; if (truthy($c)) $cuts[] = $c; }
    sort($cuts, SORT_STRING); $last = $cuts ? end($cuts) : null;
    if ($last) {
        $days = (int) floor(((int) (microtime(true) * 1000) - strtotime($last . 'T00:00:00+07:00') * 1000) / 86400000);
        if ($days >= 3) $add($days >= 7 ? 'r' : 'o', 'Data ST terakhir s.d. ' . $last . ' (' . $days . ' hari lalu)', 'status sync');
    }
    try {
        $sb = securityBanner_($u, $db);
        if ($sb['locked']) $add('r', $sb['locked'] . ' akun terkunci karena gagal login', 'keamanan akun');
        if ($sb['off']) $add('o', $sb['off'] . ' akun ASM ke atas belum mengaktifkan Authenticator', 'keamanan akun');
    } catch (Throwable $e) {}
    // Peringatan "KPI/target belum diisi" dihapus atas permintaan: target kosong dianggap memang belum ada.
    return $out;
}
