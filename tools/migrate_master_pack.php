<?php
/**
 * DSM Master Pack migration — jalankan SEKALI setelah file paket ditimpa.
 * Membuat tabel PIN sementara terenkripsi dan menambahkan STATUS pada Master_Product.
 * Aman diulang.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('memory_limit','1024M'); set_time_limit(0); chdir(__DIR__.'/..'); $_SERVER['REMOTE_ADDR']='cli';
foreach (glob(__DIR__ . '/../lib/*.php') as $f) require_once $f;

echo "DSM Master Pack migration\n";
tempPinSchema_(); echo "✓ _temp_pins siap\n";
ensureProductStatus_(); echo "✓ Master_Product.STATUS siap (existing = ACTIVE)\n";
echo "Selesai.\n";
