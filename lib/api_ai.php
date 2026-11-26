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

function aiGeminiKey_(): string { return defined('DSM_GEMINI_KEY') ? (string) DSM_GEMINI_KEY : ''; }
function aiOpenaiKey_(): string { return defined('DSM_OPENAI_KEY') ? trim((string) DSM_OPENAI_KEY) : ''; }
/** Penyedia AI: 'openai' hanya bila DSM_AI_PROVIDER = 'openai' DAN kunci OpenAI terpasang; selain itu Gemini (bawaan, tidak berubah). */
function aiProvider_(): string { return (defined('DSM_AI_PROVIDER') && strtolower((string) DSM_AI_PROVIDER) === 'openai' && aiOpenaiKey_() !== '') ? 'openai' : 'gemini'; }
function aiKey_(): string { return aiProvider_() === 'openai' ? aiOpenaiKey_() : aiGeminiKey_(); }
function aiOpenaiModel_(): string { return defined('DSM_OPENAI_MODEL') && trim((string) DSM_OPENAI_MODEL) !== '' ? trim((string) DSM_OPENAI_MODEL) : 'gpt-4.1-mini'; }
function aiShownModel_(): string { return aiProvider_() === 'openai' ? aiOpenaiModel_() : aiModel_(); }
function aiModel_(): string { return defined('DSM_GEMINI_MODEL') ? (string) DSM_GEMINI_MODEL : 'gemini-3.8-flash'; }
function aiNum_($v): string { return number_format((float) $v, 0, ',', '.'); }
function aiPct_($v): string { return $v === null ? '—' : number_format((float) $v, 1, ',', '.') . '%'; }
function aiCol_($p): string { return $p === null ? '#6F7B8E' : ($p < 60 ? '#E5354E' : ($p < 90 ? '#F5A623' : '#0E9F6E')); }
function aiPill_($p): string { return $p === null ? '' : ($p < 60 ? 'r' : ($p < 90 ? 'y' : 'g')); }
function aiBrandIn_(string $q, array $brands): ?string {
    foreach ($brands as $b) { if ($b !== '' && stripos($q, $b) !== false) return $b; }
    return null;
}

