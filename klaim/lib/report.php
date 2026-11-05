<?php
require_once __DIR__ . '/query.php';

/* ---------- monitoring budget (H05) dan rincian per orang ---------- */
function monitor_scope_ok(array $u): bool { return has_role($u, 'HOS') || has_role($u, 'AUDIT') || has_role($u, 'FM') || has_role($u, 'SA') || has_role($u, 'ADMIN'); }
function monitor_budget(array $u, array $f): array {
  if (!monitor_scope_ok($u)) fail('Tidak diizinkan.', 403);
  $period = $f['period'] ?? date('Y-m'); $w = ['can_submit=1', 'active=1']; $p = [];
  $global = has_role($u, 'HOS') || has_role($u, 'SA');
  if (!$global) {
    if ((has_role($u, 'AUDIT') || has_role($u, 'FM')) && $u['entities']) { $w[] = '(' . implode(' OR ', array_fill(0, count($u['entities']), "entity LIKE ?")) . ')'; foreach ($u['entities'] as $e) $p[] = "%$e%"; }
    elseif (has_role($u, 'ADMIN') && $u['region']) { $w[] = 'region=?'; $p[] = $u['region']; }
  }
  if (!empty($f['region'])) { $w[] = 'region=?'; $p[] = $f['region']; }
  if (!empty($f['claimant_id'])) { $w[] = 'id=?'; $p[] = (int) $f['claimant_id']; }
  $users = q("SELECT * FROM claim_users WHERE " . implode(' AND ', $w) . " ORDER BY name", $p);
  $cats = !empty($f['category']) ? [$f['category']] : CATS; $rows = []; $sum = ['budget' => 0, 'realized' => 0, 'reserved' => 0, 'available' => 0, 'over' => 0];
  $n = aging_days();
  foreach ($users as $r) {
    $per = []; $tb = $tr = $ts = $to = 0; $cnt = 0; $ba = 0;
    foreach ($cats as $c) {
      $b = budget_info((int) $r['id'], $c, $period); $b['enabled'] = (bool) val("SELECT enabled FROM claim_entitlement WHERE user_id=? AND category=?", [$r['id'], $c]);
      $b['count'] = (int) val("SELECT COUNT(*) FROM claims WHERE claimant_id=? AND category=? AND budget_period=? AND status IN ('WAIT','REV','DONE')", [$r['id'], $c, $period]);
      $b['ba'] = (int) val("SELECT COUNT(*) FROM claims WHERE claimant_id=? AND category=? AND budget_period=? AND status IN ('WAIT','REV','DONE') AND ba_required=1", [$r['id'], $c, $period]);
      $per[$c] = $b; $tb += $b['budget']; $tr += $b['realized']; $ts += $b['reserved']; $to += $b['over']; $cnt += $b['count']; $ba += $b['ba'];
    }
    $lama = $n === null ? 0 : (int) val("SELECT COUNT(*) FROM claims WHERE claimant_id=? AND status IN ('WAIT','REV') AND stage_since<?", [$r['id'], date('Y-m-d H:i:s', time() - $n * 86400)]);
    $pc = (int) val("SELECT COALESCE(SUM(amount-realized),0) FROM claim_pc WHERE claimant_id=? AND status IN ('DANA')", [$r['id']]);
    $rows[] = ['id' => (int) $r['id'], 'name' => $r['name'], 'job' => $r['job'], 'region' => $r['region'], 'entity' => $r['entity'], 'cats' => $per, 'budget' => $tb, 'realized' => $tr, 'reserved' => $ts, 'available' => $tb - $tr - $ts, 'over' => $to, 'count' => $cnt, 'ba' => $ba, 'lama' => $lama, 'pc_out' => $pc];
    $sum['budget'] += $tb; $sum['realized'] += $tr; $sum['reserved'] += $ts; $sum['over'] += $to;
  }
  $sum['available'] = $sum['budget'] - $sum['realized'] - $sum['reserved'];
  return ['period' => $period, 'sum' => $sum, 'rows' => $rows, 'regions' => array_column(q("SELECT DISTINCT region FROM claim_users WHERE region IS NOT NULL AND can_submit=1 ORDER BY region"), 'region')];
}
function claimant_claims(array $u, int $uid, string $period): array {
  $m = monitor_budget($u, ['claimant_id' => $uid, 'period' => $period]);
  if (!$m['rows']) fail('Tidak ada data atau di luar cakupan Anda.', 403);
  $rows = q("SELECT * FROM claims WHERE claimant_id=? AND budget_period=? AND status<>'DRAFT' ORDER BY id DESC", [$uid, $period]);
  return ['person' => $m['rows'][0], 'claims' => array_map('row_out', $rows)];
}

/* ---------- realisasi finance (F05) ---------- */
function realisasi(array $u, array $f): array {
  if (!has_role($u, 'FT') && !has_role($u, 'FM') && !has_role($u, 'SA') && !has_role($u, 'HOS')) fail('Tidak diizinkan.', 403);
  $period = $f['period'] ?? date('Y-m'); $ents = $u['entities']; $mine = (int) $u['id'];
  foreach (['FT', 'FM'] as $rr) if (has_role($u, $rr)) $ents = array_values(array_unique(array_merge($ents, uncovered_entities($rr)))); // badan usaha tanpa pemegang tetap masuk
  $scope = $ents; if (has_role($u, 'SA') || has_role($u, 'HOS')) $scope = array_column(q("SELECT DISTINCT entity FROM claims WHERE entity IS NOT NULL"), 'entity');
  if (!empty($f['entity'])) $ents = [$f['entity']];
  if (has_role($u, 'SA') || has_role($u, 'HOS')) $ents = !empty($f['entity']) ? [$f['entity']] : $scope;
  if (!$ents && !has_role($u, 'FT')) return ['period' => $period, 'empty' => true];
  if (!$ents) $ents = ['-'];
  $in = implode(',', array_fill(0, count($ents), '?'));
  $d1 = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['from'] ?? '')) ? $f['from'] : null; $d2 = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['to'] ?? '')) ? $f['to'] : null;
  $range = $d1 || $d2; $dcol = fn($c) => "substr($c,1,10)";
  $dflt = fn($c) => $range ? str_replace('%s', $dcol($c), ($d1 ? " AND %s>='$d1'" : '') . ($d2 ? " AND %s<='$d2'" : '')) : " AND substr($c,1,7)='" . preg_replace('/[^0-9-]/', '', $period) . "'";
  $tf = fn($rt) => q("SELECT t.*, u.name AS uname FROM claim_transfers t JOIN claim_users u ON u.id=t.user_id WHERE t.ref_type=? AND (t.entity IN ($in) OR t.user_id=?)" . $dflt('t.created_at') . " ORDER BY t.id DESC", [$rt, ...$ents, has_role($u, 'FT') ? $mine : 0]);
  $cl = $tf('claim'); $pcT = $tf('pc');
  $byCat = []; $byPerson = []; $trx = [];
  foreach ($cl as $t) {
    $c = get_claim((int) $t['ref_id']); $cu = val("SELECT name FROM claim_users WHERE id=?", [$c['claimant_id']]);
    $kind = $c['pc_id'] ? 'Reimburse petty cash' : 'Klaim reguler';
    $byCat[$c['category']] = ($byCat[$c['category']] ?? 0) + (int) $t['amount']; $byPerson[$cu] = ($byPerson[$cu] ?? 0) + (int) $t['amount'];
    $trx[] = ['at' => $t['created_at'], 'ref' => $c['number'], 'who' => $cu, 'kind' => $kind, 'amount' => (int) $t['amount'], 'transfer_no' => $t['transfer_no']];
  }
  foreach ($pcT as $t) { $p = get_pc((int) $t['ref_id']); $cu = val("SELECT name FROM claim_users WHERE id=?", [$p['claimant_id']]);
    $trx[] = ['at' => $t['created_at'], 'ref' => $p['number'], 'who' => $cu, 'kind' => 'Transfer dana petty cash', 'amount' => (int) $t['amount'], 'transfer_no' => $t['transfer_no']]; }
  usort($trx, fn($a, $b) => strcmp($b['at'], $a['at']));
  $pcRows = q("SELECT * FROM claim_pc WHERE entity IN ($in) AND funded_at IS NOT NULL" . $dflt('funded_at'), [...$ents]);
  $cair = array_sum(array_column($pcRows, 'amount')); $real = array_sum(array_column($pcRows, 'realized'));
  $setor = (int) array_sum(array_map(fn($r) => $r['status'] === 'SELESAI' ? (int) $r['setoran_amount'] : 0, $pcRows));
  $belum = (int) array_sum(array_map(fn($r) => $r['status'] === 'DANA' ? (int) $r['amount'] : 0, $pcRows));
  arsort($byPerson);
  $fm = (has_role($u, 'FM') || has_role($u, 'HOS') || has_role($u, 'SA')) ? realisasi_fm($ents, $period, $d1, $d2, $cl, $trx) : null;
  return ['fm' => $fm, 'scope' => array_values($scope), 'period' => $period, 'from' => $d1, 'to' => $d2, 'entities' => $ents, 'sum' => ['klaim' => array_sum(array_column($cl, 'amount')), 'pc_cair' => $cair, 'setor' => $setor, 'jumlah' => count($cl)],
    'byCat' => $byCat, 'byPerson' => $byPerson, 'trx' => $trx, 'pc' => ['cair' => $cair, 'real' => $real, 'setor' => $setor, 'belum' => $belum]];
}

