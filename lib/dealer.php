<?php
/**
 * DealerEdit.gs — cari & edit dealer, ganti kode induk, hapus dealer, ganti induk massal, export user + PIN.
 * KODE_UNIQ_CUSTOMER (kolom ID_DEALER) = identitas toko; KODE CUSTOMER = kode induk.
 */
require_once __DIR__ . '/api_import.php';
require_once __DIR__ . '/api_tutup.php';

const INDUK_KEYED = [['sheet' => 'Master_Promotor', 'col' => 'ID DEALER'], ['sheet' => 'Master_Grade', 'col' => 'ID DEALER'], ['sheet' => 'Dealer_Tutup', 'col' => 'KODE CUSTOMER'], ['sheet' => 'Dealer_Assignment_History', 'col' => 'KODE CUSTOMER'], ['sheet' => 'Dealer_Brand_Assignment', 'col' => 'KODE CUSTOMER']];

function indukLogAppend_(array $pairs, $actor, $reason): void {
    $g = readGrid_(INDUK_LOG); $heads = ['WAKTU', 'KODE INDUK LAMA', 'KODE INDUK BARU', 'JUMLAH TOKO', 'OLEH', 'ALASAN'];
    $rows = $g && count($g) ? array_slice($g, 1) : []; $now = jkt_date('Y-m-d H:i');
    foreach ($pairs as $p) $rows[] = [$now, $p['lama'], $p['baru'], $p['toko'] ?? '', $actor, $reason];
    putTable_(INDUK_LOG, $heads, $rows);
}
function uniqHeads_(array $heads): array {
    return array_map(function ($h) { $t = preg_replace('/\s+/', '_', js_upper(text_($h))); return $t === UNIQ_LABEL || $t === 'KODE_UNIQ' || $t === 'ID_DEALER' ? UNIQ_COL : text_($h); }, $heads);
}
function uniqObjects_(array $heads, array $objects): array {
    $ren = [];
    foreach ($heads as $h) { $t = preg_replace('/\s+/', '_', js_upper(text_($h))); if (($t === UNIQ_LABEL || $t === 'KODE_UNIQ') && $h !== UNIQ_COL) $ren[$h] = UNIQ_COL; }
    if (!$ren) return $objects;
    return array_map(function ($o) use ($ren) { $x = []; foreach ($o as $k => $v) $x[$ren[$k] ?? $k] = $v; return $x; }, $objects);
}
function uniqOutHeads_(array $heads): array { return array_map(fn($h) => $h === UNIQ_COL ? UNIQ_LABEL : $h, $heads); }

// ===== Helper Master_Customer =====
function mcGrid_(): array {
    $g = readGrid_('Master_Customer'); if (!$g) fail_('Master_Customer tidak ditemukan.');
    $h = array_map('text_', $g[0]); if (!in_array(UNIQ_COL, $h, true)) $h[] = UNIQ_COL;
    $rows = [];
    foreach (array_slice($g, 1) as $r) {
        if (!row_has_value_($r)) continue;
        $o = []; foreach ($h as $i => $k) { $v = $r[$i] ?? null; $o[$k] = $v === null ? '' : $v; }
        $rows[] = $o;
    }
    return ['heads' => $h, 'rows' => $rows, 'raw' => $g];
}
function mcPut_(array $g): void { putTable_('Master_Customer', $g['heads'], array_map(fn($r) => array_map(fn($h) => $r[$h] ?? '', $g['heads']), $g['rows'])); }
function mcUniq_(array $r): string { return text_($r[UNIQ_COL] ?? null) ?: text_($r['KODE CUSTOMER'] ?? null); }
function mcCanEdit_(array $u, array $db, $nik): bool {
    if ($u['role'] === 'SUPER ADMIN') return true;
    $p = null; foreach ($db['meta']['people'] as $x) if ($x['nik'] === $nik) { $p = $x; break; }
    return (bool) ($p && canManageUser_($u, $p));
}
function dealerClosed_(): array { $s = []; try { foreach (dtRows_() as $x) if ($x['status'] === 'TUTUP') $s[$x['id']] = $x['date']; } catch (Throwable $e) {} return $s; }
function findRow_(array $rows, callable $f): ?array { foreach ($rows as $r) if ($f($r)) return $r; return null; }

