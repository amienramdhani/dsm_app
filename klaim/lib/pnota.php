<?php
/* Perdin balik nota (mockup doc/mockup/2026-11-20/dsm-klaim-perdin-balik-nota.html).
   Perdin boleh diajukan di muka. Setelah Finance Transfer, klaim tetap status DONE (uang sudah keluar) tetapi nota_state:
   WAIT (menunggu nota) -> CEK (Admin periksa) -> REV (nota perlu revisi) / SETOR (sisa dana perlu disetor) -> OK.
   Selisih lebih (terpakai > dana) otomatis menjadi klaim reimburse. Telat melewati tenggat = pengajuan klaim baru diblokir (lihat pc_blocked). */
require_once __DIR__ . '/pc.php';

const PN_OPEN = ['WAIT', 'REV'];
function pn_kind(array $c): bool { $p = claim_parts($c); return count($p) === 1 && $p[0]['kind'] === 'perdin'; }
function pn_due(array $c): ?string {
  $m = jdec($c['meta']) ?: []; $e = $m['tgl_pulang'] ?? null; if (!$e) return null;
  $grace = (int) (current_rule()['pc_grace_days'] ?? 0);
  return date('Y-m-d', strtotime($e . " +$grace days"));
}
/** Dipanggil saat Finance Transfer mencatat transfer klaim Perdin. */
function pn_start(array $c, array $u): void {
  if (!pn_kind($c)) return;
  $due = pn_due($c);
  ex("UPDATE claims SET nota_state='WAIT', nota_due=?, updated_at=? WHERE id=?", [$due, now(), $c['id']]);
  hist('claim', (int) $c['id'], 'Menunggu balik nota', $u, null, null, $due ? 'Tenggat ' . $due : null, 'FT');
  notify((int) $c['claimant_id'], 'tugas', "Klaim Perdin {$c['number']} sudah ditransfer. Mohon balik nota" . ($due ? " sebelum $due." : '.'), 'claim', (int) $c['id']);
}
function pn_label(array $c): ?string {
  $s = $c['nota_state'] ?? null; if (!$s || $s === 'OK') return null;
  if (in_array($s, PN_OPEN, true) && $c['nota_due'] && date('Y-m-d') > $c['nota_due']) return 'Lewat tenggat nota';
  return ['WAIT' => 'Menunggu nota', 'REV' => 'Nota perlu revisi', 'CEK' => 'Nota diperiksa Admin', 'SETOR' => ($c['nota_setoran_no'] ?? null) ? 'Menunggu konfirmasi setoran' : 'Menunggu setoran sisa'][$s] ?? null;
}
function pn_is_admin(array $u, array $c): bool { return sa_free($u) || (has_role($u, 'ADMIN') && in_scope($u, $c, 'ADMIN') && !same_person($u, $c)); }
function pn_is_ft(array $u, array $c): bool { return sa_free($u) || (has_role($u, 'FT') && in_scope($u, $c, 'FT')); }

/** Data balik nota untuk detail klaim. */
function pn_out(array $u, array $c): ?array {
  $s = $c['nota_state'] ?? null; if (!$s) return null;
  $notes = q("SELECT * FROM claim_notes WHERE claim_id=? ORDER BY line_no", [$c['id']]); $plan = []; $makan = 0;
  foreach ($notes as $n) { $d = jdec($n['detail']) ?: [];
    if (($d['komponen'] ?? '') === 'Uang makan') { $makan += (int) $n['nominal']; continue; }
    $plan[] = ['id' => (int) $n['id'], 'komponen' => $d['komponen'] ?? 'Nota', 'jenis' => $d['jenis'] ?? ($d['keterangan'] ?? ''), 'nominal' => (int) $n['nominal'], 'mode' => $d['mode'] ?? 'sekali']; }
  $rn = q("SELECT * FROM claim_rnotes WHERE claim_id=? ORDER BY line_no", [$c['id']]);
  foreach ($rn as &$r) { $r['files'] = array_values(array_filter(files_of('claim', (int) $c['id'], 'rnota', (int) $r['id']), fn($f) => $f['slot'] === 'rnota')); } unset($r);
  $own = same_person($u, $c); $real = (int) array_sum(array_column($rn, 'nominal')) + $makan;
  $m = jdec($c['meta']) ?: [];
  return ['state' => $s, 'label' => pn_label($c) ?? 'Selesai', 'due' => $c['nota_due'], 'overdue' => in_array($s, PN_OPEN, true) && $c['nota_due'] && date('Y-m-d') > $c['nota_due'],
    'dana' => (int) $c['total'], 'makan' => $makan, 'plan' => $plan, 'rnotes' => $rn, 'terpakai' => $rn ? $real : null, 'sisa' => $rn ? (int) $c['total'] - $real : null,
    'tgl_berangkat' => $m['tgl_berangkat'] ?? null, 'tgl_pulang' => $m['tgl_pulang'] ?? null, 'rev_note' => $c['nota_rev_note'] ?? null,
    'setoran_no' => $c['nota_setoran_no'] ?? null, 'setoran_files' => files_of('claim', (int) $c['id'], 'setoran'), 'excess_claim_id' => $c['nota_excess_id'] ?? null,
    'perm' => ['submit' => ($own || sa_free($u)) && in_array($s, PN_OPEN, true), 'review' => $s === 'CEK' && pn_is_admin($u, $c), 'setoran' => ($own || sa_free($u)) && $s === 'SETOR' && !$c['nota_setoran_no'], 'close' => $s === 'SETOR' && !!$c['nota_setoran_no'] && pn_is_ft($u, $c)]];
}

