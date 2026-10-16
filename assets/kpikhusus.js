/* KPI Khusus (ST, SO, DA, DOS) — panel Admin 4 tab + daftar DOS dealer (Sales / ASM / atasan).
   Dimuat dari index.php; memakai helper global aplikasi (rpc, esc, num, decimal, panel, modal, closeModal, fail, $).
   Spesifikasi: dsm-ux/SPEC-kpi-so-dos.md */
(function () {
  'use strict';
  const css = `
.kkTb{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0}.kkTb .fi{min-width:150px;flex:1 1 150px}.kkChk{display:flex;gap:6px;align-items:center;font-size:12.5px;color:#5a6b80;font-weight:600}
.kkPill{display:inline-block;border-radius:99px;padding:2px 9px;font:800 11px system-ui;background:#EDF1F7;color:#4B5A70}.kkPill.k{background:linear-gradient(135deg,#6D3FD8,#9B6BF2);color:#fff}
.kkEd .kkRow{display:grid;grid-template-columns:90px minmax(120px,1fr) 80px 130px 34px;gap:6px;align-items:center;margin:6px 0}.kkEd .kkRow .fi{width:100%;min-width:0}
.kkEd .kkX{border:0;background:#FDE3E7;color:#B3203A;border-radius:10px;height:34px;font-weight:800;cursor:pointer}
.kkEd .kkTot{display:flex;justify-content:space-between;align-items:center;border-radius:12px;padding:8px 12px;font-weight:800;margin:8px 0;background:#E3F6EE;color:#0B7A55}.kkEd .kkTot.bad{background:#FDE3E7;color:#B3203A}
.kkEd .kkChips{display:flex;gap:6px;flex-wrap:wrap;margin:6px 0}.kkEd .kkChips button{border:1px solid #D7E2F0;background:#fff;border-radius:99px;padding:6px 12px;font:700 12px system-ui;color:#5a6b80;cursor:pointer}.kkEd .kkChips button.on{background:linear-gradient(135deg,#2B7BE0,#5BA2F5);border-color:transparent;color:#fff}
.kkEd .kkNote{background:#FFF6E5;border:1px solid #F8DDA8;border-radius:12px;padding:8px 11px;color:#6B4A00;font-size:12px;margin:8px 0}
.kkEd .kkPrev{background:linear-gradient(135deg,#EEF4FD,#fff);border:1px solid #E3EAF3;border-radius:14px;padding:10px 12px;margin:8px 0;font-size:12.5px}
.kkEd #kkRep,.kkEd #kkReason{width:100%}
.kkEd label.kkL{display:block;font:800 10.5px system-ui;letter-spacing:.06em;color:#6B7A90;text-transform:uppercase;margin:10px 0 3px}
@media(max-width:560px){.kkEd .kkRow{grid-template-columns:1fr 1fr;}.kkEd .kkRow .kkX{grid-column:2}}
.kkCard{background:linear-gradient(135deg,#F4EEFF,#EEF5FF 60%,#FFF);border:1px solid #fff;border-radius:18px;box-shadow:0 8px 24px rgba(20,40,80,.09);padding:14px;margin:0 0 14px}
.kkCard h3{margin:0;font:800 15px system-ui;letter-spacing:.02em}.kkCard .kkSub{color:#6B7A90;font-size:12px;margin:2px 0 8px}
.kkTiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:8px;margin:8px 0}.kkTiles div{background:rgba(255,255,255,.92);border:1px solid #fff;border-radius:14px;box-shadow:0 4px 14px rgba(20,40,80,.07);padding:8px 10px}.kkTiles small{display:block;color:#6B7A90;font-size:10px;font-weight:800;letter-spacing:.05em;text-transform:uppercase}.kkTiles b{font:800 17px system-ui}
.kkMode{display:flex;gap:6px;margin:8px 0;flex-wrap:wrap}.kkMode button{flex:1 1 120px;border:0;border-radius:12px;padding:8px;font:800 11.5px system-ui;background:#fff;color:#6B7A90;box-shadow:0 4px 14px rgba(20,40,80,.07);cursor:pointer}.kkMode button.on{background:linear-gradient(135deg,#6D3FD8,#9B6BF2);color:#fff}
.kkCrumb{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin:6px 0}.kkCrumb button{border:0;background:#fff;border-radius:99px;padding:5px 11px;font:700 11.5px system-ui;color:#1E5FB8;box-shadow:0 4px 14px rgba(20,40,80,.07);cursor:pointer}.kkCrumb span{color:#6B7A90}
.kkFlt{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:6px;margin:8px 0}.kkFlt .fi{width:100%;min-width:0}
.kkCard .bg{display:inline-block;border-radius:8px;padding:2px 8px;font:800 11px system-ui;white-space:nowrap}.kkCard .bg.g{background:#E3F6EE;color:#0B7A55}.kkCard .bg.y{background:#FFF3D6;color:#8A5E00}.kkCard .bg.r{background:#FDE3E7;color:#B3203A}.kkCard .bg.n{background:#EDF1F7;color:#4B5A70}
.kkCard .kkPt{font:800 12px system-ui;border-radius:99px;padding:3px 10px;white-space:nowrap}.kkCard .kkPt.g{background:#E3F6EE;color:#0B7A55}.kkCard .kkPt.r{background:#FDE3E7;color:#B3203A}.kkCard .kkPt.n{background:#EDF1F7;color:#4B5A70}
.kkCard table{width:100%!important;border-collapse:collapse!important;font-size:12.5px!important}
#view .kkCard th{font:800 10px system-ui!important;letter-spacing:.05em;text-transform:uppercase;color:#fff!important;background:#2B7BE0!important;padding:8px!important;text-align:right!important;white-space:nowrap!important;position:sticky;top:0;border:0!important;height:auto!important}
#view .kkCard th:first-child{text-align:left!important;border-radius:10px 0 0 10px}#view .kkCard th:last-child{border-radius:0 10px 10px 0}
#view .kkCard td{padding:7px 8px!important;border:0!important;border-bottom:1px solid #E3EAF3!important;text-align:right!important;white-space:nowrap!important;background:rgba(255,255,255,.75)!important;font-size:12.5px!important;height:auto!important;color:#16202e!important;font-weight:500}
#view .kkCard td:first-child{text-align:left!important;font-weight:700}#view .kkCard td.l{text-align:left!important}#view .kkCard tr.kkGo{cursor:pointer}#view .kkCard tr.kkGo:hover td{background:#F3F7FD!important}
#view .kkCard td .kkDual,#view .kkCard td.kkDual{font-size:11px!important;color:#6B7A90!important;font-weight:500}
.kkCard .kkScroll{overflow:auto;max-height:520px;border-radius:12px}.kkCard .kkDual{color:#6B7A90;font-size:11.5px}
.kkCard .kkMine{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;margin:8px 0}.kkCard .kkMine div.k{background:#fff;border:1px solid #E3EAF3;border-radius:14px;padding:10px 12px}
.kkCard .kkMine small{color:#6B7A90;font-size:11px}.kkCard .kkMine b{font:800 14px system-ui}
.kkCard .kkZone{display:flex;gap:6px;flex-wrap:wrap;margin:6px 0}
.kkTabs{display:flex;gap:8px;flex-wrap:wrap;margin:6px 0 12px}.kkTabs button{border:0;border-radius:99px;padding:8px 15px;font:800 12px system-ui;background:#fff;color:#5a6b80;cursor:pointer;box-shadow:0 3px 12px rgba(43,123,224,.12)}.kkTabs button.on{background:linear-gradient(135deg,#6D3FD8,#2B7BE0);color:#fff}
.kkFlash{border-radius:12px;padding:9px 13px;margin:8px 0;font-weight:700;font-size:13px;background:#E3F6EE;color:#0B7A55}.kkFlash.bad{background:#FDE3E7;color:#8E1B30}
.kkBox{background:rgba(255,255,255,.82);border:1px solid #fff;border-radius:16px;box-shadow:0 6px 20px rgba(43,80,160,.1);padding:12px 14px;margin:10px 0}
.kkInd{background:#fff;border:1px solid #E3EAF3;border-radius:14px;padding:10px 12px;margin:8px 0;display:flex;flex-direction:column;gap:8px}.kkInd.dos{background:linear-gradient(#FFF7ED,#fff);border-color:#FBD3A0}
.kkLn{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.kkLn>*{flex:0 0 auto}.kkLn .g{flex:1 1 150px;min-width:0}.kkLn .fi{min-width:0}.kkLn small,.kkLn .nt{color:#6B7A90;font-size:12px}
.kkTy{display:inline-block;border-radius:99px;padding:3px 11px;font:800 11px system-ui}.kkTy.ST{background:#DCFCE7;color:#166534}.kkTy.SO{background:#DBEAFE;color:#1D4ED8}.kkTy.DA{background:#FAE8FF;color:#A21CAF}.kkTy.DOS{background:#FFEDD5;color:#C2410C}
.kkInd .kkX{border:0;background:#FDE3E7;color:#B3203A;border-radius:10px;height:32px;padding:0 12px;font-weight:800;cursor:pointer}
.kkChips{display:flex;gap:6px;flex-wrap:wrap;align-items:center}.kkChips button{border:1px solid #D7E2F0;background:#fff;border-radius:99px;padding:5px 11px;font:700 11.5px system-ui;color:#5a6b80;cursor:pointer}.kkChips button.on{background:linear-gradient(135deg,#2B7BE0,#5BA2F5);border-color:transparent;color:#fff}
.kkTot{display:flex;justify-content:space-between;align-items:center;border-radius:12px;padding:9px 13px;font-weight:800;margin:8px 0;background:#E3F6EE;color:#0B7A55}.kkTot.bad{background:#FDE3E7;color:#B3203A}
.kkNote{background:#FFF6E5;border:1px solid #F8DDA8;border-radius:12px;padding:8px 11px;color:#6B4A00;font-size:12px;margin:6px 0}.kkNote.bad{background:#FDE3E7;border-color:#F4B7C0;color:#8E1B30}
.kkOk{display:inline-block;border-radius:99px;padding:2px 9px;font:800 10.5px system-ui;background:#DCFCE7;color:#166534}.kkWarn{display:inline-block;border-radius:99px;padding:2px 9px;font:800 10.5px system-ui;background:#FEF3C7;color:#92400E}
.kkSim{display:flex;gap:8px;align-items:center;flex-wrap:wrap;background:#F0FDF4;border-radius:12px;padding:7px 11px;font-size:12.5px}.kkSim .fi{width:80px}
.kkTier{width:100%!important;border-collapse:collapse!important;min-width:360px}.kkTier th{font:800 10.5px system-ui!important;color:#6B7A90!important;text-transform:uppercase;text-align:left!important;padding:4px 6px!important;background:transparent!important}.kkTier td{padding:3px 6px!important;border:0!important;background:transparent!important}.kkTier td .fi{width:100%}
.kkWrap{overflow-x:auto}.kkLbl{display:block;font:800 10.5px system-ui;letter-spacing:.06em;color:#6B7A90;text-transform:uppercase;margin:12px 0 4px}
.kkPrevBox{background:linear-gradient(135deg,#EEF4FD,#fff);border:1px solid #E3EAF3;border-radius:14px;padding:10px 12px;margin:8px 0;font-size:12.5px}
.kkPick{max-height:220px;overflow:auto;display:flex;flex-direction:column;gap:3px;border:1px solid #E3EAF3;border-radius:12px;padding:8px;background:#fff}.kkPick label{display:flex;gap:8px;align-items:center;font-size:12.5px}
.kkLink{background:none;border:0;color:#1E5FB8;font:800 12.5px system-ui;cursor:pointer;text-decoration:underline;padding:0}
`;
  const st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);
  const E = s => (typeof esc === 'function' ? esc(s) : String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])));
  const N = n => (typeof num === 'function' ? num(n) : (n == null ? '—' : Math.round(n).toLocaleString('id-ID')));
  const D = n => (n == null ? '—' : Number(n).toLocaleString('id-ID', { maximumFractionDigits: 1 }));
  const SIGN = n => (n > 0 ? '+' + n : String(n));
  const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  const monthName = p => MON[(+String(p).slice(5, 7)) - 1] || '';

  // ------------------------------------------------------------------
  // Panel Admin "KPI Khusus": 4 tab inline (Daftar Orang · Editor · Aturan Poin DOS · Target SO), tanpa popup
  // ------------------------------------------------------------------
  let KK = null, EDIT = null, CP = null, TG = null, RL = null;
  const S = { tab: 'list', q: '', role: '', only: false, flash: null, dl: null, ask: null };
  const prevPer = per => { const y = +per.slice(0, 4), m = +per.slice(5, 7) - 1; return m < 1 ? (y - 1) + '-12' : y + '-' + String(m).padStart(2, '0'); };
  const toUi = r => ({ tiers: (r.tiers || []).map(t => Array.isArray(t) ? { from: t[0], pts: t[1] } : { from: t.from, pts: t.pts }), stok0: r.stok0, noSo: r.noSo });
  const cl = o => JSON.parse(JSON.stringify(o));
  const ruleMax = r => Math.max(0, ...r.tiers.map(t => +t.pts || 0), +r.stok0 || 0, +r.noSo || 0);
  const simPts = (r, d) => { let p = 0; [...r.tiers].sort((a, b) => a.from - b.from).forEach(t => { if (d >= +t.from) p = +t.pts; }); return p; };
  const num0 = v => { const n = parseFloat(String(v).replace(',', '.')); return isNaN(n) ? 0 : n; };
  const flash = (t, bad) => { S.flash = t ? { t, bad } : null; };
  const gradesFor = b => b === 'ALL' ? KK.grades : (KK.gradesByBrand[b] || []);
  const typesFor = b => b === 'ALL' ? [...new Set(Object.values(KK.types).flat())].sort() : (KK.types[b] || []);
  const ruleOf = i => EDIT.items[i].ruleMode === 'custom' ? EDIT.items[i].rule : toUi(KK.rule);

  window.kkAdminPanel = function (v, a) {
    v.insertAdjacentHTML('beforeend', panel('KPI Khusus', '<div id="kkApp" class="kkEd"><p class="v6load">Memuat…</p></div>', 'ST, SO, DA, dan DOS per Sales / ASM'));
    const box = $('kkApp'); if (!box) return;
    box.onclick = onClick; box.onchange = onChange; box.oninput = onInput;
    kkLoad();
  };
  async function kkLoad() {
    const box = $('kkApp'); if (!box) return;
    try { KK = await rpc('apiKpiKhususGet', TOKEN, REPORT.period); RL = null; app(); }
    catch (e) { box.innerHTML = '<p class="v6rmErr">' + E(e.message || e) + '</p>'; }
  }
  function app() {
    const box = $('kkApp'); if (!box || !KK) return;
    const tabs = [['list', 'Daftar Orang'], ['edit', 'Editor KPI Khusus'], ['rule', 'Aturan Poin DOS'], ['target', 'Target SO']];
    box.innerHTML = `<div class="kkTabs">${tabs.map(([k, l]) => `<button type="button" data-kktab="${k}" class="${S.tab === k ? 'on' : ''}">${l}</button>`).join('')}</div>${S.flash ? `<div class="kkFlash ${S.flash.bad ? 'bad' : ''}">${S.flash.t}</div>` : ''}${askHtml()}<div id="kkTabBody">${S.tab === 'list' ? listHtml() : S.tab === 'edit' ? editHtml() : S.tab === 'rule' ? ruleHtml() : targetHtml()}</div>`;
  }

  // ----- inline konfirmasi (pengganti popup) -----
  function askHtml() {
    const a = S.ask; if (!a) return '';
    return `<div class="kkBox"><b>${E(a.title)}</b><p class="hint" style="margin:4px 0 8px">${a.text}</p><div class="kkLn"><input class="fi g" id="kkAskReason" placeholder="Alasan (minimal 5 karakter)"><button class="chip primary" type="button" data-act="askOk">Lanjut</button><button class="chip" type="button" data-act="askNo">Batal</button></div></div>`;
  }
  const ask = (title, text, fn) => { S.ask = { title, text, fn }; app(); };

  // ----- tab 1: daftar orang -----
  const summary = c => !c ? '<span class="kkPill">BELUM DIATUR</span>' : c.items.map(i => `<span class="kkPill ${i.m === 'DOS' ? 'k' : ''}">${i.m} ${E(i.b === 'ALL' ? 'SEMUA' : i.b)} ${i.w}%</span>`).join(' ');
  function listHtml() {
    const q = S.q.toLowerCase();
    const rows = KK.people.filter(p => (!S.role || p.role === S.role) && (!S.only || p.config) && (!q || (p.nama + ' ' + p.area + ' ' + p.nik).toLowerCase().includes(q)));
    const cnt = KK.people.filter(p => p.config).length;
    const status = p => !p.config ? '<span class="kkPill">BELUM DIATUR</span>' : (p.config.replace ? `<span class="kkPill k">GANTI ${E(p.config.replaces)}</span>` : '<span class="kkPill k">TAMBAHAN</span>');
    return `<div class="kkTb"><input id="kkQ" class="fi" type="search" placeholder="Cari nama atau area" value="${E(S.q)}"><select id="kkRole" class="fi"><option value="">Semua Jabatan</option><option ${S.role === 'SALES' ? 'selected' : ''}>SALES</option><option ${S.role === 'ASM' ? 'selected' : ''}>ASM</option></select><label class="kkChk"><input type="checkbox" id="kkOnly" ${S.only ? 'checked' : ''}> Hanya yang sudah diatur</label><button class="chip" type="button" data-act="copyList">Salin dari Bulan Lalu</button></div>
      <p class="hint">Periode <b>${E(KK.period)}</b> · ${cnt} akun sudah diatur · menampilkan ${Math.min(rows.length, 200)} dari ${rows.length}</p>
      ${table(['Nama', 'Jabatan', 'Area', 'Status', 'Jumlah Dealer', 'Susunan', ''], rows.slice(0, 200).map(p => [E(p.nama) + '<small class="cellsub">' + E(p.nik) + '</small>', E(p.role), E(p.area || p.region || '—'), status(p), p.config ? (p.dealers == null ? '—' : `<button type="button" class="kkLink" data-act="dl" data-nik="${E(p.nik)}">${N(p.dealers)} dealer ▾</button>`) : '—', summary(p.config), `<button class="chip ${p.config ? '' : 'primary'}" type="button" data-act="edit" data-nik="${E(p.nik)}">${p.config ? 'Ubah' : 'Atur'}</button>`]))}
      <div id="kkDl">${dlHtml()}</div>`;
  }
  function dlHtml() {
    const d = S.dl; if (!d) return '';
    if (!d.rows) return `<div class="kkBox"><p class="v6load">Memuat daftar dealer…</p></div>`;
    const q = (d.q || '').toLowerCase(), rs = d.rows.filter(r => !q || (r.nama + ' ' + r.dealer + ' ' + r.brand).toLowerCase().includes(q));
    return `<div class="kkBox"><div class="kkLn"><b class="g">Dealer yang dihitung · ${E(d.nama)} · ${d.count} dealer-brand${d.asof ? ' · per ' + E(d.asof) : ''}</b><button class="chip" type="button" data-act="dlClose">Tutup</button></div><input class="fi" id="kkDlQ" type="search" placeholder="Cari dealer atau brand" value="${E(d.q || '')}" style="margin:8px 0;width:100%">
      <p class="hint">Mengikuti brand dan grade di tiap indikator SO / DOS. Dealer dengan data perlu dicek ditandai dan tidak dihitung di DOS.</p>
      ${table(['Dealer', 'Brand', 'Grade', 'Stok', 'SO', 'DOS', 'Kondisi'], rs.slice(0, 300).map(r => [E(r.nama) + '<small class="cellsub">' + E(r.dealer) + '</small>', E(r.brand), E(r.grade || '—'), N(r.stock), N(r.so), r.inDos ? D(r.dos) + (r.dos >= 1000 ? ' <span class="kkWarn" title="SO terlalu kecil">⚠ SO terlalu kecil</span>' : '') + (r.bad ? ' <span class="kkPill">CEK DATA</span>' : '') : '<span class="kkDual">—</span>', r.inDos ? E(r.cond) : '—']))}</div>`;
  }

  // ----- tab 2: editor -----
  const total = () => EDIT.items.reduce((a, i) => a + (parseFloat(i.w) || 0), 0);
  function openEdit(p) {
    const c = p.config;
    const def = [{ m: 'SO', b: 'ALL', w: 90, basis: 'MTD', cats: [] }, { m: 'DOS', b: 'ALL', w: 10, basis: 'MTD', cats: [] }];
    EDIT = { p, replace: c ? !!c.replace : p.groups.length > 0, replaces: c?.replaces || p.groups[0]?.name || '', reason: '', sim: {}, items: (c ? c.items : def).map(x => normItem(x)), prev: null };
    CP = { q: '', picked: new Set(), rules: true, over: false };
    S.tab = 'edit'; flash(null); app(); window.scrollTo?.({ top: ($('kkApp')?.getBoundingClientRect().top || 0) + window.scrollY - 80, behavior: 'smooth' });
  }
  function normItem(x) {
    const it = { ...x };
    if (it.m === 'SO' || it.m === 'DOS') { it.basis = it.basis || 'MTD'; it.cats = [...(it.cats || [])]; }
    if (it.m === 'DA') { it.mode = it.mode || 'biasa'; it.minDA = it.minDA || 2; it.must = [...(it.must || [])]; it.perProm = it.perProm || 3; }
    if (it.m === 'DOS') { it.ruleMode = it.rule ? 'custom' : 'default'; it.rule = it.rule ? toUi(it.rule) : toUi(KK.rule); }
    return it;
  }
  function tierTable(rule, ro, scope) {
    const sorted = [...rule.tiers].map((t, i) => ({ ...t, i })).sort((a, b) => a.from - b.from);
    const nextOf = i => { const idx = sorted.findIndex(t => t.i === i); const n = sorted[idx + 1]; return n ? (+n.from - 1) : null; };
    return `<div class="kkWrap"><table class="kkTier"><thead><tr><th>Dari (hari)</th><th>Sampai (hari)</th><th>Poin</th><th></th></tr></thead><tbody>${rule.tiers.map((t, i) => `<tr><td><input class="fi" type="number" min="0" step="1" value="${t.from}" ${ro ? 'disabled' : ''} data-f="tier" data-s="${scope}" data-r="${i}" data-tf="from"></td><td><input class="fi" value="${nextOf(i) == null ? 'tak terbatas' : nextOf(i)}" disabled></td><td><input class="fi" type="number" step="0.5" value="${t.pts}" ${ro ? 'disabled' : ''} data-f="tier" data-s="${scope}" data-r="${i}" data-tf="pts"></td><td>${ro || rule.tiers.length < 2 ? '' : `<button type="button" class="kkX" data-act="tierDel" data-s="${scope}" data-r="${i}">✕</button>`}</td></tr>`).join('')}
      <tr><td colspan="2"><b>Stok 0</b></td><td><input class="fi" type="number" step="0.5" value="${rule.stok0}" ${ro ? 'disabled' : ''} data-f="tsp" data-s="${scope}" data-k="stok0"></td><td></td></tr>
      <tr><td colspan="2"><b>Ada stok tanpa SO</b></td><td><input class="fi" type="number" step="0.5" value="${rule.noSo}" ${ro ? 'disabled' : ''} data-f="tsp" data-s="${scope}" data-k="noSo"></td><td></td></tr></tbody></table></div>${ro ? '' : `<button class="chip" type="button" data-act="tierAdd" data-s="${scope}" style="margin-top:6px">+ Tambah Baris</button>`}`;
  }
  const chipsHtml = (list, picked, i, k, empty) => list.length ? `<div class="kkChips">${list.map(g => `<button type="button" data-act="chip" data-i="${i}" data-k="${k}" data-v="${E(g)}" class="${picked.includes(g) ? 'on' : ''}">${E(g)}</button>`).join('')}</div>` : `<div class="kkNote">${empty}</div>`;
  function itemHtml(it, i) {
    const brands = KK.brands, b = it.b;
    const head = `<div class="kkLn"><span class="kkTy ${it.m}">${it.m}</span><select class="fi g" data-f="b" data-i="${i}"><option value="ALL" ${b === 'ALL' ? 'selected' : ''}>Semua Brand</option>${brands.map(x => `<option ${b === x ? 'selected' : ''}>${E(x)}</option>`).join('')}</select><input class="fi" style="width:80px" type="number" min="0" step="0.5" data-f="w" data-live="1" data-i="${i}" value="${it.w}" aria-label="Bobot"><span class="nt">%</span>${it.m === 'SO' || it.m === 'DOS' ? `<select class="fi" data-f="basis" data-i="${i}"><option value="MTD" ${it.basis !== 'R30' ? 'selected' : ''}>Bulan Berjalan</option><option value="R30" ${it.basis === 'R30' ? 'selected' : ''}>30 Hari Terakhir</option></select>` : ''}<button type="button" class="kkX" data-act="delI" data-i="${i}" aria-label="Hapus">✕</button></div>`;
    let body = '';
    if (it.m === 'ST') body = `<small class="nt">Target diisi di Edit Target (kolom ST reguler) · semua dealer, tanpa filter grade.</small>`;
    if (it.m === 'SO') body = `<div><span class="kkLbl" style="margin:0 0 4px">Grade dealer</span>${chipsHtml(gradesFor(b), it.cats, i, 'cats', 'Master Grade belum terisi untuk brand ini, semua dealer ikut dihitung.')}${it.cats.length ? '' : (gradesFor(b).length ? '<small class="nt">Tidak ada yang dipilih = semua grade.</small>' : '')}</div><small class="nt">Target SO diisi di tab Target SO.</small>`;
    if (it.m === 'DA') {
      const idNote = it.mode === 'biasa' && +it.minDA === 2 ? 'Target: kolom DA reguler di Edit Target.' : 'Target: kolom KHUSUS otomatis di Edit Target.';
      body = `<div class="kkLn"><small class="nt">Min unit</small><input class="fi" style="width:70px" type="number" min="1" step="1" data-f="minDA" data-i="${i}" value="${it.minDA}"><small class="nt">Aturan hitung</small><select class="fi" data-f="mode" data-i="${i}"><option value="biasa" ${it.mode === 'biasa' ? 'selected' : ''}>DA BIASA</option><option value="daq" ${it.mode === 'daq' ? 'selected' : ''}>DAQ · TYPE WAJIB</option><option value="iot" ${it.mode === 'iot' ? 'selected' : ''}>DA IOT · PER PROMOTOR</option></select>${it.mode === 'iot' ? `<small class="nt">Min unit ACC per promotor</small><input class="fi" style="width:70px" type="number" min="1" step="1" data-f="perProm" data-i="${i}" value="${it.perProm}">` : ''}</div>`
        + (it.mode === 'daq' ? `<div><span class="kkLbl" style="margin:0 0 4px">Type wajib (minimal 1 unit dari type yang dipilih)</span>${chipsHtml(typesFor(b), it.must, i, 'must', 'Tidak ada type untuk brand ini.')}</div>` : '')
        + `<small class="nt">${it.mode === 'iot' ? (b === 'ALL' ? '<b style="color:#B3203A">DA IoT wajib memilih satu brand tertentu.</b> ' : 'Batas dealer = angka per promotor × jumlah promotor (tanpa promotor = 1×). ') : it.mode === 'daq' ? 'Dealer dihitung kalau total unit ≥ Min unit dan ada minimal 1 unit dari type wajib. ' : 'Dealer aktif kalau beli minimal sekian unit brand ini. '}${idNote}</small>`;
    }
    if (it.m === 'DOS') {
      const r = ruleOf(i), mx = ruleMax(r), ok = Math.abs((+it.w || 0) - mx) < 1e-9, sim = EDIT.sim[i] ?? 45;
      body = `<div><span class="kkLbl" style="margin:0 0 4px">Grade dealer</span>${chipsHtml(gradesFor(b), it.cats, i, 'cats', 'Master Grade belum terisi untuk brand ini, semua dealer ikut dihitung.')}${it.cats.length || !gradesFor(b).length ? '' : '<small class="nt">Tidak ada yang dipilih = semua grade.</small>'}</div>
        <div class="kkLn"><label class="kkChk"><input type="radio" name="rm${i}" data-f="ruleMode" data-i="${i}" value="default" ${it.ruleMode !== 'custom' ? 'checked' : ''}> Pakai aturan default</label><label class="kkChk"><input type="radio" name="rm${i}" data-f="ruleMode" data-i="${i}" value="custom" ${it.ruleMode === 'custom' ? 'checked' : ''}> Atur sendiri</label><span class="${ok ? 'kkOk' : 'kkWarn'}" id="kkMx${i}">${ok ? 'Bobot sesuai poin maks +' + mx : 'Poin maks +' + mx + ', bobot ' + (+it.w || 0)}</span></div>
        ${tierTable(r, it.ruleMode !== 'custom', i)}
        <div class="kkSim">Simulasi: DOS <input class="fi" type="number" min="0" data-f="sim" data-i="${i}" value="${sim}"> hari → <b id="kkSim${i}">${SIGN(simPts(r, +sim))} poin</b></div>`;
    }
    return `<div class="kkInd ${it.m === 'DOS' ? 'dos' : ''}">${head}${body}</div>`;
  }
  function totalHtml() { const t = total(), ok = Math.abs(t - 100) < 1e-4; return `<div class="kkTot ${ok ? '' : 'bad'}" id="kkTot"><span>Total Bobot (ST + SO + DA + DOS)</span><span>${+t.toFixed(2)}% ${ok ? '✓' : '(harus 100%)'}</span></div>`; }
  function cpHtml() {
    const p = EDIT.p; if (!p.config) return '<div class="kkNote">Simpan konfigurasi dulu, setelah itu bisa disalin ke ' + (p.role === 'ASM' ? 'ASM' : 'sales') + ' lain.</div>';
    const q = CP.q.toLowerCase(), list = KK.people.filter(x => x.role === p.role && x.nik !== p.nik && (!q || (x.nama + ' ' + x.area).toLowerCase().includes(q))).slice(0, 150);
    return `<div class="kkBox"><b>Salin indikator ke ${p.role === 'ASM' ? 'ASM' : 'sales'} lain</b>
      <div class="kkLn" style="margin:8px 0"><input class="fi g" id="kkCpQ" type="search" placeholder="Cari nama atau area" value="${E(CP.q)}"><button class="chip" type="button" data-act="pickAll">Pilih semua yang tampil</button><button class="chip" type="button" data-act="pickNone">Kosongkan</button></div>
      <div class="kkPick">${list.map(x => `<label><input type="checkbox" data-f="pick" data-nik="${E(x.nik)}" ${CP.picked.has(x.nik) ? 'checked' : ''}> ${E(x.nama)} <small class="nt">· ${E(x.area || x.region || '')}</small>${x.config ? ' <span class="kkWarn">SUDAH ADA</span>' : ''}</label>`).join('') || '<small class="nt">Tidak ada yang cocok.</small>'}</div>
      <label class="kkChk" style="margin-top:8px"><input type="checkbox" data-f="cpRules" ${CP.rules ? 'checked' : ''}> Salin aturan poin DOS yang "Atur sendiri"</label>
      <div class="kkLn" style="margin-top:6px"><small class="nt">Kalau tujuan sudah punya KPI Khusus:</small><select class="fi" data-f="cpOver"><option value="0" ${CP.over ? '' : 'selected'}>Lewati</option><option value="1" ${CP.over ? 'selected' : ''}>Timpa</option></select></div>
      <div class="kkNote">Target SO tidak ikut disalin (isi di tab Target SO). Bobot dan indikator mengikuti konfigurasi yang tersimpan, bukan yang sedang diubah di layar.</div>
      <button class="chip primary" type="button" data-act="copyTo" id="kkCpGo" ${CP.picked.size ? '' : 'disabled'}>Salin ke ${CP.picked.size} ${p.role === 'ASM' ? 'ASM' : 'sales'}</button></div>`;
  }
  function editHtml() {
    if (!EDIT) return '<div class="kkNote">Pilih orang di tab Daftar Orang, lalu klik Atur atau Ubah.</div>';
    const p = EDIT.p, noGroups = !p.groups.length;
    const nGroups = p.groups.length, effTxt = EDIT.replace ? `Kelompok <b>${E(EDIT.replaces || '—')}</b> diganti kelompok KPI Khusus. KPI akhir = rata-rata <b>${nGroups}</b> kelompok.` : `Kelompok KPI Khusus <b>ditambahkan</b>. KPI akhir = rata-rata <b>${nGroups + 1}</b> kelompok${nGroups ? `, bobot tiap kelompok lain turun dari ${+(100 / Math.max(nGroups, 1)).toFixed(1)}% jadi ${+(100 / (nGroups + 1)).toFixed(1)}%` : ''}.`;
    const dosMax = EDIT.items.filter(i => i.m === 'DOS').reduce((a, i) => a + ruleMax(i.ruleMode === 'custom' ? i.rule : toUi(KK.rule)), 0);
    return `<div class="kkLn"><button class="chip" type="button" data-act="back">← Kembali ke Daftar</button></div>
      <h3 style="margin:10px 0 2px">KPI Khusus · ${E(p.nama)}</h3><p class="hint">${E(p.role)} · ${E(p.area || p.region || '')} · periode ${E(KK.period)}${p.role === 'ASM' ? ' · dealer = seluruh dealer sales di timnya' : ''}</p>
      <label class="kkChk" style="background:#EEF2FF;border-radius:12px;padding:10px 12px;margin:8px 0"><input type="checkbox" data-f="replace" ${EDIT.replace ? 'checked' : ''} ${noGroups ? 'disabled' : ''}> <span><b>Gantikan kelompok KPI regional</b><br><small class="nt">${noGroups ? 'Kebijakan KPI region untuk periode ini belum diisi, jadi hanya bisa ditambahkan sebagai kelompok baru.' : 'Dicentang: menggantikan satu kelompok regional. Tidak dicentang: ditambahkan sebagai kelompok baru.'}</small></span></label>
      ${EDIT.replace && !noGroups ? `<label class="kkLbl">Kelompok yang digantikan</label><select class="fi" data-f="replaces" style="width:100%">${p.groups.map(g => `<option ${g.name === EDIT.replaces ? 'selected' : ''}>${E(g.name)}</option>`).join('')}</select>` : ''}
      <div class="kkNote" id="kkEff" style="background:#EEF4FD;border-color:#D7E2F0;color:#1E3A66">${effTxt}</div>
      <label class="kkLbl">Indikator dan bobot · total 100%</label>
      ${EDIT.items.map(itemHtml).join('')}
      <div class="kkLn" style="margin:8px 0"><button class="chip" type="button" data-act="addI" data-m="ST">+ Tambah ST</button><button class="chip" type="button" data-act="addI" data-m="SO">+ Tambah SO</button><button class="chip" type="button" data-act="addI" data-m="DA">+ Tambah DA</button><button class="chip" type="button" data-act="addI" data-m="DOS">+ Tambah DOS</button></div>
      ${totalHtml()}
      <div class="kkNote">Skor kelompok = jumlah (capaian × bobot) untuk ST, SO, dan DA, ditambah poin semua baris DOS (dijumlahkan langsung dari tabel). Minimal 0. Indikator tanpa target tidak dihitung dan bobotnya dibagi ulang.${dosMax ? ` Poin DOS maksimal gabungan saat ini: <b>+${+dosMax.toFixed(2)}</b>.` : ''}</div>
      <label class="kkLbl">Alasan perubahan</label><input class="fi" id="kkReason" data-live="reason" placeholder="Minimal 5 karakter" value="${E(EDIT.reason)}" style="width:100%">
      <div class="kkLn" style="margin-top:10px"><button class="chip" type="button" data-act="prev">Pratinjau</button><button class="chip primary" type="button" data-act="save">Simpan</button>${p.config ? '<button class="chip" type="button" data-act="del" style="color:#B3203A">Hapus Konfigurasi</button>' : ''}</div>
      <div id="kkMsg">${EDIT.prev || ''}</div>
      ${cpHtml()}`;
  }
  const payload = () => ({ replace: EDIT.replace, replaces: EDIT.replace ? EDIT.replaces : '', items: EDIT.items.map(i => {
    const o = { m: i.m, b: i.b, w: parseFloat(i.w) || 0 };
    if (i.m === 'SO' || i.m === 'DOS') { o.basis = i.basis || 'MTD'; o.cats = i.cats; }
    if (i.m === 'DA') { o.minDA = +i.minDA || 2; o.mode = i.mode; o.must = i.must; o.perProm = +i.perProm || 3; }
    if (i.m === 'DOS' && i.ruleMode === 'custom') o.rule = { tiers: i.rule.tiers.map(t => ({ from: +t.from, pts: +t.pts })), stok0: +i.rule.stok0, noSo: +i.rule.noSo };
    return o;
  }) });
  const msg = (h, bad) => { EDIT.prev = h ? `<div class="${bad ? 'kkNote bad' : 'kkPrevBox'}">${h}</div>` : ''; const m = $('kkMsg'); if (m) m.innerHTML = EDIT.prev; };
  async function doPreview() {
    msg('Menghitung pratinjau…');
    try {
      const r = await rpc('apiKpiKhususPreview', TOKEN, KK.period, EDIT.p.nik, payload());
      const li = r.indicators.map(i => {
        if (i.metric === 'DOS') return `<li><b>${E(i.label)}</b> · stok ${N(i.dos?.stock)} · SO ${N(i.dos?.so)} (${i.dos?.n} hari) · DOS ${D(i.dos?.days)} hari · <b>${i.score == null ? E(i.state) : SIGN(+i.score.toFixed(2)) + ' poin'}</b>${i.dos?.excluded ? ' · ' + i.dos.excluded + ' dealer-brand data perlu dicek (tidak dihitung)' : ''}</li>`;
        return `<li><b>${E(i.label)}</b> · aktual ${N(i.actual)} / target ${i.target ? N(i.target) : '—'}${i.ach != null ? ' = ' + D(i.ach) + '%' : ' · <i>' + E(i.state) + '</i>'}</li>`;
      }).join('');
      msg(`<b>Pratinjau per ${E(r.asof || '—')}</b> · ${r.dealerCount == null ? '—' : r.dealerCount} dealer terhitung (SO / DOS)<ul style="margin:6px 0 0 18px;padding:0">${li}</ul><p style="margin:6px 0 0"><b>Skor kelompok:</b> ${r.groupScore == null ? '— (belum ada indikator yang punya target)' : D(r.groupScore)} · poin DOS ${SIGN(+(r.dosPoints || 0).toFixed(2))}</p>`);
    } catch (e) { msg(E(e.message || e), true); }
  }
  const reasonVal = () => ($('kkReason')?.value || EDIT.reason || '').trim();

  // ----- tab 3: aturan poin DOS default -----
  function ruleHtml() {
    if (!RL) RL = { draft: toUi(KK.rule), sim: 45 };
    const r = RL.draft;
    return `<div class="kkBox"><b>Aturan Poin DOS · Default · periode ${E(KK.period)}</b><p class="hint" style="margin:4px 0 8px">Dipakai semua baris DOS yang memilih "Pakai aturan default". Berlaku per periode. ${KK.ruleStored ? '' : '<span class="kkWarn">BAWAAN, belum disimpan untuk periode ini</span>'}</p>
      ${tierTable(r, false, 'rule')}
      <div class="kkSim" style="margin-top:8px">Simulasi: DOS <input class="fi" type="number" min="0" data-f="rsim" value="${RL.sim}"> hari → <b id="kkRsim">${SIGN(simPts(r, +RL.sim))} poin</b></div>
      <label class="kkLbl">Alasan perubahan</label><input class="fi" id="kkRuleReason" placeholder="Minimal 5 karakter" style="width:100%">
      <div class="kkLn" style="margin-top:10px"><button class="chip primary" type="button" data-act="ruleSave">Simpan</button><button class="chip" type="button" data-act="ruleCopy">Salin dari Bulan Lalu</button><button class="chip" type="button" data-act="ruleReset">Kembalikan ke Tersimpan</button></div><div id="kkRuleMsg"></div>
      <p class="hint" style="margin-top:8px">"Dari (hari)" adalah batas bawah. Satu baris berlaku sampai sebelum baris berikutnya, jadi rentang tidak pernah bolong atau tumpang tindih.</p></div>`;
  }

  // ----- tab 4: target SO -----
  async function loadTargets() {
    TG = { rows: null, dirty: {}, q: '', brand: '', empty: false, picked: new Set(), bulk: '' }; app();
    try { const r = await rpc('apiKpiKhususTargets', TOKEN, KK.period); TG.rows = r.rows; } catch (e) { flash(E(e.message || e), true); TG.rows = []; }
    app();
  }
  const tkey = r => r.nik + '|' + r.brand;
  const tval = r => (tkey(r) in TG.dirty) ? TG.dirty[tkey(r)] : (r.value == null ? '' : String(r.value));
  function targetHtml() {
    if (!TG) { loadTargets(); return '<p class="v6load">Memuat…</p>'; }
    if (!TG.rows) return '<p class="v6load">Memuat…</p>';
    const q = TG.q.toLowerCase(), brands = [...new Set(TG.rows.map(r => r.brand))].sort();
    const rows = TG.rows.filter(r => (!TG.brand || r.brand === TG.brand) && (!TG.empty || tval(r) === '') && (!q || (r.nama + ' ' + r.area + ' ' + r.asm).toLowerCase().includes(q)));
    const dirty = Object.keys(TG.dirty).length, srcPill = r => r.source === 'MANUAL' ? '<span class="kkPill k">MANUAL</span>' : r.source === 'UPLOAD' ? '<span class="kkPill">DARI UPLOAD</span>' : r.source === 'OTOMATIS' ? `<span class="kkPill">OTOMATIS · ${r.members} SALES</span>` : '<span class="kkWarn">BELUM DIISI</span>';
    return `<div class="kkBox"><b>Target SO · periode ${E(KK.period)}</b><p class="hint" style="margin:4px 0 8px">Hanya orang yang punya indikator SO di KPI Khusus. Satu baris = satu orang dan satu brand. Satuan: unit. Yang diisi di sini menang atas unggahan kolom "SO &lt;BRAND&gt;".</p>
      <div class="kkTb"><input id="kkTq" class="fi" type="search" placeholder="Cari nama, area, atau ASM" value="${E(TG.q)}"><select id="kkTb" class="fi"><option value="">Semua Brand</option>${brands.map(b => `<option ${b === TG.brand ? 'selected' : ''}>${E(b)}</option>`).join('')}</select><label class="kkChk"><input type="checkbox" id="kkTe" ${TG.empty ? 'checked' : ''}> Hanya yang belum diisi</label></div>
      <div class="kkBox" style="margin:6px 0"><div class="kkLn">Isi target <input class="fi" id="kkBulk" type="number" min="0" placeholder="mis. 100" style="width:110px" value="${E(TG.bulk)}"> unit untuk baris yang dicentang <button class="chip primary" type="button" data-act="tgApply">Terapkan</button><button class="chip" type="button" data-act="tgAll">Centang semua yang tampil</button><button class="chip" type="button" data-act="tgNone">Kosongkan centang</button><button class="chip" type="button" data-act="tgCopy" style="margin-left:auto">Salin dari Bulan Lalu</button></div></div>
      <div class="kkWrap"><table class="kkTier" style="min-width:680px"><thead><tr><th></th><th>Nama</th><th>Jabatan</th><th>Area</th><th>Brand</th><th>Target (unit)</th><th>Sumber</th></tr></thead><tbody>${rows.slice(0, 400).map(r => `<tr><td>${r.role === 'SALES' ? `<input type="checkbox" data-f="tpick" data-k="${E(tkey(r))}" ${TG.picked.has(tkey(r)) ? 'checked' : ''}>` : ''}</td><td>${E(r.nama)}</td><td>${E(r.role)}</td><td>${E(r.area || r.region || '—')}</td><td>${E(r.brand)}</td><td><input class="fi" style="width:110px" type="number" min="0" step="1" data-f="tval" data-k="${E(tkey(r))}" value="${E(tval(r))}" placeholder="kosong" ${r.role === 'ASM' ? 'disabled' : ''}></td><td>${tkey(r) in TG.dirty ? '<span class="kkWarn">BELUM DISIMPAN</span>' : srcPill(r)}</td></tr>`).join('') || '<tr><td colspan="7">Belum ada orang dengan indikator SO.</td></tr>'}</tbody></table></div>
      <p class="hint">${rows.length} baris${rows.length > 400 ? ' (400 pertama ditampilkan)' : ''} · baris ASM otomatis dari jumlah target sales di timnya.</p>
      <label class="kkLbl">Alasan perubahan</label><input class="fi" id="kkTgReason" placeholder="Minimal 5 karakter" style="width:100%">
      <div class="kkLn" style="margin-top:10px"><button class="chip primary" type="button" data-act="tgSave" ${dirty ? '' : 'disabled'}>Simpan ${dirty ? dirty + ' perubahan' : ''}</button></div></div>`;
  }

  // ----- event -----
  async function reloadAll(f) { window.kkReset(); await kkLoad(); if (f) { flash(f); app(); } if (typeof refresh === 'function') refresh(); }
  function onInput(e) {
    const t = e.target, f = t.dataset.f, live = t.dataset.live;
    if (live === 'reason' && EDIT) { EDIT.reason = t.value; return; }
    if (f === 'w' && EDIT) { const i = +t.dataset.i; EDIT.items[i].w = parseFloat(t.value) || 0; const tot = $('kkTot'); if (tot) tot.outerHTML = totalHtml(); if (EDIT.items[i].m === 'DOS') { const r = ruleOf(i), mx = ruleMax(r), ok = Math.abs((+t.value || 0) - mx) < 1e-9, pill = $('kkMx' + i); if (pill) { pill.className = ok ? 'kkOk' : 'kkWarn'; pill.textContent = ok ? 'Bobot sesuai poin maks +' + mx : 'Poin maks +' + mx + ', bobot ' + (+t.value || 0); } } return; }
    if (t.id === 'kkQ') { S.q = t.value; const b = $('kkTabBody'); const pos = t.selectionStart; b.innerHTML = listHtml(); const n = $('kkQ'); n.focus(); n.setSelectionRange(pos, pos); return; }
    if (t.id === 'kkDlQ' && S.dl) { S.dl.q = t.value; const pos = t.selectionStart; $('kkDl').innerHTML = dlHtml(); const n = $('kkDlQ'); n.focus(); n.setSelectionRange(pos, pos); return; }
    if (t.id === 'kkCpQ' && CP) { CP.q = t.value; const pos = t.selectionStart; const keep = $('kkReason')?.value; const holder = t.closest('.kkBox'); holder.outerHTML = cpHtml(); const n = $('kkCpQ'); n.focus(); n.setSelectionRange(pos, pos); return; }
    if (t.id === 'kkTq' && TG) { TG.q = t.value; const pos = t.selectionStart; $('kkTabBody').innerHTML = targetHtml(); const n = $('kkTq'); n.focus(); n.setSelectionRange(pos, pos); return; }
    if (f === 'tval' && TG) { const k = t.dataset.k; TG.dirty[k] = t.value; const b = document.querySelector('[data-act="tgSave"]'); if (b) { const n = Object.keys(TG.dirty).length; b.disabled = !n; b.textContent = 'Simpan ' + n + ' perubahan'; } return; }
    if (f === 'rsim' && RL) { RL.sim = t.value; $('kkRsim').textContent = SIGN(simPts(RL.draft, +t.value)) + ' poin'; return; }
    if (f === 'sim' && EDIT) { const i = +t.dataset.i; EDIT.sim[i] = t.value; $('kkSim' + i).textContent = SIGN(simPts(ruleOf(i), +t.value)) + ' poin'; return; }
  }
  function onChange(e) {
    const t = e.target, f = t.dataset.f; if (t.id === 'kkRole') { S.role = t.value; app(); return; } if (t.id === 'kkOnly') { S.only = t.checked; app(); return; }
    if (t.id === 'kkTb' && TG) { TG.brand = t.value; app(); return; } if (t.id === 'kkTe' && TG) { TG.empty = t.checked; app(); return; }
    if (!f) return;
    const i = +t.dataset.i, it = EDIT && EDIT.items[i];
    if (f === 'replace') { EDIT.replace = t.checked; if (t.checked && !EDIT.replaces) EDIT.replaces = EDIT.p.groups[0]?.name || ''; app(); }
    else if (f === 'replaces') { EDIT.replaces = t.value; app(); }
    else if (f === 'b') { it.b = t.value; it.cats = []; it.must = []; app(); }
    else if (f === 'basis') { it.basis = t.value; app(); }
    else if (f === 'minDA') { it.minDA = Math.max(1, parseInt(t.value) || 1); app(); }
    else if (f === 'mode') { it.mode = t.value; it.must = []; app(); }
    else if (f === 'perProm') { it.perProm = Math.max(1, num0(t.value)); app(); }
    else if (f === 'ruleMode') { it.ruleMode = t.value; if (t.value === 'custom' && !it.rule) it.rule = toUi(KK.rule); else if (t.value === 'custom') it.rule = it.rule || toUi(KK.rule); app(); }
    else if (f === 'tier') { const r = t.dataset.s === 'rule' ? RL.draft : EDIT.items[+t.dataset.s].rule, row = r.tiers[+t.dataset.r]; row[t.dataset.tf] = num0(t.value); r.tiers.sort((a, b) => a.from - b.from); app(); }
    else if (f === 'tsp') { const r = t.dataset.s === 'rule' ? RL.draft : EDIT.items[+t.dataset.s].rule; r[t.dataset.k] = num0(t.value); app(); }
    else if (f === 'pick') { t.checked ? CP.picked.add(t.dataset.nik) : CP.picked.delete(t.dataset.nik); const go = $('kkCpGo'); if (go) { go.disabled = !CP.picked.size; go.textContent = 'Salin ke ' + CP.picked.size + ' ' + (EDIT.p.role === 'ASM' ? 'ASM' : 'sales'); } }
    else if (f === 'cpRules') CP.rules = t.checked; else if (f === 'cpOver') CP.over = t.value === '1';
    else if (f === 'tpick') { t.checked ? TG.picked.add(t.dataset.k) : TG.picked.delete(t.dataset.k); }
    else if (f === 'tval') { /* ditangani oleh input */ }
  }
  async function onClick(e) {
    const root = $('kkApp'), tb = e.target.closest('[data-kktab]'); if (tb && root.contains(tb)) { S.tab = tb.dataset.kktab; flash(null); if (S.tab === 'target' && !TG) loadTargets(); else app(); return; }
    const b = e.target.closest('[data-act]'); if (!b || !root.contains(b)) return; const a = b.dataset.act, i = +b.dataset.i;
    try {
      if (a === 'edit') { openEdit(KK.people.find(p => p.nik === b.dataset.nik)); }
      else if (a === 'back') { S.tab = 'list'; flash(null); app(); }
      else if (a === 'dl') {
        const p = KK.people.find(x => x.nik === b.dataset.nik); if (S.dl && S.dl.nik === p.nik) { S.dl = null; app(); return; }
        S.dl = { nik: p.nik, nama: p.nama, rows: null, count: 0, q: '' }; app();
        const r = await rpc('apiKpiKhususDealerList', TOKEN, KK.period, p.nik); if (S.dl && S.dl.nik === p.nik) { S.dl.rows = r.dealers; S.dl.count = r.count; S.dl.asof = r.asof; if ($('kkDl')) $('kkDl').innerHTML = dlHtml(); }
      }
      else if (a === 'dlClose') { S.dl = null; app(); }
      else if (a === 'addI') { const m = b.dataset.m; EDIT.items.push(normItem({ m, b: 'ALL', w: 0 })); app(); }
      else if (a === 'delI') { EDIT.items.splice(i, 1); app(); }
      else if (a === 'chip') { const it = EDIT.items[i], k = b.dataset.k, v = b.dataset.v; it[k] = it[k].includes(v) ? it[k].filter(x => x !== v) : [...it[k], v]; app(); }
      else if (a === 'tierAdd') { const r = b.dataset.s === 'rule' ? RL.draft : EDIT.items[+b.dataset.s].rule; const mx = Math.max(...r.tiers.map(t => +t.from)); r.tiers.push({ from: mx + 30, pts: 0 }); app(); }
      else if (a === 'tierDel') { const r = b.dataset.s === 'rule' ? RL.draft : EDIT.items[+b.dataset.s].rule; r.tiers.splice(+b.dataset.r, 1); app(); }
      else if (a === 'prev') await doPreview();
      else if (a === 'save') {
        const reason = reasonVal(); msg(''); b.disabled = true;
        try { await rpc('apiKpiKhususSave', TOKEN, KK.period, EDIT.p.nik, payload(), reason); const nm = EDIT.p.nama; EDIT = null; S.tab = 'list'; await reloadAll('Tersimpan: KPI Khusus ' + E(nm) + '.'); }
        catch (err) { msg(E(err.message || err), true); b.disabled = false; }
      }
      else if (a === 'del') {
        const reason = reasonVal(); if (reason.length < 5) { msg('Isi alasan minimal 5 karakter untuk menghapus.', true); return; } b.disabled = true;
        try { await rpc('apiKpiKhususDelete', TOKEN, KK.period, EDIT.p.nik, reason); const nm = EDIT.p.nama; EDIT = null; S.tab = 'list'; await reloadAll('Dihapus: KPI Khusus ' + E(nm) + '.'); }
        catch (err) { msg(E(err.message || err), true); b.disabled = false; }
      }
      else if (a === 'pickAll') { const q = CP.q.toLowerCase(); KK.people.filter(x => x.role === EDIT.p.role && x.nik !== EDIT.p.nik && (!q || (x.nama + ' ' + x.area).toLowerCase().includes(q))).slice(0, 150).forEach(x => CP.picked.add(x.nik)); $('kkCpGo').closest('.kkBox').outerHTML = cpHtml(); }
      else if (a === 'pickNone') { CP.picked.clear(); $('kkCpGo').closest('.kkBox').outerHTML = cpHtml(); }
      else if (a === 'copyTo') {
        const reason = reasonVal(); if (reason.length < 5) { msg('Isi alasan perubahan (minimal 5 karakter) di atas sebelum menyalin.', true); window.scrollTo?.({ top: $('kkReason').getBoundingClientRect().top + window.scrollY - 120, behavior: 'smooth' }); return; }
        b.disabled = true;
        try { const r = await rpc('apiKpiKhususCopyTo', TOKEN, KK.period, EDIT.p.nik, [...CP.picked], { rules: CP.rules, overwrite: CP.over }, reason); CP.picked.clear(); window.kkReset(); await kkLoad(); const p2 = KK.people.find(x => x.nik === EDIT.p.nik); if (p2) EDIT.p = p2; flash('Disalin ke ' + r.copied + ' orang.' + (r.skipped?.length ? ' Dilewati: ' + r.skipped.map(E).join('; ') : ''), r.copied === 0); app(); }
        catch (err) { msg(E(err.message || err), true); b.disabled = false; }
      }
      else if (a === 'copyList') ask('Salin KPI Khusus dari bulan lalu', `Salin semua konfigurasi dari <b>${prevPer(KK.period)}</b> ke <b>${E(KK.period)}</b>. Akun yang sudah diatur di ${E(KK.period)} tidak ditimpa.`, async reason => { const r = await rpc('apiKpiKhususCopy', TOKEN, prevPer(KK.period), KK.period, reason); await reloadAll('Disalin ' + r.copied + ' konfigurasi.' + (r.skipped?.length ? ' Dilewati: ' + r.skipped.map(E).join('; ') : '')); });
      else if (a === 'askNo') { S.ask = null; app(); }
      else if (a === 'askOk') {
        const reason = ($('kkAskReason')?.value || '').trim(); if (reason.length < 5) { flash('Isi alasan minimal 5 karakter.', true); app(); return; }
        const fn = S.ask.fn; S.ask = null; flash('Memproses…'); app();
        try { await fn(reason); } catch (err) { flash(E(err.message || err), true); app(); }
      }
      else if (a === 'ruleSave') {
        const reason = ($('kkRuleReason')?.value || '').trim(), m = $('kkRuleMsg');
        try { const r = await rpc('apiKpiKhususRuleSave', TOKEN, KK.period, { tiers: RL.draft.tiers.map(t => ({ from: +t.from, pts: +t.pts })), stok0: +RL.draft.stok0, noSo: +RL.draft.noSo }, reason); RL = null; await reloadAll('Aturan poin DOS tersimpan untuk ' + E(KK.period) + '.'); }
        catch (err) { if (m) m.innerHTML = `<div class="kkNote bad">${E(err.message || err)}</div>`; }
      }
      else if (a === 'ruleCopy') ask('Salin aturan poin DOS dari bulan lalu', `Salin tabel poin dari <b>${prevPer(KK.period)}</b> ke <b>${E(KK.period)}</b> (menimpa tabel periode ini).`, async reason => { await rpc('apiKpiKhususRuleCopy', TOKEN, prevPer(KK.period), KK.period, reason); RL = null; await reloadAll('Aturan poin DOS disalin dari ' + prevPer(KK.period) + '.'); });
      else if (a === 'ruleReset') { RL = null; app(); }
      else if (a === 'tgApply') {
        const v = ($('kkBulk')?.value ?? '').trim(); TG.bulk = v; if (v === '' || !(+v >= 0)) { flash('Isi angka target yang valid.', true); app(); return; }
        if (!TG.picked.size) { flash('Centang dulu baris yang mau diisi.', true); app(); return; }
        TG.picked.forEach(k => { TG.dirty[k] = String(+v); }); flash(null); app();
      }
      else if (a === 'tgAll') { const q = TG.q.toLowerCase(); TG.rows.filter(r => r.role === 'SALES' && (!TG.brand || r.brand === TG.brand) && (!TG.empty || tval(r) === '') && (!q || (r.nama + ' ' + r.area + ' ' + r.asm).toLowerCase().includes(q))).forEach(r => TG.picked.add(tkey(r))); app(); }
      else if (a === 'tgNone') { TG.picked.clear(); app(); }
      else if (a === 'tgSave') {
        const reason = ($('kkTgReason')?.value || '').trim(); if (reason.length < 5) { flash('Isi alasan minimal 5 karakter.', true); app(); return; }
        const changes = Object.entries(TG.dirty).map(([k, v]) => { const [nik, ...br] = k.split('|'); return { nik, brand: br.join('|'), value: String(v).trim() === '' ? null : +v }; });
        b.disabled = true;
        try { await rpc('apiKpiKhususTargetSave', TOKEN, KK.period, changes, reason); TG = null; window.kkReset(); await loadTargets(); flash('Target SO tersimpan (' + changes.length + ' baris).'); app(); if (typeof refresh === 'function') refresh(); }
        catch (err) { flash(E(err.message || err), true); app(); }
      }
      else if (a === 'tgCopy') ask('Salin target SO dari bulan lalu', `Salin target SO manual dari <b>${prevPer(KK.period)}</b> ke <b>${E(KK.period)}</b>, hanya untuk sel yang masih kosong.`, async reason => { const r = await rpc('apiKpiKhususTargetCopy', TOKEN, prevPer(KK.period), KK.period, reason); TG = null; await loadTargets(); flash('Disalin ' + r.copied + ' target. Cek ulang angkanya sebelum dipakai.'); app(); });
    } catch (err) { flash(E(err.message || err), true); app(); }
  }

  // ------------------------------------------------------------------
  // Daftar DOS dealer KPI khusus (halaman DOS)
  // ------------------------------------------------------------------
  let V = null, M = { mode: 'tier', path: [], q: '', frg: '', fbr: '', fbd: '', sort: 'dos', dir: -1 };
  window.kkReset = () => { V = null; };
  const LV = ['rgm', 'asm', 'sales', 'nama'], LN = ['RGM', 'ASM', 'SALES', 'DEALER'];
  const ptsOf = d => { let p = 0; (V.rule?.tiers || []).forEach(t => { if (d >= t[0]) p = t[1]; }); return p; };
  const cond = (D0, stock) => stock <= 0 ? 'HABIS' : D0 == null ? 'TANPA SO' : (p => p > 0 ? 'AMAN' : p < 0 ? 'TINGGI' : 'WASPADA')(ptsOf(D0));
  const CC = { AMAN: 'g', WASPADA: 'y', TINGGI: 'r', HABIS: 'n', 'TANPA SO': 'r' };
  const bd = c => `<span class="bg ${CC[c] || 'n'}">${c}</span>`;
  function agg(rows) {
    let stock = 0, so = 0, perDay = 0; const ok = rows.filter(r => !r.bad);
    ok.forEach(r => { stock += r.stock; so += r.so; perDay += r.so / (r.days || 1); });
    const D0 = stock <= 0 ? 0 : perDay > 0 ? stock / perDay : null;
    return { stock, so, D: D0, dealers: new Set(rows.map(r => r.dealer)).size, bad: rows.length - ok.length };
  }
  const startLevel = () => ({ SALES: 3, ASM: 2, RGM: 1 }[V.role] ?? 0);
  const subjPts = s => { const d = (s?.dos || []).filter(x => x.score != null); return d.length ? d.reduce((a, x) => a + x.score, 0) : null; };
  function groupRows(rows, key) { const m = new Map(); rows.forEach(r => { const k = r[key] || '—'; if (!m.has(k)) m.set(k, []); m.get(k).push(r); }); return [...m.entries()].map(([n, x]) => ({ n, x, ...agg(x) })); }
  function curRows() { let rs = V.dealers; M.path.forEach((p, i) => { const k = LV[startLevel() + i]; rs = rs.filter(r => (r[k] || '—') === p); }); return rs; }
  function flat() {
    let rs = V.dealers.map(r => ({ ...r, c: r.cond }));
    const q = M.q.toLowerCase(); if (q) rs = rs.filter(r => (r.nama + ' ' + r.sales).toLowerCase().includes(q));
    if (M.frg) rs = rs.filter(r => r.rgm === M.frg); if (M.fbr) rs = rs.filter(r => r.brand === M.fbr); if (M.fbd) rs = rs.filter(r => r.c === M.fbd);
    const f = { dos: r => r.dos ?? 1e9, stok: r => r.stock, nama: r => r.nama, sales: r => r.sales, asm: r => r.asm, rgm: r => r.rgm, brand: r => r.brand }[M.sort] || (r => r.dos ?? 1e9);
    return rs.sort((a, b) => { const x = f(a), y = f(b); return (x > y ? 1 : x < y ? -1 : 0) * M.dir; });
  }
  function zoneHtml(rows) { const z = { AMAN: 0, WASPADA: 0, TINGGI: 0, HABIS: 0 }; rows.filter(r => !r.bad).forEach(r => { const c = r.cond; z[c === 'TANPA SO' ? 'TINGGI' : c]++; }); return `<div class="kkZone">${Object.entries(z).map(([k, n]) => `<span class="bg ${CC[k]}">${k} ${n}</span>`).join('')}</div>`; }
  const basisTxt = () => V.dealers.some(r => r.basis === 'R30') && V.dealers.some(r => r.basis !== 'R30') ? 'SO (Campuran)' : V.dealers[0]?.basis === 'R30' ? 'SO 30 Hari' : `SO 1–${V.days} ${monthName(V.period)}`;
  function mine() {
    const me = V.subjects.filter(s => s.nik === (typeof BOOT !== 'undefined' ? BOOT?.user?.nik : null)); if (!me.length) return '';
    const base = i => `<div class="k"><small>${E(i.label)} · bobot ${i.weight}%</small><br><b>${N(i.actual)} / ${i.target ? N(i.target) : '—'}</b> ${i.ach != null ? '· ' + D(i.ach) + '%' : '<span class="kkDual">' + E(i.state) + '</span>'}</div>`;
    return me.map(s => `<div class="kkMine">${[...(s.st || []), ...s.so, ...(s.da || [])].map(base).join('')}${s.dos.map(i => `<div class="k"><small>${E(i.label)} · jatah ${i.weight}%</small><br><b>DOS ${D(i.actual)} hari</b> · <span class="kkPt ${i.score > 0 ? 'g' : i.score < 0 ? 'r' : 'n'}">${i.score == null ? E(i.state) : SIGN(+i.score.toFixed(2)) + ' poin'}</span><br><span class="kkDual">${E(i.state === 'SEMENTARA' ? 'Sementara · ' : '')}${i.dos?.basis === 'R30' ? 'SO 30 hari' : 'SO 1–' + (i.dos?.n || '') + ' ' + monthName(V.period)}</span></div>`).join('')}</div>`).join('');
  }
  function modeBtns() { return V.role === 'SALES' ? '' : `<div class="kkMode"><button type="button" data-kkm="tier" class="${M.mode === 'tier' ? 'on' : ''}">Per Jenjang</button><button type="button" data-kkm="flat" class="${M.mode === 'flat' ? 'on' : ''}">Semua Dealer</button></div>`; }
  function render() {
    const all = agg(V.dealers), head = `<h3>DOS Dealer · KPI Khusus</h3><div class="kkSub">${E(basisTxt())}${V.partial ? ' · SEMENTARA' : ''} · hanya dealer yang ditentukan di KPI khusus · stok per ${E(V.asof || '—')}</div>
      <div class="kkTiles"><div><small>Dealer</small><b>${N(all.dealers)}</b></div><div><small>Total Stok</small><b>${N(all.stock)}</b></div><div><small>DOS Gabungan</small><b>${D(all.D)}</b></div></div>${zoneHtml(V.dealers)}${mine()}${modeBtns()}`;
    return head + (V.role === 'SALES' || M.mode === 'flat' ? flatHtml() : tierHtml());
  }
  function flatHtml() {
    const rs = flat(), rgms = [...new Set(V.dealers.map(r => r.rgm).filter(Boolean))].sort(), brands = [...new Set(V.dealers.map(r => r.brand))].sort(), th = (l, k) => `<th data-kksort="${k}" style="cursor:pointer">${l}${M.sort === k ? (M.dir < 0 ? ' ▼' : ' ▲') : ''}</th>`;
    return `<div class="kkFlt"><input class="fi" id="kkSearch" type="search" placeholder="Cari dealer atau sales" value="${E(M.q)}"><select class="fi" data-kkf="frg" ${rgms.length < 2 ? 'disabled' : ''}><option value="">Semua RGM</option>${rgms.map(x => `<option ${x === M.frg ? 'selected' : ''}>${E(x)}</option>`).join('')}</select><select class="fi" data-kkf="fbr"><option value="">Semua Brand</option>${brands.map(x => `<option ${x === M.fbr ? 'selected' : ''}>${E(x)}</option>`).join('')}</select><select class="fi" data-kkf="fbd"><option value="">Semua Kondisi</option>${['AMAN', 'WASPADA', 'TINGGI', 'HABIS', 'TANPA SO'].map(x => `<option ${x === M.fbd ? 'selected' : ''}>${x}</option>`).join('')}</select></div>
      <div class="kkScroll"><table><thead><tr>${th('Dealer', 'nama')}${th('Brand', 'brand')}${th('Sales', 'sales')}${th('ASM', 'asm')}${V.role === 'SALES' ? '' : th('RGM', 'rgm')}${th('Stok', 'stok')}<th>${E(basisTxt())}</th>${th('DOS KPI', 'dos')}<th>DOS 30 Hr</th><th>Kondisi</th></tr></thead><tbody>${rs.map(r => `<tr><td>${E(r.nama)}${r.bad ? ' <span class="bg n" title="Data stok/SO perlu dicek, tidak dihitung">CEK DATA</span>' : ''}<div class="kkDual">${E(r.dealer)}${r.grade ? ' · Grade ' + E(r.grade) : ''}</div></td><td class="l">${E(r.brand)}</td><td class="l">${E(r.sales)}</td><td class="l">${E(r.asm)}</td>${V.role === 'SALES' ? '' : `<td class="l">${E(r.rgm)}</td>`}<td>${N(r.stock)}</td><td>${N(r.so)}</td><td><b>${D(r.dos)}</b></td><td class="kkDual">${D(r.dos30)}</td><td>${bd(r.c)}</td></tr>`).join('') || '<tr><td colspan="10">Tidak ada dealer untuk pilihan ini.</td></tr>'}</tbody></table></div><p class="kkDual">${rs.length} baris · DOS KPI = stok ÷ (SO ÷ hari) sesuai basis KPI · DOS 30 Hr hanya pembanding.</p>`;
  }
  function tierHtml() {
    const L = startLevel() + M.path.length, rs = curRows(), base = V.role === 'ASM' ? 'TIM SAYA' : V.role === 'RGM' ? 'REGION SAYA' : 'NASIONAL';
    const crumb = `<div class="kkCrumb"><button type="button" data-kkc="-1">${base}</button>${M.path.map((p, i) => `<span>›</span><button type="button" data-kkc="${i}">${E(p)}</button>`).join('')}</div>`;
    if (L >= 3) return crumb + zoneHtml(rs) + flatTierTable(rs);
    const subjOf = n => L === 1 ? V.subjects.find(s => s.role === 'ASM' && s.asm === n) : L === 2 ? V.subjects.find(s => s.role === 'SALES' && s.nama === n) : null;
    const groups = groupRows(rs, LV[L]).sort((a, b) => (b.D ?? 1e9) - (a.D ?? 1e9));
    return crumb + zoneHtml(rs) + `<div class="kkScroll"><table><thead><tr><th>${LN[L]}</th><th>Dealer</th><th>Stok</th><th>${E(basisTxt())}</th><th>DOS</th><th>Poin DOS</th><th>Kondisi</th></tr></thead><tbody>${groups.map(g => { const s = subjOf(g.n), p = subjPts(s); return `<tr class="kkGo" data-kkgo="${E(g.n)}"><td>${E(g.n)} ›</td><td>${N(g.dealers)}</td><td>${N(g.stock)}</td><td>${N(g.so)}</td><td><b>${D(g.D)}</b></td><td>${p == null ? '<span class="kkDual">info</span>' : `<span class="kkPt ${p > 0 ? 'g' : p < 0 ? 'r' : 'n'}">${SIGN(+p.toFixed(2))}</span>`}</td><td>${bd(cond(g.D, g.stock))}</td></tr>`; }).join('')}</tbody></table></div>`;
  }
  function flatTierTable(rs) {
    const rows = rs.map(r => ({ ...r, c: r.cond })).sort((a, b) => (b.dos ?? 1e9) - (a.dos ?? 1e9));
    return `<div class="kkScroll"><table><thead><tr><th>Dealer</th><th>Brand</th><th>Stok</th><th>${E(basisTxt())}</th><th>DOS KPI</th><th>DOS 30 Hr</th><th>Kondisi</th></tr></thead><tbody>${rows.map(r => `<tr><td>${E(r.nama)}${r.bad ? ' <span class="bg n">CEK DATA</span>' : ''}<div class="kkDual">${E(r.dealer)}${r.grade ? ' · Grade ' + E(r.grade) : ''}</div></td><td class="l">${E(r.brand)}</td><td>${N(r.stock)}</td><td>${N(r.so)}</td><td><b>${D(r.dos)}</b></td><td class="kkDual">${D(r.dos30)}</td><td>${bd(r.c)}</td></tr>`).join('')}</tbody></table></div>`;
  }
  function paint() {
    const h = $('kkHost'); if (!h || !V) return;
    h.innerHTML = `<section class="kkCard">${render()}</section>`;
    const c = h.querySelector('.kkCard');
    c.onclick = e => {
      const t = e.target.closest('[data-kkm],[data-kkc],[data-kkgo],[data-kksort]'); if (!t) return;
      if (t.dataset.kkm) { M.mode = t.dataset.kkm; M.path = []; }
      else if (t.dataset.kkc != null) M.path = M.path.slice(0, +t.dataset.kkc + 1);
      else if (t.dataset.kkgo) M.path.push(t.dataset.kkgo);
      else if (t.dataset.kksort) { const k = t.dataset.kksort; if (M.sort === k) M.dir *= -1; else { M.sort = k; M.dir = (k === 'dos' || k === 'stok') ? -1 : 1; } }
      paint();
    };
    c.onchange = e => { const f = e.target.dataset.kkf; if (f) { M[f] = e.target.value; paint(); } };
    const q = $('kkSearch'); if (q) q.oninput = () => { M.q = q.value; const pos = q.selectionStart; paint(); const n = $('kkSearch'); if (n) { n.focus(); n.setSelectionRange(pos, pos); } };
  }
  window.kkDosCard = async function () {
    const h = $('kkHost'); if (!h) return;
    try {
      if (!V || V.period !== REPORT.period) { V = await rpc('apiKpiKhususDealers', TOKEN, REPORT.period); M.path = []; }
      if (!V.subjects.some(s => s.hasDos)) { h.innerHTML = ''; return; }
      paint();
    } catch (e) { h.innerHTML = ''; }
  };
})();
