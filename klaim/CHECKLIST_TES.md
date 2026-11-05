# Checklist Tes DSM Claim (versi terbaru, 29 Okt 2026)

Cara pakai: kerjakan berurutan. Tandai ✅ kalau sesuai, ❌ kalau tidak, lalu tulis catatannya.
Kalau ada yang bengkok, kirim ke Claude: **nomor langkah + layar + yang diharapkan vs yang terjadi** (screenshot lebih bagus).

Akun tes (password semua `123456`):
`sa` Super Admin · `admin` Rina (Admin Cirebon/RISM) · `admin2` Sinta (Admin Jakarta/MSI) · `audit` Andi · `hos` Hadi · `fm` Maya · `ft` Finance RISM · `ft2` Finance MSI · `rgm` Budi · `dewi` Dewi Lestari (Sales) · `eko` · `rudi`

Catatan penting:
- Super Admin (`sa`) dalam MODE TES bebas bertindak di tahap mana pun (riwayat ditandai [MODE TES]). Untuk tes batasan asli, login langsung dengan akun perannya, atau Menu SA → "Masuk sebagai".
- Ambang BA, N hari "menunggu lama", tarif uang makan, dan budget di data awal hanyalah data tes. Ambang BA sengaja kosong sampai diisi.
- Tampilan dites di HP (lebar ±390px) dan desktop.

---

## 0. Persiapan
1. Timpa semua file terbaru ke `C:\laragon\www\dsm-claim`. ☐
2. Buka `localhost/dsm-claim/install.php` → buat database. **Wajib diulang** setiap ada perubahan tabel (data tes ter-reset). ☐
3. Install tidak error SQL (kalau error, kirim pesan persisnya). ☐
4. Login `sa` → Menu → Pengaturan: Mode Tes = ON. ☐
5. Menu SA → Pengguna: ganti nama akun Finance jadi nama orang (mis. "Fitri Handayani"), isi kolom NIK bila perlu. ☐

## 1. Klaim normal (BBM)
1. `dewi` → Ajukan → BBM. Isi brand, refnote, lokasi (diketik manual bebas), foto odometer awal dan akhir, 1 nota + foto. ☐
2. Kolom **Keterangan tambahan (opsional)** ada di form (boleh kosong, boleh diisi); isinya tampil di detail klaim dan di PDF hanya bila diisi. ☐
3. Di Ringkasan, kotak **TOTAL NOMINAL DIPROSES** (kuning-oranye) tampil, lalu KIRIM KLAIM. ☐ Nomor `KLM-RISM-YYMM-0001`.
4. Buka detail klaim: ada kotak **Posisi sekarang** ("Sekarang di Admin… Setelah itu: Finance Manager → Finance Transfer"). ☐
4. `admin` (Rina) → Antrean → buka klaim → total highlight di atas → SETUJUI. ☐
5. `fm` (Maya) → SETUJUI. ☐
6. `ft` → Siap transfer → isi nomor transfer + unggah foto bukti → CATAT TRANSFER. ☐ Status Selesai.
7. Detail klaim (cek sebagai `dewi` dan `admin`): bagian **Transfer** menampilkan nomor, nama Finance, waktu, foto bukti. ☐
8. Klik UNDUH PDF KLAIM. Cek: ☐
   - kotak TOTAL NOMINAL DIPROSES + terbilang (mis. "Empat Puluh Satu Ribu Rupiah"),
   - rekening penuh, watermark nama pengunduh + waktu,
   - riwayat proses bersih; "PDF diunduh" tidak mencampur alur, adanya di tabel **Log unduhan** terpisah,
   - halaman terakhir **Bukti transfer** (nominal, terbilang, nomor, waktu, nama Finance, foto).
9. Batasan: `dewi` tidak bisa menyetujui klaim sendiri; `admin2` (Jakarta) tidak bisa membuka klaim Cirebon. ☐

