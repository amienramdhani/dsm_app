/* DSM · Summary Pencapaian per Brand (desktop): kolom "Target / Aktual" dipisah jadi dua kolom.
   Hanya >=761px dan hanya tabel Summary per Brand; HP tidak berubah. Idempoten. */
(function(){
 if(window.__dsmSummarySplit)return;window.__dsmSummarySplit=1;
 const mq=window.matchMedia('(min-width:761px)');
 function split(tb){
  if(tb.dataset.taSplit)return;const heads=tb.tHead&&tb.tHead.rows;if(!heads||heads.length<2)return;
  const lead=heads[0].querySelectorAll('th.lc').length,body=tb.tBodies[0];if(!body)return;
  const first=[...body.rows].find(r=>r.querySelector('.targetActual'));if(!first)return;
  const cols=[];[...first.cells].forEach((td,j)=>{if(j>=lead&&td.querySelector('.targetActual'))cols.push(j);});
  if(!cols.length)return;tb.dataset.taSplit='1';
  /* header baris 2 + colspan header grup */
  const h2=[...heads[1].cells],metric=[...heads[0].querySelectorAll('th.metricHead')];
  cols.slice().reverse().forEach(j=>{
   const th=h2[j-lead];if(!th)return;
   const nt=document.createElement('th');nt.className=th.className.replace('groupStart','').trim();nt.textContent='Aktual';nt.classList.add('taSplit');
   th.textContent='Target';th.classList.add('taSplit');th.after(nt);
   let acc=0;for(const m of metric){const cs=m.colSpan||1;if(j-lead<acc+cs){m.colSpan=cs+1;break;}acc+=cs;}
  });
  [...tb.tBodies].forEach(b=>[...b.rows].forEach(r=>{
   cols.slice().reverse().forEach(j=>{
    const td=r.cells[j];if(!td)return;const ta=td.querySelector('.targetActual');if(!ta)return;
    const t=ta.children[0]?ta.children[0].textContent:'',a=ta.querySelector('b')?ta.querySelector('b').textContent:'';
    const na=document.createElement('td');na.className='taSplit taAct';na.innerHTML='<b></b>';na.firstChild.textContent=a;
    td.className=(td.className+' taSplit taTgt').trim();td.textContent=t;td.after(na);
   });
  }));
 }
 function scan(){if(!mq.matches)return;document.querySelectorAll('#main table.grouped').forEach(tb=>{if(!tb.dataset.taSplit&&tb.querySelector('tbody button.textlink[data-brand]'))try{split(tb)}catch(e){}});}
 let t=0;const go=()=>{clearTimeout(t);t=setTimeout(scan,30)};
 new MutationObserver(go).observe(document.body,{childList:true,subtree:true});go();
})();
