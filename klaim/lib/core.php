<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bridge.php';

const CATS = ['Transportasi', 'Entertain', 'Perdin', 'Operasional'];
const STAGE_NAME = ['RGM' => 'RGM', 'ADMIN' => 'Admin', 'AUDIT' => 'Audit', 'HOS' => 'HOS', 'FM' => 'Finance Manager', 'FT' => 'Finance Transfer'];
const ROLE_NAME = ['SA' => 'Super Admin', 'ADMIN' => 'Admin', 'AUDIT' => 'Audit', 'HOS' => 'HOS', 'FM' => 'Finance Manager', 'FT' => 'Finance Transfer', 'RGM' => 'RGM'];

class Err extends Exception {}
function fail(string $m, int $code = 400): never { throw new Err($m, $code); }

/* ---------- sesi dan pengguna ---------- */
function sess(): void { if (session_status() !== PHP_SESSION_ACTIVE) { @session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']); @session_start(); } }
function user_row(int $id): ?array { $u = one("SELECT * FROM claim_users WHERE id=?", [$id]); return $u ? shape_user($u) : null; }
function shape_user(array $u): array {
  $u['roles'] = array_values(array_filter(explode(',', $u['roles'] ?? '')));
  $u['entities'] = array_values(array_filter(array_map('trim', explode(',', $u['entity'] ?? ''))));
  $u['area_list'] = array_values(array_filter(array_map('trim', explode(',', $u['areas'] ?? ''))));
  unset($u['pass_hash']);
  $u['has_sign'] = !empty($u['sign_img']); unset($u['sign_img']);
  return $u;
}
/** Pengguna efektif: termasuk penyamaran Super Admin dan akses cadangan. */
/** Apakah wilayah tugas Admin/RGM ini mencakup region klaim? Daftar wilayah (areas) menang; kosong = pakai region tunggal; keduanya kosong = semua. */
function covers_region(array $u, ?string $region): bool {
  $a = $u['area_list'] ?? [];
  if ($a) return !$region || in_array($region, $a, true);
  return !$u['region'] || !$region || $u['region'] === $region;
}
function me(): ?array {
  sess();
  if (dsm_present() && !dsm_bind_session()) return null;
  $uid = $_SESSION['uid'] ?? null;
  if (!$uid) return null;
  $u = user_row((int) $uid);
  if (!$u || !$u['active']) return null;
  $u['real_id'] = (int) ($_SESSION['real_uid'] ?? $uid);
  $u['impersonating'] = $u['real_id'] !== (int) $uid;
  if (!empty($_SESSION['backup']['id'])) {
    $b = backup_grant((int) $_SESSION['backup']['id'], (int) $uid);
    if ($b) {
      $u['roles'][] = $b['role'];
      $as = $b['for_user_id'] ? user_row((int) $b['for_user_id']) : null;
      if ($as) { $u['region'] = $as['region']; $u['area_list'] = $as['area_list']; $u['entities'] = $as['entities']; $u['entity'] = $as['entity']; $u['backup_for'] = $as['name']; }
      $u['backup_role'] = $b['role']; $u['backup_left'] = max(0, strtotime($b['active_until']) - time());
      $u['backup_uses'] = (int) $b['use_count']; $u['backup_max'] = (int) ($b['max_use'] ?: 5);
    } else unset($_SESSION['backup']);
  }
  return $u;
}
/** Izin akses cadangan yang masih berlaku untuk pengguna ini (belum dicabut, durasi belum habis). */
function backup_grant(int $id, int $uid): ?array {
  $b = one("SELECT * FROM claim_backup WHERE id=? AND used_by=? AND revoked_at IS NULL AND active_until>?", [$id, $uid, now()]);
  return $b ?: null;
}
function need_me(): array { $u = me(); if (!$u) fail('Sesi habis, silakan masuk lagi.', 401); return $u; }
function has_role(array $u, string $r): bool { return in_array($r, $u['roles'], true); }
function is_sa(array $u): bool { return has_role($u, 'SA'); }
function real_is_sa(array $u): bool { static $c = []; $id = $u['real_id']; return $c[$id] ??= (bool) preg_match('/\bSA\b/', (string) val("SELECT roles FROM claim_users WHERE id=?", [$id]) ?: ''); }
function test_mode(): bool { return setting('test_mode', dsm_present() ? '0' : '1') === '1'; } // di DSM (produksi) bawaannya MATI
function sa_free(array $u): bool { return is_sa($u) && test_mode(); }
function setting(string $k, ?string $d = null): ?string { $v = val("SELECT sval FROM claim_settings WHERE skey=?", [$k]); return $v ?? $d; }
function set_setting(string $k, ?string $v): void { if (val("SELECT 1 FROM claim_settings WHERE skey=?", [$k])) ex("UPDATE claim_settings SET sval=? WHERE skey=?", [$v, $k]); else ex("INSERT INTO claim_settings (skey,sval) VALUES (?,?)", [$k, $v]); }
function is_claimant(array $u): bool { return (bool) $u['can_submit']; }

/* ---------- aturan dan versi aturan ---------- */
function current_rule(?string $on = null): array {
  $r = one("SELECT * FROM claim_rules WHERE effective_from<=? ORDER BY effective_from DESC, id DESC", [$on ?? date('Y-m-d')]);
  return $r ?: ['id' => 0, 'nota_max_days' => null, 'max_cost_km' => null, 'max_ent_pax' => null, 'km_diff_pct' => null, 'bbm_round' => null, 'pc_grace_days' => null, 'min_km_mobil' => null, 'min_km_motor' => null, 'min_km_makan' => null, 'days' => null];
}
function aging_days(): ?int { $v = setting('aging_days'); return $v === null || $v === '' ? null : (int) $v; }

/* ---------- riwayat, notifikasi ---------- */
function acting_role(array $u, ?string $stage = null): string { if ($stage && has_role($u, $stage)) return $stage; foreach (['SA', 'ADMIN', 'AUDIT', 'HOS', 'FM', 'FT', 'RGM'] as $r) if (has_role($u, $r)) return $r; return 'PEMOHON'; }
function hist(string $rt, int $rid, string $action, ?array $u, ?string $notes = null, ?string $before = null, ?string $after = null, ?string $role = null): void {
  $name = $u ? $u['name'] . (!empty($u['impersonating']) ? ' (via Super Admin)' : '') : 'Sistem';
  ins('claim_history', ['ref_type' => $rt, 'ref_id' => $rid, 'actor_id' => $u['id'] ?? null, 'actor_name' => $name, 'role' => $role ?? ($u ? acting_role($u) : 'SISTEM'),
    'action' => $action, 'notes' => $notes, 'before_val' => $before, 'after_val' => $after, 'created_at' => now()]);
}
function notify(int $uid, string $kind, string $text, ?string $rt = null, ?int $rid = null): void {
  ins('claim_notif', ['user_id' => $uid, 'kind' => $kind, 'text' => $text, 'ref_type' => $rt, 'ref_id' => $rid, 'is_read' => 0, 'created_at' => now()]);
}
/** Normalisasi nomor WA Indonesia ke format 62xxxx. Kosong = null, salah format = gagal. */
function norm_wa(?string $v): ?string {
  $d = preg_replace('/\D/', '', (string) $v); if ($d === '') return null;
  if (str_starts_with($d, '0')) $d = '62' . substr($d, 1); elseif (str_starts_with($d, '8')) $d = '62' . $d;
  if (!preg_match('/^62\d{8,13}$/', $d)) fail('Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.', 422);
  return $d;
}
/** Badan usaha yang tidak dipegang satu pun user aktif dengan peran ini (AUDIT/FM/FT). Mereka jatuh ke semua pemegang peran itu supaya klaim tidak nyangkut.
 *  Kalau ada user peran itu tanpa batasan badan usaha, semuanya sudah tercakup (hasil kosong). */
function uncovered_entities(string $role): array {
  $covered = []; $any = false; $users = 0;
  foreach (q("SELECT * FROM claim_users WHERE active=1") as $r) { $u = shape_user($r); if (!has_role($u, $role)) continue; $users++; if (!$u['entities']) $any = true; else foreach ($u['entities'] as $e) $covered[$e] = 1; }
  if ($any || !$users) return [];
  return array_values(array_diff(entity_options(), array_keys($covered)));
}
function stage_users(string $stage, ?string $region, ?string $entity): array {
  $out = [];
  foreach (q("SELECT * FROM claim_users WHERE active=1") as $r) {
    $u = shape_user($r);
    if (!has_role($u, $stage)) continue;
    if (in_array($stage, ['ADMIN', 'RGM'], true) && !covers_region($u, $region)) continue;
    if (in_array($stage, ['AUDIT', 'FM', 'FT'], true) && $u['entities'] && $entity && !in_array($entity, $u['entities'], true) && !in_array($entity, uncovered_entities($stage), true)) continue;
    $out[] = $u;
  }
  return $out;
}
function notify_stage(string $stage, array $claim, string $text, string $rt = 'claim'): void {
  foreach (stage_users($stage, $claim['region'] ?? null, $claim['entity'] ?? null) as $u)
    if ($u['id'] != ($claim['claimant_id'] ?? 0)) notify($u['id'], 'tugas', $text, $rt, (int) $claim['id']);
}

/* ---------- nomor ---------- */
function next_number(string $prefix, ?string $entity): string {
  $ym = date('ym'); $key = $entity === '' ? "$prefix-$ym" : $prefix . '-' . ($entity ?: 'XX') . '-' . $ym; // entity '' = nomor tanpa kode badan usaha
  if (!val("SELECT 1 FROM claim_seq WHERE skey=?", [$key])) ex("INSERT INTO claim_seq (skey,n) VALUES (?,0)", [$key]);
  ex("UPDATE claim_seq SET n=n+1 WHERE skey=?", [$key]);
  return "$key-" . str_pad((string) val("SELECT n FROM claim_seq WHERE skey=?", [$key]), 4, '0', STR_PAD_LEFT);
}

/* ---------- cakupan ---------- */
function in_scope(array $u, array $c, string $role): bool {
  if (sa_free($u) || has_role($u, 'SA')) return true;
  return match ($role) {
    'ADMIN', 'RGM' => ($u['area_list'] || $u['region']) ? (($c['region'] ?? null) !== null && covers_region($u, $c['region'])) : true,
    'AUDIT', 'FM', 'FT' => !$u['entities'] || in_array($c['entity'] ?? '', $u['entities'], true) || in_array($c['entity'] ?? '', uncovered_entities($role), true),
    'HOS' => true,
    default => false,
  };
}
function can_view(array $u, array $c): bool {
  if ((int) $c['claimant_id'] === (int) $u['id'] || (int) ($c['created_by'] ?? 0) === (int) $u['id']) return true;
  foreach (['SA', 'ADMIN', 'AUDIT', 'HOS', 'FM', 'FT', 'RGM'] as $r) if (has_role($u, $r) && in_scope($u, $c, $r)) return true;
  return false;
}
function same_person(array $u, array $c): bool { return (int) $c['claimant_id'] === (int) $u['id'] || (int) ($c['created_by'] ?? 0) === (int) $u['id']; }

/* ---------- budget (dihitung langsung dari klaim; reservasi penuh sejak submit) ---------- */
function budget_of(int $uid, string $cat): int { return (int) val("SELECT budget FROM claim_entitlement WHERE user_id=? AND category=? AND enabled=1", [$uid, $cat]); }
/** Perdin dan Operasional tidak punya budget bulanan: tidak pernah over budget, tidak memicu BA budget. */
function no_budget_cat(string $cat): bool { return in_array($cat, ['Perdin', 'Operasional'], true); }
function claim_budget(array $c, string $period, ?int $excludeClaim = null): ?array { return ($c['outside_sales'] || no_budget_cat((string) $c['category'])) ? null : budget_info((int) $c['claimant_id'], $c['category'], $period, $excludeClaim); }
function budget_info(int $uid, string $cat, string $period, ?int $excludeClaim = null): array {
  $b = no_budget_cat($cat) ? 0 : budget_of($uid, $cat); $ex = $excludeClaim ?: 0;
  $real = (int) val("SELECT COALESCE(SUM(total),0) FROM claims WHERE claimant_id=? AND category=? AND budget_period=? AND status='DONE' AND outside_sales=0 AND id<>?", [$uid, $cat, $period, $ex]);
  $res = (int) val("SELECT COALESCE(SUM(total),0) FROM claims WHERE claimant_id=? AND category=? AND budget_period=? AND status IN ('WAIT','REV') AND outside_sales=0 AND id<>?", [$uid, $cat, $period, $ex]);
  if (no_budget_cat($cat)) return ['budget' => 0, 'realized' => $real, 'reserved' => $res, 'available' => 0, 'over' => 0, 'none' => true];
  $avail = $b - $real - $res;
  return ['budget' => $b, 'realized' => $real, 'reserved' => $res, 'available' => $avail, 'over' => max(0, -$avail)];
}
function budget_all(int $uid, string $period): array {
  $o = []; foreach (CATS as $c) { $o[$c] = budget_info($uid, $c, $period); $o[$c]['enabled'] = (bool) val("SELECT enabled FROM claim_entitlement WHERE user_id=? AND category=?", [$uid, $c]); }
  return $o;
}

/* ---------- berkas ---------- */
function upload_dir(): string { $d = __DIR__ . '/../storage/uploads'; if (!is_dir($d)) mkdir($d, 0775, true); return $d; }
function save_upload(array $u, array $f, string $slot): array {
  $c = cfg();
  if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) fail('Upload gagal, coba lagi.');
  if ($f['size'] > $c['upload_max_mb'] * 1048576) fail('Ukuran file maksimal ' . $c['upload_max_mb'] . ' MB.');
  $mime = function_exists('mime_content_type') ? mime_content_type($f['tmp_name']) : ($f['type'] ?? '');
  if (!in_array($mime, $c['upload_types'], true)) fail('Jenis file tidak diizinkan (hanya foto atau PDF).');
  $sha = hash_file('sha256', $f['tmp_name']);
  $stored = bin2hex(random_bytes(16)) . ($mime === 'application/pdf' ? '.pdf' : '.bin');
  if (!move_uploaded_file($f['tmp_name'], upload_dir() . '/' . $stored)) { if (!@rename($f['tmp_name'], upload_dir() . '/' . $stored)) fail('Gagal menyimpan file.'); }
  $id = ins('claim_files', ['uploader' => $u['id'], 'stored_name' => $stored, 'orig' => substr($f['name'] ?? 'file', 0, 150), 'mime' => $mime, 'size_b' => (int) $f['size'], 'sha' => $sha, 'slot' => $slot, 'created_at' => now()]);
  return ['id' => $id, 'orig' => $f['name'] ?? '', 'mime' => $mime, 'sha' => $sha];
}
function link_files(array $ids, string $rt, int $rid, ?int $noteId, string $slot): void {
  foreach ($ids as $fid) ex("UPDATE claim_files SET ref_type=?, ref_id=?, note_id=?, slot=? WHERE id=?", [$rt, $rid, $noteId, $slot, (int) $fid]);
}
function files_of(string $rt, int $rid, ?string $slot = null, ?int $noteId = null): array {
  $sql = "SELECT id, orig, mime, slot, note_id FROM claim_files WHERE ref_type=? AND ref_id=?"; $p = [$rt, $rid];
  if ($slot) { $sql .= " AND slot=?"; $p[] = $slot; }
  if ($noteId !== null) { $sql .= " AND note_id=?"; $p[] = $noteId; }
  return q($sql . " ORDER BY id", $p);
}

/** Daftar badan usaha yang dikenal (dari data pengguna; kolom entity boleh berisi beberapa, dipisah koma). */
/** Daftar brand: di DSM diambil dari master brand DSM (+ "Lainnya"); standalone dari tabel lookup. */
function brand_options(): array {
  $own = array_column(q("SELECT val FROM claim_lookup WHERE kind='brand' ORDER BY val"), 'val');
  if (!dsm_present()) return $own;
  $d = [];
  try { require_once __DIR__ . '/../../lib/auth.php'; require_once __DIR__ . '/../../lib/dealer_brand.php'; $d = dbaPrimaryBrands_(); } catch (Throwable $e) { $d = []; }
  if (!$d) return $own;
  $d = array_values(array_filter($d, fn($b) => strcasecmp($b, 'LAINNYA') !== 0)); $d[] = 'Lainnya'; return $d;
}
function entity_options(): array {
  $o = []; foreach (q("SELECT val AS entity FROM claim_lookup WHERE kind='entity'") as $r) $o[trim($r['entity'])] = 1;
  foreach (q("SELECT DISTINCT entity FROM claim_users WHERE entity IS NOT NULL AND entity<>''") as $r) foreach (explode(',', $r['entity']) as $e) { $e = trim($e); if ($e !== '') $o[$e] = 1; }
  $k = array_keys($o); sort($k); return $k;
}
