/* ATURAN STATUS DEALER (Paket 2, 2026-11-17)
 * - Ringkasan atas halaman Dealer: kunci status + MTD, SUDAH ORDER = DA KPI, 6 kartu status, Bangkit bulan ini.
 * - Penanda bulan berjalan OPSI B di kotak brand: ✓ NOV · 4u / ½ NOV · 1u / BELUM NOV.
 * - Layar Admin › Brand › Aturan Status Dealer (Super Admin): pola, coba pola, dampak sebelum simpan.
 * Status dihitung server (lib/dealer_status.php). File ini hanya tampilan. Tidak menyentuh KPI. */
(function () {
  const MON = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
  const DSCOL = { 'AKTIF': ['#E2F5EA', '#0E6E48', '●'], 'DI BAWAH MIN': ['#FFF4D6', '#8A5A00', '½'], 'BANGKIT': ['#E3F4F6', '#0E7C8C', '↗'], 'TURUN': ['#FDE8D4', '#B4530E', '↘'],
    'IDLE': ['#FFF1DC', '#B7791F', '◐'], 'PASIF': ['#FBE3E1', '#A3201B', 'Ⅱ'], 'NONAKTIF': ['#E5E7EB', '#374151', '○'], 'LAINNYA': ['#EEF1F5', '#5B6778', '?'],
    'BELUM ORDER': ['#EEF2F7', '#62798B', '–'], 'NOO': ['#ECE6FB', '#6D3FD8', '★'], 'TUTUP': ['#E4E8EE', '#4D5A6B', '✕'] };
  const EXTRA = [['#E6EEFA', '#0B5CAD'], ['#EEE8FB', '#6D3FD8'], ['#E3F4F6', '#0E7C8C'], ['#FFF1DC', '#C25A12']];
  window.dsCol = s => DSCOL[s] || EXTRA[[...String(s)].reduce((a, c) => a + c.charCodeAt(0), 0) % EXTRA.length].concat('•');
  window.dsMon = p => MON[(+String(p || '').slice(5, 7) || 1) - 1];
  const SUSAH = ['IDLE', 'PASIF', 'TURUN', 'NONAKTIF'];
  const DESC = { 'AKTIF': 'Order (minimal unit) di bulan terakhir pola.', 'DI BAWAH MIN': 'Bulan terakhir order, tapi di bawah minimal unit.', 'BANGKIT': 'Sempat berhenti beberapa bulan, lalu order lagi di bulan terakhir pola.',
    'TURUN': 'Dua bulan order berturut-turut, lalu berhenti dua bulan.', 'IDLE': 'Terakhir order dua bulan lalu.', 'PASIF': 'Dua bulan terakhir tanpa order.', 'NONAKTIF': 'Enam bulan tanpa order.',
    'LAINNYA': 'Pola tidak cocok dengan aturan mana pun.', 'BELUM ORDER': 'Belum pernah order sampai bulan lalu.', 'NOO': 'Order pertama brand ini terjadi bulan ini.' };
  window.dsDesc = s => DESC[s] || 'Status buatan Super Admin. Lihat Admin › Brand › Aturan Status Dealer.';
  const pill = (s, txt) => { const c = dsCol(s); return `<span class="dsPill" style="background:${c[0]};color:${c[1]}">${esc(txt || s)}</span>`; };
  window.dsPill = pill;
  // ---------- mode ringkasan: TOTAL atau per brand ----------
  window.DSV = window.DSV || { brand: 'TOTAL' };
  function mode() { const bs = (REPORT.brands || []).map(b => b.brand); if (DSV.brand !== 'TOTAL' && !bs.includes(DSV.brand)) DSV.brand = 'TOTAL'; return DSV.brand; }
  function stOf(c, b) { if (c.status === 'TUTUP') return null; return b === 'TOTAL' ? c.status : (c.bs || {})[b] || null; }
  function orderOf(c, b) { const d = c.ds; if (!d) return { q: 0, da: false }; if (b === 'TOTAL') return { q: d.order?.q || 0, da: (d.order?.nda || 0) > 0, nda: d.order?.nda || 0 }; const x = (d.bc || {})[b]; return x ? { q: x.q || 0, da: !!x.da } : { q: 0, da: false }; }

  window.dsTopSummary = function (cs) {
    const info = REPORT.statusInfo; if (!info) return '';
    const b = mode(), M = dsMon(REPORT.period), cut = REPORT.coverage?.cutoff || '', day = +cut.slice(8, 10) || 1;
    const by = {}, ord = {}; let nOrder = 0, nDA = 0, nPair = 0; const bk = {};
    cs.forEach(c => { const s = stOf(c, b); if (!s) return; const o = orderOf(c, b); by[s] = (by[s] || 0) + 1; if (o.q > 0) { ord[s] = (ord[s] || 0) + 1; nOrder++; if (SUSAH.includes(s)) bk[s] = (bk[s] || 0) + 1; } if (o.da) nDA++; nPair += o.nda || 0; });
    const lockTxt = info.locked ? `<span class="dsChip lock"><em>🔒 STATUS</em>dikunci 1 ${M.charAt(0) + M.slice(1).toLowerCase()}</span>` : `<span class="dsChip temp"><em>⏳ STATUS</em>sementara · ${dsMon(info.months?.[5])} belum ditutup</span>`;
    const seg = `<div class="dsSeg" role="tablist"><span class="${b === 'TOTAL' ? 'on' : ''}" data-dsb="TOTAL">TOTAL</span>${(REPORT.brands || []).map(x => `<span class="${b === x.brand ? 'on' : ''}" data-dsb="${esc(x.brand)}">${esc(x.brand)}</span>`).join('')}</div>`;
    const big = b === 'TOTAL'
      ? `<div class="dsK big"><div class="ic">✓</div><div><span>SUDAH ORDER ${M}</span><div class="n">${num(nOrder)}</div><small>dealer · DA KPI (semua brand): <b>${num(nPair)}</b> pasangan dealer-brand</small><b class="dsKpi" data-dsi="total">≠ DA KPI ⓘ</b></div></div>`
      : `<div class="dsK big"><div class="ic">✓</div><div><span>SUDAH ORDER ${M} · ${esc(b)}</span><div class="n">${num(nDA)}</div><small>memenuhi minimal DA · ${num(nOrder - nDA)} lagi order di bawah minimal</small><b class="dsKpi" data-dsi="brand">= DA KPI ⓘ</b></div></div>`;
    const base = ['AKTIF', 'DI BAWAH MIN', 'TURUN', 'IDLE', 'PASIF', 'NONAKTIF'];
    const more = Object.keys(by).filter(s => !base.includes(s) && by[s] > 0);
    const card = s => { const c = dsCol(s); return `<div class="dsK" data-dss="${esc(s)}"><div class="ic" style="background:${c[0]};color:${c[1]}">${c[2]}</div><div><span>${esc(s)}</span><div class="n">${num(by[s] || 0)}</div><small>✓ ${num(ord[s] || 0)} sudah order ${M}</small></div></div>`; };
    const nb = SUSAH.reduce((a, s) => a + (bk[s] || 0), 0);
    return `<section class="dsTop"><div class="dsChips">${lockTxt}<span class="dsChip mtd"><em>MTD</em>1–${day} ${M.charAt(0) + M.slice(1).toLowerCase()} ${String(REPORT.period).slice(0, 4)}</span></div>${seg}
      <div class="dsSl">ORDER BULAN INI</div><div class="dsKg">${big}</div>
      <div class="dsSl">STATUS DEALER (POLA ${dsMon(info.months?.[0])}–${dsMon(info.months?.[5])}) <span class="dsI" data-dsi="status">i</span></div><div class="dsKg">${base.concat(more).map(card).join('')}</div>
      <div class="dsSl">BANGKIT BULAN INI · ${num(nb)} DEALER, SEBELUMNYA: <span class="dsI" data-dsi="bangkit">i</span></div>
      <div class="dsBk">${SUSAH.map(s => { const c = dsCol(s); const on = customerFilter.order === 'YES' && customerFilter.statuses.length === 1 && customerFilter.statuses[0] === s; return `<div class="${on ? 'on' : ''}" data-dsbk="${s}" style="background:${c[0]};color:${c[1]}"><b>${num(bk[s] || 0)}</b><small>${s}</small></div>`; }).join('')}</div></section>`;
  };
  const INFO = {
    total: 'Angka DA di KPI dihitung per pasangan dealer-brand (1 dealer yang order 2 brand = 2 DA). Di mode TOTAL, kartu ini menghitung DEALER, jadi angkanya berbeda. Pilih brand di atas untuk melihat angka yang sama persis dengan DA KPI brand itu.',
    brand: 'Sama persis dengan angka DA di KPI brand ini: minimal unit, tipe wajib, dan aturan promotor mengikuti editor KPI periode ini.',
    status: 'Status dikunci dari pola 6 bulan yang sudah selesai dan tidak berubah sampai bulan depan. Order bulan berjalan tidak mengubah status; lihat penanda ✓ / ½ / BELUM di kotak brand. Aturan diatur Super Admin di Admin › Brand › Aturan Status Dealer.',
    bangkit: 'Dealer yang status kuncinya Idle / Pasif / Turun / Nonaktif tapi sudah order bulan ini. Ketuk salah satu kotak untuk memfilter daftar dealer.' };
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
    const leg = `<div class="dsLeg"><span><i class="dsc y"></i>Order (DA)</span><span><i class="dsc h"></i>Di bawah min</span><span><i class="dsc n"></i>Tidak order</span><span><i class="dsc a"></i>Belum dealer</span></div>`;
    return `<section class="dsDet"><h4>Status dealer ${ds.locked ? '· 🔒 dikunci 1 ' + M.charAt(0) + M.slice(1).toLowerCase() : '· ⏳ sementara'}</h4>${head}<div class="dsDetRow dsTot"><b>TOTAL</b>${pill(ds.status)}${dsPola(ds.pola, ds.months)}<small>${esc(ds.aturan ? 'Kena aturan #' + ds.aturan : dsDesc(ds.status))}${ds.order?.q > 0 ? ` · order ${M}: ${num(ds.order.q)} unit` : ` · belum order ${M}`}</small></div>
      ${bs.map(b => { const x = ds.bc[b] || {}; return `<div class="dsDetRow"><b>${esc(b)}</b>${pill(ds.bs[b])}${dsPola(x.pola, ds.months)}<small>${x.q > 0 ? `${M}: ${num(x.q)} unit${x.da ? ' · memenuhi DA' : ' · di bawah minimal DA'}${x.last ? ' · terakhir ' + esc(tgl(x.last)) : ''}` : 'Belum order ' + M}</small></div>`; }).join('')}${leg}</section>`;
  };
  // ---------- ganti tampilan status lama ----------
  window.badge = function (status) {
    if (status === 'TUTUP') return '<span class="statusBadge v6st-tutup">DEALER TUTUP</span>';
    const c = dsCol(status); const label = ['AKTIF', 'IDLE', 'PASIF', 'TURUN', 'NONAKTIF', 'BANGKIT'].includes(status) ? 'DEALER ' + status : status;
    return `<span ${v6TipShort(dsDesc(status))} class="v6tipD statusBadge" style="background:${c[0]};color:${c[1]}">${esc(label)}</span>`;
  };
  window.v6BsBadge = function (c, b) { const s = c.bs && c.bs[b]; return s ? pill(s) + dsStrip(c, b) : ''; };
  window.v6BsMatch = function (c, sel) { const bs = c.bs || {}, f = customerFilter.brand; if (f && f !== 'ALL') return sel.includes(bs[f]); return Object.values(bs).some(x => sel.includes(x)); };
  window.v6BsSummary = function (c) {
    const bs = c.bs || {}, f = customerFilter.brand; if (f && f !== 'ALL') return bs[f] ? [bs[f], f + ' · ' + bs[f]] : null;
    const vals = Object.entries(bs); if (!vals.length) return null;
    for (const k of ['NONAKTIF', 'PASIF', 'IDLE', 'TURUN', 'DI BAWAH MIN', 'NOO']) { const n = vals.filter(x => x[1] === k); if (n.length) return [k, n.length > 1 ? k + ' di ' + n.length + ' brand' : k + ' di ' + n[0][0]]; }
    return ['AKTIF', 'AKTIF di semua brand'];
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
  window.dsStatusList = () => (REPORT.statusInfo?.statuses || ['AKTIF', 'DI BAWAH MIN', 'BANGKIT', 'TURUN', 'IDLE', 'PASIF', 'NONAKTIF', 'LAINNYA']).concat(['NOO', 'BELUM ORDER', 'TUTUP']);

  /* ---------- Admin › Brand › Aturan Status Dealer (Super Admin) ---------- */
  const CY = ['y', 'h', 'n', 'a'];
  let ST = null; // {cfg, data, scope:'*'|BRAND, sim}
  const isSA = () => (BOOT?.user?.role || '') === 'SUPER ADMIN';
  window.v6BrandThrPanel = function (v) {
    if (!isSA()) return;
    v.insertAdjacentHTML('beforeend', panel('Aturan Status Dealer', `<div id="dsAdm"><p class="hint v6load">Memuat…</p></div>`, '<span class="dsSA">SUPER ADMIN</span>'));
    rpc('apiDealerStatusRules', TOKEN).then(r => { ST = { data: r, cfg: JSON.parse(JSON.stringify(r.config)), scope: '*', sim: ['n', 'y', 'y', 'y', 'n', 'n'], dirty: false }; dsAdm(); })
      .catch(e => { const b = document.getElementById('dsAdm'); if (b) b.innerHTML = '<div class="banner bad">' + esc(e.message || e) + '</div>'; });
  };
  const curRules = () => ST.scope === '*' ? ST.cfg.rules : (ST.cfg.brands[ST.scope]?.rules || null);
  const curMin = () => ST.scope === '*' ? ST.cfg.min : (ST.cfg.brands[ST.scope]?.min ?? null);
  const effRules = () => curRules() || ST.cfg.rules;
  const statuses = () => ST.data.base.concat(ST.cfg.extra || []);
  function match(rules, cells) { for (let i = 0; i < rules.length; i++) if (rules[i][0].every((v, k) => v === 'a' || v === cells[k])) return [rules[i][1], i]; return ['LAINNYA', -1]; }
  function dsAdm() {
    const box = document.getElementById('dsAdm'); if (!box) return; const d = ST.data, ms = d.months.map(dsMon), M = dsMon(d.period);
    const rules = effRules(), own = ST.scope === '*' || !!curRules(), [hitS, hit] = match(rules, ST.sim), min = curMin(), effMin = min ?? ST.cfg.min;
    const da = ST.scope !== '*' ? d.da[ST.scope] : null;
    const cnt = ST.scope === '*' ? d.count.total : (d.count.brand[ST.scope] || {});
    box.innerHTML = `<p class="hint">Status dikunci tiap tanggal 1 dari pola <b>6 bulan yang sudah selesai</b> (sekarang ${ms[0]}–${ms[5]} untuk status ${M}). Dasar hitung SELL THRU, dari seluruh transaksi dealer. <b>Tidak dipakai KPI</b>: KPI, achievement, dan insentif tetap memakai aturan di editor KPI.</p>
      <div class="dsSeg dsScope"><span class="${ST.scope === '*' ? 'on' : ''}" data-dsc="*">SEMUA BRAND</span>${d.brands.map(b => `<span class="${ST.scope === b ? 'on' : ''}" data-dsc="${esc(b)}">${esc(b)}${ST.cfg.brands[b] ? ' •' : ''}</span>`).join('')}</div>
      <div class="dsSl">ORDER = MINIMAL UNIT PER BULAN</div>
      <div class="dsMin"><button type="button" data-dsm="-1">−</button><b>${min == null ? '<small>ikut umum</small> ' + ST.cfg.min : min}</b><button type="button" data-dsm="1">+</button><span>unit = ✓ order · 1–${Math.max(1, effMin - 1)} unit = ½ di bawah min${ST.scope !== '*' && min != null ? ' · <u data-dsm="reset">ikut umum</u>' : ''}</span></div>
      ${da ? `<p class="hint">Penanda bulan berjalan ${esc(ST.scope)} ikut DA KPI: minimal <b>${num(da.min)}</b> unit${da.types.length ? ' · tipe ' + esc(da.types.join(', ')) : ''}${da.must.length ? ' · wajib ' + esc(da.must.join(', ')) : ''}${da.perProm ? ' · ' + num(da.perProm) + '/promotor' : ''} (dari editor KPI, tidak diubah di sini).</p>` : ''}
      <div class="dsSl">ATURAN ${ST.scope === '*' ? 'UMUM' : esc(ST.scope)} · DICEK DARI ATAS</div>
      ${ST.scope !== '*' && !own ? `<div class="dsNote">${esc(ST.scope)} memakai aturan umum. <button type="button" class="chip" data-dsown>Buat aturan sendiri</button></div>` : ''}
      <div class="dsRules${own ? '' : ' ro'}"><div class="dsRh"><span></span>${ms.map(m => `<small>${m}</small>`).join('')}<small>STATUS</small></div>
      ${rules.map((r, i) => `<div class="dsRule${i === hit ? ' hit' : ''}"><span class="no">${i + 1}</span>${r[0].map((v, k) => `<i class="dsc ${v}" data-dsr="${i}" data-dsk="${k}">${SYM[v]}</i>`).join('')}<span class="st" data-dsp="${i}" style="background:${dsCol(r[1])[0]};color:${dsCol(r[1])[1]}">${esc(r[1])}</span>${own ? `<span class="ops"><u data-dsu="${i}">↑</u><u data-dsd="${i}">✕</u></span>` : ''}</div>`).join('')}</div>
      ${own ? `<div class="toolbar"><button type="button" class="chip" data-dsadd>+ Aturan</button>${ST.scope !== '*' ? '<button type="button" class="chip" data-dsdrop>Kembali ke aturan umum</button>' : ''}<button type="button" class="chip" data-dsnew>+ Status baru</button></div>` : ''}
      <p class="hint">✓ order · ½ di bawah min · ✕ tidak order · • bebas. Ketuk sel untuk mengganti, ketuk status untuk memilih. Tidak cocok aturan mana pun → LAINNYA.</p>
      <div class="dsSl">COBA POLA</div>
      <div class="dsSim">${ms.map((m, i) => `<div><small>${m}</small><i class="dsc ${ST.sim[i]}" data-dss2="${i}">${SYM[ST.sim[i]]}</i></div>`).join('')}</div>
      <div class="dsRes" style="background:${dsCol(hitS)[0]};color:${dsCol(hitS)[1]}"><div><small>STATUS ${M} (dikunci 1 ${M})</small><b>${esc(hitS)}</b></div><small>${hit < 0 ? 'tidak ada aturan cocok' : 'kena aturan #' + (hit + 1)}</small></div>
      <div class="dsSl">JUMLAH DEALER SEKARANG ${ST.scope === '*' ? '(TOTAL)' : '· ' + esc(ST.scope)}</div>
      <div class="dsCnt">${statuses().filter(s => cnt[s]).map(s => `${pill(s)}<b>${num(cnt[s])}</b>`).join('') || '<span class="hint">Belum ada data.</span>'}</div>
      <div class="dsSl">SIMPAN</div>
      <div class="toolbar"><input class="fi" id="dsWhy" placeholder="Alasan perubahan, minimal 5 karakter" style="min-width:240px;flex:1"><button type="button" class="chip" data-dsimp>Lihat dampak</button><button type="button" class="chip primary" data-dssave ${ST.dirty ? '' : 'disabled'}>Simpan</button></div>
      <div id="dsImp"></div><p class="hint" id="dsMsg" role="alert"></p>
      <p class="hint">${d.config.by ? 'Terakhir diubah ' + esc(d.config.by) + ' · ' + esc(String(d.config.at || '').slice(0, 10)) + '. ' : ''}Periode lampau yang sudah dikunci tidak ikut berubah; aturan baru berlaku untuk status ${M} dan seterusnya.</p>`;
  }
  const touch = () => { ST.dirty = true; const i = document.getElementById('dsImp'); if (i) i.innerHTML = ''; };
  document.addEventListener('click', async e => {
    if (!ST || !e.target.closest('#dsAdm,#dsOv')) return; const t = e.target, rules = curRules();
    const g = (sel, k) => { const x = t.closest(sel); return x ? x.dataset[k] : null; };
    let v;
    if ((v = g('[data-dsc]', 'dsc')) !== null) { ST.scope = v; return dsAdm(); }
    if ((v = g('[data-dsm]', 'dsm')) !== null) { touch(); if (v === 'reset') { if (ST.cfg.brands[ST.scope]) { ST.cfg.brands[ST.scope].min = null; if (!ST.cfg.brands[ST.scope].rules) delete ST.cfg.brands[ST.scope]; } return dsAdm(); }
      if (ST.scope === '*') ST.cfg.min = Math.max(1, Math.min(50, ST.cfg.min + +v)); else { const b = ST.cfg.brands[ST.scope] = ST.cfg.brands[ST.scope] || { min: null, rules: null }; b.min = Math.max(1, Math.min(50, (b.min ?? ST.cfg.min) + +v)); } return dsAdm(); }
    if (t.closest('[data-dsown]')) { touch(); const b = ST.cfg.brands[ST.scope] = ST.cfg.brands[ST.scope] || { min: null, rules: null }; b.rules = JSON.parse(JSON.stringify(ST.cfg.rules)); return dsAdm(); }
    if (t.closest('[data-dsdrop]')) { touch(); const b = ST.cfg.brands[ST.scope]; if (b) { b.rules = null; if (b.min == null) delete ST.cfg.brands[ST.scope]; } return dsAdm(); }
    if (rules && (v = g('[data-dsr]', 'dsr')) !== null) { touch(); const k = +t.closest('[data-dsk]').dataset.dsk, r = rules[+v][0]; r[k] = CY[(CY.indexOf(r[k]) + 1) % 4]; return dsAdm(); }
    if (rules && (v = g('[data-dsu]', 'dsu')) !== null) { const i = +v; if (i > 0) { touch(); [rules[i - 1], rules[i]] = [rules[i], rules[i - 1]]; } return dsAdm(); }
    if (rules && (v = g('[data-dsd]', 'dsd')) !== null) { if (rules.length > 1) { touch(); rules.splice(+v, 1); } return dsAdm(); }
    if (rules && t.closest('[data-dsadd]')) { touch(); rules.push([['a', 'a', 'a', 'a', 'a', 'a'], 'LAINNYA']); return dsAdm(); }
    if ((v = g('[data-dss2]', 'dss2')) !== null) { const o = ['y', 'h', 'n']; ST.sim[+v] = o[(o.indexOf(ST.sim[+v]) + 1) % 3]; return dsAdm(); }
    if (rules && (v = g('[data-dsp]', 'dsp')) !== null) { ST.pick = +v; dsSheet('Pilih status', `<div class="dsOpts">${statuses().map(s => `<div data-dso="${esc(s)}" style="background:${dsCol(s)[0]};color:${dsCol(s)[1]}">${esc(s)}</div>`).join('')}</div>`); return; }
    if ((v = g('[data-dso]', 'dso')) !== null) { touch(); curRules()[ST.pick][1] = v; document.getElementById('dsOv').classList.remove('on'); return dsAdm(); }
    if (t.closest('[data-dsnew]')) { const s = (prompt('Nama status baru (maksimal 12 huruf, satu baris):') || '').trim().toUpperCase(); if (!s) return; if (s.length > 12) return alert('Maksimal 12 huruf.'); if (!statuses().includes(s)) { touch(); ST.cfg.extra = (ST.cfg.extra || []).concat(s); } return dsAdm(); }
    if (t.closest('[data-dsimp]')) {
      const box = document.getElementById('dsImp'); box.innerHTML = '<p class="hint v6load">Menghitung dampak dari data asli…</p>';
      try { const r = await rpc('apiDealerStatusImpact', TOKEN, ST.cfg); ST.impactOk = true;
        const sc = ST.scope === '*' ? 'total' : 'brand', A = sc === 'total' ? r.before.total : (r.before.brand[ST.scope] || {}), B = sc === 'total' ? r.after.total : (r.after.brand[ST.scope] || {});
        box.innerHTML = `<div class="dsImp"><div class="h"><span>STATUS ${dsMon(r.period)} ${ST.scope === '*' ? '· TOTAL' : '· ' + esc(ST.scope)}</span><span>SEKARANG</span><span>SESUDAH</span><span></span></div>${r.statuses.filter(s => A[s] || B[s]).map(s => { const a = A[s] || 0, b = B[s] || 0, d = b - a; return `<div><span>${pill(s)}</span><span>${num(a)}</span><b>${num(b)}</b><span class="d ${d > 0 ? 'up' : d < 0 ? 'dn' : ''}">${d > 0 ? '+' : ''}${d ? num(d) : '–'}</span></div>`; }).join('')}</div><p class="hint">Dihitung dari seluruh dealer, data asli periode ${esc(r.period)}. KPI tidak terpengaruh.</p>`;
      } catch (err) { box.innerHTML = '<div class="banner bad">' + esc(err.message || err) + '</div>'; }
      return;
    }
    if (t.closest('[data-dssave]')) {
      const msg = document.getElementById('dsMsg'), why = document.getElementById('dsWhy').value;
      if (!ST.impactOk) { msg.textContent = 'Tekan "Lihat dampak" dulu sebelum menyimpan.'; return; }
      if (!confirm('Simpan aturan status dealer? Status bulan ini dihitung ulang dengan aturan baru.')) return;
      try { await rpc('apiDealerStatusSave', TOKEN, ST.cfg, why); const r = await rpc('apiDealerStatusRules', TOKEN); ST = { data: r, cfg: JSON.parse(JSON.stringify(r.config)), scope: ST.scope, sim: ST.sim, dirty: false }; dsAdm(); document.getElementById('dsMsg').textContent = 'Tersimpan. Buka ulang halaman Dealer untuk melihat status terbaru.'; }
      catch (err) { msg.textContent = err.message || err; }
    }
  });
})();
