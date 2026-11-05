<?php
require_once __DIR__ . '/core.php';

const TRIG = [
  'OVER_BUDGET' => 'Klaim melebihi sisa budget', 'DIGIT_BBM' => 'Nominal BBM tidak mengikuti aturan digit', 'NOTA_LAMA' => 'Nota terlalu lama',
  'BIAYA_KM' => 'Biaya per km melebihi batas', 'ENT_PAX' => 'Entertain per peserta melebihi batas', 'KM_BBM' => 'Selisih jarak Perdin dengan KM BBM melebihi batas',
  'TEMUAN_ADMIN' => 'Temuan Admin saat pemeriksaan', 'KEHILANGAN_NOTA' => 'Kehilangan nota', 'NOMINAL_TIDAK_SESUAI' => 'Ketidaksesuaian nominal',
  'TEMUAN_LAIN' => 'Temuan / kendala lain', 'NOTA_HARI' => 'Nota di luar hari nota normal', 'TANPA_DOK' => 'Tanpa foto dokumentasi (Entertain)',
];
/** [label pendek kapital, kelompok warna] untuk badge jenis BA */
const TRIG_BADGE = [
  'OVER_BUDGET' => ['OVER BUDGET', 'nom'], 'DIGIT_BBM' => ['DIGIT BBM TIDAK SESUAI', 'nom'], 'NOTA_LAMA' => ['NOTA TERLALU LAMA', 'nota'], 'BIAYA_KM' => ['BIAYA PER KM MELEBIHI BATAS', 'km'],
  'ENT_PAX' => ['ENTERTAIN PER PESERTA MELEBIHI BATAS', 'nom'], 'KM_BBM' => ['SELISIH JARAK PERDIN DAN KM BBM', 'km'], 'TEMUAN_ADMIN' => ['TEMUAN ADMIN', 'oth'],
  'KEHILANGAN_NOTA' => ['KEHILANGAN NOTA', 'nota'], 'NOMINAL_TIDAK_SESUAI' => ['KETIDAKSESUAIAN NOMINAL', 'nom'],
  'TEMUAN_LAIN' => ['TEMUAN / KENDALA LAIN', 'oth'], 'NOTA_HARI' => ['NOTA DI LUAR HARI NORMAL', 'nota'], 'TANPA_DOK' => ['TANPA FOTO DOKUMENTASI', 'oth'],
];
const ADMIN_BA_TYPES = ['KEHILANGAN_NOTA', 'TEMUAN_ADMIN']; // nominal salah = pakai Revisi, bukan BA
const PEMOHON_BA_TYPES = ['KEHILANGAN_NOTA', 'TEMUAN_LAIN']; // Pemohon boleh membuat BA sendiri saat mengajukan
const TEMUAN_KAT = ['TGL' => 'Tanggal nota tidak sesuai', 'FOTO' => 'Foto / dokumen tidak jelas', 'RUTE' => 'Rute / jarak tidak wajar', 'DATA' => 'Data tidak sesuai (plat, brand, dll.)', 'LAIN' => 'Lainnya'];
/** Hari klaim yang diizinkan (1=Senin .. 7=Minggu). Belum diatur: Perdin semua hari, lainnya Senin-Sabtu. */
function allowed_days(array $rule, string $cat): array {
  $j = jdec($rule['days'] ?? null); if (is_array($j) && isset($j[$cat])) return array_values(array_unique(array_map('intval', $j[$cat])));
  return $cat === 'Perdin' ? [1, 2, 3, 4, 5, 6, 7] : [1, 2, 3, 4, 5, 6];
}
/** Merapikan data tambahan BA (nota hilang, kategori temuan, nota terkait) dan memeriksanya terhadap klaim. */
function ba_extra_clean(array $c, array $types, $raw, array $fileIds, array $rule): array {
  $raw = is_array($raw) ? $raw : []; $lines = array_map('intval', array_column(q("SELECT line_no FROM claim_notes WHERE claim_id=? ORDER BY line_no", [$c['id']]), 'line_no'));
  $pick = fn($a) => array_values(array_intersect(array_unique(array_map('intval', is_array($a) ? $a : [])), $lines));
  $x = [];
  if (in_array('KEHILANGAN_NOTA', $types, true)) {
    $x['lost'] = $pick($raw['lost'] ?? []); if (!$x['lost']) fail('Pilih nota yang hilang.', 422);
    if (!count($fileIds)) fail('Nota hilang wajib disertai bukti pengganti (mis. mutasi rekening, riwayat e-toll, screenshot aplikasi).', 422);
  }
  if (array_intersect(['TEMUAN_ADMIN', 'TEMUAN_LAIN'], $types)) {
    $k = (string) ($raw['kategori'] ?? 'LAIN'); if (!isset(TEMUAN_KAT[$k])) fail('Pilih kategori temuan.', 422);
    $x['kategori'] = $k; $x['related'] = $pick($raw['related'] ?? []);
  }
  return $x;
}
const HARI_ID = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
/** Aturan digit BBM Mobil: digit akhir (ribuan) nominal harus sama dengan angka aturan. Mis. aturan 2000: 62.000 benar, 64.000 salah. */
function digit_ok(int $nom, int $rule): bool { return $rule <= 0 || $nom % 10000 === $rule % 10000; }
/** Nominal valid terdekat di atas (atau sama dengan) nominal nota. */
function digit_should(int $nom, int $rule): int { $r = $rule % 10000; $b = intdiv($nom, 10000) * 10000 + $r; return $b < $nom ? $b + 10000 : $b; }
/** Jarak minimal Perdin agar uang makan boleh diklaim (km). Belum diatur = 80; 0 = tanpa batas. */
function min_km_makan(array $rule): int { $v = $rule['min_km_makan'] ?? null; return $v === null || $v === '' ? 80 : (int) $v; }
/** Penjelasan per pemicu: ['ba_explanations' => [KODE => teks]]; tanpa itu, satu penjelasan umum dipakai untuk semuanya. Mengembalikan [teks gabungan, peta, pesan error]. */
function ba_explain(array $trigs, array $in, string $key = 'ba_explanations', string $single = 'ba_explanation'): array {
  $map = is_array($in[$key] ?? null) ? $in[$key] : []; $out = []; $bad = [];
  foreach ($trigs as $t) { $x = trim((string) ($map[$t] ?? '')); if ($x === '' && !$map) $x = trim((string) ($in[$single] ?? '')); if (mb_strlen($x) < 10) $bad[] = TRIG[$t] ?? $t; else $out[$t] = $x; }
  $txt = count($out) === 1 ? reset($out) : implode("\n", array_map(fn($t, $x) => (TRIG[$t] ?? $t) . ': ' . $x, array_keys($out), $out));
  return [$txt, $out, $bad ? 'Isi penjelasan BA untuk: ' . implode('; ', $bad) . '.' : ''];
}
function ba_inputs(array $in): array { // jenis BA sukarela + data tambahan dari Pemohon
  $t = array_values(array_intersect(PEMOHON_BA_TYPES, is_array($in['ba_types'] ?? null) ? $in['ba_types'] : [])); return [$t, $in['ba_extra'] ?? []];
}

/** Peserta Meeting / acara: tiap baris "Nama — Jabatan", nama dan jabatan wajib. Mengembalikan daftar pesan error. */
function peserta_errors(array $pes, string $pre): array {
  $e = [];
  foreach (array_values($pes) as $i => $x) { [$n, $j] = array_pad(explode(' — ', (string) $x, 2), 2, ''); $no = 'Peserta ' . ($i + 1) . ': ';
    if (trim($n) === '') $e[] = $pre . $no . 'nama wajib diisi.'; elseif (trim($j) === '') $e[] = $pre . $no . 'jabatan atau asal wajib diisi (' . trim($n) . ').'; }
  return $e;
}
function form_kind(string $typeName): string {
  return match ($typeName) {
    'BBM' => 'bbm', 'Tol' => 'tol', 'Parkir', 'Servis kendaraan', 'Ojol & taksi online' => 'trans', 'Tiket perjalanan' => 'tiket', 'Meeting' => 'meeting',
    'Makan-makan', 'Kue ulang tahun' => 'party', 'Karangan bunga', 'Reward / Hadiah' => 'ent', 'Operasional lainnya' => 'lain', 'Perdin' => 'perdin', 'Uang makan' => 'makan', 'Kost' => 'kost', 'Penginapan / Hotel' => 'hotel', default => 'ops',
  };
}
function type_row(int $id): array { $t = one("SELECT * FROM claim_types WHERE id=?", [$id]); if (!$t) fail('Jenis klaim tidak ditemukan.'); return $t; }
function get_claim(int $id): array { $c = one("SELECT * FROM claims WHERE id=?", [$id]); if (!$c) fail('Klaim tidak ditemukan.', 404); return $c; }
/** Bagian klaim: satu jenis (biasa) atau beberapa jenis dari satu kategori dalam satu klaim. */
function claim_type_ids(array $c): array { $ids = array_values(array_map('intval', jdec($c['type_ids'] ?? '') ?: [])); return count($ids) > 1 ? $ids : [(int) $c['type_id']]; }
function claim_parts(array $c): array {
  $ids = claim_type_ids($c); $meta = jdec($c['meta'] ?? '') ?: []; $multi = count($ids) > 1; $out = [];
  foreach ($ids as $tid) { $t = type_row($tid); $out[] = ['type' => $t, 'kind' => form_kind($t['name']), 'meta' => $multi ? (($meta['parts'] ?? [])[(string) $tid] ?? ($meta['parts'][$tid] ?? [])) : $meta]; }
  return $out;
}
function claim_type_label(array $c): string { return implode(' + ', array_map(fn($p) => $p['type']['name'], claim_parts($c))); }
function is_ba_claim(array $c): bool { return (bool) $c['ba_required']; }

