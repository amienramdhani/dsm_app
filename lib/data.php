<?php
/**
 * Lapisan data DSM — pengganti SpreadsheetApp / DriveApp / PropertiesService.
 *
 *  - Setiap sheet Google Sheets jadi 1 tabel MySQL "sh_<nama>" dengan kolom
 *    sama persis dengan header sheet (lihat tabel _sheets untuk pemetaannya).
 *  - Script Properties -> tabel _props.
 *  - File JSON di Drive (snapshot, data per region) -> folder storage/files.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/js.php';

function db_(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DSM_DB_HOST . ';port=' . DSM_DB_PORT . ';dbname=' . DSM_DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DSM_DB_USER, DSM_DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='', time_zone='+00:00'",
        ]);
    }
    return $pdo;
}

function storage_dir_(string $sub = ''): string {
    $base = defined('DSM_STORAGE') ? DSM_STORAGE : __DIR__ . '/../storage';
    $dir = $base . ($sub !== '' ? '/' . $sub : '');
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    return $dir;
}

// ------------------------------------------------------------------
// Script Properties
// ------------------------------------------------------------------

function prop_get_(string $k): ?string {
    $st = db_()->prepare('SELECT v FROM _props WHERE k = ?');
    $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string) $v;
}
function prop_set_(string $k, $v): void {
    db_()->prepare('REPLACE INTO _props (k, v) VALUES (?, ?)')->execute([$k, (string) $v]);
}
function prop_delete_(string $k): void { db_()->prepare('DELETE FROM _props WHERE k = ?')->execute([$k]); }
function prop_all_(): array {
    $o = [];
    foreach (db_()->query('SELECT k, v FROM _props')->fetchAll() as $r) $o[$r['k']] = $r['v'];
    return $o;
}

// ------------------------------------------------------------------
// File JSON (pengganti Drive): writeJson_ / readJson_
// ------------------------------------------------------------------

function file_path_(string $id): string {
    if (!preg_match('/^[A-Za-z0-9_.-]{1,200}$/', $id)) fail_('ID file tidak valid.');
    return storage_dir_('files') . '/' . $id . '.json';
}
function newFileId_(): string { return 'F' . date('YmdHis') . '-' . bin2hex(random_bytes(6)); }
function writeJson_(string $name, $obj): string {
    $id = newFileId_();
    file_put_contents(file_path_($id), js_stringify($obj), LOCK_EX);
    return $id;
}
function writeJsonId_(string $id, $obj): void { file_put_contents(file_path_($id), js_stringify($obj), LOCK_EX); }
function readJson_(string $id) {
    static $memo = [];
    if (array_key_exists($id, $memo)) return $memo[$id];
    $p = file_path_($id);
    if (!is_file($p)) fail_('File data tidak ditemukan: ' . $id);
    $v = json_decode(file_get_contents($p), true, 512, JSON_BIGINT_AS_STRING);
    if (count($memo) > 40) $memo = [];
    return $memo[$id] = $v;
}

// ------------------------------------------------------------------
// Sheet mirror
// ------------------------------------------------------------------

function sheet_registry_(bool $fresh = false): array {
    static $reg = null;
    if ($reg === null || $fresh) {
        $reg = [];
        foreach (db_()->query('SELECT name, tbl, heads, cols FROM _sheets')->fetchAll() as $r) {
            $reg[$r['name']] = ['tbl' => $r['tbl'], 'heads' => json_decode($r['heads'], true), 'cols' => json_decode($r['cols'], true)];
        }
    }
    return $reg;
}
function sheet_info_(string $name): ?array { return sheet_registry_()[$name] ?? null; }
function sheet_exists_(string $name): bool { return sheet_info_($name) !== null; }

/** Nilai sel untuk disimpan (string), meniru tampilan nilai Sheets. */
function cell_out_($v): string {
    if ($v === null) return '';
    if ($v === true) return 'TRUE';
    if ($v === false) return 'FALSE';
    if (is_int($v) || is_float($v)) return js_str($v);
    if (is_array($v)) return js_stringify($v);
    return (string) $v;
}

