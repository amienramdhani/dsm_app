/* DROPDOWN DESKTOP STANDAR (2026-11-24): tandai <select> yang sedang memfilter (bukan "Semua") dengan kelas dd-on.
   Pasangan assets/dropdown_std.css. Tidak dimuat oleh DSM Klaim. */
(function(){
 /* aktif hanya bila dropdown punya opsi pertama "Semua…/ALL/kosong" dan yang dipilih bukan opsi itu (dropdown periode, urutan, dsb tidak diwarnai) */
 function isOn(s){if(s.multiple||s.size>1||s.hasAttribute('data-nodd')||s.selectedIndex<=0)return false;const f=s.options[0];if(!f)return false;return /^(semua|all)\b/i.test(f.text.trim())||/^(|all)$/i.test(String(f.value).trim());}
 function mark(s){try{s.classList.toggle('dd-on',isOn(s));}catch(e){}}
 function scan(root){(root||document).querySelectorAll('#app select,.modal select').forEach(mark);}
 document.addEventListener('change',e=>{if(e.target&&e.target.tagName==='SELECT')mark(e.target);},true);
 let t=null;const mo=new MutationObserver(()=>{clearTimeout(t);t=setTimeout(()=>scan(),60);});
 function start(){scan();mo.observe(document.body,{childList:true,subtree:true});}
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
})();
