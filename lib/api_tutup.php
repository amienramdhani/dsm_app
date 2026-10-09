<?php
/** DealerTutup.gs — daftar, ajukan, proses, upload massal. */
require_once __DIR__ . '/common.php';

const DT_HEAD_ = ['KODE CUSTOMER', 'TANGGAL TUTUP', 'ALASAN', 'STATUS', 'DIAJUKAN_OLEH', 'DIAJUKAN_AT', 'DIPROSES_OLEH', 'DIPROSES_AT', 'CATATAN'];

/** Dipakai jalur tulis saja: buat sheet bila belum ada. */
function dtSheet_(): string {
    ensureTable_('Dealer_Tutup', DT_HEAD_);
    return 'Dealer_Tutup';
}
function dtRows_(): array {
    $v = readGrid_('Dealer_Tutup'); if ($v === null) return [];
    if (count($v) < 2) return [];
    $h = array_map('text_', array_shift($v));
    $out = [];
    foreach (array_values($v) as $i => $r) {
        $g = function ($k) use ($h, $r) { $j = array_search($k, $h, true); return $j === false ? '' : ($r[$j] ?? ''); };
        $d = $g('TANGGAL TUTUP');
        $x = ['row' => $i + 2, 'id' => text_($g('KODE CUSTOMER')), 'date' => PortalCore::iso($d),
            'alasan' => js_str(js_or($g('ALASAN'), '')), 'status' => js_or(text_($g('STATUS')), 'TUTUP'), 'by' => text_($g('DIAJUKAN_OLEH')), 'at' => js_str(js_or($g('DIAJUKAN_AT'), '')),
            'procBy' => text_($g('DIPROSES_OLEH')), 'procAt' => js_str(js_or($g('DIPROSES_AT'), '')), 'note' => js_str(js_or($g('CATATAN'), ''))];
        $out[] = $x;
    }
    return js_filter($out, fn($x) => truthy($x['id']));
}
/* Peta dealer yang BERLAKU tutup: {id: tanggal}. Dipakai Analisa/Potensi/AI. (cache per request) */
function dtClosed_(): array {
    if (isset($GLOBALS['DSM_DTC'])) return $GLOBALS['DSM_DTC'];
    $out = [];
    try { foreach (dtRows_() as $x) if ($x['status'] === 'TUTUP' && truthy($x['date'])) $out[$x['id']] = $x['date']; } catch (Throwable $e) {}
    return $GLOBALS['DSM_DTC'] = $out;
}
function dtNow_(): string { return jkt_date('Y-m-d H:i'); }
function dtCtx_($token): array {
    $source = snapshot_(); $u = guard_($token, false, false, $source); $meta = $source['meta']; $ids = allowed_($source, $u, 'ALL');
    $set = js_set($ids);
    $admin = $u['role'] === 'ADMIN' || $u['role'] === 'SUPER ADMIN';
    $inScope = function ($id) use ($meta, $u, $set) { $c = $meta['customers'][$id] ?? null; if (!$c) return false; return $u['role'] === 'SUPER ADMIN' || in_set($set, $c['nik'] ?? null); };
    return ['source' => $source, 'u' => $u, 'meta' => $meta, 'ids' => $ids, 'admin' => $admin, 'inScope' => $inScope];
}
function dtFind_(array $meta, $code): string {
    $code = text_($code); if (isset($meta['customers'][$code])) return $code;
    foreach (js_keys($meta['customers']) as $k) if (in_array($code, $meta['customers'][$k]['aliases'] ?? [], true)) return $k;
    return '';
}
function dtName_(array $meta, $nik) {
    $p = js_find($meta['people'], fn($x) => $x['nik'] === $nik);
    return $p ? js_str($p['nama'] ?? null) . ' · ' . js_str($p['role'] ?? null) : $nik;
}