function sheet_table_name_(string $name): string {
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $name));
    $slug = trim($slug, '_');
    if ($slug === '') $slug = 'sheet';
    return substr('sh_' . $slug, 0, 60);
}

function sheet_columns_(array $heads): array {
    $cols = []; $used = [];
    foreach (array_values($heads) as $i => $h) {
        $c = preg_replace('/[`\x00-\x1F]/', '', text_($h));
        $c = rtrim(substr($c, 0, 60));
        if ($c === '') $c = 'col_' . ($i + 1);
        $base = $c; $n = 2;
        while (isset($used[mb_strtolower($c)])) { $c = substr($base, 0, 56) . '_' . $n++; }
        $used[mb_strtolower($c)] = true;
        $cols[] = $c;
    }
    return $cols;
}

/** sheet.getDataRange().getValues() — [header, ...baris]; null bila sheet tidak ada. */
function readGrid_(string $name): ?array {
    $info = sheet_info_($name);
    if (!$info) return null;
    $cols = $info['cols'];
    $grid = [$info['heads']];
    if ($cols) {
        $sel = implode(',', array_map(fn($c) => '`' . $c . '`', $cols));
        foreach (db_()->query('SELECT ' . $sel . ' FROM `' . $info['tbl'] . '` ORDER BY _row')->fetchAll(PDO::FETCH_NUM) as $r) {
            $grid[] = array_map(fn($x) => $x === null ? '' : $x, $r);
        }
    }
    return $grid;
}

/** table_(name): baris sebagai objek header -> nilai; baris kosong dibuang. */
function table_(string $name): array {
    static $memo = [];
    if (isset($GLOBALS['__table_memo_gen']) && ($memo['__gen'] ?? null) !== $GLOBALS['__table_memo_gen']) { $memo = ['__gen' => $GLOBALS['__table_memo_gen']]; }
    if (isset($memo[$name])) return $memo[$name];
    $grid = readGrid_($name);
    if ($grid === null) fail_('Sheet tidak ditemukan: ' . $name);
    $heads = array_map('text_', array_shift($grid));
    $out = [];
    foreach ($grid as $r) {
        $any = false; foreach ($r as $x) if ($x !== '' && $x !== null) { $any = true; break; }
        if (!$any) continue;
        $o = [];
        foreach ($heads as $i => $h) $o[$h] = $r[$i] ?? '';
        $out[] = $o;
    }
    return $memo[$name] = $out;
}
function table_cache_clear_(): void { $GLOBALS['__table_memo_gen'] = ($GLOBALS['__table_memo_gen'] ?? 0) + 1; }
/** Tandai data berubah: cache hasil (lib/rescache.php) otomatis tidak dipakai lagi. Sheet log/akun tidak memengaruhi hasil laporan. */
function data_rev_bump_(string $sheet = ''): void {
    if (in_array($sheet, ['Audit_Log', '_Auth', 'Backup_Log', 'Version_Log'], true)) return;
    try { prop_set_('DATA_REV', sprintf('%.6f', microtime(true))); } catch (Throwable $e) { /* cache hanya optimasi */ }
}
function optionalTable_(string $name): array { return sheet_exists_($name) ? table_($name) : []; }

