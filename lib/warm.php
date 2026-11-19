<?php
/**
 * Panaskan cache otomatis setelah publikasi (2026-11-19).
 *  1. warmNote_(): setiap apiDashboard / apiDOS yang bisa di-cache dicatat (akun + argumen) ke tabel dsm_warm.
 *  2. warmRun_(): dipanggil pekerja antrean SETELAH publikasi. Kombinasi yang dipakai 3 hari terakhir dihitung ulang
 *     satu per satu (paling sering dulu) atas nama akun itu, lalu disimpan ke cache — pembuka pertama tidak menunggu.
 * Sesi atas nama user = sesi sekali pakai bertanda 'warm' (seperti sesi bot Telegram): ditolak di api.php, dihapus selesai.
 * Rem: berhenti bila waktu habis, ada antrean publikasi baru, atau snapshot aktif berganti. Darurat: properti WARM_OFF = 1.
 * Telemarketing & Mode Demo tidak dipanaskan.
 */
const DSM_WARM_FNS = ['apiDashboard', 'apiDOS'];
const DSM_WARM_DAYS = 3;
const DSM_WARM_MAX = 200;

function warm_schema_(): void {
    static $done = false; if ($done) return; $done = true;
    db_()->exec("CREATE TABLE IF NOT EXISTS dsm_warm (h CHAR(64) NOT NULL PRIMARY KEY, nik VARCHAR(100) NOT NULL, fn VARCHAR(60) NOT NULL, args TEXT NOT NULL, last_ms BIGINT NOT NULL, hits INT NOT NULL DEFAULT 1, KEY idx_last (last_ms)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
}

/** Catat pemakaian (dipanggil api.php bila kunci cache ada). Gagal dicatat tidak mengganggu request. */
function warmNote_(string $fn, array $args): void {
    $nik = $GLOBALS['DSM_CACHE_NIK'] ?? null; unset($GLOBALS['DSM_CACHE_NIK']); // sekali pakai: tidak terbawa ke catatan berikutnya
    if (!in_array($fn, DSM_WARM_FNS, true)) return;
    if (!is_string($nik) || $nik === '') return;
    if (!empty($GLOBALS['DSM_TM_CONTEXT'])) return;
    try {
        warm_schema_();
        $a = json_encode(array_slice($args, 1), JSON_UNESCAPED_UNICODE); if ($a === false || strlen($a) > 2000) return;
        db_()->prepare('INSERT INTO dsm_warm (h, nik, fn, args, last_ms, hits) VALUES (?,?,?,?,?,1) ON DUPLICATE KEY UPDATE hits = hits + 1, last_ms = VALUES(last_ms)')
            ->execute([hash('sha256', $fn . '|' . $nik . '|' . $a), $nik, $fn, $a, now_ms()]);
    } catch (Throwable $e) { /* hanya optimasi */ }
}

/** Jawaban JSON persis seperti dsm_reply() di api.php. */
function warmJson_(array $data): ?string {
    $j = json_encode(js_norm($data), (JS_JSON_FLAGS & ~JSON_PRESERVE_ZERO_FRACTION) | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    return $j === false ? null : $j;
}

/**
 * Hitung ulang kombinasi yang sering dipakai. $budgetMs = batas waktu total.
 * Mengembalikan ringkasan ['done','skip','fail','stop'] untuk log.
 */
function warmRun_(int $budgetMs): array {
    $out = ['done' => 0, 'skip' => 0, 'fail' => 0, 'stop' => ''];
    try { if (prop_get_('WARM_OFF')) { $out['stop'] = 'WARM_OFF'; return $out; } } catch (Throwable $e) { return $out; }
    $deadline = now_ms() + $budgetMs; $active = prop_get_('ACTIVE'); if (!$active) return $out;
    warm_schema_();
    $st = db_()->prepare('SELECT nik, fn, args FROM dsm_warm WHERE last_ms >= ? ORDER BY hits DESC, last_ms DESC LIMIT ' . (int) DSM_WARM_MAX);
    $st->execute([now_ms() - DSM_WARM_DAYS * 86400000]); $list = $st->fetchAll();
    $roles = []; try { foreach (snapshot_people_() as $p) $roles[(string) $p['nik']] = (string) $p['role']; } catch (Throwable $e) {}
    foreach ($list as $w) {
        if (now_ms() > $deadline) { $out['stop'] = 'waktu habis'; break; }
        if (prop_get_('ACTIVE') !== $active) { $out['stop'] = 'snapshot berganti'; break; }
        if (function_exists('pqQueue_') && pqQueue_()) { $out['stop'] = 'ada antrean publikasi'; break; }
        $nik = (string) $w['nik']; $fn = (string) $w['fn']; $args = json_decode((string) $w['args'], true);
        if (!in_array($fn, DSM_WARM_FNS, true) || !is_array($args) || !function_exists($fn)) { $out['skip']++; continue; }
        if (!isset($roles[$nik]) || $roles[$nik] === 'TELEMARKETING') { $out['skip']++; continue; }
        $a = account_($nik);
        if (!$a || !PortalCore::active($a['AKTIF'] ?? null) || PortalCore::active($a['WAJIB_GANTI'] ?? null)) { $out['skip']++; continue; }
        $token = hash_(uuid_() . uuid_() . now_ms()); $key = 'SESSION_' . hash_($token);
        try {
            session_put_($key, ['nik' => $nik, 'version' => (int) num_($a['VERSI']), 'until' => now_ms() + 300000, 'role' => $roles[$nik], 'deviceId' => 'WARM', 'touched' => now_ms(), 'warm' => 1]);
            $all = array_merge([$token], array_values($args));
            $ckey = dsm_cache_key_($fn, $all);
            if ($ckey === null || is_file(dsm_cache_dir_() . '/' . $ckey . '.json')) { $out['skip']++; continue; }
            unset($GLOBALS['DSM_TM_CONTEXT']);
            $cs = dsm_contact_patch_on_($fn) ? (prop_get_('DEALER_CONTACT_STATE') ?? '') : null; // status kontak SAAT mulai menghitung
            $rf = new ReflectionFunction($fn); while (count($all) < $rf->getNumberOfParameters()) $all[] = null;
            $json = warmJson_(['ok' => true, 'result' => $fn(...$all)]);
            if ($json === null) { $out['fail']++; continue; }
            if (prop_get_('ACTIVE') !== $active) { $out['stop'] = 'snapshot berganti'; break; } // hasil dari data lama: jangan disimpan
            dsm_cache_put_($ckey, $cs !== null ? 'CS:' . $cs . "\n" . $json : $json);
            $out['done']++;
        } catch (Throwable $e) {
            $out['fail']++; error_log('[dsm] panaskan ' . $fn . ' ' . $nik . ' gagal: ' . $e->getMessage());
        } finally {
            try { db_()->prepare('DELETE FROM _sessions WHERE k = ?')->execute([$key]); } catch (Throwable $e) {}
        }
    }
    try { db_()->prepare('DELETE FROM dsm_warm WHERE last_ms < ?')->execute([now_ms() - 14 * 86400000]); } catch (Throwable $e) {}
    return $out;
}
