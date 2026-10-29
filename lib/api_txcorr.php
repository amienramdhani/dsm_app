<?php
/**
 * Koreksi Transaksi (Phase 3 langkah 3 + 4): antrean Perlu Ditinjau, pencarian transaksi, koreksi Sales efektif satu transaksi, pencabutan.
 * Hanya Admin/Super Admin (adminGuard_). Data ST mentah, kepemilikan dealer, dan target tidak disentuh;
 * koreksi disimpan di dsm_txcorr dan diterapkan oleh effectiveRows_ saat publish.
 */

const TXC_MARKERS = ['KOREKSI' => 'Koreksi khusus', 'TRANSFER' => 'Dialihkan melalui transfer', 'ST_BERBEDA' => 'Sales ST berbeda', 'KREDIT_TETAP' => 'Kredit tetap ke penjual'];
const TXC_CODES = ['KOREKSI' => 'Sudah dikoreksi', 'TRANSFER' => 'Dialihkan melalui transfer', 'ST_BERBEDA' => 'Sales di file ST berbeda dari pemilik dealer-brand', 'DEALER_BARU' => 'Dealer belum ada di Master Customer', 'RETUR' => 'Retur berbeda Sales dari transaksi asal', 'KREDIT_TETAP' => 'Penjual masuk daftar Kredit tetap ke penjual'];

