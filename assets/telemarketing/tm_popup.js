/* Catat order langsung di popup dealer (Sales, ASM, Telemarketing, Super Admin). Memakai form bertumpuk 4 kelompok. */
(function(){
 const ROLES=['TELEMARKETING','SALES','ASM','SUPER ADMIN'];
 const pageDraw0=tmDrawOrder;
 const pageDraw=function(){pageDraw0();const f=$('tmOrderForm');if(!f)return;f.querySelectorAll('.tmx-items-table tbody tr').forEach(tr=>{['#','Brand','Nama barang / TYPE','Warna','Qty','Harga / unit','Subtotal',''].forEach((l,i)=>{if(tr.children[i])tr.children[i].dataset.l=l;});const ix=tr.querySelector('.tmx-index');if(ix)ix.dataset.n='Barang '+ix.textContent.trim();});};
 tmDrawOrder=function(){
  if(!tmState.orderModal)return pageDraw();
  tmDrawOrderStack();
  const f=$('tmOrderForm');if(!f)return;
  const g2=f.querySelectorAll('.tm-form-group')[1];
  if(g2){const tb=g2.querySelector('.toolbar');if(tb)tb.remove();const find=$('tmDealerFind');if(find)find.closest('label').remove();}
  f.querySelectorAll('.tm-order-table tbody tr').forEach(tr=>{['No','Brand','Nama barang / TYPE','Warna','Qty','Harga','Subtotal',''].forEach((l,i)=>{if(tr.children[i])tr.children[i].dataset.l=l;});});
  f.querySelectorAll('.tm-order-table tbody tr td:first-child').forEach(td=>td.dataset.n='Barang '+td.textContent.trim());
 };
 const totals=tmDrawTotals;
 tmDrawTotals=function(){
  if(!tmState.orderModal||$('tmOrderQty'))return totals();
  const o=tmState.order,t=tmTotals(o),el=$('tmOrderTotal');if(!el)return;
  el.innerHTML=`<div class="tm-pop-tot"><span>Subtotal<b>${rupiah(t.amount)}</b></span><span>Credit Note<b>− ${rupiah(t.discount)}</b></span><span class="gt">Total order<b>${rupiah(t.total)}</b></span></div>`;
  o.items.forEach(i=>{const c=document.querySelector(`[data-tm-subtotal="${i.uid}"]`);if(c)c.textContent=rupiah((Number(i.qty)||0)*tmMoneyNum(i.price));});
 };
 function draw(){
  const o=tmState.order;
  $('mtitle').textContent='Catat order · '+(o.dealerSnapshot?.name||'');
  $('mbody').innerHTML='<div id="tmOrderEditor"></div><div id="tmOrderList" hidden></div>';
  const m=$('mask').querySelector('.modal');if(m)m.classList.add('tm-ordermodal');
  tmDrawOrder();
 }
 async function open(id){
  try{await tmEnsureBoot();}catch(e){modal('Catat order',`<p class="hint">${esc(e.message)}</p>`);return;}
  const d=tmState.boot.dealers.find(x=>String(x.id)===String(id));
  if(!d){modal('Catat order','<p class="hint">Dealer ini di luar cakupan form order akun Anda.</p>');return;}
  tmState.order=tmNewOrder(d.id);tmState.orderModal=true;draw();
 }
 window.tmOrderPopup=open;
 const mk=document.getElementById('mask');
 if(mk)new MutationObserver(()=>{if(!mk.classList.contains('on')){tmState.orderModal=false;const m=mk.querySelector('.modal');if(m)m.classList.remove('tm-ordermodal');}}).observe(mk,{attributes:true,attributeFilter:['class']});
 const save=tmSaveOrder;
 tmSaveOrder=async function(status){
  await save(status);
  if(tmState.orderModal&&status==='SAVED'&&$('tmOrderError')&&!$('tmOrderError').textContent){setTimeout(()=>{if(tmState.orderModal){tmState.order=null;closeModal();}},1100);}
 };
 const preview=tmPreviewOrder;
 tmPreviewOrder=function(o){
  preview(o);
  if(!tmState.orderModal)return;
  const bar=$('mbody').querySelector('.toolbar');
  if(bar){bar.insertAdjacentHTML('afterbegin','<button class="chip" id="tmPopBack">‹ Kembali ke form</button>');$('tmPopBack').onclick=()=>draw();}
 };
 const orig=tmDealerActions;
 tmDealerActions=function(id){
  const role=BOOT?.user?.role;if(!ROLES.includes(role))return;
  if(tmIsMe())orig(id);
  else{const body=$('mbody');if(!body||body.querySelector('.tm-dealer-actions'))return;body.insertAdjacentHTML('afterbegin','<div class="toolbar tm-dealer-actions"><button class="chip primary" id="tmDealerOrder">+ Catat order</button></div>');}
  const b=$('tmDealerOrder');if(b)b.onclick=()=>open(id);
 };
})();
