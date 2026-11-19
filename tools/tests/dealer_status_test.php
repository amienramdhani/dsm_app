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

// Data: periode 2026-11; jendela Agu–Okt; basis 2026-01.
$P = '2026-11'; $rows = [];
$add = function ($cu, $b, $m, $q, $type = 'T1', $day = '10') use (&$rows) { $rows[$m][] = ['customer' => $cu, 'brand' => $b, 'date' => "$m-$day", 'q' => $q, 'a' => 0, 'acc' => 0, 'docs' => ["$cu$m$b"], 'type' => $type, 'nik' => 'S1', 'aq' => 0]; };
$M = ['2026-01','2026-02','2026-03','2026-04','2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10'];
// D1: order Agu, Okt -> ACTIVE (2 dari 3)
$add('D1', 'ITEL', '2026-08', 2); $add('D1', 'ITEL', '2026-10', 5);
// D2: order Okt saja 1 unit -> PASSIVE
$add('D2', 'ITEL', '2026-10', 1);
// D3: terakhir order Jul (Agu-Okt kosong = 3 bln) -> IDLE
$add('D3', 'ITEL', '2026-07', 3);
// D4: terakhir order Mei (Jun-Okt = 5 bln) -> IDLE
$add('D4', 'ITEL', '2026-05', 2);
// D5: terakhir order Apr (6 bln) -> INACTIVE
$add('D5', 'ITEL', '2026-04', 2);
// D6: order 2025 saja -> PROSPECT (basis 2026)
$add('D6', 'ITEL', '2025-12', 2);
// D7: banyak invoice di 1 bulan = 1 bulan; Okt 3 baris -> PASSIVE
$add('D7', 'ITEL', '2026-10', 1); $add('D7', 'ITEL', '2026-10', 1, 'T1', '20'); $add('D7', 'ITEL', '2026-10', 1, 'T1', '25');
// D8: retur bikin qty bulan Sep = 0 -> bulan Sep tidak dihitung; Okt order -> PASSIVE
$add('D8', 'ITEL', '2026-09', 2); $add('D8', 'ITEL', '2026-09', -2); $add('D8', 'ITEL', '2026-10', 1);
// D10: ITEL Agu + TECNO Sep -> per brand PASSIVE, total ACTIVE
$add('D10', 'ITEL', '2026-08', 1); $add('D10', 'TECNO', '2026-09', 1);
// D11: HONOR saja (dimatikan) -> brand PASSIVE, total PROSPECT
$add('D11', 'HONOR', '2026-10', 3);
// bulan berjalan
$add('D3', 'ITEL', $P, 2); $add('D9', 'ITEL', $P, 2); // D9 = NOO
$add('D10', 'TECNO', $P, 2, 'T9'); $add('D5', 'ITEL', $P, 1);
$first = [];
foreach ($rows as $m => $rs) foreach ($rs as $r) { $k = jkey([$r['customer'], $r['brand']]); if (!isset($first[$k]) || strcmp($r['date'], $first[$k]['date']) < 0) $first[$k] = ['date' => $r['date'], 'nik' => 'S1']; }
$cov = []; foreach (array_merge($M, [$P]) as $m) $cov[$m] = ['cutoff' => $m === $P ? "$m-16" : PortalCore::end($m), 'closed' => $m !== $P];
$pol = fn($b, $min, $types = [], $must = []) => ['period' => $P, 'region' => 'R', 'group' => 'G', 'metric' => 'DA', 'brand' => $b, 'minDA' => $min, 'types' => $types, 'must' => $must, 'perProm' => 0];
$db = ['_vid' => 'T1', 'cubes' => $rows, 'coverage' => $cov, 'first' => $first, 'meta' => ['policies' => [$pol('ITEL', 3), $pol('TECNO', 2, ['T9'])], 'products' => []]];

