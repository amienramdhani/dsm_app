# DSM — MASTER HANDOFF REVISI 17–18 OKTOBER 2026

> **Tujuan dokumen:** menjadi source of truth untuk Claude saat melanjutkan revisi DSM setelah branch `dev` dikembalikan ke versi terakhir tanggal 17 Oktober 2026.
>
> Dokumen ini sengaja dibuat sangat detail. Jika ada perbedaan antara request lama dan request terbaru, **ikuti bagian “FINAL / LATEST DECISION”** di dokumen ini.
>
> **PENTING:** Jangan menjadikan patch ChatGPT tanggal 18 Oktober sebagai acuan UI/arsitektur. Patch tersebut mengalami beberapa regression. Gunakan aplikasi DSM versi tanggal 17 sebagai baseline visual dan behavior, lalu implementasikan requirement final di bawah secara targeted.

---

# 0. STATUS REPOSITORY DAN BASELINE

## 0.1 Repository

Repository yang boleh disentuh:

```text
aryhidayattt/dsm
```

Branch development:

```text
dev
```

Branch production/stable:

```text
main
```

## 0.2 Baseline yang akan dipakai

Versi terakhir branch `dev` pada tanggal 17 Oktober 2026:

```text
76cb12b6b5e334ccf0e6ace68fb29df4a4538bb2
Bump transfer hotfix cache version
```

Ini yang akan dijadikan baseline setelah rollback.

## 0.3 Kondisi branch saat dokumen dibuat

Branch `dev` tanggal 18 sudah mengalami beberapa patch ChatGPT yang bermasalah.

Head yang terakhir terdeteksi sebelum rollback:

```text
302d0b6cbcb43ec5c1371c9372d2ff60cf43a1c0
Reload DSM cleanup bundle
```

Jangan menganggap head tanggal 18 sebagai baseline final.

## 0.4 Main

`main` masih sangat lama / baseline awal:

```text
6dcc23d36fea19b2708c26dab738e74156062046
Initial DSM development baseline
```

**Jangan push / merge ke `main` selama revisi ini belum runtime-tested dan disetujui user.**

## 0.5 Safety rules repository

Wajib:

- Kerja dari baseline tanggal 17 di `dev` atau branch kerja turunan `dev`.
- Jangan menyentuh `main`.
- Jangan menyentuh repo milik Amin.
- Audit source existing sebelum coding.
- Gunakan perubahan sekecil mungkin.
- Jangan mengubah file besar / shell aplikasi hanya untuk “mempermudah” kalau ada hook existing yang lebih aman.
- Jangan menambah hotfix bertumpuk yang saling menimpa DOM.
- Jangan claim “sudah aman” tanpa runtime test.
- Jangan push ke production.
- Setelah coding: test lokal dahulu.
- Setelah user menyatakan cocok, baru lakukan langkah deploy/merge berikutnya.

---

# 1. FILOSOFI REVISI

DSM existing tanggal 17 adalah **source of truth visual**.

Artinya:

- struktur sidebar existing dipertahankan,
- kategori Admin existing dipertahankan,
- font existing dipertahankan,
- spacing existing dipertahankan,
- kartu existing tidak boleh membengkak,
- mobile behavior existing tidak boleh rusak,
- bottom navigation existing tidak boleh rusak,
- role/permission existing tidak boleh diubah kecuali requirement ini memang meminta,
- formula KPI existing tidak boleh diubah,
- nomenklatur existing jangan diganti sembarangan,
- perubahan baru harus terasa sebagai bagian native dari DSM, bukan “plugin” yang ditempel.

User sangat tidak ingin:

- layout Admin berubah total,
- menu dibuat dengan clone DOM asal-asalan,
- label menu terpotong `...`,
- card terlalu besar,
- form clipped,
- dropdown tertutup overflow parent,
- feature existing hilang saat feature baru masuk,
- menu menjadi duplikat,
- satu request menghasilkan beberapa panel berbeda padahal harus satu pintu.

---

# 2. PEMBEDAAN BESAR: TRANSFER DEALER VS ALIHKAN PORTOFOLIO

Dua fitur ini **berbeda total** dan jangan dicampur.

## 2.1 Transfer Dealer

Dipakai untuk:

- memindahkan dealer tertentu,
- dari satu Sales aktif ke Sales aktif lain,
- sumber tetap aktif,
- perpindahan bisa hanya brand tertentu,
- target Sales **tidak ikut pindah**,
- fokus pada ownership operasional dealer-brand.

Contoh:

```text
Sales A tetap bekerja.
Dealer ABC brand iPhone dipindahkan ke Sales B.
Brand lain dealer ABC boleh tetap di Sales A.
```

## 2.2 Alihkan Portofolio / Transfer Sales

Dipakai untuk kondisi besar seperti:

- Sales resign,
- Sales mutasi total,
- ASM/RGM berhenti rangkap Sales,
- kebutuhan pengalihan seluruh portfolio,
- cuti panjang pernah dibahas sebagai alasan, tetapi treatment status akun akhir untuk cuti perlu hati-hati dan jangan diasumsikan sama dengan resign tanpa keputusan user.

Flow ini mencakup:

- dealer,
- ownership dealer-brand,
- target,
- actual MTD,
- effective date,
- status user bila relevan,
- histori,
- audit,
- rollback.

**Jangan membuat “Transfer Target” sebagai fitur/menu terpisah.**

Target hanya ikut pada **Alihkan Portofolio**, bukan Transfer Dealer biasa.

---

# 3. TRANSFER DEALER — FINAL / LATEST DECISION

## 3.1 Lokasi menu

**FINAL:**

```text
LAINNYA
└── TRANSFER DEALER
```

Jangan lagi taruh Transfer Dealer di Admin.

Jangan lagi mengikuti keputusan lama tanggal 17 yang menaruh Transfer Dealer di grup `PANTAU` setelah `DEALER`.

Keputusan `PANTAU` adalah **superseded**.

## 3.2 Role yang bisa melihat

Menu Transfer Dealer harus tersedia untuk:

- ASM
- RGM
- Admin
- Super Admin

Tidak tersedia untuk Sales biasa.

Role lain jangan otomatis diberi akses kecuali memang existing DSM sudah punya authority yang setara dan user menyetujui.

## 3.3 Perbedaan behavior per role

### ASM

ASM boleh:

- memilih Sales sumber di bawah scope ASM,
- memilih Sales tujuan di bawah scope ASM,
- memilih dealer,
- memilih brand dealer yang dipindahkan,
- isi tanggal efektif,
- isi alasan,
- preview,
- submit request.

ASM **tidak boleh langsung mengubah ownership**.

Final action ASM:

```text
AJUKAN TRANSFER
```

Status setelah submit:

```text
MENUNGGU APPROVAL ADMIN
```

### RGM

Sama seperti ASM, tetapi scope mengikuti bawahan RGM.

RGM juga hanya mengajukan.

Ownership belum berubah sampai approval.

### Admin

Admin boleh:

- memilih Sales sumber,
- memilih Sales tujuan,
- memilih dealer-brand,
- preview,
- menjalankan transfer langsung.

Admin tidak harus membuat pending request untuk transfer yang dia lakukan sendiri.

### Super Admin

Behavior setara Admin untuk fitur ini.

## 3.4 Backend authorization WAJIB

Jangan hanya filter dropdown frontend.

Backend harus melakukan validation ulang.

Contoh:

```text
ASM A hanya berwenang Sales 1–5.
User memanipulasi request API dan mengirim Sales 20.
Backend wajib reject.
```

Validasi server-side minimal:

- actor masih valid,
- role masih berhak,
- Sales sumber masih di scope actor,
- Sales tujuan masih di scope actor,
- Sales sumber != Sales tujuan,
- dealer masih dimiliki source untuk brand yang diminta,
- brand yang dikirim memang valid,
- effective date valid,
- alasan valid,
- request belum pernah diproses bila flow approval.

Admin/Super Admin memakai authority Admin, bukan scope ASM/RGM.

---

# 4. TRANSFER DEALER — PEMILIHAN SALES

## 4.1 Sales sumber

Searchable selector / combobox.

Pencarian minimal mendukung:

- nama,
- NIK,
- role,
- region.

Contoh isi hasil:

```text
REFKY WIJAYA
2025.05.05.06 · SALES · JAKARTA
```

## 4.2 Sales tujuan

**FINAL: dipilih satu kali di awal.**

User secara eksplisit tidak mau destination Sales per dealer untuk Transfer Dealer harian.

Flow:

```text
Pilih Sales Sumber
→ Pilih Sales Tujuan
→ Pilih Dealer/Brand
```

Jangan tampilkan dropdown “Sales Tujuan” berulang di setiap dealer.

Jangan pakai per-row override untuk Transfer Dealer biasa.

Per-row/multi-recipient allocation hanya untuk **Alihkan Portofolio**.

