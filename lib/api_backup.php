<?php
/** Backup DSM: dump semua tabel (JSON) + salin file storage -> storage/backups/Harian-*.zip, manifest SHA-256. */
require_once __DIR__ . '/data.php';

function backupDir_(): string { return storage_dir_('backups'); }
function backupLog_(string $status, string $id, $detail): void {
    ensureTable_('Backup_Log', ['WAKTU', 'JENIS', 'STATUS', 'FOLDER', 'KETERANGAN'], []);
    $info = sheet_info_('Backup_Log');
    if ($info && ($info['heads'][0] ?? '') === '') { $g = readGrid_('Backup_Log'); array_shift($g); putTable_('Backup_Log', ['WAKTU', 'JENIS', 'STATUS', 'FOLDER', 'KETERANGAN'], $g); }
    appendRow_('Backup_Log', [gmdate('Y-m-d\TH:i:s.000\Z'), 'HARIAN', $status, $id, mb_substr((string) $detail, 0, 1500)]);
}
function backupKeep_(): int { $k = (int) (prop_get_('BACKUP_KEEP') ?? 14); return max(2, min(60, $k ?: 14)); }

function backupRun_(): string {
    if (!class_exists('ZipArchive')) fail_('Ekstensi zip PHP belum aktif.');
    $name = 'Harian-' . gmdate('Y-m-d\TH-i-s') . '.zip';
    $path = backupDir_() . '/' . $name . '.tmp';
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) fail_('Tidak bisa membuat file backup.');
    $files = []; $tmp = [];
    $pdo = db_();
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $f = tempnam(sys_get_temp_dir(), 'bk'); $tmp[] = $f; $fh = fopen($f, 'w'); $n = 0;
        fwrite($fh, '{"table":' . json_encode($t) . ',"rows":[');
        $st = $pdo->query('SELECT * FROM `' . str_replace('`', '', $t) . '`');
        while ($r = $st->fetch()) { fwrite($fh, ($n++ ? ',' : '') . json_encode($r, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)); }
        fwrite($fh, ']}'); fclose($fh);
        $zip->addFile($f, 'db/' . $t . '.json'); $files[] = ['name' => 'db/' . $t . '.json', 'rows' => $n, 'sha256' => hash_file('sha256', $f)];
    }
    $dir = storage_dir_('files');
    foreach (glob($dir . '/*') ?: [] as $f) if (is_file($f)) { $zip->addFile($f, 'files/' . basename($f)); $files[] = ['name' => 'files/' . basename($f), 'sha256' => hash_file('sha256', $f)]; }
    foreach (glob(storage_dir_('info') . '/*') ?: [] as $f) if (is_file($f)) { $zip->addFile($f, 'info/' . basename($f)); $files[] = ['name' => 'info/' . basename($f), 'sha256' => hash_file('sha256', $f)]; } // lampiran Informasi
    $zip->addFromString('manifest.json', json_encode(['schema' => 1, 'finished' => gmdate('c'), 'status' => 'LENGKAP', 'files' => $files]));
    if (!$zip->close()) fail_('Backup gagal ditulis (disk penuh?).');
    foreach ($tmp as $f) @unlink($f);
    $z = new ZipArchive();
    if ($z->open($path) !== true || $z->numFiles !== count($files) + 1) fail_('Verifikasi backup gagal.');
    $z->close();
    rename($path, backupDir_() . '/' . $name);
    return $name;
}
function backupPrune_(): void {
    $l = glob(backupDir_() . '/Harian-*.zip') ?: []; rsort($l, SORT_STRING);
    foreach (array_slice($l, backupKeep_()) as $f) @unlink($f);
}
function backupExec_(): void {
    try { $n = backupRun_(); backupPrune_(); backupLog_('LENGKAP', $n, 'Selesai ' . round(filesize(backupDir_() . '/' . $n) / 1048576, 1) . ' MB'); }
    catch (Throwable $e) { backupLog_('GAGAL', '', $e->getMessage()); throw $e; }
}
/** Tombol lama dari versi Apps Script (sinkron folder Drive & AI Assistant). */
function apiSyncRunNow($token = null) { fail_('Sinkron folder Google Drive tidak dipakai lagi di DSM versi server. Gunakan menu Upload regional.'); }
function apiSyncSchedule($token = null, $on = null) { apiSyncRunNow(); }
function apiSyncRetry($token = null, $id = null) { apiSyncRunNow(); }
function apiSyncFileDownload($token = null, $id = null) { apiSyncRunNow(); }

function apiStartBackup($token) {
    guard_($token, true);
    with_lock_(function () { backupExec_(); });
    return backupStatus_();
}
function apiBackupSettings($token, $keep, $statusId = '') {
    $u = guard_($token, true); $keep = is_string($keep) ? (trim($keep) === "" ? NAN : (float) $keep) : $keep;
    if (!is_numeric($keep) || floor($keep) != $keep || $keep < 2 || $keep > 60) fail_('Retensi 2–60 backup harian.');
    prop_set_('BACKUP_KEEP', (string) (int) $keep);
    audit_($u['nik'], 'BACKUP_SETTINGS', 'Retensi ' . (int) $keep);
    return backupStatus_();
}
