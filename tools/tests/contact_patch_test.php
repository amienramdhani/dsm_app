<?php
/**
 * Uji tempel kontak dealer pada cache apiDashboard (lib/rescache.php): hasil harus byte-sama dengan hitung ulang.
 * Tanpa MySQL: lib disalin ke folder sementara; db_() memakai SQLite, authSession_/account_ memakai data uji.
 * Jalankan: php tools/tests/contact_patch_test.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2); $tmp = sys_get_temp_dir() . '/dsm_cp_' . getmypid();
@mkdir("$tmp/lib", 0775, true); copy("$root/config.php", "$tmp/config.php");
foreach (glob("$root/lib/*.php") as $f) copy($f, "$tmp/lib/" . basename($f));
$rw = function (string $file, string $fn, string $body) {
    $s = file_get_contents($file); $i = strpos($s, "function $fn("); if ($i === false) die("tidak ada $fn\n");
    $b = strpos($s, '{', $i); $d = 0; for ($j = $b; $j < strlen($s); $j++) { if ($s[$j] === '{') $d++; elseif ($s[$j] === '}') { $d--; if ($d === 0) break; } }
    file_put_contents($file, substr($s, 0, $b) . '{' . $body . '}' . substr($s, $j + 1));
};
$rw("$tmp/lib/data.php", 'db_', 'static $p = null; if (!$p) { $p = new PDO("sqlite::memory:", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); $p->exec("CREATE TABLE _props (k TEXT PRIMARY KEY, v TEXT)"); } return $p;');
$rw("$tmp/lib/auth.php", 'authSession_', '$s = $GLOBALS["T_SESS"][$token] ?? null; if (!$s) fail_("SESI: Silakan masuk kembali."); $a = $GLOBALS["T_ACC"][$s["nik"]] ?? null; if (!$a) fail_("SESI: Silakan masuk kembali."); return ["s" => $s, "a" => $a];');
$rw("$tmp/lib/auth.php", 'account_', 'return $GLOBALS["T_ACC"][text_($nik)] ?? null;');
define('DSM_STORAGE', "$tmp/storage");
require "$tmp/lib/js.php"; require "$tmp/lib/core.php"; require "$tmp/lib/auth.php"; require "$tmp/lib/rescache.php";
$pass = 0; $fail = 0; function t($n, $ok) { global $pass, $fail; $ok ? $pass++ : $fail++; echo ($ok ? '  ✓ ' : '  ✗ ') . $n . "\n"; }
$enc = fn($d) => json_encode(js_norm($d), (JS_JSON_FLAGS & ~JSON_PRESERVE_ZERO_FRACTION) | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE); // = dsm_reply
// bentuk mirip apiDashboard: customers (3 sumber), objek kosong {}, angka pecahan, teks unicode, id angka
$make = function (array $ct) {
  $cu = function ($id, $nama) use ($ct) { $c = $ct[$id] ?? ['hp' => '', 'alamat' => '']; return ['id' => $id, 'nama' => $nama, 'nik' => 'S1', 'region' => 'CRB', 'kota' => 'CIREBON', 'alamat' => $c['alamat'], 'hp' => $c['hp'], 'status' => 'AKTIF', 'bs' => js_obj([]), 'bsr' => js_obj(['ITEL' => ['last' => '2026-10', 'gap' => 0]]), 'current' => ['qty' => 3, 'omzet' => 1500000.5, 'aksesori' => 0]]; };
  $rows = []; for ($i = 0; $i < 3000; $i++) $rows[] = $cu('D' . $i, 'TOKO ' . $i . ' Ñandú');
  $rows[] = $cu('C1', 'TOKO A'); $rows[] = $cu('1001', 'TOKO ANGKA');
  return ['ok' => true, 'result' => ['period' => '2026-10', 'total' => ['qty' => 10, 'omzet' => 2.25], 'brands' => [['brand' => 'ITEL', 'current' => ['qty' => 1]]], 'customers' => $rows, 'empty' => js_obj([]), 'list' => [], 'kpi' => null,
    'tm' => ['customers' => [$cu('C1', 'TOKO A')]], 'people' => [['nik' => 'C1', 'id' => 'X', 'hp' => 'tidak berubah']]]];
};
$ct1 = ['C1' => ['hp' => '0812', 'alamat' => 'JL A'], '1001' => ['hp' => '', 'alamat' => 'JL ANGKA']];
$ct2 = ['C1' => ['hp' => '0899', 'alamat' => 'JL BARU / "kutip"'], '1001' => ['hp' => '0811', 'alamat' => 'JL ANGKA'], 'D5' => ['hp' => '0855', 'alamat' => 'JL D5']];
$j1 = $enc($make($ct1)); $j2 = $enc($make($ct2));
$ov = []; foreach ($ct2 as $k => $v) $ov[$k] = ['pic' => 'P', 'hp' => $v['hp'], 'alamat' => $v['alamat']];
$p = dsm_contact_patch_json_($j1, $ov);
t('tempel kontak = byte-sama dengan hitung ulang (' . round(strlen($j1) / 1024) . ' KB)', $p === $j2);
t('objek kosong {} tetap {} (tidak jadi [])', str_contains($p, '"empty":{}') && str_contains($p, '"bs":{}'));
t('objek bukan dealer (people: id lain) tidak disentuh', str_contains($p, '"hp":"tidak berubah"'));
t('tanpa override: isi tidak berubah', dsm_contact_patch_json_($j1, []) === $j1);
t('JSON rusak: null (hitung ulang)', dsm_contact_patch_json_('{rusak', $ov) === null);
// alur cache: simpan dengan status kontak A, status berubah ke B -> hasil ditempel & disimpan ulang
prop_set_('ACTIVE', 'F1');
prop_set_('DEALER_CONTACT_STATE', writeJson_('dcA', ['requests' => [], 'overrides' => js_obj(array_map(fn($v) => ['pic' => 'P'] + $v, $ct1))]));
$key = 'k' . md5('x'); $csA = prop_get_('DEALER_CONTACT_STATE');
dsm_cache_put_($key, 'CS:' . $csA . "\n" . $j1);
t('status kontak sama: dikirim apa adanya', dsm_cache_contact_($key) === $j1);
prop_set_('DEALER_CONTACT_STATE', writeJson_('dcB', ['requests' => [], 'overrides' => js_obj($ov)]));
t('status kontak berubah: kontak terbaru ditempel', dsm_cache_contact_($key) === $j2);
t('hasil tempelan disimpan ulang (baca berikutnya tanpa tempel)', str_starts_with(dsm_cache_get_($key), 'CS:' . prop_get_('DEALER_CONTACT_STATE') . "\n"));
dsm_cache_put_($key, $j1); t('cache format lama (tanpa CS:) tidak dipakai', dsm_cache_contact_($key) === null);
t('hanya apiDashboard yang ditempel', dsm_contact_patch_on_('apiDashboard') && !dsm_contact_patch_on_('apiDOS') && !dsm_contact_patch_on_('apiTransactions'));
prop_set_('CONTACT_PATCH_OFF', '1'); t('properti CONTACT_PATCH_OFF=1: mati', !dsm_contact_patch_on_('apiDashboard')); prop_delete_('CONTACT_PATCH_OFF');
$t0 = microtime(true); dsm_contact_patch_json_($j1, $ov); printf("  (waktu tempel %.0f ms untuk %d KB)\n", (microtime(true) - $t0) * 1000, strlen($j1) / 1024);
exec('rm -rf ' . escapeshellarg($tmp));
echo "\nHasil: $pass lolos, $fail gagal\n"; exit($fail ? 1 : 0);
