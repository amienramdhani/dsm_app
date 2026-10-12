# INSTRUKSI UNTUK GPT: Menu Panduan & Demo Presentasi DSM

Kamu melanjutkan pekerjaan di aplikasi **DSM (Distribution Sales Monitoring)** milik Bro Ary. Baca seluruh dokumen ini sebelum menyentuh kode.

## 0. Aturan main (WAJIB)
1. Bahasa komunikasi: Indonesia informal. Panggil user **"Bro"**, pakai "gue/lu".
2. **Tunjukkan mockup HTML dulu dan tunggu persetujuan** sebelum mengubah kode aplikasi. User bilang: "jangan ngoding dulu sebelum gue setuju".
3. **Isi chart "Proyeksi Sell Thru" tidak boleh disentuh sama sekali.** Container-nya boleh diubah, isinya tidak.
4. Semua judul dan toggle **dikapitalisasi** (contoh: "Per Type", "Per Sales", "Berdampingan").
5. Kirim ke user **hanya file yang berubah**, jangan kirim ulang file yang sama.
6. Tampilan terang, bersih, putih (bukan tema gelap) untuk mockup/dashboard. UI mobile modern: gradient halus, shadow lembut, warna cerah.
7. Dilarang ada elemen yang saling menimpa (overlap). User sangat sensitif soal ini. Cek di desktop DAN HP.
8. Beri rekomendasi praktis yang jelas ("paling worth it"), jangan hanya daftar opsi.
9. Jangan klaim "sudah dites" kalau belum benar-benar dijalankan dan dilihat hasilnya (screenshot).

## 1. Stack & struktur
PHP + MySQL (Laragon). Satu file besar `index.php` (HTML + CSS + JS inline) plus `lib/*.php`, `api.php`.
- Navigasi: objek `titles={...}` di index.php (sekitar baris 3408). Menu desktop dibangun di `start()` dari `Object.keys(titles)`; menu mobile "Lainnya" di `openMobileMore()` (ada `hints`).
- Render halaman: `async function render()` memakai rantai `if(PAGE==='beranda')...else if(PAGE==='informasi')...`. Pindah halaman: `navigateTo(page)`.
- Role (`BOOT.user.role`): `SUPER ADMIN`, `ADMIN`, `HEAD OF SALES`, `CHIEF OPERATING OFFICER`, `CHIEF COMMERCIAL OFFICER`, `BOARD OF DIRECTOR`, `RGM`, `ASM`, `SALES`, dst. Sales hanya melihat data sendiri; menu Admin hanya ADMIN/SUPER ADMIN.
- Ada overlay tampilan: `v7ux.css` dan `v7ux.js` yang disuntik ke index.php oleh `build.py`. Selector CSS di overlay memakai pola `html body :is(#app,.modal) :is(#main,#view,#mbody) ...` dengan `!important` supaya menang specificity. JS overlay jalan ulang lewat MutationObserver.
- Kalau kamu tidak punya `build.py`/`v7ux.*`, edit langsung index.php dan jangan hapus blok `/*V7UX-CSS-START*/...END*/` dan `<script id="v7ux">`.

## 2. Yang SUDAH selesai (batch 1)
File: `index.php` (sudah dimodifikasi), `panduan.js`, `panduan-content.js`.
- Halaman baru `panduan` terdaftar di `titles`, `render()`, `openMobileMore()` hints, dan script `panduan-content.js` + `panduan.js` dimuat di `<head>`.
- `panduan-content.js` berisi `window.DSM_GUIDE = {groups, glossary, topics}`. Tiap topik: `id, group, title, roles ('all' atau array role), intro, baca[[istilah,penjelasan]], warna(string), aksi[[Desktop/HP, penjelasan]], kasus(string), faq[[tanya,jawab]]`.
- `panduan.js` merender: desktop = daftar isi kiri + kolom cari + isi kanan; HP = daftar dulu, lalu detail ("← Daftar Panduan"). 5 tab: Cara Baca, Warna & Tanda, Interaksi, Contoh Pakai, FAQ. Tombol "Coba Sekarang" memanggil `navigateTo(menu)`.
- Isi baru ada untuk: Login & Navigasi, Kamus Istilah, Beranda (3 topik), Dealer (2), Brand (2), KPI (1), Admin (Target & Edit KPI).
- Mockup yang sudah DISETUJUI user ada di file `dsm-panduan.html` dan `dsm-presentasi.html` (lampiran). Ikuti tata letaknya.

## 3. Yang BELUM selesai (tugasmu), urut prioritas

