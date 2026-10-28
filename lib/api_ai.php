<?php
/**
 * AI Asisten DSM — read-only.
 * Gemini memilih fungsi (function calling); SERVER menjalankan fungsi dengan hak akses user dan
 * menyusun blok tampilan (kartu angka, tabel, grafik batang). AI hanya menulis ringkasan teks.
 * Tanpa kunci Gemini / saat gagal / saat kuota habis: router kata kunci lokal (fallbackReason diisi).
 *
 * config.php (opsional):
 *   define('DSM_GEMINI_KEY', '...');          // kunci dari Google AI Studio
 *   define('DSM_GEMINI_MODEL', 'gemini-3.6-flash');
 *   define('DSM_AI_DAILY', 60);               // batas pertanyaan per user per hari
 */
require_once __DIR__ . '/common.php';

function aiKey_(): string { return defined('DSM_GEMINI_KEY') ? (string) DSM_GEMINI_KEY : ''; }
function aiModel_(): string { return defined('DSM_GEMINI_MODEL') ? (string) DSM_GEMINI_MODEL : 'gemini-2.5-flash'; }
function aiNum_($v): string { return number_format((float) $v, 0, ',', '.'); }
function aiPct_($v): string { return $v === null ? '—' : number_format((float) $v, 1, ',', '.') . '%'; }
function aiCol_($p): string { return $p === null ? '#6F7B8E' : ($p < 60 ? '#E5354E' : ($p < 90 ? '#F5A623' : '#0E9F6E')); }
function aiPill_($p): string { return $p === null ? '' : ($p < 60 ? 'r' : ($p < 90 ? 'y' : 'g')); }
function aiBrandIn_(string $q, array $brands): ?string {
    foreach ($brands as $b) { if ($b !== '' && stripos($q, $b) !== false) return $b; }
    return null;
}

const AI_GROUPS = [
    ['cat' => 'Capaian & proyeksi', 'items' => ['Proyeksi akhir bulan tembus target nggak?', 'Brand mana yang paling ketinggalan dari target?', 'Ringkas performa minggu ini']],
    ['cat' => 'Tim & early warning', 'items' => ['Siapa yang proyeksinya bahaya?', 'Rincian KPI saya per indikator']],
    ['cat' => 'Dealer', 'items' => ['10 dealer prioritas kunjungan minggu ini', 'Dealer yang belum order bulan ini', 'Dealer mana yang berpotensi menambah Dealer Aktif?']],
    ['cat' => 'Stok & produk', 'items' => ['Dealer mana yang stoknya menipis?', 'TYPE apa yang paling laku bulan ini?', 'Tren penjualan per minggu']],
    ['cat' => 'Istilah', 'items' => ['Jelaskan arti DOS', 'Apa bedanya Sell Thru dan Sell Out?']],
];
const AI_FOLLOW = [
    'perf_summary' => ['Brand mana yang paling ketinggalan dari target?', 'Siapa yang proyeksinya bahaya?', '10 dealer prioritas kunjungan minggu ini'],
    'brand_achievement' => ['Siapa yang proyeksinya bahaya?', 'Dealer mana yang berpotensi menambah Dealer Aktif?', 'TYPE apa yang paling laku bulan ini?'],
    'early_warning' => ['Rincian KPI saya per indikator', '10 dealer prioritas kunjungan minggu ini', 'Brand mana yang paling ketinggalan dari target?'],
    'dealer_not_order' => ['10 dealer prioritas kunjungan minggu ini', 'Dealer mana yang berpotensi menambah Dealer Aktif?'],
    'dealer_priority' => ['Dealer yang belum order bulan ini', 'Dealer mana yang stoknya menipis?'],
    'dealer_potential' => ['10 dealer prioritas kunjungan minggu ini', 'Brand mana yang paling ketinggalan dari target?'],
    'dos_risk' => ['10 dealer prioritas kunjungan minggu ini', 'TYPE apa yang paling laku bulan ini?'],
    'kpi_detail' => ['Siapa yang proyeksinya bahaya?', 'Brand mana yang paling ketinggalan dari target?'],
    'top_type' => ['Dealer mana yang stoknya menipis?', 'Tren penjualan per minggu'],
    'weekly_trend' => ['Proyeksi akhir bulan tembus target nggak?', 'TYPE apa yang paling laku bulan ini?'],
    'glossary' => ['Proyeksi akhir bulan tembus target nggak?', 'Dealer mana yang stoknya menipis?'],
];

/* ---------- konteks & data (di-memo per request) ---------- */
function aiCtx_(string $token, string $period, string $sel): array {
    static $c = [];
    $k = $period . '|' . $sel;
    if (!isset($c[$k])) $c[$k] = ['token' => $token, 'period' => $period, 'sel' => $sel, 'dash' => null, 'ew' => null];
    return $c[$k];
}
function aiDash_(array &$x): array { if (!$x['dash']) $x['dash'] = apiDashboard($x['token'], $x['period'], $x['sel']); return $x['dash']; }
function aiEw_(array &$x, ?string $level = null): array { $k = $level ?: '_'; if (!isset($x['ew'][$k])) $x['ew'][$k] = apiEarlyWarning($x['token'], $x['period'], $level); return $x['ew'][$k]; }
function aiProj_($actual, $run, $days): ?float { return $run > 0 ? $actual / $run * $days : null; }