function indukRewrite_(array &$g, array &$pairs, $db): void {
    $map = []; foreach ($pairs as $p) $map[$p['lama']] = $p['baru'];
    $name = [];
    foreach ($g['rows'] as $r) { $k = text_($r['KODE CUSTOMER'] ?? null); if (!isset($map[$k]) && text_($r['NAMA INDUK CUSTOMER'] ?? null) !== '') $name[$k] = $name[$k] ?? text_($r['NAMA INDUK CUSTOMER']); }
    foreach ($pairs as &$p) $p['toko'] = 0; unset($p);
    foreach ($g['rows'] as &$r) {
        $k = text_($r['KODE CUSTOMER'] ?? null); $to = $map[$k] ?? null; if (!truthy($to)) continue;
        if (text_($r[UNIQ_COL] ?? null) === '') $r[UNIQ_COL] = $k;
        $r['KODE CUSTOMER'] = $to; if (truthy($name[$to] ?? null)) $r['NAMA INDUK CUSTOMER'] = $name[$to];
        foreach ($pairs as &$p) if ($p['lama'] === $k) $p['toko']++; unset($p);
    }
    unset($r);
    indukRewriteOthers_($map);
}
function indukRewriteOthers_(array $map): void {
    foreach (INDUK_KEYED as $c) {
        $s = readGrid_($c['sheet']); if (!$s || count($s) < 2) continue;
        $h = array_map('text_', $s[0]); $i = array_search($c['col'], $h, true); if ($i === false) continue;
        $have = []; $changed = false;
        foreach (array_slice($s, 1) as $r) $have[text_($r[$i] ?? null)] = 1;
        $out = [];
        foreach (array_slice($s, 1) as $r) {
            $k = text_($r[$i] ?? null); $to = $map[$k] ?? null;
            if (!truthy($to)) { $out[] = $r; continue; }
            $changed = true;
            if (($c['sheet'] === 'Master_Promotor' || $c['sheet'] === 'Master_Grade') && isset($have[$to])) continue;
            $r[$i] = $to; $out[] = $r;
        }
        if ($changed) putTable_($c['sheet'], $s[0], $out);
    }
}
function indukCheck_(array $g, $db, $lama, $baru): string {
    $lama = text_($lama); $baru = text_($baru);
    if ($lama === '' || $baru === '') return 'Kode induk lama & baru wajib diisi.';
    if ($lama === $baru) return 'Kode induk baru sama dengan yang lama.';
    if (!preg_match('/^[A-Za-z0-9._\-\/]{1,60}$/', $baru)) return 'Kode induk baru hanya boleh huruf, angka, titik, strip, garis miring (maks 60).';
    $from = js_filter($g['rows'], fn($r) => text_($r['KODE CUSTOMER'] ?? null) === $lama);
    if (!$from) return 'Kode induk ' . $lama . ' tidak ada di Master_Customer.';
    $to = js_filter($g['rows'], fn($r) => text_($r['KODE CUSTOMER'] ?? null) === $baru);
    $n1 = text_($from[0]['NIK SALES'] ?? null);
    if ($to && text_($to[0]['NIK SALES'] ?? null) !== $n1) return 'Induk ' . $baru . ' dipegang NIK ' . text_($to[0]['NIK SALES'] ?? null) . ', induk ' . $lama . ' dipegang ' . $n1 . '. Samakan dulu lewat Transfer dealer.';
    $uq = []; foreach ($g['rows'] as $r) $uq[text_($r[UNIQ_COL] ?? null)] = 1;
    if (!$to && isset($uq[$baru]) && !js_some($from, fn($r) => text_($r[UNIQ_COL] ?? null) === $baru)) return 'Kode ' . $baru . ' sudah dipakai sebagai kode uniq toko lain.';
    return '';
}

// ===== Aktivitas (Activity.gs: pembantu) =====
function actRows_(): array {
    if (!sheet_exists_('Audit_Log')) return [];
    $out = [];
    foreach (sheet_tail_('Audit_Log', 3000) as $r) { $v = $r['values']; $out[] = ['at' => text_($v[0] ?? ''), 'nik' => text_($v[1] ?? ''), 'action' => text_($v[2] ?? ''), 'detail' => text_($v[3] ?? ''), 'row' => $r['_row']]; }
    return array_reverse($out);
}
function actWhen_($iso): string {
    try { $d = new DateTimeImmutable((string) $iso); return $d->setTimezone(jkt_tz_())->format('d/m/Y H:i'); } catch (Throwable $e) { return (string) $iso; }
}

