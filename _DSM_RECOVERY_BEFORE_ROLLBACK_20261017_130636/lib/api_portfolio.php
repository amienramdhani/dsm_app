<?php
/**
 * Alihkan Portofolio Sales.
 * Satu proses: dealer/PIC, dealer×brand ownership, target aktif/future,
 * atribusi aktual MTD, dan exit portofolio Sales.
 */
require_once __DIR__ . '/bulk.php';
require_once __DIR__ . '/portfolio_core.php';

function ptCtx_($token): array {
    $db = snapshot_();
    $u = adminGuard_($token, $db);
    return ['db'=>$db,'u'=>$u];
}

function ptEffective_(string $mode): array {
    $cur = jkt_date('Y-m');
    if ($mode === 'NEXT_MONTH') {
        $d = (new DateTimeImmutable($cur.'-01', jkt_tz_()))->modify('+1 month');
        return ['period'=>$d->format('Y-m'),'date'=>$d->format('Y-m-01'),'future'=>true];
    }
    return ['period'=>$cur,'date'=>$cur.'-01','future'=>false];
}

function ptSource_(array $db, array $u, string $nik): array {
    $p = PortalCore::person($db['meta']['people'], $nik);
    if (!$p || !truthy($p['sales'] ?? false)) fail_('User ini tidak memiliki portofolio Sales aktif.');
    if ($u['role'] !== 'SUPER ADMIN' && !canManageUser_($u, $p)) fail_('User di luar kewenangan Admin.');
    return $p;
}

function ptCandidates_(array $db, array $u, array $source): array {
    $out = [];
    foreach ($db['meta']['people'] as $p) {
        if ($p['nik'] === $source['nik'] || $p['role'] !== 'SALES' || !truthy($p['sales'] ?? false)) continue;
        if (js_upper(text_($p['wilayah'] ?? null)) !== js_upper(text_($source['wilayah'] ?? null))) continue;
        $a = account_($p['nik']);
        if ($a && !PortalCore::active($a['AKTIF'] ?? null)) continue;
        if ($u['role'] !== 'SUPER ADMIN' && !canManageUser_($u,$p)) continue;
        $out[] = [
            'nik'=>$p['nik'],
            'nama'=>$p['nama'],
            'region'=>$p['region'] ?? '',
            'wilayah'=>$p['wilayah'] ?? '',
        ];
    }
    usort($out, fn($a,$b)=>lc($a['nama'],$b['nama']));
    return $out;
}

function ptDealerSourceBrands_(array $db, string $sourceNik, string $dealer, string $date, ?array $brandIdx = null): array {
    $out = [];
    $brandIdx = $brandIdx ?? dbaIndex_();
    foreach (dbaPrimaryBrands_() as $brand) {
        $o = dealerBrandOwner_($db,$dealer,$brand,$date,$brandIdx);
        if ($o['nik'] === $sourceNik) $out[] = $brand;
    }
    return $out;
}

function ptDealers_(array $db, string $sourceNik, string $period): array {
    $today = jkt_date('Y-m-d');
    $brandIdx = dbaIndex_();
    $genericIdx = portfolioAssignmentIndex_();
    $rows = [];

    foreach ($db['meta']['customers'] as $id=>$c) {
        $dealer = (string)$id;
        $brands = ptDealerSourceBrands_($db,$sourceNik,$dealer,$today,$brandIdx);
        $generic = portfolioOwnerAt_($genericIdx,$dealer,$today,text_($c['nik'] ?? null));
        if (!$brands && $generic !== $sourceNik) continue;
        $rows[$dealer] = [
            'dealer'=>$dealer,
            'nama'=>text_($c['nama'] ?? null),
            'region'=>text_($c['region'] ?? null),
            'brands'=>$brands,
            'qty'=>0.0,
            'omzet'=>0.0,
        ];
    }

    $ref = (string)($db['raw'][$period] ?? '');
    if ($ref !== '') {
        try {
            foreach (dataRows_($ref) as $t) {
                if (substr(text_($t['date'] ?? null),0,7) !== $period) continue;
                $dealer = custInduk_($db['meta'], text_($t['customer'] ?? null));
                if (!isset($rows[$dealer])) continue;
                $prod = $db['meta']['products'][text_($t['sku'] ?? null)] ?? null;
                if (!$prod) continue;
                $brand = js_upper(text_($prod['brand'] ?? null));
                if (truthy($prod['device'] ?? false) && in_array($brand,$rows[$dealer]['brands'],true)) {
                    $rows[$dealer]['qty'] += (float)($t['qty'] ?? 0);
                    $rows[$dealer]['omzet'] += (float)($t['amount'] ?? 0);
                }
            }
        } catch (Throwable $e) {}
    }

    $out = array_values($rows);
    usort($out, fn($a,$b)=>lc($a['nama'],$b['nama']) ?: lc($a['dealer'],$b['dealer']));
    return $out;
}

