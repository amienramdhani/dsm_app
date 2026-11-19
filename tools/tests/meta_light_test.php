<?php
/**
 * Uji apiDealerContact dengan snapshot_meta_() vs snapshot penuh: hasil & pesan error harus SAMA PERSIS.
 * Tanpa MySQL: lib disalin ke folder sementara; db_() memakai SQLite, authSession_/account_ memakai data uji.
 * Jalankan: php tools/tests/meta_light_test.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2); $tmp = sys_get_temp_dir() . '/dsm_ml_' . getmypid();
@mkdir("$tmp/lib", 0775, true); copy("$root/config.php", "$tmp/config.php");
foreach (glob("$root/lib/*.php") as $f) copy($f, "$tmp/lib/" . basename($f));
$rw = function (string $file, string $fn, string $body) {
    $s = file_get_contents($file); $i = strpos($s, "function $fn("); if ($i === false) die("tidak ada $fn\n");
    $b = strpos($s, '{', $i); $d = 0; for ($j = $b; $j < strlen($s); $j++) { if ($s[$j] === '{') $d++; elseif ($s[$j] === '}') { $d--; if ($d === 0) break; } }
    file_put_contents($file, substr($s, 0, $b) . '{' . $body . '}' . substr($s, $j + 1));
};
$rw("$tmp/lib/data.php", 'db_', 'static $p = null; if (!$p) { $p = new PDO("sqlite::memory:", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); $p->exec("CREATE TABLE _props (k TEXT PRIMARY KEY, v TEXT)"); } return $p;');
$rw("$tmp/lib/auth.php", 'authSession_', '$s = $GLOBALS["T_SESS"][$token] ?? null; if (!$s) fail_("SESI: Silakan masuk kembali."); $a = $GLOBALS["T_ACC"][$s["nik"]] ?? null; if (!$a) fail_("SESI: Silakan masuk kembali."); return ["s" => $s, "a" => $a];');
$rw("$tmp/lib/auth.php", 'account_', 'return $GLOBALS["T_ACC"][text_($nik)] ?? null;');
define('DSM_STORAGE', "$tmp/storage"); define('DSM_PEPPER', 'pepper-uji');
require "$tmp/lib/js.php"; require "$tmp/lib/core.php"; require "$tmp/lib/common.php"; require "$tmp/lib/api_contact.php"; require "$tmp/lib/publish.php";
$pass = 0; $fail = 0; function t($n, $ok) { global $pass, $fail; $ok ? $pass++ : $fail++; echo ($ok ? '  ✓ ' : '  ✗ ') . $n . "\n"; }
function fresh() { foreach (['DSM_SNAP_CACHE','DSM_PEOPLE_CACHE','DSM_META_CACHE'] as $k) $GLOBALS[$k] = null; $GLOBALS['DSM_JSON_MEMO'] = []; PortalCore::resetMemo('x' . mt_rand()); }
$people = [
  ['nik' => 'S1', 'nama' => 'Sales 1', 'role' => 'SALES', 'region' => 'CRB', 'atasan' => 'A1', 'sales' => true, 'portfolio' => 'CRB', 'wilayah' => 'JABAR'],
  ['nik' => 'S2', 'nama' => 'Sales 2', 'role' => 'SALES', 'region' => 'SMG', 'atasan' => 'A2', 'sales' => true, 'portfolio' => 'SMG', 'wilayah' => 'JATENG'],
  ['nik' => 'A1', 'nama' => 'Spv 1', 'role' => 'SPV', 'region' => 'CRB', 'atasan' => 'R1', 'sales' => false, 'portfolio' => '', 'wilayah' => 'JABAR'],
  ['nik' => 'A2', 'nama' => 'Spv 2', 'role' => 'SPV', 'region' => 'SMG', 'atasan' => '', 'sales' => false, 'portfolio' => '', 'wilayah' => 'JATENG'],
  ['nik' => 'R1', 'nama' => 'Asm lama', 'role' => 'ASM', 'region' => 'CRB', 'atasan' => '', 'sales' => false, 'portfolio' => '', 'wilayah' => 'JABAR'],
  ['nik' => 'AD', 'nama' => 'Admin Jabar', 'role' => 'ADMIN', 'region' => '', 'atasan' => '', 'sales' => false, 'portfolio' => '', 'wilayah' => '', 'adminRegions' => ['JABAR']],
  ['nik' => 'SA', 'nama' => 'Super', 'role' => 'SUPER ADMIN', 'region' => '', 'atasan' => '', 'sales' => false, 'portfolio' => '', 'wilayah' => ''],
];
$cust = ['C1' => ['id' => 'C1', 'nama' => 'TOKO A', 'nik' => 'S1', 'region' => 'CRB', 'kota' => 'CIREBON', 'pic' => '', 'alamat' => 'JL A', 'hp' => '0812000001', 'aliases' => ['X1']],
         'C2' => ['id' => 'C2', 'nama' => 'TOKO B', 'nik' => 'S2', 'region' => 'SMG', 'kota' => 'SEMARANG', 'pic' => 'Ani', 'alamat' => 'JL B', 'hp' => '0812000002', 'aliases' => ['']],
         '1001' => ['id' => '1001', 'nama' => 'TOKO ANGKA', 'nik' => 'S1', 'region' => 'CRB', 'kota' => 'CIREBON', 'pic' => '', 'alamat' => '', 'hp' => '', 'aliases' => ['']]];
$cubes = []; for ($i = 0; $i < 200000; $i++) $cubes['2026-10'][] = ['date' => '2026-10-01', 'region' => 'JABAR', 'nik' => 'S1', 'customer' => 'C1', 'brand' => 'ITEL', 'type' => 'A', 'q' => 1, 'a' => 1500000.5, 'acc' => 0, 'aq' => 0, 'docs' => ['["CS","INV' . $i . '"]']];
writeJsonId_('F1', pubOut_(['meta' => ['roleSchema' => 1, 'people' => $people, 'customers' => $cust, 'products' => ['P1' => ['id' => 'P1', 'brand' => 'ITEL', 'device' => true]], 'targets' => [['period' => '2026-10', 'nik' => 'S1', 'brand' => 'ITEL', 'metric' => 'QTY', 'target' => 12.5, 'indicator' => '']], 'policies' => [], 'warnings' => []], 'cubes' => $cubes, 'coverage' => ['2026-10' => ['cutoff' => '2026-10-28', 'closed' => false]]]));
unset($cubes); prop_set_('ACTIVE', 'F1');
// status kontak: override + riwayat permintaan
$state = ['requests' => [['id' => 'r1', 'dealer' => 'C1', 'actor' => 'S1', 'status' => 'PENDING'], ['id' => 'r2', 'dealer' => 'C2', 'actor' => 'AD', 'status' => 'APPROVED'], ['id' => 'r3', 'dealer' => 'C1', 'actor' => 'AD', 'status' => 'APPROVED']], 'overrides' => js_obj(['C1' => ['pic' => 'Budi', 'hp' => '0899', 'alamat' => 'JL BARU']])];
prop_set_('DEALER_CONTACT_STATE', writeJson_('dc', $state));
$GLOBALS['T_ACC'] = []; foreach ($people as $p) $GLOBALS['T_ACC'][$p['nik']] = ['AKTIF' => true, 'WAJIB_GANTI' => false];
$GLOBALS['T_SESS'] = []; foreach ($people as $p) $GLOBALS['T_SESS']['t' . $p['nik']] = ['nik' => $p['nik']];
$call = function ($tok, $id, $light) { fresh(); $GLOBALS['DSM_META_LIGHT_OFF'] = $light ? 0 : 1; try { $r = apiDealerContact($tok, $id); return ['ok', json_encode($r)]; } catch (Throwable $e) { return [get_class($e), $e->getMessage()]; } };
$diff = []; $same = 0; $okCalls = 0;
foreach (array_keys($GLOBALS['T_SESS']) as $tok) foreach (['C1', 'C2', '1001', 'TIDAK', null] as $id) {
  $a = $call($tok, $id, false); $b = $call($tok, $id, true); @unlink(file_path_('F1-meta')); $c = $call($tok, $id, true);
  if ($a === $b && $a === $c) { $same++; if ($a[0] === 'ok') $okCalls++; } else $diff[] = "$tok/$id full=" . json_encode($a) . " light=" . json_encode($b);
}
t("apiDealerContact ringan = penuh ($same kombinasi role x dealer, $okCalls berhasil, sisanya ditolak dengan pesan sama)", !$diff && $okCalls > 3); foreach (array_slice($diff, 0, 4) as $d) echo "      $d\n";
$r = json_decode($call('tSA', 'C1', true)[1], true); t('kontak terbaru (override) ikut tampil', $r['values']['pic'] === 'Budi' && $r['values']['hp'] === '0899');
fresh(); $GLOBALS['DSM_META_LIGHT_OFF'] = 0; apiDealerContact('tSA', 'C1'); t('versi ringan tidak memuat snapshot penuh', ($GLOBALS['DSM_SNAP_CACHE'] ?? null) === null && is_file(file_path_('F1-meta')));
// kontak diubah -> hasil ikut berubah tanpa publikasi
$state['overrides'] = js_obj(['C1' => ['pic' => 'Citra', 'hp' => '0811', 'alamat' => 'JL C']]); prop_set_('DEALER_CONTACT_STATE', writeJson_('dc2', $state));
$GLOBALS['DSM_META_CACHE'] = $GLOBALS['DSM_META_CACHE']; // cache request lama sengaja dibiarkan: kunci memuat status kontak
$r = apiDealerContact('tSA', 'C1'); t('status kontak berubah -> nilai baru (tanpa publikasi)', $r['values']['pic'] === 'Citra');
// publikasi baru
$s2 = json_decode(file_get_contents(file_path_('F1')), true); $s2['meta']['customers']['C1']['nama'] = 'TOKO A2'; $s2['meta']['customers']['C1']['nik'] = 'S2'; writeJsonId_('F2', $s2); unset($s2); prop_set_('ACTIVE', 'F2');
$a = $call('tAD', 'C1', false); $b = $call('tAD', 'C1', true); t('publikasi baru (dealer pindah sales) -> hasil sama dengan penuh', $a === $b && is_file(file_path_('F2-meta')));
file_put_contents(file_path_('F2-meta'), '{rusak'); $b = $call('tSA', 'C1', true); $a = $call('tSA', 'C1', false); t('file -meta rusak: tetap benar', $a === $b);
prop_set_('META_LIGHT_OFF', '1'); fresh(); $GLOBALS['DSM_META_LIGHT_OFF'] = 0; apiDealerContact('tSA', 'C1'); t('properti META_LIGHT_OFF=1: kembali muat snapshot penuh', ($GLOBALS['DSM_SNAP_CACHE'] ?? null) !== null); prop_delete_('META_LIGHT_OFF');
$GLOBALS['DSM_TM_CONTEXT'] = ['nik' => 'T']; fresh(); $GLOBALS['DSM_META_LIGHT_OFF'] = 0; $x = snapshot_meta_(); t('Telemarketing: tetap snapshot penuh (meta di-scope)', empty($x['_metaOnly'])); unset($GLOBALS['DSM_TM_CONTEXT']);
$n = 5; $t0 = microtime(true); for ($i = 0; $i < $n; $i++) $call('tSA', 'C1', false); $full = (microtime(true) - $t0) / $n * 1000;
$call('tSA', 'C1', true); $t0 = microtime(true); for ($i = 0; $i < $n; $i++) $call('tSA', 'C1', true); $light = (microtime(true) - $t0) / $n * 1000;
printf("  (apiDealerContact per request: penuh %.0f ms, ringan %.1f ms)\n", $full, $light); t('ringan jauh lebih cepat', $light * 10 < $full);
exec('rm -rf ' . escapeshellarg($tmp));
echo "\nHasil: $pass lolos, $fail gagal\n"; exit($fail ? 1 : 0);