echo "== Status terkunci (jendela Agu–Okt) ==\n";
$exp = ['D1' => 'ACTIVE', 'D2' => 'PASSIVE', 'D3' => 'IDLE', 'D4' => 'IDLE', 'D5' => 'INACTIVE', 'D7' => 'PASSIVE', 'D8' => 'PASSIVE'];
foreach ($exp as $d => $s) t("$d = $s", fn() => eq(dsDealer_($db, $P, $d)['bs']['ITEL'] ?? null, $s));
t('D6 order hanya 2025 = PROSPECT', fn() => eq(dsDealer_($db, $P, 'D6')['status'], 'PROSPECT'));
t('D10 per brand PASSIVE, total ACTIVE', function () use ($db, $P) { $x = dsDealer_($db, $P, 'D10'); eq($x['bs']['ITEL'], 'PASSIVE'); eq($x['bs']['TECNO'], 'PASSIVE'); eq($x['status'], 'ACTIVE'); });
t('D11 HONOR dimatikan: brand PASSIVE, total PROSPECT', function () use ($db, $P) { $x = dsDealer_($db, $P, 'D11'); eq($x['bs']['HONOR'], 'PASSIVE'); eq($x['status'], 'PROSPECT'); });
t('D9 belum pernah order: PROSPECT + NOO bulan ini', function () use ($db, $P) { $x = dsDealer_($db, $P, 'D9'); eq($x['status'], 'PROSPECT'); eq($x['noo'], true); eq($x['bs']['ITEL'], 'PROSPECT'); });
t('dealer tak dikenal = PROSPECT', fn() => eq(dsDealer_($db, $P, 'ZZ')['status'], 'PROSPECT'));
t('pola jendela D1 = yny', fn() => eq(dsDealer_($db, $P, 'D1')['pola'], 'yny'));

echo "== Ubah aturan ==\n";
t('INACTIVE mulai 5 bulan: D4 jadi INACTIVE', function () use ($db, $P) { setCfg(['inact' => 5] + dsDefault_()); $db2 = $db; $db2['_vid'] = 'T2'; eq(dsDealer_($db2, $P, 'D4')['bs']['ITEL'], 'INACTIVE'); setCfg(dsDefault_()); });
t('ACTIVE minimal 1 bulan: D2 jadi ACTIVE', function () use ($db, $P) { setCfg(['act' => 1] + dsDefault_()); $db2 = $db; $db2['_vid'] = 'T3'; eq(dsDealer_($db2, $P, 'D2')['bs']['ITEL'], 'ACTIVE'); setCfg(dsDefault_()); });
t('validasi: INACTIVE tidak boleh ≤ jendela', function () { try { dsClean_(['inact' => 3] + dsDefault_()); throw new Exception('lolos'); } catch (Throwable $e) { if ($e->getMessage() === 'lolos') throw $e; } });
t('validasi: ACTIVE tidak boleh > jendela', function () { try { dsClean_(['act' => 4] + dsDefault_()); throw new Exception('lolos'); } catch (Throwable $e) { if ($e->getMessage() === 'lolos') throw $e; } });

echo "== Penanda bulan berjalan = DA KPI ==\n";
foreach (['ITEL' => 3, 'TECNO' => 2] as $b => $min) t("jumlah dealer DA $b = PortalCore::metrics()", function () use ($db, $P, $b, $min) {
  $types = $b === 'TECNO' ? ['T9'] : [];
  $kpiRows = array_values(array_filter($db['cubes'][$P], fn($r) => !$types || in_array($r['type'], $types, true)));
  $kpi = PortalCore::metrics($kpiRows, $db, $P, $b, $min)['da'];
  $mine = 0; foreach (dsCurrent_($db, $P)['brand'] as $bs) if (!empty($bs[$b]['da'])) $mine++;
  eq($mine, $kpi);
});
t('D5 1 unit Nov ITEL (min DA 3) = sudah order tapi belum DA', function () use ($db, $P) { $c = dsCurrent_($db, $P)['brand']['D5']['ITEL']; eq($c['q'], 1); eq($c['da'], false); });
t('D3 IDLE order Nov = bangkit bulan ini', fn() => eq(dsDealer_($db, $P, 'D3')['bangkit'], true));
t('ringkasan per brand TECNO: yang belum pernah ambil = PROSPECT', function () use ($db, $P) { $s = dsSummary_($db, $P, ['D1','D2','D10'], 'TECNO'); eq($s['by']['PROSPECT']['n'], 2); eq($s['by']['PASSIVE']['n'], 1); });
t('ringkasan TOTAL', function () use ($db, $P) { $s = dsSummary_($db, $P, ['D1','D2','D3','D4','D5','D6','D7','D8','D9','D10','D11']); echo '    ', json_encode(array_map(fn($x) => $x['n'], array_filter($s['by'], fn($x) => $x['n']))), ' order=', $s['order'], ' bangkit=', $s['bangkit'], "\n"; eq($s['by']['ACTIVE']['n'], 2); eq($s['by']['PROSPECT']['n'], 3); eq($s['order'], 4); eq($s['bangkit'], 2); });