function ptTargetInputCols_(array $heads): array {
    $norm = array_map(fn($h)=>preg_replace('/\s+/u',' ',js_upper(text_($h))), $heads);
    $pIdx = array_search('PERIODE TARGET',$norm,true);
    $nIdx = array_search('NIK SALES',$norm,true);
    $nameIdx = array_search('NAMA SALES',$norm,true);
    if ($pIdx === false || $nIdx === false) fail_('Target_Input wajib memiliki PERIODE TARGET dan NIK SALES.');
    $cols = [];
    foreach ($norm as $i=>$h) {
        if (preg_match('/^(ST|DA|NOO|IDDLE|OMZET|SO)\s+(.+)$/u',$h,$m)) {
            $cols[] = ['i'=>$i,'metric'=>$m[1],'brand'=>trim($m[2])];
        }
    }
    return ['pIdx'=>$pIdx,'nIdx'=>$nIdx,'nameIdx'=>$nameIdx,'cols'=>$cols];
}

function ptTargetId_(string $kind,string $period,string $brand,string $metric,string $indicator=''): string {
    return implode('¦',[$kind,$period,$brand,$metric,$indicator]);
}

function ptTargets_(string $nik, string $fromPeriod): array {
    $out = [];
    $g = readGrid_('Target_Input');
    if ($g && count($g) >= 2) {
        $c = ptTargetInputCols_($g[0]);
        foreach (array_slice($g,1) as $r) {
            $p = text_($r[$c['pIdx']] ?? null);
            $n = text_($r[$c['nIdx']] ?? null);
            if ($n !== $nik || $p === '' || strcmp($p,$fromPeriod) < 0) continue;
            foreach ($c['cols'] as $col) {
                $v = $r[$col['i']] ?? '';
                if ($v === '' || $v === null) continue;
                $num = js_number_($v);
                if (!is_numeric($num) || !is_finite((float)$num)) continue;
                $out[] = [
                    'id'=>ptTargetId_('WIDE',$p,$col['brand'],$col['metric'],''),
                    'kind'=>'WIDE','period'=>$p,'brand'=>$col['brand'],
                    'metric'=>$col['metric'],'indicator'=>'','value'=>(float)$num,
                ];
            }
        }
    }

    foreach (optionalTable_('Target_Periode') as $r) {
        $p = text_($r['PERIODE'] ?? null);
        $n = text_($r['NIK'] ?? null);
        if ($n !== $nik || $p === '' || strcmp($p,$fromPeriod) < 0) continue;
        $b = text_($r['BRAND'] ?? null);
        $m = js_upper(text_($r['METRIK'] ?? null));
        $i = text_($r['INDIKATOR_ID'] ?? null);
        $v = (float)($r['TARGET'] ?? 0);
        $out[] = [
            'id'=>ptTargetId_('LONG',$p,$b,$m,$i),
            'kind'=>'LONG','period'=>$p,'brand'=>$b,'metric'=>$m,
            'indicator'=>$i,'value'=>$v,
        ];
    }

    if (function_exists('kkSchema_')) {
        kkSchema_();
        $q = db_()->prepare('SELECT period,brand,value FROM _kk_target WHERE nik=? AND period>=? ORDER BY period,brand');
        $q->execute([$nik,$fromPeriod]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = [
                'id'=>ptTargetId_('KKSO',$r['period'],$r['brand'],'SO','KK_SO_MANUAL'),
                'kind'=>'KKSO','period'=>$r['period'],'brand'=>$r['brand'],
                'metric'=>'SO','indicator'=>'KK_SO_MANUAL','value'=>(float)$r['value'],
            ];
        }
    }

    usort($out, fn($a,$b)=>
        strcmp($a['period'],$b['period']) ?:
        strcmp($a['metric'],$b['metric']) ?:
        strcmp($a['brand'],$b['brand'])
    );
    return $out;
}

