<?php
require_once __DIR__ . '/pc.php';

function age_days(?string $since): int { return $since ? (int) floor((time() - strtotime($since)) / 86400) : 0; }
function status_label(array $c): string {
  return match ($c['status']) {
    'DRAFT' => 'Draft', 'WAIT' => ba_pemohon_pending($c) ? 'Menunggu TTD BA Pemohon' : 'Menunggu ' . STAGE_NAME[$c['stage']], 'DONE' => 'Selesai', 'REJECTED' => 'Ditolak',
    'REV' => 'Perlu Revisi · di ' . ($c['rev_holder'] === 'ADMIN' ? 'Admin' : 'Pemohon'), default => $c['status'],
  };
}
function row_out(array $c): array {
  $n = aging_days(); $age = age_days($c['stage_since']);
  $t = ['name' => claim_type_label($c)];
  $cl = one("SELECT name, job FROM claim_users WHERE id=?", [$c['claimant_id']]);
  return ['id' => (int) $c['id'], 'date' => substr((string) ($c['submitted_at'] ?: $c['created_at']), 0, 10), 'number' => $c['number'], 'claimant' => $cl['name'] ?? '', 'job' => $cl['job'] ?? '', 'region' => $c['region'], 'entity' => $c['entity'], 'entity_by' => !empty($c['entity_by']) ? val("SELECT name FROM claim_users WHERE id=?", [$c['entity_by']]) : null,
    'category' => $c['category'], 'type' => $t['name'] ?? '', 'brand' => $c['brand'], 'refnote' => $c['refnote'], 'total' => (int) $c['total'], 'status' => $c['status'], 'stage' => $c['stage'],
    'label' => status_label($c), 'version' => (int) $c['current_version'], 'ba' => (bool) $c['ba_required'], 'over' => (int) $c['over_budget_amount'], 'outside' => (bool) $c['outside_sales'],
    'age_days' => $age, 'aging' => $n !== null && in_array($c['status'], ['WAIT', 'REV'], true) && $age > $n, 'since' => $c['stage_since'], 'created_at' => $c['created_at'], 'submitted_at' => $c['submitted_at'],
    'rev_holder' => $c['rev_holder'], 'rev_stage' => $c['rev_stage']];
}
/** Daftar klaim berdasarkan tampilan (view) dan cakupan peran. */
function claim_list(array $u, array $f): array {
  $view = $f['view'] ?? 'mine'; $w = ['1=1']; $p = [];
  $todo = [];
  if (!empty($f['todo'])) { // Perlu tindakan saya: antrean semua peran yang dipegang + revisi di Admin + revisi/BA di tangan pemohon
    $sub = fn(array $g) => claim_list($u, $g + ['limit' => 200, 'todo' => 0])['rows'];
    $mineOnly = ($f['todo'] ?? '') === 'mine'; // dari layar Klaim saya: hanya yang harus dikerjakan sebagai pemohon
    foreach ($mineOnly ? [] : ['ADMIN', 'AUDIT', 'HOS', 'FM', 'FT'] as $r) if (has_role($u, $r)) {
      $nm = ['ADMIN' => 'Admin', 'AUDIT' => 'Audit', 'HOS' => 'HOS', 'FM' => 'Finance Manager', 'FT' => 'Finance Transfer'][$r];
      foreach ([0, 1] as $ba) foreach ($sub(['view' => 'queue', 'stage' => $r, 'ba' => $ba]) as $x) $todo[(int) $x['id']] = $nm;
    }
    if (!$mineOnly && has_role($u, 'ADMIN')) foreach ($sub(['view' => 'revisi_admin']) as $x) $todo[(int) $x['id']] = 'Revisi di Admin';
    foreach (q("SELECT * FROM claims WHERE claimant_id=? AND status IN ('WAIT','REV')", [$u['id']]) as $c) {
      if ($c['status'] === 'REV' && $c['rev_holder'] === 'PEMOHON') $todo[(int) $c['id']] = 'Perbaiki klaim';
      elseif ($c['status'] === 'WAIT' && ba_pemohon_pending($c)) $todo[(int) $c['id']] = 'Tanda tangan BA';
    }
    $f['view'] = $view = 'all'; $w[] = $todo ? 'id IN (' . implode(',', array_keys($todo)) . ')' : '0=1';
  }
  $scopeSql = function (string $role) use ($u, &$w, &$p) {
    if (sa_free($u) || has_role($u, 'SA')) return;
    if (in_array($role, ['ADMIN', 'RGM'], true) && $u['region']) { $w[] = 'region=?'; $p[] = $u['region']; }
    if (in_array($role, ['AUDIT', 'FM', 'FT'], true) && $u['entities']) { $en = array_values(array_unique(array_merge($u['entities'], uncovered_entities($role)))); $w[] = 'entity IN (' . implode(',', array_fill(0, count($en), '?')) . ')'; array_push($p, ...$en); }
  };
  switch ($view) {
    case 'mine': $w[] = '(claimant_id=? OR created_by=?)'; array_push($p, $u['id'], $u['id']);
      $tab = $f['tab'] ?? 'proses';
      $w[] = match ($tab) { 'selesai' => "status='DONE'", 'revisi' => "status='REV'", 'ditolak' => "status='REJECTED'", 'draft' => "status='DRAFT'", default => "status='WAIT'" };
      break;
    case 'queue': $st = $f['stage'] ?? acting_role($u); $w[] = "status='WAIT' AND stage=?"; $p[] = $st; $scopeSql($st);
      if (isset($f['ba'])) $w[] = 'ba_required=' . ((int) $f['ba'] ? 1 : 0); elseif (in_array($st, ['ADMIN', 'HOS', 'FM'], true)) $w[] = 'ba_required=0';
      if ($st === 'ADMIN' || $st === 'FM' || $st === 'FT' || $st === 'AUDIT') {} 
      if (!sa_free($u)) { $w[] = 'claimant_id<>? AND created_by<>?'; array_push($p, $u['id'], $u['id']); }
      break;
    case 'ba': $w[] = "status IN ('WAIT','REV') AND ba_required=1"; if (!has_role($u, 'HOS')) foreach (['ADMIN', 'AUDIT', 'FM', 'FT'] as $r) if (has_role($u, $r)) { $scopeSql($r); break; } break;
    case 'revisi_admin': $w[] = "status='REV' AND rev_holder='ADMIN'"; $scopeSql('ADMIN'); break;
    case 'proses': $w[] = "status IN ('WAIT','REV')"; foreach (['ADMIN', 'AUDIT', 'FM', 'FT'] as $r) if (has_role($u, $r)) { $scopeSql($r); break; } break;
    case 'selesai': $w[] = "status='DONE'"; foreach (['ADMIN', 'AUDIT', 'FM', 'FT'] as $r) if (has_role($u, $r)) { $scopeSql($r); break; } break;
    case 'all':
      $w[] = "status<>'DRAFT'";
      if (!$todo && !empty($f['todo'])) break;
      if (!empty($todo)) break;
      if (!(has_role($u, 'HOS') || has_role($u, 'SA'))) { $done = false; foreach (['ADMIN', 'AUDIT', 'FM', 'FT'] as $r) if (has_role($u, $r)) { $scopeSql($r); $done = true; break; } if (!$done) { $w[] = '(claimant_id=? OR created_by=?)'; array_push($p, $u['id'], $u['id']); } }
      break;
  }
  if (!empty($f['q'])) { $w[] = '(number LIKE ? OR refnote LIKE ? OR claimant_id IN (SELECT id FROM claim_users WHERE name LIKE ?))'; $k = '%' . $f['q'] . '%'; array_push($p, $k, $k, $k); }
  if (!empty($f['claimant_id'])) { $w[] = 'claimant_id=?'; $p[] = (int) $f['claimant_id']; }
  if (!empty($f['category'])) { $w[] = 'category=?'; $p[] = $f['category']; }
  if (!empty($f['status'])) { $w[] = 'status=?'; $p[] = $f['status']; }
  if (!empty($f['region'])) { $w[] = 'region=?'; $p[] = $f['region']; }
  if (!empty($f['entity'])) { $w[] = 'entity=?'; $p[] = $f['entity']; }
  if (!empty($f['from'])) { $w[] = 'substr(COALESCE(submitted_at,created_at),1,10)>=?'; $p[] = $f['from']; }
  if (!empty($f['to'])) { $w[] = 'substr(COALESCE(submitted_at,created_at),1,10)<=?'; $p[] = $f['to']; }
  $limit = min(200, (int) ($f['limit'] ?? 50)); $off = (int) ($f['offset'] ?? 0);
  $rows = q("SELECT * FROM claims WHERE " . implode(' AND ', $w) . " ORDER BY COALESCE(stage_since, created_at) ASC, id DESC LIMIT $limit OFFSET $off", $p);
  $total = (int) val("SELECT COUNT(*) FROM claims WHERE " . implode(' AND ', $w), $p);
  $out = array_map('row_out', $rows); if ($todo) foreach ($out as &$o) $o['todo'] = $todo[(int) $o['id']] ?? null; unset($o);
  return ['rows' => $out, 'total' => $total];
}

