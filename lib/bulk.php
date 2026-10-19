<?php
/**
 * Upload massal: Master Customer (Sync/Upload dealer), Tambah dealer, bulk edit customer, transfer dealer,
 * bulk master (produk, target, KPI, region map, dst), atribut dealer (promotor/grade), Big Region per dealer.
 * Terjemahan 1:1 dari Sync.gs (syncMaster_), DealerUpload.gs, DealerImport.gs, DealerTemplate.gs, BulkAdmin.gs,
 * Promotor.gs, DealerRegion.gs, TargetInput.gs.
 */
require_once __DIR__ . '/dealer.php';

// ------------------------------------------------------------------
// TargetInput.gs
// ------------------------------------------------------------------
function targetInputHeads_(array $db): array {
    $g = readGrid_(TARGET_INPUT);
    if ($g && count($g[0])) return array_values(js_filter(array_map('text_', $g[0]), fn($x) => truthy($x)));
    $brands = anaDeviceBrands_($db['meta']); $heads = TARGET_INPUT_FIXED;
    foreach ($brands as $b) $heads[] = 'ST ' . $b;
    foreach ($brands as $b) $heads[] = 'DA ' . $b;
    putTable_(TARGET_INPUT, $heads, []);
    return $heads;
}
function targetInputTemplate_(array $db): array {
    $heads = targetInputHeads_($db); $period = jkt_date('Y-m');
    $people = js_filter($db['meta']['people'], fn($p) => truthy($p['sales'] ?? null));
    $people = js_sort($people, fn($a, $b) => strcmp((string) js_str($a['nama']), (string) js_str($b['nama'])));
    $existing = [];
    foreach (table_(TARGET_INPUT) as $r) if (text_($r['PERIODE TARGET'] ?? null) === $period) $existing[text_($r['NIK SALES'] ?? null)] = $r;
    $rows = array_map(function ($p) use ($heads, $period, $existing) {
        $old = $existing[(string) $p['nik']] ?? [];
        $r = [];
        foreach ($heads as $i => $h) {
            if ($i === 0) $r[] = $period;
            elseif ($i === 1) $r[] = $p['nik'];
            elseif ($i === 2) $r[] = $p['nama'];
            else $r[] = text_($old[$h] ?? null);
        }
        return $r;
    }, $people);
    return workingXlsx_('Target_Bulanan-' . str_replace('-', '', $period) . '.xlsx', TARGET_INPUT, $heads, $rows, [
        '1 baris = 1 sales untuk 1 periode. File ini sudah berisi target bulan berjalan; edit nilainya lalu upload kembali.',
        'PERIODE TARGET format YYYY-MM. KOSONG = target belum diisi; 0 = memang tidak diwajibkan.',
        'Key upsert: PERIODE TARGET + NIK SALES. NAMA SALES hanya referensi dan diabaikan saat upload.',
        'Kolom target: ST <BRAND>, DA <BRAND>, NOO <BRAND>, IDDLE <BRAND>, OMZET <BRAND>, SO <BRAND> (opsional, untuk KPI Khusus).'], ['PERIODE TARGET','NIK SALES'], ['NAMA SALES']);
}

// ------------------------------------------------------------------
// DealerRegion.gs (bagian yang dipakai transfer dealer)
// ------------------------------------------------------------------
const DEALER_REGION_HEADS = ['KODE CUSTOMER', 'WILAYAH', 'BERLAKU_MULAI', 'CATATAN', 'UPDATED_BY', 'UPDATED_AT'];
function dealerRegionGrid_(): array {
    $g = readGrid_('Dealer_Region');
    if (!$g || !count($g)) return ['heads' => DEALER_REGION_HEADS, 'rows' => []];
    $h = array_map('text_', $g[0]);
    foreach (DEALER_REGION_HEADS as $x) if (!in_array($x, $h, true)) $h[] = $x;
    $rows = [];
    foreach (array_slice($g, 1) as $r) {
        if (!row_has_value_($r)) continue;
        $o = []; foreach ($h as $i => $k) $o[$k] = $r[$i] ?? '';
        $o['BERLAKU_MULAI'] = js_or(PortalCore::iso($o['BERLAKU_MULAI']), text_($o['BERLAKU_MULAI'])); $rows[] = $o;
    }
    return ['heads' => $h, 'rows' => $rows];
}
function dealerRegionWrite_(array $g): void { putTable_('Dealer_Region', $g['heads'], array_map(fn($r) => array_map(fn($h) => $r[$h] ?? '', $g['heads']), $g['rows'])); }
function dealerRegionAppend_(array $list, $actor): int {
    if (!$list) return 0;
    $g = dealerRegionGrid_(); $now = iso_now();
    foreach ($list as $x) {
        $g['rows'] = array_values(js_filter($g['rows'], fn($r) => !(text_($r['KODE CUSTOMER'] ?? null) === $x['dealer'] && ($r['BERLAKU_MULAI'] ?? null) === $x['date'])));
        $g['rows'][] = ['KODE CUSTOMER' => $x['dealer'], 'WILAYAH' => $x['big'], 'BERLAKU_MULAI' => $x['date'], 'CATATAN' => js_or($x['note'] ?? '', ''), 'UPDATED_BY' => $actor, 'UPDATED_AT' => $now];
    }
    dealerRegionWrite_($g); return count($list);
}
function dayAfter_(string $iso, int $d = 1): string { return (new DateTimeImmutable($iso . 'T00:00:00', jkt_tz_()))->modify(($d >= 0 ? '+' : '') . $d . ' day')->format('Y-m-d'); }
function dealerRegionMin_(array $db): string {
    $c = []; foreach (js_values($db['coverage'] ?? []) as $v) if (truthy($v['cutoff'] ?? null)) $c[] = $v['cutoff'];
    $c = js_sort_default($c); if (!$c) return '';
    return dayAfter_(end($c), 1);
}
function drSuper_($token): array { $db = snapshot_(); $u = guard_($token, true, false, $db); if ($u['role'] !== 'SUPER ADMIN') fail_('Khusus Super Admin.'); return ['db' => $db, 'u' => $u]; }
function apiDealerRegionGet($token, $q = null) {
    $c = drSuper_($token); $db = $c['db']; $g = dealerRegionGrid_(); $bigs = js_sort_default($db['meta']['regionList'] ?? []);
    $q = js_upper(text_($q)); $today = jkt_date('Y-m-d'); $rows = [];
    foreach ($g['rows'] as $i => $r) {
        $id = text_($r['KODE CUSTOMER'] ?? null); $cu = $db['meta']['customers'][$id] ?? null;
        $x = ['i' => $i, 'dealer' => $id, 'nama' => $cu ? js_or($cu['nama'] ?? '', '') : '', 'ada' => (bool) $cu, 'big' => text_($r['WILAYAH'] ?? null), 'from' => $r['BERLAKU_MULAI'], 'note' => text_($r['CATATAN'] ?? null), 'by' => text_($r['UPDATED_BY'] ?? null),
            'now' => $cu ? text_(RegionCore::route($db, ['customer' => $id, 'date' => $today, 'nik' => $cu['nik']], 'ST')) : ''];
        if ($q === '' || mb_strpos(js_upper($x['dealer'] . ' ' . $x['nama'] . ' ' . $x['big']), $q) !== false) $rows[] = $x;
    }
    usort($rows, fn($a, $b) => (lc($a['dealer'], $b['dealer']) ?: strcmp((string) $b['from'], (string) $a['from'])));
    return ['rows' => array_slice($rows, 0, 500), 'total' => count($rows), 'bigs' => $bigs, 'minDate' => dealerRegionMin_($db)];
}
function apiDealerRegionLookup($token, $dealer) {
    $c = drSuper_($token); $db = $c['db']; $id = text_($dealer); $cu = $db['meta']['customers'][$id] ?? null;
    if (!$cu) fail_('Dealer ' . $id . ' tidak ditemukan di Master_Customer.');
    $today = jkt_date('Y-m-d'); $p = PortalCore::person($db['meta']['people'], $cu['nik']);
    return ['dealer' => $id, 'nama' => js_or($cu['nama'] ?? '', ''), 'nik' => $cu['nik'], 'sales' => $p ? $p['nama'] : '', 'salesBig' => $p ? text_($p['wilayah'] ?? null) : '', 'now' => text_(RegionCore::route($db, ['customer' => $id, 'date' => $today, 'nik' => $cu['nik']], 'ST'))];
}
function apiDealerRegionSave($token, $op, $reason) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function () use ($token, $op, $reason) {
        $c = drSuper_($token); $db = $c['db']; $g = dealerRegionGrid_(); $before = readGrid_('Dealer_Region'); $msg = '';
        if (is_array($op) && !empty($op['add'])) {
            $a = $op['add']; $id = text_($a['dealer'] ?? null); $big = text_($a['big'] ?? null); $date = PortalCore::iso($a['date'] ?? null);
            if (!isset($db['meta']['customers'][$id])) fail_('Dealer ' . $id . ' tidak ditemukan.');
            $bigs = array_map('js_upper', $db['meta']['regionList'] ?? []);
            if (!in_array(js_upper($big), $bigs, true)) fail_('Big Region ' . $big . ' tidak ada di Region Map.');
            if (!truthy($date)) fail_('Tanggal berlaku wajib (YYYY-MM-DD).');
            $min = dealerRegionMin_($db);
            if ($min !== '' && strcmp($date, $min) < 0) fail_('Tanggal berlaku minimal ' . $min . ' (data sebelum tanggal itu sudah dipublikasi dan tetap di Big Region lama).');
            $cur = text_(RegionCore::route($db, ['customer' => $id, 'date' => $date, 'nik' => $db['meta']['customers'][$id]['nik']], 'ST'));
            if (js_upper($cur) === js_upper($big)) fail_('Dealer ' . $id . ' per ' . $date . ' sudah di ' . $big . '.');
            $g['rows'] = array_values(js_filter($g['rows'], fn($r) => !(text_($r['KODE CUSTOMER'] ?? null) === $id && ($r['BERLAKU_MULAI'] ?? null) === $date)));
            $g['rows'][] = ['KODE CUSTOMER' => $id, 'WILAYAH' => $big, 'BERLAKU_MULAI' => $date, 'CATATAN' => text_($a['note'] ?? null) ?: $reason, 'UPDATED_BY' => $c['u']['nik'], 'UPDATED_AT' => iso_now()];
            $msg = $id . ' → ' . $big . ' mulai ' . $date . ' (sebelumnya ' . ($cur !== '' ? $cur : '-') . ')';
        } else fail_('Aksi tidak valid.');
        checkpoint_($c['u']['nik'], 'Dealer_Region ' . $msg . ' · ' . $reason);
        try {
            dealerRegionWrite_($g);
            $meta = meta_(); validateConfigChange_($db, $meta);
            publish_($meta, $db['raw'], $db['coverage'], $c['u']['nik'], 'DEALER_REGION ' . $msg . ' · ' . $reason);
        } catch (Throwable $e) { if ($before) restoreGrid_('Dealer_Region', $before); throw $e; }
        audit_($c['u']['nik'], 'DEALER_REGION', $msg . ' · ' . $reason);
        return ['ok' => true, 'msg' => $msg];
    });
}

