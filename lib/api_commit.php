<?php
/* Komitmen mingguan (tab AKSI). Tanpa persetujuan: ditulis sendiri atau ditetapkan atasan.
   Tabel dsm_commit dibuat otomatis. Status: OPEN, DONE, PARTIAL, NOT. */

function commitSchema_(): void {
    static $done = false; if ($done) return;
    db_()->exec("CREATE TABLE IF NOT EXISTS dsm_commit (id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, wk CHAR(10) NOT NULL, owner_nik VARCHAR(100) NOT NULL, author_nik VARCHAR(100) NOT NULL, body VARCHAR(400) NOT NULL, status VARCHAR(10) NOT NULL DEFAULT 'OPEN', note VARCHAR(400) NOT NULL DEFAULT '', created_ms BIGINT NOT NULL, updated_ms BIGINT NOT NULL, KEY idx_week (wk), KEY idx_owner (owner_nik, wk)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    db_()->exec("CREATE TABLE IF NOT EXISTS dsm_commit_cm (id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, commit_id BIGINT NOT NULL, author_nik VARCHAR(100) NOT NULL, body VARCHAR(400) NOT NULL, created_ms BIGINT NOT NULL, KEY idx_c (commit_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done = true;
}
/** Senin dari minggu yang memuat tanggal (Y-m-d, WIB). */
function commitWeek_(?string $date = null): string {
    $ts = strtotime(($date ?: jkt_date('Y-m-d')) . ' 00:00:00 UTC'); $dow = (int) gmdate('N', $ts);
    return gmdate('Y-m-d', $ts - ($dow - 1) * 86400);
}
function commitUser_($token, bool $write = true): array {
    $u = guard_($token); if ($write && !empty($u['demo'])) fail_('Mode Demo hanya-baca.');
    if (($u['role'] ?? '') === 'TELEMARKETING') fail_('Komitmen mingguan belum tersedia untuk role ini.');
    return $u;
}
function commitScope_(array $db, array $u): array {
    $ids = PortalCore::scope($db['meta']['people'], $u['nik']);
    $lv = PortalCore::lv($u['role'] ?? ''); $me = (string) $u['nik'];
    // Level HOS ke atas: boleh ke semua orang yang levelnya sama atau di bawahnya (BOD/COO/CCO bisa sampai HOS), bukan ke atasnya.
    if ($lv >= 4 && $lv < 7) {
        $by = []; foreach ($db['meta']['people'] as $p) $by[(string) $p['nik']] = $p;
        $ids = array_values(array_filter($ids, fn($n) => (string) $n === $me || PortalCore::lv($by[(string) $n]['role'] ?? '') <= $lv));
    }
    return js_set($ids);
}
function commitRow_(array $r, array $PBY, string $me): array {
    $o = $PBY[(string) $r['owner_nik']] ?? null; $a = $PBY[(string) $r['author_nik']] ?? null;
    return ['id' => (int) $r['id'], 'week' => $r['wk'], 'owner' => (string) $r['owner_nik'], 'ownerName' => $o ? text_($o['nama'] ?? '') : (string) $r['owner_nik'], 'author' => (string) $r['author_nik'], 'authorName' => $a ? text_($a['nama'] ?? '') : (string) $r['author_nik'],
        'text' => $r['body'], 'status' => $r['status'], 'note' => $r['note'], 'mine' => (string) $r['owner_nik'] === $me, 'fromBoss' => (string) $r['author_nik'] !== (string) $r['owner_nik'], 'canEdit' => (string) $r['author_nik'] === $me];
}

function apiCommitBoot($token) {
    $u = commitUser_($token, false); commitSchema_(); $db = snapshot_(); $me = (string) $u['nik'];
    $sc = commitScope_($db, $u); $PBY = []; foreach ($db['meta']['people'] as $p) $PBY[(string) $p['nik']] = $p;
    $wk = commitWeek_(); $last = commitWeek_(gmdate('Y-m-d', strtotime($wk . ' UTC') - 86400));
    $ids = array_keys($sc); if (!$ids) $ids = [$me];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $q = db_()->prepare("SELECT * FROM dsm_commit WHERE wk IN (?,?) AND owner_nik IN ($ph) ORDER BY id");
    $q->execute(array_merge([$wk, $last], array_map('strval', $ids)));
    $rows = array_map(fn($r) => commitRow_($r, $PBY, $me), $q->fetchAll(PDO::FETCH_ASSOC));
    $people = [];
    foreach ($ids as $n) { $p = $PBY[(string) $n] ?? null; if ($p && in_array($p['role'], ['SALES', 'ASM', 'RGM', 'HEAD OF SALES', 'CHIEF OPERATING OFFICER', 'CHIEF COMMERCIAL OFFICER', 'BOARD OF DIRECTOR'], true) && count($people) < 1000) $people[] = ['nik' => (string) $p['nik'], 'nama' => text_($p['nama'] ?? ''), 'role' => $p['role']]; }
    usort($people, fn($a, $b) => strcmp($a['nama'], $b['nama']));
    $cms = [];
    if ($rows) {
        $cids = array_map(fn($r) => $r['id'], $rows); $ph2 = implode(',', array_fill(0, count($cids), '?'));
        $q2 = db_()->prepare("SELECT * FROM dsm_commit_cm WHERE commit_id IN ($ph2) ORDER BY id"); $q2->execute($cids);
        foreach ($q2->fetchAll(PDO::FETCH_ASSOC) as $c) { $a = $PBY[(string) $c['author_nik']] ?? null; $cms[(int) $c['commit_id']][] = ['by' => (string) $c['author_nik'], 'byName' => $a ? text_($a['nama'] ?? '') : (string) $c['author_nik'], 'text' => $c['body'], 'at' => (int) $c['created_ms']]; }
    }
    return ['me' => $me, 'week' => $wk, 'lastWeek' => $last, 'rows' => $rows, 'people' => $people, 'cms' => js_obj($cms)];
}

function apiCommitSave($token, $input) {
    $u = commitUser_($token); commitSchema_(); $input = is_array($input) ? $input : []; $db = snapshot_(); $me = (string) $u['nik'];
    $text = text_($input['text'] ?? ''); $owner = text_($input['owner'] ?? '') ?: $me; $id = (int) ($input['id'] ?? 0);
    if (mb_strlen($text) < 3 || mb_strlen($text) > 400) fail_('Isi komitmen 3–400 karakter.');
    $sc = commitScope_($db, $u); if (!isset($sc[$owner])) fail_('Anda tidak berwenang membuat komitmen untuk orang ini.');
    $now = now_ms();
    if ($id) {
        $q = db_()->prepare('SELECT * FROM dsm_commit WHERE id = ?'); $q->execute([$id]); $r = $q->fetch(PDO::FETCH_ASSOC);
        if (!$r) fail_('Komitmen tidak ditemukan.'); if ((string) $r['author_nik'] !== $me && $u['role'] !== 'SUPER ADMIN') fail_('Hanya pembuat komitmen yang boleh mengubah isinya.');
        db_()->prepare('UPDATE dsm_commit SET body = ?, updated_ms = ? WHERE id = ?')->execute([$text, $now, $id]);
        return true;
    }
    $cnt = db_()->prepare('SELECT COUNT(*) FROM dsm_commit WHERE wk = ? AND owner_nik = ?'); $cnt->execute([commitWeek_(), $owner]);
    if ((int) $cnt->fetchColumn() >= 15) fail_('Maksimal 15 komitmen per orang per minggu.');
    db_()->prepare('INSERT INTO dsm_commit (wk, owner_nik, author_nik, body, status, note, created_ms, updated_ms) VALUES (?,?,?,?,?,?,?,?)')->execute([commitWeek_(), $owner, $me, $text, 'OPEN', '', $now, $now]);
    if ($owner !== $me) commitNotify_([$owner], 'Komitmen baru dari ' . commitName_($db, $me), $text);
    return true;
}

function apiCommitStatus($token, $id, $status, $note = null) {
    $u = commitUser_($token); commitSchema_(); $db = snapshot_(); $me = (string) $u['nik']; $status = js_upper(text_($status)); $note = text_($note);
    if (!in_array($status, ['OPEN', 'DONE', 'PARTIAL', 'NOT'], true)) fail_('Status tidak valid.'); if (mb_strlen($note) > 400) fail_('Catatan maksimal 400 karakter.');
    $q = db_()->prepare('SELECT * FROM dsm_commit WHERE id = ?'); $q->execute([(int) $id]); $r = $q->fetch(PDO::FETCH_ASSOC); if (!$r) fail_('Komitmen tidak ditemukan.');
    $sc = commitScope_($db, $u); if (!isset($sc[(string) $r['owner_nik']])) fail_('Anda tidak berwenang mengubah status komitmen ini.');
    db_()->prepare('UPDATE dsm_commit SET status = ?, note = ?, updated_ms = ? WHERE id = ?')->execute([$status, $note, now_ms(), (int) $id]);
    if ($status !== $r['status'] && $status !== 'OPEN') {
        $lb = ['DONE' => 'Selesai', 'PARTIAL' => 'Sebagian', 'NOT' => 'Belum'][$status] ?? $status; $ow = (string) $r['owner_nik']; $au = (string) $r['author_nik'];
        if ($ow !== $me) commitNotify_([$ow], 'Komitmenmu dinilai: ' . $lb, $r['body']);
        elseif ($au !== $ow) commitNotify_([$au], commitName_($db, $ow) . ' menandai komitmen: ' . $lb, $r['body']);
    }
    return true;
}

function apiCommitDelete($token, $id) {
    $u = commitUser_($token); commitSchema_(); $me = (string) $u['nik'];
    $q = db_()->prepare('SELECT * FROM dsm_commit WHERE id = ?'); $q->execute([(int) $id]); $r = $q->fetch(PDO::FETCH_ASSOC); if (!$r) return true;
    if ((string) $r['author_nik'] !== $me && $u['role'] !== 'SUPER ADMIN') fail_('Hanya pembuat komitmen yang boleh menghapus.');
    if ($r['status'] !== 'OPEN') fail_('Komitmen yang sudah dinilai tidak bisa dihapus.');
    db_()->prepare('DELETE FROM dsm_commit WHERE id = ?')->execute([(int) $id]); db_()->prepare('DELETE FROM dsm_commit_cm WHERE commit_id = ?')->execute([(int) $id]);
    return true;
}

function apiCommitComment($token, $id, $text) {
    $u = commitUser_($token); commitSchema_(); $db = snapshot_(); $me = (string) $u['nik']; $text = text_($text);
    if (mb_strlen($text) < 2 || mb_strlen($text) > 400) fail_('Komentar 2–400 karakter.');
    $q = db_()->prepare('SELECT owner_nik, author_nik FROM dsm_commit WHERE id = ?'); $q->execute([(int) $id]); $cr = $q->fetch(PDO::FETCH_ASSOC); if (!$cr) fail_('Komitmen tidak ditemukan.'); $o = $cr['owner_nik'];
    $sc = commitScope_($db, $u); if (!isset($sc[(string) $o])) fail_('Anda tidak berwenang berkomentar di komitmen ini.');
    db_()->prepare('INSERT INTO dsm_commit_cm (commit_id, author_nik, body, created_ms) VALUES (?,?,?,?)')->execute([(int) $id, $me, $text, now_ms()]);
    $pq = db_()->prepare('SELECT DISTINCT author_nik FROM dsm_commit_cm WHERE commit_id = ?'); $pq->execute([(int) $id]);
    $to = array_merge([(string) $cr['owner_nik'], (string) $cr['author_nik']], array_map('strval', $pq->fetchAll(PDO::FETCH_COLUMN)));
    commitNotify_(array_values(array_filter($to, fn($n) => $n !== $me)), 'Komentar baru pada komitmen', commitName_($db, $me) . ': ' . $text);
    return true;
}

/* ---------- Notifikasi & pengingat ---------- */
function commitName_(array $db, string $nik): string {
    foreach ($db['meta']['people'] as $p) if ((string) $p['nik'] === $nik) return text_($p['nama'] ?? '') ?: $nik;
    return $nik;
}
function commitNotify_(array $niks, string $title, string $body): void {
    try {
        $niks = array_values(array_unique(array_filter(array_map('strval', $niks), fn($n) => $n !== '')));
        if (!$niks || !function_exists('pushOn_') || !pushOn_() || !function_exists('ntRuleOn_') || !ntRuleOn_('KOMITMEN_NOTIF')) return;
        ntAutoSend_('KOMITMEN_NOTIF', mb_substr($title, 0, 110), mb_substr($body, 0, 380), 'beranda', $niks);
    } catch (Throwable $e) { error_log('[dsm] notif komitmen gagal: ' . $e->getMessage()); }
}
/** Pengingat Senin: belum ada komitmen minggu ini; komitmen minggu lalu belum dinilai. Dipanggil aturan KOMITMEN_MINGGUAN. */
function commitReminders_(): int {
    commitSchema_(); $db = snapshot_(); $wk = commitWeek_(); $last = commitWeek_(gmdate('Y-m-d', strtotime($wk . ' UTC') - 86400));
    $field = ['SALES', 'ASM', 'RGM']; $all = array_merge($field, ['HEAD OF SALES', 'CHIEF OPERATING OFFICER', 'CHIEF COMMERCIAL OFFICER', 'BOARD OF DIRECTOR']);
    $act = []; foreach ($db['meta']['people'] as $p) { if (!in_array($p['role'], $all, true)) continue; $a = account_($p['nik']); if ($a && PortalCore::active($a['AKTIF'])) $act[(string) $p['nik']] = $p['role']; }
    $has = []; $q = db_()->prepare('SELECT owner_nik, COUNT(*) FROM dsm_commit WHERE wk = ? GROUP BY owner_nik'); $q->execute([$wk]); foreach ($q->fetchAll(PDO::FETCH_NUM) as $r) $has[(string) $r[0]] = (int) $r[1];
    $own = []; $given = []; $q = db_()->prepare("SELECT owner_nik, author_nik, COUNT(*) FROM dsm_commit WHERE wk = ? AND status = 'OPEN' GROUP BY owner_nik, author_nik"); $q->execute([$last]);
    foreach ($q->fetchAll(PDO::FETCH_NUM) as $r) { $o = (string) $r[0]; $a = (string) $r[1]; $own[$o] = ($own[$o] ?? 0) + (int) $r[2]; if ($a !== $o) $given[$a] = ($given[$a] ?? 0) + (int) $r[2]; }
    $n = 0;
    foreach ($act as $nik => $role) {
        $lines = [];
        if (in_array($role, $field, true) && empty($has[$nik])) $lines[] = 'Belum ada komitmen minggu ini. Tulis komitmenmu di tab AKSI.';
        if (!empty($own[$nik])) $lines[] = $own[$nik] . ' komitmen minggu lalu belum dinilai.';
        if (!empty($given[$nik])) $lines[] = $given[$nik] . ' komitmen yang kamu berikan minggu lalu belum dinilai.';
        if ($lines && ntAutoSend_('KOMITMEN_MINGGUAN', 'Komitmen minggu ini', implode(' ', $lines), 'beranda', [$nik])) $n++;
    }
    return $n;
}
