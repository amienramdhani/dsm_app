<?php
/**
 * Agent API (bot Telegram tim IT) — Paket 1: fondasi.
 *
 * - BOT_KEY: disimpan sebagai hash (hash_ + PEPPER), bisa dicabut.
 * - Kode hubungkan 6 digit: berlaku 5 menit, sekali pakai, disimpan sebagai hash.
 * - Sambungan Telegram -> NIK: berlaku TETAP 30 hari (tidak geser), semua role.
 *   Putus otomatis bila akun nonaktif atau VERSI akun berubah (ganti/reset PIN).
 * - Pemanggilan fungsi baca DSM memakai sesi internal sekali-pakai (flag agent=1),
 *   jadi filter role sama persis dengan aplikasi web. Sesi dihapus setelah request.
 *   Sesi ini TIDAK didaftarkan sebagai perangkat (tidak mengusir perangkat web user).
 */

const AGENT_LINK_DAYS = 30;
const AGENT_CODE_MS = 300000;          // 5 menit
const AGENT_RATE_TG = 30;              // request / menit per Telegram ID
const AGENT_RATE_KEY = 300;            // request / menit per BOT_KEY
const AGENT_CODE_TRIES = 5;            // salah kode / 10 menit per Telegram ID sebelum diblok

class AgentError extends Exception {
    public int $http; public string $codeName; public string $why = '';
    public function __construct(int $http, string $code, string $msg, string $why = '') { parent::__construct($msg); $this->http = $http; $this->codeName = $code; $this->why = $why; }
}
function agentFail_(int $http, string $code, string $msg, string $why = ''): never { throw new AgentError($http, $code, $msg, $why); }

function agentSchema_(): void {
    static $done = false; if ($done) return;
    $pdo = db_();
    $o = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
    $pdo->exec("CREATE TABLE IF NOT EXISTS dsm_agent_key (id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, key_hash VARCHAR(64) NOT NULL, label VARCHAR(100) NOT NULL DEFAULT '', active TINYINT NOT NULL DEFAULT 1, created_by VARCHAR(100) NOT NULL DEFAULT '', created_at VARCHAR(40) NOT NULL DEFAULT '', revoked_by VARCHAR(100) NOT NULL DEFAULT '', revoked_at VARCHAR(40) NOT NULL DEFAULT '', last_used_ms BIGINT NOT NULL DEFAULT 0, UNIQUE KEY uq_hash (key_hash)) $o");
    $pdo->exec("CREATE TABLE IF NOT EXISTS dsm_tg_code (code_hash VARCHAR(64) NOT NULL PRIMARY KEY, nik VARCHAR(100) NOT NULL, until_ms BIGINT NOT NULL, used TINYINT NOT NULL DEFAULT 0, KEY idx_nik (nik)) $o");
    $pdo->exec("CREATE TABLE IF NOT EXISTS dsm_tg_link (telegram_id VARCHAR(32) NOT NULL PRIMARY KEY, nik VARCHAR(100) NOT NULL, username VARCHAR(100) NOT NULL DEFAULT '', versi INT NOT NULL DEFAULT 0, linked_ms BIGINT NOT NULL, expires_ms BIGINT NOT NULL, last_used_ms BIGINT NOT NULL DEFAULT 0, revoked_ms BIGINT NOT NULL DEFAULT 0, revoked_by VARCHAR(100) NOT NULL DEFAULT '', revoke_reason VARCHAR(200) NOT NULL DEFAULT '', KEY idx_nik (nik)) $o");
    $pdo->exec("CREATE TABLE IF NOT EXISTS dsm_agent_log (id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, at_ms BIGINT NOT NULL, key_id BIGINT NOT NULL DEFAULT 0, telegram_id VARCHAR(32) NOT NULL DEFAULT '', nik VARCHAR(100) NOT NULL DEFAULT '', resource VARCHAR(40) NOT NULL DEFAULT '', params VARCHAR(500) NOT NULL DEFAULT '', status VARCHAR(20) NOT NULL DEFAULT '', ms INT NOT NULL DEFAULT 0, KEY idx_tg (telegram_id, at_ms), KEY idx_key (key_id, at_ms), KEY idx_at (at_ms)) $o");
    $done = true;
}

// ---------------- BOT_KEY ----------------

