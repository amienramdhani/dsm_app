<?php
/** Setting Sub Region: ASM & RGM per sub region. Perubahan menulis NIK ATASAN di Master_Sales (bisa dijadwalkan, bisa Vacant). */
require_once __DIR__ . '/api_acct.php';

const SUBREGION_PENDING = 'SUBREGION_PENDING';
const SUBREGION_RGM = 'SUBREGION_RGM';

function srJson_(string $k): array { $v = prop_get_($k); $a = $v ? json_decode($v, true) : []; return is_array($a) ? $a : []; }
function srToday_(): string { return (new DateTimeImmutable('now', jkt_tz_()))->format('Y-m-d'); }
function srCol_(array $h, string $c): int { $i = array_search($c, $h, true); if ($i === false) fail_('Kolom ' . $c . ' tidak ada di Master_Sales.'); return $i; }

/** Peta sub region → {sub,big,salesNiks[],asm,rgm}. */
function srState_(array $db): array {
    $people = $db['meta']['people']; $by = [];
    foreach ($people as $p) $by[$p['nik']] = $p;
    $subs = [];
    foreach ($people as $p) {
        $s = $p['region'] ?? ''; if ($s === '' || !in_array($p['role'], ['SALES', 'ASM'], true)) continue;
        $subs[$s] ??= ['sub' => $s, 'big' => $p['wilayah'] ?? '', 'sales' => [], 'asmRows' => []];
        if ($p['role'] === 'SALES') $subs[$s]['sales'][] = $p; else $subs[$s]['asmRows'][] = $p;
        if (!$subs[$s]['big']) $subs[$s]['big'] = $p['wilayah'] ?? '';
    }
    $mem = srJson_(SUBREGION_RGM); $out = [];
    foreach ($subs as $s => $x) {
        $cnt = [];
        foreach ($x['sales'] as $p) if ($p['atasan'] !== '' && ($by[$p['atasan']]['role'] ?? '') === 'ASM') $cnt[$p['atasan']] = ($cnt[$p['atasan']] ?? 0) + 1;
        arsort($cnt); $asm = $cnt ? array_key_first($cnt) : null;
        if (!$asm && count($x['asmRows']) === 1 && !$x['sales']) $asm = $x['asmRows'][0]['nik'];
        $rgm = null;
        if ($asm && ($by[$asm]['atasan'] ?? '') !== '') $rgm = $by[$asm]['atasan'];
        elseif (!$asm && !empty($mem[$s])) $rgm = $mem[$s];
        $out[$s] = ['sub' => $s, 'big' => $x['big'], 'sales' => array_map(fn($p) => $p['nik'], $x['sales']), 'asm' => $asm, 'rgm' => $rgm, 'asmRows' => array_map(fn($p) => $p['nik'], $x['asmRows'])];
    }
    ksort($out);
    return [$out, $by];
}
function srNm_(array $by, ?string $nik): ?array { return $nik && isset($by[$nik]) ? ['nik' => $nik, 'nama' => $by[$nik]['nama']] : null; }
function srAllowed_(array $u, array $st): bool { return $u['role'] === 'SUPER ADMIN' || ($u['role'] === 'ADMIN' && in_array($st['big'], $u['adminRegions'] ?? [], true)); }
function srGuard_($token, $sub): array {
    $db = snapshot_(); $u = adminGuard_($token, $db); [$st, $by] = srState_($db); $sub = text_($sub);
    if (!isset($st[$sub])) fail_('Sub region tidak ditemukan.');
    if (!srAllowed_($u, $st[$sub])) fail_('Sub region di luar cakupan Admin kamu.');
    return [$db, $u, $st[$sub], $by];
}
/** Hitung dampak. $asm/$rgm '' = Vacant. $scope: all | unassigned. */
function srPlan_(array $db, array $s, array $by, string $asm, string $rgm, string $scope): array {
    $block = []; $warn = [];
    if ($asm !== '' && (($by[$asm]['role'] ?? '') !== 'ASM')) $block[] = 'ASM pilihan bukan berposisi ASM.';
    if ($rgm !== '' && (($by[$rgm]['role'] ?? '') !== 'RGM')) $block[] = 'RGM pilihan bukan berposisi RGM.';
    $move = [];
    foreach ($s['sales'] as $n) {
        if ($n === $asm) continue;
        $cur = $by[$n]['atasan']; $isAsm = ($by[$cur]['role'] ?? '') === 'ASM';
        if ($cur === $asm) continue;
        if ($scope === 'unassigned' && $cur !== '' && $isAsm) continue;
        $move[] = ['nik' => $n, 'nama' => $by[$n]['nama'], 'dari' => $cur === '' ? null : ($by[$cur]['nama'] ?? $cur)];
    }
    $asmMove = null;
    if ($asm !== '') {
        $rows = array_values(array_filter($db['meta']['people'], fn($p) => $p['nik'] === $asm));
        $reg = $by[$asm]['region'] ?? '';
        if ($reg !== $s['sub']) {
            foreach (srState_($db)[0] as $o) if ($o['sub'] === $reg && $o['asm'] === $asm && $o['sub'] !== $s['sub']) $block[] = $by[$asm]['nama'] . ' masih jadi ASM aktif di sub region ' . $reg . '. Vacant-kan atau ganti ASM di sana dulu.';
            $asmMove = ['dari' => $reg, 'ke' => $s['sub']];
        }
    }
    $rgmChange = ($asm !== '' && ($by[$asm]['atasan'] ?? '') !== $rgm);
    if ($s['asm'] && $s['asm'] !== $asm && $asm !== '') $warn[] = ($by[$s['asm']]['nama'] ?? '') . ' tidak lagi punya anak buah di sub region ini; datanya tidak diubah.';
    if ($asm === '' && $rgm !== '') $warn[] = 'ASM Vacant: RGM dicatat untuk sub region ini, anak buah sales tanpa atasan.';
    return ['block' => $block, 'warn' => $warn, 'move' => $move, 'asmMove' => $asmMove, 'rgmChange' => $rgmChange];
}
function srApply_(array $db, string $actor, string $sub, string $asm, string $rgm, string $scope, string $reason, string $why): void {
    [$st, $by] = srState_($db); $s = $st[$sub] ?? null; if (!$s) fail_('Sub region ' . $sub . ' tidak ditemukan.');
    $pl = srPlan_($db, $s, $by, $asm, $rgm, $scope); if ($pl['block']) fail_(implode(' ', $pl['block']));
    $moveSet = array_flip(array_map(fn($m) => $m['nik'], $pl['move']));
    checkpoint_($actor, 'Sub region ' . $sub . ' · ' . $reason);
    $before = readGrid_('Master_Sales'); $mem = srJson_(SUBREGION_RGM);
    try {
        acctRewrite_('Master_Sales', function ($h, &$r) use ($sub, $asm, $rgm, $moveSet) {
            $ni = srCol_($h, 'NIK KARYAWAN'); $ai = srCol_($h, 'NIK ATASAN'); $ri = srCol_($h, 'REGION'); $pi = srCol_($h, 'POSISI');
            $n = text_($r[$ni] ?? null); $pos = js_upper(text_($r[$pi] ?? null));
            if (isset($moveSet[$n]) && $pos === 'SALES' && text_($r[$ri] ?? null) === $sub) $r[$ai] = $asm;
            if ($asm !== '' && $n === $asm && $pos === 'ASM') { $r[$ri] = $sub; $r[$ai] = $rgm; }
        });
        $mem[$sub] = $rgm; prop_set_(SUBREGION_RGM, json_encode($mem));
        $meta = meta_(); validateConfigChange_($db, $meta);
        publish_($meta, $db['raw'], $db['coverage'], $actor, 'SUB REGION ' . $sub . ' · ' . $reason);
    } catch (Throwable $e) { restoreGrid_('Master_Sales', $before); throw $e; }
    $nm = fn($n) => $n === '' ? 'VACANT' : ($by[$n]['nama'] ?? $n);
    audit_($actor, 'SUB_REGION', $sub . ' · ASM ' . $nm($asm) . ' · RGM ' . $nm($rgm) . ' · ' . count($pl['move']) . ' sales · ' . $reason . ($why ? ' · ' . $why : ''));
}
/** Terapkan jadwal yang sudah jatuh tempo. Aman dipanggil berulang. */
function srApplyDue_(): void {
    $pend = srJson_(SUBREGION_PENDING); if (!$pend) return; $today = srToday_(); $keep = []; $did = false;
    foreach ($pend as $p) { if (($p['eff'] ?? '9999') > $today) $keep[] = $p; else $due[] = $p; }
    if (empty($due)) return;
    with_lock_(function () use ($due, &$keep) {
        $left = srJson_(SUBREGION_PENDING); $ids = array_flip(array_map(fn($p) => $p['id'], $due));
        $left = array_values(array_filter($left, fn($p) => !isset($ids[$p['id']])));
        prop_set_(SUBREGION_PENDING, json_encode($left));
        foreach ($due as $p) {
            try { srApply_(snapshot_(), 'SISTEM', $p['sub'], $p['asm'], $p['rgm'], $p['scope'], $p['reason'], 'jadwal ' . $p['eff'] . ' oleh ' . $p['by']); }
            catch (Throwable $e) { audit_('SISTEM', 'SUB_REGION_GAGAL', $p['sub'] . ' · ' . $e->getMessage()); }
        }
    });
}
function apiSubRegionList($token) {
    srApplyDue_();
    $db = snapshot_(); $u = adminGuard_($token, $db); [$st, $by] = srState_($db); $rows = [];
    foreach ($st as $s) if (srAllowed_($u, $s)) $rows[] = ['sub' => $s['sub'], 'big' => $s['big'], 'sales' => count($s['sales']), 'asm' => srNm_($by, $s['asm']), 'rgm' => srNm_($by, $s['rgm'])];
    $cand = fn($role) => array_values(array_map(fn($p) => ['nik' => $p['nik'], 'nama' => $p['nama'], 'region' => $p['region']], array_filter($db['meta']['people'], fn($p) => $p['role'] === $role)));
    $pend = array_values(array_filter(srJson_(SUBREGION_PENDING), fn($p) => isset($st[$p['sub']]) && srAllowed_($u, $st[$p['sub']])));
    foreach ($pend as &$p) { $p['asmNama'] = $p['asm'] === '' ? 'Vacant' : ($by[$p['asm']]['nama'] ?? $p['asm']); $p['rgmNama'] = $p['rgm'] === '' ? 'Vacant' : ($by[$p['rgm']]['nama'] ?? $p['rgm']); } unset($p);
    return ['rows' => $rows, 'asmList' => $cand('ASM'), 'rgmList' => $cand('RGM'), 'pending' => $pend, 'today' => srToday_()];
}
function srArgs_($asm, $rgm, $scope): array { return [text_($asm), text_($rgm), $scope === 'unassigned' ? 'unassigned' : 'all']; }
function apiSubRegionPreview($token, $sub, $asm = '', $rgm = '', $scope = 'all') {
    [$db, $u, $s, $by] = srGuard_($token, $sub); [$asm, $rgm, $scope] = srArgs_($asm, $rgm, $scope);
    $pl = srPlan_($db, $s, $by, $asm, $rgm, $scope);
    return ['sub' => $s['sub'], 'asmNow' => srNm_($by, $s['asm']), 'rgmNow' => srNm_($by, $s['rgm']), 'asmNew' => $asm === '' ? null : srNm_($by, $asm), 'rgmNew' => $rgm === '' ? null : srNm_($by, $rgm), 'asmVacant' => $asm === '', 'rgmVacant' => $rgm === '', 'move' => $pl['move'], 'asmMove' => $pl['asmMove'], 'rgmChange' => $pl['rgmChange'], 'warn' => $pl['warn'], 'block' => $pl['block'], 'total' => count($s['sales'])];
}
function apiSubRegionSave($token, $sub, $asm, $rgm, $reason, $eff = '', $scope = 'all') {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    [$asm, $rgm, $scope] = srArgs_($asm, $rgm, $scope); $eff = text_($eff); $today = srToday_();
    if ($eff === '' || $eff === 'now') $eff = $today;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eff)) fail_('Format tanggal efektif salah.');
    if ($eff < $today) fail_('Tanggal efektif tidak boleh mundur.');
    return with_lock_(function () use ($token, $sub, $asm, $rgm, $reason, $eff, $scope, $today) {
        [$db, $u, $s, $by] = srGuard_($token, $sub);
        $pl = srPlan_($db, $s, $by, $asm, $rgm, $scope); if ($pl['block']) fail_(implode(' ', $pl['block']));
        if ($eff === $today) { srApply_($db, $u['nik'], $s['sub'], $asm, $rgm, $scope, $reason, ''); return ['ok' => true, 'applied' => true, 'moved' => count($pl['move'])]; }
        $pend = srJson_(SUBREGION_PENDING);
        $pend = array_values(array_filter($pend, fn($p) => $p['sub'] !== $s['sub']));
        $pend[] = ['id' => bin2hex(random_bytes(4)), 'sub' => $s['sub'], 'asm' => $asm, 'rgm' => $rgm, 'scope' => $scope, 'eff' => $eff, 'reason' => $reason, 'by' => $u['nama'], 'at' => $today];
        prop_set_(SUBREGION_PENDING, json_encode($pend));
        audit_($u['nik'], 'SUB_REGION_JADWAL', $s['sub'] . ' · efektif ' . $eff . ' · ' . $reason);
        return ['ok' => true, 'applied' => false, 'eff' => $eff];
    });
}
function apiSubRegionCancel($token, $id) {
    return with_lock_(function () use ($token, $id) {
        $db = snapshot_(); $u = adminGuard_($token, $db); [$st] = srState_($db); $pend = srJson_(SUBREGION_PENDING); $out = []; $hit = null;
        foreach ($pend as $p) { if ($p['id'] === $id) $hit = $p; else $out[] = $p; }
        if (!$hit) fail_('Jadwal tidak ditemukan (mungkin sudah berlaku).');
        if (!isset($st[$hit['sub']]) || !srAllowed_($u, $st[$hit['sub']])) fail_('Di luar cakupan Admin kamu.');
        prop_set_(SUBREGION_PENDING, json_encode($out));
        audit_($u['nik'], 'SUB_REGION_BATAL', $hit['sub'] . ' · efektif ' . $hit['eff']);
        return ['ok' => true];
    });
}
