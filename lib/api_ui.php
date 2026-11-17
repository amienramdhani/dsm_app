<?php
/**
 * Preferensi tampilan per akun (Paket 3): latar belakang, panel Admin yang disematkan, panel Admin terakhir dibuka.
 * Disimpan di Properties dengan kunci UI_PREF_<NIK>. Tidak menyentuh data penjualan / KPI.
 */
require_once __DIR__ . '/common.php';

const UI_BG_OPTIONS = ['abu', 'langit', 'sky', 'tosca', 'biru', 'petrol', 'navy', 'royal'];

function uiPrefDefault_(): array { return ['bg' => 'langit', 'pins' => [], 'recent' => []]; }

function uiPrefsGet_(string $nik): array {
    $raw = json_decode(prop_get_('UI_PREF_' . $nik) ?? '', true);
    return uiPrefClean_(is_array($raw) ? $raw : [], uiPrefDefault_());
}

function uiPrefClean_(array $in, array $base): array {
    $o = $base;
    if (array_key_exists('bg', $in) && in_array((string) $in['bg'], UI_BG_OPTIONS, true)) $o['bg'] = (string) $in['bg'];
    if (array_key_exists('pins', $in) && is_array($in['pins'])) {
        $p = [];
        foreach ($in['pins'] as $x) { $x = trim(mb_substr((string) $x, 0, 80)); if ($x !== '' && !in_array($x, $p, true)) $p[] = $x; if (count($p) >= 12) break; }
        $o['pins'] = $p;
    }
    if (array_key_exists('recent', $in) && is_array($in['recent'])) {
        $r = []; $seen = [];
        foreach ($in['recent'] as $x) {
            if (!is_array($x)) continue; $k = trim(mb_substr((string) ($x['k'] ?? ''), 0, 80)); $t = (int) ($x['t'] ?? 0);
            if ($k === '' || isset($seen[$k]) || $t <= 0) continue; $seen[$k] = 1; $r[] = ['k' => $k, 't' => $t]; if (count($r) >= 6) break;
        }
        $o['recent'] = $r;
    }
    return $o;
}

function apiUiPrefs($token) {
    $u = guard_($token);
    return uiPrefsGet_((string) $u['nik']);
}

/** $patch hanya berisi kunci yang berubah (bg / pins / recent). */
function apiUiPrefSave($token, $patch = null) {
    $u = guard_($token);
    $cur = uiPrefsGet_((string) $u['nik']);
    $next = uiPrefClean_(is_array($patch) ? $patch : [], $cur);
    prop_set_('UI_PREF_' . $u['nik'], json_encode($next, JSON_UNESCAPED_UNICODE));
    return $next;
}

/** Ringkasan kecil untuk halaman depan Admin (Bento): status backup. Approval & kelengkapan region sudah ada di browser. */
function apiAdminHome($token) {
    $u = guard_($token);
    if (!in_array($u['role'], ['ADMIN', 'SUPER ADMIN'], true)) fail_('Khusus Admin.');
    $b = function_exists('backupStatus_') ? backupStatus_() : null;
    return ['backup' => $b ? ['ok' => !$b['overdue'], 'time' => $b['last']['WAKTU'] ?? null] : null];
}