function build_route(array $c): array {
  $hos = $c['requires_hos'] || !$c['acct_own'] || $c['outside_sales'];
  $r = $c['ba_required'] ? ['ADMIN', 'HOS', 'AUDIT', 'FM', 'FT'] : ($hos ? ['ADMIN', 'HOS', 'FM', 'FT'] : ['ADMIN', 'FM', 'FT']);
  if ($c['outside_sales']) $r = array_values(array_diff($r, ['ADMIN']));
  return $r;
}
function duration_days(?string $a, ?string $b): int { if (!$a || !$b) return 0; $d = (strtotime($b) - strtotime($a)) / 86400; return $d < 0 ? 0 : (int) round($d) + 1; }
function norm_gate(string $s): string { return mb_convert_case(trim(preg_replace('/\s+/', ' ', $s)), MB_CASE_TITLE, 'UTF-8'); }

/* ---------- simpan (draft / perbaikan) ---------- */
function owner_can_edit(array $u, array $c): bool {
  if ($c['status'] === 'DRAFT') return (int) $c['created_by'] === (int) $u['id'];
  if ($c['status'] === 'REV') {
    if ($c['rev_holder'] === 'PEMOHON') return same_person($u, $c);
    if ($c['rev_holder'] === 'ADMIN') return has_role($u, 'ADMIN') && in_scope($u, $c, 'ADMIN') && !same_person($u, $c);
  }
  return false;
}
function pc_blocked(int $uid): ?array {
  $p = one("SELECT * FROM claim_pc WHERE claimant_id=? AND (status='DANA' OR (status='SETOR' AND setoran_no IS NULL)) ", [$uid]);
  if (!$p) return null;
  $grace = (int) (current_rule()['pc_grace_days'] ?? 0);
  $deadline = date('Y-m-d', strtotime($p['p_end'] . " +$grace days"));
  return date('Y-m-d') > $deadline ? $p + ['deadline' => $deadline] : null;
}

function claim_save(array $u, array $in): int {
  $id = (int) ($in['id'] ?? 0);
  $t = type_row((int) ($in['type_id'] ?? 0));
  $division = !empty($in['division']);
  if ($id) {
    $c = get_claim($id);
    if (!owner_can_edit($u, $c)) fail('Anda tidak dapat mengubah klaim ini pada tahap sekarang.', 403);
    $claimant = (int) $c['claimant_id'];
  } else {
    if ($division) { if (!has_role($u, 'ADMIN')) fail('Hanya Admin yang dapat mengajukan untuk divisi.', 403); $claimant = (int) $u['id']; }
    else { if (!is_claimant($u)) fail('Anda belum diberi hak mengajukan klaim.', 403); $claimant = (int) $u['id']; }
    if (pc_blocked($claimant)) fail('Pengajuan klaim diblokir sampai petty cash dipertanggungjawabkan.', 403);
  }
  $cu = user_row($claimant);
  if (!$division && empty($c['outside_sales'] ?? 0)) {
    $en = val("SELECT enabled FROM claim_entitlement WHERE user_id=? AND category=?", [$claimant, $t['category']]);
    if (!$en) fail('Kategori ' . $t['category'] . ' belum diaktifkan untuk Anda.', 403);
  }
  if ($t['category'] !== (string) ($in['category'] ?? $t['category'])) fail('Jenis tidak sesuai kategori.');
  $meta = is_array($in['meta'] ?? null) ? $in['meta'] : [];
  // beberapa jenis dalam satu klaim: wajib satu kategori, tanpa Perdin, tiap jenis sekali
  $tids = array_values(array_unique(array_map('intval', $in['type_ids'] ?? [])));
  if (count($tids) > 1) {
    if ($tids[0] !== (int) $t['id']) array_unshift($tids, (int) $t['id']); $tids = array_values(array_unique($tids));
    foreach ($tids as $x) { $tx = type_row($x); if ($tx['category'] !== $t['category']) fail('Satu klaim hanya boleh berisi jenis dari satu kategori.', 422);
      if (in_array(form_kind($tx['name']), ['perdin', 'makan'], true) || $tx['name'] === 'Reimburse petty cash') fail('Jenis ' . $tx['name'] . ' tidak bisa digabung dengan jenis lain.', 422); }
    $meta['parts'] = array_intersect_key(is_array($meta['parts'] ?? null) ? $meta['parts'] : [], array_flip(array_map('strval', $tids)));
  } else { $tids = [(int) $t['id']]; unset($meta['parts']); }
  $multi = count($tids) > 1;
  $acct = $in['account'] ?? ['mode' => 'self'];
  $own = ($acct['mode'] ?? 'self') === 'self';
  $row = [
    'category' => $t['category'], 'type_id' => $t['id'], 'brand' => trim($in['brand'] ?? ''), 'refnote' => trim($in['refnote'] ?? ''),
    'location' => trim($in['location'] ?? ''), 'meta' => jenc($meta), 'type_ids' => $multi ? jenc($tids) : null, 'acct_own' => $own ? 1 : 0, 'updated_at' => now(),
    'acct_bank' => $own ? $cu['bank'] : trim($acct['bank'] ?? ''), 'acct_no' => $own ? $cu['bank_no'] : trim($acct['no'] ?? ''),
    'acct_name' => $own ? $cu['bank_name'] : trim($acct['name'] ?? ''), 'acct_reason' => $own ? null : trim($acct['reason'] ?? ''),
  ];
  $kind = form_kind($t['name']);
  $notesIn = array_values($in['notes'] ?? []);
  if ($kind === 'perdin') $notesIn = perdin_notes($cu, $meta, $notesIn);
  if ($multi) {
    $bbmT = 0; foreach ($tids as $x) if (form_kind(type_row($x)['name']) === 'bbm') $bbmT = $x;
    foreach ($notesIn as &$n) { $n['detail'] = is_array($n['detail'] ?? null) ? $n['detail'] : []; $n['detail']['type_id'] = (int) ($n['detail']['type_id'] ?? 0);
      if (!in_array($n['detail']['type_id'], $tids, true)) fail('Setiap nota harus punya jenis yang dipilih di klaim ini.', 422);
      if ($n['detail']['type_id'] === $bbmT) $n = bbm_notes([$n])[0]; }
    unset($n);
  } elseif ($kind === 'bbm') $notesIn = bbm_notes($notesIn);
  $total = 0; foreach ($notesIn as $n) $total += max(0, (int) ($n['nominal'] ?? 0));
  $row['total'] = $total;
  if (!$id) {
    $row += ['claimant_id' => $claimant, 'created_by' => $u['id'], 'region' => $cu['region'], 'entity' => $cu['entities'][0] ?? null, 'status' => 'DRAFT',
      'outside_sales' => $division ? 1 : 0, 'created_at' => now(), 'current_version' => 0, 'requires_hos' => 0];
    if ($division) $row['region'] = $meta['divisi_region'] ?? $cu['region'];
    $id = ins('claims', $row);
    hist('claim', $id, 'Draft dibuat', $u);
  } else {
    upd('claims', $id, $row);
  }
  // nota: pertahankan id yang sama, hapus yang tidak ada lagi
  $keep = [];
  foreach ($notesIn as $i => $n) {
    $d = $n['detail'] ?? []; if (isset($d['asal'])) { $d['asal'] = norm_gate($d['asal']); $d['tujuan'] = norm_gate($d['tujuan'] ?? ''); }
    $nr = ['claim_id' => $id, 'line_no' => $i + 1, 'nominal' => max(0, (int) ($n['nominal'] ?? 0)), 'nota_date' => ($n['date'] ?? '') ?: null, 'detail' => jenc($d)];
    $nid = (int) ($n['id'] ?? 0);
    if ($nid && val("SELECT 1 FROM claim_notes WHERE id=? AND claim_id=?", [$nid, $id])) { upd('claim_notes', $nid, $nr); } else { $nid = ins('claim_notes', $nr); }
    $keep[] = $nid;
    sync_files($u, $id, $nid, $n['files'] ?? [], 'nota');
    foreach (['asal', 'tujuan'] as $g) if (!empty($d[$g]) && !val("SELECT 1 FROM claim_gate WHERE name=?", [$d[$g]])) ins('claim_gate', ['name' => $d[$g]]);
  }
  foreach (q("SELECT id FROM claim_notes WHERE claim_id=?", [$id]) as $r) if (!in_array((int) $r['id'], $keep, true)) {
    ex("DELETE FROM claim_notes WHERE id=?", [$r['id']]); ex("UPDATE claim_files SET slot='removed' WHERE ref_type='claim' AND ref_id=? AND note_id=?", [$id, $r['id']]);
  }
  foreach (['odo_awal', 'odo_akhir', 'doc', 'maps'] as $slot) if (array_key_exists($slot, $in['files'] ?? [])) sync_files($u, $id, null, $in['files'][$slot], $slot);
  return $id;
}
function sync_files(array $u, int $claimId, ?int $noteId, array $ids, string $slot): void {
  $ids = array_map('intval', $ids);
  $cur = q("SELECT id FROM claim_files WHERE ref_type='claim' AND ref_id=? AND slot=? AND " . ($noteId === null ? 'note_id IS NULL' : 'note_id=' . (int) $noteId), [$claimId, $slot]);
  foreach ($cur as $r) if (!in_array((int) $r['id'], $ids, true)) ex("UPDATE claim_files SET slot='removed' WHERE id=?", [$r['id']]);
  foreach ($ids as $fid) {
    $f = one("SELECT * FROM claim_files WHERE id=?", [$fid]);
    if (!$f) continue;
    if ($f['ref_type'] === null && (int) $f['uploader'] !== (int) $u['id'] && !sa_free($u)) fail('Berkas tidak valid.', 403);
    if ($f['ref_type'] !== null && !($f['ref_type'] === 'claim' && (int) $f['ref_id'] === $claimId)) fail('Berkas milik klaim lain.', 403);
    link_files([$fid], 'claim', $claimId, $noteId, $slot);
  }
}

