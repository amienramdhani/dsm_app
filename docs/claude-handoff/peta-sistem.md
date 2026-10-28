# Peta Sistem DSM — branch `dev`

**Repo:** `aryhidayattt/dsm`  
**Branch yang dibaca:** `dev`  
**Commit saat audit:** `76cb12b6b5e334ccf0e6ace68fb29df4a4538bb2`  
**Sifat dokumen:** hasil pembacaan kode saja; tidak ada perubahan kode/repo.

## Aturan pembacaan

- **Apa adanya** = ditemukan langsung di kode.
- **DUGAAN** = hubungan masuk akal tetapi tidak terbukti langsung dari potongan kode yang dibaca.
- **TIDAK DITEMUKAN** = tidak ditemukan bukti langsung pada kode yang diaudit.
- DSM tidak seluruhnya membaca tabel SQL secara langsung. `lib/data.php` menyatakan:
  - setiap sheet lama dimirror ke tabel MySQL dinamis `sh_<nama>` dan registry-nya disimpan di `_sheets`;
  - Script Properties disimpan di `_props`;
  - snapshot/data per-region disimpan sebagai JSON di `storage/files`.
- Karena itu, bila endpoint membaca `snapshot_()` / `dataRows_()` dan tidak ada query SQL langsung, kolom DB di bawah ditulis sebagai **snapshot/file layer; tabel SQL langsung TIDAK DITEMUKAN**. Ini sengaja supaya tidak mengarang.

## Peta menu utama

