/* Isi menu Panduan DSM. Ubah teks di sini; dipakai menu Panduan di aplikasi. roles: 'all' | ['SALES','ASM',...] (prefix match). */
window.DSM_GUIDE={
groups:[
 {id:'mulai',title:'Mulai'},
 {id:'beranda',title:'Beranda',page:'beranda'},
 {id:'customer',title:'Dealer',page:'customer'},
 {id:'brand',title:'Brand',page:'brand'},
 {id:'kpi',title:'KPI',page:'kpi'},
 {id:'admin',title:'Admin',page:'admin',roles:['ADMIN','SUPER ADMIN']}
],
glossary:[
 ['ST (Sell Thru)','Penjualan dari kita ke dealer. Ini indikator utama target sales.'],
 ['SO (Sell Out)','Penjualan dari dealer ke konsumen akhir.'],
 ['NOO','New Open Outlet: dealer baru yang mulai order pada periode berjalan.'],
 ['Dealer Aktif','Dealer yang punya order pada periode terpilih.'],
 ['Dealer IDDLE','Dealer-brand yang kembali order ST (QTY net positif) setelah tepat 2 bulan penuh kosong. Contoh Oktober: Juli order, Agustus–September kosong, Oktober order = 1. Jeda 3 bulan atau lebih serta dealer baru tidak dihitung. ALL menjumlahkan pasangan dealer-brand.'],
 ['DOS','Days of Stock: perkiraan stok cukup untuk berapa hari penjualan, dihitung dari stok dibagi laju jual harian.'],
 ['Target','Angka yang harus dicapai pada periode terpilih.'],
 ['Aktual / MTD','Month To Date: total dari tanggal 1 sampai data terakhir yang dipublish.'],
 ['LMTD','Last Month To Date: periode yang sama (tanggal 1 sampai tanggal yang sama) pada bulan lalu.'],
 ['Growth','MTD dibanding LMTD. Rumus: MTD ÷ LMTD − 1.'],
 ['% Capai','Aktual ÷ Target.'],
 ['Estimasi / Proyeksi','Perkiraan akhir bulan dari laju harian berjalan dikali sisa hari kerja.'],
 ['Induk & Outlet','Satu dealer induk bisa punya beberapa kode outlet. Angka induk = gabungan outlet-outletnya.'],
 ['Cakupan','Wilayah/tim yang boleh dilihat akun ini. Berubah sesuai jabatan.'],
 ['Publish','Saat admin mengunggah data terbaru. Semua angka mengikuti publish terakhir, bukan real-time.']
],
topics:[
 {id:'login',group:'mulai',title:'Login & Navigasi',roles:'all',
  intro:'Cara masuk, berpindah menu, dan memahami batas data pada akun Anda.',
  baca:[['Login','Masuk dengan NIK dan PIN. PIN bisa diganti dari menu Akun (di HP: Lainnya › Ganti PIN).'],['Perangkat','Satu akun dibatasi maksimal 5 perangkat. Kelola di menu Perangkat Saya.'],['Periode','Pilihan periode di bagian atas menentukan bulan yang dianalisa pada hampir semua menu.'],['Cakupan','Pilihan cakupan menentukan wilayah/tim. Sales hanya melihat datanya sendiri.']],
  aksi:[['Desktop','Menu ada di bilah atas. Klik judul panel untuk membuka/menutup.'],['HP','Menu utama di bawah (Beranda, Dealer, Brand, Transaksi). Menu lain di Lainnya.']],
  kasus:'Pindah akun di perangkat yang sama? Keluar dulu, lalu login ulang. Data akun sebelumnya tidak dibawa.',
  faq:[['Angka tidak berubah padahal ada order baru?','Data mengikuti publish terakhir dari admin, bukan real-time.'],['Menu Tim/Analisa tidak ada?','Menu mengikuti jabatan. Sales tidak punya menu tersebut.']]},
 {id:'kamus',group:'mulai',title:'Kamus Istilah',roles:'all',glossary:true,intro:'Arti semua istilah yang muncul di DSM.'},

 {id:'b-ringkas',group:'beranda',title:'Ringkasan Pencapaian',roles:'all',
  intro:'Kartu di bagian atas Beranda: gambaran cepat target, aktual, dan proyeksi untuk periode dan cakupan terpilih.',
  baca:[['Target','Target periode terpilih.'],['Aktual','Aktual sampai data terakhir publish.'],['% Capai','Aktual ÷ Target.'],['Estimasi','Laju harian × sisa hari kerja, ditambahkan ke aktual.'],['Growth','Dibanding LMTD (bulan lalu, tanggal sama).']],
  warna:'Hijau: capaian ≥ 100%. Kuning: 80–99%. Merah: di bawah 80%. Setiap indikator punya warna sendiri: ST biru, SO oranye, Dealer Aktif hijau tosca, NOO ungu, Omzet cokelat.',
  aksi:[['Desktop','Hover angka untuk tooltip. Klik kartu untuk rincian bila tersedia.'],['HP','Tap kartu untuk membuka rincian.']],
  kasus:'Awal pekan: lihat % Capai dan Estimasi. Bila Estimasi di bawah 100%, lanjut ke kartu region untuk mencari penyebabnya.',
  faq:[['Kenapa Estimasi berubah tiap publish?','Laju harian dihitung ulang dari data terbaru.'],['Growth minus artinya?','MTD lebih rendah dari periode yang sama bulan lalu.']]},
 {id:'b-tren',group:'beranda',title:'Tren Harian',roles:'all',
  intro:'Grafik per hari pada periode berjalan, dibanding bulan lalu, untuk melihat ritme order.',
  baca:[['Batang','Nilai harian. Batang pudar adalah hari berjalan yang belum final.'],['Garis target','Target harian kumulatif.'],['LMTD','Pembanding bulan lalu pada tanggal yang sama.'],['Growth','MTD ÷ LMTD − 1.']],
  aksi:[['Desktop','Hover batang untuk angka harian.'],['HP','Tap batang; rincian muncul di bawah grafik. Putar ke landscape untuk layar penuh.']],
  kasus:'Bila batang minggu ini konsisten di bawah garis target, dorong order sebelum akhir bulan.',
  faq:[['Angka hari ini kecil?','Data mengikuti publish terakhir.']]},
 {id:'b-region',group:'beranda',title:'Kartu Wilayah & Tim',roles:'all',
  intro:'Perbandingan region/tim dengan persen proyeksi, aktual, dan metrik Target, Aktual, Today, LMTD, Growth.',
  baca:[['Persen proyeksi','Estimasi capaian akhir bulan wilayah tersebut.'],['Today','Aktual hari ini.'],['LMTD · Growth','Perbandingan dengan bulan lalu.']],
  warna:'Warna kartu mengikuti ambang capaian (hijau/kuning/merah). Di desktop kartu region tampil 3 per baris.',
  aksi:[['Desktop','Klik kartu untuk melihat rincian.'],['HP','Tap kartu; bagian rincian terbuka.']],
  kasus:'Cari kartu merah lebih dulu, lalu buka rinciannya untuk mencari ASM/sales yang tertinggal.',
  faq:[['Kenapa kartu hanya sedikit?','Daftar mengikuti cakupan akun.']]},

 {id:'c-daftar',group:'customer',title:'Daftar Dealer',roles:'all',
  intro:'Dealer dalam cakupan Anda beserta status order.',
  baca:[['Kode & Nama','Identitas dealer.'],['Status order','Menunjukkan dealer yang belum order pada periode.'],['Induk','Dealer induk menggabungkan outlet-outletnya.']],
  aksi:[['Desktop','Cari dengan nama/kode/wilayah, klik baris untuk detail.'],['HP','Ketik di kolom cari lalu tap kartu dealer.']],
  kasus:'Cari dealer yang belum order, lalu hubungi lewat kontak di detail dealer.',
  faq:[['Dealer tidak ketemu?','Dealer di luar cakupan akun tidak ditampilkan.']]},
 {id:'c-history',group:'customer',title:'History Dealer per Brand',roles:'all',
  intro:'Riwayat penjualan dealer per bulan dan per brand: Sell Thru, Sell Out, omzet.',
  baca:[['Band brand','Nama brand tetap di tengah area kolomnya saat tabel digeser ke kanan.'],['Kolom indikator','Setiap indikator punya warna header sendiri sebagai pembeda.']],
  aksi:[['Desktop','Geser tabel ke kanan; nama dealer tetap terlihat.'],['HP','Geser; kolom pertama menempel di kiri.']],
  kasus:'Bandingkan beberapa bulan terakhir untuk melihat apakah dealer menurun atau hanya musiman.',
  faq:[['Riwayat transaksi periode ini kosong padahal ada history?','Pada dealer induk, transaksi tercatat di kode outlet; bila kosong, laporkan ke admin.']]},

 {id:'br-capai',group:'brand',title:'Pencapaian per Brand',roles:'all',
  intro:'Target, aktual, dealer pembeli, dan kontribusi tiap brand.',
  baca:[['Kontribusi','Porsi brand terhadap total.'],['Dealer pembeli','Jumlah dealer yang membeli brand tersebut.']],
  aksi:[['Desktop','Pilih brand untuk rincian tipe dan dealer.'],['HP','Tap brand.']],
  kasus:'Pilih brand dengan capaian rendah, lalu lihat tipe mana yang menahan.',
  faq:[['Kenapa total brand beda dengan Beranda?','Cakupan atau periode yang dipilih berbeda.']]},
 {id:'br-proyeksi',group:'brand',title:'Proyeksi & Analisa Mingguan',roles:'all',
  intro:'Proyeksi Sell Thru dan perbandingan minggu ke minggu (WoW) serta bulan ke bulan (MoM).',
  baca:[['WoW','Perubahan dibanding minggu sebelumnya.'],['MoM','Perubahan dibanding bulan sebelumnya.'],['Minggu 5','Tampil “-” bila bulan tidak punya minggu ke-5.']],
  aksi:[['Desktop','Hover titik tren untuk detail.'],['HP','Tap titik.']],
  kasus:'Pakai WoW untuk menilai apakah dorongan minggu ini berhasil.',
  faq:[]},

 {id:'k-skor',group:'kpi',title:'Skor & Indikator KPI',roles:'all',
  intro:'Target, capaian, bobot indikator, dan skor akhir.',
  baca:[['Bobot','Kontribusi tiap indikator pada skor akhir.'],['Capaian','Aktual ÷ Target per indikator.'],['Dealer IDDLE','Hitungan per brand: Juli > 0, Agustus ≤ 0, September ≤ 0, Oktober > 0. Satu dealer-brand dihitung sekali. Histori ST belum lengkap ditandai menunggu; bukan nol.'],['Skor','Capaian dikalikan bobot, lalu dijumlahkan.']],
  warna:'Header tiap indikator punya warna berbeda (ST biru, SO oranye, Dealer Aktif tosca, NOO ungu, Omzet cokelat).',
  aksi:[['Desktop','Buka node pohon KPI untuk rincian level di bawahnya.'],['HP','Tap baris untuk membuka.']],
  kasus:'Saat 1-on-1, bandingkan skor tiap anggota pada indikator yang sama.',
  faq:[['Cakupan KPI berbeda tiap akun?','Ya, mengikuti jabatan dan region.']]},

 {id:'a-target',group:'admin',title:'Target & Edit KPI',roles:['ADMIN','SUPER ADMIN'],
  intro:'Mengatur target bulanan per sales dan target khusus KPI.',
  baca:[['Target Bulanan per Sales','Admin › Master › Bulk master. Template terisi; ubah PERIODE TARGET untuk menyalin ke bulan lain.'],['Target Khusus KPI','Target khusus untuk indikator KPI.'],['Clone ke region lain','Salin target/KPI seluruh region atau satu kelompok saja; tiap kelompok bisa dilewati atau ditimpa.']],
  aksi:[['Desktop','Gunakan tombol Clone di samping Simpan & publish.']],
  kasus:'Awal bulan: salin target bulan lalu ke bulan baru, lalu sesuaikan yang berubah.',
  faq:[['Apakah clone menimpa data?','Hanya bila Anda memilih Timpa; pilihan Lewati menjaga data lama.']]}
]};

