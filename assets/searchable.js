/* Dropdown yang bisa dicari: ketik nama/jabatan, daftar menyaring otomatis. Nilai & event select asli tetap dipakai. */
function dsmSearchable(sel,placeholder){
 if(!sel||sel.dataset.ss)return;sel.dataset.ss='1';
 const wrap=document.createElement('div');wrap.className='ss';sel.parentNode.insertBefore(wrap,sel);wrap.appendChild(sel);sel.classList.add('ss-native');sel.tabIndex=-1;
 const inp=document.createElement('input');inp.type='text';inp.className='fi ss-in';inp.autocomplete='off';const ph=placeholder||'Ketik untuk mencari…';inp.placeholder=ph;
 const list=document.createElement('div');list.className='ss-list';list.hidden=true;wrap.append(inp,list);
 const E=s=>String(s).replace(/[&<>\"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;'}[c]));
 const label=()=>sel.options[sel.selectedIndex]?.text||'';
 const sync=()=>{inp.value=label();};
 const draw=q=>{const k=(q||'').trim().toLowerCase(),opts=[...sel.options].filter(o=>!k||o.text.toLowerCase().includes(k));list.innerHTML=opts.length?opts.map(o=>`<button type="button" class="ss-opt${o.selected?' on':''}" data-i="${o.index}" ${o.disabled?'disabled':''}>${E(o.text)}</button>`).join(''):'<div class="ss-none">Tidak ditemukan</div>';};
 const open=()=>{draw('');list.hidden=false;inp.placeholder=label()||ph;inp.value='';const on=list.querySelector('.on');if(on)list.scrollTop=on.offsetTop-40;};
 const close=()=>{list.hidden=true;inp.placeholder=ph;sync();};
 const pick=i=>{sel.selectedIndex=+i;sel.dispatchEvent(new Event('change',{bubbles:true}));close();inp.blur();};
 inp.addEventListener('focus',open);inp.addEventListener('click',()=>{if(list.hidden)open();});
 inp.addEventListener('input',()=>{list.hidden=false;draw(inp.value);});
 inp.addEventListener('keydown',e=>{if(e.key==='Escape'){close();inp.blur();}else if(e.key==='Enter'){e.preventDefault();const f=list.querySelector('.ss-opt:not([disabled])');if(f)pick(f.dataset.i);}});
 list.addEventListener('mousedown',e=>{const b=e.target.closest('.ss-opt');if(b&&!b.disabled){e.preventDefault();pick(b.dataset.i);}else e.preventDefault();});
 inp.addEventListener('blur',()=>setTimeout(close,120));
 sel.addEventListener('change',sync);sync();
}

/* Progressive enhancement approved 2026-10-17. Kept here so index.php tetap tidak perlu disentuh. */
(function dsmApprovedRevisionLoader(){
 if(document.getElementById('dsmAdminRevisionCss'))return;
 const css=document.createElement('link');css.id='dsmAdminRevisionCss';css.rel='stylesheet';css.href='assets/admin_revision.css?v=20261019h';document.head.appendChild(css);const kit=document.createElement('link');kit.id='dsmMobileKitCss';kit.rel='stylesheet';kit.href='assets/mobile_kit.css?v=20261019b';document.head.appendChild(kit);const dense=document.createElement('link');dense.id='dsmTableDenseCss';dense.rel='stylesheet';dense.href='assets/table_dense.css?v=20261019w';document.head.appendChild(dense);const hero=document.createElement('link');hero.id='dsmMobileHeroCss';hero.rel='stylesheet';hero.href='assets/mobile_hero.css?v=20261019m';document.head.appendChild(hero);const bst=document.createElement('link');bst.id='dsmBerandaStyleCss';bst.rel='stylesheet';bst.href='assets/beranda_style.css?v=20261020d';document.head.appendChild(bst);
 const load=(src,id)=>new Promise((ok,fail)=>{if(document.getElementById(id)){ok();return}const s=document.createElement('script');s.id=id;s.src=src;s.onload=ok;s.onerror=fail;document.head.appendChild(s);});
 load('assets/person_picker.js?v=20261029a','dsmPersonPickerJs')
   .then(()=>load('assets/portfolio_transfer.js?v=20261019i','dsmPortfolioTransferJs'))
   .then(()=>load('assets/dealer_transfer_direct.js?v=20261029a','dsmDealerTransferDirectJs'))
   .then(()=>load('assets/transfer_ui_hotfix.js?v=20261020a','dsmTransferUiHotfixJs'))
   .then(()=>load('assets/tx_correction.js?v=20261029a','dsmTxCorrectionJs'))
   .then(()=>load('assets/dealer_target.js?v=20261019b','dsmDealerTargetJs'))
   .then(()=>load('assets/keep_seller.js?v=20261029a','dsmKeepSellerJs'))
   .then(()=>load('assets/owner_history.js?v=20261027a','dsmOwnerHistoryJs'))
   .then(()=>load('assets/aksi.js?v=20261029s','dsmAksiJs'))
   .then(()=>load('assets/admin_revision.js?v=20261019i','dsmAdminRevisionJs'))
   .then(()=>load('assets/admin_portfolio_guard.js?v=20261019a','dsmAdminPortfolioGuardJs'))
   .then(()=>load('assets/mobile_hero.js?v=20261019m','dsmMobileHeroJs'))
   .then(()=>load('assets/holiday_popup.js?v=20261019b','dsmHolidayPopupJs'))
   .then(()=>load('assets/summary_split.js?v=20261019a','dsmSummarySplitJs'))
   .then(()=>load('assets/cell_inline.js?v=20261019c','dsmCellInlineJs'))
   .then(()=>load('assets/kpikhusus_period.js?v=20261017i','dsmKpiKhususPeriodJs'))
   .catch(e=>console.error('[DSM] gagal memuat revisi Admin',e));
})();
