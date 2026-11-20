/* Draf di Beranda & Klaim saya + tambah nota cepat (2026-11-19).
 * Mockup disetujui: doc/mockup/2026-11-19/dsm-klaim-draf-beranda.html
 * Isian nota sama dengan form (notaHtml di form.js): tanggal, nominal, foto; Tol + gerbang; BBM + jenis BBM.
 * Isian boleh kosong saat disimpan (wajib saat kirim, dicek server claim_check). Foto wajib. */
const DRAFT_OLD_DAYS = 3;
const KINDIC = { bbm: '⛽', tol: '🛣️', trans: '🚗', tiket: '🎫', meeting: '🤝', party: '🎂', ent: '💐', kost: '🏠', hotel: '🏨', lain: '🧾', ops: '📦', perdin: '🧳', makan: '🍱' };

function draftAge(ts) {
  const d = new Date(String(ts || '').replace(' ', 'T')); if (isNaN(d)) return { txt: '', days: 0 };
  const day = x => new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime();
  const days = Math.round((day(new Date()) - day(d)) / 864e5), hm = String(d.getHours()).padStart(2, '0') + '.' + String(d.getMinutes()).padStart(2, '0');
  return { days, txt: days <= 0 ? 'Disimpan hari ini ' + hm : days === 1 ? 'Disimpan kemarin ' + hm : 'Disimpan ' + days + ' hari lalu' };
}
function draftCard(r) {
  const a = draftAge(r.updated_at), old = a.days > DRAFT_OLD_DAYS, k = (r.types[0] || {}).kind;
  const th = r.thumbs.slice(0, 3).map(f => `<div class="th" data-view="${f.id}">${/pdf/.test(f.mime || '') ? 'PDF' : `<img loading="lazy" src="${fileUrl(f.id)}" alt="">`}</div>`).join('');
  return `<div class="drf${old ? ' old' : ''}"><div class="drh" data-go="#/claim/${r.id}"><div class="dic">${KINDIC[k] || CATIC[r.category] || '🧾'}</div><div class="dtx"><b>${esc(r.category)} · ${esc(r.type)}</b>
    <span>${r.notes} nota${r.incomplete ? ` · <em>${r.incomplete} belum lengkap</em>` : ''} · <i class="${old ? 'o' : ''}">${a.txt}</i></span></div><div class="dam">${rp(r.total)}</div></div>
    <div class="thumbs">${th}${r.quick ? `<div class="th add" data-dq="${r.id}">+</div>` : ''}</div>
    <div class="dra">${r.quick ? `<button type="button" data-dq="${r.id}">📷 Tambah nota</button>` : ''}<button type="button" class="p" data-go="#/edit/${r.id}">Lanjutkan &amp; kirim</button></div></div>`;
}
/** Bagian "Draf belum dikirim" (Beranda: maks. 3 + Lihat semua). Kosong bila tidak ada draf. */
function draftSec(dl, max) {
  if (!dl || !dl.rows.length) return '';
  window.__drafts = dl.rows;
  return `<div class="sec"><h3 class="drt">Draf belum dikirim <span class="cnt">${dl.total}</span>${max && dl.total > max ? '<a data-go="#/mine?tab=draft">Lihat semua</a>' : ''}</h3>${dl.rows.slice(0, max || dl.rows.length).map(draftCard).join('')}</div>`;
}

