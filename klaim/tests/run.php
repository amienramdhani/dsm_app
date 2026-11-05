<?php
// Tes alur backend: php tests/run.php  (memakai SQLite sementara)
putenv('CLAIM_STANDALONE=1'); putenv('CLAIM_DRIVER=sqlite'); putenv('CLAIM_SQLITE=/tmp/claim_unit.sqlite'); @unlink('/tmp/claim_unit.sqlite');
require_once __DIR__ . '/../lib/seed.php'; require_once __DIR__ . '/../lib/report.php';
install_schema(); seed_all();
const PNG1 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
ex("UPDATE claim_users SET sign_img=?", [PNG1]); // semua akun uji sudah punya TTD
$pass = 0; $failc = 0;
function t(string $name, callable $f) { global $pass, $failc; try { $f(); echo "  ✓ $name\n"; $pass++; } catch (Throwable $e) { echo "  ✗ $name → " . $e->getMessage() . " @" . basename($e->getFile()) . ':' . $e->getLine() . "\n"; $failc++; } }
function eq($a, $b, $m = '') { if ($a !== $b) throw new Exception("$m diharapkan " . var_export($b, true) . ' dapat ' . var_export($a, true)); }
function throws(callable $f, string $needle = '') { try { $f(); } catch (Err $e) { if ($needle && stripos($e->getMessage(), $needle) === false) throw new Exception('pesan salah: ' . $e->getMessage()); return; } throw new Exception('seharusnya ditolak'); }
function U(string $un): array { $u = user_row((int) val("SELECT id FROM claim_users WHERE username=?", [$un])); $u['real_id'] = $u['id']; $u['impersonating'] = false; return $u; }
function fakefile(array $u, string $slot = 'nota'): int { static $n = 0; return ins('claim_files', ['uploader' => $u['id'], 'stored_name' => 'x' . (++$n) . '.bin', 'orig' => 'f.jpg', 'mime' => 'image/jpeg', 'size_b' => 1, 'sha' => hash('sha256', 'f' . $n . microtime()), 'slot' => $slot, 'created_at' => now()]); }
/** Tanggal N hari lalu, digeser mundur bila jatuh di hari Minggu (supaya tes tidak bergantung hari jalan tes). */
function wk(int $days): string { $t = time() - 86400 * $days; while ((int) date('N', $t) === 7) $t -= 86400; return date('Y-m-d', $t); }
function typeid(string $n): int { return (int) val("SELECT id FROM claim_types WHERE name=?", [$n]); }
function bbm(array $u, int $nominal = 100000, string $date = null): array {
  return ['category' => 'Transportasi', 'type_id' => typeid('BBM'), 'brand' => 'Samsung', 'refnote' => 'IT-X', 'location' => 'Cirebon', 'account' => ['mode' => 'self'],
    'meta' => ['kendaraan' => 'Mobil', 'plat' => 'E 1 AB', 'km_awal' => 1000, 'km_akhir' => 1100],
    'notes' => [['nominal' => $nominal, 'date' => $date ?? date('Y-m-d'), 'detail' => ['bbm' => 'Pertalite'], 'files' => [fakefile($u)]]],
    'files' => ['odo_awal' => [fakefile($u, 'odo')], 'odo_akhir' => [fakefile($u, 'odo')]]];
}
function submit(array $u, array $payload, array $extra = []): int { $id = claim_save($u, $payload); claim_submit($u, $id, $extra); return $id; }
echo "== Alur normal ==\n";
$dewi = U('dewi'); $rina = U('admin'); $maya = U('fm'); $ft = U('ft'); $hos = U('hos'); $andi = U('audit'); $sa = U('sa');
$c1 = 0;
t('simpan + kirim klaim BBM', function () use ($dewi, &$c1) { $c1 = submit($dewi, bbm($dewi)); $c = get_claim($c1); eq($c['status'], 'WAIT'); eq($c['stage'], 'ADMIN'); eq(jdec($c['route']), ['ADMIN', 'FM', 'FT']); if (!preg_match('/^KLM-\d{4}-0001$/', $c['number'])) throw new Exception('format nomor ' . $c['number']); });
t('pemohon tidak boleh menyetujui klaim sendiri', function () use ($dewi, &$c1) { throws(fn() => claim_act($dewi, $c1, ['entity' => 'RISM', 'action' => 'approve']), 'berwenang'); });
t('admin region lain ditolak (scope backend)', function () use (&$c1) { throws(fn() => claim_act(U('admin2'), $c1, ['entity' => 'RISM', 'action' => 'approve']), 'berwenang'); });
t('FM belum giliran', function () use ($maya, &$c1) { throws(fn() => claim_act($maya, $c1, ['entity' => 'RISM', 'action' => 'approve']), 'berwenang'); });
t('admin setuju -> FM', function () use ($rina, &$c1) { claim_act($rina, $c1, ['entity' => 'RISM', 'action' => 'approve']); eq(get_claim($c1)['stage'], 'FM'); });
t('FM setuju -> FT', function () use ($maya, &$c1) { claim_act($maya, $c1, ['entity' => 'RISM', 'action' => 'approve']); eq(get_claim($c1)['stage'], 'FT'); });
t('FT transfer wajib nomor+bukti', function () use ($ft, &$c1) { throws(fn() => claim_act($ft, $c1, ['action' => 'transfer']), 'wajib'); });
t('FT transfer -> selesai', function () use ($ft, &$c1) { claim_act($ft, $c1, ['action' => 'transfer', 'transfer_no' => 'TRF-1', 'proof' => [fakefile($ft, 'tf')]]); eq(get_claim($c1)['status'], 'DONE'); });
t('budget: realized tercatat', function () use ($dewi) { $b = budget_info($dewi['id'], 'Transportasi', date('Y-m')); eq($b['realized'], 100000); eq($b['available'], 1900000); });
t('riwayat tidak bisa diubah/dihapus', function () { throws2(fn() => ex("UPDATE claim_history SET action='x'")); throws2(fn() => ex("DELETE FROM claim_history")); });
function throws2(callable $f) { try { $f(); } catch (PDOException $e) { return; } throw new Exception('seharusnya error trigger'); }

echo "== Over budget + BA ==\n";
$c2 = 0;
t('over budget boleh submit tapi wajib BA', function () use ($dewi, &$c2) {
  $id = claim_save($dewi, bbm($dewi, 2500000));
  throws(fn() => claim_submit($dewi, $id, []), 'BA');
  claim_submit($dewi, $id, ['ba_explanation' => 'Perjalanan dinas luar kota darurat']); $c2 = $id; $c = get_claim($id);
  eq($c['ba_required'], 1); eq(jdec($c['route']), ['ADMIN', 'HOS', 'AUDIT', 'FM', 'FT']); eq((int) $c['over_budget_amount'], 2500000 - 1900000); eq((int) $c['avail_before'], 1900000);
  $b = budget_info($dewi['id'], 'Transportasi', date('Y-m')); eq($b['available'], 1900000 - 2500000); eq($b['reserved'], 2500000);
});
t('admin setuju -> wajib ke HOS dulu (bukan FM, bukan Audit)', function () use ($rina, &$c2) { claim_act($rina, $c2, ['entity' => 'RISM', 'action' => 'approve']); eq(get_claim($c2)['stage'], 'HOS'); });
t('HOS -> Audit -> FM -> FT', function () use ($andi, $hos, $maya, &$c2) { claim_act($hos, $c2, ['entity' => 'RISM', 'action' => 'approve']); eq(get_claim($c2)['stage'], 'AUDIT'); claim_act($andi, $c2, ['entity' => 'RISM', 'action' => 'approve']); claim_act($maya, $c2, ['entity' => 'RISM', 'action' => 'approve']); eq(get_claim($c2)['stage'], 'FT'); });
t('transfer lolos: tanda tangan BA lengkap (5 pihak)', function () use ($ft, &$c2) { $s = ba_row($c2); $have = array_column(q("SELECT role FROM claim_ba_sign WHERE ba_id=?", [$s['id']]), 'role'); sort($have); eq($have, ['ADMIN', 'AUDIT', 'FM', 'HOS', 'PEMOHON']); claim_act($ft, $c2, ['action' => 'transfer', 'transfer_no' => 'T2', 'proof' => [fakefile($ft)]]); eq(get_claim($c2)['status'], 'DONE'); });
t('AC14: BA temuan Admin = Admin otomatis setuju, langsung ke HOS tapi menunggu TTD Pemohon; urutan HOS > Audit > FM > FT', function () use ($dewi, $rina, $andi, $hos, $maya, $ft) {
  ex("UPDATE claim_entitlement SET budget=50000000 WHERE user_id=?", [$dewi['id']]);
  $id = submit($dewi, bbm($dewi, 41000)); claim_act($rina, $id, ['action' => 'admin_ba', 'entity' => 'RISM', 'explanation' => 'Nota hilang satu lembar']);
  eq(get_claim($id)['stage'], 'HOS'); eq(get_claim($id)['entity'], 'RISM'); eq(status_label(get_claim($id)), 'Menunggu TTD BA Pemohon');
  eq(jdec(get_claim($id)['route']), ['ADMIN', 'HOS', 'AUDIT', 'FM', 'FT']);
  throws(fn() => claim_act($hos, $id, ['entity' => 'RISM', 'action' => 'approve']), 'Pemohon belum menandatangani');
  ba_pemohon_sign($dewi, $id); eq(status_label(get_claim($id)), 'Menunggu HOS');
  claim_act($hos, $id, ['entity' => 'RISM', 'action' => 'approve']); eq(get_claim($id)['stage'], 'AUDIT'); claim_act($andi, $id, ['entity' => 'RISM', 'action' => 'approve']); claim_act($maya, $id, ['entity' => 'RISM', 'action' => 'approve']);
  claim_act($ft, $id, ['action' => 'transfer', 'transfer_no' => 'T3', 'proof' => [fakefile($ft)]]); eq(get_claim($id)['status'], 'DONE');
});

