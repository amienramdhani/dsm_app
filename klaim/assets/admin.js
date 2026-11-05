/* DSM Klaim - Admin (A07-A10), pengaturan, monitoring budget (H05), realisasi (F05), Super Admin (X01/X02) */
'use strict';
const NOBUD = ['Perdin', 'Operasional']; // kategori tanpa budget bulanan
R.a = async (sub, id) => {
  if (sub === 'hak' && id) return hakEdit(+id);
  if (sub === 'hak') return hakList();
  if (sub === 'massal') return massalScreen();
  if (sub === 'jenis') return jenisScreen();
  if (sub === 'atur') return aturScreen();
  location.hash = '#/menu';
};
async function hakList() {
  const j = await api('people_admin');
  shell(hero('Hak klaim', 'A08', { back: true }) + `<div class="sec"><input id="hq" placeholder="Cari nama"><div id="hl" style="margin-top:8px"></div></div>`, { hero: false });
  const draw = () => { const q = $('#hq').value.toLowerCase(); $('#hl').innerHTML = j.rows.filter(r => r.name.toLowerCase().includes(q)).map(r => `<div class="li" data-go="#/a/hak/${r.id}"><div class="dot" style="background:#1E5FB8">${esc(r.name[0])}</div><div class="t"><b>${esc(r.name)}</b><span>${esc(r.job)} · ${esc(r.region || '-')} · ${esc(r.entity || '-')}</span><div style="margin-top:3px"><span class="pill ${+r.can_submit ? 'p-ok' : 'p-gr'}">${+r.can_submit ? 'Boleh mengajukan' : 'Belum diberi hak'}</span>${+r.can_petty ? ' <span class="pill p-pu">Petty cash</span>' : ''}</div></div>›</div>`).join('') || emptyBox('Tidak ada.'); };
  $('#hq').oninput = draw; draw();
}
async function hakEdit(id) {
  const j = await api('people_admin'); const u = j.rows.find(r => r.id == id); if (!u) throw new Error('Pengguna tidak ditemukan atau di luar region Anda.');
  const ent = Object.fromEntries(u.ent.map(e => [e.category, e]));
  shell(hero(u.name, 'A09 · HAK KLAIM', { back: true, small: true }) + `<div class="sec"><div class="card"><div class="row"><div><b>${esc(u.job)} · ${esc(u.region || '-')}</b><div class="mut">${esc(u.entity || '-')}</div></div>${sw(+u.can_submit, 'cs')}</div><div class="mut" style="margin-top:6px">Boleh mengajukan klaim</div></div>
   <div class="sec" style="padding-top:12px"><h3>Kategori dan budget per bulan</h3><div class="card">${CATS_LIST.map(c => `<div class="row" style="margin-bottom:8px"><div style="flex:1"><b>${CATIC[c]} ${c}</b></div>${sw(ent[c] ? +ent[c].enabled : 0, 'en.' + c)}${NOBUD.includes(c) ? '<span class="mut" style="width:130px;text-align:center">Tanpa budget bulanan</span>' : `<input data-bud="${c}" inputmode="numeric" style="width:130px" value="${fmtN(ent[c] ? ent[c].budget : 0)}">`}</div>`).join('')}</div></div>
   <div class="sec" style="padding-top:12px"><h3>Perdin</h3><div class="card" style="border:1.5px solid #F07A0C"><div class="fld" style="margin:0"><label>Tarif uang makan per hari (Rp)</label><input id="meal" inputmode="numeric" value="${fmtN(u.meal_rate)}" placeholder="Belum diisi"></div><div class="mut" style="margin-top:4px">Diatur per orang. Terkunci untuk pemohon; dipakai: tarif × durasi.</div></div></div>
   <div class="sec" style="padding-top:12px"><h3>Rekening default</h3><div class="card">${fld('Bank', typed('hbank', S.L.bank, u.bank, 'Pilih bank'))}${fld('Nomor rekening', `<input id="hno" value="${esc(u.bank_no || '')}" inputmode="numeric">`)}${fld('Atas nama', `<input id="hnm" value="${esc(u.bank_name || '')}">`)}</div></div>
   <div class="sec" style="padding-top:12px"><h3>Petty cash</h3><div class="card"><div class="row"><b>Boleh mengajukan petty cash</b>${sw(+u.can_petty, 'pcn')}</div>${fld('Plafon (Rp)', `<input id="hplaf" inputmode="numeric" value="${fmtN(u.petty_ceiling)}">`)}</div></div>
   <div class="sec" style="padding:12px 14px"><button class="cta" id="hsv">SIMPAN</button><button class="btn g" id="hcp" style="margin-top:10px;width:100%">⧉ SALIN HAK INI KE ORANG LAIN</button><div class="mut" style="margin-top:6px;text-align:center">Yang disalin adalah pengaturan yang <b>sudah tersimpan</b>. Simpan dulu kalau ada perubahan. Rekening tidak ikut disalin.</div></div>`, { hero: false });
  $('#hcp').onclick = () => copyHak(u, j.rows);
  $('#app').addEventListener('click', e => { const s = e.target.closest('[data-sw]'); if (s) s.classList.toggle('on'); });
  $('#app').addEventListener('input', e => { if (e.target.matches('[data-bud],#meal,#hplaf')) e.target.value = fmtN(num(e.target.value)); });
  $('#hsv').onclick = () => guard(async () => {
    const on = k => $(`[data-sw="${k}"]`).classList.contains('on'); const cats = {}; CATS_LIST.forEach(c => cats[c] = { enabled: on('en.' + c) ? 1 : 0, budget: NOBUD.includes(c) ? 0 : num($(`[data-bud="${c}"]`).value) });
    await api('entitlement_save', { user_id: id, can_submit: on('cs') ? 1 : 0, cats, meal_rate: $('#meal').value === '' ? '' : num($('#meal').value), bank: $('#hbank').value, bank_no: $('#hno').value, bank_name: $('#hnm').value, can_petty: on('pcn') ? 1 : 0, petty_ceiling: num($('#hplaf').value) });
    toast('Hak klaim tersimpan.'); location.hash = '#/a/hak'; });
}
function copyHak(src, rows) {
  const st = { parts: { submit: 1, cats: 1, meal: 1, petty: 0 }, skip: 0, sel: new Set(), q: '', job: '', region: '', entity: '' };
  const cands = rows.filter(r => r.id != src.id && r.id != S.me.id);
  const uniq = k => [...new Set(cands.map(r => (k === 'entity' ? (r.entity || '') : (r[k] || ''))).filter(Boolean))].sort();
  const PT = [['submit', 'Boleh mengajukan klaim', ''], ['cats', 'Kategori dan budget per bulan', 'Transportasi, Entertain, Perdin, Operasional'], ['meal', 'Tarif uang makan Perdin', ''], ['petty', 'Petty cash dan plafon', '']];
  const cb = on => `<span class="cbx${on ? ' on' : ''}">${on ? '✓' : ''}</span>`;
  const vis = () => cands.filter(r => (!st.q || (r.name + ' ' + (r.job || '')).toLowerCase().includes(st.q)) && (!st.job || r.job === st.job) && (!st.region || r.region === st.region) && (!st.entity || (r.entity || '').split(',').map(x => x.trim()).includes(st.entity)));
  sheet(`<div id="cpx"></div>`, (el, close) => {
    const body = el.querySelector('#cpx');
    const step1 = () => {
      const v = vis(); const allOn = v.length && v.every(r => st.sel.has(r.id));
      body.innerHTML = `<h3>Salin dari ${esc(src.name)}</h3><div class="mut"><b>1. APA YANG DISALIN</b></div>
      ${PT.map(p => `<div class="ckr" data-pt="${p[0]}">${cb(st.parts[p[0]])}<div>${p[1]}${p[2] ? `<div class="mut">${p[2]}</div>` : ''}</div></div>`).join('')}
      <div class="note blue">Rekening bank <b>tidak ikut disalin</b>, karena itu data pribadi tiap orang.</div>
      <div class="mut" style="margin-top:12px"><b>2. SALIN KE SIAPA</b></div>
      <input id="cq" placeholder="Cari nama" value="${esc(st.q)}" style="margin-top:6px">
      <div class="two" style="margin-top:6px"><select id="cj"><option value="">Semua jabatan</option>${uniq('job').map(x => `<option ${st.job === x ? 'selected' : ''}>${esc(x)}</option>`).join('')}</select><select id="cr2"><option value="">Semua region</option>${uniq('region').map(x => `<option ${st.region === x ? 'selected' : ''}>${esc(x)}</option>`).join('')}</select></div>
      <select id="ce" style="margin-top:6px"><option value="">Semua badan usaha</option>${[...new Set(cands.flatMap(r => (r.entity || '').split(',').map(x => x.trim()).filter(Boolean)))].sort().map(x => `<option ${st.entity === x ? 'selected' : ''}>${esc(x)}</option>`).join('')}</select>
      <div class="row" style="margin-top:10px"><span class="mut">${v.length} orang cocok · ${st.sel.size} dipilih</span><b id="call" style="color:#1E5FB8;font-size:13px;cursor:pointer">${allOn ? 'Batal pilih semua' : 'Pilih semua ' + v.length}</b></div>
      <div id="clst" style="max-height:34vh;overflow:auto;margin-top:4px">${v.map(r => `<div class="ckr" data-id="${r.id}">${cb(st.sel.has(r.id))}<div style="flex:1"><b>${esc(r.name)}</b><div class="mut">${esc(r.job || '-')} · ${esc(r.region || '-')} · ${esc(r.entity || '-')}</div></div><span class="chip ${r.ent.length ? '' : 'on'}" style="font-size:10px">${r.ent.length ? 'SUDAH ADA HAK' : 'BELUM DIATUR'}</span></div>`).join('') || '<div class="mut" style="padding:10px">Tidak ada yang cocok.</div>'}</div>
      <div class="ckr" id="cskip" style="margin-top:6px">${cb(st.skip)}<div>Lewati yang sudah punya hak<div class="mut">Hanya isi yang belum diatur, yang sudah ada tidak ditimpa.</div></div></div>
      <button class="cta" id="cnext" style="margin-top:10px" ${st.sel.size ? '' : 'disabled'}>LANJUT · ${st.sel.size} ORANG DIPILIH</button>`;
      body.querySelectorAll('[data-pt]').forEach(e => e.onclick = () => { st.parts[e.dataset.pt] = st.parts[e.dataset.pt] ? 0 : 1; step1(); });
      body.querySelectorAll('[data-id]').forEach(e => e.onclick = () => { const id = +e.dataset.id; st.sel.has(id) ? st.sel.delete(id) : st.sel.add(id); const sc = body.querySelector('#clst').scrollTop; step1(); body.querySelector('#clst').scrollTop = sc; });
      body.querySelector('#cskip').onclick = () => { st.skip = st.skip ? 0 : 1; step1(); };
      body.querySelector('#call').onclick = () => { allOn ? v.forEach(r => st.sel.delete(r.id)) : v.forEach(r => st.sel.add(r.id)); step1(); };
      body.querySelector('#cq').oninput = e => { st.q = e.target.value.toLowerCase(); const pos = e.target.selectionStart; step1(); const i = body.querySelector('#cq'); i.focus(); i.setSelectionRange(pos, pos); };
      body.querySelector('#cj').onchange = e => { st.job = e.target.value; step1(); }; body.querySelector('#cr2').onchange = e => { st.region = e.target.value; step1(); }; body.querySelector('#ce').onchange = e => { st.entity = e.target.value; step1(); };
      body.querySelector('#cnext').onclick = () => guard(async () => { if (!Object.values(st.parts).some(Boolean)) throw new Error('Pilih apa yang disalin.'); const r = await api('entitlement_copy', req(1)); step2(r); });
    };
    const req = dry => ({ from_user_id: src.id, to_ids: [...st.sel], parts: st.parts, skip_existing: st.skip ? 1 : 0, dry });
    const step2 = r => {
      const n = r.new + r.over;
      body.innerHTML = `<h3>Salin ke ${n} orang?</h3><div class="card"><div class="mut"><b>Yang disalin dari ${esc(src.name)}</b></div><div class="chips">${PT.filter(p => st.parts[p[0]]).map(p => `<span class="chip">${p[1].split(' per ')[0]}</span>`).join('')}</div></div>
      <div class="note ${r.over ? 'warn' : ''}"><b>${r.new} orang belum punya hak</b> akan diisi.${r.over ? `<br><b>${r.over} orang sudah punya hak</b> akan <b>ditimpa</b> dengan hak ${esc(src.name)}. Hak lama mereka hilang.` : ''}</div>
      ${r.samples.length ? `<div class="card"><div class="mut"><b>Contoh perubahan yang ditimpa</b></div>${r.samples.map(x => `<div style="font-size:12.5px;margin-top:6px"><b>${esc(x.name)}</b><br>${x.changes.map(esc).join('<br>')}</div>`).join('')}</div>` : ''}
      ${r.skipped.length ? `<div class="note">${r.skipped.length} dilewati: ${r.skipped.slice(0, 5).map(x => esc(x.name) + ' (' + esc(x.why) + ')').join(', ')}${r.skipped.length > 5 ? ', …' : ''}</div>` : ''}
      <button class="cta" id="cgo2" ${n ? '' : 'disabled'}>YA, SALIN KE ${n} ORANG</button><button class="btn g" id="cbk" style="margin-top:8px;width:100%">KEMBALI</button>`;
      body.querySelector('#cbk').onclick = step1;
      body.querySelector('#cgo2').onclick = () => guard(async () => { const d = await api('entitlement_copy', req(0)); close(); toast(`Selesai. ${d.done} orang diperbarui.`); });
    };
    step1();
  });
}
/* ---------- Update massal WA & rekening (tempel dari Excel / CSV) ---------- */
function parseSheetText(text) {
  const t = String(text || '').replace(/^﻿/, ''); const lines = t.split(/\r?\n/).filter(l => l.trim() !== ''); if (!lines.length) return [];
  const delim = lines[0].includes('\t') ? '\t' : (lines[0].split(';').length > lines[0].split(',').length ? ';' : ',');
  const split = l => { const o = []; let cur = '', q = false; for (let i = 0; i < l.length; i++) { const c = l[i]; if (q) { if (c === '"' && l[i + 1] === '"') { cur += '"'; i++; } else if (c === '"') q = false; else cur += c; } else if (c === '"') q = true; else if (c === delim) { o.push(cur); cur = ''; } else cur += c; } o.push(cur); return o.map(x => x.trim()); };
  const cells = lines.map(split); const H = cells[0].map(h => h.toUpperCase().replace(/[^A-Z]/g, ''));
  const hasHead = H.includes('NIK'); const idx = {};
  if (hasHead) H.forEach((h, i) => { const k = { NIK: 'nik', WA: 'wa', WHATSAPP: 'wa', NOMORWA: 'wa', BANK: 'bank', NOREKENING: 'bank_no', NOMORREKENING: 'bank_no', NOREK: 'bank_no', REKENING: 'bank_no', ATASNAMA: 'bank_name' }[h]; if (k && idx[k] === undefined) idx[k] = i; });
  else ['nik', 'wa', 'bank', 'bank_no', 'bank_name'].forEach((k, i) => idx[k] = i);
  return (hasHead ? cells.slice(1) : cells).map(c => { const r = {}; for (const k in idx) r[k] = c[idx[k]] ?? ''; return r; }).filter(r => r.nik);
}
async function massalScreen() {
  shell(hero('Update massal WA & rekening', 'A11', { back: true }) + `<div class="sec">
   <div class="card"><b><span class="stp">1</span>Unduh template</b><div class="mut" style="margin:4px 0 0 29px">Berisi semua orang di region Anda beserta data sekarang. Isi atau ubah kolom WA dan rekening saja.</div><a class="btn g" style="display:block;text-align:center;text-decoration:none;margin-top:8px" href="api.php?a=contact_template">⬇ UNDUH TEMPLATE (.xlsx)</a></div>
   <div class="card"><b><span class="stp">2</span>Tempel dari Excel</b><div class="mut" style="margin:4px 0 8px 29px">Salin sel dari Excel (dengan baris judul NIK, WA, BANK, NO REKENING, ATAS NAMA), lalu tempel di sini.</div><textarea id="mpaste" rows="7" placeholder="NIK&#9;WA&#9;BANK&#9;NO REKENING&#9;ATAS NAMA" style="width:100%;font:12px ui-monospace,Menlo,monospace"></textarea><div class="mut" style="margin-top:6px;text-align:center">atau <label style="color:#1E5FB8;font-weight:700;cursor:pointer">upload file .csv<input type="file" id="mfile" accept=".csv,text/csv,.txt" hidden></label></div>
   <div class="note blue" style="margin-top:8px"><b>Aturan:</b> NIK wajib (kunci). Sel yang <b>dikosongkan tidak mengubah apa pun</b>, jadi tidak ada data terhapus karena sel kosong. Nomor rekening dengan angka 0 di depan: format kolom sebagai Teks di Excel.</div><button class="cta" id="mchk" style="margin-top:8px">PERIKSA DATA</button></div><div id="mres"></div></div>`, { hero: false });
  $('#mfile').onchange = e => { const f = e.target.files[0]; if (!f) return; const fr = new FileReader(); fr.onload = () => { $('#mpaste').value = fr.result; }; fr.readAsText(f); };
  const csvEsc = v => '"' + String(v ?? '').replace(/"/g, '""') + '"';
  $('#mchk').onclick = () => guard(async () => {
    const rows = parseSheetText($('#mpaste').value); if (!rows.length) throw new Error('Tidak ada baris data. Pastikan ada kolom NIK.');
    const r = await api('contact_bulk', { rows, dry: 1 }); const c = r.count; const ok = r.rows.filter(x => x.status !== 'ERROR' && x.status !== 'SAMA').length;
    const tg = { BERUBAH: 'on', PERIKSA: 'on', SAMA: '', ERROR: '' }; const stc = { ERROR: '#9A2620', BERUBAH: '#0B5E50', PERIKSA: '#7A5200', SAMA: '#5b7482' };
    $('#mres').innerHTML = `<div class="card" style="margin-top:10px"><b>${r.rows.length} baris diperiksa</b><div class="sum" style="margin-top:8px;grid-template-columns:repeat(3,1fr)"><div><b style="color:#0B5E50">${c.berubah}</b><span class="mut">Berubah</span></div><div><b>${c.sama}</b><span class="mut">Sama</span></div><div><b style="color:#9A2620">${c.error}</b><span class="mut">Error</span></div></div></div>
     <div class="card" style="padding:4px 12px;max-height:46vh;overflow:auto">${r.rows.map(x => `<div style="padding:9px 0;border-bottom:1px solid #eef3f6"><div class="row"><b>${esc(x.name || x.nik)}</b><span class="chip" style="color:${stc[x.status]}">${x.status}</span></div>${x.changes.map(h => `<div class="mut">${esc(h.f)} <s style="color:#9A2620">${esc(h.old)}</s> → <b style="color:#0B5E50">${esc(h.new)}</b></div>`).join('')}${x.error ? `<div class="mut" style="color:#9A2620">${esc(x.error)}</div>` : ''}${x.note ? `<div class="mut">${esc(x.note)}</div>` : ''}${x.status === 'SAMA' ? '<div class="mut">Tidak ada perubahan</div>' : ''}</div>`).join('')}</div>
     ${c.error ? `<div class="note warn"><b>${c.error} baris error tidak akan disimpan.</b> Sisanya tetap bisa disimpan.</div><button class="btn g" id="merr" style="width:100%;margin-top:8px">⬇ UNDUH DAFTAR ERROR</button>` : ''}
     <button class="cta" id="msave" style="margin-top:10px" ${ok ? '' : 'disabled'}>SIMPAN ${ok} PERUBAHAN</button>`;
    if ($('#merr')) $('#merr').onclick = () => { const body = ['NIK,NAMA,ERROR', ...r.rows.filter(x => x.status === 'ERROR').map(x => [x.nik, x.name, x.error].map(csvEsc).join(','))].join('\r\n'); const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + body], { type: 'text/csv' })); a.download = 'error_update_massal.csv'; a.click(); };
    $('#msave').onclick = () => guard(async () => { const d = await api('contact_bulk', { rows, dry: 0 }); $('#mres').innerHTML = `<div class="note ok" style="margin-top:10px"><b>✓ Tersimpan.</b> ${d.count.berubah} orang diperbarui (${d.count.wa} nomor WA, ${d.count.rek} rekening). ${d.count.error} baris error dilewati. Tercatat di riwayat.</div><button class="cta" id="mdone" style="margin-top:8px">SELESAI</button>`; $('#mdone').onclick = () => { location.hash = '#/menu'; }; $('#mpaste').value = ''; });
  });
}
async function jenisScreen() {
  const j = await api('types_all');
  shell(hero('Jenis klaim', 'A10', { back: true }) + `<div class="sec">${['Transportasi', 'Entertain', 'Perdin', 'Operasional'].map(c => `<div class="card"><div class="row"><b>${CATIC[c]} ${c}</b>${c !== 'Perdin' ? `<span class="chip" data-addt="${c}">+ Jenis</span>` : ''}</div>${j.rows.filter(t => t.category === c).map(t => `<div class="row" style="margin-top:8px"><span style="${+t.active ? '' : 'text-decoration:line-through;color:#5B7285'}">${esc(t.name)} ${+t.locked ? '<span class="pill p-gr">terkunci</span>' : ''}</span>${+t.locked ? '' : `<span class="chip" data-tt="${t.id}:${+t.active ? 0 : 1}">${+t.active ? 'Nonaktifkan' : 'Aktifkan'}</span>`}</div>`).join('')}</div>`).join('')}<div class="note">Tidak ada jenis “Lainnya”. Jenis baru ditambah di sini.</div></div>`, { hero: false });
  $$('[data-tt]').forEach(e => e.onclick = () => guard(async () => { const [id, a] = e.dataset.tt.split(':'); await api('type_save', { id, active: +a }); S.L = await api('lookups'); jenisScreen(); }));
  $$('[data-addt]').forEach(e => e.onclick = () => sheet(`<h3>Jenis baru · ${e.dataset.addt}</h3>${fld('Nama jenis', '<input id="tn">')}<button class="btn" id="tok">TAMBAH</button>`, (el, close) => $('#tok', el).onclick = () => guard(async () => { await api('type_save', { category: e.dataset.addt, name: $('#tn', el).value }); close(); S.L = await api('lookups'); jenisScreen(); })));
}
async function aturScreen() {
  const j = await api('rules_get'); const r = j.rule; const canEdit = S.me.roles.includes('ADMIN') || S.me.roles.includes('SA');
  const f = (id, l, v, u, hint) => fld(l, `<input id="${id}" inputmode="numeric" value="${v ?? ''}" placeholder="Belum diisi" ${canEdit ? '' : 'disabled'}>`, hint + (u ? ` Satuan: ${u}.` : ''));
  let DY = {}; try { DY = typeof r.days === 'string' ? JSON.parse(r.days) : (r.days || {}); } catch (e) { DY = {}; }
  const HR = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
  const dayOf = c => (DY[c] || (c === 'Perdin' ? [1, 2, 3, 4, 5, 6, 7] : [1, 2, 3, 4, 5, 6])).map(Number);
  const daysCard = CATS_LIST.map(c => `<div class="fld"><label>${c}</label><div class="bchs" data-dc="${c}">${HR.map((h, i) => `<span class="bch ${dayOf(c).includes(i + 1) ? 'on' : ''}" ${canEdit ? `data-dd="${i + 1}"` : ''}>${h}</span>`).join('')}</div></div>`).join('');
  shell(hero('Pengaturan klaim', 'ATURAN BA', { back: true }) + `<div class="sec"><div class="note blue">Perubahan membuat versi aturan baru; klaim lama tidak berubah.</div>
   <div class="card"><h3 style="font-size:13px;margin-bottom:8px">Aturan digit akhir BBM Mobil</h3>${f('r5', 'Digit akhir nominal (kelipatan 10.000)', r.bbm_round, 'Rp', 'Contoh 2000: 62.000 sesuai, 64.000 memicu BA. Contoh 5000: 105.000 sesuai, 100.000 memicu BA. Kosong atau 0 = tanpa aturan. Nominal yang diklaim tetap sesuai nota.')}</div>
   <div class="card"><h3 style="font-size:13px;margin-bottom:8px">Hari nota normal (di luar ini wajib BA)</h3><div class="mut" style="margin-bottom:8px">Nota bertanggal di hari yang tidak dicentang tetap bisa diajukan, tetapi otomatis memakai BA.</div>${daysCard}</div>
   <div class="card"><h3 style="font-size:13px;margin-bottom:8px">Jarak minimal uang makan Perdin</h3>${f('r9', 'Jarak minimal', r.min_km_makan ?? 80, 'km', 'Di bawah jarak ini uang makan tidak bisa diklaim. Kosong = 80 km. 0 = tanpa batas.')}</div>
   <div class="card"><h3 style="font-size:13px;margin-bottom:8px">Petty cash</h3>${f('r6', 'Masa tenggang pertanggungjawaban', r.pc_grace_days, 'hari', 'Setelah akhir periode.')}</div>
   <div class="card"><h3 style="font-size:13px;margin-bottom:8px">Perdin</h3><div class="mut">Tarif uang makan per hari tidak diatur di sini. Isi di Hak klaim (A09) untuk tiap pemohon.</div>${S.me.roles.includes('ADMIN') ? '<button class="btn g sm" data-go="#/a/hak" style="margin-top:8px">BUKA HAK KLAIM</button>' : ''}</div>
   ${canEdit ? `<div class="fld"><label>Berlaku mulai</label><input type="date" id="reff" value="${today()}"></div><button class="cta" id="rsv">SIMPAN SEBAGAI VERSI BARU</button>` : ''}
   <div class="card" style="margin-top:12px"><h3 style="font-size:13px;margin-bottom:8px">Penanda “menunggu lama”</h3>${fld('Nilai N (hari)', `<input id="agn" inputmode="numeric" value="${j.aging_days ?? ''}" placeholder="Belum diisi" ${j.is_sa ? '' : 'disabled'}>`, 'Hanya Super Admin yang dapat mengubah. Tidak ada auto-approve; hanya penanda.')}${j.is_sa ? '<button class="btn b2" id="asv">SIMPAN N</button>' : ''}</div>
   <div class="card"><h3 style="font-size:13px;margin-bottom:8px">Riwayat versi aturan</h3>${j.history.map(h => `<div class="kv"><span>${tgl(h.effective_from)} · #${h.id}</span><span class="mut">digit ${h.bbm_round ?? '-'} · makan ≥ ${h.min_km_makan ?? 80} km</span></div>`).join('')}</div></div>`, { hero: false });
  $$('[data-dd]').forEach(e => e.onclick = () => e.classList.toggle('on'));
  $('#rsv')?.addEventListener('click', () => guard(async () => { const days = {}; $$('[data-dc]').forEach(g => days[g.dataset.dc] = [...g.querySelectorAll('.bch.on')].map(x => +x.dataset.dd)); await api('rules_save', { effective_from: $('#reff').value, bbm_round: $('#r5').value, pc_grace_days: $('#r6').value, min_km_makan: $('#r9').value, days }); toast('Versi aturan baru tersimpan.'); aturScreen(); }));
  $('#asv')?.addEventListener('click', () => guard(async () => { await api('aging_save', { aging_days: $('#agn').value }); S.aging = $('#agn').value === '' ? null : +$('#agn').value; toast('Tersimpan.'); }));
}

/* ---------- monitoring budget (H05) ---------- */
let MF = { period: new Date().toISOString().slice(0, 7) };
R.monitor = async () => {
  const j = await api('monitor', MF); const s = j.sum;
  shell(hero('Monitoring Budget', 'READ-ONLY') + `<div class="sec"><div class="sum"><div><b>${rp(s.budget)}</b><span class="mut">Budget</span></div><div><b>${rp(s.realized)}</b><span class="mut">Terpakai</span></div><div><b>${rp(s.reserved)}</b><span class="mut">Diproses</span></div></div><div class="sum" style="margin-top:6px;grid-template-columns:1fr 1fr"><div><b style="${s.available < 0 ? 'color:#D2453F' : ''}">${rp(s.available)}</b><span class="mut">Available</span></div><div><b style="color:#D2453F">${rp(s.over)}</b><span class="mut">Over budget</span></div></div>
   <button class="fbtn" id="mfb" style="margin-top:10px">FILTER <em>${['region', 'claimant_id', 'category'].filter(k => MF[k]).length}</em> <em>${j.rows.length} orang</em></button></div>
   <div class="sec">${j.rows.map(r => { const used = r.budget ? (r.realized + r.reserved) / r.budget : 0; return `<div class="card" data-go="#/person/${r.id}" style="cursor:pointer"><div class="row"><div><b>${esc(r.name)}</b><div class="mut">${esc(r.job)} · ${esc(r.region || '-')}</div></div><div class="r"><b style="${r.available < 0 ? 'color:#D2453F' : ''}">${rp(r.available)}</b><div class="mut">${r.over ? 'Over ' + rp(r.over) : 'Available'}</div></div></div><div class="tr" style="margin:8px 0"><i style="width:${Math.min(100, used * 100)}%;background:${used > 1 ? '#D2453F' : used > .8 ? '#F07A0C' : '#12866A'}"></i></div><div class="mut">${r.count} klaim${r.ba ? ` · ${r.ba} BA` : ''}${r.lama ? ` · <b style="color:#F07A0C">${r.lama} menunggu lama</b>` : ''}${r.pc_out ? ` · PC belum dipertanggungjawabkan ${rp(r.pc_out)}` : ''}</div></div>`; }).join('') || emptyBox('Tidak ada data dalam cakupan Anda.')}</div>`, { hero: false });
  $('#mfb').onclick = () => sheet(`<h3>Filter</h3><div class="fld"><label>Periode</label><input type="month" id="mp" value="${MF.period}"></div><div class="fld"><label>Region</label><select id="mr"><option value="">Semua</option>${j.regions.map(r => `<option ${MF.region === r ? 'selected' : ''}>${esc(r)}</option>`).join('')}</select></div><div class="fld"><label>Pemohon</label>${pemohonSel('mc', (S.L.people.find(x => x.id == MF.claimant_id) || {}).name)}</div><div class="fld"><label>Kategori</label><select id="mk"><option value="">Semua</option>${CATS_LIST.map(c => `<option ${MF.category === c ? 'selected' : ''}>${c}</option>`).join('')}</select></div><div class="two"><button class="btn g" id="mrs">RESET</button><button class="btn" id="mok">LIHAT HASIL</button></div>`, (el, close) => {
    pemohonUp(el, 'mc'); $('#mrs', el).onclick = () => { MF = { period: new Date().toISOString().slice(0, 7) }; close(); R.monitor(); };
    $('#mok', el).onclick = () => { const p = S.L.people.find(x => x.name === $('#mc', el).value); MF = { period: $('#mp', el).value || MF.period, region: $('#mr', el).value, claimant_id: p ? p.id : '', category: $('#mk', el).value }; close(); R.monitor(); }; });
};
const CATG = { Transportasi: ['#F0643C', '#D2453F'], Entertain: ['#9A5BE8', '#6D3FD8'], Perdin: ['#3C8DE0', '#1E5FB8'], Operasional: ['#F0A400', '#C25A12'] };
/** Kartu budget per kategori (ringkas): batang terpakai/diproses/over, status hampir habis dan over budget. */
function budgetCats(p) {
  const ents = Object.entries(p.cats), tb = ents.reduce((a, [, b]) => a + b.budget, 0), tu = ents.reduce((a, [, b]) => a + b.realized + b.reserved, 0);
  const cards = ents.map(([c, b]) => {
    const used = b.realized + b.reserved, over = b.none ? 0 : Math.max(0, used - b.budget), den = Math.max(b.budget, used) || 1, pct = b.budget ? Math.round(used / b.budget * 100) : 0;
    const pAmt = Math.max(0, b.reserved - over), tAmt = used - over - pAmt, g = CATG[c] || ['#3C8DE0', '#1E5FB8'];
    const w = x => (x / den * 100).toFixed(1) + '%', warn = !over && b.budget && pct >= 80;
    const tag = over ? '<span class="btag t-over">OVER BUDGET</span>' : warn ? `<span class="btag t-warn">HAMPIR HABIS ${pct}%</span>` : '';
    return `<div class="bcat ${over ? 'over' : warn ? 'warn' : ''}"><div class="top"><div class="ic" style="background:linear-gradient(135deg,${g[0]},${g[1]})">${CATIC[c] || ''}</div><b>${esc(c)} ${tag}<small>${b.count} klaim · ${b.ba} BA</small></b><div class="bd">${b.none ? 'Tanpa budget' : rp(b.budget)}</div></div>
<div class="bbar"><i style="width:${w(tAmt)};background:#12A67F"></i><i style="width:${w(pAmt)};background:#F0A400"></i>${over ? `<i class="ov" style="width:${w(over)}"></i>` : ''}</div>
<div class="bmini">${b.none ? '' : `<span><i style="--c:${b.available < 0 ? '#D2453F' : '#1E5FB8'}"></i>Tersedia <b class="${b.available < 0 ? 'neg' : ''}">${b.available < 0 ? '- ' : ''}${rp(Math.abs(b.available))}</b></span>`}<span><i style="--c:#12A67F"></i>Terpakai <b>${rp(b.realized)}</b></span><span><i style="--c:#F0A400"></i>Diproses <b>${rp(b.reserved)}</b></span></div>
${over ? `<div class="ovnote">⚠ Melebihi budget <b>${rp(over)}</b>. Klaim yang melewati budget wajib BA (Berita Acara).</div>` : ''}</div>`;
  }).join('');
  return `<div class="bhero"><small>${esc((p.region || '-').toUpperCase())} · ${MF.period}</small><div class="bsum"><div><b>${rp(tb)}</b><span>Total budget</span></div><div><b>${rp(tu)}</b><span>Terpakai + diproses</span></div><div><b class="${tb - tu < 0 ? 'neg' : ''}">${rp(tb - tu)}</b><span>Masih tersedia</span></div></div></div>
<div class="sec"><h3>Budget per kategori</h3>${cards}<div class="bpc"><div class="ic">💵</div><b>Petty cash belum dipertanggungjawabkan</b><span>${rp(p.pc_out)}</span></div></div>`;
}
R.person = async (id) => {
  const j = await api('claimant_claims', { user_id: id, period: MF.period }); const p = j.person;
  shell(hero(p.name, `${esc(p.job)} · ${esc(p.region || '-')} · ${MF.period}`, { back: true, small: true }) + `${budgetCats(p)}<div class="sec"><h3>Rincian klaim yang diajukan (${j.claims.length})</h3>${j.claims.map(claimLi).join('') || emptyBox('Belum ada klaim.')}</div>`, { hero: false });
};

/* ---------- realisasi finance (F05) ---------- */
R.realisasi = async () => {
  const per = sessionStorage.getItem('rper') || new Date().toISOString().slice(0, 7); const rmode = sessionStorage.getItem('rmode') || 'bulan'; const d1 = sessionStorage.getItem('rd1') || per + '-01', d2 = sessionStorage.getItem('rd2') || today();
  const rent = sessionStorage.getItem('rent') || '';
  const j = await api('realisasi', { period: per, ...(rmode === 'rentang' ? { from: d1, to: d2 } : {}), ...(rent ? { entity: rent } : {}) });
  if (j.fm) return realisasiFM(j, { per, rmode, d1, d2, rent });
  const rows = o => Object.entries(o || {}).map(([k, v]) => `<div class="kv"><span>${esc(k)}</span><span>${rp(v)}</span></div>`).join('') || '<div class="mut">Belum ada.</div>';
  shell(hero('Realisasi', 'F05 · READ-ONLY') + `<div class="sec"><div class="fld"><label>Lihat berdasarkan</label><div class="tgl2"><div data-rm="bulan" class="${rmode === 'bulan' ? 'on' : ''}">Periode (bulan)</div><div data-rm="rentang" class="${rmode === 'rentang' ? 'on' : ''}">Rentang tanggal</div></div></div>${rmode === 'bulan' ? `<div class="fld"><label>Periode</label><input type="month" id="rp" value="${per}"></div>` : `<div class="two"><div class="fld"><label>Dari</label><input type="date" id="rd1" value="${d1}"></div><div class="fld"><label>Sampai</label><input type="date" id="rd2" value="${d2}"></div></div>`}${j.empty ? emptyBox('Anda belum ditugaskan ke badan usaha.') : `<div class="sum"><div><b>${rp(j.sum.klaim)}</b><span class="mut">Transfer klaim</span></div><div><b>${rp(j.sum.pc_cair)}</b><span class="mut">PC cair</span></div><div><b>${rp(j.sum.setor)}</b><span class="mut">Setoran sisa</span></div></div><div class="mut" style="margin-top:6px">${j.sum.jumlah} klaim tertransfer${rmode === 'rentang' ? ' · ' + tgl(d1) + ' – ' + tgl(d2) : ''} · Badan usaha ${esc(j.entities.join(', '))}</div>
   <div class="sec" style="padding:12px 0 0"><h3>Per kategori</h3><div class="card">${rows(j.byCat)}</div></div><div class="sec" style="padding:12px 0 0"><h3>Per pemohon</h3><div class="card">${rows(j.byPerson)}</div></div>
   <div class="sec" style="padding:12px 0 0"><h3>Petty cash</h3><div class="card">${hl('Dana cair', rp(j.pc.cair))}${hl('Terealisasi', rp(j.pc.real))}${hl('Sisa disetor', rp(j.pc.setor))}${hl('Belum dipertanggungjawabkan', rp(j.pc.belum))}</div></div>
   <div class="sec" style="padding:12px 0 0"><h3>Transaksi</h3>${j.trx.map(t => `<div class="card"><div class="row"><b>${esc(t.ref)} · ${esc(t.who)}</b><b>${rp(t.amount)}</b></div><div class="mut">${esc(t.kind)} · ${esc(t.transfer_no)} · ${wktS(t.at)}</div></div>`).join('') || emptyBox('Belum ada transaksi.')}</div><a class="btn g" style="display:block;text-align:center;text-decoration:none;margin-top:10px" href="api.php?a=export${rmode === 'rentang' ? '&from=' + d1 + '&to=' + d2 : ''}">EXPORT EXCEL</a>`}</div>`, { hero: false });
  $('#rp') && ($('#rp').onchange = e => { sessionStorage.setItem('rper', e.target.value); R.realisasi(); });
  $$('[data-rm]').forEach(o => o.onclick = () => { sessionStorage.setItem('rmode', o.dataset.rm); R.realisasi(); });
  ['rd1', 'rd2'].forEach(k => $('#' + k) && ($('#' + k).onchange = e => { sessionStorage.setItem(k, e.target.value); R.realisasi(); }));
};

/* ---------- Realisasi untuk HoS / Finance Manager (tab + bagian lipat) ---------- */
const jt = n => Math.abs(n) >= 1e6 ? (n < 0 ? '-' : '') + 'Rp ' + (Math.abs(n) / 1e6).toFixed(1).replace('.', ',') + ' jt' : rp(n);
const CATCLR = { Transportasi: '#F0643C', Entertain: '#9A5BE8', Perdin: '#3C8DE0', Operasional: '#F0A400' };
function rfSec(title, inner, open = true) { return `<details class="rf-sc" ${open ? 'open' : ''}><summary>${title}</summary><div>${inner}</div></details>`; }
function rfBars(o, max) { const e = Object.entries(o || {}); if (!e.length) return '<div class="mut">Belum ada data.</div>'; const m = max || Math.max(...e.map(x => x[1]), 1); return e.map(([k, v]) => `<div class="rf-hb"><div class="t"><span>${esc(k)}</span><b>${jt(v)}</b></div><div class="tr"><i style="width:${Math.max(2, v * 100 / m)}%"></i></div></div>`).join(''); }
function rfTren(tren, gran) {
  if (!tren.length) return '<div class="mut">Belum ada transaksi.</div>'; const W = 360, H = 150, pad = 14, base = 128, mx = Math.max(...tren.map(t => t.v), 1), cm = Math.max(...tren.map(t => t.cum), 1), n = tren.length, bw = Math.min(22, (W - pad * 2) / n * .62), st = (W - pad * 2) / n;
  const bars = tren.map((t, i) => { const h = Math.max(2, t.v / mx * 100); return `<rect x="${(pad + st * i + (st - bw) / 2).toFixed(1)}" y="${(base - h).toFixed(1)}" width="${bw.toFixed(1)}" height="${h.toFixed(1)}" rx="4" fill="#3C8DE0"><title>${esc(t.k)} · ${rp(t.v)}</title></rect>`; }).join('');
  const pts = tren.map((t, i) => `${(pad + st * i + st / 2).toFixed(1)},${(base - t.cum / cm * 100).toFixed(1)}`).join(' ');
  const lab = (i) => `<text x="${(pad + st * i + st / 2).toFixed(1)}" y="144" font-size="9" fill="#5a6784" text-anchor="middle">${esc(gran === 'bulan' ? tren[i].k : tren[i].k.slice(8) + '/' + tren[i].k.slice(5, 7))}</text>`;
  return `<svg viewBox="0 0 ${W} ${H}" role="img" aria-label="Tren realisasi">${bars}<polyline fill="none" stroke="#12A67F" stroke-width="3" stroke-linecap="round" points="${pts}"/><line x1="8" y1="${base}" x2="352" y2="${base}" stroke="#d5deeb"/>${lab(0)}${n > 1 ? lab(n - 1) : ''}</svg><div class="mut">Batang = per ${gran}, garis = kumulatif.</div>`;
}
function rfDonut(o) {
  const e = Object.entries(o || {}); const tot = e.reduce((a, x) => a + x[1], 0); if (!tot) return '<div class="mut">Belum ada data.</div>';
  let acc = 0; const seg = e.map(([k, v]) => { const a = acc, b = acc + v * 100 / tot; acc = b; return `${CATCLR[k] || '#999'} ${a}% ${b}%`; }).join(',');
  return `<div class="rf-donut"><div class="dn" style="background:conic-gradient(${seg})"></div><div class="lg">${e.map(([k, v]) => `<div><span><i style="--c:${CATCLR[k] || '#999'}"></i>${esc(k)}</span><b>${Math.round(v * 100 / tot)}%</b></div>`).join('')}</div></div>`;
}
function rfBud(b) {
  return b.cats.map(c => { const nb = NOBUD.includes(c.cat); const pct = nb ? 0 : (c.budget ? c.real * 100 / c.budget : (c.real ? 999 : 0)); const tg = pct > 100 ? '<span class="rf-tag to">OVER BUDGET</span>' : pct >= 85 ? `<span class="rf-tag tw">HAMPIR HABIS ${Math.round(pct)}%</span>` : '';
    return `<div class="rf-hb rf-bv ${pct > 100 ? 'ov' : ''}"><div class="t"><span>${esc(c.cat)}${tg}</span><b>${nb ? jt(c.real) + ' · tanpa budget' : jt(c.real) + ' / ' + jt(c.budget)}</b></div><div class="tr"><i style="width:${Math.min(100, pct)}%"></i></div></div>`; }).join('');
}
function realisasiFM(j, x) {
  const f = j.fm, k = f.kpi, tab = sessionStorage.getItem('rtab') || 'ringkasan'; const d = k.prev ? Math.round((k.total - k.prev) * 100 / k.prev) : null;
  const dl = d == null ? '<i>baru</i>' : `<i>${d >= 0 ? '▲' : '▼'} ${Math.abs(d)}%</i>`;
  const kpi = `<div class="rf-kpi"><div class="k1"><span>Total realisasi</span><b>${jt(k.total)}</b><span>vs periode sebelumnya ${dl}</span></div><div class="k2"><span>Klaim tertransfer</span><b>${k.n} klaim</b><span>rata-rata ${jt(k.avg)}</span></div><div class="k3"><span>Klaim memakai BA</span><b>${k.ba_n}${k.n ? ' (' + Math.round(k.ba_n * 100 / k.n) + '%)' : ''}</b><span>${jt(k.ba_total)}</span></div><div class="k4"><span>Petty cash belum dipertanggungjawabkan</span><b>${jt(k.pc_belum)}</b><span>${k.pc_belum_n} petty cash</span></div></div>`;
  const baBox = f.ba.cnt.length ? `<div class="rf-ba">${f.ba.cnt.map((c, i) => `<div><b>${c}</b>${esc(f.ba.label[i])}</div>`).join('')}</div>` : '<div class="mut">Tidak ada klaim BA pada periode ini.</div>';
  const pcBox = `<div class="card" style="margin:0">${hl('Dana cair', rp(j.pc.cair))}${hl('Terealisasi', rp(j.pc.real))}${hl('Sisa disetor', rp(j.pc.setor))}${hl('Belum dipertanggungjawabkan', rp(j.pc.belum))}</div>`;
  const trxBox = j.trx.map(t => `<div class="card"><div class="row"><b>${esc(t.ref)} · ${esc(t.who)}</b><b>${rp(t.amount)}</b></div><div class="mut">${esc(t.kind)} · ${esc(t.transfer_no)} · ${wktS(t.at)}</div></div>`).join('') || emptyBox('Belum ada transaksi.');
  const body = {
    ringkasan: kpi + '<div style="height:9px"></div>' + rfSec('KOMPOSISI KATEGORI', rfDonut(f.byCat)) + rfSec('PER BADAN USAHA', rfBars(f.byEnt)) + rfSec('TOP PEMOHON', rfBars(f.top), false) + rfSec('SOROTAN BA', baBox, false) + rfSec('PETTY CASH', pcBox, false),
    tren: rfSec('REALISASI PER ' + (f.gran === 'bulan' ? 'BULAN' : 'HARI'), rfTren(f.tren, f.gran)) + rfSec('TOP PEMOHON', rfBars(f.top)) + rfSec('PER KATEGORI', rfBars(f.byCat), false),
    budget: (f.bud.length ? f.bud.map((b, i) => rfSec('REALISASI VS BUDGET · ' + esc(b.entity), rfBud(b), i === 0)).join('') + `<div class="mut" style="margin:0 4px 8px">Budget = jumlah budget pemohon di badan usaha itu${f.months > 1 ? ' × ' + f.months + ' bulan' : ''}. Realisasi = klaim selesai pada periode budget tersebut.</div>` : emptyBox('Belum ada budget atau realisasi pada periode ini.')) + rfSec('SOROTAN BA', baBox),
    detail: rfSec('PETTY CASH', pcBox) + rfSec('TRANSAKSI (' + j.trx.length + ')', trxBox) + `<a class="btn g" style="display:block;text-align:center;text-decoration:none;margin-top:6px" href="api.php?a=export${x.rmode === 'rentang' ? '&from=' + x.d1 + '&to=' + x.d2 : ''}">EXPORT EXCEL</a>`,
  }[tab];
  shell(hero('Realisasi', 'F05 · READ-ONLY') + `<div class="sec"><div class="rf-flt"><select id="rmode"><option value="bulan" ${x.rmode === 'bulan' ? 'selected' : ''}>Periode (bulan)</option><option value="rentang" ${x.rmode === 'rentang' ? 'selected' : ''}>Rentang tanggal</option></select>${x.rmode === 'bulan' ? `<input type="month" id="rp" value="${x.per}">` : `<input type="date" id="rd1" value="${x.d1}"><input type="date" id="rd2" value="${x.d2}">`}</div>
   <div class="rf-flt"><select id="rent"><option value="">Semua badan usaha</option>${j.scope.map(e => `<option ${x.rent === e ? 'selected' : ''}>${esc(e)}</option>`).join('')}</select></div>
   <div class="tgl2" style="margin-bottom:9px">${[['ringkasan', 'Ringkasan'], ['tren', 'Tren'], ['budget', 'Budget'], ['detail', 'Detail']].map(t => `<div data-rt="${t[0]}" class="${tab === t[0] ? 'on' : ''}">${t[1]}</div>`).join('')}</div>${body}</div>`, { hero: false });
  $('#rp') && ($('#rp').onchange = e => { sessionStorage.setItem('rper', e.target.value); R.realisasi(); });
  $('#rmode').onchange = e => { sessionStorage.setItem('rmode', e.target.value); R.realisasi(); };
  $('#rent').onchange = e => { sessionStorage.setItem('rent', e.target.value); R.realisasi(); };
  ['rd1', 'rd2'].forEach(q => $('#' + q) && ($('#' + q).onchange = e => { sessionStorage.setItem(q, e.target.value); R.realisasi(); }));
  $$('[data-rt]').forEach(o => o.onclick = () => { sessionStorage.setItem('rtab', o.dataset.rt); R.realisasi(); });
}

/* ---------- Super Admin ---------- */
R.sa = async (sub, id) => {
  if (sub === 'users') return saUsers(); if (sub === 'codes') return saCodes(); if (sub === 'settings') return saSettings(); if (sub === 'switch') return saSwitch(); if (sub === 'harga') return saHarga(); if (sub === 'bu') return saBu();
  location.hash = '#/home';
};
async function saUsers() {
  const ac = await api('area_check'); const arAll = ac.regions.slice();
  const arTxt = u => { const a = u.areas ? String(u.areas).split(',').filter(Boolean) : []; const adm = (u.roles || '').split(',').some(r => r === 'ADMIN' || r === 'RGM'); if (!adm) return ''; return `<div class="mut">Wilayah tugas: ${a.length ? esc(a.join(', ')) : u.region ? esc(u.region) : '<b>Semua region</b>'}</div>`; };
  const arWarn = ac.uncovered.length ? `<div class="card" style="background:#FFF0CC;color:#7A5200;margin-bottom:8px"><b>Region tanpa Admin</b><div class="mut" style="color:#7A5200">${ac.uncovered.map(r => esc(r.region) + ' (' + r.n + ' pemohon)').join(', ')}. Klaim dari region ini belum punya Admin penerima.</div></div>` : '';
  const j = await api('sa_users');
  shell(hero('Peran klaim pengguna', 'X01', { back: true }) + `<div class="sec">${arWarn}<button class="btn g" id="unew">+ Pengguna baru</button><div style="margin-top:8px">${j.rows.map(u => `<div class="li" data-u="${u.id}"><div class="dot" style="background:#10394E">${esc(u.name[0])}</div><div class="t"><b>${esc(u.name)}${+u.active ? '' : ' (nonaktif)'}</b><span>${esc(u.username)} · ${esc(u.job)} · ${esc(u.region || '-')} · ${esc(u.entity || '-')}</span>${arTxt(u)}<div style="margin-top:3px">${(u.roles ? u.roles.split(',') : []).map(r => `<span class="pill p-adm">${r}</span> `).join('')}${+u.can_submit ? '<span class="pill p-ok">Pemohon</span>' : ''}</div></div>›</div>`).join('')}</div></div>`, { hero: false });
  const edit = u => sheet(`<h3>${u ? 'Ubah ' + esc(u.name) : 'Pengguna baru'}</h3>${u || window.CLAIM_DSM ? '' : fld('Username', '<input id="xu" autocapitalize="off">') + fld('Password (kosong = 123456)', '<input id="xp">')}${fld('Nama', `<input id="xn" value="${esc(u?.name || '')}">`)}${fld('Nomor WhatsApp', `<input id="xw" inputmode="tel" value="${esc(u?.wa || '')}" placeholder="0812 3456 7890">`, 'Untuk tombol Hubungi (follow-up klaim).')}${fld('NIK (nomor induk karyawan)' + (window.CLAIM_DSM && !u ? ' · pilih dari daftar DSM' : ''), `<input id="xk" value="${esc(u?.nik || '')}">`)}${fld('Jabatan (Sales/ASM/RGM/dst)', `<input id="xj" value="${esc(u?.job || '')}">`)}<div class="two">${fld('Region', `<input id="xr" value="${esc(u?.region || '')}">`)}${fld('Badan usaha (pisah koma)', `<input id="xe" value="${esc(u?.entity || '')}">`)}</div>
    <div class="fld" id="xarf" style="display:none"><label>Wilayah tugas Admin/RGM (boleh lebih dari satu)</label><div class="chips">${arAll.map(r => `<span class="chip" data-ar="${esc(r)}">${esc(r)}</span>`).join('')}</div><div class="mut" id="xarn" style="margin-top:6px"></div>${window.CLAIM_DSM ? '<button class="btn g" id="xard" style="margin-top:8px">⬇ AMBIL DARI PENUGASAN ADMIN DSM</button>' : ''}</div>
    <div class="fld"><label>Peran (ASM dan SALES hanya penanda jabatan, tidak menyetujui)</label><div class="chips">${['ADMIN', 'AUDIT', 'HOS', 'FM', 'FT', 'RGM', 'ASM', 'SALES', 'SA'].map(r => `<span class="chip" data-rl="${r}" style="${(u?.roles || '').split(',').includes(r) ? 'background:#10394E;color:#fff' : ''}">${r}</span>`).join('')}</div></div>
    <div class="row"><div><b>Boleh mengajukan klaim</b><div class="mut">Kemampuan mengajukan terpisah dari menyetujui. RGM dapat keduanya.</div></div>${sw(u ? +u.can_submit : 0, 'xs')}</div>${u ? `<div class="row" style="margin-top:8px"><b>Aktif</b>${sw(+u.active, 'xa')}</div>` : ''}<button class="btn" id="xok" style="margin-top:10px">SIMPAN</button>`, (el, close) => {
    $$('[data-rl]', el).forEach(c => c.onclick = () => { const on = c.dataset.on !== '1'; c.dataset.on = on ? '1' : '0'; c.style.cssText = on ? 'background:#10394E;color:#fff' : ''; }); $$('[data-rl]', el).forEach(c => c.dataset.on = (u?.roles || '').split(',').includes(c.dataset.rl) ? '1' : '0');
    const arOn = new Set(u?.areas ? String(u.areas).split(',').map(x => x.trim()).filter(Boolean) : []);
    const arPaint = () => { $$('[data-ar]', el).forEach(c => c.style.cssText = arOn.has(c.dataset.ar) ? 'background:#10394E;color:#fff' : ''); const n = $('#xarn', el); if (n) n.textContent = arOn.size ? 'Klaim dari region ini masuk ke orang ini: ' + [...arOn].join(', ') : 'Kosong = ikut Region di atas; kalau Region juga kosong = semua region.'; };
    const arShow = () => { const on = $$('[data-rl]', el).some(c => c.dataset.on === '1' && (c.dataset.rl === 'ADMIN' || c.dataset.rl === 'RGM')); $('#xarf', el).style.display = on ? '' : 'none'; };
    $$('[data-ar]', el).forEach(c => c.onclick = () => { arOn.has(c.dataset.ar) ? arOn.delete(c.dataset.ar) : arOn.add(c.dataset.ar); arPaint(); });
    $$('[data-rl]', el).forEach(c => c.addEventListener('click', () => setTimeout(arShow, 0))); arShow(); arPaint();
    const ad = $('#xard', el); if (ad) ad.onclick = () => guard(async () => { const nk = $('#xk', el).value; const r = await api('area_dsm', { nik: nk }); if (!r.areas.length) return toast('Tidak ada penugasan Admin di DSM untuk NIK ini.'); r.areas.forEach(a => { const m = arAll.find(x => x.toUpperCase() === a.toUpperCase()); arOn.add(m || a); }); if (r.areas.some(a => !arAll.some(x => x.toUpperCase() === a.toUpperCase()))) toast('Sebagian wilayah belum punya pemohon, tetap ditambahkan.'); arPaint(); });
    $$('[data-sw]', el).forEach(s => s.onclick = () => s.classList.toggle('on'));
    if (window.CLAIM_DSM && !u && window.dsmPersonInput) guard(async () => { // pilih orang dari daftar DSM, nama/jabatan/region terisi otomatis
      const pl = (await api('dsm_people')).rows, k = $('#xk', el);
      window.dsmPersonInput(k, pl, { format: p => p.nik, title: 'Pilih pengguna DSM', ph: 'Pilih orang dari DSM…', search: 'Cari nama / NIK / role / region' });
      k.addEventListener('change', () => { const p = pl.find(x => x.nik === k.value); if (!p) return; $('#xn', el).value = p.nama || ''; $('#xj', el).value = p.role ? p.role.charAt(0) + p.role.slice(1).toLowerCase() : ''; $('#xr', el).value = p.region || ''; });
    });
    $('#xok', el).onclick = () => guard(async () => { await api('sa_user_save', { id: u?.id, username: $('#xu', el)?.value, password: $('#xp', el)?.value, name: $('#xn', el).value, wa: $('#xw', el).value, nik: $('#xk', el).value, job: $('#xj', el).value, region: $('#xr', el).value, areas: [...arOn], entity: $('#xe', el).value, roles: $$('[data-rl]', el).filter(c => c.dataset.on === '1').map(c => c.dataset.rl), can_submit: $('[data-sw="xs"]', el).classList.contains('on') ? 1 : 0, active: u ? ($('[data-sw="xa"]', el).classList.contains('on') ? 1 : 0) : 1 }); close(); S.L = await api('lookups'); toast('Tersimpan.'); saUsers(); }); });
  $$('[data-u]').forEach(e => e.onclick = () => edit(j.rows.find(r => r.id == e.dataset.u))); $('#unew').onclick = () => edit(null);
}
async function saCodes() {
  const h = await api('backup_holders'); let pp = { rows: [] }; const isSA = S.me.roles.includes('SA');
  const mine = h.rows.filter(r => isSA || r.id == S.me.id); let dur = 24; const firstRole = () => { const f = mine[0]; return f && String(f.roles).split(',').includes('ADMIN') ? 'ADMIN' : 'FT'; };
  const opt = (r, none) => `<select id="${r}"><option value="">${none}</option>${pp.rows.map(p => `<option value="${p.id}" data-nama="${esc(p.name)}" data-role="${esc(p.job || '')}" data-region="${esc(p.region || '')}" data-nik=" ">${esc(p.name)}</option>`).join('')}</select>`;
  const draw = async () => {
    const L = (await api('backup_list')).rows;
    const fmt = s => { const h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60); return (h ? h + ' jam ' : '') + m + ' mnt'; };
    const tg = { AKTIF: 't1', BELUM: 't4', SELESAI: 't2', KEDALUWARSA: 't2', DICABUT: 't3' }, lb = { AKTIF: 'AKTIF', BELUM: 'BELUM DIPAKAI', SELESAI: 'SELESAI', KEDALUWARSA: 'KEDALUWARSA', DICABUT: 'DICABUT' };
    $('#clist').innerHTML = L.length ? L.map(r => `<div class="card"><div class="row"><b>${esc(r.to_name || '-')}</b><span class="chip ${r.status === 'AKTIF' ? 'on' : ''}">${lb[r.status]}</span></div><div class="mut">${STAGE[r.role] || r.role} · menggantikan ${esc(r.for_name || '-')}<br>Durasi ${(r.duration_min || 120) / 60} jam · diberi oleh ${esc(r.issuer_name || '-')}${r.status === 'AKTIF' ? ` · sisa ${fmt(r.left)}` : ''}${r.used_at ? `<br>Masuk ${r.use_count || 0} dari ${r.max_use || 5} kali · terakhir ${esc(String(r.last_used_at || r.used_at).slice(5, 16))}` : `<br>Berlaku dipakai sampai ${esc(String(r.expires_at).slice(5, 16))}`}${r.status === 'DICABUT' ? `<br>Dicabut ${esc(String(r.revoked_at).slice(5, 16))}` : ''}</div>${r.status === 'AKTIF' || r.status === 'BELUM' ? `<button class="btn r2 sm" data-rv="${r.id}" style="margin-top:8px">${r.status === 'AKTIF' ? 'CABUT AKSES' : 'CABUT KODE'}</button>` : ''}</div>`).join('') : '<div class="note">Belum ada kode.</div>';
    $$('[data-rv]').forEach(b => b.onclick = () => { if (!confirm('Cabut sekarang? Penerima langsung kehilangan akses.')) return; guard(async () => { await api('backup_revoke', { id: b.dataset.rv }); toast('Dicabut.'); draw(); }); });
  };
  shell(hero('Kode cadangan', 'X02', { back: true }) + `<div class="sec"><div class="note blue">Kode untuk <b>satu orang</b> yang Anda pilih, menggantikan <b>Admin</b> atau <b>Finance Transfer</b>. Jam durasi mulai saat kode dipakai pertama kali (kode harus dipakai dalam 24 jam). Penerima bisa masuk lagi maksimal 5 kali selama durasi. Bisa dicabut kapan saja. HOS, Audit, dan Finance Manager tidak punya pengganti.</div><div class="card">${fld('Orang yang digantikan', `<select id="ch">${mine.map(r => `<option value="${r.id}" data-nama="${esc(r.name)}" data-role="${esc(String(r.roles))}" data-nik=" ">${esc(r.name)} (${r.roles})</option>`).join('')}</select>`)}${fld('Peran yang dibuka', '<select id="cr"><option value="ADMIN">Admin</option><option value="FT">Finance Transfer</option></select>')}<div id="cwho"></div><div class="fld"><label>Durasi akses</label><div class="chips" id="cdur">${[[2, '2 jam'], [4, '4 jam'], [8, '8 jam'], [24, '1 hari'], [72, '3 hari']].map(d => `<span class="chip ${d[0] === dur ? 'on' : ''}" data-d="${d[0]}">${d[1]}</span>`).join('')}</div></div><button class="cta" id="cgo">TERBITKAN KODE</button><div id="cout"></div></div><h3 style="margin-top:14px">Daftar kode</h3><div id="clist"></div></div>`, { hero: false });
  pemohonUp(document, 'ch');
  const who = async () => { pp = await api('backup_people', { role: $('#cr').value, except: $('#ch').value }); $('#cwho').innerHTML = fld('Diberikan kepada (satu orang, peran ' + (STAGE[$('#cr').value] || '') + ' juga)', pp.rows.length ? opt('cto', 'Pilih orang') : '<div class="note red">Tidak ada pemegang peran ' + (STAGE[$('#cr').value] || '') + ' lain yang aktif.</div>'); pemohonUp(document, 'cto'); };
  $('#cr').value = firstRole(); $('#cr').onchange = who; $('#ch').addEventListener('change', () => { const o = $('#ch').selectedOptions[0]; if (o) $('#cr').value = String(o.dataset.role || '').split(',').includes('ADMIN') ? 'ADMIN' : 'FT'; who(); }); who();
  $$('[data-d]').forEach(c => c.onclick = () => { dur = +c.dataset.d; $$('[data-d]').forEach(x => x.classList.toggle('on', x === c)); });
  $('#cgo').onclick = () => guard(async () => {
    const el = $('#cto'); const nm = el ? el.value : ''; const so = el && el.selectedOptions && el.selectedOptions[0]; const p = pp.rows.find(x => String(x.id) === String(nm) || x.name === nm || (so && x.name === so.dataset.nama)); if (!p) throw new Error('Pilih orang yang akan diberi kode.');
    const j = await api('backup_issue', { role: $('#cr').value, for_user_id: $('#ch').value, to_user_id: p.id, duration_hours: dur });
    $('#cout').innerHTML = `<div class="note ok" style="text-align:center;margin-top:10px">Kode untuk <b>${esc(j.to)}</b> (tampil sekali):<div style="font-size:28px;letter-spacing:.25em;font-weight:800">${j.code}</div>Akses ${j.duration_hours} jam sejak dipakai. Kirim kode ini ke ${esc(j.to)} lewat WA.</div>`; draw();
  });
  draw();
}
async function saSettings() {
  shell(hero('Pengaturan Super Admin', 'MODE TES', { back: true }) + `<div class="sec"><div class="card"><div class="row"><div><b>Mode tes</b><div class="mut">Super Admin boleh bertindak di semua tahap, tanpa batas region/self-approval. Dicatat di riwayat dengan tanda [MODE TES]. Matikan saat produksi.</div></div>${sw(S.testMode, 'tm')}</div></div>
   <div class="card"><b>Penanda “menunggu lama” (N hari)</b><div class="mut">Diatur di Pengaturan klaim.</div><button class="btn g sm" data-go="#/a/atur" style="margin-top:8px">BUKA</button></div>
   ${window.CLAIM_DSM ? `<div class="card"><b>Akun uji semua peran</b><div class="mut">Membuat akun Admin, Audit, HOS, FM, FT, RGM, ASM, Sales (berlabel “uji”) untuk dicoba lewat Ganti peran. Tidak bisa dipakai login. Butuh Mode tes aktif.</div><div class="two" style="margin-top:8px"><button class="btn sm" id="ujion">BUAT / AKTIFKAN</button><button class="btn g sm" id="ujioff">NONAKTIFKAN</button></div></div>` : ''}${window.CLAIM_DSM ? '' : `<div class="card" style="border-color:#D2453F"><b style="color:#D2453F">Reset data uji</b><div class="mut">Menghapus SEMUA data klaim dan mengisi ulang data awal. Hanya untuk uji lokal.</div><button class="btn r2 sm" id="rst" style="margin-top:8px">RESET SEMUA DATA</button></div>`}</div>`, { hero: false });
  $('[data-sw="tm"]').onclick = e => { const el = e.currentTarget; guard(async () => { const on = !el.classList.contains('on'); await api('sa_testmode', { on: on ? 1 : 0 }); await loadMe(); toast(on ? 'Mode tes aktif.' : 'Mode tes mati.'); saSettings(); }); };
  if ($('#ujion')) { $('#ujion').onclick = () => guard(async () => { const j = await api('sa_seed_uji'); toast(j.dibuat ? j.dibuat + ' akun uji dibuat. Buka Ganti peran.' : 'Akun uji sudah ada dan aktif.'); }); $('#ujioff').onclick = () => guard(async () => { await api('sa_uji_off'); toast('Akun uji dinonaktifkan.'); }); }
  if ($('#rst')) $('#rst').onclick = () => { if (!confirm('Hapus SEMUA data klaim dan reset?')) return; if (!confirm('Yakin? Tindakan ini tidak bisa dibatalkan.')) return; guard(async () => { await api('sa_reset'); S.me = null; S.L = null; toast('Data direset. Masuk lagi.'); location.hash = '#/login'; route(); }); };
}
async function saSwitch() {
  const j = await api('sa_users');
  shell(hero('Ganti peran (uji)', 'SUPER ADMIN', { back: true }) + `<div class="sec"><div class="note blue">Masuk sebagai pengguna lain tanpa password. Banner di atas layar akan menampilkan tombol “kembali”.</div>${j.rows.filter(u => +u.active).map(u => `<div class="li" data-sw2="${u.id}"><div class="dot" style="background:#10394E">${esc(u.name[0])}</div><div class="t"><b>${esc(u.name)}</b><span>${esc(u.job)} · ${esc(u.region || '-')} · ${esc(u.entity || '-')}</span></div>›</div>`).join('')}</div>`, { hero: false });
  $$('[data-sw2]').forEach(e => e.onclick = () => guard(async () => { await api('switch_user', { user_id: e.dataset.sw2 }); await loadMe(); S.mode = null; location.hash = '#/home'; route(); }));
}
route();

