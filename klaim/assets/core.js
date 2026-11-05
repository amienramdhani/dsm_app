/* DSM Klaim - inti: state, API, router, kerangka layar, beranda */
'use strict';
const S = { me: null, counts: {}, mode: null, L: null, testMode: false, aging: null };
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const rp = n => (n < 0 ? '-' : '') + 'Rp ' + Math.abs(Math.round(+n || 0)).toLocaleString('id-ID');
const BLN = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const tgl = d => d ? (+d.slice(8, 10)) + ' ' + BLN[+d.slice(5, 7)] + ' ' + d.slice(0, 4) : '-';
const wkt = d => d ? tgl(d.slice(0, 10)) + ' ' + d.slice(11, 19).replace(/:/g, '.') : '-';
const wktS = d => d ? (+d.slice(8, 10)) + ' ' + BLN[+d.slice(5, 7)] + ' ' + d.slice(11, 19).replace(/:/g, '.') : '-';
const today = () => new Date(Date.now() - new Date().getTimezoneOffset() * 6e4).toISOString().slice(0, 10);
const STAGE = { RGM: 'RGM', ADMIN: 'Admin', AUDIT: 'Audit', HOS: 'HOS', FM: 'Finance Manager', FT: 'Finance Transfer', PEMOHON: 'Pemohon' };
const CATCOL = { Transportasi: 'linear-gradient(135deg,#3C8DE0,#1E5FB8)', Entertain: 'linear-gradient(135deg,#8C6BE8,#6D3FD8)', Perdin: 'linear-gradient(135deg,#27B38F,#12866A)', Operasional: 'linear-gradient(135deg,#FFB020,#F07A0C)' };
const CATIC = { Transportasi: '⛽', Entertain: '🍽', Perdin: '🧳', Operasional: '🏢' };

