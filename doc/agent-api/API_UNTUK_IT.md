# DSM Agent API — Dokumen untuk Tim IT

Versi: 0.2 (2026-11-16) · Status: **sudah dikoding di branch dev, uji di server tes**
Pemilik: Ary Hidayat (DSM) · Pengguna: bot Telegram tim IT (OpenClaw + GPT)

---

## 1. Gambaran singkat

DSM membuka API **baca-saja** supaya bot Telegram bisa menjawab pertanyaan data (sell thru/sell out, KPI, DOS, dealer, dll).

- Bot **tidak pernah** login pakai PIN/password user.
- User menghubungkan akun Telegram-nya sendiri lewat **kode 6 digit** dari halaman **Telegram Agent** di DSM (klik nama di pojok kiri bawah).
- Setiap pertanyaan dijawab **sesuai role user DSM tersebut** (Sales hanya lihat datanya sendiri, dst). Aturan hak akses sama persis dengan aplikasi web DSM.
- Sambungan berlaku **30 hari tetap** sejak dihubungkan, lalu harus dihubungkan ulang.

```
User Telegram ──chat──> Bot IT (OpenClaw + GPT)
                            │  Authorization: Bearer <BOT_KEY>
                            │  X-Telegram-User-Id: <id dari Telegram>
                            ▼
                     DSM  /agent_api.php
                            │  cek key → cek sambungan → cek user aktif & role
                            ▼
                  fungsi baca DSM yang sama dengan aplikasi web
```

---

## 2. SYARAT WAJIB di sisi bot (tidak boleh diubah)

Keamanan DSM bergantung pada tiga hal ini. Kalau salah satunya dilanggar, data bisa bocor ke orang yang tidak berhak.

1. **`X-Telegram-User-Id` diisi oleh kode bot dari update Telegram (`message.from.id`), BUKAN oleh GPT.**
   GPT tidak boleh bisa menentukan atau mengubah nilai ini. Tool/skill yang dipanggil GPT hanya menerima parameter data (periode, brand, dealer, dst); header identitas ditempel oleh kode di luar GPT. Kalau GPT yang mengisi, orang bisa menyuruh bot "anggap saya Super Admin".
2. **`BOT_KEY` disimpan di environment/config server bot**, tidak ditulis di prompt, file skill, log chat, atau repo.
3. **Tidak ada cache jawaban yang dipakai lintas user.** Jawaban untuk user A tidak boleh dipakai untuk menjawab user B.

Tambahan yang sangat disarankan:
- Mesin bot jangan dipasangi skill komunitas sembarangan (skill bisa membaca key).
- Pesan kode 6 digit tidak perlu disimpan setelah dikirim ke DSM.
- Jangan pernah minta user mengirim PIN DSM ke bot.

---

## 3. Autentikasi

| Header | Isi | Keterangan |
|---|---|---|
| `Authorization` | `Bearer <BOT_KEY>` | Satu key untuk bot, dibuat Super Admin DSM. Disimpan DSM dalam bentuk hash, bisa dicabut kapan saja. |
| `X-Telegram-User-Id` | angka ID Telegram | Wajib di semua request kecuali `health`. |

Semua request lewat HTTPS. Request tanpa key valid ditolak `401`.

---

## 4. Alur hubungkan akun

1. User buka DSM → klik **nama di pojok kiri bawah** (HP: menu **Lainnya**) → **Telegram Agent** → **Buat kode** → muncul kode 6 digit (berlaku 5 menit, sekali pakai).
2. User mengirim kode itu ke bot.
3. Bot memanggil:

```
POST /agent_api.php?r=link
Authorization: Bearer <BOT_KEY>
X-Telegram-User-Id: 123456789
Content-Type: application/json

{"code":"482719","username":"rudi_sup"}
```

Jawaban sukses:
```json
{"ok":true,"result":{"nama":"Rudi S.","role":"SUPERVISOR","cakupan":"Cirebon 2","berlaku_sampai":"2026-12-10","sisa_hari":30}}
```

4. Selanjutnya setiap pertanyaan cukup memakai `X-Telegram-User-Id` yang sama.

Pemutusan sambungan terjadi otomatis bila: 30 hari habis, user memutus sendiri dari halaman Telegram Agent, Admin memutus dari panel Sambungan Telegram, akun dinonaktifkan, atau PIN user diganti/direset.