// ------------------------------------------------------------------
// Sync.gs — syncMaster_ (UPSERT Master_Customer / Master_Product, tidak pernah menghapus)
// ------------------------------------------------------------------
/** @return array dry: ringkasan; non-dry: ['rows'=>[...], 'rejected'=>..., 'heads'=>...] */
function syncMaster_(array $rd, $fileName, $dry = false): array {
    $cust = $rd['kind'] === 'CUSTOMER'; $sheet = $cust ? 'Master_Customer' : 'Master_Product'; $keyCol = $cust ? 'KODE CUSTOMER' : 'KODE BARANG';
    if ($cust) { $oh = $rd['heads']; $rd = array_merge($rd, ['heads' => uniqHeads_($oh), 'objects' => uniqObjects_($oh, $rd['objects'])]); }
    $old = snapshot_(); $before = readGrid_($sheet); if (!$before) fail_('Sheet ' . $sheet . ' tidak ditemukan.');
    $heads = array_map('text_', $before[0]); $ki = array_search($keyCol, $heads, true); if ($ki === false) fail_($sheet . ' tidak punya kolom ' . $keyCol . '.');
    $ai = $cust ? array_search('ID_DEALER', $heads, true) : false; $ni = $cust ? array_search('NIK SALES', $heads, true) : false;
    $useAlias = $cust && $ai !== false && in_array('ID_DEALER', $rd['heads'], true);
    $rowKey = function ($r) use ($useAlias, $ai, $ki) { $a = $useAlias ? text_($r[$ai] ?? null) : ''; return $a !== '' ? $a : text_($r[$ki] ?? null); };
    $rows = []; foreach (array_slice($before, 1) as $r) if (row_has_value_($r)) $rows[] = $r;
    $pos = []; $kodeNik = [];
    foreach ($rows as $i => $r) { $pos[$rowKey($r)] = $i; if ($cust) $kodeNik[text_($r[$ki] ?? null)] = text_($r[$ni] ?? null); }
    $mangled = excelMangledMap_(array_map(fn($r) => text_($r[$ki] ?? null), $rows));
    $cols = array_values(js_filter($rd['heads'], fn($h) => in_array($h, $heads, true)));
    $ignored = array_values(js_filter($rd['heads'], fn($h) => truthy($h) && !in_array($h, $heads, true) && !in_array($h, ['ALASAN', 'SOLUSI'], true)));
    $people = []; foreach ($old['meta']['people'] as $p) $people[(string) $p['nik']] = $p;
    $brands = [];
    try { foreach (table_('Product_Rules') as $r) $brands[js_upper(text_($r['BRAND'] ?? null))] = 1; } catch (Throwable $e) {}
    $indukPairs = []; $namaFile = []; $seen = []; $rejected = []; $fileNik = [];
    $sigHeads = array_values(js_filter($rd['heads'], fn($h) => !in_array($h, ['ALASAN', 'SOLUSI'], true)));
    $sig = fn($o) => js_stringify(array_map(fn($h) => text_($o[$h] ?? null), $sigHeads));
    $add = 0; $upd = 0; $same = 0; $dupSame = 0;
    $rej = function ($o, $alasan, $solusi) use (&$rejected) { $rejected[] = ['row' => $o, 'alasan' => $alasan, 'solusi' => $solusi]; };
    foreach ($rd['objects'] as $o) {
        $kode = text_($o[$keyCol] ?? null); $alias = $useAlias ? text_($o['ID_DEALER'] ?? null) : ''; $k = $alias !== '' ? $alias : $kode; $label = $useAlias ? 'ID_DEALER' : $keyCol;
        if ($kode === '') { $rej($o, $keyCol . ' kosong.', 'Isi ' . $keyCol . '.'); continue; }
        if (array_key_exists($k, $seen)) { if ($seen[$k] === $sig($o)) { $dupSame++; continue; } $rej($o, $label . ' ' . $k . ' dobel di file dengan isi berbeda.', 'Sisakan satu baris untuk ' . $label . ' ' . $k . '.'); continue; }
        $seen[$k] = $sig($o);
        if (truthy($mangled[$kode] ?? null) && !array_key_exists($k, $pos)) { $rej($o, $keyCol . ' ' . $kode . ' kemungkinan rusak oleh Excel (angka nol hilang).', 'Harusnya ' . $mangled[$kode] . '. Di Excel format kolom kode sebagai Teks, lalu isi ulang kodenya.'); continue; }
        $has = array_key_exists($k, $pos); $cur = $has ? $rows[$pos[$k]] : array_fill(0, count($heads), '');
        $prevKode = ($cust && $has && $useAlias) ? text_($cur[$ki] ?? null) : '';
        if ($prevKode !== '' && $prevKode !== $kode) {
            if (truthy($indukPairs[$prevKode] ?? null) && $indukPairs[$prevKode] !== $kode) { $rej($o, 'Induk ' . $prevKode . ' diganti ke 2 kode berbeda di file (' . $indukPairs[$prevKode] . ' & ' . $kode . ').', 'Semua toko satu induk harus pindah ke induk yang sama.'); continue; }
            if (truthy($kodeNik[$kode] ?? null) && $kodeNik[$kode] !== ($kodeNik[$prevKode] ?? null)) { $rej($o, 'Ganti induk ' . $prevKode . ' → ' . $kode . ': induk ' . $kode . ' dipegang NIK ' . $kodeNik[$kode] . ', bukan ' . ($kodeNik[$prevKode] ?? 'undefined') . '.', 'Samakan pemegangnya dulu lewat Transfer dealer.'); continue; }
            $indukPairs[$prevKode] = $kode;
        }
        if ($cust) {
            $nik = text_($o['NIK SALES'] ?? null); $oldNik = js_or($kodeNik[$kode] ?? null, js_or($prevKode !== '' ? ($kodeNik[$prevKode] ?? null) : null, '')); $oldNik = js_str($oldNik);
            if (array_key_exists($kode, $fileNik) && $nik !== '' && $fileNik[$kode] !== $nik) { $rej($o, 'KODE CUSTOMER ' . $kode . ' punya NIK SALES berbeda di file (' . $fileNik[$kode] . ' vs ' . $nik . ').', 'Satu dealer = satu pemegang. Samakan NIK SALES semua baris dealer ini.'); continue; }
            if ($oldNik !== '' && $nik !== '' && $nik !== $oldNik) { $rej($o, 'NIK SALES beda dari pemegang sekarang (' . $oldNik . ' → ' . $nik . ').', 'Pindah pemegang pakai Admin › Dealer › Transfer dealer. Di file ini kosongkan/samakan NIK SALES.'); continue; }
            if ($oldNik === '' && ($nik === '' || !isset($people[$nik]) || !truthy($people[$nik]['sales'] ?? null))) { $rej($o, 'NIK SALES ' . ($nik !== '' ? $nik : '(kosong)') . ' belum terdaftar sebagai sales aktif.', 'Daftarkan NIK lewat Admin › User › Tambah user massal (POSISI SALES), lalu upload ulang.'); continue; }
            if ($nik !== '') $fileNik[$kode] = $nik;
            if (!$has && $nik === '') $o['NIK SALES'] = $oldNik;
        } else {
            $b = js_upper(text_($o['BRAND'] ?? null));
            if ($b !== '' && !isset($brands[$b])) { $rej($o, 'BRAND "' . js_str($o['BRAND'] ?? null) . '" belum ada di Product_Rules.', 'Tambah brand di Admin › Master › Product Rules, lalu upload ulang.'); continue; }
            if (!$has && $b === '') { $rej($o, 'Produk baru tanpa BRAND.', 'Isi kolom BRAND.'); continue; }
        }
        $changed = false;
        foreach ($cols as $h) {
            $v = $o[$h] ?? null; if ($v === '' || $v === null) continue;
            $j = array_search($h, $heads, true); $nv = text_($v);
            if (text_($cur[$j] ?? null) !== $nv) { $cur[$j] = $nv; $changed = true; }
        }
        if ($cust && text_($o['NAMA INDUK CUSTOMER'] ?? null) !== '') $namaFile[$kode] = text_($o['NAMA INDUK CUSTOMER']);
        if (!$has) { $rows[] = $cur; $pos[$k] = count($rows) - 1; $add++; } else { $rows[$pos[$k]] = $cur; if ($changed) $upd++; else $same++; }
    }
    $indukMoved = 0; $namaFix = 0; $pairList = [];
    foreach (js_keys($indukPairs) as $l) $pairList[] = ['lama' => (string) $l, 'baru' => $indukPairs[$l]];
    if ($cust) {
        $nii = array_search('NAMA INDUK CUSTOMER', $heads, true);
        foreach ($rows as &$r) {
            $kk = text_($r[$ki] ?? null); $to = $indukPairs[$kk] ?? null;
            if (truthy($to)) { if ($ai !== false && text_($r[$ai] ?? null) === '') $r[$ai] = $kk; $r[$ki] = $to; $indukMoved++; }
        }
        unset($r);
        if ($nii !== false) {
            $nm = []; foreach ($rows as $r) { $kk = text_($r[$ki] ?? null); if (truthy($namaFile[$kk] ?? null)) $nm[$kk] = $namaFile[$kk]; }
            foreach ($rows as &$r) { $kk = text_($r[$ki] ?? null); if (truthy($nm[$kk] ?? null) && text_($r[$nii] ?? null) !== $nm[$kk]) { $r[$nii] = $nm[$kk]; $namaFix++; } }
            unset($r);
        }
        if ($indukMoved || $namaFix) $upd = $upd ?: 1;
    }
    $hint = '';
    if ($cust && $rejected) {
        $miss = [];
        foreach ($rejected as $x) if (preg_match('/NIK SALES (\S+) belum terdaftar/', $x['alasan'], $m)) $miss[$m[1]] = ($miss[$m[1]] ?? 0) + 1;
        $ks = js_keys($miss);
        if ($ks) $hint = ' | NIK belum terdaftar (' . count($ks) . '): ' . implode(', ', array_map(fn($k) => $k . ' (' . $miss[$k] . ')', array_slice($ks, 0, 30))) . (count($ks) > 30 ? ', dst.' : '');
    }
    $note = ($pairList ? 'GANTI INDUK ' . count($pairList) . ' (' . implode(', ', array_map(fn($p) => $p['lama'] . '→' . $p['baru'], array_slice($pairList, 0, 10))) . (count($pairList) > 10 ? ', dst.' : '') . '), ' : '')
        . ($namaFix ? 'nama induk diseragamkan ' . $namaFix . ' baris, ' : '') . 'baru ' . $add . ', diperbarui ' . $upd . ', sama ' . $same . ($dupSame ? ', kembar dilewati ' . $dupSame : '')
        . ($rejected ? ', DITOLAK ' . count($rejected) . ' (lihat file DITOLAK)' : '') . $hint . ($ignored ? ' | kolom diabaikan: ' . implode(', ', $ignored) : '');
    if ($dry) return ['add' => $add, 'upd' => $upd, 'same' => $same, 'dupSame' => $dupSame, 'rejected' => $rejected, 'note' => $note, 'total' => count($rd['objects']), 'ignored' => $ignored, 'induk' => $pairList, 'namaFix' => $namaFix];
    $out = ['rows' => [['kind' => $rd['kind'], 'period' => '-', 'region' => '-', 'rows' => count($rd['objects']), 'qty' => '', 'note' => $note]], 'rejected' => $rejected, 'heads' => $rd['heads']];
    if (!$add && !$upd) { $out['rows'][0]['note'] = $note . ' | tidak ada perubahan'; return $out; }
    checkpoint_('SYNC', 'Sync ' . $rd['kind'] . ' ' . $fileName);
    $others = [];
    if ($pairList) { foreach (array_merge(array_map(fn($c) => $c['sheet'], INDUK_KEYED), [INDUK_LOG]) as $s) $others[$s] = readGrid_($s); }
    try {
        putTable_($sheet, $heads, $rows);
        if ($pairList) { $mp = []; foreach ($pairList as $p) $mp[$p['lama']] = $p['baru']; indukRewriteOthers_($mp); indukLogAppend_($pairList, 'SYNC', 'Upload dealer ' . $fileName); }
        $meta = meta_(); validateConfigChange_($old, $meta);
        publish_($meta, $old['raw'], $old['coverage'], 'SYNC', 'Sync ' . $rd['kind'] . ' (' . $fileName . ') ' . $note, null, $old['parts'] ?? null);
    } catch (Throwable $e) {
        restoreGrid_($sheet, $before);
        foreach ($others as $x => $g) { if ($g) restoreGrid_($x, $g); elseif ($x === INDUK_LOG) deleteSheet_($x); }
        throw $e;
    }
    return $out;
}