echo "== Kunci bulanan ==\n";
array_map('unlink', glob(storage_dir_('status') . '/*') ?: []); setCfg(dsDefault_()); $db['_vid'] = 'L1';
t('bulan Okt ditutup -> hasil dikunci ke file', function () use ($db, $P) { $L = dsLocked_($db, $P); eq($L['locked'], true); eq(is_file(storage_dir_('status') . "/$P.json"), true); });
t('data Okt berubah setelah dikunci -> status tidak berubah', function () use ($db, $P) {
  $db2 = $db; $db2['_vid'] = 'T9'; $db2['cubes']['2026-10'] = []; eq(dsDealer_($db2, $P, 'D2')['bs']['ITEL'], 'PASSIVE');
});
t('bulan sebelumnya belum ditutup -> sementara, tidak ditulis', function () use ($db) {
  $db2 = $db; $db2['_vid'] = 'T8'; $db2['coverage']['2026-11']['closed'] = false; $db2['coverage']['2026-12'] = ['cutoff' => '2026-12-03', 'closed' => false];
  $L = dsLocked_($db2, '2026-12'); eq($L['locked'], false); eq(is_file(storage_dir_('status') . '/2026-12.json'), false);
});
echo "== PROSPECT: dealer tutup / diajukan tutup dikecualikan (2026-11-19) ==\n";
t('PROSPECT dihitung langsung dari dealer yang ikut dihitung', function () {
  // raw: X1 (ACTIVE, ITEL), X2 (IDLE, ITEL & TECNO). Master: X1, X2, X3, X4, X5. X2 & X5 dikecualikan (tutup / diajukan tutup).
  $raw = ['total' => ['X1' => ['ACTIVE', 'yyy', 0], 'X2' => ['IDLE', 'ynn', 3]], 'brand' => ['X1' => ['ITEL' => ['ACTIVE', 'yyy', 0]], 'X2' => ['ITEL' => ['IDLE', 'ynn', 3], 'TECNO' => ['IDLE', 'ynn', 3]]]];
  $elig = ['X1' => 1, 'X3' => 1, 'X4' => 1];
  $c = dsCount_($raw, DS_BASE, $elig);
  eq($c['total'][DS_NEVER], 2, 'PROSPECT total (X3, X4)');
  eq($c['brand']['ITEL'][DS_NEVER], 2, 'PROSPECT ITEL (X3, X4)'); eq($c['brand']['TECNO'][DS_NEVER], 3, 'PROSPECT TECNO (X1, X3, X4)');
  eq($c['total']['IDLE'], 1, 'status lain tetap dihitung apa adanya');
  // cara lama (angka) tetap bisa dipakai
  eq(dsCount_($raw, DS_BASE, 5)['total'][DS_NEVER], 3);
});
t('dealerPendingClose_: hanya baris terakhir PENGAJUAN', function () {
  $GLOBALS['__dtRowsTest'] = [['id' => 'A', 'status' => 'PENGAJUAN'], ['id' => 'B', 'status' => 'PENGAJUAN'], ['id' => 'B', 'status' => 'DITOLAK'], ['id' => 'C', 'status' => 'TUTUP']];
  $last = []; foreach ($GLOBALS['__dtRowsTest'] as $x) $last[$x['id']] = $x['status'];
  eq(array_keys(array_filter($last, fn($st) => $st === 'PENGAJUAN')), ['A']);
  if (!str_contains(file_get_contents(dirname(__DIR__, 2) . '/lib/dealer.php'), "foreach (dtRows_() as \$x) \$last[\$x['id']] = \$x['status']; } catch (Throwable \$e) {} return array_filter(\$last, fn(\$st) => \$st === 'PENGAJUAN')")) throw new Exception('implementasi berbeda dari yang diuji');
});
t('halaman Dealer (JS): diajukan tutup tidak masuk PROSPECT', function () {
  $js = file_get_contents(dirname(__DIR__, 2) . '/assets/dealer_status.js');
  if (!str_contains($js, "if (s === 'PROSPECT' && typeof V6TUTUP !== 'undefined' && V6TUTUP && V6TUTUP.pend && V6TUTUP.pend[c.id]) return null;")) throw new Exception('tidak ada');
});

