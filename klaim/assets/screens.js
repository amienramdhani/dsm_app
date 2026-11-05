const evHtml = e => `<div class="ev ${EVC(e.action)}"><div class="h"><span>${esc(e.action)}</span><span class="t">${wktS(e.created_at)}</span></div><div class="mut">${esc(e.actor_name)}${e.role ? ' · ' + esc(e.role) : ''}</div>${e.notes ? `<div class="mut">${esc(e.notes)}</div>` : ''}${e.before_val && e.after_val ? `<div class="diff"><s>${esc(e.before_val)}</s><span>→</span><b>${esc(e.after_val)}</b></div>` : e.before_val || e.after_val ? `<div class="mut">→ <b>${esc(e.after_val || e.before_val)}</b></div>` : ''}</div>`;
const histHtml = hist => { const dl = hist.filter(e => /diunduh$/.test(e.action)).reverse(), main = hist.filter(e => !/diunduh$/.test(e.action)).reverse();
  return `<div class="tline">${main.map(evHtml).join('')}</div>${dl.length ? `<details style="margin-top:10px"><summary class="mut" style="cursor:pointer"><b>Log unduhan (${dl.length})</b> · siapa mengunduh PDF dan kapan</summary><div class="tline" style="margin-top:8px">${dl.map(e => `<div class="kv"><span>${esc(e.action.replace(' diunduh', ''))}<br><small class="mut">${esc(e.actor_name)}${e.role ? ' · ' + esc(e.role) : ''}</small></span><span class="mut">${wktS(e.created_at)}</span></div>`).join('')}</div></details>` : ''}`; };
/* DSM Klaim - detail klaim (G04), tindakan approver, petty cash, rekap */
'use strict';
const hl = (a, b) => `<div class="kv"><span>${a}</span><span>${b}</span></div>`;
const EVC = a => /Disetujui|Transfer|selesai|dikonfirmasi|Pertanggung/.test(a) ? 'ok' : /revisi|Revisi|Versi|BA/.test(a) ? 'rv' : /Ditolak|Dibatalkan/.test(a) ? 'no' : 'sb';

