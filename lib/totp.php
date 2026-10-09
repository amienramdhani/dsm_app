<?php
/**
 * Verifikasi 2 langkah (TOTP / Google Authenticator), kode cadangan, kunci akun bertingkat,
 * dan panel keamanan Admin. Penyimpangan SENGAJA dari Apps Script (Paket 9).
 * Tabel _authx dibuat otomatis saat pertama dipakai (tidak perlu migrasi).
 * Kunci rahasia TOTP disimpan terenkripsi (AES-256-GCM, kunci turunan PEPPER).
 */

const TFA_LOCK_STEPS = [900000, 3600000, 86400000]; // 15 menit, 1 jam, 24 jam
const TFA_TICKET_MS = 300000;   // 5 menit setelah PIN benar
const TFA_SETUP_MS = 900000;    // 15 menit untuk aktivasi (pasang aplikasi dulu)
const TFA_MAX_TRIES = 5;

// ------------------------------------------------------------------
// Penyimpanan
// ------------------------------------------------------------------

function authx_table_(): void {
    static $done = false; if ($done) return; $done = true;
    db_()->exec("CREATE TABLE IF NOT EXISTS _authx (nik VARCHAR(100) NOT NULL PRIMARY KEY, secret TEXT NULL, tfa_on TINYINT NOT NULL DEFAULT 0, backups TEXT NULL, last_step BIGINT NOT NULL DEFAULT 0, lock_level INT NOT NULL DEFAULT 0, last_lock_ms BIGINT NOT NULL DEFAULT 0, fail_log TEXT NULL, updated_ms BIGINT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function authx_blank_(string $nik): array {
    return ['nik' => $nik, 'secret' => null, 'tfa_on' => 0, 'backups' => null, 'last_step' => 0, 'lock_level' => 0, 'last_lock_ms' => 0, 'fail_log' => null, 'updated_ms' => 0];
}
function authx_get_($nik): array {
    authx_table_(); $nik = text_($nik);
    $st = db_()->prepare('SELECT * FROM _authx WHERE nik = ?'); $st->execute([$nik]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: authx_blank_($nik);
}
function authx_put_(array $x): void {
    authx_table_(); $x['updated_ms'] = now_ms();
    db_()->prepare('REPLACE INTO _authx (nik, secret, tfa_on, backups, last_step, lock_level, last_lock_ms, fail_log, updated_ms) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$x['nik'], $x['secret'], (int) $x['tfa_on'], $x['backups'], (int) $x['last_step'], (int) $x['lock_level'], (int) $x['last_lock_ms'], $x['fail_log'], (int) $x['updated_ms']]);
}
function authx_all_(): array {
    authx_table_(); $out = [];
    foreach (db_()->query('SELECT * FROM _authx')->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['nik']] = $r;
    return $out;
}
function authx_fail_times_(array $x): array {
    $l = json_decode((string) ($x['fail_log'] ?? ''), true);
    return is_array($l) ? array_values(array_map('intval', $l)) : [];
}
function authx_reset_lock_($nik): void {
    $x = authx_get_($nik);
    if ($x['lock_level'] || $x['last_lock_ms']) { $x['lock_level'] = 0; $x['last_lock_ms'] = 0; authx_put_($x); }
}

// ------------------------------------------------------------------
// TOTP (RFC 6238, SHA-1, 6 digit, 30 detik)
// ------------------------------------------------------------------

const B32_ALPHA = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
function b32_encode_(string $bin): string {
    $bits = ''; foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = ''; foreach (str_split($bits, 5) as $chunk) $out .= B32_ALPHA[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}
function b32_decode_(string $s): string {
    $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s)); $bits = '';
    foreach (str_split($s) as $c) $bits .= str_pad(decbin(strpos(B32_ALPHA, $c)), 5, '0', STR_PAD_LEFT);
    $out = ''; foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}
function totp_code_(string $secretBin, int $step): string {
    $h = hash_hmac('sha1', pack('N2', 0, $step) , $secretBin, true);
    $o = ord($h[19]) & 15;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
}
/** Kembalikan nomor step yang cocok (toleransi ±1 step), atau null. Step harus lebih baru dari $lastStep (anti pakai ulang). */
function totp_verify_(string $secretBin, string $code, int $lastStep = 0, ?int $nowSec = null): ?int {
    if (!preg_match('/^\d{6}$/', $code)) return null;
    $cur = intdiv($nowSec ?? time(), 30); $hit = null;
    foreach ([0, -1, 1] as $d) {
        $s = $cur + $d;
        if (hash_equals(totp_code_($secretBin, $s), $code) && $s > $lastStep && ($hit === null)) $hit = $s;
    }
    return $hit;
}
function tfa_key_(): string {
    if (!defined('DSM_PEPPER') || DSM_PEPPER === '') fail_('Setup portal belum selesai.');
    return hash('sha256', 'tfa|' . DSM_PEPPER, true);
}
function tfa_encrypt_(string $plain): string {
    $iv = random_bytes(12); $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', tfa_key_(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ct === false) fail_('Gagal mengamankan kunci 2FA (ekstensi openssl).');
    return base64_encode($iv . $tag . $ct);
}
function tfa_decrypt_(?string $blob): ?string {
    if (!$blob) return null; $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) < 29) return null;
    $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', tfa_key_(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $p === false ? null : $p;
}

