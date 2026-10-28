<?php
/**
 * Login, PIN, sesi, mode Demo, dan guard_ — terjemahan 1:1 dari Auth.gs.
 * Sesi disimpan di tabel _sessions (pengganti Script Properties SESSION_*).
 */
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/region.php';

/** hash_() — HMAC-SHA256(value, PEPPER), base64 URL-safe tanpa '='. Harus identik dengan Apps Script. */
function hash_(string $value): string {
    if (!defined('DSM_PEPPER') || DSM_PEPPER === '' || DSM_PEPPER === 'GANTI_DENGAN_PEPPER_ASLI_DARI_APPS_SCRIPT') fail_('Setup portal belum selesai.');
    return rtrim(strtr(base64_encode(hash_hmac('sha256', $value, DSM_PEPPER, true)), '+/', '-_'), '=');
}
function randomPin_(): string {
    $b = hash_hmac('sha256', bin2hex(random_bytes(16)) . bin2hex(random_bytes(16)), DSM_PEPPER, true);
    return str_pad((string) (((ord($b[0]) * 65536) + (ord($b[1]) * 256) + ord($b[2])) % 10000), 4, '0', STR_PAD_LEFT);
}
function validatePin_($pin): void { if (!preg_match('/^\d{4}$/', js_str($pin))) fail_('PIN harus tepat 4 digit.'); }
function safeEqual_($a, $b): bool { return hash_equals(js_str($a), js_str($b)); }

// ------------------------------------------------------------------
// _Auth
// ------------------------------------------------------------------

const AUTH_HEADS = ['NIK', 'SALT', 'PIN_HASH', 'AKTIF', 'VERSI', 'WAJIB_GANTI', 'GAGAL', 'LOCK_UNTIL'];

function account_($nik): ?array {
    $nik = text_($nik);
    foreach (sheet_rows_with_id_('_Auth') as $i => $r) {
        if ($i === 0 && false) continue;
        if (text_($r['values'][0] ?? '') === $nik) {
            $o = ['_row' => $r['_row']];
            foreach (AUTH_HEADS as $j => $h) $o[$h] = $r['values'][$j] ?? '';
            return $o;
        }
    }
    return null;
}
function writeAccount_(array $a): void {
    $nik = text_($a['NIK']);
    $row = null;
    foreach (sheet_rows_with_id_('_Auth') as $r) if (text_($r['values'][0] ?? '') === $nik) { $row = $r['_row']; break; }
    if ($row === null) fail_('Akun belum terdaftar. Publikasikan master terlebih dahulu.');
    sheet_update_row_('_Auth', $row, [$a['NIK'], $a['SALT'], $a['PIN_HASH'], $a['AKTIF'], $a['VERSI'], $a['WAJIB_GANTI'], $a['GAGAL'], $a['LOCK_UNTIL']]);
}

// PIN sementara yang masih boleh dilihat Admin/Super Admin. Disimpan terenkripsi terpisah dari hash login
// dan otomatis dihapus begitu user mengganti PIN pada login pertama.
function tempPinSchema_(): void {
    db_()->exec("CREATE TABLE IF NOT EXISTS _temp_pins (nik VARCHAR(100) NOT NULL PRIMARY KEY, pin_enc TEXT NOT NULL, created_ms BIGINT NOT NULL, created_by VARCHAR(100) NOT NULL DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function tempPinKey_(): string {
    if (!defined('DSM_PEPPER') || DSM_PEPPER === '') fail_('Setup portal belum selesai.');
    return hash('sha256', 'temp-pin|' . DSM_PEPPER, true);
}
function tempPinEncrypt_(string $plain): string {
    $iv = random_bytes(12); $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', tempPinKey_(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ct === false) fail_('Gagal mengamankan PIN sementara.');
    return base64_encode($iv . $tag . $ct);
}
function tempPinDecrypt_(?string $blob): string {
    if (!$blob) return ''; $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) < 29) return '';
    $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', tempPinKey_(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $p === false ? '' : $p;
}
function tempPinPut_(string $nik, string $pin, string $by = ''): void {
    tempPinSchema_(); db_()->prepare('REPLACE INTO _temp_pins (nik,pin_enc,created_ms,created_by) VALUES (?,?,?,?)')->execute([$nik, tempPinEncrypt_($pin), now_ms(), $by]);
}
function tempPinGet_(string $nik): string {
    tempPinSchema_(); $st=db_()->prepare('SELECT pin_enc FROM _temp_pins WHERE nik=?'); $st->execute([$nik]); return tempPinDecrypt_($st->fetchColumn() ?: '');
}
function tempPinDrop_(string $nik): void { tempPinSchema_(); db_()->prepare('DELETE FROM _temp_pins WHERE nik=?')->execute([$nik]); }