const STC = { ADMIN: '#1E5FB8', HOS: '#4338CA', AUDIT: '#0E7C6B', FM: '#8A5A00', FT: '#B4237A' };
/** Kotak "Posisi sekarang": ringkas (pemegang + ringkasan TTD), alur dan TTD BA dibuka dengan satu ketukan. */
function posBox(d, c, p) {
  const n = d.next; if (!n) return '';
  const pem = n.who === 'PEMOHON', h = (n.holders && n.holders[0]) || {}, sg = n.sign;
  const rest = n.rest || [], nm = x => (x.holders || []).map(y => esc(y.name)).join(', ') || '<i>belum ada user</i>';
  const sub = pem ? 'Pemohon · tanda tangan BA' : esc(STAGE[c.stage] || c.stage) + (c.stage === 'FT' ? ' · transfer' : ' · periksa dan setujui');
  const more = (n.holders || []).length > 1 && !pem ? ` <small>+${n.holders.length - 1} lainnya</small>` : '';
  const sum = sg ? `<div class="pmini"><span>TTD BA ${sg.done} dari ${sg.total} selesai</span>${sg.pending.map(x => `<span class="a">✍ ${esc(x.role === 'PEMOHON' ? 'Pemohon' : STAGE[x.role] || x.role)}</span>`).join('')}</div>` : '';
  const det = (rest.length || (sg && sg.pending.length)) ? `<details class="pdet"><summary>Lihat alur &amp; TTD</summary>
    ${rest.length ? `<div class="pt">${pem ? 'ALUR SETELAH TTD' : 'SETELAH ITU'}</div><div class="pflow">${rest.map(x => `<div class="pf" style="--c:${STC[x.stage] || '#5a6784'}"><i class="pdot"></i><b>${esc(x.label)}</b><span>${nm(x)}</span></div>`).join('')}</div>` : ''}
    ${sg && sg.pending.length ? `<div class="pt">TTD BA YANG MASIH DITUNGGU</div><div class="psign">${sg.pending.map(x => `<span class="pchip">✍ ${esc(x.text.replace(/ \(.*$/, ''))} <small>${esc((x.text.match(/\((.*)\)/) || [, ''])[1])}</small></span>`).join('')}</div>` : ''}</details>` : '';
  return `<div class="posbox${pem ? ' pem' : ''}"><div class="phd"><span>POSISI SEKARANG</span>${n.step ? `<em>TAHAP ${n.step[0]} DARI ${n.step[1]}</em>` : ''}</div>
    <div class="pnow"><div class="pav">${esc((h.name || '?').trim().charAt(0).toUpperCase())}</div><div><b>${esc(h.name || 'Belum ada user di tahap ini')}${more}</b><span>${sub}</span></div></div>
    ${sum}${det}
    ${p.ba_sign ? '<div class="pwarn">Anda perlu menandatangani BA di bawah.</div>' : ''}${p.followup ? `<button class="btn wa" id="bfu" style="margin-top:10px">💬 HUBUNGI ${esc((STAGE[c.stage] || c.stage).toUpperCase())} UNTUK FOLLOW-UP</button>` : ''}</div>`;
}
/** Daftar peserta (Meeting / Entertain) sebagai kapsul nama dengan inisial; Pemohon diberi warna oranye. */
function pesBox(kind, m, label, who) {
  if (kind !== 'meeting' && kind !== 'party') return '';
  const ps = (m.peserta || []).filter(x => String(x).trim()).map(x => { const [n, ...j] = String(x).split(' — '); return { n: n.trim(), j: j.join(' — ').trim() }; });
  if (!ps.length) return '';
  const me = x => who && x.n.toLowerCase() === String(who).toLowerCase();
  return `<div class="sec"><h3>Peserta${label ? ' · ' + esc(label) : ''}</h3><div class="card"><div class="phd2"><b>Daftar peserta</b><span class="pcnt">${ps.length} ORANG</span></div><div class="pchips">${ps.map(x => `<span class="pch${me(x) || /pemohon/i.test(x.j) ? ' me' : ''}"><i>${esc(x.n.charAt(0).toUpperCase())}</i>${esc(x.n)}${x.j ? ` <small>${esc(x.j)}</small>` : ''}</span>`).join('')}</div></div></div>`;
}
R.claim = async (id) => {
  const d = await api('claim_get', { id }); const c = d.claim, p = d.perm, m = d.meta, k = d.kind;
  const cu = d.claimant; const stale = d.approvals.filter(a => a.action === 'approve' && !a.valid);
  let h = `<div class="sec" style="padding-bottom:0"><a class="btn g" style="display:block;text-align:center;text-decoration:none" target="_blank" href="api.php?a=pdf&id=${c.id}">⬇ UNDUH PDF KLAIM</a></div><div class="sec"><div class="card">${d.rev ? `<div class="note ${d.rev.holder === 'ADMIN' ? 'blue' : ''}"><b>${esc(c.label)}</b><br>Diminta ${STAGE[d.rev.stage]} (${esc(d.rev.by || '')}): “${esc(d.rev.note || '')}”</div>` : ''}
   ${c.status === 'REJECTED' ? `<div class="note red"><b>Ditolak (final).</b> Reservasi budget dilepas.</div>` : ''}
   <div class="row"><div>${pill(row(c))} ${c.over_budget_amount > 0 ? `<span class="pill p-no">Over ${rp(c.over_budget_amount)}</span>` : ''}${c.outside_sales == 1 ? ' <span class="pill p-pu">Di luar sales</span>' : ''}</div><div class="mut">v${c.current_version || 1}</div></div>
   <div style="margin-top:8px">${routeChips(d.route.length ? d.route : [], c.stage, c.status === 'DONE')}</div>
   <div class="card row totbox" style="margin-top:8px"><b>TOTAL NOMINAL DIPROSES</b><b>${rp(c.total)}</b></div>
   ${posBox(d, c, p)}
   ${c.status === 'WAIT' || c.status === 'REV' ? `<div class="mut" style="margin-top:6px">Di tahap ini ${c.age_days} hari${c.aging ? ' · <b style="color:#F07A0C">menunggu lama</b>' : ''}</div>` : ''}</div></div>`;
  // tindakan
  h += actionsHtml(d);
  // info
  const info = [['Pemohon', `${esc(cu.name)} (${esc(cu.job)})`], ['Region', esc(cu.region || '-')], ['Badan usaha', c.entity_by ? `${esc(c.entity)} <span class="chip on" style="cursor:default;font-size:10px">DIPILIH ADMIN</span> <span class="mut">${esc(c.entity_by)}</span>` : '<span class="mut">Belum ditentukan Admin</span>'], ['Kategori / jenis', `${esc(c.category)} / ${esc(d.type_label)}`], ['Brand · Refnote', `${esc(c.brand)} · ${esc(c.refnote)}`]];
  if (k !== 'perdin' && k !== 'makan') info.push(['Lokasi', esc(c.location)]);
  if (m.catatan) info.push(['Keterangan tambahan', esc(m.catatan)]);
  const kInfo = (k, m) => {
  if (k === 'bbm') info.push(['Kendaraan · Plat · KM', `${esc(m.kendaraan || '-')} · ${esc(m.plat || '')} · ${(+m.km_awal || 0).toLocaleString('id-ID')} → ${(+m.km_akhir || 0).toLocaleString('id-ID')} (${Math.max(0, m.km_akhir - m.km_awal)} km)`]);
  if (k === 'meeting') info.push(['Tujuan · jam', `${esc(m.tujuan)} · ${esc(m.jam_mulai)}–${esc(m.jam_selesai)}`], ...(m.tempat ? [['Tempat', esc(m.tempat)]] : []));
  if (k === 'ent') info.push(['Penerima', esc(m.penerima)], ...(m.tempat ? [['Tempat', esc(m.tempat)]] : []), ['Keterangan', esc(m.keterangan)]);
  if (k === 'party') { if (m.penerima) info.push(['Yang dirayakan', esc(m.penerima)]); if (m.tempat) info.push(['Tempat', esc(m.tempat)]); info.push(['Keterangan', esc(m.keterangan)]); }
  if (k === 'tiket') info.push(['Moda · operator', `${esc(m.moda)} · ${esc(m.operator)}`], ['Rute', `${esc(m.asal)} → ${esc(m.tujuan_kota)}`], ['Tanggal', `${tgl(m.tgl_berangkat)}${m.tgl_pulang ? ' s/d ' + tgl(m.tgl_pulang) : ''}`], ['Penumpang', esc(m.penumpang)], ['Tujuan perjalanan', esc(m.keterangan)]);
  if (k === 'lain') info.push(['Judul', esc(m.judul)], ['Kebutuhan / alasan', esc(m.keterangan)], ['Rincian', (m.rincian || []).map(r => `${esc(r.uraian)} · ${r.qty} × ${rp(r.harga)} = <b>${rp(r.qty * r.harga)}</b>`).join('<br>')]);
  if (k === 'trans' || k === 'ops') info.push(['Keterangan', esc(m.keterangan)]);
  if (k === 'kost') info.push(['Kost · periode', `${esc(m.nama_kost)} · ${esc(m.periode)}`], ['Pemilik', esc(m.pemilik)]);
  if (k === 'hotel') info.push(['Hotel', esc(m.nama_hotel)], ['Menginap', `${tgl(m.checkin)} s/d ${tgl(m.checkout)}`], ['Tujuan', esc(m.tujuan)]);
  };
  if (k === 'multi') d.parts.forEach(p => { info.push([`<b style="color:#9A5A00">Bagian ${esc(p.type.name)}</b>`, `<b>${rp(d.notes.filter(n => n.detail?.type_id == p.type.id).reduce((x, n) => x + +n.nominal, 0))}</b>`]); kInfo(p.kind, Array.isArray(p.meta) ? {} : p.meta); });
  else kInfo(k, m);
  info.push(['Rekening', `${esc(c.acct_bank)} ${esc(c.acct_no)} a.n. ${esc(c.acct_name)}${c.acct_own == 1 ? '' : ' <span class="pill p-rev">bukan milik pemohon</span>'}`]);
  if (c.acct_own != 1) info.push(['Alasan rekening lain', esc(c.acct_reason)]);
  if (c.requires_hos == 1) info.push(['Butuh HOS (Admin)', esc(c.hos_reason)]);
  h += `<div class="sec"><h3>Data pengajuan</h3><div class="card">${info.map(x => hl(x[0], x[1])).join('')}</div></div>`;
  (k === 'multi' ? d.parts : [{ kind: k, meta: m, type: d.type }]).forEach(P => { h += pesBox(P.kind, Array.isArray(P.meta) ? {} : P.meta, k === 'multi' ? P.type.name : '', d.claimant.name); });
  if (k === 'perdin' || k === 'makan') {
    h += `<div class="sec"><h3>Perjalanan dinas</h3><div class="card">${hl('Tanggal', `${tgl(m.tgl_berangkat)} s/d ${tgl(m.tgl_pulang)}`)}${hl('Tujuan', esc(m.tujuan))}${hl('Total jarak (Maps)', `<b>${esc(m.km_total)} km</b>`)}
    <div style="margin:8px 0"><div class="mut">Rute (${(m.rute || []).length} tujuan)</div>${(m.rute || []).map((r, i) => `<div class="ruteitem"><div class="no">${i + 1}</div><div>${esc(r)}</div></div>`).join('')}</div>
    <div class="mut">Screenshot Google Maps (ketuk untuk memperbesar)</div><div class="mapbox" style="margin-top:4px">${(d.files.maps || []).map(f => `<img data-view="${f.id}" src="${fileUrl(f.id)}" alt="Maps">`).join('') || '<div class="mut" style="padding:12px">Tidak ada.</div>'}</div>
    ${d.admin_check ? `<div class="note ok" style="margin-top:8px">Pemeriksaan rute oleh ${esc(d.admin_check.by)}: titik sesuai ✔ · jarak sesuai ✔ · durasi valid ✔</div>` : ''}</div></div>`;
  }
  h += `<div class="sec"><h3>Nota (${d.notes.length})</h3>${d.notes.map((n, i) => { const t = n.detail || {}; const ket = t.komponen ? `${esc(t.komponen)}${t.jenis ? ' · ' + esc(t.jenis) : ''}${t.mode === 'hari' ? ` · ${rp(t.per_hari)} × ${t.hari} hari` : t.komponen === 'Uang makan' ? ` · ${rp(t.tarif)} × ${t.hari} hari` : ''}` : esc(d.kind === 'multi' ? ((d.parts.find(p => p.type.id == t.type_id) || {}).type || d.type).name : d.type.name); return `<div class="card"><div class="row"><b>${ket}</b><b>${rp(n.nominal)}</b></div>${c.status === 'REV' && d.rev?.lines?.[n.line_no || i + 1] ? `<div class="note red" style="margin:6px 0">Catatan: ${esc(d.rev.lines[n.line_no || i + 1])}</div>` : ''}${t.bbm ? `<div class="mut">${esc(t.bbm)}${t.liter != null ? ' · ' + String(t.liter).replace('.', ',') + ' L' : ''} · Nominal nota ${rp(t.nota_nominal ?? n.nominal)} · <b>Nominal diklaim ${rp(n.nominal)}</b>${(t.nota_nominal ?? n.nominal) > n.nominal ? ` · selisih ${rp(t.nota_nominal - n.nominal)} ditanggung pemohon` : ''}</div>` : ''}<div class="mut">${tgl(n.nota_date)}${t.asal ? ` · Rute tol: ${esc(t.asal)} → ${esc(t.tujuan)}` : ''}</div>${n.files.length ? thumbs(n.files) : ''}</div>`; }).join('')}
   <div class="card row totbox"><b>TOTAL NOMINAL DIPROSES</b><b>${rp(c.total)}</b></div></div>`;
  const cf = [['odo_awal', 'Odometer awal'], ['odo_akhir', 'Odometer akhir'], ['doc', 'Dokumentasi']].filter(x => (d.files[x[0]] || []).length);
  if (cf.length) h += `<div class="sec"><h3>Lampiran klaim</h3><div class="card">${cf.map(x => `<div class="mut">${x[1]}</div>${thumbs(d.files[x[0]])}`).join('')}</div></div>`;
  if (d.budget) { const b = d.budget; h += `<div class="sec"><h3>Budget ${esc(c.category)} · ${esc(c.budget_period || '')}</h3><div class="card"><div class="sum"><div><b>${rp(b.budget)}</b><span class="mut">Budget</span></div><div><b>${rp(b.realized)}</b><span class="mut">Terpakai</span></div><div><b>${rp(b.reserved)}</b><span class="mut">Diproses</span></div></div><div class="kv" style="margin-top:6px"><span>Available</span><span style="${b.available < 0 ? 'color:#D2453F' : ''}">${rp(b.available)}</span></div>${c.avail_before != null ? hl('Available sebelum klaim ini', rp(c.avail_before)) : ''}</div></div>`; }
  if (d.ba) { const b = d.ba; h += `<div class="sec"><h3>Berita Acara</h3><div class="card"><div class="row"><b>${esc(b.number)}</b><span class="pill p-adm">BA v${b.version}</span></div><div class="mut">Dibuat ${esc(b.creator)} (${b.creator_role === 'PEMOHON' ? 'Pemohon' : 'Admin'})</div>${b.triggers.map(t => `<div class="pill p-rev" style="margin:4px 4px 0 0">${esc(t)}</div>`).join('')}${baSections(b, d)}${b.snapshot ? `${hl('Periode budget', esc(b.snapshot.period))}${hl('Available sebelum klaim', rp(b.snapshot.avail_before))}${hl('Nominal klaim', rp(b.snapshot.claim_amount))}${hl('Over budget', rp(b.snapshot.over))}` : ''}${b.files.length ? thumbs(b.files) : ''}
   <div class="mut" style="margin-top:8px">Tanda tangan elektronik</div>${b.required.map(r => { const s = b.signs.filter(x => x.role === r); const v = s.find(x => x.valid); const old = s.find(x => !x.valid); return `<div class="kv"><span>${STAGE[r] || r}</span><span>${v ? `✔ ${esc(v.name)} · ${wktS(v.signed_at)}` : old ? `<span style="color:#D2453F">versi lama tidak berlaku</span>` : '<span class="mut">belum</span>'}</span></div>`; }).join('')}
   ${b.complete ? '<div class="note ok">Tanda tangan BA lengkap.</div>' : '<div class="note">Tanda tangan BA belum lengkap. Finance tidak dapat mentransfer.</div>'}
   <div class="two" style="margin-top:8px">${p.ba_sign ? '<button class="btn gr" id="basign">TANDA TANGANI BA</button>' : '<span></span>'}<a class="btn g" style="text-align:center;text-decoration:none" target="_blank" href="api.php?a=ba_pdf&id=${c.id}">PDF BA</a></div></div></div>`; }
  h += `<div class="sec"><h3>Jejak persetujuan</h3><div class="card">${[{ stage: 'PEMOHON', uname: cu.name, action: 'submit', created_at: c.submitted_at, valid: true }, ...d.approvals].filter(a => a.created_at).map(a => `<div class="kv"><span>${STAGE[a.stage] || a.stage}<br><small class="mut">${esc(a.uname)} · ${wktS(a.created_at)}</small></span><span>${{ submit: 'Mengajukan', approve: 'Setuju', revise: 'Minta revisi', reject: 'Tolak', transfer: 'Transfer ' + esc(a.notes || '') }[a.action]}${a.action === 'approve' && !a.valid ? '<br><small style="color:#D2453F">versi lama · outdated</small>' : ''}${a.notes && a.action !== 'transfer' ? `<br><small class="mut">“${esc(a.notes)}”</small>` : ''}</span></div>`).join('') || '<div class="mut">Belum ada.</div>'}</div></div>`;
  if (d.versions.length > 1) h += `<div class="sec"><h3>Versi klaim</h3><div class="card">${d.versions.map(v => `<div class="kv"><span>v${v.version_no} · ${wktS(v.created_at)}<br><small class="mut">${esc(v.reason || '')}</small></span><span>${rp(v.total)}</span></div>`).join('')}</div></div>`;
  h += `<div class="sec"><h3>Riwayat (tambah-saja, sampai detik)</h3><div class="card">${histHtml(d.history)}</div></div>`;
  if (d.transfer) h += `<div class="sec"><h3>Transfer</h3><div class="card">${hl('Nomor', esc(d.transfer.transfer_no))}${hl('Oleh', esc(d.transfer.uname))}${hl('Waktu', wktS(d.transfer.created_at))}${thumbs(d.files.transfer)}</div></div>`;
  shell(hero(c.number || 'Draft', `${esc(d.type_label)} · ${esc(cu.name)}`, { back: true, small: true }) + h, { hero: false });
  $('#bfu')?.addEventListener('click', () => guard(async () => {
    const f = await api('followup_info', { id });
    sheet(`<h3>Hubungi ${esc(f.stage)}</h3><div class="mut">Penyetuju di tahap ini · klaim menunggu ${f.days} hari</div>
      ${f.people.length ? f.people.map(p => `<div class="fuper"><span class="fuav">${esc(p.name.split(' ').map(x => x[0]).slice(0, 2).join('').toUpperCase())}</span><div><b>${esc(p.name)}</b><span class="mut">${esc(p.job || '')}${p.region ? ' · ' + esc(p.region) : ''}</span></div>${p.wa ? `<a class="fuwa" target="_blank" rel="noopener" data-fu="${p.id}" href="https://wa.me/${p.wa}?text=${encodeURIComponent(p.text)}">WhatsApp</a>` : '<span class="fuwa off">No. WA belum ada</span>'}</div>`).join('') : '<div class="note">Belum ada penyetuju terdaftar untuk tahap ini.</div>'}
      ${f.people[0] ? `<div class="fumsg"><b>Pesan otomatis:</b><br>${esc(f.people[0].text)}</div>` : ''}
      <button class="btn g" id="fuapp" style="margin-top:10px" ${f.app_sent_today ? 'disabled' : ''}>${f.app_sent_today ? 'PENGINGAT HARI INI SUDAH DIKIRIM' : 'KIRIM PENGINGAT DI APLIKASI SAJA'}</button><div class="mut" style="margin-top:6px">Setiap follow-up tercatat di riwayat klaim. Pengingat di aplikasi maksimal sekali sehari.</div>`, (el, close) => {
      $$('[data-fu]', el).forEach(a => a.addEventListener('click', () => { api('followup_log', { id, via: 'wa', to: +a.dataset.fu }).catch(() => {}); }));
      $('#fuapp', el).onclick = () => guard(async () => { await api('followup_log', { id, via: 'app' }); close(); toast('Pengingat terkirim ke ' + f.stage + '.'); route(); });
    });
  }));
  $('#basign')?.addEventListener('click', () => guard(async () => {
    const img = (await api('sign_get')).img;
    if (!img) { try { await askSign(); } catch (e) { return; } $('#basign')?.click(); return; } // belum punya TTD: popup, lalu lanjut menandatangani
    sheet(`<h3>Tanda tangani BA</h3><div class="fld"><label>TANDA TANGAN ANDA</label><div style="background:#FAFCFE;border:1px solid #D3DEE8;border-radius:14px;text-align:center;padding:10px"><img src="${img}" alt="" style="max-width:100%;max-height:110px"></div><div class="mut" style="margin-top:6px">Memakai tanda tangan tersimpan. <a href="#/ttd" id="gochg">Ganti</a></div></div><button class="btn" id="dosign">TANDA TANGANI BA</button>`, (el, close) => {
      $('#gochg', el).onclick = () => close(); $('#dosign', el).onclick = () => guard(async () => { await api('ba_sign', { id }); close(); toast('BA ditandatangani. Klaim lanjut ke tahap berikutnya.'); route(); });
    });
  }));
  bindActions(d);
};
const row = c => ({ status: c.status, stage: c.stage, label: c.label });

