<?php
/**
 * CEK PAKET 2 — jalankan di Laragon dari folder DSM:   php tools/cek_status_dealer.php
 *
 * 1) SIDIK JARI KPI: angka KPI/achievement semua region + semua orang untuk 3 periode terakhir, diringkas jadi satu kode.
 *    Jalankan SEBELUM update (kode lama) dan SESUDAH update. Kodenya harus SAMA PERSIS.
 * 2) DAMPAK: jumlah dealer per status cara lama vs aturan baru (data asli, periode terbaru). Hanya jalan di kode baru.
 *
 * Tidak mengubah data apa pun.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
require_once $root . '/config.php';
require_once $root . '/lib/common.php';
foreach (['api_kpi', 'api_brand', 'dealer_status'] as $f) if (is_file("$root/lib/$f.php")) require_once "$root/lib/$f.php";
ini_set('memory_limit', '2048M'); set_time_limit(0);

$db = snapshot_();
$ps = array_keys($db['coverage']); sort($ps, SORT_STRING); $periods = array_slice($ps, -3); $latest = end($ps);
echo "Periode terbaru: $latest\n\n== 1) SIDIK JARI KPI ==\n";
$all = []; foreach ($db['meta']['people'] as $p) $all[] = (string) $p['nik'];
$regions = []; foreach ($db['meta']['people'] as $p) if (truthy($p['sales'] ?? null) && truthy($p['wilayah'] ?? null)) $regions[$p['wilayah']] = true;
$regions = array_keys($regions); sort($regions, SORT_STRING);
$fp = [];
foreach ($periods as $per) {
    $one = [];
    foreach ($regions as $r) {
        $ids = []; foreach ($db['meta']['people'] as $p) if (($p['wilayah'] ?? null) === $r) $ids[] = (string) $p['nik'];
        $v = coverageView_($db, $per, $ids, 'ST', ['role' => 'SUPER ADMIN']);
        $one[$r] = PortalCore::kpi($v, $per, $ids, $r);
        foreach ($ids as $nik) { $pp = person_($db, $nik); if ($pp && truthy($pp['sales'] ?? null)) $one["$r|$nik"] = PortalCore::kpi($v, $per, [$nik], $r); }
    }
    $h = substr(sha1(json_encode($one)), 0, 16); $fp[] = $h; echo "  $per : $h  (" . count($one) . " kartu KPI)\n";
}
echo "  KODE KPI: " . substr(sha1(implode('|', $fp)), 0, 16) . "   <- catat, bandingkan sebelum vs sesudah update\n";

if (!function_exists('dsComputeRaw_')) { echo "\n(Bagian DAMPAK hanya di kode baru.)\n"; exit; }
echo "\n== 2) DAMPAK STATUS DEALER ($latest, seluruh dealer) ==\n";
$base = dsBase_($db); $pp = PortalCore::prev($latest); $ppp = PortalCore::prev($pp);
// cara lama (status total di halaman Dealer, sebelum Paket 2)
$hist = []; foreach ($base['cubes'] as $per => $rows) if (strcmp($per, $latest) <= 0) foreach ($rows as $r) { $k = substr($r['date'], 0, 7); $hist[(string) $r['customer']][$k] = ($hist[(string) $r['customer']][$k] ?? 0) + $r['q']; }
$old = [];
foreach ($hist as $cu => $m) {
    $cq = $m[$latest] ?? 0; $per = array_keys(array_filter($m, fn($q, $k) => $q > 0 && $k !== $latest, ARRAY_FILTER_USE_BOTH));
    $s = $cq >= 2 ? 'AKTIF' : ($cq > 0 ? 'BELUM MINIMUM DA' : (in_array($pp, $per, true) ? 'DA BULAN LALU' : (!$per ? 'BELUM ORDER' : (in_array($ppp, $per, true) ? 'IDLE' : 'PASIF'))));
    $old[$s] = ($old[$s] ?? 0) + 1;
}
arsort($old); echo "  CARA LAMA (dihitung dari bulan berjalan):\n"; foreach ($old as $k => $v) printf("    %-18s %7s\n", $k, number_format($v, 0, ',', '.'));
$L = dsLocked_($db, $latest); $new = []; foreach ($L['total'] as $v) $new[$v[0]] = ($new[$v[0]] ?? 0) + 1;
$C = dsCurrent_($db, $latest); $ord = []; foreach ($L['total'] as $cu => $v) if (($C['total'][$cu]['q'] ?? 0) > 0) $ord[$v[0]] = ($ord[$v[0]] ?? 0) + 1;
arsort($new); echo "  ATURAN BARU (pola " . $L['months'][0] . " s.d. " . end($L['months']) . ($L['locked'] ? ', TERKUNCI' : ', sementara') . "):\n";
foreach ($new as $k => $v) printf("    %-18s %7s   (%s sudah order bulan ini)\n", $k, number_format($v, 0, ',', '.'), number_format($ord[$k] ?? 0, 0, ',', '.'));
$nda = 0; foreach ($C['brand'] as $bs) foreach ($bs as $x) if ($x['da']) $nda++;
echo "  Pasangan dealer-brand memenuhi DA bulan ini (aturan DA KPI): " . number_format($nda, 0, ',', '.') . "\n";
