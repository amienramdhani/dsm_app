/* DSM · Sel tabel desktop: kode di depan nama, sub-teks pendek sebaris dengan strip.
   Hanya >=761px, hanya <td> dengan <small class="cellsub"> sebagai anak langsung terakhir.
   Tabel yang sudah punya aturan inline sendiri dilewati. Idempoten; HP tidak disentuh. */
(function(){
 if(window.__dsmCellInline)return;window.__dsmCellInline=1;
 const mq=window.matchMedia('(min-width:761px)');
 const SKIP='#customerRows,#customerTableRows,.v6ewTable,#teamRows,#brandIndicatorRows,.v6dbS,.dtdTable,.v6csTable,.v6gttable,.mobileCard,.modal .v6popHead';
 const isCode=s=>/^[A-Za-z]{0,3}\d[\w.\-\/]{2,}$/.test(s)&&(s.match(/\d/g)||[]).length>=3;
 function fix(td){
  if(td.dataset.ci)return;const sm=td.lastElementChild;
  if(!sm||sm.tagName!=='SMALL'||!sm.classList.contains('cellsub')||sm.parentElement!==td)return;
  if(sm.classList.contains('txSub')||sm.classList.contains('ewAsm')||sm.classList.contains('ewRgm'))return;
  if(td.querySelector('table,button.v6pjc'))return;
  const txt=sm.textContent.trim();if(!txt)return;td.dataset.ci='1';
  const parts=txt.split(' · ');
  if(isCode(parts[0])){
   const c=document.createElement('small');c.className='cellsub ciCode';c.textContent=parts[0];td.insertBefore(c,td.firstChild);
   const rest=parts.slice(1).join(' · ');
   if(rest&&rest.length<=36){sm.textContent=rest;sm.classList.add('ciIn');}else if(rest){sm.textContent=rest;}else sm.remove();
  }else if(txt.length<=36)sm.classList.add('ciIn');
  else td.dataset.ci='0';
 }
 /* Kolom "Sales / Wilayah" (Dealer + TYPE) dipecah jadi dua kolom di desktop */
 function splitSW(tb){
  if(tb.dataset.swSplit||!tb.tHead||!tb.tHead.rows.length)return;
  const hr=tb.tHead.rows[0],idx=[...hr.cells].findIndex(th=>/^sales\s*\/\s*wilayah$/i.test(th.textContent.trim()));
  if(idx<0)return;tb.dataset.swSplit='1';
  const th=hr.cells[idx],nt=document.createElement('th');nt.className=th.className;nt.textContent='Wilayah';th.textContent='Sales';th.after(nt);
  [...tb.tBodies].forEach(b=>[...b.rows].forEach(r=>{const td=r.cells[idx];if(!td||td.colSpan>1)return;const nd=document.createElement('td');nd.className=td.className;const sm=td.lastElementChild;
   if(sm&&sm.tagName==='SMALL'&&sm.classList.contains('cellsub')){nd.textContent=sm.textContent;sm.remove();}else{nd.textContent=r.classList.contains('totalRow')?'':'';}
   td.after(nd);}));
 }
 function scan(){if(!mq.matches)return;document.querySelectorAll('#main table').forEach(tb=>{if(!tb.dataset.swSplit)try{splitSW(tb)}catch(e){}});document.querySelectorAll('#main td > small.cellsub:last-child').forEach(sm=>{const td=sm.parentElement;if(td.tagName==='TD'&&!td.closest(SKIP))try{fix(td)}catch(e){}});}
 let t=0;const go=()=>{clearTimeout(t);t=setTimeout(scan,40)};
 new MutationObserver(go).observe(document.body,{childList:true,subtree:true});go();
})();
