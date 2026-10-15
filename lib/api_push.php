<?php
/** Push notification — saklar default MATI. Aktifkan dengan define('DSM_PUSH_ON', true) di config.php. */
require_once __DIR__ . '/common.php';

function pushOn_(): bool { return defined('DSM_PUSH_ON') && DSM_PUSH_ON === true; }
function pushB64_(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function pushKeys_(): array {
    $k = prop_get_('PUSH_VAPID');
    if ($k) { $a = json_decode($k, true); if (is_array($a) && !empty($a['pem'])) return $a; }
    $res = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$res) fail_('OpenSSL di server tidak bisa membuat kunci push.');
    openssl_pkey_export($res, $pem);
    $d = openssl_pkey_get_details($res)['ec'];
    $pub = pushB64_("\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT));
    $a = ['pem' => $pem, 'pub' => $pub];
    prop_set_('PUSH_VAPID', json_encode($a));
    return $a;
}
function pushDefaults_(): array { return ['on' => false, 'data' => true, 'insight' => true, 'sistem' => true, 'quiet' => true, 'qFrom' => '21:00', 'qTo' => '06:00']; }

function apiPushState($token) {
    $u = guard_($token);
    $p = json_decode(prop_get_('PUSH_PREF_' . $u['nik']) ?? '', true);
    $isAdmin = in_array($u['role'], ['ADMIN', 'SUPER ADMIN'], true);
    $sub = json_decode(prop_get_('PUSH_SUB_' . $u['nik']) ?? '[]', true);
    return ['available' => pushOn_(), 'publicKey' => pushOn_() ? pushKeys_()['pub'] : '', 'prefs' => array_merge(pushDefaults_(), is_array($p) ? $p : []), 'admin' => $isAdmin, 'devices' => is_array($sub) ? count($sub) : 0];
}
function apiPushSave($token, $prefs = null, $sub = null) {
    $u = guard_($token); if (!pushOn_()) fail_('Notifikasi belum diaktifkan oleh admin server.');
    $d = pushDefaults_(); $in = is_array($prefs) ? $prefs : []; $o = [];
    foreach ($d as $k => $v) { $x = $in[$k] ?? $v; $o[$k] = is_bool($v) ? (bool) $x : (preg_match('/^\d{2}:\d{2}$/D', (string) $x) ? (string) $x : $v); }
    prop_set_('PUSH_PREF_' . $u['nik'], json_encode($o));
    if (is_array($sub) && !empty($sub['endpoint']) && !empty($sub['keys']['p256dh']) && !empty($sub['keys']['auth'])) {
        $list = json_decode(prop_get_('PUSH_SUB_' . $u['nik']) ?? '[]', true); $list = is_array($list) ? $list : [];
        $list = array_values(array_filter($list, fn($s) => ($s['endpoint'] ?? '') !== $sub['endpoint']));
        $list[] = ['endpoint' => (string) $sub['endpoint'], 'keys' => ['p256dh' => (string) $sub['keys']['p256dh'], 'auth' => (string) $sub['keys']['auth']]];
        prop_set_('PUSH_SUB_' . $u['nik'], json_encode(array_slice($list, -5)));
    }
    return apiPushState($token);
}
/** Kirim ke satu NIK. Butuh library minishlink/web-push (composer require minishlink/web-push). */
function pushSendTo_(string $nik, string $title, string $body, string $type = 'sistem'): string {
    if (!pushOn_()) return 'Saklar push masih mati.';
    $auto = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($auto)) return 'Library web-push belum terpasang (jalankan: composer require minishlink/web-push).';
    require_once $auto;
    $p = array_merge(pushDefaults_(), json_decode(prop_get_('PUSH_PREF_' . $nik) ?? '', true) ?: []);
    if (!$p['on'] || empty($p[$type])) return 'Pengguna mematikan jenis notifikasi ini.';
    if ($p['quiet']) { $h = date('H:i'); $f = $p['qFrom']; $t = $p['qTo']; if ($f > $t ? ($h >= $f || $h < $t) : ($h >= $f && $h < $t)) return 'Jam tenang.'; }
    $subs = json_decode(prop_get_('PUSH_SUB_' . $nik) ?? '[]', true) ?: [];
    if (!$subs) return 'Belum ada perangkat terdaftar.';
    $k = pushKeys_(); $ws = new \Minishlink\WebPush\WebPush(['VAPID' => ['subject' => 'mailto:admin@localhost', 'publicKey' => $k['pub'], 'privateKey' => $k['pem']]]);
    foreach ($subs as $s) $ws->queueNotification(\Minishlink\WebPush\Subscription::create($s), json_encode(['title' => $title, 'body' => $body]));
    $ok = 0; foreach ($ws->flush() as $r) if ($r->isSuccess()) $ok++;
    return "Terkirim $ok dari " . count($subs) . ' perangkat.';
}
function apiPushTest($token) {
    $u = guard_($token); if (!pushOn_()) fail_('Notifikasi belum diaktifkan oleh admin server.');
    return ['hasil' => pushSendTo_($u['nik'], 'DSM', 'Tes notifikasi berhasil.', 'sistem')];
}