const AI_GROUPS = [
    ['cat' => 'Capaian & proyeksi', 'items' => ['Ringkasan bulan ini', 'Run rate dan target harian berapa?', 'Proyeksi akhir bulan tembus target nggak?', 'Fokus minggu ini apa?']],
    ['cat' => 'Penjualan harian', 'items' => ['Penjualan 7 hari terakhir', 'Penjualan kemarin berapa?', 'Hari apa penjualan paling tinggi?']],
    ['cat' => 'Brand & type', 'items' => ['Brand mana yang paling ketinggalan dari target?', 'Kontribusi tiap brand berapa?', 'NOO per brand', 'TYPE apa yang paling laku bulan ini?']],
    ['cat' => 'Tim, KPI & ranking', 'items' => ['Siapa yang proyeksinya bahaya?', 'Rincian KPI saya per indikator', 'KPI tim siapa yang terendah?', 'Ranking sales', 'Region mana yang paling bagus?']],
    ['cat' => 'Dealer', 'items' => ['10 dealer prioritas kunjungan minggu ini', 'Dealer yang hilang bulan ini', 'Top 10 dealer', 'Dealer paling turun', 'Dealer mana yang berpotensi menambah Dealer Aktif?', 'Konsentrasi dealer besar']],
    ['cat' => 'Stok', 'items' => ['Dealer mana yang stoknya menipis?', 'TYPE apa yang habis di banyak dealer?', 'Dealer yang stoknya numpuk']],
    ['cat' => 'Istilah', 'items' => ['Jelaskan arti DOS', 'Apa bedanya Sell Thru dan Sell Out?']],
];
const AI_FOLLOW = [
    'perf_summary' => ['Brand mana yang paling ketinggalan dari target?', 'Fokus minggu ini apa?', 'Penjualan 7 hari terakhir'],
    'brand_type' => ['Siapa yang proyeksinya bahaya?', 'Dealer yang hilang bulan ini', 'Ranking sales'],
    'daily_sales' => ['Hari apa penjualan paling tinggi?', 'Ringkasan bulan ini', 'Top 10 dealer'],
    'dealer_insight' => ['Dealer yang hilang bulan ini', 'Fokus minggu ini apa?', 'Dealer yang stoknya numpuk'],
    'stock_insight' => ['Dealer mana yang stoknya menipis?', 'Dealer yang hilang bulan ini'],
    'ranking' => ['KPI tim siapa yang terendah?', 'Fokus minggu ini apa?'],
    'focus_plan' => ['Ranking sales', 'Penjualan 7 hari terakhir'],
    'kpi_team' => ['Ranking sales', 'Fokus minggu ini apa?'],
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
        ['name' => 'brand_type', 'description' => 'Per brand dan per type: kontribusi %, capaian & proyeksi per brand, DA/NOO/Sell Out per brand, brand terbaik/terlemah, jumlah brand on track, type teratas & paling sedikit per brand, Sell Out dan rasio SO/ST per type (type yang numpuk di dealer). Isi brand untuk detail satu brand: KPI ST/DA/NOO vs target dan daftar dealer NOO (dealer baru) brand itu. Pakai juga untuk pertanyaan NOO per brand.', 'parameters' => ['brand' => ['type' => 'string', 'description' => 'Nama brand (opsional)'], 'person' => $person, 'region' => $region]],
        ['name' => 'perf_summary', 'description' => 'Ringkasan lengkap periode ini: Sell Thru (aktual, target, %capai, gap, proyeksi, run rate per hari, butuh per hari untuk tembus, LMTD/growth, penjualan hari terakhir), Sell Out (aktual, growth, rasio SO/ST), Dealer Aktif (aktual, target, kurang, coverage, dealer belum order, rata-rata qty per dealer), NOO (aktual, target, kurang, proyeksi), omzet, hari kerja berjalan/total/sisa. Opsional per wilayah.', 'parameters' => ['person' => $person, 'region' => $region]],
        ['name' => 'brand_achievement', 'description' => 'Capaian Sell Thru per brand terhadap target, proyeksi dan pertumbuhan. Untuk pertanyaan brand mana yang tertinggal. Opsional per wilayah.', 'parameters' => ['person' => $person, 'region' => $region]],
        ['name' => 'early_warning', 'description' => 'Daftar orang/tim yang proyeksinya paling jauh dari target (Early Warning). level: SALES, ASM atau RGM.', 'parameters' => ['level' => ['type' => 'string', 'description' => 'SALES, ASM atau RGM. Kosongkan bila tidak disebut.'], 'brand' => $brand, 'person' => $person, 'region' => $region, 'limit' => $limit]],
        ['name' => 'dealer_not_order', 'description' => 'Dealer yang belum order (qty 0) pada periode ini, opsional per brand dan wilayah.', 'parameters' => ['brand' => $brand, 'person' => $person, 'region' => $region, 'limit' => $limit]],
        ['name' => 'dealer_priority', 'description' => 'Dealer prioritas kunjungan: sebelumnya order, sekarang diam atau lama tidak order. Opsional per wilayah.', 'parameters' => ['person' => $person, 'region' => $region, 'limit' => $limit]],
        ['name' => 'dealer_potential', 'description' => 'Dealer yang berpotensi menambah Dealer Aktif sebuah brand: aktif di brand lain tapi belum order brand tersebut.', 'parameters' => ['brand' => $brand, 'person' => $person, 'region' => $region, 'limit' => $limit]],
        ['name' => 'dos_risk', 'description' => 'Stok dealer: DOS menipis, habis, atau stok tinggi, beserta TYPE yang perlu restock.', 'parameters' => ['brand' => $brand, 'person' => $person, 'region' => $region, 'limit' => $limit, 'status' => ['type' => 'string', 'description' => 'MENIPIS, HABIS atau STOK TINGGI. Default MENIPIS.']]],
        ['name' => 'kpi_detail', 'description' => 'Rincian KPI satu orang per indikator: bobot, target, aktual, % capai, skor tertimbang, kurang, proyeksi, butuh per hari, indikator terlemah. Isi person untuk KPI sales/ASM/RGM lain dalam cakupan; kosong = KPI user sendiri.', 'parameters' => ['person' => $person]],
        ['name' => 'dealer_insight', 'description' => 'Analisis dealer. mode: lost (order bulan lalu tapi belum bulan ini), top (dealer terbesar), drop (paling turun vs LMTD), cross (dealer aktif yang belum order semua brand, isi brand untuk yang sudah order brand itu), concentration (porsi top 5/10/20 dealer), detail (satu dealer: isi dealer dengan nama/kode).', 'parameters' => ['mode' => ['type' => 'string', 'description' => 'lost|top|drop|cross|concentration|detail'], 'dealer' => ['type' => 'string', 'description' => 'Nama/kode dealer untuk mode detail'], 'brand' => $brand, 'person' => $person, 'region' => $region]],
        ['name' => 'ranking', 'description' => 'Ranking & perbandingan. mode: sales (ranking sales: target, aktual, proyeksi, DA, NOO), region (ranking region), compare (bandingin dua orang: isi person dan person2).', 'parameters' => ['mode' => ['type' => 'string', 'description' => 'sales|region|compare'], 'person' => $person, 'person2' => ['type' => 'string', 'description' => 'Nama orang kedua untuk compare'], 'region' => $region]],
        ['name' => 'day_pattern', 'description' => 'Pola penjualan per hari dalam seminggu (hari tertinggi/terendah) dan minggu ini vs minggu lalu.', 'parameters' => null],
        ['name' => 'focus_plan', 'description' => 'Saran fokus untuk tembus target: gabungan butuh per hari, DA/NOO yang kurang, brand tertinggal, KPI terlemah, dealer hilang. Opsional per wilayah/orang.', 'parameters' => ['person' => $person, 'region' => $region]],
        ['name' => 'daily_sales', 'description' => 'Penjualan per hari: N hari terakhir (default 7) atau satu tanggal (YYYY-MM-DD): Sell Thru, Sell Out, omzet, dealer order, NOO per hari. Isi person untuk per sales/tim, atau region untuk per wilayah.', 'parameters' => ['days' => ['type' => 'integer', 'description' => 'Jumlah hari terakhir, default 7'], 'date' => ['type' => 'string', 'description' => 'Tanggal YYYY-MM-DD (opsional)'], 'person' => $person, 'region' => $region]],
        ['name' => 'stock_insight', 'description' => 'Stok lanjutan. mode: type_habis (TYPE yang habis di banyak dealer), numpuk (stok tinggi / belum bergerak, terutama yang tidak ada penjualan 30 hari). Opsional brand, region.', 'parameters' => ['mode' => ['type' => 'string', 'description' => 'type_habis|numpuk'], 'brand' => $brand, 'person' => $person, 'region' => $region]],
        ['name' => 'da_noo_cumulative', 'description' => 'Perkembangan Dealer Aktif dan NOO kumulatif per tanggal (N tanggal terakhir): berapa DA/NOO sampai tanggal tertentu dan tambahan per hari. Opsional brand, person, region.', 'parameters' => ['days' => ['type' => 'integer', 'description' => 'Jumlah tanggal terakhir, default 10'], 'brand' => $brand, 'person' => $person, 'region' => $region]],
        ['name' => 'transactions', 'description' => 'Rincian transaksi pada satu tanggal dari data mentah: dealer, brand, TYPE, qty, omzet, nomor SJ (surat jalan). date=ALL untuk seluruh periode. Isi date (YYYY-MM-DD); kosong = tanggal terakhir yang ada data. Opsional dealer (nama/kode), brand, person, region.', 'parameters' => ['date' => ['type' => 'string', 'description' => 'Tanggal YYYY-MM-DD (opsional)'], 'dealer' => ['type' => 'string', 'description' => 'Nama/kode dealer (opsional)'], 'by' => ['type' => 'string', 'description' => 'sales atau region: pecah hasil per semua sales / per semua region'], 'brand' => $brand, 'person' => $person, 'region' => $region]],
        ['name' => 'kpi_team', 'description' => 'KPI semua orang dalam cakupan (tim): skor per orang dan per grup, rata-rata, yang di bawah 70%, diurutkan terendah. Opsional per wilayah. Untuk ASM/RGM/Admin.', 'parameters' => ['region' => $region]],
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
/* Nama tidak lengkap (2026-11-25): cari semua orang di cakupan yang namanya memuat kata di pertanyaan. */
function aiPersonCands_(string $q, string $token, array $skip = []): array {
    $Q = mb_strtoupper($q); $stop = array_flip(array_merge(['YANG', 'BERAPA', 'BULAN', 'INI', 'TYPE', 'TIPE', 'BRAND', 'DEALER', 'AKTIF', 'TARGET', 'RINGKASAN', 'SALES', 'PUNYA', 'DONG', 'BRO', 'MANA', 'LAKU', 'HARI', 'RATE', 'PROYEKSI', 'CAPAIAN', 'TIM', 'ASM', 'RGM', 'SELL', 'THRU', 'OUT', 'NOO', 'OMZET', 'OMSET'], array_map('mb_strtoupper', $skip)));
    $qt = array_filter(preg_split('/[^A-Z0-9]+/u', $Q), fn($t) => mb_strlen($t) >= 3 && !isset($stop[$t]));
    if (!$qt) return []; $out = []; $max = 0;
    foreach (aiPeople_($token) as $p) {
        $n = mb_strtoupper((string) ($p['nama'] ?? '')); if ($n === '' || strpos($n, 'VACANT') === 0 || !in_array($p['role'] ?? '', ['SALES', 'ASM', 'RGM'], true)) continue;
        $nt = array_flip(preg_split('/\s+/', $n)); $hit = []; foreach ($qt as $t) if (isset($nt[$t])) $hit[$t] = true;
        if (!$hit) { foreach ($qt as $t) foreach (array_keys($nt) as $w) if (aiFuzzyTok_($t, (string) $w) && mb_strtoupper($t) !== $w) { $hit[$t] = true; break; } }
        if (!$hit) continue; $c = count($hit); if ($c > $max) { $max = $c; $out = []; } if ($c === $max) $out[$p['nik']] = $p + ['_kata' => array_keys($hit)];
    }
    return array_values($out);
}
function aiPersonPick_(array $cands, string $q): array {
    $kata = $cands[0]['_kata']; $chips = [];
    foreach (array_slice($cands, 0, 8) as $c) { $qq = $q; foreach ($kata as $k) $qq = preg_replace('/(?<![\p{L}0-9])' . preg_quote($k, '/') . '(?![\p{L}0-9])/iu', '§', $qq, 1); $chips[] = trim(preg_replace('/§(\s*§)*/u', $c['nama'], $qq)); }
    return ['data' => ['pilihan' => array_map(fn($c) => $c['nama'], $cands)], 'summary' => 'Ada ' . count($cands) . ' nama "' . implode(' ', $kata) . '" di cakupan lu. Maksudnya yang mana? Ketuk salah satu di bawah.',
        'blocks' => [['t' => 'table', 'title' => 'Nama yang cocok', 'cols' => ['Nama', 'Jabatan', 'Region'], 'rows' => array_map(fn($c) => [$c['nama'], $c['role'], $c['region'] ?? '—'], array_slice($cands, 0, 12))], ['t' => 'chips', 'items' => $chips]]];
}
function aiRun_(string $name, array $args, array &$x): array {
    $rgA = trim((string) ($args['region'] ?? ''));
    if ($rgA !== '' && !in_array($name, ['glossary', 'kpi_detail'], true) && empty($args['person']) && !aiCust_($x, $rgA)) {
        $rc = aiRegionCands_($rgA, $x); $nm = $rc['names'];
        if (count($nm) === 1) $args['region'] = $nm[0]; elseif (count($nm) > 1) { $r = aiRegionPick_($nm, $rc['tok'], (string) ($x['q'] ?? $rgA)); $r['data']['need_choice'] = true; return $r; }
        else { $r = aiAskBack_($rgA, (string) ($x['q'] ?? $rgA), $x); $r['data']['need_choice'] = true; return $r; }
    }
    $pn = trim((string) ($args['person'] ?? ''));
    if ($pn !== '' && $name !== 'glossary' && $name !== 'kpi_team' && $name !== 'ranking' && $name !== 'day_pattern') {
        $cd = aiPersonCands_($pn, $x['token']); $ex = null; foreach ($cd as $c) if (mb_strtoupper($c['nama']) === mb_strtoupper($pn)) $ex = $c;
        if (!$ex && count($cd) > 1) { $r = aiPersonPick_($cd, (string) ($x['q'] ?? $pn)); $r['data']['need_choice'] = true; return $r; }
        $pp = $ex ?: ($cd[0] ?? null) ?: aiPersonFind_($pn, $x['token']);
        if (!$pp) return ['data' => ['error' => 'Nama ' . $pn . ' tidak ditemukan di cakupan akun ini.'], 'blocks' => [], 'summary' => 'Nama "' . $pn . '" tidak ditemukan di cakupan akun lu. Coba tulis nama persis seperti di daftar cakupan.'];
        if ($name === 'kpi_detail') { $r = aiKpi_($x, (string) $pp['nik']); $r['summary'] = '[' . $pp['role'] . ' ' . $pp['nama'] . '] ' . $r['summary']; return $r; }
        $y = aiCtx_($x['token'], $x['period'], (string) $pp['nik']); $args['person'] = '';
        if ($name === 'early_warning') $args['region'] = (string) $pp['nama'];
        $r = aiRun_($name, $args, $y); $lbl = $pp['role'] . ' ' . $pp['nama'];
        $r['summary'] = '[' . $lbl . '] ' . $r['summary']; if (isset($r['data']) && is_array($r['data'])) $r['data']['cakupan'] = $lbl;
        return $r;
    }
    switch ($name) {
        case 'perf_summary': return aiPerf_($x, (string) ($args['region'] ?? ''));
        case 'brand_type': return aiBrandType_($x, (string) ($args['brand'] ?? ''), (string) ($args['region'] ?? ''));
        case 'brand_achievement': return aiBrands_($x, (string) ($args['region'] ?? ''));
        case 'early_warning': return aiEarly_($x, strtoupper((string) ($args['level'] ?? '')), (string) ($args['region'] ?? ''), aiLim_($args['limit'] ?? 0, 8));
        case 'dealer_not_order': return aiNotOrder_($x, (string) ($args['brand'] ?? ''), (string) ($args['region'] ?? ''), aiLim_($args['limit'] ?? 0));
        case 'dealer_priority': return aiPriority_($x, (string) ($args['region'] ?? ''), aiLim_($args['limit'] ?? 0));
        case 'dealer_potential': return aiPotential_($x, (string) ($args['brand'] ?? ''), (string) ($args['region'] ?? ''), aiLim_($args['limit'] ?? 0));
        case 'dos_risk': return aiDos_($x, (string) ($args['brand'] ?? ''), strtoupper((string) ($args['status'] ?? 'MENIPIS')), (string) ($args['region'] ?? ''), aiLim_($args['limit'] ?? 0, 8));
        case 'kpi_detail': return aiKpi_($x);
        case 'dealer_insight': return aiDealer_($x, (string) ($args['mode'] ?? 'lost'), (string) ($args['brand'] ?? ''), (string) ($args['region'] ?? ''), (string) ($args['dealer'] ?? ''), aiLim_($args['limit'] ?? 0));
        case 'ranking': return aiRank_($x, (string) ($args['mode'] ?? 'sales'), (string) ($args['region'] ?? ''), (string) ($args['person'] ?? ''), (string) ($args['person2'] ?? ''));
        case 'day_pattern': return aiDayPattern_($x);
        case 'focus_plan': return aiFocus_($x, (string) ($args['region'] ?? ''));
        case 'daily_sales': return aiDaily_($x, (int) ($args['days'] ?? 7) ?: 7, (string) ($args['date'] ?? ''), (string) ($args['region'] ?? ''));
        case 'stock_insight': return aiStock_($x, (string) ($args['mode'] ?? 'numpuk'), (string) ($args['brand'] ?? ''), (string) ($args['region'] ?? ''), aiLim_($args['limit'] ?? 0));
        case 'da_noo_cumulative': return aiCum_($x, (string) ($args['brand'] ?? ''), (string) ($args['region'] ?? ''), (int) ($args['days'] ?? 10) ?: 10);
        case 'transactions': return aiTrans_($x, (string) ($args['date'] ?? ''), (string) ($args['region'] ?? ''), (string) ($args['brand'] ?? ''), (string) ($args['dealer'] ?? ''), ((int) ($args['limit'] ?? 0)) > 0 ? min(300, (int) $args['limit']) : 40, (string) ($args['by'] ?? ''));
        case 'kpi_team': return aiKpiTeam_($x, (string) ($args['region'] ?? ''));
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
/* Ringkasan lengkap (2026-11-24): ST, SO, DA, NOO, omzet, run rate, target harian, waktu. Semua dari apiDashboard + hari kerja Early Warning. */
function aiPerf_(array &$x, string $region = ''): array {
    $d = aiDash_($x); $ew = aiEw_($x); $run = (float) ($ew['run'] ?? 0); $days = (float) ($ew['days'] ?? 0); $sisa = max(0, $days - $run);
    $tv = fn($t) => !empty($t['exists']) ? (float) $t['value'] : null;
    if ($region === '') {
        $t = $d['total']; $p = $d['previous']; $bt = $d['brandTotals'] ?? [];
        $tST = $tv($bt['target'] ?? null); $tDA = $tv($bt['targetDA'] ?? null); $tNOO = $tv($bt['targetNOO'] ?? null);
    } else {
        $t = ['qty' => 0, 'da' => 0, 'noo' => 0, 'omzet' => 0, 'sellout' => 0]; $p = null; $tST = 0.0; $tDA = 0.0; $tNOO = null;
        foreach (aiDash_($x)['regionalBrands'] ?? [] as $rb) if (stripos((string) $rb['region'], $region) !== false) {
            foreach (['qty', 'da', 'noo', 'omzet', 'sellout'] as $k) $t[$k] += (float) ($rb['total'][$k] ?? 0);
            $tST += (float) ($tv($rb['target'] ?? null) ?? 0); $tDA += (float) ($tv($rb['targetDA'] ?? null) ?? 0);
        }
        if ($tST <= 0) $tST = null; if ($tDA <= 0) $tDA = null;
        $p = ['qty' => 0, 'da' => 0, 'noo' => 0, 'omzet' => 0, 'sellout' => 0]; foreach (aiCust_($x, $region) as $c) foreach ($p as $k => $_) $p[$k] += (float) ($c['previous'][$k] ?? 0);
    }
    $pc = fn($a, $b) => ($b && $b > 0) ? $a / $b * 100 : null; $gr = fn($a, $b) => ($b !== null && $b > 0) ? ($a / $b - 1) * 100 : null;
    $qty = (float) $t['qty']; $rr = $run > 0 ? $qty / $run : null; $proj = aiProj_($qty, $run, $days);
    $gap = $tST !== null ? max(0, $tST - $qty) : null; $need = ($tST !== null && $sisa > 0) ? max(0, $tST - $qty) / $sisa : null;
    $nooProj = aiProj_((float) $t['noo'], $run, $days);
    $cust = aiCust_($x, $region); $nDealer = count($cust);
    $nBelum = 0; foreach ($cust as $c) if (!in_array($c['status'] ?? '', ['AKTIF', 'BELUM MINIMUM DA'], true)) $nBelum++;
    $daily = $d['daily'] ?? []; $last = $daily ? end($daily) : null;
    $so = (float) ($t['sellout'] ?? 0);
    $data = [
        'catatan' => 'Hari kerja berjalan/total dari Early Warning. Proyeksi = aktual / hari kerja berjalan x hari kerja total. Growth dibanding bulan lalu pada hari yang sama (LMTD).',
        'cakupan_wilayah' => $region ?: 'semua',
        'data_sampai' => $d['coverage']['cutoff'] ?? null, 'hari_kerja_berjalan' => $run ?: null, 'hari_kerja_total' => $days ?: null, 'sisa_hari_kerja' => $days ? $sisa : null,
        'sell_thru' => ['aktual' => $qty, 'target' => $tST, 'capai_persen' => $pc($qty, $tST), 'gap_ke_target' => $gap, 'proyeksi_akhir_bulan' => $proj, 'proyeksi_persen_target' => $pc($proj ?? 0, $tST),
            'run_rate_per_hari' => $rr, 'butuh_per_hari_untuk_tembus' => $need, 'selisih_run_rate_vs_butuh' => ($rr !== null && $need !== null) ? $rr - $need : null,
            'lmtd' => $p['qty'] ?? null, 'growth_vs_lmtd_persen' => $p ? $gr($qty, (float) $p['qty']) : null,
            'hari_terakhir' => $last ? ['tanggal' => $last['date'], 'qty' => $last['qty'], 'vs_run_rate_persen' => $rr ? $pc($last['qty'], $rr) : null] : null],
        'sell_out' => ['aktual' => $so, 'lmtd' => $p['sellout'] ?? null, 'growth_vs_lmtd_persen' => $p && isset($p['sellout']) ? $gr($so, (float) $p['sellout']) : null, 'rasio_so_per_st_persen' => $pc($so, $qty)],
        'dealer_aktif' => ['aktual' => (float) $t['da'], 'target' => $tDA, 'capai_persen' => $pc((float) $t['da'], $tDA), 'kurang' => $tDA !== null ? max(0, $tDA - $t['da']) : null, 'lmtd' => $p['da'] ?? null, 'growth_vs_lmtd_persen' => $p ? $gr((float) $t['da'], (float) ($p['da'] ?? 0)) : null,
            'total_dealer_cakupan' => $nDealer ?: null, 'coverage_persen' => $nDealer ? $pc((float) $t['da'], $nDealer) : null, 'dealer_belum_order' => $nDealer ? $nBelum : null, 'rata2_qty_per_dealer_aktif' => $t['da'] ? $qty / $t['da'] : null],
        'noo' => ['aktual' => (float) $t['noo'], 'target' => $tNOO, 'capai_persen' => $pc((float) $t['noo'], $tNOO), 'kurang' => $tNOO !== null ? max(0, $tNOO - $t['noo']) : null, 'proyeksi_akhir_bulan' => $nooProj, 'lmtd' => $p['noo'] ?? null],
        'omzet' => ['aktual' => (float) ($t['omzet'] ?? 0), 'lmtd' => $p['omzet'] ?? null, 'growth_vs_lmtd_persen' => $p ? $gr((float) ($t['omzet'] ?? 0), (float) ($p['omzet'] ?? 0)) : null],
    ];
    $f1 = fn($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '.');
    $blocks = [['t' => 'kpi', 'items' => [
        ['l' => 'Sell Thru', 'v' => aiNum_($qty), 's' => $tST ? aiPct_($pc($qty, $tST)) . ' dari ' . aiNum_($tST) : 'target belum lengkap', 'c' => '#1E5FB8'],
        ['l' => 'Proyeksi', 'v' => $proj === null ? '—' : aiNum_($proj), 's' => $tST ? aiPct_($pc($proj ?? 0, $tST)) . ' dari target' : 'akhir bulan', 'c' => aiCol_($pc($proj ?? 0, $tST))],
        ['l' => 'Run rate / hari', 'v' => $f1($rr), 's' => $need === null ? 'butuh/hari —' : 'butuh ' . $f1($need) . '/hari', 'c' => ($rr !== null && $need !== null && $rr < $need) ? '#E5354E' : '#0E9F6E'],
        ['l' => 'Dealer Aktif', 'v' => aiNum_($t['da']), 's' => $tDA ? aiPct_($pc((float) $t['da'], $tDA)) . ' · kurang ' . aiNum_(max(0, $tDA - $t['da'])) : 'target belum ada', 'c' => '#0E7C6B'],
        ['l' => 'NOO', 'v' => aiNum_($t['noo']), 's' => $tNOO ? aiPct_($pc((float) $t['noo'], $tNOO)) . ' dari ' . aiNum_($tNOO) : 'proyeksi ' . ($nooProj === null ? '—' : aiNum_($nooProj)), 'c' => '#6D3FD8'],
        ['l' => 'Sell Out', 'v' => aiNum_($so), 's' => 'SO/ST ' . aiPct_($pc($so, $qty)), 'c' => '#C25A12'],
    ]]];
    $rows = [['Sell Thru', $tST ? aiNum_($tST) : '—', aiNum_($qty), aiPct_($pc($qty, $tST)), $p ? aiPct_($gr($qty, (float) $p['qty'])) : '—'], ['Sell Out', '—', aiNum_($so), '—', $p && isset($p['sellout']) ? aiPct_($gr($so, (float) $p['sellout'])) : '—'],
        ['Dealer Aktif', $tDA ? aiNum_($tDA) : '—', aiNum_($t['da']), aiPct_($pc((float) $t['da'], $tDA)), $p ? aiPct_($gr((float) $t['da'], (float) ($p['da'] ?? 0))) : '—'], ['NOO', $tNOO ? aiNum_($tNOO) : '—', aiNum_($t['noo']), aiPct_($pc((float) $t['noo'], $tNOO)), '—'],
        ['Omzet (Rp)', '—', aiNum_($t['omzet'] ?? 0), '—', $p ? aiPct_($gr((float) ($t['omzet'] ?? 0), (float) ($p['omzet'] ?? 0))) : '—']];
    $blocks[] = ['t' => 'table', 'title' => 'Ringkasan indikator' . ($region ? ' · ' . $region : '') . ' · hari kerja ' . aiNum_($run) . '/' . aiNum_($days) . ' (sisa ' . aiNum_($sisa) . ')', 'cols' => ['Indikator', 'Target', 'Aktual', '% capai', 'Growth LMTD'], 'rows' => $rows];
    $sum = 'Sell Thru ' . aiNum_($qty) . ($tST ? ' (' . aiPct_($pc($qty, $tST)) . ' dari target ' . aiNum_($tST) . '), proyeksi ' . aiNum_($proj ?? 0) . ' atau ' . aiPct_($pc($proj ?? 0, $tST)) . '.' : ', target belum lengkap.')
        . ($rr !== null ? ' Run rate ' . $f1($rr) . '/hari' . ($need !== null ? ', butuh ' . $f1($need) . '/hari di sisa ' . aiNum_($sisa) . ' hari kerja.' : '.') : '')
        . ' DA ' . aiNum_($t['da']) . ($tDA ? ' (' . aiPct_($pc((float) $t['da'], $tDA)) . ')' : '') . ', NOO ' . aiNum_($t['noo']) . ', Sell Out ' . aiNum_($so) . '.';
    return ['data' => $data, 'blocks' => $blocks, 'summary' => $sum];
}
/* Brand & type (2026-11-24): kontribusi, capaian & proyeksi per brand, brand terbaik/terlemah, on track, type teratas/terlambat per brand, SO per type. */
function aiBrandType_(array &$x, string $brand = '', string $region = ''): array {
    $d = aiDash_($x);
    if ($region !== '') { /* per wilayah: aktual & LMTD dari dealer di wilayah itu, target ST/DA dari regionalBrands; target NOO & type tidak tersedia per wilayah */
        $cu = aiCust_($x, $region); $R = []; $mk = ['qty' => 0, 'da' => 0, 'noo' => 0, 'sellout' => 0, 'omzet' => 0];
        foreach ($cu as $c) { foreach ($c['brands'] ?? [] as $bb) { $k = $bb['brand']; $R[$k] ??= ['brand' => $k, 'current' => $mk, 'previous' => $mk, 'target' => null, 'targetDA' => null, 'targetNOO' => null]; foreach ($mk as $f => $_) $R[$k]['current'][$f] += (float) ($bb[$f] ?? 0); } }
        foreach (aiRegBrands_($x, $region) as $rb) { $k = $rb['brand']; if (isset($R[$k]) && !empty($rb['target']['exists'])) $R[$k]['target'] = $rb['target']; }
        foreach ($d['regionalBrands'] ?? [] as $rb) if (stripos((string) $rb['region'], $region) !== false && isset($R[$rb['brand']]) && !empty($rb['targetDA']['exists'])) { $t = &$R[$rb['brand']]['targetDA']; $t = ['exists' => true, 'value' => ($t['value'] ?? 0) + (float) $rb['targetDA']['value']]; unset($t); }
        $d['brands'] = array_values(array_filter($R, fn($r) => $r['current']['qty'] > 0 || $r['target'])); $d['customers'] = $cu; $d['types'] = [];
        $d['total'] = ['qty' => array_sum(array_map(fn($r) => $r['current']['qty'], $d['brands']))];
    } $ew = aiEw_($x); $run = (float) ($ew['run'] ?? 0); $days = (float) ($ew['days'] ?? 0);
    $tv = fn($t) => !empty($t['exists']) ? (float) $t['value'] : null; $pc = fn($a, $b) => ($b && $b > 0) ? $a / $b * 100 : null;
    $tot = max(1, (float) ($d['total']['qty'] ?? 0)); $B = [];
    foreach ($d['brands'] ?? [] as $b) {
        $c = $b['current']; $q = (float) $c['qty']; $tg = $tv($b['target'] ?? null); $pr = aiProj_($q, $run, $days); $pq = (float) ($b['previous']['qty'] ?? 0);
        $B[] = ['brand' => $b['brand'], 'qty' => $q, 'kontribusi_persen' => $q / $tot * 100, 'target' => $tg, 'capai_persen' => $pc($q, $tg), 'proyeksi' => $pr, 'proyeksi_persen' => $pc($pr ?? 0, $tg), 'growth_vs_lmtd_persen' => $pq > 0 ? ($q / $pq - 1) * 100 : null,
            'dealer_aktif' => (float) $c['da'], 'target_da' => $tv($b['targetDA'] ?? null), 'noo' => (float) $c['noo'], 'target_noo' => $tv($b['targetNOO'] ?? null), 'sell_out' => (float) ($c['sellout'] ?? 0),
            'omzet' => (float) ($c['omzet'] ?? 0), 'growth_omzet_persen' => (float) ($b['previous']['omzet'] ?? 0) > 0 ? ($c['omzet'] / $b['previous']['omzet'] - 1) * 100 : null, 'growth_da_persen' => (float) ($b['previous']['da'] ?? 0) > 0 ? ($c['da'] / $b['previous']['da'] - 1) * 100 : null,
            'run_rate_per_hari' => $run > 0 ? $q / $run : null, 'butuh_per_hari' => ($tg && $days > $run) ? max(0, $tg - $q) / ($days - $run) : null, 'kurang_ke_target' => $tg ? max(0, $tg - $q) : null];
    }
    usort($B, fn($a, $b) => $b['qty'] <=> $a['qty']);
    $ranked = array_values(array_filter($B, fn($r) => $r['proyeksi_persen'] !== null)); usort($ranked, fn($a, $b) => $b['proyeksi_persen'] <=> $a['proyeksi_persen']);
    $onTrack = count(array_filter($ranked, fn($r) => $r['proyeksi_persen'] >= 100));
    $types = $d['types'] ?? []; $TB = [];
    foreach ($types as $t) { if ($brand !== '' && strcasecmp($t['brand'], $brand) !== 0) continue; $TB[$t['brand']][] = ['type' => $t['type'], 'qty' => (float) $t['qty'], 'sell_out' => isset($t['sellout']) ? (float) $t['sellout'] : null]; }
    $tData = [];
    foreach ($TB as $bn => $L) {
        usort($L, fn($a, $b) => $b['qty'] <=> $a['qty']); $bq = max(1, array_sum(array_column($L, 'qty')));
        foreach ($L as &$r) { $r['kontribusi_persen'] = $r['qty'] / $bq * 100; $r['rasio_so_st_persen'] = $r['sell_out'] !== null && $r['qty'] > 0 ? $r['sell_out'] / $r['qty'] * 100 : null; } unset($r);
        $tData[$bn] = ['jumlah_type_terjual' => count($L), 'teratas' => array_slice($L, 0, $brand ? 10 : 3), 'paling_sedikit' => array_slice(array_reverse($L), 0, $brand ? 5 : 2),
            'numpuk_di_dealer' => array_slice(array_values(array_filter($L, fn($r) => $r['rasio_so_st_persen'] !== null && $r['qty'] >= 5 && $r['rasio_so_st_persen'] < 50)), 0, 5)];
    }
    $data = ['wilayah' => $region ?: 'semua', 'catatan' => ($region ? 'Per wilayah: target NOO dan rincian type tidak tersedia, hanya total. ' : '') . 'Type hanya yang terjual (qty>0) periode ini. Rasio SO/ST < 50% dengan qty >= 5 = kemungkinan numpuk di dealer. Growth per type belum tersedia.',
        'brand' => $brand ? array_values(array_filter($B, fn($r) => strcasecmp($r['brand'], $brand) === 0)) : $B,
        'brand_terbaik' => $ranked[0]['brand'] ?? null, 'brand_terlemah' => $ranked ? end($ranked)['brand'] : null, 'brand_on_track' => $onTrack, 'brand_dengan_target' => count($ranked), 'type_per_brand' => $tData];
    $blocks = [];
    $f1 = fn($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '.');
    if (!$brand) {
        $blocks[] = ['t' => 'table', 'title' => 'Sell Thru per brand' . ($region ? ' · ' . $region : '') . ' · ' . $onTrack . ' dari ' . count($ranked) . ' brand on track', 'cols' => ['Brand', 'Target', 'Aktual', '% capai', 'Proyeksi', 'Growth', 'Run rate/hr', 'Butuh/hr', 'Kontribusi'],
            'rows' => array_map(fn($r) => [$r['brand'], $r['target'] === null ? '—' : aiNum_($r['target']), aiNum_($r['qty']), aiPct_($r['capai_persen']), aiPct_($r['proyeksi_persen']), aiPct_($r['growth_vs_lmtd_persen']), $f1($r['run_rate_per_hari']), $f1($r['butuh_per_hari']), aiPct_($r['kontribusi_persen'])], $B)];
        $blocks[] = ['t' => 'table', 'title' => 'DA, NOO, Sell Out & omzet per brand' . ($region ? ' · ' . $region : ''), 'cols' => ['Brand', 'Target DA', 'DA', 'Target NOO', 'NOO', 'Sell Out', 'Omzet (Rp)', 'Growth omzet'],
            'rows' => array_map(fn($r) => [$r['brand'], $r['target_da'] === null ? '—' : aiNum_($r['target_da']), aiNum_($r['dealer_aktif']), $r['target_noo'] === null ? '—' : aiNum_($r['target_noo']), aiNum_($r['noo']), aiNum_($r['sell_out']), aiNum_($r['omzet']), aiPct_($r['growth_omzet_persen'])], $B)];
    }
    foreach ($tData as $bn => $td) $blocks[] = ['t' => 'table', 'title' => 'Type ' . $bn . ' · ' . $td['jumlah_type_terjual'] . ' type terjual', 'cols' => ['Type', 'Qty', 'Kontribusi', 'Sell Out', 'SO/ST'],
        'rows' => array_map(fn($r) => [$r['type'], aiNum_($r['qty']), aiPct_($r['kontribusi_persen']), $r['sell_out'] === null ? '—' : aiNum_($r['sell_out']), aiPct_($r['rasio_so_st_persen'])], $td['teratas'])];
    $one = $brand ? ($data['brand'][0] ?? null) : null; $nooList = [];
    if ($one) {
        $nm = []; foreach (aiPeople_($x['token']) as $pp) $nm[(string) $pp['nik']] = $pp['nama'];
        foreach ($d['customers'] ?? [] as $c) foreach ($c['brands'] ?? [] as $bb) if (strcasecmp($bb['brand'], $one['brand']) === 0 && (float) ($bb['noo'] ?? 0) > 0) $nooList[] = [$c['id'], $c['nama'], $c['region'] ?? '', $nm[(string) ($c['nik'] ?? '')] ?? '—', aiNum_($bb['qty'] ?? 0)];
        $data['dealer_noo'] = array_map(fn($r) => ['kode' => $r[0], 'nama' => $r[1], 'region' => $r[2], 'sales' => $r[3], 'qty' => $r[4]], $nooList);
        array_unshift($blocks, ['t' => 'kpi', 'items' => [
            ['l' => 'Sell Thru', 'v' => aiNum_($one['qty']), 's' => $one['target'] ? aiPct_($one['capai_persen']) . ' dari ' . aiNum_($one['target']) : 'kontribusi ' . aiPct_($one['kontribusi_persen']), 'c' => '#1E5FB8'],
            ['l' => 'Dealer Aktif', 'v' => aiNum_($one['dealer_aktif']), 's' => $one['target_da'] ? aiPct_($one['dealer_aktif'] / $one['target_da'] * 100) . ' dari ' . aiNum_($one['target_da']) : 'target belum ada', 'c' => '#0E7C6B'],
            ['l' => 'NOO', 'v' => aiNum_($one['noo']), 's' => $one['target_noo'] ? aiPct_($one['noo'] / $one['target_noo'] * 100) . ' dari ' . aiNum_($one['target_noo']) . ' · kurang ' . aiNum_(max(0, $one['target_noo'] - $one['noo'])) : 'target belum ada', 'c' => '#6D3FD8'],
            ['l' => 'Sell Out', 'v' => aiNum_($one['sell_out']), 's' => 'growth ST ' . aiPct_($one['growth_vs_lmtd_persen']), 'c' => '#C25A12'],
            ['l' => 'Run rate/hari', 'v' => $f1($one['run_rate_per_hari']), 's' => $one['butuh_per_hari'] === null ? 'butuh/hari —' : 'butuh ' . $f1($one['butuh_per_hari']) . '/hari', 'c' => '#1E5FB8'],
            ['l' => 'Omzet', 'v' => aiNum_($one['omzet']), 's' => 'growth ' . aiPct_($one['growth_omzet_persen']), 'c' => '#9A6400']]]);
        $blocks[] = ['t' => 'table', 'title' => 'Dealer NOO ' . $one['brand'] . ($region ? ' · ' . $region : '') . ' · ' . count($nooList) . ' dealer', 'cols' => ['Kode', 'Nama Dealer', 'Region', 'Sales', 'Qty'], 'rows' => array_slice($nooList, 0, 30)];
    }
    $blocks = array_slice($blocks, 0, 4);
    $sum = $brand ? ($one ? $brand . ': qty ' . aiNum_($one['qty']) . ' (kontribusi ' . aiPct_($one['kontribusi_persen']) . '), proyeksi ' . aiPct_($one['proyeksi_persen']) . ' dari target. NOO ' . aiNum_($one['noo']) . ($one['target_noo'] ? ' dari target ' . aiNum_($one['target_noo']) : '') . ' (' . count($nooList) . ' dealer baru).' . ' Type teratas: ' . implode(', ', array_map(fn($r) => $r['type'] . ' ' . aiNum_($r['qty']), array_slice($tData[$one['brand']]['teratas'] ?? [], 0, 3))) . '.' : 'Brand ' . $brand . ' tidak ada di cakupan ini.')
        : count($B) . ' brand. Terbaik ' . ($data['brand_terbaik'] ?? '—') . ', terlemah ' . ($data['brand_terlemah'] ?? '—') . '. ' . $onTrack . ' dari ' . count($ranked) . ' brand on track.';
    return ['data' => $data, 'blocks' => $blocks, 'summary' => $sum];
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
/* Periode lain (2026-11-25): "bulan lalu", "oktober", "periode 2026-10" memindahkan seluruh jawaban ke periode itu. Kata pembanding (dibanding/vs/growth) tidak memindahkan periode. */
const AI_MONTHS = ['januari' => 1, 'jan' => 1, 'februari' => 2, 'feb' => 2, 'maret' => 3, 'mar' => 3, 'april' => 4, 'apr' => 4, 'mei' => 5, 'juni' => 6, 'jun' => 6, 'juli' => 7, 'jul' => 7, 'agustus' => 8, 'agu' => 8, 'agt' => 8, 'september' => 9, 'sept' => 9, 'sep' => 9, 'oktober' => 10, 'okt' => 10, 'november' => 11, 'nov' => 11, 'desember' => 12, 'des' => 12];
function aiPeriodPhrase_(string $q): string {
    $l = mb_strtolower($q);
    if (preg_match('/dibanding|banding|\bvs\b|versus|lmtd|growth|pertumbuhan/u', $l)) return '';
    if (preg_match('/periode\s+(20\d\d-\d\d)/u', $l, $m)) return $m[0];
    if (preg_match('/bulan\s+(lalu|kemarin|sebelumnya)/u', $l, $m)) return $m[0];
    foreach (array_keys(AI_MONTHS) as $mn) if (preg_match('/(?<![\p{L}0-9])(bulan\s+)?' . $mn . '(\s+20\d\d)?(?![\p{L}0-9])/u', $l, $m)) return $m[0];
    return '';
}
function aiPeriodIn_(string $q, string $current, array $avail): array {
    $ph = aiPeriodPhrase_($q); if ($ph === '') return ['p' => '', 'phrase' => '', 'missing' => ''];
    if (preg_match('/(20\d\d-\d\d)/', $ph, $m)) $t = $m[1];
    elseif (preg_match('/lalu|kemarin|sebelumnya/', $ph)) $t = date('Y-m', strtotime(substr($current, 0, 7) . '-01 -1 month'));
    else { $mon = 0; foreach (AI_MONTHS as $mn => $n) if (preg_match('/(?<![\p{L}0-9])' . $mn . '(?![\p{L}0-9])/u', $ph)) { $mon = $n; break; }
        $cy = (int) substr($current, 0, 4); $cm = (int) substr($current, 5, 2); $yr = preg_match('/(20\d\d)/', $ph, $m) ? (int) $m[1] : ($mon > $cm ? $cy - 1 : $cy); $t = sprintf('%04d-%02d', $yr, $mon); }
    if ($t === substr($current, 0, 7)) return ['p' => '', 'phrase' => $ph, 'missing' => ''];
    foreach ($avail as $a) if (substr((string) $a, 0, 7) === $t) return ['p' => (string) $a, 'phrase' => $ph, 'missing' => ''];
    return ['p' => '', 'phrase' => $ph, 'missing' => $t];
}
/* Percakapan nyambung (2026-11-25): pertanyaan pendek/terpotong ("kalau cirebon?", "itel?", "bulan lalu?") digabung dengan pertanyaan user sebelumnya. */
function aiFollowUp_(string $q, $context, array $brands, array &$x): string {
    if (!is_array($context)) return $q; $prev = '';
    foreach (array_reverse($context) as $m) if (($m['role'] ?? '') === 'user' && trim((string) ($m['text'] ?? '')) !== '' && trim((string) $m['text']) !== trim($q)) { $prev = trim((string) $m['text']); break; }
    if ($prev === '') return $q;
    $lead = (bool) preg_match('/^\s*(kalau|kalo|terus|lalu|dan|bagaimana|gimana|bandingin|coba|untuk|kalau di|di)\b/iu', $q); $words = count(preg_split('/\s+/', trim($q)));
    [$fn] = aiRoute_($q, $brands); if (($words > 7 && !$lead) || ($fn !== '' && !$lead)) return $q;
    $new = $prev; $found = false;
    foreach ($brands as $b) if (stripos($q, $b) !== false) { $rep = false; foreach ($brands as $pb) if (stripos($new, $pb) !== false) { $new = preg_replace('/' . preg_quote($pb, '/') . '/i', $b, $new, 1); $rep = true; break; } if (!$rep) $new .= ' ' . $b; $found = true; break; }
    $rg = aiRegionIn_($q, $x); if ($rg === '') { $rc = aiRegionCands_($q, $x, $brands); if (count($rc['names']) === 1) $rg = $rc['names'][0]; }
    if ($rg !== '') { $pr = aiRegionIn_($prev, $x); $new = $pr !== '' ? str_ireplace($pr, $rg, $new) : $new . ' ' . $rg; $found = true; }
    $pe = aiPersonFind_($q, $x['token'], true);
    if ($pe) { $pp = aiPersonFind_($prev, $x['token'], true); $new = $pp ? str_ireplace((string) $pp['nama'], (string) $pe['nama'], $new) : $new . ' ' . $pe['nama']; $found = true; }
    if (preg_match('/tanggal\s*\d{1,2}|tgl\s*\d{1,2}|kemarin|hari ini/iu', $q, $dm)) { $new = preg_replace('/tanggal\s*\d{1,2}|tgl\s*\d{1,2}|kemarin|hari ini/iu', '', $new); $new .= ' ' . $dm[0]; $found = true; }
    $ph = aiPeriodPhrase_($q); if ($ph !== '') { $old = aiPeriodPhrase_($new); if ($old !== '') $new = str_ireplace($old, '', $new); $new .= ' ' . $ph; $found = true; }
    return $found ? trim(preg_replace('/\s+/', ' ', $new)) : $q;
}
/* Nama wilayah disingkat / typo (2026-11-25): "jabo" -> JABODETABEK, "jabodetabk" -> JABODETABEK. 1 cocok = langsung dipakai, >1 = tampil pilihan. */
function aiRegionNames_(array &$x): array { $n = []; foreach (aiDash_($x)['customers'] ?? [] as $c) foreach (['bigRegion', 'region', 'subRegion'] as $k) { $v = trim((string) ($c[$k] ?? '')); if ($v !== '') $n[$v] = 1; } return array_keys($n); }
function aiFuzzyTok_(string $t, string $word): bool {
    $t = mb_strtoupper($t); $word = mb_strtoupper($word); $lt = strlen($t); if ($lt < 3) return false;
    if ($lt >= 4 && strpos($word, $t) === 0) return true;
    if ($lt >= 5 && abs(strlen($word) - $lt) <= 2) { $d = levenshtein($t, $word); return $d <= ($lt >= 8 ? 2 : 1); }
    return false;
}
function aiStopWords_(): array { return ['YANG', 'BERAPA', 'BULAN', 'TYPE', 'TIPE', 'BRAND', 'DEALER', 'AKTIF', 'TARGET', 'RINGKASAN', 'SALES', 'PUNYA', 'DONG', 'MANA', 'LAKU', 'HARI', 'RATE', 'PROYEKSI', 'CAPAIAN', 'RANKING', 'PENJUALAN', 'TERAKHIR', 'REGION', 'WILAYAH', 'KPI', 'FOKUS', 'MINGGU', 'HILANG', 'TURUN', 'TERBESAR', 'HARIAN', 'KEMARIN', 'BANDINGIN', 'OMZET', 'OMSET', 'DENGAN', 'SELL', 'THRU', 'TIDAK', 'BELUM', 'ORDER', 'PALING', 'BAGUS', 'JELEK', 'SEMUA', 'TOTAL', 'BANYAK', 'GIMANA', 'KENAPA', 'APAKAH', 'DEALER', 'TEMBUS', 'SIAPA', 'APA', 'KAPAN', 'AJA', 'ITU', 'INI', 'SAYA', 'GUE', 'KITA', 'AKU', 'KAMI', 'PERIODE', 'TAHUN', 'LALU', 'DEPAN', 'TIM', 'ASM', 'RGM', 'NOO', 'DOS', 'STOK', 'KPI', 'BIAR', 'SUPAYA', 'BISA', 'MAU', 'TOLONG', 'LIHAT', 'TAMPILKAN', 'KASIH', 'DONG']; }
function aiRegionCands_(string $q, array &$x, array $skip = []): array {
    $stop = aiStopWords_();
    $skip = array_map('mb_strtoupper', $skip); $names = aiRegionNames_($x); $res = []; $tok = '';
    foreach (array_filter(preg_split('/[^A-Za-z0-9]+/u', $q), fn($t) => strlen($t) >= 3) as $t) {
        $T = mb_strtoupper($t); if (in_array($T, $stop, true) || in_array($T, $skip, true) || ctype_digit($T)) continue; $hit = [];
        foreach ($names as $n) foreach (preg_split('/[^A-Z0-9]+/u', mb_strtoupper($n)) as $w) if ($w !== '' && aiFuzzyTok_($T, $w)) { $hit[$n] = 1; break; }
        if ($hit && (!$res || count($hit) < count($res))) { $res = array_keys($hit); $tok = $t; }
    }
    return ['names' => $res, 'tok' => $tok];
}
function aiRegionPick_(array $names, string $tok, string $q): array {
    $names = array_slice($names, 0, 8); $chips = array_map(function ($n) use ($tok, $q) { $r = trim(preg_replace('/(?<![\p{L}0-9])' . preg_quote($tok, '/') . '(?![\p{L}0-9])/iu', $n, $q, 1)); return $r === trim($q) ? $q . ' ' . $n : $r; }, $names);
    return ['data' => ['pilihan' => $names], 'summary' => 'Ada ' . count($names) . ' wilayah yang mirip "' . $tok . '". Maksudnya yang mana? Ketuk salah satu di bawah.', 'blocks' => [['t' => 'chips', 'items' => $chips]]];
}
/* Tanya balik (2026-11-25): kata yang disebut sebagai wilayah/sales tapi tidak cocok apa pun -> jangan tampilkan data asal-asalan, minta user memilih. */
function aiAskBack_(string $tok, string $q, array &$x): array {
    $rep = function ($n) use ($tok, $q) { $r = trim(preg_replace('/(?<![\p{L}0-9])' . preg_quote($tok, '/') . '(?![\p{L}0-9])/iu', $n, $q, 1)); return $r === trim($q) ? $q . ' ' . $n : $r; };
    $T = mb_strtoupper($tok); $sc = [];
    foreach (aiPeople_($x['token']) as $p) { $n = mb_strtoupper((string) ($p['nama'] ?? '')); if ($n === '' || strpos($n, 'VACANT') === 0 || !in_array($p['role'] ?? '', ['SALES', 'ASM', 'RGM'], true)) continue;
        $b = 0; foreach (preg_split('/\s+/', $n) as $w) { similar_text($T, $w, $pc); $b = max($b, $pc); } $sc[$p['nama']] = $b; }
    arsort($sc); $pn = array_slice(array_keys($sc), 0, 4); $pchips = array_map($rep, $pn);
    $rs = []; foreach (aiRegionList_($x) as $rn) { $b = 0; foreach (preg_split('/\s+/', mb_strtoupper($rn)) as $w) { similar_text($T, $w, $pc); $b = max($b, $pc); } $rs[$rn] = $b; } arsort($rs); $reg = array_slice(array_keys($rs), 0, 4); $chips = array_merge(array_map($rep, $reg), $pchips); $reg = array_merge($reg, $pn); 
    return ['data' => ['tidak_dikenali' => $tok, 'saran' => $reg], 'summary' => 'Gue belum nemu wilayah atau nama sales "' . $tok . '" di cakupan lu, jadi gue gak mau nebak. Maksudnya yang mana? Pilih wilayah atau nama sales yang mirip di bawah, atau tulis ulang namanya.', 'blocks' => $chips ? [['t' => 'chips', 'items' => $chips]] : []];
}
function aiUnknownTok_(string $q, array $brands): string {
    if (!preg_match_all('/(?<![\p{L}0-9])(?:wilayah|region|area|daerah|kota|punya|milik|sales|asm|rgm|di|untuk)\s+([\p{L}]{3,})/iu', $q, $mm)) return '';
    $stop = aiStopWords_(); $b = array_map('mb_strtoupper', $brands);
    foreach ($mm[1] as $w) { $W = mb_strtoupper($w); if (!in_array($W, $stop, true) && !in_array($W, $b, true) && !preg_match('/^(top|per|semua|all|brand|type|dealer|hari|bulan|minggu|tahun|ini|itu|yang|dan|atau|sama)$/i', $w)) return $w; }
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
/* KPI rinci (2026-11-25): per orang dalam cakupan (apiPersonKpi sudah cek cakupan), plus KPI tim. */
function aiKpi_(array &$x, string $nik = ''): array {
    $u = guard_($x['token']); $nik = $nik ?: (string) $u['nik']; $k = apiPersonKpi($x['token'], $x['period'], $nik);
    if (empty($k['groups'])) return ['data' => ['status' => $k['status'] ?? 'KPI belum tersedia'], 'blocks' => [], 'summary' => 'KPI belum tersedia (' . ($k['status'] ?? 'belum ada kebijakan') . ').'];
    $ew = aiEw_($x); $run = (float) ($ew['run'] ?? 0); $days = (float) ($ew['days'] ?? 0); $sisa = max(0, $days - $run);
    $f1 = fn($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '.');
    $data = ['skor' => $k['score'] ?? null, 'status' => $k['status'] ?? null, 'hari_kerja_berjalan' => $run, 'hari_kerja_total' => $days, 'grup' => []]; $all = [];
    $blocks = [['t' => 'kpi', 'items' => [['l' => 'Skor KPI', 'v' => aiPct_($k['score'] ?? null), 's' => (string) ($k['status'] ?? ''), 'c' => aiCol_($k['score'] ?? null)]]]];
    foreach ($k['groups'] as $g) {
        $blocks[0]['items'][] = ['l' => $g['name'], 'v' => aiPct_($g['score'] ?? null), 's' => count($g['indicators'] ?? []) . ' indikator', 'c' => aiCol_($g['score'] ?? null)];
        $rows = []; $gi = [];
        foreach ($g['indicators'] ?? [] as $i) {
            $t = (float) ($i['target'] ?? 0); $a = (float) ($i['actual'] ?? 0); $iddle = ($i['metric'] ?? '') === 'IDDLE';
            $proj = $iddle ? null : aiProj_($a, $run, $days); $kurang = $iddle ? null : max(0, $t - $a); $need = (!$iddle && $sisa > 0 && $t > 0) ? max(0, $t - $a) / $sisa : null;
            $r = ['indikator' => $i['label'], 'metrik' => $i['metric'] ?? null, 'brand' => $i['brand'] ?? null, 'type' => $i['types'] ?? null, 'bobot' => $i['weight'], 'batas_capai' => $i['cap'] ?? null, 'target' => $t, 'aktual' => $a, 'capai_persen' => $i['ach'] ?? null, 'skor_tertimbang' => $i['score'] ?? null, 'kurang' => $kurang, 'proyeksi' => $proj, 'proyeksi_persen' => ($proj !== null && $t > 0) ? $proj / $t * 100 : null, 'butuh_per_hari' => $need, 'status' => $i['state'] ?? null];
            $gi[] = $r; $all[] = $r + ['grup' => $g['name']];
            $rows[] = [$i['label'], aiNum_($i['weight']) . '%', aiNum_($t), aiNum_($a), aiPct_($i['ach'] ?? null), $kurang === null ? '—' : aiNum_($kurang), $r['proyeksi_persen'] === null ? '—' : aiPct_($r['proyeksi_persen']), $need === null ? '—' : $f1($need)];
        }
        $blocks[] = ['t' => 'table', 'title' => $g['name'] . ' · skor ' . aiPct_($g['score'] ?? null), 'cols' => ['Indikator', 'Bobot', 'Target', 'Aktual', '% capai', 'Kurang', 'Proyeksi', 'Butuh/hari'], 'rows' => $rows];
        $data['grup'][] = ['nama' => $g['name'], 'skor' => $g['score'] ?? null, 'indikator' => $gi];
    }
    $low = array_values(array_filter($all, fn($r) => $r['capai_persen'] !== null && $r['capai_persen'] < 100)); usort($low, fn($a, $b) => $a['capai_persen'] <=> $b['capai_persen']);
    $data['indikator_terlemah'] = array_slice($low, 0, 3);
    $sum = 'Skor KPI ' . aiPct_($k['score'] ?? null) . ' (' . ($k['status'] ?? '') . ').' . ($low ? ' Paling perlu dikejar: ' . implode(', ', array_map(fn($r) => $r['indikator'] . ' ' . aiPct_($r['capai_persen']) . ($r['kurang'] ? ' (kurang ' . aiNum_($r['kurang']) . ')' : ''), array_slice($low, 0, 3))) . '.' : ' Semua indikator sudah 100%.');
    return ['data' => $data, 'blocks' => $blocks, 'summary' => $sum];
}
function aiKpiTeam_(array &$x, string $region = ''): array {
    try { $L = apiTeamKpi($x['token'], $x['period'], $x['sel']); } catch (Throwable $e) { return ['data' => ['error' => $e->getMessage()], 'blocks' => [], 'summary' => 'KPI tim tidak tersedia untuk jabatan lu (khusus ASM, RGM, Admin).']; }
    if ($region !== '') $L = array_values(array_filter($L, fn($r) => stripos((string) $r['region'], $region) !== false || stripos((string) $r['area'], $region) !== false));
    usort($L, fn($a, $b) => ($a['kpi']['score'] ?? 999) <=> ($b['kpi']['score'] ?? 999));
    $rows = []; $d = [];
    foreach ($L as $r) { $kp = $r['kpi'] ?? []; $gs = []; foreach ($kp['groups'] ?? [] as $g) $gs[] = $g['name'] . ' ' . aiPct_($g['score'] ?? null);
        $rows[] = [$r['nama'], $r['region'], aiPct_($kp['score'] ?? null), (string) ($kp['status'] ?? ''), implode(' · ', $gs)]; $d[] = ['nama' => $r['nama'], 'region' => $r['region'], 'skor' => $kp['score'] ?? null, 'status' => $kp['status'] ?? null, 'grup' => $gs]; }
    $sc = array_values(array_filter(array_column($d, 'skor'), fn($v) => $v !== null)); $avg = $sc ? array_sum($sc) / count($sc) : null; $low = count(array_filter($sc, fn($v) => $v < 70));
    return ['data' => ['wilayah' => $region ?: 'semua', 'jumlah' => count($d), 'rata2_skor' => $avg, 'skor_di_bawah_70' => $low, 'orang' => $d],
        'blocks' => [['t' => 'kpi', 'items' => [['l' => 'Jumlah', 'v' => aiNum_(count($d)), 's' => 'orang di cakupan', 'c' => '#1E5FB8'], ['l' => 'Rata-rata skor', 'v' => aiPct_($avg), 's' => 'KPI tim', 'c' => aiCol_($avg)], ['l' => 'Skor < 70%', 'v' => aiNum_($low), 's' => 'perlu perhatian', 'c' => '#E5354E']]],
            ['t' => 'table', 'title' => 'KPI per orang' . ($region ? ' · ' . $region : '') . ' (terendah dulu)', 'cols' => ['Nama', 'Region', 'Skor', 'Status', 'Per grup'], 'rows' => array_slice($rows, 0, 40)]],
        'summary' => count($d) . ' orang, rata-rata skor KPI ' . aiPct_($avg) . ', ' . $low . ' orang di bawah 70%.' . ($d ? ' Terendah: ' . implode(', ', array_map(fn($r) => $r['nama'] . ' ' . aiPct_($r['skor']), array_slice($d, 0, 3))) . '.' : '')];
}
/* ===== Paket 2026-11-25: dealer, ranking, pola hari, fokus ===== */
function aiSalesNm_(array &$x): array { static $m = null; if ($m === null) { $m = []; foreach (aiPeople_($x['token']) as $p) $m[(string) $p['nik']] = $p['nama']; } return $m; }
function aiDRow_(array &$x, array $c): array { $nm = aiSalesNm_($x); return [(string) $c['id'], (string) $c['nama'], (string) ($c['region'] ?? ''), ($c['sales'] ?? '') ?: ($nm[(string) ($c['nik'] ?? '')] ?? '—')]; }
function aiDealer_(array &$x, string $mode, string $brand = '', string $region = '', string $dealer = '', int $lim = 10): array {
    $cu = aiCust_($x, $region); $H = ['Kode', 'Nama Dealer', 'Region', 'Sales']; $rg = $region ? ' · ' . $region : '';
    $gp = fn($a, $b) => $b > 0 ? ($a / $b - 1) * 100 : null;
    if ($mode === 'detail') {
        $cd = aiDealerCands_($dealer, $x); $best = null;
        if (count($cd) === 1) $best = $cd[0]['c']; elseif (count($cd) > 1) { $r = aiDealerPick_($cd, $dealer); $r['data']['need_choice'] = true; return $r; }
        if (!$best) return ['data' => ['error' => 'dealer tidak ditemukan'], 'blocks' => [], 'summary' => 'Gue belum nemu dealer "' . $dealer . '" di cakupan lu, jadi gue gak mau nebak. Tulis ulang nama atau kode dealernya ya.'];
        $c = $best; $rows = []; foreach ($c['brands'] ?? [] as $bb) if ((float) $bb['qty'] > 0 || (float) ($bb['sellout'] ?? 0) > 0) $rows[] = [$bb['brand'], aiNum_($bb['qty']), aiNum_($bb['sellout'] ?? 0), aiNum_($bb['noo'] ?? 0) === '0' ? '—' : 'NOO'];
        $cur = $c['current']; $pv = $c['previous'];
        $data = ['dealer' => ['kode' => $c['id'], 'nama' => $c['nama'], 'region' => $c['region'] ?? '', 'kota' => $c['kota'] ?? '', 'sales' => aiDRow_($x, $c)[3], 'status' => $c['status'] ?? '', 'order_terakhir' => $c['last'] ?? null, 'jeda_hari' => $c['jeda'] ?? null],
            'bulan_ini' => $cur, 'lmtd' => $pv, 'growth_qty_persen' => $gp((float) $cur['qty'], (float) $pv['qty']), 'per_brand' => $rows];
        return ['data' => $data, 'blocks' => [['t' => 'kpi', 'items' => [['l' => 'Sell Thru', 'v' => aiNum_($cur['qty']), 's' => 'LMTD ' . aiNum_($pv['qty']) . ' · ' . aiPct_($data['growth_qty_persen']), 'c' => '#1E5FB8'], ['l' => 'Sell Out', 'v' => aiNum_($cur['sellout'] ?? 0), 's' => 'bulan ini', 'c' => '#C25A12'], ['l' => 'Status', 'v' => (string) ($c['status'] ?? '—'), 's' => $c['last'] ? 'order terakhir ' . $c['last'] . ' (' . (int) $c['jeda'] . ' hari)' : 'belum pernah order', 'c' => '#0E7C6B']]],
            ['t' => 'table', 'title' => $c['id'] . ' · ' . $c['nama'] . ' · per brand', 'cols' => ['Brand', 'Qty', 'Sell Out', 'NOO'], 'rows' => $rows]],
            'summary' => $c['nama'] . ' (' . $c['id'] . ', sales ' . $data['dealer']['sales'] . '): ST ' . aiNum_($cur['qty']) . ' bulan ini vs ' . aiNum_($pv['qty']) . ' LMTD, status ' . ($c['status'] ?? '—') . ($c['last'] ? ', order terakhir ' . $c['last'] . ' (' . (int) $c['jeda'] . ' hari lalu).' : '.')];
    }
    if ($mode === 'concentration') {
        $L = $cu; usort($L, fn($a, $b) => (float) $b['current']['qty'] <=> (float) $a['current']['qty']); $tq = max(1, array_sum(array_map(fn($c) => (float) $c['current']['qty'], $L))); $to = max(1, array_sum(array_map(fn($c) => (float) ($c['current']['omzet'] ?? 0), $L)));
        $act = count(array_filter($L, fn($c) => (float) $c['current']['qty'] > 0)); $s = [];
        foreach ([5, 10, 20] as $n) { $t = array_slice($L, 0, $n); $s[$n] = ['qty' => array_sum(array_map(fn($c) => (float) $c['current']['qty'], $t)) / $tq * 100, 'omzet' => array_sum(array_map(fn($c) => (float) ($c['current']['omzet'] ?? 0), $t)) / $to * 100]; }
        $rows = array_map(fn($c) => array_merge(aiDRow_($x, $c), [aiNum_($c['current']['qty']), aiPct_((float) $c['current']['qty'] / $tq * 100)]), array_slice($L, 0, 10));
        return ['data' => ['dealer_aktif' => $act, 'porsi_top' => $s], 'blocks' => [['t' => 'kpi', 'items' => [['l' => 'Top 5', 'v' => aiPct_($s[5]['qty']), 's' => 'porsi qty · omzet ' . aiPct_($s[5]['omzet']), 'c' => '#1E5FB8'], ['l' => 'Top 10', 'v' => aiPct_($s[10]['qty']), 's' => 'porsi qty · omzet ' . aiPct_($s[10]['omzet']), 'c' => '#6D3FD8'], ['l' => 'Top 20', 'v' => aiPct_($s[20]['qty']), 's' => 'dari ' . aiNum_($act) . ' dealer aktif', 'c' => '#C25A12']]],
            ['t' => 'table', 'title' => '10 dealer terbesar' . $rg, 'cols' => array_merge($H, ['Qty', 'Porsi']), 'rows' => $rows]],
            'summary' => '10 dealer teratas menyumbang ' . aiPct_($s[10]['qty']) . ' qty dan ' . aiPct_($s[10]['omzet']) . ' omzet dari ' . aiNum_($act) . ' dealer aktif.' . ($s[10]['qty'] > 50 ? ' Cukup tergantung ke dealer besar.' : '')];
    }
    $rows = []; $title = ''; $cols = $H;
    if ($mode === 'lost') {
        foreach ($cu as $c) { $p = (float) $c['previous']['qty']; if ($p > 0 && (float) $c['current']['qty'] <= 0) $rows[] = [$p, array_merge(aiDRow_($x, $c), [aiNum_($p), $c['last'] ?? '—', $c['jeda'] === null ? '—' : (int) $c['jeda'] . ' hr'])]; }
        $title = 'Dealer bulan lalu order, bulan ini belum'; $cols = array_merge($H, ['Qty LMTD', 'Order terakhir', 'Jeda']);
    } elseif ($mode === 'top' || $mode === 'drop') {
        foreach ($cu as $c) { $q = (float) $c['current']['qty']; $p = (float) $c['previous']['qty']; if ($mode === 'top' && $q <= 0) continue; if ($mode === 'drop' && $p <= $q) continue;
            $rows[] = [$mode === 'top' ? $q : $p - $q, array_merge(aiDRow_($x, $c), [aiNum_($p), aiNum_($q), $mode === 'top' ? aiPct_($gp($q, $p)) : '-' . aiNum_($p - $q)])]; }
        $title = $mode === 'top' ? 'Dealer terbesar bulan ini' : 'Dealer paling turun vs LMTD'; $cols = array_merge($H, ['LMTD', 'Bulan ini', $mode === 'top' ? 'Growth' : 'Turun']);
    } elseif ($mode === 'cross') {
        $all = array_map(fn($b) => $b['brand'], aiDash_($x)['brands'] ?? []);
        foreach ($cu as $c) { $has = []; foreach ($c['brands'] ?? [] as $bb) if ((float) $bb['qty'] > 0) $has[] = $bb['brand'];
            if (!$has || ($brand !== '' && !in_array($brand, $has, true))) continue; $miss = array_values(array_diff($all, $has)); if (!$miss) continue;
            $rows[] = [(float) $c['current']['qty'] + 1000 * (count($all) - count($miss)), array_merge(aiDRow_($x, $c), [implode(', ', $has), implode(', ', $miss)])]; }
        $title = $brand ? 'Order ' . $brand . ', belum order brand lain' : 'Dealer aktif yang belum order semua brand'; $cols = array_merge($H, ['Sudah order', 'Belum order']);
    }
    usort($rows, fn($a, $b) => $b[0] <=> $a[0]); $n = count($rows); $rows = array_map(fn($r) => $r[1], $rows);
    $labels = ['lost' => 'dealer hilang', 'top' => 'dealer aktif', 'drop' => 'dealer turun', 'cross' => 'dealer peluang cross-sell'];
    return ['data' => ['mode' => $mode, 'jumlah' => $n, 'dealer' => array_map(fn($r) => array_combine($cols, $r), array_slice($rows, 0, $lim))],
        'blocks' => [['t' => 'table', 'title' => $title . $rg . ' · ' . $n . ' dealer', 'cols' => $cols, 'rows' => array_slice($rows, 0, max($lim, 10))]],
        'summary' => $n . ' ' . ($labels[$mode] ?? 'dealer') . $rg . '.' . ($rows ? ' Teratas: ' . implode(', ', array_map(fn($r) => $r[1], array_slice($rows, 0, 3))) . '.' : '')];
}
function aiRank_(array &$x, string $mode, string $region = '', string $p1 = '', string $p2 = ''): array {
    $ew = aiEw_($x); $run = (float) ($ew['run'] ?? 0); $days = (float) ($ew['days'] ?? 0); $pc = fn($a, $b) => $b > 0 ? $a / $b * 100 : null;
    if ($mode === 'compare') {
        $A = []; foreach ([$p1, $p2] as $pn) { if ($pn === '') continue; $r = aiRun_('perf_summary', ['person' => $pn], $x); if (empty($r['data']['sell_thru'])) return $r; $A[] = [$r['data']['cakupan'] ?? $pn, $r['data']]; }
        if (count($A) < 2) return ['data' => [], 'blocks' => [], 'summary' => 'Sebut dua nama yang mau dibandingin, misalnya "bandingin Ahmad Rizki sama Budi Santoso".'];
        $g = fn($d, $a, $b) => $d[$a][$b] ?? null; $f = fn($v, $p = false) => $v === null ? '—' : ($p ? aiPct_($v) : aiNum_($v));
        $rows = [['ST target', $f($g($A[0][1], 'sell_thru', 'target')), $f($g($A[1][1], 'sell_thru', 'target'))], ['ST aktual', $f($g($A[0][1], 'sell_thru', 'aktual')), $f($g($A[1][1], 'sell_thru', 'aktual'))], ['% capai', $f($g($A[0][1], 'sell_thru', 'capai_persen'), 1), $f($g($A[1][1], 'sell_thru', 'capai_persen'), 1)], ['Proyeksi % target', $f($g($A[0][1], 'sell_thru', 'proyeksi_persen_target'), 1), $f($g($A[1][1], 'sell_thru', 'proyeksi_persen_target'), 1)], ['Growth LMTD', $f($g($A[0][1], 'sell_thru', 'growth_vs_lmtd_persen'), 1), $f($g($A[1][1], 'sell_thru', 'growth_vs_lmtd_persen'), 1)],
            ['DA target', $f($g($A[0][1], 'dealer_aktif', 'target')), $f($g($A[1][1], 'dealer_aktif', 'target'))], ['DA aktual', $f($g($A[0][1], 'dealer_aktif', 'aktual')), $f($g($A[1][1], 'dealer_aktif', 'aktual'))], ['NOO target', $f($g($A[0][1], 'noo', 'target')), $f($g($A[1][1], 'noo', 'target'))], ['NOO aktual', $f($g($A[0][1], 'noo', 'aktual')), $f($g($A[1][1], 'noo', 'aktual'))], ['Sell Out', $f($g($A[0][1], 'sell_out', 'aktual')), $f($g($A[1][1], 'sell_out', 'aktual'))]];
        $w = ($g($A[0][1], 'sell_thru', 'proyeksi_persen_target') ?? 0) >= ($g($A[1][1], 'sell_thru', 'proyeksi_persen_target') ?? 0) ? 0 : 1;
        return ['data' => ['a' => $A[0], 'b' => $A[1]], 'blocks' => [['t' => 'table', 'title' => 'Perbandingan', 'cols' => ['Indikator', $A[0][0], $A[1][0]], 'rows' => $rows]], 'summary' => 'Proyeksi Sell Thru lebih bagus ' . $A[$w][0] . '.'];
    }
    if ($mode === 'region') {
        $R = []; foreach (aiDash_($x)['regionalBrands'] ?? [] as $rb) { $k = (string) $rb['region']; if ($region !== '' && stripos($k, $region) === false) continue; $R[$k] ??= ['q' => 0, 't' => 0, 'da' => 0, 'tda' => 0];
            $R[$k]['q'] += (float) ($rb['total']['qty'] ?? 0); $R[$k]['da'] += (float) ($rb['total']['da'] ?? 0); if (!empty($rb['target']['exists'])) $R[$k]['t'] += (float) $rb['target']['value']; if (!empty($rb['targetDA']['exists'])) $R[$k]['tda'] += (float) $rb['targetDA']['value']; }
        $L = []; foreach ($R as $k => $r) { $pr = aiProj_($r['q'], $run, $days); $L[] = ['region' => $k, 'target' => $r['t'], 'aktual' => $r['q'], 'capai' => $pc($r['q'], $r['t']), 'proyeksi_persen' => $pc($pr ?? 0, $r['t']), 'da_target' => $r['tda'], 'da' => $r['da'], 'da_persen' => $pc($r['da'], $r['tda'])]; }
        usort($L, fn($a, $b) => ($b['proyeksi_persen'] ?? -1) <=> ($a['proyeksi_persen'] ?? -1));
        return ['data' => ['region' => $L], 'blocks' => [['t' => 'table', 'title' => 'Ranking region (proyeksi tertinggi dulu)', 'cols' => ['Region', 'Target', 'Aktual', '% capai', 'Proyeksi', 'Target DA', 'DA', '% DA'], 'rows' => array_map(fn($r) => [$r['region'], aiNum_($r['target']), aiNum_($r['aktual']), aiPct_($r['capai']), aiPct_($r['proyeksi_persen']), aiNum_($r['da_target']), aiNum_($r['da']), aiPct_($r['da_persen'])], $L)]],
            'summary' => $L ? 'Region terbaik ' . $L[0]['region'] . ' (proyeksi ' . aiPct_($L[0]['proyeksi_persen']) . '), terlemah ' . end($L)['region'] . ' (' . aiPct_(end($L)['proyeksi_persen']) . ').' : 'Data region belum ada.'];
    }
    $e = aiEw_($x, 'SALES'); $cu = aiDash_($x)['customers'] ?? []; $nm = aiSalesNm_($x); $agg = [];
    foreach ($cu as $c) { $s = $nm[(string) ($c['nik'] ?? '')] ?? null; if (!$s) continue; $agg[$s] ??= ['da' => 0, 'noo' => 0]; $agg[$s]['da'] += (float) ($c['current']['da'] ?? ((float) $c['current']['qty'] > 0 ? 1 : 0)); $agg[$s]['noo'] += (float) ($c['current']['noo'] ?? 0); }
    $L = [];
    foreach ($e['rows'] ?? [] as $r) { if (preg_match('/^VACANT/i', (string) $r['nama'])) continue; if ($region !== '' && stripos((string) ($r['region'] ?? '') . ' ' . ($r['big'] ?? ''), $region) === false) continue;
        $pr = aiProj_($r['actual'], $run, $days); $L[] = ['nama' => $r['nama'], 'region' => $r['region'] ?? '', 'target' => (float) $r['target'], 'aktual' => (float) $r['actual'], 'capai' => $pc((float) $r['actual'], (float) $r['target']), 'proyeksi_persen' => $pc($pr ?? 0, (float) $r['target']), 'da' => $agg[$r['nama']]['da'] ?? null, 'noo' => $agg[$r['nama']]['noo'] ?? null]; }
    usort($L, fn($a, $b) => ($b['proyeksi_persen'] ?? -1) <=> ($a['proyeksi_persen'] ?? -1)); $i = 0;
    return ['data' => ['sales' => $L], 'blocks' => [['t' => 'table', 'title' => 'Ranking sales' . ($region ? ' · ' . $region : '') . ' (proyeksi tertinggi dulu)', 'cols' => ['#', 'Nama', 'Region', 'Target', 'Aktual', '% capai', 'Proyeksi', 'DA', 'NOO'], 'rows' => array_map(function ($r) use (&$i) { $i++; return [$i, $r['nama'], $r['region'], aiNum_($r['target']), aiNum_($r['aktual']), aiPct_($r['capai']), aiPct_($r['proyeksi_persen']), $r['da'] === null ? '—' : aiNum_($r['da']), $r['noo'] === null ? '—' : aiNum_($r['noo'])]; }, array_slice($L, 0, 40))]],
        'summary' => $L ? count($L) . ' sales. Teratas ' . $L[0]['nama'] . ' (proyeksi ' . aiPct_($L[0]['proyeksi_persen']) . '), terbawah ' . end($L)['nama'] . ' (' . aiPct_(end($L)['proyeksi_persen']) . ').' : 'Ranking sales tidak tersedia di cakupan ini.'];
}
function aiDayPattern_(array &$x): array {
    $d = aiDash_($x)['daily'] ?? []; $nm = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']; $S = [];
    foreach ($d as $r) { $w = (int) date('w', strtotime($r['date'])); $S[$w] ??= ['q' => 0, 'n' => 0]; $S[$w]['q'] += (float) $r['qty']; $S[$w]['n']++; }
    ksort($S); $L = []; foreach ($S as $w => $s) $L[] = ['hari' => $nm[$w], 'rata2' => $s['q'] / max(1, $s['n']), 'jumlah_hari' => $s['n']];
    $mx = max(1, ...array_map(fn($r) => $r['rata2'], $L ?: [['rata2' => 1]])); $sorted = $L; usort($sorted, fn($a, $b) => $b['rata2'] <=> $a['rata2']);
    $wk = aiDash_($x)['weekly'] ?? []; $n = count($wk); $cmp = $n >= 2 ? ['minggu_ini' => $wk[$n - 1], 'minggu_lalu' => $wk[$n - 2]] : null;
    $bl = [['t' => 'bars', 'title' => 'Rata-rata Sell Thru per hari', 'rows' => array_map(fn($r) => ['l' => $r['hari'], 'v' => $r['rata2'] / $mx * 100, 'txt' => number_format($r['rata2'], 1, ',', '.'), 'c' => '#1E5FB8'], $L)]];
    if ($cmp) $bl[] = ['t' => 'kpi', 'items' => [['l' => 'Minggu ' . $cmp['minggu_lalu']['week'], 'v' => aiNum_($cmp['minggu_lalu']['qty']), 's' => 'minggu lalu', 'c' => '#64748B'], ['l' => 'Minggu ' . $cmp['minggu_ini']['week'], 'v' => aiNum_($cmp['minggu_ini']['qty']), 's' => 'minggu ini (bisa belum penuh)', 'c' => '#1E5FB8']]];
    return ['data' => ['per_hari' => $L, 'minggu' => $cmp, 'catatan' => 'Minggu berjalan bisa belum penuh.'], 'blocks' => $bl,
        'summary' => $sorted ? 'Paling tinggi hari ' . $sorted[0]['hari'] . ' (rata-rata ' . number_format($sorted[0]['rata2'], 1, ',', '.') . '), paling rendah ' . end($sorted)['hari'] . '.' . ($cmp ? ' Minggu ini ' . aiNum_($cmp['minggu_ini']['qty']) . ' vs minggu lalu ' . aiNum_($cmp['minggu_lalu']['qty']) . '.' : '') : 'Belum ada data harian.'];
}
function aiFocus_(array &$x, string $region = ''): array {
    $p = aiPerf_($x, $region)['data']; $st = $p['sell_thru']; $tips = []; $bl = [];
    if ($st['butuh_per_hari_untuk_tembus'] !== null) $tips[] = 'Kejar ' . number_format($st['butuh_per_hari_untuk_tembus'], 1, ',', '.') . ' unit/hari (run rate sekarang ' . number_format((float) $st['run_rate_per_hari'], 1, ',', '.') . ') untuk tembus target, kurang ' . aiNum_($st['gap_ke_target']) . '.';
    if (($p['dealer_aktif']['kurang'] ?? 0) > 0) $tips[] = 'Dealer Aktif kurang ' . aiNum_($p['dealer_aktif']['kurang']) . ': aktifkan dealer yang bulan lalu order (daftar di bawah).';
    if (($p['noo']['kurang'] ?? 0) > 0) $tips[] = 'NOO kurang ' . aiNum_($p['noo']['kurang']) . ' dealer baru.';
    $b = aiBrandType_($x, '', $region)['data']; $w = null; foreach ($b['brand'] as $r) if ($r['proyeksi_persen'] !== null && (!$w || $r['proyeksi_persen'] < $w['proyeksi_persen'])) $w = $r;
    if ($w && $w['proyeksi_persen'] < 100) $tips[] = 'Brand paling ketinggalan: ' . $w['brand'] . ' (proyeksi ' . aiPct_($w['proyeksi_persen']) . ').';
    try { $k = aiKpi_($x)['data']['indikator_terlemah'] ?? []; if ($k) $tips[] = 'KPI terlemah: ' . implode(', ', array_map(fn($r) => $r['indikator'] . ' ' . aiPct_($r['capai_persen']), $k)) . '.'; } catch (Throwable $e) {}
    $lost = aiDealer_($x, 'lost', '', $region, '', 8); $pri = aiPriority_($x, $region, 8);
    $bl[] = ['t' => 'note', 'text' => implode("\n", array_map(fn($t, $i) => ($i + 1) . '. ' . $t, $tips, array_keys($tips)))];
    $bl[] = $lost['blocks'][0]; if (!empty($pri['blocks'])) $bl[] = end($pri['blocks']);
    return ['data' => ['prioritas' => $tips, 'ringkasan' => $p, 'dealer_hilang' => $lost['data'], 'dealer_prioritas' => $pri['data'] ?? null], 'blocks' => $bl, 'summary' => 'Fokus minggu ini: ' . implode(' ', $tips)];
}
/* Penjualan harian (2026-11-25): N hari terakhir atau satu tanggal, dari dashboard daily[]. Per sales ikut cakupan lewat person; per region tidak ada data harian. */
function aiDailyRegion_(array &$x, string $region): ?array {
    try {
        $source = snapshot_(); $u = guard_($x['token'], false, false, $source); $base = allowed_($source, $u, 'ALL'); $bs = js_set($base); $ids = [];
        foreach ($source['meta']['people'] as $p) { if (!isset($bs[(string) $p['nik']]) || !truthy($p['sales'] ?? false)) continue;
            foreach (['wilayah', 'region', 'portfolio'] as $k) if (stripos((string) ($p[$k] ?? ''), $region) !== false) { $ids[] = $p['nik']; break; } }
        if (!$ids) return null; $db = coverageView_($source, $x['period'], $ids, 'ST', $u); return PortalCore::report($db, $x['period'], $ids, null);
    } catch (Throwable $e) { return null; }
}
function aiRowsCtx_(array &$x, string $region = ''): array {
    $source = snapshot_(); $u = guard_($x['token'], false, false, $source); $ids = allowed_($source, $u, $x['sel'] ?: 'ALL');
    if ($region !== '') { $keep = []; $set = js_set($ids); foreach ($source['meta']['people'] as $p) { if (!isset($set[(string) $p['nik']]) || !truthy($p['sales'] ?? false)) continue; foreach (['wilayah', 'region', 'portfolio'] as $k) if (stripos((string) ($p[$k] ?? ''), $region) !== false) { $keep[] = $p['nik']; break; } } $ids = $keep; if (!$ids) throw new RuntimeException('Wilayah "' . $region . '" tidak ketemu di cakupan lu.'); }
    $db = coverageView_($source, $x['period'], $ids, 'ST', $u); return [$db, $ids, PortalCore::slice($db, $x['period'], $ids)];
}
/* Perkembangan kumulatif per tanggal: Dealer Aktif & NOO sampai tanggal itu (total, atau satu brand), per wilayah/sales. */
function aiCum_(array &$x, string $brand = '', string $region = '', int $n = 10): array {
    try { [$db, $ids, $all] = aiRowsCtx_($x, $region); } catch (Throwable $e) { return ['data' => ['error' => $e->getMessage()], 'blocks' => [], 'summary' => $e->getMessage()]; }
    $period = $x['period']; $dates = array_values(array_unique(array_map(fn($r) => $r['date'], $all))); sort($dates, SORT_STRING); $dates = array_slice($dates, -max(1, min(31, $n)));
    $hf = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']; $rows = []; $out = []; $prevDa = null; $prevNoo = null;
    foreach ($dates as $d) { $cum = array_values(array_filter($all, fn($r) => strcmp($r['date'], $d) <= 0)); $day = array_values(array_filter($all, fn($r) => $r['date'] === $d));
        $mc = PortalCore::metrics($cum, $db, $period, $brand ?: null); $md = PortalCore::metrics($day, $db, $period, $brand ?: null);
        $rows[] = [date('d/m/Y', strtotime($d)), $hf[(int) date('w', strtotime($d))], aiNum_($mc['da']), $prevDa === null ? '—' : '+' . aiNum_($mc['da'] - $prevDa), aiNum_($mc['noo']), $prevNoo === null ? '—' : '+' . aiNum_($mc['noo'] - $prevNoo), aiNum_($md['outlets']), aiNum_($md['qty'])];
        $out[] = ['tanggal' => $d, 'da_kumulatif' => $mc['da'], 'noo_kumulatif' => $mc['noo'], 'dealer_transaksi_hari_itu' => $md['outlets'], 'qty_hari_itu' => $md['qty']]; $prevDa = $mc['da']; $prevNoo = $mc['noo']; }
    $last = end($out) ?: null;
    return ['data' => ['brand' => $brand ?: 'semua', 'wilayah' => $region ?: 'semua', 'per_tanggal' => $out, 'catatan' => 'Dealer Aktif dan NOO dihitung kumulatif dari awal periode sampai tanggal itu, dengan aturan yang sama seperti Beranda.'],
        'blocks' => [['t' => 'kpi', 'items' => $last ? [['l' => 'Dealer Aktif', 'v' => aiNum_($last['da_kumulatif']), 's' => 'sampai ' . $last['tanggal'], 'c' => '#0E7C6B'], ['l' => 'NOO', 'v' => aiNum_($last['noo_kumulatif']), 's' => 'kumulatif', 'c' => '#6D3FD8']] : []],
            ['t' => 'table', 'title' => 'Dealer Aktif & NOO kumulatif per tanggal' . ($brand ? ' · ' . $brand : '') . ($region ? ' · ' . $region : ''), 'cols' => ['Tanggal', 'Hari', 'Dealer Aktif', 'Tambah DA', 'NOO', 'Tambah NOO', 'Dealer transaksi', 'Qty hari itu'], 'rows' => array_reverse($rows)]],
        'summary' => $last ? 'Sampai ' . $last['tanggal'] . ($brand ? ' (' . $brand . ')' : '') . ': Dealer Aktif ' . aiNum_($last['da_kumulatif']) . ', NOO ' . aiNum_($last['noo_kumulatif']) . '.' : 'Belum ada transaksi di periode ini.'];
}
/* Detail satu tanggal (2026-11-25): dari transaksi mentah. Dealer transaksi, NOO baru (pasangan dealer-brand pertama kali order di tanggal itu), DA kumulatif s/d tanggal itu, dan daftar dealer. */
function aiDateDetail_(array &$x, string $date, string $region = ''): array {
    try { [$db, $ids, $all] = aiRowsCtx_($x, $region); $period = $x['period']; } catch (Throwable $e) { return ['data' => ['error' => $e->getMessage()], 'blocks' => [], 'summary' => $e->getMessage()]; }
    $day = array_values(array_filter($all, fn($r) => $r['date'] === $date));
    if (!$day) return ['data' => [], 'blocks' => [], 'summary' => 'Tidak ada transaksi tanggal ' . $date . ' di cakupan ini (libur, belum ada data, atau di luar periode).'];
    $cum = array_values(array_filter($all, fn($r) => strcmp($r['date'], $date) <= 0));
    $nm = []; foreach (aiDash_($x)['customers'] ?? [] as $c) $nm[(string) $c['id']] = $c; $sn = aiSalesNm_($x);
    $brands = array_values(array_unique(array_map(fn($r) => $r['brand'], $day))); sort($brands);
    $mt = PortalCore::metrics($day, $db, $period); $mc = PortalCore::metrics($cum, $db, $period);
    $rows = []; $bd = [];
    foreach ($brands as $b) { $md = PortalCore::metrics($day, $db, $period, $b); $mk = PortalCore::metrics($cum, $db, $period, $b);
        $rows[] = [$b, aiNum_($md['qty']), aiNum_($md['outlets']), aiNum_($md['noo']), aiNum_($mk['da']), aiNum_($mk['noo']), aiNum_($md['omzet'])];
        $bd[] = ['brand' => $b, 'qty_hari_itu' => $md['qty'], 'dealer_transaksi' => $md['outlets'], 'noo_hari_itu' => $md['noo'], 'da_kumulatif' => $mk['da'], 'noo_kumulatif' => $mk['noo'], 'omzet' => $md['omzet']]; }
    $first = $db['first'] ?? []; $D = []; $N = [];
    foreach ($day as $r) { $id = (string) $r['customer']; $D[$id]['b'][$r['brand']] = ($D[$id]['b'][$r['brand']] ?? 0) + (float) $r['q']; $D[$id]['q'] = ($D[$id]['q'] ?? 0) + (float) $r['q'];
        $f = $first[jkey([$r['customer'], $r['brand']])] ?? null; if ($f && $f['date'] === $date && $f['nik'] === $r['nik'] && (float) $r['q'] > 0) $N[$id][$r['brand']] = true; }
    uasort($D, fn($a, $b) => $b['q'] <=> $a['q']);
    $info = fn($id) => [$id, (string) ($nm[$id]['nama'] ?? $id), (string) ($nm[$id]['region'] ?? ''), (string) (($nm[$id]['sales'] ?? '') ?: ($sn[(string) ($nm[$id]['nik'] ?? '')] ?? '—'))];
    $drows = []; foreach ($D as $id => $v) { $bs = []; foreach ($v['b'] as $b => $q) $bs[] = $b . ' ' . aiNum_($q); $drows[] = array_merge($info($id), [implode(', ', $bs), aiNum_($v['q'])]); }
    $nrows = []; foreach ($N as $id => $bs) $nrows[] = array_merge($info($id), [implode(', ', array_keys($bs))]);
    $rg = $region ? ' · ' . $region : '';
    $blocks = [['t' => 'kpi', 'items' => [['l' => 'Sell Thru', 'v' => aiNum_($mt['qty']), 's' => $date, 'c' => '#1E5FB8'], ['l' => 'Dealer transaksi', 'v' => aiNum_($mt['outlets']), 's' => aiNum_($mt['nota']) . ' SJ', 'c' => '#0E7C6B'], ['l' => 'NOO hari itu', 'v' => aiNum_($mt['noo']), 's' => 'kumulatif ' . aiNum_($mc['noo']), 'c' => '#6D3FD8'], ['l' => 'DA s/d tanggal ini', 'v' => aiNum_($mc['da']), 's' => 'kumulatif bulan berjalan', 'c' => '#0E9F6E']]],
        ['t' => 'table', 'title' => 'Per brand · ' . $date . $rg, 'cols' => ['Brand', 'Qty', 'Dealer transaksi', 'NOO hari itu', 'DA s/d tanggal', 'NOO kumulatif', 'Omzet (Rp)'], 'rows' => $rows]];
    if ($nrows) $blocks[] = ['t' => 'table', 'title' => 'Dealer NOO tanggal ' . $date . ' · ' . count($nrows) . ' dealer', 'cols' => ['Kode', 'Nama Dealer', 'Region', 'Sales', 'Brand baru'], 'rows' => array_slice($nrows, 0, 30)];
    $blocks[] = ['t' => 'table', 'title' => 'Dealer yang transaksi tanggal ' . $date . ' · ' . count($drows) . ' dealer', 'cols' => ['Kode', 'Nama Dealer', 'Region', 'Sales', 'Brand (qty)', 'Total'], 'rows' => array_slice($drows, 0, 30)];
    return ['data' => ['tanggal' => $date, 'wilayah' => $region ?: 'semua', 'sell_thru' => $mt['qty'], 'dealer_transaksi' => $mt['outlets'], 'nota' => $mt['nota'], 'noo_hari_itu' => $mt['noo'], 'da_kumulatif' => $mc['da'], 'noo_kumulatif' => $mc['noo'], 'per_brand' => $bd, 'dealer' => array_map(fn($r) => ['kode' => $r[0], 'nama' => $r[1], 'brand' => $r[4]], array_slice($drows, 0, 30)), 'dealer_noo' => array_map(fn($r) => ['kode' => $r[0], 'nama' => $r[1], 'brand' => $r[4]], $nrows)],
        'blocks' => $blocks, 'summary' => $date . ': Sell Thru ' . aiNum_($mt['qty']) . ' dari ' . aiNum_($mt['outlets']) . ' dealer (' . aiNum_($mt['nota']) . ' SJ), NOO ' . aiNum_($mt['noo']) . ' (kumulatif ' . aiNum_($mc['noo']) . '), Dealer Aktif kumulatif ' . aiNum_($mc['da']) . '.'];
}
/* Rincian transaksi (2026-11-25): baris transaksi per tanggal dari data mentah: dealer, brand, TYPE, qty, omzet, nota. Per sales/region/brand/dealer. */
function aiTrans_(array &$x, string $date = '', string $region = '', string $brand = '', string $dealer = '', int $lim = 40, string $by = ''): array {
    try { [$db, $ids, $all] = aiRowsCtx_($x, $region); } catch (Throwable $e) { return ['data' => ['error' => $e->getMessage()], 'blocks' => [], 'summary' => $e->getMessage()]; }
    if (preg_match('/^@D(\d+)$/', $date, $dm)) $date = substr((string) $x['period'], 0, 7) . '-' . str_pad($dm[1], 2, '0', STR_PAD_LEFT);
    $isAll = strtoupper($date) === 'ALL'; if ($isAll) $date = ''; elseif ($date === '') { $ds = array_map(fn($r) => $r['date'], $all); rsort($ds, SORT_STRING); $date = $ds[0] ?? ''; $auto = true; }
    $rows = array_values(array_filter($all, fn($r) => ($isAll || $r['date'] === $date) && (float) $r['q'] !== 0.0 && ($brand === '' || strcasecmp($r['brand'], $brand) === 0)));
    $nm = []; foreach (aiDash_($x)['customers'] ?? [] as $c) $nm[(string) $c['id']] = $c; $sn = aiSalesNm_($x);
    if ($dealer !== '') { $cd = aiDealerCands_($dealer, $x, array_map(fn($b) => $b['brand'], aiDash_($x)['brands'] ?? [])); if (count($cd) > 1) { $tl = $isAll ? 'sebulan' : ($date !== '' ? 'tanggal ' . (int) substr($date, 8, 2) : ''); $r = aiDealerPick_($cd, $dealer, 'Transaksi', $tl . ($brand ? ' ' . $brand : '')); $r['data']['need_choice'] = true; return $r; } if (count($cd) === 1) { $id = (string) $cd[0]['c']['id']; $rows = array_values(array_filter($rows, fn($r) => (string) $r['customer'] === $id)); } else return ['data' => [], 'blocks' => [], 'summary' => 'Gue belum nemu dealer "' . $dealer . '" di cakupan lu, jadi gue gak mau nebak. Tulis ulang nama atau kode dealernya ya.']; }
    if (!$rows) return ['data' => [], 'blocks' => [], 'summary' => 'Tidak ada transaksi' . ($date ? ' tanggal ' . $date : '') . ' di cakupan ini (libur, belum ada data, atau di luar periode).'];
    usort($rows, fn($a, $b) => $isAll ? [$b['date'], (string) $a['customer']] <=> [$a['date'], (string) $b['customer']] : [(string) $a['customer'], $b['q']] <=> [(string) $b['customer'], $a['q']]);
    $docs = []; $dl = []; $q = 0; $om = 0; $out = []; $tb = [];
    foreach ($rows as $r) { foreach ((array) ($r['docs'] ?? []) as $d) $docs[(string) $d] = 1; $id = (string) $r['customer']; $dl[$id] = 1; $q += (float) $r['q']; $om += (float) $r['a']; $c = $nm[$id] ?? [];
        $nota = array_slice(array_map('strval', (array) ($r['docs'] ?? [])), 0, 2); $more = count((array) ($r['docs'] ?? [])) > 2 ? ' +' . (count($r['docs']) - 2) : '';
        $tb[] = array_merge($isAll ? [date('d/m', strtotime($r['date']))] : [], [$id, (string) ($c['nama'] ?? $id), (string) ($r['brand'] ?? ''), (string) ($r['type'] ?? ''), aiNum_($r['q']), aiNum_($r['a']), implode(', ', $nota) . $more]);
        $out[] = ['tanggal' => $r['date'], 'kode' => $id, 'dealer' => $c['nama'] ?? $id, 'sales' => ($c['sales'] ?? '') ?: ($sn[(string) ($r['nik'] ?? '')] ?? ''), 'brand' => $r['brand'], 'type' => $r['type'], 'qty' => $r['q'], 'omzet' => $r['a'], 'no_sj' => array_values(array_map('strval', (array) ($r['docs'] ?? [])))]; }
    $BR = []; if ($brand === '') { foreach ($rows as $r) { $k = (string) $r['brand']; $BR[$k] ??= ['d' => [], 'sj' => [], 'q' => 0, 'a' => 0]; $BR[$k]['d'][(string) $r['customer']] = 1; foreach ((array) ($r['docs'] ?? []) as $dd) $BR[$k]['sj'][(string) $dd] = 1; $BR[$k]['q'] += (float) $r['q']; $BR[$k]['a'] += (float) $r['a']; } uasort($BR, fn($a, $b) => $b['q'] <=> $a['q']); }
    $G = []; $byU = strtolower($by);
    if ($byU === 'sales' || $byU === 'region') { foreach ($rows as $r) { $k = $byU === 'sales' ? ($sn[(string) ($r['nik'] ?? '')] ?? (string) ($r['nik'] ?? '—')) : (string) (($r['region'] ?? '') ?: '—'); $G[$k] ??= ['rg' => (string) ($r['region'] ?? ''), 'd' => [], 'sj' => [], 'q' => 0, 'a' => 0]; $G[$k]['d'][(string) $r['customer']] = 1; foreach ((array) ($r['docs'] ?? []) as $dd) $G[$k]['sj'][(string) $dd] = 1; $G[$k]['q'] += (float) $r['q']; $G[$k]['a'] += (float) $r['a']; }
        uasort($G, fn($a, $b) => $b['q'] <=> $a['q']); }
    $rg = $region ? ' · ' . $region : '';
    return ['data' => ['tanggal' => $date, 'otomatis_tanggal_terakhir' => !empty($auto), 'jumlah_baris' => count($rows), 'jumlah_sj' => count($docs), 'dealer' => count($dl), 'qty' => $q, 'omzet' => $om, 'transaksi' => array_slice($out, 0, 200), 'per_brand' => array_map(fn($k, $g) => ['brand' => $k, 'dealer' => count($g['d']), 'sj' => count($g['sj']), 'qty' => $g['q'], 'omzet' => $g['a']], array_keys($BR), array_values($BR)), 'per_kelompok' => array_map(fn($k, $g) => ['nama' => $k, 'dealer' => count($g['d']), 'sj' => count($g['sj']), 'qty' => $g['q'], 'omzet' => $g['a']], array_keys($G), array_values($G)), 'catatan' => 'Satu baris = gabungan per dealer, brand, TYPE, dan sales di tanggal itu. Nomor yang tampil adalah nomor SJ (surat jalan).'],
        'blocks' => [['t' => 'kpi', 'items' => [['l' => 'Surat jalan', 'v' => aiNum_(count($docs)), 's' => 'No. SJ · ' . ($isAll ? $x['period'] : $date), 'c' => '#1E5FB8'], ['l' => 'Dealer', 'v' => aiNum_(count($dl)), 's' => 'bertransaksi', 'c' => '#0E7C6B'], ['l' => 'Qty', 'v' => aiNum_($q), 's' => count($rows) . ' baris', 'c' => '#C25A12'], ['l' => 'Omzet', 'v' => aiNum_($om), 's' => 'Rp', 'c' => '#9A6400']]],
            ...(count($BR) > 1 ? [['t' => 'table', 'title' => 'Per brand · ' . count($BR) . ' brand', 'cols' => ['Brand', 'Dealer', 'Jumlah SJ', 'Qty', 'Omzet (Rp)', 'Kontribusi'], 'rows' => array_map(fn($k, $g) => [$k, aiNum_(count($g['d'])), aiNum_(count($g['sj'])), aiNum_($g['q']), aiNum_($g['a']), $q > 0 ? aiPct_($g['q'] / $q * 100) : '—'], array_keys($BR), array_values($BR))]] : []),
            ...($G ? [['t' => 'table', 'title' => 'Per ' . ($byU === 'sales' ? 'sales' : 'region') . ' · ' . count($G) . ' ' . ($byU === 'sales' ? 'sales' : 'region'), 'cols' => array_merge([$byU === 'sales' ? 'Sales' : 'Region'], $byU === 'sales' ? ['Region'] : [], ['Dealer', 'Jumlah SJ', 'Qty', 'Omzet (Rp)']), 'rows' => array_map(fn($k, $g) => array_merge([$k], $byU === 'sales' ? [$g['rg']] : [], [aiNum_(count($g['d'])), aiNum_(count($g['sj'])), aiNum_($g['q']), aiNum_($g['a'])]), array_keys($G), array_values($G))]] : []),
            ['t' => 'table', 'title' => 'Transaksi ' . ($isAll ? 'seluruh periode ' . $x['period'] : $date) . $rg . ($brand ? ' · ' . $brand : '') . ' · ' . count($rows) . ' baris', 'cols' => array_merge($isAll ? ['Tgl'] : [], ['Kode', 'Dealer', 'Brand', 'TYPE', 'Qty', 'Omzet (Rp)', 'No. SJ']), 'rows' => array_slice($tb, 0, $lim), 'fold' => count($tb) > 8 && ($BR || $G)]],
        'summary' => (!empty($auto) ? 'Tanggal terakhir yang ada data: ' : '') . ($isAll ? 'Periode ' . $x['period'] : $date) . ': ' . aiNum_(count($docs)) . ' SJ dari ' . aiNum_(count($dl)) . ' dealer, qty ' . aiNum_($q) . ', omzet Rp ' . aiNum_($om) . '.' . (count($rows) > $lim ? ' Tabel menampilkan ' . $lim . ' baris pertama dari ' . count($rows) . '.' : '')];
}
function aiDaily_(array &$x, int $n = 7, string $date = '', string $region = ''): array {
    $d = $region !== '' ? aiDailyRegion_($x, $region) : aiDash_($x);
    if ($d === null) return ['data' => [], 'blocks' => [], 'summary' => 'Wilayah "' . $region . '" tidak ketemu di cakupan lu atau belum ada sales di wilayah itu.']; $D = $d['daily'] ?? []; if (!$D) return ['data' => [], 'blocks' => [], 'summary' => 'Belum ada data penjualan harian di periode ini.'];
    if (preg_match('/^@D(\\d+)$/', $date, $dm)) $date = substr((string) $x['period'], 0, 7) . '-' . str_pad($dm[1], 2, '0', STR_PAD_LEFT);
    if ($date !== '') return aiDateDetail_($x, $date, $region);
    $nm = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']; $hf = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']; $n = max(1, min(31, $n));
    if ($date !== '') { $D = array_values(array_filter($D, fn($r) => $r['date'] === $date)); if (!$D) return ['data' => [], 'blocks' => [], 'summary' => 'Tidak ada data penjualan tanggal ' . $date . ' (di luar periode atau libur).']; } else $D = array_slice($D, -$n);
    $rows = []; $out = []; $sq = $so = $sm = 0; $mx = max(1, ...array_map(fn($r) => (float) $r['qty'], $D));
    foreach ($D as $r) { $w = $nm[(int) date('w', strtotime($r['date']))]; $q = (float) $r['qty']; $sq += $q; $so += (float) ($r['sellout'] ?? 0); $sm += (float) ($r['omzet'] ?? 0);
        $rows[] = [date('d/m/Y', strtotime($r['date'])), $hf[(int) date('w', strtotime($r['date']))], aiNum_($q), aiNum_($r['sellout'] ?? 0), aiNum_($r['omzet'] ?? 0), aiNum_($r['outlets'] ?? 0), aiNum_($r['nota'] ?? 0), aiNum_($r['noo'] ?? 0)];
        $out[] = ['tanggal' => $r['date'], 'hari' => $hf[(int) date('w', strtotime($r['date']))], 'sell_thru' => $q, 'sell_out' => $r['sellout'] ?? null, 'omzet' => $r['omzet'] ?? null, 'dealer_transaksi' => $r['outlets'] ?? null, 'jumlah_sj' => $r['nota'] ?? null, 'noo' => $r['noo'] ?? null, 'dealer_aktif_hari_itu' => $r['da'] ?? null]; }
    $best = null; foreach ($D as $r) if (!$best || $r['qty'] > $best['qty']) $best = $r; $c = count($D);
    $bars = array_map(fn($r) => ['l' => $nm[(int) date('w', strtotime($r['date']))] . ' ' . date('d/m', strtotime($r['date'])), 'v' => (float) $r['qty'] / $mx * 100, 'txt' => aiNum_($r['qty']), 'c' => '#1E5FB8'], $D);
    $blocks = [['t' => 'kpi', 'items' => [['l' => 'Total ST', 'v' => aiNum_($sq), 's' => $c . ' hari kerja', 'c' => '#1E5FB8'], ['l' => 'Rata-rata/hari', 'v' => number_format($sq / $c, 1, ',', '.'), 's' => 'Sell Thru', 'c' => '#0E7C6B'], ['l' => 'Sell Out', 'v' => aiNum_($so), 's' => 'total periode ini', 'c' => '#C25A12']]]];
    $bn = array_map(fn($b) => $b['brand'], $d['brands'] ?? []); $use = [];
    foreach ($bn as $i => $b) { foreach ($D as $r) if ((float) ($r['brands'][$i] ?? 0) > 0) { $use[$i] = $b; break; } }
    if ($use) {
        $tot = array_fill_keys(array_keys($use), 0); $brow = []; $bd = [];
        foreach (array_reverse($D) as $r) { $row = [date('d/m/Y', strtotime($r['date'])), $hf[(int) date('w', strtotime($r['date']))]]; $one = [];
            foreach ($use as $i => $b) { $q = (float) ($r['brands'][$i] ?? 0); $tot[$i] += $q; $row[] = $q ? aiNum_($q) : '-'; $one[$b] = $q; } $row[] = aiNum_($r['qty']); $brow[] = $row; $bd[] = ['tanggal' => $r['date']] + $one + ['total' => (float) $r['qty']]; }
        $trow = ['TOTAL', '']; foreach ($use as $i => $b) $trow[] = aiNum_($tot[$i]); $trow[] = aiNum_($sq); $brow[] = $trow; 
        $blocks[] = ['t' => 'table', 'title' => 'Sell Thru per hari per brand' . ($region ? ' · ' . $region : ''), 'cols' => array_merge(['Tanggal', 'Hari'], array_values($use), ['Total']), 'rows' => $brow];
    }
    $blocks[] = ['t' => 'table', 'title' => 'Sell Out, omzet, dealer transaksi & NOO per hari', 'cols' => ['Tanggal', 'Hari', 'Sell Thru', 'Sell Out', 'Omzet (Rp)', 'Dealer transaksi', 'Jumlah SJ', 'NOO'], 'rows' => array_reverse($rows)];
    return ['data' => ['catatan' => 'Hari tanpa transaksi tidak muncul. Dealer transaksi = jumlah dealer berbeda yang bertransaksi di hari itu; dealer_aktif_hari_itu dihitung hanya dari transaksi hari itu (bukan DA kumulatif bulan berjalan); NOO per brand per tanggal belum tersedia.', 'harian' => $out, 'per_brand_harian' => $bd ?? [], 'total_st' => $sq, 'rata2_st' => $sq / $c],
        'blocks' => $blocks, 'summary' => ($date ?: $c . ' hari terakhir') . ': Sell Thru ' . aiNum_($sq) . ($c > 1 ? ', rata-rata ' . number_format($sq / $c, 1, ',', '.') . '/hari. Tertinggi ' . date('d/m', strtotime($best['date'])) . ' (' . aiNum_($best['qty']) . ').' : '.')];
}
/* Cari dealer dari kalimat (2026-11-25): skor = jumlah kata nama dealer yang disebut; typo ditoleransi. Kode dealer persis = langsung. */
function aiDealerCands_(string $q, array &$x, array $skip = []): array {
    $Q = mb_strtoupper($q); $stop = array_merge(aiStopWords_(), array_map('mb_strtoupper', $skip), ['DEALER', 'TOKO', 'OUTLET', 'CUSTOMER', 'ORDER', 'APA', 'AJA', 'DETAIL', 'STATUS', 'GIMANA', 'LAGI', 'BULAN', 'INFO', 'TRANSAKSI', 'TANGGAL', 'TGL', 'NOTA', 'SEBULAN', 'SEMUA', 'SELURUH', 'KEMARIN', 'LENGKAP']);
    $qt = array_values(array_filter(array_unique(preg_split('/[^A-Z0-9]+/u', $Q)), fn($t) => strlen($t) >= 3 && !in_array($t, $stop, true) && !ctype_digit($t)));
    $cu = aiDash_($x)['customers'] ?? []; $res = [];
    foreach ($cu as $c) { if (stripos($Q, mb_strtoupper((string) $c['id'])) !== false && strlen((string) $c['id']) >= 4) return [['c' => $c, 'score' => 99, 'exact' => true]]; }
    if (!$qt) return [];
    foreach ($cu as $c) { $nt = array_filter(preg_split('/[^A-Z0-9]+/u', mb_strtoupper((string) $c['nama']))); $h = 0; $fz = 0;
        foreach ($qt as $t) { if (in_array($t, $nt, true)) $h++; else foreach ($nt as $w) if (aiFuzzyTok_($t, $w)) { $fz++; break; } }
        if ($h + $fz > 0) $res[] = ['c' => $c, 'score' => $h * 2 + $fz, 'exact' => false, 'hit' => $h]; }
    usort($res, fn($a, $b) => $b['score'] <=> $a['score']); if (!$res) return [];
    $top = $res[0]['score']; return array_values(array_filter($res, fn($r) => $r['score'] === $top));
}
function aiDealerPick_(array $cands, string $q, string $verb = 'Detail dealer', string $tail = ''): array {
    $rows = []; $chips = []; foreach (array_slice($cands, 0, 8) as $r) { $c = $r['c']; $rows[] = [(string) $c['id'], (string) $c['nama'], (string) ($c['region'] ?? ''), (string) ($c['kota'] ?? '')]; $chips[] = trim($verb . ' ' . $c['id'] . ' ' . $c['nama'] . ' ' . $tail); }
    return ['data' => ['pilihan' => array_column($rows, 1)], 'summary' => 'Ada ' . count($cands) . ' dealer yang mirip dengan yang lu sebut. Maksudnya yang mana? Ketuk salah satu di bawah.', 'blocks' => [['t' => 'table', 'title' => 'Dealer yang cocok', 'cols' => ['Kode', 'Nama Dealer', 'Region', 'Kota'], 'rows' => $rows], ['t' => 'chips', 'items' => $chips]]];
}
/* Stok (2026-11-25): type habis di banyak dealer, stok tinggi / numpuk. Data dari apiDOS (cakupan sudah dijaga). */
function aiStock_(array &$x, string $mode, string $brand = '', string $region = '', int $lim = 10): array {
    $get = function (string $st) use (&$x, $brand) { try { return apiDOS($x['token'], $x['period'], $x['sel'], ['view' => 'TYPE', 'brand' => $brand ?: 'ALL', 'status' => $st, 'region' => 'ALL', 'subRegion' => 'ALL', 'type' => 'ALL', 'query' => '', 'pageSize' => 200], 0); } catch (Throwable $e) { return ['ready' => false, 'message' => $e->getMessage(), 'rows' => []]; } };
    $filt = function (array $rows) use (&$x, $region) { if ($region === '') return $rows; $nm = []; foreach (aiCust_($x, $region) as $c) $nm[$c['nama']] = 1; return array_values(array_filter($rows, fn($q) => isset($nm[$q['nama']]))); };
    $rg = $region ? ' · ' . $region : ''; $bt = $brand ? ' · ' . $brand : '';
    if ($mode === 'type_habis') {
        $r = $get('HABIS'); if (empty($r['ready'])) return ['data' => ['error' => $r['message'] ?? ''], 'blocks' => [], 'summary' => (string) ($r['message'] ?? 'Data stok belum siap.')];
        $A = []; foreach ($filt($r['rows'] ?? []) as $q) { $k = $q['brand'] . '|' . $q['type']; $A[$k] ??= ['brand' => $q['brand'], 'type' => $q['type'], 'dealer' => 0, 'so' => 0]; $A[$k]['dealer']++; $A[$k]['so'] += (float) ($q['sellout'] ?? 0); }
        $L = array_values($A); usort($L, fn($a, $b) => [$b['dealer'], $b['so']] <=> [$a['dealer'], $a['so']]); $top = array_slice($L, 0, max($lim, 10));
        return ['data' => ['catatan' => 'Maksimal 200 pasangan dealer-TYPE berstatus HABIS yang dibaca.', 'type_habis' => $top], 'blocks' => [['t' => 'table', 'title' => 'TYPE yang habis di banyak dealer' . $bt . $rg, 'cols' => ['Brand', 'TYPE', 'Dealer habis', 'SO 30h'], 'rows' => array_map(fn($a) => [$a['brand'], $a['type'], aiNum_($a['dealer']), aiNum_($a['so'])], $top)]],
            'summary' => $top ? 'TYPE paling banyak habis: ' . implode(', ', array_map(fn($a) => $a['type'] . ' (' . $a['dealer'] . ' dealer)', array_slice($top, 0, 3))) . '.' : 'Tidak ada TYPE berstatus habis.'];
    }
    $rows = []; foreach (['STOK TINGGI', 'BELUM BERGERAK'] as $st) { $r = $get($st); if (empty($r['ready'])) { if ($st === 'STOK TINGGI') return ['data' => ['error' => $r['message'] ?? ''], 'blocks' => [], 'summary' => (string) ($r['message'] ?? 'Data stok belum siap.')]; continue; } foreach ($filt($r['rows'] ?? []) as $q) $rows[$q['nama'] . '|' . $q['type']] = $q + ['_st' => $st]; }
    $L = array_values($rows); usort($L, fn($a, $b) => (float) $b['stock'] <=> (float) $a['stock']); $diam = count(array_filter($L, fn($q) => (float) ($q['sellout'] ?? 0) <= 0)); $top = array_slice($L, 0, max($lim, 10));
    return ['data' => ['jumlah' => count($L), 'tanpa_penjualan_30h' => $diam, 'teratas' => array_map(fn($q) => ['dealer' => $q['nama'], 'type' => $q['type'], 'stok' => $q['stock'], 'so30' => $q['sellout']], $top)],
        'blocks' => [['t' => 'kpi', 'items' => [['l' => 'Stok numpuk', 'v' => aiNum_(count($L)), 's' => 'pasangan dealer-TYPE', 'c' => '#C25A12'], ['l' => 'Tanpa penjualan', 'v' => aiNum_($diam), 's' => 'SO 30 hari = 0', 'c' => '#E5354E']]], ['t' => 'table', 'title' => 'Stok numpuk' . $bt . $rg . ' (stok terbesar)', 'cols' => ['Dealer', 'TYPE', 'Stok', 'SO 30h', 'DOS'], 'rows' => array_map(fn($q) => [$q['nama'], $q['type'], aiNum_($q['stock']), aiNum_($q['sellout'] ?? 0), isset($q['dos']) && $q['dos'] !== null ? aiNum_($q['dos']) : '—'], $top)]],
        'summary' => count($L) . ' pasangan dealer-TYPE stoknya tinggi atau belum bergerak, ' . $diam . ' di antaranya tidak ada penjualan 30 hari.'];
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
    if ($m('/transaksi|nota|rincian order|order apa aja/') && !$m('/dealer transaksi|jumlah transaksi|berapa dealer|kumulatif|per tanggal|tiap hari/')) { $dd = ''; if (preg_match('/tanggal\\s*(\\d{1,2})|tgl\\s*(\\d{1,2})/', $q, $dm)) $dd = '@D' . ((int) ($dm[1] ?: $dm[2])); elseif ($m('/kemarin/')) $dd = date('Y-m-d', strtotime('-1 day')); elseif ($m('/hari ini/')) $dd = date('Y-m-d'); if (!$dd && $m('/seluruh|sebulan|bulan ini|satu bulan|semua tanggal|all/')) $dd = 'ALL'; return ['transactions', ['date' => $dd, 'brand' => $b, 'by' => $m('/per sales|tiap sales|semua sales|setiap sales|all sales/') ? 'sales' : ($m('/per region|per wilayah|tiap region|tiap wilayah|semua region|semua wilayah|all region/') ? 'region' : ''), 'limit' => $m('/semua|all|lengkap|seluruh/') ? 300 : 0]]; }
    if ($m('/(dealer aktif|\\bda\\b|noo)/') && $m('/(per tanggal|tanggal(?!\\s*\\d)|tgl(?!\\s*\\d)|tiap hari|per hari|harian|kumulatif|perkembangan|progres|naik)/')) { $nn = preg_match('/(\\d+)\\s*(hari|tanggal)/', $q, $mm) ? (int) $mm[1] : 10; return ['da_noo_cumulative', ['brand' => $b, 'days' => $nn]]; }
    if ($m('/(\\d+|seminggu|satu minggu|kemarin|hari ini|harian|per hari|tanggal|tgl)/') && $m('/penjualan|sell ?thru|\\bst\\b|order|omzet|omset|jual|noo|dealer transaksi|dealer aktif|brand/') && $m('/hari terakhir|\\d+ hari|seminggu|7 hari|kemarin|hari ini|harian|per hari|tanggal \\d|tgl \\d/') && !$m('/hari apa|target|run ?rate|butuh/')) { $dd = ''; $nn = 7; if (preg_match('/tanggal\\s*(\\d{1,2})|tgl\\s*(\\d{1,2})/', $q, $dm)) $dd = '@D' . ((int) ($dm[1] ?: $dm[2])); elseif ($m('/kemarin/')) $dd = date('Y-m-d', strtotime('-1 day')); elseif ($m('/hari ini/')) $dd = date('Y-m-d'); elseif (preg_match('/(\\d+)\\s*hari/', $q, $mm)) $nn = (int) $mm[1]; return ['daily_sales', $dd ? ['date' => $dd] : ['days' => $nn]]; }
    if ($m('/(type|tipe).*(habis|kosong)|(habis|kosong).*(type|tipe)|banyak dealer.*habis/')) return ['stock_insight', ['mode' => 'type_habis', 'brand' => $b]];
    if ($m('/numpuk|stok (tinggi|berlebih|banyak)|belum bergerak|stok.*(gak|ga|tidak) (jalan|laku|jual)/')) return ['stock_insight', ['mode' => 'numpuk', 'brand' => $b]];
    if ($m('/bandingin|banding|vs |versus|dibanding(kan)? dengan/') && !$m('/lmtd|bulan lalu/')) return ['ranking', ['mode' => 'compare']];
    if ($m('/ranking|peringkat|urutan|terbaik|terbawah|paling bagus|paling jelek/') && !$m('/brand|type|tipe/')) return ['ranking', ['mode' => $m('/region|wilayah/') ? 'region' : 'sales']];
    if ($m('/region.*(bagus|jelek|terbaik|terlemah)/')) return ['ranking', ['mode' => 'region']];
    if ($m('/fokus|prioritas minggu|harus ngapain|saran|gimana (biar|supaya)|biar tembus/')) return ['focus_plan', []];
    if ($m('/hari apa|per hari dalam|minggu ini.*minggu lalu|hari (tertinggi|terendah|ramai|sepi)/')) return ['day_pattern', []];
    if ($m('/dealer.*(hilang|lost|berhenti)|(hilang|lost|berhenti).*dealer|(dulu|rutin).*order.*belum|bulan lalu order/')) return ['dealer_insight', ['mode' => 'lost']];
    if ($m('/dealer.*(turun|menurun|anjlok)|(turun|menurun|anjlok).*dealer|paling turun/')) return ['dealer_insight', ['mode' => 'drop']];
    if ($m('/top\\s*\\d*\\s*dealer|dealer\\s*top|dealer\\s*\\d*\\s*(terbesar|terbaik|teratas)|(terbesar|terbaik|teratas).*dealer|dealer.*paling (besar|banyak)/')) return ['dealer_insight', ['mode' => 'top']];
    if ($m('/konsentrasi|ketergantungan|porsi.*dealer|persen.*top/')) return ['dealer_insight', ['mode' => 'concentration']];
    if ($m('/cross|belum order brand lain|belum order semua brand/')) return ['dealer_insight', ['mode' => 'cross', 'brand' => $b]];
    if ($m('/kpi/') && $m('/tim|semua|per sales|setiap|tiap|ranking|terendah|tertinggi|siapa/')) return ['kpi_team', []];
    if ($m('/kpi/')) return ['kpi_detail', []];
    if ($m('/potensi|dealer aktif|cross/')) return ['dealer_potential', ['brand' => $b]];
    if ($m('/prioritas|kunjungi|kunjungan/')) return ['dealer_priority', []];
    if ($m('/belum order|belum transaksi|nggak order|tidak order/')) return ['dealer_not_order', ['brand' => $b]];
    if ($m('/stok|dos|menipis|restock|habis/')) return ['dos_risk', ['brand' => $b, 'status' => $m('/habis/') ? 'HABIS' : ($m('/tinggi/') ? 'STOK TINGGI' : 'MENIPIS')]];
    if ($m('/bahaya|early|tertinggal|siapa/')) return ['early_warning', ['brand' => $b]];
    if ($m('/noo|dealer baru/') && ($b || $m('/per brand|tiap brand|setiap brand|brand/'))) return ['brand_type', ['brand' => $b]];
    if ($m('/kontribusi|brand terbaik|brand terlemah|on track|numpuk|type .*(laku|teratas)|(laku|teratas).*type/') || ($b && $m('/type|tipe/'))) return ['brand_type', ['brand' => $b]];
    if ($m('/(detail|rinci|lengkap|semua).*(brand|capai)|(brand|capai).*(detail|rinci|lengkap)/') && !$m('/ketinggalan/')) return ['brand_type', ['brand' => $b]];
    if ($m('/ketinggalan|per brand|capaian brand|brand mana/')) return ['brand_achievement', []];
    if ($m('/type|laku|terlaris/')) return ['top_type', ['brand' => $b]];
    if ($m('/proyeksi|tembus|target|capaian|performa|ringkas|run ?rate|per hari|harian|dealer aktif|\\bda\\b|noo|omzet|omset|sell ?out|\\bso\\b|sisa hari|hari kerja/') && !$m('/per minggu|tren|trend|potensi/')) return ['perf_summary', []];
    if ($m('/minggu|tren|trend/')) return ['weekly_trend', []];
    return ['', []];
}

/* ---------- Gemini ---------- */
/* Model cadangan bila model utama sibuk (503/500/429). Bisa diatur lewat DSM_GEMINI_FALLBACK, kosong = tanpa cadangan. */
function aiModelFb_(): string { return defined('DSM_GEMINI_FALLBACK') ? (string) DSM_GEMINI_FALLBACK : 'gemini-3.5-flash-lite'; }
$GLOBALS['AI_MODEL_USED'] = null;
/** Panggil Gemini dengan coba ulang: model utama 2x (jeda 1 dtk), lalu model cadangan 1x. Hanya untuk error sementara (429/500/503/timeout). */
function aiGemini_(array $body): array {
    $models = array_values(array_unique(array_filter([aiModel_(), aiModel_(), aiModelFb_()])));
    if (count($models) === 1) $models[] = $models[0];
    $last = null;
    foreach ($models as $i => $mdl) {
        try { $j = aiGeminiOnce_($body, $mdl); $GLOBALS['AI_MODEL_USED'] = $mdl; return $j; }
        catch (RuntimeException $e) { $last = $e; if (!preg_match('/^(Gemini error (429|500|502|503|504)|Kuota Gemini|Tidak bisa menghubungi)/', $e->getMessage())) throw $e; if ($i < count($models) - 1) usleep(900000); }
    }
    throw $last;
}
function aiGeminiOnce_(array $body, string $mdl): array {
    $url = (defined('DSM_GEMINI_BASE') ? (string) DSM_GEMINI_BASE : 'https://generativelanguage.googleapis.com/v1beta') . '/models/' . rawurlencode($mdl) . ':generateContent';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . aiGeminiKey_()], CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE)]);
    $res = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($res === false) throw new RuntimeException('Tidak bisa menghubungi Gemini: ' . $err);
    $j = json_decode((string) $res, true);
    if ($code === 429) throw new RuntimeException('Kuota Gemini habis sementara (429).');
    if ($code >= 400 || !is_array($j)) throw new RuntimeException('Gemini error ' . $code . ': ' . substr((string) ($j['error']['message'] ?? $res), 0, 160));
    return $j;
}
function aiSysPrompt_(array $x, array $brands): string {
    return "Kamu DSM AI, asisten data penjualan distribusi. Jawab dalam Bahasa Indonesia yang santai dan jelas, maksimal 3 kalimat.\n"
        . "ATURAN: semua angka HARUS berasal dari hasil fungsi. Jangan menghitung atau mengarang angka. Tabel dan grafik sudah ditampilkan otomatis oleh aplikasi dari hasil fungsi, jadi cukup beri kesimpulan dan sorotan, jangan mengulang semua angka.\n"
        . "Jika pertanyaan tidak bisa dijawab oleh fungsi yang ada, katakan terus terang belum bisa. Jangan menebak penyebab; hanya tunjukkan pola dari data. Kalau brand tidak jelas untuk fungsi yang butuh brand, tanyakan balik.\n"
        . "Jika user menyebut wilayah (mis. Jawa Timur, Jabar), isi parameter region dengan nama wilayah itu; jika user menyebut jumlah (mis. 30 dealer) isi limit dengan angka itu. Jika user menyebut nama Sales/ASM/RGM, isi person dengan nama itu (jangan isi region). Wilayah yang ada: " . implode(', ', array_slice(aiRegionList_($x), 0, 60)) . ".\n"
        . "Brand yang ada: " . implode(', ', $brands) . ". Periode " . $x['period'] . "."
        . ((function_exists('aiKnowText_') && ($kt = aiKnowText_()) !== '') ? "\nPENGETAHUAN PERUSAHAAN dari Super Admin (ikuti bila relevan; tetap jangan mengarang angka):\n" . $kt : '');
}
/** Pintu masuk LLM: OpenAI bila dipilih; bila gagal dan kunci Gemini ada, jatuh ke Gemini. */
function aiLlm_(string $q, array $context, array &$x, array $brands, array &$blocks, ?string &$intent): string {
    if (aiProvider_() === 'openai') {
        try { return aiLlmOpenai_($q, $context, $x, $brands, $blocks, $intent); }
        catch (Throwable $e) { if (aiGeminiKey_() === '') throw $e; $blocks = []; $intent = null; }
    }
    return aiLlmGemini_($q, $context, $x, $brands, $blocks, $intent);
}
function aiOpenai_(array $body): array {
    $url = (defined('DSM_OPENAI_BASE') ? rtrim((string) DSM_OPENAI_BASE, '/') : 'https://api.openai.com/v1') . '/chat/completions';
    $last = null;
    for ($try = 0; $try < 3; $try++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . aiOpenaiKey_()], CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE)]);
        $res = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($res === false) { $last = new RuntimeException('Tidak bisa menghubungi OpenAI: ' . $err); usleep(900000); continue; }
        $j = json_decode((string) $res, true);
        if (in_array($code, [429, 500, 502, 503, 504], true)) { $last = new RuntimeException('OpenAI error ' . $code . ': ' . substr((string) ($j['error']['message'] ?? ''), 0, 120)); usleep(900000); continue; }
        if ($code >= 400 || !is_array($j)) throw new RuntimeException('OpenAI error ' . $code . ': ' . substr((string) ($j['error']['message'] ?? $res), 0, 160));
        $GLOBALS['AI_MODEL_USED'] = aiOpenaiModel_();
        return $j;
    }
    throw $last ?: new RuntimeException('OpenAI error');
}
function aiLlmOpenai_(string $q, array $context, array &$x, array $brands, array &$blocks, ?string &$intent): string {
    $tools = [];
    foreach (aiTools_() as $t) $tools[] = ['type' => 'function', 'function' => ['name' => $t['name'], 'description' => $t['description'], 'parameters' => ['type' => 'object', 'properties' => $t['parameters'] ? $t['parameters'] : new stdClass()]]];
    $msgs = [['role' => 'system', 'content' => aiSysPrompt_($x, $brands)]];
    foreach ($context as $m) { $tx = trim((string) ($m['text'] ?? '')); if ($tx === '') continue; $msgs[] = ['role' => ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user', 'content' => mb_substr($tx, 0, 600)]; }
    $msgs[] = ['role' => 'user', 'content' => $q];
    for ($round = 0; $round < 3; $round++) {
        $j = aiOpenai_(['model' => aiOpenaiModel_(), 'messages' => $msgs, 'tools' => $tools, 'max_completion_tokens' => 1200]);
        $m = $j['choices'][0]['message'] ?? [];
        $calls = $m['tool_calls'] ?? [];
        if (!$calls) { $text = trim((string) ($m['content'] ?? '')); if ($text === '') throw new RuntimeException('OpenAI tidak memberi jawaban.'); return $text; }
        $msgs[] = ['role' => 'assistant', 'content' => $m['content'] ?? null, 'tool_calls' => $calls];
        foreach ($calls as $c) {
            $name = (string) ($c['function']['name'] ?? ''); $args = json_decode((string) ($c['function']['arguments'] ?? '{}'), true); if (!is_array($args)) $args = [];
            try { $r = aiRun_($name, $args, $x); } catch (Throwable $e) { $r = ['data' => ['error' => $e->getMessage()], 'blocks' => [], 'summary' => '']; }
            $intent = $intent ?: $name; foreach ($r['blocks'] as $b) $blocks[] = $b;
            $msgs[] = ['role' => 'tool', 'tool_call_id' => (string) ($c['id'] ?? ''), 'content' => mb_substr(json_encode(['hasil' => $r['data']], JSON_UNESCAPED_UNICODE), 0, 24000)];
        }
    }
    throw new RuntimeException('OpenAI terlalu banyak langkah.');
}
function aiLlmGemini_(string $q, array $context, array &$x, array $brands, array &$blocks, ?string &$intent): string {
    $decl = []; foreach (aiTools_() as $t) { $f = ['name' => $t['name'], 'description' => $t['description']]; if ($t['parameters']) $f['parameters'] = ['type' => 'object', 'properties' => $t['parameters']]; $decl[] = $f; }
    $sys = aiSysPrompt_($x, $brands);
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
    return ['model' => aiKey_() !== '' ? aiShownModel_() : 'Mode dasar', 'role' => $u['role'], 'groups' => AI_GROUPS, 'geminiOn' => aiKey_() !== ''];
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
    $qRaw = $q; $x = aiCtx_($token, $period, $sel); $brands = array_map(fn($b) => $b['brand'], aiDash_($x)['brands'] ?? []);
    try { $q = aiFollowUp_($q, $context, $brands, $x); } catch (Throwable $e) { $q = $qRaw; }
    $avail = []; try { $avail = apiBootstrap($token)['periods'] ?? []; } catch (Throwable $e) {}
    $pi = aiPeriodIn_($q, $period, $avail); $periodNote = '';
    if ($pi['missing'] !== '') { return ['intent' => 'PERIOD_MISSING', 'answer' => 'Data periode ' . $pi['missing'] . ' belum tersedia di DSM. Periode yang ada: ' . implode(', ', array_slice($avail, 0, 6)) . '.', 'blocks' => [], 'follow' => [], 'scope' => $sel, 'model' => 'Mode dasar', 'fallbackReason' => null]; }
    if ($pi['p'] !== '') { $period = $pi['p']; $periodNote = '[Periode ' . $period . '] '; $q = trim(str_ireplace($pi['phrase'], '', $q)) ?: $q; $x = aiCtx_($token, $period, $sel); $brands = array_map(fn($b) => $b['brand'], aiDash_($x)['brands'] ?? []); }
    $x['q'] = $q;
    $blocks = []; $intent = null; $text = ''; $model = 'Mode dasar'; $fallback = null;
    $rule = function_exists('aiRuleMatch_') ? aiRuleMatch_($q) : null; // arahan Super Admin (Ajari AI) didahulukan
    if ($rule) {
        try { $r = aiRun_($rule['target'], aiArgsFor_($rule['target'], $q, $x, $brands), $x); $text = $r['summary']; $blocks = $r['blocks']; $intent = $rule['target']; $model = 'Arahan Super Admin'; aiRuleUsed_((int) $rule['id']); }
        catch (Throwable $e) { $rule = null; }
    }
    if ($rule) {}
    elseif (aiKey_() !== '') {
        try { $text = aiLlm_($q, is_array($context) ? array_slice($context, -6) : [], $x, $brands, $blocks, $intent); $model = $GLOBALS['AI_MODEL_USED'] ?: aiShownModel_();
            if (function_exists('aiKnowHit_')) aiKnowHit_($q);
            if (!$intent && preg_match('/belum bisa (gue |saya |aku )?(jawab|menjawab)|tidak bisa (menjawab|dijawab)|belum (tersedia|ada data)/iu', $text)) $intent = 'UNSUPPORTED'; }
        catch (Throwable $e) { $fallback = $e->getMessage(); $blocks = []; $intent = null; }
    } else { $fallback = 'Kunci Gemini belum dipasang'; }
    if ($model === 'Mode dasar') {
        [$fn, $args] = aiRoute_($q, $brands);
        if (in_array($fn, ['', 'perf_summary', 'dealer_insight'], true) && $m_d = preg_match('/dealer|toko|outlet|customer|detail/i', $q)) { $dc = aiDealerCands_($q, $x, $brands); if ($dc && (!empty($dc[0]['exact']) || $dc[0]['score'] >= 2 || $fn === '' || preg_match('/(dealer|toko|outlet)\\s+\\S+/i', $q)) && !preg_match('/(yang|semua|top|paling|berapa dealer|dealer aktif|belum order|hilang|turun|konsentrasi|cross)/i', $q)) { $fn = 'dealer_insight'; $args = ['mode' => 'detail', 'dealer' => $q]; } }
        if ($fn === 'transactions' && ($dc2 = aiDealerCands_($q, $x, $brands)) && !aiPersonFind_($q, $x['token'], true) && (aiRegionIn_($q, $x) === '' || $dc2[0]['score'] >= 2 || preg_match('/dealer|toko|outlet/i', $q))) $args['dealer'] = $q;
        if ($fn === 'ranking' && ($args['mode'] ?? '') === 'compare') { $two = []; foreach (aiPeople_($x['token']) as $pp) { $n = mb_strtoupper((string) ($pp['nama'] ?? '')); if ($n !== '' && strpos($n, 'VACANT') !== 0 && preg_match('/(?<![A-Z0-9])' . preg_quote($n, '/') . '(?![A-Z0-9])/u', mb_strtoupper($q))) $two[] = $pp['nama']; } $args['person'] = $two[0] ?? ''; $args['person2'] = $two[1] ?? ''; }
        if ($fn !== 'ranking' && in_array($fn, ['top_type', 'weekly_trend', 'perf_summary', 'brand_type', 'kpi_detail', 'kpi_team', 'daily_sales', 'transactions', 'da_noo_cumulative', 'stock_insight', 'dealer_insight', 'focus_plan', 'ranking', 'day_pattern', 'brand_achievement', 'dealer_potential', 'dealer_not_order', 'dealer_priority', 'dos_risk', 'early_warning'], true)) { $pe = aiPersonFind_($q, $x['token'], true); if (!$pe) { $cd = aiPersonCands_($q, $x['token'], $brands); if (count($cd) === 1) $pe = $cd[0]; elseif (count($cd) > 1) $pick = aiPersonPick_($cd, $q); } if ($pe) $args['person'] = (string) $pe['nama']; elseif (empty($pick)) { $rg = aiRegionIn_($q, $x); if ($rg !== '') $args['region'] = $rg; else { $rc = aiRegionCands_($q, $x, $brands); if (count($rc['names']) === 1) $args['region'] = $rc['names'][0]; elseif (count($rc['names']) > 1) $pick = aiRegionPick_($rc['names'], $rc['tok'], $q); elseif (($ut = aiUnknownTok_($q, $brands)) !== '') $pick = aiAskBack_($ut, $q, $x); } } $lm = aiLimitIn_($q); if ($lm > 0) $args['limit'] = $lm; }
        if ($fn === '' && $fallback && preg_match('/(429|500|502|503|504)|Kuota|menghubungi/', $fallback)) { $text = 'AI sedang sibuk (server Gemini lagi penuh). Coba kirim ulang sebentar lagi, atau pakai salah satu pertanyaan contoh yang tetap bisa dijawab.'; $intent = 'BUSY'; }
        elseif ($fn === '' && function_exists('aiKnowHit_') && ($kh = aiKnowHit_($q))) { $text = $kh['title'] . ': ' . $kh['body']; $intent = 'KNOWLEDGE'; }
        elseif ($fn === '') { $text = 'Maaf, pertanyaan itu belum bisa gue jawab. Sudah dicatat supaya bisa ditambahkan. Coba salah satu pertanyaan contoh.'; $intent = 'UNSUPPORTED'; }
        elseif (!empty($pick)) { $text = $pick['summary']; $blocks = $pick['blocks']; $intent = 'PICK'; }
        else { $r = aiRun_($fn, $args, $x); $text = $r['summary']; $blocks = $r['blocks']; $intent = $fn; }
    }
    $blocks = array_slice($blocks, 0, 4); if ($periodNote !== '') $text = $periodNote . $text;
    $ms = (int) round((microtime(true) - $t0) * 1000);
    aiAudit_($u, $qRaw, (string) $intent, $sel, count($blocks), $model, $intent === 'UNSUPPORTED' ? 'UNSUPPORTED' : ($fallback ? 'FALLBACK' : 'OK'), $ms);
    $follow = AI_FOLLOW[$intent] ?? [];
    return ['intent' => $intent ?: 'PERFORMANCE_SUMMARY', 'answer' => $text, 'blocks' => $blocks, 'follow' => $follow, 'scope' => $sel, 'model' => $model, 'fallbackReason' => $model === 'Mode dasar' ? $fallback : null];
}