// ===== Cari & detail =====
function apiDealerSearch($token, $q) {
    $db = snapshot_(); $u = adminGuard_($token, $db); $g = mcGrid_(); $closed = dealerClosed_(); $people = [];
    foreach ($db['meta']['people'] as $p) $people[(string) $p['nik']] = $p;
    $q = js_upper(text_($q)); if (mb_strlen($q) < 2) fail_('Ketik minimal 2 huruf.');
    $hit = js_filter($g['rows'], function ($r) use ($q, $u, $db) {
        return mb_strpos(js_upper(mcUniq_($r) . ' ' . text_($r['KODE CUSTOMER'] ?? null) . ' ' . text_($r['NAMA CUSTOMER'] ?? null) . ' ' . text_($r['NAMA INDUK CUSTOMER'] ?? null)), $q) !== false && mcCanEdit_($u, $db, text_($r['NIK SALES'] ?? null));
    });
    $rows = [];
    foreach (array_slice($hit, 0, 60) as $r) {
        $k = text_($r['KODE CUSTOMER'] ?? null); $p = $people[text_($r['NIK SALES'] ?? null)] ?? null;
        $rows[] = ['uniq' => mcUniq_($r), 'induk' => $k, 'nama' => text_($r['NAMA CUSTOMER'] ?? null) ?: text_($r['NAMA INDUK CUSTOMER'] ?? null), 'namaInduk' => text_($r['NAMA INDUK CUSTOMER'] ?? null), 'sales' => $p ? $p['nama'] : text_($r['NIK SALES'] ?? null), 'big' => $p ? ($p['wilayah'] ?? null) : '', 'closed' => !empty($closed[$k])];
    }
    return ['total' => count($hit), 'rows' => $rows];
}
function dealerTxCount_(array $db, $induk, $uniq): array {
    $n = 0; $own = 0;
    foreach (js_keys($db['raw'] ?? []) as $p) { try { foreach (dataRows_((string) $db['raw'][$p]) as $t) { $c = text_($t['customer'] ?? null); if ($c === $uniq) $own++; if ($c === $uniq || custInduk_($db['meta'], $c) === $induk) $n++; } } catch (Throwable $e) {} }
    $so = 0;
    foreach (js_keys($db['so']['raw'] ?? []) as $p) { try { foreach (dataRows_((string) $db['so']['raw'][$p]) as $t) if (text_($t['customer'] ?? null) === $uniq) $so++; } catch (Throwable $e) {} }
    return ['induk' => $n, 'uniq' => $own, 'so' => $so];
}
function apiDealerEditDetail($token, $uniq) {
    $db = snapshot_(); $u = adminGuard_($token, $db); $g = mcGrid_(); $uniq = text_($uniq);
    $r = findRow_($g['rows'], fn($x) => mcUniq_($x) === $uniq); if (!$r) fail_('Dealer ' . $uniq . ' tidak ditemukan.');
    if (!mcCanEdit_($u, $db, text_($r['NIK SALES'] ?? null))) fail_('Dealer di luar wilayah admin Anda.');
    $k = text_($r['KODE CUSTOMER'] ?? null); $p = PortalCore::person($db['meta']['people'], text_($r['NIK SALES'] ?? null)); $closed = dealerClosed_();
    $sib = [];
    foreach ($g['rows'] as $x) if (text_($x['KODE CUSTOMER'] ?? null) === $k) $sib[] = ['uniq' => mcUniq_($x), 'nama' => text_($x['NAMA CUSTOMER'] ?? null) ?: text_($x['NAMA INDUK CUSTOMER'] ?? null), 'namaInduk' => text_($x['NAMA INDUK CUSTOMER'] ?? null)];
    $names = []; foreach ($sib as $x) if ($x['namaInduk'] !== '' && !in_array($x['namaInduk'], $names, true)) $names[] = $x['namaInduk'];
    $hist = [];
    try {
        foreach (actRows_() as $a) {
            if (count($hist) >= 15) break;
            if (preg_match('/^(DEALER_|INDUK_|CUSTOMER_BULK)/', $a['action']) && (strpos($a['detail'], $uniq) !== false || strpos($a['detail'], $k) !== false)) $hist[] = ['when' => actWhen_($a['at']), 'by' => $a['nik'], 'text' => $a['detail']];
        }
    } catch (Throwable $e) {}
    $fields = [];
    foreach ($g['heads'] as $h) if (!in_array($h, [UNIQ_COL, 'KODE CUSTOMER', 'NIK SALES', 'NAMA INDUK CUSTOMER'], true)) $fields[] = ['key' => $h, 'value' => text_($r[$h] ?? null)];
    return ['uniq' => $uniq, 'induk' => $k, 'namaInduk' => text_($r['NAMA INDUK CUSTOMER'] ?? null), 'namaIndukList' => $names, 'nik' => text_($r['NIK SALES'] ?? null), 'sales' => $p ? $p['nama'] : '', 'big' => $p ? ($p['wilayah'] ?? null) : '', 'fields' => $fields, 'siblings' => $sib, 'closed' => js_or($closed[$k] ?? null, ''), 'tx' => dealerTxCount_($db, $k, $uniq), 'history' => $hist, 'superuser' => $u['role'] === 'SUPER ADMIN'];
}
function apiDealerIndukPeek($token, $uniq, $baru) {
    $db = snapshot_(); adminGuard_($token, $db); $g = mcGrid_(); $r = findRow_($g['rows'], fn($x) => mcUniq_($x) === text_($uniq));
    if (!$r) fail_('Dealer tidak ditemukan.');
    $lama = text_($r['KODE CUSTOMER'] ?? null); $baru = text_($baru);
    $err = indukCheck_($g, $db, $lama, $baru); $to = js_filter($g['rows'], fn($x) => text_($x['KODE CUSTOMER'] ?? null) === $baru); $from = js_filter($g['rows'], fn($x) => text_($x['KODE CUSTOMER'] ?? null) === $lama);
    return ['err' => $err, 'lama' => $lama, 'baru' => $baru, 'toko' => array_map('mcUniq_', $from), 'exists' => count($to), 'existsToko' => array_map('mcUniq_', $to), 'namaBaru' => $to ? text_($to[0]['NAMA INDUK CUSTOMER'] ?? null) : ''];
}