---

# 5. TRANSFER DEALER — DEALER × BRAND

Ini requirement paling penting.

## 5.1 Transfer tidak boleh dealer-only

Ownership harus dapat dipisah berdasarkan:

```text
dealer + brand
```

Jangan transfer seluruh dealer otomatis hanya karena user mencentang dealer.

## 5.2 Brand selector

Sediakan pilihan brand.

Preferred UI:

- dropdown/checklist,
- bisa pilih beberapa brand,
- opsi “Semua brand”,
- label ringkas seperti `3 brand dipilih`,
- setelah pilih selesai, list dealer otomatis mengikuti.

## 5.3 Dealer hanya satu baris

Satu dealer tampil satu kali.

Jangan satu dealer diulang berkali-kali per brand.

Contoh:

```text
ANGIE SHOP.COM
320.827 · JABODETABEK

Brand yang akan dipindahkan:
[IPHONE] [ITEL] [MOTOPAD]
```

## 5.4 Partial match wajib tetap muncul

Contoh user memilih brand:

```text
IPHONE
ITEL
MOTOPAD
```

Dealer A hanya punya:

```text
IPHONE
ITEL
```

Dealer A **tetap harus muncul**.

Chip pada row hanya menunjukkan brand terpilih yang memang dimiliki dealer/source.

Jangan require dealer punya semua brand filter.

## 5.5 Brand yang tidak dipilih tetap pada Sales lama

Contoh:

```text
Dealer ABC
IPHONE   -> Sales A
REALME   -> Sales A
TECNO    -> Sales A
```

User transfer hanya:

```text
IPHONE -> Sales B
```

Hasil:

```text
IPHONE   -> Sales B
REALME   -> Sales A
TECNO    -> Sales A
```

Jangan menghasilkan assignment `ALL` yang diam-diam memindahkan semuanya.

---

# 6. TRANSFER DEALER — PRIMARY PIC DAN FUTURE BRAND

Dealer bisa punya ownership per brand.

Tetapi juga ada konsep PIC/owner utama untuk fallback.

## 6.1 Full consolidation

Contoh sebelum:

```text
Dealer ABC
IPHONE -> Sales A
ITEL -> Sales A
REALME -> Sales A
```

Semua assignment aktif dipindahkan ke Sales B.

Hasil:

```text
Primary PIC Dealer ABC -> Sales B
IPHONE -> Sales B
ITEL -> Sales B
REALME -> Sales B
```

## 6.2 Future brand

Setelah semua assignment dealer terkonsolidasi ke Sales B, bila nanti muncul brand baru yang belum pernah punya explicit brand assignment:

```text
MOTOROLA baru muncul
```

fallback harus mengikuti:

```text
Sales B
```

bukan Sales A lama.

## 6.3 Split ownership

Contoh:

```text
IPHONE -> Sales A
REALME -> Sales B
```

Dealer masih split.

Jangan mengubah primary/fallback secara agresif sampai brand aktif yang relevan benar-benar konvergen sesuai rule.

Explicit brand assignment tetap menang atas generic owner.

---

# 7. TRANSFER DEALER — UI DESKTOP

Target: compact, padat, konsisten dengan DSM.

## 7.1 Struktur

Flow terbaru:

```text
1. SALES
2. DEALER
3. PREVIEW
```

Tidak perlu wizard besar berlebihan.

## 7.2 Header/filter

Minimal:

- Sales sumber,
- Sales tujuan,
- tanggal efektif,
- search dealer,
- area/region filter bila relevan,
- brand multi-select,
- page size,
- pilih semua hasil filter,
- kosongkan pilihan.

## 7.3 Tabel

Kolom yang relevan:

- checkbox,
- kode / nama dealer,
- area,
- brand yang dipindahkan,
- aktual MTD,
- owner sekarang.

Destination sudah dipilih di atas, jadi jangan ada dropdown destination per row.

## 7.4 Pagination

Untuk data besar:

- pagination,
- 25 / 50 / 100 rows,
- bulk selection,
- selection tidak hilang ketika pindah page/filter selama dealer masih valid.

## 7.5 Summary

Minimal:

- dealer sumber,
- dealer dipilih,
- brand assignment dipilih,
- aktual MTD terpilih.

## 7.6 Alasan

Alasan wajib.

Minimal 5 karakter adalah threshold yang pernah dipakai di mockup/logic.

Jangan proses kalau alasan kosong/terlalu pendek.

---

# 8. TRANSFER DEALER — UI MOBILE

Mobile harus benar-benar didesain sebagai mobile.

Bukan desktop table yang dipaksa mengecil.

## 8.1 Flow

```text
Sales
→ Dealer
→ Preview
```

## 8.2 Row dealer

Compact row/card.

Contoh:

```text
☑ ANGIE SHOP.COM
  320.827 · JABODETABEK

  IPHONE · ITEL · MOTOPAD

  Aktual MTD: 19
```

Tidak perlu banyak box/kartu nested.

## 8.3 Sticky action

Action area harus sticky di bawah konten, tetapi:

- tidak menutupi bottom navigation,
- memperhitungkan safe area,
- tidak membuat tombol keluar layar.

Contoh:

```text
3 dealer · 7 brand dipilih
[ Preview → ]
```

## 8.4 Jangan clipped

Wajib cek:

- combobox,
- dropdown brand,
- modal,
- preview,
- search results,
- textarea alasan.

Parent container tidak boleh memotong dropdown karena `overflow:hidden`.

---

# 9. TRANSFER DEALER — PREVIEW

Sebelum submit/execute selalu ada Preview.

Preview minimal menunjukkan:

```text
Sales sumber
Sales tujuan
Tanggal efektif
Jumlah dealer
Jumlah brand assignment
Dealer per baris
Brand yang dipindahkan per dealer
Aktual MTD terkait
Alasan
```

Contoh detail:

```text
ANGIE SHOP.COM · 320.827
IPHONE, ITEL
REFKY WIJAYA → IMAM FAISAL
19 unit MTD
```

Copy per role:

### ASM/RGM

Harus jelas:

```text
Pengajuan ini hanya membuat request.
Ownership belum berubah sampai Admin approve.
```

### Admin

Harus jelas:

```text
Setelah konfirmasi, transfer langsung diproses.
Jika gagal, ownership tetap kondisi semula.
```

---

# 10. APPROVAL TRANSFER DEALER

## 10.1 Pending request

ASM/RGM submit menghasilkan request dengan status:

```text
PENDING / MENUNGGU APPROVAL
```

Submission sendiri **tidak melakukan mutation ownership**.

## 10.2 Data request

Simpan minimal:

- request ID,
- actor,
- actor role,
- Sales sumber,
- Sales tujuan,
- tanggal efektif,
- dealer,
- exact brand per dealer,
- alasan,
- created timestamp,
- status,
- reviewer,
- review timestamp,
- review note.

## 10.3 Admin approve

Action:

```text
SETUJUI & JALANKAN TRANSFER
```

Saat approve:

1. lock / claim request,
2. pastikan status masih Pending,
3. revalidate seluruh scope/data,
4. jalankan exact dealer-brand transfer,
5. update primary PIC bila memenuhi aturan,
6. rebuild/publish data yang relevan,
7. baru ubah status Approved.

## 10.4 Admin reject

Action:

```text
TOLAK + CATATAN
```

Reject:

- tidak mengubah ownership,
- menyimpan reviewer,
- menyimpan catatan,
- status `REJECTED`.

## 10.5 Double approval protection

Double click / dua Admin bersamaan tidak boleh mengeksekusi transfer dua kali.

Gunakan guarded status transition:

```text
PENDING -> PROCESSING -> APPROVED
```

atau equivalent transactional lock.

---

# 11. ATOMIC TRANSFER / ROLLBACK

Wajib atomic.

Jika ada 20 dealer-brand pada request dan dealer ke-15 gagal:

**tidak boleh 14 dealer pertama terlanjur berubah.**

Harus:

```text
ROLLBACK SEMUA
```

Termasuk:

- dealer-brand assignment,
- master primary owner bila sempat berubah,
- history,
- snapshot pointer jika berubah,
- target kalau flow Alihkan Portofolio,
- status user bila flow Alihkan Portofolio.

Untuk Transfer Dealer biasa target tidak disentuh.

Jika execution gagal, status dapat:

```text
FAILED / GAGAL DIPROSES
```

Tetapi ownership harus sama persis seperti sebelum proses.

---

# 12. EFFECTIVE DATE DAN HISTORY

Semua ownership change harus effective-dated.

## 12.1 Tidak rewrite histori bulan lama

Contoh:

```text
September: Dealer ABC = Sales A
18 Oktober: pindah = Sales B
```

Laporan September harus tetap:

```text
Sales A
```

Oktober setelah effective date mengikuti Sales B sesuai ownership rule.