function txcPerson_(array $meta, $nik): array {
    $nik = (string) $nik; $p = $nik !== '' ? PortalCore::person($meta['people'], $nik) : null;
    return ['nik' => $nik, 'nama' => $p ? text_($p['nama'] ?? '') : '', 'wilayah' => $p ? text_($p['wilayah'] ?? '') : ''];
}
function txcOut_(array $a, array $meta): array {
    $c = $meta['customers'][$a['induk']] ?? null; $m = (string) ($a['marker'] ?? '');
    return ['key' => $a['tx_key'], 'period' => $a['period'], 'date' => $a['tx_date'], 'company' => $a['company'], 'doc' => $a['doc'], 'line' => $a['line'], 'sku' => $a['sku'], 'brand' => $a['brand'],
        'customer' => $a['customer'], 'induk' => $a['induk'], 'dealer' => $c ? text_($c['nama'] ?? '') : '', 'region' => $a['region'], 'qty' => (int) $a['qty'], 'amount' => (float) $a['amount'],
        'raw' => txcPerson_($meta, $a['raw_nik']), 'owner' => txcPerson_($meta, $a['owner_nik']), 'eff' => txcPerson_($meta, $a['eff_nik']),
        'marker' => $m, 'markerLabel' => TXC_MARKERS[$m] ?? '', 'review' => (int) ($a['review'] ?? 0) === 1, 'code' => (string) ($a['code'] ?? ''), 'why' => TXC_CODES[$a['code'] ?? ''] ?? '', 'corrId' => $a['corr_id'] !== null ? (int) $a['corr_id'] : null];
}
function txcLike_(string $q): string { return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%'; }
function txcTotals_(array $snap, string $period): array {
    $q = 0; $a = 0.0; foreach ($snap['cubes'][$period] ?? [] as $r) { $q += $r['q']; $a += $r['a']; }
    return [$q, round($a, 2)];
}
/** NIK Sales yang boleh dilihat/dikelola Admin ini (null = Super Admin: semua). */
function txcScope_(array $db, array $u): ?array {
    if (($u['role'] ?? '') === 'SUPER ADMIN') return null;
    $o = []; foreach ($db['meta']['people'] as $p) if (dbaCanManageNik_($u, $db, (string) $p['nik'])) $o[(string) $p['nik']] = true;
    return $o;
}
function txcCandidates_(array $db, array $u): array {
    $out = [];
    foreach ($db['meta']['people'] as $p) {
        if (!truthy($p['sales'] ?? null) || (function_exists('ptIsVac_') && ptIsVac_($p))) continue;
        if (function_exists('dbaCanManageNik_') && !dbaCanManageNik_($u, $db, (string) $p['nik'])) continue;
        $out[] = ['nik' => (string) $p['nik'], 'nama' => text_($p['nama'] ?? ''), 'role' => text_($p['role'] ?? ''), 'wilayah' => text_($p['wilayah'] ?? ''), 'region' => js_or(text_($p['portfolio'] ?? ''), text_($p['region'] ?? ''))];
    }
    usort($out, fn($a, $b) => strcmp($a['nama'], $b['nama'])); return $out;
}
function txcPeriods_(array $db): array { $k = array_map('strval', array_keys($db['raw'] ?? [])); rsort($k, SORT_STRING); return $k; }

function apiTxCorrBoot($token) {
    $db = snapshot_(); $u = adminGuard_($token, $db); $meta = $db['meta']; $periods = txcPeriods_($db); $cur = jkt_date('Y-m');
    $orph = json_decode((string) prop_get_('TXC_ORPHANS'), true); $orph = is_array($orph) ? $orph : []; $out = [];
    if ($orph) {
        txcSchema_(); $ids = array_values(array_unique(array_map(fn($o) => (int) $o['id'], $orph))); $ph = implode(',', array_fill(0, count($ids), '?'));
        $q = db_()->prepare('SELECT id, tx_ref, period, raw_nik, new_nik, reason, created_at FROM dsm_txcorr WHERE active = 1 AND id IN (' . $ph . ')'); $q->execute($ids); $rows = []; foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[(int) $r['id']] = $r;
        $why = ['FINGERPRINT' => 'Baris berubah sejak dikoreksi', 'NIK_TIDAK_AKTIF' => 'Sales tujuan tidak ada lagi', 'TIDAK_DITEMUKAN' => 'Baris tidak ada di data ST'];
        $sc0 = txcScope_($db, $u);
        foreach ($orph as $o) { $r = $rows[(int) $o['id']] ?? null; if (!$r) continue; if ($sc0 !== null && !isset($sc0[(string) $r['new_nik']])) continue; $out[] = ['id' => (int) $r['id'], 'period' => $r['period'], 'ref' => $r['tx_ref'], 'to' => txcPerson_($meta, $r['new_nik']), 'reason' => $r['reason'], 'at' => $r['created_at'], 'why' => $why[$o['why']] ?? $o['why']]; }
    }
    return ['periods' => $periods, 'period' => in_array($cur, $periods, true) ? $cur : ($periods[0] ?? $cur), 'regions' => array_values(RegionCore::regions($meta)), 'candidates' => txcCandidates_($db, $u), 'orphans' => $out,
        'markers' => TXC_MARKERS, 'superuser' => $u['role'] === 'SUPER ADMIN'];
}

function txcSalesNiks_(array $meta, string $q): array {
    $q = js_upper($q); $o = []; foreach ($meta['people'] as $p) if ($q !== '' && (mb_strpos(js_upper(text_($p['nama'] ?? '')), $q) !== false || js_upper((string) $p['nik']) === $q)) $o[] = (string) $p['nik'];
    return $o;
}

/** Antrean dari tabel pengecualian (server-side filter + paginasi). */
function apiTxCorrList($token, $f = null) {
    $db = snapshot_(); $u = adminGuard_($token, $db); $meta = $db['meta']; $f = is_array($f) ? $f : [];
    $period = PortalCore::month($f['period'] ?? jkt_date('Y-m')); $status = js_upper(text_($f['status'] ?? 'REVIEW')); $region = text_($f['region'] ?? ''); $q = trim(text_($f['q'] ?? ''));
    $size = in_array((int) ($f['size'] ?? 25), [25, 50, 100], true) ? (int) $f['size'] : 25; $page = max(1, (int) ($f['page'] ?? 1));
    txAttrSchema_(); $w = ['period = ?']; $p = [$period];
    if ($status === 'REVIEW') $w[] = 'review = 1'; elseif ($status === 'CORRECTED') $w[] = "marker = 'KOREKSI'"; elseif ($status === 'KEEP') $w[] = "marker = 'KREDIT_TETAP'";
    if ($region !== '') { $w[] = 'region = ?'; $p[] = $region; }
    $sc = txcScope_($db, $u);
    if ($sc !== null) { if (!$sc) $w[] = '1 = 0'; else { $sk = array_keys($sc); $ph = implode(',', array_fill(0, count($sk), '?')); $w[] = "(raw_nik IN ($ph) OR eff_nik IN ($ph) OR owner_nik IN ($ph))"; $p = array_merge($p, $sk, $sk, $sk); } }
    if ($q !== '') {
        $like = txcLike_($q); $c = ['doc LIKE ?', 'line LIKE ?', 'sku LIKE ?', 'customer LIKE ?', 'induk LIKE ?']; $pp = [$like, $like, $like, $like, $like];
        $dn = []; foreach ($meta['customers'] as $id => $cu) if (mb_strpos(js_upper(text_($cu['nama'] ?? '')), js_upper($q)) !== false) $dn[] = (string) $id;
        if ($dn) { $dn = array_slice($dn, 0, 200); $c[] = 'induk IN (' . implode(',', array_fill(0, count($dn), '?')) . ')'; $pp = array_merge($pp, $dn); }
        $sn = txcSalesNiks_($meta, $q);
        if ($sn) { $ph = implode(',', array_fill(0, count($sn), '?')); $c[] = 'raw_nik IN (' . $ph . ')'; $c[] = 'eff_nik IN (' . $ph . ')'; $pp = array_merge($pp, $sn, $sn); }
        $w[] = '(' . implode(' OR ', $c) . ')'; $p = array_merge($p, $pp);
    }
    $where = implode(' AND ', $w); $cnt = db_()->prepare('SELECT COUNT(*) FROM dsm_attr WHERE ' . $where); $cnt->execute($p); $total = (int) $cnt->fetchColumn();
    $st = db_()->prepare('SELECT * FROM dsm_attr WHERE ' . $where . ' ORDER BY tx_date DESC, doc, tx_key LIMIT ' . $size . ' OFFSET ' . (($page - 1) * $size)); $st->execute($p);
    $items = array_map(fn($a) => txcOut_($a, $meta), $st->fetchAll(PDO::FETCH_ASSOC));
    return ['items' => $items, 'total' => $total, 'page' => $page, 'size' => $size, 'pages' => max(1, (int) ceil($total / $size))];
}

/** Cari transaksi mana pun pada satu periode (untuk koreksi yang tidak ada di antrean). Maksimal 50 hasil. */
function apiTxCorrSearch($token, $f = null) {
    $db = snapshot_(); $u = adminGuard_($token, $db); $meta = $db['meta']; $f = is_array($f) ? $f : [];
    $period = PortalCore::month($f['period'] ?? jkt_date('Y-m')); $q = js_upper(trim(text_($f['q'] ?? ''))); $region = text_($f['region'] ?? '');
    if (mb_strlen($q) < 3) fail_('Ketik minimal 3 karakter (invoice, kode dealer, nama dealer, SKU, atau Sales).');
    if (!isset($db['raw'][$period])) return ['items' => [], 'total' => 0, 'capped' => false];
    @ini_set('memory_limit', '1536M');
    $rows = txPrepRows_($meta, dataRowsUnscoped_((string) $db['raw'][$period])); $keys = txKeys_($rows); $sn = txcSalesNiks_($meta, $q); $hit = []; $total = 0; $sc = txcScope_($db, $u);
    foreach ($rows as $i => $t) {
        if ($region !== '' && ($t['region'] ?? '') !== $region) continue;
        if ($sc !== null && !isset($sc[(string) $t['nik']])) continue;
        $cu = $meta['customers'][$t['customer']] ?? null;
        $hay = js_upper(($t['doc'] ?? '') . '|' . ($t['line'] ?? '') . '|' . ($t['sku'] ?? '') . '|' . ($t['uniq'] ?? $t['customer']) . '|' . ($t['customer'] ?? '') . '|' . ($cu ? text_($cu['nama'] ?? '') : ''));
        if (mb_strpos($hay, $q) === false && !in_array((string) $t['nik'], $sn, true)) continue;
        $total++; if (count($hit) < 50) $hit[] = $i;
    }
    $cur = []; if ($hit) { txAttrSchema_(); $ks = array_map(fn($i) => $keys[$i]['key'], $hit); $st = db_()->prepare('SELECT * FROM dsm_attr WHERE tx_key IN (' . implode(',', array_fill(0, count($ks), '?')) . ')'); $st->execute($ks); foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) $cur[$a['tx_key']] = $a; }
    $prods = $meta['products']; $items = [];
    foreach ($hit as $i) {
        $t = $rows[$i]; $k = $keys[$i]['key'];
        $a = $cur[$k] ?? ['tx_key' => $k, 'period' => $period, 'tx_date' => $t['date'], 'company' => $t['company'] ?? '', 'doc' => $t['doc'] ?? '', 'line' => $t['line'] ?? '', 'sku' => $t['sku'] ?? '', 'brand' => js_upper(text_($prods[$t['sku']]['brand'] ?? '')),
            'customer' => $t['uniq'] ?? $t['customer'], 'induk' => $t['customer'], 'region' => $t['region'] ?? '', 'qty' => $t['qty'], 'amount' => $t['amount'], 'raw_nik' => $t['nik'], 'owner_nik' => $t['nik'], 'eff_nik' => $t['nik'], 'marker' => '', 'review' => 0, 'code' => '', 'corr_id' => null];
        $items[] = txcOut_($a, $meta);
    }
    return ['items' => $items, 'total' => $total, 'capped' => $total > 50];
}

