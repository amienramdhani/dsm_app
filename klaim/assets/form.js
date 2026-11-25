/* DSM Klaim - form pengajuan (P03-P17), ringkasan, petty cash (P19) */
const brandSel = v => { const L = S.L.brand || [], m = L.find(b => b.toLowerCase() === String(v || '').toLowerCase()) || ''; const ex = v && !m ? `<option value="${esc(v)}" selected>${esc(v)}</option>` : ''; return `<select id="fbrand"><option value="">Pilih brand…</option>${ex}${L.map(b => `<option value="${esc(b)}" ${b === m ? 'selected' : ''}>${esc(b)}</option>`).join('')}</select>`; };
'use strict';
let F = null; // state form klaim
const KIND = { BBM: 'bbm', 'Tol': 'tol', 'Parkir': 'trans', 'Servis kendaraan': 'trans', 'Ojol & taksi online': 'trans', 'Tiket perjalanan': 'tiket', 'Operasional lainnya': 'lain', 'Meeting': 'meeting', 'Makan-makan': 'party', 'Kue ulang tahun': 'party', 'Karangan bunga': 'ent', 'Reward / Hadiah': 'ent', 'Perdin': 'perdin', 'Uang makan': 'makan', 'Kost': 'kost', 'Penginapan / Hotel': 'hotel' };
const kindOf = n => KIND[n] || 'ops';
const makanLocked = (m) => { const mk = +(S.L?.rules?.min_km_makan ?? 80); return mk > 0 && num(m.km_total) < mk; };
const durDays = (a, b) => { if (!a || !b) return 0; const d = Math.round((new Date(b) - new Date(a)) / 864e5); return d < 0 ? 0 : d + 1; };
function pickFiles(cb, multiple = true, accept = 'image/*,application/pdf') {
  const i = document.createElement('input'); i.type = 'file'; i.accept = accept; i.multiple = multiple; i.style.display = 'none';
  i.onchange = async () => { const fs = [...i.files]; if (!fs.length) return; toast('Mengunggah ' + fs.length + ' berkas...'); const out = []; try { for (const f of fs) out.push(await uploadFile(f, 'nota')); cb(out); } catch (e) { toast(e.message, true); } i.remove(); };
  document.body.appendChild(i); i.click();
}
const fmtN = v => v === '' || v == null ? '' : Number(v).toLocaleString('id-ID');
const num = v => parseInt(String(v).replace(/[^\d-]/g, ''), 10) || 0;

/* ---------- P03 pilih kategori dan jenis (retouch 2026-11-24, mockup doc/mockup/2026-11-24/dsm-klaim-ajukan-opsi.html B2) ----------
   Ketuk = centang (bisa beberapa dalam satu kategori; Perdin hanya satu), lalu tombol LANJUT AJUKAN. */
const KCAT = { Transportasi: ['#1E5FB8', 'fuel'], Entertain: ['#C25A12', 'food'], Perdin: ['#0E7C6B', 'bag'], Operasional: ['#6D3FD8', 'build'] };
const KPIC = { fuel: 'M4 20V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v15M3 20h12M6 8h6M14 9h2a2 2 0 0 1 2 2v5a1.5 1.5 0 0 0 3 0V8l-3-3', food: 'M5 3v8a3 3 0 0 0 6 0V3M8 3v18M16 21V3c3 1 4 4 4 8h-4', bag: 'M4 7h16v13H4zM9 7V5a3 3 0 0 1 6 0v2M4 12h16', build: 'M3 21h18M5 21V7l7-4 7 4v14M9 9h1M14 9h1M9 13h1M14 13h1M10 21v-4h4v4',
  toll: 'M4 20 8 4M20 20 16 4M12 6v2M12 11v2M12 16v2', park: 'M4 4h16v16H4zM10 16V8h3a2.5 2.5 0 0 1 0 5h-3', car: 'M5 17h14M6 17v2M18 17v2M4 13l2-6h12l2 6v4H4z', tick: 'M4 8a2 2 0 0 0 0 4v4h16v-4a2 2 0 0 0 0-4V4H4zM14 4v12', meet: 'M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6M3 20c0-3 3-5 6-5s6 2 6 5M17 11.5a2.5 2.5 0 1 0 0-5M15 15c3 0 6 1.5 6 5',
  cake: 'M4 21h16v-8H4zM4 16c2 1 4 1 4 0s2-1 4 0 4 1 4 0 4 1 4 0M12 13V9M12 6a1 1 0 0 0 0-2', flower: 'M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6M12 11v10M12 16c-3 0-5-2-5-4M12 18c3 0 5-2 5-4', gift: 'M3 8h18v5H3zM5 13v8h14v-8M12 8v13M12 8c-2-4-6-3-4 0M12 8c2-4 6-3 4 0', plane: 'M3 13l18-7-5 14-4-6-9-1z', bed: 'M3 18V7M3 13h18v5M7 13V10h5v3',
  box: 'M3 8l9-5 9 5v8l-9 5-9-5zM3 8l9 5 9-5M12 13v8', wrench: 'M14 6a4 4 0 0 0-5 5L3 17l4 4 6-6a4 4 0 0 0 5-5l-3 3-3-3z', dots: 'M6 12h.01M12 12h.01M18 12h.01', search: 'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14M20 20l-4-4' };
const KTYPE = { BBM: 'fuel', Tol: 'toll', Parkir: 'park', 'Ojol & taksi online': 'car', 'Tiket perjalanan': 'tick', Meeting: 'meet', 'Makan-makan': 'food', 'Kue ulang tahun': 'cake', 'Karangan bunga': 'flower', 'Reward / Hadiah': 'gift', Perdin: 'plane', 'Uang makan': 'food', Kost: 'bed', 'Penginapan / Hotel': 'bed', 'ATK & perlengkapan kantor': 'box', 'Ongkir & kurir': 'box', 'Servis kendaraan': 'wrench', 'Operasional lainnya': 'dots' };
const kpic = k => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${KPIC[k] || KPIC.dots}"/></svg>`;
R.new = async () => {
  const div = QP.get('div') === '1'; const b = await api('budget');
  const cats = ['Transportasi', 'Entertain', 'Perdin', 'Operasional'];
  const en = c => div || (b.budget[c] && b.budget[c].enabled);
  const single = c => c === 'Perdin';
  shell(hero(div ? 'Ajukan untuk divisi' : 'Ajukan klaim', div ? 'ADMIN · DI LUAR SALES' : 'PEMOHON', { back: true, small: true }) +
    `<div class="sec kpwrap">${b.blocked ? `<div class="note red">Pengajuan diblokir: petty cash ${esc(b.blocked.number)} melewati tenggat.</div>` : ''}
    <label class="kpsr">${kpic('search')}<input id="ksr" placeholder="Cari jenis klaim, mis. bensin, hotel" autocomplete="off"></label>
    <div class="kphint">Ketuk satu atau beberapa jenis dalam satu kategori, lalu <b>Lanjut ajukan</b>.</div><div id="kmsg"></div>
    ${cats.map(c => { const types = S.L.types.filter(t => t.category === c && t.name !== 'Reimburse petty cash'); const bb = b.budget[c], cc = KCAT[c] || ['#0B5CAD', 'dots'];
      return `<div class="kpc${en(c) ? '' : ' off'}" style="--c:${cc[0]}" data-kc="${c}"><div class="kpt"><span class="kpic2">${kpic(cc[1])}</span><b>${c}</b>${!div && bb && bb.enabled && !bb.none ? `<small style="${bb.available < 0 ? 'color:#D2453F;font-weight:800' : ''}">sisa <b>${rp(bb.available)}</b></small>` : en(c) ? '<small>tanpa batas budget</small>' : ''}</div>
      ${en(c) ? `<div class="kpg">${types.map(t => `<button type="button" class="kpj" data-new="${t.id}" data-cat="${c}" data-nm="${esc(t.name.toLowerCase())}"><span class="kpji">${kpic(KTYPE[t.name] || cc[1])}</span>${esc(t.name)}</button>`).join('')}</div>${single(c) && types.length > 1 ? '<div class="kpone">Perdin: pilih salah satu</div>' : ''}` : '<div class="mut" style="margin-top:4px">Belum diaktifkan oleh Admin.</div>'}</div>`; }).join('')}
    <div class="kpbar" id="kbar"><div id="ksel" class="kpsel">Belum ada jenis dipilih</div><button class="btn" id="kgo" disabled>LANJUT AJUKAN →</button></div></div>`, { hero: false });
  let gc = null; const sel = [];
  const nameOf = id => S.L.types.find(t => t.id == id)?.name || '';
  const msg = t => { $('#kmsg').innerHTML = t ? `<div class="kpwarn">${esc(t)}</div>` : ''; };
  const paint = () => { $$('[data-new]').forEach(e => { e.classList.toggle('on', sel.includes(+e.dataset.new)); e.classList.toggle('dim', !!gc && e.dataset.cat !== gc); });
    const cc = gc ? (KCAT[gc] || ['#0B5CAD'])[0] : '#0B5CAD'; $('#kbar').style.setProperty('--c', cc);
    $('#ksel').innerHTML = sel.length ? `${esc(gc)}: ${sel.map(id => `<span>${esc(nameOf(id))}</span>`).join('')}` : 'Belum ada jenis dipilih';
    $('#kgo').disabled = !sel.length; $('#kgo').textContent = sel.length ? `LANJUT AJUKAN (${sel.length} jenis) →` : 'LANJUT AJUKAN →'; };
  $$('[data-new]').forEach(e => e.onclick = () => { if (b.blocked) return toast('Pengajuan diblokir.', true);
    const id = +e.dataset.new, c = e.dataset.cat; msg('');
    if (gc && c !== gc) { sel.length = 0; msg('Pilihan sebelumnya dihapus: satu klaim hanya untuk satu kategori (' + c + ').'); }
    gc = c; const i = sel.indexOf(id);
    if (i >= 0) sel.splice(i, 1); else if (single(c)) { sel.length = 0; sel.push(id); } else sel.push(id);
    if (!sel.length) gc = null; paint(); });
  $('#kgo').onclick = () => { if (!sel.length) return; location.hash = '#/form/' + sel[0] + (sel.length > 1 ? '?multi=' + sel.join(',') + (div ? '&div=1' : '') : (div ? '?div=1' : '')); };
  $('#ksr').oninput = () => { const q = $('#ksr').value.trim().toLowerCase(); $$('[data-new]').forEach(e => e.classList.toggle('hide', !!q && !e.dataset.nm.includes(q) && !e.dataset.cat.toLowerCase().includes(q)));
    $$('[data-kc]').forEach(k => k.classList.toggle('hide', !!q && !k.querySelector('[data-new]:not(.hide)'))); };
  paint();
};

