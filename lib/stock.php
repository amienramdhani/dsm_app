<?php
/** StockCore — sell out & stok (monitoring, tidak masuk KPI). Terjemahan StockCore.gs. */
require_once __DIR__ . '/core.php';

final class StockCore {
    const BASE = '2025-12-01';

    public static function shift(string $d, int $n): string { return days_iso(iso_days($d) + $n); }

    public static function complete(array $cov, string $from, string $to): bool {
        for ($d = $from; strcmp($d, $to) <= 0;) {
            $p = substr($d, 0, 7); $e = PortalCore::end($p); $last = strcmp($e, $to) < 0 ? $e : $to;
            if (!isset($cov[$p]) || strcmp($cov[$p]['cutoff'], $last) < 0) return false;
            $d = self::shift($last, 1);
        }
        return true;
    }

    public static function canSee(array $db, array $ids, $customer): bool {
        $owner = $db['stock']['owners'][$customer] ?? null;
        if (truthy($owner)) return in_array($owner, $ids, true) || in_array((string) $owner, array_map('strval', $ids), true);
        return self::allPeople($db, $ids);
    }
    private static function allPeople(array $db, array $ids): bool {
        $s = js_set($ids);
        foreach ($db['meta']['people'] as $p) if (!isset($s[(string) $p['nik']])) return false;
        return true;
    }

    public static function monthly(array $db, string $period, array $ids, $customer = null, $brand = null, $type = null): array {
        $st = $db['stock'] ?? null;
        if (!$st || !isset($st['coverage'][$period])) return ['qty' => null, 'cutoff' => null, 'complete' => false];
        $sc = $st['coverage'][$period]['cutoff']; $dc = $db['coverage'][$period]['cutoff'];
        $cutoff = strcmp($sc, $dc) < 0 ? $sc : $dc;
        // Indeks per pemilik dealer dibuat sekali per (view, periode, cutoff); indeks per tim = gabungan pemilik.
        $byOwner = PortalCore::memo('soown', PortalCore::vid($db) . '|' . $period . '|' . $cutoff, function () use ($db, $st, $period, $cutoff) {
            $o = [];
            foreach (stockMoves_($db, $period . '-01', $cutoff) as $t) {
                if (substr($t['date'], 0, 7) !== $period || strcmp($t['date'], $cutoff) > 0) continue;
                $owner = $st['owners'][$t['customer']] ?? null; $ok = truthy($owner) ? 'o:' . $owner : '-';
                $p = $db['meta']['products'][$t['sku']];
                $ty = js_or($p['type'] ?? '', js_or($p['nama'] ?? '', $t['sku']));
                foreach (['', (string) $t['customer']] as $c) foreach (['', (string) $p['brand']] as $b) foreach (uniq(['', $ty]) as $y) {
                    $k = $c . "\x1f" . $b . "\x1f" . $y; $o[$ok][$k] = ($o[$ok][$k] ?? 0) + $t['outQty'];
                }
            }
            return $o;
        });
        $index = PortalCore::memo('soidx', PortalCore::vid($db) . '|' . $period . '|' . PortalCore::idsKey($ids) . '|' . $cutoff, function () use ($db, $ids, $byOwner) {
            $allowed = js_set($ids); $all = self::allPeople($db, $ids); $index = [];
            foreach ($byOwner as $ok => $map) {
                if ($ok === '-' ? !$all : !isset($allowed[substr($ok, 2)])) continue;
                foreach ($map as $k => $v) $index[$k] = ($index[$k] ?? 0) + $v;
            }
            return $index;
        });
        return ['qty' => $index[js_str_or_empty($customer) . "\x1f" . js_str_or_empty($brand) . "\x1f" . js_str_or_empty($type)] ?? 0, 'cutoff' => $cutoff, 'complete' => $cutoff === $db['coverage'][$period]['cutoff']];
    }