/** putTable_(name, heads, rows): ganti seluruh isi sheet. */
function putTable_(string $name, array $heads, array $rows): void {
    table_cache_clear_(); data_rev_bump_($name);
    $pdo = db_();
    $tbl = sheet_table_name_($name);
    $old = sheet_info_($name);
    if ($old) $tbl = $old['tbl'];
    else {
        // hindari bentrok nama tabel dari dua sheet berbeda
        $taken = array_column(sheet_registry_(), 'tbl');
        $base = $tbl; $n = 2;
        while (in_array($tbl, $taken, true)) $tbl = substr($base, 0, 56) . '_' . $n++;
    }
    $heads = array_values($heads);
    $cols = sheet_columns_($heads);
    // DSM v5: tulis ke tabel baru dulu, lalu tukar nama sekaligus (RENAME atomik).
    // Kalau proses gagal/terputus di tengah, tabel lama tetap utuh — tidak ada lagi sheet setengah kosong.
    $tmp = substr($tbl, 0, 60) . '__n'; $bak = substr($tbl, 0, 60) . '__o';
    $pdo->exec('DROP TABLE IF EXISTS `' . $tmp . '`');
    $defs = ['`_row` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY'];
    foreach ($cols as $c) $defs[] = '`' . $c . '` TEXT NULL';
    $pdo->exec('CREATE TABLE `' . $tmp . '` (' . implode(',', $defs) . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC');
    if ($rows && $cols) sheet_insert_rows_($tmp, $cols, $rows);
    $exists = (bool) $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tbl))->fetchColumn();
    $pdo->exec('DROP TABLE IF EXISTS `' . $bak . '`');
    if ($exists) $pdo->exec('RENAME TABLE `' . $tbl . '` TO `' . $bak . '`, `' . $tmp . '` TO `' . $tbl . '`');
    else $pdo->exec('RENAME TABLE `' . $tmp . '` TO `' . $tbl . '`');
    $pdo->prepare('REPLACE INTO _sheets (name, tbl, heads, cols, updated_at) VALUES (?,?,?,?,UTC_TIMESTAMP())')
        ->execute([$name, $tbl, json_encode(array_map('cell_out_', $heads), JSON_UNESCAPED_UNICODE), json_encode($cols, JSON_UNESCAPED_UNICODE)]);
    sheet_registry_(true);
    if ($exists) $pdo->exec('DROP TABLE IF EXISTS `' . $bak . '`');
}

function sheet_insert_rows_(string $tbl, array $cols, array $rows): void {
    $pdo = db_();
    $n = count($cols);
    $colSql = implode(',', array_map(fn($c) => '`' . $c . '`', $cols));
    foreach (array_chunk($rows, 500) as $chunk) {
        $ph = []; $vals = [];
        foreach ($chunk as $r) {
            $r = array_values((array) $r);
            $ph[] = '(' . implode(',', array_fill(0, $n, '?')) . ')';
            for ($i = 0; $i < $n; $i++) $vals[] = cell_out_($r[$i] ?? '');
        }
        $pdo->prepare('INSERT INTO `' . $tbl . '` (' . $colSql . ') VALUES ' . implode(',', $ph))->execute($vals);
    }
}

function ensureTable_(string $name, array $heads, array $rows = []): void {
    $i = sheet_info_($name);
    // sheet kosong tanpa header (mis. hasil insertSheet) dianggap belum dibuat
    if ($i && !array_filter($i['heads'], fn($h) => $h !== '')) { $g = readGrid_($name); if (count($g) <= 2 && !array_filter($g[1] ?? [], fn($v) => $v !== '' && $v !== null)) $i = null; }
    if (!$i) putTable_($name, $heads, $rows);
}

/** Baris data + id baris internal (_row), untuk update satu baris. */
function sheet_rows_with_id_(string $name): array {
    $info = sheet_info_($name);
    if (!$info) fail_('Sheet tidak ditemukan: ' . $name);
    $sel = implode(',', array_map(fn($c) => '`' . $c . '`', $info['cols']));
    $out = [];
    foreach (db_()->query('SELECT _row' . ($sel ? ',' . $sel : '') . ' FROM `' . $info['tbl'] . '` ORDER BY _row')->fetchAll(PDO::FETCH_NUM) as $r) {
        $id = (int) array_shift($r); $out[] = ['_row' => $id, 'values' => array_map(fn($x) => $x ?? '', $r)];
    }
    return $out;
}
/** Ganti nilai kolom ke-1..n pada satu baris (setara getRange(i,1,1,n).setValues). */
function sheet_update_row_(string $name, int $rowId, array $values, int $startCol = 1): void {
    table_cache_clear_(); data_rev_bump_($name);
    $info = sheet_info_($name);
    $cols = array_slice($info['cols'], $startCol - 1, count($values));
    if (!$cols) return;
    $set = implode(',', array_map(fn($c) => '`' . $c . '`=?', $cols));
    db_()->prepare('UPDATE `' . $info['tbl'] . '` SET ' . $set . ' WHERE _row=?')->execute(array_merge(array_map('cell_out_', array_slice(array_values($values), 0, count($cols))), [$rowId]));
}

