# DSM Klaim (modul di dalam DSM)

Folder ini adalah modul Klaim yang berjalan di dalam repo DSM.
- **Login**: memakai token DSM (NIK+PIN, 2FA, sesi). Klaim tidak punya login sendiri.
- **Database**: database DSM yang sama, tabel berawalan `claim_`.
- **Pasang/upgrade**: `php klaim/migrate.php` (aman diulang, tidak menghapus data, tanpa pengguna uji). Setelah itu Super Admin DSM membuka menu **DSM Klaim**, mengisi Harga BBM, Badan usaha, dan memberi peran Klaim per NIK.
- **Mode mandiri untuk tes**: set env `CLAIM_STANDALONE=1` (dan `CLAIM_DRIVER=sqlite`) lalu `php klaim/tests/run.php`.
- **Sebelum live**: matikan Mode tes (Super Admin > Pengaturan).

---

(Catatan lama dari versi mandiri di bawah ini.)

# DSM Claim (Klaim) — versi lokal

## Install di Laragon
1. Salin folder `dsm-claim` ke `C:\laragon\www\dsm-claim`.
2. Start Laragon (Apache + MySQL). PHP minimal 8.1, ekstensi pdo_mysql, zip, fileinfo, gd (opsional, untuk kompres).
3. Buka `http://localhost/dsm-claim/install.php` → klik tombol buat database (db `dsm_claim`, user root tanpa password; ubah di `config.php` bila beda).
4. Buka `http://localhost/dsm-claim/` → login. Semua password `123456`.
Install ulang = reset semua data (berguna untuk mengulang tes).

## Akun uji
sa (Super Admin, mode tes bebas) · admin (Rina, Cirebon/RISM) · admin2 (Sinta, Jakarta/MSI) · audit · hos · fm · ft · ft2 · rgm (Budi) · asm · dewi (Dewi Lestari, Sales) · eko · rudi

## Catatan penting
- Ambang BA, hari "menunggu lama", tarif uang makan, dan budget di seed hanyalah DATA TES. Ambang BA sengaja kosong sampai Admin/SA mengisi.
- PDF = halaman cetak (tombol CETAK / SIMPAN PDF → Save as PDF di browser).
- Repo dsm_app belum disentuh.
