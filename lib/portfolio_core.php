<?php
/**
 * DSM Portfolio Transfer core helpers.
 * Dibaca oleh publish.php agar histori raw tetap utuh tetapi ownership efektif
 * dapat diterapkan saat snapshot dibangun.
 */

const PT_EXIT_SHEET = 'Portfolio_Exit';
const PT_EXIT_HEADS = ['NIK','EFFECTIVE_DATE','TYPE','STATUS','UPDATED_BY','UPDATED_AT'];

function portfolioExitRows_(): array {
    $out = [];
    foreach (optionalTable_(PT_EXIT_SHEET) as $r) {
        $nik = text_($r['NIK'] ?? null);
        $date = text_($r['EFFECTIVE_DATE'] ?? null);
        if ($nik === '' || $date === '') continue;
        try { $date = PortalCore::iso($date); } catch (Throwable $e) { continue; }
        $out[] = [
            'nik'=>$nik,
            'date'=>$date,
            'type'=>js_upper(js_or(text_($r['TYPE'] ?? null),'PORTFOLIO')),
            'status'=>js_upper(js_or(text_($r['STATUS'] ?? null),'ACTIVE')),
            'by'=>text_($r['UPDATED_BY'] ?? null),
            'at'=>text_($r['UPDATED_AT'] ?? null),
        ];
    }
    return $out;
}

function portfolioExitDue_($nik, ?string $date = null): bool {
    $nik = text_($nik);
    $date = $date ?: jkt_date('Y-m-d');
    foreach (portfolioExitRows_() as $r) {
        if ($r['nik'] === $nik && $r['status'] === 'ACTIVE' && $r['date'] <= $date) return true;
    }
    return false;
}

function portfolioExitSet_(string $nik, string $date, string $actor): void {
    $date = PortalCore::iso($date);
    $next = [];
    foreach (optionalTable_(PT_EXIT_SHEET) as $r) {
        if (text_($r['NIK'] ?? null) !== $nik) $next[] = $r;
    }
    $next[] = [
        'NIK'=>$nik,
        'EFFECTIVE_DATE'=>$date,
        'TYPE'=>'PORTFOLIO',
        'STATUS'=>'ACTIVE',
        'UPDATED_BY'=>$actor,
        'UPDATED_AT'=>iso_now(),
    ];
    putTable_(PT_EXIT_SHEET, PT_EXIT_HEADS, array_map(
        fn($r)=>array_map(fn($h)=>$r[$h] ?? '', PT_EXIT_HEADS),
        $next
    ));
}

function portfolioClearExit_(string $nik): void {
    if (!sheet_exists_(PT_EXIT_SHEET)) return;
    $rows = array_values(array_filter(
        optionalTable_(PT_EXIT_SHEET),
        fn($r)=>text_($r['NIK'] ?? null) !== $nik
    ));
    putTable_(PT_EXIT_SHEET, PT_EXIT_HEADS, array_map(
        fn($r)=>array_map(fn($h)=>$r[$h] ?? '', PT_EXIT_HEADS),
        $rows
    ));
}

function portfolioFilterSalesRows_(array $rows, $roleSchema = 2, ?string $date = null): array {
    $date = $date ?: jkt_date('Y-m-d');
    $roles = [];
    foreach ($rows as $r) {
        $nik = text_($r['NIK KARYAWAN'] ?? null);
        $role = PortalCore::position($r['POSISI'] ?? null, $roleSchema);
        if ($nik !== '') $roles[$nik][$role] = true;
    }
    $out = [];
    foreach ($rows as $r) {
        $nik = text_($r['NIK KARYAWAN'] ?? null);
        $role = PortalCore::position($r['POSISI'] ?? null, $roleSchema);
        $hasHigher = false;
        foreach (array_keys($roles[$nik] ?? []) as $rr) {
            if ($rr !== 'SALES' && PortalCore::lv($rr) > PortalCore::lv('SALES')) { $hasHigher = true; break; }
        }
        if ($role === 'SALES' && $hasHigher && portfolioExitDue_($nik, $date)) continue;
        $out[] = $r;
    }
    return $out;
}