/** Perdin: hitung ulang nota dari komponen (uang makan terkunci; hari <= durasi). */
function perdin_notes(array $cu, array $meta, array $notes): array {
  $dur = duration_days($meta['tgl_berangkat'] ?? null, $meta['tgl_pulang'] ?? null);
  $out = []; $hasMakan = false;
  foreach ($notes as $n) {
    $d = $n['detail'] ?? []; $k = $d['komponen'] ?? '';
    if ($k === 'Uang makan') {
      if ($hasMakan) continue; $hasMakan = true;
      $rate = (int) ($cu['meal_rate'] ?? 0);
      $n['nominal'] = $rate * $dur; $d['hari'] = $dur; $d['tarif'] = $rate; $n['files'] = [];
    } elseif (in_array($k, ['Transportasi', 'Penginapan'], true)) {
      if (($d['mode'] ?? 'sekali') === 'hari') { $n['nominal'] = (int) ($d['per_hari'] ?? 0) * (int) ($d['hari'] ?? 0); }
    }
    $n['detail'] = $d; $out[] = $n;
  }
  return $out;
}

/* ---------- BBM: harga, konversi Pertamax, pembulatan Rp 100 ---------- */
const BBM_JENIS = ['Pertalite', 'Pertamax'];
const KENDARAAN = ['Mobil', 'Motor'];
/** Harga BBM yang berlaku pada tanggal nota (null bila belum ada). */
function fuel_price(?string $date): ?array {
  $date = $date ?: date('Y-m-d');
  return one("SELECT * FROM claim_fuel_price WHERE effective_from<=? ORDER BY effective_from DESC, id DESC LIMIT 1", [$date]);
}
/** Hitung nominal diklaim dari nominal nota. Pertalite = nota; Pertamax = (nota ÷ harga Pertamax) × harga Pertalite, dibulatkan Rp 100. */
function fuel_calc(int $nota, string $jenis, array $p): array {
  $liter = $jenis === 'Pertamax' ? $nota / max(1, (int) $p['pertamax']) : $nota / max(1, (int) $p['pertalite']);
  $claim = $jenis === 'Pertamax' ? (int) (round($nota * (int) $p['pertalite'] / max(1, (int) $p['pertamax']) / 100) * 100) : $nota;
  return ['liter' => round($liter, 2), 'claim' => $claim];
}
/** Nominal yang diinput pemohon untuk BBM adalah nominal NOTA; server menghitung nominal diklaim. */
function bbm_notes(array $notes): array {
  foreach ($notes as &$n) {
    $d = $n['detail'] ?? []; $nota = max(0, (int) ($n['nominal'] ?? 0)); $j = $d['bbm'] ?? '';
    $d['nota_nominal'] = $nota;
    $p = in_array($j, BBM_JENIS, true) && !empty($n['date']) ? fuel_price($n['date']) : null;
    if ($p) { $r = fuel_calc($nota, $j, $p); $d['liter'] = $r['liter']; $d['harga_pertalite'] = (int) $p['pertalite']; $d['harga_pertamax'] = (int) $p['pertamax']; $d['harga_tgl'] = $p['effective_from']; $n['nominal'] = $r['claim']; }
    else { unset($d['liter'], $d['harga_pertalite'], $d['harga_pertamax'], $d['harga_tgl']); $n['nominal'] = $nota; }
    $n['detail'] = $d;
  }
  return $notes;
}

