/* DSM transfer UI hotfix: Transfer Sales overflow/combobox portal fix (Bug #3).
   Menu-clone/FAB injection for Transfer Dealer was retired — Transfer Dealer is now
   a native sidebar menu item (index.php GROUPS.lain), see assets/dealer_transfer_direct.js openPage(). */
(function(){
'use strict';
const STYLE_ID='dsmTransferUiHotfixStyle';

function addStyle(){
  let s=document.getElementById(STYLE_ID);
  if(!s){ s=document.createElement('style'); s.id=STYLE_ID; document.head.appendChild(s); }
  s.textContent=`
  details[data-dtd-sales-panel],
  details[data-dtd-sales-panel][open],
  details[data-dtd-sales-panel] .adminFeatureContent,
  details[data-dtd-sales-panel] .adminFeatureContent>.pb,
  #dtdSalesPanel,#dtdSalesPanel .dtdSalesForm{
    height:auto!important;min-height:0!important;max-height:none!important;overflow:visible!important;
  }
  details[data-dtd-sales-panel][open] .adminFeatureContent{display:block!important;visibility:visible!important}
  #dtdSalesPanel .ss{position:relative!important;z-index:80!important;overflow:visible!important}
  .dtdSsPortal{position:fixed!important;z-index:2147483000!important;max-height:300px!important;overflow:auto!important;background:#fff!important;box-shadow:0 12px 32px rgba(20,40,70,.22)!important}
  `;
}

function portalSalesSearch(){
  const panel=document.getElementById('dtdSalesPanel');if(!panel)return;
  const details=panel.closest('details[data-dtd-sales-panel]');
  [details,details?.querySelector('.adminFeatureContent'),details?.querySelector('.pb'),panel,panel.querySelector('.dtdSalesForm')].filter(Boolean).forEach(x=>{
    x.style.setProperty('overflow','visible','important');
    x.style.setProperty('max-height','none','important');
    x.style.setProperty('height','auto','important');
  });
  panel.querySelectorAll('.ss').forEach(wrap=>{
    if(wrap.dataset.dtdPortal==='1')return;wrap.dataset.dtdPortal='1';
    const inp=wrap.querySelector('.ss-in'),list=wrap.querySelector('.ss-list');if(!inp||!list)return;
    const place=()=>{
      const r=inp.getBoundingClientRect();
      if(list.parentElement!==document.body)document.body.appendChild(list);
      list.classList.add('dtdSsPortal');
      list.style.left=Math.round(r.left)+'px';list.style.top=Math.round(r.bottom+3)+'px';list.style.width=Math.round(r.width)+'px';
    };
    inp.addEventListener('focus',()=>requestAnimationFrame(place));
    inp.addEventListener('input',()=>requestAnimationFrame(place));
    inp.addEventListener('blur',()=>setTimeout(()=>{
      if(list.parentElement===document.body){
        wrap.appendChild(list);list.classList.remove('dtdSsPortal');
        list.style.left=list.style.top=list.style.width='';
      }
    },220));
  });
}

addStyle();
portalSalesSearch();
const obs=new MutationObserver(()=>{
  addStyle();portalSalesSearch();
});
obs.observe(document.documentElement,{childList:true,subtree:true});
})();
