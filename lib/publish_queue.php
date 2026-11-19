<?php
/**
 * ANTREAN PUBLIKASI (Tahap 3 simpan cepat, 2026-11-18, mockup doc/mockup/2026-11-18/dsm-publikasi-data.html)
 *
 * - Simpan BERAT tidak lagi menunggu publikasi penuh di dalam request. publish_() memasukkan perubahan ke antrean
 *   (publishQueueDeferOk_) lalu layar langsung kembali. Upload ST/SO juga masuk antrean (pqEnqueueImport_).
 * - Pekerja (pqWork_) menjalankan antrean: (1) upload satu per satu, (2) semua perubahan master digabung jadi SATU publikasi.
 *   Dipicu dari: cron CLI tiap menit (tools/publish_worker.php, tanpa batas 180 dtk FPM) — utama;
 *   dan setelah respons dikirim (fastcgi_finish_request) — supaya tanpa cron pun tetap jalan.
 * - Tahan publikasi (Super Admin): antrean tidak diproses sampai dilepas; lepas otomatis setelah PQ_HOLD_MAX.
 * - Darurat: properti PUBLISH_QUEUE_OFF = 1 → kembali seperti dulu (publikasi langsung di dalam request).
 */
require_once __DIR__ . '/common.php';

const PQ_PROP = 'PUBLISH_QUEUE';
const PQ_STATE = 'PUBLISH_STATE';
const PQ_HOLD = 'PUBLISH_HOLD';
const PQ_HOLD_MAX = 2 * 3600 * 1000; // ditahan > 2 jam → dilepas otomatis
const PQ_RETRY_MS = 5 * 60 * 1000;   // gagal → dicoba lagi otomatis 5 menit kemudian

function pqOn_(): bool { return !prop_get_('PUBLISH_QUEUE_OFF'); }
function pqJson_(string $k, $def) { $v = prop_get_($k); $j = $v ? json_decode((string) $v, true) : null; return is_array($j) ? $j : $def; }
function pqQueue_(): array { return pqJson_(PQ_PROP, []); }
function pqState_(): array { return pqJson_(PQ_STATE, ['status' => 'idle', 'hist' => []]); }
function pqSetState_(array $s): void { $s['hist'] = array_slice($s['hist'] ?? [], 0, 10); prop_set_(PQ_STATE, json_encode($s, JSON_UNESCAPED_UNICODE)); }
function pqHold_(): ?array {
    $h = pqJson_(PQ_HOLD, []); if (!$h) return null;
    if (now_ms() - (int) ($h['at'] ?? 0) > PQ_HOLD_MAX) { prop_delete_(PQ_HOLD); return null; }
    return $h;
}
/** Kunci kecil khusus mengubah isi antrean. */
function pqLocked_(callable $f) {
    $pdo = db_(); $got = (int) $pdo->query("SELECT GET_LOCK('dsm_queue', 10)")->fetchColumn();
    if (!$got) fail_('Antrean publikasi sibuk. Coba lagi sebentar.');
    try { return $f(); } finally { $pdo->query("SELECT RELEASE_LOCK('dsm_queue')"); }
}
function pqAdd_(array $item): void {
    pqLocked_(function () use ($item) {
        $q = pqQueue_(); $item += ['id' => uuid_(), 'at' => now_ms()]; $q[] = $item;
        prop_set_(PQ_PROP, json_encode(array_slice($q, -200), JSON_UNESCAPED_UNICODE));
    });
    pqKick_();
}
function pqNick_(string $nik): string {
    try { $db = snapshot_(); $p = PortalCore::person($db['meta']['people'], $nik); return js_or($p['nama'] ?? null, $nik); } catch (Throwable $e) { return $nik; }
}
/** Dipanggil dari publish_(): boleh ditunda bila ini publikasi ulang dari master (data ST/SO tidak berubah). */
function publishQueueDeferOk_(?array $prior, $rawMap, $coverage, $soOverride, $partsOverride): bool {
    if (!pqOn_() || !empty($GLOBALS['DSM_IN_WORKER']) || !$prior || truthy($soOverride)) return false;
    if (truthy($partsOverride) && json_encode($partsOverride) !== json_encode($prior['parts'] ?? null)) return false;
    if (json_encode($rawMap ?? []) !== json_encode($prior['raw'] ?? [])) return false;
    if (json_encode($coverage ?? []) !== json_encode($prior['coverage'] ?? [])) return false;
    return true;
}
function pqEnqueueMaster_(array $prior, $actor, $reason): array {
    pqAdd_(['kind' => 'master', 'label' => mb_substr(js_str($reason), 0, 160), 'nik' => js_str($actor), 'nama' => pqNick_(js_str($actor))]);
    audit_(js_str($actor), 'PUBLISH_QUEUE', js_str($reason));
    return ['version' => $prior['id'], 'processed' => null, 'notes' => [], 'rows' => null, 'queued' => true];
}
/** Upload ST/SO: pratinjau sudah diperiksa, prosesnya masuk antrean. */
function pqEnqueueImport_(array $u, string $stageId, string $label): array {
    foreach (pqQueue_() as $x) if (($x['kind'] ?? '') === 'import' && ($x['stage'] ?? '') === $stageId) return ['queued' => true];
    pqAdd_(['kind' => 'import', 'stage' => $stageId, 'label' => $label, 'nik' => $u['nik'], 'nama' => js_or($u['nama'] ?? null, $u['nik'])]);
    audit_($u['nik'], 'PUBLISH_QUEUE', 'Upload ' . $label);
    return ['queued' => true];
}

