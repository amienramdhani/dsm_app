<?php
/** DealerContact.gs — kontak dealer (ajukan, review, edit langsung, bulk Super Admin). */
require_once __DIR__ . '/common.php';

function saveContactState_(array $state): void {
    $state['overrides'] = js_obj($state['overrides'] ?? []);
    $id = writeJson_('dealer-contacts-' . (int) (microtime(true) * 1000), $state);
    prop_set_('DEALER_CONTACT_STATE', $id);
}
function contactValues_($c): array { $c = is_array($c) ? $c : []; return ['pic' => js_str(js_or($c['pic'] ?? null, '')), 'hp' => js_str(js_or($c['hp'] ?? null, '')), 'alamat' => js_str(js_or($c['alamat'] ?? null, ''))]; }
function contactSame_($a, $b): bool { return js_stringify($a) === js_stringify($b); }
function contactRegion_(array $db, $c): string { $p = js_find($db['meta']['people'], fn($p) => $p['nik'] === $c['nik']); return js_str(js_or($p['wilayah'] ?? null, '')); }
function contactCanReview_(array $db, array $u, $c): bool { return !!$c && ($u['role'] === 'SUPER ADMIN' || ($u['role'] === 'ADMIN' && in_array(contactRegion_($db, $c), $u['adminRegions'] ?? [], true))); }
function contactCanSubmit_(array $u, $c): bool { return !!$c && (($u['role'] === 'SALES' && $c['nik'] === $u['nik']) || ($u['role'] === 'TELEMARKETING' && tmHasDealer_(tmLivePairs_($u['nik']), (string)$c['id']))); }
function contactRevision_(array $db, $c): string { return hash_(js_stringify(['values' => contactValues_($c), 'owner' => $c['nik'], 'region' => contactRegion_($db, $c)])); }
function contactPlain_($v): string { return preg_replace('/\D/', '', $v); }
function contactHpOk_(string $hp): bool { return (bool) preg_match('/^[+0-9 ()-]{8,25}$/D', $hp); }

