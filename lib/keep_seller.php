<?php
/**
 * Kredit tetap ke penjual (P1).
 *
 * Penjual yang masuk daftar ini TIDAK dialihkan ke pemilik dealer-brand oleh resolver Effective Sales:
 * transaksinya tetap atas NIK penjual di file ST. Daftar dikelola Super Admin, berlaku per periode (bulan),
 * dengan alasan wajib dan riwayat. Dua jenis aturan:
 *   - ROLE   : seluruh orang dengan role tersebut (default HEAD OF SALES dan BOARD OF DIRECTOR).
 *   - PERSON : satu NIK tertentu (mis. RGM yang pegang beberapa region). PERSON didahulukan atas ROLE.
 * Setelan region kartu (region_mode/region_value) disimpan di sini dan dipakai paket P2:
 *   WILAYAH = ikut wilayah penjual, PEMILIK = ikut region pemilik dealer, TERTENTU = region pilihan.
 * Data ST mentah dan kepemilikan dealer tidak disentuh. Hasil dihitung ulang tiap publish.
 */

const KS_ROLES = ['HEAD OF SALES', 'BOARD OF DIRECTOR', 'CHIEF OPERATING OFFICER', 'CHIEF COMMERCIAL OFFICER', 'RGM', 'ASM'];
const KS_MODES = ['WILAYAH', 'PEMILIK', 'TERTENTU'];

