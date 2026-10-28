<?php
// Pengatur jadwal notifikasi. Cron tiap 5 menit:  */5 * * * * php /path/ke/dsm/notif_cli.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/lib/api_import.php';
require_once __DIR__ . '/lib/api_notif.php';
$log = ntTick_(); echo date('c') . ' ' . ($log ? implode(' ', $log) : 'tidak ada tugas') . "\n";