function apiPortfolioTransferPreview($token,$sourceNik,$mode='CURRENT_MONTH') {
    $c = ptCtx_($token);
    $db = $c['db']; $u = $c['u'];
    $source = ptSource_($db,$u,text_($sourceNik));
    $eff = ptEffective_(js_upper(text_($mode)));
    $dealers = ptDealers_($db,$source['nik'],jkt_date('Y-m'));
    $targets = ptTargets_($source['nik'],$eff['period']);
    $qty = 0; $omzet = 0;
    foreach ($dealers as $d) { $qty += $d['qty']; $omzet += $d['omzet']; }

    return [
        'source'=>[
            'nik'=>$source['nik'],'nama'=>$source['nama'],'role'=>$source['role'],
            'wilayah'=>$source['wilayah'] ?? '',
            'acting'=>($source['role']!=='SALES' && !empty($source['sales'])),
        ],
        'effective'=>$eff,
        'candidates'=>ptCandidates_($db,$u,$source),
        'dealers'=>$dealers,
        'targets'=>$targets,
        'summary'=>['dealers'=>count($dealers),'qty'=>$qty,'omzet'=>$omzet],
        'canFuture'=>$source['role']!=='SALES',
    ];
}

function ptValidateParts_(array $targets, $allocRaw, array $candidateSet): array {
    $allocRaw = is_array($allocRaw) ? array_values($allocRaw) : [];
    $by = [];
    foreach ($allocRaw as $a) if (is_array($a)) $by[text_($a['id'] ?? null)] = $a;

    $out = [];
    foreach ($targets as $t) {
        $a = $by[$t['id']] ?? null;
        if (!$a) fail_('Pembagian target belum lengkap: '.$t['period'].' · '.$t['metric'].' '.$t['brand'].'.');
        $parts = []; $sum = 0.0;
        foreach (array_values(is_array($a['parts'] ?? null) ? $a['parts'] : []) as $p) {
            $nik = text_($p['nik'] ?? null);
            $v = (float)($p['value'] ?? 0);
            if (!isset($candidateSet[$nik])) fail_('Penerima target tidak valid: '.$nik);
            if ($v < 0 || !is_finite($v)) fail_('Pembagian target tidak valid.');
            $parts[] = ['nik'=>$nik,'value'=>$v];
            $sum += $v;
        }
        if (abs($sum-(float)$t['value']) > 0.001) {
            fail_('Total pembagian '.$t['metric'].' '.$t['brand'].' '.$t['period'].' harus '.js_str($t['value']).' (sekarang '.js_str($sum).').');
        }
        $out[$t['id']] = ['target'=>$t,'parts'=>$parts];
    }
    if (count($out) !== count($by)) fail_('Ada target yang tidak lagi cocok dengan data terbaru. Muat ulang pratinjau.');
    return $out;
}

function ptTransferWide_(string $source,array $alloc,array $people): void {
    $g = readGrid_('Target_Input');
    if (!$g || count($g) < 1) return;
    $heads = $g[0];
    $c = ptTargetInputCols_($heads);
    $rows = array_map('array_values',array_slice($g,1));
    $names = [];
    foreach ($people as $p) $names[$p['nik']] = $p['nama'];

    $findRow = function($period,$nik) use (&$rows,$c) {
        foreach ($rows as $i=>$r) {
            if (text_($r[$c['pIdx']] ?? null) === $period && text_($r[$c['nIdx']] ?? null) === $nik) return $i;
        }
        return null;
    };

    $colMap = [];
    foreach ($c['cols'] as $col) $colMap[js_upper($col['metric']).'|'.js_upper($col['brand'])] = $col['i'];

    foreach ($alloc as $x) {
        $t = $x['target'];
        if ($t['kind'] !== 'WIDE') continue;
        $ci = $colMap[js_upper($t['metric']).'|'.js_upper($t['brand'])] ?? null;
        if ($ci === null) fail_('Kolom target tidak ditemukan: '.$t['metric'].' '.$t['brand']);

        $si = $findRow($t['period'],$source);
        if ($si === null) fail_('Baris target sumber berubah. Muat ulang pratinjau.');
        $rows[$si][$ci] = '';

        foreach ($x['parts'] as $p) {
            if ($p['value'] == 0) continue;
            $ri = $findRow($t['period'],$p['nik']);
            if ($ri === null) {
                $row = array_fill(0,count($heads),'');
                $row[$c['pIdx']] = $t['period'];
                $row[$c['nIdx']] = $p['nik'];
                if ($c['nameIdx'] !== false) $row[$c['nameIdx']] = $names[$p['nik']] ?? '';
                $rows[] = $row;
                $ri = count($rows)-1;
            }
            $old = $rows[$ri][$ci] ?? '';
            $rows[$ri][$ci] = ($old === '' || $old === null ? 0 : (float)$old) + $p['value'];
        }
    }

    putTable_('Target_Input',$heads,$rows);
}