function ksSchema_(): void {
    static $done = false; if ($done) return;
    db_()->exec("CREATE TABLE IF NOT EXISTS dsm_keepseller (id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(10) NOT NULL, subject VARCHAR(100) NOT NULL, period_from CHAR(7) NOT NULL, period_to CHAR(7) NOT NULL DEFAULT '', region_mode VARCHAR(10) NOT NULL DEFAULT 'WILAYAH', region_value VARCHAR(100) NOT NULL DEFAULT '', reason VARCHAR(500) NOT NULL DEFAULT '', actor VARCHAR(100) NOT NULL DEFAULT '', created_at VARCHAR(40) NOT NULL DEFAULT '', active TINYINT NOT NULL DEFAULT 1, revoked_by VARCHAR(100) NOT NULL DEFAULT '', revoked_at VARCHAR(40) NOT NULL DEFAULT '', revoke_reason VARCHAR(500) NOT NULL DEFAULT '', KEY idx_act (active, kind, subject)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
    $done = true;
    // Benih sekali: HOS dan BOD kredit tetap ke penjual, wilayah ikut penjual (GROSS NAS / HEAD OFFICE).
    if (prop_get_('KEEPSELLER_SEEDED') !== '1') {
        $from = substr(function_exists('dbaCutover_') ? dbaCutover_() : '2026-10-01', 0, 7); $now = iso_now();
        $ins = db_()->prepare("INSERT INTO dsm_keepseller (kind, subject, period_from, region_mode, reason, actor, created_at, active) VALUES ('ROLE', ?, ?, 'WILAYAH', 'Aturan awal: penjualan HOS/BOD tetap atas nama penjual', 'SISTEM', ?, 1)");
        foreach (['HEAD OF SALES', 'BOARD OF DIRECTOR'] as $r) $ins->execute([$r, $from, $now]);
        prop_set_('KEEPSELLER_SEEDED', '1');
    }
}

/** Aturan aktif (cache per request). $reset=true setelah menulis. */
function ksRules_(bool $reset = false): array {
    static $cache = null; if ($reset) $cache = null;
    if ($cache !== null) return $cache;
    try { ksSchema_(); $q = db_()->query('SELECT id, kind, subject, period_from, period_to, region_mode, region_value FROM dsm_keepseller WHERE active = 1 ORDER BY id'); $cache = $q->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { $cache = []; }
    return $cache;
}

function ksCovers_(array $r, string $period): bool {
    return $r['period_from'] <= $period && ($r['period_to'] === '' || $period < $r['period_to']);
}

/**
 * Aturan yang berlaku untuk NIK ini pada periode (YYYY-MM), atau null.
 * PERSON didahulukan atas ROLE. Nik kosong atau tidak dikenal -> null.
 */
function keepSellerRule_(array $meta, string $nik, string $period): ?array {
    if ($nik === '' || $period === '') return null;
    $rules = ksRules_(); if (!$rules) return null;
    $role = null;
    foreach ($rules as $r) if ($r['kind'] === 'PERSON' && $r['subject'] === $nik && ksCovers_($r, $period)) return $r;
    $p = PortalCore::person($meta['people'] ?? [], $nik);
    if ($p) $role = (string) ($p['role'] ?? '');
    if ($role === null || $role === '') return null;
    foreach ($rules as $r) if ($r['kind'] === 'ROLE' && $r['subject'] === $role && ksCovers_($r, $period)) return $r;
    return null;
}
function keepSellerCheck_(array $meta, string $nik, string $period): bool { return keepSellerRule_($meta, $nik, $period) !== null; }

// ----------------------------------------------------------------- API (khusus Super Admin)

function ksGuard_($token, ?array $db = null): array {
    $db = $db ?: snapshot_(); $u = guard_($token, true, false, $db);
    if (($u['role'] ?? '') !== 'SUPER ADMIN') fail_('Khusus Super Admin.');
    return $u;
}
function ksOut_(array $r, array $meta): array {
    $p = $r['kind'] === 'PERSON' ? PortalCore::person($meta['people'] ?? [], (string) $r['subject']) : null;
    return ['id' => (int) $r['id'], 'kind' => $r['kind'], 'subject' => $r['subject'], 'nama' => $p ? text_($p['nama'] ?? '') : '', 'role' => $p ? text_($p['role'] ?? '') : '',
        'periodFrom' => $r['period_from'], 'periodTo' => $r['period_to'], 'regionMode' => $r['region_mode'], 'regionValue' => $r['region_value'],
        'reason' => $r['reason'] ?? '', 'actor' => $r['actor'] ?? '', 'at' => $r['created_at'] ?? '', 'active' => (int) ($r['active'] ?? 1) === 1,
        'revokedBy' => $r['revoked_by'] ?? '', 'revokedAt' => $r['revoked_at'] ?? '', 'revokeReason' => $r['revoke_reason'] ?? ''];
}

function apiKeepSellerBoot($token) {
    $db = snapshot_(); $u = ksGuard_($token, $db); $meta = $db['meta']; ksSchema_();
    $rows = db_()->query('SELECT * FROM dsm_keepseller ORDER BY id DESC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC);
    $cand = [];
    foreach ($meta['people'] as $p) {
        if (($p['role'] ?? '') === 'TELEMARKETING') continue;
        $cand[] = ['nik' => (string) $p['nik'], 'nama' => text_($p['nama'] ?? ''), 'role' => text_($p['role'] ?? ''), 'wilayah' => text_($p['wilayah'] ?? '')];
    }
    usort($cand, fn($a, $b) => strcmp($a['nama'], $b['nama']));
    return ['rules' => array_map(fn($r) => ksOut_($r, $meta), $rows), 'roles' => KS_ROLES, 'modes' => KS_MODES, 'candidates' => $cand,
        'regions' => array_values(RegionCore::regions($meta)), 'period' => jkt_date('Y-m')];
}

/** Terapkan perubahan ke semua halaman: publish ulang, dengan pengaman total company. */
function ksRepublish_(array $db, array $u, string $period, string $reason, callable $undo): void {
    if (!function_exists('txcRepublish_')) { $undo(); fail_('Modul republish tidak tersedia.'); }
    txcRepublish_($db, $u, $period, $reason, $undo);
}

function apiKeepSellerSave($token, $spec = null) {
    $spec = is_array($spec) ? $spec : [];
    $kind = js_upper(text_($spec['kind'] ?? '')); $subject = text_($spec['subject'] ?? ''); $mode = js_upper(text_($spec['regionMode'] ?? 'WILAYAH')); $regVal = text_($spec['regionValue'] ?? '');
    $reason = text_($spec['reason'] ?? ''); $from = PortalCore::month($spec['periodFrom'] ?? jkt_date('Y-m'));
    if (!in_array($kind, ['ROLE', 'PERSON'], true)) fail_('Jenis aturan tidak valid.');
    if (!in_array($mode, KS_MODES, true)) fail_('Setelan region tidak valid.');
    if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    if ($kind === 'ROLE') { $subject = js_upper($subject); if (!in_array($subject, KS_ROLES, true)) fail_('Role tidak valid.'); }
    if ($mode !== 'TERTENTU') $regVal = '';
    return with_lock_(function () use ($token, $kind, $subject, $mode, $regVal, $reason, $from) {
        @ini_set('memory_limit', '2560M');
        $db = snapshot_(); $u = ksGuard_($token, $db); $meta = $db['meta']; assertPeriodOpen_($from); ksSchema_();
        if ($kind === 'PERSON' && !PortalCore::person($meta['people'], $subject)) fail_('NIK tidak ditemukan.');
        if ($mode === 'TERTENTU' && !in_array($regVal, RegionCore::regions($meta), true)) fail_('Pilih region yang valid.');
        $pdo = db_(); $now = iso_now();
        $s = $pdo->prepare('SELECT id, period_from, period_to FROM dsm_keepseller WHERE active = 1 AND kind = ? AND subject = ?'); $s->execute([$kind, $subject]); $old = $s->fetchAll(PDO::FETCH_ASSOC);
        checkpoint_($u['nik'], 'Kredit tetap ke penjual ' . $kind . ' ' . $subject . ' · ' . $reason);
        $newId = 0;
        // Aturan lama yang sudah mulai sebelum periode baru hanya DIPOTONG di periode baru (bulan sebelumnya tidak berubah);
        // yang mulai pada/setelah periode baru dinonaktifkan.
        $undo = function () use ($pdo, &$newId, $old) {
            if ($newId) $pdo->prepare('DELETE FROM dsm_keepseller WHERE id = ?')->execute([$newId]);
            foreach ($old as $o) $pdo->prepare("UPDATE dsm_keepseller SET active = 1, period_to = ?, revoked_by = '', revoked_at = '', revoke_reason = '' WHERE id = ?")->execute([$o['period_to'], (int) $o['id']]);
            ksRules_(true);
        };
        try {
            foreach ($old as $o) {
                if ($from > $o['period_from']) $pdo->prepare('UPDATE dsm_keepseller SET period_to = ? WHERE id = ?')->execute([$from, (int) $o['id']]);
                else $pdo->prepare("UPDATE dsm_keepseller SET active = 0, revoked_by = ?, revoked_at = ?, revoke_reason = 'DIGANTI ATURAN BARU' WHERE id = ?")->execute([$u['nik'], $now, (int) $o['id']]);
            }
            $pdo->prepare('INSERT INTO dsm_keepseller (kind, subject, period_from, region_mode, region_value, reason, actor, created_at, active) VALUES (?,?,?,?,?,?,?,?,1)')->execute([$kind, $subject, $from, $mode, $regVal, mb_substr($reason, 0, 500), $u['nik'], $now]);
            $newId = (int) $pdo->lastInsertId(); ksRules_(true);
            ksRepublish_($db, $u, $from, 'KREDIT_TETAP_PENJUAL ' . $kind . ' ' . $subject . ' · ' . $reason, $undo);
        } catch (Throwable $e) { $undo(); throw $e; }
        audit_($u['nik'], 'KEEP_SELLER_SAVE', $kind . ' ' . $subject . ' dari ' . $from . ' · region ' . $mode . ($regVal !== '' ? ' ' . $regVal : '') . ' · ' . $reason);
        return ['ok' => true, 'id' => $newId];
    });
}

function apiKeepSellerRevoke($token, $id, $reason = null, $period = null) {
    $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan pencabutan minimal 5 karakter.'); $id = (int) $id;
    $to = PortalCore::month($period ?? jkt_date('Y-m'));
    return with_lock_(function () use ($token, $id, $reason, $to) {
        @ini_set('memory_limit', '2560M');
        $db = snapshot_(); $u = ksGuard_($token, $db); assertPeriodOpen_($to); ksSchema_(); $pdo = db_();
        $s = $pdo->prepare('SELECT * FROM dsm_keepseller WHERE id = ? AND active = 1'); $s->execute([$id]); $r = $s->fetch(PDO::FETCH_ASSOC);
        if (!$r) fail_('Aturan tidak ditemukan atau sudah dicabut.');
        if ($to <= $r['period_from']) fail_('Periode berakhir harus setelah periode mulai (' . $r['period_from'] . '). Untuk membatalkan penuh, ganti aturan dengan periode mulai yang baru.');
        checkpoint_($u['nik'], 'Cabut kredit tetap ke penjual #' . $id . ' · ' . $reason);
        $undo = function () use ($pdo, $id) { $pdo->prepare("UPDATE dsm_keepseller SET period_to = '', revoked_by = '', revoked_at = '', revoke_reason = '' WHERE id = ?")->execute([$id]); ksRules_(true); };
        try {
            $pdo->prepare('UPDATE dsm_keepseller SET period_to = ?, revoked_by = ?, revoked_at = ?, revoke_reason = ? WHERE id = ?')->execute([$to, $u['nik'], iso_now(), mb_substr($reason, 0, 500), $id]); ksRules_(true);
            ksRepublish_($db, $u, $to, 'CABUT_KREDIT_TETAP_PENJUAL #' . $id . ' · ' . $reason, $undo);
        } catch (Throwable $e) { $undo(); throw $e; }
        audit_($u['nik'], 'KEEP_SELLER_REVOKE', '#' . $id . ' ' . $r['kind'] . ' ' . $r['subject'] . ' berakhir sebelum ' . $to . ' · ' . $reason);
        return ['ok' => true, 'id' => $id];
    });
}