/** Jalankan pekerja setelah respons terkirim (PHP-FPM). Satu kali per request. */
function pqKick_(): void {
    if (!empty($GLOBALS['DSM_PQ_KICK']) || !empty($GLOBALS['DSM_IN_WORKER']) || PHP_SAPI === 'cli') return;
    $GLOBALS['DSM_PQ_KICK'] = 1;
    register_shutdown_function(function () {
        if (!function_exists('fastcgi_finish_request')) return;
        @fastcgi_finish_request(); @ignore_user_abort(true); @set_time_limit(0);
        try { pqWork_(false); } catch (Throwable $e) { error_log('[dsm] antrean publikasi: ' . $e->getMessage()); }
    });
}
/** Cadangan tanpa cron: request biasa memicu pekerja bila ada antrean yang tidak tersentuh > 90 detik. */
function pqMaybeKick_(): void {
    try {
        if (!pqOn_() || PHP_SAPI === 'cli') return; $q = pqQueue_(); if (!$q) return;
        $st = pqState_(); if (($st['status'] ?? '') === 'run' && now_ms() - (int) ($st['beat'] ?? 0) < 120000) return;
        if (($st['status'] ?? '') === 'err' && now_ms() < (int) ($st['retryAt'] ?? 0)) return;
        if (now_ms() - (int) ($q[0]['at'] ?? 0) > 90000) pqKick_();
    } catch (Throwable $e) {}
}

/**
 * Pekerja antrean. $force = abaikan Tahan publikasi & jadwal ulang gagal (tombol PUBLIKASIKAN SEKARANG).
 * Hanya satu pekerja yang jalan (kunci dsm_worker).
 */
