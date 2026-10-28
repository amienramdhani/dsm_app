/* DSM Riwayat pemilik dealer (P4) — panel Admin, baca-saja.
   Periode terkunci dibaca dari foto yang disimpan saat kunci periode; periode belum dikunci dari data terkini. */
(function(){
'use strict';
const E=s=>(typeof esc==='function'?esc(s):String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));
const css=`.ohWrap{display:grid;gap:10px}.ohTools{display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(170px,1fr))}.ohTools label{display:grid;gap:4px;font-size:10px;font-weight:900;color:#6b7a99;letter-spacing:.4px}
.ohTools .fi{width:100%;font:inherit;font-size:12.5px;font-weight:700;padding:9px;border-radius:12px;border:1px solid #cfe0fb;background:#f8fbff}
.ohSrc{display:inline-block;font-size:9px;font-weight:900;letter-spacing:.4px;padding:4px 9px;border-radius:999px}.ohSrc.lock{background:#e5f7ee;color:#0b7a45}.ohSrc.live{background:#fff0d6;color:#a35a00}
.ohTbl{overflow-x:auto}.ohTbl table{min-width:640px;width:100%;border-collapse:separate;border-spacing:0;font-size:11.5px;border:1px solid #e3eaf7;border-radius:14px;overflow:hidden;background:#fff}
.ohTbl th{background:linear-gradient(135deg,#1556b8,#2f80ed);color:#fff;text-align:left;padding:8px 10px;font-size:10px;letter-spacing:.5px;text-transform:uppercase}.ohTbl td{padding:8px 10px;border-top:1px solid #eef3fb;font-weight:700;vertical-align:top}.ohTbl td small{display:block;color:#6b7a99;font-weight:600;font-size:10px;margin-top:1px}
.ohMsg{border-radius:12px;padding:8px 10px;font-size:12px;font-weight:700;background:#eef5ff;color:#1d4a91}.ohMsg.bad{background:#fde8e8;color:#c62828}.ohPager{display:flex;justify-content:space-between;align-items:center;font-size:11px;font-weight:700;color:#6b7a99}`;
function mount(v){
 if(!document.getElementById('ohCss')){const s=document.createElement('style');s.id='ohCss';s.textContent=css;document.head.appendChild(s)}
 v.insertAdjacentHTML('beforeend',`<section class="panel"><div class="ph"><h3>Riwayat pemilik dealer</h3><span class="hint">Siapa pemilik dealer-brand pada periode tertentu · baca-saja</span></div><div class="pb"><div id="ohApp">Memuat…</div></div></section>`);
 const root=v.querySelector('#ohApp');let B=null,S={period:'',q:'',brand:'',page:1},D=null,err='',busy=false;
 async function boot(){try{B=await rpc('apiOwnerHistoryBoot',TOKEN);S.period=B.period;shell()}catch(e){root.innerHTML=`<div class="banner bad">${E(e.message||String(e))}</div>`}}
 function shell(){
  root.innerHTML=`<div class="ohWrap"><div class="ohTools">
   <label>DEALER (KODE ATAU NAMA)<input class="fi" id="ohQ" placeholder="Ketik minimal 2 huruf…" value="${E(S.q)}" autocomplete="off"></label>
   <label>PERIODE<select class="fi" id="ohP">${B.periods.map(p=>`<option value="${E(p.period)}"${p.period===S.period?' selected':''}>${E(p.period)}${p.locked?' · dikunci':' · berjalan'}</option>`).join('')}</select></label>
   <label>BRAND<select class="fi" id="ohB"><option value="">Semua brand</option>${B.brands.map(b=>`<option${b===S.brand?' selected':''}>${E(b)}</option>`).join('')}</select></label></div>
   <div><button type="button" class="chip" id="ohX">Unduh seluruh periode (CSV)</button></div><div id="ohOut"></div></div>`;
  const q=root.querySelector('#ohQ');let t=null;q.oninput=()=>{S.q=q.value;clearTimeout(t);t=setTimeout(()=>{S.page=1;load()},400)};
  root.querySelector('#ohP').onchange=e=>{S.period=e.target.value;S.page=1;load()};root.querySelector('#ohB').onchange=e=>{S.brand=e.target.value;S.page=1;load()};
  root.querySelector('#ohX').onclick=async e=>{const b=e.target;b.disabled=true;try{downloadBase64(await rpc('apiOwnerHistoryExport',TOKEN,S.period))}catch(x){err=x.message||String(x);draw()}finally{b.disabled=false}};
  draw();
 }
 async function load(){
  if(S.q.trim().length<2){D=null;err='';draw();return}
  busy=true;err='';draw();
  try{D=await rpc('apiOwnerHistory',TOKEN,{period:S.period,q:S.q.trim(),brand:S.brand,page:S.page})}catch(e){D=null;err=e.message||String(e)}
  busy=false;draw();
 }
 function draw(){
  const o=root.querySelector('#ohOut');if(!o)return;
  if(err){o.innerHTML=`<div class="ohMsg bad">${E(err)}</div>`;return}
  if(busy){o.innerHTML='<div class="ohMsg">Memuat…</div>';return}
  if(!D){o.innerHTML='<div class="ohMsg">Ketik kode atau nama dealer untuk melihat siapa pemiliknya pada periode terpilih.</div>';return}
  const src=D.locked?`<span class="ohSrc lock">FOTO PERIODE DIKUNCI · ${E(String(D.takenAt).slice(0,10))}</span>`:`<span class="ohSrc live">DATA TERKINI · PERIODE BELUM DIKUNCI</span>`;
  const rows=D.rows.map(x=>`<tr><td>${E(x.dealerNama||x.dealer)}<small>${E(x.dealer)}${x.kota?' · '+E(x.kota):''}</small></td><td>${E(x.brand)}</td><td>${E(x.nama||x.nik)}<small>${E(x.nik)}${x.atasan?' · atasan '+E(x.atasan):''}</small></td><td>${E(x.role)}</td><td>${E(x.big)}<small>${E(x.region)}</small></td></tr>`).join('');
  o.innerHTML=`<div style="margin:6px 0">${src} <span class="hint">posisi per ${E(D.asof)}</span></div>`+(D.rows.length?`<div class="ohTbl"><table><thead><tr><th>Dealer</th><th>Brand</th><th>Pemilik</th><th>Jabatan</th><th>Big Region / Region</th></tr></thead><tbody>${rows}</tbody></table></div>`:'<div class="ohMsg">Tidak ada data pemilik untuk dealer ini pada periode tersebut.</div>')+
   `<div class="ohPager"><span>${D.total} dealer cocok · halaman ${D.page} dari ${D.pages}</span><span><button type="button" class="chip" id="ohPv"${D.page<=1?' disabled':''}>‹</button> <button type="button" class="chip" id="ohNx"${D.page>=D.pages?' disabled':''}>›</button></span></div>`;
  const pv=o.querySelector('#ohPv'),nx=o.querySelector('#ohNx');if(pv)pv.onclick=()=>{S.page--;load()};if(nx)nx.onclick=()=>{S.page++;load()};
 }
 boot();
}
window.dsmOwnerHistoryPanel=mount;
})();
