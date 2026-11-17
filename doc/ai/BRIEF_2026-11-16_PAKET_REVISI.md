# BRIEF PAKET REVISI — 2026-11-16

Brief ini merangkum keputusan Bro Ary dalam diskusi 16 November 2026 (mockup disetujui, **belum dikoding**).
Ditulis untuk sesi AI berikutnya (rencananya model Fable) supaya bisa langsung kerja tanpa menanyakan ulang.

Mockup acuan ada di `doc/mockup/2026-11-16/`. Mockup yang disetujui = kontrak (target kemiripan ~98%).

---

## STRUKTUR FINAL: 3 PAKET (keputusan user 2026-11-16)

User minta maksimal 3 paket. Bagian "PAKET 0–5" di bawah tetap jadi rincian teknis; pengelompokannya jadi:

| Paket | Isi (rincian di bagian lama) | Model | Effort |
|---|---|---|---|
| **1** | Paket 0 (Audit) + Paket 2 (Klaim Mode Demo + menu rapi) | Fable | Audit: max · Klaim: high |
| **2** | Paket 3 (Aturan Status Dealer + halaman Dealer + penanda opsi B) | Fable | high |
| **3** | Paket 1 (Admin 8 tab + Bento) + Paket 4 (Background) + Paket 5 (Tema gelap) | Opus | high |

Titik cek di dalam paket (wajib berhenti & lapor ke user):
- Paket 1: setelah `AUDIT_2026-11.md` selesai → user baca → baru koding Klaim. Temuan **kritis** (hak akses/keamanan) dilaporkan, perbaikannya menunggu persetujuan user.
- Paket 2: tampilkan "dampak sebelum simpan" dengan data asli + bukti angka KPI/achievement identik sebelum vs sesudah, sebelum dipasang.
- Paket 3: Admin + background dulu; tema gelap terakhir.

---

## ATURAN KERJA WAJIB UNTUK PAKET INI

1. **Kerjakan per paket, urut.** Satu paket selesai → kirim ke user → user tes → baru lanjut. JANGAN gabung semua paket jadi satu kiriman.
2. **Coding hanya setelah user bilang "gas"** untuk paket tersebut. "Kerjakan paket 0" = boleh mulai paket 0 saja.
3. Branch `dev`. Jangan sentuh `main` dan `amienramdhani/dsm_app`.
4. Minimal change. Isi tiap panel Admin (form/tabel di dalamnya) TIDAK diubah kecuali disebut.
5. Semua warna **solid**. User menilai gradient terlalu "anak muda" untuk aplikasi perusahaan. Mockup Bento dan beberapa mockup lain masih memakai ikon/header gradient → **saat koding ganti jadi solid**, bentuk & susunan tetap.
6. Teks UI di HP: label status maks ±12 huruf satu baris, terpotong "…" bukan turun baris. Keterangan panjang → ikon ⓘ. Cek di lebar 360px dan 390px.
7. Setelah tiap paket: update `HANDOFF.md` + `CHANGELOG_AI.md`. Jangan klaim "sudah dites" kalau belum dijalankan.
8. Kirim ke user hanya file yang berubah + 3–5 poin ringkas.

---

## PAKET 0 — AUDIT MENYELURUH (read-only, TANPA ubah kode)

Hasil: satu dokumen temuan `doc/ai/AUDIT_2026-11.md`, diurutkan dari paling berbahaya. Tiap temuan: file + baris, skenario gagal konkret, usulan perbaikan, tingkat risiko.

Fokus:
1. **Hak akses API**: setiap fungsi yang dipanggil lewat `api.php` → apakah `guard_`/cek role benar. Bisakah Admin biasa / Sales memanggil fungsi Super Admin lewat request langsung? Termasuk `klaim/api.php`.
2. **Perhitungan status dealer** dihitung di 3 tempat (plus status per brand di `lib/core.php` ~baris 405). Petakan juga **apakah KPI/achievement memakai status ini** atau aturannya sendiri (`MIN_QTY_DA`): `lib/core.php` (~baris 398), `lib/api_menu.php` (~baris 99), `lib/api_analisa.php` (~baris 130–136). Petakan perbedaannya. Ini masukan wajib untuk Paket 3.
3. **Data uji Klaim**: semua query laporan/budget/monitoring/realisasi/export di `klaim/` — mana yang sudah mengecualikan akun `UJI-%` dan klaim buatan mereka, mana yang belum. Masukan wajib untuk Paket 2.
4. Bug logika di KPI/achievement, DOS, transfer dealer, kredit tetap ke penjual.
5. CSS bertumpuk/duplikat di `index.php` (contoh `.topbar` di-override berkali-kali) — cukup daftar, jangan dibereskan.
6. Rahasia/kredensial yang ter-commit.

