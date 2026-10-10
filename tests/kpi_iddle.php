<?php
// Jalankan dari root aplikasi: php tests/kpi_iddle.php
// Murni data dummy; tidak membaca/menulis database atau sesi akun.
require_once __DIR__ . '/../lib/region.php';

$checks = 0;
function same($actual, $expected, string $name): void {
    global $checks;
    if ($actual !== $expected) throw new RuntimeException($name . ': actual=' . var_export($actual, true) . ', expected=' . var_export($expected, true));
    $checks++;
}
function fixture(array $orders, string $period = '2026-10'): array {
    static $id = 0;
    $db = ['id' => 'test-' . ++$id, 'meta' => ['people' => [['nik' => 'S1', 'role' => 'SALES', 'sales' => true, 'region' => 'SUB', 'wilayah' => 'REG']], 'policies' => [], 'targets' => [], 'customers' => []], 'cubes' => [], 'coverage' => [], 'first' => [], 'unlinked' => []];
    $month = $period;
    for ($i = 0; $i < 5; $i++) { $db['coverage'][$month] = ['cutoff' => PortalCore::end($month), 'closed' => true]; $db['cubes'][$month] = []; $month = PortalCore::prev($month); }
    foreach ($orders as $o) {
        [$month, $customer, $brand, $qty] = $o;
        $db['cubes'][$month][] = ['date' => $month . '-' . ($o[6] ?? '15'), 'customer' => $customer, 'brand' => $brand, 'q' => $qty, 'nik' => $o[4] ?? 'S1', 'region' => $o[5] ?? 'REG', 'type' => 'TYPE', 'a' => $qty * 100, 'acc' => 0, 'docs' => ['DOC' . count($db['cubes'][$month])]];
    }
    PortalCore::resetMemo($db['id']);
    return $db;
}
function countIddle(array $orders, $expected, string $name, string $brand = 'ITEL', string $period = '2026-10'): void {
    $db = fixture($orders, $period);
    same(PortalCore::dealerIddle(PortalCore::slice($db, $period, ['S1']), $db, $period, $brand), $expected, $name);
}
countIddle([['2026-07','A','ITEL',1],['2026-10','A','ITEL',1]], 1, 'Tepat dua bulan kosong dihitung');
countIddle([['2026-06','A','ITEL',2],['2026-10','A','ITEL',1]], 0, 'Juli Agustus September kosong = pasif');
countIddle([['2026-08','A','ITEL',2],['2026-10','A','ITEL',1]], 0, 'Satu bulan kosong tidak dihitung');
countIddle([['2026-07','A','ITEL',2],['2026-09','A','ITEL',1],['2026-10','A','ITEL',1]], 0, 'Bulan lalu order tidak dihitung');
countIddle([['2026-07','A','ITEL',2],['2026-08','A','ITEL',1],['2026-10','A','ITEL',1]], 0, 'Order di bulan jeda pertama tidak dihitung');
countIddle([['2026-10','A','ITEL',1]], 0, 'Dealer baru bukan IDDLE');
countIddle([['2026-07','A','ITEL',2]], 0, 'Belum order bulan ini');
countIddle([['2026-07','A','ITEL',2],['2026-10','A','ITEL',1],['2026-10','A','ITEL',3]], 1, 'Order berulang tetap satu dealer-brand');
countIddle([['2026-07','A','ITEL',2],['2026-08','A','TECNO',1],['2026-09','A','TECNO',1],['2026-10','A','ITEL',1]], 1, 'Order brand lain tidak memutus jeda ITEL');
countIddle([['2026-07','A','TECNO',2],['2026-10','A','ITEL',1]], 0, 'Histori brand lain bukan histori ITEL');
countIddle([['2026-07','A','ITEL',2],['2026-07','A','TECNO',1],['2026-10','A','ITEL',1],['2026-10','A','TECNO',1]], 2, 'ALL menjumlahkan pasangan dealer-brand', 'ALL');
countIddle([['2026-07','A','ITEL',2,'S2'],['2026-10','A','ITEL',1]], 1, 'Ganti Sales tidak menghilangkan histori');
countIddle([['2026-07','A','ITEL',2],['2026-10','A','ITEL',1,'S2']], 0, 'Aktual tetap sesuai cakupan Sales');
countIddle([['2026-07','A','ITEL',2],['2026-10','A','ITEL',1],['2026-10','A','ITEL',-1]], 0, 'ST net nol bulan ini tidak dihitung');
countIddle([['2026-07','A','ITEL',2],['2026-08','A','ITEL',1],['2026-08','A','ITEL',-1],['2026-10','A','ITEL',1]], 1, 'ST net nol pada bulan jeda tetap kosong');
countIddle([['2026-07','A','ITEL',2],['2026-07','A','ITEL',-2],['2026-10','A','ITEL',1]], 0, 'Basis Juli harus ST net positif');
countIddle([['2025-10','A','ITEL',2],['2026-01','A','ITEL',1]], 1, 'Pergantian tahun', 'ITEL', '2026-01');

