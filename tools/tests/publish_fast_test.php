<?php
/** Uji tanda atribusi untuk jalur simpan cepat. Jalankan: php tools/tests/publish_fast_test.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
define('DSM_STORAGE', sys_get_temp_dir() . '/dsm_pf_test');
require_once $root . '/lib/common.php'; require_once $root . '/lib/publish.php';
$pass = 0; $fail = 0;
function t($n, $ok) { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ $n\n"; } else { $fail++; echo "  ✗ $n\n"; } }
$base = ['customers' => ['D1' => ['id' => 'D1', 'nama' => 'TOKO A', 'nik' => 'S1', 'region' => 'JABAR', 'hp' => '0812', 'alamat' => 'Jl. A', 'pic' => 'Budi', 'kota' => 'CIREBON']],
  'people' => [['nik' => 'S1', 'nama' => 'Sales 1', 'role' => 'SALES', 'region' => 'JABAR']], 'products' => ['P1' => ['sku' => 'P1', 'brand' => 'ITEL', 'device' => true]],
  'policies' => [['metric' => 'ST', 'target' => 10]], 'targets' => [['nik' => 'S1', 'target' => 100]], 'nikMerge' => [], 'regionList' => ['JABAR']];
$sig = fn($m) => publishAttrSig_($m); $b = $sig($base);
$m = $base; $m['policies'][0]['target'] = 99; $m['targets'][0]['target'] = 5; t('ubah KPI / target = jalur cepat', $sig($m) === $b);
$m = $base; $m['customers']['D1']['hp'] = '0899'; $m['customers']['D1']['alamat'] = 'Jl. B'; $m['customers']['D1']['pic'] = 'Ani'; t('ubah kontak dealer = jalur cepat', $sig($m) === $b);
$m = $base; $m['people'][0]['adminRegions'] = ['JABAR']; t('ubah akses admin = jalur cepat', $sig($m) === $b);
$m = $base; $m['customers']['D1']['nik'] = 'S2'; t('ganti sales dealer = publikasi penuh', $sig($m) !== $b);
$m = $base; $m['customers']['D1']['nama'] = 'TOKO B'; t('ganti nama dealer = publikasi penuh (nama ikut data stok)', $sig($m) !== $b);
$m = $base; $m['people'][0]['region'] = 'JATIM'; t('ganti region sales = publikasi penuh', $sig($m) !== $b);
$m = $base; $m['products']['P1']['brand'] = 'TECNO'; t('ganti brand produk = publikasi penuh', $sig($m) !== $b);
$m = $base; $m['customers']['D2'] = ['id' => 'D2', 'nik' => 'S1']; t('tambah dealer = publikasi penuh', $sig($m) !== $b);
t('tanpa izin pemanggil = publikasi penuh', publishFastOk_(['id' => 'X'], $base, [], [], null, null) === false);
echo "\nHasil: $pass lolos, $fail gagal\n"; exit($fail ? 1 : 0);