// ------------------------------------------------------------------
// Kode cadangan
// ------------------------------------------------------------------

function backup_norm_(string $c): string { return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $c)); }
function backup_hash_(string $nik, string $normCode): string { return hash_('bk|' . $nik . '|' . $normCode); }
function backup_generate_(string $nik): array {
    $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $codes = []; $hashes = [];
    for ($i = 0; $i < 8; $i++) {
        $raw = ''; for ($j = 0; $j < 8; $j++) $raw .= $alpha[random_int(0, 31)];
        $codes[] = substr($raw, 0, 4) . '-' . substr($raw, 4); $hashes[] = backup_hash_($nik, $raw);
    }
    return ['codes' => $codes, 'hashes' => $hashes];
}

// ------------------------------------------------------------------
// Kunci akun bertingkat + log gagal
// ------------------------------------------------------------------

function lockText_(int $ms): string {
    $m = (int) ceil($ms / 60000);
    if ($m < 60) return max(1, $m) . ' menit';
    $h = (int) ceil($m / 60);
    return $h . ' jam';
}
/** Catat satu kegagalan (PIN atau kode 2FA) pada akun. Mengembalikan sisa ms kunci bila akun baru saja terkunci, selain itu 0. */
function loginFailed_(array $a, string $action): int {
    $now = now_ms(); $nik = text_($a['NIK']); $x = authx_get_($nik);
    $a['GAGAL'] = (int) num_($a['GAGAL']) + 1; $lockMs = 0;
    if ($a['GAGAL'] >= 5) {
        $lvl = (int) $x['lock_level'];
        if ((int) $x['last_lock_ms'] > 0 && $now - (int) $x['last_lock_ms'] > 172800000) $lvl = 0; // 48 jam bersih: mulai dari awal
        $lockMs = TFA_LOCK_STEPS[min($lvl, 2)];
        $a['LOCK_UNTIL'] = $now + $lockMs; $a['GAGAL'] = 0;
        $x['lock_level'] = min($lvl + 1, 2); $x['last_lock_ms'] = $now;
    }
    writeAccount_($a);
    $log = array_values(array_filter(authx_fail_times_($x), fn($t) => $now - $t < 7 * 86400000)); $log[] = $now;
    $x['fail_log'] = json_encode(array_slice($log, -100)); authx_put_($x);
    audit_($nik, $action, $lockMs ? 'kunci ' . lockText_($lockMs) : '');
    return $lockMs;
}
function loginSuccess_(array $a, array &$gate, string $deviceId = '', ?string $deviceName = null): array {
    if (num_($a['GAGAL']) || num_($a['LOCK_UNTIL'])) { $a['GAGAL'] = 0; $a['LOCK_UNTIL'] = 0; writeAccount_($a); }
    authx_reset_lock_($a['NIK']);
    loginIpClear_($gate);
    $token = issue_($a, $deviceId, $deviceName); audit_(text_($a['NIK']), 'LOGIN', '');
    return ['token' => $token, 'mustChange' => PortalCore::active($a['WAJIB_GANTI'])];
}
function loginGate_(): array {
    $now = now_ms(); $t = json_decode(prop_get_('LOGIN_GLOBAL') ?? '', true) ?: ['start' => 0, 'count' => 0];
    if ($now - $t['start'] > 60000) { $t['start'] = $now; $t['count'] = 0; }
    if ($t['count'] > 150) fail_('Terlalu banyak percobaan masuk. Coba beberapa saat lagi.'); // DSM v5: yang dihitung hanya percobaan GAGAL
    $key = 'LOGIN_IP_' . substr(sha1(loginIp_()), 0, 20); $ip = json_decode(prop_get_($key) ?? '', true) ?: ['start' => 0, 'count' => 0];
    if ($now - $ip['start'] > 600000) $ip = ['start' => $now, 'count' => 0];
    if ($ip['count'] >= 15) fail_('Terlalu banyak percobaan gagal dari perangkat/jaringan ini. Coba lagi dalam 10 menit.');
    return ['key' => $key, 't' => $ip];
}
function loginIpFail_(array &$g): void {
    $g['t']['count']++; prop_set_($g['key'], json_encode($g['t']));
    $now = now_ms(); $t = json_decode(prop_get_('LOGIN_GLOBAL') ?? '', true) ?: ['start' => 0, 'count' => 0];
    if ($now - $t['start'] > 60000) { $t['start'] = $now; $t['count'] = 0; }
    $t['count']++; prop_set_('LOGIN_GLOBAL', json_encode($t));
}
function loginIpClear_(array &$g): void { if ($g['t']['count'] > 0) { $g['t']['count'] = 0; prop_set_($g['key'], json_encode($g['t'])); } }