$db = fixture([['2026-07','A','ITEL',1],['2026-10','A','ITEL',1]]);
$db['coverage']['2026-09']['cutoff'] = '2026-09-15';
same(PortalCore::dealerIddle($db['cubes']['2026-10'], $db, '2026-10', 'ITEL'), null, 'Histori MTD belum membuktikan satu bulan kosong');
$db = fixture([['2026-07','A','ITEL',1],['2026-10','A','ITEL',1]]);
unset($db['coverage']['2026-08']);
same(PortalCore::dealerIddle($db['cubes']['2026-10'], $db, '2026-10', 'ITEL'), null, 'Histori bulan hilang tidak dianggap nol');

$source = fixture([['2026-07','A','ITEL',1,'S2','OLD'],['2026-10','A','ITEL',1,'S1','REG']]);
foreach ($source['coverage'] as $month => $cov) foreach (['OLD','REG'] as $region) $source['parts']['ST'][$month][$region] = ['cutoff' => $cov['cutoff']];
$source['meta']['regionList'] = ['REG','OLD'];
$view = coverageView_($source, '2026-10', ['S1'], 'ST');
same(count($view['cubes']['2026-07']), 0, 'View menyaring region lama');
same(PortalCore::dealerIddle($view['cubes']['2026-10'], $view, '2026-10', 'ITEL'), 1, 'Histori global tetap mengenali dealer pindah region');

function configure(array &$db, int $target = 2): void {
    $db['meta']['policies'] = [['period'=>'2026-10','region'=>'REG','group'=>'REAKTIVASI','metric'=>'IDDLE','brand'=>'ITEL','weight'=>100,'cap'=>120,'minDA'=>1]];
    $db['meta']['targets'] = [['period'=>'2026-10','nik'=>'S1','brand'=>'ITEL','metric'=>'IDDLE','indicator'=>'','target'=>$target]];
    PortalCore::resetMemo($db['id']);
}
$db = fixture([['2026-07','A','ITEL',1],['2026-10','A','ITEL',1]]); configure($db);
$k = PortalCore::kpi($db,'2026-10',['S1'],'REG');
same($k['groups'][0]['indicators'][0]['actual'], 1, 'Aktual KPI IDDLE');
same((float)$k['score'], 50.0, 'Skor aktual 1 target 2 = 50 persen');
same($k['status'], 'FINAL', 'KPI lengkap final');
configure($db, 0); $k = PortalCore::kpi($db,'2026-10',['S1'],'REG');
same($k['score'], null, 'Target 0 tidak diwajibkan');
configure($db); $db['coverage']['2026-08']['cutoff']='2026-08-10';PortalCore::resetMemo($db['id']);
$k = PortalCore::kpi($db,'2026-10',['S1'],'REG');
same($k['groups'][0]['indicators'][0]['actual'], null, 'Histori kurang: aktual null');
same($k['groups'][0]['indicators'][0]['state'], 'HISTORI ST BELUM LENGKAP', 'Status histori kurang');
same($k['status'], 'BELUM FINAL', 'KPI histori kurang belum final');

$db = fixture([['2026-07','A','ITEL',1],['2026-10','A','ITEL',1]]);
$cfg = [['group'=>'REAKTIVASI','metric'=>'IDDLE','brand'=>'ITEL','weight'=>100,'cap'=>120,'target'=>2,'minDA'=>1]];
$k = PortalCore::kpiRgm($db,'2026-10',['S1'],'REG',$cfg);
same((float)$k['score'], 50.0, 'KPI RGM menghitung IDDLE');
$db['coverage']['2026-08']['cutoff']='2026-08-10';PortalCore::resetMemo($db['id']);
same(PortalCore::kpiRgm($db,'2026-10',['S1'],'REG',$cfg)['score'], null, 'RGM histori kurang tidak dinilai nol');

$db = fixture([['2026-07','A','ITEL',1],['2026-10','A','ITEL',1,'S1'],['2026-10','A','ITEL',1,'S2']]);
$pairs = null;
same(PortalCore::dealerIddle(PortalCore::slice($db,'2026-10',['S1','S2']),$db,'2026-10','ITEL',$pairs),1,'Dealer-brand lintas Sales tetap satu di total tim');
same($pairs, [jkey(['A','ITEL'])], 'Identitas pasangan untuk deduplikasi TOTAL TIM');

// Metrik lama tetap sama; IDDLE tidak ditambahkan ke panel Beranda umum.
$db = fixture([['2026-10','A','ITEL',2],['2026-10','B','ITEL',1]]);
$m = PortalCore::metrics($db['cubes']['2026-10'],$db,'2026-10');
same($m['qty'], 3, 'Regresi QTY');same($m['da'], 1, 'Regresi DA min 2');same($m['outlets'], 2, 'Regresi dealer unik');
echo "OK: {$checks} pemeriksaan PHP.\n";
