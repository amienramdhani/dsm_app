# REVISI BUG DSM

**Repository:** `aryhidayattt/dsm`  
**Branch acuan:** `dev`  
**Baseline:** `76cb12b6b5e334ccf0e6ace68fb29df4a4538bb2`  
**Tujuan:** daftar BUG existing yang harus diperbaiki tanpa mencampurnya dengan request perubahan/fitur.

## Aturan

- **Bug** = perilaku existing yang salah / rusak, padahal fungsinya seharusnya sudah berjalan.
- Jangan memasukkan fitur baru, pemindahan menu, atau perubahan behavior baru ke file ini.
- Setiap bug harus bisa direproduksi.
- Jika tidak ada error Console, tulis `Tidak ada / tidak ditemukan`.
- Kelima bug di bawah adalah bug pada baseline tanggal 17.
- Regression dari percobaan tanggal 18 **bukan** bug baseline dan tidak dimasukkan sebagai pekerjaan dari file ini.

---

## Bug #1

- **Halaman/menu:**  
  Sidebar utama DSM / navigasi desktop — `TRANSFER DEALER`.

- **Role yang kena (Admin/ASM/RGM/Sales/dll):**  
  ASM dan RGM.

- **Langkah ngulang:**  
  1. Login sebagai ASM atau RGM.  
  2. Tunggu aplikasi dan sidebar selesai dimuat.  
  3. Lihat area paling atas sidebar sebelum `BERANDA`.

- **Yang terjadi sekarang:**  
  Menu `TRANSFER DEALER` muncul sendiri / melayang di atas `BERANDA`, tidak menyatu dengan struktur sidebar native DSM. Baseline menggunakan mekanisme inject/clone menu sehingga item dapat berada di luar grouping navigasi normal.

- **Yang seharusnya:**  
  Tidak boleh ada item menu yang melayang di luar struktur sidebar. Menu harus mengikuti lifecycle, styling, spacing, active-state, dan grouping navigasi DSM secara normal. Lokasi final menu mengikuti file `request-revisi.md`, bukan ditentukan oleh bug ini.

- **Pesan error di Console (F12) kalau ada:**  
  Tidak ada / tidak ditemukan. Masalah terlihat sebagai bug DOM/navigation.

- **Prioritas:**  
  ganggu

---

## Bug #2

- **Halaman/menu:**  
  `ADMIN → PENGGUNA & AKSES → Transfer Sales / Alihkan Portofolio`.

- **Role yang kena (Admin/ASM/RGM/Sales/dll):**  
  Admin dan Super Admin.

- **Langkah ngulang:**  
  1. Login sebagai Admin / Super Admin.  
  2. Masuk ke halaman `ADMIN`.  
  3. Buka kategori `PENGGUNA & AKSES`.  
  4. Buka `Transfer Sales / Alihkan Portofolio`.  
  5. Scroll dan gunakan form sampai bagian bawah.

- **Yang terjadi sekarang:**  
  Isi panel/form dapat terpotong. Bagian bawah atau child content tertentu bisa sulit dijangkau karena tinggi/overflow parent container.

- **Yang seharusnya:**  
  Seluruh isi panel harus tampil utuh dan bisa discroll normal. Parent container tidak boleh memotong child content. Perbaikan harus targeted dan tidak mengubah layout Admin global.

- **Pesan error di Console (F12) kalau ada:**  
  Tidak ada / tidak ditemukan. Bug layout/CSS overflow.

- **Prioritas:**  
  ganggu

---

## Bug #3

- **Halaman/menu:**  
  `ADMIN → PENGGUNA & AKSES → Transfer Sales / Alihkan Portofolio`.

- **Role yang kena (Admin/ASM/RGM/Sales/dll):**  
  Admin dan Super Admin.