/* ---------- validasi penuh + pemicu BA ---------- */
function claim_check(array $c, ?array $rule = null): array {
  $rule ??= current_rule();
  $parts = claim_parts($c); $multi = count($parts) > 1; $kind = $multi ? 'multi' : $parts[0]['kind'];
  $byT = []; foreach ($parts as $p) $byT[(int) $p['type']['id']] = $p;
  $notes = q("SELECT * FROM claim_notes WHERE claim_id=? ORDER BY line_no", [$c['id']]);
  foreach ($notes as &$n) { $n['detail'] = jdec($n['detail']) ?: []; $n['files'] = files_of('claim', (int) $c['id'], 'nota', (int) $n['id']); }
  unset($n);
  $cf = fn($slot) => files_of('claim', (int) $c['id'], $slot);
  $err = []; $trig = []; $info = [];
  $brands = array_map('strtolower', array_merge(brand_options(), array_column(q("SELECT val FROM claim_lookup WHERE kind='brand'"), 'val'))); // data lama (mis. "Infinix") tetap sah
  if (!$c['brand'] || !in_array(strtolower($c['brand']), $brands, true)) $err[] = 'Pilih Brand dari daftar.';
  if (!in_array($kind, ['perdin', 'makan'], true) && strlen(trim((string) $c['location'])) < 2) $err[] = 'Lokasi (kota/kabupaten) wajib diisi.';
  if (!count($notes)) $err[] = 'Minimal satu nota.';
  $today = date('Y-m-d');
  foreach ($notes as $i => $n) {
    $no = 'Nota ' . ($i + 1) . ': ';
    $makan = ($n['detail']['komponen'] ?? '') === 'Uang makan';
    if ($n['nominal'] <= 0 && !$makan) $err[] = $no . 'nominal wajib diisi.';
    if ($makan && $n['nominal'] <= 0) $err[] = 'Tarif uang makan Anda belum diatur Admin.';
    if (!$makan) {
      if (!$n['nota_date']) $err[] = $no . 'tanggal nota wajib.';
      elseif ($n['nota_date'] > $today) $err[] = $no . 'tanggal tidak boleh di masa depan.';
      $perHari = in_array($n['detail']['komponen'] ?? '', ['Transportasi', 'Penginapan'], true) && ($n['detail']['mode'] ?? '') === 'hari';
      if (!count($n['files']) && !$perHari) $err[] = $no . 'lampirkan minimal satu foto nota.';
    }
    if ($n['nota_date'] && !in_array((int) date('N', strtotime($n['nota_date'])), allowed_days($rule, (string) $c['category']), true) && !$makan) { $trig['NOTA_HARI'] = 1; $info['hari'][] = ['line' => $i + 1, 'date' => $n['nota_date'], 'hari' => HARI_ID[(int) date('N', strtotime($n['nota_date']))], 'nominal' => (int) $n['nominal']]; }
    $np = $multi ? ($byT[(int) ($n['detail']['type_id'] ?? 0)] ?? null) : $parts[0];
    if (!$np) { $err[] = $no . 'jenis nota tidak dikenal.'; continue; }
    $nk = $np['kind']; $meta = $np['meta'];
    if ($nk === 'tol' && (empty($n['detail']['asal']) || empty($n['detail']['tujuan']))) $err[] = $no . 'gerbang asal dan tujuan wajib.';
    if ($nk === 'bbm') {
      $j = $n['detail']['bbm'] ?? '';
      if (!in_array($j, BBM_JENIS, true)) $err[] = $no . 'pilih jenis BBM (Pertalite atau Pertamax).';
      elseif ($n['nota_date'] && !fuel_price($n['nota_date'])) $err[] = $no . 'harga BBM pada tanggal nota belum diatur Super Admin.';
      $notaNom = (int) ($n['detail']['nota_nominal'] ?? $n['nominal']);
      if (($meta['kendaraan'] ?? '') === 'Mobil' && !digit_ok($notaNom, (int) ($rule['bbm_round'] ?? 0))) { $trig['DIGIT_BBM'] = 1; $info['digit'][] = ['line' => $i + 1, 'date' => $n['nota_date'], 'jumlah' => $notaNom, 'seharusnya' => digit_should($notaNom, (int) $rule['bbm_round']), 'klaim' => (int) $n['nominal']]; }
    }
  }
  foreach ($parts as $P) {
  $meta = $P['meta']; $kind = $P['kind']; $t = $P['type']; $tid = (int) $t['id'];
  $pnotes = $multi ? array_values(array_filter($notes, fn($n) => (int) ($n['detail']['type_id'] ?? 0) === $tid)) : $notes;
  $ptot = 0; foreach ($pnotes as $n) $ptot += (int) $n['nominal'];
  $pre = $multi ? $t['name'] . ': ' : '';
  if ($multi && !$pnotes) $err[] = $pre . 'minimal satu nota.';
  $need = function ($k, $label) use (&$err, $meta, $pre) { if (trim((string) ($meta[$k] ?? '')) === '') $err[] = $pre . "$label wajib diisi."; };
  switch ($kind) {
    case 'bbm':
      if (!in_array($meta['kendaraan'] ?? '', KENDARAAN, true)) $err[] = $pre . 'Pilih jenis kendaraan (Mobil atau Motor).';
      $need('plat', 'Plat kendaraan');
      $a = (int) ($meta['km_awal'] ?? 0); $b = (int) ($meta['km_akhir'] ?? 0);
      if ($a <= 0 || $b <= $a) $err[] = $pre . 'KM akhir harus lebih besar dari KM awal.';
      if (!count($cf('odo_awal'))) $err[] = $pre . 'Foto odometer awal wajib.';
      if (!count($cf('odo_akhir'))) $err[] = $pre . 'Foto odometer akhir wajib.';
      break;
    case 'trans': case 'ops': case 'ent': if (!($t['name'] === 'Parkir' && $kind === 'trans')) $need('keterangan', 'Keterangan'); if ($kind === 'ent') { $need('penerima', 'Nama penerima'); if (!count($cf('doc'))) $trig['TANPA_DOK'] = 1; } break; // tempat acara opsional untuk Karangan bunga / Reward
    case 'party':
      $need('keterangan', 'Keterangan'); $need('tempat', 'Tempat acara');
      if ($t['name'] === 'Kue ulang tahun') $need('penerima', 'Nama yang dirayakan');
      $pes = array_values(array_filter($meta['peserta'] ?? [], fn($x) => trim((string) $x) !== ''));
      $uniq = array_unique(array_map(fn($x) => mb_strtolower(trim(explode(' — ', (string) $x)[0])), $pes));
      if (count($uniq) < 2) $err[] = $pre . 'Daftar peserta minimal 2 orang berbeda (tidak boleh dirayakan sendiri).';
      foreach (peserta_errors($pes, $pre) as $m) $err[] = $m;
      if (!count($cf('doc'))) $trig['TANPA_DOK'] = 1;
      break;
    case 'tiket':
      if (!in_array($meta['moda'] ?? '', ['Pesawat', 'Kereta', 'Bus / travel'], true)) $err[] = $pre . 'Pilih moda tiket (Pesawat, Kereta, atau Bus / travel).';
      $need('asal', 'Kota asal'); $need('tujuan_kota', 'Kota tujuan'); $need('tgl_berangkat', 'Tanggal berangkat'); $need('operator', 'Maskapai / operator'); $need('penumpang', 'Nama penumpang'); $need('keterangan', 'Tujuan perjalanan');
      if (!empty($meta['tgl_pulang']) && !empty($meta['tgl_berangkat']) && $meta['tgl_pulang'] < $meta['tgl_berangkat']) $err[] = $pre . 'Tanggal pulang tidak boleh sebelum tanggal berangkat.';
      break;
    case 'lain':
      $need('judul', 'Judul pengeluaran'); $need('keterangan', 'Kebutuhan / alasan');
      $rin = array_values(array_filter($meta['rincian'] ?? [], fn($r) => trim((string) ($r['uraian'] ?? '')) !== ''));
      if (!$rin) $err[] = $pre . 'Rincian barang / jasa minimal satu baris.';
      else { $sum = 0; foreach ($rin as $r) $sum += max(0, (int) ($r['qty'] ?? 0)) * max(0, (int) ($r['harga'] ?? 0));
        if ($sum <= 0) $err[] = $pre . 'Isi qty dan harga pada rincian.'; elseif ($sum !== (int) $ptot) $err[] = $pre . 'Total rincian (' . rp($sum) . ') harus sama dengan total nota (' . rp((int) $ptot) . ').'; }
      break;
    case 'meeting':
      $need('tujuan', 'Tujuan meeting'); $need('tempat', 'Tempat meeting'); $need('jam_mulai', 'Jam mulai'); $need('jam_selesai', 'Jam selesai');
      $pes = array_values(array_filter($meta['peserta'] ?? [], fn($x) => trim((string) $x) !== ''));
      if (count($pes) < 2) $err[] = $pre . 'Peserta meeting minimal 2 orang.';
      foreach (peserta_errors($pes, $pre) as $m) $err[] = $m;
      if (!count($cf('doc'))) $trig['TANPA_DOK'] = 1; // tanpa foto dokumentasi: tidak diblokir, tetapi wajib BA
      break;
    case 'kost': $need('nama_kost', 'Nama kost'); $need('periode', 'Periode sewa'); $need('pemilik', 'Nama pemilik'); break;
    case 'hotel': $need('nama_hotel', 'Nama hotel'); $need('checkin', 'Check-in'); $need('checkout', 'Check-out'); $need('tujuan', 'Tujuan menginap'); break;
    case 'makan':
      $dur = duration_days($meta['tgl_berangkat'] ?? null, $meta['tgl_pulang'] ?? null);
      if ($dur <= 0) $err[] = $pre . 'Tanggal berangkat/pulang tidak valid.';
      $need('tujuan', 'Tujuan perjalanan');
      if (!count(array_filter($meta['rute'] ?? [], fn($x) => trim((string) $x) !== ''))) $err[] = $pre . 'Rute minimal satu tujuan.';
      if (!count($cf('maps'))) $err[] = $pre . 'Screenshot Google Maps wajib (satu gambar).';
      if (count($cf('maps')) > 1) $err[] = $pre . 'Screenshot Google Maps hanya satu.';
      $km = (float) ($meta['km_total'] ?? 0); $mk = min_km_makan($rule);
      if ($km <= 0) $err[] = $pre . 'Total jarak (km) dari Maps wajib.';
      elseif ($mk > 0 && $km < $mk) $err[] = $pre . 'Uang makan belum bisa diklaim: jarak Perdin ' . $km . ' km, minimal ' . $mk . ' km.';
      $rate = (int) val("SELECT meal_rate FROM claim_users WHERE id=?", [$c['claimant_id']]);
      if ($rate <= 0) $err[] = $pre . 'Tarif uang makan Anda belum diatur Admin.';
      elseif ($dur > 0 && $ptot > $rate * $dur) $err[] = $pre . 'Melebihi batas uang makan ' . rp($rate * $dur) . ' (' . rp($rate) . ' × ' . $dur . ' hari).';
      $info['makan'] = ['tarif' => $rate, 'hari' => $dur, 'batas' => $rate * max(0, $dur)];
      foreach ($pnotes as $i => $n) if ($n['nota_date'] && $dur > 0 && ($n['nota_date'] < $meta['tgl_berangkat'] || $n['nota_date'] > $meta['tgl_pulang'])) $err[] = $pre . 'Nota ' . ($i + 1) . ': tanggal harus di antara tanggal berangkat dan pulang.';
      break;
    case 'perdin':
      $dur = duration_days($meta['tgl_berangkat'] ?? null, $meta['tgl_pulang'] ?? null);
      if ($dur <= 0) $err[] = $pre . 'Tanggal berangkat/pulang tidak valid.';
      $need('tujuan', 'Tujuan perjalanan');
      $rute = array_values(array_filter($meta['rute'] ?? [], fn($x) => trim((string) $x) !== ''));
      if (!count($rute)) $err[] = $pre . 'Rute minimal satu tujuan.';
      if (!count($cf('maps'))) $err[] = $pre . 'Screenshot Google Maps wajib (satu gambar).';
      if (count($cf('maps')) > 1) $err[] = $pre . 'Screenshot Google Maps hanya satu.';
      if ((float) ($meta['km_total'] ?? 0) <= 0) $err[] = $pre . 'Total jarak (km) dari Maps wajib.';
      $mk = min_km_makan($rule); if ($mk > 0 && (float) ($meta['km_total'] ?? 0) < $mk && array_filter($pnotes, fn($n) => ($n['detail']['komponen'] ?? '') === 'Uang makan')) $err[] = $pre . 'Uang makan Perdin hanya untuk jarak minimal ' . $mk . ' km (jarak Anda ' . (float) $meta['km_total'] . ' km). Hapus komponen Uang makan.';
      foreach ($pnotes as $n) { $d = $n['detail']; if (($d['hari'] ?? 0) > $dur && ($d['komponen'] ?? '') !== 'Uang makan') $err[] = ($d['komponen'] ?? 'Komponen') . ': jumlah hari melebihi durasi (' . $dur . ' hari).'; }
      break;
  }
  }
  // rekening
  if (!$c['acct_bank'] || !$c['acct_no'] || !$c['acct_name']) $err[] = 'Rekening tujuan lengkap wajib (bank, nomor, atas nama).';
  if (!$c['acct_own'] && trim((string) $c['acct_reason']) === '') $err[] = 'Alasan memakai rekening bukan milik pemohon wajib.';
  // nota ganda
  foreach (duplicate_notes($c, $notes) as $m) $err[] = $m;
  // budget
  $period = $c['budget_period'] ?: date('Y-m');
  $bi = null;
  if (!$c['outside_sales']) {
    $bi = budget_info((int) $c['claimant_id'], $c['category'], $period, (int) $c['id']);
    if ($bi['available'] < $c['total']) $trig['OVER_BUDGET'] = 1;
  }
  return ['errors' => array_values(array_unique($err)), 'triggers' => array_keys($trig), 'budget' => $bi, 'period' => $period, 'notes' => $notes, 'kind' => $multi ? 'multi' : $parts[0]['kind'], 'info' => $info];
}
function duplicate_notes(array $c, array $notes): array {
  $m = []; $cid = (int) $c['id']; $seen = [];
  $active = "c.status IN ('WAIT','REV','DONE') AND c.number IS NOT NULL AND c.id<>?";
  foreach ($notes as $i => $n) {
    foreach ($n['files'] as $f) {
      $sha = val("SELECT sha FROM claim_files WHERE id=?", [$f['id']]);
      if (isset($seen[$sha]) && $seen[$sha] !== (int) $n['id']) $m[] = 'Foto yang sama dipakai di dua nota pada klaim ini.';
      $seen[$sha] = (int) $n['id'];
      $o = one("SELECT c.number FROM claim_files f JOIN claims c ON c.id=f.ref_id WHERE f.ref_type='claim' AND f.sha=? AND f.slot='nota' AND $active LIMIT 1", [$sha, $cid]);
      if ($o) $m[] = 'Nota ' . ($i + 1) . ' sudah dipakai pada klaim ' . $o['number'] . '.';
    }
    if (($n['detail']['komponen'] ?? '') === 'Uang makan' || !$n['nota_date']) continue;
    $ntid = (int) ($n['detail']['type_id'] ?? $c['type_id']);
    $rows = q("SELECT c.number, c.type_id, n.detail FROM claim_notes n JOIN claims c ON c.id=n.claim_id WHERE c.claimant_id=? AND n.nota_date=? AND n.nominal=? AND $active", [$c['claimant_id'], $n['nota_date'], $n['nominal'], $cid]);
    foreach ($rows as $r) {
      $d = jdec($r['detail']) ?: [];
      if ((int) ($d['type_id'] ?? $r['type_id']) !== $ntid) continue;
      if (isset($n['detail']['asal'])) { if (($d['asal'] ?? '') !== $n['detail']['asal'] || ($d['tujuan'] ?? '') !== $n['detail']['tujuan']) continue; }
      $m[] = 'Nota ' . ($i + 1) . ' bentrok dengan klaim ' . $r['number'] . ' (pemohon, jenis, tanggal, dan nominal sama).';
    }
  }
  return $m;
}

