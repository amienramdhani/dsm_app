<?php
/** Uji reuse mutasi stok (movesStore_) tanpa MySQL: tabel dsm_move ditiru di SQLite. Jalankan: php tools/tests/moves_store_test.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
require $root . '/lib/js.php';
$GLOBALS['pdo']=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$GLOBALS['pdo']->exec('CREATE TABLE dsm_move (snap TEXT, seq INT, tanggal TEXT, region TEXT, kode_customer TEXT, kode_produk TEXT, in_qty INT, out_qty INT, PRIMARY KEY(snap,seq))');
function db_(){ return $GLOBALS['pdo']; }
// store.php dimuat apa adanya, kecuali store_schema_() (DDL MySQL) dijadikan kosong.
$src = preg_replace('/function store_schema_\(\): void \{.*?\n\}\n/s', "function store_schema_(): void {}\n", file_get_contents($root . '/lib/store.php'), 1);
$tmp = tempnam(sys_get_temp_dir(), 'st') . '.php'; file_put_contents($tmp, $src); require $tmp; unlink($tmp);
$p=0;$f=0; function t($n,$ok){global $p,$f; $ok?$p++:$f++; echo ($ok?"  ✓ ":"  ✗ ")."$n\n";}
$mk=fn($n,$salt='')=>array_map(fn($i)=>['date'=>'2026-11-'.sprintf('%02d',1+$i%28),'region'=>'R'.($i%3),'customer'=>'C'.$i.$salt,'sku'=>'S'.($i%7),'inQty'=>$i%5,'outQty'=>$i%2],range(0,$n-1));
$m1=$mk(3000);
// publikasi pertama: tanpa prior -> tulis penuh
$r1=movesStore_('A',$m1,null);
t('tanpa prior: tulis baris baru (snap=A)', $r1['snap']==='A' && movesCount_('A')===3000);
// prior berisi moveSnap+moveHash dari r1; isi sama -> dipakai ulang
$prior=['stock'=>['moveSnap'=>$r1['snap'],'moveHash'=>$r1['hash']]];
$r2=movesStore_('B',$m1,$prior);
t('isi sama: pakai ulang snap A, tidak ada baris B', $r2['snap']==='A' && movesCount_('B')===0);
// isi beda 1 nilai -> tulis baru
$m2=$m1; $m2[10]['outQty']=9;
$r3=movesStore_('C',$m2,$prior);
t('isi beda 1 nilai: tulis baru snap C', $r3['snap']==='C' && movesCount_('C')===3000);
// urutan beda -> hash beda
$m3=$m1; [$m3[0],$m3[1]]=[$m3[1],$m3[0]];
t('urutan beda: hash beda', movesHash_($m3)!==movesHash_($m1));
// prior tanpa moveHash (snapshot lama) -> tulis penuh
$r4=movesStore_('D',$m1,['stock'=>['moveSnap'=>'A']]);
t('prior tanpa moveHash: tulis penuh', $r4['snap']==='D' && movesCount_('D')===3000);
// baris snap lama rusak (jumlah beda) walau hash sama -> tulis ulang
db_()->exec("DELETE FROM dsm_move WHERE snap='A' AND seq<5");
$r5=movesStore_('E',$m1,$prior);
t('baris lama kurang walau hash sama: tulis ulang', $r5['snap']==='E' && movesCount_('E')===3000);
// prior format lama (moves di JSON) -> tulis penuh
$r6=movesStore_('F',$m1,['stock'=>['moves'=>true,'moveHash'=>$r1['hash']]]);
t('prior format lama (moves di JSON): tulis penuh', $r6['snap']==='F');
// prior hasil penciutan tetap kompatibel (key moveSnap/moveHash saja)
$slim=['id'=>'X','stock'=>array_intersect_key(['moveSnap'=>'D','moveHash'=>movesHash_($m1),'lain'=>1],['moveSnap'=>1,'moveHash'=>1])];
$r7=movesStore_('G',$m1,$slim);
t('prior yang diciutkan tetap bisa reuse', $r7['snap']==='D');
echo "\nHasil: $p lolos, $f gagal\n"; exit($f?1:0);
