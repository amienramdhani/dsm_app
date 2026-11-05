<?php
require_once __DIR__ . '/lib/report.php';
require_once __DIR__ . '/lib/seed.php';

function body(): array {
  static $b; if ($b !== null) return $b;
  $raw = file_get_contents('php://input'); $j = $raw && ($raw[0] ?? '') === '{' ? json_decode($raw, true) : null;
  return $b = is_array($j) ? $j + $_GET : array_merge($_GET, $_POST);
}
function out($d, int $code = 200): never { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => true] + (is_array($d) ? $d : ['data' => $d]), JSON_UNESCAPED_UNICODE); exit; }
function need_role(array $u, string ...$roles): void { if (sa_free($u) || has_role($u, 'SA')) return; foreach ($roles as $r) if (has_role($u, $r)) return; fail('Tidak diizinkan.', 403); }
function can_manage_user(array $u, array $t): bool { return sa_free($u) || has_role($u, 'SA') || (has_role($u, 'ADMIN') && (!$u['region'] || $u['region'] === $t['region'])); }

try {
  sess();
  $in = body(); $a = $in['a'] ?? '';
  if ($a === 'login') {
    if (dsm_present()) fail('Masuk lewat DSM.', 401);
    $r = one("SELECT * FROM claim_users WHERE username=? AND active=1", [strtolower(trim($in['username'] ?? ''))]);
    if (!$r || !password_verify($in['password'] ?? '', $r['pass_hash'])) fail('Username atau password salah.', 401);
    session_regenerate_id(true); $_SESSION['uid'] = (int) $r['id']; $_SESSION['real_uid'] = (int) $r['id']; unset($_SESSION['backup']);
    out(['me' => me()]);
  }
  if ($a === 'logout') { $_SESSION = []; session_destroy(); out([]); }
  if ($a === 'demo_users') { if (dsm_present()) out(['users' => []]); out(['users' => array_map(fn($r) => ['username' => $r['username'], 'name' => $r['name'], 'job' => $r['job']], q("SELECT username,name,job FROM claim_users WHERE active=1 ORDER BY id"))]); }
  $u = need_me();
  // Mode lihat: Super Admin yang "masuk sebagai" orang lain (Mode tes mati = produksi) hanya boleh melihat, tidak boleh mengubah apa pun.
  if (!empty($u['impersonating']) && !test_mode() && in_array($a, ['upload', 'claim_save', 'claim_submit', 'claim_act', 'claim_resubmit', 'claim_cancel', 'followup_log', 'wa_save', 'sign_save', 'ba_sign', 'pc_submit', 'pc_act', 'pc_note', 'pc_report', 'pc_setoran', 'pc_close', 'notif_read', 'entitlement_save', 'entitlement_copy', 'type_save', 'rules_save', 'aging_save', 'entity_add', 'entity_del', 'fuel_save', 'sa_user_save', 'sa_seed_uji', 'sa_uji_off', 'sa_testmode', 'sa_reset', 'backup_issue', 'backup_use', 'backup_revoke'], true))
    fail('Mode lihat: Super Admin tidak bisa mengubah data atas nama orang lain. Klik "kembali" dulu.', 403);

  switch ($a) {
    case 'me': out(['dsm_role' => dsm_present() ? (dsm_auth()['role'] ?? '') : null, 'me' => $u, 'counts' => counts($u), 'test_mode' => test_mode(), 'aging_days' => aging_days()]);
    case 'switch_user': // Super Admin: ganti peran untuk menguji
      if (!real_is_sa($u)) fail('Hanya Super Admin.', 403);
      $t = user_row((int) $in['user_id']); if (!$t) fail('Pengguna tidak ada.');
      $_SESSION['uid'] = $t['id']; unset($_SESSION['backup']);
      hist('user', (int) $u['real_id'], 'Super Admin beralih ke ' . $t['name'], $u);
      out(['me' => me()]);
    case 'lookups':
      if (!val("SELECT 1 FROM claim_types WHERE name='Uang makan'")) sync_claim_types(); // jenis baru muncul tanpa menunggu migrate.php
      out(['kota' => array_column(q("SELECT val FROM claim_lookup WHERE kind='kota' ORDER BY val"), 'val'), 'brand' => brand_options(),
        'bank' => array_column(q("SELECT val FROM claim_lookup WHERE kind='bank' ORDER BY val"), 'val'), 'gate' => array_column(q("SELECT name FROM claim_gate ORDER BY used DESC, name LIMIT 300"), 'name'),
        'fuel' => q("SELECT effective_from, pertalite, pertamax FROM claim_fuel_price ORDER BY effective_from DESC, id DESC"), 'types' => q("SELECT id, category, name, active, locked FROM claim_types WHERE active=1 ORDER BY sort_no"), 'regions' => array_column(q("SELECT DISTINCT region FROM claim_users WHERE region IS NOT NULL ORDER BY region"), 'region'),
        'entities' => entity_options(), 'rules' => ['min_km_makan' => min_km_makan(current_rule()), 'bbm_round' => (int) (current_rule()['bbm_round'] ?? 0)],
        'people' => q("SELECT id, name, job, region FROM claim_users WHERE can_submit=1 AND active=1 ORDER BY name")]);
    case 'budget': out(['period' => date('Y-m'), 'budget' => budget_all((int) $u['id'], date('Y-m')), 'blocked' => pc_blocked((int) $u['id']), 'meal_rate' => $u['meal_rate'], 'account' => ['bank' => $u['bank'], 'no' => $u['bank_no'], 'name' => $u['bank_name']]]);
    case 'upload':
      if (empty($_FILES['file'])) fail('File tidak ditemukan.');
      out(['file' => save_upload($u, $_FILES['file'], $in['slot'] ?? 'nota')]);
    case 'file':
      $f = one("SELECT * FROM claim_files WHERE id=?", [(int) $in['id']]); if (!$f) fail('Berkas tidak ada.', 404);
      $okv = false;
      if ($f['ref_type'] === 'claim') $okv = can_view($u, get_claim((int) $f['ref_id']));
      elseif ($f['ref_type'] === 'pc') $okv = pc_view($u, get_pc((int) $f['ref_id']));
      elseif ($f['ref_type'] === 'ba') { $ba = one("SELECT claim_id FROM claim_ba WHERE id=?", [$f['ref_id']]); $okv = $ba && can_view($u, get_claim((int) $ba['claim_id'])); }
      else $okv = (int) $f['uploader'] === (int) $u['id'];
      if (!$okv && !sa_free($u)) fail('Tidak diizinkan.', 403);
      header('Content-Type: ' . $f['mime']); header('Cache-Control: private, max-age=3600'); readfile(upload_dir() . '/' . $f['stored_name']); exit;
    case 'claim_save': $id = claim_save($u, $in); out(['id' => $id, 'check' => (function () use ($id) { $c = get_claim($id); $k = claim_check($c); return ['errors' => $k['errors'], 'triggers' => $k['triggers'], 'budget' => $k['budget'], 'total' => (int) $c['total']]; })()]);
    case 'claim_check': $c = get_claim((int) $in['id']); if (!can_view($u, $c)) fail('Tidak diizinkan.', 403); $k = claim_check($c);
      out(['errors' => $k['errors'], 'triggers' => $k['triggers'], 'trigger_labels' => array_map(fn($t) => TRIG[$t], $k['triggers']), 'budget' => $k['budget'], 'route' => build_route($c + ['ba_required' => $k['triggers'] ? 1 : $c['ba_required']]), 'total' => (int) $c['total'], 'period' => $k['period'], 'info' => $k['info'] ?? new stdClass]);
    case 'claim_submit': out(claim_submit($u, (int) $in['id'], $in));
    case 'claim_get': out(claim_detail($u, (int) $in['id']));
    case 'claim_list': out(claim_list($u, $in));
    case 'claim_act': out(claim_act($u, (int) $in['id'], $in));
    case 'claim_resubmit': out(claim_resubmit($u, (int) $in['id'], $in));
    case 'claim_cancel': claim_cancel($u, (int) $in['id']); out([]);
    case 'followup_info': out(followup_info($u, (int) $in['id']));
    case 'followup_log': followup_log($u, (int) $in['id'], ($in['via'] ?? '') === 'app' ? 'app' : 'wa', (int) ($in['to'] ?? 0)); out([]);
    case 'wa_save': ex("UPDATE claim_users SET wa=? WHERE id=?", [norm_wa($in['wa'] ?? ''), (int) $u['id']]); out(['wa' => val("SELECT wa FROM claim_users WHERE id=?", [(int) $u['id']])]);
    case 'sign_get': out(['img' => val("SELECT sign_img FROM claim_users WHERE id=?", [(int) $u['id']]) ?: null]);
    case 'sign_save': // tanda tangan milik akun yang sedang aktif
      $img = (string) ($in['img'] ?? '');
      if ($img === '') { ex("UPDATE claim_users SET sign_img=NULL WHERE id=?", [(int) $u['id']]); out([]); }
      if (!preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $img) || strlen($img) > 300000) fail('Tanda tangan harus berupa gambar PNG maksimal sekitar 200 KB.', 422);
      $bin = base64_decode(substr($img, 22), true); if ($bin === false || substr($bin, 0, 4) !== "\x89PNG") fail('Gambar tanda tangan tidak valid.', 422);
      ex("UPDATE claim_users SET sign_img=? WHERE id=?", [$img, (int) $u['id']]); hist('user', (int) $u['id'], 'Tanda tangan diperbarui', $u); out([]);
    case 'ba_sign': ba_pemohon_sign($u, (int) $in['id']); out([]);
    case 'counts': out(['counts' => counts($u)]);
    case 'gate_suggest': out(['gate' => array_column(q("SELECT name FROM claim_gate ORDER BY used DESC, name LIMIT 300"), 'name')]);
    // ---- petty cash
    case 'pc_submit': out(['id' => pc_submit($u, $in)]);
    case 'pc_get': $p = get_pc((int) $in['id']); if (!pc_view($u, $p)) fail('Tidak diizinkan.', 403);
      out(['pc' => pc_out($p), 'history' => q("SELECT * FROM claim_history WHERE ref_type='pc' AND ref_id=? ORDER BY id", [$p['id']]), 'transfer' => one("SELECT * FROM claim_transfers WHERE ref_type='pc' AND ref_id=? ORDER BY id DESC", [$p['id']]),
        'setoran_files' => files_of('pc', (int) $p['id'], 'setoran'), 'maps' => files_of('pc', (int) $p['id'], 'maps'), 'can_act' => $p['status'] === 'WAIT' && pc_stage_ok($u, $p, $p['stage']), 'stage' => $p['stage'], 'is_owner' => (int) $p['claimant_id'] === (int) $u['id']]);
    case 'pc_list': out(['rows' => pc_list($u, $in)]);
    case 'pc_act': out(pc_act($u, (int) $in['id'], $in));
    case 'pc_note': out(['id' => pc_add_note($u, (int) $in['id'], $in)]);
    case 'pc_report': out(pc_send_report($u, (int) $in['id']));
    case 'pc_setoran': pc_setoran($u, (int) $in['id'], $in); out([]);
    case 'pc_close': pc_close($u, (int) $in['id']); out([]);
    // ---- notifikasi
    case 'notif_list': out(['rows' => q("SELECT * FROM claim_notif WHERE user_id=? ORDER BY id DESC LIMIT 60", [$u['id']])]);
    case 'notif_read': ex("UPDATE claim_notif SET is_read=1 WHERE user_id=?" . (!empty($in['id']) ? " AND id=" . (int) $in['id'] : ''), [$u['id']]); out([]);
    // ---- monitoring dan laporan
    case 'monitor': out(monitor_budget($u, $in));
    case 'claimant_claims': out(claimant_claims($u, (int) $in['user_id'], $in['period'] ?? date('Y-m')));
    case 'todo_count': out(['n' => claim_list($u, ['view' => 'all', 'todo' => 1, 'limit' => 1])['total']]);
    case 'cal_day': out(cal_day($u, (string) ($in['date'] ?? '')));
    case 'cal': out(cal_month($u, (string) ($in['month'] ?? date('Y-m'))));
    case 'realisasi': out(realisasi($u, $in));
    case 'export':
      [$h, $rows] = export_claims($u, $in); $data = xlsx_build($h, $rows, 'Klaim');
      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="klaim_' . date('Ymd_His') . '.xlsx"'); echo $data; exit;
    case 'pdf': case 'ba_pdf': case 'pc_pdf':
      $id = (int) $in['id'];
      $html = $a === 'pdf' ? claim_pdf_html($u, $id) : ($a === 'ba_pdf' ? ba_pdf_html($u, $id) : pc_pdf_html($u, $id));
      hist($a === 'pc_pdf' ? 'pc' : 'claim', $id, ($a === 'ba_pdf' ? 'PDF BA' : 'PDF') . ' diunduh', $u, null, null, 'oleh ' . $u['name']);
      header('Content-Type: text/html; charset=utf-8'); echo $html; exit;
    // ---- admin
    case 'people_admin':
      need_role($u, 'ADMIN');
      $w = '1=1'; $p = []; if (!sa_free($u) && !has_role($u, 'SA') && $u['region']) { $w = 'region=?'; $p[] = $u['region']; }
      $rows = q("SELECT * FROM claim_users WHERE $w AND active=1 ORDER BY name", $p);
      foreach ($rows as &$r) { unset($r['pass_hash']); $r['ent'] = q("SELECT category, enabled, budget FROM claim_entitlement WHERE user_id=?", [$r['id']]); }
      out(['rows' => $rows]);
    case 'entitlement_save':
      $t = user_row((int) $in['user_id']); if (!$t) fail('Pengguna tidak ada.'); need_role($u, 'ADMIN'); if (!can_manage_user($u, $t)) fail('Di luar region Anda.', 403);
      if (same_person($u, ['claimant_id' => $t['id'], 'created_by' => $t['id']]) && !sa_free($u)) fail('Admin tidak mengatur hak klaimnya sendiri.', 403);
      $f = ['can_submit' => !empty($in['can_submit']) ? 1 : 0, 'can_petty' => !empty($in['can_petty']) ? 1 : 0, 'petty_ceiling' => max(0, (int) ($in['petty_ceiling'] ?? 0)),
        'meal_rate' => ($in['meal_rate'] ?? '') === '' ? null : max(0, (int) $in['meal_rate'])];
      foreach (['bank', 'bank_no', 'bank_name'] as $k) if (isset($in[$k])) $f[$k] = trim($in[$k]);
      upd('claim_users', $t['id'], $f);
      foreach (CATS as $cat) {
        $e = $in['cats'][$cat] ?? null; if (!$e) continue;
        if (val("SELECT 1 FROM claim_entitlement WHERE user_id=? AND category=?", [$t['id'], $cat])) ex("UPDATE claim_entitlement SET enabled=?, budget=? WHERE user_id=? AND category=?", [!empty($e['enabled']) ? 1 : 0, max(0, (int) $e['budget']), $t['id'], $cat]);
        else ins('claim_entitlement', ['user_id' => $t['id'], 'category' => $cat, 'enabled' => !empty($e['enabled']) ? 1 : 0, 'budget' => max(0, (int) $e['budget'])]);
      }
      hist('user', (int) $t['id'], 'Hak klaim diubah', $u, jenc(['tarif_makan' => $f['meal_rate'], 'plafon' => $f['petty_ceiling']]));
      out([]);
    case 'entitlement_copy': // salin hak klaim (yang tersimpan) dari satu orang ke banyak orang. Rekening tidak ikut.
      need_role($u, 'ADMIN'); $src = user_row((int) ($in['from_user_id'] ?? 0)); if (!$src) fail('Sumber tidak ada.'); if (!can_manage_user($u, $src)) fail('Di luar region Anda.', 403);
      $parts = $in['parts'] ?? []; $do = fn($k) => !empty($parts[$k]); if (!($do('submit') || $do('cats') || $do('meal') || $do('petty'))) fail('Pilih apa yang disalin.');
      $ids = array_values(array_unique(array_filter(array_map('intval', $in['to_ids'] ?? [])))); if (!$ids) fail('Pilih penerima.'); if (count($ids) > 300) fail('Maksimal 300 orang sekali salin.');
      $skipEx = !empty($in['skip_existing']); $dry = !empty($in['dry']);
      $sent = []; foreach (q("SELECT category, enabled, budget FROM claim_entitlement WHERE user_id=?", [$src['id']]) as $e) $sent[$e['category']] = $e;
      $new = 0; $over = 0; $skip = []; $samples = []; $done = 0;
      try { if (!$dry) db()->beginTransaction();
      foreach ($ids as $tid) {
        if ($tid === (int) $src['id']) continue;
        $t = user_row($tid); if (!$t || !$t['active']) { $skip[] = ['name' => $t['name'] ?? ('#' . $tid), 'why' => 'tidak aktif']; continue; }
        if (!can_manage_user($u, $t)) { $skip[] = ['name' => $t['name'], 'why' => 'di luar region Anda']; continue; }
        if (same_person($u, ['claimant_id' => $t['id'], 'created_by' => $t['id']]) && !sa_free($u)) { $skip[] = ['name' => $t['name'], 'why' => 'hak sendiri']; continue; }
        $cur = []; foreach (q("SELECT category, enabled, budget FROM claim_entitlement WHERE user_id=?", [$tid]) as $e) $cur[$e['category']] = $e;
        $has = (bool) $cur; if ($has && $skipEx) { $skip[] = ['name' => $t['name'], 'why' => 'sudah punya hak']; continue; }
        $has ? $over++ : $new++;
        if ($dry) {
          if ($has && count($samples) < 3) { $ch = [];
            if ($do('cats')) foreach (CATS as $c) { $a = (int) ($cur[$c]['budget'] ?? 0); $b = (int) ($sent[$c]['budget'] ?? 0); if ($a !== $b) $ch[] = $c . ' ' . number_format($a, 0, ',', '.') . ' → ' . number_format($b, 0, ',', '.'); }
            if ($do('meal') && (int) $t['meal_rate'] !== (int) $src['meal_rate']) $ch[] = 'Tarif makan ' . number_format((int) $t['meal_rate'], 0, ',', '.') . ' → ' . number_format((int) $src['meal_rate'], 0, ',', '.');
            if ($do('submit') && (int) $t['can_submit'] !== (int) $src['can_submit']) $ch[] = 'Boleh klaim ' . ($t['can_submit'] ? 'ya' : 'tidak') . ' → ' . ($src['can_submit'] ? 'ya' : 'tidak');
            if ($ch) $samples[] = ['name' => $t['name'], 'changes' => array_slice($ch, 0, 3)]; }
          continue;
        }
        $f = [];
        if ($do('submit')) $f['can_submit'] = (int) $src['can_submit'];
        if ($do('meal')) $f['meal_rate'] = $src['meal_rate'] === null ? null : (int) $src['meal_rate'];
        if ($do('petty')) { $f['can_petty'] = (int) $src['can_petty']; $f['petty_ceiling'] = (int) $src['petty_ceiling']; }
        if ($f) upd('claim_users', $tid, $f);
        if ($do('cats')) foreach (CATS as $c) {
          $en = (int) ($sent[$c]['enabled'] ?? 0); $bu = (int) ($sent[$c]['budget'] ?? 0);
          if (isset($cur[$c])) ex("UPDATE claim_entitlement SET enabled=?, budget=? WHERE user_id=? AND category=?", [$en, $bu, $tid, $c]); else ins('claim_entitlement', ['user_id' => $tid, 'category' => $c, 'enabled' => $en, 'budget' => $bu]);
        }
        hist('user', $tid, 'Hak klaim disalin dari ' . $src['name'], $u, implode(', ', array_keys(array_filter(['akses' => $do('submit'), 'budget' => $do('cats'), 'tarif makan' => $do('meal'), 'petty cash' => $do('petty')]))));
        $done++;
      }
      if (!$dry) db()->commit(); } catch (Throwable $e) { if (!$dry && db()->inTransaction()) db()->rollBack(); throw $e; }
      out(['dry' => $dry, 'new' => $new, 'over' => $over, 'skipped' => $skip, 'samples' => $samples, 'done' => $done]);
    case 'type_save':
      need_role($u, 'ADMIN'); $id = (int) ($in['id'] ?? 0); $cat = $in['category'] ?? '';
      if ($id) { $t = type_row($id); if ($t['locked']) fail('Jenis ini terkunci.'); $f = ['name' => trim($in['name'] ?? $t['name']), 'active' => isset($in['active']) ? ($in['active'] ? 1 : 0) : $t['active']]; if (!$f['name']) fail('Nama wajib.'); upd('claim_types', $id, $f); }
      else { if (!in_array($cat, ['Operasional', 'Transportasi', 'Entertain'], true)) fail('Kategori tidak valid.'); if (!trim($in['name'] ?? '')) fail('Nama wajib.'); if (strcasecmp(trim($in['name']), 'Lainnya') === 0 && $cat !== 'Operasional') fail('Jenis "Lainnya" tidak tersedia.'); $id = ins('claim_types', ['category' => $cat, 'name' => trim($in['name']), 'active' => 1, 'locked' => 0, 'sort_no' => 50]); }
      out(['id' => $id]);
    case 'types_all': need_role($u, 'ADMIN'); out(['rows' => q("SELECT * FROM claim_types WHERE name<>'Reimburse petty cash' ORDER BY sort_no")]);
    case 'rules_get': need_role($u, 'ADMIN', 'AUDIT', 'FM', 'HOS'); out(['rule' => current_rule(), 'history' => q("SELECT * FROM claim_rules ORDER BY effective_from DESC, id DESC LIMIT 20"), 'aging_days' => aging_days(), 'is_sa' => has_role($u, 'SA') || sa_free($u)]);
    case 'rules_save':
      need_role($u, 'ADMIN'); $f = ['effective_from' => $in['effective_from'] ?: date('Y-m-d'), 'created_by' => $u['id'], 'created_at' => now()];
      foreach (['bbm_round', 'pc_grace_days', 'min_km_makan'] as $k) $f[$k] = ($in[$k] ?? '') === '' ? null : max(0, (int) $in[$k]);
      if (isset($f['bbm_round']) && $f['bbm_round'] > 9999) fail('Aturan digit akhir BBM berupa angka ribuan di bawah 10.000, mis. 2000 atau 5000.', 422);
      $dj = []; foreach (is_array($in['days'] ?? null) ? $in['days'] : [] as $cat => $ds) if (in_array($cat, CATS, true) && is_array($ds)) $dj[$cat] = array_values(array_unique(array_filter(array_map('intval', $ds), fn($d) => $d >= 1 && $d <= 7)));
      $f['days'] = $dj ? json_encode($dj) : null;
      ins('claim_rules', $f); hist('user', (int) $u['id'], 'Aturan BA diperbarui (versi baru)', $u, jenc($f)); out([]);
    case 'aging_save': if (!has_role($u, 'SA') && !sa_free($u)) fail('Hanya Super Admin.', 403); set_setting('aging_days', ($in['aging_days'] ?? '') === '' ? null : (string) max(1, (int) $in['aging_days'])); out([]);
    // ---- super admin
    case 'entity_list': { $used = []; foreach (q("SELECT DISTINCT entity FROM claim_users WHERE entity IS NOT NULL AND entity<>''") as $r) foreach (explode(',', $r['entity']) as $e) $used[trim($e)] = 1;
      foreach (q("SELECT DISTINCT entity FROM claims WHERE entity IS NOT NULL") as $r) $used[$r['entity']] = 1;
      out(['rows' => array_map(fn($e) => ['name' => $e, 'used' => isset($used[$e])], entity_options())]); }
    case 'entity_add': need_role($u, 'SA'); $n = strtoupper(trim($in['name'] ?? ''));
      if (!preg_match('/^[A-Z0-9 .&-]{2,30}$/', $n)) fail('Nama badan usaha 2-30 karakter (huruf, angka, spasi, titik, &, -).');
      if (in_array($n, entity_options(), true)) fail('Badan usaha itu sudah ada.'); ins('claim_lookup', ['kind' => 'entity', 'val' => $n]);
      hist('user', (int) $u['id'], 'Badan usaha ditambahkan: ' . $n, $u); out(['ok' => 1]);
    case 'entity_del': need_role($u, 'SA'); $n = trim($in['name'] ?? '');
      foreach (q("SELECT entity FROM claim_users WHERE entity IS NOT NULL") as $r) if (in_array($n, array_map('trim', explode(',', $r['entity'])), true)) fail('Masih dipakai pengguna. Ubah dulu di Pengguna & peran.');
      if (val("SELECT 1 FROM claims WHERE entity=?", [$n])) fail('Sudah dipakai klaim, tidak bisa dihapus.');
      ex("DELETE FROM claim_lookup WHERE kind='entity' AND val=?", [$n]); out(['ok' => 1]);
    case 'fuel_list': out(['rows' => q("SELECT f.*, u.name AS uname FROM claim_fuel_price f LEFT JOIN claim_users u ON u.id=f.created_by ORDER BY f.effective_from DESC, f.id DESC"), 'today' => date('Y-m-d')]);
    case 'fuel_save':
      need_role($u, 'SA'); $ef = $in['effective_from'] ?? ''; $pl = (int) ($in['pertalite'] ?? 0); $px = (int) ($in['pertamax'] ?? 0);
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ef)) fail('Tanggal berlaku wajib diisi.'); if ($pl <= 0 || $px <= 0) fail('Harga Pertalite dan Pertamax wajib diisi (lebih dari 0).');
      if ($px < $pl) fail('Harga Pertamax tidak boleh lebih rendah dari Pertalite.');
      if (val("SELECT 1 FROM claim_fuel_price WHERE effective_from=?", [$ef])) fail('Sudah ada harga untuk tanggal berlaku itu. Gunakan tanggal lain.');
      $id = ins('claim_fuel_price', ['effective_from' => $ef, 'pertalite' => $pl, 'pertamax' => $px, 'created_by' => $u['id'], 'created_at' => now()]);
      hist('user', (int) $u['id'], 'Harga BBM diperbarui', $u, null, null, "berlaku $ef · Pertalite " . rp($pl) . ' · Pertamax ' . rp($px)); out(['id' => $id]);
    case 'sa_users': need_role($u, 'SA'); $r = q("SELECT id,username,name,nik,job,region,entity,roles,can_submit,active,wa FROM claim_users ORDER BY id"); out(['rows' => $r]);
    case 'dsm_people': need_role($u, 'SA'); if (!dsm_present()) out(['rows' => []]);
      try { require_once __DIR__ . '/../lib/auth.php'; $pp = snapshot_()['meta']['people'] ?? []; } catch (Throwable $e) { $pp = []; }
      $have = array_flip(array_map('strval', array_column(q("SELECT nik FROM claim_users WHERE nik IS NOT NULL"), 'nik'))); $rows = [];
      foreach ($pp as $x) { $nk = (string) ($x['nik'] ?? ''); if ($nk === '' || str_starts_with($nk, 'UJI-') || isset($rows[$nk])) continue;
        $rows[$nk] = ['nik' => $nk, 'nama' => (string) ($x['nama'] ?? $nk), 'role' => (string) ($x['role'] ?? ''), 'region' => (string) ($x['region'] ?? ''), 'disabled' => isset($have[$nk]), 'note' => isset($have[$nk]) ? 'Sudah punya akses Klaim' : ''];
      }
      usort($rows, fn($a, $b) => strcasecmp($a['nama'], $b['nama'])); out(['rows' => array_values($rows)]);
    case 'sa_user_save':
      need_role($u, 'SA'); $id = (int) ($in['id'] ?? 0); $roles = implode(',', array_values(array_intersect(['ADMIN', 'AUDIT', 'HOS', 'FM', 'FT', 'RGM', 'ASM', 'SALES', 'SA'], $in['roles'] ?? [])));
      $f = ['name' => trim($in['name'] ?? ''), 'nik' => trim($in['nik'] ?? '') ?: null, 'job' => trim($in['job'] ?? ''), 'region' => ($in['region'] ?? '') ?: null, 'entity' => ($in['entity'] ?? '') ?: null, 'roles' => $roles, 'can_submit' => !empty($in['can_submit']) ? 1 : 0, 'active' => isset($in['active']) ? ($in['active'] ? 1 : 0) : 1];
      if (array_key_exists('wa', $in)) $f['wa'] = norm_wa($in['wa']);
      if (dsm_present() && !$id) { $nk = trim($in['nik'] ?? ''); $per = $nk !== '' ? dsm_person($nk) : null; if (!$per) fail('NIK tidak ditemukan di DSM. Tambahkan dulu akunnya di DSM.');
        if (val("SELECT 1 FROM claim_users WHERE nik=?", [$nk])) fail('NIK itu sudah punya akses Klaim.'); $f['nik'] = $nk; $f['name'] = $f['name'] ?: ($per['nama'] ?? ''); $f['job'] = $f['job'] ?: ucfirst(strtolower((string) ($per['role'] ?? ''))); $f['region'] = $f['region'] ?: ($per['region'] ?? null);
        $in['username'] = strtolower(preg_replace('/[^a-z0-9_.]/i', '', $nk)) ?: 'u' . substr(md5($nk), 0, 6); $in['password'] = bin2hex(random_bytes(8)); }
      if (!$f['name']) fail('Nama wajib.');
      if ($id) upd('claim_users', $id, $f);
      else { if (!preg_match('/^[a-z0-9_.]{3,30}$/', $in['username'] ?? '')) fail('Username 3-30 huruf kecil/angka.'); $id = ins('claim_users', $f + ['username' => $in['username'], 'pass_hash' => password_hash($in['password'] ?: '123456', PASSWORD_DEFAULT), 'created_at' => now()]); }
      hist('user', $id, 'Peran/akses pengguna diubah', $u, $roles . ($f['can_submit'] ? ' +pemohon' : '')); out(['id' => $id]);
    case 'sa_seed_uji': need_role($u, 'SA'); if (!test_mode()) fail('Aktifkan Mode tes dulu.'); $n = seed_uji_users(); out(['dibuat' => $n]);
    case 'sa_uji_off': need_role($u, 'SA'); $n = ex("UPDATE claim_users SET active=0 WHERE nik LIKE 'UJI-%'"); out(['nonaktif' => $n]);
    case 'sa_testmode': need_role($u, 'SA'); set_setting('test_mode', !empty($in['on']) ? '1' : '0'); out([]);
    case 'sa_reset': if (dsm_present()) fail('Reset dinonaktifkan di DSM.', 403); if (!real_is_sa($u)) fail('Hanya Super Admin.', 403); install_schema(); seed_all(); $_SESSION = []; out(['reset' => true]);
    case 'backup_issue':
      $role = $in['role'] ?? ''; if (!in_array($role, ['ADMIN', 'FT'], true)) fail('Kode cadangan hanya untuk Admin atau Finance Transfer.');
      $for = (int) ($in['for_user_id'] ?? 0); $fu = user_row($for);
      if (!$fu || !has_role($fu, $role)) fail('Pemegang peran tidak ditemukan.');
      $to = (int) ($in['to_user_id'] ?? 0); $tu = $to ? user_row($to) : null;
      if (!$tu || !$tu['active']) fail('Pilih orang yang akan diberi kode.');
      if ($to === $for) fail('Penerima kode tidak boleh orang yang digantikan.');
      if (!has_role($tu, $role)) fail('Penerima harus pemegang peran ' . ($role === 'FT' ? 'Finance Transfer' : 'Admin') . ' juga.');
      $hours = (int) ($in['duration_hours'] ?? 0); if (!in_array($hours, [2, 4, 8, 24, 72], true)) fail('Pilih durasi akses.');
      if (!has_role($u, 'SA') && !sa_free($u) && (int) $u['id'] !== $for) fail('Kode hanya dapat diterbitkan Super Admin atau orang yang digantikan.', 403);
      $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
      ins('claim_backup', ['role' => $role, 'code_hash' => password_hash($code, PASSWORD_DEFAULT), 'issuer_id' => $u['id'], 'for_user_id' => $for, 'to_user_id' => $to, 'duration_min' => $hours * 60, 'use_count' => 0, 'max_use' => 5, 'expires_at' => date('Y-m-d H:i:s', time() + 86400), 'created_at' => now()]);
      hist('user', $for, 'Kode cadangan ' . $role . ' untuk ' . $tu['name'] . ' (' . $hours . ' jam) diterbitkan oleh ' . $u['name'], $u);
      notify($to, 'info', 'Anda diberi kode cadangan ' . ($role === 'FT' ? 'Finance Transfer' : 'Admin') . ' menggantikan ' . $fu['name'] . ' (' . $hours . ' jam). Kode dikirim terpisah oleh pemberi. Pakai lewat menu Masuk kode cadangan dalam 24 jam.');
      out(['code' => $code, 'valid_hours' => 24, 'duration_hours' => $hours, 'to' => $tu['name']]);
    case 'backup_use':
      $code = strtoupper(trim($in['code'] ?? ''));
      foreach (q("SELECT * FROM claim_backup WHERE revoked_at IS NULL AND (used_at IS NULL AND expires_at>? OR active_until>?) ORDER BY id DESC", [now(), now()]) as $b) if (password_verify($code, $b['code_hash'])) {
        if ((int) $b['for_user_id'] === (int) $u['id']) fail('Kode ini untuk menggantikan Anda, bukan untuk Anda sendiri.');
        if (!empty($b['to_user_id']) && (int) $b['to_user_id'] !== (int) $u['id']) fail('Kode ini diberikan untuk orang lain.');
        if ($b['used_by'] && (int) $b['used_by'] !== (int) $u['id']) fail('Kode ini sudah dipakai orang lain.');
        $max = (int) ($b['max_use'] ?: 5); $n = (int) $b['use_count'];
        if ($n >= $max) fail("Jatah masuk habis ($max kali). Minta kode baru.");
        if (!$b['used_by']) { $until = date('Y-m-d H:i:s', time() + ((int) ($b['duration_min'] ?: 120)) * 60); ex("UPDATE claim_backup SET used_by=?, used_at=?, active_until=?, use_count=1, last_used_at=? WHERE id=?", [$u['id'], now(), $until, now(), $b['id']]); }
        else ex("UPDATE claim_backup SET use_count=use_count+1, last_used_at=? WHERE id=?", [now(), $b['id']]);
        $_SESSION['backup'] = ['id' => (int) $b['id']];
        hist('user', (int) $u['id'], 'Akses cadangan ' . $b['role'] . ' dipakai (masuk ke-' . ($n + 1) . ' dari ' . $max . ')', $u); out(['me' => me()]);
      }
      fail('Kode tidak valid, kedaluwarsa, atau sudah dicabut.');
    case 'backup_exit':
      if (!empty($_SESSION['backup'])) { hist('user', (int) $u['id'], 'Keluar dari akses cadangan', $u); unset($_SESSION['backup']); }
      out(['me' => me()]);
    case 'backup_list':
      $sa = has_role($u, 'SA') || sa_free($u);
      $rows = q("SELECT b.*, f.name AS for_name, t.name AS to_name, i.name AS issuer_name FROM claim_backup b LEFT JOIN claim_users f ON f.id=b.for_user_id LEFT JOIN claim_users t ON t.id=b.to_user_id LEFT JOIN claim_users i ON i.id=b.issuer_id" . ($sa ? '' : " WHERE b.issuer_id=" . (int) $u['id']) . " ORDER BY b.id DESC LIMIT 60");
      foreach ($rows as &$r) {
        $r['code_hash'] = null; $now = now();
        $r['status'] = $r['revoked_at'] ? 'DICABUT' : ($r['used_at'] ? ($r['active_until'] > $now ? 'AKTIF' : 'SELESAI') : ($r['expires_at'] > $now ? 'BELUM' : 'KEDALUWARSA'));
        $r['left'] = $r['status'] === 'AKTIF' ? max(0, strtotime($r['active_until']) - time()) : 0;
      } unset($r);
      out(['rows' => $rows]);
    case 'backup_revoke':
      $b = one("SELECT * FROM claim_backup WHERE id=?", [(int) ($in['id'] ?? 0)]); if (!$b) fail('Kode tidak ditemukan.', 404);
      if (!has_role($u, 'SA') && !sa_free($u) && (int) $b['issuer_id'] !== (int) $u['id']) fail('Hanya Super Admin atau pemberi kode yang bisa mencabut.', 403);
      if ($b['revoked_at']) fail('Kode sudah dicabut.');
      ex("UPDATE claim_backup SET revoked_at=?, revoked_by=? WHERE id=?", [now(), $u['id'], $b['id']]);
      hist('user', (int) $b['for_user_id'], 'Kode cadangan #' . $b['id'] . ' dicabut oleh ' . $u['name'], $u); out([]);
    case 'backup_people': // calon penerima: pemegang peran yang sama dengan yang digantikan
      $role = $in['role'] ?? ''; $ex = (int) ($in['except'] ?? 0);
      out(['rows' => array_values(array_filter(q("SELECT id, name, job, region, roles FROM claim_users WHERE active=1" . (test_mode() ? '' : " AND nik NOT LIKE 'UJI-%'") . " ORDER BY name"), fn($r) => (int) $r['id'] !== $ex && in_array($role, explode(',', (string) $r['roles']), true)))]);
    case 'backup_holders': out(['rows' => q("SELECT id, name, roles FROM claim_users WHERE (roles LIKE '%ADMIN%' OR roles LIKE '%FT%') AND active=1")]);
    case 'history_user': need_role($u, 'SA', 'ADMIN'); out(['rows' => q("SELECT * FROM claim_history WHERE ref_type='user' ORDER BY id DESC LIMIT 100")]);
    default: fail('Aksi tidak dikenal: ' . $a, 404);
  }
} catch (Err $e) {
  out_err($e->getMessage(), $e->getCode() ?: 400);
} catch (Throwable $e) {
  error_log('[claim] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  out_err('Terjadi kesalahan pada server: ' . $e->getMessage(), 500);
}
function out_err(string $m, int $code): never { if ($code < 100 || $code > 599) $code = 400; http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE); exit; }