/** sheet.appendRow(row) */
function appendRow_(string $name, array $row): void {
    table_cache_clear_(); data_rev_bump_($name);
    $info = sheet_info_($name);
    if (!$info) fail_('Sheet tidak ditemukan: ' . $name);
    $cols = $info['cols'];
    $row = array_values($row);
    if (count($row) > count($cols)) { // sheet melebar: tambah kolom tanpa header
        $heads = $info['heads'];
        for ($i = count($cols); $i < count($row); $i++) $heads[] = '';
        $grid = readGrid_($name); array_shift($grid); $grid[] = $row;
        putTable_($name, $heads, $grid);
        return;
    }
    sheet_insert_rows_($info['tbl'], $cols, [$row]);
}

// ------------------------------------------------------------------
// Audit & snapshot
// ------------------------------------------------------------------

function audit_(string $nik, string $action, $detail = ''): void {
    try {
        if (!sheet_exists_('Audit_Log')) return;
        appendRow_('Audit_Log', [iso_now(), $nik, $action, mb_substr(js_str($detail ?? ''), 0, 2000)]);
    } catch (Throwable $e) { error_log('[dsm] audit gagal: ' . $e->getMessage()); }
}

function snapshot_(): array {
    static $snap = null, $snapId = null;
    $id = prop_get_('ACTIVE');
    if (!$id) fail_('Belum ada data dipublikasikan.');
    $key = $id . '|' . (prop_get_('DEALER_CONTACT_STATE') ?? '');
    if ($snap !== null && $snapId === $key) return $snap;
    $s = readJson_($id);
    PortalCore::resetMemo($id);
    PortalCore::adaptMeta($s['meta']);
    applyDealerContacts_($s['meta']);
    $s['id'] = $id; $s['_vid'] = $id;
    $snapId = $key;
    return $snap = $s;
}

function contactState_(): array {
    $id = prop_get_('DEALER_CONTACT_STATE');
    return $id ? readJson_($id) : ['requests' => [], 'overrides' => []];
}
function applyDealerContacts_(array &$meta, ?array $state = null): array {
    $state = $state ?? contactState_();
    foreach (js_keys($state['overrides'] ?? []) as $id) {
        if (isset($meta['customers'][$id])) $meta['customers'][$id] = array_merge($meta['customers'][$id], $state['overrides'][$id]);
    }
    return $meta;
}

/** book.deleteSheet(sheet): hapus tabel + catatan registri. */
function deleteSheet_(string $name): void {
    $info = sheet_info_($name); if (!$info) return;
    table_cache_clear_(); data_rev_bump_($name);
    db_()->exec('DROP TABLE IF EXISTS `' . $info['tbl'] . '`');
    db_()->prepare('DELETE FROM _sheets WHERE name = ?')->execute([$name]);
    sheet_registry_(true);
}
/** n baris terakhir sheet (urutan sheet) sebagai [ [nilai...], ... ] beserta nomor baris sheet (mulai 2). */
function sheet_tail_(string $name, int $n): array {
    $info = sheet_info_($name); if (!$info || !$info['cols']) return [];
    $sel = implode(',', array_map(fn($c) => '`' . $c . '`', $info['cols']));
    $rows = db_()->query('SELECT _row, ' . $sel . ' FROM `' . $info['tbl'] . '` ORDER BY _row DESC LIMIT ' . (int) $n)->fetchAll(PDO::FETCH_NUM);
    $out = [];
    foreach (array_reverse($rows) as $r) { $id = array_shift($r); $out[] = ['_row' => (int) $id, 'values' => array_map(fn($x) => $x === null ? '' : $x, $r)]; }
    return $out;
}
require_once __DIR__ . '/store.php';
