/* Hero Beranda HP. Pace = capaian SELL THRU / time gone (hari kerja). 4 tingkat: >=70 aman, 50-69, 30-49, <30.
   5 hari kerja pertama selalu tampil normal. Data dari V6X (apiExec), tanpa request baru. */
(function(){
 if(window.dsmMobileHero)return;window.dsmMobileHero=1;
 const IMG={pagi:'assets/hero/pagi.webp',siang:'assets/hero/siang.webp',sore:'assets/hero/sore.webp',malam:'assets/hero/malam.webp',badai:'assets/hero/badai.webp'};
 const V='?v=20261019m',MOOD={pagi:'Pagi cerah · siap mulai visit dealer',siang:'Siang cerah · lanjut follow-up dealer',sore:'Sore · waktunya closing visit',malam:'Hari selesai · waktunya istirahat'};
 const GREET={pagi:'Selamat pagi,',siang:'Selamat siang,',sore:'Selamat sore,',malam:'Selamat malam,'};
 const rand=(a,b)=>Math.floor(Math.random()*(b-a+1))+a;
 let timer=null;
 function tod(){const h=new Date().getHours();return h>=5&&h<10?'pagi':h>=10&&h<16?'siang':h>=16&&h<19?'sore':'malam';}
 function calc(){
  try{
   if(typeof V6X==='undefined'||!V6X||!V6X.data||V6X.drill)return null;
   const d=V6X.data,node=(d.level==='ASM'||d.level==='SALES')?d.nodes.O:d.nodes.N;if(!node)return null;
   const m=v6XM(node,'ALL','st');if(!(m.t>0)||!(d.days>0))return {normal:true,run:d.run,days:d.days};
   const cap=m.a/m.t*100,tg=d.run/d.days*100,pace=tg>0?cap/tg*100:100;
   return {cap,tg,pace,run:d.run,days:d.days};
  }catch(e){return null;}
 }
 function tier(c){
  if(!c||c.normal||c.run<=5||c.pace==null)return 0;
  return c.pace>=70?0:c.pace>=50?1:c.pace>=30?2:3;
 }
 const CHIP=['','Sedikit di bawah ritme · masih bisa dikejar','Performa mulai tertinggal dari Time Gone','Target kritis · fokus recovery plan'];
 const TXT=['Performa aman','Mulai tertinggal · perlu dikejar','Warning · tertinggal dari Time Gone','Kritis · tertinggal jauh'];
 const f1=v=>String(Math.round(v*10)/10).replace('.',',');
 function flashLoop(el,t){
  clearTimeout(timer);if(!t)return;
  const fl=el.querySelector('.hsFlash');if(!fl)return;
  const ms=t===3?rand(3000,5000):t===2?rand(5000,8000):rand(8000,12000);
  timer=setTimeout(()=>{if(!el.isConnected)return;if(el.offsetParent!==null){fl.classList.remove('pulse');void fl.offsetWidth;fl.classList.add('pulse');}flashLoop(el,t);},ms);
 }
 function build(){
  const main=document.getElementById('main'),view=document.getElementById('view');
  document.body.classList.toggle('dsmBerandaPage',typeof PAGE!=='undefined'&&PAGE==='beranda');
  const off=()=>{const o=document.getElementById('dsmHero');if(o)o.remove();if(main)main.classList.remove('dsmHeroOn');page(false);};
  if(!main||!view||typeof PAGE==='undefined'||PAGE!=='beranda')return off();
  const c=calc(),old=document.getElementById('dsmHero');
  if(!c)return off();
  const t=tier(c),T=tod(),sig=[T,t,c.pace==null?'':Math.round(c.pace),c.cap==null?'':Math.round(c.cap),c.run].join('|');
  if(old&&old.dataset.sig===sig&&main.firstElementChild===old&&main.classList.contains('dsmHeroOn'))return;
  if(old)old.remove();
  main.classList.remove('dsmHeroOn');
  const cs=getComputedStyle(main);main.style.setProperty('--hsPx',(parseFloat(cs.paddingLeft)||0)+'px');
  const el=document.createElement('section');el.id='dsmHero';el.dataset.sig=sig;el.setAttribute('aria-label','Ringkasan suasana pencapaian');
  el.className=T+(t?' t'+t:'');
  const img=(t&&T!=='malam')?IMG.badai:IMG[T],nm=(BOOT&&BOOT.user&&BOOT.user.nama)||'';
  const ach=c.cap==null?'—':f1(c.cap)+'%',tg=c.tg==null?'—':f1(c.tg)+'%',pc=c.pace==null||c.run<=5?'':Math.round(c.pace)+'%';
  const e=s=>String(s).replace(/[&<>"]/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[x]));
  el.innerHTML='<div class="hsScene" style="background-image:url(\''+img+V+'\')"></div>'
   +'<div class="hsHi"><small>'+GREET[T]+'</small><b>'+e(nm)+'</b></div>'
   +'<div class="hsMood">'+e(t?CHIP[t]:MOOD[T])+'</div>'
   +'<div class="hsFlash"></div>'
   +'<div class="hsSum"><div><b>'+e(TXT[t])+'</b><span>Sell Thru '+ach+' · Time Gone '+tg+'</span></div>'+(pc?'<div class="hsPace">'+pc+'<small>Pace</small></div>':'')+'</div>';
  main.insertBefore(el,main.firstChild);main.classList.add('dsmHeroOn');page(true);gs();
  flashLoop(el,t);
 }
 function page(on){
  on=!!on&&matchMedia('(max-width:760px)').matches;
  document.body.classList.toggle('dsmHeroPage',!!on);
  let b=document.getElementById('dsmBand');
  if(on&&!b){b=document.createElement('div');b.id='dsmBand';b.setAttribute('aria-hidden','true');document.body.appendChild(b);}
  if(!on&&b)b.remove();
 }
 /* Kartu Cakupan & Periode di menu Lainnya (HP). Select asli tetap dipakai: pilihan diteruskan ke #scopeSelect/#periodSelect. */
 function moreCard(m){
  const panel=m.querySelector('.mmPanel');if(!panel||panel.querySelector('.hsCard'))return;
  const sc=document.getElementById('scopeSelect'),pe=document.getElementById('periodSelect');if(!sc||!pe)return;
  const e=s=>String(s).replace(/[&<>"]/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[x]));
  const mk=(src,label)=>{const opts=[...src.options].map((o,i)=>'<option value="'+i+'"'+(i===src.selectedIndex?' selected':'')+'>'+e(o.text)+'</option>').join('');
   return '<label class="hsRow"><span><small>'+label+'</small></span><select class="hsSel" data-for="'+src.id+'"'+(src.disabled?' disabled':'')+'>'+opts+'</select></label>';};
  const upd=(document.getElementById('updData')||{}).textContent||'';
  const d=document.createElement('div');d.className='hsCard';
  d.innerHTML='<h4>CAKUPAN &amp; PERIODE</h4>'+mk(sc,'Cakupan')+mk(pe,'Periode')+(upd.trim()&&upd.trim()!=='—'?'<p class="hsUpd"><i></i>Data lengkap s.d. <b>'+e(upd.trim())+'</b></p>':'');
  const me=panel.querySelector('.mmMe');if(me)me.after(d);else panel.prepend(d);
  d.addEventListener('change',ev=>{const t=ev.target.closest('.hsSel');if(!t)return;const src=document.getElementById(t.dataset.for);if(!src)return;src.selectedIndex=+t.value;src.dispatchEvent(new Event('change',{bubbles:true}));try{if(typeof closeMobileMore==='function'){closeMobileMore();if(typeof syncMobileNav==='function')syncMobileNav();}}catch(x){}});
 }
 new MutationObserver(ms=>{for(const r of ms)for(const n of r.addedNodes){if(n.nodeType===1&&n.id==='mobileMore')moreCard(n);}}).observe(document.body,{childList:true,subtree:true});
 function geo(){
  const h=document.getElementById('dsmHero');if(!h||h.offsetParent===null)return;
  const tb=document.querySelector('.topbar'),H=h.offsetHeight,T=tb?tb.offsetHeight:0;document.body.style.setProperty('--hsT',T+'px');
  h.style.setProperty('--hsTop',(tb&&getComputedStyle(tb).position==='sticky'?tb.offsetHeight:0)+'px');
  h.style.setProperty('--hsHalf',(H/2)+'px');{const m=document.getElementById('main');if(m){m.style.setProperty('--hsTop',h.style.getPropertyValue('--hsTop'));m.style.setProperty('--hsHalf',(H/2)+'px');}}
  const st=parseFloat(h.style.getPropertyValue('--hsTop'))||0,r=h.getBoundingClientRect().top;
  const k=Math.max(0,Math.min(1,(st-r)/(H/2)));h.style.setProperty('--k',k.toFixed(3));
  const band=document.getElementById('dsmBand'),tabs=document.querySelector('#main .v7btabs');
  if(band){if(tabs){let bg='';for(const el of [document.getElementById('main'),document.getElementById('app'),document.body,document.documentElement]){const c=el&&getComputedStyle(el).backgroundColor;if(c&&c!=='rgba(0, 0, 0, 0)'&&c!=='transparent'){bg=c;break;}}band.style.cssText='top:'+st+'px;height:'+(H/2+6+tabs.offsetHeight+10)+'px;background:'+(bg||'#F3F6FC');band.hidden=false;}else band.hidden=true;}
 }
 let gr=0;const gs=()=>{cancelAnimationFrame(gr);gr=requestAnimationFrame(geo);};
 addEventListener('scroll',gs,{passive:true});addEventListener('resize',gs);
 let raf=0;const sched=()=>{cancelAnimationFrame(raf);raf=requestAnimationFrame(()=>{try{build();}catch(e){}});};
 function watch(){const main=document.getElementById('main');if(!main){setTimeout(watch,400);return;}new MutationObserver(sched).observe(main,{childList:true,subtree:true});sched();}
 watch();
})();