/* ---------- fungsi (tool) ---------- */
function aiTools_(): array {
    $brand = ['type' => 'string', 'description' => 'Nama brand persis seperti di data (mis. ITEL, MOTOPAD). Kosongkan bila tidak disebut.'];
    $region = ['type' => 'string', 'description' => 'Nama wilayah (Big Region, region atau sub region, mis. JAWA TIMUR). Isi jika user menyebut wilayah tertentu; kosongkan bila tidak.'];
    $person = ['type' => 'string', 'description' => 'Nama Sales, ASM atau RGM (mis. ARIFIN SORMANDANI). Isi jika user menyebut orang/tim tertentu: data dihitung untuk seluruh tim orang itu. Kosongkan bila tidak.'];
    $limit = ['type' => 'integer', 'description' => 'Jumlah baris yang diminta user (mis. 30). Default 10, maksimal 100. Isi 100 jika user minta semua.'];
    return [
        ['name' => 'perf_summary', 'description' => 'Ringkasan capaian total periode ini: aktual, target, proyeksi akhir bulan, pertumbuhan vs bulan lalu. Opsional per wilayah.', 'parameters' => ['person' => $person, 'region' => $region]],
        ['name' => 'brand_achievement', 'description' => 'Capaian Sell Thru per brand terhadap target, proyeksi dan pertumbuhan. Untuk pertanyaan brand mana yang tertinggal. Opsional per wilayah.', 'parameters' => ['person' => $person, 'region' => $region]],
        ['name' => 'early_warning', 'description' => 'Daftar orang/tim yang proyeksinya paling jauh dari target (Early Warning). level: SALES, ASM atau RGM.', 'parameters' => ['level' => ['type' => 'string', 'description' => 'SALES, ASM atau RGM. Kosongkan bila tidak disebut.'], 'brand' => $brand, 'person' => $person, 'region' => $region, 'limit' => $limit]],
        ['name' => 'dealer_not_order', 'description' => 'Dealer yang belum order (qty 0) pada periode ini, opsional per brand dan wilayah.', 'parameters' => ['brand' => $brand, 'person' => $person, 'region' => $region, 'limit' => $limit]],
        ['name' => 'dealer_priority', 'description' => 'Dealer prioritas kunjungan: sebelumnya order, sekarang diam atau lama tidak order. Opsional per wilayah.', 'parameters' => ['person' => $person, 'region' => $region, 'limit' => $limit]],
        ['name' => 'dealer_potential', 'description' => 'Dealer yang berpotensi menambah Dealer Aktif sebuah brand: aktif di brand lain tapi belum order brand tersebut.', 'parameters' => ['brand' => $brand, 'person' => $person, 'region' => $region, 'limit' => $limit]],
        ['name' => 'dos_risk', 'description' => 'Stok dealer: DOS menipis, habis, atau stok tinggi, beserta TYPE yang perlu restock.', 'parameters' => ['brand' => $brand, 'person' => $person, 'region' => $region, 'limit' => $limit, 'status' => ['type' => 'string', 'description' => 'MENIPIS, HABIS atau STOK TINGGI. Default MENIPIS.']]],
        ['name' => 'kpi_detail', 'description' => 'Rincian KPI milik user yang bertanya, per indikator (target, aktual, % capai, poin, bobot).', 'parameters' => null],
        ['name' => 'top_type', 'description' => 'TYPE (produk) paling laku periode ini, opsional per brand dan per orang/tim.', 'parameters' => ['brand' => $brand, 'person' => $person]],
        ['name' => 'weekly_trend', 'description' => 'Tren penjualan per minggu pada periode ini. Opsional per orang/tim.', 'parameters' => ['person' => $person]],
        ['name' => 'glossary', 'description' => 'Menjelaskan istilah DSM: DOS, LMTD, MTD, Sell Thru, Sell Out, proyeksi, Dealer Aktif, NOO.', 'parameters' => ['term' => ['type' => 'string', 'description' => 'Istilah yang ditanyakan.']]],
    ];
}

function aiPeople_(string $token): array { static $p = null; if ($p === null) { try { $p = apiBootstrap($token)['people'] ?? []; } catch (Throwable $e) { $p = []; } } return $p; }
function aiPersonFind_(string $q, string $token, bool $loose = false): ?array {
    $q = mb_strtoupper(trim($q)); if ($q === '') return null; $best = null; $bl = 0;
    foreach (aiPeople_($token) as $p) {
        $n = mb_strtoupper((string) ($p['nama'] ?? '')); if ($n === '' || strpos($n, 'VACANT') === 0 || !in_array($p['role'] ?? '', ['SALES', 'ASM', 'RGM'], true)) continue;
        $hit = $loose ? (preg_match('/(?<![A-Z0-9])' . preg_quote($n, '/') . '(?![A-Z0-9])/u', $q) === 1) : ($n === $q || strpos($n, $q) !== false);
        if (!$hit && $loose) { $tk = preg_split('/\s+/', $n); if (count($tk) >= 2) { $all = true; foreach ($tk as $t) if (strlen($t) > 1 && !preg_match('/(?<![A-Z0-9])' . preg_quote($t, '/') . '(?![A-Z0-9])/u', $q)) { $all = false; break; } $hit = $all; } }
        if ($hit && strlen($n) > $bl) { $best = $p; $bl = strlen($n); }
    }
    return $best;
}
function aiRun_(string $name, array $args, array &$x): array {
    $pn = trim((string) ($args['person'] ?? ''));
    if ($pn !== '' && $name !== 'glossary' && $name !== 'kpi_detail') {
        $pp = aiPersonFind_($pn, $x['token']);
        if (!$pp) return ['data' => ['error' => 'Nama ' . $pn . ' tidak ditemukan di cakupan akun ini.'], 'blocks' => [], 'summary' => 'Nama "' . $pn . '" tidak ditemukan di cakupan akun lu. Coba tulis nama persis seperti di daftar cakupan.'];
        $y = aiCtx_($x['token'], $x['period'], (string) $pp['nik']); $args['person'] = '';
        if ($name === 'early_warning') $args['region'] = (string) $pp['nama'];
        $r = aiRun_($name, $args, $y); $lbl = $pp['role'] . ' ' . $pp['nama'];
        $r['summary'] = '[' . $lbl . '] ' . $r['summary']; if (isset($r['data']) && is_array($r['data'])) $r['data']['cakupan'] = $lbl;
        return $r;
    }
    switch ($name) {
        case 'perf_summary': return aiPerf_($x, (string) ($args['region'] ?? ''));
        case 'brand_achievement': return aiBrands_($x, (string) ($args['region'] ?? ''));
        case 'early_warning': return aiEarly_($x, strtoupper((string) ($args['level'] ?? '')), (string) ($args['region'] ?? ''), aiLim_($args['limit'] ?? 0, 8));
        case 'dealer_not_order': return aiNotOrder_($x, (string) ($args['brand'] ?? ''), (string) ($args['region'] ?? ''), aiLim_($args['limit'] ?? 0));
        case 'dealer_priority': return aiPriority_($x, (string) ($args['region'] ?? ''), aiLim_($args['limit'] ?? 0));
        case 'dealer_potential': return aiPotential_($x, (string) ($args['brand'] ?? ''), (string) ($args['region'] ?? ''), aiLim_($args['limit'] ?? 0));
        case 'dos_risk': return aiDos_($x, (string) ($args['brand'] ?? ''), strtoupper((string) ($args['status'] ?? 'MENIPIS')), (string) ($args['region'] ?? ''), aiLim_($args['limit'] ?? 0, 8));
        case 'kpi_detail': return aiKpi_($x);
        case 'top_type': return aiTopType_($x, (string) ($args['brand'] ?? ''));
        case 'weekly_trend': return aiWeekly_($x);
        case 'glossary': return aiGloss_((string) ($args['term'] ?? ''));
    }
    return ['data' => ['error' => 'Fungsi tidak dikenal.'], 'blocks' => [], 'summary' => 'Belum bisa menjawab itu.'];
}