function apiTxCorrHistory($token, $key) {
    $db = snapshot_(); adminGuard_($token, $db); $key = text_($key); if (!preg_match('/^[0-9a-f]{40}$/', $key)) fail_('Kunci transaksi tidak valid.');
    txcSchema_(); $q = db_()->prepare('SELECT id, raw_nik, new_nik, reason, actor, created_at, active, revoked_by, revoked_at, revoke_reason FROM dsm_txcorr WHERE tx_key = ? ORDER BY id DESC LIMIT 20'); $q->execute([$key]); $out = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $out[] = ['id' => (int) $r['id'], 'from' => txcPerson_($db['meta'], $r['raw_nik']), 'to' => txcPerson_($db['meta'], $r['new_nik']), 'reason' => $r['reason'], 'actor' => txcPerson_($db['meta'], $r['actor'])['nama'] ?: $r['actor'], 'at' => $r['created_at'], 'active' => (int) $r['active'] === 1, 'revokedBy' => $r['revoked_by'], 'revokedAt' => $r['revoked_at'], 'revokeReason' => $r['revoke_reason']];
    return ['items' => $out];
}

/** Publish ulang + pengaman total company; bila total berubah, dikembalikan oleh $undo dan dipublish ulang. */
function txcRepublish_(array $db, array $u, string $period, string $reason, callable $undo): void {
    $before = txcTotals_($db, $period); $meta2 = meta_(); validateConfigChange_($db, $meta2); $published = false;
    try {
        publish_($meta2, $db['raw'], $db['coverage'], $u['nik'], $reason); $published = true;
        $after = txcTotals_(snapshot_(), $period);
        if ($before[0] !== $after[0] || abs($before[1] - $after[1]) > 0.01) fail_('Total company periode ' . $period . ' berubah (' . $before[0] . ' unit menjadi ' . $after[0] . '). Perubahan dibatalkan.');
    } catch (Throwable $e) {
        $undo();
        if ($published) { try { $m = meta_(); publish_($m, $db['raw'], $db['coverage'], $u['nik'], 'PEMULIHAN KOREKSI · ' . $reason); } catch (Throwable $e2) { error_log('[dsm] pemulihan koreksi gagal: ' . $e2->getMessage()); } }
        throw $e;
    }
}