// ------------------------------------------------------------------
// DealerUpload.gs
// ------------------------------------------------------------------
const DEALER_UPLOAD_MAX = 20000;
function dealerUploadRead_(array $form): array {
    $parsed = bulkReadRows_($form['file'] ?? null, 'Master_Customer'); $heads = array_map('text_', $parsed['heads']);
    if (!in_array('KODE CUSTOMER', $heads, true)) fail_('Kolom wajib KODE CUSTOMER tidak ada. Pakai template / hasil Download data dealer.');
    if (count($parsed['rows']) > DEALER_UPLOAD_MAX) fail_('Maksimal ' . number_format(DEALER_UPLOAD_MAX, 0, ',', '.') . ' baris per file. Pecah jadi beberapa file.');
    if (!$parsed['rows']) fail_('File kosong.');
    return ['kind' => 'CUSTOMER', 'heads' => $heads, 'objects' => $parsed['rows']];
}
function apiDealerUploadStage($form) {
    $form = is_array($form) ? $form : []; $db = snapshot_(); $u = guard_($form['token'] ?? null, true, false, $db);
    $rd = dealerUploadRead_($form); $name = text_(blob_name($form['file'] ?? null) ?: 'upload.xlsx');
    $r = syncMaster_($rd, $name, true); $id = uuid_();
    prop_set_('DEALER_UP_' . $id, writeJson_('dealer-up-' . $id, ['actor' => $u['nik'], 'base' => $db['id'], 'created' => now_ms(), 'name' => $name, 'rd' => $rd]));
    return ['id' => $id, 'name' => $name, 'total' => $r['total'], 'add' => $r['add'], 'upd' => $r['upd'], 'same' => $r['same'], 'dupSame' => $r['dupSame'], 'rejected' => count($r['rejected']), 'ignored' => $r['ignored'] ?? [], 'induk' => $r['induk'] ?? [], 'namaFix' => $r['namaFix'] ?? 0,
        'sample' => array_map(fn($x) => ['kode' => text_($x['row']['KODE CUSTOMER'] ?? null), 'id' => text_($x['row']['ID_DEALER'] ?? null), 'alasan' => $x['alasan'], 'solusi' => $x['solusi']], array_slice($r['rejected'], 0, 50))];
}
function dealerUploadLoad_($token, $id): array {
    $db = snapshot_(); $u = guard_($token, true, false, $db); $ptr = prop_get_('DEALER_UP_' . js_str($id));
    if (!$ptr) fail_('Pratinjau tidak tersedia / sudah dipakai. Periksa file lagi.');
    $s = readJson_($ptr); if ($s['actor'] !== $u['nik'] || now_ms() - $s['created'] > 3600000) fail_('Pratinjau kedaluwarsa. Periksa file lagi.');
    return ['s' => $s, 'u' => $u, 'db' => $db];
}
function apiDealerUploadRejected($token, $id) {
    $L = dealerUploadLoad_($token, $id); $r = syncMaster_($L['s']['rd'], $L['s']['name'], true);
    if (!$r['rejected']) fail_('Tidak ada baris ditolak.');
    return rejectBase64_($L['s']['name'], $L['s']['rd']['heads'], $r['rejected']);
}
function apiDealerUploadCommit($token, $id, $reason = null) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function () use ($token, $id, $reason) {
        $L = dealerUploadLoad_($token, $id); $out = syncMaster_($L['s']['rd'], $L['s']['name']);
        audit_($L['u']['nik'], 'DEALER_UPLOAD', $L['s']['name'] . ' · ' . $out['rows'][0]['note'] . ' · ' . $reason);
        prop_delete_('DEALER_UP_' . $id);
        return ['note' => $out['rows'][0]['note'], 'rejected' => count($out['rejected'] ?? [])];
    });
}
function apiDealerDownloadAll($token) {
    $db = snapshot_(); $u = guard_($token, true, false, $db); $g = readGrid_('Master_Customer'); if (!$g) fail_('Master_Customer tidak ditemukan.');
    $heads = array_map('text_', $g[0]); $ni = array_search('NIK SALES', $heads, true);
    $rows = array_values(js_filter(array_slice($g, 1), fn($r) => row_has_value_($r)));
    if ($u['role'] !== 'SUPER ADMIN') { $ok = []; foreach ($db['meta']['people'] as $p) if (canManageUser_($u, $p)) $ok[(string) $p['nik']] = 1; $rows = array_values(js_filter($rows, fn($r) => isset($ok[text_($r[$ni] ?? null)]))); }
    $rows = array_map(fn($r) => array_map(fn($v) => (string) ($v ?? ''), $r), $rows);
    [$outHeads,$outRows] = addRefAfter_(uniqOutHeads_($heads),$rows,'NIK SALES','NAMA SALES',fn($r,$h)=>personNameByNik_($db,text_($r[array_search('NIK SALES',$h,true)]??'')));
    return workingXlsx_('Data-Dealer-' . jkt_date('Ymd') . '.xlsx', 'Master_Customer', $outHeads, $outRows,
        ['1 baris = 1 toko. KODE CUSTOMER adalah kode induk untuk tabel & hitung DA.', 'NIK SALES jangan diubah di sini — pindah pemegang pakai Transfer Dealer.', 'NAMA SALES hanya referensi dan diabaikan saat upload.'], ['KODE CUSTOMER','NIK SALES'], ['NAMA SALES']);
}

// ------------------------------------------------------------------
// DealerImport.gs / DealerTemplate.gs
// ------------------------------------------------------------------
function validateDealers_($rows, array $db, array $u): array {
    if (!is_array($rows) || !$rows || count($rows) > 500) fail_('Isi 1–500 dealer per unggahan.');
    // KODE CUSTOMER = kode induk (boleh berulang). ID_DEALER = kode toko unik (wajib unik).
    $parents = [];
    $usedUniq = [];
    foreach ($db['meta']['customers'] as $parent => $c) {
        $parents[(string) $parent] = $c;
        $usedUniq[(string) $parent] = 1; // cegah kode uniq baru bertabrakan dengan kode induk existing.
        foreach ($c['aliases'] ?? [] as $a) if (truthy($a)) $usedUniq[(string) $a] = 1;
    }
    $out = [];
    foreach (array_values($rows) as $i => $r) {
        $clean = []; foreach (DEALER_HEADERS as $k) $clean[$k] = text_($r[$k] ?? null);
        $parent = $clean['KODE CUSTOMER']; $uniq = $clean['ID_DEALER']; $nik = $clean['NIK SALES']; $p = PortalCore::person($db['meta']['people'], $nik);
        $err = function ($m) use ($i) { fail_('Baris ' . ($i + 2) . ': ' . $m); };
        if (!preg_match('/^[A-Za-z0-9._-]{1,60}$/', $uniq)) $err('KODE_DEALER_UNIQ tidak valid.');
        if (!preg_match('/^[A-Za-z0-9._-]{1,60}$/', $parent)) $err('KODE_INDUK_DEALER tidak valid.');
        if ($clean['NAMA INDUK CUSTOMER'] === '' || mb_strlen($clean['NAMA INDUK CUSTOMER']) > 150) $err('NAMA INDUK CUSTOMER tidak valid.');
        if (isset($usedUniq[$uniq])) $err('KODE_DEALER_UNIQ sudah terdaftar/berulang: ' . $uniq);
        if (!$p || !truthy($p['sales'] ?? null) || !canManageUser_($u, $p)) $err('NIK harus Sales atau atasan rangkap dalam wilayah tugas.');
        if (isset($parents[$parent]) && text_($parents[$parent]['nik'] ?? null) !== '' && text_($parents[$parent]['nik'] ?? null) !== $nik) {
            $err('KODE_INDUK_DEALER ' . $parent . ' sudah ada dengan NIK SALES berbeda. Gunakan NIK pemegang induk yang sama atau Transfer Dealer setelah dealer dibuat.');
        }
        foreach ($clean as $v) if (mb_strlen($v) > 500) $err('teks maksimal 500 karakter.');
        $usedUniq[$uniq] = 1;
        if (!isset($parents[$parent])) $parents[$parent] = ['nik' => $nik];
        $out[] = array_merge($clean, ['NAMA CUSTOMER' => $clean['NAMA INDUK CUSTOMER'], 'REGION' => js_or($p['portfolio'] ?? null, $p['region'] ?? null), 'SALES' => $p['nama']]);
    }
    return $out;
}
function apiDealerTemplate($token) {
    adminGuard_($token);
    return templateXlsx_('Template-Dealer-Baru.xlsx', 'Template_Dealer', DEALER_TEMPLATE_HEADERS, [], [
        'KODE_DEALER_UNIQ = kode toko/dealer unik (disimpan sebagai ID_DEALER).',
        'KODE_INDUK_DEALER = kode induk/customer (disimpan sebagai KODE CUSTOMER) dan boleh sama untuk beberapa toko.',
        'Simpan sebagai XLSX, lalu Admin > Tambah dealer > Periksa upload dealer.'
    ]);
}
function apiStageDealers($form) {
    $form = is_array($form) ? $form : []; $db = snapshot_(); $u = adminGuard_($form['token'] ?? null, $db);
    $rows = validateDealers_(dealerReadFile_($form['file'] ?? null), $db, $u); $id = uuid_();
    $s = ['actor' => $u['nik'], 'base' => $db['id'], 'config' => configHash_(meta_()), 'created' => now_ms(), 'rows' => $rows];
    prop_set_('DEALER_STAGE_' . $id, writeJson_('dealer-preview-' . $id, $s));
    return ['id' => $id, 'total' => count($rows), 'rows' => array_map(fn($r) => ['id' => $r['ID_DEALER'], 'nama' => $r['NAMA INDUK CUSTOMER'], 'nik' => $r['NIK SALES'], 'sales' => $r['SALES'], 'region' => $r['REGION']], $rows)];
}
function appendDealers_(array $rows, array $db, array $u): array {
    $before = readGrid_('Master_Customer'); if (!$before) fail_('Master_Customer tidak ditemukan.');
    $heads = $before[0];
    foreach (array_merge(DEALER_HEADERS, ['NAMA CUSTOMER', 'REGION', 'SALES']) as $h) if (!in_array($h, $heads, true)) $heads[] = $h;
    checkpoint_($u['nik'], 'Tambah ' . count($rows) . ' dealer');
    try {
        $data = [];
        foreach (array_slice($before, 1) as $r) { $line = []; foreach ($heads as $i => $h) $line[] = $r[$i] ?? ''; $data[] = $line; }
        foreach ($rows as $r) { $line = []; foreach ($heads as $h) $line[] = $r[$h] ?? ''; $data[] = $line; }
        putTable_('Master_Customer', $heads, $data);
        $meta = meta_(); validateConfigChange_($db, $meta);
        publish_($meta, $db['raw'], $db['coverage'], $u['nik'], 'Tambah ' . count($rows) . ' dealer');
        return ['total' => count($rows)];
    } catch (Throwable $e) { restoreGrid_('Master_Customer', $before); throw $e; }
}
function apiCommitDealers($token, $id) {
    return with_lock_(function () use ($token, $id) {
        $db = snapshot_(); $u = adminGuard_($token, $db); $ptr = prop_get_('DEALER_STAGE_' . js_str($id));
        if (!$ptr) fail_('Pratinjau tidak ditemukan.');
        $s = readJson_($ptr);
        if ($s['actor'] !== $u['nik'] || $s['base'] !== $db['id'] || $s['config'] !== configHash_(meta_()) || now_ms() - $s['created'] > 3600000) fail_('Data berubah/pratinjau kedaluwarsa; periksa file lagi.');
        $r = appendDealers_(validateDealers_($s['rows'], $db, $u), $db, $u);
        prop_delete_('DEALER_STAGE_' . $id);
        return $r;
    });
}
function apiAddDealer($token, $input) {
    return with_lock_(function () use ($token, $input) {
        $db = snapshot_(); $u = adminGuard_($token, $db);
        return appendDealers_(validateDealers_([$input], $db, $u), $db, $u);
    });
}

