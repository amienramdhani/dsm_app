<?php
/**
 * Helper supaya perilaku PHP sama dengan JavaScript (Apps Script V8).
 * Semua logika bisnis DSM adalah terjemahan 1:1 dari kode Apps Script,
 * jadi detail kecil seperti pembulatan, urutan key, dan perbandingan teks
 * harus identik — kalau tidak, angka di layar bisa beda tipis.
 */

/** Error yang pesannya ditampilkan ke pengguna (setara `throw Error('...')` di Apps Script). */
class AppError extends Exception {}

function fail_(string $msg): never { throw new AppError($msg); }

// ------------------------------------------------------------------
// JSON / key
// ------------------------------------------------------------------

/** Normalisasi nilai sebelum di-encode: float bulat -> int (JS menulis 3, bukan 3.0). */
function js_norm($v) {
    if (is_float($v)) {
        if (is_finite($v) && floor($v) == $v && abs($v) < 9007199254740992) return (int) $v;
        if (!is_finite($v)) return null; // JSON.stringify(NaN/Infinity) => null
        return $v;
    }
    if (is_array($v)) { foreach ($v as $k => $x) $v[$k] = js_norm($x); return $v; }
    if ($v instanceof stdClass) { $o = new stdClass(); foreach ($v as $k => $x) $o->$k = js_norm($x); return $o; }
    return $v;
}

const JS_JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRESERVE_ZERO_FRACTION;

/** Setara JSON.stringify(v). */
function js_stringify($v): string {
    $s = json_encode(js_norm($v), (JS_JSON_FLAGS & ~JSON_PRESERVE_ZERO_FRACTION) | JSON_PARTIAL_OUTPUT_ON_ERROR);
    return $s === false ? 'null' : $s;
}

/** PortalCore.key(parts) = JSON.stringify(parts). */
function jkey(array $parts): string { return js_stringify(array_values($parts)); }

function js_parse(string $s) { return json_decode($s, true); }

// ------------------------------------------------------------------
// Teks & angka
// ------------------------------------------------------------------

/** String(x) versi JS untuk angka/boolean/null. */
function js_str($v): string {
    if ($v === null) return 'null';
    if ($v === true) return 'true';
    if ($v === false) return 'false';
    if (is_int($v)) return (string) $v;
    if (is_float($v)) {
        if (is_nan($v)) return 'NaN';
        if (is_infinite($v)) return $v > 0 ? 'Infinity' : '-Infinity';
        if (floor($v) == $v && abs($v) < 1e21) return number_format($v, 0, '.', '');
        $s = json_encode($v); // shortest round-trip, sama dengan JS untuk rentang normal
        return $s;
    }
    if (is_array($v)) return implode(',', array_map(fn($x) => $x === null ? '' : js_str($x), $v));
    return (string) $v;
}

const JS_WS = "\x{0009}\x{000A}\x{000B}\x{000C}\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}";

function js_trim(string $s): string {
    $r = preg_replace('/^[' . JS_WS . ']+|[' . JS_WS . ']+$/u', '', $s);
    return $r === null ? trim($s) : $r;
}

/** PortalCore.text */
function text_($v): string { return js_trim($v === null ? '' : js_str($v)); }

function js_upper(string $s): string { return mb_strtoupper($s, 'UTF-8'); }
function js_lower(string $s): string { return mb_strtolower($s, 'UTF-8'); }
/** String.prototype.slice untuk teks (indeks karakter). */
function js_slice(string $s, int $a, ?int $b = null): string {
    // Semua teks bisnis (tanggal, kode) ASCII; pakai byte untuk kecepatan.
    $len = strlen($s);
    if ($a < 0) $a = max(0, $len + $a);
    if ($b === null) $b = $len; elseif ($b < 0) $b = max(0, $len + $b);
    if ($b <= $a) return '';
    return substr($s, $a, $b - $a);
}
function js_pad2($n): string { return str_pad((string) $n, 2, '0', STR_PAD_LEFT); }