/* ---------- tambah nota cepat ---------- */
function draftPick(multiple, onFiles) {
  const inp = document.createElement('input'); inp.type = 'file'; inp.accept = 'image/*,application/pdf'; if (multiple) inp.multiple = true; else inp.setAttribute('capture', 'environment');
  inp.onchange = () => { if (inp.files && inp.files.length) onFiles([...inp.files]); }; inp.click();
}
function quickNote(r) {
  const multi = r.types.length > 1; const Q = { type: multi ? null : r.types[0], files: [], date: today(), nominal: '', bbm: '', asal: '', tujuan: '' };
  const fields = () => {
    const k = Q.type && Q.type.kind; let h = '';
    if (multi) h += fld('Jenis nota', `<div class="qchips">${r.types.map(t => `<div class="chip${Q.type && Q.type.id === t.id ? ' on' : ''}" data-qt="${t.id}">${esc(t.name)}</div>`).join('')}</div>`);
    if (!Q.type) return h;
    if (k === 'tol') h += `<div class="two">${fld('Gerbang asal', typed('qga', S.L.gate || [], Q.asal, 'Contoh: GT Kalikangkung', false))}${fld('Gerbang tujuan', typed('qgt', S.L.gate || [], Q.tujuan, 'Contoh: GT Cikampek Utama', false))}</div>`;
    h += `<div class="two">${fld('Tanggal nota', `<input type="date" id="qd" value="${Q.date}" max="${today()}">`)}${fld(k === 'bbm' ? 'Nominal nota (Rp)' : 'Nominal (Rp)', `<input id="qn" inputmode="numeric" placeholder="0" value="${Q.nominal}">`)}</div>`;
    if (k === 'bbm') h += `<div class="fld"><label>Jenis BBM</label><div class="qchips two2">${['Pertalite', 'Pertamax'].map(v => `<div class="chip${Q.bbm === v ? ' on' : ''}" data-qb="${v}">${v}</div>`).join('')}</div></div><div id="qcalc"></div>`;
    return h;
  };
  const body = () => `<h3 class="qh">Tambah nota</h3><div class="mut" style="margin:-4px 0 10px">${esc(r.category)} · ${esc(r.type)} · nota ke-${r.notes + 1}</div>
    <div class="fld"><label>Foto nota (${Q.files.length})</label><div class="thumbs">${Q.files.map(f => `<div class="th" data-view="${f.id}">${/pdf/.test(f.mime || '') ? 'PDF' : `<img src="${fileUrl(f.id)}" alt="">`}<b data-rm="${f.id}">×</b></div>`).join('')}<div class="th add" id="qadd">📷</div></div>${Q.type && Q.type.kind === 'tol' ? '<div class="mut">Struk tol atau screenshot riwayat e-toll yang memperlihatkan tanggal, gerbang, dan nominal. Foto kartu saja tidak sah.</div>' : ''}</div>
    <div id="qf">${fields()}</div>
    <div class="note blue" style="margin-top:4px">Isian yang dikosongkan wajib diisi saat <b>Lanjutkan &amp; kirim</b>. Foto wajib.</div>
    <div class="two" style="margin-top:8px"><button class="btn g" id="qx">BATAL</button><button class="btn" id="qok">SIMPAN KE DRAF</button></div>`;
  sheet(`<div id="qroot">${body()}</div>`, (el, close) => {
    const keep = () => { const g = id => el.querySelector('#' + id); if (g('qd')) Q.date = g('qd').value; if (g('qn')) Q.nominal = g('qn').value; if (g('qga')) Q.asal = g('qga').value; if (g('qgt')) Q.tujuan = g('qgt').value; };
    const calc = () => { const c = el.querySelector('#qcalc'); if (!c) return; keep();
      if (!Q.bbm) { c.className = 'note blue'; c.innerHTML = 'Pilih jenis BBM, perhitungan klaim muncul otomatis.'; return; }
      const x = bbmCalc({ nominal: Q.nominal, date: Q.date, detail: { bbm: Q.bbm } });
      if (!x.p) { c.className = 'note red'; c.innerHTML = '<b>Harga BBM belum diatur Super Admin</b> untuk tanggal nota ini.'; return; }
      c.className = 'note'; c.innerHTML = `≈ ${String(x.liter).replace('.', ',')} liter · diklaim <b>${rp(x.claim)}</b>`; };
    const paint = () => { keep(); el.querySelector('#qroot').innerHTML = body(); bind(); calc(); };
    const add = list => guard(async () => { const btn = el.querySelector('#qok'); btn.disabled = true; btn.textContent = 'MENGUNGGAH…';
      try { for (const f of list) Q.files.push(await uploadFile(f, 'nota')); } finally { paint(); } });
    const bind = () => {
      el.querySelector('#qadd').onclick = () => draftPick(true, add);
      el.querySelectorAll('[data-rm]').forEach(b => b.onclick = e => { e.stopPropagation(); Q.files = Q.files.filter(f => f.id != b.dataset.rm); paint(); });
      el.querySelectorAll('[data-qt]').forEach(c => c.onclick = () => { Q.type = r.types.find(t => t.id == c.dataset.qt); paint(); });
      el.querySelectorAll('[data-qb]').forEach(c => c.onclick = () => { Q.bbm = c.dataset.qb; paint(); });
      ['qd', 'qn'].forEach(id => { const i = el.querySelector('#' + id); if (i) i.oninput = calc; });
      el.querySelector('#qx').onclick = close;
      el.querySelector('#qok').onclick = () => guard(async () => {
        keep(); if (!Q.files.length) throw new Error('Foto nota wajib.'); if (multi && !Q.type) throw new Error('Pilih jenis nota.');
        const k = Q.type.kind, detail = k === 'tol' ? { asal: Q.asal, tujuan: Q.tujuan } : k === 'bbm' && Q.bbm ? { bbm: Q.bbm } : {};
        await api('draft_note_add', { id: r.id, type_id: Q.type.id, date: Q.date, nominal: num(Q.nominal), detail, files: Q.files.map(f => f.id) });
        close(); toast('Nota ditambahkan ke draf'); route();
      });
    };
    bind(); calc();
    draftPick(false, add); // kamera langsung terbuka (masih dalam ketukan tombol)
  });
}
document.addEventListener('click', e => {
  const b = e.target.closest('[data-dq]'); if (!b) return; e.stopPropagation();
  const r = (window.__drafts || []).find(x => x.id == b.dataset.dq); if (r) quickNote(r);
}, true);
