<?php
/**
 * Riwayat transfer + tombol Batalkan (undo).
 * Prinsip: undo hanya untuk perubahan TERAKHIR pada sheet kepemilikan yang sama.
 * Saat commit, sheet "sebelum" disimpan + hash kondisi "sesudah". Saat undo, bila hash sekarang
 * masih sama (tidak ada perubahan lain sesudahnya) sheet dikembalikan persis, lalu publish ulang.
 */

const UNDO_INDEX = 'TRANSFER_UNDO_INDEX';
const UNDO_KEEP = 15;

function undoSheets_(string $type): array {
    $s = ['Master_Customer', 'Dealer_Assignment_History', DBA_SHEET];
    if ($type === 'PORTFOLIO') { $s[] = 'Target_Input'; $s[] = 'Target_Periode'; $s[] = PT_EXIT_SHEET; }
    return $s;
}
function undoKkRows_(array $niks, string $period): array {
    if (!$niks || !function_exists('kkSchema_')) return [];
    kkSchema_(); $ph = implode(',', array_fill(0, count($niks), '?'));
    $q = db_()->prepare('SELECT nik,period,brand,value,actor,updated_at FROM _kk_target WHERE nik IN(' . $ph . ') AND period>=? ORDER BY nik,period,brand');
    $q->execute(array_merge(array_values($niks), [$period])); return $q->fetchAll(PDO::FETCH_ASSOC);
}
function undoHash_(array $sheets, array $niks = [], string $period = ''): string {
    $h = []; foreach ($sheets as $s) $h[$s] = readGrid_($s);
    $h['_kk'] = $period !== '' ? undoKkRows_($niks, $period) : [];
    return md5(json_encode($h, JSON_UNESCAPED_UNICODE));
}
function undoIndex_(): array { $v = json_decode((string)prop_get_(UNDO_INDEX), true); return is_array($v) ? $v : []; }

/** Dipanggil SETELAH commit sukses (di dalam lock). Tidak boleh menggagalkan commit. */
function undoRegister_(string $type, string $label, array $u, array $beforeGrids, array $opt = []): void {
    try {
        $id = 'UND-' . jkt_date('Ymd') . '-' . strtoupper(substr(str_replace('-', '', uuid_()), 0, 6));
        $niks = array_values($opt['niks'] ?? []); $period = (string)($opt['kkPeriod'] ?? '');
        $sheets = undoSheets_($type);
        $before = []; foreach ($sheets as $s) $before[$s] = $beforeGrids[$s] ?? null;
        $entry = ['id' => $id, 'type' => $type, 'sheets' => $sheets, 'before' => $before, 'kkBefore' => $opt['kkBefore'] ?? [], 'niks' => $niks, 'kkPeriod' => $period,
            'afterHash' => undoHash_($sheets, $niks, $period), 'published' => (bool)($opt['published'] ?? false), 'periods' => $opt['periods'] ?? [],
            'blocked' => (string)($opt['blocked'] ?? '')];
        prop_set_('TRANSFER_UNDO_' . $id, writeJson_('transfer-undo-' . $id, $entry));
        $idx = undoIndex_();
        array_unshift($idx, ['id' => $id, 'type' => $type, 'label' => $label, 'actor' => $u['nik'], 'actorName' => $u['nama'] ?? $u['nik'], 'at' => now_ms(),
            'date' => (string)($opt['date'] ?? ''), 'reason' => (string)($opt['reason'] ?? ''), 'undone' => false]);
        foreach (array_slice($idx, UNDO_KEEP) as $old) prop_delete_('TRANSFER_UNDO_' . $old['id']);
        prop_set_(UNDO_INDEX, json_encode(array_slice($idx, 0, UNDO_KEEP), JSON_UNESCAPED_UNICODE));
    } catch (Throwable $e) { /* best effort */ }
}
function undoLoad_(string $id): array {
    $ptr = prop_get_('TRANSFER_UNDO_' . $id); if (!$ptr) fail_('Data pembatalan tidak tersedia (sudah kedaluwarsa).');
    return readJson_($ptr);
}
function undoStatus_(array $meta, array $u, int $position): array {
    if (!empty($meta['undone'])) return [false, 'Sudah dibatalkan.'];
    if ($u['role'] !== 'SUPER ADMIN' && $meta['actor'] !== $u['nik']) return [false, 'Hanya pelaku atau Super Admin yang dapat membatalkan.'];
    if ($position > 4) return [false, 'Terlalu lama; hanya perubahan terbaru yang dapat dibatalkan.'];
    try { $e = undoLoad_($meta['id']); } catch (Throwable $ex) { return [false, 'Data pembatalan tidak tersedia.']; }
    if ($e['blocked'] !== '') return [false, $e['blocked']];
    if (undoHash_($e['sheets'], $e['niks'], $e['kkPeriod']) !== $e['afterHash']) return [false, 'Sudah ada perubahan lain sesudahnya. Batalkan perubahan yang lebih baru dulu.'];
    return [true, ''];
}