function aiRegBrands_(array &$x, string $region): array {
    $o = [];
    foreach (aiDash_($x)['regionalBrands'] ?? [] as $rb) if (stripos((string) $rb['region'], $region) !== false) {
        $k = $rb['brand']; $o[$k] ??= ['brand' => $k, 'current' => ['qty' => 0, 'da' => 0], 'previous' => ['qty' => 0], 'target' => ['exists' => false, 'value' => 0]];
        $o[$k]['current']['qty'] += (float) ($rb['total']['qty'] ?? 0); $o[$k]['current']['da'] += (float) ($rb['total']['da'] ?? 0);
        if (!empty($rb['target']['exists'])) { $o[$k]['target']['exists'] = true; $o[$k]['target']['value'] += (float) $rb['target']['value']; }
    }
    if ($o) return array_values($o);
    foreach (aiCust_($x, $region) as $c) foreach ($c['brands'] ?? [] as $bb) {
        $k = $bb['brand']; $o[$k] ??= ['brand' => $k, 'current' => ['qty' => 0, 'da' => 0], 'previous' => ['qty' => 0], 'target' => ['exists' => false, 'value' => 0]];
        $o[$k]['current']['qty'] += (float) $bb['qty']; if ((float) $bb['qty'] > 0) $o[$k]['current']['da']++;
    }
    return array_values($o);
}
function aiPerf_(array &$x, string $region = ''): array {
    $d = aiDash_($x); $ew = aiEw_($x); $t = $d['total']; $p = $d['previous'];
    $tg = $d['brandTotals']['target']; $target = !empty($tg['exists']) ? (float) $tg['value'] : null;
    if ($region !== '') { $t = ['qty' => 0, 'da' => 0, 'noo' => 0]; $p = ['qty' => 0]; $target = 0.0; foreach (aiRegBrands_($x, $region) as $b) { $t['qty'] += $b['current']['qty']; $t['da'] += $b['current']['da']; $target += $b['target']['value']; } if ($target <= 0) $target = null; }
    $proj = aiProj_($t['qty'], $ew['run'] ?? 0, $ew['days'] ?? 0);
    $pc = ($target && $proj !== null) ? $proj / $target * 100 : null; $ac = $target ? $t['qty'] / $target * 100 : null;
    $gr = !empty($p['qty']) ? ($t['qty'] / $p['qty'] - 1) * 100 : null;
    $data = ['aktual_qty' => $t['qty'], 'target_qty' => $target, 'capai_persen' => $ac, 'proyeksi_akhir_bulan' => $proj, 'proyeksi_persen_target' => $pc, 'growth_vs_bulan_lalu_persen' => $gr, 'dealer_aktif' => $t['da'], 'noo' => $t['noo'], 'hari_kerja_berjalan' => $ew['run'] ?? null, 'hari_kerja_total' => $ew['days'] ?? null, 'data_sampai' => $d['coverage']['cutoff'] ?? null];
    $blocks = [['t' => 'kpi', 'items' => [
        ['l' => 'Aktual', 'v' => aiNum_($t['qty']), 's' => 'MTD · ' . aiPct_($ac) . ' target', 'c' => '#2B7BE0'],
        ['l' => 'Proyeksi', 'v' => $proj === null ? '—' : aiNum_($proj), 's' => 'akhir bulan', 'c' => '#F07A0C'],
        ['l' => 'Target', 'v' => $target ? aiNum_($target) : '—', 's' => $pc === null ? 'target belum lengkap' : aiPct_($pc) . ' dari target', 'c' => aiCol_($pc)],
    ]]];
    return ['data' => $data, 'blocks' => $blocks, 'summary' => $target ? 'Aktual ' . aiNum_($t['qty']) . ' unit (' . aiPct_($ac) . ' dari target). Proyeksi akhir bulan ' . aiNum_($proj) . ' unit atau ' . aiPct_($pc) . ' dari target.' : 'Aktual ' . aiNum_($t['qty']) . ' unit. Target belum lengkap sehingga proyeksi terhadap target belum bisa dihitung.'];
}
function aiBrands_(array &$x, string $region = ''): array {
    $d = aiDash_($x); $ew = aiEw_($x); $rows = [];
    foreach ($region !== '' ? aiRegBrands_($x, $region) : $d['brands'] as $b) {
        $tg = !empty($b['target']['exists']) ? (float) $b['target']['value'] : null; $q = (float) $b['current']['qty'];
        $pr = aiProj_($q, $ew['run'] ?? 0, $ew['days'] ?? 0); $ac = $tg ? $q / $tg * 100 : null;
        $gr = !empty($b['previous']['qty']) ? ($q / $b['previous']['qty'] - 1) * 100 : null;
        $rows[] = ['brand' => $b['brand'], 'qty' => $q, 'target' => $tg, 'ac' => $ac, 'pr' => ($tg && $pr !== null) ? $pr / $tg * 100 : null, 'gr' => $gr];
    }
    usort($rows, fn($a, $b) => ($a['ac'] ?? 999) <=> ($b['ac'] ?? 999));
    $bars = array_map(fn($r) => ['l' => $r['brand'], 'v' => $r['ac'] ?? 0, 'txt' => aiPct_($r['ac']), 'c' => aiCol_($r['ac'])], $rows);
    $tb = array_map(fn($r) => [$r['brand'], aiNum_($r['qty']), $r['target'] ? aiNum_($r['target']) : '—', $r['pr'] === null ? '—' : aiPct_($r['pr']), $r['gr'] === null ? '—' : aiPct_($r['gr'])], $rows);
    $worst = $rows[0] ?? null;
    return ['data' => ['brands' => array_map(fn($r) => ['brand' => $r['brand'], 'aktual' => $r['qty'], 'target' => $r['target'], 'capai_persen' => $r['ac'], 'proyeksi_persen' => $r['pr'], 'growth_persen' => $r['gr']], $rows)],
        'blocks' => [['t' => 'bars', 'title' => 'Capaian Sell Thru per brand' . ($region ? ' · ' . $region : ''), 'rows' => $bars], ['t' => 'table', 'title' => 'Rincian per brand' . ($region ? ' · ' . $region : ''), 'cols' => ['Brand', 'Aktual', 'Target', 'Proyeksi', 'Growth'], 'rows' => $tb]],
        'summary' => !$worst ? 'Belum ada data brand.' : ($worst['ac'] === null ? 'Target brand untuk wilayah ini belum tersedia di data, jadi yang tampil hanya aktual per brand.' : $worst['brand'] . ' paling jauh dari target (' . aiPct_($worst['ac']) . ').')];
}
function aiEarly_(array &$x, string $level, string $region = '', int $lim = 8): array {
    $ew = aiEw_($x, in_array($level, ['SALES', 'ASM', 'RGM'], true) ? $level : null); $rows = [];
    foreach ($ew['rows'] ?? [] as $r) {
        if ((float) $r['target'] <= 0 || preg_match('/^VACANT/i', (string) $r['nama'])) continue;
        if ($region !== '' && stripos((string) ($r['region'] ?? ''), $region) === false && stripos((string) ($r['big'] ?? ''), $region) === false && stripos((string) ($r['boss'] ?? ''), $region) === false && stripos((string) ($r['nama'] ?? ''), $region) === false) continue;
        $pr = aiProj_($r['actual'], $ew['run'] ?? 0, $ew['days'] ?? 0); $pc = $pr === null ? null : $pr / $r['target'] * 100;
        $rows[] = ['nama' => $r['nama'], 'role' => $r['role'], 'actual' => $r['actual'], 'target' => $r['target'], 'proj' => $pr, 'pc' => $pc];
    }
    usort($rows, fn($a, $b) => ($a['pc'] ?? 999) <=> ($b['pc'] ?? 999)); $top = array_slice($rows, 0, $lim);
    $tb = array_map(fn($r) => [$r['nama'], aiNum_($r['actual']), aiNum_($r['proj'] ?? 0), '<span class="pc ' . aiPill_($r['pc']) . '">' . aiPct_($r['pc']) . '</span>'], $top);
    $bad = count(array_filter($rows, fn($r) => ($r['pc'] ?? 999) < 60));
    return ['data' => ['level' => $ew['level'] ?? $level, 'wilayah' => $region ?: 'semua', 'jumlah_dengan_target' => count($rows), 'jumlah_bahaya_kurang_60_persen' => $bad, 'terburuk' => array_map(fn($r) => ['nama' => $r['nama'], 'aktual' => $r['actual'], 'proyeksi' => $r['proj'], 'proyeksi_persen_target' => $r['pc']], $top)],
        'blocks' => [['t' => 'table', 'title' => 'Early Warning · ' . ($ew['level'] ?? '') . ($region ? ' · ' . $region : ''), 'cols' => ['Nama', 'Aktual', 'Proyeksi', 'Proyeksi/target'], 'rows' => $tb, 'html' => [3]]],
        'summary' => count($rows) ? $bad . ' dari ' . count($rows) . ' berstatus bahaya (proyeksi di bawah 60% target). Paling jauh: ' . $top[0]['nama'] . ' (' . aiPct_($top[0]['pc']) . ').' : 'Belum ada target yang lengkap untuk dihitung' . ($region ? ' di ' . $region : '') . '.'];
}
function aiCust_(array &$x, string $region = ''): array {
    $all = aiDash_($x)['customers'] ?? []; if ($region === '') return $all;
    return array_values(array_filter($all, fn($c) => stripos((string) ($c['bigRegion'] ?? ''), $region) !== false || stripos((string) ($c['region'] ?? ''), $region) !== false || stripos((string) ($c['subRegion'] ?? ''), $region) !== false));
}
function aiRegionIn_(string $q, array &$x): string {
    static $names = null; if ($names === null) { $names = []; foreach (aiDash_($x)['customers'] ?? [] as $c) foreach (['bigRegion', 'region', 'subRegion'] as $k) { $v = trim((string) ($c[$k] ?? '')); if (strlen($v) >= 4) $names[$v] = true; } $names = array_keys($names); usort($names, fn($a, $b) => strlen($b) <=> strlen($a)); }
    foreach ($names as $n) if (stripos($q, $n) !== false) return $n;
    return '';
}
function aiRegionList_(array &$x): array { $n = []; foreach (aiDash_($x)['customers'] ?? [] as $c) foreach (['bigRegion', 'region'] as $k) { $v = trim((string) ($c[$k] ?? '')); if ($v !== '') $n[$v] = 1; } return array_keys($n); }
function aiLimitIn_(string $q): int {
    if (preg_match('/(\d{1,3})\s*(dealer|besar|teratas|top|orang|sales)/i', $q, $m) || preg_match('/top\s*(\d{1,3})/i', $q, $m)) return max(1, min(100, (int) $m[1]));
    if (preg_match('/semua|seluruh|lengkap|full/i', $q)) return 100;
    return 0;
}
function aiLim_($v, int $def = 10): int { $n = (int) $v; return $n > 0 ? min(100, $n) : $def; }
function aiBq_(array $c, string $brand): float { foreach ($c['brands'] ?? [] as $b) if ($b['brand'] === $brand) return (float) $b['qty']; return 0.0; }
function aiNotOrder_(array &$x, string $brand, string $region = '', int $lim = 10): array {
    $rows = [];
    foreach (aiCust_($x, $region) as $c) {
        $q = $brand ? aiBq_($c, $brand) : (float) $c['current']['qty'];
        if ($q > 0) continue;
        $rows[] = ['n' => $c['nama'], 'id' => $c['id'], 'sales' => $c['sales'] ?? '', 'jeda' => $c['jeda'] ?? null, 'prev' => (float) ($c['previous']['qty'] ?? 0)];
    }
    usort($rows, fn($a, $b) => $b['prev'] <=> $a['prev']); $top = array_slice($rows, 0, $lim); $w = ($brand ? ' · ' . $brand : '') . ($region ? ' · ' . $region : '');
    return ['data' => ['brand' => $brand ?: 'semua', 'wilayah' => $region ?: 'semua', 'jumlah_belum_order' => count($rows), 'ditampilkan' => count($top), 'daftar_terbesar_bulan_lalu' => array_map(fn($r) => ['dealer' => $r['n'], 'qty_bulan_lalu' => $r['prev'], 'hari_sejak_order' => $r['jeda']], $top)],
        'blocks' => [['t' => 'table', 'title' => 'Dealer belum order' . $w . ' (' . count($top) . ' dari ' . aiNum_(count($rows)) . ', urut bulan lalu terbesar)', 'cols' => ['Dealer', 'Sales', 'Terakhir', 'Bln lalu'], 'rows' => array_map(fn($r) => [$r['n'], $r['sales'], $r['jeda'] === null ? '—' : $r['jeda'] . ' hari', aiNum_($r['prev'])], $top)]],
        'summary' => aiNum_(count($rows)) . ' dealer belum order' . ($brand ? ' ' . $brand : '') . ($region ? ' di ' . $region : '') . ' bulan ini.'];
}
function aiPriority_(array &$x, string $region = '', int $lim = 10): array {
    $rows = [];
    foreach (aiCust_($x, $region) as $c) {
        $prev = (float) ($c['previous']['qty'] ?? 0); $cur = (float) $c['current']['qty']; $jeda = (int) ($c['jeda'] ?? 0);
        if ($prev <= 0 || ($cur > 0 && $jeda < 14)) continue;
        $rows[] = ['n' => $c['nama'], 'sales' => $c['sales'] ?? '', 'jeda' => $jeda, 'prev' => $prev, 'cur' => $cur, 'skor' => $prev * (1 + min($jeda, 60) / 30)];
    }
    usort($rows, fn($a, $b) => $b['skor'] <=> $a['skor']); $top = array_slice($rows, 0, $lim);
    return ['data' => ['wilayah' => $region ?: 'semua', 'jumlah_kandidat' => count($rows), 'dealer_prioritas' => array_map(fn($r) => ['dealer' => $r['n'], 'sales' => $r['sales'], 'hari_sejak_order' => $r['jeda'], 'qty_bulan_lalu' => $r['prev'], 'qty_bulan_ini' => $r['cur']], $top)],
        'blocks' => [['t' => 'table', 'title' => 'Dealer prioritas kunjungan' . ($region ? ' · ' . $region : '') . ' (' . count($top) . ' dari ' . aiNum_(count($rows)) . ')', 'cols' => ['Dealer', 'Sales', 'Terakhir', 'Bln lalu'], 'rows' => array_map(fn($r) => [$r['n'], $r['sales'], $r['jeda'] . ' hari', aiNum_($r['prev'])], $top)]],
        'summary' => 'Dipilih dari dealer yang ordernya besar bulan lalu tapi sekarang diam atau lama tidak order.'];
}
function aiPotential_(array &$x, string $brand, string $region = '', int $lim = 10): array {
    $d = aiDash_($x); $names = array_map(fn($b) => $b['brand'], $d['brands']);
    if (!$brand) return ['data' => ['perlu_brand' => true, 'brand_tersedia' => $names], 'blocks' => [['t' => 'chips', 'items' => array_map(fn($b) => 'Dealer mana yang berpotensi menambah Dealer Aktif ' . $b . '?', $names)]], 'summary' => 'Brand yang mana? Pilih salah satu di bawah.'];
    $cur = null; foreach ($d['brands'] as $b) if ($b['brand'] === $brand) $cur = $b;
    $rows = []; $da = 0; $scope = aiCust_($x, $region);
    foreach ($scope as $c) {
        if (aiBq_($c, $brand) > 0) { $da++; continue; }
        if ((float) $c['current']['qty'] <= 0) continue;
        $st = $c['bs'][$brand] ?? '—'; $rows[] = ['n' => $c['nama'], 'sales' => $c['sales'] ?? '', 'tot' => (float) $c['current']['qty'], 'st' => $st];
    }
    usort($rows, fn($a, $b) => $b['tot'] <=> $a['tot']); $top = array_slice($rows, 0, $lim);
    $tgDA = null;
    if ($region === '') { $tgDA = $cur['targetDA']['value'] ?? null; $da = $cur['current']['da'] ?? $da; }
    else { $s = 0; $f = false; foreach ($d['regionalBrands'] ?? [] as $rb) if ($rb['brand'] === $brand && stripos((string) $rb['region'], $region) !== false && isset($rb['targetDA']['value'])) { $s += (float) $rb['targetDA']['value']; $f = true; } if ($f) $tgDA = $s; }
    $gap = $tgDA !== null ? max(0, $tgDA - $da) : null; $w = $region ? ' di ' . $region : '';
    $blocks = [['t' => 'kpi', 'items' => array_values(array_filter([['l' => 'Dealer aktif', 'v' => aiNum_($da), 's' => $brand . ($region ? ' · ' . $region : ''), 'c' => '#2B7BE0'], $tgDA !== null ? ['l' => 'Target', 'v' => aiNum_($tgDA), 's' => 'Dealer Aktif', 'c' => '#F07A0C'] : null, $gap !== null ? ['l' => 'Kurang', 'v' => aiNum_($gap), 's' => 'dealer lagi', 'c' => $gap > 0 ? '#E5354E' : '#0E9F6E'] : null]))]];
    $blocks[] = ['t' => 'table', 'title' => 'Dealer berpotensi · ' . $brand . ($region ? ' · ' . $region : '') . ' (' . count($top) . ' dari ' . aiNum_(count($rows)) . ')', 'cols' => ['Dealer', 'Sales', 'Qty brand lain', 'Status'], 'rows' => array_map(fn($r) => [$r['n'], $r['sales'], aiNum_($r['tot']), $r['st']], $top)];
    return ['data' => ['brand' => $brand, 'wilayah' => $region ?: 'nasional', 'dealer_aktif' => $da, 'target_dealer_aktif' => $tgDA, 'kurang' => $gap, 'jumlah_kandidat' => count($rows), 'ditampilkan' => count($top), 'kandidat_teratas' => array_map(fn($r) => ['dealer' => $r['n'], 'qty_brand_lain' => $r['tot'], 'status_brand' => $r['st']], $top)],
        'blocks' => $blocks, 'summary' => aiNum_(count($rows)) . ' dealer aktif di brand lain tapi belum order ' . $brand . $w . '.' . ($gap !== null ? ' Kurang ' . aiNum_($gap) . ' dealer aktif dari target.' : '')];
}
function aiDos_(array &$x, string $brand, string $status, string $region = '', int $lim = 8): array {
    if (!in_array($status, ['MENIPIS', 'HABIS', 'STOK TINGGI'], true)) $status = 'MENIPIS';
    $r = apiDOS($x['token'], $x['period'], $x['sel'], ['view' => 'TYPE', 'brand' => $brand ?: 'ALL', 'status' => $status, 'region' => 'ALL', 'subRegion' => 'ALL', 'type' => 'ALL', 'query' => '', 'pageSize' => 200], 0);
    if (empty($r['ready'])) return ['data' => ['error' => $r['message'] ?? 'Data stok belum siap.'], 'blocks' => [], 'summary' => (string) ($r['message'] ?? 'Data stok belum siap.')];
    $rows = $r['rows'] ?? [];
    if ($region !== '') { $nm = []; foreach (aiCust_($x, $region) as $c) $nm[$c['nama']] = 1; $rows = array_values(array_filter($rows, fn($q) => isset($nm[$q['nama']]))); }
    usort($rows, fn($a, $b) => ($b['sellout'] ?? 0) <=> ($a['sellout'] ?? 0)); $top = array_slice($rows, 0, $lim);
    $t = $r['total'] ?? [];
    return ['data' => ['status' => $status, 'brand' => $brand ?: 'semua', 'dos_gabungan_hari' => $t['dos'] ?? null, 'jumlah_pasangan_dealer_type_menipis_atau_habis' => $t['low'] ?? null, 'teratas' => array_map(fn($q) => ['dealer' => $q['nama'], 'type' => $q['type'], 'stok' => $q['stock'], 'so30' => $q['sellout'], 'dos' => $q['dos'] ?? null], $top)],
        'blocks' => [['t' => 'table', 'title' => 'Stok ' . strtolower($status) . ($brand ? ' · ' . $brand : '') . ($region ? ' · ' . $region : '') . ' (SO 30 hari terbesar)', 'cols' => ['Dealer', 'TYPE', 'Stok', 'SO 30h', 'DOS'], 'rows' => array_map(fn($q) => [$q['nama'], $q['type'], aiNum_($q['stock']), aiNum_($q['sellout']), isset($q['dos']) && $q['dos'] !== null ? ($q['dos'] > 365 ? '>365' : number_format($q['dos'], 1, ',', '.')) : '—'], $top)]],
        'summary' => 'Ada ' . aiNum_(count($rows)) . ' pasangan dealer–TYPE berstatus ' . $status . '. Yang tampil diurutkan dari penjualan 30 hari terbesar.'];
}
function aiKpi_(array &$x): array {
    $u = guard_($x['token']); $k = apiPersonKpi($x['token'], $x['period'], $u['nik']);
    if (empty($k['groups'])) return ['data' => ['status' => $k['status'] ?? 'KPI belum tersedia'], 'blocks' => [], 'summary' => 'KPI belum tersedia untuk akun ini (' . ($k['status'] ?? 'belum ada kebijakan') . ').'];
    $blocks = [['t' => 'kpi', 'items' => [['l' => 'Skor KPI', 'v' => aiPct_($k['score'] ?? null), 's' => (string) ($k['status'] ?? ''), 'c' => aiCol_($k['score'] ?? null)]]]]; $data = ['skor' => $k['score'] ?? null, 'grup' => []];
    foreach ($k['groups'] as $g) {
        $rows = []; $gi = [];
        foreach ($g['indicators'] ?? [] as $i) { $rows[] = [$i['label'], aiNum_($i['weight']) . '%', aiNum_($i['target']), aiNum_($i['actual']), aiPct_($i['ach'] ?? null)]; $gi[] = ['indikator' => $i['label'], 'bobot' => $i['weight'], 'target' => $i['target'], 'aktual' => $i['actual'], 'capai_persen' => $i['ach'] ?? null]; }
        $blocks[] = ['t' => 'table', 'title' => $g['name'] . ' · skor ' . aiPct_($g['score'] ?? null), 'cols' => ['Indikator', 'Bobot', 'Target', 'Aktual', '% capai'], 'rows' => $rows]; $data['grup'][] = ['nama' => $g['name'], 'skor' => $g['score'] ?? null, 'indikator' => $gi];
    }
    return ['data' => $data, 'blocks' => $blocks, 'summary' => 'Skor KPI ' . aiPct_($k['score'] ?? null) . '.'];
}
function aiTopType_(array &$x, string $brand): array {
    $d = aiDash_($x); $t = array_values(array_filter($d['types'] ?? [], fn($r) => !$brand || $r['brand'] === $brand));
    usort($t, fn($a, $b) => $b['qty'] <=> $a['qty']); $top = array_slice($t, 0, 8); $mx = max(1, ...array_map(fn($r) => $r['qty'], $top ?: [['qty' => 1]]));
    return ['data' => ['brand' => $brand ?: 'semua', 'type_teratas' => array_map(fn($r) => ['type' => $r['type'], 'qty' => $r['qty'], 'sellout' => $r['sellout']], $top)],
        'blocks' => [['t' => 'bars', 'title' => 'TYPE terlaris' . ($brand ? ' · ' . $brand : ''), 'rows' => array_map(fn($r) => ['l' => $r['type'], 'v' => $r['qty'] / $mx * 100, 'txt' => aiNum_($r['qty']), 'c' => '#2B7BE0', 'wide' => true], $top)]],
        'summary' => $top ? $top[0]['type'] . ' paling laku (' . aiNum_($top[0]['qty']) . ' unit).' : 'Belum ada data TYPE.'];
}
function aiWeekly_(array &$x): array {
    $d = aiDash_($x); $w = $d['weekly'] ?? []; $mx = max(1, ...array_map(fn($r) => $r['qty'], $w ?: [['qty' => 1]]));
    $best = null; foreach ($w as $r) if (!$best || $r['qty'] > $best['qty']) $best = $r;
    return ['data' => ['per_minggu' => array_map(fn($r) => ['minggu' => $r['week'], 'qty' => $r['qty']], $w)],
        'blocks' => [['t' => 'bars', 'title' => 'Sell Thru per minggu', 'rows' => array_map(fn($r) => ['l' => 'Minggu ' . $r['week'], 'v' => $r['qty'] / $mx * 100, 'txt' => aiNum_($r['qty']), 'c' => '#2B7BE0'], $w)]],
        'summary' => $best ? 'Minggu ' . $best['week'] . ' paling tinggi (' . aiNum_($best['qty']) . ' unit).' : 'Belum ada data mingguan.'];
}
function aiGloss_(string $term): array {
    $g = [
        'DOS' => 'DOS (Days of Stock) = estimasi stok ÷ rata-rata Sell Out harian (30 hari). Artinya stok cukup untuk berapa hari. Di bawah 7 hari menipis, di atas 45 hari stok tinggi.',
        'LMTD' => 'LMTD = periode yang sama bulan lalu (tanggal 1 sampai tanggal data yang sama), dipakai sebagai pembanding adil untuk MTD.',
        'MTD' => 'MTD (Month to Date) = akumulasi dari tanggal 1 sampai tanggal data terakhir bulan ini.',
        'SELL THRU' => 'Sell Thru (ST) = penjualan dari distributor ke dealer. Ini yang dihitung ke target.',
        'SELL OUT' => 'Sell Out (SO) = penjualan dari dealer ke konsumen akhir.',
        'PROYEKSI' => 'Proyeksi = aktual ÷ hari kerja berjalan × total hari kerja bulan itu. Asumsinya ritme sekarang bertahan.',
        'DEALER AKTIF' => 'Dealer Aktif = dealer yang order pada periode berjalan sesuai aturan brand (batas idle dan pasif diatur di Admin).',
        'NOO' => 'NOO (New Open Outlet) = dealer yang pertama kali bertransaksi pada bulan itu.',
    ];
    $q = strtoupper($term); $hit = null;
    foreach ($g as $k => $v) if (strpos($q, $k) !== false || ($k === 'SELL THRU' && strpos($q, 'THRU') !== false) || ($k === 'SELL OUT' && strpos($q, ' OUT') !== false)) { $hit = $k; break; }
    if ($hit === null) return ['data' => ['istilah_tersedia' => array_keys($g)], 'blocks' => [], 'summary' => 'Istilah itu belum ada di kamus. Tersedia: ' . implode(', ', array_keys($g)) . '.'];
    $both = ($q !== '' && strpos($q, 'THRU') !== false && strpos($q, 'OUT') !== false);
    $txt = $both ? $g['SELL THRU'] . ' ' . $g['SELL OUT'] : $g[$hit];
    return ['data' => ['penjelasan' => $txt], 'blocks' => [['t' => 'note', 'text' => $txt]], 'summary' => $txt];
}

