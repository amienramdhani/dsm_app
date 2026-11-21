/* Penamaan wilayah di LAYAR (2026-11-20, permintaan user). Hanya tampilan: Export/Import/database tidak berubah.
   Big Region -> REGION, Region (tengah) -> SUB REGION, Sub Region (lama) -> AREA.
   Kode sumber tetap memakai istilah lama; teks di layar diterjemahkan di sini. Halaman Admin dikecualikan
   (petunjuk template Import menyebut nama kolom file yang belum diganti). Beri atribut data-nolabel untuk mengecualikan elemen. */
(function(){
 const MAP={'big region':'Region','sub region':'Area','region':'Sub Region'};
 const RE=/\b(big region|sub[ -]region|region)\b/gi,RT=/\b(big region|sub[ -]region|region)\b/i;
 const conv=s=>s.replace(RE,m=>{const t=MAP[m.toLowerCase().replace('-',' ')];if(!t)return m;return m===m.toUpperCase()?t.toUpperCase():m[0]===m[0].toLowerCase()?t.toLowerCase():t;});
 const done=new WeakMap(),SKIP='script,style,textarea,input,[data-nolabel],[contenteditable]';
 let adminNow=()=>{try{return typeof PAGE!=='undefined'&&PAGE==='admin';}catch(e){return false;}};
 function text(n){if(n.nodeType!==3)return;const d=n.data;if(done.get(n)===d||!RT.test(d))return;const p=n.parentElement;if(!p||p.closest(SKIP))return;const o=conv(d);done.set(n,o);if(o!==d)n.data=o;}
 const ATTR=['title','placeholder','aria-label','data-v6tip'];
 function attrs(el){if(el.nodeType!==1||el.closest('[data-nolabel]'))return;ATTR.forEach(a=>{const v=el.getAttribute&&el.getAttribute(a);if(!v||!/region/i.test(v)||el.dataset&&el.dataset.rlc===a+v)return;const o=conv(v);el.setAttribute(a,o);if(el.dataset)el.dataset.rlc=a+o;});}
 function walk(root){if(adminNow())return;if(root.nodeType===3){text(root);return;}if(root.nodeType!==1)return;if(root.matches&&root.matches(SKIP))return;attrs(root);const w=document.createTreeWalker(root,NodeFilter.SHOW_ELEMENT|NodeFilter.SHOW_TEXT);let n;while((n=w.nextNode())){if(n.nodeType===3)text(n);else{if(n.matches(SKIP)){continue;}attrs(n);}}}
 let q=new Set(),sch=false;
 function flush(){sch=false;const a=[...q];q=new Set();a.forEach(walk);}
 function enqueue(n){q.add(n);if(!sch){sch=true;requestAnimationFrame(flush);}}
 new MutationObserver(ms=>{for(const m of ms){if(m.type==='characterData')enqueue(m.target);else m.addedNodes.forEach(enqueue);}}).observe(document.documentElement,{childList:true,subtree:true,characterData:true});
 document.addEventListener('DOMContentLoaded',()=>enqueue(document.body));
})();
