<?php
/** Jembatan ke DSM. Login, 2FA, kunci akun, dan batas sesi sepenuhnya milik DSM; Klaim hanya memakai tokennya. */
function dsm_auth(): ?array {
  static $memo = [];
  $t = (string) ($_SERVER['HTTP_X_DSM_TOKEN'] ?? '');
  // Tautan unduh/gambar (GET, tanpa header) memakai token yang tersimpan di sesi, hanya untuk aksi baca-saja, dan tetap divalidasi ulang ke DSM.
  if ($t === '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && in_array($_GET['a'] ?? '', ['file', 'pdf', 'ba_pdf', 'pc_pdf', 'export', 'contact_template'], true)) { sess(); $t = (string) ($_SESSION['dsm_tok'] ?? ''); }
  if ($t === '') return null;
  if (array_key_exists($t, $memo)) return $memo[$t];
  try {
    require_once __DIR__ . '/../../lib/auth.php';
    $r = authSession_($t); $s = $r['s']; $a = $r['a'];
    if (PortalCore::active($a['WAJIB_GANTI'] ?? false) || !empty($s['demoNik'])) return $memo[$t] = null;
    return $memo[$t] = ['nik' => (string) $s['nik'], 'role' => (string) ($s['role'] ?? '')];
  } catch (Throwable $e) { return $memo[$t] = null; }
}
/** Data orang di DSM (nama, posisi, region). Hanya dipanggil saat menambah akses, bukan tiap request. */
function dsm_person(string $nik): ?array {
  try { require_once __DIR__ . '/../../lib/auth.php'; $p = PortalCore::person(snapshot_()['meta']['people'], $nik); return $p ?: null; } catch (Throwable $e) { return null; }
}
/** Super Admin DSM otomatis punya akses Super Admin Klaim, supaya ada yang bisa mengatur pengguna. */
function dsm_provision(array $auth): ?array {
  if ($auth['role'] !== 'SUPER ADMIN') return null;
  $p = dsm_person($auth['nik']);
  $id = ins('claim_users', ['username' => strtolower($auth['nik']), 'pass_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'name' => $p['nama'] ?? $auth['nik'], 'job' => 'Super Admin', 'nik' => $auth['nik'], 'region' => null, 'entity' => null, 'roles' => 'SA', 'can_submit' => 0, 'active' => 1, 'created_at' => now()]);
  return one("SELECT * FROM claim_users WHERE id=?", [$id]);
}
/** Menyelaraskan sesi PHP Klaim dengan akun DSM yang sedang masuk. false = tidak punya akses Klaim. */
function dsm_bind_session(): bool {
  $auth = dsm_auth(); if (!$auth || str_starts_with($auth['nik'], 'UJI-')) return false; // UJI-* hanya akun uji, tidak boleh login
  $row = one("SELECT * FROM claim_users WHERE nik=?", [$auth['nik']]) ?: dsm_provision($auth);
  if (!$row || !$row['active']) return false;
  if (($_SESSION['dsm_nik'] ?? '') !== $auth['nik']) { $_SESSION = ['dsm_nik' => $auth['nik'], 'uid' => (int) $row['id'], 'real_uid' => (int) $row['id']]; }
  if (!empty($_SERVER['HTTP_X_DSM_TOKEN'])) $_SESSION['dsm_tok'] = (string) $_SERVER['HTTP_X_DSM_TOKEN'];
  return true;
}