function actionsHtml(d) {
  const p = d.perm, c = d.claim; let h = '';
  if (p.edit && c.status === 'DRAFT') h += `<div class="sec"><div class="two"><button class="btn" data-go="#/edit/${c.id}">LANJUTKAN DRAFT</button><button class="btn g" id="bcancel">Hapus draft</button></div></div>`;
  if (p.edit && c.status === 'REV') h += `<div class="sec"><div class="card"><b>Perbaiki klaim</b><div class="mut">Versi saat ini v${c.current_version}. ${d.rev?.holder === 'ADMIN' ? 'Anda (Admin) melengkapi sendiri.' : 'Perbaiki lalu kirim kembali ke ' + STAGE[d.rev?.stage] + '.'}</div><div class="two" style="margin-top:8px"><button class="btn" data-go="#/edit/${c.id}">PERBAIKI</button><button class="btn g" data-go="#/ringkas/${c.id}">Kirim tanpa ubah</button></div>${p.cancel ? '<button class="btn g sm" id="bcancel" style="margin-top:8px">Batalkan klaim</button>' : ''}</div></div>`;
  if (p.rev_throw) h += `<div class="sec"><div class="card"><div class="note blue">Revisi sedang di Admin. Lengkapi sendiri atau lempar ke pemohon.</div><div class="two"><button class="btn" data-go="#/edit/${c.id}">LENGKAPI SENDIRI</button><button class="btn b2" id="bthrow">LEMPAR KE PEMOHON</button></div></div></div>`;
  if (p.act) {
    const st = c.stage; let x = `<div class="sec"><div class="card" style="border:2px solid #F07A0C"><b>Tindakan ${STAGE[st]}</b>`;
    if (st === 'ADMIN') {
      x += `<div class="fld"><label>BADAN USAHA (WAJIB DIPILIH ADMIN)</label><div class="chips" style="margin-top:2px" id="entchips">${(S.L.entities || []).map(e => `<span class="chip" data-ent="${esc(e)}" style="padding:10px 18px;font-size:13px;border-radius:12px">${esc(e)}</span>`).join('')}</div><div class="mut" id="enthint" style="margin-top:6px">${d.claimant.entity ? 'Saran dari data pemohon: ' + esc(d.claimant.entity) + '. ' : ''}Menentukan Audit, FM, FT penerima dan header PDF.</div></div>`;
      if (!c.refnote) x += fld('REFNOTE (WAJIB DIISI ADMIN)', '<input id="refnote" placeholder="mis. IT-SEMARANG">');
      if (d.kind === 'perdin' || d.kind === 'makan') x += `<div class="note blue"><b>Pemeriksaan rute Perdin</b></div>${['titik|Titik di foto Maps sama dengan daftar rute', 'jarak|Total jarak foto sama dengan yang diisi pemohon', 'durasi|Durasi dan hari komponen valid'].map(s => { const [k, l] = s.split('|'); return `<label class="opt" style="padding:8px"><input type="checkbox" data-chk="${k}" style="width:auto;min-height:0;margin-right:8px"><span>${l}</span></label>`; }).join('')}`;
      if (!c.ba_required) x += `<div class="row" style="margin:8px 0"><div><b>Perlu persetujuan HOS?</b><div class="mut">Alasan wajib dan tercatat</div></div>${sw(false, 'needhos')}</div><div id="hosbox" class="hide">${fld('Alasan', '<input id="hosreason">')}</div>`;
      if (p.admin_ba) x += `<button class="btn g sm" id="badm" style="margin-bottom:8px">Buat BA (temuan Admin)</button>`;
    }
    if (st === 'FT') x += ftForm(d);
    if (st !== 'FT') x += `<div class="fld"><label>Catatan (opsional untuk Setujui)</label><input id="anote"></div><button class="btn gr" id="bapp">SETUJUI</button>`;
    x += `<div class="two" style="margin-top:8px"><button class="btn b2" id="brev">MINTA REVISI</button><button class="btn r2" id="brej">TOLAK</button></div></div></div>`;
    h += x;
  }
  return h;
}
function ftForm(d) {
  const ba = d.ba && !d.ba.complete;
  return `${ba ? '<div class="note red">Tanda tangan BA belum lengkap untuk versi terbaru. Transfer tidak dapat dicatat.</div>' : ''}<div class="kv"><span>Nominal</span><span class="b">${rp(d.claim.total)}</span></div><div class="kv"><span>Rekening</span><span>${esc(d.claim.acct_bank)} ${esc(d.claim.acct_no)}<br>a.n. ${esc(d.claim.acct_name)}</span></div>
   ${fld('Nomor transfer', '<input id="tfno">')}${fld('Bukti transfer', '<div id="tfp"></div><button class="btn g sm" id="tfadd">+ Unggah bukti</button>')}<button class="btn gr" id="btf" ${ba ? 'disabled' : ''}>CATAT TRANSFER</button>`;
}
function bindActions(d) {
  const id = d.claim.id; const done = async (m) => { toast(m); await refreshCounts(); route(); };
  $('#bcancel')?.addEventListener('click', () => confirm('Batalkan/hapus klaim ini?') && guard(async () => { await api('claim_cancel', { id }); toast('Dibatalkan.'); location.hash = '#/mine'; }));
  $('#bthrow')?.addEventListener('click', () => guard(async () => { await api('claim_act', { id, action: 'rev_throw' }); await done('Revisi dilempar ke pemohon.'); }));
  $('#app').addEventListener('click', e => { const s = e.target.closest('[data-sw="needhos"]'); if (s) { s.classList.toggle('on'); $('#hosbox').classList.toggle('hide', !s.classList.contains('on')); } });
  let ENT = ''; $$('[data-ent]').forEach(ch => ch.onclick = () => { ENT = ch.dataset.ent; $$('[data-ent]').forEach(o => o.classList.toggle('on', o === ch)); $('#enthint')?.classList.remove('errt'); });
  $('#bapp')?.addEventListener('click', () => guard(async () => {
    if ($('#entchips') && !ENT) { const h = $('#enthint'); if (h) { h.style.color = '#D2453F'; h.style.fontWeight = '700'; h.textContent = 'Pilih badan usaha dulu sebelum menyetujui.'; h.scrollIntoView({ block: 'center' }); } return; }
    if ($('#refnote') && !$('#refnote').value.trim()) { toast('Isi Refnote dulu sebelum menyetujui.'); $('#refnote').focus(); return; }
    const chk = {}; $$('[data-chk]').forEach(c => chk[c.dataset.chk] = c.checked); const need = $('[data-sw="needhos"]')?.classList.contains('on');
    await api('claim_act', { id, action: 'approve', notes: $('#anote').value, perdin_check: chk, entity: ENT, refnote: $('#refnote')?.value, need_hos: need ? 1 : 0, hos_reason: $('#hosreason')?.value }); await done('Disetujui.'); }));
  const decide = (kind) => {
    const rev = kind === 'revise';
    sheet(`<h3>${rev ? 'Minta revisi' : 'Tolak klaim'}</h3>${rev ? `<div class="note">Klaim tetap aktif dan reservasi budget ditahan. Setelah diperbaiki kembali ke tahap Anda.</div>` : `<div class="note red">Tolak bersifat <b>final</b>. Reservasi budget dilepas dan klaim tidak masuk antrean perbaikan.</div>`}
      ${rev && d.perm.revise_target ? `<div class="fld"><label>Perbaikan oleh</label><div class="opt on" data-tg="PEMOHON"><div class="dotr"></div><div><b>Langsung ke pemohon</b><span class="mut">Admin tidak terlibat</span></div></div><div class="opt" data-tg="ADMIN"><div class="dotr"></div><div><b>Ke Admin</b><span class="mut">Admin melengkapi sendiri atau melempar ke pemohon</span></div></div></div>` : ''}
      <div class="fld"><label>Catatan (wajib)</label><textarea id="dnote" placeholder="Tuliskan alasan dengan jelas"></textarea></div>${rev && (d.notes || []).length ? `<div class="fld"><label>Catatan per nota (opsional)</label>${d.notes.map((n, i) => `<div class="mut" style="margin-top:4px">Nota ${n.line_no || i + 1} · ${rp(n.nominal)}</div><input data-ln="${n.line_no || i + 1}" placeholder="mis. seharusnya Rp 30.000">`).join('')}</div>` : ''}<button class="btn ${rev ? 'b2' : 'r2'}" id="dok">${rev ? 'KIRIM PERMINTAAN REVISI' : 'TOLAK KLAIM'}</button>`, (el, close) => {
      let tg = 'PEMOHON'; $$('[data-tg]', el).forEach(o => o.onclick = () => { tg = o.dataset.tg; $$('[data-tg]', el).forEach(x => x.classList.toggle('on', x === o)); });
      $('#dok', el).onclick = () => guard(async () => { await api('claim_act', { id, action: kind, notes: $('#dnote', el).value, target: tg, line_notes: Object.fromEntries($$('[data-ln]', el).filter(x => x.value.trim()).map(x => [x.dataset.ln, x.value.trim()])) }); close(); await done(rev ? 'Permintaan revisi terkirim.' : 'Klaim ditolak.'); });
    });
  };
  $('#brev')?.addEventListener('click', () => decide('revise')); $('#brej')?.addEventListener('click', () => decide('reject'));
  $('#badm')?.addEventListener('click', () => {
    const nn = (d.notes || []).map((n, i) => ({ no: n.line_no || i + 1, nominal: n.nominal }));
    const V = { on: { KEHILANGAN_NOTA: false, TEMUAN_ADMIN: false }, lost: [], kategori: 'TGL', related: [], text: {}, frame: {}, files: [] };
    const chips = (key, sel) => nn.map(n => `<span class="bch ${sel.includes(n.no) ? 'on' : ''}" data-${key}="${n.no}">Nota ${n.no} · ${rp(n.nominal)}</span>`).join('');
    const code = x => x === 'TEMUAN_ADMIN' ? 'TEMUAN_LAIN' : x; // kalimat & saran sama dengan Pemohon
    const frame = x => baFrame(code(x), { lost: V.lost, kategori: V.kategori, related: V.related, info: {} });
    const card = x => {
      if (V.text[x] === undefined) { V.frame[x] = frame(x); V.text[x] = V.frame[x]; }
      const extra = x === 'KEHILANGAN_NOTA' ? `<div class="fld"><label>Nota yang hilang</label><div class="bchs">${chips('lost', V.lost)}</div></div><div class="fld"><label>Bukti pengganti (minimal 1 file)</label><div id="baf">${thumbs(V.files)}</div><button class="btn g sm" id="bafa">+ Bukti pengganti</button></div>`
        : `<div class="fld"><label>Kategori temuan</label><div class="bchs">${Object.entries(TEMUAN_KAT).map(([k2, t]) => `<span class="bch ${V.kategori === k2 ? 'on' : ''}" data-kat="${k2}">${t}</span>`).join('')}</div></div><div class="fld"><label>Nota terkait (opsional)</label><div class="bchs">${chips('rel', V.related)}</div></div>`;
      return `<div class="bacard"><div class="row"><b>${x === 'KEHILANGAN_NOTA' ? 'Kehilangan nota' : 'Temuan lain'}</b><span class="bdel" data-off="${x}">Hapus</span></div>${extra}<div class="fld"><label>Penjelasan</label><textarea data-bx="${x}" rows="3">${esc(V.text[x])}</textarea></div><div class="bsug">${(BA_SUG[code(x)] || []).map(t => `<span class="bch sm" data-sug="${x}">${esc(t)}</span>`).join('')}</div></div>`;
    };
    sheet(`<h3>Buat BA (temuan Admin)</h3><div class="note">Dengan membuat BA, Anda otomatis menandatangani dan menyetujui klaim ini, lalu klaim menunggu tanda tangan Pemohon, kemudian ke HOS → Audit → Finance Manager.</div>${ENT ? `<div class="note ok">Badan usaha: <b>${esc(ENT)}</b></div>` : '<div class="note red">Pilih badan usaha di kotak tindakan lebih dulu. Wajib untuk membuat BA.</div>'}<div class="note blue">Nominal salah? Pakai <b>Minta revisi</b>, bukan BA.</div><div id="bacards"></div><div id="bapick"></div><button class="btn" id="baok" style="margin-top:10px">BUAT BA</button>`, (el, close) => {
      const draw = () => { const on = Object.keys(V.on).filter(x => V.on[x]); $('#bacards', el).innerHTML = on.map(card).join(''); $('#bapick', el).innerHTML = Object.keys(V.on).filter(x => !V.on[x]).map(x => `<span class="bch" data-on="${x}">+ ${x === 'KEHILANGAN_NOTA' ? 'Kehilangan nota' : 'Temuan lain'}</span>`).join(' '); $('#bafa', el) && ($('#bafa', el).onclick = () => pickFiles(fs => { V.files.push(...fs); draw(); })); };
      const refresh = x => { const old = V.frame[x] || ''; const tail = (V.text[x] || '').startsWith(old) ? V.text[x].slice(old.length) : V.text[x]; V.frame[x] = frame(x); V.text[x] = V.frame[x] + tail; };
      const tog = (arr, v) => { const i = arr.indexOf(v); i < 0 ? arr.push(v) : arr.splice(i, 1); };
      el.oninput = e => { if (e.target.dataset.bx) V.text[e.target.dataset.bx] = e.target.value; };
      el.onclick = e => { const t = e.target.closest('[data-lost],[data-rel],[data-kat],[data-sug],[data-on],[data-off]'); if (!t) return;
        if (t.dataset.lost) { tog(V.lost, +t.dataset.lost); refresh('KEHILANGAN_NOTA'); }
        else if (t.dataset.rel) { tog(V.related, +t.dataset.rel); refresh('TEMUAN_ADMIN'); }
        else if (t.dataset.kat) { V.kategori = t.dataset.kat; refresh('TEMUAN_ADMIN'); }
        else if (t.dataset.sug) { const x = t.dataset.sug, old = V.text[x] || ''; V.text[x] = (old.length > (V.frame[x] || '').length && !/\s$/.test(old) ? old + ' ' : old) + t.textContent; }
        else if (t.dataset.on) V.on[t.dataset.on] = true;
        else if (t.dataset.off) { V.on[t.dataset.off] = false; delete V.text[t.dataset.off]; delete V.frame[t.dataset.off]; }
        draw(); };
      draw();
      $('#baok', el).onclick = () => guard(async () => {
        const types = Object.keys(V.on).filter(x => V.on[x]); if (!types.length) throw new Error('Pilih minimal satu jenis BA.'); if (!ENT) throw new Error('Pilih badan usaha dulu.'); if ($('#refnote') && !$('#refnote').value.trim()) throw new Error('Isi Refnote dulu.');
        const explanations = {}; for (const x of types) { const tx = (V.text[x] || '').trim(); if (tx.length < (V.frame[x] || '').trim().length + 6) throw new Error('Lengkapi penjelasan untuk ' + (x === 'KEHILANGAN_NOTA' ? 'Kehilangan nota' : 'Temuan lain') + '.'); explanations[x] = tx; }
        if (types.includes('KEHILANGAN_NOTA') && !V.lost.length) throw new Error('Pilih nota yang hilang.');
        if (types.includes('KEHILANGAN_NOTA') && !V.files.length) throw new Error('Nota hilang wajib disertai bukti pengganti (minimal 1 file).');
        const chk = {}; $$('[data-chk]').forEach(c => chk[c.dataset.chk] = c.checked);
        await api('claim_act', { id, action: 'admin_ba', entity: ENT, refnote: $('#refnote')?.value, perdin_check: chk, types, explanations, extra: { lost: V.lost, kategori: V.kategori, related: V.related }, files: V.files.map(f => f.id) });
        close(); await done('BA dibuat dan klaim disetujui Admin. Pemohon diminta menandatangani.'); });
    });
  });
  if ($('#tfadd')) { let pf = []; $('#tfadd').onclick = () => pickFiles(fs => { pf.push(...fs); $('#tfp').innerHTML = thumbs(pf); });
    $('#btf').onclick = () => guard(async () => { await api('claim_act', { id, action: 'transfer', transfer_no: $('#tfno').value, proof: pf.map(f => f.id) }); await done('Transfer dicatat. Klaim selesai.'); }); }
}