---

## PAKET 1 — ADMIN DSM: STRUKTUR BARU + BENTO

Mockup: `01-admin-struktur-menu.html` (mode SESUDAH) dan `02-admin-bento-b2.html` (variasi **B2 · PERSONAL**, versi solid).
File utama: `assets/admin_revision.js` (array `GROUPS` dan `enhanceAdminGroups`), `assets/admin_revision.css`, panel di `index.php` fungsi `admin()`.

### 1a. 8 tab dikelompokkan per OBJEK (bukan per cara kerja)
Urutan: Approval · Data Harian · Dealer · Brand · KPI & Target · Master Data · Pengguna & Akses · Sistem.

| Tab | Panel |
|---|---|
| Approval | Approval (badge "N baru") |
| Data Harian | SERING DIPAKAI: Upload regional ST/SO, Status Sync, Kelengkapan data per region, Peta kelengkapan · PENGECEKAN: Catatan kualitas data, Kunci periode · HATI-HATI: Pemulihan regional |
| Dealer | SERING DIPAKAI: Cari & edit dealer, Tambah dealer, **Upload Massal Dealer** · KEPEMILIKAN: Transfer dealer massal, Riwayat transfer, Riwayat pemilik, Big Region per dealer, Dealer tutup · HATI-HATI: Ganti kode induk & hapus dealer massal |
| Brand | **Pengaturan Brand** (gabungan) · TERKAIT: Region per brand & target Big Region |
| KPI & Target | TARGET: Edit target, Region per brand & target Big Region · KPI: Atur KPI, KPI Khusus, KPI Telemarketing |
| Master Data | SERING DIPAKAI: **Download & Upload Master**, Master Product, Cek Kesehatan Master, **Publikasi Master** (rename dari "Master & kebijakan") · WILAYAH & KALENDER: Region Map, Hari Libur · FORM ORDER & TELEMARKETING: Badan usaha form order, Penugasan telemarketing |
| Pengguna & Akses | Akun pengguna, Tambah user massal, Keamanan akun, Hak export, Kredit tetap ke penjual, Sambungan Telegram |
| Sistem | Autobackup, Riwayat aktivitas, Notifikasi khusus |

- Label "· Super Admin" di judul → badge kecil SUPER ADMIN. Admin biasa tetap tidak melihat panel Super Admin.
- Panel berbahaya: merah + "HATI-HATI".
- Pencarian membaca judul + **kata kunci tersembunyi** per panel (contoh ketik "brand" → semua panel terkait brand muncul; "kontak" → Upload Massal Dealer). Tab yang punya hasil diberi angka, lainnya meredup.

### 1b. Penggabungan panel (tampilan baru, proses backend TETAP yang lama)
- **Pengaturan Brand**: satu panel/sheet dengan sub-tab DAFTAR & KLASIFIKASI · AMBANG STATUS · WILAYAH & TARGET · PIC DEALER · ATURAN PRODUK (Product Rules). Menggantikan pencarian bolak-balik di 4 tab.
- **Upload Massal Dealer**: 1 pintu, pilih jenis: Tambah + Edit Dealer (proses "Upload dealer") · Edit Field Customer (proses "Bulk edit customer") · Kontak Saja (proses "Update kontak dealer massal", Super Admin).
- **Download & Upload Master**: gabungan "Bulk master & kebijakan" + "Download data master". Admin: download saja. Super Admin: download + upload. **Tambah jenis "Master Dealer"** di dropdown → diteruskan ke proses Upload Massal Dealer (tidak ada logika ganda).
- Kalau berisiko, boleh dipecah: 1a dulu (pindah tab + rename), 1b menyusul.

### 1c. Halaman depan Admin = Bento B2 Personal (solid)
- Sapaan + tanggal.
- **Tugas hari ini**: checklist otomatis dari data yang sudah ada (approval menunggu, region belum upload, backup).
- **Panel sematan**: user sematkan panel dengan ☆ di halaman grup; disimpan **per user di server** (ikut ke HP lain).
- **Terakhir dibuka**, ringkasan Data Harian (% kelengkapan + jam sync), grid semua menu.
- Halaman grup: header warna solid sesuai grup, daftar panel kartu putih, seksi Sering dipakai / Kepemilikan / Hati-hati.

Keputusan ini **menggantikan** aturan lama "Admin dibagi 5 tab" (lihat DECISIONS.md).

---

