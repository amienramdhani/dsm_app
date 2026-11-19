<?php
/**
 * Pekerja antrean publikasi DSM (Tahap 3). Dijalankan cron tiap menit lewat PHP CLI (tanpa batas 180 dtk PHP-FPM):
 *   * * * * * php /path/ke/dsm/tools/publish_worker.php >> /path/ke/dsm/storage/publish_worker.log 2>&1
 * Aman dijalankan berkali-kali: hanya satu pekerja yang aktif (kunci MySQL dsm_worker).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
foreach (file($root . '/api.php') as $line) if (preg_match("~^require_once __DIR__ \\. '(/lib/[a-z0-9_]+\\.php)'~", $line, $m)) require_once $root . $m[1];
ini_set('memory_limit', '2560M'); set_time_limit(0);
try { $r = pqWork_(false); if (!empty($r['done'])) echo date('c'), ' publikasi selesai: ', $r['done'], PHP_EOL; }
catch (Throwable $e) { fwrite(STDERR, date('c') . ' gagal: ' . $e->getMessage() . PHP_EOL); exit(1); }
