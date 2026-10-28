<?php
/**
 * Notifikasi khusus & otomatis (Paket 24).
 * - Kirim khusus: pengirim memilih sasaran DALAM cakupannya (semua tim / per role / per region / orang tertentu),
 *   judul, isi, gambar (opsional), halaman tujuan, kirim sekarang atau dijadwalkan.
 * - Aturan otomatis (Admin): DOS menipis, ringkasan KPI mingguan, target SO belum diisi, data baru masuk.
 * - Pengiriman memakai pushSendTo_ (api_push.php); pengguna harus sudah mengaktifkan notifikasi di perangkatnya.
 * - Pengatur jadwal: php notif_cli.php tiap 5 menit (cron). Tanpa cron, pesan terjadwal dan aturan otomatis tidak jalan.
 */
require_once __DIR__ . '/api_push.php';
require_once __DIR__ . '/api_kpi.php';
require_once __DIR__ . '/api_kpikhusus.php';
require_once __DIR__ . '/api_commit.php';

const NT_PAGES = ['', 'beranda', 'customer', 'brand', 'tim', 'kpi', 'dos', 'peringkat', 'transaksi', 'analisa'];
const NT_RULES = [
    'DOS_MENIPIS'  => ['label' => 'DOS menipis / habis', 'desc' => 'Ke Sales pemilik dealer dan ASM-nya · maks. 1x per hari per orang', 'type' => 'insight', 'time' => '08:00', 'freq' => 'DAILY'],
    'KPI_MINGGUAN' => ['label' => 'Pengingat KPI mingguan', 'desc' => 'Tiap Senin · ke semua Sales dan ASM', 'type' => 'insight', 'time' => '08:00', 'freq' => 'WEEKLY'],
    'TARGET_SO'    => ['label' => 'Target SO belum diisi', 'desc' => 'Tanggal 3 tiap bulan · ke ASM yang timnya punya indikator SO tanpa target', 'type' => 'sistem', 'time' => '09:00', 'freq' => 'MONTHLY'],
    'KOMITMEN_NOTIF'    => ['label' => 'Notifikasi komitmen', 'desc' => 'Saat komitmen diberikan, dikomentari, atau dinilai · ke penerima/pemberi komitmen', 'type' => 'insight', 'time' => '', 'freq' => 'EVENT'],
    'KOMITMEN_MINGGUAN' => ['label' => 'Pengingat komitmen mingguan', 'desc' => 'Tiap Senin · belum ada komitmen minggu ini / komitmen minggu lalu belum dinilai', 'type' => 'insight', 'time' => '08:00', 'freq' => 'WEEKLY'],
    'DATA_BARU'    => ['label' => 'Data baru masuk', 'desc' => 'Setelah publikasi data selesai · ke Admin', 'type' => 'data', 'time' => '', 'freq' => 'EVENT'],
];

