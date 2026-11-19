<?php
/**
 * Uji panaskan cache (lib/warm.php): pencatatan, hasil = cache request asli, sesi sementara selalu dihapus, rem pengaman.
 * Tanpa MySQL (SQLite). apiDashboard/apiDOS diganti fungsi tiruan yang memakai guard_() asli. Jalankan: php tools/tests/warm_test.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2); $tmp = sys_get_temp_dir() . '/dsm_wm_' . getmypid();
@mkdir("$tmp/lib", 0775, true); copy("$root/config.php", "$tmp/config.php");
foreach (glob("$root/lib/*.php") as $f) copy($f, "$tmp/lib/" . basename($f));
$rw = function (string $file, string $fn, string $body) {
    $s = file_get_contents($file); $i = strpos($s, "function $fn("); if ($i === false) die("tidak ada $fn\n");
    $b = strpos($s, '{', $i); $d = 0; for ($j = $b; $j < strlen($s); $j++) { if ($s[$j] === '{') $d++; elseif ($s[$j] === '}') { $d--; if ($d === 0) break; } }
    file_put_contents($file, substr($s, 0, $b) . '{' . $body . '}' . substr($s, $j + 1));
};
$rw("$tmp/lib/data.php", 'db_', 'static $p = null; if (!$p) { $p = new PDO("sqlite::memory:", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); $p->exec("CREATE TABLE _props (k TEXT PRIMARY KEY, v TEXT)"); $p->exec("CREATE TABLE _sessions (k TEXT PRIMARY KEY, nik TEXT, data TEXT, until_ms INT, device_id TEXT)"); } return $p;');
$rw("$tmp/lib/auth.php", 'session_put_', 'db_()->prepare("REPLACE INTO _sessions (k, nik, data, until_ms, device_id) VALUES (?,?,?,?,?)")->execute([$key, (string) $s["nik"], json_encode($s), (int) $s["until"], (string) ($s["deviceId"] ?? "")]);');
$rw("$tmp/lib/auth.php", 'account_', 'return $GLOBALS["T_ACC"][text_($nik)] ?? null;');
// warm_schema_: DDL MySQL -> SQLite
$w = file_get_contents("$tmp/lib/warm.php");
$w = str_replace('db_()->exec("CREATE TABLE IF NOT EXISTS dsm_warm (h CHAR(64) NOT NULL PRIMARY KEY, nik VARCHAR(100) NOT NULL, fn VARCHAR(60) NOT NULL, args TEXT NOT NULL, last_ms BIGINT NOT NULL, hits INT NOT NULL DEFAULT 1, KEY idx_last (last_ms)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");', 'db_()->exec("CREATE TABLE IF NOT EXISTS dsm_warm (h TEXT PRIMARY KEY, nik TEXT, fn TEXT, args TEXT, last_ms INT, hits INT DEFAULT 1)");', $w, $cnt);
if ($cnt !== 1) die("DDL warm tidak ditemukan\n");
$w = str_replace('ON DUPLICATE KEY UPDATE hits = hits + 1, last_ms = VALUES(last_ms)', 'ON CONFLICT(h) DO UPDATE SET hits = hits + 1, last_ms = excluded.last_ms', $w, $cnt); if ($cnt !== 1) die("upsert tidak ditemukan\n");
file_put_contents("$tmp/lib/warm.php", $w);
define('DSM_STORAGE', "$tmp/storage"); define('DSM_PEPPER', 'pepper-uji');
require "$tmp/lib/js.php"; require "$tmp/lib/core.php"; require "$tmp/lib/auth.php"; require "$tmp/lib/rescache.php"; require "$tmp/lib/warm.php";
// fungsi tiruan (nama sama dengan aslinya), memakai guard_() asli
$GLOBALS['CALLS'] = 0;
function apiDashboard($token, $period, $selection) { $GLOBALS['CALLS']++; $u = guard_($token); if (!empty($GLOBALS['BOOM'])) throw new Exception('boom'); if (!empty($GLOBALS['SWAP'])) prop_set_('ACTIVE', 'F2'); return ['who' => $u['nik'], 'period' => $period, 'sel' => $selection, 'n' => 1.5, 'e' => js_obj([]), 'customers' => [['id' => 'C1', 'hp' => '08']]]; }
function apiDOS($token, $period = null, $selection = null, $filter = null, $page = null) { $GLOBALS['CALLS']++; $u = guard_($token); return ['who' => $u['nik'], 'args' => [$period, $selection, $filter, $page]]; }
$pass = 0; $fail = 0; function t($n, $ok) { global $pass, $fail; $ok ? $pass++ : $fail++; echo ($ok ? '  ✓ ' : '  ✗ ') . $n . "\n"; }
$people = [['nik' => 'S1', 'nama' => 'Sales', 'role' => 'SALES'], ['nik' => 'R1', 'nama' => 'Rgm', 'role' => 'RGM'], ['nik' => 'T1', 'nama' => 'Tele', 'role' => 'TELEMARKETING'],
           ['nik' => 'X1', 'nama' => 'Nonaktif', 'role' => 'SALES'], ['nik' => 'W1', 'nama' => 'Wajib ganti', 'role' => 'SALES']];
writeJsonId_('F1', ['meta' => ['roleSchema' => 2, 'people' => $people, 'customers' => [], 'products' => []]]); writeJsonId_('F2', ['meta' => ['roleSchema' => 2, 'people' => $people, 'customers' => [], 'products' => []]]);
prop_set_('ACTIVE', 'F1');
$GLOBALS['T_ACC'] = ['S1' => ['AKTIF' => true, 'WAJIB_GANTI' => false, 'VERSI' => 3], 'R1' => ['AKTIF' => true, 'WAJIB_GANTI' => false, 'VERSI' => 1], 'T1' => ['AKTIF' => true, 'WAJIB_GANTI' => false, 'VERSI' => 1],
                     'X1' => ['AKTIF' => false, 'WAJIB_GANTI' => false, 'VERSI' => 1], 'W1' => ['AKTIF' => true, 'WAJIB_GANTI' => true, 'VERSI' => 1]];
$login = function ($nik, $extra = []) { $tok = hash_(uuid_() . $nik . mt_rand()); session_put_('SESSION_' . hash_($tok), array_merge(['nik' => $nik, 'version' => $GLOBALS['T_ACC'][$nik]['VERSI'], 'until' => now_ms() + 3600000, 'role' => 'X', 'touched' => now_ms()], $extra)); return $tok; };
// simulasikan request user (seperti api.php): kunci cache -> catat
$req = function ($tok, $fn, ...$a) { $args = array_merge([$tok], $a); $k = dsm_cache_key_($fn, $args); if ($k !== null) warmNote_($fn, $args); return $k; };
$tS1 = $login('S1'); $tR1 = $login('R1'); $tT1 = $login('T1'); $tX1 = $login('X1');
$req($tS1, 'apiDashboard', '2026-11', 'ALL'); $req($tS1, 'apiDashboard', '2026-11', 'ALL'); $req($tS1, 'apiDOS', '2026-11', 'ALL', ['brand' => 'ITEL'], 0);
$req($tR1, 'apiDashboard', '2026-11', 'R1'); $req($tR1, 'apiExec', '2026-11', 'ALL'); // apiExec tidak dicatat
$GLOBALS['T_ACC']['T1']['AKTIF'] = true; warmNote_('apiDashboard', [$tT1, '2026-11', 'ALL']); // tidak lewat key -> tidak ada nik -> tidak dicatat
$GLOBALS['DSM_CACHE_NIK'] = 'T1'; warmNote_('apiDashboard', [$tT1, '2026-11', 'ALL']); // paksa: Telemarketing tercatat, harus dilewati saat panaskan
$GLOBALS['DSM_CACHE_NIK'] = 'X1'; warmNote_('apiDashboard', [$tX1, '2026-11', 'ALL']); $GLOBALS['DSM_CACHE_NIK'] = 'W1'; warmNote_('apiDashboard', ['t', '2026-11', 'ALL']);
$rows = db_()->query('SELECT nik, fn, hits FROM dsm_warm ORDER BY nik, fn')->fetchAll();
t('pencatatan: hanya apiDashboard/apiDOS, hitungan naik', count($rows) === 6 && (int) array_values(array_filter($rows, fn($r) => $r['nik'] === 'S1' && $r['fn'] === 'apiDashboard'))[0]['hits'] === 2 && !array_filter($rows, fn($r) => $r['fn'] === 'apiExec'));
warmNote_('apiDashboard', [$tS1, 'X', 'Y']); t('penanda akun sekali pakai: catatan tanpa kunci cache tidak tercatat', (int) db_()->query("SELECT COUNT(*) FROM dsm_warm WHERE args LIKE '%\"X\"%'")->fetchColumn() === 0);
$sessBefore = (int) db_()->query('SELECT COUNT(*) FROM _sessions')->fetchColumn();
$r = warmRun_(60000);
t('panaskan: 3 kombinasi dihitung (S1 x2, R1 x1); Tele/nonaktif/wajib ganti dilewati', $r['done'] === 3 && $r['skip'] === 3 && $r['fail'] === 0);
t('sesi sementara selalu dihapus', (int) db_()->query('SELECT COUNT(*) FROM _sessions')->fetchColumn() === $sessBefore);
// cache hasil panaskan = cache yang dicari request asli user (token berbeda, nik sama)
$k = dsm_cache_key_('apiDashboard', [$tS1, '2026-11', 'ALL']); $c = dsm_cache_get_($k);
t('cache Dashboard ada & diawali status kontak (CS:)', is_string($c) && str_starts_with($c, 'CS:' . (prop_get_('DEALER_CONTACT_STATE') ?? '') . "\n"));
$expect = json_encode(js_norm(['ok' => true, 'result' => ['who' => 'S1', 'period' => '2026-11', 'sel' => 'ALL', 'n' => 1.5, 'e' => js_obj([]), 'customers' => [['id' => 'C1', 'hp' => '08']]]]), (JS_JSON_FLAGS & ~JSON_PRESERVE_ZERO_FRACTION) | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
t('isi cache byte-sama dengan jawaban api.php (atas nama S1, bukan akun lain)', dsm_cache_contact_($k) === $expect);
$kd = dsm_cache_key_('apiDOS', [$tS1, '2026-11', 'ALL', ['brand' => 'ITEL'], 0]); $cd = dsm_cache_get_($kd);
t('cache DOS: argumen filter ikut persis, tanpa awalan CS:', is_string($cd) && !str_starts_with($cd, 'CS:') && str_contains($cd, '"args":["2026-11","ALL",{"brand":"ITEL"},0]'));
$GLOBALS['CALLS'] = 0; $r = warmRun_(60000); t('jalan ulang: yang sudah ada di cache dilewati (tidak dihitung lagi)', $r['done'] === 0 && $GLOBALS['CALLS'] === 0);
array_map('unlink', glob(dsm_cache_dir_() . '/*'));
$GLOBALS['BOOM'] = 1; $r = warmRun_(60000); unset($GLOBALS['BOOM']); t('fungsi error: dicatat gagal, lanjut, sesi tetap dihapus', $r['fail'] === 2 && $r['done'] === 1 && (int) db_()->query('SELECT COUNT(*) FROM _sessions')->fetchColumn() === $sessBefore);
array_map('unlink', glob(dsm_cache_dir_() . '/*'));
prop_set_('WARM_OFF', '1'); $r = warmRun_(60000); prop_delete_('WARM_OFF'); t('properti WARM_OFF=1: tidak jalan', $r['done'] === 0 && $r['stop'] === 'WARM_OFF');
$r = warmRun_(-1); t('waktu habis: berhenti', $r['done'] === 0 && $r['stop'] === 'waktu habis');
$GLOBALS['SWAP'] = 1; $r = warmRun_(60000); unset($GLOBALS['SWAP']); t('snapshot berganti di tengah: berhenti & hasil lama TIDAK disimpan', $r['stop'] === 'snapshot berganti' && !glob(dsm_cache_dir_() . '/*.json'));
prop_set_('ACTIVE', 'F1');
// sesi panaskan ditolak di pintu browser (api.php)
t('api.php menolak sesi bertanda warm', str_contains(file_get_contents($root . '/api.php'), "!empty(\$as['warm'])"));
exec('rm -rf ' . escapeshellarg($tmp));
echo "\nHasil: $pass lolos, $fail gagal\n"; exit($fail ? 1 : 0);