## 2. Revisi
1. `dewi` ajukan klaim baru. `fm` (setelah Admin setuju) → MINTA REVISI, pilih tujuan: **Admin** atau **Pemohon**, isi catatan. ☐
2. Tujuan Admin: `admin` bisa **lengkapi sendiri** atau **lempar ke pemohon**. ☐
3. Tujuan Pemohon: `dewi` perbaiki lalu kirim ulang. Klaim kembali ke tahap yang meminta (bukan mulai dari awal). ☐
4. `dewi` ubah **nominal** saat revisi → versi baru (v2); persetujuan lama tampil "versi lama · outdated". ☐
5. Riwayat mencatat tiap langkah sampai detik. ☐

## 3. Perdin
1. `dewi` → Ajukan → Perdin. Isi rute (min. 2 titik), **1 screenshot Google Maps**, total jarak. ☐
2. Uang makan otomatis (tarif × durasi), terkunci. ☐
3. Transportasi / Penginapan mode **Per hari**: **lampiran nota opsional** (label "· opsional"), bisa kirim tanpa nota. ☐
4. Transportasi / Penginapan mode **Sekali** dan Entertain: nota tetap wajib. ☐
5. Jumlah hari tidak boleh melebihi durasi perjalanan. ☐
6. `admin`: tanpa centang checklist rute (titik, jarak, durasi) → SETUJUI ditolak. Setelah dicentang → berhasil. ☐
7. Total di form = total di PDF. ☐

### Klaim saya: Perlu tindakan saya
1. Di **Klaim saya** ada pilihan **Daftar klaim / Perlu tindakan saya** di bawah tombol FILTER. Pilihan kedua hanya menampilkan klaim milik sendiri yang menunggu **Tanda tangan BA** atau **Perbaiki klaim** (revisi), tiap baris diberi label tindakannya. Antrean peran lain (Admin, HOS, dst.) tidak ikut tampil di sini. ☐

### Peserta Meeting dan acara
1. Form Meeting, Makan-makan, Kue ulang tahun: tiap peserta punya kolom **Nama** dan **Jabatan / asal**. Kirim dengan jabatan kosong → ditolak dengan pesan "Peserta N: jabatan atau asal wajib diisi". Detail klaim dan PDF menampilkan nama + jabatan. ☐

### 3b. Perdin · Uang makan (klaim reimburse)
1. Jalankan `php klaim/migrate.php` sekali (menambah jenis **Uang makan** di kategori Perdin). Ajukan klaim → Perdin → ada dua jenis: Perdin dan Uang makan. ☐
2. Isi tanggal berangkat/pulang, tujuan, rute, 1 screenshot Maps, jarak < 80 km → muncul pesan merah, kirim ditolak. Jarak ≥ 80 km → pesan hijau. ☐
3. Tambah nota makan (tanggal harus dalam rentang berangkat–pulang, foto nota wajib). Bar batas = tarif per hari × jumlah hari (tarif dari Hak klaim). Total melebihi batas → bar merah, kirim ditolak. ☐
4. Admin wajib centang pemeriksaan rute (titik, jarak, durasi) seperti Perdin; detail klaim dan PDF menampilkan data perjalanan. ☐

## 4. Berita Acara (BA)
Tab **BA** di bottom nav untuk Admin, HOS, FM (Audit: "Antrean BA" di Beranda).

