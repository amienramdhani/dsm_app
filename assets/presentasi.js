/* Presentasi DSM (ASM ke atas). Slide dibangun dari data yang sama dengan Beranda (apiExec), jadi angkanya identik.
   Tahap 1: review bulanan dari modul Beranda. Teks edit disimpan di perangkat ini (localStorage). */
(function(){
const COL={st:'#1E5FB8',so:'#C25A12',da:'#0E7C6B',noo:'#6D3FD8',om:'#9A6400'};
const LBL={st:'ST (SELL THRU)',so:'SO (SELL OUT)',da:'DEALER AKTIF',noo:'NOO',om:'OMZET'};
const P={d:null,key:'',slides:[],on:new Set(),cur:0,ov:{},custom:[],st:null};
const fm=(v,i)=>i==='om'?moneyShort(v):num(Math.round(v));
const fu=(v,i)=>fm(v,i)+(i==='st'||i==='so'?' unit':'');
const cl=p=>p>=100?'pg':p>=80?'pa':'pr';
const cc=p=>p>=100?'#CDEEDB':p>=80?'#FFE9B8':'#F8C9C9';
const sg=g=>g==null?'—':(g>=0?'▲ +':'▼ ')+decimal(Math.abs(g))+'%';
function lsKey(){return 'dsmPres|'+(BOOT?.user?.nik||'')+'|'+(P.d?.period||'')}
function load(){try{const j=JSON.parse(localStorage.getItem(lsKey())||'{}');P.ov=j.ov||{};P.custom=j.custom||[];P.st=j.on||null}catch(e){P.ov={};P.custom=[];P.st=null}}
function save(){try{localStorage.setItem(lsKey(),JSON.stringify({ov:P.ov,custom:P.custom,on:[...P.on]}))}catch(e){}}
/* ---------- chart kecil ---------- */
const T=(x,y,t,o={})=>`<text x="${x}" y="${y}" font-size="${o.s||11}" fill="${o.c||'#5b6b7f'}" text-anchor="${o.a||'start'}" font-weight="${o.w||600}">${esc(t)}</text>`;
function lineChart(ser,labels){let o='';for(let i=0;i<5;i++){const y=20+i*40;o+=`<line x1="44" y1="${y}" x2="330" y2="${y}" stroke="#e6edf5"/>`}
 const mx=Math.max(1,...ser.flatMap(s=>s[1]));[0,1,2,3,4].forEach(i=>o+=T(40,24+i*40,num(mx*(1-i/4)),{a:'end',s:8.5}));
 const n=labels.length;labels.forEach((l,i)=>{if(n<=12||i%Math.ceil(n/10)===0)o+=T(44+i*(286/Math.max(1,n-1)),195,l,{a:'middle',s:9})});
 ser.forEach(([nm,d,c,dash])=>{const pts=d.map((v,i)=>`${44+i*(286/Math.max(1,n-1))},${180-v/mx*160}`).join(' ');o+=`<polyline points="${pts}" fill="none" stroke="${c}" stroke-width="2.6" stroke-linejoin="round" ${dash?'stroke-dasharray="5 4"':''}/>`});
 ser.forEach(([nm,,c],i)=>o+=`<rect x="${50+i*100}" y="4" width="10" height="4" rx="2" fill="${c}"/>`+T(64+i*100,9,nm,{s:9}));
 return `<svg viewBox="0 0 340 205">${o}</svg>`}
function wrap(m,k,t,bodyCls,body,ins){return {m,k,t,cls:bodyCls,body,ins:ins||[]}}
const KPI=(l,v,x='')=>`<div class="card"><small>${l}</small><b>${v}</b>${x}</div>`;
/* ---------- slide Beranda ---------- */
function buildBeranda(d){
 const out=[],root=d.nodes.N||d.nodes.O;if(!root)return out;
 const M=(n,b,i)=>v6XM(n,b,i),C=(m,i)=>v6XCalc(m,i,d);
 const st=M(root,'ALL','st'),cst=C(st,'st'),so=M(root,'ALL','so'),om=M(root,'ALL','om'),coM=C(om,'om');
 const per=bulan(d.period);
 out.push({id:'sampul',m:'BERANDA',k:'Sampul',t:'Review DSM '+per,cover:1,cards:[['AKTUAL ST',fu(st.a,'st')],['PROYEKSI ST',cst.pp==null?'—':Math.round(cst.pp)+'%'],['OMZET',fm(om.a,'om')]]});
 /* kartu indikator */
 const cards=['st','so','da','noo','om'].map(i=>{const m=M(root,'ALL',i),c=C(m,i),has=c.hasT,col=COL[i];
  return `<div class="ic" style="--c:${col}"><h4>${LBL[i]}</h4><div class="big">${fu(m.a,i)}</div><div class="b2"><i style="width:${has?Math.min(100,c.cap):Math.min(100,m.lm?m.a/m.lm*100:0)}%"></i></div>${has?`<div class="r"><span>Target</span><b>${fm(m.t,i)}</b></div>`:''}<div class="r"><span>Aktual</span><b>${fm(m.a,i)}</b></div><div class="r"><span>Today</span><b>${m.td==null?'—':fm(m.td,i)}</b></div><div class="r"><span>LMTD</span><b>${fm(m.lm,i)}</b></div><div class="r"><span>Growth</span><b class="${(c.g||0)>=0?'pg':'pr'}">${i==='noo'?(Math.round(c.g)>0?'+':'')+num(Math.round(c.g)||0):sg(c.g)}</b></div>${has&&c.pp!=null?`<div class="r"><span>Proyeksi</span><b class="${cl(c.pp)}">${Math.round(c.pp)}%</b></div>`:''}</div>`}).join('');
 const ins=[];
 if(cst.hasT)ins.push(`ST ${fu(st.a,'st')} (${Math.round(cst.cap)}% dari target ${fm(st.t,'st')}); proyeksi akhir bulan ${Math.round(cst.pp)}%.`);
 if(so.a&&st.a)ins.push(`SO ${fu(so.a,'so')} ${so.a>st.a?'lebih tinggi':'lebih rendah'} dari ST: ${so.a>st.a?'barang keluar ke konsumen lebih cepat dari yang masuk ke dealer.':'barang masuk ke dealer lebih cepat dari yang keluar ke konsumen.'}`);
 ins.push(`Omzet ${fm(om.a,'om')}${coM.g!=null?`, ${coM.g>=0?'naik':'turun'} ${decimal(Math.abs(coM.g))}% dari LMTD`:''}.`);
 out.push({id:'kartu',m:'BERANDA',k:'Kartu indikator',t:cst.hasT?`ST ${Math.round(cst.cap)}% dari target, proyeksi ${Math.round(cst.pp)}%`:'Ringkasan indikator '+per,cls:'g5',body:[cards],ins});
 /* heatmap + region */
 const bigs=Object.keys(d.nodes).filter(k=>k.startsWith('B|')).sort((a,b)=>(C(M(d.nodes[a],'ALL','st'),'st').pp??999)-(C(M(d.nodes[b],'ALL','st'),'st').pp??999));
 if(bigs.length&&d.brands.length){
  const cell=(n,b)=>{const m=M(n,b,'st');if(!m.t&&!m.a)return '<div class="hd">–</div>';const c=C(m,'st');return c.pp==null?'<div class="hd">–</div>':`<div style="background:${cc(c.pp)}">${Math.round(c.pp)}%</div>`};
  const rows=bigs.map(k=>`<div class="rn">${esc(d.nodes[k].name)}</div>${d.brands.map(b=>cell(d.nodes[k],b)).join('')}`).join('')+`<div class="rn">TOTAL</div>${d.brands.map(b=>cell(root,b)).join('')}`;
  const low=[];bigs.forEach(k=>{const r=d.brands.filter(b=>{const m=M(d.nodes[k],b,'st');const c=C(m,'st');return c.pp!=null&&c.pp<80});if(r.length)low.push(`${d.nodes[k].name}: ${r.length} brand di bawah 80% (${r.slice(0,4).join(', ')})`)});
  out.push({id:'heat',m:'BERANDA',k:'Heatmap region',t:'Peta pencapaian ST per region dan brand',cls:'c1',body:[`<div class="card"><div class="hm" style="grid-template-columns:1.4fr repeat(${d.brands.length},1fr)"><div class="hd"></div>${d.brands.map(b=>`<div class="hd">${esc(b)}</div>`).join('')}${rows}</div><div style="display:flex;gap:1cqw;margin-top:.8cqw;font-size:clamp(7px,.9cqw,11px);color:#5b6b7f"><span><i style="display:inline-block;width:10px;height:10px;background:#CDEEDB;border-radius:3px"></i> ≥100%</span><span><i style="display:inline-block;width:10px;height:10px;background:#FFE9B8;border-radius:3px"></i> 80–99%</span><span><i style="display:inline-block;width:10px;height:10px;background:#F8C9C9;border-radius:3px"></i> &lt;80%</span><span>Proyeksi akhir bulan terhadap target ST</span></div></div>`],ins:low.length?low.slice(0,3):['Semua region di atas 80% pada semua brand.']});
 }
 /* brand nasional */
 const bn=d.brands.map(b=>{const m=M(root,b,'st');return {b,m,c:C(m,'st')}}).filter(x=>x.m.a||x.m.t).sort((a,b)=>(b.c.pp??-1)-(a.c.pp??-1));
 if(bn.length){
  const col=p=>p==null?'#9fb0c3':p>=100?'#12866A':p>=80?'#F0A400':'#D2453F';
  const bars=bn.map(x=>`<div class="rb"><span>${esc(x.b)}</span><div class="tr"><i style="width:${Math.min(100,x.c.cap||0)}%;background:${col(x.c.pp)}"></i><em style="left:${Math.min(100,x.c.tg)}%"></em></div><span style="color:${col(x.c.pp)}">${x.c.pp==null?'—':Math.round(x.c.pp)+'%'}</span><span class="${(x.c.g||0)>=0?'pg':'pr'}">${sg(x.c.g)}</span></div>`).join('')+'<div style="font-size:clamp(7px,.9cqw,11px);color:#5b6b7f;margin-top:.5cqw">Batang = aktual terhadap target · garis = posisi hari berjalan · kanan = proyeksi dan growth vs LMTD</div>';
  const tb=`<table class="tb"><tr><th>BRAND</th><th>TARGET</th><th>AKTUAL</th><th>SELISIH</th></tr>${bn.map(x=>`<tr><td>${esc(x.b)}</td><td>${x.m.t?num(x.m.t):'—'}</td><td>${num(x.m.a)}</td><td class="${x.m.t&&x.m.a>=x.m.t?'pg':'pr'}">${x.m.t?(x.m.a-x.m.t>=0?'+':'−')+num(Math.abs(Math.round(x.m.a-x.m.t))):'—'}</td></tr>`).join('')}</table>`;
  const best=bn.filter(x=>x.c.pp!=null),ins2=[];
  if(best.length){const t=best[0],w=best[best.length-1];ins2.push(`${t.b} tertinggi (${Math.round(t.c.pp)}% proyeksi), ${w.b} terendah (${Math.round(w.c.pp)}%).`);if(w.m.t)ins2.push(`${w.b}: ${num(Math.round(w.m.a))} dari target ${num(w.m.t)} unit, kurang ${num(Math.max(0,Math.round(w.m.t-w.m.a)))} unit.`)}
  out.push({id:'brand',m:'BERANDA',k:'Brand nasional',t:best.length?`${best[0].b} terdepan, ${best[best.length-1].b} paling tertinggal`:'Pencapaian per brand',cls:'c2',body:[`<div class="card">${bars}</div>`,`<div class="card">${tb}</div>`],ins:ins2});
 }
 /* kartu region */
 if(bigs.length){
  const rc=bigs.slice(0,6).map(k=>{const m=M(d.nodes[k],'ALL','st'),c=C(m,'st');return `<div class="rc"><div class="t"><b>${esc(d.nodes[k].name)}</b><b class="${c.pp==null?'':cl(c.pp)}">${c.pp==null?'—':Math.round(c.pp)+'%'}</b></div><div class="k2"><div><span>Target</span><b>${m.t?num(m.t):'—'}</b></div><div><span>Aktual</span><b>${num(m.a)}</b></div><div><span>Today</span><b>${num(m.td)}</b></div><div><span>LMTD</span><b>${num(m.lm)}</b></div><div><span>Growth</span><b class="${(c.g||0)>=0?'pg':'pr'}">${sg(c.g)}</b></div></div></div>`}).join('');
  const g=bigs.map(k=>({n:d.nodes[k].name,c:C(M(d.nodes[k],'ALL','st'),'st')})).filter(x=>x.c.g!=null),down=g.filter(x=>x.c.g<0).map(x=>x.n);
  out.push({id:'region',m:'BERANDA',k:'Kartu region',t:'Pencapaian ST per Big Region',cls:'c1',body:[`<div>${rc}</div>`],ins:[bigs.length>6?`Menampilkan 6 dari ${bigs.length} region (terendah dulu).`:`${bigs.length} region, diurutkan dari proyeksi terendah.`,down.length?`Region turun dari LMTD: ${down.join(', ')}.`:'Semua region naik dari LMTD.']});
 }
 /* tren kumulatif */
 const dl=(d.daily||{}).N||(d.daily||{}).O||{},dates=Object.keys(dl).filter(x=>x<=d.cutoff&&x.slice(0,7)===d.period).sort();
 if(dates.length>1&&st.t){let cum=0;const a=dates.map(x=>cum+=Object.values(dl[x]).reduce((s,v)=>s+v,0)),pace=dates.map((x,i)=>st.t/d.days*(i+1));
  out.push({id:'tren',m:'BERANDA',k:'Tren harian',t:`Akumulasi ST: ${num(Math.round(a[a.length-1]))} unit dari target ${num(st.t)}`,cls:'c1',body:[`<div class="card">${lineChart([['Aktual',a,'#0E7C6B'],['Target (garis laju)',pace,'#F0A400',1]],dates.map(x=>x.slice(8)))}</div>`],ins:[`Aktual ${num(Math.round(a[a.length-1]))} unit pada ${dates.length} hari penjualan; laju target ${num(Math.round(pace[pace.length-1]))} unit.`,a[a.length-1]>=pace[pace.length-1]?'Aktual di atas laju target.':'Aktual di bawah laju target.']});}
 /* ASM */
 const asm=(d.asm||[]).slice().sort((a,b)=>b.proj-a.proj);
 if(asm.length>=2){const row=x=>`<div class="rb" style="grid-template-columns:1fr auto"><span>${esc(x.nama)} · ${esc(x.region||'')}</span><span class="${cl(x.proj)}">${Math.round(x.proj)}%</span></div>`;
  out.push({id:'asm',m:'BERANDA',k:'ASM teratas & terendah',t:`${asm[0].nama} memimpin, ${asm[asm.length-1].nama} paling rendah`,cls:'c2',body:[`<div class="card"><small>TERTINGGI (ESTIMASI AKHIR BULAN)</small>${asm.slice(0,3).map(row).join('')}</div>`,`<div class="card"><small>TERENDAH</small>${asm.slice(-3).reverse().map(row).join('')}</div>`],ins:[`Selisih proyeksi tertinggi dan terendah ${Math.round(asm[0].proj-asm[asm.length-1].proj)} poin.`]});}
 return out;
}
/* ---------- penutup otomatis ---------- */
function buildClose(base){
 const k=base.find(s=>s.id==='kartu'),b=base.find(s=>s.id==='brand');
 return {id:'penutup',m:'PENUTUP',k:'Penutup',t:'Ringkasan dan tindak lanjut',cls:'c1',body:[`<div class="card" style="font-size:clamp(9px,1.5cqw,17px);line-height:1.55"><b style="font-size:clamp(10px,1.7cqw,19px);color:#10394E">Ringkasan</b><ul style="margin:.6cqw 0 0;padding-left:1.4cqw">${((k&&k.ins)||[]).concat((b&&b.ins)||[]).slice(0,5).map(x=>`<li>${esc(x)}</li>`).join('')}</ul></div>`],ins:[]};
}
function buildAll(d){const base=buildBeranda(d);const all=base.concat([buildClose(base)]);
 P.custom.forEach(c=>all.splice(all.length-1,0,{id:c.id,m:'PENUTUP',k:'Slide teks',t:c.t||'Slide teks',cls:'c1',custom:1,body:[`<div class="card" style="font-size:clamp(9px,1.6cqw,18px);line-height:1.6;white-space:pre-line">${esc(c.body||'')}</div>`],ins:[]}));
 return all}
const RING=['sampul','kartu','heat','brand','penutup'];
/* ---------- render ---------- */
function slideHtml(s){const o=P.ov[s.id]||{},title=o.t!=null&&o.t!==''?o.t:s.t,per=bulan(P.d.period),scope=$('scopeSelect')?.selectedOptions?.[0]?.textContent||'';
 const foot=`<div class="ft"><span>Data s.d. ${esc(P.d.cutoff||'')} · Cakupan: ${esc(scope)}</span><span>DSM</span></div>`;
 if(s.cover)return `<div style="flex:1;display:flex;flex-direction:column;justify-content:center"><div class="k">DSM · DISTRIBUTION SALES MONITORING</div><h3 style="font-size:clamp(22px,5cqw,56px)">${esc(title)}</h3><div style="color:#5b6b7f;font-size:clamp(10px,1.6cqw,18px)">${esc(scope)} · MTD vs LMTD</div><div style="display:flex;gap:2cqw;margin-top:3cqw">${s.cards.map(c=>KPI(c[0],esc(c[1]))).join('')}</div></div>${foot}`;
 const insL=o.ins!=null?String(o.ins).split('\n').filter(x=>x.trim()):s.ins;
 return `<div class="k">${esc(s.m)} · ${esc(per.toUpperCase())}</div><h3>${esc(title)}</h3><div class="body ${s.cls}">${s.body.join('')}</div>${insL&&insL.length?`<div class="ins"><b>💡 ANALISA</b><ul>${insL.map(x=>`<li>${esc(x)}</li>`).join('')}</ul></div>`:''}${o.own?`<div class="own"><b>✎ TEKS TAMBAHAN</b>${esc(o.own)}</div>`:''}${foot}`}
function vis(){return P.slides.filter(s=>P.on.has(s.id))}
function paint(v){
 const root=v||document.querySelector('.prs');if(!root)return;const vs=vis();let s=P.slides.find(x=>x.id===P.cur)||vs[0];
 if(s&&!P.on.has(s.id)&&vs.length)s=vs[0];if(s)P.cur=s.id;
 const o=(s&&P.ov[s.id])||{};
 root.querySelector('#prSl').innerHTML=s?slideHtml(s):'<div style="margin:auto;color:#5b6b7f;font-weight:700">Belum ada slide dipilih</div>';
 root.querySelector('#prCnt').textContent=vs.length+' dari '+P.slides.length+' slide dipilih';
 root.querySelector('#prPg').textContent=s&&vs.length?String(vs.indexOf(s)+1).padStart(2,'0')+' / '+vs.length:'– / –';
 if(s){root.querySelector('#prT').value=o.t!=null?o.t:s.t;root.querySelector('#prA').value=o.ins!=null?o.ins:(s.ins||[]).join('\n');root.querySelector('#prO').value=o.own||'';root.querySelector('#prN').value=o.note||'';
  const cw=root.querySelector('#prCw');cw.hidden=!s.custom;if(s.custom){root.querySelector('#prC').value=(P.custom.find(c=>c.id===s.id)||{}).body||''}}
 let g='',h='';P.slides.forEach(x=>{if(x.m!==g){g=x.m;const ids=P.slides.filter(y=>y.m===g).map(y=>y.id),all=ids.every(i=>P.on.has(i));h+=`<div class="pmh"><span>${esc(g)}</span><u data-g="${esc(g)}">${all?'LEPAS':'PILIH'} SEMUA</u></div>`}
  h+=`<div class="pit ${s&&x.id===s.id?'on':''} ${P.on.has(x.id)?'':'dim'}" data-i="${x.id}"><i class="${P.on.has(x.id)?'':'off'}" data-c="${x.id}">✓</i><span>${esc(P.ov[x.id]?.t||x.k)}</span><em>${String(P.slides.indexOf(x)+1).padStart(2,'0')}</em></div>`});
 root.querySelector('#prList').innerHTML=h;
}
function step(dir){const vs=vis();if(!vs.length)return;const i=vs.findIndex(x=>x.id===P.cur);P.cur=vs[(i+dir+vs.length)%vs.length].id;paint()}
function setOv(k,val){const id=P.cur;(P.ov[id]=P.ov[id]||{})[k]=val;save()}
function bind(root){
 root.querySelector('#prList').onclick=e=>{const g=e.target.closest('[data-g]');if(g){const ids=P.slides.filter(y=>y.m===g.dataset.g).map(y=>y.id),all=ids.every(i=>P.on.has(i));ids.forEach(i=>all?P.on.delete(i):P.on.add(i));save();paint();return}
  const it=e.target.closest('.pit');if(!it)return;const id=it.dataset.i;if(e.target.dataset.c!==undefined){P.on.has(id)?P.on.delete(id):P.on.add(id)}else{P.cur=id;P.on.add(id)}save();paint()};
 root.querySelector('#prPv').onclick=()=>step(-1);root.querySelector('#prNx').onclick=()=>step(1);
 root.querySelector('#prGo').onclick=()=>{const st=root.querySelector('#prStage');if(st.requestFullscreen)st.requestFullscreen().catch(()=>{});};
 root.querySelectorAll('[data-p]').forEach(b=>b.onclick=()=>{const k=b.dataset.p;P.on=new Set(k==='all'?P.slides.map(s=>s.id):k==='ring'?P.slides.filter(s=>RING.includes(s.id)||s.custom).map(s=>s.id):[]);save();paint()});
 root.querySelector('#prAdd').onclick=()=>{const id='c'+Date.now();P.custom.push({id,t:'Slide teks',body:'Tulis isi slide di sini…'});P.slides=buildAll(P.d);P.on.add(id);P.cur=id;save();paint()};
 const inp=(id,k)=>root.querySelector(id).addEventListener('input',e=>{setOv(k,e.target.value);const sl=root.querySelector('#prSl'),s=P.slides.find(x=>x.id===P.cur);if(s&&k!=='note'){sl.innerHTML=slideHtml(s);root.querySelector('#prList').querySelector('.pit.on span').textContent=P.ov[s.id]?.t||s.k}});
 inp('#prT','t');inp('#prA','ins');inp('#prO','own');inp('#prN','note');
 root.querySelector('#prC').addEventListener('input',e=>{const c=P.custom.find(x=>x.id===P.cur);if(c){c.body=e.target.value;P.slides=buildAll(P.d);root.querySelector('#prSl').innerHTML=slideHtml(P.slides.find(x=>x.id===P.cur));save()}});
 let sx=null;const stage=root.querySelector('#prStage');stage.addEventListener('touchstart',e=>{sx=e.touches[0].clientX},{passive:true});stage.addEventListener('touchend',e=>{if(sx==null)return;const dx=e.changedTouches[0].clientX-sx;if(Math.abs(dx)>50)step(dx<0?1:-1);sx=null});
}
function keys(e){if(!document.querySelector('.prs'))return;if(/INPUT|TEXTAREA/.test((e.target||{}).tagName||''))return;if(e.key==='ArrowRight')step(1);if(e.key==='ArrowLeft')step(-1)}
if(!window._prsKeys){window._prsKeys=1;addEventListener('keydown',keys)}
window.presentasiView=async function(v,seq){
 const key=REPORT.period+'|'+$('scopeSelect').value+'|'+(BOOT?.user?.nik||'');
 if(P.key!==key||!P.d){v.innerHTML='<p class="v6load">Menyiapkan slide…</p>';try{const d=await rpc('apiExec',TOKEN,REPORT.period,$('scopeSelect').value);if(seq!==SEQUENCE)return;P.d=d;P.key=key}catch(e){v.innerHTML='<div class="banner bad">'+esc(e.message)+'</div>';return}}
 load();P.slides=buildAll(P.d);
 const ids=P.slides.map(s=>s.id);P.on=new Set(P.st?P.st.filter(i=>ids.includes(i)):ids);P.cur=P.slides[0]?P.slides[0].id:'';
 if(!P.slides.length){v.innerHTML='<div class="info">Belum ada data untuk cakupan dan periode ini.</div>';return}
 v.innerHTML=`<div class="prs"><div class="pbar"><div><b style="font-size:17px">Presentasi DSM</b><div style="font-size:11.5px;color:#5b6b7f">Periode dan cakupan mengikuti pilihan di atas. Angka sama dengan Beranda.</div></div><div class="sp"></div><div class="pseg"><span class="on">REVIEW BULANAN</span><span class="dis" title="Segera hadir">REVIEW MINGGUAN · SEGERA</span></div><button class="pbtn" id="prGo">⛶ PRESENTASIKAN</button></div>
 <div class="pbar"><span class="pchip" data-p="all">PAKET: LENGKAP</span><span class="pchip" data-p="ring">PAKET: RINGKAS</span><span class="pchip" id="prAdd">+ SLIDE TEKS</span><span class="pchip" data-p="none">KOSONGKAN</span><div class="sp"></div><span class="ppill" id="prCnt"></span></div>
 <div class="pgrid"><div class="plist" id="prList"></div><div class="pprev"><div class="pstage" id="prStage"><div class="slide" id="prSl"></div></div>
 <div class="pnav"><span>✓ Angka langsung dari DSM</span><span><b id="prPv">←</b> <span id="prPg"></span> <b id="prNx">→</b></span><span></span></div>
 <div class="pedit"><label><small>JUDUL SLIDE · BISA DIEDIT</small><input id="prT"></label><label><small>ANALISA · OTOMATIS, BISA DIUBAH (SATU BARIS = SATU POIN)</small><textarea id="prA" rows="3"></textarea></label><label><small>TEKS TAMBAHAN · TAMPIL DI SLIDE</small><textarea id="prO" rows="2" placeholder="Tulis kalimat tambahan yang mau tampil di slide ini…"></textarea></label><label id="prCw"><small>ISI SLIDE TEKS</small><textarea id="prC" rows="4"></textarea></label><label><small>CATATAN PRESENTER · HANYA UNTUK ANDA, TIDAK TAMPIL DI SLIDE · TERSIMPAN DI PERANGKAT INI</small><textarea id="prN" rows="2"></textarea></label></div></div></div></div>`;
 const root=v.querySelector('.prs');bind(root);paint(root);
};
})();
