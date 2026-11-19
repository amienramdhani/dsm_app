/* =====================================================================
   DSM · Rapikan halaman desktop (mockup disetujui 17 Nov 2026)
   Dealer · Tim (bagian atas) · DOS · Transaksi · Peringkat
   - Khusus desktop (>= 821px). HP tidak disentuh.
   - Tidak mengubah data, rumus, atau handler: elemen asli DIPINDAH / disembunyikan,
     tidak ditulis ulang, jadi filter dan klik tetap memakai kode lama.
   - Berjalan ulang tiap #main dirender; setiap langkah idempotent (penanda data-pt).
   ===================================================================== */
(function () {
  'use strict';
  var mq = window.matchMedia('(min-width:821px)');
  var $q = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var n_ = function (t) { return parseInt(String(t || '').replace(/[^\d]/g, ''), 10) || 0; };
  var page = function () { try { return typeof PAGE !== 'undefined' ? PAGE : ''; } catch (e) { return ''; } };

  /* ---------------------------------------------------------------- DEALER */
  var STC = { 'AKTIF': '#12A86B', 'DI BAWAH MIN': '#F2C230', 'BANGKIT': '#22B0C4', 'TURUN': '#E8883A', 'IDLE': '#F2B04E', 'PASIF': '#E5534B', 'NONAKTIF': '#9AA3AE', 'LAINNYA': '#B7C0CC', 'BELUM ORDER': '#D5DCE6', 'ACTIVE': '#2E9E45', 'PASSIVE': '#E89A00', 'INACTIVE': '#C62E2E', 'PROSPECT': '#1E7FD6' };
  function stColor(s) { if (STC[s]) return STC[s]; try { var c = dsCol(s); return c && c[1] ? c[1] : '#9AA3AE'; } catch (e) { return '#9AA3AE'; } }

  function kpiCard(c, lab, val, sub, extra) {
    return '<div class="ptK" style="--c:' + c + '"><small>' + lab + '</small><b class="ptKv">' + val + '</b><span class="ptKs">' + sub + '</span>' + (extra || '') + '</div>';
  }

  function dealerKpi(top, view) {
    if (top.dataset.ptK) return;
    var big = $q('.dsK.big', top); if (!big) return;
    var kp = $$('.kpis .kpi', view);
    var noo = kp[0], so = kp[1];
    var order = $q('.dsSl', top);
    var M = ($q('span', big) || {}).textContent || 'SUDAH ORDER';
    var bkBoxes = $$('.dsBk [data-dsbk]', top);
    var bkTot = bkBoxes.reduce(function (a, b) { return a + n_(($q('b', b) || {}).textContent); }, 0);
    var card1 = kpiCard('#0E7C6B', esc(M.replace(/\s+/g, ' ').trim()), ($q('.n', big) || {}).textContent || '0', ($q('small', big) || {}).innerHTML || '');
    var h = '<div class="ptK4" id="ptK4">' + card1;
    h += noo ? kpiCard('#6D3FD8', esc(($q('.klab', noo) || {}).textContent || 'NOO'), ($q('.kval', noo) || {}).innerHTML || '', esc(($q('.ksub', noo) || {}).textContent || '')) : '';
    var bk = bkBoxes.map(function (b) { var s = b.dataset.dsbk; return '<a href="#" class="ptBk' + (b.classList.contains('on') ? ' on' : '') + '" data-dsbk="' + esc(s) + '">' + esc(s) + ' <b>' + n_(($q('b', b) || {}).textContent) + '</b></a>'; }).join(' · ');
    h += so ? kpiCard('#C25A12', esc(($q('.klab', so) || {}).textContent || 'SELL OUT'), ($q('.kval', so) || {}).innerHTML || '', esc(($q('.ksub', so) || {}).textContent || '')) : '';
    h += '</div>';
    var holder = document.createElement('div'); holder.innerHTML = h; var row = holder.firstChild;
    /* ikon keterangan DA KPI asli dipindah ke kartu pertama supaya klik ⓘ tetap jalan */
    var kb = $q('.dsKpi', big); if (kb) { kb.classList.add('ptKpiBtn'); $q('.ptK', row).appendChild(kb); }
    var seg = $q('.dsSeg', top); (seg ? seg.after(row) : top.prepend(row));
    $$('.dsSl', top).forEach(function (el) { if (/ORDER BULAN INI|BANGKIT/.test(el.textContent)) el.classList.add('ptHide'); });
    var kgs = $$('.dsKg', top); if (kgs[0]) kgs[0].classList.add('ptHide');
    var bkEl = $q('.dsBk', top); if (bkEl) bkEl.classList.add('ptHide');
    var oldKp = $q('.kpis', view); if (oldKp) oldKp.classList.add('ptHide');
    top.dataset.ptK = '1';
  }

  function dealerStatus(top) {
    if (top.dataset.ptS) return;
    var kgs = $$('.dsKg', top), kg = kgs[kgs.length - 1];
    var sl = $$('.dsSl', top).filter(function (el) { return /STATUS DEALER/.test(el.textContent); })[0];
    if (!kg || !sl || kg.classList.contains('ptHide')) return;
    var chips = $$('.dsK[data-dss]', kg); if (!chips.length) return;
    var tot = 0, parts = chips.map(function (c) {
      var s = c.dataset.dss, n = n_(($q('.n', c) || {}).textContent); tot += n;
      c.style.setProperty('--c', stColor(s)); return { s: s, n: n };
    });
    var bar = '<div class="ptBar">' + parts.map(function (p) {
      return p.n > 0 ? '<i data-dss="' + esc(p.s) + '" title="' + esc(p.s) + ' · ' + p.n + '" style="flex:' + p.n + ' 1 0;background:' + stColor(p.s) + '"></i>' : '';
    }).join('') + '</div>';
    var wrap = document.createElement('div'); wrap.className = 'ptSt';
    var head = document.createElement('div'); head.className = 'ptStH';
    head.innerHTML = '<span></span><em>Klik status untuk menyaring daftar di bawah</em>';
    head.firstChild.appendChild(sl);
    wrap.appendChild(head); wrap.insertAdjacentHTML('beforeend', bar); wrap.appendChild(kg);
    top.appendChild(wrap);
    top.dataset.ptS = '1'; syncStatusOn(top);
  }
  function syncStatusOn(top) {
    try {
      $$('.dsK[data-dss]', top).forEach(function (c) {
        var s = c.dataset.dss; c.classList.toggle('on', customerFilter.statuses.length === 1 && customerFilter.statuses[0] === s && customerFilter.order === 'ALL');
      });
    } catch (e) { }
  }

  var OTHER = [
    ['csubregion', 'Sub Region', function () { return customerFilter.subRegion; }],
    ['cprom', 'Promotor', function () { return customerFilter.prom; }],
    ['cgrade', 'Grade', function () { return customerFilter.grade; }],
    ['ccity', 'Kota', function () { return customerFilter.city; }]
  ];
  function dealerFilter(view) {
    var card = $q('.customerFilterCard', view); if (!card || card.dataset.ptF) return;
    var grid = $q('.customerFilterGrid', card); if (!grid) return;
    var lab = function (id) { var el = document.getElementById(id); return el ? el.closest('label') : null; };
    var bar = document.createElement('div'); bar.className = 'ptFb';
    ['cq', 'cregion', 'cbrand', 'corder'].forEach(function (id) {
      var l = lab(id); if (l) { l.classList.add('ptFi', 'pt-' + id); bar.appendChild(l); var el = document.getElementById(id); if (el && !el.getAttribute('aria-label')) el.setAttribute('aria-label', l.textContent.trim()); }
    });
    var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'ptMore'; btn.setAttribute('aria-expanded', 'false');
    btn.innerHTML = 'FILTER LAIN <span class="ptMoreN" hidden>0</span>';
    bar.appendChild(btn);
    var chips = document.createElement('div'); chips.className = 'ptChips'; bar.appendChild(chips);
    var sum = $q('#customerFilterSummary', card); if (sum) { sum.classList.add('ptSum'); bar.appendChild(sum); }
    var act = $q('.filterActions', card); if (act) { act.classList.add('ptAct'); bar.appendChild(act); }
    card.prepend(bar);
    grid.classList.add('ptOtherGrid'); grid.hidden = true;
    btn.onclick = function () { grid.hidden = !grid.hidden; btn.setAttribute('aria-expanded', String(!grid.hidden)); btn.classList.toggle('on', !grid.hidden); };
    card.classList.add('ptTidy');
    card.dataset.ptF = '1';

    function refresh() {
      var n = 0, h = '';
      try {
        OTHER.forEach(function (o) { var v = o[2](); if (v && v !== 'ALL') { n++; h += '<span class="ptChip" data-ptx="' + o[0] + '">' + o[1].toUpperCase() + ': ' + esc(v) + ' <b>✕</b></span>'; } });
        customerFilter.statuses.forEach(function (s) { n++; h += '<span class="ptChip" data-ptxs="' + esc(s) + '">STATUS: ' + esc(s) + ' <b>✕</b></span>'; });
      } catch (e) { }
      chips.innerHTML = h;
      var b = $q('.ptMoreN', btn); b.textContent = n; b.hidden = !n;
      syncStatusOn($q('.dsTop', view) || document);
    }
    chips.addEventListener('click', function (e) {
      var x = e.target.closest('.ptChip'); if (!x) return;
      if (x.dataset.ptx) { var el = document.getElementById(x.dataset.ptx); if (el) { el.value = 'ALL'; el.dispatchEvent(new Event('change', { bubbles: true })); } }
      else if (x.dataset.ptxs) { var cb = $$('[name="customerStatus"]', card).filter(function (c) { return c.value === x.dataset.ptxs; })[0]; if (cb) { cb.checked = false; cb.dispatchEvent(new Event('change', { bubbles: true })); } }
      setTimeout(refresh, 0);
    });
    card.addEventListener('change', function () { setTimeout(refresh, 0); });
    card.addEventListener('click', function (e) { if (e.target.closest('#creset')) setTimeout(refresh, 30); });
    if (sum) new MutationObserver(function () { setTimeout(refresh, 0); }).observe(sum, { childList: true });
    refresh();
    /* legenda sel brand di bawah tabel */
    var rows = $q('#customerRows', view);
    if (rows && !$q('.ptLeg', view)) rows.insertAdjacentHTML('afterend', '<div class="ptLeg"><b>Cara baca brand:</b> angka besar = ST bulan ini · <span class="ptLg0">0</span> = belum order · <span class="ptLg0">–</span> = belum jadi dealer brand ini · baris abu kecil = potensi / peluang</div>');
  }

  function dealerCells(view) {
    $$('#customerRows td', view).forEach(function (td) {
      if (td.dataset.ptC) return;
      var pill = $q('.dsPill', td), pc = $q('.v6pc', td);
      if (!pill && !pc) return;
      td.dataset.ptC = '1';
      var strip = $q('.dsStrip', td); if (strip) strip.remove();
      var wrap = document.createElement('div'); wrap.className = 'ptC';
      var r1 = document.createElement('div'); r1.className = 'ptC1';
      if (pc) {
        var b = $q('b', pc), sm = $q('small', pc);
        if (b) r1.appendChild(b);
        if (pill) r1.appendChild(pill);
        wrap.appendChild(r1);
        if (sm) { sm.classList.add('ptCs'); wrap.appendChild(sm); }
        pc.remove();
      } else {
        var txt = ''; Array.prototype.slice.call(td.childNodes).forEach(function (nd) { if (nd.nodeType === 3 && nd.textContent.trim()) { txt += nd.textContent.trim(); nd.remove(); } });
        var nb = document.createElement('b'); nb.textContent = txt; r1.appendChild(nb);
        if (pill) r1.appendChild(pill);
        wrap.appendChild(r1);
      }
      td.prepend(wrap);
    });
  }

  function tidyDealer() {
    var view = $q('#view'); if (!view) return;
    var top = $q('.dsTop', view);
    if (top) { dealerKpi(top, view); dealerStatus(top); }
    dealerFilter(view);
    dealerCells(view);
  }


  /* ---------------------------------------------------------------- TIM (bagian atas) */
  function tidyTim() {
    var view = $q('#view'); if (!view) return;
    var lv = $q('#teamLevel', view), tb = lv && lv.closest('.toolbar');
    if (tb && !tb.dataset.ptT) {
      tb.dataset.ptT = '1'; tb.classList.add('ptTb');
      var big = $q('#teamBig', tb), sub = $q('#teamSub', tb);
      var lb = big && big.closest('label'), ls = sub && sub.closest('label');
      var more = document.createElement('div'); more.className = 'ptTbMore'; more.hidden = true;
      if (lb) more.appendChild(lb); if (ls) more.appendChild(ls);
      var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'ptMore'; btn.setAttribute('aria-expanded', 'false');
      btn.innerHTML = 'FILTER LAIN <span class="ptMoreN" hidden>0</span>';
      tb.appendChild(btn); tb.after(more);
      var upd = function () {
        var n = 0; try { if (teamScope.big !== 'ALL') n++; if (teamScope.sub !== 'ALL') n++; } catch (e) { }
        var b = $q('.ptMoreN', btn); b.textContent = n; b.hidden = !n;
      };
      btn.onclick = function () { more.hidden = !more.hidden; btn.setAttribute('aria-expanded', String(!more.hidden)); btn.classList.toggle('on', !more.hidden); };
      upd();
    }
    var panel = $q('#v6EW .panel', view);
    if (panel && !panel.dataset.ptE) {
      var ph = $q('.ph', panel), ctrl = $q('.v6ctrl', panel);
      if (!ph || !ctrl) return;
      panel.dataset.ptE = '1'; panel.classList.add('ptEw');
      var seg = $q('.v6seg', ctrl), tg = $q('.hint', ctrl);
      var oh = $q('.hint', ph); if (oh) oh.classList.add('ptHide');
      var h3 = $q('h3', ph);
      if (h3 && seg) h3.after(seg);
      if (tg && seg) { tg.classList.add('ptTg'); tg.textContent = tg.textContent.replace(/\s*·\s*ikut filter di atas/, ''); seg.after(tg); }
      ctrl.classList.add('ptHide');
      var kp = $$('.v6kpis .kpi', panel), map = ['good', 'bad', 'warn', ''], col = ['#0E7C6B', '#C0362C', '#D68A00', '#0B5CAD'];
      kp.forEach(function (k, i) {
        k.style.setProperty('--c', col[i] || '#0B5CAD');
        if (map[i]) { k.dataset.ptSt = map[i]; k.setAttribute('role', 'button'); k.tabIndex = 0; k.title = 'Klik untuk menyaring tabel'; }
      });
      var apply = function () {
        var cur = panel.dataset.ptF || '';
        $$('.v6kpis .kpi', panel).forEach(function (k) { k.classList.toggle('on', !!cur && k.dataset.ptSt === cur); });
        $$('.v6ewTable tbody tr', panel).forEach(function (tr) {
          if (/^\s*Total\s*$/.test((tr.cells[0] || {}).textContent || '')) { tr.hidden = false; return; }
          var pill = $q('.v6pill', tr); var ok = !cur || (pill && pill.classList.contains('v6' + cur));
          tr.hidden = !ok;
        });
      };
      var toggle = function (k) { if (!k || !k.dataset.ptSt) return; panel.dataset.ptF = panel.dataset.ptF === k.dataset.ptSt ? '' : k.dataset.ptSt; apply(); };
      panel.addEventListener('click', function (e) { toggle(e.target.closest('.v6kpis .kpi')); });
      panel.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { var k = e.target.closest('.v6kpis .kpi'); if (k) { e.preventDefault(); toggle(k); } } });
    }
  }

  /* ---------------------------------------------------------------- DOS */
  var KD = [
    ['BUTUH TINDAKAN', '#C0362C', [['HABIS', '#C0362C'], ['MENIPIS', '#E0701C'], ['PERLU CEK DATA', '#D68A00']]],
    ['AMAN', '#0E7C6B', [['CUKUP', '#0E7C6B'], ['STOK TINGGI', '#1E5FB8']]],
    ['TIDAK BERGERAK', '#6B7686', [['BELUM BERGERAK', '#6D3FD8'], ['TIDAK AKTIF', '#6B7686']]]
  ];
  function infoPop(btn, html) {
    var pop = document.createElement('div'); pop.className = 'ptIp'; pop.hidden = true; pop.innerHTML = typeof html === 'function' ? '' : html;
    btn.after(pop);
    btn.onclick = function (e) { e.stopPropagation(); if (typeof html === 'function') pop.innerHTML = html(); pop.hidden = !pop.hidden; };
    document.addEventListener('click', function f(e) { if (!document.body.contains(btn)) { document.removeEventListener('click', f); return; } if (!pop.contains(e.target)) pop.hidden = true; });
  }
  function dosApply() { var b = document.getElementById('dosApply'); if (b) b.click(); }
  function dosSetStatus(v) {
    var el = document.getElementById('dosStatus'); if (!el) return;
    el.value = v; dosApply();
  }
  function tidyDOS() {
    var view = $q('#view'); if (!view) return;
    var fl = $q('.dosFilters', view), kp = $q('.kpis', view);
    if (!fl || !kp || fl.dataset.ptD) return;   /* belum selesai dirender / sudah dirapikan */
    fl.dataset.ptD = '1';
    var basis = $q('.dosBasis', view), info = $q('.info', view), xb = document.getElementById('dosExportBtn');

    /* info 1 baris + ⓘ */
    if (info) {
      var parts = info.innerHTML.split(/<br\s*\/?>/i);
      var first = (parts[0] || '').replace(/Estimasi stok per/i, 'Posisi stok').replace(/\s*·\s*/g, '</span><span class="ptIs">').replace(/Sell Thru s\.d\./i, 'ST s.d.').replace(/Sell Out s\.d\./i, 'SO s.d.');
      var rest = parts.slice(1).join('<br>');
      info.className = 'ptDosInfo';
      info.innerHTML = '<span class="ptIs">' + first + '</span>' + (rest ? '<span class="ptIs">' + rest.replace(/<span class="hint">[\s\S]*?<\/span>/, '').replace(/^Kecepatan Sell Out:/i, 'Kecepatan SO').replace(/\.\s*Stok dihitung[\s\S]*$/, '') + '</span><button type="button" class="ptI" aria-label="Keterangan">i</button>' : '');
      var ib = $q('.ptI', info);
      if (ib) infoPop(ib, rest.replace(/<span class="hint">([\s\S]*?)<\/span>/, '$1'));
    }

    /* pilihan DOS + Export satu baris */
    if (basis) {
      var row = document.createElement('div'); row.className = 'ptDosRow';
      basis.before(row); row.appendChild(basis);
      var tbx = xb && xb.closest('.toolbar'); if (xb) { xb.classList.add('ptExp'); row.appendChild(xb); }
      if (tbx && !tbx.children.length) tbx.classList.add('ptHide');
    }

    /* 5 kartu ramping, 2 terakhir bisa diklik */
    var cards = $$('.kpi', kp), col = ['#1E5FB8', '#C25A12', '#0E7C6B', '#C0362C', '#D68A00'], stt = ['', '', '', 'MENIPIS', 'PERLU CEK DATA'];
    var h = '<div class="ptK5">' + cards.map(function (k, i) {
      var l = ($q('.klab', k) || {}).textContent || '', v = ($q('.kval', k) || {}).innerHTML || '', sb = ($q('.ksub', k) || {}).textContent || '';
      if (stt[i]) sb += ' · klik untuk saring';
      return '<div class="ptK' + (stt[i] ? ' ptKc' : '') + '" style="--c:' + col[i] + '"' + (stt[i] ? ' data-pt-ds="' + stt[i] + '" role="button" tabindex="0"' : '') + '><small>' + esc(l) + '</small><b class="ptKv">' + v + '</b><span class="ptKs">' + esc(sb) + '</span></div>';
    }).join('') + '</div>';
    kp.insertAdjacentHTML('afterend', h); kp.classList.add('ptHide');
    var k5 = $q('.ptK5', view);
    k5.addEventListener('click', function (e) { var c = e.target.closest('[data-pt-ds]'); if (c) dosSetStatus(c.dataset.ptDs); });
    k5.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { var c = e.target.closest('[data-pt-ds]'); if (c) { e.preventDefault(); dosSetStatus(c.dataset.ptDs); } } });
    var cur = '', sel = document.getElementById('dosStatus'); cur = sel ? sel.value : '';
    $$('.ptKc', k5).forEach(function (c) { c.classList.toggle('on', c.dataset.ptDs === cur); });

    /* bar filter: cari + Region + Brand + FILTER LAIN + RESET (langsung jalan) */
    var lab = function (id) { var el = document.getElementById(id); return el ? el.closest('label') : null; };
    var bar = document.createElement('div'); bar.className = 'ptFb ptFbD';
    var lq = lab('dosQuery'), lr = lab('dosRegion'), lbd = lab('dosBrand'), ls = lab('dosSubRegion'), lt = lab('dosType'), lk = lab('dosStatus');
    var qi = document.getElementById('dosQuery'); if (qi) { qi.placeholder = 'Cari dealer / TYPE / sales…'; qi.setAttribute('aria-label', 'Cari dealer, TYPE, atau sales'); }
    [[lq, 'pt-dq'], [lr, 'pt-dr'], [lbd, 'pt-db']].forEach(function (a) {
      if (!a[0]) return; a[0].classList.add('ptFi', a[1]);
      var el = $q('select,input', a[0]); if (el && !el.getAttribute('aria-label')) el.setAttribute('aria-label', a[0].textContent.trim());
      bar.appendChild(a[0]);
    });
    var more = document.createElement('button'); more.type = 'button'; more.className = 'ptMore'; more.setAttribute('aria-expanded', 'false');
    var nOther = 0; try { if (dosFilter.subRegion !== 'ALL') nOther++; if (dosFilter.type !== 'ALL') nOther++; } catch (e) { }
    more.innerHTML = 'FILTER LAIN <span class="ptMoreN"' + (nOther ? '' : ' hidden') + '>' + nOther + '</span>';
    bar.appendChild(more);
    var rs = document.getElementById('dosReset');
    if (rs) { var act = document.createElement('div'); act.className = 'ptAct'; act.appendChild(rs); bar.appendChild(act); }
    var grid = document.createElement('div'); grid.className = 'ptOtherGrid ptOtherD'; grid.hidden = !nOther;
    [ls, lt].forEach(function (l) { if (l) { var el = $q('select', l); if (el) el.setAttribute('aria-label', l.textContent.trim()); grid.appendChild(l); } });
    more.onclick = function () { grid.hidden = !grid.hidden; more.setAttribute('aria-expanded', String(!grid.hidden)); more.classList.toggle('on', !grid.hidden); };
    if (nOther) more.classList.add('on');
    fl.before(bar); bar.after(grid);
    /* langsung jalan: pilihan berubah = terapkan; kotak cari: Enter / keluar kotak */
    ['dosRegion', 'dosSubRegion', 'dosBrand', 'dosType'].forEach(function (id) { var el = document.getElementById(id); if (el) el.addEventListener('change', function () { setTimeout(dosApply, 0); }); });
    if (qi) qi.addEventListener('change', function () { setTimeout(dosApply, 0); });

    /* kondisi: chip berkelompok (opsi B), menggantikan dropdown Kondisi */
    var kd = document.createElement('div'); kd.className = 'ptKd';
    kd.innerHTML = '<span class="ptKdL">KONDISI</span>' + KD.map(function (g) {
      return '<span class="ptKg" style="--g:' + g[1] + '"><em>' + g[0] + '</em>' + g[2].map(function (c) {
        return '<button type="button" class="ptKc2' + (c[0] === cur ? ' on' : '') + '" data-pt-kd="' + c[0] + '" style="--c:' + c[1] + '"><i></i>' + c[0] + '</button>';
      }).join('') + '</span>';
    }).join('') + '<button type="button" class="ptI" aria-label="Arti kondisi">i</button>';
    grid.after(kd);
    kd.addEventListener('click', function (e) { var b = e.target.closest('[data-pt-kd]'); if (!b) return; dosSetStatus(b.classList.contains('on') ? 'ALL' : b.dataset.ptKd); });
    var kib = $q('.ptI', kd), leg = ''; try { leg = V12LEG.map(function (x) { return '<div><b>' + x[1] + '</b> ' + x[2] + '</div>'; }).join(''); } catch (e) { }
    if (kib) infoPop(kib, leg || 'Kondisi dihitung dari DOS dan kualitas data stok.');
    fl.classList.add('ptHide'); fl.style.setProperty('display', 'none', 'important');
  }

  /* ---------------------------------------------------------------- TRANSAKSI */
  function iso(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  function addDays(s, n) { var d = new Date(s + 'T00:00:00'); d.setDate(d.getDate() + n); return iso(d); }
  function tidyTrx() {
    var view = $q('#view'); if (!view) return;
    var kp = $q('.kpis', view);
    if (kp && !kp.dataset.ptT) {
      kp.dataset.ptT = '1';
      var col = ['#1E5FB8', '#1E5FB8', '#C25A12', '#9A6400'];
      var h = '<div class="ptK4">' + $$('.kpi', kp).map(function (k, i) {
        return kpiCard(col[i] || '#1E5FB8', esc(($q('.klab', k) || {}).textContent || ''), ($q('.kval', k) || {}).innerHTML || '', esc(($q('.ksub', k) || {}).textContent || ''));
      }).join('') + '</div>';
      kp.insertAdjacentHTML('afterend', h); kp.classList.add('ptHide');
    }
    var tb = $q('.transactionDetailPanel .toolbar', view);
    if (tb && !tb.dataset.ptQ) {
      var fr = document.getElementById('trxFrom'), to = document.getElementById('trxTo'); if (!fr || !to) return;
      tb.dataset.ptQ = '1'; tb.classList.add('ptTrx');
      var mx = to.max || to.value, mn = fr.min || fr.value;
      var Q = [['HARI INI', mx, mx], ['7 HARI', addDays(mx, -6) < mn ? mn : addDays(mx, -6), mx], ['BULAN INI', mn, mx]];
      var box = document.createElement('div'); box.className = 'ptQk';
      box.innerHTML = Q.map(function (q) { return '<button type="button" data-a="' + q[1] + '" data-b="' + q[2] + '">' + q[0] + '</button>'; }).join('');
      var mark = function () { $$('button', box).forEach(function (b) { b.classList.toggle('on', fr.value === b.dataset.a && to.value === b.dataset.b); }); };
      box.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) return;
        fr.value = b.dataset.a; to.value = b.dataset.b;
        fr.dispatchEvent(new Event('change', { bubbles: true })); to.dispatchEvent(new Event('change', { bubbles: true }));
        var ap = document.getElementById('trxApply'); if (ap) setTimeout(function () { ap.click(); }, 0);
      });
      var rs = document.getElementById('trxReset'); (rs || tb.lastChild).before(box);
      var raw = document.getElementById('trxRawExport'); if (raw) { raw.classList.add('ptRaw'); raw.textContent = 'DOWNLOAD RAW'; }
      mark();
    }
  }

  /* ---------------------------------------------------------------- PERINGKAT */
  function tidyRank() {
    var view = $q('#view'); if (!view) return;
    var tb = $q('.toolbar', view), tabs = $q('.tabs', view), leg = $q('.rankLegend', view), inf = document.getElementById('rankInfo');
    if (tb && tabs && leg && inf && !tb.dataset.ptR) {
      tb.dataset.ptR = '1'; tb.classList.add('ptRk');
      tb.appendChild(tabs); tabs.classList.add('ptRkTabs');
      var tags = document.createElement('div'); tags.className = 'ptRkLeg';
      tags.innerHTML = $$('.rankTag', leg).map(function (t) { return t.outerHTML; }).join('');
      tb.appendChild(tags);
      var ib = document.createElement('button'); ib.type = 'button'; ib.className = 'ptI'; ib.setAttribute('aria-label', 'Keterangan peringkat'); ib.textContent = 'i';
      tb.appendChild(ib);
      infoPop(ib, function () {
        var sm = $q('small', leg), my = document.getElementById('myRankCards'), h = $q('.hint', my || document);
        return '<div>' + inf.innerHTML + '</div>' + (sm ? '<div>' + sm.innerHTML + '</div>' : '') + (h ? '<div>' + h.innerHTML + '</div>' : '');
      });
      inf.classList.add('ptHide'); leg.classList.add('ptHide');
    }
    var my = document.getElementById('myRankCards'), kp = my && $q('.kpis', my);
    if (kp && !my.dataset.ptR) {
      my.dataset.ptR = '1';
      var head = $q('p', my), hb = head && $q('b', head), htxt = head ? head.textContent : '';
      var m = /KPI Akhir\s*([\d.,]+%?)/.exec(htxt), nm = hb ? hb.textContent : '', role = (htxt.split('·')[1] || '').trim();
      var items = $$('.kpi', kp).map(function (k) { return [($q('.klab', k) || {}).textContent || '', ($q('.kval', k) || {}).innerHTML || '', ($q('.ksub', k) || {}).textContent || '']; });
      var all = [['KPI Akhir saya', m ? m[1] : '—', nm + (role ? ' · ' + role : '')]].concat(items), col = ['#0E7C6B', '#1E5FB8', '#C25A12'];
      var h = '<div class="ptK4 ptK3">' + all.map(function (c, i) { return kpiCard(col[i] || '#1E5FB8', esc(c[0]), c[1], esc(c[2])); }).join('') + '</div>';
      kp.insertAdjacentHTML('afterend', h); kp.classList.add('ptHide'); if (head) head.classList.add('ptHide');
      $$('.hint', my).forEach(function (x) { x.classList.add('ptHide'); });
    }
  }

  /* ---------------------------------------------------------------- BAR FILTER HALAMAN LAIN (gaya seragam, tanpa memindah elemen) */
  function tidyGeneric() {
    var view = $q('#view'); if (!view) return;
    $$('.toolbar', view).forEach(function (tb) {
      if (tb.dataset.ptG || /\bpt(Tb|Rk|Trx)\b/.test(tb.className) || tb.classList.contains('dosFilters')) return;
      tb.dataset.ptG = '1';
      if (tb.closest('form,.modal,.adminFeature') || $q('textarea,input[type=file],[required]', tb)) return;
      tb.classList.add('ptGen');
    });
  }

  /* ---------------------------------------------------------------- pemicu */
  var PAGES = { customer: tidyDealer, tim: tidyTim, dos: tidyDOS, transaksi: tidyTrx, peringkat: tidyRank, brand: tidyGeneric, analisa: tidyGeneric, targetso: tidyGeneric, transfer: tidyGeneric, koreksi: tidyGeneric };
  var q = false;
  function run() {
    document.documentElement.classList.toggle('ptOn', mq.matches);
    if (!mq.matches) return;
    var fn = PAGES[page()]; if (!fn) return;
    try { fn(); } catch (e) { if (window.console) console.warn('page_tidy', e); }
  }
  function later() { if (q) return; q = true; requestAnimationFrame(function () { q = false; run(); }); }
  function boot() {
    var main = $q('#main'); if (main) new MutationObserver(later).observe(main, { childList: true, subtree: true });
    if (mq.addEventListener) mq.addEventListener('change', later); else if (mq.addListener) mq.addListener(later);
    later();
  }
  window.dsmTidy = { run: run, pages: PAGES };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
