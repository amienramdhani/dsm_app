<?php
/**
 * PortalCore — aturan bisnis murni (terjemahan 1:1 dari Core.gs).
 * DA, NOO, target, skor KPI, dan isi laporan Beranda dihitung di sini.
 */
require_once __DIR__ . '/js.php';

final class PortalCore {
    const LEVEL = ['SALES' => 1, 'ASM' => 2, 'RGM' => 3, 'ADMIN' => 3.5, 'HEAD OF SALES' => 4, 'CHIEF OPERATING OFFICER' => 5, 'CHIEF COMMERCIAL OFFICER' => 5, 'BOARD OF DIRECTOR' => 6, 'SUPER ADMIN' => 7];

    /** Versi data orang; menghapus cache saat snapshot berganti. */
    public static string $ver = '';
    private static array $memo = [];

    public static function memo(string $bucket, string $key, callable $make) {
        $k = self::$ver . '|' . $key;
        if (!isset(self::$memo[$bucket])) self::$memo[$bucket] = [];
        if (!array_key_exists($k, self::$memo[$bucket])) {
            if (count(self::$memo[$bucket]) > 5000) self::$memo[$bucket] = [];
            self::$memo[$bucket][$k] = $make();
        }
        return self::$memo[$bucket][$k];
    }
    public static function resetMemo(string $ver): void { self::$ver = $ver; self::$memo = []; }

    public static function text($v): string { return text_($v); }
    public static function key(array $parts): string { return jkey($parts); }
    public static function round($n) { return js_mround(($n + PHP_FLOAT_EPSILON) * 100) / 100; }
    public static function active($v): bool { return $v === true || in_array(js_upper(text_($v)), ['TRUE', 'YA', '1'], true); }
    public static function lv(?string $role): float { return self::LEVEL[$role ?? ''] ?? 0; }

