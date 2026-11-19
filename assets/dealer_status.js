/* ATURAN STATUS DEALER (Paket 2, 2026-11-17)
 * - Ringkasan atas halaman Dealer: kunci status + MTD, SUDAH ORDER = DA KPI, 6 kartu status, Bangkit bulan ini.
 * - Penanda bulan berjalan OPSI B di kotak brand: ✓ NOV · 4u / ½ NOV · 1u / BELUM NOV.
 * - Layar Admin › Brand › Aturan Status Dealer (Super Admin): pola, coba pola, dampak sebelum simpan.
 * Status dihitung server (lib/dealer_status.php). File ini hanya tampilan. Tidak menyentuh KPI. */
(function () {
  const MON = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
  const DSCOL = { 'ACTIVE': ['#E4F4E8', '#237A36', '●'], 'PASSIVE': ['#FDF3DC', '#9A6400', '◐'], 'INACTIVE': ['#FBE6E5', '#B4332E', '○'], 'PROSPECT': ['#E3EEF9', '#1E6FBF', '+'], 'AKTIF': ['#E2F5EA', '#0E6E48', '●'], 'DI BAWAH MIN': ['#FFF4D6', '#8A5A00', '½'], 'BANGKIT': ['#E3F4F6', '#0E7C8C', '↗'], 'TURUN': ['#FDE8D4', '#B4530E', '↘'],
    'IDLE': ['#FCEBDC', '#C24F0C', 'Ⅱ'], 'PASIF': ['#FBE3E1', '#A3201B', 'Ⅱ'], 'NONAKTIF': ['#E5E7EB', '#374151', '○'], 'LAINNYA': ['#EEF1F5', '#5B6778', '?'],
    'BELUM ORDER': ['#EEF2F7', '#62798B', '–'], 'NOO': ['#ECE6FB', '#6D3FD8', '★'], 'TUTUP': ['#E4E8EE', '#4D5A6B', '✕'] };
  const EXTRA = [['#E6EEFA', '#0B5CAD'], ['#EEE8FB', '#6D3FD8'], ['#E3F4F6', '#0E7C8C'], ['#FFF1DC', '#C25A12']];
  window.dsCol = s => DSCOL[s] || EXTRA[[...String(s)].reduce((a, c) => a + c.charCodeAt(0), 0) % EXTRA.length].concat('•');
  window.dsMon = p => MON[(+String(p || '').slice(5, 7) || 1) - 1];
  const SUSAH = ['IDLE', 'INACTIVE'];
  const ORDER = ['ACTIVE', 'PASSIVE', 'IDLE', 'INACTIVE', 'PROSPECT'];
  const DESC = { 'ACTIVE': 'Order minimal 2 dari 3 bulan closing terakhir.', 'PASSIVE': 'Order hanya 1 dari 3 bulan closing terakhir.', 'IDLE': 'Tidak order 3–5 bulan berturut-turut.', 'INACTIVE': 'Tidak order 6 bulan atau lebih berturut-turut.',
    'PROSPECT': 'Belum pernah order di 2026 (per brand: belum pernah ambil brand ini).', 'AKTIF': 'Order (minimal unit) di bulan terakhir pola.', 'DI BAWAH MIN': 'Bulan terakhir order, tapi di bawah minimal unit.', 'BANGKIT': 'Sempat berhenti beberapa bulan, lalu order lagi di bulan terakhir pola.',
    'TURUN': 'Dua bulan order berturut-turut, lalu berhenti dua bulan.', 'PASIF': 'Dua bulan terakhir tanpa order.', 'NONAKTIF': 'Enam bulan tanpa order.',
    'LAINNYA': 'Pola tidak cocok dengan aturan mana pun.', 'BELUM ORDER': 'Belum pernah order sampai bulan lalu.', 'NOO': 'Order pertama brand ini terjadi bulan ini.' };
  window.dsDesc = s => DESC[s] || 'Status buatan Super Admin. Lihat Admin › Brand › Aturan Status Dealer.';
  const pill = (s, txt) => { const c = dsCol(s); return `<span class="dsPill" style="background:${c[0]};color:${c[1]}">${esc(txt || s)}</span>`; };
  window.dsPill = pill;
  // ---------- mode ringkasan: TOTAL atau per brand ----------
  window.DSV = window.DSV || { brand: 'TOTAL' };
  function mode() { const bs = (REPORT.brands || []).map(b => b.brand); if (DSV.brand !== 'TOTAL' && !bs.includes(DSV.brand)) DSV.brand = 'TOTAL'; return DSV.brand; }
  // TUTUP tidak dihitung; dealer yang sedang DIAJUKAN tutup tidak dihitung sebagai PROSPECT (keputusan user 2026-11-19).
  function stOf(c, b) { if (c.status === 'TUTUP') return null; const s = b === 'TOTAL' ? c.status : (c.bs || {})[b] || 'PROSPECT';
    if (s === 'PROSPECT' && typeof V6TUTUP !== 'undefined' && V6TUTUP && V6TUTUP.pend && V6TUTUP.pend[c.id]) return null; return s; }
  function nooOf(c, b) { const d = c.ds; if (!d) return false; return b === 'TOTAL' ? !!d.noo : !!(d.bc || {})[b]?.noo; }
  // Rumus status terbaru dari Admin (jendela, ambang) untuk teks keterangan.
  function rule() { const r = REPORT.statusInfo?.rule || {}; return { win: r.win || 3, act: r.act || 2, inact: r.inact || 6 }; }
  function orderOf(c, b) { const d = c.ds; if (!d) return { q: 0, da: false }; if (b === 'TOTAL') return { q: d.order?.q || 0, da: (d.order?.nda || 0) > 0, nda: d.order?.nda || 0 }; const x = (d.bc || {})[b]; return x ? { q: x.q || 0, da: !!x.da } : { q: 0, da: false }; }

  window.dsTopSummary = function (cs) {
    const info = REPORT.statusInfo; if (!info) return '';
    const b = mode(), M = dsMon(REPORT.period), cut = REPORT.coverage?.cutoff || '', day = +cut.slice(8, 10) || 1;
    const by = {}, ord = {}; let nOrder = 0, nDA = 0, nPair = 0, nAll = 0, nNoo = 0; const bk = {};
    cs.forEach(c => { const s = stOf(c, b); if (!s) return; nAll++; const o = orderOf(c, b); by[s] = (by[s] || 0) + 1; if (o.q > 0) { ord[s] = (ord[s] || 0) + 1; nOrder++; if (SUSAH.includes(s)) bk[s] = (bk[s] || 0) + 1; } if (o.da) nDA++; nPair += o.nda || 0; if (o.q > 0 && nooOf(c, b)) nNoo++; });
    const mon = s => s.charAt(0) + s.slice(1).toLowerCase(), m0 = dsMon(info.months?.[0]), m1 = dsMon(info.months?.[info.months.length - 1]);
    const lockTxt = info.locked ? `<span class="dsChip lock"><em>🔒 STATUS</em>dikunci 1 ${M.charAt(0) + M.slice(1).toLowerCase()}</span>` : `<span class="dsChip temp"><em>⏳ STATUS</em>sementara · ${m1} belum ditutup</span>`;
    const seg = `<div class="dsSeg" role="tablist"><span class="${b === 'TOTAL' ? 'on' : ''}" data-dsb="TOTAL">TOTAL</span>${(REPORT.brands || []).map(x => `<span class="${b === x.brand ? 'on' : ''}" data-dsb="${esc(x.brand)}">${esc(x.brand)}</span>`).join('')}</div>`;
    const big = b === 'TOTAL'
      ? `<div class="dsK big"><div class="ic">✓</div><div><span>SUDAH ORDER ${M}</span><div class="n">${num(nOrder)}</div><small>dealer · DA KPI (semua brand): <b>${num(nPair)}</b> pasangan dealer-brand</small><b class="dsKpi" data-dsi="total">≠ DA KPI ⓘ</b></div></div>`
      : `<div class="dsK big"><div class="ic">✓</div><div><span>SUDAH ORDER ${M} · ${esc(b)}</span><div class="n">${num(nDA)}</div><small>memenuhi minimal DA · ${num(nOrder - nDA)} lagi order di bawah minimal</small><b class="dsKpi" data-dsi="brand">= DA KPI ⓘ</b></div></div>`;
    const base = ORDER.slice();
    const more = Object.keys(by).filter(s => !base.includes(s) && by[s] > 0);
    const card = s => { const c = dsCol(s); return `<div class="dsK" data-dss="${esc(s)}"><div class="ic" style="background:${c[0]};color:${c[1]}">${c[2]}</div><div><span>${esc(s)}</span><div class="n">${num(by[s] || 0)}</div><small>✓ ${num(ord[s] || 0)} sudah order ${M}</small></div></div>`; };
    const nb = SUSAH.reduce((a, s) => a + (bk[s] || 0), 0);
    return `<section class="dsTop"><div class="dsChips">${lockTxt}<span class="dsChip mtd"><em>MTD</em>1–${day} ${M.charAt(0) + M.slice(1).toLowerCase()} ${String(REPORT.period).slice(0, 4)}</span></div>${seg}
      <div class="dsSl">ORDER BULAN INI</div><div class="dsKg">${big}</div>
      ${dsMobile(b, M, by, ord, nAll, nOrder, nDA, nPair, nNoo, base.concat(more), m0, m1)}
      <div class="dsSl">STATUS DEALER (POLA ${m0}–${m1}) <span class="dsI" data-dsi="status">i</span></div><div class="dsKg">${base.concat(more).map(card).join('')}</div>
</div></section>`;
  };
  /* Versi HP (mockup dsm-dealer-status-hp.html Opsi A): kartu DA bulan ini + daftar status. Desktop tetap memakai kartu lama (page_tidy). */
  function dsMobile(b, M, by, ord, nAll, nOrder, nDA, nPair, nNoo, list, m0, m1) {
    const one = customerFilter.statuses.length === 1 && customerFilter.order === 'ALL' ? customerFilter.statuses[0] : null;
    const tot = list.reduce((a, s) => a + (by[s] || 0), 0) || 1, nd = b === 'TOTAL' ? nOrder : nDA;
    const bar = list.filter(s => by[s]).map(s => `<i style="flex:${by[s]} 1 0;background:${dsCol(s)[1]}"></i>`).join('');
    const rows = list.map(s => { const c = dsCol(s), n = by[s] || 0, o = ord[s] || 0; return `<div class="dsMr${one === s ? ' on' : ''}" data-dss="${esc(s)}" style="--c:${c[1]};--bg:${c[0]}"><div class="ic" style="background:${c[0]};color:${c[1]}">${c[2]}</div><div class="tx"><b style="color:${c[1]}">${esc(s)}</b><div class="mb"><i style="width:${n ? Math.round(o / n * 100) : 0}%"></i></div><small>${s === 'PROSPECT' && nNoo ? `<em>NOO ${num(nNoo)}</em> · ` : ''}✓ ${num(o)} sudah order ${M}${n ? ' · ' + Math.round(o / n * 100) + '%' : ''}</small></div><div class="nn"><b>${num(n)}</b><small>${(n / tot * 100).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%</small></div></div>`; }).join('');
    return `<div class="dsM">
      <div class="dsMc"><div class="dsMh"><b>ORDER BULAN INI${b === 'TOTAL' ? '' : ' · ' + esc(b)}</b><small>${num(nAll)} dealer</small></div>
        <div class="dsMo"><div><span>✓ DA BULAN INI (${M})</span><div class="n">${num(nd)}</div></div><div class="r">belum order<br><b>${num(Math.max(0, nAll - nOrder))}</b></div></div>
        <div class="dsMp"><i style="width:${nAll ? Math.round(nd / nAll * 1000) / 10 : 0}%"></i></div>
        <div class="dsMt">${b === 'TOTAL' ? `<span data-dsi="total">DA KPI semua brand: ${num(nPair)} pasangan dealer-brand ⓘ</span>` : `<span data-dsi="brand">= DA KPI ${esc(b)} · ${num(nOrder - nDA)} order di bawah minimal ⓘ</span>`}${nNoo ? `<span class="noo">NOO ${M}: ${num(nNoo)} dealer · dari PROSPECT</span>` : ''}</div></div>
      <div class="dsMc"><div class="dsMh"><b>STATUS POLA ${rule().win} BULAN · ${m0}–${m1} <span class="dsI" data-dsi="status">i</span></b><small>ketuk untuk saring</small></div>
        <div class="dsMbar">${bar}</div><div class="dsMl${one ? ' dim' : ''}">${rows}</div>
        <p class="dsMn">Basis ST qty 2026 · untuk analisa, tidak mempengaruhi KPI</p></div></div>`;
  }
  const INFO = {
    total: 'Angka DA di KPI dihitung per pasangan dealer-brand (1 dealer yang order 2 brand = 2 DA). Di mode TOTAL, kartu ini menghitung DEALER, jadi angkanya berbeda. Pilih brand di atas untuk melihat angka yang sama persis dengan DA KPI brand itu.',
    brand: 'Sama persis dengan angka DA di KPI brand ini: minimal unit, tipe wajib, dan aturan promotor mengikuti editor KPI periode ini.',
    get status() { const r = rule(); return `Status dihitung dari jumlah bulan order dalam ${r.win} bulan closing terakhir (data ST qty, basis 2026) dan dikunci tiap tanggal 1. ACTIVE = order min ${r.act} dari ${r.win} bulan · PASSIVE = order 1 bulan · IDLE = tidak order ${r.win}–${r.inact - 1} bulan berturut-turut · INACTIVE = tidak order ≥ ${r.inact} bulan · PROSPECT = belum pernah order di 2026 (per brand: belum pernah ambil brand itu). Bulan berjalan hanya untuk monitoring progres. Hanya untuk analisa, tidak mempengaruhi KPI. Aturan diatur Super Admin di Admin › Brand › Aturan Status Dealer.`; },
    bangkit: 'Dealer yang status kuncinya Idle / Inactive tapi sudah order bulan ini. Ketuk salah satu kotak untuk memfilter daftar dealer.' };
  document.addEventListener('click', e => {
    const s = e.target.closest('[data-dsb]'); if (s) { DSV.brand = s.dataset.dsb; render(); return; }
    const i = e.target.closest('[data-dsi]'); if (i) { e.stopPropagation(); dsSheet('Keterangan', `<p class="hint" style="font-size:13px;line-height:1.55">${esc(INFO[i.dataset.dsi])}</p>`); return; }
    const k = e.target.closest('[data-dsbk]'); if (k) { const st = k.dataset.dsbk, on = customerFilter.order === 'YES' && customerFilter.statuses.length === 1 && customerFilter.statuses[0] === st; customerFilter.statuses = on ? [] : [st]; customerFilter.order = on ? 'ALL' : 'YES'; if (DSV.brand !== 'TOTAL') customerFilter.brand = DSV.brand; render(); setTimeout(() => document.getElementById('dealerList')?.scrollIntoView({ behavior: 'smooth' }), 60); return; }
    const c = e.target.closest('[data-dss]'); if (c) { const st = c.dataset.dss, on = customerFilter.statuses.length === 1 && customerFilter.statuses[0] === st && customerFilter.order === 'ALL'; customerFilter.statuses = on ? [] : [st]; customerFilter.order = 'ALL'; if (DSV.brand !== 'TOTAL') customerFilter.brand = DSV.brand; render(); setTimeout(() => document.getElementById('dealerList')?.scrollIntoView({ behavior: 'smooth' }), 60); }
  });
  window.dsSheet = function (title, html) {
    let o = document.getElementById('dsOv'); if (!o) { o = document.createElement('div'); o.id = 'dsOv'; document.body.appendChild(o); o.addEventListener('click', ev => { if (ev.target === o || ev.target.closest('[data-dsx]')) o.classList.remove('on'); }); }
    o.innerHTML = `<div class="dsSheet"><div class="dsSh"><b>${esc(title)}</b><button type="button" data-dsx aria-label="Tutup">✕</button></div><div class="dsSb">${html}</div></div>`; o.classList.add('on'); return o;
  };
  // ---------- penanda OPSI B ----------
  window.dsStrip = function (c, b) {
    const x = c.ds?.bc?.[b]; if (!x) return ''; const M = dsMon(REPORT.period);
    if (x.noo) return `<div class="dsStrip ok">★ NOO ${M} · ${num(x.q)}u</div>`;
    if (!(x.q > 0)) return `<div class="dsStrip no">BELUM ${M}</div>`;
    return x.da ? `<div class="dsStrip ok">✓ ${M} · ${num(x.q)}u</div>` : `<div class="dsStrip h">½ ${M} · ${num(x.q)}u</div>`;
  };
  // ---------- pola 6 bulan (detail dealer) ----------
  const SYM = { y: '✓', h: '½', n: '✕', a: '•' };
  window.dsPola = function (pola, months) { return `<div class="dsPola">${String(pola || 'nnnnnn').split('').map((v, i) => `<div><small>${dsMon(months?.[i])}</small><i class="dsc ${v}">${SYM[v]}</i></div>`).join('')}</div>`; };
  window.dsDetailHtml = function (d) {
    const ds = d.ds; if (!ds) return ''; const M = dsMon(REPORT.period);
    const bs = Object.keys(ds.bs || {});
    const head = `<div class="dsDetHead"><span></span><span></span><div class="dsPola">${(ds.months || []).map(m => `<small>${dsMon(m)}</small>`).join('')}</div><small>KETERANGAN</small></div>`;
    const leg = `<div class="dsLeg"><span><i class="dsc y"></i>Order (qty &gt; 0)</span><span><i class="dsc n"></i>Tidak order</span><span><i class="dsc a"></i>Sebelum basis</span></div>`;
    return `<section class="dsDet${ds.p12 ? ' has12' : ''}"><h4>Status dealer ${ds.locked ? '· 🔒 dikunci 1 ' + M.charAt(0) + M.slice(1).toLowerCase() : '· ⏳ sementara'}</h4>${head}<div class="dsDetRow dsTot"><b>TOTAL</b>${pill(ds.status)}${dsPola(ds.pola, ds.months)}<small>${esc(dsDesc(ds.status))}${ds.noo ? ' · NOO bulan ini' : ''}${ds.order?.q > 0 ? ` · order ${M}: ${num(ds.order.q)} unit` : ` · belum order ${M}`}</small></div>
      ${bs.map(b => { const x = ds.bc[b] || {}; return `<div class="dsDetRow"><b>${esc(b)}</b>${pill(ds.bs[b])}${dsPola(x.pola, ds.months)}<small>${x.q > 0 ? `${M}: ${num(x.q)} unit${x.da ? ' · memenuhi DA' : ' · di bawah minimal DA'}${x.last ? ' · terakhir ' + esc(tgl(x.last)) : ''}` : 'Belum order ' + M}</small></div>`; }).join('')}${leg}${ds.p12 ? dsP12Html(ds, M) : ''}</section>`;
  };
  // ---------- HP: kartu baris 12 bulan (mockup 2026-11-19) ----------
  const d12 = p => `<div class="ds12">${String(p || '').padEnd(12, 'n').split('').map((c, i) => `<i class="${c === 'b' ? 'b' : c === 'y' ? 'y' : ''}${i >= 9 ? ' w' : ''}"></i>`).join('')}</div>`;
  window.dsP12Html = function (ds, M) {
    const P = ds.p12, ms = P.months || [], y0 = (ms[0] || '').slice(0, 4), y1 = (ms[11] || '').slice(0, 4), cut = ms.findIndex(m => m.slice(0, 4) !== y0);
    const yr = cut > 0 ? `<div class="ds12y" style="grid-template-columns:${cut}fr ${12 - cut}fr"><span>BULAN · ${y0}</span><span>${y1} →</span></div>` : `<div class="ds12y"><span>BULAN · ${y0}</span></div>`;
    const mn = `<div class="ds12m">${ms.map((m, i) => `<span class="${i >= 9 ? 'w' : ''}">${+m.slice(5, 7)}</span>`).join('')}</div>`;
    const card = (nm, st, q, pola, info, tot) => `<div class="ds12r${tot ? ' tot' : ''}"><div class="n"><b>${esc(nm)}</b>${pill(st)}</div><div class="q">${q > 0 ? num(q) + ' <small>unit ' + M + '</small>' : '<small>belum order ' + M + '</small>'}</div><div class="p">${tot ? yr + mn : ''}${d12(pola)}</div><div class="t">${info}</div></div>`;
    const bs = Object.keys(ds.bs || {});
    return `<div class="ds12x">12 bulan terakhir · <b>garis bawah = jendela status</b> · kotak putus-putus = sebelum basis</div>` +
      card('TOTAL', ds.status, ds.order?.q, P.total, esc(dsDesc(ds.status)) + (ds.noo ? ' · NOO bulan ini' : ''), 1) +
      bs.map(b => { const x = ds.bc[b] || {}; return card(b, ds.bs[b], x.q, P.brand?.[b] || String(P.total || '').replace(/y/g, 'n'), x.q > 0 ? (x.da ? 'memenuhi DA' : 'di bawah minimal DA') + (x.last ? ' · terakhir ' + esc(tgl(x.last)) : '') : (x.last ? 'terakhir ' + esc(tgl(x.last)) : 'belum order ' + M)); }).join('') +
      `<div class="ds12l"><span><i class="y"></i>order</span><span><i></i>tidak order</span><span><i class="b"></i>sebelum basis</span></div>`;
  };
  // ---------- ganti tampilan status lama ----------
  window.badge = function (status) {
    if (status === 'TUTUP') return '<span class="statusBadge v6st-tutup">DEALER TUTUP</span>';
    const c = dsCol(status); const label = ['AKTIF', 'IDLE', 'PASIF', 'TURUN', 'NONAKTIF', 'BANGKIT', 'ACTIVE', 'PASSIVE', 'INACTIVE'].includes(status) ? 'DEALER ' + status : status;
    return `<span ${v6TipShort(dsDesc(status))} class="v6tipD statusBadge" style="background:${c[0]};color:${c[1]}">${esc(label)}</span>`;
  };
  window.v6BsBadge = function (c, b) { const s = c.bs && c.bs[b]; return s ? pill(s) + dsStrip(c, b) : ''; };
  window.v6BsMatch = function (c, sel) { const bs = c.bs || {}, f = customerFilter.brand; if (f && f !== 'ALL') return sel.includes(bs[f] || 'PROSPECT'); return Object.values(bs).some(x => sel.includes(x)); };
  window.v6BsSummary = function (c) {
    const bs = c.bs || {}, f = customerFilter.brand; if (f && f !== 'ALL') return bs[f] ? [bs[f], f + ' · ' + bs[f]] : null;
    const vals = Object.entries(bs); if (!vals.length) return null;
    for (const k of ['INACTIVE', 'IDLE', 'PASSIVE', 'NONAKTIF', 'PASIF', 'TURUN', 'DI BAWAH MIN', 'NOO']) { const n = vals.filter(x => x[1] === k); if (n.length) return [k, n.length > 1 ? k + ' di ' + n.length + ' brand' : k + ' di ' + n[0][0]]; }
    return ['ACTIVE', 'ACTIVE di semua brand'];
  };
  window.v6BsDetail = function (c) { return c.ds ? dsDetailHtml({ ds: Object.assign({ status: c.status, bs: c.bs, months: REPORT.statusInfo?.months, locked: REPORT.statusInfo?.locked }, c.ds) }) : ''; };
  // Detail dealer: Status Dealer pindah ke tab sendiri (urutan ke-2, nomor tab 5 supaya logika tab lama tidak bergeser).
  if (typeof window.dealerTabs === 'function' && !window.dealerTabs.__ds) {
    const orig = window.dealerTabs;
    window.dealerTabs = function (id, period, selection) {
      const r = orig.apply(this, arguments);
      try {
        const body = document.getElementById('mbody'), tabs = body && body.querySelector('.dealerTabs'), det = body && body.querySelector('#dealerSummary .dsDet');
        if (tabs && det && !tabs.querySelector('[data-dtab="5"]')) {
          const pane = document.createElement('div'); pane.id = 'dealerStatusTab'; pane.hidden = true; pane.append(det); body.append(pane);
          const b = document.createElement('button'); b.type = 'button'; b.setAttribute('role', 'tab'); b.setAttribute('aria-selected', 'false'); b.className = 'chip modalTab'; b.dataset.dtab = '5';
          b.innerHTML = `${menuIcon('analisa')}<span>Status</span>`; tabs.children[0].after(b); tabs.classList.add('ds6');
          const hs = tabs.querySelector('[data-dtab="1"] span'); if (hs) hs.textContent = 'Histori';
          tabs.addEventListener('click', e => { const sel = e.target.closest('[data-dtab]'); if (sel) pane.hidden = sel.dataset.dtab !== '5'; });
          const bd = body.querySelector('#dealerSummary .detailstatus .statusBadge');
          if (bd) { bd.classList.add('dsGo'); bd.title = 'Lihat status dealer'; bd.onclick = () => b.click(); }
        }
      } catch (e) { /* tab tambahan gagal: isi tetap di Ringkasan */ }
      return r;
    };
    window.dealerTabs.__ds = true;
  }
  window.dsStatusList = () => ORDER.concat(['TUTUP']);

  /* ---------- Admin › Brand › Aturan Status Dealer (Super Admin) ----------
   * Mockup dsm-admin-aturan-status-hp.html: 3 tab RUMUS STATUS / BRAND / CEK DAMPAK. */
  let ST = null; // {data, cfg, tab, dirty, imp}
  const isSA = () => (BOOT?.user?.role || '') === 'SUPER ADMIN';
  const PCOL = { ACTIVE: '#2E9E45', PASSIVE: '#E89A00', IDLE: '#E2570F', INACTIVE: '#C62E2E', PROSPECT: '#1E7FD6' };
  window.v6BrandThrPanel = function (v) {
    if (!isSA()) return;
    v.insertAdjacentHTML('beforeend', panel('Aturan Status Dealer', `<div id="dsAdm"><p class="hint v6load">Memuat…</p></div>`, '<span class="dsSA">SUPER ADMIN</span>'));
    rpc('apiDealerStatusRules', TOKEN).then(r => { ST = { data: r, cfg: JSON.parse(JSON.stringify(r.config)), tab: 'rumus', dirty: false, imp: null }; dsAdm(); })
      .catch(e => { const b = document.getElementById('dsAdm'); if (b) b.innerHTML = '<div class="banner bad">' + esc(e.message || e) + '</div>'; });
  };
  const nx = (k, v, min, max) => `<span class="dsNum"><button type="button" data-dsn="${k}" data-dv="-1"${v <= min ? ' disabled' : ''}>−</button><b>${v}</b><button type="button" data-dsn="${k}" data-dv="1"${v >= max ? ' disabled' : ''}>+</button></span>`;
  const pl = s => `<span class="dsAp" style="background:${PCOL[s]}">${s}</span>`;
  function dsAdm() {
    const box = document.getElementById('dsAdm'); if (!box) return; const d = ST.data, c = ST.cfg, M = dsMon(d.period), mon = M.charAt(0) + M.slice(1).toLowerCase();
    const nxt = (() => { const i = (+d.period.slice(0, 4)) * 12 + (+d.period.slice(5, 7)); return dsMon(`${Math.floor(i / 12)}-${String(i % 12 + 1).padStart(2, '0')}`); })();
    const tabs = [['rumus', 'RUMUS STATUS'], ['brand', 'BRAND'], ['dampak', 'CEK DAMPAK']].map(([k, t]) => `<span class="${ST.tab === k ? 'on' : ''}" data-dst="${k}">${t}</span>`).join('');
    let body = '';
    if (ST.tab === 'rumus') {
      const yrs = []; for (let y = 2024; y <= new Date().getFullYear(); y++) yrs.push(y);
      const since = yrs.flatMap(y => MON.map((m, i) => [`${y}-${String(i + 1).padStart(2, '0')}`, `${m.charAt(0) + m.slice(1).toLowerCase()} ${y}`]));
      body = `<div class="dsAl">🔒 <div>Status <b>dikunci tiap tanggal 1</b>. Simpan aturan = status ${esc(mon)} dihitung ulang dengan aturan baru; periode lampau yang sudah dikunci tidak berubah.</div></div>
        <div class="dsAc"><div class="dsAh">DASAR HITUNG</div>
          <div class="dsAg"><label><small>DATA</small><div class="fi ro">ST qty</div></label>
          <label><small>BASIS DATA MULAI</small><select class="fi" data-dssince>${since.map(([v, t]) => `<option value="${v}"${v === c.since ? ' selected' : ''}>${t}</option>`).join('')}</select></label>
          <label><small>JENDELA (BLN CLOSING)</small>${nx('win', c.win, 1, 12)}</label>
          <label><small>DIHITUNG ORDER BILA</small><div class="fi ro">qty &gt; 0 / bulan</div></label></div>
          <ul class="dsAk"><li>✓ Banyak invoice dalam 1 bulan = 1 bulan order</li><li>✓ Retur tidak dihitung (qty bersih bulan itu)</li><li>✓ Bulan berjalan (MTD) hanya monitoring progres</li></ul></div>
        <div class="dsAc"><div class="dsAh">ATURAN STATUS <small>dicek dari atas ke bawah</small></div>
          <div class="dsAr">${pl('ACTIVE')}<span>Order min ${nx('act', c.act, 1, c.win)} dari ${c.win} bulan</span></div>
          <div class="dsAr">${pl('PASSIVE')}<span>Order 1${c.act > 2 ? '–' + (c.act - 1) : ''} dari ${c.win} bulan${c.act <= 1 ? ' <em>(tidak terpakai)</em>' : ''}</span></div>
          <div class="dsAr">${pl('IDLE')}<span>Tidak order ${c.win}–${c.inact - 1} bln berturut-turut</span></div>
          <div class="dsAr">${pl('INACTIVE')}<span>Tidak order ≥ ${nx('inact', c.inact, c.win + 1, 36)} bln berturut-turut</span></div>
          <div class="dsAr">${pl('PROSPECT')}<span>Belum pernah ambil brand ini sejak basis data (TOTAL: brand apa pun)</span></div></div>`;
    } else if (ST.tab === 'brand') {
      const on = d.brands.filter(b => !c.off.includes(b)).length, extra = c.off.filter(b => !d.brands.includes(b));
      body = `<div class="dsAc"><div class="dsAh">BRAND DI "ALL BRAND" <small>${on} dari ${d.brands.length} brand</small></div>
        ${d.brands.concat(extra).map(b => { const off = c.off.includes(b); return `<div class="dsAb${off ? ' off' : ''}" data-dsbr="${esc(b)}"><b>${esc(b)}</b><small>${off ? 'tidak ikut TOTAL' : ''}</small><span class="dsSw${off ? ' off' : ''}"></span></div>`; }).join('')}</div>
        <div class="dsAl">ⓘ <div>Per brand: dealer yang belum pernah ambil brand itu sejak basis data = <b>PROSPECT</b>. ALL BRAND = order brand mana pun yang dinyalakan. Status untuk <b>analisa</b>, tidak mempengaruhi KPI.</div></div>`;
    } else {
      const im = ST.imp;
      body = `<div class="dsAc"><div class="dsAh">CEK DAMPAK SEBELUM SIMPAN <small>status ${esc(mon)} · ALL BRAND</small></div>
        ${!im ? `<p class="hint">${ST.dirty ? 'Tekan HITUNG DAMPAK untuk melihat perubahan dari data asli.' : 'Belum ada perubahan aturan.'}</p><button type="button" class="chip" data-dsimp ${ST.dirty ? '' : 'disabled'}>HITUNG DAMPAK</button>`
          : im.err ? `<div class="banner bad">${esc(im.err)}</div>` : im.load ? '<p class="hint v6load">Menghitung dari data asli…</p>'
          : im.r.statuses.map(s => { const a = im.r.before.total[s] || 0, b = im.r.after.total[s] || 0, x = b - a; return `<div class="dsAd"><i style="background:${PCOL[s]}"></i><span>${s}</span><b>${num(b)}</b><em class="${x > 0 ? 'up' : x < 0 ? 'dn' : ''}">${x > 0 ? '+' : ''}${x ? num(x) : '–'}</em></div>`; }).join('') + `<p class="hint">Selisih dibanding status yang terkunci sekarang. KPI tidak terpengaruh.</p>`}</div>
        <div class="dsAc"><div class="dsAh">BERLAKU</div><p class="hint">Status ${esc(mon)} dihitung ulang dengan aturan baru, lalu dikunci lagi. Bulan depan (${esc(nxt)}) otomatis ikut aturan ini.</p>
          <label><small class="dsAlb">ALASAN PERUBAHAN (WAJIB)</small><input class="fi" id="dsWhy" placeholder="Contoh: ikut briefing RGM klasifikasi ST…" value="${esc(ST.why || '')}"></label>
          <p class="hint">${d.config.by ? 'Terakhir diubah ' + esc(d.config.by) + ' · ' + esc(String(d.config.at || '').slice(0, 10)) + '. ' : ''}Riwayat perubahan tersimpan di log aktivitas (siapa, kapan, alasan).</p></div>`;
    }
    box.innerHTML = `<div class="dsAt">${tabs}</div>${body}<div class="dsAbar"><button type="button" class="chip" data-dsreset>RESET</button><button type="button" class="chip primary" data-dssave ${ST.dirty ? '' : 'disabled'}>SIMPAN ATURAN</button></div><p class="hint" id="dsMsg" role="alert"></p>`;
  }
  const touch = () => { ST.dirty = true; ST.imp = null; };
  document.addEventListener('change', e => { if (!ST || !e.target.closest('#dsAdm')) return; if (e.target.matches('[data-dssince]')) { ST.cfg.since = e.target.value; touch(); dsAdm(); } });
  document.addEventListener('input', e => { if (ST && e.target.id === 'dsWhy') ST.why = e.target.value; });
  document.addEventListener('click', async e => {
    if (!ST || !e.target.closest('#dsAdm')) return; const t = e.target, c = ST.cfg;
    let x;
    if ((x = t.closest('[data-dst]'))) { ST.tab = x.dataset.dst; return dsAdm(); }
    if ((x = t.closest('[data-dsn]'))) { const k = x.dataset.dsn, dv = +x.dataset.dv; c[k] += dv;
      if (k === 'win') { c.win = Math.max(1, Math.min(12, c.win)); c.act = Math.min(c.act, c.win); c.inact = Math.max(c.inact, c.win + 1); }
      if (k === 'act') c.act = Math.max(1, Math.min(c.win, c.act)); if (k === 'inact') c.inact = Math.max(c.win + 1, Math.min(36, c.inact));
      touch(); return dsAdm(); }
    if ((x = t.closest('[data-dsbr]'))) { const b = x.dataset.dsbr; c.off = c.off.includes(b) ? c.off.filter(y => y !== b) : c.off.concat(b); touch(); return dsAdm(); }
    if (t.closest('[data-dsreset]')) { ST.cfg = JSON.parse(JSON.stringify(ST.data.config)); ST.dirty = false; ST.imp = null; return dsAdm(); }
    if (t.closest('[data-dsimp]')) { ST.imp = { load: true }; dsAdm();
      try { ST.imp = { r: await rpc('apiDealerStatusImpact', TOKEN, ST.cfg) }; } catch (err) { ST.imp = { err: err.message || String(err) }; }
      return dsAdm(); }
    if (t.closest('[data-dssave]')) {
      const msg = () => document.getElementById('dsMsg'), why = (ST.why || '').trim();
      if (!ST.imp?.r) { ST.tab = 'dampak'; dsAdm(); msg().textContent = 'Tekan HITUNG DAMPAK dulu sebelum menyimpan.'; return; }
      if (why.length < 5) { ST.tab = 'dampak'; dsAdm(); msg().textContent = 'Isi alasan perubahan (minimal 5 huruf).'; return; }
      if (!await dsmConfirm('Simpan aturan status dealer? Status bulan ini dihitung ulang dengan aturan baru.')) return;
      try { await rpc('apiDealerStatusSave', TOKEN, ST.cfg, why); const r = await rpc('apiDealerStatusRules', TOKEN); ST = { data: r, cfg: JSON.parse(JSON.stringify(r.config)), tab: 'rumus', dirty: false, imp: null }; dsAdm(); msg().textContent = 'Tersimpan. Buka ulang halaman Dealer untuk melihat status terbaru.'; }
      catch (err) { msg().textContent = err.message || err; }
    }
  });
})();