function ptTransferLong_(string $source,array $alloc): void {
    $g = readGrid_('Target_Periode');
    if (!$g) return;
    $heads = array_map('text_',$g[0]);
    $rows = optionalTable_('Target_Periode');

    $key = fn($r)=>ptTargetId_(
        'LONG',
        text_($r['PERIODE'] ?? null),
        text_($r['BRAND'] ?? null),
        js_upper(text_($r['METRIK'] ?? null)),
        text_($r['INDIKATOR_ID'] ?? null)
    );

    $ids = [];
    foreach ($alloc as $x) if ($x['target']['kind'] === 'LONG') $ids[$x['target']['id']] = $x;

    $next = [];
    foreach ($rows as $r) {
        $k = $key($r);
        if (text_($r['NIK'] ?? null) === $source && isset($ids[$k])) continue;
        $next[] = $r;
    }

    foreach ($ids as $x) {
        $t = $x['target'];
        foreach ($x['parts'] as $p) {
            if ($p['value'] == 0) continue;
            $hit = null;
            foreach ($next as $i=>$r) {
                if (text_($r['NIK'] ?? null) === $p['nik'] && $key($r) === $t['id']) { $hit = $i; break; }
            }
            if ($hit !== null) $next[$hit]['TARGET'] = (float)($next[$hit]['TARGET'] ?? 0) + $p['value'];
            else $next[] = [
                'PERIODE'=>$t['period'],'NIK'=>$p['nik'],'BRAND'=>$t['brand'],
                'METRIK'=>$t['metric'],'TARGET'=>$p['value'],'INDIKATOR_ID'=>$t['indicator'],
            ];
        }
    }

    putTable_('Target_Periode',$heads,array_map(fn($r)=>array_map(fn($h)=>$r[$h] ?? '',$heads),$next));
}

function ptTransferKkSo_(string $source,array $alloc,string $actor): void {
    if (!function_exists('kkSchema_')) return;
    kkSchema_();

    foreach ($alloc as $x) {
        $t = $x['target'];
        if ($t['kind'] !== 'KKSO') continue;

        db_()->prepare('DELETE FROM _kk_target WHERE nik=? AND period=? AND brand=?')
            ->execute([$source,$t['period'],$t['brand']]);

        foreach ($x['parts'] as $p) {
            if ($p['value'] == 0) continue;
            $q = db_()->prepare('SELECT value FROM _kk_target WHERE nik=? AND period=? AND brand=?');
            $q->execute([$p['nik'],$t['period'],$t['brand']]);
            $old = $q->fetchColumn();
            $v = ($old === false ? 0 : (float)$old) + $p['value'];

            db_()->prepare('REPLACE INTO _kk_target (nik,period,brand,value,actor,updated_at) VALUES (?,?,?,?,?,?)')
                ->execute([$p['nik'],$t['period'],$t['brand'],$v,$actor,iso_now()]);
        }
    }
    if (function_exists('kkReset_')) kkReset_();
}