/** Buat BOT_KEY baru. Nilai asli hanya dikembalikan SEKALI. */
function agentKeyCreate_(string $label, string $by): array {
    agentSchema_();
    $plain = 'dsmk_' . bin2hex(random_bytes(24));
    db_()->prepare('INSERT INTO dsm_agent_key (key_hash, label, active, created_by, created_at) VALUES (?,?,1,?,?)')->execute([hash_('AGENTKEY|' . $plain), mb_substr($label, 0, 100), $by, iso_now()]);
    audit_($by, 'AGENT_KEY_CREATE', $label);
    return ['id' => (int) db_()->lastInsertId(), 'label' => $label, 'key' => $plain];
}
function agentKeyRevoke_(int $id, string $by): void {
    agentSchema_();
    db_()->prepare('UPDATE dsm_agent_key SET active=0, revoked_by=?, revoked_at=? WHERE id=?')->execute([$by, iso_now(), $id]);
    audit_($by, 'AGENT_KEY_REVOKE', (string) $id);
}
function agentKeyCheck_(?string $header): int {
    agentSchema_();
    // Alasan penolakan hanya masuk log (kolom status), bukan ke jawaban: pesan ke pemanggil tetap sama.
    $bad = fn(string $why) => agentFail_(401, 'BAD_KEY', 'BOT_KEY tidak valid.', $why);
    if (!is_string($header) || trim($header) === '') $bad('BAD_KEY:NO_HEADER');       // header Authorization tidak sampai ke PHP
    if (!preg_match('/^Bearer\s+(dsmk_[a-f0-9]{48})$/', trim($header), $m)) $bad('BAD_KEY:FORMAT'); // ada, tapi bukan "Bearer dsmk_<48 hex>"
    $st = db_()->prepare('SELECT id, active FROM dsm_agent_key WHERE key_hash=?'); $st->execute([hash_('AGENTKEY|' . $m[1])]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) $bad('BAD_KEY:NOT_FOUND');                                              // format benar, hash tidak ada di database ini
    if ((int) $row['active'] !== 1) $bad('BAD_KEY:REVOKED');                           // key ada tapi sudah dicabut
    $id = (int) $row['id'];
    db_()->prepare('UPDATE dsm_agent_key SET last_used_ms=? WHERE id=?')->execute([now_ms(), $id]);
    return $id;
}

// ---------------- Kode hubungkan ----------------

/** Kode 6 digit untuk NIK (dipanggil dari halaman Telegram Agent). Kode lama milik NIK yang sama dihapus. */
function agentCodeIssue_(string $nik): array {
    agentSchema_(); $pdo = db_();
    $pdo->prepare('DELETE FROM dsm_tg_code WHERE nik=? OR until_ms < ?')->execute([$nik, now_ms()]);
    for ($i = 0; $i < 10; $i++) {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        try { $pdo->prepare('INSERT INTO dsm_tg_code (code_hash, nik, until_ms) VALUES (?,?,?)')->execute([hash_('TGCODE|' . $code), $nik, now_ms() + AGENT_CODE_MS]); return ['code' => $code, 'until_ms' => now_ms() + AGENT_CODE_MS]; }
        catch (PDOException $e) { /* bentrok hash kode aktif lain: ulangi */ }
    }
    fail_('Gagal membuat kode. Coba lagi.');
}

function agentPerson_(string $nik): ?array {
    try { return PortalCore::person(snapshot_()['meta']['people'], $nik); } catch (Throwable $e) { return null; }
}

/** Label cakupan untuk ditampilkan. Role tingkat pusat = Nasional. */
function agentScopeLabel_(?array $p): string {
    if (!$p) return '';
    if (in_array((string) ($p['role'] ?? ''), ['SUPER ADMIN', 'ADMIN', 'HEAD OF SALES', 'BOARD OF DIRECTOR', 'CHIEF OPERATING OFFICER', 'CHIEF COMMERCIAL OFFICER'], true)) return 'Nasional';
    foreach (['portfolio', 'region', 'wilayah'] as $k) if (!empty($p[$k])) return (string) $p[$k];
    return '';
}

function agentLinkInfo_(array $link): array {
    $p = agentPerson_((string) $link['nik']) ?? [];
    $sisa = (int) max(0, ceil(((int) $link['expires_ms'] - now_ms()) / 86400000));
    return [
        'nama' => (string) ($p['nama'] ?? $link['nik']),
        'role' => (string) ($p['role'] ?? ''),
        'cakupan' => agentScopeLabel_($p ?: null),
        'berlaku_sampai' => date('Y-m-d', intdiv((int) $link['expires_ms'], 1000)),
        'sisa_hari' => $sisa,
    ];
}