// ------------------------------------------------------------------
// BulkAdmin.gs
// ------------------------------------------------------------------
const CUSTOMER_BULK_KEY = 'KODE CUSTOMER';
const CUSTOMER_BULK_LOCKED = ['KODE CUSTOMER', 'NIK SALES'];
const TRANSFER_HEADERS = ['KODE CUSTOMER', 'NAMA CUSTOMER', 'BRAND', 'LOKASI DEALER', 'BIG REGION PIC', 'REGION PIC', 'NIK SALES SAAT INI', 'NAMA SALES SAAT INI', 'NEW_SALES_NIK', 'EFFECTIVE_DATE', 'NOTE'];
const ASSIGNMENT_HEADERS = ['KODE CUSTOMER', 'NIK SALES', 'BERLAKU_MULAI', 'BERLAKU_SAMPAI', 'SUMBER', 'NOTE', 'UPDATED_BY', 'UPDATED_AT'];
const MASTER_BULK = [
    'PRODUCT' => ['sheet' => 'Master_Product', 'keys' => ['KODE BARANG'], 'label' => 'Master Product'],
    'TARGET' => ['sheet' => 'Target_Input', 'keys' => ['PERIODE TARGET', 'NIK SALES'], 'label' => 'Target Bulanan per Sales'],
    'TARGET_KHUSUS' => ['sheet' => 'Target_Periode', 'keys' => ['PERIODE', 'NIK', 'BRAND', 'METRIK', 'INDIKATOR_ID'], 'label' => 'Target Khusus KPI'],
    'KPI' => ['sheet' => 'KPI_Config', 'keys' => ['PERIODE', 'WILAYAH_KPI', 'KELOMPOK_KPI', 'METRIK', 'BRAND', 'INDIKATOR_ID'], 'label' => 'KPI Config'],
    'PRODUCT_RULES' => ['sheet' => 'Product_Rules', 'keys' => ['BRAND'], 'label' => 'Product Rules'],
    'REGION_MAP' => ['sheet' => 'Region_Map', 'keys' => ['REGION'], 'label' => 'Region Map'],
    'DOS' => ['sheet' => 'DOS_Config', 'keys' => ['WILAYAH'], 'label' => 'DOS Config'],
    'PROMOTOR' => ['sheet' => 'Master_Promotor', 'keys' => ['ID DEALER'], 'label' => 'Master Promotor'],
    'GRADE' => ['sheet' => 'Master_Grade', 'keys' => ['ID DEALER'], 'label' => 'Master Grade'],
    'ORG' => ['sheet' => 'Master_Sales', 'keys' => ['NIK KARYAWAN', 'POSISI'], 'label' => 'Struktur Organisasi / Master Sales', 'heads' => ['NIK KARYAWAN', 'POSISI', 'REGION', 'NIK ATASAN'], 'existingOnly' => true],
];


function addRefAfter_(array $heads, array $rows, string $after, string $newHead, callable $valueFn): array {
    $idx = array_search($after, $heads, true); if ($idx === false) return [$heads, $rows];
    $outHeads = $heads; array_splice($outHeads, $idx + 1, 0, [$newHead]); $outRows = [];
    foreach ($rows as $r) { $r = array_values($r); $v = $valueFn($r, $heads); array_splice($r, $idx + 1, 0, [$v]); $outRows[] = $r; }
    return [$outHeads, $outRows];
}
function personNameByNik_(array $db, string $nik): string { $p = PortalCore::person($db['meta']['people'], $nik); return $p ? text_($p['nama'] ?? null) : ''; }

function apiCustomerBulkExport($token) {
    $db = snapshot_(); $u = adminGuard_($token, $db); $grid = readGrid_('Master_Customer'); if (!$grid) fail_('Master_Customer tidak ditemukan.');
    $heads = array_map('text_', $grid[0]); $idx = array_search('NIK SALES', $heads, true); $rows = [];
    foreach (array_slice($grid, 1) as $r) {
        if (!row_has_value_($r)) continue;
        if ($u['role'] !== 'SUPER ADMIN') { $p = PortalCore::person($db['meta']['people'], text_($r[$idx] ?? null)); if (!($p && canManageUser_($u, $p))) continue; }
        $rows[] = array_map(fn($h, $i) => (string) ($r[$i] ?? ''), $heads, array_keys($heads));
    }
    [$outHeads,$outRows] = addRefAfter_($heads,$rows,'NIK SALES','NAMA SALES',fn($r,$h)=>personNameByNik_($db,text_($r[array_search('NIK SALES',$h,true)]??'')));
    return workingXlsx_('Bulk-Edit-Customer-' . jkt_date('Ymd') . '.xlsx', 'Master_Customer', $outHeads, $outRows,
        ['KODE CUSTOMER adalah key dan tidak boleh diubah.', 'NIK SALES dan NAMA SALES tidak dapat diubah dari bulk edit customer; gunakan Transfer Dealer.', 'Upload hanya baris yang ingin diperbarui juga diperbolehkan.'], ['KODE CUSTOMER','NIK SALES'], ['NAMA SALES']);
}
function validateCustomerBulk_(array $rows, array $db, array $u): array {
    if (!$rows || count($rows) > 5000) fail_('Isi 1–5.000 customer per upload.');
    $by = []; foreach (table_('Master_Customer') as $r) $by[text_($r[CUSTOMER_BULK_KEY] ?? null)] = $r;
    $heads = array_map('text_', readGrid_('Master_Customer')[0]); $allowed = array_values(js_filter($heads, fn($h) => !in_array($h, CUSTOMER_BULK_LOCKED, true)));
    $out = [];
    foreach (array_values($rows) as $i => $row) {
        $id = text_($row[CUSTOMER_BULK_KEY] ?? null); $old = $by[$id] ?? null;
        if ($id === '' || !$old) fail_('Baris ' . ($i + 2) . ': KODE CUSTOMER tidak ditemukan: ' . $id);
        $owner = PortalCore::person($db['meta']['people'], text_($old['NIK SALES'] ?? null));
        if ($u['role'] !== 'SUPER ADMIN' && (!$owner || !canManageUser_($u, $owner))) fail_('Baris ' . ($i + 2) . ': dealer di luar kewenangan.');
        if (text_($row['NIK SALES'] ?? null) !== '' && text_($row['NIK SALES'] ?? null) !== text_($old['NIK SALES'] ?? null)) fail_('Baris ' . ($i + 2) . ': NIK SALES tidak boleh diubah dari bulk customer. Gunakan Transfer Dealer.');
        $after = $old; $changes = [];
        foreach ($allowed as $h) {
            if (!array_key_exists($h, $row)) continue;
            $next = text_($row[$h]); $prev = text_($old[$h] ?? null);
            if ($next !== $prev) { $after[$h] = $next; $changes[] = ['field' => $h, 'before' => $prev, 'after' => $next]; }
        }
        $out[] = ['id' => $id, 'nama' => js_or(text_($old['NAMA INDUK CUSTOMER'] ?? null), text_($old['NAMA CUSTOMER'] ?? null)), 'before' => $old, 'after' => $after, 'changes' => $changes, 'changed' => (bool) $changes];
    }
    return $out;
}
function apiStageCustomerBulk($form) {
    $form = is_array($form) ? $form : []; $db = snapshot_(); $u = adminGuard_($form['token'] ?? null, $db); $parsed = bulkReadRows_($form['file'] ?? null, 'Master_Customer');
    if (!in_array(CUSTOMER_BULK_KEY, $parsed['heads'], true)) fail_('Kolom wajib KODE CUSTOMER.');
    $preview = validateCustomerBulk_($parsed['rows'], $db, $u); $id = uuid_();
    prop_set_('CUSTOMER_BULK_' . $id, writeJson_('customer-bulk-' . $id, ['actor' => $u['nik'], 'base' => $db['id'], 'created' => now_ms(), 'rows' => $parsed['rows']]));
    return ['id' => $id, 'total' => count($preview), 'changed' => count(js_filter($preview, fn($x) => $x['changed'])), 'rows' => array_map(fn($x) => ['id' => $x['id'], 'nama' => $x['nama'], 'changes' => $x['changes']], $preview)];
}
function apiCommitCustomerBulk($token, $id, $reason = null) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function () use ($token, $id, $reason) {
        $db = snapshot_(); $u = adminGuard_($token, $db); $ptr = prop_get_('CUSTOMER_BULK_' . js_str($id)); if (!$ptr) fail_('Pratinjau tidak tersedia.');
        $s = readJson_($ptr); if ($s['actor'] !== $u['nik'] || $s['base'] !== $db['id'] || now_ms() - $s['created'] > 3600000) fail_('Data berubah/pratinjau kedaluwarsa.');
        $preview = validateCustomerBulk_($s['rows'], $db, $u); $changes = array_values(js_filter($preview, fn($x) => $x['changed']));
        if (!$changes) { prop_delete_('CUSTOMER_BULK_' . $id); return ['updated' => 0]; }
        $before = readGrid_('Master_Customer'); $heads = array_map('text_', $before[0]); $map = []; foreach ($changes as $x) $map[$x['id']] = $x['after'];
        checkpoint_($u['nik'], 'Bulk edit customer ' . count($changes) . ' · ' . $reason);
        try {
            $objects = array_map(fn($r) => $map[text_($r[CUSTOMER_BULK_KEY] ?? null)] ?? $r, table_('Master_Customer'));
            putTable_('Master_Customer', $heads, array_map(fn($r) => array_map(fn($h) => $r[$h] ?? '', $heads), $objects));
            $meta = meta_(); validateConfigChange_($db, $meta);
            publish_($meta, $db['raw'], $db['coverage'], $u['nik'], 'CUSTOMER_BULK ' . count($changes) . ' ' . $reason);
            audit_($u['nik'], 'CUSTOMER_BULK', count($changes) . ' ' . $reason);
            prop_delete_('CUSTOMER_BULK_' . $id);
            return ['updated' => count($changes)];
        } catch (Throwable $e) { restoreGrid_('Master_Customer', $before); throw $e; }
    });
}