function ntSchema_(): void {
    static $done = false; if ($done) return;
    db_()->exec("CREATE TABLE IF NOT EXISTS _nt_msg (id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(12) NOT NULL, rule_key VARCHAR(30) NOT NULL DEFAULT '', sender VARCHAR(100) NOT NULL, title VARCHAR(120) NOT NULL, body VARCHAR(400) NOT NULL, image VARCHAR(80) NOT NULL DEFAULT '', base VARCHAR(255) NOT NULL DEFAULT '', link VARCHAR(30) NOT NULL DEFAULT '', important TINYINT NOT NULL DEFAULT 0, target LONGTEXT NULL, recipients INT NOT NULL DEFAULT 0, sent INT NOT NULL DEFAULT 0, status VARCHAR(12) NOT NULL, send_at VARCHAR(20) NOT NULL DEFAULT '', created_at VARCHAR(20) NOT NULL, KEY st (status, send_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db_()->exec("CREATE TABLE IF NOT EXISTS _nt_log (msg_id BIGINT NOT NULL, nik VARCHAR(100) NOT NULL, status VARCHAR(8) NOT NULL, detail VARCHAR(200) NOT NULL DEFAULT '', opened_at VARCHAR(20) NOT NULL DEFAULT '', PRIMARY KEY(msg_id,nik)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db_()->exec("CREATE TABLE IF NOT EXISTS _nt_rule (rule_key VARCHAR(30) NOT NULL PRIMARY KEY, on_ TINYINT NOT NULL DEFAULT 0, time_ VARCHAR(5) NOT NULL DEFAULT '', last_run VARCHAR(10) NOT NULL DEFAULT '', actor VARCHAR(100) NOT NULL DEFAULT '', updated_at VARCHAR(20) NOT NULL DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Aturan komitmen aktif secara bawaan (Admin bisa mematikan di menu Notifikasi).
    $seed = db_()->prepare('INSERT IGNORE INTO _nt_rule (rule_key,on_,time_,last_run,actor,updated_at) VALUES (?,?,?,?,?,?)');
    $seed->execute(['KOMITMEN_NOTIF', 1, '', '', 'SISTEM', date('Y-m-d H:i:s')]); $seed->execute(['KOMITMEN_MINGGUAN', 1, '08:00', '', 'SISTEM', date('Y-m-d H:i:s')]);
    $done = true;
}
function ntNow_(): string { return date('Y-m-d H:i:s'); }
function ntIsTop_(array $u): bool { return in_array($u['role'], ['ADMIN', 'SUPER ADMIN'], true) || str_starts_with((string) $u['role'], 'RGM'); }
function ntIsAdmin_(array $u): bool { return in_array($u['role'], ['ADMIN', 'SUPER ADMIN'], true); }

/** Orang dalam cakupan pengirim (tanpa dirinya), hanya akun aktif. */
function ntScope_(array $db, array $u): array {
    $people = $db['meta']['people']; $out = [];
    if ($u['role'] === 'SUPER ADMIN') $niks = array_map(fn($p) => (string) $p['nik'], $people);
    elseif ($u['role'] === 'ADMIN') { $regs = $u['adminRegions'] ?? []; $niks = []; foreach ($people as $p) if (in_array($p['wilayah'] ?? null, $regs, true)) $niks[] = (string) $p['nik']; }
    elseif (in_array($u['role'], ['SALES', 'TELEMARKETING'], true)) $niks = [];
    else $niks = array_map('strval', PortalCore::scope($people, $u['nik']));
    $seen = [];
    foreach ($niks as $n) {
        if ($n === (string) $u['nik'] || isset($seen[$n]) || $n === '-' || $n === '') continue; $seen[$n] = 1;
        $p = PortalCore::person($people, $n); if (!$p) continue;
        $a = account_($n); if (!$a || !PortalCore::active($a['AKTIF'])) continue;
        $out[$n] = ['nik' => $n, 'nama' => (string) $p['nama'], 'role' => (string) $p['role'], 'region' => (string) js_or($p['portfolio'] ?? null, js_or($p['region'] ?? null, '')), 'wilayah' => (string) js_or($p['wilayah'] ?? null, '')];
    }
    return $out;
}
function ntPushActive_(string $nik): bool {
    if (!pushOn_()) return false;
    $p = json_decode(prop_get_('PUSH_PREF_' . $nik) ?? '', true); if (!is_array($p) || empty($p['on'])) return false;
    $s = json_decode(prop_get_('PUSH_SUB_' . $nik) ?? '[]', true); return is_array($s) && count($s) > 0;
}
/** Terjemahkan sasaran ke daftar NIK dalam cakupan. */
function ntResolve_(array $scope, $t): array {
    $t = is_array($t) ? $t : []; $type = (string) ($t['type'] ?? 'all'); $out = [];
    foreach ($scope as $n => $p) {
        if ($type === 'all') $out[] = $n;
        elseif ($type === 'role' && $p['role'] === (string) ($t['role'] ?? '')) $out[] = $n;
        elseif ($type === 'region' && $p['region'] === (string) ($t['region'] ?? '')) $out[] = $n;
        elseif ($type === 'nik' && in_array($n, array_map('strval', (array) ($t['niks'] ?? [])), true)) $out[] = $n;
    }
    return $out;
}
function ntBaseUrl_(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? ''); if ($host === '') return defined('DSM_BASE_URL') ? rtrim(DSM_BASE_URL, '/') . '/' : '';
    $dir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    return ($https ? 'https://' : 'http://') . $host . $dir . '/';
}

function apiNotifState($token) {
    ntSchema_(); $db = snapshot_(); $u = guard_($token, false, false, $db);
    $scope = ntScope_($db, $u); $roles = []; $regions = []; $active = 0;
    foreach ($scope as $p) { $roles[$p['role']] = ($roles[$p['role']] ?? 0) + 1; if ($p['region'] !== '') $regions[$p['region']] = ($regions[$p['region']] ?? 0) + 1; }
    foreach ($scope as $n => $p) if (ntPushActive_($n)) $active++;
    ksort($roles); ksort($regions);
    $people = array_values(array_map(fn($p) => ['nik' => $p['nik'], 'nama' => $p['nama'], 'role' => $p['role'], 'region' => $p['region']], $scope));
    usort($people, fn($a, $b) => strcmp($a['nama'], $b['nama']));
    $cron = (int) (prop_get_('NT_CRON_AT') ?: 0);
    $out = ['canSend' => count($scope) > 0, 'total' => count($scope), 'active' => $active, 'roles' => $roles, 'regions' => $regions, 'people' => array_slice($people, 0, 800), 'important' => ntIsTop_($u), 'admin' => ntIsAdmin_($u), 'pushOn' => pushOn_(), 'cronOk' => $cron > 0 && time() - $cron < 900, 'pages' => NT_PAGES, 'me' => $u['nik']];
    if (ntIsAdmin_($u)) {
        $q = db_()->query('SELECT rule_key,on_,time_,last_run FROM _nt_rule'); $cur = []; foreach ($q->fetchAll() as $r) $cur[$r['rule_key']] = $r;
        $rules = []; foreach (NT_RULES as $k => $d) { $c = $cur[$k] ?? null; $rules[] = ['key' => $k, 'label' => $d['label'], 'desc' => $d['desc'], 'freq' => $d['freq'], 'on' => $c ? (bool) $c['on_'] : false, 'time' => $c && $c['time_'] !== '' ? $c['time_'] : $d['time'], 'lastRun' => $c['last_run'] ?? '']; }
        $out['rules'] = $rules;
    }
    return $out;
}
function apiNotifPreview($token, $target = null) {
    $db = snapshot_(); $u = guard_($token, false, false, $db); $scope = ntScope_($db, $u); $ids = ntResolve_($scope, $target); $act = 0;
    foreach ($ids as $n) if (ntPushActive_($n)) $act++;
    return ['count' => count($ids), 'active' => $act];
}

function ntSaveImage_($data): string {
    if (!is_string($data) || $data === '') return '';
    if (!preg_match('#^data:image/(jpeg|png);base64,([A-Za-z0-9+/=\r\n]+)$#', $data, $m)) fail_('Gambar harus JPG atau PNG.');
    $bin = base64_decode($m[2], true); if ($bin === false || strlen($bin) > 1048576) fail_('Gambar terlalu besar (maks. 1 MB).');
    $info = @getimagesizefromstring($bin); if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) fail_('File gambar tidak valid.');
    if ($info[0] > 2400 || $info[1] > 2400) fail_('Dimensi gambar maks. 2400 px.');
    $name = bin2hex(random_bytes(10)) . ($info[2] === IMAGETYPE_PNG ? '.png' : '.jpg');
    file_put_contents(storage_dir_('notif') . '/' . $name, $bin);
    return $name;
}

/** Kirim satu pesan ke daftar NIK; catat log; kembalikan jumlah terkirim. */
function ntDeliver_(int $id, array $msg, array $niks): int {
    $sent = 0; $ins = db_()->prepare('REPLACE INTO _nt_log (msg_id,nik,status,detail) VALUES (?,?,?,?)');
    $type = $msg['kind'] === 'KHUSUS' ? 'pengumuman' : (NT_RULES[$msg['rule_key']]['type'] ?? 'sistem');
    $url = './' . ($msg['link'] !== '' ? '?nt=' . $id . '#' . $msg['link'] : '?nt=' . $id);
    $img = $msg['image'] !== '' && $msg['base'] !== '' ? $msg['base'] . 'notif_img.php?f=' . rawurlencode($msg['image']) : '';
    foreach ($niks as $n) {
        try { $r = pushSendTo_((string) $n, $msg['title'], $msg['body'], $type, ['url' => $url, 'image' => $img, 'important' => (bool) $msg['important'], 'tag' => 'nt' . $id]); }
        catch (Throwable $e) { $r = 'Gagal: ' . $e->getMessage(); }
        $ok = preg_match('/^Terkirim ([1-9]\d*) dari/', $r) === 1; if ($ok) $sent++;
        $ins->execute([$id, (string) $n, $ok ? 'OK' : 'SKIP', mb_substr($r, 0, 190)]);
    }
    return $sent;
}
function ntInsert_(array $m): int {
    $q = db_()->prepare('INSERT INTO _nt_msg (kind,rule_key,sender,title,body,image,base,link,important,target,recipients,sent,status,send_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $q->execute([$m['kind'], $m['rule_key'] ?? '', $m['sender'], $m['title'], $m['body'], $m['image'] ?? '', $m['base'] ?? '', $m['link'] ?? '', (int) ($m['important'] ?? 0), $m['target'] ?? null, (int) ($m['recipients'] ?? 0), 0, $m['status'], $m['send_at'] ?? '', ntNow_()]);
    return (int) db_()->lastInsertId();
}

function apiNotifSend($token, $p = null) {
    ntSchema_(); $db = snapshot_(); $u = guard_($token, false, false, $db); $p = is_array($p) ? $p : [];
    $scope = ntScope_($db, $u); if (!$scope) fail_('Akun ini tidak punya sasaran dalam cakupan.');
    $title = trim((string) ($p['title'] ?? '')); $body = trim((string) ($p['body'] ?? ''));
    if ($title === '' || mb_strlen($title) > 60) fail_('Judul wajib diisi (maks. 60 karakter).');
    if ($body === '' || mb_strlen($body) > 160) fail_('Isi pesan wajib diisi (maks. 160 karakter).');
    $link = (string) ($p['link'] ?? ''); if (!in_array($link, NT_PAGES, true)) fail_('Halaman tujuan tidak dikenal.');
    $important = !empty($p['important']); if ($important && !ntIsTop_($u)) fail_('Tanda Penting hanya untuk Admin dan RGM.');
    $target = is_array($p['target'] ?? null) ? $p['target'] : ['type' => 'all'];
    $ids = ntResolve_($scope, $target); if (!$ids) fail_('Tidak ada penerima untuk sasaran ini.');
    $when = trim((string) ($p['sendAt'] ?? ''));
    if ($when !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/D', $when)) fail_('Format jadwal tidak valid.');
        if (strcmp($when, date('Y-m-d H:i')) <= 0) fail_('Jadwal harus di masa depan.');
        if (strcmp($when, date('Y-m-d H:i', time() + 30 * 86400)) > 0) fail_('Jadwal maksimal 30 hari ke depan.');
    }
    $image = ntSaveImage_($p['image'] ?? '');
    $id = ntInsert_(['kind' => 'KHUSUS', 'sender' => (string) $u['nik'], 'title' => $title, 'body' => $body, 'image' => $image, 'base' => ntBaseUrl_(), 'link' => $link, 'important' => $important ? 1 : 0, 'target' => json_encode(['t' => $target, 'niks' => $ids]), 'recipients' => count($ids), 'status' => $when !== '' ? 'JADWAL' : 'TERKIRIM', 'send_at' => $when]);
    if ($when !== '') return ['id' => $id, 'status' => 'JADWAL', 'recipients' => count($ids), 'sendAt' => $when];
    $msg = ['kind' => 'KHUSUS', 'rule_key' => '', 'title' => $title, 'body' => $body, 'image' => $image, 'base' => ntBaseUrl_(), 'link' => $link, 'important' => $important ? 1 : 0];
    $sent = ntDeliver_($id, $msg, $ids);
    db_()->prepare('UPDATE _nt_msg SET sent=? WHERE id=?')->execute([$sent, $id]);
    return ['id' => $id, 'status' => 'TERKIRIM', 'recipients' => count($ids), 'sent' => $sent, 'pushOn' => pushOn_()];
}
function apiNotifTest($token, $p = null) {
    ntSchema_(); $u = guard_($token); $p = is_array($p) ? $p : [];
    $title = trim((string) ($p['title'] ?? '')) ?: 'DSM'; $body = trim((string) ($p['body'] ?? '')) ?: 'Tes notifikasi.';
    $img = ''; if (!empty($p['image'])) { $name = ntSaveImage_($p['image']); $img = ntBaseUrl_() . 'notif_img.php?f=' . rawurlencode($name); }
    $link = (string) ($p['link'] ?? ''); if (!in_array($link, NT_PAGES, true)) $link = '';
    return ['hasil' => pushSendTo_((string) $u['nik'], mb_substr($title, 0, 60), mb_substr($body, 0, 160), 'pengumuman', ['url' => './' . ($link !== '' ? '#' . $link : ''), 'image' => $img, 'important' => true])];
}
function apiNotifHistory($token, $limit = 50) {
    ntSchema_(); $u = guard_($token); $limit = max(1, min(200, (int) $limit)); $super = $u['role'] === 'SUPER ADMIN';
    $op = '(SELECT COUNT(*) FROM _nt_log l WHERE l.msg_id=m.id AND l.opened_at<>\'\')';
    $q = db_()->prepare("SELECT m.id,m.kind,m.rule_key,m.sender,m.title,m.body,m.recipients,m.sent,m.status,m.send_at,m.created_at,$op AS opened FROM _nt_msg m WHERE m.kind='KHUSUS'" . ($super ? '' : ' AND m.sender=?') . " ORDER BY m.id DESC LIMIT $limit");
    $q->execute($super ? [] : [(string) $u['nik']]); $rows = $q->fetchAll();
    if ($super) { // pesan otomatis dirangkum per aturan per hari agar riwayat tidak penuh
        $a = db_()->query("SELECT MAX(m.id) AS id,'OTOMATIS' AS kind,m.rule_key,'SISTEM' AS sender,MAX(m.title) AS title,MAX(m.body) AS body,SUM(m.recipients) AS recipients,SUM(m.sent) AS sent,'TERKIRIM' AS status,'' AS send_at,MAX(m.created_at) AS created_at,SUM($op) AS opened,COUNT(*) AS n FROM _nt_msg m WHERE m.kind='OTOMATIS' GROUP BY m.rule_key,SUBSTR(m.created_at,1,10) ORDER BY id DESC LIMIT $limit");
        foreach ($a->fetchAll() as $r) { if ((int) $r['n'] > 1) { $r['title'] = (NT_RULES[$r['rule_key']]['label'] ?? $r['rule_key']) . ' (' . $r['n'] . ' pesan)'; $r['body'] = 'Dikirim otomatis ke masing-masing penerima.'; } $rows[] = $r; }
        usort($rows, fn($x, $y) => strcmp($y['created_at'], $x['created_at'])); $rows = array_slice($rows, 0, $limit);
    }
    $db = snapshot_(); $out = [];
    foreach ($rows as $r) { $s = PortalCore::person($db['meta']['people'], $r['sender']); $out[] = ['id' => (int) $r['id'], 'kind' => $r['kind'], 'rule' => $r['rule_key'], 'sender' => $s['nama'] ?? $r['sender'], 'title' => $r['title'], 'body' => $r['body'], 'recipients' => (int) $r['recipients'], 'sent' => (int) $r['sent'], 'opened' => (int) $r['opened'], 'status' => $r['status'], 'sendAt' => $r['send_at'], 'at' => $r['created_at']]; }
    return ['rows' => $out];
}

function apiNotifCancel($token, $id = null) {
    ntSchema_(); $u = guard_($token); $id = (int) $id;
    $q = db_()->prepare("UPDATE _nt_msg SET status='BATAL' WHERE id=? AND status='JADWAL' AND (sender=? OR ?=1)"); $q->execute([$id, (string) $u['nik'], $u['role'] === 'SUPER ADMIN' ? 1 : 0]);
    if (!$q->rowCount()) fail_('Pesan terjadwal tidak ditemukan.');
    return ['ok' => true];
}
function apiNotifOpened($token, $id = null) {
    ntSchema_(); $u = guard_($token); $id = (int) $id; if ($id <= 0) return ['ok' => false];
    db_()->prepare("UPDATE _nt_log SET opened_at=? WHERE msg_id=? AND nik=? AND opened_at=''")->execute([ntNow_(), $id, (string) $u['nik']]);
    return ['ok' => true];
}
function apiNotifRuleSave($token, $key = null, $on = null, $time = null) {
    ntSchema_(); $u = guard_($token); if (!ntIsAdmin_($u)) fail_('Hanya Admin yang boleh mengatur aturan otomatis.');
    $key = (string) $key; if (!isset(NT_RULES[$key])) fail_('Aturan tidak dikenal.');
    $time = (string) $time; if (NT_RULES[$key]['freq'] !== 'EVENT' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $time)) fail_('Jam tidak valid (HH:MM).');
    $lq = db_()->prepare('SELECT last_run FROM _nt_rule WHERE rule_key=?'); $lq->execute([$key]); $last = (string) ($lq->fetchColumn() ?: '');
    db_()->prepare('REPLACE INTO _nt_rule (rule_key,on_,time_,last_run,actor,updated_at) VALUES (?,?,?,?,?,?)')->execute([$key, $on ? 1 : 0, NT_RULES[$key]['freq'] === 'EVENT' ? '' : $time, $last, (string) $u['nik'], ntNow_()]);
    return ['ok' => true];
}

// ------------------------------------------------------------------
// Aturan otomatis (dijalankan notif_cli.php atau hook kejadian)
// ------------------------------------------------------------------
function ntRuleOn_(string $key): bool {
    ntSchema_(); $q = db_()->prepare('SELECT on_ FROM _nt_rule WHERE rule_key=?'); $q->execute([$key]); return (bool) $q->fetchColumn();
}
function ntAutoSend_(string $key, string $title, string $body, string $link, array $niks): int {
    $niks = array_values(array_unique(array_map('strval', $niks))); if (!$niks) return 0;
    $id = ntInsert_(['kind' => 'OTOMATIS', 'rule_key' => $key, 'sender' => 'SISTEM', 'title' => $title, 'body' => $body, 'link' => $link, 'recipients' => count($niks), 'status' => 'TERKIRIM']);
    $sent = ntDeliver_($id, ['kind' => 'OTOMATIS', 'rule_key' => $key, 'title' => $title, 'body' => $body, 'image' => '', 'base' => '', 'link' => $link, 'important' => 0], $niks);
    db_()->prepare('UPDATE _nt_msg SET sent=? WHERE id=?')->execute([$sent, $id]); return $id;
}
function ntActiveByRole_(array $db, array $roles): array {
    $out = []; foreach ($db['meta']['people'] as $p) { if (!in_array($p['role'], $roles, true)) continue; $a = account_($p['nik']); if ($a && PortalCore::active($a['AKTIF'])) $out[] = (string) $p['nik']; } return $out;
}
/** Jalankan satu aturan. Kembalikan jumlah pesan terkirim. */
function ntRunRule_(string $key): int {
    $db = snapshot_(); $cov = array_keys($db['coverage'] ?? []); sort($cov); $period = (string) (end($cov) ?: ''); $people = $db['meta']['people'];
    if ($key === 'KPI_MINGGUAN') {
        return ntAutoSend_($key, 'KPI minggu ini', 'Cek skor dan peringkat KPI kamu, lalu kejar indikator yang masih merah.', 'kpi', ntActiveByRole_($db, ['SALES', 'ASM'])) ? 1 : 0;
    }
    if ($key === 'KOMITMEN_MINGGUAN') return function_exists('commitReminders_') ? commitReminders_() : 0;
    if ($key === 'DATA_BARU') {
        return ntAutoSend_($key, 'Data baru masuk', 'Publikasi data terbaru selesai. Angka sudah diperbarui.', 'beranda', ntActiveByRole_($db, ['ADMIN', 'SUPER ADMIN'])) ? 1 : 0;
    }
    if ($key === 'TARGET_SO') {
        if ($period === '' || !function_exists('kkRows_')) return 0; $rows = kkRows_($period); $miss = [];
        foreach ($rows as $nik => $c) {
            $p = PortalCore::person($people, $nik); if (!$p || $p['role'] !== 'SALES') continue; $asm = targetAsm_($people, $p); if (!$asm) continue;
            foreach ($c['items'] as $it) { if ($it['m'] !== 'SO') continue; if (!kkSoTarget_($db, $period, [$nik], $it['b'])['exists']) { $miss[(string) $asm['nik']][(string) $nik] = 1; break; } }
        }
        $n = 0; foreach ($miss as $asm => $list) if (ntAutoSend_($key, 'Target SO belum diisi', count($list) . ' sales di timmu belum punya target SO bulan ini.', 'kpi', [$asm])) $n++;
        return $n;
    }
    if ($key === 'DOS_MENIPIS') {
        if ($period === '') return 0; $ids = array_map(fn($p) => (string) $p['nik'], $people);
        $v = coverageView_($db, $period, $ids, 'PAIR', null);
        // Region yang datanya belum lengkap tidak ikut dihitung (sama dengan menu DOS), region lain tetap diproses.
        $r = StockCore::calculate($v, $period, $ids, []); if (empty($r['ready'])) return 0;
        $per = []; foreach ($r['rows'] as $t) { if (!in_array($t['status'], ['MENIPIS', 'HABIS'], true)) continue; $o = (string) ($t['nik'] ?? ''); if ($o === '') continue; $per[$o][(string) $t['customer']] = 1; }
        $asmCount = []; foreach ($per as $o => $d) { $pp = PortalCore::person($people, $o); $asm = $pp ? targetAsm_($people, $pp) : null; if ($asm && (string) $asm['nik'] !== (string) $o) foreach ($d as $c => $_) $asmCount[(string) $asm['nik']][$c] = 1; }
        $n = 0;
        foreach ($per as $o => $d) { $a = account_($o); if (!$a || !PortalCore::active($a['AKTIF'])) continue; if (ntAutoSend_($key, 'DOS menipis: ' . count($d) . ' dealer', 'Ada ' . count($d) . ' dealer milikmu yang stoknya menipis atau habis. Cek menu DOS.', 'dos', [$o])) $n++; }
        foreach ($asmCount as $o => $d) { if (isset($per[$o])) continue; $a = account_($o); if (!$a || !PortalCore::active($a['AKTIF'])) continue; if (ntAutoSend_($key, 'DOS menipis di timmu', count($d) . ' dealer di timmu stoknya menipis atau habis. Cek menu DOS.', 'dos', [$o])) $n++; }
        return $n;
    }
    return 0;
}
/** Dipanggil dari hook kejadian (mis. setelah publikasi data). Tidak pernah melempar error. */
function ntEvent_(string $key): void {
    try { if (pushOn_() && ntRuleOn_($key)) ntRunRule_($key); } catch (Throwable $e) {}
}
/** Satu putaran pengatur jadwal. */
function ntTick_(): array {
    ntSchema_(); prop_set_('NT_CRON_AT', (string) time()); $log = [];
    $q = db_()->prepare("SELECT * FROM _nt_msg WHERE status='JADWAL' AND send_at<=? ORDER BY id LIMIT 20"); $q->execute([date('Y-m-d H:i')]);
    foreach ($q->fetchAll() as $m) {
        $upd = db_()->prepare("UPDATE _nt_msg SET status='MENGIRIM' WHERE id=? AND status='JADWAL'"); $upd->execute([$m['id']]); if (!$upd->rowCount()) continue;
        $t = json_decode((string) $m['target'], true); $niks = (array) ($t['niks'] ?? []);
        $sent = ntDeliver_((int) $m['id'], $m, $niks);
        db_()->prepare("UPDATE _nt_msg SET status='TERKIRIM', sent=? WHERE id=?")->execute([$sent, $m['id']]); $log[] = 'jadwal#' . $m['id'] . ':' . $sent;
    }
    $now = date('H:i'); $today = date('Y-m-d'); $dow = (int) date('N'); $dom = (int) date('j');
    $rs = db_()->query('SELECT rule_key,time_,last_run FROM _nt_rule WHERE on_=1')->fetchAll();
    foreach ($rs as $r) {
        $d = NT_RULES[$r['rule_key']] ?? null; if (!$d || $d['freq'] === 'EVENT' || $r['last_run'] === $today || strcmp($now, $r['time_']) < 0) continue;
        if ($d['freq'] === 'WEEKLY' && $dow !== 1) continue; if ($d['freq'] === 'MONTHLY' && $dom !== 3) continue;
        db_()->prepare('UPDATE _nt_rule SET last_run=? WHERE rule_key=?')->execute([$today, $r['rule_key']]);
        try { $log[] = $r['rule_key'] . ':' . ntRunRule_($r['rule_key']); } catch (Throwable $e) { $log[] = $r['rule_key'] . ':ERR ' . $e->getMessage(); }
    }
    return $log;
}