async function saHarga() {
  const j = await api('fuel_list'); const cur = j.rows.find(r => r.effective_from <= j.today);
  shell(hero('Harga BBM', 'X03', { back: true }) + `<div class="sec"><div class="note blue">Dipakai menghitung klaim BBM sesuai <b>tanggal nota</b>. Pertalite diklaim penuh sesuai nota. Pertamax dikonversi ke liter lalu dikali harga Pertalite, dibulatkan Rp 100.</div>
   <div class="card" style="margin-top:10px"><b>Tambah harga baru</b><div class="two">${fld('Berlaku mulai', `<input type="date" id="fd" value="${j.today}">`)}<div></div></div><div class="two">${fld('Pertalite /L', '<input id="fp" inputmode="numeric" placeholder="10.000">')}${fld('Pertamax /L', '<input id="fx" inputmode="numeric" placeholder="12.500">')}</div><button class="btn" id="fok">SIMPAN HARGA</button></div>
   <h3 style="margin-top:14px">Riwayat harga</h3>${j.rows.length ? j.rows.map(r => `<div class="li"><div style="flex:1"><b>Berlaku ${esc(r.effective_from)}</b>${cur && cur.id === r.id ? ' <span class="chip on">BERLAKU</span>' : ''}<div class="mut">Pertalite ${rp(r.pertalite)} · Pertamax ${rp(r.pertamax)} · oleh ${esc(r.uname || '-')}</div></div></div>`).join('') : '<div class="note red">Belum ada harga. Klaim BBM tidak bisa dikirim sebelum harga diisi.</div>'}</div>`);
  $$('#fp,#fx').forEach(e => e.oninput = () => { e.value = fmtN(num(e.value)); });
  $('#fok').onclick = () => guard(async () => { await api('fuel_save', { effective_from: $('#fd').value, pertalite: num($('#fp').value), pertamax: num($('#fx').value) }); S.L = await api('lookups'); toast('Harga BBM tersimpan'); saHarga(); });
}