function setPin_($nik, $pin, $force): array {
    validatePin_($pin);
    $a = account_($nik);
    if (!$a) { appendRow_('_Auth', [$nik, '', '', false, 0, true, 0, 0]); $a = account_($nik); }
    $a['SALT'] = uuid_();
    $a['PIN_HASH'] = hash_($nik . '|' . $a['SALT'] . '|' . $pin);
    $a['AKTIF'] = true; $a['VERSI'] = (int) PortalCore::number(js_or($a['VERSI'], 0)) + 1; $a['WAJIB_GANTI'] = (bool) $force; $a['GAGAL'] = 0; $a['LOCK_UNTIL'] = 0;
    writeAccount_($a); authx_reset_lock_($nik);
    if ($force) tempPinPut_(text_($nik), (string) $pin); else tempPinDrop_(text_($nik));
    revokeAllDevices_(text_($nik)); // PIN berubah = semua perangkat lama wajib masuk ulang
    return $a;
}
function uuid_(): string {
    $d = random_bytes(16); $d[6] = chr((ord($d[6]) & 0x0f) | 0x40); $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}
function num_($v): float { $s = text_($v); return $s === '' ? 0 : (is_numeric($s) ? (float) $s : 0); }

// ------------------------------------------------------------------
// Sesi
// ------------------------------------------------------------------

function session_get_(string $key): ?array {
    $st = db_()->prepare('SELECT data FROM _sessions WHERE k = ?'); $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? null : (json_decode($v, true) ?: null);
}
function session_put_(string $key, array $s): void {
    devices_schema_();
    db_()->prepare('REPLACE INTO _sessions (k, nik, data, until_ms, device_id) VALUES (?,?,?,?,?)')->execute([$key, (string) $s['nik'], json_encode($s, JSON_UNESCAPED_UNICODE), (int) $s['until'], (string) ($s['deviceId'] ?? '')]);
}
function cleanupAuth_(): void { db_()->prepare('DELETE FROM _sessions WHERE until_ms < ?')->execute([now_ms()]); }

// ------------------------------------------------------------------
// Perangkat (Paket 11 / #37) — sliding session + manajemen perangkat
// ------------------------------------------------------------------

const DEVICE_MAX = 5;
const DEVICE_IDLE_MS_NORMAL = 30 * 86400000; // 30 hari tanpa aktivitas
const DEVICE_IDLE_MS_ADMIN = 7 * 86400000;   // 7 hari untuk ADMIN / SUPER ADMIN
const DEVICE_TOUCH_MS = 600000;              // perpanjang sesi & "terakhir aktif" paling sering tiap 10 menit