function apiTransferDealerTemplate($token, $bigRegion = null, $brand = 'ALL') {
    $db = snapshot_(); $u = adminGuard_($token, $db); $bigRegion = text_($bigRegion); $brand = js_upper(js_or(text_($brand), 'ALL')); $today = jkt_date('Y-m-d');
    $brands = dbaPrimaryBrands_(); if ($brand !== 'ALL' && !in_array($brand, $brands, true)) fail_('Brand transfer tidak valid.');
    $idx = dbaIndex_(); $rows = [];
    foreach ($db['meta']['customers'] as $id => $c) {
        $id = (string)$id;
        if ($brand === 'ALL') {
            $oldNik = dbaMasterNik_($db,$id); $p = PortalCore::person($db['meta']['people'],$oldNik); if(!$p) continue;
            if($u['role']!=='SUPER ADMIN'&&!canManageUser_($u,$p)) continue;
            $big = text_($p['wilayah']??null); if($bigRegion!==''&&js_upper($big)!==js_upper($bigRegion)) continue;
            $rows[] = [$id,text_($c['nama']??null),'ALL',text_($c['region']??null),$big,text_($p['portfolio']??($p['region']??'')),$oldNik,text_($p['nama']??null),'',$today,''];
        } else {
            $o = dealerBrandOwner_($db,$id,$brand,$today,$idx); if($o['nik']==='') continue;
            if($u['role']!=='SUPER ADMIN'&&!dbaCanManageNik_($u,$db,$o['nik'])) continue;
            if($bigRegion!==''&&js_upper($o['big'])!==js_upper($bigRegion)) continue;
            $rows[] = [$id,text_($c['nama']??null),$brand,text_($c['region']??null),$o['big'],$o['region'],$o['nik'],$o['nama'],'',$today,''];
        }
    }
    usort($rows,fn($a,$b)=>strcmp((string)$a[1],(string)$b[1]) ?: strcmp((string)$a[2],(string)$b[2]));
    return workingXlsx_('Transfer-Dealer-' . preg_replace('/[^A-Za-z0-9_-]+/','-', ($bigRegion ?: 'SEMUA').'-'.$brand) . '-' . jkt_date('Ymd') . '.xlsx','Transfer_Dealer',TRANSFER_HEADERS,$rows,
        ['Data difilter Big Region PIC: '.js_or($bigRegion,'SEMUA').' · Brand: '.$brand.'.','BRAND=ALL memindahkan seluruh brand dealer dan memperbarui default NIK SALES di Master Customer. Brand spesifik hanya memindahkan PIC brand itu.','Isi NEW_SALES_NIK, EFFECTIVE_DATE dan NOTE. Nama Sales akan divalidasi dari NIK.','Lokasi fisik dealer tidak berubah. Transaksi lama tidak ditulis ulang.'],['KODE CUSTOMER','BRAND'],['NAMA CUSTOMER','LOKASI DEALER','BIG REGION PIC','REGION PIC','NIK SALES SAAT INI','NAMA SALES SAAT INI']);
}
function transferRows_(array $rows, array $db, array $u): array {
    if (!$rows || count($rows) > 5000) fail_('Isi 1–5.000 baris per transfer.');
    $seen = []; $today = jkt_date('Y-m-d'); $out = []; $idx=dbaIndex_(); $brands=dbaPrimaryBrands_();
    foreach (array_values($rows) as $i => $r) {
        $dealer = text_($r['KODE CUSTOMER'] ?? null); $brand = js_upper(js_or(text_($r['BRAND'] ?? null),'ALL')); $nik = text_($r['NEW_SALES_NIK'] ?? null); $date = PortalCore::iso($r['EFFECTIVE_DATE'] ?? null); $note = text_($r['NOTE'] ?? null);
        $c = $db['meta']['customers'][$dealer] ?? null; $p = PortalCore::person($db['meta']['people'], $nik);
        if ($dealer === '' || !$c) fail_('Baris ' . ($i + 2) . ': dealer tidak ditemukan: ' . $dealer);
        if ($brand !== 'ALL' && !in_array($brand,$brands,true)) fail_('Baris '.($i+2).': BRAND tidak valid: '.$brand);
        $key=$dealer.'|'.$brand; if (isset($seen[$key])) fail_('Baris ' . ($i + 2) . ': dealer + brand duplikat dalam file: ' . $dealer . ' · ' . $brand); $seen[$key]=1;
        if (!$p || !truthy($p['sales'] ?? null) || !dbaCanManageNik_($u,$db,$nik)) fail_('Baris ' . ($i + 2) . ': Sales tujuan tidak valid/di luar kewenangan: ' . $nik);
        if (!$date) fail_('Baris '.($i+2).': EFFECTIVE_DATE tidak valid.');
        if (strcmp($date, $today) > 0) fail_('Baris ' . ($i + 2) . ': tanggal efektif masa depan belum didukung; jalankan transfer pada/ setelah tanggal efektif.');
        $checkBrands=$brand==='ALL'?$brands:[$brand]; $olds=[];
        foreach($checkBrands as $b){$o=dealerBrandOwner_($db,$dealer,$b,$date,$idx);if($o['nik']==='')continue;if(!dbaCanManageNik_($u,$db,$o['nik']))fail_('Baris '.($i+2).': PIC sumber '.$b.' di luar kewenangan.');$olds[$b]=$o;}
        if(!$olds) fail_('Baris '.($i+2).': PIC saat ini tidak ditemukan.');
        if(js_every(array_values($olds),fn($o)=>$o['nik']===$nik)) fail_('Baris '.($i+2).': Sales tujuan sama dengan PIC saat ini.');
        $labels=array_values(array_unique(array_map(fn($o)=>dealerBrandOwnerLabel_($o),array_values($olds)))); $oldBigs=array_values(array_unique(array_filter(array_map(fn($o)=>$o['big'],array_values($olds)))));
        $out[]=['dealer'=>$dealer,'nama'=>js_or($c['nama']??null,$dealer),'brand'=>$brand,'oldNik'=>count($labels)===1?reset($olds)['nik']:'MULTI','oldName'=>implode(' / ',$labels),'newNik'=>$nik,'newName'=>text_($p['nama']??null),'date'=>$date,'note'=>$note,'oldBig'=>implode(' / ',$oldBigs),'newBig'=>text_($p['wilayah']??null),'cross'=>js_some($oldBigs,fn($x)=>js_upper($x)!==js_upper(text_($p['wilayah']??null))),'brands'=>$checkBrands];
    }
    return $out;
}
function apiStageDealerTransfer($form) {
    $form = is_array($form) ? $form : []; $db = snapshot_(); $u = adminGuard_($form['token'] ?? null, $db); $parsed = bulkReadRows_($form['file'] ?? null, 'Transfer_Dealer');
    foreach (['KODE CUSTOMER','BRAND','NEW_SALES_NIK','EFFECTIVE_DATE'] as $h) if (!in_array($h, $parsed['heads'], true)) fail_('Kolom wajib ' . $h);
    if (!$parsed['rows'] || count($parsed['rows']) > 5000) fail_('Isi 1–5.000 baris per transfer.');
    $rej = []; $ok = []; $seen = [];
    foreach ($parsed['rows'] as $i => $r) {
        try {
            $k = text_($r['KODE CUSTOMER'] ?? null).'|'.js_upper(js_or(text_($r['BRAND']??null),'ALL'));
            if ($k !== '|' && isset($seen[$k])) fail_('Baris 2: dealer + brand duplikat dalam file: ' . str_replace('|',' · ',$k));
            transferRows_([$r], $db, $u); $seen[$k] = 1; $ok[] = $r;
        } catch (AppError $e) {
            $m = $e->getMessage();
            $rej[] = ['row' => $r, 'alasan' => preg_replace('/^Baris 2/', 'Baris ' . ($i + 2), $m, 1), 'solusi' => preg_match('/tidak ditemukan/', $m) ? 'Cek KODE CUSTOMER atau NIK Sales.' : (preg_match('/Sales tujuan/', $m) ? 'NEW_SALES_NIK harus NIK Sales aktif di wilayah Anda.' : (preg_match('/duplikat/', $m) ? 'Satu kombinasi dealer + brand cukup 1 baris.' : (preg_match('/sama dengan PIC/', $m) ? 'PIC sudah sama — hapus barisnya.' : (preg_match('/masa depan/', $m) ? 'Isi EFFECTIVE_DATE hari ini atau sebelumnya.' : 'Perbaiki baris sesuai petunjuk.'))))];
        }
    }
    if (!$ok) fail_('Semua baris ditolak. Contoh: ' . ($rej[0]['alasan'] ?? ''));
    $rows = transferRows_($ok, $db, $u); $id = uuid_();
    prop_set_('TRANSFER_STAGE_' . $id, writeJson_('transfer-stage-' . $id, ['actor' => $u['nik'], 'base' => $db['id'], 'created' => now_ms(), 'rows' => $ok, 'rejected' => $rej, 'heads' => $parsed['heads']]));
    return ['id'=>$id,'total'=>count($rows),'rows'=>$rows,'rejected'=>count($rej),'rejSample'=>array_map(fn($x)=>['alasan'=>$x['alasan'],'solusi'=>$x['solusi']],array_slice($rej,0,20))];
}
function apiTransferRejected($token, $id) {
    $u = adminGuard_($token); $ptr = prop_get_('TRANSFER_STAGE_' . js_str($id)); if (!$ptr) fail_('Pratinjau tidak tersedia.');
    $s = readJson_($ptr); if ($s['actor'] !== $u['nik']) fail_('Bukan pratinjau Anda.'); if (empty($s['rejected'])) fail_('Tidak ada baris ditolak.');
    return rejectBase64_('Transfer-Dealer.xlsx', js_or($s['heads'] ?? null, TRANSFER_HEADERS), $s['rejected']);
}
function apiCommitDealerTransfer($token, $id, $reason = null) {
    $reason=text_($reason); if(mb_strlen($reason)<5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function()use($token,$id,$reason){
        $db=snapshot_();$u=adminGuard_($token,$db);$ptr=prop_get_('TRANSFER_STAGE_'.js_str($id));if(!$ptr)fail_('Pratinjau tidak tersedia.');$s=readJson_($ptr);if($s['actor']!==$u['nik']||$s['base']!==$db['id']||now_ms()-$s['created']>3600000)fail_('Data berubah/pratinjau kedaluwarsa.');
        $rows=transferRows_($s['rows'],$db,$u);$beforeCustomer=readGrid_('Master_Customer');$beforeLegacy=readGrid_('Dealer_Assignment_History');$beforeBrand=readGrid_(DBA_SHEET);checkpoint_($u['nik'],'Transfer dealer per brand '.count($rows).' · '.$reason);$allChanged=false;$logs=[];
        try{
            foreach($rows as $t){
                foreach($t['brands'] as $b){$r=dbaSet_($db,$u,$t['dealer'],$b,$t['newNik'],$t['date'],js_or($t['note'],$reason),'BULK_TRANSFER');$logs[]=$t['dealer'].' · '.$b.' · '.dealerBrandOwnerLabel_($r['before']).' → '.dealerBrandOwnerLabel_($r['after']).' · '.$t['date'];}
                if($t['brand']==='ALL'){$allChanged=true;$heads=array_map('text_',$beforeCustomer[0]);$objects=table_('Master_Customer');foreach($objects as &$c)if(text_($c['KODE CUSTOMER']??null)===$t['dealer'])$c['NIK SALES']=$t['newNik'];unset($c);putTable_('Master_Customer',$heads,array_map(fn($r)=>array_map(fn($h)=>$r[$h]??'',$heads),$objects));
                    $history=optionalTable_('Dealer_Assignment_History');$now=iso_now();$active=null;foreach($history as $i=>$h)if(text_($h['KODE CUSTOMER']??null)===$t['dealer']&&text_($h['BERLAKU_SAMPAI']??null)==='')$active=$i;if($active!==null)$history[$active]['BERLAKU_SAMPAI']=dayAfter_($t['date'],-1);else $history[]=['KODE CUSTOMER'=>$t['dealer'],'NIK SALES'=>dbaMasterNik_($db,$t['dealer']),'BERLAKU_MULAI'=>'','BERLAKU_SAMPAI'=>dayAfter_($t['date'],-1),'SUMBER'=>'SEED','NOTE'=>'Owner sebelum transfer semua brand','UPDATED_BY'=>$u['nik'],'UPDATED_AT'=>$now];$history[]=['KODE CUSTOMER'=>$t['dealer'],'NIK SALES'=>$t['newNik'],'BERLAKU_MULAI'=>$t['date'],'BERLAKU_SAMPAI'=>'','SUMBER'=>'BULK_TRANSFER_ALL','NOTE'=>js_or($t['note'],$reason),'UPDATED_BY'=>$u['nik'],'UPDATED_AT'=>$now];putTable_('Dealer_Assignment_History',ASSIGNMENT_HEADERS,array_map(fn($r)=>array_map(fn($h)=>$r[$h]??'',ASSIGNMENT_HEADERS),$history));
                }
            }
            if($allChanged){$meta=meta_();validateConfigChange_($db,$meta);publish_($meta,$db['raw'],$db['coverage'],$u['nik'],'DEALER_TRANSFER_ALL '.count($rows).' '.$reason);}
            audit_($u['nik'],'DEALER_BRAND_TRANSFER',implode(' | ',array_slice($logs,0,80)).(count($logs)>80?' …':'').' · '.$reason);if(function_exists('undoRegister_'))undoRegister_('TRANSFER','Transfer Dealer · '.count($rows).' dealer · '.count($logs).' assignment brand',$u,['Master_Customer'=>$beforeCustomer,'Dealer_Assignment_History'=>$beforeLegacy,DBA_SHEET=>$beforeBrand],['published'=>$allChanged,'date'=>$rows[0]['date']??'','reason'=>$reason]);prop_delete_('TRANSFER_STAGE_'.$id);return['updated'=>count($rows),'assignments'=>count($logs),'allBrand'=>$allChanged];
        }catch(Throwable $e){if($beforeCustomer)restoreGrid_('Master_Customer',$beforeCustomer);if($beforeLegacy)restoreGrid_('Dealer_Assignment_History',$beforeLegacy);elseif(sheet_exists_('Dealer_Assignment_History'))deleteSheet_('Dealer_Assignment_History');if($beforeBrand)restoreGrid_(DBA_SHEET,$beforeBrand);elseif(sheet_exists_(DBA_SHEET))deleteSheet_(DBA_SHEET);throw $e;}
    });
}

