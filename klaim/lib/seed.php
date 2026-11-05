<?php
require_once __DIR__ . '/core.php';
/** Baris akun uji: username, nama, job, region, entity, roles, can_submit, bank, no, an, petty, plafon, tarif makan. */
function demo_user_rows(): array {
  return [
    // username, nama, job, region, entity, roles, can_submit, bank, no, an, petty, plafon, tarif makan
    ['sa', 'Super Admin', 'Super Admin', null, null, 'SA', 0, null, null, null, 0, 0, null],
    ['admin', 'Rina', 'Admin', 'Cirebon', 'RISM', 'ADMIN', 0, null, null, null, 0, 0, null],
    ['admin2', 'Sinta', 'Admin', 'Jakarta', 'MSI', 'ADMIN', 0, null, null, null, 0, 0, null],
    ['audit', 'Andi', 'Audit', null, 'RISM,MSI', 'AUDIT', 0, null, null, null, 0, 0, null],
    ['hos', 'Hadi', 'HOS', null, null, 'HOS', 0, null, null, null, 0, 0, null],
    ['fm', 'Maya', 'Finance Manager', null, 'RISM,MSI', 'FM', 0, null, null, null, 0, 0, null],
    ['ft', 'Fitri Handayani', 'Finance Transfer', null, 'RISM', 'FT', 0, null, null, null, 0, 0, null],
    ['ft2', 'Yoga Pratama', 'Finance Transfer', null, 'MSI', 'FT', 0, null, null, null, 0, 0, null],
    ['rgm', 'Budi Santoso', 'RGM', 'Cirebon', 'RISM', 'RGM', 1, 'BCA', '5120334455', 'Budi Santoso', 1, 5000000, 75000],
    ['asm', 'Joko Widodo', 'ASM', 'Cirebon', 'RISM', '', 1, 'BRI', '0231456789', 'Joko Widodo', 1, 4000000, 60000],
    ['dewi', 'Dewi Lestari', 'Sales', 'Cirebon', 'RISM', '', 1, 'BCA', '4821907766', 'Dewi Lestari', 1, 3000000, 50000],
    ['eko', 'Eko Prasetyo', 'Sales', 'Cirebon', 'RISM', '', 1, 'Mandiri', '1300098877', 'Eko Prasetyo', 0, 0, 50000],
    ['rudi', 'Rudi Hartono', 'Sales', 'Jakarta', 'MSI', '', 1, 'BNI', '0098123456', 'Rudi Hartono', 0, 0, 55000],
  ];
}
/** Satu akun uji + hak anggaran. Mengembalikan id pengguna. */
function insert_user_row(array $r, string $pw, ?string $nik = null, string $username = null): int {
  $id = ins('claim_users', ['username' => $username ?? $r[0], 'pass_hash' => $pw, 'name' => $r[1], 'job' => $r[2], 'region' => $r[3], 'entity' => $r[4], 'roles' => $r[5], 'can_submit' => $r[6],
    'bank' => $r[7], 'bank_no' => $r[8], 'bank_name' => $r[9], 'can_petty' => $r[10], 'petty_ceiling' => $r[11], 'meal_rate' => $r[12], 'active' => 1, 'created_at' => now()]);
  ex("UPDATE claim_users SET nik=? WHERE id=?", [$nik ?? (($r[4] ?: 'DSM') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT)), $id]);
  if ($r[6]) foreach (['Transportasi' => 2000000, 'Entertain' => 1500000, 'Perdin' => 3000000, 'Operasional' => 1000000] as $cat => $b) ins('claim_entitlement', ['user_id' => $id, 'category' => $cat, 'enabled' => 1, 'budget' => $b]);
  return $id;
}
/** Akun uji untuk Super Admin (Ganti peran). Tidak bisa dipakai login: NIK berawalan UJI- tidak ada di DSM. Idempoten. */
function seed_uji_users(): int {
  $pw = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT); $n = 0;
  foreach (demo_user_rows() as $r) {
    if ($r[0] === 'sa') continue;
    $nik = 'UJI-' . strtoupper($r[0]); if (val("SELECT 1 FROM claim_users WHERE nik=?", [$nik])) { ex("UPDATE claim_users SET active=1 WHERE nik=?", [$nik]); continue; }
    $r[1] .= ' (uji)'; insert_user_row($r, $pw, $nik, 'uji-' . $r[0]); $n++;
  }
  return $n;
}
/** Daftar jenis klaim yang berlaku (urutan = urutan tampil). Nilai 1 = terkunci (tidak bisa dimatikan Admin). */
const TYPE_SPEC = [
  'Transportasi' => ['BBM' => 1, 'Tol' => 0, 'Parkir' => 0, 'Ojol & taksi online' => 0, 'Tiket perjalanan' => 0],
  'Entertain' => ['Meeting' => 0, 'Makan-makan' => 0, 'Kue ulang tahun' => 0, 'Karangan bunga' => 0, 'Reward / Hadiah' => 0],
  'Perdin' => ['Perdin' => 1, 'Uang makan' => 0],
  'Operasional' => ['Kost' => 0, 'Penginapan / Hotel' => 0, 'ATK & perlengkapan kantor' => 0, 'Ongkir & kurir' => 0, 'Servis kendaraan' => 0, 'Operasional lainnya' => 0],
];
/** Jenis lama yang dipensiunkan (dinonaktifkan, tidak dihapus agar klaim lama tetap utuh). */
const TYPE_RETIRED = [['Transportasi', 'Servis kendaraan'], ['Operasional', 'Listrik, air & internet'], ['Operasional', 'Kebersihan & keamanan'], ['Operasional', 'Perbaikan & maintenance']];
/** Aman dijalankan berulang: menambah jenis yang belum ada, mengaktifkan yang berlaku, menonaktifkan yang dipensiunkan. Jenis buatan Admin tidak disentuh. */
function sync_claim_types(): array {
  $log = []; $s = 0;
  foreach (TYPE_SPEC as $cat => $list) foreach ($list as $name => $lock) {
    $s++; $r = one("SELECT id, active FROM claim_types WHERE category=? AND name=?", [$cat, $name]);
    if (!$r) { ins('claim_types', ['category' => $cat, 'name' => $name, 'active' => 1, 'locked' => $lock, 'sort_no' => $s]); $log[] = "+ $cat / $name"; }
    else { ex("UPDATE claim_types SET sort_no=? WHERE id=?", [$s, $r['id']]); }
  }
  foreach (TYPE_RETIRED as [$cat, $name]) {
    $keep = isset(TYPE_SPEC[$cat][$name]);
    if ($keep) continue;
    $n = ex("UPDATE claim_types SET active=0 WHERE category=? AND name=? AND active=1", [$cat, $name]); if ($n) $log[] = "- $cat / $name (dinonaktifkan)";
  }
  return $log;
}
/** Data awal: pengguna uji, jenis klaim, daftar kota/brand/bank. Angka konfigurasi BA sengaja dikosongkan. */
function seed_all(bool $demo = true): void {
  $pw = password_hash('123456', PASSWORD_DEFAULT);
  $U = demo_user_rows();
  if ($demo) foreach ($U as $r) insert_user_row($r, $pw);
  sync_claim_types();
  ins('claim_types', ['category' => 'Operasional', 'name' => 'Reimburse petty cash', 'active' => 0, 'locked' => 1, 'sort_no' => 99]);
  $kota = ['Cirebon', 'Kuningan', 'Majalengka', 'Indramayu', 'Brebes', 'Tegal', 'Pemalang', 'Pekalongan', 'Semarang', 'Salatiga', 'Surakarta', 'Yogyakarta', 'Magelang', 'Purwokerto', 'Cilacap', 'Bandung', 'Cimahi', 'Sumedang', 'Garut', 'Tasikmalaya', 'Ciamis', 'Banjar', 'Subang', 'Purwakarta', 'Karawang', 'Bekasi', 'Bogor', 'Depok', 'Jakarta Pusat', 'Jakarta Barat', 'Jakarta Selatan', 'Jakarta Timur', 'Jakarta Utara', 'Tangerang', 'Serang', 'Surabaya', 'Malang', 'Denpasar', 'Medan', 'Makassar'];
  foreach ($kota as $k) ins('claim_lookup', ['kind' => 'kota', 'val' => $k]);
  foreach (['Samsung', 'Oppo', 'Vivo', 'Xiaomi', 'Realme', 'Infinix', 'Tecno', 'Honor', 'Apple', 'Lainnya'] as $b) ins('claim_lookup', ['kind' => 'brand', 'val' => $b]);
  foreach (['BCA', 'BRI', 'BNI', 'Mandiri', 'CIMB Niaga', 'Permata', 'BSI', 'Danamon'] as $b) ins('claim_lookup', ['kind' => 'bank', 'val' => $b]);
  ins('claim_rules', ['effective_from' => '2026-01-01', 'created_at' => now()]);
  if ($demo) ins('claim_fuel_price', ['effective_from' => '2000-01-01', 'pertalite' => 10000, 'pertamax' => 20000, 'created_by' => 1, 'created_at' => now()]); // DATA TES: ganti lewat Super Admin → Harga BBM
  set_setting('test_mode', $demo ? '1' : '0'); // produksi (tanpa akun uji): Mode tes mati
}