/* ---------- BA ---------- */
function ba_required_roles(array $c): array {
  $ba = empty($c['id']) ? null : ba_row((int) $c['id']);
  $r = ($ba && ($ba['creator_role'] ?? '') === 'ADMIN') ? ['ADMIN', 'PEMOHON', 'HOS', 'AUDIT', 'FM'] : ['PEMOHON', 'ADMIN', 'HOS', 'AUDIT', 'FM'];
  return $c['outside_sales'] ? array_values(array_diff($r, ['ADMIN'])) : $r; }
function ba_row(int $claimId): ?array { return one("SELECT * FROM claim_ba WHERE claim_id=?", [$claimId]); }
/** Tanda tangan tersimpan di profil wajib ada sebelum menandatangani BA (Super Admin mode tes dikecualikan). */
function must_sign(array $u, string $who = 'Anda'): void {
  if (sa_free($u)) return;
  if (!val("SELECT 1 FROM claim_users WHERE id=? AND sign_img IS NOT NULL AND sign_img<>''", [$u['id']]))
    fail("$who belum mengisi tanda tangan. Isi tanda tangan Anda dulu, lalu ulangi.", 422);
}
function ba_sign(array $ba, string $role, array $u): void {
  if (!val("SELECT 1 FROM claim_ba_sign WHERE ba_id=? AND version_no=? AND role=?", [$ba['id'], $ba['version_no'], $role]))
    ins('claim_ba_sign', ['ba_id' => $ba['id'], 'version_no' => $ba['version_no'], 'role' => $role, 'user_id' => $u['id'], 'signed_at' => now(),
      'img' => val("SELECT sign_img FROM claim_users WHERE id=?", [$u['id']]) ?: null]);
}
function ba_pemohon_pending(array $c): bool {
  if (empty($c['ba_required'])) return false; $ba = ba_row((int) $c['id']); if (!$ba) return false;
  return !val("SELECT 1 FROM claim_ba_sign WHERE ba_id=? AND version_no=? AND role='PEMOHON'", [$ba['id'], $ba['version_no']]);
}
function ba_complete(array $c): bool {
  $ba = ba_row((int) $c['id']); if (!$ba) return false;
  $have = array_column(q("SELECT role FROM claim_ba_sign WHERE ba_id=? AND version_no=?", [$ba['id'], $ba['version_no']]), 'role');
  return !array_diff(ba_required_roles($c), $have);
}
function ba_create(array $u, array $c, array $trigs, string $explain, string $creatorRole, array $fileIds = [], $extraIn = []): void {
  if (strlen(trim($explain)) < 10) fail('Penjelasan BA minimal 10 karakter.');
  $extra = ba_extra_clean($c, $trigs, $extraIn, $fileIds, current_rule());
  if (is_array($extraIn) && !empty($extraIn['expl']) && is_array($extraIn['expl'])) $extra['expl'] = $extraIn['expl'];
  $auto = claim_check($c)['info'] ?? []; foreach (['digit', 'hari'] as $k) if (!empty($auto[$k]) && in_array($k === 'digit' ? 'DIGIT_BBM' : 'NOTA_HARI', $trigs, true)) $extra[$k] = $auto[$k];
  must_sign($u);
  if (ba_row((int) $c['id'])) fail('Klaim ini sudah mempunyai BA (satu klaim satu BA).');
  $period = $c['budget_period'] ?: date('Y-m'); $bi = $c['outside_sales'] ? null : budget_info((int) $c['claimant_id'], $c['category'], $period, (int) $c['id']);
  $snap = $bi ? ['period' => $period, 'avail_before' => $bi['available'], 'claim_amount' => (int) $c['total'], 'over' => max(0, (int) $c['total'] - max(0, $bi['available']))] : null;
  $id = ins('claim_ba', ['number' => next_number('BA', $c['entity']), 'claim_id' => $c['id'], 'creator_id' => $u['id'], 'creator_role' => $creatorRole,
    'triggers' => jenc($trigs), 'explanation' => $explain, 'snapshot' => jenc($snap), 'extra' => $extra ? jenc($extra) : null, 'version_no' => max(1, (int) $c['current_version']), 'created_at' => now()]);
  link_files($fileIds, 'ba', $id, null, 'ba');
  $ba = one("SELECT * FROM claim_ba WHERE id=?", [$id]);
  ba_sign($ba, $creatorRole === 'PEMOHON' ? 'PEMOHON' : 'ADMIN', $u);
  ex("UPDATE claims SET ba_required=1 WHERE id=?", [$c['id']]);
  hist('claim', (int) $c['id'], 'BA dibuat (' . $ba['number'] . ')', $u, implode('; ', array_map(fn($t) => TRIG[$t] ?? $t, $trigs)), null, $creatorRole === 'PEMOHON' ? 'dibuat Pemohon' : 'dibuat Admin');
}

