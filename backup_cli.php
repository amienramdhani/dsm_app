<?php
// Jalankan terjadwal: php backup_cli.php  (cron harian / Task Scheduler)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/lib/api_import.php';
require_once __DIR__ . '/lib/api_backup.php';
backupExec_(); echo "Backup selesai\n";
