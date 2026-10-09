<?php
/**
 * KONFIGURASI DSM — Tahap 1
 *
 * PEPPER DI BAWAH INI WAJIB SAMA PERSIS dengan nilai Script Property
 * bernama "PEPPER" di project Apps Script lama lu.
 *
 * Cara ambil nilainya:
 *   1. Buka project Apps Script lama (yang masih jalan sekarang).
 *   2. Klik ikon gerigi (Project Settings) di kiri.
 *   3. Scroll ke "Script properties", cari baris "PEPPER".
 *   4. Copy persis nilainya (jangan ada spasi/enter nyelip), paste di bawah.
 *
 * Kalau nilai ini beda walau cuma 1 karakter, SEMUA PIN lama tidak akan
 * pernah cocok lagi — makanya ini wajib dicek dulu sebelum tes login.
 */
define('DSM_PEPPER', '5d4ece76-3d42-4086-958e-07ccfe3917ace3952549-a696-4a49-9036-637879497e2e152fb4d3-c8fa-4a4e-b9bd-70fa918dc129');

// --- Koneksi database (default Laragon, biasanya tidak perlu diubah) ---
define('DSM_DB_HOST', 'localhost');
define('DSM_DB_PORT', '3306');
define('DSM_DB_NAME', 'dsm');
define('DSM_DB_USER', 'root');
define('DSM_DB_PASS', '');

// Zona waktu aplikasi
date_default_timezone_set('Asia/Jakarta');
