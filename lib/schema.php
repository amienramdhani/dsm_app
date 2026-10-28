<?php
/** Tabel sistem DSM. Dipanggil oleh tools/import_bundle.php (aman dijalankan berulang). */
require_once __DIR__ . '/data.php';

function dsm_install_schema_(): void {
    $pdo = db_();
    $pdo->exec("CREATE TABLE IF NOT EXISTS _props (k VARCHAR(190) NOT NULL PRIMARY KEY, v LONGTEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS _sheets (name VARCHAR(190) NOT NULL PRIMARY KEY, tbl VARCHAR(64) NOT NULL, heads LONGTEXT NOT NULL, cols LONGTEXT NOT NULL, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS _sessions (k VARCHAR(120) NOT NULL PRIMARY KEY, nik VARCHAR(100) NOT NULL, data TEXT NOT NULL, until_ms BIGINT NOT NULL, device_id VARCHAR(64) NOT NULL DEFAULT '', KEY idx_nik (nik), KEY idx_until (until_ms)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (function_exists('sessions_ensure_device_col_')) sessions_ensure_device_col_();
    // Paket 11 (#37): perangkat terdaftar per NIK (maks 4), dibuat otomatis juga oleh devices_schema_() di auth.php.
    $pdo->exec("CREATE TABLE IF NOT EXISTS _devices (device_id VARCHAR(64) NOT NULL, nik VARCHAR(100) NOT NULL, nama VARCHAR(150) NOT NULL DEFAULT '', ua VARCHAR(255) NOT NULL DEFAULT '', created_ms BIGINT NOT NULL, last_seen_ms BIGINT NOT NULL, PRIMARY KEY (nik, device_id), KEY idx_nik (nik)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Salinan baris transaksi untuk dilihat/dianalisa di Navicat (sumber utama tetap file per region).
    $pdo->exec("CREATE TABLE IF NOT EXISTS tx_st (id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, periode CHAR(7) NOT NULL, region VARCHAR(100) NOT NULL DEFAULT '', tanggal DATE NOT NULL, badan_usaha VARCHAR(100) NOT NULL DEFAULT '', no_transaksi VARCHAR(100) NOT NULL DEFAULT '', no_sj VARCHAR(100) NOT NULL DEFAULT '', kode_produk VARCHAR(100) NOT NULL, kode_customer VARCHAR(100) NOT NULL, nik_sales VARCHAR(100) NOT NULL DEFAULT '', qty INT NOT NULL, amount DECIMAL(18,2) NOT NULL DEFAULT 0, id_baris VARCHAR(150) NOT NULL DEFAULT '', ref_transaksi VARCHAR(100) NOT NULL DEFAULT '', KEY idx_pr (periode, region), KEY idx_cust (kode_customer), KEY idx_nik (nik_sales), KEY idx_tgl (tanggal)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tx_so (id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, periode CHAR(7) NOT NULL, region VARCHAR(100) NOT NULL DEFAULT '', tanggal DATE NOT NULL, kode_customer VARCHAR(100) NOT NULL, kode_produk VARCHAR(100) NOT NULL, qty INT NOT NULL, KEY idx_pr (periode, region), KEY idx_cust (kode_customer), KEY idx_tgl (tanggal)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tx_part (jenis CHAR(2) NOT NULL, periode CHAR(7) NOT NULL, region VARCHAR(100) NOT NULL, file_id VARCHAR(200) NOT NULL, cutoff DATE NULL, baris INT NOT NULL DEFAULT 0, PRIMARY KEY (jenis, periode, region)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Tabel versi Tahap 1 yang sudah tidak dipakai. */
function dsm_drop_tahap1_(): array {
    $dropped = [];
    foreach (['auth', 'master_sales', 'region_map', 'periode', 'sessions', 'login_throttle', 'audit_log'] as $t) {
        $exists = db_()->query("SHOW TABLES LIKE " . db_()->quote($t))->fetchColumn();
        if ($exists) { db_()->exec('DROP TABLE `' . $t . '`'); $dropped[] = $t; }
    }
    return $dropped;
}