- **Langkah ngulang:**  
  1. Login sebagai Admin / Super Admin.  
  2. Buka `ADMIN → PENGGUNA & AKSES`.  
  3. Buka `Transfer Sales / Alihkan Portofolio`.  
  4. Klik input pemilihan Sales sumber / penerima.  
  5. Ketik nama, NIK, role, atau region sampai daftar hasil pencarian muncul.

- **Yang terjadi sekarang:**  
  Dropdown hasil pencarian Sales dapat terpotong oleh batas panel, tertutup container lain, tidak keluar penuh, atau sulit dipilih karena overflow/stacking context.

- **Yang seharusnya:**  
  Dropdown tampil penuh di atas content lain, dapat keluar dari batas card/panel bila perlu, dapat discroll, dan semua opsi dapat diklik. Jangan memperbaiki dengan CSS global yang merusak dropdown lain.

- **Pesan error di Console (F12) kalau ada:**  
  Tidak ada / tidak ditemukan. Bug overflow / stacking context / z-index.

- **Prioritas:**  
  ganggu

---

## Bug #4

- **Halaman/menu:**  
  `ADMIN` — Focus Mode / pembukaan panel kategori.

- **Role yang kena (Admin/ASM/RGM/Sales/dll):**  
  Admin dan Super Admin.

- **Langkah ngulang:**  
  1. Login sebagai Admin / Super Admin.  
  2. Masuk ke `ADMIN`.  
  3. Pilih salah satu kategori.  
  4. Buka salah satu panel/fitur di kategori tersebut.  
  5. Perhatikan posisi content aktif terhadap daftar panel/kategori.

- **Yang terjadi sekarang:**  
  Content aktif dapat muncul di posisi yang tidak natural sehingga terasa seperti lompat ke tengah layout dan flow `kategori → panel → content` tidak konsisten.

- **Yang seharusnya:**  
  Struktur kategori Admin existing tetap dipertahankan. Content aktif tampil pada posisi yang konsisten dan mudah ditemukan tanpa merombak seluruh halaman Admin.

- **Pesan error di Console (F12) kalau ada:**  
  Tidak ada / tidak ditemukan. Bug UX/layout.

- **Prioritas:**  
  ganggu

---

## Bug #5

- **Halaman/menu:**  
  `ADMIN` versi mobile — pemilih/navigasi kategori.

- **Role yang kena (Admin/ASM/RGM/Sales/dll):**  
  Admin dan Super Admin.

- **Langkah ngulang:**  
  1. Login Admin / Super Admin dari HP atau viewport mobile.  
  2. Masuk ke `ADMIN`.  
  3. Buka/lihat pemilih kategori.  
  4. Coba membaca dan memilih seluruh kategori.

- **Yang terjadi sekarang:**  
  Nama kategori dapat terpotong kiri/kanan, tidak terlihat penuh, terlalu padat, atau sulit dipilih.

- **Yang seharusnya:**  
  Semua kategori dapat dibaca dan dipilih dengan jelas di mobile. Tampilan tetap compact dan mengikuti style Admin existing, tanpa mengecilkan font secara berlebihan dan tanpa merusak desktop.

- **Pesan error di Console (F12) kalau ada:**  
  Tidak ada / tidak ditemukan. Bug responsive layout.

- **Prioritas:**  
  ganggu

---

## Bukan bug baseline tanggal 17

Item berikut pernah muncul akibat eksperimen tanggal 18 dan **jangan dianggap kondisi current `dev`**:

- duplicate `PENGECEKAN TRANSAKSI` + `KOREKSI TRANSAKSI`;
- `KOREKSI TRANSAKSI` tampil pucat / struktur sidebar salah;
- label `KOREKSI TRANSA...` terpotong;
- `TRANSFER DEALER` sempat hilang pada Admin;
- loader revision sempat tidak termuat;
- panel `NOTIFIKASI KHUSUS` sempat tidak terhapus.

Branch backup tanggal 18 boleh dipakai untuk audit/referensi, tetapi jangan di-merge langsung ke baseline.