/* ---------- router kata kunci (cadangan) ---------- */
function aiRoute_(string $q, array $brands): array {
    $l = mb_strtolower($q); $b = aiBrandIn_($q, $brands) ?? '';
    $m = fn($re) => preg_match($re, $l) === 1;
    if ($m('/arti|apa itu|apa bedanya|bedanya|jelaskan|istilah|cara hitung/')) return ['glossary', ['term' => $q]];
    if ($m('/kpi/')) return ['kpi_detail', []];
    if ($m('/potensi|dealer aktif|cross/')) return ['dealer_potential', ['brand' => $b]];
    if ($m('/prioritas|kunjungi|kunjungan/')) return ['dealer_priority', []];
    if ($m('/belum order|belum transaksi|nggak order|tidak order/')) return ['dealer_not_order', ['brand' => $b]];
    if ($m('/stok|dos|menipis|restock|habis/')) return ['dos_risk', ['brand' => $b, 'status' => $m('/habis/') ? 'HABIS' : ($m('/tinggi/') ? 'STOK TINGGI' : 'MENIPIS')]];
    if ($m('/bahaya|early|tertinggal|siapa/')) return ['early_warning', ['brand' => $b]];
    if ($m('/ketinggalan|per brand|capaian brand|brand mana/')) return ['brand_achievement', []];
    if ($m('/type|laku|terlaris/')) return ['top_type', ['brand' => $b]];
    if ($m('/proyeksi|tembus|target|capaian|performa|ringkas/') && !$m('/per minggu|tren|trend/')) return ['perf_summary', []];
    if ($m('/minggu|tren|trend/')) return ['weekly_trend', []];
    return ['', []];
}

