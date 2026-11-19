/* POPUP DETAIL DEALER — versi DESKTOP rapi (2026-11-18), mockup doc/mockup/2026-11-18/dsm-detail-dealer.html.
 * HP tidak diubah. Isi & proses tetap: elemen asli dipindah ke header, bukan dibuat ulang.
 * - Header: status + kode di bawah judul, baris aksi (+ CATAT ORDER, Telepon, WhatsApp, ⋯ berisi Tandai dealer tutup).
 * - Tab menempel di atas isi, kartu angka berwarna indikator, brand tanpa transaksi disembunyikan (bisa ditampilkan). */
(function () {
  const desk = () => window.matchMedia('(min-width:821px)').matches;
  let menuOpen = false;
  function run() {
    const mask = document.getElementById('mask'); if (!mask) return; const box = mask.querySelector('.modal'), body = document.getElementById('mbody');
    if (!box || !body) return;
    const isDealer = !!body.querySelector('#dealerSummary, .dealerTabs, .detailstatus');
    const clean = () => { const sb = box.querySelector('.mhead .ddSub'), ds = sb && sb.querySelector('.detailstatus'); if (ds && isDealer) body.prepend(ds); box.classList.remove('ddX'); box.querySelector('.ddAct')?.remove(); sb?.remove(); };
    if (!isDealer) { clean(); return; }
    if (!desk()) { if (box.classList.contains('ddX')) clean(); return; }
    box.classList.add('ddX');
    try { const st0 = box.querySelector('.detailstatus'); window.__ddLast = { nama: (document.getElementById('mtitle')?.textContent || '').trim(), info: st0 ? st0.textContent.replace(/^[^·]*·\s*/, '').trim() : '', at: Date.now() }; } catch (x) { }
    const head = box.querySelector('.mhead'); if (!head) return;
    // keterangan di bawah judul
    let sub = head.querySelector('.ddSub'); if (!sub) { sub = document.createElement('div'); sub.className = 'ddSub'; head.querySelector('h3')?.after(sub); }
    const st = body.querySelector('.detailstatus'); if (st && st.parentNode !== sub) sub.appendChild(st);
    // baris aksi
    let act = box.querySelector('.ddAct'); if (!act) { act = document.createElement('div'); act.className = 'ddAct'; head.after(act); }
    const order = body.querySelector('#tmDealerOrder'); if (order && order.parentNode !== act) { order.className = 'ddB p'; order.textContent = '＋ CATAT ORDER'; act.prepend(order); }
    body.querySelectorAll('.contactLinks a').forEach(a => { if (a.parentNode === act) return; if (a.classList.contains('contactPhone')) { a.className = 'ddB o'; a.innerHTML = '☎ ' + a.textContent.replace(/^Telepon\s*/, ''); } else if (a.classList.contains('contactWhatsApp')) { a.className = 'ddB wa'; a.textContent = '◉ WHATSAPP'; } act.appendChild(a); });
    const tut = body.querySelector('[data-v6t="form"]');
    let more = act.querySelector('.ddMore');
    if (tut && !more) { more = document.createElement('div'); more.className = 'ddMore'; more.innerHTML = '<button type="button" class="ddB m" aria-label="Aksi lain">⋯</button>'; act.appendChild(more); }
    if (more) { const m = more.querySelector('.ddMenu'); if (menuOpen && !m && tut) more.insertAdjacentHTML('beforeend', `<div class="ddMenu"><button type="button" data-ddtut>⛔ ${tut.textContent}<small>Perlu alasan · tidak bisa dibatalkan sendiri</small></button></div>`); if (!menuOpen && m) m.remove(); }
    // brand tanpa transaksi disembunyikan
    body.querySelectorAll('#dealerSummary table, .mbody > table').forEach(t => {
      if (t.dataset.dd) return; const h = t.querySelector('thead th:nth-child(2), tr th:nth-child(2)'); if (!h || !/SELL ?THRU/i.test(t.textContent.slice(0, 300))) return;
      t.dataset.dd = '1'; const rows = [...t.querySelectorAll('tbody tr')].filter(r => !/^total$/i.test((r.cells[0]?.textContent || '').trim()));
      const empty = rows.filter(r => [...r.cells].slice(1).every(td => /^(–|-|—|0|Rp\s*0|)$/.test(td.textContent.trim())));
      if (!empty.length) return; empty.forEach(r => r.classList.add('ddEmpty')); t.classList.add('ddHideEmpty');
      const lk = document.createElement('button'); lk.type = 'button'; lk.className = 'ddAll'; lk.textContent = 'Tampilkan ' + empty.length + ' brand tanpa transaksi';
      lk.onclick = () => { const on = t.classList.toggle('ddHideEmpty'); lk.textContent = on ? 'Tampilkan ' + empty.length + ' brand tanpa transaksi' : 'Sembunyikan brand tanpa transaksi'; };
      (t.closest('.tableWrap, .tw, div') || t).after(lk);
    });
  }
  document.addEventListener('click', e => {
    if (e.target.closest('.ddMore > .ddB')) { menuOpen = !menuOpen; run(); return; }
    if (e.target.closest('[data-ddtut]')) { menuOpen = false; run(); const b = document.querySelector('#mbody [data-v6t="form"]'); if (b) { b.click(); b.closest('.v6tban')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); } return; }
    if (menuOpen && !e.target.closest('.ddMore')) { menuOpen = false; run(); }
  });
  window.ddRun = run; let t = null;
  const go = () => { clearTimeout(t); t = setTimeout(() => { try { run(); } catch (x) { } }, 40); };
  // setiap pop-up baru dibuka: buang header dealer sebelumnya (status, tombol aksi) supaya tidak terbawa ke pop-up lain
  function reset() { menuOpen = false; const box = document.querySelector('#mask .modal'); if (!box) return; box.classList.remove('ddX'); box.querySelector('.ddAct')?.remove(); box.querySelector('.mhead .ddSub')?.remove(); }
  function wrapModal() { if (typeof window.modal !== 'function' || window.modal.__dd) return; const o = window.modal; window.modal = function () { reset(); return o.apply(this, arguments); }; window.modal.__dd = true; Object.assign(window.modal, { __fk: o.__fk }); }
  wrapModal(); document.addEventListener('DOMContentLoaded', wrapModal);
  function hook() { const b = document.getElementById('mbody'); if (!b) return false; new MutationObserver(go).observe(b, { childList: true, subtree: true }); return true; }
  if (!hook()) document.addEventListener('DOMContentLoaded', hook);
  window.addEventListener('resize', go);
})();