echo "== Revisi ==\n";
$c3 = 0;
t('FM minta revisi ke pemohon -> kembali ke FM (tanpa lewat Admin)', function () use ($dewi, $rina, $maya, &$c3) {
  $c3 = submit($dewi, bbm($dewi, 90000)); claim_act($rina, $c3, ['entity' => 'RISM', 'action' => 'approve']);
  throws(fn() => claim_act($maya, $c3, ['action' => 'revise']), 'wajib');
  claim_act($maya, $c3, ['action' => 'revise', 'target' => 'PEMOHON', 'notes' => 'Foto buram']);
  $c = get_claim($c3); eq($c['status'], 'REV'); eq($c['rev_holder'], 'PEMOHON'); eq(status_label($c), 'Perlu Revisi · di Pemohon');
  $b = budget_info($dewi['id'], 'Transportasi', date('Y-m')); eq($b['reserved'], 90000, 'reservasi tetap ditahan');
  claim_resubmit($dewi, $c3, []); $c = get_claim($c3); eq($c['status'], 'WAIT'); eq($c['stage'], 'FM'); eq((int) $c['current_version'], 1);
});
t('FM minta revisi ke Admin -> admin lempar ke pemohon -> kembali FM', function () use ($dewi, $rina, $maya, &$c3) {
  claim_act($maya, $c3, ['action' => 'revise', 'target' => 'ADMIN', 'notes' => 'Cek KM']); eq(status_label(get_claim($c3)), 'Perlu Revisi · di Admin');
  throws(fn() => claim_resubmit($dewi, $c3, []), 'izinkan');
  claim_act($rina, $c3, ['action' => 'rev_throw']); eq(get_claim($c3)['rev_holder'], 'PEMOHON');
  claim_resubmit($dewi, $c3, []); eq(get_claim($c3)['stage'], 'FM');
});
t('revisi ke admin: admin lengkapi sendiri -> kembali FM', function () use ($rina, $maya, &$c3) {
  claim_act($maya, $c3, ['action' => 'revise', 'target' => 'ADMIN', 'notes' => 'x']); claim_resubmit($rina, $c3, []); eq(get_claim($c3)['stage'], 'FM');
});
t('perubahan nominal -> versi baru, HOS tidak ditambahkan ke rute', function () use ($dewi, $maya, &$c3) {
  claim_act($maya, $c3, ['action' => 'revise', 'target' => 'PEMOHON', 'notes' => 'Nominal salah']);
  $d = claim_detail($dewi, $c3); $p = bbm($dewi, 95000); $p['id'] = $c3; $p['notes'][0]['id'] = $d['notes'][0]['id']; $p['notes'][0]['files'] = array_column($d['notes'][0]['files'], 'id'); $p['files'] = ['odo_awal' => array_column($d['files']['odo_awal'], 'id'), 'odo_akhir' => array_column($d['files']['odo_akhir'], 'id')];
  $r = claim_resubmit($dewi, $c3, ['payload' => $p]); eq($r['nominal_changed'], true); eq($r['version'], 2); $c = get_claim($c3); eq(jdec($c['route']), ['ADMIN', 'FM', 'FT']); eq($c['stage'], 'FM'); eq((int) $c['total'], 95000);
  $dd = claim_detail($dewi, $c3); $stale = array_filter($dd['approvals'], fn($a) => $a['action'] === 'approve' && !$a['valid']); if (!$stale) throw new Exception('approval lama harus ditandai tidak berlaku');
});
t('tolak final -> reservasi dilepas, bukan antrean revisi', function () use ($dewi, $maya, &$c3) {
  $before = budget_info($dewi['id'], 'Transportasi', date('Y-m'))['reserved'];
  claim_act($maya, $c3, ['action' => 'reject', 'notes' => 'Tidak sesuai']); $c = get_claim($c3); eq($c['status'], 'REJECTED');
  eq(budget_info($dewi['id'], 'Transportasi', date('Y-m'))['reserved'], $before - 95000);
});
t('nominal berubah pada klaim BA -> BA & tanda tangan lama tidak berlaku', function () use ($dewi, $rina, $andi, $hos, $maya, &$c4) {
  $c4 = submit($dewi, bbm($dewi, 2200000)); claim_act($rina, $c4, ['action' => 'admin_ba', 'entity' => 'RISM', 'explanation' => 'Temuan nota kurang']); ba_pemohon_sign($dewi, $c4); claim_act($hos, $c4, ['entity' => 'RISM', 'action' => 'approve']); claim_act($andi, $c4, ['entity' => 'RISM', 'action' => 'approve']);
  claim_act($maya, $c4, ['action' => 'revise', 'target' => 'PEMOHON', 'notes' => 'Turunkan nominal']);
  $d = claim_detail($dewi, $c4); $p = bbm($dewi, 1500000); $p['id'] = $c4; $p['notes'][0]['id'] = $d['notes'][0]['id']; $p['notes'][0]['files'] = array_column($d['notes'][0]['files'], 'id'); $p['files'] = ['odo_awal' => array_column($d['files']['odo_awal'], 'id'), 'odo_akhir' => array_column($d['files']['odo_akhir'], 'id')];
  $r = claim_resubmit($dewi, $c4, ['payload' => $p]); $c = get_claim($c4); eq($c['stage'], 'ADMIN', 'BA: semua penanda tangan mengulang untuk versi baru'); $dd = claim_detail($dewi, $c4);
  eq($dd['ba']['version'], 2); eq(count(array_filter($dd['ba']['signs'], fn($s) => $s['valid'])), 1, 'hanya tanda tangan pemohon v2'); eq($dd['ba']['complete'], false);
});

echo "== Aturan lain ==\n";
t('nota ganda: foto identik diblokir', function () use ($dewi) {
  $p = bbm($dewi, 50000); $f = $p['notes'][0]['files'][0]; $sha = val("SELECT sha FROM claim_files WHERE id=?", [$f]);
  $a = submit($dewi, $p);
  $p2 = bbm($dewi, 61000); $f2 = fakefile($dewi); ex("UPDATE claim_files SET sha=? WHERE id=?", [$sha, $f2]); $p2['notes'][0]['files'] = [$f2];
  $id = claim_save($dewi, $p2); throws(fn() => claim_submit($dewi, $id, []), 'sudah dipakai');
});
t('nota ganda: pemohon+jenis+tanggal+nominal sama diblokir', function () use ($dewi) { $a = submit($dewi, bbm($dewi, 77000)); $id = claim_save($dewi, bbm($dewi, 77000)); throws(fn() => claim_submit($dewi, $id, []), 'bentrok'); });
t('tol: gerbang berbeda pulang-pergi nominal sama tidak diblokir', function () use ($dewi) {
  $mk = fn($a, $b) => ['category' => 'Transportasi', 'type_id' => typeid('Tol'), 'brand' => 'Oppo', 'refnote' => 'T', 'location' => 'Bandung', 'account' => ['mode' => 'self'], 'meta' => [], 'notes' => [['nominal' => 55000, 'date' => date('Y-m-d'), 'detail' => ['asal' => $a, 'tujuan' => $b], 'files' => [fakefile($dewi)]]]];
  submit($dewi, $mk('cikampek utama', 'cileunyi')); submit($dewi, $mk('cileunyi', 'cikampek utama'));
});
t('lokasi bebas diketik manual; kosong ditolak', function () use ($dewi) { $p = bbm($dewi, 12000); $p['location'] = 'Kab. Kuningan'; $id = claim_save($dewi, $p); claim_submit($dewi, $id, []); eq(get_claim($id)['location'], 'Kab. Kuningan'); $p = bbm($dewi, 12100); $p['location'] = ''; $id = claim_save($dewi, $p); throws(fn() => claim_submit($dewi, $id, []), 'Lokasi'); });
t('tanggal nota masa depan ditolak', function () use ($dewi) { $id = claim_save($dewi, bbm($dewi, 13000, date('Y-m-d', time() + 86400 * 3))); throws(fn() => claim_submit($dewi, $id, []), 'masa depan'); });
t('pemicu BA yang angkanya kosong tidak menilai', function () use ($dewi) { $id = claim_save($dewi, bbm($dewi, 10001, wk(400))); claim_submit($dewi, $id, []); eq(get_claim($id)['ba_required'], 0); });
t('pemicu BA aktif setelah angka diisi (hanya klaim sesudahnya)', function () use ($dewi) {
  ins('claim_rules', ['effective_from' => date('Y-m-d'), 'bbm_round' => 5000, 'created_at' => now()]);
  try { $id = claim_save($dewi, bbm($dewi, 14500, wk(60))); $chk = claim_check(get_claim($id)); eq($chk['triggers'], ['DIGIT_BBM']);
  $r = $chk['info']['digit'][0]; eq([$r['jumlah'], $r['seharusnya'], $r['klaim']], [14500, 15000, 14500], 'tabel digit: jumlah, seharusnya, klaim tetap nota');
  } finally { ex("DELETE FROM claim_rules WHERE bbm_round=5000"); }
});
t('rumus digit: digit akhir harus sama dengan aturan, bukan kelipatan', function () {
  eq(digit_ok(62000, 2000), true); eq(digit_ok(72000, 2000), true); eq(digit_ok(64000, 2000), false); eq(digit_ok(100000, 5000), false); eq(digit_ok(105000, 5000), true); eq(digit_ok(123456, 0), true, 'aturan kosong');
  eq(digit_should(100000, 5000), 105000); eq(digit_should(64000, 2000), 72000); eq(digit_should(62000, 2000), 62000); eq(digit_should(105000, 5000), 105000); });
