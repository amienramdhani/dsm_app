/* DSM - Ajari AI (Super Admin), 2026-11-24. Mockup doc/mockup/2026-11-24/dsm-ai-ajari-opsi.html
   Dibuka dari tombol AJARI AI di panel samping AI Assistant. Server: lib/api_ai_teach.php */
'use strict';
let AIT = null, AIT_TAB = 'a', AIT_SEL = null, AIT_FILTER = 'BARU', AIT_Q = '', AIT_DAYS = 30;
const aitEsc = s => (typeof esc === 'function' ? esc(s) : String(s ?? ''));
const aitDate = v => { if (!v) return '-'; const d = typeof v === 'number' ? new Date(v) : new Date(v); if (isNaN(d)) return String(v).slice(5, 16); return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); };
const AIT_ST = { BARU: ['BARU', 'b'], DIAJARI: ['SUDAH DIAJARI', 'a'], DIABAIKAN: ['DIABAIKAN', 'x'], DEV: ['UNTUK DEVELOPER', 's'] };
const AIT_KC = { ISTILAH: ['#6D3FD8', 'k'], ATURAN: ['#C25A12', 's'], 'GAYA JAWAB': ['#0E7C6B', 'a'] };

async function aiTeachView() {
  const v = $('view'); if (!v) return;
  v.innerHTML = '<div class="aiEmpty">Memuat Ajari AI…</div>';
  try { AIT = await rpc('apiAITeachHome', TOKEN, AIT_DAYS); } catch (e) { v.innerHTML = '<div class="banner bad">' + aitEsc(e.message) + '</div>'; return; }
  aitDraw();
}
function aitDraw() {
  const v = $('view'), s = AIT.stats;
  v.innerHTML = `<div class="aiT">
   <div class="aiTban"><div><h1>AJARI AI</h1><p>Super Admin · bantu AI menjawab pertanyaan yang belum bisa dijawab</p></div><span class="sp"></span><button type="button" class="aiTbk" data-aitback>← KEMBALI KE AI ASSISTANT</button></div>
   <div class="aiTkp"><div class="r"><small>BELUM TERJAWAB</small><b>${s.baru}</b><span>${AIT.days} hari terakhir</span></div><div><small>DITANYA BERULANG</small><b>${s.ulang}</b><span>ditanya ≥ 3 kali</span></div><div class="g"><small>ARAHAN AKTIF</small><b>${s.rules}</b><span>dipakai ${s.rulesUsed} kali</span></div><div><small>PENGETAHUAN</small><b>${s.know}</b><span>${AIT.knowLen} / ${AIT.knowMax} karakter</span></div><div class="hl"><small>TINGKAT TERJAWAB</small><b>${s.rate == null ? '-' : s.rate + '%'}</b><span>dari ${s.total} pertanyaan</span></div></div>
   <div class="aiTtb">${[['a', 'BELUM TERJAWAB', s.baru], ['b', 'ARAHAN', 0], ['c', 'PENGETAHUAN', 0]].map(t => `<span data-aittab="${t[0]}" class="${AIT_TAB === t[0] ? 'on' : ''}">${t[1]}${t[2] ? `<em>${t[2]}</em>` : ''}</span>`).join('')}</div>
   <div id="aiTbody"></div></div>`;
  aitBody();
}
function aitBody() {
  const b = $('aiTbody'); if (!b) return; aitBody2(b);
  b.querySelectorAll('.aiTt th').forEach(th => { th.style.setProperty('background', '#E6EBF1', 'important'); th.style.setProperty('color', '#0F172A', 'important'); th.style.setProperty('border-bottom', '2px solid #15303F', 'important'); });
}
function aitBody2(b) {
  if (AIT_TAB === 'a') {
    const L = AIT.unknown.filter(x => (AIT_FILTER === 'SEMUA' || x.status === AIT_FILTER) && (!AIT_Q || x.q.toLowerCase().includes(AIT_Q)));
    if (AIT_SEL && !AIT.unknown.find(x => x.qkey === AIT_SEL)) AIT_SEL = null; if (!AIT_SEL && L[0]) AIT_SEL = L[0].qkey;
    b.innerHTML = `<div class="aiTgr"><div class="aiTpn"><div class="h">Pertanyaan yang belum terjawab <small>dari catatan AI · pertanyaan yang sama dikelompokkan</small></div>
      <div class="aiTfb"><input id="aiTq" placeholder="Cari pertanyaan" value="${aitEsc(AIT_Q)}"><div class="aiTsg">${[['BARU', 'BARU'], ['DIAJARI', 'SUDAH DIAJARI'], ['DEV', 'UNTUK DEVELOPER'], ['DIABAIKAN', 'DIABAIKAN'], ['SEMUA', 'SEMUA']].map(x => `<span data-aitf="${x[0]}" class="${AIT_FILTER === x[0] ? 'on' : ''}">${x[1]}</span>`).join('')}</div><select id="aiTdays">${[7, 30, 90].map(d => `<option value="${d}" ${AIT_DAYS === d ? 'selected' : ''}>${d} hari terakhir</option>`).join('')}</select></div>
      <div class="aiTtw"><table class="aiTt v7gtT"><thead><tr><th>PERTANYAAN</th><th class="n">DITANYA</th><th>OLEH</th><th>TERAKHIR</th><th>ALASAN</th><th>STATUS</th></tr></thead><tbody>${L.map(x => `<tr data-aitsel="${aitEsc(x.qkey)}" class="${x.qkey === AIT_SEL ? 'sel' : ''}"><td><b>${aitEsc(x.q)}</b></td><td class="n"><span class="aiTcnt">${x.n}</span></td><td>${aitEsc(x.who.slice(0, 2).join(', '))}${x.who.length > 2 ? ', +' + (x.who.length - 2) : ''}</td><td>${aitDate(x.last)}</td><td><span class="aiTpl ${x.reason === 'AI SIBUK' ? 'x' : 's'}">${x.reason}</span></td><td><span class="aiTpl ${AIT_ST[x.status][1]}">${AIT_ST[x.status][0]}</span></td></tr>`).join('') || '<tr><td colspan="6" class="mut">Tidak ada pertanyaan di filter ini. 👍</td></tr>'}</tbody></table></div></div>
      <div class="aiTpn" id="aiTside"></div></div>`;
    aitSide();
    $('aiTq').oninput = e => { AIT_Q = e.target.value.trim().toLowerCase(); const p = e.target.selectionStart; aitBody(); const n = $('aiTq'); n.focus(); n.setSelectionRange(p, p); };
    $('aiTdays').onchange = e => { AIT_DAYS = +e.target.value; aiTeachView(); };
  } else if (AIT_TAB === 'b') {
    b.innerHTML = `<div class="aiTpn"><div class="h">Arahan <small>kata kunci / contoh pertanyaan → jawaban yang sudah ada · dicek sebelum AI</small><button type="button" class="aiTbtn b" data-aitrule="0" style="margin-left:auto">+ ARAHAN BARU</button></div>
      <div class="aiTtw"><table class="aiTt v7gtT"><thead><tr><th>KATA KUNCI / CONTOH</th><th>DIARAHKAN KE</th><th class="n">DIPAKAI</th><th>DIBUAT</th><th>AKTIF</th><th></th></tr></thead><tbody>${AIT.rules.map(r => `<tr><td><b>${aitEsc(r.keywords || '-')}</b>${r.example ? `<span class="sm">contoh: "${aitEsc(r.example)}"</span>` : ''}</td><td><span class="aiTpl a">${aitEsc((AIT.targets[r.target] || r.target).toUpperCase())}</span></td><td class="n">${r.used}</td><td>${aitEsc(r.created_name)} · ${aitDate(+r.created_at)}</td><td><span class="aiTsw ${+r.active ? '' : 'off'}" data-aittog="rule:${r.id}"></span></td><td class="aiTlk"><span data-aitrule="${r.id}">ubah</span> · <span data-aitdel="rule:${r.id}">hapus</span></td></tr>`).join('') || '<tr><td colspan="6" class="mut">Belum ada arahan.</td></tr>'}</tbody></table></div></div>`;
  } else {
    b.innerHTML = `<div class="aiTpn"><div class="h">Pengetahuan <small>catatan yang ikut dikirim ke AI setiap pertanyaan · ${AIT.knowLen} / ${AIT.knowMax} karakter terpakai</small><button type="button" class="aiTbtn b" data-aitknow="0" style="margin-left:auto">+ PENGETAHUAN</button></div>
      <div class="aiTkg">${AIT.know.map(k => { const c = AIT_KC[k.kind] || AIT_KC.ISTILAH; return `<div class="aiTkc" style="--c:${c[0]}"><div class="t"><span class="aiTpl ${c[1]}">${aitEsc(k.kind)}</span><b>${aitEsc(k.title)}</b><span class="aiTsw ${+k.active ? '' : 'off'}" data-aittog="know:${k.id}"></span></div><p>${aitEsc(k.body)}</p><div class="m">${aitEsc(k.created_name)} · ${aitDate(+k.created_at)} · dipakai ${k.used} kali${+k.active ? '' : ' · nonaktif'} <span class="aiTlk"><span data-aitknow="${k.id}">ubah</span> · <span data-aitdel="know:${k.id}">hapus</span></span></div></div>`; }).join('') || '<div class="mut" style="padding:10px">Belum ada pengetahuan.</div>'}</div></div>`;
  }
}
let AIT_MODE = 'rule';
function aitSide(prev) {
  const box = $('aiTside'); if (!box) return; const x = AIT.unknown.find(u => u.qkey === AIT_SEL);
  if (!x) { box.innerHTML = '<div class="h">Ajari jawaban</div><div class="bd mut">Pilih pertanyaan di tabel.</div>'; return; }
  const opts = Object.entries(AIT.targets).map(([k, l]) => `<option value="${k}">${aitEsc(l)}</option>`).join('');
  box.innerHTML = `<div class="h">Ajari jawaban</div><div class="bd">
   <div class="aiTqb"><b>"${aitEsc(x.q)}"</b><small>ditanya ${x.n} kali · ${x.who.length} orang · terakhir ${aitDate(x.last)}</small>${x.note ? `<small>Catatan: ${aitEsc(x.note)}</small>` : ''}</div>
   <div class="lb">PILIH CARA</div>
   <label class="aiTopt ${AIT_MODE === 'rule' ? 'on' : ''}" data-aitmode="rule"><i></i><div><b>Arahkan ke jawaban yang sudah ada</b><small>Pertanyaan ini artinya sama dengan yang AI sudah bisa jawab.</small>${AIT_MODE === 'rule' ? `<select id="aiTtg"><option value="">Pilih jawaban tujuan…</option>${opts}</select><small>Kata kunci (pisahkan koma; semua kata harus ada di pertanyaan):</small><input id="aiTkw" placeholder="mis. target aman, aman ga" value="${aitEsc(x.qkey.split(' ').slice(0, 3).join(' '))}">` : ''}</div></label>
   <label class="aiTopt ${AIT_MODE === 'know' ? 'on' : ''}" data-aitmode="know"><i></i><div><b>Tambah pengetahuan</b><small>Penjelasan istilah, aturan, atau kebijakan. Cocok untuk "apa itu …".</small>${AIT_MODE === 'know' ? `<select id="aiTkk"><option>ISTILAH</option><option>ATURAN</option><option>GAYA JAWAB</option></select><input id="aiTkt" placeholder="Judul / istilah, mis. NOO"><textarea id="aiTkb" placeholder="Isi penjelasan singkat (maks. 1.000 karakter)"></textarea>` : ''}</div></label>
   <label class="aiTopt ${AIT_MODE === 'dev' ? 'on' : ''}" data-aitmode="dev"><i></i><div><b>Belum bisa · catat untuk developer</b><small>Butuh data baru (mis. insentif, retur). Masuk daftar permintaan.</small>${AIT_MODE === 'dev' ? `<input id="aiTdn" placeholder="Catatan untuk developer (opsional)" value="${aitEsc(x.note || '')}">` : ''}</div></label>
   <div class="aiTprev" id="aiTprev"${prev ? '' : ' hidden'}>${prev || ''}</div>
   <div class="aiTbtns"><button type="button" class="aiTbtn r" data-aitmark="DIABAIKAN">ABAIKAN</button>${AIT_MODE === 'rule' ? '<button type="button" class="aiTbtn" data-aittest>UJI DULU</button>' : ''}<button type="button" class="aiTbtn b" data-aitsave>${AIT_MODE === 'dev' ? 'SIMPAN CATATAN' : 'SIMPAN &amp; AKTIFKAN'}</button></div>
   ${x.status !== 'BARU' ? '<button type="button" class="aiTlink" data-aitmark="BARU">Kembalikan ke status BARU</button>' : ''}</div>`;
}
function aitForm(what, id) {
  const r = id ? (what === 'rule' ? AIT.rules : AIT.know).find(z => z.id == id) : null;
  const opts = Object.entries(AIT.targets).map(([k, l]) => `<option value="${k}" ${r && r.target === k ? 'selected' : ''}>${aitEsc(l)}</option>`).join('');
  const body = what === 'rule'
    ? `<label>Jawaban tujuan</label><select id="aiTf1"><option value="">Pilih…</option>${opts}</select><label>Kata kunci (pisahkan koma)</label><input id="aiTf2" value="${aitEsc(r ? r.keywords : '')}" placeholder="mis. target aman, tembus ga"><label>Contoh pertanyaan (opsional)</label><input id="aiTf3" value="${aitEsc(r ? r.example : '')}">`
    : `<label>Jenis</label><select id="aiTf1">${['ISTILAH', 'ATURAN', 'GAYA JAWAB'].map(k => `<option ${r && r.kind === k ? 'selected' : ''}>${k}</option>`).join('')}</select><label>Judul / istilah</label><input id="aiTf2" value="${aitEsc(r ? r.title : '')}"><label>Isi (maks. 1.000 karakter)</label><textarea id="aiTf3">${aitEsc(r ? r.body : '')}</textarea>`;
  modal((id ? 'Ubah ' : 'Tambah ') + (what === 'rule' ? 'arahan' : 'pengetahuan'), `<div class="aiTform">${body}<button type="button" class="aiTbtn b" id="aiTfok">SIMPAN</button></div>`);
  $('aiTfok').onclick = async () => {
    const d = what === 'rule' ? { id, target: $('aiTf1').value, keywords: $('aiTf2').value, example: $('aiTf3').value, off: r && !+r.active } : { id, kind: $('aiTf1').value, title: $('aiTf2').value, body: $('aiTf3').value, off: r && !+r.active };
    try { await rpc('apiAITeachSave', TOKEN, what, d); if (typeof closeModal === 'function') closeModal(); else $('mask').classList.remove('on'); aiTeachView(); } catch (e) { alert(e.message); }
  };
}
document.addEventListener('click', async e => {
  const t = e.target.closest && e.target.closest('[data-aiteach],[data-aitback],[data-aittab],[data-aitf],[data-aitsel],[data-aitmode],[data-aitmark],[data-aitsave],[data-aittest],[data-aitrule],[data-aitknow],[data-aittog],[data-aitdel]'); if (!t) return;
  const D = t.dataset;
  if ('aiteach' in D) { AIT_TAB = 'a'; aiTeachView(); return; }
  if ('aitback' in D) { if (typeof aiView === 'function') aiView($('view'), SEQUENCE); return; }
  if (D.aittab) { AIT_TAB = D.aittab; aitDraw(); return; }
  if (D.aitf) { AIT_FILTER = D.aitf; AIT_SEL = null; aitBody(); return; }
  if (D.aitsel) { AIT_SEL = D.aitsel; aitBody(); return; }
  if (D.aitmode) { if (AIT_MODE !== D.aitmode) { AIT_MODE = D.aitmode; aitSide(); } return; }
  const x = AIT && AIT.unknown.find(u => u.qkey === AIT_SEL);
  try {
    if (D.aitmark) { await rpc('apiAITeachMark', TOKEN, AIT_SEL, D.aitmark, ''); return aiTeachView(); }
    if ('aittest' in D) { const tg = $('aiTtg').value; if (!tg) return alert('Pilih jawaban tujuan dulu.'); const p = $('aiTprev'); p.hidden = false; p.innerHTML = '<div class="lb">PRATINJAU JAWABAN AI</div>Menyiapkan…';
      const r = await rpc('apiAITeachTest', TOKEN, $('periodSelect').value, $('scopeSelect').value, tg, x.q);
      p.innerHTML = `<div class="lb">PRATINJAU JAWABAN AI</div><div class="aiTans"><div class="av">✦</div><div>${aitEsc(r.answer)}${(r.blocks || []).map(b => typeof aiBlock === 'function' ? aiBlock(b) : '').join('')}</div></div>`; return; }
    if ('aitsave' in D) {
      if (AIT_MODE === 'rule') { const tg = $('aiTtg').value; if (!tg) return alert('Pilih jawaban tujuan dulu.'); await rpc('apiAITeachSave', TOKEN, 'rule', { target: tg, keywords: $('aiTkw').value, example: x.q, qkey: x.qkey }); }
      else if (AIT_MODE === 'know') await rpc('apiAITeachSave', TOKEN, 'know', { kind: $('aiTkk').value, title: $('aiTkt').value, body: $('aiTkb').value, qkey: x.qkey });
      else await rpc('apiAITeachMark', TOKEN, x.qkey, 'DEV', $('aiTdn').value);
      return aiTeachView();
    }
    if (D.aitrule) return aitForm('rule', +D.aitrule || 0);
    if (D.aitknow) return aitForm('know', +D.aitknow || 0);
    if (D.aittog) { const [w, id] = D.aittog.split(':'); await rpc('apiAITeachSave', TOKEN, 'toggle', { what: w, id }); return aiTeachView(); }
    if (D.aitdel) { const [w, id] = D.aitdel.split(':'); if (!confirm('Hapus ' + (w === 'know' ? 'pengetahuan' : 'arahan') + ' ini?')) return; await rpc('apiAITeachDelete', TOKEN, w, id); return aiTeachView(); }
  } catch (err) { alert(err.message); }
});