/** Snapshot beberapa sheet untuk rollback. */
function sheets_before_(array $names): array { $b = []; foreach ($names as $s) $b[$s] = readGrid_($s); return $b; }
function sheets_restore_(array $before): void {
    foreach ($before as $s => $g) { if ($g) restoreGrid_($s, $g); elseif ($s === INDUK_LOG) deleteSheet_($s); }
}

// ===== Simpan edit =====
function apiDealerSave($token, $uniq, $input, $reason) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function () use ($token, $uniq, $input, $reason) {
        $db = snapshot_(); $u = adminGuard_($token, $db); $g = mcGrid_(); $uniq = text_($uniq); $input = is_array($input) ? $input : [];
        $idx = null; foreach ($g['rows'] as $i => $x) if (mcUniq_($x) === $uniq) { $idx = $i; break; }
        if ($idx === null) fail_('Dealer ' . $uniq . ' tidak ditemukan.');
        if (!mcCanEdit_($u, $db, text_($g['rows'][$idx]['NIK SALES'] ?? null))) fail_('Dealer di luar wilayah admin Anda.');
        $lama = text_($g['rows'][$idx]['KODE CUSTOMER'] ?? null); $log = []; $fields = is_array($input['fields'] ?? null) ? $input['fields'] : [];
        foreach ($fields as $h => $fv) {
            $h = (string) $h;
            if (in_array($h, [UNIQ_COL, 'KODE CUSTOMER', 'NIK SALES', 'NAMA INDUK CUSTOMER'], true) || !in_array($h, $g['heads'], true)) continue;
            $v = text_($fv); if (mb_strlen($v) > 200) fail_($h . ' maksimal 200 karakter.');
            $cur = text_($g['rows'][$idx][$h] ?? null);
            if ($cur !== $v) { $log[] = $h . ': ' . ($cur !== '' ? $cur : '-') . ' → ' . ($v !== '' ? $v : '-'); $g['rows'][$idx][$h] = $v; }
        }
        $ni = array_key_exists('namaInduk', $input) && $input['namaInduk'] !== null ? text_($input['namaInduk']) : null;
        if ($ni !== null) {
            if ($ni === '') fail_('Nama induk tidak boleh kosong.');
            $n = 0; foreach ($g['rows'] as &$x) if (text_($x['KODE CUSTOMER'] ?? null) === $lama && text_($x['NAMA INDUK CUSTOMER'] ?? null) !== $ni) { $x['NAMA INDUK CUSTOMER'] = $ni; $n++; } unset($x);
            if ($n) $log[] = 'nama induk ' . $lama . ' → ' . $ni . ' (' . $n . ' baris)';
        }
        $baru = text_($input['indukBaru'] ?? null); $pairs = [];
        if ($baru !== '' && $baru !== $lama) {
            $e = indukCheck_($g, $db, $lama, $baru); if ($e) fail_($e);
            $pairs = [['lama' => $lama, 'baru' => $baru]];
            $exists = js_some($g['rows'], fn($x) => text_($x['KODE CUSTOMER'] ?? null) === $baru);
            if (!$exists && text_($input['namaIndukBaru'] ?? null) !== '') foreach ($g['rows'] as &$x) if (text_($x['KODE CUSTOMER'] ?? null) === $lama) $x['NAMA INDUK CUSTOMER'] = text_($input['namaIndukBaru']); unset($x);
        }
        if (!$log && !$pairs) return ['ok' => true, 'nothing' => true];
        $sheets = array_merge(['Master_Customer', INDUK_LOG], array_map(fn($c) => $c['sheet'], INDUK_KEYED)); $before = sheets_before_($sheets);
        checkpoint_($u['nik'], 'Edit dealer ' . $uniq . ' · ' . $reason);
        try {
            if ($pairs) { indukRewrite_($g, $pairs, $db); $log[] = 'ganti induk ' . $lama . ' → ' . $baru . ' (' . $pairs[0]['toko'] . ' toko)'; indukLogAppend_($pairs, $u['nik'], $reason); }
            mcPut_($g);
            $meta = meta_(); validateConfigChange_($db, $meta);
            publish_($meta, $db['raw'], $db['coverage'], $u['nik'], 'EDIT DEALER ' . $uniq . ' · ' . $reason);
        } catch (Throwable $err) { sheets_restore_($before); throw $err; }
        audit_($u['nik'], $pairs ? 'INDUK_GANTI' : 'DEALER_EDIT', $uniq . ' (' . ($pairs ? $baru : $lama) . ') · ' . implode('; ', $log) . ' · ' . $reason);
        return ['ok' => true, 'log' => $log];
    });
}