/* ---------- kirim ---------- */
function claim_submit(array $u, int $id, array $in): array {
  $c = get_claim($id);
  if ($c['status'] !== 'DRAFT') fail('Klaim sudah dikirim.');
  if (!owner_can_edit($u, $c)) fail('Tidak diizinkan.', 403);
  if (pc_blocked((int) $c['claimant_id'])) fail('Pengajuan klaim diblokir sampai petty cash dipertanggungjawabkan.', 403);
  ex("UPDATE claims SET budget_period=? WHERE id=?", [date('Y-m'), $id]); $c = get_claim($id);
  $rule = current_rule(); $chk = claim_check($c, $rule);
  if ($chk['errors']) fail('Lengkapi dulu: ' . implode(' ', array_slice($chk['errors'], 0, 4)), 422);
  $bi = $chk['budget'];
  db()->beginTransaction();
  try {
    $number = next_number('KLM', '');
    ins('claim_versions', ['claim_id' => $id, 'version_no' => 1, 'total' => $c['total'], 'reason' => 'Pengajuan awal', 'snapshot' => jenc(['total' => $c['total'], 'notes' => ver_notes($id)]), 'created_at' => now()]);
    ex("UPDATE claims SET number=?, current_version=1, rule_set_id=?, avail_before=?, over_budget_amount=?, submitted_at=? WHERE id=?",
      [$number, $rule['id'], $bi['available'] ?? null, $bi ? min($c['total'], $bi['over'] + 0) : 0, now(), $id]);
    if ($bi && $bi['available'] < $c['total']) ex("UPDATE claims SET over_budget_amount=? WHERE id=?", [$c['total'] - max(0, $bi['available']) , $id]);
    $c = get_claim($id);
    [$vol, $volx] = ba_inputs($in); $trigs = array_values(array_unique(array_merge($chk['triggers'], $vol)));
    if ($trigs) {
      [$etxt, $emap, $eerr] = ba_explain($trigs, $in); if ($eerr) { db()->rollBack(); fail('Klaim ini wajib BA. ' . $eerr, 422); }
      ba_create($u, $c, $trigs, $etxt, 'PEMOHON', array_map('intval', $in['ba_files'] ?? []), (is_array($volx) ? $volx : []) + ['expl' => $emap]);
      $c = get_claim($id);
    }
    $route = build_route($c);
    ex("UPDATE claims SET route=?, status='WAIT', stage=?, stage_since=? WHERE id=?", [jenc($route), $route[0], now(), $id]);
    hist('claim', $id, 'Klaim dikirim', $u, null, null, $number . ' · ' . rp((int) $c['total']));
    $c = get_claim($id);
    notify_stage($route[0], $c, "Klaim $number menunggu pemeriksaan Anda.");
    db()->commit();
  } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
  return ['number' => $number, 'route' => $route];
}

/* ---------- aksi tahap ---------- */
function stage_owner_ok(array $u, array $c, string $stage): bool {
  if (sa_free($u)) return true;
  if (!has_role($u, $stage)) return false;
  if (!in_scope($u, $c, $stage)) return false;
  if (same_person($u, $c)) return false;
  return true;
}
function next_stage(array $c): ?string { $r = jdec($c['route']) ?: []; $i = array_search($c['stage'], $r, true); return $i === false ? null : ($r[$i + 1] ?? null); }

