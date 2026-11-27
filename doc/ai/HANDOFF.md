# DSM CURRENT HANDOFF

Last updated: 2026-10-20

## Repository

Development repository:

`aryhidayattt/dsm`

Stable branch:

`main`

Active development branch:

`dev`

The `dev` branch is the canonical current development state.

External AI chat memory, old ZIP packages, and `/home/claude/run` are supporting history only when they conflict with the repository.

---

## Current Status

- Initial DSM development baseline has been committed and pushed.
- ChatGPT GitHub integration is connected and can read/write the private DSM development repository.
- Shared AI context is stored in Git so Claude, ChatGPT, and human development can stay synchronized.
- Local secrets are separated from tracked configuration.
- `config.local.php` is ignored by Git.
- Safe example configuration exists in `config.local.example.php`.
- Development repository remains separate from Amin's final repository: `amienramdhani/dsm_app`.
- Claude's previous final handoff has been reconciled against the current `dev` code and current approved decisions.

- 2026-10-19: Fase 3 langkah 2 selesai di kode (BELUM TERUJI): `lib/tx_effective.php` + panggilan di `publish_`. Rancangan Phase 3 disetujui user. Berikutnya langkah 3 (tabel pengecualian + menu KOREKSI TRANSAKSI). Transfer masih menulis ulang partisi ST sampai langkah 5.

---

## Current Workflow

Development:

DSM local / AI development
→ `aryhidayattt/dsm`
→ branch `dev`
→ test / review

After approval:

`dev`
→ `main`
→ final review
→ transfer/finalize into Amin's `dsm_app` repository.

Do not use the old numbered ZIP-package flow as the canonical development workflow anymore.

---

## Shared AI Context

Read in this order before meaningful development work:

1. `CLAUDE.md`
2. `doc/ai/PROJECT_RULES.md`
3. `doc/ai/DECISIONS.md`
4. `doc/ai/CURRENT_STATE.md`
5. `doc/ai/HANDOFF.md`
6. `doc/ai/CHANGELOG_AI.md`

For Claude specifically, also read:

`doc/ai/CLAUDE_SYNC.md`

---

## Important Current Reconciliation Findings

### Telemarketing KPI UI

Approved decisions are ahead of some current UI code.

Latest approved direction requires:

- one indicator per row,
- active/inactive indicator,
- Actual source,
- KPI parameters,
- weight,
- interactive progress toward 100%,
- `Salin ke TM lain`,
- no dominant internal IDs,
- no generic `Aksi` button as the main drill-down pattern.

Current code still contains some older patterns, so the existing UI must not be treated as the final approved design.

### KPI Validation

Latest approved Telemarketing validation structure is:

- subordinate/other TM detail panel,
- current TM's own validation panel,
- compact one-row KPI information,
- Target / Actual / Achievement / Score where relevant,
- `Rincian` drill-down,
- dealer counted/unmet/reason/Sales owner where relevant.

### Notification permission mismatch

Older handoff says all roles may send notifications within scope.

Current code gives Sales and Telemarketing no recipient scope.

This is a known code/requirement mismatch and must not be silently assumed resolved.

### Config architecture

Older instructions referring to secrets inside tracked `config.php` are obsolete.

Tracked `config.php` loads ignored `config.local.php`.

Never commit secrets.

### AI Assistant

Current repository contains a substantial Gemini-based read-only AI Assistant backend. Runtime/completeness still requires validation, but the repository is materially ahead of the older handoff description.

---

## Important Safety

Do not commit:

- `config.local.php`
- API keys
- DSM PEPPER
- credentials
- passwords
- private keys
- tokens

`storage/cache` currently remains versioned because the deployment may depend on generated JSON files.

Do not remove it from Git until the runtime/deployment dependency is confirmed.

---

## Current Priority

Before new large DSM changes:

1. use the current `dev` branch as baseline,
2. preserve approved decisions,
3. do not restore older Claude-session UI where newer decisions exist,
4. implement only requested changes,
5. update AI documentation after meaningful work.

---

## Pending

- Continue DSM development from `dev`.
- Reconcile implementation gaps against the latest approved Telemarketing KPI/validation design when the user requests coding.
- Validate notification runtime/permissions on the actual environment when requested.
- Update this document after meaningful development sessions.

---

## Sesi 2026-10-19 - Fase 1 revisi (Claude)

Rencana kerja 3 fase (disetujui user): Fase 1 = Bug #1-#5 + Request #1-#5 + #10-#12; Fase 2 = Alihkan Portofolio / Transfer Sales (#6-#9); Fase 3 = Koreksi Transaksi + Raw/Effective Sales (#13-#19). Transfer ke `amienramdhani/dsm_app` hanya setelah SEMUA fase lolos uji dan atas perintah user. `main` tidak disentuh. `backup-dev-20261018` hanya referensi.

Selesai dan dikonfirmasi user di lokal: Bug #1 (sisi Admin), #2, #3, #4, #5, Request #1 (lihat CHANGELOG_AI 2026-10-19).

Berikutnya di Fase 1: Request #10 -> #11 -> #12, lalu Request #2-#5. Aturan kerja: satu bug/request = satu commit, tandai BELUM TERUJI bila belum dites, bump versi cache loader sekali per perubahan logis, role harus divalidasi di frontend DAN backend.

Fakta kode yang perlu diingat:
- Loader aset Admin ada di assets/searchable.js (rantai portfolio_transfer -> dealer_transfer_direct -> transfer_ui_hotfix -> admin_revision -> ...), versi cache dibump manual di sana.
- Transfer Dealer native: `PAGE==='transfer'` -> `transferDealerPage()` -> `window.dsmDealerTransfer.openPage('dtdPageApp')`. Panel Transfer Dealer lama di accordion Admin (`injectAdmin()`) masih ada sampai Request #10.

## Update 2026-10-19 (Phase 1)
- Request #10/#11/#12 selesai di kode (BELUM TERUJI di app nyata). Approval terpusat = panel Admin "Approval" (group `ap`). Sisa Phase 1: Request #2-#5.
- Request #2-#5 diimplementasi (BELUM TERUJI). Phase 1 kode selesai; tinggal tes user per role. Berikutnya Phase 2 (#6-#9).


## Phase 3 langkah 3+4 (2026-10-19)

- Menu Koreksi Transaksi ditambahkan (grup Lain, Admin/Super Admin). BELUM TERUJI di app nyata.
- Berikutnya: langkah 5 (pindah 6 raw reader ke resolver, hentikan rewrite ST, export lineage), lalu Target SO per dealer, lalu QA #20.

## Phase 3 langkah 5 (2026-10-19)

- Reader ST mentah memakai `txEffRaw_`; transfer tidak lagi menulis ulang partisi ST; export RAW ST punya kolom lineage. BELUM TERUJI.
- Perlu dites: Transaksi/export/preview setelah transfer, upload ST ulang, publish dengan TXEFF_OFF=1.
- Berikutnya: Target SO per dealer, lalu QA #20. `sliceCmp` override masih ada.

## Target SO per dealer · Tahap A (2026-10-19)

- Menu Target SO + upload Excel + tinjau read-only. Skor KPI belum berubah. BELUM TERUJI.
- Tahap B (belum): skor Σ min(aktual, 1,2×target dealer)/Σ target; target ikut pemilik dealer per tanggal (pro-rata hari); fallback `_kk_target`; transfer tidak memindahkan target manual untuk dealer bertarget.

## Target SO Tahap B (2026-10-19)

- Skor SO KPI Khusus memakai target per dealer (lihat CHANGELOG_AI). Transfer dealer tidak memindahkan target dealer (mengikuti kepemilikan per tanggal); `_kk_target` manual tetap dipakai hanya jika subjek tidak punya dealer bertarget.
- Berikutnya: QA #20 lintas role/desktop/mobile; keputusan `sliceCmp`.


## Mobile kit (2026-10-19)
- `assets/mobile_kit.css` = kulit compact HP untuk Admin (Akun pengguna, Cari/Tambah dealer, Approval, form user), Koreksi, Target SO, Transfer Dealer. Presentasi saja. Perlu diuji user di HP nyata per role (Super Admin vs Admin wilayah). Desktop Approval disetujui (sudah memakai tampilan desktop yang ada); desktop user/dealer belum disetujui.

## Request dicatat - padatkan tabel desktop (2026-10-19, BELUM DIKERJAKAN)
- User minta tabel desktop berikut dipadatkan (baris lebih rendah, kolom lebih rapat, kurangi scroll). Hanya dicatat, belum ada kode; tunggu mockup/go-ahead.
- 1) Early Warning - Sell Thru (ASM): baris ~50px dengan sub-baris kecil (RGM · sales, region induk), bar Ach vs time gone, proyeksi + pill, tren 7 hari, tombol Detail.
- 2) Pencapaian Tim - ASM: baris ~50px (NAMA / RGM dengan sub-label "RGM" / REGION), area tabel tinggi terbatas dengan baris Total sticky.
- Berlaku desktop saja; HP tetap existing. Tabel berwarna yang sudah disetujui jangan disentuh. Terkait rencana gaya "Desktop 1" untuk tabel default (menunggu jawaban soal badge Ach dan kolom pertama sticky).
- 3) Tabel grup "Tim <ASM>" (kolom SALES / REGION, baris Total; klik tim membuka sales di bawahnya): user minta dirapikan. Tambahan request: saat satu tim dibuka, tim lain dibuat samar (dim/redup) supaya fokus ke tim yang terbuka. Hanya dicatat, belum ada kode. Fokus-samar murni presentasi (CSS + toggle kelas), tidak mengubah data/total; perlu cek apakah beberapa tim boleh terbuka bersamaan. Desktop dulu; HP tetap existing.

## Tabel padat desktop (2026-10-19)
- `assets/table_dense.css` (dimuat loader `searchable.js`, hanya >=761px; HP tidak berubah): (1) tabel DEFAULT (header biru muda) -> gaya Desktop 1 (header gradient, zebra, hover, baris 34px, baris total); keluarga tabel berwarna dikecualikan lewat daftar `:not(...)` + `:has(th.v7bh/.metricHead/.grp/...)`. (2) Early Warning satu baris per ASM (sub-info inline, 32px). (3) Pencapaian Tim ASM: baris 32px, label RGM inline, warna kolom tetap. (4) Perbandingan Tim (`.v6gttable`): grup tertutup meredup saat ada grup terbuka (CSS `:has`, tanpa JS; beberapa grup boleh terbuka sekaligus seperti sebelumnya).
- Tidak ada perubahan data/logika/total. Badge Ach TIDAK ditambah (tidak ada batas baru); kolom pertama sticky tidak diubah.
- Diuji hanya di fixture CSS (desktop 1440 + HP 390: tabel HP identik). BELUM diuji di app asli per role; cek tabel berwarna lain yang mungkin lolos pengecualian (jika ada tabel berwarna ikut berubah, tambahkan kelasnya ke daftar `:not` di file itu).

## Request dicatat - Beranda HP (2026-10-19, BELUM DIKERJAKAN)
- Mockup "Mockup beranda dan popup libur" board A (hero tipis) DISETUJUI hanya bagian ATAS: hero gradient (sapaan + avatar inisial + lonceng + angka Sell Thru bulan ini + time gone/sisa hari kerja) dan strip kartu kecil yang menimpa hero (Proyeksi, Dealer aktif, Early Warning, bisa digeser).
- Bagian BAWAH beranda (jalan pintas, perlu perhatian, dll di mockup) TIDAK dipakai: biarkan seperti beranda existing.
- Popup selamat berlibur (board C) dari tabel `Hari_Libur`: sekali per hari libur; status persetujuan belum dikonfirmasi user, jangan dikerjakan dulu. Peta/koordinat dealer: di-skip.
- Mockup baru dicatat, belum ada kode. Perlu go-ahead sebelum coding; HP saja, desktop beranda belum disetujui.
- UPDATE (user setuju): popup selamat berlibur (board C) DISETUJUI. Sumber tanggal/nama dari tabel `Hari_Libur`; muncul sekali per hari libur per perangkat (diingat di browser); isi: tanggal, "Selamat [nama hari libur]", catatan hari tidak dihitung sebagai hari kerja, tombol "Terima kasih". Belum dikerjakan, tunggu go-ahead. Peta dealer tetap di-skip.
- UPDATE hero beranda HP (user pilih): hero IKUT JAM buka app (pagi 05-10, siang 10-15, sore 15-18, malam 18-05; langit/matahari/bulan/bukit + sapaan), TANPA ikon wajah/mood. Kalau penjualan jelek (laju Ach vs time gone < 40%, batas dari user) muncul animasi HUJAN deras di hero. Mockup: "Mockup hero waktu dan mood" board 1. Detail hujan (tingkatan, hari awal bulan netral, reduced-motion) belum diputuskan; belum dikerjakan.

## Request dicatat - Early Warning (2026-10-19, BELUM DIKERJAKAN)
- Kolom nama di Early Warning tidak perlu menampilkan ATASAN (sub-baris "RGM <nama> · n sales"). Minimal: untuk tingkat RGM jangan tampilkan atasan (Head of Sales). User belum memutuskan apakah hilang total untuk semua tingkat; tanya ulang saat coding.
- Warna teks tingkat ASM dan RGM dibedakan (belum ditentukan warna dan bagian mana: nama saja atau juga sub-label). Hanya presentasi, jangan ubah data/urutan. Terkait pemadatan Early Warning yang sudah dikerjakan di `assets/table_dense.css` (sub-info saat ini inline, 32px).

## Request dicatat - tinggi baris & bold tabel (2026-10-19, BELUM DIKERJAKAN)
- "Pencapaian per Indikator" (tingkat SALES, per brand): baris terlalu tinggi (nama + region 2 baris, ~50px). User minta dipadatkan seperti tabel lain (nama dan region satu baris/inline atau tinggi lebih rendah). Header berwarna/hijau per brand jangan diubah.
- Tabel "Perbandingan Tim" (`.v6gttable`, baris "Tim <nama ASM>" + kolom Region): teks nama tim dan region jangan bold (font-weight normal/medium). Baris Total boleh tetap tebal (belum dikonfirmasi). Hanya presentasi.

## Keputusan hero beranda HP (2026-10-19, BELUM DIKERJAKAN, tunggu "gas")
- Referensi user: file `dsm_mobile_hero_clean_kilat.html` (5 gambar WebP: pagi, siang, sore, malam tanpa orang, badai). Gambar dipakai persis, diperkecil ke ~640px (~20-30 KB/gambar), lazy per waktu. Hanya HP, desktop tidak disentuh, bagian bawah beranda tetap existing.
- Pace = capaian Sell Thru / Time Gone (hanya Sell Thru).
- 4 tingkat: >=70% aman (gambar sesuai jam), 50-69%, 30-49%, <30% (makin gelap dan kilat makin sering). Batas persis (apakah 70 masuk aman) dan gambar tiap tingkat belum final.
- Hari awal bulan: 5 hari kerja pertama selalu tampil normal (bukan 3).
- Chip teks suasana boleh, emoji ikon di kartu ringkasan dibuang. Tanpa garis petir; kilat hanya flash lembut, patuhi prefers-reduced-motion. Malam: toko menyala dan tanpa orang (sudah oke).
- Popup selamat berlibur dari `Hari_Libur` tetap langkah terpisah.