### 4a. BA dibuat Admin (temuan)
1. `dewi` ajukan klaim BBM biasa. `admin` buka klaim → **Buat BA (temuan Admin)**. ☐
2. Pilih **jenis BA** (boleh lebih dari satu): Kehilangan nota / Ketidaksesuaian nominal / Temuan lain. Tanpa memilih → ditolak. Penjelasan < 10 karakter → ditolak. Tambah lampiran foto bukti. ☐
3. Syarat: **badan usaha wajib dipilih dulu** (BA Admin otomatis menandatangani dan menyetujui). Tanpa badan usaha → ditolak. Admin harus sudah punya TTD (menu **Tanda tangan saya**). ☐
4. Setelah dibuat, klaim langsung ke **HOS**, status **"Menunggu TTD BA Pemohon"**. HOS **tidak bisa** menyetujui dulu (pesan jelas). Berlaku juga untuk Perdin. ☐
5. `dewi` dapat notifikasi → buka klaim → TANDA TANGANI BA → muncul sheet dengan TTD tersimpan → TANDA TANGANI. Bila TTD belum diisi, sheet mengarahkan ke **Tanda tangan saya**. ☐
6. `hos` dapat notifikasi, status "Menunggu HOS" → SETUJUI. Lanjut berurutan: `audit` → `fm` → `ft`. Tiap tahap: kotak Posisi sekarang menunjukkan **TTD BA yang masih ditunggu**. ☐
7. Klaim BA muncul di tab **BA**, bukan di Antrean biasa. Badge angka di tab BA sesuai. ☐
8. `ft` tidak bisa transfer bila TTD BA belum lengkap. ☐

### 4a-2. BA dibuat Pemohon (over budget / pemicu lain)
1. Pemohon tanpa TTD: kirim klaim yang butuh BA **diblokir** (tombol abu-abu + tautan "Atur sekarang"). Klaim tanpa BA tetap bisa dikirim. ☐
2. Setelah TTD diisi: kirim → TTD pemohon otomatis. Rute: **Admin → HOS → Audit → FM → FT**. ☐
3. Approver yang belum punya TTD tidak bisa menyetujui klaim BA (pesan: isi di Tanda tangan saya). ☐

### 4a-3. Tanda tangan (menu → Tanda tangan saya)
1. **Gambar di layar**: gambar dengan jari/stylus → SIMPAN. Tersimpan, tampil di bagian atas. HAPUS membersihkan kotak. ☐
2. **Unggah foto**: pilih foto TTD di kertas putih → pratinjau latar transparan → SIMPAN. ☐
3. Ganti TTD kemudian: BA lama tetap menampilkan TTD yang dulu dipakai (snapshot). ☐
4. PDF BA: kotak tanda tangan berurutan sesuai pembuat BA (Admin dulu bila dibuat Admin; Pemohon dulu bila dibuat Pemohon), lalu HOS, Audit, FM, dengan gambar TTD, nama, waktu. ☐
5. Nominal berubah pada klaim BA → semua penanda tangan mengulang dari Admin untuk versi baru. ☐

### 4b. BA otomatis (over budget)
1. `dewi` ajukan nominal melebihi sisa budget → wajib isi penjelasan BA (min. 10 karakter). ☐
2. Pemohon otomatis sudah tanda tangan; alur: Admin → Audit → HOS → FM → FT. ☐
3. Ubah nominal setelah BA → BA naik versi, semua tanda tangan diulang. ☐
4. Menu Aturan (Admin/SA): isi ambang (mis. nota lama, biaya per km) → klaim yang melanggar memicu BA. ☐

### 4c. PDF BA
1. Buka PDF BA (tombol PDF BA). Cek sesuai mockup: ☐
   - header (BU, region, nomor BA, tanggal, nomor klaim),
   - **badge jenis BA berwarna** (nota oranye, nominal merah, KM biru),
   - Data pihak: Nama pemohon / **NIK · Jabatan** / Region · BU / Pembuat BA,
   - Data klaim + nominal + terbilang, selisih budget,
   - penjelasan, kalimat penutup "Demikian berita acara ini dibuat…",
   - kota-tanggal + kotak tanda tangan elektronik (Pemohon, Admin, Audit, HOS, FM),
   - footer: nomor halaman, nama pengunduh, waktu cetak.
2. Lampiran bukti di **halaman berikutnya**, maksimal **3 foto per halaman** (coba lampirkan 4 foto → 2 halaman lampiran). ☐
3. Di PDF klaim, baris riwayat terkait BA berwarna oranye (highlight). ☐

