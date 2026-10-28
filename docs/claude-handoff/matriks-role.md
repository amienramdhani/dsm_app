# Matriks Role DSM — branch `dev`

**Repo:** `aryhidayattt/dsm`  
**Branch:** `dev`  
**Commit saat audit:** `76cb12b6b5e334ccf0e6ace68fb29df4a4538bb2`  
**Role yang diminta:** Admin / ASM / RGM / Sales  
**Kode tidak diubah.**

## Legenda

- **BOLEH** = ada jalur render/API di kode yang mendukung role tersebut atau role tersebut disebut eksplisit sebagai diizinkan.
- **TIDAK** = kode secara eksplisit menolak/menyembunyikan role tersebut.
- **DUGAAN: BOLEH** = halaman tampak generik dan scope dikelola backend, tetapi pemeriksaan visibilitas menu per-role tidak ditemukan eksplisit.
- **TIDAK DITEMUKAN** = tidak ada bukti cukup untuk memutuskan.
- Matriks ini memetakan **hak menurut kode yang berhasil dibaca**, bukan requirement baru.

## Matriks menu × role

| Menu | Admin | ASM | RGM | Sales | Bukti / catatan dari kode |
|---|---|---|---|---|---|
| **Beranda** | DUGAAN: BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | `PAGE` default `beranda`; `render()` memanggil `v6ExecView()`. `apiDashboard` memakai `guard_` + scope, tetapi visibilitas tombol per empat role tidak ditemukan eksplisit pada potongan yang dibaca |
| **Dealer** | DUGAAN: BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | `render()` memanggil `customers()` untuk `PAGE==='customer'`; backend detail/transaksi memakai scope user |
| **Brand** | BOLEH | BOLEH | BOLEH | BOLEH | `render()` memanggil `brands()`. Kode `brandAnalysisVisible()` secara eksplisit hanya menyembunyikan **analisa brand tambahan** untuk Sales, bukan menu Brand utama |
| **Tim** | DUGAAN: BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | BOLEH | `apiTeam` mempunyai cabang eksplisit untuk role Sales; higher-role scope juga ada. Visibilitas nav Admin/ASM/RGM tidak ditemukan sebagai check terpisah |
| **DOS** | BOLEH | BOLEH | BOLEH | BOLEH | `dosView()` punya path eksplisit `BOOT.user.role!=='SALES'` untuk panel wilayah dan path lain untuk Sales; artinya halaman menangani Sales maupun non-Sales |
| **AI Assistant** | DUGAAN: BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | `render()` memanggil `aiView()` tanpa role check pada dispatch halaman. Detail permission endpoint `apiAIChat` belum dipetakan sampai tuntas |
| **Analisa** | DUGAAN: BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | `render()` memanggil `v6AnalisaView()` tanpa role gate pada dispatcher; beberapa sub-analisa punya pembatasan tersendiri |
| **KPI** | BOLEH | BOLEH | BOLEH | BOLEH | `kpis()` secara eksplisit mengenali `ADMIN`, `ASM`, `RGM`, `SALES` dalam logic role |
| **Peringkat** | BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | `ranks()` tidak memblok empat role pada dispatcher; Admin punya tambahan search KPI. Rule backend lengkap per role belum diaudit satu-per-satu |
| **Transaksi** | BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | `transactions()` tersedia umum; Admin mendapat tombol tambahan `Download transaksi RAW`. `apiTransactions` memakai `guard_`/scope |
| **Informasi** | DUGAAN: BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | `render()` memanggil `information()` tanpa role gate pada dispatcher. Hak edit/upload berbeda dan belum dipetakan di tabel ini |
| **Transfer Dealer** | **BOLEH** | **BOLEH** | **BOLEH** | **TIDAK** | `dtdCtx_()` eksplisit hanya menerima `ASM`, `RGM`, `ADMIN`, `SUPER ADMIN`. `dtdDirect_()` = Admin/Super Admin; `dtdApproval_()` = ASM/RGM. Sales gagal dengan pesan “Transfer Dealer hanya tersedia…” |
| **Admin** | **BOLEH** | TIDAK DITEMUKAN | TIDAK DITEMUKAN | TIDAK DITEMUKAN | Kode Admin jelas mempunyai path Admin dan Super Admin, termasuk `a.superuser`; namun visibility/guard top-level untuk ASM/RGM/Sales belum ditemukan secara eksplisit pada audit ini, jadi tidak diisi `TIDAK` tanpa bukti |
| **Panduan** | DUGAAN: BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | `render()` memanggil `window.panduanView(view)` tanpa role gate pada dispatcher |
| **Perangkat Saya** | DUGAAN: BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | DUGAAN: BOLEH | `perangkatView()` memanggil `apiMyDevices` untuk user login; tidak ditemukan check role spesifik di dispatcher |
| **FOLLOW UP / Telemarketing** | TIDAK DITEMUKAN | TIDAK DITEMUKAN | TIDAK DITEMUKAN | TIDAK DITEMUKAN | Page `tmfu` ada, tetapi role matrix spesifik empat role ini tidak dipetakan tanpa membaca penuh `tmRenderPage()` + `lib/api_telemarketing.php` |
| **FORM ORDER** | TIDAK DITEMUKAN | TIDAK DITEMUKAN | TIDAK DITEMUKAN | TIDAK DITEMUKAN | Page `tmorder` ada; permission per empat role belum ditemukan langsung |
| **AKTIVITAS TELEMARKETING** | TIDAK DITEMUKAN | TIDAK DITEMUKAN | TIDAK DITEMUKAN | TIDAK DITEMUKAN | Page `tmactivity` ada; permission per empat role belum ditemukan langsung |

