<?php
/** Optional CLI migration; the app also initializes these tables on first use. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../lib/api_telemarketing.php';
try {
    tmSchema_();
    foreach (['_tm_profile', '_tm_assignment', '_tm_scope', '_tm_kpi', '_tm_fu', '_tm_order', '_tm_business'] as $table) {
        db_()->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
        echo $table . " OK\n";
    }
    echo "Migrasi Telemarketing selesai. Data existing tidak dihapus.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Migrasi gagal: " . $e->getMessage() . "\n");
    exit(1);
}