## 5. Nota ganda
Unggah foto nota yang sama, atau nota dengan pemohon + jenis + tanggal + nominal sama → muncul peringatan "bentrok", tidak bisa kirim. Nota dari klaim yang Ditolak/dibatalkan tidak dihitung. ☐

## 6. Petty cash
1. `dewi` → Petty cash → Ajukan: tujuan, lokasi, keterangan tambahan (opsional), periode, rencana (total ≤ plafon). Total yang tampil sama dengan jumlah rencana. ☐
2. Rute: `rgm` → `admin` → `hos` → `fm` setujui berurutan (tiap tombol SETUJUI muncul bagi yang berhak). ☐
3. `ft` → nomor transfer + foto bukti → CATAT TRANSFER DANA. ☐
4. `dewi` tambah nota (di luar rencana boleh) → KIRIM pertanggungjawaban. ☐
5. Ada sisa → `dewi` setor sisa + bukti → `ft` konfirmasi → Selesai. ☐
6. Nota melebihi dana → kelebihan otomatis jadi klaim reimburse. ☐
7. Lewat tenggat → klaim baru diblokir. ☐
8. PDF petty cash: bukti transfer dana dan bukti setoran ada di halaman akhir; log unduhan terpisah. ☐

## 7. Admin & monitoring
1. Hak klaim (A09): atur tarif uang makan **per orang**, kategori, budget. ☐ Jenis klaim (A10). ☐
2. Monitor budget (H05): HOS lihat semua; Audit/FM hanya badan usaha sendiri; Admin hanya region sendiri. ☐
3. Rekap/Export Excel dari daftar (semua kolom, termasuk jumlah revisi dan link PDF). ☐ Realisasi Finance (F05) read-only. ☐
4. Pengaturan "menunggu lama" (N hari) di Menu Aturan: isi angka → klaim yang lewat batas ditandai. ☐

## 8. Super Admin & lainnya
1. SA: Menu → "Masuk sebagai" user lain, lalu kembali. ☐
2. SA → Pengguna: buat pengguna baru, ubah nama/NIK/peran, nonaktifkan. ☐
3. **Kode cadangan**: Menu (Admin/FT/SA) → Terbitkan kode untuk pengganti → kode 6 karakter muncul (sebelumnya error SQL). Login pengganti lewat "Masuk kode cadangan" → dapat peran + scope orang yang digantikan, kode hanya sekali pakai. ☐
4. Notifikasi lonceng: muncul di tiap tahap, bisa ditandai dibaca. ☐
5. Reset data tes (SA). ☐

## 9. Tampilan HP (390px) dan desktop
1. Tidak ada scroll horizontal, tidak ada elemen tumpang tindih, teks tidak terpotong. ☐
2. Tombol FILTER oranye di daftar; bottom nav sesuai peran (Admin: Antrean, BA, Revisi, Diproses, Menu). ☐
3. Beranda: kartu KPI tampil, hitungan status ada. ☐

---
### Hal yang masih menunggu keputusan (bukan bug)
- Setelah Pemohon tanda tangan di BA temuan Admin, apakah Admin masih harus menekan Setujui lagi? (sekarang: ya, Admin tetap menyetujui)
- Angka konfigurasi: ambang BA, N hari, tarif, masa tenggang petty cash.
- Pemetaan peran saat integrasi ke DSM (`aryhidayattt/dsm`).

