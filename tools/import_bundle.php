<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Import file ekspor Apps Script (DSM-export-xxxx.zip) ke database DSM.
 *
 * Cara pakai (Terminal Laragon):
 *   cd C:\laragon\www\dsm\tools
 *   php import_bundle.php C:\Users\NAMA\Downloads\DSM-export-20261002-0900.zip
 *
 * Yang dilakukan:
 *   1. Membuat tabel sistem DSM (kalau belum ada).
 *   2. Mengganti SEMUA sheet (Master_Sales, Master_Customer, _Auth, KPI, target, dll)
 *      dengan isi dari file ekspor — 1 sheet = 1 tabel "sh_..." di MySQL.
 *   3. Menyalin data ST/SO per bulan per region + snapshot publikasi terakhir.
 *   4. Mengaktifkan snapshot itu, jadi angka di DSM = angka di Apps Script.
 *
 * PIN lama tetap berlaku (asal PEPPER di config.php sama dengan Apps Script).
 */

ini_set('memory_limit', '2048M');
set_time_limit(0);
$base = is_file(__DIR__ . '/../lib/data.php') ? __DIR__ . '/..' : (is_file(__DIR__ . '/../dsm/lib/data.php') ? __DIR__ . '/../dsm' : null);
if (!$base) { fwrite(STDERR, "Tidak ketemu folder dsm (lib/data.php). Taruh folder tools di dalam folder dsm.\n"); exit(1); }
require_once $base . '/lib/schema.php';

$zipPath = $argv[1] ?? null;
$yes = in_array('--yes', $argv, true);
if (!$zipPath || !is_file($zipPath)) { fwrite(STDERR, "Pakai: php import_bundle.php <file DSM-export-xxx.zip> [--yes]\n"); exit(1); }
if (!class_exists('ZipArchive')) { fwrite(STDERR, "Ekstensi PHP 'zip' belum aktif. Di Laragon: Menu > PHP > Extensions > zip.\n"); exit(1); }

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) { fwrite(STDERR, "File zip tidak bisa dibuka: $zipPath\n"); exit(1); }
$read = function (string $name) use ($zip) { $s = $zip->getFromName($name); if ($s === false) throw new RuntimeException("Isi zip tidak lengkap: $name"); return $s; };
$manifest = json_decode($read('manifest.json'), true);
if (($manifest['app'] ?? '') !== 'sales-portal') { fwrite(STDERR, "Ini bukan file ekspor Sales Portal.\n"); exit(1); }

echo "File ekspor: " . basename($zipPath) . "  (dibuat " . ($manifest['exportedAt'] ?? '?') . ")\n";
echo "Sheet: " . count($manifest['sheets']) . " · Snapshot aktif: " . $manifest['active'] . "\n\n";
if (!$yes) {
    echo "PERHATIAN: semua data DSM di database '" . DSM_DB_NAME . "' akan DIGANTI dengan isi file ini.\nKetik YA lalu Enter untuk lanjut: ";
    if (strtoupper(trim((string) fgets(STDIN))) !== 'YA') { echo "Dibatalkan.\n"; exit(0); }
}

$t0 = microtime(true);
dsm_install_schema_();
$dropped = dsm_drop_tahap1_();
if ($dropped) echo "Tabel lama Tahap 1 dihapus: " . implode(', ', $dropped) . "\n";

// 1) Sheet
$pdo = db_();
foreach ($pdo->query('SELECT tbl FROM _sheets')->fetchAll(PDO::FETCH_COLUMN) as $t) $pdo->exec('DROP TABLE IF EXISTS `' . $t . '`');
$pdo->exec('DELETE FROM _sheets');
sheet_registry_(true);
$sheets = json_decode($read('sheets.json'), true);
foreach ($sheets as $name => $grid) {
    $heads = $grid[0] ?? [];
    $rows = array_slice($grid, 1);
    putTable_((string) $name, $heads, $rows);
    printf("  sheet %-28s %6d baris\n", $name, count($rows));
}

// 2) Properties
$pdo->exec('DELETE FROM _props');
$pdo->exec('DELETE FROM _sessions');
foreach (json_decode($read('props.json'), true) as $k => $v) if (!in_array($k, ['ACTIVE', 'PREVIOUS'], true)) prop_set_($k, $v);

