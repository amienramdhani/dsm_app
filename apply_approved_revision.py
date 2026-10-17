#!/usr/bin/env python3
from pathlib import Path
import shutil, sys

ROOT = Path(sys.argv[1] if len(sys.argv) > 1 else ".").resolve()
PACK = Path(__file__).resolve().parent
changed=[]

def replace_once(rel, old, new, label):
    p=ROOT/rel
    s=p.read_text(encoding="utf-8")
    if new in s:
        print(f"[skip] {label}")
        return
    if old not in s:
        raise SystemExit(f"[STOP] pola tidak ditemukan: {label} ({rel})")
    bak=p.with_suffix(p.suffix+".dsmrev.bak")
    if not bak.exists(): shutil.copy2(p,bak)
    p.write_text(s.replace(old,new,1),encoding="utf-8")
    changed.append(rel)
    print(f"[ok] {label}")

def copy_new(rel):
    src=PACK/rel; dst=ROOT/rel
    dst.parent.mkdir(parents=True,exist_ok=True)
    if dst.exists() and dst.read_bytes()==src.read_bytes():
        print(f"[skip] {rel}"); return
    if dst.exists():
        bak=dst.with_suffix(dst.suffix+".dsmrev.bak")
        if not bak.exists(): shutil.copy2(dst,bak)
    shutil.copy2(src,dst);changed.append(rel);print(f"[ok] {rel}")

for rel in [
    "lib/portfolio_core.php","lib/api_portfolio.php",
    "assets/portfolio_transfer.js","assets/admin_revision.js","assets/admin_revision.css"
]:
    copy_new(rel)

# dealer_brand.php — schedule future assignment only for orchestrated portfolio transfer.
replace_once("lib/dealer_brand.php",
"""function dbaSet_(array $db, array $u, string $dealer, string $brand, string $nik, string $from, string $note, string $source = 'MANUAL'): array {""",
"""function dbaSet_(array $db, array $u, string $dealer, string $brand, string $nik, string $from, string $note, string $source = 'MANUAL', bool $allowFuture = false): array {""",
"dbaSet allowFuture")
replace_once("lib/dealer_brand.php",
"""    if (!$from) fail_('Tanggal efektif wajib YYYY-MM-DD.'); if (strcmp($from, jkt_date('Y-m-d')) > 0) fail_('Tanggal efektif masa depan belum didukung.');""",
"""    if (!$from) fail_('Tanggal efektif wajib YYYY-MM-DD.'); if (!$allowFuture && strcmp($from, jkt_date('Y-m-d')) > 0) fail_('Tanggal efektif masa depan belum didukung.');""",
"dbaSet future guard")

# publish.php — core hook, checkpoint, meta owner and raw->snapshot re-attribution.
replace_once("lib/publish.php",
"""require_once __DIR__ . '/common.php';
require_once __DIR__ . '/api_exec.php';""",
"""require_once __DIR__ . '/common.php';
require_once __DIR__ . '/portfolio_core.php';
require_once __DIR__ . '/api_exec.php';""",
"publish require portfolio core")
replace_once("lib/publish.php",
"""'Target_Big', '_Auth'];""",
"""'Target_Big', 'Portfolio_Exit', '_Auth'];""",
"checkpoint Portfolio_Exit")
replace_once("lib/publish.php",
"""    $s = settings_(); $meta = PortalCore::normalize(['sales' => table_('Master_Sales'), 'customers' => table_('Master_Customer'), 'products' => table_('Master_Product')], $s);""",
"""    $s = settings_();
    $salesRows = portfolioFilterSalesRows_(table_('Master_Sales'), $s['roleSchema'] ?? 2);
    $meta = PortalCore::normalize(['sales' => $salesRows, 'customers' => table_('Master_Customer'), 'products' => table_('Master_Product')], $s);""",
"meta filter secondary Sales")
replace_once("lib/publish.php",
"""    return applyDealerContacts_($meta);""",
"""    return applyDealerContacts_(portfolioApplyMetaOwners_($meta));""",
"meta effective dealer owner")
replace_once("lib/publish.php",
"""    $tx = nikResolveRows_($meta, custResolveRows_($meta, $tx)); $soRows = custResolveRows_($meta, $soRows);""",
"""    $tx = nikResolveRows_($meta, custResolveRows_($meta, $tx));
    $tx = portfolioResolveRows_($meta, $tx); // raw tetap utuh; ownership diterapkan hanya ke snapshot
    $soRows = custResolveRows_($meta, $soRows);""",
"snapshot portfolio attribution")