async function saBu() {
  const j = await api('entity_list');
  shell(hero('Badan usaha', 'X04', { back: true }) + `<div class="sec"><div class="note blue">Daftar ini muncul sebagai pilihan Admin saat menyetujui klaim. Badan usaha yang sudah melekat pada pengguna otomatis ikut tampil.</div>
   <div class="card" style="margin-top:10px"><b>Tambah badan usaha</b>${fld('Nama (mis. TOP, SMI, CSN)', '<input id="bun" autocapitalize="characters" placeholder="NAMA BADAN USAHA">')}<button class="btn" id="buok">TAMBAH</button></div>
   <h3 style="margin-top:14px">Daftar (${j.rows.length})</h3>${j.rows.map(r => `<div class="li"><div style="flex:1"><b>${esc(r.name)}</b><div class="mut">${r.used ? 'Sedang dipakai' : 'Belum dipakai'}</div></div>${r.used ? '' : `<span data-bud="${esc(r.name)}" style="color:#D2453F;cursor:pointer;font-weight:700">Hapus</span>`}</div>`).join('')}</div>`);
  $('#buok').onclick = () => guard(async () => { await api('entity_add', { name: $('#bun').value }); S.L = await api('lookups'); toast('Badan usaha ditambahkan'); saBu(); });
  $$('[data-bud]').forEach(e => e.onclick = () => confirm('Hapus ' + e.dataset.bud + '?') && guard(async () => { await api('entity_del', { name: e.dataset.bud }); S.L = await api('lookups'); saBu(); }));
}

