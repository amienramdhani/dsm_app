/* DSM · header halaman desktop (banner biru solid). Hanya memindahkan elemen judul/keterangan/chip yang sudah ada,
   jadi isi dan fungsi halaman tidak berubah. HP tidak disentuh. */
(function () {
  'use strict';
  var mq = window.matchMedia('(min-width:821px)');
  var SKIP = { beranda: 1, admin: 1, ai: 1, klaim: 1 };
  var SUB = {
    customer: 'Daftar dan status dealer sesuai cakupan Anda',
    brand: 'Pencapaian per brand dan perbandingan periode',
    kpi: 'KPI dan pencapaian sesuai hak akses Anda',
    analisa: 'Analisa sell thru, sell out, dan peluang',
    transaksi: 'Rincian transaksi harian dan pola penjualan',
    peringkat: 'Papan peringkat KPI Sales dan ASM',
    presentasi: 'Ringkasan untuk presentasi'
  };
  var page = function () { try { return typeof PAGE !== 'undefined' ? PAGE : ''; } catch (e) { return ''; } };
  var $q = function (s, r) { return (r || document).querySelector(s); };

  function shortDates(root) {
    var w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null), n, re = /(\d+[–-]\d+)\s+([A-Za-zÀ-ÿ]{3})[A-Za-zÀ-ÿ]*(\s+\d{4})?/;
    while ((n = w.nextNode())) { if (re.test(n.nodeValue)) n.nodeValue = n.nodeValue.replace(re, '$1 $2'); }
  }

  /* chip "STATUS DIKUNCI" milik halaman Dealer: salinannya ditampilkan di banner, aslinya disembunyikan lewat CSS */
  function lockChip(main) {
    var ban = $q('.phBan', main); if (!ban) return;
    var src = $q('.dsChips .dsChip.lock, .dsChips .dsChip.temp', main), r = $q('.phR', ban), old = $q('.phLock', ban);
    if (!src || !r) { if (old) old.remove(); return; }
    var cl = src.cloneNode(true); var em = cl.querySelector('em'); if (em) em.textContent += ' '; var txt = cl.textContent.replace(/\s+/g, ' ').trim();
    if (old && old.dataset.t === txt) return;
    if (old) old.remove();
    var c = document.createElement('span'); c.className = 'phLock'; c.dataset.t = txt; c.textContent = txt; r.appendChild(c);
  }

  function run() {
    if (!mq.matches) return;
    var main = document.getElementById('main'); if (!main) return;
    lockChip(main);
    var pg = page(); if (!pg || SKIP[pg] || main.classList.contains('dsmHeroOn')) return;
    var t = $q(':scope>.ptitle', main), s = $q(':scope>.psub', main);
    if (!t || !s || t.closest('.phBan')) return;
    var ban = document.createElement('div'); ban.className = 'phBan'; ban.dataset.phPage = pg;
    var l = document.createElement('div'), r = document.createElement('div'); l.className = 'phL'; r.className = 'phR';
    t.before(ban); ban.appendChild(l); ban.appendChild(r); l.appendChild(t);
    if ($q('.v6cmp', s)) {
      var sub = document.createElement('div'); sub.className = 'phS'; sub.textContent = SUB[pg] || '';
      if (sub.textContent) l.appendChild(sub);
      r.appendChild(s); shortDates(s);
    } else { s.classList.add('phS'); l.appendChild(s); }
    document.dispatchEvent(new CustomEvent('dsm-pageheader'));
  }

  var q = 0;
  function later() { if (q) return; q = requestAnimationFrame(function () { q = 0; try { run(); } catch (e) { if (window.console) console.warn('page_header', e); } }); }
  function init() {
    var main = document.getElementById('main'); if (!main) { setTimeout(init, 300); return; }
    new MutationObserver(later).observe(main, { childList: true, subtree: true });
    if (mq.addEventListener) mq.addEventListener('change', later);
    later();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
