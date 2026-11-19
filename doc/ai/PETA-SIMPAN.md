# Peta tombol Simpan / Upload DSM vs publikasi ulang (2026-11-18)

Tujuan: paket "simpan cepat". Tahap 1+2 DIKERJAKAN 18 Nov (lihat HANDOFF). Tahap 3 DIKERJAKAN 18 Nov (antrean + cron, lihat HANDOFF).

## Kenapa lambat (hasil baca kode `dev`)
- Hampir semua simpan memanggil `publish_()` (lib/publish.php:140) di dalam request yang sama: baca ulang SEMUA baris ST+SO semua bulan, petakan region, `PortalCore::build` + `StockCore::build`, tulis snapshot baru. Batas 240 dtk.
- Sebelum itu `checkpoint_()` menyalin semua sheet master ke JSON (cadangan).
- Semua berjalan di dalam `with_lock_()` → selama satu publikasi jalan, simpan lain ikut antre (efek berantai timeout).
- Setelah itu `DATA_REV` berganti → cache semua halaman (`lib/rescache.php`) tidak terpakai, buka halaman pertama kali ikut lambat.
- Bagian master yang ikut membentuk angka transaksi: `customers`, `people`, `products`, `nikMerge`, Region Map (routing), Keep Seller / Koreksi Transaksi (effectiveRows_). Bagian lain (KPI, target, kebijakan) dibaca saat halaman dibuka, tidak perlu bangun ulang transaksi.

## Rencana
1. RINGAN = ganti bagian master di snapshot tanpa baca ulang ST/SO (detik).
2. BERAT = publikasi penuh dijalankan di belakang layar (job), layar langsung kembali "Data sedang diperbarui…", data lama tetap aktif sampai selesai.

## Peta
| Menu / tombol | Fungsi | Kelas usulan | Alasan |
|---|---|---|---|
| Atur KPI, bobot, target, KPI Khusus, KPI Telemarketing | `kpiCommit_` (api_kpi.php) | RINGAN | hanya kebijakan/target |
| Publikasi konfigurasi | `apiPublishConfig` | RINGAN* | *cek isi; kalau master transaksi ikut berubah → BERAT |
| Bulk master: TARGET / KPI | `apiCommitMasterBulk` | RINGAN | per jenis master |
| Bulk master: DEALER / PRODUCT / SALES / REGION | `apiCommitMasterBulk`, `attrCommit_` | BERAT (belakang) | ubah atribusi |
| Edit 1 dealer | `apiDealerSave` | RINGAN bila cuma kontak/nama/alamat; BERAT bila sales/region/induk berubah | |
| Hapus dealer, Ganti kode induk massal | `apiDealerDelete`, `apiIndukBulkCommit` | BERAT | |
| Tambah dealer (form & massal) | `appendDealers_`, `apiCommitCustomerBulk` | BERAT | dealer baru bisa menarik transaksi lama |
| Big region per dealer | `apiDealerRegionSave` | BERAT | |
| Transfer dealer, batal transfer, bulk transfer | `apiCommitDealerTransfer`, `apiTransferUndo`, `apiPortfolioTransferCommit` | BERAT | |
| Kredit tetap ke penjual | `apiKeepSellerSave/Revoke` | BERAT | |
| Koreksi Transaksi (satu & massal, cabut) | `apiTxCorrSave/Bulk/Revoke` | BERAT | |
| Region Map | `apiRegionMapSave` | BERAT | routing |
| Brand & Klasifikasi | `apiBrandClassificationSave` | BERAT | brand/MASUK_QTY produk |
| Master Product | `apiProductMasterSave` | BERAT | |
| Simpan user, user massal, vacant otomatis | `apiSaveUser`, `apiCommitUsers`, `apiPortfolioEnsureVacant` | RINGAN bila PIN/akses/nama; BERAT bila role/region/atasan berubah | people ikut build |
| Gabung / hapus akun | `apiAccountMergeCommit`, `apiAccountDelete` | BERAT | nikMerge |
| Upload ST / SO | `publishRegionImport_` | BERAT (belakang) | memang bangun ulang |
| Sync master | `syncMaster_` | BERAT (belakang) | |
| Restore checkpoint / restore data | `apiCheckpointRestore`, `apiCommitRestore` | BERAT (belakang) | |
| Simpan lain tanpa publikasi (kontak, aturan status dealer, dll) | `putTable_` saja | sudah ringan | hanya cache dibuang |

## Catatan teknis untuk coding
- Job belakang: satu antrean (tabel/prop `PUBLISH_QUEUE`), satu pekerja (dipicu setelah respons dikirim: `fastcgi_finish_request()` di PHP-FPM; Apache mod_php: `ignore_user_abort` + `Connection: close`, atau cron 1 menit). Perlu cek ke IT/Amin mana yang tersedia di server.
- Beberapa simpan berat beruntun digabung jadi satu publikasi.
- Banner status "Data sedang diperbarui (mulai jam …)" untuk Admin; gagal → data lama tetap aktif + pesan di panel Admin.
- Setelah job selesai: panaskan cache halaman utama.