## 12.2 Jangan menulis ulang raw transaksi lama

Raw ST adalah bukti sumber.

Ownership effective dihitung pada reporting layer / resolved layer.

---

# 13. ALIHKAN PORTOFOLIO — LOKASI

Final location:

```text
ADMIN
└── PENGGUNA & AKSES
    └── Edit User / Alihkan Portofolio
```

Bukan di `LAINNYA`.

Bukan digabung dengan Transfer Dealer.

Bukan menu Transfer Target terpisah.

Pada flow Edit User, ketika portofolio Sales aktif hendak dimatikan:

```text
Portofolio Sales masih aktif
40 dealer, target periode aktif, dan pencapaian MTD masih terhubung...
[ Alihkan Portofolio ]
```

Portofolio harus dibereskan lebih dulu.

---

# 14. ALIHKAN PORTOFOLIO — USE CASE

Harus support sumber berupa:

- Sales biasa,
- ASM rangkap Sales,
- RGM rangkap Sales.

Alasan yang pernah disepakati/ditampilkan:

- Resign,
- Mutasi,
- Berhenti rangkap Sales,
- Cuti panjang,
- Lainnya.

Catatan:

- `Resign` jelas bisa berakhir user nonaktif setelah transfer.
- `Berhenti rangkap Sales` mematikan fungsi rangkap Sales setelah portfolio selesai.
- `Cuti panjang` status akhir belum pernah benar-benar difinalkan; jangan otomatis nonaktif permanen tanpa keputusan user.

---

# 15. ALIHKAN PORTOFOLIO — 4 TAHAP

Flow final:

```text
1. PENERIMA / SUMBER & ALASAN
2. BAGI DEALER
3. TARGET & AKTUAL
4. PREVIEW
```

## 15.1 Tahap 1 — sumber, alasan, effective mode

Data:

- Sales sumber,
- alasan,
- berlaku mulai,
- recipient Sales.

Effective mode minimal pernah disepakati:

```text
Bulan berjalan
Bulan berikutnya
```

Jika bulan berikutnya, system harus scheduled/effective-dated, bukan langsung mengubah current period.

## 15.2 Multi recipient

Alihkan Portofolio boleh ke:

- satu Sales,
- beberapa Sales.

Ini berbeda dengan Transfer Dealer biasa.

---

# 16. ALIHKAN PORTOFOLIO — BAGI DEALER

## 16.1 Semua portfolio wajib teralokasi untuk resign/full transfer

Untuk source resign / mutasi total / berhenti rangkap:

Semua assignment yang menjadi milik source harus dialokasikan.

**Termasuk brand yang bulan berjalan tidak punya penjualan.**

Jangan membangun daftar hanya dari transaksi ST bulan berjalan.

Source of truth adalah ownership/assignment, bukan actual saja.

## 16.2 Dealer-brand existing milik Sales lain jangan ditimpa

Contoh dealer split:

```text
Dealer ABC
IPHONE -> Source Sales
REALME -> Sales X
```

Saat portfolio Source dialihkan:

- IPHONE boleh dipindahkan,
- REALME milik Sales X jangan ditimpa.

## 16.3 Allocation UI

Harus support:

- checklist,
- assign per row,
- bulk assign,
- multi-recipient,
- filter/search,
- summary per recipient.

Filter penting:

```text
BELUM DIALOKASIKAN
```

Supaya Admin mudah melihat sisa dealer yang belum diberi recipient.

## 16.4 Progress

Contoh:

```text
38 / 42 dealer dialokasikan
```

Tombol lanjut tidak boleh aktif bila scenario full transfer mewajibkan 100% dan masih ada yang kosong.

## 16.5 Bulk

Boleh ada:

- pilih semua tampil,
- assign selected ke recipient,
- bagi merata.

Tetapi hasil harus terlihat dan editable.

---

# 17. ALIHKAN PORTOFOLIO — TARGET

Target ikut hanya di flow ini.

## 17.1 Target source harus direconcile

Target source harus terbagi 100%.

Contoh:

```text
SO source = 100
Andi = 60
Budi = 40
TOTAL = 100
```

Kalau:

```text
60 + 35 = 95
```

jangan lanjut.

## 17.2 Suggested target otomatis

System memberikan suggestion pembagian target berdasarkan pembagian dealer / kontribusi actual yang relevan.

Tetapi:

```text
ADMIN BOLEH EDIT MANUAL
```

## 17.3 Merge, jangan overwrite

Jika recipient sudah punya target:

```text
Target existing Andi = 80
Tambahan dari source = 20
```

Hasil:

```text
Target baru Andi = 100
```

Bukan 20.

## 17.4 Preview target

Harus terlihat:

```text
SEBELUM → TAMBAHAN → SESUDAH
```

Contoh:

```text
Andi
SO ALL
80 → +20 → 100
```

Ini lebih jelas daripada hanya menampilkan angka target hasil akhir.

## 17.5 Target layer existing

Dalam pack/mockup sebelumnya target yang perlu ikut mencakup data target existing DSM, termasuk:

- `Target_Input`,
- `Target_Periode`,
- target SO / KPI Khusus yang memang terkait user/source.

Jangan membuat schema target baru kalau existing schema sudah mencakupnya.

---

# 18. ALIHKAN PORTOFOLIO — ACTUAL MTD

Actual tidak dibagi berdasarkan persentase target.

Actual mengikuti dealer-brand yang benar-benar dialihkan.

Contoh:

```text
Dealer A actual 30 -> Andi
Dealer B actual 20 -> Budi
```

Hasil:

```text
Andi +30
Budi +20
```

Bukan pembagian persentase semata.

Raw ST tetap tidak diubah.

---

# 19. ALIHKAN PORTOFOLIO — PREVIEW

Sebelum commit, tampilkan minimal:

```text
Dari siapa
Ke siapa saja
Alasan
Effective date/mode
Total dealer
Allocation per recipient
Brand assignment
Actual MTD per recipient
Target sebelum
Target tambahan
Target sesudah
Status akhir source
Histori
```

Preview harus cukup detail untuk Admin bisa mendeteksi salah distribusi sebelum eksekusi.

---

# 20. ALIHKAN PORTOFOLIO — STATUS USER

### Resign

Setelah sukses:

- portfolio 100% sudah dialihkan,
- target sudah dialihkan,
- actual effective sudah benar,
- source boleh dinonaktifkan sesuai flow existing.

### ASM/RGM berhenti rangkap Sales

Setelah sukses:

- role ASM/RGM tetap,
- flag/status rangkap Sales dimatikan,
- portfolio Sales sudah kosong/selesai dialihkan.

Histori target lama **tidak boleh menghalangi** proses berhenti rangkap.

---

# 21. ALIHKAN PORTOFOLIO — ATOMIC

Satu proses:

```text
dealer
+ target
+ actual attribution
+ status source
```

Jika satu bagian gagal:

```text
SEMUA ROLLBACK
```

Jangan ada kondisi dealer sudah pindah tetapi target gagal atau status source sudah berubah.

---

# 22. MENU ADMIN — JANGAN REDESIGN

Ini constraint keras.

User sudah beberapa kali menegaskan jangan mengutak-atik tampilan Admin.

Artinya:

- kategori existing jangan dibangun ulang,
- layout sidebar Admin jangan diganti,
- ukuran font existing jangan diubah global,
- card existing jangan berubah hanya karena feature baru,
- focus mode existing jangan rusak,
- form lain jangan pindah,
- jangan menghapus panel selain yang secara eksplisit diminta.

Feature baru harus masuk native ke struktur existing.

---

# 23. ADMIN — ITEM LAMA YANG HARUS DIHIDE

## 23.1 PIC Dealer per Brand

Menu/panel `PIC Dealer per Brand` yang dianggap overlap dengan Transfer Dealer baru harus di-hide dari Admin.

Jangan delete backend data/logic yang mungkin masih dibutuhkan ownership engine.

Yang diminta user adalah **hide menu/panel dari Admin**, bukan menghapus seluruh subsystem assignment.

## 23.2 Transfer Dealer lama/duplikat

Jika Admin masih memiliki panel Transfer Dealer lama yang duplikat dengan menu baru:

- hide/remove entry UI lama,
- jangan tampil dua versi.

Transfer Dealer final berada di `LAINNYA`.

---

# 24. ADMIN — SATU PUSAT APPROVAL

User tidak mau panel approval berserakan.

Harus ada satu panel/menu:

```text
APPROVAL
```

di Admin.

Ini menjadi pusat approval berbagai request.

Minimal saat ini:

- Transfer Dealer ASM/RGM,
- perubahan kontak dealer.

Ke depan bisa diperluas request type lain.

## 24.1 Approval list

Tampilkan row/card compact dengan:

- request type,
- requester,
- date/time,
- subject/dealer,
- status,
- ringkasan,
- tombol/detail.

