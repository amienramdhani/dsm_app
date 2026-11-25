/* DSM Klaim - tampilan DESKTOP untuk non-sales (2026-11-24, mockup doc/mockup/2026-11-24/dsm-klaim-desktop-nonsales.html, kartu gaya B).
   Tampilan HP TIDAK diubah: file ini hanya membungkus hasil shell() saat:
   layar >= 1024px, peran bukan Pemohon, dan pengguna tidak memilih "Tampilan HP" (localStorage kl_view = 'm').
   Daftar klaim (.li) diubah jadi tabel; menu bawah diganti menu samping. */
'use strict';
const DKR = {}; // baris klaim terakhir yang dirender, untuk tabel desktop
const DK_MIN = 1024;
const dkPref = () => { try { return localStorage.getItem('kl_view') || 'd'; } catch (e) { return 'd'; } };
const dkSet = v => { try { localStorage.setItem('kl_view', v); } catch (e) {} };
const dkEligible = () => innerWidth >= DK_MIN && S.me && curMode() !== 'pemohon';
const isDesk = () => dkEligible() && dkPref() !== 'm';

const _claimLi = claimLi;
claimLi = r => { DKR[r.id] = r; return _claimLi(r); };

const _shell = shell;
shell = function (inner, opt = {}) {
  const r = _shell(inner, opt);
  document.body.classList.toggle('dk-on', isDesk());
  if (isDesk()) dkBuild(); else if (dkEligible()) $('#app').insertAdjacentHTML('beforeend', '<button class="dk-sw" data-dkv="d">TAMPILAN DESKTOP</button>');
  return r;
};

