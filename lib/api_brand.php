<?php
/** Menu admin Brand: tambah / ubah catatan / nonaktifkan. Nonaktif hanya menyembunyikan brand dari pilihan upload baru; histori & laporan tetap utuh. */
require_once __DIR__ . '/common.php';

const BRAND_HEADS = ['BRAND', 'AKTIF', 'CATATAN', 'UPDATED_BY', 'UPDATED_AT'];
function brandMaster_(): array {
    if (isset($GLOBALS['DSM_BRM'])) return $GLOBALS['DSM_BRM'];
    $g = readGrid_('Master_Brand'); $out = [];
    if ($g) { $h = array_map('text_', $g[0]); $ix = array_flip($h);
        foreach (array_slice($g, 1) as $r) { $b = text_($r[$ix['BRAND'] ?? 0] ?? null); if ($b === '') continue;
            $out[js_upper($b)] = ['brand' => $b, 'aktif' => js_upper(text_($r[$ix['AKTIF'] ?? 1] ?? 'TRUE')) !== 'FALSE', 'catatan' => text_($r[$ix['CATATAN'] ?? 2] ?? null), 'by' => text_($r[$ix['UPDATED_BY'] ?? 3] ?? null), 'at' => text_($r[$ix['UPDATED_AT'] ?? 4] ?? null)]; } }
    return $GLOBALS['DSM_BRM'] = $out;
}
function brandInactive_(): array { $o = []; foreach (brandMaster_() as $k => $v) if (!$v['aktif']) $o[$v['brand']] = true; return $o; }
function brandGuard_($token): array { return guard_($token, true); }
function brandRows_(array $meta): array {
    $use = [];
    foreach ($meta['products'] ?? [] as $p) { $b = text_($p['brand'] ?? null); if ($b === '') continue; $k = js_upper($b);
        if (!isset($use[$k])) $use[$k] = ['brand' => $b, 'products' => 0, 'device' => 0]; $use[$k]['products']++; if (truthy($p['device'] ?? null)) $use[$k]['device']++; }
    $m = brandMaster_(); $rows = [];
    foreach (array_unique(array_merge(array_keys($use), array_keys($m))) as $k) {
        $rows[] = ['brand' => $m[$k]['brand'] ?? $use[$k]['brand'], 'aktif' => $m[$k]['aktif'] ?? true, 'catatan' => $m[$k]['catatan'] ?? '', 'products' => $use[$k]['products'] ?? 0, 'device' => $use[$k]['device'] ?? 0, 'updatedBy' => $m[$k]['by'] ?? '', 'updatedAt' => $m[$k]['at'] ?? ''];
    }
    return js_sort($rows, fn($a, $b) => lcn_($a['brand'], $b['brand']));
}
function apiBrandAdmin($token) { brandGuard_($token); $db = snapshot_(); return ['rows' => brandRows_($db['meta']), 'classifications'=>brandRuleRows_(), 'parents'=>brandRuleParents_(), 'categories'=>brandRuleCategories_()]; }
function brandWrite_(string $brand, bool $aktif, string $catatan, string $nik): void {
    ensureTable_('Master_Brand', BRAND_HEADS); $g = readGrid_('Master_Brand'); $rows = array_slice($g, 1); $now = gmdate('c'); $hit = false;
    foreach ($rows as $i => $r) if (js_upper(text_($r[0] ?? null)) === js_upper($brand)) { $rows[$i] = [text_($r[0]), $aktif ? 'TRUE' : 'FALSE', $catatan, $nik, $now]; $hit = true; }
    if (!$hit) $rows[] = [$brand, $aktif ? 'TRUE' : 'FALSE', $catatan, $nik, $now];
    putTable_('Master_Brand', BRAND_HEADS, array_values($rows)); unset($GLOBALS['DSM_BRM']);
}
function apiBrandSave($token, $brand = null, $catatan = null, $aktif = null) {
    $u = brandGuard_($token);
    return with_lock_(function () use ($u, $brand, $catatan, $aktif) {
        $db = snapshot_(); $brand = preg_replace('/\s+/', ' ', js_trim(js_str(js_or($brand, ''))));
        if (mb_strlen($brand) < 2 || mb_strlen($brand) > 40) fail_('Nama brand 2–40 karakter.');
        if (!preg_match('/^[\p{L}\p{N} &\-\.\+\/]+$/u', $brand)) fail_('Nama brand hanya boleh huruf, angka, spasi, & - . + /');
        $catatan = mb_substr(js_trim(js_str(js_or($catatan, ''))), 0, 200);
        $exist = js_find(brandRows_($db['meta']), fn($r) => js_upper($r['brand']) === js_upper($brand));
        $name = $exist ? $exist['brand'] : $brand;
        brandWrite_($name, $aktif === false ? false : ($exist ? $exist['aktif'] : true), $catatan, $u['nik']);
        audit_($u['nik'], $exist ? 'BRAND_UBAH' : 'BRAND_TAMBAH', $name);
        return ['rows' => brandRows_($db['meta']), 'baru' => !$exist, 'tanpaProduk' => !$exist || !$exist['products']];
    });
}
function apiBrandSetActive($token, $brand = null, $aktif = null) {
    $u = brandGuard_($token);
    return with_lock_(function () use ($u, $brand, $aktif) {
        $db = snapshot_(); $brand = text_($brand);
        $exist = js_find(brandRows_($db['meta']), fn($r) => $r['brand'] === $brand); if (!$exist) fail_('Brand tidak ditemukan.');
        $on = $aktif === true || $aktif === 'true'; brandWrite_($brand, $on, $exist['catatan'], $u['nik']);
        audit_($u['nik'], $on ? 'BRAND_AKTIF' : 'BRAND_NONAKTIF', $brand);
        return ['rows' => brandRows_($db['meta'])];
    });
}

