<?php
/**
 * KPI Khusus per Sales / ASM (v2, Paket 22).
 * - Konfigurasi per NIK per periode (tabel _kk_config). Satu kelompok "KPI KHUSUS" yang bisa MENGGANTIKAN
 *   satu kelompok KPI regional atau DITAMBAHKAN sebagai kelompok baru (KPI akhir = rata-rata semua kelompok).
 * - Indikator: ST (target reguler), SO (target SO manual/unggah), DA (aturan DA Biasa / DAQ / DA IoT, target reguler),
 *   DOS (poin langsung dari tabel hari → poin; bobot DOS = jatah porsi di total 100%).
 * - Tabel poin DOS: default global per periode (_kk_rule), boleh ditimpa per baris DOS.
 * - Target SO manual per sales per brand: _kk_target (menang atas unggah kolom "SO <BRAND>").
 * Skor kelompok = rata-rata berbobot (ST/SO/DA yang punya target) × porsi non-DOS + jumlah poin DOS. Minimal 0.
 * Spesifikasi lengkap: dsm-ux/SPEC-kpi-so-dos.md
 */

const KK_GROUP = 'KPI KHUSUS';
const KK_CAP = 120;
const KK_VER = 2;

function kkSchema_(): void {
    static $done = false; if ($done) return;
    db_()->exec("CREATE TABLE IF NOT EXISTS _kk_config (nik VARCHAR(100) NOT NULL, period CHAR(7) NOT NULL, data LONGTEXT NOT NULL, actor VARCHAR(100) NOT NULL, updated_at VARCHAR(40) NOT NULL, PRIMARY KEY(nik,period)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db_()->exec("CREATE TABLE IF NOT EXISTS _kk_rule (period CHAR(7) NOT NULL PRIMARY KEY, data LONGTEXT NOT NULL, actor VARCHAR(100) NOT NULL, updated_at VARCHAR(40) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db_()->exec("CREATE TABLE IF NOT EXISTS _kk_target (nik VARCHAR(100) NOT NULL, period CHAR(7) NOT NULL, brand VARCHAR(100) NOT NULL, value DOUBLE NOT NULL, actor VARCHAR(100) NOT NULL, updated_at VARCHAR(40) NOT NULL, PRIMARY KEY(nik,period,brand)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}
function kkReset_(): void { unset($GLOBALS['KK_CACHE'], $GLOBALS['KK_RULE'], $GLOBALS['KK_TGT']); }

/** Konfigurasi lama (paket 21) dinaikkan ke bentuk v2 saat dibaca. */
function kkNormalize_(array $c): array {
    if ((int) ($c['v'] ?? 1) >= 2) return $c;
    $cats = array_values((array) ($c['cats'] ?? [])); $items = [];
    foreach ((array) ($c['items'] ?? []) as $it) {
        $x = ['m' => $it['m'], 'b' => $it['b'], 'w' => $it['w']];
        if (in_array($it['m'], ['SO', 'DOS'], true)) { $x['basis'] = $it['basis'] ?? 'MTD'; $x['cats'] = $cats; }
        $items[] = $x;
    }
    $c['v'] = KK_VER; $c['replace'] = true; $c['items'] = $items; unset($c['cats']);
    return $c;
}
function kkRows_(string $period): array {
    if (!isset($GLOBALS['KK_CACHE'][$period])) {
        kkSchema_(); $out = [];
        $q = db_()->prepare('SELECT nik,data,actor,updated_at FROM _kk_config WHERE period=?'); $q->execute([$period]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $d = json_decode($r['data'], true);
            if (is_array($d)) { $d = kkNormalize_($d); $d['actor'] = $r['actor']; $d['updatedAt'] = $r['updated_at']; $out[(string) $r['nik']] = $d; }
        }
        $GLOBALS['KK_CACHE'][$period] = $out;
    }
    return $GLOBALS['KK_CACHE'][$period];
}

// ------------------------------------------------------------------
// Tabel poin DOS (default global per periode)
// ------------------------------------------------------------------
function kkDefaultRule_(): array { return ['tiers' => [[0, 10], [60, 0], [91, -5]], 'stok0' => 0, 'noSo' => -5]; }
function kkCleanRule_($in): array {
    $in = is_array($in) ? $in : []; $tiers = []; $raw = array_values(is_array($in['tiers'] ?? null) ? $in['tiers'] : []);
    if (!$raw) fail_('Tabel poin DOS minimal satu baris.');
    if (count($raw) > 20) fail_('Tabel poin DOS maksimal 20 baris.');
    foreach ($raw as $i => $t) {
        $f = is_array($t) && array_key_exists('from', $t) ? $t['from'] : (is_array($t) ? ($t[0] ?? null) : null);
        $p = is_array($t) && array_key_exists('pts', $t) ? $t['pts'] : (is_array($t) ? ($t[1] ?? null) : null);
        $f = kpiNum_($f); $p = kpiNum_($p);
        if (!is_numeric($f) || !is_finite((float) $f) || $f < 0 || $f > 9999 || floor($f) != $f) fail_('Baris ' . ($i + 1) . ': "Dari (hari)" harus bilangan bulat 0–9999.');
        if (!is_numeric($p) || !is_finite((float) $p) || abs($p) > 100) fail_('Baris ' . ($i + 1) . ': poin harus angka antara −100 dan 100.');
        $tiers[] = [(int) $f, (float) $p + 0];
    }
    usort($tiers, fn($a, $b) => $a[0] <=> $b[0]);
    if ($tiers[0][0] !== 0) fail_('Baris pertama harus mulai dari 0 hari.');
    for ($i = 1; $i < count($tiers); $i++) if ($tiers[$i][0] === $tiers[$i - 1][0]) fail_('Ada dua baris dengan "Dari" yang sama (' . $tiers[$i][0] . ' hari).');
    $o = ['tiers' => $tiers];
    foreach (['stok0' => 'Stok 0', 'noSo' => 'Ada stok tanpa SO'] as $k => $lbl) {
        $v = kpiNum_($in[$k] ?? null); if (!is_numeric($v) || !is_finite((float) $v) || abs($v) > 100) fail_($lbl . ': poin harus angka antara −100 dan 100.');
        $o[$k] = (float) $v + 0;
    }
    return $o;
}
function kkPeriodRule_(string $period): array {
    if (!isset($GLOBALS['KK_RULE'][$period])) {
        kkSchema_(); $q = db_()->prepare('SELECT data FROM _kk_rule WHERE period=?'); $q->execute([$period]); $d = $q->fetchColumn();
        $r = $d ? json_decode($d, true) : null;
        $GLOBALS['KK_RULE'][$period] = is_array($r) && isset($r['tiers']) ? $r : kkDefaultRule_();
    }
    return $GLOBALS['KK_RULE'][$period];
}
function kkRuleStored_(string $period): bool { kkSchema_(); $q = db_()->prepare('SELECT COUNT(*) FROM _kk_rule WHERE period=?'); $q->execute([$period]); return (int) $q->fetchColumn() > 0; }
function kkRuleFor_(string $period, array $it): array { return isset($it['rule']) && is_array($it['rule']) ? $it['rule'] : kkPeriodRule_($period); }
function kkRuleMax_(array $rule): float { $m = 0.0; foreach ($rule['tiers'] as $t) $m = max($m, (float) $t[1]); return max($m, (float) $rule['stok0'], (float) $rule['noSo']); }

function kkDosPoints_($stock, $so, int $days, array $rule): array {
    if ($stock <= 0) return ['dos' => 0.0, 'points' => (float) $rule['stok0'], 'cond' => 'HABIS'];
    if ($so <= 0) return ['dos' => null, 'points' => (float) $rule['noSo'], 'cond' => 'TANPA SO'];
    $d = $stock / ($so / $days); $pts = 0.0;
    foreach ($rule['tiers'] as $t) if ($d >= $t[0]) $pts = (float) $t[1];
    return ['dos' => $d, 'points' => $pts, 'cond' => $pts > 0 ? 'AMAN' : ($pts < 0 ? 'TINGGI' : 'WASPADA')];
}

// ------------------------------------------------------------------
// Master data
// ------------------------------------------------------------------
/** Kelompok KPI regional untuk seorang Sales / ASM. */
function kkRegionGroups_(array $db, string $period, array $p): array {
    $reg = $p['wilayah'] ?? null;
    $sub = js_upper(text_(js_or($p['portfolio'] ?? '', $p['region'] ?? '')));
    $policies = $db['meta']['policies'] ?? [];
    if ($sub !== '') { $own = js_filter($policies, fn($c) => $c['period'] === $period && js_upper(text_($c['region'])) === $sub); if ($own) $reg = $own[0]['region']; }
    $cfg = js_filter($policies, fn($c) => $c['period'] === $period && $c['region'] === $reg);
    $groups = [];
    foreach ($cfg as $c) { $g = js_upper(text_($c['group'])); if (!isset($groups[$g])) $groups[$g] = ['name' => $g, 'indicators' => 0, 'weight' => 0]; $groups[$g]['indicators']++; $groups[$g]['weight'] += (float) $c['weight']; }
    return array_values($groups);
}
function kkBrands_(array $db): array {
    $b = []; foreach ($db['meta']['products'] ?? [] as $p) { $x = js_upper(text_($p['brand'] ?? '')); if ($x !== '') $b[$x] = true; }
    $o = array_keys($b); sort($o); return $o;
}
function kkGrades_(): array {
    $g = []; foreach (attrMap_('GRADE') as $x) foreach ($x['brands'] as $bv) $g[(string) $bv[1]] = true;
    $o = array_keys($g); sort($o); return $o;
}
/** Grade per brand yang ada di Master Grade: [brand => [grade,...]]. */
function kkGradesByBrand_(): array {
    $m = []; foreach (attrMap_('GRADE') as $x) foreach ($x['brands'] as $bv) $m[js_upper((string) $bv[0])][(string) $bv[1]] = true;
    $o = []; foreach ($m as $b => $g) { $k = array_keys($g); sort($k); $o[$b] = $k; } return $o;
}
function kkTypes_(array $db): array { $t = kpiProducts_($db)['types']; $o = []; foreach ($t as $b => $l) $o[js_upper((string) $b)] = array_values($l); return $o; }

/** ID indikator target khusus untuk DA dengan aturan bukan default (DA Biasa, min unit 2). '' = pakai kolom DA reguler. */
function kkDaId_(array $it): string {
    $mode = $it['mode'] ?? 'biasa'; $min = (int) ($it['minDA'] ?? 2);
    if ($mode === 'biasa' && $min === 2) return '';
    $must = (array) ($it['must'] ?? []); sort($must);
    return 'KK_DA_' . preg_replace('/[^A-Z0-9]+/', '', $it['b']) . '_' . ($mode === 'iot' ? 'IOT' . rtrim(rtrim(number_format((float) ($it['perProm'] ?? 3), 2, '.', ''), '0'), '.') : ($mode === 'daq' ? 'DAQ' : 'MIN')) . '_M' . $min . ($must ? '_' . substr(md5(implode('|', $must)), 0, 6) : '');
}
function kkDaLabel_(array $it): string {
    $mode = $it['mode'] ?? 'biasa'; $min = (int) ($it['minDA'] ?? 2); $b = $it['b'] === 'ALL' ? '' : ' ' . $it['b'];
    if ($mode === 'iot') return 'DA' . $b . ' · IOT ' . rtrim(rtrim(number_format((float) ($it['perProm'] ?? 3), 2, '.', ''), '0'), '.') . '/PROMOTOR · MIN ' . $min;
    if ($mode === 'daq') return 'DA' . $b . ' · DAQ (' . implode(', ', (array) ($it['must'] ?? [])) . ') · MIN ' . $min;
    return 'DA' . $b . ($min === 2 ? '' : ' · MIN ' . $min);
}
function kkItemLabel_(array $it): string {
    $b = $it['b'] === 'ALL' ? 'Semua Brand' : $it['b'];
    switch ($it['m']) {
        case 'ST': return 'ST ' . $b;
        case 'SO': return 'Sell Out ' . $b;
        case 'DA': return kkDaLabel_($it) . ($it['b'] === 'ALL' ? ' · Semua Brand' : '');
        default: return 'DOS ' . $b;
    }
}

/** Validasi + normalisasi konfigurasi dari editor Admin. */
function kkClean_(array $db, string $period, array $p, $in): array {
    $in = is_array($in) ? $in : [];
    $regionGroups = array_column(kkRegionGroups_($db, $period, $p), 'name');
    $replace = !empty($in['replace']); $replaces = js_upper(text_($in['replaces'] ?? ''));
    if ($replace) {
        if (!$regionGroups) fail_('Kebijakan KPI region untuk ' . $p['nama'] . ' belum diisi pada periode ' . $period . '. Hilangkan centang "Gantikan kelompok" untuk menambahkan sebagai kelompok baru.');
        if (!in_array($replaces, $regionGroups, true)) fail_('Pilih kelompok KPI yang digantikan (' . implode(', ', $regionGroups) . ').');
    } else $replaces = '';
    $brands = kkBrands_($db); $grades = kkGrades_(); $typesAll = kkTypes_($db); $items = []; $seen = []; $w = 0; $hasBase = false;
    $raw = array_values(is_array($in['items'] ?? null) ? $in['items'] : []);
    if (!$raw) fail_('Tambahkan minimal satu indikator.');
    if (count($raw) > 30) fail_('Maksimal 30 indikator.');
    foreach ($raw as $i => $x) {
        $x = is_array($x) ? $x : []; $line = 'Indikator ' . ($i + 1) . ': ';
        $m = js_upper(text_($x['m'] ?? '')); $b = js_upper(text_($x['b'] ?? 'ALL')); if ($b === '') $b = 'ALL';
        $wt = kpiNum_($x['w'] ?? null);
        if (!in_array($m, ['ST', 'SO', 'DA', 'DOS'], true)) fail_($line . 'pilih ST, SO, DA, atau DOS.');
        if ($b !== 'ALL' && !in_array($b, $brands, true)) fail_($line . 'brand tidak dikenal.');
        if (!is_numeric($wt) || !is_finite((float) $wt) || !($wt > 0)) fail_($line . 'bobot harus > 0.');
        $it = ['m' => $m, 'b' => $b, 'w' => (float) $wt];
        if ($m === 'SO' || $m === 'DOS') {
            $basis = js_upper(text_($x['basis'] ?? 'MTD')); $it['basis'] = in_array($basis, ['MTD', 'R30'], true) ? $basis : 'MTD';
            $cats = [];
            foreach ((array) ($x['cats'] ?? []) as $c) { $c = text_($c); if ($c !== '') { if (!in_array($c, $grades, true)) fail_($line . 'kategori dealer "' . $c . '" tidak ada di Master Grade.'); $cats[$c] = $c; } }
            $it['cats'] = array_values($cats);
        }
        if ($m === 'DA') {
            $mode = strtolower(text_($x['mode'] ?? 'biasa')); if (!in_array($mode, ['biasa', 'daq', 'iot'], true)) $mode = 'biasa';
            $min = kpiNum_($x['minDA'] ?? 2); if (!is_numeric($min) || !is_finite((float) $min) || floor($min) != $min || $min < 1) fail_($line . 'Min unit harus bilangan bulat minimal 1.');
            $it['mode'] = $mode; $it['minDA'] = (int) $min; $it['must'] = []; $it['perProm'] = 0;
            if ($mode === 'daq') {
                $known = $b === 'ALL' ? array_unique(array_merge(...array_values($typesAll ?: [[]]))) : ($typesAll[$b] ?? []);
                $must = []; foreach ((array) ($x['must'] ?? []) as $t) { $t = text_($t); if ($t !== '') { if ($known && !in_array($t, $known, true)) fail_($line . 'type wajib "' . $t . '" tidak ada pada brand ini.'); $must[$t] = $t; } }
                if (!$must) fail_($line . 'DAQ butuh minimal satu type wajib.');
                $it['must'] = array_values($must);
            }
            if ($mode === 'iot') {
                if ($b === 'ALL') fail_($line . 'DA IoT wajib memilih satu brand tertentu.');
                $pp = kpiNum_($x['perProm'] ?? 3); if (!is_numeric($pp) || !is_finite((float) $pp) || $pp < 1) fail_($line . 'Min unit ACC per promotor harus angka minimal 1.');
                $it['perProm'] = (float) $pp + 0;
            }
            $it['id'] = kkDaId_($it);
        }
        if ($m === 'DOS' && isset($x['rule']) && is_array($x['rule'])) $it['rule'] = kkCleanRule_($x['rule']);
        $sig = $m . '|' . $b . '|' . ($m === 'DA' ? ($it['mode'] . '|' . $it['minDA'] . '|' . implode(',', $it['must']) . '|' . $it['perProm']) : (($it['basis'] ?? '') . '|' . implode(',', $it['cats'] ?? [])));
        if (isset($seen[$sig])) fail_($line . kkItemLabel_($it) . ' sudah ada dengan aturan yang sama.'); $seen[$sig] = true;
        if ($m !== 'DOS') $hasBase = true;
        $w += (float) $wt; $items[] = $it;
    }
    if (!$hasBase) fail_('Kelompok wajib punya minimal satu indikator ST, SO, atau DA. DOS tidak bisa sendirian.');
    if (abs($w - 100) > 0.0001) fail_('Total bobot kelompok harus 100% (sekarang ' . rtrim(rtrim(number_format($w, 2, '.', ''), '0'), '.') . '%).');
    return ['v' => KK_VER, 'replace' => $replace, 'replaces' => $replaces, 'items' => $items];
}

// ------------------------------------------------------------------
// Saldo stok & SO per dealer×brand
// ------------------------------------------------------------------
/** Seluruh dealer×brand untuk rentang tertentu (di-cache per request): pemilik, grade, stok akhir, SO. */
function kkPairsAll_(array $db, string $asof, string $from): array {
    $key = $asof . '|' . $from;
    if (isset($GLOBALS['KK_PA'][$key])) return $GLOBALS['KK_PA'][$key];
    $bal = stockBalance_($db, $asof, $from); $idx = $GLOBALS['KK_IDX'] ?? ($GLOBALS['KK_IDX'] = dbaIndex_());
    $grade = attrMap_('GRADE'); $sb = [];
    foreach ($db['meta']['products'] ?? [] as $sku => $p) $sb[(string) $sku] = js_upper(text_($p['brand'] ?? ''));
    $pairs = [];
    foreach ($bal as $t) {
        $brand = $sb[(string) $t['sku']] ?? ''; if ($brand === '') continue;
        $k = $t['customer'] . "\x1f" . $brand;
        if (!isset($pairs[$k])) {
            $gv = null; foreach (($grade[(string) $t['customer']]['brands'] ?? []) as $bv) if (js_upper((string) $bv[0]) === $brand) { $gv = (string) $bv[1]; break; }
            $pairs[$k] = ['customer' => (string) $t['customer'], 'brand' => $brand, 'owner' => (string) dealerBrandOwner_($db, (string) $t['customer'], $brand, $asof, $idx)['nik'], 'grade' => $gv, 'stock' => 0, 'so' => 0, 'bad' => false];
        }
        $pairs[$k]['stock'] += $t['stock']; $pairs[$k]['so'] += $t['sellout'];
        if ($t['early'] || $t['stock'] < 0) $pairs[$k]['bad'] = true;
    }
    return $GLOBALS['KK_PA'][$key] = $pairs;
}
/** Dealer×brand milik subjek ($idSet) untuk satu brand ('ALL' = semua) dan kategori grade ($cats kosong = semua). */
function kkRowPairs_(array $pairs, array $idSet, string $brand, array $cats): array {
    $out = []; $cs = $cats ? array_flip($cats) : null;
    foreach ($pairs as $k => $pr) {
        if (!isset($idSet[$pr['owner']])) continue;
        if ($brand !== 'ALL' && $pr['brand'] !== $brand) continue;
        if ($cs !== null && !($pr['grade'] !== null && isset($cs[$pr['grade']]))) continue;
        $out[$k] = $pr;
    }
    return $out;
}

/** Konteks tanggal data untuk periode. */
function kkAsof_(array $db, string $period): ?array {
    $st = $db['stock'] ?? null; $soCut = $st['coverage'][$period]['cutoff'] ?? null; $stCut = $db['coverage'][$period]['cutoff'] ?? null;
    if (!$soCut || !$stCut) return null;
    $asof = strcmp($soCut, $stCut) < 0 ? $soCut : $stCut; $end = PortalCore::end($period); if (strcmp($asof, $end) > 0) $asof = $end;
    $start = $period . '-01'; if (strcmp($asof, $start) < 0) return null;
    return ['asof' => $asof, 'start' => $start, 'days' => (int) substr($asof, 8, 2), 'end' => $end, 'partial' => strcmp($asof, $end) < 0];
}

// ------------------------------------------------------------------
// Target SO (manual menang atas unggah)
// ------------------------------------------------------------------
function kkManualTargets_(string $period): array {
    if (!isset($GLOBALS['KK_TGT'][$period])) {
        kkSchema_(); $m = []; $q = db_()->prepare('SELECT nik,brand,value FROM _kk_target WHERE period=?'); $q->execute([$period]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $m[(string) $r['nik']][js_upper($r['brand'])] = (float) $r['value'];
        $GLOBALS['KK_TGT'][$period] = $m;
    }
    return $GLOBALS['KK_TGT'][$period];
}
/** Target SO untuk sekumpulan NIK (Sales = dirinya, ASM = jumlah sales di timnya). */
function kkSoTarget_(array $db, string $period, array $ids, string $brand): array {
    $man = kkManualTargets_($period); $tv = 0.0; $exists = false; $nManual = 0; $nUpload = 0;
    foreach ($ids as $id) {
        $p = PortalCore::person($db['meta']['people'], $id); if (!$p || !truthy($p['sales'] ?? false)) continue;
        if (isset($man[(string) $id][$brand])) { $tv += $man[(string) $id][$brand]; $exists = true; $nManual++; continue; }
        $t = PortalCore::target($db['meta'], $period, [$id], $brand, 'SO', ''); if ($t['exists']) { $tv += $t['value']; $exists = true; $nUpload++; }
    }
    return ['value' => $tv, 'exists' => $exists, 'manual' => $nManual, 'upload' => $nUpload];
}

// ------------------------------------------------------------------
// Perhitungan
// ------------------------------------------------------------------
/** Hitung seluruh indikator konfigurasi untuk subjek dengan anggota $ids. */
function kkCompute_(array $db, string $period, array $ids, array $cfg): array {
    $ctx = kkAsof_($db, $period); $idSet = array_flip(array_map('strval', $ids)); $rows = null;
    $pMtd = $pR30 = null;
    $indicators = [];
    foreach ($cfg['items'] as $it) {
        $b = $it['b']; $m = $it['m']; $ind = kkItemLabel_($it);
        $base = ['indicator' => $m . '_' . $b . ($m === 'DA' ? '_' . ($it['id'] ?: 'REG') : ''), 'label' => $ind, 'types' => [], 'minDA' => $m === 'DA' ? $it['minDA'] : null, 'metric' => $m, 'brand' => $b, 'weight' => $it['w'], 'cap' => $m === 'DOS' ? null : KK_CAP, 'iddlePairs' => null, 'target' => null, 'actual' => null, 'ach' => null, 'score' => null, 'state' => 'BERLAKU'];
        if ($m === 'ST' || $m === 'DA') {
            $rows = $rows ?? PortalCore::slice($db, $period, $ids);
            $t = PortalCore::target($db['meta'], $period, $ids, $b, $m === 'ST' ? 'QTY' : 'DA', $m === 'DA' ? $it['id'] : '');
            $a = PortalCore::metrics($rows, $db, $period, $b, $m === 'DA' ? $it['minDA'] : 2, 1, $m === 'DA' ? $it['must'] : [], $m === 'DA' ? $it['perProm'] : 0);
            $actual = $m === 'ST' ? $a['qty'] : $a['da'];
            $base['actual'] = $actual; $base['target'] = $t['value']; $base['daId'] = $m === 'DA' ? $it['id'] : null;
            if (!($t['value'] > 0)) $base['state'] = 'TARGET BELUM DIISI';
            else { $base['ach'] = $actual / $t['value'] * 100; $base['score'] = min(KK_CAP, max(0, $base['ach'])); }
            $indicators[] = $base; continue;
        }
        if (!$ctx) { $indicators[] = ['state' => 'SELL OUT BELUM DIPUBLIKASIKAN'] + $base; continue; }
        $basis = $it['basis'] ?? 'MTD';
        if ($basis === 'R30') $pR30 = $pR30 ?? kkPairsAll_($db, $ctx['asof'], StockCore::shift($ctx['asof'], -29));
        else $pMtd = $pMtd ?? kkPairsAll_($db, $ctx['asof'], $ctx['start']);
        $src = kkRowPairs_($basis === 'R30' ? $pR30 : $pMtd, $idSet, $b, $it['cats'] ?? []);
        $days = $basis === 'R30' ? 30 : $ctx['days']; $stock = 0; $so = 0; $cust = []; $excluded = 0;
        foreach ($src as $pr) { if ($pr['bad']) { $excluded++; continue; } $stock += $pr['stock']; $so += $pr['so']; $cust[$pr['customer']] = true; }
        $base['dealers'] = count($cust);
        if ($m === 'SO') {
            $t = kkSoTarget_($db, $period, $ids, $b);
            $base['actual'] = $so; $base['target'] = $t['value']; $base['targetSource'] = $t['manual'] ? ($t['upload'] ? 'CAMPUR' : 'MANUAL') : ($t['upload'] ? 'UPLOAD' : null);
            if (!($t['value'] > 0)) $base['state'] = 'TARGET SO BELUM DIISI';
            else { $base['ach'] = $so / $t['value'] * 100; $base['score'] = min(KK_CAP, max(0, $base['ach'])); }
        } else {
            $rule = kkRuleFor_($period, $it); $r = kkDosPoints_($stock, $so, $days, $rule);
            $base['actual'] = $r['dos']; $base['score'] = $r['points'];
            $base['dos'] = ['days' => $r['dos'], 'points' => $r['points'], 'cond' => $r['cond'], 'stock' => $stock, 'so' => $so, 'n' => $days, 'basis' => $basis, 'excluded' => $excluded, 'max' => kkRuleMax_($rule), 'custom' => isset($it['rule'])];
            if (!count($cust)) { $base['state'] = $excluded ? 'DATA PERLU DICEK' : 'TIDAK ADA DEALER'; $base['score'] = null; }
            elseif ($ctx['partial'] && $basis === 'MTD') $base['state'] = 'SEMENTARA';
        }
        $indicators[] = $base;
    }
    return ['ctx' => $ctx, 'indicators' => $indicators];
}

/** Skor kelompok: Σ(skor × bobot) / Σ(bobot terhitung) × porsi non-DOS + Σ poin DOS, minimal 0. */
function kkGroupScore_(array $ind): array {
    $baseW = 0; $counted = 0; $sum = 0; $pts = 0; $partial = false;
    foreach ($ind as $i) {
        if ($i['metric'] === 'DOS') { if ($i['score'] !== null) $pts += $i['score']; }
        else { $baseW += $i['weight']; if ($i['score'] !== null) { $counted += $i['weight']; $sum += $i['score'] * $i['weight']; } }
        if ($i['state'] === 'SEMENTARA') $partial = true;
    }
    $score = $counted > 0 ? max(0, ($sum / $counted) * ($baseW / 100) + $pts) : null;
    return ['score' => $score, 'partial' => $partial, 'dosPoints' => $pts];
}

/** Bentuk kelompok KPI khusus (sama bentuknya dengan kelompok lain di PortalCore::kpi). */
function kkBuildGroup_(array $db, string $period, array $ids, array $cfg, bool &$tentative): array {
    $r = kkCompute_($db, $period, $ids, $cfg); $ind = $r['indicators']; $s = kkGroupScore_($ind);
    if ($s['partial']) $tentative = true;
    return ['name' => KK_GROUP, 'score' => $s['score'], 'pending' => false, 'partial' => false, 'na' => $s['score'] === null, 'special' => true, 'replaces' => $cfg['replaces'] ?? '', 'dosPoints' => $s['dosPoints'], 'indicators' => $ind];
}

/** Dipanggil PortalCore::kpi: ganti / tambahkan kelompok khusus bila subjek punya konfigurasi. */
function kkApply_(array $db, string $period, array $ids, ?array $owner, array $groups, bool &$tentative): array {
    $nik = $owner['nik'] ?? (count($ids) === 1 ? (string) array_values($ids)[0] : null);
    if (!$nik) return $groups;
    $cfg = kkRows_($period)[$nik] ?? null; if (!$cfg) return $groups;
    try { $g = kkBuildGroup_($db, $period, $ids, $cfg, $tentative); } catch (Throwable $e) { return $groups; }
    $out = []; $placed = false; $rep = !empty($cfg['replace']) ? ($cfg['replaces'] ?? '') : '';
    foreach ($groups as $x) {
        if (js_upper(text_($x['name'])) === js_upper(KK_GROUP)) continue;
        if (!$placed && $rep !== '' && js_upper(text_($x['name'])) === $rep) { $out[] = $g; $placed = true; continue; }
        $out[] = $x;
    }
    if (!$placed) $out[] = $g;
    return $out;
}

/** Kunci kolom Edit Target yang dibutuhkan konfigurasi (ST dan DA, target reguler). */
function kkTargetNeeds_(string $period, string $nik): array {
    $cfg = kkRows_($period)[$nik] ?? null; $o = [];
    if (!$cfg) return $o;
    foreach ($cfg['items'] as $it) {
        if ($it['m'] === 'ST') $o[] = ['metric' => 'QTY', 'brand' => $it['b'], 'indicator' => '', 'label' => 'ST ' . $it['b']];
        elseif ($it['m'] === 'DA') { $id = $it['id'] ?? kkDaId_($it); $o[] = ['metric' => 'DA', 'brand' => $it['b'], 'indicator' => $id, 'label' => $id === '' ? 'DA ' . $it['b'] : kkDaLabel_($it)]; }
    }
    return $o;
}

// ------------------------------------------------------------------
// API Admin
// ------------------------------------------------------------------
function kkManageable_(array $u, array $db): array {
    $out = [];
    foreach ($db['meta']['people'] as $p) {
        if (!in_array($p['role'], ['SALES', 'ASM'], true)) continue;
        if (!canManageUser_($u, $p)) continue;
        $out[] = $p;
    }
    return $out;
}
function kkIds_(array $db, array $p): array { return $p['role'] === 'ASM' ? array_map('strval', PortalCore::scope($db['meta']['people'], $p['nik'])) : [(string) $p['nik']]; }
function kkTarget_(string $token, string $nik): array {
    $db = snapshot_(); $u = adminGuard_($token, $db); $p = PortalCore::person($db['meta']['people'], $nik);
    if (!$p || !in_array($p['role'], ['SALES', 'ASM'], true) || !canManageUser_($u, $p)) fail_('Akun di luar kewenangan atau bukan Sales/ASM.');
    return [$db, $u, $p];
}
function kkReasonOk_($reason): string { $r = text_($reason); if (mb_strlen($r) < 5) fail_('Isi alasan minimal 5 karakter.'); return $r; }

/** Jumlah dealer yang dihitung (SO ∪ DOS) untuk satu konfigurasi. */
function kkDealerCount_(array $db, array $p, array $cfg, ?array $ctx): ?int {
    if (!$ctx) return null; $idSet = array_flip(kkIds_($db, $p)); $set = []; $has = false;
    foreach ($cfg['items'] as $it) {
        if (!in_array($it['m'], ['SO', 'DOS'], true)) continue; $has = true;
        $pairs = kkPairsAll_($db, $ctx['asof'], ($it['basis'] ?? 'MTD') === 'R30' ? StockCore::shift($ctx['asof'], -29) : $ctx['start']);
        foreach (kkRowPairs_($pairs, $idSet, $it['b'], $it['cats'] ?? []) as $pr) $set[$pr['customer']] = true;
    }
    return $has ? count($set) : null;
}

function apiKpiKhususGet($token, $period = null) {
    $db = snapshot_(); $u = adminGuard_($token, $db);
    $period = $period ?: ($db['meta']['periods'][0] ?? null);
    $period = PortalCore::month($period); $rows = kkRows_($period); $people = []; $ctx = kkAsof_($db, $period);
    foreach (kkManageable_($u, $db) as $p) {
        $c = $rows[$p['nik']] ?? null;
        $people[] = ['nik' => $p['nik'], 'nama' => $p['nama'] ?? '', 'role' => $p['role'], 'region' => js_or($p['wilayah'] ?? null, ''), 'area' => js_or($p['portfolio'] ?? null, $p['region'] ?? ''), 'groups' => kkRegionGroups_($db, $period, $p), 'config' => $c, 'dealers' => $c ? kkDealerCount_($db, $p, $c, $ctx) : null];
    }
    usort($people, fn($a, $b) => strcmp($a['nama'], $b['nama']));
    $pr = kpiProducts_($db);
    return ['period' => $period, 'people' => $people, 'brands' => kkBrands_($db), 'grades' => kkGrades_(), 'gradesByBrand' => (object) kkGradesByBrand_(), 'types' => (object) kkTypes_($db), 'superuser' => $u['role'] === 'SUPER ADMIN', 'rule' => kkPeriodRule_($period), 'ruleStored' => kkRuleStored_($period), 'cap' => KK_CAP, 'asof' => $ctx['asof'] ?? null];
}
function apiKpiKhususSave($token, $period, $nik, $cfg, $reason = null) {
    demoAssertWritable_($token); [$db, $u, $p] = kkTarget_($token, text_($nik)); $period = PortalCore::month($period);
    $reason = kkReasonOk_($reason); assertPeriodOpen_($period); $clean = kkClean_($db, $period, $p, $cfg);
    return with_lock_(function () use ($u, $p, $period, $clean, $reason) {
        kkSchema_(); $clean['reason'] = $reason;
        db_()->prepare('INSERT INTO _kk_config (nik,period,data,actor,updated_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE data=VALUES(data),actor=VALUES(actor),updated_at=VALUES(updated_at)')->execute([$p['nik'], $period, json_encode($clean, JSON_UNESCAPED_UNICODE), $u['nik'], iso_now()]);
        kkReset_(); data_rev_bump_('KPI Khusus'); audit_($u['nik'], 'KPI_KHUSUS_SAVE', $p['nik'] . ' ' . $period . ' · ' . $reason);
        return ['ok' => true, 'config' => $clean];
    });
}
function apiKpiKhususDelete($token, $period, $nik, $reason = null) {
    demoAssertWritable_($token); [$db, $u, $p] = kkTarget_($token, text_($nik)); $period = PortalCore::month($period);
    $reason = kkReasonOk_($reason); assertPeriodOpen_($period);
    return with_lock_(function () use ($u, $p, $period, $reason) {
        kkSchema_(); db_()->prepare('DELETE FROM _kk_config WHERE nik=? AND period=?')->execute([$p['nik'], $period]);
        kkReset_(); data_rev_bump_('KPI Khusus'); audit_($u['nik'], 'KPI_KHUSUS_DELETE', $p['nik'] . ' ' . $period . ' · ' . $reason);
        return ['ok' => true];
    });
}
/** Salin seluruh konfigurasi (yang boleh dikelola Admin ini) dari periode lain; yang sudah ada di periode tujuan tidak ditimpa. */
function apiKpiKhususCopy($token, $from, $to, $reason = null) {
    demoAssertWritable_($token); $db = snapshot_(); $u = adminGuard_($token, $db);
    $from = PortalCore::month($from); $to = PortalCore::month($to); if ($from === $to) fail_('Periode asal dan tujuan sama.');
    $reason = kkReasonOk_($reason); assertPeriodOpen_($to);
    return with_lock_(function () use ($db, $u, $from, $to, $reason) {
        kkSchema_(); $src = kkRows_($from); $dst = kkRows_($to); $n = 0; $skip = [];
        foreach (kkManageable_($u, $db) as $p) {
            $c = $src[$p['nik']] ?? null; if (!$c || isset($dst[$p['nik']])) continue;
            try { $clean = kkClean_($db, $to, $p, $c); } catch (Throwable $e) { $skip[] = $p['nama'] . ': ' . $e->getMessage(); continue; }
            $clean['reason'] = 'Salin dari ' . $from . ' · ' . $reason;
            db_()->prepare('INSERT INTO _kk_config (nik,period,data,actor,updated_at) VALUES (?,?,?,?,?)')->execute([$p['nik'], $to, json_encode($clean, JSON_UNESCAPED_UNICODE), $u['nik'], iso_now()]); $n++;
        }
        kkReset_(); data_rev_bump_('KPI Khusus'); audit_($u['nik'], 'KPI_KHUSUS_COPY', $from . ' → ' . $to . ' (' . $n . ') · ' . $reason);
        return ['copied' => $n, 'skipped' => $skip];
    });
}
/** Salin konfigurasi satu orang ke sales / ASM lain (peran yang sama). $opts: rules (salin tabel poin DOS sendiri), overwrite. */
function apiKpiKhususCopyTo($token, $period, $fromNik, $toNiks, $opts = null, $reason = null) {
    demoAssertWritable_($token); [$db, $u, $src] = kkTarget_($token, text_($fromNik)); $period = PortalCore::month($period);
    $reason = kkReasonOk_($reason); assertPeriodOpen_($period); $opts = is_array($opts) ? $opts : [];
    $toNiks = array_values(array_unique(array_map('strval', (array) $toNiks))); if (!$toNiks) fail_('Pilih minimal satu tujuan.'); if (count($toNiks) > 300) fail_('Maksimal 300 tujuan sekali salin.');
    $cfg = kkRows_($period)[$src['nik']] ?? null; if (!$cfg) fail_('Simpan dulu konfigurasi ' . $src['nama'] . ' sebelum disalin.');
    return with_lock_(function () use ($db, $u, $src, $period, $cfg, $toNiks, $opts, $reason) {
        kkSchema_(); $dst = kkRows_($period); $n = 0; $skip = []; $over = !empty($opts['overwrite']); $rules = !empty($opts['rules']);
        foreach ($toNiks as $nik) {
            $p = PortalCore::person($db['meta']['people'], $nik);
            if (!$p || $nik === (string) $src['nik']) continue;
            if (!in_array($p['role'], ['SALES', 'ASM'], true) || !canManageUser_($u, $p)) { $skip[] = ($p['nama'] ?? $nik) . ': di luar kewenangan'; continue; }
            if ($p['role'] !== $src['role']) { $skip[] = $p['nama'] . ': peran berbeda (' . $p['role'] . ')'; continue; }
            if (isset($dst[$nik]) && !$over) { $skip[] = $p['nama'] . ': sudah punya KPI Khusus (dilewati)'; continue; }
            $in = $cfg; if (!$rules) foreach ($in['items'] as $k => $it) unset($in['items'][$k]['rule']);
            try { $clean = kkClean_($db, $period, $p, $in); } catch (Throwable $e) { $skip[] = $p['nama'] . ': ' . $e->getMessage(); continue; }
            $clean['reason'] = 'Salin dari ' . $src['nama'] . ' · ' . $reason;
            db_()->prepare('INSERT INTO _kk_config (nik,period,data,actor,updated_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE data=VALUES(data),actor=VALUES(actor),updated_at=VALUES(updated_at)')->execute([$p['nik'], $period, json_encode($clean, JSON_UNESCAPED_UNICODE), $u['nik'], iso_now()]); $n++;
        }
        kkReset_(); data_rev_bump_('KPI Khusus'); audit_($u['nik'], 'KPI_KHUSUS_COPYTO', $src['nik'] . ' → ' . $n . ' orang · ' . $period . ' · ' . $reason);
        return ['copied' => $n, 'skipped' => $skip];
    });
}
/** Pratinjau untuk editor: nilai indikator + skor kelompok + jumlah dealer (tidak menyimpan apa pun). */
function apiKpiKhususPreview($token, $period, $nik, $cfg) {
    [$db, $u, $p] = kkTarget_($token, text_($nik)); $period = PortalCore::month($period);
    $clean = kkClean_($db, $period, $p, $cfg); $ids = kkIds_($db, $p);
    $tmp = false; $g = kkBuildGroup_($db, $period, $ids, $clean, $tmp); $ctx = kkAsof_($db, $period);
    return ['asof' => $ctx['asof'] ?? null, 'days' => $ctx['days'] ?? null, 'indicators' => $g['indicators'], 'groupScore' => $g['score'], 'dosPoints' => $g['dosPoints'], 'dealerCount' => kkDealerCount_($db, $p, $clean, $ctx)];
}
/** Daftar dealer yang dihitung untuk konfigurasi tersimpan seseorang (Admin / Super Admin). */
function apiKpiKhususDealerList($token, $period, $nik) {
    [$db, $u, $p] = kkTarget_($token, text_($nik)); $period = PortalCore::month($period);
    $cfg = kkRows_($period)[$p['nik']] ?? null; $ctx = kkAsof_($db, $period);
    if (!$cfg || !$ctx) return ['asof' => $ctx['asof'] ?? null, 'dealers' => [], 'count' => 0];
    $rows = kkDealerRows_($db, $period, $p, $cfg, $ctx, false);
    return ['asof' => $ctx['asof'], 'days' => $ctx['days'], 'count' => count($rows), 'dealers' => array_slice($rows, 0, 800)];
}

// ----- Tabel poin DOS default -----
function apiKpiKhususRuleSave($token, $period, $rule, $reason = null) {
    demoAssertWritable_($token); $db = snapshot_(); $u = adminGuard_($token, $db); $period = PortalCore::month($period);
    $reason = kkReasonOk_($reason); assertPeriodOpen_($period); $clean = kkCleanRule_($rule);
    return with_lock_(function () use ($u, $period, $clean, $reason) {
        kkSchema_();
        db_()->prepare('INSERT INTO _kk_rule (period,data,actor,updated_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE data=VALUES(data),actor=VALUES(actor),updated_at=VALUES(updated_at)')->execute([$period, json_encode($clean), $u['nik'], iso_now()]);
        kkReset_(); data_rev_bump_('KPI Khusus'); audit_($u['nik'], 'KPI_KHUSUS_RULE', $period . ' · ' . $reason);
        return ['ok' => true, 'rule' => $clean];
    });
}
function apiKpiKhususRuleCopy($token, $from, $to, $reason = null) {
    demoAssertWritable_($token); $db = snapshot_(); $u = adminGuard_($token, $db);
    $from = PortalCore::month($from); $to = PortalCore::month($to); if ($from === $to) fail_('Periode asal dan tujuan sama.');
    $reason = kkReasonOk_($reason); assertPeriodOpen_($to);
    return with_lock_(function () use ($u, $from, $to, $reason) {
        kkSchema_(); if (!kkRuleStored_($from)) fail_('Periode ' . $from . ' belum punya tabel poin tersimpan (masih memakai bawaan).');
        $rule = kkPeriodRule_($from);
        db_()->prepare('INSERT INTO _kk_rule (period,data,actor,updated_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE data=VALUES(data),actor=VALUES(actor),updated_at=VALUES(updated_at)')->execute([$to, json_encode($rule), $u['nik'], iso_now()]);
        kkReset_(); data_rev_bump_('KPI Khusus'); audit_($u['nik'], 'KPI_KHUSUS_RULECOPY', $from . ' → ' . $to . ' · ' . $reason);
        return ['ok' => true, 'rule' => $rule];
    });
}

// ----- Target SO -----
/** Baris target SO: satu baris = satu orang × satu brand pada indikator SO di KPI Khususnya. */
function apiKpiKhususTargets($token, $period = null) {
    $db = snapshot_(); $u = adminGuard_($token, $db); $period = PortalCore::month($period ?: ($db['meta']['periods'][0] ?? null));
    $rows = kkRows_($period); $man = kkManualTargets_($period); $out = [];
    foreach (kkManageable_($u, $db) as $p) {
        $c = $rows[$p['nik']] ?? null; if (!$c) continue;
        $asm = $p['role'] === 'SALES' ? targetAsm_($db['meta']['people'], $p) : $p;
        foreach ($c['items'] as $it) {
            if ($it['m'] !== 'SO') continue;
            $b = $it['b']; $ids = kkIds_($db, $p);
            if ($p['role'] === 'ASM') { $t = kkSoTarget_($db, $period, $ids, $b); $val = $t['exists'] ? $t['value'] : null; $src = 'OTOMATIS'; $n = 0; foreach ($ids as $i) { $q = PortalCore::person($db['meta']['people'], $i); if ($q && truthy($q['sales'] ?? false)) $n++; } }
            else {
                if (isset($man[(string) $p['nik']][$b])) { $val = $man[(string) $p['nik']][$b]; $src = 'MANUAL'; }
                else { $t = PortalCore::target($db['meta'], $period, [$p['nik']], $b, 'SO', ''); $val = $t['exists'] ? $t['value'] : null; $src = $t['exists'] ? 'UPLOAD' : 'KOSONG'; }
                $n = 1;
            }
            $out[] = ['nik' => $p['nik'], 'nama' => $p['nama'] ?? '', 'role' => $p['role'], 'region' => js_or($p['wilayah'] ?? null, ''), 'area' => js_or($p['portfolio'] ?? null, $p['region'] ?? ''), 'asm' => $asm['nama'] ?? '', 'brand' => $b, 'value' => $val, 'source' => $src, 'members' => $n];
        }
    }
    usort($out, fn($a, $b) => strcmp($a['area'] . $a['asm'] . ($a['role'] === 'ASM' ? '0' : '1') . $a['nama'], $b['area'] . $b['asm'] . ($b['role'] === 'ASM' ? '0' : '1') . $b['nama']) ?: strcmp($a['brand'], $b['brand']));
    return ['period' => $period, 'rows' => $out];
}
function apiKpiKhususTargetSave($token, $period, $changes, $reason = null) {
    demoAssertWritable_($token); $db = snapshot_(); $u = adminGuard_($token, $db); $period = PortalCore::month($period);
    $reason = kkReasonOk_($reason); assertPeriodOpen_($period);
    if (!is_array($changes) || !$changes) fail_('Tidak ada perubahan.'); $changes = array_values($changes); if (count($changes) > 5000) fail_('Maksimal 5.000 baris sekali simpan.');
    $brands = kkBrands_($db); $list = [];
    foreach ($changes as $x) {
        $x = is_array($x) ? $x : []; $nik = text_($x['nik'] ?? ''); $b = js_upper(text_($x['brand'] ?? ''));
        $p = PortalCore::person($db['meta']['people'], $nik);
        if (!$p || $p['role'] !== 'SALES' || !truthy($p['sales'] ?? false)) fail_('NIK ' . $nik . ' bukan Sales (target ASM otomatis dari tim).');
        if (!canManageUser_($u, $p)) fail_($p['nama'] . ' di luar wilayah admin Anda.');
        if ($b !== 'ALL' && !in_array($b, $brands, true)) fail_('Brand ' . $b . ' tidak dikenal.');
        $v = $x['value'] ?? null; $val = ($v === null || $v === '') ? null : kpiNum_($v);
        if ($val !== null && (!is_numeric($val) || !is_finite((float) $val) || $val < 0)) fail_($p['nama'] . ': nilai target tidak valid.');
        $list[] = [$nik, $b, $val];
    }
    return with_lock_(function () use ($u, $period, $list, $reason) {
        kkSchema_(); $pdo = db_();
        foreach ($list as [$nik, $b, $val]) {
            if ($val === null) $pdo->prepare('DELETE FROM _kk_target WHERE nik=? AND period=? AND brand=?')->execute([$nik, $period, $b]);
            else $pdo->prepare('INSERT INTO _kk_target (nik,period,brand,value,actor,updated_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value),actor=VALUES(actor),updated_at=VALUES(updated_at)')->execute([$nik, $period, $b, (float) $val, $u['nik'], iso_now()]);
        }
        kkReset_(); data_rev_bump_('KPI Khusus'); audit_($u['nik'], 'KPI_KHUSUS_TARGET', $period . ' (' . count($list) . ' baris) · ' . $reason);
        return ['ok' => true, 'count' => count($list)];
    });
}
/** Salin target SO manual dari periode lain ke sel yang masih kosong. */
function apiKpiKhususTargetCopy($token, $from, $to, $reason = null) {
    demoAssertWritable_($token); $db = snapshot_(); $u = adminGuard_($token, $db);
    $from = PortalCore::month($from); $to = PortalCore::month($to); if ($from === $to) fail_('Periode asal dan tujuan sama.');
    $reason = kkReasonOk_($reason); assertPeriodOpen_($to);
    return with_lock_(function () use ($db, $u, $from, $to, $reason) {
        kkSchema_(); $src = kkManualTargets_($from); $dst = kkManualTargets_($to); $cfgs = kkRows_($to); $n = 0;
        foreach (kkManageable_($u, $db) as $p) {
            if ($p['role'] !== 'SALES' || !isset($cfgs[$p['nik']])) continue;
            foreach ($cfgs[$p['nik']]['items'] as $it) {
                if ($it['m'] !== 'SO') continue; $b = $it['b'];
                if (isset($dst[(string) $p['nik']][$b]) || !isset($src[(string) $p['nik']][$b])) continue;
                db_()->prepare('INSERT INTO _kk_target (nik,period,brand,value,actor,updated_at) VALUES (?,?,?,?,?,?)')->execute([$p['nik'], $to, $b, $src[(string) $p['nik']][$b], $u['nik'], iso_now()]); $n++;
            }
        }
        kkReset_(); data_rev_bump_('KPI Khusus'); audit_($u['nik'], 'KPI_KHUSUS_TARGETCOPY', $from . ' → ' . $to . ' (' . $n . ') · ' . $reason);
        return ['copied' => $n];
    });
}

// ------------------------------------------------------------------
// Tampilan: daftar dealer KPI khusus (Sales / ASM / atasan / Admin)
// ------------------------------------------------------------------

/** Baris dealer×brand untuk satu subjek. $dosOnly: hanya dealer yang masuk indikator DOS. */
function kkDealerRows_(array $db, string $period, array $p, array $cfg, array $ctx, bool $dosOnly = true): array {
    $idSet = array_flip(kkIds_($db, $p)); $people = $db['meta']['people'];
    $mtd = kkPairsAll_($db, $ctx['asof'], $ctx['start']); $r30 = kkPairsAll_($db, $ctx['asof'], StockCore::shift($ctx['asof'], -29));
    $map = [];
    foreach ($cfg['items'] as $it) {
        if (!in_array($it['m'], ['SO', 'DOS'], true)) continue; if ($dosOnly && $it['m'] !== 'DOS') continue;
        $basis = $it['basis'] ?? 'MTD';
        foreach (kkRowPairs_($basis === 'R30' ? $r30 : $mtd, $idSet, $it['b'], $it['cats'] ?? []) as $k => $pr) {
            if (!isset($map[$k])) $map[$k] = ['pr' => $pr, 'dos' => null, 'so' => false];
            if ($it['m'] === 'DOS' && !$map[$k]['dos']) $map[$k]['dos'] = $it; if ($it['m'] === 'SO') $map[$k]['so'] = true;
        }
    }
    $out = [];
    foreach ($map as $k => $e) {
        $pr = $mtd[$k] ?? $e['pr']; $it = $e['dos']; $basis = $it['basis'] ?? 'MTD'; $rule = $it ? kkRuleFor_($period, $it) : kkPeriodRule_($period);
        $c = $db['meta']['customers'][$pr['customer']] ?? null; $sales = PortalCore::person($people, $pr['owner']); $asm = $sales ? targetAsm_($people, $sales) : null;
        $so30 = $r30[$k]['so'] ?? 0; $a = kkDosPoints_($pr['stock'], $pr['so'], $ctx['days'], $rule); $b = kkDosPoints_($pr['stock'], $so30, 30, $rule);
        $use = $basis === 'R30' ? $b : $a;
        $out[] = ['dealer' => $pr['customer'], 'nama' => js_or($c['nama'] ?? null, $pr['customer']), 'brand' => $pr['brand'], 'grade' => $pr['grade'], 'nik' => $pr['owner'], 'sales' => $sales['nama'] ?? '', 'asmNik' => $asm['nik'] ?? '', 'asm' => $asm['nama'] ?? '', 'rgm' => kkRgmOf_($people, $asm), 'region' => js_or($sales['portfolio'] ?? null, $sales['region'] ?? ''), 'big' => $sales['wilayah'] ?? '',
            'stock' => $pr['stock'], 'so' => $basis === 'R30' ? $so30 : $pr['so'], 'days' => $basis === 'R30' ? 30 : $ctx['days'], 'basis' => $basis, 'dos' => $use['dos'], 'cond' => $use['cond'], 'points' => $it ? $use['points'] : null, 'dos30' => $b['dos'], 'dosMtd' => $a['dos'], 'bad' => $pr['bad'], 'inDos' => (bool) $it, 'inSo' => $e['so']];
    }
    usort($out, fn($x, $y) => ($y['dos'] ?? INF) <=> ($x['dos'] ?? INF));
    return $out;
}
function kkRgmOf_(array $people, ?array $asm): string {
    if (!$asm) return ''; $b = PortalCore::person($people, $asm['atasan'] ?? ''); return $b ? ($b['nama'] ?? '') : '';
}
/** Subjek (Sales/ASM) dengan konfigurasi yang boleh dilihat pemanggil. */
function apiKpiKhususDealers($token, $period = null) {
    $db = snapshot_(); $u = guard_($token, false, false, $db);
    $period = PortalCore::month($period ?: ($db['meta']['periods'][0] ?? null)); $rows = kkRows_($period); $people = $db['meta']['people'];
    if ($u['role'] === 'TELEMARKETING') fail_('Tidak tersedia untuk akun ini.');
    $visible = null;
    if ($u['role'] === 'SALES') $visible = [(string) $u['nik'] => true];
    elseif (in_array($u['role'], ['ASM', 'RGM'], true)) $visible = array_flip(array_map('strval', array_merge([$u['nik']], PortalCore::scope($people, $u['nik']))));
    elseif ($u['role'] === 'ADMIN') { $regs = $u['adminRegions'] ?? []; $visible = []; foreach ($people as $q) if (in_array($q['wilayah'] ?? null, $regs, true)) $visible[(string) $q['nik']] = true; }
    $ctx = kkAsof_($db, $period); $subjects = []; $dealers = [];
    foreach ($people as $p) {
        $c = $rows[$p['nik']] ?? null; if (!$c || !in_array($p['role'], ['SALES', 'ASM'], true)) continue;
        if ($visible !== null && !isset($visible[(string) $p['nik']])) continue;
        $hasDos = false; foreach ($c['items'] as $it) if ($it['m'] === 'DOS') $hasDos = true;
        $ids = kkIds_($db, $p); $tmp = false; $g = kkBuildGroup_($db, $period, $ids, $c, $tmp);
        $by = fn($m) => array_values(array_filter($g['indicators'], fn($i) => $i['metric'] === $m));
        $asm = $p['role'] === 'SALES' ? targetAsm_($people, $p) : $p;
        $subjects[] = ['nik' => $p['nik'], 'nama' => $p['nama'] ?? '', 'role' => $p['role'], 'asmNik' => $asm['nik'] ?? '', 'asm' => $asm['nama'] ?? '', 'rgm' => kkRgmOf_($people, $asm), 'region' => js_or($p['portfolio'] ?? null, $p['region'] ?? ''), 'big' => $p['wilayah'] ?? '', 'replace' => !empty($c['replace']), 'replaces' => $c['replaces'] ?? '', 'groupScore' => $g['score'], 'dosPoints' => $g['dosPoints'], 'hasDos' => $hasDos, 'dos' => $by('DOS'), 'so' => $by('SO'), 'st' => $by('ST'), 'da' => $by('DA')];
        if ($hasDos && $p['role'] === 'SALES' && $ctx) foreach (kkDealerRows_($db, $period, $p, $c, $ctx, true) as $d) $dealers[] = $d;
    }
    return ['period' => $period, 'asof' => $ctx['asof'] ?? null, 'days' => $ctx['days'] ?? null, 'partial' => $ctx['partial'] ?? false, 'subjects' => $subjects, 'dealers' => $dealers, 'rule' => kkPeriodRule_($period), 'role' => $u['role']];
}
