<?php
/** Uji Aturan Status Dealer dengan data buatan (tanpa database). Jalankan: php tools/tests/dealer_status_test.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
define('DSM_STORAGE', sys_get_temp_dir() . '/dsm_ds_test');
require_once $root . '/lib/common.php'; require_once $root . '/lib/dealer_status.php';
function setCfg($c) { $GLOBALS['DSM_DSCFG'] = dsClean_($c) + ['by' => '', 'at' => '']; }
setCfg(dsDefault_());
array_map('unlink', glob(storage_dir_('status') . '/*') ?: []);
$pass = 0; $fail = 0;
function t($n, $f) { global $pass, $fail; try { $f(); $pass++; echo "  ✓ $n\n"; } catch (Throwable $e) { $fail++; echo "  ✗ $n — " . $e->getMessage() . "\n"; } }
function eq($a, $b, $m = '') { if ($a !== $b) throw new Exception($m . ' expected ' . json_encode($b) . ' got ' . json_encode($a)); }

// Data: periode 2026-11; pola Mei–Okt. Baris per (dealer, brand, bulan, qty).
$P = '2026-11'; $rows = [];
$add = function ($cu, $b, $m, $q, $type = 'T1', $day = '10') use (&$rows) { $rows[$m][] = ['customer' => $cu, 'brand' => $b, 'date' => "$m-$day", 'q' => $q, 'a' => 0, 'acc' => 0, 'docs' => ["$cu$m$b"], 'type' => $type, 'nik' => 'S1', 'aq' => 0]; };
$M = ['2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10'];
// D1: order tiap bulan 2 unit -> AKTIF
foreach ($M as $m) $add('D1', 'ITEL', $m, 2);
// D2: Okt 1 unit -> DI BAWAH MIN
$add('D2', 'ITEL', '2026-10', 1);
// D3: Jul, Agu order, Sep-Okt kosong -> TURUN
$add('D3', 'ITEL', '2026-07', 3); $add('D3', 'ITEL', '2026-08', 2);
// D4: Mei order, Jul-Sep kosong, Okt order -> BANGKIT
$add('D4', 'ITEL', '2026-05', 2); $add('D4', 'ITEL', '2026-10', 2);
// D5: Sep order, Okt kosong -> IDLE
$add('D5', 'ITEL', '2026-09', 2);
// D6: Jun order saja -> PASIF
$add('D6', 'ITEL', '2026-06', 2);
// D7: order terakhir 2025 (sebelum jendela) -> NONAKTIF
$first = ['["D7","ITEL"]' => ['date' => '2025-12-02', 'nik' => 'S1']];
// D8: total vs per brand: Okt 1 ITEL + 1 TECNO -> per brand DI BAWAH MIN, total AKTIF (2 unit)
$add('D8', 'ITEL', '2026-10', 1); $add('D8', 'TECNO', '2026-10', 1);
// bulan berjalan
$add('D1', 'ITEL', $P, 3); $add('D5', 'ITEL', $P, 1); $add('D6', 'ITEL', $P, 2); $add('D9', 'ITEL', $P, 2); // D9 = NOO
$add('D8', 'TECNO', $P, 2, 'T9');
foreach ($rows as $m => $rs) foreach ($rs as $r) { $k = jkey([$r['customer'], $r['brand']]); if (!isset($first[$k]) || strcmp($r['date'], $first[$k]['date']) < 0) $first[$k] = ['date' => $r['date'], 'nik' => 'S1']; }
$cov = []; foreach (array_merge($M, [$P]) as $m) $cov[$m] = ['cutoff' => $m === $P ? "$m-16" : PortalCore::end($m), 'closed' => $m !== $P];
$pol = fn($b, $min, $types = [], $must = []) => ['period' => $P, 'region' => 'R', 'group' => 'G', 'metric' => 'DA', 'brand' => $b, 'minDA' => $min, 'types' => $types, 'must' => $must, 'perProm' => 0];
$db = ['_vid' => 'T1', 'cubes' => $rows, 'coverage' => $cov, 'first' => $first, 'meta' => ['policies' => [$pol('ITEL', 3), $pol('TECNO', 2, ['T9'])], 'products' => []]];

echo "== Status terkunci (pola Mei–Okt) ==\n";
$exp = ['D1' => 'AKTIF', 'D2' => 'DI BAWAH MIN', 'D3' => 'TURUN', 'D4' => 'BANGKIT', 'D5' => 'IDLE', 'D6' => 'PASIF', 'D7' => 'NONAKTIF'];
foreach ($exp as $d => $s) t("$d = $s", fn() => eq(dsDealer_($db, $P, $d)['bs']['ITEL'], $s));
t('D8 per brand ITEL = DI BAWAH MIN, total = AKTIF', function () use ($db, $P) { $x = dsDealer_($db, $P, 'D8'); eq($x['bs']['ITEL'], 'DI BAWAH MIN'); eq($x['status'], 'AKTIF'); });
t('D9 belum pernah order sebelum Nov: total BELUM ORDER, brand NOO', function () use ($db, $P) { $x = dsDealer_($db, $P, 'D9'); eq($x['status'], 'BELUM ORDER'); eq($x['bs']['ITEL'], 'NOO'); });
t('status total = status brand untuk dealer 1 brand', fn() => eq(dsDealer_($db, $P, 'D3')['status'], 'TURUN'));
t('dealer tak dikenal = BELUM ORDER', fn() => eq(dsDealer_($db, $P, 'ZZ')['status'], 'BELUM ORDER'));

echo "== Pengecualian brand & aturan sendiri ==\n";
t('ITEL minimal 3 unit: D1 (2 unit/bulan) jadi DI BAWAH MIN', function () use ($db, $P) {
  setCfg(['brands' => ['ITEL' => ['min' => 3]]] + dsDefault_());
  $db2 = $db; $db2['_vid'] = 'T2'; eq(dsDealer_($db2, $P, 'D1')['bs']['ITEL'], 'DI BAWAH MIN'); eq(dsDealer_($db2, $P, 'D1')['status'], 'AKTIF', 'total tetap pakai min umum');
  setCfg(dsDefault_());
});
t('status baru buatan sendiri + aturan', function () use ($db, $P) {
  $c = dsDefault_(); $c['extra'] = ['SETIA']; array_unshift($c['rules'], [['y', 'y', 'y', 'y', 'y', 'y'], 'SETIA']);
  setCfg($c); $db2 = $db; $db2['_vid'] = 'T3';
  eq(dsDealer_($db2, $P, 'D1')['status'], 'SETIA'); setCfg(dsDefault_());
});
t('validasi: nama status terlalu panjang ditolak', function () { try { dsClean_(['min' => 2, 'rules' => dsDefault_()['rules'], 'extra' => ['NAMASANGATPANJANGSEKALI']]); throw new Exception('lolos'); } catch (Throwable $e) { if ($e->getMessage() === 'lolos') throw $e; } });
t('validasi: status tak dikenal ditolak', function () { try { dsClean_(['min' => 2, 'rules' => [[['a','a','a','a','a','a'], 'XYZ']]]); throw new Exception('lolos'); } catch (Throwable $e) { if ($e->getMessage() === 'lolos') throw $e; } });
t('tidak cocok aturan mana pun = LAINNYA', fn() => eq(dsMatch_([[['y','y','y','y','y','y'], 'AKTIF']], ['n','n','n','n','n','y'])[0], 'LAINNYA'));

echo "== Penanda bulan berjalan = DA KPI ==\n";
foreach (['ITEL' => 3, 'TECNO' => 2] as $b => $min) t("jumlah dealer DA $b = PortalCore::metrics()", function () use ($db, $P, $b, $min) {
  $types = $b === 'TECNO' ? ['T9'] : [];
  $kpiRows = array_values(array_filter($db['cubes'][$P], fn($r) => !$types || in_array($r['type'], $types, true)));
  $kpi = PortalCore::metrics($kpiRows, $db, $P, $b, $min)['da'];
  $mine = 0; foreach (dsCurrent_($db, $P)['brand'] as $bs) if (!empty($bs[$b]['da'])) $mine++;
  eq($mine, $kpi);
});
t('D5 1 unit Nov ITEL (min DA 3) = sudah order tapi belum DA', function () use ($db, $P) { $c = dsCurrent_($db, $P)['brand']['D5']['ITEL']; eq($c['q'], 1); eq($c['da'], false); });
t('D6 PASIF order Nov = bangkit bulan ini', fn() => eq(dsDealer_($db, $P, 'D6')['bangkit'], true));
t('ringkasan TOTAL', function () use ($db, $P) { $s = dsSummary_($db, $P, ['D1','D2','D3','D4','D5','D6','D7','D8','D9']); echo '    ', json_encode(array_map(fn($x) => $x['n'], array_filter($s['by'], fn($x) => $x['n']))), ' order=', $s['order'], ' bangkit=', $s['bangkit'], "\n"; eq($s['by']['AKTIF']['n'], 2); eq($s['order'], 5); eq($s['bangkit'], 2); });

echo "== Kunci bulanan ==\n";
array_map('unlink', glob(storage_dir_('status') . '/*') ?: []); setCfg(dsDefault_()); $db['_vid'] = 'L1';
t('bulan Okt ditutup -> hasil dikunci ke file', function () use ($db, $P) { $L = dsLocked_($db, $P); eq($L['locked'], true); eq(is_file(storage_dir_('status') . "/$P.json"), true); });
t('data Okt berubah setelah dikunci -> status tidak berubah', function () use ($db, $P) {
  $db2 = $db; $db2['_vid'] = 'T9'; $db2['cubes']['2026-10'] = []; eq(dsDealer_($db2, $P, 'D2')['bs']['ITEL'], 'DI BAWAH MIN');
});
t('bulan sebelumnya belum ditutup -> sementara, tidak ditulis', function () use ($db) {
  $db2 = $db; $db2['_vid'] = 'T8'; $db2['coverage']['2026-11']['closed'] = false; $db2['coverage']['2026-12'] = ['cutoff' => '2026-12-03', 'closed' => false];
  $L = dsLocked_($db2, '2026-12'); eq($L['locked'], false); eq(is_file(storage_dir_('status') . '/2026-12.json'), false);
});
echo "\nHasil: $pass lolos, $fail gagal\n"; exit($fail ? 1 : 0);