/** Data analisa Realisasi untuk HOS / Finance Manager: KPI + pembanding, tren, komposisi, badan usaha, top pemohon, budget, sorotan BA. */
function realisasi_fm(array $ents, string $period, ?string $d1, ?string $d2, array $cl, array $trx): array {
  $in = implode(',', array_fill(0, count($ents), '?')); $range = $d1 || $d2;
  $lo = $d1 ?: ($range ? '1970-01-01' : $period . '-01'); $hi = $d2 ?: ($range ? '2999-12-31' : date('Y-m-t', strtotime($period . '-01')));
  $sumT = fn($a, $b) => (int) val("SELECT COALESCE(SUM(t.amount),0) FROM claim_transfers t WHERE t.ref_type='claim' AND t.entity IN ($in) AND substr(t.created_at,1,10)>=? AND substr(t.created_at,1,10)<=?", [...$ents, $a, $b]);
  $total = array_sum(array_column($cl, 'amount')); $n = count($cl);
  if ($range && $d1 && $d2) { $days = max(1, (int) round((strtotime($d2) - strtotime($d1)) / 86400) + 1); $pa = date('Y-m-d', strtotime($d1 . " -$days days")); $pb = date('Y-m-d', strtotime($d1 . ' -1 day')); }
  else { $pa = date('Y-m-01', strtotime($lo . ' -1 month')); $pb = date('Y-m-t', strtotime($pa)); }
  $prev = $sumT($pa, $pb);
  // BA & kategori & badan usaha & pemohon dari transaksi klaim
  $baN = 0; $baT = 0; $trig = []; $byEnt = []; $byCat = []; $byP = []; $daily = [];
  foreach ($cl as $t) {
    $c = get_claim((int) $t['ref_id']); $amt = (int) $t['amount'];
    $byEnt[$t['entity'] ?: '-'] = ($byEnt[$t['entity'] ?: '-'] ?? 0) + $amt; $byCat[$c['category']] = ($byCat[$c['category']] ?? 0) + $amt;
    $nm = val("SELECT name FROM claim_users WHERE id=?", [$c['claimant_id']]); $byP[$nm] = ($byP[$nm] ?? 0) + $amt;
    $day = substr($t['created_at'], 0, 10); $daily[$day] = ($daily[$day] ?? 0) + $amt;
    if ($ba = ba_row((int) $c['id'])) { $baN++; $baT += $amt; foreach (jdec($ba['triggers']) ?: [] as $cd) $trig[$cd] = ($trig[$cd] ?? 0) + 1; }
  }
  arsort($byEnt); arsort($byP); ksort($daily); arsort($trig);
  // tren: harian untuk <= 62 hari, selain itu bulanan
  $spanDays = $range && $d1 && $d2 ? (int) round((strtotime($d2) - strtotime($d1)) / 86400) + 1 : 31; $gran = $spanDays > 62 ? 'bulan' : 'hari'; $series = [];
  foreach ($daily as $d => $v) { $k = $gran === 'bulan' ? substr($d, 0, 7) : $d; $series[$k] = ($series[$k] ?? 0) + $v; }
  $cum = 0; $tren = []; foreach ($series as $k => $v) { $cum += $v; $tren[] = ['k' => $k, 'v' => $v, 'cum' => $cum]; }
  // realisasi vs budget per badan usaha per kategori (budget bulanan x jumlah bulan, realisasi klaim DONE per periode budget)
  $months = []; for ($m = strtotime(substr($lo === '1970-01-01' ? $period . '-01' : $lo, 0, 7) . '-01'), $e = strtotime(substr($hi === '2999-12-31' ? $period . '-01' : $hi, 0, 7) . '-01'); $m <= $e && count($months) < 24; $m = strtotime('+1 month', $m)) $months[] = date('Y-m', $m);
  $mi = implode(',', array_fill(0, count($months), '?')); $bud = [];
  foreach ($ents as $en) {
    $us = q("SELECT id FROM claim_users WHERE can_submit=1 AND active=1 AND entity=?", [$en]); $cats = [];
    foreach (CATS as $cat) {
      $b = 0; foreach ($us as $x) $b += budget_of((int) $x['id'], $cat); $b *= count($months);
      $r = (int) val("SELECT COALESCE(SUM(total),0) FROM claims WHERE status='DONE' AND outside_sales=0 AND category=? AND entity=? AND budget_period IN ($mi)", [$cat, $en, ...$months]);
      if ($b || $r) $cats[] = ['cat' => $cat, 'budget' => $b, 'real' => $r];
    }
    if ($cats) $bud[] = ['entity' => $en, 'cats' => $cats];
  }
  $pcb = q("SELECT amount FROM claim_pc WHERE entity IN ($in) AND status='DANA'", $ents);
  return ['kpi' => ['total' => $total, 'n' => $n, 'avg' => $n ? intdiv($total, $n) : 0, 'prev' => $prev, 'ba_n' => $baN, 'ba_total' => $baT, 'pc_belum' => (int) array_sum(array_column($pcb, 'amount')), 'pc_belum_n' => count($pcb)],
    'tren' => $tren, 'gran' => $gran, 'byEnt' => $byEnt, 'byCat' => $byCat, 'top' => array_slice($byP, 0, 10, true), 'bud' => $bud, 'months' => count($months),
    'ba' => ['trig' => array_map(fn($cd) => $cd, array_keys($trig)), 'cnt' => array_values($trig), 'label' => array_map(fn($cd) => TRIG[$cd] ?? $cd, array_keys($trig))]];
}

/* ---------- Excel (.xlsx tanpa library; butuh ekstensi zip) ---------- */
function xlsx_build(array $head, array $rows, string $sheet = 'Data'): string {
  $col = function (int $i): string { $s = ''; $i++; while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = intdiv($i - 1, 26); } return $s; };
  $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
  $all = array_merge([$head], $rows);
  foreach ($all as $ri => $r) {
    $x .= '<row r="' . ($ri + 1) . '">';
    foreach (array_values($r) as $ci => $v) {
      $ref = $col($ci) . ($ri + 1);
      if (is_int($v) || is_float($v)) $x .= "<c r=\"$ref\"><v>$v</v></c>";
      else $x .= "<c r=\"$ref\" t=\"inlineStr\"><is><t xml:space=\"preserve\">" . htmlspecialchars((string) $v, ENT_XML1) . '</t></is></c>';
    }
    $x .= '</row>';
  }
  $x .= '</sheetData></worksheet>';
  $tmp = tempnam(sys_get_temp_dir(), 'xl'); $z = new ZipArchive(); $z->open($tmp, ZipArchive::OVERWRITE);
  $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
  $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
  $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . htmlspecialchars($sheet, ENT_XML1) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
  $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
  $z->addFromString('xl/worksheets/sheet1.xml', $x); $z->close();
  $d = file_get_contents($tmp); unlink($tmp); return $d;
}
function export_claims(array $u, array $f): array {
  if (!has_role($u, 'ADMIN') && !has_role($u, 'FT') && !has_role($u, 'AUDIT') && !has_role($u, 'SA') && !has_role($u, 'FM') && !has_role($u, 'HOS')) fail('Tidak diizinkan.', 403);
  $f['view'] = 'all'; $f['limit'] = 200; $list = claim_list($u, $f)['rows'];
  $head = ['Nomor', 'Tanggal', 'Pemohon', 'Region', 'Badan usaha', 'Kategori', 'Jenis', 'Brand', 'Refnote', 'Jumlah nota', 'Rekening', 'Nominal', 'Status', 'Transfer', 'Nomor BA', 'Jumlah revisi', 'Jumlah lampiran', 'Link PDF', 'Tanggal nota', 'Lokasi', 'Rute tol'];
  $out = [];
  foreach ($list as $r) {
    $c = get_claim($r['id']); $notes = q("SELECT * FROM claim_notes WHERE claim_id=? ORDER BY line_no", [$r['id']]);
    $tr = one("SELECT transfer_no FROM claim_transfers WHERE ref_type='claim' AND ref_id=? ORDER BY id DESC", [$r['id']]); $ba = ba_row($r['id']);
    $tol = []; foreach ($notes as $n) { $d = jdec($n['detail']) ?: []; if (!empty($d['asal'])) $tol[] = $d['asal'] . ' → ' . $d['tujuan']; }
    $out[] = [$r['number'], substr((string) $r['submitted_at'], 0, 10), $r['claimant'], $r['region'], $r['entity'], $r['category'], $r['type'], $r['brand'], $r['refnote'], count($notes), $c['acct_bank'] . ' ' . $c['acct_no'] . ' a.n. ' . $c['acct_name'], (int) $r['total'], $r['label'], $tr['transfer_no'] ?? '', $ba['number'] ?? '',
      (int) val("SELECT COUNT(*) FROM claim_approvals WHERE ref_type='claim' AND ref_id=? AND action='revise'", [$r['id']]), (int) val("SELECT COUNT(*) FROM claim_files WHERE ref_type='claim' AND ref_id=? AND slot<>'removed'", [$r['id']]),
      'api.php?a=pdf&id=' . $r['id'], implode(', ', array_filter(array_map(fn($n) => $n['nota_date'], $notes))), $r['region'] ? ($c['location'] ?? '') : '', implode('; ', $tol)];
  }
  ins('claim_export_log', ['user_id' => $u['id'], 'kind' => 'klaim', 'filter_json' => substr(jenc($f), 0, 480), 'row_count' => count($out), 'created_at' => now()]);
  return [$head, $out];
}

