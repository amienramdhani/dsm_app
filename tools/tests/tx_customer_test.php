<?php
/** Uji rincian transaksi satu dealer: custCodesFor_ & dataRowsCustomer_ (lib/store.php) = cara lama (baca bulan penuh lalu saring).
 *  Tanpa MySQL: tabel partisi ditiru di SQLite. Jalankan: php tools/tests/tx_customer_test.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
require $root . '/lib/js.php';
$GLOBALS['pdo'] = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$GLOBALS['pdo']->exec('CREATE TABLE dsm_part (id INTEGER PRIMARY KEY AUTOINCREMENT, jenis TEXT, periode TEXT, region TEXT, baris INT, created_ms INT)');
$GLOBALS['pdo']->exec('CREATE TABLE dsm_st (part_id INT, seq INT, tanggal TEXT, badan_usaha TEXT, no_transaksi TEXT, no_sj TEXT, kode_produk TEXT, kode_customer TEXT, nik_sales TEXT, qty INT, amount REAL, id_baris TEXT, ref_transaksi TEXT, region TEXT)');
$GLOBALS['pdo']->exec('CREATE TABLE dsm_so (part_id INT, seq INT, tanggal TEXT, kode_customer TEXT, kode_produk TEXT, qty INT, region TEXT)');
function db_() { return $GLOBALS['pdo']; }
function file_path_($id) { return sys_get_temp_dir() . '/' . $id . '.json'; }
$src = preg_replace('/function store_schema_\(\): void \{.*?\n\}\n/s', "function store_schema_(): void {}\n", file_get_contents($root . '/lib/store.php'), 1);
$tmp = tempnam(sys_get_temp_dir(), 'st') . '.php'; file_put_contents($tmp, $src); require $tmp; unlink($tmp);
// custMap_/custInduk_/custCodesFor_ asli dari publish.php (diambil potongannya saja, tanpa memuat seluruh aplikasi)
$pub = file_get_contents($root . '/lib/publish.php');
$a = strpos($pub, 'function custMap_('); $b = strpos($pub, 'function custResolveRows_(');
$tmp = tempnam(sys_get_temp_dir(), 'cm') . '.php'; file_put_contents($tmp, "<?php\n" . substr($pub, $a, $b - $a)); require $tmp; unlink($tmp);
$pass = 0; $fail = 0; function t($n, $ok) { global $pass, $fail; $ok ? $pass++ : $fail++; echo ($ok ? '  ✓ ' : '  ✗ ') . $n . "\n"; }
mt_srand(11);
// master: induk C0..C59 dengan alias, log kode induk lama->baru (termasuk rantai A->B->C), kode angka
$cu = []; for ($i = 0; $i < 60; $i++) $cu["C$i"] = ['id' => "C$i", 'aliases' => [mt_rand(0, 2) ? "U$i" : '', mt_rand(0, 3) ? '' : "V$i"]];
$cu['1001'] = ['id' => '1001', 'aliases' => ['0099']];
$log = [['lama' => 'OLD1', 'baru' => 'C1'], ['lama' => 'OLD2', 'baru' => 'OLD3'], ['lama' => 'OLD3', 'baru' => 'C2'], ['lama' => 'X9', 'baru' => 'GONE']];
$meta = ['customers' => $cu, 'indukLog' => $log];
$universe = array_merge(array_keys($cu), ['U1', 'U2', 'V3', 'OLD1', 'OLD2', 'OLD3', 'X9', 'GONE', 'ASING', '0099', '1001']);
for ($i = 0; $i < 60; $i++) { $universe[] = "U$i"; $universe[] = "V$i"; }
$universe = array_values(array_unique(array_map('strval', $universe)));
// partisi: 3 region x 2 bulan, baris acak memakai semua kode
$parts = []; foreach (['JABAR', 'JATENG', 'JATIM'] as $r) {
  $rows = []; for ($i = 0; $i < 1500; $i++) $rows[] = ['date' => '2026-10-' . sprintf('%02d', 1 + $i % 28), 'company' => 'CS', 'doc' => 'INV' . $i, 'sj' => '', 'sku' => 'P' . ($i % 9), 'customer' => $universe[mt_rand(0, count($universe) - 1)], 'nik' => 'S1', 'qty' => 1 + $i % 3, 'amount' => 1000.5 * ($i % 4), 'line' => '', 'ref' => '', 'region' => $r];
  $parts[] = partWrite_('ST', '2026-10', $r, $rows);
}
$ptr = monthPointer_(array_map(fn($p) => ['file' => $p], $parts));
$old = dataRows_($ptr);
$cases = 0; $bad = [];
foreach (array_merge($universe, ['TIDAK-ADA']) as $c) {
  $target = custInduk_($meta, $c);
  $expect = []; foreach ($universe as $x) if ($x === $c || custInduk_($meta, $x) === $target) $expect[$x] = 1; // definisi cara lama
  $codes = custCodesFor_($meta, $c);
  $miss = array_diff(array_keys($expect), $codes);
  // baris: cara lama = saring bulan penuh dengan predikat apiTransactions; cara baru = SQL per kode
  $want = array_values(array_filter($old, fn($t) => $t['customer'] === $c || custInduk_($meta, $t['customer']) === custInduk_($meta, $c)));
  $got = array_values(array_filter(dataRowsCustomer_($ptr, $codes), fn($t) => $t['customer'] === $c || custInduk_($meta, $t['customer']) === custInduk_($meta, $c)));
  $cases++; if ($miss || $got !== $want) $bad[] = "$c miss=" . implode(',', $miss) . ' rows ' . count($got) . '/' . count($want);
}
t("kode dealer lengkap & baris identik (urutan + isi) untuk $cases kode (induk, alias, kode lama, rantai, angka, tak dikenal)", !$bad); foreach (array_slice($bad, 0, 5) as $d) echo "      $d\n";
$codes = custCodesFor_($meta, 'OLD2'); sort($codes); t('rantai kode lama OLD2 -> OLD3 -> C2 ikut', in_array('C2', $codes, true) && in_array('OLD3', $codes, true));
t('alias angka 0099 -> 1001', in_array('0099', custCodesFor_($meta, '1001'), true));
t('kode kosong -> tidak ada baris', dataRowsCustomer_($ptr, custCodesFor_($meta, '')) === []);
echo "\nHasil: $pass lolos, $fail gagal\n"; exit($fail ? 1 : 0);