function devices_schema_(): void {
    static $done = false; if ($done) return; $done = true;
    $pdo = db_();
    $pdo->exec("CREATE TABLE IF NOT EXISTS _devices (device_id VARCHAR(64) NOT NULL, nik VARCHAR(100) NOT NULL, nama VARCHAR(150) NOT NULL DEFAULT '', ua VARCHAR(255) NOT NULL DEFAULT '', created_ms BIGINT NOT NULL, last_seen_ms BIGINT NOT NULL, PRIMARY KEY (nik, device_id), KEY idx_nik (nik)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    sessions_ensure_device_col_();
}
/** "ADD COLUMN IF NOT EXISTS" tidak didukung semua versi MySQL/MariaDB — cek dulu via information_schema supaya aman di semua versi. */
function sessions_ensure_device_col_(): void {
    $pdo = db_();
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '_sessions' AND COLUMN_NAME = 'device_id'");
        $st->execute();
        if ((int) $st->fetchColumn() === 0) $pdo->exec("ALTER TABLE _sessions ADD COLUMN device_id VARCHAR(64) NOT NULL DEFAULT ''");
    } catch (Throwable $e) {}
}
function deviceIdleMs_(string $role): int { return in_array($role, ['ADMIN', 'SUPER ADMIN'], true) ? DEVICE_IDLE_MS_ADMIN : DEVICE_IDLE_MS_NORMAL; }
function deviceUa_(): string { return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255); }
function deviceAutoName_(string $ua): string {
    $os = 'Perangkat';
    if (stripos($ua, 'iphone') !== false) $os = 'iPhone';
    elseif (stripos($ua, 'ipad') !== false) $os = 'iPad';
    elseif (stripos($ua, 'android') !== false) $os = 'Android';
    elseif (stripos($ua, 'windows') !== false) $os = 'Windows';
    elseif (stripos($ua, 'mac os') !== false) $os = 'Mac';
    elseif (stripos($ua, 'linux') !== false) $os = 'Linux';
    $br = 'Browser';
    if (stripos($ua, 'edg/') !== false) $br = 'Edge';
    elseif (stripos($ua, 'firefox/') !== false) $br = 'Firefox';
    elseif (stripos($ua, 'chrome/') !== false) $br = 'Chrome';
    elseif (stripos($ua, 'safari/') !== false) $br = 'Safari';
    return $br . ' di ' . $os;
}
function deviceList_(string $nik): array {
    devices_schema_();
    $st = db_()->prepare('SELECT device_id,nama,ua,created_ms,last_seen_ms FROM _devices WHERE nik=? ORDER BY last_seen_ms DESC'); $st->execute([$nik]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
/** Daftarkan perangkat baru (menegakkan batas 4/NIK) atau perbarui yang sudah ada. */
function deviceRegisterOrTouch_(string $nik, string $deviceId, ?string $name, string $role = ''): void {
    devices_schema_();
    // DSM v5: perangkat yang tidak aktif melewati batas idle (30 hari / 7 hari admin) otomatis gugur, jadi tidak memenuhi kuota 5 perangkat.
    db_()->prepare('DELETE FROM _devices WHERE nik=? AND last_seen_ms < ?')->execute([$nik, now_ms() - deviceIdleMs_($role)]);
    $deviceId = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $deviceId), 0, 64);
    if ($deviceId === '') fail_('Perangkat tidak dikenali. Muat ulang halaman lalu coba lagi.');
    $pdo = db_(); $now = now_ms(); $ua = deviceUa_();
    $st = $pdo->prepare('SELECT nama FROM _devices WHERE nik=? AND device_id=?'); $st->execute([$nik, $deviceId]);
    $existingName = $st->fetchColumn();
    $nm = js_trim(js_str($name ?? ''));
    if ($existingName === false) {
        $cst = $pdo->prepare('SELECT COUNT(*) FROM _devices WHERE nik=?'); $cst->execute([$nik]);
        if ((int) $cst->fetchColumn() >= DEVICE_MAX) fail_('Sudah ada ' . DEVICE_MAX . ' perangkat terdaftar untuk akun ini. Buka DSM di salah satu perangkat lama lalu hapus satu perangkat di menu "Perangkat Saya" sebelum masuk dari perangkat baru, atau minta Admin menghapusnya. Perangkat yang tidak dipakai lebih dari 30 hari terhapus otomatis.');
        if ($nm === '') $nm = deviceAutoName_($ua);
        $pdo->prepare('INSERT INTO _devices (device_id,nik,nama,ua,created_ms,last_seen_ms) VALUES (?,?,?,?,?,?)')->execute([$deviceId, $nik, substr($nm, 0, 150), $ua, $now, $now]);
    } else {
        $nm = $nm !== '' ? $nm : (string) $existingName;
        $pdo->prepare('UPDATE _devices SET nama=?, ua=?, last_seen_ms=? WHERE nik=? AND device_id=?')->execute([substr($nm, 0, 150), $ua, $now, $nik, $deviceId]);
    }
}
function deviceTouch_(string $nik, string $deviceId): void {
    if ($deviceId === '') return;
    devices_schema_();
    db_()->prepare('UPDATE _devices SET last_seen_ms=? WHERE nik=? AND device_id=?')->execute([now_ms(), $nik, $deviceId]);
}
function deviceRemove_(string $nik, string $deviceId): void {
    devices_schema_(); $pdo = db_();
    $pdo->prepare('DELETE FROM _devices WHERE nik=? AND device_id=?')->execute([$nik, $deviceId]);
    $pdo->prepare('DELETE FROM _sessions WHERE nik=? AND device_id=?')->execute([$nik, $deviceId]);
}
/** Cabut semua perangkat + sesi milik satu NIK. Dipanggil saat ganti PIN, reset 2FA, dsb. */
function revokeAllDevices_(string $nik): void {
    devices_schema_(); $pdo = db_();
    $pdo->prepare('DELETE FROM _devices WHERE nik=?')->execute([$nik]);
    $pdo->prepare('DELETE FROM _sessions WHERE nik=?')->execute([$nik]);
}