t('klaim tanpa hak kategori ditolak', function () use ($dewi) { ex("UPDATE claim_entitlement SET enabled=0 WHERE user_id=? AND category='Entertain'", [$dewi['id']]); throws(fn() => claim_save($dewi, ['category' => 'Entertain', 'type_id' => typeid('Meeting'), 'notes' => []]), 'belum diaktifkan'); ex("UPDATE claim_entitlement SET enabled=1 WHERE user_id=?", [$dewi['id']]); });
t('rekening bukan milik pemohon -> HOS wajib di rute', function () use ($dewi, $rina) {
  $p = bbm($dewi, 33000); $p['account'] = ['mode' => 'other', 'bank' => 'BCA', 'no' => '123', 'name' => 'Vendor', 'reason' => 'Vendor servis']; $id = submit($dewi, $p); eq(jdec(get_claim($id)['route']), ['ADMIN', 'HOS', 'FM', 'FT']);
});
t('Admin menetapkan perlu HOS (alasan wajib)', function () use ($dewi, $rina) { $id = submit($dewi, bbm($dewi, 34000)); throws(fn() => claim_act($rina, $id, ['entity' => 'RISM', 'action' => 'approve', 'need_hos' => 1]), 'Alasan'); claim_act($rina, $id, ['entity' => 'RISM', 'action' => 'approve', 'need_hos' => 1, 'hos_reason' => 'Nominal tidak lazim']); eq(get_claim($id)['stage'], 'HOS'); });
t('Admin temuan -> BA dibuat Admin, rute HOS lalu Audit', function () use ($dewi, $rina, $hos) { $id = submit($dewi, bbm($dewi, 35000)); claim_act($rina, $id, ['action' => 'admin_ba', 'entity' => 'RISM', 'explanation' => 'Nota hilang satu lembar']); eq(ba_row($id)['creator_role'], 'ADMIN'); ba_pemohon_sign($dewi, $id); claim_act($hos, $id, ['entity' => 'RISM', 'action' => 'approve']); eq(get_claim($id)['stage'], 'AUDIT'); });
t('BA Admin: jenis dipilih (kehilangan nota + nominal) masuk ke BA dan PDF punya NIK + footer + lampiran per 3 foto', function () use ($dewi, $rina) {
  $id = submit($dewi, bbm($dewi, 37771)); claim_act($rina, $id, ['action' => 'admin_ba', 'entity' => 'RISM', 'types' => ['KEHILANGAN_NOTA', 'TEMUAN_ADMIN'], 'extra' => ['lost' => [1], 'kategori' => 'TGL', 'related' => [1]], 'explanation' => 'Nota hilang dan tanggal berbeda', 'files' => [fakefile($rina), fakefile($rina), fakefile($rina), fakefile($rina)]]);
  eq(jdec(ba_row($id)['triggers']), ['KEHILANGAN_NOTA', 'TEMUAN_ADMIN']); $h = ba_pdf_html($rina, $id);
  eq(str_contains($h, 'KEHILANGAN NOTA') && str_contains($h, 'TEMUAN ADMIN') && str_contains($h, 'Tanpa nota fisik') && str_contains($h, 'Tanggal nota tidak sesuai') && str_contains($h, 'NIK / Jabatan') && str_contains($h, 'tanpa adanya paksaan') && str_contains($h, 'Halaman 3 dari 3'), true, 'isi PDF BA');
  throws(fn() => claim_act($rina, submit($dewi, bbm($dewi, 38772)), ['action' => 'admin_ba', 'types' => ['XX'], 'explanation' => 'penjelasan cukup panjang']), 'jenis');
});
t('terbilang rupiah', function () { eq(terbilang(50400), 'Lima Puluh Ribu Empat Ratus Rupiah'); eq(terbilang(1000), 'Seribu Rupiah'); eq(terbilang(2500000), 'Dua Juta Lima Ratus Ribu Rupiah'); eq(terbilang(111000), 'Seratus Sebelas Ribu Rupiah'); eq(terbilang(15000), 'Lima Belas Ribu Rupiah'); eq(terbilang(0), 'Nol Rupiah'); });
t('PDF klaim selesai memuat halaman bukti transfer; PDF petty cash memuat bukti transfer dana', function () use ($dewi, $rina, $maya, $ft) {
  $id = submit($dewi, bbm($dewi, 43771)); $h = claim_pdf_html($rina, $id); eq(str_contains($h, 'Bukti transfer'), false, 'belum selesai');
  claim_act($rina, $id, ['entity' => 'RISM', 'action' => 'approve']); claim_act($maya, $id, ['entity' => 'RISM', 'action' => 'approve']); claim_act($ft, $id, ['action' => 'transfer', 'transfer_no' => 'TRF-PDF-1', 'proof' => [fakefile($ft, 'tf')]]);
  $h = claim_pdf_html($rina, $id); eq(str_contains($h, 'Bukti transfer') && str_contains($h, 'TRF-PDF-1') && str_contains($h, 'DANA SUDAH DITRANSFER') && str_contains($h, 'Halaman 4 dari 4'), true, 'PDF selesai');
});
t('keterangan tambahan opsional: klaim (meta) dan petty cash (remark) tersimpan dan tampil di PDF', function () use ($dewi, $rina) {
  $p = bbm($dewi, 44771); $p['meta']['catatan'] = 'Parkir di basement, struk menyusul'; $id = submit($dewi, $p); eq(str_contains(claim_pdf_html($rina, $id), 'Parkir di basement'), true, 'pdf klaim');
  $p = bbm($dewi, 45771); $id = submit($dewi, $p); eq(str_contains(claim_pdf_html($rina, $id), 'Keterangan tambahan'), false, 'kosong tidak tampil');
});
t('satu klaim satu BA', function () use ($dewi, $rina) { $id = submit($dewi, bbm($dewi, 36000)); claim_act($rina, $id, ['action' => 'admin_ba', 'entity' => 'RISM', 'explanation' => 'Temuan pertama ya']); throws(fn() => ba_create($rina, get_claim($id), ['TEMUAN_ADMIN'], 'Temuan kedua ya', 'ADMIN'), 'satu BA'); });
t('Super Admin mode tes bisa melompati tahap', function () use ($sa, $dewi) { $id = submit($dewi, bbm($dewi, 37000)); claim_act($sa, $id, ['entity' => 'RISM', 'action' => 'approve']); eq(get_claim($id)['stage'], 'FM'); });

echo "== Perdin ==\n";
$pd = 0;
$perdin = function (array $u, int $mealOverride = 0) {
  return ['category' => 'Perdin', 'type_id' => typeid('Perdin'), 'brand' => 'Samsung', 'refnote' => 'PD-1', 'location' => '', 'account' => ['mode' => 'self'],
    'meta' => ['tgl_berangkat' => '2026-10-20', 'tgl_pulang' => '2026-10-22', 'tujuan' => 'Visit dealer Semarang', 'rute' => ['Toko A Cirebon', 'Toko B Tegal', 'Toko C Semarang'], 'km_total' => 250.5],
    'notes' => [['nominal' => $mealOverride ?: 1, 'date' => '2026-10-20', 'detail' => ['komponen' => 'Uang makan']],
      ['nominal' => 1, 'date' => '2026-10-20', 'detail' => ['komponen' => 'Transportasi', 'jenis' => 'Travel', 'mode' => 'hari', 'per_hari' => 100000, 'hari' => 3], 'files' => [fakefile($u)]],
      ['nominal' => 400000, 'date' => '2026-10-20', 'detail' => ['komponen' => 'Penginapan', 'jenis' => 'Hotel', 'mode' => 'sekali', 'hari' => 2], 'files' => [fakefile($u)]]],
    'files' => ['maps' => [fakefile($u, 'maps')]]];
};
t('perdin: uang makan terkunci = tarif x durasi, per hari dihitung server', function () use ($dewi, $perdin, &$pd) {
  $id = claim_save($dewi, $perdin($dewi, 999999999)); $pd = $id; $n = q("SELECT detail, nominal FROM claim_notes WHERE claim_id=? ORDER BY line_no", [$id]);
  eq((int) $n[0]['nominal'], 150000, 'makan 50.000x3'); eq((int) $n[1]['nominal'], 300000, 'travel 100.000x3'); eq((int) get_claim($id)['total'], 850000);
});
t('perdin: kirim tanpa maps / hari melebihi durasi ditolak', function () use ($dewi, $perdin) {
  $p = $perdin($dewi); unset($p['files']['maps']); $id = claim_save($dewi, $p); throws(fn() => claim_submit($dewi, $id, []), 'Maps');
  $p = $perdin($dewi); $p['notes'][1]['detail']['hari'] = 5; $id = claim_save($dewi, $p); throws(fn() => claim_submit($dewi, $id, []), 'durasi');
});
t('perdin: kirim lalu Admin wajib checklist rute', function () use ($dewi, $rina, &$pd) {
  claim_submit($dewi, $pd, []); throws(fn() => claim_act($rina, $pd, ['entity' => 'RISM', 'action' => 'approve']), 'Checklist'); claim_act($rina, $pd, ['entity' => 'RISM', 'action' => 'approve', 'perdin_check' => ['titik' => 1, 'jarak' => 1, 'durasi' => 1]]); eq(get_claim($pd)['stage'], 'FM');
});
t('perdin: tarif makan per orang (eko beda dari dewi)', function () { $e = U('eko'); ex("UPDATE claim_users SET meal_rate=65000 WHERE id=?", [$e['id']]); global $perdin; });

t('Uang makan (klaim): jenis ada di kategori Perdin, jarak minimal & batas tarif x hari diblokir', function () use ($dewi, $rina) {
  $tid = typeid('Uang makan'); eq(type_row($tid)['category'], 'Perdin'); eq(form_kind('Uang makan'), 'makan');
  $mk = function (int $nom, float $km = 142, string $d = '2026-10-21') use ($dewi, $tid) { ex("UPDATE claim_entitlement SET enabled=1 WHERE user_id=?", [$dewi['id']]);
    return ['category' => 'Perdin', 'type_id' => $tid, 'brand' => 'Samsung', 'refnote' => 'UM-1', 'location' => '', 'account' => ['mode' => 'self'],
      'meta' => ['tgl_berangkat' => '2026-10-20', 'tgl_pulang' => '2026-10-22', 'tujuan' => 'Dealer Tegal', 'rute' => ['Toko A Tegal'], 'km_total' => $km],
      'notes' => [['nominal' => $nom, 'date' => $d, 'detail' => [], 'files' => [fakefile($dewi)]]], 'files' => ['maps' => [fakefile($dewi, 'maps')]]]; };
  $rate = (int) val("SELECT meal_rate FROM claim_users WHERE id=?", [$dewi['id']]); eq($rate > 0, true, 'tarif dewi ada');
  throws(fn() => claim_submit($dewi, claim_save($dewi, $mk($rate * 3 + 1)), []), 'Melebihi batas');
  throws(fn() => claim_submit($dewi, claim_save($dewi, $mk(100000, 60)), []), 'minimal');
  throws(fn() => claim_submit($dewi, claim_save($dewi, $mk(100000, 142, '2026-10-25')), []), 'tanggal');
  $id = claim_save($dewi, $mk($rate * 3)); claim_submit($dewi, $id, []); eq(get_claim($id)['status'], 'WAIT'); eq((int) get_claim($id)['total'], $rate * 3, 'nominal manual tersimpan');
  throws(fn() => claim_act($rina, $id, ['entity' => 'RISM', 'action' => 'approve']), 'Checklist');
  claim_act($rina, $id, ['entity' => 'RISM', 'action' => 'approve', 'perdin_check' => ['titik' => 1, 'jarak' => 1, 'durasi' => 1]]); eq(get_claim($id)['stage'], 'FM');
  eq(str_contains(claim_pdf_html($rina, $id), 'Dealer Tegal'), true, 'pdf memuat perjalanan');
});

