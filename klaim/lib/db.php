<?php
// Lapisan database: MySQL (Laragon) atau SQLite (uji otomatis). SQL dibuat portabel.
date_default_timezone_set('Asia/Jakarta');
/** True bila modul ini berjalan di dalam repo DSM (folder klaim/) dan memakai login + database DSM. */
function dsm_present(): bool { static $p; return $p ??= (getenv('CLAIM_STANDALONE') !== '1' && is_file(__DIR__ . '/../../lib/auth.php')); }
function cfg(): array {
  static $c; if ($c) return $c;
  $base = ['upload_max_mb' => 8, 'upload_types' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], 'session_minutes' => 600, 'backup_session_minutes' => 120];
  if (getenv('CLAIM_DRIVER') === 'sqlite') return $c = $base + ['driver' => 'sqlite', 'sqlite_path' => getenv('CLAIM_SQLITE') ?: (__DIR__ . '/../storage/claim.sqlite')];
  if (dsm_present()) { require_once __DIR__ . '/../../config.php'; return $c = $base + ['driver' => 'mysql', 'host' => DSM_DB_HOST, 'port' => (int) DSM_DB_PORT, 'db' => DSM_DB_NAME, 'user' => DSM_DB_USER, 'pass' => DSM_DB_PASS]; }
  return $c = $base + ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'db' => 'dsm_claim', 'user' => 'root', 'pass' => ''];
}
function db(): PDO {
  static $pdo;
  if ($pdo) return $pdo;
  $c = cfg();
  if (($c['driver'] ?? 'mysql') === 'sqlite') {
    $pdo = new PDO('sqlite:' . $c['sqlite_path']);
    $pdo->exec('PRAGMA foreign_keys=OFF');
  } else {
    $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['db']};charset=utf8mb4", $c['user'], $c['pass']);
    $pdo->exec("SET time_zone='+07:00'");
  }
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  return $pdo;
}
function q(string $sql, array $p = []): array { $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll(); }
function one(string $sql, array $p = []): ?array { $r = q($sql, $p); return $r[0] ?? null; }
function val(string $sql, array $p = []) { $r = one($sql, $p); return $r ? array_values($r)[0] : null; }
function ex(string $sql, array $p = []): int { $s = db()->prepare($sql); $s->execute($p); return $s->rowCount(); }
function ins(string $table, array $row): int {
  $cols = array_keys($row);
  ex("INSERT INTO $table (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")", array_values($row));
  return (int) db()->lastInsertId();
}
function upd(string $table, int $id, array $row): void {
  $set = implode(',', array_map(fn($c) => "$c=?", array_keys($row)));
  ex("UPDATE $table SET $set WHERE id=?", [...array_values($row), $id]);
}
function now(): string { return date('Y-m-d H:i:s'); }
function jenc($v): string { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
function jdec(?string $s) { return $s ? json_decode($s, true) : null; }
function rp(int $n): string { return ($n < 0 ? '-' : '') . 'Rp ' . number_format(abs($n), 0, ',', '.'); }
function install_schema(): void {
  $c = cfg(); $sqlite = ($c['driver'] ?? 'mysql') === 'sqlite';
  $tables = ['claim_users','claim_entitlement','claim_types','claim_lookup','claim_gate','claim_rules','claim_settings','claim_seq','claims','claim_versions','claim_notes','claim_files','claim_approvals','claim_history','claim_ba','claim_ba_sign','claim_transfers','claim_pc','claim_pc_notes','claim_notif','claim_backup','claim_export_log','claim_fuel_price'];
  foreach ($tables as $t) db()->exec("DROP TABLE IF EXISTS $t");
  $sql = file_get_contents(__DIR__ . '/../sql/schema.sql');
  $sql = str_replace(['{AI}', '{LTXT}'], $sqlite ? ['INTEGER PRIMARY KEY AUTOINCREMENT', 'TEXT'] : ['INT AUTO_INCREMENT PRIMARY KEY', 'MEDIUMTEXT'], $sql);
  if ($sqlite) $sql = preg_replace('/\bBIGINT\b/', 'INTEGER', $sql);
  foreach (array_filter(array_map('trim', explode(';', $sql))) as $st) db()->exec($st);
  // Riwayat append-only: tidak bisa diubah/dihapus (PRD 63, 71)
  foreach (['UPDATE', 'DELETE'] as $op) {
    $n = "claim_history_no_" . strtolower($op);
    db()->exec($sqlite
      ? "CREATE TRIGGER $n BEFORE $op ON claim_history BEGIN SELECT RAISE(ABORT,'riwayat append-only'); END"
      : "CREATE TRIGGER $n BEFORE $op ON claim_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='riwayat append-only'");
  }
}

/** Migrasi AMAN (additive) untuk database DSM: hanya membuat tabel yang belum ada, tidak menghapus apa pun. */
function migrate_schema(): array {
  $c = cfg(); $sqlite = ($c['driver'] ?? 'mysql') === 'sqlite'; $made = [];
  $sql = file_get_contents(__DIR__ . '/../sql/schema.sql');
  $sql = str_replace(['{AI}', '{LTXT}'], $sqlite ? ['INTEGER PRIMARY KEY AUTOINCREMENT', 'TEXT'] : ['INT AUTO_INCREMENT PRIMARY KEY', 'MEDIUMTEXT'], $sql);
  if ($sqlite) $sql = preg_replace('/\bBIGINT\b/', 'INTEGER', $sql);
  foreach (array_filter(array_map('trim', explode(';', $sql))) as $st) {
    if (!preg_match('/^CREATE TABLE\s+(\w+)/i', $st, $m)) continue;
    $exists = $sqlite ? (bool) val("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?", [$m[1]]) : (bool) val("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?", [$m[1]]);
    if ($exists) continue;
    db()->exec($st); $made[] = $m[1];
  }
  // kolom tambahan untuk database yang sudah ada (aditif)
  foreach ([['claim_users', 'sign_img', $sqlite ? 'TEXT' : 'MEDIUMTEXT'], ['claim_ba_sign', 'img', $sqlite ? 'TEXT' : 'MEDIUMTEXT'], ['claims', 'entity_by', 'INT'], ['claims', 'rev_notes', $sqlite ? 'TEXT' : 'MEDIUMTEXT'], ['claim_rules', 'min_km_mobil', 'INT'], ['claim_rules', 'min_km_motor', 'INT'], ['claims', 'type_ids', 'VARCHAR(200)'], ['claim_users', 'wa', 'VARCHAR(20)'], ['claim_ba', 'extra', $sqlite ? 'TEXT' : 'MEDIUMTEXT'], ['claim_rules', 'days', 'VARCHAR(400)'], ['claim_rules', 'max_no_nota', 'INT'], ['claim_rules', 'min_km_makan', 'INT'], ['claim_backup', 'to_user_id', 'INT'], ['claim_backup', 'duration_min', 'INT'], ['claim_backup', 'active_until', 'DATETIME'], ['claim_backup', 'use_count', 'INT'], ['claim_backup', 'max_use', 'INT'], ['claim_backup', 'last_used_at', 'DATETIME'], ['claim_backup', 'revoked_at', 'DATETIME'], ['claim_backup', 'revoked_by', 'INT']] as [$tb, $col, $ty]) {
    $has = $sqlite ? (bool) array_filter(q("PRAGMA table_info($tb)"), fn($r) => $r['name'] === $col)
      : (bool) val("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?", [$tb, $col]);
    if (!$has) { db()->exec("ALTER TABLE $tb ADD COLUMN $col $ty NULL"); $made[] = "$tb.$col"; }
  }
  foreach (['UPDATE', 'DELETE'] as $op) {
    $n = 'claim_history_no_' . strtolower($op);
    $has = $sqlite ? (bool) val("SELECT 1 FROM sqlite_master WHERE type='trigger' AND name=?", [$n]) : (bool) val("SELECT 1 FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name=?", [$n]);
    if ($has) continue;
    db()->exec($sqlite ? "CREATE TRIGGER $n BEFORE $op ON claim_history BEGIN SELECT RAISE(ABORT,'riwayat append-only'); END"
      : "CREATE TRIGGER $n BEFORE $op ON claim_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='riwayat append-only'");
  }
  return $made;
}