function masterBulkCfg_($type): array { $c = MASTER_BULK[js_upper(js_str(js_or($type, '')))] ?? null; if (!$c) fail_('Jenis bulk master tidak valid.'); return $c; }
function masterKey_(array $r, array $keys): string { return implode("\x1f", array_map(fn($k) => text_($r[$k] ?? null), $keys)); }
function ensureProductStatus_(): void { $g=readGrid_('Master_Product'); if(!$g)return; $h=array_map('text_',$g[0]); if(in_array('STATUS',$h,true))return; $h[]='STATUS'; $rows=[]; foreach(array_slice($g,1) as $r){if(!row_has_value_($r))continue;$r=array_values($r);$r[]='ACTIVE';$rows[]=$r;} putTable_('Master_Product',$h,$rows); }
function masterDownloadRows_(array $cfg,array $db): array { if($cfg['sheet']==='Master_Product')ensureProductStatus_(); $grid=readGrid_($cfg['sheet']); if(!$grid)fail_('Sheet tidak ditemukan: '.$cfg['sheet']); $heads=$cfg['heads']??array_map('text_',$grid[0]); $rows=[]; foreach(table_($cfg['sheet']) as $r)$rows[]=array_map(fn($h)=>text_($r[$h]??null),$heads); $ref=[];$locked=$cfg['keys']; if($cfg['sheet']==='Target_Periode'&&in_array('NIK',$heads,true)){[$heads,$rows]=addRefAfter_($heads,$rows,'NIK','NAMA SALES',fn($r,$h)=>personNameByNik_($db,text_($r[array_search('NIK',$h,true)]??'')));$ref[]='NAMA SALES';} if($cfg['sheet']==='Master_Sales'&&in_array('NIK KARYAWAN',$heads,true)){[$heads,$rows]=addRefAfter_($heads,$rows,'NIK KARYAWAN','NAMA',fn($r,$h)=>personNameByNik_($db,text_($r[array_search('NIK KARYAWAN',$h,true)]??'')));$ref[]='NAMA';} return[$heads,$rows,$locked,$ref]; }

