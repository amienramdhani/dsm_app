<?php
/** Activity.gs — Riwayat aktivitas, daftar checkpoint, Kembalikan checkpoint (Super Admin). */
require_once __DIR__ . '/users_bulk.php';

const ACT_LABEL = [
    'CHECKPOINT' => 'Checkpoint', 'ACCOUNT_MERGE' => 'Gabung akun', 'ACCOUNT_DELETE' => 'Hapus akun', 'DEALER_TRANSFER' => 'Transfer dealer', 'DEALER_REGION' => 'Big Region dealer',
    'CUSTOMER_BULK' => 'Bulk edit dealer', 'DEALER_BRAND_ASSIGN' => 'Set PIC dealer-brand', 'DEALER_BRAND_TRANSFER' => 'Transfer PIC dealer-brand', 'DEALER_BRAND_CUTOVER' => 'Ubah cutover PIC', 'REGION_MAP_EDIT' => 'Edit Region Map', 'KPI_ADMIN' => 'KPI / target', 'CHECKPOINT_RESTORE' => 'Kembalikan checkpoint', 'LOGIN' => 'Login', 'PUBLISH' => 'Publish data',
    'HARI_LIBUR_SIMPAN' => 'Simpan hari libur', 'HARI_LIBUR_HAPUS' => 'Hapus hari libur', 'DETAIL_KPI' => 'Buka detail KPI',
];

function apiActivity($token, $q = null, $action = null) {
    $db = snapshot_(); $u = guard_($token, true, false, $db);
    if ($u['role'] !== 'SUPER ADMIN' && $u['role'] !== 'ADMIN') fail_('Khusus admin.');
    $names = []; foreach ($db['meta']['people'] as $p) $names[(string) $p['nik']] = $p['nama'];
    $q = js_upper(text_($q)); $action = text_($action);
    $rows = array_values(array_filter(actRows_(), fn($r) => $r['action'] !== 'CHECKPOINT'));
    if ($u['role'] !== 'SUPER ADMIN') $rows = array_values(array_filter($rows, fn($r) => $r['nik'] === $u['nik']));
    $acts = []; foreach ($rows as $r) $acts[$r['action']] = 1;
    $noisy = ['LOGIN' => 1, 'PUBLISH' => 1, 'DETAIL_KPI' => 1, 'DEMO_START' => 1];
    $rows = array_values(array_filter($rows, fn($r) => ($action !== '' ? $r['action'] === $action : !isset($noisy[$r['action']]))
        && ($q === '' || mb_strpos(js_upper($r['nik'] . ' ' . ($names[$r['nik']] ?? '') . ' ' . $r['action'] . ' ' . $r['detail']), $q) !== false)));
    $keys = array_map('strval', array_keys($acts)); sort($keys, SORT_STRING);
    return ['rows' => array_map(fn($r) => ['when' => actWhen_($r['at']), 'nik' => $r['nik'], 'nama' => $names[$r['nik']] ?? '', 'action' => $r['action'], 'label' => ACT_LABEL[$r['action']] ?? $r['action'], 'detail' => $r['detail']], array_slice($rows, 0, 300)),
        'total' => count($rows), 'actions' => array_map(fn($a) => ['id' => $a, 'label' => ACT_LABEL[$a] ?? $a], $keys)];
}

function apiCheckpoints($token) {
    $db = snapshot_(); $u = guard_($token, true, false, $db); if ($u['role'] !== 'SUPER ADMIN') fail_('Khusus Super Admin.');
    $names = []; foreach ($db['meta']['people'] as $p) $names[(string) $p['nik']] = $p['nama'];
    $out = [];
    foreach (array_slice(array_values(array_filter(actRows_(), fn($r) => $r['action'] === 'CHECKPOINT')), 0, 60) as $r) {
        if (preg_match('/\s(\S+)$/', $r['detail'], $m, PREG_OFFSET_CAPTURE)) { $file = $m[1][0]; $reason = substr($r['detail'], 0, $m[0][1]); } else { $file = ''; $reason = $r['detail']; }
        if ($file === '') continue;
        $out[] = ['when' => actWhen_($r['at']), 'nik' => $r['nik'], 'nama' => $names[$r['nik']] ?? $r['nik'], 'reason' => $reason, 'file' => $file];
    }
    return ['rows' => $out];
}

function apiCheckpointRestore($token, $file, $reason = null) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function () use ($token, $file, $reason) {
        $db = snapshot_(); $u = guard_($token, true, false, $db); if ($u['role'] !== 'SUPER ADMIN') fail_('Khusus Super Admin.');
        $cp = null; try { $cp = readJson_(js_str($file)); } catch (Throwable $e) {}
        if (!$cp || empty($cp['sheets'])) fail_('File checkpoint tidak terbaca.');
        $names = array_map('strval', array_keys($cp['sheets'])); $before = [];
        foreach (array_merge($names, ['_Auth']) as $s) $before[$s] = readGrid_($s);
        checkpoint_($u['nik'], 'Sebelum kembalikan checkpoint ' . js_str($cp['at'] ?? '') . ' · ' . $reason);
        try {
            foreach ($names as $s) {
                $g = $cp['sheets'][$s] ?? null; if (!$g) continue;
                if ($s === '_Auth') {
                    $cur = readGrid_('_Auth'); if (!$cur) { restoreGrid_($s, $g); continue; }
                    $h = array_map('text_', $cur[0]); $ni = array_search('NIK', $h, true); $have = [];
                    foreach (array_slice($cur, 1) as $r) $have[text_($r[$ni] ?? null)] = 1;
                    $oh = array_map('text_', $g[0]); $oni = array_search('NIK', $oh, true); $add = [];
                    foreach (array_slice($g, 1) as $r) {
                        if (isset($have[text_($r[$oni] ?? null)])) continue;
                        $add[] = array_map(function ($k) use ($oh, $r) { $j = array_search($k, $oh, true); return $j === false ? '' : ($r[$j] ?? ''); }, $h);
                    }
                    if ($add) putTable_('_Auth', $cur[0], array_merge(array_slice($cur, 1), $add));
                    continue;
                }
                restoreGrid_($s, $g);
            }
            $meta = meta_();
            publish_($meta, $db['raw'], $db['coverage'], $u['nik'], 'KEMBALIKAN CHECKPOINT ' . js_str($cp['reason'] ?? '') . ' · ' . $reason);
        } catch (Throwable $e) { foreach ($before as $s => $g) if ($g) restoreGrid_($s, $g); throw $e; }
        audit_($u['nik'], 'CHECKPOINT_RESTORE', js_str($cp['reason'] ?? '') . ' (' . js_str($cp['at'] ?? '') . ') · ' . $reason);
        return ['ok' => true, 'sheets' => count($names)];
    });
}