/** Hubungkan Telegram ID memakai kode. Satu Telegram ID = satu NIK; menimpa sambungan lama milik ID tsb. */
function agentLinkWithCode_(string $tgId, string $code, string $username): array {
    agentSchema_(); $pdo = db_();
    $st = $pdo->prepare("SELECT COUNT(*) FROM dsm_agent_log WHERE telegram_id=? AND resource='link' AND status='BAD_CODE' AND at_ms > ?");
    $st->execute([$tgId, now_ms() - 600000]);
    if ((int) $st->fetchColumn() >= AGENT_CODE_TRIES) agentFail_(429, 'RATE_LIMIT', 'Terlalu banyak kode salah. Coba lagi 10 menit lagi.');
    if (!preg_match('/^\d{6}$/', $code)) agentFail_(400, 'BAD_CODE', 'Kode harus 6 angka. Ambil kode di DSM: klik nama Anda > Telegram Agent.');
    $h = hash_('TGCODE|' . $code);
    $st = $pdo->prepare('SELECT nik, until_ms, used FROM dsm_tg_code WHERE code_hash=?'); $st->execute([$h]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int) $row['used'] === 1 || (int) $row['until_ms'] < now_ms()) agentFail_(400, 'BAD_CODE', 'Kode salah, sudah dipakai, atau kedaluwarsa. Ambil kode baru di DSM: klik nama Anda > Telegram Agent.');
    // Tandai terpakai secara atomik (dua request bersamaan tidak bisa sama-sama berhasil).
    $up = $pdo->prepare('UPDATE dsm_tg_code SET used=1 WHERE code_hash=? AND used=0'); $up->execute([$h]);
    if ($up->rowCount() !== 1) agentFail_(400, 'BAD_CODE', 'Kode sudah dipakai. Ambil kode baru di DSM: klik nama Anda > Telegram Agent.');
    $nik = (string) $row['nik'];
    $a = account_($nik);
    if (!$a || !PortalCore::active($a['AKTIF'])) agentFail_(403, 'LINK_REVOKED', 'Akun DSM tidak aktif.');
    $now = now_ms(); $exp = $now + AGENT_LINK_DAYS * 86400000;
    $pdo->prepare('REPLACE INTO dsm_tg_link (telegram_id, nik, username, versi, linked_ms, expires_ms, last_used_ms, revoked_ms, revoked_by, revoke_reason) VALUES (?,?,?,?,?,?,?,0,\'\',\'\')')
        ->execute([$tgId, $nik, mb_substr($username, 0, 100), (int) num_($a['VERSI']), $now, $exp, $now]);
    audit_($nik, 'TG_LINK', $tgId . ($username !== '' ? ' @' . $username : ''));
    return agentLinkInfo_(['nik' => $nik, 'expires_ms' => $exp]);
}

/** Putuskan satu sambungan (user sendiri / Admin). */
function agentLinkRevoke_(string $tgId, string $by, string $reason): void {
    agentSchema_();
    db_()->prepare('UPDATE dsm_tg_link SET revoked_ms=?, revoked_by=?, revoke_reason=? WHERE telegram_id=? AND revoked_ms=0')->execute([now_ms(), $by, mb_substr($reason, 0, 200), $tgId]);
    audit_($by, 'TG_UNLINK', $tgId . ' ' . $reason);
}
/** Putuskan SEMUA sambungan (khusus Super Admin, darurat). */
function agentLinkRevokeAll_(string $by, string $reason): int {
    agentSchema_();
    $st = db_()->prepare('UPDATE dsm_tg_link SET revoked_ms=?, revoked_by=?, revoke_reason=? WHERE revoked_ms=0'); $st->execute([now_ms(), $by, mb_substr($reason, 0, 200)]);
    audit_($by, 'TG_UNLINK_ALL', $reason);
    return $st->rowCount();
}

/** Ambil sambungan aktif untuk Telegram ID, atau gagal dengan kode yang jelas. Mengecek akun aktif & VERSI. */
function agentLinkActive_(string $tgId): array {
    agentSchema_();
    $st = db_()->prepare('SELECT * FROM dsm_tg_link WHERE telegram_id=?'); $st->execute([$tgId]);
    $l = $st->fetch(PDO::FETCH_ASSOC);
    if (!$l) agentFail_(403, 'NOT_LINKED', 'Akun Telegram ini belum terhubung. Buka DSM, klik nama Anda di pojok kiri bawah (di HP: menu Lainnya), pilih Telegram Agent, lalu kirim kodenya ke bot.');
    if ((int) $l['revoked_ms'] > 0) agentFail_(403, 'LINK_REVOKED', 'Sambungan DSM sudah diputus. Hubungkan ulang dari DSM: klik nama Anda > Telegram Agent.');
    if ((int) $l['expires_ms'] < now_ms()) agentFail_(403, 'LINK_EXPIRED', 'Sesi 30 hari sudah habis. Hubungkan ulang dari DSM: klik nama Anda > Telegram Agent.');
    $a = account_((string) $l['nik']);
    $reason = !$a ? 'akun tidak ditemukan' : (!PortalCore::active($a['AKTIF']) ? 'akun nonaktif' : ((int) num_($a['VERSI']) !== (int) $l['versi'] ? 'PIN diganti/direset' : ''));
    if ($reason !== '') { agentLinkRevoke_($tgId, 'SISTEM', $reason); agentFail_(403, 'LINK_REVOKED', 'Sambungan DSM terputus (' . $reason . '). Hubungkan ulang dari DSM: klik nama Anda > Telegram Agent.'); }
    db_()->prepare('UPDATE dsm_tg_link SET last_used_ms=? WHERE telegram_id=?')->execute([now_ms(), $tgId]);
    $l['_account'] = $a;
    return $l;
}