// Telemarketing uses the same DSM guide renderer and role visibility.
DSM_GUIDE.groups.push({id:'telemarketing',title:'Telemarketing'});
DSM_GUIDE.glossary.push(['Penugasan dealer–brand','Pasangan dealer dan brand yang ditangani satu Telemarketing. Dealer sama dapat ditangani Telemarketing berbeda jika brand berbeda.'],['Admin PIC Telemarketing','Admin yang ditunjuk Super Admin untuk mengelola penugasan, target, dan aktual manual Telemarketing lintas region.'],['Umur FU','Hari sejak FU terakhir; jika belum pernah FU dihitung sejak mulai penugasan.'],['Refnote','Referensi atau catatan identitas order yang ikut dicetak dalam PDF.'],['Badan usaha','Nama perusahaan dan logo yang disimpan pada catatan order / PDF.'],['Target fallback','Target manual yang dipakai jika target Sales yang sesuai tidak tersedia.']);
DSM_GUIDE.topics.push(
{id:'tm-akses',group:'telemarketing',title:'Penugasan & menu Telemarketing',roles:'all',intro:'Telemarketing memiliki tim, KPI, dan peringkat terpisah dari Sales.',baca:[['Cakupan','Bisa melintasi Sales, ASM, RGM, dan region. Data operasional hanya dari dealer–brand yang ditugaskan.'],['Atasan utama','Satu RGM untuk struktur pelaporan; tidak membatasi penugasan lintas region.'],['Menu','Beranda, Dealer, Brand, Analisa, Transaksi, DOS, KPI, Peringkat, Follow Up, dan Catatan Order. Detail dealer memakai tampilan dan histori Sales yang sama.']],aksi:[['Kontak dealer','Alamat dan nomor HP dapat diajukan melalui alur perubahan kontak existing. PIC Sales dealer tidak diganti oleh Telemarketing.']],faq:[['Penugasan berubah kapan?','Akses dealer dan FU berubah langsung. Cakupan KPI mulai bulan berikutnya, kecuali penugasan pertama tanpa perpindahan dari cakupan bulan ini, atau koreksi bulan berjalan yang dinyatakan Admin. Pasangan bulan ini pada pemegang lama harus dilepas melalui koreksi sebelum dipindahkan efektif bulan ini. Histori bulan lalu tetap utuh.']]},
{id:'tm-kpi',group:'telemarketing',title:'KPI & peringkat Telemarketing',roles:'all',intro:'Aktual otomatis berasal dari transaksi resmi Sales pada dealer–brand penugasan.',baca:[['Target','Seluruh dealer Sales dapat mengikuti target Sales yang sesuai. Dealer pilihan atau campuran memakai target manual terpisah. Target Sales tidak tersedia: isi target manual / fallback.'],['Aktual manual','Indikator tertentu memakai target sekaligus aktual manual oleh Admin PIC. Nilai kosong belum final; 0 adalah nilai yang sah. Target 0 tidak diwajibkan.'],['Perhitungan','Capaian = aktual ÷ target × 100. Skor dibatasi cap; bobot indikator yang berlaku dinormalisasi di kelompok. KPI akhir = rata-rata skor kelompok yang berlaku.'],['Aturan','Indikator dapat dibatasi brand, TYPE, minimum unit per dealer–brand, serta beberapa aturan minimum yang digabung AND / OR.'],['Rincian','KPI sendiri memiliki sumber aktual dan dealer yang berkontribusi. Telemarketing lain hanya melihat indikator, bobot, capaian, dan skor bobot.']],aksi:[['Manager','Menu KPI dan Peringkat memakai tab Sales / Telemarketing. KPI pribadi Telemarketing terlihat utuh; rincian dealer tetap mengikuti kewenangan area. Aktivitas Telemarketing tersedia di menu tersendiri.']],faq:[['Order atau FU menambah aktual?','Tidak. Keduanya pegangan operasional; aktual otomatis hanya dari transaksi resmi Sales.']]},
{id:'tm-fu',group:'telemarketing',title:'Follow Up & daftar prioritas',roles:'all',intro:'Catat hasil komunikasi dan jadwal berikutnya pada setiap dealer–brand.',baca:[['Filter','Cari dealer / kode / Sales, brand, Big Region, region, dan status FU.'],['Prioritas','Belum pernah FU, lama tidak FU, jadwal lewat, FU hari ini, dan terpantau. Ambang lama tidak FU ditentukan Super Admin di profil Telemarketing.']],aksi:[['Catat FU','Isi tanggal, metode, hasil, catatan, dan tanggal FU berikutnya. Riwayat mengikuti periode yang dipilih.']],faq:[['Transaksi baru mereset umur FU?','Tidak. Umur FU dihitung dari komunikasi yang dicatat.']]},
{id:'tm-order',group:'telemarketing',title:'Catatan order & PDF',roles:'all',intro:'Order untuk pegangan Telemarketing, tanpa persetujuan Admin.',baca:[['Identitas','Pilih badan usaha, tanggal, region, refnote, dan sales person. Pembuat order ditampilkan terpisah.'],['Dealer','Cari nama / kode dari dealer penugasan, atau isi dealer baru secara manual.'],['Barang','Campurkan master dan barang baru dalam satu order; warna dapat diketik. Harga bersifat opsional.'],['PDF','Pratinjau dan unduhan memakai layout yang sama. Nama, logo, dealer, dan sales person disimpan pada snapshot order.']],aksi:[['Penjual','Default Sales PIC dealer; dapat diganti nama Telemarketing sendiri, Sales terdaftar, atau nama Sales baru yang diketik manual.'],['Simpan','Simpan draft atau order. Klik Pratinjau / PDF lalu Download PDF.']],faq:[['Badan usaha berubah, PDF lama ikut berubah?','Tidak. Order tersimpan menggunakan snapshot identitas saat disimpan.']]}
);

