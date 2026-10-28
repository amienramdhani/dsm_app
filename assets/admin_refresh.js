/* DSM Admin refresh — approved grouping, focus mode, and polished Edit User. */
(function(){
'use strict';
const E=s=>(typeof esc==='function'?esc(s):String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));
const css=`
/* Admin focus mode: warna fokus mengikuti warna grup; tab kategori tetap memakai style existing. */
#view .admList details.adminFeature{transition:opacity .16s ease,filter .16s ease,transform .16s ease}
#view .admList details.adminFeature.admFocusDim{opacity:.34;filter:saturate(.55)}
#view .admList details.adminFeature.admFocusDim:hover{opacity:.72;filter:saturate(.8)}
#view .admList details.adminFeature[open]{opacity:1!important;filter:none!important}
#view .admList details.adminFeature[open]>summary{background:color-mix(in srgb,var(--c1,#2B7BE0) 14%,#fff)!important;border:1px solid color-mix(in srgb,var(--c1,#2B7BE0) 32%,#e6ebf2)!important;box-shadow:0 8px 20px color-mix(in srgb,var(--c1,#2B7BE0) 11%,transparent)!important}
#view .admList details.adminFeature[open]>summary>span{color:color-mix(in srgb,var(--c1,#2B7BE0) 78%,#172033)!important}
#view .admList details.adminFeature[open]>.adminFeatureContent{background:#fff!important;border:1px solid color-mix(in srgb,var(--c1,#2B7BE0) 18%,#e8edf3)!important;border-top:0!important;box-shadow:0 12px 28px rgba(20,40,80,.06)!important}
#view .admTabs{align-self:start}
/* Edit User */
.ueShell{display:flex;flex-direction:column;gap:10px}.ueCard{border:1px solid #e4e9f0;border-radius:14px;background:#fff;padding:12px}.ueHead{display:flex;align-items:center;gap:9px;margin-bottom:10px}.ueIcon{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;background:#f1ecff}.ueHead b{font-size:12.5px}.ueHead small{display:block;color:#78849a;font-size:10px;margin-top:2px}.ueGrid{display:grid;grid-template-columns:1fr 1fr;gap:9px 10px}.ueGrid label,.ueField{font-size:10.5px;color:#354156}.ueGrid .fi,.ueField .fi{width:100%;margin-top:4px}.ueAct{border:1px solid #eadffb;background:#faf7ff;border-radius:12px;padding:10px 11px}.ueAct label.confirm{margin:0}.ueAct small{display:block;color:#7d718f;font-size:10px;margin:4px 0 0 24px}.uePortfolio{margin-top:9px}.ueAccess{border:1px solid #e4e9f0;border-radius:12px;padding:10px}.ueAccess>p{font-weight:800;font-size:10.5px;margin:0 0 7px}.ueFoot{position:sticky;bottom:0;background:rgba(255,255,255,.96);backdrop-filter:blur(9px);border-top:1px solid #e8edf3;margin:2px -2px -2px;padding:10px 2px 2px;display:flex;gap:7px;justify-content:flex-end;align-items:center}.ueFoot .ueTransfer{margin-right:auto}.ueHint{font-size:10px;color:#78849a;line-height:1.45;margin:0}.ueErr{color:#b4233d;font-size:10.5px;margin:0}.ueHidden{display:none!important}
@media(max-width:640px){.ueGrid{grid-template-columns:1fr}.ueFoot{flex-wrap:wrap}.ueFoot .ueTransfer{width:100%;margin-right:0}.ueFoot .chip{flex:1}}
`;
const st=document.createElement('style');st.textContent=css;document.head.appendChild(st);

const GROUPS=[
 ['u','Pengguna & Akses','#2B7BE0',/akun pengguna|tambah user|hak export|keamanan akun|perangkat/i],
 ['d','Dealer','#0E9F6E',/cari & edit dealer|tambah dealer|upload dealer|bulk edit customer|update kontak|approval data dealer|pic dealer|big region per dealer|dealer tutup|transfer dealer|ganti kode induk/i],
 ['h','Upload Harian','#F07A0C',/upload regional|peta kelengkapan|kelengkapan data|status sync/i],
 ['m','Master Data','#6D3FD8',/master product|brand & klasifikasi|region map|hari libur|download data master/i],
 ['k','KPI & Target','#5C59D9',/kpi khusus|edit target|atur kpi|region per brand.*target big|target big region/i],
 ['p','Kebijakan & Aturan','#9B6BF2',/master & kebijakan|bulk master.*kebijakan|brand · ambang|ambang status dealer/i],
 ['t','Telemarketing','#D94686',/telemarketing/i],
 ['o','Form Order','#0F9F86',/badan usaha.*form order|form order/i],
 ['s','Sistem','#6F7B8E',/.*/]
];
function groupOf(name){for(const g of GROUPS.slice(0,-1))if(g[3].test(name))return g;return GROUPS[GROUPS.length-1]}
function iconOf(name){return typeof admIcon==='function'?admIcon(name):'⚙️'}

window.dsmFoldAdminFeatures=function(v){
 const panels=[...v.children].filter(el=>el.classList.contains('panel')),items=[];
 panels.forEach((panel,i)=>{
   const head=panel.querySelector(':scope > .ph'),name=head?.querySelector('h3')?.textContent||('Fitur Admin '+(i+1)),hint=head?.querySelector('.hint')?.textContent||'',g=groupOf(name);
   const d=document.createElement('details');d.className=panel.className+' adminFeature';d.dataset.feature=name;d.dataset.tab=g[0];d.style.setProperty('--c1',g[2]);
   const summary=document.createElement('summary');summary.innerHTML='<i class="admIc">'+iconOf(name)+'</i><span>'+E(name)+(hint?'<small>'+E(hint)+'</small>':'')+'</span>';d.append(summary);
   if(head)head.hidden=true;panel.replaceWith(d);d.append(panel);panel.classList.add('adminFeatureContent');d.dataset.hay=(name+' '+d.textContent).toLowerCase().replace(/\s+/g,' ');items.push(d);
   if(/kunci periode|pemulihan regional|ganti kode induk/i.test(name)){d.classList.add('admWarn');const sp=d.querySelector(':scope > summary > span');if(sp){const w=document.createElement('span');w.className='admWl';w.textContent='HATI-HATI';sp.firstChild.after(w)}}
 });
 const finder=document.createElement('div');finder.className='adminFinder';finder.innerHTML='<input id="adminFeatureSearch" class="fi" type="search" placeholder="Cari panel atau isinya…" aria-label="Cari panel Admin"><p class="hint" id="adminFeatureCount"></p>';
 const wrap=document.createElement('div');wrap.className='admWrap';const nav=document.createElement('div');nav.className='admTabs';const list=document.createElement('div');list.className='admList';items.forEach(d=>list.append(d));wrap.append(finder,nav,list);v.prepend(wrap);
 let cur=(GROUPS.find(g=>items.some(d=>d.dataset.tab===g[0]))||GROUPS[0])[0],q='';
 const hit=d=>!q||d.dataset.hay.includes(q),cnt=k=>items.filter(d=>d.dataset.tab===k&&hit(d)).length,tot=k=>items.filter(d=>d.dataset.tab===k).length;
 const clearFocus=()=>items.forEach(x=>x.classList.remove('admFocusDim','admFocused'));
 function focus(d){items.forEach(x=>{if(!x.hidden&&x!==d)x.open=false});items.forEach(x=>x.classList.toggle('admFocusDim',!x.hidden&&x!==d));d.classList.add('admFocused')}
 items.forEach(d=>d.addEventListener('toggle',()=>{if(d.open)focus(d);else if(!items.some(x=>x.open&&!x.hidden))clearFocus()}));
 function paint(){
   const withHit=GROUPS.filter(g=>cnt(g[0])>0);if(q&&!cnt(cur)&&withHit.length)cur=withHit[0][0];
   nav.innerHTML=GROUPS.filter(g=>tot(g[0])>0).map(g=>'<button type="button" data-k="'+g[0]+'" class="'+(g[0]===cur?'on':'')+(q?(cnt(g[0])?' hit':' dim'):'')+'" style="--c:'+g[2]+'"><span>'+E(g[1])+'</span><em>'+(q?cnt(g[0]):tot(g[0]))+'</em></button>').join('');
   items.forEach(d=>{d.open=false;d.hidden=!(d.dataset.tab===cur&&hit(d));});clearFocus();const total=items.filter(hit).length;const c=document.getElementById('adminFeatureCount');if(c)c.textContent=q?(total+' panel cocok dari '+items.length):(items.length+' panel · ketuk untuk membuka');
 }
 nav.onclick=e=>{const b=e.target.closest('[data-k]');if(!b)return;cur=b.dataset.k;paint()};
 const search=document.getElementById('adminFeatureSearch');if(search)search.oninput=e=>{q=e.target.value.trim().toLowerCase();paint()};paint();
};

window.dsmUserEditor=function(a,user){
 const u=user||{nik:'',nama:'',role:'SALES',region:'',atasan:'',adminRegions:[],portfolio:'',sales:false};
 const roles=a.superuser?['SALES','TELEMARKETING','ASM','RGM','ADMIN','HEAD OF SALES','CHIEF OPERATING OFFICER','CHIEF COMMERCIAL OFFICER','BOARD OF DIRECTOR','SUPER ADMIN']:(user?.role==='TELEMARKETING'?['TELEMARKETING']:['SALES','ASM','RGM']);
 const regions=a.regionMap.filter(r=>a.superuser||a.managedRegions.includes(r.WILAYAH_KPI)).map(r=>r.REGION);
 const opt=(values,current)=>values.map(x=>`<option value="${E(x)}" ${x===current?'selected':''}>${E(x)}</option>`).join('');
 const wasActing=!!(user&&u.sales&&u.role!=='SALES');
 const actingNow=wasActing;
 const portfolioValue=actingNow?(u.portfolio||u.region||''):'';
 const canPortfolio=user&&!!u.sales;
 modal(user?'Edit user':'Tambah Sales / User',`<form id="userEditor" class="ueShell">
   <div class="ueCard"><div class="ueHead"><div class="ueIcon">👤</div><div><b>Identitas & struktur</b><small>Data akun, jabatan, region, dan atasan utama.</small></div></div><div class="ueGrid">
     <label>NIK<input class="fi" name="nik" value="${E(u.nik)}" ${user?'readonly':''} required></label><label>Nama<input class="fi" name="nama" value="${E(u.nama)}" required maxlength="120"></label>
     <label>Jabatan<select class="fi" name="role">${opt(roles,u.role)}</select></label><label>Region operasional<select class="fi" name="region"><option value="">Nasional / Admin</option>${opt(regions,u.region)}</select></label>
     <label style="grid-column:1/-1">Atasan<select class="fi" name="atasan"><option value="">Tidak ada / belum ditugaskan</option>${a.bosses.filter(b=>b.nik!==u.nik).map(b=>`<option value="${E(b.nik)}" ${b.nik===u.atasan?'selected':''}>${E(b.nama)} · ${E(b.role)}</option>`).join('')}</select></label>
   </div></div>
   <div class="ueAct"><label class="confirm"><input name="acting" type="checkbox" ${actingNow?'checked':''}> ASM / RGM juga menangani portofolio Sales</label><small>Aktifkan hanya jika ASM/RGM memang memegang dealer dan target sebagai Sales.</small><label class="ueField uePortfolio ${actingNow?'':'ueHidden'}" id="uePortfolio">Region portofolio rangkap<select class="fi" name="portfolio"><option value="">Pilih region portofolio</option>${opt(regions,portfolioValue)}</select></label></div>
   ${a.superuser?'<div class="ueAccess"><p>Wilayah tugas · khusus role ADMIN</p><div id="userAdminRegions" class="regionChecks">'+a.regions.map(r=>`<label><input type="checkbox" value="${E(r)}" ${(u.adminRegions||[]).includes(r)?'checked':''}> ${E(r)}</label>`).join('')+'</div></div>':''}
   <p class="ueHint">NIK yang sama tetap satu akun dan login sebagai jabatan tertinggi. Jika portofolio Sales dihentikan, gunakan alur Alihkan Portofolio agar dealer, target, dan atribusi pencapaian tidak tertinggal.</p><p id="userSaveError" class="ueErr" role="alert"></p>
   <div class="ueFoot">${canPortfolio?'<button class="chip ueTransfer" type="button" id="ueTransfer">↪ Alihkan Portofolio</button>':''}<button type="button" class="chip" id="ueCancel">Batal</button><button class="chip primary" type="submit">Simpan user</button></div>
 </form>`);
 const f=document.getElementById('userEditor'),role=f.elements.role,boss=f.elements.atasan,acting=f.elements.acting,portfolio=f.elements.portfolio,pbox=document.getElementById('uePortfolio');
 function syncRole(){
   const tele=role.value==='TELEMARKETING',canAct=['ASM','RGM'].includes(role.value);[...boss.options].forEach(o=>{const b=a.bosses.find(x=>x.nik===o.value);o.disabled=tele&&(!b||b.role!=='RGM')});boss.required=tele;acting.disabled=tele||!canAct;if(!canAct||tele)acting.checked=false;syncActing();
 }
 function syncActing(){const on=acting.checked&&!acting.disabled;pbox.classList.toggle('ueHidden',!on);portfolio.disabled=!on;portfolio.required=on;if(on&&!portfolio.value)portfolio.value=f.elements.region.value||'';if(!on)portfolio.value=''}
 role.addEventListener('change',syncRole);acting.addEventListener('change',syncActing);f.elements.region.addEventListener('change',()=>{if(acting.checked&&!portfolio.value)portfolio.value=f.elements.region.value});syncRole();
 [['atasan','Ketik nama atau jabatan…'],['region','Ketik nama region…'],['portfolio','Ketik nama region…']].forEach(([n,ph])=>{if(typeof dsmSearchable==='function')dsmSearchable(f.elements[n],ph)});
 document.getElementById('ueCancel').onclick=()=>closeModal();
 const openTransfer=(reason,onDone)=>{if(!window.dsmPortfolioTransfer)return (document.getElementById('userSaveError').textContent='Modul Alihkan Portofolio belum dimuat.');window.dsmPortfolioTransfer.open({sourceNik:u.nik,reason,onDone})};
 if(document.getElementById('ueTransfer'))document.getElementById('ueTransfer').onclick=()=>openTransfer(wasActing?'Berhenti rangkap Sales':'Resign',async()=>{closeModal();if(typeof start==='function')await start()});
 f.onsubmit=async e=>{
   e.preventDefault();const btn=f.querySelector('button[type="submit"]'),input=Object.fromEntries(new FormData(f));input.original=user?.nik||'';input.acting=acting.checked;input.portfolio=input.acting?(portfolio.value||''):'';input.adminRegions=[...(document.getElementById('userAdminRegions')?.querySelectorAll(':checked')||[])].map(x=>x.value);
   const stopActing=wasActing&&!input.acting;
   if(stopActing){openTransfer('Berhenti rangkap Sales',async out=>{if(!out?.effective?.future){await rpc('apiSaveUser',TOKEN,input)}closeModal();if(typeof start==='function')await start()});return}
   btn.disabled=true;try{const r=await rpc('apiSaveUser',TOKEN,input);closeModal();if(typeof start==='function')await start();if(r.pin)modal('User ditambahkan',`<p>NIK ${E(r.nik)}</p><div class="temporaryPin">${E(r.pin)}</div><p>PIN sementara; wajib diganti saat login pertama.</p>`)}catch(err){const x=document.getElementById('userSaveError');if(x)x.textContent=err.message||String(err);else if(typeof fail==='function')fail(err)}finally{btn.disabled=false}
 };
};
})();