// ------------------------------------------------------------------
// Tiket verifikasi (setelah PIN benar, sebelum sesi diterbitkan)
// ------------------------------------------------------------------

/** Apakah akun ini wajib 2FA: jabatan di atas SALES, atau 2FA sudah aktif. */
/** Saklar sementara (mis. untuk demo): define('DSM_TFA_OFF', true); di config.php mematikan kewajiban Authenticator. */
function tfa_off_(): bool { return defined('DSM_TFA_OFF') && DSM_TFA_OFF; }
function tfa_needed_($nik): bool {
    if (tfa_off_()) return false;
    $x = authx_get_($nik); if ((int) $x['tfa_on']) return true;
    try {
        $p = PortalCore::person(snapshot_()['meta']['people'], $nik);
        return $p && PortalCore::lv($p['role'] ?? '') >= 2;
    } catch (AppError $e) { return false; } // belum ada data dipublikasikan (setup awal)
}
function tfa_ticket_issue_(string $nik, string $mode, string $deviceId = '', ?string $deviceName = null): string {
    cleanupAuth_();
    db_()->prepare("DELETE FROM _sessions WHERE nik = ? AND k LIKE 'TICKET\\_%'")->execute([$nik]);
    $ticket = hash_(uuid_() . uuid_() . now_ms());
    session_put_('TICKET_' . hash_($ticket), ['nik' => $nik, 'mode' => $mode, 'tries' => 0, 'until' => now_ms() + ($mode === 'setup' ? TFA_SETUP_MS : TFA_TICKET_MS), 'deviceId' => $deviceId, 'deviceName' => $deviceName]);
    return $ticket;
}
function tfa_ticket_get_($ticket): array {
    $msg = 'Sesi verifikasi habis. Masuk ulang dengan NIK dan PIN.';
    if (!is_string($ticket) || strlen($ticket) < 30 || strlen($ticket) > 200) fail_($msg);
    $key = 'TICKET_' . hash_($ticket); $s = session_get_($key);
    if (!$s || (int) $s['until'] < now_ms()) fail_($msg);
    return ['key' => $key, 's' => $s];
}
function tfa_ticket_drop_(array $t): void { db_()->prepare('DELETE FROM _sessions WHERE k = ?')->execute([$t['key']]); }
/** Hitung satu salah kode pada tiket; setelah TFA_MAX_TRIES tiket hangus. */
function tfa_ticket_miss_(array $t): void {
    $t['s']['tries'] = (int) $t['s']['tries'] + 1;
    if ($t['s']['tries'] >= TFA_MAX_TRIES) tfa_ticket_drop_($t); else session_put_($t['key'], $t['s']);
}
function tfa_account_(array $t): array {
    $a = account_($t['s']['nik']);
    if (!$a || !PortalCore::active($a['AKTIF'])) fail_('NIK atau PIN tidak sesuai.');
    if (num_($a['LOCK_UNTIL']) > now_ms()) fail_('Akun dikunci sementara. Coba lagi setelah ' . lockText_((int) (num_($a['LOCK_UNTIL']) - now_ms())) . '.');
    return $a;
}
function tfa_bad_(array $a, array $t, array &$gate): void {
    $lock = loginFailed_($a, 'LOGIN_2FA_GAGAL'); loginIpFail_($gate); tfa_ticket_miss_($t);
    if ($lock) fail_('Terlalu banyak salah. Akun dikunci ' . lockText_($lock) . '.');
    fail_('Kode tidak sesuai. Periksa jam HP (harus otomatis) lalu coba kode terbaru.');
}

