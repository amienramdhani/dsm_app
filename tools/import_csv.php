<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Import data dari CSV (hasil export Google Sheets) ke database dsm.
 * PIN lama TIDAK direset — SALT & PIN_HASH dipindah apa adanya.
 *
 * Cara pakai (dari folder tools/, lewat terminal Laragon atau CMD):
 *   php import_csv.php --auth=auth.csv --sales=master_sales.csv --region=region_map.csv
 *
 * Cara bikin CSV-nya:
 *   1. Buka Google Sheet lama (yang jadi sumber Apps Script).
 *   2. Buka sheet "_Auth" -> File > Download > Comma Separated Values (.csv) -> simpan sebagai auth.csv
 *   3. Buka sheet "Master_Sales" -> File > Download > csv -> simpan sebagai master_sales.csv
 *   4. Buka sheet "Region_Map" -> File > Download > csv -> simpan sebagai region_map.csv
 *   5. Taruh ketiga file itu satu folder sama import_csv.php ini, lalu jalankan perintah di atas.
 *
 * Catatan: _Auth sheet biasanya disembunyikan (hideSheet). Klik kanan tab
 * sheet di pojok kiri bawah > "Unhide sheet" dulu baru bisa di-download.
 */

// Cari lib/db.php baik kalau folder tools/ ini sejajar dengan dsm/
// (..\dsm\lib\db.php) maupun kalau ditaruh di dalam dsm\tools\ (..\lib\db.php).
$dbLibCandidates = [
    __DIR__ . '/../dsm/lib/db.php',
    __DIR__ . '/../lib/db.php',
];
$dbLibPath = null;
foreach ($dbLibCandidates as $candidate) {
    if (is_file($candidate)) {
        $dbLibPath = $candidate;
        break;
    }
}
if ($dbLibPath === null) {
    fwrite(STDERR, "Tidak ketemu lib/db.php. Pastikan folder tools ini ada di dalam folder dsm (dsm\\tools) atau sejajar dengan folder dsm.\n");
    exit(1);
}
require_once $dbLibPath;

function arg(string $name, ?string $default = null): ?string {
    foreach ($GLOBALS['argv'] as $a) {
        if (str_starts_with($a, "--$name=")) {
            return substr($a, strlen($name) + 3);
        }
    }
    return $default;
}

function read_csv(string $path): array {
    if (!is_file($path)) {
        fwrite(STDERR, "File tidak ditemukan: $path\n");
        exit(1);
    }
    $fh = fopen($path, 'r');
    // Buang BOM UTF-8 kalau ada (bawaan Google Sheets export)
    $first = fgets($fh);
    $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
    rewind($fh);
    fgets($fh);
    $headerLine = str_getcsv($first);
    $headers = array_map(fn($h) => strtoupper(trim($h)), $headerLine);
    $rows = [];
    while (($line = fgetcsv($fh)) !== false) {
        if (count($line) === 1 && trim((string) $line[0]) === '') {
            continue;
        }
        $row = [];
        foreach ($headers as $i => $h) {
            $row[$h] = $line[$i] ?? '';
        }
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

function truthy(string $v): bool {
    $v = strtoupper(trim($v));
    return in_array($v, ['TRUE', 'YA', '1'], true);
}

$authPath = arg('auth');
$salesPath = arg('sales');
$regionPath = arg('region');

if (!$authPath && !$salesPath && !$regionPath) {
    fwrite(STDERR, "Pakai: php import_csv.php --auth=auth.csv --sales=master_sales.csv --region=region_map.csv\n");
    exit(1);
}

$pdo = dsm_pdo();

if ($regionPath) {
    $rows = read_csv($regionPath);
    $st = $pdo->prepare('REPLACE INTO region_map (region, wilayah_kpi) VALUES (?,?)');
    $n = 0;
    foreach ($rows as $r) {
        $region = trim($r['REGION'] ?? '');
        if ($region === '') continue;
        $st->execute([$region, trim($r['WILAYAH_KPI'] ?? '')]);
        $n++;
    }
    echo "Region_Map: $n baris.\n";
}

if ($salesPath) {
    $rows = read_csv($salesPath);
    $st = $pdo->prepare('REPLACE INTO master_sales (nik, nama, posisi, region, nik_atasan) VALUES (?,?,?,?,?)');
    $n = 0;
    foreach ($rows as $r) {
        $nik = trim($r['NIK KARYAWAN'] ?? '');
        if ($nik === '') continue;
        $st->execute([
            $nik,
            trim($r['SALES'] ?? ''),
            trim($r['POSISI'] ?? ''),
            trim($r['REGION'] ?? ''),
            trim($r['NIK ATASAN'] ?? ''),
        ]);
        $n++;
    }
    echo "Master_Sales: $n baris.\n";
}

if ($authPath) {
    $rows = read_csv($authPath);
    $st = $pdo->prepare(
        'REPLACE INTO auth (nik, salt, pin_hash, aktif, versi, wajib_ganti, gagal, lock_until)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    $n = 0;
    foreach ($rows as $r) {
        $nik = trim($r['NIK'] ?? '');
        if ($nik === '') continue;
        $st->execute([
            $nik,
            (string) ($r['SALT'] ?? ''),
            (string) ($r['PIN_HASH'] ?? ''),
            truthy((string) ($r['AKTIF'] ?? '')) ? 1 : 0,
            (int) ($r['VERSI'] ?? 0),
            truthy((string) ($r['WAJIB_GANTI'] ?? '')) ? 1 : 0,
            (int) ($r['GAGAL'] ?? 0),
            (int) ($r['LOCK_UNTIL'] ?? 0),
        ]);
        $n++;
    }
    echo "_Auth: $n baris.\n";
}

echo "Selesai.\n";
