/* Guard Edit User: penghentian rangkap Sales wajib lewat Alihkan Portofolio. */
(function(){
'use strict';
const bound=new WeakSet();
async function bind(form){
 if(!form||bound.has(form))return;bound.add(form);
 const acting=form.elements?.acting,nik=form.elements?.nik,portfolio=form.elements?.portfolio;
 if(!acting||!nik)return;
 const initiallyActing=!!acting.checked;let exited=false;
 if(nik.readOnly&&nik.value){
   try{
     const s=await rpc('apiPortfolioExitState',TOKEN,nik.value);
     exited=!!s?.exited;
     if(exited){
       acting.checked=false;
       if(portfolio){portfolio.value='';portfolio.disabled=true;portfolio.dispatchEvent(new Event('change',{bubbles:true}));}
       acting.dispatchEvent(new Event('change',{bubbles:true}));
       const tools=form.querySelector('.dsmPortfolioTools');
       if(tools){const b=tools.querySelector('button');if(b){b.disabled=true;b.textContent='Portofolio sudah dialihkan';}}
     }
   }catch(e){console.warn('[DSM] status portofolio tidak terbaca',e);}
 }
 form.addEventListener('submit',e=>{
   if(initiallyActing&&!acting.checked&&!exited){
     e.preventDefault();e.stopImmediatePropagation();
     alert('Gunakan tombol “Alihkan Portofolio” agar dealer, target, dan aktual ikut dipindahkan dalam satu proses.');
   }
 },true);
}
const scan=()=>{const f=document.getElementById('userEditor');if(f)bind(f)};
new MutationObserver(scan).observe(document.documentElement,{childList:true,subtree:true});scan();
})();