| Menu | File yang membangun UI | Endpoint/API yang terbukti terkait | Tabel DB / storage yang terbukti terkait |
|---|---|---|---|
| **Beranda** (`beranda`) | `index.php` — `render()` memanggil `v6ExecView()` untuk `PAGE==='beranda'` | `apiBootstrap`, `apiDashboard` di `lib/api_main.php`; request masuk lewat `api.php` | `snapshot_()` / JSON `storage/files`; tabel SQL langsung untuk halaman ini **TIDAK DITEMUKAN** |
| **Dealer** (`customer`) | `index.php` — `customers()`, `dealerHistoryPanel()`, detail dealer | `apiDashboard`; `apiDealerDetail`, `apiDealerHistory`, `apiDealerStock` | Snapshot/file layer. Tabel SQL langsung **TIDAK DITEMUKAN** |
| **Brand** (`brand`) | `index.php` — `brands()`; backend tambahan `lib/api_brand.php` | `apiDashboard`; `apiBrandAdmin`, `apiBrandSave`, `apiBrandSetActive`, `apiBrandClassificationSave`, `apiBrandThresholdSave`; endpoint export/analisa brand ada di `api.php` | Sheet-mirror dinamis via `_sheets`/`sh_<nama>` untuk konfigurasi yang memakai layer sheet; nama tabel fisik spesifik menu **TIDAK DITEMUKAN** |
| **Tim** (`tim`) | `index.php` — `team()`; backend `lib/api_menu.php` | `apiTeam`; sebagian perbandingan memakai endpoint analisa/weekly | Snapshot/file layer; tabel SQL langsung **TIDAK DITEMUKAN** |
| **DOS** (`dos`) | `index.php` — `dosView()`; logic stok juga ada di `lib/stock.php` | `apiDOS`; endpoint export DOS terdaftar di `api.php` | Snapshot/file layer; tabel SQL langsung **TIDAK DITEMUKAN** |
| **AI Assistant** (`ai`) | `index.php` — `aiView()`, `aiSubmit()`; backend `lib/api_ai.php` | `apiAIOptions`, `apiAIChat` | Tabel SQL langsung **TIDAK DITEMUKAN** |
| **Analisa** (`analisa`) | `index.php` — `v6AnalisaView()`; backend `lib/api_analisa.php`, `lib/api_weekly.php` | `apiAnalisa`, `apiEarlyWarning`, `apiPotensi`, `apiWeeklyAnalysis`, `apiTeamComparison` | Snapshot/file layer; tabel SQL langsung **TIDAK DITEMUKAN** |
| **KPI** (`kpi`) | `index.php` — `kpis()`; `assets/kpikhusus.js`, `assets/kpikhusus_period.js`; backend `lib/api_kpi.php`, `lib/api_kpikhusus.php` | `apiKpiTree`, `apiPersonKpi`, `apiTeamKpi` dan API KPI Khusus yang didaftarkan di `api.php` | Banyak konfigurasi memakai sheet-mirror dinamis (`_sheets` → `sh_<nama>`). Nama tabel fisik spesifik KPI **TIDAK DITEMUKAN** dari kode yang dibaca |
| **Peringkat** (`peringkat`) | `index.php` — `ranks()`; backend utama `lib/api_main.php` | `apiRank`, `apiMyRank`, `apiRankDetail` | Snapshot/file layer; tabel SQL langsung **TIDAK DITEMUKAN** |
| **Transaksi** (`transaksi`) | `index.php` — `transactions()`, `transactionGroups()`; backend `lib/api_menu.php` | `apiTransactions`, `apiTransactionGroups`, `apiTransactionExportRaw` | Runtime `apiTransactions` membaca raw ref dari snapshot lalu `dataRows_()`. `lib/schema.php` membuat `tx_st`, tetapi komentarnya menyebut `tx_st` adalah copy untuk Navicat dan sumber utama tetap file per-region. Jadi **jangan anggap `tx_st` sumber langsung `apiTransactions`** |
| **Informasi** (`informasi`) | `index.php` — `information()`; backend `lib/api_info.php` | `apiInfo`, `apiUploadInfo`, `apiSetInfoActive`, `apiDownloadInfo`, `apiAdminBanner` | Tabel fisik langsung **TIDAK DITEMUKAN**; bila memakai sheet layer, mapping aktual ada di `_sheets` |
| **Transfer Dealer** | `assets/dealer_transfer_direct.js`; `assets/transfer_ui_hotfix.js`; backend `lib/api_dealer_transfer_direct.php` | `apiDealerTransferDirectBoot`, `apiDealerTransferDirectList`, `apiDealerTransferDirectStage` | **`_dealer_transfer_requests`** dibuat langsung oleh `dtdReqSchema_()`. Stage sementara juga memakai `_props` melalui `prop_set_('TRANSFER_STAGE_...')`. Data dealer/people/transaction dibaca dari snapshot/file layer |
| **Admin** (`admin`) | `index.php` — `admin()` dan panel-panel Admin; `assets/admin_revision.js`, `assets/admin_revision.css`, `assets/admin_portfolio_guard.js`; banyak backend `lib/api_*` | `apiAdmin`, `apiSaveUser`, `apiResetPin`, `apiSetActive`, `apiPeriodLock`, import/export/admin APIs, Region Map, Hari Libur, backup, health, dll. `api.php` adalah dispatcher tunggal | Arsitektur umum: `_props`, `_sheets`, `_sessions`, `_devices`; sheet seperti `Region_Map`, `Hari_Libur`, `Audit_Log` dipetakan ke tabel fisik dinamis melalui `_sheets`. Tabel spesifik tiap panel harus ditelusuri per endpoint |
| **Panduan** (`panduan`) | `index.php` memanggil `window.panduanView(view)` | **TIDAK DITEMUKAN** endpoint API wajib untuk render Panduan pada kode yang dibaca | **TIDAK DITEMUKAN** |
| **Perangkat Saya** (`perangkat`) | `index.php` — `perangkatView()`, `renderMyDevices()`; notifikasi ditambahkan ke view ini | `apiMyDevices`; `apiPushSave`, `apiPushTest`; endpoint account/device lain ada di `lib/api_acct.php` / `lib/api_push.php` | `_devices` dibuat di schema. Tabel lain yang dipakai push subscription **TIDAK DITEMUKAN** dari potongan yang dibaca |
| **FOLLOW UP** (`tmfu`) | `index.php` mengenali page; UI TM berasal dari asset di `assets/telemarketing/` | API Telemarketing didaftarkan dari `lib/api_telemarketing.php` | Tabel fisik spesifik **TIDAK DITEMUKAN** dari audit ini |
| **FORM ORDER** (`tmorder`) | `index.php` mengenali page; UI TM berasal dari asset di `assets/telemarketing/` | API Telemarketing / order berada di `lib/api_telemarketing.php` | Tabel fisik spesifik **TIDAK DITEMUKAN** |
| **AKTIVITAS TELEMARKETING** (`tmactivity`) | `index.php` mengenali page; UI TM berasal dari asset di `assets/telemarketing/` | API Telemarketing berada di `lib/api_telemarketing.php` | Tabel fisik spesifik **TIDAK DITEMUKAN** |

