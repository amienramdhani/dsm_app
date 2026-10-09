<?php
/** Unified migration: Master Pack + Dealer × Brand PIC. Safe to rerun. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('memory_limit','1536M'); set_time_limit(0); chdir(__DIR__.'/..'); $_SERVER['REMOTE_ADDR']='cli';
foreach (glob(__DIR__ . '/../lib/*.php') as $f) require_once $f;

echo "DSM Master + PIC Dealer per Brand migration\n";
if (function_exists('tempPinSchema_')) { tempPinSchema_(); echo "✓ _temp_pins siap\n"; }
if (function_exists('ensureProductStatus_')) { ensureProductStatus_(); echo "✓ Master_Product.STATUS siap\n"; }

if (!prop_get_(DBA_CUTOVER_PROP)) prop_set_(DBA_CUTOVER_PROP, '2026-10-01');
$cut = dbaCutover_(); $brands = dbaPrimaryBrands_();
if (!$brands) throw new RuntimeException('Brand utama tidak ditemukan dari Product_Rules.');

// Master_Customer.NIK SALES adalah baseline/fallback implisit. Assignment eksplisit hanya
// disimpan ketika PIC sebuah brand memang berbeda/berubah, supaya tabel tetap kecil dan cepat.
if (!sheet_exists_(DBA_SHEET)) dbaWrite_([]);
$rows = dbaRows_();

$owners = []; $conflict = [];
foreach (table_('Master_Customer') as $r) {
    $d = text_($r['KODE CUSTOMER'] ?? null); $n = text_($r['NIK SALES'] ?? null); if ($d===''||$n==='') continue;
    if (isset($owners[$d]) && $owners[$d] !== $n) { $conflict[$d] = [$owners[$d],$n]; continue; }
    $owners[$d] = $n;
}

echo "✓ Cutover validasi PIC: {$cut}\n";
echo "✓ Brand utama: ".implode(', ',$brands)."\n";
echo "✓ Baseline: Master_Customer.NIK SALES (implisit, tidak menduplikasi ribuan baris)\n";
echo "✓ Assignment/history eksplisit existing: ".count($rows)."\n";
if ($conflict) echo "⚠ ".count($conflict)." dealer punya NIK SALES default berbeda antar baris Master_Customer; cek Health Check setelah login. Contoh: ".implode(', ',array_slice(array_keys($conflict),0,10))."\n";
echo "Selesai. Buka Admin → Dealer → PIC Dealer per Brand lalu jalankan Cek Kesehatan Master.\n";