## BBM: kendaraan, jenis BBM, harga (BARU)
Jalankan ulang `install.php` (ada tabel baru `claim_fuel_price`; harga tes Pertalite Rp 10.000 / Pertamax Rp 20.000 sejak 2000-01-01 hanya data uji).
- [ ] Super Admin → Menu/Beranda → **Harga BBM**: tambah harga dengan tanggal berlaku; tanggal ganda ditolak; Pertamax < Pertalite ditolak; hanya SA yang bisa.
- [ ] Form BBM: wajib pilih **Mobil/Motor**; plat, KM, **foto odometer awal & akhir tetap wajib**.
- [ ] Per nota pilih **Pertalite/Pertamax**. Pertalite: diklaim = nominal nota.
- [ ] Pertamax nota 50.000 (Pertalite 10.000, Pertamax 20.000) → 2,5 L → diklaim **Rp 25.000**; selisih Rp 25.000 ditanggung pemohon, **tidak** memicu BA.
- [ ] Pembulatan Pertamax ke Rp 100 (nota 33.300 → 16.700).
- [ ] Satu klaim boleh campur Pertalite & Pertamax; total, budget, over-budget, transfer memakai nominal diklaim.
- [ ] Mobil: nominal NOTA tidak kelipatan aturan digit → pemicu DIGIT_BBM; Motor bebas; Pertamax dinilai dari nominal nota, bukan hasil hitung.
- [ ] Harga dipakai sesuai **tanggal nota** (ganti harga baru tidak mengubah nota lama).
- [ ] Tanggal nota tanpa harga berlaku → kirim diblokir ("harga BBM … belum diatur Super Admin").
- [ ] Detail & PDF menampilkan jenis BBM, liter, nominal nota dan nominal diklaim, plus kendaraan.


## 9. Jenis klaim baru
1. Ajukan → Transportasi: BBM, Tol, Parkir, **Ojol & taksi online**, **Tiket perjalanan**. Operasional: Kost, Penginapan / Hotel, ATK, Ongkir & kurir, **Servis kendaraan**, Operasional lainnya. Jenis lama (Listrik, Kebersihan, Perbaikan, Servis di Transportasi) tidak muncul, klaim lamanya tetap utuh. ☐
2. **Kue ulang tahun / Makan-makan**: daftar peserta (nama + jabatan) wajib minimal 2 orang berbeda; pemohon otomatis peserta pertama. Isi hanya diri sendiri → ditolak. Karangan bunga tetap tanpa peserta. ☐
3. **Tiket perjalanan**: pilih moda (Pesawat / Kereta / Bus), rute, tanggal, maskapai/operator, penumpang, tujuan. Pulang sebelum berangkat → ditolak. ☐
4. **Operasional lainnya**: judul, alasan, rincian (uraian, qty, harga) wajib; total rincian harus sama dengan total nota. ☐
5. Field wajib yang dulu lolos kosong (plat, keterangan, nama kost, dll.) sekarang benar-benar diblokir saat kirim. ☐

## 10. Aturan BA v2 (Pengaturan klaim)
1. Admin → Pengaturan klaim: **Aturan digit akhir BBM Mobil** (mis. 2000), **Hari nota normal** per kategori (chip Sen–Min; Perdin semua hari, lainnya Sen–Sab), **Jarak minimal uang makan Perdin** (80 km, 0 = tanpa batas). Simpan sebagai versi baru. ☐
2. BBM Mobil dengan digit akhir tidak sesuai (aturan 2000: 64.000 → BA; 62.000 → aman; aturan 5000: 100.000 → BA, 105.000 → aman). Nominal klaim tetap sesuai nota. Motor bebas. ☐
3. Nota bertanggal Minggu (Transportasi/Entertain/Operasional) tetap bisa diajukan, otomatis memicu BA. Perdin tidak. ☐
4. Entertain tanpa foto dokumentasi → BA (tidak diblokir). ☐
5. Perdin dengan jarak di bawah 80 km → Uang makan terkunci "Belum bisa" dengan pesan jarak. ☐
6. Pemicu lama (umur nota, biaya per km, per peserta, selisih KM BBM, batas nominal tanpa nota) sudah tidak ada. ☐