---

## 5. Format jawaban

Sukses:
```json
{"ok":true,"result":{...},"meta":{"periode":"2026-11","sd_tanggal":"2026-11-09","halaman":1,"total_halaman":3}}
```

Gagal:
```json
{"ok":false,"code":"LINK_EXPIRED","message":"Sesi 30 hari sudah habis. Hubungkan ulang dari DSM: klik nama Anda > Telegram Agent."}
```

`message` sudah berbahasa Indonesia dan boleh langsung diteruskan ke user.

| HTTP | `code` | Arti | Saran bot |
|---|---|---|---|
| 401 | `BAD_KEY` | BOT_KEY salah/dicabut | Hubungi admin DSM, jangan diteruskan ke user |
| 403 | `NOT_LINKED` | Telegram ID belum terhubung | Minta user buka DSM > nama > Telegram Agent |
| 403 | `LINK_EXPIRED` | Lewat 30 hari | Minta hubungkan ulang |
| 403 | `LINK_REVOKED` | Diputus user/Admin, akun nonaktif, atau PIN diganti | Minta hubungkan ulang |
| 400 | `BAD_CODE` | Kode salah/kedaluwarsa/sudah dipakai | Minta kode baru |
| 403 | `FORBIDDEN` | Data di luar hak akses role user | Teruskan pesan apa adanya |
| 400 | `BAD_PARAM` | Parameter salah | Perbaiki parameter |
| 429 | `RATE_LIMIT` | Terlalu banyak request | Tunggu sesuai header `Retry-After` |
| 500 | `SERVER` | Gangguan server | Coba lagi nanti |

---

## 6. Parameter umum

| Parameter | Contoh | Keterangan |
|---|---|---|
| `periode` | `2026-11` | Format YYYY-MM. Default bulan berjalan |
| `nik` | `SLS-1180` | Opsional. Persempit ke satu orang/tim di bawah user. NIK dicari lewat `r=orang`. Di luar cakupan user → `FORBIDDEN` |
| `brand` | `OPPO` | Opsional |
| `halaman`, `per_halaman` | `1`, `50` | Untuk jawaban berbentuk daftar. Default 50, maks 100 |

Angka mengikuti aturan DSM: rata-rata unit/dealer dibulatkan, persentase boleh desimal. Istilah: "dealer" (bukan outlet), "aktual" (bukan realisasi). Growth bulan berjalan = MTD vs LMTD.

---

## 7. Daftar resource (semua GET kecuali `link`)

Format: `GET /agent_api.php?r=<resource>&<parameter>`

**Sistem**

| Resource | Isi | Parameter |
|---|---|---|
| `health` | Cek API hidup (tanpa Telegram ID) | - |
| `me` | Nama, role, cakupan, sisa hari sambungan | - |
| `link` (POST) | Hubungkan akun pakai kode | body `code`, `username` |
| `akan_habis` | Telegram ID yang sambungannya habis ≤ 3 hari (cukup BOT_KEY) | - |
| `orang` | Daftar orang dalam cakupan user (nik, nama, role, wilayah, region). **Pakai ini untuk mengubah nama → NIK** | `cari`, `role` |

**Penjualan & KPI**

| Resource | Isi | Parameter |
|---|---|---|
| `ringkasan` | Aktual ST/SO, target, achievement, growth, per brand + daftar dealer | `periode`, `nik` |
| `eksekutif` | Ringkasan level manajemen | `periode`, `nik` |
| `kpi` | Pohon KPI (bobot & capaian) | `periode`, `nik` |
| `kpi_tim` | KPI per anggota tim | `periode`, `nik` |
| `kpi_orang` | KPI satu orang | `periode`, `nik` (wajib) |
| `peringkat` | Peringkat sales | `periode`, `nasional=1`, `level` |
| `peringkat_saya` | Posisi user di peringkat | `periode`, `nik` |
| `tim` | Pencapaian tim per level | `periode`, `level`, `brand`, `nik` |
| `early_warning` | Yang berisiko tidak capai | `periode`, `level`, `brand`, `metrik`, `nik` |
| `potensi` | Potensi tambahan | `periode`, `nik` |
| `mingguan` | Analisa W1–W5 | `periode`, `nik` |
| `perbandingan` | Perbandingan periode / ST vs SO | `periode`, `nik`, `mode` |
| `growth_type` | Pertumbuhan per type | `periode`, `brand`, `nik` |

