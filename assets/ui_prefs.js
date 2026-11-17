/* Paket 3 (2026-11-17): preferensi tampilan per akun — latar belakang + data untuk Beranda Admin (sematan, terakhir dibuka).
   Disimpan di server (apiUiPrefSave) dan disalin ke localStorage supaya latar langsung tampil saat aplikasi dibuka. */
(function () {
  'use strict';
  const BG = [
    ['abu', 'Abu Polos', '#F4F6F9', 0], ['langit', 'Biru Langit', '#CFE3F7', 0], ['sky', 'Sky', '#BAD7FA', 0], ['tosca', 'Tosca Muda', '#C7ECE6', 0],
    ['biru', 'Biru DSM', '#0B5CAD', 1], ['petrol', 'Petrol', '#10394E', 1], ['navy', 'Navy', '#0F2B4F', 1], ['royal', 'Biru Royal', '#1D4ED8', 1]
  ];
  const LS = 'dsm-ui-bg';
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  function apply(k) {
    const b = BG.find(x => x[0] === k) || BG[1], h = document.documentElement;
    h.dataset.bg = b[0]; h.style.setProperty('--uibg', b[2]);
    if (b[3]) h.dataset.bgdark = '1'; else delete h.dataset.bgdark;
    try { localStorage.setItem(LS, b[0]); } catch (e) { }
  }
  try { apply(localStorage.getItem(LS) || 'langit'); } catch (e) { apply('langit'); }

  const ui = window.dsmUi = { prefs: { bg: 'langit', pins: [], recent: [] }, BG };
  ui.save = function (patch) {
    Object.assign(ui.prefs, patch || {});
    if (patch && patch.bg) apply(patch.bg);
    try { window.dispatchEvent(new Event('dsm-ui-prefs')); } catch (e) { }
    if (typeof rpc !== 'function' || typeof TOKEN === 'undefined' || !TOKEN) return Promise.resolve(ui.prefs);
    return rpc('apiUiPrefSave', TOKEN, patch).then(r => { if (r) { ui.prefs = Object.assign(ui.prefs, r); } return ui.prefs; })
      .catch(e => { console.warn('[DSM] simpan tampilan', e); return ui.prefs; });
  };
  /* ambil preferensi dari BOOT setiap kali akun (BOOT) berganti */
  let seen = null;
  setInterval(() => {
    let b = null; try { b = (typeof BOOT !== 'undefined') ? BOOT : null; } catch (e) { b = null; }
    if (!b || b === seen) return; seen = b;
    const p = b.ui || {}; ui.prefs = { bg: p.bg || 'langit', pins: Array.isArray(p.pins) ? p.pins : [], recent: Array.isArray(p.recent) ? p.recent : [] };
    apply(ui.prefs.bg); try { window.dispatchEvent(new Event('dsm-ui-prefs')); } catch (e) { }
  }, 400);

  /* ---------- Pemilih latar (Lainnya › Tampilan) ---------- */
  function openPicker() {
    document.getElementById('uiBgSheet')?.remove();
    const cur = ui.prefs.bg || document.documentElement.dataset.bg || 'langit';
    const sw = b => `<button type="button" class="uiSw${b[0] === cur ? ' on' : ''}" data-bg="${b[0]}"><i style="background:${b[2]}"></i><span>${esc(b[1])}</span></button>`;
    const o = document.createElement('div'); o.id = 'uiBgSheet'; o.setAttribute('role', 'dialog'); o.setAttribute('aria-modal', 'true');
    o.innerHTML = `<div class="uiBox"><div class="uiGrab"></div><div class="uiHd"><b>TAMPILAN · LATAR BELAKANG</b><button type="button" data-x aria-label="Tutup">✕</button></div>
      <small class="uiGl">TERANG</small><div class="uiGrid">${BG.filter(b => !b[3]).map(sw).join('')}</div>
      <small class="uiGl">BIRU SOLID · judul di latar jadi putih</small><div class="uiGrid">${BG.filter(b => b[3]).map(sw).join('')}</div>
      <p class="uiNote">Kartu tetap putih, tombol FILTER tetap oren, warna indikator & status tidak berubah. Pilihan disimpan di akun Anda (ikut ke HP lain).</p></div>`;
    o.onclick = e => {
      if (e.target === o || e.target.closest('[data-x]')) { o.remove(); return; }
      const s = e.target.closest('[data-bg]'); if (!s) return;
      o.querySelectorAll('.uiSw').forEach(x => x.classList.toggle('on', x === s)); ui.save({ bg: s.dataset.bg });
    };
    document.body.appendChild(o);
  }
  ui.openPicker = openPicker;
  const ICO = '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 1 0 0 18c1.1 0 1.6-.9 1.2-1.8l-.4-.9c-.5-1 .2-2.3 1.4-2.3H17a4 4 0 0 0 4-4c0-5-4-9-9-9z"/><circle cx="7.5" cy="11" r="1.2"/><circle cx="10.5" cy="7.5" r="1.2"/><circle cx="15" cy="7.5" r="1.2"/></svg>';
  function inject() {
    /* desktop: tombol di area akun (menu akun v7 mengambil tombol .out secara otomatis) */
    const who = document.querySelector('.topbar .who');
    if (who && !who.querySelector('#uiBgBtn')) {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'out'; b.id = 'uiBgBtn'; b.textContent = 'Tampilan'; b.onclick = openPicker;
      const out = [...who.querySelectorAll(':scope>button.out')].find(x => /keluar/i.test(x.textContent)); if (out) out.before(b); else who.appendChild(b);
    }
    /* HP: drawer LAINNYA */
    const mm = document.querySelector('#mobileMore .mmOut');
    if (mm && !mm.querySelector('[data-ui-bg]')) {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'mmBtn'; b.dataset.uiBg = '1'; b.innerHTML = ICO + '<span>Tampilan</span>';
      b.onclick = e => { e.stopPropagation(); try { if (typeof closeMobileMore === 'function') closeMobileMore(); if (typeof syncMobileNav === 'function') syncMobileNav(); } catch (x) { } openPicker(); };
      mm.prepend(b);
    }
  }
  let q = false; new MutationObserver(() => { if (q) return; q = true; requestAnimationFrame(() => { q = false; inject(); }); }).observe(document.documentElement, { childList: true, subtree: true }); inject();
})();
