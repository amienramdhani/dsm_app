/* Popup "Selamat berlibur" (disetujui 2026-10-19). Muncul sekali per tanggal libur per perangkat.
   Sumber: BOOT.v6hol (tanggal aktif dari sheet Hari_Libur) dan BOOT.v6holn (KETERANGAN). Minggu biasa tidak memicu popup. */
(function(){
 if(window.dsmHolidayPopup)return;window.dsmHolidayPopup=1;
 const KEY='dsmHolSeen';
 const HARI=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'],BLN=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
 const e=s=>String(s).replace(/[&<>"]/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[x]));
 const pad=n=>String(n).padStart(2,'0');
 function today(){const d=new Date();return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate());}
 function seen(){try{return JSON.parse(localStorage.getItem(KEY)||'[]');}catch(x){return [];}}
 function mark(d){try{const a=seen().filter(x=>x>=d.slice(0,4)+'-01-01');a.push(d);localStorage.setItem(KEY,JSON.stringify(a.slice(-40)));}catch(x){}}
 function css(){if(document.getElementById('dsmHolCss'))return;const s=document.createElement('style');s.id='dsmHolCss';s.textContent=
 '#dsmHol{position:fixed;inset:0;z-index:300;display:grid;place-items:center;padding:20px;background:rgba(10,22,52,.55);backdrop-filter:blur(3px);animation:hlIn .25s ease-out}'
 +'#dsmHol .hlCard{width:min(100%,340px);background:#fff;border-radius:26px;overflow:hidden;box-shadow:0 24px 60px rgba(10,22,52,.35);text-align:center;color:#14203D;font-family:"DSM Hero",system-ui,-apple-system,"Segoe UI",sans-serif}'
 +'#dsmHol .hlTop{position:relative;height:118px;background:linear-gradient(135deg,#2F6FE0,#6D4FD8 60%,#F59E5B);overflow:hidden}'
 +'#dsmHol .hlTop::before{content:"";position:absolute;left:50%;top:34px;width:62px;height:62px;margin-left:-31px;border-radius:50%;background:radial-gradient(circle,#FFF6D6 0 38%,rgba(255,214,120,.55) 62%,transparent 72%)}'
 +'#dsmHol .hlTop i{position:absolute;width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.75);animation:hlTw 2.4s ease-in-out infinite}'
 +'#dsmHol .hlBody{padding:18px 22px 22px}'
 +'#dsmHol small{display:block;font-size:11px;font-weight:600;color:#6B7A8C;letter-spacing:.04em}'
 +'#dsmHol h3{margin:6px 0 8px;font-size:21px;line-height:1.25;font-weight:800;letter-spacing:-.01em}'
 +'#dsmHol p{margin:0 0 16px;font-size:12.5px;line-height:1.5;color:#4B5568}'
 +'#dsmHol button{width:100%;border:0;border-radius:14px;padding:12px;font:inherit;font-size:14px;font-weight:800;color:#fff;background:linear-gradient(135deg,#2F6FE0,#6D4FD8);box-shadow:0 8px 18px rgba(47,91,216,.3);cursor:pointer}'
 +'#dsmHol button:focus{outline:none}#dsmHol button:focus-visible{outline:3px solid rgba(47,111,224,.45);outline-offset:2px}'
 +'@keyframes hlIn{from{opacity:0}to{opacity:1}}@keyframes hlTw{0%,100%{opacity:.25;transform:scale(.8)}50%{opacity:1;transform:scale(1.2)}}'
 +'@media(prefers-reduced-motion:reduce){#dsmHol,#dsmHol .hlTop i{animation:none}}';
 document.head.appendChild(s);}
 function show(date,name){
  if(document.getElementById('dsmHol'))return;css();
  const d=new Date(date+'T00:00:00'),tg=HARI[d.getDay()]+', '+d.getDate()+' '+BLN[d.getMonth()]+' '+d.getFullYear();
  const box=document.createElement('div');box.id='dsmHol';box.setAttribute('role','dialog');box.setAttribute('aria-modal','true');box.setAttribute('aria-labelledby','dsmHolT');
  const dots=[[12,18],[26,70],[44,30],[70,52],[84,22],[90,76],[58,12]].map((p,i)=>'<i style="left:'+p[0]+'%;top:'+p[1]+'%;animation-delay:'+(i*.3)+'s"></i>').join('');
  box.innerHTML='<div class="hlCard"><div class="hlTop">'+dots+'</div><div class="hlBody"><small>'+e(tg)+'</small><h3 id="dsmHolT">Selamat '+e(name||'berlibur')+'</h3><p>Hari ini libur dan tidak dihitung sebagai hari kerja. Selamat beristirahat dan menikmati waktu bersama keluarga.</p><button type="button" id="dsmHolOk">Terima kasih</button></div></div>';
  document.body.appendChild(box);
  const close=()=>{mark(date);box.remove();document.removeEventListener('keydown',key);};
  const key=ev=>{if(ev.key==='Escape')close();};
  document.addEventListener('keydown',key);
  box.querySelector('#dsmHolOk').onclick=close;box.addEventListener('click',ev=>{if(ev.target===box)close();});
  box.querySelector('#dsmHolOk').focus();
 }
 function check(){
  try{
   const app=document.getElementById('app');if(!app||app.hidden)return;
   if(typeof BOOT==='undefined'||!BOOT||!BOOT.user)return;
   const t=today();if(!Array.isArray(BOOT.v6hol)||!BOOT.v6hol.includes(t)||seen().includes(t))return;
   const n=(BOOT.v6holn&&BOOT.v6holn[t])||'';
   show(t,n&&/^(hari|tahun|idul|natal|isra|waisak|kenaikan|maulid|nyepi|jumat)/i.test(n)?n:(n?'Hari Libur '+n:'Hari Libur'));
  }catch(x){}
 }
 setInterval(check,2000);setTimeout(check,800);
})();