function claim_act(array $u, int $id, array $in, bool $auto = false): array {
  $c = get_claim($id); $act = $in['action'] ?? '';
  $notes = trim($in['notes'] ?? '');
  if ($act === 'rev_throw') {
    if ($c['status'] !== 'REV' || $c['rev_holder'] !== 'ADMIN') fail('Klaim tidak sedang di Admin.');
    if (!(has_role($u, 'ADMIN') && in_scope($u, $c, 'ADMIN') && !same_person($u, $c)) && !sa_free($u)) fail('Tidak diizinkan.', 403);
    ex("UPDATE claims SET rev_holder='PEMOHON', updated_at=? WHERE id=?", [now(), $id]);
    hist('claim', $id, 'Admin melempar revisi ke pemohon', $u, $notes ?: null, 'Perlu Revisi · di Admin', 'Perlu Revisi · di Pemohon');
    notify((int) $c['claimant_id'], 'revisi', "Klaim {$c['number']} perlu revisi (di Pemohon).", 'claim', $id);
    return ['ok' => 1];
  }
  if ($c['status'] !== 'WAIT') fail('Klaim tidak sedang menunggu keputusan.');
  $stage = $c['stage'];
  if (!stage_owner_ok($u, $c, $stage)) fail('Anda tidak berwenang pada tahap ini (atau ini klaim Anda sendiri).', 403);
  $role = $stage; $ver = (int) $c['current_version'];
  $star = sa_free($u) && !has_role($u, $stage) ? ' [MODE TES]' : '';
  if ($act === 'reject') {
    if ($notes === '') fail('Alasan penolakan wajib diisi.');
    ins('claim_approvals', ['ref_type' => 'claim', 'ref_id' => $id, 'version_no' => $ver, 'stage' => $stage, 'user_id' => $u['id'], 'action' => 'reject', 'notes' => $notes, 'created_at' => now()]);
    ex("UPDATE claims SET status='REJECTED', stage=NULL, done_at=?, updated_at=? WHERE id=?", [now(), now(), $id]);
    hist('claim', $id, 'Ditolak' . $star, $u, $notes, null, 'Ditolak final · reservasi budget dilepas', $role);
    notify((int) $c['claimant_id'], 'ditolak', "Klaim {$c['number']} ditolak oleh " . STAGE_NAME[$stage] . ".", 'claim', $id);
    return ['ok' => 1];
  }
  if ($act === 'revise') {
    if ($notes === '') fail('Catatan revisi wajib diisi.');
    $target = $in['target'] ?? 'PEMOHON';
    if (!in_array($target, ['ADMIN', 'PEMOHON'], true)) fail('Tujuan revisi tidak valid.');
    if ($stage === 'ADMIN' || $c['outside_sales']) $target = 'PEMOHON';
    $ln = [];
    foreach ((array) ($in['line_notes'] ?? []) as $k => $v) { $v = trim((string) $v); if ($v !== '' && (int) $k > 0) $ln[(int) $k] = mb_substr($v, 0, 300); }
    if ($ln) { $notes .= "\n" . implode("\n", array_map(fn($k) => "Nota $k: " . $ln[$k], array_keys($ln))); }
    ins('claim_approvals', ['ref_type' => 'claim', 'ref_id' => $id, 'version_no' => $ver, 'stage' => $stage, 'user_id' => $u['id'], 'action' => 'revise', 'notes' => $notes, 'created_at' => now()]);
    ex("UPDATE claims SET rev_notes=" . ($ln ? "'" . str_replace("'", "''", jenc($ln)) . "'" : 'NULL') . ", status='REV', rev_stage=?, rev_holder=?, rev_by=?, rev_note=?, stage=NULL, stage_since=?, updated_at=? WHERE id=?", [$stage, $target, $u['id'], $notes, now(), now(), $id]);
    $pos = 'Perlu Revisi · di ' . ($target === 'ADMIN' ? 'Admin' : 'Pemohon');
    hist('claim', $id, 'Minta revisi' . $star, $u, $notes, null, $pos . ' (diminta ' . STAGE_NAME[$stage] . ')', $role);
    if ($target === 'ADMIN') foreach (stage_users('ADMIN', $c['region'], $c['entity']) as $a) { if ($a['id'] != $c['claimant_id']) notify($a['id'], 'revisi', "Klaim {$c['number']} perlu revisi (di Admin).", 'claim', $id); }
    else notify((int) $c['claimant_id'], 'revisi', "Klaim {$c['number']} perlu revisi (di Pemohon): $notes", 'claim', $id);
    return ['ok' => 1];
  }
  if ($act === 'admin_ba') { // temuan Admin -> Admin membuat BA
    if ($stage !== 'ADMIN') fail('BA temuan hanya dibuat Admin saat pemeriksaan.');
    $types = array_values(array_intersect(ADMIN_BA_TYPES, $in['types'] ?? ['TEMUAN_ADMIN'])); if (!$types) fail('Pilih minimal satu jenis BA.');
    [$reason, $emap, $eerr] = ba_explain($types, $in, 'explanations', 'explanation'); if ($eerr) fail($eerr, 422);
    $ent = trim((string) ($in['entity'] ?? ''));
    if ($ent === '' || !in_array($ent, entity_options(), true)) fail('Pilih badan usaha dulu: BA temuan Admin sekaligus menyetujui klaim.', 422);
    ba_create($u, $c, $types, $reason, 'ADMIN', array_map('intval', $in['files'] ?? []), (is_array($in['extra'] ?? null) ? $in['extra'] : []) + ['expl' => $emap]);
    // BA temuan Admin = Admin otomatis menandatangani dan menyetujui; klaim langsung ke HoS, menunggu TTD Pemohon.
    $ap = ['action' => 'approve', 'entity' => $ent, 'refnote' => $in['refnote'] ?? '', 'notes' => 'Otomatis saat Admin membuat BA temuan'];
    foreach (['perdin_check'] as $k) if (isset($in[$k])) $ap[$k] = $in[$k];
    claim_act($u, $id, $ap, true);
    notify((int) $c['claimant_id'], 'info', "Admin membuat BA untuk klaim {$c['number']}. Mohon tanda tangani BA.", 'claim', $id);
    return ['ok' => 1];
  }
  if ($act === 'transfer') {
    if ($stage !== 'FT') fail('Belum giliran Finance Transfer.');
    if ($c['ba_required'] && !ba_complete($c)) fail('Transfer tidak dapat dicatat: tanda tangan BA belum lengkap untuk versi terbaru.', 422);
    $no = trim($in['transfer_no'] ?? ''); $proof = array_map('intval', $in['proof'] ?? []);
    if ($no === '') fail('Nomor transfer wajib.'); if (!$proof) fail('Bukti transfer wajib diunggah.');
    ins('claim_transfers', ['ref_type' => 'claim', 'ref_id' => $id, 'transfer_no' => $no, 'amount' => $c['total'], 'user_id' => $u['id'], 'entity' => $c['entity'], 'created_at' => now()]);
    link_files($proof, 'claim', $id, null, 'transfer');
    ins('claim_approvals', ['ref_type' => 'claim', 'ref_id' => $id, 'version_no' => $ver, 'stage' => 'FT', 'user_id' => $u['id'], 'action' => 'transfer', 'notes' => $no, 'created_at' => now()]);
    ex("UPDATE claims SET status='DONE', stage=NULL, done_at=?, updated_at=? WHERE id=?", [now(), now(), $id]);
    hist('claim', $id, 'Transfer dicatat' . $star, $u, $no, null, rp((int) $c['total']), 'FT');
    notify((int) $c['claimant_id'], 'selesai', "Klaim {$c['number']} sudah ditransfer (" . rp((int) $c['total']) . ").", 'claim', $id);
    return ['ok' => 1];
  }
  if ($act !== 'approve') fail('Aksi tidak dikenal.');
  if ($stage === 'FT') fail('Finance Transfer mencatat transfer, bukan menyetujui.');
  if (!$auto && ba_pemohon_pending($c)) fail('Belum bisa disetujui: Pemohon belum menandatangani BA. Klaim kembali ke Anda setelah Pemohon tanda tangan.', 422);
  if ($stage === 'ADMIN') {
    $ent = trim((string) ($in['entity'] ?? ''));
    if ($ent === '') fail('Pilih badan usaha dulu sebelum menyetujui.', 422);
    $rn = trim((string) ($in['refnote'] ?? '')) ?: trim((string) $c['refnote']);
    if ($rn === '') fail('Isi Refnote dulu sebelum menyetujui.', 422);
    if ($rn !== trim((string) $c['refnote'])) { ex("UPDATE claims SET refnote=? WHERE id=?", [$rn, $id]); hist('claim', $id, 'Admin mengisi Refnote', $u, null, $c['refnote'] ?: '-', $rn, $role); $c = get_claim($id); }
    if (!in_array($ent, entity_options(), true)) fail('Badan usaha tidak dikenal.', 422);
    if ($ent !== ($c['entity'] ?? '') || (int) ($c['entity_by'] ?? 0) !== (int) $u['id']) {
      ex("UPDATE claims SET entity=?, entity_by=? WHERE id=?", [$ent, $u['id'], $id]);
      if ($ent !== ($c['entity'] ?? '')) hist('claim', $id, 'Admin memilih badan usaha ' . $ent, $u, null, $c['entity'] ?: '-', $ent, $role);
      $c = get_claim($id);
    }
    $kind = form_kind(type_row((int) $c['type_id'])['name']);
    if (in_array($kind, ['perdin', 'makan'], true)) {
      $chk = $in['perdin_check'] ?? [];
      foreach (['titik', 'jarak', 'durasi'] as $k) if (empty($chk[$k])) fail('Checklist pemeriksaan rute Perdin belum lengkap.', 422);
      ex("UPDATE claims SET admin_check=? WHERE id=?", [jenc(['check' => $chk, 'by' => $u['name'], 'at' => now()]), $id]);
    }
    if (!empty($in['need_hos']) && !$c['ba_required']) {
      if (trim($in['hos_reason'] ?? '') === '') fail('Alasan memerlukan HOS wajib diisi.');
      ex("UPDATE claims SET requires_hos=1, hos_reason=? WHERE id=?", [trim($in['hos_reason']), $id]);
      hist('claim', $id, 'Admin menetapkan perlu HOS', $u, trim($in['hos_reason']));
    }
    $c = get_claim($id); ex("UPDATE claims SET route=? WHERE id=?", [jenc(build_route($c)), $id]); $c = get_claim($id);
  }
  ins('claim_approvals', ['ref_type' => 'claim', 'ref_id' => $id, 'version_no' => $ver, 'stage' => $stage, 'user_id' => $u['id'], 'action' => 'approve', 'notes' => $notes ?: null, 'created_at' => now()]);
  if ($c['ba_required'] && ($ba = ba_row($id)) && in_array($stage, ['ADMIN', 'AUDIT', 'HOS', 'FM'], true)) { must_sign($u); ba_sign($ba, $stage, $u); }
  $next = next_stage($c);
  if (!$next) fail('Rute tidak valid.');
  ex("UPDATE claims SET stage=?, stage_since=?, updated_at=? WHERE id=?", [$next, now(), now(), $id]);
  hist('claim', $id, 'Disetujui ' . STAGE_NAME[$stage] . $star, $u, $notes ?: null, null, 'Lanjut ke ' . STAGE_NAME[$next], $role);
  if (!$auto) notify_stage($next, $c, "Klaim {$c['number']} menunggu " . ($next === 'FT' ? 'transfer' : 'persetujuan') . " Anda.");
  return ['ok' => 1, 'next' => $next];
}