// ===== Hapus dealer =====
function dealerDeleteBlock_(array $db, array $g, $uniq): array {
    $r = findRow_($g['rows'], fn($x) => mcUniq_($x) === $uniq); if (!$r) return ['Dealer ' . $uniq . ' tidak ditemukan.'];
    $k = text_($r['KODE CUSTOMER'] ?? null); $tx = dealerTxCount_($db, $k, $uniq); $b = [];
    if ($tx['uniq']) $b[] = 'Punya ' . $tx['uniq'] . ' baris transaksi ST — pakai Tutup dealer, jangan dihapus.';
    if ($tx['so']) $b[] = 'Punya ' . $tx['so'] . ' baris data SO — pakai Tutup dealer.';
    $last = count(js_filter($g['rows'], fn($x) => text_($x['KODE CUSTOMER'] ?? null) === $k)) === 1;
    if ($last && $k !== $uniq && $tx['induk'] > $tx['uniq']) $b[] = 'Induk ' . $k . ' masih punya transaksi lewat kode lama.';
    return $b;
}
function rejSummary_(array $rejected, string $name, array $heads): array {
    return ['rejected' => count($rejected), 'rejSample' => array_map(fn($x) => ['alasan' => $x['alasan'], 'solusi' => $x['solusi']], array_slice($rejected, 0, 20)),
        'rejFile' => $rejected ? (function () use ($name, $heads, $rejected) { try { return rejectBase64_($name, $heads, $rejected); } catch (Throwable $e) { return null; } })() : null];
}
function apiDealerDelete($token, $uniqs, $reason) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function () use ($token, $uniqs, $reason) {
        $db = snapshot_(); $u = adminGuard_($token, $db); $g = mcGrid_();
        $list = js_filter(array_map('text_', is_array($uniqs) ? $uniqs : [$uniqs]), fn($x) => $x !== ''); $ok = []; $rejected = [];
        if (!$list) fail_('Isi kode uniq.'); if (count($list) > 2000) fail_('Maksimal 2.000 dealer sekali hapus.');
        foreach ($list as $i => $x) {
            $r = findRow_($g['rows'], fn($y) => mcUniq_($y) === $x);
            $b = $r && !mcCanEdit_($u, $db, text_($r['NIK SALES'] ?? null)) ? ['Dealer di luar wilayah admin Anda.'] : dealerDeleteBlock_($db, $g, $x);
            if ($b) { $j = implode(' ', $b); $rejected[] = ['row' => ['KODE_UNIQ_CUSTOMER' => $x], 'alasan' => 'Baris ' . ($i + 2) . ': ' . $j, 'solusi' => preg_match('/transaksi|SO/', $j) ? 'Pakai Tutup dealer (data & histori tetap ada).' : (preg_match('/tidak ditemukan/', $j) ? 'Cek kodenya (0 di depan hilang karena Excel?).' : 'Minta admin wilayah dealer itu.')]; }
            else $ok[] = $x;
        }
        $rs = fn() => rejSummary_($rejected, 'Hapus-Dealer.xlsx', ['KODE_UNIQ_CUSTOMER']);
        if (!$ok) return array_merge(['deleted' => 0], $rs());
        $before = readGrid_('Master_Customer'); $set = js_set($ok);
        checkpoint_($u['nik'], 'Hapus dealer ' . count($ok) . ' · ' . $reason);
        try {
            $g['rows'] = array_values(js_filter($g['rows'], fn($r) => !isset($set[mcUniq_($r)]))); mcPut_($g);
            $meta = meta_(); validateConfigChange_($db, $meta);
            publish_($meta, $db['raw'], $db['coverage'], $u['nik'], 'HAPUS DEALER ' . count($ok) . ' · ' . $reason);
        } catch (Throwable $e) { restoreGrid_('Master_Customer', $before); throw $e; }
        audit_($u['nik'], 'DEALER_HAPUS', implode(', ', array_slice($ok, 0, 50)) . (count($ok) > 50 ? ' …' : '') . ' (' . count($ok) . ') · ' . $reason);
        return array_merge(['deleted' => count($ok)], $rs());
    });
}
function apiDealerDeleteCheck($token, $uniq) { $db = snapshot_(); adminGuard_($token, $db); return ['block' => dealerDeleteBlock_($db, mcGrid_(), text_($uniq))]; }

