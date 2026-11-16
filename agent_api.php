<?php
/**
 * DSM Agent API (baca-saja) untuk bot Telegram tim IT.
 * Dokumen: doc/agent-api/API_UNTUK_IT.md
 *
 * GET  /agent_api.php?r=<resource>&...   (semua resource data)
 * POST /agent_api.php?r=link             {"code":"123456","username":"..."}
 * Header: Authorization: Bearer <BOT_KEY>, X-Telegram-User-Id: <id>
 *
 * Resource: health, me, link, akan_habis + resource data (AGENT_RESOURCES di lib/agent.php).
 */
declare(strict_types=0);
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('memory_limit', '1536M');
set_time_limit(120);

// Muat modul yang sama dengan api.php supaya semua fungsi baca tersedia.
require_once __DIR__ . '/lib/blob.php';
require_once __DIR__ . '/lib/api_main.php';
require_once __DIR__ . '/lib/api_analisa.php';
require_once __DIR__ . '/lib/api_tutup.php';
require_once __DIR__ . '/lib/api_weekly.php';
require_once __DIR__ . '/lib/api_exec.php';
require_once __DIR__ . '/lib/api_import.php';
require_once __DIR__ . '/lib/dealer.php';
require_once __DIR__ . '/lib/bulk.php';
require_once __DIR__ . '/lib/api_dealer_transfer_direct.php';
require_once __DIR__ . '/lib/users_bulk.php';
require_once __DIR__ . '/lib/activity.php';
require_once __DIR__ . '/lib/api_kpi.php';
require_once __DIR__ . '/lib/api_kpikhusus.php';
require_once __DIR__ . '/lib/api_portfolio.php';
require_once __DIR__ . '/lib/tx_effective.php';
require_once __DIR__ . '/lib/api_txcorr.php';
require_once __DIR__ . '/lib/api_commit.php';
require_once __DIR__ . '/lib/keep_seller.php';
require_once __DIR__ . '/lib/owner_snapshot.php';
require_once __DIR__ . '/lib/api_dealertarget.php';
require_once __DIR__ . '/lib/transfer_undo.php';
require_once __DIR__ . '/lib/api_admin2.php';
require_once __DIR__ . '/lib/api_acct.php';
require_once __DIR__ . '/lib/api_health.php';
require_once __DIR__ . '/lib/api_backup.php';
require_once __DIR__ . '/lib/api_contact.php';
require_once __DIR__ . '/lib/api_info.php';
require_once __DIR__ . '/lib/api_menu.php';
require_once __DIR__ . '/lib/api_export2.php';
require_once __DIR__ . '/lib/api_brand.php';
require_once __DIR__ . '/lib/rescache.php';
require_once __DIR__ . '/lib/api_telemarketing.php';
require_once __DIR__ . '/lib/api_push.php';
require_once __DIR__ . '/lib/api_notif.php';
require_once __DIR__ . '/lib/api_ai.php';
require_once __DIR__ . '/lib/agent.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function agentReply_(int $http, array $data): never {
    http_response_code($http);
    if ($http === 429) header('Retry-After: 60');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

function agentHeader_(string $name): ?string {
    $k = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (isset($_SERVER[$k])) return (string) $_SERVER[$k];
    if ($name === 'Authorization') {
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) return (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        if (function_exists('getallheaders')) foreach (getallheaders() as $h => $v) if (strcasecmp($h, 'Authorization') === 0) return (string) $v;
    }
    return null;
}

/** Resource yang tidak butuh Telegram ID. */
const AGENT_NO_TG = ['health', 'akan_habis'];