/* ---------- perbaikan selesai ---------- */
function claim_resubmit(array $u, int $id, array $in): array {
  $c = get_claim($id);
  if ($c['status'] !== 'REV') fail('Klaim tidak sedang perlu revisi.');
  if (!owner_can_edit($u, $c) && !sa_free($u)) fail('Tidak diizinkan.', 403);
  if (!empty($in['payload'])) { $in['payload']['id'] = $id; claim_save($u, $in['payload']); $c = get_claim($id); }
  $chk = claim_check($c);
  if ($chk['errors']) fail('Lengkapi dulu: ' . implode(' ', array_slice($chk['errors'], 0, 4)), 422);
  $ver = (int) $c['current_version'];
  $lastTotal = (int) val("SELECT total FROM claim_versions WHERE claim_id=? AND version_no=?", [$id, $ver]);
  $changed = $lastTotal !== (int) $c['total'];
  $holder = $c['rev_holder']; $isAdminFix = $holder === 'ADMIN';
  $actorIsPemohon = same_person($u, $c);
  db()->beginTransaction();
  try {
    if ($changed) {
      $ver++;
      ins('claim_versions', ['claim_id' => $id, 'version_no' => $ver, 'total' => $c['total'], 'reason' => 'Perubahan nominal ' . rp($lastTotal) . ' → ' . rp((int) $c['total']), 'snapshot' => jenc(['total' => $c['total'], 'notes' => ver_notes($id)]), 'created_at' => now()]);
      $bi = $chk['budget'];
      ex("UPDATE claims SET current_version=?, avail_before=?, over_budget_amount=? WHERE id=?", [$ver, $bi['available'] ?? null, $bi ? max(0, (int) $c['total'] - max(0, $bi['available'] + 0)) : 0, $id]);
      hist('claim', $id, 'Versi baru v' . $ver, $u, 'Approval versi lama tidak berlaku', rp($lastTotal), rp((int) $c['total']));
    }
    $c = get_claim($id);
    if ($ba = ba_row($id)) {
      if ($changed) { ex("UPDATE claim_ba SET version_no=? WHERE id=?", [$ver, $ba['id']]); hist('claim', $id, 'BA dan tanda tangan versi lama tidak berlaku', $u, null, 'BA v' . ($ver - 1), 'BA v' . $ver . ' perlu ditandatangani ulang'); $ba = ba_row($id); }
      if ($actorIsPemohon && !$c['outside_sales']) ba_sign($ba, 'PEMOHON', $u); elseif ($c['outside_sales']) ba_sign($ba, 'PEMOHON', $u);
    } elseif (($trigs = array_values(array_unique(array_merge($chk['triggers'], ba_inputs($in)[0])))) !== []) {
      [$etxt, $emap, $eerr] = ba_explain($trigs, $in); if ($eerr) { db()->rollBack(); fail('Perubahan ini memicu BA. ' . $eerr, 422); }
      ba_create($u, $c, $trigs, $etxt, $actorIsPemohon ? 'PEMOHON' : 'ADMIN', array_map('intval', $in['ba_files'] ?? []), (is_array(ba_inputs($in)[1]) ? ba_inputs($in)[1] : []) + ['expl' => $emap]);
      $c = get_claim($id);
    }
    $route = build_route($c);
    $resume = $c['rev_stage'];
    if ($changed && ba_row($id)) $resume = $route[0]; // BA: semua penanda tangan menandatangani ulang versi baru
    elseif ($changed) foreach (['HOS', 'AUDIT'] as $s) { // tahap yang wajib menyetujui versi terbaru
      if (in_array($s, $route, true) && (!in_array($resume, $route, true) || array_search($s, $route) < array_search($resume, $route))) { $resume = $s; break; }
    }
    if (!in_array($resume, $route, true)) $resume = $route[0];
    ex("UPDATE claims SET route=?, status='WAIT', stage=?, stage_since=?, rev_holder=NULL, rev_stage=NULL, updated_at=? WHERE id=?", [jenc($route), $resume, now(), now(), $id]);
    hist('claim', $id, 'Perbaikan selesai' . ($isAdminFix ? ' (dilengkapi Admin)' : ' (oleh pemohon)'), $u, null, 'Perlu Revisi', 'Kembali ke ' . STAGE_NAME[$resume]);
    $c = get_claim($id);
    notify_stage($resume, $c, "Klaim {$c['number']} kembali menunggu " . STAGE_NAME[$resume] . " (perbaikan selesai).");
    db()->commit();
  } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
  return ['ok' => 1, 'resume' => $resume, 'version' => $ver, 'nominal_changed' => $changed];
}
function ba_pemohon_sign(array $u, int $id): void {
  $c = get_claim($id); $ba = ba_row($id);
  if (!$ba || !same_person($u, $c)) fail('Tidak diizinkan.', 403);
  if (in_array($c['status'], ['REJECTED', 'DONE'], true)) fail('Klaim sudah final.');
  must_sign($u); ba_sign($ba, 'PEMOHON', $u); hist('claim', $id, 'Pemohon menandatangani BA v' . $ba['version_no'], $u, null, null, $c['stage'] ? 'Lanjut: ' . STAGE_NAME[$c['stage']] . ' memproses' : null);
  if ($c['status'] === 'WAIT' && $c['stage']) notify_stage($c['stage'], $c, "Pemohon sudah menandatangani BA klaim {$c['number']}. Silakan lanjutkan " . ($c['stage'] === 'FT' ? 'transfer' : 'pemeriksaan') . ".");
}
function claim_cancel(array $u, int $id): void {
  $c = get_claim($id);
  if (!same_person($u, $c) || !in_array($c['status'], ['DRAFT', 'REV'], true)) fail('Klaim ini tidak dapat dibatalkan.', 403);
  if ($c['status'] === 'DRAFT') { ex("DELETE FROM claim_notes WHERE claim_id=?", [$id]); ex("DELETE FROM claims WHERE id=?", [$id]); return; }
  ex("UPDATE claims SET status='REJECTED', stage=NULL, done_at=? WHERE id=?", [now(), $id]);
  hist('claim', $id, 'Dibatalkan pemohon', $u, null, null, 'Reservasi budget dilepas');
}

/* ---------- follow-up (Hubungi) ---------- */
const FOLLOWUP_DAYS = 1;
function followup_ok(array $u, array $c): bool {
  return same_person($u, $c) && $c['status'] === 'WAIT' && $c['stage'] && !ba_pemohon_pending($c) && $c['stage_since'] && (time() - strtotime($c['stage_since'])) > FOLLOWUP_DAYS * 86400;
}
function followup_info(array $u, int $id): array {
  $c = get_claim($id); if (!followup_ok($u, $c)) fail('Tombol hubungi muncul setelah klaim lebih dari ' . FOLLOWUP_DAYS . ' hari menunggu di tahap yang sama.', 422);
  $days = (int) floor((time() - strtotime($c['stage_since'])) / 86400); $st = STAGE_NAME[$c['stage']];
  $people = [];
  foreach (stage_users($c['stage'], $c['region'], $c['entity']) as $p) { if ((int) $p['id'] === (int) $c['claimant_id']) continue;
    $people[] = ['id' => (int) $p['id'], 'name' => $p['name'], 'job' => $p['job'], 'region' => $p['region'], 'wa' => $p['wa'] ?? null]; }
  $msg = fn($nm) => "Halo $nm, mohon dibantu cek klaim {$c['number']} (" . claim_type_label($c) . ', ' . rp((int) $c['total']) . ") yang menunggu persetujuan $st sejak $days hari. Terima kasih. - {$u['name']}";
  foreach ($people as &$p) $p['text'] = $msg($p['name']); unset($p);
  $today = (bool) val("SELECT 1 FROM claim_history WHERE ref_type='claim' AND ref_id=? AND action LIKE 'Pengingat dikirim%' AND substr(created_at,1,10)=?", [$id, date('Y-m-d')]);
  return ['stage' => $st, 'days' => $days, 'people' => $people, 'app_sent_today' => $today];
}
function followup_log(array $u, int $id, string $via, int $to = 0): void {
  $c = get_claim($id); $info = followup_info($u, $id); $st = $info['stage'];
  if ($via === 'app') {
    if ($info['app_sent_today']) fail('Pengingat di aplikasi untuk klaim ini sudah dikirim hari ini.', 422);
    foreach ($info['people'] as $p) notify($p['id'], 'info', "Pengingat: klaim {$c['number']} menunggu persetujuan Anda sejak {$info['days']} hari.", 'claim', $id);
    hist('claim', $id, 'Pengingat dikirim ke ' . $st, $u, 'lewat notifikasi aplikasi');
  } else {
    $p = current(array_filter($info['people'], fn($x) => $x['id'] === $to)); if (!$p) fail('Penyetuju tidak ditemukan.', 404);
    hist('claim', $id, 'Pemohon menghubungi ' . $p['name'] . ' (' . $st . ')', $u, 'lewat WhatsApp untuk follow-up');
  }
}

function ver_notes(int $id): array { $o = []; foreach (q("SELECT line_no, nominal FROM claim_notes WHERE claim_id=? ORDER BY line_no", [$id]) as $r) $o[(string) (int) $r['line_no']] = (int) $r['nominal']; return $o; }