function issue_(array $a, string $deviceId = '', ?string $deviceName = null): string {
    cleanupAuth_();
    $nik = text_($a['NIK']);
    $role = '';
    try { $p = PortalCore::person(snapshot_()['meta']['people'], $nik); $role = (string) ($p['role'] ?? ''); } catch (Throwable $e) {}
    if ($deviceId !== '') deviceRegisterOrTouch_($nik, $deviceId, $deviceName, $role);
    db_()->prepare('DELETE FROM _sessions WHERE nik = ? AND device_id = ?')->execute([$nik, $deviceId]);
    $token = hash_(uuid_() . uuid_() . now_ms());
    $until = now_ms() + deviceIdleMs_($role);
    session_put_('SESSION_' . hash_($token), ['nik' => $nik, 'version' => (int) num_($a['VERSI']), 'until' => $until, 'role' => $role, 'deviceId' => $deviceId, 'touched' => now_ms()]);
    return $token;
}
function authSession_($token): array {
    if (!is_string($token) || strlen($token) < 30 || strlen($token) > 200) fail_('SESI: Silakan masuk kembali.');
    $key = 'SESSION_' . hash_($token); $s = session_get_($key);
    if (!$s) fail_('SESI: Silakan masuk kembali.');
    $a = account_((string) $s['nik']);
    if (!$a || (int) $s['until'] < now_ms() || !PortalCore::active($a['AKTIF']) || (int) num_($a['VERSI']) !== (int) $s['version']) fail_('SESI: Silakan masuk kembali.');
    // Sesi berjalan (sliding): selama masih aktif, batas waktu digeser maju. Ditahan maks tiap 10 menit biar hemat query.
    $now = now_ms();
    if (!isset($s['touched']) || $now - (int) $s['touched'] > DEVICE_TOUCH_MS) {
        $s['until'] = $now + deviceIdleMs_((string) ($s['role'] ?? '')); $s['touched'] = $now;
        session_put_($key, $s);
        if (!empty($s['deviceId'])) deviceTouch_((string) $s['nik'], (string) $s['deviceId']);
    }
    return ['key' => $key, 's' => $s, 'a' => $a];
}

