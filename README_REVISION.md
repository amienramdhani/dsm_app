# DSM Approved Revision Pack

Basis: `aryhidayattt/dsm` branch `dev`, head `f999b49293e72686a47e396748fe353ecdde4bdc`.

Scope implementasi:
- Admin 9 grup + Upload Harian + Focus Mode berbasis warna grup.
- Edit User dipoles; Region portofolio kosong/nonaktif saat rangkap Sales off.
- Alihkan Portofolio 4 tahap: penerima, dealer, target/actual, preview.
- Multi penerima + bulk assign dealer.
- Dealer-brand yang sudah dimiliki Sales lain tidak ikut ditimpa.
- Target `Target_Input`, `Target_Periode`, dan target SO KPI Khusus dipindah/merge.
- Actual ST current period mengikuti dealer/brand lewat effective-dated ownership tanpa menulis ulang raw transaksi.
- Histori target lama tidak lagi menghalangi berhenti rangkap Sales.
- KPI Khusus compact accordion + focus editing + sticky action.
- DOS: kondisi `Ada SO tapi stok 0` memiliki poin tersendiri (default 0).

Safety:
- Tidak menyentuh `main` atau repo Amin.
- Apply script membuat `.dsmrev.bak` untuk file existing yang dipatch.
- Transfer portfolio menyimpan backup tabel terkait dan rollback sebelum publish bila proses gagal.
- `dbaSet_` tetap melarang assignment masa depan untuk flow lama; hanya orchestrator portfolio yang boleh mengaktifkannya.

Validasi pack:
- `php -l lib/portfolio_core.php` PASS
- `php -l lib/api_portfolio.php` PASS
- `node --check assets/portfolio_transfer.js` PASS
- `node --check assets/admin_revision.js` PASS
- `python -m py_compile apply_approved_revision.py` PASS

Belum dilakukan:
- Belum ditulis/push ke GitHub `dev`.
- Belum diuji dengan database/Laragon user karena runtime lokal tidak tersedia di sesi ini.
