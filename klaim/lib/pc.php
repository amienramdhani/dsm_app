<?php
require_once __DIR__ . '/claims.php';

function get_pc(int $id): array { $p = one("SELECT * FROM claim_pc WHERE id=?", [$id]); if (!$p) fail('Petty cash tidak ditemukan.', 404); return $p; }
function pc_route(array $claimant): array { return in_array('RGM', $claimant['roles'], true) ? ['ADMIN', 'HOS', 'FM', 'FT'] : ['RGM', 'ADMIN', 'HOS', 'FM', 'FT']; }
function pc_view(array $u, array $p): bool {
  if ((int) $p['claimant_id'] === (int) $u['id']) return true;
  foreach (['SA', 'ADMIN', 'HOS', 'FM', 'FT', 'RGM'] as $r) if (has_role($u, $r) && in_scope($u, $p, $r)) return true;
  return false;
}
function pc_stage_ok(array $u, array $p, string $stage): bool {
  if (sa_free($u)) return true;
  return has_role($u, $stage) && in_scope($u, $p, $stage) && (int) $p['claimant_id'] !== (int) $u['id'];
}
function pc_submit(array $u, array $in): int {
  if (!$u['can_petty']) fail('Anda belum diberi hak petty cash.', 403);
  if (one("SELECT 1 FROM claim_pc WHERE claimant_id=? AND status NOT IN ('SELESAI','DITOLAK')", [$u['id']])) fail('Anda masih mempunyai petty cash aktif (satu permintaan aktif).');
  if (pc_blocked((int) $u['id'])) fail('Pengajuan diblokir sampai petty cash dipertanggungjawabkan.', 403);
  $plan = array_values($in['plan'] ?? []); $sum = 0;
  if (!count($plan)) fail('Rencana pemakaian minimal satu baris.');
  foreach ($plan as $i => $r) {
    if (trim($r['uraian'] ?? '') === '' || !in_array($r['kategori'] ?? '', CATS, true) || (int) ($r['nominal'] ?? 0) <= 0) fail('Rencana baris ' . ($i + 1) . ' belum lengkap.');
    $sum += (int) $r['nominal'];
    if ($r['kategori'] === 'Perdin') {
      $d = $r['perdin'] ?? []; $dur = duration_days($d['tgl_berangkat'] ?? null, $d['tgl_pulang'] ?? null);
      if ($dur <= 0 || empty($d['rute']) || (float) ($d['km_total'] ?? 0) <= 0) fail("Rencana baris " . ($i + 1) . ": rute Perdin, tanggal, dan total km wajib.");
      if (empty($d['maps'])) fail("Rencana baris " . ($i + 1) . ": screenshot Google Maps wajib.");
    }
  }
  $amount = (int) ($in['amount'] ?? 0);
  if ($amount !== $sum) fail('Total rencana (' . rp($sum) . ') harus sama dengan jumlah diminta (' . rp($amount) . ').');
  if ($amount > (int) $u['petty_ceiling']) fail('Jumlah melebihi plafon petty cash Anda (' . rp((int) $u['petty_ceiling']) . ').');
  if (empty($in['p_start']) || empty($in['p_end']) || $in['p_end'] < $in['p_start']) fail('Periode tidak valid.');
  if (trim($in['purpose'] ?? '') === '') fail('Tujuan wajib diisi.');
  $route = pc_route($u);
  $id = ins('claim_pc', ['number' => next_number('PC', $u['entities'][0] ?? null), 'claimant_id' => $u['id'], 'region' => $u['region'], 'entity' => $u['entities'][0] ?? null,
    'purpose' => trim($in['purpose']), 'remark' => trim($in['remark'] ?? '') ?: null, 'location' => $in['location'] ?? null, 'p_start' => $in['p_start'], 'p_end' => $in['p_end'], 'amount' => $amount, 'plan' => jenc($plan),
    'status' => 'WAIT', 'stage' => $route[0], 'route' => jenc($route), 'acct_bank' => $u['bank'], 'acct_no' => $u['bank_no'], 'acct_name' => $u['bank_name'],
    'stage_since' => now(), 'created_at' => now(), 'updated_at' => now()]);
  foreach ($plan as $r) foreach (($r['perdin']['maps'] ?? []) as $fid) link_files([(int) $fid], 'pc', $id, null, 'maps');
  $p = get_pc($id);
  hist('pc', $id, 'Petty cash diajukan', $u, null, null, $p['number'] . ' · ' . rp($amount));
  foreach (stage_users($route[0], $p['region'], $p['entity']) as $a) notify($a['id'], 'tugas', "Petty cash {$p['number']} menunggu giliran Anda.", 'pc', $id);
  return $id;
}
function pc_next(array $p): ?string { $r = jdec($p['route']) ?: []; $i = array_search($p['stage'], $r, true); return $i === false ? null : ($r[$i + 1] ?? null); }