function apiTxCorrSave($token, $period, $key, $newNik, $reason = null) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    $period = PortalCore::month($period); $key = text_($key); $newNik = text_($newNik);
    if (!preg_match('/^[0-9a-f]{40}$/', $key)) fail_('Kunci transaksi tidak valid.');
    return with_lock_(function () use ($token, $period, $key, $newNik, $reason) {
        @ini_set('memory_limit', '2560M');
        $db = snapshot_(); $u = adminGuard_($token, $db); $meta = $db['meta']; assertPeriodOpen_($period);
        if (!isset($db['raw'][$period])) fail_('Periode ini tidak punya data ST.');
        $rows = txPrepRows_($meta, dataRowsUnscoped_((string) $db['raw'][$period])); $keys = txKeys_($rows); $idx = null;
        foreach ($keys as $i => $k) if ($k['key'] === $key) { $idx = $i; break; }
        if ($idx === null) fail_('Transaksi tidak ditemukan pada data ST periode ini.');
        $t = $rows[$idx]; $k = $keys[$idx]; unset($rows);
        $p = PortalCore::person($meta['people'], $newNik);
        if (!$p || !truthy($p['sales'] ?? null) || (function_exists('ptIsVac_') && ptIsVac_($p))) fail_('Sales tujuan tidak valid.');
        txAttrSchema_(); txcSchema_();
        $q = db_()->prepare('SELECT eff_nik FROM dsm_attr WHERE tx_key = ?'); $q->execute([$key]); $curEff = $q->fetchColumn(); $raw = (string) $t['nik']; $eff = $curEff !== false ? (string) $curEff : $raw;
        if ($newNik === $eff) fail_('Sales tujuan sama dengan Sales efektif saat ini.');
        if (!dbaCanManageNik_($u, $db, $eff) || !dbaCanManageNik_($u, $db, $newNik)) fail_('Sales sumber atau tujuan di luar kewenangan Admin.');
        $pdo = db_(); $now = iso_now(); $deact = [];
        $s = $pdo->prepare('SELECT id FROM dsm_txcorr WHERE tx_key = ? AND active = 1'); $s->execute([$key]); foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $id) $deact[] = (int) $id;
        checkpoint_($u['nik'], 'Koreksi transaksi ' . $k['ref'] . ' · ' . $reason);
        $newId = 0;
        $undo = function () use ($pdo, &$newId, $deact) {
            if ($newId) $pdo->prepare('DELETE FROM dsm_txcorr WHERE id = ?')->execute([$newId]);
            foreach ($deact as $id) $pdo->prepare("UPDATE dsm_txcorr SET active = 1, revoked_by = '', revoked_at = '', revoke_reason = '' WHERE id = ?")->execute([$id]);
        };
        try {
            if ($deact) $pdo->prepare("UPDATE dsm_txcorr SET active = 0, revoked_by = ?, revoked_at = ?, revoke_reason = 'DIGANTI KOREKSI BARU' WHERE tx_key = ? AND active = 1")->execute([$u['nik'], $now, $key]);
            $pdo->prepare('INSERT INTO dsm_txcorr (tx_key, tx_ref, period, raw_nik, new_nik, reason, actor, created_at, fp, active) VALUES (?,?,?,?,?,?,?,?,?,1)')->execute([$key, mb_substr($k['ref'], 0, 400), $period, $raw, $newNik, mb_substr($reason, 0, 500), $u['nik'], $now, $k['fp']]);
            $newId = (int) $pdo->lastInsertId();
            txcRepublish_($db, $u, $period, 'KOREKSI_TRANSAKSI ' . $k['ref'] . ' · ' . $reason, $undo);
        } catch (Throwable $e) { if ($newId) $undo(); throw $e; }
        audit_($u['nik'], 'TX_CORRECTION', $k['ref'] . ' · ' . $eff . ' -> ' . $newNik . ' (raw ' . $raw . ') · ' . $reason);
        return ['ok' => true, 'id' => $newId, 'key' => $key, 'from' => txcPerson_($meta, $eff), 'to' => txcPerson_($meta, $newNik)];
    });
}