/* ---------- Gemini ---------- */
function aiGemini_(array $body): array {
    $url = (defined('DSM_GEMINI_BASE') ? (string) DSM_GEMINI_BASE : 'https://generativelanguage.googleapis.com/v1beta') . '/models/' . rawurlencode(aiModel_()) . ':generateContent';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . aiKey_()], CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE)]);
    $res = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($res === false) throw new RuntimeException('Tidak bisa menghubungi Gemini: ' . $err);
    $j = json_decode((string) $res, true);
    if ($code === 429) throw new RuntimeException('Kuota Gemini habis sementara.');
    if ($code >= 400 || !is_array($j)) throw new RuntimeException('Gemini error ' . $code . ': ' . substr((string) ($j['error']['message'] ?? $res), 0, 160));
    return $j;
}
function aiLlm_(string $q, array $context, array &$x, array $brands, array &$blocks, ?string &$intent): string {
    $decl = []; foreach (aiTools_() as $t) { $f = ['name' => $t['name'], 'description' => $t['description']]; if ($t['parameters']) $f['parameters'] = ['type' => 'object', 'properties' => $t['parameters']]; $decl[] = $f; }
    $sys = "Kamu DSM AI, asisten data penjualan distribusi. Jawab dalam Bahasa Indonesia yang santai dan jelas, maksimal 3 kalimat.\n"
        . "ATURAN: semua angka HARUS berasal dari hasil fungsi. Jangan menghitung atau mengarang angka. Tabel dan grafik sudah ditampilkan otomatis oleh aplikasi dari hasil fungsi, jadi cukup beri kesimpulan dan sorotan, jangan mengulang semua angka.\n"
        . "Jika pertanyaan tidak bisa dijawab oleh fungsi yang ada, katakan terus terang belum bisa. Jangan menebak penyebab; hanya tunjukkan pola dari data. Kalau brand tidak jelas untuk fungsi yang butuh brand, tanyakan balik.\n"
        . "Jika user menyebut wilayah (mis. Jawa Timur, Jabar), isi parameter region dengan nama wilayah itu; jika user menyebut jumlah (mis. 30 dealer) isi limit dengan angka itu. Jika user menyebut nama Sales/ASM/RGM, isi person dengan nama itu (jangan isi region). Wilayah yang ada: " . implode(', ', array_slice(aiRegionList_($x), 0, 60)) . ".\n"
        . "Brand yang ada: " . implode(', ', $brands) . ". Periode " . $x['period'] . ".";
    $contents = [];
    foreach ($context as $m) { $t = trim((string) ($m['text'] ?? '')); if ($t === '') continue; $contents[] = ['role' => ($m['role'] ?? '') === 'assistant' ? 'model' : 'user', 'parts' => [['text' => mb_substr($t, 0, 600)]]]; }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $q]]];
    for ($round = 0; $round < 3; $round++) {
        $j = aiGemini_(['systemInstruction' => ['parts' => [['text' => $sys]]], 'contents' => $contents, 'tools' => [['functionDeclarations' => $decl]], 'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 600]]);
        $parts = $j['candidates'][0]['content']['parts'] ?? [];
        $calls = array_values(array_filter($parts, fn($p) => isset($p['functionCall'])));
        if (!$calls) { $text = trim(implode("\n", array_map(fn($p) => (string) ($p['text'] ?? ''), $parts))); if ($text === '') throw new RuntimeException('Gemini tidak memberi jawaban.'); return $text; }
        $contents[] = ['role' => 'model', 'parts' => $parts]; $resp = [];
        foreach ($calls as $c) {
            $name = (string) $c['functionCall']['name']; $args = (array) ($c['functionCall']['args'] ?? []);
            try { $r = aiRun_($name, $args, $x); } catch (Throwable $e) { $r = ['data' => ['error' => $e->getMessage()], 'blocks' => [], 'summary' => '']; }
            $intent = $intent ?: $name; foreach ($r['blocks'] as $b) $blocks[] = $b;
            $resp[] = ['functionResponse' => ['name' => $name, 'response' => ['hasil' => $r['data']]]];
        }
        $contents[] = ['role' => 'user', 'parts' => $resp];
    }
    throw new RuntimeException('Gemini terlalu banyak langkah.');
}

/* ---------- API ---------- */
function apiAIOptions($token = null) {
    $u = guard_($token);
    return ['model' => aiKey_() !== '' ? aiModel_() : 'Mode dasar', 'role' => $u['role'], 'groups' => AI_GROUPS, 'geminiOn' => aiKey_() !== ''];
}
function aiAudit_(array $u, string $q, string $intent, string $scope, int $rows, string $model, string $status, int $ms): void {
    try { db_()->prepare('INSERT INTO sh_ai_audit_log (TIMESTAMP,NIK,ROLE,QUESTION,INTENT,SCOPE,ROWS_RETURNED,MODEL,STATUS,DURATION_MS) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([gmdate('c'), $u['nik'], $u['role'], mb_substr($q, 0, 500), $intent, $scope, (string) $rows, $model, $status, (string) $ms]); } catch (Throwable $e) {}
}
function apiAIChat($token = null, $period = null, $selection = null, $question = null, $context = null) {
    $t0 = microtime(true); $u = guard_($token);
    $q = trim((string) $question); if ($q === '') fail_('Tulis pertanyaannya dulu.'); if (mb_strlen($q) > 1000) fail_('Pertanyaan terlalu panjang.');
    $period = (string) $period; $sel = (string) ($selection ?: 'ALL');
    $key = 'AI_RATE_' . $u['nik'] . '_' . gmdate('Ymd'); $used = (int) (prop_get_($key) ?? 0); $lim = defined('DSM_AI_DAILY') ? (int) DSM_AI_DAILY : 60;
    if ($used >= $lim) { aiAudit_($u, $q, '', $sel, 0, '', 'LIMIT', 0); fail_('Batas ' . $lim . ' pertanyaan per hari sudah tercapai. Coba lagi besok.'); }
    prop_set_($key, (string) ($used + 1));
    $x = aiCtx_($token, $period, $sel); $brands = array_map(fn($b) => $b['brand'], aiDash_($x)['brands'] ?? []);
    $blocks = []; $intent = null; $text = ''; $model = 'Mode dasar'; $fallback = null;
    if (aiKey_() !== '') {
        try { $text = aiLlm_($q, is_array($context) ? array_slice($context, -6) : [], $x, $brands, $blocks, $intent); $model = aiModel_(); }
        catch (Throwable $e) { $fallback = $e->getMessage(); $blocks = []; $intent = null; }
    } else { $fallback = 'Kunci Gemini belum dipasang'; }
    if ($model === 'Mode dasar') {
        [$fn, $args] = aiRoute_($q, $brands);
        if (in_array($fn, ['top_type', 'weekly_trend', 'perf_summary', 'brand_achievement', 'dealer_potential', 'dealer_not_order', 'dealer_priority', 'dos_risk', 'early_warning'], true)) { $pe = aiPersonFind_($q, $x['token'], true); if ($pe) $args['person'] = (string) $pe['nama']; else { $rg = aiRegionIn_($q, $x); if ($rg !== '') $args['region'] = $rg; } $lm = aiLimitIn_($q); if ($lm > 0) $args['limit'] = $lm; }
        if ($fn === '') { $text = 'Maaf, pertanyaan itu belum bisa gue jawab. Sudah dicatat supaya bisa ditambahkan. Coba salah satu pertanyaan contoh.'; $intent = 'UNSUPPORTED'; }
        else { $r = aiRun_($fn, $args, $x); $text = $r['summary']; $blocks = $r['blocks']; $intent = $fn; }
    }
    $blocks = array_slice($blocks, 0, 4);
    $ms = (int) round((microtime(true) - $t0) * 1000);
    aiAudit_($u, $q, (string) $intent, $sel, count($blocks), $model, $intent === 'UNSUPPORTED' ? 'UNSUPPORTED' : ($fallback ? 'FALLBACK' : 'OK'), $ms);
    $follow = AI_FOLLOW[$intent] ?? [];
    return ['intent' => $intent ?: 'PERFORMANCE_SUMMARY', 'answer' => $text, 'blocks' => $blocks, 'follow' => $follow, 'scope' => $sel, 'model' => $model, 'fallbackReason' => $model === 'Mode dasar' ? $fallback : null];
}
