<?php
// Alat sementara Agent API (sebelum panel Admin di Paket 2). Hanya bisa dijalankan dari terminal server.
//   php tools/agent_cli.php key "Bot Telegram IT"   -> buat BOT_KEY (ditampilkan SEKALI)
//   php tools/agent_cli.php keys                   -> daftar BOT_KEY
//   php tools/agent_cli.php revoke-key <id>        -> cabut BOT_KEY
//   php tools/agent_cli.php code <NIK>             -> kode hubungkan 6 digit (5 menit) untuk uji
//   php tools/agent_cli.php links                  -> daftar sambungan Telegram
//   php tools/agent_cli.php unlink-all "alasan"    -> putuskan semua sambungan
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../lib/api_main.php';
require_once __DIR__ . '/../lib/agent.php';
agentSchema_();
$cmd = $argv[1] ?? ''; $arg = $argv[2] ?? '';
try {
    switch ($cmd) {
        case 'key': $k = agentKeyCreate_($arg !== '' ? $arg : 'Bot Telegram', 'CLI'); echo "ID {$k['id']} ({$k['label']})\nBOT_KEY: {$k['key']}\nSimpan sekarang, nilai ini tidak bisa ditampilkan lagi.\n"; break;
        case 'keys': foreach (db_()->query('SELECT id,label,active,created_at,revoked_at FROM dsm_agent_key ORDER BY id') as $r) echo implode(' | ', $r), "\n"; break;
        case 'revoke-key': agentKeyRevoke_((int) $arg, 'CLI'); echo "Dicabut.\n"; break;
        case 'code':
            if ($arg === '' || !account_($arg)) { echo "NIK tidak ditemukan.\n"; exit(1); }
            $c = agentCodeIssue_($arg); echo "Kode: {$c['code']} (berlaku 5 menit)\n"; break;
        case 'links': foreach (db_()->query('SELECT telegram_id,nik,username,FROM_UNIXTIME(expires_ms/1000) habis,revoked_ms,revoke_reason FROM dsm_tg_link ORDER BY linked_ms') as $r) echo implode(' | ', $r), "\n"; break;
        case 'unlink-all': echo agentLinkRevokeAll_('CLI', $arg !== '' ? $arg : 'darurat'), " sambungan diputus.\n"; break;
        default: echo "Perintah: key | keys | revoke-key <id> | code <NIK> | links | unlink-all <alasan>\n";
    }
} catch (Throwable $e) { echo 'Gagal: ', $e->getMessage(), "\n"; exit(1); }