/** Math.round versi JS (setengah dibulatkan ke atas, termasuk untuk negatif). */
function js_mround(float|int $x): float|int { return floor($x + 0.5); }

/** Bandingkan dua angka/teks dengan aturan JS `<` (teks vs teks = urutan kode, bukan numerik). */
function js_lt($a, $b): bool {
    if (is_string($a) && is_string($b)) return strcmp($a, $b) < 0;
    return $a < $b;
}
function js_cmp_str(string $a, string $b): int { return $a === $b ? 0 : (strcmp($a, $b) < 0 ? -1 : 1); }

// ------------------------------------------------------------------
// localeCompare
// ------------------------------------------------------------------

function js_collator(string $locale, bool $numeric = false, bool $base = false) {
    static $cache = [];
    $k = $locale . '|' . ($numeric ? 1 : 0) . '|' . ($base ? 1 : 0);
    if (!array_key_exists($k, $cache)) {
        if (class_exists('Collator')) {
            $c = new Collator($locale);
            if ($numeric) $c->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
            if ($base) $c->setStrength(Collator::PRIMARY);
            $cache[$k] = $c;
        } else {
            $cache[$k] = null;
        }
    }
    return $cache[$k];
}

/** a.localeCompare(b[, locale, {numeric, sensitivity:'base'}]) */
function js_locale_compare($a, $b, string $locale = 'en_US', bool $numeric = false, bool $base = false): int {
    $a = js_str($a); $b = js_str($b);
    $c = js_collator($locale === 'id' ? 'id_ID' : $locale, $numeric, $base);
    if ($c) { $r = $c->compare($a, $b); return $r < 0 ? -1 : ($r > 0 ? 1 : 0); }
    // Cadangan kalau ekstensi intl belum aktif: tidak peka huruf besar/kecil.
    $r = $numeric ? strnatcasecmp($a, $b) : strcasecmp($a, $b);
    if ($r === 0 && !$base) $r = -strcmp($a, $b); // huruf kecil lebih dulu, seperti ICU
    return $r < 0 ? -1 : ($r > 0 ? 1 : 0);
}
function lc($a, $b): int { return js_locale_compare($a, $b); }
function lc_id($a, $b): int { return js_locale_compare($a, $b, 'id'); }

// ------------------------------------------------------------------
// Array / object
// ------------------------------------------------------------------

/** Object.keys versi JS: key bilangan bulat (indeks) dulu urut naik, sisanya sesuai urutan masuk. Selalu string. */
function js_keys($obj): array {
    if (!is_array($obj)) return [];
    $idx = []; $rest = [];
    foreach ($obj as $k => $_) {
        $s = (string) $k;
        if (is_int($k) && $k >= 0 && $k < 4294967295) $idx[] = $k;
        elseif (preg_match('/^(0|[1-9][0-9]{0,9})$/', $s) && (int) $s < 4294967295) $idx[] = (int) $s;
        else $rest[] = $s;
    }
    sort($idx, SORT_NUMERIC);
    return array_merge(array_map('strval', $idx), $rest);
}
function js_values($obj): array { $out = []; foreach (js_keys($obj) as $k) $out[] = $obj[$k]; return $out; }
function js_entries($obj): array { $out = []; foreach (js_keys($obj) as $k) $out[] = [$k, $obj[$k]]; return $out; }

/** new Set(arr) -> array unik, urutan kemunculan pertama, kesetaraan ketat. */
function uniq(array $a): array {
    $seen = []; $out = [];
    foreach ($a as $v) {
        $t = is_string($v) ? 's:' . $v : (is_int($v) || is_float($v) ? 'n:' . js_str($v) : (is_bool($v) ? 'b:' . ($v ? 1 : 0) : ($v === null ? 'z' : 'j:' . js_stringify($v))));
        if (!isset($seen[$t])) { $seen[$t] = true; $out[] = $v; }
    }
    return $out;
}