    public static function number($v) {
        if ((is_int($v) || is_float($v)) && is_finite((float) $v)) return $v;
        $s = preg_replace('/\s/u', '', text_($v));
        if ($s === '') fail_('Nilai angka kosong.');
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = strrpos($s, ',') > strrpos($s, '.') ? str_replace(',', '.', str_replace('.', '', $s)) : str_replace(',', '', $s);
        } elseif (str_contains($s, ',')) $s = preg_replace('/,/', '.', $s, 1);
        if (!preg_match('/^-?\d+(\.\d+)?$/', $s)) fail_('Angka tidak valid: ' . $s);
        $f = (float) $s;
        return (floor($f) == $f && abs($f) < 9007199254740992) ? (int) $f : $f;
    }
    public static function iso($v): string {
        $s = js_slice(text_($v), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) || gmdate('Y-m-d', (int) strtotime($s . 'T00:00:00Z')) !== $s) fail_('Tanggal harus YYYY-MM-DD: ' . $s);
        return $s;
    }
    public static function month($v): string { if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', text_($v))) fail_('Periode harus YYYY-MM.'); return text_($v); }
    public static function prev(string $p): string {
        $y = (int) substr($p, 0, 4); $m = (int) substr($p, 5, 2) - 1;
        if ($m < 1) { $m = 12; $y--; }
        return sprintf('%04d-%02d', $y, $m);
    }
    public static function end(string $p): string {
        $y = (int) substr($p, 0, 4); $m = (int) substr($p, 5, 2);
        return sprintf('%04d-%02d-%02d', $y, $m, cal_days_in_month_($y, $m));
    }

    public static function position($value, $schema): string {
        $role = js_upper(text_($value));
        if ((int) $schema === 1) $role = ['SPV' => 'ASM', 'SUPERVISOR' => 'ASM', 'ASM' => 'RGM'][$role] ?? $role;
        return ['COO' => 'CHIEF OPERATING OFFICER', 'CCO' => 'CHIEF COMMERCIAL OFFICER', 'CHIEF COMERCIAL OFFICER' => 'CHIEF COMMERCIAL OFFICER', 'BOD' => 'BOARD OF DIRECTOR', 'BOARD OF DIRECTORS' => 'BOARD OF DIRECTOR'][$role] ?? $role;
    }
    public static function adaptMeta(array &$meta): array {
        if ((int) ($meta['roleSchema'] ?? 0) === 2) return $meta;
        foreach ($meta['people'] as &$p) $p['role'] = self::position($p['role'], 1);
        unset($p);
        $meta['roleSchema'] = 2;
        return $meta;
    }

    // --------------------------------------------------------------
    // Orang & cakupan
    // --------------------------------------------------------------

    /** Indeks NIK -> orang (people.find(p=>p.nik===x)). */
    public static function personIndex(array $people): array {
        return self::memo('pidx', (string) count($people) . ':' . ($people[0]['nik'] ?? ''), function () use ($people) {
            $ix = [];
            foreach ($people as $p) { $k = (string) $p['nik']; if (!isset($ix[$k])) $ix[$k] = $p; }
            return $ix;
        });
    }
    public static function person(array $people, $nik): ?array {
        if ($nik === null) return null;
        return self::personIndex($people)[(string) $nik] ?? null;
    }

    public static function scope(array $people, $nik): array {
        $nik = (string) $nik;
        return self::memo('scope', $nik . '#' . count($people), function () use ($people, $nik) {
            $root = self::person($people, $nik);
            if (!$root) fail_('Pengguna tidak ada dalam master.');
            if ($root['role'] === 'ADMIN') {
                $ar = $root['adminRegions'] ?? [];
                $o = [];
                foreach ($people as $p) if ($p['nik'] === $nik || (in_array($p['role'], ['SALES', 'ASM', 'RGM'], true) && in_array($p['wilayah'] ?? null, $ar, true))) $o[] = $p['nik'];
                return $o;
            }
            if (self::lv($root['role']) >= 4) return array_map(fn($p) => $p['nik'], $people);
            $set = [$nik => true]; $list = [$nik]; $changed = true;
            while ($changed) {
                $changed = false;
                foreach ($people as $p) {
                    $a = $p['atasan'] ?? '';
                    if ($a !== '' && $a !== null && isset($set[(string) $a]) && !isset($set[(string) $p['nik']])) { $set[(string) $p['nik']] = true; $list[] = $p['nik']; $changed = true; }
                }
            }
            return $list;
        });
    }

    // --------------------------------------------------------------
    // Potongan data (cube)
    // --------------------------------------------------------------

    public static function vid(array $db): string { return (string) ($db['_vid'] ?? $db['id'] ?? ''); }
    public static function idsKey(array $ids): string { return md5(implode("\x1f", $ids)); }

    public static function slice(array $db, string $period, array $ids, $cut = null): array {
        $key = self::vid($db) . '|' . $period . '|' . self::idsKey($ids) . '|' . ($cut ?? '');
        return self::memo('slice', $key, function () use ($db, $period, $ids, $cut) {
            $set = js_set($ids); $o = [];
            foreach ($db['cubes'][$period] ?? [] as $r) if (isset($set[(string) $r['nik']]) && (!truthy($cut) || strcmp($r['date'], $cut) <= 0)) $o[] = $r;
            return $o;
        });
    }
    public static function sliceCmp(array $db, string $period, array $ids, $cut = null, $sel = null): array {
        if (truthy($sel)) {
            $ks = js_keys($db['coverage'] ?? []); sort($ks, SORT_STRING);
            if ($sel !== js_last($ks)) return self::slice($db, $period, $ids, $cut);
        }
        $key = self::vid($db) . '|' . $period . '|' . self::idsKey($ids) . '|' . ($cut ?? '');
        return self::memo('sliceCmp', $key, function () use ($db, $period, $ids, $cut) {
            $set = js_set($ids); $cu = $db['meta']['customers'] ?? []; $o = [];
            foreach ($db['cubes'][$period] ?? [] as $r) {
                if (truthy($cut) && strcmp($r['date'], $cut) > 0) continue;
                $c = $cu[$r['customer']] ?? null; $n = $c['nik'] ?? null;
                if (truthy($n) && $n !== $r['nik']) { $r['nik'] = $n; }
                if (isset($set[(string) $r['nik']])) $o[] = $r;
            }
            return $o;
        });
    }

    public static function metrics(array $rows, array $db, string $period, $brand = null, $minDA = 2, $minNOO = 1): array {
        $q = 0; $a = 0; $acc = 0; $pairs = []; $pairOf = []; $docs = []; $customers = []; $byPair = [];
        $all = !truthy($brand) || $brand === 'ALL';
        foreach ($rows as $r) {
            if (!$all && $r['brand'] !== $brand) continue;
            $q += $r['q']; $a += $r['a']; $acc += $r['acc'];
            foreach ($r['docs'] as $d) $docs[$d] = true;
            // kunci internal murah; kunci format JS (jkey) dibuat sekali per pasangan saat dibutuhkan
            $ik = $r['customer'] . "\x1f" . $r['brand'];
            if (!isset($pairs[$ik])) { $pairs[$ik] = 0; $pairOf[$ik] = $r; }
            $pairs[$ik] += $r['q'];
            $byPair[$ik][] = $r;
        }
        $da = 0; $noo = 0; $pending = false;
        $first = $db['first'] ?? []; $unl = $db['unlinked'] ?? [];
        foreach ($pairs as $ik => $v) {
            if ($v >= $minDA) $da++;
            if ($v > 0) {
                $pr = $pairOf[$ik];
                $customers[(string) $pr['customer']] = true;
                $k = jkey([$pr['customer'], $pr['brand']]);
                $f = $first[$k] ?? null;
                if (truthy($unl[$k] ?? null)) $pending = true;
                if ($v >= $minNOO && $f && substr($f['date'], 0, 7) === $period) {
                    foreach ($byPair[$ik] as $r) if ($r['nik'] === $f['nik'] && $r['date'] === $f['date']) { $noo++; break; }
                }
            }
        }
        return ['qty' => $q, 'omzet' => self::round($a), 'aksesori' => self::round($acc), 'da' => $da, 'noo' => $noo, 'outlets' => count($customers), 'nota' => count($docs), 'nooPending' => $pending];
    }

    /** Indeks target per (periode, brand, metrik, indikator) agar target() tidak menyapu semua baris. */
    private static function targetIndex(array $meta): array {
        return self::memo('tidx', (string) count($meta['targets'] ?? []), function () use ($meta) {
            $ix = [];
            foreach ($meta['targets'] ?? [] as $t) $ix[$t['period'] . "\x1f" . $t['brand'] . "\x1f" . $t['metric'] . "\x1f" . (string) ($t['indicator'] ?? '')][] = $t;
            return $ix;
        });
    }
    public static function target(array $meta, string $period, array $ids, $brand, string $metric, $indicator = ''): array {
        $eligible = [];
        foreach ($ids as $id) { $p = self::person($meta['people'], $id); if ($p && truthy($p['sales'] ?? false)) $eligible[] = $id; }
        $es = js_set($eligible);
        $found = []; $has = [];
        foreach (self::targetIndex($meta)[$period . "\x1f" . js_str($brand) . "\x1f" . $metric . "\x1f" . (string) ($indicator ?? '')] ?? [] as $t) {
            if (isset($es[(string) $t['nik']])) { $found[] = $t; $has[(string) $t['nik']] = true; }
        }
        $value = 0; foreach ($found as $t) $value += $t['target'];
        $missing = []; foreach ($eligible as $id) if (!isset($has[(string) $id])) $missing[] = $id;
        return ['value' => $value, 'missing' => $missing, 'exists' => count($found) > 0];
    }

    // --------------------------------------------------------------
    // KPI
    // --------------------------------------------------------------

    public static function kpiRgm(array $db, string $period, array $ids, $region, array $cfg): array {
        $all = self::slice($db, $period, $ids); $groups = []; $byReg = null;
        foreach (uniq(array_map(fn($c) => $c['group'], $cfg)) as $group) {
            $weighted = 0; $weights = 0; $pending = false; $indicators = [];
            foreach ($cfg as $c) {
                if ($c['group'] !== $group) continue;
                $bigs = array_map('js_upper', $c['bigs'] ?? []); $types = $c['types'] ?? [];
                if ($bigs) {
                    // Paket 10: aktual = SEMUA penjualan di Big Region terpilih, siapa pun salesnya (bukan hanya tim RGM).
                    $byReg = $byReg ?? rgmRegionRows_($period); $rows = [];
                    foreach (uniq($bigs) as $bg) foreach ($byReg[$bg] ?? [] as $r) $rows[] = $r;
                    if ($types) $rows = js_filter($rows, fn($r) => in_array($r['type'], $types, true));
                } else $rows = js_filter($all, fn($r) => (!$types || in_array($r['type'], $types, true)));
                $a = self::metrics($rows, $db, $period, $c['brand'], js_or($c['minDA'] ?? null, 2), js_or($c['minNOO'] ?? null, 1));
                $actual = ['QTY' => $a['qty'], 'DA' => $a['da'], 'NOO' => $a['noo'], 'OMZET' => $a['omzet']][$c['metric']] ?? null;
                $state = 'BERLAKU'; $score = null; $ach = null; $tg = $c['target'] ?? null;
                // Paket 10: target kosong → otomatis dari target Big Region (manual, kalau tidak ada: jumlah target sales). Hanya QTY/DA brand tertentu tanpa filter type.
                $autoVal = null; $auto = false;
                if ($bigs && !$types && ($c['brand'] ?? 'ALL') !== 'ALL' && (($c['metric'] === 'QTY') || ($c['metric'] === 'DA' && (float) js_or($c['minDA'] ?? null, 2) === 2.0))) {
                    $autoVal = 0.0; foreach (uniq($bigs) as $bg) $autoVal += rbBigTarget_($db['meta'], $period, $bg, $c['brand'], $c['metric']) ?? rbSalesTarget_($db['meta'], $period, $bg, $c['brand'], $c['metric']);
                    if ($tg === null && $autoVal > 0) { $tg = $autoVal; $auto = true; }
                }
                if ($tg === null) { $state = 'TIDAK DIWAJIBKAN'; } // target kosong = tidak ikut hitungan, tanpa peringatan
                elseif ($tg === 0 || $tg === 0.0) $state = 'TIDAK DIWAJIBKAN';
                elseif ($c['metric'] === 'NOO' && $a['nooPending']) { $state = 'VERIFIKASI RETUR'; $pending = true; }
                else { $ach = $actual / $tg * 100; $score = min($c['cap'], max(0, $ach)); $weighted += $score * $c['weight']; $weights += $c['weight']; }
                $indicators[] = ['indicator' => $c['indicator'] ?? null, 'label' => js_or($c['label'] ?? null, $c['metric'] . ' ' . $c['brand'] . (($c['bigs'] ?? []) ? ' · ' . implode(' + ', $c['bigs']) : '')), 'types' => $types, 'bigs' => $c['bigs'] ?? [], 'minDA' => js_or($c['minDA'] ?? null, 2), 'metric' => $c['metric'], 'brand' => $c['brand'], 'weight' => $c['weight'], 'cap' => $c['cap'], 'target' => $tg ?? 0, 'actual' => $actual, 'ach' => $ach, 'score' => $score, 'state' => $state, 'regionBased' => (bool) $bigs, 'targetAuto' => $auto, 'autoValue' => $autoVal, 'manualTarget' => $c['target'] ?? null];
            }
            $groups[] = ['name' => $group, 'score' => $pending || !$weights ? null : $weighted / $weights, 'pending' => $pending, 'na' => !$pending && !$weights, 'indicators' => $indicators];
        }
        $scored = js_filter($groups, fn($g) => !$g['na']); $pending = js_some($scored, fn($g) => $g['pending']);
        $sum = 0; foreach ($scored as $g) $sum += $g['score'];
        return ['region' => $region, 'rgm' => true, 'groups' => $groups, 'score' => $pending || !$scored ? null : $sum / count($scored), 'status' => $pending ? 'BELUM FINAL' : (!$scored ? 'TIDAK DIWAJIBKAN' : 'FINAL'), 'count' => count($scored)];
    }

    public static function kpi(array $db, string $period, array $ids, $region, ?array $owner = null): array {
        if ($owner && ($owner['role'] ?? '') === 'RGM') {
            $rc = js_filter($db['meta']['rgmPolicies'] ?? [], fn($c) => $c['period'] === $period && $c['nik'] === $owner['nik']);
            if ($rc) return self::kpiRgm($db, $period, $ids, $region, $rc);
        }
        $reg = $region; $sub = '';
        $subs = [];
        foreach ($ids as $id) { $p = self::person($db['meta']['people'], $id); if ($p && truthy($p['sales'] ?? false)) $subs[] = js_upper(text_(js_or($p['portfolio'] ?? '', $p['region'] ?? ''))); }
        $subs = uniq($subs);
        if (count($subs) === 1) {
            $own = js_filter($db['meta']['policies'], fn($c) => $c['period'] === $period && js_upper(text_($c['region'])) === $subs[0]);
            if ($own) { $reg = $own[0]['region']; $sub = $reg; }
        }
        $cfg = js_filter($db['meta']['policies'], fn($c) => $c['period'] === $period && $c['region'] === $reg);
        $rows = self::slice($db, $period, $ids); $groups = []; $tentative = false;
        foreach (uniq(array_map(fn($c) => $c['group'], $cfg)) as $group) {
            $weighted = 0; $weights = 0; $pending = false; $indicators = [];
            foreach ($cfg as $c) {
                if ($c['group'] !== $group) continue;
                $types = $c['types'] ?? [];
                $t = self::target($db['meta'], $period, $ids, $c['brand'], $c['metric'], js_or($c['indicator'] ?? null, ''));
                $a = self::metrics($types ? js_filter($rows, fn($r) => in_array($r['type'], $types, true)) : $rows, $db, $period, $c['brand'], js_or($c['minDA'] ?? null, 2), js_or($c['minNOO'] ?? null, 1));
                $actual = ['QTY' => $a['qty'], 'DA' => $a['da'], 'NOO' => $a['noo'], 'OMZET' => $a['omzet']][$c['metric']] ?? null;
                $state = 'BERLAKU'; $score = null; $ach = null;
                if ($t['value'] == 0 && !$t['missing']) $state = 'TIDAK DIWAJIBKAN';
                elseif ($t['value'] > 0) {
                    $ach = $actual / $t['value'] * 100; $score = min($c['cap'], max(0, $ach)); $weighted += $score * $c['weight']; $weights += $c['weight'];
                    if ($c['metric'] === 'NOO' && $a['nooPending']) { $state = 'SEMENTARA · verifikasi retur'; $tentative = true; }
                } else { $state = 'TIDAK DIWAJIBKAN'; }
                $indicators[] = ['indicator' => $c['indicator'] ?? null, 'label' => $c['label'] ?? null, 'types' => $types, 'minDA' => js_or($c['minDA'] ?? null, 2), 'metric' => $c['metric'], 'brand' => $c['brand'], 'weight' => $c['weight'], 'cap' => $c['cap'], 'target' => $t['value'], 'actual' => $actual, 'ach' => $ach, 'score' => $score, 'state' => $state];
            }
            $groups[] = ['name' => $group, 'score' => !$weights ? null : $weighted / $weights, 'pending' => $pending && !$weights, 'partial' => $pending && (bool) $weights, 'na' => !$pending && !$weights, 'indicators' => $indicators];
        }
        $scored = js_filter($groups, fn($g) => !$g['na'] && $g['score'] !== null);
        $pending = !$groups || js_some($groups, fn($g) => $g['pending'] || $g['partial']) || $tentative;
        $sum = 0; foreach ($scored as $g) $sum += $g['score'];
        $status = !$groups ? 'KEBIJAKAN BELUM DIISI' : (!$scored ? (js_some($groups, fn($g) => $g['pending']) ? 'BELUM FINAL' : 'TIDAK DIWAJIBKAN') : ($pending ? 'SEMENTARA' : 'FINAL'));
        return ['region' => $region, 'sub' => $sub, 'groups' => $groups, 'tentative' => $tentative, 'score' => !$scored ? null : $sum / count($scored), 'status' => $status, 'count' => count($scored)];
    }

    // --------------------------------------------------------------
    // Periode pembanding & laporan
    // --------------------------------------------------------------

    public static function comparisonPeriod(array $db, string $p): array {
        $info = $db['coverage'][$p]; $prior = self::prev($p);
        $cutoff = truthy($info['closed'] ?? false) ? self::end($prior) : $prior . '-' . js_pad2(min((int) substr($info['cutoff'], 8), (int) substr(self::end($prior), 8)));
        $pc = $db['coverage'][$prior] ?? null;
        return ['period' => $prior, 'cutoff' => $cutoff, 'available' => (bool) $pc && strcmp($pc['cutoff'], $cutoff) >= 0];
    }

    public static function report(array $db, string $period, array $ids, $region): array {
        self::month($period);
        $coverage = $db['coverage'][$period] ?? null;
        if (!$coverage) fail_('Periode belum tersedia.');
        $rows = self::slice($db, $period, $ids); $pp = self::prev($period);
        $cut = truthy($coverage['closed'] ?? false) ? self::end($pp) : $pp . '-' . js_pad2(min((int) substr($coverage['cutoff'], 8), (int) substr(self::end($pp), 8)));
        $previous = self::sliceCmp($db, $pp, $ids, $cut, $period);
        $total = self::metrics($rows, $db, $period);
        $idSet = js_set($ids);
        $bl = array_map(fn($r) => $r['brand'], $rows);
        foreach ($db['meta']['targets'] ?? [] as $t) if ($t['period'] === $period && isset($idSet[(string) $t['nik']]) && !truthy($t['indicator'] ?? '') && $t['brand'] !== 'ALL') $bl[] = $t['brand'];
        $brands = js_sort_default(uniq($bl));
        $brandRows = array_map(fn($b) => ['brand' => $b, 'current' => self::metrics($rows, $db, $period, $b), 'previous' => self::metrics($previous, $db, $pp, $b), 'target' => self::target($db['meta'], $period, $ids, $b, 'QTY'), 'targetDA' => self::target($db['meta'], $period, $ids, $b, 'DA'), 'targetNOO' => self::target($db['meta'], $period, $ids, $b, 'NOO')], $brands);
        $byDate = []; foreach ($rows as $r) $byDate[$r['date']][] = $r;
        $dates = array_keys($byDate); $dates = array_map('strval', $dates); sort($dates, SORT_STRING);
        $daily = [];
        foreach ($dates as $date) { $dr = $byDate[$date]; $daily[] = array_merge(['date' => $date], self::metrics($dr, $db, $period), ['brands' => array_map(fn($b) => self::metrics($dr, $db, $period, $b)['qty'], $brands)]); }
        $lastDay = (int) substr(self::end($period), 8); $weekly = [];
        foreach ([1, 2, 3, 4, 5] as $w) {
            if (!(($w - 1) * 7 < $lastDay)) continue;
            $rr = js_filter($rows, fn($r) => min(5, (int) ceil(((int) substr($r['date'], 8)) / 7)) === $w);
            $weekly[] = array_merge(['week' => $w], self::metrics($rr, $db, $period), ['brands' => array_map(fn($b) => self::metrics($rr, $db, $period, $b)['qty'], $brands)]);
        }
        $allHistory = [];
        foreach (js_keys($db['cubes']) as $p) if (strcmp($p, $period) <= 0) foreach (self::slice($db, $p, $ids) as $r) $allHistory[] = $r;
        $codes = [];
        foreach ($db['meta']['customers'] as $c) if (isset($idSet[(string) $c['nik']])) $codes[] = (string) $c['id'];
        foreach ($allHistory as $r) $codes[] = (string) $r['customer'];
        $codes = uniq($codes);
        $grp = function (array $list) { $m = []; foreach ($list as $r) $m[(string) $r['customer']][] = $r; return $m; };
        $gCur = $grp($rows); $gOld = $grp($previous); $gHist = $grp($allHistory);
        $ppp = self::prev($pp); $cutDays = iso_days($coverage['cutoff']);
        $customers = [];
        foreach ($codes as $id) {
            $c = $db['meta']['customers'][$id] ?? null; $current = $gCur[$id] ?? []; $old = $gOld[$id] ?? []; $hist = $gHist[$id] ?? [];
            $net = []; foreach ($hist as $r) { $k = substr($r['date'], 0, 7) . "\x1f" . $r['brand']; $net[$k] = ($net[$k] ?? 0) + $r['q']; }
            $history = js_filter($hist, fn($r) => $r['q'] > 0 && $net[substr($r['date'], 0, 7) . "\x1f" . $r['brand']] > 0);
            $periods = uniq(array_map(fn($r) => substr($r['date'], 0, 7), $history));
            $ds = array_map(fn($r) => $r['date'], $history); sort($ds, SORT_STRING); $last = $ds ? end($ds) : null;
            $currentQty = 0; foreach ($current as $r) $currentQty += $r['q'];
            $status = $currentQty >= 2 ? 'AKTIF' : ($currentQty > 0 ? 'BELUM MINIMUM DA' : (in_array($pp, $periods, true) ? 'DA BULAN LALU' : (!$periods ? 'BELUM ORDER' : (in_array($ppp, $periods, true) ? 'IDLE' : 'PASIF'))));
            $customers[] = ['id' => $id, 'nama' => js_or($c['nama'] ?? null, 'Master belum tersedia'), 'nik' => js_or($c['nik'] ?? null, $current[0]['nik'] ?? null), 'region' => $c['region'] ?? '', 'kota' => $c['kota'] ?? '', 'alamat' => $c['alamat'] ?? '', 'hp' => $c['hp'] ?? '', 'status' => $status, 'last' => $last, 'jeda' => $last ? (int) floor($cutDays - iso_days($last)) : null, 'current' => self::metrics($current, $db, $period), 'previous' => self::metrics($old, $db, $pp), 'brands' => array_map(fn($b) => array_merge(['brand' => $b], self::metrics($current, $db, $period, $b)), $brands)];
        }
        $types = [];
        foreach ($rows as $r) { if (!$r['q']) continue; $k = jkey([$r['brand'], $r['type']]); if (!isset($types[$k])) $types[$k] = ['brand' => $r['brand'], 'type' => $r['type'], 'qty' => 0]; $types[$k]['qty'] += $r['q']; }
        $cks = js_keys($db['coverage']); $cks = js_filter($cks, fn($p) => strcmp($p, $period) <= 0); sort($cks, SORT_STRING); $cks = array_slice($cks, -12);
        $trend = [];
        foreach ($cks as $p) {
            $info = $db['coverage'][$p]; $cmp = self::comparisonPeriod($db, $p);
            $now = self::metrics($p === $period ? self::slice($db, $p, $ids) : self::sliceCmp($db, $p, $ids, null, $period), $db, $p);
            $before = self::metrics(self::sliceCmp($db, $cmp['period'], $ids, $cmp['cutoff'], $period), $db, $cmp['period']);
            $trend[] = ['period' => $p, 'qty' => $now['qty'], 'omzet' => $now['omzet'], 'previousQty' => $cmp['available'] ? $before['qty'] : null, 'growth' => $cmp['available'] && $before['qty'] != 0 ? ($now['qty'] - $before['qty']) / $before['qty'] * 100 : null, 'previousPeriod' => $cmp['period'], 'previousCutoff' => $cmp['cutoff'], 'closed' => $info['closed'] ?? false, 'cutoff' => $info['cutoff']];
        }
        return ['period' => $period, 'coverage' => $coverage, 'previousPeriod' => $pp, 'previousCutoff' => $cut, 'previousAvailable' => self::comparisonPeriod($db, $period)['available'], 'total' => $total, 'previous' => self::metrics($previous, $db, $pp), 'brands' => $brandRows, 'daily' => $daily, 'weekly' => $weekly, 'customers' => $customers, 'types' => array_values($types), 'kpi' => truthy($region) ? self::kpi($db, $period, $ids, $region) : null, 'trend' => $trend];
    }

    public static function groupedTransactions(array $rows, array $meta): array {
        $groups = [];
        foreach ($rows as $t) {
            $p = $meta['products'][$t['sku']]; $type = js_or($p['type'] ?? '', js_or($p['nama'] ?? '', $t['sku']));
            $sj = js_or($t['sj'] ?? '', ''); $id = jkey([$t['date'], $t['company'], js_or($sj, 'DOC:' . $t['doc']), $t['customer'], $t['nik'], $p['brand'], $type]);
            if (!isset($groups[$id])) $groups[$id] = ['date' => $t['date'], 'company' => $t['company'], 'sj' => $sj, 'customer' => $t['customer'], 'nik' => $t['nik'], 'nama' => $meta['customers'][$t['customer']]['nama'] ?? $t['customer'], 'brand' => $p['brand'], 'type' => $type, 'qty' => 0, 'amount' => 0, 'sourceRows' => 0, 'deviceQty' => 0];
            $groups[$id]['qty'] += $t['qty']; $groups[$id]['amount'] += $t['amount']; $groups[$id]['deviceQty'] += truthy($p['device'] ?? false) ? $t['qty'] : 0; $groups[$id]['sourceRows']++;
        }
        $out = array_map(function ($g) { $g['amount'] = self::round($g['amount']); return $g; }, array_values($groups));
        return js_sort($out, fn($a, $b) => (-lc($a['date'], $b['date'])) ?: lc($a['company'], $b['company']) ?: lc($a['sj'], $b['sj']) ?: lc($a['customer'], $b['customer']) ?: lc($a['nik'], $b['nik']) ?: lc($a['brand'], $b['brand']) ?: lc($a['type'], $b['type']));
    }

    // --------------------------------------------------------------
    // Publikasi (dipakai publish_ di lib/publish.php) — terjemahan 1:1
    // --------------------------------------------------------------

    /** PortalCore.people(rows, regionRows, roleSchema) — urutan hasil = Object.values(map) versi JS. */
    public static function people(array $rows, array $regionRows, $roleSchema): array {
        $map = []; $rm = [];
        foreach ($regionRows as $r) $rm[text_($r['REGION'] ?? null)] = text_($r['WILAYAH_KPI'] ?? null);
        foreach ($rows as $r) {
            $nik = text_($r['NIK KARYAWAN'] ?? null); $role = self::position($r['POSISI'] ?? null, $roleSchema);
            if ($nik === '' || !isset(self::LEVEL[$role])) fail_('NIK atau posisi tidak valid.');
            $p = ['nik' => $nik, 'nama' => text_($r['SALES'] ?? null), 'role' => $role, 'region' => text_($r['REGION'] ?? null), 'atasan' => text_($r['NIK ATASAN'] ?? null), 'sales' => $role === 'SALES', 'portfolio' => $role === 'SALES' ? text_($r['REGION'] ?? null) : ''];
            if (!isset($map[$nik])) $map[$nik] = $p;
            else {
                $old = $map[$nik];
                if ($old['nama'] !== $p['nama']) fail_('NIK memiliki nama berbeda: ' . $nik);
                if ($p['role'] === $old['role']) fail_('NIK dan role berulang: ' . $nik);
                if (self::LEVEL[$role] > self::LEVEL[$old['role']]) { $p['sales'] = $p['sales'] || $old['sales']; $p['portfolio'] = js_or($p['portfolio'], $old['portfolio']); $map[$nik] = $p; }
                else { $old['sales'] = $old['sales'] || $p['sales']; $old['portfolio'] = js_or($old['portfolio'], $p['portfolio']); $map[$nik] = $old; }
            }
        }
        $out = [];
        foreach (js_keys($map) as $k) {
            $p = $map[$k];
            if ($p['atasan'] === $p['nik']) fail_('Atasan utama menunjuk diri sendiri: ' . $p['nik']);
            if ($p['atasan'] !== '' && !isset($map[$p['atasan']])) fail_('Atasan tidak ditemukan: ' . $p['atasan']);
            $seen = [$p['nik'] => true]; $n = $p['atasan'];
            while (truthy($n)) { if (isset($seen[$n])) fail_('Siklus hierarki: ' . $p['nik']); $seen[$n] = true; $n = isset($map[$n]) ? $map[$n]['atasan'] : null; }
            $p['wilayah'] = js_or($rm[$p['region']] ?? null, js_or($rm[$p['portfolio']] ?? null, ''));
            if (!truthy($p['wilayah']) && self::LEVEL[$p['role']] < 4 && $p['role'] !== 'ADMIN') fail_('Region belum dipetakan di Region_Map: ' . $p['region']);
            $map[$k] = $p; $out[] = $p;
        }
        return $out;
    }

    /** PortalCore.normalize(raw, settings). Map customers/products disusun dalam urutan kunci JS. */
    public static function normalize(array $raw, array $settings): array {
        $warnings = [];
        $ps = self::people($raw['sales'], $settings['regions'], js_or($settings['roleSchema'] ?? null, 2));
        $pids = []; foreach ($ps as $p) if (!isset($pids[$p['nik']])) $pids[$p['nik']] = $p;
        $products = []; $customers = []; $rules = [];
        foreach ($settings['products'] as $r) $rules[text_($r['BRAND'] ?? null)] = ['brand' => js_or(text_($r['BRAND_UTAMA'] ?? null), text_($r['BRAND'] ?? null)), 'device' => self::active($r['MASUK_QTY'] ?? null)];
        foreach ($raw['products'] as $r) {
            $id = text_($r['KODE BARANG'] ?? null); $source = text_($r['BRAND'] ?? null); $rule = $rules[$source] ?? null;
            if ($id === '' || !$rule) fail_('Product_Rules belum lengkap: ' . $source);
            // DSM v5: produk berkategori ACC (mis. Moto Watch) dihitung aksesori, bukan unit HP, walau brand-nya MASUK_QTY.
            $p = ['id' => $id, 'nama' => text_($r['NAMA BARANG'] ?? null), 'brand' => $rule['brand'], 'source' => $source, 'type' => text_($r['TYPE'] ?? null), 'device' => $rule['device'] && js_upper(text_($r['KATEGORI PRODUK'] ?? null)) !== 'ACC'];
            if (isset($products[$id])) {
                if ($products[$id]['brand'] !== $p['brand'] || $products[$id]['device'] !== $p['device']) fail_('Kode produk punya klasifikasi berbeda: ' . $id);
                $warnings[] = 'Nama produk berulang; memakai baris pertama: ' . $id;
            } else $products[$id] = $p;
        }
        foreach ($raw['customers'] as $r) {
            $id = text_($r['KODE CUSTOMER'] ?? null); $nik = text_($r['NIK SALES'] ?? null);
            if ($id === '' || !isset($pids[$nik])) fail_('Kode customer/NIK tidak valid: ' . $id);
            $c = ['id' => $id, 'nama' => js_or(text_($r['NAMA INDUK CUSTOMER'] ?? null), text_($r['NAMA CUSTOMER'] ?? null)), 'nik' => $nik, 'region' => text_($r['REGION'] ?? null), 'kota' => text_($r['KOTA'] ?? null), 'pic' => text_($r['NAMA PIC'] ?? null), 'alamat' => text_($r['ALAMAT'] ?? null), 'hp' => text_($r['No. HP'] ?? null), 'aliases' => [text_($r['ID_DEALER'] ?? null)]];
            if (isset($customers[$id])) {
                if ($customers[$id]['nik'] !== $nik) fail_('Alias outlet berbeda pemegang: ' . $id);
                $customers[$id]['aliases'] = uniq(array_merge($customers[$id]['aliases'], $c['aliases']));
            } else $customers[$id] = $c;
        }
        $targets = []; $tk = [];
        foreach ($settings['targets'] as $r) {
            $p = self::month($r['PERIODE'] ?? null); $nik = text_($r['NIK'] ?? null); $b = text_($r['BRAND'] ?? null); $metric = js_upper(text_($r['METRIK'] ?? null));
            $value = self::number($r['TARGET'] ?? null); $indicator = text_($r['INDIKATOR_ID'] ?? null); $k = jkey([$p, $nik, $b, $metric, $indicator]);
            if (!isset($pids[$nik]) || !$pids[$nik]['sales']) fail_('Target hanya untuk portofolio Sales: ' . $nik);
            if ($b === '' || !in_array($metric, ['QTY', 'DA', 'NOO', 'OMZET'], true) || $value < 0) fail_('Target tidak valid.');
            if (isset($tk[$k])) fail_('Target duplikat: ' . $k);
            $tk[$k] = true;
            $targets[] = ['period' => $p, 'nik' => $nik, 'brand' => $b, 'metric' => $metric, 'target' => $value, 'indicator' => $indicator];
        }
        $policies = []; $pk = [];
        foreach ($settings['kpi'] as $r) {
            if (!self::active($r['AKTIF'] ?? null)) continue;
            $p = ['period' => self::month($r['PERIODE'] ?? null), 'region' => text_($r['WILAYAH_KPI'] ?? null), 'group' => text_($r['KELOMPOK_KPI'] ?? null), 'metric' => js_upper(text_($r['METRIK'] ?? null)), 'brand' => text_($r['BRAND'] ?? null)];
            $p['weight'] = self::number($r['BOBOT'] ?? null); $p['cap'] = self::number($r['BATAS_SKOR'] ?? null);
            $p['indicator'] = text_($r['INDIKATOR_ID'] ?? null); $p['label'] = text_($r['LABEL'] ?? null);
            $p['types'] = uniq(array_values(array_filter(array_map('text_', explode('|', text_($r['TYPE_FILTER'] ?? null))), fn($x) => $x !== '')));
            $p['minDA'] = text_($r['MIN_QTY_DA'] ?? null) !== '' ? self::number($r['MIN_QTY_DA']) : 2;
            $p['minNOO'] = text_($r['MIN_QTY_DA'] ?? null) !== '' ? self::number($r['MIN_QTY_DA']) : 1;
            if ($p['region'] === '' || $p['group'] === '' || $p['brand'] === '' || !in_array($p['metric'], ['QTY', 'DA', 'NOO', 'OMZET'], true) || $p['weight'] <= 0 || $p['cap'] <= 0) fail_('Konfigurasi KPI tidak valid.');
            if (!(is_int($p['minDA']) || (is_float($p['minDA']) && is_finite($p['minDA']) && floor($p['minDA']) == $p['minDA'])) || $p['minDA'] < 1) fail_('MIN_QTY_DA harus bilangan bulat minimal 1.');
            if ($p['types'] && !in_array($p['metric'], ['QTY', 'DA'], true)) fail_('TYPE_FILTER hanya untuk QTY atau DA.');
            if (($p['types'] || ($p['metric'] === 'DA' && $p['minDA'] != 2) || ($p['metric'] === 'NOO' && $p['minNOO'] != 1)) && $p['indicator'] === '') fail_('Isi INDIKATOR_ID untuk aturan khusus.');
            foreach ($p['types'] as $t) {
                $ok = false;
                foreach ($products as $x) if ($x['device'] && $x['type'] === $t && ($p['brand'] === 'ALL' || $x['brand'] === $p['brand'])) { $ok = true; break; }
                if (!$ok) fail_('TYPE_FILTER tidak ditemukan pada brand perangkat.');
            }
            $k = jkey([$p['period'], $p['region'], $p['group'], $p['metric'], $p['brand'], $p['indicator']]);
            if (isset($pk[$k])) fail_('Indikator KPI duplikat: ' . $k);
            $pk[$k] = true; $policies[] = $p;
        }
        $grouped = [];
        foreach ($policies as $p) { $k = jkey([$p['period'], $p['region'], $p['group']]); $grouped[$k] = ($grouped[$k] ?? 0) + $p['weight']; }
        foreach ($grouped as $k => $v) if (abs($v - 100) > 0.0001) fail_('Bobot indikator per kelompok harus 100: ' . $k . ' = ' . js_str($v));
        return ['roleSchema' => 2, 'people' => $ps, 'customers' => self::jsOrdered($customers), 'products' => self::jsOrdered($products), 'targets' => $targets, 'policies' => $policies, 'warnings' => uniq($warnings)];
    }

    /** Susun ulang map PHP agar foreach mengikuti urutan Object.keys versi JS (kunci indeks naik dulu). */
    public static function jsOrdered(array $a): array {
        $hasInt = false; foreach ($a as $k => $_) if (is_int($k)) { $hasInt = true; break; }
        if (!$hasInt) return $a;
        $o = []; foreach (js_keys($a) as $k) $o[$k] = $a[$k];
        return $o;
    }

    /** PortalCore.transactions(rows, meta, period, cutoff) */
    public static function transactions(array $rows, array $meta, $period = null, $cutoff = null): array {
        $seen = []; $pids = []; foreach ($meta['people'] as $p) $pids[$p['nik']] = true;
        $result = [];
        foreach (array_values($rows) as $i => $r) {
            $t = ['date' => self::iso($r['TANGGAL TRANSAKSI'] ?? null), 'company' => text_($r['BADAN USAHA'] ?? null), 'doc' => text_($r['NO TRANSAKSI'] ?? null), 'sj' => text_($r['NOMOR SJ'] ?? null), 'sku' => text_($r['KODE PRODUK'] ?? null), 'customer' => text_($r['KODE CUSTOMER'] ?? null), 'nik' => text_($r['NIK SALES'] ?? null)];
            $t['qty'] = self::number($r['QTY'] ?? null); $t['amount'] = self::number($r['AMOUNT'] ?? null);
            $t['line'] = text_($r['ID_BARIS'] ?? null); $t['ref'] = text_($r['REF_TRANSAKSI'] ?? null);
            if (truthy($period) && substr($t['date'], 0, 7) !== $period) fail_('Baris ' . ($i + 2) . ' berada di luar bulan ' . $period . ' (tanggal ' . $t['date'] . ')');
            if (truthy($cutoff) && strcmp($t['date'], $cutoff) > 0) fail_('Tanggal transaksi melewati cutoff impor.');
            if (!isset($meta['products'][$t['sku']])) fail_('KODE PRODUK ' . $t['sku'] . ' tidak ada di Master Product (baris ' . ($i + 2) . ')');
            if (!isset($pids[$t['nik']])) fail_('NIK SALES ' . js_or($t['nik'], '(kosong)') . ' tidak dikenal & dealer ' . $t['customer'] . ' tidak ditemukan di Master Customer (baris ' . ($i + 2) . ')');
            if ($t['doc'] === '' || $t['customer'] === '') fail_('Referensi transaksi tidak valid pada baris ' . ($i + 2) . ' (NO TRANSAKSI / KODE CUSTOMER kosong)');
            $q = $t['qty'];
            if (!(is_int($q) || (is_float($q) && is_finite($q) && floor($q) == $q)) || $q == 0 || !is_finite((float) $t['amount'])) fail_('Qty/amount tidak valid pada baris ' . ($i + 2));
            if (($q < 0 && $t['amount'] > 0) || ($q > 0 && $t['amount'] < 0)) fail_('Tanda qty dan amount tidak konsisten pada baris ' . ($i + 2));
            $k = $t['line'] !== '' ? jkey([$t['company'], $t['doc'], $t['line']]) : jkey([$t['date'], $t['company'], $t['doc'], $t['sj'], $t['sku'], $t['customer'], $t['nik'], $t['qty'], $t['amount']]);
            if (isset($seen[$k])) fail_('Baris transaksi duplikat ' . ($i + 2) . '. Jika baris sah terpisah, sertakan ID_BARIS unik.');
            $seen[$k] = true; $result[] = $t;
        }
        return $result;
    }

    /** PortalCore.build(meta, tx, coverage) → {meta,cubes,coverage,first,unlinked,life,notes}. */
    public static function build(array $meta, array $tx, $coverage): array {
        $cubes = []; $notes = $meta['warnings'] ?? []; $orders = []; $unlinked = []; $first = []; $life = []; $missing = [];
        $peopleIds = []; foreach ($meta['people'] as $p) $peopleIds[$p['nik']] = true;
        $products = $meta['products']; $custs = $meta['customers'];
        foreach ($tx as $t) {
            $p = $products[$t['sku']] ?? null;
            if (!$p) fail_('Produk tidak ditemukan dalam histori: ' . js_str($t['sku']));
            if (!isset($peopleIds[$t['nik']])) fail_('NIK historis hilang dari master: ' . js_str($t['nik']) . '. Nonaktifkan akun, jangan hapus master.');
            $period = substr($t['date'], 0, 7); $region = js_or($t['region'] ?? null, '');
            $k = jkey([$t['date'], $t['nik'], $t['customer'], $p['brand'], $p['type'], $region]);
            if (!isset($cubes[$period][$k])) $cubes[$period][$k] = ['date' => $t['date'], 'region' => $region, 'nik' => $t['nik'], 'customer' => $t['customer'], 'brand' => $p['brand'], 'type' => $p['type'], 'q' => 0, 'a' => 0, 'acc' => 0, 'docs' => []];
            $c = &$cubes[$period][$k];
            $c['q'] += $p['device'] ? $t['qty'] : 0; $c['a'] += $t['amount']; $c['acc'] += $p['device'] ? 0 : $t['amount'];
            $c['docs'][jkey([$t['company'], $t['doc']])] = 1;
            unset($c);
            if (!isset($custs[$t['customer']])) $missing[$t['customer']] = true;
            if (!$p['device']) continue;
            $pair = jkey([$t['customer'], $p['brand']]); $ok = jkey([$t['company'], $t['doc'], $t['customer'], $p['brand']]);
            if ($t['qty'] > 0) {
                if (!isset($orders[$ok])) $orders[$ok] = ['pair' => $pair, 'date' => $t['date'], 'nik' => $t['nik'], 'q' => 0, 'doc' => $t['doc'], 'company' => $t['company']];
                $orders[$ok]['q'] += $t['qty'];
                if (strcmp((string) $t['nik'], (string) $orders[$ok]['nik']) < 0) $orders[$ok]['nik'] = $t['nik'];
            } elseif (!truthy($t['ref'] ?? null)) $unlinked[$pair] = true;
            $cu = $t['customer'];
            if (!isset($life[$cu][$period])) $life[$cu][$period] = ['q' => 0, 'last' => ''];
            $life[$cu][$period]['q'] += $t['qty'];
            if ($t['qty'] > 0 && strcmp($t['date'], $life[$cu][$period]['last']) > 0) $life[$cu][$period]['last'] = $t['date'];
        }
        foreach ($tx as $t) {
            if ($t['qty'] >= 0 || !truthy($t['ref'] ?? null) || !$products[$t['sku']]['device']) continue;
            $b = $products[$t['sku']]['brand'];
            $pair = jkey([$t['customer'], $b]); $ok = jkey([$t['company'], $t['ref'], $t['customer'], $b]);
            if (isset($orders[$ok])) $orders[$ok]['q'] += $t['qty']; else $unlinked[$pair] = true;
        }
        // Urut tanggal → doc → nik (localeCompare). Tanggal ISO: localeCompare setara strcmp.
        $list = []; foreach ($orders as $o) if ($o['q'] > 0) $list[] = $o;
        unset($orders);
        $col = js_collator('en_US');
        if ($col) foreach ($list as &$o) { $o['_d'] = $col->getSortKey(js_str($o['doc'])); $o['_n'] = $col->getSortKey(js_str($o['nik'])); }
        unset($o);
        usort($list, $col
            ? fn($a, $b) => strcmp($a['date'], $b['date']) ?: strcmp($a['_d'], $b['_d']) ?: strcmp($a['_n'], $b['_n'])
            : fn($a, $b) => lc($a['date'], $b['date']) ?: lc($a['doc'], $b['doc']) ?: lc($a['nik'], $b['nik']));
        foreach ($list as $o) if (!isset($first[$o['pair']])) $first[$o['pair']] = ['date' => $o['date'], 'nik' => $o['nik']];
        unset($list);
        if ($missing) $notes[] = 'Customer belum ada di master: ' . implode(', ', js_keys($missing)) . '. Transaksi tetap masuk total; lengkapi master.';
        if ($unlinked) $notes[] = count($unlinked) . ' pasangan customer–brand memiliki retur tanpa referensi yang dapat diverifikasi. NOO terkait belum final.';
        foreach ($cubes as $per => $map) {
            $rows = [];
            foreach ($map as $r) { $r['docs'] = array_map('strval', array_keys($r['docs'])); $r['a'] = self::round($r['a']); $r['acc'] = self::round($r['acc']); $rows[] = $r; }
            $cubes[$per] = $rows;
        }
        return ['meta' => $meta, 'cubes' => $cubes, 'coverage' => $coverage, 'first' => $first, 'unlinked' => $unlinked, 'life' => $life, 'notes' => $notes];
    }
}

function cal_days_in_month_(int $y, int $m): int { return (int) gmdate('t', gmmktime(0, 0, 0, $m, 1, $y)); }