// ------------------------------------------------------------------
// Endpoint "Perangkat Saya" (semua role) & manajemen oleh Admin/Super Admin
// ------------------------------------------------------------------

function apiMyDevices($token) {
    $auth = authSession_($token);
    $myId = (string) ($auth['s']['deviceId'] ?? '');
    return array_map(fn($r) => ['deviceId' => $r['device_id'], 'nama' => $r['nama'], 'ua' => $r['ua'], 'created' => (int) $r['created_ms'], 'lastSeen' => (int) $r['last_seen_ms'], 'current' => $r['device_id'] === $myId && $myId !== ''], deviceList_((string) $auth['s']['nik']));
}
function apiRenameDevice($token, $deviceId, $nama) {
    $auth = authSession_($token); $nik = (string) $auth['s']['nik']; devices_schema_();
    $nama = js_trim(js_str($nama ?? '')); if ($nama === '') fail_('Nama perangkat tidak boleh kosong.');
    $deviceId = js_trim(js_str($deviceId ?? ''));
    $st = db_()->prepare('UPDATE _devices SET nama=? WHERE nik=? AND device_id=?'); $st->execute([substr($nama, 0, 150), $nik, $deviceId]);
    if ($st->rowCount() === 0) fail_('Perangkat tidak ditemukan.');
    audit_($nik, 'DEVICE_RENAME', $deviceId . ' -> ' . $nama);
    return apiMyDevices($token);
}
function apiRemoveDevice($token, $deviceId) {
    $auth = authSession_($token); $nik = (string) $auth['s']['nik'];
    $deviceId = js_trim(js_str($deviceId ?? ''));
    deviceRemove_($nik, $deviceId);
    audit_($nik, 'DEVICE_REMOVE', $deviceId);
    return apiMyDevices($token);
}
function apiAdminListDevices($token, $nik) {
    $x = enforceUser_($token, js_str($nik));
    return array_map(fn($r) => ['deviceId' => $r['device_id'], 'nama' => $r['nama'], 'ua' => $r['ua'], 'created' => (int) $r['created_ms'], 'lastSeen' => (int) $r['last_seen_ms']], deviceList_(text_($x['target']['nik'])));
}
function apiAdminRemoveDevice($token, $nik, $deviceId) {
    $x = enforceUser_($token, js_str($nik)); $n = text_($x['target']['nik']);
    $deviceId = js_trim(js_str($deviceId ?? ''));
    deviceRemove_($n, $deviceId);
    audit_($x['u']['nik'], 'DEVICE_REMOVE_ADMIN', $n . ' | ' . $deviceId);
    return apiAdminListDevices($token, $nik);
}

// ------------------------------------------------------------------
// Mode Demo
// ------------------------------------------------------------------

function demoState_($token, ?array $db = null): array {
    $auth = authSession_($token); $db = $db ?? snapshot_();
    $actor = PortalCore::person($db['meta']['people'], $auth['s']['nik']);
    $target = !empty($auth['s']['demoNik']) ? PortalCore::person($db['meta']['people'], $auth['s']['demoNik']) : null;
    if (!$actor) fail_('Akun tidak ditemukan.');
    return ['active' => (bool) $target, 'canStart' => $actor['role'] === 'SUPER ADMIN', 'actor' => ['nik' => $actor['nik'], 'nama' => $actor['nama'], 'role' => $actor['role']],
        'target' => $target ? ['nik' => $target['nik'], 'nama' => $target['nama'], 'role' => $target['role'], 'wilayah' => js_or($target['wilayah'] ?? '', ''), 'region' => js_or($target['portfolio'] ?? '', js_or($target['region'] ?? '', ''))] : null, 'readOnly' => (bool) $target];
}
function demoAssertWritable_($token): void { if (!empty(authSession_($token)['s']['demoNik'])) fail_('Mode Demo hanya-baca. Kembali ke akun Super Admin untuk melakukan perubahan.'); }
function demoTargetAllowed_(?array $p): bool { return $p && $p['nik'] !== '-' && !in_array($p['role'], ['ADMIN', 'SUPER ADMIN'], true); }