function js_filter(array $a, callable $f): array { $o = []; foreach ($a as $v) if ($f($v)) $o[] = $v; return $o; }
function js_map(array $a, callable $f): array { $o = []; foreach (array_values($a) as $i => $v) $o[] = $f($v, $i); return $o; }
function js_flat_map(array $a, callable $f): array { $o = []; foreach ($a as $v) { $r = $f($v); if (is_array($r) && array_is_list($r)) { foreach ($r as $x) $o[] = $x; } else $o[] = $r; } return $o; }
function js_find(array $a, callable $f) { foreach ($a as $v) if ($f($v)) return $v; return null; }
function js_find_index(array $a, callable $f): int { foreach (array_values($a) as $i => $v) if ($f($v)) return $i; return -1; }
function js_some(array $a, callable $f): bool { foreach ($a as $v) if ($f($v)) return true; return false; }
function js_every(array $a, callable $f): bool { foreach ($a as $v) if (!$f($v)) return false; return true; }
function js_includes(array $a, $x): bool { return in_array($x, $a, true); }
function js_sum(array $a, callable $f) { $s = 0; foreach ($a as $v) $s += $f($v); return $s; }
function js_last(array $a) { return $a ? $a[array_key_last($a)] : null; }
/** arr.sort() tanpa comparator: bandingkan sebagai teks. */
function js_sort_default(array $a): array { usort($a, fn($x, $y) => strcmp(js_str($x), js_str($y))); return $a; }
function js_sort(array $a, callable $cmp): array { usort($a, function ($x, $y) use ($cmp) { $r = $cmp($x, $y); return $r < 0 ? -1 : ($r > 0 ? 1 : 0); }); return $a; }

/** Himpunan string untuk pencarian cepat (pengganti `ids.includes(x)` di loop besar). */
function js_set(array $a): array { $s = []; foreach ($a as $v) $s[(string) $v] = true; return $s; }
function in_set(array $set, $v): bool { return $v !== null && isset($set[(string) $v]); }

/** Object kosong saat di-encode ke JSON harus `{}` bukan `[]`. */
function js_obj(array $a) { return $a ? $a : new stdClass(); }

/** Ambil nilai bertingkat aman: dig($a, 'x', 'y') setara a?.x?.y */
function dig($a, ...$path) {
    foreach ($path as $p) {
        if (!is_array($a) || $p === null || !array_key_exists($p, $a)) return null;
        $a = $a[$p];
    }
    return $a;
}

/** Truthiness ala JS (string kosong, 0, null, false -> false; array/obyek selalu true). */
function truthy($v): bool {
    if (is_array($v) || is_object($v)) return true;
    if (is_string($v)) return $v !== '';
    if (is_float($v)) return $v != 0 && !is_nan($v);
    return (bool) $v;
}
/** a || b ala JS */
function js_or($a, $b) { return truthy($a) ? $a : $b; }

// ------------------------------------------------------------------
// Tanggal
// ------------------------------------------------------------------

function iso_days(string $d): int { return intdiv((int) strtotime($d . 'T00:00:00Z'), 86400); }
function days_iso(int $n): string { return gmdate('Y-m-d', $n * 86400); }
function now_ms(): int { return (int) floor(microtime(true) * 1000); }
function iso_now(): string { $t = microtime(true); return gmdate('Y-m-d\TH:i:s', (int) $t) . '.' . str_pad((string) floor(($t - floor($t)) * 1000), 3, '0', STR_PAD_LEFT) . 'Z'; }
/** Utilities.formatDate(new Date(), 'Asia/Jakarta', fmt) untuk pola umum. */
function jkt_date(string $phpFormat, ?int $ts = null): string { return gmdate($phpFormat, ($ts ?? time()) + 7 * 3600); }