// ===== Ganti kode induk massal =====
function indukBulkPlan_(array $db, array $g, array $rows): array {
    $ok = []; $rejected = []; $seen = [];
    foreach ($rows as $i => $o) {
        $lama = text_($o['KODE INDUK LAMA'] ?? null); $baru = text_($o['KODE INDUK BARU'] ?? null); $line = $i + 2;
        if ($lama === '' && $baru === '') continue;
        $e = isset($seen[$lama]) ? 'Kode induk lama ' . $lama . ' dobel di file.' : indukCheck_($g, $db, $lama, $baru);
        if (!$e && js_some($ok, fn($x) => $x['baru'] === $lama || $x['lama'] === $baru)) $e = 'Berantai dengan baris lain (' . $lama . ' / ' . $baru . '). Pisah jadi 2 upload.';
        if ($e) { $rejected[] = ['row' => $o, 'alasan' => 'Baris ' . $line . ': ' . $e, 'solusi' => preg_match('/Transfer/', $e) ? 'Transfer dealernya dulu supaya NIK sama.' : (preg_match('/tidak ada/', $e) ? 'Cek kodenya (0 di depan hilang karena Excel?).' : 'Perbaiki baris ini.')]; continue; }
        $seen[$lama] = 1; $ok[] = ['lama' => $lama, 'baru' => $baru, 'toko' => count(js_filter($g['rows'], fn($r) => text_($r['KODE CUSTOMER'] ?? null) === $lama))];
    }
    return ['ok' => $ok, 'rejected' => $rejected];
}
function apiIndukBulkTemplate($token) { adminGuard_($token); return templateXlsx_('Template-Ganti-Kode-Induk.xlsx', 'Ganti_Induk', ['KODE INDUK LAMA', 'KODE INDUK BARU'], [['', '']], ['1 baris = 1 kode induk. Semua toko (kode uniq) di induk lama ikut pindah ke induk baru.', 'Induk baru boleh sudah ada (digabung) asal NIK Sales sama.', 'Transaksi lama tidak diubah; histori, DA & NOO otomatis dihitung ke induk baru.', 'Format kolom sebagai Teks supaya angka 0 di depan tidak hilang.']); }
function apiIndukBulkStage($form) {
    $form = is_array($form) ? $form : []; $db = snapshot_(); $u = adminGuard_($form['token'] ?? null, $db); $parsed = bulkReadRows_($form['file'] ?? null, 'Ganti_Induk');
    foreach (['KODE INDUK LAMA', 'KODE INDUK BARU'] as $h) if (!in_array($h, $parsed['heads'], true)) fail_('Kolom wajib ' . $h . '. Pakai template.');
    if (count($parsed['rows']) > 5000) fail_('Maksimal 5.000 baris.');
    $g = mcGrid_(); $plan = indukBulkPlan_($db, $g, $parsed['rows']);
    if ($u['role'] !== 'SUPER ADMIN') {
        $keep = [];
        foreach ($plan['ok'] as $p) {
            $r = findRow_($g['rows'], fn($x) => text_($x['KODE CUSTOMER'] ?? null) === $p['lama']);
            if (mcCanEdit_($u, $db, text_($r['NIK SALES'] ?? null))) { $keep[] = $p; continue; }
            $plan['rejected'][] = ['row' => ['KODE INDUK LAMA' => $p['lama'], 'KODE INDUK BARU' => $p['baru']], 'alasan' => $p['lama'] . ': di luar wilayah admin Anda.', 'solusi' => 'Minta admin wilayahnya.'];
        }
        $plan['ok'] = $keep;
    }
    $id = uuid_();
    prop_set_('INDUK_BULK_' . $id, writeJson_('induk-bulk-' . $id, ['actor' => $u['nik'], 'created' => now_ms(), 'rows' => $parsed['rows'], 'rejected' => $plan['rejected'], 'heads' => $parsed['heads']]));
    return ['id' => $id, 'total' => count($plan['ok']), 'toko' => array_sum(array_map(fn($p) => $p['toko'], $plan['ok'])), 'sample' => array_slice($plan['ok'], 0, 50), 'rejected' => count($plan['rejected']), 'rejSample' => array_map(fn($x) => ['alasan' => $x['alasan'], 'solusi' => $x['solusi']], array_slice($plan['rejected'], 0, 20))];
}
function apiIndukBulkRejected($token, $id) {
    $u = adminGuard_($token); $ptr = prop_get_('INDUK_BULK_' . js_str($id)); if (!$ptr) fail_('Pratinjau tidak tersedia.');
    $s = readJson_($ptr); if ($s['actor'] !== $u['nik']) fail_('Bukan pratinjau Anda.');
    return rejectBase64_('Ganti-Kode-Induk.xlsx', $s['heads'], $s['rejected']);
}
function apiIndukBulkCommit($token, $id, $reason) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function () use ($token, $id, $reason) {
        $db = snapshot_(); $u = adminGuard_($token, $db); $ptr = prop_get_('INDUK_BULK_' . js_str($id)); if (!$ptr) fail_('Pratinjau tidak tersedia.');
        $s = readJson_($ptr); if ($s['actor'] !== $u['nik'] || now_ms() - $s['created'] > 3600000) fail_('Pratinjau kedaluwarsa. Upload ulang.');
        $g = mcGrid_(); $plan = indukBulkPlan_($db, $g, $s['rows']);
        $plan['ok'] = array_values(js_filter($plan['ok'], function ($p) use ($g, $u, $db) { $r = findRow_($g['rows'], fn($x) => text_($x['KODE CUSTOMER'] ?? null) === $p['lama']); return mcCanEdit_($u, $db, text_($r['NIK SALES'] ?? null)); }));
        if (!$plan['ok']) fail_('Tidak ada baris yang bisa diproses.');
        $sheets = array_merge(['Master_Customer', INDUK_LOG], array_map(fn($c) => $c['sheet'], INDUK_KEYED)); $before = sheets_before_($sheets);
        checkpoint_($u['nik'], 'Ganti kode induk massal ' . count($plan['ok']) . ' · ' . $reason);
        try {
            indukRewrite_($g, $plan['ok'], $db); indukLogAppend_($plan['ok'], $u['nik'], $reason); mcPut_($g);
            $meta = meta_(); validateConfigChange_($db, $meta);
            publish_($meta, $db['raw'], $db['coverage'], $u['nik'], 'GANTI INDUK ' . count($plan['ok']) . ' · ' . $reason);
        } catch (Throwable $e) { sheets_restore_($before); throw $e; }
        audit_($u['nik'], 'INDUK_GANTI', implode(', ', array_map(fn($p) => $p['lama'] . '→' . $p['baru'], array_slice($plan['ok'], 0, 30))) . ' (' . count($plan['ok']) . ') · ' . $reason);
        prop_delete_('INDUK_BULK_' . $id);
        return ['ok' => true, 'count' => count($plan['ok'])];
    });
}