    /** StockCore.parse(rows, meta, period, cutoff) */
    public static function parse(array $rows, array $meta, $period = null, $cutoff = null): array {
        $out = [];
        foreach (array_values($rows) as $i => $r) {
            $x = ['date' => PortalCore::iso($r['TANGGAL SO'] ?? null), 'customer' => text_($r['KODE CUSTOMER'] ?? null), 'sku' => text_($r['KODE PRODUK'] ?? null), 'qty' => PortalCore::number($r['QTY'] ?? null)];
            if ($x['customer'] === '' || !isset($meta['products'][$x['sku']])) fail_('SO baris ' . ($i + 2) . ': dealer kosong atau produk tidak dikenal ' . $x['sku']);
            $q = $x['qty'];
            if (!(is_int($q) || (is_float($q) && is_finite($q) && floor($q) == $q)) || $q < 0) fail_('SO harus QTY bulat nonnegatif. Koreksi melalui penggantian periode.');
            if (truthy($period) && substr($x['date'], 0, 7) !== $period) fail_('SO berada di luar bulan ' . $period);
            if (truthy($cutoff) && strcmp($x['date'], $cutoff) > 0) fail_('SO melewati cutoff ' . $cutoff);
            $out[] = $x;
        }
        return $out;
    }

    /** StockCore.build(meta, tx, so) → {moves, owners, coverage, notes, base}. */
    public static function build(array $meta, array $tx, array $so): array {
        $owners = []; $candidates = []; $notes = [];
        foreach ($meta['customers'] as $c) $owners[$c['id']] = $c['nik'];
        foreach ($tx as $t) $candidates[$t['customer']][(string) $t['nik']] = $t['nik'];
        foreach (js_keys($candidates) as $id) {
            if (!truthy($owners[$id] ?? null)) {
                if (count($candidates[$id]) === 1) { $owners[$id] = reset($candidates[$id]); $notes[] = 'Dealer ' . $id . ' belum di master; pemilik sementara dari Sell Thru.'; }
                else $notes[] = 'Dealer ' . $id . ' belum memiliki pemilik tunggal; hanya terlihat nasional.';
            }
        }
        unset($candidates);
        $map = [];
        $add = function (array $t, string $kind) use (&$map, $meta) {
            $p = $meta['products'][$t['sku']] ?? null;
            if (!$p) fail_('Produk histori SO tidak ada di master: ' . js_str($t['sku']));
            if (!$p['device']) return;
            $region = js_or($t['region'] ?? null, '');
            $k = jkey([$t['date'], $t['customer'], $t['sku'], $region]);
            if (!isset($map[$k])) $map[$k] = ['date' => $t['date'], 'region' => $region, 'customer' => $t['customer'], 'sku' => $t['sku'], 'inQty' => 0, 'outQty' => 0];
            $map[$k][$kind] += $t['qty'];
        };
        foreach ($tx as $t) $add($t, 'inQty');
        $soRows = $so['rows'] ?? [];
        foreach ($soRows as $t) $add($t, 'outQty');
        $early = 0; foreach ($soRows as $t) if (strcmp($t['date'], self::BASE) < 0 && $t['qty'] != 0) $early++;
        if ($early) $notes[] = $early . ' baris SO sebelum baseline ' . self::BASE . '; tidak dikurangkan dari stok dan ditandai perlu cek.';
        $noted = []; foreach ($notes as $n) $noted[$n] = true;
        foreach ($map as $m) {
            $id = $m['customer'];
            if (!truthy($owners[$id] ?? null)) { $msg = 'Dealer ' . js_str($id) . ' belum terpetakan; hanya terlihat nasional.'; if (!isset($noted[$msg])) { $notes[] = $msg; $noted[$msg] = true; } }
        }
        return ['moves' => array_values($map), 'owners' => $owners, 'coverage' => js_or($so['coverage'] ?? null, []), 'notes' => $notes, 'base' => self::BASE];
    }

    public static function rule(array $db, $region): array {
        $rules = $db['dosRules'] ?? [];
        return js_find($rules, fn($r) => $r['region'] === $region) ?? js_find($rules, fn($r) => $r['region'] === 'ALL') ?? ['min' => 7, 'max' => 45];
    }

