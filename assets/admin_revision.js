/* DSM approved Admin focus / menu structure / Edit User polish / KPI compact */
(function(){
'use strict';
/* Paket 3 (2026-11-17): 8 tab per OBJEK + seksi + kata kunci tersembunyi + Beranda Admin (Bento B2, warna solid).
   Isi panel TIDAK diubah; hanya dipindah/dikelompokkan. Penggabungan panel (1b) menyusul. */
const T=(re,kw,o)=>Object.assign({re,kw},o||{});
const GROUPS=[
 ['ap','Approval','#E5354E','✅','Persetujuan menunggu',[['',[T(/^approval/i,'setuju persetujuan approve')]]]],
 ['h','Data Harian','#F07A0C','📊','Upload & kelengkapan data',[
  ['SERING DIPAKAI',[T(/upload regional/i,'sell thru sell out harian st so upload'),T(/^publikasi data/i,'publikasi antrean publish tahan cron perbarui'),T(/status sync/i,'sinkron sync'),T(/kelengkapan data per region/i,'lengkap region belum upload'),T(/peta kelengkapan/i,'kalender lengkap tanggal')]],
  ['PENGECEKAN',[T(/catatan kualitas/i,'kualitas error janggal'),T(/kunci periode/i,'lock bulan final tutup periode')]],
  ['HATI-HATI',[T(/pemulihan regional/i,'restore rollback kembalikan',{w:1})]]]],
 ['d','Dealer','#0E9F6E','🏪','Data & kepemilikan dealer',[
  ['SERING DIPAKAI',[T(/cari & edit dealer/i,'customer toko kontak sales edit'),T(/^tambah dealer/i,'customer baru noo'),T(/^upload massal dealer/i,'customer massal tambah edit upload bulk dealer field kontak telepon alamat pic hp'),T(/upload dealer/i,'customer massal tambah edit upload'),T(/bulk edit customer/i,'dealer massal field'),T(/update kontak/i,'kontak telepon alamat pic massal hp')]],
  ['KEPEMILIKAN',[T(/transfer dealer/i,'pindah sales brand kepemilikan'),T(/riwayat transfer/i,'history log transfer'),T(/riwayat pemilik/i,'history sales pemilik'),T(/big region per dealer/i,'wilayah region'),T(/dealer tutup/i,'nonaktif tutup')]],
  ['HATI-HATI',[T(/ganti kode induk/i,'hapus delete kode induk',{w:1})]]]],
 ['b','Brand','#B98900','🏷️','Semua setelan brand',[
  ['SEMUA SETELAN BRAND',[T(/brand & klasifikasi/i,'brand aktif kelas klasifikasi'),T(/aturan status dealer/i,'ambang status dealer pola brand'),T(/brand · ambang/i,'ambang status dealer brand'),T(/pic dealer/i,'pic sales brand'),T(/product rules|aturan produk/i,'aturan produk brand type')]],
  ['TERKAIT',[T(null,'',{link:'region per brand',label:'Region per brand & target Big Region',sub:'Ada di tab KPI & Target'})]]]],
 ['kt','KPI & Target','#7C4DCE','🎯','Target & indikator',[
  ['TARGET',[T(/edit target/i,'target bulan sales'),T(/region per brand/i,'brand target region big region wilayah')]],
  ['KPI',[T(/atur kpi/i,'bobot indikator brand kpi'),T(/kpi khusus/i,'type indikator khusus'),T(/kpi telemarketing/i,'telemarketing tm target')]]]],
 ['md','Master Data','#6D3FD8','🗂️','Produk, wilayah, publikasi',[
  ['SERING DIPAKAI',[T(/^download & upload master/i,'massal upload download bulk master dealer customer product sales target kpi region'),T(/bulk master/i,'massal upload download master'),T(/download data master/i,'download working file master'),T(/master product/i,'type barang produk brand'),T(/cek kesehatan/i,'health error master'),T(/^master & kebijakan/i,'publish kebijakan terbitkan publikasi',{name:'Publikasi Master'})]],
  ['WILAYAH & KALENDER',[T(/region map/i,'wilayah peta region'),T(/hari libur/i,'kalender libur cuti')]],
  ['FORM ORDER & TELEMARKETING',[T(/badan usaha/i,'order pt cv form order'),T(/penugasan telemarketing/i,'telemarketing follow up telepon')]]]],
 ['u','Pengguna & Akses','#2B7BE0','👤','Akun, PIN, izin',[['',[T(/akun pengguna/i,'user login pin role'),T(/sub region/i,'asm rgm atasan wilayah vacant jadwal'),T(/tambah user/i,'user massal'),T(/keamanan akun/i,'security pin 2fa sesi'),T(/hak export/i,'download izin export'),T(/kredit tetap ke penjual/i,'kredit sales penjual'),T(/sambungan telegram|telegram/i,'bot notif telegram')]]]],
 ['s','Sistem','#6F7B8E','⚙️','Backup & log',[['',[T(/autobackup|backup/i,'cadangan backup'),T(/riwayat aktivitas/i,'audit log aktivitas'),T(/notifikasi khusus/i,'push pesan notifikasi')]],['LAINNYA',[T(/.*/,'',{rest:1})]]]],
];
const HOME=['home','Beranda Admin','#0B5CAD','🏠','Ringkasan & sematan'];
const hexRgb=h=>{const x=String(h).replace('#','');const v=parseInt(x.length===3?x.split('').map(c=>c+c).join(''):x,16);return[(v>>16)&255,(v>>8)&255,v&255]};
const rgba=(h,a)=>{const[r,g,b]=hexRgb(h);return`rgba(${r},${g},${b},${a})`};
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const pend=k=>k==='ap'&&window.dsmApprovalPending>0?`<em class="dsmPend">${window.dsmApprovalPending} baru</em>`:'';
const G=k=>k==='home'?HOME:GROUPS.find(g=>g[0]===k)||GROUPS[GROUPS.length-1];
/* cari tempat panel: grup, seksi, urutan */
function placeFor(name){let pos=0;for(const g of GROUPS)for(let si=0;si<g[5].length;si++)for(const t of g[5][si][1]){pos++;if(t.re&&!t.rest&&t.re.test(name||''))return{g:g[0],s:si,pos,t}}const g=GROUPS[GROUPS.length-1];return{g:g[0],s:g[5].length-1,pos:999,t:{kw:''}}}
/* preferensi per akun (disimpan di server) */
const UI=()=>{try{return(window.dsmUi&&window.dsmUi.prefs)||{pins:[],recent:[]}}catch(e){return{pins:[],recent:[]}}};
const saveUi=patch=>{try{if(window.dsmUi&&window.dsmUi.save)return window.dsmUi.save(patch)}catch(e){}return Promise.resolve()};
function ago(t){const m=Math.round((Date.now()-t)/60000);if(m<1)return'baru';if(m<60)return m+' mnt';const h=Math.round(m/60);if(h<24)return h+' jam';const d=Math.round(h/24);return d===1?'kemarin':d+' hari'}
/* ---------- 1b: penggabungan panel. Isi panel lama DIPINDAH (bukan ditulis ulang), jadi tombol, form, dan proses backend tetap yang lama. ---------- */
const MERGES=[
 {name:'Upload Massal Dealer',ic:'📤',hint:'Pilih jenis dulu, proses di belakang tetap sama',kind:'radio',subs:[['all','Tambah + Edit Dealer',/^upload dealer/i,'Satu file untuk dealer baru dan perubahan data. Maks 20.000 baris.'],['field','Edit Field Customer',/^bulk edit customer/i,'Download data existing, edit field, upload ulang. NIK Sales tidak bisa diubah di sini.'],['kontak','Kontak Saja',/^update kontak/i,'PIC, nomor HP, alamat. Maks 500 dealer. Khusus Super Admin.']]},
 {name:'Download & Upload Master',ic:'🗂️',hint:'Admin: download · Super Admin: download + upload',kind:'master'}
];
function mergeAdminPanels(list){
 const kids=()=>[...list.querySelectorAll(':scope>details.adminFeature')];
 const find=re=>kids().find(d=>re.test(d.dataset.feature||''));
 const hideEl=el=>{el.classList.add('dsmMrgHideSel');el.style.setProperty('display','none','important')};
 const take=d=>{const c=d.querySelector(':scope>.adminFeatureContent');if(c){c.classList.remove('adminFeatureContent');c.classList.add('dsmMrgPanel')}return c};
 const mk=(cfg,at)=>{const h=document.createElement('details');h.className='adminFeature dsmMrgHost';h.dataset.feature=cfg.name;h.innerHTML=`<summary><i class="admIc">${cfg.ic}</i><span>${esc(cfg.name)}<small>${esc(cfg.hint)}</small></span></summary>`;const c=document.createElement('div');c.className='panel adminFeatureContent dsmMrg';h.append(c);at.before(h);return{h,c}};
 for(const cfg of MERGES){
  if(cfg.kind==='master'){
   const dl=find(/^download data master/i),up=find(/^bulk master/i),first=dl||up;if(!first)continue;
   const srcSel=(dl&&dl.querySelector('#v6mdType'))||(up&&up.querySelector('#masterBulkType'));if(!srcSel)continue;
   const{h,c}=mk(cfg,first);const hasUp=!!up;
   const opts=[['DEALER','Master Dealer']].concat([...srcSel.options].map(o=>[o.value,o.textContent]));
   c.innerHTML=`<div class="dsmMrgType"><label>JENIS MASTER<select class="fi" id="dsmMrgType">${opts.map(o=>`<option value="${esc(o[0])}">${esc(o[1])}</option>`).join('')}</select></label></div><div class="dsmMrgNote blue" data-m="dealer"><b>Master Dealer</b> ada di sini. Upload-nya diteruskan ke proses <b>Upload Massal Dealer</b> yang sama, jadi tidak ada aturan ganda.<div class="dsmMrgBtns"><button type="button" class="chip" data-mb="dl">DOWNLOAD DATA DEALER</button><button type="button" class="chip primary" data-mb="up">BUKA UPLOAD MASSAL DEALER</button></div></div><div class="dsmMrgBlk" data-m="dl"><div class="dsmMrgLbl">DOWNLOAD</div></div>${hasUp?'<div class="dsmMrgBlk" data-m="up"><div class="dsmMrgLbl">UPLOAD · SUPER ADMIN</div></div>':'<div class="dsmMrgNote" data-m="noup">Upload master khusus Super Admin. Admin bisa download saja.</div>'}`;
   const bd=c.querySelector('[data-m="dl"]'),bu=c.querySelector('[data-m="up"]');
   if(dl){const p=take(dl);if(p){bd.append(p);const s=p.querySelector('#v6mdType');if(s)hideEl(s);p.querySelectorAll(':scope>.pb>p.hint').forEach(x=>x.remove())}dl.remove()}else bd.remove();
   if(up){const p=take(up);if(p){bu.append(p);const s=p.querySelector('#masterBulkType');if(s)hideEl(s.closest('label')||s)}up.remove()}
   const sel=c.querySelector('#dsmMrgType');
   const apply=()=>{const v=sel.value,dealer=v==='DEALER';c.querySelectorAll('[data-m]').forEach(x=>{const m=x.dataset.m;x.hidden=dealer?m!=='dealer':m==='dealer'});if(!dealer){const a=document.getElementById('v6mdType'),b=document.getElementById('masterBulkType');[a,b].forEach(s=>{if(s){s.value=v;s.dispatchEvent(new Event('change',{bubbles:true}))}})}};
   sel.onchange=apply;apply();
   c.addEventListener('click',e=>{const b=e.target.closest('[data-mb]');if(!b)return;if(b.dataset.mb==='dl'){const x=document.getElementById('v6duAll');if(x)x.click();else alert('Buka Upload Massal Dealer untuk download data dealer.')}else{const w=list.closest('.admWrap'),t=kids().find(d=>d.dataset.feature==='Upload Massal Dealer');if(w&&w._dsmGoPanel&&t){w._dsmGoPanel(t);t._sub&&t._sub('all')}}});
   continue}
  const found=cfg.subs.map(s=>({s,d:find(s[2])})).filter(x=>x.d);if(!found.length)continue;
  const{h,c}=mk(cfg,found[0].d);
  const nav=document.createElement('div');nav.className=cfg.kind==='radio'?'dsmMrgOpts':'dsmMrgTabs';c.append(nav);
  const secs={};found.forEach(({s,d})=>{const sec=document.createElement('section');sec.className='dsmMrgSec';sec.dataset.sub=s[0];const p=take(d);if(p)sec.append(p);c.append(sec);secs[s[0]]=sec;d.remove()});
  const ids=found.map(x=>x.s[0]);
  nav.innerHTML=found.map(({s})=>cfg.kind==='radio'?`<button type="button" class="dsmMrgOpt" data-sub="${s[0]}"><i></i><span><b>${esc(s[1])}</b><small>${esc(s[3]||'')}</small></span></button>`:`<button type="button" class="dsmMrgTab" data-sub="${s[0]}">${esc(s[1])}</button>`).join('');
  if(ids.length<2)nav.hidden=true;
  const pick=id=>{if(!secs[id])return;h.dataset.sub=id;nav.querySelectorAll('[data-sub]').forEach(b=>b.classList.toggle('on',b.dataset.sub===id));Object.keys(secs).forEach(k=>secs[k].hidden=k!==id)};
  nav.onclick=e=>{const b=e.target.closest('[data-sub]');if(b)pick(b.dataset.sub)};
  h._sub=pick;h._subs=ids;pick(ids[0]);
 }
 if(!window._dsmMrgGo){window._dsmMrgGo=1;document.addEventListener('click',e=>{const t=e.target.closest&&e.target.closest('[data-v6hgo]');if(!t)return;const key=String(t.dataset.v6hgo||'').toLowerCase();document.querySelectorAll('.dsmMrgHost').forEach(h=>{h.querySelectorAll('.dsmMrgSec').forEach(sec=>{if([...sec.querySelectorAll('h3')].some(x=>x.textContent.toLowerCase().includes(key))&&h._sub)h._sub(sec.dataset.sub)})})},true)}
}
function enhanceAdminGroups(wrap){
 if(!wrap||wrap.dataset.dsmRev==='2')return;const nav=wrap.querySelector('.admTabs'),list=wrap.querySelector('.admList'),finder=wrap.querySelector('#adminFeatureSearch');if(!nav||!list||!finder)return;
 wrap.dataset.dsmRev='2';wrap.classList.add('dsmAdm8');nav.classList.add('dsmAdminTabs');list.classList.add('dsmFocusMode');try{mergeAdminPanels(list)}catch(e){console.warn('[DSM] gabung panel Admin',e)}
 const items=[...list.querySelectorAll(':scope>.adminFeature')];
 const byName={};
 items.forEach(d=>{const name=d.dataset.feature||d.querySelector('summary')?.innerText||'',pl=placeFor(name),g=G(pl.g);byName[name]=d;
  d.dataset.dsmGroup=pl.g;d.dataset.tab=pl.g;d.dataset.dsmSec=String(pl.s);d.dataset.dsmPos=String(pl.pos);d.dataset.dsmColor=g[2];
  d.style.setProperty('--dsm-soft',rgba(g[2],.085));d.style.setProperty('--dsm-mid',rgba(g[2],.14));d.style.setProperty('--dsm-line',rgba(g[2],.28));d.style.setProperty('--dsm-shadow',rgba(g[2],.11));d.style.setProperty('--dsm-strong',g[2]);
  d.dataset.hay=((d.dataset.hay||(name+' '+d.textContent))+' '+(pl.t.kw||'')+' '+g[1]).toLowerCase().replace(/\s+/g,' ');
  const sp=d.querySelector(':scope>summary>span');
  if(sp&&!sp.dataset.dsm8){sp.dataset.dsm8='1';const tn=[...sp.childNodes].find(n=>n.nodeType===3&&n.textContent.trim());
   if(tn){let txt=tn.textContent;const sa=/·\s*super admin/i.test(txt);txt=txt.replace(/\s*·\s*super admin\s*/i,' ').trim();if(pl.t.name)txt=pl.t.name;tn.textContent=txt;if(sa){const b=document.createElement('b');b.className='dsmSA';b.textContent='SUPER ADMIN';tn.after(b)}}}
  const warn=!!(pl.t.w||/HATI-HATI/.test(G(pl.g)[5][pl.s]?.[0]||''));d.classList.toggle('admWarn',warn);if(!warn)d.querySelector(':scope>summary .admWl')?.remove();else if(sp&&!sp.querySelector('.admWl')){const w=document.createElement('span');w.className='admWl';w.textContent='HATI-HATI';const t0=[...sp.childNodes].find(n=>n.nodeType===3&&n.textContent.trim());if(t0)t0.after(w);else sp.prepend(w)}
  const sum=d.querySelector(':scope>summary');if(sum&&!sum.querySelector('.dsmPin')){const pb=document.createElement('button');pb.type='button';pb.className='dsmPin';pb.title='Sematkan di Beranda Admin';pb.setAttribute('aria-label','Sematkan panel');pb.dataset.pin=name;pb.textContent='☆';sum.appendChild(pb)}
 });
 /* urutkan + judul seksi */
 const sorted=items.slice().sort((a,b)=>GROUPS.findIndex(g=>g[0]===a.dataset.dsmGroup)-GROUPS.findIndex(g=>g[0]===b.dataset.dsmGroup)||(+a.dataset.dsmSec)-(+b.dataset.dsmSec)||(+a.dataset.dsmPos)-(+b.dataset.dsmPos));
 const heads=[];let last='';
 sorted.forEach(d=>{const key=d.dataset.dsmGroup+'|'+d.dataset.dsmSec;if(key!==last){last=key;const g=G(d.dataset.dsmGroup),sec=g[5][+d.dataset.dsmSec];
   /* tautan pintas seksi (mis. Brand › TERKAIT) diletakkan setelah seksinya */
   if(sec&&sec[0]){const h=document.createElement('div');h.className='dsmSecHd'+(/HATI-HATI/.test(sec[0])?' warn':'');h.dataset.dsmGroup=d.dataset.dsmGroup;h.textContent=sec[0];list.appendChild(h);heads.push(h)}}
  list.appendChild(d)});
 /* tautan pintas (seksi yang isinya link, mis. Brand › TERKAIT) */
 GROUPS.forEach(g=>g[5].forEach(sec=>sec[1].filter(t=>t.link).forEach(t=>{const target=items.find(d=>(d.dataset.feature||'').toLowerCase().includes(t.link));if(!target||(t.pick&&!(target._subs||[]).includes(t.pick)))return;
  const h=document.createElement('div');h.className='dsmSecHd';h.dataset.dsmGroup=g[0];h.textContent=sec[0];const a=document.createElement('button');a.type='button';a.className='dsmLink';a.dataset.dsmGroup=g[0];a.innerHTML=`<span><b>${esc(t.label)}</b><small>${esc(t.sub)}</small></span><i>›</i>`;a.onclick=()=>{goPanel(target);if(t.pick&&target._sub)target._sub(t.pick)};
  const lastOfG=[...list.children].filter(x=>x.dataset.dsmGroup===g[0]).pop();(lastOfG||list.lastChild).after(h);h.after(a);heads.push(h,a)})));
 const home=document.createElement('div');home.className='dsmHome';list.prepend(home);
 const newNav=nav.cloneNode(false);nav.replaceWith(newNav);const newFinder=finder.cloneNode(true);finder.replaceWith(newFinder);newFinder.placeholder='Cari panel… brand, target, kontak';const countEl=wrap.querySelector('#adminFeatureCount');
 let cur=(()=>{try{return sessionStorage.getItem('dsm-adm-tab')||'home'}catch(e){return'home'}})(),q='';
 const pick=document.createElement('button');pick.type='button';pick.className='dsmCatPick';pick.setAttribute('aria-haspopup','dialog');newNav.before(pick);
 const hit=d=>!q||(d.dataset.hay||d.textContent||'').toLowerCase().includes(q),total=k=>items.filter(d=>d.dataset.dsmGroup===k).length,count=k=>items.filter(d=>d.dataset.dsmGroup===k&&hit(d)).length;
 const avail=()=>[HOME].concat(GROUPS.filter(g=>total(g[0])>0));
 const tabBtn=g=>{const k=g[0],n=k==='home'?'':(q?count(k):total(k));return `<button type="button" data-k="${k}" class="${k===cur&&!q?'on':''}${q&&k!=='home'?(count(k)?' hit':' dim'):''}${q&&k==='home'?' dim':''}" style="--c:${g[2]}"><span>${esc(g[1].toUpperCase())}</span>${n!==''?`<em>${n}</em>`:''}${pend(k)}</button>`};
 function openSheet(){if(document.querySelector('.dsmCatSheet'))return;const ov=document.createElement('div');ov.className='dsmCatSheet';ov.setAttribute('role','dialog');ov.setAttribute('aria-modal','true');ov.innerHTML='<div class="dsmCatBox"><div class="dsmCatGrab"></div><h3>PILIH KATEGORI ADMIN</h3>'+avail().map(tabBtn).join('')+'</div>';const done=()=>{ov.remove();document.removeEventListener('keydown',escK)},escK=e=>{if(e.key==='Escape')done()};ov.onclick=e=>{if(e.target===ov){done();return}const b=e.target.closest('[data-k]');if(!b)return;go(b.dataset.k);done()};document.addEventListener('keydown',escK);document.body.appendChild(ov)}
 pick.onclick=openSheet;
 function go(k){cur=k;try{sessionStorage.setItem('dsm-adm-tab',k)}catch(e){}if(q){q='';newFinder.value='';items.forEach(d=>d.open=false)}paint()}
 function goPanel(d){if(!d)return;go(d.dataset.dsmGroup);d.open=true;}
 wrap._dsmGo=go;wrap._dsmGoPanel=goPanel;
 function paint(){const av=avail();if(!av.some(g=>g[0]===cur))cur='home';if(q&&(cur==='home'||count(cur)===0)){const f=av.find(g=>g[0]!=='home'&&count(g[0])>0);if(f)cur=f[0]}
  newNav.innerHTML=av.map(tabBtn).join('');{const g=G(cur);pick.style.setProperty('--c',g[2]);pick.innerHTML=`<span>${esc(g[1].toUpperCase())}</span>${cur!=='home'?`<em>${q?count(cur):total(cur)}</em>`:''}${pend(cur)}<i aria-hidden="true">▾</i>`}
  const showHome=cur==='home'&&!q;home.hidden=!showHome;if(showHome)renderHome();
  items.forEach(d=>{const show=!showHome&&d.dataset.dsmGroup===cur&&hit(d);d.hidden=!show;if(q&&show&&q.length>1)d.open=true;const pb=d.querySelector('.dsmPin');if(pb){const on=UI().pins.includes(d.dataset.feature);pb.textContent=on?'★':'☆';pb.classList.toggle('on',on)}});
  heads.forEach(h=>h.hidden=showHome||!!q||h.dataset.dsmGroup!==cur);
  wrap.classList.toggle('dsmIsHome',showHome);
  if(countEl)countEl.textContent=q?`${items.filter(hit).length} panel cocok dari ${items.length}`:showHome?`${items.length} panel · ☆ untuk menyematkan`:`${total(cur)} panel · ketuk untuk membuka`;
  const hh=wrap.querySelector('.dsmDetailHost');if(hh&&hh._owner&&hh._owner.hidden){const o=hh._owner;release(hh);o.open=false}clearFocus(list)}
 /* ---------- Beranda Admin (Bento B2, solid) ---------- */
 let homeData=null,homeReq=false;
 function tasks(){const out=[];const n=+window.dsmApprovalPending||0;
  out.push(n>0?{t:`Tinjau ${n} approval`,tag:'PENTING',c:'red',k:'ap'}:{t:'Tidak ada approval menunggu',done:1,k:'ap'});
  try{const cut=REPORT?.coverage?.cutoff,rs=REPORT?.regionStatus||[];if(rs.length){const lag=rs.filter(r=>!r.st||(cut&&r.st<cut));const p=byName[Object.keys(byName).find(x=>/kelengkapan data per region/i.test(x))||''];out.push(lag.length?{t:`${lag.length} region belum lengkap ST`,tag:'DATA',c:'amber',panel:p}:{t:'Semua region lengkap ST',done:1,panel:p})}}catch(e){}
  const b=homeData&&homeData.backup;const bp=byName[Object.keys(byName).find(x=>/autobackup|backup/i.test(x))||''];
  if(b)out.push(b.ok?{t:'Backup harian berhasil'+(b.time?' · '+String(b.time).slice(0,16):''),done:1,panel:bp}:{t:'Backup belum jalan lebih dari 36 jam',tag:'PENTING',c:'red',panel:bp});
  return out}
 function ringPct(){try{const cut=REPORT?.coverage?.cutoff,rs=REPORT?.regionStatus||[];if(!rs.length)return null;const ok=rs.filter(r=>r.st&&(!cut||r.st>=cut)).length;return{p:Math.round(ok/rs.length*100),cut,lag:rs.length-ok}}catch(e){return null}}
 function renderHome(){if(!homeData&&!homeReq&&typeof rpc==='function'){homeReq=true;rpc('apiAdminHome',TOKEN).then(r=>{homeData=r||{};if(!home.hidden)renderHome()}).catch(()=>{homeData={}})}
  const now=new Date(),hr=now.getHours(),salam=hr<11?'Pagi':hr<15?'Siang':hr<18?'Sore':'Malam';let nama='';try{nama=String(BOOT?.user?.nama||'').trim().split(/\s+/)[0]||'';nama=nama.charAt(0).toUpperCase()+nama.slice(1).toLowerCase()}catch(e){}
  const tgl=now.toLocaleDateString('id-ID',{weekday:'long',day:'numeric',month:'short'}).toUpperCase()+' · '+now.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});
  const ts=tasks(),done=ts.filter(t=>t.done).length;const pins=UI().pins.filter(n=>byName[n]);const rec=(UI().recent||[]).filter(r=>byName[r.k]).slice(0,4);const ring=ringPct();
  const ic=n=>{const d=byName[n];const g=G(d?.dataset.dsmGroup);return `<i class="dsmIc" style="--c:${g[2]}">${esc(d?.querySelector('.admIc')?.textContent||g[3])}</i>`};
  const nm=n=>{const sp=byName[n]?.querySelector(':scope>summary>span');const tn=sp&&[...sp.childNodes].find(x=>x.nodeType===3&&x.textContent.trim());return esc(tn?tn.textContent.trim():n)};
  home.innerHTML=`<div class="dsmHi"><small>${esc(tgl)}</small><h2>${salam}${nama?', '+esc(nama):''}</h2></div>
  <div class="dsmBento">
   <section class="dsmCard dsmTasks"><header><b>TUGAS HARI INI · ${done} DARI ${ts.length}</b></header>${ts.map((t,i)=>`<button type="button" class="dsmTask${t.done?' done':''}" data-task="${i}"><i>${t.done?'✓':''}</i><span>${esc(t.t)}</span>${t.tag?`<em class="${t.c}">${t.tag}</em>`:''}</button>`).join('')}</section>
   <section class="dsmCard dsmPins"><header><b>PANEL SEMATAN SAYA</b><small>☆ di daftar panel untuk menambah</small></header><div class="dsmPinGrid">${pins.map(n=>`<button type="button" class="dsmPinTile" data-open="${esc(n)}">${ic(n)}<span>${nm(n)}</span></button>`).join('')||'<p class="dsmEmpty">Belum ada panel disematkan. Buka kategori, lalu ketuk ☆ di panel yang sering dipakai.</p>'}</div></section>
   <section class="dsmCard dsmRing"><header><b>DATA HARIAN</b></header>${ring?`<div class="dsmDonut" style="--p:${ring.p}"><b>${ring.p}%</b></div><p>${ring.lag?ring.lag+' region belum lengkap':'Semua region lengkap'}${ring.cut?'<br><small>ST s.d. '+esc(ring.cut)+'</small>':''}</p>`:'<p class="dsmEmpty">Belum ada data kelengkapan.</p>'}</section>
   <section class="dsmCard dsmRecent"><header><b>TERAKHIR DIBUKA</b></header>${rec.map(r=>`<button type="button" class="dsmRecIt" data-open="${esc(r.k)}">${ic(r.k)}<span>${nm(r.k)}</span><small>${ago(r.t)}</small></button>`).join('')||'<p class="dsmEmpty">Belum ada.</p>'}</section>
  </div>
  <div class="dsmAllHd">SEMUA MENU</div><div class="dsmAll">${GROUPS.filter(g=>total(g[0])>0).map(g=>`<button type="button" class="dsmAllIt" data-go="${g[0]}" style="--c:${g[2]}"><i>${g[3]}</i><b>${esc(g[1])}</b><small>${esc(g[4])}</small><em>${total(g[0])}</em>${pend(g[0])}</button>`).join('')}</div>`;
  home.onclick=e=>{const o=e.target.closest('[data-open]');if(o){goPanel(byName[o.dataset.open]);return}const g=e.target.closest('[data-go]');if(g){go(g.dataset.go);return}const t=e.target.closest('[data-task]');if(t){const x=ts[+t.dataset.task];if(x.panel)goPanel(x.panel);else if(x.k)go(x.k)}}}
 newNav.onclick=e=>{const b=e.target.closest('[data-k]');if(!b)return;go(b.dataset.k)};newFinder.oninput=e=>{q=e.target.value.trim().toLowerCase();if(!q)items.forEach(d=>d.open=false);paint()};
 list.addEventListener('click',e=>{const pb=e.target.closest('.dsmPin');if(!pb)return;e.preventDefault();e.stopPropagation();const n=pb.dataset.pin,p=UI().pins.slice(),i=p.indexOf(n);if(i>=0)p.splice(i,1);else p.push(n);if(window.dsmUi&&window.dsmUi.prefs)window.dsmUi.prefs.pins=p;saveUi({pins:p});paint()},true);
 list.addEventListener('toggle',e=>{const d=e.target;if(!(d instanceof HTMLDetailsElement)||d.parentElement!==list)return;const h=ensureHost(list);if(d.open){activate(h,list,d);const n=d.dataset.feature;if(n){const r=[{k:n,t:Date.now()}].concat((UI().recent||[]).filter(x=>x.k!==n)).slice(0,6);if(window.dsmUi&&window.dsmUi.prefs)window.dsmUi.prefs.recent=r;clearTimeout(wrap._recT);wrap._recT=setTimeout(()=>saveUi({recent:r}),1500)}}else{if(h._owner===d)release(h);if(!list.querySelector(':scope>details.adminFeature[open]'))clearFocus(list)}},true);
 window.addEventListener('dsm-approval-badge',()=>{if(wrap.isConnected)paint()});window.addEventListener('dsm-ui-prefs',()=>{if(wrap.isConnected)paint()});paint();
}
function focusPanel(list,d){[...list.querySelectorAll(':scope>.adminFeature')].forEach(x=>x.classList.toggle('dsmPanelFocus',x===d));list.classList.add('hasFocus')}
/* Isi panel tampil di area terpisah di bawah daftar (bukan akordeon): elemen dipindah, bukan di-clone. */
function ensureHost(list){let h=list.parentElement.querySelector(':scope>.dsmDetailHost');if(!h){h=document.createElement('div');h.className='adminFeature dsmDetailHost';h.hidden=true;list.after(h)}return h}
function release(h){const o=h._owner;if(o){const c=h.querySelector(':scope>.adminFeatureContent');if(c)o.append(c)}h._owner=null;h.hidden=true;h.replaceChildren()}
function activate(h,list,d){if(h._owner===d)return;release(h);const c=d.querySelector(':scope>.adminFeatureContent');if(!c)return;list.querySelectorAll(':scope>details.adminFeature[open]').forEach(x=>{if(x!==d)x.open=false});const t=document.createElement('div');t.className='dsmHostTitle';t.textContent=d.dataset.feature||'';h.replaceChildren(t,c);h._owner=d;h.hidden=false;focusPanel(list,d);requestAnimationFrame(()=>{try{const r=h.getBoundingClientRect();if(r.top<0||r.top>innerHeight*.6)window.scrollTo({top:scrollY+r.top-12,behavior:'smooth'})}catch(e){}})}
function clearFocus(list){list.classList.remove('hasFocus');[...list.querySelectorAll(':scope>.adminFeature')].forEach(x=>x.classList.remove('dsmPanelFocus'))}
function section(head,sub,icon,cls=''){const s=document.createElement('section');s.className='dsmUserSection '+cls;s.innerHTML=`<div class="dsmUserSectionHead"><i>${icon}</i><div><b>${head}</b><small>${sub}</small></div></div><div class="dsmUserSectionGrid"></div>`;return s}
function polishUserForm(form){
 if(!form||form.dataset.dsmPolished==='1')return;form.dataset.dsmPolished='1';form.classList.add('dsmUserPolished');const uf=form.querySelector('.userForm');if(!uf)return;const labels=[...uf.children].filter(x=>x.tagName==='LABEL');if(labels.length<6)return;
 const ident=section('Identitas pengguna','NIK dan nama akun','👤'),struct=section('Jabatan & struktur','Role, region operasional, dan atasan','🧭'),port=section('Portofolio Sales','Aktif hanya untuk ASM / RGM yang ikut menangani portofolio Sales','🎯','dsmPortfolioSection');struct.querySelector('.dsmUserSectionGrid').classList.add('struct');const pi=port.querySelector('.dsmUserSectionGrid');pi.className='dsmPortfolioLine';[labels[0],labels[1]].forEach(x=>ident.querySelector('.dsmUserSectionGrid').appendChild(x));[labels[2],labels[3],labels[4]].forEach(x=>struct.querySelector('.dsmUserSectionGrid').appendChild(x));pi.appendChild(labels[5]);
 const acting=form.querySelector('input[name="acting"]'),confirm=acting?.closest('label.confirm');if(confirm)pi.appendChild(confirm);uf.replaceChildren(ident,struct,port);const role=form.elements.role,region=form.elements.region,portfolio=form.elements.portfolio,wasActing=!!acting?.checked;if(portfolio)portfolio.dataset.lastPortfolio=portfolio.value||region?.value||'';
 const tools=document.createElement('div');tools.className='dsmPortfolioTools';if(wasActing&&form.elements.nik?.readOnly){const btn=document.createElement('button');btn.type='button';btn.className='chip';btn.textContent='Alihkan Portofolio';btn.onclick=()=>window.dsmPortfolioTransfer?.open({sourceNik:form.elements.nik.value,reason:(role?.value==='SALES'?'Resign':'Berhenti rangkap Sales'),onDone:async()=>{if(typeof start==='function')await start()}});tools.appendChild(btn)}const help=document.createElement('span');help.className='dsmPortfolioHelp';help.textContent='Dealer, target, dan aktual MTD dipindahkan lewat satu proses supaya data tidak putus.';tools.appendChild(help);port.appendChild(tools);const warn=document.createElement('div');warn.className='dsmPortfolioWarn';warn.hidden=true;warn.textContent='Portofolio Sales akan dimatikan. Jika masih ada dealer/target aktif, gunakan “Alihkan Portofolio” terlebih dahulu.';port.appendChild(warn);
 function syncPortfolio(){const allow=['ASM','RGM'].includes(role?.value||'');port.hidden=!allow;if(!allow&&acting){acting.checked=false;acting.disabled=true}else if(acting)acting.disabled=false;if(!portfolio||!acting)return;if(!acting.checked){if(portfolio.value)portfolio.dataset.lastPortfolio=portfolio.value;portfolio.value='';portfolio.disabled=true;portfolio.closest('label')?.classList.add('dsmPortfolioDisabled');portfolio.dispatchEvent(new Event('change',{bubbles:true}));warn.hidden=!wasActing}else{portfolio.disabled=false;portfolio.closest('label')?.classList.remove('dsmPortfolioDisabled');if(!portfolio.value)portfolio.value=portfolio.dataset.lastPortfolio||region?.value||'';portfolio.dispatchEvent(new Event('change',{bubbles:true}));warn.hidden=true}}
 role?.addEventListener('change',syncPortfolio);acting?.addEventListener('change',syncPortfolio);region?.addEventListener('change',()=>{if(acting?.checked&&portfolio&&!portfolio.value){portfolio.value=region.value;portfolio.dispatchEvent(new Event('change',{bubbles:true}))}});syncPortfolio();const save=form.querySelector('button[type="submit"],button:not([type])');if(save&&save.parentElement===form){const bar=document.createElement('div');bar.className='dsmSaveBar';save.replaceWith(bar);bar.appendChild(save)}
 try{
  const nm=form.elements.nama,nk=form.elements.nik,hero=document.createElement('div');hero.className='dsmUserHero';
  const ini=v=>(String(v||'?').trim().split(/\s+/).slice(0,2).map(x=>x[0]).join('')||'?').toUpperCase();
  const paint=()=>{hero.innerHTML=`<span class="dsmAvatar">${ini(nm?.value)}</span><div><b>${(nm?.value||'User baru').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]))}</b><small>${(nk?.value||'NIK belum diisi').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]))}</small></div><span class="dsmRolePill">${role?.value||''}</span>`};
  paint();['input','change'].forEach(ev=>form.addEventListener(ev,paint));form.prepend(hero);
  const regionsBox=form.querySelector('#userAdminRegions');
  if(regionsBox){const lead=regionsBox.previousElementSibling,sec=section('Wilayah tugas Admin','Khusus jabatan ADMIN: big region yang boleh dikelola','🗺️','dsmAdminRegionSection');const g=sec.querySelector('.dsmUserSectionGrid');g.className='dsmAdminRegionGrid';if(lead&&lead.tagName==='P')lead.remove();g.appendChild(regionsBox);uf.after(sec);const sync=()=>{sec.hidden=(role?.value||'')!=='ADMIN'};role?.addEventListener('change',sync);sync()}
  const note=[...form.children].find(x=>x.tagName==='P'&&/NIK yang sama/.test(x.textContent||''));if(note)note.classList.add('dsmUserNote');
 }catch(e){console.warn('[DSM] hero Edit User',e)}
}
function compactKpi(root){
 if(!root)return;root.querySelectorAll('.kkInd:not(.dsmCompact)').forEach((card,idx)=>{const head=card.querySelector(':scope>.kkLn:first-child');if(!head)return;card.classList.add('dsmCompact');card.dataset.dsmCompactKey=String(idx);const body=document.createElement('div');body.className='kkCompactBody';[...card.childNodes].filter(n=>n!==head).forEach(n=>body.appendChild(n));card.appendChild(body);const tog=document.createElement('button');tog.type='button';tog.className='kkCompactToggle';tog.textContent='⌄';tog.title='Buka/tutup rincian';tog.onclick=e=>{e.stopPropagation();toggle(card)};head.appendChild(tog);head.onclick=e=>{if(e.target.closest('input,select,button'))return;toggle(card)};const wanted=root.dataset.dsmKkOpen;if((wanted!=null&&wanted!==''&&wanted===card.dataset.dsmCompactKey)||((wanted==null||wanted==='')&&card.classList.contains('dos')&&!root.querySelector('.kkInd.dsmOpen'))){card.classList.add('dsmOpen');tog.textContent='⌃';root.dataset.dsmKkOpen=card.dataset.dsmCompactKey}});
 root.querySelectorAll('.kkTier tbody').forEach(tb=>{if(tb.querySelector('[data-dsm-sostock0]'))return;const stock=[...tb.querySelectorAll('tr')].find(tr=>/stok 0/i.test(tr.textContent||''));if(!stock)return;const tr=document.createElement('tr');tr.dataset.dsmSostock0='1';tr.innerHTML='<td colspan="2"><b>Ada SO tapi stok 0</b></td><td><input class="fi" value="0" disabled title="Aturan tetap: 0 poin"></td><td></td>';stock.after(tr)});
 const total=root.querySelector('.kkTot:not(.dsmWeightTotal)');if(total){total.classList.add('dsmWeightTotal');const m=total.textContent.match(/([\d.,]+)%/),n=m?Math.max(0,Math.min(100,parseFloat(m[1].replace(',','.')))):0;total.style.setProperty('--kk-progress',String(n/100))}const reason=root.querySelector('#kkReason');if(reason&&!reason.closest('.kkStickyFooter')){const label=reason.previousElementSibling,actions=reason.nextElementSibling;if(label&&actions?.classList.contains('kkLn')){const box=document.createElement('div');box.className='kkStickyFooter';label.before(box);box.append(label,reason,actions)}}
}
function toggle(card){const root=card.closest('#kkApp'),open=!card.classList.contains('dsmOpen');if(open)root?.querySelectorAll('.kkInd.dsmOpen').forEach(x=>{x.classList.remove('dsmOpen');const b=x.querySelector('.kkCompactToggle');if(b)b.textContent='⌄'});card.classList.toggle('dsmOpen',open);const b=card.querySelector('.kkCompactToggle');if(b)b.textContent=open?'⌃':'⌄';if(root)root.dataset.dsmKkOpen=open?(card.dataset.dsmCompactKey||''):''}
const obs=new MutationObserver(()=>{document.querySelectorAll('.admWrap').forEach(enhanceAdminGroups);const f=document.getElementById('userEditor');if(f)polishUserForm(f);compactKpi(document.getElementById('kkApp'))});obs.observe(document.documentElement,{childList:true,subtree:true});document.querySelectorAll('.admWrap').forEach(enhanceAdminGroups);const f=document.getElementById('userEditor');if(f)polishUserForm(f);compactKpi(document.getElementById('kkApp'));
})();