function indukNameConflicts_(): array {
    $g = mcGrid_(); $by = [];
    foreach ($g['rows'] as $r) { $k = text_($r['KODE CUSTOMER'] ?? null); $n = text_($r['NAMA INDUK CUSTOMER'] ?? null); if ($k === '' || $n === '') continue; $by[$k][$n] = ($by[$k][$n] ?? 0) + 1; }
    $out = [];
    foreach (js_keys($by) as $k) if (count($by[$k]) > 1) $out[] = ['induk' => $k, 'names' => array_map('strval', array_keys($by[$k])), 'uniq' => mcUniq_(findRow_($g['rows'], fn($r) => text_($r['KODE CUSTOMER'] ?? null) === (string) $k))];
    return $out;
}

// ===== Export user + PIN sementara =====
function apiUserPinExport($token, $gen = null) {
    $db = snapshot_(); $u = adminGuard_($token, $db);
    $run = function () use ($db, $u, $gen) {
        $g = readGrid_('_Auth') ?: [['NIK', 'SALT', 'PIN_HASH', 'AKTIF', 'VERSI', 'WAJIB_GANTI', 'GAGAL', 'LOCK_UNTIL']]; $h = array_map('text_', $g[0]);
        $ix = function ($k) use ($h) { $i = array_search($k, $h, true); return $i === false ? -1 : $i; };
        $rows = array_slice($g, 1); $byNik = [];
        foreach ($rows as $i => $r) { $k = text_($r[$ix('NIK')] ?? null); if ($k !== '' && !isset($byNik[$k])) $byNik[$k] = $i; }
        $tr = fn($v) => $v === true || in_array(js_upper(js_str($v)), ['TRUE', 'YA', '1'], true);
        $out = []; $made = 0;
        foreach ($db['meta']['people'] as $p) {
            if (!($u['role'] === 'SUPER ADMIN' || canManageUser_($u, $p))) continue;
            $i = $byNik[(string) $p['nik']] ?? null; $r = $i === null ? null : $rows[$i];
            if ($r && !PortalCore::active($r[$ix('AKTIF')] ?? null) && text_($r[$ix('PIN_HASH')] ?? null) !== '') $st = 'NONAKTIF';
            elseif (!$r || text_($r[$ix('PIN_HASH')] ?? null) === '') $st = 'BELUM ADA PIN';
            elseif ($tr($r[$ix('WAJIB_GANTI')] ?? null)) $st = 'SEMENTARA (BELUM LOGIN)';
            else $st = 'SUDAH DIGANTI';
            $pin = '';
            if (truthy($gen) && ($st === 'BELUM ADA PIN' || $st === 'SEMENTARA (BELUM LOGIN)')) {
                $pin = randomPin_(); $salt = uuid_();
                if (!$r) { $r = array_fill(0, count($h), ''); $r[$ix('NIK')] = $p['nik']; $r[$ix('VERSI')] = 0; $r[$ix('GAGAL')] = 0; $r[$ix('LOCK_UNTIL')] = 0; $rows[] = $r; $byNik[(string) $p['nik']] = count($rows) - 1; $i = count($rows) - 1; }
                $r[$ix('SALT')] = $salt; $r[$ix('PIN_HASH')] = hash_($p['nik'] . '|' . $salt . '|' . $pin); $r[$ix('AKTIF')] = true; $r[$ix('VERSI')] = PortalCore::number(js_or($r[$ix('VERSI')] ?? 0, 0)) + 1; $r[$ix('WAJIB_GANTI')] = true; $r[$ix('GAGAL')] = 0; $r[$ix('LOCK_UNTIL')] = 0;
                $rows[$i] = $r; tempPinPut_((string) $p['nik'], $pin, (string) $u['nik']); $st = 'SEMENTARA (BELUM LOGIN)'; $made++;
            }
            $out[(string) $p['nik']] = ['status' => $st, 'pin' => $pin];
        }
        if ($made) {
            $before = readGrid_('_Auth'); checkpoint_($u['nik'], 'Export user + ' . $made . ' PIN sementara');
            try { putTable_('_Auth', $g[0], $rows); } catch (Throwable $e) { if ($before) restoreGrid_('_Auth', $before); throw $e; }
            audit_($u['nik'], 'EXPORT_PIN', $made . ' PIN sementara dibuat untuk user yang belum pernah login');
        }
        return ['users' => js_obj($out), 'made' => $made];
    };
    return truthy($gen) ? with_lock_($run) : $run();
}