/* Daftar untuk tampilan: semua baris dalam cakupan user + ST 3 bulan terakhir (untuk admin menilai). */
function apiDealerTutupList($token) {
    $c = dtCtx_($token); $meta = $c['meta'];
    $rows = js_filter(dtRows_(), fn($x) => ($c['inScope'])($x['id']));
    $latest = []; foreach ($rows as $x) $latest[$x['id']] = $x['row']; // baris terakhir per dealer = status saat ini
    $list = array_map(function ($x) use ($meta, $latest) {
        $cu = $meta['customers'][$x['id']] ?? [];
        return ['row' => $x['row'], 'id' => $x['id'], 'nama' => js_or($cu['nama'] ?? null, $x['id']), 'sub' => js_or($cu['region'] ?? null, ''), 'kota' => js_or($cu['kota'] ?? null, ''), 'date' => $x['date'], 'alasan' => $x['alasan'], 'status' => $x['status'], 'current' => $latest[$x['id']] === $x['row'],
            'by' => $x['by'], 'byName' => dtName_($meta, $x['by']), 'at' => $x['at'], 'procBy' => $x['procBy'], 'procName' => truthy($x['procBy']) ? dtName_($meta, $x['procBy']) : '', 'procAt' => $x['procAt'], 'note' => $x['note']];
    }, $rows);
    // ST 3 bulan terakhir untuk pengajuan yang menunggu
    $pendIx = []; foreach ($list as $i => $x) if ($x['status'] === 'PENGAJUAN' && $x['current']) $pendIx[] = $i;
    if ($pendIx) {
        try {
            $db = $c['source']; $now = jkt_date('Y-m'); $months = [anaPrev_($now, 2), anaPrev_($now, 1), $now]; $allIds = array_map(fn($p) => $p['nik'], $meta['people']); $want = []; $st = [];
            foreach ($pendIx as $i) { $want[$list[$i]['id']] = 1; $st[$list[$i]['id']] = [0, 0, 0]; }
            foreach ($months as $i => $p) {
                try { $v = coverageView_($db, $p, $allIds, 'ST', $c['u']); } catch (Throwable $e) { continue; }
                if (!isset($v['cubes'][$p])) continue;
                foreach (PortalCore::slice($v, $p, $allIds, null) as $r) if (isset($want[$r['customer']]) && $r['q'] > 0) $st[$r['customer']][$i] += $r['q'];
            }
            foreach ($pendIx as $i) { $list[$i]['st3'] = $st[$list[$i]['id']]; $list[$i]['st3Months'] = $months; }
        } catch (Throwable $e) {}
    }
    return ['admin' => $c['admin'], 'role' => $c['u']['role'], 'rows' => $list];
}