## 24.2 Transfer Dealer detail

Tampilkan:

- dari Sales,
- ke Sales,
- dealer,
- brand,
- effective date,
- alasan,
- requester,
- timestamp,
- approval action.

## 24.3 Perubahan kontak dealer detail

Jangan dump JSON mentah.

Harus manusiawi:

```text
Dealer: ABC PHONE

Nama PIC
Sebelum: Budi
Sesudah: Andi

No. HP
Sebelum: 0812...
Sesudah: 0813...

Alasan:
...
```

Action:

- Approve
- Reject + reason

## 24.4 Approval tidak boleh duplikat

Jangan ada Approval Transfer Dealer, Approval Kontak Dealer, dll. sebagai panel besar terpisah bila bisa digabung.

Satu pusat approval.

---

# 25. ADMIN — HAPUS NOTIFIKASI KHUSUS

Panel:

```text
NOTIFIKASI KHUSUS
```

di halaman Admin harus dihapus/hide.

Alasan:

Feature notifikasi sudah ada di halaman:

```text
PERANGKAT
```

Penting:

- hanya hilangkan panel Admin,
- jangan mematikan backend push notification,
- jangan menghapus menu Perangkat,
- jangan merusak setting notifikasi existing.

---

# 26. KOREKSI TRANSAKSI — FINAL MENU

Final menu:

```text
LAINNYA
└── KOREKSI TRANSAKSI
```

Visible untuk:

- Admin,
- Super Admin.

Jangan taruh di halaman Admin.

## 26.1 Hanya satu menu

Jangan sampai ada:

```text
PENGECEKAN TRANSAKSI
KOREKSI TRANSAKSI
```

bersamaan.

Final label hanya:

```text
KOREKSI TRANSAKSI
```

## 26.2 Full label

Jangan:

```text
KOREKSI TRANSA...
```

Sidebar DSM existing sudah bisa wrap label panjang.

Jika lebar tidak cukup, dua baris boleh.

Jangan ellipsis.

## 26.3 Native sidebar structure

Jangan clone menu Dealer lalu hapus `data-page`.

Jangan tempel DOM yang tidak memiliki class/structure native.

Gunakan registration/menu tree existing DSM.

Desktop dan mobile `Lainnya` harus sama-sama mendapat entry dengan rule role yang benar.

---

# 27. KOREKSI TRANSAKSI — TUJUAN HALAMAN

Halaman ini bukan transaction browser umum.

Tujuan:

```text
mencari transaksi yang perlu diperiksa
```

Default jangan load semua transaksi normal.

Default state:

```text
PERLU DITINJAU
```

---

# 28. KOREKSI TRANSAKSI — FILTER

Minimal:

- Periode
- Region
- Search

Search dapat mencakup:

- dealer,
- kode dealer,
- invoice/dokumen,
- Sales,
- SKU bila tersedia.

Default periode: bulan berjalan.

Default status: `Perlu Ditinjau`.

---

# 29. KOREKSI TRANSAKSI — PERFORMANCE

Data ST bisa besar.

Wajib:

- server-side filtering,
- server-side pagination,
- page size yang masuk akal,
- jangan fetch semua transaksi ke browser lalu filter JS.

Endpoint ke client tetap harus paginated dan tidak mengirim semua row.

---

# 30. MARKER TRANSAKSI

Marker final:

```text
Sales ST berbeda
Dialihkan melalui transfer
Koreksi khusus
```

## 30.1 Sales ST berbeda

Sales raw dari ST berbeda dengan Sales yang seharusnya berdasarkan ownership effective.

## 30.2 Dialihkan melalui transfer

Transaksi dihitung ke owner baru karena dealer-brand sudah dialihkan melalui ownership/effective transfer.

## 30.3 Koreksi khusus

Ada override manual Admin/Super Admin khusus transaksi tersebut.

---

# 31. MARKER HARUS HISTORICAL-AWARE

Jangan compare raw ST dengan `Master_Customer` current owner saja.

Gunakan ownership berdasarkan tanggal transaksi.

Contoh:

```text
September:
Dealer ABC -> Sales A

Oktober:
Dealer ABC -> Sales B
```

Transaksi September dengan ST Sales A adalah normal.

Jangan diberi marker `Sales ST berbeda` hanya karena owner sekarang Sales B.

Resolver minimal:

```text
transaction date
+ dealer
+ brand
+ assignment history
+ effective date
```

---

# 32. KOREKSI KHUSUS SATU TRANSAKSI

Admin / Super Admin dapat memilih satu transaksi dan menetapkan:

```text
Sales Aktual / Sales Dihitung = Sales B
```

## 32.1 Scope correction

Correction hanya memengaruhi transaksi itu.

Tidak boleh:

- memindahkan dealer,
- mengubah owner dealer,
- mengubah transaksi dealer lain,
- mengubah target,
- mengubah raw ST.

## 32.2 Reason wajib

Setiap correction wajib punya alasan.

Audit:

- corrected by,
- corrected at,
- original Sales,
- corrected Sales,
- reason,
- stable transaction key.

---

# 33. RAW ST HARUS TETAP ASLI

Raw import ST adalah evidence/audit source.

Contoh raw:

```text
Invoice: INV001
Sales ST: Sales A
```

Setelah correction:

Raw tetap:

```text
Sales ST: Sales A
```

Resolved/effective:

```text
Sales Dihitung: Sales B
Attribution: CORRECTION
Reason: ...
```

Jangan rewrite Sales A di raw storage menjadi B.

---

# 34. KOREKSI HARUS TAHAN RE-UPLOAD

Use case wajib:

Hari ini:

```text
1–18 Oktober di-upload.
INV001 raw = Sales A.
Admin koreksi INV001 -> Sales B.
```

Besok:

```text
1–19 Oktober di-upload ulang.
INV001 muncul lagi raw = Sales A.
```

Hasil yang benar:

```text
INV001 tetap dihitung ke Sales B.
Admin TIDAK perlu koreksi ulang.
```

## 34.1 Stable identity

Identity correction **jangan memasukkan NIK Sales sebagai bagian pembeda utama**.

Karena Sales raw bisa berubah tetapi transaksi sebenarnya sama.

Prioritas key ideal:

```text
company/business unit
+ document/invoice
+ line number
```

Jika source memiliki stable row ID, gunakan itu.

Fallback boleh memakai kombinasi field transaksi stabil seperti tanggal, company, document, SJ, SKU, dealer, qty, amount.

Kalau ada line ID, gunakan line ID untuk menghindari collision identical duplicate lines.

---

# 35. RE-UPLOAD TIDAK BOLEH DOUBLE ACTUAL

Ketika ST replace tanggal 1 sampai hari berjalan:

- old snapshot diganti sesuai mekanisme existing,
- correction lama diterapkan lagi,
- actual tidak boleh dobel,
- transaction correction record tidak boleh menjadi duplicate baru hanya karena upload ulang.

---

# 36. ORDER OF ATTRIBUTION / RESOLUTION

Secara konsep effective attribution:

```text
RAW ST
↓
resolve ownership dealer-brand by transaction date
↓
apply transfer/effective ownership
↓
apply manual transaction correction LAST
↓
build snapshot/cubes/report
```

Manual correction memiliki precedence paling tinggi untuk transaksi spesifik.

Tetapi raw row tetap menyimpan original Sales.

---

# 37. KOREKSI HARUS KONSISTEN DI SEMUA MODUL

Jika transaksi 10 unit dikoreksi:

```text
Sales A -> Sales B
```

Hasil:

```text
Sales A: -10
Sales B: +10
Total nasional: tetap sama
```

Tidak boleh double count.

## 37.1 Modul yang wajib konsisten

Minimal:

- Beranda
- KPI
- Validasi KPI
- detail Validasi KPI
- Dealer detail
- Ranking
- Analisa
- Analisa Brand
- Analisa Dealer bila ada
- Tim / recap
- Export

Semua harus mengonsumsi effective attribution yang sama.

Jangan implement correction terpisah per page.

---

# 38. VALIDASI KPI

Ini area wajib diuji.

Kalau KPI contoh:

```text
ST
DA
NOO
Omzet
```

Correction mengubah owner transaksi:

- ST harus pindah owner,
- omzet harus pindah owner,
- DA/NOO harus dihitung ulang memakai formula existing,
- detail dealer/transaksi di Validasi KPI harus cocok dengan angka header.

Jangan sekadar mengubah Dashboard tetapi detail Validasi masih menunjukkan Sales lama.

---

# 39. EXPORT

Export yang memakai data actual harus konsisten dengan effective attribution.

Untuk audit, pertahankan lineage sehingga bisa dibedakan:

```text
Sales ST Asli
Sales Dihitung / Effective Sales
Marker / Attribution
Alasan Koreksi (bila ada)
```

Jangan menghapus raw lineage.