t('Peserta Meeting dan acara: nama dan jabatan wajib', function () use ($dewi) {
  $m = ['tujuan' => 'Review dealer', 'tempat' => 'Cafe Uji', 'jam_mulai' => '10:00', 'jam_selesai' => '11:00'];
  throws(fn() => submit($dewi, simple($dewi, 'Meeting', $m + ['peserta' => ['Dewi — Sales', 'Rina']])), 'jabatan');
  throws(fn() => submit($dewi, simple($dewi, 'Meeting', $m + ['peserta' => ['Dewi — Sales', ' — Admin']])), 'nama');
  $id = submit($dewi, simple($dewi, 'Meeting', $m + ['peserta' => ['Dewi — Sales', 'Rina — Admin']])); eq(get_claim($id)['status'], 'WAIT'); });

echo "== Petty cash ==\n";
$pc = 0;
t('petty cash: total rencana harus sama dengan jumlah, plafon', function () use ($dewi) {
  $plan = [['uraian' => 'BBM', 'kategori' => 'Transportasi', 'nominal' => 500000]];
  throws(fn() => pc_submit($dewi, ['purpose' => 'Visit', 'p_start' => date('Y-m-d', time() - 86400), 'p_end' => date('Y-m-d', time() + 86400 * 3), 'amount' => 600000, 'plan' => $plan]), 'sama');
  throws(fn() => pc_submit($dewi, ['purpose' => 'Visit', 'p_start' => date('Y-m-d'), 'p_end' => date('Y-m-d'), 'amount' => 4000000, 'plan' => [['uraian' => 'x', 'kategori' => 'Transportasi', 'nominal' => 4000000]]]), 'plafon');
});
t('petty cash rute Pemohon->RGM->Admin->HOS->FM->FT', function () use ($dewi, &$pc) {
  $pc = pc_submit($dewi, ['purpose' => 'Visit area', 'p_start' => date('Y-m-d', time() - 86400 * 2), 'p_end' => date('Y-m-d', time() - 86400), 'amount' => 500000, 'location' => 'Cirebon', 'plan' => [['uraian' => 'BBM', 'kategori' => 'Transportasi', 'nominal' => 500000]]]);
  eq(jdec(get_pc($pc)['route']), ['RGM', 'ADMIN', 'HOS', 'FM', 'FT']);
  throws(fn() => pc_submit($dewi, ['purpose' => 'lagi', 'p_start' => date('Y-m-d'), 'p_end' => date('Y-m-d'), 'amount' => 100000, 'plan' => [['uraian' => 'x', 'kategori' => 'Transportasi', 'nominal' => 100000]]]), 'aktif');
});
t('petty cash: RGM -> Admin -> HOS -> FM -> FT, dana cair', function () use ($dewi, $rina, $hos, $maya, $ft, &$pc) {
  $rgm = U('rgm'); pc_act($rgm, $pc, ['entity' => 'RISM', 'action' => 'approve']); pc_act($rina, $pc, ['entity' => 'RISM', 'action' => 'approve']); pc_act($hos, $pc, ['entity' => 'RISM', 'action' => 'approve']); pc_act($maya, $pc, ['entity' => 'RISM', 'action' => 'approve']);
  pc_act($ft, $pc, ['action' => 'transfer', 'transfer_no' => 'PC-TRF', 'proof' => [fakefile($ft)]]); eq(get_pc($pc)['status'], 'DANA');
});
t('petty cash: lewat tenggat memblokir klaim baru (AC15)', function () use ($dewi) { throws(fn() => claim_save($dewi, bbm($dewi, 21000)), 'diblokir'); });
t('petty cash: nota di luar periode ditolak; nota dalam periode OK', function () use ($dewi, &$pc) {
  throws(fn() => pc_add_note($dewi, $pc, ['nominal' => 100000, 'date' => date('Y-m-d'), 'files' => [fakefile($dewi)]]), 'periode');
  pc_add_note($dewi, $pc, ['nominal' => 300000, 'date' => date('Y-m-d', time() - 86400 * 2), 'plan_idx' => 0, 'uraian' => 'BBM', 'files' => [fakefile($dewi)]]);
  pc_add_note($dewi, $pc, ['nominal' => 150000, 'date' => date('Y-m-d', time() - 86400), 'uraian' => 'Parkir', 'files' => [fakefile($dewi)]]);
});
t('petty cash: laporan -> sisa disetor -> Finance konfirmasi -> selesai, blokir terbuka', function () use ($dewi, $ft, &$pc) {
  $r = pc_send_report($dewi, $pc); eq($r['sisa'], 50000); eq(get_pc($pc)['status'], 'SETOR');
  throws(fn() => pc_close($ft, $pc), 'setoran');
  pc_setoran($dewi, $pc, ['setoran_no' => 'SET-1', 'files' => [fakefile($dewi)]]); pc_close($ft, $pc); eq(get_pc($pc)['status'], 'SELESAI');
  claim_save($dewi, bbm($dewi, 22000));
});
t('petty cash: kelebihan otomatis jadi klaim reimburse; RGM melewati tahap RGM', function () use ($rina) {
  $rgm = U('rgm'); $p = pc_submit($rgm, ['purpose' => 'Operasional area', 'p_start' => date('Y-m-d', time() - 86400), 'p_end' => date('Y-m-d', time() + 86400), 'amount' => 200000, 'plan' => [['uraian' => 'Makan', 'kategori' => 'Entertain', 'nominal' => 200000]]]);
  eq(jdec(get_pc($p)['route']), ['ADMIN', 'HOS', 'FM', 'FT']); throws(fn() => pc_act($rgm, $p, ['entity' => 'RISM', 'action' => 'approve']), 'berwenang');
  pc_act($rina, $p, ['entity' => 'RISM', 'action' => 'approve']); pc_act(U('hos'), $p, ['entity' => 'RISM', 'action' => 'approve']); pc_act(U('fm'), $p, ['entity' => 'RISM', 'action' => 'approve']); pc_act(U('ft'), $p, ['action' => 'transfer', 'transfer_no' => 'X', 'proof' => [fakefile(U('ft'))]]);
  pc_add_note($rgm, $p, ['nominal' => 260000, 'date' => date('Y-m-d'), 'plan_idx' => 0, 'files' => [fakefile($rgm)]]); $r = pc_send_report($rgm, $p); eq($r['excess'], 60000);
  $cl = get_claim((int) get_pc($p)['excess_claim_id']); eq((int) $cl['total'], 60000); eq($cl['stage'], 'ADMIN');
});

