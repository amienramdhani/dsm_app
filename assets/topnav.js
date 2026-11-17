/* =====================================================================
   DSM · Menu atas desktop (opsi B, disetujui 17 Nov 2026)
   - Khusus desktop (lebar >= 821px). HP tidak disentuh.
   - Tidak membuat daftar menu sendiri: semua diambil dari tombol asli di
     #navigation (sudah disaring hak akses role oleh dsm.js). Klik di bar
     ini hanya memanggil .click() pada tombol asli, jadi navigateTo, kunci
     navigasi, dan aturan role tetap satu sumber.
   - Bar: logo, Beranda, Semua Menu (panel 3 kolom), AI Assistant,
     Informasi, DSM Klaim, Admin, cari menu (Ctrl K), akun.
   ===================================================================== */
(function () {
  'use strict';
  var mq = window.matchMedia('(min-width:821px)');
  var $q = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var SV = function (p) { return '<svg viewBox="0 0 24 24" aria-hidden="true">' + p + '</svg>'; };

  var IC = {
    beranda: '<path d="M3 11 12 3l9 8M5 10v11h5v-7h4v7h5V10"/>',
    customer: '<path d="M3 9l1.5-5h15L21 9M4 9v11h16V9M3 9h18M9 20v-6h6v6"/>',
    brand: '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',
    tim: '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c.6-4 3.4-6 7-6s6.4 2 7 6M16 4.5a3.5 3.5 0 0 1 0 7M22 20c-.4-2.6-1.8-4.4-4-5.3"/>',
    dos: '<path d="M21 8 12 3 3 8v8l9 5 9-5zM3 8l9 5 9-5M12 13v8"/>',
    transaksi: '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6"/>',
    analisa: '<path d="M3 3v18h18M7 15l4-4 3 3 5-6"/>',
    kpi: '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
    peringkat: '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0zM17 6h3v2a3 3 0 0 1-3 3M7 6H4v2a3 3 0 0 0 3 3"/>',
    ai: '<path d="M12 3l2 5 5 2-5 2-2 5-2-5-5-2 5-2z"/>',
    presentasi: '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
    klaim: '<path d="M9 3h6l1 3h3v15H5V6h3zM9 13l2 2 4-4"/>',
    transfer: '<path d="M7 7h11l-3-3M17 17H6l3 3"/>',
    koreksi: '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    targetso: '<circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 2"/>',
    informasi: '<circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v5h1"/>',
    admin: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
    tmactivity: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
    tmorder: '<path d="M6 3h12v18H6zM9 8h6M9 12h6M9 16h3"/>',
    panduan: '<path d="M4 4h7a3 3 0 0 1 3 3v13a2 2 0 0 0-2-2H4zM20 4h-6M20 8h-6"/>',
    perangkat: '<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M11 18h2"/>',
    telegram: '<path d="M21 4 3 11l6 2 2 6 3-4 5 4 2-15zM9 13l8-6"/>',
    tampilan: '<path d="M12 3a9 9 0 1 0 0 18c1.1 0 1.6-.9 1.2-1.8l-.4-.9c-.5-1 .2-2.3 1.4-2.3H17a4 4 0 0 0 4-4c0-5-4-9-9-9z"/>',
    pin: '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    keluar: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    demo: '<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M10 9l4 2.5-4 2.5zM8 21h8"/>',
    cari: '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
    caret: '<path d="M7 10l5 5 5-5"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>'
  };
  var DESC = {
    customer: 'Daftar & status dealer', brand: 'Performa per brand', tim: 'Pencapaian tim sales', dos: 'Stok & hari cukup',
    transaksi: 'Rincian penjualan', analisa: 'Tren & perbandingan', kpi: 'Nilai & bobot KPI', peringkat: 'Ranking sales',
    presentasi: 'Bahan rapat', transfer: 'Pindah pemilik dealer', koreksi: 'Perbaiki data transaksi', targetso: 'Target sell out',
    tmactivity: 'Aktivitas telemarketing', tmorder: 'Form order telemarketing', ai: 'Tanya data', informasi: 'Pengumuman',
    klaim: 'Ajukan & approval', admin: 'Pengaturan sistem', beranda: 'Ringkasan'
  };
  var LABEL = { tmactivity: 'Telemarketing', tmorder: 'Form Order' };
  var GROUPS = [
    ['PANTAU', ['customer', 'brand', 'tim', 'dos', 'transaksi', 'tmactivity', 'tmorder']],
    ['ANALISA', ['analisa', 'kpi', 'peringkat', 'presentasi']],
    ['KERJA', ['transfer', 'koreksi', 'targetso']]
  ];
  var TOP = ['ai', 'informasi', 'klaim', 'admin'];
  var ACCT = ['perangkat', 'telegram', 'panduan'];

  var klaimN = 0;

  function srcBtn(p) { return $q('#navigation button[data-page="' + p + '"]'); }
  function label(p) {
    if (LABEL[p]) return LABEL[p];
    var b = srcBtn(p); var t = b ? (b.textContent || '').trim() : p;
    return t;
  }
  function go(p) { var b = srcBtn(p); if (b && !b.disabled) b.click(); }
  function cur() { var b = $q('#navigation button.on'); return b ? b.dataset.page : ''; }

  function ico(p, cls) { return '<span class="' + (cls || 'tnI') + '">' + SV(IC[p] || IC.user) + '</span>'; }

  function groupsFor(pages) {
    var placed = {}, cols = [];
    GROUPS.forEach(function (g) {
      var items = g[1].filter(function (p) { return pages.indexOf(p) > -1; });
      items.forEach(function (p) { placed[p] = 1; });
      cols.push([g[0], items]);
    });
    var rest = pages.filter(function (p) {
      return !placed[p] && p !== 'beranda' && p !== 'tmfu' && TOP.indexOf(p) < 0 && ACCT.indexOf(p) < 0;
    });
    if (rest.length) cols[2][1] = cols[2][1].concat(rest);
    return cols.filter(function (c) { return c[1].length; });
  }

  function badgeHtml(p) {
    var n = p === 'klaim' ? klaimN : p === 'admin' ? (+window.dsmApprovalPending || 0) : 0;
    return n > 0 ? '<em class="tnBd" data-bd="' + p + '">' + (n > 99 ? '99+' : n) + '</em>' : '';
  }

  var built = '';
  function build() {
    var bar = $q('#tnBar'), root = document.documentElement;
    if (!mq.matches) { if (bar) bar.remove(); root.classList.remove('tnOn'); built = ''; return; }
    var app = $q('#app'); if (!app || app.hidden) return;
    var src = $$('#navigation button[data-page]'); if (!src.length) return;
    var pages = src.map(function (b) { return b.dataset.page; });
    var sig = pages.join(',');
    if (bar && built === sig) { sync(); return; }
    built = sig;
    var cols = groupsFor(pages);
    var has = function (p) { return pages.indexOf(p) > -1; };
    var h = '<a class="tnLogo" href="#" data-go="beranda" aria-label="Beranda"><img src="assets/dsm-logo-crop.jpg" alt="DSM"></a>';
    if (has('beranda')) h += '<button type="button" class="tnTab" data-go="beranda">' + SV(IC.beranda) + '<span>Beranda</span></button>';
    if (cols.length) {
      h += '<div class="tnDd"><button type="button" class="tnTab" id="tnMenuBtn" aria-haspopup="true" aria-expanded="false"><span>Semua Menu</span>' + SV(IC.caret).replace('<svg', '<svg class="tnCar"') + '</button>';
      h += '<div class="tnMega" id="tnMega" role="menu" hidden style="--tnc:' + cols.length + '">';
      cols.forEach(function (c) {
        h += '<div class="tnCol"><div class="tnGl">' + c[0] + '</div>';
        c[1].forEach(function (p) {
          h += '<button type="button" role="menuitem" class="tnMi" data-go="' + p + '">' + ico(p, 'tnMic') + '<span><b>' + esc(label(p)) + '</b><small>' + esc(DESC[p] || '') + '</small></span></button>';
        });
        h += '</div>';
      });
      h += '</div></div>';
    }
    var tops = TOP.filter(has);
    if (tops.length) {
      h += '<i class="tnVs"></i>';
      tops.forEach(function (p) {
        h += '<button type="button" class="tnTab tnTop" data-go="' + p + '" title="' + esc(label(p)) + '">' + SV(IC[p]) + '<span>' + esc(label(p)) + '</span>' + badgeHtml(p) + '</button>';
      });
    }
    h += '<div class="tnSp"></div>';
    h += '<div class="tnWarnWrap" id="tnWarnWrap" hidden><button type="button" class="tnWarn" id="tnWarn" aria-expanded="false"></button><div class="tnWarnPop" id="tnWarnPop" hidden></div></div>';
    h += '<button type="button" class="tnScope' + (subOpen() ? ' on' : '') + '" id="tnScope" aria-expanded="' + subOpen() + '" aria-label="Cakupan dan periode"><span class="tnScT">' + esc(scopeText()) + '</span><b aria-hidden="true">▾</b></button>';
    h += '<div class="tnFind"><label class="tnFi">' + SV(IC.cari) + '<input id="tnSearch" type="search" autocomplete="off" placeholder="Cari menu…" aria-label="Cari menu"><kbd>Ctrl K</kbd></label><div class="tnRes" id="tnRes" hidden role="listbox"></div></div>';
    h += '<div class="tnDd tnAcc"><button type="button" class="tnAv" id="tnAcc" aria-haspopup="true" aria-expanded="false" aria-label="Menu akun"></button><div class="tnPop" id="tnPop" role="menu" hidden></div></div>';
    if (!bar) { bar = document.createElement('header'); bar.id = 'tnBar'; app.insertBefore(bar, app.firstChild); bar.addEventListener('click', onClick); }
    bar.dataset.sig = sig; bar.innerHTML = h;
    root.classList.add('tnOn');
    wireSearch();
    sync();
    if (has('klaim')) loadKlaim();
  }

  function sync() {
    var bar = $q('#tnBar'); if (!bar) return;
    var c = cur(), inMega = false;
    $$('[data-go]', bar).forEach(function (b) {
      var on = b.dataset.go === c;
      if (b.classList.contains('tnTab') || b.classList.contains('tnMi')) b.classList.toggle('on', on);
      if (on && b.classList.contains('tnMi')) inMega = true;
    });
    var mb = $q('#tnMenuBtn'); if (mb) mb.classList.toggle('on', inMega);
    var av = $q('#tnAcc');
    if (av) {
      var nm = ($q('#namaTop') && $q('#namaTop').textContent || '').trim();
      var t = ($q('#avatarTop') && $q('#avatarTop').textContent || '').trim() || (nm ? nm.slice(0, 1) : '?');
      if (av.textContent !== t) av.textContent = t;
    }
    laterCrumb();
    ['klaim', 'admin'].forEach(function (p) {
      var b = $q('.tnTop[data-go="' + p + '"]', bar); if (!b) return;
      var n = p === 'klaim' ? klaimN : (+window.dsmApprovalPending || 0);
      var e = $q('.tnBd', b);
      if (n > 0) { if (!e) { e = document.createElement('em'); e.className = 'tnBd'; b.appendChild(e); } e.textContent = n > 99 ? '99+' : String(n); }
      else if (e) e.remove();
    });
  }

  function closeAll() {
    var m = $q('#tnMega'), p = $q('#tnPop'), r = $q('#tnRes');
    if (m) m.hidden = true; if (p) p.hidden = true; if (r) r.hidden = true;
    var mb = $q('#tnMenuBtn'); if (mb) { mb.setAttribute('aria-expanded', 'false'); mb.classList.remove('open'); }
    var a = $q('#tnAcc'); if (a) a.setAttribute('aria-expanded', 'false');
    var wp = $q('#tnWarnPop'); if (wp) wp.hidden = true; var wb = $q('#tnWarn'); if (wb) wb.setAttribute('aria-expanded', 'false');
  }

  function openAcct() {
    var pop = $q('#tnPop'); if (!pop) return;
    var nm = ($q('#namaTop') && $q('#namaTop').textContent || '').trim();
    var rl = ($q('#roleChip') && $q('#roleChip').textContent || '').trim();
    var items = '';
    ACCT.forEach(function (p) {
      var b = srcBtn(p);
      if (b) items += '<button type="button" role="menuitem" class="tnPi" data-go="' + p + '">' + ico(p, 'tnPic') + esc(label(p)) + '</button>';
    });
    var outs = $$('.topbar .who > button.out').filter(function (b) { return !b.hidden && b.style.display !== 'none' && (b.textContent || '').trim(); });
    var rest = '', exit = '';
    outs.forEach(function (b, i) {
      var t = b.textContent.trim();
      var k = /keluar/i.test(t) ? 'keluar' : /pin/i.test(t) ? 'pin' : /tampilan/i.test(t) ? 'tampilan' : /demo|super admin/i.test(t) ? 'demo' : 'user';
      var html = '<button type="button" role="menuitem" class="tnPi' + (k === 'keluar' ? ' out' : '') + '" data-out="' + i + '">' + ico(k, 'tnPic') + esc(t) + '</button>';
      if (k === 'keluar') exit = html; else rest += html;
    });
    pop._outs = outs;
    pop.innerHTML = '<div class="tnMe"><b>' + esc(nm || '—') + '</b><small>' + esc(rl) + '</small></div>' + items + rest + (exit ? '<hr>' + exit : '');
    pop.hidden = false; $q('#tnAcc').setAttribute('aria-expanded', 'true');
  }

  function onClick(e) {
    var t = e.target;
    var go_ = t.closest('[data-go]');
    if (go_) { e.preventDefault(); closeAll(); go(go_.dataset.go); return; }
    var out = t.closest('[data-out]');
    if (out) { var pop = $q('#tnPop'); var b = pop && pop._outs && pop._outs[+out.dataset.out]; closeAll(); if (b) b.click(); return; }
    if (t.closest('#tnMenuBtn')) {
      var m = $q('#tnMega'), was = m.hidden; closeAll();
      if (was) { m.hidden = false; $q('#tnMenuBtn').setAttribute('aria-expanded', 'true'); $q('#tnMenuBtn').classList.add('open'); }
      return;
    }
    if (t.closest('#tnWarn')) { var wp = $q('#tnWarnPop'), ww = wp.hidden; closeAll(); if (ww) { wp.hidden = false; $q('#tnWarn').setAttribute('aria-expanded', 'true'); } return; }
    if (t.closest('#tnScope')) { closeAll(); setSub(!subOpen()); return; }
    if (t.closest('#tnAcc')) { var p = $q('#tnPop'), w = p.hidden; closeAll(); if (w) openAcct(); return; }
  }

  /* ---------- peringatan ST belum lengkap (khusus Admin/Super Admin) ---------- */
  function warnData() {
    try {
      if (!['ADMIN', 'SUPER ADMIN'].includes(BOOT.user.role)) return null;
      var cut = REPORT && REPORT.coverage && REPORT.coverage.cutoff, rs = (REPORT && REPORT.regionStatus) || [];
      var lag = rs.filter(function (r) { return !r.st || (cut && r.st < cut); });
      return lag.length && cut ? { cut: cut, n: lag.length, rs: rs, lag: lag } : null;
    } catch (e) { return null; }
  }
  function updWarn() {
    var w = $q('#tnWarnWrap'), b = $q('#tnWarn'), pop = $q('#tnWarnPop'); if (!w) return;
    var d = warnData(), skip = ['admin', 'perangkat', 'telegram', 'panduan', 'transfer', 'koreksi', 'targetso'].indexOf(cur()) >= 0;
    if (!d || skip) { w.hidden = true; return; }
    var key = d.cut + '|' + d.n + '|' + d.rs.length; if (w.dataset.k === key) { w.hidden = false; return; }
    w.dataset.k = key; w.hidden = false;
    var T = function (x) { try { return typeof tgl === 'function' ? tgl(x) : (x || '—'); } catch (e) { return x || '—'; } };
    b.innerHTML = '<span aria-hidden="true">⚠︎</span><span class="tnWt">ST BELUM LENGKAP · </span><span>' + d.n + ' REGION</span><b aria-hidden="true">▾</b>';
    pop.innerHTML = '<div class="tnWh">ST belum lengkap s.d. ' + esc(T(d.cut)) + '</div><table><thead><tr><th>Region induk</th><th>ST sampai</th><th>SO sampai</th></tr></thead><tbody>' +
      d.rs.map(function (r) { var lag = !r.st || r.st < d.cut; return '<tr' + (lag ? ' class="lag"' : '') + '><td>' + esc(r.region) + '</td><td>' + esc(T(r.st)) + '</td><td>' + esc(T(r.so)) + '</td></tr>'; }).join('') +
      '</tbody></table><p>Region yang belum lengkap ditandai, bukan dianggap nol. Perbandingan mingguan ST–SO dan DOS memakai tanggal yang sejajar.</p>';
  }
  var cq = 0;
  function laterCrumb() { if (cq) return; cq = requestAnimationFrame(function () { cq = 0; try { updWarn(); } catch (e) { } }); }
  document.addEventListener('dsm-pageheader', laterCrumb);

  /* ---------- bar Cakupan & Periode: bisa dibuka/tutup (bawaan tertutup) ---------- */
  function subOpen() { return document.documentElement.classList.contains('tnSubOpen'); }
  function setSub(on) {
    document.documentElement.classList.toggle('tnSubOpen', !!on);
    try { localStorage.setItem('dsm_tn_sub', on ? '1' : '0'); } catch (e) { }
    var b = $q('#tnScope'); if (b) { b.classList.toggle('on', !!on); b.setAttribute('aria-expanded', String(!!on)); }
  }
  function scopeText() {
    var sel = document.getElementById('periodSelect'), o = sel && sel.options[sel.selectedIndex];
    return o && o.textContent.trim() ? o.textContent.trim() : 'Periode';
  }
  function updScope() { var t = $q('#tnScope .tnScT'); if (t) t.textContent = scopeText(); }
  try { if (localStorage.getItem('dsm_tn_sub') === '1') document.documentElement.classList.add('tnSubOpen'); } catch (e) { }

  /* ---------- cari menu ---------- */
  function wireSearch() {
    var inp = $q('#tnSearch'), box = $q('#tnRes'); if (!inp) return;
    var idx = -1;
    function items() {
      var q = inp.value.trim().toLowerCase();
      var all = $$('#navigation button[data-page]').map(function (b) { return b.dataset.page; }).filter(function (p) { return p !== 'tmfu'; });
      var out = all.map(function (p) { return { p: p, l: label(p), d: DESC[p] || '' }; });
      if (!q) return out.filter(function (x) { return x.p !== 'beranda'; }).slice(0, 8);
      return out.filter(function (x) { return (x.l + ' ' + x.d).toLowerCase().indexOf(q) > -1; }).slice(0, 8);
    }
    function render() {
      var list = items(); idx = list.length ? 0 : -1;
      box.innerHTML = list.length ? list.map(function (x, i) { return '<button type="button" role="option" class="tnRi' + (i === 0 ? ' on' : '') + '" data-go="' + x.p + '">' + ico(x.p, 'tnPic') + '<span><b>' + esc(x.l) + '</b><small>' + esc(x.d) + '</small></span></button>'; }).join('') : '<div class="tnNo">Menu tidak ditemukan</div>';
      box.hidden = false;
    }
    inp.addEventListener('focus', function () { var m = $q('#tnMega'), p = $q('#tnPop'); if (m) m.hidden = true; if (p) p.hidden = true; render(); });
    inp.addEventListener('input', render);
    inp.addEventListener('keydown', function (e) {
      var rows = $$('.tnRi', box);
      if (e.key === 'Escape') { inp.value = ''; box.hidden = true; inp.blur(); }
      else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault(); if (!rows.length) return;
        idx = (idx + (e.key === 'ArrowDown' ? 1 : -1) + rows.length) % rows.length;
        rows.forEach(function (r, i) { r.classList.toggle('on', i === idx); });
      } else if (e.key === 'Enter') {
        e.preventDefault(); var r = rows[idx] || rows[0];
        if (r) { var p = r.dataset.go; inp.value = ''; box.hidden = true; inp.blur(); go(p); }
      }
    });
  }

  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K') && $q('#tnSearch')) {
      e.preventDefault(); var i = $q('#tnSearch'); i.focus(); i.select();
    } else if (e.key === 'Escape') closeAll();
  });
  document.addEventListener('mousedown', function (e) {
    if (!e.target.closest('#tnBar')) closeAll();
    else if (!e.target.closest('.tnFind')) { var r = $q('#tnRes'); if (r) r.hidden = true; }
  }, true);

  /* ---------- badge Klaim (sama dengan badge HP: klaim/api.php todo_count) ---------- */
  var klaimT = 0;
  function loadKlaim() {
    if (Date.now() - klaimT < 60000) return; klaimT = Date.now();
    try {
      fetch('klaim/api.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-DSM-Token': (typeof TOKEN !== 'undefined' ? TOKEN : '') }, body: JSON.stringify({ a: 'todo_count' }) })
        .then(function (r) { return r.json(); })
        .then(function (j) { if (j && j.ok) { klaimN = +j.n || 0; sync(); } })
        .catch(function () { });
    } catch (e) { }
  }
  setInterval(function () { if (document.visibilityState === 'visible' && $q('.tnTop[data-go="klaim"]')) loadKlaim(); }, 300000);
  window.addEventListener('dsm-approval-badge', sync);

  /* ---------- pantau perubahan ---------- */
  var q = false;
  function later() { if (q) return; q = true; requestAnimationFrame(function () { q = false; try { build(); } catch (e) { } }); }
  function boot() {
    var nav = $q('#navigation');
    if (nav) new MutationObserver(later).observe(nav, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'hidden'] });
    var app = $q('#app');
    if (app) new MutationObserver(later).observe(app, { attributes: true, attributeFilter: ['hidden'] });
    var mn = $q('#main'); if (mn) new MutationObserver(laterCrumb).observe(mn, { childList: true });
    var ps = $q('#periodSelect'); if (ps) { ps.addEventListener('change', updScope); new MutationObserver(updScope).observe(ps, { childList: true, attributes: true, subtree: true }); }
    var who = $q('#namaTop'); if (who) new MutationObserver(later).observe(who, { childList: true, characterData: true, subtree: true });
    var role = $q('#roleChip'); if (role) new MutationObserver(later).observe(role, { childList: true, characterData: true, subtree: true });
    if (mq.addEventListener) mq.addEventListener('change', later); else if (mq.addListener) mq.addListener(later);
    later();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
