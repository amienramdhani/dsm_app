<?php
/**
 * Cache hasil laporan (baca-saja) agar klik berikutnya instan.
 * Kunci = fungsi + akun + argumen + versi data (ACTIVE, kontak dealer, DATA_REV). Data berubah → kunci berubah → cache lama otomatis tidak dipakai.
 * Hanya fungsi tanpa efek samping (sudah diperiksa). Mode Demo & akun wajib-ganti-PIN tidak di-cache.
 */
const DSM_CACHEABLE = ['apiDashboard', 'apiDealerHistory', 'apiDealerHistorySummary', 'apiDOS', 'apiPotensi', 'apiAnalisa', 'apiEarlyWarning', 'apiTeam', 'apiTeamComparison', 'apiCompare', 'apiGrowthType', 'apiExec', 'apiWeeklyAnalysis', 'apiKpiTree', 'apiTeamKpi', 'apiRank', 'apiMyRank', 'apiTransactions', 'apiTransactionGroups', 'apiDealerStock', 'apiDealerDetail'];

function dsm_cache_dir_(): string { $d = storage_dir_('cache'); return $d; }
/** Versi kode: berubah kalau file PHP diganti, supaya cache hasil lama tidak terpakai setelah update aplikasi. */
function dsm_code_ver_(): string {
    static $v = null; if ($v !== null) return $v;
    $m = (int) @filemtime(dirname(__DIR__) . '/api.php');
    foreach (glob(__DIR__ . '/*.php') ?: [] as $f) $m = max($m, (int) @filemtime($f));
    return $v = (string) $m;
}
function dsm_cache_key_(string $fn, array $args): ?string {
    if (!in_array($fn, DSM_CACHEABLE, true) || !$args) return null;
    try {
        $auth = authSession_($args[0]); $s = $auth['s'];
        if (!empty($s['demoNik'])) return null;
        if (PortalCore::active($auth['a']['WAJIB_GANTI'])) return null;
        $active = prop_get_('ACTIVE'); if (!$active) return null;
        $ver = dsm_code_ver_() . '|' . $active . '|' . (prop_get_('DEALER_CONTACT_STATE') ?? '') . '|' . (prop_get_('DATA_REV') ?? '');
        return hash('sha256', $fn . '|' . $s['nik'] . '|' . $ver . '|' . json_encode(array_slice($args, 1)));
    } catch (Throwable $e) { return null; }
}
function dsm_cache_get_(?string $key): ?string {
    if ($key === null) return null;
    $p = dsm_cache_dir_() . '/' . $key . '.json';
    if (!is_file($p)) return null;
    $v = @file_get_contents($p);
    return ($v === false || $v === '') ? null : $v;
}
function dsm_cache_put_(?string $key, string $json): void {
    if ($key === null || strlen($json) > 12 * 1048576) return;
    try {
        $dir = dsm_cache_dir_(); $tmp = $dir . '/' . $key . '.tmp' . getmypid();
        if (@file_put_contents($tmp, $json) === false) return;
        @rename($tmp, $dir . '/' . $key . '.json');
        if (mt_rand(1, 25) === 1) dsm_cache_prune_($dir);
    } catch (Throwable $e) { /* abaikan */ }
}
function dsm_cache_prune_(string $dir): void {
    $files = glob($dir . '/*.json') ?: []; if (count($files) <= 300) return;
    usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));
    foreach (array_slice($files, 0, count($files) - 200) as $f) @unlink($f);
}
