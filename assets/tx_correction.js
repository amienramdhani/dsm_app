/* DSM Koreksi Transaksi — antrean Perlu Ditinjau, pencarian transaksi, koreksi Sales efektif satu transaksi. Khusus Admin/Super Admin. */
(function(){
'use strict';
const E=s=>(typeof esc==='function'?esc(s):String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));
const N=n=>Number(n||0).toLocaleString('id-ID',{maximumFractionDigits:0});
const ID='txcPageApp';
let S={tab:'REVIEW',period:'',region:'',q:'',page:1,size:25,boot:null,data:null,items:[],busy:false,msg:null};
let timer=null,seq=0;

const css=`
.txcWrap{display:grid;gap:12px}
.txcHero{display:flex;gap:12px;align-items:center;background:linear-gradient(135deg,#fff8ef,#fff);border:1px solid #f3dcc0;border-radius:16px;padding:12px 14px;box-shadow:0 6px 18px rgba(240,122,12,.08)}
.txcHero i{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#f07a0c,#ff9a3c);color:#fff;display:grid;place-items:center;font-style:normal;font-size:19px;flex:none}
.txcHero b{display:block;font-size:14px}.txcHero small{color:#6b7a90;font-weight:600;font-size:11.5px;line-height:1.4}
.txcTabs{display:flex;gap:6px;flex-wrap:wrap}.txcTabs button{border:1px solid #d7e0eb;background:#fff;color:#51647d;border-radius:99px;padding:8px 14px;font:800 12px system-ui;cursor:pointer}.txcTabs button.on{background:linear-gradient(135deg,#f07a0c,#ff9a3c);color:#fff;border-color:transparent;box-shadow:0 4px 10px rgba(240,122,12,.3)}
.txcTools{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.txcTools .grow{flex:1 1 260px;min-width:180px}
.txcTools input,.txcTools select,.txcForm select,.txcForm textarea,.txcForm input{border:1px solid #d6e2ee;background:#fff;border-radius:11px;padding:9px 11px;font:600 12.5px system-ui;color:#16202e;max-width:100%;box-sizing:border-box}
.txcTools input.grow{width:100%}.txcForm textarea{width:100%;min-height:62px;resize:vertical}.txcForm select{width:100%}
.txcBtn{border:0;border-radius:11px;padding:10px 16px;font:800 12.5px system-ui;cursor:pointer;color:#fff;background:linear-gradient(135deg,#0A5BC4,#2B7BE0);box-shadow:0 5px 12px rgba(43,123,224,.28)}.txcBtn.warn{background:linear-gradient(135deg,#d94d65,#ef7388);box-shadow:0 5px 12px rgba(217,77,101,.25)}.txcBtn.ghost{background:#fff;color:#51647d;border:1px solid #d7e0eb;box-shadow:none}.txcBtn:disabled{opacity:.55;cursor:not-allowed}
.txcBox{border:1px solid #e0e7ef;border-radius:14px;overflow:auto;background:#fff;max-height:min(560px,62vh)}
.txcTbl{width:100%;border-collapse:collapse;font-size:12.5px}.txcTbl th{position:sticky;top:0;background:#f3f7fc;border-bottom:2px solid #cfdcec;text-align:left;font:800 10.5px system-ui;letter-spacing:.05em;color:#0A2E57;padding:9px 10px;white-space:nowrap}.txcTbl td{padding:9px 10px;border-bottom:1px solid #eef2f7;vertical-align:top}.txcTbl tr[data-i]{cursor:pointer}.txcTbl tr[data-i]:hover td{background:#fff8ef}.txcTbl .r{text-align:right;white-space:nowrap}
.txcTbl small{display:block;color:#7c8899;font-size:10.5px}
.txcTag{display:inline-block;border-radius:99px;padding:3px 9px;font:800 10.5px system-ui;white-space:nowrap}.txcTag.KOREKSI{background:#f1eafc;color:#6d3fd8}.txcTag.TRANSFER{background:#e8f4ff;color:#1f6fc2}.txcTag.ST_BERBEDA{background:#fff1d6;color:#b86a00}.txcTag.none{background:#f1f3f6;color:#8a97a8}
.txcWhy{display:block;color:#b24259;font-size:10.5px;font-weight:700;margin-top:3px}
.txcPager{display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap;font-size:12px;color:#51647d;font-weight:700}
.txcMsg{border-radius:12px;padding:10px 12px;font-size:12.5px;font-weight:700}.txcMsg.ok{background:#e8f7f0;color:#0e7a52;border:1px solid #bfe7d4}.txcMsg.bad{background:#fdecef;color:#b24259;border:1px solid #f4c6cf}.txcMsg.warn{background:#fff6e5;color:#8a5a00;border:1px solid #f1d9a5}
.txcOrph{border:1px solid #f1d9a5;background:#fffaf0;border-radius:14px;padding:10px 12px}.txcOrph h4{margin:0 0 6px;font-size:12.5px;color:#8a5a00}.txcOrph .o{display:flex;gap:10px;align-items:center;justify-content:space-between;padding:6px 0;border-top:1px dashed #f1d9a5;font-size:12px}.txcOrph .o:first-of-type{border-top:0}
.txcOv{position:fixed;inset:0;background:rgba(15,25,40,.45);display:flex;align-items:center;justify-content:center;z-index:9000;padding:14px}
.txcModal{background:#fff;border-radius:20px;box-shadow:0 24px 60px rgba(20,40,80,.3);width:min(720px,100%);max-height:92vh;overflow:auto;padding:18px}
.txcModal h3{margin:0 0 4px;font-size:16px}.txcModal .sub{color:#7c8899;font-size:11.5px;font-weight:600;margin-bottom:12px}
.txcInfo{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-bottom:12px}.txcInfo div{background:#f8fafc;border:1px solid #e3e9f1;border-radius:10px;padding:8px}.txcInfo small{display:block;color:#7b889a;font:800 9.5px system-ui;text-transform:uppercase;letter-spacing:.05em}.txcInfo b{font-size:12.5px;word-break:break-word}
.txcChain{display:grid;grid-template-columns:1fr auto 1fr auto 1fr;gap:6px;align-items:stretch;margin-bottom:12px}.txcChain .c{border:1px solid #e1e7ef;border-radius:12px;padding:9px;background:#fff}.txcChain .c.eff{border-color:#f07a0c;background:#fff8ef}.txcChain small{display:block;color:#7b889a;font:800 9.5px system-ui;text-transform:uppercase;letter-spacing:.05em}.txcChain b{font-size:12.5px;display:block;margin-top:2px}.txcChain .ar{align-self:center;color:#9aa6b5;font-weight:800}
.txcForm label{display:block;font:800 10px system-ui;text-transform:uppercase;letter-spacing:.05em;color:#66778f;margin:10px 0 4px}
.txcHist{margin-top:12px;border-top:1px solid #eef2f7;padding-top:8px;font-size:11.5px;color:#51647d}.txcHist div{padding:4px 0}
.txcAct{display:flex;gap:8px;justify-content:flex-end;margin-top:14px;flex-wrap:wrap}
@media(max-width:760px){
 .txcBox{max-height:none;border:0;overflow:visible;background:transparent}
 .txcTbl thead{display:none}.txcTbl,.txcTbl tbody{display:block}.txcTbl tr[data-i]{display:grid;grid-template-columns:1fr 1fr;gap:4px 10px;background:#fff;border:1px solid #e0e7ef;border-radius:13px;padding:10px;margin-bottom:8px}
 .txcTbl td{display:block;border:0;padding:0}.txcTbl td::before{content:attr(data-l);display:block;color:#7b889a;font:800 9px system-ui;text-transform:uppercase;letter-spacing:.05em}.txcTbl td.wide{grid-column:1/-1}.txcTbl .r{text-align:left}
 .txcInfo{grid-template-columns:1fr 1fr}.txcChain{grid-template-columns:1fr}.txcChain .ar{display:none}
}`;
function addCss(){if(document.getElementById('txcStyle'))return;const s=document.createElement('style');s.id='txcStyle';s.textContent=css;document.head.appendChild(s)}
const root=()=>document.getElementById(ID);
const who=p=>p&&p.nik?`${E(p.nama||p.nik)}<small>${E(p.nik)}</small>`:'<small>—</small>';
const whoLine=p=>p&&p.nik?`${E(p.nama||p.nik)} (${E(p.nik)})`:'—';
const tag=it=>it.marker?`<span class="txcTag ${E(it.marker)}">${E(it.markerLabel)}</span>`:'<span class="txcTag none">Tanpa marker</span>';
const money=n=>'Rp '+N(n);

function shell(){
 const b=S.boot,r=root();if(!r)return;
 const tabs=[['REVIEW','Perlu Ditinjau'],['CORRECTED','Sudah Dikoreksi'],['KEEP','Kredit Tetap ke Penjual'],['SEARCH','Cari Transaksi']];
 r.innerHTML=`<div class="txcWrap">
  <div class="txcHero"><i>✎</i><div><b>Koreksi Sales efektif satu transaksi</b><small>Hanya mengganti Sales penerima transaksi yang dipilih. Data ST mentah, kepemilikan dealer, target, dan transaksi lain tidak berubah. Total company tetap sama.</small></div></div>
  <div id="txcMsg"></div>
  <div id="txcOrph"></div>
  <div class="txcTabs">${tabs.map(t=>`<button type="button" data-tab="${t[0]}" class="${S.tab===t[0]?'on':''}">${t[1]}</button>`).join('')}</div>
  <div class="txcTools">
   <select id="txcPeriod" aria-label="Periode">${b.periods.map(p=>`<option value="${E(p)}"${p===S.period?' selected':''}>${E(p)}</option>`).join('')}</select>
   <select id="txcRegion" aria-label="Region"><option value="">Semua region</option>${b.regions.map(x=>`<option value="${E(x)}"${x===S.region?' selected':''}>${E(x)}</option>`).join('')}</select>
   <input id="txcQ" class="grow" placeholder="${S.tab==='SEARCH'?'Invoice, kode/nama dealer, SKU, atau nama Sales (min. 3 huruf)…':'Cari invoice, dealer, SKU, atau Sales…'}" value="${E(S.q)}">
   ${S.tab==='SEARCH'?'<button type="button" class="txcBtn" id="txcGo">Cari</button>':`<select id="txcSize" aria-label="Jumlah baris">${[25,50,100].map(n=>`<option value="${n}"${n===S.size?' selected':''}>${n} baris</option>`).join('')}</select>`}
  </div>
  <div id="txcList"></div>
 </div>`;
 r.querySelectorAll('[data-tab]').forEach(x=>x.onclick=()=>{S.tab=x.dataset.tab;S.page=1;S.items=[];S.data=null;shell();renderOrph();if(S.tab!=='SEARCH')load();else renderList()});
 r.querySelector('#txcPeriod').onchange=e=>{S.period=e.target.value;S.page=1;S.tab==='SEARCH'?(S.items=[],renderList()):load()};
 r.querySelector('#txcRegion').onchange=e=>{S.region=e.target.value;S.page=1;S.tab==='SEARCH'?(S.items=[],renderList()):load()};
 const q=r.querySelector('#txcQ');q.oninput=()=>{S.q=q.value;if(S.tab==='SEARCH')return;clearTimeout(timer);timer=setTimeout(()=>{S.page=1;load()},380)};
 q.onkeydown=e=>{if(e.key==='Enter'){clearTimeout(timer);S.q=q.value;S.page=1;S.tab==='SEARCH'?search():load()}};
 const sz=r.querySelector('#txcSize');if(sz)sz.onchange=e=>{S.size=Number(e.target.value);S.page=1;load()};
 const go=r.querySelector('#txcGo');if(go)go.onclick=()=>{S.q=q.value;search()};
 showMsg();renderOrph();renderList();
}
function showMsg(){const m=document.getElementById('txcMsg');if(!m)return;m.innerHTML=S.msg?`<div class="txcMsg ${E(S.msg.t)}">${E(S.msg.m)}</div>`:''}
function renderOrph(){
 const box=document.getElementById('txcOrph');if(!box)return;const o=S.boot?.orphans||[];
 if(!o.length){box.innerHTML='';return}
 box.innerHTML=`<div class="txcOrph"><h4>${o.length} koreksi tidak menempel ke transaksi mana pun dan tidak diterapkan</h4>${o.map(x=>`<div class="o"><span><b>${E(x.period)}</b> · ${E(x.ref.replace(/^[LC]\|/,'').replace(/\|/g,' · ')).slice(0,90)}<br><small>${E(x.why)} · ke ${whoLine(x.to)} · ${E(x.reason)}</small></span><button type="button" class="txcBtn warn" data-revoke="${x.id}">Cabut</button></div>`).join('')}</div>`;
 box.querySelectorAll('[data-revoke]').forEach(b=>b.onclick=()=>revokeDialog(Number(b.dataset.revoke)));
}
function rowHtml(it,i){
 return `<tr data-i="${i}"><td data-l="Tanggal">${E(it.date)}</td>
  <td data-l="Dealer" class="wide"><b>${E(it.dealer||it.induk)}</b><small>${E(it.customer)} · ${E(it.region)}</small></td>
  <td data-l="Dokumen">${E(it.doc)}${it.line?`<small>baris ${E(it.line)}</small>`:''}</td>
  <td data-l="Brand / SKU">${E(it.brand)}<small>${E(it.sku)}</small></td>
  <td data-l="Qty" class="r">${N(it.qty)}<small>${money(it.amount)}</small></td>
  <td data-l="Sales di file ST">${who(it.raw)}</td>
  <td data-l="Sales efektif">${who(it.eff)}</td>
  <td data-l="Marker" class="wide">${tag(it)}${it.review&&it.why?`<span class="txcWhy">${E(it.why)}</span>`:''}</td></tr>`;
}
function renderList(){
 const box=document.getElementById('txcList');if(!box)return;
 if(S.busy){box.innerHTML='<p class="hint">Memuat…</p>';return}
 if(S.tab==='SEARCH'&&!S.items.length&&!S.searched){box.innerHTML='<div class="txcMsg warn">Cari transaksi mana pun pada periode terpilih untuk dikoreksi, misalnya dari nomor invoice atau kode dealer.</div>';return}
 if(!S.items.length){box.innerHTML=`<div class="txcMsg ok">${S.tab==='REVIEW'?'Tidak ada transaksi yang perlu ditinjau pada filter ini.':S.tab==='CORRECTED'?'Belum ada transaksi yang dikoreksi pada filter ini.':S.tab==='KEEP'?'Tidak ada transaksi penjual kredit-tetap yang pemilik dealernya berbeda pada filter ini.':'Tidak ada transaksi yang cocok.'}</div>`;return}
 const d=S.data||{};
 box.innerHTML=`<div class="txcBox"><table class="txcTbl"><thead><tr><th>TANGGAL</th><th>DEALER</th><th>DOKUMEN</th><th>BRAND / SKU</th><th class="r">QTY / OMZET</th><th>SALES DI FILE ST</th><th>SALES EFEKTIF</th><th>MARKER</th></tr></thead><tbody>${S.items.map(rowHtml).join('')}</tbody></table></div>
  <div class="txcPager"><span>${S.tab==='SEARCH'?`Menampilkan ${S.items.length} dari ${N(d.total)} transaksi${d.capped?' — persempit pencarian untuk melihat sisanya':''}`:`${N(d.total)} transaksi · halaman ${d.page} dari ${d.pages}`}</span>${S.tab==='SEARCH'?'':`<span><button type="button" class="txcBtn ghost" id="txcPrev"${d.page<=1?' disabled':''}>‹ Sebelumnya</button> <button type="button" class="txcBtn ghost" id="txcNext"${d.page>=d.pages?' disabled':''}>Berikutnya ›</button></span>`}</div>`;
 box.querySelectorAll('tr[data-i]').forEach(tr=>tr.onclick=()=>detail(S.items[Number(tr.dataset.i)]));
 const pv=box.querySelector('#txcPrev'),nx=box.querySelector('#txcNext');if(pv)pv.onclick=()=>{S.page--;load()};if(nx)nx.onclick=()=>{S.page++;load()};
}
async function load(){
 const my=++seq;S.busy=true;renderList();
 try{const r=await rpc('apiTxCorrList',TOKEN,{period:S.period,status:S.tab,region:S.region,q:S.q,page:S.page,size:S.size});if(my!==seq)return;S.data=r;S.items=r.items;S.searched=true;}
 catch(e){if(my!==seq)return;S.items=[];S.msg={t:'bad',m:e.message||String(e)};showMsg();}
 S.busy=false;renderList();
}
async function search(){
 const my=++seq;S.busy=true;S.msg=null;showMsg();renderList();
 try{const r=await rpc('apiTxCorrSearch',TOKEN,{period:S.period,region:S.region,q:S.q});if(my!==seq)return;S.data=r;S.items=r.items;S.searched=true;}
 catch(e){if(my!==seq)return;S.items=[];S.searched=false;S.msg={t:'bad',m:e.message||String(e)};showMsg();}
 S.busy=false;renderList();
}
function overlay(html){
 closeModal();const o=document.createElement('div');o.className='txcOv';o.id='txcOv';o.innerHTML=`<div class="txcModal" role="dialog" aria-modal="true">${html}</div>`;
 o.addEventListener('mousedown',e=>{if(e.target===o&&!S.saving)closeModal()});document.body.appendChild(o);return o;
}
function closeModal(){const o=document.getElementById('txcOv');if(o)o.remove()}
async function detail(it){
 if(!it)return;
 const cands=(S.boot.candidates||[]).filter(c=>c.nik!==it.eff.nik);
 const o=overlay(`<h3>Koreksi transaksi</h3><div class="sub">${E(it.doc)}${it.line?' · baris '+E(it.line):''} · ${E(it.date)}</div>
  <div class="txcInfo"><div><small>Dealer</small><b>${E(it.dealer||it.induk)}</b></div><div><small>Kode / Region</small><b>${E(it.customer)} · ${E(it.region)}</b></div><div><small>Brand / SKU</small><b>${E(it.brand)} · ${E(it.sku)}</b></div><div><small>Qty</small><b>${N(it.qty)}</b></div><div><small>Omzet</small><b>${money(it.amount)}</b></div><div><small>Marker</small><b>${tag(it)}</b></div></div>
  <div class="txcChain"><div class="c"><small>Sales di file ST</small><b>${whoLine(it.raw)}</b></div><span class="ar">›</span><div class="c"><small>Pemilik dealer-brand</small><b>${whoLine(it.owner)}</b></div><span class="ar">›</span><div class="c eff"><small>Sales efektif sekarang</small><b>${whoLine(it.eff)}</b></div></div>
  ${it.review&&it.why?`<div class="txcMsg warn">${E(it.why)}</div>`:''}
  <div class="txcForm"><label for="txcTo">Sales efektif yang benar</label><select id="txcTo"><option value="">Pilih Sales…</option>${cands.map(c=>`<option value="${E(c.nik)}" data-nama="${E(c.nama)}" data-role="${E(c.role||'')}" data-region="${E(c.region||'')}" data-nik="${E(c.nik)}">${E(c.nama)} · ${E(c.nik)}${c.region?' · '+E(c.region):''}</option>`).join('')}</select>
  <label for="txcWhyIn">Alasan koreksi (min. 5 karakter)</label><textarea id="txcWhyIn" placeholder="Contoh: transaksi dikerjakan Sales B, tercatat di Sales A pada ST"></textarea>
  <div id="txcErr"></div></div>
  <div class="txcAct">${it.corrId?'<button type="button" class="txcBtn warn" id="txcRev">Cabut koreksi</button>':''}<button type="button" class="txcBtn ghost" id="txcCancel">Tutup</button><button type="button" class="txcBtn" id="txcSave">Simpan koreksi</button></div>
  <div class="txcHist" id="txcHist"><i>Memuat riwayat…</i></div>`);
 try{if(typeof window.dsmPersonSelect==='function')window.dsmPersonSelect(o.querySelector('#txcTo'),{title:'Pilih Sales efektif',ph:'Pilih Sales…'});else if(typeof window.dsmSearchable==='function')window.dsmSearchable(o.querySelector('#txcTo'),'Ketik nama / NIK Sales…')}catch(e){}
 o.querySelector('#txcCancel').onclick=closeModal;
 const rv=o.querySelector('#txcRev');if(rv)rv.onclick=()=>{closeModal();revokeDialog(it.corrId)};
 o.querySelector('#txcSave').onclick=()=>save(it,o);
 try{const h=await rpc('apiTxCorrHistory',TOKEN,it.key);const hb=o.querySelector('#txcHist');if(hb)hb.innerHTML=h.items.length?'<b>Riwayat koreksi</b>'+h.items.map(x=>`<div>${E(x.at.slice(0,16).replace('T',' '))} · ${whoLine(x.from)} → ${whoLine(x.to)} · ${E(x.reason)} · oleh ${E(x.actor)}${x.active?' <b>(aktif)</b>':` · dicabut${x.revokeReason?': '+E(x.revokeReason):''}`}</div>`).join(''):'<i>Belum ada koreksi untuk transaksi ini.</i>'}catch(e){const hb=o.querySelector('#txcHist');if(hb)hb.innerHTML=''}
}
async function save(it,o){
 const to=o.querySelector('#txcTo').value,why=o.querySelector('#txcWhyIn').value.trim(),err=o.querySelector('#txcErr');
 if(!to){err.innerHTML='<div class="txcMsg bad">Pilih Sales yang benar.</div>';return}
 if(why.length<5){err.innerHTML='<div class="txcMsg bad">Isi alasan minimal 5 karakter.</div>';return}
 const btn=o.querySelector('#txcSave');S.saving=true;btn.disabled=true;btn.textContent='Menyimpan & publish ulang…';err.innerHTML='<div class="txcMsg warn">Data dipublish ulang agar semua halaman ikut berubah. Ini bisa memakan beberapa menit; jangan tutup halaman.</div>';
 try{const r=await rpc('apiTxCorrSave',TOKEN,it.period,it.key,to,why);S.saving=false;closeModal();S.msg={t:'ok',m:`Koreksi tersimpan: ${r.from.nama||r.from.nik} → ${r.to.nama||r.to.nik}. Semua halaman sudah memakai Sales efektif yang baru.`};showMsg();S.tab==='SEARCH'?search():load();}
 catch(e){S.saving=false;btn.disabled=false;btn.textContent='Simpan koreksi';err.innerHTML=`<div class="txcMsg bad">${E(e.message||String(e))}</div>`}
}
function revokeDialog(id){
 const o=overlay(`<h3>Cabut koreksi</h3><div class="sub">Atribusi transaksi kembali ke hasil sebelum koreksi (Sales di file ST atau pemilik dealer-brand pada tanggal itu).</div><div class="txcForm"><label for="txcRevWhy">Alasan pencabutan (min. 5 karakter)</label><textarea id="txcRevWhy"></textarea><div id="txcErr"></div></div><div class="txcAct"><button type="button" class="txcBtn ghost" id="txcCancel">Batal</button><button type="button" class="txcBtn warn" id="txcRevGo">Cabut koreksi</button></div>`);
 o.querySelector('#txcCancel').onclick=closeModal;
 o.querySelector('#txcRevGo').onclick=async()=>{
  const why=o.querySelector('#txcRevWhy').value.trim(),err=o.querySelector('#txcErr'),btn=o.querySelector('#txcRevGo');
  if(why.length<5){err.innerHTML='<div class="txcMsg bad">Isi alasan minimal 5 karakter.</div>';return}
  S.saving=true;btn.disabled=true;btn.textContent='Memproses & publish ulang…';err.innerHTML='<div class="txcMsg warn">Data dipublish ulang. Jangan tutup halaman.</div>';
  try{await rpc('apiTxCorrRevoke',TOKEN,id,why);S.saving=false;closeModal();S.msg={t:'ok',m:'Koreksi dicabut. Semua halaman sudah memakai atribusi sebelum koreksi.'};S.boot=await rpc('apiTxCorrBoot',TOKEN);renderOrph();showMsg();S.tab==='SEARCH'?search():load();}
  catch(e){S.saving=false;btn.disabled=false;btn.textContent='Cabut koreksi';err.innerHTML=`<div class="txcMsg bad">${E(e.message||String(e))}</div>`}
 };
}
async function openPage(id){
 addCss();const r=document.getElementById(id||ID);if(!r)return;
 try{S.boot=await rpc('apiTxCorrBoot',TOKEN)}catch(e){r.innerHTML='<div class="banner bad">'+E(e.message||String(e))+'</div>';return}
 S.period=S.period||S.boot.period;S.tab='REVIEW';S.page=1;S.q='';S.region='';S.items=[];S.data=null;S.msg=null;S.searched=false;
 shell();load();
}
window.dsmTxCorrection={openPage};
})();