function dtClear_(): void { unset($GLOBALS['DSM_DTC']); }
function dtWrite_($rowNo, array $obj): void {
    dtSheet_();
    $vals = array_map(fn($k) => ($obj[$k] ?? null) === null ? '' : $obj[$k], DT_HEAD_);
    if ($rowNo) { $ids = sheet_rows_with_id_('Dealer_Tutup'); sheet_update_row_('Dealer_Tutup', $ids[$rowNo - 2]['_row'], $vals); }
    else appendRow_('Dealer_Tutup', $vals);
    dtClear_();
}
function dtObj_(array $x): array { return ['KODE CUSTOMER' => $x['id'], 'TANGGAL TUTUP' => $x['date'], 'ALASAN' => $x['alasan'], 'STATUS' => $x['status'], 'DIAJUKAN_OLEH' => $x['by'], 'DIAJUKAN_AT' => $x['at'], 'DIPROSES_OLEH' => $x['procBy'], 'DIPROSES_AT' => $x['procAt'], 'CATATAN' => $x['note']]; }
function dtActive_($id) {
    $l = js_filter(dtRows_(), fn($x) => $x['id'] === $id && ($x['status'] === 'TUTUP' || $x['status'] === 'PENGAJUAN'));
    return $l ? end($l) : null;
}
function apiDealerTutupAjukan($token, $id, $tanggal, $alasan = null) {
    $c = dtCtx_($token); $meta = $c['meta'];
    return with_lock_(function () use ($c, $meta, $id, $tanggal, $alasan) {
        $id = dtFind_($meta, $id); if (!$id || !($c['inScope'])($id)) fail_('Dealer tidak ditemukan dalam cakupan Anda.');
        $d = PortalCore::iso($tanggal); $today = jkt_date('Y-m-d');
        if (!truthy($d)) fail_('Tanggal tutup tidak valid.'); if ($d > $today) fail_('Tanggal tutup tidak boleh di masa depan.');
        $alasan = js_slice(js_trim(js_str(js_or($alasan, ''))), 0, 300); if (mb_strlen($alasan) < 3) fail_('Alasan wajib diisi.');
        $cur = dtActive_($id); if ($cur) fail_($cur['status'] === 'TUTUP' ? 'Dealer ini sudah tercatat tutup.' : 'Dealer ini sudah punya pengajuan yang menunggu admin.');
        $adm = $c['admin'];
        $x = ['id' => $id, 'date' => $d, 'alasan' => $alasan, 'status' => $adm ? 'TUTUP' : 'PENGAJUAN', 'by' => $c['u']['nik'], 'at' => dtNow_(), 'procBy' => $adm ? $c['u']['nik'] : '', 'procAt' => $adm ? dtNow_() : '', 'note' => ''];
        dtWrite_(null, dtObj_($x)); audit_($c['u']['nik'], 'DEALER_TUTUP_' . $x['status'], $id . ' ' . $d . ' ' . $alasan);
        return ['ok' => true, 'status' => $x['status']];
    });
}
function apiDealerTutupProses($token, $row, $aksi, $catatan = null) {
    $c = dtCtx_($token); if (!$c['admin']) fail_('Akses admin diperlukan.');
    return with_lock_(function () use ($c, $row, $aksi, $catatan) {
        $x = js_find(dtRows_(), fn($r) => $r['row'] === (int) $row); if (!$x || !($c['inScope'])($x['id'])) fail_('Data tidak ditemukan.');
        $aksi = strtoupper(js_str(js_or($aksi, ''))); $catatan = js_slice(js_trim(js_str(js_or($catatan, ''))), 0, 300);
        if ($aksi === 'SETUJU') { if ($x['status'] !== 'PENGAJUAN') fail_('Pengajuan sudah diproses.'); $x['status'] = 'TUTUP'; }
        elseif ($aksi === 'TOLAK') { if ($x['status'] !== 'PENGAJUAN') fail_('Pengajuan sudah diproses.'); if (mb_strlen($catatan) < 3) fail_('Alasan penolakan wajib diisi.'); $x['status'] = 'DITOLAK'; }
        elseif ($aksi === 'BUKA') { if ($x['status'] !== 'TUTUP') fail_('Dealer ini tidak berstatus tutup.'); $x['status'] = 'DIBUKA'; }
        else fail_('Aksi tidak dikenal.');
        $x['procBy'] = $c['u']['nik']; $x['procAt'] = dtNow_(); if ($catatan !== '') $x['note'] = $catatan;
        dtWrite_($x['row'], dtObj_($x)); audit_($c['u']['nik'], 'DEALER_TUTUP_' . $aksi, $x['id'] . ($catatan !== '' ? ' | ' . $catatan : ''));
        return ['ok' => true, 'status' => $x['status']];
    });
}
function apiDealerTutupUpload($token, $rows, $commit = false) {
    $c = dtCtx_($token); $meta = $c['meta']; if (!$c['admin']) fail_('Akses admin diperlukan.');
    if (!is_array($rows) || !$rows) fail_('File kosong.'); if (count($rows) > 2000) fail_('Maksimal 2.000 baris per upload.');
    $today = jkt_date('Y-m-d'); $seen = []; $closedNow = [];
    foreach (dtRows_() as $x) if ($x['status'] === 'TUTUP' || $x['status'] === 'PENGAJUAN') $closedNow[$x['id']] = $x['status'];
    $out = [];
    foreach (array_values($rows) as $i => $r) {
        $r = is_array($r) ? array_values($r) : [];
        $code = text_($r[0] ?? null); $id = dtFind_($meta, $code); $d = PortalCore::iso($r[1] ?? null); $alasan = js_slice(js_trim(js_str(js_or($r[2] ?? null, ''))), 0, 300); $err = '';
        if (!$id) $err = 'Kode tidak ada di Master_Customer'; elseif (!($c['inScope'])($id)) $err = 'Di luar cakupan Anda';
        elseif (!truthy($d)) $err = 'Tanggal tidak valid'; elseif ($d > $today) $err = 'Tanggal di masa depan';
        elseif (mb_strlen($alasan) < 3) $err = 'Alasan kosong'; elseif (isset($seen[$id])) $err = 'Kode dobel di file';
        elseif (($closedNow[$id] ?? null) === 'TUTUP') $err = 'Sudah tercatat tutup';
        if ($id) $seen[$id] = 1;
        $out[] = ['line' => $i + 2, 'code' => $code, 'id' => $id, 'nama' => $id ? $meta['customers'][$id]['nama'] : '', 'date' => $d, 'alasan' => $alasan, 'error' => $err, 'replacesPending' => ($closedNow[$id] ?? null) === 'PENGAJUAN'];
    }
    $ok = array_values(array_filter($out, fn($x) => $x['error'] === ''));
    if (!truthy($commit)) return ['rows' => $out, 'ok' => count($ok), 'bad' => count($out) - count($ok)];
    with_lock_(function () use ($ok, $c) {
        $existing = dtRows_();
        foreach ($ok as $x) {
            $pend = js_find($existing, fn($r) => $r['id'] === $x['id'] && $r['status'] === 'PENGAJUAN');
            $obj = ['id' => $x['id'], 'date' => $x['date'], 'alasan' => $x['alasan'], 'status' => 'TUTUP', 'by' => $pend ? $pend['by'] : $c['u']['nik'], 'at' => $pend ? $pend['at'] : dtNow_(), 'procBy' => $c['u']['nik'], 'procAt' => dtNow_(), 'note' => 'Upload massal'];
            if ($pend) dtWrite_($pend['row'], dtObj_($obj)); else dtWrite_(null, dtObj_($obj));
        }
        audit_($c['u']['nik'], 'DEALER_TUTUP_UPLOAD', count($ok) . ' dealer');
    });
    return ['saved' => count($ok), 'skipped' => count($out) - count($ok), 'rows' => $out];
}