// ------------------------------------------------------------------
// DSM: Brand & Klasifikasi terpadu (UI satu panel, backend tetap Master_Brand + Product_Rules)
// ------------------------------------------------------------------
const BRAND_RULE_EXTRA_HEADS = ['BRAND','BRAND_UTAMA','MASUK_QTY','KATEGORI','AKTIF','CATATAN','UPDATED_BY','UPDATED_AT'];

function brandRuleCategoryMap_(): array {
    $cnt = [];
    foreach (optionalTable_('Master_Product') as $r) {
        $b = js_upper(text_($r['BRAND'] ?? null)); $c = text_($r['KATEGORI PRODUK'] ?? null);
        if ($b === '' || $c === '') continue;
        $cnt[$b][$c] = ($cnt[$b][$c] ?? 0) + 1;
    }
    $out = [];
    foreach ($cnt as $b => $m) { arsort($m); $out[$b] = (string) array_key_first($m); }
    return $out;
}
function brandRuleRows_(): array {
    $cats = brandRuleCategoryMap_(); $prod = [];
    foreach (optionalTable_('Master_Product') as $r) {
        $b = js_upper(text_($r['BRAND'] ?? null)); if ($b === '') continue;
        if (!isset($prod[$b])) $prod[$b] = 0; $prod[$b]++;
    }
    $rows = []; $seen = [];
    foreach (optionalTable_('Product_Rules') as $r) {
        $name = text_($r['BRAND'] ?? null); if ($name === '') continue; $k = js_upper($name); $seen[$k] = 1;
        $parent = js_or(text_($r['BRAND_UTAMA'] ?? null), $name);
        $qty = PortalCore::active($r['MASUK_QTY'] ?? null);
        $cat = js_or(text_($r['KATEGORI'] ?? null), $cats[$k] ?? ($qty ? 'HP' : 'ACC'));
        $rows[] = ['name'=>$name,'parent'=>$parent,'category'=>$cat,'qty'=>$qty,
            'active'=>js_upper(text_($r['AKTIF'] ?? 'TRUE')) !== 'FALSE','note'=>text_($r['CATATAN'] ?? null),
            'products'=>$prod[$k] ?? 0,'updatedBy'=>text_($r['UPDATED_BY'] ?? null),'updatedAt'=>text_($r['UPDATED_AT'] ?? null)];
    }
    // Brand induk yang belum punya rule (mis. TECNO) tetap ditampilkan dan bisa langsung dilengkapi.
    foreach (brandMaster_() as $m) {
        $k = js_upper($m['brand']); if (isset($seen[$k])) continue;
        $rows[] = ['name'=>$m['brand'],'parent'=>$m['brand'],'category'=>$cats[$k] ?? 'HP','qty'=>true,
            'active'=>$m['aktif'],'note'=>$m['catatan'],'products'=>$prod[$k] ?? 0,'updatedBy'=>$m['by'],'updatedAt'=>$m['at'],'missingRule'=>true];
    }
    return js_sort($rows, fn($a,$b)=>lcn_($a['name'],$b['name']));
}
function brandRuleParents_(): array {
    $x = [];
    foreach (brandMaster_() as $m) $x[$m['brand']] = 1;
    foreach (optionalTable_('Product_Rules') as $r) { $b = text_($r['BRAND_UTAMA'] ?? null); if ($b !== '') $x[$b] = 1; }
    return js_sort_default(array_keys($x));
}
function brandRuleCategories_(): array {
    $x = ['HP'=>1,'ACC'=>1,'LDU'=>1];
    foreach (optionalTable_('Master_Product') as $r) { $c = text_($r['KATEGORI PRODUK'] ?? null); if ($c !== '') $x[$c] = 1; }
    foreach (optionalTable_('Product_Rules') as $r) { $c = text_($r['KATEGORI'] ?? null); if ($c !== '') $x[$c] = 1; }
    return js_sort_default(array_keys($x));
}
function brandRuleWriteAll_(array $objects): void {
    $g = readGrid_('Product_Rules'); $heads = $g ? array_map('text_', $g[0]) : [];
    foreach (BRAND_RULE_EXTRA_HEADS as $h) if (!in_array($h,$heads,true)) $heads[] = $h;
    $rows = [];
    foreach ($objects as $r) $rows[] = array_map(fn($h)=>$r[$h] ?? '', $heads);
    putTable_('Product_Rules',$heads,$rows);
}
function apiBrandClassificationSave($token,$input=null,$reason=null) {
    $u = brandGuard_($token); if ($u['role'] !== 'SUPER ADMIN') fail_('Edit Brand & Klasifikasi khusus Super Admin.');
    $input = is_array($input) ? $input : []; $reason = text_($reason); if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');
    return with_lock_(function() use ($u,$input,$reason) {
        $db = snapshot_(); $name = preg_replace('/\s+/u',' ',js_trim(js_str($input['name'] ?? ''))); $orig = text_($input['original'] ?? null);
        $parent = preg_replace('/\s+/u',' ',js_trim(js_str($input['parent'] ?? ''))); $cat = preg_replace('/\s+/u',' ',js_trim(js_str($input['category'] ?? '')));
        $qty = ($input['qty'] ?? false) === true || js_upper(text_($input['qty'] ?? null)) === 'TRUE' || text_($input['qty'] ?? null) === '1';
        $active = !((($input['active'] ?? true) === false) || js_upper(text_($input['active'] ?? null)) === 'FALSE' || text_($input['active'] ?? null) === '0');
        $note = mb_substr(text_($input['note'] ?? null),0,200);
        foreach ([['Klasifikasi',$name],['Brand induk',$parent]] as [$lab,$v]) {
            if (mb_strlen($v)<2 || mb_strlen($v)>40 || !preg_match('/^[\p{L}\p{N} &\-\.\+\/]+$/u',$v)) fail_($lab.' tidak valid.');
        }
        if ($cat === '' || mb_strlen($cat)>40) fail_('Kategori wajib diisi.');
        $objects = optionalTable_('Product_Rules'); $found = -1; $existing = null;
        foreach ($objects as $i=>$r) if (js_upper(text_($r['BRAND'] ?? null)) === js_upper($orig !== '' ? $orig : $name)) { $found=$i; $existing=$r; break; }
        if ($existing && $orig !== '' && js_upper($name)!==js_upper($orig)) fail_('Nama klasifikasi existing dikunci. Buat klasifikasi baru bila nama harus berbeda.');
        foreach ($objects as $i=>$r) if ($i!==$found && js_upper(text_($r['BRAND'] ?? null))===js_upper($name)) fail_('Klasifikasi '.$name.' sudah ada.');
        $parents = brandRuleParents_(); $parentExists = js_some($parents,fn($x)=>js_upper($x)===js_upper($parent));
        if (!$parentExists && js_upper($parent)!==js_upper($name)) fail_('Brand induk '.$parent.' belum ada. Buat klasifikasi utama '.$parent.' terlebih dahulu.');
        $next = array_merge($existing ?? [], ['BRAND'=>$name,'BRAND_UTAMA'=>$parent,'MASUK_QTY'=>$qty?'true':'false','KATEGORI'=>$cat,'AKTIF'=>$active?'TRUE':'FALSE','CATATAN'=>$note,'UPDATED_BY'=>$u['nik'],'UPDATED_AT'=>gmdate('c')]);
        if ($found>=0) $objects[$found]=$next; else $objects[]=$next;
        $beforeRule = readGrid_('Product_Rules'); $beforeBrand = sheet_exists_('Master_Brand') ? readGrid_('Master_Brand') : [];
        $changes=[]; foreach (['BRAND_UTAMA','MASUK_QTY','KATEGORI','AKTIF','CATATAN'] as $h) { $a=text_($existing[$h]??''); $b=text_($next[$h]??''); if($a!==$b)$changes[]=$h.': '.$a.' → '.$b; }
        if (!$existing) $changes[]='KLASIFIKASI BARU';
        checkpoint_($u['nik'],'Brand & Klasifikasi '.$name.' · '.$reason);
        try {
            if (js_upper($parent)===js_upper($name)) brandWrite_($parent,$active,$note,$u['nik']);
            brandRuleWriteAll_($objects);
            $meta=meta_(); validateConfigChange_($db,$meta); publish_($meta,$db['raw'],$db['coverage'],$u['nik'],'BRAND_RULE '.$name.' '.$reason);
            audit_($u['nik'],'BRAND_KLASIFIKASI',$name.' ['.implode('; ',$changes).'] · '.$reason);
            unset($GLOBALS['DSM_BRM']);
            return ['rows'=>brandRows_($meta),'classifications'=>brandRuleRows_(),'parents'=>brandRuleParents_(),'categories'=>brandRuleCategories_()];
        } catch(Throwable $e) { restoreGrid_('Product_Rules',$beforeRule); if($beforeBrand) restoreGrid_('Master_Brand',$beforeBrand); throw $e; }
    });
}