/** Hitungan untuk kartu beranda. */
function counts(array $u): array {
  $out = [];
  $one = fn(array $f) => claim_list($u, $f + ['limit' => 1])['total'];
  if (is_claimant($u)) foreach (['proses', 'revisi', 'selesai', 'ditolak', 'draft'] as $t) $out['mine_' . $t] = $one(['view' => 'mine', 'tab' => $t]);
  foreach (['ADMIN', 'AUDIT', 'HOS', 'FM', 'FT'] as $r) if (has_role($u, $r)) { $out['queue_' . $r] = $one(['view' => 'queue', 'stage' => $r]); if (in_array($r, ['ADMIN', 'HOS', 'FM'], true)) $out['ba_' . $r] = $one(['view' => 'queue', 'stage' => $r, 'ba' => 1]); }
  if (has_role($u, 'ADMIN')) $out['revisi_admin'] = $one(['view' => 'revisi_admin']);
  $n = aging_days(); $out['aging_days'] = $n;
  foreach (['ADMIN', 'AUDIT', 'HOS', 'FM', 'FT'] as $r) if (isset($out['queue_' . $r]) && $n !== null) {
    $rows = claim_list($u, ['view' => 'queue', 'stage' => $r, 'limit' => 200])['rows'];
    $out['lama_' . $r] = count(array_filter($rows, fn($x) => $x['aging']));
  }
  $out['notif'] = (int) val("SELECT COUNT(*) FROM claim_notif WHERE user_id=? AND is_read=0", [$u['id']]);
  if (has_role($u, 'RGM') || sa_free($u)) $out['pc_rgm'] = (int) val("SELECT COUNT(*) FROM claim_pc WHERE status='WAIT' AND stage='RGM'" . (!sa_free($u) && $u['region'] ? " AND region='" . str_replace("'", '', $u['region']) . "'" : ''));
  foreach (['ADMIN' => 'ADMIN', 'HOS' => 'HOS', 'FM' => 'FM', 'FT' => 'FT'] as $r => $_) if (has_role($u, $r)) $out['pcq_' . $r] = (int) val("SELECT COUNT(*) FROM claim_pc WHERE status='WAIT' AND stage=?", [$r]);
  return $out;
}