// ------------------------------------------------------------------
// Endpoint login 2 langkah
// ------------------------------------------------------------------

function apiLogin2fa($ticket, $code) {
    $gate = loginGate_(); $t = tfa_ticket_get_($ticket); $a = tfa_account_($t); $nik = text_($a['NIK']);
    $x = authx_get_($nik); if (!(int) $x['tfa_on']) fail_('Authenticator belum diaktifkan. Masuk ulang.');
    $code = trim(js_str($code ?? '')); $used = false;
    if (preg_match('/^\d{6}$/', $code)) {
        $secret = tfa_decrypt_($x['secret']); $step = $secret === null ? null : totp_verify_($secret, $code, (int) $x['last_step']);
        if ($step === null) tfa_bad_($a, $t, $gate);
        $x['last_step'] = $step;
    } else {
        $norm = backup_norm_($code); $hashes = json_decode((string) $x['backups'], true) ?: [];
        $h = strlen($norm) === 8 ? backup_hash_($nik, $norm) : ''; $idx = $h === '' ? false : array_search($h, $hashes, true);
        if ($idx === false) tfa_bad_($a, $t, $gate);
        array_splice($hashes, $idx, 1); $x['backups'] = json_encode($hashes); $used = true;
        audit_($nik, 'LOGIN_KODE_CADANGAN', 'sisa ' . count($hashes));
    }
    authx_put_($x); tfa_ticket_drop_($t);
    $out = loginSuccess_($a, $gate, (string) ($t['s']['deviceId'] ?? ''), $t['s']['deviceName'] ?? null);
    $out['usedBackup'] = $used; $out['backupLeft'] = count(json_decode((string) $x['backups'], true) ?: []);
    return $out;
}
function apiTotpSetup($ticket) {
    $t = tfa_ticket_get_($ticket); $a = tfa_account_($t); $nik = text_($a['NIK']); $x = authx_get_($nik);
    if ((int) $x['tfa_on']) fail_('Authenticator sudah aktif untuk akun ini.');
    $secret = tfa_decrypt_($x['secret']);
    if ($secret === null) { $secret = random_bytes(20); $x['secret'] = tfa_encrypt_($secret); authx_put_($x); }
    $t['s']['until'] = now_ms() + TFA_SETUP_MS; session_put_($t['key'], $t['s']);
    $b32 = b32_encode_($secret);
    return ['secret' => $b32, 'nik' => $nik, 'uri' => 'otpauth://totp/' . rawurlencode('DSM:' . $nik) . '?secret=' . $b32 . '&issuer=DSM&algorithm=SHA1&digits=6&period=30'];
}
function apiTotpEnable($ticket, $code) {
    $gate = loginGate_(); $t = tfa_ticket_get_($ticket); $a = tfa_account_($t); $nik = text_($a['NIK']); $x = authx_get_($nik);
    if ((int) $x['tfa_on']) fail_('Authenticator sudah aktif untuk akun ini.');
    $secret = tfa_decrypt_($x['secret']); if ($secret === null) fail_('Mulai aktivasi dulu (kunci belum dibuat).');
    $step = totp_verify_($secret, trim(js_str($code ?? '')), 0);
    if ($step === null) tfa_bad_($a, $t, $gate);
    $bk = backup_generate_($nik);
    $x['tfa_on'] = 1; $x['last_step'] = $step; $x['backups'] = json_encode($bk['hashes']); authx_put_($x);
    audit_($nik, 'TFA_AKTIF', ''); tfa_ticket_drop_($t);
    $out = loginSuccess_($a, $gate, (string) ($t['s']['deviceId'] ?? ''), $t['s']['deviceName'] ?? null); $out['backupCodes'] = $bk['codes'];
    return $out;
}