function portfolioAssignmentIndex_(): array {
    $idx = [];
    foreach (optionalTable_('Dealer_Assignment_History') as $r) {
        $dealer = text_($r['KODE CUSTOMER'] ?? null);
        $nik = text_($r['NIK SALES'] ?? null);
        if ($dealer === '' || $nik === '') continue;
        $from = text_($r['BERLAKU_MULAI'] ?? null);
        $to = text_($r['BERLAKU_SAMPAI'] ?? null);
        if ($from !== '') { try { $from = PortalCore::iso($from); } catch (Throwable $e) { $from = ''; } }
        if ($to !== '') { try { $to = PortalCore::iso($to); } catch (Throwable $e) { $to = ''; } }
        $idx[$dealer][] = ['nik'=>$nik,'from'=>$from,'to'=>$to];
    }
    foreach ($idx as &$list) usort($list, fn($a,$b)=>strcmp($a['from'],$b['from']));
    unset($list);
    return $idx;
}

function portfolioOwnerAt_(array $idx, string $dealer, string $date, string $fallback): string {
    $hit = '';
    foreach ($idx[$dealer] ?? [] as $r) {
        if ($r['from'] !== '' && $r['from'] > $date) continue;
        if ($r['to'] !== '' && $r['to'] < $date) continue;
        $hit = $r['nik'];
    }
    return $hit !== '' ? $hit : $fallback;
}

function portfolioApplyMetaOwners_(array $meta): array {
    $idx = portfolioAssignmentIndex_();
    if (!$idx) return $meta;
    $today = jkt_date('Y-m-d');
    foreach ($meta['customers'] ?? [] as $id => &$c) {
        $n = portfolioOwnerAt_($idx, (string)$id, $today, text_($c['nik'] ?? null));
        if ($n !== '' && PortalCore::person($meta['people'], $n)) $c['nik'] = $n;
    }
    unset($c);
    return $meta;
}

function portfolioResolveRows_(array $meta, array $rows): array {
    if (!$rows) return $rows;
    $brandIdx = function_exists('dbaIndex_') ? dbaIndex_() : [];
    $genericIdx = portfolioAssignmentIndex_();
    if (!$brandIdx && !$genericIdx) return $rows;
    $people = [];
    foreach ($meta['people'] ?? [] as $p) $people[(string)$p['nik']] = true;
    foreach ($rows as $i => $t) {
        $date = text_($t['date'] ?? null);
        $customer = text_($t['customer'] ?? null);
        if ($date === '' || $customer === '') continue;
        $dealer = function_exists('custInduk_') ? custInduk_($meta, $customer) : $customer;
        $nik = text_($t['nik'] ?? null);
        if ($nik !== '' && function_exists('keepSellerCheck_') && keepSellerCheck_($meta, $nik, js_slice($date, 0, 7))) continue; // kredit tetap ke penjual
        $sku = text_($t['sku'] ?? null);
        $brand = js_upper(text_($meta['products'][$sku]['brand'] ?? null));
        if ($brand !== '' && function_exists('dbaAt_')) {
            $a = dbaAt_($brandIdx, $dealer, $brand, $date);
            if ($a && text_($a['nik'] ?? null) !== '') $nik = text_($a['nik']);
            else $nik = portfolioOwnerAt_($genericIdx, $dealer, $date, $nik);
        } else $nik = portfolioOwnerAt_($genericIdx, $dealer, $date, $nik);
        if ($nik !== '' && isset($people[$nik])) $rows[$i]['nik'] = $nik;
    }
    return $rows;
}

function portfolioHasActiveWork_(array $db, string $nik): array {
    $today = jkt_date('Y-m-d');
    $period = jkt_date('Y-m');
    $dealers = 0;
    $idx = portfolioAssignmentIndex_();
    foreach ($db['meta']['customers'] ?? [] as $id=>$c) {
        $owner = portfolioOwnerAt_($idx, (string)$id, $today, text_($c['nik'] ?? null));
        if ($owner === $nik) $dealers++;
    }
    $targets = 0;
    foreach ($db['meta']['targets'] ?? [] as $t) {
        if (($t['nik'] ?? '') === $nik && strcmp((string)($t['period'] ?? ''), $period) >= 0) $targets++;
    }
    return ['dealers'=>$dealers,'targets'=>$targets,'has'=>($dealers > 0 || $targets > 0)];
}
