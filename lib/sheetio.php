<?php
/**
 * sheetio.php — pengganti IO Google Sheets/Drive untuk file upload & ekspor XLSX.
 *
 *  1. parseCsv_()            : Utilities.parseCsv
 *  2. xlsx_grid_()           : upload XLSX → Google Sheet → getDataRange().getValues()/getDisplayValues()
 *     xlsx_sheet_names_(), date_fmt_() (Utilities.formatDate(d,'Asia/Jakarta',pola))
 *  3. Wb / WbSheet / WbRange : SpreadsheetApp tiruan (workbook sementara) + wb_xlsx_() / exportGoogleSheetXlsx_()
 *  4. Port 1:1: templateXlsx_, templateGuideWrite_, rejectXlsxBlob_, rejectBase64_, excelCodeNorm_,
 *     excelMangledMap_, strictSolusi_, strictScan_, bulkReadRows_, stReadFile_, soReadFile_, syncRead_,
 *     dealerReadFile_, userBulkRows_.
 */
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/blob.php';

const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
/** Format tampilan numFmtId 14 (tanggal pendek bawaan, tergantung locale). ECMA-376: m/d/yyyy. */
if (!defined('XLSX_DATE14')) define('XLSX_DATE14', 'm/d/yyyy');
if (!defined('DEALER_HEADERS')) define('DEALER_HEADERS', ['KODE CUSTOMER', 'NAMA INDUK CUSTOMER', 'NIK SALES', 'ID_DEALER', 'KOTA', 'ALAMAT', 'No. HP', 'SUB REGION']);
if (!defined('DEALER_TEMPLATE_HEADERS')) define('DEALER_TEMPLATE_HEADERS', ['KODE_DEALER_UNIQ', 'KODE_INDUK_DEALER', 'NAMA INDUK CUSTOMER', 'NIK SALES', 'KOTA', 'ALAMAT', 'No. HP', 'SUB REGION']);
if (!defined('USER_BULK_HEADERS')) define('USER_BULK_HEADERS', ['NIK', 'NAMA', 'POSISI', 'REGION', 'NIK_ATASAN', 'WILAYAH_ADMIN']);

function jkt_tz_(): DateTimeZone { static $z = null; return $z ??= new DateTimeZone('Asia/Jakarta'); }

// ==================================================================
// 1. CSV
// ==================================================================

/** Utilities.parseCsv(text, delim): RFC4180, kutip ganda, baris baru di dalam kutip, \r\n / \n. */
function parseCsv_(string $text, string $delim = ','): array {
    if (str_starts_with($text, "\xEF\xBB\xBF")) $text = substr($text, 3);
    $rows = []; $row = []; $f = ''; $q = false; $n = strlen($text); $i = 0; $any = false;
    while ($i < $n) {
        $ch = $text[$i];
        if ($q) {
            if ($ch === '"') {
                if ($i + 1 < $n && $text[$i + 1] === '"') { $f .= '"'; $i += 2; continue; }
                $q = false; $i++; continue;
            }
            // potong cepat sampai kutip berikutnya
            $j = strpos($text, '"', $i);
            if ($j === false) { $f .= substr($text, $i); $i = $n; continue; }
            $f .= substr($text, $i, $j - $i); $i = $j; continue;
        }
        if ($ch === '"') { $q = true; $any = true; $i++; continue; }
        if ($ch === $delim) { $row[] = $f; $f = ''; $any = true; $i++; continue; }
        if ($ch === "\r" || $ch === "\n") {
            $row[] = $f; $rows[] = $row; $row = []; $f = ''; $any = false;
            $i += ($ch === "\r" && $i + 1 < $n && $text[$i + 1] === "\n") ? 2 : 1;
            continue;
        }
        // potong cepat teks biasa
        $len = strcspn($text, "\"\r\n" . $delim, $i);
        $f .= substr($text, $i, $len); $any = true; $i += $len;
    }
    if ($any || $f !== '' || $row) { $row[] = $f; $rows[] = $row; }
    return $rows;
}

/** Pilih delimiter CSV: aturan "lebih banyak ; daripada ," (ST/SO/Sync/Bulk). */
function csv_delim_count_(string $text): string {
    $line = preg_split('/\r?\n/', $text, 2)[0] ?? '';
    return count(explode(';', $line)) > count(explode(',', $line)) ? ';' : ',';
}
/** Aturan "baris pertama mengandung ;" (Dealer/User/Kontak). */
function csv_delim_has_(string $text): string {
    $line = preg_split('/\r?\n/', $text, 2)[0] ?? '';
    return str_contains($line, ';') ? ';' : ',';
}
function strip_bom_(string $s): string { return str_starts_with($s, "\xEF\xBB\xBF") ? substr($s, 3) : $s; }

// ==================================================================
// 2. Tanggal
// ==================================================================

/** Utilities.formatDate(d, 'Asia/Jakarta', pattern) — pola SimpleDateFormat. */
function date_fmt_(DateTimeInterface $d, string $pattern = 'yyyy-MM-dd'): string {
    $d = DateTimeImmutable::createFromInterface($d)->setTimezone(jkt_tz_());
    $out = ''; $n = strlen($pattern); $i = 0;
    while ($i < $n) {
        $c = $pattern[$i];
        if ($c === "'") {
            $j = strpos($pattern, "'", $i + 1);
            if ($j === $i + 1) { $out .= "'"; $i += 2; continue; }
            if ($j === false) $j = $n;
            $out .= substr($pattern, $i + 1, $j - $i - 1); $i = $j + 1; continue;
        }
        if (!ctype_alpha($c)) { $out .= $c; $i++; continue; }
        $k = $i; while ($k < $n && $pattern[$k] === $c) $k++;
        $len = $k - $i; $i = $k;
        $num = fn(int $v) => str_pad((string) $v, $len, '0', STR_PAD_LEFT);
        switch ($c) {
            case 'y': case 'Y': $y = (int) $d->format('Y'); $out .= $len === 2 ? substr((string) $y, -2) : $num($y); break;
            case 'M': $out .= $len >= 4 ? $d->format('F') : ($len === 3 ? $d->format('M') : $num((int) $d->format('n'))); break;
            case 'd': $out .= $num((int) $d->format('j')); break;
            case 'H': $out .= $num((int) $d->format('G')); break;
            case 'h': $out .= $num((int) $d->format('g')); break;
            case 'k': $out .= $num(((int) $d->format('G')) ?: 24); break;
            case 'm': $out .= $num((int) $d->format('i')); break;
            case 's': $out .= $num((int) $d->format('s')); break;
            case 'S': $out .= $num((int) $d->format('v')); break;
            case 'a': $out .= $d->format('A'); break;
            case 'E': $out .= $len >= 4 ? $d->format('l') : $d->format('D'); break;
            case 'u': $out .= $d->format('N'); break;
            case 'Z': $out .= $d->format('O'); break;
            case 'X': $out .= $len === 1 ? substr($d->format('O'), 0, 3) : $d->format($len === 2 ? 'O' : 'P'); break;
            case 'z': $out .= $len >= 4 ? 'Western Indonesia Time' : 'WIB'; break;
            default: $out .= str_repeat($c, $len);
        }
    }
    return $out;
}

/** String(date) versi JS (untuk header bertipe tanggal). */
function js_date_string_(DateTimeInterface $d): string {
    $d = (new DateTimeImmutable('@' . $d->getTimestamp()))->setTimezone(jkt_tz_());
    return $d->format('D M d Y H:i:s') . ' GMT+0700 (Western Indonesia Time)';
}
/** PortalCore.text untuk nilai getValues (bisa Date). */
function cell_text_($v): string { return $v instanceof DateTimeInterface ? js_date_string_($v) : text_($v); }

// ==================================================================
// 2b. Format angka & tanggal (tampilan sel, meniru getDisplayValues)
// ==================================================================

/** Kode format bawaan Excel (numFmtId → formatCode). */
function xlsx_builtin_fmt_(int $id): ?string {
    static $m = null;
    $m ??= [0 => 'General', 1 => '0', 2 => '0.00', 3 => '#,##0', 4 => '#,##0.00', 9 => '0%', 10 => '0.00%', 11 => '0.00E+00',
        12 => '# ?/?', 13 => '# ??/??', 15 => 'd-mmm-yy', 16 => 'd-mmm', 17 => 'mmm-yy', 18 => 'h:mm AM/PM',
        19 => 'h:mm:ss AM/PM', 20 => 'h:mm', 21 => 'h:mm:ss', 22 => 'm/d/yyyy h:mm', 37 => '#,##0 ;(#,##0)',
        38 => '#,##0 ;[Red](#,##0)', 39 => '#,##0.00;(#,##0.00)', 40 => '#,##0.00;[Red](#,##0.00)', 45 => 'mm:ss',
        46 => '[h]:mm:ss', 47 => 'mmss.0', 48 => '##0.0E+0', 49 => '@'];
    if ($id === 14) return XLSX_DATE14;
    if (isset($m[$id])) return $m[$id];
    // 27-36, 50-58: format tanggal locale Asia Timur → tampil sebagai yyyy-mm-dd
    if (($id >= 27 && $id <= 36) || ($id >= 50 && $id <= 58)) return 'yyyy-mm-dd';
    return null;
}
function xlsx_builtin_is_date_(int $id): bool {
    return ($id >= 14 && $id <= 22) || ($id >= 27 && $id <= 36) || ($id >= 45 && $id <= 47) || ($id >= 50 && $id <= 58);
}

/** Apakah kode format custom adalah format tanggal/waktu (ada y/m/d/h/s di luar kutip & kurung). */
function xlsx_code_is_date_(string $code): bool {
    static $memo = [];
    if (isset($memo[$code])) return $memo[$code];
    $c = preg_replace('/"[^"]*"|\\\\.|_.|\*./s', '', $code);
    $elapsed = (bool) preg_match('/\[(h+|m+|s+)\]/i', $c);
    $c = preg_replace('/\[[^\]]*\]/', '', $c);
    $c = preg_replace('/General/i', '', $c);
    return $memo[$code] = $elapsed || (bool) preg_match('/[ymdhs]/i', $c);
}

/** Pisah seksi format (;) di luar kutip. */
function fmt_sections_(string $code): array {
    $out = []; $cur = ''; $q = false; $n = strlen($code);
    for ($i = 0; $i < $n; $i++) {
        $ch = $code[$i];
        if ($ch === '"') $q = !$q;
        elseif (!$q && $ch === '\\' && $i + 1 < $n) { $cur .= $ch . $code[++$i]; continue; }
        elseif (!$q && $ch === ';') { $out[] = $cur; $cur = ''; continue; }
        $cur .= $ch;
    }
    $out[] = $cur;
    return $out;
}

/** Angka format "General"/Automatic: bulat tanpa desimal, desimal terpendek (maks 15 digit signifikan). */
function num_general_($v): string {
    if (is_int($v)) return (string) $v;
    if (!is_finite($v)) return js_str($v);
    if ($v == floor($v) && abs($v) < 1e21) return number_format($v, 0, '.', '');
    $r = (float) sprintf('%.15g', $v);
    $s = js_str($r);
    if (stripos($s, 'e') !== false) { // 1e-7 → 1.00E-07 (gaya Sheets)
        return strtoupper(sprintf('%.2E', $r));
    }
    return $s;
}

/** Render angka dengan kode format numerik sederhana (0, 0.00, #,##0, 0%, 0.00E+00, prefix/suffix literal). */
function num_fmt_($x, string $code): string {
    if ($code === '' || strcasecmp($code, 'General') === 0 || $code === '@') return num_general_($x);
    $secs = fmt_sections_($code);
    $neg = $x < 0; $sec = $secs[0];
    if ($neg && count($secs) >= 2 && trim($secs[1]) !== '') { $sec = $secs[1]; $x = -$x; $neg = false; }
    elseif ($x == 0 && count($secs) >= 3) $sec = $secs[2];
    elseif ($neg) $x = -$x;
    // pisah literal & placeholder
    $pre = ''; $suf = ''; $pat = ''; $seen = false; $n = strlen($sec); $pct = 0;
    for ($i = 0; $i < $n; $i++) {
        $ch = $sec[$i]; $lit = null;
        if ($ch === '"') { $j = strpos($sec, '"', $i + 1); if ($j === false) $j = $n; $lit = substr($sec, $i + 1, $j - $i - 1); $i = $j; }
        elseif ($ch === '\\') { $lit = $sec[++$i] ?? ''; }
        elseif ($ch === '_') { $i++; $lit = ' '; }
        elseif ($ch === '*') { $i++; continue; }
        elseif ($ch === '[') { $j = strpos($sec, ']', $i); $i = $j === false ? $n : $j; continue; }
        elseif (strpos('0#?.,', $ch) !== false) { $pat .= $ch; $seen = true; continue; }
        elseif (($ch === 'E' || $ch === 'e') && $seen && isset($sec[$i + 1]) && ($sec[$i + 1] === '+' || $sec[$i + 1] === '-')) { $pat .= 'E' . $sec[$i + 1]; $i++; continue; }
        else { if ($ch === '%') $pct++; $lit = $ch; }
        if ($seen) $suf .= $lit; else $pre .= $lit;
    }
    if ($pat === '') return ($neg ? '-' : '') . $pre . $suf;
    for ($k = 0; $k < $pct; $k++) $x *= 100;
    if (($e = strpos($pat, 'E')) !== false) {
        $mant = substr($pat, 0, $e); $dp = strpos($mant, '.');
        $dec = $dp === false ? 0 : strlen(preg_replace('/[^0#?]/', '', substr($mant, $dp + 1)));
        $expDigits = max(1, strlen(preg_replace('/[^0#?]/', '', substr($pat, $e + 2))));
        $s = sprintf('%.' . $dec . 'E', $x);
        [$m, $ex] = explode('E', $s);
        $ex = (int) $ex; $sign = $ex < 0 ? '-' : ($pat[$e + 1] === '+' ? '+' : '');
        $num = $m . 'E' . $sign . str_pad((string) abs($ex), $expDigits, '0', STR_PAD_LEFT);
    } else {
        while (str_ends_with($pat, ',')) { $pat = substr($pat, 0, -1); $x /= 1000; }
        $dp = strpos($pat, '.');
        $ip = $dp === false ? $pat : substr($pat, 0, $dp);
        $fp = $dp === false ? '' : substr($pat, $dp + 1);
        $dec = strlen(preg_replace('/[^0#?]/', '', $fp));
        $minDec = substr_count($fp, '0');
        $th = str_contains($ip, ',');
        $num = number_format(round($x, $dec), $dec, '.', $th ? ',' : '');
        if ($dec > $minDec) { // buang nol opsional (#)
            $num = rtrim($num, '0'); $keep = strlen(substr($num, strpos($num, '.') + 1));
            if ($keep < $minDec) $num .= str_repeat('0', $minDec - $keep);
            $num = rtrim($num, '.');
        }
        $minInt = substr_count($ip, '0');
        if ($minInt === 0 && str_starts_with($num, '0')) $num = substr($num, 1);
        elseif ($minInt > 1) {
            $intPart = explode('.', $num)[0]; $digits = str_replace(',', '', $intPart);
            if (strlen($digits) < $minInt) $num = str_repeat('0', $minInt - strlen($digits)) . $num;
        }
        if ($num === '' || $num === '.') $num = '0';
        if ($num !== '' && str_ends_with($num, '.')) $num = substr($num, 0, -1);
        if ($neg && (float) str_replace(',', '', $num) == 0) $neg = false;
    }
    return ($neg ? '-' : '') . $pre . $num . $suf;
}

