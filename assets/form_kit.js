/* STANDAR FORM POP-UP (2026-11-18) — mockup doc/mockup/2026-11-18/dsm-form-standar.html
 * - Pop-up yang berisi form (tanpa tabel/tab) diberi kelas .mForm → gaya form standar (form_kit.css).
 * - Tombol simpan dipindah ke kaki form yang menempel + tombol BATAL. Simpan mati sampai kolom wajib terisi.
 * - alert() diganti notifikasi kecil di bawah layar.
 * Isi & proses form TIDAK diubah (tombol asli dipindah, bukan dibuat ulang). */
(function () {
  function isFormModal(body) {
    if (!body) return false;
    const forms = body.querySelectorAll('form'); if (forms.length !== 1) return false;
    if (body.querySelector('table, .dealerTabs, .modalTab, .kpis, canvas, svg.chart')) return false;
    return true;
  }
  function enhance() {
    const mask = document.getElementById('mask'), body = document.getElementById('mbody');
    if (!mask || !body) return; const box = mask.querySelector('.modal'); if (!box) return;
    const old = box.querySelector(':scope>.mfoot');
    if (old) { const fb = old.querySelector('.mfP'), fid = fb && fb.getAttribute('form'); if (fid && body.querySelector('form#' + CSS.escape(fid))) return; old.remove(); } // form sama → kaki form sudah terpasang
    if (!isFormModal(body)) { box.classList.remove('mForm'); return; }
    box.classList.add('mForm');
    // keterangan di bawah judul: dealer yang sedang dibuka (bila form dibuka dari detail dealer < 15 menit)
    const head = box.querySelector('.mhead'), h3 = head && head.querySelector('h3'); head?.querySelector('.mfSub')?.remove();
    const L = window.__ddLast, tt = (h3?.textContent || '');
    if (h3 && L && L.nama && Date.now() - L.at < 900000 && /kontak|dealer|pic|tutup|order|transfer/i.test(tt) && tt.indexOf(L.nama) < 0) { const sb = document.createElement('small'); sb.className = 'mfSub'; sb.textContent = L.nama + (L.info ? ' · ' + L.info : ''); h3.appendChild(sb); }
    const form = body.querySelector('form');
    const subs = [...form.querySelectorAll('button:not([type=button]):not([type=reset]), input[type=submit]')];
    if (subs.length !== 1) return; // banyak tombol (mis. pratinjau + simpan) → biarkan di tempat
    const btn = subs[0];
    if (!form.id) form.id = 'mf' + Math.random().toString(36).slice(2, 8);
    // Tombol asli dipindah ke kaki, jadi kode lama yang mencari tombol lewat form.querySelector('button') (mis. untuk
    // b.disabled=true saat menyimpan) tidak menemukannya dan error diam-diam. Penanda posisi asli + pencarian form
    // dilengkapi agar tombol tetap ditemukan di urutan DOM aslinya.
    const ph = document.createComment('mf'); btn.parentNode.insertBefore(ph, btn);
    const nq = Element.prototype.querySelectorAll;
    const findAll = function (sel) {
      const r = Array.prototype.slice.call(nq.call(form, sel)); let hit = false;
      try { hit = btn.matches(sel); } catch (e) { hit = false; }
      if (hit && r.indexOf(btn) < 0) { const i = r.findIndex(x => ph.compareDocumentPosition(x) & Node.DOCUMENT_POSITION_FOLLOWING); r.splice(i < 0 ? r.length : i, 0, btn); }
      return r;
    };
    form.querySelectorAll = findAll; form.querySelector = sel => findAll(sel)[0] || null;
    btn.setAttribute('form', form.id); btn.classList.add('mfP'); if (/hapus|tutup dealer|gabung|nonaktif/i.test(btn.textContent)) btn.classList.add('danger');
    const foot = document.createElement('div'); foot.className = 'mfoot';
    const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'mfB'; cancel.textContent = 'Batal'; cancel.onclick = () => { try { closeModal(); } catch (e) { mask.classList.remove('on'); } };
    foot.append(cancel, btn); box.appendChild(foot);
    const check = () => { if (!btn.dataset.busy) btn.disabled = !form.checkValidity(); };
    form.addEventListener('input', check); form.addEventListener('change', check); check();
    // tombol yang di-disable kode lama saat menyimpan tetap dihormati
    new MutationObserver(() => { btn.dataset.busy = btn.disabled && form.checkValidity() ? '1' : ''; }).observe(btn, { attributes: true, attributeFilter: ['disabled'] });
  }
  function hook() {
    if (typeof window.modal !== 'function' || window.modal.__fk) return false;
    const orig = window.modal;
    window.modal = function () { const r = orig.apply(this, arguments); try { enhance(); } catch (e) { } return r; };
    window.modal.__fk = true;
    const body = document.getElementById('mbody');
    if (body) new MutationObserver(() => { clearTimeout(hook.t); hook.t = setTimeout(() => { try { enhance(); } catch (e) { } }, 30); }).observe(body, { childList: true });
    return true;
  }
  if (!hook()) document.addEventListener('DOMContentLoaded', hook);
  // ---------- notifikasi kecil ----------
  let tt = null;
  window.dsmToast = function (msg, err) {
    let t = document.getElementById('dsmToast'); if (!t) { t = document.createElement('div'); t.id = 'dsmToast'; t.setAttribute('role', 'status'); document.body.appendChild(t); }
    t.textContent = String(msg ?? ''); t.className = err ? 'err on' : 'on'; clearTimeout(tt); tt = setTimeout(() => t.classList.remove('on'), Math.min(8000, 2500 + String(msg).length * 40));
  };
  window.alert = m => window.dsmToast(m, /gagal|tidak|wajib|harus|error|salah|maks|minimal|belum/i.test(String(m)));
})();
/* ---------- Konfirmasi gaya DSM (pengganti confirm() bawaan browser) ----------
 * await dsmConfirm(pesan) → true/false. Kata berbahaya (hapus, tutup, pulihkan, gabung, nonaktif, timpa) → tombol merah. */