$t0 = microtime(true);
$keyId = 0; $tgId = ''; $nik = '';
$res = strtolower(preg_replace('/[^a-z_]/i', '', (string) ($_GET['r'] ?? '')));
$params = $_GET; unset($params['r']);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($res === '') agentFail_(400, 'BAD_PARAM', 'Parameter r (resource) wajib diisi.');
    if ($res === 'health') agentReply_(200, ['ok' => true, 'result' => ['status' => 'OK', 'waktu' => date('c')]]);

    $keyId = agentKeyCheck_(agentHeader_('Authorization'));

    if (!in_array($res, AGENT_NO_TG, true)) {
        $tgId = trim((string) agentHeader_('X-Telegram-User-Id'));
        if (!preg_match('/^\d{3,20}$/', $tgId)) agentFail_(400, 'BAD_PARAM', 'Header X-Telegram-User-Id wajib berisi angka ID Telegram.');
    }
    agentRate_($keyId, $tgId);

    switch ($res) {
        case 'link': {
            if ($method !== 'POST') agentFail_(400, 'BAD_PARAM', 'Gunakan POST untuk link.');
            $body = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($body)) agentFail_(400, 'BAD_PARAM', 'Body harus JSON.');
            $username = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($body['username'] ?? ''));
            $out = agentLinkWithCode_($tgId, trim((string) ($body['code'] ?? '')), $username);
            $st = db_()->prepare('SELECT nik FROM dsm_tg_link WHERE telegram_id=?'); $st->execute([$tgId]); $nik = (string) $st->fetchColumn();
            agentLog_($keyId, $tgId, $nik, $res, [], 'OK', (int) ((microtime(true) - $t0) * 1000));
            agentReply_(200, ['ok' => true, 'result' => $out]);
        }
        case 'akan_habis': {
            // Daftar Telegram ID yang sambungannya habis <= 3 hari (untuk pengingat H-3 oleh bot).
            agentSchema_();
            $st = db_()->prepare('SELECT telegram_id, expires_ms FROM dsm_tg_link WHERE revoked_ms=0 AND expires_ms > ? AND expires_ms <= ? ORDER BY expires_ms');
            $st->execute([now_ms(), now_ms() + 3 * 86400000]);
            $list = array_map(fn($r) => ['telegram_id' => $r['telegram_id'], 'berlaku_sampai' => date('Y-m-d', intdiv((int) $r['expires_ms'], 1000)), 'sisa_hari' => (int) ceil(((int) $r['expires_ms'] - now_ms()) / 86400000)], $st->fetchAll(PDO::FETCH_ASSOC));
            agentLog_($keyId, '', '', $res, [], 'OK', (int) ((microtime(true) - $t0) * 1000));
            agentReply_(200, ['ok' => true, 'result' => $list]);
        }
    }

    if ($method !== 'GET') agentFail_(400, 'BAD_PARAM', 'Gunakan GET.');
    $link = agentLinkActive_($tgId);
    $nik = (string) $link['nik'];

    switch ($res) {
        case 'me':
            $out = agentLinkInfo_($link);
            break;
        default:
            if (!in_array($res, AGENT_RESOURCES, true)) agentFail_(400, 'BAD_PARAM', 'Resource "' . $res . '" tidak dikenal. Lihat daftar di dokumen API.');
            $per = max(1, min(AGENT_MAX_LIST, (int) ($params['per_halaman'] ?? AGENT_DEF_LIST)));
            $hal = max(1, (int) ($params['halaman'] ?? 1));
            $raw = agentResource_($res, $link, $params);
            $meta = [];
            $out = agentMaskDeep_(agentTrim_($raw, $per, ($hal - 1) * $per, $meta));
            unset($meta['_paged']);
            $meta += ['periode' => $params['periode'] ?? date('Y-m'), 'cakupan' => agentLinkInfo_($link)['cakupan'], 'role' => agentLinkInfo_($link)['role']];
            agentLog_($keyId, $tgId, $nik, $res, $params, 'OK', (int) ((microtime(true) - $t0) * 1000));
            agentReply_(200, ['ok' => true, 'result' => $out, 'meta' => $meta]);
    }
    agentLog_($keyId, $tgId, $nik, $res, $params, 'OK', (int) ((microtime(true) - $t0) * 1000));
    agentReply_(200, ['ok' => true, 'result' => $out]);

} catch (AgentError $e) {
    agentLog_($keyId, $tgId, $nik, $res, $params, $e->codeName, (int) ((microtime(true) - $t0) * 1000));
    agentReply_($e->http, ['ok' => false, 'code' => $e->codeName, 'message' => $e->getMessage()]);
} catch (AppError $e) {
    // Pesan dari fungsi DSM (mis. di luar hak akses role).
    agentLog_($keyId, $tgId, $nik, $res, $params, 'FORBIDDEN', (int) ((microtime(true) - $t0) * 1000));
    agentReply_(403, ['ok' => false, 'code' => 'FORBIDDEN', 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[dsm-agent] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    agentLog_($keyId, $tgId, $nik, $res, $params, 'SERVER', (int) ((microtime(true) - $t0) * 1000));
    agentReply_(500, ['ok' => false, 'code' => 'SERVER', 'message' => 'Gangguan server DSM. Coba lagi nanti.']);
}
