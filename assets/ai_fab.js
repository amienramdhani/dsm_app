/* Tombol AI Assistant melayang (HP). Mockup: doc/mockup/2026-11-26/dsm-ai-tombol-final.html
   Bisa digeser, nempel ke tepi kiri/kanan, posisi diingat per perangkat. Tulisan ASSISTANT tampil 3 detik sekali per sesi. */
(function(){
'use strict';
const KEY='dsmAiFab',SS='dsmAiFabSeen';
let fab=null,st=null,pos=null;
const LOGO='<svg viewBox="0 0 48 48" aria-hidden="true"><path d="M24 3C12.4 3 3 11 3 21c0 5.4 2.8 10.2 7.2 13.5-.3 3-1.6 6-3.7 8.5 4.6-.4 8.6-2.1 11.4-4.3 1.9.4 3.9.6 6.1.6 11.6 0 21-8 21-18S35.6 3 24 3z" fill="#2563EB"/><rect x="11" y="13" width="26" height="17" rx="7" fill="#fff"/><rect x="8.2" y="19" width="3" height="6" rx="1.5" fill="#fff"/><rect x="36.8" y="19" width="3" height="6" rx="1.5" fill="#fff"/><rect x="13.5" y="15.5" width="21" height="12" rx="5" fill="#2563EB"/><circle cx="19.5" cy="20.5" r="1.8" fill="#fff"/><circle cx="28.5" cy="20.5" r="1.8" fill="#fff"/><path d="M21 24.2q3 2.4 6 0" fill="none" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/></svg>';
const page=()=>{try{return typeof PAGE!=='undefined'?PAGE:''}catch(e){return ''}};
const eligible=()=>{const mb=document.getElementById('mBottom');if(!mb||getComputedStyle(mb).display==='none'||!mb.offsetHeight)return false;const p=page();if(p==='ai')return false;if(document.getElementById('mobileMore'))return false;return !!document.querySelector('#navigation [data-page="ai"]');};
function bounds(){const mb=document.getElementById('mBottom');return{top:60,bot:(mb?mb.offsetHeight:60)+8}}
function place(x,y,snap){const w=innerWidth,h=innerHeight,b=bounds(),fw=fab.offsetWidth,fh=fab.offsetHeight;x=Math.max(6,Math.min(w-fw-6,x));y=Math.max(b.top,Math.min(h-fh-b.bot,y));fab.classList.toggle('snap',!!snap);fab.style.left=x+'px';fab.style.top=y+'px';fab.classList.toggle('R',x+fw/2<w/2);return{x,y}}
function init(){let s=null;try{s=JSON.parse(localStorage.getItem(KEY))}catch(e){}const fw=fab.offsetWidth,fh=fab.offsetHeight,b=bounds();const left=s&&s.side==='L';pos=place(left?6:innerWidth-fw-6,s&&typeof s.y==='number'?s.y:innerHeight-fh-b.bot-6,false)}
function build(){
 fab=document.createElement('button');fab.type='button';fab.id='dsmAiFab';fab.setAttribute('aria-label','Buka AI Assistant');fab.innerHTML=LOGO+'<span class="lb">ASSISTANT</span>';fab.hidden=true;document.body.appendChild(fab);
 fab.addEventListener('pointerdown',e=>{try{fab.setPointerCapture(e.pointerId)}catch(_){}st={sx:e.clientX,sy:e.clientY,x:fab.offsetLeft,y:fab.offsetTop,m:false}});
 fab.addEventListener('pointermove',e=>{if(!st)return;const dx=e.clientX-st.sx,dy=e.clientY-st.sy;if(!st.m&&Math.hypot(dx,dy)>6){st.m=true;fab.classList.add('drag')}if(st.m){e.preventDefault();place(st.x+dx,st.y+dy,false)}});
 fab.addEventListener('pointerup',()=>{if(!st)return;const moved=st.m;fab.classList.remove('drag');st=null;if(moved){const left=fab.offsetLeft+fab.offsetWidth/2<innerWidth/2;const p=place(left?6:innerWidth-fab.offsetWidth-6,fab.offsetTop,true);try{localStorage.setItem(KEY,JSON.stringify({side:left?'L':'R',y:p.y}))}catch(e){}}else{try{navigateTo('ai')}catch(e){}}});
 fab.addEventListener('pointercancel',()=>{st=null;fab.classList.remove('drag')});
 addEventListener('resize',()=>{if(!fab.hidden)init()});
}
let shown=false;
function showLabel(){let seen=false;try{seen=sessionStorage.getItem(SS)==='1'}catch(e){}if(seen)return;try{sessionStorage.setItem(SS,'1')}catch(e){}fab.classList.add('lab');setTimeout(()=>fab.classList.remove('lab'),3000)}
function sync(){if(!fab){if(!document.body)return;build()}const ok=eligible();if(ok&&fab.hidden){fab.hidden=false;init();if(!shown){shown=true;showLabel()}}else if(!ok&&!fab.hidden){fab.hidden=true}}
setInterval(sync,700);document.addEventListener('DOMContentLoaded',sync);
})();