/** Pemohon mengirim balik nota (menggantikan isian sebelumnya bila sedang revisi). */
function pn_submit(array $u, int $id, array $in): array {
  $c = get_claim($id);
  if (!same_person($u, $c) && !sa_free($u)) fail('Tidak diizinkan.', 403);
  if (!in_array($c['nota_state'] ?? '', PN_OPEN, true)) fail('Klaim ini tidak sedang menunggu balik nota.');
  $m = jdec($c['meta']) ?: []; $a = $m['tgl_berangkat'] ?? null; $b = $m['tgl_pulang'] ?? null; $today = date('Y-m-d');
  $rows = array_values($in['notes'] ?? []); if (!$rows) fail('Isi minimal satu nota.', 422);
  $planIds = array_map('intval', array_column(q("SELECT id FROM claim_notes WHERE claim_id=?", [$id]), 'id')); $clean = [];
  foreach ($rows as $i => $r) {
    $no = 'Nota ' . ($i + 1) . ': '; $nom = (int) ($r['nominal'] ?? 0); $date = (string) ($r['date'] ?? ''); $files = array_map('intval', $r['files'] ?? []);
    if ($nom <= 0) fail($no . 'nominal wajib.', 422);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) fail($no . 'tanggal wajib.', 422);
    if ($a && $b && ($date < $a || $date > $b)) fail($no . 'tanggal harus di antara tanggal berangkat dan pulang.', 422);
    if ($date > $today) fail($no . 'tanggal tidak boleh di masa depan.', 422);
    if (!$files) fail($no . 'foto nota wajib.', 422);
    files_check($u, $files, 'claim', $id);
    foreach ($files as $fid) { $sha = val("SELECT sha FROM claim_files WHERE id=?", [$fid]);
      if (val("SELECT 1 FROM claim_files WHERE sha=? AND slot IN ('nota','pcnota','rnota') AND ref_type IS NOT NULL AND id<>? AND NOT (ref_type='claim' AND ref_id=? AND slot='rnota') AND ref_type IN ('claim','pc')", [$sha, $fid, $id])) fail($no . 'foto nota ini sudah pernah dipakai.', 422); }
    $pid = isset($r['plan_id']) && $r['plan_id'] !== '' && $r['plan_id'] !== null ? (int) $r['plan_id'] : null;
    if ($pid !== null && !in_array($pid, $planIds, true)) fail($no . 'komponen rencana tidak dikenal.', 422);
    $clean[] = ['nom' => $nom, 'date' => $date, 'files' => $files, 'pid' => $pid, 'komponen' => mb_substr(trim((string) ($r['komponen'] ?? '')), 0, 30), 'uraian' => mb_substr(trim((string) ($r['uraian'] ?? '')), 0, 160)];
  }
  db()->beginTransaction();
  try {
    lock_row('claims', $id);
    if (ex("UPDATE claims SET nota_state='CEK', updated_at=? WHERE id=? AND nota_state IN ('WAIT','REV')", [now(), $id]) !== 1) fail('Balik nota ini sudah dikirim.', 409);
    foreach (q("SELECT id FROM claim_rnotes WHERE claim_id=?", [$id]) as $o) ex("UPDATE claim_files SET slot='removed' WHERE ref_type='claim' AND ref_id=? AND note_id=? AND slot='rnota'", [$id, $o['id']]);
    ex("DELETE FROM claim_rnotes WHERE claim_id=?", [$id]);
    foreach ($clean as $i => $r) {
      $nid = ins('claim_rnotes', ['claim_id' => $id, 'line_no' => $i + 1, 'plan_note_id' => $r['pid'], 'komponen' => $r['komponen'], 'uraian' => $r['uraian'], 'nominal' => $r['nom'], 'nota_date' => $r['date'], 'off_plan' => $r['pid'] === null ? 1 : 0]);
      link_files($u, $r['files'], 'claim', $id, $nid, 'rnota');
    }
    $makan = 0; foreach (q("SELECT nominal, detail FROM claim_notes WHERE claim_id=?", [$id]) as $n) if ((jdec($n['detail'])['komponen'] ?? '') === 'Uang makan') $makan += (int) $n['nominal'];
    $real = (int) array_sum(array_column($clean, 'nom')) + $makan;
    ex("UPDATE claims SET nota_state='CEK', nota_sent_at=?, nota_realized=?, nota_rev_note=NULL, updated_at=? WHERE id=?", [now(), $real, now(), $id]);
    hist('claim', $id, 'Balik nota dikirim', $u, null, 'Terpakai ' . rp($real), 'Dana ' . rp((int) $c['total']) . ' · ' . count($clean) . ' nota');
    db()->commit();
  } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
  foreach (stage_users('ADMIN', $c['region'], $c['entity']) as $x) if ((int) $x['id'] !== (int) $c['claimant_id']) notify((int) $x['id'], 'tugas', "Balik nota klaim Perdin {$c['number']} menunggu pemeriksaan.", 'claim', $id);
  return ['ok' => 1, 'terpakai' => $real, 'sisa' => (int) $c['total'] - $real];
}