---

# 40. TARGET TRANSFER DEALER BIASA

**HARD RULE:**

Transfer Dealer biasa:

```text
TARGET TIDAK PINDAH
```

Actual setelah effective dapat berubah mengikuti dealer-brand.

Target baru disentuh pada Alihkan Portofolio.

---

# 41. TARGET ALIHKAN PORTOFOLIO

Alihkan Portofolio full transfer:

```text
TARGET IKUT DIBAGI
```

Rule:

- suggested split otomatis,
- editable,
- sum harus reconcile,
- recipient existing target di-merge,
- preview before/add/after,
- histori periode lalu aman.

---

# 42. HISTORI TARGET DAN ACTUAL

Jangan rewrite historical months.

Contoh source resign Oktober.

September:

```text
Target Sales A = X
Actual Sales A = Y
```

September harus tetap Sales A.

Oktober current/effective dapat mengikuti transfer sesuai tanggal/mode.

---

# 43. MOBILE ADMIN / FORM REGRESSION CHECK

Sebelum declare selesai, cek smartphone.

Wajib test:

- scroll dari atas sampai bawah,
- tidak ada section clipped,
- searchable Sales dropdown keluar utuh,
- dropdown tidak masuk belakang card,
- footer/sticky action tidak menabrak bottom nav,
- table horizontal bisa digeser bila memang tabel,
- modal tidak lebih lebar dari viewport,
- keyboard mobile tidak membuat action tidak bisa dijangkau,
- label tidak terpotong,
- button proporsional.

---

# 44. DESKTOP REGRESSION CHECK

Wajib:

- sidebar tidak berubah,
- focus mode Admin tidak membuat content aktif muncul di posisi salah,
- dropdown tidak tertutup,
- form tetap bisa scroll,
- tabel compact,
- action tidak hilang,
- horizontal overflow hanya pada table yang memang memerlukan,
- tidak ada overlay dengan z-index liar.

---

# 45. ADMIN FOCUS MODE

Versi sebelumnya pernah ada bug:

- panel dipilih,
- content aktif muncul di posisi yang membingungkan / terlalu jauh,
- form terpotong.

Baseline tanggal 17 **belum sempurna pada Focus Mode**. Bug posisi konten aktif yang membingungkan/terlalu jauh termasuk bug baseline yang wajib diperbaiki tanpa redesign Admin.

Jika menambah Approval panel, ikuti struktur visual Admin existing, tetapi perbaiki behavior Focus Mode tersebut.

Jangan membuat navigation / active content model baru.

---

# 46. SEARCHABLE COMBO

Sales selector harus:

- searchable,
- nama,
- NIK,
- role,
- region,
- keyboard usable,
- hasil tidak tertutup container,
- klik item mengisi selection,
- source tidak boleh sama dengan destination,
- out-of-scope tidak boleh muncul,
- backend tetap validate.

---

# 47A. BUG BASELINE TANGGAL 17 YANG WAJIB CLAUDE PERBAIKI

Bagian ini **BUKAN request fitur baru**. Ini adalah daftar bug yang sudah ada pada baseline `dev` tanggal 17 Oktober 2026 (`76cb12b6...`) dan sempat dicoba diperbaiki pada 18 Oktober. Claude harus memperbaikinya dari baseline 17 dengan cara native/targeted, bukan membawa kembali patch regression tanggal 18.

## 47A.1 Transfer Dealer ASM/RGM melayang di atas sidebar

**Gejala aktual baseline 17:**

`TRANSFER DEALER` dapat muncul sebagai item pucat/melayang di area paling atas sidebar, di atas `BERANDA`, bukannya menjadi item navigasi native di grup yang benar.

Contoh tampilan salah:

```text
TRANSFER DEALER   <- melayang / orphan
BERANDA
PANTAU
  DEALER
  BRAND
  TIM
...
```

**Root cause yang sudah teridentifikasi:**

- `assets/transfer_ui_hotfix.js` membuat menu ASM/RGM dengan clone elemen `DEALER`.
- atribut routing/native `data-*` dibuang dari clone,
- clone disisipkan secara manual setelah row Dealer,
- ketika DSM merender/memindahkan navigation tree native, clone tersebut tidak ikut struktur menu,
- hasilnya menjadi orphan/floating menu di posisi lama.

**Final fix yang diminta:**

- jangan gunakan clone DOM / floating fallback sebagai menu final,
- daftarkan `TRANSFER DEALER` melalui mekanisme navigation native DSM,
- lokasi final tetap `LAINNYA`, sesuai keputusan terbaru,
- role: ASM, RGM, Admin, Super Admin,
- jangan muncul di atas `BERANDA`,
- jangan duplicate,
- desktop dan mobile harus konsisten.

**Acceptance:** tidak ada elemen `TRANSFER DEALER` orphan/floating setelah initial render, rerender sidebar, ganti role/demo, atau navigation refresh.

## 47A.2 Transfer Sales / Alihkan Portofolio terpotong di Admin

**Gejala baseline 17:**

- panel/form `Transfer Sales / Alihkan Portofolio` dapat terpotong,
- bagian bawah form atau action tidak selalu terlihat,
- scroll/height panel tidak mengikuti isi,
- kombinasi `max-height` / `overflow` pada container Admin bisa memotong konten.

**Fix:**

- audit parent `details.adminFeature`, `.adminFeatureContent`, `.pb`, dan container terkait,
- jangan memberi `overflow:hidden`/`max-height` yang memotong form aktif,
- konten harus bisa berkembang/scroll secara normal,
- jangan memperbaiki dengan CSS global yang mengubah semua panel Admin.

**Acceptance:** seluruh form, action, dan preview dapat diakses dari atas sampai bawah di desktop dan mobile tanpa bagian hilang.

## 47A.3 Dropdown pencarian Sales terpotong / tertutup panel

**Gejala baseline 17:**

Searchable combo Sales pada Transfer Sales/Alihkan Portofolio dapat:

- tertutup oleh parent card,
- terpotong karena overflow,
- muncul di belakang panel lain,
- hasil pencarian tidak terlihat penuh.

**Fix:**

- gunakan dropdown/portal mechanism yang aman bila perlu,
- hitung positioning terhadap viewport dengan benar,
- `z-index` hanya dinaikkan secara scoped,
- jangan meninggalkan list portal di `body` setelah blur/close,
- keyboard dan click selection harus tetap bekerja.

**Acceptance:** pencarian berdasarkan nama/NIK/role/region tampil utuh dan dapat dipilih di desktop/mobile, termasuk saat form berada dekat bawah viewport.

## 47A.4 Admin Focus Mode: konten aktif muncul di posisi membingungkan

**Gejala baseline 17:**

Saat user memilih panel/kategori di Admin:

- content aktif bisa muncul terlalu jauh dari item yang dipilih,
- posisi scroll terasa loncat,
- user sulit memahami panel mana yang sedang aktif,
- pada kombinasi layout tertentu konten dapat terlihat seperti nyelip di tengah daftar.

**Fix:**

- pertahankan visual Admin existing,
- rapikan placement/scroll behavior Focus Mode saja,
- jangan redesign kategori/sidebar Admin,
- active content harus muncul konsisten pada lokasi yang mudah dipahami,
- action sticky/form existing tidak boleh rusak.

**Acceptance:** memilih panel selalu membawa user ke content aktif yang jelas, tanpa layout lompat aneh atau kehilangan konteks kategori.

## 47A.5 Kategori Admin versi mobile terpotong / tidak terbaca utuh

**Gejala baseline 17:**

Di layar HP, selector/list kategori Admin dapat:

- terpotong kiri/kanan,
- nama kategori tidak terbaca penuh,
- navigasi kategori terasa padat/sulit dipilih.

**Fix:**

- pertahankan nama kategori lengkap,
- jangan crop label,
- jangan ellipsis kalau membuat kategori ambigu,
- gunakan pola mobile existing/approved yang memungkinkan semua kategori diakses dengan jelas,
- tidak boleh menimbulkan horizontal overflow halaman utama yang liar.

**Acceptance:** seluruh kategori Admin dapat dibaca dan dipilih pada viewport mobile tanpa label terpotong.

## 47A.6 Batasan penting

Kelima poin di atas adalah **bug baseline tanggal 17**, bukan fitur baru dan bukan regression tanggal 18. Jangan mencampurkannya dengan bug yang justru muncul akibat patch tanggal 18 seperti duplicate `PENGECEKAN TRANSAKSI` / `KOREKSI TRANSAKSI`, label ellipsis, atau loader revision yang tidak terpasang.

---

# 47. MENU / NAVIGATION REGRESSION YANG TERJADI 18 OKTOBER — JANGAN DIULANG

## 47.1 File revisi ada tetapi tidak pernah diload

Kesalahan:

- asset baru masuk repo,
- entry page tidak memanggilnya,
- user pull tetapi aplikasi tidak berubah.

Pelajaran:

- setelah implementasi, trace full load path,
- pastikan bundle benar-benar loaded pada browser,
- jangan hanya cek file exists.

## 47.2 Menu transaksi pucat / salah struktur

Kesalahan:

- menu dibuat dengan clone elemen yang salah,
- `data-page`/structure native hilang,
- hasil tampak disabled/pucat dan tidak sejajar.

Jangan lakukan clone DOM semacam itu.

## 47.3 Duplikat transaksi

Pernah muncul:

```text
PENGECEKAN TRANSAKSI
KOREKSI TRANSAKSI
```

Final harus hanya satu:

```text
KOREKSI TRANSAKSI
```

## 47.4 Ellipsis

Pernah tampil:

```text
KOREKSI TRANSA...
```

Final full/wrap.

## 47.5 Transfer Dealer hilang pada Admin

Bug terjadi karena menu hanya ditampilkan ketika flag approval ASM/RGM true.

Final:

- ASM/RGM ada,
- Admin/Super Admin juga ada.

## 47.6 Notifikasi Khusus masih muncul

Padahal request final: hapus dari Admin.

Pastikan benar-benar hilang setelah runtime.

## 47.7 Approval tidak jelas

Approval baru jangan menjadi raw JSON dan jangan membuat layout Admin berubah.

---

# 48. ITEM YANG SUPERSEDED / JANGAN DIIKUTI

## 48.1 Transfer Dealer di PANTAU

Tanggal 17 pernah diputuskan:

```text
PANTAU
DEALER
TRANSFER DEALER
```

**SUPERSEDED.**

Final:

```text
LAINNYA
TRANSFER DEALER
```

## 48.2 Koreksi di Admin

Pernah dibahas sebagai Admin/Pengecekan.

**SUPERSEDED.**

Final:

```text
LAINNYA
KOREKSI TRANSAKSI
```

## 48.3 Nama Pengecekan Transaksi

**SUPERSEDED.**

Final label:

```text
KOREKSI TRANSAKSI
```

## 48.4 Sales tujuan per dealer pada Transfer Dealer

Mockup lama pernah punya destination per row/default override.

**SUPERSEDED oleh request user:**

```text
Pilih Sales tujuan sekali di awal
→ checklist dealer
```

Per-recipient row hanya untuk Alihkan Portofolio.

## 48.5 Banyak panel approval

**SUPERSEDED.**

Final satu pusat Approval.

## 48.6 Panel Notifikasi Khusus di Admin

Remove.

## 48.7 Transfer Target terpisah

Jangan buat.

Target hanya bagian dari Alihkan Portofolio.

---

# 49. REQUEST DETAIL DARI MOCKUP YANG SUDAH DISETUJUI

Referensi mockup yang pernah dibuat dan relevan:

```text
DSM_MOCKUP_TRANSFER_FINAL_MOBILE_APPROVAL_SALES.html
DSM_MOCKUP_TRANSFER_DEALER_DESKTOP_MOBILE_REVISI.html
DSM_MOCKUP_TRANSFER_DEALER_SCROLL_FIX.html
DSM_MOCKUP_TRANSFER_DEALER_TABLE_COMPACT.html
DSM_MOCKUP_ALIHKAN_PORTOFOLIO.html
DSM_Alihkan_Portofolio_Checklist.html
DSM_Transfer_Dealer_Per_Brand_Mockup.html
DSM_Transfer_Dealer_Mobile.html
DSM_Transfer_Dealer_Dalam_Aplikasi.html
DSM_Admin_Existing_Approval.html
DSM_Penanda_Sales_ST_Berbeda.html
DSM_Before_After_Perbaikan_UI.html
README_REVISION.md
```

Claude sebaiknya membaca file-file mockup/revision tersebut sebagai visual/reference, lalu memakai requirement FINAL dokumen ini untuk resolve konflik.

---

# 50. EXPECTED API / DOMAIN BEHAVIOR

Tidak wajib memakai nama endpoint tertentu, tetapi domain behavior harus jelas.

## 50.1 Transfer Dealer boot/list

Return:

- actor role,
- allowed source Sales,
- allowed destination Sales,
- dealer-brand assignments,
- current actual MTD summary,
- approval required true/false.

## 50.2 Preview

Preview harus server validated.

Jangan hanya build preview dari browser state.

## 50.3 Submit

ASM/RGM:

```text
create request only
```

No ownership write.

## 50.4 Execute

Admin direct/approval:

```text
validate
lock
save backup/current state
write assignments
sync primary PIC where eligible
rebuild
publish
audit
commit
```

Failure: rollback.

## 50.5 Correction save

```text
validate transaction identity
save correction
rebuild effective data
publish
audit
```

Jika publish/rebuild gagal, jangan meninggalkan reporting dalam half-state.

---

# 51. DATA MODEL CONCEPTUAL

Gunakan schema existing bila sudah ada.

Jangan membuat redundant tables tanpa audit.

Conceptual entities:

```text
Dealer Brand Assignment
Dealer Assignment History
Transfer Request
Transfer Audit
Transaction Correction
Transaction Correction Audit
Target Input / Target Period
Master Customer
Master Sales
ST Raw
Snapshot / Parts / Cube
```

Rule:

- raw ST immutable,
- assignment history effective-dated,
- correction separate,
- reporting reads effective rows.

---

# 52. AUDIT TRAIL

## Transfer Dealer

Simpan:

- before owner,
- after owner,
- dealer,
- brand,
- effective date,
- actor,
- approver,
- reason,
- request ID,
- timestamp.

## Alihkan Portofolio

Simpan:

- source,
- recipients,
- dealer allocation,
- target before,
- target addition,
- target after,
- actual allocation,
- effective mode,
- status source before/after,
- actor,
- timestamp.

## Transaction Correction

Simpan:

- stable tx key,
- original Sales,
- corrected Sales,
- reason,
- actor,
- created/update timestamp.

---

# 53. VALIDATION TRANSFER DEALER

Reject bila:

- source kosong,
- destination kosong,
- source = destination,
- tidak ada dealer,
- tidak ada brand,
- alasan tidak valid,
- actor out of scope,
- source out of scope,
- destination out of scope,
- dealer bukan owner source lagi,
- brand bukan owner source lagi,
- stale request,
- request sudah processed,
- date invalid.

Jika data berubah setelah request dibuat tetapi sebelum approval, approval harus revalidate.

---

# 54. VALIDATION ALIHKAN PORTOFOLIO

Reject/disable proceed bila:

- source belum dipilih,
- alasan kosong,
- recipient kosong,
- dealer required belum 100% allocated,
- target source belum 100% reconciled,
- recipient invalid,
- source = recipient,
- effective mode invalid,
- target result negatif/invalid,
- conflicting ownership write,
- status source tidak konsisten dengan request.

---

# 55. ACCESS MATRIX RINGKAS

| Fitur | Sales | ASM | RGM | Admin | Super Admin |
|---|---:|---:|---:|---:|---:|
| Lihat Transfer Dealer | Tidak | Ya | Ya | Ya | Ya |
| Ajukan Transfer Dealer | Tidak | Ya | Ya | — | — |
| Direct Transfer Dealer | Tidak | Tidak | Tidak | Ya | Ya |
| Approve Transfer Dealer | Tidak | Tidak | Tidak | Ya | Ya |
| Koreksi Transaksi | Tidak | Tidak | Tidak | Ya | Ya |
| Pusat Approval | Tidak | Tidak | Tidak | Ya | Ya |
| Alihkan Portofolio | Tidak | Tidak | Tidak | Ya | Ya |

Catatan: ASM/RGM authority selalu limited ke bawahan/scope.

---

# 56. MENU FINAL YANG RELEVAN

Gambaran, bukan berarti rewrite seluruh sidebar:

```text
...
LAINNYA
├── INFORMASI
├── AI ASISTANT
├── TRANSFER DEALER          [ASM/RGM/Admin/Super Admin]
├── KOREKSI TRANSAKSI       [Admin/Super Admin]
├── ADMIN                    [Admin/Super Admin]
└── PANDUAN
...
```

Ikuti urutan/struktur aktual baseline tanggal 17 bila ada sedikit perbedaan.

Yang penting:

- Transfer Dealer ada di Lainnya,
- Koreksi Transaksi ada di Lainnya,
- label full,
- role benar,
- tidak duplikat.

---

# 57. ADMIN FINAL YANG RELEVAN

Di halaman Admin:

- existing categories tetap,
- Pengguna & Akses tetap,
- Alihkan Portofolio tetap di Pengguna & Akses,
- ada satu Pusat Approval,
- PIC Dealer per Brand lama di-hide,
- Transfer Dealer panel lama/duplikat di-hide,
- Notifikasi Khusus di-hide/remove,
- Koreksi Transaksi **tidak** ditaruh di Admin.