function apiDealerContact($token, $id = null) {
    $db = snapshot_(); $u = guard_($token, false, false, $db); $c = $db['meta']['customers'][js_str($id)] ?? null;
    if (!$c || !(contactCanSubmit_($u, $c) || in_array($c['nik'], PortalCore::scope($db['meta']['people'], $u['nik']), true) || contactCanReview_($db, $u, $c))) fail_('Dealer di luar cakupan.');
    $state = contactState_();
    $rq = array_values(array_filter($state['requests'], fn($r) => $r['dealer'] === $id && ($r['actor'] === $u['nik'] || contactCanReview_($db, $u, $c))));
    return ['values' => contactValues_($c), 'canSubmit' => contactCanSubmit_($u, $c), 'canEdit' => contactCanReview_($db, $u, $c), 'revision' => contactRevision_($db, $c), 'requests' => array_reverse(array_slice($rq, -20))];
}
function apiSubmitDealerContact($token, $input = null) {
    demoAssertWritable_($token); $input = is_array($input) ? $input : [];
    return with_lock_(function () use ($token, $input) {
        demoAssertWritable_($token);
        $db = snapshot_(); $u = guard_($token, false, false, $db); $c = $db['meta']['customers'][js_str($input['dealer'] ?? null)] ?? null;
        if (!contactCanSubmit_($u, $c)) fail_('Hanya Sales pemegang dealer yang dapat mengajukan.');
        $state = contactState_();
        foreach ($state['requests'] as $r) if ($r['dealer'] === $c['id'] && $r['status'] === 'PENDING') fail_('Dealer masih memiliki pengajuan menunggu persetujuan.');
        $before = contactValues_($c);
        if (!contactSame_($before, $input['before'] ?? null)) fail_('Kontak berubah. Buka ulang detail dealer.');
        $after = ['pic' => $u['role'] === 'TELEMARKETING' ? $before['pic'] : text_($input['pic'] ?? null), 'hp' => text_($input['hp'] ?? null), 'alamat' => text_($input['alamat'] ?? null)]; $reason = text_($input['reason'] ?? null);
        if (mb_strlen($after['pic']) > 120 || mb_strlen($after['alamat']) > 1000 || $after['alamat'] === '' || ($after['pic'] === '' && $u['role'] !== 'TELEMARKETING')) fail_('Isi nama PIC (maks.120) dan alamat (maks.1000).');
        $dg = strlen(contactPlain_($after['hp']));
        if (!contactHpOk_($after['hp']) || $dg < 8 || $dg > 15) fail_('Nomor HP harus 8–15 digit.');
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) fail_('Isi alasan 5–500 karakter.');
        if (contactSame_($before, $after)) fail_('Belum ada perubahan.');
        $p = js_find($db['meta']['people'], fn($p) => $p['nik'] === $u['nik']);
        $row = ['id' => uuid_(), 'dealer' => $c['id'], 'nama' => $c['nama'], 'actor' => $u['nik'], 'actorName' => js_or($u['nama'] ?? null, js_or($p['nama'] ?? null, $u['nik'])), 'owner' => $c['nik'], 'region' => contactRegion_($db, $c), 'before' => $before, 'after' => $after, 'reason' => $reason, 'status' => 'PENDING', 'created' => iso_now()];
        $state['requests'][] = $row; saveContactState_($state); audit_($u['nik'], 'CONTACT_SUBMIT', $row['id'] . ' ' . $c['id']);
        return ['id' => $row['id']];
    });
}
function apiContactRequests($token, $status = null) {
    $db = snapshot_(); $u = adminGuard_($token, $db); $out = [];
    foreach (contactState_()['requests'] as $r) {
        $c = $db['meta']['customers'][$r['dealer']] ?? null;
        if (!(contactCanReview_($db, $u, $c) && (!truthy($status) || $status === 'ALL' || $r['status'] === $status))) continue;
        $stale = !$c || $r['owner'] !== $c['nik'] || $r['region'] !== contactRegion_($db, $c) || !contactSame_($r['before'], contactValues_($c));
        $out[] = array_merge($r, ['stale' => $r['status'] === 'PENDING' && $stale, 'current' => contactValues_($c ?? [])]);
    }
    return array_reverse($out);
}
function apiReviewDealerContacts($token, $ids = null, $decision = null, $reason = null) {
    if (!is_array($ids) || !$ids || count($ids) > 100 || count(array_unique(array_map('js_str', $ids))) !== count($ids)) fail_('Pilih 1–100 pengajuan unik.');
    if (!in_array($decision, ['APPROVED', 'REJECTED'], true)) fail_('Keputusan tidak valid.');
    $reason = text_($reason);
    if ($decision === 'REJECTED' && (mb_strlen($reason) < 5 || mb_strlen($reason) > 500)) fail_('Alasan penolakan wajib 5–500 karakter.');
    return with_lock_(function () use ($token, $ids, $decision, $reason) {
        $db = snapshot_(); $u = adminGuard_($token, $db); $state = contactState_(); $ix = [];
        foreach ($ids as $id) {
            $k = null; foreach ($state['requests'] as $i => $r) if ($r['id'] === $id) { $k = $i; break; }
            $r = $k === null ? null : $state['requests'][$k];
            if (!$r || !contactCanReview_($db, $u, $db['meta']['customers'][$r['dealer']] ?? null)) fail_('Pengajuan di luar kewenangan.');
            if ($r['status'] !== 'PENDING') fail_('Pengajuan sudah diproses. Perbarui daftar.');
            $c = $db['meta']['customers'][$r['dealer']];
            if ($decision === 'APPROVED' && ($r['owner'] !== $c['nik'] || $r['region'] !== contactRegion_($db, $c) || !contactSame_($r['before'], contactValues_($c)))) fail_('Data berubah: ' . $r['nama'] . '. Periksa ulang dan keluarkan dari pilihan.');
            $ix[] = $k;
        }
        foreach ($ix as $k) {
            $r = &$state['requests'][$k]; $r['status'] = $decision; $r['reviewed'] = iso_now(); $r['reviewer'] = $u['nik']; $r['reviewReason'] = $reason;
            if ($decision === 'APPROVED') $state['overrides'][$r['dealer']] = $r['after'];
            unset($r);
        }
        saveContactState_($state); audit_($u['nik'], 'CONTACT_' . $decision, implode(',', $ids));
        return ['count' => count($ix)];
    });
}
function validatedContactUpdate_($input, array $before): array {
    $input = is_array($input) ? $input : []; $after = [];
    foreach (['pic', 'hp', 'alamat'] as $k) $after[$k] = js_or(text_($input[$k] ?? null), $before[$k]);
    if (mb_strlen($after['pic']) > 120 || mb_strlen($after['alamat']) > 1000) fail_('Nama PIC maksimal 120, alamat maksimal 1000 karakter.');
    if ($after['hp'] !== '' && !contactHpOk_($after['hp'])) fail_('Format nomor HP tidak valid.');
    $dg = strlen(contactPlain_($after['hp']));
    if ($after['hp'] !== '' && ($dg < 8 || $dg > 15)) fail_('Nomor HP harus 8–15 digit.');
    return $after;
}
function adminContactRecord_(array $db, array $u, $c, array $after, string $reason, string $method): array {
    $p = js_find($db['meta']['people'], fn($p) => $p['nik'] === $u['nik']);
    return ['id' => uuid_(), 'dealer' => $c['id'], 'nama' => $c['nama'], 'actor' => $u['nik'], 'actorName' => js_or($u['nama'] ?? null, js_or($p['nama'] ?? null, $u['nik'])), 'owner' => $c['nik'], 'region' => contactRegion_($db, $c), 'before' => contactValues_($c), 'after' => $after, 'reason' => $reason, 'status' => 'APPROVED', 'created' => iso_now(), 'reviewed' => iso_now(), 'reviewer' => $u['nik'], 'reviewReason' => $method, 'method' => $method];
}
function apiSaveDealerContact($token, $input = null) {
    $input = is_array($input) ? $input : [];
    return with_lock_(function () use ($token, $input) {
        $db = snapshot_(); $u = adminGuard_($token, $db); $c = $db['meta']['customers'][js_str($input['dealer'] ?? null)] ?? null;
        if (!contactCanReview_($db, $u, $c)) fail_('Dealer di luar wilayah tugas.');
        if (($input['revision'] ?? null) !== contactRevision_($db, $c)) fail_('Kontak/pemegang dealer berubah. Buka ulang detail dealer.');
        $after = validatedContactUpdate_($input, contactValues_($c)); $reason = text_($input['reason'] ?? null);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) fail_('Isi alasan 5–500 karakter.');
        if (contactSame_($after, contactValues_($c))) fail_('Tidak ada perubahan.');
        $state = contactState_(); $row = adminContactRecord_($db, $u, $c, $after, $reason, 'ADMIN_DIRECT');
        $state['requests'][] = $row; $state['overrides'][$c['id']] = $after; saveContactState_($state);
        audit_($u['nik'], 'CONTACT_ADMIN_EDIT', $row['id'] . ' ' . $c['id']);
        return ['id' => $row['id']];
    });
}
const CONTACT_BULK_HEADERS = ['KODE CUSTOMER', 'NAMA DEALER', 'REGION', 'NIK SALES', 'NAMA PIC', 'NO HP', 'ALAMAT', 'VERSI KONTAK'];
function apiExportDealerContacts($token) {
    $db = snapshot_(); guard_($token, true, false, $db);
    $cs = js_sort(array_values($db['meta']['customers']), fn($a, $b) => js_locale_compare($a['nama'], $b['nama']));
    return ['headers' => CONTACT_BULK_HEADERS, 'rows' => array_map(fn($c) => [$c['id'], $c['nama'], contactRegion_($db, $c), $c['nik'], js_or($c['pic'] ?? null, ''), js_or($c['hp'] ?? null, ''), js_or($c['alamat'] ?? null, ''), contactRevision_($db, $c)], $cs)];
}
function contactBulkRows_($file): array {
    if (!($file instanceof Blob) || $file->size() === 0 || $file->size() > 5 * 1024 * 1024) fail_('Pilih XLSX/CSV maksimal 5 MB.');
    if (preg_match('/\.csv$/i', $file->getName())) {
        $s = preg_replace('/^\x{FEFF}/u', '', $file->getDataAsString('UTF-8'));
        $first = preg_split('/\r?\n/', $s)[0];
        $grid = parseCsv_($s, str_contains($first, ';') ? ';' : ',');
    } elseif (preg_match('/\.xlsx$/i', $file->getName())) {
        $pick = xlsx_pick_single_($file->getBytes(), ['Kontak_Dealer']);
        if ($pick === null) fail_('Gunakan sheet Kontak_Dealer atau satu sheet saja.');
        $grid = xlsx_grid_($file->getBytes(), [$pick], true)['grid'];
    } else fail_('Gunakan XLSX atau CSV.');
    $heads = array_map('text_', array_shift($grid) ?? []);
    foreach (['KODE CUSTOMER', 'NAMA PIC', 'NO HP', 'ALAMAT', 'VERSI KONTAK'] as $k) if (count(array_keys($heads, $k, true)) !== 1) fail_('Kolom wajib unik: ' . $k);
    $out = [];
    foreach ($grid as $r) {
        if (!array_filter($r, fn($v) => text_($v) !== '')) continue;
        $o = []; foreach ($heads as $i => $h) $o[$h] = text_($r[$i] ?? null); $out[] = $o;
    }
    return $out;
}
function validateContactBulk_($rows, array $db): array {
    if (!is_array($rows) || !$rows || count($rows) > 500) fail_('Isi 1–500 dealer per file.');
    $seen = []; $out = [];
    foreach (array_values($rows) as $i => $r) {
        $id = text_($r['KODE CUSTOMER'] ?? null); $c = $db['meta']['customers'][$id] ?? null;
        if (!$c || isset($seen[$id])) fail_('Kode dealer tidak ditemukan/duplikat di baris ' . ($i + 2));
        $seen[$id] = 1;
        if (($r['VERSI KONTAK'] ?? null) !== contactRevision_($db, $c)) fail_('Data berubah atau versi tidak valid: ' . $c['nama'] . '. Export ulang.');
        $before = contactValues_($c); $after = validatedContactUpdate_(['pic' => $r['NAMA PIC'] ?? null, 'hp' => $r['NO HP'] ?? null, 'alamat' => $r['ALAMAT'] ?? null], $before);
        $out[] = ['dealer' => $id, 'nama' => $c['nama'], 'region' => contactRegion_($db, $c), 'revision' => contactRevision_($db, $c), 'before' => $before, 'after' => $after, 'changed' => !contactSame_($before, $after)];
    }
    return $out;
}
function apiStageContactBulk($form) {
    $db = snapshot_(); $u = guard_($form['token'] ?? null, true, false, $db); $rows = contactBulkRows_($form['file'] ?? null); $preview = validateContactBulk_($rows, $db); $id = uuid_();
    prop_set_('CONTACT_BULK_' . $id, writeJson_('contact-bulk-preview-' . $id, ['actor' => $u['nik'], 'created' => (int) (microtime(true) * 1000), 'rows' => $rows]));
    return ['id' => $id, 'rows' => $preview, 'total' => count($preview), 'changed' => count(array_filter($preview, fn($r) => $r['changed']))];
}
function apiCommitContactBulk($token, $id = null, $reason = null) {
    $reason = text_($reason);
    if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) fail_('Isi alasan 5–500 karakter.');
    return with_lock_(function () use ($token, $id, $reason) {
        $db = snapshot_(); $u = guard_($token, true, false, $db); $ptr = prop_get_('CONTACT_BULK_' . js_str($id));
        if (!$ptr) fail_('Pratinjau tidak tersedia/sudah dipakai.');
        $stage = readJson_($ptr);
        if ($stage['actor'] !== $u['nik'] || (int) (microtime(true) * 1000) - $stage['created'] > 3600000) fail_('Pratinjau bukan milik akun ini atau kedaluwarsa.');
        $rows = array_values(array_filter(validateContactBulk_($stage['rows'], $db), fn($r) => $r['changed'])); $state = contactState_();
        foreach ($rows as $r) {
            $c = $db['meta']['customers'][$r['dealer']];
            $state['requests'][] = adminContactRecord_($db, $u, $c, $r['after'], $reason, 'SUPER_ADMIN_BULK'); $state['overrides'][$r['dealer']] = $r['after'];
        }
        if ($rows) saveContactState_($state);
        prop_delete_('CONTACT_BULK_' . $id); audit_($u['nik'], 'CONTACT_BULK_REPLACE', $id . ' ' . count($rows));
        return ['updated' => count($rows), 'unchanged' => count($stage['rows']) - count($rows)];
    });
}
