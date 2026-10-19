# REQUEST REVISI DSM — V2 RAPI

**Repository:** `aryhidayattt/dsm`  
**Branch acuan:** `dev`  
**Baseline:** `76cb12b6b5e334ccf0e6ace68fb29df4a4538bb2`  
**Dokumen pasangan:** `revisi-bug.md`  
**Tujuan:** memetakan request perubahan/fitur tanpa mencampurnya dengan bug existing dan tanpa request yang tumpang tindih.

## Aturan Utama

- **Request** = perubahan, penambahan, pemindahan, atau penyempurnaan behavior/fitur.
- **Bug existing tidak masuk file ini.** Bug ada di `revisi-bug.md`.
- Existing DSM pada baseline tanggal 17 adalah **source of truth visual dan struktur aplikasi**.
- Jangan redesign sidebar, Admin, mobile navigation, spacing, font, atau pola UI global kecuali memang disebut eksplisit di request.
- Implementasi harus **targeted dan native** terhadap arsitektur existing; hindari clone DOM, floating fallback, dan tumpukan hotfix.
- Role/authorization harus divalidasi **frontend + backend**.
- Jika mockup/keputusan lama bertentangan dengan keputusan terbaru di file ini, **file ini yang menang**.
- Status mengacu pada baseline `76cb12b6b5e334ccf0e6ace68fb29df4a4538bb2`:
  - `belum dikerjakan` = requirement final belum ada pada baseline.
  - `setengah jadi` = sebagian fondasi/flow sudah ada tetapi belum memenuhi requirement final.
  - `sudah benar` = requirement sudah terpenuhi pada baseline.
- **Request yang berhubungan boleh saling mereferensikan, tetapi tanggung jawabnya tidak boleh diduplikasi.**
  - Contoh: Request Transfer Dealer mengatur business flow transfer.
  - Request Approval Center mengatur **tempat tunggal** Admin memproses seluruh jenis approval.

---

# A. TRANSFER DEALER

## Request #1
- **Judul:** Jadikan `TRANSFER DEALER` menu native di kelompok `LAINNYA`
- **Kenapa dibutuhkan (tujuan bisnisnya):** Agar fungsi transfer dealer mudah ditemukan oleh role yang berwenang, tidak tercecer di Admin/Pantau, dan mengikuti struktur navigasi DSM.
- **Halaman/menu:** Sidebar utama → `LAINNYA → TRANSFER DEALER`
- **Role yang pakai:** ASM, RGM, Admin, Super Admin. Sales tidak boleh mengakses.
- **Perilaku yang diinginkan (langkah per langkah):**
  1. User login.
  2. Role ASM/RGM/Admin/Super Admin melihat satu menu `TRANSFER DEALER` di kelompok `LAINNYA`.
  3. Klik menu membuka halaman Transfer Dealer native DSM.
  4. Tidak ada entry Transfer Dealer di tempat lain.
- **Aturan bisnis:**
  - Sales tidak melihat menu dan backend harus menolak akses Sales.
  - Jangan menggunakan clone DOM/floating fallback.
  - Menu harus mengikuti styling, icon, spacing, hover, active state, desktop/mobile navigation existing.
- **Cara ngetes (hasil yang diharapkan):**
  1. ASM/RGM/Admin/Super Admin → tepat satu menu `TRANSFER DEALER` di `LAINNYA`.
  2. Sales → menu tidak tampil dan direct API access ditolak.
  3. Desktop/mobile → tidak ada duplicate atau item melayang.
- **Prioritas:** tinggi
- **Status:** belum dikerjakan

---

## Request #2
- **Judul:** Finalisasi flow Transfer Dealer, role scope, dan pemilihan Sales
- **Kenapa dibutuhkan (tujuan bisnisnya):** ASM/RGM perlu mengajukan transfer hanya dalam wilayah kewenangannya, sedangkan Admin/Super Admin dapat menjalankan transfer secara langsung.
- **Halaman/menu:** `LAINNYA → TRANSFER DEALER`
- **Role yang pakai:** ASM, RGM, Admin, Super Admin
- **Perilaku yang diinginkan (langkah per langkah):**
  1. Pilih Sales sumber melalui searchable selector.
  2. Pilih Sales tujuan melalui searchable selector.
  3. Selector dapat dicari dengan nama, NIK, role, atau region.
  4. Sales tujuan dipilih **sekali di awal** untuk ordinary Transfer Dealer.
  5. Lanjut ke pemilihan dealer-brand.
  6. Tentukan tanggal efektif dan alasan.
  7. Preview.
  8. ASM/RGM submit request; Admin/Super Admin dapat menjalankan langsung.