/** Serial tanggal → [Y,m,d,H,i,s,dow,ms]. */
function serial_parts_(float $serial, bool $d1904 = false): array {
    $days = (int) floor($serial);
    $ms = (int) round(($serial - $days) * 86400000);
    if ($ms >= 86400000) { $days++; $ms -= 86400000; }
    $ts = ($days + ($d1904 ? -24107 : -25569)) * 86400 + intdiv($ms, 1000);
    [$Y, $m, $d, $H, $i, $s, $w] = explode(' ', gmdate('Y n j G i s w', $ts));
    return [(int) $Y, (int) $m, (int) $d, (int) $H, (int) $i, (int) $s, (int) $w, $ms % 1000];
}
/** Serial → DateTimeImmutable di Asia/Jakarta (jam dinding sama dengan sel). */
function serial_date_(float $serial, bool $d1904 = false): DateTimeImmutable {
    [$Y, $m, $d, $H, $i, $s] = serial_parts_(round($serial * 86400) / 86400, $d1904); // Sheets membulatkan ke detik
    return new DateTimeImmutable(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $Y, $m, $d, $H, $i, $s), jkt_tz_());
}
/** DateTimeInterface → serial (jam dinding Asia/Jakarta). */
function date_serial_(DateTimeInterface $d): float {
    $l = DateTimeImmutable::createFromInterface($d)->setTimezone(jkt_tz_());
    $ts = gmmktime((int) $l->format('G'), (int) $l->format('i'), (int) $l->format('s'), (int) $l->format('n'), (int) $l->format('j'), (int) $l->format('Y'));
    $v = $ts / 86400 + 25569 + ((int) $l->format('v')) / 86400000;
    return $v == floor($v) ? (float) (int) $v : $v;
}

/** Tampilkan serial tanggal dengan kode format Excel (d/m/yyyy, yyyy-mm-dd, d-mmm-yy, h:mm AM/PM, [h]:mm ...). */
function xlsx_date_text_(float $serial, string $code, bool $d1904 = false): string {
    $sec = fmt_sections_($code)[0];
    $tok = []; $n = strlen($sec); $ampm = false;
    for ($i = 0; $i < $n; $i++) {
        $ch = $sec[$i];
        if ($ch === '"') { $j = strpos($sec, '"', $i + 1); if ($j === false) $j = $n; $tok[] = ['L', substr($sec, $i + 1, $j - $i - 1)]; $i = $j; continue; }
        if ($ch === '\\') { $tok[] = ['L', $sec[++$i] ?? '']; continue; }
        if ($ch === '_') { $i++; $tok[] = ['L', ' ']; continue; }
        if ($ch === '*') { $i++; continue; }
        if ($ch === '[') {
            $j = strpos($sec, ']', $i); if ($j === false) $j = $n;
            $in = strtolower(substr($sec, $i + 1, $j - $i - 1)); $i = $j;
            if (preg_match('/^(h+|m+|s+)$/', $in)) $tok[] = ['E' . $in[0], strlen($in)];
            continue;
        }
        if (strncasecmp(substr($sec, $i, 5), 'AM/PM', 5) === 0) { $tok[] = ['AMPM', substr($sec, $i, 5)]; $ampm = true; $i += 4; continue; }
        if (strncasecmp(substr($sec, $i, 3), 'A/P', 3) === 0) { $tok[] = ['AP', substr($sec, $i, 3)]; $ampm = true; $i += 2; continue; }
        $lc = strtolower($ch);
        if (strpos('ymdhse', $lc) !== false) {
            $k = $i; while ($k < $n && strtolower($sec[$k]) === $lc) $k++;
            $tok[] = [$lc === 'e' ? 'y' : $lc, $k - $i]; $i = $k - 1; continue;
        }
        if ($ch === '.' && $i + 1 < $n && $sec[$i + 1] === '0' && $tok && in_array(end($tok)[0], ['s', 'Es'], true)) {
            $k = $i + 1; while ($k < $n && $sec[$k] === '0') $k++;
            $tok[] = ['F', $k - $i - 1]; $i = $k - 1; continue;
        }
        if ($lc === 'g' || $lc === 'b') continue;
        $tok[] = ['L', $ch];
    }
    // m = menit bila setelah h atau sebelum s
    $idx = array_keys(array_filter($tok, fn($t) => $t[0] !== 'L'));
    foreach ($idx as $p => $ti) {
        if ($tok[$ti][0] !== 'm' || $tok[$ti][1] > 2) continue;
        $prev = $p > 0 ? $tok[$idx[$p - 1]][0] : null; $next = isset($idx[$p + 1]) ? $tok[$idx[$p + 1]][0] : null;
        if ($prev === 'h' || $prev === 'Eh' || $next === 's' || $next === 'Es') $tok[$ti][0] = 'M';
    }
    $hasFrac = (bool) array_filter($tok, fn($t) => $t[0] === 'F');
    $ser = $hasFrac ? $serial : round($serial * 86400) / 86400;
    [$Y, $mo, $d, $H, $mi, $s, $w, $ms] = serial_parts_($ser, $d1904);
    static $MON = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    static $DAY = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $out = '';
    foreach ($tok as [$t, $v]) {
        switch ($t) {
            case 'L': $out .= $v; break;
            case 'y': $out .= $v <= 2 ? substr((string) $Y, -2) : str_pad((string) $Y, 4, '0', STR_PAD_LEFT); break;
            case 'm': $out .= $v === 1 ? $mo : ($v === 2 ? js_pad2($mo) : ($v === 3 ? substr($MON[$mo - 1], 0, 3) : ($v === 5 ? $MON[$mo - 1][0] : $MON[$mo - 1]))); break;
            case 'M': $out .= $v === 1 ? $mi : js_pad2($mi); break;
            case 'd': $out .= $v === 1 ? $d : ($v === 2 ? js_pad2($d) : ($v === 3 ? substr($DAY[$w], 0, 3) : $DAY[$w])); break;
            case 'h': $hh = $ampm ? (($H % 12) ?: 12) : $H; $out .= $v === 1 ? $hh : js_pad2($hh); break;
            case 's': $out .= $v === 1 ? $s : js_pad2($s); break;
            case 'F': $out .= '.' . substr(str_pad((string) $ms, 3, '0', STR_PAD_LEFT), 0, $v); break;
            case 'AMPM': $out .= $H < 12 ? 'AM' : 'PM'; break;
            case 'AP': $out .= $H < 12 ? 'A' : 'P'; break;
            case 'Eh': $out .= str_pad((string) (int) floor($ser * 24), $v, '0', STR_PAD_LEFT); break;
            case 'Em': $out .= str_pad((string) (int) floor($ser * 1440), $v, '0', STR_PAD_LEFT); break;
            case 'Es': $out .= str_pad((string) (int) floor($ser * 86400 + 1e-9), $v, '0', STR_PAD_LEFT); break;
        }
    }
    return $out;
}

// ==================================================================
// 2c. Pembaca XLSX (streaming)
// ==================================================================

