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
