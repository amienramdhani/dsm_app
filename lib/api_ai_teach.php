<?php
/* DSM - Ajari AI (Super Admin), 2026-11-24. Mockup: doc/mockup/2026-11-24/dsm-ai-ajari-opsi.html
   - Arahan  (dsm_ai_rule): kata kunci -> fungsi AI yang sudah ada. Dicek sebelum Gemini, jadi pasti dijawab.
   - Pengetahuan (dsm_ai_know): catatan singkat yang ikut dikirim ke Gemini (maks ~2.000 karakter).
   - Status pertanyaan belum terjawab (dsm_ai_unk): BARU / DIAJARI / DIABAIKAN / DEV. Sumber pertanyaan: sh_ai_audit_log.
   AI tidak belajar sendiri; semua berlaku setelah disimpan Super Admin. */

const AI_TARGETS = ['perf_summary' => 'Ringkasan (ST, SO, DA, NOO, run rate)', 'brand_type' => 'Brand & type', 'brand_achievement' => 'Capaian per brand', 'early_warning' => 'Early warning tim', 'dealer_not_order' => 'Dealer belum order',
    'dealer_priority' => 'Dealer prioritas kunjungan', 'dealer_potential' => 'Dealer potensi Dealer Aktif', 'dos_risk' => 'Stok / DOS', 'kpi_detail' => 'Rincian KPI', 'kpi_team' => 'KPI tim', 'transactions' => 'Rincian transaksi per tanggal', 'da_noo_cumulative' => 'DA & NOO kumulatif per tanggal', 'stock_insight' => 'Stok lanjutan', 'daily_sales' => 'Penjualan harian', 'dealer_insight' => 'Analisis dealer', 'ranking' => 'Ranking & perbandingan', 'day_pattern' => 'Pola hari', 'focus_plan' => 'Saran fokus', 'top_type' => 'TYPE terlaris',
    'weekly_trend' => 'Tren mingguan', 'glossary' => 'Penjelasan istilah'];
const AI_KNOW_MAX = 2000;

function aiTeachDb_(): PDO {
    static $ok = false; $pdo = db_();
    if (!$ok) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS dsm_ai_rule (id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, keywords VARCHAR(500) NOT NULL, example VARCHAR(500) NOT NULL DEFAULT '', target VARCHAR(40) NOT NULL, active TINYINT NOT NULL DEFAULT 1, used INT NOT NULL DEFAULT 0, created_by VARCHAR(100) NOT NULL DEFAULT '', created_at BIGINT NOT NULL DEFAULT 0) DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS dsm_ai_know (id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(20) NOT NULL DEFAULT 'ISTILAH', title VARCHAR(120) NOT NULL, body VARCHAR(1000) NOT NULL, active TINYINT NOT NULL DEFAULT 1, used INT NOT NULL DEFAULT 0, created_by VARCHAR(100) NOT NULL DEFAULT '', created_at BIGINT NOT NULL DEFAULT 0) DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS dsm_ai_unk (qkey VARCHAR(190) NOT NULL PRIMARY KEY, status VARCHAR(10) NOT NULL DEFAULT 'BARU', note VARCHAR(300) NOT NULL DEFAULT '', by_nik VARCHAR(100) NOT NULL DEFAULT '', at_ms BIGINT NOT NULL DEFAULT 0) DEFAULT CHARSET=utf8mb4");
        $ok = true;
    }
    return $pdo;
}
/** Kunci pengelompokan pertanyaan mirip: huruf kecil, tanpa tanda baca, spasi tunggal. */
function aiQKey_(string $q): string { $l = mb_strtolower($q); $l = preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $l); return mb_substr(trim(preg_replace('/\s+/', ' ', $l)), 0, 180); }
function aiKw_(string $s): array { return array_values(array_filter(array_map(fn($k) => aiQKey_($k), explode(',', $s)), fn($k) => $k !== '')); }