function ptSetDealer_(array $db,array $u,string $sourceNik,string $dealer,string $newNik,string $date,string $reason,bool $future): int {
    $changed = 0;
    $brandIdx = dbaIndex_();

    foreach (dbaPrimaryBrands_() as $brand) {
        $before = dealerBrandOwner_($db,$dealer,$brand,$date,$brandIdx);
        if ($before['nik'] !== $sourceNik) continue; // brand yang sudah milik Sales lain jangan disentuh
        dbaSet_($db,$u,$dealer,$brand,$newNik,$date,$reason,'PORTFOLIO_TRANSFER',$future);
        $brandIdx = dbaIndex_();
        $changed++;
    }

    $genericIdx = portfolioAssignmentIndex_();
    $masterNik = dbaMasterNik_($db,$dealer);
    $genericBefore = portfolioOwnerAt_($genericIdx,$dealer,$date,$masterNik);

    if ($genericBefore === $sourceNik) {
        $history = optionalTable_('Dealer_Assignment_History');
        $now = iso_now();
        $next = [];

        foreach ($history as $h) {
            if (text_($h['KODE CUSTOMER'] ?? null) !== $dealer) { $next[] = $h; continue; }
            $from = text_($h['BERLAKU_MULAI'] ?? null);
            $to = text_($h['BERLAKU_SAMPAI'] ?? null);

            if ($from !== '' && $from > $date && ($to === '' || $to >= $date)) {
                fail_('Sudah ada assignment dealer masa depan untuk '.$dealer.' mulai '.$from.'. Periksa assignment itu dulu.');
            }
            if ($from === $date) continue;
            if ($from !== '' && $from < $date && ($to === '' || $to >= $date)) {
                $h['BERLAKU_SAMPAI'] = dayAfter_($date,-1);
            }
            $next[] = $h;
        }

        $hasBaseline = false;
        foreach ($next as $h) {
            if (text_($h['KODE CUSTOMER'] ?? null) === $dealer &&
                text_($h['NIK SALES'] ?? null) === $sourceNik &&
                text_($h['BERLAKU_SAMPAI'] ?? null) === dayAfter_($date,-1)) {
                $hasBaseline = true;
                break;
            }
        }
        if (!$hasBaseline && $masterNik === $sourceNik) {
            $next[] = [
                'KODE CUSTOMER'=>$dealer,'NIK SALES'=>$sourceNik,
                'BERLAKU_MULAI'=>'','BERLAKU_SAMPAI'=>dayAfter_($date,-1),
                'SUMBER'=>'SEED','NOTE'=>'Owner sebelum alih portofolio',
                'UPDATED_BY'=>$u['nik'],'UPDATED_AT'=>$now,
            ];
        }

        $next[] = [
            'KODE CUSTOMER'=>$dealer,'NIK SALES'=>$newNik,
            'BERLAKU_MULAI'=>$date,'BERLAKU_SAMPAI'=>'',
            'SUMBER'=>'PORTFOLIO_TRANSFER','NOTE'=>$reason,
            'UPDATED_BY'=>$u['nik'],'UPDATED_AT'=>$now,
        ];
        putTable_('Dealer_Assignment_History',ASSIGNMENT_HEADERS,array_map(
            fn($r)=>array_map(fn($h)=>$r[$h] ?? '',ASSIGNMENT_HEADERS),
            $next
        ));

        if (!$future && $masterNik === $sourceNik) {
            $g = readGrid_('Master_Customer');
            $heads = array_map('text_',$g[0]);
            $objects = table_('Master_Customer');
            foreach ($objects as &$c) {
                if (text_($c['KODE CUSTOMER'] ?? null) === $dealer) $c['NIK SALES'] = $newNik;
            }
            unset($c);
            putTable_('Master_Customer',$heads,array_map(fn($r)=>array_map(fn($h)=>$r[$h] ?? '',$heads),$objects));
        }
    }

    return $changed;
}