## PAKET 2 — DSM KLAIM: MODE DEMO + MENU RAPI

Mockup: `04-klaim-menu-mode-demo.html` (warna **Biru DSM**).
Kondisi kode sekarang: sudah ada `test_mode` (setting), akun uji `UJI-*` (`klaim/lib/seed.php`), "Ganti peran" (`switch_user`), `sa_testmode`, `sa_uji_off` (`klaim/api.php` ~baris 277–278). Kekurangannya: mematikan mode tes TIDAK otomatis menyembunyikan akun uji; data klaim akun uji kemungkinan masih ikut laporan/budget.

### 2a. Satu sakelar "Mode Demo" (Super Admin › Atur / Menu)
- **Nyala**: akun demo semua peran aktif (Pemohon, RGM, Admin, Audit, HOS, FM, FT); SA bisa "Masuk sebagai" peran mana pun; semua klaim/BA/petty cash yang dibuat ditandai DEMO; **pita oren "MODE DEMO AKTIF"** di semua layar.
- **Mati** (dengan konfirmasi berisi rincian): akun demo disembunyikan otomatis; semua data demo **hilang dari** daftar, budget, monitoring, realisasi, export; data TIDAK dihapus, muncul lagi saat demo dinyalakan.
- Tombol **"Hapus permanen data demo"** (konfirmasi merah). Data asli tidak tersentuh.
- **Paling penting**: data demo TIDAK BOLEH memotong budget asli atau masuk export/laporan asli. Uji ini secara eksplisit.