# core.php — historical targets must not block removal of an old Sales portfolio.
replace_once("lib/core.php",
"""            if (!isset($pids[$nik]) || !$pids[$nik]['sales']) fail_('Target hanya untuk portofolio Sales: ' . $nik);""",
"""            if (!isset($pids[$nik])) fail_('Target mengacu ke NIK yang tidak ada: ' . $nik);
            if (!$pids[$nik]['sales'] && strcmp($p, date('Y-m')) >= 0) fail_('Target aktif/future hanya untuk portofolio Sales: ' . $nik);""",
"historical target validation")

# api_import.php — prevent unsafe manual uncheck when dealer/active target remains.
replace_once("lib/api_import.php",
"""        $acting = truthy($input['acting'] ?? null) && in_array($role, ['ASM', 'RGM'], true);
        $portfolio = text_($input['portfolio'] ?? null) ?: $region;
        if ($acting) { $pw = null; foreach ($rm as $r) if (js_str($r['REGION'] ?? null) === $portfolio) { $pw = $r['WILAYAH_KPI'] ?? null; break; } if ($pw !== $willRegion) fail_('Portofolio rangkap harus satu big region.'); }""",
"""        $acting = truthy($input['acting'] ?? null) && in_array($role, ['ASM', 'RGM'], true);
        $portfolio = text_($input['portfolio'] ?? null) ?: $region;
        $was = PortalCore::person($db['meta']['people'], $nik);
        if (!$acting && $was && in_array($was['role'], ['ASM','RGM'], true) && truthy($was['sales'] ?? false)) {
            $work = portfolioHasActiveWork_($db, $nik);
            if ($work['has']) fail_('Portofolio Sales masih memiliki ' . $work['dealers'] . ' dealer dan ' . $work['targets'] . ' target aktif/future. Gunakan Alihkan Portofolio agar dealer, target, dan aktual berpindah dalam satu proses.');
        }
        if ($acting) portfolioClearExit_($nik);
        if ($acting) { $pw = null; foreach ($rm as $r) if (js_str($r['REGION'] ?? null) === $portfolio) { $pw = $r['WILAYAH_KPI'] ?? null; break; } if ($pw !== $willRegion) fail_('Portofolio rangkap harus satu big region.'); }""",
"safe acting portfolio toggle")

# api.php — route portfolio endpoints.
replace_once("api.php",
"""require_once __DIR__ . '/lib/api_kpikhusus.php';
require_once __DIR__ . '/lib/api_admin2.php';""",
"""require_once __DIR__ . '/lib/api_kpikhusus.php';
require_once __DIR__ . '/lib/api_portfolio.php';
require_once __DIR__ . '/lib/api_admin2.php';""",
"api require portfolio")
replace_once("api.php",
"""const DSM_ALLOWED = ['apiNotifState'""",
"""const DSM_ALLOWED = ['apiPortfolioTransferPreview','apiPortfolioTransferCommit','apiNotifState'""",
"api allow portfolio")
replace_once("api.php",
"""const DSM_WRITE_FNS = ['apiNotifSend'""",
"""const DSM_WRITE_FNS = ['apiPortfolioTransferCommit','apiNotifSend'""",
"api write portfolio")

