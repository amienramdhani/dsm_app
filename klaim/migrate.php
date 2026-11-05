<?php
/**
 * Migrasi Klaim untuk database DSM. AMAN dijalankan berulang: hanya membuat tabel yang belum ada,
 * mengisi data acuan (jenis klaim, kota, brand, bank, aturan awal) bila masih kosong, dan TIDAK membuat pengguna uji.
 * Jalankan:  php klaim/migrate.php   (atau buka /klaim/migrate.php sebagai Super Admin DSM lewat token)
 */
require_once __DIR__ . '/lib/seed.php';
$cli = PHP_SAPI === 'cli';
function mig_out(string $m, bool $cli) { if ($cli) { echo $m . "\n"; } else { header('Content-Type: text/plain; charset=utf-8'); echo $m; } }
try {
  if (!$cli) { $auth = dsm_auth(); if (!$auth || $auth['role'] !== 'SUPER ADMIN') { http_response_code(403); mig_out('Hanya Super Admin DSM.', false); exit; } }
  $made = migrate_schema();
  $seeded = false; $tlog = [];
  if (!val("SELECT 1 FROM claim_types LIMIT 1")) { seed_all(false); $seeded = true; } else $tlog = sync_claim_types();
  mig_out('Selesai. Tabel baru: ' . ($made ? implode(', ', $made) : '(tidak ada, sudah lengkap)') . '. Data acuan: ' . ($seeded ? 'diisi' : 'sudah ada') . '. Jenis klaim: ' . ($tlog ? implode('; ', $tlog) : 'sudah sesuai') . '. Harga BBM: isi lewat Super Admin > Harga BBM.', $cli);
} catch (Throwable $e) { mig_out('GAGAL: ' . $e->getMessage(), $cli); exit(1); }