function apiPortfolioTransferCommit($token,$payload,$reason=null) {
    $payload = is_array($payload) ? $payload : [];
    $reason = text_($reason ?? ($payload['reason'] ?? null));
    if (mb_strlen($reason) < 5) fail_('Isi alasan minimal 5 karakter.');

    return with_lock_(function() use ($token,$payload,$reason) {
        $db = snapshot_();
        $u = adminGuard_($token,$db);
        $source = ptSource_($db,$u,text_($payload['sourceNik'] ?? null));
        $mode = js_upper(text_($payload['mode'] ?? 'CURRENT_MONTH'));
        $eff = ptEffective_($mode);

        if ($source['role'] === 'SALES' && $eff['future']) {
            fail_('Untuk Sales resign, gunakan Ambil alih bulan berjalan agar akun dapat dinonaktifkan dalam satu proses.');
        }

        $cands = ptCandidates_($db,$u,$source);
        $candidateSet = [];
        foreach ($cands as $c) $candidateSet[$c['nik']] = $c;

        $currentDealers = ptDealers_($db,$source['nik'],jkt_date('Y-m'));
        $need = [];
        foreach ($currentDealers as $d) $need[$d['dealer']] = 1;

        $assign = is_array($payload['assignments'] ?? null) ? array_values($payload['assignments']) : [];
        $seen = [];
        foreach ($assign as $a) {
            $dealer = text_($a['dealer'] ?? null);
            $to = text_($a['newNik'] ?? null);
            if (!isset($need[$dealer])) fail_('Dealer bukan lagi milik sumber: '.$dealer);
            if (isset($seen[$dealer])) fail_('Dealer dobel: '.$dealer);
            if (!isset($candidateSet[$to])) fail_('Sales penerima tidak valid: '.$to);
            $seen[$dealer] = $to;
        }
        if (count($seen) !== count($need)) {
            fail_('Semua dealer harus ditentukan penerimanya ('.count($seen).'/'.count($need).').');
        }

        $targets = ptTargets_($source['nik'],$eff['period']);
        $alloc = ptValidateParts_($targets,$payload['allocations'] ?? [],$candidateSet);

        $sheets = [
            'Master_Customer','Dealer_Assignment_History',DBA_SHEET,
            'Target_Input','Target_Periode',PT_EXIT_SHEET,'_Auth',
        ];
        $before = [];
        foreach ($sheets as $s) $before[$s] = readGrid_($s);

        kkSchema_();
        $nikSet = array_values(array_unique(array_merge(array_keys($candidateSet),[$source['nik']])));
        $ph = implode(',',array_fill(0,count($nikSet),'?'));
        $q = db_()->prepare('SELECT nik,period,brand,value,actor,updated_at FROM _kk_target WHERE nik IN ('.$ph.') AND period>=?');
        $q->execute(array_merge($nikSet,[$eff['period']]));
        $kkBackup = $q->fetchAll(PDO::FETCH_ASSOC);

        checkpoint_($u['nik'],'Alihkan portofolio '.$source['nik'].' · '.$reason);

        try {
            $brandAssignments = 0;
            foreach ($seen as $dealer=>$to) {
                $brandAssignments += ptSetDealer_($db,$u,$source['nik'],$dealer,$to,$eff['date'],$reason,$eff['future']);
            }

            ptTransferWide_($source['nik'],$alloc,$db['meta']['people']);
            ptTransferLong_($source['nik'],$alloc);
            ptTransferKkSo_($source['nik'],$alloc,$u['nik']);
            portfolioExitSet_($source['nik'],$eff['date'],$u['nik']);

            // Pure Sales resign ikut transaksi atomic: _Auth dibackup dan dinonaktifkan sebelum publish.
            $deactivate = $source['role'] === 'SALES' && !$eff['future'];
            if ($deactivate) {
                $a = account_($source['nik']);
                if ($a) {
                    $a['AKTIF'] = false;
                    $a['VERSI'] = (int)($a['VERSI'] ?? 0) + 1;
                    writeAccount_($a);
                }
            }

            $meta = meta_();
            validateConfigChange_($db,$meta);
            publish_(
                $meta,$db['raw'],$db['coverage'],$u['nik'],
                'PORTFOLIO_TRANSFER '.$source['nik'].' → '.count(array_unique($seen)).' sales · '.$reason
            );

            // Setelah snapshot aktif, sesi lama dicabut best-effort; kegagalan tidak membatalkan transfer yang sudah sah.
            if ($deactivate) {
                try { revokeAllDevices_($source['nik']); }
                catch (Throwable $e) { error_log('[dsm] revoke device setelah portfolio transfer gagal: '.$e->getMessage()); }
            }
            try {
                audit_(
                    $u['nik'],'PORTFOLIO_TRANSFER',
                    $source['nik'].' · '.count($seen).' dealer · '.$brandAssignments.
                    ' dealer-brand · '.count($targets).' target · '.$eff['date'].' · '.$reason
                );
            } catch (Throwable $e) {
                error_log('[dsm] audit portfolio transfer gagal: '.$e->getMessage());
            }

            return [
                'ok'=>true,
                'dealers'=>count($seen),
                'brandAssignments'=>$brandAssignments,
                'targets'=>count($targets),
                'recipients'=>count(array_unique($seen)),
                'effective'=>$eff,
                'sourceRole'=>$source['role'],
            ];
        } catch (Throwable $e) {
            foreach ($before as $name=>$g) {
                if ($g) restoreGrid_($name,$g);
                elseif (sheet_exists_($name)) deleteSheet_($name);
            }

            db_()->prepare('DELETE FROM _kk_target WHERE nik IN ('.$ph.') AND period>=?')
                ->execute(array_merge($nikSet,[$eff['period']]));
            $ins = db_()->prepare('REPLACE INTO _kk_target (nik,period,brand,value,actor,updated_at) VALUES (?,?,?,?,?,?)');
            foreach ($kkBackup as $r) {
                $ins->execute([$r['nik'],$r['period'],$r['brand'],$r['value'],$r['actor'],$r['updated_at']]);
            }
            if (function_exists('kkReset_')) kkReset_();
            throw $e;
        }
    });
}
