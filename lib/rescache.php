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
        $auth = authSession_($args[0]); $s = $auth['s']; $GLOBALS['DSM_CACHE_NIK'] = (string) $s['nik']; // dipakai warmNote_
        if (!empty($s['demoNik'])) { $GLOBALS['DSM_CACHE_NIK'] = null; return null; }
        if (PortalCore::active($auth['a']['WAJIB_GANTI'])) return null;
        $active = prop_get_('ACTIVE'); if (!$active) return null;
        // Fungsi di DSM_CONTACT_PATCH: kontak dealer TIDAK ikut kunci (simpan kontak tidak membuang cache semua user);
        // kontak terbaru ditempel saat cache dibaca (dsm_cache_contact_). Darurat: properti CONTACT_PATCH_OFF = 1.
        $cs = dsm_contact_patch_on_($fn) ? '*' : (prop_get_('DEALER_CONTACT_STATE') ?? '');
        $ver = dsm_code_ver_() . '|' . $active . '|' . $cs . '|' . (prop_get_('DATA_REV') ?? '');
        return hash('sha256', $fn . '|' . $s['nik'] . '|' . $ver . '|' . json_encode(array_slice($args, 1)));
    } catch (Throwable $e) { return null; }
}
/** Fungsi cache yang kontak dealernya (hp/alamat/pic pada objek ber-id dealer) ditempel ulang saat dibaca. Hanya yang sudah diaudit. */
const DSM_CONTACT_PATCH = ['apiDashboard'];
function dsm_contact_patch_on_(string $fn): bool {
    if (!in_array($fn, DSM_CONTACT_PATCH, true)) return false;
    try { return !prop_get_('CONTACT_PATCH_OFF'); } catch (Throwable $e) { return false; }
}
/** Isi cache untuk fungsi DSM_CONTACT_PATCH disimpan dengan baris awal "CS:<id status kontak>" (ditulis di api.php dsm_reply). */
/**
 * Baca cache fungsi DSM_CONTACT_PATCH. Status kontak sama dengan saat disimpan -> kirim apa adanya.
 * Berbeda -> tempel kontak terbaru (override) ke setiap objek {id: kode dealer, hp/alamat/pic}, simpan ulang, kirim.
 */
function dsm_cache_contact_(?string $key): ?string {
    $raw = dsm_cache_get_($key); if ($raw === null) return null;
    if (!str_starts_with($raw, 'CS:')) return null; // format lama: anggap tidak ada
    $nl = strpos($raw, "\n"); if ($nl === false) return null;
    $was = substr($raw, 3, $nl - 3); $json = substr($raw, $nl + 1); $cur = prop_get_('DEALER_CONTACT_STATE') ?? '';
    if ($was === $cur) return $json;
    $patched = dsm_contact_patch_json_($json, contactState_()['overrides'] ?? []);
    if ($patched === null) return null; // gagal menempel: hitung ulang seperti biasa
    dsm_cache_put_($key, 'CS:' . $cur . "\n" . $patched);
    return $patched;
}
function dsm_contact_patch_json_(string $json, $overrides): ?string {
    if (!$overrides) return $json;
    $data = json_decode($json); if (!is_object($data)) return null; // objek tetap objek ({} tidak berubah jadi [])
    $ov = []; foreach ($overrides as $id => $v) $ov[(string) $id] = (array) $v;
    $walk = function (&$x) use (&$walk, $ov) {
        if (is_array($x)) { foreach ($x as &$y) $walk($y); unset($y); return; }
        if (!is_object($x)) return;
        if (isset($x->id) && (is_string($x->id) || is_int($x->id)) && isset($ov[(string) $x->id])) {
            foreach (['hp', 'alamat', 'pic'] as $f) if (property_exists($x, $f) && array_key_exists($f, $ov[(string) $x->id])) $x->$f = $ov[(string) $x->id][$f];
        }
        foreach (get_object_vars($x) as $k => $y) { if (is_array($y) || is_object($y)) { $walk($y); $x->$k = $y; } }
    };
    $walk($data);
    $out = json_encode($data, (JS_JSON_FLAGS & ~JSON_PRESERVE_ZERO_FRACTION) | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    return $out === false ? null : $out;
}
function dsm_cache_get_(?string $key): ?string {
    if ($key === null) return null;
    $p = dsm_cache_dir_() . '/' . $key . '.json';
    if (!is_file($p)) return null;
    $v = @file_get_contents($p);
    if ($v === false || $v === '') return null;
    @touch($p); // tandai baru dipakai: pemangkasan membuang yang paling lama TIDAK dipakai, bukan yang paling lama dibuat
    return $v;
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
/** Batas cache: jumlah file DAN total ukuran (bisa diubah lewat config.local.php: DSM_CACHE_MAX_FILES / DSM_CACHE_MAX_MB).
 *  Dulu 300 file (dipangkas ke 200): dengan banyak akun x menu x filter cepat penuh, cache user sudah terbuang sebelum dipakai lagi. */
function dsm_cache_limits_(): array {
    return [defined('DSM_CACHE_MAX_FILES') ? (int) DSM_CACHE_MAX_FILES : 3000, (defined('DSM_CACHE_MAX_MB') ? (int) DSM_CACHE_MAX_MB : 1024) * 1048576];
}
function dsm_cache_prune_(string $dir): void {
    [$maxN, $maxB] = dsm_cache_limits_();
    $files = []; $total = 0;
    foreach (glob($dir . '/*.json') ?: [] as $f) { $sz = (int) @filesize($f); $files[] = [$f, (int) @filemtime($f), $sz]; $total += $sz; }
    if (count($files) <= $maxN && $total <= $maxB) return;
    usort($files, fn($a, $b) => $a[1] <=> $b[1]); // paling lama tidak dipakai lebih dulu
    $n = count($files); $tN = (int) ($maxN * 0.8); $tB = (int) ($maxB * 0.8);
    foreach ($files as $x) { if ($n <= $tN && $total <= $tB) break; if (@unlink($x[0])) { $n--; $total -= $x[2]; } }
}