/* ---------- petty cash ---------- */
function pcLi(p) {
  const cls = p.status === 'SELESAI' ? 'p-ok' : p.status === 'DITOLAK' ? 'p-no' : p.overdue ? 'p-no' : p.status === 'REV' ? 'p-rev' : 'p-adm';
  return `<div class="li" data-go="#/pcd/${p.id}"><div class="dot" style="background:#10394E">💵</div><div class="t"><b>${esc(p.number)} · ${esc(p.claimant)}</b><span>${esc(p.purpose)} · ${tgl(p.p_start)}–${tgl(p.p_end)}</span><div style="margin-top:3px"><span class="pill ${cls}">${esc(p.label)}</span>${p.status === 'WAIT' ? ` <span class="pill ${p.aging ? 'p-rev' : 'p-gr'}">⏱ ${p.age_days} hari${p.aging ? ' · menunggu lama' : ''}</span>` : ''}</div></div><div class="r"><b>${rp(p.amount)}</b></div></div>`;
}
R.pc = async () => {
  const [j, b] = await Promise.all([api('pc_list', { view: 'mine' }), api('budget')]);
  const active = j.rows.find(r => !['SELESAI', 'DITOLAK'].includes(r.status));
  shell(hero('Petty cash saya', 'P18') + `<div class="sec">${b.blocked ? `<div class="note red"><b>Pengajuan klaim diblokir.</b> Pertanggungjawaban ${esc(b.blocked.number)} melewati tenggat ${tgl(b.blocked.deadline)}.</div>` : ''}${S.me.can_petty ? `<div class="card"><div class="row"><div><b>Plafon petty cash</b><div class="mut">Satu permintaan aktif per orang</div></div><b>${rp(S.me.petty_ceiling)}</b></div>${active ? '<div class="mut" style="margin-top:6px">Anda masih punya petty cash aktif.</div>' : '<button class="cta" data-go="#/pcnew" style="margin-top:10px">AJUKAN PETTY CASH</button>'}</div>` : '<div class="note">Anda belum diberi hak petty cash oleh Admin.</div>'}</div><div class="sec"><h3>Riwayat</h3>${j.rows.length ? j.rows.map(pcLi).join('') : emptyBox('Belum ada petty cash.')}</div>`, { hero: false });
};
R.pcq = async (st) => { const j = await api('pc_list', { view: 'queue', stage: st }); shell(hero('Antrean petty cash · ' + (STAGE[st] || st), 'R01', { back: true }) + `<div class="sec">${st === 'FT' ? '' : ''}${j.rows.length ? j.rows.map(pcLi).join('') : emptyBox('Tidak ada petty cash menunggu.')}</div>${st === 'FT' ? await pcFtExtra() : ''}`, { hero: false }); };
async function pcFtExtra() { const j = await api('pc_list', { view: 'ft' }); return `<div class="sec"><h3>Setoran sisa menunggu konfirmasi / penutupan</h3>${j.rows.length ? j.rows.map(pcLi).join('') : emptyBox('Tidak ada.')}</div>`; }
R.pcproses = async () => { const j = await api('pc_list', { view: 'proses' }); shell(hero('Petty cash diproses', 'R03', { back: true }) + `<div class="sec">${j.rows.length ? j.rows.map(pcLi).join('') : emptyBox('Tidak ada.')}</div>`, { hero: false }); };
R.pcmon = async () => { const j = await api('pc_list', { view: 'monitor' }); shell(hero('Monitoring petty cash', 'H04') + `<div class="sec">${j.rows.length ? j.rows.map(p => `${pcLi(p)}${p.status === 'DANA' ? `<div class="mut" style="margin:-4px 0 8px 8px">Belum dipertanggungjawabkan: ${rp(p.amount - p.realized_now)} · tenggat ${tgl(p.deadline)}</div>` : ''}`).join('') : emptyBox('Tidak ada petty cash berjalan.')}</div>`, { hero: false }); };
R.pcd = async (id) => {
  const j = await api('pc_get', { id }); const p = j.pc; const own = j.is_owner; let h = '';
  const info = [['Pemohon', esc(p.claimant)], ['Tujuan', esc(p.purpose)], ['Lokasi', esc(p.location || '-')], ...(p.remark ? [['Keterangan tambahan', esc(p.remark)]] : []), ['Periode', `${tgl(p.p_start)} s/d ${tgl(p.p_end)}`], ['Jumlah diminta', `<b>${rp(p.amount)}</b>`], ['Tenggat pertanggungjawaban', tgl(p.deadline)], ['Rekening', `${esc(p.acct_bank)} ${esc(p.acct_no)} a.n. ${esc(p.acct_name)}`]];
  h += `<div class="sec"><div class="card"><div class="row"><span class="pill ${p.overdue ? 'p-no' : p.status === 'REV' ? 'p-rev' : p.status === 'SELESAI' ? 'p-ok' : 'p-adm'}">${esc(p.label)}</span><span class="mut">${esc(p.number)}</span></div><div style="margin-top:8px">${p.status === 'WAIT' ? routeChips(p.route ? JSON.parse(p.route) : [], p.stage, false) : ''}</div>${p.status === 'REV' ? `<div class="note"><b>Perlu revisi.</b> ${esc(p.rev_note || '')}</div>` : ''}${p.overdue ? '<div class="note red"><b>Lewat tenggat.</b> Seluruh pengajuan klaim pemohon diblokir sampai pertanggungjawaban selesai.</div>' : ''}</div></div>
  <div class="sec"><h3>Data</h3><div class="card">${info.map(x => hl(x[0], x[1])).join('')}</div></div>
  <div class="sec"><h3>Rencana pemakaian</h3>${p.plan.map((r, i) => `<div class="card"><div class="row"><b>${esc(r.uraian)}</b><b>${rp(r.nominal)}</b></div><div class="mut">${CATIC[r.kategori] || ''} ${esc(r.kategori)}</div>${r.perdin ? `<div class="mut">Perdin ${tgl(r.perdin.tgl_berangkat)}–${tgl(r.perdin.tgl_pulang)} · ${esc((r.perdin.rute || []).join(' → '))} · ${esc(r.perdin.km_total)} km</div>` : ''}</div>`).join('')}${j.maps.length ? `<div class="card"><div class="mut">Screenshot Maps (Perdin)</div>${thumbs(j.maps)}</div>` : ''}</div>`;
  if (['DANA', 'SETOR', 'TUTUP', 'SELESAI'].includes(p.status)) {
    h += `<div class="sec"><h3>Nota pertanggungjawaban</h3>${p.notes.map(n => `<div class="card"><div class="row"><b>${esc(n.uraian || 'Nota')}</b><b>${rp(n.nominal)}</b></div><div class="mut">${tgl(n.nota_date)} · ${n.off_plan == 1 ? 'Di luar rencana' : 'Rencana ' + (+n.plan_idx + 1)}</div>${thumbs(n.files)}</div>`).join('') || emptyBox('Belum ada nota.')}
    <div class="card"><div class="sum"><div><b>${rp(p.amount)}</b><span class="mut">Dana</span></div><div><b>${rp(p.realized_now)}</b><span class="mut">Terpakai</span></div><div><b style="${p.amount - p.realized_now < 0 ? 'color:#D2453F' : ''}">${rp(p.amount - p.realized_now)}</b><span class="mut">Sisa</span></div></div>${p.realized_now > p.amount ? '<div class="note blue">Kelebihan otomatis menjadi klaim reimburse saat pertanggungjawaban dikirim.</div>' : ''}</div></div>`;
  }
  if (p.status === 'SETOR' || p.status === 'TUTUP' || p.status === 'SELESAI') h += `<div class="sec"><h3>Pertanggungjawaban</h3><div class="card">${hl('Terpakai', rp(p.realized))}${hl('Sisa disetor', rp(p.sisa))}${hl('Nomor setoran', esc(p.setoran_no || '-'))}${thumbs(j.setoran_files)}${p.excess_claim_id ? `<div class="note blue">Kelebihan menjadi klaim reimburse.<br><span data-go="#/claim/${p.excess_claim_id}" style="text-decoration:underline;cursor:pointer">Buka klaim</span></div>` : ''}</div></div>`;
  // aksi
  let ac = '';
  if (j.can_act && p.status === 'WAIT') ac += `<div class="card" style="border:2px solid #F07A0C"><b>Tindakan ${STAGE[j.stage]}</b>${j.stage === 'FT' ? `${fld('Nomor transfer', '<input id="ptf">')}${fld('Bukti transfer', '<div id="ptp"></div><button class="btn g sm" id="ptadd">+ Unggah bukti</button>')}<button class="btn gr" id="pttf">CATAT TRANSFER DANA</button>` : `<div class="fld"><label>Catatan</label><input id="pnote"></div><button class="btn gr" id="papp">SETUJUI</button>`}<div class="two" style="margin-top:8px"><button class="btn b2" id="prev">MINTA REVISI</button><button class="btn r2" id="prej">TOLAK</button></div></div>`;
  if (own && p.status === 'REV') ac += `<div class="card"><b>Perbaiki petty cash</b><button class="btn" id="pfix" style="margin-top:8px">PERBAIKI & KIRIM ULANG</button></div>`;
  if (own && p.status === 'DANA') ac += `<div class="card"><b>Pertanggungjawaban</b><div class="mut">Kirim nota selama periode, lalu kirim pertanggungjawaban.</div><div class="two" style="margin-top:8px"><button class="btn b2" id="pnadd">+ TAMBAH NOTA</button><button class="btn gr" id="psend">KIRIM PERTANGGUNGJAWABAN</button></div></div>`;
  if (own && p.status === 'SETOR' && !p.setoran_no) ac += `<div class="card"><b>Setor sisa ${rp(p.sisa)}</b><div class="mut">Transfer ke rekening perusahaan, lalu kirim bukti.</div><button class="btn" id="pset" style="margin-top:8px">KIRIM BUKTI SETORAN</button></div>`;
  if ((p.status === 'SETOR' && p.setoran_no || p.status === 'TUTUP') && (S.me.roles.includes('FT') || S.me.roles.includes('SA'))) ac += `<div class="card" style="border:2px solid #12866A"><b>${p.status === 'TUTUP' ? 'Sisa nol: tutup petty cash' : 'Konfirmasi setoran'}</b><div class="mut">${p.status === 'TUTUP' ? 'Tidak ada sisa untuk disetor.' : `Setoran ${rp(p.sisa)} · no. ${esc(p.setoran_no)}`}</div><button class="btn gr" id="pclose" style="margin-top:8px">${p.status === 'TUTUP' ? 'TUTUP PETTY CASH' : 'KONFIRMASI SETORAN & SELESAIKAN'}</button></div>`;
  h += `<div class="sec">${ac}<a class="btn g" style="display:block;text-align:center;text-decoration:none;margin-top:8px" target="_blank" href="api.php?a=pc_pdf&id=${id}">UNDUH PDF</a></div>`;
  h += `<div class="sec"><h3>Riwayat</h3><div class="card">${histHtml(j.history)}</div></div>`;
  shell(hero(p.number, 'PETTY CASH · ' + esc(p.claimant), { back: true, small: true }) + h, { hero: false });
  const done = async m => { toast(m); await refreshCounts(); route(); };
  $('#papp')?.addEventListener('click', () => guard(async () => { await api('pc_act', { id, action: 'approve', notes: $('#pnote').value }); await done('Disetujui.'); }));
  const dec = kind => sheet(`<h3>${kind === 'revise' ? 'Minta revisi' : 'Tolak petty cash'}</h3><div class="fld"><label>Catatan (wajib)</label><textarea id="pdn"></textarea></div><button class="btn ${kind === 'revise' ? 'b2' : 'r2'}" id="pdk">KIRIM</button>`, (el, close) => { $('#pdk', el).onclick = () => guard(async () => { await api('pc_act', { id, action: kind, notes: $('#pdn', el).value }); close(); await done('Tersimpan.'); }); });
  $('#prev')?.addEventListener('click', () => dec('revise')); $('#prej')?.addEventListener('click', () => dec('reject'));
  if ($('#ptadd')) { let pf = []; $('#ptadd').onclick = () => pickFiles(fs => { pf.push(...fs); $('#ptp').innerHTML = thumbs(pf); }); $('#pttf').onclick = () => guard(async () => { await api('pc_act', { id, action: 'transfer', transfer_no: $('#ptf').value, proof: pf.map(f => f.id) }); await done('Dana dicatat ditransfer.'); }); }
  $('#pnadd')?.addEventListener('click', () => { let nf = []; sheet(`<h3>Tambah nota</h3><div class="fld"><label>Uraian</label><input id="nu"></div><div class="two">${fld('Tanggal nota', `<input id="nd" type="date" value="${today() > p.p_end ? p.p_end : today()}" min="${p.p_start}" max="${p.p_end}">`)}${fld('Nominal', '<input id="nn" inputmode="numeric">')}</div>${fld('Rencana terkait', `<select id="np"><option value="">Di luar rencana</option>${p.plan.map((r, i) => `<option value="${i}">${esc(r.uraian)}</option>`).join('')}</select>`)}<div id="nf"></div><button class="btn g sm" id="nfa">+ Foto nota</button><button class="btn" id="nok" style="margin-top:10px">SIMPAN NOTA</button>`, (el, close) => {
    $('#nfa', el).onclick = () => pickFiles(fs => { nf.push(...fs); $('#nf', el).innerHTML = thumbs(nf); }); $('#nn', el).oninput = e => e.target.value = fmtN(num(e.target.value));
    $('#nok', el).onclick = () => guard(async () => { await api('pc_note', { id, nominal: num($('#nn', el).value), date: $('#nd', el).value, uraian: $('#nu', el).value, plan_idx: $('#np', el).value, files: nf.map(f => f.id) }); close(); await done('Nota ditambahkan.'); }); }); });
  $('#psend')?.addEventListener('click', () => confirm('Kirim pertanggungjawaban? Nota tidak dapat ditambah lagi.') && guard(async () => { const r = await api('pc_report', { id }); await done(r.excess ? `Terkirim. Kelebihan ${rp(r.excess)} menjadi klaim reimburse.` : r.sisa ? `Terkirim. Setor sisa ${rp(r.sisa)}.` : 'Terkirim. Sisa nol.'); }));
  $('#pset')?.addEventListener('click', () => { let sf = []; sheet(`<h3>Setoran sisa ${rp(p.sisa)}</h3><div class="note blue">Transfer ke rekening perusahaan lalu unggah bukti.</div>${fld('Nomor setoran / referensi', '<input id="sn">')}<div id="sfl"></div><button class="btn g sm" id="sfa">+ Bukti setoran</button><button class="btn" id="sok" style="margin-top:10px">KIRIM BUKTI</button>`, (el, close) => { $('#sfa', el).onclick = () => pickFiles(fs => { sf.push(...fs); $('#sfl', el).innerHTML = thumbs(sf); }); $('#sok', el).onclick = () => guard(async () => { await api('pc_setoran', { id, setoran_no: $('#sn', el).value, files: sf.map(f => f.id) }); close(); await done('Bukti setoran terkirim.'); }); }); });
  $('#pclose')?.addEventListener('click', () => guard(async () => { await api('pc_close', { id }); await done('Petty cash selesai.'); }));
  $('#pfix')?.addEventListener('click', () => { PF = { purpose: p.purpose, remark: p.remark || '', location: p.location || '', p_start: p.p_start, p_end: p.p_end, plan: p.plan.map(r => ({ ...r })), fixId: id }; location.hash = '#/pcfix'; });
};
R.pcfix = async () => { if (!PF || !PF.fixId) return location.hash = '#/pc'; const id = PF.fixId; const sum = PF.plan.reduce((a, r) => a + num(r.nominal), 0); const bb = await api('budget');
  shell(hero('Perbaiki petty cash', 'P19', { back: true, small: true }) + `<div class="sec"><div class="card">${fld('Tujuan', `<input id="fpu" value="${esc(PF.purpose)}">`)}${fld('Keterangan tambahan (opsional)', `<textarea id="fpr">${esc(PF.remark || '')}</textarea>`)}<div class="two">${fld('Mulai', `<input id="fps" type="date" value="${PF.p_start}">`)}${fld('Selesai', `<input id="fpe" type="date" value="${PF.p_end}">`)}</div></div>${PF.plan.map((r, i) => `<div class="card">${fld('Uraian', `<input data-fu="${i}:uraian" value="${esc(r.uraian)}">`)}${fld('Nominal', `<input data-fu="${i}:nominal" value="${fmtN(r.nominal)}" inputmode="numeric">`)}<div class="mut">${esc(r.kategori)}</div></div>`).join('')}<button class="btn" id="fgo">KIRIM ULANG</button></div>`, { hero: false });
  $('#app').oninput = e => { const t = e.target; if (t.dataset.fu) { const [i, k] = t.dataset.fu.split(':'); let v = t.value; if (k === 'nominal') { v = num(v); t.value = fmtN(v); } PF.plan[i][k] = v; } };
  $('#fgo').onclick = () => guard(async () => { const plan = PF.plan.map(r => ({ ...r, nominal: num(r.nominal) })); await api('pc_act', { id, action: 'rev_done', payload: { purpose: $('#fpu').value, remark: $('#fpr').value, p_start: $('#fps').value, p_end: $('#fpe').value, plan, amount: plan.reduce((a, r) => a + r.nominal, 0) } }); PF = null; toast('Terkirim ulang.'); location.hash = '#/pcd/' + id; });
};