// Tambahan v12: ikon, ringkasan satu baris, rumus, dan contoh hitungan (dipakai tampilan Panduan di HP).
(function(){
 const D=window.DSM_GUIDE,T=id=>D.topics.find(t=>t.id===id);
 const meta={login:['🔑','Masuk, menu bawah, dan menu Lainnya'],kamus:['📖','LMTD, MTD, ST, SO, DOS, dan lainnya'],'b-ringkas':['📊','Angka utama dan cara bacanya'],'b-tren':['📈','Grafik penjualan per hari'],'b-region':['👥','Perbandingan wilayah dan tim'],'c-daftar':['🏪','Cari dan filter dealer'],'c-history':['🕘','Riwayat penjualan dealer per brand'],'br-capai':['🏷️','Target dan aktual tiap brand'],'br-proyeksi':['🔮','Perkiraan akhir bulan'],'k-skor':['🎯','Cara skor dihitung'],'a-target':['🛠️','Target dan pengaturan KPI'],'tm-akses':['📞','Penugasan dan menu Telemarketing'],'tm-kpi':['🏆','KPI dan peringkat Telemarketing'],'tm-fu':['📝','Follow up dan daftar prioritas'],'tm-order':['🧾','Catatan order dan PDF']};
 Object.keys(meta).forEach(id=>{const t=T(id);if(t){t.ik=meta[id][0];t.sub=meta[id][1];}});
 const kamusEx=[['MTD','Total dari tanggal 1 sampai hari ini. Contoh: sampai tanggal 14 total 1.200 unit.'],['LMTD','Periode yang sama bulan lalu. Contoh: tanggal 1 sampai 14 bulan lalu total 1.000 unit.'],['Growth','MTD ÷ LMTD − 1. Contoh: 1.200 ÷ 1.000 − 1 = +20%.']];
 D.glossary.forEach(g=>{const x=kamusEx.find(k=>k[0]===g[0].split(' ')[0].replace(/\W.*$/,'')||g[0].startsWith(k[0]));});
 const k=T('kamus');if(k){k.contoh='MTD 1.200 unit, LMTD 1.000 unit.\nGrowth = 1.200 ÷ 1.000 − 1 = +20%.';k.rumus='Growth = MTD ÷ LMTD − 1';}
 const set=(id,rumus,contoh,tip)=>{const t=T(id);if(!t)return;if(rumus)t.rumus=rumus;if(contoh)t.contoh=contoh;if(tip)t.tip=tip;};
 set('b-ringkas','% Capai = Aktual ÷ Target × 100%','Aktual 840 unit, target 1.000 unit, LMTD 700 unit.\n% Capai = 840 ÷ 1.000 = 84%.\nGrowth = 840 ÷ 700 − 1 = +20%.\nArtinya capaian 84% dari target dan 20% lebih tinggi dari LMTD.','Di awal bulan % Capai bisa kecil tapi Growth tinggi. Baca keduanya bersamaan.');
 set('b-region','Growth = Aktual ÷ LMTD − 1','Wilayah A: aktual Rp 480 jt, LMTD Rp 400 jt.\nGrowth = 480 ÷ 400 − 1 = +20%.','Wilayah kecil bisa punya Growth besar karena angka dasarnya kecil. Cek juga omzetnya.');
 set('br-capai','Sisa target = Target − Aktual','Brand X: target 500 unit, aktual 350 unit.\n% Capai = 350 ÷ 500 = 70%.\nSisa target = 500 − 350 = 150 unit.\nBila sisa 10 hari kerja, perlu 150 ÷ 10 = 15 unit per hari.','Lihat sisa target per hari kerja, bukan hanya persen.');
 set('br-proyeksi','Estimasi = Aktual ÷ hari berjalan × total hari periode','Hari ke-10 dari 30, aktual 300 unit, target 1.000 unit.\nEstimasi = 300 ÷ 10 × 30 = 900 unit (90% dari target).\nKurang 100 unit, perlu naik sekitar 5 unit per hari dari sekarang.','Estimasi hanya perkiraan. Akhir bulan biasanya lebih ramai.');
 set('k-skor','Skor = jumlah (Capaian × Bobot)','Indikator 1: capaian 90%, bobot 60% = 54.\nIndikator 2: capaian 100%, bobot 40% = 40.\nSkor akhir = 54 + 40 = 94.','Perhatikan indikator berbobot besar lebih dulu karena pengaruhnya paling kuat.');
 set('c-history',null,'Bulan ini ST 50 dan SO 30. Bulan lalu ST 40 dan SO 35.\nST naik 25% (50 ÷ 40 − 1). SO turun 14% (30 ÷ 35 − 1).\nST naik tapi SO turun: stok dealer bisa menumpuk.','ST jauh di atas SO beberapa bulan berturut-turut berarti stok dealer menumpuk.');
 D.groups.splice(D.groups.findIndex(g=>g.id==='kpi'),0,{id:'pantau',title:'Pantau',page:'dos'});
 D.topics.push(
 {id:'p-dos',group:'pantau',title:'DOS (Days of Stock)',roles:'all',ik:'📦',sub:'Stok cukup untuk berapa hari',intro:'Perkiraan berapa hari stok dealer bertahan dengan kecepatan jual (Sell Out) 30 hari terakhir.',baca:[['Estimasi stok','Stok dealer yang dihitung dari data Sell Thru dan Sell Out.'],['SO 30 hari','Sell Out 30 hari kalender terakhir.'],['Rata-rata per hari','SO 30 hari dibagi 30.'],['Batas','Default: kurang dari 7 hari = menipis, lebih dari 45 hari = stok tinggi. Batas bisa berbeda per region sesuai aturan Admin.']],rumus:'DOS = Estimasi stok ÷ (SO 30 hari ÷ 30)',contoh:'Dealer A, type X: stok 24 unit, SO 30 hari = 60 unit.\nRata-rata per hari = 60 ÷ 30 = 2 unit.\nDOS = 24 ÷ 2 = 12 hari, status CUKUP.\nBila stok tinggal 10: DOS = 5 hari, status MENIPIS.',status:[['MENIPIS','DOS di bawah batas minimum'],['HABIS','Stok 0 tetapi masih ada Sell Out'],['STOK TINGGI','DOS di atas batas maksimum'],['CUKUP','DOS di antara batas minimum dan maksimum'],['BELUM BERGERAK','Ada stok, Sell Out 30 hari = 0'],['TIDAK AKTIF','Stok 0 dan Sell Out 0'],['PERLU CEK DATA','Data ST/SO belum lengkap, jadi tidak dihitung']],warna:'Merah: menipis atau habis. Oranye: stok tinggi. Hijau: cukup. Abu-abu: belum bergerak, tidak aktif, atau perlu cek data.',aksi:[['HP','Pindah antar kelompok kolom lewat chip di atas tabel. Tap baris untuk rincian.']],tip:'Bila banner "ST belum lengkap" muncul, angka DOS wilayah itu belum bisa dipercaya penuh.',kasus:'Cari baris MENIPIS lebih dulu, lalu hubungi dealer atau tim sebelum stok habis.'},
 {id:'c-histst',group:'customer',title:'Histori ST/SO Dealer',roles:'all',ik:'📉',sub:'Riwayat ST dan SO di detail dealer',intro:'Di detail dealer, tab Histori ST/SO membandingkan Sell Thru (masuk) dan Sell Out (keluar) tiap bulan.',baca:[['Brand','Hanya brand yang pernah order yang tampil, plus pilihan Semua.'],['Periode','Pilih 6, 12, atau 24 bulan.'],['Garis','Biru: Sell Thru. Oranye: Sell Out.'],['Panah','▲ naik dan ▼ turun dibanding bulan sebelumnya.']],contoh:'Bulan ini ST 50 dan SO 30. Bulan lalu ST 40 dan SO 35.\nST naik 25% (50 ÷ 40 − 1). SO turun 14% (30 ÷ 35 − 1).',tip:'ST di atas SO terus-menerus berarti stok dealer menumpuk.',aksi:[['HP','Tap baris bulan untuk rincian.']]},
 {id:'br-pola',group:'brand',title:'Pola Hari Penjualan',roles:'all',ik:'📅',sub:'Hari mana paling ramai',intro:'Rata-rata unit per hari kerja, dipisah per hari dalam seminggu.',baca:[['Warna','Makin pekat berarti makin ramai.'],['Unit per hari','Tampilan awal. Brand kecil lebih mudah dibaca dalam unit daripada persen.']],contoh:'Rata-rata semua hari kerja brand Motopad: 4 unit.\nRata-rata hari Senin: 6 unit.\nSelisih = 6 − 4 = +2 unit, jadi Senin lebih ramai 2 unit dari rata-rata.',aksi:[['HP','Tap sel untuk melihat unit hari itu, rata-rata semua hari kerja, dan selisihnya.']],tip:'Persen bisa menyesatkan untuk brand yang penjualannya sedikit.'},
 {id:'br-tumbuh',group:'brand',title:'Pertumbuhan per Type per Sales',roles:'all',ik:'📊',sub:'Type dan sales yang naik atau turun',intro:'Membandingkan penjualan MTD dengan LMTD per type atau per sales.',baca:[['Penggerak angka','3 type atau sales yang paling menaikkan dan paling menurunkan total, berdasarkan selisih unit.'],['Batang tipis','Panjang MTD dan LMTD tiap type.'],['Abu-abu','LMTD di bawah 20 unit dianggap volume kecil, persennya kurang bermakna.']],rumus:'Selisih unit = MTD − LMTD',contoh:'Type X: MTD 60 unit, LMTD 40 unit.\nSelisih = 60 − 40 = +20 unit.\nGrowth = 60 ÷ 40 − 1 = +50%.',aksi:[['HP','Tap type untuk melihat sales yang naik atau turun terbesar.']],tip:'Urutkan menurut selisih unit, bukan persen, supaya yang volumenya kecil tidak menyesatkan.'},
 {id:'m-pasang',group:'mulai',title:'Pasang DSM di HP',roles:'all',ik:'📲',sub:'Seperti aplikasi, tanpa Play Store',intro:'DSM bisa dipasang ke layar utama supaya dibuka seperti aplikasi.',langkah:{Android:['Buka DSM di Chrome.','Tap menu ⋮ di kanan atas.','Pilih Pasang aplikasi atau Tambahkan ke layar utama.','Tap Pasang. Ikon DSM muncul di layar utama.'],iPhone:['Buka DSM di Safari, bukan Chrome.','Tap tombol Bagikan (kotak dengan panah ke atas).','Pilih Tambah ke Layar Utama, lalu Tambah.','Buka DSM dari ikon di layar utama.']},tip:'Di iPhone, pemasangan ke Layar Utama wajib bila ingin menerima notifikasi (iOS 16.4 ke atas).'},
 {id:'m-notif',group:'mulai',title:'Notifikasi',roles:'all',ik:'🔔',sub:'Data baru dan insight harian',intro:'DSM bisa mengirim notifikasi saat data baru masuk dan satu insight penting per hari. Fitur ini aktif setelah dinyalakan Admin.',langkah:{Android:['Buka Lainnya, lalu Pengaturan notifikasi.','Tap Aktifkan, lalu pilih Izinkan.','Pilih jenis notifikasi dan jam tenang.'],iPhone:['Pasang DSM ke Layar Utama lebih dulu.','Buka DSM dari ikon di layar utama.','Buka Lainnya, lalu Pengaturan notifikasi, tap Aktifkan, lalu Izinkan.']},tip:'Notifikasi hanya berisi data sesuai wilayah dan akses akun Anda.'}
 );
})();
