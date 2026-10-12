# Validasi pembaruan DSM

## Pemeriksaan yang berhasil dijalankan

- `node --check` pada `tm.js`, `tm_update.js`, dan `pdf.js` tanpa kesalahan sintaks.
- Uji logika order dummy: dua barang subtotal Rp4.500.000, Credit Note Rp500.000, total Rp4.000.000; teks menyebut `DEALER NOO` dan TOP 14 hari.
- Generator PDF menghasilkan satu halaman; PDF dirender sebagai gambar dan diperiksa visual. Pratinjau dan download memakai hasil `DSMTMPdf.layout(...)` yang sama.
- Kode orientasi fullscreen heatmap tidak lagi memanggil orientation lock. CSS heatmap mempertahankan scroll pada layar kecil.

## Perlu dijalankan saat tersedia PHP/browser

Lingkungan pengerjaan ini tidak memiliki PHP maupun binary Chromium. Jalankan di salinan aplikasi dengan PHP, PDO SQLite untuk fixture dan MySQL/MariaDB untuk smoke test hosting:

```sh
php -d short_open_tag=0 tests/telemarketing_backend.php
```

Fixture memakai SQLite in-memory, tidak membuka database produksi. Pemeriksaan browser yang disarankan: akun Tele lintas RGM, grup region/Sales/brand, dealer pilihan, rekan satu atasan dan beda atasan, editor KPI HP horizontal, DA biasa/DAQ/IoT/KEY, audit aktual manual, order dari setiap role, PDF/teks, Credit Note, serta fullscreen heatmap HP portrait. Pastikan aplikasi memiliki izin membuat tabel `_tm_kpi_actual_log` dan tabel Telemarketing lainnya. Validasi perhitungan dan hak akses pada database nyata tetap penting sebelum dipakai tim.