function apiTransferUndoList($token) {
    $db = snapshot_(); $u = adminGuard_($token, $db); $out = [];
    foreach (undoIndex_() as $i => $m) {
        [$ok, $why] = $i < 5 ? undoStatus_($m, $u, $i) : [false, 'Terlalu lama; hanya perubahan terbaru yang dapat dibatalkan.'];
        $out[] = $m + ['undoable' => $ok, 'why' => $why];
    }
    return ['items' => $out];
}

function apiTransferUndo($token, $id, $reason = null) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan pembatalan minimal 5 karakter.');
    return with_lock_(function () use ($token, $id, $reason) {
        $db = snapshot_(); $u = adminGuard_($token, $db); $id = text_($id);
        $pos = null; $meta = null; foreach (undoIndex_() as $i => $m) if ($m['id'] === $id) { $pos = $i; $meta = $m; break; }
        if ($meta === null) fail_('Riwayat tidak ditemukan.');
        [$ok, $why] = undoStatus_($meta, $u, $pos); if (!$ok) fail_($why);
        $e = undoLoad_($id); $cur = []; foreach ($e['sheets'] as $s) $cur[$s] = readGrid_($s);
        $kkNow = undoKkRows_($e['niks'], $e['kkPeriod']);
        checkpoint_($u['nik'], 'Batalkan transfer ' . $id . ' · ' . $reason);
        $restore = function (array $grids, array $kk) use ($e) {
            foreach ($e['sheets'] as $s) { $g = $grids[$s] ?? null; if ($g) restoreGrid_($s, $g); elseif (sheet_exists_($s)) deleteSheet_($s); }
            if ($e['kkPeriod'] !== '' && $e['niks'] && function_exists('kkSchema_')) {
                kkSchema_(); $ph = implode(',', array_fill(0, count($e['niks']), '?'));
                db_()->prepare('DELETE FROM _kk_target WHERE nik IN(' . $ph . ') AND period>=?')->execute(array_merge($e['niks'], [$e['kkPeriod']]));
                $ins = db_()->prepare('REPLACE INTO _kk_target(nik,period,brand,value,actor,updated_at) VALUES(?,?,?,?,?,?)');
                foreach ($kk as $r) $ins->execute([$r['nik'], $r['period'], $r['brand'], $r['value'], $r['actor'], $r['updated_at']]);
                if (function_exists('kkReset_')) kkReset_();
            }
        };
        try {
            $restore($e['before'], $e['kkBefore']);
            if ($e['published']) {
                $meta2 = meta_(); validateConfigChange_($db, $meta2); $parts = null;
                if ($e['type'] === 'PORTFOLIO' && function_exists('ptPartsForPublish_')) foreach ($e['periods'] as $pp) $parts = ptPartsForPublish_($db, $meta2, $pp, $parts);
                publish_($meta2, $db['raw'], $db['coverage'], $u['nik'], 'UNDO ' . $id . ' ' . $reason, null, $parts);
            }
            $idx = undoIndex_(); foreach ($idx as &$m) if ($m['id'] === $id) $m['undone'] = true; unset($m);
            prop_set_(UNDO_INDEX, json_encode($idx, JSON_UNESCAPED_UNICODE));
            audit_($u['nik'], 'TRANSFER_UNDO', $id . ' · ' . $meta['label'] . ' · ' . $reason);
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $ex) { $restore($cur, $kkNow); throw $ex; }
    });
}