function pc_act(array $u, int $id, array $in): array {
  $p = get_pc($id); $act = $in['action'] ?? ''; $notes = trim($in['notes'] ?? '');
  if ($act === 'rev_done') {
    if ($p['status'] !== 'REV') fail('Tidak sedang perlu revisi.');
    if (!((int) $p['claimant_id'] === (int) $u['id']) && !sa_free($u)) fail('Tidak diizinkan.', 403);
    if (!empty($in['payload'])) { $pl = $in['payload']; $sum = array_sum(array_map(fn($r) => (int) $r['nominal'], $pl['plan'] ?? []));
      if ($sum !== (int) $pl['amount']) fail('Total rencana harus sama dengan jumlah diminta.'); if ((int) $pl['amount'] > (int) $u['petty_ceiling']) fail('Melebihi plafon.');
      ex("UPDATE claim_pc SET purpose=?, remark=?, amount=?, plan=?, p_start=?, p_end=? WHERE id=?", [$pl['purpose'], trim($pl['remark'] ?? '') ?: null, $pl['amount'], jenc($pl['plan']), $pl['p_start'], $pl['p_end'], $id]); }
    $resume = $p['rev_stage'];
    ex("UPDATE claim_pc SET status='WAIT', stage=?, rev_holder=NULL, rev_stage=NULL, stage_since=?, updated_at=? WHERE id=?", [$resume, now(), now(), $id]);
    hist('pc', $id, 'Perbaikan selesai', $u, null, 'Perlu Revisi', 'Kembali ke ' . STAGE_NAME[$resume]);
    foreach (stage_users($resume, $p['region'], $p['entity']) as $a) notify($a['id'], 'tugas', "Petty cash {$p['number']} kembali ke Anda.", 'pc', $id);
    return ['ok' => 1];
  }
  if ($p['status'] !== 'WAIT') fail('Petty cash tidak sedang menunggu keputusan.');
  $stage = $p['stage'];
  if (!pc_stage_ok($u, $p, $stage)) fail('Anda tidak berwenang pada tahap ini (atau ini petty cash Anda sendiri).', 403);
  if ($act === 'reject') {
    if ($notes === '') fail('Alasan penolakan wajib.');
    ex("UPDATE claim_pc SET status='DITOLAK', stage=NULL, updated_at=? WHERE id=?", [now(), $id]);
    ins('claim_approvals', ['ref_type' => 'pc', 'ref_id' => $id, 'version_no' => 1, 'stage' => $stage, 'user_id' => $u['id'], 'action' => 'reject', 'notes' => $notes, 'created_at' => now()]);
    hist('pc', $id, 'Ditolak', $u, $notes, null, null, $stage); notify((int) $p['claimant_id'], 'ditolak', "Petty cash {$p['number']} ditolak.", 'pc', $id); return ['ok' => 1];
  }
  if ($act === 'revise') {
    if ($notes === '') fail('Catatan revisi wajib.');
    ex("UPDATE claim_pc SET status='REV', rev_stage=?, rev_holder='PEMOHON', rev_note=?, stage=NULL, updated_at=? WHERE id=?", [$stage, $notes, now(), $id]);
    ins('claim_approvals', ['ref_type' => 'pc', 'ref_id' => $id, 'version_no' => 1, 'stage' => $stage, 'user_id' => $u['id'], 'action' => 'revise', 'notes' => $notes, 'created_at' => now()]);
    hist('pc', $id, 'Minta revisi', $u, $notes, null, 'Perlu Revisi · di Pemohon', $stage); notify((int) $p['claimant_id'], 'revisi', "Petty cash {$p['number']} perlu revisi: $notes", 'pc', $id); return ['ok' => 1];
  }
  if ($act === 'transfer') {
    if ($stage !== 'FT') fail('Belum giliran Finance Transfer.');
    $no = trim($in['transfer_no'] ?? ''); $proof = array_map('intval', $in['proof'] ?? []);
    if ($no === '' || !$proof) fail('Nomor dan bukti transfer wajib.');
    ins('claim_transfers', ['ref_type' => 'pc', 'ref_id' => $id, 'transfer_no' => $no, 'amount' => $p['amount'], 'user_id' => $u['id'], 'entity' => $p['entity'], 'created_at' => now()]);
    link_files($proof, 'pc', $id, null, 'transfer');
    ex("UPDATE claim_pc SET status='DANA', stage=NULL, funded_at=?, updated_at=? WHERE id=?", [now(), now(), $id]);
    hist('pc', $id, 'Dana ditransfer', $u, $no, null, rp((int) $p['amount']), 'FT');
    notify((int) $p['claimant_id'], 'selesai', "Dana petty cash {$p['number']} sudah ditransfer. Kirim nota sebelum akhir periode.", 'pc', $id); return ['ok' => 1];
  }
  if ($act !== 'approve' || $stage === 'FT') fail('Aksi tidak valid.');
  ins('claim_approvals', ['ref_type' => 'pc', 'ref_id' => $id, 'version_no' => 1, 'stage' => $stage, 'user_id' => $u['id'], 'action' => 'approve', 'notes' => $notes ?: null, 'created_at' => now()]);
  $next = pc_next($p);
  ex("UPDATE claim_pc SET stage=?, stage_since=?, updated_at=? WHERE id=?", [$next, now(), now(), $id]);
  hist('pc', $id, 'Disetujui ' . STAGE_NAME[$stage], $u, $notes ?: null, null, 'Lanjut ke ' . STAGE_NAME[$next], $stage);
  foreach (stage_users($next, $p['region'], $p['entity']) as $a) notify($a['id'], 'tugas', "Petty cash {$p['number']} menunggu Anda.", 'pc', $id);
  return ['ok' => 1, 'next' => $next];
}
function pc_add_note(array $u, int $id, array $in): int {
  $p = get_pc($id);
  if ((int) $p['claimant_id'] !== (int) $u['id'] && !sa_free($u)) fail('Tidak diizinkan.', 403);
  if ($p['status'] !== 'DANA') fail('Nota hanya dapat dikirim setelah dana diterima dan sebelum pertanggungjawaban dikirim.');
  $nom = (int) ($in['nominal'] ?? 0); $date = $in['date'] ?? '';
  if ($nom <= 0) fail('Nominal wajib.'); if (!$date) fail('Tanggal wajib.');
  if ($date < $p['p_start'] || $date > $p['p_end']) fail('Tanggal nota harus berada dalam periode petty cash (' . $p['p_start'] . ' s/d ' . $p['p_end'] . ').');
  if ($date > date('Y-m-d')) fail('Tanggal nota tidak boleh di masa depan.');
  $files = array_map('intval', $in['files'] ?? []); if (!$files) fail('Foto nota wajib.');
  foreach ($files as $fid) { $sha = val("SELECT sha FROM claim_files WHERE id=?", [$fid]);
    if (val("SELECT 1 FROM claim_files WHERE sha=? AND slot IN ('nota','pcnota') AND ref_type IS NOT NULL AND id<>? AND ref_type IN ('claim','pc')", [$sha, $fid])) fail('Foto nota ini sudah pernah dipakai.'); }
  $off = isset($in['plan_idx']) && $in['plan_idx'] !== '' && $in['plan_idx'] !== null ? 0 : 1;
  $nid = ins('claim_pc_notes', ['pc_id' => $id, 'nominal' => $nom, 'nota_date' => $date, 'plan_idx' => $off ? null : (int) $in['plan_idx'], 'off_plan' => $off, 'uraian' => trim($in['uraian'] ?? '')]);
  link_files($files, 'pc', $id, $nid, 'pcnota');
  hist('pc', $id, 'Nota ditambahkan', $u, ($in['uraian'] ?? '') . ($off ? ' (di luar rencana)' : ''), null, rp($nom));
  return $nid;
}
function pc_send_report(array $u, int $id): array {
  $p = get_pc($id);
  if ((int) $p['claimant_id'] !== (int) $u['id'] && !sa_free($u)) fail('Tidak diizinkan.', 403);
  if ($p['status'] !== 'DANA') fail('Status tidak sesuai.');
  $real = (int) val("SELECT COALESCE(SUM(nominal),0) FROM claim_pc_notes WHERE pc_id=?", [$id]);
  if ($real <= 0) fail('Kirim minimal satu nota dulu.');
  $amount = (int) $p['amount']; $sisa = max(0, $amount - $real); $excess = max(0, $real - $amount); $claimId = null;
  if ($excess > 0) {
    $t = one("SELECT id FROM claim_types WHERE name='Reimburse petty cash'");
    $cu = user_row((int) $p['claimant_id']);
    $claimId = ins('claims', ['claimant_id' => $cu['id'], 'created_by' => $cu['id'], 'region' => $cu['region'], 'entity' => $cu['entities'][0] ?? null, 'category' => 'Operasional', 'type_id' => $t['id'],
      'brand' => 'Lainnya', 'refnote' => $p['number'], 'location' => $p['location'], 'total' => $excess, 'status' => 'WAIT', 'stage' => 'ADMIN', 'budget_period' => date('Y-m'),
      'current_version' => 1, 'meta' => jenc(['keterangan' => 'Kelebihan pemakaian petty cash ' . $p['number']]), 'acct_bank' => $cu['bank'], 'acct_no' => $cu['bank_no'], 'acct_name' => $cu['bank_name'], 'acct_own' => 1,
      'pc_id' => $id, 'stage_since' => now(), 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $c = get_claim($claimId);
    ex("UPDATE claims SET number=?, route=? WHERE id=?", [next_number('KLM', ''), jenc(['ADMIN', 'FM', 'FT']), $claimId]);
    ins('claim_versions', ['claim_id' => $claimId, 'version_no' => 1, 'total' => $excess, 'reason' => 'Reimburse kelebihan petty cash', 'created_at' => now()]);
    $nid = ins('claim_notes', ['claim_id' => $claimId, 'line_no' => 1, 'nominal' => $excess, 'nota_date' => date('Y-m-d'), 'detail' => jenc(['komponen' => 'Kelebihan petty cash'])]);
    foreach (q("SELECT id FROM claim_files WHERE ref_type='pc' AND ref_id=? AND slot='pcnota' LIMIT 1", [$id]) as $f) {
      $src = one("SELECT * FROM claim_files WHERE id=?", [$f['id']]); unset($src['id']); $src['ref_type'] = 'claim'; $src['ref_id'] = $claimId; $src['note_id'] = $nid; $src['slot'] = 'pcref'; ins('claim_files', $src);
    }
    hist('claim', $claimId, 'Dibuat otomatis dari kelebihan petty cash', $u, $p['number'], null, rp($excess));
    $c = get_claim($claimId); notify_stage('ADMIN', $c, "Klaim {$c['number']} (reimburse petty cash) menunggu pemeriksaan.");
  }
  $new = $sisa > 0 ? 'SETOR' : 'TUTUP';
  ex("UPDATE claim_pc SET status=?, realized=?, sisa=?, nota_sent_at=?, excess_claim_id=?, updated_at=? WHERE id=?", [$new, $real, $sisa, now(), $claimId, now(), $id]);
  hist('pc', $id, 'Pertanggungjawaban dikirim', $u, null, 'Terpakai ' . rp($real), $sisa > 0 ? 'Sisa ' . rp($sisa) . ' perlu disetor' : ($excess ? 'Kelebihan ' . rp($excess) . ' jadi klaim reimburse' : 'Sisa nol'));
  foreach (stage_users('FT', $p['region'], $p['entity']) as $a) notify($a['id'], 'tugas', "Petty cash {$p['number']} " . ($sisa > 0 ? 'menunggu setoran sisa.' : 'siap ditutup.'), 'pc', $id);
  return ['sisa' => $sisa, 'excess' => $excess, 'status' => $new];
}
function pc_setoran(array $u, int $id, array $in): void {
  $p = get_pc($id);
  if ((int) $p['claimant_id'] !== (int) $u['id'] && !sa_free($u)) fail('Tidak diizinkan.', 403);
  if ($p['status'] !== 'SETOR') fail('Tidak ada sisa yang perlu disetor.');
  $no = trim($in['setoran_no'] ?? ''); $files = array_map('intval', $in['files'] ?? []);
  if ($no === '' || !$files) fail('Nomor setoran dan bukti wajib.');
  link_files($files, 'pc', $id, null, 'setoran');
  ex("UPDATE claim_pc SET setoran_no=?, setoran_amount=?, updated_at=? WHERE id=?", [$no, $p['sisa'], now(), $id]);
  hist('pc', $id, 'Bukti setoran dikirim', $u, $no, null, rp((int) $p['sisa']));
  foreach (stage_users('FT', $p['region'], $p['entity']) as $a) notify($a['id'], 'tugas', "Bukti setoran {$p['number']} menunggu konfirmasi.", 'pc', $id);
}
function pc_close(array $u, int $id): void {
  $p = get_pc($id);
  if (!sa_free($u) && !(has_role($u, 'FT') && in_scope($u, $p, 'FT'))) fail('Hanya Finance Transfer.', 403);
  if ($p['status'] === 'SETOR' && !$p['setoran_no']) fail('Bukti setoran belum dikirim pemohon.');
  if (!in_array($p['status'], ['SETOR', 'TUTUP'], true)) fail('Status tidak sesuai.');
  ex("UPDATE claim_pc SET status='SELESAI', done_at=?, updated_at=? WHERE id=?", [now(), now(), $id]);
  hist('pc', $id, $p['status'] === 'SETOR' ? 'Setoran dikonfirmasi, petty cash selesai' : 'Petty cash ditutup (sisa nol)', $u, null, null, null, 'FT');
  notify((int) $p['claimant_id'], 'selesai', "Petty cash {$p['number']} selesai.", 'pc', $id);
}