---

# 58. ACCEPTANCE TEST — TRANSFER DEALER ASM

Scenario login ASM.

Expected:

1. `LAINNYA > TRANSFER DEALER` muncul.
2. Sales source hanya bawahan ASM.
3. Sales destination hanya bawahan ASM.
4. Pilih brand.
5. Dealer partial-match tetap muncul.
6. Pilih dealer.
7. Preview exact brand.
8. Submit.
9. Status Pending.
10. Ownership database belum berubah.
11. KPI/actual belum berpindah hanya karena submit.

---

# 59. ACCEPTANCE TEST — TRANSFER DEALER RGM

Sama dengan ASM.

Tambahkan:

- bisa melihat Sales lintas ASM hanya yang masih berada di scope RGM,
- luar region/scope RGM ditolak backend.

---

# 60. ACCEPTANCE TEST — OUT-OF-SCOPE HACK

Manipulasi request manually:

```text
source = Sales di luar scope
```

Expected:

```text
validation error
NO mutation
```

Lakukan juga untuk destination.

---

# 61. ACCEPTANCE TEST — APPROVAL

1. ASM buat request 2 dealer, 3 brand.
2. Login Admin.
3. Request muncul di pusat Approval.
4. Buka detail.
5. Exact dealer-brand terlihat.
6. Approve.
7. Hanya 3 assignment brand berubah.
8. Brand lain tetap owner lama.
9. Primary owner hanya berubah bila aturan consolidation terpenuhi.
10. Status request Approved.

---

# 62. ACCEPTANCE TEST — REJECT

1. ASM submit.
2. Admin reject + note.
3. Ownership tetap.
4. Status Rejected.
5. Audit reviewer/note ada.

---

# 63. ACCEPTANCE TEST — ATOMIC FAILURE

Simulasikan kegagalan di tengah process/publish.

Expected:

- semua assignment kembali,
- primary owner kembali,
- snapshot/pointer tidak half-updated,
- status Failed,
- request tidak bisa dianggap Approved.

---

# 64. ACCEPTANCE TEST — FULL CONSOLIDATION

Sebelum:

```text
Dealer D
A -> Sales 1
B -> Sales 1
C -> Sales 1
```

Transfer A+B+C ke Sales 2.

Expected:

```text
primary PIC -> Sales 2
future unassigned brand -> Sales 2
```

---

# 65. ACCEPTANCE TEST — SPLIT OWNERSHIP

Sebelum:

```text
A -> Sales 1
B -> Sales 1
```

Transfer A saja ke Sales 2.

Expected:

```text
A -> Sales 2
B -> Sales 1
```

Tidak boleh semua ikut.

---

# 66. ACCEPTANCE TEST — TARGET TRANSFER DEALER

Transfer Dealer biasa.

Expected:

```text
Target Sales 1 sebelum = sama sesudah
Target Sales 2 sebelum = sama sesudah
```

Actual setelah effective dapat berubah mengikuti dealer-brand.

---

# 67. ACCEPTANCE TEST — ALIHKAN PORTOFOLIO

Source resign dengan:

```text
42 dealer
sebagian actual 0
beberapa brand tanpa transaksi bulan ini
2 recipient
```

Expected:

- semua assignment source masuk daftar,
- tidak hanya dealer dengan actual,
- semua assignment wajib teralokasi,
- filter Belum Dialokasikan bekerja,
- summary recipient benar.

---

# 68. ACCEPTANCE TEST — TARGET MERGE

Recipient Andi sudah punya:

```text
SO = 80
```

Source dialokasikan:

```text
+20
```

Expected:

```text
Before 80
Additional 20
After 100
```

Bukan overwrite 20.

---

# 69. ACCEPTANCE TEST — ACTUAL PORTFOLIO

Source actual:

```text
Dealer A = 30
Dealer B = 20
```

A -> Recipient 1  
B -> Recipient 2

Expected:

```text
Recipient 1 +30
Recipient 2 +20
```

Histori previous period source tetap.

---

# 70. ACCEPTANCE TEST — TRANSACTION CORRECTION

Raw:

```text
INV001
Dealer X
Sales ST = A
```

Correction:

```text
Sales dihitung = B
```

Expected:

- raw tetap A,
- effective B,
- reason/audit tersimpan,
- Dashboard A turun,
- Dashboard B naik,
- KPI A turun,
- KPI B naik,
- Validasi KPI detail cocok,
- Ranking cocok,
- Tim recap cocok,
- Export cocok,
- total company tidak berubah.

---

# 71. ACCEPTANCE TEST — RE-UPLOAD ST

Hari 1:

- upload 1–18,
- correct INV001 A -> B.

Hari 2:

- upload 1–19,
- INV001 muncul lagi.

Expected:

- correction otomatis diterapkan,
- tidak perlu correction ulang,
- tidak double count,
- audit correction tetap satu logical correction.

---

# 72. ACCEPTANCE TEST — HISTORICAL OWNERSHIP

September:

```text
Dealer X -> Sales A
```

Oktober:

```text
Dealer X -> Sales B
```

Expected:

Transaksi September Sales A **tidak** masuk Perlu Ditinjau hanya karena current owner B.

---

# 73. ACCEPTANCE TEST — NAVIGATION

Desktop:

- Transfer Dealer terlihat role yang benar.
- Koreksi Transaksi hanya Admin/Super Admin.
- tidak ada `PENGECEKAN TRANSAKSI` duplikat.
- label tidak ellipsis.
- Admin normal.

Mobile:

- entry juga muncul di halaman/menu Lainnya,
- tidak overflow,
- tidak duplikat,
- bottom nav tidak rusak.

---

# 74. ACCEPTANCE TEST — ADMIN

Login Admin.

Expected:

- struktur Admin tetap familiar seperti baseline,
- PIC Dealer per Brand lama tidak tampil,
- Transfer Dealer lama/duplikat tidak tampil,
- Pusat Approval ada,
- Notifikasi Khusus tidak tampil,
- Koreksi Transaksi tidak ditempel di Admin,
- Alihkan Portofolio tetap ada di Pengguna & Akses.

---

# 75. RUNTIME QA WAJIB SEBELUM PUSH FINAL

Jangan cukup dengan syntax check.

Wajib runtime lokal:

- login setiap role,
- sidebar desktop,
- sidebar/mobile Lainnya,
- modal,
- search,
- pagination,
- transfer request,
- approval,
- reject,
- correction,
- reupload,
- KPI check,
- Validasi KPI check,
- export check,
- Alihkan Portofolio,
- rollback scenario.

---

# 76. TEST MATRIX ROLE

Minimal akun/test fixture:

```text
Sales
ASM
RGM
Admin
Super Admin
```

Untuk ASM/RGM sediakan:

- Sales in-scope,
- Sales out-of-scope.

Untuk dealer:

- dealer single brand,
- dealer multi brand,
- dealer split owner,
- dealer zero actual,
- dealer dengan actual,
- dealer future/new brand case.

---

# 77. TEST MATRIX DATA

Siapkan:

- transaksi normal,
- ST Sales mismatch,
- transferred ownership,
- correction,
- previous month transaction,
- same invoice re-upload,
- duplicate-looking line dengan line ID,
- dealer no current sale tetapi ownership aktif.

---

# 78. NO-RUNTIME-CLAIM POLICY

Claude jangan mengatakan “100% selesai” hanya karena code review.

Gunakan status jujur:

```text
Static implementation complete
Syntax checked
Runtime tested: YES/NO
Scenario tested: ...
```

---

# 79. WORKFLOW YANG DIMINTA USER

1. Mulai dari versi tanggal 17.
2. Audit aplikasi existing.
3. Cocokkan requirement dokumen ini ke code existing.
4. Jangan redesign.
5. Buat perubahan minimal.
6. Jalankan static checks.
7. Jalankan runtime lokal.
8. Tunjukkan hasil ke user.
9. Perbaiki bila ada mismatch.
10. Baru push saat user menyuruh.
11. `main` tetap jangan disentuh.

---

# 80. PRIORITAS IMPLEMENTASI

## P1 — jangan rusak baseline

- audit menu architecture,
- audit role authorization,
- audit ownership resolver,
- audit publish/snapshot path.

## P2 — Transfer Dealer backend

- dealer-brand exact transfer,
- scope,
- request,
- atomic approval,
- effective history,
- primary PIC.

## P3 — Transfer Dealer UI native

- Lainnya,
- desktop,
- mobile,
- preview.

## P4 — Unified Approval

- Transfer Dealer,
- contact change,
- hide duplicate old panels.

## P5 — Transaction correction backend

- stable key,
- audit,
- effective attribution,
- rebuild.

## P6 — Koreksi Transaksi UI

- native Lainnya,
- server filter/pagination,
- correction action.

