/* Alat ukur performa (mati bawaan). Nyala/mati: Admin › Sistem › Alat ukur performa (Super Admin), atau ?probe=1 / ?probe=0.
   Mengukur: lama tiap panggilan server (nama fungsi + ukuran jawaban), dan waktu kerja tiap pengamat DOM / rAF per file skrip.
   Tidak mengubah perilaku DSM; hanya mencatat dan menampilkan kotak kecil di pojok kiri bawah. */
(function () {
  'use strict';
  try {
    var m = /[?&]probe=(\d)/.exec(location.search);
    if (m) localStorage.setItem('dsm_probe', m[1]);
    if (localStorage.getItem('dsm_probe') !== '1') return;
  } catch (e) { return; }
  var H = {}, F = [], LT = { n: 0, ms: 0, max: 0 };
  function where() {
    try {
      var s = (new Error()).stack.split('\n');
      for (var i = 2; i < s.length; i++) { var mm = /([\w\-.]+\.(?:js|php))(?:\?[^:)]*)?:(\d+)/.exec(s[i]); if (mm && mm[1] !== 'perf_probe.js') return mm[1] + ':' + mm[2]; }
    } catch (e) { }
    return '?';
  }
  function rec(k, d) { var h = H[k] || (H[k] = { n: 0, ms: 0, max: 0 }); h.n++; h.ms += d; if (d > h.max) h.max = d; }
  var MO = window.MutationObserver;
  window.MutationObserver = function (cb) {
    var w = where(), self;
    var wrapped = function (recs, obs) { var t = performance.now(); try { return cb.call(this, recs, obs); } finally { rec('Pengamat DOM ' + w, performance.now() - t); } };
    self = new MO(wrapped); return self;
  };
  window.MutationObserver.prototype = MO.prototype;
  var RAF = window.requestAnimationFrame;
  window.requestAnimationFrame = function (cb) {
    var w = where();
    return RAF.call(window, function (ts) { var t = performance.now(); try { return cb(ts); } finally { rec('rAF ' + w, performance.now() - t); } });
  };
  var ST = window.setTimeout;
  window.setTimeout = function (cb, ms) {
    if (typeof cb !== 'function') return ST.apply(window, arguments);
    var w = where(), a = [].slice.call(arguments, 2);
    return ST.call(window, function () { var t = performance.now(); try { return cb.apply(this, a); } finally { rec('setTimeout ' + w, performance.now() - t); } }, ms);
  };
  var FE = window.fetch;
  window.fetch = function (u, o) {
    var fn = '?', t = performance.now();
    try { var b = o && o.body; if (typeof b === 'string') { var mm = /"fn":"(\w+)"/.exec(b.slice(0, 200)); if (mm) fn = mm[1]; } else if (b && b.get) { var p = b.get('__payload'); var m2 = /"fn":"(\w+)"/.exec(String(p).slice(0, 200)); if (m2) fn = m2[1]; } } catch (e) { }
    return FE.apply(window, arguments).then(function (r) {
      var t1 = performance.now(), c = r.clone();
      c.text().then(function (x) { F.push({ fn: fn, server: Math.round(t1 - t), kb: Math.round(x.length / 1024), at: Date.now() }); if (F.length > 60) F.shift(); });
      return r;
    });
  };
  try { new PerformanceObserver(function (l) { l.getEntries().forEach(function (e) { LT.n++; LT.ms += e.duration; if (e.duration > LT.max) LT.max = e.duration; }); }).observe({ entryTypes: ['longtask'] }); } catch (e) { }
  function esc(s) { return String(s).replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }); }
  function box() {
    var el = document.getElementById('dsmProbe');
    if (!el) {
      el = document.createElement('div'); el.id = 'dsmProbe';
      el.style.cssText = 'position:fixed;left:6px;bottom:6px;z-index:2147483647;max-width:520px;max-height:60vh;overflow:auto;background:#10253F;color:#E8F0FA;font:11px/1.35 ui-monospace,Consolas,monospace;padding:8px 10px;border-radius:8px;opacity:.96;white-space:pre';
      el.title = 'Klik untuk tutup/buka. Alat ukur DSM. Matikan di Admin › Sistem › Alat ukur performa.';
      el.onclick = function () { el.dataset.min = el.dataset.min ? '' : '1'; draw(); };
      (document.body || document.documentElement).appendChild(el);
    }
    return el;
  }
  function draw() {
    var el = box();
    if (el.dataset.min) { el.textContent = 'PROBE (klik)'; return; }
    var hs = Object.keys(H).map(function (k) { return [k, H[k]]; }).sort(function (a, b) { return b[1].ms - a[1].ms; }).slice(0, 7);
    var fs = F.slice().sort(function (a, b) { return b.server - a.server; }).slice(0, 6);
    var o = 'PROBE · long task: ' + LT.n + 'x, total ' + Math.round(LT.ms) + ' ms, terpanjang ' + Math.round(LT.max) + ' ms\n\nSERVER (fungsi · ms sampai jawaban · KB)\n';
    o += fs.map(function (f) { return f.fn + ' · ' + f.server + ' ms · ' + f.kb + ' KB'; }).join('\n') || '(belum ada)';
    o += '\n\nBROWSER (kerja total · jumlah · terlama)\n';
    o += hs.map(function (h) { return Math.round(h[1].ms) + ' ms · ' + h[1].n + 'x · max ' + Math.round(h[1].max) + ' · ' + h[0]; }).join('\n') || '(belum ada)';
    el.textContent = o;
  }
  setInterval(function () { try { draw(); } catch (e) { } }, 2000);
  window.dsmProbe = { H: H, F: F, LT: LT, draw: draw };
})();