/* ---------- PDF (HTML cetak, gaya G05-G13 yang disepakati) ---------- */
/** Terbilang rupiah: 50400 → "Lima Puluh Ribu Empat Ratus Rupiah" */
function terbilang(int $n): string {
  if ($n === 0) return 'Nol Rupiah'; if ($n < 0) return 'Minus ' . terbilang(-$n);
  $s = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
  $f = function (int $x) use (&$f, $s): string {
    if ($x < 12) return $s[$x];
    if ($x < 20) return $s[$x - 10] . ' Belas';
    if ($x < 100) return $s[intdiv($x, 10)] . ' Puluh' . ($x % 10 ? ' ' . $s[$x % 10] : '');
    if ($x < 200) return 'Seratus' . ($x % 100 ? ' ' . $f($x % 100) : '');
    if ($x < 1000) return $s[intdiv($x, 100)] . ' Ratus' . ($x % 100 ? ' ' . $f($x % 100) : '');
    if ($x < 2000) return 'Seribu' . ($x % 1000 ? ' ' . $f($x % 1000) : '');
    if ($x < 1000000) return $f(intdiv($x, 1000)) . ' Ribu' . ($x % 1000 ? ' ' . $f($x % 1000) : '');
    if ($x < 1000000000) return $f(intdiv($x, 1000000)) . ' Juta' . ($x % 1000000 ? ' ' . $f($x % 1000000) : '');
    if ($x < 1000000000000) return $f(intdiv($x, 1000000000)) . ' Miliar' . ($x % 1000000000 ? ' ' . $f($x % 1000000000) : '');
    return $f(intdiv($x, 1000000000000)) . ' Triliun' . ($x % 1000000000000 ? ' ' . $f($x % 1000000000000) : '');
  };
  return $f($n) . ' Rupiah';
}
function e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function tgl(?string $d): string { if (!$d) return '-'; $b = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']; return (int) substr($d, 8, 2) . ' ' . $b[(int) substr($d, 5, 2)] . ' ' . substr($d, 0, 4); }
function wkt(?string $d): string { return $d ? tgl(substr($d, 0, 10)) . ' ' . str_replace(':', '.', substr($d, 11, 8)) : '-'; }
function mask_none(string $n): string { return $n; } // [v2] nomor rekening tampil penuh
function pdf_css(): string {
  return ".avt{display:inline-flex;align-items:center;gap:6px}.avt i{font-style:normal;width:16px;height:16px;border-radius:50%;background:#1E5FB8;color:#fff;font-size:8.5px;font-weight:800;display:inline-flex;align-items:center;justify-content:center}table.pes tr.me td{background:#FFF4E8}table.pes tr.me .avt i{background:#C25A12}.tg{font-size:8px;font-weight:800;color:#C25A12;border:1px solid #F0C9A4;border-radius:99px;padding:0 5px;margin-left:5px}body{margin:0;background:#E5E7EB;font-family:'Plus Jakarta Sans',system-ui,sans-serif;color:#15213C}.pg{width:794px;min-height:1123px;box-sizing:border-box;background:#fff;padding:44px 48px 28px;display:flex;flex-direction:column;gap:10px;margin:12px auto;position:relative;overflow:hidden;page-break-after:always}
.hd{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:2px solid #1D3A78;padding-bottom:10px}.hd .co{font-size:12px;font-weight:800;color:#1D3A78}.hd .tt{font-size:19px;font-weight:800;margin-top:2px}.hd .no{font-size:12px;color:#47587A}.hd .pn{font-size:11px;font-weight:800;color:#47587A}
.sh{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:#1D3A78;margin:6px 0 2px}table{width:100%;border-collapse:collapse}td,th{padding:6px 10px;font-size:12px;border:1px solid #D5E0F2;line-height:17px;vertical-align:top;text-align:left}th{background:#F1F5FB;color:#47587A;font-size:11px}td.k{background:#F1F5FB;font-weight:800;width:190px;color:#47587A}td.r,th.r{text-align:right}.ft{margin-top:auto;display:flex;justify-content:space-between;font-size:10px;color:#47587A;border-top:1px solid #D5E0F2;padding-top:6px}.tx{font-size:12px;line-height:18px}.bad{color:#B91C1C;font-weight:800}
.wm{position:absolute;inset:0;pointer-events:none;display:flex;flex-wrap:wrap;align-content:space-around;justify-content:space-around;opacity:.05;font-weight:800;font-size:15px;color:#1D3A78;transform:rotate(-24deg) scale(1.35)}.wm span{padding:26px 30px;white-space:nowrap}
.img{max-width:100%;max-height:460px;border:1px solid #D5E0F2;border-radius:6px;display:block;margin:4px 0}.two{display:flex;gap:10px}.two>div{flex:1}.sig{display:flex;gap:8px}.sig>div{flex:1;border:1px solid #D5E0F2;border-radius:6px;padding:8px;min-height:92px;font-size:11px}.sig b{display:block;color:#47587A;margin-bottom:6px}
.tot{display:flex;justify-content:space-between;align-items:center;background:linear-gradient(90deg,#FFF4D6,#FFE3A8);border:2px solid #F0A400;border-radius:10px;padding:12px 16px}.tot em{display:block;font-style:italic;font-weight:700;font-size:12.5px;color:#10394E;margin-top:3px}.tot span{font-size:12px;font-weight:800;letter-spacing:.08em;color:#8A5A00}.tot b{font-size:24px;font-weight:800;color:#10394E}
.bar{display:flex;height:9px;border-radius:99px;overflow:hidden;margin:9px 0 7px;background:#ffffff99}.bar i{display:block}.tot .lg span{font-size:11.5px;font-weight:600;letter-spacing:0;color:#10394E}.tot .lg b{font-size:12px;font-weight:800;color:#10394E}.lg{display:flex;gap:16px;flex-wrap:wrap;font-size:11.5px}.lg span{display:flex;align-items:center;gap:6px}.lg u{width:10px;height:10px;border-radius:3px;display:inline-block}
.kv2{display:grid;grid-template-columns:1fr 1fr;gap:0 18px}.kv2>div{display:flex;gap:10px;padding:5px 0;border-bottom:1px solid #D5E0F2;font-size:12px}.kv2 span{color:#47587A;width:96px;flex:none}.kv2 b{font-weight:700}
.rek{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:6px;padding:9px 14px;border-radius:10px;background:#FFF4D6;border:1px solid #F3C473;border-left:5px solid #F0A400}.rek small{font-weight:800;letter-spacing:.06em;color:#7a5200;font-size:10.5px}.rek div{display:flex;gap:14px;align-items:baseline;text-align:right}.rek b{font-size:15px}.rek span{color:#7a5200;font-size:12px}
.pc{border:1px solid #D5E0F2;border-radius:12px;overflow:hidden;margin-bottom:9px;break-inside:avoid}.pch{display:flex;align-items:center;gap:8px;padding:8px 14px;font-size:13.5px}.pch .cnt{color:#47587A;font-size:11px;font-weight:600}.pch .amt{margin-left:auto;font-size:14.5px}.dotc{width:10px;height:10px;border-radius:99px;flex:none}
.chips{display:flex;flex-wrap:wrap;gap:6px;padding:8px 14px 2px}.chp{font-size:11px;background:#F3F6FB;border-radius:99px;padding:2px 10px}.chp i{font-style:normal;color:#47587A;margin-right:5px}
.nr{display:flex;gap:12px;padding:6px 14px;border-top:1px solid #D5E0F2;font-size:12px;line-height:17px}.nth{display:flex;gap:12px;padding:8px 14px 3px;font-size:9.5px;font-weight:800;letter-spacing:.05em;color:#47587A;text-transform:uppercase}.nn{width:20px;color:#47587A}.nt{width:92px;color:#47587A}.nk{flex:1}.na{width:100px;text-align:right;font-weight:700}
.gt2{display:flex;justify-content:space-between;align-items:center;padding:11px 16px;border-radius:12px;background:#10394E;color:#fff;font-weight:800;margin-top:2px}.gt2 small{font-weight:500;opacity:.8;margin-left:8px}.gt2 b{font-size:18px}
.gh{display:flex;align-items:center;gap:8px;padding:8px 14px;border-radius:10px;color:#fff;font-weight:800;font-size:13.5px}.gh small{margin-left:auto;font-weight:600;opacity:.92}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:8px}.ph1{border:1px solid #D5E0F2;border-radius:10px;overflow:hidden;break-inside:avoid}.ph1 .im{height:300px;background:#F6F8FC;display:grid;place-items:center}.ph1 img{max-width:100%;max-height:300px;object-fit:contain;display:block}.ph1 p{margin:0;padding:6px 10px;font-size:11.5px;line-height:16px}.ph1 p b{display:block}
@media print{.pc,.pch,.gt2,.gh,.rek,.bar i,.dotc,.chp{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
@media print{body{background:#fff}.tot{-webkit-print-color-adjust:exact;print-color-adjust:exact}.pg{margin:0;box-shadow:none}.noprint{display:none}@page{size:A4;margin:0}}";
}
function wm_html(array $u): string {
  $t = e($u['name'] . ' · ' . date('d/m/Y H.i.s')); return '<div class="wm">' . str_repeat("<span>$t</span>", 9) . '</div>';
}
function pdf_shell(string $title, array $pages, array $u, string $co, string $no, string $stamp): string {
  $n = count($pages); $h = '';
  foreach ($pages as $i => $p) {
    $h .= '<div class="pg">' . wm_html($u) . '<div class="hd"><div><div class="co">' . e($co) . '</div><div class="tt">' . e($title) . '</div><div class="no">Nomor: ' . e($no) . '</div></div><span class="pn">Hal ' . ($i + 1) . '</span></div>' . $p . '<div class="ft"><span>Dicetak ' . e($stamp) . ' oleh ' . e($u['name']) . ' · isi sama untuk semua peran</span><span>Halaman ' . ($i + 1) . ' dari ' . $n . '</span></div></div>';
  }
  return '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>' . e($title . ' ' . $no) . '</title><meta name="viewport" content="width=820"><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"><style>' . pdf_css() . '</style></head><body>' . '<div class="noprint" style="text-align:center;padding:10px"><button onclick="window.print()" style="padding:12px 22px;border:0;border-radius:12px;background:#1D4ED8;color:#fff;font-weight:800;font-size:14px">CETAK / SIMPAN PDF</button></div>' . $h . '</body></html>';
}
/** Riwayat proses (BA di-highlight) + log unduhan dipisah supaya tidak tercampur. */
function history_html(array $hist): string {
  $dl = array_filter($hist, fn($h) => (bool) preg_match('/diunduh$/', $h['action'])); $main = array_filter($hist, fn($h) => !preg_match('/diunduh$/', $h['action']));
  $h = '<div class="sh">Riwayat proses (tambah-saja, sampai detik)</div><table><tr><th>Waktu</th><th>User</th><th>Jabatan</th><th>Aksi</th><th>Keterangan</th></tr>';
  $jobs = []; $jobOf = function ($x) use (&$jobs) { $id = (int) ($x['actor_id'] ?? 0); if (!$id) return '-'; return $jobs[$id] ??= (val("SELECT job FROM claim_users WHERE id=?", [$id]) ?: '-'); };
  foreach ($main as $x) {
    $ba = (bool) preg_match('/\bBA\b/', $x['action']); $kt = $x['before_val'] && $x['after_val'] ? e($x['before_val']) . ' → ' . e($x['after_val']) : e($x['after_val'] ?: ($x['before_val'] ?: ''));
    $h .= '<tr' . ($ba ? ' style="background:#FFF1E3"' : '') . '><td>' . wkt($x['created_at']) . '</td><td>' . e($x['actor_name']) . '</td><td>' . e($jobOf($x)) . '</td><td>' . ($ba ? '<b style="color:#C25A12">' . e($x['action']) . '</b>' : e($x['action'])) . '</td><td>' . $kt . '</td></tr>';
  }
  $h .= '</table>';
  if ($dl) { $h .= '<div class="sh">Log unduhan (' . count($dl) . ')</div><table><tr><th>Waktu</th><th>Dokumen</th><th>Diunduh oleh</th></tr>'; foreach ($dl as $x) $h .= '<tr><td>' . wkt($x['created_at']) . '</td><td>' . e(str_replace(' diunduh', '', $x['action'])) . '</td><td>' . e($x['actor_name']) . '</td></tr>'; $h .= '</table>'; }
  return $h;
}
function kv_rows(array $rows): string { $h = '<table>'; foreach ($rows as [$k, $v]) $h .= '<tr><td class="k">' . e($k) . '</td><td>' . $v . '</td></tr>'; return $h . '</table>'; }

/** Ketarangan satu nota (sama untuk PDF biasa dan multi-jenis). */
function nota_ket(array $n, string $dflt, bool $withType = false): string {
  $det = $n['detail']; $ket = $det['komponen'] ?? $dflt;
  if (!empty($det['jenis'])) $ket .= ' · ' . $det['jenis'];
  if (!empty($det['bbm'])) $ket = e($det['bbm']) . (isset($det['liter']) ? ' · ' . str_replace('.', ',', (string) $det['liter']) . ' L' : '') . ' · nota ' . rp((int) ($det['nota_nominal'] ?? $n['nominal']));
  elseif (!empty($det['asal'])) $ket = e($dflt) . ' · ' . e($det['asal']) . ' → ' . e($det['tujuan']);
  else $ket = e($ket);
  return $ket;
}
function part_chips(string $kind, array $m): array {
  $c = []; $nf = fn($x) => number_format((int) $x, 0, ',', '.');
  switch ($kind) {
    case 'bbm': $c[] = ['Kendaraan', trim(($m['kendaraan'] ?? '-') . ' · ' . ($m['plat'] ?? ''), ' ·')]; $c[] = ['KM', $nf($m['km_awal'] ?? 0) . ' → ' . $nf($m['km_akhir'] ?? 0)]; $c[] = ['Jarak', max(0, (int) ($m['km_akhir'] ?? 0) - (int) ($m['km_awal'] ?? 0)) . ' km']; break;
    case 'meeting': $c[] = ['Tujuan', $m['tujuan'] ?? '']; $c[] = ['Tempat', $m['tempat'] ?? '']; $c[] = ['Jam', ($m['jam_mulai'] ?? '') . '–' . ($m['jam_selesai'] ?? '')]; break;
    case 'party': if (!empty($m['penerima'])) $c[] = ['Dirayakan', $m['penerima']]; $c[] = ['Tempat', $m['tempat'] ?? '']; $c[] = ['Keterangan', $m['keterangan'] ?? '']; break;
    case 'tiket': $c[] = ['Moda', trim(($m['moda'] ?? '') . ' · ' . ($m['operator'] ?? ''), ' ·')]; $c[] = ['Rute', ($m['asal'] ?? '') . ' → ' . ($m['tujuan_kota'] ?? '')]; $c[] = ['Berangkat', tgl($m['tgl_berangkat'] ?? null) . (!empty($m['tgl_pulang']) ? ' s/d ' . tgl($m['tgl_pulang']) : '')]; $c[] = ['Penumpang', $m['penumpang'] ?? '']; if (!empty($m['keterangan'])) $c[] = ['Tujuan', $m['keterangan']]; break;
    case 'lain': $c[] = ['Judul', $m['judul'] ?? '']; if (!empty($m['keterangan'])) $c[] = ['Alasan', $m['keterangan']]; foreach (($m['rincian'] ?? []) as $r) $c[] = [$r['uraian'] ?? '', (int) ($r['qty'] ?? 0) . ' × ' . rp((int) ($r['harga'] ?? 0))]; break;
    case 'ent': if (!empty($m['penerima'])) $c[] = ['Penerima', $m['penerima']]; if (!empty($m['tempat'])) $c[] = ['Tempat', $m['tempat']]; if (!empty($m['keterangan'])) $c[] = ['Keterangan', $m['keterangan']]; break;
    case 'trans': case 'ops': if (!empty($m['keterangan'])) $c[] = ['Catatan', $m['keterangan']]; break;
    case 'kost': $c[] = ['Kost', ($m['nama_kost'] ?? '') . ' · ' . ($m['periode'] ?? '')]; if (!empty($m['pemilik'])) $c[] = ['Pemilik', $m['pemilik']]; break;
    case 'hotel': $c[] = ['Hotel', $m['nama_hotel'] ?? '']; $c[] = ['Menginap', ($m['checkin'] ?? '') . ' s/d ' . ($m['checkout'] ?? '')]; if (!empty($m['tujuan'])) $c[] = ['Tujuan', $m['tujuan']]; break;
  }
  return array_values(array_filter($c, fn($x) => trim((string) $x[1]) !== ''));
}
/** Daftar peserta (Meeting / Entertain): tabel bernomor 2 kolom, inisial bulat di samping nama, baris Pemohon diwarnai. Tanpa nominal. */
function peserta_block(string $kind, array $m, string $label = '', string $who = '', string $whoJob = ''): string {
  if (!in_array($kind, ['meeting', 'party'], true)) return '';
  $rows = []; foreach (array_values(array_filter($m['peserta'] ?? [], fn($x) => trim((string) $x) !== '')) as $i => $x) { [$nm, $jb] = array_pad(explode(' — ', (string) $x, 2), 2, ''); $rows[] = [$i + 1, trim($nm), trim($jb)]; }
  if (!$rows) return '';
  $n = count($rows); $half = (int) ceil($n / 2);
  $tb = function (array $r) use ($who, $whoJob) { $h = '<table class="pes"><tr><th style="width:24px">NO</th><th>NAMA</th><th>JABATAN</th></tr>';
    foreach ($r as $x) { $me = ($who !== '' && mb_strtolower($x[1]) === mb_strtolower($who)) || preg_match('/pemohon/i', $x[2]);
      $h .= '<tr' . ($me ? ' class="me"' : '') . '><td>' . $x[0] . '</td><td><span class="avt"><i>' . e(mb_strtoupper(mb_substr($x[1], 0, 1))) . '</i>' . e($x[1]) . ($me ? '<span class="tg">PEMOHON</span>' : '') . '</span></td><td>' . e(preg_replace('/\s*\(?pemohon\)?$/i', '', $x[2]) ?: ($me ? ($whoJob ?: '-') : '-')) . '</td></tr>'; }
    return $h . '</table>'; };
  return '<div class="sh">Daftar peserta' . ($label ? ' · ' . e($label) : '') . ' <span style="float:right;font-weight:600">' . $n . ' orang</span></div>'
    . ($n > 3 ? '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 14px">' . $tb(array_slice($rows, 0, $half)) . $tb(array_slice($rows, $half)) . '</div>' : $tb($rows));
}
/** PDF klaim yang berisi beberapa jenis: ringkasan warna, kartu per jenis, lampiran 1 halaman = 1 jenis (maks 4 foto). */
function claim_pdf_multi_html(array $u, array $d): string {
  $c = $d['claim']; $tn = $d['type_label'];
  $pal = [['#E8710A', '#FDF0E3'], ['#2E6E9E', '#E6EEF6'], ['#12866A', '#E3F3EE'], ['#6D3FD8', '#EEE9FB'], ['#9A6400', '#F8EFD9'], ['#0E7490', '#E0F1F5']];
  $img = fn($f) => '<img src="api.php?a=file&id=' . (int) $f['id'] . '" alt="">';
  $firstId = (int) $d['parts'][0]['type']['id']; $total = max(1, (int) $c['total']); $by = []; $sub = [];
  foreach ($d['notes'] as $i => $n) { $tid = (int) ($n['detail']['type_id'] ?? 0); if (!isset($sub[$tid]) && !array_filter($d['parts'], fn($P) => (int) $P['type']['id'] === $tid)) $tid = $firstId; $by[$tid][] = [$i, $n]; $sub[$tid] = ($sub[$tid] ?? 0) + (int) $n['nominal']; }
  $bar = $lg = ''; foreach ($d['parts'] as $k => $P) { $t = (int) $P['type']['id']; $v = $sub[$t] ?? 0; $col = $pal[$k % count($pal)][0];
    $bar .= '<i style="width:' . round($v * 100 / $total, 1) . '%;background:' . $col . '"></i>'; $lg .= '<span><u style="background:' . $col . '"></u>' . e($P['type']['name']) . ' <b>' . rp($v) . '</b> · ' . round($v * 100 / $total) . '%</span>'; }
  $cl = $d['claimant'];
  $kv = [['Nomor / versi', e($c['number']) . ' · v' . (int) $c['current_version']], ['Kategori', e($c['category'])], ['Pemohon', e($cl['name']) . ' · ' . e($cl['job'])], ['Brand / Refnote', e($c['brand']) . ' / ' . e($c['refnote'])], ['Region', e($cl['region'])], ['Badan usaha', e($c['entity'] ?: $cl['entity'])], ['Lokasi', e($c['location'])]];
  if (!empty($d['meta']['catatan'])) $kv[] = ['Keterangan', nl2br(e($d['meta']['catatan']))];
  if ($d['ba']) $kv[] = ['Terkait BA', e($d['ba']['number'])];
  $p1 = '<div class="tx">Pengajuan klaim ' . count($d['parts']) . ' jenis (' . e($tn) . ') dalam satu klaim, diajukan melalui aplikasi DSM Klaim pada ' . tgl(substr((string) $c['submitted_at'], 0, 10)) . '.</div>'
    . '<div class="tot" style="flex-direction:column;align-items:stretch"><div style="display:flex;justify-content:space-between;align-items:center"><div><span>TOTAL NOMINAL DIPROSES</span><em>' . e(terbilang((int) $c['total'])) . '</em></div><b>' . rp((int) $c['total']) . '</b></div>'
    . '<div class="bar">' . $bar . '</div><div class="lg">' . $lg . '</div></div><div class="sh">Data pengajuan</div><div class="kv2">' . implode('', array_map(fn($r) => '<div><span>' . e($r[0]) . '</span><b>' . $r[1] . '</b></div>', $kv)) . '</div>'
    . '<div class="rek"><small>REKENING TUJUAN</small><div><b>' . e($c['acct_bank']) . ' · ' . e(mask_none((string) $c['acct_no'])) . '</b><span>a.n. ' . e($c['acct_name']) . ($c['acct_own'] ? '' : ' · BUKAN rekening pemohon: ' . e($c['acct_reason'])) . '</span></div></div>'
    . '<div class="sh">Rincian per jenis</div>';
  foreach ($d['parts'] as $k => $P) { $t = (int) $P['type']['id']; [$col, $tint] = $pal[$k % count($pal)]; $nn = $by[$t] ?? [];
    $ch = implode('', array_map(fn($x) => '<span class="chp"><i>' . e($x[0]) . '</i>' . e($x[1]) . '</span>', part_chips($P['kind'], (array) $P['meta'])));
    $p1 .= '<div class="pc"><div class="pch" style="background:' . $tint . '"><span class="dotc" style="background:' . $col . '"></span><b style="color:' . $col . '">' . e($P['type']['name']) . '</b><span class="cnt">' . count($nn) . ' nota</span><b class="amt" style="color:' . $col . '">' . rp($sub[$t] ?? 0) . '</b></div>'
      . ($ch ? '<div class="chips">' . $ch . '</div>' : '') . '<div style="padding:0 10px">' . peserta_block($P['kind'], (array) $P['meta'], '', (string) $d['claimant']['name'], (string) ($d['claimant']['job'] ?? '')) . '</div><div class="nth"><span class="nn">No</span><span class="nt">Tanggal</span><span class="nk">Keterangan</span><span class="na">Diklaim</span></div>';
    foreach ($nn as [$i, $n]) $p1 .= '<div class="nr"><span class="nn">' . ($i + 1) . '</span><span class="nt">' . tgl($n['nota_date']) . '</span><span class="nk">' . nota_ket($n, $P['type']['name']) . '</span><span class="na">' . rp((int) $n['nominal']) . '</span></div>';
    $p1 .= '</div>'; }
  $p1 .= '<div class="gt2"><span>TOTAL DIKLAIM<small>' . count($d['parts']) . ' jenis · ' . count($d['notes']) . ' nota</small></span><b>' . rp((int) $c['total']) . '</b></div>';
  // halaman 2: budget + persetujuan
  $p2 = '';
  if ($d['budget']) { $b = $d['budget']; $before = (int) ($c['avail_before'] ?? $b['available']);
    $p2 .= '<div class="sh">Dampak budget ' . e($c['category']) . '</div>' . kv_rows([['Sisa sebelum klaim', rp($before)], ['Klaim ini', rp((int) $c['total'])], ['Sisa jika selesai', rp($before - (int) $c['total'])], ['Over budget', (int) $c['over_budget_amount'] > 0 ? '<span class="bad">' . rp((int) $c['over_budget_amount']) . '</span>' : '-']]); }
  if ($d['ba']) $p2 .= '<div class="sh">Pemicu Berita Acara</div><div class="tx">' . e(implode('; ', $d['ba']['triggers'])) . '. ' . e($d['ba']['number']) . ' dibuat oleh ' . e($d['ba']['creator']) . ' (' . ($d['ba']['creator_role'] === 'PEMOHON' ? 'Pemohon' : 'Admin') . ').</div>';
  $p2 .= '<div class="sh">Jejak persetujuan</div><table><tr><th>Tahap</th><th>User</th><th>Jabatan</th><th>Waktu (WIB)</th><th>Keputusan</th></tr><tr><td>Pemohon</td><td>' . e($cl['name']) . '</td><td>' . e($cl['job']) . '</td><td>' . wkt($c['submitted_at']) . '</td><td>Mengajukan</td></tr>';
  foreach ($d['approvals'] as $a) $p2 .= '<tr><td>' . e(STAGE_NAME[$a['stage']] ?? $a['stage']) . '</td><td>' . e($a['uname']) . '</td><td>' . e($a['ujob'] ?? '') . '</td><td>' . wkt($a['created_at']) . '</td><td>' . e(['approve' => 'Setuju', 'revise' => 'Minta revisi', 'reject' => 'Tolak', 'transfer' => 'Transfer ' . $a['notes']][$a['action']] ?? $a['action']) . ($a['valid'] || $a['action'] !== 'approve' ? '' : ' <i>(versi lama, tidak berlaku)</i>') . '</td></tr>';
  $p2 .= '</table>' . history_html($d['history']);
  $cat = ''; foreach ($d['approvals'] as $a) if ($a['notes'] && $a['action'] !== 'transfer') $cat .= '<tr><td>' . e(STAGE_NAME[$a['stage']] ?? $a['stage']) . '</td><td>' . e($a['notes']) . '</td></tr>';
  if ($cat) $p2 .= '<div class="sh">Catatan penyetuju</div><table><tr><th>Peran</th><th>Catatan</th></tr>' . $cat . '</table>';
  $p2 .= '<div class="tx">Seluruh catatan terlihat oleh semua peran. Nominal yang berubah setelah BA ditandatangani membatalkan BA dan mengulang tanda tangan.</div>';
  $pages = [$p1, $p2];
  // lampiran: 1 halaman = 1 jenis, maksimal 4 foto per halaman
  $pn = count($d['parts']);
  foreach ($d['parts'] as $k => $P) { $t = (int) $P['type']['id']; [$col] = $pal[$k % count($pal)]; $items = [];
    if ($P['kind'] === 'bbm') { $m = (array) $P['meta'];
      foreach ($d['files']['odo_awal'] as $f) $items[] = [$img($f), 'Odometer awal', number_format((int) ($m['km_awal'] ?? 0), 0, ',', '.') . ' km'];
      foreach ($d['files']['odo_akhir'] as $f) $items[] = [$img($f), 'Odometer akhir', number_format((int) ($m['km_akhir'] ?? 0), 0, ',', '.') . ' km']; }
    foreach ($by[$t] ?? [] as [$i, $n]) foreach ($n['files'] as $f) $items[] = [$img($f), 'Nota ' . ($i + 1), rp((int) $n['nominal']) . (!empty($n['detail']['asal']) ? ' · ' . e($n['detail']['asal']) . ' → ' . e($n['detail']['tujuan']) : '')];
    if ($k === $pn - 1) { foreach (['doc' => 'Dokumentasi', 'maps' => 'Google Maps rute'] as $s => $l) foreach ($d['files'][$s] as $f) $items[] = [$img($f), $l, '']; }
    foreach (array_chunk($items, 4) as $ci => $chunk) {
      $pg = '<div class="sh" style="margin-top:0">Lampiran · jenis ' . ($k + 1) . ' dari ' . $pn . '</div><div class="gh" style="background:' . $col . '">' . e($P['type']['name']) . ($ci ? ' (lanjutan)' : '') . '<small>' . count($items) . ' foto · ' . rp($sub[$t] ?? 0) . '</small></div><div class="g2">';
      foreach ($chunk as $it) $pg .= '<div class="ph1"><div class="im">' . $it[0] . '</div><p><b>' . e($it[1]) . '</b>' . $it[2] . '</p></div>';
      $pages[] = $pg . '</div>'; } }
  if ($c['status'] === 'DONE' && $d['transfer']) {
    $t = $d['transfer'];
    $p4 = '<div class="sh">Bukti transfer</div><div class="tot"><div><span>DANA SUDAH DITRANSFER</span><em>' . e(terbilang((int) $t['amount'])) . '</em></div><b>' . rp((int) $t['amount']) . '</b></div>'
      . kv_rows([['Nomor transfer', e($t['transfer_no'])], ['Waktu transfer (WIB)', wkt($t['created_at'])], ['Dicatat oleh', e($t['uname']) . ' · Finance Transfer'], ['Rekening tujuan', e($c['acct_bank']) . ' ' . e($c['acct_no']) . ' a.n. ' . e($c['acct_name'])]]);
    foreach ($d['files']['transfer'] as $f) $p4 .= '<div style="max-width:100%">' . str_replace('<img ', '<img class="img" ', $img($f)) . '</div>';
    $pages[] = $p4;
  }
  return pdf_shell('Lembar Klaim ' . $tn, $pages, $u, ($c['entity'] ?: $cl['entity']) ?: 'DSM', (string) $c['number'], date('d M Y H.i'));
}
function claim_pdf_html(array $u, int $id): string {
  $d = claim_detail($u, $id); if (count($d['parts']) > 1) return claim_pdf_multi_html($u, $d);
  $c = $d['claim']; $kind = $d['kind']; $m = $d['meta']; $tn = $d['type_label'];
  $img = fn($f) => '<img class="img" src="api.php?a=file&id=' . (int) $f['id'] . '" alt="">';
  $rows = [['Nomor / versi', e($c['number']) . ' · versi ' . (int) $c['current_version']],
    ['Pemohon', e($d['claimant']['name']) . ' (' . e($d['claimant']['job']) . ') · Region ' . e($d['claimant']['region']) . ' · ' . e($d['claimant']['entity'])],
    ['Kategori / jenis', e($c['category']) . ' / ' . e($tn)], ['Brand / Refnote', e($c['brand']) . ' / ' . e($c['refnote'])]];
  if (!in_array($kind, ['perdin', 'makan'], true)) $rows[] = ['Lokasi', e($c['location'])];
  if (!empty($m['catatan'])) $rows[] = ['Keterangan tambahan', nl2br(e($m['catatan']))];
  $multi = count($d['parts']) > 1; $tname = [];
  foreach ($d['parts'] as $P) { $tname[$P['type']['id']] = $P['type']['name']; }
  foreach ($d['parts'] as $P) { $kind = $P['kind']; $m = (array) $P['meta'];
  if ($multi) $rows[] = ['<b>Bagian ' . e($P['type']['name']) . '</b>', 'Subtotal ' . rp(array_sum(array_map(fn($n) => (int) (($n['detail']['type_id'] ?? 0) == $P['type']['id'] ? $n['nominal'] : 0), $d['notes'])))];
  switch ($kind) {
    case 'bbm': $rows[] = ['Kendaraan · Plat · KM awal → akhir', e(($m['kendaraan'] ?? '-') . ' · ' . ($m['plat'] ?? '')) . ' · ' . number_format((int) ($m['km_awal'] ?? 0), 0, ',', '.') . ' → ' . number_format((int) ($m['km_akhir'] ?? 0), 0, ',', '.') . ' (' . max(0, (int) ($m['km_akhir'] ?? 0) - (int) ($m['km_awal'] ?? 0)) . ' km)']; break;
    case 'meeting': if (!empty($m['tempat'])) $rows[] = ['Tempat', e($m['tempat'])]; $rows[] = ['Tujuan · jam', e($m['tujuan'] ?? '') . ' · ' . e($m['jam_mulai'] ?? '') . '–' . e($m['jam_selesai'] ?? '')]; break;
    case 'party': if (!empty($m['penerima'])) $rows[] = ['Yang dirayakan', e($m['penerima'])]; if (!empty($m['tempat'])) $rows[] = ['Tempat', e($m['tempat'])]; $rows[] = ['Keterangan', e($m['keterangan'] ?? '')]; break;
    case 'tiket': $rows[] = ['Moda · operator', e($m['moda'] ?? '') . ' · ' . e($m['operator'] ?? '')]; $rows[] = ['Rute · tanggal', e($m['asal'] ?? '') . ' → ' . e($m['tujuan_kota'] ?? '') . ' · ' . tgl($m['tgl_berangkat'] ?? null) . (!empty($m['tgl_pulang']) ? ' s/d ' . tgl($m['tgl_pulang']) : '')]; $rows[] = ['Penumpang', e($m['penumpang'] ?? '')]; $rows[] = ['Tujuan perjalanan', e($m['keterangan'] ?? '')]; break;
    case 'lain': $rows[] = ['Judul', e($m['judul'] ?? '')]; $rows[] = ['Kebutuhan / alasan', nl2br(e($m['keterangan'] ?? ''))]; $rows[] = ['Rincian', implode('<br>', array_map(fn($r) => e($r['uraian'] ?? '') . ' · ' . (int) ($r['qty'] ?? 0) . ' × ' . rp((int) ($r['harga'] ?? 0)) . ' = ' . rp((int) ($r['qty'] ?? 0) * (int) ($r['harga'] ?? 0)), $m['rincian'] ?? []))]; break;
    case 'ent': $rows[] = ['Penerima', e($m['penerima'] ?? '')]; if (!empty($m['tempat'])) $rows[] = ['Tempat', e($m['tempat'])]; $rows[] = ['Keterangan', e($m['keterangan'] ?? '')]; break;
    case 'trans': case 'ops': $rows[] = ['Keterangan', e($m['keterangan'] ?? '')]; break;
    case 'kost': $rows[] = ['Nama kost · periode', e($m['nama_kost'] ?? '') . ' · ' . e($m['periode'] ?? '')]; $rows[] = ['Pemilik', e($m['pemilik'] ?? '')]; break;
    case 'hotel': $rows[] = ['Hotel', e($m['nama_hotel'] ?? '') . ' · ' . e($m['checkin'] ?? '') . ' s/d ' . e($m['checkout'] ?? '')]; $rows[] = ['Tujuan menginap', e($m['tujuan'] ?? '')]; break;
    case 'makan': case 'perdin':
      $dur = duration_days($m['tgl_berangkat'] ?? null, $m['tgl_pulang'] ?? null);
      $rows[] = ['Tanggal · durasi', tgl($m['tgl_berangkat'] ?? null) . ' s/d ' . tgl($m['tgl_pulang'] ?? null) . ' (' . $dur . ' hari)']; $rows[] = ['Tujuan perjalanan', e($m['tujuan'] ?? '')];
      $rows[] = ['Rute', e(implode(' → ', $m['rute'] ?? [])) . ' · ' . e($m['km_total'] ?? 0) . ' km (Google Maps)']; break;
  }
  }
  $kind = $d['kind']; $hasBbm = in_array('bbm', array_column($d['parts'], 'kind'), true);
  if ($d['ba']) $rows[] = ['Terkait BA', e($d['ba']['number'])];
  $p1 = '<div class="tx">Pengajuan klaim ' . e($tn) . ' berikut diajukan melalui aplikasi DSM Klaim pada ' . tgl(substr((string) $c['submitted_at'], 0, 10)) . '.</div><div class="tot"><div><span>TOTAL NOMINAL DIPROSES</span><em>' . e(terbilang((int) $c['total'])) . '</em></div><b>' . rp((int) $c['total']) . '</b></div><div class="sh">Data pengajuan</div>' . kv_rows($rows) . implode('', array_map(fn($P) => peserta_block($P['kind'], (array) $P['meta'], count($d['parts']) > 1 ? $P['type']['name'] : '', (string) $d['claimant']['name'], (string) ($d['claimant']['job'] ?? '')), $d['parts']));
  $p1 .= '<div class="sh">Rincian nota</div><table><tr><th>No</th><th>Tanggal nota</th><th>Keterangan</th><th class="r">Diklaim</th></tr>';
  foreach ($d['notes'] as $i => $n) {
    $det = $n['detail']; $ket = $det['komponen'] ?? ($multi ? ($tname[$det['type_id'] ?? 0] ?? $tn) : $tn);
    if (!empty($det['jenis'])) $ket .= ' · ' . $det['jenis']; if (!empty($det['mode'])) $ket .= ' (' . ($det['mode'] === 'hari' ? 'per hari ' . rp((int) ($det['per_hari'] ?? 0)) . ' × ' . (int) ($det['hari'] ?? 0) . ' hari' : 'sekali') . ')'; elseif (($det['komponen'] ?? '') === 'Uang makan') $ket .= ' · ' . rp((int) ($det['tarif'] ?? 0)) . ' × ' . (int) ($det['hari'] ?? 0) . ' hari';
    if (!empty($det['bbm'])) { $ket = ($multi ? 'BBM · ' : '') . e($det['bbm']) . (isset($det['liter']) ? ' · ' . str_replace('.', ',', (string) $det['liter']) . ' L' : '') . ' · nota ' . rp((int) ($det['nota_nominal'] ?? $n['nominal'])); }
    if (!empty($det['asal'])) $ket .= ' · ' . e($det['asal']) . ' → ' . e($det['tujuan']);
    $p1 .= '<tr><td>' . ($i + 1) . '</td><td>' . tgl($n['nota_date']) . '</td><td>' . $ket . '</td><td class="r">' . rp((int) $n['nominal']) . '</td></tr>';
  }
  $p1 .= '<tr style="background:#FFF4D6"><td></td><td></td><td><b>Total ' . count($d['notes']) . ' nota</b></td><td class="r" style="font-size:15px"><b>' . rp((int) $c['total']) . '</b></td></tr></table>';
  if ($d['budget']) { $b = $d['budget']; $before = (int) ($c['avail_before'] ?? $b['available']);
    $p1 .= '<div class="sh">Dampak budget ' . e($c['category']) . '</div>' . kv_rows([['Sisa sebelum klaim', rp($before)], ['Klaim ini', rp((int) $c['total'])], ['Sisa jika selesai', rp($before - (int) $c['total'])], ['Over budget', (int) $c['over_budget_amount'] > 0 ? '<span class="bad">' . rp((int) $c['over_budget_amount']) . '</span>' : '-']]); }
  $p1 .= '<div class="sh">Rekening tujuan</div>' . kv_rows([['Rekening tujuan', e($c['acct_bank']) . ' ' . e(mask_none((string) $c['acct_no'])) . ' a.n. ' . e($c['acct_name']) . ($c['acct_own'] ? ' (rekening pemohon)' : ' (BUKAN rekening pemohon: ' . e($c['acct_reason']) . ')')]]);
  if ($d['ba']) $p1 .= '<div class="sh">Pemicu Berita Acara</div><div class="tx">' . e(implode('; ', $d['ba']['triggers'])) . '. ' . e($d['ba']['number']) . ' dibuat oleh ' . e($d['ba']['creator']) . ' (' . ($d['ba']['creator_role'] === 'PEMOHON' ? 'Pemohon' : 'Admin') . ').</div>';
  // halaman 2
  $p2 = '<div class="sh">Jejak persetujuan</div><table><tr><th>Tahap</th><th>User</th><th>Jabatan</th><th>Waktu (WIB)</th><th>Keputusan</th></tr><tr><td>Pemohon</td><td>' . e($d['claimant']['name']) . '</td><td>' . e($d['claimant']['job']) . '</td><td>' . wkt($c['submitted_at']) . '</td><td>Mengajukan</td></tr>';
  foreach ($d['approvals'] as $a) $p2 .= '<tr><td>' . e(STAGE_NAME[$a['stage']] ?? $a['stage']) . '</td><td>' . e($a['uname']) . '</td><td>' . e($a['ujob'] ?? '') . '</td><td>' . wkt($a['created_at']) . '</td><td>' . e(['approve' => 'Setuju', 'revise' => 'Minta revisi', 'reject' => 'Tolak', 'transfer' => 'Transfer ' . $a['notes']][$a['action']] ?? $a['action']) . ($a['valid'] || $a['action'] !== 'approve' ? '' : ' <i>(versi lama, tidak berlaku)</i>') . '</td></tr>';
  $p2 .= '</table>' . history_html($d['history']);
  $cat = ''; foreach ($d['approvals'] as $a) if ($a['notes'] && $a['action'] !== 'transfer') $cat .= '<tr><td>' . e(STAGE_NAME[$a['stage']] ?? $a['stage']) . '</td><td>' . e($a['notes']) . '</td></tr>';
  if ($cat) $p2 .= '<div class="sh">Catatan penyetuju</div><table><tr><th>Peran</th><th>Catatan</th></tr>' . $cat . '</table>';
  $p2 .= '<div class="tx">Seluruh catatan terlihat oleh semua peran. Nominal yang berubah setelah BA ditandatangani membatalkan BA dan mengulang tanda tangan.</div>';
  // halaman 3: lampiran
  $p3 = '<div class="sh">Lampiran</div>';
  if ($hasBbm) $p3 .= '<div class="two"><div><div class="tx"><b>Odometer awal</b></div>' . implode('', array_map($img, $d['files']['odo_awal'])) . '</div><div><div class="tx"><b>Odometer akhir</b></div>' . implode('', array_map($img, $d['files']['odo_akhir'])) . '</div></div>';
  foreach (['doc' => 'Dokumentasi', 'maps' => 'Google Maps rute'] as $s => $l) if ($d['files'][$s]) $p3 .= '<div class="tx"><b>' . $l . '</b></div>' . implode('', array_map($img, $d['files'][$s]));
  foreach ($d['notes'] as $i => $n) { if (!$n['files']) continue; $det = $n['detail'];
    $p3 .= '<div class="tx"><b>Nota ' . ($i + 1) . '</b> · ' . rp((int) $n['nominal']) . (!empty($det['asal']) ? ' · Rute tol: ' . e($det['asal']) . ' → ' . e($det['tujuan']) : '') . '</div>' . implode('', array_map($img, $n['files'])); }
  $pages = [$p1, $p2, $p3];
  if ($c['status'] === 'DONE' && $d['transfer']) {
    $t = $d['transfer'];
    $p4 = '<div class="sh">Bukti transfer</div><div class="tot"><div><span>DANA SUDAH DITRANSFER</span><em>' . e(terbilang((int) $t['amount'])) . '</em></div><b>' . rp((int) $t['amount']) . '</b></div>'
      . kv_rows([['Nomor transfer', e($t['transfer_no'])], ['Waktu transfer (WIB)', wkt($t['created_at'])], ['Dicatat oleh', e($t['uname']) . ' · Finance Transfer'], ['Rekening tujuan', e($c['acct_bank']) . ' ' . e($c['acct_no']) . ' a.n. ' . e($c['acct_name'])]]);
    foreach ($d['files']['transfer'] as $f) $p4 .= $img($f);
    $pages[] = $p4;
  }
  return pdf_shell('Lembar Klaim ' . $tn, $pages, $u, ($d['claim']['entity'] ?: $d['claimant']['entity']) ?: 'DSM', (string) $c['number'], date('d M Y H.i'));
}
/** Bagian BA: nota hilang (dengan persen nominal tanpa nota) dan temuan berkategori. */
function ba_extra_html(array $d, array $ba): string {
  $x = $ba['extra'] ?? null; if (!$x) return ''; $h = ''; $tot = max(1, (int) $d['claim']['total']);
  $nm = []; foreach ($d['parts'] as $P) $nm[(int) $P['type']['id']] = $P['type']['name'];
  $label = fn($n) => nota_ket($n, $nm[(int) ($n['detail']['type_id'] ?? 0)] ?? $d['type_label']);
  if (!empty($x['lost'])) {
    $h .= '<div class="sh">4. Nota hilang</div><table><tr><th style="width:40px">No</th><th>Keterangan</th><th class="r">Nominal</th></tr>'; $sum = 0;
    foreach ($d['notes'] as $n) if (in_array((int) $n['line_no'], $x['lost'], true)) { $sum += (int) $n['nominal']; $h .= '<tr><td>' . (int) $n['line_no'] . '</td><td>' . $label($n) . '</td><td class="r">' . rp((int) $n['nominal']) . '</td></tr>'; }
    $h .= '<tr style="background:#FFF4D6"><td></td><td><b>Tanpa nota fisik</b></td><td class="r"><b>' . rp($sum) . '</b></td></tr></table>'
      . '<div class="tx" style="margin-top:4px"><b>' . round($sum * 100 / $tot) . '%</b> dari total klaim (' . rp($sum) . ' dari ' . rp((int) $d['claim']['total']) . ') tidak memiliki nota fisik. Bukti pengganti: lihat lampiran BA.</div>';
  }
  if (!empty($x['kategori'])) {
    $rel = array_filter($d['notes'], fn($n) => in_array((int) $n['line_no'], $x['related'] ?? [], true));
    $h .= '<div class="sh">' . (empty($x['lost']) ? '4' : '5') . '. Temuan</div><table><tr><th>Kategori</th><th>Nota terkait</th></tr><tr><td>' . e(TEMUAN_KAT[$x['kategori']] ?? $x['kategori']) . '</td><td>'
      . ($rel ? implode('<br>', array_map(fn($n) => 'Nota ' . (int) $n['line_no'] . ' · ' . tgl($n['nota_date']) . ' · ' . $label($n) . ' · ' . rp((int) $n['nominal']), $rel)) : '-') . '</td></tr></table>';
  }
  return $h;
}

/** Isi BA gaya surat: kalimat pembuka, satu bagian bernomor per pemicu (tabel otomatis + penjelasan), lalu penutup oleh pemanggil. */
function ba_letter_html(array $d, array $ba): string {
  $x = $ba['extra'] ?? null; $ex = is_array($x) ? ($x['expl'] ?? []) : [];
  if (!$ex) return '<div class="sh">3. Penjelasan kejadian</div><div class="tx" style="border:1px solid #D5E0F2;border-radius:6px;padding:10px 12px;background:#FBFDFE">' . nl2br(e($ba['explanation'])) . '</div>' . ba_extra_html($d, $ba);
  $c = $d['claim']; $cl = $d['claimant']; $tot = max(1, (int) $c['total']);
  $nm = []; foreach ($d['parts'] as $P) $nm[(int) $P['type']['id']] = $P['type']['name'];
  $label = fn($n) => nota_ket($n, $nm[(int) ($n['detail']['type_id'] ?? 0)] ?? $d['type_label']);
  $h = '<div class="sh">3. Uraian</div><div class="tx">Dengan ini disampaikan bahwa pengajuan klaim <b>' . e($c['number']) . '</b> atas nama <b>' . e($cl['name']) . '</b> memerlukan Berita Acara karena hal-hal berikut.</div>';
  $i = 0; $box = 'border:1px solid #D5E0F2;border-radius:6px;padding:8px 12px;background:#FBFDFE;margin:4px 0 8px';
  foreach ($ba['trigger_codes'] as $cd) {
    if (!isset($ex[$cd])) continue; $i++;
    $h .= '<div class="sh" style="font-size:12.5px">3.' . $i . ' ' . e(TRIG[$cd] ?? $cd) . '</div>';
    if ($cd === 'DIGIT_BBM' && !empty($x['digit'])) {
      $h .= '<table><tr><th>Tanggal</th><th class="r">Jumlah</th><th class="r">Seharusnya</th><th class="r">Klaim</th></tr>';
      foreach ($x['digit'] as $r) $h .= '<tr><td>' . tgl($r['date']) . '</td><td class="r">' . rp((int) $r['jumlah']) . '</td><td class="r">' . rp((int) $r['seharusnya']) . '</td><td class="r">' . rp((int) $r['klaim']) . '</td></tr>';
      $h .= '</table>';
    }
    if ($cd === 'NOTA_HARI' && !empty($x['hari'])) {
      $h .= '<table><tr><th>Nota</th><th>Tanggal</th><th>Hari</th><th class="r">Nominal</th></tr>';
      foreach ($x['hari'] as $r) $h .= '<tr><td>' . (int) $r['line'] . '</td><td>' . tgl($r['date']) . '</td><td>' . e($r['hari']) . '</td><td class="r">' . rp((int) $r['nominal']) . '</td></tr>';
      $h .= '</table>';
    }
    if ($cd === 'KEHILANGAN_NOTA' && !empty($x['lost'])) {
      $h .= '<table><tr><th style="width:40px">No</th><th>Keterangan</th><th class="r">Nominal</th></tr>'; $sum = 0;
      foreach ($d['notes'] as $n) if (in_array((int) $n['line_no'], $x['lost'], true)) { $sum += (int) $n['nominal']; $h .= '<tr><td>' . (int) $n['line_no'] . '</td><td>' . $label($n) . '</td><td class="r">' . rp((int) $n['nominal']) . '</td></tr>'; }
      $h .= '<tr style="background:#FFF4D6"><td></td><td><b>Tanpa nota fisik</b></td><td class="r"><b>' . rp($sum) . '</b></td></tr></table><div class="tx" style="margin-top:4px"><b>' . round($sum * 100 / $tot) . '%</b> dari total klaim tidak memiliki nota fisik. Bukti pengganti: lihat lampiran BA.</div>';
    }
    if (in_array($cd, ['TEMUAN_ADMIN', 'TEMUAN_LAIN'], true) && !empty($x['kategori'])) {
      $rel = array_filter($d['notes'], fn($n) => in_array((int) $n['line_no'], $x['related'] ?? [], true));
      $h .= '<table><tr><th>Kategori</th><th>Nota terkait</th></tr><tr><td>' . e(TEMUAN_KAT[$x['kategori']] ?? $x['kategori']) . '</td><td>' . ($rel ? implode('<br>', array_map(fn($n) => 'Nota ' . (int) $n['line_no'] . ' · ' . tgl($n['nota_date']) . ' · ' . $label($n) . ' · ' . rp((int) $n['nominal']), $rel)) : '-') . '</td></tr></table>';
    }
    $h .= '<div class="tx" style="' . $box . '">' . nl2br(e($ex[$cd])) . '</div>';
  }
  return $h . ba_versi_html($d);
}
/** Perubahan nominal per nota antar versi klaim (hanya bila klaim pernah direvisi nominalnya). */
function ba_versi_html(array $d): string {
  $vs = $d['versions'] ?? []; if (count($vs) < 2) return '';
  $a = jdec($vs[0]['snapshot'] ?? null)['notes'] ?? null; $b = jdec($vs[count($vs) - 1]['snapshot'] ?? null)['notes'] ?? null; if (!is_array($a) || !is_array($b)) return '';
  $v1 = (int) $vs[0]['version_no']; $v2 = (int) $vs[count($vs) - 1]['version_no']; $rows = ''; $keys = array_unique(array_merge(array_keys($a), array_keys($b))); sort($keys, SORT_NUMERIC);
  foreach ($keys as $k) { $x1 = (int) ($a[$k] ?? 0); $x2 = (int) ($b[$k] ?? 0); if ($x1 === $x2) continue; $rows .= '<tr><td>Nota ' . (int) $k . '</td><td class="r">' . rp($x1) . '</td><td class="r">' . rp($x2) . '</td></tr>'; }
  if ($rows === '') return '';
  return '<div class="sh" style="font-size:12.5px">Perubahan nominal antar versi</div><table><tr><th></th><th class="r">v' . $v1 . '</th><th class="r">v' . $v2 . '</th></tr>' . $rows . '<tr style="background:#F1F5FB"><td><b>Total</b></td><td class="r"><b>' . rp((int) $vs[0]['total']) . '</b></td><td class="r"><b>' . rp((int) $vs[count($vs) - 1]['total']) . '</b></td></tr></table>';
}
function ba_pdf_html(array $u, int $claimId): string {
  $d = claim_detail($u, $claimId); $ba = $d['ba']; if (!$ba) fail('Klaim ini tidak mempunyai BA.', 404); $c = $d['claim']; $cl = $d['claimant'];
  $snap = $ba['snapshot']; $co = $cl['entity'] ?: 'DSM';
  $col = ['nota' => ['#fff1e3', '#C25A12', '#f3b57f'], 'nom' => ['#fde8e6', '#D2453F', '#f0a8a3'], 'km' => ['#e6f0fb', '#1E5FB8', '#a7c6ee'], 'oth' => ['#eef2f6', '#47587A', '#c9d4df']];
  $badges = ''; foreach ($ba['trigger_codes'] as $t) { [$lb, $g] = TRIG_BADGE[$t] ?? [strtoupper($t), 'oth']; $badges .= '<span class="tp" style="background:' . $col[$g][0] . ';color:' . $col[$g][1] . ';border-color:' . $col[$g][2] . '">' . e($lb) . '</span>'; }
  $files = $ba['files']; $nPages = 1 + (int) ceil(count($files) / 3); $stamp = date('d M Y H.i');
  $hd = fn(string $title) => '<div class="hd"><div><div class="co">' . e($co) . '</div><div class="no">Region ' . e($cl['region'] ?: '-') . ' · DSM Claim</div></div><div style="text-align:right;font-size:12px;color:#47587A">No: <b style="color:#1D3A78">' . e($ba['number']) . '</b><br>Tanggal: ' . tgl(substr($ba['created_at'] ?? date('Y-m-d'), 0, 10)) . '<br>Klaim: ' . e($c['number']) . ' · versi ' . (int) $ba['version'] . '</div></div>';
  $ft = fn(int $i) => '<div class="ft"><span>Dicetak ' . e($stamp) . ' oleh ' . e($u['name']) . ' · isi sama untuk semua peran</span><span>Halaman ' . $i . ' dari ' . $nPages . '</span></div>';
  $p1 = $hd('') . '<div class="bt">BERITA ACARA</div><div class="bs">Pengajuan klaim operasional di luar ketentuan</div><div class="types">' . $badges . '</div>'
    . '<div class="bs">Terkait klaim <b>' . e($c['number']) . '</b> · ' . e($c['category']) . ' / ' . e($d['type_label']) . ' · <b>' . rp((int) $c['total']) . '</b></div>'
    . '<div class="sh">1. Data pihak</div>' . kv_rows([['Nama pemohon', e($cl['name'])], ['NIK / Jabatan', e($cl['nik'] ?: '-') . ' · ' . e($cl['job'])], ['Region', e($cl['region'] ?: '-')], ['Badan usaha', e($cl['entity'] ?: '-')], ['Pembuat BA', e($ba['creator']) . ' · ' . ($ba['creator_role'] === 'PEMOHON' ? 'Pemohon' : 'Admin')]])
    . '<div class="sh">2. Data klaim</div>' . kv_rows(array_merge([['Jenis / brand', e($c['category']) . ' / ' . e($d['type_label']) . ' · ' . e($c['brand'])], ['Nominal klaim', rp((int) $c['total']) . '<br><i>' . e(terbilang((int) $c['total'])) . '</i>']], $snap ? [['Budget ' . e($c['category']) . ' ' . e($snap['period']), 'Sisa sebelum klaim ' . rp((int) $snap['avail_before']) . ' · selisih over budget ' . ($snap['over'] > 0 ? '<b class="bad">' . rp((int) $snap['over']) . '</b>' : 'tidak ada')]] : []))
    . ba_letter_html($d, $ba)
    . (count($files) ? '<div class="tx" style="color:#47587A;margin-top:4px">Lampiran bukti: ' . count($files) . ' foto, lihat halaman 2' . ($nPages > 2 ? '–' . $nPages : '') . '.</div>' : '')
    . '<div class="close">Demikian berita acara ini dibuat dengan sebenar-benarnya untuk dapat dipergunakan sebagaimana mestinya, dengan penuh kesadaran dan tanggung jawab tanpa adanya paksaan dari pihak mana pun.</div>'
    . '<div style="text-align:right;font-size:12px;margin-bottom:6px">' . e($cl['region'] ?: 'Cirebon') . ', ' . tgl(date('Y-m-d')) . '</div>';
  $sg = []; foreach ($ba['signs'] as $s) if ($s['valid']) $sg[$s['role']] = $s;
  $p1 .= '<div class="sig">';
  $LB = ['PEMOHON' => 'Pemohon', 'ADMIN' => 'Admin', 'HOS' => 'HOS', 'AUDIT' => 'Audit', 'FM' => 'Finance Manager'];
  foreach ($ba['required'] as $rl) {
    $l = $LB[$rl] ?? $rl;
    $p1 .= '<div style="text-align:center"><b style="color:#1D3A78">' . $l . '</b>' . (isset($sg[$rl]) ? ((($im = val("SELECT img FROM claim_ba_sign WHERE ba_id=? AND version_no=? AND role=?", [$ba['id'], $ba['version'], $rl])) && preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $im)) ? '<br><img src="' . $im . '" alt="" style="max-height:54px;max-width:130px">' : '<br><span style="color:#12866A;font-weight:800">✔ Ditandatangani</span>') . '<br>' . e($sg[$rl]['name']) . '<br><small style="color:#47587A">' . tgl(substr($sg[$rl]['signed_at'], 0, 10)) . '<br>' . str_replace(':', '.', substr($sg[$rl]['signed_at'], 11, 5)) . ' WIB</small>' : '<span class="bad">Belum menandatangani</span>') . '</div>';
  }
  $p1 .= '</div><div class="tx" style="color:#47587A;font-size:10.5px">Tanda tangan dari versi klaim sebelumnya tidak berlaku. BA ini berlaku untuk versi ' . (int) $ba['version'] . '.</div>';
  $pages = [$p1 . $ft(1)];
  foreach (array_chunk($files, 3) as $k => $grp) {
    $pg = $hd('') . '<div class="bt" style="font-size:16px">LAMPIRAN BUKTI BERITA ACARA</div><div class="bs">Maksimal 3 foto per halaman.</div>';
    foreach ($grp as $i => $f) $pg .= '<div class="cap">Foto ' . ($k * 3 + $i + 1) . '</div><img class="img" style="max-height:290px" src="api.php?a=file&id=' . (int) $f['id'] . '" alt="">';
    $pages[] = $pg . $ft($k + 2);
  }
  $h = ''; foreach ($pages as $p) $h .= '<div class="pg">' . wm_html($u) . $p . '</div>';
  $extra = '.bt{text-align:center;font-size:21px;font-weight:800;letter-spacing:.18em;color:#1D3A78;margin-top:8px}.bs{text-align:center;font-size:12px;color:#47587A}.types{display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin:4px 0}.tp{padding:6px 14px;border-radius:999px;font-weight:800;font-size:12px;letter-spacing:.04em;border:1.5px solid}.close{margin:14px 0 6px;text-align:center;font-style:italic;font-size:12.5px;padding:10px 16px;border-top:1px solid #D5E0F2;border-bottom:1px solid #D5E0F2}.cap{font-weight:800;color:#1D3A78;font-size:12px;margin-top:8px}';
  return '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>' . e('Berita Acara ' . $ba['number']) . '</title><meta name="viewport" content="width=820"><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"><style>' . pdf_css() . $extra . '</style></head><body><div class="noprint" style="text-align:center;padding:10px"><button onclick="window.print()" style="padding:12px 22px;border:0;border-radius:12px;background:#1D4ED8;color:#fff;font-weight:800;font-size:14px">CETAK / SIMPAN PDF</button></div>' . $h . '</body></html>';
}
function pc_pdf_html(array $u, int $id): string {
  $p = get_pc($id); if (!pc_view($u, $p)) fail('Tidak diizinkan.', 403); $o = pc_out($p);
  $rows = [['Nomor', e($p['number'])], ['Pemohon', e($o['claimant']) . ' · ' . e($p['region']) . ' · ' . e($p['entity'])], ['Tujuan', e($p['purpose'])], ...($p['remark'] ? [['Keterangan tambahan', nl2br(e($p['remark']))]] : []), ['Periode', tgl($p['p_start']) . ' s/d ' . tgl($p['p_end'])], ['Jumlah diminta', rp((int) $p['amount']) . '<br><i>' . e(terbilang((int) $p['amount'])) . '</i>'], ['Status', e($o['label'])]];
  $h = '<div class="sh">Data petty cash</div>' . kv_rows($rows) . '<div class="sh">Rencana pemakaian</div><table><tr><th>Uraian</th><th>Kategori</th><th class="r">Nominal</th></tr>';
  foreach ($o['plan'] as $r) $h .= '<tr><td>' . e($r['uraian']) . '</td><td>' . e($r['kategori']) . '</td><td class="r">' . rp((int) $r['nominal']) . '</td></tr>';
  $h .= '</table><div class="sh">Nota pertanggungjawaban</div><table><tr><th>Tanggal</th><th>Uraian</th><th>Rencana</th><th class="r">Nominal</th></tr>';
  foreach ($o['notes'] as $n) $h .= '<tr><td>' . tgl($n['nota_date']) . '</td><td>' . e($n['uraian']) . '</td><td>' . ($n['off_plan'] ? 'Di luar rencana' : 'Sesuai rencana') . '</td><td class="r">' . rp((int) $n['nominal']) . '</td></tr>';
  $h .= '</table>' . kv_rows([['Terpakai', rp((int) $p['realized'])], ['Sisa disetor', rp((int) $p['sisa'])], ['Nomor setoran', e($p['setoran_no'] ?: '-')]]);
  $hs = q("SELECT * FROM claim_history WHERE ref_type='pc' AND ref_id=? ORDER BY id", [$id]);
  $h2 = history_html($hs);
  $pgs = [$h, $h2];
  $tr = one("SELECT t.*, u.name AS uname FROM claim_transfers t JOIN claim_users u ON u.id=t.user_id WHERE t.ref_type='pc' AND t.ref_id=? ORDER BY t.id DESC", [$id]);
  $tf = files_of('pc', $id, 'transfer'); $sf = files_of('pc', $id, 'setoran');
  if ($tr || $sf) {
    $im = fn($f) => '<img class="img" src="api.php?a=file&id=' . (int) $f['id'] . '" alt="">'; $p4 = '';
    if ($tr) $p4 .= '<div class="sh">Bukti transfer dana</div><div class="tot"><div><span>DANA SUDAH DITRANSFER</span><em>' . e(terbilang((int) $tr['amount'])) . '</em></div><b>' . rp((int) $tr['amount']) . '</b></div>' . kv_rows([['Nomor transfer', e($tr['transfer_no'])], ['Waktu transfer (WIB)', wkt($tr['created_at'])], ['Dicatat oleh', e($tr['uname']) . ' · Finance Transfer']]) . implode('', array_map($im, $tf));
    if ($sf) $p4 .= '<div class="sh">Bukti setoran sisa</div>' . kv_rows([['Nomor setoran', e($p['setoran_no'] ?: '-')], ['Sisa disetor', rp((int) $p['sisa'])]]) . implode('', array_map($im, $sf));
    $pgs[] = $p4;
  }
  return pdf_shell('Lembar Petty Cash', $pgs, $u, $p['entity'] ?: 'DSM', (string) $p['number'], date('d M Y H.i'));
}