function apiDemoOptions($token) {
    $db = snapshot_(); $state = demoState_($token, $db);
    if (!$state['canStart']) fail_('Hanya Super Admin yang dapat menggunakan Mode Demo.');
    $list = [];
    foreach ($db['meta']['people'] as $p) {
        if (!demoTargetAllowed_($p)) continue;
        $a = account_($p['nik']);
        if (!$a || !PortalCore::active($a['AKTIF']) || !truthy($a['PIN_HASH'])) continue;
        $list[] = ['nik' => $p['nik'], 'nama' => $p['nama'], 'role' => $p['role'], 'bigRegion' => js_or($p['wilayah'] ?? '', 'Nasional'), 'region' => js_or($p['portfolio'] ?? '', js_or($p['region'] ?? '', '—'))];
    }
    return js_sort($list, fn($a, $b) => lc($a['bigRegion'], $b['bigRegion']) ?: lc($a['region'], $b['region']) ?: lc($a['role'], $b['role']) ?: lc($a['nama'], $b['nama']));
}
function apiStartDemo($token, $targetNik) {
    $db = snapshot_(); $auth = authSession_($token);
    $actor = PortalCore::person($db['meta']['people'], $auth['s']['nik']); $target = PortalCore::person($db['meta']['people'], js_str($targetNik));
    if (($actor['role'] ?? null) !== 'SUPER ADMIN') fail_('Hanya Super Admin yang dapat menggunakan Mode Demo.');
    if (!demoTargetAllowed_($target)) fail_('Pilih akun operasional selain Admin/Super Admin.');
    $a = account_($target['nik']);
    if (!$a || !PortalCore::active($a['AKTIF']) || !truthy($a['PIN_HASH'])) fail_('Akun tujuan tidak aktif atau belum memiliki PIN.');
    $auth['s']['demoNik'] = $target['nik']; session_put_($auth['key'], $auth['s']);
    audit_($actor['nik'], 'DEMO_START', $target['nik'] . ' | ' . $target['nama'] . ' | ' . $target['role']);
    return demoState_($token, $db);
}
function apiStopDemo($token) {
    $db = snapshot_(); $auth = authSession_($token);
    $actor = PortalCore::person($db['meta']['people'], $auth['s']['nik']);
    $target = !empty($auth['s']['demoNik']) ? PortalCore::person($db['meta']['people'], $auth['s']['demoNik']) : null;
    if (($actor['role'] ?? null) !== 'SUPER ADMIN') fail_('Hanya Super Admin yang dapat mengakhiri Mode Demo.');
    unset($auth['s']['demoNik']); session_put_($auth['key'], $auth['s']);
    audit_($actor['nik'], 'DEMO_STOP', $target ? $target['nik'] . ' | ' . $target['nama'] : '');
    return demoState_($token, $db);
}

// ------------------------------------------------------------------
// Login / guard
// ------------------------------------------------------------------

