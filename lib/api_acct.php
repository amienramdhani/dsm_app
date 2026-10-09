<?php
/** AccountMerge.gs — Gabung akun & Hapus akun (Super Admin). */
require_once __DIR__ . '/api_admin2.php';

const ACCT_SHEETS = [
    ['sheet' => 'Master_Sales', 'col' => 'NIK ATASAN', 'what' => 'bawahan'],
    ['sheet' => 'Master_Customer', 'col' => 'NIK SALES', 'what' => 'baris dealer'],
    ['sheet' => 'Target_Input', 'col' => 'NIK SALES', 'what' => 'baris target'],
    ['sheet' => 'Target_Periode', 'col' => 'NIK', 'what' => 'target khusus KPI'],
    ['sheet' => 'Admin_Access', 'col' => 'NIK', 'what' => 'akses admin'],
    ['sheet' => 'Export_Access', 'col' => 'NIK', 'what' => 'hak export'],
    ['sheet' => 'Dealer_Brand_Assignment', 'col' => 'NIK SALES', 'what' => 'assignment PIC dealer-brand'],
];
function acctSuper_($token): array {
    $db = snapshot_(); $u = guard_($token, true, false, $db);
    if ($u['role'] !== 'SUPER ADMIN') fail_('Khusus Super Admin.');
    return ['db' => $db, 'u' => $u];
}
function acctTxCount_(array $db, string $nik): int {
    $n = 0;
    try { foreach (js_keys($db['cubes'] ?? []) as $p) $n += count(js_filter(PortalCore::slice($db, (string) $p, [$nik]), fn($t) => $t['nik'] === $nik)); } catch (Throwable $e) {}
    return $n;
}
function acctUsage_(array $db, string $nik): array {
    $out = ['refs' => [], 'total' => 0, 'tx' => acctTxCount_($db, $nik), 'roles' => []];
    foreach (ACCT_SHEETS as $c) {
        $g = readGrid_($c['sheet']); if (!$g) continue;
        $h = array_map('text_', $g[0]); $i = array_search($c['col'], $h, true); if ($i === false) continue;
        $n = count(array_filter(array_slice($g, 1), fn($r) => text_($r[$i] ?? null) === $nik));
        if ($n) { $out['refs'][] = ['sheet' => $c['sheet'], 'col' => $c['col'], 'what' => $c['what'], 'count' => $n]; $out['total'] += $n; }
    }
    $ms = readGrid_('Master_Sales'); $h = array_map('text_', $ms[0]); $ni = array_search('NIK KARYAWAN', $h, true); $pi = array_search('POSISI', $h, true);
    foreach (array_slice($ms, 1) as $r) if (text_($r[$ni] ?? null) === $nik) $out['roles'][] = PortalCore::position($r[$pi] ?? null, 2);
    return $out;
}
function apiAccountMergePreview($token, $keep, $drop) {
    $c = acctSuper_($token); $db = $c['db']; $keep = text_($keep); $drop = text_($drop);
    if ($keep === '' || $drop === '' || $keep === $drop) fail_('Pilih dua akun yang berbeda.');
    $pk = PortalCore::person($db['meta']['people'], $keep); $pd = PortalCore::person($db['meta']['people'], $drop);
    $ms = readGrid_('Master_Sales'); $h = array_map('text_', $ms[0]); $ni = array_search('NIK KARYAWAN', $h, true); $nm = array_search('SALES', $h, true);
    $inSheet = fn($n) => js_some(array_slice($ms, 1), fn($r) => text_($r[$ni] ?? null) === $n);
    if (!$pk && !$inSheet($keep)) fail_('Akun yang dipertahankan (' . $keep . ') tidak ditemukan.');
    $u = acctUsage_($db, $drop); $block = [];
    if ($drop === $c['u']['nik']) $block[] = 'Tidak bisa menggabungkan akun yang sedang dipakai login.';
    $dealerRef = js_find($u['refs'], fn($r) => $r['sheet'] === 'Master_Customer');
    if ($dealerRef && !($pk && truthy($pk['sales'] ?? false))) $block[] = 'Akun ' . $drop . ' memegang ' . $dealerRef['count'] . ' dealer, tapi ' . $keep . ' bukan Sales. Transfer dealernya dulu atau pilih akun Sales.';
    $tg = readGrid_('Target_Input');
    if ($tg) {
        $th = array_map('text_', $tg[0]); $tp = array_search('PERIODE TARGET', $th, true); $tn = array_search('NIK SALES', $th, true); $pk2 = [];
        foreach (array_slice($tg, 1) as $r) if (text_($r[$tn] ?? null) === $keep) $pk2[text_($r[$tp] ?? null)] = 1;
        $clash = [];
        foreach (array_slice($tg, 1) as $r) if (text_($r[$tn] ?? null) === $drop && isset($pk2[text_($r[$tp] ?? null)])) $clash[] = text_($r[$tp] ?? null);
        if ($clash) $block[] = 'Target bulan ' . implode(', ', $clash) . ' ada di dua akun. Hapus salah satu di Target dulu.';
    }
    $dropName = '';
    if ($pd) $dropName = $pd['nama'];
    else foreach (array_slice($ms, 1) as $r) if (text_($r[$ni] ?? null) === $drop) { $dropName = $r[$nm] ?? ''; break; }
    return ['keep' => $keep, 'keepName' => $pk ? $pk['nama'] : '', 'drop' => $drop, 'dropName' => text_($dropName), 'refs' => $u['refs'], 'roles' => $u['roles'], 'tx' => $u['tx'], 'block' => $block];
}
/** acctRewrite_: fn(h, &r) mengembalikan false untuk membuang baris. */
function acctRewrite_(string $sheet, callable $fn): ?array {
    $g = readGrid_($sheet); if (!$g) return null;
    $h = array_map('text_', $g[0]); $out = [];
    foreach (array_slice($g, 1) as $r) { $r = array_values($r); $x = $fn($h, $r); if ($x !== false) $out[] = $r; }
    putTable_($sheet, $g[0], $out);
    return $g;
}
function nikMergeAppend_($lama, $baru, $actor, $reason): void {
    $g = readGrid_(NIK_MERGE_LOG); $rows = $g ? array_slice($g, 1) : [];
    $rows[] = [(new DateTimeImmutable('now', jkt_tz_()))->format('Y-m-d H:i'), $lama, $baru, $actor, $reason];
    putTable_(NIK_MERGE_LOG, ['WAKTU', 'NIK LAMA', 'NIK BARU', 'OLEH', 'ALASAN'], $rows);
}
function apiAccountMergeCommit($token, $keep, $drop, $reason = null) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function () use ($token, $keep, $drop, $reason) {
        $pv = apiAccountMergePreview($token, $keep, $drop); if ($pv['block']) fail_(implode(' ', $pv['block']));
        $c = acctSuper_($token); $db = $c['db']; $before = [];
        checkpoint_($c['u']['nik'], 'Gabung akun ' . $drop . ' → ' . $keep . ' · ' . $reason);
        try {
            foreach (['Master_Sales', 'Master_Customer', 'Target_Input', 'Target_Periode', 'Admin_Access', 'Export_Access', 'Dealer_Brand_Assignment', '_Auth', NIK_MERGE_LOG] as $s) $before[$s] = readGrid_($s);
            $drp = $pv['drop']; $kp = $pv['keep'];
            acctRewrite_('Master_Sales', function ($h, &$r) use ($drp, $kp) { $ni = array_search('NIK KARYAWAN', $h, true); $ai = array_search('NIK ATASAN', $h, true); if (text_($r[$ni] ?? null) === $drp) return false; if (text_($r[$ai] ?? null) === $drp) $r[$ai] = $kp; });
            acctRewrite_('Master_Customer', function ($h, &$r) use ($drp, $kp) { $i = array_search('NIK SALES', $h, true); if ($i !== false && text_($r[$i] ?? null) === $drp) $r[$i] = $kp; });
            acctRewrite_('Target_Input', function ($h, &$r) use ($drp, $kp, $pv) { $i = array_search('NIK SALES', $h, true); if ($i !== false && text_($r[$i] ?? null) === $drp) { $r[$i] = $kp; $n = array_search('NAMA SALES', $h, true); if ($n !== false) $r[$n] = $pv['keepName']; } });
            acctRewrite_('Target_Periode', function ($h, &$r) use ($drp, $kp) { $i = array_search('NIK', $h, true); if ($i !== false && text_($r[$i] ?? null) === $drp) $r[$i] = $kp; });
            $seenAcc = [];
            acctRewrite_('Admin_Access', function ($h, &$r) use ($drp, $kp, &$seenAcc) { $i = array_search('NIK', $h, true); $w = array_search('WILAYAH', $h, true); if (text_($r[$i] ?? null) === $drp) $r[$i] = $kp; $k = text_($r[$i] ?? null) . '|' . text_($r[$w] ?? null); if (isset($seenAcc[$k])) return false; $seenAcc[$k] = 1; });
            acctRewrite_('Export_Access', function ($h, &$r) use ($drp) { $i = array_search('NIK', $h, true); if ($i !== false && text_($r[$i] ?? null) === $drp) return false; });
            acctRewrite_('Dealer_Brand_Assignment', function ($h, &$r) use ($drp, $kp) { $i = array_search('NIK SALES', $h, true); if ($i !== false && text_($r[$i] ?? null) === $drp) $r[$i] = $kp; });
            acctRewrite_('_Auth', function ($h, &$r) use ($drp) { $i = array_search('NIK', $h, true); if ($i !== false && text_($r[$i] ?? null) === $drp) return false; });
            nikMergeAppend_($drp, $kp, $c['u']['nik'], $reason);
            $meta = meta_(); validateConfigChange_($db, $meta);
            publish_($meta, $db['raw'], $db['coverage'], $c['u']['nik'], 'GABUNG AKUN ' . $drp . ' → ' . $kp . ' · ' . $reason);
        } catch (Throwable $e) {
            foreach ($before as $s => $g) { if ($g) restoreGrid_($s, $g); elseif ($s === NIK_MERGE_LOG) deleteSheet_($s); }
            throw $e;
        }
        tempPinDrop_($pv['drop']);
        audit_($c['u']['nik'], 'ACCOUNT_MERGE', $pv['drop'] . ' → ' . $pv['keep'] . ' · ' . implode(', ', array_map(fn($r) => $r['count'] . ' ' . $r['what'], $pv['refs'])) . ' · ' . $reason);
        return ['ok' => true, 'moved' => $pv['refs']];
    });
}
function apiAccountDeleteCheck($token, $nik) {
    $c = acctSuper_($token); $db = $c['db']; $nik = text_($nik);
    $u = acctUsage_($db, $nik); $block = [];
    if ($nik === $c['u']['nik']) $block[] = 'Tidak bisa menghapus akun sendiri.';
    $p = PortalCore::person($db['meta']['people'], $nik);
    if ($p && $p['role'] === 'SUPER ADMIN' && count(js_filter($db['meta']['people'], fn($x) => $x['role'] === 'SUPER ADMIN')) < 2) $block[] = 'Super Admin terakhir tidak bisa dihapus.';
    foreach (js_filter($u['refs'], fn($r) => $r['sheet'] !== 'Admin_Access' && $r['sheet'] !== 'Export_Access') as $r) $block[] = 'Masih punya ' . $r['count'] . ' ' . $r['what'] . ' (' . $r['sheet'] . ').';
    if ($u['tx']) $block[] = 'Punya ' . $u['tx'] . ' transaksi historis — nonaktifkan saja, jangan dihapus.';
    return ['nik' => $nik, 'nama' => $p ? $p['nama'] : '', 'refs' => $u['refs'], 'tx' => $u['tx'], 'block' => $block];
}
function apiAccountDelete($token, $nik, $reason = null) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function () use ($token, $nik, $reason) {
        $ck = apiAccountDeleteCheck($token, $nik); if ($ck['block']) fail_(implode(' ', $ck['block']));
        $c = acctSuper_($token); $db = $c['db']; $before = []; $n = $ck['nik'];
        checkpoint_($c['u']['nik'], 'Hapus akun ' . $n . ' · ' . $reason);
        try {
            foreach (['Master_Sales', 'Admin_Access', 'Export_Access', '_Auth'] as $s) $before[$s] = readGrid_($s);
            acctRewrite_('Master_Sales', function ($h, &$r) use ($n) { if (text_($r[array_search('NIK KARYAWAN', $h, true)] ?? null) === $n) return false; });
            foreach (['Admin_Access', 'Export_Access', '_Auth'] as $s) acctRewrite_($s, function ($h, &$r) use ($n) { $i = array_search('NIK', $h, true); if ($i !== false && text_($r[$i] ?? null) === $n) return false; });
            $meta = meta_(); validateConfigChange_($db, $meta);
            publish_($meta, $db['raw'], $db['coverage'], $c['u']['nik'], 'HAPUS AKUN ' . $n . ' · ' . $reason);
        } catch (Throwable $e) { foreach ($before as $s => $g) if ($g) restoreGrid_($s, $g); throw $e; }
        tempPinDrop_($n);
        audit_($c['u']['nik'], 'ACCOUNT_DELETE', $n . ' ' . $ck['nama'] . ' · ' . $reason);
        return ['ok' => true];
    });
}