function pqWork_(bool $force = false): array {
    $pdo = db_(); $got = (int) $pdo->query("SELECT GET_LOCK('dsm_worker', 0)")->fetchColumn(); if (!$got) return ['busy' => true];
    $GLOBALS['DSM_IN_WORKER'] = 1; $done = 0;
    try {
        @ini_set('memory_limit', '2560M');
        for ($round = 0; $round < 10; $round++) {
            $q = pqQueue_(); if (!$q) break;
            $st = pqState_();
            if (!$force && pqHold_()) break;
            if (!$force && ($st['status'] ?? '') === 'err' && now_ms() < (int) ($st['retryAt'] ?? 0)) break;
            $imp = null; foreach ($q as $x) if (($x['kind'] ?? '') === 'import') { $imp = $x; break; }
            $batch = $imp ? [$imp] : array_values(array_filter($q, fn($x) => ($x['kind'] ?? '') !== 'import'));
            $ids = array_column($batch, 'id'); $label = $imp ? 'Upload ' . $imp['label'] : implode(' · ', array_slice(array_column($batch, 'label'), 0, 5)) . (count($batch) > 5 ? ' · +' . (count($batch) - 5) : '');
            $t0 = now_ms(); $avg = (int) ($st['avgMs'] ?? 120000);
            pqSetState_(['status' => 'run', 'started' => $t0, 'beat' => $t0, 'label' => $label, 'count' => count($batch), 'avgMs' => $avg, 'hist' => $st['hist'] ?? []]);
            try {
                if ($imp) publishRegionImportRun_(['nik' => $imp['nik'], 'nama' => $imp['nama'] ?? $imp['nik']], (string) $imp['stage'], true);
                else with_lock_(function () use ($label, $batch) {
                    $act = snapshot_(); $meta = meta_();
                    publish_($meta, $act['raw'], $act['coverage'], $batch[count($batch) - 1]['nik'] ?? 'ANTREAN', 'ANTREAN · ' . $label, null, $act['parts'] ?? null);
                });
                $ms = now_ms() - $t0;
                // master yang masuk SEBELUM upload ikut terbawa publikasi upload (meta_() terbaru) → ikut dibersihkan
                $drop = $ids; if ($imp) foreach ($q as $x) if (($x['kind'] ?? '') !== 'import' && (int) $x['at'] <= (int) $imp['at']) $drop[] = $x['id'];
                pqLocked_(function () use ($drop) { $q = array_values(array_filter(pqQueue_(), fn($x) => !in_array($x['id'], $drop, true))); prop_set_(PQ_PROP, json_encode($q, JSON_UNESCAPED_UNICODE)); });
                $st = pqState_(); array_unshift($st['hist'], ['ok' => true, 'label' => $label, 'by' => implode(', ', array_unique(array_column($batch, 'nama'))), 'at' => $t0, 'ms' => $ms]);
                pqSetState_(['status' => 'idle', 'avgMs' => (int) round(($avg * 2 + $ms) / 3), 'last' => now_ms(), 'lastMs' => $ms, 'lastLabel' => $label, 'hist' => $st['hist']]);
                $done++;
                if (function_exists('pqWarm_')) pqWarm_();
            } catch (Throwable $e) {
                if (str_contains($e->getMessage(), 'Sistem sedang memproses publikasi lain')) { $st = pqState_(); $st['status'] = 'idle'; pqSetState_($st); break; } // simpan lain sedang jalan → coba di putaran berikutnya
                $st = pqState_(); array_unshift($st['hist'], ['ok' => false, 'label' => $label, 'by' => implode(', ', array_unique(array_column($batch, 'nama'))), 'at' => $t0, 'ms' => now_ms() - $t0, 'err' => mb_substr($e->getMessage(), 0, 200)]);
                pqSetState_(['status' => 'err', 'err' => mb_substr($e->getMessage(), 0, 300), 'errAt' => now_ms(), 'retryAt' => now_ms() + PQ_RETRY_MS, 'label' => $label, 'avgMs' => $avg, 'hist' => $st['hist']]);
                error_log('[dsm] publikasi antrean gagal: ' . $e->getMessage());
                // upload yang gagal karena pratinjau hilang tidak akan pernah berhasil → keluarkan dari antrean
                if ($imp && str_contains($e->getMessage(), 'Pratinjau tidak tersedia')) pqLocked_(function () use ($ids) { $q = array_values(array_filter(pqQueue_(), fn($x) => !in_array($x['id'], $ids, true))); prop_set_(PQ_PROP, json_encode($q, JSON_UNESCAPED_UNICODE)); });
                break;
            }
            $force = false;
        }
        // Panaskan cache akun yang aktif 3 hari terakhir (lib/warm.php). CLI (cron) boleh lama; lewat web dibatasi.
        if ($done && function_exists('warmRun_')) { try { $w = warmRun_(PHP_SAPI === 'cli' ? 480000 : 60000); if (PHP_SAPI === 'cli') echo date('c'), ' panaskan: ', json_encode($w), PHP_EOL; } catch (Throwable $e) { error_log('[dsm] panaskan gagal: ' . $e->getMessage()); } }
    } finally { unset($GLOBALS['DSM_IN_WORKER']); $pdo->query("SELECT RELEASE_LOCK('dsm_worker')"); }
    return ['done' => $done];
}