/* ---------- inisialisasi form ---------- */
function blankForm(type, div) {
  const k = kindOf(type.name);
  const f = { id: 0, type, kind: k, category: type.category, brand: '', refnote: '', location: '', meta: {}, notes: [], files: { odo_awal: [], odo_akhir: [], doc: [], maps: [] }, account: { mode: 'self' }, division: div, rev: null, ver: 0, lastTotal: 0 };
  if (k === 'perdin') { f.meta = { tgl_berangkat: today(), tgl_pulang: today(), tujuan: '', rute: [''], km_total: '' }; f.pd = { makan: true, transport: { on: false, mode: 'hari', items: [] }, inap: { on: false, mode: 'hari', items: [] }, ent: { on: false, ket: '', nominal: '', files: [] } }; }
  else if (k === 'makan') f.meta = { tgl_berangkat: today(), tgl_pulang: today(), tujuan: '', rute: [''], km_total: '' };
  else if (k === 'meeting') f.meta = { peserta: ['', ''] };
  else if (k === 'party') f.meta = { peserta: [S.me.name + ' — Pemohon', ''] };
  else if (k === 'tiket') f.meta = { moda: 'Pesawat', penumpang: S.me.name };
  else if (k === 'lain') f.meta = { rincian: [{ uraian: '', qty: 1, harga: '' }] };
  if (k !== 'perdin') f.notes.push({ nominal: '', date: today(), detail: {}, files: [] });
  return f;
}
function fromDetail(d) {
  const f = { id: d.claim.id, type: d.type, kind: d.kind, category: d.claim.category, brand: d.claim.brand, refnote: d.claim.refnote, location: d.claim.location, meta: d.meta || {}, notes: [], files: {}, account: { mode: d.claim.acct_own == 1 ? 'self' : 'other', bank: d.claim.acct_bank, no: d.claim.acct_no, name: d.claim.acct_name, reason: d.claim.acct_reason },
    division: !!(+d.claim.outside_sales), rev: d.rev, ver: +d.claim.current_version, lastTotal: (d.versions[d.versions.length - 1] || {}).total || 0 };
  for (const s of ['odo_awal', 'odo_akhir', 'doc', 'maps']) f.files[s] = d.files[s] || [];
  if (d.kind === 'perdin') {
    f.pd = { makan: false, transport: { on: false, mode: 'hari', items: [] }, inap: { on: false, mode: 'hari', items: [] }, ent: { on: false, ket: '', nominal: '', files: [] } };
    for (const n of d.notes) { const dt = n.detail || {}, k = dt.komponen;
      if (k === 'Uang makan') f.pd.makan = true;
      else if (k === 'Transportasi' || k === 'Penginapan') { const g = k === 'Transportasi' ? f.pd.transport : f.pd.inap; g.on = true; g.mode = dt.mode || 'hari'; g.items.push({ id: n.id, jenis: dt.jenis || '', nominal: dt.mode === 'hari' ? dt.per_hari : n.nominal, hari: dt.hari || 1, date: n.nota_date, files: n.files }); }
      else if (k === 'Entertain') f.pd.ent = { on: true, ket: dt.keterangan || '', nominal: n.nominal, files: n.files, id: n.id, date: n.nota_date }; }
  } else if (d.kind === 'multi') {
    f.baseMeta = { catatan: (d.meta || {}).catatan || '' }; f.meta = f.baseMeta;
    f.secs = d.parts.map(p => ({ type: p.type, kind: p.kind, meta: Array.isArray(p.meta) ? {} : { ...p.meta } }));
    f.notes = d.notes.map(n => ({ id: n.id, nominal: n.detail?.nota_nominal ?? n.nominal, date: n.nota_date, detail: n.detail || {}, files: n.files }));
  } else f.notes = d.notes.map(n => ({ id: n.id, nominal: d.kind === 'bbm' ? (n.detail?.nota_nominal ?? n.nominal) : n.nominal, date: n.nota_date, detail: n.detail || {}, files: n.files }));
  return f;
}
function blankMulti(types, div) {
  const f = blankForm(types[0], div); f.notes = []; f.baseMeta = {};
  f.secs = types.map(t => { const b = blankForm(t, div); return { type: t, kind: b.kind, meta: b.meta }; });
  f.secs.forEach(sc => f.notes.push({ nominal: '', date: today(), detail: { type_id: sc.type.id }, files: [] }));
  f.kind = 'multi'; f.meta = f.baseMeta; return f;
}
R.form = async (typeId) => {
  const ids = (QP.get('multi') || '').split(',').filter(Boolean).map(Number);
  const tp = id => S.L.types.find(t => t.id == id) || (() => { throw new Error('Jenis tidak ditemukan'); })();
  const b = await api('budget'); F = ids.length > 1 ? blankMulti(ids.map(tp), QP.get('div') === '1') : blankForm(tp(typeId), QP.get('div') === '1'); F.meal = b.meal_rate; F.selfAcct = b.account; renderForm();
};
R.edit = async (id) => {
  const [d, b] = await Promise.all([api('claim_get', { id }), api('budget')]); if (!d.perm.edit) throw new Error('Klaim ini tidak dapat diubah sekarang.');
  F = fromDetail(d); F.meal = b.meal_rate; F.selfAcct = b.account; renderForm();
};

/* ---------- perdin -> nota ---------- */
function pdNotes() {
  const m = F.meta, pd = F.pd, n = [];
  if (pd.makan && !makanLocked(m)) n.push({ nominal: 0, date: m.tgl_berangkat, detail: { komponen: 'Uang makan' }, files: [] });
  for (const [key, label] of [['transport', 'Transportasi'], ['inap', 'Penginapan']]) { const g = pd[key]; if (!g.on) continue;
    for (const it of g.items) n.push({ id: it.id, nominal: g.mode === 'hari' ? num(it.nominal) * num(it.hari) : num(it.nominal), date: it.date || m.tgl_berangkat, detail: g.mode === 'hari' ? { komponen: label, jenis: it.jenis, mode: 'hari', per_hari: num(it.nominal), hari: num(it.hari) } : { komponen: label, jenis: it.jenis, mode: 'sekali', hari: num(it.hari) || 1 }, files: it.files }); }
  if (pd.ent.on) n.push({ id: pd.ent.id, nominal: num(pd.ent.nominal), date: pd.ent.date || m.tgl_berangkat, detail: { komponen: 'Entertain', keterangan: pd.ent.ket }, files: pd.ent.files });
  return n;
}
function pdTotal() {
  const dur = durDays(F.meta.tgl_berangkat, F.meta.tgl_pulang); let t = F.pd.makan && !makanLocked(F.meta) ? (F.meal || 0) * dur : 0;
  for (const k of ['transport', 'inap']) { const g = F.pd[k]; if (g.on) t += g.items.reduce((a, i) => a + (g.mode === 'hari' ? num(i.nominal) * num(i.hari) : num(i.nominal)), 0); }
  if (F.pd.ent.on) t += num(F.pd.ent.nominal); return t;
}
/* BBM: cermin hitungan server (server tetap yang berwenang) */
const fuelAt = d => (S.L.fuel || []).find(p => p.effective_from <= (d || today()));
function bbmCalc(n) {
  const nota = num(n.nominal), j = n.detail?.bbm, p = fuelAt(n.date);
  if (!p || !j) return { p, nota, claim: nota, liter: null };
  const lit = nota / (j === 'Pertamax' ? p.pertamax : p.pertalite);
  return { p, nota, liter: Math.round(lit * 100) / 100, claim: j === 'Pertamax' ? Math.round(nota * p.pertalite / p.pertamax / 100) * 100 : nota };
}
/* satu klaim beberapa jenis: tiap bagian punya type, kind, meta; konteks F.kind/F.meta/F.type ditukar sementara */
function useSec(si) { const s = F.secs[si]; F.kind = s.kind; F.meta = s.meta; F.type = s.type; F.curSec = si; }
function useBase() { if (!F.secs) return; F.kind = 'multi'; F.meta = F.baseMeta; F.type = F.secs[0].type; F.curSec = null; }
const noteKind = n => F.secs ? (F.secs.find(s => s.type.id == n.detail?.type_id) || {}).kind : F.kind;
const typeLabel = () => F.secs ? F.secs.map(s => s.type.name).join(' + ') : F.type.name;
const noteClaim = n => noteKind(n) === 'bbm' ? bbmCalc(n).claim : num(n.nominal);
const formTotal = () => F.kind === 'perdin' ? pdTotal() : F.notes.reduce((a, n) => a + noteClaim(n), 0);
function payload() {
  const notes = F.kind === 'perdin' ? pdNotes() : F.notes.map(n => ({ id: n.id, nominal: num(n.nominal), date: n.date, detail: n.detail, files: n.files.map(f => f.id) }));
  if (F.kind === 'perdin') notes.forEach(n => n.files = (n.files || []).map(f => f.id ?? f));
  const clean = (kind, src) => { const meta = { ...src }; if (kind === 'party') meta.peserta = (meta.peserta || []).filter(x => x.split(' — ')[0].trim());
  if (kind === 'lain') meta.rincian = (meta.rincian || []).filter(r => (r.uraian || '').trim()).map(r => ({ uraian: r.uraian.trim(), qty: num(r.qty), harga: num(r.harga) }));
  if (kind === 'meeting') meta.peserta = (meta.peserta || []).filter(x => x.trim());
  if (kind === 'perdin' || kind === 'makan') meta.rute = meta.rute.filter(x => x.trim()); return meta; };
  let meta, type_ids;
  if (F.secs) { meta = { catatan: F.baseMeta.catatan || '', parts: {} }; F.secs.forEach(sc => meta.parts[sc.type.id] = clean(sc.kind, sc.meta)); type_ids = F.secs.map(sc => sc.type.id); }
  else meta = clean(F.kind, F.meta);
  return { id: F.id, category: F.category, type_id: F.secs ? F.secs[0].type.id : F.type.id, type_ids, brand: F.brand, refnote: F.refnote, location: F.location, meta, notes, account: F.account, division: F.division, files: { odo_awal: F.files.odo_awal.map(f => f.id), odo_akhir: F.files.odo_akhir.map(f => f.id), doc: F.files.doc.map(f => f.id), maps: F.files.maps.map(f => f.id) } };
}

