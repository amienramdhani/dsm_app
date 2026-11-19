<?php
/**
 * Uji guard_() ringan (snapshot_people_) vs guard_() dengan snapshot penuh: hasil & pesan error harus SAMA PERSIS.
 * Tanpa MySQL: lib disalin ke folder sementara; db_() memakai SQLite, authSession_/account_ memakai data uji.
 * Jalankan: php tools/tests/guard_light_test.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2); $tmp = sys_get_temp_dir() . '/dsm_gl_' . getmypid();
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
define('DSM_STORAGE', "$tmp/storage");
require "$tmp/lib/js.php"; require "$tmp/lib/core.php"; require "$tmp/lib/auth.php";
$pass = 0; $fail = 0; function t($n, $ok) { global $pass, $fail; $ok ? $pass++ : $fail++; echo ($ok ? '  ✓ ' : '  ✗ ') . $n . "\n"; }
function fresh() { $GLOBALS['DSM_SNAP_CACHE'] = null; $GLOBALS['DSM_JSON_MEMO'] = []; $GLOBALS['DSM_PEOPLE_CACHE'] = null; PortalCore::resetMemo('x' . mt_rand()); }

// Snapshot uji: skema jabatan lama (1) supaya adaptMeta ikut diuji (SPV->ASM, ASM->RGM).
$people = [
  ['nik' => 'S1', 'nama' => 'Sales 1', 'role' => 'SALES', 'region' => 'CRB', 'atasan' => 'A1', 'sales' => true, 'portfolio' => 'CRB', 'wilayah' => 'JABAR'],
  ['nik' => 'A1', 'nama' => 'Spv 1', 'role' => 'SPV', 'region' => 'CRB', 'atasan' => 'R1', 'sales' => false, 'portfolio' => '', 'wilayah' => 'JABAR'],
  ['nik' => 'R1', 'nama' => 'Asm lama', 'role' => 'ASM', 'region' => 'CRB', 'atasan' => '', 'sales' => false, 'portfolio' => '', 'wilayah' => 'JABAR'],
  ['nik' => 'T1', 'nama' => 'Tele 1', 'role' => 'TELEMARKETING', 'region' => 'CRB', 'atasan' => 'R1', 'sales' => false, 'portfolio' => '', 'wilayah' => 'JABAR'],
  ['nik' => 'AD', 'nama' => 'Admin', 'role' => 'ADMIN', 'region' => '', 'atasan' => '', 'sales' => false, 'portfolio' => '', 'wilayah' => '', 'adminRegions' => ['JABAR']],
  ['nik' => 'SA', 'nama' => 'Super', 'role' => 'SUPER ADMIN', 'region' => '', 'atasan' => '', 'sales' => false, 'portfolio' => '', 'wilayah' => ''],
  ['nik' => '-', 'nama' => 'Kosong', 'role' => 'SALES', 'region' => '', 'atasan' => '', 'sales' => true, 'portfolio' => '', 'wilayah' => ''],
  ['nik' => '0123', 'nama' => 'NIK angka', 'role' => 'SALES', 'region' => 'CRB', 'atasan' => 'A1', 'sales' => true, 'portfolio' => 'CRB', 'wilayah' => 'JABAR'],
];
$cubes = []; for ($i = 0; $i < 300000; $i++) $cubes['2026-10'][] = ['date' => '2026-10-' . sprintf('%02d', 1 + $i % 28), 'region' => 'JABAR', 'nik' => 'S1', 'customer' => 'C' . ($i % 5000), 'brand' => 'ITEL', 'type' => 'A' . ($i % 40), 'q' => 1, 'a' => 1500000, 'acc' => 0, 'aq' => 0, 'docs' => ['["CS","INV' . $i . '"]']];
$snap = ['meta' => ['roleSchema' => 1, 'people' => $people, 'customers' => ['C1' => ['id' => 'C1', 'nama' => 'X', 'nik' => 'S1']], 'products' => []], 'cubes' => $cubes, 'coverage' => []];
writeJsonId_('F1', $snap); unset($cubes, $snap); prop_set_('ACTIVE', 'F1');
echo "  (snapshot uji " . round(filesize(file_path_('F1')) / 1048576, 1) . " MB)\n";

$GLOBALS['T_ACC'] = []; foreach ($people as $p) $GLOBALS['T_ACC'][$p['nik']] = ['AKTIF' => true, 'WAJIB_GANTI' => false];
$GLOBALS['T_ACC']['WG'] = ['AKTIF' => true, 'WAJIB_GANTI' => true]; $GLOBALS['T_ACC']['XX'] = ['AKTIF' => true, 'WAJIB_GANTI' => false];
$GLOBALS['T_ACC']['S1W'] = ['AKTIF' => true, 'WAJIB_GANTI' => true];
$sess = ['tS1' => ['nik' => 'S1'], 'tA1' => ['nik' => 'A1'], 'tR1' => ['nik' => 'R1'], 'tT1' => ['nik' => 'T1'], 'tAD' => ['nik' => 'AD'], 'tSA' => ['nik' => 'SA'],
  'tDash' => ['nik' => '-'], 'tNum' => ['nik' => '0123'], 'tXX' => ['nik' => 'XX'], 'tWG' => ['nik' => 'S1W'],
  'tDemoS1' => ['nik' => 'SA', 'demoNik' => 'S1'], 'tDemoAD' => ['nik' => 'SA', 'demoNik' => 'AD'], 'tDemoX' => ['nik' => 'SA', 'demoNik' => 'ZZ']];
$GLOBALS['T_SESS'] = $sess;
$run = function ($tok, $admin, $allow, $full) { fresh(); try { $r = $full ? guard_($tok, $admin, $allow, snapshot_()) : guard_($tok, $admin, $allow); return ['ok', json_encode($r)]; } catch (Throwable $e) { return [get_class($e), $e->getMessage()]; } };
$same = 0; $diff = [];
foreach (array_merge(array_keys($sess), ['tidak-ada']) as $tok) foreach ([false, true] as $admin) foreach ([false, true] as $allow) {
    $a = $run($tok, $admin, $allow, true); $b = $run($tok, $admin, $allow, false); // b: request ke-2+ (file -people sudah ada)
    @unlink(file_path_('F1-people')); $c = $run($tok, $admin, $allow, false);       // c: request pertama (file belum ada)
    if ($a === $b && $a === $c) $same++; else $diff[] = "$tok admin=" . (int) $admin . " allow=" . (int) $allow . " full=" . json_encode($a) . " light=" . json_encode($b);
}
t("guard_ ringan = guard_ penuh untuk $same kombinasi (role, Demo, admin, wajib ganti PIN, akun hilang)", !$diff); foreach (array_slice($diff, 0, 5) as $d) echo "      $d\n";
fresh(); $r = guard_('tA1'); t('skema jabatan lama ikut disesuaikan (SPV -> ASM)', $r['role'] === 'ASM');
fresh(); $r = guard_('tR1'); t('skema jabatan lama ikut disesuaikan (ASM -> RGM)', $r['role'] === 'RGM');
fresh(); $r = guard_('tNum'); t('NIK berawalan 0 tetap teks', $r['nik'] === '0123');
fresh(); guard_('tS1'); t('guard_ ringan tidak memuat snapshot penuh', ($GLOBALS['DSM_SNAP_CACHE'] ?? null) === null && is_file(file_path_('F1-people')));
// publikasi baru: nama orang berubah -> file lama tidak dipakai
$s2 = json_decode(file_get_contents(file_path_('F1')), true); $s2['meta']['people'][0]['nama'] = 'Sales Baru'; writeJsonId_('F2', $s2); unset($s2); prop_set_('ACTIVE', 'F2');
fresh(); t('setelah publikasi baru: data orang terbaru', guard_('tS1')['nama'] === 'Sales Baru' && is_file(file_path_('F2-people')));
// file -people rusak / milik snapshot lain -> dibuat ulang dari snapshot penuh
file_put_contents(file_path_('F2-people'), '{rusak'); fresh(); t('file -people rusak: tetap benar', guard_('tS1')['nama'] === 'Sales Baru');
file_put_contents(file_path_('F2-people'), json_encode(['id' => 'F1', 'people' => $people])); fresh(); t('file -people salah id: tidak dipakai', guard_('tS1')['nama'] === 'Sales Baru');
// sakelar darurat
prop_set_('PEOPLE_LIGHT_OFF', '1'); fresh(); guard_('tS1'); t('properti PEOPLE_LIGHT_OFF=1: kembali muat snapshot penuh', ($GLOBALS['DSM_SNAP_CACHE'] ?? null) !== null); prop_delete_('PEOPLE_LIGHT_OFF');
// snapshot sudah dimuat di request ini -> people diambil dari situ (tanpa baca file)
fresh(); snapshot_(); @unlink(file_path_('F2-people')); guard_('tS1'); t('snapshot sudah dimuat: tidak menulis file tambahan', !is_file(file_path_('F2-people')));
// waktu
fresh(); guard_('tS1'); $n = 5;
$t0 = microtime(true); for ($i = 0; $i < $n; $i++) { fresh(); guard_('tS1', false, false, snapshot_()); } $full = (microtime(true) - $t0) / $n * 1000;
$t0 = microtime(true); for ($i = 0; $i < $n; $i++) { fresh(); guard_('tS1'); } $light = (microtime(true) - $t0) / $n * 1000;
printf("  (waktu guard_ per request: penuh %.0f ms, ringan %.1f ms)\n", $full, $light);
t('ringan jauh lebih cepat', $light * 10 < $full);
exec('rm -rf ' . escapeshellarg($tmp));
echo "\nHasil: $pass lolos, $fail gagal\n"; exit($fail ? 1 : 0);
