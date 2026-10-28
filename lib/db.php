<?php
require_once __DIR__ . '/../config.php';

function dsm_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DSM_DB_HOST . ';port=' . DSM_DB_PORT . ';dbname=' . DSM_DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DSM_DB_USER, DSM_DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/** Waktu sekarang dalam milidetik, setara Date.now() di Apps Script. */
function dsm_now_ms(): int {
    return (int) round(microtime(true) * 1000);
}