### 2b. Menu rapi
- Header warna solid **Biru DSM** (#0B5CAD), kartu "Masuk sebagai: <peran> · GANTI", status profil (WhatsApp belum diisi = kuning) langsung di header.
- Menu dikelompokkan: AKSES CEPAT (4 ikon) · PENGATURAN KLAIM · UJI & DEMO (SA) · AKUN SAYA (Profil, Tanda tangan, Kode cadangan) · tombol KEMBALI KE DSM.
- **Nav bawah maks 5 per peran.** Kalender pindah ke Menu. HOS & FM: Realisasi jadi tab di dalam Budget. Pemohon: **AJUKAN** jadi tombol oren besar di tengah.

| Peran | Nav sesudah |
|---|---|
| Super Admin | Beranda, Pengguna, Kode, Atur, Menu |
| Pemohon | Beranda, Klaim saya, (AJUKAN), Petty cash, Menu |
| RGM | Beranda, Antrean PC, Diproses, Kalender, Menu (tetap) |
| Admin | Antrean, BA, Revisi, Diproses, Menu |
| Audit | Antrean BA, Semua, Budget, Export, Menu |
| HOS | Antrean, BA, Semua, Budget, Menu |
| FM | Antrean, BA, Diproses, Budget, Menu |
| FT | Transfer, Akan datang, Riwayat, Realisasi, Menu |

---

## PAKET 3 — ATURAN STATUS DEALER

Mockup: `03-aturan-status-dealer.html`. Letak: tab Brand › Aturan Status Dealer (menggantikan/merangkum "Brand · Ambang Status Dealer").

### Keputusan
- Dasar hitung: **SELL THRU**.
- Dihitung **per brand DAN total semua brand** (dua-duanya). Di layar ada toggle PER BRAND / TOTAL.
- "Order" = minimal N unit (default 2, bisa diatur); "di bawah min" = 1 s.d. N−1 unit.
- **Pola order 6 bulan yang sudah selesai.** Status **dikunci tiap tanggal 1**. Contoh status November = pola Mei–Oktober.
- Aturan berupa baris pola (tiap bulan: ✓ order / ½ di bawah min / ✕ tidak order / • bebas) → status. Dicek dari atas ke bawah, aturan pertama yang cocok dipakai; tidak ada yang cocok → LAINNYA.
- Status baru bisa ditambah (nama maks ±12 huruf).
- Ada **"Coba pola"** (simulasi 1 dealer) dan **"Dampak sebelum simpan"** (jumlah dealer per status sekarang vs sesudah, dari data asli).
- Pengecualian per brand (contoh Infinix minimal 3 unit / aturan sendiri).
- **Penanda bulan berjalan** (tidak mengubah status) memakai **aturan yang sama persis dengan DA di KPI** (minimal unit per indikator/brand dari editor KPI). Jumlah "sudah order bulan ini" harus sama dengan angka DA di KPI.
- **Tampilan kartu dealer TETAP seperti sekarang** (nama, pill status, PIC, ST/SO/Omzet, kotak per brand, "Terakhir order", tombol Lihat detail). Penanda pakai **OPSI B** (mockup `03b-kartu-dealer-penanda-OPSI-B.html`): strip kecil di bawah status tiap kotak brand: hijau **"✓ NOV · 4u"** (memenuhi DA), kuning **"½ NOV · 1u"** (di bawah minimum DA), abu **"BELUM NOV"**. Rincian tanggal/unit di detail dealer.
- **Ringkasan atas halaman Dealer DISETUJUI** (mockup `03c-halaman-dealer-ringkasan-REFERENSI.html`, bagian atas saja): chip "🔒 Status dikunci 1 <bln>" + "MTD", kartu besar **SUDAH ORDER <BLN> = DA KPI**, 6 kartu status (Aktif, Di Bawah Min, Turun, Idle, Pasif, Nonaktif) dengan keterangan "✓ n sudah order", kotak **Bangkit bulan ini** (bisa diketuk = filter). Kartu **NOO per brand** dan **Sell Out** yang ada sekarang TETAP ditampilkan (di bawah kotak Bangkit). Bagian daftar dealer di mockup itu diabaikan; daftar memakai kartu sekarang + opsi B.
- **"Bangkit bulan ini"**: hitungan dealer yang status kuncinya Idle/Pasif/Turun/Nonaktif tapi sudah order bulan ini. FILTER Dealer dapat pilihan Sudah / Belum order bulan ini.
- Halaman Dealer menampilkan **AKTIF** (status terkunci) dan **ORDER BLN INI** (berjalan). Keterangan panjang → ⓘ.

### Nama status (disetujui)
AKTIF · **DI BAWAH MIN** (dulu BELUM MINIMUM DA) · **BANGKIT** (dulu usulan KEMBALI AKTIF) · TURUN · IDLE · PASIF · **NONAKTIF** (6 bulan tanpa order) · LAINNYA.
**DA BULAN LALU dihapus** sebagai status → diganti penanda ⏳.

### Teknis wajib
- Satukan 3 perhitungan status (lihat Paket 0 poin 2) jadi **satu fungsi sumber** untuk tampilan status: Dealer, Beranda, Analisa, export.
- **KPI, achievement, dan insentif sales TIDAK BOLEH berubah.** KPI punya aturannya sendiri (`MIN_QTY_DA` per indikator di `lib/api_kpi.php`, KPI Dealer Idle di editor KPI). Aturan Status Dealer yang baru TIDAK dipakai oleh perhitungan KPI kecuali user memutuskan lain secara eksplisit. Buktikan dengan membandingkan angka KPI/achievement sebelum vs sesudah (harus identik).
- Cek semua tempat yang memakai nama status lama (filter, KPI "Dealer Idle", Early Warning, export, agent API) dan sesuaikan.
- Tampilkan dampak ke user sebelum dipasang.

---

## PAKET 4 — BACKGROUND / TAMPILAN

Mockup: `05a-background-abu-polos.html` (opsi "Abu Polos"), `05b-background-biru-terang.html`.
- **Bawaan: Abu Polos `#F4F6F9`** (solid, tanpa gradient/blob) — menggantikan latar "blob sejuk" sekarang.
- Pilihan per user di **Lainnya › Tampilan**, disimpan per akun:
  - Terang: Abu Polos (bawaan), Biru Langit `#CFE3F7`, Sky `#BAD7FA`, Tosca Muda `#C7ECE6`.
  - Biru solid (judul/teks yang menempel di latar jadi putih): Biru DSM `#0B5CAD`, Petrol `#10394E`, Navy `#0F2B4F`, Biru Royal `#1D4ED8`.
- Kartu tetap putih. Tombol FILTER tetap oren. Warna indikator (ST/SO/Dealer Aktif/NOO/Omzet) dan warna status TIDAK ikut berubah.

## PAKET 5 — TEMA GELAP (paling akhir)

Mockup: `06-tema-gelap.html`. Disetujui: **Navy Malam** dan **Hitam Pekat**.
Kartu ikut gelap, warna indikator dibuat versi lebih terang (arti sama). Wajib audit semua halaman (tabel, chart, heatmap, popup, PDF form order, warna hardcode) supaya tidak ada teks gelap di atas latar gelap.

---

## PENDING (jangan dikerjakan)
- Tema warna (Oren / Corporate / Material You / Sport Racing) — mockup `99-tema-warna-PENDING.html`. Ditunda user.
- Penyeragaman tampilan isi ±45 panel Admin — tahap terpisah setelah Paket 1.
- DSM Staging terpisah — user memilih memakai Mode Demo DSM (sudah ada) + Mode Demo Klaim (Paket 2).
