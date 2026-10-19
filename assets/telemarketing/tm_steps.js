/* DSM · Form Catat Order di popup dealer (HP <=760px): tampilan bertahap.
   Hanya lapisan tampilan di atas tmDrawOrder: field, id, handler, validasi, simpan/draft/pratinjau tidak diubah.
   Semua bagian tetap ada di DOM (hanya disembunyikan CSS), jadi tmCollectOrder membaca semuanya. */
(function(){
 if(typeof tmDrawOrder!=='function'||window.__tmSteps)return;window.__tmSteps=1;
 const mq=window.matchMedia('(max-width:760px)'),LBL=['Identitas','Dealer','Barang','Catatan'];
 const H=s=>String(s==null?'':s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
 let cur=0,t=0;
 const draw0=tmDrawOrder;
 function sums(o){
  let d='';try{d=typeof tgl==='function'?tgl(o.date):(o.date||'');}catch(e){d=o.date||'';}
  let tot='';try{const x=tmTotals(o);tot=(o.items||[]).length+' barang'+(x.total?' · '+rupiah(x.total):'');}catch(e){tot=(o.items||[]).length+' barang';}
  const n=String(o.notes||'').trim();
  return [
   [o.company||o.businessSnapshot?.name,o.region,o.payment,d].filter(Boolean).join(' · ')||'Belum diisi',
   [o.dealerSnapshot?.name||o.manualDealer?.name,o.sales].filter(Boolean).join(' · ')||'Belum diisi',
   (o.items||[]).length?tot:'Belum ada barang',
   n?(n.length>44?n.slice(0,44)+'…':n):'Opsional'
  ];
 }
 function done(o){return [!!(o.businessId&&o.region),!!(o.dealerSnapshot?.name&&String(o.sales||'').trim()),(o.items||[]).length>0,true];}
 function setOpen(f,i){
  cur=i;f.querySelectorAll('.tm-form-group').forEach((g,k)=>{g.classList.toggle('fgOpen',k===i);});
  f.querySelectorAll('.fgSteps button').forEach((b,k)=>b.classList.toggle('on',k===i));
 }
 function refresh(f){
  const o=tmState.order;if(!o)return;const S=sums(o),D=done(o);
  f.querySelectorAll('.tm-form-group').forEach((g,k)=>{const s=g.querySelector('.fgSum');if(s)s.textContent=S[k];g.classList.toggle('fgDone',!!D[k]&&k<3);});
  f.querySelectorAll('.fgSteps button').forEach((b,k)=>{b.classList.toggle('ok',!!D[k]&&k<3);b.querySelector('u').textContent=(D[k]&&k<3)?'✓':String(k+1);});
 }
 function enhance(){
  if(!mq.matches||!tmState.orderModal)return;
  const f=document.getElementById('tmOrderForm');if(!f||f.classList.contains('fgx'))return;
  const gs=[...f.querySelectorAll('.tm-form-group')];if(gs.length!==4)return;
  f.classList.add('fgx');
  const nav=document.createElement('nav');nav.className='fgSteps';
  nav.innerHTML=LBL.map((l,k)=>`<button type="button" data-fg="${k}"><u>${k+1}</u>${l}</button>`).join('');
  f.insertBefore(nav,f.firstChild);
  gs.forEach((g,k)=>{
   const sum=document.createElement('div');sum.className='fgSum';g.querySelector('.ph').after(sum);
   const ch=document.createElement('span');ch.className='fgChev';ch.setAttribute('aria-hidden','true');g.querySelector('.ph').appendChild(ch);
   if(k<3){const nx=document.createElement('button');nx.type='button';nx.className='fgNext';nx.dataset.fgnext=String(k+1);nx.textContent='Lanjut · '+LBL[k+1]+' →';g.querySelector('.pb').appendChild(nx);}
  });
  /* bar aksi (Simpan order / draft / Pratinjau) dipindah ke dasar form, tetap elemen yang sama */
  const bar=f.querySelector('.tm-sticky-actions');if(bar){f.appendChild(bar);const err=f.querySelector('#tmOrderError');if(err)bar.insertBefore(err,bar.firstChild);}
  f.addEventListener('click',e=>{
   const ph=e.target.closest('.tm-form-group>.ph');if(ph){const k=gs.indexOf(ph.parentElement);setOpen(f,k===cur?-1:k);return;}
   const st=e.target.closest('.fgSteps button');if(st){setOpen(f,+st.dataset.fg);scrollTo(f,+st.dataset.fg);return;}
   const nx=e.target.closest('[data-fgnext]');if(nx){const k=+nx.dataset.fgnext;setOpen(f,k);scrollTo(f,k);}
  });
  f.addEventListener('invalid',e=>{const g=e.target.closest&&e.target.closest('.tm-form-group');if(!g||g.classList.contains('fgOpen'))return;const k=gs.indexOf(g);setOpen(f,k);const el=e.target;setTimeout(()=>{try{el.reportValidity();}catch(_){}} ,60);},true);
  const re=()=>{clearTimeout(t);t=setTimeout(()=>{try{tmCollectOrder();}catch(e){}refresh(f);},120);};
  f.addEventListener('input',re);f.addEventListener('change',re);
  try{tmCollectOrder();}catch(e){}
  setOpen(f,cur<0?-1:Math.min(cur,3));refresh(f);
 }
 function scrollTo(f,k){const g=f.querySelectorAll('.tm-form-group')[k];if(g)setTimeout(()=>g.scrollIntoView({block:'start',behavior:'smooth'}),30);}
 tmDrawOrder=function(){draw0.apply(this,arguments);try{enhance();}catch(e){}};
 /* tm_popup.js membungkus tmDrawOrder pada saat load; pembungkus kita memanggil versi terakhir, jadi popup baru selalu mulai di langkah 1 */
 const mk=document.getElementById('mask');
 if(mk)new MutationObserver(()=>{if(!mk.classList.contains('on'))cur=0;}).observe(mk,{attributes:true,attributeFilter:['class']});
})();

/* Detail dealer: tombol Tandai/Ajukan dealer tutup dipindah tepat di bawah tombol + Catat order. Hanya urutan elemen. */
(function(){
 let t=0;
 function move(){
  const a=document.querySelector('#mbody .tm-dealer-actions'),b=document.querySelector('#mbody .v6tban.plain');
  if(a&&b&&a.nextElementSibling!==b)a.after(b);
 }
 new MutationObserver(()=>{clearTimeout(t);t=setTimeout(move,30);}).observe(document.body,{childList:true,subtree:true});
})();