async function api(a, data = {}, _again = false) {
  let r;
  try { r = await fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/json', ...(window.CLAIM_DSM ? { 'X-DSM-Token': (() => { try { return localStorage.getItem('sp-token') || ''; } catch (e) { return ''; } })() } : {}) }, body: JSON.stringify({ a, ...data }), credentials: 'same-origin' }); }
  catch (e) { throw new Error('Tidak dapat terhubung ke server. Periksa koneksi lalu coba lagi.'); }
  let j; try { j = await r.json(); } catch (e) { throw new Error('Respons server tidak valid.'); }
  if (!j.ok && r.status === 422 && !_again && a !== 'sign_save' && window.askSign && /Anda belum mengisi tanda tangan/.test(j.error || '')) { await askSign(); return api(a, data, true); } // popup TTD, lalu ulangi aksi tadi
  if (!j.ok) { if (r.status === 401 && S.me) { S.me = null; location.hash = '#/login'; } const er = new Error(j.error || 'Gagal.'); er.status = r.status; throw er; }
  return j;
}
function toast(m, err) { const t = document.createElement('div'); t.className = 'toast' + (err ? ' err' : ''); t.textContent = m; document.body.appendChild(t); setTimeout(() => t.remove(), err ? 4200 : 2400); }
async function guard(fn) { try { return await fn(); } catch (e) { toast(e.message, true); } }
function sheet(html, onMount) {
  const bg = document.createElement('div'); bg.className = 'sheet-bg'; bg.innerHTML = `<div class="sheet"><div class="grip"></div>${html}</div>`;
  bg.addEventListener('click', e => { if (e.target === bg) bg.remove(); }); document.body.appendChild(bg);
  if (onMount) onMount(bg.firstChild, () => bg.remove()); return bg;
}
async function compressImage(file) {
  if (!file.type.startsWith('image/') || file.size < 400000) return file;
  try {
    const bmp = await createImageBitmap(file); const max = 1600, sc = Math.min(1, max / Math.max(bmp.width, bmp.height));
    const c = document.createElement('canvas'); c.width = Math.round(bmp.width * sc); c.height = Math.round(bmp.height * sc); c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
    const b = await new Promise(r => c.toBlob(r, 'image/jpeg', 0.82)); return b && b.size < file.size ? new File([b], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file;
  } catch (e) { return file; }
}
async function uploadFile(file, slot) {
  const f = await compressImage(file); const fd = new FormData(); fd.append('a', 'upload'); fd.append('slot', slot); fd.append('file', f);
  const r = await fetch('api.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: window.CLAIM_DSM ? { 'X-DSM-Token': (() => { try { return localStorage.getItem('sp-token') || ''; } catch (e) { return ''; } })() } : {} }); const j = await r.json().catch(() => ({ ok: false, error: 'Upload gagal.' }));
  if (!j.ok) throw new Error(j.error); return j.file;
}
const fileUrl = id => 'api.php?a=file&id=' + id;
function thumbs(files, opt = {}) {
  return `<div class="thumbs">${(files || []).map(f => `<div class="th" data-view="${f.id}">${/pdf/.test(f.mime || '') ? 'PDF' : `<img loading="lazy" src="${fileUrl(f.id)}" alt="">`}${opt.rm ? `<b data-rm="${f.id}">×</b>` : ''}</div>`).join('')}${opt.add ? `<div class="th add" data-addfile="${esc(opt.add)}">+</div>` : ''}</div>`;
}
document.addEventListener('click', e => {
  const v = e.target.closest('[data-view]'); if (v && !e.target.closest('[data-rm]')) { const id = v.dataset.view; sheet(`<img src="${fileUrl(id)}" style="width:100%;border-radius:12px" alt=""><a class="btn g" style="display:block;text-align:center;margin-top:10px;text-decoration:none" href="${fileUrl(id)}" target="_blank">BUKA UKURAN PENUH</a>`); }
});

/* ---------- komponen ---------- */
const sw = (on, id) => `<div class="sw ${on ? 'on' : ''}" data-sw="${id}"><i></i></div>`;
function pemohonSel(id, curName) { return `<select id="${id}"><option value="">Semua pemohon</option>${S.L.people.map(p => `<option data-nama="${esc(p.name)}" data-role="${esc(p.job || '')}" data-region="${esc(p.region || '')}" data-nik=" " ${p.name === curName ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select>`; }
function pemohonUp(root, id) { try { const el = root.querySelector('#' + id); if (el && window.dsmPersonSelect) dsmPersonSelect(el, { title: 'Pilih orang', search: 'Cari nama / jabatan / region' }); } catch (e) {} }
function typed(id, list, value, ph, strict = true) {
  return `<input id="${id}" list="dl_${id}" value="${esc(value || '')}" placeholder="${esc(ph || 'Ketik untuk mencari')}" autocomplete="off" data-strict="${strict ? 1 : 0}" data-list="${esc(JSON.stringify(list))}"><datalist id="dl_${id}">${list.map(x => `<option value="${esc(x)}">`).join('')}</datalist>`;
}
document.addEventListener('change', e => {
  const t = e.target; if (t.tagName === 'INPUT' && t.dataset.strict === '1') { const list = JSON.parse(t.dataset.list || '[]'); if (t.value && !list.includes(t.value)) { const m = list.find(x => x.toLowerCase() === t.value.toLowerCase()); if (m) t.value = m; else { t.value = ''; t.classList.add('bad'); toast('Pilih salah satu nilai dari daftar.', true); setTimeout(() => t.classList.remove('bad'), 1500); } } }
});
function routeChips(route, cur, done) {
  const i = route.indexOf(cur);
  return `<div class="route">${route.map((s, k) => `${k ? '›' : ''}<span class="${done ? 'd' : k < i ? 'd' : k === i ? 'n' : ''}">${STAGE[s] || s}</span>`).join('')}</div>`;
}
function pill(c) {
  const l = c.label || '';
  const cls = c.status === 'DONE' ? 'st-ok' : c.status === 'REJECTED' ? 'st-no' : c.status === 'REV' ? 'st-rev' : c.status === 'DRAFT' ? 'st-draft' : /TTD BA/.test(l) ? 'st-ttd' : ({ ADMIN: 'st-adm', HOS: 'st-hos', AUDIT: 'st-aud', FM: 'st-fm', FT: 'st-ft' })[c.stage] || 'st-adm';
  return `<span class="pill st ${cls}">${esc(l)}</span>`;
}
function claimLi(r) {
  return `<div class="li" data-go="#/claim/${r.id}"><div class="dot" style="background:${CATCOL[r.category] || '#999'}">${CATIC[r.category] || '•'}</div><div class="t"><b>${esc(r.number || 'Draft')} · ${esc(r.claimant)}</b><span>${esc(r.type)} · ${esc(r.brand || '')}${r.version > 1 ? ' · v' + r.version : ''}${r.ba ? ' · BA' : ''}${r.outside ? ' · Di luar sales' : ''}</span><div style="margin-top:3px">${pill(r)} ${r.todo ? `<span class="pill p-adm">Menunggu Anda · ${esc(r.todo)}</span> ` : ''}${r.over ? `<span class="pill p-no">Over ${rp(r.over)}</span>` : ''}${agingTag(r)}</div></div><div class="r"><b>${rp(r.total)}</b></div></div>`;
}
function agingTag(r) {
  if (r.status !== 'WAIT' && r.status !== 'REV') return '';
  return ` <span class="pill ${r.aging ? 'p-rev' : 'p-gr'}">⏱ ${r.age_days} hari${r.aging ? ' · menunggu lama' : ''}</span>`;
}
const emptyBox = t => `<div class="card mut" style="text-align:center;padding:26px">${esc(t)}</div>`;
const skeleton = () => `<div class="skel"></div><div class="skel"></div><div class="skel"></div>`;

/* ---------- kerangka ---------- */
const MODES = {
  pemohon: { n: 'Pemohon', nav: [['🏠', 'Beranda', '#/home'], ['🧾', 'Klaim saya', '#/mine'], ['➕', 'Ajukan', '#/new'], ['💵', 'Petty cash', '#/pc'], ['🗓️', 'Kalender', '#/kalender'], ['☰', 'Menu', '#/menu']] },
  rgm: { n: 'RGM', nav: [['🏠', 'Beranda', '#/home'], ['📥', 'Antrean PC', '#/pcq/RGM'], ['⏳', 'Diproses', '#/pcproses'], ['🗓️', 'Kalender', '#/kalender'], ['☰', 'Menu', '#/menu']] },
  admin: { n: 'Admin', nav: [['📥', 'Antrean', '#/home'], ['📝', 'BA', '#/ba'], ['↩️', 'Revisi', '#/revisi'], ['⏳', 'Diproses', '#/proses'], ['🗓️', 'Kalender', '#/kalender'], ['☰', 'Menu', '#/menu']] },
  audit: { n: 'Audit', nav: [['🏠', 'Antrean BA', '#/home'], ['📚', 'Semua klaim', '#/all'], ['📊', 'Budget', '#/monitor'], ['⬇️', 'Export', '#/rekap'], ['🗓️', 'Kalender', '#/kalender'], ['☰', 'Menu', '#/menu']] },
  hos: { n: 'HOS', nav: [['🏠', 'Antrean', '#/home'], ['📝', 'BA', '#/ba'], ['📚', 'Semua klaim', '#/all'], ['📊', 'Budget', '#/monitor'], ['📈', 'Realisasi', '#/realisasi'], ['🗓️', 'Kalender', '#/kalender'], ['☰', 'Menu', '#/menu']] },
  fm: { n: 'Finance Manager', nav: [['🏠', 'Antrean', '#/home'], ['📝', 'BA', '#/ba'], ['⏳', 'Diproses', '#/proses'], ['📊', 'Budget', '#/monitor'], ['📈', 'Realisasi', '#/realisasi'], ['🗓️', 'Kalender', '#/kalender'], ['☰', 'Menu', '#/menu']] },
  ft: { n: 'Finance Transfer', nav: [['💸', 'Siap transfer', '#/home'], ['🔜', 'Akan datang', '#/upcoming'], ['🕘', 'Riwayat', '#/selesai'], ['📈', 'Realisasi', '#/realisasi'], ['🗓️', 'Kalender', '#/kalender'], ['☰', 'Menu', '#/menu']] },
  sa: { n: 'Super Admin', nav: [['🏠', 'Beranda', '#/home'], ['👥', 'Pengguna', '#/sa/users'], ['🔑', 'Kode', '#/sa/codes'], ['⚙️', 'Atur', '#/sa/settings'], ['☰', 'Menu', '#/menu']] },
};
function availModes() {
  const r = S.me.roles, m = [];
  if (S.me.can_submit) m.push('pemohon'); if (r.includes('RGM')) m.push('rgm');
  for (const [k, role] of [['admin', 'ADMIN'], ['audit', 'AUDIT'], ['hos', 'HOS'], ['fm', 'FM'], ['ft', 'FT'], ['sa', 'SA']]) if (r.includes(role)) m.push(k);
  return m.length ? m : ['pemohon'];
}
function curMode() { const a = availModes(); if (!S.mode || !a.includes(S.mode)) S.mode = sessionStorage.getItem('mode_' + S.me.id); if (!S.mode || !a.includes(S.mode)) S.mode = a[0]; return S.mode; }
function setMode(m) { S.mode = m; sessionStorage.setItem('mode_' + S.me.id, m); }
function shell(inner, opt = {}) {
  const mode = curMode(); const nav = MODES[mode].nav; const hash = location.hash || '#/home';
  const cur = nav.findIndex(n => n[2] === hash.split('?')[0]);
  const nb = { '#/home': 0, '#/revisi': S.counts.revisi_admin || 0, '#/pc': 0, '#/ba': S.counts['ba_' + ({ admin: 'ADMIN', hos: 'HOS', fm: 'FM' }[mode] || '')] || 0 };
  const bell = `<div class="ib" data-go="#/notif">🔔${S.counts.notif ? `<i>${S.counts.notif}</i>` : ''}</div>`;
  const modes = availModes();
  const mb = modes.length > 1 ? `<div class="modebar">${modes.map(m => `<span class="${m === mode ? 'a' : ''}" data-mode="${m}">${MODES[m].n}</span>`).join('')}</div>` : '';
  const bm = S.me.backup_role ? `<div class="testbar bk" id="bkbar"><span>Akses cadangan ${STAGE[S.me.backup_role]}${S.me.backup_for ? ' · menggantikan ' + esc(S.me.backup_for) : ''}<small>masuk ke-${S.me.backup_uses} dari ${S.me.backup_max}</small></span><b id="bkcd">⏱ --:--</b><u data-do="bkexit">Keluar</u></div>` : '';
  const im = S.me.impersonating ? `<div class="testbar" style="background:#E8F1FB;color:#14437F">Anda sedang ${S.testMode ? 'sebagai' : 'melihat sebagai'} ${esc(S.me.name)} (Super Admin${S.testMode ? '' : ' · mode lihat, tidak bisa mengubah'}) · <u data-do="unimp">kembali</u></div>` : '';
  const tm = S.testMode && S.me.roles.includes('SA') ? `<div class="testbar">MODE TES AKTIF: Super Admin boleh bertindak di semua tahap</div>` : '';
  $('#app').innerHTML = `${bm}${im}${tm}${opt.hero === false ? '' : opt.heroHtml || ''}${opt.noModes ? '' : (opt.showModes ? mb : '')}${inner}<div class="nav n${nav.length}">${nav.map((n, i) => `<div class="${i === cur ? 'a' : ''}" data-go="${n[2]}"><em>${navIcon(n[1])}</em>${n[1]}${nb[n[2]] && i !== cur ? `<span class="nb">${nb[n[2]]}</span>` : ''}</div>`).join('')}</div>`;
  window.scrollTo(0, 0); bkTick(); return bell;
}
/* ikon garis bawah, gaya DSM induk */
const NAVP = { home: 'M3 11 12 3l9 8M5 10v11h5v-7h4v7h5V10', receipt: 'M5 3h14v18l-3-2-4 2-4-2-3 2V3m4 5h6m-6 4h6', plus: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18m0 5v8m-4-4h8', wallet: 'M3 7h18v13H3zM3 7l3-4h12l3 4M16 13h2', more: 'M4 5h16M4 12h16M4 19h16',
  inbox: 'M3 13h5l2 3h4l2-3h5M5 5h14l2 8v6H3v-6z', doc: 'M6 2h9l5 5v15H6zM14 2v6h6M9 13h6m-6 4h4', undo: 'M9 14 4 9l5-5M4 9h10a6 6 0 0 1 0 12h-3', clock: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18m0 4v5l3 3', stack: 'M12 3 2 8l10 5 10-5-10-5M2 13l10 5 10-5M2 18l10 5 10-5',
  chart: 'M4 20V10m8 10V4m8 16v-7M2 22h20', down: 'M12 3v12m0 0-5-5m5 5 5-5M4 21h16', send: 'M7 7h12m0 0-4-4m4 4-4 4M17 17H5m0 0 4 4m-4-4 4-4', cal: 'M4 5h16v16H4zM4 10h16M9 3v4m6-4v4', hist: 'M3 12a9 9 0 1 0 3-6.7M3 4v5h5M12 8v4l3 2', trend: 'M3 17l6-6 4 4 8-8M15 7h6v6',
  users: 'M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8m-6 10v-3a6 6 0 0 1 12 0v3m2-17a4 4 0 0 1 0 8m2 3a5 5 0 0 1 4 5v1', key: 'M15 7a4 4 0 1 1-3.5 6L4 20H2v-3l1-1h2v-2h2l2.5-2.5A4 4 0 0 1 15 7m1.5-1.5h0', kal: 'M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2m-2 6h18M8 2v4m8-4v4M7 14h2m3 0h2m3 0h0M7 17.5h2m3 0h2', gear: 'M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6m7.4 3a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14.5 2h-5l-.4 2.6a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7 7 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 2 1.2l.4 2.6h5l.4-2.6a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2' };
const NAVK = { 'Beranda': 'home', 'Klaim saya': 'receipt', 'Ajukan': 'plus', 'Petty cash': 'wallet', 'Menu': 'more', 'Antrean': 'inbox', 'Antrean PC': 'inbox', 'Antrean BA': 'doc', 'BA': 'doc', 'Revisi': 'undo', 'Diproses': 'clock', 'Semua klaim': 'stack', 'Budget': 'chart', 'Export': 'down', 'Siap transfer': 'send', 'Akan datang': 'cal', 'Riwayat': 'hist', 'Realisasi': 'trend', 'Kalender': 'kal', 'Pengguna': 'users', 'Kode': 'key', 'Atur': 'gear' };
const navIcon = l => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${NAVP[NAVK[l]] || NAVP.more}"/></svg>`;
function hero(title, sub, opt = {}) {
  const bell = `<div class="ib" data-go="#/notif">🔔${S.counts.notif ? `<i>${S.counts.notif}</i>` : ''}</div>`;
  return `<div class="hero ${opt.small ? 's' : ''}"><div class="hrow"><div>${opt.back ? `<div class="back" data-back="1">← Kembali</div>` : ''}<small>${esc(sub || '')}</small><h2>${esc(title)}</h2></div>${opt.nobell ? '' : bell}</div>${opt.extra || ''}</div>`;
}
function page(heroHtml, inner, extraTop = '') { shell(`${heroHtml}${extraTop}${inner}`, { hero: false }); }

/* ---------- router ---------- */
const R = {};
async function route() {
  const ap = $('#app'); ap.oninput = ap.onclick = ap.onchange = null;
  const h = (location.hash || '#/home').slice(2); const [p, ...rest] = h.split('?')[0].split('/');
  if (!S.me && p !== 'login') { try { await loadMe(); } catch (e) {} }
  if (!S.me) return loginScreen();
  if (p === 'login') { location.hash = '#/home'; return; }
  const fn = R[p || 'home'] || R.home;
  $('#app').insertAdjacentHTML('beforeend', '<div class="skel" id="sk"></div>');
  window.QP = new URLSearchParams(location.hash.split('?')[1] || ''); try { await fn(...rest); } catch (e) { toast(e.message, true); if (e.status === 403 || e.status === 404) { if (p !== 'home') location.hash = '#/home'; } else $('#sk')?.remove(); }
}
let BK_END = 0;
function bkTick() {
  const el = document.getElementById('bkcd'); if (!S.me || !S.me.backup_role || !el) return;
  const left = Math.max(0, Math.round((BK_END - Date.now()) / 1000)); const h = Math.floor(left / 3600), m = Math.floor(left % 3600 / 60), sc = left % 60;
  el.textContent = '⏱ ' + (h ? String(h).padStart(2, '0') + ':' : '') + String(m).padStart(2, '0') + ':' + String(sc).padStart(2, '0');
  const bar = document.getElementById('bkbar'); bar.classList.toggle('warn', left < 600 && left >= 300); bar.classList.toggle('bad', left < 300);
  if (left <= 0) { BK_END = 0; toast('Akses cadangan berakhir.'); loadMe().then(() => { S.mode = null; location.hash = '#/home'; route(); }); }
}
setInterval(bkTick, 1000);
async function loadMe() {
  const j = await api('me'); S.me = j.me; BK_END = S.me && S.me.backup_role ? Date.now() + S.me.backup_left * 1000 : 0; setTimeout(bkTick, 0); S.dsmRole = j.dsm_role; S.counts = j.counts; S.testMode = j.test_mode; S.aging = j.aging_days;
  if (!S.L) { S.L = (await api('lookups')); }
}
async function refreshCounts() { try { const j = await api('counts'); S.counts = j.counts; } catch (e) {} }
window.addEventListener('hashchange', route);
document.addEventListener('click', async e => {
  const g = e.target.closest('[data-go]'); if (g) { location.hash = g.dataset.go; return; }
  if (e.target.closest('[data-back]')) { history.length > 1 ? history.back() : (location.hash = '#/home'); return; }
  const m = e.target.closest('[data-mode]'); if (m) { setMode(m.dataset.mode); location.hash = '#/home'; route(); return; }
  if (e.target.closest('[data-do="bkexit"]')) { await guard(async () => { await api('backup_exit'); await loadMe(); S.mode = null; toast('Keluar dari akses cadangan.'); location.hash = '#/home'; route(); }); return; }
  if (e.target.closest('[data-do="unimp"]')) { await guard(async () => { await api('switch_user', { user_id: S.me.real_id }); await loadMe(); S.mode = null; location.hash = '#/home'; route(); }); }
});

/* ---------- DSM ---------- */
function dsmLogout() {
  let t = ''; try { t = localStorage.getItem('sp-token') || ''; } catch (e) {}
  fetch('../api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ fn: 'apiLogout', args: [t] }) }).catch(() => {}).finally(() => { try { localStorage.removeItem('sp-token'); } catch (e) {} location.href = '../index.php'; });
}
/* ---------- login ---------- */
async function loginScreen() {
  if (window.CLAIM_DSM) {
    S.me = null;
    $('#app').innerHTML = `<div class="hero s" style="padding:36px 20px 40px"><small>DSM</small><h2 style="font-size:26px">Klaim</h2><div style="opacity:.9;margin-top:4px">Pengajuan, approval, dan budget biaya operasional</div></div>
    <div class="login" style="padding-top:18px"><div class="card"><div class="note red"><b>Tidak bisa membuka Klaim.</b><br>Sesi DSM habis, atau akun Anda belum diberi akses Klaim oleh Super Admin.</div><a class="cta" href="../index.php" style="display:block;text-align:center;text-decoration:none;margin-top:12px">KEMBALI KE DSM</a><button class="btn g" id="dsmout" style="width:100%;margin-top:8px">KELUAR DARI DSM</button></div></div>`;
    $('#dsmout').onclick = dsmLogout;
    return;
  }
  S.me = null; let users = []; try { users = (await api('demo_users')).users; } catch (e) {}
  $('#app').innerHTML = `<div class="hero s" style="padding:36px 20px 40px"><small>DSM</small><h2 style="font-size:26px">Klaim</h2><div style="opacity:.9;margin-top:4px">Pengajuan, approval, dan budget biaya operasional</div></div>
  <div class="login" style="padding-top:18px"><div class="card"><div class="fld"><label>Username</label><input id="lu" autocapitalize="off" autocomplete="username"></div><div class="fld"><label>Password</label><input id="lp" type="password" autocomplete="current-password"></div><button class="cta" id="lgo">MASUK</button></div>
  <div class="note blue" style="margin-top:14px">Versi uji lokal. Semua akun memakai password <b>123456</b>. Ketuk nama untuk mengisi username.</div><div class="who">${users.map(u => `<span data-u="${esc(u.username)}">${esc(u.name)} · ${esc(u.job)}</span>`).join('')}</div></div>`;
  $$('.who span').forEach(s => s.onclick = () => { $('#lu').value = s.dataset.u; $('#lp').value = '123456'; });
  const go = () => guard(async () => { const j = await api('login', { username: $('#lu').value, password: $('#lp').value }); S.me = j.me; S.mode = null; await loadMe(); location.hash = '#/home'; route(); });
  $('#lgo').onclick = go; $('#lp').onkeydown = e => { if (e.key === 'Enter') go(); };
}

/* ---------- beranda per peran (G01) ---------- */
function ring(pct, color, label) {
  const r = 34, c = 2 * Math.PI * r, d = Math.max(0, Math.min(1, pct)) * c;
  return `<svg class="ring" width="84" height="84" viewBox="0 0 84 84"><circle cx="42" cy="42" r="${r}" fill="none" stroke="#DCE5EC" stroke-width="9"/><circle cx="42" cy="42" r="${r}" fill="none" stroke="${color}" stroke-width="9" stroke-linecap="round" stroke-dasharray="${d} ${c}" transform="rotate(-90 42 42)"/><text x="42" y="47" text-anchor="middle" font-size="15" font-weight="800" fill="#10394E">${label}</text></svg>`;
}
function kcard(cls, ic, n, l, s, go) { return `<div class="k ${cls}" data-go="${go}"><div class="ic">${ic}</div><div class="n">${n ?? 0}</div><div class="l">${l}</div><div class="s">${s}</div></div>`; }
R.home = async () => {
  await loadMe(); const mode = curMode(); const c = S.counts;
  const name = esc(S.me.name.split(' ')[0]);
  let glass = '', alert = '', cards = '', list = '', sub = MODES[mode].n.toUpperCase();
  const listLoad = async f => { const j = await api('claim_list', f); return j.rows; };
  if (mode === 'pemohon') {
    const b = await api('budget'); const tot = Object.values(b.budget).reduce((a, x) => ({ budget: a.budget + (x.enabled ? x.budget : 0), av: a.av + (x.enabled ? x.available : 0), over: a.over + x.over }), { budget: 0, av: 0, over: 0 });
    const used = tot.budget ? (tot.budget - tot.av) / tot.budget : 0;
    glass = `<div class="glass"><div class="row">${ring(used, used > 1 ? '#D2453F' : '#12866A', Math.round(used * 100) + '%')}<div><div class="mut">Sisa budget bulan ini</div><div class="big" style="${tot.av < 0 ? 'color:#D2453F' : ''}">${rp(tot.av)}</div><div class="mut">dari ${rp(tot.budget)}${tot.over ? ` · <b style="color:#D2453F">Over ${rp(tot.over)}</b>` : ''}</div></div></div>
      ${Object.entries(b.budget).filter(([k, x]) => x.enabled).map(([k, x]) => { const p = x.budget ? Math.min(1, (x.realized + x.reserved) / x.budget) : 0; return `<div class="bar" style="margin:10px 0 0"><div class="rr"><span>${CATIC[k]} ${k}</span><span style="${x.available < 0 ? 'color:#D2453F' : ''}">${rp(x.available)}</span></div><div class="tr"><i style="width:${p * 100}%;background:${x.available < 0 ? '#D2453F' : '#12866A'}"></i></div></div>`; }).join('')}</div>`;
    if (b.blocked) alert = `<div class="alert" data-go="#/pc"><div>🚫</div><div><b>Pengajuan klaim diblokir</b><span>Petty cash ${esc(b.blocked.number)} melewati tenggat ${tgl(b.blocked.deadline)}</span></div><div class="go">BUKA</div></div>`;
    else if (c.mine_revisi) alert = `<div class="alert" data-go="#/mine?tab=revisi"><div>↩️</div><div><b>${c.mine_revisi} klaim perlu revisi</b><span>Perbaiki agar kembali berjalan</span></div><div class="go">BUKA</div></div>`;
    cards = kcard('g2', '⏳', c.mine_proses, 'Diproses', 'sedang berjalan', '#/mine?tab=proses') + kcard('g1', '↩️', c.mine_revisi, 'Perlu revisi', 'butuh perbaikan', '#/mine?tab=revisi') + kcard('g3', '✅', c.mine_selesai, 'Selesai', 'sudah ditransfer', '#/mine?tab=selesai') + kcard('g4', '⛔', c.mine_ditolak, 'Ditolak', 'final', '#/mine?tab=ditolak');
    list = (await listLoad({ view: 'mine', tab: 'proses', limit: 4 })).map(claimLi).join('');
  } else if (['admin', 'audit', 'hos', 'fm', 'ft'].includes(mode)) {
    const st = { admin: 'ADMIN', audit: 'AUDIT', hos: 'HOS', fm: 'FM', ft: 'FT' }[mode]; const q = c['queue_' + st] || 0; const lama = c['lama_' + st] || 0;
    const ttl = { ADMIN: 'Menunggu pemeriksaan Anda', AUDIT: 'Menunggu keputusan BA Anda', HOS: 'Menunggu persetujuan Anda', FM: 'Menunggu persetujuan Anda', FT: 'Siap ditransfer' }[st];
    glass = `<div class="glass"><div class="row">${ring(q ? Math.min(1, lama / q) : 0, lama ? '#F07A0C' : '#12866A', q)}<div><div class="mut">${ttl}</div><div class="big">${q} klaim</div><div class="mut">${S.aging != null ? `${lama} menunggu lebih dari ${S.aging} hari` : 'Batas hari "menunggu lama" belum diatur Super Admin'}</div></div></div></div>`;
    if (lama) alert = `<div class="alert" data-go="#/queue/${st}"><div>⏱</div><div><b>${lama} klaim menunggu lama</b><span>Lebih dari ${S.aging} hari di tahap Anda</span></div><div class="go">LIHAT</div></div>`;
    else if (mode === 'admin' && c.revisi_admin) alert = `<div class="alert" data-go="#/revisi"><div>↩️</div><div><b>${c.revisi_admin} revisi menunggu di Admin</b><span>Lengkapi sendiri atau lempar ke pemohon</span></div><div class="go">BUKA</div></div>`;
    const pq = c['pcq_' + st] || 0;
    cards = kcard('g1', '📥', q, 'Antrean', 'tugas Anda', '#/queue/' + st) + (mode === 'admin' ? kcard('g4', '↩️', c.revisi_admin, 'Revisi di Admin', 'perlu ditangani', '#/revisi') : kcard('g2', '⏳', '', 'Diproses', 'semua tahap', '#/proses')) +
      (mode === 'ft' ? kcard('g5', '🔜', '', 'Akan datang', 'estimasi masuk', '#/upcoming') : kcard('g3', '📚', '', 'Semua klaim', 'dalam cakupan', '#/all')) + (['admin', 'hos', 'fm', 'ft'].includes(mode) ? kcard('g5', '💵', pq, 'Petty cash', 'menunggu Anda', mode === 'hos' ? '#/pcmon' : '#/pcq/' + st) : kcard('g5', '📊', '', 'Budget', 'per orang', '#/monitor'));
    list = (await listLoad({ view: 'queue', stage: st, limit: 5 })).map(claimLi).join('');
  } else if (mode === 'rgm') {
    glass = `<div class="glass"><div class="row">${ring(0, '#12866A', c.pc_rgm || 0)}<div><div class="mut">Petty cash menunggu RGM</div><div class="big">${c.pc_rgm || 0} permintaan</div></div></div></div>`;
    cards = kcard('g1', '📥', c.pc_rgm, 'Antrean PC', 'menunggu Anda', '#/pcq/RGM') + kcard('g2', '⏳', '', 'Diproses', 'sudah Anda setujui', '#/pcproses');
    const rows = await api('pc_list', { view: 'queue', stage: 'RGM' }); list = rows.rows.map(pcLi).join('');
  } else if (mode === 'sa') {
    glass = `<div class="glass"><div class="mut">Super Admin</div><div class="big">Pengaturan dan uji</div><div class="mut">Ganti peran, atur akses, terbitkan kode cadangan.</div></div>`;
    cards = kcard('g2', '👥', '', 'Pengguna & peran', 'X01', '#/sa/users') + kcard('g1', '🔑', '', 'Kode cadangan', 'X02', '#/sa/codes') + kcard('g3', '⚙️', '', 'Pengaturan', 'N hari, mode tes', '#/sa/settings') + kcard('g4', '⛽', '', 'Harga BBM', 'Pertalite & Pertamax', '#/sa/harga') + kcard('g1', '🏢', '', 'Badan usaha', 'daftar pilihan Admin', '#/sa/bu') + kcard('g5', '🎭', '', 'Ganti peran', 'uji sebagai orang lain', '#/sa/switch');
  }
  const h = `<div class="hero"><div class="hrow"><div><small>${sub}</small><h2>Halo, ${name}</h2></div><div class="ib" data-go="#/notif">🔔${c.notif ? `<i>${c.notif}</i>` : ''}</div></div></div>`;
  shell(`${h}${availModes().length > 1 ? `<div class="modebar" style="position:relative;z-index:2;margin-top:-52px;margin-bottom:46px">${availModes().map(m => `<span class="${m === mode ? 'a' : ''}" data-mode="${m}">${MODES[m].n}</span>`).join('')}</div>` : ''}${glass}${alert}<div class="kc">${cards}</div><div class="sec"><h3>${mode === 'pemohon' ? 'Klaim terbaru' : 'Antrean teratas'}</h3>${list || emptyBox('Belum ada data')}</div>${mode === 'pemohon' ? '<div style="height:12px"></div>' : ''}`, { hero: false });
};

/* ---------- daftar + filter (G03) ---------- */
let LF = {};
async function listScreen(title, base, opt = {}) {
  const f = { ...base, ...(opt.keep ? LF[opt.key] || {} : {}) }; LF[opt.key || title] = f;
  const active = ['q', 'category', 'status', 'region', 'claimant_id', 'from', 'to'].filter(k => f[k]);
  const j = await api('claim_list', { ...f, limit: 50 });
  let oth = null; if (opt.todoFilter) { try { oth = (await api('claim_list', { ...f, todo: f.todo ? 0 : (opt.todoMine ? 'mine' : 1), limit: 1 })).total; } catch (e) { oth = null; } }
  const tabs = opt.tabs && !f.todo ? `<div class="tabs">${opt.tabs.map(t => `<span class="${t[0] === f.tab ? 'a' : ''}" data-tab="${t[0]}">${t[1]}${S.counts['mine_' + t[0]] != null ? ' · ' + S.counts['mine_' + t[0]] : ''}</span>`).join('')}</div>` : '';
  const inner = `${tabs}<div class="sec"><div class="two" style="grid-template-columns:1fr auto;gap:8px"><input id="lq" placeholder="Cari nomor, nama, refnote" value="${esc(f.q || '')}"><button class="btn g sm" id="lqb">Cari</button></div>
    <button class="fbtn" id="lfb" style="margin-top:8px">FILTER <em>${active.length}</em> <em>${j.total} hasil</em></button>${opt.todoFilter ? `<div class="tgl2" style="margin-top:8px"><div data-td="0" class="${f.todo ? '' : 'on'}">${opt.todoMine ? 'Daftar klaim' : 'Semua klaim'}<small>${f.todo ? (oth ?? '') : j.total} klaim</small></div><div data-td="1" class="${f.todo ? 'on' : ''}">Perlu tindakan saya<small>${f.todo ? j.total : (oth ?? '')} klaim</small></div></div>` : ''}${active.length ? `<div class="chips">${active.map(k => `<span class="chip" data-clr="${k}">${k === 'claimant_id' ? 'Pemohon' : k === 'todo' ? 'Perlu tindakan saya' : k} ✕</span>`).join('')}</div>` : ''}</div>
    <div class="sec">${opt.top || ''}${j.rows.length ? j.rows.map(claimLi).join('') : emptyBox(opt.empty || 'Tidak ada klaim.')}</div>`;
  shell(hero(title, opt.sub || MODES[curMode()].n.toUpperCase(), { back: opt.back }) + inner, { hero: false });
  const reload = nf => { LF[opt.key || title] = { ...f, ...nf }; listScreen(title, LF[opt.key || title], opt); };
  $('#lqb').onclick = () => reload({ q: $('#lq').value }); $('#lq').onkeydown = e => { if (e.key === 'Enter') reload({ q: $('#lq').value }); };
  $$('[data-td]').forEach(t => t.onclick = () => reload({ todo: +t.dataset.td ? (opt.todoMine ? 'mine' : 1) : '' }));
  $$('[data-tab]').forEach(t => t.onclick = () => reload({ tab: t.dataset.tab })); $$('[data-clr]').forEach(t => t.onclick = () => reload({ [t.dataset.clr]: '' }));
  $('#lfb').onclick = () => { sheet(`<h3>Filter</h3><div class="fld"><label>Kategori</label><select id="fc"><option value="">Semua</option>${['Transportasi', 'Entertain', 'Perdin', 'Operasional'].map(c => `<option ${f.category === c ? 'selected' : ''}>${c}</option>`).join('')}</select></div>
    ${opt.statusFilter ? `<div class="fld"><label>Status</label><select id="fs"><option value="">Semua</option>${[['WAIT', 'Menunggu'], ['REV', 'Perlu Revisi'], ['DONE', 'Selesai'], ['REJECTED', 'Ditolak']].map(s => `<option value="${s[0]}" ${f.status === s[0] ? 'selected' : ''}>${s[1]}</option>`).join('')}</select></div>` : ''}
    <div class="fld"><label>Pemohon</label>${pemohonSel('fp', (S.L.people.find(x => x.id == f.claimant_id) || {}).name)}</div>
    <div class="fld"><label>Region</label><select id="fr"><option value="">Semua</option>${S.L.regions.map(r => `<option ${f.region === r ? 'selected' : ''}>${esc(r)}</option>`).join('')}</select></div>
    <div class="two"><div class="fld"><label>Dari</label><input type="date" id="ff" value="${f.from || ''}"></div><div class="fld"><label>Sampai</label><input type="date" id="ft" value="${f.to || ''}"></div></div>
    <div class="two"><button class="btn g" id="frs">RESET</button><button class="btn" id="fok">LIHAT HASIL</button></div>`, (el, close) => {
    pemohonUp(el, 'fp');
    $('#frs', el).onclick = () => { close(); reload({ category: '', status: '', claimant_id: '', region: '', from: '', to: '' }); };
    $('#fok', el).onclick = () => { const p = S.L.people.find(x => x.name === $('#fp', el).value); close(); reload({ category: $('#fc', el).value, status: $('#fs', el)?.value || '', claimant_id: p ? p.id : '', region: $('#fr', el).value, from: $('#ff', el).value, to: $('#ft', el).value }); };
  }); };
}
R.mine = async () => listScreen('Klaim saya', { view: 'mine', tab: QP.get('tab') || 'proses' }, { key: 'mine', sub: 'PEMOHON', todoFilter: true, todoMine: true, tabs: [['proses', 'Diproses'], ['revisi', 'Perlu Revisi'], ['selesai', 'Selesai'], ['ditolak', 'Ditolak'], ['draft', 'Draft']], empty: 'Belum ada klaim di tab ini.' });
R.queue = async (st) => listScreen('Antrean ' + (STAGE[st] || st), { view: 'queue', stage: st }, { key: 'q' + st, empty: 'Antrean kosong. Semua sudah ditangani.' });
R.revisi = async () => listScreen('Revisi di Admin', { view: 'revisi_admin' }, { key: 'rev', sub: 'ADMIN', empty: 'Tidak ada revisi di Admin.' });
R.proses = async () => listScreen('Diproses', { view: 'proses' }, { key: 'proses', statusFilter: true, todoFilter: true });
R.ba = async () => listScreen('Klaim dengan BA', { view: 'ba' }, { key: 'ba', todoFilter: true, empty: 'Tidak ada klaim dengan BA yang sedang berjalan.' });
R.selesai = async () => listScreen(curMode() === 'ft' ? 'Riwayat' : 'Selesai', { view: 'selesai' }, { key: 'selesai' });
R.all = async () => listScreen('Semua klaim', { view: 'all' }, { key: 'all', statusFilter: true, todoFilter: true });
R.upcoming = async () => listScreen('Akan datang', { view: 'proses' }, { key: 'upc', sub: 'ESTIMASI MASUK KE FINANCE', empty: 'Tidak ada.', top: '<div class="note blue">Klaim yang masih di tahap sebelum Finance Transfer. Tanda tahap menunjukkan posisinya.</div>' });

/* ---------- notifikasi ---------- */
R.notif = async () => {
  const j = await api('notif_list'); await api('notif_read'); S.counts.notif = 0;
  shell(hero('Notifikasi', 'LONCENG', { back: true, nobell: true }) + `<div class="sec">${j.rows.length ? j.rows.map(n => `<div class="li" ${n.ref_type ? `data-go="#/${n.ref_type === 'pc' ? 'pcd' : 'claim'}/${n.ref_id}"` : ''} style="${n.is_read ? '' : 'border-color:#F07A0C'}"><div class="dot" style="background:${{ tugas: '#1E5FB8', revisi: '#F07A0C', ditolak: '#D2453F', selesai: '#12866A' }[n.kind] || '#5B7285'}">${{ tugas: '📥', revisi: '↩️', ditolak: '⛔', selesai: '✅' }[n.kind] || '•'}</div><div class="t"><b style="white-space:normal">${esc(n.text)}</b><span>${wkt(n.created_at)}</span></div></div>`).join('') : emptyBox('Belum ada notifikasi.')}</div>`, { hero: false });
};

/* ---------- menu ---------- */
R.menu = async () => {
  const m = curMode(); const links = [];
  if (m === 'admin') links.push(['✅', 'Selesai', '#/selesai'], ['👥', 'Hak klaim', '#/a/hak'], ['🏷️', 'Jenis klaim', '#/a/jenis'], ['⚙️', 'Pengaturan klaim', '#/a/atur'], ['⬇️', 'Rekap dan export', '#/rekap'], ['💵', 'Petty cash Admin', '#/pcq/ADMIN'], ['🏢', 'Ajukan untuk divisi', '#/new?div=1'], ['📊', 'Monitoring budget', '#/monitor']);
  if (['audit', 'hos', 'fm', 'ft'].includes(m)) links.push(['⬇️', 'Rekap dan export', '#/rekap']);
  if (m === 'ft') links.push(['💵', 'Petty cash (transfer & setoran)', '#/pcq/FT']);
  if (m === 'fm' || m === 'hos') links.push(['⚙️', 'Lihat aturan BA', '#/a/atur']);
  if (m === 'hos') links.push(['💵', 'Petty cash', '#/pcmon']);
  if (m === 'fm') links.push(['📚', 'Semua klaim', '#/all']);
  if (m === 'sa') links.push(['⛽', 'Harga BBM', '#/sa/harga'], ['🏢', 'Badan usaha', '#/sa/bu'], ['🎭', 'Ganti peran (uji)', '#/sa/switch']);
  if (['admin', 'ft'].includes(m)) links.push(['🔐', 'Terbitkan kode untuk pengganti', '#/sa/codes']);
  if (m === 'sa') links.push(['🗓️', 'Kalender', '#/kalender']);
  links.unshift(['👤', 'Profil saya (WhatsApp)' + (S.me.wa ? '' : ' · belum diisi'), '#/profil'], ['✍️', 'Tanda tangan saya' + (S.me.has_sign ? '' : ' (belum diisi)'), '#/ttd']);
  links.push(['🔑', 'Masuk kode cadangan', '#/kode']);
  shell(hero(S.me.name, ROLE_LBL(), { back: false }) + `<div class="sec">${links.map(l => `<div class="li" data-go="${l[2]}"><div class="dot" style="background:#10394E">${l[0]}</div><div class="t"><b>${l[1]}</b></div>›</div>`).join('')}<div class="li" id="lo"><div class="dot" style="background:#D2453F">⎋</div><div class="t"><b>${window.CLAIM_DSM && S.dsmRole !== 'OPERASIONAL' ? 'KEMBALI KE DSM' : 'KELUAR'}</b></div></div></div>`, { hero: false });
  $('#lo').onclick = async () => { if (window.CLAIM_DSM) { if (S.dsmRole === 'OPERASIONAL') dsmLogout(); else location.href = '../index.php'; return; } await api('logout'); S.me = null; S.L = null; location.hash = '#/login'; route(); };
};
/* ---------- profil: nomor WhatsApp ---------- */
R.profil = async () => {
  const fmt = w => w ? '0' + String(w).slice(2).replace(/(\d{3})(\d{4})(\d+)/, '$1-$2-$3') : '';
  shell(hero('Profil saya', esc(S.me.name), { back: true }) + `<div class="sec"><div class="card">${fld('Nomor WhatsApp', `<input id="pw" inputmode="tel" value="${esc(fmt(S.me.wa))}" placeholder="0812 3456 7890">`, 'Dipakai rekan untuk follow-up klaim. Tampil sebagai tombol WhatsApp, nomor tidak dipajang di daftar.')}<button class="btn" id="pws">SIMPAN</button></div>
    <div class="li" data-go="#/ttd"><div class="dot" style="background:#10394E">✍️</div><div class="t"><b>Tanda tangan saya</b><span class="mut">${S.me.has_sign ? 'Sudah diisi' : 'Belum diisi'}</span></div>›</div></div>`, { hero: false });
  $('#pws').onclick = () => guard(async () => { const j = await api('wa_save', { wa: $('#pw').value }); S.me.wa = j.wa; toast(j.wa ? 'Nomor WhatsApp tersimpan.' : 'Nomor WhatsApp dihapus.'); R.profil(); });
};
/* ---------- tanda tangan tersimpan di profil ---------- */
R.ttd = async () => {
  const cur = (await api('sign_get')).img; let mode = 'draw', draft = null;
  const W = 600, H = 220;
  const paint = () => {
    shell(hero('Tanda tangan saya', 'TTD', { back: true }) + `<div class="sec">
     ${cur ? `<div class="card"><label class="mut">TANDA TANGAN TERSIMPAN</label><div style="background:#FAFCFE;border:1px solid #D3DEE8;border-radius:14px;text-align:center;padding:8px;margin-top:6px"><img src="${cur}" alt="" style="max-width:100%;max-height:110px"></div><div class="mut" style="margin-top:6px">Dipakai otomatis setiap Anda menandatangani BA. Isi ulang di bawah untuk menggantinya.</div></div>` : '<div class="note red"><b>Belum ada tanda tangan.</b> Klaim yang butuh BA tidak bisa dikirim sebelum ini diisi.</div>'}
     <div class="card"><div class="two" style="margin-bottom:10px"><span class="chip${mode === 'draw' ? ' on' : ''}" data-m="draw" style="text-align:center;padding:11px">✍ Gambar di layar</span><span class="chip${mode === 'photo' ? ' on' : ''}" data-m="photo" style="text-align:center;padding:11px">📷 Unggah foto</span></div>
      ${mode === 'draw' ? `<canvas id="pad" width="${W}" height="${H}" style="width:100%;height:auto;aspect-ratio:${W}/${H};border:2px dashed #9DB6CC;border-radius:14px;background:#FAFCFE;touch-action:none;display:block"></canvas><div class="mut" style="margin-top:6px">Tanda tangan dengan jari atau stylus di dalam kotak.</div>`
      : `<div class="note">Foto tanda tangan di kertas putih dengan cahaya cukup. Latar putih dibuang otomatis. Hasilnya bergantung pada pencahayaan, jadi cara gambar di layar lebih disarankan.</div><input type="file" id="pf" accept="image/*" style="margin:6px 0"><div id="pv" style="text-align:center;background:#FAFCFE;border:1px solid #D3DEE8;border-radius:14px;min-height:80px;padding:6px">${draft ? `<img src="${draft}" style="max-width:100%;max-height:110px">` : '<span class="mut">Belum ada foto dipilih</span>'}</div>`}
      <div class="two" style="margin-top:12px"><button class="btn g" id="tclr">HAPUS</button><button class="btn" id="tsave">SIMPAN TANDA TANGAN</button></div></div></div>`, { hero: false });
    $$('[data-m]').forEach(o => o.onclick = () => { mode = o.dataset.m; draft = null; paint(); });
    if (mode === 'draw') bindPad(); else bindPhoto();
    $('#tsave').onclick = () => guard(async () => {
      const img = mode === 'draw' ? padPng() : draft; if (!img) throw new Error(mode === 'draw' ? 'Gambar tanda tangan dulu di dalam kotak.' : 'Pilih foto tanda tangan dulu.');
      await api('sign_save', { img }); S.me.has_sign = true; toast('Tanda tangan tersimpan.'); R.ttd();
    });
  };
  let ink = false;
  const bindPad = () => {
    const cv = $('#pad'), g = cv.getContext('2d'); ink = false; g.lineWidth = 3.4; g.lineCap = g.lineJoin = 'round'; g.strokeStyle = '#1E3F8A'; let down = false;
    const pt = e => { const r = cv.getBoundingClientRect(); return [(e.clientX - r.left) * W / r.width, (e.clientY - r.top) * H / r.height]; };
    cv.onpointerdown = e => { e.preventDefault(); cv.setPointerCapture(e.pointerId); down = true; ink = true; const [x, y] = pt(e); g.beginPath(); g.moveTo(x, y); g.lineTo(x + .1, y + .1); g.stroke(); };
    cv.onpointermove = e => { if (!down) return; e.preventDefault(); const [x, y] = pt(e); g.lineTo(x, y); g.stroke(); };
    cv.onpointerup = cv.onpointercancel = () => { down = false; };
    $('#tclr').onclick = () => guard(async () => { g.clearRect(0, 0, W, H); ink = false; });
  };
  window.padPng = () => {
    if (!ink) return null; const cv = $('#pad'), g = cv.getContext('2d'), d = g.getImageData(0, 0, W, H).data; let x0 = W, y0 = H, x1 = 0, y1 = 0;
    for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) if (d[(y * W + x) * 4 + 3] > 20) { if (x < x0) x0 = x; if (x > x1) x1 = x; if (y < y0) y0 = y; if (y > y1) y1 = y; }
    const m = 8; x0 = Math.max(0, x0 - m); y0 = Math.max(0, y0 - m); x1 = Math.min(W, x1 + m); y1 = Math.min(H, y1 + m);
    const o = document.createElement('canvas'); o.width = x1 - x0; o.height = y1 - y0; o.getContext('2d').drawImage(cv, x0, y0, o.width, o.height, 0, 0, o.width, o.height); return o.toDataURL('image/png');
  };
  const bindPhoto = () => {
    $('#tclr').onclick = () => { draft = null; paint(); };
    $('#pf').onchange = e => {
      const f = e.target.files[0]; if (!f) return; const im = new Image();
      im.onload = () => {
        const s = Math.min(1, 520 / im.width, 220 / im.height), w = Math.max(1, Math.round(im.width * s)), h = Math.max(1, Math.round(im.height * s));
        const cv = document.createElement('canvas'); cv.width = w; cv.height = h; const g = cv.getContext('2d'); g.drawImage(im, 0, 0, w, h);
        const id = g.getImageData(0, 0, w, h), d = id.data;
        for (let i = 0; i < d.length; i += 4) { const lum = .299 * d[i] + .587 * d[i + 1] + .114 * d[i + 2]; const a = Math.max(0, Math.min(255, (225 - lum) * 4)); d[i] = 30; d[i + 1] = 63; d[i + 2] = 138; d[i + 3] = a; }
        g.putImageData(id, 0, 0); draft = cv.toDataURL('image/png'); $('#pv').innerHTML = `<img src="${draft}" style="max-width:100%;max-height:110px">`;
      };
      im.src = URL.createObjectURL(f);
    };
  };
  paint();
};
const ROLE_LBL = () => [S.me.job, S.me.region, S.me.entity].filter(Boolean).join(' · ');
R.kode = async () => {
  shell(hero('Masuk dengan kode cadangan', 'G02', { back: true }) + `<div class="sec"><div class="card"><div class="note">Kode dari Super Admin atau dari orang yang Anda gantikan. Kode hanya untuk Anda, berlaku selama durasi yang ditentukan pemberi, dan bisa dipakai masuk lagi maksimal 5 kali.</div><div class="fld"><label>Kode</label><input id="kc" maxlength="8" style="text-transform:uppercase;letter-spacing:.2em;font-size:18px;text-align:center"></div><button class="cta" id="kg">GUNAKAN KODE</button></div></div>`, { hero: false });
  $('#kg').onclick = () => guard(async () => { const j = await api('backup_use', { code: $('#kc').value }); await loadMe(); S.mode = null; toast('Akses cadangan aktif.'); location.hash = '#/home'; });
};
