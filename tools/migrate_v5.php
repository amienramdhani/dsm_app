<?php
/**
 * Migrasi DSM v5 — jalankan SEKALI setelah file aplikasi baru dipasang.
 *
 * Cara pakai (Terminal Laragon / SSH VPS):
 *   cd C:\laragon\www\dsm\tools        (atau folder dsm di VPS)
 *   php migrate_v5.php
 *
 * Yang dilakukan:
 *   1. Membuat tabel baru: dsm_part, dsm_st, dsm_so, dsm_move.
 *   2. Menyalin semua data ST/SO dari file JSON lama (storage/files) ke tabel MySQL.
 *   3. Publikasi ulang (angka tidak berubah) → snapshot baru tanpa data mutasi stok di dalamnya.
 *   4. Membersihkan file JSON yang sudah tidak dipakai.
 * Aman diulang: partisi yang sudah di MySQL dilewati.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('memory_limit', '3072M');
set_time_limit(0);
chdir(__DIR__ . '/..');
$_SERVER['REMOTE_ADDR'] = 'cli';
foreach (glob(__DIR__ . '/../lib/*.php') as $f) require_once $f;

$t0 = microtime(true);
echo "DSM v5 — migrasi data ST/SO ke MySQL\n";
$before = 0; foreach (glob(storage_dir_('files') . '/*.json') ?: [] as $f) $before += filesize($f);
echo 'Ukuran storage/files sebelum: ' . round($before / 1048576, 1) . " MB\n";

$res = with_lock_(function () {
    store_schema_();
    $old = snapshot_();
    if (empty($old['parts'])) fail_('Snapshot aktif belum berformat partisi region (v4). Tidak bisa dimigrasi.');
    $parts = $old['parts']; $copied = 0; $rows = 0;
    foreach (['ST', 'SO'] as $kind) {
        foreach (js_keys($parts[$kind] ?? []) as $p) {
            foreach (js_keys($parts[$kind][$p]) as $r) {
                $e = $parts[$kind][$p][$r]; $f = (string) ($e['file'] ?? '');
                if ($f === '' || str_starts_with($f, 'P:')) continue;
                $data = pubReadJsonRaw_($f);
                $parts[$kind][$p][$r]['file'] = partWrite_($kind, (string) $p, (string) $r, $data);
                $copied++; $rows += count($data);
                echo "  {$kind} {$p} {$r}: " . count($data) . " baris\n";
            }
        }
    }
    echo "Partisi disalin: {$copied} ({$rows} baris). Publikasi ulang…\n";
    $out = publish_(meta_(), $old['raw'], $old['coverage'], 'SISTEM', 'Migrasi DSM v5 (data ST/SO ke MySQL)', null, $parts);
    return $out;
});
echo 'Snapshot baru: ' . $res['version'] . ' (' . $res['rows'] . " baris ST)\n";

// Kolom PIN AKSES di Master_Sales menyimpan PIN dalam teks biasa (tidak dipakai aplikasi) → dikosongkan.
$info = sheet_info_('Master_Sales');
if ($info && ($i = array_search('PIN AKSES', array_map('text_', $info['heads']), true)) !== false) {
    $n = db_()->exec('UPDATE `' . $info['tbl'] . '` SET `' . $info['cols'][$i] . "` = '' WHERE `" . $info['cols'][$i] . "` <> ''");
    echo "Kolom PIN AKSES dikosongkan ({$n} baris).\n";
}
// Tabel lama dari tool import yang tidak pernah dibaca aplikasi.
foreach (['tx_st', 'tx_so', 'tx_part'] as $t) db_()->exec('DROP TABLE IF EXISTS `' . $t . '`');
echo "Tabel lama tx_st / tx_so / tx_part (tidak terpakai) dihapus.\n";
$p = storage_prune_();
$after = 0; foreach (glob(storage_dir_('files') . '/*.json') ?: [] as $f) $after += filesize($f);
echo 'Ukuran storage/files sesudah: ' . round($after / 1048576, 1) . " MB (file dibersihkan: {$p['files']})\n";
echo 'Selesai dalam ' . round(microtime(true) - $t0, 1) . " detik.\n";