// 3) File data
$filesDir = storage_dir_('files');
foreach (glob($filesDir . '/*.json') as $f) unlink($f);
$snapshotJson = $read('snapshot.json');
file_put_contents(file_path_($manifest['active']), $snapshotJson);
if (!empty($manifest['contactState'])) { file_put_contents(file_path_($manifest['contactState']['id']), $read($manifest['contactState']['file'])); prop_set_('DEALER_CONTACT_STATE', $manifest['contactState']['id']); }

$pdo->exec('TRUNCATE tx_st'); $pdo->exec('TRUNCATE tx_so'); $pdo->exec('TRUNCATE tx_part');
$snap = json_decode($snapshotJson, true);
$monthRows = ['ST' => [], 'SO' => []];
$counts = ['ST' => 0, 'SO' => 0];
foreach (['ST', 'SO'] as $kind) {
    foreach ($manifest['parts'][$kind] ?? [] as $period => $regions) {
        foreach ($regions as $region => $e) {
            $json = $read($e['file']);
            file_put_contents(file_path_($e['id']), $json);
            $rows = json_decode($json, true) ?: [];
            $pdo->prepare('INSERT INTO tx_part (jenis, periode, region, file_id, cutoff, baris) VALUES (?,?,?,?,?,?)')->execute([$kind, $period, $region, $e['id'], $e['cutoff'], count($rows)]);
            foreach ($rows as $r) $monthRows[$kind][$period][] = $r;
            insert_tx_($kind, $period, (string) $region, $rows);
            $counts[$kind] += count($rows);
        }
    }
}
// Berkas bulanan gabungan (dipakai menu Transaksi), dengan id sama seperti di snapshot.
foreach ($snap['raw'] ?? [] as $p => $id) if (isset($monthRows['ST'][$p])) writeJsonId_($id, $monthRows['ST'][$p]);
foreach ($snap['so']['raw'] ?? [] as $p => $id) if (isset($monthRows['SO'][$p])) writeJsonId_($id, $monthRows['SO'][$p]);

prop_set_('ACTIVE', $manifest['active']);
prop_set_('DSM_IMPORTED_FROM', basename($zipPath) . ' @ ' . ($manifest['exportedAt'] ?? ''));

printf("\nTransaksi: ST %s baris, SO %s baris.\n", number_format($counts['ST'], 0, ',', '.'), number_format($counts['SO'], 0, ',', '.'));
printf("Selesai dalam %.1f detik. Buka aplikasi DSM dan login seperti biasa.\n", microtime(true) - $t0);
echo "Jangan lupa hapus file ekspor dari Google Drive & folder Download (isinya data lengkap).\n";

function insert_tx_(string $kind, string $period, string $region, array $rows): void {
    $pdo = db_();
    if ($kind === 'ST') { $cols = '(periode, region, tanggal, badan_usaha, no_transaksi, no_sj, kode_produk, kode_customer, nik_sales, qty, amount, id_baris, ref_transaksi)'; $n = 13; }
    else { $cols = '(periode, region, tanggal, kode_customer, kode_produk, qty)'; $n = 6; }
    foreach (array_chunk($rows, 1000) as $chunk) {
        $vals = [];
        foreach ($chunk as $t) {
            if ($kind === 'ST') array_push($vals, $period, $region, $t['date'], (string) ($t['company'] ?? ''), (string) ($t['doc'] ?? ''), (string) ($t['sj'] ?? ''), (string) $t['sku'], (string) $t['customer'], (string) ($t['nik'] ?? ''), (int) $t['qty'], (float) ($t['amount'] ?? 0), (string) ($t['line'] ?? ''), (string) ($t['ref'] ?? ''));
            else array_push($vals, $period, $region, $t['date'], (string) $t['customer'], (string) $t['sku'], (int) $t['qty']);
        }
        $ph = implode(',', array_fill(0, count($chunk), '(' . implode(',', array_fill(0, $n, '?')) . ')'));
        $pdo->prepare('INSERT INTO tx_' . strtolower($kind) . ' ' . $cols . ' VALUES ' . $ph)->execute($vals);
    }
}