// ---------------- Sesi internal ----------------

/** Jalankan fungsi baca DSM atas nama user sambungan, dengan sesi sekali pakai. */
function agentCall_(array $link, string $fn, array $args) {
    $a = $link['_account'];
    $p = agentPerson_((string) $link['nik']);
    $token = hash_(uuid_() . uuid_() . now_ms());
    $key = 'SESSION_' . hash_($token);
    session_put_($key, ['nik' => (string) $link['nik'], 'version' => (int) num_($a['VERSI']), 'until' => now_ms() + 300000, 'role' => (string) ($p['role'] ?? ''), 'deviceId' => 'AGENT_TG', 'touched' => now_ms(), 'agent' => 1]);
    try {
        // Sama seperti api.php: siapkan konteks Telemarketing (pembatasan pasangan dealer-brand + daftar menu TM).
        if (function_exists('tmBeginRequest_')) { unset($GLOBALS['DSM_TM_CONTEXT']); tmBeginRequest_($fn, array_merge([$token], $args)); }
        $rf = new ReflectionFunction($fn); $all = array_merge([$token], $args);
        while (count($all) < $rf->getNumberOfParameters()) $all[] = null;
        return $fn(...$all);
    }
    finally { db_()->prepare('DELETE FROM _sessions WHERE k=?')->execute([$key]); }
}

// ---------------- Rate limit & log ----------------

function agentRate_(int $keyId, string $tgId): void {
    $since = now_ms() - 60000; $pdo = db_();
    $st = $pdo->prepare('SELECT COUNT(*) FROM dsm_agent_log WHERE key_id=? AND at_ms>?'); $st->execute([$keyId, $since]);
    if ((int) $st->fetchColumn() >= AGENT_RATE_KEY) agentFail_(429, 'RATE_LIMIT', 'Terlalu banyak permintaan. Coba lagi sebentar.');
    if ($tgId === '') return;
    $st = $pdo->prepare('SELECT COUNT(*) FROM dsm_agent_log WHERE telegram_id=? AND at_ms>?'); $st->execute([$tgId, $since]);
    if ((int) $st->fetchColumn() >= AGENT_RATE_TG) agentFail_(429, 'RATE_LIMIT', 'Terlalu banyak pertanyaan dalam semenit. Coba lagi sebentar.');
}

