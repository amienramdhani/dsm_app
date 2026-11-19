<?php
/** Uji pemangkasan cache hasil (lib/rescache.php). Jalankan: php tools/tests/rescache_prune_test.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
define('DSM_STORAGE', sys_get_temp_dir() . '/dsm_rc_test_' . getmypid());
define('DSM_CACHE_MAX_FILES', 50); define('DSM_CACHE_MAX_MB', 1);
function storage_dir_(string $sub = ''): string { $d = DSM_STORAGE . ($sub !== '' ? '/' . $sub : ''); if (!is_dir($d)) mkdir($d, 0775, true); return $d; }
require $root . '/lib/rescache.php';
$pass = 0; $fail = 0; function t($n, $ok) { global $pass, $fail; $ok ? $pass++ : $fail++; echo ($ok ? '  ✓ ' : '  ✗ ') . $n . "\n"; }
$dir = dsm_cache_dir_(); $now = time();
$mk = function (string $k, int $bytes, int $age) use ($dir, $now) { file_put_contents("$dir/$k.json", str_repeat('x', $bytes)); touch("$dir/$k.json", $now - $age); };
// 1) di bawah batas: tidak ada yang dihapus
for ($i = 0; $i < 40; $i++) $mk("a$i", 100, 1000 - $i);
dsm_cache_prune_($dir); t('di bawah batas: tidak dipangkas', count(glob("$dir/*.json")) === 40);
// 2) lewat batas jumlah: turun ke 80% dan yang dibuang paling lama
for ($i = 40; $i < 70; $i++) $mk("a$i", 100, 1000 - $i);
dsm_cache_prune_($dir); $left = glob("$dir/*.json");
t('lewat batas jumlah: sisa 40 (80% dari 50)', count($left) === 40);
t('yang dibuang paling lama (a0..a29 hilang)', !is_file("$dir/a0.json") && !is_file("$dir/a29.json") && is_file("$dir/a30.json") && is_file("$dir/a69.json"));
// 3) cache yang baru dibaca bertahan walau dibuat paling awal
$mk('hot', 100, 5000);
dsm_cache_get_('hot');
for ($i = 70; $i < 90; $i++) $mk("a$i", 100, 10);
dsm_cache_prune_($dir); t('baru dibaca (touch) tidak ikut dibuang', is_file("$dir/hot.json"));
// 4) lewat batas ukuran
foreach (glob("$dir/*.json") as $f) unlink($f);
for ($i = 0; $i < 10; $i++) $mk("b$i", 200000, 100 - $i); // 2 MB > 1 MB
dsm_cache_prune_($dir); $sum = 0; foreach (glob("$dir/*.json") as $f) $sum += filesize($f);
t('lewat batas ukuran: total <= 80% batas', $sum <= 0.8 * 1048576 && $sum > 0);
t('lewat batas ukuran: yang tersisa paling baru', is_file("$dir/b9.json") && !is_file("$dir/b0.json"));
// 5) get/put normal
dsm_cache_put_('k1', '{"ok":true}'); t('put lalu get mengembalikan isi', dsm_cache_get_('k1') === '{"ok":true}');
t('kunci null aman', dsm_cache_get_(null) === null);
array_map('unlink', glob("$dir/*")); @rmdir($dir); @rmdir(DSM_STORAGE);
echo "\nHasil: $pass lolos, $fail gagal\n"; exit($fail ? 1 : 0);
