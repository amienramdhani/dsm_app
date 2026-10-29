/* DSM Kredit tetap ke penjual (P1) — panel Admin khusus Super Admin.
   Penjual di daftar ini tidak dialihkan ke pemilik dealer; transaksinya tetap atas NIK penjual. Dipanggil dari index.php sebelum foldAdminFeatures. */
(function(){
'use strict';
const E=s=>(typeof esc==='function'?esc(s):String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));
const ROLE_LBL={'HEAD OF SALES':'HOS (Head of Sales)','BOARD OF DIRECTOR':'BOD (Board of Director)','CHIEF OPERATING OFFICER':'COO','CHIEF COMMERCIAL OFFICER':'CCO','RGM':'RGM','ASM':'ASM'};
const MODE_LBL={WILAYAH:'Ikut wilayah penjual',PEMILIK:'Ikut pemilik dealer',TERTENTU:'Region tertentu'};
const css=`.ksWrap{display:grid;gap:12px}.ksCard{border:1px solid #e0e7ef;border-radius:16px;background:#fff;padding:12px 14px;box-shadow:0 4px 14px rgba(20,40,80,.05)}
.ksCard h4{margin:0 0 6px;font-size:13.5px}.ksRow{display:flex;gap:10px;align-items:center;border:1px solid #e3eaf7;border-radius:14px;padding:9px 11px;margin-bottom:8px;background:linear-gradient(180deg,#fff,#f5f9ff)}
.ksRow .w{flex:1;min-width:0;font-weight:800;font-size:12.5px}.ksRow .w small{display:block;color:#6b7a99;font-weight:600;font-size:10.5px;margin-top:2px}
.ksTag{font-size:9px;font-weight:900;letter-spacing:.4px;padding:3px 8px;border-radius:999px;display:inline-block}.ksTag.on{background:#e5f7ee;color:#0b7a45}.ksTag.off{background:#eef1f6;color:#5a6785}.ksTag.mode{background:#fff0d6;color:#a35a00}
.ksGrid{display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))}.ksGrid label{display:grid;gap:4px;font-size:10px;font-weight:900;color:#6b7a99;letter-spacing:.4px}
.ksGrid .fi,.ksCard textarea{width:100%;font:inherit;font-size:12.5px;font-weight:700;padding:9px;border-radius:12px;border:1px solid #cfe0fb;background:#f8fbff}.ksCard textarea{min-height:56px}
.ksMsg{border-radius:12px;padding:8px 10px;font-size:12px;font-weight:700}.ksMsg.ok{background:#e5f7ee;color:#0b7a45}.ksMsg.bad{background:#fde8e8;color:#c62828}.ksLog{font-size:11px;padding:6px 0;border-bottom:1px solid #eef3fb;font-weight:600}.ksLog em{display:block;font-style:normal;color:#6b7a99;font-size:10px}`;
function addCss(){if(document.getElementById('ksCss'))return;const s=document.createElement('style');s.id='ksCss';s.textContent=css;document.head.appendChild(s)}
function covers(r,p){return r.periodFrom<=p&&(!r.periodTo||p<r.periodTo)}
function who(r){return r.kind==='ROLE'?(ROLE_LBL[r.subject]||r.subject)+' (semua orang di role ini)':(r.nama||r.subject)+' · '+r.subject+(r.role?' · '+r.role:'')}
function regionTxt(r){return MODE_LBL[r.regionMode]+(r.regionMode==='TERTENTU'?': '+r.regionValue:'')}
function mount(v){
 addCss();
 v.insertAdjacentHTML('beforeend',`<section class="panel"><div class="ph"><h3>Kredit tetap ke penjual</h3><span class="hint">Penjualan tetap atas nama penjual, tidak dialihkan ke pemilik dealer</span></div><div class="pb"><div id="ksApp">Memuat…</div></div></section>`);
 const root=v.querySelector('#ksApp');let B=null,msg=null,revoke=null;
 const reload=async()=>{try{B=await rpc('apiKeepSellerBoot',TOKEN);draw()}catch(e){root.innerHTML=`<div class="banner bad">${E(e.message||String(e))}</div>`}};
 function draw(){
  const cur=B.period;const rules=B.rules.filter(r=>r.active);const live=rules.filter(r=>covers(r,cur));
  const roleRows=B.roles.map(role=>{const r=live.find(x=>x.kind==='ROLE'&&x.subject===role);return `<div class="ksRow"><div class="w">${E(ROLE_LBL[role]||role)}<small>${r?E(regionTxt(r))+' · sejak '+E(r.periodFrom):'Ikut pemilik dealer'}</small></div><span class="ksTag ${r?'on':'off'}">${r?'KREDIT TETAP':'IKUT PEMILIK'}</span>${r?`<button type="button" class="chip" data-rv="${r.id}">Cabut</button>`:`<button type="button" class="chip" data-add-role="${E(role)}">Aktifkan</button>`}</div>`}).join('');
  const personRows=live.filter(r=>r.kind==='PERSON').map(r=>`<div class="ksRow"><div class="w">${E(who(r))}<small><span class="ksTag mode">${E(regionTxt(r))}</span> · sejak ${E(r.periodFrom)}<br>${E(r.reason)}</small></div><button type="button" class="chip" data-edit="${r.id}">Ubah</button><button type="button" class="chip" data-rv="${r.id}">Cabut</button></div>`).join('')||'<p class="hint">Belum ada pengecualian per orang.</p>';
  const log=B.rules.slice(0,15).map(r=>`<div class="ksLog">${E(who(r))}: kredit tetap, ${E(regionTxt(r))}, ${E(r.periodFrom)}${r.periodTo?' sampai sebelum '+E(r.periodTo):''}${r.active?'':' (diganti)'}<em>${E(r.actor)} · ${E(String(r.at).slice(0,16).replace('T',' '))} · ${E(r.reason)}${r.revokedBy&&r.revokeReason?' · dicabut: '+E(r.revokeReason):''}</em></div>`).join('');
  root.innerHTML=`<div class="ksWrap">${msg?`<div class="ksMsg ${msg.t}">${E(msg.m)}</div>`:''}
  <div class="ksCard"><h4>Otomatis per role</h4><p class="hint">Role yang aktif di sini tidak dialihkan ke pemilik dealer. HOS dan BOD aktif sejak awal.</p>${roleRows}</div>
  <div class="ksCard"><h4>Pengecualian per orang</h4>${personRows}</div>
  <div class="ksCard" id="ksForm"><h4 id="ksFormT">Tambah aturan</h4><div class="ksGrid">
   <label>JENIS<select class="fi" id="ksKind"><option value="PERSON">Per orang</option><option value="ROLE">Per role</option></select></label>
   <label id="ksSubW">ORANG<input class="fi" id="ksSub" autocomplete="off" placeholder="Pilih orang…"></label>
   <label>BERLAKU MULAI (BULAN)<input class="fi" id="ksFrom" type="month" value="${E(cur)}"></label>
   <label>REGION KARTU<select class="fi" id="ksMode">${Object.keys(MODE_LBL).map(m=>`<option value="${m}">${MODE_LBL[m]}</option>`).join('')}</select></label>
   <label id="ksRegW" hidden>REGION<select class="fi" id="ksReg">${B.regions.map(x=>`<option>${E(x)}</option>`).join('')}</select></label></div>
   <label style="display:grid;gap:4px;font-size:10px;font-weight:900;color:#6b7a99;margin-top:8px">ALASAN (WAJIB)<textarea id="ksWhy" placeholder="Contoh: pegang 3 region, KPI tetap atas nama dia"></textarea></label>
   <p class="hint">Berlaku untuk semua penjualan orang/role ini, bukan sebagian. Kredit tetap atas nama penjual; pemilik dealer tidak mendapat kredit KPI dari unit itu. Menyimpan akan menerbitkan ulang data.</p>
   <button type="button" class="chip primary" id="ksSave">Simpan</button></div>
  ${revoke?`<div class="ksCard"><h4>Cabut aturan #${revoke}</h4><div class="ksGrid"><label>BERAKHIR SEBELUM BULAN<input class="fi" id="ksTo" type="month" value="${E(cur)}"></label><label>ALASAN PENCABUTAN<input class="fi" id="ksRvWhy"></label></div><p class="hint">Bulan sebelum bulan ini tidak berubah.</p><button type="button" class="chip primary" id="ksRvGo">Cabut</button> <button type="button" class="chip" id="ksRvNo">Batal</button></div>`:''}
  <div class="ksCard"><h4>Riwayat perubahan</h4>${log||'<p class="hint">Belum ada.</p>'}</div></div>`;
  const $=id=>root.querySelector('#'+id);
  const mkPerson=inp=>{try{if(window.dsmPersonInput)window.dsmPersonInput(inp,B.candidates.map(c=>({value:c.nik,nik:c.nik,nama:c.nama,role:c.role,region:c.region||''})),{format:p=>p.nama+' · '+p.nik+' · '+p.role,title:'Pilih orang',ph:'Pilih orang…'})}catch(e){}};mkPerson($('ksSub'));
  const sync=()=>{const role=$('ksKind').value==='ROLE';$('ksSubW').firstChild.textContent=role?'ROLE':'ORANG';const w=$('ksSubW');const old=w.querySelector('#ksSub');if(role&&old.tagName==='INPUT'){const sel=document.createElement('select');sel.className='fi';sel.id='ksSub';sel.innerHTML=B.roles.map(r=>`<option value="${E(r)}">${E(ROLE_LBL[r]||r)}</option>`).join('');(old.closest('.ppWrap')||old).replaceWith(sel)}else if(!role&&old.tagName==='SELECT'){const inp=document.createElement('input');inp.className='fi';inp.id='ksSub';inp.placeholder='Pilih orang…';old.replaceWith(inp);mkPerson(inp)}$('ksRegW').hidden=$('ksMode').value!=='TERTENTU'};
  $('ksKind').onchange=sync;$('ksMode').onchange=sync;
  const prefill=(kind,subject,mode,regionValue)=>{$('ksKind').value=kind;sync();const s=$('ksSub');if(kind==='ROLE')s.value=subject;else{const c=B.candidates.find(x=>x.nik===subject);s.value=c?c.nama+' · '+c.nik+' · '+c.role:subject;if(s._ppSync)s._ppSync()}$('ksMode').value=mode||'WILAYAH';sync();if(regionValue)$('ksReg').value=regionValue;$('ksFormT').textContent='Tambah / ubah aturan';$('ksForm').scrollIntoView({behavior:'smooth',block:'center'})};
  root.querySelectorAll('[data-add-role]').forEach(b=>b.onclick=()=>prefill('ROLE',b.dataset.addRole,'WILAYAH'));
  root.querySelectorAll('[data-edit]').forEach(b=>b.onclick=()=>{const r=B.rules.find(x=>x.id===+b.dataset.edit);if(r)prefill(r.kind,r.subject,r.regionMode,r.regionValue)});
  root.querySelectorAll('[data-rv]').forEach(b=>b.onclick=()=>{revoke=+b.dataset.rv;msg=null;draw();root.querySelector('#ksRvWhy')?.focus()});
  if($('ksRvNo'))$('ksRvNo').onclick=()=>{revoke=null;draw()};
  if($('ksRvGo'))$('ksRvGo').onclick=async()=>{const btn=$('ksRvGo');btn.disabled=true;try{await rpc('apiKeepSellerRevoke',TOKEN,revoke,$('ksRvWhy').value.trim(),$('ksTo').value);msg={t:'ok',m:'Aturan dicabut dan data sudah diterbitkan ulang.'};revoke=null;await reload()}catch(e){msg={t:'bad',m:e.message||String(e)};draw()}};
  $('ksSave').onclick=async()=>{
   const kind=$('ksKind').value;let subject=$('ksSub').value.trim();
   if(kind==='PERSON'){const m=subject.split(' · ');const byNik=B.candidates.find(c=>c.nik===subject)||B.candidates.find(c=>c.nik===(m[1]||'').trim());if(!byNik){msg={t:'bad',m:'Pilih orang dari saran (nama · NIK).'};draw();return}subject=byNik.nik}
   const btn=$('ksSave');btn.disabled=true;
   try{await rpc('apiKeepSellerSave',TOKEN,{kind,subject,periodFrom:$('ksFrom').value,regionMode:$('ksMode').value,regionValue:$('ksReg').value,reason:$('ksWhy').value.trim()});msg={t:'ok',m:'Aturan tersimpan dan data sudah diterbitkan ulang.'};await reload()}
   catch(e){msg={t:'bad',m:e.message||String(e)};draw()}};
  sync();
 }
 reload();
}
window.dsmKeepSellerPanel=mount;
})();

/* Chip "termasuk X unit dijual sales luar area" pada kartu Big Region (render di index.php v6XHierCard). Rincian muncul di popup. */
(function(){
 const E=s=>(typeof esc==='function'?esc(s):String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));
 const N=n=>Number(n||0).toLocaleString('id-ID');
 if(!document.getElementById('dsmExtCss')){const s=document.createElement('style');s.id='dsmExtCss';s.textContent=`.dsmExtChip{display:inline-flex;align-items:center;gap:4px;margin-top:6px;background:linear-gradient(135deg,#fff3dc,#ffe2b0);border:1px solid #f1c36a;color:#a35a00;font-size:10px;font-weight:800;padding:4px 9px;border-radius:999px;cursor:pointer;box-shadow:0 3px 8px rgba(245,158,11,.25)}.dsmExtRow{border:1px solid #e3eaf7;border-radius:14px;background:#fff;margin-bottom:8px;overflow:hidden}.dsmExtRow summary{display:flex;gap:10px;align-items:center;padding:10px 12px;cursor:pointer;list-style:none}.dsmExtRow summary b{flex:1;font-size:12.5px}.dsmExtRow summary small{display:block;color:#6b7a99;font-weight:700;font-size:10px;margin-top:2px}.dsmExtRow .dl{display:flex;justify-content:space-between;padding:6px 12px;border-top:1px solid #f0f4fb;font-size:11.5px;font-weight:600}.dsmExtRow .dl small{color:#6b7a99}`;document.head.appendChild(s)}
 function open(key){
  const X=(typeof V6X!=='undefined')?V6X:null,d=X&&X.data,n=d&&d.nodes&&d.nodes[key];if(!n||!n.ext)return;const B=X.brand,list=n.ext[B]||[];if(!list.length)return;const tot=list.reduce((a,x)=>a+(x.q||0),0);
  const body=`<p class="hint">${E(n.name)} · ${E(B==='ALL'?'Semua brand':B)} · SELL THRU · total ${N(tot)} unit, sudah termasuk di Aktual kartu.</p>`+list.map(x=>`<details class="dsmExtRow"><summary><span style="width:10px;height:10px;border-radius:50%;background:#f59e0b;flex:none"></span><b>${E(x.nama)}<small>${E(x.role)}${x.area?' · area asal '+E(x.area):''}</small></b><span><b>${N(x.q)}</b><small>unit</small></span></summary>${(x.dealers||[]).map(y=>`<div class="dl"><span>${E(y.n||y.c)}<br><small>${E(y.c)}</small></span><b>${N(y.q)}</b></div>`).join('')}</details>`).join('')+`<p class="hint">Kredit KPI ke pemilik dealer, kecuali penjual masuk daftar Kredit tetap ke penjual. Penjual melihatnya sebagai info di akunnya.</p>`;
  if(typeof modal==='function')modal('Dijual sales luar area',body);
 }
 document.addEventListener('click',e=>{const c=e.target.closest&&e.target.closest('[data-dsm-ext]');if(!c)return;e.stopPropagation();e.preventDefault();open(c.dataset.dsmExt)},true);
 document.addEventListener('keydown',e=>{if((e.key==='Enter'||e.key===' ')&&e.target.matches&&e.target.matches('[data-dsm-ext]')){e.preventDefault();open(e.target.dataset.dsmExt)}});
})();