/* ---------- render form ---------- */
function fld(label, inner, hint) { return `<div class="fld"><label>${label}</label>${inner}${hint ? `<div class="mut">${hint}</div>` : ''}</div>`; }
function inp(path, v, extra = '') { return `<input data-f="${path}" value="${esc(v ?? '')}" ${extra}>`; }
const KIC = { bbm: '⛽', tol: '🛣️', trans: '🚗', tiket: '🎫', meeting: '🤝', party: '🎂', ent: '💐', kost: '🏠', hotel: '🏨', lain: '🧾', ops: '📦' };
const pesSplit = p => { const [n, ...j] = (p || '').split(' — '); return [n, j.join(' — ')]; };
function pesInputs(pes, label, hint) {
  return `<div class="fld"><label>${label}</label>${pes.map((p, i) => { const [n, j] = pesSplit(p); return `<div style="display:flex;gap:6px;margin-bottom:6px"><input data-pp="${i}:n" value="${esc(n)}" placeholder="Nama peserta" style="flex:1.3"><input data-pp="${i}:j" value="${esc(j)}" placeholder="Jabatan / asal" style="flex:1">${pes.length > 2 ? `<button class="btn g sm" data-pesrm="${i}" style="width:auto;padding:0 12px;color:#D2453F">✕</button>` : ''}</div>`; }).join('')}<button class="btn g sm" id="pesadd">+ Peserta</button>${hint ? `<div class="mut" style="margin-top:6px">${hint}</div>` : ''}</div>`;
}
/* form padat + strip judul + warna jenis (2026-11-24, mockup doc/mockup/2026-11-24/dsm-klaim-form-padat-semua.html opsi E) */
const kt = (t, right = '') => `<div class="kft"><span>${t}</span>${right}</div>`;
const fp = (label, input, files, add) => `<div class="fld"><label>${label}</label><div class="kfp">${input}${thumbs(files, { rm: 1, add })}</div></div>`;
const KCOL = { BBM: '#D97706', Tol: '#0E7C6B', Parkir: '#2563EB', 'Ojol & taksi online': '#7C3AED', 'Servis kendaraan': '#475569', 'Tiket perjalanan': '#0891B2', Meeting: '#C2410C', 'Makan-makan': '#DB2777', 'Kue ulang tahun': '#E11D48', 'Karangan bunga': '#16A34A', 'Reward / Hadiah': '#9333EA', Kost: '#0D9488', 'Penginapan / Hotel': '#4F46E5', 'ATK & perlengkapan kantor': '#CA8A04', 'Ongkir & kurir': '#EA580C', 'Operasional lainnya': '#64748B' };
const kcol = n => KCOL[n] || '#3B78C2';
function kindBody(multi) {
  const k = F.kind, m = F.meta, t = F.type.name; let body = '';
  const ta = (path, v, ph) => `<textarea data-f="${path}"${ph ? ` placeholder="${ph}"` : ''}>${esc(v || '')}</textarea>`;
  const docF = (label, hint) => fld(label, thumbs(F.files.doc, { rm: 1, add: 'doc' }), hint);
  const TANPA = 'Tanpa foto dokumentasi, klaim tetap bisa dikirim tetapi wajib dengan BA.';
  if (k === 'bbm') body += `<div class="card">${kt('Kendaraan &amp; odometer')}<div class="kfr"><div class="fld"><label>Kendaraan</label><div class="chips">${['Mobil', 'Motor'].map(v => `<span class="chip${m.kendaraan === v ? ' on' : ''}" data-kend="${v}">${v}</span>`).join('')}</div></div>${fld('Plat kendaraan', inp('meta.plat', m.plat, 'placeholder="E 1234 AB"'))}</div>${m.kendaraan === 'Motor' ? '<div class="mut">Motor: nominal nota bebas, tanpa aturan digit.</div>' : ''}
    <div class="two">${fp('KM awal + foto', inp('meta.km_awal', m.km_awal, 'inputmode="numeric"'), F.files.odo_awal, 'odo_awal')}${fp('KM akhir + foto', inp('meta.km_akhir', m.km_akhir, 'inputmode="numeric"'), F.files.odo_akhir, 'odo_akhir')}</div><div id="kmcalc" class="mut"></div></div>`;
  if (k === 'trans' || k === 'ops') { const op = F.type.name === 'Parkir' ? ' (opsional)' : '';
    body += `<div class="card">${kt('Detail')}${fld('Keterangan' + op, ta('meta.keterangan', m.keterangan))}${docF('Foto dokumentasi' + op)}</div>`; }
  if (k === 'meeting') body += `<div class="card">${kt('Detail meeting')}${fld('Tujuan meeting', inp('meta.tujuan', m.tujuan))}${fld('Tempat meeting', inp('meta.tempat', m.tempat, 'placeholder="mis. Cafe Kopi Kenangan Paskal / Aula Hotel X"'))}<div class="two">${fld('Jam mulai', inp('meta.jam_mulai', m.jam_mulai, 'type="time"'))}${fld('Jam selesai', inp('meta.jam_selesai', m.jam_selesai, 'type="time"'))}</div>
    ${pesInputs(m.peserta || [], 'Peserta (minimal 2 orang, nama dan jabatan wajib)', 'Tulis jabatan atau asal tiap peserta, mis. Sales / Owner dealer / Supervisor.')}${docF('Foto dokumentasi meeting', TANPA)}</div>`;
  if (k === 'party') { const pes = m.peserta || [];
    body += `<div class="card">${kt('Detail acara')}${t === 'Kue ulang tahun' ? fld('Nama yang dirayakan', inp('meta.penerima', m.penerima, 'placeholder="mis. Bpk. Hendra (owner dealer)"')) : ''}<div class="two">${fld('Tempat acara', inp('meta.tempat', m.tempat, 'placeholder="mis. Aula Hotel X"'))}${fld('Keterangan', inp('meta.keterangan', m.keterangan, 'placeholder="Acara apa"'))}</div>
    ${pesInputs(pes, 'Daftar peserta (wajib, minimal 2 orang, nama dan jabatan wajib)', 'Tidak bisa dirayakan sendiri: minimal 2 orang berbeda. Pemohon otomatis jadi peserta pertama.')}${docF('Foto dokumentasi acara', TANPA)}</div>`; }
  if (k === 'tiket') body += `<div class="card">${kt('Detail perjalanan')}<div class="fld"><label>Moda</label><div class="chips">${['Pesawat', 'Kereta', 'Bus / travel'].map(v => `<span class="chip${m.moda === v ? ' on' : ''}" data-moda="${v}">${v}</span>`).join('')}</div></div>
    <div class="two">${fld('Kota asal', inp('meta.asal', m.asal, 'placeholder="mis. Jakarta (CGK)"'))}${fld('Kota tujuan', inp('meta.tujuan_kota', m.tujuan_kota, 'placeholder="mis. Surabaya (SUB)"'))}</div>
    <div class="two">${fld('Tanggal berangkat', inp('meta.tgl_berangkat', m.tgl_berangkat, 'type="date"'))}${fld('Tanggal pulang (opsional)', inp('meta.tgl_pulang', m.tgl_pulang, 'type="date"'))}</div>
    <div class="two">${fld(m.moda === 'Pesawat' ? 'Maskapai' : 'Operator', inp('meta.operator', m.operator, `placeholder="${m.moda === 'Pesawat' ? 'mis. Garuda Indonesia' : m.moda === 'Kereta' ? 'mis. KAI Argo Bromo' : 'mis. Cititrans'}"`))}${fld('Nama penumpang', inp('meta.penumpang', m.penumpang))}</div>${fld('Tujuan perjalanan', ta('meta.keterangan', m.keterangan, 'Kunjungan dealer, training, ...'))}
    <div class="note">Tiket untuk Perdin diajukan sebagai komponen Perdin. Di sini untuk perjalanan di luar Perdin. Lampirkan e-tiket / invoice di nota.</div></div>`;
  if (k === 'lain') { const rin = m.rincian || []; const sum = rin.reduce((a, r) => a + num(r.qty) * num(r.harga), 0);
    body += `<div class="card">${kt('Detail pengeluaran')}${fld('Judul pengeluaran', inp('meta.judul', m.judul, 'placeholder="mis. Sewa tenda event dealer gathering"'))}${fld('Kebutuhan / alasan', ta('meta.keterangan', m.keterangan, 'Kenapa perlu dan kenapa tidak masuk jenis lain'))}
    <div class="fld"><label>Rincian barang / jasa</label>${rin.map((r, i) => `<div class="kfrin"><input data-rin="${i}:uraian" value="${esc(r.uraian || '')}" placeholder="Uraian" style="flex:2"><input data-rin="${i}:qty" inputmode="numeric" value="${esc(r.qty ?? '')}" placeholder="Qty" style="flex:.6"><input data-rin="${i}:harga" inputmode="numeric" value="${fmtN(r.harga)}" placeholder="Harga" style="flex:1.2">${rin.length > 1 ? `<button class="kfx" data-rinrm="${i}">✕</button>` : ''}</div>`).join('')}<button class="btn g sm" id="rinadd">+ Baris rincian</button>
    <div class="mut" id="rinsum" style="margin-top:6px">Total rincian <b>${rp(sum)}</b> harus sama dengan total nota.</div></div><div class="note">Jenis ini bebas, jadi judul, alasan, dan rincian wajib diisi supaya Admin bisa menilai.</div></div>`; }
  if (k === 'ent') body += `<div class="card">${kt('Detail penerima')}<div class="two">${fld('Nama penerima', inp('meta.penerima', m.penerima))}${fld('Tempat (opsional)', inp('meta.tempat', m.tempat, 'placeholder="mis. Rumah duka"'))}</div>${fld('Keterangan', ta('meta.keterangan', m.keterangan))}${docF('Foto dokumentasi', TANPA)}</div>`;
  if (k === 'kost') body += `<div class="card">${kt('Detail kost')}<div class="two">${fld('Nama kost', inp('meta.nama_kost', m.nama_kost))}${fld('Periode sewa', inp('meta.periode', m.periode, 'placeholder="mis. Nov - Des 2026"'))}</div>${fld('Nama pemilik', inp('meta.pemilik', m.pemilik))}<div class="note">Jika rekening pemilik bukan milik Anda, klaim wajib melewati HOS.</div></div>`;
  if (k === 'hotel') body += `<div class="card">${kt('Detail hotel')}${fld('Nama hotel', inp('meta.nama_hotel', m.nama_hotel))}<div class="two">${fld('Check-in', inp('meta.checkin', m.checkin, 'type="date"'))}${fld('Check-out', inp('meta.checkout', m.checkout, 'type="date"'))}</div>${fld('Tujuan menginap', inp('meta.tujuan', m.tujuan))}</div>`;
  if (k === 'perdin') body += perdinHtml();
  else if (k === 'makan') body += makanHtml();
  else if (multi) body += `${F.notes.map((n, i) => n.detail?.type_id == F.type.id ? notaHtml(n, i) : '').join('')}<button class="btn g kfadd" data-notaadd="${F.type.id}">+ Tambah nota ${esc(F.type.name)}</button>`;
  else body += `${F.notes.map((n, i) => notaHtml(n, i)).join('')}<button class="btn g kfadd" id="notaadd">+ Tambah nota</button>`;
  return body;
}
function renderForm() {
  const _y = window.scrollY; useBase();
  const k = F.kind, m = F.meta, t = typeLabel(); const total = formTotal();
  const editRev = F.rev;
  let body = `<div class="card">${kt('Info klaim')}${editRev ? `<div class="note blue">Versi klaim saat ini: <b>v${F.ver}</b>. Catatan peminta revisi: “${esc(editRev.note || '')}”</div><div id="nomwarn"></div>` : ''}
  <div class="two">${fld('Brand', brandSel(F.brand))}${fld('Refnote (opsional)', inp('refnote', F.refnote, 'placeholder="mis. IT-SEMARANG"'))}</div>
  ${k !== 'perdin' && k !== 'makan' ? fld('Lokasi (kota/kabupaten)', `<input id="floc" value="${esc(F.location || '')}" placeholder="Ketik kota/kabupaten" autocomplete="off">`, 'Detail tempat tulis di keterangan.') : ''}</div>`;
  if (F.secs) {
    const sub = sc => F.notes.filter(n => n.detail?.type_id == sc.type.id).reduce((x, n) => x + noteClaim(n), 0), cnt = sc => F.notes.filter(n => n.detail?.type_id == sc.type.id).length;
    body += `<div class="kfmx"><div class="t">PENGAJUAN SEKALIGUS · ${F.secs.length} JENIS</div><div class="jj">${F.secs.map((sc, si) => `<a data-jsec="${si}" style="--sc:${kcol(sc.type.name)}">${esc(sc.type.name)} · ${cnt(sc)} nota<b data-subt="${sc.type.id}">${rp(sub(sc))}</b></a>`).join('')}</div><div class="sum"><span>Total klaim</span><b data-tot>${rp(total)}</b></div></div>`;
    F.secs.forEach((sc, si) => { useSec(si);
      body += `<div data-sec="${si}"><div class="secHead kfsh" style="--sc:${kcol(sc.type.name)}"><span class="ic">${kpic(KTYPE[sc.type.name] || 'dots')}</span><div><b>${esc(sc.type.name)}</b><small>${cnt(sc)} nota</small></div><em data-subt="${sc.type.id}">${rp(sub(sc))}</em></div>${kindBody(true)}</div>`; }); useBase(); }
  else body += kindBody(false);
  body += `<div class="card">${kt('Keterangan')}${fld('Keterangan tambahan (opsional)', `<textarea data-f="meta.catatan" placeholder="Tulis jika ada hal yang perlu diketahui pemeriksa">${esc(m.catatan || '')}</textarea>`)}</div>`;
  body += `<div class="card" id="rekbox">${rekHtml()}</div><div class="card row kftot"><b>Total</b><b id="tot" data-tot>${rp(total)}</b></div>
   <div class="two" style="margin-top:8px"><button class="btn g" id="bsave">Simpan draft</button><button class="btn" id="bnext">${editRev ? 'Lanjut kirim perbaikan' : 'Lanjut ringkasan'}</button></div>`;
  shell(hero((editRev ? 'Perbaiki: ' : 'Form ') + t, F.division ? 'ADMIN · DI LUAR SALES' : 'P' + (F.id ? '17' : '04'), { back: true, small: true }) + `<div class="sec kf" data-kc="${esc(F.category || F.type.category || '')}"><div id="kfban"></div>${body}</div>`, { hero: false });
  bindForm(); updNom(); kfApply(); window.scrollTo(0, _y);
}
function notaHtml(n, i) {
  const tol = F.kind === 'tol';
  const rm = (F.secs ? F.notes.filter(x => x.detail?.type_id == n.detail?.type_id).length : F.notes.length) > 1 ? `<span data-notarm="${i}">Hapus</span>` : '';
  return `<div class="card" data-nota="${i}">${kt('Nota ' + (i + 1), rm)}
   ${tol ? `<div class="two">${fld('Gerbang asal', typed('ga' + i, S.L.gate, n.detail.asal, 'Contoh: GT Kalikangkung', false))}${fld('Gerbang tujuan', typed('gt' + i, S.L.gate, n.detail.tujuan, 'Contoh: GT Cikampek Utama', false))}</div>` : ''}
   <div class="kf3">${fld('Tanggal nota', `<input type="date" data-n="${i}:date" value="${n.date || ''}" max="${today()}"${F.kind === 'makan' ? ` min="${F.meta.tgl_berangkat}"` : ''}>`)}${fld('Nominal (Rp)', `<input inputmode="numeric" data-n="${i}:nominal" value="${fmtN(n.nominal)}" placeholder="0">`)}${fld('Nota (' + n.files.length + ')', thumbs(n.files, { rm: 1, add: 'nota:' + i }))}</div>
   ${F.kind === 'bbm' ? `<div class="fld"><label>Jenis BBM</label><div class="chips">${['Pertalite', 'Pertamax'].map(v => `<span class="chip${n.detail?.bbm === v ? ' on' : ''}" data-bbm="${i}:${v}">${v}</span>`).join('')}</div></div><div data-calc="${i}"></div>` : ''}
   ${tol ? '<div class="mut">Struk tol atau screenshot riwayat e-toll yang memperlihatkan tanggal, gerbang, dan nominal. Foto kartu saja tidak sah.</div>' : ''}</div>`;
}
function rekHtml() {
  const a = F.account, sa = F.selfAcct || {};
  return `${kt('Rekening tujuan')}<div class="opt ${a.mode === 'self' ? 'on' : ''}" data-acc="self"><div class="dotr"></div><div><b>Rekening saya</b><span class="mut">${esc(sa.bank || '-')} ${esc(sa.no || '')} a.n. ${esc(sa.name || '')}</span></div></div>
  <div class="opt ${a.mode === 'other' ? 'on' : ''}" data-acc="other"><div class="dotr"></div><div><b>Rekening lain</b><span class="mut">Vendor, pemilik kost, hotel, atau pihak lain</span></div></div>
  ${a.mode === 'other' ? `<div class="note">Rekening bukan milik pemohon <b>wajib melewati HOS</b>.</div>${fld('Bank', typed('fbank', S.L.bank, a.bank, 'Pilih bank'))}${fld('Nomor rekening', inp('account.no', a.no, 'inputmode="numeric"'))}${fld('Atas nama', inp('account.name', a.name))}${fld('Alasan lengkap', `<textarea data-f="account.reason">${esc(a.reason || '')}</textarea>`)}` : ''}`;
}
function tripCard(m, dur) {
  return `<div class="card">${kt('Perjalanan')}<div class="two">${fld('Tanggal berangkat', `<input type="date" data-f="meta.tgl_berangkat" value="${m.tgl_berangkat}">`)}${fld('Tanggal pulang', `<input type="date" data-f="meta.tgl_pulang" value="${m.tgl_pulang}" min="${m.tgl_berangkat}">`)}</div><div class="pill p-adm" id="dur">Durasi ${dur} hari (berangkat dan pulang dihitung penuh)</div>
   ${fld('Tujuan perjalanan', inp('meta.tujuan', m.tujuan))}
   <div class="fld"><label>Rute (tempat tujuan spesifik)</label>${m.rute.map((r, i) => `<div class="ruteitem"><div class="no">${i + 1}</div><input data-rt="${i}" value="${esc(r)}" placeholder="mis. Toko Samsung Ahmad Yani, Tegal"><button data-rtup="${i}">↑</button><button data-rtdn="${i}">↓</button>${m.rute.length > 1 ? `<button data-rtrm="${i}">✕</button>` : ''}</div>`).join('')}<button class="btn g sm" id="rtadd">+ Tambah tujuan</button><div class="mut" style="margin-top:4px">Rute berhenti di tujuan akhir.</div></div>
   <div class="kfr">${fld('Screenshot Maps', thumbs(F.files.maps, { rm: 1, add: F.files.maps.length ? '' : 'maps' }))}${fld('Total jarak dari Maps (km)', inp('meta.km_total', m.km_total, 'inputmode="decimal"'), 'Satu gambar, semua titik terlihat.')}</div>${makanLocked(m) && num(m.km_total) > 0 ? `<div class="note red">Uang makan belum bisa diklaim — jarak Perdin ${num(m.km_total)} km, minimal ${+(S.L?.rules?.min_km_makan ?? 80)} km.</div>` : (num(m.km_total) > 0 ? `<div class="note ok">Jarak ${num(m.km_total)} km memenuhi minimal ${+(S.L?.rules?.min_km_makan ?? 80)} km.</div>` : '')}</div>`;
}
function makanCap() {
  const m = F.meta, dur = durDays(m.tgl_berangkat, m.tgl_pulang), rate = F.meal || 0, cap = rate * dur, tot = F.notes.reduce((a, n) => a + num(n.nominal), 0), over = tot > cap, pct = cap ? Math.min(100, Math.round(tot * 100 / cap)) : 0;
  return `${rate ? `<div class="row"><span class="mut">Batas maksimal: ${rp(rate)}/hari × ${dur} hari</span><b>${rp(cap)}</b></div><div class="rf-bar" style="height:10px;border-radius:99px;background:#E8EEF6;overflow:hidden;margin:6px 0"><i style="display:block;height:100%;width:${pct}%;background:${over ? '#D2453F' : 'linear-gradient(90deg,#12A67F,#0E7C6B)'}"></i></div><div class="row"><span class="mut">Terpakai</span><b style="color:${over ? '#D2453F' : 'inherit'}">${rp(tot)}</b></div>${over ? `<div class="note red">Melebihi batas ${rp(cap)}. Kurangi nominal agar bisa dikirim.</div>` : ''}` : '<div class="note red">Tarif uang makan Anda belum diatur Admin.</div>'}`;
}
function makanHtml() {
  const m = F.meta, dur = durDays(m.tgl_berangkat, m.tgl_pulang);
  return tripCard(m, dur) + `${F.notes.map((n, i) => notaHtml(n, i)).join('')}<button class="btn g kfadd" id="notaadd">+ Tambah nota makan</button>
   <div class="card">${kt('Batas uang makan')}<div id="makancap">${makanCap()}</div></div>`;
}
function perdinHtml() {
  const m = F.meta, pd = F.pd, dur = durDays(m.tgl_berangkat, m.tgl_pulang);
  const grp = (key, label, g) => `<div class="card"><div class="row"><b>${label}</b>${sw(g.on, 'pd.' + key)}</div>${g.on ? `<div class="tabs" style="padding:8px 0 0"><span class="${g.mode === 'hari' ? 'a' : ''}" data-gmode="${key}:hari">Per hari</span><span class="${g.mode === 'sekali' ? 'a' : ''}" data-gmode="${key}:sekali">Sekali</span></div>
    ${g.items.map((it, i) => `<div class="card kfsub">${kt(label + ' ' + (i + 1), `<span data-gitrm="${key}:${i}">Hapus</span>`)}${fld('Jenis', `<input data-gi="${key}:${i}:jenis" value="${esc(it.jenis)}" placeholder="${key === 'transport' ? 'Travel, sewa motor, tol, BBM...' : 'Hotel, kost...'}">`)}
    <div class="two">${fld(g.mode === 'hari' ? 'Nominal per hari' : 'Nominal total', `<input inputmode="numeric" data-gi="${key}:${i}:nominal" value="${fmtN(it.nominal)}">`)}${fld(g.mode === 'hari' ? `Jumlah hari (maks ${dur})` : 'Hari tercakup', `<input inputmode="numeric" data-gi="${key}:${i}:hari" value="${it.hari}">`)}</div>
    ${g.mode === 'hari' ? `<div class="mut" data-gisum="${key}:${i}">= ${rp(num(it.nominal) * num(it.hari))}</div>` : ''}${fld('Lampiran nota (' + it.files.length + ')' + ' · opsional', thumbs(it.files, { rm: 1, add: 'gi:' + key + ':' + i }))}</div>`).join('')}
    <button class="btn g sm" data-giadd="${key}" style="margin-top:8px">+ Tambah ${label.toLowerCase()}</button>` : ''}</div>`;
  return `<div class="card">${kt('Perjalanan')}<div class="two">${fld('Tanggal berangkat', `<input type="date" data-f="meta.tgl_berangkat" value="${m.tgl_berangkat}">`)}${fld('Tanggal pulang', `<input type="date" data-f="meta.tgl_pulang" value="${m.tgl_pulang}" min="${m.tgl_berangkat}">`)}</div><div class="pill p-adm" id="dur">Durasi ${dur} hari (berangkat dan pulang dihitung penuh)</div>
   ${fld('Tujuan perjalanan', inp('meta.tujuan', m.tujuan))}
   <div class="fld"><label>Rute (tempat tujuan spesifik)</label>${m.rute.map((r, i) => `<div class="ruteitem"><div class="no">${i + 1}</div><input data-rt="${i}" value="${esc(r)}" placeholder="mis. Toko Samsung Ahmad Yani, Tegal"><button data-rtup="${i}">↑</button><button data-rtdn="${i}">↓</button>${m.rute.length > 1 ? `<button data-rtrm="${i}">✕</button>` : ''}</div>`).join('')}<button class="btn g sm" id="rtadd">+ Tambah tujuan</button><div class="mut" style="margin-top:4px">Rute berhenti di tujuan akhir.</div></div>
   <div class="kfr">${fld('Screenshot Maps', thumbs(F.files.maps, { rm: 1, add: F.files.maps.length ? '' : 'maps' }))}${fld('Total jarak dari Maps (km)', inp('meta.km_total', m.km_total, 'inputmode="decimal"'), 'Satu gambar, semua titik terlihat.')}</div></div>
   <div class="card"><div class="row"><div><b>Uang makan</b><div class="mut">Otomatis: tarif per hari × durasi. Terkunci.</div></div>${makanLocked(m) ? '<span class="pill p-no">Belum bisa</span>' : sw(pd.makan, 'pd.makan')}</div>${makanLocked(m) ? `<div class="note red">Uang makan belum bisa diklaim — jarak Perdin ${num(m.km_total) || 0} km, minimal ${+(S.L?.rules?.min_km_makan ?? 80)} km.</div>` : ''}${pd.makan && !makanLocked(m) ? `<div class="kv"><span>Tarif per hari (diatur Admin)</span><span>${F.meal ? rp(F.meal) : '<span style="color:#D2453F">Belum diatur</span>'}</span></div><div class="kv"><span>${dur} hari</span><span>${rp((F.meal || 0) * dur)}</span></div>` : ''}</div>
   ${grp('transport', 'Transportasi', pd.transport)}${grp('inap', 'Penginapan', pd.inap)}
   <div class="card"><div class="row"><b>Entertain (opsional)</b>${sw(pd.ent.on, 'pd.ent')}</div>${pd.ent.on ? `${fld('Keterangan', inp('pd.ent.ket', pd.ent.ket))}${fld('Nominal', `<input inputmode="numeric" data-f="pd.ent.nominal" value="${fmtN(pd.ent.nominal)}">`)}${fld('Lampiran nota (' + pd.ent.files.length + ') · opsional', thumbs(pd.ent.files, { rm: 1, add: 'pdent' }))}` : ''}</div>`;
}
function setPath(o, path, v) { const p = path.split('.'); let c = o; for (let i = 0; i < p.length - 1; i++) { c[p[i]] ??= {}; c = c[p[i]]; } c[p[p.length - 1]] = v; }
function bbmBox() {
  const L = x => String(x).replace('.', ',');
  const tg = d => { try { return new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }); } catch (e) { return d; } };
  const row = (a, b, bold) => `<div style="display:flex;justify-content:space-between;gap:8px;padding:3px 0"><span>${a}</span><b${bold ? ' style="font-size:20px;color:#9A5A00"' : ''}>${b}</b></div>`;
  $$('[data-calc]').forEach(e => { const n = F.notes[e.dataset.calc], c = bbmCalc(n), j = n.detail?.bbm;
    e.className = ''; e.style.cssText = '';
    if (!j) { e.className = 'note blue'; e.innerHTML = 'Pilih jenis BBM, perhitungan klaim muncul otomatis di sini.'; return; }
    if (!c.p) { e.className = 'note red'; e.innerHTML = '<b>Harga BBM belum diatur Super Admin</b> untuk tanggal nota ini. Klaim belum bisa dikirim.'; return; }
    const gap = Math.max(0, c.nota - c.claim), t = tg(n.date);
    const box = j === 'Pertalite'
      ? row(`Harga Pertalite (${t})`, rp(c.p.pertalite) + ' / liter') + row('Perkiraan liter', L(c.liter) + ' L') + '<div style="border-top:1px dashed #E8B04A;margin:6px 0"></div>' + row('NOMINAL DIKLAIM', rp(c.claim), 1)
      : row(`Harga Pertamax (${t})`, rp(c.p.pertamax) + ' / liter') + row('Jumlah liter', L(c.liter) + ' L') + row(`Harga Pertalite (${t})`, rp(c.p.pertalite) + ' / liter') + row(`${L(c.liter)} L × ${rp(c.p.pertalite)}`, rp(c.claim)) + '<div style="border-top:1px dashed #E8B04A;margin:6px 0"></div>' + row('NOMINAL DIKLAIM', rp(c.claim), 1);
    e.innerHTML = `<div style="background:linear-gradient(135deg,#FFF4D6,#FFE2A8);border:1px solid #F0B63C;border-radius:14px;padding:12px 14px;font-size:13px">${box}</div>`
      + (gap > 0 ? `<div class="note" style="margin-top:8px;background:#FFF1E0;border-left:3px solid #F0A400">Selisih <b>${rp(gap)}</b> (nota ${rp(c.nota)} − diklaim) ditanggung pemohon dan tidak memicu BA.</div>` : '')
      + (F.meta.kendaraan === 'Mobil' ? '<div class="mut" style="margin-top:6px">🚗 Mobil: digit belakang nominal nota harus sesuai aturan (dicek otomatis per nota).</div>' : ''); });
}
function updNom() {
  const bi = F.secs ? F.secs.findIndex(x => x.kind === 'bbm') : -1; if (bi >= 0) useSec(bi);
  try {
    if (F.kind === 'bbm') bbmBox();
    const bbmTot = F.notes.filter(n => noteKind(n) === 'bbm').reduce((x, n) => x + noteClaim(n), 0);
    if ($('#kmcalc')) { const a = num(F.meta.km_awal), b = num(F.meta.km_akhir); $('#kmcalc').textContent = b > a ? `Jarak ${b - a} km · biaya per km ${rp(Math.round(bbmTot / (b - a)))}` : ''; }
  } finally { if (bi >= 0) useBase(); }
  if (F.kind === 'makan' && $('#makancap')) $('#makancap').innerHTML = makanCap();
  const t = formTotal(); $$('[data-tot]').forEach(el => el.textContent = rp(t));
  if (F.secs) F.secs.forEach(sc => { $$(`[data-subt="${sc.type.id}"]`).forEach(e => e.textContent = rp(F.notes.filter(n => n.detail?.type_id == sc.type.id).reduce((x, n) => x + noteClaim(n), 0))); });
  $$('[data-gisum]').forEach(e => { const [k, i] = e.dataset.gisum.split(':'); const it = F.pd[k].items[i]; e.textContent = '= ' + rp(num(it.nominal) * num(it.hari)); });
  if ($('#dur')) $('#dur').textContent = `Durasi ${durDays(F.meta.tgl_berangkat, F.meta.tgl_pulang)} hari (berangkat dan pulang dihitung penuh)`;
  const w = $('#nomwarn'); if (w && F.rev) w.innerHTML = t !== +F.lastTotal ? `<div class="note red">Nominal berubah dari ${rp(F.lastTotal)} ke ${rp(t)}. Perubahan nominal membuat <b>versi klaim baru</b> dan membutuhkan approval ulang untuk versi terbaru${F.kind === 'perdin' ? '' : ''}. Jika klaim ber-BA, tanda tangan dan approval BA juga diulang.</div>` : '';
}
function bindForm() {
  const root = $('#app');
  const ctx = h => e => { const si = e.target.closest?.('[data-sec]')?.dataset.sec; if (F.secs && si != null) useSec(+si); try { return h(e); } finally { useBase(); } };
  root.oninput = ctx(e => {
    const t = e.target;
    if (t.dataset.f) { let v = t.value; if (/nominal$/.test(t.dataset.f)) { v = num(v); t.value = fmtN(v); } setPath(F, t.dataset.f, v); updNom(); }
    else if (t.dataset.n) { const [i, k] = t.dataset.n.split(':'); let v = t.value; if (k === 'nominal') { v = num(v); t.value = fmtN(v); } F.notes[i][k] = v; updNom(); }
    else if (t.dataset.pes) F.meta.peserta[t.dataset.pes] = t.value;
    else if (t.dataset.pp) { const [i, w] = t.dataset.pp.split(':'); const [n, ...j] = (F.meta.peserta[i] || '').split(' — '); const nn = w === 'n' ? t.value : n, jj = w === 'j' ? t.value : j.join(' — '); F.meta.peserta[i] = nn + (jj.trim() ? ' — ' + jj : ''); }
    else if (t.dataset.rin) { const [i, f] = t.dataset.rin.split(':'); let v = t.value; if (f === 'harga') { v = num(v); t.value = fmtN(v); } F.meta.rincian[i][f] = v; const s = F.meta.rincian.reduce((a, r) => a + num(r.qty) * num(r.harga), 0); const el = $('#rinsum'); if (el) el.innerHTML = `Total rincian <b>${rp(s)}</b> harus sama dengan total nota (${rp(formTotal())}).`; }
    else if (t.dataset.rt) F.meta.rute[t.dataset.rt] = t.value;
    else if (t.dataset.gi) { const [k, i, f] = t.dataset.gi.split(':'); let v = t.value; if (f === 'nominal') { v = num(v); t.value = fmtN(v); } F.pd[k].items[i][f] = v; updNom(); }
    else if (t.id === 'fbrand') F.brand = t.value; else if (t.id === 'floc') F.location = t.value;
    else if (t.id === 'fbank') F.account.bank = t.value;
    else if (/^g[at]\d+$/.test(t.id)) { const i = +t.id.slice(2); F.notes[i].detail[t.id[1] === 'a' ? 'asal' : 'tujuan'] = t.value; }
  });
  root.onchange = ctx(e => { const t = e.target; if (t.dataset.f && /tgl_|km_total/.test(t.dataset.f)) renderForm(); });
  root.onclick = ctx(e => {
    const t = e.target;
    const add = t.closest('[data-addfile]'); if (add && add.dataset.addfile) { const w = add.dataset.addfile; pickFiles(fs => { addFiles(w, fs); renderForm(); }, w !== 'maps'); return; }
    const rm = t.closest('[data-rm]'); if (rm) { e.stopPropagation(); const id = +rm.dataset.rm; const strip = a => { const i = a.findIndex(f => f.id == id); if (i >= 0) a.splice(i, 1); }; for (const s in F.files) strip(F.files[s]); F.notes.forEach(n => strip(n.files)); if (F.pd) { F.pd.transport.items.forEach(i => strip(i.files)); F.pd.inap.items.forEach(i => strip(i.files)); strip(F.pd.ent.files); } renderForm(); return; }
    const js = t.closest('[data-jsec]'); if (js) { $(`[data-sec="${js.dataset.jsec}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'start' }); return; }
    if (t.closest('[data-view]')) return;
    const sv = t.closest('[data-sw]'); if (sv) { const [a, k] = sv.dataset.sw.split('.'); if (k === 'makan') F.pd.makan = !F.pd.makan; else F.pd[k].on = !F.pd[k].on; renderForm(); return; }
    const ac = t.closest('[data-acc]'); if (ac) { F.account.mode = ac.dataset.acc; $('#rekbox').innerHTML = rekHtml(); return; }
    const gm = t.closest('[data-gmode]'); if (gm) { const [k, m] = gm.dataset.gmode.split(':'); F.pd[k].mode = m; renderForm(); return; }
    const ga = t.closest('[data-giadd]'); if (ga) { F.pd[ga.dataset.giadd].items.push({ jenis: '', nominal: '', hari: 1, files: [] }); renderForm(); return; }
    const gr = t.closest('[data-gitrm]'); if (gr) { const [k, i] = gr.dataset.gitrm.split(':'); F.pd[k].items.splice(i, 1); renderForm(); return; }
    if (t.id === 'rtadd') { F.meta.rute.push(''); renderForm(); return; }
    const up = t.closest('[data-rtup]'); if (up) { const i = +up.dataset.rtup; if (i > 0) [F.meta.rute[i - 1], F.meta.rute[i]] = [F.meta.rute[i], F.meta.rute[i - 1]]; renderForm(); return; }
    const dn = t.closest('[data-rtdn]'); if (dn) { const i = +dn.dataset.rtdn; if (i < F.meta.rute.length - 1) [F.meta.rute[i + 1], F.meta.rute[i]] = [F.meta.rute[i], F.meta.rute[i + 1]]; renderForm(); return; }
    const rr = t.closest('[data-rtrm]'); if (rr) { F.meta.rute.splice(+rr.dataset.rtrm, 1); renderForm(); return; }
    if (t.id === 'pesadd') { F.meta.peserta.push(''); renderForm(); return; }
    const md = t.closest('[data-moda]'); if (md) { F.meta.moda = md.dataset.moda; renderForm(); return; }
    if (t.id === 'rinadd') { (F.meta.rincian ||= []).push({ uraian: '', qty: 1, harga: '' }); renderForm(); return; }
    const rnr = t.closest('[data-rinrm]'); if (rnr) { F.meta.rincian.splice(+rnr.dataset.rinrm, 1); renderForm(); return; }
    const pr = t.closest('[data-pesrm]'); if (pr) { F.meta.peserta.splice(+pr.dataset.pesrm, 1); renderForm(); return; }
    const kb = t.closest('[data-kend]'); if (kb) { F.meta.kendaraan = kb.dataset.kend; renderForm(); return; }
    const bb = t.closest('[data-bbm]'); if (bb) { const [i, v] = bb.dataset.bbm.split(':'); F.notes[i].detail = { ...F.notes[i].detail, bbm: v }; renderForm(); return; }
    const nad = t.closest('[data-notaadd]'); if (nad) { F.notes.push({ nominal: '', date: today(), detail: { type_id: +nad.dataset.notaadd }, files: [] }); renderForm(); return; }
    if (t.id === 'notaadd') { F.notes.push({ nominal: '', date: today(), detail: {}, files: [] }); renderForm(); return; }
    const nr = t.closest('[data-notarm]'); if (nr) { F.notes.splice(+nr.dataset.notarm, 1); renderForm(); return; }
    if (t.id === 'bsave') saveForm(false); if (t.id === 'bnext') saveForm(true);
  });
}
function addFiles(w, fs) {
  const o = fs.map(f => ({ id: f.id, mime: f.mime, orig: f.orig }));
  if (['odo_awal', 'odo_akhir', 'doc'].includes(w)) F.files[w].push(...o); else if (w === 'maps') F.files.maps = o.slice(0, 1);
  else if (w.startsWith('nota:')) F.notes[+w.split(':')[1]].files.push(...o);
  else if (w.startsWith('gi:')) { const [, k, i] = w.split(':'); F.pd[k].items[i].files.push(...o); } else if (w === 'pdent') F.pd.ent.files.push(...o);
}
/* ---------- validasi di form: isian kurang ditandai merah ---------- */
const KF_LBL = { 'Plat kendaraan': 'meta.plat', 'Keterangan': 'meta.keterangan', 'Nama penerima': 'meta.penerima', 'Tempat acara': 'meta.tempat', 'Nama yang dirayakan': 'meta.penerima', 'Kota asal': 'meta.asal', 'Kota tujuan': 'meta.tujuan_kota', 'Tanggal berangkat': 'meta.tgl_berangkat', 'Maskapai / operator': 'meta.operator', 'Nama penumpang': 'meta.penumpang', 'Tujuan perjalanan': 'meta.tujuan|meta.keterangan', 'Judul pengeluaran': 'meta.judul', 'Kebutuhan / alasan': 'meta.keterangan', 'Tujuan meeting': 'meta.tujuan', 'Tempat meeting': 'meta.tempat', 'Jam mulai': 'meta.jam_mulai', 'Jam selesai': 'meta.jam_selesai', 'Nama kost': 'meta.nama_kost', 'Periode sewa': 'meta.periode', 'Nama pemilik': 'meta.pemilik', 'Nama hotel': 'meta.nama_hotel', 'Check-in': 'meta.checkin', 'Check-out': 'meta.checkout', 'Tujuan menginap': 'meta.tujuan' };
function kfSel(msg) {
  let m = msg, si = null;
  if (F.secs) F.secs.forEach((sc, i) => { if (si == null && m.startsWith(sc.type.name + ': ')) { si = i; m = m.slice(sc.type.name.length + 2); } });
  const nm = /^Nota (\d+)[: ]/.exec(m);
  if (nm) { const i = nm[1] - 1, r = m.slice(nm[0].length);
    const s = /nominal/i.test(r) ? `[data-n="${i}:nominal"]` : /tanggal/i.test(r) ? `[data-n="${i}:date"]` : /foto nota/i.test(r) ? `[data-addfile="nota:${i}"]` : /BBM/.test(r) ? `[data-bbm^="${i}:"]` : /gerbang/i.test(r) ? `#ga${i}` : `[data-nota="${i}"] .kft`;
    return { si: null, sel: s, msg: r.replace(/^\s+/, '') }; }
  let s = null; const w = /^(.+?) wajib diisi\.$/.exec(m);
  if (/Brand/.test(m)) s = '#fbrand'; else if (/^Lokasi/.test(m)) s = '#floc';
  else if (/kendaraan \(/.test(m)) s = '[data-kend]'; else if (/KM akhir/.test(m)) s = '[data-f="meta.km_akhir"]';
  else if (/odometer awal/i.test(m)) s = '[data-addfile="odo_awal"]'; else if (/odometer akhir/i.test(m)) s = '[data-addfile="odo_akhir"]';
  else if (w && KF_LBL[w[1]]) s = KF_LBL[w[1]].split('|').map(x => `[data-f="${x}"]`).join(',');
  else if (/eserta/.test(m)) s = '#pesadd'; else if (/Total rincian/.test(m)) s = '#rinsum'; else if (/incian|qty/.test(m)) s = '#rinadd';
  else if (/^Rute/.test(m)) s = '#rtadd'; else if (/Google Maps/.test(m)) s = '[data-addfile="maps"],.kf [data-f="meta.km_total"]';
  else if (/jarak \(km\)/.test(m)) s = '[data-f="meta.km_total"]'; else if (/moda/.test(m)) s = '[data-moda]';
  else if (/Tanggal (pulang|berangkat)/.test(m)) s = '[data-f="meta.tgl_pulang"]'; else if (/minimal satu nota/i.test(m)) s = '[data-notaadd],#notaadd';
  else if (/[Rr]ekening/.test(m)) s = '#rekbox .kft';
  return { si, sel: s, msg: m };
}
function kfMark(errs) { F.errs = errs.map((e, i) => ({ k: 'e' + i, raw: e, ...kfSel(e) })); kfApply(true); toast('Lengkapi isian yang merah dulu.', true); }
function kfApply(scroll) {
  const ban = $('#kfban'); if (!ban || !F) return; const errs = F.errs || [];
  $$('.kerr').forEach(e => { e.classList.remove('kerr'); delete e.dataset.ek; }); $$('.kerrm').forEach(e => e.remove());
  const left = [], grp = {}; let first = null;
  errs.forEach(x => {
    const sc = x.si != null ? $(`[data-sec="${x.si}"]`) : $('.kf'); const el = x.sel && sc ? sc.querySelector(x.sel) : null;
    if (!el) { left.push(x.raw); return; }
    const box = el.closest('.fld') || el.closest('.card'); if (!box) { left.push(x.raw); return; }
    box.classList.add('kerr'); box.dataset.ek = (box.dataset.ek ? box.dataset.ek + '|' : '') + x.k;
    if (box.classList.contains('fld')) box.insertAdjacentHTML('beforeend', `<div class="kerrm">${esc(x.msg)}</div>`);
    const card = box.closest('.card'); const g = (x.si != null ? F.secs[x.si].type.name + ' · ' : '') + (card?.querySelector('.kft span')?.textContent || 'Lainnya');
    if (!grp[g]) grp[g] = { n: 0, el: box }; grp[g].n++; first ||= box;
  });
  if (!errs.length) { ban.innerHTML = ''; return; }
  ban.innerHTML = `<div class="kfban"><b>Masih ada ${errs.length} isian yang belum lengkap</b>Isian yang ditandai merah wajib diisi dulu.${Object.keys(grp).length ? `<div class="jp">${Object.entries(grp).map(([g, o], i) => `<span data-kfj="${i}">${esc(g)} · ${o.n}</span>`).join('')}</div>` : ''}${left.length ? `<div class="lf">${left.map(x => '• ' + esc(x)).join('<br>')}</div>` : ''}</div>`;
  const gl = Object.values(grp); $$('[data-kfj]').forEach(e => e.onclick = () => gl[+e.dataset.kfj].el.scrollIntoView({ behavior: 'smooth', block: 'center' }));
  if (scroll) (first || ban).scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function kfClear(e) {
  if (!F || !F.errs || !F.errs.length || !$('.kf')) return;
  if (e.type === 'click' && !e.target.closest('[data-kend],[data-bbm],[data-moda],[data-addfile],#pesadd,#rinadd,#rtadd,[data-notaadd],#notaadd,[data-acc]')) return;
  const box = e.target.closest('.kerr'); if (!box) return;
  const ks = (box.dataset.ek || '').split('|'); F.errs = F.errs.filter(x => !ks.includes(x.k)); kfApply();
}
['input', 'change', 'click'].forEach(ev => document.addEventListener(ev, kfClear, true));

async function saveForm(next) {
  await guard(async () => {
    const j = await api('claim_save', payload()); F.id = j.id;
    /* 2026-11-24: setelah draft tersimpan, entri riwayat form diganti #/edit/id supaya tombol "← Kembali" (dan back browser/HP) dari Ringkasan membuka draft yang sama, bukan form kosong */
    if (!/^#\/edit\//.test(location.hash)) history.replaceState(null, '', location.pathname + location.search + '#/edit/' + j.id);
    if (!next) { toast('Draft tersimpan.'); return; }
    /* 2026-11-24: cek kelengkapan dulu; kalau ada yang kurang tetap di form, isian ditandai merah (mockup dsm-klaim-validasi-opsi.html) */
    const ck = await api('claim_check', { id: j.id });
    if (ck.errors && ck.errors.length) { kfMark(ck.errors); return; }
    F.errs = []; location.hash = '#/ringkas/' + j.id;
  });
}

/* kalimat pembuka + saran per pemicu BA (BA v2) */
const BA_SUG = {
  OVER_BUDGET: ['kebutuhan operasional mendadak.', 'ada kegiatan tambahan yang tidak terjadwal.', 'harga di lapangan lebih tinggi dari perkiraan.'],
  NOTA_HARI: ['kegiatan memang berlangsung di hari tersebut.', 'toko / SPBU hanya sempat dikunjungi di hari tersebut.', 'kebutuhan mendesak di luar jam kerja.'],
  DIGIT_BBM: ['SPBU hanya melayani nominal tertentu.', 'pengisian dibatasi mesin pompa.', 'tangki penuh sebelum mencapai nominal aturan.'],
  TANPA_DOK: ['kamera / ponsel bermasalah saat acara.', 'acara berlangsung mendadak sehingga tidak sempat memotret.', 'tamu keberatan difoto.'],
  KEHILANGAN_NOTA: ['nota tertinggal di tempat pembelian.', 'nota rusak / luntur.', 'nota tidak diberikan oleh penjual.'],
  TEMUAN_LAIN: ['terjadi kesalahan input.', 'kondisi di lapangan berbeda dari rencana.', 'nota dibuat di hari berbeda dari pembelian.'],
};
const TEMUAN_KAT = { TGL: 'Tanggal nota tidak sesuai', FOTO: 'Foto / dokumen tidak jelas', RUTE: 'Rute / jarak tidak wajar', DATA: 'Data tidak sesuai', LAIN: 'Lainnya' };
const BA_TITLE = { OVER_BUDGET: 'Over budget', NOTA_HARI: 'Nota di luar hari normal', DIGIT_BBM: 'Digit BBM Mobil', TANPA_DOK: 'Tanpa foto dokumentasi', KEHILANGAN_NOTA: 'Kehilangan nota', TEMUAN_LAIN: 'Temuan / kendala lain', TEMUAN_ADMIN: 'Temuan Admin' };
function baFrame(code, ctx) {
  const L = (ctx.lost || []).join(', ');
  switch (code) {
    case 'OVER_BUDGET': return `Klaim melebihi sisa budget ${rp(ctx.over)} karena `;
    case 'NOTA_HARI': return `Pembelian dilakukan pada hari ${[...new Set((ctx.info.hari || []).map(h => h.hari))].join(', ')} karena `;
    case 'DIGIT_BBM': return 'Pengisian BBM sesuai kondisi SPBU sehingga nominal tidak sesuai aturan digit karena ';
    case 'TANPA_DOK': return 'Foto dokumentasi acara tidak tersedia karena ';
    case 'KEHILANGAN_NOTA': return `Nota ${L || '…'} hilang / rusak karena `;
    case 'TEMUAN_LAIN': return ctx.kategori === 'TGL' ? `Tanggal pada nota ${(ctx.related || []).join(', ') || '…'} berbeda dengan tanggal input, dikoreksi karena ` : (TEMUAN_KAT[ctx.kategori || 'LAIN'] + ': ');
  }
  return '';
}
function baTables(code, info) {
  if (code === 'DIGIT_BBM' && (info.digit || []).length) return `<table class="batb"><tr><th>Tanggal</th><th>Jumlah</th><th>Seharusnya</th><th>Klaim</th></tr>${info.digit.map(r => `<tr><td>${esc(r.date || '')}</td><td>${rp(r.jumlah)}</td><td>${rp(r.seharusnya)}</td><td>${rp(r.klaim)}</td></tr>`).join('')}</table>`;
  if (code === 'NOTA_HARI' && (info.hari || []).length) return `<table class="batb"><tr><th>Nota</th><th>Tanggal</th><th>Hari</th><th>Nominal</th></tr>${info.hari.map(r => `<tr><td>${r.line}</td><td>${esc(r.date || '')}</td><td>${esc(r.hari)}</td><td>${rp(r.nominal)}</td></tr>`).join('')}</table>`;
  return '';
}

/* ---------- P14 ringkasan sebelum kirim ---------- */
R.ringkas = async (id) => {
  const [d, k] = await Promise.all([api('claim_get', { id }), api('claim_check', { id })]); const c = d.claim; const rev = c.status === 'REV';
  const b = k.budget, info = k.info || {}; const hasBA = !!d.ba; const autoT = (rev && hasBA) ? [] : k.triggers.slice();
  const over = b ? Math.max(0, c.total - Math.max(0, b.available + (rev ? +c.total : 0))) : 0;
  const nn = d.notes.map((n, i) => ({ no: n.line_no || i + 1, nominal: n.nominal, date: n.nota_date }));
  const V = { on: { KEHILANGAN_NOTA: false, TEMUAN_LAIN: false }, lost: [], kategori: 'TGL', related: [], text: {}, frame: {}, files: [] }; // V = status BA sukarela & teks
  const codes = () => [...autoT, ...['KEHILANGAN_NOTA', 'TEMUAN_LAIN'].filter(x => V.on[x] && !autoT.includes(x))];
  const ctx = () => ({ over, info, lost: V.lost, kategori: V.kategori, related: V.related });
  const changed = rev && +c.total !== +(d.versions[d.versions.length - 1] || {}).total;
  const noteChips = (key, sel) => nn.map(n => `<span class="bch ${sel.includes(n.no) ? 'on' : ''}" data-${key}="${n.no}">Nota ${n.no} · ${rp(n.nominal)}</span>`).join('');
  const baCard = code => {
    if (V.text[code] === undefined) { const f = baFrame(code, ctx()); V.frame[code] = f; V.text[code] = f; }
    const extra = code === 'KEHILANGAN_NOTA' ? `<div class="fld"><label>Nota yang hilang</label><div class="bchs" id="lostchips">${noteChips('lost', V.lost)}</div></div><div class="fld"><label>Bukti pengganti (minimal 1 file)</label><div id="bafiles">${thumbs(V.files)}</div><button class="btn g sm" id="baadd">+ Bukti pengganti</button><div class="mut">Mis. mutasi rekening, riwayat e-toll, screenshot aplikasi.</div></div>`
      : code === 'TEMUAN_LAIN' ? `<div class="fld"><label>Kategori temuan</label><div class="bchs" id="katchips">${Object.entries(TEMUAN_KAT).map(([x, t]) => `<span class="bch ${V.kategori === x ? 'on' : ''}" data-kat="${x}">${t}</span>`).join('')}</div></div><div class="fld"><label>Nota terkait (opsional)</label><div class="bchs" id="relchips">${noteChips('rel', V.related)}</div></div>` : '';
    const vol = !autoT.includes(code);
    return `<div class="bacard"><div class="row"><b>${BA_TITLE[code]}</b>${vol ? `<span class="bdel" data-voloff="${code}">Hapus</span>` : ''}</div>${baTables(code, info)}${extra}<div class="fld"><label>Penjelasan (lanjutkan kalimatnya)</label><textarea data-bx="${code}" rows="3">${esc(V.text[code])}</textarea></div><div class="bsug">${(BA_SUG[code] || []).map(t => `<span class="bch sm" data-sug="${code}">${esc(t)}</span>`).join('')}</div></div>`;
  };
  const drawBA = () => {
    const cs = codes(); const vol = !hasBA ? ['KEHILANGAN_NOTA', 'TEMUAN_LAIN'].filter(x => !cs.includes(x)) : [];
    $('#baarea').innerHTML = (cs.length ? `<div class="note red"><b>Klaim ini memakai BA</b><br>Satu BA berisi ${cs.length} penjelasan. Isi tiap penjelasan, ketuk kalimat saran untuk melanjutkan.</div>` : '') + cs.map(baCard).join('') +
      (vol.length ? `<div class="card"><b>Ada hal lain yang perlu dilaporkan?</b><div class="mut">Opsional. Dicantumkan dalam BA yang sama.</div><div class="bchs" style="margin-top:6px">${vol.map(x => `<span class="bch" data-volon="${x}">+ ${BA_TITLE[x]}</span>`).join('')}</div></div>` : '');
    const kb = $('#kirim'); if (kb && !k.errors.length) { const need = cs.length && !hasBA && !S.me.has_sign; kb.disabled = !!need; kb.textContent = need ? 'KIRIM KLAIM (TTD BELUM DIATUR)' : (rev ? 'KIRIM PERBAIKAN' : 'KIRIM KLAIM'); $('#nosign').innerHTML = need ? '<div class="note red"><b>Tanda tangan belum diatur.</b> Klaim ini memakai BA, jadi Anda perlu mengisi tanda tangan dulu. <a href="#/ttd"><b>Atur sekarang</b></a></div>' : ''; }
  };
  shell(hero(rev ? 'Kirim perbaikan' : 'Ringkasan sebelum kirim', rev ? 'P17' : 'P14', { back: true, small: true }) + `<div class="sec">
   <div class="card"><div class="kv"><span>Jenis</span><span>${esc(d.claim.category)} / ${esc(d.type_label)}</span></div><div class="kv"><span>Refnote</span><span>${c.refnote ? esc(c.refnote) : '<span class="mut">Diisi Admin</span>'}</span></div><div class="kv"><span>Jumlah nota</span><span>${d.notes.length}</span></div><div class="kv"><span>Total</span><span class="b">${rp(c.total)}</span></div><div class="kv"><span>Rekening</span><span>${esc(c.acct_bank)} ${esc(c.acct_no)} a.n. ${esc(c.acct_name)}</span></div>${rev ? `<div class="kv"><span>Versi saat ini</span><span>v${c.current_version}${changed ? ' → v' + (+c.current_version + 1) : ''}</span></div>` : ''}</div>
   ${changed ? `<div class="note red">Perubahan nominal akan membuat <b>versi klaim baru</b> dan membutuhkan approval ulang untuk versi terbaru.${d.ba ? ' BA dan tanda tangan versi lama tidak berlaku; tanda tangan diulang.' : ''}</div>` : ''}
   ${over > 0 && !d.claim.outside_sales ? `<div class="card" style="border-color:#D2453F"><div class="note red"><b>Over Budget ${rp(over)}</b></div><div class="kv"><span>Periode budget</span><span>${esc(k.period)}</span></div><div class="kv"><span>Available sebelum klaim</span><span>${rp(c.avail_before ?? b.available)}</span></div><div class="kv"><span>Nominal klaim</span><span>${rp(c.total)}</span></div></div>` : ''}
   <div class="card"><b>Rute persetujuan</b><div style="margin-top:6px">${routeChips(k.route.length ? k.route : d.route, '', false)}</div></div>
   ${k.errors.length ? `<div class="note red"><b>Lengkapi dulu:</b><br>${k.errors.map(x => '• ' + esc(x)).join('<br>')}</div><button class="btn g" data-go="#/edit/${id}">KEMBALI MEMPERBAIKI</button>` : ''}
   <div id="baarea"></div>${hasBA ? `<div class="note blue">Klaim ini sudah mempunyai ${esc(d.ba.number)}.</div>` : ''}<div id="nosign"></div>
   ${k.errors.length ? '' : `<button class="btn" id="kirim" style="margin-top:8px">${rev ? 'KIRIM PERBAIKAN' : 'KIRIM KLAIM'}</button>`}</div>`, { hero: false });
  drawBA();
  const root = $('#baarea');
  root.oninput = e => { const t = e.target; if (t.dataset.bx) V.text[t.dataset.bx] = t.value; };
  root.onclick = e => { const t = e.target.closest('[data-lost],[data-rel],[data-kat],[data-sug],[data-volon],[data-voloff],#baadd'); if (!t) return;
    const tog = (arr, v) => { const i = arr.indexOf(v); i < 0 ? arr.push(v) : arr.splice(i, 1); };
    const refreshFrame = code => { const old = (V.frame || {})[code] || ''; const tail = (V.text[code] || '').startsWith(old) ? V.text[code].slice(old.length) : V.text[code]; const nf = baFrame(code, ctx()); V.frame[code] = nf; V.text[code] = nf + tail; };
    if (t.dataset.lost) { tog(V.lost, +t.dataset.lost); refreshFrame('KEHILANGAN_NOTA'); drawBA(); }
    else if (t.dataset.rel) { tog(V.related, +t.dataset.rel); refreshFrame('TEMUAN_LAIN'); drawBA(); }
    else if (t.dataset.kat) { V.kategori = t.dataset.kat; refreshFrame('TEMUAN_LAIN'); drawBA(); }
    else if (t.dataset.sug) { const c2 = t.dataset.sug; const old = V.text[c2] || ''; const base = (V.frame || {})[c2] || ''; V.text[c2] = (old.length > base.length && !/\s$/.test(old) ? old + ' ' : old) + t.textContent; drawBA(); }
    else if (t.dataset.volon) { V.on[t.dataset.volon] = true; drawBA(); }
    else if (t.dataset.voloff) { V.on[t.dataset.voloff] = false; delete V.text[t.dataset.voloff]; if (V.frame) delete V.frame[t.dataset.voloff]; drawBA(); }
    else if (t.id === 'baadd') pickFiles(fs => { V.files.push(...fs); drawBA(); });
  };
  if ($('#kirim')) $('#kirim').onclick = () => guard(async () => {
    const cs = codes(); const expl = {};
    if (!hasBA) for (const x of cs) { const base = (V.frame || {})[x] || ''; const tx = (V.text[x] || '').trim(); if (tx.length < base.trim().length + 6) throw new Error('Lengkapi penjelasan untuk: ' + BA_TITLE[x] + '.'); expl[x] = tx; }
    const vol = cs.filter(x => !autoT.includes(x));
    if (vol.includes('KEHILANGAN_NOTA') && !V.lost.length) throw new Error('Pilih nota yang hilang.');
    if (vol.includes('KEHILANGAN_NOTA') && !V.files.length) throw new Error('Nota hilang wajib disertai bukti pengganti (minimal 1 file).');
    const payload = { id, ba_explanations: expl, ba_files: V.files.map(f => f.id), ba_types: vol, ba_extra: { lost: V.lost, kategori: V.kategori, related: V.related } };
    $('#kirim').disabled = true;
    try {
      const r = rev ? await api('claim_resubmit', payload) : await api('claim_submit', payload);
      toast(rev ? 'Perbaikan terkirim' + (r.nominal_changed ? ' (versi baru v' + r.version + ')' : '') : 'Klaim ' + r.number + ' terkirim.'); await refreshCounts(); location.hash = '#/claim/' + id;
    } catch (e) { $('#kirim').disabled = false; throw e; }
  });
};

/* ---------- P19 form petty cash ---------- */
let PF = null;
R.pcnew = async () => {
  const b = await api('budget'); const plaf = S.me.petty_ceiling;
  PF = PF || { purpose: '', location: '', p_start: today(), p_end: today(), plan: [{ uraian: '', kategori: 'Transportasi', nominal: '' }] };
  renderPcForm(plaf, b);
};
function renderPcForm(plaf, b) {
  const _y = window.scrollY; setTimeout(() => window.scrollTo(0, _y), 0);
  const sum = PF.plan.reduce((a, r) => a + num(r.nominal), 0);
  const rowHtml = (r, i) => `<div class="card"><div class="row"><b>Rencana ${i + 1}</b>${PF.plan.length > 1 ? `<span data-prm="${i}" style="color:#D2453F;cursor:pointer">Hapus</span>` : ''}</div>${fld('Uraian', `<input data-pr="${i}:uraian" value="${esc(r.uraian)}">`)}<div class="two">${fld('Kategori', `<select data-pr="${i}:kategori">${CATS_LIST.map(c => `<option ${r.kategori === c ? 'selected' : ''}>${c}</option>`).join('')}</select>`)}${fld('Nominal', `<input inputmode="numeric" data-pr="${i}:nominal" value="${fmtN(r.nominal)}">`)}</div>
    ${r.kategori === 'Perdin' ? pcPerdin(r, i) : ''}</div>`;
  shell(hero('Ajukan petty cash', 'P19', { back: true, small: true }) + `<div class="sec">${b.blocked ? `<div class="note red">Pengajuan diblokir sampai petty cash lama dipertanggungjawabkan.</div>` : ''}<div class="card">${fld('Tujuan', `<input data-pf="purpose" value="${esc(PF.purpose)}">`)}${fld('Lokasi', `<input id="pfloc" value="${esc(PF.location || '')}" placeholder="Ketik kota/kabupaten" autocomplete="off">`)}<div class="two">${fld('Periode mulai', `<input type="date" data-pf="p_start" value="${PF.p_start}">`)}${fld('Periode selesai', `<input type="date" data-pf="p_end" value="${PF.p_end}" min="${PF.p_start}">`)}</div>${fld('Keterangan tambahan (opsional)', `<textarea data-pf="remark" placeholder="Tulis jika ada hal yang perlu diketahui pemeriksa">${esc(PF.remark || '')}</textarea>`)}</div>
   <div class="sec" style="padding:0"><h3>Rencana pemakaian</h3>${PF.plan.map(rowHtml).join('')}<button class="btn g" id="pradd">+ Tambah rencana</button></div>
   <div class="card row" style="margin-top:8px"><div><b>Jumlah diminta</b><div class="mut">Plafon Anda ${rp(plaf)}</div></div><b id="pctot" style="font-size:18px;${sum > plaf ? 'color:#D2453F' : ''}">${rp(sum)}</b></div><div id="pcwarn">${sum > plaf ? '<div class="note red">Melebihi plafon petty cash Anda.</div>' : ''}</div>
   <div class="note blue">Rute: ${S.me.roles.includes('RGM') ? 'Admin → HOS → Finance Manager → Finance Transfer (Anda RGM, tahap RGM dilewati)' : 'RGM → Admin → HOS → Finance Manager → Finance Transfer'}</div><button class="btn" id="pcgo" style="margin-top:6px">KIRIM PENGAJUAN</button></div>`, { hero: false });
  const root = $('#app');
  root.oninput = e => { const t = e.target; if (t.dataset.pf) PF[t.dataset.pf] = t.value; else if (t.id === 'pfloc') PF.location = t.value; else if (t.dataset.pr) { const [i, k] = t.dataset.pr.split(':'); let v = t.value; if (k === 'nominal') { v = num(v); t.value = fmtN(v); } PF.plan[i][k] = v; if (k === 'nominal') { const s2 = PF.plan.reduce((a, r) => a + num(r.nominal), 0); $('#pctot').textContent = rp(s2); $('#pctot').style.color = s2 > plaf ? '#D2453F' : ''; $('#pcwarn').innerHTML = s2 > plaf ? '<div class="note red">Melebihi plafon petty cash Anda.</div>' : ''; } } else if (t.dataset.pd) { const [i, k] = t.dataset.pd.split(':'); (PF.plan[i].perdin ??= { rute: [''] })[k] = t.value; } else if (t.dataset.prt) { const [i, j] = t.dataset.prt.split(':'); PF.plan[i].perdin.rute[j] = t.value; } };
  root.onchange = e => { const t = e.target; if ((t.dataset.pr || '').endsWith(':kategori') || t.dataset.pf === 'p_start') renderPcForm(plaf, b); };
  root.onclick = e => { const t = e.target;
    if (t.id === 'pradd') { PF.plan.push({ uraian: '', kategori: 'Transportasi', nominal: '' }); renderPcForm(plaf, b); }
    const rm = t.closest('[data-prm]'); if (rm) { PF.plan.splice(+rm.dataset.prm, 1); renderPcForm(plaf, b); }
    const ra = t.closest('[data-prtadd]'); if (ra) { PF.plan[+ra.dataset.prtadd].perdin.rute.push(''); renderPcForm(plaf, b); }
    const mp = t.closest('[data-addfile]'); if (mp && mp.dataset.addfile.startsWith('pcmaps:')) { const i = +mp.dataset.addfile.split(':')[1]; pickFiles(fs => { PF.plan[i].perdin.maps = [fs[0].id]; PF.plan[i].perdin.mapsF = fs[0]; renderPcForm(plaf, b); }, false); }
    if (t.id === 'pcgo') guard(async () => { const plan = PF.plan.map(r => ({ ...r, nominal: num(r.nominal), perdin: r.kategori === 'Perdin' ? { ...r.perdin, rute: (r.perdin?.rute || []).filter(x => x.trim()) } : undefined })); await api('pc_submit', { ...PF, plan, amount: plan.reduce((a, r) => a + r.nominal, 0) }); PF = null; toast('Petty cash terkirim.'); location.hash = '#/pc'; });
  };
}
const CATS_LIST = ['Transportasi', 'Entertain', 'Perdin', 'Operasional'];
function pcPerdin(r, i) {
  const p = r.perdin ??= { rute: [''], tgl_berangkat: PF.p_start, tgl_pulang: PF.p_end };
  return `<div class="note blue">Perdin mengikuti aturan Perdin reguler: rute, satu screenshot Maps, total jarak.</div><div class="two">${fld('Berangkat', `<input type="date" data-pd="${i}:tgl_berangkat" value="${p.tgl_berangkat || ''}">`)}${fld('Pulang', `<input type="date" data-pd="${i}:tgl_pulang" value="${p.tgl_pulang || ''}">`)}</div>
  <div class="fld"><label>Rute</label>${(p.rute || ['']).map((x, j) => `<div class="ruteitem"><div class="no">${j + 1}</div><input data-prt="${i}:${j}" value="${esc(x)}" placeholder="Tempat tujuan"></div>`).join('')}<button class="btn g sm" data-prtadd="${i}">+ Tujuan</button></div>
  ${fld('Total jarak Maps (km)', `<input inputmode="decimal" data-pd="${i}:km_total" value="${esc(p.km_total || '')}">`)}${fld('Screenshot Google Maps', thumbs(p.mapsF ? [p.mapsF] : [], { rm: 0, add: p.mapsF ? '' : 'pcmaps:' + i }))}`;
}