function xml_dec_(string $s): string {
    if (strpos($s, '&') !== false) $s = html_entity_decode($s, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    if (strpos($s, '_x') !== false) $s = preg_replace_callback('/_x([0-9A-Fa-f]{4})_/', fn($m) => mb_chr(hexdec($m[1]), 'UTF-8'), $s);
    return $s;
}
/** Teks dari <si>/<is>: gabungan semua <t> (rich text), tanpa <rPh> (fonetik). */
function xml_rich_text_(string $x): string {
    if (strpos($x, 'rPh') !== false) $x = preg_replace('#<(\w+:)?rPh\b.*?</(\w+:)?rPh>#s', '', $x);
    if (!preg_match_all('#<(?:\w+:)?t(?:\s[^>]*)?(?:/>|>(.*?)</(?:\w+:)?t>)#s', $x, $m)) return '';
    return xml_dec_(implode('', $m[1]));
}
function xml_attrs_(string $a): array {
    preg_match_all('/([\w:]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/', $a, $m, PREG_SET_ORDER);
    $o = [];
    foreach ($m as $x) $o[$x[1]] = xml_dec_(($x[2] ?? '') !== '' ? $x[2] : ($x[3] ?? ''));
    return $o;
}
function col_index_(string $letters): int { // 'A' → 0
    $n = 0; $l = strlen($letters);
    for ($i = 0; $i < $l; $i++) $n = $n * 26 + (ord($letters[$i]) - 64);
    return $n - 1;
}
function col_letters_(int $c): string { // 1 → 'A'
    $s = '';
    while ($c > 0) { $m = ($c - 1) % 26; $s = chr(65 + $m) . $s; $c = intdiv($c - 1, 26); }
    return $s;
}

/** Workbook XLSX yang dibuka dari byte (file sementara + ZipArchive). */
final class XlsxBook {
    public array $sheets = [];     // [['name'=>, 'path'=>, 'state'=>]]
    public bool $d1904 = false;
    public array $xfKind = [];     // indeks xf → 0 general, 1 tanggal, 2 format angka, 3 teks
    public array $xfCode = [];
    private ?array $sst = null;
    private string $sstPath = '';
    private string $tmp;
    private ZipArchive $zip;

    public function __construct(string $bytes) {
        $this->tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($this->tmp, $bytes);
        $this->zip = new ZipArchive();
        if ($bytes === '' || $this->zip->open($this->tmp, ZipArchive::RDONLY) !== true) { @unlink($this->tmp); fail_('File XLSX tidak dapat dibaca.'); }
        $root = $this->rels('_rels/.rels', '');
        $wbPath = 'xl/workbook.xml';
        foreach ($root as $r) if (str_ends_with($r['type'], '/officeDocument')) $wbPath = $r['target'];
        $wb = $this->zip->getFromName($wbPath);
        if ($wb === false) fail_('File XLSX tidak dapat dibaca.');
        $dir = str_contains($wbPath, '/') ? substr($wbPath, 0, strrpos($wbPath, '/') + 1) : '';
        $rels = []; $stylesPath = null;
        foreach ($this->rels($dir . '_rels/' . basename($wbPath) . '.rels', $dir) as $id => $r) {
            $rels[$id] = $r['target'];
            if (str_ends_with($r['type'], '/styles')) $stylesPath = $r['target'];
            if (str_ends_with($r['type'], '/sharedStrings')) $this->sstPath = $r['target'];
        }
        if ($this->sstPath === '' && $this->zip->locateName($dir . 'sharedStrings.xml') !== false) $this->sstPath = $dir . 'sharedStrings.xml';
        if (preg_match('/<(?:\w+:)?workbookPr\b([^>]*)>/', $wb, $m)) {
            $a = xml_attrs_($m[1]); $v = strtolower($a['date1904'] ?? '');
            $this->d1904 = $v === '1' || $v === 'true';
        }
        preg_match_all('/<(?:\w+:)?sheet\b([^>]*?)\/?>/', $wb, $mm);
        foreach ($mm[1] as $attr) {
            $a = xml_attrs_($attr); $rid = null;
            foreach ($a as $k => $v) if (preg_match('/^\w+:id$/', $k)) $rid = $v;
            if ($rid === null || !isset($rels[$rid])) continue;
            $this->sheets[] = ['name' => $a['name'] ?? '', 'path' => $rels[$rid], 'state' => $a['state'] ?? 'visible'];
        }
        $this->loadStyles($stylesPath ?? ($dir . 'styles.xml'));
    }
    public function __destruct() { try { $this->zip->close(); } catch (Throwable $e) {} @unlink($this->tmp); }

    private function rels(string $path, string $dir): array {
        $x = $this->zip->getFromName($path);
        $out = [];
        if ($x === false) return $out;
        preg_match_all('/<(?:\w+:)?Relationship\b([^>]*?)\/?>/', $x, $m);
        foreach ($m[1] as $attr) {
            $a = xml_attrs_($attr);
            if (($a['TargetMode'] ?? '') === 'External') continue;
            $t = $a['Target'] ?? '';
            $t = str_starts_with($t, '/') ? substr($t, 1) : $dir . $t;
            // normalisasi ../
            $parts = [];
            foreach (explode('/', $t) as $p) { if ($p === '..') array_pop($parts); elseif ($p !== '.' && $p !== '') $parts[] = $p; }
            $out[$a['Id'] ?? ''] = ['type' => $a['Type'] ?? '', 'target' => implode('/', $parts)];
        }
        return $out;
    }

    private function loadStyles(string $path): void {
        $x = $this->zip->getFromName($path);
        if ($x === false) return;
        $custom = [];
        if (preg_match_all('/<(?:\w+:)?numFmt\b([^>]*?)\/?>/', $x, $m)) {
            foreach ($m[1] as $attr) { $a = xml_attrs_($attr); if (isset($a['numFmtId'])) $custom[(int) $a['numFmtId']] = $a['formatCode'] ?? ''; }
        }
        if (!preg_match('#<(?:\w+:)?cellXfs\b[^>]*>(.*?)</(?:\w+:)?cellXfs>#s', $x, $cm)) return;
        preg_match_all('/<(?:\w+:)?xf\b([^>]*)>/', $cm[1], $xm);
        foreach ($xm[1] as $i => $attr) {
            $a = xml_attrs_($attr); $id = (int) ($a['numFmtId'] ?? 0);
            $code = $custom[$id] ?? xlsx_builtin_fmt_($id) ?? 'General';
            $isDate = isset($custom[$id]) ? xlsx_code_is_date_($code) : (xlsx_builtin_is_date_($id) || ($code !== 'General' && xlsx_code_is_date_($code)));
            $this->xfCode[$i] = $code;
            $this->xfKind[$i] = $isDate ? 1 : ($code === '@' ? 3 : (strcasecmp($code, 'General') === 0 ? 0 : 2));
        }
    }

    public function names(): array { return array_column($this->sheets, 'name'); }

    private function sst(): array {
        if ($this->sst !== null) return $this->sst;
        $this->sst = [];
        if ($this->sstPath === '') return $this->sst;
        $xr = new XMLReader();
        if (!@$xr->open('zip://' . $this->tmp . '#' . $this->sstPath, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) return $this->sst;
        $ok = $xr->read();
        while ($ok) {
            if ($xr->nodeType === XMLReader::ELEMENT && $xr->localName === 'si') {
                $x = $xr->readOuterXml();
                if (preg_match('#^<(?:\w+:)?si\b[^>]*><(?:\w+:)?t(?:\s[^>]*)?>([^<]*)</(?:\w+:)?t></(?:\w+:)?si>$#', $x, $m)) $this->sst[] = xml_dec_($m[1]);
                elseif (preg_match('#^<(?:\w+:)?si\b[^>]*/>$#', $x)) $this->sst[] = '';
                else $this->sst[] = xml_rich_text_($x);
                $ok = $xr->next();
                continue;
            }
            $ok = $xr->read();
        }
        $xr->close();
        return $this->sst;
    }

    /** getDataRange().getValues() ($display=false) / getDisplayValues() ($display=true) untuk sheet ke-$i. */
    public function grid(int $i, bool $display): array {
        $path = $this->sheets[$i]['path'];
        $sst = $this->sst();
        $xr = new XMLReader();
        if (!@$xr->open('zip://' . $this->tmp . '#' . $path, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) fail_('File XLSX tidak dapat dibaca.');
        $rows = []; $maxRow = 0; $maxCol = 0; $rowNo = 0;
        $kind = $this->xfKind; $codes = $this->xfCode; $d1904 = $this->d1904;
        $dateMemo = []; $dispMemo = []; $colMemo = [];
        $re = '#<c(?=[\s/>])(?=(?:[^>]*?\sr="([A-Z]{1,3})\d*")?)(?=(?:[^>]*?\ss="(\d+)")?)(?=(?:[^>]*?\st="(\w+)")?)[^>]*?(?:/>|>(.*?)</c>)#s';
        $ok = $xr->read();
        while ($ok) {
            if ($xr->nodeType !== XMLReader::ELEMENT || $xr->localName !== 'row') { $ok = $xr->read(); continue; }
            $ra = $xr->getAttribute('r');
            $rowNo = $ra !== null && $ra !== '' ? (int) $ra : $rowNo + 1;
            $x = $xr->readOuterXml();
            $ok = $xr->next();
            if (preg_match('/^<(\w+):row/', $x, $pm)) $x = str_replace(['<' . $pm[1] . ':', '</' . $pm[1] . ':'], ['<', '</'], $x);
            if (!preg_match_all($re, $x, $cells, PREG_SET_ORDER)) continue;
            $row = []; $col = -1; $last = -1;
            foreach ($cells as $c) {
                $col = ($c[1] ?? '') !== '' ? ($colMemo[$c[1]] ??= col_index_($c[1])) : $col + 1;
                $body = $c[4] ?? '';
                $t = ($c[3] ?? '') !== '' ? $c[3] : 'n';
                if ($body === '' && $t !== 'inlineStr') continue;
                $v = '';
                if ($t !== 'inlineStr') {
                    if (strncmp($body, '<v>', 3) === 0) $v = substr($body, 3, strpos($body, '</v>') - 3);
                    elseif (preg_match('#<v(?:\s[^>]*)?>(.*?)</v>#s', $body, $vm)) $v = $vm[1];
                    else continue; // formula tanpa nilai cache / sel kosong
                }
                switch ($t) {
                    case 's': $val = $sst[(int) $v] ?? ''; break;
                    case 'inlineStr': $val = xml_rich_text_($body); break;
                    case 'str': $val = xml_dec_($v); break;
                    case 'e': $val = xml_dec_($v); break;
                    case 'b': $val = $display ? ($v === '1' || $v === 'true' ? 'TRUE' : 'FALSE') : ($v === '1' || $v === 'true'); break;
                    case 'd':
                        $dt = new DateTimeImmutable(substr(xml_dec_($v), 0, 19), jkt_tz_());
                        $sid = ($c[2] ?? '') !== '' ? (int) $c[2] : 0;
                        $val = $display ? xlsx_date_text_(date_serial_($dt), ($kind[$sid] ?? 0) === 1 ? $codes[$sid] : 'yyyy-mm-dd', false) : $dt;
                        break;
                    default:
                        if ($v === '') continue 2;
                        $sid = ($c[2] ?? '') !== '' ? (int) $c[2] : 0;
                        $k = $kind[$sid] ?? 0;
                        if ($k === 1) {
                            if ($display) $val = $dispMemo[$sid . '|' . $v] ??= xlsx_date_text_((float) $v, $codes[$sid], $d1904);
                            else $val = $dateMemo[$v] ??= serial_date_((float) $v, $d1904);
                            break;
                        }
                        $f = (float) $v;
                        $num = ($f == floor($f) && abs($f) < 9007199254740992.0) ? (int) $f : $f;
                        if (!$display) { $val = $num; break; }
                        $val = ($k === 2) ? ($dispMemo[$sid . '|' . $v] ??= num_fmt_($num, $codes[$sid])) : num_general_($num);
                }
                if ($val === '') continue;
                $row[$col] = $val;
                if ($col > $last) $last = $col;
            }
            if ($last < 0) continue;
            $rows[$rowNo] = $row;
            if ($rowNo > $maxRow) $maxRow = $rowNo;
            if ($last + 1 > $maxCol) $maxCol = $last + 1;
        }
        $xr->close();
        if ($maxRow === 0) return [['']]; // getDataRange sheet kosong = A1
        $grid = [];
        $empty = array_fill(0, $maxCol, '');
        for ($r = 1; $r <= $maxRow; $r++) {
            if (!isset($rows[$r])) { $grid[] = $empty; continue; }
            $sp = $rows[$r]; unset($rows[$r]);
            $line = $empty;
            foreach ($sp as $ci => $vv) $line[$ci] = $vv;
            $grid[] = $line;
        }
        return $grid;
    }
}

/** Buka (dengan cache 1 entri) workbook dari byte. */
function xlsx_book_(string $bytes): XlsxBook {
    static $last = null, $key = null;
    $k = strlen($bytes) . ':' . md5($bytes);
    if ($key !== $k) { $last = null; $last = new XlsxBook($bytes); $key = $k; }
    return $last;
}

/** Nama sheet sesuai urutan workbook (termasuk sheet tersembunyi, seperti getSheets()). */
function xlsx_sheet_names_(string $bytes): array { return xlsx_book_($bytes)->names(); }

/**
 * Upload XLSX → Google Sheet → getDataRange().getValues() / getDisplayValues().
 * Pilih sheet: nama pertama di $preferredSheets yang ada; selain itu sheet pertama ($firstIfNoneMatches) atau null.
 * @return array{sheet:string,grid:array}|null
 */
function xlsx_grid_(string $bytes, array $preferredSheets = [], bool $display = false, bool $firstIfNoneMatches = true): ?array {
    $book = xlsx_book_($bytes);
    $names = $book->names();
    $idx = null;
    foreach ($preferredSheets as $p) {
        if ($p === null || $p === '') continue;
        $i = array_search((string) $p, $names, true);
        if ($i !== false) { $idx = $i; break; }
    }
    if ($idx === null) {
        if (!$firstIfNoneMatches || !$names) return null;
        $idx = 0;
    }
    return ['sheet' => $names[$idx], 'grid' => $book->grid($idx, $display)];
}

// ==================================================================
// 3. SpreadsheetApp tiruan (workbook sementara) + ekspor XLSX
// ==================================================================

/** SpreadsheetApp.BorderStyle */
final class BorderStyle {
    const DOTTED = 'DOTTED', DASHED = 'DASHED', SOLID = 'SOLID', SOLID_MEDIUM = 'SOLID_MEDIUM', SOLID_THICK = 'SOLID_THICK', DOUBLE = 'DOUBLE';
}
/** SpreadsheetApp.create / flush. */
final class SpreadsheetApp {
    public static function create(string $name, ?int $rows = null, ?int $cols = null): Wb { return Wb::create($name, $rows, $cols); }
    public static function flush(): void {}
}

/** Spreadsheet sementara di memori. Gaya sel disimpan sebagai id gaya ter-intern (hemat memori). */
final class Wb {
    public string $name; public string $id; public string $tz = 'Asia/Jakarta';
    /** @var WbSheet[] */ public array $sheets = [];
    public array $styles = [[]];      // id → props
    private array $styleIdx = ['[]' => 0];
    private array $trans = [];

    public static function create(string $name, ?int $rows = null, ?int $cols = null): self {
        $w = new self(); $w->name = $name; $w->id = 'TMP' . bin2hex(random_bytes(8));
        $w->sheets[] = new WbSheet($w, 'Sheet1', $rows ?? 1000, $cols ?? 26);
        return $w;
    }
    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function rename(string $n): void { $this->name = $n; }
    public function setSpreadsheetTimeZone(string $tz): void { $this->tz = $tz; }
    public function getSpreadsheetTimeZone(): string { return $this->tz; }
    public function getSheets(): array { return $this->sheets; }
    public function getNumSheets(): int { return count($this->sheets); }
    public function getActiveSheet(): WbSheet { return $this->sheets[0]; }
    public function getSheetByName(string $n): ?WbSheet { foreach ($this->sheets as $s) if ($s->name === $n) return $s; return null; }
    public function insertSheet(?string $name = null, ?int $index = null): WbSheet {
        if ($name === null) { $k = count($this->sheets) + 1; while ($this->getSheetByName('Sheet' . $k)) $k++; $name = 'Sheet' . $k; }
        if ($this->getSheetByName($name)) fail_('A sheet with the name "' . $name . '" already exists. Please enter another name.');
        $s = new WbSheet($this, $name, 1000, 26);
        if ($index === null || $index >= count($this->sheets)) $this->sheets[] = $s; else array_splice($this->sheets, max(0, $index), 0, [$s]);
        return $s;
    }
    public function deleteSheet(WbSheet $s): void {
        if (count($this->sheets) < 2) fail_('You can\'t remove all the sheets in a document.');
        $this->sheets = array_values(array_filter($this->sheets, fn($x) => $x !== $s));
    }
    /** id gaya baru = gaya $id dengan prop diganti. */
    public function styleWith(int $id, string $prop, $val): int {
        $tk = $id . "\x1f" . $prop . "\x1f" . serialize($val);
        if (isset($this->trans[$tk])) return $this->trans[$tk];
        $p = $this->styles[$id];
        if ($val === null) unset($p[$prop]); else $p[$prop] = $val;
        ksort($p);
        $k = serialize($p);
        if (!isset($this->styleIdx[$k])) { $this->styleIdx[$k] = count($this->styles); $this->styles[] = $p; }
        return $this->trans[$tk] = $this->styleIdx[$k];
    }
}

final class WbSheet {
    public array $cells = [];   // [row][col] → nilai (1-based)
    public array $sty = [];     // [row][col] → id gaya
    public array $widths = [];  // col → lebar (karakter Excel)
    public array $merges = [];  // [r1,c1,r2,c2]
    public int $frozenRows = 0, $frozenCols = 0;
    public ?string $autoFilterRef = null;
    public bool $hidden = false, $gridlines = true;
    public function __construct(public Wb $wb, public string $name, public int $maxRows, public int $maxCols) {}

    public function getParent(): Wb { return $this->wb; }
    public function getName(): string { return $this->name; }
    public function getSheetName(): string { return $this->name; }
    public function setName(string $n): self {
        $o = $this->wb->getSheetByName($n);
        if ($o && $o !== $this) fail_('A sheet with the name "' . $n . '" already exists. Please enter another name.');
        $this->name = $n; return $this;
    }
    public function getIndex(): int { return array_search($this, $this->wb->sheets, true) + 1; }
    public function activate(): self { return $this; }
    public function getMaxRows(): int { return $this->maxRows; }
    public function getMaxColumns(): int { return $this->maxCols; }
    public function insertColumnsAfter(int $after, int $n): self { $this->maxCols += $n; return $this; }
    public function insertRowsAfter(int $after, int $n): self { $this->maxRows += $n; return $this; }
    public function getLastRow(): int {
        $m = 0; foreach ($this->cells as $r => $row) foreach ($row as $v) if ($v !== '' && $v !== null) { if ($r > $m) $m = $r; break; }
        return $m;
    }
    public function getLastColumn(): int {
        $m = 0; foreach ($this->cells as $row) foreach ($row as $c => $v) if ($v !== '' && $v !== null && $c > $m) $m = $c;
        return $m;
    }
    /** getRange(row, col[, numRows[, numCols]]) atau getRange('A1:C3'). */
    public function getRange($a, ?int $col = null, ?int $nr = null, ?int $nc = null): WbRange {
        if (is_string($a) && $col === null) {
            if (!preg_match('/^\$?([A-Z]+)\$?(\d+)(?::\$?([A-Z]+)\$?(\d+))?$/i', trim($a), $m)) fail_('Range not found');
            $r1 = (int) $m[2]; $c1 = col_index_(strtoupper($m[1])) + 1;
            $r2 = isset($m[4]) ? (int) $m[4] : $r1; $c2 = isset($m[3]) ? col_index_(strtoupper($m[3])) + 1 : $c1;
            return $this->rng(min($r1, $r2), min($c1, $c2), abs($r2 - $r1) + 1, abs($c2 - $c1) + 1);
        }
        return $this->rng((int) $a, (int) $col, $nr ?? 1, $nc ?? 1);
    }
    private function rng(int $r, int $c, int $nr, int $nc): WbRange {
        if ($r < 1 || $c < 1) fail_('The coordinates of the range are outside the dimensions of the sheet.');
        if ($nr < 1) fail_('The number of rows in the range must be at least 1.');
        if ($nc < 1) fail_('The number of columns in the range must be at least 1.');
        if ($r + $nr - 1 > $this->maxRows) $this->maxRows = $r + $nr - 1;
        if ($c + $nc - 1 > $this->maxCols) $this->maxCols = $c + $nc - 1;
        return new WbRange($this, $r, $c, $nr, $nc);
    }
    public function getDataRange(): WbRange { return $this->rng(1, 1, max(1, $this->getLastRow()), max(1, $this->getLastColumn())); }
    public function appendRow(array $row): self {
        $row = array_values($row); if (!$row) return $this;
        $this->getRange($this->getLastRow() + 1, 1, 1, count($row))->setValues([$row]);
        return $this;
    }
    public function clear(): self { $this->cells = []; $this->sty = []; $this->merges = []; return $this; }
    public function setFrozenRows(int $n): self { $this->frozenRows = $n; return $this; }
    public function setFrozenColumns(int $n): self { $this->frozenCols = $n; return $this; }
    public function setAutoFilter(?string $ref): self { $this->autoFilterRef = $ref ? strtoupper(trim($ref)) : null; return $this; }
    public function getFrozenRows(): int { return $this->frozenRows; }
    public function getFrozenColumns(): int { return $this->frozenCols; }
    public function hideSheet(): self { $this->hidden = true; return $this; }
    public function showSheet(): self { $this->hidden = false; return $this; }
    public function isSheetHidden(): bool { return $this->hidden; }
    public function setHiddenGridlines(bool $h): self { $this->gridlines = !$h; return $this; }
    /** Lebar kolom dalam piksel (Sheets) → karakter Excel (Calibri 11: ±7 px/karakter). */
    public function setColumnWidth(int $col, int $px): self { $this->widths[$col] = round($px / 7, 2); return $this; }
    public function setColumnWidths(int $start, int $n, int $px): self { for ($c = $start; $c < $start + $n; $c++) $this->setColumnWidth($c, $px); return $this; }
    public function getColumnWidth(int $col): int { return (int) round(($this->widths[$col] ?? 100 / 7) * 7); }
    public function autoResizeColumn(int $col): self { return $this->autoResizeColumns($col, 1); }
    /** Lebar ≈ panjang teks terpanjang × 1,1 + 2 (dibatasi 100 karakter); kolom kosong tidak diubah. */
    public function autoResizeColumns(int $start, int $n): self {
        $max = [];
        foreach ($this->cells as $r => $row) {
            foreach ($row as $c => $v) {
                if ($c < $start || $c >= $start + $n || $v === '' || $v === null) continue;
                $len = 0;
                foreach (explode("\n", $this->display($r, $c)) as $line) $len = max($len, mb_strwidth($line, 'UTF-8'));
                $p = $this->wb->styles[$this->sty[$r][$c] ?? 0];
                $f = max(1, ($p['size'] ?? 10) / 11) * (($p['bold'] ?? false) ? 1.1 : 1);
                $len *= $f;
                if ($len > ($max[$c] ?? 0)) $max[$c] = $len;
            }
        }
        foreach ($max as $c => $len) $this->widths[$c] = min(100, round($len * 1.1 + 2, 2));
        return $this;
    }
    /** Teks tampilan satu sel. */
    public function display(int $r, int $c): string {
        $v = $this->cells[$r][$c] ?? '';
        $fmt = $this->wb->styles[$this->sty[$r][$c] ?? 0]['fmt'] ?? '';
        if ($v === null || $v === '') return '';
        if (is_bool($v)) return $v ? 'TRUE' : 'FALSE';
        if ($v instanceof DateTimeInterface) return xlsx_date_text_(date_serial_($v), $fmt !== '' && xlsx_code_is_date_($fmt) ? $fmt : 'yyyy-mm-dd');
        if (is_int($v) || is_float($v)) return $fmt === '@' ? js_str($v) : ($fmt !== '' && xlsx_code_is_date_($fmt) ? xlsx_date_text_((float) $v, $fmt) : num_fmt_($v, $fmt));
        return (string) $v;
    }
}

final class WbRange {
    public function __construct(public WbSheet $sh, public int $r, public int $c, public int $nr, public int $nc) {}
    public function getSheet(): WbSheet { return $this->sh; }
    public function getRow(): int { return $this->r; }
    public function getColumn(): int { return $this->c; }
    public function getNumRows(): int { return $this->nr; }
    public function getNumColumns(): int { return $this->nc; }
    public function getLastRow(): int { return $this->r + $this->nr - 1; }
    public function getLastColumn(): int { return $this->c + $this->nc - 1; }
    public function getA1Notation(): string {
        $a = col_letters_($this->c) . $this->r;
        return $this->nr === 1 && $this->nc === 1 ? $a : $a . ':' . col_letters_($this->getLastColumn()) . $this->getLastRow();
    }

    private function norm($v) {
        if ($v === null) return '';
        if (is_float($v) && !is_finite($v)) return js_str($v);
        if (is_float($v) && $v == floor($v) && abs($v) < 9007199254740992) return (int) $v;
        if (is_array($v) || (is_object($v) && !($v instanceof DateTimeInterface))) return js_str($v);
        return $v;
    }
    private function put(int $r, int $c, $v): void {
        $v = $this->norm($v);
        if ($v === '') { unset($this->sh->cells[$r][$c]); return; }
        $this->sh->cells[$r][$c] = $v;
        if ($v instanceof DateTimeInterface) { // Sheets otomatis memberi format tanggal
            $sid = $this->sh->sty[$r][$c] ?? 0;
            if (($this->sh->wb->styles[$sid]['fmt'] ?? '') === '') {
                $hasTime = DateTimeImmutable::createFromInterface($v)->setTimezone(jkt_tz_())->format('His') !== '000000';
                $this->sh->sty[$r][$c] = $this->sh->wb->styleWith($sid, 'fmt', $hasTime ? 'yyyy-mm-dd hh:mm:ss' : 'yyyy-mm-dd');
            }
        }
    }
    public function setValues(array $vals): self {
        $vals = array_values($vals);
        if (count($vals) !== $this->nr) fail_('The number of rows in the data does not match the number of rows in the range. The data has ' . count($vals) . ' but the range has ' . $this->nr . '.');
        foreach ($vals as $i => $row) {
            $row = array_values((array) $row);
            if (count($row) !== $this->nc) fail_('The number of columns in the data does not match the number of columns in the range. The data has ' . count($row) . ' but the range has ' . $this->nc . '.');
            foreach ($row as $j => $v) $this->put($this->r + $i, $this->c + $j, $v);
        }
        return $this;
    }
    public function setValue($v): self {
        for ($i = 0; $i < $this->nr; $i++) for ($j = 0; $j < $this->nc; $j++) $this->put($this->r + $i, $this->c + $j, $v);
        return $this;
    }
    public function getValues(): array {
        $o = [];
        for ($i = 0; $i < $this->nr; $i++) { $row = []; for ($j = 0; $j < $this->nc; $j++) $row[] = $this->sh->cells[$this->r + $i][$this->c + $j] ?? ''; $o[] = $row; }
        return $o;
    }
    public function getValue() { return $this->sh->cells[$this->r][$this->c] ?? ''; }
    public function getDisplayValues(): array {
        $o = [];
        for ($i = 0; $i < $this->nr; $i++) { $row = []; for ($j = 0; $j < $this->nc; $j++) $row[] = $this->sh->display($this->r + $i, $this->c + $j); $o[] = $row; }
        return $o;
    }
    public function getDisplayValue(): string { return $this->sh->display($this->r, $this->c); }
    public function clearContent(): self { return $this->setValue(''); }

    /** Terapkan satu properti gaya ke semua sel. */
    private function style(string $prop, $val): self {
        $wb = $this->sh->wb;
        for ($i = 0; $i < $this->nr; $i++) {
            $r = $this->r + $i;
            for ($j = 0; $j < $this->nc; $j++) {
                $c = $this->c + $j;
                $this->sh->sty[$r][$c] = $wb->styleWith($this->sh->sty[$r][$c] ?? 0, $prop, $val);
            }
        }
        return $this;
    }
    /** Versi jamak (setBackgrounds dll): matriks nilai per sel. */
    private function styles(string $prop, array $m, ?callable $f = null): self {
        $m = array_values($m);
        if (count($m) !== $this->nr) fail_('The number of rows in the data does not match the number of rows in the range. The data has ' . count($m) . ' but the range has ' . $this->nr . '.');
        $wb = $this->sh->wb;
        foreach ($m as $i => $row) {
            $row = array_values((array) $row);
            if (count($row) !== $this->nc) fail_('The number of columns in the data does not match the number of columns in the range. The data has ' . count($row) . ' but the range has ' . $this->nc . '.');
            foreach ($row as $j => $v) {
                $r = $this->r + $i; $c = $this->c + $j;
                $this->sh->sty[$r][$c] = $wb->styleWith($this->sh->sty[$r][$c] ?? 0, $prop, $f ? $f($v) : $v);
            }
        }
        return $this;
    }
    private static function color($c): ?string {
        if ($c === null || $c === '') return null;
        $c = ltrim(trim((string) $c), '#');
        static $named = ['white' => 'FFFFFF', 'black' => '000000', 'red' => 'FF0000', 'green' => '008000', 'blue' => '0000FF', 'yellow' => 'FFFF00', 'gray' => '808080', 'grey' => '808080', 'orange' => 'FFA500'];
        if (isset($named[strtolower($c)])) return $named[strtolower($c)];
        if (strlen($c) === 3) $c = $c[0] . $c[0] . $c[1] . $c[1] . $c[2] . $c[2];
        return preg_match('/^[0-9A-Fa-f]{6}$/', $c) ? strtoupper($c) : null;
    }
    private static function weight($w): ?bool { return $w === null ? null : (strtolower((string) $w) === 'bold' ? true : null); }

    public function setNumberFormat(string $f): self { return $this->style('fmt', $f === '' ? null : $f); }
    public function setNumberFormats(array $m): self { return $this->styles('fmt', $m, fn($f) => (string) $f === '' ? null : (string) $f); }
    public function setBackground($c): self { return $this->style('fill', self::color($c)); }
    public function setBackgrounds(array $m): self { return $this->styles('fill', $m, fn($c) => self::color($c)); }
    public function setBackgroundRGB(int $r, int $g, int $b): self { return $this->style('fill', sprintf('%02X%02X%02X', $r, $g, $b)); }
    public function setFontColor($c): self { return $this->style('color', self::color($c)); }
    public function setFontColors(array $m): self { return $this->styles('color', $m, fn($c) => self::color($c)); }
    public function setFontWeight($w): self { return $this->style('bold', self::weight($w)); }
    public function setFontWeights(array $m): self { return $this->styles('bold', $m, fn($w) => self::weight($w)); }
    public function setFontStyle($s): self { return $this->style('italic', strtolower((string) $s) === 'italic' ? true : null); }
    public function setFontLine($s): self { return $this->style('line', in_array($s, ['underline', 'line-through'], true) ? $s : null); }
    public function setFontFamily($f): self { return $this->style('font', $f === null || $f === '' ? null : (string) $f); }
    public function setFontSize($n): self { return $this->style('size', $n === null ? null : (float) $n); }
    public function setWrap(bool $w): self { return $this->style('wrap', $w ? true : null); }
    public function setWrapStrategy($s): self { return $this->style('wrap', $s === 'WRAP' ? true : null); }
    public function setHorizontalAlignment($a): self { $a = strtolower((string) $a); return $this->style('h', in_array($a, ['left', 'center', 'right'], true) ? $a : null); }
    public function setVerticalAlignment($a): self { $a = strtolower((string) $a); return $this->style('v', $a === 'middle' ? 'center' : (in_array($a, ['top', 'bottom'], true) ? $a : null)); }
    public function setHorizontalAlignments(array $m): self { return $this->styles('h', $m, fn($a) => in_array(strtolower((string) $a), ['left', 'center', 'right'], true) ? strtolower((string) $a) : null); }

    /** setBorder(top, left, bottom, right, vertical, horizontal[, color, style]); null = tidak diubah. */
    public function setBorder(?bool $top, ?bool $left, ?bool $bottom, ?bool $right, ?bool $vertical, ?bool $horizontal, $color = null, $style = null): self {
        $map = [BorderStyle::SOLID => 'thin', BorderStyle::SOLID_MEDIUM => 'medium', BorderStyle::SOLID_THICK => 'thick', BorderStyle::DASHED => 'dashed', BorderStyle::DOTTED => 'dotted', BorderStyle::DOUBLE => 'double'];
        $b = [$map[$style ?? BorderStyle::SOLID] ?? 'thin', self::color($color) ?? '000000'];
        $wb = $this->sh->wb;
        $r2 = $this->getLastRow(); $c2 = $this->getLastColumn();
        for ($r = $this->r; $r <= $r2; $r++) for ($c = $this->c; $c <= $c2; $c++) {
            $sides = [
                'bt' => $r === $this->r ? $top : $horizontal,
                'bb' => $r === $r2 ? $bottom : $horizontal,
                'bl' => $c === $this->c ? $left : $vertical,
                'br' => $c === $c2 ? $right : $vertical,
            ];
            $id = $this->sh->sty[$r][$c] ?? 0;
            foreach ($sides as $k => $on) if ($on !== null) $id = $wb->styleWith($id, $k, $on ? $b : null);
            $this->sh->sty[$r][$c] = $id;
        }
        return $this;
    }
    public function merge(): self { if ($this->nr > 1 || $this->nc > 1) $this->sh->merges[] = [$this->r, $this->c, $this->getLastRow(), $this->getLastColumn()]; return $this; }
    public function mergeAcross(): self { if ($this->nc > 1) for ($i = 0; $i < $this->nr; $i++) $this->sh->merges[] = [$this->r + $i, $this->c, $this->r + $i, $this->getLastColumn()]; return $this; }
    public function mergeVertically(): self { if ($this->nr > 1) for ($j = 0; $j < $this->nc; $j++) $this->sh->merges[] = [$this->r, $this->c + $j, $this->getLastRow(), $this->c + $j]; return $this; }
}

// ------------------------------------------------------------------
// Penulis XLSX
// ------------------------------------------------------------------

function x_esc_(string $s): string {
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $s)) $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
    if (!mb_check_encoding($s, 'UTF-8')) $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function x_num_($v): string {
    if (is_int($v)) return (string) $v;
    $s = json_encode($v); // terpendek & presisi penuh
    return str_contains($s, 'e') || str_contains($s, 'E') ? strtoupper($s) : $s;
}

/** Tulis Wb menjadi file .xlsx (Blob). */
function wb_xlsx_(Wb $wb): Blob {
    $styles = $wb->styles;
    $fonts = ['<font><sz val="10"/><color rgb="FF000000"/><name val="Arial"/><family val="2"/></font>'];
    $fills = ['<fill><patternFill patternType="none"/></fill>', '<fill><patternFill patternType="gray125"/></fill>'];
    $borders = ['<border><left/><right/><top/><bottom/><diagonal/></border>'];
    $numFmts = []; $xfs = []; $fontIdx = [$fonts[0] => 0]; $fillIdx = []; $borderIdx = [$borders[0] => 0];
    $builtin = ['General' => 0, '0' => 1, '0.00' => 2, '#,##0' => 3, '#,##0.00' => 4, '0%' => 9, '0.00%' => 10, '0.00E+00' => 11, '@' => 49];
    $xfOf = []; // id gaya → indeks xf
    $xfKey = [];
    $mkXf = function (int $sid) use (&$styles, &$fonts, &$fills, &$borders, &$numFmts, &$xfs, &$fontIdx, &$fillIdx, &$borderIdx, $builtin, &$xfKey): int {
        $p = $styles[$sid];
        $f = '<font>' . (!empty($p['bold']) ? '<b/>' : '') . (!empty($p['italic']) ? '<i/>' : '') . (($p['line'] ?? '') === 'line-through' ? '<strike/>' : '') . (($p['line'] ?? '') === 'underline' ? '<u/>' : '')
            . '<sz val="' . x_num_($p['size'] ?? 10) . '"/><color rgb="FF' . ($p['color'] ?? '000000') . '"/><name val="' . x_esc_($p['font'] ?? 'Arial') . '"/><family val="2"/></font>';
        $fontId = $fontIdx[$f] ??= (function () use (&$fonts, $f) { $fonts[] = $f; return count($fonts) - 1; })();
        $fillId = 0;
        if (isset($p['fill'])) {
            $fl = '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $p['fill'] . '"/><bgColor indexed="64"/></patternFill></fill>';
            $fillId = $fillIdx[$fl] ??= (function () use (&$fills, $fl) { $fills[] = $fl; return count($fills) - 1; })();
        }
        $side = fn($tag, $b) => $b ? '<' . $tag . ' style="' . $b[0] . '"><color rgb="FF' . $b[1] . '"/></' . $tag . '>' : '<' . $tag . '/>';
        $bd = '<border>' . $side('left', $p['bl'] ?? null) . $side('right', $p['br'] ?? null) . $side('top', $p['bt'] ?? null) . $side('bottom', $p['bb'] ?? null) . '<diagonal/></border>';
        $borderId = $borderIdx[$bd] ??= (function () use (&$borders, $bd) { $borders[] = $bd; return count($borders) - 1; })();
        $fmt = $p['fmt'] ?? 'General'; $nf = $builtin[$fmt] ?? null;
        if ($nf === null) { if (!isset($numFmts[$fmt])) $numFmts[$fmt] = 164 + count($numFmts); $nf = $numFmts[$fmt]; }
        $al = '';
        if (isset($p['h']) || isset($p['v']) || !empty($p['wrap'])) {
            $al = '<alignment' . (isset($p['h']) ? ' horizontal="' . $p['h'] . '"' : '') . ' vertical="' . ($p['v'] ?? 'bottom') . '"' . (!empty($p['wrap']) ? ' wrapText="1"' : '') . '/>';
        }
        $x = '<xf numFmtId="' . $nf . '" fontId="' . $fontId . '" fillId="' . $fillId . '" borderId="' . $borderId . '" xfId="0"'
            . ($nf ? ' applyNumberFormat="1"' : '') . ($fontId ? ' applyFont="1"' : '') . ($fillId ? ' applyFill="1"' : '') . ($borderId ? ' applyBorder="1"' : '')
            . ($al !== '' ? ' applyAlignment="1">' . $al . '</xf>' : '/>');
        if (!isset($xfKey[$x])) { $xfKey[$x] = count($xfs); $xfs[] = $x; }
        return $xfKey[$x];
    };
    $mkXf(0); // xf 0 = default

    $sst = []; $sstList = []; $sstCount = 0;
    $zipTmp = tempnam(sys_get_temp_dir(), 'wbx');
    $zip = new ZipArchive();
    if ($zip->open($zipTmp, ZipArchive::OVERWRITE) !== true) fail_('Gagal membuat file XLSX.');
    $sheetsMeta = []; $usedNames = [];
    $active = 0; foreach ($wb->sheets as $i => $s) if (!$s->hidden) { $active = $i; break; }
    foreach ($wb->sheets as $si => $sh) {
        // nama sheet valid untuk Excel: maks 31 karakter, tanpa []:*?/\
        $nm = mb_substr(preg_replace('/[\[\]:*?\/\\\\]/u', '_', $sh->name), 0, 31, 'UTF-8');
        if ($nm === '') $nm = 'Sheet' . ($si + 1);
        $base = $nm; $k = 2; while (isset($usedNames[mb_strtolower($nm)])) { $suf = ' (' . $k++ . ')'; $nm = mb_substr($base, 0, 31 - strlen($suf)) . $suf; }
        $usedNames[mb_strtolower($nm)] = true;
        $sheetsMeta[] = ['name' => $nm, 'hidden' => $sh->hidden && $si !== $active];

        $rowsIdx = array_unique(array_merge(array_keys($sh->cells), array_keys($sh->sty)));
        sort($rowsIdx);
        $maxR = 1; $maxC = 1;
        $parts = [];
        foreach ($rowsIdx as $r) {
            $cv = $sh->cells[$r] ?? []; $cs = $sh->sty[$r] ?? [];
            $cols = array_unique(array_merge(array_keys($cv), array_keys($cs)));
            sort($cols);
            $line = '<row r="' . $r . '">';
            $any = false;
            foreach ($cols as $c) {
                $sid = $cs[$c] ?? 0;
                $xf = $sid ? ($xfOf[$sid] ??= $mkXf($sid)) : 0;
                $v = $cv[$c] ?? '';
                if ($v === '' && $xf === 0) continue;
                $any = true;
                if ($r > $maxR) $maxR = $r; if ($c > $maxC) $maxC = $c;
                $ref = col_letters_($c) . $r;
                $sAttr = $xf ? ' s="' . $xf . '"' : '';
                $fmt = $styles[$sid]['fmt'] ?? '';
                if ($v === '') { $line .= '<c r="' . $ref . '"' . $sAttr . '/>'; continue; }
                if ($v instanceof DateTimeInterface) $v = date_serial_($v);
                if (is_bool($v)) { $line .= '<c r="' . $ref . '"' . $sAttr . ' t="b"><v>' . ($v ? 1 : 0) . '</v></c>'; continue; }
                if ((is_int($v) || is_float($v)) && $fmt !== '@') { $line .= '<c r="' . $ref . '"' . $sAttr . '><v>' . x_num_($v) . '</v></c>'; continue; }
                $str = is_string($v) ? $v : js_str($v);
                if (!isset($sst[$str])) { $sst[$str] = count($sstList); $sstList[] = $str; }
                $sstCount++;
                $line .= '<c r="' . $ref . '"' . $sAttr . ' t="s"><v>' . $sst[$str] . '</v></c>';
            }
            if ($any) $parts[] = $line . '</row>';
        }
        foreach ($sh->merges as $mg) { $maxR = max($maxR, $mg[2]); $maxC = max($maxC, $mg[3]); }
        $view = '<sheetView workbookViewId="0"' . ($si === $active ? ' tabSelected="1"' : '') . ($sh->gridlines ? '' : ' showGridLines="0"') . '>';
        $fr = $sh->frozenRows; $fc = $sh->frozenCols;
        if ($fr > 0 || $fc > 0) {
            $pane = $fr > 0 && $fc > 0 ? 'bottomRight' : ($fr > 0 ? 'bottomLeft' : 'topRight');
            $tl = col_letters_($fc + 1) . ($fr + 1);
            $view .= '<pane' . ($fc > 0 ? ' xSplit="' . $fc . '"' : '') . ($fr > 0 ? ' ySplit="' . $fr . '"' : '') . ' topLeftCell="' . $tl . '" activePane="' . $pane . '" state="frozen"/>'
                . '<selection pane="' . $pane . '" activeCell="' . $tl . '" sqref="' . $tl . '"/>';
        }
        $view .= '</sheetView>';
        $colsXml = '';
        if ($sh->widths) {
            ksort($sh->widths);
            $colsXml = '<cols>';
            foreach ($sh->widths as $c => $w) $colsXml .= '<col min="' . $c . '" max="' . $c . '" width="' . x_num_((float) $w) . '" customWidth="1"/>';
            $colsXml .= '</cols>';
        }
        $mergeXml = '';
        if ($sh->merges) {
            $mergeXml = '<mergeCells count="' . count($sh->merges) . '">';
            foreach ($sh->merges as $mg) $mergeXml .= '<mergeCell ref="' . col_letters_($mg[1]) . $mg[0] . ':' . col_letters_($mg[3]) . $mg[2] . '"/>';
            $mergeXml .= '</mergeCells>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1' . ($maxR > 1 || $maxC > 1 ? ':' . col_letters_($maxC) . $maxR : '') . '"/>'
            . '<sheetViews>' . $view . '</sheetViews>'
            . '<sheetFormatPr defaultColWidth="14.43" defaultRowHeight="15"/>'
            . $colsXml . '<sheetData>' . implode('', $parts) . '</sheetData>'
            . ($sh->autoFilterRef ? '<autoFilter ref="' . x_esc_($sh->autoFilterRef) . '"/>' : '') . $mergeXml
            . '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/></worksheet>';
        $zip->addFromString('xl/worksheets/sheet' . ($si + 1) . '.xml', $xml);
        unset($parts, $xml);
    }
    // sharedStrings
    $ss = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $sstCount . '" uniqueCount="' . count($sstList) . '">';
    $buf = [];
    foreach ($sstList as $s) {
        $e = x_esc_($s);
        $buf[] = '<si><t' . ($s !== trim($s) || str_contains($s, "\n") ? ' xml:space="preserve"' : '') . '>' . $e . '</t></si>';
    }
    $zip->addFromString('xl/sharedStrings.xml', $ss . implode('', $buf) . '</sst>');
    // styles
    $nfXml = '';
    if ($numFmts) { $nfXml = '<numFmts count="' . count($numFmts) . '">'; foreach ($numFmts as $code => $id) $nfXml .= '<numFmt numFmtId="' . $id . '" formatCode="' . x_esc_($code) . '"/>'; $nfXml .= '</numFmts>'; }
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $nfXml
        . '<fonts count="' . count($fonts) . '">' . implode('', $fonts) . '</fonts>'
        . '<fills count="' . count($fills) . '">' . implode('', $fills) . '</fills>'
        . '<borders count="' . count($borders) . '">' . implode('', $borders) . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="' . count($xfs) . '">' . implode('', $xfs) . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '<dxfs count="0"/><tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/></styleSheet>');
    // workbook
    $sx = ''; $rx = ''; $ct = '';
    foreach ($sheetsMeta as $i => $m) {
        $n = $i + 1;
        $sx .= '<sheet name="' . x_esc_($m['name']) . '" sheetId="' . $n . '"' . ($m['hidden'] ? ' state="hidden"' : '') . ' r:id="rId' . $n . '"/>';
        $rx .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
        $ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    $k = count($sheetsMeta);
    $rx .= '<Relationship Id="rId' . ($k + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '<Relationship Id="rId' . ($k + 2) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<workbookPr/><bookViews><workbookView activeTab="' . $active . '"/></bookViews><sheets>' . $sx . '</sheets><calcPr calcId="0"/></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rx . '</Relationships>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>');
    $now = gmdate('Y-m-d\TH:i:s\Z');
    $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>' . x_esc_($wb->name) . '</dc:title><dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified></cp:coreProperties>');
    $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>DSM</Application></Properties>');
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . $ct
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
        . '</Types>');
    $zip->close();
    $bytes = (string) file_get_contents($zipTmp);
    @unlink($zipTmp);
    return new Blob($bytes, XLSX_MIME, $wb->name . '.xlsx');
}
/** Pengganti exportGoogleSheetXlsx_(id): ekspor Wb sementara menjadi Blob .xlsx. */
function exportGoogleSheetXlsx_(Wb $wb): Blob { return wb_xlsx_($wb); }

// ==================================================================
// 4. Port 1:1 — template, file DITOLAK, pembaca file upload
// ==================================================================

/** Object.fromEntries(heads.map((h,i)=>[h, r[i]])) — sel di luar baris (undefined) tidak ikut (seperti JSON.stringify). */
function row_obj_(array $heads, array $r, ?callable $f = null): array {
    $o = [];
    foreach ($heads as $i => $h) {
        if (array_key_exists($i, $r)) $o[$h] = $f ? $f($r[$i]) : $r[$i];
        elseif ($f) $o[$h] = $f(null);
        else unset($o[$h]);
    }
    return $o;
}
/** r.some(v => v !== '' && v != null) */
function row_has_value_(array $r): bool { foreach ($r as $v) if ($v !== '' && $v !== null) return true; return false; }
/** r.some(v => PortalCore.text(v)) */
function row_has_text_(array $r): bool { foreach ($r as $v) if (cell_text_($v) !== '') return true; return false; }
function blob_ok_($file, int $max, bool $needBytes = true): bool {
    return $file instanceof Blob && (!$needBytes || $file->size() > 0) && $file->size() <= $max;
}

// ------------------------------------------------------------------
// TemplateGuide.gs
// ------------------------------------------------------------------
const TEMPLATE_GUIDE = [
  'Master_Grade' => [ 'title' => 'Master Grade dealer per brand', 'cols' => [
    ['ID DEALER', 'Kode dealer, harus ada di Master_Customer. Satu dealer satu baris', 'C0001'],
    ['NAMA DEALER', 'Bantu baca saja, tidak dibaca sistem', 'Mitra Cell Sumber'],
    ['GRADE <BRAND>', 'Grade dealer di brand itu (teks bebas, maks 20 karakter). Kosong = tidak ada grade', 'A']],
    'exHeads' => ['ID DEALER', 'NAMA DEALER', 'GRADE ITEL', 'GRADE MOTOROLA', 'GRADE VILLAON'],
    'ex' => [['C0001', 'Mitra Cell Sumber', 'A', 'B', 'GOLD'], ['C0045', 'Maju Jaya', 'C', '', '']] ],
  'Master_Promotor' => [ 'title' => 'Master Promotor (dealer ber-promotor)', 'cols' => [
    ['ID DEALER', 'Kode dealer, harus ada di Master_Customer. Satu dealer satu baris', 'C0001'],
    ['NAMA DEALER', 'Bantu baca saja, tidak dibaca sistem', 'Mitra Cell Sumber'],
    ['PROMOTOR <BRAND>', 'Jumlah promotor brand itu di dealer (angka bulat). Kosong = 0. Boleh tambah kolom brand baru / kompetitor', '1']],
    'exHeads' => ['ID DEALER', 'NAMA DEALER', 'PROMOTOR ITEL', 'PROMOTOR MOTOROLA', 'PROMOTOR VIVO'],
    'ex' => [['C0001', 'Mitra Cell Sumber', '1', '1', ''], ['C0045', 'Maju Jaya', '2', '', '1'], ['C0090', 'Sinar Phone', '', '', '']],
    'notes' => ['Baris dengan semua kolom brand kosong tetap BER-PROMOTOR (brand belum diisi).'] ],
  'Master_Transaksi' => [ 'title' => 'Upload SELL THRU (ST)', 'cols' => [
    ['TANGGAL TRANSAKSI', 'Tanggal transaksi, format YYYY-MM-DD', '2026-10-01'],
    ['BADAN USAHA', 'Nama badan usaha penjual', 'PT MSI'],
    ['NO TRANSAKSI', 'Nomor faktur / transaksi', 'INV/2610/0001'],
    ['NOMOR SJ', 'Nomor surat jalan (boleh kosong)', 'SJ/2610/0001'],
    ['KODE GUDANG', 'Kode gudang asal barang', 'GD-CRB'],
    ['KODE PRODUK', 'Kode barang, harus ada di Master_Product', 'SKU1'],
    ['KODE CUSTOMER', 'Kode dealer, harus ada di Master_Customer', 'C0001'],
    ['NIK SALES', 'NIK sales pemegang dealer', '2002'],
    ['QTY', 'Jumlah unit. Retur pakai angka minus. Tidak boleh 0', '2'],
    ['AMOUNT', 'Nilai rupiah transaksi, tanpa titik/Rp', '5400000']],
    'ex' => [['2026-10-01', 'PT MSI', 'INV/2610/0001', 'SJ/2610/0001', 'GD-CRB', 'SKU1', 'C0001', '2002', '2', '5400000'],
         ['2026-10-02', 'PT MSI', 'RTR/2610/0003', '', 'GD-CRB', 'SKU1', 'C0001', '2002', '-1', '-2700000']] ],
  'Master_SO' => [ 'title' => 'Upload SELL OUT (SO)', 'cols' => [
    ['KODE CUSTOMER', 'Kode dealer, harus ada di Master_Customer', 'C0001'],
    ['TANGGAL SO', 'Tanggal barang terjual di dealer, YYYY-MM-DD', '2026-10-03'],
    ['KODE PRODUK', 'Kode barang, harus ada di Master_Product', 'SKU1'],
    ['QTY', 'Jumlah unit terjual ke konsumen', '1']],
    'ex' => [['C0001', '2026-10-03', 'SKU1', '1'], ['C0001', '2026-10-03', 'SKU7', '2']] ],
  'Master_Customer' => [ 'title' => 'Edit massal dealer', 'cols' => [
    ['KODE CUSTOMER', 'Kunci dealer. JANGAN diubah', 'C0001'],
    ['NAMA CUSTOMER', 'Nama toko/outlet', 'Mitra Cell Sumber'],
    ['NAMA INDUK CUSTOMER', 'Nama induk kalau satu pemilik punya beberapa outlet', 'Mitra Group'],
    ['NIK SALES', 'Pemegang dealer. JANGAN diubah di sini — pakai Transfer Dealer', '2002'],
    ['REGION', 'Region dealer', 'CIREBON'],
    ['KOTA', 'Kota / kecamatan', 'Sumber'],
    ['NAMA PIC', 'Nama pemilik / kontak', 'Budi Ramadhan'],
    ['ALAMAT', 'Alamat lengkap', 'Jl. Sudirman No. 52'],
    ['No. HP', 'Nomor WhatsApp, awali 08 atau 62', '081234567890'],
    ['ID_DEALER', 'Kode lama/alias, pisahkan | kalau lebih dari satu', 'C0001|MCS-01']],
    'notes' => ['Hanya ubah kolom yang perlu. Baris yang tidak berubah otomatis dilewati.'] ],
  'Transfer_Dealer' => [ 'title' => 'Transfer dealer ke sales lain', 'cols' => [
    ['KODE CUSTOMER', 'Kode dealer yang dipindah (referensi/key)', 'C0001'],
    ['NAMA CUSTOMER', 'Referensi nama dealer, tidak dibaca saat upload', 'Mitra Cell'],
    ['BIG REGION', 'Referensi Big Region saat file dibuat', 'JAWA BARAT'],
    ['REGION', 'Referensi region/portfolio sales saat ini', 'CIREBON'],
    ['NIK SALES SAAT INI', 'Referensi owner saat ini', '2002'],
    ['NAMA SALES SAAT INI', 'Referensi nama owner saat ini', 'Rina'],
    ['NEW_SALES_NIK', 'NIK sales tujuan', '2003'],
    ['EFFECTIVE_DATE', 'Mulai berlaku, YYYY-MM-DD', '2026-10-01'],
    ['NOTE', 'Alasan (boleh kosong)', 'Rotasi area']],
    'ex' => [['C0001', '2003', '2026-10-01', 'Rotasi area'], ['C0015', '2003', '2026-10-01', '']],
    'notes' => ['Transaksi lama tetap milik sales lama; mulai EFFECTIVE_DATE tercatat ke sales baru.'] ],
  'Master_Product' => [ 'title' => 'Master Product', 'cols' => [
    ['KODE BARANG', 'Kunci produk, sama dengan KODE PRODUK di transaksi', 'SKU1'],
    ['NAMA BARANG', 'Nama lengkap barang', 'ITEL A70 4/128'],
    ['BRAND', 'Brand, harus ada di Product_Rules', 'ITEL'],
    ['KATEGORI PRODUK', 'Kategori produk; menentukan device vs aksesori', 'HP'],
    ['TYPE', 'Type/seri — dipakai TYPE_FILTER di KPI, tulis konsisten', 'A70 4/128'],
    ['STATUS', 'ACTIVE = aktif; INACTIVE = produk lama/discontinue. Jangan hapus produk historis', 'ACTIVE']],
    'ex' => [['SKU101', 'ITEL A70 4/128', 'ITEL', 'A70 4/128'], ['SKU102', 'ITEL SPARK 20 8/256', 'ITEL', 'SPARK 20']] ],
  'Target_Input' => [ 'title' => 'Target bulanan per Sales', 'cols' => [
    ['PERIODE TARGET', 'Bulan target, format YYYY-MM', '2026-10'],
    ['NIK SALES', 'NIK sales', '2002'],
    ['NAMA SALES', 'Info saja, tidak dibaca sistem', 'Rina Wulandari'],
    ['ST <BRAND>', 'Target SELL THRU (unit) brand itu', 'ST ITEL = 120'],
    ['DA <BRAND>', 'Target dealer aktif brand itu', 'DA ITEL = 30'],
    ['NOO <BRAND>', 'Target dealer baru (kolom opsional, tambah kalau brand punya target NOO)', 'NOO ITEL = 3'],
    ['IDDLE <BRAND>', 'Target dealer-brand yang kembali order setelah tepat 2 bulan penuh kosong ST (kolom opsional)', 'IDDLE ITEL = 5'],
    ['OMZET <BRAND>', 'Target omzet rupiah (kolom opsional)', 'OMZET ITEL = 250000000']],
    'notes' => ['KOSONG = target belum diisi (KPI jadi "belum lengkap"). 0 = memang tidak diwajibkan.',
            'Brand baru: tambah kolom ke samping, misal "ST BRANDBARU" dan "DA BRANDBARU".',
            'Nama kolom tidak boleh dobel. Brand ALL = total semua brand.'],
    'exHeads' => ['PERIODE TARGET', 'NIK SALES', 'NAMA SALES', 'ST ITEL', 'ST VILLAON', 'DA ITEL', 'DA VILLAON', 'NOO ITEL'],
    'ex' => [['2026-10', '2002', 'Rina Wulandari', '120', '40', '30', '12', '3'], ['2026-10', '2003', 'Dedi Kurnia', '100', '0', '25', '', '']] ],
  'Target_Periode' => [ 'title' => 'Target khusus KPI (pakai INDIKATOR_ID)', 'cols' => [
    ['PERIODE', 'Bulan, format YYYY-MM', '2026-10'],
    ['NIK', 'NIK sales', '2002'],
    ['BRAND', 'Harus sama dengan BRAND di KPI_Config', 'ITEL'],
    ['METRIK', 'Harus sama dengan METRIK di KPI_Config: QTY / DA / NOO / IDDLE / OMZET', 'QTY'],
    ['INDIKATOR_ID', 'Harus sama persis dengan INDIKATOR_ID di KPI_Config', 'ITEL_SPARK'],
    ['TARGET', 'Angka target', '25']],
    'notes' => ['Target rutin (tanpa INDIKATOR_ID) diisi di Target Bulanan per Sales, bukan di sini.'],
    'ex' => [['2026-10', '2002', 'ITEL', 'QTY', 'ITEL_SPARK', '25'], ['2026-10', '2002', 'VILLAON', 'DA', 'DA_VILLAON_3', '8']] ],
  'KPI_Config' => [ 'title' => 'Konfigurasi KPI', 'cols' => [
    ['PERIODE', 'Bulan berlaku, format YYYY-MM', '2026-10'],
    ['WILAYAH_KPI', 'Nama Big Region, persis sama seperti di master', 'JABAR TIMUR'],
    ['KELOMPOK_KPI', 'Nama kelompok, bebas', 'SELL THRU, COVERAGE, EKSPANSI'],
    ['METRIK', 'QTY (SELL THRU unit), DA, NOO, IDDLE, atau OMZET', 'QTY'],
    ['BRAND', 'Nama brand, atau ALL untuk semua brand', 'ITEL'],
    ['BOBOT', 'Bobot di dalam kelompoknya (buat total per kelompok = 100)', '50'],
    ['BATAS_SKOR', 'Skor maksimal per indikator, dalam %', '120'],
    ['AKTIF', 'TRUE dipakai, FALSE dimatikan', 'TRUE'],
    ['INDIKATOR_ID', 'Kosong untuk indikator biasa. WAJIB diisi kalau pakai TYPE_FILTER atau DA minimal ≠ 2', 'ITEL_SPARK'],
    ['LABEL', 'Nama yang tampil di portal', 'QTY Itel Spark 20'],
    ['TYPE_FILTER', 'Type tertentu, lebih dari satu dipisah |. Cuma untuk QTY atau DA. Harus persis sama dengan TYPE di Master_Product', 'SPARK 20|SPARK 20 PRO'],
    ['MIN_QTY_DA', 'Minimal unit supaya dealer dihitung aktif. Kosong = 2', '3']],
    'notes' => ['Skor indikator = capai % (dibatasi BATAS_SKOR). Skor kelompok = rata-rata tertimbang BOBOT. Skor akhir = rata-rata semua kelompok.',
            'IDDLE: Juli order, Agustus dan September tanpa ST net positif, Oktober order = 1; jeda 3 bulan atau lebih tidak dihitung. Hitungan per dealer-brand; ALL adalah jumlah pasangan yang memenuhi aturan. Histori tiga bulan sebelumnya harus lengkap.',
            'Indikator dengan INDIKATOR_ID butuh target sendiri di "Target Khusus KPI" (lihat tabel paling bawah).'],
    'ex' => [['2026-10', 'JABAR TIMUR', 'SELL THRU', 'QTY', 'ALL', '50', '120', 'TRUE', '', 'QTY All Brand', '', ''],
         ['2026-10', 'JABAR TIMUR', 'SELL THRU', 'QTY', 'ITEL', '30', '120', 'TRUE', '', 'QTY Itel', '', ''],
         ['2026-10', 'JABAR TIMUR', 'SELL THRU', 'QTY', 'ITEL', '20', '120', 'TRUE', 'ITEL_SPARK', 'QTY Itel Spark 20', 'SPARK 20|SPARK 20 PRO', ''],
         ['2026-10', 'JABAR TIMUR', 'COVERAGE', 'DA', 'ALL', '60', '110', 'TRUE', '', 'Dealer Aktif', '', ''],
         ['2026-10', 'JABAR TIMUR', 'COVERAGE', 'DA', 'VILLAON', '40', '110', 'TRUE', 'DA_VILLAON_3', 'DA Villaon min 3 unit', '', '3'],
         ['2026-10', 'JABAR TIMUR', 'EKSPANSI', 'NOO', 'ALL', '100', '100', 'TRUE', '', 'NOO All Brand', '', '']],
    'extra' => [[ 'title' => 'Contoh target untuk indikator khusus (diisi di Target Khusus KPI)', 'heads' => ['PERIODE', 'NIK', 'BRAND', 'METRIK', 'INDIKATOR_ID', 'TARGET'],
      'rows' => [['2026-10', '2002', 'ITEL', 'QTY', 'ITEL_SPARK', '25'], ['2026-10', '2002', 'VILLAON', 'DA', 'DA_VILLAON_3', '8'], ['2026-10', '2003', 'ITEL', 'QTY', 'ITEL_SPARK', '20']] ]] ],
  'Product_Rules' => [ 'title' => 'Product Rules (daftar brand)', 'cols' => [
    ['BRAND', 'Nama brand seperti di Master_Product', 'ITEL'],
    ['BRAND_UTAMA', 'Brand induk untuk pengelompokan (biasanya sama)', 'ITEL'],
    ['MASUK_QTY', 'TRUE = dihitung sebagai unit perangkat (SELL THRU). FALSE = aksesori, tidak dihitung unit', 'TRUE']],
    'ex' => [['ITEL', 'ITEL', 'TRUE'], ['ITEL ACC', 'ITEL', 'FALSE']] ],
  'Region_Map' => [ 'title' => 'Region Map', 'cols' => [
    ['REGION', 'Nama region / sub region operasional', 'CIREBON'],
    ['WILAYAH_KPI', 'Big Region tempat region itu bernaung', 'JABAR TIMUR'],
    ['KANAL', 'Kosong = penjualan sales. ONLINE = penjualan online (ikut total nasional untuk RGM ke atas, tidak dihitung DEALER AKTIF / NOO / SELL OUT / ranking / KPI)', 'ONLINE']],
    'ex' => [['CIREBON', 'JABAR TIMUR', ''], ['KUNINGAN', 'JABAR TIMUR', ''], ['ONLINE TECNO', 'ONLINE TECNO', 'ONLINE']] ],
  'DOS_Config' => [ 'title' => 'Batas DOS', 'cols' => [
    ['WILAYAH', 'Big Region, atau ALL untuk default semua wilayah', 'ALL'],
    ['DOS_MIN', 'Di bawah angka ini stok dianggap kurang (hari)', '7'],
    ['DOS_MAX', 'Di atas angka ini stok dianggap berlebih (hari)', '45']],
    'ex' => [['ALL', '7', '45'], ['JABAR TIMUR', '10', '40']] ],
  'Master_Sales' => [ 'title' => 'Struktur organisasi', 'cols' => [
    ['NIK KARYAWAN', 'NIK user yang sudah ada', '2002'],
    ['POSISI', 'SALES, ASM, RGM, HEAD OF SALES, CHIEF OPERATING OFFICER, CHIEF COMMERCIAL OFFICER, ADMIN, SUPER ADMIN', 'SALES'],
    ['REGION', 'Region operasional (lihat Region_Map)', 'CIREBON'],
    ['NIK ATASAN', 'NIK atasan langsung', '2001']],
    'notes' => ['Hanya untuk mengubah user yang sudah ada. User baru pakai Tambah user massal.'],
    'ex' => [['2002', 'SALES', 'CIREBON', '2001'], ['2001', 'ASM', 'CIREBON', '2000']] ],
  'Template_Dealer' => [ 'title' => 'Tambah dealer baru', 'cols' => [
    ['KODE_DEALER_UNIQ', 'WAJIB. Kode toko/dealer yang unik dan dipakai transaksi. Tidak boleh sama dengan dealer lain', 'BT03283'],
    ['KODE_INDUK_DEALER', 'WAJIB. Kode induk/customer. Boleh sama untuk beberapa toko/dealer di bawah induk yang sama', '008.448'],
    ['NAMA INDUK CUSTOMER', 'WAJIB. Nama induk customer', 'Tunggal Jaya Sumedang'],
    ['NIK SALES', 'WAJIB. NIK sales pemegang default dealer', '2025.04.23.06'],
    ['KOTA', 'Opsional', 'SUMEDANG'],
    ['ALAMAT', 'Opsional', 'Jl. Raya Jatinunggal'],
    ['No. HP', 'Opsional. Nomor WhatsApp, awali 08/62', '081234567890'],
    ['SUB REGION', 'Opsional. Info tambahan', 'BIG TASIK']],
    'notes' => ['KODE_DEALER_UNIQ = identitas toko yang unik (disimpan pada ID_DEALER di database).', 'KODE_INDUK_DEALER = kode induk/customer (disimpan pada KODE CUSTOMER di database) dan boleh dipakai lebih dari satu toko.', 'Maksimal 500 dealer per file. Satu baris tidak valid membatalkan seluruh upload.', 'Menambah dealer tidak otomatis jadi NOO; NOO dihitung dari transaksi pertama per brand.'],
    'ex' => [['BT03283', '008.448', 'Tunggal Jaya Sumedang', '2025.04.23.06', 'SUMEDANG', 'Jl. Raya Jatinunggal', '081234567890', 'BIG TASIK']] ],
  'Template_User' => [ 'title' => 'Tambah user massal', 'cols' => [
    ['NIK', 'NIK karyawan baru (teks)', '2010'],
    ['NAMA', 'Nama lengkap', 'Siti Aminah'],
    ['POSISI', 'SALES, ASM, RGM, HEAD OF SALES, ADMIN, dst', 'SALES'],
    ['REGION', 'Region operasional di Region_Map', 'CIREBON'],
    ['NIK_ATASAN', 'NIK atasan langsung (boleh atasan yang ada di file yang sama)', '2001'],
    ['WILAYAH_ADMIN', 'Khusus ADMIN: wilayah yang dikelola, pisahkan |', 'JABAR TIMUR|JABAR BARAT']],
    'notes' => ['Maksimal 100 user per file. NIK yang sudah ada dilewati.', 'Role rangkap: buat user dulu, lalu edit lewat aplikasi.'],
    'ex' => [['2010', 'Siti Aminah', 'SALES', 'CIREBON', '2001', ''], ['9001', 'Admin Jabar', 'ADMIN', '', '1002', 'JABAR TIMUR|JABAR BARAT']] ]
];

function templateGuideWrite_(Wb $ss, string $sheet, array $heads, ?array $notes): void {
    $g = TEMPLATE_GUIDE[$sheet] ?? null; if (!$g && !($notes && count($notes))) return;
    $ns = $ss->insertSheet('PETUNJUK'); $r = 1; $W = 4;
    $put = function (array $vals, ?string $style = null) use ($ns, &$r, $W) {
        $vals = array_values($vals);
        $w = max($W, count($vals)); if ($ns->getMaxColumns() < $w) $ns->insertColumnsAfter($ns->getMaxColumns(), $w - $ns->getMaxColumns());
        $rg = $ns->getRange($r, 1, 1, count($vals))->setNumberFormat('@')->setValues([array_map(fn($x) => $x === null ? '' : js_str($x), $vals)])->setFontFamily('Calibri')->setFontSize(11)->setVerticalAlignment('top')->setWrap(true);
        if ($style === 'title') $rg->setFontSize(14)->setFontWeight('bold')->setFontColor('#10394E');
        if ($style === 'sec') $rg->setFontWeight('bold')->setFontColor('#10394E');
        if ($style === 'head') $rg->setBackground('#10394E')->setFontColor('#ffffff')->setFontWeight('bold');
        if ($style === 'row') $rg->setBorder(true, true, true, true, true, true, '#D5DEE8', BorderStyle::SOLID);
        if ($style === 'ex') $rg->setBackground('#F4F8FD')->setBorder(true, true, true, true, true, true, '#D5DEE8', BorderStyle::SOLID);
        $r++;
    };
    $put(['PETUNJUK · ' . ($g ? $g['title'] : $sheet)], 'title'); $r++;
    foreach (array_merge($notes ?? [], $g && !empty($g['notes']) ? $g['notes'] : []) as $n) $put(['• ' . js_str($n)]);
    $put(['• Isi data di sheet "' . $sheet . '" mulai baris 2. Jangan ubah nama kolom. Sheet PETUNJUK ini tidak dibaca sistem.']);
    if ($g) {
        $r++; $put(['Arti kolom'], 'sec'); $put(['Kolom', 'Isi', 'Contoh'], 'head');
        foreach ($g['cols'] as $c) $put($c, 'row');
        if (!empty($g['ex'])) {
            $r++; $put(['Contoh isian'], 'sec');
            $ord = $g['exHeads'] ?? array_map(fn($c) => $c[0], $g['cols']);
            $eh = $g['exHeads'] ?? (count($heads) > 1 ? array_values($heads) : $ord);
            $put($eh, 'head');
            foreach ($g['ex'] as $x) $put(array_map(function ($h) use ($ord, $x) { $i = array_search($h, $ord, true); return $i === false ? '' : ($x[$i] ?? null); }, $eh), 'ex');
        }
        foreach ($g['extra'] ?? [] as $t) { $r++; $put([$t['title']], 'sec'); $put($t['heads'], 'head'); foreach ($t['rows'] as $x) $put($x, 'ex'); }
    }
    $ns->setColumnWidth(1, 190); $ns->setColumnWidth(2, 420); $ns->setColumnWidth(3, 230);
    for ($c = 4; $c <= $ns->getMaxColumns(); $c++) $ns->setColumnWidth($c, 130);
    $ns->setHiddenGridlines(true);
}

// ------------------------------------------------------------------
// BulkAdmin.gs
// ------------------------------------------------------------------

/** Template XLSX (sheet data teks + sheet PETUNJUK) → {name, mime, base64}. */
function templateXlsx_(string $name, string $sheet, array $heads, ?array $rows, ?array $notes): array {
    $ss = SpreadsheetApp::create('TMP_' . now_ms());
    $ss->setSpreadsheetTimeZone('Asia/Jakarta');
    $sh = $ss->getSheets()[0]; $sh->setName($sheet);
    $heads = array_values($heads);
    $data = array_merge([$heads], array_values($rows ?: []));
    $sh->getRange(1, 1, count($data), count($heads))->setNumberFormat('@')->setValues($data);
    $sh->setFrozenRows(1);
    $sh->getRange(1, 1, 1, count($heads))->setBackground('#10394E')->setFontColor('#ffffff')->setFontWeight('bold')->setFontFamily('Calibri')->setFontSize(11);
    if (count($data) > 1) $sh->getRange(2, 1, count($data) - 1, count($heads))->setFontFamily('Calibri')->setFontSize(11);
    $sh->autoResizeColumns(1, count($heads));
    templateGuideWrite_($ss, $sheet, $heads, $notes);
    SpreadsheetApp::flush();
    $blob = exportGoogleSheetXlsx_($ss);
    return ['name' => $name, 'mime' => XLSX_MIME, 'base64' => base64_encode($blob->getBytes())];
}


/** Working file XLSX: data existing + petunjuk, kode selalu text, kolom referensi diberi warna abu-abu. */
function workingXlsx_(string $name, string $sheet, array $heads, array $rows, array $notes = [], array $locked = [], array $reference = []): array {
    $ss = SpreadsheetApp::create('TMP_' . now_ms()); $ss->setSpreadsheetTimeZone('Asia/Jakarta');
    $sh = $ss->getSheets()[0]; $sh->setName($sheet); $heads = array_values($heads);
    $data = array_merge([$heads], array_values($rows));
    $sh->getRange(1, 1, count($data), count($heads))->setNumberFormat('@')->setValues($data)->setFontFamily('Calibri')->setFontSize(11);
    $sh->setFrozenRows(1); $sh->getRange(1, 1, 1, count($heads))->setBackground('#10394E')->setFontColor('#ffffff')->setFontWeight('bold');
    if (count($data) > 1) {
        foreach ($heads as $i => $h) {
            if (in_array($h, $locked, true)) $sh->getRange(2, $i + 1, count($data) - 1, 1)->setBackground('#E5E7EB');
            elseif (in_array($h, $reference, true)) $sh->getRange(2, $i + 1, count($data) - 1, 1)->setBackground('#F3F4F6')->setFontColor('#64748B');
        }
    }
    $sh->autoResizeColumns(1, count($heads));
    $meta = ['File kerja berisi data existing saat download. Ubah hanya kolom yang diperbolehkan, lalu upload kembali.',
             'Semua kode/NIK ditulis sebagai Text agar nol di depan tidak hilang.',
             'Abu-abu = key/referensi; kolom referensi diabaikan ketika upload.',
             'Dibuat: ' . (new DateTimeImmutable('now', jkt_tz_()))->format('d/m/Y H:i') . ' · jumlah data: ' . count($rows)];
    templateGuideWrite_($ss, $sheet, $heads, array_merge($meta, $notes));
    SpreadsheetApp::flush(); $blob = exportGoogleSheetXlsx_($ss);
    return ['name' => $name, 'mime' => XLSX_MIME, 'base64' => base64_encode($blob->getBytes())];
}

/** Baca XLSX/CSV upload (tampilan) → {heads, rows:[{head:nilai}]}. */
function bulkReadRows_($file, $preferred): array {
    if (!blob_ok_($file, 8 * 1024 * 1024)) fail_('Pilih XLSX/CSV maksimal 8 MB.');
    if (preg_match('/\.csv$/i', $file->getName())) {
        $text = strip_bom_($file->getDataAsString('UTF-8'));
        $grid = parseCsv_($text, csv_delim_count_($text));
    } elseif (preg_match('/\.xlsx$/i', $file->getName())) {
        $grid = xlsx_grid_($file->getBytes(), truthy($preferred) ? [(string) $preferred] : [], true, true)['grid'];
    } else fail_('Gunakan XLSX atau CSV.');
    $heads = array_map('cell_text_', array_shift($grid) ?? []);
    if (!count($heads)) fail_('Header file kosong.');
    $rows = [];
    foreach ($grid as $r) if (row_has_text_($r)) $rows[] = row_obj_($heads, $r);
    return ['heads' => $heads, 'rows' => $rows];
}

// ------------------------------------------------------------------
// Reject.gs
// ------------------------------------------------------------------

/** File DITOLAK: baris asli + kolom ALASAN & SOLUSI. */
function rejectXlsxBlob_($fileName, array $heads, array $rejected): Blob {
    $base = preg_replace('/\.(xlsx|csv)$/i', '', js_str(js_or($fileName, 'upload')));
    $stamp = date_fmt_(new DateTimeImmutable('now'), 'yyyyMMdd-HHmm');
    $name = 'DITOLAK-' . $base . '-' . $stamp . '.xlsx';
    $H = array_merge(array_values(array_filter(array_values($heads), fn($h) => truthy($h))), ['ALASAN', 'SOLUSI']);
    $ss = SpreadsheetApp::create('TMP_REJ_' . now_ms());
    $ss->setSpreadsheetTimeZone('Asia/Jakarta');
    $sh = $ss->getSheets()[0]; $sh->setName('DITOLAK');
    $cols = array_slice($H, 0, -2);
    $data = [$H];
    foreach ($rejected as $x) {
        $line = [];
        foreach ($cols as $h) { $v = $x['row'][$h] ?? null; $line[] = $v === null ? '' : ($v instanceof DateTimeInterface ? js_date_string_($v) : js_str($v)); }
        $line[] = js_or($x['alasan'] ?? '', ''); $line[] = js_or($x['solusi'] ?? '', '');
        $data[] = $line;
    }
    $n = count($data); $w = count($H);
    $sh->getRange(1, 1, $n, $w)->setNumberFormat('@')->setValues($data)->setFontFamily('Calibri')->setFontSize(11);
    $sh->getRange(1, 1, 1, $w)->setBackground('#10394E')->setFontColor('#ffffff')->setFontWeight('bold');
    if ($n > 1) {
        $sh->getRange(2, $w - 1, $n - 1, 1)->setBackground('#FEE2E2')->setFontColor('#991B1B')->setFontWeight('bold');
        $sh->getRange(2, $w, $n - 1, 1)->setBackground('#E0F2FE')->setFontColor('#075985');
    }
    $sh->setFrozenRows(1); $sh->autoResizeColumns(1, $w); $sh->setColumnWidth($w - 1, 320); $sh->setColumnWidth($w, 380);
    $info = $ss->insertSheet('PETUNJUK');
    $info->getRange(1, 1, 4, 1)->setValues([
        ['Baris di sheet DITOLAK ditolak saat upload/sync. Baris lain di file asli SUDAH diproses.'],
        ['Perbaiki sesuai kolom SOLUSI, lalu hapus kolom ALASAN & SOLUSI (boleh juga dibiarkan — diabaikan saat upload).'],
        ['Upload / taruh ulang file ini ke folder Sync "1. MASUK" atau menu upload yang sama.'],
        ['Jumlah baris ditolak: ' . count($rejected)]]);
    SpreadsheetApp::flush();
    return exportGoogleSheetXlsx_($ss)->setName($name);
}
function rejectBase64_($fileName, array $heads, array $rejected): array {
    $b = rejectXlsxBlob_($fileName, $heads, $rejected);
    return ['name' => $b->getName(), 'mime' => XLSX_MIME, 'base64' => base64_encode($b->getBytes())];
}
/** Kode yang kemungkinan dirusak Excel: 008.070 → 8.07, 001.365 → 1.365 */
function excelCodeNorm_($k): ?string {
    return preg_match('/^0*(\d+)\.(\d+?)0*$/', js_trim(js_str(js_or($k, ''))), $m) ? $m[1] . '.' . $m[2] : null;
}
function excelMangledMap_($codes): array {
    $out = [];
    foreach (($codes ?: []) as $c) { $c = js_trim(js_str(js_or($c, ''))); $n = excelCodeNorm_($c); if ($n !== null && $n !== '' && $n !== $c && !truthy($out[$n] ?? null)) $out[$n] = $c; }
    return $out;
}
function strictSolusi_($msg): string {
    $msg = js_str($msg ?? '');
    if (preg_match('/luar bulan/i', $msg)) return 'Tanggal di luar bulan yang diproses. Periksa kolom tanggal / pisahkan file per bulan.';
    if (preg_match('/cutoff/i', $msg)) return 'Tanggal melewati batas impor. Periksa tanggal transaksi.';
    if (preg_match('/KODE PRODUK .* tidak ada/i', $msg)) return 'Daftarkan kode produk ini di Master Product (Sync-Master-Product) lalu upload ulang. Cek juga 0 di depan yang hilang karena Excel.';
    if (preg_match('/NIK SALES .* tidak dikenal/i', $msg)) return 'Dealer ini belum terdaftar di Master Customer, jadi NIK tidak bisa diganti otomatis ke pemegang dealer. Daftarkan dealernya (Upload dealer / Sync Master Customer) lalu upload ulang.';
    if (preg_match('/Referensi transaksi tidak valid/i', $msg)) return 'Kode dealer / kode barang / NIK tidak dikenal. Daftarkan dulu (Upload dealer / Master Product) atau perbaiki kodenya. Cek juga kode yang rusak diolah Excel (0 di depan hilang).';
    if (preg_match('/Qty\/amount|Angka tidak valid/i', $msg)) return 'Isi QTY dan AMOUNT dengan angka yang valid.';
    if (preg_match('/Tanda qty/i', $msg)) return 'QTY dan AMOUNT harus sama-sama positif (jual) atau sama-sama negatif (retur).';
    if (preg_match('/duplikat/i', $msg)) return 'Hapus baris kembar, atau isi kolom ID_BARIS unik kalau memang transaksi terpisah.';
    if (preg_match('/di luar pilihan/i', $msg)) return 'Dealer/brand baris ini bukan bagian dari region/brand yang dipilih di layar upload. Pilih region/brand yang sesuai, atau pisahkan baris ini ke file lain.';
    if (preg_match('/terpetakan/i', $msg)) return 'Dealer belum punya Big Region. Pastikan dealer ada di Master_Customer dengan NIK Sales yang benar, atau atur di Admin › Big Region per dealer.';
    return 'Periksa isi baris ini sesuai template.';
}
/** Bersihkan pesan "baris N" (replace pertama saja, seperti JS tanpa flag g). */
function strict_clean_(string $s): string {
    $s = preg_replace('/\s*\(baris \d+\)/iu', '', $s, 1);
    $s = preg_replace('/\s*(pada )?baris \d+/iu', '', $s, 1);
    return trim(preg_replace('/^Baris \d+:?\s*/iu', '', $s, 1));
}
/** ST/SO ketat: kumpulkan SEMUA baris bermasalah (maks $maxRej) dengan memanggil $parse berulang. */
function strictScan_($kind, array $objects, $meta, callable $parse, $maxRej = null): array {
    $maxRej = truthy($maxRej) ? (int) $maxRej : 300;
    $objects = array_values($objects);
    $idx = array_keys($objects); $rejected = []; $guard = 0;
    $msgOf = fn(Throwable $e) => $e->getMessage();
    $pick = fn(array $ix) => array_map(fn($i) => $objects[$i], $ix);
    while ($guard++ < $maxRej + 5) {
        try { $parse($pick($idx)); return ['rejected' => $rejected, 'stopped' => false]; }
        catch (Throwable $e) {
            $msg = $msgOf($e);
            $n = preg_match('/[Bb]aris(?: transaksi duplikat)? (\d+)|pada baris (\d+)/', $msg, $m) ? (int) (($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? 0)) : 0;
            if (!$n || $n - 2 >= count($idx)) {
                // pesan tanpa nomor baris → cek satu per satu
                $keep = []; $found = 0;
                foreach ($idx as $i) {
                    if (count($rejected) >= $maxRej) { $keep[] = $i; continue; }
                    try { $parse([$objects[$i]]); $keep[] = $i; }
                    catch (Throwable $x) {
                        $mm = $msgOf($x); $found++;
                        $rejected[] = ['row' => $objects[$i], 'line' => $i + 2, 'alasan' => 'Baris ' . ($i + 2) . ': ' . strict_clean_($mm), 'solusi' => strictSolusi_($mm)];
                    }
                }
                if (!$found) return ['rejected' => $rejected, 'stopped' => true, 'error' => $msg];
                $idx = $keep;
                if (count($rejected) >= $maxRej) return ['rejected' => $rejected, 'stopped' => true, 'error' => 'Lebih dari ' . $maxRej . ' baris bermasalah — perbaiki dulu yang ini.'];
                continue;
            }
            $pos = $n - 2;
            if ($pos < 0) { // JS: idx[-1] = undefined, splice(-1,1) membuang elemen terakhir
                $rejected[] = ['row' => null, 'line' => null, 'alasan' => 'Baris NaN: ' . strict_clean_(preg_match('/duplikat/i', $msg) ? 'transaksi kembar dengan baris lain di file ini' : $msg), 'solusi' => strictSolusi_($msg)];
                array_splice($idx, -1, 1);
            } else {
                $orig = $idx[$pos];
                $rejected[] = ['row' => $objects[$orig], 'line' => $orig + 2, 'alasan' => 'Baris ' . ($orig + 2) . ': ' . strict_clean_(preg_match('/duplikat/i', $msg) ? 'transaksi kembar dengan baris lain di file ini' : $msg), 'solusi' => strictSolusi_($msg)];
                array_splice($idx, $pos, 1);
            }
            if (count($rejected) >= $maxRej) return ['rejected' => $rejected, 'stopped' => true, 'error' => 'Lebih dari ' . $maxRej . ' baris bermasalah — perbaiki dulu yang ini.'];
        }
    }
    return ['rejected' => $rejected, 'stopped' => true];
}

// ------------------------------------------------------------------
// Pembaca file upload (bagian baca saja)
// ------------------------------------------------------------------

/** Pilih sheet: $name, atau satu-satunya sheet; null bila tidak ada. */
function xlsx_pick_single_(string $bytes, array $names): ?string {
    $all = xlsx_sheet_names_($bytes);
    foreach ($names as $n) if (in_array($n, $all, true)) return $n;
    return count($all) === 1 ? $all[0] : null;
}
/** Objek baris getValues: tanggal → 'yyyy-MM-dd'. */
function values_rows_(array $heads, array $data): array {
    $out = [];
    foreach ($data as $r) {
        if (!row_has_value_($r)) continue;
        $out[] = row_obj_($heads, $r, null);
    }
    foreach ($out as &$o) foreach ($o as $k => $v) if ($v instanceof DateTimeInterface) $o[$k] = date_fmt_($v, 'yyyy-MM-dd');
    unset($o);
    return $out;
}
function shift_heads_(array &$data): array {
    if (!$data) fail_("Cannot read properties of undefined (reading 'map')");
    return array_map('cell_text_', array_shift($data));
}

/** RegioanlImport.gs — file ST (getValues). */
function stReadFile_($file): array {
    if (!($file instanceof Blob) || $file->size() > 20 * 1024 * 1024) fail_('Pilih XLSX/CSV maksimal 20 MB.');
    $name = $file->getName();
    if (preg_match('/\.csv$/i', $name)) {
        $s = strip_bom_($file->getDataAsString('UTF-8'));
        $data = parseCsv_($s, csv_delim_count_($s));
    } elseif (preg_match('/\.xlsx$/i', $name)) {
        $pick = xlsx_pick_single_($file->getBytes(), ['Master_Transaksi']);
        if ($pick === null) fail_('Sheet Master_Transaksi tidak ditemukan.');
        $data = xlsx_grid_($file->getBytes(), [$pick], false)['grid'];
    } else fail_('Format XLSX atau CSV.');
    $heads = shift_heads_($data);
    foreach (['TANGGAL TRANSAKSI', 'NO TRANSAKSI', 'KODE PRODUK', 'KODE CUSTOMER', 'NIK SALES', 'QTY', 'AMOUNT'] as $k) if (!in_array($k, $heads, true)) fail_('Kolom wajib ' . $k);
    return values_rows_($heads, $data);
}

/** SellOut.gs — file SO (getValues). */
function soReadFile_($file): array {
    if (!($file instanceof Blob) || $file->size() > 20 * 1024 * 1024) fail_('Pilih XLSX/CSV maksimal 20 MB.');
    $name = $file->getName();
    if (preg_match('/\.csv$/i', $name)) {
        $s = strip_bom_($file->getDataAsString('UTF-8'));
        $data = parseCsv_($s, csv_delim_count_($s));
    } elseif (preg_match('/\.xlsx$/i', $name)) {
        $pick = xlsx_pick_single_($file->getBytes(), ['Master_SO']);
        if ($pick === null) fail_('Pilih sheet Master_SO atau workbook satu sheet.');
        $data = xlsx_grid_($file->getBytes(), [$pick], false)['grid'];
    } else fail_('Format harus XLSX/CSV.');
    $heads = shift_heads_($data);
    foreach (['KODE CUSTOMER', 'TANGGAL SO', 'KODE PRODUK', 'QTY'] as $h) if (!in_array($h, $heads, true)) fail_('Kolom SO wajib: ' . $h);
    if (count(uniq($heads)) !== count($heads)) fail_('Header SO berulang.');
    $out = [];
    foreach ($data as $r) {
        if (!row_has_value_($r)) continue;
        $o = [];
        foreach ($heads as $i => $h) { $v = $r[$i] ?? null; if (!array_key_exists($i, $r)) { unset($o[$h]); continue; } $o[$h] = $v instanceof DateTimeInterface ? date_fmt_($v, 'yyyy-MM-dd') : $v; }
        $out[] = $o;
    }
    return $out;
}

/** Sync.gs — baca file folder Sync (Blob). → {kind, objects, heads} */
function syncRead_(Blob $file): array {
    $name = $file->getName();
    if (preg_match('/\.csv$/i', $name)) {
        $s = strip_bom_($file->getDataAsString('UTF-8'));
        $data = parseCsv_($s, csv_delim_count_($s));
    } else {
        $data = xlsx_grid_($file->getBytes(), ['Master_Transaksi', 'Master_SO', 'Master_Customer', 'Master_Product'], false, true)['grid'] ?? null;
    }
    if (!$data || count($data) < 2) fail_('File kosong.');
    $heads = array_map('cell_text_', array_shift($data));
    $has = fn($k) => in_array($k, $heads, true);
    $kind = $has('KODE BARANG') && $has('NAMA BARANG') ? 'PRODUCT' : ($has('TANGGAL SO') ? 'SO' : ($has('TANGGAL TRANSAKSI') ? 'ST' : ($has('KODE CUSTOMER') && $has('NIK SALES') ? 'CUSTOMER' : 'ST')));
    $need = ['ST' => ['TANGGAL TRANSAKSI', 'NO TRANSAKSI', 'KODE PRODUK', 'KODE CUSTOMER', 'NIK SALES', 'QTY', 'AMOUNT'], 'SO' => ['KODE CUSTOMER', 'TANGGAL SO', 'KODE PRODUK', 'QTY'], 'CUSTOMER' => ['KODE CUSTOMER', 'NIK SALES'], 'PRODUCT' => ['KODE BARANG', 'NAMA BARANG', 'BRAND']][$kind];
    foreach ($need as $k) if (!$has($k)) fail_('Kolom wajib tidak ada: ' . $k);
    return ['kind' => $kind, 'objects' => values_rows_($heads, $data), 'heads' => $heads];
}

/** DealerImport.gs — file dealer baru (tampilan). */
function dealerReadFile_($file): array {
    if (!blob_ok_($file, 5 * 1024 * 1024)) fail_('Pilih XLSX/CSV maksimal 5 MB.');
    if (preg_match('/\.csv$/i', $file->getName())) {
        $text = strip_bom_($file->getDataAsString('UTF-8'));
        $grid = parseCsv_($text, csv_delim_has_($text));
    } elseif (preg_match('/\.xlsx$/i', $file->getName())) {
        $pick = xlsx_pick_single_($file->getBytes(), ['Template_Dealer', 'Master_Customer']);
        if ($pick === null) fail_('Sheet Template_Dealer tidak ditemukan.');
        $grid = xlsx_grid_($file->getBytes(), [$pick], true)['grid'];
    } else fail_('Gunakan XLSX/CSV.');

    $rawHeads = array_map(fn($h) => preg_replace('/\s+/u', ' ', js_upper(cell_text_($h))), array_shift($grid) ?? []);
    $isNew = in_array('KODE_DEALER_UNIQ', $rawHeads, true) || in_array('KODE_INDUK_DEALER', $rawHeads, true);
    $alias = [
        'KODE_DEALER_UNIQ' => 'ID_DEALER',
        'KODE UNIQ DEALER' => 'ID_DEALER',
        'KODE_UNIQ_CUSTOMER' => 'ID_DEALER',
        'KODE UNIQ CUSTOMER' => 'ID_DEALER',
        'KODE_INDUK_DEALER' => 'KODE CUSTOMER',
        'KODE INDUK DEALER' => 'KODE CUSTOMER',
    ];
    $heads = array_map(fn($h) => $alias[$h] ?? $h, $rawHeads);
    if (count(uniq($heads)) !== count($heads)) fail_('Header dealer berulang setelah normalisasi. Gunakan KODE_DEALER_UNIQ dan KODE_INDUK_DEALER satu kali saja.');

    if ($isNew) {
        foreach (['ID_DEALER', 'KODE CUSTOMER', 'NAMA INDUK CUSTOMER', 'NIK SALES'] as $h) if (!in_array($h, $heads, true)) fail_('Kolom wajib ' . ($h === 'ID_DEALER' ? 'KODE_DEALER_UNIQ' : ($h === 'KODE CUSTOMER' ? 'KODE_INDUK_DEALER' : $h)) . '.');
    } else {
        // Kompatibilitas file lama: KODE CUSTOMER tetap dibaca sebagai kode induk,
        // ID_DEALER/KODE_UNIQ_CUSTOMER sebagai kode toko unik. Bila kode unik kosong,
        // gunakan KODE CUSTOMER sebagai fallback agar template lama satu-toko tetap bisa dipakai.
        foreach (['KODE CUSTOMER', 'NAMA INDUK CUSTOMER', 'NIK SALES'] as $h) if (!in_array($h, $heads, true)) fail_('Kolom wajib ' . $h . '.');
        if (!in_array('ID_DEALER', $heads, true)) $heads[] = 'ID_DEALER';
    }

    $out = [];
    foreach ($grid as $r) {
        if (!row_has_text_($r)) continue;
        $o = row_obj_(array_slice($heads, 0, count($r)), $r);
        // Bila file legacy tidak punya kolom ID_DEALER atau nilainya kosong, pakai kode induk sebagai uniq.
        if (!$isNew && text_($o['ID_DEALER'] ?? null) === '') $o['ID_DEALER'] = text_($o['KODE CUSTOMER'] ?? null);
        $out[] = $o;
    }
    return $out;
}

/** Mobile.gs.gs — file user massal (tampilan, semua nilai teks). */
function userBulkRows_($file): array {
    if (!blob_ok_($file, 5 * 1024 * 1024)) fail_('Pilih XLSX/CSV maksimal 5 MB.');
    if (preg_match('/\.csv$/i', $file->getName())) {
        $s = strip_bom_($file->getDataAsString('UTF-8'));
        $grid = parseCsv_($s, csv_delim_has_($s));
    } elseif (preg_match('/\.xlsx$/i', $file->getName())) {
        $grid = xlsx_grid_($file->getBytes(), ['Template_User'], true, true)['grid'];
    } else fail_('Gunakan XLSX atau CSV.');
    $ALIAS = ['JABATAN' => 'POSISI', 'NIK ATASAN' => 'NIK_ATASAN', 'WILAYAH ADMIN' => 'WILAYAH_ADMIN', 'NIK KARYAWAN' => 'NIK'];
    $heads = array_map(function ($h) use ($ALIAS) { $x = preg_replace('/\s+/u', ' ', js_upper(cell_text_($h))); return $ALIAS[$x] ?? $x; }, array_shift($grid) ?? []);
    foreach (array_slice(USER_BULK_HEADERS, 0, 5) as $h) if (!in_array($h, $heads, true)) fail_('Kolom wajib: ' . $h);
    $out = [];
    foreach ($grid as $r) if (row_has_text_($r)) $out[] = row_obj_($heads, $r, fn($v) => cell_text_($v));
    return $out;
}
