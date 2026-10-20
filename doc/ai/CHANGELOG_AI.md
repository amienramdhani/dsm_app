# DSM AI CHANGELOG

Records meaningful changes performed with AI assistance.

This is not a replacement for Git commit history.

---

## Format

### YYYY-MM-DD - Short title

AI:
Claude / ChatGPT / Other

Scope:
What area was changed.

Files:
- file1
- file2

Changes:
- change
- change

Validation:
- test performed
- result

Pending:
- unresolved item if any

---

# History

## 2026-10-19 - Fase 3 langkah 2: resolver Effective Sales di publish (BELUM TERUJI)

AI:
Claude

Scope:
Fase 3 (Request #15, #17 bagian fondasi). Rancangan: doc Rancangan Phase 3 (disetujui user 2026-10-19). Hanya fondasi backend; belum ada menu/UI.

Files:
- lib/tx_effective.php (baru)
- lib/publish.php
- api.php

Changes:
- `effectiveRows_()` dipanggil di `publish_` sebelum `PortalCore::build`: NIK file ST -> pemilik dealer-brand menurut tanggal (`portfolioResolveRows_`) -> koreksi manual per transaksi. Dihitung ulang tiap publish, jadi tahan upload ulang. Data ST mentah tidak ditulis ulang oleh resolver.
- `txKeys_()`: kunci transaksi stabil tanpa NIK (ID_BARIS bila ada; cadangan memuat SJ/SKU/dealer asli/tanggal/qty/amount + nomor urut kembaran) plus sidik jari baris.
- Tabel baru `dsm_txcorr` (dibuat otomatis, saat ini kosong). Koreksi yang tidak cocok (baris hilang, sidik jari berubah, NIK tidak aktif) tidak diterapkan diam-diam dan dilaporkan sebagai yatim di catatan publish.
- Catatan publish memuat jumlah baris yang disesuaikan resolver (untuk melihat dampak publish pertama).
- Saklar darurat: prop `TXEFF_OFF` = '1' mematikan resolver.
- `ptPartsForPublish_` sengaja TIDAK diubah: transfer masih menulis ulang partisi ST sampai enam pembaca baris mentah (api_menu x2, api_export2, preview api_portfolio dan api_dealer_transfer_direct) dipindah ke resolver (langkah 5).

Validation:
- php -l (3 file) lolos.
- Harness stub: kunci tidak berubah oleh NIK/urutan, baris kembar dibedakan, koreksi menempel, baris hilang/sidik jari berubah jadi yatim, saklar off = perilaku lama.
- Belum diuji di aplikasi nyata.

Pending:
- Uji nyata: publish ulang lalu baca catatan jumlah baris disesuaikan; bandingkan total per Sales bulan tanpa transfer; transfer lalu upload ulang bulan sama.
- Langkah 3 berikutnya: tabel pengecualian atribusi + menu KOREKSI TRANSAKSI.

---

## 2026-10-19 - Fase 1 (bagian 1): Bug #1-#5 dan Transfer Dealer menu native

AI:
Claude

Scope:
Fase 1 dari rencana 3 fase (docs/claude-handoff/revisi-bug.md + request-revisi.md #1). Baseline kode: 76cb12b. Bug dan request dikerjakan terpisah, satu commit per item.

Files:
- index.php
- assets/telemarketing/tm.js
- assets/dealer_transfer_direct.js
- assets/transfer_ui_hotfix.js
- assets/admin_revision.js
- assets/admin_revision.css
- assets/searchable.js (hanya bump versi cache loader)

Changes:
- Bug #1 + Request #1 (21527e1): Transfer Dealer jadi menu native di grup LAINNYA (`PAGE==='transfer'`, `titles`, `GROUPS.lain`, `menuIcon`, `transferDealerPage()`), gate role ASM/RGM/ADMIN/SUPER ADMIN di `tmMenuAllowed()`. Mekanisme clone DOM / FAB / modal (`roleBoot`, `addRoleFab`, `openRole`, `injectRoleMenu`) dihapus; diganti `openPage(containerId)`. Tab Approval tampil hanya jika backend `canApprove`. Backend tidak diubah.
- Bug #2 dan #3: sudah tertangani `portalSalesSearch()` + `addStyle()` di transfer_ui_hotfix.js (dipertahankan); akar masalah `.pb{overflow-x:auto}` memaksa overflow-y auto. Dikonfirmasi user.
- Bug #5 (8772ad1): di mobile (<=760px) tab kategori Admin diganti satu tombol + popup bottom-sheet. Desktop tidak berubah.
- Bug #4 (5429a76): panel Admin berperan sebagai baris daftar; isinya dipindah (bukan di-clone) ke satu area `.dsmDetailHost` di bawah daftar, satu panel aktif sekaligus. Percobaan awal 8772ad1/d0ee9ed (scroll, CSS order) digantikan.
- Cache loader: dealer_transfer_direct.js dan transfer_ui_hotfix.js ?v=20261019a; admin_revision.js/css ?v=20261019d.

Validation:
- Syntax: php -l, node --check.
- Bug #4/#5 diuji di Chromium memakai harness statis (CSS asli index.php + foldAdminFeatures + admin_revision.js, panel dummy) pada 1440px dan 390px.
- Dikonfirmasi manual oleh user di app lokal: Bug #1 (sisi Admin), #2, #3, #4, #5.

Pending:
- Bug #1 per role ASM/RGM/Sales belum dites sistematis.
- Request #10 (sembunyikan panel Transfer Dealer lama di Admin, termasuk tab Via Excel), #11 (satu tab APPROVAL), #12 (hapus NOTIFIKASI KHUSUS), serta Request #2-#5 belum dikerjakan.
- Catatan arsitektur: halaman Admin punya dua lapis kategori (foldAdminFeatures di index.php + admin_revision.js yang meng-override); jangan menambah lapisan ketiga.

---

## 2026-10-17 - Transfer Dealer mobile + ASM/RGM approval + Transfer Sales entry

AI:
ChatGPT

Scope:
Dealer ownership transfer UX, role workflow, approval, and standalone Sales transfer entry

Files:
- `lib/api_dealer_transfer_direct.php`
- `assets/dealer_transfer_direct.js`
- `assets/searchable.js`
- removed `assets/dealer_transfer_direct_hotfix.js`

Changes:
- Simplified Transfer Dealer so Sales tujuan is selected once at the top; dealer rows are checklist-only and no longer show duplicate per-row/bulk destination controls.
- Added searchable Sales sumber and Sales tujuan using the shared DSM searchable-combobox behavior.
- Added a dedicated mobile rendering: compact dealer rows, stacked source/destination fields, filter controls, and a sticky selected-count / Preview action.
- Removed the clipping behavior around the Transfer Dealer Admin panel and kept the dealer table as its own scroll area on desktop.
- Added ASM and RGM access. They can prepare and preview a transfer, but submission creates a PENDING request and does not change ownership.
- Added Admin approval/rejection inside Transfer Dealer. Approval revalidates current ownership and then executes through the existing canonical transfer engine; rejection requires a note.
- Added request statuses PENDING / APPROVED / REJECTED / FAILED with audit metadata in `_dealer_transfer_requests`.
- Added a standalone `Transfer Sales` entry under Pengguna & Akses before Admin grouping. It launches the existing Alihkan Portofolio engine for resign, mutation, stop-rangkap, or long leave, including target/actual handling.
- Removed the temporary dealer-transfer hotfix because its behavior is now integrated into the main transfer UI.

Validation:
- Changes were written only to branch `dev`.
- `main` and Amin's final repository were not touched.
- Transfer execution still routes through `transferRows_` + `apiCommitDealerTransfer`; ASM/RGM requests do not execute that commit until Admin approval.

Pending:
- Pull latest `dev`, hard refresh, then smoke-test: Admin direct transfer, ASM request, RGM request, Admin approval/rejection, mobile scroll/sticky action, and Transfer Sales menu entry.

---

## 2026-10-17 - Direct dealer transfer in Admin

AI:
ChatGPT

Scope:
Dealer Admin operational transfer workflow

Files:
- `api.php`
- `lib/api_dealer_transfer_direct.php`
- `assets/dealer_transfer_direct.js`
- `assets/searchable.js`

Changes:
- Added a direct in-app workflow inside the existing Transfer Dealer panel while preserving the existing Excel upload workflow in a separate tab.
- Added compact table selection for large dealer portfolios with search, area/brand filters, 25/50/100 row page size, select-all filtered results, pagination, per-row receiving Sales, and bulk receiving-Sales assignment.
- Direct transfer uses the existing `transferRows_` validation and `apiCommitDealerTransfer` commit engine, so permission checks, effective-dated ownership, dealer-brand assignment history, audit, checkpoint, and rollback remain one canonical path.
- Dealer-brand split ownership is preserved: only brands currently owned by the selected source Sales are transferred; `BRAND=ALL` is used only when the source owns every primary brand and the generic Master Customer owner.
- Current MTD actual is displayed as selection context only; original raw transactions are not rewritten.
- Transfer Dealer does not redistribute Sales targets. Resign, full mutation, stop-rangkap, or transitions that must move targets/actual/status together remain the responsibility of Alihkan Portofolio.

Validation:
- API whitelist and demo write protection were extended only for the new direct-transfer staging endpoint.
- Existing `apiIndukBulkTemplate` route was explicitly preserved after API wiring review.
- Changes were written only to branch `dev`; `main` and Amin's final repository were not touched.

Pending:
- Pull `dev` and smoke-test the direct Transfer Dealer tab in Laragon before using it on live operational data.

---

## 2026-10-17 - KPI Khusus period selector

AI:
ChatGPT

Scope:
KPI Khusus Admin period navigation

Files:
- `assets/kpikhusus_period.js`
- `assets/searchable.js`

Changes:
- Added an independent month selector to KPI Khusus, matching the behavior/range used by Atur KPI reguler.
- KPI Khusus no longer has to stay locked to the currently selected report period.
- Period changes reload KPI Khusus with fresh private state so editor/target drafts from the previous month cannot leak into another month.
- Daftar Orang, Editor KPI Khusus, Aturan Poin DOS, Target SO, preview, and save continue to use the loaded KPI Khusus period.

Validation:
- Changes were written only to branch `dev`.
- `main` and Amin's final repository were not touched.
- Local Laragon smoke test is required after pulling the latest `dev`.

Pending:
- Confirm period switching from the KPI Khusus screen in Laragon.

---

## 2026-10-17 - Admin focus, KPI compact, and portfolio transfer implementation

AI:
ChatGPT

Scope:
Admin UI / Edit User / KPI Khusus / portfolio ownership transfer

Files:
- `api.php`
- `assets/searchable.js`
- `assets/admin_revision.css`
- `assets/admin_revision.js`
- `assets/admin_portfolio_guard.js`
- `assets/portfolio_transfer.js`
- `lib/dealer_brand.php`
- `lib/portfolio_core.php`
- `lib/api_portfolio.php`

Changes:
- Added the approved Admin category structure with a dedicated Upload Harian group while retaining Sistem.
- Added Admin Focus Mode; inactive panels are dimmed and the active panel uses a soft version of its group color.
- Polished Edit User and made Region portofolio rangkap blank/disabled when rangkap Sales is off.
- Added the four-stage Alihkan Portofolio flow: Penerima, Bagi Dealer, Target & Aktual, and Preview.
- Supports multiple receiving Sales, per-dealer allocation, bulk assignment, and dealer-brand ownership preservation.
- Moves and merges active/future targets from Target_Input, Target_Periode, and manual KPI Khusus SO target storage.
- Current-period ST attribution is rebuilt from effective dealer-brand ownership without rewriting the original uploaded raw source.
- Added atomic rollback coverage around portfolio transfer data changes.
- Added Edit User guard so an active rangkap portfolio cannot be switched off directly without the portfolio transfer flow.
- Added compact KPI Khusus accordion/focus editing, weight progress treatment, and a visible fixed rule `Ada SO tapi stok 0 = 0` in the editor.

Validation:
- Changes were written only to branch `dev`.
- `main` and Amin's final repository were not touched.
- Repository compare confirms `dev` is ahead of the prior reconciliation baseline and the expected changed/new files are present.
- Local Laragon runtime validation is still required after pulling `dev`.

Pending:
- Run smoke tests in Laragon for Admin navigation, Edit User, KPI Khusus, and a non-production portfolio-transfer scenario before merging anywhere else.

---

## 2026-10-17 - Claude handoff reconciled with current repository

AI:
ChatGPT

Scope:
AI context synchronization / repository documentation

Files:
- `CLAUDE.md`
- `doc/ai/CURRENT_STATE.md`
- `doc/ai/CLAUDE_SYNC.md`
- `doc/ai/HANDOFF.md`
- `doc/ai/CHANGELOG_AI.md`

Changes:
- Reconciled Claude's previous final DSM handoff against the current `dev` branch and approved decisions.
- Declared `aryhidayattt/dsm` branch `dev` as the canonical current development state.
- Added `CURRENT_STATE.md` with verified repository state and known documentation/code drift.
- Added `CLAUDE_SYNC.md` explaining what Claude's previous session is behind on.
- Updated `CLAUDE.md` so Claude must read current repository context before coding.
- Removed the obsolete pending item about connecting ChatGPT to GitHub.
- Documented that the old numbered ZIP/package workflow is historical rather than canonical.
- Documented the newer secret model: tracked `config.php` loads ignored `config.local.php`.
- Documented current Telemarketing KPI and KPI Validation decisions that are newer than parts of the existing implementation.
- Documented known notification permission mismatch.
- Documented that the AI Assistant backend in the current repository is materially ahead of the older handoff description.

Validation:
- Compared Claude handoff against the current `dev` code and repository AI docs.
- Verified current KPI Khusus v2 code, PWA/push code, Telemarketing modules, Form Order implementation, config separation, and AI Assistant backend are present in the repository.
- No application source code was modified during this synchronization.

Pending:
- Future application changes should use `dev` plus current AI documentation as baseline.
- Known implementation gaps remain to be addressed only when explicitly requested.

---

## 2026-10-17 - Development repository initialized

AI:
ChatGPT

Scope:
Git / development workflow

Changes:

- Created private DSM development repository.
- Created separate `main` and `dev` branches.
- `main` defined as stable baseline.
- `dev` defined as active development branch.
- Local secret configuration separated from tracked configuration.
- `config.local.php` excluded from Git.
- `config.local.example.php` added as safe configuration template.
- Development repository kept separate from Amin's final DSM repository.

Validation:

- Git working tree clean.
- `dev` successfully pushed to `origin/dev`.
- `config.local.php` is ignored by Git.

---

### 2026-10-19 - Peringatan tabrakan (Alihkan Portofolio) + Riwayat Transfer & tombol Batalkan
AI:
Claude

Scope:
- Langkah 1 Alihkan Portofolio: bila ada perubahan pemilik dealer yang lebih baru dari tanggal efektif -> kotak peringatan + tombol "Pakai tanggal X" (backend: ptConflicts_ di preview).
- Admin > Riwayat Transfer: daftar perubahan terbaru (Transfer Dealer & Alihkan Portofolio) dengan tombol Batalkan. Undo = restore sheet "sebelum" (Master_Customer, Dealer_Assignment_History, Dealer_Brand_Assignment; + Target_Input/Target_Periode/Portfolio_Exit/_kk_target utk portofolio) HANYA bila hash kondisi "sesudah" masih sama (tidak ada perubahan lain sesudahnya) + publish ulang. Pelaku atau Super Admin; alasan >= 5 karakter; audit TRANSFER_UNDO.
- Tidak dapat dibatalkan: portofolio yang menonaktifkan akun (Resign), pembuatan akun VACANT tidak ikut dikembalikan, perangkat yang sudah dicabut tidak kembali.

Files:
- lib/transfer_undo.php (baru), lib/bulk.php (apiCommitDealerTransfer register), lib/api_portfolio.php, api.php (require + whitelist + write fn), index.php (panel Riwayat Transfer), assets/portfolio_transfer.js, assets/admin_revision.js, assets/searchable.js

Validation:
- php -l, node --check (+inline). Tes stub undo (register/list/undo/ditolak bila ada perubahan lain/dua kali). BELUM TERUJI di app nyata (khususnya undo portofolio + _kk_target + publish).

---

### 2026-10-19 - VACANT otomatis + audit #9 (alasan pengalihan) + whitelist API
AI:
Claude

Scope:
- Alihkan Portofolio: tombol "Alihkan semua ke VACANT <area>"; akun `VAC-<AREA>` dibuat otomatis (Master_Sales, POSISI SALES, tanpa login/PIN, atasan = atasan sumber / ASM sendiri bila rangkap) lewat apiPortfolioEnsureVacant; VACANT lama dipakai ulang (cocok per area, case-insensitive). Sumber VACANT -> penerima Sales baru (VACANT tidak jadi kandidat penerima sesama VACANT).
- Daftar user Admin menyembunyikan VACANT (chip "Tampilkan VACANT (n)"); NIK `VAC…` tidak bisa dibuat manual (apiSaveUser).
- #9: kategori alasan dikirim ke backend (PT_REASONS). Resign (Sales, bukan VACANT, bukan future) -> nonaktif akun; Cuti panjang -> tidak menonaktifkan & tidak mematikan flag Sales; sisa target di sumber hanya untuk periode efektif pada mode tanggal tertentu. Audit mencatat kategori + tanggal efektif.
- api.php: apiPortfolioAssets & apiPortfolioEnsureVacant didaftarkan ke DSM_ALLOWED/DSM_WRITE_FNS (tanpa ini cek "rangkap kosong" di Edit User akan gagal).

Files:
- lib/api_portfolio.php, lib/api_import.php, api.php, index.php (isVacUser, filter daftar user), assets/portfolio_transfer.js, assets/searchable.js

Validation:
- php -l, node --check (+ inline scripts), tes stub ptFindVacant_/ptVacantNik_. BELUM TERUJI di app nyata (pembuatan akun VACANT + publish, laporan ASM menjumlah VACANT).

---

### 2026-10-19 - Edit User: tampilan baru + berhenti rangkap Sales satu proses bila portofolio kosong
AI:
Claude

Scope:
- ASM/RGM dengan rangkap Sales yang tidak punya dealer, target, maupun aktual MTD boleh di-uncheck lalu Simpan (sekali proses). Jika masih ada aset -> tetap wajib Alihkan Portofolio.
- Edit User: hero (avatar/nama/NIK/role), kartu bergradasi, fokus input, wilayah tugas Admin jadi kartu chip (hanya tampil untuk ADMIN), tombol simpan gradient.

Files:
- lib/api_portfolio.php (ptAssets_, apiPortfolioAssets), lib/api_import.php (apiSaveUser: guard backend), assets/admin_portfolio_guard.js, assets/admin_revision.js/.css, assets/searchable.js

Validation:
- php -l, node --check. BELUM TERUJI di app nyata (guard backend baru menolak uncheck bila ada dealer/target/aktual dan belum "exited").

---

### 2026-10-19 - Alihkan Portofolio: split target dengan sisa di Sales lama (tanggal tertentu)
AI:
Claude

Scope:
Mode tanggal tertentu: kolom "Tetap di <Sales lama>" per target. Penerima + sisa wajib = target asal (total ASM tidak berubah). Target penerima di-merge (ditambah), sisa tetap di source (Target_Input, Target_Periode, KK SO).

Files:
- lib/api_portfolio.php (ptAlloc_ keep, ptMoveWide_/ptMoveLong_/ptMoveKk_), assets/portfolio_transfer.js, assets/searchable.js

Validation:
- php -l, node --check, tes logika ptAlloc_/ptMoveLong_ dengan stub (200 -> 130 sisa + 70 ke B; 125 ditolak). BELUM TERUJI di app nyata. Pemotongan aktual per tanggal (A sampai tgl-1, B mulai tgl) belum diverifikasi -> cek bersama Phase 3.

---

### 2026-10-19 - Alihkan Portofolio: pilih periode efektif mundur
AI:
Claude

Scope:
"Berlaku mulai" bisa memilih periode 1-6 bulan ke belakang (YYYY-MM), selain bulan berjalan / bulan depan.

Files:
- lib/api_portfolio.php (ptEffective_, ptPeriodOptions_, ptPeriods_, ptDealers_ multi-periode, publish ST parts semua periode sejak efektif), assets/portfolio_transfer.js, assets/searchable.js

Changes:
- Ownership efektif sejak tanggal 1 periode terpilih; target >= periode itu ikut dipindah (logika existing); aktual dihitung sejak periode itu; ST parts di-publish ulang untuk setiap periode dari efektif s/d sekarang.

Tambahan: opsi "Tanggal tertentu…" (mode `DATE:YYYY-MM-DD`, <= hari ini, >= awal 6 bulan lalu); ownership berlaku sejak tanggal itu, target tetap per bulan.

Validation:
- php -l, node --check. BELUM TERUJI di app nyata. RISIKO: menyentuh atribusi periode lampau (beririsan Phase 3) - uji di data salinan dulu; assignment masa depan/konflik tetap diblok existing guard.

---

### 2026-10-19 - Request #7 UI Alihkan Portofolio (Phase 2, awal)
AI:
Claude

Scope:
Tahap 2 "Bagi Dealer": dropdown per baris diganti checkbox dealer + kartu penerima (alokasikan terpilih), filter region, tag penerima berwarna (klik x untuk batal), progress x/y. Backend tidak diubah; daftar dealer sudah berbasis ownership (actual 0 tetap muncul) dan Lanjut diblok bila belum 100%.

Files:
- assets/portfolio_transfer.js, assets/searchable.js (cache v)

Validation:
- node --check. BELUM TERUJI di app nyata. Alokasi masih per dealer (belum per dealer-brand).

---

### 2026-10-19 - Request #2-#5 Transfer Dealer (Phase 1)
AI:
Claude

Scope:
Backend + UI Transfer Dealer: pilih brand per dealer, preview, sinkron Primary PIC saat konsolidasi, approval aman.

Files:
- lib/api_dealer_transfer_direct.php, assets/dealer_transfer_direct.js, assets/searchable.js (cache v)

Changes:
- Item transfer menerima `brands` (subset brand milik source; tanpa implicit ALL untuk sebagian brand).
- Approve: klaim atomik PENDING->PROCESSING (anti double-process), validasi ulang scope PEMOHON (source+tujuan), deteksi stale vs preview saat diajukan (mismatch -> FAILED tanpa mutasi).
- Konsolidasi: bila setelah transfer semua brand aktif dealer dipegang tujuan, kirim baris ALL sehingga Primary PIC (Master_Customer NIK SALES) ikut tersinkron; split dipertahankan.
- UI: chip brand per dealer bisa dicentang (desktop+mobile), MTD per brand terpilih, preview menampilkan brand/tujuan/MTD/alasan.

Validation:
- php -l, node --check. BELUM TERUJI di app nyata (khususnya baris ALL saat sebagian brand sudah milik tujuan, rollback commit, target tidak bergeser).

---

### 2026-10-19 - Request #10, #11, #12 (Phase 1)
AI:
Claude

Scope:
Admin: panel Transfer Dealer lama disembunyikan (flow Excel pindah ke menu Transfer Dealer, tab "Via Excel"); panel "Approval" terpusat (Kontak Dealer + Transfer Dealer, filter jenis/status, badge pending); NOTIFIKASI KHUSUS dihapus dari Admin (tetap di Perangkat Saya).

Files:
- index.php (bulkMaintenancePanels, transferExcelMount, approvalPanel, loadContactRequests, ADMIC, tabOf)
- assets/dealer_transfer_direct.js, assets/admin_revision.js, assets/admin_revision.css, assets/searchable.js (cache v)

Validation:
- php -l, node --check (termasuk inline script). BELUM TERUJI di app nyata.

---


## 2026-10-19 — Phase 3 langkah 3+4: menu Koreksi Transaksi

AI:
Claude

Scope:
Menu KOREKSI TRANSAKSI (Admin/Super Admin): antrean Perlu Ditinjau, Sudah Dikoreksi, cari transaksi; koreksi Sales efektif satu baris transaksi (alasan wajib), cabut koreksi, riwayat/audit, banner koreksi yatim, republish dengan guard total company (qty & omzet) dan rollback otomatis.

Files:
- lib/api_txcorr.php (baru), lib/tx_effective.php, lib/publish.php, api.php (whitelist + write list)
- assets/tx_correction.js (baru), assets/searchable.js (loader), assets/telemarketing/tm.js (gate role), index.php (halaman `koreksi`)

Validation:
- php -l, node --check (termasuk inline script). SQL API baru belum dijalankan di MySQL. BELUM TERUJI di app nyata.

---

## 2026-10-19 — Phase 3 langkah 5: reader mentah → resolver, stop rewrite ST, lineage export

AI:
Claude

Scope:
- `txEffRaw_()` (lib/tx_effective.php): baris ST mentah dikembalikan dalam bentuk asli tetapi NIK = Sales efektif (pemilik dealer-brand + koreksi). Dipakai di apiTransactions, apiTransactionGroups, export RAW ST, preview aktual (api_portfolio, api_dealer_transfer_direct), audit PIC (api_health), baseline dealer_brand.
- `ptPartsForPublish_` tidak lagi menulis ulang partisi ST (data mentah utuh; partisi yang sudah pernah ditulis ulang dibiarkan). Kill-switch TXEFF_OFF kini memakai portfolioResolveRows_ lama sebagai fallback.
- Export RAW ST: kolom NIK/NAMA SALES FILE ST, MARKER ATRIBUSI, ALASAN KOREKSI (alasan hanya terisi untuk ADMIN/SUPER ADMIN).
- Belum diubah: `PortalCore::sliceCmp` (override pemilik master untuk bulan pembanding) — perlu keputusan terpisah.

Validation:
- php -l; stub test txEffRaw_ (jumlah/urutan baris sama, NIK efektif benar). BELUM TERUJI di app nyata.

---

## 2026-10-19 — Target SO per dealer · Tahap A (data + upload + tinjau)

AI:
Claude

Scope:
Menu "Target SO" (grup Lainnya). Admin/Super Admin: template Excel (KODE CUSTOMER, BRAND, PERIODE, TARGET SO) → preview Baru/Update/Sama/Ditolak → commit dengan alasan (audit + log perubahan). Semua role: daftar target read-only sesuai kepemilikan dealer saat ini. Skor KPI BELUM memakai target ini (Tahap B).

Files:
- lib/api_dealertarget.php (baru: dsm_dealer_target, dsm_dealer_target_log), api.php (whitelist + write list)
- assets/dealer_target.js (baru), assets/searchable.js (loader), assets/telemarketing/tm.js (TELEMARKETING tidak melihat menu), index.php (halaman `targetso`)

Validation:
- php -l, node --check, stub test parser/plan. SQL belum dijalankan di MySQL. BELUM TERUJI di app nyata.

---

## 2026-10-19 — Target SO per dealer · Tahap B (skor KPI)

AI:
Claude

Scope:
Indikator SO di KPI Khusus memakai target per dealer bila subjek punya dealer bertarget (basis MTD): skor = Σ min(SO dealer, KK_CAP% × target) / Σ target, per segmen kepemilikan dealer-brand; target dibagi pro-rata hari bila dealer pindah Sales tengah bulan. `kkSoTarget_` menganggap target ada bila ada dealer bertarget (notif TARGET_SO tidak salah menandai). Tanpa dealer bertarget → skor lama.

Files:
- lib/api_dealertarget.php (dtgTargets_, dtgSegments_, dtgSubjectPlan_, dtgScoreCompute_), lib/api_kpikhusus.php (kkCompute_, kkSoTarget_)

Validation:
- php -l; stub test transfer tengah bulan (A 70%, B 35,3%, A+B 55,6%) cocok hitungan manual. BELUM TERUJI di app nyata.

---

## 2026-10-19 — QA #20 (awal): scope Admin regional di Koreksi Transaksi

AI:
Claude

Scope:
Audit statis otorisasi Phase 3: semua api Koreksi & Target SO terdaftar di whitelist, write-list, dan ber-guard. Ditemukan: daftar/pencarian/orphan Koreksi Transaksi belum dibatasi wilayah untuk ADMIN regional (tulis/cabut sudah). Diperbaiki dengan `txcScope_` (Super Admin = semua; Admin = Sales yang boleh dikelola).

Files:
- lib/api_txcorr.php

Validation:
- php -l. BELUM TERUJI di app nyata.

---

## 2026-10-19 — Fix Transfer Dealer ASM/RGM: sumber & tujuan = Sales di bawahnya

AI:
Claude

Scope:
`dtdPersonOk_` untuk ASM/RGM sebelumnya jatuh ke `canManageUser_` (khusus Admin) sehingga hanya dirinya yang muncul. Sekarang ASM/RGM melihat dirinya + seluruh Sales di bawahnya (pohon atasan, `PortalCore::scope`). Berlaku untuk daftar sumber, tujuan, dan validasi pengajuan/approval.

Files:
- lib/api_dealer_transfer_direct.php

Validation:
- php -l; stub (RGM→ASM→2 Sales: RGM melihat R,A,S1,S2; ASM melihat A,S1,S2; Sales lain tidak). BELUM TERUJI di app nyata.

---

## 2026-10-19 — Rapikan Transfer Dealer versi mobile (ASM/RGM)

AI:
Claude

Scope:
Tampilan HP: stepper 3 kolom ringkas, input/select 42px tanpa overflow (termasuk tanggal), filter 2 kolom, tombol penuh lebar, kartu dealer lebih besar (teks 12-14px, checkbox 24px, seluruh kartu bisa di-tap), brand chip lebih besar, preview berupa kartu tumpuk (tanpa scroll horizontal), sticky bar aksi dengan tinggi 44px, kartu approval 2 kolom. Logika tidak diubah.

Files:
- assets/dealer_transfer_direct.js, assets/searchable.js (cache v 20261019h)

Validation:
- node --check; screenshot Chromium 390px dengan data tiruan (halaman pilih dealer & preview) tanpa error konsol. BELUM TERUJI di app nyata.

---

## 2026-10-19 — Transfer Dealer: centang per brand hanya brand yang pernah ST + aturan brand baru

AI:
Claude

Scope:
- Daftar dealer: chip brand hanya brand yang pernah ada ST (qty>0 di cube terpublish mana pun). Dealer tanpa riwayat ST menampilkan "Belum ada ST · semua brand ikut" dan seluruh brand ikut pindah.
- Stage: bila semua brand ST yang dipegang Sales sumber dipilih DAN itu membuat dealer berpindah penuh, brand yang belum pernah ST ikut pindah → baris ALL (Primary PIC/Master Customer ikut) → brand baru kelak masuk Sales tujuan. Bila split (ada brand tidak ikut / dipegang Sales lain), hanya brand terpilih yang dipindah; brand baru kelak tetap ke pemilik awal (Master Customer).
- Preview menampilkan baris petunjuk "Brand baru yang kelak muncul → <Sales>".
- Usulan default untuk kasus split (brand baru → pemilik awal) menunggu konfirmasi user; belum dicatat di DECISIONS.md.

Files:
- lib/api_dealer_transfer_direct.php (dtdStEver_, list, dtdBuildStage_), assets/dealer_transfer_direct.js, assets/searchable.js (v 20261019i)

Validation:
- php -l, node --check; stub 4 skenario (semua ST pindah → ALL; sebagian → per-brand, master tetap; split dengan Sales lain; dealer tanpa ST). BELUM TERUJI di app nyata.

---

## 2026-10-19 — Target SO: filter region + keputusan TRANSFER-BRAND-001

AI:
Claude

Scope:
- Menu Target SO: filter Region (region dealer dari Master Customer; daftar region mengikuti target yang boleh dilihat user). Region juga tampil di bawah nama dealer.
- DECISIONS.md: TRANSFER-BRAND-001 (aturan brand per-ST dan brand baru saat transfer) disetujui user.

Files:
- lib/api_dealertarget.php (apiDealerTargetList param `$region`), assets/dealer_target.js, assets/searchable.js (v 20261019b), doc/ai/DECISIONS.md

Validation:
- php -l, node --check. BELUM TERUJI di app nyata.

---

## 2026-10-19 — Transfer Dealer UI redesign (approved mockup)
- `assets/dealer_transfer_direct.js` (v=20261019j): header gradient + step pills, kartu Dari→Ke, ringkasan sticky di kanan (desktop), kartu dealer + ceklis brand besar (mobile), preview berupa kartu + ringkasan. Logika/API/aturan transfer tidak berubah; alasan tetap diisi di langkah 1.
- Search dealer tidak lagi kehilangan fokus saat mengetik.
- Mockup disetujui user: Design artifact "Mockup Transfer Dealer Mobile".

## 2026-10-19 — Transfer Dealer: picker Sales sesuai mockup
- `assets/dealer_transfer_direct.js` (v=20261019k): Dari/Ke Sales jadi dua kartu berdampingan (nama + NIK di bawah), tap membuka sheet pencarian Sales; tanggal jadi baris "Berlaku mulai" dengan format "20 Okt 2026". Logika dan API tidak berubah.

## 2026-10-19 — Mobile kit compact (Admin, Koreksi, Target SO, Transfer Dealer)
- File baru `assets/mobile_kit.css` (v=20261019a), dimuat dari loader `assets/searchable.js`. Hanya berlaku di layar <=760px; desktop tidak berubah.
- Kulit compact di atas markup yang sudah ada (tidak ada perubahan handler/API/validasi/hak akses): pita judul panel Admin bergradient; Akun pengguna dan Cari dealer jadi kartu kecil (via `:has()`; browser lama tetap tabel biasa); Approval kartu padat; modal Tambah/Edit user dan Tambah dealer jadi bottom sheet padat; Koreksi transaksi/Target SO hero gradient + kartu padat; Transfer Dealer dipadatkan.
- Mockup disetujui user: Design artifact "Mockup Admin User Dealer Approval" (mobile: user, edit user, cari dealer, tambah dealer, approval; desktop: approval). Desktop user/dealer belum disetujui, tidak diubah.
- Validasi: fixture HTML 390px (tanpa horizontal scroll). BELUM TERUJI di app nyata per role.

## 2026-10-19 — Transfer Dealer desktop: toolbar filter lebih proporsional
- `assets/dealer_transfer_direct.js` (v=20261019l): di desktop, baris cari + Area/Brand/per halaman + tombol jadi kartu dua baris (cari penuh lebar, kontrol 46px). Mobile tidak berubah. Logika tidak berubah.

## 2026-10-19 — Navbar bawah HP: gaya B1 (mockup disetujui)
- `assets/mobile_kit.css` (v=20261019b): navbar bawah `#mBottom` jadi bar putih menempel (sudut atas 24px, shadow lembut); tab aktif = ikon dalam kotak gradient lembut + label indigo + garis gradient di atas. Hanya CSS, tab/markup/navigasi tidak berubah. Mockup: Design artifact "Mockup Background dan Navbar HP" (NavB1).
- Background HP tidak diubah (opsi belum ada yang cocok). Mockup tabel padat (4 HP + 4 desktop) dibuat, belum dipilih: artifact "Mockup Tabel Padat DSM".

## 2026-10-19 - Tabel padat desktop (Claude)
- Tambah `assets/table_dense.css` + loader `searchable.js` (`?v=20261019a`). Desktop saja: tabel default -> Desktop 1; Early Warning & Pencapaian Tim ASM dipadatkan; Perbandingan Tim meredupkan grup tertutup saat ada yang terbuka. Tabel berwarna & HP tidak disentuh. Hanya diuji di fixture.

## 2026-10-19 - Hero Beranda HP
- Tambah `assets/mobile_hero.js/.css` dan `assets/hero/*.webp`, dimuat via `assets/searchable.js`. Hero mengikuti jam dan pace Sell Thru (4 tingkat), kilat lembut, 5 hari kerja awal netral. Desktop dan `index.php` tidak diubah.

## 2026-10-19 - Paket Beranda HP, popup libur, Early Warning, tabel padat
- Beranda HP: tab tanpa tembus, Cakupan/Periode di Lainnya, topbar bersih, font Inter. Popup selamat berlibur (`assets/holiday_popup.js`, `apiV6Boot` +`holn`). Early Warning: `v6EWSub`. `table_dense.css`: Pencapaian per Indikator padat, Perbandingan Tim non-bold.

- 2026-10-19: beranda_style.css baru (tab gaya B HP+desktop, latar navy/biru pucat Beranda); mobile_hero.js flag dsmBerandaPage; versi aset dinaikkan.
- 2026-10-19: beranda_style.css desktop: latar semua halaman + tab navy.
- 2026-10-19: tab aktif navy dibedakan warnanya.
- 2026-10-19: fix table_dense.css: Daftar Dealer tidak ikut gaya header gradient.
- 2026-10-19: EW kolom Region lebih lega.
- 2026-10-19: strip pemisah sub-teks inline tabel padat.
- 2026-10-19: garis pemisah hitam grup terbuka di Perbandingan Tim.
- 2026-10-19: hapus kata 'Tim' di baris grup Perbandingan Tim.
- 2026-10-19: Rincian Mingguan per Type: buka-tutup per model.
- 2026-10-19: hapus teks varian; baris tanpa varian navy.
- 2026-10-19: padatkan Summary Pencapaian per Brand.
- 2026-10-19: padatkan tabel Transfer Dealer.
- 2026-10-19: summary_split.js: pisah Target & Aktual (desktop).
- 2026-10-19: fix growth sebaris desktop.
- 2026-10-19: Transfer Dealer kode di depan nama.
- 2026-10-19: Matriks dealer×brand: header warna kembali + kolom Unit/bln.
- 2026-10-19: kode dealer di Matriks.
- 2026-10-19: Dealer Baru per Sales format seperti Tim.
- 2026-10-19: header tabel rata tengah vertikal global.
- 2026-10-19: tabel Transaksi sebaris.
- 2026-10-19: header middle kuat + kode dealer depan di Transaksi.
- 2026-10-19: cell_inline.js: kode di depan & sub sebaris untuk semua tabel desktop.
- 2026-10-19: pisah kolom Sales/Wilayah di Dealer+TYPE (desktop).
- 2026-10-19: tm_steps.js/css: form Catat Order popup HP bertahap.
- 2026-10-19: kartu barang form order HP dipadatkan.
- 2026-10-19: Daftar Dealer dikecualikan dari cell_inline.
- 2026-10-19: tombol dealer tutup merah full width.
- 2026-10-19: Catat order full width.
- 2026-10-19: tombol tutup dealer dipindah di bawah Catat order.
- 2026-10-19: font tabel HP lebih kecil.
- 2026-10-20: splash baru assets/dsm_splash.js (logo SVG menggambar garis 3 dtk, latar lembut, putih melebar dari tengah lalu konten); logo app/login diganti PNG transparan (assets/dsm-logo.png, .svg); ?splash=1 paksa tampil.
- 2026-10-20: splash diperlambat x1,4 (~4,7 dtk) dan selalu memutar animasi walau reduced-motion.
- 2026-10-20: sidebar desktop + topbar HP (non-Beranda) gradien biru lembut (beranda_style.css v20261020a).
- 2026-10-20: tree sidebar desktop lebih tegas (color-mix); bar bawah + drawer HP biru lembut (beranda_style.css v20261020b).
- 2026-10-20: drawer HP 'Semua menu' kembali putih; ikon menu Brand diganti ikon HP (menuIcon).
- 2026-10-20: assets/scope_picker.js: pemilih Cakupan HP jadi bottom sheet berkelompok seperti desktop (select asli tetap sumber nilai).
- 2026-10-20: scope_picker.js juga menangani select Cakupan di kartu drawer HP (.hsSel dari mobile_hero.js).
- 2026-10-20: kolom cari di sheet Cakupan HP (filter nama/wilayah).
- 2026-10-20: Mode Eksekutif HP: chip Brand grid 4 kolom, chip Indikator segmen satu baris, semua kapital (beranda_style.css v20261020d).
