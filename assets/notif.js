/* Notifikasi khusus & otomatis (Paket 24): panel Kirim / Aturan Otomatis / Riwayat.
   Dipasang di halaman Perangkat (semua role yang punya bawahan) dan di Admin. Memakai helper global (rpc, esc, panel, $, TOKEN). */
(function () {
  'use strict';
  const css = `
.ntW{display:flex;flex-direction:column;gap:12px}
.ntTabs{display:flex;gap:8px;flex-wrap:wrap}.ntTabs button{border:0;border-radius:99px;padding:8px 15px;font:800 12px system-ui;background:#fff;color:#5a6b80;cursor:pointer;box-shadow:0 2px 8px rgba(99,102,241,.12)}.ntTabs button.on{background:linear-gradient(135deg,#6366f1,#06b6d4)!important;color:#fff!important}
.ntGrid{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:16px;align-items:start}@media(max-width:860px){.ntGrid{grid-template-columns:1fr}}
.ntLbl{font:800 11px system-ui;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin:4px 0}
.ntW .fi,.ntW textarea{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:8px 10px;font:inherit;background:#fff;box-sizing:border-box}.ntW textarea{min-height:76px;resize:vertical}
.ntChips{display:flex;gap:6px;flex-wrap:wrap}.ntChips button{border:1px solid #cbd5e1;background:#fff;border-radius:99px;padding:6px 12px;font:700 12px system-ui;color:#475569;cursor:pointer}.ntChips button.on{background:#eef2ff!important;border-color:#6366f1!important;color:#4338ca!important}
.ntInfo{background:#ecfeff;border:1px solid #a5f3fc;border-radius:12px;padding:9px 12px;font-size:12.5px;color:#155e75}.ntWarn{background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:9px 12px;font-size:12.5px;color:#92400e}.ntBad{background:#fee2e2;border:1px solid #fecaca;border-radius:12px;padding:9px 12px;font-size:12.5px;color:#991b1b}.ntOk{background:#dcfce7;border:1px solid #bbf7d0;border-radius:12px;padding:9px 12px;font-size:12.5px;color:#166534}
.ntW .ntBtn{border:0!important;border-radius:99px!important;padding:9px 16px!important;font:800 13px system-ui!important;cursor:pointer;background:linear-gradient(135deg,#6366f1,#06b6d4)!important;color:#fff!important;box-shadow:0 4px 12px rgba(99,102,241,.3)}.ntW .ntBtn.g{background:#fff!important;color:#475569!important;border:1px solid #cbd5e1!important;box-shadow:none}.ntBtn:disabled{opacity:.55;cursor:default}
.ntDrop{display:block;border:2px dashed #a5b4fc;border-radius:14px;padding:12px;text-align:center;color:#6366f1;background:#f5f7ff;font-weight:700;cursor:pointer}.ntDrop img{max-width:100%;max-height:120px;border-radius:10px;display:block;margin:6px auto 0}
.ntPh{width:290px;max-width:100%;margin:0 auto;border:8px solid #1e293b;border-radius:28px;background:linear-gradient(#cbd5e1,#e2e8f0);padding:12px 9px;box-sizing:border-box}.ntToast{background:rgba(255,255,255,.96);border-radius:16px;padding:10px 12px;box-shadow:0 6px 18px rgba(15,23,42,.18);display:flex;flex-direction:column;gap:5px;font-size:13px}.ntToast .h{font-size:11px;color:#64748b}.ntToast img{width:100%;max-height:130px;object-fit:cover;border-radius:10px}
.ntRule{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:10px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}.ntRule>div{flex:1 1 220px}.ntRule small{display:block;color:#64748b}
.ntPeople{max-height:180px;overflow:auto;border:1px solid #e2e8f0;border-radius:10px;background:#fff;padding:4px 8px}.ntPeople label{display:flex;gap:8px;align-items:center;padding:3px 0;font-size:13px}
.ntTb{width:100%;border-collapse:collapse;font-size:13px;background:#fff;border-radius:12px;overflow:hidden}.ntTb th{background:#eef2ff;color:#3730a3;text-align:left;padding:8px 10px;font:800 11px system-ui;letter-spacing:.05em;text-transform:uppercase}.ntTb td{padding:8px 10px;border-top:1px solid #eef2f7;vertical-align:top}
.ntB{display:inline-block;border-radius:99px;padding:2px 9px;font:800 10.5px system-ui}.ntB.k{background:#ede9fe;color:#5b21b6}.ntB.o{background:#fef3c7;color:#92400e}.ntB.s{background:#dcfce7;color:#166534}.ntB.j{background:#e0f2fe;color:#075985}.ntB.x{background:#e2e8f0;color:#475569}
@media(max-width:600px){.ntTb{display:block;overflow-x:auto}}`;
  const st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  const E = s => esc(String(s ?? ''));
  const PAGES = { '': 'Tanpa tujuan', beranda: 'Beranda', customer: 'Dealer', brand: 'Brand', tim: 'Tim', kpi: 'KPI', dos: 'DOS', peringkat: 'Peringkat', transaksi: 'Transaksi', analisa: 'Analisa' };
  let NT = null, S = { tab: 'kirim', ttype: 'all', role: '', region: '', niks: new Set(), q: '', title: '', body: '', link: 'dos', important: false, when: false, at: '', image: '', imageName: '', msg: null, busy: false, cnt: null }, HIS = null, timer = null;

  window.ntPanel = async function (v) {
    if (!v || !v.isConnected || v.querySelector('#ntApp')) return;
    let st; try { st = await rpc('apiNotifState', TOKEN); } catch (e) { return; }
    if (!v.isConnected || v.querySelector('#ntApp')) return;
    if (!st.canSend && !st.admin) return;
    NT = st; if (!st.canSend && S.tab === 'kirim') S.tab = 'oto';
    v.insertAdjacentHTML('beforeend', panel('Notifikasi Khusus', '<div id="ntApp" class="ntW"></div>', 'Kirim pesan ke tim dan atur notifikasi otomatis'));
    const box = $('ntApp'); if (!box) return;
    box.onclick = onClick; box.onchange = onChange; box.oninput = onInput;
    draw(); preview();
  };

  function tabs() { const t = []; if (NT.canSend) t.push(['kirim', 'Kirim Notifikasi']); if (NT.admin) t.push(['oto', 'Aturan Otomatis']); t.push(['riw', 'Riwayat']); return t; }
  function draw() {
    const box = $('ntApp'); if (!box || !NT) return;
    box.innerHTML = `<div class="ntTabs">${tabs().map(([k, l]) => `<button type="button" data-nt="${k}" class="${S.tab === k ? 'on' : ''}">${l}</button>`).join('')}</div><div id="ntBody">${S.tab === 'kirim' ? kirim() : S.tab === 'oto' ? oto() : riw()}</div>`;
    if (S.tab === 'riw' && !HIS) loadHis();
  }
  function targetObj() { return S.ttype === 'all' ? { type: 'all' } : S.ttype === 'role' ? { type: 'role', role: S.role } : S.ttype === 'region' ? { type: 'region', region: S.region } : { type: 'nik', niks: [...S.niks] }; }
  function kirim() {
    const roles = Object.entries(NT.roles), regs = Object.entries(NT.regions);
    const people = NT.people.filter(p => !S.q || (p.nama + ' ' + p.nik + ' ' + p.role).toLowerCase().includes(S.q.toLowerCase())).slice(0, 120);
    const sel = `<div class="ntChips" id="ntSas"><button type="button" data-tt="all" class="${S.ttype === 'all' ? 'on' : ''}">Semua tim saya (${NT.total})</button><button type="button" data-tt="role" class="${S.ttype === 'role' ? 'on' : ''}">Per role</button>${regs.length ? `<button type="button" data-tt="region" class="${S.ttype === 'region' ? 'on' : ''}">Per region</button>` : ''}<button type="button" data-tt="nik" class="${S.ttype === 'nik' ? 'on' : ''}">Orang tertentu</button></div>`;
    const sub = S.ttype === 'role' ? `<div class="ntChips">${roles.map(([r, n]) => `<button type="button" data-role="${E(r)}" class="${S.role === r ? 'on' : ''}">${E(r)} (${n})</button>`).join('')}</div>`
      : S.ttype === 'region' ? `<div class="ntChips">${regs.map(([r, n]) => `<button type="button" data-region="${E(r)}" class="${S.region === r ? 'on' : ''}">${E(r)} (${n})</button>`).join('')}</div>`
      : S.ttype === 'nik' ? `<input class="fi" id="ntQ" placeholder="Cari nama, NIK, atau jabatan" value="${E(S.q)}"><div class="ntPeople" id="ntPeople">${people.map(p => `<label><input type="checkbox" data-nik="${E(p.nik)}" ${S.niks.has(p.nik) ? 'checked' : ''}> ${E(p.nama)} <small>· ${E(p.role)}${p.region ? ' · ' + E(p.region) : ''}</small></label>`).join('') || '<small>Tidak ada hasil.</small>'}</div>` : '';
    const warns = (!NT.pushOn ? '<div class="ntWarn">Notifikasi belum diaktifkan di server (DSM_PUSH_ON). Pesan akan tercatat di riwayat tetapi belum bisa terkirim.</div>' : '') + (S.when && !NT.cronOk ? '<div class="ntWarn">Pengatur jadwal (cron notif_cli.php) belum terdeteksi aktif. Pesan terjadwal baru terkirim setelah cron berjalan.</div>' : '');
    return `<div class="ntGrid"><div class="ntW">
      <div class="ntLbl">Sasaran (sesuai cakupan Anda)</div>${sel}${sub}
      <div class="ntInfo" id="ntCnt">${cntText()}</div>
      <div><div class="ntLbl">Judul</div><input class="fi" id="ntTitle" maxlength="60" value="${E(S.title)}" placeholder="Contoh: Target DOS minggu ini"></div>
      <div><div class="ntLbl">Isi pesan</div><textarea id="ntBody2" maxlength="160" placeholder="Maks. 160 karakter">${E(S.body)}</textarea><small id="ntLen">${S.body.length}/160</small></div>
      <div><div class="ntLbl">Gambar (opsional)</div><label class="ntDrop" for="ntFile">${S.image ? '✓ ' + E(S.imageName) + ' · klik untuk ganti' : '+ Pilih gambar (JPG/PNG, otomatis dikecilkan ≤ 1 MB)'}${S.image ? `<img src="${S.image}" alt="">` : ''}</label><input type="file" id="ntFile" accept="image/jpeg,image/png" hidden>${S.image ? '<button type="button" class="ntBtn g" data-act="rmimg" style="margin-top:6px">Hapus gambar</button>' : ''}</div>
      <div><div class="ntLbl">Buka halaman saat diketuk</div><select class="fi" id="ntLink">${Object.entries(PAGES).map(([k, l]) => `<option value="${k}" ${S.link === k ? 'selected' : ''}>${l}</option>`).join('')}</select></div>
      <div><div class="ntLbl">Kirim</div><div class="ntChips"><button type="button" data-when="0" class="${!S.when ? 'on' : ''}">Sekarang</button><button type="button" data-when="1" class="${S.when ? 'on' : ''}">Jadwalkan…</button></div>${S.when ? `<input class="fi" type="datetime-local" id="ntAt" value="${E(S.at)}" style="margin-top:6px;max-width:260px">` : ''}</div>
      ${NT.important ? `<label class="ntLbl" style="display:flex;gap:8px;align-items:center;text-transform:none;letter-spacing:0"><input type="checkbox" id="ntImp" ${S.important ? 'checked' : ''}> Tandai Penting (menembus jam tenang pengguna)</label>` : ''}
      ${warns}<div id="ntMsg">${S.msg || ''}</div>
      <div class="ntChips"><button type="button" class="ntBtn" data-act="send" ${S.busy ? 'disabled' : ''}>${S.when ? 'Jadwalkan Notifikasi' : 'Kirim Notifikasi'}</button><button type="button" class="ntBtn g" data-act="test" ${S.busy ? 'disabled' : ''}>Kirim tes ke saya</button></div>
    </div><div><div class="ntLbl">Pratinjau HP</div><div class="ntPh"><div class="ntToast"><div class="h"><b>DSM</b> · sekarang</div><b id="ntPT">${E(S.title || 'Judul notifikasi')}</b><span id="ntPB">${E(S.body || 'Isi pesan akan tampil di sini.')}</span>${S.image ? `<img src="${S.image}" alt="">` : ''}</div></div><p class="hint" style="font-size:11.5px;color:#64748b">Gambar besar tampil di Android/Chrome. iPhone hanya menampilkan judul dan isi.</p></div></div>`;
  }
  function cntText() {
    if (!S.cnt) return 'Memeriksa penerima…';
    return `Akan dikirim ke <b>${S.cnt.count} orang</b> · ${S.cnt.active} sudah mengaktifkan notifikasi, ${S.cnt.count - S.cnt.active} belum (tidak menerima).`;
  }
  function preview() {
    if (S.tab !== 'kirim') return; clearTimeout(timer);
    timer = setTimeout(async () => { try { S.cnt = await rpc('apiNotifPreview', TOKEN, targetObj()); } catch (e) { S.cnt = { count: 0, active: 0 }; } const c = $('ntCnt'); if (c) c.innerHTML = cntText(); }, 250);
  }
  function oto() {
    return `<div class="ntInfo">Aturan otomatis dijalankan pengatur jadwal (cron <code>notif_cli.php</code>) dan hanya terkirim ke orang yang sudah mengaktifkan notifikasi. ${NT.cronOk ? '<b>Cron terdeteksi aktif.</b>' : '<b>Cron belum terdeteksi.</b>'}</div>` + NT.rules.map(r => `<div class="ntRule"><div><b>${E(r.label)}</b><small>${E(r.desc)}</small>${r.lastRun ? `<small>Terakhir jalan: ${E(r.lastRun)}</small>` : ''}</div>${r.freq === 'EVENT' ? '' : `<input class="fi" type="time" style="width:110px" data-rt="${r.key}" value="${E(r.time)}">`}<label style="display:flex;gap:6px;align-items:center;font-weight:700"><input type="checkbox" data-ro="${r.key}" ${r.on ? 'checked' : ''}> Aktif</label><button type="button" class="ntBtn g" data-act="rule" data-key="${r.key}">Simpan</button></div>`).join('') + '<div id="ntMsg"></div>';
  }
  async function loadHis() {
    try { HIS = (await rpc('apiNotifHistory', TOKEN, 60)).rows; } catch (e) { HIS = []; S.msg = `<div class="ntBad">${E(e.message || e)}</div>`; }
    if (S.tab === 'riw') draw();
  }
  function riw() {
    if (!HIS) return '<p class="v6load">Memuat riwayat…</p>';
    const badge = r => r.status === 'JADWAL' ? '<span class="ntB j">JADWAL ' + E(r.sendAt) + '</span>' : r.status === 'BATAL' ? '<span class="ntB x">BATAL</span>' : r.status === 'MENGIRIM' ? '<span class="ntB j">MENGIRIM</span>' : '<span class="ntB s">TERKIRIM</span>';
    return `<table class="ntTb"><thead><tr><th>Waktu</th><th>Judul</th><th>Pengirim</th><th>Terkirim</th><th>Dibuka</th><th>Jenis</th><th></th></tr></thead><tbody>${HIS.map(r => `<tr><td>${E(r.at.slice(0, 16))}</td><td><b>${E(r.title)}</b><br><small>${E(r.body)}</small></td><td>${E(r.sender)}</td><td>${r.sent}/${r.recipients}</td><td>${r.opened}</td><td>${r.kind === 'KHUSUS' ? '<span class="ntB k">KHUSUS</span>' : '<span class="ntB o">OTOMATIS</span>'} ${badge(r)}</td><td>${r.status === 'JADWAL' ? `<button type="button" class="ntBtn g" data-act="cancel" data-id="${r.id}">Batalkan</button>` : ''}</td></tr>`).join('') || '<tr><td colspan="7">Belum ada notifikasi.</td></tr>'}</tbody></table><div id="ntMsg">${S.msg || ''}</div>`;
  }
  const flash = (h, bad) => { S.msg = h ? `<div class="${bad ? 'ntBad' : 'ntOk'}">${h}</div>` : ''; const m = $('ntMsg'); if (m) m.innerHTML = S.msg; };
  function atFmt() { return S.at ? S.at.replace('T', ' ') : ''; }

  function shrink(file) {
    return new Promise((res, rej) => {
      const fr = new FileReader(); fr.onerror = () => rej(new Error('Gagal membaca gambar.'));
      fr.onload = () => { const im = new Image(); im.onerror = () => rej(new Error('File bukan gambar yang valid.')); im.onload = () => {
        let w = im.width, h = im.height; const mx = 1200; if (w > mx) { h = Math.round(h * mx / w); w = mx; }
        const c = document.createElement('canvas'); c.width = w; c.height = h; const x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, w, h); x.drawImage(im, 0, 0, w, h);
        let q = 0.88, d = c.toDataURL('image/jpeg', q); while (d.length > 1300000 && q > 0.4) { q -= 0.1; d = c.toDataURL('image/jpeg', q); }
        if (d.length > 1300000) return rej(new Error('Gambar masih terlalu besar. Pilih gambar lain.')); res(d); }; im.src = fr.result; };
      fr.readAsDataURL(file);
    });
  }
  function payload() { return { title: S.title.trim(), body: S.body.trim(), link: S.link, important: S.important, image: S.image || '', target: targetObj(), sendAt: S.when ? atFmt() : '' }; }

  async function onClick(e) {
    const t = e.target; if (!$('ntApp') || !$('ntApp').contains(t)) return;
    const tab = t.closest('[data-nt]'); if (tab) { S.tab = tab.dataset.nt; S.msg = null; if (S.tab === 'riw') HIS = null; draw(); preview(); return; }
    const tt = t.closest('[data-tt]'); if (tt) { S.ttype = tt.dataset.tt; S.cnt = null; draw(); preview(); return; }
    const ro = t.closest('[data-role]'); if (ro) { S.role = ro.dataset.role; S.cnt = null; draw(); preview(); return; }
    const rg = t.closest('[data-region]'); if (rg) { S.region = rg.dataset.region; S.cnt = null; draw(); preview(); return; }
    const wh = t.closest('[data-when]'); if (wh) { S.when = wh.dataset.when === '1'; if (S.when && !S.at) { const d = new Date(Date.now() + 3600000); d.setMinutes(0, 0, 0); const p = n => String(n).padStart(2, '0'); S.at = `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:00`; } draw(); return; }
    const b = t.closest('[data-act]'); if (!b) return; const a = b.dataset.act;
    try {
      if (a === 'rmimg') { S.image = ''; S.imageName = ''; draw(); }
      else if (a === 'send') {
        const p = payload(); if (!p.title) return flash('Judul wajib diisi.', true); if (!p.body) return flash('Isi pesan wajib diisi.', true);
        if (S.ttype === 'role' && !S.role) return flash('Pilih role sasaran.', true); if (S.ttype === 'region' && !S.region) return flash('Pilih region sasaran.', true); if (S.ttype === 'nik' && !S.niks.size) return flash('Pilih minimal satu orang.', true);
        if (S.when && !S.at) return flash('Isi tanggal dan jam jadwal.', true);
        S.busy = true; flash('Mengirim…'); b.disabled = true;
        const r = await rpc('apiNotifSend', TOKEN, p); S.busy = false; HIS = null;
        const done = r.status === 'JADWAL' ? `Dijadwalkan ${E(r.sendAt)} untuk ${r.recipients} orang.` : `Selesai: ${r.sent} dari ${r.recipients} penerima menerima notifikasi${r.pushOn ? '' : ' (notifikasi server belum aktif)'}.`;
        S.title = ''; S.body = ''; S.image = ''; S.imageName = ''; S.important = false; S.when = false; S.msg = `<div class="ntOk">${done}</div>`; draw(); preview();
      }
      else if (a === 'test') { S.busy = true; b.disabled = true; const r = await rpc('apiNotifTest', TOKEN, { title: S.title, body: S.body, link: S.link, image: S.image || '' }); S.busy = false; draw(); flash(E(r.hasil)); }
      else if (a === 'cancel') { await rpc('apiNotifCancel', TOKEN, +b.dataset.id); HIS = null; await loadHis(); flash('Jadwal dibatalkan.'); }
      else if (a === 'rule') {
        const k = b.dataset.key, on = !!$('ntApp').querySelector(`[data-ro="${k}"]`).checked, tm = $('ntApp').querySelector(`[data-rt="${k}"]`); await rpc('apiNotifRuleSave', TOKEN, k, on, tm ? tm.value : '');
        NT = await rpc('apiNotifState', TOKEN); draw(); flash('Aturan tersimpan.');
      }
    } catch (err) { S.busy = false; draw(); flash(E(err.message || err), true); }
  }
  async function onChange(e) {
    const t = e.target;
    if (t.id === 'ntFile') { const f = t.files && t.files[0]; if (!f) return; try { S.image = await shrink(f); S.imageName = f.name; draw(); } catch (err) { flash(E(err.message || err), true); } return; }
    if (t.id === 'ntLink') S.link = t.value; if (t.id === 'ntAt') S.at = t.value; if (t.id === 'ntImp') S.important = t.checked;
    if (t.dataset.nik) { t.checked ? S.niks.add(t.dataset.nik) : S.niks.delete(t.dataset.nik); S.cnt = null; const c = $('ntCnt'); if (c) c.innerHTML = cntText(); preview(); }
  }
  function onInput(e) {
    const t = e.target;
    if (t.id === 'ntTitle') { S.title = t.value; const p = $('ntPT'); if (p) p.textContent = t.value || 'Judul notifikasi'; }
    else if (t.id === 'ntBody2') { S.body = t.value; const p = $('ntPB'); if (p) p.textContent = t.value || 'Isi pesan akan tampil di sini.'; const l = $('ntLen'); if (l) l.textContent = t.value.length + '/160'; }
    else if (t.id === 'ntQ') { S.q = t.value; const pos = t.selectionStart; draw(); const n = $('ntQ'); if (n) { n.focus(); n.setSelectionRange(pos, pos); } }
  }
})();
