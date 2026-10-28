<?php
/** DSM migration: Brand & Klasifikasi terpadu + dukungan export transaksi RAW. Safe to rerun. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('memory_limit','2048M'); set_time_limit(0); chdir(__DIR__.'/..'); $_SERVER['REMOTE_ADDR']='cli';
foreach (glob(__DIR__ . '/../lib/*.php') as $f) require_once $f;

echo "DSM Brand & Klasifikasi migration\n";
$g = readGrid_('Product_Rules');
if (!$g) throw new RuntimeException('Product_Rules tidak ditemukan.');
$oldHeads = array_map('text_', $g[0]);
$newHeads = $oldHeads;
foreach (BRAND_RULE_EXTRA_HEADS as $h) if (!in_array($h,$newHeads,true)) $newHeads[]=$h;
$rows=[];
foreach (array_slice($g,1) as $r) {
    $o=[]; foreach($oldHeads as $i=>$h)$o[$h]=$r[$i]??''; if(text_($o['BRAND']??null)!=='')$rows[]=$o;
}
// Infer kategori dari produk existing (mayoritas per klasifikasi).
$catCount=[];
foreach(optionalTable_('Master_Product') as $r){$b=js_upper(text_($r['BRAND']??null));$c=text_($r['KATEGORI PRODUK']??null);if($b!==''&&$c!=='')$catCount[$b][$c]=($catCount[$b][$c]??0)+1;}
$infer=[];foreach($catCount as $b=>$m){arsort($m);$infer[$b]=(string)array_key_first($m);}
$seen=[];$now=gmdate('c');
foreach($rows as &$o){$b=text_($o['BRAND']??null);$k=js_upper($b);$seen[$k]=1;$qty=PortalCore::active($o['MASUK_QTY']??null);if(text_($o['BRAND_UTAMA']??null)==='')$o['BRAND_UTAMA']=$b;if(text_($o['KATEGORI']??null)==='')$o['KATEGORI']=$infer[$k]??($qty?'HP':'ACC');if(text_($o['AKTIF']??null)==='')$o['AKTIF']='TRUE';if(!array_key_exists('CATATAN',$o))$o['CATATAN']='';if(!array_key_exists('UPDATED_BY',$o))$o['UPDATED_BY']='';if(!array_key_exists('UPDATED_AT',$o))$o['UPDATED_AT']='';}
unset($o);
// Master_Brand yang belum punya Product Rule (mis. TECNO) dibuat rule utama otomatis.
foreach(brandMaster_() as $m){$b=$m['brand'];$k=js_upper($b);if(isset($seen[$k]))continue;$rows[]=['BRAND'=>$b,'BRAND_UTAMA'=>$b,'MASUK_QTY'=>'true','KATEGORI'=>$infer[$k]??'HP','AKTIF'=>$m['aktif']?'TRUE':'FALSE','CATATAN'=>$m['catatan']?:'Rule utama dibuat otomatis saat migrasi','UPDATED_BY'=>'MIGRATION','UPDATED_AT'=>$now];$seen[$k]=1;echo "✓ Rule utama dibuat: {$b}\n";}
$before = $g; $db = snapshot_();
checkpoint_('MIGRATION','Brand & Klasifikasi terpadu');
try {
    $out=[];foreach($rows as $o)$out[]=array_map(fn($h)=>$o[$h]??'',$newHeads);
    putTable_('Product_Rules',$newHeads,$out);
    $meta=meta_();validateConfigChange_($db,$meta);
    publish_($meta,$db['raw'],$db['coverage'],'MIGRATION','BRAND_CLASSIFICATION_SCHEMA');
} catch(Throwable $e) { restoreGrid_('Product_Rules',$before); throw $e; }

echo "✓ Product_Rules diperluas: ".implode(', ',$newHeads)."\n";
echo "✓ Kategori klasifikasi diisi dari Master Product existing; rule tanpa produk default HP/ACC sesuai MASUK_QTY.\n";
echo "✓ Snapshot dipublish ulang agar mapping brand terbaru langsung aktif.\n";
echo "✓ Export transaksi RAW tidak membutuhkan tabel database tambahan.\n";
echo "Selesai. Login ulang / Ctrl+F5 lalu buka Admin → Master → Brand & Klasifikasi.\n";
