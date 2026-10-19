/* KPI Khusus — pemilih periode mandiri seperti Atur KPI reguler. */
(function(){
'use strict';
let raw=null,wrapped=null,selected=null,lastHost=null,lastArg=null,switching=false;

function ym(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')}
function currentMonth(){return ym(new Date())}
function periods(){
  const now=new Date(),out=[];
  // Samakan rentang dengan Atur KPI reguler: 3 bulan ke depan s.d. 10 bulan ke belakang.
  for(let i=-2;i<12;i++){
    const d=new Date(now.getFullYear(),now.getMonth()-i+1,1);
    const p=ym(d); if(!out.includes(p))out.push(p);
  }
  return out;
}
function withReportPeriod(period,fn){
  const has=typeof REPORT!=='undefined'&&REPORT;
  const old=has?REPORT.period:null;
  try{if(has)REPORT.period=period;return fn();}
  finally{if(has)REPORT.period=old;}
}
function css(){
  if(document.getElementById('kkPeriodStyle'))return;
  const st=document.createElement('style');st.id='kkPeriodStyle';st.textContent=`
  #kkPeriodBar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0 0 10px;padding:9px 10px;background:#f8fafc;border:1px solid #e3eaf3;border-radius:12px}
  #kkPeriodBar label{display:flex;align-items:center;gap:7px;font:700 11px system-ui;color:#667085}
  #kkPeriodBar select{min-width:110px}
  #kkPeriodMsg{font-size:10.5px;color:#7c8899}
  `;document.head.appendChild(st);
}
function mountToolbar(){
  css();
  const app=document.getElementById('kkApp');if(!app)return;
  const tabs=app.querySelector('.kkTabs');if(!tabs||app.querySelector('#kkPeriodBar'))return;
  const bar=document.createElement('div');bar.id='kkPeriodBar';
  const ps=periods();if(!selected)selected=(typeof REPORT!=='undefined'&&REPORT?.period)||currentMonth();
  if(!ps.includes(selected))ps.unshift(selected);
  bar.innerHTML=`<label>Bulan <select class="fi" id="kkPeriodSelect">${ps.map(p=>`<option value="${p}" ${p===selected?'selected':''}>${p}</option>`).join('')}</select></label><button class="chip" type="button" id="kkPeriodLoad">Muat KPI</button><span id="kkPeriodMsg">Periode KPI Khusus berdiri sendiri dari periode laporan utama.</span>`;
  tabs.before(bar);
  const sel=bar.querySelector('#kkPeriodSelect');
  sel.onchange=()=>switchPeriod(sel.value);
  bar.querySelector('#kkPeriodLoad').onclick=()=>switchPeriod(sel.value,true);
}
function observeToolbar(){
  mountToolbar();
  const app=document.getElementById('kkApp');if(!app)return;
  const ob=new MutationObserver(()=>mountToolbar());ob.observe(app,{childList:true,subtree:true});
  setTimeout(()=>ob.disconnect(),15000);
}
function install(){
  if(typeof window.kkAdminPanel!=='function')return false;
  if(window.kkAdminPanel===wrapped)return true;
  raw=window.kkAdminPanel;
  wrapped=function(v,a){
    lastHost=v;lastArg=a;
    if(!selected)selected=currentMonth();
    const r=withReportPeriod(selected,()=>raw(v,a));
    setTimeout(observeToolbar,0);
    return r;
  };
  window.kkAdminPanel=wrapped;
  return true;
}
function reloadKpiScript(){
  return new Promise((ok,fail)=>{
    const s=document.createElement('script');
    s.src='assets/kpikhusus.js?v=period-'+Date.now();
    s.onload=()=>{s.remove();ok();};
    s.onerror=()=>{s.remove();fail(new Error('Gagal memuat ulang KPI Khusus.'));};
    document.head.appendChild(s);
  });
}
async function switchPeriod(period,force=false){
  if(!/^\d{4}-(0[1-9]|1[0-2])$/.test(period)||switching)return;
  if(!force&&period===selected)return;
  switching=true;selected=period;
  const app=document.getElementById('kkApp');
  const section=app?.closest('section.panel');
  const host=section?.parentElement||lastHost;
  if(!host){switching=false;return;}
  const msg=document.getElementById('kkPeriodMsg');if(msg)msg.textContent='Memuat '+period+'…';
  try{
    if(section)section.remove();
    await reloadKpiScript();
    // kpikhusus.js yang baru memberi closure/state baru, sehingga draft periode lama tidak ikut terbawa.
    raw=null;wrapped=null;
    if(!install())throw new Error('KPI Khusus belum siap.');
    lastHost=host;
    window.kkAdminPanel(host,lastArg);
  }catch(e){
    console.error('[DSM] gagal ganti periode KPI Khusus',e);
    if(typeof alert==='function')alert(e.message||e);
  }finally{switching=false;}
}

let tries=0;
const timer=setInterval(()=>{
  tries++;
  if(install()){
    clearInterval(timer);
    // Bila panel sudah sempat terbuka sebelum enhancer siap, tetap tambahkan selector tanpa mengubah datanya.
    if(document.getElementById('kkApp')){
      selected=(typeof REPORT!=='undefined'&&REPORT?.period)||currentMonth();
      observeToolbar();
    }
  }else if(tries>120)clearInterval(timer);
},100);
})();