/** Admin memeriksa balik nota: approve / revise. */
function pn_review(array $u, int $id, array $in): array {
  $c = get_claim($id); $act = $in['action'] ?? ''; $notes = trim((string) ($in['notes'] ?? ''));
  if (($c['nota_state'] ?? '') !== 'CEK') fail('Balik nota tidak sedang menunggu pemeriksaan.');
  if (!pn_is_admin($u, $c)) fail('Tidak diizinkan.', 403);
  if ($act !== 'revise' && $act !== 'approve') fail('Aksi tidak dikenal.');
  if ($act === 'revise' && $notes === '') fail('Catatan revisi wajib diisi.', 422);
  db()->beginTransaction();
  try {
    $c = lock_row('claims', $id);
    $new = 'REV'; $real = (int) ($c['nota_realized'] ?? 0); $dana = (int) $c['total']; $sisa = $dana - $real; $excessId = null;
    if ($act === 'approve') $new = $sisa > 0 ? 'SETOR' : 'OK';
    // hanya satu request yang bisa memindahkan status dari CEK; request kedua berhenti di sini, sebelum klaim reimburse dibuat
    if (ex("UPDATE claims SET nota_state=?, updated_at=? WHERE id=? AND nota_state='CEK'", [$new, now(), $id]) !== 1) fail('Balik nota ini sudah diproses.', 409);
    if ($act === 'revise') {
      ex("UPDATE claims SET nota_rev_note=? WHERE id=?", [$notes, $id]);
      hist('claim', $id, 'Balik nota diminta revisi', $u, $notes, null, 'Nota perlu revisi', 'ADMIN');
      notify((int) $c['claimant_id'], 'revisi', "Balik nota klaim Perdin {$c['number']} perlu revisi: $notes", 'claim', $id);
    } else {
      if ($sisa < 0) $excessId = pn_excess_claim($c, -$sisa, $u);
      ex("UPDATE claims SET nota_sisa=?, nota_excess_id=? WHERE id=?", [max(0, $sisa), $excessId, $id]);
      hist('claim', $id, 'Balik nota disetujui Admin', $u, $notes ?: null, 'Terpakai ' . rp($real), $sisa > 0 ? 'Sisa ' . rp($sisa) . ' perlu disetor' : ($sisa < 0 ? 'Kelebihan ' . rp(-$sisa) . ' jadi klaim reimburse' : 'Sisa nol'), 'ADMIN');
      if ($sisa > 0) notify((int) $c['claimant_id'], 'tugas', "Balik nota {$c['number']} disetujui. Mohon setor sisa " . rp($sisa) . '.', 'claim', $id);
      else notify((int) $c['claimant_id'], 'selesai', "Balik nota klaim Perdin {$c['number']} selesai.", 'claim', $id);
    }
    db()->commit();
  } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
  return $act === 'revise' ? ['ok' => 1, 'state' => 'REV'] : ['ok' => 1, 'state' => $new, 'sisa' => max(0, $sisa), 'excess' => max(0, -$sisa)];
}
/** Kelebihan pemakaian Perdin -> klaim reimburse otomatis (pola sama dengan kelebihan petty cash). */
function pn_excess_claim(array $c, int $excess, array $u): int {
  $t = one("SELECT id FROM claim_types WHERE name='Reimburse petty cash'"); if (!$t) fail('Jenis "Reimburse petty cash" belum ada. Jalankan migrasi.', 500);
  $cu = user_row((int) $c['claimant_id']);
  $id = ins('claims', ['claimant_id' => $cu['id'], 'is_demo' => (int) ($c['is_demo'] ?? 0), 'created_by' => $cu['id'], 'region' => $cu['region'], 'entity' => $c['entity'] ?: ($cu['entities'][0] ?? null), 'category' => 'Operasional', 'type_id' => $t['id'],
    'brand' => $c['brand'] ?: 'Lainnya', 'refnote' => $c['number'], 'location' => $c['location'], 'total' => $excess, 'status' => 'WAIT', 'stage' => 'ADMIN', 'budget_period' => date('Y-m'),
    'current_version' => 1, 'meta' => jenc(['keterangan' => 'Kelebihan pemakaian Perdin ' . $c['number']]), 'acct_bank' => $c['acct_bank'], 'acct_no' => $c['acct_no'], 'acct_name' => $c['acct_name'], 'acct_own' => (int) ($c['acct_own'] ?? 1),
    'stage_since' => now(), 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
  ex("UPDATE claims SET number=?, route=? WHERE id=?", [next_number('KLM', ''), jenc(['ADMIN', 'FM', 'FT']), $id]);
  ins('claim_versions', ['claim_id' => $id, 'version_no' => 1, 'total' => $excess, 'reason' => 'Reimburse kelebihan Perdin', 'created_at' => now()]);
  $nid = ins('claim_notes', ['claim_id' => $id, 'line_no' => 1, 'nominal' => $excess, 'nota_date' => date('Y-m-d'), 'detail' => jenc(['komponen' => 'Kelebihan Perdin'])]);
  foreach (q("SELECT id FROM claim_files WHERE ref_type='claim' AND ref_id=? AND slot='rnota' ORDER BY id", [$c['id']]) as $f) {
    $src = one("SELECT * FROM claim_files WHERE id=?", [$f['id']]); unset($src['id']); $src['ref_id'] = $id; $src['note_id'] = $nid; $src['slot'] = 'pcref'; ins('claim_files', $src);
  }
  hist('claim', $id, 'Dibuat otomatis dari kelebihan Perdin', $u, $c['number'], null, rp($excess));
  $n = get_claim($id); notify_stage('ADMIN', $n, "Klaim {$n['number']} (reimburse kelebihan Perdin) menunggu pemeriksaan.");
  return $id;
}
/** Pemohon mengirim bukti setoran sisa dana. */
function pn_setoran(array $u, int $id, array $in): void {
  $c = get_claim($id);
  if (!same_person($u, $c) && !sa_free($u)) fail('Tidak diizinkan.', 403);
  if (($c['nota_state'] ?? '') !== 'SETOR' || $c['nota_setoran_no']) fail('Tidak ada sisa yang perlu disetor.');
  $no = trim((string) ($in['setoran_no'] ?? '')); $files = array_map('intval', $in['files'] ?? []);
  if ($no === '' || !$files) fail('Nomor setoran dan bukti wajib.', 422);
  db()->beginTransaction();
  try {
    lock_row('claims', $id);
    if (ex("UPDATE claims SET nota_setoran_no=?, updated_at=? WHERE id=? AND nota_state='SETOR' AND nota_setoran_no IS NULL", [$no, now(), $id]) !== 1) fail('Bukti setoran sudah dikirim.', 409);
    link_files($u, $files, 'claim', $id, null, 'setoran');
    hist('claim', $id, 'Bukti setoran dikirim', $u, $no, null, rp((int) $c['nota_sisa']));
    db()->commit();
  } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
  foreach (stage_users('FT', $c['region'], $c['entity']) as $a) notify((int) $a['id'], 'tugas', "Bukti setoran klaim Perdin {$c['number']} menunggu konfirmasi.", 'claim', $id);
}
/** Finance Transfer mengonfirmasi setoran -> balik nota selesai. */
function pn_close(array $u, int $id): void {
  $c = get_claim($id);
  if (!pn_is_ft($u, $c)) fail('Hanya Finance Transfer.', 403);
  if (($c['nota_state'] ?? '') !== 'SETOR' || !$c['nota_setoran_no']) fail('Bukti setoran belum dikirim pemohon.');
  if (ex("UPDATE claims SET nota_state='OK', updated_at=? WHERE id=? AND nota_state='SETOR' AND nota_setoran_no IS NOT NULL", [now(), $id]) !== 1) fail('Setoran ini sudah dikonfirmasi.', 409);
  hist('claim', $id, 'Setoran dikonfirmasi, balik nota selesai', $u, null, null, null, 'FT');
  notify((int) $c['claimant_id'], 'selesai', "Balik nota klaim Perdin {$c['number']} selesai.", 'claim', $id);
}
