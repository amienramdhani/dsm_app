<?php
/** Edit target: export template Excel (sales sesuai filter + target sekarang) dan import (pratinjau, belum menyimpan). */
require_once __DIR__ . '/bulk.php';
require_once __DIR__ . '/target_number.php';

const TARGET_XL_SHEET = 'Edit_Target';
const TARGET_XL_FIXED = ['NIK', 'NAMA SALES', 'SUB REGION'];

/** Kolom target yang tampil di grid (aturan sama dengan layar Edit target) dengan judul Excel yang unik. */
function targetXlCols_(array $T): array {
    $need = []; foreach ($T['rows'] as $r) foreach ($r['need'] as $k) $need[$k] = 1;
    $lbl = ['QTY' => 'ST', 'DA' => 'DA', 'NOO' => 'NOO', 'IDDLE' => 'Dealer IDDLE', 'OMZET' => 'OMZET'];
    $out = []; $used = [];
    foreach ($T['cols'] as $c) {
        if (!(isset($need[$c['key']]) || (!$need && empty($c['free'])))) continue;
        $h = truthy($c['indicator'] ?? '') ? ($c['label'] . ' (khusus)') : (($lbl[$c['metric']] ?? $c['metric']) . ' ' . $c['brand']);
        $h = preg_replace('/\s+/u', ' ', js_upper($h)); $n = 2; $base = $h;
        while (isset($used[$h])) $h = $base . ' #' . $n++;
        $used[$h] = 1; $out[] = ['head' => $h, 'key' => $c['key'], 'metric' => $c['metric'], 'brand' => $c['brand'], 'indicator' => (string) ($c['indicator'] ?? '')];
    }
    return $out;
}

function apiTargetExport($token, $period = null, $big = null, $sub = null) {
    $T = apiTargetGet($token, $period, $big, $sub);
    if (!$T['rows']) fail_('Tidak ada Sales di filter ini.');
    $cols = targetXlCols_($T); $heads = array_merge(TARGET_XL_FIXED, array_map(fn($c) => $c['head'], $cols));
    $rows = array_map(function ($r) use ($cols) {
        $v = (array) ($r['vals'] ?? []); $o = [$r['nik'], $r['nama'], $r['sub']];
        foreach ($cols as $c) { $x = $v[$c['key']] ?? null; $o[] = ($x === null || $x === '') ? '' : (string) $x; }
        return $o;
    }, $T['rows']);
    $tag = preg_replace('/[^A-Za-z0-9]+/', '-', trim((string) $big) ?: 'SEMUA');
    return workingXlsx_('Target-' . str_replace('-', '', $T['period']) . '-' . $tag . '.xlsx', TARGET_XL_SHEET, $heads, $rows, [
        'Periode: ' . $T['period'] . ' · Big Region: ' . (trim((string) $big) ?: 'Semua') . ($sub ? ' · Sub Region: ' . $sub : ''),
        'Isi atau ubah hanya kolom target. NIK, NAMA SALES, dan SUB REGION adalah acuan (jangan diubah).',
        'Sel KOSONG = tidak diubah. Untuk target nol tulis 0. Angka: 6500, 6.500 (ribuan), atau 6,5 (desimal).',
        'Baris ASM otomatis tidak ada di file ini karena dihitung dari total timnya.'], TARGET_XL_FIXED, [], array_map(fn($c) => $c['head'], $cols), true);
}

/** Baca file lalu bandingkan dengan target sekarang. TIDAK menyimpan: hasilnya dipakai layar untuk mengisi kotak. */
function apiTargetImportCheck($form) {
    $form = is_array($form) ? $form : [];
    $T = apiTargetGet($form['token'] ?? null, $form['period'] ?? null, $form['big'] ?? null, $form['sub'] ?? null);
    $file = $form['file'] ?? null;
    if (!blob_ok_($file, 5 * 1024 * 1024)) fail_('Pilih file XLSX maksimal 5 MB.');
    if (!preg_match('/\.xlsx$/i', $file->getName())) fail_('Gunakan file .xlsx hasil Export template.');
    $g = xlsx_grid_($file->getBytes(), [TARGET_XL_SHEET], true, true); $grid = $g['grid'] ?? [];
    if (!$grid) fail_('File kosong.');
    $heads = array_map(fn($h) => preg_replace('/\s+/u', ' ', js_upper(cell_text_($h))), array_shift($grid));
    $ni = array_search('NIK', $heads, true); if ($ni === false) fail_('Kolom NIK tidak ada. Pakai file hasil Export template.');
    $cols = targetXlCols_($T); $ci = [];
    foreach ($cols as $c) { $i = array_search($c['head'], $heads, true); if ($i !== false) $ci[] = [$i, $c]; }
    if (!$ci) fail_('Tidak ada kolom target yang cocok. Pakai file hasil Export template untuk region dan bulan yang sama.');
    $byNik = []; foreach ($T['rows'] as $r) $byNik[(string) $r['nik']] = $r;
    $changes = []; $rej = []; $seen = []; $read = 0; $same = 0; $line = 1;
    foreach ($grid as $r) {
        $line++; $nik = cell_text_($r[$ni] ?? null); if (!row_has_text_($r) || $nik === '') continue;
        $read++;
        if (isset($seen[$nik])) { $rej[] = ['baris' => $line, 'nik' => $nik, 'nama' => '', 'kolom' => '-', 'alasan' => 'NIK dobel di file']; continue; } $seen[$nik] = 1;
        $p = $byNik[$nik] ?? null;
        if (!$p) { $rej[] = ['baris' => $line, 'nik' => $nik, 'nama' => '', 'kolom' => '-', 'alasan' => 'NIK tidak ada di region/filter ini']; continue; }
        $cur = (array) ($p['vals'] ?? []);
        foreach ($ci as [$i, $c]) {
            $raw = cell_text_($r[$i] ?? null); if (trim($raw) === '') continue;
            $n = targetNumber_($raw);
            if ($n === null) continue;
            if (kn_nan($n)) { $rej[] = ['baris' => $line, 'nik' => $nik, 'nama' => $p['nama'], 'kolom' => $c['head'], 'alasan' => '"' . $raw . '" bukan angka']; continue; }
            $old = $cur[$c['key']] ?? null;
            if ($old !== null && $old !== '' && (float) $old === (float) $n) { $same++; continue; }
            $changes[] = ['nik' => $nik, 'nama' => $p['nama'], 'key' => $c['key'], 'kolom' => $c['head'], 'old' => ($old === null || $old === '') ? '' : (string) $old, 'value' => $n == floor($n) ? (string) (int) $n : rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.')];
        }
    }
    if (!$read) fail_('Tidak ada baris data di file.');
    return ['period' => $T['period'], 'read' => $read, 'same' => $same, 'changes' => $changes, 'rejectedCount' => count($rej), 'rejected' => array_slice($rej, 0, 100)];
}