/* ---------- API ---------- */
function pqView_(array $u): array {
    $st = pqState_(); $q = pqQueue_(); $h = pqHold_();
    $hb = ($st['status'] ?? '') === 'run' ? (int) ($st['beat'] ?? 0) : 0;
    if ($hb && now_ms() - $hb > 15 * 60 * 1000) $st['status'] = 'err'; // pekerja mati di tengah jalan (mis. server restart)
    try { $act = prop_get_('ACTIVE'); } catch (Throwable $e) { $act = null; }
    return ['status' => $st['status'] ?? 'idle', 'started' => $st['started'] ?? null, 'avgMs' => $st['avgMs'] ?? null, 'label' => $st['label'] ?? '', 'err' => $st['err'] ?? null, 'errAt' => $st['errAt'] ?? null, 'retryAt' => $st['retryAt'] ?? null,
        'last' => $st['last'] ?? null, 'lastMs' => $st['lastMs'] ?? null, 'lastLabel' => $st['lastLabel'] ?? null, 'hist' => $st['hist'] ?? [], 'now' => now_ms(),
        'queue' => array_map(fn($x) => ['kind' => $x['kind'], 'label' => $x['label'], 'nama' => $x['nama'] ?? $x['nik'], 'at' => $x['at']], $q),
        'hold' => $h ? ['by' => $h['nama'] ?? $h['by'], 'at' => $h['at'], 'until' => (int) $h['at'] + PQ_HOLD_MAX] : null,
        'on' => pqOn_(), 'sa' => $u['role'] === 'SUPER ADMIN', 'active' => $act];
}
function apiPublishStatus($token) { $u = adminGuard_($token); pqMaybeKick_(); return pqView_($u); }
function apiPublishNow($token) {
    $u = guard_($token, true); if ($u['role'] !== 'SUPER ADMIN') fail_('Khusus Super Admin.');
    if (!pqQueue_()) return pqView_($u);
    prop_delete_(PQ_HOLD);
    $st = pqState_(); if (($st['status'] ?? '') === 'err') { $st['retryAt'] = 0; $st['status'] = 'idle'; pqSetState_($st); }
    audit_($u['nik'], 'PUBLISH_NOW', count(pqQueue_()) . ' perubahan');
    // jalankan setelah respons terkirim supaya layar tidak menunggu
    $GLOBALS['DSM_PQ_FORCE'] = 1;
    if (empty($GLOBALS['DSM_PQ_KICK']) && PHP_SAPI !== 'cli') { $GLOBALS['DSM_PQ_KICK'] = 1; register_shutdown_function(function () { if (!function_exists('fastcgi_finish_request')) return; @fastcgi_finish_request(); @ignore_user_abort(true); @set_time_limit(0); try { pqWork_(true); } catch (Throwable $e) { error_log('[dsm] publikasi sekarang: ' . $e->getMessage()); } }); }
    $v = pqView_($u); $v['status'] = 'run'; $v['started'] = now_ms(); return $v;
}
function apiPublishHold($token, $on) {
    $u = guard_($token, true); if ($u['role'] !== 'SUPER ADMIN') fail_('Khusus Super Admin.');
    if ($on === true || $on === 1 || $on === '1' || $on === 'true') { prop_set_(PQ_HOLD, json_encode(['by' => $u['nik'], 'nama' => js_or($u['nama'] ?? null, $u['nik']), 'at' => now_ms()])); audit_($u['nik'], 'PUBLISH_HOLD', 'tahan'); }
    else { prop_delete_(PQ_HOLD); audit_($u['nik'], 'PUBLISH_HOLD', 'lepas'); if (pqQueue_()) pqKick_(); }
    return pqView_($u);
}
/** Tahap 4: panaskan setelah publikasi — snapshot baru & status dealer terkunci dibaca sekali supaya pembuka pertama tidak menunggu. */
function pqWarm_(): void {
    try { $db = snapshot_(); if (function_exists('dsLocked_') && function_exists('dsLatest_')) dsLocked_($db, dsLatest_(dsBase_($db))); } catch (Throwable $e) { error_log('[dsm] panaskan gagal: ' . $e->getMessage()); }
}