# index.php — progressive enhancement assets only; existing page logic stays intact.
replace_once("index.php",
"""<link rel=\"stylesheet\" href=\"assets/telemarketing/tm_order_mockup.css?v=<?=@filemtime(__DIR__.'/assets/telemarketing/tm_order_mockup.css')?>\">""",
"""<link rel=\"stylesheet\" href=\"assets/telemarketing/tm_order_mockup.css?v=<?=@filemtime(__DIR__.'/assets/telemarketing/tm_order_mockup.css')?>\">
<link rel=\"stylesheet\" href=\"assets/admin_revision.css?v=<?=@filemtime(__DIR__.'/assets/admin_revision.css')?>\">""",
"index admin revision css")
replace_once("index.php",
"""<script src=\"assets/kpikhusus.js?v=<?=@filemtime(__DIR__.'/assets/kpikhusus.js')?>\"></script>""",
"""<script src=\"assets/kpikhusus.js?v=<?=@filemtime(__DIR__.'/assets/kpikhusus.js')?>\"></script>
<script src=\"assets/portfolio_transfer.js?v=<?=@filemtime(__DIR__.'/assets/portfolio_transfer.js')?>\"></script>
<script src=\"assets/admin_revision.js?v=<?=@filemtime(__DIR__.'/assets/admin_revision.js')?>\"></script>""",
"index admin revision js")

# KPI Khusus backend: explicit "ada SO tapi stok 0".
replace_once("lib/api_kpikhusus.php",
"""function kkDefaultRule_(): array { return ['tiers' => [[0, 10], [60, 0], [91, -5]], 'stok0' => 0, 'noSo' => -5]; }""",
"""function kkDefaultRule_(): array { return ['tiers' => [[0, 10], [60, 0], [91, -5]], 'stok0' => 0, 'soStock0' => 0, 'noSo' => -5]; }""",
"DOS default SO+stock0")
replace_once("lib/api_kpikhusus.php",
"""    foreach (['stok0' => 'Stok 0', 'noSo' => 'Ada stok tanpa SO'] as $k => $lbl) {""",
"""    foreach (['stok0' => 'Stok 0', 'soStock0' => 'Ada SO tapi stok 0', 'noSo' => 'Ada stok tanpa SO'] as $k => $lbl) {""",
"DOS clean SO+stock0")
replace_once("lib/api_kpikhusus.php",
"""        $GLOBALS['KK_RULE'][$period] = is_array($r) && isset($r['tiers']) ? $r : kkDefaultRule_();""",
"""        if (is_array($r) && isset($r['tiers'])) {
            if (!array_key_exists('soStock0', $r)) $r['soStock0'] = 0;
            $GLOBALS['KK_RULE'][$period] = $r;
        } else $GLOBALS['KK_RULE'][$period] = kkDefaultRule_();""",
"DOS legacy rule normalize")
replace_once("lib/api_kpikhusus.php",
"""function kkRuleMax_(array $rule): float { $m = 0.0; foreach ($rule['tiers'] as $t) $m = max($m, (float) $t[1]); return max($m, (float) $rule['stok0'], (float) $rule['noSo']); }""",
"""function kkRuleMax_(array $rule): float { $m = 0.0; foreach ($rule['tiers'] as $t) $m = max($m, (float) $t[1]); return max($m, (float) $rule['stok0'], (float) ($rule['soStock0'] ?? 0), (float) $rule['noSo']); }""",
"DOS rule max")
replace_once("lib/api_kpikhusus.php",
"""function kkDosPoints_($stock, $so, int $days, array $rule): array {
    if ($stock <= 0) return ['dos' => 0.0, 'points' => (float) $rule['stok0'], 'cond' => 'HABIS'];""",
"""function kkDosPoints_($stock, $so, int $days, array $rule): array {
    if ($stock <= 0 && $so > 0) return ['dos' => 0.0, 'points' => (float) ($rule['soStock0'] ?? 0), 'cond' => 'HABIS'];
    if ($stock <= 0) return ['dos' => 0.0, 'points' => (float) $rule['stok0'], 'cond' => 'HABIS'];""",
"DOS points SO+stock0")