function dkBuild() {
  const ap = $('#app'), mode = curMode(), c = S.counts || {};
  const nav = MODES[mode].nav.filter(n => n[2] !== '#/menu');
  const hp = (location.hash || '#/home').split('?')[0];
  const nb = { '#/revisi': c.revisi_admin || 0, '#/ba': c['ba_' + ({ admin: 'ADMIN', hos: 'HOS', fm: 'FM' }[mode] || '')] || 0, '#/home': c['queue_' + ({ admin: 'ADMIN', audit: 'AUDIT', hos: 'HOS', fm: 'FM', ft: 'FT' }[mode] || '')] || 0 };
  const item = (l, go, ic, n) => `<a class="${hp === go || (go === '#/monitor' && hp === '#/realisasi') ? 'on' : ''}" data-go="${go}">${navIcon(ic || l)}<span>${esc(l)}</span>${n ? `<em>${n}</em>` : ''}</a>`;
  const modes = availModes();
  const side = `<aside class="dk-sb"><div class="dk-lg"><i>DSM</i><div><b>DSM Klaim</b><small>Biaya operasional</small></div></div>
    ${modes.length > 1 ? `<label class="dk-role"><span>PERAN</span><select id="dkrole">${modes.map(m => `<option value="${m}" ${m === mode ? 'selected' : ''}>${esc(MODES[m].n)}</option>`).join('')}</select></label>` : `<div class="dk-role"><span>PERAN</span><b>${esc(MODES[mode].n)}</b></div>`}
    <nav class="dk-nv"><div class="g">KERJA</div>${nav.map(n => item(n[1], n[2], n[1], nb[n[2]])).join('')}
    <div class="g">LAINNYA</div>${item('Notifikasi', '#/notif', 'Riwayat', c.notif)}${item('Menu', '#/menu', 'Menu')}</nav>
    <div class="dk-me"><b>${esc(S.me.name)}</b><small>${esc(S.me.job || MODES[mode].n)}</small><button class="dk-hp" data-dkv="m">${navIcon('Menu')}TAMPILAN HP</button></div></aside>`;
  const kids = [...ap.children].filter(e => !e.classList.contains('nav') && !e.classList.contains('skel'));
  ap.querySelector('.nav')?.remove();
  ap.insertAdjacentHTML('afterbegin', `${side}<main class="dk-mn"></main>`);
  const mn = ap.querySelector('.dk-mn'); kids.forEach(k => mn.appendChild(k));
  mn.querySelectorAll('.modebar').forEach(e => e.remove());
  const rs = $('#dkrole'); if (rs) rs.onchange = () => { setMode(rs.value); location.hash = '#/home'; route(); };
  dkTables(mn);
  if (/^#\/claim\//.test(location.hash)) dkClaim(mn);
  if (mode === 'sa' && /^#\/(home)?$|^$/.test((location.hash || '#/home').split('?')[0]) || (mode === 'sa' && (location.hash || '#/home') === '#/home')) dkSaHome(mn);
  if (/^#\/sa\/users/.test(location.hash)) dkUsers(mn);
  if (/^#\/sa\/codes/.test(location.hash)) dkCodes(mn);
}

/* kumpulan .li klaim yang berurutan -> satu tabel */
function dkTables(root) {
  const groups = []; let cur = null;
  root.querySelectorAll('.li[data-go^="#/claim/"]').forEach(li => {
    if (cur && cur.last.nextElementSibling === li) cur.items.push(li); else { cur = { items: [li] }; groups.push(cur); }
    cur.last = li;
  });
  groups.forEach(g => {
    const rows = g.items.map(li => DKR[+li.dataset.go.split('/').pop()]).filter(Boolean);
    if (rows.length !== g.items.length) return;
    const tb = document.createElement('div'); tb.className = 'dk-tw';
    tb.innerHTML = `<table class="dk-tb"><thead><tr><th>NO. KLAIM</th><th>TANGGAL</th><th>PEMOHON</th><th>REGION</th><th>KATEGORI / JENIS</th><th>BRAND</th><th class="n">TOTAL</th><th>STATUS</th><th>UMUR</th></tr></thead><tbody>${rows.map(r => `<tr data-go="#/claim/${r.id}">
      <td><b>${esc(r.number || 'Draft')}</b>${r.version > 1 ? ` <small>v${r.version}</small>` : ''}</td><td>${r.date ? tgl(r.date) : '-'}</td><td>${esc(r.claimant)}</td><td>${esc(r.region || '-')}</td>
      <td><span class="dk-ty" style="--c:${CATCOL[r.category] || '#94A3B8'}"><i></i>${esc(r.category || '')} · ${esc(r.type)}</span></td><td>${esc(r.brand || '-')}</td><td class="n"><b>${rp(r.total)}</b></td>
      <td>${pill(r)}${r.todo ? ` <span class="pill p-adm">Menunggu Anda</span>` : ''}${r.ba ? ' <span class="pill p-no">BA</span>' : ''}${r.over ? ` <span class="pill p-no">Over ${rp(r.over)}</span>` : ''}</td>
      <td>${['WAIT', 'REV'].includes(r.status) ? `<span class="dk-age${r.aging ? ' l' : ''}">${r.age_days ? r.age_days + ' hari' : 'hari ini'}</span>` : '-'}</td></tr>`).join('')}</tbody></table>`;
    g.items[0].before(tb); g.items.forEach(li => li.remove());
  });
}

document.addEventListener('click', e => { const b = e.target.closest('[data-dkv]'); if (!b) return; dkSet(b.dataset.dkv); route(); });
let _dkW = innerWidth; addEventListener('resize', () => { const a = _dkW >= DK_MIN, b = innerWidth >= DK_MIN; _dkW = innerWidth; if (a !== b && S.me) route(); });

/* detail klaim versi desktop (mockup layar 2). Halaman asli tetap dirender dulu supaya semua tombol & event-nya hidup,
   lalu bagiannya dipindah ke tata letak: judul + Sebelumnya/Berikutnya, kiri isi klaim, kanan foto nota + status, bar aksi bawah. */
let DKC = null, DKL = [];
const _api = api; api = async (f, a) => { const r = await _api(f, a); if (f === 'claim_get') DKC = r; if (f === 'claim_list' && r && r.rows && (a || {}).limit !== 1) DKL = r.rows.map(x => x.id); return r; };
function dkClaim(mn) {
  const d = DKC; const secs = [...mn.children].filter(e => e.classList.contains('sec')); if (!d || !secs.length) return;
  const c = d.claim, cu = d.claimant || {};
  const head = s => s.querySelector(':scope>h3')?.textContent.trim() || '';
  const take = re => secs.filter(s => re.test(head(s)));
  const pdf = secs.find(s => s.querySelector('a[href*="a=pdf"]') && !head(s));
  const status = secs.find(s => !head(s) && s.querySelector('.totbox'));
  const acts = secs.filter(s => !head(s) && s !== pdf && s !== status);
  const ix = DKL.indexOf(c.id), prv = ix > 0 ? DKL[ix - 1] : null, nxt = ix >= 0 && ix < DKL.length - 1 ? DKL[ix + 1] : null;
  const st = t => `<div class="dk-st"><span>${t}</span></div>`;
  // foto semua nota
  const fotos = []; d.notes.forEach((n, i) => (n.files || []).forEach(f => fotos.push({ f, i, l: 'Nota ' + (i + 1) })));
  [['odo_awal', 'Odometer awal'], ['odo_akhir', 'Odometer akhir'], ['doc', 'Dokumentasi'], ['maps', 'Google Maps']].forEach(([k, l]) => ((d.files || {})[k] || []).forEach(f => fotos.push({ f, i: -1, l })));
  const isPdf = f => /pdf/.test(f.mime || '');
  const big = x => x ? (isPdf(x.f) ? `<div class="dk-big pdf"><iframe src="${fileUrl(x.f.id)}#view=FitH" title="PDF"></iframe><button class="dk-b dk-pbtn" data-view="${x.f.id}" data-pdf="1">⤢ PERBESAR PDF</button></div>` : `<div class="dk-big" data-view="${x.f.id}"><img src="${fileUrl(x.f.id)}" alt=""></div>`) : '<div class="dk-big e">Tidak ada foto nota</div>';
  const tName = n => { const t = n.detail || {}; return t.komponen || (d.kind === 'multi' ? ((d.parts.find(p => p.type.id == t.type_id) || {}).type || {}).name : d.type_label) || '-'; };
  const tDet = n => { const t = n.detail || {}; return [t.bbm, t.asal && t.tujuan ? `${t.asal} → ${t.tujuan}` : '', t.jenis, t.mode === 'hari' ? `${rp(t.per_hari)} × ${t.hari} hari` : ''].filter(Boolean).map(esc).join(' · ') || '-'; };
  const notaTb = `<div class="sec"><div class="card dk-c">${st(`Nota (${d.notes.length})`).replace('</div>', `<b>Total ${rp(c.total)}</b></div>`)}<table class="dk-tb dk-nt"><thead><tr><th>#</th><th>JENIS</th><th>TANGGAL</th><th>DETAIL</th><th class="n">NOMINAL</th><th>FOTO</th></tr></thead><tbody>${d.notes.map((n, i) => `<tr data-dkn="${i}"><td>${i + 1}</td><td><b>${esc(tName(n))}</b></td><td>${n.nota_date ? tgl(n.nota_date) : '-'}</td><td>${tDet(n)}</td><td class="n"><b>${rp(n.nominal)}</b></td><td><div class="dk-tt">${(n.files || []).slice(0, 3).map(f => isPdf(f) ? `<i class="pdf" data-view="${f.id}" data-pdf="1" title="Klik untuk perbesar">PDF</i>` : `<i data-view="${f.id}" title="Klik untuk perbesar" style="background-image:url('${fileUrl(f.id)}')"></i>`).join('') || '<span class="mut">-</span>'}</div></td></tr>`).join('')}</tbody></table></div></div>`;
  const wrap = document.createElement('div'); wrap.className = 'dk-cd';
  wrap.innerHTML = `<div class="dk-tt0"><button class="dk-b" data-dkback>← Kembali</button><h3>${esc(c.number || 'Draft')} · ${esc(cu.name || '')}</h3>${c.ba_required == 1 ? '<span class="pill p-no">DENGAN BA</span>' : ''}${pill(row(c))}<span class="dk-sp"></span>${prv ? `<button class="dk-b" data-go="#/claim/${prv}">‹ Sebelumnya</button>` : ''}${nxt ? `<button class="dk-b" data-go="#/claim/${nxt}">Berikutnya ›</button>` : ''}</div>
    <div class="dk-tot"><span>TOTAL NOMINAL DIPROSES</span><b>${rp(c.total)}</b></div><div class="dk-cl"></div><div class="dk-cr"><div class="sec"><div class="card dk-c">${st(`<span id="dkfl">${fotos[0] ? fotos[0].l : 'Foto'}</span>`).replace('</div>', `<b>${fotos.length} foto</b></div>`)}<div class="dk-fv">${big(fotos[0])}${fotos.length ? `<div class="dk-ths">${fotos.map((x, k) => `<i data-dkf="${k}" class="${k ? '' : 'on'}${isPdf(x.f) ? ' pdf' : ''}">${isPdf(x.f) ? '<em>PDF</em>' : `<img src="${fileUrl(x.f.id)}" alt="">`}<u>${x.l}</u></i>`).join('')}</div>` : ''}</div></div></div></div>`;
  secs[0].before(wrap); const cl = wrap.querySelector('.dk-cl'), cr = wrap.querySelector('.dk-cr');
  // kiri
  const dp = take(/^Data pengajuan/)[0]; if (dp) { const cd = dp.querySelector('.card'); cd.classList.add('dk-c', 'dk-kv'); cd.insertAdjacentHTML('afterbegin', st('Info klaim') + `<div class="dk-v">v${c.current_version || 1}</div>`); dp.querySelector('h3').remove(); cl.appendChild(dp); }
  take(/^(Peserta|Daftar peserta|Perjalanan dinas)/).forEach(s => cl.appendChild(s));
  cl.insertAdjacentHTML('beforeend', notaTb); take(/^Nota/).forEach(s => s.remove());
  take(/^(Berita Acara|Balik nota)/).forEach(s => cl.appendChild(s));
  take(/^Lampiran/).forEach(s => s.remove()); // sudah ada di panel foto kanan
  take(/^Berita Acara/).forEach(dkBA);
  cl.querySelectorAll('.mapbox').forEach(m => { const cap = m.previousElementSibling; if (cap && /Google Maps/.test(cap.textContent)) cap.remove(); m.outerHTML = '<div class="mut">Screenshot Google Maps: lihat di panel foto kanan.</div>'; });
  // kanan
  acts.forEach(s => cr.appendChild(s));
  if (status) { status.querySelector('.totbox')?.remove(); cr.appendChild(status); }
  take(/^(Budget|Jejak|Versi|Riwayat|Transfer)/).forEach(s => cr.appendChild(s));
  if (pdf) { const a = pdf.querySelector('a[href*="a=pdf"]'); a.className = 'dk-b dk-pdfb'; a.removeAttribute('style'); a.textContent = '⬇ UNDUH PDF KLAIM'; wrap.querySelector('.dk-sp').after(a); pdf.remove(); }
  [...cl.children, ...cr.children].forEach(s => { const h = s.querySelector(':scope>h3'); const cd = s.querySelector(':scope>.card'); if (h && cd && !cd.querySelector('.dk-st')) { cd.classList.add('dk-c'); cd.insertAdjacentHTML('afterbegin', st(h.innerHTML)); h.remove(); } });
  // bar aksi bawah: tombol cermin dari tombol asli
  const mirror = [['bapp', 'gr', '✓ SETUJUI'], ['brev', 'g', 'MINTA REVISI'], ['brej', 'r', 'TOLAK'], ['btf', 'gr', 'CATAT TRANSFER']].filter(([id]) => document.getElementById(id));
  if (mirror.length) wrap.querySelector('.dk-tot').insertAdjacentHTML('beforeend', `<div class="dk-ta">${mirror.map(([id, k, t]) => `<button class="dk-b ${k}" data-dkm="${id}">${t}</button>`).join('')}</div>`);
  mn.querySelectorAll('[data-dkm]').forEach(b => b.onclick = () => { const o = document.getElementById(b.dataset.dkm); o.scrollIntoView({ block: 'center' }); o.click(); });
  mn.querySelector('[data-dkback]').onclick = () => history.back();
  mn.querySelectorAll('[data-dkf]').forEach(t => t.onclick = () => { mn.querySelector('.dk-big').outerHTML = big(fotos[+t.dataset.dkf]); mn.querySelectorAll('[data-dkf]').forEach(x => x.classList.toggle('on', x === t)); $('#dkfl').textContent = fotos[+t.dataset.dkf].l; });
  mn.querySelectorAll('[data-dkn]').forEach(r => r.onclick = () => { const k = fotos.findIndex(x => x.i === +r.dataset.dkn); if (k >= 0) mn.querySelector(`[data-dkf="${k}"]`)?.click() ?? (mn.querySelector('.dk-big').outerHTML = big(fotos[k])); mn.querySelectorAll('[data-dkn]').forEach(x => x.classList.toggle('sel', x === r)); });
}

/* kartu BA versi desktop: angka budget jadi 4 kotak sejajar, tanda tangan jadi deret langkah, tombol PDF BA kecil di judul */
function dkBA(sec) {
  const cd = sec.querySelector(':scope>.card'); if (!cd) return;
  const kids = [...cd.children]; const ttdAt = kids.findIndex(e => e.classList.contains('mut') && /Tanda tangan elektronik/.test(e.textContent)); if (ttdAt < 0) return;
  const before = kids.slice(0, ttdAt), after = kids.slice(ttdAt + 1);
  const bud = before.filter(e => e.classList.contains('kv')), ttd = after.filter(e => e.classList.contains('kv'));
  const box = document.createElement('div'); box.className = 'dk-bab';
  box.innerHTML = `<div class="dk-bb">${bud.map(k => `<div><small>${k.children[0].innerHTML}</small><b>${k.children[1].innerHTML}</b></div>`).join('')}</div>
    <div class="dk-bl">TANDA TANGAN ELEKTRONIK</div><div class="dk-bs">${ttd.map(k => { const v = k.children[1].innerHTML, ok = /✔/.test(v), old = /versi lama/.test(v);
      return `<div class="${ok ? 'ok' : old ? 'x' : ''}"><i>${ok ? '✓' : old ? '!' : ''}</i><b>${k.children[0].innerHTML}</b><small>${ok ? v.replace('✔', '').trim() : old ? 'versi lama tidak berlaku' : 'belum'}</small></div>`; }).join('')}</div>`;
  bud[0]?.before(box); [...bud, kids[ttdAt], ...ttd].forEach(e => e.remove());
  const pdf = cd.querySelector('a[href*="ba_pdf"]'); const two = pdf?.closest('.two');
  if (pdf) { pdf.className = 'dk-b dk-bapdf'; pdf.textContent = '⬇ PDF BA'; const hd = sec.querySelector('.dk-st') || cd.querySelector('.dk-st'); (hd || cd).appendChild(pdf); }
  if (two && !two.querySelector('button')) two.remove(); else if (two) two.classList.add('dk-bact');
}

/* popup PDF di desktop: ditampilkan sebagai dokumen (bukan gambar) */
document.addEventListener('click', e => {
  const v = e.target.closest('[data-view][data-pdf]'); if (!v || !document.body.classList.contains('dk-on')) return;
  e.stopImmediatePropagation(); const u = fileUrl(v.dataset.view);
  sheet(`<iframe src="${u}" style="width:100%;height:78vh;border:0;border-radius:10px;background:#F1F5F9" title="PDF"></iframe><a class="btn g" style="display:block;text-align:center;margin-top:10px;text-decoration:none" href="${u}" target="_blank">BUKA DI TAB BARU</a>`);
  document.querySelector('.sheet-bg:last-child .sheet')?.classList.add('dk-wide');
}, true);

/* ---------- ikon garis untuk menu desktop (pengganti emoji) ---------- */
const DKI = { cal: 'M4 5h16v16H4zM4 10h16M9 3v4m6-4v4', key: 'M15 7a4 4 0 1 1-3.5 6L4 20H2v-3l1-1h2v-2h2l2.5-2.5A4 4 0 0 1 15 7', down: 'M12 3v12m0 0-5-5m5 5 5-5M4 21h16', chart: 'M4 20V10m8 10V4m8 16v-7M2 22h20',
  users: 'M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8m-6 10v-3a6 6 0 0 1 12 0v3m2-17a4 4 0 0 1 0 8m2 3a5 5 0 0 1 4 5v1', up: 'M12 21V9m0 0-5 5m5-5 5 5M4 3h16', tag: 'M20 12 12 20l-8-8V4h8zM8 8h.01', gear: 'M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M14 4v4M8 10v4M16 16v4',
  ok: 'M20 6 9 17l-5-5', cash: 'M3 7h18v12H3zM3 11h18M7 15h3', bld: 'M3 21h18M5 21V7l7-4 7 4v14M9 9h1M14 9h1M9 13h1M14 13h1', doc: 'M6 3h9l4 4v14H6zM14 3v5h5M9 13h7M9 17h5', undo: 'M9 14 4 9l5-5M4 9h11a5 5 0 0 1 0 10h-3',
  x: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18M8 8l8 8', fuel: 'M4 20V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v15M3 20h12M14 9h2a2 2 0 0 1 2 2v5a1.5 1.5 0 0 0 3 0V8l-3-3', lab: 'M9 3h6M10 3v6l-5 9a2 2 0 0 0 2 3h10a2 2 0 0 0 2-3l-5-9V3', mask: 'M7 7h12m0 0-4-4m4 4-4 4M17 17H5m0 0 4 4m-4-4 4-4',
  user: 'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M4 21v-1a7 7 0 0 1 14 0v1', pen: 'M4 20h4L19 9l-4-4L4 16zM14 6l4 4', stack: 'M12 3 2 8l10 5 10-5-10-5M2 13l10 5 10-5', dots: 'M6 12h.01M12 12h.01M18 12h.01' };
const DKE = { '🗓️': 'cal', '🔐': 'key', '🔑': 'key', '⬇️': 'down', '📊': 'chart', '👥': 'users', '📇': 'up', '🏷️': 'tag', '⚙️': 'gear', '✅': 'ok', '💵': 'cash', '🏢': 'bld', '📄': 'doc', '↩️': 'undo', '⛔': 'x', '⛽': 'fuel', '🧪': 'lab', '🎭': 'mask', '👤': 'user', '✍️': 'pen', '📚': 'stack' };
const dkIc = e => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="${DKI[DKE[e]] || DKI[e] || DKI.dots}"/></svg>`;
const DKCOL = ['#0B5CAD', '#6D3FD8', '#12866A', '#F07A0C', '#0E7C6B', '#D97706', '#DB2777', '#475569'];
const DKCLS = { b: '#1E5FB8', p: '#6D3FD8', g: '#12866A', o: '#D97706', t: '#0E7C6B', s: '#475569', r: '#D2453F', y: '#CA8A04' };

/* ---------- Menu (Lainnya) versi desktop: mockup dsm-klaim-desktop-menu.html ---------- */
const _menu = R.menu;
R.menu = async () => {
  if (!isDesk()) return _menu();
  const m = curMode(), d = menuDef(m), ms = availModes(), ini = S.me.name.split(/\s+/).map(w => w[0]).join('').slice(0, 2).toUpperCase();
  const ck = (cls, ic, t, s, go, em) => `<div class="dk-ck ${cls}" data-go="${go}"><i>${dkIc(ic)}</i><div><b>${t}</b><small>${s}</small></div>${em ? `<em>${em}</em>` : ''}</div>`;
  const pf = `<div class="dk-pf"><div class="tp"><div class="av">${esc(ini)}</div><div><small>${esc(MODES[m].n.toUpperCase())}${S.demo ? ' · DEMO' : ''}</small><b>${esc(S.me.name)}</b></div></div><div class="bd">
    ${ms.length > 1 ? `<div class="lb">MASUK SEBAGAI</div><div class="dk-rl">${ms.map(x => `<span class="${x === m ? 'on' : ''}" data-mode="${x}">${esc(MODES[x].n)}</span>`).join('')}</div>` : ''}
    <div class="lb">KELENGKAPAN AKUN</div>${ck(S.me.wa ? 'ok' : 'w', '👤', 'WhatsApp', 'Dipakai untuk follow-up', '#/profil', S.me.wa ? 'ADA' : 'BELUM DIISI')}${ck(S.me.has_sign ? 'ok' : 'w', '✍️', 'Tanda tangan', 'Dipakai di BA &amp; approval', '#/ttd', S.me.has_sign ? 'ADA' : 'BELUM DIISI')}${ck('n', '🔑', 'Masuk kode cadangan', 'Kalau menggantikan orang lain', '#/kode', '')}
    <button class="dk-out" id="lo">↩ ${window.CLAIM_DSM && S.dsmRole !== 'OPERASIONAL' ? 'KEMBALI KE DSM' : 'KELUAR'}</button></div></div>`;
  let r = '';
  if (d.q.length) r += `<div class="dk-gp"><h4>AKSES CEPAT</h4><div class="dk-qa">${d.q.map((q, i) => `<div class="dk-q" style="--c:${DKCOL[i % DKCOL.length]}" data-go="${q[3]}"><i>${dkIc(q[0])}</i><b>${esc(q[1])}</b></div>`).join('')}</div></div>`;
  d.g.forEach(([t, items]) => { r += `<div class="dk-gp"><h4>${t}</h4><div class="dk-tl">${items.map(it => `<div class="dk-t" style="--c:${DKCLS[it[1]] || '#0B5CAD'}" data-go="${it[4]}"><i>${dkIc(it[0])}</i><div><b>${esc(it[2])}</b><small>${esc(it[3])}</small></div>${it[5] ? `<em>${esc(it[5])}</em>` : ''}<span class="go">›</span></div>`).join('')}</div></div>`; });
  shell(hero('Menu', MODES[m].n.toUpperCase() + (S.demo ? ' (DEMO)' : '')) + `<div class="dk-menu">${pf}<div>${r || '<div class="mut">Tidak ada menu tambahan untuk peran ini.</div>'}</div></div>`, { hero: false });
  $('#lo').onclick = async () => { if (window.CLAIM_DSM) { if (S.dsmRole === 'OPERASIONAL') dsmLogout(); else location.href = '../index.php'; return; } await api('logout'); S.me = null; S.L = null; location.hash = '#/login'; route(); };
};

/* ---------- Beranda Super Admin desktop: mockup dsm-klaim-desktop-sa.html ---------- */
const SA_DESC = { '#/sa/users': 'Tambah pengguna, atur peran dan wilayah tugas.', '#/sa/codes': 'Terbitkan akses cadangan saat approver berhalangan.', '#/sa/settings': 'Batas hari menunggu lama, mode tes, aturan umum.', '#/sa/harga': 'Harga Pertalite & Pertamax per tanggal berlaku.', '#/sa/bu': 'Daftar pilihan badan usaha untuk Admin.', '#/sa/switch': 'Uji aplikasi sebagai pengguna lain.' };
const SA_IC = { '#/sa/users': 'users', '#/sa/codes': 'key', '#/sa/settings': 'gear', '#/sa/harga': 'fuel', '#/sa/bu': 'bld', '#/sa/switch': 'mask' };
const SA_COL = { '#/sa/users': '#1E5FB8', '#/sa/codes': '#6D3FD8', '#/sa/settings': '#475569', '#/sa/harga': '#D97706', '#/sa/bu': '#0E7C6B', '#/sa/switch': '#DB2777' };
function dkSaHome(mn) {
  const ks = [...mn.querySelectorAll('.kc .k[data-go]')]; if (!ks.length) return;
  const tiles = ks.map(k => { const g = k.dataset.go; return `<div class="dk-t big" style="--c:${SA_COL[g] || '#0B5CAD'}" data-go="${g}"><i>${dkIc(SA_IC[g] || 'dots')}</i><div><b>${k.querySelector('.l')?.textContent || ''}</b><small>${SA_DESC[g] || k.querySelector('.s')?.textContent || ''}</small>${g === '#/sa/users' ? '<div class="mt" id="dksau"></div>' : ''}</div><span class="go">›</span></div>`; }).join('');
  [...mn.children].filter(e => !e.classList.contains('hero')).forEach(e => e.remove());
  mn.insertAdjacentHTML('beforeend', `<div class="dk-sa"><div class="dk-sst" id="dksst"></div><div class="dk-gp"><h4>PENGATURAN</h4><div class="dk-tl">${tiles}</div></div><div id="dksaw"></div></div>`);
  Promise.all([api('sa_users').catch(() => null), api('area_check').catch(() => null)]).then(([u, a]) => {
    const el = $('#dksst'); if (!el) return; const rows = u ? u.rows : [], act = rows.filter(r => +r.active), unc = a ? a.uncovered : [];
    const st = (c, ic, l, n, s) => `<div class="dk-k" style="--c:${c}"><i>${dkIc(ic)}</i><div><small>${l}</small><b>${n}</b><span>${s}</span></div></div>`;
    el.innerHTML = st('#1E5FB8', 'users', 'PENGGUNA AKTIF', act.length, `${act.filter(r => +r.can_submit).length} bisa mengajukan`) + st(unc.length ? '#F07A0C' : '#12866A', unc.length ? 'x' : 'ok', 'REGION TANPA ADMIN', unc.length, unc.length ? unc.map(r => esc(r.region)).join(', ') : 'semua region tercakup') + st('#6D3FD8', 'key', 'PERAN APPROVER', act.filter(r => /ADMIN|HOS|FM|FT|AUDIT|RGM/.test(r.roles || '')).length, 'Admin, HOS, Finance, Audit, RGM');
    const m = $('#dksau'); if (m) m.innerHTML = `<span>${rows.length} pengguna</span>${unc.length ? `<span class="w">${unc.length} region tanpa Admin</span>` : ''}`;
    if (unc.length) $('#dksaw').innerHTML = `<div class="dk-al"><b>Region tanpa Admin:</b> ${unc.map(r => esc(r.region) + ' (' + r.n + ' pemohon)').join(', ')}. Klaim dari region ini belum punya Admin penerima.<button class="dk-b" data-go="#/sa/users">ATUR ADMIN</button></div>`;
  });
}

/* ---------- Pengguna & peran desktop: daftar -> tabel, cari + filter peran ---------- */
let DKU = null; const _api2 = api; api = async (f, a) => { const r = await _api2(f, a); if (f === 'sa_users') DKU = r; return r; };
const RCOL = { SA: '#0F172A', RGM: '#0E7C6B', ADMIN: '#1E5FB8', AUDIT: '#6D3FD8', HOS: '#C2410C', FM: '#9A6400', FT: '#12866A', ASM: '#64748B', SALES: '#64748B' };
function dkUsers(mn) {
  const lis = [...mn.querySelectorAll('.li[data-u]')]; if (!lis.length || !DKU) return;
  const by = {}; DKU.rows.forEach(r => by[r.id] = r);
  const roles = r => String(r.roles || '').split(',').filter(Boolean);
  const cnt = {}; DKU.rows.forEach(r => roles(r).forEach(x => cnt[x] = (cnt[x] || 0) + 1)); const nSub = DKU.rows.filter(r => +r.can_submit).length;
  const warn = mn.querySelector('.sec>.card'); if (warn && /Region tanpa Admin/.test(warn.textContent)) warn.classList.add('dk-al');
  const nb = $('#unew'); nb.className = 'dk-b dk-bb2'; nb.textContent = '+ PENGGUNA BARU';
  const bar = document.createElement('div'); bar.className = 'dk-ufb';
  bar.innerHTML = `<input id="dkuq" placeholder="Cari nama, username, NIK, region"><div class="dk-chp"><span class="on" data-rf="">SEMUA <em>${DKU.rows.length}</em></span><span data-rf="PEMOHON">PEMOHON <em>${nSub}</em></span>${['ADMIN', 'RGM', 'HOS', 'FM', 'FT', 'AUDIT', 'SA'].filter(x => cnt[x]).map(x => `<span data-rf="${x}">${x} <em>${cnt[x]}</em></span>`).join('')}</div>`;
  nb.before(bar); bar.appendChild(nb);
  const tw = document.createElement('div'); tw.className = 'dk-tw';
  tw.innerHTML = `<table class="dk-tb"><thead><tr><th>NAMA</th><th>USERNAME</th><th>JABATAN</th><th>PERAN</th><th>REGION</th><th>WILAYAH TUGAS</th><th>BADAN USAHA</th><th>AKTIF</th><th></th></tr></thead><tbody>${lis.map(li => { const r = by[li.dataset.u] || {}; const rs = roles(r); const c = RCOL[rs[0]] || '#64748B';
    const ar = r.areas ? String(r.areas).split(',').filter(Boolean).join(', ') : ''; const adm = rs.some(x => x === 'ADMIN' || x === 'RGM');
    return `<tr data-u="${r.id}" data-rs="${rs.join(',')}${+r.can_submit ? ',PEMOHON' : ''}" data-q="${esc([r.name, r.username, r.nik, r.region, r.job].join(' ').toLowerCase())}"><td><div class="dk-av" style="--c:${c}"><i>${esc((r.name || '?')[0])}</i><b>${esc(r.name || '')}</b></div></td><td>${esc(r.username || '-')}</td><td>${esc(r.job || '-')}</td>
      <td>${rs.map(x => `<span class="dk-rlc" style="--c:${RCOL[x] || '#64748B'}">${x}</span>`).join('')}${+r.can_submit ? '<span class="dk-rlc" style="--c:#64748B">Pemohon</span>' : ''}</td><td>${esc(r.region || '-')}</td><td>${adm ? (ar ? esc(ar) : r.region ? esc(r.region) : '<b>Semua region</b>') : '<span class="mut">-</span>'}</td><td>${esc(r.entity || '-')}</td>
      <td><span class="dk-tg ${+r.active ? '' : 'off'}"></span></td><td class="dk-ac">UBAH ›</td></tr>`; }).join('')}</tbody></table>`;
  lis[0].parentElement.replaceWith(tw);
  let rf = '';
  const apply = () => { const q = $('#dkuq').value.trim().toLowerCase(); tw.querySelectorAll('tbody tr').forEach(tr => tr.style.display = (!q || tr.dataset.q.includes(q)) && (!rf || tr.dataset.rs.split(',').includes(rf)) ? '' : 'none'); };
  $('#dkuq').oninput = apply; bar.querySelectorAll('[data-rf]').forEach(c => c.onclick = () => { rf = c.dataset.rf; bar.querySelectorAll('[data-rf]').forEach(x => x.classList.toggle('on', x === c)); apply(); });
}

/* ---------- Monitoring Budget desktop (mockup dsm-klaim-desktop-budget.html opsi B + chart per region) ---------- */
const dkJt = n => (Math.abs(n) >= 1e6 ? (n / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt' : Math.round(n / 1e3).toLocaleString('id-ID') + ' rb');
const dkNum = n => Math.round(n).toLocaleString('id-ID');
function dkDonut(items, center, sub) { // items: [label, value, color]
  const tot = items.reduce((a, x) => a + x[1], 0); let acc = 0;
  const segs = tot ? items.filter(x => x[1] > 0).map(x => { const p = x[1] / tot * 100, o = 25 - acc; acc += p; return `<circle cx="21" cy="21" r="15.9" fill="none" stroke="${x[2]}" stroke-width="7" stroke-dasharray="${p} ${100 - p}" stroke-dashoffset="${o}"/>`; }).join('') : '';
  return `<div class="dk-dn"><svg viewBox="0 0 42 42"><circle cx="21" cy="21" r="15.9" fill="none" stroke="#EEF2F7" stroke-width="7"/>${segs}<text x="21" y="20" text-anchor="middle" font-size="4" font-weight="800">${center}</text><text x="21" y="25" text-anchor="middle" font-size="2.8" fill="#64748B">${sub}</text></svg>
    <div class="dl">${items.map(x => `<div style="--c:${x[2]}"><i></i>${esc(x[0])}<b>${rp(x[1])}</b><small>${tot ? Math.round(x[1] / tot * 100) : 0}%</small></div>`).join('') || '<div class="mut">Belum ada data.</div>'}</div></div>`;
}
function dkCols(list, cb) { // list: {n, p, s}
  if (!list.length) return '<div class="mut" style="padding:30px;text-align:center">Belum ada data.</div>';
  const col = c => c.p >= 100 ? '#D2453F' : c.p >= 85 ? '#F0A400' : '#1E5FB8';
  let h = ''; [0, 25, 50, 75, 100].forEach(v => { const y = 210 - v * 1.8; h += `<line x1="40" x2="630" y1="${y}" y2="${y}" stroke="#EEF2F7"/><text x="32" y="${y + 4}" text-anchor="end" fill="#94A3B8" font-size="10">${v}%</text>`; });
  h += `<line x1="40" x2="630" y1="${210 - 85 * 1.8}" y2="${210 - 85 * 1.8}" stroke="#F0A400" stroke-dasharray="4 3"/><text x="630" y="${210 - 85 * 1.8 - 5}" text-anchor="end" fill="#C27C00" font-size="10" font-weight="700">batas waspada 85%</text>`;
  const w = 590 / list.length;
  list.forEach((c, k) => { const v = Math.min(c.p, 100), x = 40 + k * w + w * .25, bw = Math.min(w * .5, 60), hh = Math.max(v * 1.8, 2);
    h += `<g class="dk-colg" data-col="${esc(c.n)}" style="cursor:pointer"><rect x="${40 + k * w}" y="0" width="${w}" height="250" fill="transparent"/><rect x="${x}" y="${210 - hh}" width="${bw}" height="${hh}" rx="5" fill="${col(c)}"><title>${esc(c.n)}</title></rect><text x="${x + bw / 2}" y="${204 - hh}" text-anchor="middle" font-weight="800" font-size="11" fill="${col(c)}">${c.p >= 100 ? 'OVER ' : ''}${c.p}%</text><text x="${x + bw / 2}" y="228" text-anchor="middle" fill="#0F172A" font-weight="700" font-size="11">${esc(c.n.length > 14 ? c.n.slice(0, 13) + '…' : c.n)}</text><text x="${x + bw / 2}" y="242" text-anchor="middle" fill="#94A3B8" font-size="10">${esc(c.s)}</text></g>`; });
  return `<svg viewBox="0 0 640 250" width="100%" font-family="inherit">${h}</svg>`;
}
const _monitor = R.monitor;
R.monitor = async () => {
  if (!isDesk()) return _monitor();
  /* mockup dsm-klaim-desktop-budget2.html: gaya sama dengan Realisasi */
  const j = await api('monitor', MF); const s = j.sum, rows = j.rows;
  const RC = ['#1E5FB8', '#12866A', '#6D3FD8', '#D97706', '#DB2777', '#0E7C6B', '#475569', '#C2410C'];
  const used = r => r.realized + r.reserved, pct = r => r.budget ? used(r) * 100 / r.budget : (used(r) > 0 ? Infinity : 0);
  const stOf = r => r.available < 0 ? 'm' : pct(r) >= 85 ? 'w' : '';
  const pTxt = (b, u, av) => av < 0 ? (b ? '<span class="dk-pct r">MINUS</span>' : '<span class="dk-pct r">TANPA BUDGET</span>') : b ? `<span class="dk-pct ${u * 100 / b >= 85 ? 'a' : 'g'}">${(u * 100 / b).toFixed(u * 100 / b < 10 && u ? 1 : 0).replace('.', ',')}%</span>` : '<span class="mut">-</span>';
  const reg = {}; rows.forEach(r => { const k = r.region || '-'; (reg[k] ||= { n: k, rows: [], b: 0, re: 0, rs: 0, av: 0, c: 0, ba: 0, m: 0 }); const g = reg[k]; g.rows.push(r); g.b += r.budget; g.re += r.realized; g.rs += r.reserved; g.av += r.available; g.c += r.count; g.ba += r.ba || 0; if (r.available < 0) g.m++; });
  const regs = Object.values(reg).sort((a, b) => a.n.localeCompare(b.n)); regs.forEach((g, i) => { g.col = RC[i % RC.length]; g.rows.sort((a, b) => pct(b) - pct(a)); });
  const nW = rows.filter(r => stOf(r) === 'w').length, nM = rows.filter(r => stOf(r) === 'm').length, nA = rows.length - nW - nM;
  const fb = `<div class="dk-fb"><input type="month" id="dkmp" value="${esc(MF.period)}"><select id="dkmr"><option value="">Region: Semua</option>${j.regions.map(r => `<option ${MF.region === r ? 'selected' : ''}>${esc(r)}</option>`).join('')}</select><select id="dkmk"><option value="">Kategori: Semua</option>${CATS_LIST.map(c => `<option ${MF.category === c ? 'selected' : ''}>${c}</option>`).join('')}</select><input id="dkmq" placeholder="Cari nama" style="width:200px"><div class="dk-seg"><span class="on" data-mf="">SEMUA</span><span data-mf="w">HAMPIR HABIS</span><span data-mf="m">MINUS</span></div><span id="dkmrg"></span></div>`;
  const kp = `<div class="dk-kp6"><div><small>BUDGET</small><b>${rp(s.budget)}</b><span>${rows.length} orang · ${regs.length} region</span></div><div><small>TERPAKAI</small><b>${rp(s.realized)}</b><span>${s.budget ? (s.realized * 100 / s.budget).toFixed(1).replace('.', ',') + '% dari budget' : '-'}</span></div><div><small>DIPROSES</small><b>${rp(s.reserved)}</b><span>masih di approval</span></div><div><small>AVAILABLE</small><b style="color:${s.available < 0 ? '#D2453F' : '#0B5CAD'}">${rp(s.available)}</b><span>${s.budget ? (s.available * 100 / s.budget).toFixed(1).replace('.', ',') + '% sisa' : '-'}</span></div><div><small>OVER BUDGET</small><b style="color:#D2453F">${rp(s.over)}</b><span>${nM} orang minus</span></div><div class="hl"><small>PERLU PERHATIAN</small><b>${nW + nM} orang</b><span>${nW} hampir habis · ${nM} minus</span></div></div>`;
  const regTb = `<table class="dk-tb"><thead><tr><th>REGION</th><th class="n">ORANG</th><th class="n">BUDGET</th><th class="n">TERPAKAI</th><th class="n">DIPROSES</th><th class="n">AVAILABLE</th><th class="n">PEMAKAIAN</th><th class="n">MINUS</th></tr></thead><tbody>${regs.map(g => `<tr data-rgp="${esc(g.n)}" style="--bc:${g.col}"><td><span class="dk-bun"><i></i>${esc(g.n)}</span></td><td class="n">${g.rows.length}</td><td class="n">${dkNum(g.b)}</td><td class="n">${dkNum(g.re)}</td><td class="n">${dkNum(g.rs)}</td><td class="n ${g.av < 0 ? 'neg' : ''}"><b>${dkNum(g.av)}</b></td><td class="n">${pTxt(g.b, g.re + g.rs, g.av)}</td><td class="n">${g.m ? `<span class="dk-tg2 r">${g.m}</span>` : '<span class="mut">0</span>'}</td></tr>`).join('')}</tbody><tfoot><tr><td>TOTAL · ${regs.length} region</td><td class="n">${rows.length}</td><td class="n">${dkNum(s.budget)}</td><td class="n">${dkNum(s.realized)}</td><td class="n">${dkNum(s.reserved)}</td><td class="n">${dkNum(s.available)}</td><td class="n">${s.budget ? ((s.realized + s.reserved) * 100 / s.budget).toFixed(1).replace('.', ',') + '%' : '-'}</td><td class="n">${nM}</td></tr></tfoot></table>`;
  const att = rows.filter(r => stOf(r)).sort((a, b) => a.available - b.available).slice(0, 8);
  let acc = 0; const seg = [[nA, '#12866A'], [nW, '#F0A400'], [nM, '#D2453F']].filter(x => x[0]).map(([v, c]) => { const p = v / (rows.length || 1) * 100, o = 25 - acc; acc += p; return `<circle cx="21" cy="21" r="15.9" fill="none" stroke="${c}" stroke-width="7" stroke-dasharray="${p} ${100 - p}" stroke-dashoffset="${o}"/>`; }).join('');
  const pc = n => rows.length ? Math.round(n * 100 / rows.length) + '%' : '0%';
  const stat = `<div class="bd"><div class="dk-dn2"><svg viewBox="0 0 42 42"><circle cx="21" cy="21" r="15.9" fill="none" stroke="#EEF2F7" stroke-width="7"/>${seg}<text x="21" y="22.5" text-anchor="middle" font-size="5.5" font-weight="800">${rows.length}</text></svg><div class="dk-dl2"><div><span class="dk-dot" style="--c:#12866A"></span>Aman<b>${nA} orang</b><small>${pc(nA)}</small></div><div><span class="dk-dot" style="--c:#F0A400"></span>Hampir habis ≥85%<b>${nW} orang</b><small>${pc(nW)}</small></div><div><span class="dk-dot" style="--c:#D2453F"></span>Minus<b>${nM} orang</b><small>${pc(nM)}</small></div></div></div>
    <div class="dk-sub">PERLU PERHATIAN</div><div class="dk-rk2">${att.map(r => `<div data-go="#/person/${r.id}" style="cursor:pointer"><span class="dk-tg2 ${stOf(r) === 'm' ? 'r' : 'a'}">${stOf(r) === 'm' ? 'MINUS' : Math.round(pct(r)) + '%'}</span>${esc(r.name)} · ${esc(r.region || '-')}<b class="${r.available < 0 ? 'neg' : ''}">${dkNum(r.available)}</b></div>`).join('') || '<div class="mut">Tidak ada. Semua aman.</div>'}</div></div>`;
  const pTb = `<table class="dk-tb"><thead><tr><th>REGION</th><th>NAMA</th><th>JABATAN</th><th class="n">BUDGET</th><th class="n">TERPAKAI</th><th class="n">DIPROSES</th><th class="n">AVAILABLE</th><th class="n">PEMAKAIAN</th><th class="n">KLAIM</th><th class="n">BA</th></tr></thead><tbody>${regs.map(g => g.rows.map((r, i) => `<tr data-go="#/person/${r.id}" data-rg="${esc(g.n)}" data-st="${stOf(r)}" data-q="${esc((r.name + ' ' + (r.job || '')).toLowerCase())}" class="${i ? '' : 'dk-bu1'}" style="--bc:${g.col}"><td>${i ? '' : `<span class="dk-bun"><i></i>${esc(g.n)}</span>`}</td><td><b>${esc(r.name)}</b></td><td>${esc(r.job || '-')}</td><td class="n">${dkNum(r.budget)}</td><td class="n">${dkNum(r.realized)}</td><td class="n">${dkNum(r.reserved)}</td><td class="n ${r.available < 0 ? 'neg' : ''}"><b>${dkNum(r.available)}</b></td><td class="n">${pTxt(r.budget, used(r), r.available)}</td><td class="n">${r.count}</td><td class="n">${r.ba || '<span class="mut">-</span>'}</td></tr>`).join('') + `<tr class="dk-busub" data-rg="${esc(g.n)}" data-sub="1" style="--bc:${g.col}"><td colspan="3">Subtotal ${esc(g.n)} · ${g.rows.length} orang</td><td class="n">${dkNum(g.b)}</td><td class="n">${dkNum(g.re)}</td><td class="n">${dkNum(g.rs)}</td><td class="n ${g.av < 0 ? 'neg' : ''}">${dkNum(g.av)}</td><td class="n">${pTxt(g.b, g.re + g.rs, g.av)}</td><td class="n">${g.c}</td><td class="n">${g.ba || '<span class="mut">-</span>'}</td></tr>`).join('')}</tbody><tfoot><tr><td colspan="3">TOTAL · ${rows.length} orang</td><td class="n">${dkNum(s.budget)}</td><td class="n">${dkNum(s.realized)}</td><td class="n">${dkNum(s.reserved)}</td><td class="n">${dkNum(s.available)}</td><td class="n">${s.budget ? ((s.realized + s.reserved) * 100 / s.budget).toFixed(1).replace('.', ',') + '%' : '-'}</td><td class="n">${rows.reduce((a, r) => a + r.count, 0)}</td><td class="n">${rows.reduce((a, r) => a + (r.ba || 0), 0)}</td></tr></tfoot></table>`;
  const pn = (t, inner, sub = '', cls = '') => `<div class="dk-rp ${cls}"><div class="h">${t}${sub ? ` <small>${sub}</small>` : ''}</div>${inner}</div>`;
  shell(hero('Monitoring Budget', 'READ-ONLY') + budTabs('monitor') + `<div class="dk-bud dk-real">${fb}${kp}<div class="dk-rg">${pn('Pemakaian per region', regTb, 'klik baris untuk saring tabel orang', 's2')}${pn('Status pemakaian', stat)}</div><div class="dk-rg">${pn('Budget per orang', pTb, 'dikelompokkan per region · klik baris untuk rincian klaim', 's3')}</div>${rows.length ? '' : emptyBox('Tidak ada data dalam filter ini.')}</div>`, { hero: false });
  let mf = '', rg = '';
  const apply = () => { const q = $('#dkmq').value.trim().toLowerCase();
    $$('.dk-rp.s3 tbody tr').forEach(tr => { const okR = !rg || tr.dataset.rg === rg; tr.style.display = tr.dataset.sub ? (okR && !q && !mf ? '' : 'none') : okR && (!q || tr.dataset.q.includes(q)) && (!mf || tr.dataset.st === mf) ? '' : 'none'; });
    $$('[data-rgp]').forEach(tr => tr.classList.toggle('sel', tr.dataset.rgp === rg));
    $('#dkmrg').innerHTML = rg ? `<span class="dk-chipx">Region: ${esc(rg)} <b data-rgx>×</b></span>` : ''; $('[data-rgx]') && ($('[data-rgx]').onclick = () => { rg = ''; apply(); }); };
  $('#dkmq').oninput = apply; $$('[data-mf]').forEach(c => c.onclick = () => { mf = c.dataset.mf; $$('[data-mf]').forEach(x => x.classList.toggle('on', x === c)); apply(); });
  $$('[data-rgp]').forEach(tr => tr.onclick = () => { rg = rg === tr.dataset.rgp ? '' : tr.dataset.rgp; apply(); });
  const reload = () => { MF = { ...MF, period: $('#dkmp').value || MF.period, region: $('#dkmr').value, category: $('#dkmk').value }; R.monitor(); };
  ['dkmp', 'dkmr', 'dkmk'].forEach(id => $('#' + id).onchange = reload);
};


function dkTren(tren, gran) {
  if (!tren || !tren.length) return '<div class="mut">Belum ada transaksi.</div>';
  const W = 640, base = 150, top = 22, n = tren.length, mx = Math.max(...tren.map(t => t.v), 1), cm = Math.max(...tren.map(t => t.cum), 1), st = (W - 50) / n, bw = Math.min(34, st * .55), every = Math.ceil(n / 14);
  const X = i => 40 + st * i + st / 2, lab = t => gran === 'bulan' ? t.k : t.k.slice(8) + '/' + t.k.slice(5, 7);
  let h = [0, .5, 1].map(f => `<line x1="40" x2="${W}" y1="${base - f * (base - top)}" y2="${base - f * (base - top)}" stroke="#EEF2F7"/><text x="34" y="${base - f * (base - top) + 3}" text-anchor="end" font-size="9" fill="#94A3B8">${f ? dkJt(mx * f) : '0'}</text>`).join('');
  tren.forEach((t, i) => { const hh = Math.max(2, t.v / mx * (base - top)); h += `<rect x="${X(i) - bw / 2}" y="${base - hh}" width="${bw}" height="${hh}" rx="3" fill="#3B78C2"><title>${esc(lab(t))} · ${rp(t.v)}</title></rect>${n <= 12 ? `<text x="${X(i)}" y="${base - hh - 4}" text-anchor="middle" font-size="9" font-weight="700" fill="#0F172A">${dkJt(t.v)}</text>` : ''}${i % every === 0 ? `<text x="${X(i)}" y="${base + 13}" text-anchor="middle" font-size="9" fill="#64748B">${esc(lab(t))}</text>` : ''}`; });
  h += `<polyline fill="none" stroke="#12866A" stroke-width="2" points="${tren.map((t, i) => `${X(i)},${base - t.cum / cm * (base - top)}`).join(' ')}"/>`;
  return `<svg viewBox="0 0 ${W} 168" width="100%" style="display:block">${h}</svg><div class="dk-lgd"><span style="--c:#3B78C2">per ${gran}</span><span style="--c:#12866A">kumulatif (total ${rp(tren[n - 1].cum)})</span></div>`;
}
/* ---------- Realisasi (HOS/FM) desktop ---------- */
const _realFM = realisasiFM;
realisasiFM = function (j, x) {
  if (!isDesk()) return _realFM(j, x);
  /* satu halaman tanpa tab (mockup dsm-klaim-desktop-realisasi.html) */
  const f = j.fm, k = f.kpi; const d = k.prev ? Math.round((k.total - k.prev) * 100 / k.prev) : null;
  const CC = { Transportasi: '#3B78C2', Entertain: '#C97A4A', Perdin: '#8268C9', Operasional: '#3E9486' }, EC = ['#1E5FB8', '#12866A', '#6D3FD8', '#D97706', '#DB2777', '#475569'];
  const dot = c => `<span class="dk-dot" style="--c:${c}"></span>`;
  const pn = (t, inner, sub = '', cls = '') => `<div class="dk-rp ${cls}"><div class="h">${t}${sub ? ` <small>${sub}</small>` : ''}</div>${inner}</div>`;
  const list = (o, col) => { const e = Object.entries(o || {}), tot = e.reduce((a, x) => a + x[1], 0); return e.map(([n, v], i) => `<div>${dot(col(n, i))}${esc(n)}<b>${dkNum(v)}</b><small>${tot ? Math.round(v * 100 / tot) : 0}%</small></div>`).join('') || '<div class="mut">Belum ada data.</div>'; };
  const catE = Object.entries(f.byCat || {}), catT = catE.reduce((a, x) => a + x[1], 0); let acc = 0;
  const donut = `<svg viewBox="0 0 42 42"><circle cx="21" cy="21" r="15.9" fill="none" stroke="#EEF2F7" stroke-width="7"/>${catT ? catE.filter(x => x[1] > 0).map(([n, v]) => { const p = v / catT * 100, o = 25 - acc; acc += p; return `<circle cx="21" cy="21" r="15.9" fill="none" stroke="${CC[n] || '#94A3B8'}" stroke-width="7" stroke-dasharray="${p} ${100 - p}" stroke-dashoffset="${o}"/>`; }).join('') : ''}<text x="21" y="22.5" text-anchor="middle" font-size="5" font-weight="800">${dkJt(k.total).replace(' ', '')}</text></svg>`;
  // budget
  let bB = 0, bR = 0; f.bud.forEach(b => b.cats.forEach(c => { if (!NOBUD.includes(c.cat)) { bB += c.budget; bR += c.real; } }));
  const pcls = p => p > 100 ? 'r' : p >= 85 ? 'a' : 'g';
  const budRows = f.bud.map((bu, bi) => { let sb = 0, sr = 0; bu.cats.forEach(c => { if (!NOBUD.includes(c.cat)) { sb += c.budget; sr += c.real; } }); const rt = bu.cats.reduce((a, c) => a + c.real, 0); return bu.cats.map((c, i) => { const nb = NOBUD.includes(c.cat), p = !nb && c.budget ? Math.round(c.real * 100 / c.budget) : null;
    return `<tr><td>${i ? '' : `<span class="dk-bun"><i></i>${esc(bu.entity)}</span>`}</td><td>${dot(CC[c.cat] || '#94A3B8')}${esc(c.cat)}</td><td class="n">${nb ? '<span class="mut">tanpa budget</span>' : dkNum(c.budget)}</td><td class="n"><b>${dkNum(c.real)}</b></td><td class="n ${!nb && c.budget - c.real < 0 ? 'neg' : ''}">${nb ? '<span class="mut">-</span>' : dkNum(c.budget - c.real)}</td><td class="n">${p == null ? (!nb && c.real ? '<span class="dk-pct r">TANPA BUDGET</span>' : '<span class="mut">-</span>') : `<span class="dk-pct ${pcls(p)}">${p}%</span>`}</td></tr>`; }).join('').replace('<tr>', `<tr class="dk-bu1" style="--bc:${EC[bi % EC.length]}">`) + `<tr class="dk-busub" style="--bc:${EC[bi % EC.length]}"><td colspan="2">Subtotal ${esc(bu.entity)}</td><td class="n">${dkNum(sb)}</td><td class="n">${dkNum(rt)}</td><td class="n ${sb - sr < 0 ? 'neg' : ''}">${dkNum(sb - sr)}</td><td class="n">${sb ? `<span class="dk-pct ${pcls(sr * 100 / sb)}">${(sr * 100 / sb).toFixed(1).replace('.', ',')}%</span>` : '-'}</td></tr>`; }).join('');
  const budTb = f.bud.length ? `<table class="dk-tb"><thead><tr><th>BADAN USAHA</th><th>KATEGORI</th><th class="n">BUDGET</th><th class="n">REALISASI</th><th class="n">SISA</th><th class="n">PEMAKAIAN</th></tr></thead><tbody>${budRows}</tbody><tfoot><tr><td>TOTAL</td><td></td><td class="n">${dkNum(bB)}</td><td class="n">${dkNum(bR)}</td><td class="n">${dkNum(bB - bR)}</td><td class="n">${bB ? `<span class="dk-pct ${pcls(bR * 100 / bB)}">${(bR * 100 / bB).toFixed(1).replace('.', ',')}%</span>` : '-'}</td></tr></tfoot></table>` : '<div class="bd mut">Belum ada budget atau realisasi pada periode ini.</div>';
  const topE = Object.entries(f.top || {}).sort((a, b) => b[1] - a[1]), topT = topE.reduce((a, x) => a + x[1], 0);
  const top = `<div class="bd dk-rk2">${topE.slice(0, 8).map(([n, v], i) => `<div><em>${i + 1}</em>${esc(n)}<b>${dkNum(v)}</b><small>${topT ? Math.round(v * 100 / topT) : 0}%</small></div>`).join('') || '<div class="mut">Belum ada data.</div>'}</div>`;
  const trx = `<table class="dk-tb"><thead><tr><th>WAKTU</th><th>REF</th><th>PEMOHON</th><th>JENIS</th><th>NO. TRANSFER</th><th class="n">NOMINAL</th></tr></thead><tbody>${j.trx.map(t => `<tr><td>${wktS(t.at)}</td><td><b>${esc(t.ref)}</b></td><td>${esc(t.who)}</td><td>${esc(t.kind)}</td><td>${esc(t.transfer_no)}</td><td class="n"><b>${dkNum(t.amount)}</b></td></tr>`).join('') || '<tr><td colspan="6" class="mut">Belum ada transaksi.</td></tr>'}</tbody></table>`;
  const ba = f.ba.cnt.length ? f.ba.cnt.map((c, i) => `<div><em>${c}</em>${esc(f.ba.label[i])}</div>`).join('') : '<div class="mut">Tidak ada klaim BA pada periode ini.</div>';
  const pc = [['Dana cair', j.pc.cair], ['Terealisasi', j.pc.real], ['Sisa disetor', j.pc.setor], ['Belum dipertanggungjawabkan', j.pc.belum]].map(([a, b]) => `<div><small>${a}</small><b>${rp(b)}</b></div>`).join('');
  const kp = `<div class="dk-kp6"><div><small>TOTAL REALISASI</small><b>${rp(k.total)}</b><span>vs periode lalu ${d == null ? '<i>baru</i>' : `<i class="${d >= 0 ? 'up' : 'dn'}">${d >= 0 ? '▲' : '▼'} ${Math.abs(d)}%</i>`}</span></div><div><small>KLAIM TERTRANSFER</small><b>${k.n} klaim</b><span>rata-rata ${rp(Math.round(k.avg))}</span></div><div><small>BUDGET TERPAKAI</small><b>${bB ? (bR * 100 / bB).toFixed(1).replace('.', ',') + '%' : '-'}</b><span>dari ${rp(bB)}</span></div><div><small>KLAIM MEMAKAI BA</small><b>${k.ba_n}${k.n ? ' (' + Math.round(k.ba_n * 100 / k.n) + '%)' : ''}</b><span>${rp(k.ba_total)}</span></div><div><small>PETTY CASH CAIR</small><b>${rp(j.pc.cair)}</b><span>sisa disetor ${rp(j.pc.setor)}</span></div><div class="hl"><small>PC BELUM DIPERTANGGUNGJAWABKAN</small><b>${rp(k.pc_belum)}</b><span>${k.pc_belum_n} petty cash</span></div></div>`;
  const exp = `api.php?a=export${x.rmode === 'rentang' ? '&from=' + x.d1 + '&to=' + x.d2 : ''}`;
  const fb = `<div class="dk-fb"><select id="rmode"><option value="bulan" ${x.rmode === 'bulan' ? 'selected' : ''}>Periode (bulan)</option><option value="rentang" ${x.rmode === 'rentang' ? 'selected' : ''}>Rentang tanggal</option></select>${x.rmode === 'bulan' ? `<input type="month" id="rp" value="${x.per}">` : `<input type="date" id="rd1" value="${x.d1}"><input type="date" id="rd2" value="${x.d2}">`}<select id="rent"><option value="">Badan usaha: Semua</option>${j.scope.map(e => `<option ${x.rent === e ? 'selected' : ''}>${esc(e)}</option>`).join('')}</select><a class="dk-b" style="margin-left:auto;display:inline-flex;align-items:center;text-decoration:none" href="${exp}">⬇ EXPORT EXCEL</a></div>`;
  const body = `<div class="dk-rg">${pn('Tren realisasi', `<div class="bd">${dkTren(f.tren, f.gran)}</div>`, 'per ' + f.gran, 's2')}${pn('Komposisi kategori', `<div class="bd"><div class="dk-dn2">${donut}<div class="dk-dl2">${list(f.byCat, n => CC[n] || '#94A3B8')}</div></div><div class="dk-sub">PER BADAN USAHA</div><div class="dk-dl2">${list(f.byEnt, (n, i) => EC[i % EC.length])}</div></div>`)}</div>
    <div class="dk-rg">${pn('Realisasi vs budget', budTb, `per badan usaha &amp; kategori${f.months > 1 ? ' · budget × ' + f.months + ' bulan' : ''}`, 's2')}${pn('Top pemohon', top)}</div>
    <div class="dk-rg">${pn('Transaksi', trx, j.trx.length + ' transfer', 's2')}${pn('Sorotan BA &amp; petty cash', `<div class="bd"><div class="dk-rk2 b">${ba}</div><div class="dk-sub">PETTY CASH</div><div class="dk-pc">${pc}</div></div>`)}</div>`;
  shell(hero('Realisasi', 'F05 · READ-ONLY') + budTabs('realisasi') + `<div class="dk-bud dk-real">${fb}${kp}${body}</div>`, { hero: false });
  $('#rp') && ($('#rp').onchange = e => { sessionStorage.setItem('rper', e.target.value); R.realisasi(); });
  $('#rmode').onchange = e => { sessionStorage.setItem('rmode', e.target.value); R.realisasi(); };
  $('#rent').onchange = e => { sessionStorage.setItem('rent', e.target.value); R.realisasi(); };
  ['rd1', 'rd2'].forEach(q => $('#' + q) && ($('#' + q).onchange = e => { sessionStorage.setItem(q, e.target.value); R.realisasi(); }));
};

/* ---------- Kode cadangan desktop (mockup dsm-klaim-desktop-kode.html) ---------- */
let DKB = []; const _api3 = api; api = async (f, a) => { const r = await _api3(f, a); if (f === 'backup_list') DKB = r.rows || []; return r; };
function dkCodes(mn) {
  const sec = mn.querySelector('.sec'); const form = sec?.querySelector('.card'), cl = $('#clist'); if (!form || !cl) return;
  const note = sec.querySelector('.note.blue'), h3 = sec.querySelector('h3');
  const wrap = document.createElement('div'); wrap.className = 'dk-kc';
  wrap.innerHTML = `<div class="dk-rp dk-kf"><div class="h">Terbitkan kode baru</div><div class="bd"></div></div><div style="min-width:0"><div class="dk-kp4" id="dkcs"></div><div class="dk-rp"><div class="h">Daftar kode <small id="dkcn"></small></div><div class="dk-cfb"><div class="dk-seg" id="dkcf">${[['', 'SEMUA'], ['AKTIF', 'AKTIF'], ['BELUM', 'BELUM DIPAKAI'], ['SELESAI', 'SELESAI'], ['KEDALUWARSA', 'KEDALUWARSA'], ['DICABUT', 'DICABUT']].map((x, i) => `<span data-cf="${x[0]}" class="${i ? '' : 'on'}">${x[1]}</span>`).join('')}</div><input id="dkcq" placeholder="Cari nama"></div><div id="dkct" style="overflow-x:auto"></div></div></div>`;
  sec.before(wrap); wrap.querySelector('.dk-kf .bd').appendChild(form); form.classList.add('dk-kform');
  if (note) { note.remove(); form.insertAdjacentHTML('beforeend', `<div class="dk-rule"><div>Kode untuk satu orang, menggantikan Admin atau Finance Transfer.</div><div>Durasi mulai saat kode dipakai pertama kali; harus dipakai dalam 24 jam.</div><div>Penerima bisa masuk maksimal 5 kali selama durasi.</div><div>Bisa dicabut kapan saja. HOS, Audit, dan Finance Manager tidak punya pengganti.</div></div>`); }
  h3?.remove(); cl.style.display = 'none'; wrap.querySelector('#dkct').after(cl);
  const cr = $('#cr'); if (cr) { cr.style.display = 'none'; cr.insertAdjacentHTML('afterend', `<div class="dk-seg dk-crs">${[...cr.options].map(o => `<span data-crv="${o.value}" class="${o.value === cr.value ? 'on' : ''}">${o.text.toUpperCase()}</span>`).join('')}</div>`);
    const paint = () => $$('[data-crv]').forEach(x => x.classList.toggle('on', x.dataset.crv === cr.value));
    $$('[data-crv]').forEach(x => x.onclick = () => { cr.value = x.dataset.crv; cr.dispatchEvent(new Event('change')); paint(); });
    $('#ch')?.addEventListener('change', () => setTimeout(paint, 0)); setTimeout(paint, 0); }
  let cf = '';
  const LB = { AKTIF: ['AKTIF', 'ak'], BELUM: ['BELUM DIPAKAI', 'be'], SELESAI: ['SELESAI', 'se'], KEDALUWARSA: ['KEDALUWARSA', 'kd'], DICABUT: ['DICABUT', 'dc'] };
  const fmt = x => { const h = Math.floor(x / 3600), m = Math.floor(x % 3600 / 60); return (h ? h + ' jam ' : '') + m + ' mnt'; };
  const dur = r => { const m = r.duration_min || 120; return m >= 1440 && m % 1440 === 0 ? m / 1440 + ' hari' : m / 60 + ' jam'; };
  const t = v => v ? String(v).slice(8, 10) + '/' + String(v).slice(5, 7) + ' ' + String(v).slice(11, 16) : '';
  const draw = () => {
    const L = DKB, cnt = k => L.filter(r => r.status === k).length;
    $('#dkcs').innerHTML = `<div class="g"><small>AKTIF</small><b>${cnt('AKTIF')}</b></div><div class="a"><small>BELUM DIPAKAI</small><b>${cnt('BELUM')}</b></div><div><small>SELESAI</small><b>${cnt('SELESAI')}</b></div><div><small>KEDALUWARSA / DICABUT</small><b>${cnt('KEDALUWARSA') + cnt('DICABUT')}</b></div>`;
    $('#dkcn').textContent = L.length + ' kode';
    const q = ($('#dkcq').value || '').trim().toLowerCase();
    const R2 = L.filter(r => (!cf || r.status === cf) && (!q || [r.to_name, r.for_name, r.issuer_name].join(' ').toLowerCase().includes(q)));
    $('#dkct').innerHTML = `<table class="dk-tb"><thead><tr><th>STATUS</th><th>PENERIMA</th><th>PERAN</th><th>MENGGANTIKAN</th><th>DURASI</th><th>PEMAKAIAN</th><th>DIBERI OLEH</th><th>TERAKHIR</th><th></th></tr></thead><tbody>${R2.map(r => { const lb = LB[r.status] || [r.status, 'se'], mx = r.max_use || 5, u = r.use_count || 0;
      return `<tr><td><span class="dk-pl p-${lb[1]}">${lb[0]}</span></td><td>${r.to_name ? `<b>${esc(r.to_name)}</b>` : '<span class="mut">-</span>'}</td><td><span class="dk-rlc" style="--c:${r.role === 'ADMIN' ? '#1E5FB8' : '#12866A'}">${esc((STAGE[r.role] || r.role).toUpperCase())}</span></td><td>${esc(r.for_name || '-')}</td><td>${dur(r)}${r.status === 'AKTIF' ? `<span class="dk-sm">sisa ${fmt(r.left)}</span>` : ''}</td>
        <td>${r.used_at ? `<span class="dk-use">${Array.from({ length: mx }, (_, i) => `<i class="${i < u ? 'on' : ''}"></i>`).join('')}</span>${u} / ${mx}` : '<span class="mut">belum dipakai</span>'}</td><td>${esc(r.issuer_name || '-')}</td>
        <td>${r.status === 'DICABUT' ? 'dicabut ' + t(r.revoked_at) : r.used_at ? t(r.last_used_at || r.used_at) : '<span class="mut">berlaku s/d ' + t(r.expires_at) + '</span>'}</td><td>${r.status === 'AKTIF' || r.status === 'BELUM' ? `<button class="dk-cb" data-dkrv="${r.id}">${r.status === 'AKTIF' ? 'CABUT AKSES' : 'CABUT KODE'}</button>` : ''}</td></tr>`; }).join('') || '<tr><td colspan="9" class="mut">Belum ada kode.</td></tr>'}</tbody></table>`;
    $$('[data-dkrv]').forEach(b => b.onclick = () => cl.querySelector(`[data-rv="${b.dataset.dkrv}"]`)?.click());
  };
  new MutationObserver(draw).observe(cl, { childList: true });
  $('#dkcq').oninput = draw; $$('[data-cf]').forEach(c => c.onclick = () => { cf = c.dataset.cf; $$('[data-cf]').forEach(x => x.classList.toggle('on', x === c)); draw(); });
  if (cl.children.length) draw();
}
