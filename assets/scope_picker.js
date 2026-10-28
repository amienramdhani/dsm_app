/* Pemilih Cakupan versi HP: bottom sheet berkelompok (sama urutannya dengan daftar desktop).
   <select id="scopeSelect"> tetap jadi sumber nilai; sheet hanya mengganti tampilan pilihnya. Hanya ≤760px. */
(function(){
 if(window.__dsmScopePicker)return;window.__dsmScopePicker=1;
 var mq=window.matchMedia('(max-width:760px)');
 var st=document.createElement('style');
 st.textContent='@media(max-width:760px){#scopeSelect,.hsSel[data-for="scopeSelect"]{pointer-events:none}}'+
 '#scpSheet{position:fixed;inset:0;z-index:99990;background:rgba(15,25,55,.38);display:flex;align-items:flex-end;opacity:0;transition:opacity .2s}'+
 '#scpSheet.on{opacity:1}'+
 '#scpSheet .scpP{width:100%;max-height:78vh;background:#fff;border-radius:22px 22px 0 0;box-shadow:0 -12px 34px rgba(20,40,80,.25);display:flex;flex-direction:column;transform:translateY(24px);transition:transform .22s ease}'+
 '#scpSheet.on .scpP{transform:none}'+
 '#scpSheet .scpH{display:flex;align-items:center;justify-content:space-between;padding:16px 18px 10px;font-weight:800;font-size:15px;color:#15213c}'+
 '#scpSheet .scpX{border:0;background:#eef3fa;border-radius:10px;width:32px;height:32px;font-size:16px;color:#4a5870}'+
 '#scpSheet .scpS{margin:0 14px 8px;display:block;width:calc(100% - 28px);box-sizing:border-box;border:1px solid #d5e0f2;background:#f4f7fc;border-radius:12px;padding:10px 12px;font-size:14px;font-family:inherit;color:#15213c;outline:0}'+
 '#scpSheet .scpS:focus{border-color:#5b8fe8;background:#fff}'+
 '#scpSheet [hidden]{display:none!important}'+
 '#scpSheet .scpE{padding:18px 10px;color:#71809b;font-size:13px}'+
 '#scpSheet .scpL{overflow-y:auto;overscroll-behavior:contain;padding:0 10px calc(16px + env(safe-area-inset-bottom,0px))}'+
 '#scpSheet .scpG{font-size:11.5px;font-weight:800;font-style:italic;text-transform:uppercase;letter-spacing:.2px;color:#1d3a78;padding:14px 8px 5px;border-top:1px solid #e3eaf6;margin-top:6px}'+
 '#scpSheet .scpG:first-child{border-top:0;margin-top:0}'+
 '#scpSheet .scpO{display:flex;align-items:center;justify-content:space-between;width:100%;border:0;background:transparent;text-align:left;font-family:inherit;font-weight:600;font-size:13.5px;line-height:1.3;color:#243251;padding:10px 8px 10px 22px;border-radius:10px;min-height:40px}'+
 '#scpSheet .scpO.top{padding-left:8px;font-weight:800}'+
 '#scpSheet .scpO.sel{background:#e8f0ff;color:#1259c7;font-weight:800}'+
 '#scpSheet .scpO.sel::after{content:"✓";font-weight:800}';
 document.head.appendChild(st);
 function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
 function close(sh){sh.classList.remove('on');setTimeout(function(){sh.remove()},220)}
 function open(){
  var sel=document.getElementById('scopeSelect');if(!sel||document.getElementById('scpSheet'))return;
  var h='';
  Array.prototype.forEach.call(sel.children,function(n){
   if(n.tagName==='OPTGROUP'){h+='<div class="scpG">'+esc(n.label)+'</div>';Array.prototype.forEach.call(n.children,function(o){h+=opt(o,sel,'')})}
   else if(n.tagName==='OPTION')h+=opt(n,sel,' top');
  });
  var sh=document.createElement('div');sh.id='scpSheet';
  sh.innerHTML='<div class="scpP" role="dialog" aria-label="Pilih cakupan"><div class="scpH"><span>Cakupan</span><button type="button" class="scpX" aria-label="Tutup">✕</button></div><input type="search" class="scpS" placeholder="Cari nama atau wilayah…" autocomplete="off" aria-label="Cari cakupan"><div class="scpL">'+h+'<div class="scpE" hidden>Tidak ditemukan.</div></div></div>';
  sh.addEventListener('click',function(e){
   if(e.target===sh||e.target.closest('.scpX')){close(sh);return}
   var b=e.target.closest('.scpO');if(!b)return;
   sel.value=b.dataset.v;sel.dispatchEvent(new Event('change',{bubbles:true}));close(sh);try{if(typeof closeMobileMore==='function'){closeMobileMore();if(typeof syncMobileNav==='function')syncMobileNav();}}catch(x){}
  });
  var inp=sh.querySelector('.scpS'),list=sh.querySelector('.scpL');
  inp.addEventListener('input',function(){
   var q=inp.value.trim().toLowerCase(),g=null,gHit=false,gEls=[],any=false;
   function flush(){if(g){g.hidden=!(gHit||!q);}}
   Array.prototype.forEach.call(list.children,function(n){
    if(n.classList.contains('scpG')){flush();g=n;gHit=!q||n.textContent.toLowerCase().indexOf(q)>-1;gEls=[]}
    else if(n.classList.contains('scpO')){
     var hit=!q||n.textContent.toLowerCase().indexOf(q)>-1||(g&&!n.classList.contains('top')&&g.textContent.toLowerCase().indexOf(q)>-1);
     n.hidden=!hit;if(hit){any=true;if(g&&!n.classList.contains('top'))gHit=true}
    }
   });
   flush();
   list.querySelector('.scpE').hidden=any;
  });
  document.body.appendChild(sh);
  requestAnimationFrame(function(){sh.classList.add('on');var s=sh.querySelector('.sel');if(s)s.scrollIntoView({block:'center'})});
 }
 function opt(o,sel,cls){return '<button type="button" class="scpO'+cls+(o.value===sel.value?' sel':'')+'" data-v="'+esc(o.value)+'">'+esc(o.textContent)+'</button>'}
 document.addEventListener('click',function(e){
  if(!mq.matches)return;
  var sel=document.getElementById('scopeSelect');if(!sel)return;
  var lab=e.target.closest&&(e.target.closest('label[for="scopeSelect"]')||(function(){var l=e.target.closest('label.hsRow');return l&&l.querySelector('.hsSel[data-for="scopeSelect"]')?l:null})());
  var r=sel.getBoundingClientRect();
  var inside=r.width>0&&e.clientX>=r.left&&e.clientX<=r.right&&e.clientY>=r.top&&e.clientY<=r.bottom&&!sel.disabled;
  if(lab||inside){e.preventDefault();open()}
 },true);
})();