(function () {
  window.dsmConfirm = function (msg, opt) {
    opt = opt || {}; const text = String(msg ?? '');
    const danger = opt.danger ?? /hapus|tutup|pulihkan|gabung|nonaktif|timpa|cabut|batalkan|reset/i.test(text);
    return new Promise(res => {
      const ov = document.createElement('div'); ov.className = 'dcfOv';
      const lines = text.split(/\n+/), title = opt.title || (lines.length > 1 || text.length > 60 ? (danger ? 'Yakin lanjut?' : 'Konfirmasi') : text), body = opt.title || lines.length > 1 || text.length > 60 ? text : '';
      ov.innerHTML = `<div class="dcf${danger ? ' danger' : ''}" role="alertdialog" aria-modal="true"><div class="dcfIc">${danger ? '⚠' : '?'}</div><b></b><p></p><div class="dcfB"><button type="button" class="n">BATAL</button><button type="button" class="y">${esc2(opt.ok || (danger ? 'YA, LANJUTKAN' : 'YA'))}</button></div></div>`;
      ov.querySelector('b').textContent = title; const p = ov.querySelector('p'); if (body) p.textContent = body; else p.remove();
      const done = v => { ov.classList.remove('on'); setTimeout(() => ov.remove(), 150); document.removeEventListener('keydown', key, true); res(v); };
      const key = e => { if (e.key === 'Escape') { e.stopPropagation(); done(false); } else if (e.key === 'Enter') { e.preventDefault(); done(true); } };
      ov.querySelector('.n').onclick = () => done(false); ov.querySelector('.y').onclick = () => done(true);
      ov.addEventListener('click', e => { if (e.target === ov) done(false); });
      document.addEventListener('keydown', key, true); document.body.appendChild(ov); requestAnimationFrame(() => { ov.classList.add('on'); ov.querySelector('.y').focus(); });
    });
  };
  function esc2(s) { return String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }
})();