function agentLog_(int $keyId, string $tgId, string $nik, string $res, array $params, string $status, int $ms): void {
    try {
        agentSchema_();
        unset($params['code']); // kode hubungkan tidak pernah dicatat
        db_()->prepare('INSERT INTO dsm_agent_log (at_ms, key_id, telegram_id, nik, resource, params, status, ms) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([now_ms(), $keyId, $tgId, $nik, mb_substr($res, 0, 40), mb_substr(json_encode($params, JSON_UNESCAPED_UNICODE) ?: '', 0, 500), $status, $ms]);
        if (random_int(1, 500) === 1) db_()->prepare('DELETE FROM dsm_agent_log WHERE at_ms < ?')->execute([now_ms() - 180 * 86400000]);
    } catch (Throwable $e) { error_log('[dsm-agent] log gagal: ' . $e->getMessage()); }
}

// ---------------- Penyamaran kontak ----------------

/** 081234567890 -> 0812****890. Dipakai Paket 3 untuk semua kolom telepon. */
function agentMaskPhone_($v): string {
    $s = preg_replace('/\D/', '', (string) $v);
    if (strlen($s) < 7) return $s === '' ? '' : str_repeat('*', strlen($s));
    return substr($s, 0, 4) . '****' . substr($s, -3);
}

// =====================================================================
// Paket 2: endpoint aplikasi DSM (lewat api.php, sesi login biasa)
// =====================================================================

function agentUser_($token): array {
    $u = guard_($token);
    if (!empty($u['demo'])) fail_('Telegram Agent tidak tersedia di Mode Demo.');
    return $u;
}
function agentAdmin_($token, bool $super = false): array {
    $u = agentUser_($token);
    if ($super ? $u['role'] !== 'SUPER ADMIN' : !in_array($u['role'], ['ADMIN', 'SUPER ADMIN'], true)) fail_($super ? 'Khusus Super Admin.' : 'Akses admin diperlukan.');
    return $u;
}
function agentStatus_(array $l): array {
    $now = now_ms(); $exp = (int) $l['expires_ms'];
    if ((int) $l['revoked_ms'] > 0) {
        $r = (string) $l['revoke_reason'];
        $lbl = stripos($r, 'PIN') !== false ? 'PIN DIGANTI' : (stripos($r, 'nonaktif') !== false ? 'AKUN NONAKTIF' : 'DIPUTUS');
        return ['kode' => 'OFF', 'label' => $lbl];
    }
    if ($exp < $now) return ['kode' => 'OFF', 'label' => 'HABIS'];
    $d = (int) ceil(($exp - $now) / 86400000);
    return $d <= 3 ? ['kode' => 'WARN', 'label' => 'HABIS ' . $d . ' HARI'] : ['kode' => 'OK', 'label' => 'AKTIF'];
}

/** Status Telegram milik user yang login. */
function apiTgState($token) {
    $u = agentUser_($token); agentSchema_();
    $st = db_()->prepare('SELECT * FROM dsm_tg_link WHERE nik=? AND revoked_ms=0 AND expires_ms>? ORDER BY linked_ms DESC LIMIT 1');
    $st->execute([$u['nik'], now_ms()]); $l = $st->fetch(PDO::FETCH_ASSOC);
    $base = ['role' => $u['role'], 'cakupan' => agentScopeLabel_($u), 'hari' => AGENT_LINK_DAYS, 'bot' => agentBotName_()];
    if (!$l) return $base + ['linked' => false];
    // Sambungan dengan VERSI lama (PIN sudah diganti) dianggap tidak tersambung.
    $a = account_((string) $u['nik']);
    if ($a && (int) num_($a['VERSI']) !== (int) $l['versi']) { agentLinkRevoke_((string) $l['telegram_id'], 'SISTEM', 'PIN diganti/direset'); return $base + ['linked' => false]; }
    return $base + ['linked' => true, 'telegramId' => $l['telegram_id'], 'username' => $l['username'], 'linkedMs' => (int) $l['linked_ms'], 'expiresMs' => (int) $l['expires_ms'], 'lastUsedMs' => (int) $l['last_used_ms'], 'sisaHari' => (int) ceil(((int) $l['expires_ms'] - now_ms()) / 86400000)];
}
/** Buat kode hubungkan 6 digit (5 menit). */
function apiTgCode($token) {
    $u = agentUser_($token);
    $c = agentCodeIssue_((string) $u['nik']);
    return ['code' => $c['code'], 'untilMs' => $c['until_ms'], 'ttlMs' => AGENT_CODE_MS];
}
/** Putuskan sambungan Telegram milik sendiri. */
function apiTgUnlink($token) {
    $u = agentUser_($token); agentSchema_();
    $st = db_()->prepare('SELECT telegram_id FROM dsm_tg_link WHERE nik=? AND revoked_ms=0'); $st->execute([$u['nik']]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) agentLinkRevoke_((string) $id, (string) $u['nik'], 'diputus pemilik akun');
    return apiTgState($token);
}
/** Daftar sambungan (Admin & Super Admin). */
function apiTgAdminList($token) {
    $u = agentAdmin_($token); agentSchema_();
    $people = snapshot_()['meta']['people'] ?? [];
    $now = now_ms();
    $rows = db_()->query('SELECT * FROM dsm_tg_link ORDER BY last_used_ms DESC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
    $out = []; $cnt = ['aktif' => 0, 'segera' => 0, 'off' => 0];
    foreach ($rows as $l) {
        $p = PortalCore::person($people, $l['nik']); $sts = agentStatus_($l);
        if ($sts['kode'] === 'OK') $cnt['aktif']++; elseif ($sts['kode'] === 'WARN') { $cnt['aktif']++; $cnt['segera']++; } else $cnt['off']++;
        $out[] = ['telegramId' => $l['telegram_id'], 'username' => $l['username'], 'nik' => $l['nik'], 'nama' => (string) ($p['nama'] ?? $l['nik']), 'role' => (string) ($p['role'] ?? ''),
            'linkedMs' => (int) $l['linked_ms'], 'expiresMs' => (int) $l['expires_ms'], 'lastUsedMs' => (int) $l['last_used_ms'], 'status' => $sts];
    }
    usort($out, fn($a, $b) => [$a['status']['kode'] === 'OFF', -$a['lastUsedMs']] <=> [$b['status']['kode'] === 'OFF', -$b['lastUsedMs']]);
    $st = db_()->prepare("SELECT COUNT(*) FROM dsm_agent_log WHERE at_ms >= ? AND status='OK' AND resource NOT IN ('link','akan_habis','health')");
    $st->execute([strtotime('today') * 1000]);
    return ['rows' => $out, 'count' => $cnt + ['hariIni' => (int) $st->fetchColumn()], 'super' => $u['role'] === 'SUPER ADMIN', 'now' => $now];
}
function apiTgAdminUnlink($token, $telegramId) {
    $u = agentAdmin_($token);
    $id = preg_replace('/\D/', '', (string) $telegramId); if ($id === '') fail_('Telegram ID tidak valid.');
    agentLinkRevoke_($id, (string) $u['nik'], 'diputus Admin ' . $u['nama']);
    return apiTgAdminList($token);
}
function apiTgAdminUnlinkAll($token, $reason) {
    $u = agentAdmin_($token, true);
    $r = trim((string) $reason); if (mb_strlen($r) < 5) fail_('Alasan wajib diisi (minimal 5 huruf).');
    $n = agentLinkRevokeAll_((string) $u['nik'], 'Putuskan semua: ' . $r);
    return ['putus' => $n] + apiTgAdminList($token);
}
/** BOT_KEY (khusus Super Admin). */
function apiAgentKeyList($token) {
    agentAdmin_($token, true); agentSchema_();
    return ['bot' => agentBotName_(), 'keys' => array_map(fn($r) => ['id' => (int) $r['id'], 'label' => $r['label'], 'active' => (int) $r['active'] === 1, 'createdBy' => $r['created_by'], 'createdAt' => $r['created_at'], 'revokedAt' => $r['revoked_at'], 'lastUsedMs' => (int) $r['last_used_ms']],
        db_()->query('SELECT * FROM dsm_agent_key ORDER BY active DESC, id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC))];
}
function apiAgentKeyCreate($token, $label) {
    $u = agentAdmin_($token, true);
    $l = trim((string) $label); if ($l === '') fail_('Isi label, contoh: Bot Telegram IT.');
    $k = agentKeyCreate_($l, (string) $u['nik']);
    return ['baru' => $k, 'list' => apiAgentKeyList($token)];
}
function apiAgentKeyRevoke($token, $id) {
    $u = agentAdmin_($token, true);
    agentKeyRevoke_((int) $id, (string) $u['nik']);
    return apiAgentKeyList($token);
}
/** Username bot Telegram (diisi Super Admin, dipakai tombol "Buka Bot"). */
function agentBotName_(): string { return preg_replace('/[^A-Za-z0-9_]/', '', (string) (prop_get_('TG_BOT_USERNAME') ?? '')); }
function apiTgBotSave($token, $username) {
    $u = agentAdmin_($token, true);
    $n = preg_replace('/[^A-Za-z0-9_]/', '', ltrim(trim((string) $username), '@'));
    prop_set_('TG_BOT_USERNAME', $n); audit_($u['nik'], 'TG_BOT_SET', $n);
    return ['bot' => $n];
}

// =====================================================================
// Paket 3: resource data (baca-saja) untuk agent_api.php
// Semua resource memanggil fungsi DSM yang sama dengan aplikasi web,
// atas nama user sambungan (agentCall_), jadi hak akses role ikut otomatis.
// Jawaban dipangkas (daftar maks per_halaman) dan nomor telepon disamarkan.
// =====================================================================

const AGENT_MAX_LIST = 100;
const AGENT_DEF_LIST = 50;

/** Param helper: periode YYYY-MM (default bulan berjalan), teks bersih, angka. */
function agentP_(array $q, string $k, string $def = ''): string { $v = trim((string) ($q[$k] ?? '')); return $v === '' ? $def : mb_substr($v, 0, 100); }
function agentPeriod_(array $q): string {
    $p = agentP_($q, 'periode', date('Y-m'));
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p)) agentFail_(400, 'BAD_PARAM', 'periode harus YYYY-MM, contoh 2026-11.');
    return $p;
}
function agentDate_(array $q, string $k): ?string {
    $v = agentP_($q, $k); if ($v === '') return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) agentFail_(400, 'BAD_PARAM', $k . ' harus YYYY-MM-DD.');
    return $v;
}
/** Cakupan: kosong = seluruh cakupan user; isi NIK bawahan untuk mempersempit (dicek DSM: di luar cakupan ditolak). */
function agentSel_(array $q): ?string { $v = agentP_($q, 'nik'); return $v === '' ? null : $v; }
function agentBrand_(array $q): ?string { $v = strtoupper(agentP_($q, 'brand')); return $v === '' || $v === 'ALL' ? null : $v; }

/** Samarkan nomor telepon di seluruh jawaban (kunci bernama telepon + pola nomor HP Indonesia di teks apa pun). */
function agentMaskDeep_($v, string $key = '') {
    if (is_array($v)) { $o = []; foreach ($v as $k => $x) $o[$k] = agentMaskDeep_($x, is_string($k) ? $k : $key); return $o; }
    if (!is_string($v) && !is_int($v)) return $v;
    $s = (string) $v;
    if ($key !== '' && preg_match('/(^|_)(hp|no_?hp|nohp|telp|tlp|telepon|phone|wa|whatsapp|kontak|contact|mobile)($|_)/i', $key) && preg_match('/\d{7,}/', preg_replace('/\D/', '', $s))) return agentMaskPhone_($s);
    if (!is_string($v)) return $v;
    return preg_replace_callback('/(?<!\d)(?:\+?62|0)\s?8[\d\s\-]{7,14}\d(?!\d)/', fn($m) => agentMaskPhone_($m[0]), $s);
}

/** Pangkas daftar panjang di semua kedalaman. Daftar terpanjang di level atas dipaging. */
function agentTrim_($v, int $limit, int $offset, array &$meta, int $depth = 0) {
    if (!is_array($v)) return $v;
    $isList = array_is_list($v);
    if ($isList && count($v) > 0) {
        $n = count($v);
        if ($depth <= 1 && !isset($meta['_paged']) && $n > $limit) {
            $meta['_paged'] = true; $meta['total_baris'] = $n; $meta['halaman'] = intdiv($offset, $limit) + 1; $meta['total_halaman'] = (int) ceil($n / $limit);
            $v = array_slice($v, $offset, $limit);
        } elseif ($n > AGENT_MAX_LIST) { $v = array_slice($v, 0, AGENT_MAX_LIST); $meta['dipangkas'] = true; }
    }
    foreach ($v as $k => $x) if (is_array($x)) $v[$k] = agentTrim_($x, $limit, $offset, $meta, $depth + 1);
    return $v;
}

/** Daftar orang dalam cakupan user (untuk mencari NIK dari nama). */
function agentPeople_(array $link, array $q): array {
    $db = snapshot_(); $people = $db['meta']['people'] ?? [];
    $u = PortalCore::person($people, (string) $link['nik']); if (!$u) agentFail_(403, 'FORBIDDEN', 'Akun tidak ditemukan di data DSM.');
    $ids = js_set(PortalCore::scope($people, (string) $link['nik']));
    $cari = mb_strtolower(agentP_($q, 'cari')); $role = strtoupper(agentP_($q, 'role'));
    $out = [];
    foreach ($people as $p) {
        if (!isset($ids[(string) $p['nik']])) continue;
        if ($role !== '' && strtoupper((string) ($p['role'] ?? '')) !== $role) continue;
        if ($cari !== '' && !str_contains(mb_strtolower(($p['nama'] ?? '') . ' ' . $p['nik']), $cari)) continue;
        $out[] = ['nik' => (string) $p['nik'], 'nama' => (string) ($p['nama'] ?? ''), 'role' => (string) ($p['role'] ?? ''), 'wilayah' => (string) ($p['wilayah'] ?? ''), 'region' => (string) js_or($p['portfolio'] ?? null, js_or($p['region'] ?? null, ''))];
    }
    return $out;
}

/** Peta resource -> pemanggilan fungsi DSM. Kunci = nama resource di dokumen IT. */
function agentResource_(string $res, array $link, array $q) {
    $per = fn() => agentPeriod_($q); $sel = fn() => agentSel_($q); $br = fn() => agentBrand_($q);
    switch ($res) {
        case 'orang':          return agentPeople_($link, $q);
        case 'ringkasan':      return agentCall_($link, 'apiDashboard', [$per(), $sel()]);
        case 'eksekutif':      return agentCall_($link, 'apiExec', [$per(), $sel()]);
        case 'kpi':            return agentCall_($link, 'apiKpiTree', [$per(), $sel()]);
        case 'kpi_tim':        return agentCall_($link, 'apiTeamKpi', [$per(), $sel()]);
        case 'kpi_orang':      $n = agentP_($q, 'nik'); if ($n === '') agentFail_(400, 'BAD_PARAM', 'nik wajib diisi (cari dulu lewat r=orang).'); return agentCall_($link, 'apiPersonKpi', [$per(), $n]);
        case 'peringkat':      return agentCall_($link, 'apiRank', [$per(), agentP_($q, 'nasional') === '1', agentP_($q, 'level') ?: null]);
        case 'peringkat_saya': return agentCall_($link, 'apiMyRank', [$per(), $sel()]);
        case 'tim':            return agentCall_($link, 'apiTeam', [$per(), agentP_($q, 'level') ?: null, $br(), $sel(), null]);
        case 'early_warning':  return agentCall_($link, 'apiEarlyWarning', [$per(), agentP_($q, 'level') ?: null, $br(), agentP_($q, 'metrik') ?: null, $sel(), null]);
        case 'potensi':        return agentCall_($link, 'apiPotensi', [$per(), $sel()]);
        case 'mingguan':       return agentCall_($link, 'apiWeeklyAnalysis', [$per(), $sel(), null]);
        case 'perbandingan':   return agentCall_($link, 'apiCompare', [$per(), $sel(), agentP_($q, 'mode') !== '' ? ['mode' => agentP_($q, 'mode')] : null]);
        case 'growth_type':    return agentCall_($link, 'apiGrowthType', [$per(), $sel(), $br(), null]);
        case 'dos':            return agentCall_($link, 'apiDOS', [$per(), $sel(), null, null]);
        case 'transaksi':      return agentCall_($link, 'apiTransactionGroups', [$per(), $sel(), 1, agentDate_($q, 'dari'), agentDate_($q, 'sampai'), null, AGENT_MAX_LIST]);
        case 'dealer_cari': {
            $cari = mb_strtolower(agentP_($q, 'cari')); if (mb_strlen($cari) < 2) agentFail_(400, 'BAD_PARAM', 'cari minimal 2 huruf (nama atau kode dealer).');
            $r = agentCall_($link, 'apiDashboard', [$per(), $sel()]);
            $out = [];
            foreach (($r['customers'] ?? []) as $c) {
                $hay = mb_strtolower(implode(' ', array_filter(array_map(fn($x) => is_scalar($x) ? (string) $x : '', is_array($c) ? $c : []))));
                if (str_contains($hay, $cari)) $out[] = $c;
                if (count($out) >= 30) break;
            }
            return $out;
        }
        case 'dealer': {
            $d = agentP_($q, 'dealer'); if ($d === '') agentFail_(400, 'BAD_PARAM', 'dealer wajib diisi (kode dealer, cari dulu lewat r=dealer_cari).');
            return ['detail' => agentCall_($link, 'apiDealerDetail', [$per(), $sel(), $d]), 'stok' => agentCall_($link, 'apiDealerStock', [$per(), $sel(), $d])];
        }
        case 'dealer_riwayat': return agentCall_($link, 'apiDealerHistorySummary', [$sel(), $br(), agentP_($q, 'dari') ?: null, agentP_($q, 'sampai') ?: null, agentP_($q, 'cari') ?: null, null, agentP_($q, 'level') ?: null]);
        case 'dealer_tutup':   return agentCall_($link, 'apiDealerTutupList', []);
        case 'telemarketing':  return agentCall_($link, 'apiTMTeam', [$per()]);
        case 'tm_kpi':         return agentCall_($link, 'apiTMKpi', [$per(), agentP_($q, 'nik') ?: null, $br()]);
        case 'tm_peringkat':   return agentCall_($link, 'apiTMRank', [$per(), $br()]);
        case 'order':          return agentCall_($link, 'apiOrderList', [$per()]);
        case 'komitmen':       return agentCall_($link, 'apiCommitBoot', []);
        case 'hari_libur':     return agentCall_($link, 'apiHariLibur', []);
    }
    agentFail_(400, 'BAD_PARAM', 'Resource "' . $res . '" tidak dikenal. Lihat daftar di dokumen API.');
}
const AGENT_RESOURCES = ['orang','ringkasan','eksekutif','kpi','kpi_tim','kpi_orang','peringkat','peringkat_saya','tim','early_warning','potensi','mingguan','perbandingan','growth_type','dos','transaksi','dealer_cari','dealer','dealer_riwayat','dealer_tutup','telemarketing','tm_kpi','tm_peringkat','order','komitmen','hari_libur'];