/* ---------- Kalender pengajuan (semua peran) ---------- */
R.kalender = async () => {
  const now = new Date(); let ym = sessionStorage.getItem('calm') || now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
  const j = await api('cal', { month: ym }); const [Y, M] = ym.split('-').map(Number); const first = new Date(Y, M - 1, 1), nd = new Date(Y, M, 0).getDate(), lead = (first.getDay() + 6) % 7;
  const todayS = today(); const ds = j.days || {}; 
  const lvl = n => n <= 0 ? '' : n <= 3 ? 'l1' : n <= 6 ? 'l2' : n <= 9 ? 'l3' : 'l4';
  const NM = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  let cells = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'].map((d, i) => `<div class="cal-h${i === 6 ? ' s' : ''}">${d}</div>`).join('') + '<div></div>'.repeat(lead);
  for (let d = 1; d <= nd; d++) { const date = ym + '-' + String(d).padStart(2, '0'), x = ds[date], dow = (lead + d - 1) % 7;
    cells += `<button type="button" class="cal-d ${x ? lvl(Math.max(x.n, x.r || 0)) : ''}${date === todayS ? ' td' : ''}${dow === 6 ? ' sun' : ''}" ${x ? `data-cd="${date}"` : 'disabled'}><b>${d}</b><em>${x ? x.n + '/' + (x.r || 0) : '-'}</em></button>`; }
  const shift = k => { const dt = new Date(Y, M - 1 + k, 1); sessionStorage.setItem('calm', dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0')); R.kalender(); };
  shell(hero('Kalender', 'PENGAJUAN', { back: true }) + `<div class="sec"><div class="card"><div class="cal-mh"><button id="cprev">‹</button><b>${NM[M - 1]} ${Y}</b><button id="cnext">›</button></div><div class="cal-g">${cells}</div>
   <div class="cal-lg"><span><i style="background:#DDEBFB"></i>1–3</span><span><i style="background:#B7D4F6"></i>4–6</span><span><i style="background:#7FB0EE"></i>7–9</span><span><i style="background:#1E5FB8"></i>10+</span><span><i style="background:#F4F7FC;border:1px solid #e1e8f3"></i>kosong</span></div>
   <div class="mut" style="margin-top:8px">${j.total} pengajuan · ${j.real || 0} realisasi bulan ini. Angka <b>3/2</b> di tiap tanggal = 3 pengajuan dikirim, 2 realisasi (ditransfer) pada hari itu. Ketuk tanggal untuk melihat daftarnya.</div></div></div>`, { hero: false });
  $('#cprev').onclick = () => shift(-1); $('#cnext').onclick = () => shift(1);
  $$('[data-cd]').forEach(b => b.onclick = () => guard(async () => {
    const date = b.dataset.cd; const r = await api('cal_day', { date }); const sum = r.sub.reduce((a, x) => a + x.total, 0), rsum = r.real.reduce((a, x) => a + x.total, 0);
    let tb = r.sub.length || !r.real.length ? 'sub' : 'real';
    sheet(`<h3>${tgl(date)}</h3><div class="tgl2" style="margin-bottom:8px"><div data-ct="sub">Pengajuan<small>${r.sub.length} klaim · ${rp(sum)}</small></div><div data-ct="real">Realisasi<small>${r.real.length} klaim · ${rp(rsum)}</small></div></div><div id="cl"></div>`, (el, close) => {
      const paint = () => { const f = tb === 'sub' ? r.sub : r.real; $('#cl', el).innerHTML = f.map(claimLi).join('') || emptyBox(tb === 'sub' ? 'Tidak ada pengajuan.' : 'Tidak ada realisasi.'); $$('[data-ct]', el).forEach(c => c.classList.toggle('on', c.dataset.ct === tb)); };
      $$('[data-ct]', el).forEach(c => c.onclick = () => { tb = c.dataset.ct; paint(); }); paint(); el.addEventListener('click', e => { if (e.target.closest('[data-go]')) close(); }); });
  }));
};