/** Detail klaim lengkap dengan izin aksi. */
function claim_detail(array $u, int $id): array {
  $c = get_claim($id);
  if (!can_view($u, $c)) fail('Tidak diizinkan melihat klaim ini.', 403);
  $t = type_row((int) $c['type_id']); $parts = claim_parts($c); $kind = count($parts) > 1 ? 'multi' : form_kind($t['name']);
  $meta = jdec($c['meta']) ?: [];
  $notes = q("SELECT * FROM claim_notes WHERE claim_id=? ORDER BY line_no", [$id]);
  foreach ($notes as &$n) { $n['detail'] = jdec($n['detail']) ?: []; $n['files'] = files_of('claim', $id, null, (int) $n['id']); $n['files'] = array_values(array_filter($n['files'], fn($f) => !in_array($f['slot'], ['removed'], true))); }
  unset($n);
  $files = []; foreach (['odo_awal', 'odo_akhir', 'doc', 'maps', 'transfer'] as $s) $files[$s] = files_of('claim', $id, $s);
  $cu = user_row((int) $c['claimant_id']);
  $ver = (int) $c['current_version'];
  $apps = q("SELECT a.*, u.name AS uname, u.job AS ujob FROM claim_approvals a JOIN claim_users u ON u.id=a.user_id WHERE a.ref_type='claim' AND a.ref_id=? ORDER BY a.id", [$id]);
  foreach ($apps as &$a) $a['valid'] = (int) $a['version_no'] === $ver;
  unset($a);
  $ba = ba_row($id); $baOut = null;
  if ($ba) {
    $signs = q("SELECT s.role, s.version_no, s.signed_at, u.name, (s.img IS NOT NULL AND s.img<>'') AS has_img FROM claim_ba_sign s JOIN claim_users u ON u.id=s.user_id WHERE s.ba_id=? ORDER BY s.id", [$ba['id']]);
    foreach ($signs as &$s) $s['valid'] = (int) $s['version_no'] === (int) $ba['version_no'];
    unset($s);
    $baOut = ['id' => (int) $ba['id'], 'number' => $ba['number'], 'creator_role' => $ba['creator_role'], 'creator' => val("SELECT name FROM claim_users WHERE id=?", [$ba['creator_id']]), 'triggers' => array_map(fn($x) => TRIG[$x] ?? $x, jdec($ba['triggers']) ?: []), 'trigger_codes' => jdec($ba['triggers']) ?: [],
      'created_at' => $ba['created_at'], 'explanation' => $ba['explanation'], 'snapshot' => jdec($ba['snapshot']), 'version' => (int) $ba['version_no'], 'signs' => $signs, 'required' => ba_required_roles($c), 'complete' => ba_complete($c), 'files' => files_of('ba', (int) $ba['id']), 'extra' => jdec($ba['extra'] ?? null) ?: null];
  }
  $period = $c['budget_period'] ?: date('Y-m');
  $bctx = $c['outside_sales'] ? null : budget_info((int) $c['claimant_id'], $c['category'], $period);
  $hist = q("SELECT * FROM claim_history WHERE ref_type='claim' AND ref_id=? ORDER BY id", [$id]);
  $route = jdec($c['route']) ?: [];
  // izin
  $perm = ['edit' => false, 'act' => false, 'transfer' => false, 'revise_target' => true, 'rev_throw' => false, 'rev_done' => false, 'cancel' => false, 'ba_sign' => false, 'admin_ba' => false];
  if (owner_can_edit($u, $c)) { $perm['edit'] = true; $perm['rev_done'] = $c['status'] === 'REV'; }
  if ($c['status'] === 'REV' && $c['rev_holder'] === 'ADMIN' && ((has_role($u, 'ADMIN') && in_scope($u, $c, 'ADMIN') && !same_person($u, $c)) || sa_free($u))) $perm['rev_throw'] = true;
  if ($c['status'] === 'WAIT' && stage_owner_ok($u, $c, $c['stage'])) { $perm['act'] = true; $perm['transfer'] = $c['stage'] === 'FT'; $perm['admin_ba'] = $c['stage'] === 'ADMIN' && !$ba; $perm['revise_target'] = !($c['stage'] === 'ADMIN' || $c['outside_sales']); }
  if (same_person($u, $c) && in_array($c['status'], ['DRAFT', 'REV'], true)) $perm['cancel'] = true;
  $perm['followup'] = followup_ok($u, $c);
  if ($ba && same_person($u, $c) && in_array($c['status'], ['WAIT', 'REV'], true)) { $perm['ba_sign'] = !array_filter($baOut['signs'], fn($s) => $s['role'] === 'PEMOHON' && $s['valid']); }
  $next = null;
  if ($c['status'] === 'WAIT' && $c['stage']) {
    $rest = array_values(array_filter(array_slice($route, array_search($c['stage'], $route) + 1)));
    $hold = fn(string $st) => array_values(array_map(fn($x) => ['name' => $x['name'], 'job' => $x['job']], array_filter(stage_users($st, $c['region'], $c['entity']), fn($x) => (int) $x['id'] !== (int) $c['claimant_id'])));
    $nm = function (string $st) use ($hold) { $n = array_column($hold($st), 'name'); return $n ? ' (' . implode(', ', array_slice($n, 0, 2)) . (count($n) > 2 ? ' +' . (count($n) - 2) : '') . ')' : ''; };
    $step = [(int) array_search($c['stage'], $route) + 1, count($route)]; $restOut = array_map(fn($st) => ['stage' => $st, 'label' => STAGE_NAME[$st], 'holders' => $hold($st)], $rest);
    $then = $rest ? 'Setelah itu: ' . implode(' → ', array_map(fn($s) => STAGE_NAME[$s] . $nm($s), $rest)) . '.' : '';
    if (ba_pemohon_pending($c)) $next = ['who' => 'PEMOHON', 'text' => 'Menunggu Pemohon (' . $cu['name'] . ') menandatangani BA.', 'then' => 'Begitu Pemohon tanda tangan, ' . STAGE_NAME[$c['stage']] . $nm($c['stage']) . ' bisa memprosesnya. ' . $then, 'holders' => [['name' => $cu['name'], 'job' => $cu['job']]], 'step' => $step, 'rest' => array_merge([['stage' => $c['stage'], 'label' => STAGE_NAME[$c['stage']], 'holders' => $hold($c['stage'])]], $restOut)];
    else $next = ['who' => $c['stage'], 'text' => 'Sekarang di ' . STAGE_NAME[$c['stage']] . ($c['stage'] === 'FT' ? ' (transfer)' : ' (periksa dan setujui)') . '.', 'then' => $then, 'holders' => $hold($c['stage']), 'step' => $step, 'rest' => $restOut];
  } elseif ($c['status'] === 'REV') $next = ['who' => $c['rev_holder'], 'text' => 'Perlu revisi di ' . ($c['rev_holder'] === 'ADMIN' ? 'Admin' : 'Pemohon') . '. Setelah diperbaiki kembali ke ' . STAGE_NAME[$c['rev_stage']] . '.', 'then' => ''];
  if ($ba && $c['status'] === 'WAIT') { $need = array_values(array_filter(ba_required_roles($c), fn($r) => !array_filter($baOut['signs'], fn($s) => $s['role'] === $r && $s['valid']))); $sp = array_map(function ($r) use ($c, $cu) {
    if ($r === 'PEMOHON') return 'Pemohon (' . $cu['name'] . ')';
    $n = array_column(array_filter(stage_users($r, $c['region'], $c['entity']), fn($x) => (int) $x['id'] !== (int) $c['claimant_id']), 'name');
    return (STAGE_NAME[$r] ?? $r) . ($n ? ' (' . implode(', ', array_slice($n, 0, 2)) . (count($n) > 2 ? ' +' . (count($n) - 2) : '') . ')' : '');
  }, $need); $next = ($next ?? []) + ['sign_pending' => $sp, 'sign' => ['done' => count(ba_required_roles($c)) - count($need), 'total' => count(ba_required_roles($c)), 'pending' => array_map(fn($r, $t) => ['role' => $r, 'text' => $t], $need, $sp)]]; }
  return ['next' => $next, 'claim' => $c + ['label' => status_label($c), 'age_days' => age_days($c['stage_since']), 'aging' => row_out($c)['aging']], 'type' => $t, 'kind' => $kind, 'meta' => $meta, 'type_label' => claim_type_label($c), 'parts' => array_map(fn($p) => ['type' => ['id' => (int) $p['type']['id'], 'name' => $p['type']['name'], 'category' => $p['type']['category']], 'kind' => $p['kind'], 'meta' => (object) $p['meta']], $parts), 'notes' => $notes, 'files' => $files,
    'claimant' => ['id' => $cu['id'], 'name' => $cu['name'], 'nik' => $cu['nik'] ?? null, 'job' => $cu['job'], 'region' => $cu['region'], 'entity' => $cu['entity']], 'approvals' => $apps, 'ba' => $baOut, 'budget' => $bctx,
    'history' => $hist, 'route' => $route, 'perm' => $perm, 'versions' => q("SELECT version_no,total,reason,snapshot,created_at FROM claim_versions WHERE claim_id=? ORDER BY version_no", [$id]),
    'admin_check' => jdec($c['admin_check']), 'transfer' => one("SELECT t.*, u.name AS uname FROM claim_transfers t JOIN claim_users u ON u.id=t.user_id WHERE ref_type='claim' AND ref_id=? ORDER BY t.id DESC", [$id]),
    'rev' => $c['status'] === 'REV' ? ['holder' => $c['rev_holder'], 'stage' => $c['rev_stage'], 'note' => $c['rev_note'], 'lines' => jdec($c['rev_notes'] ?? null) ?: new stdClass, 'by' => val("SELECT name FROM claim_users WHERE id=?", [$c['rev_by']])] : null,
    'rule' => current_rule()];
}
function pc_out(array $p): array {
  $plan = jdec($p['plan']) ?: [];
  $notes = q("SELECT * FROM claim_pc_notes WHERE pc_id=? ORDER BY id", [$p['id']]);
  foreach ($notes as &$n) $n['files'] = files_of('pc', (int) $p['id'], 'pcnota', (int) $n['id']);
  unset($n);
  $cl = one("SELECT name, job FROM claim_users WHERE id=?", [$p['claimant_id']]);
  $grace = (int) (current_rule()['pc_grace_days'] ?? 0);
  $deadline = date('Y-m-d', strtotime($p['p_end'] . " +$grace days"));
  $lbl = match ($p['status']) { 'WAIT' => 'Menunggu ' . STAGE_NAME[$p['stage']], 'REV' => 'Perlu Revisi · di Pemohon', 'DANA' => date('Y-m-d') > $deadline ? 'Lewat tenggat' : 'Menunggu nota', 'SETOR' => $p['setoran_no'] ? 'Menunggu konfirmasi setoran' : 'Menunggu setoran sisa', 'TUTUP' => 'Menunggu penutupan Finance', 'SELESAI' => 'Selesai', 'DITOLAK' => 'Ditolak', default => $p['status'] };
  return array_merge($p, ['plan' => $plan, 'notes' => $notes, 'claimant' => $cl['name'], 'job' => $cl['job'], 'label' => $lbl, 'deadline' => $deadline, 'overdue' => $p['status'] === 'DANA' && date('Y-m-d') > $deadline,
    'realized_now' => (int) array_sum(array_column($notes, 'nominal')), 'age_days' => age_days($p['stage_since']), 'aging' => (aging_days() !== null && in_array($p['status'], ['WAIT'], true) && age_days($p['stage_since']) > aging_days())]);
}
function pc_list(array $u, array $f): array {
  $w = ['1=1']; $p = []; $v = $f['view'] ?? 'mine';
  if ($v === 'mine') { $w[] = 'claimant_id=?'; $p[] = $u['id']; }
  elseif ($v === 'queue') { $st = $f['stage'] ?? acting_role($u); $w[] = "status='WAIT' AND stage=?"; $p[] = $st; if (!sa_free($u)) { $w[] = 'claimant_id<>?'; $p[] = $u['id']; if (in_array($st, ['ADMIN', 'RGM']) && $u['region']) { $w[] = 'region=?'; $p[] = $u['region']; } if (in_array($st, ['FM', 'FT']) && $u['entities']) { $w[] = 'entity IN (' . implode(',', array_fill(0, count($u['entities']), '?')) . ')'; array_push($p, ...$u['entities']); } } }
  elseif ($v === 'ft') { $w[] = "status IN ('SETOR','TUTUP')"; if (!sa_free($u) && $u['entities']) { $w[] = 'entity IN (' . implode(',', array_fill(0, count($u['entities']), '?')) . ')'; array_push($p, ...$u['entities']); } }
  elseif ($v === 'monitor') { $w[] = "status IN ('DANA','SETOR','TUTUP')"; }
  elseif ($v === 'proses') { $w[] = "status IN ('WAIT','REV')"; if (!sa_free($u) && !has_role($u, 'HOS') && $u['region'] && has_role($u, 'ADMIN')) { $w[] = 'region=?'; $p[] = $u['region']; } }
  return array_map('pc_out', q("SELECT * FROM claim_pc WHERE " . implode(' AND ', $w) . " ORDER BY COALESCE(stage_since, created_at) ASC LIMIT 100", $p));
}