### A. Lengkapi isi panduan (`panduan-content.js`), PRIORITAS TINGGI
User: "informasinya harus lengkap, menjelaskan fitur segala macam". Isi batch 1 masih kerangka.
- Tambah topik untuk SEMUA menu: Beranda, Dealer, Brand, **Tim, DOS, Analisa, KPI, Peringkat, Transaksi, Informasi, Perangkat Saya, AI Assistant (bila aktif), Admin** (Bulk master, Target Bulanan per Sales, Target Khusus KPI, Clone target/KPI ke region lain, Publish, manajemen akses/PIN/perangkat, Demo "Lihat sebagai").
- Per topik jelaskan: tiap panel/tabel/grafik di halaman itu, arti setiap kolom dan angka beserta rumusnya, arti warna, setiap tombol/filter/toggle, perbedaan desktop vs HP, contoh pemakaian nyata, dan FAQ.
- **Jangan mengarang.** Buka menu aslinya di aplikasi yang berjalan, baca kodenya, lalu tulis sesuai perilaku nyata. Tandai ke user bagian yang kamu tidak yakin.
- Fakta yang sudah terkonfirmasi: indikator ST biru `#1E5FB8`, SO oranye `#C25A12`, Dealer Aktif tosca `#0E7C6B`, NOO ungu `#6D3FD8`, Omzet cokelat `#9A6400`. Tabel "PER TYPE / PER SALES" punya toggle (default Per Type, tersimpan di localStorage `dsmTypeMode`). Growth bulan berjalan = MTD vs LMTD. Clone KPI/target bisa seluruh region atau satu kelompok, dengan opsi lewati/timpa. Upload target: Admin › Master › Bulk master › "Target Bulanan per Sales". Dealer induk punya beberapa kode outlet.
- Beda tampilan per role: kalau suatu menu tidak tersedia untuk role tertentu, set `roles` pada topik/grup. Contoh: Tim, Analisa, Peringkat tidak untuk SALES.

### B. Tampilan aplikasi + data contoh di dalam panduan, PRIORITAS TINGGI
User komplain: "ko ga ada tampilan aplikasi / contoh mockup datanya".
- Tiap topik harus punya **ilustrasi** tampilan aplikasi dengan **data contoh** (bukan kosong). Dua pendekatan, pilih yang paling ringan:
  1. Screenshot asli dari aplikasi (dengan data demo, nama sales/dealer disamarkan), disimpan di `assets/guide/*.webp`, ditampilkan di tiap topik. Buat versi desktop (1440 px) dan HP (390 px).
  2. Atau komponen mini HTML/SVG statis yang meniru panel aslinya dengan data contoh.
- Beri **anotasi** nomor/garis kuning pada bagian yang dijelaskan. Tombol "Coba Sekarang" sebaiknya menyorot panel terkait di menu asli (outline kuning sementara).
- Pasang tombol kecil **"?"** di judul panel penting yang membuka topik panduan terkait (`PAGE='panduan'` + pilih topik).
- Jangan embed aplikasi live (iframe) di dalam menu Panduan: berat dan bentrok sesi login.

### C. Rework `demo-presentasi.html` + `presentation-guide.js`, PRIORITAS TINGGI
Masalah: desktop dirender hanya 1100 px lalu di-scale, sehingga layout sempit, sidebar terpotong, tampilan "tidak sesuai desktop". User: "tampilan desktop dan mobile harus real".
- Iframe desktop harus dirender **1440 × 900** (atau 1366 × 768) lalu di-scale dengan `transform: scale()` supaya muat. Mobile **390 × 844** dalam bingkai HP.
- Tambah toggle: **Berdampingan / Desktop Saja / Mobile Saja** (kapital).
- Panduan di panel kanan memakai **data yang sama** dengan `panduan-content.js` (satu sumber isi), lengkap dengan 5 tab dan indikator "FITUR n / total".
- Panduan ikut halaman yang sedang dibuka di iframe (sudah ada mekanisme baca menu aktif di `presentation-guide.js`; pertahankan).
- Perilaku yang diminta user: **di HP** halaman ini hanya menampilkan bingkai HP (satu layar) dengan panduan sebagai lembar geser; **di desktop** ada pilihan tampilan.
- Hanya untuk ADMIN/SUPER ADMIN (tombol "Presentasi" di index.php baris ~3478 sudah membatasi lewat `demo.canStart`; jangan longgarkan).
- Iframe harus punya `data-dsm-presentation="1"` (dipakai `resumeStoredSession()`).

### D. Opsional / tertunda
- Fix `lib/api_menu.php`: `apiTransactions` filter customer harus juga cocok ke kode outlet milik induk, pakai `custInduk_($db['meta'], $t['customer'] ?? '') === $customer`. Efeknya Riwayat Transaksi dealer induk (mis. RISM4) tidak kosong lagi. Belum dikirim ke user; tanya dulu.
- Pertanyaan terbuka: apakah chart tren di menu Brand ikut gaya baru (target / % capai / estimasi)? Default sekarang MINGGU 5 tampil "-".

## 4. Cara mengetes (wajib sebelum klaim selesai)
- Jalankan aplikasi lokal, login dengan akun beberapa role (minimal SALES, ASM/RGM, ADMIN) dan cek menu Panduan berbeda sesuai role.
- Screenshot desktop **1440×900** dan HP **390×844** untuk: daftar Panduan, satu topik tiap menu, mode pencarian, halaman Demo Presentasi (3 mode toggle).
- Pastikan: tidak ada scroll horizontal pada body, tidak ada teks terpotong, tidak ada overlap, tidak ada error di console, rotasi HP landscape tidak menutup layar.
- Pastikan akun tidak bocor antar user: setelah logout, panduan/cache tidak membawa data akun sebelumnya.

## 5. Format hasil kerjamu
1. Kirim mockup (HTML) untuk bagian B dan C dulu. Tunggu "gas" dari Bro.
2. Baru ubah kode. Kirim bertahap per batch (menu inti dulu, lalu sisanya) supaya Bro bisa cek arah penulisan.
3. Tiap kirim: sebut file yang berubah dan 3–5 poin ringkas apa yang berubah/belum.
