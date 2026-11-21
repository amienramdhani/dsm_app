/* DSM · Menu TIM desktop (rombak T1+T3, mockup doc/mockup/2026-11-20/dsm-tim-rombak-opsi.html).
   Hanya desktop. HP tetap memakai team() lama. Sumber data: apiEarlyWarning + apiTeam (tidak ada field API baru).
   Istilah di source: "Big Region" = tingkat atas, "Region" = tingkat tengah, "Sub Region" = tingkat bawah
   (region_labels.js menerjemahkan ke REGION / SUB REGION / AREA di layar). */
(function(){
'use strict';
const TOT='__TOTAL__';
const S={bi:0,tab:0,indEW:'ST',indPI:'ST',sel:null,cache:{},pop:false,tm:'w',ct:'b'};
const W={good:'Tembus',warn:'Mengejar',bad:'Tertinggal',na:'Tanpa target'},CL={good:'ok',warn:'warn',bad:'bad',na:'na'};
const COL={good:'#12866A',warn:'#C28A00',bad:'#D2453F',na:'#6B7785'};
const IND={ST:{n:'SELL THRU',t:'target',a:'qty'},DA:{n:'DEALER AKTIF',t:'targetDA',a:'da'},NOO:{n:'NOO',t:'targetNOO',a:'noo'},SO:{n:'SELL OUT',t:null,a:'sellout'},OM:{n:'OMZET',t:null,a:'omzet',rp:true}};
const EWM={ST:'QTY',DA:'DA',NOO:'NOO'},UNIT={QTY:'unit',DA:'dealer',NOO:'NOO'};
const pc=n=>n==null?'—':pct(n);
const nz=n=>Number(n)||0;
const brandOpts=()=>`<option value="ALL" ${brandChoice==='ALL'?'selected':''}>SEMUA BRAND</option>`+REPORT.brands.map(b=>`<option value="${esc(b.brand)}" ${b.brand===brandChoice?'selected':''}>${esc(b.brand)}</option>`).join('');
const brandTxt=()=>brandChoice==='ALL'?'SEMUA BRAND':brandChoice;
function key(){return [REPORT.period,$('scopeSelect').value,teamLevel,JSON.stringify(teamScope)].join('|');}
function cached(k,fn){return S.cache[k]||(S.cache[k]=Promise.resolve().then(fn).catch(err=>{delete S.cache[k];throw err;}));}
function getEW(){const m=EWM[S.indEW]||'QTY';return cached('ew|'+key()+'|'+brandChoice+'|'+m,()=>rpc('apiEarlyWarning',TOKEN,REPORT.period,teamLevel,brandChoice,m,$('scopeSelect').value,teamScope));}
function getPT(){return cached('pt|'+key()+'|'+brandChoice,()=>rpc('apiTeam',TOKEN,REPORT.period,teamLevel,brandChoice,$('scopeSelect').value,teamScope));}
function getPacks(){return cached('pk|'+key(),async()=>{const res=await Promise.all(REPORT.brands.map(b=>rpc('apiTeam',TOKEN,REPORT.period,teamLevel,b.brand,$('scopeSelect').value,teamScope)));return REPORT.brands.map((b,i)=>({brand:b.brand,rows:cleanTeamRows(res[i]),total:(res[i]||[]).find(x=>x.nama==='Total')}));});}
function bossHead(){return teamLevel==='SALES'?'ASM':teamLevel==='ASM'?'RGM':teamLevel==='RGM'?'Head of Sales':teamLevel==='BIG REGION'?'Cakupan':'Big Region';}
const bossTxt=p=>p.boss?esc((p.bossRole&&p.bossRole!=='CAKUPAN'&&p.bossRole!=='BIG REGION'?p.bossRole+' ':'')+p.boss):'—';
function gc(a,l,ok){if(ok===false)return '<span class="tvD">—</span>';a=nz(a);l=nz(l);if(!a&&!l)return '<span class="tvD">—</span>';if(!l)return '<span class="tvGc u">▲ baru</span>';const p=(a-l)/l*100,up=p>=0;return `<span class="tvGc ${up?'u':'d'}">${up?'▲':'▼'} ${pct(Math.abs(p))}</span>`;}
const stChip=s=>`<span class="tvSt ${CL[s]}">${W[s]}</span>`;
const td=(h,c)=>`<td class="${c||'n'}">${h}</td>`;
function fmt(v,ind){return v==null?'—':IND[ind].rp?rupiah(v):num(v);}
/* sel sel: sel dari baris (apiTeam) atau total */
function cellsFor(p,ind,first){const m=IND[ind],f=first?' bsep':'';
 if(!p)return (m.t?['—','—','—','—','—']:['—','—','—']).map((x,i)=>`<td class="n${i===0?f:''}">${x}</td>`).join('');
 const a=p.total?.[m.a],l=p.previous?.[m.a],ok=p.previousAvailable,t=m.t?nz(p[m.t]?.value):0,o=[];
 if(m.t)o.push(`<td class="n${f}">${t>0?num(t):'—'}</td>`,`<td class="n"><b>${num(a)}</b></td>`,`<td class="n">${t>0?pc(a/t*100):'—'}</td>`);
 else o.push(`<td class="n${f}"><b>${fmt(a,ind)}</b></td>`);
 o.push(`<td class="n">${ok?fmt(l,ind):'—'}</td>`,`<td>${gc(a,l,ok)}</td>`);return o.join('');}
function subHead(ind,first){const m=IND[ind],f=first?' bsep':'';return (m.t?['TARGET','AKTUAL','%ACHV','LMTD','GROWTH']:['AKTUAL','LMTD','GROWTH']).map((x,i)=>`<th class="${x==='GROWTH'?'':'n'}${i===0?f:''}">${x}</th>`).join('');}
function ncol(ind){return IND[ind].t?5:3;}
/* tabel per brand (rincian + tab Per Indikator) */
function brandTable(o){const ind=o.ind,brs=o.packs;
 const head=`<tr>${o.lead.map(h=>`<th rowspan="2" class="tvLead">${h}</th>`).join('')}${brs.map((b,i)=>`<th class="bg b${i%6+1} bsep" data-g="${i}" colspan="${ncol(ind)}">${esc(b.brand)}</th>`).join('')}</tr><tr>${brs.map(b=>subHead(ind,true)).join('')}</tr>`;
 const body=o.rows.map(r=>`<tr>${r.lead.map((c,i)=>`<td class="${i?'':'nm'}">${c}</td>`).join('')}${brs.map(b=>cellsFor(r.p[b.brand],ind,true)).join('')}</tr>`).join('');
 const foot=o.total?`<tfoot><tr><td colspan="${o.lead.length}">${o.total.label}</td>${brs.map(b=>cellsFor(b.total,ind,true)).join('')}</tr></tfoot>`:'';
 return `<table class="tvT"><thead>${head}</thead><tbody>${body}</tbody>${foot}</table>`;}
/* ---------- hitungan Early Warning ---------- */
function calc(r){const tg=r.days?r.run/r.days*100:0,left=r.days-r.run;
 const rows=r.rows.map(x=>{const p=x.target?x.actual/x.target*100:null,proj=r.run?x.actual/r.run*r.days:x.actual,pp=x.target?proj/x.target*100:null,rr=r.run?x.actual/r.run:0,need=x.target&&left?Math.max(0,x.target-x.actual)/left:0;return {...x,p,proj,pp,rr,need,st:pp==null?'na':pp>=100?'good':pp>=80?'warn':'bad'};}).sort((a,b)=>(a.pp??999)-(b.pp??999));
 const wt=rows.filter(x=>x.target),T=wt.reduce((n,x)=>n+x.target,0),A=rows.reduce((n,x)=>n+nz(x.actual),0),TA=wt.reduce((n,x)=>n+nz(x.actual),0),PJ=wt.reduce((n,x)=>n+nz(x.proj),0),RR=rows.reduce((n,x)=>n+nz(x.rr),0),gap=rows.reduce((n,x)=>n+Math.max(0,nz(x.target)-nz(x.actual)),0);
 const vs=k=>{const arr=rows.map(x=>x[k]).filter(Array.isArray);if(!arr.length)return null;const n=Math.max(...arr.map(a=>a.length));return Array.from({length:n},(_,i)=>{const v=arr.map(a=>a[i]);return v.every(z=>z==null)?null:v.reduce((m,z)=>m+nz(z),0);});};
 const ppT=T?PJ/T*100:null,tot={nik:TOT,nama:'TOTAL',region:'',boss:'',target:T,targetComplete:rows.every(x=>x.targetComplete!==false),actual:A,p:T?TA/T*100:null,proj:ppT==null&&r.run?A/r.run*r.days:(ppT==null?A:PJ),pp:ppT,rr:RR,need:T&&left?Math.max(0,T-TA)/left:0,st:ppT==null?'na':ppT>=100?'good':ppT>=80?'warn':'bad',dm:vs('dm'),wk:vs('wk'),wkL:vs('wkL')};
 return {r,rows,tot,tg,left,T,A,TA,PJ,RR,gap,tp:T?TA/T*100:null,pp:T?PJ/T*100:null,unit:UNIT[r.metric]||'unit',lv:{SALES:'Sales',ASM:'ASM',RGM:'RGM','BIG REGION':'Big Region',REGION:'Region'}[r.level]||r.level};}
function mint(c){const n=k=>c.rows.filter(x=>x.st===k).length;
 if(isMobile())return `<div class="tvMst"><div><small>Tembus</small><b>${n('good')}/${c.rows.length}</b></div><div class="r"><small>${W.bad}</small><b>${num(n('bad'))}</b></div><div class="y"><small>${W.warn}</small><b>${num(n('warn'))}</b></div><div><small>Gap tim</small><b>${num(c.gap)}</b></div></div>`;
 return `<div class="tvMint"><div><small>Diproyeksikan tembus</small><b>${n('good')} / ${c.rows.length}</b><em>proyeksi ≥ 100% target</em></div><div class="r"><small>${W.bad}</small><b>${num(n('bad'))}</b><em>proyeksi &lt; 80% target</em></div><div class="y"><small>${W.warn}</small><b>${num(n('warn'))}</b><em>proyeksi 80–99%</em></div><div><small>Gap tim</small><b>${num(c.gap)} ${c.unit}</b><em>${c.left?'butuh '+num(c.gap/c.left)+' '+c.unit+'/hari kerja · sisa '+c.left+' hari kerja':'bulan selesai'}</em></div></div>`;}
const DAYN=['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
function dayLbl(d){const t=new Date(String(d).slice(0,10)+'T00:00:00');return isNaN(t)?String(d):DAYN[t.getDay()]+' '+t.getDate();}

/* ---------- tren: per hari (sebulan penuh) / per minggu (vs bulan lalu), batang atau garis ---------- */
const BLN=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'],BLS=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'],HRN=['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
const periodMon=p=>Number(String(p).slice(5,7))-1;
function trenDaily(x,r,color,W,scroll,bar){const md=r.mdays||[],dm=x.dm||[];if(!md.length||!dm.length)return '<div class="empty">Belum ada data harian.</div>';
 const n=md.length,pad=16,band=(W-2*pad)/n,mx=Math.max(...dm,1),H=64,y=v=>H-v/mx*46,xx=i=>pad+band*i+band/2;let lastRun=-1;md.forEach((m,i)=>{if(m[1]===0)lastRun=i;});let g='',pts=[];
 md.forEach((m,i)=>{const v=dm[i],dd=new Date(m[0]+'T00:00:00'),off=m[1]===1,wait=m[1]===2,cx=xx(i);
  if(m[1]===0){if(bar)g+=`<rect x="${cx-band*.31}" y="${y(v)}" width="${band*.62}" height="${H-y(v)}" rx="1.5" fill="${i===lastRun?color:'#E88A85'}"/>`;else{pts.push(cx+','+y(v));g+=`<circle cx="${cx}" cy="${y(v)}" r="${i===lastRun?3.2:2.2}" fill="${i===lastRun?color:'#fff'}" stroke="${color}" stroke-width="1.4"/>`;}g+=`<text x="${cx}" y="${y(v)-4}" text-anchor="middle" font-size="8" font-weight="700" fill="#10394E">${num(v)}</text>`;}
  else if(off)g+=bar?`<rect x="${cx-band*.31}" y="${H-10}" width="${band*.62}" height="10" rx="1.5" fill="#EEF1F5"/><text x="${cx}" y="${H-14}" text-anchor="middle" font-size="7" fill="#9AA6B2">libur</text>`:`<circle cx="${cx}" cy="${H}" r="2" fill="#E3E9EF" stroke="#C5CDD6"/><text x="${cx}" y="${H-7}" text-anchor="middle" font-size="7" fill="#9AA6B2">libur</text>`;
  else g+=`<circle cx="${cx}" cy="${H}" r="2" fill="#fff" stroke="#C5CDD6" stroke-dasharray="1.5 1.5"/>`;
  g+=`<text x="${cx}" y="${H+11}" text-anchor="middle" font-size="8" fill="${dd.getDay()===0?'#C0392B':'#6B7785'}">${dd.getDate()}</text><text x="${cx}" y="${H+20}" text-anchor="middle" font-size="6.5" fill="#9AA6B2">${HRN[dd.getDay()]}</text>`;});
 return `<div class="tvTrSc"><svg viewBox="0 0 ${W} 88" style="display:block;${scroll?'min-width:'+W+'px':'width:100%;max-width:'+Math.round(W*1.08)+'px'}" class="tvTren"><line x1="${pad}" x2="${W-pad}" y1="${H}" y2="${H}" stroke="#D5DDE6"/>${bar?'':`<polyline fill="none" stroke="${color}" stroke-width="1.4" points="${pts.join(' ')}"/>`}${g}</svg></div>${scroll?'<div class="tvCap" style="margin:2px 0">↔ geser ke samping untuk melihat seluruh tanggal</div>':''}`;}
function trenWeekly(x,r,color,W,bar){const ws=r.weeks||[],a=x.wk||[],b=x.wkL||[];if(!ws.length)return '<div class="empty">Belum ada data mingguan.</div>';
 const gy='#8C99A6',n=ws.length,mx=Math.max(...a.filter(v=>v!=null),...b.filter(v=>v!=null),1),H=60,y=v=>H-v/mx*44,wide=W>500;let g='',pa=[],pb=[];
 if(bar){const pad=30,band=(W-2*pad)/n,bw=Math.min(34,band/2.6);ws.forEach((w,i)=>{const cx=pad+band*i+band/2;
  if(b[i]!=null)g+=`<rect x="${cx-bw-1}" y="${y(b[i])}" width="${bw}" height="${H-y(b[i])}" rx="2" fill="#C5CDD6"/><text x="${cx-bw/2-1}" y="${y(b[i])-3}" text-anchor="middle" font-size="9" fill="${gy}">${num(b[i])}</text>`;
  if(a[i]!=null)g+=`<rect x="${cx+1}" y="${y(a[i])}" width="${bw}" height="${H-y(a[i])}" rx="2" fill="${color}"/><text x="${cx+bw/2+1}" y="${y(a[i])-3}" text-anchor="middle" font-size="10" font-weight="700" fill="#10394E">${num(a[i])}</text>`;else g+=`<text x="${cx+bw/2+1}" y="${H-4}" text-anchor="middle" font-size="9" fill="#9AA6B2">belum</text>`;
  g+=`<text x="${cx}" y="76" text-anchor="middle" font-size="9.5" fill="#6B7785">W${w.w}${wide?' · '+w.from+'–'+w.to+' '+BLS[periodMon(r.period||REPORT.period)]:''}</text>`;});
  g=`<line x1="24" x2="${W-24}" y1="${H}" y2="${H}" stroke="#D5DDE6"/>`+g;}
 else{const pad=50,xx=i=>pad+i*(W-2*pad)/Math.max(1,n-1);ws.forEach((w,i)=>{
  if(b[i]!=null){pb.push(xx(i)+','+y(b[i]));g+=`<circle cx="${xx(i)}" cy="${y(b[i])}" r="2.2" fill="#fff" stroke="${gy}"/><text x="${xx(i)}" y="${y(b[i])+11}" text-anchor="middle" font-size="9" fill="${gy}">${num(b[i])}</text>`;}
  if(a[i]!=null){pa.push(xx(i)+','+y(a[i]));g+=`<circle cx="${xx(i)}" cy="${y(a[i])}" r="2.6" fill="#fff" stroke="${color}" stroke-width="1.6"/><text x="${xx(i)}" y="${y(a[i])-7}" text-anchor="middle" font-size="10" font-weight="700" fill="#10394E">${num(a[i])}</text>`;}else g+=`<text x="${xx(i)}" y="${H-7}" text-anchor="middle" font-size="9" fill="#9AA6B2">belum</text>`;
  g+=`<text x="${xx(i)}" y="86" text-anchor="middle" font-size="9.5" fill="#6B7785">W${w.w}${wide?' · '+w.from+'–'+w.to+' '+BLS[periodMon(r.period||REPORT.period)]:''}</text>`;});
  g=`<polyline fill="none" stroke="${gy}" stroke-width="1.4" stroke-dasharray="4 3" points="${pb.join(' ')}"/><polyline fill="none" stroke="${color}" stroke-width="1.6" points="${pa.join(' ')}"/>`+g;}
 const pl=r.prevPeriod?BLN[periodMon(r.prevPeriod)]:'bulan lalu';
 const leg=`<div class="tvLg"><span><i style="background:${color};${bar?'height:8px':''}"></i>Bulan ini</span><span><i style="background:${bar?'#C5CDD6':'transparent'};${bar?'height:8px':'border-top:2px dashed '+gy+';height:0'}"></i>Bulan lalu (${pl})</span></div>`;
 const mon=BLS[periodMon(r.period||REPORT.period)],sumA=a.reduce((m,v)=>m+(v||0),0),sumB=b.reduce((m,v)=>m+(v||0),0),hkS=ws.reduce((m,w)=>m+(w.state==='wait'?0:w.hkRun),0),hasB=b.length>0;
 const rows=ws.map((w,i)=>a[i]==null?`<tr class="run"><td>W${w.w}</td><td>${w.from}–${w.to} ${mon}</td><td class="n">${w.hk}</td><td colspan="4" class="n">belum berjalan</td></tr>`:`<tr><td class="nm">W${w.w}${w.state==='run'?' <small>berjalan</small>':''}</td><td>${w.from}–${w.cap} ${mon}</td><td class="n">${w.hkRun}</td><td class="n"><b>${num(a[i])}</b></td><td class="n">${w.hkRun?num(Math.round(a[i]/w.hkRun)):'—'}</td><td class="n">${hasB?num(b[i]):'—'}</td><td>${hasB?gc(a[i],b[i],true):'—'}</td></tr>`).join('');
 const tb=`<div class="tvSc h0"><table class="tvT"><thead><tr><th>MINGGU</th><th>TANGGAL</th><th class="n">HK</th><th class="n">UNIT</th><th class="n">RATA / HARI</th><th class="n">BULAN LALU</th><th>VS BULAN LALU</th></tr></thead><tbody>${rows}</tbody><tfoot><tr><td>Bulan ini</td><td></td><td class="n">${hkS}</td><td class="n">${num(sumA)}</td><td class="n">${hkS?num(Math.round(sumA/hkS)):'—'}</td><td class="n">${hasB?num(sumB):'—'}</td><td>${hasB?gc(sumA,sumB,true):'—'}</td></tr></tfoot></table></div>`;
 return `<svg viewBox="0 0 ${W} 94" style="display:block;width:100%;max-width:${Math.round(W*1.08)}px" class="tvTren">${g}</svg>${leg}${tb}`;}
function trenBox(x,r,color,mobile){const W=mobile?340:700,Wd=900;
 return `<div class="tvTb"><div class="tvTg"><b>TREN</b><div class="tvSeg2"><button type="button" data-tm="d" class="${S.tm==='d'?'on':''}">PER HARI</button><button type="button" data-tm="w" class="${S.tm==='w'?'on':''}">PER MINGGU</button></div><div class="tvSeg2"><button type="button" data-ct="b" class="${S.ct==='b'?'on':''}">BATANG</button><button type="button" data-ct="l" class="${S.ct==='l'?'on':''}">GARIS</button></div><small>${S.tm==='d'?'unit per hari · sebulan penuh':'unit per minggu · dibanding bulan lalu'}</small></div>${S.tm==='d'?trenDaily(x,r,color,Wd,mobile,S.ct==='b'):trenWeekly(x,r,color,W,S.ct==='b')}</div>`;}
function bindTren(root,x,r,color,mobile){root.querySelectorAll('[data-tm],[data-ct]').forEach(b=>b.onclick=()=>{if(b.dataset.tm)S.tm=b.dataset.tm;if(b.dataset.ct)S.ct=b.dataset.ct;const box=root.querySelector('.tvTb');if(!box)return;const t=document.createElement('div');t.innerHTML=trenBox(x,r,color,mobile);box.replaceWith(t.firstChild);bindTren(root,x,r,color,mobile);});}
function totHead(c){const sc=teamScope.sub!=='ALL'?teamScope.sub:teamScope.region!=='ALL'?teamScope.region:teamScope.big!=='ALL'?teamScope.big:null,me=BOOT.user||{};
 if(!sc)return {t:'AREA SAYA <span class="tvTotB">TOTAL</span>',s:esc([me.role,String(me.nama||'').toUpperCase()].filter(Boolean).join(' '))+' · '+c.rows.length+' '+esc(c.lv)};
 return {t:'TOTAL · '+esc(sc),s:c.rows.length+' '+esc(c.lv)};}
const isAct=x=>teamLevel==='SALES'&&(x.acting||(x.role&&x.role!=='SALES'));
const nmH=x=>isAct(x)?`<span class="tvAct">${esc(x.nama)}</span>`:esc(x.nama);
const canDetail=x=>x.nik&&x.nik!==TOT&&!String(x.nik).startsWith('AREA:');
const kbtn=x=>canDetail(x)?`<button type="button" class="tvBtn" data-tvp="${esc(x.nik)}">LIHAT DETAIL KPI</button>`:'';
/* ---------- kerangka ---------- */
function filters(){
 const levels=allowedTeamLevels(),sales=(BOOT.people||[]).filter(p=>p.sales),bigs=sortedUnique(sales.map(p=>p.wilayah));
 if(teamScope.big!=='ALL'&&!bigs.includes(teamScope.big))teamScope.big='ALL';
 const areaSales=sales.filter(p=>teamScope.big==='ALL'||p.wilayah===teamScope.big),regions=sortedUnique(areaSales.map(p=>p.portfolio||p.region));
 if(teamScope.region!=='ALL'&&!regions.includes(teamScope.region))teamScope.region='ALL';
 const regionSales=areaSales.filter(p=>teamScope.region==='ALL'||(p.portfolio||p.region)===teamScope.region),subs=sortedUnique((REPORT.customers||[]).filter(c=>(teamScope.big==='ALL'||c.bigRegion===teamScope.big)&&(teamScope.region==='ALL'||c.subRegion===teamScope.region)).map(c=>c.subRegion).concat(regionSales.map(p=>p.portfolio||p.region)));
 if(teamScope.sub!=='ALL'&&!subs.includes(teamScope.sub))teamScope.sub='ALL';
 const act=(teamScope.sub!=='ALL'?1:0);
 if(isMobile()){const n=(teamScope.big!=='ALL'?1:0)+(teamScope.region!=='ALL'?1:0)+act,ch=[`<span>${esc(brandTxt())}</span>`,`<span>${esc(teamLevel)}</span>`].concat([['big',teamScope.big],['region',teamScope.region],['sub',teamScope.sub]].filter(x=>x[1]!=='ALL').map(x=>`<span>${esc(x[1])} <a data-tvx="${x[0]}">✕</a></span>`));
  return `<div class="tvM"><button type="button" class="tvFbtn full" id="tvFl">FILTER <i>${n}</i></button><div class="tvFp" id="tvPop" ${S.pop?'':'hidden'}><label>Tingkat<select class="fi" id="teamLevel">${levels.map(l=>`<option ${l===teamLevel?'selected':''}>${l}</option>`).join('')}</select></label><label>Brand<select class="fi" id="teamBrand">${brandOpts()}</select></label><label>Big Region<select class="fi" id="teamBig">${filterOptions(bigs,teamScope.big,'Semua Big Region')}</select></label><label>Region<select class="fi" id="teamRegion">${filterOptions(regions,teamScope.region,'Semua Region')}</select></label><label>Sub Region<select class="fi" id="teamSub">${filterOptions(subs,teamScope.sub,'Semua Sub Region')}</select></label><button type="button" class="tvReset" id="tvReset">RESET</button></div><div class="tvChips">${ch.join('')}</div></div>`;}
 return `<div class="tvFb"><label>Tingkat<select class="fi" id="teamLevel">${levels.map(l=>`<option ${l===teamLevel?'selected':''}>${l}</option>`).join('')}</select></label><label>Brand<select class="fi" id="teamBrand">${brandOpts()}</select></label><label>Big Region<select class="fi" id="teamBig">${filterOptions(bigs,teamScope.big,'Semua Big Region')}</select></label><label>Region<select class="fi" id="teamRegion">${filterOptions(regions,teamScope.region,'Semua Region')}</select></label><span class="tvSp"></span><span class="tvPopW"><button type="button" class="tvFbtn" id="tvFl">FILTER LAIN${act?' · '+act:''}</button><div class="tvPop" id="tvPop" ${S.pop?'':'hidden'}><label>Sub Region<select class="fi" id="teamSub">${filterOptions(subs,teamScope.sub,'Semua Sub Region')}</select></label><button type="button" class="tvReset" id="tvReset">RESET</button></div></span></div>`;}
function bindFilters(){
 const go=()=>render();
 $('teamLevel').onchange=e=>{teamLevel=e.target.value;S.sel=null;go();};$('teamBrand').onchange=e=>{brandChoice=e.target.value;go();};
 $('teamBig').onchange=e=>{teamScope.big=e.target.value;teamScope.region='ALL';teamScope.sub='ALL';S.sel=null;go();};
 $('teamRegion').onchange=e=>{teamScope.region=e.target.value;teamScope.sub='ALL';S.sel=null;go();};
 $('teamSub').onchange=e=>{teamScope.sub=e.target.value;S.sel=null;go();};
 $('tvFl').onclick=()=>{S.pop=!S.pop;$('tvPop').hidden=!S.pop;};
 document.querySelectorAll('[data-tvx]').forEach(a=>a.onclick=()=>{const k=a.dataset.tvx;teamScope[k]='ALL';if(k==='big'){teamScope.region='ALL';teamScope.sub='ALL';}if(k==='region')teamScope.sub='ALL';S.sel=null;go();});
 $('tvReset').onclick=()=>{teamScope.big='ALL';teamScope.region='ALL';teamScope.sub='ALL';S.sel=null;S.pop=false;go();};}
function tabsHtml(){const mb=isMobile(),t=mb?['EARLY WARNING','PENCAPAIAN','INDIKATOR']:['EARLY WARNING','PENCAPAIAN TIM','PER INDIKATOR'],ew=S.tab===0,pi=S.tab===2;
 const seg=(ind,list,cur)=>`<div class="tvSeg"><small>${mb?'':'INDIKATOR'}</small>${list.map(k=>`<button type="button" class="${k===cur?'on':''}" data-tvi="${k}">${IND[k].n}</button>`).join('')}</div>`;
 return `<div class="tvTabs">${t.map((x,i)=>`<button type="button" class="${i===S.tab?'on':''}" data-tvt="${i}">${x}</button>`).join('')}<span class="tvSp"></span>${mb?'':ew?seg('ew',['ST','DA','NOO'],S.indEW):pi?seg('pi',['ST','DA','NOO','SO','OM'],S.indPI):'<small class="tvNote">semua indikator tampil sekaligus</small>'}</div>${mb?(ew?seg('ew',['ST','DA','NOO'],S.indEW):pi?seg('pi',['ST','DA','NOO','SO','OM'],S.indPI):''):''}`;}
window.timV2=async function(v,seq){
 const levels=allowedTeamLevels();if(!levels.includes(teamLevel))teamLevel=levels[0]||'SALES';
 if(!S.bi){brandChoice='ALL';S.bi=1;}else if(brandChoice!=='ALL'&&!REPORT.brands.some(b=>b.brand===brandChoice))brandChoice='ALL';
 v.innerHTML=`<div class="tvRoot">${filters()}<div id="tvMint"></div><div id="tvTabs"></div><div id="tvBody"><div class="empty v6load">Memuat tim…</div></div></div>`;
 bindFilters();
 const ctx={v,seq};window._tvCtx=ctx;
 if(isMobile())$('tvMint').onclick=()=>{if(ctx.c)sheet(ctx,ctx.c,TOT);};
 drawTabs(ctx);
 try{const r=await getEW();if(seq!==SEQUENCE||!$('tvMint'))return;const c=calc(r);ctx.c=c;$('tvMint').innerHTML=mint(c);}catch(e){if($('tvMint'))$('tvMint').innerHTML='<div class="banner bad">'+esc(e.message)+'</div>';}
 await drawBody(ctx);};
function drawTabs(ctx){const el=$('tvTabs');if(!el)return;el.innerHTML=tabsHtml();
 el.querySelectorAll('[data-tvt]').forEach(b=>b.onclick=()=>{S.tab=+b.dataset.tvt;drawTabs(ctx);drawBody(ctx);});
 el.querySelectorAll('[data-tvi]').forEach(b=>b.onclick=()=>{if(S.tab===0)S.indEW=b.dataset.tvi;else S.indPI=b.dataset.tvi;drawTabs(ctx);if(S.tab===0){refreshMint(ctx).then(()=>drawBody(ctx));}else drawBody(ctx);});}
async function refreshMint(ctx){try{const r=await getEW();if(ctx.seq!==SEQUENCE||!$('tvMint'))return;ctx.c=calc(r);$('tvMint').innerHTML=mint(ctx.c);}catch(e){}}
async function drawBody(ctx){const el=$('tvBody');if(!el)return;const seq=ctx.seq,tab=S.tab;el.innerHTML='<div class="empty v6load">Memuat…</div>';
 try{
  if(tab===0){const r=await getEW();if(seq!==SEQUENCE||tab!==S.tab||!$('tvBody'))return;ctx.c=calc(r);$('tvMint').innerHTML=mint(ctx.c);drawEW(ctx);}
  else if(tab===1){const rows=await getPT();if(seq!==SEQUENCE||tab!==S.tab||!$('tvBody'))return;drawPT(rows);}
  else{const packs=await getPacks();if(seq!==SEQUENCE||tab!==S.tab||!$('tvBody'))return;drawPI(packs);}
 }catch(e){if($('tvBody'))$('tvBody').innerHTML='<div class="banner bad">'+esc(e.message)+'</div>';}}
function bindPerson(root){root.querySelectorAll('[data-tvp]').forEach(b=>b.onclick=ev=>{ev.stopPropagation();person(b.dataset.tvp);});}
/* ---------- tab EARLY WARNING ---------- */
function drawEW(ctx){const c=ctx.c,el=$('tvBody');if(!c.rows.length){el.innerHTML='<div class="empty">Tidak ada data pada cakupan ini.</div>';return;}
 const top=c.rows.filter(x=>x.st!=='na').slice(0,5);if(S.sel&&!c.rows.some(x=>x.nik===S.sel))S.sel=null;
 const isT=!S.sel,s=isT?c.tot:c.rows.find(x=>x.nik===S.sel),r=c.r,th=totHead(c);
 const list=top.map((x,i)=>`<div class="${!isT&&x.nik===S.sel?'on':''}" data-tvs="${esc(x.nik)}"><span>${i+1}</span><b>${nmH(x)}</b><span>${pc(x.p)}</span>${stChip(x.st)}</div>`).join('');
 const rowsF=c.rows.map((x,i)=>`<tr class="cl${x.nik===S.sel?' sel':''}" data-tvs="${esc(x.nik)}" data-up="1"><td>${i+1}</td><td><b class="lk">${nmH(x)}</b></td><td>${bossTxt(x)}</td><td>${esc(x.region)}</td><td class="n">${x.target?num(x.target)+(x.targetComplete?'':' *'):'—'}</td><td class="n"><b>${num(x.actual)}</b></td><td>${x.p==null?'—':`<div class="tvAch">${v6Mini(x.p,c.tg)}<b>${pct(x.p)}</b></div>`}</td><td class="n">${x.pp==null?num(x.proj):pct(x.pp)}</td><td class="n">${decimal(x.rr)}</td><td class="n">${!x.target?'—':nz(x.actual)>=x.target?'✓':c.left?num(x.need):'—'}</td><td>${stChip(x.st)}</td><td>${kbtn(x)}</td></tr>`).join('');
 const foot=`<tfoot><tr class="cl${isT?' sel':''}" data-tvs="${TOT}" data-up="1"><td></td><td>Total · ${c.rows.length} ${esc(c.lv)} <span class="tvHintI">klik → rincian area saya</span></td><td></td><td></td><td class="n">${c.T?num(c.T):'—'}</td><td class="n">${num(c.A)}</td><td>${c.tp==null?'—':pct(c.tp)}</td><td class="n">${c.pp==null?'—':pct(c.pp)}</td><td class="n">${c.RR?decimal(c.RR):'—'}</td><td class="n">${c.left&&c.T?num(Math.max(0,c.T-c.TA)/c.left):'—'}</td><td></td><td></td></tr></tfoot>`;
 if(isMobile()){drawEWm(ctx,c,top);return;}
 el.innerHTML=`<div class="tvMd"><div class="tvCard tvList"><div class="tvPh"><b>URUT RISIKO</b><span class="tvSp"></span><small>${top.length} teratas dari ${c.rows.length} ${esc(c.lv)}</small></div>${list}</div><div class="tvCard tvDt" id="tvDt"><div class="tvDh"><div><h3>${isT?th.t:nmH(s)}</h3><small>${isT?th.s:bossTxt(s)+' · '+esc(s.region)}</small></div><span class="tvSp"></span>${isT?'':`<button type="button" class="tvBack" data-tvs="${TOT}" data-up="1">← AREA SAYA</button>`}${kbtn(s)}</div><div class="tvKc"><div><small>TARGET</small><b>${s.target?num(s.target):'—'}</b></div><div><small>AKTUAL</small><b>${num(s.actual)}</b></div><div><small>PROYEKSI</small><b style="color:${COL[s.st]}">${s.pp==null?num(s.proj):pct(s.pp)}</b></div><div><small>RUN RATE</small><b>${decimal(s.rr)}</b></div><div><small>BUTUH / HARI</small><b>${!s.target?'—':nz(s.actual)>=s.target?'✓':c.left?num(s.need):'—'}</b></div></div>${trenBox(s,r,COL[s.st],false)}<div class="tvCap" style="margin-top:10px">PER BRAND · ${IND[S.indEW].n}</div><div id="tvDetBr" class="tvSc h0"><div class="empty v6load">Memuat rincian per brand…</div></div><div class="tvHint">↔ geser ke samping untuk melihat semua brand · kolom ${esc(c.lv).toUpperCase()} tetap menempel</div></div></div><div class="tvCard tvAll"><div class="tvPh"><b>SEMUA ${esc(c.lv).toUpperCase()} · TABEL LENGKAP</b><small>klik nama → rincian muncul di atas</small><span class="tvSp"></span><small>time gone ${pct(c.tg)} · sisa ${c.left} hari kerja</small></div><div class="tvSc tvFull"><table class="tvT"><thead><tr><th>#</th><th>${esc(c.lv).toUpperCase()}</th><th>${esc(bossHead()).toUpperCase()}</th><th>Region</th><th class="n">TARGET</th><th class="n">AKTUAL</th><th>ACH VS TIME GONE</th><th class="n">PROYEKSI</th><th class="n">RUN RATE</th><th class="n">BUTUH / HARI</th><th>STATUS</th><th></th></tr></thead><tbody>${rowsF}</tbody>${foot}</table></div><div class="tvNoteB">Urut dari yang paling jauh dari target. <b>Proyeksi</b> = aktual ÷ ${r.run} hari kerja × ${r.days} hari kerja (Minggu &amp; Hari_Libur tidak dihitung). ${r.metric!=='QTY'?'Proyeksi '+IND[S.indEW].n+' bersifat perkiraan. ':''}* target belum lengkap.</div></div>`;
 el.querySelectorAll('[data-tvs]').forEach(b=>b.onclick=()=>{S.sel=b.dataset.tvs===TOT?null:b.dataset.tvs;drawEW(ctx);if(b.dataset.up)$('tvDt').scrollIntoView({behavior:'smooth',block:'center'});});
 bindPerson(el);bindTren(el,s,r,COL[s.st],false);
 getPacks().then(packs=>{const box=$('tvDetBr');if(!box||ctx.seq!==SEQUENCE||(S.sel||TOT)!==s.nik)return;
  const pp={};packs.forEach(b=>{pp[b.brand]=isT?b.total:b.rows.find(x=>x.nik===s.nik);});
  box.innerHTML=brandTable({ind:S.indEW,packs,lead:[isT?'CAKUPAN':esc(c.lv).toUpperCase()],rows:[{lead:['<b>'+(isT?'TOTAL AREA':nmH(s))+'</b>'],p:pp}],total:null});
 }).catch(e=>{const box=$('tvDetBr');if(box)box.innerHTML='<div class="empty">'+esc(e.message)+'</div>';});}
/* ---------- HP: Early Warning tabel padat + lembar rincian ---------- */
function drawEWm(ctx,c,top){const el=$('tvBody'),r=c.r;
 const rows=c.rows.map((x,i)=>`<tr data-tvs="${esc(x.nik)}"><td><b>${nmH(x)}</b></td><td class="n">${x.target?num(x.target):'—'}</td><td class="n"><b>${num(x.actual)}</b></td><td class="n">${pc(x.p)}</td><td class="n">${x.pp==null?num(x.proj):pct(x.pp)}</td><td class="n">${decimal(x.rr)}</td><td class="n">${!x.target?'—':nz(x.actual)>=x.target?'✓':c.left?num(x.need):'—'}</td><td>${stChip(x.st)}</td></tr>`).join('');
 const foot=`<tfoot><tr class="cl" data-tvs="${TOT}"><td>TOTAL · ${c.rows.length} <span class="tvHintI">ketuk</span></td><td class="n">${c.T?num(c.T):'—'}</td><td class="n">${num(c.A)}</td><td class="n">${c.tp==null?'—':pct(c.tp)}</td><td class="n">${c.pp==null?'—':pct(c.pp)}</td><td class="n">${c.RR?decimal(c.RR):'—'}</td><td class="n">${c.left&&c.T?num(Math.max(0,c.T-c.TA)/c.left):'—'}</td><td></td></tr></tfoot>`;
 el.innerHTML=`<div class="tvCap">URUT RISIKO · geser ke samping ↔ · ketuk baris untuk rincian</div><div class="tvCard"><div class="tvSc"><table class="tvT"><thead><tr><th class="tvLead">${esc(c.lv).toUpperCase()}</th><th class="n">TARGET</th><th class="n">AKTUAL</th><th class="n">ACH</th><th class="n">PROY</th><th class="n">RUN</th><th class="n">BUTUH</th><th>STATUS</th></tr></thead><tbody>${rows}</tbody>${foot}</table></div></div><div class="tvNoteB">time gone ${pct(c.tg)} · sisa ${c.left} hari kerja. <b>Proyeksi</b> = aktual ÷ ${r.run} hari kerja × ${r.days} hari kerja.</div>`;
 el.querySelectorAll('[data-tvs]').forEach(b=>b.onclick=()=>sheet(ctx,c,b.dataset.tvs));}
function sheet(ctx,c,nik){const isT=nik===TOT,s=isT?c.tot:c.rows.find(x=>x.nik===nik);if(!s)return;const th=totHead(c);let sh=$('tvSheet');if(!sh){sh=document.createElement('div');sh.id='tvSheet';sh.className='tvSheet';document.body.appendChild(sh);}
 let bg=$('tvSheetBg');if(!bg){bg=document.createElement('div');bg.id='tvSheetBg';bg.className='tvSheetBg';document.body.appendChild(bg);}
 const close=()=>{sh.classList.remove('on');bg.classList.remove('on');};bg.onclick=close;
 sh.innerHTML=`<div class="tvGrip"></div><div class="tvDh"><div><h3>${isT?th.t:nmH(s)}</h3><small>${isT?th.s:bossTxt(s)+' · '+esc(s.region)}</small></div>${isT?'':`<span class="tvSp"></span><button type="button" class="tvBack" id="tvShBack">← AREA SAYA</button>`}</div><div class="tvKc m"><div><small>TARGET</small><b>${s.target?num(s.target):'—'}</b></div><div><small>AKTUAL</small><b>${num(s.actual)}</b></div><div><small>PROYEKSI</small><b style="color:${COL[s.st]}">${s.pp==null?num(s.proj):pct(s.pp)}</b></div><div><small>RUN RATE</small><b>${decimal(s.rr)}</b></div><div><small>BUTUH / HARI</small><b>${!s.target?'—':nz(s.actual)>=s.target?'✓':c.left?num(s.need):'—'}</b></div><div><small>ACH</small><b>${pc(s.p)}</b></div></div>${trenBox(s,c.r,COL[s.st],true)}<div class="tvCap">PER BRAND · ${IND[S.indEW].n}</div><div id="tvShBr" class="tvSc h0"><div class="empty v6load">Memuat…</div></div>${kbtn(s)?kbtn(s).replace('class="tvBtn"','class="tvBtn full"'):''}`;
 bg.classList.add('on');sh.classList.add('on');bindPerson(sh);bindTren(sh,s,c.r,COL[s.st],true);if($('tvShBack'))$('tvShBack').onclick=()=>sheet(ctx,c,TOT);sh.querySelectorAll('[data-tvp]').forEach(b=>{const f=b.onclick;b.onclick=ev=>{close();f&&f(ev);};});
 getPacks().then(packs=>{const box=$('tvShBr');if(!box)return;const m=IND[S.indEW];box.innerHTML=`<table class="tvT"><thead><tr><th class="tvLead">BRAND</th>${(m.t?['TARGET','AKTUAL','%ACHV','LMTD','GROWTH']:['AKTUAL','LMTD','GROWTH']).map(x=>`<th class="${x==='GROWTH'?'':'n'}">${x}</th>`).join('')}</tr></thead><tbody>${packs.map(b=>{const p=isT?b.total:b.rows.find(x=>x.nik===nik);return `<tr><td class="nm">${esc(b.brand)}</td>${cellsFor(p,S.indEW,false)}</tr>`;}).join('')}</tbody></table>`;}).catch(e=>{const box=$('tvShBr');if(box)box.innerHTML='<div class="empty">'+esc(e.message)+'</div>';});}
function jumpChips(list,cur){return `<div class="tvJump">${list.map((x,i)=>`<button type="button" data-j="${i}" class="${i===0?'on':''}">${x}</button>`).join('')}</div><div class="tvCap" style="margin:0 0 6px">↔ geser ke samping · ketuk chip untuk loncat · kolom pertama menempel</div>`;}
function bindJump(el){const sc=el.querySelector('.tvSc');if(!sc)return;el.querySelectorAll('[data-j]').forEach(b=>b.onclick=()=>{const th=el.querySelector('th[data-g="'+b.dataset.j+'"]'),f=sc.querySelector('th.tvLead');if(!th)return;sc.scrollTo({left:Math.max(0,th.offsetLeft-(f?f.offsetWidth:0)),behavior:'smooth'});el.querySelectorAll('[data-j]').forEach(x=>x.classList.toggle('on',x===b));});}
/* ---------- tab PENCAPAIAN TIM ---------- */
function drawPT(rowsAll){const total=rowsAll.find(x=>x.nama==='Total'),rows=cleanTeamRows(rowsAll),el=$('tvBody');
 if(!rows.length){el.innerHTML='<div class="empty">Tidak ada data pada cakupan ini.</div>';return;}
 let gi=0;const grp=(t,c,n,cls)=>`<th class="bg ${cls} bsep" data-g="${gi++}" colspan="${n}">${t}</th>`;
 const head=`<tr><th rowspan="2" class="tvLead">NAMA</th>${isMobile()?'':`<th rowspan="2">${esc(bossHead()).toUpperCase()}</th><th rowspan="2">Region</th>`}${grp('SELL THRU','',5,'b1')}${grp('DEALER AKTIF','',5,'b2')}${grp('NOO','',2,'b3')}${grp('SELL OUT','',1,'b4')}${grp('OMZET','',1,'bo')}</tr><tr>${subHead('ST',true)}${subHead('DA',true)}<th class="n bsep">TARGET</th><th class="n">AKTUAL</th><th class="n bsep">AKTUAL</th><th class="n bsep">AKTUAL</th></tr>`;
 /* SO & OMZET hanya kolom AKTUAL */
 const only=(p,ind)=>`<td class="n bsep"><b>${fmt(p.total?.[IND[ind].a],ind)}</b></td>`;
 const rowCells=p=>cellsFor(p,'ST',true)+cellsFor(p,'DA',true)+(()=>{const tt=nz(p.targetNOO?.value);return `<td class="n bsep">${tt>0?num(tt):'—'}</td><td class="n"><b>${num(p.total?.noo)}</b></td>`;})()+only(p,'SO')+only(p,'OM');
 const body=rows.map(p=>`<tr><td class="nm">${nmH(p)}</td>${isMobile()?'':`<td>${bossTxt(p)}</td><td>${esc(p.region)}</td>`}${rowCells(p)}</tr>`).join('');
 const foot=total?`<tfoot><tr><td colspan="${isMobile()?1:3}">Total · ${rows.length} ${esc(teamLevel==='SALES'?'Sales':teamLevel)}</td>${rowCells(total)}</tr></tfoot>`:'';
 el.innerHTML=(isMobile()?jumpChips(['SELL THRU','DEALER AKTIF','NOO','SELL OUT','OMZET']):'')+`<div class="tvCard"><div class="tvSc"><table class="tvT"><thead>${head}</thead><tbody>${body}</tbody>${foot}</table></div>${isMobile()?'':'<div class="tvHint tvPad">↔ geser ke samping jika tabel melebar · kolom pertama tetap menempel</div>'}</div>`;if(isMobile())bindJump(el);}
/* ---------- tab PER INDIKATOR ---------- */
function drawPI(packs){const el=$('tvBody'),map=new Map();
 packs.forEach(b=>b.rows.forEach(p=>{if(!map.has(p.nik))map.set(p.nik,{p:{},lead:p});map.get(p.nik).p[b.brand]=p;}));
 const ord=[...map.values()].sort((a,b)=>String(a.lead.nama).localeCompare(String(b.lead.nama),'id'));
 if(!ord.length){el.innerHTML='<div class="empty">Tidak ada data pada cakupan ini.</div>';return;}
 const lv=teamLevel==='SALES'?'SALES':teamLevel;
 const mbl=isMobile(),rows=ord.map(o=>({lead:mbl?['<b>'+nmH(o.lead)+'</b>']:['<b>'+nmH(o.lead)+'</b>',bossTxt(o.lead),esc(o.lead.region)],p:o.p}));
 el.innerHTML=(isMobile()?jumpChips(packs.map(b=>esc(b.brand))):'')+`<div class="tvCard"><div class="tvSc">${brandTable({ind:S.indPI,packs,lead:mbl?[esc(lv)]:[esc(lv),esc(bossHead()).toUpperCase(),'Region'],rows,total:{label:'Total · '+ord.length+' '+esc(lv==='SALES'?'Sales':lv)}})}</div>${isMobile()?'':`<div class="tvHint tvPad">${IND[S.indPI].n} · ↔ geser ke samping jika tabel melebar · kolom pertama tetap menempel</div>`}</div>`;if(isMobile())bindJump(el);}
})();