function apiMasterBulkTemplate($token, $type = null) {
    $db=snapshot_(); adminGuard_($token,$db); $t=js_upper(js_str(js_or($type,''))); if(isset(DEALER_ATTR[$t]))return attrTemplate_($t); if($t==='TARGET')return targetInputTemplate_($db); $cfg=masterBulkCfg_($type); [$heads,$rows,$locked,$ref]=masterDownloadRows_($cfg,$db); return workingXlsx_($cfg['sheet'].'-'.jkt_date('Ymd').'.xlsx',$cfg['sheet'],$heads,$rows,['Mode upsert: key sama diperbarui, key baru ditambahkan kecuali Struktur Organisasi.','File ini berisi data existing.','Semua perubahan diperiksa sebelum commit.'],$locked,$ref);
}
function apiStageMasterBulk($form) {
    $form = is_array($form) ? $form : []; $type = js_upper(js_str(js_or($form['type'] ?? null, '')));
    if (isset(DEALER_ATTR[$type])) return attrStage_($type, $form);
    if ($type === 'TARGET') targetInputHeads_(snapshot_()); if ($type === 'PRODUCT') ensureProductStatus_();
    $db = snapshot_(); $u = guard_($form['token'] ?? null, true); if ($u['role'] !== 'SUPER ADMIN') fail_('Upload/edit bulk master khusus Super Admin.'); $cfg = masterBulkCfg_($form['type'] ?? null); $grid = readGrid_($cfg['sheet']);
    $allHeads = array_map('text_', $grid[0]); $heads = $cfg['heads'] ?? $allHeads; $parsed = bulkReadRows_($form['file'] ?? null, $cfg['sheet']);
    foreach ($heads as $h) if (!in_array($h, $parsed['heads'], true)) fail_('Kolom wajib ' . $h);
    if (!$parsed['rows'] || count($parsed['rows']) > 10000) fail_('Isi 1–10.000 baris.');
    $existing = []; foreach (table_($cfg['sheet']) as $r) $existing[masterKey_($r, $cfg['keys'])] = $r;
    $seen = []; $rejected = []; $kept = []; $kl = implode(', ', $cfg['keys']); $rows = [];
    foreach ($parsed['rows'] as $i => $r) {
        $rj = function ($a, $b) use (&$rejected, $r, $i) { $rejected[] = ['row' => $r, 'alasan' => 'Baris ' . ($i + 2) . ': ' . $a, 'solusi' => $b]; };
        $key = masterKey_($r, $cfg['keys']);
        if (str_replace("\x1f", '', $key) === '') { $rj('kunci kosong (' . $kl . ').', 'Isi kolom ' . $kl . '.'); continue; }
        if (isset($seen[$key])) { $rj('kunci dobel dengan baris sebelumnya (' . $kl . ').', 'Satu kombinasi ' . $kl . ' cukup 1 baris — hapus yang kembar.'); continue; }
        $seen[$key] = 1; $old = $existing[$key] ?? null;
        if (!empty($cfg['existingOnly']) && !$old) { $rj($cfg['label'] . ' hanya untuk user yang sudah ada.', 'Tambah user baru lewat Tambah user massal.'); continue; }
        $clean = []; foreach ($heads as $h) $clean[$h] = text_($r[$h] ?? null);
        $changed = !$old; $changes=[]; if ($old) foreach ($heads as $h) if (text_($old[$h] ?? null) !== $clean[$h]) { $changed = true; $changes[]=['field'=>$h,'before'=>text_($old[$h]??null),'after'=>$clean[$h]]; }
        $kept[] = $r; $rows[] = ['key' => $key, 'status' => $old ? ($changed ? 'UPDATE' : 'SAMA') : 'BARU', 'values' => $clean, 'changes'=>$changes];
    }
    if (!$rows && $rejected) fail_('Semua baris ditolak. Contoh: ' . $rejected[0]['alasan']);
    $id = uuid_();
    prop_set_('MASTER_BULK_' . $id, writeJson_('master-bulk-' . $id, ['actor' => $u['nik'], 'base' => $db['id'], 'created' => now_ms(), 'type' => $type, 'rows' => $kept, 'rejected' => $rejected, 'heads' => $parsed['heads']]));
    $cnt = fn($s) => count(js_filter($rows, fn($r) => $r['status'] === $s));
    return ['id' => $id, 'label' => $cfg['label'], 'total' => count($rows), 'newCount' => $cnt('BARU'), 'updateCount' => $cnt('UPDATE'), 'sameCount' => $cnt('SAMA'), 'rows' => array_slice($rows, 0, 100), 'rejected' => count($rejected), 'rejSample' => array_map(fn($x) => ['alasan' => $x['alasan'], 'solusi' => $x['solusi']], array_slice($rejected, 0, 30))];
}
function apiMasterBulkRejected($token, $id) {
    $u = guard_($token, true); $ptr = prop_get_('MASTER_BULK_' . js_str($id)); if (!$ptr) fail_('Pratinjau tidak tersedia.');
    $s = readJson_($ptr); if ($s['actor'] !== $u['nik']) fail_('Bukan pratinjau Anda.');
    if (empty($s['rejected'])) fail_('Tidak ada baris ditolak.');
    return rejectBase64_('Upload-' . js_or($s['type'] ?? null, 'master') . '.xlsx', js_or($s['heads'] ?? null, array_keys($s['rejected'][0]['row'] ?? [])), $s['rejected']);
}
function apiCommitMasterBulk($token, $id, $reason = null) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function () use ($token, $id, $reason) {
        $db = snapshot_(); $u = guard_($token, true); if ($u['role'] !== 'SUPER ADMIN') fail_('Commit bulk master khusus Super Admin.'); $ptr = prop_get_('MASTER_BULK_' . js_str($id)); if (!$ptr) fail_('Pratinjau tidak tersedia.');
        $s = readJson_($ptr); if ($s['actor'] !== $u['nik'] || $s['base'] !== $db['id'] || now_ms() - $s['created'] > 3600000) fail_('Data berubah/pratinjau kedaluwarsa.');
        if (isset(DEALER_ATTR[$s['type']])) return attrCommit_($token, $id, $reason, $s);
        $cfg = masterBulkCfg_($s['type']); $before = readGrid_($cfg['sheet']); $heads = array_map('text_', $before[0]); $editHeads = $cfg['heads'] ?? $heads;
        $map = []; foreach (table_($cfg['sheet']) as $r) $map[masterKey_($r, $cfg['keys'])] = $r;
        foreach ($s['rows'] as $r) {
            $clean = []; foreach ($editHeads as $h) $clean[$h] = text_($r[$h] ?? null); $key = masterKey_($clean, $cfg['keys']);
            if (!empty($cfg['existingOnly']) && !isset($map[$key])) fail_($cfg['label'] . ' hanya boleh mengubah user existing.');
            $map[$key] = array_merge($map[$key] ?? [], $clean);
        }
        checkpoint_($u['nik'], 'Bulk ' . $cfg['label'] . ' · ' . $reason);
        try {
            $out = []; foreach (js_keys($map) as $k) $out[] = array_map(fn($h) => $map[$k][$h] ?? '', $heads);
            putTable_($cfg['sheet'], $heads, $out);
            $meta = meta_(); validateConfigChange_($db, $meta);
            publish_($meta, $db['raw'], $db['coverage'], $u['nik'], 'MASTER_BULK ' . $cfg['sheet'] . ' ' . count($s['rows']) . ' ' . $reason);
            audit_($u['nik'], 'MASTER_BULK', $cfg['sheet'] . ' ' . count($s['rows']) . ' ' . $reason);
            prop_delete_('MASTER_BULK_' . $id);
            return ['updated' => count($s['rows'])];
        } catch (Throwable $e) { restoreGrid_($cfg['sheet'], $before); throw $e; }
    });
}


// ------------------------------------------------------------------
// Master Product editor (Super Admin)
// ------------------------------------------------------------------
function productSuper_($token): array { $db=snapshot_(); $u=guard_($token,true,false,$db); if($u['role']!=='SUPER ADMIN') fail_('Edit Master Product khusus Super Admin.'); return[$db,$u]; }
function apiProductMasterGet($token,$q=null) {
    [$db,$u]=productSuper_($token); ensureProductStatus_(); $q=js_upper(text_($q)); $rows=[]; $types=[];$cats=[];
    foreach(table_('Master_Product') as $r){ $code=text_($r['KODE BARANG']??null); $name=text_($r['NAMA BARANG']??null); $brand=text_($r['BRAND']??null); $cat=text_($r['KATEGORI PRODUK']??null); $type=text_($r['TYPE']??null); $status=js_upper(text_($r['STATUS']??'ACTIVE'))?:'ACTIVE'; if($status!=='INACTIVE'){if($type!=='')$types[$type]=1;if($cat!=='')$cats[$cat]=1;} if($q!==''&&mb_strpos(js_upper($code.' '.$name.' '.$brand.' '.$type.' '.$cat),$q)===false)continue; $rows[]=['code'=>$code,'name'=>$name,'brand'=>$brand,'category'=>$cat,'type'=>$type,'status'=>$status]; }
    usort($rows,fn($a,$b)=>strcmp($a['code'],$b['code']));
    $rules=[];$brands=[];$brCat=[];foreach(optionalTable_('Master_Product') as $x){$bb=text_($x['BRAND']??null);$cc=text_($x['KATEGORI PRODUK']??null);if($bb!==''&&$cc!==''&&!isset($brCat[$bb]))$brCat[$bb]=$cc;}foreach(table_('Product_Rules') as $r){$b=text_($r['BRAND']??null);if($b==='')continue;$active=js_upper(text_($r['AKTIF']??'TRUE'))!=='FALSE';if($active)$brands[$b]=1;$qty=PortalCore::active($r['MASUK_QTY']??null);$rules[]=['name'=>$b,'parent'=>js_or(text_($r['BRAND_UTAMA']??null),$b),'category'=>js_or(text_($r['KATEGORI']??null),$brCat[$b]??($qty?'HP':'ACC')),'qty'=>$qty,'active'=>$active];}
    usort($rules,fn($a,$b)=>strcmp($a['name'],$b['name']));
    return['rows'=>array_slice($rows,0,500),'total'=>count($rows),'brands'=>js_sort_default(array_keys($brands)),'rules'=>$rules,'types'=>js_sort_default(array_keys($types)),'categories'=>js_sort_default(array_keys($cats))];
}
function apiProductImpact($token,$code) {
    [$db,$u]=productSuper_($token); $code=text_($code); $r=null; foreach(table_('Master_Product') as $x)if(text_($x['KODE BARANG']??null)===$code){$r=$x;break;} if(!$r)fail_('Produk tidak ditemukan.');
    $st=db_()->prepare('SELECT COUNT(*) c, COALESCE(SUM(qty),0) q FROM tx_st WHERE kode_produk=?');$st->execute([$code]);$x=$st->fetch(PDO::FETCH_ASSOC)?:['c'=>0,'q'=>0];
    $kpi=[];foreach(optionalTable_('KPI_Config') as $k){$f='|'.js_upper(text_($k['TYPE_FILTER']??null)).'|';$t=js_upper(text_($r['TYPE']??null));if($t!==''&&str_contains($f,'|'.$t.'|'))$kpi[]=text_($k['LABEL']??($k['INDIKATOR_ID']??''));}
    return['transactions'=>(int)$x['c'],'qty'=>(float)$x['q'],'kpi'=>array_values(array_unique(array_filter($kpi)))];
}
function apiProductMasterSave($token,$items,$reason) {
    $reason=text_($reason); if(mb_strlen($reason)<5)fail_('Isi alasan minimal 5 karakter.'); if(!is_array($items)||!$items||count($items)>100)fail_('Isi 1–100 produk per simpan.');
    return with_lock_(function()use($token,$items,$reason){[$db,$u]=productSuper_($token);ensureProductStatus_();$before=readGrid_('Master_Product');$heads=array_map('text_',$before[0]);$map=[];foreach(table_('Master_Product') as $r)$map[text_($r['KODE BARANG']??null)]=$r;$brandOk=[];foreach(table_('Product_Rules') as $r){$b=js_upper(text_($r['BRAND']??null));if($b!=='')$brandOk[$b]=1;}$log=[];
      foreach($items as $i=>$x){$code=text_($x['code']??null);if($code===''||!preg_match('/^[A-Za-z0-9._-]{1,100}$/',$code))fail_('Baris '.($i+1).': KODE BARANG tidak valid.');$old=$map[$code]??null;if($old&&text_($x['originalCode']??$code)!==$code)fail_('KODE BARANG existing tidak boleh diubah.');$name=text_($x['name']??null);$brand=text_($x['brand']??null);$cat=text_($x['category']??null);$type=text_($x['type']??null);$status=js_upper(text_($x['status']??'ACTIVE'));if($name===''||$brand===''||$cat===''||$type==='')fail_('Baris '.($i+1).': Nama, Brand, Kategori, dan Type wajib.');if(!isset($brandOk[js_upper($brand)]))fail_('Baris '.($i+1).': BRAND '.$brand.' tidak ada di Product Rules.');if(!in_array($status,['ACTIVE','INACTIVE'],true))fail_('STATUS harus ACTIVE/INACTIVE.');$next=array_merge($old??[],['KODE BARANG'=>$code,'NAMA BARANG'=>$name,'BRAND'=>$brand,'KATEGORI PRODUK'=>$cat,'TYPE'=>$type,'STATUS'=>$status]);$chg=[];foreach(['NAMA BARANG','BRAND','KATEGORI PRODUK','TYPE','STATUS'] as $h){$a=text_($old[$h]??'');$b=text_($next[$h]??'');if($a!==$b)$chg[]=$h.': '.$a.' → '.$b;}if(!$old)$chg[]='PRODUK BARU';$map[$code]=$next;if($chg)$log[]=$code.' ['.implode('; ',$chg).']';}
      if(!$log)return['updated'=>0];checkpoint_($u['nik'],'Master Product '.count($log).' · '.$reason);try{$out=[];foreach($map as $r)$out[]=array_map(fn($h)=>$r[$h]??'',$heads);putTable_('Master_Product',$heads,$out);$meta=meta_();validateConfigChange_($db,$meta);publish_($meta,$db['raw'],$db['coverage'],$u['nik'],'PRODUCT_MASTER '.count($log).' '.$reason);audit_($u['nik'],'MASTER_PRODUCT',implode(' | ',array_slice($log,0,30)).' · '.$reason);return['updated'=>count($log)];}catch(Throwable $e){restoreGrid_('Master_Product',$before);throw $e;}
    });
}