## Hak khusus yang terbukti langsung

### Transfer Dealer

Kode backend `lib/api_dealer_transfer_direct.php`:

```text
Allowed:
- ASM
- RGM
- ADMIN
- SUPER ADMIN

Direct execute:
- ADMIN
- SUPER ADMIN

Approval/request mode:
- ASM
- RGM
```

Sales secara eksplisit tidak termasuk.

### KPI

Di `index.php`, fungsi `kpis()` mempunyai check:

```text
ADMIN
SUPER ADMIN
ASM
RGM
SALES
```

dan perlakuan tambahan untuk top-tier roles.

### Brand

`brandAnalysisVisible()` mengembalikan false untuk Sales:

```text
return BOOT?.user?.role !== 'SALES'
```

Ini **bukan berarti menu Brand dilarang untuk Sales**. Yang dibatasi adalah subbagian analisa brand tertentu.

### DOS

Kode memiliki kondisi eksplisit yang membedakan:

```text
BOOT.user.role !== 'SALES'
```

untuk panel “DOS per wilayah”, sedangkan tampilan DOS utama tetap mempunyai path untuk Sales.

### Transaksi

Admin/Super Admin mendapatkan aksi ekstra:

```text
Download transaksi RAW
```

melalui check:

```text
['ADMIN','SUPER ADMIN'].includes(BOOT.user.role)
```

Ini tidak membuktikan role lain tidak boleh membuka menu Transaksi; hanya membuktikan export RAW tersebut khusus Admin/Super Admin.

## Hal yang sengaja tidak disimpulkan

Audit ini **tidak mengubah `DUGAAN` menjadi fakta** hanya berdasarkan struktur organisasi yang diharapkan.

Yang masih perlu pembacaan lanjutan jika Claude membutuhkan matriks 100% exhaustive:

1. fungsi yang membentuk tombol navigasi awal sebelum `v7Nav()` memindahkan item ke group;
2. `tmRenderPage()` dan seluruh role gate Telemarketing;
3. guard setiap endpoint `apiAI*`, `apiInfo*`, `apiDOS`, `apiAnalisa*`;
4. guard `apiAdmin()` secara penuh;
5. hak action-level di dalam menu (lihat vs edit vs export vs approve).

Sampai bukti itu dibaca, sel yang belum pasti tetap ditulis **DUGAAN** atau **TIDAK DITEMUKAN**, sesuai instruksi.
