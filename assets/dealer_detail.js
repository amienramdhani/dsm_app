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
    const clean = () => { const sb = box.querySelector('.mhead .ddSub'), ds = sb && sb.querySelector('.detailstatus'); if (ds && isDealer) body.prepend(ds); box.classList.remove('ddX'); body.querySelector('.dealerTabs [data-dtab="1"]')?.removeAttribute('hidden'); box.querySelector('.ddFoot')?.remove(); box.querySelector('.ddAct')?.remove(); sb?.remove(); };
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
    /* HUBUNGI (mockup 2026-11-19 v2): satu tombol berisi Telepon / WhatsApp / Lihat semua kontak */
    if (!act.querySelector('.ddHub')) {
      const ls = [...body.querySelectorAll('#dealerSummary .contactLinks a')], tel = ls.find(a => a.classList.contains('contactPhone')), wa = ls.find(a => a.classList.contains('contactWhatsApp'));
      const num = tel ? tel.textContent.replace(/^Telepon\s*/, '').trim() : '';
      const hub = document.createElement('div'); hub.className = 'ddHub';
      hub.innerHTML = ls.length ? `<button type="button" class="ddB g">📞 HUBUNGI ▾</button><div class="ddHm" hidden>${tel ? `<a href="${tel.getAttribute('href')}"><i style="background:#E8F1FB">☎</i><span>Telepon<small>${num.replace(/</g, '&lt;')}</small></span></a>` : ''}${wa ? `<a href="${wa.getAttribute('href')}" target="_blank" rel="noopener"><i style="background:#E3F5EA">💬</i><span>WhatsApp<small>${num.replace(/</g, '&lt;')}</small></span></a>` : ''}<hr><a href="#" data-ddkt><i style="background:#F1F4F8">👤</i><span>Lihat semua kontak<small>PIC &amp; alamat · tab KONTAK</small></span></a></div>` : '<button type="button" class="ddB g" disabled title="Nomor belum ada">📞 HUBUNGI · nomor belum ada</button>';
      const o0 = act.querySelector('#tmDealerOrder'); o0 ? o0.after(hub) : act.prepend(hub);
      hub.onclick = e => { const m = hub.querySelector('.ddHm'); if (!m) return; if (e.target.closest('[data-ddkt]')) { e.preventDefault(); m.hidden = true; body.querySelector('.dealerTabs [data-dtab="4"]')?.click(); return; } if (e.target.closest('.ddB')) m.hidden = !m.hidden; else m.hidden = true; };
    }
    /* tombol TUTUP menempel di bawah popup */
    if (!box.querySelector('.ddFoot')) { const f = document.createElement('div'); f.className = 'ddFoot'; f.innerHTML = '<button type="button" class="ddB o">TUTUP</button>'; f.onclick = e => { if (e.target.closest('button')) (typeof closeModal === 'function' ? closeModal() : box.querySelector('.mhead .x')?.click()); }; box.appendChild(f); }
    /* tab gaya segmen, HISTORI disembunyikan di desktop, alamat keluar dari Ringkasan (ada di tab KONTAK) */
    const tb = body.querySelector('.dealerTabs'); if (tb) { tb.classList.add('ddSeg'); const h = tb.querySelector('[data-dtab="1"]'); if (h) h.hidden = true; }
    body.querySelectorAll('#dealerSummary > p').forEach(p => { if (/Lokasi dealer/.test(p.textContent)) p.classList.add('ddAddr'); });
    const tut = body.querySelector('[data-v6t="form"]');
    /* v3 2026-11-19 (mockup "A · tombol terlihat"): tombol tutup dealer terlihat di baris aksi, formulir lama dipindah ke popover (jalan di semua tab) */
    act.querySelector('.ddMore')?.remove();
    let tw = act.querySelector('.ddTut');
    if (tut && !tw) { tw = document.createElement('div'); tw.className = 'ddTut'; tw.innerHTML = '<button type="button" class="ddB red" data-ddtut>\u26D4 ' + tut.textContent.replace(/</g, '&lt;').trim() + '</button><div class="ddTf"><div class="ddTfT"></div><div class="ddTfB"></div><div class="ddTfF"><button type="button" class="ddB o" data-ddtfx>BATAL</button></div></div>'; tw.querySelector('.ddTfT').textContent = '\u26D4 ' + tut.textContent.trim().toUpperCase(); act.appendChild(tw); }
    body.querySelectorAll('#contactPanel').forEach(ddK3);
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
  /* popover tutup dealer: formulir asli (.v6tform, dibuat dsm.js) dipindah ke sini supaya terlihat di semua tab; id & tombol kirim tetap */
  function closeTf() { document.querySelector('#mask .ddTf.on')?.classList.remove('on'); }
  function toggleTf() {
    const tf = document.querySelector('#mask .ddTf'); if (!tf) return; if (tf.classList.contains('on')) { tf.classList.remove('on'); return; }
    let f = tf.querySelector('.v6tform');
    if (!f) { const b = document.querySelector('#mbody [data-v6t="form"]'); const src = b && b.parentElement.querySelector('.v6tform'); if (!src) return; if (!src.innerHTML) b.click(); tf.querySelector('.ddTfB').appendChild(src); f = src; }
    f.hidden = false; tf.classList.add('on');
  }
  /* tab KONTAK desktop: tabel rapat (mockup K3). Elemen asli (tombol edit, tautan telepon/WA, riwayat) dipindah, bukan dibuat ulang, jadi handler tetap jalan. */
  function ddK3(panel) {
    if (!panel || panel.querySelector('.ddK3')) return;
    const h3 = panel.querySelector(':scope > h3'); if (!h3) return;
    const addr = h3.nextElementSibling; if (!addr) return;
    const links = addr.nextElementSibling;
    const pic = [...panel.querySelectorAll(':scope > p')].find(p => /^\s*PIC:/i.test(p.textContent));
    const btns = [...panel.querySelectorAll(':scope > button')], hist = [...panel.querySelectorAll(':scope > details.contactHistory')];
    const esc = t => String(t == null ? '' : t).replace(/&/g, '&amp;').replace(/</g, '&lt;');
    const wrap = document.createElement('div'); wrap.className = 'ddK3';
    const hasLinks = links && links.classList && links.classList.contains('contactLinks');
    const tel = hasLinks ? links.querySelector('.contactPhone') : null, wa = hasLinks ? links.querySelector('.contactWhatsApp') : null;
    const num = tel ? tel.textContent.replace(/^Telepon\s*/, '').trim() : '';
    if (tel) tel.textContent = 'TELEPON'; if (wa) wa.textContent = 'WHATSAPP';
    const picTxt = pic ? pic.textContent.replace(/^\s*PIC:\s*/i, '').trim() : '';
    const hpTxt = hasLinks ? esc(num) : (links && /^Kontak:/.test(links.textContent) ? esc(links.textContent.replace(/^Kontak:\s*/, '')) : '<i class="e">Belum tersedia</i>');
    wrap.innerHTML = '<table class="ddK3t"><thead><tr><th colspan="2">KONTAK &amp; ALAMAT</th><th class="ddK3a"></th></tr></thead><tbody>'
      + '<tr><td class="kl">PIC</td><td colspan="2">' + (picTxt && picTxt !== '—' ? esc(picTxt) : '<i class="e">Belum diisi</i>') + '</td></tr>'
      + '<tr><td class="kl">NOMOR HP</td><td>' + hpTxt + '</td><td class="ddK3l"></td></tr>'
      + '<tr><td class="kl">ALAMAT</td><td colspan="2">' + (addr.textContent && !/belum diisi/i.test(addr.textContent) ? esc(addr.textContent) : '<i class="e">Belum diisi</i>') + '</td></tr></tbody></table>';
    if (hasLinks) wrap.querySelector('.ddK3l').appendChild(links);
    const act = wrap.querySelector('.ddK3a'); btns.forEach(b => act.appendChild(b));
    if (hist.length) { const hh = document.createElement('div'); hh.className = 'ddK3h'; hh.textContent = 'RIWAYAT PERUBAHAN'; const hl = document.createElement('div'); hl.className = 'ddK3hl'; hist.forEach(d => hl.appendChild(d)); wrap.append(hh, hl); }
    h3.remove(); addr.remove(); if (links && !hasLinks) links.remove(); pic?.remove();
    panel.prepend(wrap);
  }
  document.addEventListener('click', e => {
    if (e.target.closest('[data-ddtut]')) { toggleTf(); return; }
    if (e.target.closest('[data-ddtfx]')) { closeTf(); return; }
    if (!e.target.closest('.ddTut')) closeTf();
    if (!e.target.closest('.ddHub')) document.querySelectorAll('.ddHm').forEach(m => m.hidden = true);
  });
  window.ddRun = run; let t = null;
  const go = () => { clearTimeout(t); t = setTimeout(() => { try { run(); } catch (x) { } }, 40); };
  // setiap pop-up baru dibuka: buang header dealer sebelumnya (status, tombol aksi) supaya tidak terbawa ke pop-up lain
  function reset() { menuOpen = false; const box = document.querySelector('#mask .modal'); if (!box) return; box.classList.remove('ddX'); box.querySelector('.ddFoot')?.remove(); box.querySelector('.ddAct')?.remove(); box.querySelector('.mhead .ddSub')?.remove(); }
  function wrapModal() { if (typeof window.modal !== 'function' || window.modal.__dd) return; const o = window.modal; window.modal = function () { reset(); return o.apply(this, arguments); }; window.modal.__dd = true; Object.assign(window.modal, { __fk: o.__fk }); }
  wrapModal(); document.addEventListener('DOMContentLoaded', wrapModal);
  function hook() { const b = document.getElementById('mbody'); if (!b) return false; new MutationObserver(go).observe(b, { childList: true, subtree: true }); return true; }
  if (!hook()) document.addEventListener('DOMContentLoaded', hook);
  window.addEventListener('resize', go);
})();