# KPI frontend mirrors backend field.
replace_once("assets/kpikhusus.js",
"""const toUi = r => ({ tiers: (r.tiers || []).map(t => Array.isArray(t) ? { from: t[0], pts: t[1] } : { from: t.from, pts: t.pts }), stok0: r.stok0, noSo: r.noSo });""",
"""const toUi = r => ({ tiers: (r.tiers || []).map(t => Array.isArray(t) ? { from: t[0], pts: t[1] } : { from: t.from, pts: t.pts }), stok0: r.stok0, soStock0: r.soStock0 ?? 0, noSo: r.noSo });""",
"KPI JS toUi SO+stock0")
replace_once("assets/kpikhusus.js",
"""const ruleMax = r => Math.max(0, ...r.tiers.map(t => +t.pts || 0), +r.stok0 || 0, +r.noSo || 0);""",
"""const ruleMax = r => Math.max(0, ...r.tiers.map(t => +t.pts || 0), +r.stok0 || 0, +r.soStock0 || 0, +r.noSo || 0);""",
"KPI JS rule max")
replace_once("assets/kpikhusus.js",
"""      <tr><td colspan=\"2\"><b>Stok 0</b></td><td><input class=\"fi\" type=\"number\" step=\"0.5\" value=\"${rule.stok0}\" ${ro ? 'disabled' : ''} data-f=\"tsp\" data-s=\"${scope}\" data-k=\"stok0\"></td><td></td></tr>
      <tr><td colspan=\"2\"><b>Ada stok tanpa SO</b></td>""",
"""      <tr><td colspan=\"2\"><b>Stok 0</b></td><td><input class=\"fi\" type=\"number\" step=\"0.5\" value=\"${rule.stok0}\" ${ro ? 'disabled' : ''} data-f=\"tsp\" data-s=\"${scope}\" data-k=\"stok0\"></td><td></td></tr>
      <tr><td colspan=\"2\"><b>Ada SO tapi stok 0</b></td><td><input class=\"fi\" type=\"number\" step=\"0.5\" value=\"${rule.soStock0 ?? 0}\" ${ro ? 'disabled' : ''} data-f=\"tsp\" data-s=\"${scope}\" data-k=\"soStock0\"></td><td></td></tr>
      <tr><td colspan=\"2\"><b>Ada stok tanpa SO</b></td>""",
"KPI JS rule table SO+stock0")
replace_once("assets/kpikhusus.js",
"""if (i.m === 'DOS' && i.ruleMode === 'custom') o.rule = { tiers: i.rule.tiers.map(t => ({ from: +t.from, pts: +t.pts })), stok0: +i.rule.stok0, noSo: +i.rule.noSo };""",
"""if (i.m === 'DOS' && i.ruleMode === 'custom') o.rule = { tiers: i.rule.tiers.map(t => ({ from: +t.from, pts: +t.pts })), stok0: +i.rule.stok0, soStock0: +(i.rule.soStock0 ?? 0), noSo: +i.rule.noSo };""",
"KPI JS custom payload")
replace_once("assets/kpikhusus.js",
"""{ tiers: RL.draft.tiers.map(t => ({ from: +t.from, pts: +t.pts })), stok0: +RL.draft.stok0, noSo: +RL.draft.noSo }""",
"""{ tiers: RL.draft.tiers.map(t => ({ from: +t.from, pts: +t.pts })), stok0: +RL.draft.stok0, soStock0: +(RL.draft.soStock0 ?? 0), noSo: +RL.draft.noSo }""",
"KPI JS rule save")

print("\nSelesai. File berubah:")
for x in changed: print(" -",x)
print("\nBackup file existing memakai suffix .dsmrev.bak")