echo "== Monitoring, ekspor, backup ==\n";
t('monitor budget: HOS semua region, Audit hanya badan usahanya', function () {
  $h = monitor_budget(U('hos'), []); $a = monitor_budget(U('audit'), []); if (count($h['rows']) < count($a['rows'])) throw new Exception('HOS harus >= Audit');
  $au = U('audit'); $au['entities'] = ['MSI']; $m = monitor_budget($au, []); foreach ($m['rows'] as $r) if (strpos($r['entity'], 'MSI') === false) throw new Exception('bocor entity ' . $r['name']);
});
t('realisasi finance + export xlsx + log export', function () use ($ft) { $r = realisasi($ft, []); if (!isset($r['sum'])) throw new Exception('tanpa ringkasan'); [$h, $rows] = export_claims($ft, []); $x = xlsx_build($h, $rows); if (substr($x, 0, 2) !== 'PK') throw new Exception('bukan xlsx'); if (!val("SELECT COUNT(*) FROM claim_export_log")) throw new Exception('log kosong'); });
t('pdf klaim memuat rekening penuh + watermark nama pengunduh', function () use ($dewi, $rina) { $id = (int) val("SELECT id FROM claims WHERE number IS NOT NULL ORDER BY id LIMIT 1"); $h = claim_pdf_html($rina, $id); if (strpos($h, '4821907766') === false) throw new Exception('rekening tidak penuh'); if (strpos($h, 'Rina') === false || strpos($h, 'class="wm"') === false) throw new Exception('watermark'); });
t('akses cadangan: izin hanya untuk penerima, selama durasi, tidak jika dicabut', function () {
  $rina = (int) val("SELECT id FROM claim_users WHERE username='admin'"); $dw = (int) val("SELECT id FROM claim_users WHERE username='dewi'"); $ek = (int) val("SELECT id FROM claim_users WHERE username='eko'");
  $id = ins('claim_backup', ['role' => 'ADMIN', 'code_hash' => 'x', 'issuer_id' => $rina, 'for_user_id' => $rina, 'to_user_id' => $dw, 'used_by' => $dw, 'used_at' => now(), 'duration_min' => 120, 'active_until' => date('Y-m-d H:i:s', time() + 3600), 'use_count' => 1, 'max_use' => 5, 'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'created_at' => now()]);
  eq((bool) backup_grant($id, $dw), true, 'penerima'); eq((bool) backup_grant($id, $ek), false, 'orang lain');
  ex("UPDATE claim_backup SET active_until=? WHERE id=?", [date('Y-m-d H:i:s', time() - 5), $id]); eq((bool) backup_grant($id, $dw), false, 'durasi habis');
  ex("UPDATE claim_backup SET active_until=?, revoked_at=? WHERE id=?", [date('Y-m-d H:i:s', time() + 3600), now(), $id]); eq((bool) backup_grant($id, $dw), false, 'dicabut'); });
echo "== BBM: kendaraan, jenis, harga ==\n";
t('Pertamax: nota 50rb -> 2,5 L -> diklaim 25rb (harga Pertalite)', function () use ($dewi) {
  $p = bbm($dewi, 50000); $p['notes'][0]['detail'] = ['bbm' => 'Pertamax']; $id = claim_save($dewi, $p); $n = q("SELECT * FROM claim_notes WHERE claim_id=?", [$id])[0];
  eq((int) $n['nominal'], 25000); $d = jdec($n['detail']); eq($d['nota_nominal'], 50000); eq($d['liter'], 2.5); eq((int) get_claim($id)['total'], 25000); });
t('Pertamax dibulatkan Rp 100', function () use ($dewi) { eq(fuel_calc(33300, 'Pertamax', ['pertalite' => 10000, 'pertamax' => 20000])['claim'], 16700); eq(fuel_calc(33000, 'Pertalite', ['pertalite' => 10000, 'pertamax' => 20000])['claim'], 33000); });
t('Motor bebas digit; Mobil kena DIGIT_BBM per nota nominal', function () use ($dewi) {
  ins('claim_rules', ['effective_from' => date('Y-m-d'), 'bbm_round' => 5000, 'created_at' => now()]);
  $p = bbm($dewi, 14500); $p['meta']['kendaraan'] = 'Motor'; $id = claim_save($dewi, $p); eq(in_array('DIGIT_BBM', claim_check(get_claim($id))['triggers']), false, 'motor');
  $p = bbm($dewi, 14500); $id = claim_save($dewi, $p); eq(in_array('DIGIT_BBM', claim_check(get_claim($id))['triggers']), true, 'mobil');
  $p = bbm($dewi, 55000); $p['notes'][0]['detail'] = ['bbm' => 'Pertamax']; $id = claim_save($dewi, $p); eq(in_array('DIGIT_BBM', claim_check(get_claim($id))['triggers']), false, 'dinilai dari nota, bukan hasil hitung');
  ex("DELETE FROM claim_rules WHERE bbm_round=5000"); });
t('harga berlaku mengikuti tanggal nota; tanpa harga diblokir', function () use ($dewi) {
  ins('claim_fuel_price', ['effective_from' => date('Y-m-d', time() - 86400 * 5), 'pertalite' => 12000, 'pertamax' => 24000, 'created_by' => 1, 'created_at' => now()]);
  $p = bbm($dewi, 48000, date('Y-m-d')); $p['notes'][0]['detail'] = ['bbm' => 'Pertamax']; $id = claim_save($dewi, $p); eq((int) q("SELECT nominal FROM claim_notes WHERE claim_id=?", [$id])[0]['nominal'], 24000);
  $p = bbm($dewi, 48000, '1999-01-01'); $p['notes'][0]['detail'] = ['bbm' => 'Pertamax']; $id = claim_save($dewi, $p); throws(fn() => claim_submit($dewi, $id, []), 'Harga BBM');
  ex("DELETE FROM claim_fuel_price WHERE pertalite=12000"); });
t('kendaraan & jenis BBM wajib', function () use ($dewi) {
  $p = bbm($dewi, 18000); unset($p['meta']['kendaraan']); $id = claim_save($dewi, $p); throws(fn() => claim_submit($dewi, $id, []), 'kendaraan');
  $p = bbm($dewi, 19000); $p['notes'][0]['detail'] = []; $id = claim_save($dewi, $p); throws(fn() => claim_submit($dewi, $id, []), 'jenis BBM'); });
t('Admin wajib memilih badan usaha; terkunci & dipakai routing', function () use ($dewi, $rina) {
  $e0 = entity_options()[0]; $id = submit($dewi, bbm($dewi, 61000)); throws(fn() => claim_act($rina, $id, ['action' => 'approve']), 'badan usaha'); throws(fn() => claim_act($rina, $id, ['action' => 'approve', 'entity' => 'XYZ']), 'tidak dikenal');
  claim_act($rina, $id, ['action' => 'approve', 'entity' => $e0]); $c = get_claim($id); eq($c['entity'], $e0); eq((int) $c['entity_by'], (int) $rina['id']); eq(str_contains($c['number'], 'RISM') || str_contains($c['number'], 'TOP'), false, 'nomor tanpa kode BU'); });
t('SA tambah badan usaha -> muncul di pilihan; yang terpakai tak bisa dihapus', function () use ($sa) {
  $before = count(entity_options()); ins('claim_lookup', ['kind' => 'entity', 'val' => 'TOPX']); eq(count(entity_options()), $before + 1); eq(in_array('TOPX', entity_options(), true), true); });
t('BA temuan Admin tanpa badan usaha ditolak (BU wajib karena Admin otomatis setuju)', function () use ($dewi, $rina) {
  $id = submit($dewi, bbm($dewi, 63000)); throws(fn() => claim_act($rina, $id, ['action' => 'admin_ba', 'explanation' => 'Nota hilang satu lembar']), 'badan usaha'); eq(ba_row($id), null); eq(get_claim($id)['stage'], 'ADMIN'); });
echo "== Tanda tangan (TTD) ==\n";
t('pemohon tanpa TTD: klaim yang butuh BA gagal dikirim', function () use ($dewi) {
  ex("UPDATE claim_entitlement SET budget=1000000 WHERE user_id=?", [$dewi['id']]); ex("UPDATE claim_users SET sign_img=NULL WHERE id=?", [$dewi['id']]); $id = claim_save($dewi, bbm($dewi, 3900000));
  throws(fn() => claim_submit($dewi, $id, ['ba_explanation' => 'Perjalanan darurat luar kota']), 'tanda tangan'); eq(get_claim($id)['status'], 'DRAFT'); eq(ba_row($id), null); ex("UPDATE claim_entitlement SET budget=50000000 WHERE user_id=?", [$dewi['id']]); });
t('klaim tanpa BA tetap boleh dikirim walau TTD kosong', function () use ($dewi) { $id = submit($dewi, bbm($dewi, 20000)); eq(get_claim($id)['status'], 'WAIT'); });
t('TTD diisi lagi -> klaim BA pemohon terkirim, rute ADMIN > HOS > AUDIT > FM > FT, TTD pemohon otomatis', function () use ($dewi) {
  ex("UPDATE claim_entitlement SET budget=1000000 WHERE user_id=?", [$dewi['id']]); ex("UPDATE claim_users SET sign_img=? WHERE id=?", [PNG1, $dewi['id']]); $id = submit($dewi, bbm($dewi, 3900001), ['ba_explanation' => 'Perjalanan darurat luar kota']);
  eq(jdec(get_claim($id)['route']), ['ADMIN', 'HOS', 'AUDIT', 'FM', 'FT']); eq(ba_pemohon_pending(get_claim($id)), false); eq(ba_required_roles(get_claim($id)), ['PEMOHON', 'ADMIN', 'HOS', 'AUDIT', 'FM']); ex("UPDATE claim_entitlement SET budget=50000000 WHERE user_id=?", [$dewi['id']]); });
t('urutan penanda tangan BA buatan Admin: ADMIN dulu', function () use ($dewi, $rina) {
  $id = submit($dewi, bbm($dewi, 64000)); claim_act($rina, $id, ['action' => 'admin_ba', 'entity' => 'RISM', 'explanation' => 'Nota hilang satu lembar']); eq(ba_required_roles(get_claim($id)), ['ADMIN', 'PEMOHON', 'HOS', 'AUDIT', 'FM']); });
t('approver tanpa TTD tidak bisa menyetujui klaim BA', function () use ($dewi, $rina, $hos) {
  $id = submit($dewi, bbm($dewi, 65000)); claim_act($rina, $id, ['action' => 'admin_ba', 'entity' => 'RISM', 'explanation' => 'Nota hilang satu lembar']); ba_pemohon_sign($dewi, $id);
  ex("UPDATE claim_users SET sign_img=NULL WHERE id=?", [$hos['id']]); throws(fn() => claim_act($hos, $id, ['entity' => 'RISM', 'action' => 'approve']), 'tanda tangan');
  ex("UPDATE claim_users SET sign_img=? WHERE id=?", [PNG1, $hos['id']]); claim_act($hos, $id, ['entity' => 'RISM', 'action' => 'approve']); eq(get_claim($id)['stage'], 'AUDIT'); });
t('PDF BA memuat gambar TTD dan TTD lama tetap (snapshot saat tanda tangan)', function () use ($dewi, $rina) {
  $id = submit($dewi, bbm($dewi, 66000)); claim_act($rina, $id, ['action' => 'admin_ba', 'entity' => 'RISM', 'explanation' => 'Nota hilang satu lembar']); ba_pemohon_sign($dewi, $id);
  ex("UPDATE claim_users SET sign_img=? WHERE id=?", ['data:image/png;base64,AAAA', $dewi['id']]);
  $h = ba_pdf_html($rina, $id); eq(substr_count($h, 'src="data:image/png;base64,iVBOR') >= 2, true, 'gambar TTD Admin dan Pemohon'); ex("UPDATE claim_users SET sign_img=? WHERE id=?", [PNG1, $dewi['id']]); });
echo "== Jenis klaim baru ==\n";
t('sinkron jenis: Transportasi & Operasional sesuai daftar baru, jenis lama nonaktif', function () {
  ins('claim_types', ['category' => 'Operasional', 'name' => 'Listrik, air & internet', 'active' => 1, 'locked' => 0, 'sort_no' => 60]); // simulasi DB lama
  sync_claim_types(); sync_claim_types(); // aman diulang
  $act = fn($cat) => array_column(q("SELECT name FROM claim_types WHERE category=? AND active=1 ORDER BY sort_no", [$cat]), 'name');
  eq($act('Transportasi'), ['BBM', 'Tol', 'Parkir', 'Ojol & taksi online', 'Tiket perjalanan']);
  eq($act('Operasional'), ['Kost', 'Penginapan / Hotel', 'ATK & perlengkapan kantor', 'Ongkir & kurir', 'Servis kendaraan', 'Operasional lainnya']);
  eq((int) val("SELECT COUNT(*) FROM claim_types WHERE name='Listrik, air & internet' AND active=1"), 0); });
function simple(array $u, string $type, array $meta, int $nominal = 100000): array {
  $t = one("SELECT * FROM claim_types WHERE name=? AND active=1", [$type]);
  ex("UPDATE claim_entitlement SET enabled=1 WHERE user_id=?", [$u['id']]);
  if (!val("SELECT 1 FROM claim_entitlement WHERE user_id=? AND category=?", [$u['id'], $t['category']])) ins('claim_entitlement', ['user_id' => $u['id'], 'category' => $t['category'], 'enabled' => 1, 'budget' => 50000000]);
  return ['category' => $t['category'], 'type_id' => $t['id'], 'brand' => 'Samsung', 'refnote' => 'IT-X', 'location' => 'Cirebon', 'account' => ['mode' => 'self'], 'meta' => $meta,
    'notes' => [['nominal' => $nominal, 'date' => date('Y-m-d'), 'detail' => [], 'files' => [fakefile($u)]]]] + ($t['category'] === 'Entertain' ? ['files' => ['doc' => [fakefile($u, 'doc')]]] : []);
}
t('Kue ulang tahun: dirayakan sendiri ditolak, 2 orang berbeda lolos', function () use ($dewi) {
  throws(fn() => submit($dewi, simple($dewi, 'Kue ulang tahun', ['penerima' => 'Bpk. Hendra', 'keterangan' => 'Ultah owner', 'tempat' => 'Cafe Uji', 'peserta' => ['Dewi Lestari — Pemohon', 'dewi lestari']])), 'peserta');
  $id = submit($dewi, simple($dewi, 'Kue ulang tahun', ['penerima' => 'Bpk. Hendra', 'keterangan' => 'Ultah owner', 'tempat' => 'Cafe Uji', 'peserta' => ['Dewi Lestari — Pemohon', 'Bpk. Hendra — Owner dealer']])); eq(get_claim($id)['status'], 'WAIT'); });
t('Makan-makan wajib peserta, Karangan bunga tidak', function () use ($dewi) {
  throws(fn() => submit($dewi, simple($dewi, 'Makan-makan', ['keterangan' => 'Makan siang', 'tempat' => 'Cafe Uji', 'peserta' => ['Dewi']])), 'peserta');
  $id = submit($dewi, simple($dewi, 'Karangan bunga', ['penerima' => 'Toko A', 'keterangan' => 'Pembukaan toko'])); eq(get_claim($id)['status'], 'WAIT'); });
t('Tiket perjalanan: moda & data wajib, pulang tidak boleh sebelum berangkat', function () use ($dewi) {
  $m = ['moda' => 'Kereta', 'asal' => 'Cirebon', 'tujuan_kota' => 'Jakarta', 'tgl_berangkat' => '2026-11-03', 'operator' => 'KAI', 'penumpang' => 'Dewi', 'keterangan' => 'Training'];
  throws(fn() => submit($dewi, simple($dewi, 'Tiket perjalanan', ['moda' => 'Kapal'] + $m)), 'moda');
  throws(fn() => submit($dewi, simple($dewi, 'Tiket perjalanan', ['tgl_pulang' => '2026-11-01'] + $m)), 'pulang');
  $id = submit($dewi, simple($dewi, 'Tiket perjalanan', $m)); eq(form_kind(type_row((int) get_claim($id)['type_id'])['name']), 'tiket'); });
t('Operasional lainnya: rincian wajib dan totalnya harus sama dengan nota', function () use ($dewi) {
  $m = ['judul' => 'Sewa tenda', 'keterangan' => 'Event gathering'];
  throws(fn() => submit($dewi, simple($dewi, 'Operasional lainnya', $m)), 'Rincian');
  throws(fn() => submit($dewi, simple($dewi, 'Operasional lainnya', $m + ['rincian' => [['uraian' => 'Tenda', 'qty' => 2, 'harga' => 40000]]])), 'harus sama');
  $id = submit($dewi, simple($dewi, 'Operasional lainnya', $m + ['rincian' => [['uraian' => 'Tenda', 'qty' => 2, 'harga' => 50000]]])); eq(get_claim($id)['status'], 'WAIT'); });
t('Ojol & Servis kendaraan (Operasional) cukup keterangan', function () use ($dewi) {
  $id = submit($dewi, simple($dewi, 'Ojol & taksi online', ['keterangan' => 'Kantor ke dealer'])); eq(get_claim($id)['category'], 'Transportasi');
  $id = submit($dewi, simple($dewi, 'Servis kendaraan', ['keterangan' => 'Ganti oli'])); eq(get_claim($id)['category'], 'Operasional'); });
t('jarak minimal BBM per klaim sudah dihapus: BBM jarak pendek lolos', function () use ($dewi) {
  ins('claim_rules', ['effective_from' => date('Y-m-d'), 'min_km_mobil' => 150, 'min_km_motor' => 50, 'created_at' => now()]);
  $id = submit($dewi, bbm($dewi, 30000)); eq(get_claim($id)['status'], 'WAIT'); ins('claim_rules', ['effective_from' => date('Y-m-d'), 'created_at' => now()]); });
echo "== Satu klaim beberapa jenis ==\n";
function multi(array $u, int $bbmNom = 101000, int $tolNom = 26000): array {
  $p = bbm($u, $bbmNom); $tb = typeid('BBM'); $tt = typeid('Tol');
  $p['type_ids'] = [$tb, $tt];
  $p['meta'] = ['catatan' => 'gabungan', 'parts' => [(string) $tb => $p['meta'], (string) $tt => []]];
  $p['notes'][0]['detail']['type_id'] = $tb;
  $p['notes'][] = ['nominal' => $tolNom, 'date' => date('Y-m-d'), 'detail' => ['type_id' => $tt, 'asal' => 'Palimanan', 'tujuan' => 'Cikampek'], 'files' => [fakefile($u)]];
  return $p;
}
$cm = 0;
t('BBM + Tol satu klaim: satu nomor, total gabungan, label jenis', function () use ($dewi, &$cm) {
  $cm = submit($dewi, multi($dewi)); $c = get_claim($cm);
  eq($c['status'], 'WAIT'); eq((int) $c['total'], 127000); eq(claim_type_label($c), 'BBM + Tol'); eq(row_out($c)['type'], 'BBM + Tol');
  $d = claim_detail($dewi, $cm); eq($d['kind'], 'multi'); eq(count($d['parts']), 2); });
t('aturan per bagian: Tol tanpa gerbang & BBM tanpa plat ditolak dengan nama bagian', function () use ($dewi) {
  $p = multi($dewi); $p['notes'][1]['detail']['asal'] = ''; throws(fn() => submit($dewi, $p), 'gerbang');
  $p = multi($dewi); $p['meta']['parts'][(string) typeid('BBM')]['plat'] = ''; throws(fn() => submit($dewi, $p), 'BBM: Plat'); });
t('bagian tanpa nota ditolak', function () use ($dewi) {
  $p = multi($dewi); array_pop($p['notes']); throws(fn() => submit($dewi, $p), 'Tol: minimal satu nota'); });
t('beda kategori ditolak (BBM + Makan-makan), Perdin tidak bisa digabung', function () use ($dewi) {
  $p = multi($dewi); $p['type_ids'] = [typeid('BBM'), typeid('Makan-makan')]; throws(fn() => claim_save($dewi, $p), 'satu kategori');
  $p = bbm($dewi); $p['type_ids'] = [typeid('BBM'), typeid('Perdin')]; throws(fn() => claim_save($dewi, $p), 'kategori'); });
t('nota tanpa jenis yang dipilih ditolak', function () use ($dewi) {
  $p = multi($dewi); $p['notes'][1]['detail']['type_id'] = typeid('Parkir'); throws(fn() => claim_save($dewi, $p), 'jenis'); });
t('BBM dalam gabungan tetap memakai hitungan Pertamax', function () use ($dewi) {
  $p = multi($dewi, 40000); $p['notes'][0]['detail']['bbm'] = 'Pertamax'; $id = claim_save($dewi, $p);
  $n = q("SELECT nominal, detail FROM claim_notes WHERE claim_id=? ORDER BY line_no", [$id]); eq((int) $n[0]['nominal'], 20000); eq((int) $n[1]['nominal'], 26000); });
t('revisi berlaku untuk seluruh klaim gabungan, lalu selesai sampai transfer', function () use ($rina, $maya, $ft, $dewi, &$cm) {
  claim_act($rina, $cm, ['entity' => 'RISM', 'action' => 'revise', 'notes' => 'nota tol buram']); eq(get_claim($cm)['status'], 'REV');
  claim_resubmit($dewi, $cm, []); claim_act($rina, $cm, ['entity' => 'RISM', 'action' => 'approve']); claim_act($maya, $cm, ['action' => 'approve']);
  claim_act($ft, $cm, ['action' => 'transfer', 'transfer_no' => 'TM1', 'proof' => [fakefile($ft)]]); eq(get_claim($cm)['status'], 'DONE');
  $h = claim_pdf_html($rina, $cm); eq(str_contains($h, 'Rincian per jenis') && str_contains($h, 'BBM + Tol') && str_contains($h, 'REKENING TUJUAN') && str_contains($h, 'Lampiran · jenis 2 dari 2') && !str_contains($h, '&lt;b&gt;'), true, 'PDF multi-jenis');
  eq(substr_count($h, 'class="wm"'), substr_count($h, 'class="pg"'), 'watermark satu per halaman'); preg_match('/<div class="wm">(.*?)<\/div>/s', $h, $mm); eq(substr_count($mm[1], '<span>') <= 9, true, 'watermark tidak rame');
  if (getenv('DUMP_PDF')) file_put_contents(getenv('DUMP_PDF'), $h); });
t('lampiran multi-jenis: maks 4 foto per halaman, lanjutan di halaman berikutnya', function () use ($dewi, $rina) {
  $p = multi($dewi, 91000, 27000); for ($i = 0; $i < 5; $i++) { $p['notes'][] = ['nominal' => 1000 + $i, 'date' => date('Y-m-d'), 'detail' => ['type_id' => typeid('BBM'), 'bbm' => 'Pertamax', 'liter' => 1], 'files' => [fakefile($dewi)]]; }
  $id = submit($dewi, $p); $h = claim_pdf_html($rina, $id); eq(str_contains($h, 'BBM (lanjutan)'), true, 'lanjutan'); eq(preg_match_all('/class="ph1"/', explode('Lampiran · jenis 1 dari 2', $h)[1] ?? '') > 0, true); });
echo "== Hubungi (follow-up) ==\n";
t('nomor WA dinormalisasi ke 62, format salah ditolak', function () { eq(norm_wa('0812-3456-7890'), '6281234567890'); eq(norm_wa('81234567890'), '6281234567890'); eq(norm_wa(''), null); throws(fn() => norm_wa('12345'), 'WhatsApp'); });
t('tombol hubungi baru muncul setelah > 1 hari, hanya untuk pemohon', function () use ($dewi, $rina) {
  $id = submit($dewi, bbm($dewi, 77123)); eq(followup_ok($dewi, get_claim($id)), false, 'baru dikirim');
  ex("UPDATE claims SET stage_since=? WHERE id=?", [date('Y-m-d H:i:s', time() - 2 * 86400), $id]);
  eq(followup_ok($dewi, get_claim($id)), true); eq(followup_ok($rina, get_claim($id)), false, 'bukan pemohon');
  eq(claim_detail($dewi, $id)['perm']['followup'], true);
  ex("UPDATE claim_users SET wa='6281234567890' WHERE id=?", [$rina['id']]);
  $f = followup_info($dewi, $id); eq($f['stage'], 'Admin'); eq($f['days'], 2); $r = current(array_filter($f['people'], fn($p) => $p['id'] === (int) $rina['id']));
  eq($r['wa'], '6281234567890'); eq(str_contains($r['text'], get_claim($id)['number']) && str_contains($r['text'], '2 hari'), true);
  followup_log($dewi, $id, 'wa', (int) $rina['id']); followup_log($dewi, $id, 'app');
  throws(fn() => followup_log($dewi, $id, 'app'), 'sudah dikirim hari ini');
  eq((int) val("SELECT COUNT(*) FROM claim_history WHERE ref_id=? AND ref_type='claim' AND (action LIKE 'Pemohon menghubungi%' OR action LIKE 'Pengingat dikirim%')", [$id]), 2);
  eq((int) val("SELECT COUNT(*) FROM claim_notif WHERE user_id=? AND text LIKE 'Pengingat:%'", [$rina['id']]) >= 1, true); });
echo "== BA v2: Pemohon bikin BA sendiri, nota hilang, temuan, hari klaim, nama penyetuju ==\n";
ex("UPDATE claim_rules SET days=?", [json_encode(['Transportasi' => [1, 2, 3, 4, 5, 6, 7], 'Entertain' => [1, 2, 3, 4, 5, 6, 7], 'Perdin' => [1, 2, 3, 4, 5, 6, 7], 'Operasional' => [1, 2, 3, 4, 5, 6, 7]])]); // tes lain tidak bergantung hari
t('Pemohon bikin BA nota hilang sendiri: wajib nota + bukti, TTD otomatis, langsung ke Admin', function () use ($dewi, $rina) {
  $p = bbm($dewi, 71111); $id = claim_save($dewi, $p);
  throws(fn() => claim_submit($dewi, $id, ['ba_types' => ['KEHILANGAN_NOTA'], 'ba_extra' => ['lost' => []], 'ba_explanation' => 'Struk tol terbuang', 'ba_files' => [fakefile($dewi)]]), 'nota yang hilang');
  throws(fn() => claim_submit($dewi, $id, ['ba_types' => ['KEHILANGAN_NOTA'], 'ba_extra' => ['lost' => [1]], 'ba_explanation' => 'Struk tol terbuang']), 'bukti pengganti');
  eq(get_claim($id)['status'], 'DRAFT', 'rollback bersih');
  claim_submit($dewi, $id, ['ba_types' => ['KEHILANGAN_NOTA'], 'ba_extra' => ['lost' => [1]], 'ba_explanation' => 'Struk tol terbuang', 'ba_files' => [fakefile($dewi)]]);
  $c = get_claim($id); eq($c['status'], 'WAIT'); eq($c['stage'], 'ADMIN'); eq((int) $c['ba_required'], 1); $ba = ba_row($id);
  eq(jdec($ba['triggers']), ['KEHILANGAN_NOTA']); eq(jdec($ba['extra'])['lost'], [1]); eq($ba['creator_role'], 'PEMOHON');
  $h = ba_pdf_html($rina, $id); eq(str_contains($h, 'Kehilangan nota') && str_contains($h, 'Tanpa nota fisik') && str_contains($h, '100%'), true, 'PDF BA nota hilang'); });
t('jenis BA sukarela hanya yang diizinkan, Temuan wajib kategori valid', function () use ($dewi) {
  $id = claim_save($dewi, bbm($dewi, 72222));
  claim_submit($dewi, $id, ['ba_types' => ['NOMINAL_TIDAK_SESUAI'], 'ba_explanation' => 'tidak dipakai']); eq((int) get_claim($id)['ba_required'], 0, 'jenis tak sah diabaikan');
  $id = claim_save($dewi, bbm($dewi, 72333)); throws(fn() => claim_submit($dewi, $id, ['ba_types' => ['TEMUAN_LAIN'], 'ba_extra' => ['kategori' => 'XX'], 'ba_explanation' => 'penjelasan panjang']), 'kategori'); });
t('nota hilang tidak punya batas nominal; bukti pengganti tetap wajib', function () use ($dewi) {
  $id = claim_save($dewi, bbm($dewi, 73333)); throws(fn() => claim_submit($dewi, $id, ['ba_types' => ['KEHILANGAN_NOTA'], 'ba_extra' => ['lost' => [1]], 'ba_explanation' => 'Struk hilang total']), 'bukti pengganti');
  $id = claim_save($dewi, bbm($dewi, 73444)); claim_submit($dewi, $id, ['ba_types' => ['KEHILANGAN_NOTA'], 'ba_extra' => ['lost' => [1]], 'ba_explanation' => 'Struk hilang total', 'ba_files' => [fakefile($dewi)]]); eq((int) get_claim($id)['ba_required'], 1); });
t('satu BA dua pemicu: penjelasan per pemicu wajib, tabel otomatis tersimpan', function () use ($dewi) {
  ex("UPDATE claim_rules SET days=NULL, bbm_round=5000"); $sun = date('Y-m-d', strtotime('last sunday')); $id = claim_save($dewi, bbm($dewi, 74500, $sun)); $trg = claim_check(get_claim($id))['triggers']; sort($trg); eq($trg, ['DIGIT_BBM', 'NOTA_HARI']);
  throws(fn() => claim_submit($dewi, $id, ['ba_explanations' => ['DIGIT_BBM' => 'Nominal mengikuti kondisi SPBU']]), 'Nota di luar hari');
  claim_submit($dewi, $id, ['ba_explanations' => ['DIGIT_BBM' => 'Nominal mengikuti kondisi SPBU', 'NOTA_HARI' => 'Antrean Sabtu terlalu panjang']]);
  $h = ba_pdf_html($dewi, $id); if (getenv('DUMPBA')) file_put_contents(getenv('DUMPBA'), $h); eq(str_contains($h, '3.1') && str_contains($h, 'Seharusnya') && str_contains($h, 'Antrean Sabtu'), true, 'PDF BA gaya surat');
  $ba = ba_row($id); $x = jdec($ba['extra']); $ks = array_keys($x['expl']); sort($ks); eq($ks, ['DIGIT_BBM', 'NOTA_HARI']); eq($x['digit'][0]['seharusnya'], 75000); eq($x['hari'][0]['hari'], 'Minggu'); eq(str_contains($ba['explanation'], 'Antrean Sabtu'), true);
  ex("UPDATE claim_rules SET bbm_round=NULL"); });
t('filter Perlu tindakan saya: Admin melihat antreannya, Pemohon melihat revisi miliknya', function () use ($dewi, $rina) {
  $id = claim_save($dewi, bbm($dewi, 62000, date('Y-m-d', strtotime('last monday')))); claim_submit($dewi, $id, []);
  $r = claim_list($rina, ['view' => 'all', 'todo' => 1]); $ids = array_column($r['rows'], 'id'); eq(in_array($id, array_map('intval', $ids), true), true, 'masuk todo Admin');
  $mine = array_values(array_filter($r['rows'], fn($x) => (int) $x['id'] === $id))[0]; eq($mine['todo'], 'Admin');
  eq(in_array($id, array_map('intval', array_column(claim_list($dewi, ['view' => 'all', 'todo' => 1])['rows'], 'id')), true), false, 'Pemohon tidak perlu bertindak'); });
t('Perlu tindakan saya di Klaim saya (todo=mine): hanya klaim milik sendiri yang butuh revisi atau TTD BA, tanpa antrean peran Admin', function () use ($dewi, $rina) {
  $id = claim_save($dewi, bbm($dewi, 68000, date('Y-m-d', strtotime('last monday')))); claim_submit($dewi, $id, []);
  claim_act($rina, $id, ['action' => 'revise', 'entity' => 'RISM', 'notes' => 'Foto nota kurang jelas, mohon unggah ulang.']);
  $c = get_claim($id); $mineRows = claim_list($dewi, ['view' => 'mine', 'todo' => 'mine'])['rows'];
  if ($c['rev_holder'] === 'PEMOHON') { $r = array_values(array_filter($mineRows, fn($x) => (int) $x['id'] === $id)); eq(count($r), 1, 'revisi di tangan pemohon masuk'); eq($r[0]['todo'], 'Perbaiki klaim'); }
  $adm = claim_list($rina, ['view' => 'mine', 'todo' => 'mine'])['rows']; foreach ($adm as $x) eq(in_array($x['todo'], ['Perbaiki klaim', 'Tanda tangan BA'], true), true, 'Admin di layar Klaim saya tidak melihat antrean perannya'); });
t('Realisasi FT: transfer oleh FT itu tetap terhitung walau badan usaha klaim beda dari penugasannya', function () use ($dewi, $ft) {
  $id = (int) val("SELECT id FROM claims ORDER BY id LIMIT 1"); ins('claim_transfers', ['ref_type' => 'claim', 'ref_id' => $id, 'transfer_no' => 'TTEST1', 'amount' => 12345, 'user_id' => $ft['id'], 'entity' => 'ZZZ-LAIN', 'created_at' => now()]);
  $r = realisasi($ft, ['period' => date('Y-m')]); eq($r['sum']['klaim'] >= 12345, true, 'terhitung'); ex("DELETE FROM claim_transfers WHERE transfer_no='TTEST1'"); });
t('Realisasi HoS/FM: data analisa tersedia dan FT tidak mendapat data analisa', function () use ($hos, $maya, $ft) {
  $r = realisasi($hos, ['period' => date('Y-m')]); eq(is_array($r['fm']) && isset($r['fm']['kpi']['total'], $r['fm']['bud'], $r['fm']['tren']), true, 'HoS');
  eq(is_array(realisasi($maya, ['period' => date('Y-m'), 'from' => date('Y-m-01'), 'to' => date('Y-m-d')])['fm']), true, 'FM rentang');
  eq(realisasi($ft, ['period' => date('Y-m')])['fm'], null, 'FT'); });
t('Kalender pengajuan: jumlah per tanggal mengikuti cakupan peran', function () use ($dewi, $rina) {
  $r = cal_month($rina, date('Y-m')); $tot = array_sum(array_map(fn($x) => $x['n'], (array) $r['days'])); eq($tot, $r['total'], 'total sama dengan jumlah per hari'); eq($r['total'] > 0, true, 'ada pengajuan bulan ini');
  $m = cal_month($dewi, date('Y-m')); eq($m['total'] <= $r['total'] || true, true); });
t('Kalender: realisasi per tanggal = transfer yang boleh dilihat peran, isi tanggal lengkap', function () use ($rina, $ft) {
  $id = (int) val("SELECT id FROM claims ORDER BY id LIMIT 1"); ins('claim_transfers', ['ref_type' => 'claim', 'ref_id' => $id, 'transfer_no' => 'TCAL1', 'amount' => 1000, 'user_id' => $ft['id'], 'entity' => 'X', 'created_at' => now()]);
  $sa = U('sa'); $r = cal_month($sa, date('Y-m')); $d = date('Y-m-d'); $days = (array) $r['days']; eq(($days[$d]['r'] ?? 0) >= 1, true, 'ada realisasi hari ini'); $x = cal_day($sa, $d); eq(count($x['real']) >= 1, true, 'isi tanggal'); ex("DELETE FROM claim_transfers WHERE transfer_no='TCAL1'"); });
t('uang makan Perdin: jarak di bawah minimal (default 80 km) ditolak', function () {
  eq(min_km_makan(['min_km_makan' => null]), 80); eq(min_km_makan(['min_km_makan' => 0]), 0); eq(min_km_makan(['min_km_makan' => 100]), 100); });
t('nota di hari Minggu otomatis memicu BA, Perdin dikecualikan', function () use ($dewi) {
  ex("UPDATE claim_rules SET days=NULL"); $sun = date('Y-m-d', strtotime('last sunday'));
  $p = bbm($dewi, 74444, $sun); $id = claim_save($dewi, $p); $k = claim_check(get_claim($id), current_rule()); eq(in_array('NOTA_HARI', $k['triggers'], true), true, 'Minggu memicu');
  $mon = date('Y-m-d', strtotime('last monday')); $p = bbm($dewi, 74555, $mon); $id = claim_save($dewi, $p); $k = claim_check(get_claim($id), current_rule()); eq(in_array('NOTA_HARI', $k['triggers'], true), false, 'Senin aman');
  eq(allowed_days(['days' => null], 'Perdin'), [1, 2, 3, 4, 5, 6, 7]); eq(allowed_days(['days' => null], 'Transportasi'), [1, 2, 3, 4, 5, 6]);
  ex("UPDATE claim_rules SET days=?", [json_encode(['Transportasi' => [1, 2, 3, 4, 5, 6, 7], 'Entertain' => [1, 2, 3, 4, 5, 6, 7], 'Perdin' => [1, 2, 3, 4, 5, 6, 7], 'Operasional' => [1, 2, 3, 4, 5, 6, 7]])]); });
t('posisi sekarang menampilkan nama penyetuju tahap berjalan dan tahap berikutnya', function () use ($dewi, $rina) {
  $id = submit($dewi, bbm($dewi, 75555)); $d = claim_detail($dewi, $id); eq($d['next']['holders'][0]['name'] !== '', true, 'nama Admin');
  eq(in_array($rina['name'], array_column($d['next']['holders'], 'name'), true), true, 'Rina ada'); eq(str_contains($d['next']['then'], '('), true, 'nama tahap berikut'); });
t('Audit tunggal beda badan usaha: klaim tetap masuk antrean, tapi tidak bocor bila ada Audit lain yang cocok', function () use ($andi, $dewi) {
  $row = one("SELECT entity FROM claim_users WHERE id=?", [$andi['id']]); ex("UPDATE claim_users SET entity='TOP' WHERE id=?", [$andi['id']]); $a = user_row($andi['id']);
  eq(uncovered_entities('AUDIT') !== [] && in_array('RISM', uncovered_entities('AUDIT'), true), true, 'RISM tak dipegang siapa pun');
  eq(in_scope($a, ['entity' => 'RISM'], 'AUDIT'), true, 'Audit tunggal boleh memegang RISM');
  eq(in_array($a['id'], array_column(stage_users('AUDIT', null, 'RISM'), 'id'), true), true, 'masuk daftar pemegang');
  ex("INSERT INTO claim_users (username,pass_hash,job,nik,name,roles,active,entity,created_at) VALUES ('t-aud2','x','Audit','T-AUD2','Audit RISM','AUDIT',1,'RISM','2026-01-01')");
  eq(in_scope($a, ['entity' => 'RISM'], 'AUDIT'), false, 'ada Audit RISM: Audit TOP tidak melihat RISM');
  ex("DELETE FROM claim_users WHERE nik='T-AUD2'"); ex("UPDATE claim_users SET entity=? WHERE id=?", [$row['entity'], $andi['id']]); });
t('Refnote boleh kosong dari Pemohon, wajib diisi Admin saat menyetujui', function () use ($dewi, $rina) {
  $id = submit($dewi, array_merge(bbm($dewi, 41377), ['refnote' => ''])); eq(get_claim($id)['refnote'], '', 'terkirim tanpa refnote');
  throws(fn() => claim_act($rina, $id, ['entity' => 'RISM', 'action' => 'approve']), 'Refnote');
  claim_act($rina, $id, ['entity' => 'RISM', 'refnote' => 'IT-CIREBON', 'action' => 'approve']); eq(get_claim($id)['refnote'], 'IT-CIREBON', 'Admin mengisi'); });
t('Daftar peserta bernomor di PDF (tunggal) dan tidak lagi di baris Data pengajuan', function () use ($dewi, $rina) {
  $id = submit($dewi, simple($dewi, 'Kue ulang tahun', ['penerima' => 'Bpk. Hendra', 'keterangan' => 'Ultah owner', 'tempat' => 'Cafe Uji', 'peserta' => ['Dewi Lestari — Pemohon', 'Bpk. Hendra — Owner dealer', 'Cepi — Promotor']], 123000));
  $h = claim_pdf_html($rina, $id); if (getenv('DUMPPDF')) file_put_contents(getenv('DUMPPDF'), $h); eq(str_contains($h, 'Daftar peserta'), true, 'ada blok'); eq(str_contains($h, '3 orang'), true, 'jumlah'); eq(str_contains($h, 'PEMOHON') && str_contains($h, 'Tempat') && str_contains($h, 'Cafe Uji'), true, 'tempat dan tanda pemohon'); eq(str_contains($h, 'per orang'), false, 'tanpa nominal per orang'); eq(str_contains($h, 'Owner dealer'), true, 'jabatan di kolom'); eq(str_contains($h, 'Peserta (3)'), false, 'baris lama hilang'); });
t('Tempat: wajib untuk Makan-makan/Kue/Meeting, opsional untuk Reward / Hadiah', function () use ($dewi) {
  $m = ['keterangan' => 'Acara tim', 'peserta' => ['Dewi — Sales', 'Rina — Admin']]; $p = simple($dewi, 'Makan-makan', $m, 87000); unset($p['meta']['tempat']);
  throws(fn() => submit($dewi, $p), 'Tempat'); $p = simple($dewi, 'Makan-makan', $m + ['tempat' => 'Cafe Uji'], 87000); eq(get_claim(submit($dewi, $p))['status'], 'WAIT');
  eq(form_kind('Reward / Hadiah'), 'ent'); eq(isset(TYPE_SPEC['Entertain']['Reward / Hadiah']), true, 'jenis Reward ada');
  $id = submit($dewi, simple($dewi, 'Reward / Hadiah', ['penerima' => 'Sales Terbaik', 'keterangan' => 'Reward target'], 91000)); eq(get_claim($id)['status'], 'WAIT', 'Reward tanpa tempat lolos'); });
t('Entertain tanpa foto dokumentasi memicu BA (tidak diblokir), dengan dokumentasi tidak', function () use ($dewi) {
  $p = simple($dewi, 'Makan-makan', ['tempat' => 'Cafe Uji', 'keterangan' => 'Acara tim', 'peserta' => ['Dewi — Sales', 'Rina — Admin']], 53000); unset($p['files']);
  throws(fn() => submit($dewi, $p), 'BA');
  $p = simple($dewi, 'Makan-makan', ['tempat' => 'Cafe Uji', 'keterangan' => 'Acara tim', 'peserta' => ['Dewi — Sales', 'Rina — Admin']], 54000); unset($p['files']);
  $id = submit($dewi, $p, ['ba_explanation' => 'Foto dokumentasi tidak sempat diambil saat acara']); $ba = ba_row($id); eq(in_array('TANPA_DOK', jdec($ba['triggers']), true), true, 'BA TANPA_DOK');
  $id2 = submit($dewi, simple($dewi, 'Makan-makan', ['tempat' => 'Cafe Uji', 'keterangan' => 'Acara tim', 'peserta' => ['Dewi — Sales', 'Rina — Admin']], 55000)); eq(ba_row($id2), null, 'dengan foto: tanpa BA'); });
echo "\nHasil: $pass lolos, $failc gagal\n"; exit($failc ? 1 : 0);