/** Kalender pengajuan: jumlah dan nominal klaim per tanggal pengajuan dalam satu bulan (mengikuti cakupan peran). */
function cal_month(array $u, string $ym): array {
  if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = date('Y-m');
  $from = $ym . '-01'; $to = date('Y-m-t', strtotime($from)); $days = []; $total = 0;
  for ($off = 0; $off < 3000; $off += 200) {
    $r = claim_list($u, ['view' => 'all', 'from' => $from, 'to' => $to, 'limit' => 200, 'offset' => $off, 'todo' => 0]);
    foreach ($r['rows'] as $x) { $d = $x['date']; $days[$d]['n'] = ($days[$d]['n'] ?? 0) + 1; $days[$d]['sum'] = ($days[$d]['sum'] ?? 0) + $x['total']; $total++; }
    if (count($r['rows']) < 200) break;
  }
  $real = 0;
  foreach (q("SELECT ref_id, created_at FROM claim_transfers WHERE ref_type='claim' AND substr(created_at,1,10)>=? AND substr(created_at,1,10)<=?", [$from, $to]) as $t) {
    $c = one("SELECT * FROM claims WHERE id=?", [$t['ref_id']]); if (!$c || !can_view($u, $c)) continue;
    $d = substr($t['created_at'], 0, 10); $days[$d]['r'] = ($days[$d]['r'] ?? 0) + 1; $days[$d]['n'] = $days[$d]['n'] ?? 0; $days[$d]['sum'] = $days[$d]['sum'] ?? 0; $real++;
  }
  return ['month' => $ym, 'days' => $days ?: new stdClass, 'total' => $total, 'real' => $real];
}
/** Isi satu tanggal kalender: klaim yang dikirim pada hari itu dan klaim yang ditransfer (direalisasikan) pada hari itu. */
function cal_day(array $u, string $date): array {
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) fail('Tanggal tidak valid.', 422);
  $sub = claim_list($u, ['view' => 'all', 'from' => $date, 'to' => $date, 'limit' => 100, 'todo' => 0])['rows']; $real = [];
  foreach (q("SELECT ref_id FROM claim_transfers WHERE ref_type='claim' AND substr(created_at,1,10)=? ORDER BY id", [$date]) as $t) { $c = one("SELECT * FROM claims WHERE id=?", [$t['ref_id']]); if ($c && can_view($u, $c)) $real[] = row_out($c); }
  return ['sub' => $sub, 'real' => $real];
}