**Dealer, stok, transaksi**

| Resource | Isi | Parameter |
|---|---|---|
| `dealer_cari` | Cari dealer dalam cakupan (maks 30) | `cari` (min 2 huruf), `periode` |
| `dealer` | Detail + stok satu dealer | `dealer` (kode, wajib), `periode` |
| `dealer_riwayat` | Ringkasan riwayat dealer | `brand`, `dari`, `sampai`, `cari`, `level`, `nik` |
| `dealer_tutup` | Daftar pengajuan dealer tutup | - |
| `dos` | Kondisi stok dealer (7 kondisi DOS) | `periode`, `nik` |
| `transaksi` | Ringkasan transaksi per kelompok (bukan baris mentah) | `periode`, `dari`, `sampai` (YYYY-MM-DD), `nik` |

**Telemarketing & lainnya**

| Resource | Isi | Parameter |
|---|---|---|
| `telemarketing` | Tim TM | `periode` |
| `tm_kpi` | KPI TM | `periode`, `nik`, `brand` |
| `tm_peringkat` | Peringkat TM | `periode`, `brand` |
| `order` | Daftar Form Order | `periode` |
| `komitmen` | Komitmen mingguan | - |
| `hari_libur` | Daftar hari libur | - |

**Catatan bentuk jawaban:** `result` = data yang sama dengan yang dipakai layar DSM untuk menu tersebut (nama field mengikuti DSM, bisa berbeda antar resource). DSM memangkas daftar panjang (paging di `meta.halaman`, `meta.total_halaman`) dan menyamarkan nomor telepon. Sarankan GPT membaca nama field dari jawaban, bukan menebak. Kalau ada resource yang jawabannya terlalu besar/membingungkan untuk GPT, laporkan ke Admin DSM untuk diringkas.

Resource yang tidak tersedia untuk role tertentu (mis. menu yang memang tidak dibuka untuk Telemarketing) akan dijawab `FORBIDDEN` dengan pesan dari DSM.

**Tidak dibuka sama sekali** (apa pun role-nya): manajemen user/PIN/2FA/perangkat, panel Super Admin, upload ST/SO, publish, approval, edit/transfer dealer, koreksi transaksi, target & bobot KPI (ubah), backup, notifikasi, config, modul Klaim.

---

## 8. Batasan

- Rate limit awal: 30 request/menit per Telegram ID, 300 request/menit per BOT_KEY.
- Satu jawaban maks 100 baris; sisanya lewat `halaman`.
- Data baris mentah (ratusan ribu transaksi) tidak disediakan. Pakai resource ringkasan.
- Nomor telepon dealer/kontak selalu disamarkan (contoh `0812****789`).
- Semua request dicatat (waktu, Telegram ID, user DSM, resource, parameter, status).

---

## 9. Contoh lengkap

Pertanyaan user: "MTD sell out tim Rudi berapa?"

1. Cari NIK Rudi:
```
GET /agent_api.php?r=orang&cari=rudi
Authorization: Bearer <BOT_KEY>
X-Telegram-User-Id: 123456789
```
```json
{"ok":true,"result":[{"nik":"SPV-0231","nama":"RUDI S.","role":"SUPERVISOR","wilayah":"JAWA BARAT","region":"CIREBON 2"}],
 "meta":{"periode":"2026-11","cakupan":"Nasional","role":"SUPER ADMIN"}}
```
2. Ambil ringkasan tim Rudi:
```
GET /agent_api.php?r=ringkasan&periode=2026-11&nik=SPV-0231
```
3. GPT membaca angka aktual, target, achievement, dan growth dari `result`, lalu menjawab.

(Nama & angka contoh, bukan data asli.)

---

## 10. Pengingat H-3 (opsional untuk bot)

`GET /agent_api.php?r=akan_habis` (cukup BOT_KEY, tanpa Telegram ID) mengembalikan daftar `telegram_id`, `berlaku_sampai`, `sisa_hari` untuk sambungan yang habis ≤ 3 hari. Bot cukup memanggil ini sekali sehari dan mengirim pengingat. `r=me` juga mengembalikan `sisa_hari` per user.

---

## 11. Kontak

Pertanyaan teknis / minta BOT_KEY / cabut akses: Admin DSM.
