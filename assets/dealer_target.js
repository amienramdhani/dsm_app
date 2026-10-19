/* DSM Target SO per dealer — Tahap A: unggah Excel (Admin/Super Admin) + tinjau read-only (semua role sesuai kepemilikan dealer). Belum memengaruhi skor KPI. */
(function(){
'use strict';
const E=s=>(typeof esc==='function'?esc(s):String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));
const N=n=>Number(n||0).toLocaleString('id-ID',{maximumFractionDigits:2});
const ID='dtgPageApp';
let S={period:'',brand:'',region:'',q:'',page:1,data:null,stage:null,msg:null,busy:false};
let timer=null,seq=0;
const css=`
.dtgWrap{display:grid;gap:12px}
.dtgHero{display:flex;gap:12px;align-items:center;background:linear-gradient(135deg,#eef6ff,#fff);border:1px solid #cfe1f5;border-radius:16px;padding:12px 14px;box-shadow:0 6px 18px rgba(43,123,224,.08)}
.dtgHero i{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#0A5BC4,#2B7BE0);color:#fff;display:grid;place-items:center;font-style:normal;font-size:19px;flex:none}
.dtgHero b{display:block;font-size:14px}.dtgHero small{color:#6b7a90;font-weight:600;font-size:11.5px;line-height:1.4}
.dtgCard{border:1px solid #e0e7ef;border-radius:16px;background:#fff;padding:12px 14px;box-shadow:0 4px 14px rgba(20,40,80,.05)}
.dtgCard h4{margin:0 0 6px;font-size:13.5px}
.dtgTools{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.dtgTools .grow{flex:1 1 220px;min-width:160px}
.dtgTools input,.dtgTools select,.dtgCard input.fi{border:1px solid #d6e2ee;background:#fff;border-radius:11px;padding:9px 11px;font:600 12.5px system-ui;color:#16202e;max-width:100%;box-sizing:border-box}
.dtgBtn{border:0;border-radius:11px;padding:10px 16px;font:800 12.5px system-ui;cursor:pointer;color:#fff;background:linear-gradient(135deg,#0A5BC4,#2B7BE0);box-shadow:0 5px 12px rgba(43,123,224,.28)}.dtgBtn.ghost{background:#fff;color:#51647d;border:1px solid #d7e0eb;box-shadow:none}.dtgBtn:disabled{opacity:.55;cursor:not-allowed}
.dtgBox{border:1px solid #e0e7ef;border-radius:14px;overflow:auto;background:#fff;max-height:min(520px,60vh)}
.dtgTbl{width:100%;border-collapse:collapse;font-size:12.5px}.dtgTbl th{position:sticky;top:0;background:#f3f7fc;border-bottom:2px solid #cfdcec;text-align:left;font:800 10.5px system-ui;letter-spacing:.05em;color:#0A2E57;padding:9px 10px;white-space:nowrap}.dtgTbl td{padding:9px 10px;border-bottom:1px solid #eef2f7;vertical-align:top}.dtgTbl .r{text-align:right;white-space:nowrap}.dtgTbl small{display:block;color:#7c8899;font-size:10.5px}
.dtgTag{display:inline-block;border-radius:99px;padding:3px 9px;font:800 10.5px system-ui}.dtgTag.BARU{background:#e8f7f0;color:#0e7a52}.dtgTag.UPDATE{background:#fff1d6;color:#b86a00}
.dtgPager{display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap;font-size:12px;color:#51647d;font-weight:700;margin-top:8px}
.dtgMsg{border-radius:12px;padding:10px 12px;font-size:12.5px;font-weight:700}.dtgMsg.ok{background:#e8f7f0;color:#0e7a52;border:1px solid #bfe7d4}.dtgMsg.bad{background:#fdecef;color:#b24259;border:1px solid #f4c6cf}.dtgMsg.warn{background:#fff6e5;color:#8a5a00;border:1px solid #f1d9a5}
.dtgStats{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0}.dtgStats div{background:#f8fafc;border:1px solid #e3e9f1;border-radius:10px;padding:8px 12px}.dtgStats small{display:block;color:#7b889a;font:800 9.5px system-ui;text-transform:uppercase;letter-spacing:.05em}.dtgStats b{font-size:14px}
@media(max-width:760px){
 .dtgBox{max-height:none;border:0;overflow:visible;background:transparent}
 .dtgTbl thead{display:none}.dtgTbl,.dtgTbl tbody{display:block}.dtgTbl tr{display:grid;grid-template-columns:1fr 1fr;gap:4px 10px;background:#fff;border:1px solid #e0e7ef;border-radius:13px;padding:10px;margin-bottom:8px}
 .dtgTbl td{display:block;border:0;padding:0}.dtgTbl td::before{content:attr(data-l);display:block;color:#7b889a;font:800 9px system-ui;text-transform:uppercase;letter-spacing:.05em}.dtgTbl td.wide{grid-column:1/-1}.dtgTbl .r{text-align:left}
}`;
function addCss(){if(document.getElementById('dtgStyle'))return;const s=document.createElement('style');s.id='dtgStyle';s.textContent=css;document.head.appendChild(s)}
const root=()=>document.getElementById(ID);
function msg(){return S.msg?`<div class="dtgMsg ${E(S.msg.t)}">${E(S.msg.m)}</div>`:''}
function shell(){
 const r=root();if(!r)return;const d=S.data;
 r.innerHTML=`<div class="dtgWrap">
 <div class="dtgHero"><i>◎</i><div><b>Target SO per dealer</b><small>Target sell out per dealer dan brand untuk satu periode. Tahap ini hanya menyimpan dan menampilkan target; skor KPI belum memakainya.</small></div></div>
 <div id="dtgMsg">${msg()}</div>
 ${d.canEdit?`<div class="dtgCard"><h4>Unggah target dari Excel</h4>
  <div class="dtgTools"><button type="button" class="dtgBtn ghost" id="dtgTpl">Download template</button>
  <form id="dtgForm" style="display:contents"><input type="hidden" name="token"><input class="fi grow" type="file" name="file" accept=".xlsx,.csv" required><button class="dtgBtn">Periksa file</button></form></div>
  <div id="dtgStage"></div></div>`:''}
 <div class="dtgCard"><h4>Target tersimpan</h4>
  <div class="dtgTools"><select id="dtgPeriod" aria-label="Periode">${d.periods.map(p=>`<option value="${E(p)}"${p===S.period?' selected':''}>${E(p)}</option>`).join('')}</select>
  <select id="dtgBrand" aria-label="Brand"><option value="">Semua brand</option>${d.brands.map(b=>`<option value="${E(b)}"${b===S.brand?' selected':''}>${E(b)}</option>`).join('')}</select>
 <select id="dtgRegion" aria-label="Region"><option value="">Semua region</option>${(d.regions||[]).map(x=>`<option value="${E(x)}"${x===S.region?' selected':''}>${E(x)}</option>`).join('')}</select>
  <input id="dtgQ" class="grow" placeholder="Cari dealer / Sales…" value="${E(S.q)}"></div>
  <div id="dtgList"></div></div></div>`;
 bind();renderStage();renderList();
}
function bind(){
 const r=root();
 r.querySelector('#dtgPeriod').onchange=e=>{S.period=e.target.value;S.page=1;load()};
 r.querySelector('#dtgBrand').onchange=e=>{S.brand=e.target.value;S.page=1;load()};
 r.querySelector('#dtgRegion').onchange=e=>{S.region=e.target.value;S.page=1;load()};
 const q=r.querySelector('#dtgQ');q.oninput=()=>{S.q=q.value;clearTimeout(timer);timer=setTimeout(()=>{S.page=1;load(true)},380)};
 const tpl=r.querySelector('#dtgTpl');if(tpl)tpl.onclick=async()=>{tpl.disabled=true;try{downloadBase64(await rpc('apiDealerTargetTemplate',TOKEN))}catch(e){S.msg={t:'bad',m:e.message||String(e)};setMsg()}finally{tpl.disabled=false}};
 const f=r.querySelector('#dtgForm');if(f)f.onsubmit=async e=>{e.preventDefault();f.elements.token.value=TOKEN;const box=r.querySelector('#dtgStage');box.innerHTML='<p class="hint">Memeriksa…</p>';try{S.stage=await rpc('apiDealerTargetStage',f);renderStage()}catch(x){S.stage=null;box.innerHTML=`<div class="dtgMsg bad">${E(x.message||String(x))}</div>`}};
}
function setMsg(){const m=document.getElementById('dtgMsg');if(m)m.innerHTML=msg()}
function renderStage(){
 const box=document.getElementById('dtgStage');if(!box)return;const s=S.stage;if(!s){box.innerHTML='';return}
 box.innerHTML=`<div class="dtgStats"><div><small>Baru</small><b>${N(s.baru)}</b></div><div><small>Update</small><b>${N(s.update)}</b></div><div><small>Sama (dilewati)</small><b>${N(s.same)}</b></div><div><small>Ditolak</small><b style="color:${s.rejected?'#b24259':'#0e7a52'}">${N(s.rejected)}</b></div></div>
 ${s.sample.length?`<div class="dtgBox"><table class="dtgTbl"><thead><tr><th>STATUS</th><th>DEALER</th><th>BRAND</th><th>PERIODE</th><th class="r">TARGET LAMA</th><th class="r">TARGET BARU</th></tr></thead><tbody>${s.sample.map(x=>`<tr><td data-l="Status"><span class="dtgTag ${E(x.status)}">${E(x.status)}</span></td><td data-l="Dealer" class="wide"><b>${E(x.nama||x.dealer)}</b><small>${E(x.dealer)}</small></td><td data-l="Brand">${E(x.brand)}</td><td data-l="Periode">${E(x.period)}</td><td data-l="Lama" class="r">${x.old==null?'—':N(x.old)}</td><td data-l="Baru" class="r"><b>${N(x.value)}</b></td></tr>`).join('')}</tbody></table></div>${s.total>s.sample.length?`<p class="hint">Menampilkan ${s.sample.length} dari ${N(s.total)} baris.</p>`:''}`:'<div class="dtgMsg warn">Tidak ada baris baru atau berubah untuk disimpan.</div>'}
 ${s.rejected?`<div class="dtgMsg warn" style="margin-top:8px"><button type="button" class="dtgBtn ghost" id="dtgRej">Download DITOLAK</button><ul style="margin:6px 0 0 18px">${s.rejSample.slice(0,6).map(x=>`<li>${E(x.alasan)} → <i>${E(x.solusi)}</i></li>`).join('')}</ul></div>`:''}
 ${s.total?`<div class="dtgTools" style="margin-top:10px"><input class="fi grow" id="dtgReason" placeholder="Alasan (wajib, min. 5 karakter)"><button type="button" class="dtgBtn" id="dtgGo">Simpan ${N(s.total)} target</button></div><div id="dtgErr"></div>`:''}`;
 const rj=box.querySelector('#dtgRej');if(rj)rj.onclick=async()=>{try{downloadBase64(await rpc('apiDealerTargetRejected',TOKEN,s.id))}catch(e){S.msg={t:'bad',m:e.message||String(e)};setMsg()}};
 const go=box.querySelector('#dtgGo');if(go)go.onclick=async()=>{
  const why=box.querySelector('#dtgReason').value.trim(),err=box.querySelector('#dtgErr');
  if(why.length<5){err.innerHTML='<div class="dtgMsg bad">Isi alasan minimal 5 karakter.</div>';return}
  if(!confirm('Simpan '+s.total+' target SO dealer?'))return;
  go.disabled=true;go.textContent='Menyimpan…';
  try{const c=await rpc('apiDealerTargetCommit',TOKEN,s.id,why);S.stage=null;S.msg={t:'ok',m:`Tersimpan: ${c.baru} baru, ${c.update} diperbarui, ${c.same} sama.`};await load(false,true)}
  catch(e){go.disabled=false;go.textContent='Simpan '+s.total+' target';err.innerHTML=`<div class="dtgMsg bad">${E(e.message||String(e))}</div>`}
 };
}
function renderList(){
 const box=document.getElementById('dtgList');if(!box)return;const d=S.data;
 if(S.busy){box.innerHTML='<p class="hint">Memuat…</p>';return}
 if(!d.rows.length){box.innerHTML=`<div class="dtgMsg ok" style="margin-top:8px">Belum ada target untuk filter ini.</div>`;return}
 box.innerHTML=`<div class="dtgStats"><div><small>Dealer-brand</small><b>${N(d.total)}</b></div><div><small>Total target</small><b>${N(d.sum)}</b></div></div>
 <div class="dtgBox"><table class="dtgTbl"><thead><tr><th>DEALER</th><th>BRAND</th><th>SALES SAAT INI</th><th class="r">TARGET SO</th>${d.canEdit?'<th>TERAKHIR DIUBAH</th>':''}</tr></thead><tbody>${d.rows.map(x=>`<tr><td data-l="Dealer" class="wide"><b>${E(x.nama||x.dealer)}</b><small>${E(x.dealer)}${x.region?' · '+E(x.region):''}</small></td><td data-l="Brand">${E(x.brand)}</td><td data-l="Sales">${E(x.owner||'—')}<small>${E(x.ownerNik)}</small></td><td data-l="Target" class="r"><b>${N(x.value)}</b></td>${d.canEdit?`<td data-l="Diubah">${E(String(x.at).slice(0,10))}<small>${E(x.actor)}</small></td>`:''}</tr>`).join('')}</tbody></table></div>
 <div class="dtgPager"><span>Halaman ${d.page} dari ${d.pages}</span><span><button type="button" class="dtgBtn ghost" id="dtgPrev"${d.page<=1?' disabled':''}>‹ Sebelumnya</button> <button type="button" class="dtgBtn ghost" id="dtgNext"${d.page>=d.pages?' disabled':''}>Berikutnya ›</button></span></div>`;
 const pv=box.querySelector('#dtgPrev'),nx=box.querySelector('#dtgNext');if(pv)pv.onclick=()=>{S.page--;load()};if(nx)nx.onclick=()=>{S.page++;load()};
}
async function load(listOnly,full){
 const my=++seq;S.busy=true;if(!full)renderList();
 try{const d=await rpc('apiDealerTargetList',TOKEN,S.period,S.q,S.brand,S.page,25,S.region);if(my!==seq)return;S.data=d;S.period=d.period;S.page=d.page}
 catch(e){if(my!==seq)return;S.msg={t:'bad',m:e.message||String(e)};if(!S.data){const r=root();if(r)r.innerHTML=`<div class="banner bad">${E(e.message||String(e))}</div>`;return}}
 S.busy=false;if(full){shell()}else{setMsg();renderList()}
}
async function openPage(id){
 addCss();if(!document.getElementById(id||ID))return;
 S.data=null;S.stage=null;S.msg=null;S.q='';S.brand='';S.region='';S.page=1;S.period='';
 await load(false,true);
}
window.dsmDealerTarget={openPage};
})();