## Hero Beranda HP - DIKERJAKAN (2026-10-19)
- File baru: `assets/mobile_hero.js`, `assets/mobile_hero.css`, `assets/hero/{pagi,siang,sore,malam,badai}.webp` (640x400, 11-16 KB). Dimuat lewat `assets/searchable.js` (CSS `?v=20261019a`, JS `?v=20261019a`). `index.php` TIDAK diubah.
- Cara kerja: MutationObserver pada `#main`; saat `PAGE==='beranda'` dan `V6X.data` ada (bukan drill), sisipkan `#dsmHero` sebagai anak pertama `#view`. Data dari V6X tanpa request baru: node `N` (level RGM ke atas) atau `O` (ASM/Sales), SELL THRU. Pace = (aktual/target) / (hari kerja berjalan/total hari kerja).
- Tingkat: >=70 aman (gambar sesuai jam: pagi 05-10, siang 10-16, sore 16-19, malam lainnya); 50-69 t1, 30-49 t2, <30 t3 = gambar badai (malam tetap gambar malam) makin gelap, kilat flash tiap 8-12 / 5-8 / 3-5 detik. Tanpa target atau hari kerja berjalan <=5: tampil normal, pace tidak ditampilkan.
- Hanya HP (`max-width:760px`); desktop `display:none`. Bagian bawah Beranda existing tidak diubah, kartu ring existing tetap ada. Avatar/bell referensi tidak dipasang karena topbar existing sudah ada. Reduced-motion: kilat tetap jalan dengan durasi lebih lembut.
- Diuji di fixture headless (HP + desktop, 4 tingkat, 5 hari awal, tanpa target). BELUM diuji di app asli: perlu QA Beranda HP per role (RGM ke atas, ASM, Sales), Ctrl+F5.
- Belum: popup selamat berlibur (langkah terpisah).
- Revisi hero (2026-10-19, setelah QA user): hero dibuat FULL-BLEED (lebar penuh, mepet atas, sudut bawah membulat 26px), sisipan pindah ke awal `#main`. Saat hero aktif, `#main` diberi class `dsmHeroOn`: judul "BERANDA" (`.ptitle`) dan baris MTD/LMTD (`.psub`) disembunyikan HANYA di Beranda HP; padding atas `#main` 0. Panel "ST belum lengkap" tetap tampil di bawah hero. Versi aset `20261019b`. Hero dilepas (class dihapus, judul kembali) saat drill-down atau halaman lain.
- Font hero (2026-10-19): hero HP memakai Plus Jakarta Sans (OFL), self-host `assets/hero/pjs-600.woff2` dan `pjs-800.woff2` (~12 KB each, subset latin), hanya untuk `#dsmHero` (font app lain tidak berubah). Nama 19px/800, pace 28px/800. Versi aset `20261019c`.
- Hero sticky (2026-10-19): hero `position:sticky` dengan `top = tinggi topbar - setengah tinggi hero`, jadi saat scroll naik sampai tersisa 50% tinggi lalu menempel di bawah header (tanpa layout shift). JS `geo()` menghitung `--hsTop`, `--hsHalf`, `--k` (0..1); sapaan turun mengikuti, chip suasana memudar. Diuji di fixture; sticky bisa gagal di app asli bila ada ancestor ber-overflow (tidak ditemukan di CSS). Versi aset `20261019d`.
- Pertanyaan terbuka: bar "Nasional / n data Sales + Filter" (`.subbar`) dan panel "ST belum lengkap" di Beranda HP, user bilang "khusus beranda ini juga ga perlu"; belum diputuskan karena Filter dipakai untuk ganti cakupan/periode.
- Filter Beranda HP (2026-10-19, keputusan user): bar `.subbar` ("Nasional / n data Sales" + tombol Filter) disembunyikan di Beranda HP (`body.dsmHeroPage`). Inisial di kanan atas (`#avatarTop`) jadi tombol (role=button, titik filter kecil): ketuk membuka popover berisi crumb + select Cakupan/Periode (`.subbar.hsOpen`, fixed di bawah topbar). Tutup saat ketuk di luar, Esc, atau setelah ganti pilihan. Halaman lain/desktop tidak berubah. Versi aset `20261019e`. Panel "ST belum lengkap" belum disentuh (menunggu keputusan user).
- Tab Beranda sticky (2026-10-19): `.v7btabs` (RINGKASAN/WILAYAH/TREN/TIM & KPI) di Beranda HP saat hero aktif memakai `top = topbar + setengah tinggi hero + 6px` supaya menempel DI BAWAH hero dan tidak overlap (var `--hsTop`/`--hsHalf` juga diset di `#main`). Versi aset `20261019f`.

## Mockup disetujui, tunggu "gas" (2026-10-19, BELUM DIKERJAKAN)
- Beranda HP: tab `.v7btabs` pakai alas solid warna halaman sampai menempel ke hero supaya konten tidak tembus di celah atas/kiri/kanan tab. Mockup: "Mockup Lainnya dan Tab".
- HP: Cakupan dan Periode dipindah ke menu Lainnya (`openMobileMore`), kartu "Cakupan & Periode" paling atas, 2 baris ketuk (select native). Titik filter di inisial kanan atas dibuang (popover `.subbar.hsOpen` diganti).
- Chip "Nasional · Sep 2026" di hero: belum diputuskan.
- Keputusan lanjutan Beranda HP (2026-10-19, BELUM DIKERJAKAN): chip "Nasional · Sep 2026" di hero TIDAK dipasang. Baris "Data lengkap s.d. ..." di header atas disembunyikan di Beranda HP (user: tidak perlu ditampilkan; usulan: tampilkan di kartu Cakupan & Periode di Lainnya). Peringatan "ST belum lengkap" tetap hanya untuk Admin/Super Admin (sudah demikian di kode), di bawah hero.

## Paket "gas semua" (2026-10-19) - DIKERJAKAN, perlu QA user
1. Beranda HP: tab `.v7btabs` tidak tembus (band fixed `#dsmBand` solid di belakang hero+tab, z 15; hero z 25). Cakupan dan Periode pindah ke kartu `.hsCard` di menu Lainnya (select asli diteruskan ke `#scopeSelect`/`#periodSelect`, ditambah "Data lengkap s.d."). Baris "Data lengkap" di topbar HP disembunyikan saat Beranda (`.v7status .v7stx`, tombol perbarui tetap). Popover filter avatar dibuang. Font hero/popup: Inter self-host (`assets/hero/inter-600/800.woff2`), pengganti Plus Jakarta Sans. Aset `mobile_hero.* ?v=20261019h`.
2. Popup selamat berlibur: `assets/holiday_popup.js` (`?v=20261019b`). Sumber `BOOT.v6hol` + nama dari `BOOT.v6holn` (BARU: `apiV6Boot` di `lib/api_analisa.php` menambah `holn` dari kolom KETERANGAN; `index.php` start() menyimpan `BOOT.v6holn`). Sekali per tanggal per perangkat (localStorage `dsmHolSeen`). Hanya tanggal di Hari_Libur (Minggu biasa tidak memicu). Judul "Selamat <KETERANGAN>"; jika KETERANGAN tidak diawali kata seperti Hari/Idul/Natal dst, jadi "Selamat Hari Libur <KETERANGAN>".
3. Early Warning: `v6EWSub()` baru di `index.php`; tingkat RGM tanpa atasan (hanya "n sales"); sub-baris atasan ASM berwarna indigo `#4B55D6`, RGM cokelat-oranye `#B45F06` (`table_dense.css`, desktop+HP). Tingkat lain tetap menampilkan atasan.
4. Pencapaian per Indikator (`#brandIndicatorRows .grouped`): baris 34px, region inline, growth (`.v7delta`) satu baris. Desktop saja (`table_dense.css ?v=20261019d`); header berwarna tidak disentuh.
5. Perbandingan Tim: nama tim dan region (`td.l` di `tr.v6gtasm`) font-weight 500; angka dan Total tetap tebal.
- Diuji di fixture headless (hero/band/Lainnya/popup). Tabel (3,4,5) belum diuji visual di app asli.
- Topbar HP (2026-10-19, permintaan user): ikon inisial `#avatarTop` disembunyikan di semua halaman HP (tidak berfungsi). Di Beranda HP topbar dibuat TRANSPARAN di atas hero (`body.dsmHeroPage .topbar` relative, background transparan, margin-bawah negatif setinggi topbar) sehingga gambar hero mulai dari paling atas layar; sapaan/chip digeser turun setinggi topbar (`--hsT`); saat scroll topbar ikut hilang dan hero 50% menempel di y=0, tab sticky tepat di bawahnya. Tombol perbarui `.v7refresh` jadi kaca putih transparan. Aset `mobile_hero.* ?v=20261019i`. Halaman lain: topbar putih sticky seperti semula.
- Koreksi (2026-10-19): ikon inisial `#avatarTop` disembunyikan HANYA di Beranda HP (`body.dsmHeroPage`), bukan semua halaman. `mobile_hero.css ?v=20261019j`.
- Keputusan final (2026-10-19): ikon inisial `#avatarTop` disembunyikan di SEMUA halaman HP (tidak berfungsi). Desktop tidak berubah. `mobile_hero.css ?v=20261019k`.
- Fix (2026-10-19): ikon inisial HP masih muncul karena `index.php` baris ~2673 (`#app .topbar .who .avatar{display:flex!important}`) lebih spesifik; selector disembunyikan diperkuat jadi `html body #app .topbar .who #avatarTop`. `mobile_hero.css ?v=20261019l`. Tabel Dealer berheader lama: ditunda user ("ntar aja").

## Disetujui, tunggu "gas" (2026-10-19, BELUM DIKERJAKAN)
- Tab Beranda HP (`.v7btabs`): gaya B (teks + garis gradasi bawah tab aktif) di atas alas navy lembut (`#1B2A5E`->`#23326C`, sudut bawah 22px) yang menyambung ke hero, sama untuk semua suasana hero. Mockup: "Mockup Tab Navy". Menggantikan tab kotak putih sekarang.
- Ide terbuka: background aplikasi HP bernuansa navy seperti ilustrasi malam; cakupan (Beranda saja atau semua halaman HP) dan bentuk (gradasi atas atau penuh dengan kartu putih) belum diputuskan.