- **Aturan bisnis:**
  - ASM hanya dapat memilih Sales dalam scope ASM.
  - RGM hanya dapat memilih Sales dalam scope RGM.
  - Admin/Super Admin mengikuti kewenangan existing.
  - Source dan destination tidak boleh sama.
  - Scope harus divalidasi ulang di backend; manipulasi payload tidak boleh menembus kewenangan.
- **Cara ngetes (hasil yang diharapkan):**
  1. Cari nama/NIK/region → kandidat yang sah muncul.
  2. ASM/RGM tidak dapat melihat/memilih Sales di luar scope.
  3. Source = destination → ditolak.
  4. Payload manual di luar scope → server menolak.
- **Prioritas:** tinggi
- **Status:** setengah jadi

---

## Request #3
- **Judul:** Pemilihan dealer × brand yang presisi dengan UI desktop/mobile yang usable
- **Kenapa dibutuhkan (tujuan bisnisnya):** Ownership dealer dapat berbeda per brand, sehingga transfer harus tepat pada pasangan dealer-brand dan tetap nyaman digunakan pada desktop maupun HP.
- **Halaman/menu:** `TRANSFER DEALER → Pilih Dealer`
- **Role yang pakai:** ASM, RGM, Admin, Super Admin
- **Perilaku yang diinginkan (langkah per langkah):**
  1. Setelah source dipilih, tampilkan dealer yang benar-benar dimiliki source.
  2. Sediakan search dealer, filter area, filter/multi-select brand, page size, select all/clear.
  3. Satu dealer tetap satu row/card.
  4. Tampilkan brand milik source sebagai chip/checklist.
  5. User memilih dealer dan exact brand yang dipindahkan.
  6. Desktop memakai tabel compact; mobile memakai card/list native mobile.
  7. Selection valid tetap bertahan saat filter/pagination berubah.
- **Aturan bisnis:**
  - Hanya brand yang dimiliki source pada tanggal efektif yang boleh dipindah.
  - Jangan implicit `ALL` bila user hanya memilih sebagian brand.
  - Partial brand match tetap membuat dealer relevan muncul.
  - Ordinary Transfer Dealer tidak memakai destination per-row.
  - Desktop dapat memakai pagination 25/50/100.
  - Sticky action mobile tidak boleh menutupi bottom nav.
- **Cara ngetes (hasil yang diharapkan):**
  1. Dealer punya Brand A+B, pilih A → hanya A yang masuk selection.
  2. Filter brand A tetap menampilkan dealer yang punya A+B.
  3. Ganti page/filter → selection yang masih valid tidak hilang.
  4. HP → seluruh filter, card, dropdown, dan tombol bisa dipakai tanpa layout pecah.
- **Prioritas:** tinggi
- **Status:** setengah jadi

---

## Request #4
- **Judul:** Preview Transfer Dealer + sinkronisasi Primary PIC yang benar
- **Kenapa dibutuhkan (tujuan bisnisnya):** User harus melihat dampak persis sebelum commit dan master PIC harus tetap konsisten setelah ownership dealer-brand berubah.
- **Halaman/menu:** `TRANSFER DEALER → Preview` dan backend ownership
- **Role yang pakai:** ASM, RGM, Admin, Super Admin; sinkronisasi PIC dijalankan sistem
- **Perilaku yang diinginkan (langkah per langkah):**
  1. Preview menampilkan source, destination, tanggal efektif, alasan, jumlah dealer, jumlah assignment brand.
  2. Tampilkan setiap dealer dan exact brand yang akan berubah.
  3. Tampilkan actual MTD yang relevan.
  4. Setelah transfer berhasil, sistem mengevaluasi ownership seluruh active brand dealer.
  5. Jika semua active brand terkonsolidasi ke satu Sales tujuan, Primary PIC disinkronkan.
  6. Jika ownership masih split, assignment per-brand tetap dipertahankan.
- **Aturan bisnis:**
  - Preview harus identik dengan payload yang akan dieksekusi.
  - Full consolidation A→B dapat menjadikan B sebagai Primary PIC.
  - Split ownership tidak boleh di-flatten secara agresif.
  - Future/unassigned brand memakai fallback PIC yang konsisten dengan model existing.
- **Cara ngetes (hasil yang diharapkan):**
  1. Preview tidak memuat dealer/brand yang tidak dipilih.
  2. Semua brand A→B → Primary PIC menjadi B.
  3. Hanya Brand A pindah ke B, Brand B tetap A → split tetap valid.
- **Prioritas:** tinggi
- **Status:** setengah jadi

---