/* ---------- rekap dan export (A11/U04) ---------- */
R.rekap = async () => {
  shell(hero('Rekap dan export', 'EXCEL', { back: true }) + `<div class="sec"><div class="card"><div class="note blue">Export mengikuti cakupan peran Anda. Setiap export tercatat (siapa, waktu, filter, jumlah baris).</div>${fld('Kategori', `<select id="xc"><option value="">Semua</option>${CATS_LIST.map(c => `<option>${c}</option>`).join('')}</select>`)}${fld('Status', '<select id="xs"><option value="">Semua</option><option value="WAIT">Menunggu</option><option value="REV">Perlu Revisi</option><option value="DONE">Selesai</option><option value="REJECTED">Ditolak</option></select>')}<div class="two">${fld('Dari', '<input type="date" id="xf">')}${fld('Sampai', '<input type="date" id="xt">')}</div><button class="cta" id="xgo">UNDUH EXCEL (.xlsx)</button></div></div>`, { hero: false });
  $('#xgo').onclick = () => { const q = new URLSearchParams({ a: 'export', category: $('#xc').value, status: $('#xs').value, from: $('#xf').value, to: $('#xt').value }); location.href = 'api.php?' + q; toast('Mengunduh...'); };
};

/* isi BA per pemicu: penjelasan, tabel otomatis, nota hilang, temuan */
function baSections(b, d) {
  const x = b.extra || {}, ex = x.expl || {}; const codes = b.trigger_codes || []; const notes = d.notes || [];
  const nl = (arr) => (arr || []).map(n => 'Nota ' + n).join(', ');
  const tb = (code) => code === 'DIGIT_BBM' && (x.digit || []).length ? `<table class="batb"><tr><th>Tanggal</th><th>Jumlah</th><th>Seharusnya</th><th>Klaim</th></tr>${x.digit.map(r => `<tr><td>${esc(r.date || '')}</td><td>${rp(r.jumlah)}</td><td>${rp(r.seharusnya)}</td><td>${rp(r.klaim)}</td></tr>`).join('')}</table>`
    : code === 'NOTA_HARI' && (x.hari || []).length ? `<table class="batb"><tr><th>Nota</th><th>Tanggal</th><th>Hari</th><th>Nominal</th></tr>${x.hari.map(r => `<tr><td>${r.line}</td><td>${esc(r.date || '')}</td><td>${esc(r.hari)}</td><td>${rp(r.nominal)}</td></tr>`).join('')}</table>` : '';
  if (!Object.keys(ex).length) return `<p>${esc(b.explanation)}</p>`; // BA lama: satu penjelasan
  return codes.filter(cd => ex[cd]).map((cd, i) => `<div style="margin-top:8px"><b>${i + 1}. ${esc(BA_TITLE[cd] || (b.triggers || [])[codes.indexOf(cd)] || cd)}</b>${tb(cd)}${cd === 'KEHILANGAN_NOTA' && x.lost ? `<div class="mut">${nl(x.lost)}</div>` : ''}${(cd === 'TEMUAN_ADMIN' || cd === 'TEMUAN_LAIN') ? `<div class="mut">${esc(TEMUAN_KAT[x.kategori] || '')}${(x.related || []).length ? ' · ' + nl(x.related) : ''}</div>` : ''}<p style="margin:4px 0">${esc(ex[cd])}</p></div>`).join('');
}