## 2026-10-19 — Tab & latar Beranda (disetujui "gass")
- Baru `assets/beranda_style.css` (dimuat via loader searchable.js). HP: tab `.v7btabs` gaya B di alas navy lembut (#1B2A5E→#23326C), `#dsmBand` disembunyikan. Desktop: header biru pucat + tab garis-bawah gradien, hanya Beranda (`body.dsmBerandaPage` di-set `mobile_hero.js`).
- Aturan top tab di mobile_hero.css dihapus (dipindah ke beranda_style.css). Belum di-QA di app asli (Ctrl+F5). Dealer table old-header masih ditunda.
- 2026-10-19 (lanjut): latar biru pucat desktop berlaku di SEMUA halaman; tab Beranda desktop jadi navy seperti HP (beranda_style.css v20261019b). Tab halaman lain (v6tabs dll) belum diubah.
- 2026-10-19: tab aktif Beranda (HP+desktop) diberi latar gradien biru-ungu transparan agar beda dari tab lain (beranda_style.css v20261019c).
- 2026-10-19: tabel Daftar Dealer (#customerRows) dikecualikan dari rule 'tabel default' di table_dense.css (header brand berwarna kembali seperti main). Tabel lain yang berwarna sendiri perlu dicek user bila ada yang ikut berubah. v20261019e
- 2026-10-19: Early Warning: sub-teks Region diberi jarak 10px + warna abu (table_dense v20261019f).
- 2026-10-19: sub-teks inline (EW, Pencapaian Tim ASM, per Indikator) diberi strip pemisah '–' (table_dense v20261019g). Tabel lain berpola sama belum ada yang ditemukan; user akan kirim jika ada.
- 2026-10-19: Perbandingan Tim: grup yang dibuka diberi garis hitam pekat atas (baris tim) & bawah (sales terakhir) via box-shadow (table_dense v20261019h). Belum dites di data asli.
- 2026-10-19: Perbandingan Tim: prefix 'Tim ' dihapus dari baris grup level SALES (index.php gName); prefix 'RGM ' (level ASM) dibiarkan.
- 2026-10-19: Rincian Mingguan per Type · ST/SO (weeklyPanels): baris dikelompokkan per model (regex pecah RAM/storage), induk = jumlah varian, awal tertutup, klik/Enter buka varian; model 1 varian tampil 1 baris biasa; TOP ST di level grup; handler delegasi click/keydown + CSS di table_dense.css (v20261019i). Total baris tidak berubah. Diuji hanya terisolasi (node), belum di data asli.
- 2026-10-19: weeklyPanels: teks 'N varian' dihapus; model tanpa varian (tr.stsoOne) nama navy #1B2A5E (table_dense v20261019j).
- 2026-10-19: Summary Pencapaian per Brand dipadatkan (growth sebaris, baris 36px) via selector .grouped:has(button.textlink[data-brand]) di table_dense v20261019k; belum dites di data asli (selector :has tidak diuji pada tabel ini).
- 2026-10-19: Transfer Dealer (.dtdTable) dipadatkan: baris 34px, nama – kode sebaris (table_dense v20261019l).
- 2026-10-19: Summary Pencapaian per Brand desktop: 'Target / Aktual' dipisah jadi 2 kolom via assets/summary_split.js (DOM, idempoten, >=761px, hanya tabel ber-button.textlink[data-brand]) + CSS taSplit di table_dense v20261019m. Diuji di fixture (header colspan +1, Total ikut). HP tidak berubah. Tabel lain yang pakai targetActual (Pencapaian Tim ASM/per Indikator) sengaja belum.
- 2026-10-19: growth sebaris (flex-direction:row) di Summary per Brand & Pencapaian per Indikator; sebelumnya kalah oleh index.php:2767 (.v7delta column). table_dense v20261019n.
- 2026-10-19: Transfer Dealer: urutan kode dulu lalu nama ('017.110 – 321 CELL'), dealer_transfer_direct.js rowHtml swap + CSS ::after (v20261019m/o).
- 2026-10-19: Matriks Dealer × Brand: .v6csTable dikecualikan dari rule tabel default (header brand berwarna seperti main); 'unit/bln' dipindah jadi kolom baru 'Unit/bln' setelah Dealer (v6CrossHtml). table_dense v20261019p. Belum dites di data asli.
- 2026-10-19: Matriks Dealer × Brand: kode dealer (d.id) di depan nama dengan strip (.dcode, table_dense v20261019q).
- 2026-10-19: Dealer Baru · Per Sales: kolom Sales & Region dipisah (Region – Big sebaris, .v6dbS), baris tabel default padat. table_dense v20261019r.
- 2026-10-19: semua header tabel vertical-align:middle (table_dense v20261019s).
- 2026-10-19: transactionTable: sub-teks (.txSub) SJ–perusahaan & Dealer–kode sebaris + strip di desktop (table_dense v20261019t). Header gradient tabel ini belum dikecualikan (tunggu user).
- 2026-10-19: header tabel middle diperkuat (specificity 3 id, lawan rule vertical-align:bottom di index.php 945/3354/3411/3496); Transaksi: kode dealer di depan nama (.txCode). table_dense v20261019u. Penyebab header 'masih bawah' pada tabel Transaksi belum terbukti di browser.
- 2026-10-19: Sisir semua tabel desktop: assets/cell_inline.js (>=761px) — td dengan small.cellsub terakhir: kode (regex) dipindah ke depan nama + strip ('320.439 – ALDIO CELL'), sisa/sub pendek (<=36 char) sebaris dengan strip; sub panjang tetap bertumpuk. Dilewati: .v6ewTable,#teamRows,#brandIndicatorRows,.v6dbS,.dtdTable,.v6csTable,.v6gttable,.mobileCard, .txSub/.ewAsm/.ewRgm. CSS ciCode/ciIn di table_dense v20261019v. Diuji di fixture kecil (urutan benar, HP 0 perubahan); belum di data asli — user perlu cek tiap tabel (Dealer+Type, Daftar tutup, Assignment, Akun pengguna, dst).
- 2026-10-19: Dealer + TYPE desktop: kolom 'Sales / Wilayah' dipecah jadi 'Sales' dan 'Wilayah' via cell_inline.js splitSW (v20261019b). HP tidak berubah. Diuji di fixture.
- 2026-10-19: Form Catat Order popup dealer (HP <=760px) jadi BERTAHAP (pilihan user B, disetujui 'oke'): assets/telemarketing/tm_steps.js + tm_steps.css, dimuat di index.php setelah tm.css / tm_popup.js. Lapisan tampilan di atas tmDrawOrder: stepper navy 4 langkah, accordion 01-04 (satu terbuka, lainnya ringkasan satu baris), tombol 'Lanjut →' per bagian, bar aksi (Simpan order/Simpan draft/Pratinjau) dipindah ke dasar form (elemen sama, handler tetap), #tmOrderError dipindah ke bar. Simpan/draft/pratinjau tetap bisa kapan saja. Field tetap di DOM (hanya display:none) sehingga tmCollectOrder & validasi tetap; event 'invalid' otomatis membuka bagian yang bermasalah. Desktop dan halaman order non-popup tidak disentuh. Diuji di fixture (stepper, toggle, invalid->buka bagian); BELUM diuji di data/akun asli: perlu QA Simpan order, Simpan draft, Pratinjau PDF, tambah/hapus barang, ganti mode dealer (redraw), dealer NOO.
- 2026-10-19: tm_steps.css: padat — field 38px, kartu barang HP jadi grid ringkas (judul+hapus satu baris, Brand|Warna, Nama, Qty|Harga, Subtotal satu baris). Belum dilihat di data asli.
- 2026-10-19: Daftar Dealer (#customerRows/#customerTableRows) dikecualikan dari cell_inline.js; tampilan nama + 'kode · kota' bertumpuk dikembalikan sesuai permintaan user. cell_inline v20261019c.
- 2026-10-19: tombol 'Tandai/Ajukan dealer tutup' (.v6tban.plain>.chip[data-v6t=form]) merah gradien & full width, desktop+HP (beranda_style v20261019d).
- 2026-10-19: tombol '+ Catat order' (#tmDealerOrder) full width (beranda_style v20261019e).
- 2026-10-19: detail dealer: .v6tban.plain (tombol tandai/ajukan tutup) dipindah tepat di bawah .tm-dealer-actions (Catat order) via observer di tm_steps.js. Hanya reorder DOM; belum dites di app asli.
- 2026-10-19: HP: font tabel dikecilkan (td 11px, th 9.5px, cellsub 9.5px, padding 6-7px) untuk semua .table-scroll table kecuali .tm-order-table (table_dense v20261019w). Belum dilihat di HP asli; tabel dengan font khusus mungkin perlu dikecualikan.

## 2026-10-20 — Splash animasi logo
- Splash lama (ombak, `v6Splash` inline) dihapus dari index.php; diganti `assets/dsm_splash.js` (CSS+SVG inline di dalam JS, dimuat setelah tm_steps.js).
- Perilaku tetap: sekali per sesi (sessionStorage `v6splash`), "Lewati ›"/ketuk untuk skip. Reduced-motion: logo statis 1,2 dtk. Uji paksa: tambahkan `?splash=1` di URL.
- Logo: `assets/dsm-logo.svg` + `assets/dsm-logo.png` (transparan, hasil trace dari JPG). `navBrand`, `dl-logo`, dan satu img lain di index.php kini pakai PNG. JPG lama dibiarkan (belum dihapus).
- Belum diverifikasi di app nyata: CSS `.navBrand` ber-background putih di beberapa rule; cek header/login (Ctrl+F5). Rollback: kembalikan src ke dsm-logo.jpg / dsm-logo-crop.jpg.

## 2026-10-20 — Navbar biru lembut
- Sidebar desktop (.topbar ≥761px) dan topbar HP selain Beranda: gradien #E3EDFD→#C5D6F4 (blok di akhir assets/beranda_style.css). Beranda HP tetap transparan di atas hero. Belum dicek di app nyata (item aktif/hover/ikon di atas warna baru).
- Update: #mBottom dan .mmPanel (HP) ikut gradien biru; garis tree desktop digelapkan dari --v7tl per grup. Belum dicek di app nyata.
- Update: panel drawer HP kembali putih (hanya bar bawah + topbar biru). Ikon Brand di menuIcon() jadi ikon ponsel.

## 2026-10-20 — Cakupan HP
- scope_picker.js (dimuat setelah dsm_splash.js): ≤760px, #scopeSelect pointer-events:none, ketuk area select/label membuka sheet berkelompok; pilih → set value + dispatch 'change'. Diuji di fixture saja; cek di app nyata (periodSelect tidak diubah).
- Fix: Cakupan HP yang dilihat user ada di kartu drawer 'Lainnya' (.hsSel[data-for=scopeSelect], klon dari mobile_hero.js); scope_picker kini menangkap itu juga dan menutup drawer setelah pilih.
- Sheet Cakupan HP kini punya kolom cari (scope_picker.js): cocok di nama atau judul grup; grup tanpa hasil disembunyikan.

## 2026-10-20 — Chip Mode Eksekutif HP
- CSS-only di akhir beranda_style.css, selektor :has(>button[data-v6xbrand]) / [data-v6xind], ≤760px. Desktop tidak diubah. Belum dicek di app nyata.

## Catatan 2026-10-20 (halaman beku)

- Penyebab freeze: loop MutationObserver di `assets/transfer_ui_hotfix.js` (`addStyle()` menulis `textContent` tiap panggilan). Sudah diperbaiki (tulis sekali). Aturan: jangan ubah DOM/`textContent` di dalam callback MutationObserver tanpa guard.
- Cara uji freeze: muat `index.php` di Chromium headless, halaman harus `load` < 1 dtk; hitung callback MutationObserver (harus puluhan, bukan ratusan).
- Splash baru (`assets/dsm_splash.js`) bukan penyebab; di branch `rollback-splash` splash dikembalikan ke versi lama. Keputusan pakai splash baru atau lama menunggu user.

## Catatan 2026-10-20 (performa Dealer HP)

- Pola berat yang dihindari: jangan bangun tabel/DOM untuk seluruh hasil filter di HP; render lazy + bertahap. Daftar Dealer HP = 20 kartu; tabel lengkap hanya saat panel dibuka (200 baris per langkah).
- Jangan memakai `localeCompare` dengan opsi di dalam comparator sort; pakai `compareValues` (Intl.Collator tunggal).
- Uji performa: mock `api.php` di Playwright memakai data `storage/cache/*.json` (hasil `apiDashboard`) digandakan jadi 7.000 dealer; ukur render dan freeze terpanjang (heartbeat 50 ms).
- Belum dikerjakan (butuh persetujuan karena menyentuh visibilitas data): kirim `alamat`/`hp` dealer hanya saat detail dibuka untuk memangkas payload (~1,4 KB/dealer).
- `storage/cache/*.json` (313 file, 256 MB) ikut ter-commit lewat commit "update" 14:21; pertimbangkan masuk `.gitignore`.


## Heatmap HP: ketuk kotak -> rincian (2026-10-20)
- Mockup #2 dipilih user; layout/tampilan heatmap tetap seperti sebelumnya (kompak/Per Brand/landscape TIDAK dikerjakan).
- `.hc` non-kosong membawa `data-hi/hm/hq/ha/hl/ht/hj` (indikator, mode, nilai tampil, aktual, LMTD, target, proyeksi). Region diambil dari `.rn` sebelumnya, brand dari `.hd` (urutan kolom) / `data-v6xb`.
- Listener klik fase-capture hanya saat `isMobile()`; `v7HeatPass` melewatkan klik programatik dari tombol "Lihat dealer" (set `V6X.brand` lalu klik `.rn` -> drill lama + cek `ownBig`). Sheet ditempel ke `document.fullscreenElement||body` supaya tampil di layar penuh; ditutup saat fullscreenchange/Esc/backdrop.
- Hati-hati: class `.bar` sudah dipakai CSS global lain (bar jadi bergeser); sheet memakai `.v7bar`.
- Catatan uji: heatmap HP hanya terlihat di layar penuh dan pada tab `data-v7tab` yang sesuai; uji Playwright: /tmp/heat_test.py (tidak di repo).

## Toolbar heatmap tanpa label (2026-10-21)

- Permintaan user: hapus tulisan INDIKATOR dan NILAI; indikator di ujung kiri, nilai di ujung kanan. Perubahan hanya CSS di index.php (sekitar baris 3033): hapus dua aturan ::before, tambah margin-right:auto pada .v6xmetricbar dan margin-left:auto pada .v6xmodebar (perlu !important karena aturan lama .v6xhmbar margin-left:0!important).

- Uji mockup Playwright dengan CSS asli: lebar 900 -> indikator mulai x=0, nilai berakhir di sisi kanan. Lebar 390 -> toolbar mode nowrap + overflow-x, nilai masih terpotong ~57px dan perlu digeser (belum diubah, butuh persetujuan jika mau dipadatkan).


## Kredit tetap ke penjual — P1 selesai kode, belum diuji (2026-10-27)
- Rencana 4 paket: P1 daftar + resolver (SELESAI KODE), P2 kartu Wilayah ikut region pemilik dealer + chip "dijual sales luar area" + sembunyikan region kosong, P3 filter KREDIT_TETAP di Koreksi, P4 foto bulanan saat kunci periode + layar Riwayat Pemilik (baca-saja). Mockup acuan: dsm-kredit-penjual.html (dikirim ke user, tidak ada di repo).
- P1 menyentuh: `lib/keep_seller.php` (baru), `lib/portfolio_core.php` (skip di `portfolioResolveRows_`), `lib/tx_effective.php` (marker KREDIT_TETAP), `lib/api_txcorr.php` (label), `api.php` (require + whitelist + WRITE_FNS), `index.php` (satu panggilan `dsmKeepSellerPanel` sebelum `foldAdminFeatures`), `assets/keep_seller.js` (baru), `assets/searchable.js` (loader), `assets/admin_revision.js` (grup Pengguna & Akses).
- Tabel `dsm_keepseller` dibuat otomatis saat pertama dipakai dan diisi HOS + BOD (prop KEEPSELLER_SEEDED). Aturan berlaku per bulan: `period_from` s/d sebelum `period_to`. Simpan memotong aturan lama di bulan baru (bulan sebelumnya tidak berubah). Setiap simpan/cabut publish ulang dengan pengaman total company.
- UJI WAJIB di Laragon: (1) buka Admin sebagai Super Admin, panel muncul di Pengguna & Akses; (2) tambah Hendra, cek transaksinya tetap NIK Hendra setelah publish; (3) cabut, cek kembali ke pemilik dealer; (4) Admin biasa tidak melihat panel dan API menolak; (5) total company tidak berubah.
- Belum dicek: apakah Koreksi menghormati kunci periode; COO/CCO ada di daftar role pilihan tetapi tidak aktif default; hasil query cek data (antrean Oktober, NIK transaksi HOS, cakupan dealer-brand) belum dilakukan user.


## P2 Kartu Wilayah selesai kode, belum diuji (2026-10-27)
- Setelah cutover, baris kubus sudah memakai NIK pemilik dealer (Effective Sales), jadi kartu Big Region non-exempt otomatis ikut region pemilik. P2 menambah: mode region penjual kredit-tetap (TERTENTU/PEMILIK), perbaikan pembanding LMTD, data `ext` + chip + popup rincian. Periode sebelum cutover (mis. Maret) TIDAK berubah; itu urusan P4 (foto bulanan).
- Uji wajib: (1) Beranda Super Admin, brand REALME, Sell Thru: Jawa Barat menampilkan chip oranye bila ada transaksi ST_BERBEDA lintas area di Oktober; klik chip, rincian penjual lalu dealer; (2) tambah Hendra dengan region TERTENTU Kalimantan: unitnya pindah ke kartu Kalimantan, target Hendra ikut; (3) mode PEMILIK: unit masuk region dealer dan chip muncul di region itu; (4) akun RGM: chip terlihat di semua kartu tapi dealer hanya untuk area sendiri; (5) angka Nasional tidak berubah.
- Risiko diketahui: `$c['ids']` membatasi baris menurut cakupan akun; chip dari dsm_attr tidak dibatasi cakupan (hanya ringkasan penjual); PEMILIK memanggil dealerBrandOwner_ per baris exempt (aman untuk volume kecil).


## P3 Koreksi: tab Kredit Tetap ke Penjual (2026-10-27)
- Tab hanya menampilkan transaksi penjual kredit-tetap yang pemilik dealer-brand-nya berbeda dari penjual (marker KREDIT_TETAP dari effectiveRows_). Uji: tambah aturan, publish, buka Koreksi, tab muncul dengan baris; antrean Perlu Ditinjau tidak bertambah.


## P4 Riwayat Pemilik selesai kode, belum diuji (2026-10-27)
- Foto dibuat HANYA saat tombol kunci periode ditekan (apiPeriodLock, locked=true). Periode yang sudah terkunci sebelum fitur ini ada TIDAK punya foto; kunci ulang (buka lalu kunci) untuk membuatnya, tetapi foto mengambil pemilik per tanggal akhir periode memakai data organisasi SAAT INI, jadi untuk bulan lama (mis. Maret) hasilnya belum tentu sama dengan kondisi saat itu. Foto yang benar hanya terbentuk mulai bulan yang dikunci sejak fitur ini aktif.
- Koreksi Transaksi sudah menghormati kunci periode (apiTxCorrSave memanggil assertPeriodOpen_). Pencabutan koreksi (apiTxCorrRevoke) belum dicek.
- Uji wajib: (1) kunci periode uji lalu cek audit OWNER_SNAPSHOT dan jumlah baris di dsm_owner_snap_meta; (2) Admin, panel Riwayat pemilik dealer di kategori Dealer; (3) periode terkunci menampilkan label FOTO; (4) ubah pemilik dealer setelah dikunci, foto tidak berubah; (5) unduh CSV; (6) waktu kunci periode dengan banyak dealer (cek tidak timeout).
- Paket P1-P4 selesai kode. Sisa: uji di Laragon, cek data (antrean Oktober, NIK transaksi HOS, cakupan dealer-brand), keputusan merge ke main.

- 2026-10-28: Paket 1 ST vs SO (% SO/ST) di Brand + Dealer selesai (frontend). Berikutnya: Paket 2 Analisa → Perbandingan mode "ST vs SO" (`v6CmpDraw` + `apiCompare`).

- 2026-10-28: Paket 2 (ST vs SO di Analisa → Perbandingan) selesai kode; perlu uji di Laragon: cek SO lengkap/tidak lengkap, W1–W5, bulan lalu, sales pemilik dealer. Riwayat `dev` dibersihkan dari commit 6e390a1 (force-push atas izin user).

- 2026-10-28: A1 tab AKSI selesai kode (assets/aksi.js). Uji: login Sales/ASM/RGM/Admin → Beranda → tab AKSI paling kanan; cek angka = tab RINGKASAN; klik brand membuka rincian. Next: A2–A4.

- Omzet card + per badan usaha selesai (lihat CHANGELOG_AI). Perlu dicek user: total per badan usaha cocok dengan omzet total; baris "Lainnya" muncul bila dsm_attr belum lengkap.

- A2–A4 selesai di tab AKSI (lihat CHANGELOG_AI). Perlu uji nyata per role: repeat order (RGM/TOP dari `rep`), daftar belum repeat (Sales/ASM), blok Admin. Tahap 2 (komitmen mingguan tersimpan, vs kemarin) belum dikerjakan.

- Tahap 2 poin 1–2 (komitmen mingguan + review) selesai; tabel `dsm_commit` dibuat otomatis saat pertama dipakai. Belum diuji dengan MySQL asli. Belum: perbandingan vs kemarin (butuh snapshot harian).
- Tab AKSI versi HP selesai (sub-menu per layar, sheet komitmen, telepon dealer). Perlu uji di HP asli.
- Dibanding kemarin (tanpa snapshot), notifikasi komitmen, dan pengingat Senin selesai. Pengingat butuh cron notif_cli.php + push aktif; belum diuji nyata.

## Agent API + Telegram — disetujui, BELUM dikoding (2026-11-10)
- Tujuan: bot Telegram tim IT (OpenClaw + GPT) bisa baca data DSM. DSM hanya menyediakan API; setup bot sepenuhnya urusan IT.
- Mockup disetujui: `dsm-agent-telegram.html` (di luar repo): Profil belum/sudah tersambung, alur chat, Admin > Pengguna & Akses > Sambungan Telegram.
- Dokumen untuk IT: `doc/agent-api/API_UNTUK_IT.md` (v0.1, menunggu review user).
- Rencana kode (tunggu "gas"):
  1. `agent_api.php` baru (terpisah dari `api.php`), GET read-only, header `Authorization: Bearer <BOT_KEY>` + `X-Telegram-User-Id`. Whitelist resource -> fungsi baca yang sudah ada (apiDashboard, apiExec, apiKpiTree, apiTeamKpi, apiRank, apiMyRank, apiTeam, apiEarlyWarning, apiPotensi, apiWeeklyAnalysis, apiCompare, apiGrowthType, apiDOS, apiTransactionGroups, apiDealerSearch, apiDealerDetail, apiDealerStock, apiDealerHistorySummary, apiDealerTutupList, apiTMTeam/apiTMKpi/apiTMRank, apiOrderList, apiCommitBoot, apiHariLibur). Jawaban diringkas (bukan payload layar), paging maks 100, rate limit, log.
  2. Tabel baru (auto-create): `dsm_tg_link` (telegram_id, nik, username, linked_at, expires_at, last_used, revoked_at, revoked_by, session_key), `dsm_tg_code` (hash kode, nik, until, used), `dsm_agent_key` (hash, label, aktif), `dsm_agent_log`.
  3. Identitas: semua fungsi DSM menerima `$token` dan pakai `authSession_` -> agent_api membuat sesi internal per sambungan dan memanggil fungsi yang sama, jadi filter role 100% sama dengan web. `authSession_` sudah otomatis menolak bila akun nonaktif atau VERSI berubah (ganti/reset PIN) -> syarat "putus otomatis" sudah gratis.
  4. Profil: kartu Hubungkan Telegram (kode 6 digit, 5 menit, sekali pakai) + Putuskan. Admin: panel Sambungan Telegram di grup Pengguna & Akses (+ "Putuskan semua" khusus Super Admin). Super Admin: buat/cabut BOT_KEY.
- PERHATIAN saat koding:
  - `authSession_` menggeser `until` (sliding). agent_api WAJIB cek `expires_at` sambungan (tetap 30 hari) SEBELUM memakai sesi.
  - Jangan pakai `issue_()` dengan deviceId: itu masuk daftar perangkat (DEVICE_MAX=5) dan bisa mengusir perangkat web user. Buat sesi internal tanpa registrasi perangkat.
  - Login DSM pakai PIN (bukan password); teks UI "password diganti" di mockup = "PIN diganti".
  - Pastikan `api.php` (browser) tidak bisa memakai sesi internal agent untuk fungsi tulis (tandai sesi `agent=1`, tolak di DSM_WRITE_FNS).
- Diputuskan user 2026-11-10: semua role termasuk Admin/Super Admin sambungan 30 hari tetap; nomor telepon dealer/kontak DISAMARKAN di jawaban agent (contoh 0812****789). Modul Klaim TIDAK dibuka ke agent (keputusan user 2026-11-10).

## Agent API Paket 1 (fondasi) — selesai kode, diuji lokal (2026-11-10)
- File: `agent_api.php` (baru), `lib/agent.php` (baru), `tools/agent_cli.php` (baru, alat sementara sebelum panel Admin), `api.php` (tolak sesi internal agent di pintu browser).
- Resource jalan: `health`, `me`, `link` (POST), `akan_habis` (untuk pengingat H-3, cukup BOT_KEY). Resource data = Paket 3.
- Diuji di MariaDB lokal kosong (bukan data asli): health, BOT_KEY salah 401, belum terhubung, kode salah, link sukses 30 hari, kode sekali pakai, resource belum ada, VERSI berubah -> LINK_REVOKED, kedaluwarsa, akan_habis, sesi agent ditolak api.php, sesi normal tidak terganggu, tabel _sessions bersih.
- BELUM diuji: `agentCall_` (sesi sekali pakai memanggil fungsi DSM) karena belum ada resource data; nama/role/cakupan di `me` (DB uji tanpa snapshot); header Authorization di Apache Laragon (kalau kosong, tambah di .htaccess: `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1`).
- Uji wajib di Laragon: (1) `php tools/agent_cli.php key "Bot IT"` simpan BOT_KEY; (2) `php tools/agent_cli.php code <NIK asli>`; (3) curl POST `?r=link` dengan kode -> nama & role muncul; (4) `?r=me`; (5) ganti PIN user lewat web -> `?r=me` jadi LINK_REVOKED; (6) `php tools/agent_cli.php unlink-all uji` -> semua LINK_REVOKED; (7) login web user yang sama tetap normal.
- Next: Paket 2 (Profil Hubungkan Telegram, panel Sambungan Telegram + Putuskan Semua, panel BOT_KEY), tunggu "gas".
- 2026-11-16 keputusan user: DSM tidak punya halaman Profil. Kartu "Hubungkan Telegram" ditaruh di menu akun (klik kartu nama di pojok kiri bawah, sejajar Perangkat Saya / Ganti PIN / Keluar), membuka halaman baru "Telegram Agent" (pola sama seperti PAGE 'perangkat', lihat index.php sekitar baris 6507 dan render()). Paket 2 wajib mengganti semua teks "DSM > Profil > Hubungkan Telegram" (lib/agent.php, doc/agent-api/API_UNTUK_IT.md, mockup) menjadi "klik nama di pojok kiri bawah > Telegram Agent". Lokasi di HP perlu dicek saat Paket 2.
- 2026-11-16 Paket 1 LOLOS uji user di Laragon (data asli, port 8080): link dengan kode -> ARI SUPER ADMIN 30 hari; ganti PIN -> LINK_REVOKED; login web dengan PIN baru normal. Catatan: cakupan kosong untuk Super Admin, tampilkan "Nasional" di Paket 3. BOT_KEY id 859 = key uji (terlihat di screenshot), WAJIB dicabut sebelum key untuk IT dibuat.

## Agent API Paket 2 (tampilan) — selesai kode, diuji lokal (2026-11-16)
- Mockup acuan: dsm-agent-telegram-v2.html (disetujui user, di luar repo).
- File: `assets/telegram_agent.js` (baru: halaman Telegram Agent + panel Admin), `lib/agent.php` (endpoint apiTgState/apiTgCode/apiTgUnlink/apiTgAdminList/apiTgAdminUnlink/apiTgAdminUnlinkAll/apiAgentKeyList/apiAgentKeyCreate/apiAgentKeyRevoke/apiTgBotSave, teks "Profil" diganti lokasi baru, cakupan "Nasional" untuk role pusat), `api.php` (require + whitelist + WRITE_FNS), `index.php` (titles 'telegram', render() diperlakukan seperti 'perangkat', menu akun desktop, sembunyi di sidebar desktop, ikon + label drawer HP, panggil dsmTgAdminPanel), `assets/searchable.js` (loader), `assets/admin_revision.js` (panel masuk tab Pengguna & Akses).
- Lokasi: desktop = klik kartu nama kiri bawah > Telegram Agent. HP = drawer LAINNYA > Telegram (pola sama dengan Perangkat; BEDA dari mockup layar 2 yang di topbar, karena di HP menu akun memang ada di drawer).
- Semua role melihat menu (data bot otomatis sesuai hak akses). Mode Demo ditolak.
- Diuji lokal (DB uji, snapshot 3 orang): state/code/link/unlink sendiri, Sales ditolak panel admin, Admin bisa lihat & putus satu, Admin ditolak BOT_KEY & Putuskan Semua, Super Admin buat/cabut key, simpan username bot, Putuskan Semua wajib alasan >=5 huruf. Tampilan halaman + panel dirender di harness (1200px & 390px): tanpa scroll horizontal, tanpa error console.
- BELUM diuji: di dalam aplikasi asli (menu akun, drawer HP, tab Pengguna & Akses, timing load telegram_agent.js saat Admin dibuka pertama kali).
- Catatan: tabel Admin menandai "PIN DIGANTI" hanya setelah sambungan dipakai/dibuka pemiliknya (cek VERSI terjadi saat dipakai), sebelum itu masih AKTIF tetapi bot tetap ditolak.
- Next: Paket 3 (resource data). Tunggu "gas".
- 2026-11-16 Paket 2b: panel Admin Sambungan Telegram diganti sesuai mockup v3 yang disetujui user (dsm-tg-admin-v3.html, di luar repo): header biru, kartu ringkasan berikon, daftar dengan avatar + batang masa berlaku + cari + filter status + muat lagi (20/halaman), kolom kanan BOT_KEY / username bot / Putuskan Semua (container query >=900px), HP satu kolom dan tabel geser dengan kolom nama menempel. Hanya `assets/telegram_agent.js` yang berubah. Diuji di harness 1440px & 390px: tanpa scroll horizontal, tanpa error console. Belum diuji di aplikasi asli.

## Agent API Paket 3 (resource data) — selesai kode, diuji lokal terbatas (2026-11-16)
- File: `lib/agent.php` (agentResource_ + AGENT_RESOURCES, agentTrim_ paging, agentMaskDeep_ penyamaran nomor HP, agentPeople_, agentCall_ kini memanggil tmBeginRequest_ seperti api.php dan mengisi argumen null), `agent_api.php` (memuat modul yang sama dengan api.php; cabang default menjalankan resource), `doc/agent-api/API_UNTUK_IT.md` v0.2.
- Jawaban = payload asli fungsi DSM (bukan bentuk baru), dipangkas + disamarkan. Kalau ada resource yang terlalu besar/aneh untuk GPT, ringkas per resource setelah dicoba IT dengan data asli.
- Diuji lokal (snapshot uji tanpa transaksi): orang (Super Admin semua, Sales hanya dirinya), cari, periode salah, parameter wajib, resource tidak dikenal, Sales minta nik orang lain -> FORBIDDEN "Data di luar cakupan", hari_libur/komitmen/dealer_tutup/tm_peringkat jalan, sesi internal terhapus. ringkasan/peringkat gagal di DB uji karena tidak ada data periode (wajar).
- WAJIB uji di Laragon dengan data asli: semua resource sekali per role (Super Admin, ASM, Sales, Telemarketing), ukuran jawaban ringkasan/dos/transaksi (waktu & besar), cek tidak ada nomor HP utuh.
- Catatan: dealer_cari memakai daftar customers dari apiDashboard (bukan apiDealerSearch yang khusus admin).

## Paket revisi 2026-11-16 — disetujui, BELUM dikoding (tunggu "gas" per paket)
Brief lengkap: `doc/ai/BRIEF_2026-11-16_PAKET_REVISI.md`. Mockup acuan: `doc/mockup/2026-11-16/`.
- Paket 0: audit menyeluruh read-only → `doc/ai/AUDIT_2026-11.md` (hak akses API, 3 perhitungan status dealer, data uji Klaim di laporan/budget).
- Paket 1: Admin 8 tab per objek + gabungan panel + Bento B2 solid.
- Paket 2: Klaim Mode Demo satu sakelar + menu rapi (nav maks 5).
- Paket 3: Aturan Status Dealer (ST, per brand + total, 6 bulan, kunci tgl 1, penanda bulan berjalan).
- Paket 4: Background Abu Polos bawaan + pilihan biru per user.
- Paket 5: Tema gelap (Navy Malam, Hitam Pekat).
- Ditunda: tema warna, penyeragaman isi panel Admin.
- STRUKTUR FINAL 3 paket: (1) Audit + Klaim [Fable], (2) Status Dealer [Fable], (3) Admin + Background + Tema gelap [Opus]. Lihat tabel di brief.
- Ringkasan atas halaman Dealer disetujui; kartu NOO per brand & Sell Out tetap.

## Audit November 2026 — SELESAI, menunggu dibaca user (2026-11-17)
Hasil: `doc/ai/AUDIT_2026-11.md` (read-only, tidak ada kode yang diubah). Ringkasan urut bahaya ada di awal dokumen.
- KRITIS (lapor dulu, perbaikan menunggu persetujuan): `config.php` berisi PEPPER ter-commit (S-1); `storage/cache/*.json` 220 file data asli ter-commit lagi (S-2).
- Klaim: daftar klaim/petty cash tanpa cek peran (A-1); daftar Admin pakai `region` bukan wilayah tugas (A-2); Mode tes memberi Super Admin kuasa atas data asli (A-3); semua laporan belum menyaring data uji (U-1…U-16).
- DSM: tidak ditemukan jalan Admin biasa/Sales memanggil fungsi Super Admin. KPI tidak memakai status dealer.
- Status dealer dihitung di 4 tempat dengan 4 aturan (peta di Bagian 2). Keputusan yang dibutuhkan sebelum Paket Status Dealer: definisi "SUDAH ORDER = DA KPI" (D-5).
- Bug logika yang perlu dites: kredit tetap berlaku surut (B-1), kunci periode vs transfer mundur (B-2).
- BELUM dikerjakan: koding Klaim Mode Demo. Menunggu user bilang "gas".

## Tindak lanjut audit — SELESAI KODE (2026-11-17)
Dikerjakan atas "gas 1-4" dari user.
1. `storage/cache/` keluar dari git (lagi) + `.gitignore`.
2. `config.php` tidak lagi berisi PEPPER; semua rahasia di `config.local.php` (contoh: `config.local.example.php`). **Server wajib punya `config.local.php` sebelum `git pull`**, kalau tidak login gagal ("Setup portal belum selesai").
3. Klaim Mode Demo (Paket 1 bagian 2) selesai kode: `is_demo` di claims/claim_pc (migrasi otomatis sekali), data demo terpisah dari semua laporan asli, masuk-sebagai hanya akun demo, hapus permanen data demo, nav maks 5, menu dikelompokkan, header Biru DSM, pita MODE DEMO AKTIF, Realisasi jadi tab di Budget (HOS/FM). Celah A-1 & A-2 audit ditutup. Tes `klaim/tests/run.php`: 116 lolos. BELUM dites di Laragon/server dengan login DSM asli.
4. Kecepatan: CSS (418 KB) & JS utama (778 KB) dipindah dari `index.php` ke `assets/dsm.css` & `assets/dsm.js` (isi identik byte-per-byte). `index.php` 1,29 MB → 91 KB. `.htaccess`: kompresi gzip + cache aset ber-versi 1 tahun; `sw.js` & `index.php` no-cache. Diuji di Apache 2.4 lokal (dengan dan tanpa mod_headers/mod_deflate).
- Belum: ganti nilai PEPPER (keputusan user: tidak perlu selama repo private); OPcache di server (setting, bukan kode).

## Paket 2 — Aturan Status Dealer — SELESAI KODE, MENUNGGU CEK DATA ASLI (2026-11-17)
- Sumber status tunggal: `lib/dealer_status.php` (aturan pola, kunci bulanan di `storage/status/<periode>.json`, penanda bulan berjalan ikut DA KPI).
- Dipakai oleh: `PortalCore::report()` (halaman Dealer, Beranda, export, agent `dealer_cari`), Detail Dealer (`apiDealerDetail`), Analisa › Reaktivasi, filter export.
- Layar: `assets/dealer_status.js` + `.css` (ringkasan atas Dealer, penanda opsi B, detail pola 6 bulan, Admin › Brand › Aturan Status Dealer khusus Super Admin; menggantikan panel "Ambang Status Dealer" — ambang lama di Master_Brand tidak dipakai lagi untuk status).
- API baru (Super Admin): `apiDealerStatusRules`, `apiDealerStatusImpact`, `apiDealerStatusSave` (alasan wajib, tercatat di Audit_Log).
- KPI/achievement/insentif: fungsi `metrics`, `kpi`, `dealerIddle`, `api_kpi`, `api_kpikhusus` TIDAK diubah.
- Uji: `php tools/tests/dealer_status_test.php` (24 lolos, data buatan).
- TITIK CEK (wajib, belum): user jalankan `php tools/cek_status_dealer.php` di Laragon sebelum & sesudah pull → KODE KPI harus sama; kirim angka DAMPAK.
- Tampilan (2026-11-17, mockup `doc/mockup/2026-11-17/dsm-status-dealer-desktop.html` disetujui): desktop pakai opsi A (pita warna tanpa simbol, bulan sekali di atas, keterangan di kanan, legenda). HP tetap sel bersimbol. Tab detail dealer HP opsi 1: grid 3×2, ikon kiri, label KAPITAL 11px, "Histori ST/SO" disingkat HISTORI.
- SELESAI KODE 2026-11-17 (menunggu cek data asli): rapikan halaman Dealer desktop pakai opsi A `doc/mockup/2026-11-17/dsm-halaman-dealer-rapi.html` — 4 kartu KPI sejajar (SUDAH ORDER, NOO, BANGKIT + asal, SELL OUT), status jadi 1 kartu (bar + 8 chip klik = filter), sel brand tanpa pita "✓ SEP" (angka + label status + pot), filter: cari + region + brand + order + tombol FILTER LAIN. Opsi B (`-B.html`) ditolak. Tunggu "gas"; cek tampilan HP sebelum pasang.
- SELESAI KODE 2026-11-17 bagian atas (tabel tetap): halaman Tim `doc/mockup/2026-11-17/dsm-halaman-tim-rapi.html` — yang disetujui baru bagian atas (bar filter TINGKAT/BRAND/REGION + FILTER LAIN, judul + toggle SELL THRU/DEALER AKTIF/NOO, 4 kartu KPI ramping garis warna kiri, kartu bisa diklik). Tabel belum diputuskan. Resep rapi 6 aturan di file yang sama belum dikonfirmasi.
- Tim: tabel TETAP seperti sekarang (keputusan user 2026-11-17); yang dirapikan hanya bagian atas.
- SELESAI KODE 2026-11-17: `doc/mockup/2026-11-17/dsm-dos-transaksi-peringkat-rapi.html` (bagian atas DOS, Transaksi, Peringkat; tabel tetap; filter DOS langsung jalan tanpa TERAPKAN; Transaksi dapat pilihan cepat tanggal). Kondisi DOS pakai opsi B `dsm-dos-kondisi-opsi.html` (3 grup BUTUH TINDAKAN / AMAN / TIDAK BERGERAK); kartu MENIPIS/HABIS dan PERLU CEK DATA dibuang, kartu DOS tinggal 3. Rencana: digabung ke Paket 3 (Admin+Bento → Background → Rapikan halaman → Tema gelap), tiap tahap berhenti & dites.
- Belum: dokumen `doc/agent-api/` belum menyebut nama status baru (AKTIF, DI BAWAH MIN, BANGKIT, TURUN, IDLE, PASIF, NONAKTIF, LAINNYA, BELUM ORDER) — kabari tim IT bot.

## Paket 3 tahap 1 — Admin 8 tab + Beranda Admin + Background — SELESAI KODE, BELUM DITES DI DSM ASLI (2026-11-17)
- Admin (`assets/admin_revision.js` + `.css`, versi dimuat dari `assets/searchable.js` ?v=20261117a): 8 tab per objek sesuai brief 1a, seksi (SERING DIPAKAI / KEPEMILIKAN / HATI-HATI dst.), kata kunci tersembunyi untuk pencarian, label "· Super Admin" jadi badge SUPER ADMIN, hanya panel di seksi HATI-HATI yang merah, "Master & kebijakan" tampil sebagai "Publikasi Master", Brand › TERKAIT = tautan ke Region per brand (panelnya tetap di KPI & Target). Isi panel tidak diubah.
- Beranda Admin (tab pertama, Bento B2 solid): tugas hari ini (approval, region belum lengkap ST, backup), panel sematan (☆ di tiap panel), data harian %, terakhir dibuka, semua menu.
- SELESAI KODE 2026-11-17 (1b, BELUM DITES DI DSM ASLI): penggabungan panel di `assets/admin_revision.js` (`mergeAdminPanels`) + `.css`. Isi panel lama DIPINDAH ke panel gabungan (bukan ditulis ulang), jadi form, tombol, dan proses backend tetap yang lama. (1) Pengaturan Brand: sub-tab DAFTAR & KLASIFIKASI / AMBANG STATUS / WILAYAH & TARGET (Super Admin) / PIC DEALER; tidak ada sub-tab ATURAN PRODUK karena tidak ada panel terpisahnya (Product Rules sudah ada di Brand & Klasifikasi). Panel Region per brand pindah ke sini; di tab KPI & Target tinggal tautan pintas. (2) Upload Massal Dealer: pilihan Tambah + Edit / Edit Field Customer / Kontak Saja (Super Admin). (3) Download & Upload Master: satu dropdown jenis (ditambah Master Dealer, diteruskan ke Upload Massal Dealer); Admin download saja, Super Admin ditambah upload. Dropdown asli dua panel disembunyikan dan disinkronkan. Mockup: `doc/mockup/2026-11-16/01-admin-struktur-menu.html`. Tes: halaman uji data buatan (Super Admin dan Admin biasa), tanpa error console.
- Background (`assets/ui_prefs.js` + `.css`, dimuat di index.php): bawaan Biru Langit #CFE3F7 (keputusan user 2026-11-17, menggantikan Abu Polos di brief), blob + kartu kaca di HP diganti latar solid + kartu putih. Pilihan per akun: tombol "Tampilan" (desktop: menu akun; HP: drawer LAINNYA). 4 terang + 4 biru solid (judul di latar jadi putih).
- Backend: `lib/api_ui.php` (apiUiPrefs, apiUiPrefSave [diblok di Mode Demo], apiAdminHome), disimpan di Properties `UI_PREF_<NIK>`; `apiBootstrap` ikut mengirim `ui`.
- Diuji di halaman uji (data buatan) 1440 / 390 / 360: tidak ada error console, tidak ada geser horizontal. Belum dicek di DSM asli. Latar biru: teks lain yang menempel langsung di latar (di luar judul halaman) mungkin masih gelap — cek per halaman, sekalian di tahap tema gelap.

## Menu atas + rapikan halaman desktop — SELESAI KODE, BELUM DITES DI DSM ASLI (2026-11-17)
- Menu desktop opsi B (mockup `doc/mockup/2026-11-17/dsm-menu-desktop-B.html`): `assets/topnav.js` + `.css`. Bar atas: logo, Beranda, SEMUA MENU (PANTAU/ANALISA/KERJA), tombol terpisah AI Assistant / Informasi / DSM Klaim / Admin, cari menu (Ctrl K), avatar. Hanya memanggil tombol asli `#navigation button[data-page]` (hak akses role tetap sumber tunggal); sidebar lama disembunyikan, tetap di DOM. HP tidak disentuh.
- Rapikan halaman desktop: `assets/page_tidy.js` + `.css` (post-render DOM enhancement + CSS `@media(min-width:821px)`, handler lama tetap jalan). Halaman: Dealer (4 kartu, bar status, filter + FILTER LAIN), Tim bagian atas (tabel tetap), DOS (info 1 baris, 5 kartu, filter langsung jalan, chip kondisi berkelompok), Transaksi (4 kartu, rentang tanggal + HARI INI/7 HARI/BULAN INI), Peringkat (3 kartu posisi saya, pilihan 1 baris, keterangan di ⓘ). Brand/Analisa/Target SO/Transfer/Koreksi: hanya gaya bar filter seragam (`tidyGeneric`, aman, tidak memindah elemen).
- Catatan: chip kondisi DOS belum menampilkan jumlah per kondisi (backend `apiDOS` belum mengirim hitungan per kondisi; perlu perubahan backend bila mau).
- Ditunda: tema gelap, kabari tim IT bot soal nama status. (1b penggabungan panel Admin sudah selesai kode, lihat bagian Paket 3 tahap 1.)
- Tes: halaman uji data buatan 1440 & 390, tidak ada error console. BELUM: DSM asli, role selain Super Admin/Sales, tiga halaman terakhir (Brand dst.) hanya dites tidak error.

## Header halaman desktop (banner biru) + peringatan ST di bar atas — SELESAI KODE, BELUM DITES DI DSM ASLI (2026-11-17)
- Mockup disetujui: `doc/mockup/2026-11-17/dsm-halaman-dealer-header-tipis.html` (peringatan di bar atas) + banner biru solid opsi D (`dsm-halaman-dealer-header-D.html`).
- `assets/page_header.js` + `.css` (baru, dimuat di index.php): memindahkan `.ptitle` + `.psub` jadi banner biru `.phBan` (judul, keterangan per halaman, chip MTD/LMTD diperkecil tanpa tahun, chip status dikunci Dealer disalin ke banner). Dilewati: beranda, admin, ai, klaim. HP tidak disentuh (`min-width:821px`).
- `assets/topnav.js` + `.css`: penanda kuning "ST BELUM LENGKAP · N REGION" di bar atas (khusus ADMIN/SUPER ADMIN, logika sama dengan `freshnessPanel()`; banner kuning lama disembunyikan di desktop); popover tabel region. Tombol Cakupan & Periode dan garis biru permanen dari sesi ini juga di topnav.
- Tinggi freeze: 62px (bar Cakupan & Periode bawaan tertutup, bisa dibuka).

- 2026-11-17: masa sambungan Telegram diubah jadi 60 hari sejak terakhir dipakai + maks 180 hari (AGENT_LINK_DAYS=60, AGENT_MAX_DAYS=180 di lib/agent.php; logika geser di agentLinkActive_). WAJIB uji Laragon: hubungkan -> tanya -> tanggal berlaku maju; di titik 180 hari tidak lewat. Juga: log BAD_KEY kini berisi alasan (NO_HEADER/FORMAT/NOT_FOUND/REVOKED) di dsm_agent_log.status. Catatan operasional: BOT_KEY dibuat per lingkungan (Laragon dan server beda database), bikin key IT dari server yang ditembak.

## Status dealer Paket 3 - frekuensi 3 bulan (2026-11-18, DIKERJAKAN, perlu QA user)
- Aturan baru (slide briefing RGM): ACTIVE order ≥2 dari 3 bln closing · PASSIVE 1 bln · IDLE tidak order 3–5 bln berturut · INACTIVE ≥6 bln · PROSPECT belum pernah order sejak basis (2026-01). ST qty, retur = qty bersih bulan itu. Tetap dikunci tiap tanggal 1. Hanya analisa, tidak ke KPI.
- Per brand: dealer yang belum pernah ambil brand itu = PROSPECT (tanpa matriks wajib jual, keputusan user). TOTAL tanpa brand yang dimatikan (HONOR default off).
- NOO = label bulan berjalan (dealer PROSPECT yang order bulan ini), bukan status kunci.
- Mockup disetujui: doc/mockup/2026-11-18/dsm-dealer-status-hp.html (Opsi A daftar) + dsm-admin-aturan-status-hp.html (3 tab).
- Belum diputuskan user: BANGKIT (kartu desktop masih ada, di HP disembunyikan); dealer master yang nonaktif dikecualikan dari PROSPECT (sekarang: dealer TUTUP dikecualikan).
- Periode lampau yang sudah dikunci (storage/status/*.json) tetap memakai nama status lama (AKTIF/PASIF/dst); warna lama tetap dikenali.

## Simpan cepat Tahap 1+2 (2026-11-18, DIKERJAKAN, BELUM TERUJI di DSM asli)
- Tahap 1: `with_lock_($f, $name)` + `with_lock_named_()` (lib/api_import.php). Simpan kecil pakai kunci sendiri: Telemarketing (profil, penugasan, KPI TM, copy KPI, badan usaha, follow up, FORM ORDER) = `dsm_tm`; kontak dealer (ajukan, review, simpan, bulk) = `dsm_contact`; ajukan dealer tutup = `dsm_tutup`. Tidak lagi antre di belakang publikasi Admin.
- Tahap 2: jalur cepat `publishMetaOnly_` di lib/publish.php. Aktif hanya bila pemanggil membungkus `publishLight_()` DAN data ST/SO sama DAN tanda atribusi master (`publishAttrSig_`) sama. Dipasang di: kpiCommit_, apiSaveUser, apiCommitUsers, apiDealerSave (tanpa ganti induk), apiCommitCustomerBulk, apiCommitMasterBulk, apiPublishConfig. Selain itu tetap publikasi penuh.
- Tanda atribusi TIDAK memuat: policies, targets, rgmPolicies, targetBig, warnings, kontak dealer (alamat/hp/pic/kota), adminRegions. Nama dealer tetap dihitung (dipakai data stok).
- storage_prune_ sekarang ikut menyimpan mutasi stok (dsm_move) yang ditunjuk snapshot aktif/sebelumnya (jalur cepat memakai mutasi lama).
- Darurat: set properti `PUBLISH_FAST_OFF` = 1 → semua kembali publikasi penuh.
- Tes: tools/tests/publish_fast_test.php (9 lolos). Wajib uji lokal: ubah bobot KPI, simpan user ganti PIN/akses, edit kontak dealer, Form Order TM saat Admin upload ST.
- Tahap 3 (publikasi berat di belakang layar) menunggu jawaban IT: PHP-FPM / mod_php, boleh cron 1 menit.

## Simpan cepat Tahap 3 - antrean publikasi (2026-11-18, DIKERJAKAN, BELUM TERUJI di DSM asli)
- lib/publish_queue.php (baru): publish_() yang hanya publikasi ulang dari master (ST/SO sama) → masuk antrean (`pqEnqueueMaster_`), upload ST/SO → antrean (`pqEnqueueImport_`, inti dipindah ke `publishRegionImportRun_`). Pekerja `pqWork_`: upload satu per satu, master digabung jadi satu publikasi. Gagal → data lama aktif, dicoba lagi 5 menit; status di prop PUBLISH_STATE, antrean PUBLISH_QUEUE, tahan PUBLISH_HOLD (lepas otomatis 2 jam).
- Pemicu: cron CLI `* * * * * php /path/dsm/tools/publish_worker.php >> /path/dsm/storage/publish_worker.log 2>&1` (UTAMA, tanpa batas 180 dtk FPM — IT perlu pasang) + fastcgi_finish_request setelah simpan + apiPublishStatus memicu ulang bila antrean diam > 90 dtk.
- UI: assets/publish_queue.js/.css. Banner biru/kuning/merah untuk Admin & Super Admin (sebelum #view). Tombol PUBLIKASIKAN di bar atas (desktop, sebelum chip ST) / header HP, khusus Super Admin, + popup. Panel Admin › Data Harian › Publikasi Data. PUBLIKASIKAN SEKARANG & Tahan publikasi khusus Super Admin.
- API baru: apiPublishStatus (Admin), apiPublishNow, apiPublishHold (Super Admin).
- Darurat: prop PUBLISH_QUEUE_OFF = 1 → publikasi langsung seperti dulu.
- Catatan: simpan yang masuk antrean tidak lagi mengembalikan sheet bila publikasi gagal belakangan (validasi tetap di depan). Restore data (apiCommitRestore) tetap langsung.
- Belum: Tahap 4 panaskan cache setelah publikasi (hook `pqWarm_` disiapkan, belum ada isinya).

## Performa baca (2026-11-19)
- Selesai: kapasitas cache hasil (lihat CHANGELOG). Cek di server: ukuran folder storage/cache tidak melewati ruang disk (batas 1 GB).
- Selesai 2026-11-19: panaskan cache otomatis setelah publikasi (lib/warm.php). Uji: buka Dashboard & DOS beberapa akun, publikasi (mis. ubah Region Map), tunggu pekerja antrean selesai, buka lagi -> harus cepat sejak pembukaan pertama. Cek tabel dsm_warm terisi; _sessions tidak menyisakan device_id WARM. Darurat: WARM_OFF=1.
- Selesai 2026-11-19: simpan kontak tidak membuang cache Dashboard (CONTACT_PATCH_OFF) + transaksi satu dealer dari DB (TX_FAST_OFF). WAJIB uji: (1) buka Dashboard, simpan kontak 1 dealer, Dashboard lagi harus cepat & HP/alamat dealer itu terbaru di daftar Dealer; (2) popup dealer tab transaksi: TX_FAST_OFF nyala vs mati (hapus storage/cache tiap kali) -> baris, qty, amount, NIK sales sama.
- Selesai 2026-11-19: apiDealerContact pakai snapshot ringan (meta saja). Uji: buka popup dealer (Admin, Super Admin, Sales pemegang), Edit kontak & simpan; probe apiDealerContact harus jauh di bawah 1,7 dtk. Darurat: META_LIGHT_OFF=1.
- Selesai 2026-11-19: detail dealer (histori satu dealer + pengamat loading). TERUJI LOKAL (Laragon, user): PT.AGRES INFO TEKNOLOGI, HISTORY_FAST_OFF nyala vs mati -> Sell Thru & Sell Out sama; apiDealerHistory popup 6,35 dtk -> 1,3 dtk. Belum diuji di server. Uji semula: buka popup 2-3 dealer, catat angka histori; set properti HISTORY_FAST_OFF=1, buka dealer yang sama, angka harus sama persis; hapus properti lagi.
- Selesai 2026-11-19: biaya tetap per request (guard_ ringan). Uji: login semua role, Mode Demo, Telemarketing; ukur ulang ?probe=1 (apiPublishStatus/apiV6Boot harus jauh di bawah 0,5 dtk; DOS dari cache lebih cepat).
- Antrean (belum dikerjakan, perlu persetujuan): apiDOS/apiExec dingin 4,6 dtk; apiDashboard 5,3 MB; pengamat DOM dsm.js:832 di DOS (perlu profil Performance dari browser user); kontak dealer & kunci cache; panaskan otomatis per akun aktif setelah publikasi.

## Fix form_kit - simpan form pop-up (2026-11-19, DIKERJAKAN, BELUM TERUJI di DSM asli)
- Rincian di CHANGELOG_AI.md. Pelajaran: setiap perubahan yang memindahkan elemen form harus dites dengan handler simpan aslinya, bukan hanya tampilan. Uji ulang semua form pop-up yang memakai querySelector('button') (14 tempat di assets/dsm.js).

## Gabung perubahan IT - publikasi lebih ringan (2026-11-19, DIKERJAKAN, BELUM TERUJI di DSM asli)
- Rincian ada di CHANGELOG_AI.md (2026-11-19). Wajib uji Laragon/MySQL: publikasi penuh dua kali beruntun (mutasi stok dipakai bersama) -> tunggu prune -> halaman Stok/DOS masih terisi; upload ST lalu ubah Region Map; simpan bersamaan dua admin (tunggu kunci 40 dtk).
- Minta IT: konfirmasi RAM server dan pm.max_children (memori 2560M per publikasi); kerja dari dev terbaru dan kirim diff/patch, bukan file utuh (file utuh mereka dari basis lama).
- Tahap 4 (panaskan cache) status: lihat commit a4df10d "panaskan setelah publikasi"; belum diverifikasi ulang di sesi ini.

## Klaim · Draf di Beranda & Klaim saya + badan usaha (2026-11-19, DIKERJAKAN, BELUM TERUJI di DSM asli)
- WAJIB uji di Laragon (akun pemohon): simpan draf, Beranda menampilkan draf, Tambah nota (kamera HP), Lanjutkan & kirim; Klaim saya › Draft; Super Admin › Badan usaha: ubah nama, gabung MSI ke PT. MSI, hapus.
- Kartu status pendek berlaku untuk semua peran (user minta 2026-11-19).
- Mockup: doc/mockup/2026-11-19/dsm-klaim-draf-beranda.html (7 keadaan).
- Beranda pemohon: bagian "Draf belum dikirim" di BAWAH 4 kartu status, di atas Klaim terbaru; hanya bila ada draf; maks 3 (Lihat semua).
- Kartu draf: jenis, jumlah nota, total sementara, waktu simpan, foto kecil; tombol "Tambah nota" & "Lanjutkan & kirim". Draf > 3 hari ditandai oranye.
- Tambah nota cepat: isian ikut jenis (notaHtml di klaim/assets/form.js: tanggal, nominal, foto; Tol + gerbang asal/tujuan; BBM + jenis BBM). Tidak ada isian keterangan bebas. Isian boleh kosong saat simpan draf, wajib saat kirim; foto wajib.
- Klaim saya: Draft jadi tombol ketiga di bawah FILTER (Daftar klaim · Perlu tindakan saya · Draft); chip "Draft" di baris atas dihapus. Menu Klaim saya diberi angka jumlah draf.
- Badan usaha: mockup doc/mockup/2026-11-19/dsm-klaim-badan-usaha.html (keputusan: klaim lama ikut diganti; hapus yang dipakai = gabung).

## Antrean per 2026-11-18 malam (BELUM DIKERJAKAN)
- Popup detail dealer: mockup doc/mockup/2026-11-18/dsm-detail-dealer.html disetujui untuk DESKTOP saja (HP tetap seperti sekarang). Tunggu "gas".
- Konfirmasi gaya DSM pengganti confirm()/prompt() bawaan browser (43 + 9 tempat), dikerjakan bertahap.
- Keterangan header form (nama/kode dealer) per form.
- Panel Admin (±61) & tabel halaman yang belum pola tabel HP (Transaksi, Koreksi, Transfer, Target SO, Peringkat, Tim) — audit per halaman.
- Simpan cepat Tahap 4: panaskan cache setelah publikasi.
- Status dealer: dealer nonaktif untuk PROSPECT = SELESAI 2026-11-19 (diajukan tutup dikecualikan). BANGKIT sudah dibuang 18 Nov.
- Menunggu IT: pasang cron publish_worker.php.

## Paket 18 Nov malam (DIKERJAKAN, BELUM TERUJI di DSM asli)
- Popup detail dealer DESKTOP (assets/dealer_detail.js/.css): header + aksi (CATAT ORDER, Telepon, WhatsApp, ⋯ Tandai dealer tutup), tab menempel, kartu warna indikator, brand tanpa transaksi disembunyikan. HP tidak berubah.
- Konfirmasi gaya DSM `dsmConfirm()` (form_kit.js/.css): SEMUA 43 confirm() diganti (6 handler di dsm.js dijadikan async).
- Keterangan dealer di header form (dari popup dealer yang terakhir dibuka, < 15 menit).
- BANGKIT dibuang dari halaman Dealer (desktop & HP). Label NOO di kartu DA kini terkirim (core.php ds.noo).
- Header tabel standar: pita Teal tua #0E5A63 teks putih (assets/table_head.css, dimuat terakhir). Header grup berwarna (tpG/tpG2/tpL/v7bh/total/skor) tidak diubah.
- Tahap 4: pqWarm_() memuat snapshot & status dealer setelah publikasi antrean.
- PROSPECT: master dealer tidak punya status nonaktif terpisah; yang dikecualikan dealer TUTUP. Dianggap selesai.

## Tab STATUS detail dealer di HP (2026-11-19)
- Selesai (BELUM TERUJI di server): tab STATUS popup dealer di HP jadi kartu baris per brand + 12 kotak bulan (P-12..P-1), angka bulan + label tahun di kartu TOTAL, 3 bulan jendela bergaris bawah, sebelum basis = putus-putus. Mockup: doc/mockup/2026-11-19/dsm-status-dealer-hp.html.
- Data: `dsP12_()` lewat RPC baru `apiDealerP12` (cek akses = apiDealerDetail), dipanggil saat tab STATUS diketuk di HP. Popup memakai data daftar Dealer, jadi p12 tidak ikut di sana. Daftar Dealer tidak bertambah. Desktop (>760px) tidak berubah.
- Uji: buka popup dealer di HP (≤760px) tab STATUS; cek dealer dengan retur, brand dimatikan, dan PROSPECT.
- Desktop v2 (2026-11-19, BELUM TERUJI): HUBUNGI, tab segmen, HISTORI disembunyikan di desktop, alamat di tab KONTAK, STATUS 12 bulan. Mockup doc/mockup/2026-11-19/dsm-detail-dealer-desktop-v2.html.

## Status mockup 2026-11-19 (diperbarui 2026-11-19 malam) — SEMUA SUDAH DIKERJAKAN
- Sudah di `dev`: Daftar Dealer padat opsi C (f474b65), filter Daftar Dealer satu baris rapat (f99cf29 + fix tooltip 66c6633, 4ecc0d2), History Dealer per Brand + Summary gaya gabungan (0be2f4c, 7821187), Ber-promotor dilipat (52a02f2, 7d2a424), popup dealer desktop v2 + tab STATUS HP (f81c2ba, b1bb198, dst.), Klaim draf & badan usaha (e4cc0d2).
- Bukan utang kerja (belum ada mockup / ditahan user):
  - Gaya padat untuk tabel dealer lain (Brand › dealer, DOS per dealer, Early Warning): belum ada mockup khusus. Wajib mockup dulu kalau mau.
  - Tema petrol untuk semua tabel (dsm-tabel-global.html): DITAHAN, user pilih gaya per tabel.
- DOS & Analisa padat "A + WARNA BRAND" DIKERJAKAN 2026-11-19 malam (lihat CHANGELOG), perlu QA user di DSM asli. Belum diputuskan: baris TOTAL baru di Matriks dealer × brand (ada di mockup sebagai usulan).

## Popup dealer v3 (2026-11-19, desktop)
Sudah dikoding: tabel padat + TOTAL berwarna, tombol Tandai tutup terlihat, Kontak K3, font konsisten. Belum dites di DSM asli (hanya harness). Belum dikerjakan/tidak disentuh: TOTAL Matriks, HP, tabel Analisa lain, tab STATUS.

## Request dicatat - Analisa › Cross-sell (2026-11-20, BELUM DIKERJAKAN)
- Matriks dealer × brand: data dealer dipisah jadi kolom KODE · DEALER · SUB REGION · SALES (kota diganti sub region; butuh backend kirim `sub` dari `anaAreaOf_` di `lib/api_analisa.php`), 1 baris per dealer (±24px), teks panjang dipotong "…" + tooltip. User bilang "oke, catat dulu". Mockup: `doc/mockup/2026-11-20/dsm-cross-sell-matriks-split.html` (desktop + HP). Belum diputuskan: desktop saja atau HP juga (di HP: kolom DEALER sticky, nama dealer & sales tampil penuh tidak dipotong, KODE masuk tooltip). Cakupan hanya tabel matriks Cross-sell. Tunggu "gas".
- Rombak total Analisa desktop (3 opsi A/B/C): `doc/mockup/2026-11-20/dsm-analisa-desktop-rombak.html`, user belum memilih. Cakupan: 3 tab yang ada, isi data tidak berubah.
- Catatan: commit 31fb4ff (filter Analisa + cari dealer, tanpa mockup) sudah di-revert 2b4c6e8; jangan dipakai ulang tanpa mockup.
- 2026-11-20: matriks Cross-sell split disetujui user ("oke cocok"), tunggu "gas". Kartu KPI Cross-sell: "Dealer aktif" diganti "Dealer bertransaksi" (hitungan sama: ada order bulan ini/lalu; nama lama bentrok dengan definisi Dealer Aktif ≥2 unit/brand). Filter desktop: opsi F4 (rapi + cari) di `dsm-analisa-filter.html`, menunggu pilihan/"gas". Catatan: data asli ±1.686 dealer, matriks tampil 80 teratas.
- 2026-11-20: user pilih filter desktop F1 (1 baris, cari + 5 dropdown + reset). Kartu KPI Cross-sell: tanpa ikon, hijau mint muda, kartu 1 "Dealer bertransaksi", kartu 2 (ex "Peluang terbuka") nama belum dipilih (Brand belum diambil / Peluang cross-sell / Peluang tawar brand). Tombol ⓘ "Bedanya dengan menu Dealer?" di kanan tab. Mockup di `dsm-analisa-filter.html`; belum "gas".
- 2026-11-20: kolom BRAND (mis. 3/7) di matriks Cross-sell mau dibuat lebih menarik: 4 opsi di `dsm-cross-sell-matriks-split.html` (Polos / Titik brand / Bar / Badge warna), belum dipilih. Mockup matriks dipakai 7 brand.
- 2026-11-20: user "oke cocok, catat dulu" untuk mockup Cross-sell (matriks split, filter F1, kartu mint tanpa ikon, ⓘ beda dengan menu Dealer, kolom BRAND). Gaya kolom BRAND yang dipilih belum disebut eksplisit (rekomendasi: BADGE WARNA, konfirmasi saat "gas"); nama kartu 2 default "Brand belum diambil". Semua BELUM dikoding.
- 2026-11-20: request baru: kartu KPI halaman Brand diubah seperti kartu Cross-sell (tanpa ikon, hijau mint muda). Mockup: `doc/mockup/2026-11-20/dsm-brand-kartu-mint.html`. Hanya halaman Brand (desktop); jangan melebar ke halaman lain tanpa mockup.
- 2026-11-20: user "oke cocok, catat dulu" untuk mockup kartu Brand (`dsm-brand-kartu-mint.html`). Request lanjutan: SEMUA kartu KPI di bagian atas halaman (contoh yang disebut: DOS, Transaksi, Telemarketing; "pokoknya kartu-kartu yang di atas") dibuat gaya sama: tanpa ikon, hijau mint muda. BELUM ada mockup untuk halaman-halaman itu, jangan dikoding. Perlu dicek: DOS/Transaksi sekarang pakai garis kiri + angka berwarna indikator (ST biru, SO oranye, omzet cokelat, Menipis/Habis merah) yang aturannya "warna indikator jangan diubah"; tanya user apakah warna angka/garis dipertahankan di atas latar mint. Daftar halaman yang punya kartu atas perlu diaudit (Dealer, Tim, KPI, Peringkat, dll) sebelum mockup.
- 2026-11-20: request baru: tabel KPI ALL BRAND (Perbandingan KPI ASM, halaman KPI desktop) mau dirapikan: header menarik + dipadatkan. Mockup: `doc/mockup/2026-11-20/dsm-kpi-tabel-rapi.html` (SEKARANG / A header berwarna indikator / B header abu muda). Belum dipilih, belum dikoding. Tabel RGM/Sales di bawahnya belum disentuh.
- 2026-11-20: user: tabel di Rincian KPI Tim ada beberapa, gayanya nanti disamakan. Struktur di kode (`desktopKpiComparison`, dsm.js): panel "Perbandingan KPI ASM" dan "Perbandingan KPI Sales", masing-masing berisi tabel ringkasan KPI ALL BRAND (`v7kSum`) + satu tabel detail per grup KPI (`kpiCompareGroup`/`kpiCompareTable`). Setelah user memilih A/B, mockup diperluas ke tabel detail per grup + versi Sales SEBELUM dikoding.
- 2026-11-20: user setuju USULAN A untuk tabel KPI ALL BRAND (target/aktual dipisah kolom) + minta garis penegas pembeda antar indikator: ditambahkan garis kiri 3px warna indikator dari header sampai TOTAL (mockup `dsm-kpi-tabel-rapi.html` diperbarui). Belum "gas"; tabel detail per grup + versi Sales masih perlu mockup.
- 2026-11-20: tabel KPI usulan A: kolom nama ASM/Sales + AREA (sub region) dibekukan (sticky) saat digeser horizontal; ditunjukkan di mockup dengan tombol LAYAR SEMPIT. Berlaku juga untuk tabel detail/Sales saat disamakan.
- 2026-11-20: user "oke cocok, catat dulu" untuk mockup tabel KPI ALL BRAND usulan A (target/aktual terpisah, garis penegas indikator, nama+area freeze). Belum dikoding. Sisa sebelum "gas": mockup tabel detail per grup + versi Sales (gaya disamakan).
- 2026-11-20: tabel "RINGKASAN SKOR KPI" (ASM·AREA | KPI ALL BRAND | KPI MOTOPAD | SKOR AKHIR | RATA-RATA TIM) ditambahkan ke mockup `dsm-kpi-tabel-rapi.html` dengan gaya usulan A yang sama (nama+area dipisah & freeze, header per kolom berwarna, garis penegas, RATA-RATA TIM mengikuti warna kolom). Menunggu persetujuan user.
- 2026-11-20: user "oke cocok" untuk tabel Ringkasan Skor KPI (gaya A, garis bawah header). Catat: belum dikoding.
- 2026-11-20: request baru: halaman Transaksi desktop dirapikan: tabel "Transaksi Harian" (heatmap per brand) dan "Rincian Transaksi" (daftar). Mockup: `doc/mockup/2026-11-20/dsm-transaksi-tabel-rapi.html`. Belum dikoding.

- 2026-11-20 Transaksi: "Rincian Transaksi" mockup DISETUJUI (cocok). "Transaksi Harian": user TIDAK mau heatmap. Mockup dsm-transaksi-tabel-rapi.html punya 3 opsi tanpa heatmap (H1 polos + tint kolom, H2 bar tipis, H3 puncak ditandai ▲), menunggu pilihan. Belum dikoding, tunggu "gas".
- 2026-11-20 Transaksi Harian: hari Minggu diarsir MERAH (permintaan user). Ditambah opsi H4 = gabungan H1 (tint kolom) + H3 (puncak ▲ sel warna brand pekat). Menunggu pilihan H1/H2/H3/H4. Belum dikoding.
- 2026-11-20 Transaksi Harian DISETUJUI: gaya H4 (tint kolom brand tipis + puncak tiap brand ▲ sel warna brand pekat, angka putih), tanpa heatmap, Minggu diarsir merah, kolom Tanggal/Hari sempit, baris TOTAL menempel. Rincian Transaksi juga disetujui. Mockup: doc/mockup/2026-11-20/dsm-transaksi-tabel-rapi.html. Status: APPROVED, menunggu "gas" (belum dikoding). Cakupan hanya 2 tabel di halaman Transaksi desktop.
- 2026-11-20 Peringkat desktop: user minta tambah kolom ASM + REGION (dekat NAMA) dan redesign. Mockup doc/mockup/2026-11-20/dsm-peringkat-desktop.html (SEKARANG vs USULAN; nama ASM = contoh). Menunggu persetujuan, belum dikoding.
- 2026-11-20 Peringkat desktop revisi: kolom BIG REGION ditambah (khusus desktop), urutan NAMA, ASM, BIG REGION, REGION; header "KLP" diganti "Σ GRUP KPI" (tidak disingkat). Mockup dsm-peringkat-desktop.html. Menunggu persetujuan.
- 2026-11-20 Peringkat desktop: garis atas header 3px ditambah untuk kolom netral (hitam kebiruan #15303F), kolom KPI tetap berwarna. Menunggu persetujuan.
- 2026-11-20 Peringkat desktop DISETUJUI ("oke cocok", catat dulu): mockup doc/mockup/2026-11-20/dsm-peringkat-desktop.html. Isi: judul ringkas + chip periode MTD/LMTD; tab & filter 1 baris (cari + JABATAN/PERINGKAT/DASAR); info panjang jadi ⓘ; legenda 3 teratas/3 terbawah/KPI Akhir ≥90% di judul papan; tabel padat 25px, header abu muda #E6EBF1 + garis atas 3px (netral #15303F, KPI berwarna) + garis bawah 2px; kolom #, NAMA, ASM (baru), BIG REGION (baru, khusus desktop), REGION, KPI ALL BRAND, KPI MOTOPAD, Σ GRUP KPI (tidak disingkat), KPI AKHIR (batang mini ≥90 hijau/60–89 kuning/<60 merah), STATUS; 3 teratas aksen emas/perak/perunggu, 3 terbawah aksen merah. Catatan teknis: nama ASM & Big Region di mockup = contoh; cek dulu backend peringkat punya field ASM/Big Region. Cakupan: papan Sales desktop saja (ASM/Telemarketing butuh mockup). Status: APPROVED, menunggu "gas".
- 2026-11-20 Tabel "KPI RGM" (panel atas halaman KPI, desktop): user minta dirapikan, boleh tidak full width. Mockup doc/mockup/2026-11-20/dsm-kpi-rgm-tabel.html (SEKARANG vs USULAN). Menunggu persetujuan, belum dikoding. Juga diminta (belum dimockup, belum dikoding): Rincian KPI Tim terbuka dari awal di halaman KPI (sub-panel ASM/Sales tertutup), perlu mockup + pertimbangan beban apiKpiTree.
- 2026-11-20 Tabel KPI RGM desktop DISETUJUI ("oke cocok"): mockup dsm-kpi-rgm-tabel.html. Tidak full width, baris 26px, header abu muda + garis atas 3px (brand berwarna, TOTAL POIN biru), kotak brand, batang mini di samping angka, TOTAL POIN batang gabungan + chip %, RATA-RATA warna = header kolom, 2 teratas aksen emas/perak. Status: APPROVED, menunggu "gas".
- 2026-11-20 CROSS-SELL DIKODEKAN (setelah "gas"): matriks split + sub region (backend field `sub`), filter F1 + cari dealer (desktop, tab Cross-sell), kartu mint, ⓘ, kolom BRAND badge. Default dipakai: badge warna, kartu 2 "Brand belum diambil", batas 80 dealer tetap. Perlu dites di server nyata (data asli). Belum: kartu mint di tab Reaktivasi/Dealer Baru & halaman lain (belum mockup/izin), Cari dealer di Reaktivasi.
- 2026-11-20 Menu ANALISA di-HIDE sementara (permintaan user, JANGAN dihapus): `HIDDEN_PAGES=['analisa']` di assets/dsm.js (menu atas, drawer HP, #analisa dialihkan ke Beranda). Semua kode Analisa (termasuk Cross-sell yang baru) utuh. Untuk memunculkan lagi: kosongkan array HIDDEN_PAGES.

## 2026-11-20 — Paket rombak desain dikodekan (user: "sekalian semua")
- DIKODEKAN & di-push ke dev: KPI ALL BRAND + Ringkasan (ASM, lalu diperluas ke SEMUA grup KPI + versi Sales, kelas `kcA`, `assets/kpi_tabel.css`); tabel KPI RGM (`kcR`, mockup dsm-kpi-rgm-tabel); Peringkat desktop (`rkT`, kolom ASM + Big Region, backend `apiRank` kirim `asm` via `rankAsmName_`; admin Cari & Kroscek + banner judul halaman BELUM disentuh); Transaksi desktop (Harian H4 + Rincian + filter tanggal `trxD`/`trxFF`); kartu mint desktop di Dealer, Peringkat, DOS, Transaksi, Telemarketing (HP tetap lama); Rincian KPI Tim terbuka default di desktop (sub-panel ASM/Sales tetap tertutup); Brand mint cards; UNIT/BLN dibulatkan; Analisa di-hide.
- Catatan teknis: tabel baru harus dikecualikan dari daftar `:not(...)` di `table_dense.css` dan skip-list `v7TblStyle`/`alignTable` di index.php (`.rgT,.v7kc,.kcA,.kcR,.rkT,.trxD`), CSS pakai 6× `#app`.
- Belum: mockup untuk HP versi tabel baru, Peringkat mode ASM/Telemarketing, bagian atas Peringkat (judul + Cari & Kroscek satu baris), mint cards di Beranda/KPI/Tim. Perlu dites di server nyata.
- 2026-11-20 Transaksi Harian desktop + OMZET DISETUJUI ("oke cocok, catat dulu"), BELUM dikoding, tunggu "gas": mockup `doc/mockup/2026-11-20/dsm-transaksi-harian-omzet.html`, opsi O1 (tiap brand 2 sub-kolom UNIT|OMZET) + garis pemisah 1px warna brand pucat (color-mix 55%) di awal tiap brand, TOTAL SELLTHRU, dan OMZET; kolom OMZET total di kanan (header cokelat, format Rp x jt/M); baris Minggu tetap diarsir merah; Dealer Produktif dirapatkan. Backend: tambah omzet per brand per hari di `lib/core.php` (daily.brands sekarang hanya qty; metrics(...)['omzet'] sudah ada). Sekalian: bug sel VILLAON puncak (7 Sep) tampil kosong di app asli, belum ketemu penyebabnya (cek rule yang menimpa background sel puncak / kolom kedua dari kanan). Desktop saja.
- 2026-11-20 Tabel KPI per grup: TOTAL TIM + header menempel (mockup dsm-kpi-total-freeze.html) sudah dikodekan (592f7ac).
- 2026-11-20 Transaksi desktop: Kalender Sell Thru + Pola Hari diringkas 2 kolom DISETUJUI ("oke cocok"), BELUM dikoding, tunggu "gas": mockup `doc/mockup/2026-11-20/dsm-transaksi-kalender-pola.html` (kiri Kalender memanjang mengikuti tinggi kolom kanan; kanan atas Pola Hari Penjualan grafik pendek; kanan bawah tabel "Indeks Hari per Brand" + ringkasan; legenda masuk judul, keterangan jadi ⓘ). Desktop saja, HP tidak berubah. Kode: `v6Calendar`, `v6DowPanel` di assets/dsm.js.

- 2026-11-20 (paket lanjutan, semua SUDAH DIKODING di dev): Transaksi Harian + kolom OMZET per brand (2162d2d); Kalender Sell Thru + Pola Hari 2 kolom, tabel indeks hari padat (e5bc764); Beranda kartu KPI mint desktop + HP 2 kolom, progress tipis + chip growth vs bulan lalu (00f6406); Peringkat desktop: filter REGION (= Big Region, kolom wilayah) + RESET dalam satu baris (kolom tabel "REGION" diganti label SUB REGION; hierarki: Sub Region di bawah Big Region) (hanya menyaring tampilan, peringkat tetap dihitung dari seluruh cakupan); Peringkat mode ASM: kolom ASM diganti Σ SALES (backend `salesCount` di apiRank). Belum: Peringkat mode Telemarketing (belum dimockup). Dites hanya di harness lokal; perlu cek di app asli (pull dev + hard refresh).
- 2026-11-20 Peringkat: user minta panel "Cari & Kroscek KPI" turun (mockup doc/mockup/2026-11-20/dsm-peringkat-kroscek-bawah.html, opsi A di bawah baris filter / B paling bawah). User bilang "kaya gini" tapi belum "gas": BELUM DIKODING, konfirmasi opsi dulu.
- 2026-11-20 Popup Validasi (ST ITEL dst): user minta tabel dirapikan. Mockup doc/mockup/2026-11-20/dsm-validasi-tabel-rapi.html. Belum dikoding, tunggu "gas".
- 2026-11-20 Popup Validasi: tabel usulan + header H2 DISETUJUI dan DIKODING (desktop; HP header lama). Peringkat: Kroscek KPI opsi A dikoding (di bawah baris filter, desktop). Masih menunggu pilihan user: pemilih Sales/Telemarketing (mockup dsm-pilih-sales-telemarketing.html S1-S4), belum dikoding.
- 2026-11-20 Pemilih Sales/Telemarketing: opsi S4 (toggle di sejajar judul halaman) DISETUJUI dan DIKODING untuk KPI + Peringkat desktop (tm.js tmAttachTabs, tm.css). HP tetap tombol lama.
- 2026-11-20 Menu TIM rombak: user "oke cocok" pada opsi T1+T3 (doc/mockup/2026-11-20/dsm-tim-rombak-opsi.html): judul polos + toggle SALES/TELEMARKETING (S4), filter TINGKAT/BRAND/BIG REGION (baru, bertingkat ke REGION)/REGION, kartu mint ringkasan, tab EARLY WARNING (daftar+rincian+tabel lengkap, klik nama = rincian) | PENCAPAIAN TIM | PER INDIKATOR, target/aktual kolom terpisah di semua tabel, istilah status Tertinggal/Mengejar/Tembus, filter INDIKATOR di kanan baris tab, tabel rincian bisa digeser dengan kolom Sales menempel. BELUM DIKODING, tunggu "gas".
- 2026-11-20 PENAMAAN WILAYAH (instruksi user, berlaku semua menu/halaman, TAMPILAN LAYAR SAJA): Big Region -> "REGION"; Region (tengah) -> "SUB REGION"; Sub Region lama -> "AREA" (nama AREA gue pilih sendiri, user belum menyebut nama). Dikerjakan lewat assets/region_labels.js (menerjemahkan teks di layar; sumber kode tetap istilah lama, jadi label baru di kode harus ditulis dengan istilah LAMA: "Big Region" utk level atas, "Region" utk level tengah). Dikecualikan: halaman Admin (petunjuk template Import menyebut kolom file), Export Excel, template Import, database. Elemen bisa dikecualikan dengan atribut data-nolabel.
- 2026-11-20 Menu TIM T1+T3: user "oke cocok semua" (FINAL, belum "gas", BELUM DIKODING). Rincian final di mockup dsm-tim-rombak-opsi.html tab T1+T3: tab EARLY WARNING = daftar urut risiko (maks 5) + rincian Sales terpilih (kartu, tren 7 hari bertitik+angka+tanggal, tabel per brand TARGET/AKTUAL/%ACHV/LMTD/GROWTH tanpa TOTAL/ASM/Sub Region, bisa digeser, kolom Sales menempel) + panel "SEMUA SALES · TABEL LENGKAP" (klik nama = ganti rincian). Tab PENCAPAIAN TIM: kolom NAMA/ASM/SUB REGION, ST+DA (TARGET/AKTUAL/%ACHV/LMTD/GROWTH), NOO (T/A), SELL OUT, OMZET, pemisah tipis antar indikator, TOTAL menempel. Tab PER INDIKATOR: SALES/ASM/SUB REGION + per brand (T/A/%/LMTD/GROWTH) + pemisah antar brand + TOTAL; segmen INDIKATOR di kanan baris tab (ST/DA/NOO/SO/Omzet). Growth = chip persen saja. Filter atas: TINGKAT, BRAND, REGION, SUB REGION (bertingkat), FILTER LAIN. Istilah status: Tertinggal/Mengejar/Tembus. Header: judul polos + toggle SALES/TELEMARKETING.
- 2026-11-20 Menu TIM HP (B2: FILTER oren+panel, ringkasan satu strip mint, tab, EW tabel padat + lembar rincian, chip loncat) dikodekan di tim_v2.js (dipakai juga di HP; kode HP lama team() tidak dipakai lagi untuk non-telemarketing). Menu TIM T1+T3 DIKODEKAN (user "gas", desktop saja): `assets/tim_v2.js` + `assets/tim_v2.css` (dipanggil dari awal `team()` di dsm.js bila `!isMobile()&&!tmIsMe()`; HP tetap kode lama). page_header.js: halaman `tim` dikecualikan dari banner biru. Data: apiEarlyWarning (tren `daily7`) + apiTeam per brand (LMTD/growth), tanpa field API baru. Tambahan atas mockup: kolom RUN RATE + BUTUH/HARI + tombol LIHAT DETAIL KPI (baris & panel rincian), kartu RUN RATE di rincian. BELUM: toggle SALES/TELEMARKETING di TIM (S4 baru ada di KPI & Peringkat); panel lama "Ringkasan Analisa TIM" tidak dibawa (tidak ada di mockup). Filter: REGION (Big Region), SUB REGION (Region tengah), FILTER LAIN berisi tingkat bawah (tampil "AREA"). Diuji hanya di harness lokal.
- 2026-11-20 Menu TIM tren Early Warning (mockup dsm-tim-tren-mingguan.html, user "gasss"): di rincian Sales (desktop panel + HP bottom sheet) ada toggle PER HARI | PER MINGGU dan BATANG | GARIS (default PER MINGGU + BATANG). PER HARI = sebulan penuh (libur abu "libur", hari belum jalan putus-putus), tanpa pembanding. PER MINGGU = W1 1–7, W2 8–14, W3 15–21, W4 22–28, W5 29–akhir, bulan ini vs bulan lalu + tabel kecil (HK, UNIT, RATA/HARI tanpa desimal, BULAN LALU, VS BULAN LALU). Tabel per brand TETAP ada (tabel minggu hanya tambahan). Backend: `apiEarlyWarning` menambah `mdays`, `weeks`, `prevPeriod`, dan per baris `dm`, `wk`, `wkL` (dibungkus try/catch). BELUM dites ke DB asli (hanya php -l + harness).
- 2026-11-20 Menu TIM: (1) filter BRAND punya opsi SEMUA BRAND (value ALL) dan itu DEFAULT pas pertama buka TIM (mockup dsm-tim-filter-semua-brand.html, "gas"). Backend: `apiTeam` memakai helper `teamTarget_` (target ALL = jumlah target tiap brand device, sama seperti apiEarlyWarning) dan sell out ALL → brand null. BELUM dites ke DB asli. (2) Baris TOTAL di tabel lengkap (desktop) / tfoot + strip mint (HP) bisa diklik → rincian gabungan; default rincian = "AREA SAYA" (TOTAL), klik nama → rincian Sales + tombol ← AREA SAYA (mockup dsm-tim-total-rincian.html, "gas"). Judul mengikuti filter: "TOTAL · <wilayah>" bila filter dipersempit.
- 2026-11-20 Klaim Perdin balik nota: DIKODING sesuai mockup `doc/mockup/2026-11-20/dsm-klaim-perdin-balik-nota.html` (server `lib/pnota.php`, commit 9a0243b; UI `klaim/assets/screens.js` pnotaHtml/pnotaBind/R.pnota). Aturan DIKONFIRMASI user 2026-11-21 (lihat DECISIONS.md): tenggat = tgl pulang + pc_grace_days, telat memblokir klaim baru, sisa disetor / lebih jadi klaim reimburse, foto nota wajib. WAJIB jalankan klaim/migrate.php di DB asli (kolom claims.nota_* + tabel claim_rnotes). Dites lokal (SQLite + browser), BELUM di MySQL asli. Daftar "Perlu tindakan saya" sudah memuat balik nota (commit berikutnya). PDF: bagian Balik nota sudah dikoding (pn_pdf_html di lib/report.php).
- 2026-11-24: Dealer Baru panel "Order Lagi atau Nggak?" dirapikan, opsi A di `doc/mockup/2026-11-24/dsm-analisa-noo-funnel-rapi.html`, disetujui dan DIKODEKAN (`v6NooHtml` fun() di assets/dsm.js + CSS `.v6funTag/.v6funSub/.v6fv` di assets/dsm.css). Analisa masih di-hide.
- 2026-11-24: Mockup panel baru "Daftar Dealer Baru <bulan>" (1 baris = 1 dealer, chip brand NOO + qty, filter brand, cari, ringkasan NOO per brand, label "dealer baru" vs dealer lama buka brand baru, kartu di HP): `doc/mockup/2026-11-24/dsm-analisa-noo-list-dealer.html`. Sumber `v6ANA.noo.pairs` (m===current), tanpa ubah backend. Tunggu "gas".
- Cross-sell: user mau cek dulu bagian mana yang ditampilkan lagi. Jangan disentuh.
- 2026-11-24 ATURAN USER (berlaku umum): urutan kolom identitas dealer di tabel = KODE | NAMA DEALER | REGION | SALES (kode selalu sebelum nama).
- 2026-11-24 CATAT DULU (belum "gas"): rombak tab Analisa > Dealer Baru. Mockup desktop `doc/mockup/2026-11-24/dsm-analisa-dealer-baru-full.html`, HP `dsm-analisa-dealer-baru-mobile.html`. User bilang cocok ("keren") untuk panel Per Bulan; lainnya belum dikonfirmasi satu-satu. Isi:
  - Kartu KPI flat mint (label KAPITAL kecil tebal, angka besar tebal, ket abu, warna hijau tua #0F4C3A, border tipis #CDE6D8, tanpa garis kiri). FONT HARUS KONSISTEN (Inter, tanpa monospace di kartu & panel Per Bulan).
  - Order Lagi opsi A (SUDAH dikodekan, commit b46c33d).
  - Panel BARU "Daftar Dealer Baru <bulan>": kolom KODE | NAMA DEALER | REGION | SALES | BRAND NOO (tag 1 kotak warna brand, tanpa kotak kecil di dalam) | Σ BRAND | QTY | TGL NOO; garis kiri 4px (ungu = dealer baru, abu = dealer lama buka brand baru); filter brand berupa tombol; HP tetap tabel, KODE sticky, ketuk baris = bottom sheet. Sumber `v6ANA.noo.pairs`, tanpa ubah backend.
  - Panel Per Sales: user bilang "ga perlu" (di mockup dibuang). BELUM ditanya apakah tabel Per Sales yang sekarang di app benar-benar dihapus, jangan hapus dulu.
  - Panel Per Bulan: kolom "Bulan pertama" (100%) dibuang, ganti kolom DEALER BARU; sel 1 baris "41% | 30 dealer"; 4 tingkat hijau + legenda; bulan berjalan garis putus; baris RATA-RATA hanya dari bulan penuh.
  - Gaya tool Ary (gradasi) sudah dicoba untuk Per Sales dan DITOLAK user.
- 2026-11-24 ATURAN UMUM: font di kartu/tabel harus konsisten (jangan campur font biasa dengan monospace tanpa alasan).

- 2026-11-24 SUDAH DIKODEKAN (menggantikan catatan "CATAT DULU" di atas): menu Analisa tampil lagi, tab hanya Reaktivasi Dealer + Dealer Baru tanpa ikon (Cross-sell tetap di kode, disembunyikan lewat `V6ANA_HIDDEN_TABS`, `HIDDEN_PAGES` dikosongkan). Reaktivasi = papan per status (desktop 4 kolom + bar distribusi, HP bar % + kartu status opsi E). Dealer Baru = KPI mint, Order Lagi atau Nggak, Daftar Dealer Baru (KODE|NAMA|REGION|SALES|BRAND NOO|Σ BRAND|QTY|TGL NOO), cohort Per Bulan (hitungan NOO = pasangan dealer×brand). Panel Per Sales tidak dirender (kode `bySales` masih ada, tanya user mau dihapus atau tidak). Backend: `lib/api_analisa.php` pairs NOO ditambah field `df` (tanggal pertama dealer muncul, semua brand) untuk penanda dealer baru. Baris tabel membuka popup detail dealer yang ada (bukan bottom sheet baru). BELUM dites dengan data asli, hanya render test data stub + syntax check. CSS baru: `assets/analisa_rombak.css`.

- 2026-11-24 HP: Reaktivasi + Dealer Baru memakai bottom sheet (`v6Sheet`, `v6ReSheet`, `v6NooSheet`, CSS `ar*` di assets/analisa_rombak.css). Reaktivasi HP: ketuk kartu = bottom sheet (desktop tetap langsung detail dealer). Dealer Baru HP: pilih bulan + 4 kartu kategori (`v6NooMobSelHtml`) menyatu dengan daftar kartu; desktop tetap tabel + panel Order Lagi. Tombol "BUKA DETAIL LENGKAP DEALER" di sheet membuka detail dealer yang ada. Mockup: doc/mockup/2026-11-24/dsm-analisa-dealer-baru-mobile-v3.html, dsm-analisa-reaktivasi-bottomsheet.html. BELUM dites di HP/data asli (hanya syntax + render stub). Mode HP ditentukan saat render (`isMobile()`), tidak berubah otomatis saat layar diputar tanpa render ulang.

- 2026-11-24 DISETUJUI, BELUM DIKERJAKAN (tunggu instruksi "gas" dari Bro Ary, akan dikerjakan dengan model lain): model dropdown desktop (gabungan opsi A + D: kotak putih sudut ±8px, bayangan tipis, tombol panah terpisah di kanan; warna aktif BIRU DSM `#0B5CAD` opsi A, bukan hijau) diterapkan ke SEMUA dropdown versi desktop di DSM, KECUALI DSM Klaim (jangan disentuh). Mockup: `doc/mockup/2026-11-24/dsm-analisa-dropdown-biru.html` (warna) dan `dsm-analisa-dropdown-opsi.html` (bentuk). Saat ini CSS baru ada di `assets/analisa_rombak.css` (blok "model dropdown filter Analisa", hanya `#v6AnaFilter select`, warna aktif masih hijau tua `#0F4C3A` => harus diganti ke `#0B5CAD`). Daftar terbuka masih bawaan browser; dropdown kustom dengan kolom cari belum dibuat (opsional, tanya dulu). Awas aturan CSS lama berspesifisitas tinggi dan menu Klaim.

- 2026-11-24 SUDAH DIKODEKAN (menggantikan catatan "DISETUJUI, BELUM DIKERJAKAN" dropdown di atas): `assets/dropdown_std.css` + `assets/dropdown_std.js` (dimuat di index.php setelah analisa_rombak.css). Semua <select> tunggal di #app dan .modal, layar >=761px: kotak putih radius 8, bayangan tipis, tombol panah terpisah; aktif (opsi pertama "Semua…/ALL" dan yang dipilih bukan itu) = kelas `dd-on`, biru #0B5CAD. Lebar/tinggi tiap halaman tidak diubah. Kecualikan dengan atribut `data-nodd`. DSM Klaim (klaim/index.php) tidak memuat file ini. Blok dropdown hijau lama di analisa_rombak.css dihapus. Dicek render dengan CSS asli (dsm.css, page_tidy, form_kit, tim_v2, analisa_cross) di Chromium; BELUM dicek di semua halaman app asli.

- Brand: grafik "MTD vs LMTD per Type" + "Siapa yang paling mengubah angka? Top 3 & Bottom 3" disembunyikan lewat `V6GT_HIDE_CHARTS` (dsm.js). Set `false` untuk memunculkan lagi.
- Brand tab terpasang (mockup dsm-brand-tab-full.html). Belum dites di app dengan data asli (sticky offset, role Top/Me).
- Brand RINGKASAN (disetujui arahnya, belum dikoding): layout B (kartu brand + detail) + tabel lengkap gaya G2 (mockup dsm-brand-tabel-lengkap-opsi.html, tombol G2). Aturan: urutan TARGET dulu baru AKTUAL; tampilkan angka LMTD + selisih; ada kolom Omzet; desktop tabel terbuka otomatis, HP terlipat. Tab DETAIL BRAND digabung ke RINGKASAN. Cek dulu apakah pembanding LMTD omzet per brand tersedia di API.


## 2026-11-24 · Brand RINGKASAN SUDAH DIKODEKAN
- Rombakan RINGKASAN (layout B, tabel G2, kartu M3d, tren gap A) sudah masuk kode. Perlu dicek di Laragon/server dengan data asli: angka Sell Out LMTD per brand, tampilan role Top/Me, sticky tab.
- Keputusan sementara gue: chip SEMUA BRAND jadi default; tab MINGGUAN/DEALER pakai chip brand sendiri (tanpa SEMUA BRAND).

## DSM Klaim desktop non-sales — tahap 2 (disetujui mockup dsm-klaim-desktop-nonsales.html, belum dikodekan)
- Detail klaim 2 kolom (info+nota+BA kiri, foto besar+rute+riwayat kanan), bar aksi menempel bawah, tombol Sebelumnya/Berikutnya.
- Budget (HOS/FM): kartu per kategori + tabel per orang dengan bar %. Transfer (FT): tabel centang + tandai sudah ditransfer (FILE TRANSFER = fitur baru, konfirmasi dulu).
- Menu Balik nota: daftar perdin (perlu diperiksa/menunggu pemohon/lewat tenggat/selesai) + panel kanan; butuh API daftar balik nota + angka dana belum dipertanggungjawabkan.

## AI via OpenAI — menunggu tes user
- Penyedia OpenAI sudah dipasang (commit 163dd62), Gemini tetap bawaan/cadangan. Aktif lewat DSM_AI_PROVIDER/DSM_OPENAI_KEY/DSM_OPENAI_MODEL di config.local.php server.
- Dicatat, BELUM dikerjakan: longgarkan `aiSysPrompt_` supaya AI boleh jawab pertanyaan umum di luar DSM. Tunggu user selesai tes dan bilang "gas".

## 2026-11-26 · Klaim › Peran klaim pengguna (desktop) — DISETUJUI MOCKUP, BELUM DIKODEKAN
Mockup: `doc/mockup/2026-11-26/dsm-klaim-peran-ringkas.html`, tab **E · Semua kolom tetap ada** (user: "oke cocok", minta dicatat dulu, jangan koding).
- Kolom TIDAK boleh ada yang dihilangkan: centang, NAMA (avatar), USERNAME, JABATAN, PERAN, REGION, WILAYAH TUGAS, DITANGANI ADMIN, BADAN USAHA, AKTIF (toggle), UBAH ›. Tombol + PENGGUNA BARU dan chip peran lengkap (SEMUA, PEMOHON, ADMIN, RGM, HOS, FM, FT, AUDIT, SA) tetap.
- Baris chip region/area yang panjang diganti satu dropdown **REGION: Semua ▾** (berpencarian, multi-centang, ada jumlah orang per region).
- Kolom kiri: daftar Admin + jumlah orang (Semua / Belum ada Admin / per Admin) sebagai filter Ditangani Admin.
- Wilayah tugas panjang: 1 area + "+N area", isi lengkap saat hover.
- Bar tugaskan melayang di bawah tabel (Tugaskan ke Admin… / Kembali otomatis).
- Panel detail kanan (opsi C) dibuang karena tidak ada data yang disembunyikan.

## 2026-11-26 · Klaim › Hak klaim desktop + navbar — MOCKUP DISETUJUI ("oke cocok", minta dicatat dulu), BELUM DIKODEKAN
Mockup: `doc/mockup/2026-11-26/dsm-klaim-hak-desktop.html`.
- Tabel padat desktop menggantikan daftar kartu: NAMA, JABATAN, REGION, DITANGANI ADMIN (baru), BADAN USAHA, PENGAJUAN, TRANSPORTASI, ENTERTAIN, PERDIN, OPERASIONAL, SUPPORT DEALER, PETTY CASH, ATUR ›. Klik baris tetap buka halaman atur detail.
- Toggle penanganan di atas tabel: SEMUA ORANG / YANG SAYA TANGANI / BUKAN YANG SAYA TANGANI (dengan jumlah), diingat per perangkat. Kolom DITANGANI ADMIN: SAYA / nama Admin lain / Otomatis. Untuk Super Admin, "saya tangani" = yang di-assign ke dia sendiri.
- Dropdown REGION (gaya tab E), filter chip status, bar bawah Salin hak ke yang dipilih (fitur salin yang ada dipindah).
- Menu Hak klaim masuk sidebar grup KERJA (desktop). HP tidak berubah.
- Dikerjakan bersama Peran klaim pengguna tab E (user belum bilang "gas").

## 2026-11-26 · Regroup menu Admin — MOCKUP BELUM DISETUJUI, JANGAN KODING
Mockup: `doc/mockup/2026-11-26/dsm-admin-menu-regroup.html` (SEKARANG vs USULAN, interaktif). Tab baru **Region**; semua brand di Brand, region di Region, target di KPI & Target, KPI di KPI. Telegram (Sambungan Telegram + Notifikasi khusus) pindah ke Sistem; bukan tampilan gaya Telegram.
- Keputusan user: **Hari libur masuk Master Data** (bukan Region; mockup masih menaruh di Region, ubah dulu).
- Masih menunggu user: Kunci periode & Status Sync ke Sistem, Badan usaha tetap di Master Data.

## 2026-11-26 · DIKODEKAN (user: "gas semua")
- Regroup menu Admin (`assets/admin_revision.js` GROUPS): tab baru Region; Master Product → Brand; Penugasan telemarketing → KPI & Target; Hari libur & Badan usaha tetap di Master Data; Sambungan Telegram + Notifikasi khusus + Status Sync + Kunci periode + Catatan kualitas → Sistem (seksi Telegram / Data & penyimpanan / Log). Isi panel tidak berubah. Perlu dicek di server dengan akun Admin.
- Edit target: baris ASM gaya C (sub judul #DCE6F2 + badge OTOMATIS, sales menjorok) — `v6tAsm` di dsm.css/dsm.js.
- Edit target: warna zigzag per grup ASM opsi B (v6tz0/v6tz1), disetujui user.
- Edit target: Enter/↓ turun, Shift+Enter/↑ naik, Tab/Shift+Tab pindah kolom (ala Excel). Menunggu user pilih gaya kotak input halus (mockup dsm-edit-target-input-halus.html) + lebar 46 (disetujui, belum dikodekan).

## 2026-11-26 · Beranda › Wilayah › Heatmap bawaan tertutup — MOCKUP DISETUJUI ("oke cocok", minta dicatat dulu), BELUM DIKODEKAN
Mockup: `doc/mockup/2026-11-26/dsm-beranda-heatmap-lipat.html`.
- Panel HEATMAP NASIONAL · REGION × BRAND di tab WILAYAH jadi panel lipat gaya Summary History (latar #d5d4fc, garis kiri 5px #6663c4, bulatan panah, tombol BUKA/TUTUP). Bawaan TERTUTUP, status buka/tutup diingat per perangkat (localStorage).
- Cakupan hanya heatmap ini; panel lain di Beranda tidak diubah.

## Edit target — menunggu pilihan user (belum dikodekan)
- Lebar kotak input 46 disetujui. Gaya kotak lebih halus: mockup `dsm-edit-target-input-halus.html` opsi A/B/C, user belum memilih (rekomendasi C).

## 2026-11-26 · Beranda › tab KOMITMEN terpisah dari AKSI — MOCKUP DISETUJUI ("oke cocok"), BELUM DIKODEKAN
Mockup: `doc/mockup/2026-11-26/dsm-beranda-komitmen-tab.html`. Tab baru KOMITMEN (paling kanan setelah AKSI) berisi Komitmen minggu ini, Review minggu lalu, Komitmen tim. Tab AKSI tinggal Dibanding kemarin + Prioritas tindakan. Isi panel tidak berubah. Menunggu "gas" untuk koding.
- AI Assistant: tombol melayang HP (logo gelembung robot, bisa digeser, nempel tepi, label ASSISTANT 3 detik sekali per sesi) di assets/ai_fab.js/.css; disetujui user (mockup dsm-ai-tombol-final.html opsi 2, 3 detik). LAINNYA tetap ada. Cek di HP asli.
- DIKODEKAN (user: gas): Beranda tab KOMITMEN (desktop) + heatmap nasional lipat bawaan tertutup; Edit target lebar input 46. Gaya kotak input halus (A/B/C) masih menunggu pilihan.
- Edit target: gaya kotak input C (polos, border saat hover/fokus, placeholder –) dikodekan; sel wajib kosong tetap kuning.

## 2026-11-26 · Edit target › bentuk tabel seperti Pencapaian — MOCKUP DISETUJUI ("oke cocok"), BELUM DIKODEKAN
Mockup: `doc/mockup/2026-11-26/dsm-edit-target-gaya-pencapaian.html` (opsi USULAN).
- Kolom depan: # · NAMA · SUB REGION (NIK disembunyikan dari tampilan, tetap dipakai di belakang). Header abu muda (#EEF2F6), huruf kecil tebal; baris tipis berpemisah.
- Hanya judul kolom dan baris TOTAL yang tebal; nama, angka, baris ASM tidak tebal.
- Baris ASM di bawah timnya: latar zigzag biru/hijau, garis kiri 4px, badge OTOMATIS, sub region di kolom sendiri.
- Kotak input samar tapi kelihatan (garis tipis + garis bawah lebih tegas, hover biru, "–" saat kosong). Ukuran huruf seragam 12px.
- DIKODEKAN: tabel Edit target gaya Pencapaian. Heatmap nasional di Beranda DISEMBUNYIKAN sementara (flag HEAT_HIDE di index.php v7HeatFold; set false untuk munculkan lagi sebagai panel lipat).
- DIKODEKAN: Status pola dealer HP 2 kolom ringkas tanpa ikon (opsi B, dealer_status.css). Persen dari total (mis. 52,7%) disembunyikan sesuai mockup.
- DIKODEKAN: Edit dealer › Big Region default jadi dropdown (Super Admin) + Berlaku mulai; simpan lewat apiDealerRegionSave (Dealer_Region, kode induk). Admin biasa tetap readonly. Beranda mengikuti lewat RegionCore::route per tanggal.

## 2026-11-27 Validasi Region Dealer
- Panel Admin > Region "Validasi Region Dealer" (Super Admin): apiDealerRegionValidate di lib/bulk.php, v6RegionValidPanel di assets/dsm.js. Tanda curiga = wilayah sales beda dengan Big Region sekarang. Belum: popup dari kartu Beranda (opsi A), kolom Aktual. Hanya dites sintaks, belum di server.