## Struktur navigasi yang ditemukan langsung

Di `index.php`, daftar judul page yang ditemukan:

```text
beranda      -> Beranda
analisa      -> Analisa
transaksi    -> Transaksi
customer     -> Dealer
brand        -> Brand
tim          -> Tim
kpi          -> KPI
peringkat    -> Peringkat
dos          -> DOS
ai           -> AI Assistant
informasi    -> Informasi
panduan      -> Panduan
perangkat    -> Perangkat Saya
admin        -> Admin
tmfu         -> FOLLOW UP
tmorder      -> FORM ORDER
tmactivity   -> AKTIVITAS TELEMARKETING
```

Grouping desktop yang ditemukan langsung pada `const GROUPS`:

```text
PANTAU
- customer
- brand
- tim
- dos
- tmfu
- tmorder
- tmactivity

ANALISA
- ai
- analisa
- kpi
- peringkat
- transaksi

LAINNYA
- informasi
- ai
- admin
```

Catatan penting: pada baseline commit ini **Transfer Dealer tidak tercantum di `GROUPS` tersebut**. Menu ASM/RGM ditambahkan lewat `assets/transfer_ui_hotfix.js` / `assets/dealer_transfer_direct.js`, sehingga bukan bagian native dari array group di `index.php`.

## Arsitektur API yang ditemukan

`api.php` adalah pintu tunggal RPC. Browser memanggil fungsi dengan format konseptual:

```text
POST api.php
{"fn":"namaFungsi","args":[...]}
```

`api.php` memuat modul backend seperti:

```text
lib/api_main.php
lib/api_analisa.php
lib/api_weekly.php
lib/api_exec.php
lib/api_import.php
lib/api_dealer_transfer_direct.php
lib/api_kpi.php
lib/api_kpikhusus.php
lib/api_portfolio.php
lib/api_admin2.php
lib/api_acct.php
lib/api_health.php
lib/api_backup.php
lib/api_contact.php
lib/api_info.php
lib/api_menu.php
lib/api_export2.php
lib/api_brand.php
lib/api_telemarketing.php
lib/api_push.php
lib/api_notif.php
lib/api_ai.php
```

## Arsitektur data yang ditemukan

Dari `lib/data.php`:

```text
Google Sheet lama
    ↓
_sheets (registry)
    ↓
sh_<nama> (physical table dinamis)

Script Properties
    ↓
_props

Snapshot / raw per-region
    ↓
storage/files/*.json
```

Dari `lib/schema.php`, tabel yang dibuat eksplisit dan ditemukan:

```text
_props
_sheets
_sessions
_devices
tx_st
tx_so
tx_part
```

Dari `lib/api_dealer_transfer_direct.php`:

```text
_dealer_transfer_requests
```

### Catatan `tx_st`

Kode schema menyatakan `tx_st` adalah copy transaksi untuk kebutuhan SQL/Navicat, sedangkan sumber utama transaksi tetap file per-region. `apiTransactions()` yang dibaca memakai snapshot/raw ref + `dataRows_()`, bukan query langsung ke `tx_st`.

## Area yang belum dapat dipetakan tanpa audit lanjutan

Hal berikut sengaja **tidak ditebak**:

- nama physical table `sh_*` aktual untuk setiap logical sheet pada database runtime;
- seluruh tabel yang mungkin dibuat dinamis oleh modul Telemarketing;
- seluruh tabel push/notifikasi selain `_devices`;
- hubungan satu-per-satu setiap subpanel Admin ke tabel fisik;
- endpoint Panduan bila implementasinya sepenuhnya client-side.

Jika dibutuhkan, audit lanjutan harus membaca tiap modul backend dan mencari semua `table_()`, `readGrid_()`, `putTable_()`, `db_()->query/prepare/exec`, serta DDL `CREATE TABLE`.
