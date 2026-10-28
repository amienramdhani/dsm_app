/* Dropdown yang bisa dicari: ketik nama/jabatan, daftar menyaring otomatis. Nilai & event select asli tetap dipakai. */
function dsmSearchable(sel,placeholder){
 if(!sel||sel.dataset.ss)return;sel.dataset.ss='1';
 const wrap=document.createElement('div');wrap.className='ss';sel.parentNode.insertBefore(wrap,sel);wrap.appendChild(sel);sel.classList.add('ss-native');sel.tabIndex=-1;
 const inp=document.createElement('input');inp.type='text';inp.className='fi ss-in';inp.autocomplete='off';const ph=placeholder||'Ketik untuk mencari…';inp.placeholder=ph;
 const list=document.createElement('div');list.className='ss-list';list.hidden=true;wrap.append(inp,list);
 const E=s=>String(s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
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