    public static function calculate(array $db, string $period, array $ids, $filter = null): array {
        PortalCore::month($period);
        if (!isset($db['coverage'][$period])) fail_('Periode tidak tersedia.');
        $st = $db['stock'] ?? null; $soCut = $st['coverage'][$period]['cutoff'] ?? null; $stCut = $db['coverage'][$period]['cutoff'];
        if (!$soCut) return ['ready' => false, 'message' => 'Sell Out bulan ini belum dipublikasikan.', 'rows' => [], 'regions' => [], 'total' => null, 'stCut' => $stCut, 'soCut' => null];
        $asof = strcmp($soCut, $stCut) < 0 ? $soCut : $stCut; $from = self::shift($asof, -29);
        // Keputusan: bulan/region tanpa data dianggap 0 (tidak ada jualan), tidak lagi memblokir DOS. $zeroAssumed hanya info.
        $zeroAssumed = !(($db['historyComplete'] ?? null) !== false && self::complete($db['coverage'], self::BASE, $asof) && self::complete($st['coverage'], self::BASE, $asof)) || !self::complete($st['coverage'], $from, $asof);
        $covered = true;
        $windowReady = strcmp($from, self::BASE) >= 0;
        $skus = []; $people = $db['meta']['people'];
        // DSM v5: saldo per dealer × SKU dihitung MySQL (tidak memuat seluruh mutasi ke RAM).
        foreach (stockBalance_($db, $asof, $from) as $t) {
            if (!self::canSee($db, $ids, $t['customer'])) continue;
            $k = jkey([$t['customer'], $t['sku']]); $p = $db['meta']['products'][$t['sku']]; $owner = $st['owners'][$t['customer']] ?? null;
            $person = PortalCore::person($people, $owner); $customer = $db['meta']['customers'][$t['customer']] ?? null;
            $region = js_or($person['portfolio'] ?? null, js_or($person['region'] ?? null, js_or($customer['region'] ?? null, 'Belum terpetakan')));
            $big = js_or($person['wilayah'] ?? null, 'Belum terpetakan');
            if (!isset($skus[$k])) $skus[$k] = ['customer' => $t['customer'], 'nama' => js_or($customer['nama'] ?? null, $t['customer'] . ' (belum di master)'), 'nik' => js_or($owner, ''), 'sales' => js_or($person['nama'] ?? null, 'Belum terpetakan'), 'region' => $region, 'big' => $big, 'brand' => $p['brand'], 'type' => js_or($p['type'] ?? '', js_or($p['nama'] ?? '', $t['sku'])), 'stock' => 0, 'sellout' => 0, 'early' => false];
            if ($t['early']) $skus[$k]['early'] = true;
            $skus[$k]['stock'] += $t['stock'];
            $skus[$k]['sellout'] += $t['sellout'];
        }
        $groups = [];
        foreach ($skus as $r) {
            $k = jkey([$r['customer'], $r['brand'], $r['type']]);
            if (!isset($groups[$k])) $groups[$k] = array_merge($r, ['stock' => 0, 'sellout' => 0, 'bad' => false, 'reasons' => []]);
            if ($r['early']) $groups[$k]['reasons'][] = 'SO sebelum baseline Desember 2025';
            if ($r['stock'] < 0) $groups[$k]['reasons'][] = 'Selisih negatif pada kode barang';
            if (!truthy($r['nik'])) $groups[$k]['reasons'][] = 'Pemilik dealer belum terpetakan';
            $groups[$k]['stock'] += $r['stock']; $groups[$k]['sellout'] += $r['sellout'];
            $groups[$k]['bad'] = $groups[$k]['bad'] || $r['early'] || $r['stock'] < 0 || !truthy($r['nik']);
        }
        $rows = [];
        foreach ($groups as $r) {
            $limits = self::rule($db, $r['big']); $avg = $windowReady ? $r['sellout'] / 30 : null;
            $dos = $covered && !$r['bad'] && $avg > 0 ? $r['stock'] / $avg : null;
            $status = !$covered || !$windowReady || $r['bad'] ? 'PERLU CEK DATA' : ($r['stock'] == 0 ? ($r['sellout'] > 0 ? 'HABIS' : 'TIDAK AKTIF') : ($r['sellout'] == 0 ? 'BELUM BERGERAK' : ($dos < $limits['min'] ? 'MENIPIS' : ($dos > $limits['max'] ? 'STOK TINGGI' : 'CUKUP'))));
            $reasons = array_merge($r['reasons'], !$covered ? ['Histori ST/SO belum lengkap'] : [], !$windowReady ? ['Data 30 hari belum lengkap'] : []);
            $soKecil = $dos !== null && $dos >= 1000;
            if ($soKecil) $reasons[] = 'SO terlalu kecil (DOS ribuan hari)';
            $rows[] = array_merge($r, ['reason' => implode('; ', uniq($reasons)), 'avg' => $avg, 'dos' => $dos, 'status' => $status, 'soKecil' => $soKecil]);
        }
        $filter = $filter ?? [];
        $q = isset($filter['query']) && truthy($filter['query']) ? js_lower(js_str($filter['query'])) : null;
        $rows = js_filter($rows, fn($r) => (!truthy($filter['region'] ?? null) || $filter['region'] === 'ALL' || $r['big'] === $filter['region']) && (!truthy($filter['subRegion'] ?? null) || $filter['subRegion'] === 'ALL' || $r['region'] === $filter['subRegion']) && (!truthy($filter['brand'] ?? null) || $filter['brand'] === 'ALL' || $r['brand'] === $filter['brand']) && (!truthy($filter['type'] ?? null) || $filter['type'] === 'ALL' || $r['type'] === $filter['type']) && (!truthy($filter['status'] ?? null) || $filter['status'] === 'ALL' || $r['status'] === $filter['status']) && ($q === null || str_contains(js_lower($r['nama'] . ' ' . $r['customer'] . ' ' . $r['type'] . ' ' . $r['sales']), $q)));
        $rows = js_sort($rows, fn($a, $b) => lc($a['nama'], $b['nama']) ?: lc($a['brand'], $b['brand']) ?: lc($a['type'], $b['type']));
        $total = function (array $items) use ($windowReady, $covered) {
            $stock = 0; $sellout = 0; $bad = false; $cust = []; $low = 0; $badN = 0; $okStock = 0; $okSell = 0;
            foreach ($items as $r) { $stock += $r['stock']; $sellout += $r['sellout']; if ($r['status'] === 'PERLU CEK DATA') { $bad = true; $badN++; } else { $okStock += $r['stock']; $okSell += $r['sellout']; } $cust[(string) $r['customer']] = true; if (in_array($r['status'], ['MENIPIS', 'HABIS'], true)) $low++; }
            $avg = $windowReady ? $sellout / 30 : null;
            // DSM v5: DOS total dihitung dari baris yang datanya bersih; baris "perlu cek data" (jumlahnya di kolom bad) tidak lagi membuat DOS total kosong.
            $okAvg = $windowReady ? $okSell / 30 : null;
            return ['stock' => $stock, 'sellout' => $sellout, 'avg' => $avg, 'dos' => $covered && $okAvg > 0 ? $okStock / $okAvg : null, 'dealerCount' => count($cust), 'typeCount' => count($items), 'low' => $low, 'bad' => $badN];
        };
        $regions = []; $brands = [];
        foreach ($rows as $r) {
            $rk = jkey([$r['big'], $r['region']]); if (!isset($regions[$rk])) $regions[$rk] = ['big' => $r['big'], 'region' => $r['region'], 'items' => []]; $regions[$rk]['items'][] = $r;
            $bk = jkey([$r['customer'], $r['brand']]); if (!isset($brands[$bk])) $brands[$bk] = ['customer' => $r['customer'], 'nama' => $r['nama'], 'brand' => $r['brand'], 'items' => []]; $brands[$bk]['items'][] = $r;
        }
        return ['ready' => true, 'asof' => $asof, 'from' => $from, 'stCut' => $stCut, 'soCut' => $soCut, 'covered' => $covered, 'windowReady' => $windowReady, 'zeroAssumed' => $zeroAssumed, 'total' => $total($rows),
            'regions' => array_map(fn($r) => array_merge(['big' => $r['big'], 'region' => $r['region']], $total($r['items'])), array_values($regions)),
            'brands' => array_map(fn($r) => array_merge(['customer' => $r['customer'], 'nama' => $r['nama'], 'brand' => $r['brand']], $total($r['items'])), array_values($brands)), 'rows' => $rows];
    }
}

function js_str_or_empty($v): string { return truthy($v) ? js_str($v) : ''; }