/** IP klien untuk throttle login. X-Forwarded-For hanya dipercaya bila DSM_TRUST_PROXY = true di config. */
function loginIp_(): string {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    if (defined('DSM_TRUST_PROXY') && DSM_TRUST_PROXY && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $x = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]); if (filter_var($x, FILTER_VALIDATE_IP)) $ip = $x; }
    return $ip;
}
function apiLogin($nik, $pin, $deviceId = null, $deviceName = null) {
    $nik = js_trim(js_str($nik ?? '')); $pin = js_str($pin ?? '');
    $deviceId = js_trim(js_str($deviceId ?? '')); $deviceName = $deviceName === null ? null : js_trim(js_str($deviceName));
    if (strlen($nik) > 60 || !preg_match('/^\d{4}$/', $pin)) fail_('NIK atau PIN tidak sesuai.');
    $now = now_ms(); $gate = loginGate_();
    $a = account_($nik);
    if (!$a || !PortalCore::active($a['AKTIF']) || $nik === '-') { loginIpFail_($gate); fail_('NIK atau PIN tidak sesuai.'); }
    if (num_($a['LOCK_UNTIL']) > $now) fail_('Akun dikunci sementara. Coba lagi setelah ' . lockText_((int) (num_($a['LOCK_UNTIL']) - $now)) . '.');
    if (!safeEqual_(hash_($nik . '|' . $a['SALT'] . '|' . $pin), $a['PIN_HASH'])) {
        loginFailed_($a, 'LOGIN_GAGAL'); loginIpFail_($gate);
        fail_('NIK atau PIN tidak sesuai.');
    }
    // Paket 9: ASM ke atas wajib verifikasi 2 langkah (Authenticator) sebelum sesi diterbitkan.
    if (tfa_needed_($nik)) {
        $on = (int) authx_get_($nik)['tfa_on'] === 1;
        return ['need2fa' => true, 'mode' => $on ? 'verify' : 'setup', 'ticket' => tfa_ticket_issue_($nik, $on ? 'verify' : 'setup', $deviceId, $deviceName)];
    }
    return loginSuccess_($a, $gate, $deviceId, $deviceName);
}

function guard_($token, bool $admin = false, bool $allowChange = false, ?array $db = null): array {
    $auth = authSession_($token); $s = $auth['s']; $a = $auth['a'];
    if (!$allowChange && PortalCore::active($a['WAJIB_GANTI'])) fail_('PIN: Ganti PIN sementara terlebih dahulu.');
    $db = $db ?? snapshot_();
    $actor = PortalCore::person($db['meta']['people'], $s['nik']);
    if (!$actor || $actor['nik'] === '-') fail_('Akun tidak ditemukan.');
    if (!empty($s['demoNik'])) {
        if ($admin) fail_('Mode Demo hanya-baca. Kembali ke akun Super Admin untuk membuka administrasi.');
        $target = PortalCore::person($db['meta']['people'], $s['demoNik']); $ta = $target ? account_($target['nik']) : null;
        if (!demoTargetAllowed_($target) || !$ta || !PortalCore::active($ta['AKTIF'])) fail_('Akun Demo tidak lagi tersedia. Kembali ke Super Admin.');
        return array_merge($target, ['demo' => true, 'demoActor' => ['nik' => $actor['nik'], 'nama' => $actor['nama'], 'role' => $actor['role']]]);
    }
    if ($admin && $actor['role'] !== 'SUPER ADMIN') fail_('Akses admin diperlukan.');
    return $actor;
}

function apiLogout($token) {
    if (is_string($token) && strlen($token) < 200) db_()->prepare('DELETE FROM _sessions WHERE k = ?')->execute(['SESSION_' . hash_($token)]);
    return true;
}
function apiChangePin($token, $pin) {
    demoAssertWritable_($token);
    $user = guard_($token, false, true); validatePin_($pin);
    $a = account_($user['nik']);
    if (safeEqual_(hash_($user['nik'] . '|' . $a['SALT'] . '|' . $pin), $a['PIN_HASH'])) fail_('Pilih PIN yang berbeda.');
    $myDeviceId = (string) (authSession_($token)['s']['deviceId'] ?? '');
    $changed = setPin_($user['nik'], $pin, false); audit_($user['nik'], 'CHANGE_PIN', '');
    // setPin_ mencabut semua perangkat (termasuk perangkat ini sendiri) — daftarkan lagi perangkat yang sedang dipakai supaya tidak ikut ter-logout.
    return ['token' => issue_($changed, $myDeviceId)];
}

require_once __DIR__ . '/totp.php';
