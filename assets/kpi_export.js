/* Export KPI Excel (Admin/Super Admin): 9 sheet, kolom dinamis sesuai kebijakan KPI, rumus hidup. */
(function(){
const COLS=n=>{let s='';while(n>0){n--;s=String.fromCharCode(65+n%26)+s;n=Math.floor(n/26);}return s;};
const X=v=>String(v??'').replace(/[\x00-\x08\x0b\x0c\x0e-\x1f]/g,'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const PAL=[['1E5FB8','EEF4FF'],['0E7C6B','E8F6F3'],['6D3FD8','F3EEFF'],['C25A12','FFF1E6']];
const BORDER_THIN='C9D3E0',BORDER_MED='153D50';

function Styles(){
 const fonts=['<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'],fills=['<fill><patternFill patternType="none"/></fill>','<fill><patternFill patternType="gray125"/></fill>'],borders=['<border><left/><right/><top/><bottom/><diagonal/></border>'],fmts=[],xfs=['<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'],cache={};
 const idx=(a,x)=>{let i=a.indexOf(x);if(i<0){a.push(x);i=a.length-1;}return i;};
 const side=(n,st)=>st?`<${n} style="${st}"><color rgb="FF${st==='medium'?BORDER_MED:BORDER_THIN}"/></${n}>`:`<${n}/>`;
 this.get=sp=>{sp=sp||{};const key=JSON.stringify(sp);if(cache[key]!==undefined)return cache[key];
  const font=idx(fonts,`<font>${sp.b?'<b/>':''}<sz val="${sp.sz||11}"/>${sp.color?`<color rgb="FF${sp.color}"/>`:''}<name val="Calibri"/><family val="2"/></font>`);
  const fill=sp.fill?idx(fills,`<fill><patternFill patternType="solid"><fgColor rgb="FF${sp.fill}"/><bgColor indexed="64"/></patternFill></fill>`):0;
  const bd=idx(borders,`<border>${side('left',sp.bl)}${side('right',sp.br)}${side('top',sp.bt)}${side('bottom',sp.bb)}<diagonal/></border>`);
  let nf=0;if(sp.fmt==='#,##0')nf=3;else if(sp.fmt==='0%')nf=9;else if(sp.fmt){let i=fmts.indexOf(sp.fmt);if(i<0){fmts.push(sp.fmt);i=fmts.length-1;}nf=164+i;}
  const al=`<alignment horizontal="${sp.h||'general'}" vertical="center"${sp.wrap?' wrapText="1"':''}/>`;
  xfs.push(`<xf numFmtId="${nf}" fontId="${font}" fillId="${fill}" borderId="${bd}" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">${al}</xf>`);
  return cache[key]=xfs.length-1;};
 this.xml=()=>`<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">${fmts.length?`<numFmts count="${fmts.length}">${fmts.map((f,i)=>`<numFmt numFmtId="${164+i}" formatCode="${X(f)}"/>`).join('')}</numFmts>`:''}<fonts count="${fonts.length}">${fonts.join('')}</fonts><fills count="${fills.length}">${fills.join('')}</fills><borders count="${borders.length}">${borders.join('')}</borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="${xfs.length}">${xfs.join('')}</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>`;
}
function Sheet(st){
 this.cells={};this.widths={};this.cf=[];this.freeze=null;this.filter=null;
 this.set=(r,c,v,sp)=>{sp=sp||{};this.cells[r+','+c]={r,c,v,s:st.get(sp),sp};};
 this.style=(r,c,patch)=>{const k=r+','+c,x=this.cells[k];const base=x?x.sp||{}:{};const sp=Object.assign({},base,patch);if(x){x.sp=sp;x.s=st.get(sp);}else this.cells[k]={r,c,v:null,s:st.get(sp),sp};};
 this.setS=this.set;
 this.xml=()=>{const rows={};Object.values(this.cells).forEach(x=>(rows[x.r]=rows[x.r]||[]).push(x));
  const rs=Object.keys(rows).map(Number).sort((a,b)=>a-b);
  const data=rs.map(r=>`<row r="${r}">${rows[r].sort((a,b)=>a.c-b.c).map(x=>{const ref=COLS(x.c)+x.r;if(x.v&&typeof x.v==='object')return `<c r="${ref}" s="${x.s}"><f>${X(x.v.f)}</f></c>`;if(x.v===null||x.v===undefined||x.v==='')return `<c r="${ref}" s="${x.s}"/>`;if(typeof x.v==='number'&&Number.isFinite(x.v))return `<c r="${ref}" s="${x.s}"><v>${x.v}</v></c>`;return `<c r="${ref}" s="${x.s}" t="inlineStr"><is><t xml:space="preserve">${X(x.v)}</t></is></c>`;}).join('')}</row>`).join('');
  const cols=Object.keys(this.widths).map(Number).sort((a,b)=>a-b).map(c=>`<col min="${c}" max="${c}" width="${this.widths[c]}" customWidth="1"/>`).join('');
  const fz=this.freeze?(this.freeze.c>1?`<pane xSplit="${this.freeze.c-1}" ySplit="${this.freeze.r-1}" topLeftCell="${COLS(this.freeze.c)}${this.freeze.r}" activePane="bottomRight" state="frozen"/>`:`<pane ySplit="${this.freeze.r-1}" topLeftCell="A${this.freeze.r}" activePane="bottomLeft" state="frozen"/>`):'';
  let pr=1;const cf=this.cf.map(g=>{const chunks=[];for(let i=0;i<g.length;i+=200)chunks.push(g.slice(i,i+200));return chunks.map(ch=>`<conditionalFormatting sqref="${ch.join(' ')}"><cfRule type="iconSet" priority="${pr++}"><iconSet iconSet="3Arrows"><cfvo type="num" val="-1000000000"/><cfvo type="num" val="-0.0000001"/><cfvo type="num" val="0.0000001"/></iconSet></cfRule></conditionalFormatting>`).join('');}).join('');
  return `<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView showGridLines="0" workbookViewId="0">${fz}</sheetView></sheetViews><sheetFormatPr defaultRowHeight="15"/>${cols?`<cols>${cols}</cols>`:''}<sheetData>${data}</sheetData>${this.filter?`<autoFilter ref="${this.filter}"/>`:''}${cf}</worksheet>`;};
}
const num=n=>n==null||!Number.isFinite(Number(n))?0:Number(n);
const ref=(r,c)=>COLS(c)+r;
function title(sh,t,sub){sh.set(2,2,t,{b:1,sz:16,color:'153D50'});sh.set(3,2,sub,{color:'6B7C8D'});}
const HB={b:1,fill:'B4C6E7',bl:'thin',br:'thin',bt:'thin',bb:'thin',h:'center'};
const CELL={bl:'thin',br:'thin',bt:'thin',bb:'thin'};

/* ---------- Tabel KPI per kelompok wilayah (Sales, ASM, Telemarketing) ---------- */
function unionCols(list){const groups=[],gi={};list.forEach(p=>(p.kpi.groups||[]).forEach(g=>{let G=gi[g.name];if(!G){G=gi[g.name]={name:g.name,inds:[],map:{}};groups.push(G);}g.indicators.forEach(i=>{if(!G.map[i.label]){G.map[i.label]={label:i.label,weight:num(i.weight),cap:num(i.cap)||120};G.inds.push(G.map[i.label]);}});}));return groups;}
function kpiBlock(sh,r,first,rows,rowVals,label){
 const groups=unionCols(rows),nf=first.length,c0=2;
 if(label){sh.set(r,c0,label,{b:1,sz:12,color:'153D50'});r++;}
 let c=c0+nf;const layout=[];
 groups.forEach((G,gi)=>{const [col,tint]=PAL[gi%4];const start=c,gl=[];
  G.inds.forEach(I=>{gl.push({I,c});sh.set(r+1,c,`${I.label}  (bobot ${Math.round(I.weight)}%)`,{b:1,color:'FFFFFF',fill:col,h:'centerContinuous',bl:'medium',bt:'thin',bb:'thin',br:'thin'});for(let k=1;k<4;k++)sh.set(r+1,c+k,null,{fill:col,h:'centerContinuous',bt:'thin',bb:'thin',br:'thin'});['TARGET','ACTUAL','% ACHV','SKOR'].forEach((h,k)=>sh.set(r+2,c+k,h,{b:1,color:col,fill:tint,h:'center',bl:k===0?'medium':'thin',br:'thin',bt:'thin',bb:'thin'}));c+=4;});
  const tc=c;sh.set(r+2,tc,'TOTAL '+G.name.replace(/^KPI\s*/i,''),{b:1,fill:'FFD966',h:'center',bl:'medium',br:'thin',bt:'thin',bb:'thin',wrap:1});
  sh.set(r,start,G.name,{b:1,color:'FFFFFF',fill:col,h:'centerContinuous',bl:'medium',bt:'thin',bb:'thin',br:'thin'});for(let k=start+1;k<=tc;k++)sh.set(r,k,null,{fill:col,h:'centerContinuous',bt:'thin',bb:'thin',br:'thin'});
  c++;layout.push({G,gl,tc});});
 const fc=c;sh.set(r,fc,null,{fill:'FFC000',bl:'medium',bt:'thin',br:'thin'});sh.set(r+1,fc,null,{fill:'FFC000',bl:'medium',br:'thin'});sh.set(r+2,fc,'KPI AKHIR',{b:1,fill:'FFC000',h:'center',bl:'medium',br:'thin',bt:'thin',bb:'thin'});
 first.forEach((h,i)=>{sh.set(r,c0+i,null,{fill:'B4C6E7',bt:'thin',bl:'thin',br:'thin'});sh.set(r+1,c0+i,null,{fill:'B4C6E7',bl:'thin',br:'thin'});sh.set(r+2,c0+i,h,HB);});
 const r0=r+3;let rr=r0,prevReg=null;
 const writeRow=(rr,vals,person,isTotal)=>{
  rowVals(rr,vals,person,isTotal);
  layout.forEach(L=>{const sk=[],den=[];
   L.gl.forEach(({I,c:ic})=>{const w=I.weight/100,cap=I.cap/100;let has=true;let ind=null;
    if(!isTotal){ind=person&&(person.kpi.groups.find(g=>g.name===L.G.name)||{indicators:[]}).indicators.find(i=>i.label===I.label);has=!!ind;}
    if(has){if(!isTotal){sh.set(rr,ic,num(ind.target),CELL);sh.set(rr,ic+1,ind.actual==null?0:num(ind.actual),CELL);}
     sh.set(rr,ic+2,{f:`IFERROR(${ref(rr,ic+1)}/${ref(rr,ic)},0)`},Object.assign({fmt:'0.0%',h:'right'},CELL));
     sh.set(rr,ic+3,{f:`IF(${ref(rr,ic)}>0,MIN(${ref(rr,ic+2)},${cap})*${w},0)`},Object.assign({fmt:'0.0%',h:'right'},CELL));
     sk.push(ref(rr,ic+3));den.push(`IF(${ref(rr,ic)}>0,${w},0)`);}
    else for(let k=0;k<4;k++)sh.set(rr,ic+k,null,CELL);
    sh.style(rr,ic,{bl:'medium'});});
   sh.set(rr,L.tc,sk.length?{f:`IFERROR((${sk.join('+')})/(${den.join('+')}),"")`}:null,Object.assign({fmt:'0.0%',fill:isTotal?'FFE699':'FFF2CC',h:'right',bl:'medium'},{br:'thin',bt:'thin',bb:'thin'}));});
  sh.set(rr,fc,layout.length?{f:`IFERROR(AVERAGE(${layout.map(L=>ref(rr,L.tc)).join(',')}),0)`}:null,{fmt:'0.0%',b:1,fill:isTotal?'FFE699':'FFF2CC',h:'right',bl:'medium',br:'thin',bt:'thin',bb:'thin'});
 };
 rows.forEach(p=>{
  const reg=p.region;writeRow(rr,null,p,false);
  if(prevReg!==null&&prevReg!==reg&&first[0]==='REGION')for(let k=c0;k<=fc;k++)sh.style(rr,k,{bt:'medium'});prevReg=reg;rr++;});
 // GRAND TOTAL
 sh.set(rr,c0,'GRAND TOTAL',Object.assign({},HB,{h:'left'}));for(let k=1;k<nf;k++)sh.set(rr,c0+k,null,HB);
 layout.forEach(L=>L.gl.forEach(({c:ic})=>{sh.set(rr,ic,{f:`SUM(${ref(r0,ic)}:${ref(rr-1,ic)})`},Object.assign({fill:'EAF0FA',h:'right'},CELL));sh.set(rr,ic+1,{f:`SUM(${ref(r0,ic+1)}:${ref(rr-1,ic+1)})`},Object.assign({fill:'EAF0FA',h:'right'},CELL));}));
 writeRow(rr,null,null,true);
 for(let k=c0;k<=fc;k++)sh.style(rr,k,{bt:'medium',bb:'medium'});
 // garis tebal kiri: per blok indikator & total
 for(let y=r;y<=rr;y++){layout.forEach(L=>{L.gl.forEach(({c:ic})=>sh.style(y,ic,{bl:'medium'}));sh.style(y,L.tc,{bl:'medium'});});sh.style(y,fc,{bl:'medium'});}
 return rr+4;
}
function kpiSheet(sh,ttl,sub,list,first,valsFn,groupKey){
 title(sh,ttl,sub);let r=5;
 if(!list.length){sh.set(5,2,'Belum ada data KPI untuk periode ini.',{color:'6B7C8D'});return;}
 const by={};list.forEach(p=>{const k=groupKey?groupKey(p):'ALL';(by[k]=by[k]||[]).push(p);});
 // Satu blok per kelompok kebijakan agar kolom indikator sama (REGION untuk Sales/ASM, atasan untuk Telemarketing)
 const keys=groupKey?Object.keys(by):['ALL'];
 if(!groupKey){ // Sales/ASM: satu tabel per region, ditumpuk
  const regs=[];list.forEach(p=>{if(!regs.includes(p.region))regs.push(p.region);});
  regs.forEach(reg=>{r=kpiBlock(sh,r,first,list.filter(p=>p.region===reg),valsFn,'');});
 }else keys.forEach(k=>{r=kpiBlock(sh,r,first,by[k],valsFn,'ATASAN UTAMA: '+k);});
 [2,3,4,5,6].forEach(c=>sh.widths[c]=c===2?16:22);for(let c=7;c<80;c++)sh.widths[c]=11;
}

/* ---------- Sheet target brand (Target Sales / Dealer Aktif / NOO) ---------- */
function brandSheet(sh,ttl,list1,list2,brands,kt,kc,kl){
 title(sh,ttl,'Brand yang punya target di bulan itu · target kosong = 0 · % achv apa adanya (tanpa batas 120%) · LMTD = bulan lalu pada tanggal yang sama');
 const growth=[];let r=5;
 const table=(label,rows,first,fn)=>{
  sh.set(r,2,label,{b:1,sz:12,color:'153D50'});r++;const c0=2,nf=first.length;let c=c0+nf;
  brands.forEach((b,gi)=>{const [col,tint]=PAL[gi%4];sh.set(r,c,b,{b:1,color:'FFFFFF',fill:col,h:'centerContinuous',bl:'medium',bt:'thin',bb:'thin',br:'thin'});for(let k=1;k<5;k++)sh.set(r,c+k,null,{fill:col,h:'centerContinuous',bt:'thin',bb:'thin',br:'thin'});['TARGET','ACTUAL','% ACHV','LMTD','% GROWTH'].forEach((h,k)=>sh.set(r+1,c+k,h,{b:1,color:col,fill:tint,h:'center',bl:k===0?'medium':'thin',br:'thin',bt:'thin',bb:'thin'}));c+=5;});
  first.forEach((h,i)=>{sh.set(r,c0+i,null,{fill:'B4C6E7',bt:'thin',bl:'thin',br:'thin'});sh.set(r+1,c0+i,h,HB);});
  const r0=r+2;let rr=r0,prev=null;
  rows.forEach(p=>{fn(p).forEach((v,i)=>sh.set(rr,c0+i,v,Object.assign({h:'left'},CELL,p.merangkap&&i===2?{color:'D00000',b:1}:{})));
   let cc=c0+nf;brands.forEach(b=>{const x=(p.brands||[]).find(y=>y.brand===b)||{};
    sh.set(rr,cc,num(x[kt]),Object.assign({h:'right',bl:'medium'},CELL,{bl:'medium'}));sh.set(rr,cc+1,num(x[kc]),Object.assign({h:'right'},CELL));
    sh.set(rr,cc+2,{f:`IFERROR(${ref(rr,cc+1)}/${ref(rr,cc)},0)`},Object.assign({fmt:'0.0%',h:'right'},CELL));sh.set(rr,cc+3,num(x[kl]),Object.assign({h:'right'},CELL));
    sh.set(rr,cc+4,{f:`IFERROR(${ref(rr,cc+1)}/${ref(rr,cc+3)}-1,0)`},Object.assign({fmt:'0.0%',h:'right'},CELL));growth.push(ref(rr,cc+4));cc+=5;});
   if(prev!==null&&prev!==p.region)for(let k=c0;k<cc;k++)sh.style(rr,k,{bt:'medium'});prev=p.region;rr++;});
  sh.set(rr,c0,'GRAND TOTAL',Object.assign({},HB,{h:'left'}));for(let k=1;k<nf;k++)sh.set(rr,c0+k,null,HB);
  let cc=c0+nf;brands.forEach(()=>{[0,1,3].forEach(k=>sh.set(rr,cc+k,{f:`SUM(${ref(r0,cc+k)}:${ref(rr-1,cc+k)})`},Object.assign({fill:'EAF0FA',h:'right'},CELL)));
   sh.set(rr,cc+2,{f:`IFERROR(${ref(rr,cc+1)}/${ref(rr,cc)},0)`},Object.assign({fmt:'0.0%',fill:'EAF0FA',h:'right'},CELL));sh.set(rr,cc+4,{f:`IFERROR(${ref(rr,cc+1)}/${ref(rr,cc+3)}-1,0)`},Object.assign({fmt:'0.0%',fill:'EAF0FA',h:'right'},CELL));growth.push(ref(rr,cc+4));cc+=5;});
  for(let k=c0;k<cc;k++)sh.style(rr,k,{bt:'medium',bb:'medium'});
  for(let y=r;y<=rr;y++)for(let b=0;b<brands.length;b++)sh.style(y,c0+nf+5*b,{bl:'medium'});
  r=rr+4;};
 if(!brands.length){sh.set(5,2,'Tidak ada brand dengan target pada periode ini.',{color:'6B7C8D'});return;}
 table('SALES (NASIONAL)',list1,['REGION','AREA','NAMA','ASM','RGM'],p=>[p.region,p.area,p.nama,p.asm,p.rgm]);
 table('ASM (AKUMULASI TIM)',list2,['REGION','AREA','NAMA','RGM'],p=>[p.region,p.area,p.nama,p.rgm]);
 sh.cf.push(growth);[2,3,4,5,6].forEach(c=>sh.widths[c]=c===2?16:22);for(let c=7;c<7+brands.length*5+4;c++)sh.widths[c]=11;sh.freeze={r:5,c:7};
}

/* ---------- RGM ---------- */
function rgmSheet(sh,d,sub){
 title(sh,'SUMMARY KPI RGM — '+sub,'Indikator dinamis sesuai kebijakan KPI RGM · capaian dibatasi sesuai setelan');
 const list=d.rgm;if(!list.length){sh.set(5,2,'Belum ada KPI RGM untuk periode ini.',{color:'6B7C8D'});return;}
 const brands=[];list.forEach(p=>p.kpi.groups.forEach(g=>g.indicators.forEach(i=>{if(i.brand&&i.brand!=='ALL'&&!brands.includes(i.brand))brands.push(i.brand);})));brands.sort();
 const sumTop=5,blockStart=sumTop+list.length+5;let r=blockStart;const skorCells={};const totals={};
 list.forEach((p,pi)=>{
  sh.set(r,2,'NAMA KARYAWAN: '+p.nama+(p.ownPolicy?'  ·  KPI tersendiri':'  ·  ikut KPI Big Region'),{b:1,color:'FFFFFF',fill:'1E5FB8',h:'centerContinuous',bt:'thin',bl:'thin',bb:'thin'});for(let k=3;k<=7;k++)sh.set(r,k,null,{fill:'1E5FB8',h:'centerContinuous',bt:'thin',bb:'thin',br:k===7?'thin':undefined});
  ['INDIKATOR KPI','BOBOT','TARGET','AKTUAL','% ACHIEV','SKOR'].forEach((h,i)=>sh.set(r+1,2+i,h,Object.assign({},HB,{fill:'EEF4FF',color:'1E5FB8'})));
  r+=2;const gTot=[];skorCells[pi]={};if(!p.kpi.groups.length){sh.set(r,2,'Belum ada kebijakan KPI RGM untuk periode ini.',{color:'6B7C8D'});r++;}
  p.kpi.groups.forEach(g=>{const s0=r,sk=[],den=[];
   g.indicators.forEach(i=>{const w=num(i.weight)/100,cap=(num(i.cap)||120)/100;sh.set(r,2,i.label,Object.assign({h:'left'},CELL));sh.set(r,3,w,Object.assign({fmt:'0%',h:'right'},CELL));sh.set(r,4,num(i.target),Object.assign({h:'right'},CELL));sh.set(r,5,i.actual==null?0:num(i.actual),Object.assign({h:'right'},CELL));
    sh.set(r,6,{f:`IFERROR(E${r}/D${r},0)`},Object.assign({fmt:'0.0%',h:'right'},CELL));sh.set(r,7,{f:`IF(D${r}>0,MIN(F${r},${cap})*C${r},0)`},Object.assign({fmt:'0.0%',h:'right'},CELL));
    sk.push('G'+r);den.push(`IF(D${r}>0,C${r},0)`);if(i.brand&&i.brand!=='ALL')(skorCells[pi][i.brand]=skorCells[pi][i.brand]||[]).push('G'+r);r++;});
   sh.set(r,2,'Total '+g.name,Object.assign({},CELL,{b:1,fill:'FFF2CC',h:'left'}));sh.set(r,3,{f:`SUM(C${s0}:C${r-1})`},Object.assign({fmt:'0%',b:1,fill:'FFF2CC',h:'right'},CELL));for(let k=4;k<=6;k++)sh.set(r,k,null,Object.assign({fill:'FFF2CC'},CELL));
   sh.set(r,7,sk.length?{f:`IFERROR((${sk.join('+')})/(${den.join('+')}),"")`}:null,Object.assign({fmt:'0.0%',b:1,fill:'FFF2CC',h:'right'},CELL));gTot.push('G'+r);r++;});
  sh.set(r,2,'KPI AKHIR',Object.assign({},CELL,{b:1,fill:'FFE699',h:'left',bt:'medium',bb:'medium'}));for(let k=3;k<=6;k++)sh.set(r,k,null,Object.assign({fill:'FFE699'},CELL,{bt:'medium',bb:'medium'}));
  sh.set(r,7,gTot.length?{f:`IFERROR(AVERAGE(${gTot.join(',')}),0)`}:null,Object.assign({fmt:'0.0%',b:1,fill:'FFE699',h:'right'},CELL,{bt:'medium',bb:'medium'}));totals[pi]='G'+r;r+=3;});
 // ringkasan poin per brand + rank
 sh.set(sumTop,2,'SUMMARY POINT KPI BY BRAND',{b:1,sz:12,color:'153D50'});
 const hd=['NAMA RGM','REGION',...brands,'TOTAL POIN','RANK'];hd.forEach((h,i)=>sh.set(sumTop+1,2+i,h,HB));
 list.forEach((p,pi)=>{const rr=sumTop+2+pi;sh.set(rr,2,p.nama,Object.assign({h:'left'},CELL));sh.set(rr,3,p.region,Object.assign({h:'left'},CELL));
  brands.forEach((b,bi)=>{const cs=skorCells[pi][b];sh.set(rr,4+bi,cs?{f:'SUM('+cs.join(',')+')'}:0,Object.assign({fmt:'0.0%',h:'right'},CELL));});
  const tc=4+brands.length;sh.set(rr,tc,{f:`SUM(${ref(rr,4)}:${ref(rr,tc-1)})`},Object.assign({fmt:'0.0%',b:1,fill:'FFF2CC',h:'right'},CELL));
  sh.set(rr,tc+1,{f:`RANK(${ref(rr,tc)},${ref(sumTop+2,tc)}:${ref(sumTop+1+list.length,tc)})`},Object.assign({b:1,h:'center'},CELL));});
 const ar=sumTop+2+list.length;sh.set(ar,2,'RATA-RATA',Object.assign({},HB,{h:'left'}));sh.set(ar,3,null,HB);for(let k=0;k<=brands.length;k++)sh.set(ar,4+k,{f:`AVERAGE(${ref(sumTop+2,4+k)}:${ref(ar-1,4+k)})`},Object.assign({fmt:'0.0%',fill:'EAF0FA',h:'right'},CELL));
 sh.set(ar,5+brands.length,null,HB);
 [2,3,4,5,6,7].forEach(c=>sh.widths[c]=c===2?34:16);for(let c=8;c<12;c++)sh.widths[c]=12;
}

function validasiSheet(sh,rows){
 title(sh,'Validasi Dealer per Indikator','Setiap Sales dan indikator KPI-nya: dealer yang terhitung dan yang sudah order tapi belum memenuhi. Gunakan filter di header.');
 const h=['NO','NAMA SALES','INDIKATOR','STATUS','KODE DEALER','DEALER','QTY','NILAI','KETERANGAN'];h.forEach((x,i)=>sh.set(5,2+i,x,HB));
 rows.forEach((v,n)=>{const r=6+n;sh.set(r,2,n+1,Object.assign({h:'center'},CELL));v.forEach((x,i)=>sh.set(r,3+i,x,Object.assign({h:i>=5&&i<=6?'right':'left',fmt:i===6?'#,##0':undefined,color:i===2&&x!=='TERHITUNG'?'B02A2A':undefined},CELL)));});
 const e=6+rows.length;sh.set(e,2,null,Object.assign({fill:'EAF0FA'},CELL));sh.set(e,3,'Total (mengikuti filter)',Object.assign({b:1,fill:'EAF0FA',h:'left'},CELL));for(let k=4;k<=10;k++)sh.set(e,k,null,Object.assign({fill:'EAF0FA'},CELL));
 sh.set(e,8,{f:`SUBTOTAL(109,H6:H${e-1})`},Object.assign({b:1,fill:'EAF0FA',h:'right'},CELL));sh.set(e,9,{f:`SUBTOTAL(109,I6:I${e-1})`},Object.assign({b:1,fill:'EAF0FA',h:'right',fmt:'#,##0'},CELL));
 if(rows.length)sh.filter=`B5:J${e-1}`;sh.freeze={r:6,c:1};
 [2,3,4,5,6,7,8,9,10].forEach((c,i)=>sh.widths[c]=[6,26,22,18,14,34,9,16,46][i]);
}
function notesSheet(sh,d){
 sh.set(2,2,'Catatan aturan KPI',{b:1,sz:16,color:'153D50'});
 [`Periode ${d.period} · data s.d. ${d.cutoff} · LMTD s.d. ${d.prevCut}.`,
  'Kelompok dan indikator KPI mengikuti kebijakan yang berlaku pada periode ini, sehingga kolom bisa berbeda tiap bulan dan tiap wilayah.',
  'SKOR indikator = MIN(capaian, batas) × bobot. Batas capaian mengikuti setelan KPI di aplikasi (umumnya 120%). Indikator dengan target 0 tidak diwajibkan dan tidak masuk penyebut.',
  'TOTAL kelompok = jumlah skor ÷ jumlah bobot indikator yang berlaku. KPI AKHIR = rata-rata total kelompok yang berlaku.',
  'Dealer Aktif: dealer yang order minimal 2 unit pada brand terkait di periode berjalan (sesuai aturan aplikasi). NOO: dealer yang pertama kali order brand tersebut di periode ini.',
  'DA dengan promotor: ACC minimal jumlah promotor × batas per promotor; tanpa promotor memakai minimum unit indikator.',
  'ASM yang merangkap Sales muncul di sheet Sales (penjualan sendiri, nama merah) dan di sheet ASM (akumulasi tim).',
  'Sheet Telemarketing dikelompokkan berdasarkan atasan utama. Sheet Validasi Dealer berisi Sales (jabatan Sales); validasi ASM dan RGM dibuka lewat aplikasi (Peringkat, Cari & Kroscek KPI).',
  'Persen pada Target Sales, Dealer Aktif, dan NOO tidak dibatasi 120%.'].forEach((t,i)=>sh.set(4+i,2,'- '+t,{wrap:0}));
 sh.widths[2]=140;
}

window.kpiExportBuild=function(d){
 const st=new Styles(),mk=()=>new Sheet(st),per=d.period,sub='Periode : '+per+' · Update : '+d.cutoff+' · Capaian dibatasi sesuai setelan aplikasi';
 const S=mk(),A=mk(),T=mk(),R=mk(),TS=mk(),DA=mk(),NO=mk(),V=mk(),C=mk();
 const nop=()=>{};
 const redName=(sh,rr,p)=>{if(p&&p.merangkap)sh.setS(rr,4,p.nama,Object.assign({h:'left',color:'D00000',b:1},CELL));};
 const rowFirst=(first,sh)=>(rr,vals,p,isTotal)=>{if(isTotal||!p)return;const v=first==='sales'?[p.region,p.area,p.nama,p.asm,p.rgm]:first==='asm'?[p.region,p.area,p.nama,p.rgm]:[p.region,p.area,p.nama,p.atasan,p.rgm];v.forEach((x,i)=>sh.setS(rr,2+i,x,Object.assign({h:'left'},CELL)));if(first==='sales')redName(sh,rr,p);};
 const kb=(sh,ttl,list,first,kind,key)=>{kpiSheet(sh,ttl,sub,list,first,rowFirst(kind,sh),key);};
 kb(S,'Pencapaian KPI Sales',d.sales,['REGION','AREA','NAMA','ASM','RGM'],'sales');
 kb(A,'Pencapaian KPI ASM (akumulasi tim)',d.asm,['REGION','AREA','NAMA','RGM'],'asm');
 kb(T,'Pencapaian KPI Telemarketing (per atasan utama)',d.tele,['REGION','AREA','NAMA','ATASAN UTAMA','RGM'],'tele',p=>p.atasan);
 rgmSheet(R,d,per);
 brandSheet(TS,'Target Sell Thru per Sales per Brand (di luar KPI)',d.sales,d.asm,d.brands,'tq','cq','lq');
 brandSheet(DA,'Dealer Aktif per Sales per Brand (di luar KPI)',d.sales,d.asm,d.brands,'td','cd','ld');
 brandSheet(NO,'NOO per Sales per Brand (di luar KPI)',d.sales,d.asm,d.brands,'tn','cn','ln');
 validasiSheet(V,d.validation);notesSheet(C,d);
 return {names:['Sales','ASM','Telemarketing','RGM','Target Sales','Dealer Aktif','NOO','Validasi Dealer','Catatan'],sheets:[S,A,T,R,TS,DA,NO,V,C].map(s=>s.xml()),styles:st.xml()};
};
window.kpiExportButton=function(v){
 if(!['ADMIN','SUPER ADMIN'].includes(BOOT?.user?.role)||!v)return;
 v.insertAdjacentHTML('afterbegin','<div class="toolbar kpiExportBar"><button class="chip primary" id="kpiExportBtn">Export Excel</button><span class="hint" id="kpiExportMsg">Semua wilayah, semua orang yang punya KPI, periode yang sedang dipilih.</span></div>');
 $('kpiExportBtn').onclick=async()=>{const b=$('kpiExportBtn'),m=$('kpiExportMsg');b.disabled=true;m.textContent='Menyusun data KPI… ini bisa memakan waktu beberapa menit.';
  try{const d=await rpc('apiKpiExport',TOKEN,REPORT.period);m.textContent='Membuat file Excel…';const x=kpiExportBuild(d);xlsxZipDownload_('Export-KPI-'+d.period+'.xlsx',x.names,x.sheets,x.styles);m.textContent='File Export-KPI-'+d.period+'.xlsx sudah diunduh.';}
  catch(e){m.textContent=e.message||'Export gagal.';}finally{b.disabled=false;}};
};
})();