## Request #5
- **Judul:** Approval, atomicity, history, dan target rule untuk Transfer Dealer
- **Kenapa dibutuhkan (tujuan bisnisnya):** Transfer harus aman, terkontrol, tidak bisa double-process, tidak merusak histori, dan tidak menggeser target ordinary transfer.
- **Halaman/menu:** Backend Transfer Dealer + request approval
- **Role yang pakai:** ASM/RGM sebagai requester; Admin/Super Admin sebagai direct executor/reviewer
- **Perilaku yang diinginkan (langkah per langkah):**
  1. ASM/RGM submit → request `PENDING`, ownership belum berubah.
  2. Admin membuka request dari Approval Center (lihat Request #11).
  3. Saat approve, server revalidasi actor scope, source, destination, ownership dealer-brand, effective date, dan staleness.
  4. Request valid masuk proses dan commit secara atomic.
  5. Request invalid/stale menjadi gagal tanpa partial mutation.
  6. Reject menyimpan note dan tidak mengubah ownership.
- **Aturan bisnis:**
  - State minimal: `PENDING → PROCESSING → APPROVED` atau `FAILED`; reject menjadi `REJECTED`.
  - Request yang sudah diproses tidak dapat diproses kedua kali.
  - Raw transaksi tidak diubah.
  - Periode sebelum effective date tetap memakai owner historis.
  - **Ordinary Transfer Dealer tidak memindahkan target Sales.**
  - Seluruh perubahan ownership/PIC/publish/audit harus all-or-nothing.
- **Cara ngetes (hasil yang diharapkan):**
  1. ASM submit → pending; ownership tetap.
  2. Admin approve valid → ownership berubah sekali.
  3. Approve lagi → ditolak.
  4. Reject → ownership tetap.
  5. Ownership berubah setelah request dibuat → approval gagal aman.
  6. Paksa failure di tengah commit → seluruh perubahan rollback.
  7. Target source/destination sebelum vs sesudah ordinary transfer tetap sama.
  8. Periode lama tetap menunjuk owner lama.
- **Prioritas:** tinggi
- **Status:** setengah jadi

---

# B. ALIHKAN PORTOFOLIO / TRANSFER SALES

## Request #6
- **Judul:** Alihkan Portofolio sebagai flow terpisah dengan 4 tahap
- **Kenapa dibutuhkan (tujuan bisnisnya):** Resign, mutasi, berhenti rangkap Sales, dan pengalihan portfolio besar membutuhkan flow dealer + target + actual + status user yang berbeda dari ordinary Transfer Dealer.
- **Halaman/menu:** `ADMIN → PENGGUNA & AKSES / Edit User → ALIHKAN PORTOFOLIO`
- **Role yang pakai:** Admin, Super Admin
- **Perilaku yang diinginkan (langkah per langkah):**
  1. Tahap 1 `Penerima`: pilih source, recipient(s), alasan, effective date/period.
  2. Tahap 2 `Bagi Dealer`: alokasikan dealer-brand source ke recipient.
  3. Tahap 3 `Target & Aktual`: distribusi target dan preview actual.
  4. Tahap 4 `Preview`: ringkasan seluruh dampak sebelum commit.
- **Aturan bisnis:**
  - Alasan minimal: Resign, Mutasi, Berhenti rangkap Sales, Cuti panjang, Lainnya.
  - Multi-recipient diperbolehkan.
  - Source dan recipient tidak boleh sama.
  - Jangan membuat fitur `Transfer Target` terpisah.
  - `Cuti panjang` tidak otomatis diperlakukan sebagai resign/permanent deactivation.
- **Cara ngetes (hasil yang diharapkan):**
  1. Hanya Admin/Super Admin dapat membuka flow.
  2. Source = recipient → ditolak.
  3. Flow selalu mengikuti urutan 4 tahap.
  4. Tidak ada target transfer di ordinary Transfer Dealer.
- **Prioritas:** tinggi
- **Status:** setengah jadi

---

## Request #7
- **Judul:** Pembagian seluruh dealer-brand portfolio ke multi-recipient
- **Kenapa dibutuhkan (tujuan bisnisnya):** Portfolio satu Sales dapat dibagi ke beberapa pengganti dan semua active ownership harus dialokasikan, termasuk dealer yang belum punya actual bulan berjalan.
- **Halaman/menu:** `ALIHKAN PORTOFOLIO → Bagi Dealer`
- **Role yang pakai:** Admin, Super Admin
- **Perilaku yang diinginkan (langkah per langkah):**
  1. Ambil daftar assignment ownership source, bukan hanya transaksi actual.
  2. Tampilkan seluruh dealer-brand aktif source termasuk actual 0.
  3. Sediakan search/filter/checklist.
  4. Assign selection ke recipient tertentu atau bulk assign.
  5. Tampilkan `Belum Dialokasikan`, summary per recipient, dan progress x/x.
  6. Blokir lanjut bila allocation wajib belum 100%.
- **Aturan bisnis:**
  - Full resign/full transfer wajib mengalokasikan seluruh active assignment source.
  - Dealer-brand yang sudah dimiliki Sales lain tidak boleh ditimpa diam-diam.
  - Actual ST bukan sumber tunggal daftar portfolio.
- **Cara ngetes (hasil yang diharapkan):**
  1. Dealer-brand actual 0 tetap muncul.
  2. Portfolio dapat dibagi ke recipient A+B.
  3. Ada assignment belum dialokasikan → Next diblok.
  4. Conflict ownership → tidak overwrite otomatis.
- **Prioritas:** tinggi
- **Status:** setengah jadi

---

## Request #8
- **Judul:** Distribusi target di-merge dan actual mengikuti dealer-brand riil
- **Kenapa dibutuhkan (tujuan bisnisnya):** Recipient mungkin sudah punya target sendiri, sedangkan actual harus mengikuti transaksi dari dealer-brand yang benar-benar diterima, bukan persentase target.
- **Halaman/menu:** `ALIHKAN PORTOFOLIO → Target & Aktual`
- **Role yang pakai:** Admin, Super Admin
- **Perilaku yang diinginkan (langkah per langkah):**
  1. Sistem membaca target source yang relevan terhadap effective period.
  2. Sistem memberi suggested split yang boleh diedit.
  3. Total target yang didistribusikan harus sama dengan target source yang dipindah.
  4. Recipient menampilkan `Before → Additional → After`.
  5. Actual MTD dihitung dari dealer-brand yang dialokasikan ke recipient.
  6. Preview menunjukkan target + actual final sebelum commit.
- **Aturan bisnis:**
  - Target recipient harus **merge/add**, bukan overwrite existing target.
  - Target history periode lama tetap pada source.
  - Layer target existing seperti `Target_Input`, `Target_Periode`, dan KPI/SO khusus yang relevan harus mengikuti model data existing.
  - Actual tidak boleh dibagi berdasarkan persentase target.
  - Histori actual sebelum effective date tidak dipindahkan.
- **Cara ngetes (hasil yang diharapkan):**
  1. Recipient target 100 + tambahan 40 → hasil 140.
  2. Distribusi target tidak 100% reconcile → commit diblok.
  3. Dealer actual 7 dialokasikan ke recipient B → attribution B mengikuti 7 tersebut.
  4. Periode lama tidak berubah.
- **Prioritas:** tinggi
- **Status:** setengah jadi

---

## Request #9
- **Judul:** Status source + atomic commit + audit Alihkan Portofolio
- **Kenapa dibutuhkan (tujuan bisnisnya):** Pengalihan massal menyentuh ownership, target, attribution, dan status user sehingga tidak boleh ada kondisi setengah pindah.
- **Halaman/menu:** Backend `ALIHKAN PORTOFOLIO`
- **Role yang pakai:** Admin, Super Admin sebagai actor; sistem melakukan commit
- **Perilaku yang diinginkan (langkah per langkah):**
  1. Validasi source, recipient, seluruh allocation, target reconciliation, effective rule.
  2. Lock/checkpoint.
  3. Simpan effective ownership.
  4. Merge target.
  5. Rebuild actual attribution/report.
  6. Update status source jika sesuai alasan.
  7. Audit.
  8. Commit seluruhnya atau rollback seluruhnya.
- **Aturan bisnis:**
  - Resign penuh: source baru dapat dinonaktifkan setelah seluruh portfolio sukses dialihkan.
  - ASM/RGM berhenti rangkap Sales: role managerial tetap aktif, flag/portfolio Sales dimatikan.
  - History target lama tidak boleh menghalangi berhenti rangkap Sales.
  - Cuti panjang tidak otomatis menjadi permanent deactivation.
- **Cara ngetes (hasil yang diharapkan):**
  1. ASM stop rangkap → tetap ASM dan portfolio Sales hilang setelah proses sukses.
  2. Resign penuh → tidak dapat selesai jika portfolio masih tersisa.
  3. Paksa failure di tengah → dealer, target, actual attribution, dan status source semuanya rollback.
  4. Audit menyimpan actor dan hasil.
- **Prioritas:** tinggi
- **Status:** setengah jadi

---

# C. ADMIN & APPROVAL

## Request #10
- **Judul:** Pertahankan layout Admin existing dan hilangkan panel legacy yang sudah digantikan
- **Kenapa dibutuhkan (tujuan bisnisnya):** Admin harus tetap familiar, tidak penuh duplikasi, dan tidak berubah desain hanya karena penambahan fitur.
- **Halaman/menu:** `ADMIN`
- **Role yang pakai:** Admin, Super Admin
- **Perilaku yang diinginkan (langkah per langkah):**
  1. Pertahankan kategori, card, spacing, focus-mode, dan pola navigasi Admin existing.
  2. Hide UI lama `PIC Dealer per Brand` bila sudah digantikan flow ownership baru.
  3. Hide panel Transfer Dealer lama di Admin karena final menu berada di `LAINNYA`.
  4. Jangan hapus engine/data ownership yang masih dipakai backend.
- **Aturan bisnis:**
  - Tidak boleh redesign global Admin.
  - Perubahan hanya pada bagian yang memang digantikan.
- **Cara ngetes (hasil yang diharapkan):**
  1. Admin tetap terlihat seperti baseline existing.
  2. Tidak ada dua pintu Transfer Dealer.
  3. Hide panel tidak memutus backend ownership.
- **Prioritas:** tinggi
- **Status:** belum dikerjakan

---

## Request #11
- **Judul:** Satu tab `APPROVAL` sebagai pusat seluruh request Admin
- **Kenapa dibutuhkan (tujuan bisnisnya):** Admin tidak boleh bingung harus approve request di panel mana. Semua approval harus masuk ke satu inbox.
- **Halaman/menu:** `ADMIN → APPROVAL`
- **Role yang pakai:** Admin, Super Admin
- **Perilaku yang diinginkan (langkah per langkah):**
  1. Admin membuka satu tab/panel `APPROVAL`.
  2. Tab menampilkan seluruh request yang butuh approval.
  3. Minimal mencakup:
     - perubahan kontak dealer;
     - Transfer Dealer dari ASM/RGM.
  4. Setiap row/card menampilkan jenis request, requester, waktu, status, dan ringkasan perubahan.
  5. Klik request membuka detail human-readable.
  6. Admin melakukan approve/reject dari tab yang sama.
  7. Approval type baru di masa depan masuk ke tab ini, bukan membuat panel approval terpisah.
- **Aturan bisnis:**
  - Ada **badge jumlah pending** pada tab/entry Approval.
  - Ada filter minimal **jenis request** dan **status**.
  - Default mudah memprioritaskan request `PENDING`/terbaru.
  - Detail perubahan kontak menampilkan `before → after`.
  - Detail Transfer Dealer menampilkan requester, source, destination, dealer-brand, effective date, alasan.
  - Jangan tampilkan raw JSON sebagai detail utama.
  - Business rule approval tiap jenis request tetap berada di engine masing-masing; tab ini adalah pusat UI/inbox.
- **Cara ngetes (hasil yang diharapkan):**
  1. Buat pending perubahan kontak + pending Transfer Dealer → keduanya muncul di tab `APPROVAL`.
  2. Badge pending sesuai jumlah request pending.
  3. Filter jenis/status bekerja.
  4. Approve/reject dapat dilakukan tanpa pindah ke panel lain.
  5. Tidak ada panel approval duplikat di area Admin lain.
- **Prioritas:** tinggi
- **Status:** setengah jadi

---

## Request #12
- **Judul:** Hapus `NOTIFIKASI KHUSUS` dari Admin tanpa merusak fitur perangkat/notifikasi
- **Kenapa dibutuhkan (tujuan bisnisnya):** Panel Admin tersebut redundant karena fungsi notifikasi/perangkat sudah punya tempat sendiri.
- **Halaman/menu:** `ADMIN`; fitur tetap tersedia melalui area Perangkat/Notifikasi existing
- **Role yang pakai:** Admin, Super Admin
- **Perilaku yang diinginkan (langkah per langkah):**
  1. Buka Admin.
  2. `NOTIFIKASI KHUSUS` tidak tampil.
  3. Halaman Perangkat/Notifikasi existing tetap berfungsi.
- **Aturan bisnis:**
  - Hapus/hide hanya UI redundant di Admin.
  - Backend push, subscription, device, dan notification settings tidak boleh rusak.
- **Cara ngetes (hasil yang diharapkan):**
  1. Panel tidak ada di Admin.
  2. Perangkat Saya/notifikasi tetap bisa dibuka, disimpan, dan dites.
- **Prioritas:** sedang
- **Status:** belum dikerjakan

---

# D. KOREKSI & ATTRIBUTION TRANSAKSI

## Request #13
- **Judul:** Buat menu native `KOREKSI TRANSAKSI` di `LAINNYA`
- **Kenapa dibutuhkan (tujuan bisnisnya):** Admin membutuhkan area khusus untuk review/koreksi attribution transaksi tanpa mencampurkannya ke halaman Admin.
- **Halaman/menu:** `LAINNYA → KOREKSI TRANSAKSI`
- **Role yang pakai:** Admin, Super Admin
- **Perilaku yang diinginkan (langkah per langkah):**
  1. Admin/Super Admin login.
  2. Satu entry `KOREKSI TRANSAKSI` tampil di kelompok `LAINNYA`.
  3. Klik membuka halaman review/koreksi transaksi.
- **Aturan bisnis:**
  - Tidak berada di Admin.
  - Hanya satu entry.
  - Nama final `KOREKSI TRANSAKSI`; label lama `PENGECEKAN TRANSAKSI` tidak dipakai.
  - Label harus tampil penuh; wrap boleh, ellipsis tidak.
  - Jangan membuat menu dengan clone `DEALER` atau injection non-native.
  - ASM/RGM/Sales tidak boleh mengakses.
- **Cara ngetes (hasil yang diharapkan):**
  1. Admin/Super Admin → tepat satu menu.
  2. ASM/RGM/Sales → tidak melihat menu dan backend menolak.
  3. Desktop/mobile → label penuh dan menu mengikuti styling native.
- **Prioritas:** tinggi
- **Status:** belum dikerjakan

---

## Request #14
- **Judul:** Halaman Koreksi Transaksi sebagai review queue, bukan browser seluruh transaksi
- **Kenapa dibutuhkan (tujuan bisnisnya):** Admin perlu fokus pada exception yang perlu diperiksa dan sistem harus tetap ringan pada dataset besar.
- **Halaman/menu:** `KOREKSI TRANSAKSI`
- **Role yang pakai:** Admin, Super Admin
- **Perilaku yang diinginkan (langkah per langkah):**
  1. Halaman default membuka filter/status `Perlu Ditinjau`.
  2. Default periode = bulan berjalan.
  3. Sediakan filter periode dan region.
  4. Sediakan search dealer/kode/invoice/Sales/SKU jika field tersedia.
  5. List memakai server-side filtering dan pagination.
  6. Klik item membuka detail/aksi koreksi.
- **Aturan bisnis:**
  - Jangan load semua raw transaction ke browser lalu filter client-side.
  - Queue fokus pada item yang memiliki alasan untuk review.
- **Cara ngetes (hasil yang diharapkan):**
  1. Dataset besar tetap responsif.
  2. Default menampilkan item `Perlu Ditinjau`.
  3. Filter/search/pagination bekerja di server.
- **Prioritas:** tinggi
- **Status:** belum dikerjakan

---

## Request #15
- **Judul:** Model Raw Sales vs Effective Sales + precedence attribution yang historical-aware
- **Kenapa dibutuhkan (tujuan bisnisnya):** DSM harus dapat menjelaskan Sales asli dari sumber dan Sales efektif untuk report tanpa merusak raw transaction.
- **Halaman/menu:** Shared backend transaction attribution
- **Role yang pakai:** Sistem; hasil dipakai seluruh role sesuai scope
- **Perilaku yang diinginkan (langkah per langkah):**
  1. Pertahankan `Raw Sales` dari data ST asli.
  2. Resolve ownership dealer-brand berdasarkan tanggal transaksi.
  3. Terapkan effective ownership hasil transfer.
  4. Terapkan manual transaction correction **paling akhir** untuk transaksi spesifik.
  5. Hasil akhirnya menjadi `Effective Sales`.
  6. Report/snapshot memakai Effective Sales, sementara Raw Sales tetap tersedia untuk audit.
- **Aturan bisnis:**
  - Raw ST tidak boleh di-rewrite untuk menyesuaikan ownership/correction.
  - Precedence final:
    `Raw ST → historical dealer-brand ownership by date → transfer/effective ownership → manual transaction correction → report`.
  - Manual correction memiliki precedence tertinggi hanya untuk transaksi terkait.
  - Marker attribution harus historical-aware:
    - `Sales ST berbeda`
    - `Dialihkan melalui transfer`
    - `Koreksi khusus`
  - Transfer sekarang tidak boleh membuat transaksi periode lama mendapatkan marker/owner baru secara salah.
- **Cara ngetes (hasil yang diharapkan):**
  1. Transaksi normal → Raw Sales = Effective Sales.
  2. Transfer effective tanggal X → transaksi sebelum X tetap owner lama.
  3. Manual correction → Effective Sales mengikuti correction, Raw Sales tetap.
  4. Marker sesuai penyebab attribution.
- **Prioritas:** tinggi
- **Status:** setengah jadi

---

## Request #16
- **Judul:** Koreksi khusus Sales efektif untuk satu transaksi + audit lengkap
- **Kenapa dibutuhkan (tujuan bisnisnya):** Kesalahan attribution spesifik harus bisa diperbaiki tanpa memindahkan ownership dealer atau mengubah transaksi lain.
- **Halaman/menu:** `KOREKSI TRANSAKSI → detail/aksi koreksi`
- **Role yang pakai:** Admin, Super Admin
- **Perilaku yang diinginkan (langkah per langkah):**
  1. Pilih satu transaksi.
  2. Pilih Sales efektif yang benar.
  3. Isi alasan wajib.
  4. Simpan.
  5. Sistem rebuild attribution transaksi terkait.
  6. Audit menyimpan siapa, kapan, original Sales, corrected Sales, reason, dan stable transaction identity.
- **Aturan bisnis:**
  - Hanya transaksi terkait yang dikoreksi.
  - Dealer ownership tidak berubah.
  - Target tidak berubah.
  - Raw ST tidak berubah.
  - Transaksi lain tidak berubah.
  - Total company tidak berubah.
- **Cara ngetes (hasil yang diharapkan):**
  1. Koreksi A→B untuk 3 unit → A -3, B +3, total company tetap.
  2. Ownership dealer tetap sama.
  3. Raw Sales tetap A, Effective Sales menjadi B.
  4. Audit record lengkap dan marker `Koreksi khusus` muncul.
- **Prioritas:** tinggi
- **Status:** belum dikerjakan

---

## Request #17
- **Judul:** Koreksi transaksi harus bertahan pada re-upload harian dengan stable identity
- **Kenapa dibutuhkan (tujuan bisnisnya):** Data ST tanggal 1 sampai hari berjalan dapat diganti setiap upload; koreksi tidak boleh hilang atau membuat actual dobel.
- **Halaman/menu:** Backend import/reupload + correction persistence
- **Role yang pakai:** Sistem; koreksi dibuat Admin/Super Admin
- **Perilaku yang diinginkan (langkah per langkah):**
  1. Admin membuat koreksi transaksi.
  2. Data periode berjalan diupload ulang.
  3. Sistem mencocokkan transaksi dengan stable identity.
  4. Koreksi logical yang sama tetap menempel.
  5. Snapshot/report dibangun ulang tanpa duplikasi.
- **Aturan bisnis:**
  - Stable key **tidak boleh bergantung pada Sales NIK**.
  - Gunakan stable line/row ID bila tersedia.
  - Jika tidak ada, prioritaskan business unit + invoice/document + line identifier.
  - Composite fallback harus mempertimbangkan duplicate-looking lines, misalnya date/company/document/SKU/dealer/qty/amount.
  - Satu koreksi logical tidak boleh menjadi beberapa koreksi setelah reupload.
- **Cara ngetes (hasil yang diharapkan):**
  1. Buat koreksi → upload ulang hari berikutnya → correction tetap aktif.
  2. Unit/omzet tidak dobel.
  3. Satu logical transaction tetap satu correction.
- **Prioritas:** tinggi
- **Status:** belum dikerjakan

---

## Request #18
- **Judul:** Seluruh modul harus memakai Effective Sales yang sama
- **Kenapa dibutuhkan (tujuan bisnisnya):** Transfer/koreksi tidak boleh benar di satu halaman tetapi berbeda di KPI, ranking, dealer detail, atau export.
- **Halaman/menu:** Beranda, KPI, Validasi KPI, Dealer, Ranking, Analisa, Brand, Tim/recap, transaksi/report, dan modul relevan lain
- **Role yang pakai:** Semua role sesuai scope
- **Perilaku yang diinginkan (langkah per langkah):**
  1. Shared attribution engine menghasilkan Effective Sales.
  2. Snapshot/report menggunakan hasil tersebut.
  3. Semua endpoint/modul membaca attribution yang sama.
  4. Rebuild/publish setelah transfer/correction memperbarui semua tampilan terkait.
- **Aturan bisnis:**
  - Jangan membuat patch attribution berbeda per halaman.
  - Formula KPI existing seperti ST/DA/NOO/Omzet tetap; yang berubah hanya siapa Sales yang menerima attribution.
  - Company/national total harus invariant.
- **Cara ngetes (hasil yang diharapkan):**
  1. Koreksi A→B muncul konsisten di Beranda, KPI, validasi KPI, Dealer, Ranking, Analisa, Brand, Tim, dan report.
  2. Total company sebelum/sesudah tetap sama.
  3. Periode historical tetap konsisten dengan effective date.
- **Prioritas:** tinggi
- **Status:** belum dikerjakan

---

## Request #19
- **Judul:** Export membawa lineage Raw Sales, Effective Sales, dan alasan attribution
- **Kenapa dibutuhkan (tujuan bisnisnya):** Rekonsiliasi di luar DSM membutuhkan transparansi antara data sumber dan attribution final.
- **Halaman/menu:** Export transaksi/report yang relevan
- **Role yang pakai:** Sesuai hak export existing; data koreksi sensitif mengikuti akses yang berlaku
- **Perilaku yang diinginkan (langkah per langkah):**
  1. Generate export.
  2. Sertakan `Raw Sales`.
  3. Sertakan `Effective Sales`.
  4. Sertakan marker/attribution.
  5. Sertakan correction reason bila relevan dan diizinkan.
- **Aturan bisnis:**
  - Raw lineage tidak boleh hilang.
  - Hak export existing tetap dipatuhi.
- **Cara ngetes (hasil yang diharapkan):**
  1. Transaksi normal → Raw = Effective.
  2. Transfer/correction → export menunjukkan perbedaan dan marker yang sesuai.
  3. Total hasil export tetap rekonsiliasi dengan report.
- **Prioritas:** sedang
- **Status:** belum dikerjakan

---

# E. QA / REGRESSION GUARD

## Request #20
- **Judul:** Regression guard dan runtime QA sebelum dianggap selesai
- **Kenapa dibutuhkan (tujuan bisnisnya):** Revisi tidak boleh memperbaiki satu fungsi sambil merusak sidebar, Admin, mobile, role access, atau formula existing.
- **Halaman/menu:** Semua area yang disentuh revisi
- **Role yang pakai:** Sales, ASM, RGM, Admin, Super Admin
- **Perilaku yang diinginkan (langkah per langkah):**
  1. Audit arsitektur existing sebelum coding.
  2. Implementasi targeted.
  3. Jalankan static/syntax check.
  4. Jalankan runtime test desktop + mobile.
  5. Test role-by-role.
  6. Test backend authorization dengan payload valid dan out-of-scope.
  7. Test flow end-to-end sebelum menyatakan selesai.
- **Aturan bisnis:**
  - `php -l` / syntax check saja tidak cukup.
  - Jangan mengubah global CSS/DOM bila solusi lokal cukup.
  - Tidak boleh muncul duplicate menu, menu pucat/non-native, label ellipsis penting, clipped dropdown/form, giant mobile card, atau role menu hilang.
  - `main` tidak boleh disentuh; pekerjaan tetap di branch `dev` sesuai workflow project.
- **Cara ngetes (hasil yang diharapkan):**
  1. Desktop sidebar/focus mode/scrolling tetap normal.
  2. Mobile bottom nav, drawer, modal, dropdown, sticky action tetap normal.
  3. Sales/ASM/RGM/Admin/Super Admin mendapat menu/data/action sesuai role.
  4. Transfer single-brand, multi-brand, split owner, zero-actual, stale approval, reject, atomic failure diuji.
  5. Alih Portfolio multi-recipient, target merge, zero-actual assignment, rollback diuji.
  6. Koreksi normal, mismatch, transferred, manual correction, old-period, reupload, duplicate-looking row diuji.
- **Prioritas:** tinggi
- **Status:** belum dikerjakan

---

# Keputusan Final yang Mengalahkan Versi Lama

- `TRANSFER DEALER` final berada di **`LAINNYA`**, bukan `PANTAU` dan bukan Admin.
- Ordinary Transfer Dealer memilih **satu destination di awal**, bukan destination per dealer.
- Ordinary Transfer Dealer **tidak memindahkan target**.
- `ALIHKAN PORTOFOLIO` adalah flow terpisah untuk pengalihan portfolio + target + actual + status user.
- `KOREKSI TRANSAKSI` final berada di **`LAINNYA`**, bukan Admin.
- Nama `PENGECEKAN TRANSAKSI` tidak dipakai lagi.
- Admin memiliki **satu tab `APPROVAL`** untuk seluruh jenis request approval.
- Approval Center harus mempunyai **badge pending + filter jenis/status**.
- Panel `NOTIFIKASI KHUSUS` di Admin dihapus/hide; backend notifikasi/perangkat tetap.
- Jangan membuat fitur `Transfer Target` terpisah.
- Jangan mengembalikan clone DOM/floating menu sebagai solusi navigasi.

---

# Pemetaan dari `request-revisi.md` Versi Lama

Tujuannya memastikan tidak ada requirement yang hilang saat 29 request dirapikan menjadi 20 request.

| Request lama | Masuk ke request V2 |
|---|---|
| #1 | #1 |
| #2 + #3 | #2 |
| #4 + #6 | #3 |
| #5 + #7 | #4 |
| #8 + #9 | #5 |
| #10 + #11 | #6 |
| #12 | #7 |
| #13 + #14 | #8 |
| #15 + #16 | #9 |
| #17 | #10 |
| #18 | #11 |
| #19 | #12 |
| #20 | #13 |
| #21 | #14 |
| #22 + #24 + #26 | #15 |
| #23 | #16 |
| #25 | #17 |
| #27 | #18 |
| #28 | #19 |
| #29 | #20 |

**Kesimpulan:** tidak ada request lama yang dihapus. Yang digabung hanya request yang membahas satu tujuan bisnis/flow yang sama.