/** Arahan yang cocok dengan pertanyaan (semua kata dari salah satu kata kunci ada di pertanyaan). */
function aiRuleMatch_(string $q): ?array {
    try { $rows = aiTeachDb_()->query("SELECT * FROM dsm_ai_rule WHERE active=1 ORDER BY used DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { return null; }
    $k = ' ' . aiQKey_($q) . ' ';
    foreach ($rows as $r) foreach (aiKw_($r['keywords'] . ',' . $r['example']) as $kw) {
        $words = explode(' ', $kw); if (!$words) continue;
        if (count(array_filter($words, fn($w) => strpos($k, ' ' . $w . ' ') !== false)) === count($words)) return $r;
    }
    return null;
}
function aiRuleUsed_(int $id): void { try { aiTeachDb_()->prepare("UPDATE dsm_ai_rule SET used=used+1 WHERE id=?")->execute([$id]); } catch (Throwable $e) {} }

/** Teks pengetahuan aktif untuk prompt Gemini (dipotong AI_KNOW_MAX karakter). */
function aiKnowText_(): string {
    try { $rows = aiTeachDb_()->query("SELECT kind,title,body FROM dsm_ai_know WHERE active=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { return ''; }
    $t = ''; foreach ($rows as $r) { $line = '- [' . $r['kind'] . '] ' . $r['title'] . ': ' . $r['body'] . "\n"; if (mb_strlen($t . $line) > AI_KNOW_MAX) break; $t .= $line; }
    return $t;
}
/** Pengetahuan yang judulnya disebut di pertanyaan: dipakai di mode dasar & penghitung "dipakai". */
function aiKnowHit_(string $q): ?array {
    try { $rows = aiTeachDb_()->query("SELECT * FROM dsm_ai_know WHERE active=1")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { return null; }
    $k = ' ' . aiQKey_($q) . ' ';
    foreach ($rows as $r) { $t = aiQKey_(preg_replace('/\(.*?\)/', '', $r['title'])); if ($t !== '' && strpos($k, ' ' . $t . ' ') !== false) { try { aiTeachDb_()->prepare("UPDATE dsm_ai_know SET used=used+1 WHERE id=?")->execute([$r['id']]); } catch (Throwable $e) {} return $r; } }
    return null;
}

/* ---------- API (khusus Super Admin) ---------- */
function apiAITeachHome($token = null, $days = 30) {
    $u = guard_($token, true); $pdo = aiTeachDb_(); $days = max(1, min(180, (int) $days));
    $since = gmdate('c', time() - $days * 86400);
    $st = $pdo->prepare("SELECT TIMESTAMP,NIK,QUESTION,INTENT,STATUS FROM sh_ai_audit_log WHERE TIMESTAMP >= ? ORDER BY TIMESTAMP DESC LIMIT 5000"); $st->execute([$since]);
    $all = $st->fetchAll(PDO::FETCH_ASSOC);
    $names = []; foreach (aiPeople_($token) as $p) $names[$p['nik']] = $p['nama'];
    $stat = []; foreach ($pdo->query("SELECT * FROM dsm_ai_unk")->fetchAll(PDO::FETCH_ASSOC) as $r) $stat[$r['qkey']] = $r;
    $g = []; $total = 0; $bad = 0;
    foreach ($all as $r) {
        if (in_array($r['STATUS'], ['LIMIT'], true)) continue; $total++;
        $busy = $r['INTENT'] === 'BUSY'; $uns = $r['STATUS'] === 'UNSUPPORTED' || $r['INTENT'] === 'UNSUPPORTED';
        if (!$busy && !$uns) continue; $bad++;
        $k = aiQKey_((string) $r['QUESTION']); if ($k === '') continue;
        if (!isset($g[$k])) $g[$k] = ['qkey' => $k, 'q' => (string) $r['QUESTION'], 'n' => 0, 'last' => $r['TIMESTAMP'], 'who' => [], 'reason' => $busy ? 'AI SIBUK' : 'TIDAK DIKENALI', 'status' => $stat[$k]['status'] ?? 'BARU', 'note' => $stat[$k]['note'] ?? ''];
        $g[$k]['n']++; $nm = $names[$r['NIK']] ?? $r['NIK']; if (!in_array($nm, $g[$k]['who'], true)) $g[$k]['who'][] = $nm;
        if (!$busy) $g[$k]['reason'] = 'TIDAK DIKENALI';
    }
    $unk = array_values($g); usort($unk, fn($a, $b) => [$b['n'], $b['last']] <=> [$a['n'], $a['last']]);
    $rules = $pdo->query("SELECT * FROM dsm_ai_rule ORDER BY active DESC, used DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $know = $pdo->query("SELECT * FROM dsm_ai_know ORDER BY active DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ([&$rules, &$know] as &$L) foreach ($L as &$x) { $x['created_name'] = $names[$x['created_by']] ?? $x['created_by']; } unset($L, $x);
    $new = array_filter($unk, fn($x) => $x['status'] === 'BARU');
    return ['days' => $days, 'unknown' => $unk, 'rules' => $rules, 'know' => $know, 'targets' => AI_TARGETS, 'knowLen' => mb_strlen(aiKnowText_()), 'knowMax' => AI_KNOW_MAX,
        'stats' => ['baru' => count($new), 'ulang' => count(array_filter($new, fn($x) => $x['n'] >= 3)), 'rules' => count(array_filter($rules, fn($r) => (int) $r['active'])), 'rulesUsed' => array_sum(array_map(fn($r) => (int) $r['used'], $rules)),
            'know' => count(array_filter($know, fn($r) => (int) $r['active'])), 'total' => $total, 'rate' => $total ? round(($total - $bad) * 100 / $total) : null]];
}
function apiAITeachSave($token = null, $kind = null, $d = null) {
    $u = guard_($token, true); $pdo = aiTeachDb_(); $d = is_array($d) ? $d : []; $now = (int) round(microtime(true) * 1000);
    if ($kind === 'rule') {
        $kw = trim((string) ($d['keywords'] ?? '')); $ex = trim((string) ($d['example'] ?? '')); $tg = (string) ($d['target'] ?? '');
        if (!isset(AI_TARGETS[$tg])) fail_('Pilih jawaban tujuan.'); if ($kw === '' && $ex === '') fail_('Isi kata kunci atau contoh pertanyaan.');
        if (!empty($d['id'])) $pdo->prepare("UPDATE dsm_ai_rule SET keywords=?,example=?,target=?,active=? WHERE id=?")->execute([mb_substr($kw, 0, 500), mb_substr($ex, 0, 500), $tg, empty($d['off']) ? 1 : 0, (int) $d['id']]);
        else $pdo->prepare("INSERT INTO dsm_ai_rule (keywords,example,target,active,created_by,created_at) VALUES (?,?,?,1,?,?)")->execute([mb_substr($kw, 0, 500), mb_substr($ex, 0, 500), $tg, $u['nik'], $now]);
    } elseif ($kind === 'know') {
        $t = trim((string) ($d['title'] ?? '')); $b = trim((string) ($d['body'] ?? '')); $k = in_array($d['kind'] ?? '', ['ISTILAH', 'ATURAN', 'GAYA JAWAB'], true) ? $d['kind'] : 'ISTILAH';
        if ($t === '' || $b === '') fail_('Judul dan isi pengetahuan wajib diisi.');
        if (!empty($d['id'])) $pdo->prepare("UPDATE dsm_ai_know SET kind=?,title=?,body=?,active=? WHERE id=?")->execute([$k, mb_substr($t, 0, 120), mb_substr($b, 0, 1000), empty($d['off']) ? 1 : 0, (int) $d['id']]);
        else $pdo->prepare("INSERT INTO dsm_ai_know (kind,title,body,active,created_by,created_at) VALUES (?,?,?,1,?,?)")->execute([$k, mb_substr($t, 0, 120), mb_substr($b, 0, 1000), $u['nik'], $now]);
    } elseif ($kind === 'toggle') {
        $tb = ($d['what'] ?? '') === 'know' ? 'dsm_ai_know' : 'dsm_ai_rule';
        $pdo->prepare("UPDATE $tb SET active=1-active WHERE id=?")->execute([(int) ($d['id'] ?? 0)]);
    } else fail_('Jenis tidak dikenal.');
    if (!empty($d['qkey'])) aiUnkSet_($pdo, (string) $d['qkey'], 'DIAJARI', '', $u['nik'], $now);
    return ['ok' => true];
}
function aiUnkSet_(PDO $pdo, string $k, string $st, string $note, string $nik, int $now): void {
    $pdo->prepare("INSERT INTO dsm_ai_unk (qkey,status,note,by_nik,at_ms) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),note=VALUES(note),by_nik=VALUES(by_nik),at_ms=VALUES(at_ms)")->execute([mb_substr($k, 0, 190), $st, mb_substr($note, 0, 300), $nik, $now]);
}
function apiAITeachMark($token = null, $qkey = null, $status = null, $note = null) {
    $u = guard_($token, true); if (!in_array($status, ['BARU', 'DIABAIKAN', 'DEV', 'DIAJARI'], true)) fail_('Status tidak dikenal.');
    aiUnkSet_(aiTeachDb_(), (string) $qkey, $status, (string) $note, $u['nik'], (int) round(microtime(true) * 1000)); return ['ok' => true];
}
function apiAITeachDelete($token = null, $what = null, $id = null) {
    guard_($token, true); $tb = $what === 'know' ? 'dsm_ai_know' : ($what === 'rule' ? 'dsm_ai_rule' : ''); if (!$tb) fail_('Jenis tidak dikenal.');
    aiTeachDb_()->prepare("DELETE FROM $tb WHERE id=?")->execute([(int) $id]); return ['ok' => true];
}
/** Pratinjau: jalankan fungsi tujuan untuk pertanyaan contoh (tanpa Gemini, tanpa dicatat). */
function apiAITeachTest($token = null, $period = null, $selection = null, $target = null, $question = null) {
    guard_($token, true); if (!isset(AI_TARGETS[$target])) fail_('Pilih jawaban tujuan.');
    $q = (string) $question; $x = aiCtx_($token, (string) $period, (string) ($selection ?: 'ALL')); $brands = array_map(fn($b) => $b['brand'], aiDash_($x)['brands'] ?? []);
    $r = aiRun_($target, aiArgsFor_($target, $q, $x, $brands), $x);
    return ['answer' => $r['summary'], 'blocks' => array_slice($r['blocks'], 0, 2)];
}
/** Argumen fungsi dari teks pertanyaan (brand, orang/wilayah, jumlah), sama seperti mode dasar. */
function aiArgsFor_(string $fn, string $q, array &$x, array $brands): array {
    $args = []; $b = aiBrandIn_($q, $brands) ?? '';
    if (in_array($fn, ['dealer_not_order', 'dealer_potential', 'dos_risk', 'top_type', 'brand_type'], true)) $args['brand'] = $b;
    if ($fn === 'glossary') $args['term'] = $q;
    if (in_array($fn, ['top_type', 'weekly_trend', 'perf_summary', 'brand_achievement', 'dealer_potential', 'dealer_not_order', 'dealer_priority', 'dos_risk', 'early_warning'], true)) {
        $pe = aiPersonFind_($q, $x['token'], true); if ($pe) $args['person'] = (string) $pe['nama']; else { $rg = aiRegionIn_($q, $x); if ($rg !== '') $args['region'] = $rg; }
        $lm = aiLimitIn_($q); if ($lm > 0) $args['limit'] = $lm;
    }
    if ($fn === 'dos_risk') { $l = mb_strtolower($q); $args['status'] = preg_match('/habis/', $l) ? 'HABIS' : (preg_match('/tinggi/', $l) ? 'STOK TINGGI' : 'MENIPIS'); }
    return $args;
}