echo "== File kunci format lama (sebelum Paket 3) dihitung ulang (2026-11-19) ==\n";
t('dsLockOk_: hanya status versi sekarang', function () {
  eq(dsLockOk_(['total' => ['A' => ['ACTIVE', 'yyy', 0]], 'brand' => ['A' => ['ITEL' => ['IDLE', 'nny', 2]]]]), true);
  eq(dsLockOk_(['total' => ['A' => ['AKTIF', 'yyy', 0]], 'brand' => []]), false, 'label lama total');
  eq(dsLockOk_(['total' => ['A' => ['ACTIVE', 'yyy', 0]], 'brand' => ['A' => ['ITEL' => ['TURUN', 'yyn', 1]]]]), false, 'label lama per brand');
  eq(dsLockOk_(['total' => [], 'brand' => []]), true, 'kosong boleh'); eq(dsLockOk_([]), false, 'rusak');
});
t('periode lampau dengan file kunci label lama: memakai rumus sekarang', function () use ($P, $rows) {
  $db = ['id' => 'SNAP-OLD', 'cubes' => $rows, 'coverage' => ['2026-09' => ['closed' => true], '2026-10' => ['closed' => true], '2026-11' => ['closed' => false]]];
  $Pp = '2026-10'; $f = storage_dir_('status') . '/' . $Pp . '.json';
  file_put_contents($f, json_encode(['total' => ['D1' => ['AKTIF', 'yyy', 0]], 'brand' => ['D1' => ['ITEL' => ['TURUN', 'yyn', 1]]], 'months' => [], 'hash' => 'lama']));
  $L = dsLocked_($db, $Pp);
  foreach ($L['total'] as $v) if (!in_array($v[0], DS_BASE, true)) throw new Exception('masih label lama: ' . $v[0]);
  $j = json_decode(file_get_contents($f), true); eq(dsLockOk_($j), true, 'file ditulis ulang dengan format baru');
  @unlink($f);
});

echo "\n== Pola 12 bulan (tab STATUS HP) ==\n";
t('P12 bulan = Nov 2025..Okt 2026', fn() => eq(implode(',', [dsP12_($db, $P, 'D1')['months'][0], dsP12_($db, $P, 'D1')['months'][11]]), '2025-11,2026-10'));
t('P12 D10 total & per brand', function () use ($db, $P) { $x = dsP12_($db, $P, 'D10'); eq($x['total'], 'bbnnnnnnnyyn'); eq($x['brand']['ITEL'], 'bbnnnnnnnynn'); eq($x['brand']['TECNO'], 'bbnnnnnnnnyn'); });
t('P12 D8 retur Sep (net 0) bukan order', fn() => eq(dsP12_($db, $P, 'D8')['brand']['ITEL'], 'bbnnnnnnnnny'));
t('P12 D11 brand dimatikan tidak masuk TOTAL', fn() => eq(dsP12_($db, $P, 'D11')['total'], 'bbnnnnnnnnnn'));
t('P12 D6 order 2025 = sebelum basis', fn() => eq(dsP12_($db, $P, 'D6')['total'], 'bbnnnnnnnnnn'));

echo "\nHasil: $pass lolos, $fail gagal\n"; exit($fail ? 1 : 0);