// ------------------------------------------------------------------
// Promotor.gs — upload Master Promotor / Master Grade (ganti seluruh isi)
// ------------------------------------------------------------------
const ATTR_KEY = 'ID DEALER';
function attrHeads_(array $cfg): array {
    $brands = []; $add = function ($b) use (&$brands) { $b = js_upper(text_($b)); if ($b !== '' && !in_array($b, $brands, true)) $brands[] = $b; };
    try { $g = readGrid_($cfg['sheet']); if ($g && count($g)) foreach ($g[0] as $h) $add(attrBrandHead_($cfg, $h)); } catch (Throwable $e) {}
    try { foreach (table_('Product_Rules') as $r) $add($r['BRAND'] ?? null); } catch (Throwable $e) {}
    return array_merge([ATTR_KEY, 'NAMA DEALER'], array_map(fn($b) => $cfg['prefix'] . ' ' . $b, $brands));
}
function attrTemplate_(string $type): array {
    $cfg = DEALER_ATTR[$type]; $db = snapshot_(); $heads = attrHeads_($cfg); $rows = [];
    try {
        $g = readGrid_($cfg['sheet']);
        if ($g && count($g)) {
            $gh = array_map(function ($h) use ($cfg) { $b = attrBrandHead_($cfg, $h); return $b !== '' ? $cfg['prefix'] . ' ' . $b : js_upper(text_($h)); }, $g[0]);
            foreach (array_slice($g, 1) as $r) {
                $o = []; foreach ($gh as $i => $h) $o[$h] = $r[$i] ?? null; $id = text_($o[ATTR_KEY] ?? null); if ($id === '') continue;
                $rows[] = array_map(fn($h) => $h === 'NAMA DEALER' ? js_or($db['meta']['customers'][$id]['nama'] ?? null, text_($o[$h] ?? null)) : (($o[$h] ?? null) === null ? '' : (string) $o[$h]), $heads);
            }
        }
    } catch (Throwable $e) {}
    $notes = $cfg['num']
        ? ['Isi HANYA dealer yang punya promotor. Dealer yang tidak ada di file = tanpa promotor.', 'Upload MENGGANTI seluruh isi sheet — hapus baris kalau promotor dealer itu sudah tidak ada.', 'Kolom brand bebas: tambah kolom "PROMOTOR <BRAND>" untuk brand baru / kompetitor.', 'Jumlah promotor boleh kosong — dealer tetap BER-PROMOTOR dengan keterangan "brand belum diisi".']
        : ['Isi dealer yang punya grade di minimal satu brand. Grade boleh beda format per brand (A/B/C, GOLD, dst).', 'Upload MENGGANTI seluruh isi sheet — hapus baris / kosongkan sel kalau grade sudah tidak berlaku.', 'Kolom brand bebas: tambah kolom "GRADE <BRAND>" untuk brand baru.'];
    return templateXlsx_('Template-' . $cfg['sheet'] . '.xlsx', $cfg['sheet'], $heads, $rows, $notes);
}
function attrValidate_(array $cfg, array $parsed, array $db): array {
    $heads = array_map(fn($h) => preg_replace('/\s+/u', ' ', js_upper(text_($h))), $parsed['heads']); $kIdx = array_search(ATTR_KEY, $heads, true);
    if ($kIdx === false) fail_('Kolom wajib ' . ATTR_KEY . '.');
    $bh = []; $seenB = [];
    foreach ($parsed['heads'] as $h) { $b = attrBrandHead_($cfg, $h); if ($b === '') continue; if (isset($seenB[$b])) fail_('Kolom ' . $cfg['prefix'] . ' ' . $b . ' dobel.'); $seenB[$b] = 1; $bh[] = [$h, $b]; }
    if (!$bh) fail_('Minimal satu kolom "' . $cfg['prefix'] . ' <BRAND>".');
    if (count($parsed['rows']) > 10000) fail_('Maksimal 10.000 baris.');
    $seen = []; $rows = []; $rejected = [];
    foreach ($parsed['rows'] as $i => $r) {
        $line = $i + 2; $id = text_($r[$parsed['heads'][$kIdx]] ?? null); $errs = []; $sol = [];
        $rj = function () use (&$rejected, $r, &$id, $line, &$errs, &$sol) { $rejected[] = ['row' => $r, 'id' => $id, 'alasan' => 'Baris ' . $line . ': ' . implode(' ', $errs), 'solusi' => implode(' ', $sol)]; };
        if ($id === '') { if (js_some($bh, fn($x) => text_($r[$x[0]] ?? null) !== '')) { $errs[] = 'ID DEALER kosong.'; $sol[] = 'Isi ID DEALER (lihat Download data dealer).'; $rj(); } continue; }
        if (isset($seen[$id])) { $errs[] = 'ID DEALER ' . $id . ' dobel.'; $sol[] = 'Satu dealer cukup 1 baris — gabungkan isinya.'; $rj(); continue; }
        $seen[$id] = 1;
        if (!isset($db['meta']['customers'][$id])) { $errs[] = 'ID DEALER ' . $id . ' tidak ada di Master_Customer.'; $sol[] = 'Cek kodenya (0 di depan hilang karena Excel?) atau daftarkan dealernya dulu.'; }
        $vals = [];
        foreach ($bh as $x) {
            $v = text_($r[$x[0]] ?? null); if ($v === '') continue;
            if ($cfg['num']) { $n = js_number_($v); if (!(is_numeric($n) && !is_nan((float) $n) && $n >= 0) || floor($n) != $n) { $errs[] = $cfg['prefix'] . ' ' . $x[1] . ' harus angka bulat ≥ 0 (isi: ' . $v . ').'; $sol[] = 'Isi jumlah orang, mis. 1 atau 2; kosongkan kalau tidak ada.'; } else $vals[$x[1]] = $n; }
            else { if (mb_strlen($v) > 20) { $errs[] = $cfg['prefix'] . ' ' . $x[1] . ' maksimal 20 karakter.'; $sol[] = 'Singkat grade-nya, mis. A / B / GOLD.'; } else $vals[$x[1]] = preg_replace('/\s+/u', ' ', js_upper($v)); }
        }
        if ($errs) { $rj(); continue; }
        $rows[] = ['id' => $id, 'nama' => isset($db['meta']['customers'][$id]) ? js_or($db['meta']['customers'][$id]['nama'] ?? null, '') : '', 'vals' => $vals];
    }
    if (!$rows && $rejected) fail_('Semua baris ditolak. Contoh: ' . $rejected[0]['alasan']);
    return ['brands' => array_map(fn($x) => $x[1], $bh), 'rows' => $rows, 'rejected' => $rejected, 'heads' => $parsed['heads']];
}
function attrStage_(string $type, array $form): array {
    $cfg = DEALER_ATTR[$type]; $db = snapshot_(); $u = guard_($form['token'] ?? null, true); $parsed = bulkReadRows_($form['file'] ?? null, $cfg['sheet']);
    $v = attrValidate_($cfg, $parsed, $db); $old = attrMap_($type); $id = uuid_();
    $rowIds = array_column($v['rows'], 'id');
    $keepOld = [];
    foreach ($v['rejected'] as $x) {
        if (truthy($x['id']) && isset($old[$x['id']]) && !in_array($x['id'], $rowIds, true)) {
            $o = $old[$x['id']]; $vals = []; foreach ($o['brands'] as $b) $vals[$b[0]] = $b[1];
            $keepOld[] = ['id' => $x['id'], 'nama' => isset($db['meta']['customers'][$x['id']]) ? js_or($db['meta']['customers'][$x['id']]['nama'] ?? null, '') : '', 'vals' => $vals];
        }
    }
    foreach ($keepOld as $k) foreach (js_keys($k['vals']) as $b) if (!in_array((string) $b, $v['brands'], true)) $v['brands'][] = (string) $b;
    $v['keepOld'] = $keepOld;
    prop_set_('MASTER_BULK_' . $id, writeJson_('master-bulk-' . $id, ['actor' => $u['nik'], 'base' => $db['id'], 'created' => now_ms(), 'type' => $type, 'data' => $v, 'rejected' => $v['rejected'], 'heads' => $v['heads']]));
    $prev = [];
    foreach ($v['rows'] as $r) {
        $o = $old[$r['id']] ?? null; $nb = [];
        foreach (js_keys($r['vals']) as $b) { $val = $r['vals'][$b]; if ($cfg['num'] ? $val > 0 : (bool) $val) $nb[] = [(string) $b, $val]; }
        $vals = ['ID DEALER' => $r['id'], 'NAMA DEALER' => $r['nama']];
        $vals[$cfg['prefix']] = $nb ? implode(' · ', array_map(fn($x) => $x[0] . ' ' . js_str($x[1]), $nb)) : ($cfg['num'] ? 'brand belum diisi' : '(kosong — diabaikan)');
        $prev[] = ['key' => $r['id'], 'status' => !$o ? 'BARU' : (js_stringify($o['brands']) === js_stringify($nb) ? 'SAMA' : 'UPDATE'), 'values' => $vals];
    }
    $removed = 0; foreach (js_keys($old) as $k) if (!in_array((string) $k, $rowIds, true)) $removed++;
    $cnt = fn($s) => count(js_filter($prev, fn($r) => $r['status'] === $s));
    return ['id' => $id, 'label' => $cfg['label'] . ' (ganti semua isi' . ($removed ? ' · ' . $removed . ' dealer dihapus' : '') . ')', 'total' => count($prev), 'newCount' => $cnt('BARU'), 'updateCount' => $cnt('UPDATE'), 'sameCount' => $cnt('SAMA'), 'rows' => array_slice($prev, 0, 100),
        'rejected' => count($v['rejected']), 'keptOld' => count($keepOld), 'rejSample' => array_map(fn($x) => ['alasan' => $x['alasan'], 'solusi' => $x['solusi']], array_slice($v['rejected'], 0, 30))];
}
function attrCommit_($token, $id, $reason, array $s): array {
    $cfg = DEALER_ATTR[$s['type']]; $u = guard_($token, true); $v = $s['data'];
    $heads = array_merge([ATTR_KEY, 'NAMA DEALER'], array_map(fn($b) => $cfg['prefix'] . ' ' . $b, $v['brands'])); $before = readGrid_($cfg['sheet']);
    checkpoint_($u['nik'], 'Bulk ' . ($cfg['label'] ?? $cfg['sheet']) . ' · ' . $reason);
    try {
        $data = [];
        foreach (array_merge($v['rows'], $v['keepOld'] ?? []) as $r) $data[] = array_merge([$r['id'], $r['nama']], array_map(fn($b) => array_key_exists($b, $r['vals']) && $r['vals'][$b] !== null ? $r['vals'][$b] : '', $v['brands']));
        putTable_($cfg['sheet'], $heads, $data);
    } catch (Throwable $e) { if ($before) restoreGrid_($cfg['sheet'], $before); throw $e; }
    audit_($u['nik'], 'MASTER_BULK', $cfg['sheet'] . ' ' . count($v['rows']) . ' ' . $reason);
    prop_delete_('MASTER_BULK_' . $id);
    return ['updated' => count($v['rows'])];
}