## P7 — Reporting consistency

- Dashboard,
- KPI,
- Validasi KPI,
- Ranking,
- Analytics,
- Tim,
- Export.

## P8 — Alihkan Portofolio

- allocation,
- target merge,
- actual,
- status,
- rollback.

## P9 — full regression QA

---

# 81. CODE REVIEW CHECKLIST

- [ ] tidak ada direct DOM clone menu liar
- [ ] tidak ada menu duplicate
- [ ] tidak ada CSS global yang mengubah seluruh Admin
- [ ] no ellipsis pada Koreksi Transaksi
- [ ] Transfer Dealer Admin tidak hidden
- [ ] ASM/RGM submit no mutation
- [ ] backend scope check
- [ ] exact brand assignment
- [ ] primary PIC rule
- [ ] target untouched in Transfer Dealer
- [ ] correction raw ST immutable
- [ ] correction stable key
- [ ] correction reupload-safe
- [ ] all reports effective attribution
- [ ] Alihkan target merge
- [ ] previous month unchanged
- [ ] atomic rollback
- [ ] Admin Notifikasi Khusus gone
- [ ] PIC Dealer per Brand old UI hidden
- [ ] one Approval center
- [ ] contact approval human-readable
- [ ] mobile tested
- [ ] desktop tested

---

# 82. DO NOT DO

Jangan:

- rewrite seluruh `index.php` hanya untuk inject feature,
- mengganti Admin UI global,
- clone sidebar item tanpa mengikuti architecture,
- bergantung pada JS-only authorization,
- memindahkan target saat Transfer Dealer biasa,
- mengubah raw ST saat correction,
- menghitung old-month ownership pakai current owner,
- membuat separate calculation per module,
- membuat correction hilang setelah upload ulang,
- membuat multi-recipient pada Transfer Dealer biasa,
- menyembunyikan Transfer Dealer dari Admin,
- membuat `Pengecekan Transaksi` kedua,
- memotong label pakai ellipsis,
- menghapus notification engine ketika hanya panel Admin yang perlu hilang,
- menghapus ownership engine lama hanya karena panel UI PIC Dealer per Brand di-hide,
- push `main`,
- claim runtime success tanpa test.

---

# 83. DEFINISI “SELESAI”

Revisi dianggap selesai hanya jika:

1. semua requirement FINAL terpenuhi,
2. item superseded tidak muncul,
3. Admin baseline tidak rusak,
4. desktop aman,
5. mobile aman,
6. role aman,
7. backend authorization aman,
8. transfer atomic,
9. correction re-upload safe,
10. reporting konsisten,
11. historical period aman,
12. Alihkan Portofolio reconcile target,
13. test runtime sudah dilakukan,
14. user sudah melihat dan menyatakan cocok.

---

# 84. CATATAN KHUSUS UNTUK CLAUDE

Sebelum menulis code, audit dan laporkan singkat:

```text
1. File/menu architecture yang akan disentuh
2. Existing backend function yang bisa dipakai ulang
3. Existing ownership/history model
4. Existing publish/snapshot path
5. Risiko regression
6. File yang direncanakan berubah
```

Jangan langsung membuat layer revision/hotfix baru sebelum memahami existing implementation.

Target utama:

```text
fitur benar-benar terintegrasi ke DSM existing
+ authorization benar
+ data benar
+ histori aman
+ UI native
+ tidak merusak fungsi yang sudah ada
```

---

# 85. FINAL SOURCE OF TRUTH SUMMARY

Jika Claude hanya mengingat 15 poin, ingat ini:

1. Baseline = `dev` terakhir 17 Okt `76cb12b6...`.
2. Jangan sentuh `main`.
3. Existing DSM = visual source of truth.
4. Transfer Dealer = `LAINNYA`.
5. Transfer Dealer = ASM/RGM submit approval; Admin/Super Admin direct.
6. Transfer Dealer = exact dealer × brand.
7. Destination Transfer Dealer dipilih sekali, bukan per dealer.
8. Full brand consolidation mengubah primary PIC/future fallback.
9. Transfer Dealer biasa tidak memindahkan target.
10. Alihkan Portofolio = Admin → Pengguna & Akses, multi-recipient, target+actual+status, atomic.
11. Satu Pusat Approval di Admin.
12. Hide PIC Dealer per Brand lama + Notifikasi Khusus dari Admin.
13. `LAINNYA > KOREKSI TRANSAKSI` hanya satu menu, full label.
14. Correction raw ST immutable, survives re-upload, konsisten ke seluruh KPI/report/export.
15. Jangan declare selesai tanpa runtime test.

---

# 86. REFERENSI FILE/MOCKUP

File yang bisa dipakai Claude sebagai referensi tambahan bila tersedia:

```text
DSM_MOCKUP_TRANSFER_FINAL_MOBILE_APPROVAL_SALES.html
DSM_MOCKUP_TRANSFER_DEALER_DESKTOP_MOBILE_REVISI.html
DSM_MOCKUP_TRANSFER_DEALER_SCROLL_FIX.html
DSM_MOCKUP_TRANSFER_DEALER_TABLE_COMPACT.html
DSM_MOCKUP_ALIHKAN_PORTOFOLIO.html
DSM_Alihkan_Portofolio_Checklist.html
DSM_Transfer_Dealer_Per_Brand_Mockup.html
DSM_Transfer_Dealer_Mobile.html
DSM_Transfer_Dealer_Dalam_Aplikasi.html
DSM_Admin_Existing_Approval.html
DSM_Penanda_Sales_ST_Berbeda.html
DSM_Before_After_Perbaikan_UI.html
README_REVISION.md
```

**Jika mockup lama bertentangan dengan dokumen ini, dokumen ini menang.**

---

# 87. CHANGE LOG KEPUTUSAN USER 17 → 18 OKTOBER

## 17 Oktober

Keputusan penting yang muncul:

- Transfer Dealer dan Transfer Sales harus dipisah.
- Transfer Dealer untuk dealer tertentu antar Sales aktif.
- Transfer Sales/Alihkan Portofolio untuk resign/mutasi/berhenti rangkap.
- ASM boleh mengajukan Transfer Dealer.
- RGM kemudian juga dipastikan boleh mengajukan.
- Ownership baru berubah hanya setelah Admin approve.
- Mobile harus benar-benar mobile.
- Sales tujuan Transfer Dealer dipilih sekali di awal.
- Setelah itu checklist dealer.
- Form harus scrollable dan tidak clipped.
- Search Sales harus mendukung nama/NIK/role/region.
- Transfer Sales harus ada di Pengguna & Akses.
- Target menjadi bagian flow Transfer Sales, bukan menu transfer target tersendiri.
- Multi recipient untuk Alihkan Portofolio.
- Existing target recipient harus di-merge.
- Histori/raw transaction harus aman.
- Awalnya lokasi Transfer Dealer pernah ditetapkan di `PANTAU` setelah `DEALER`.

## 18 Oktober — perubahan/finalisasi

- Transfer Dealer akhirnya dipindahkan ke `LAINNYA`.
- Transfer Dealer dipertegas harus dealer × brand.
- Partial brand match tetap tampil.
- Full convergence mengubah primary PIC.
- Future brand mengikuti primary PIC baru.
- Admin/Super Admin direct transfer.
- Approval wajib atomic.
- Alihkan Portofolio harus mencakup assignment tanpa actual.
- Filter `Belum Dialokasikan`.
- Suggested target split editable.
- Preview target `Sebelum → Tambahan → Sesudah`.
- Transfer Dealer biasa target tidak pindah.
- Transaction mismatch/history mulai diformalisasi.
- Koreksi khusus satu transaksi.
- Correction harus survive upload ulang ST.
- Corrected attribution wajib konsisten seluruh DSM.
- Koreksi dipindahkan keluar Admin ke `LAINNYA`.
- Final nama menu `KOREKSI TRANSAKSI`.
- Harus hanya satu entry.
- Label tidak boleh ellipsis.
- Admin harus punya satu Approval center.
- Perubahan kontak dealer masuk Approval center.
- PIC Dealer per Brand lama di-hide.
- Notifikasi Khusus Admin dihapus karena sudah ada di Perangkat.
- Tampilan Admin existing tidak boleh dirombak.
- Patch ChatGPT 18 menimbulkan regression; karena itu baseline dikembalikan ke versi 17.

---

# 88. NOTE UNTUK HANDOFF ANTAR AI

User akan meneruskan dokumen ini ke Claude.

Jangan meminta user mengulang requirement yang sudah ada di sini.

Jika ada hal ambigu:

1. cek source existing,
2. cek mockup/reference file,
3. prioritaskan latest decision di dokumen ini,
4. bila masih ambiguity yang berdampak pada data/behavior, baru tanya user.

Jangan membuat asumsi besar tanpa approval.

---

**END OF MASTER HANDOFF**
