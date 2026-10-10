/* Menu Panduan DSM: tampil di dalam aplikasi, isi dari panduan-content.js */
(function(){
const G=()=>window.DSM_GUIDE||{groups:[],topics:[],glossary:[]};
const E=s=>String(s==null?'':s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const st={topic:'login',tab:'baca',q:'',list:true};
const TABS=[['baca','Cara Baca'],['warna','Warna & Tanda'],['aksi','Interaksi'],['kasus','Contoh Pakai'],['faq','FAQ']];
function okRole(r){if(!r||r==='all')return true;const u=(window.BOOT&&BOOT.user&&BOOT.user.role)||'';return r.includes(u);}
function groups(){const g=G();return g.groups.filter(x=>okRole(x.roles)).map(x=>({...x,items:g.topics.filter(t=>t.group===x.id&&okRole(t.roles))})).filter(x=>x.items.length);}
function hay(t){return JSON.stringify([t.title,t.intro,t.baca,t.warna,t.aksi,t.kasus,t.faq]).toLowerCase();}
function visible(){const q=st.q.trim().toLowerCase();return groups().map(g=>({...g,items:q?g.items.filter(t=>hay(t).includes(q)||(t.glossary&&G().glossary.some(x=>x[0].toLowerCase().includes(q)))):g.items})).filter(g=>g.items.length);}
function dl(rows){return '<dl class="pgDl">'+rows.map(r=>'<dt>'+E(r[0])+'</dt><dd>'+E(r[1])+'</dd>').join('')+'</dl>';}
function tabHtml(t,tab){
 if(t.glossary)return dl(G().glossary);
 if(tab==='baca')return t.baca&&t.baca.length?dl(t.baca):'<p class="pgEmpty">Belum ada penjelasan.</p>';
 if(tab==='warna')return t.warna?'<p>'+E(t.warna)+'</p>':'<p class="pgEmpty">Tidak ada kode warna khusus pada bagian ini.</p>';
 if(tab==='aksi')return t.aksi&&t.aksi.length?dl(t.aksi):'<p class="pgEmpty">Tidak ada interaksi khusus.</p>';
 if(tab==='kasus')return t.kasus?'<p>'+E(t.kasus)+'</p>':'<p class="pgEmpty">Belum ada contoh.</p>';
 return t.faq&&t.faq.length?dl(t.faq):'<p class="pgEmpty">Belum ada pertanyaan.</p>';
}
function find(id){return G().topics.find(t=>t.id===id);}
function html(){
 const gs=visible(),t=find(st.topic)||(gs[0]&&gs[0].items[0]);
 const g=t&&G().groups.find(x=>x.id===t.group);
 const toc=gs.map(x=>'<h4>'+E(x.title).toUpperCase()+'</h4>'+x.items.map(i=>'<a href="#" data-pg-topic="'+i.id+'" class="'+(t&&i.id===t.id&&!st.list?'on':'')+'">'+E(i.title)+'</a>').join('')).join('')||'<p class="pgEmpty">Tidak ada hasil.</p>';
 const tabs=t&&!t.glossary?'<div class="pgTabs" role="tablist">'+TABS.map(x=>'<button type="button" role="tab" data-pg-tab="'+x[0]+'" class="'+(st.tab===x[0]?'on':'')+'">'+x[1]+'</button>').join('')+'</div>':'';
 const body=t?'<div class="pgCrumb">Panduan › '+E(g?g.title:'')+'</div><h3 class="pgH">'+E(t.title)+'</h3><p class="pgIntro">'+E(t.intro||'')+'</p>'+tabs+'<div class="pgBody">'+tabHtml(t,st.tab)+'</div><div class="pgAct">'+(g&&g.page?'<button type="button" class="pgBtn pri" data-pg-go="'+g.page+'">Coba Sekarang → Buka '+E(g.title)+'</button>':'')+'</div>':'<p class="pgEmpty">Tidak ada hasil.</p>';
 return '<div class="pgWrap '+(st.list?'pgList':'pgDetail')+'"><nav class="pgToc"><input id="pgQ" class="pgQ" type="search" placeholder="Cari: LMTD, clone, DOS…" value="'+E(st.q)+'" aria-label="Cari panduan">'+toc+'</nav><section class="pgMain"><button type="button" class="pgBack" data-pg-back>← Daftar Panduan</button>'+body+'</section></div>';
}
const CSS='.pgWrap{display:grid;grid-template-columns:250px minmax(0,1fr);gap:16px;align-items:start}.pgToc,.pgMain{background:#fff;border:1px solid #dbe5ec;border-radius:12px;padding:14px;min-width:0}.pgToc{display:flex;flex-direction:column;gap:3px;position:sticky;top:12px;max-height:calc(100vh - 40px);overflow:auto}.pgToc h4{margin:10px 0 2px;font-size:10px;letter-spacing:.12em;color:#62798b}.pgToc a{padding:6px 8px;border-radius:7px;color:#1d3244;text-decoration:none;font-size:13px}.pgToc a.on{background:#1E5FB81a;color:#1E5FB8;font-weight:700}.pgQ{width:100%;box-sizing:border-box;padding:9px 10px;border:1px solid #dbe5ec;border-radius:8px;font:inherit}.pgCrumb{font-size:11px;color:#62798b}.pgH{margin:4px 0 6px;font-size:21px}.pgIntro{margin:0 0 12px;color:#4a6071;line-height:1.6;max-width:70ch}.pgTabs{display:flex;gap:2px;border-bottom:1px solid #dbe5ec;overflow-x:auto;margin-bottom:12px}.pgTabs button{border:0;background:none;padding:8px 11px;font-weight:700;font-size:13px;color:#62798b;border-bottom:2px solid transparent;white-space:nowrap;cursor:pointer}.pgTabs button.on{color:#1E5FB8;border-color:#1E5FB8}.pgDl{display:grid;grid-template-columns:170px minmax(0,1fr);gap:8px 16px;margin:0;font-size:13.5px}.pgDl dt{font-weight:800}.pgDl dd{margin:0;color:#4a6071;line-height:1.55}.pgBody p{line-height:1.6;max-width:70ch;margin:0}.pgEmpty{color:#62798b;font-size:13px}.pgAct{margin-top:16px;display:flex;gap:8px;flex-wrap:wrap}.pgBtn{border:0;border-radius:9px;padding:10px 15px;font-weight:700;cursor:pointer}.pgBtn.pri{background:#1E5FB8;color:#fff}.pgBack{display:none;border:0;background:none;color:#1E5FB8;font-weight:700;padding:0 0 10px;cursor:pointer}@media(max-width:760px){.pgWrap{grid-template-columns:1fr}.pgToc{position:static;max-height:none}.pgList .pgMain{display:none}.pgDetail .pgToc{display:none}.pgBack{display:block}.pgDl{grid-template-columns:1fr;gap:2px}.pgDl dd{margin-bottom:8px}}';
function draw(view){
 if(!document.getElementById('pgCss')){const s=document.createElement('style');s.id='pgCss';s.textContent=CSS;document.head.appendChild(s);}
 const keep=document.activeElement&&document.activeElement.id==='pgQ',pos=keep?document.activeElement.selectionStart:0;
 view.innerHTML=html();
 if(keep){const q=view.querySelector('#pgQ');q.focus();q.setSelectionRange(pos,pos);}
 const wide=!window.matchMedia('(max-width:760px)').matches;
 if(wide&&st.list){st.list=false;view.firstChild.className='pgWrap pgDetail';}
}
window.panduanView=async function(view){
 st.list=window.matchMedia('(max-width:760px)').matches;
 draw(view);
 view.onclick=e=>{
  const a=e.target.closest('[data-pg-topic]'),tb=e.target.closest('[data-pg-tab]'),go=e.target.closest('[data-pg-go]'),bk=e.target.closest('[data-pg-back]');
  if(a){e.preventDefault();st.topic=a.dataset.pgTopic;st.tab='baca';st.list=false;draw(view);window.scrollTo(0,0);}
  else if(tb){st.tab=tb.dataset.pgTab;draw(view);}
  else if(go){window.navigateTo(go.dataset.pgGo);}
  else if(bk){st.list=true;draw(view);}
 };
 view.oninput=e=>{if(e.target.id==='pgQ'){st.q=e.target.value;st.list=window.matchMedia('(max-width:760px)').matches;draw(view);}};
};
})();