function apiTxCorrRevoke($token, $id, $reason = null) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan pencabutan minimal 5 karakter.'); $id = (int) $id;
    return with_lock_(function () use ($token, $id, $reason) {
        @ini_set('memory_limit', '2560M');
        $db = snapshot_(); $u = adminGuard_($token, $db); txcSchema_(); $pdo = db_();
        $q = $pdo->prepare('SELECT * FROM dsm_txcorr WHERE id = ? AND active = 1'); $q->execute([$id]); $r = $q->fetch(PDO::FETCH_ASSOC); if (!$r) fail_('Koreksi tidak ditemukan atau sudah dicabut.');
        assertPeriodOpen_($r['period']);
        if (!dbaCanManageNik_($u, $db, (string) $r['new_nik'])) fail_('Koreksi ini di luar kewenangan Admin.');
        checkpoint_($u['nik'], 'Cabut koreksi transaksi #' . $id . ' · ' . $reason);
        $pdo->prepare('UPDATE dsm_txcorr SET active = 0, revoked_by = ?, revoked_at = ?, revoke_reason = ? WHERE id = ?')->execute([$u['nik'], iso_now(), mb_substr($reason, 0, 500), $id]);
        $undo = function () use ($pdo, $id) { $pdo->prepare("UPDATE dsm_txcorr SET active = 1, revoked_by = '', revoked_at = '', revoke_reason = '' WHERE id = ?")->execute([$id]); };
        try { txcRepublish_($db, $u, $r['period'], 'CABUT_KOREKSI #' . $id . ' · ' . $reason, $undo); } catch (Throwable $e) { throw $e; }
        audit_($u['nik'], 'TX_CORRECTION_REVOKE', '#' . $id . ' ' . $r['tx_ref'] . ' · ' . $reason);
        return ['ok' => true, 'id' => $id];
    });
}
