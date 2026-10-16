<?php
// Gambar notifikasi (nama berkas acak, hanya jpg/png dari storage/notif).
$f = (string) ($_GET['f'] ?? '');
if (!preg_match('/^[a-f0-9]{20}\.(jpg|png)$/D', $f)) { http_response_code(404); exit; }
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
$p = storage_dir_('notif') . '/' . $f;
if (!is_file($p)) { http_response_code(404); exit; }
header('Content-Type: ' . (str_ends_with($f, '.png') ? 'image/png' : 'image/jpeg'));
header('Cache-Control: public, max-age=604800');
header('X-Content-Type-Options: nosniff');
readfile($p);
