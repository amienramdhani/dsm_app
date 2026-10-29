/* Pemilih orang bersama (gaya "Pilih Sales"): sheet dari bawah di HP, kartu di tengah di desktop.
   Baris: avatar inisial, NAMA, ROLE · AREA · NIK, centang untuk yang terpilih, kotak cari.
   API:
   - dsmPersonSheet({title, items, selected, multi, empty, ph, done})   buka sheet langsung
   - dsmPersonSelect(selectEl, {title, ph})                           ubah <select> orang jadi kolom pemilih
   - dsmPersonInput(inputEl, items, {format, title, ph})              ubah <input> teks orang jadi kolom pemilih
   items: [{value,nama,role,region,nik,note,disabled}]  (value default = nik)
   Nilai & event asli (change/input) tetap dipakai, jadi kode pemanggil tidak perlu diubah. */
(function () {
  if (window.dsmPersonSheet) return;
  const E = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const ini = n => String(n || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase() || '?';
  const subOf = p => [p.role, p.region, p.nik].filter(Boolean).join(' · ') + (p.note ? (p.role || p.region || p.nik ? ' · ' : '') + p.note : '');
  const css = `
.ppOv{position:fixed;inset:0;z-index:99999;background:rgba(18,32,58,.55);display:flex;align-items:flex-end;justify-content:center}
.ppSheet{background:#fff;width:100%;max-width:480px;max-height:82vh;border-radius:24px 24px 0 0;padding:10px 16px calc(16px + env(safe-area-inset-bottom,0));display:flex;flex-direction:column;gap:10px;box-sizing:border-box;color:#12203a;font-family:inherit}
.ppGrab{width:40px;height:5px;border-radius:99px;background:#d9e0ee;align-self:center;flex:none}
.ppHd{display:flex;justify-content:space-between;align-items:center;gap:8px}.ppHd b{font-size:18px;font-weight:800}
.ppX{width:44px;height:44px;border:0;border-radius:99px;background:#f3f5fa;font-size:22px;cursor:pointer;color:#12203a;flex:none}
.ppQ{min-height:46px;box-sizing:border-box;width:100%;font-size:16px;border:1.5px solid #d9e0ee;border-radius:12px;padding:0 14px;font-family:inherit;background:#fff;color:#12203a;flex:none}
.ppQ:focus{outline:none;border-color:#3f4fd6}
.ppList{overflow:auto;display:flex;flex-direction:column;gap:8px;min-height:60px;-webkit-overflow-scrolling:touch}
.ppItem{display:flex;align-items:center;gap:12px;min-height:60px;text-align:left;border:2px solid transparent;background:#f8fafd;border-radius:16px;padding:8px 12px;cursor:pointer;font-family:inherit;color:inherit;width:100%;box-sizing:border-box;flex:none}
.ppItem:hover{background:#eef3ff}.ppItem.on{border-color:#3f4fd6;background:#eef1ff}.ppItem[disabled]{opacity:.45;cursor:not-allowed}
.ppAv{width:40px;height:40px;border-radius:99px;background:#dce6ff;color:#3340b0;font:800 14px system-ui;display:grid;place-items:center;flex:none}
.ppItem.on .ppAv{background:#3f4fd6;color:#fff}.ppAv.none{background:#eceff5;color:#5a6784}
.ppTx{flex:1;min-width:0}.ppTx b{display:block;font-size:15px;color:#12203a;overflow-wrap:anywhere}.ppTx small{display:block;font-size:12px;color:#4a566f;margin-top:1px;overflow-wrap:anywhere}
.ppItem i{color:#3f4fd6;font-style:normal;font-weight:900}
.ppInfo{padding:14px 6px;text-align:center;color:#5a6784;font-size:13px}
.ppFoot{display:flex;gap:8px;flex:none}.ppGo{flex:1;min-height:50px;border:0;border-radius:14px;background:#3f4fd6;color:#fff;font:800 15px system-ui;cursor:pointer}.ppClr{min-height:50px;padding:0 16px;border:1.5px solid #d9e0ee;border-radius:14px;background:#fff;color:#12203a;font:700 14px system-ui;cursor:pointer}
@media(min-width:761px){.ppOv{align-items:center}.ppSheet{border-radius:24px;max-height:72vh}}
.ppField{display:flex;align-items:center;gap:10px;width:100%;box-sizing:border-box;min-height:46px;text-align:left;border:1.5px solid #d9e0ee;background:#fff;border-radius:12px;padding:5px 12px 5px 8px;cursor:pointer;font-family:inherit;color:#12203a}
.ppField:hover{border-color:#aebbe0}.ppField:focus{outline:none;border-color:#3f4fd6;box-shadow:0 0 0 3px rgba(63,79,214,.15)}
.ppField .ppAv{width:32px;height:32px;font-size:12px}.ppField .ppTx b{font-size:14px;font-weight:700}.ppField .ppTx small{font-size:11.5px}
.ppField .ppPh{flex:1;color:#7a879f;font-size:14px}.ppField .ppCh{color:#6b7a99;font-size:12px;flex:none}
.pp-native{position:absolute!important;width:1px!important;height:1px!important;opacity:0!important;pointer-events:none!important;overflow:hidden!important;margin:0!important;padding:0!important;border:0!important}
.ppWrap{position:relative;width:100%;min-width:0}`;
  function addCss() { if (document.getElementById('ppStyle')) return; const s = document.createElement('style'); s.id = 'ppStyle'; s.textContent = css; document.head.appendChild(s); }

  function sheet(o) {
    addCss();
    const items = (o.items || []).map(p => Object.assign({}, p, { value: p.value != null ? String(p.value) : String(p.nik || '') }));
    const multi = !!o.multi;
    const sel = new Set((Array.isArray(o.selected) ? o.selected : [o.selected]).filter(v => v != null && v !== '').map(String));
    const ov = document.createElement('div'); ov.className = 'ppOv';
    ov.innerHTML = `<div class="ppSheet" role="dialog" aria-modal="true" aria-label="${E(o.title || 'Pilih')}"><div class="ppGrab"></div><div class="ppHd"><b>${E(o.title || 'Pilih orang')}</b><button type="button" class="ppX" aria-label="Tutup">×</button></div><input class="ppQ" type="search" autocomplete="off" placeholder="${E(o.ph || 'Cari nama / NIK / role / region')}" aria-label="Cari"><div class="ppList"></div>${multi ? '<div class="ppFoot"><button type="button" class="ppClr">Kosongkan</button><button type="button" class="ppGo"></button></div>' : ''}</div>`;
    const list = ov.querySelector('.ppList'), q = ov.querySelector('.ppQ'), MAX = 80;
    const goBtn = ov.querySelector('.ppGo');
    const foot = () => { if (goBtn) goBtn.textContent = sel.size ? 'Pilih ' + sel.size + ' orang' : 'Selesai'; };
    const draw = () => {
      const k = q.value.trim().toLowerCase();
      const hit = items.filter(p => !k || (p.nama + ' ' + (p.role || '') + ' ' + (p.region || '') + ' ' + (p.nik || '') + ' ' + (p.note || '')).toLowerCase().includes(k));
      let html = '';
      if (o.empty && !multi && (!k || String(o.empty).toLowerCase().includes(k))) html += `<button type="button" class="ppItem ${sel.size ? '' : 'on'}" data-v=""><span class="ppAv none">–</span><span class="ppTx"><b>${E(o.empty)}</b></span>${sel.size ? '' : '<i>✓</i>'}</button>`;
      const kr = q.value.trim();
      if (o.free && kr && !items.some(p => String(p.nik).toLowerCase() === kr.toLowerCase())) html += `<button type="button" class="ppItem" data-v="${E(kr)}"><span class="ppAv none">?</span><span class="ppTx"><b>Pakai NIK "${E(kr)}"</b><small>Tidak ada di daftar akun</small></span></button>`;
      html += hit.slice(0, MAX).map(p => { const on = sel.has(p.value); return `<button type="button" class="ppItem ${on ? 'on' : ''}" data-v="${E(p.value)}" ${p.disabled ? 'disabled' : ''}><span class="ppAv">${E(ini(p.nama))}</span><span class="ppTx"><b>${E(p.nama)}</b><small>${E(subOf(p))}</small></span>${on ? '<i>✓</i>' : ''}</button>`; }).join('');
      if (hit.length > MAX) html += `<div class="ppInfo">Menampilkan ${MAX} dari ${hit.length}. Ketik untuk mempersempit.</div>`;
      if (!html) html = '<div class="ppInfo">Tidak ada yang cocok.</div>';
      list.innerHTML = html; foot();
    };
    const close = () => { ov.remove(); document.removeEventListener('keydown', esc, true); };
    const esc = e => { if (e.key === 'Escape') { e.stopPropagation(); close(); } };
    ov.addEventListener('click', e => {
      if (e.target === ov || e.target.closest('.ppX')) return close();
      if (e.target.closest('.ppClr')) { sel.clear(); draw(); return; }
      if (e.target.closest('.ppGo')) { const v = [...sel]; close(); o.done && o.done(v); return; }
      const b = e.target.closest('.ppItem'); if (!b || b.disabled) return;
      const v = b.dataset.v;
      if (multi) { sel.has(v) ? sel.delete(v) : sel.add(v); const y = list.scrollTop; draw(); list.scrollTop = y; }
      else { close(); o.done && o.done(v); }
    });
    q.addEventListener('keydown', e => { if (e.key === 'Enter' && !multi) { e.preventDefault(); const f = list.querySelector('.ppItem:not([disabled])'); if (f) { const v = f.dataset.v; close(); o.done && o.done(v); } } });
    q.addEventListener('input', draw);
    document.addEventListener('keydown', esc, true);
    draw(); document.body.appendChild(ov); setTimeout(() => q.focus(), 0);
    return { close };
  }

  function fieldHtml(p, ph) {
    return p
      ? `<span class="ppAv">${E(ini(p.nama))}</span><span class="ppTx"><b>${E(p.nama)}</b><small>${E(subOf(p))}</small></span><span class="ppCh">▾</span>`
      : `<span class="ppPh">${E(ph)}</span><span class="ppCh">▾</span>`;
  }

  /* <select> orang -> kolom pemilih. Data per opsi dibaca dari data-nama/role/region/nik, atau dari teks "Nama · ROLE · ..." */
  function select(sel, o) {
    if (!sel || sel.dataset.pp) return; o = o || {}; addCss(); sel.dataset.pp = '1';
    const wrap = document.createElement('div'); wrap.className = 'ppWrap'; sel.parentNode.insertBefore(wrap, sel); wrap.appendChild(sel); sel.classList.add('pp-native'); sel.tabIndex = -1;
    const btn = document.createElement('button'); btn.type = 'button'; btn.className = 'ppField'; wrap.appendChild(btn);
    const read = () => [...sel.options].filter(op => op.value !== '').map(op => {
      const d = op.dataset; let nama = d.nama, role = d.role, region = d.region, nik = d.nik, note = '';
      if (!nama) {
        const parts = op.text.split(' · ').map(s => s.trim()).filter(Boolean); nama = parts.shift() || op.value; nik = nik || op.value;
        const rest = parts.filter(s => s !== nik); role = rest[0] || ''; region = rest[1] || ''; if (rest.length > 2) note = rest.slice(2).join(' · ');
      }
      return { value: op.value, nama, role, region, nik: nik || op.value, note: note || d.note || '', disabled: op.disabled };
    });
    const emptyOpt = [...sel.options].find(op => op.value === ''); const empty = o.empty || (emptyOpt && emptyOpt.text && !/^pilih/i.test(emptyOpt.text) ? emptyOpt.text : '');
    const ph = o.ph || (emptyOpt && emptyOpt.text) || 'Pilih orang…';
    const cur = () => read().find(p => p.value === sel.value);
    const sync = () => { btn.innerHTML = fieldHtml(cur(), ph); };
    btn.onclick = () => sheet({ title: o.title || 'Pilih orang', items: read(), selected: sel.value, empty, ph: o.search, done: v => { sel.value = v; sel.dispatchEvent(new Event('change', { bubbles: true })); sync(); } });
    sel.addEventListener('change', sync);
    try { new MutationObserver(sync).observe(sel, { childList: true }); } catch (e) {}
    sync(); sel._ppSync = sync;
  }

  /* <input> teks orang -> kolom pemilih; nilai input diisi lewat format(p) supaya pembaca lama tetap jalan */
  function input(inp, items, o) {
    if (!inp || inp.dataset.pp) return; o = o || {}; addCss(); inp.dataset.pp = '1';
    const fmt = o.format || (p => p.nik); const list = () => (typeof items === 'function' ? items() : items) || [];
    const wrap = document.createElement('div'); wrap.className = 'ppWrap'; inp.parentNode.insertBefore(wrap, inp); wrap.appendChild(inp); inp.classList.add('pp-native'); inp.tabIndex = -1; inp.removeAttribute('list');
    const btn = document.createElement('button'); btn.type = 'button'; btn.className = 'ppField'; wrap.appendChild(btn);
    const ph = o.ph || 'Pilih orang…';
    const cur = () => { const v = inp.value; const f = list().find(p => fmt(p) === v || String(p.nik) === v); return f || (o.free && v ? { nama: v, role: 'NIK di luar daftar akun', nik: '' } : null); };
    const sync = () => { btn.innerHTML = fieldHtml(cur(), ph); };
    btn.onclick = () => { const c = cur(); sheet({ title: o.title || 'Pilih orang', items: list(), selected: c ? (c.value != null ? c.value : c.nik) : '', ph: o.search, free: o.free, done: v => { const p = list().find(x => String(x.value != null ? x.value : x.nik) === v); inp.value = p ? fmt(p) : (o.free ? v : ''); inp.dispatchEvent(new Event('input', { bubbles: true })); inp.dispatchEvent(new Event('change', { bubbles: true })); sync(); } }); };
    inp._ppSync = sync; sync();
  }

  window.dsmPersonCss = addCss; window.dsmPersonSheet = sheet; window.dsmPersonSelect = select; window.dsmPersonInput = input;
})();