## 10b. BA: satu BA banyak penjelasan
1. Ringkasan sebelum kirim: tiap pemicu punya kartu sendiri (tabel Jumlah/Seharusnya/Klaim untuk digit, tabel hari untuk nota Minggu), kolom penjelasan berisi kalimat pembuka, dan kalimat saran yang bisa diketuk. Penjelasan hanya kalimat pembuka → ditolak. ☐
2. Tombol "+ Kehilangan nota" / "+ Temuan / kendala lain": pilih nota, bukti pengganti minimal 1 file (tanpa batas nominal), kategori temuan. Pemohon otomatis menandatangani, BA masuk ke Admin. ☐
3. Admin → Buat BA hanya punya **Kehilangan nota** dan **Temuan lain**; nominal salah → Minta revisi. Admin otomatis menyetujui, lalu Pemohon TTD → HOS → Audit → FM → FT. ☐
4. Minta revisi: ada kolom catatan per nota (mis. "seharusnya Rp 30.000"); Pemohon melihatnya di nota terkait. ☐
5. PDF BA bergaya surat: kalimat pembuka, bagian 3.1, 3.2 per pemicu, penutup, TTD. Klaim yang direvisi nominalnya menampilkan "Perubahan nominal antar versi". ☐

## 11. Satu klaim beberapa jenis
1. Ajukan → di kartu kategori ketuk **＋ Gabung beberapa jenis**, pilih BBM + Tol → LANJUT. Jenis kategori lain tidak bisa dipilih. Perdin tidak bisa digabung. ☐
2. Form berisi bagian per jenis (judul oren + subtotal), tiap bagian punya nota sendiri dan tombol Tambah nota. Total = jumlah semua bagian. ☐
3. Kirim → **satu nomor** klaim, jenis tampil "BBM + Tol" di daftar, rincian, PDF (ada baris "Bagian BBM / Bagian Tol"). ☐
4. Aturan per bagian: Tol tanpa gerbang, BBM tanpa plat, bagian tanpa nota → pesan menyebut nama bagian. ☐
5. Minta revisi → seluruh klaim kembali; edit, ubah nominal salah satu bagian, kirim ulang. ☐

## 12. Hubungi (follow-up)
1. `admin`/penyetuju isi **Menu → Profil saya → Nomor WhatsApp** (atau Super Admin di form pengguna). ☐
2. Klaim yang **lebih dari 1 hari** di tahap yang sama: pemohon melihat tombol hijau **HUBUNGI <TAHAP> UNTUK FOLLOW-UP**. Sebelum 1 hari tidak muncul; orang lain tidak melihatnya. ☐
3. Sheet berisi penyetuju tahap itu. Tombol **WhatsApp** membuka WA dengan pesan otomatis (nomor klaim, jenis, nominal, berapa hari). Yang belum punya nomor tampil abu-abu. ☐
4. **Kirim pengingat di aplikasi**: semua penyetuju tahap itu dapat notifikasi; maksimal sekali sehari. Semua follow-up tercatat di riwayat klaim. ☐

## 13. Tampilan
1. Navbar bawah: latar putih, ikon garis, item aktif kapsul oren muda + garis oren di atas. Cek di semua peran. ☐

## Kode cadangan (v2)
- Super Admin / pemegang peran: X02 → pilih peran, orang yang digantikan, **orang penerima**, durasi (2 jam – 3 hari) → kode tampil sekali.
- Penerima: menu Masuk kode cadangan → bar atas menampilkan hitungan mundur (kuning < 10 menit, merah < 5 menit), tombol Keluar. Masuk lagi dengan kode sama, maksimal 5 kali.
- Orang lain yang memakai kode → ditolak. Durasi habis / jatah 5 kali habis / dicabut → akses hilang.
- Daftar kode: status AKTIF / BELUM DIPAKAI / SELESAI / DICABUT; tombol Cabut hanya Super Admin dan pemberi kode.
- Wajib jalankan `php klaim/migrate.php` (kolom baru claim_backup).