// ------------------------------------------------------------------
// Admin: panel keamanan, reset 2FA, buka kunci
// ------------------------------------------------------------------

function securityRows_(array $u, array $db): array {
    $now = now_ms();
    $accts = []; foreach (sheet_rows_with_id_('_Auth') as $r) $accts[text_($r['values'][0] ?? '')] = $r['values'];
    $xs = authx_all_(); $rows = []; $seen = [];
    foreach ($db['meta']['people'] as $p) {
        $nik = text_($p['nik']); if ($nik === '-' || isset($seen[$nik]) || !isset($accts[$nik]) || !canManageUser_($u, $p)) continue; $seen[$nik] = 1;
        $v = $accts[$nik]; if (!PortalCore::active($v[3] ?? null) || !truthy($v[2] ?? '')) continue;
        $x = $xs[$nik] ?? authx_blank_($nik); $times = authx_fail_times_($x);
        $need = PortalCore::lv($p['role'] ?? '') >= 2;
        $lockUntil = (int) num_($v[7] ?? 0);
        $rows[] = ['nik' => $nik, 'nama' => $p['nama'], 'role' => $p['role'], 'wilayah' => $p['wilayah'] ?? '',
            'tfa' => (int) $x['tfa_on'] ? 'on' : ($need ? 'off' : 'na'),
            'fails24' => count(array_filter($times, fn($t) => $now - $t < 86400000)), 'fails7' => count(array_filter($times, fn($t) => $now - $t < 7 * 86400000)),
            'lockLeftMs' => $lockUntil > $now ? $lockUntil - $now : 0, 'lastFail' => $times ? max($times) : 0];
    }
    usort($rows, fn($a, $b) => ($b['lockLeftMs'] > 0) <=> ($a['lockLeftMs'] > 0) ?: $b['fails24'] <=> $a['fails24'] ?: strcmp($a['nama'], $b['nama']));
    return $rows;
}
function apiSecurityPanel($token) {
    $db = snapshot_(); $u = adminGuard_($token, $db); $now = now_ms(); $rows = securityRows_($u, $db);
    $need = array_filter($rows, fn($r) => $r['tfa'] !== 'na');
    return ['rows' => $rows, 'now' => $now, 'summary' => [
        'need' => count($need), 'on' => count(array_filter($need, fn($r) => $r['tfa'] === 'on')),
        'locked' => count(array_filter($rows, fn($r) => $r['lockLeftMs'] > 0)), 'fails24' => array_sum(array_column($rows, 'fails24'))]];
}
function apiResetTfa($token, $nik) {
    demoAssertWritable_($token); $x = enforceUser_($token, js_str($nik)); $n = text_($x['target']['nik']);
    $r = authx_get_($n); $r['secret'] = null; $r['tfa_on'] = 0; $r['backups'] = null; $r['last_step'] = 0; authx_put_($r);
    revokeAllDevices_($n);
    audit_($x['u']['nik'], 'TFA_RESET', $n . ' | ' . $x['target']['nama']);
    return true;
}
function apiUnlockAccount($token, $nik) {
    demoAssertWritable_($token); $x = enforceUser_($token, js_str($nik)); $n = text_($x['target']['nik']);
    $a = account_($n); if (!$a) fail_('Akun belum terdaftar.');
    $a['GAGAL'] = 0; $a['LOCK_UNTIL'] = 0; writeAccount_($a); authx_reset_lock_($n);
    audit_($x['u']['nik'], 'AKUN_BUKA_KUNCI', $n . ' | ' . $x['target']['nama']);
    return true;
}
/** Untuk banner Admin: jumlah akun terkunci dan akun wajib-2FA yang belum aktif (dalam kewenangan admin). */
function securityBanner_(array $u, array $db): array {
    $rows = securityRows_($u, $db);
    return ['locked' => count(array_filter($rows, fn($r) => $r['lockLeftMs'] > 0)), 'off' => tfa_off_() ? 0 : count(array_filter($rows, fn($r) => $r['tfa'] === 'off'))];
}
