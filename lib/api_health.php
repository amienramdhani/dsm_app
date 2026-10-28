<?php
/** Health.gs — Cek Kesehatan Master. */
require_once __DIR__ . '/api_acct.php';

const HEALTH_STEPS = [['id' => 1, 'name' => 'Region Map'], ['id' => 2, 'name' => 'User & struktur'], ['id' => 3, 'name' => 'Product'], ['id' => 4, 'name' => 'Master Customer'], ['id' => 5, 'name' => 'PIC Dealer per Brand'], ['id' => 6, 'name' => 'ST → SO']];

function healthLev_(string $a, string $b): int {
    $A = mb_str_split($a); $B = mb_str_split($b); $la = count($A); $lb = count($B);
    if (abs($la - $lb) > 1) return 9;
    $d = [];
    for ($i = 0; $i <= $la; $i++) $d[$i] = [$i];
    for ($j = 0; $j <= $lb; $j++) $d[0][$j] = $j;
    for ($i = 1; $i <= $la; $i++) for ($j = 1; $j <= $lb; $j++) $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + ($A[$i - 1] === $B[$j - 1] ? 0 : 1));
    return $d[$la][$lb];
}

function apiHealthCheck($token) {
    $u = guard_($token, true);
    $issues = []; $started = microtime(true);
    $add = function ($step, $level, $problem, $fix, $action = '', $detail = '') use (&$issues) { $issues[] = ['step' => $step, 'level' => $level, 'problem' => $problem, 'fix' => $fix, 'action' => $action, 'detail' => $detail]; };
    $safe = function ($n) { try { return table_($n); } catch (Throwable $e) { return null; } };
    $T = fn($x) => text_($x); $up = fn($x) => js_upper(text_($x));
    // 1 · Region Map
    $rm = $safe('Region_Map') ?: []; $rmap = []; $rkeys = [];
    if (!$rm) $add(1, 'r', 'Sheet Region_Map kosong / tidak ada.', 'Isi Region Map di Admin › Master › Region Map.', 'regionmap');
    foreach ($rm as $r) {
        $k = $T($r['REGION'] ?? null); if ($k === '') continue;
        if (truthy($rmap[$up($k)] ?? null)) $add(1, 'r', 'REGION dobel di Region Map: ' . $k, 'Hapus salah satu di Admin › Master › Region Map.', 'regionmap');
        $rmap[$up($k)] = $T($r['WILAYAH_KPI'] ?? null); $rkeys[] = $k;
        if ($T($r['WILAYAH_KPI'] ?? null) === '') $add(1, 'r', 'REGION ' . $k . ' belum punya Big Region (WILAYAH_KPI).', 'Isi Big Region-nya di Region Map.', 'regionmap');
    }
    for ($i = 0; $i < count($rkeys); $i++) for ($j = $i + 1; $j < count($rkeys); $j++) {
        $a = $up($rkeys[$i]); $b = $up($rkeys[$j]);
        if ($a !== $b && mb_strlen($a) >= 6 && healthLev_($a, $b) === 1) $add(1, 'o', 'Region mirip (kemungkinan typo): ' . $rkeys[$i] . ' vs ' . $rkeys[$j], 'Kalau salah satunya typo, hapus/ganti di Region Map (region yang dipakai user tidak bisa dihapus — pindahkan user dulu).', 'regionmap');
    }
    // 2 · User & struktur
    $ms = $safe('Master_Sales') ?: []; $auth = []; $people = []; $byName = [];
    foreach ($safe('_Auth') ?: [] as $a) $auth[$T($a['NIK'] ?? null)] = $a;
    $active = function ($nik) use ($auth) { $a = $auth[(string) $nik] ?? null; return !$a || PortalCore::active($a['AKTIF'] ?? null); };
    try {
        $ag = readGrid_('_Auth') ?: []; $seenA = [];
        foreach (array_slice($ag, 1) as $r) {
            $raw = js_str($r[0] ?? ''); $k = js_trim($raw); if ($k === '') continue;
            if ($raw !== $k) $add(2, 'o', 'Akun ' . $k . ' di sheet _Auth tersimpan dengan spasi di NIK.', 'Aman setelah update ini (sudah otomatis dirapikan saat Update status massal). Kalau user itu tetap tidak bisa login, Reset PIN-nya.', 'users');
            if (truthy($seenA[$k] ?? 0)) $add(2, 'o', 'NIK ' . $k . ' dobel di sheet _Auth (' . ($seenA[$k] + 1) . ' baris).', 'Yang dipakai baris paling atas. Hapus baris bawah di sheet _Auth, lalu Reset PIN kalau user tidak bisa login.', 'users');
            $seenA[$k] = ($seenA[$k] ?? 0) + 1;
        }
    } catch (Throwable $e) {}
    try {
        try { $rcMeta = meta_(); } catch (Throwable $e2) { $rcMeta = snapshot_()['meta']; }
        $rc = rgmConflicts_($rcMeta);
        foreach (js_keys($rc) as $big) {
            $l = $rc[$big];
            $add(2, 'o', $big . ' punya ' . count($l) . ' RGM: ' . implode(', ', array_map(fn($x) => $x['nama'] . ' (' . $x['count'] . ' orang)', $l)) . '. Yang nyasar lewat: ' . implode('; ', array_map(fn($x) => implode(', ', $x['via']), array_slice($l, 1))) . '.', '1 Big Region hanya boleh 1 RGM. Perbaiki NIK ATASAN orang yang disebut (arahkan ke ASM/RGM ' . $l[0]['nama'] . '), atau perbaiki REGION-nya kalau dia sebenarnya bukan orang ' . $big . '.', 'users');
        }
    } catch (Throwable $e) {}
    foreach (array_values($ms) as $i => $r) {
        $nik = $T($r['NIK KARYAWAN'] ?? null); $role = PortalCore::position($r['POSISI'] ?? null, 2); $nama = $T($r['SALES'] ?? null); $reg = $T($r['REGION'] ?? null); $line = 'Master_Sales baris ' . ($i + 2);
        if ($nik === '') { $add(2, 'r', $line . ': NIK kosong.', 'Isi NIK atau hapus barisnya lewat Struktur Organisasi.', 'users'); continue; }
        if (!truthy(PortalCore::LEVEL[$role] ?? 0)) $add(2, 'r', $nama . ' (' . $nik . '): POSISI "' . js_str($r['POSISI'] ?? null) . '" tidak dikenal.', 'Pakai salah satu: ' . implode(', ', array_map('strval', array_keys(PortalCore::LEVEL))) . '.', 'users');
        if (isset($people[$nik])) {
            $p = &$people[$nik];
            if ($p['nama'] !== $nama) $add(2, 'r', 'NIK ' . $nik . ' punya dua nama: ' . $p['nama'] . ' / ' . $nama, 'Samakan nama, atau kalau beda orang ganti NIK salah satunya.', 'users');
            elseif (in_array($role, $p['roles'], true)) $add(2, 'r', 'NIK ' . $nik . ' (' . $nama . ') jabatan ' . $role . ' ditulis dua kali.', 'Hapus salah satu baris di Struktur Organisasi.', 'users');
            $p['roles'][] = $role;
            if ($role === 'SALES') { $p['sales'] = true; $p['portfolio'] = $reg; }
            if ((PortalCore::LEVEL[$role] ?? 0) > (PortalCore::LEVEL[$p['role']] ?? 0)) { $p['role'] = $role; $p['region'] = $reg; $p['atasan'] = $T($r['NIK ATASAN'] ?? null); }
            unset($p);
        } else $people[$nik] = ['nik' => $nik, 'nama' => $nama, 'role' => $role, 'roles' => [$role], 'region' => $reg, 'atasan' => $T($r['NIK ATASAN'] ?? null), 'sales' => $role === 'SALES', 'portfolio' => $role === 'SALES' ? $reg : ''];
    }
    $pkeys = js_keys($people);
    foreach ($pkeys as $nik) {
        $nik = (string) $nik; $p = $people[$nik]; $lv = PortalCore::LEVEL[$p['role']] ?? 0;
        $byName[$up($p['nama'])][] = $p;
        if ($lv && $lv < 4 && $p['role'] !== 'ADMIN' && !truthy($rmap[$up($p['region'])] ?? null) && !truthy($rmap[$up($p['portfolio'])] ?? null))
            $add(2, 'r', $p['nama'] . ' (' . $nik . ', ' . $p['role'] . '): REGION "' . js_or($p['region'], '(kosong)') . '" tidak ada di Region Map.', truthy($p['region']) ? 'Tambahkan "' . $p['region'] . '" di Region Map, atau ganti REGION user ke region yang ada (isi region/sub region, bukan Big Region).' : 'Isi REGION user di Akun pengguna › Edit.', truthy($p['region']) ? 'regionmap' : 'users');
        elseif ($lv && $lv < 4 && !array_key_exists($up($p['region']), $rmap) && js_some($rkeys, fn($k) => $up($k) === $up($p['region']) && $k !== $p['region']))
            $add(2, 'o', $p['nama'] . ': REGION "' . $p['region'] . '" beda huruf besar/kecil dengan Region Map.', 'Samakan penulisannya.', 'users');
        if ($p['atasan'] === $nik) $add(2, 'r', $p['nama'] . ' (' . $nik . '): atasan menunjuk diri sendiri.', 'Ganti NIK ATASAN di Akun pengguna › Edit.', 'users');
        elseif (truthy($p['atasan']) && !isset($people[$p['atasan']])) $add(2, 'r', $p['nama'] . ' (' . $nik . '): NIK ATASAN ' . $p['atasan'] . ' tidak terdaftar.', 'Kalau itu akun dobel orang yang sama, pakai Gabung akun. Kalau salah ketik, ganti NIK ATASAN di Akun pengguna › Edit.', 'users');
        $seen = []; $n = $nik;
        while (truthy($n) && isset($people[$n]) && !isset($seen[$n])) { $seen[$n] = 1; $n = $people[$n]['atasan']; }
        if (truthy($n) && isset($seen[$n])) $add(2, 'r', $p['nama'] . ' (' . $nik . '): struktur atasan berputar (siklus).', 'Periksa NIK ATASAN berantai mulai dari ' . $p['nama'] . '.', 'users');
    }
    foreach (js_keys($byName) as $k) {
        $l = $byName[$k];
        if (count($l) > 1 && (string) $k !== '') $add(2, 'o', 'Nama sama, NIK beda (kemungkinan akun dobel): ' . implode(' | ', array_map(fn($p) => $p['nama'] . ' · ' . $p['nik'], $l)), 'Kalau orangnya sama, pakai Gabung akun (pertahankan NIK yang dipakai atasan/bawahan).', 'merge');
    }
    $kids = [];
    foreach ($pkeys as $n) { $a = $people[(string) $n]['atasan']; if (truthy($a)) $kids[$a][] = (string) $n; }
    foreach ($pkeys as $n) { $n = (string) $n; $p = $people[$n]; if ($p['role'] === 'ASM' && !($kids[$n] ?? []) && !$p['sales']) $add(2, 'i', 'ASM ' . $p['nama'] . ' belum punya sales di bawahnya.', 'Isi NIK ATASAN sales-nya ke ' . $n . ', atau jadikan ASM rangkap sales / buat VACANT.', 'users'); }
    // 3 · Product
    $rules = []; foreach ($safe('Product_Rules') ?: [] as $r) $rules[$T($r['BRAND'] ?? null)] = 1;
    $badB = [];
    foreach ($safe('Master_Product') ?: [] as $r) { $b = $T($r['BRAND'] ?? null); if ($T($r['KODE BARANG'] ?? null) !== '' && !isset($rules[$b])) $badB[$b] = ($badB[$b] ?? 0) + 1; }
    foreach (js_keys($badB) as $b) $add(3, 'r', 'BRAND "' . js_or((string) $b, '(kosong)') . '" di Master Product belum ada di Product Rules (' . $badB[$b] . ' produk).', 'Tambah brand di Admin › Master › Product Rules (atau perbaiki BRAND produknya).', 'productrules');
    $prodRows=$safe('Master_Product')?:[]; $seenProd=[]; $emptyType=[]; $emptyCat=[]; $emptyName=[];
    foreach($prodRows as $r){$k=$T($r['KODE BARANG']??null);if($k==='')continue;if(isset($seenProd[$k]))$add(3,'r','KODE BARANG duplikat: '.$k.'.','Buka Master Product, bandingkan baris duplikat dan pertahankan satu kode yang benar.','product');$seenProd[$k]=1;if($T($r['NAMA BARANG']??null)==='')$emptyName[]=$k;if($T($r['TYPE']??null)==='')$emptyType[]=$k;if($T($r['KATEGORI PRODUK']??null)==='')$emptyCat[]=$k;}
    if($emptyName)$add(3,'o',count($emptyName).' produk belum punya NAMA BARANG. Contoh: '.implode(', ',array_slice($emptyName,0,5)).'.','Buka Master Product → filter kode tersebut → lengkapi NAMA BARANG.','product');
    if($emptyType)$add(3,'o',count($emptyType).' produk belum punya TYPE. Contoh: '.implode(', ',array_slice($emptyType,0,5)).'.','Buka Master Product → lengkapi TYPE. Gunakan autocomplete TYPE existing supaya penulisannya konsisten.','product');
    if($emptyCat)$add(3,'r',count($emptyCat).' produk belum punya KATEGORI PRODUK. Contoh: '.implode(', ',array_slice($emptyCat,0,5)).'.','Buka Master Product → lengkapi kategori. Kategori menentukan unit device vs aksesori.','product');
    try{$known=array_fill_keys(array_keys($seenProd),1);$st=db_()->query("SELECT kode_produk,COUNT(*) c FROM tx_st GROUP BY kode_produk");$unkp=[];while($x=$st->fetch(PDO::FETCH_ASSOC)){if(!isset($known[(string)$x['kode_produk']]))$unkp[(string)$x['kode_produk']]=(int)$x['c'];}if($unkp){arsort($unkp);$add(3,'r',count($unkp).' kode produk transaksi belum ada di Master Product. Contoh: '.implode(', ',array_slice(array_keys($unkp),0,5)).'.','Tambahkan kode tersebut lewat Master Product. Setelah lengkap, jalankan Cek Kesehatan lagi.','product');}}catch(Throwable $e){}
    // 4 · Master Customer
    $mc = $safe('Master_Customer') ?: []; $kodeNik = []; $unk = []; $kodes = []; $dealersOf = [];
    foreach (array_values($mc) as $i => $r) {
        $kode = $T($r['KODE CUSTOMER'] ?? null); $nik = $T($r['NIK SALES'] ?? null);
        if ($kode === '') { $add(4, 'r', 'Master_Customer baris ' . ($i + 2) . ': KODE CUSTOMER kosong.', 'Isi kodenya atau hapus barisnya.', 'dealer'); continue; }
        $kodes[] = $kode;
        if (array_key_exists($kode, $kodeNik) && $kodeNik[$kode] !== $nik) $add(4, 'r', 'Dealer ' . $kode . ' punya dua NIK default/fallback (' . $kodeNik[$kode] . ' & ' . $nik . ').', 'Master Customer tetap harus punya satu NIK default per dealer. Perbedaan pemegang per brand diatur lewat PIC Dealer per Brand.', 'picbrand');
        $kodeNik[$kode] = $nik;
        if (!isset($people[$nik])) $unk[$nik] = ($unk[$nik] ?? 0) + 1; else $dealersOf[$nik][$kode] = 1;
    }
    foreach (js_sort(array_map('strval', js_keys($unk)), fn($a, $b) => $unk[$b] - $unk[$a]) as $nik) $add(4, 'r', 'NIK SALES ' . js_or((string) $nik, '(kosong)') . ' memegang ' . $unk[$nik] . ' baris dealer tapi tidak terdaftar sebagai user.', 'Daftarkan NIK di Admin › User › Tambah user massal (POSISI SALES), atau pindahkan dealernya lewat Transfer dealer.', 'users');
    try {
        $nc = indukNameConflicts_();
        foreach (array_slice($nc, 0, 200) as $x) $add(4, 'o', 'Kode induk ' . $x['induk'] . ' punya ' . count($x['names']) . ' nama induk berbeda: ' . implode(' / ', $x['names']) . '.', 'Buka Admin › Dealer › Cari & edit dealer, cari ' . $x['induk'] . ', isi Nama induk sekali (otomatis berlaku untuk semua toko di induk itu).', 'cari & edit');
        if (count($nc) > 200) $add(4, 'o', '… dan ' . (count($nc) - 200) . ' kode induk lain dengan nama tidak seragam.', 'Upload dealer dengan nama induk yang benar juga otomatis menyeragamkan.', 'cari & edit');
    } catch (Throwable $e) {}
    $mg = excelMangledMap_($kodes); $km = []; foreach ($kodes as $k) $km[$k] = 1;
    foreach (js_keys($mg) as $n) if (isset($km[(string) $n])) $add(4, 'o', 'Kode ' . $n . ' dan ' . $mg[$n] . ' dua-duanya ada — kemungkinan ' . $n . ' adalah ' . $mg[$n] . ' yang rusak oleh Excel.', 'Cek dealernya. Kalau sama, hapus/benerin kode ' . $n . ' (format kolom kode = Teks di Excel).', 'dealer');
    // 5 · PIC Dealer per Brand (effective-dated). Cross-region sah dan bukan error.
    foreach (js_keys($dealersOf) as $nik) { $nik = (string) $nik; $p = $people[$nik] ?? null; if ($p && !$active($nik) && !preg_match('/^VAC/i', $nik)) $add(5, 'o', count($dealersOf[$nik]) . ' dealer masih memakai default/fallback ' . $p['nama'] . ' (' . $nik . ') yang akunnya NONAKTIF.', 'Atur PIC per brand ke Sales aktif. Jika seluruh brand memang pindah, gunakan Transfer dealer → Semua Brand agar fallback ikut diperbarui.', 'picbrand'); }
    $assignRows = dbaRows_(); $assignIdx = dbaIndex_($assignRows); $brandsPic = dbaPrimaryBrands_(); $cutover = dbaCutover_();
    $assignKeys = []; $ranges = [];
    foreach ($assignRows as $a) {
        $k = $a['dealer'].'\x1f'.$a['brand']; $assignKeys[$k] = 1; $ranges[$k][] = $a;
        if (!isset($kodeNik[$a['dealer']])) $add(5,'r','Assignment PIC menunjuk dealer yang tidak ada di Master Customer: '.$a['dealer'].' · '.$a['brand'].'.','Perbaiki/hapus assignment tersebut lewat PIC Dealer per Brand.','picbrand');
        if (!in_array($a['brand'],$brandsPic,true)) $add(5,'r','Assignment '.$a['dealer'].' memakai brand yang tidak aktif/valid: '.$a['brand'].'.','Pilih brand yang terdaftar di Product Rules.','picbrand');
        $pp=$people[$a['nik']]??null;
        if(!$pp) $add(5,'r','PIC '.$a['dealer'].' · '.$a['brand'].' menunjuk NIK tidak dikenal: '.$a['nik'].'.','Pilih Sales yang terdaftar.','picbrand');
        elseif(!$active($a['nik'])&&!preg_match('/^VAC/i',$a['nik'])) $add(5,'o','PIC '.$a['dealer'].' · '.$a['brand'].' adalah '.$pp['nama'].' ('.$a['nik'].') yang NONAKTIF.','Set / Transfer PIC brand ke Sales aktif dengan tanggal efektif yang benar.','picbrand');
    }
    foreach ($ranges as $k=>$list) {
        usort($list,fn($a,$b)=>strcmp($a['from'],$b['from']));
        for($i=1;$i<count($list);$i++){ $prev=$list[$i-1];$cur=$list[$i]; if($prev['status']==='ACTIVE'&&$cur['status']==='ACTIVE'&&($prev['to']===''||$prev['to'] >= $cur['from'])) { [$d,$b]=explode("\x1f",$k); $add(5,'r','Periode PIC tumpang tindih: '.$d.' · '.$b.' ('.$prev['from'].' s.d. '.js_or($prev['to'],'sekarang').' dan '.$cur['from'].' s.d. '.js_or($cur['to'],'sekarang').').','Rapikan riwayat effective date lewat PIC Dealer per Brand sebelum transaksi baru diproses.','picbrand'); break; } }
    }
    // Tanpa assignment eksplisit, Master Customer adalah baseline/fallback yang sah.
    // Mismatch hanya dihitung mulai cutover. Histori sebelum cutover dianggap accepted dan tidak pernah jadi warning.
    $snapPic = null;
    try { $snapPic = snapshot_(); } catch (Throwable $e) {}
    if($snapPic){
        $mm=[];$cutMonth=substr($cutover,0,7);$periods=js_sort_default(array_map('strval',js_keys($snapPic['raw']??[])));
        foreach($periods as $ppd){ if(strcmp($ppd,$cutMonth)<0)continue; $ref=(string)($snapPic['raw'][$ppd]??''); if($ref==='')continue;
            try{foreach(txEffRaw_($snapPic['meta'],dataRows_($ref)) as $t){$dt=PortalCore::iso($t['date']??null);if(!$dt||$dt<$cutover)continue;$nik=$T($t['nik']??null);$sku=$T($t['sku']??null);$cust=$T($t['customer']??null);$qty=(float)($t['qty']??0);if($qty<=0||$nik===''||$cust===''||!isset($snapPic['meta']['products'][$sku]))continue;$dealer=custInduk_($snapPic['meta'],$cust);$brand=$T($snapPic['meta']['products'][$sku]['brand']??null);if($brand===''||!in_array($brand,$brandsPic,true))continue;$own=dealerBrandOwner_($snapPic,$dealer,$brand,$dt,$assignIdx);if($own['nik']===''||$own['nik']===$nik)continue;$key=jkey([$dealer,$brand,$own['nik'],$nik]);if(!isset($mm[$key]))$mm[$key]=['dealer'=>$dealer,'brand'=>$brand,'owner'=>$own['nik'],'actual'=>$nik,'rows'=>0,'qty'=>0,'last'=>''];$mm[$key]['rows']++;$mm[$key]['qty']+=$qty;if($dt>$mm[$key]['last'])$mm[$key]['last']=$dt;}}
            catch(Throwable $e){}
        }
        uasort($mm,fn($a,$b)=>$b['rows']<=>$a['rows'] ?: abs($b['qty'])<=>abs($a['qty'])); $shown=0;
        foreach($mm as $m){ if($shown++>=200)break; $op=$people[$m['owner']]??null;$ap=$people[$m['actual']]??null;$ol=trim($m['owner'].($op?' — '.$op['nama']:''));$al=trim($m['actual'].($ap?' — '.$ap['nama']:''));$add(5,'o',$m['dealer'].' · '.$m['brand'].': PIC '.$ol.' tetapi sejak cutover ada '.$m['rows'].' transaksi / QTY '.number_format($m['qty'],0,',','.').' oleh '.$al.' (terakhir '.$m['last'].').','Jika PIC memang berubah, Set/Transfer PIC dengan Effective Date yang benar. Jika transaksi itu hanya irisan/backup sesaat, pertahankan PIC dan cek transaksinya.','picbrand'); }
        if(count($mm)>200)$add(5,'o','… dan '.(count($mm)-200).' mismatch PIC lain sejak cutover.','Buka PIC Dealer per Brand dan filter/search dealer terkait.','picbrand');
    }
    // 6 · info
    $snap = null;
    try { $snap = snapshot_(); } catch (Throwable $e) { $add(6, 'r', 'Data aplikasi gagal dimuat: ' . $e->getMessage(), 'Bereskan dulu semua masalah merah di langkah 1–4, lalu publish ulang.', ''); }
    $info = ['users' => count($people), 'dealers' => count($kodeNik), 'products' => count($safe('Master_Product') ?: []), 'regions' => count($rkeys), 'ms' => 0];
    if ($snap) { try { $ck = js_sort_default(array_map('strval', js_keys($snap['coverage'] ?? []))); $last = $ck ? end($ck) : null; $info['lastPeriod'] = $last ?: ''; $info['cutoff'] = $last ? js_or(($snap['coverage'][$last] ?? [])['cutoff'] ?? null, '') : ''; } catch (Throwable $e) {} }
    $vl = $safe('Version_Log') ?: [];
    if ($vl) { $lv2 = end($vl); $info['lastPublish'] = $T(js_or($lv2['WAKTU'] ?? null, js_or($lv2['TIME'] ?? null, js_or($lv2['TANGGAL'] ?? null, '')))); }
    $info['ms'] = (int) round((microtime(true) - $started) * 1000);
    $steps = [];
    foreach (HEALTH_STEPS as $s) { $l = js_filter($issues, fn($x) => $x['step'] === $s['id']); $steps[] = ['id' => $s['id'], 'name' => $s['name'], 'red' => count(js_filter($l, fn($x) => $x['level'] === 'r')), 'orange' => count(js_filter($l, fn($x) => $x['level'] === 'o')), 'info' => count(js_filter($l, fn($x) => $x['level'] === 'i'))]; }
    $order = ['r' => 0, 'o' => 1, 'i' => 2];
    $issues = js_sort($issues, fn($a, $b) => ($a['step'] - $b['step']) ?: ($order[$a['level']] - $order[$b['level']]));
    $firstRed = js_find($steps, fn($s) => $s['red'] > 0);
    $red = count(js_filter($issues, fn($x) => $x['level'] === 'r')); $orange = count(js_filter($issues, fn($x) => $x['level'] === 'o'));
    prop_set_('V6_HEALTH_SUM', js_stringify(['red' => $red, 'orange' => $orange, 'at' => now_ms()]));
    return ['at' => (new DateTimeImmutable('now', jkt_tz_()))->format('d/m/Y H:i'), 'steps' => $steps, 'issues' => array_slice($issues, 0, 800), 'total' => count($issues),
        'next' => $firstRed ? 'Mulai dari langkah ' . $firstRed['id'] . ' (' . $firstRed['name'] . ') — masih ada ' . $firstRed['red'] . ' masalah merah.' : (js_some($issues, fn($x) => $x['level'] === 'o') ? 'Tidak ada masalah merah. Cek yang oranye kalau sempat.' : 'Semua master sehat ✓'), 'info' => $info];
}
