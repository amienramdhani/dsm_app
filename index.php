<!DOCTYPE html><html lang="id"><head><script src="assets/perf_probe.js?v=<?=@filemtime(__DIR__.'/assets/perf_probe.js')?>"></script><base target="_top"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>DSM · Distribution Sales Monitoring</title><link rel="manifest" href="manifest.webmanifest"><meta name="theme-color" content="#1e6fd9"><link rel="apple-touch-icon" href="assets/icon-192.png"><meta name="apple-mobile-web-app-capable" content="yes">
<script>
// Prevent native form navigation even if the main application fails to start.
document.addEventListener('submit',function(event){event.preventDefault();},true);


</script><link rel="stylesheet" href="assets/dsm.css?v=<?=@filemtime(__DIR__.'/assets/dsm.css')?>"><link rel="stylesheet" href="assets/trx_daily_hp.css?v=<?=@filemtime(__DIR__.'/assets/trx_daily_hp.css')?>"><link rel="stylesheet" href="assets/dealer_status.css?v=<?=@filemtime(__DIR__.'/assets/dealer_status.css')?>"><link rel="stylesheet" href="assets/ui_prefs.css?v=<?=@filemtime(__DIR__.'/assets/ui_prefs.css')?>"><link rel="stylesheet" href="assets/topnav.css?v=<?=@filemtime(__DIR__.'/assets/topnav.css')?>"><link rel="stylesheet" href="assets/publish_queue.css?v=<?=@filemtime(__DIR__.'/assets/publish_queue.css')?>"><link rel="stylesheet" href="assets/form_kit.css?v=<?=@filemtime(__DIR__.'/assets/form_kit.css')?>"><link rel="stylesheet" href="assets/dealer_detail.css?v=<?=@filemtime(__DIR__.'/assets/dealer_detail.css')?>"><link rel="stylesheet" href="assets/page_tidy.css?v=<?=@filemtime(__DIR__.'/assets/page_tidy.css')?>"><link rel="stylesheet" href="assets/page_header.css?v=<?=@filemtime(__DIR__.'/assets/page_header.css')?>"><link rel="stylesheet" href="assets/table_head.css?v=<?=@filemtime(__DIR__.'/assets/table_head.css')?>"><link rel="stylesheet" href="assets/table_theme.css?v=<?=@filemtime(__DIR__.'/assets/table_theme.css')?>"><link rel="stylesheet" href="assets/analisa_cross.css?v=<?=@filemtime(__DIR__.'/assets/analisa_cross.css')?>"><link rel="stylesheet" href="assets/analisa_rombak.css?v=<?=@filemtime(__DIR__.'/assets/analisa_rombak.css')?>"><link rel="stylesheet" href="assets/brand_tabs.css?v=<?=@filemtime(__DIR__.'/assets/brand_tabs.css')?>"><link rel="stylesheet" href="assets/dropdown_std.css?v=<?=@filemtime(__DIR__.'/assets/dropdown_std.css')?>"><script src="assets/dropdown_std.js?v=<?=@filemtime(__DIR__.'/assets/dropdown_std.js')?>" defer></script><link rel="stylesheet" href="assets/kpi_tabel.css?v=<?=@filemtime(__DIR__.'/assets/kpi_tabel.css')?>"><link rel="stylesheet" href="assets/tim_v2.css?v=<?=@filemtime(__DIR__.'/assets/tim_v2.css')?>"><link rel="stylesheet" href="assets/ai_v7.css?v=<?=@filemtime(__DIR__.'/assets/ai_v7.css')?>"><link rel="stylesheet" href="assets/ai_visual.css?v=<?=@filemtime(__DIR__.'/assets/ai_visual.css')?>"><script src="panduan-content.js?v=<?= @filemtime(__DIR__.'/panduan-content.js') ?: 1 ?>"></script><script src="panduan.js?v=<?= @filemtime(__DIR__.'/panduan.js') ?: 1 ?>"></script><script>addEventListener("DOMContentLoaded",()=>{(function(){const bg=document.createElement('div');bg.id='v12bg';bg.innerHTML='<i></i><i></i><i></i>';document.body.prepend(bg);let y0=null,dy=0,el=null;const th=70;
 const ind=()=>el||(el=Object.assign(document.createElement('div'),{id:'v12ptr',textContent:'↻'}),document.body.appendChild(el),el);
 addEventListener('touchstart',e=>{if(!isMobile()||scrollY>0||document.querySelector('.mask:not([hidden]),.modal'))return y0=null;y0=e.touches[0].clientY;dy=0},{passive:true});
 addEventListener('touchmove',e=>{if(y0==null)return;dy=e.touches[0].clientY-y0;if(dy>0&&scrollY<=0){ind().style.top=Math.min(dy/2,60)-40+'px';ind().style.transform='rotate('+dy*3+'deg)'}},{passive:true});
 addEventListener('touchend',()=>{if(y0==null)return;const go=dy>th*1.5;y0=null;if(!el)return;if(go&&typeof navigateTo==='function'&&typeof PAGE!=='undefined'&&PAGE){el.style.top='16px';el.classList.add('spin');Promise.resolve(navigateTo(PAGE)).finally(()=>setTimeout(()=>{el.classList.remove('spin');el.style.top='-48px'},500))}else el.style.top='-48px'});
 const m=document.getElementById('main');if(m){new MutationObserver(()=>{m.classList.remove('v12pt');void m.offsetWidth;m.classList.add('v12pt')}).observe(m,{childList:true})}
})();
});</script><script>if("serviceWorker" in navigator&&location.protocol==="https:"){const had=!!navigator.serviceWorker.controller;let shown=false;const banner=()=>{if(shown||document.getElementById("dsmUpd"))return;shown=true;const d=document.createElement("div");d.id="dsmUpd";d.setAttribute("role","status");d.innerHTML='<b>Ada pembaruan DSM</b><span>Muat ulang untuk memakai versi terbaru.</span><button type="button" id="dsmUpdGo">Muat Ulang</button><button type="button" id="dsmUpdLater" aria-label="Nanti">Nanti</button>';document.body.appendChild(d);document.getElementById("dsmUpdGo").onclick=()=>location.reload();document.getElementById("dsmUpdLater").onclick=()=>d.remove();};navigator.serviceWorker.addEventListener("controllerchange",()=>{if(had)banner()});addEventListener("load",()=>navigator.serviceWorker.register("sw.js?v=<?= @filemtime(__FILE__) ?: 1 ?>").then(reg=>{const chk=()=>reg.update().catch(()=>{});document.addEventListener("visibilitychange",()=>{if(!document.hidden)chk()});setInterval(chk,1800000)}).catch(()=>{}))}</script><style>#dsmUpd{position:fixed;left:50%;transform:translateX(-50%);bottom:calc(84px + env(safe-area-inset-bottom,0px));z-index:99999;display:flex;gap:10px;align-items:center;flex-wrap:wrap;max-width:min(560px,calc(100vw - 24px));padding:10px 14px;border-radius:16px;background:linear-gradient(135deg,rgba(99,102,241,.96),rgba(6,182,212,.96));color:#fff;box-shadow:0 10px 30px rgba(30,41,99,.35);backdrop-filter:blur(8px);font:13px/1.35 system-ui,sans-serif}#dsmUpd span{flex:1 1 160px;opacity:.95}#dsmUpd button{border:0;border-radius:999px;padding:7px 14px;font-weight:800;cursor:pointer;background:#fff;color:#4338ca}#dsmUpd #dsmUpdLater{background:rgba(255,255,255,.2);color:#fff}@media(min-width:761px){#dsmUpd{bottom:24px}}</style></head><body><div id="login" class="dlPage"><div class="dl-splash" aria-hidden="true"><div class="dl-splash-box"><img src="assets/dsm-logo.jpg" alt="DSM"><div class="dl-sweep"></div></div></div>
<div class="dl-wave"></div>
<section class="dl-shell"><section class="dl-brand"><div class="dl-pixels" aria-hidden="true"><span class="dl-px"></span><span class="dl-px"></span><span class="dl-px"></span><span class="dl-px"></span><span class="dl-px"></span></div>
<div><div class="dl-brand-wrap"><img class="dl-logo" src="assets/dsm-logo.jpg" alt="DSM Distribution Sales Monitoring"><div class="dl-headline">Pantau distribusi. <span>Ambil keputusan lebih cepat.</span></div><p class="dl-copy">Aplikasi untuk memantau performa sales, dealer, brand, KPI, dan pergerakan distribusi secara terstruktur.</p>
<div class="dl-metrics"><div class="dl-metric"><strong>Real-time View</strong><span>Monitoring harian</span></div><div class="dl-metric"><strong>Role Based</strong><span>Akses sesuai cakupan</span></div><div class="dl-metric"><strong>Data Driven</strong><span>Fokus pada aksi</span></div></div></div></div>
<div class="dl-brand-foot">DSM · Distribution Sales Monitoring</div></section>
<section class="dl-login-side"><form class="dl-card" id="loginForm" autocomplete="on"><h2>Masuk ke DSM</h2><p>Gunakan akun terdaftar untuk mengakses dashboard.</p>
<div class="lerr" id="lerr" role="alert"></div>
<div class="dl-field"><svg class="dl-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg><input id="inNik" type="text" autocomplete="username" required placeholder="NIK / Username" aria-label="NIK / Username"></div>
<div class="dl-field"><svg class="dl-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><input id="inPin" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="current-password" required placeholder="PIN" aria-label="PIN 4 digit"><span class="dl-eye" id="inPinEye" role="button" tabindex="0">Lihat</span></div>
<button class="dl-btn" id="loginBtn" type="submit">Masuk →</button><div class="dl-help-text">Lupa PIN atau belum terdaftar? Hubungi admin untuk reset.</div></form></section></section></div>
<div id="changePin" hidden><form class="lcard" id="pinForm"><div class="llogo">Ganti PIN</div><p class="ltag">Pilih PIN baru. PIN sementara harus diganti sebelum membuka data.</p><div class="lfield"><label for="newPin">PIN baru</label><input id="newPin" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required autocomplete="new-password"></div><div class="lfield"><label for="confirmPin">Ulangi PIN</label><input id="confirmPin" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required autocomplete="new-password"></div><button class="lbtn">Simpan PIN</button><button type="button" class="chip" onclick="logout()">Keluar</button><div id="pinErr" role="alert"></div></form></div>
<div id="tfa" hidden>
<div class="lcard tfaCard">
<div class="llogo">DSM</div><div class="ltag" id="tfaTag">Verifikasi 2 langkah</div>
<div class="lerr" id="tfaErr" role="alert"></div>
<form id="tfaVerify" hidden autocomplete="off">
 <p class="tfaP" id="tfaVerifyP">Buka aplikasi Authenticator di HP dan ketik kode 6 digit untuk <b>DSM</b>.</p>
 <div class="lfield"><label for="tfaCode" id="tfaCodeL">Kode 6 digit</label><input id="tfaCode" class="tfaOtp" inputmode="numeric" autocomplete="one-time-code" maxlength="12" required placeholder="000000"></div>
 <button class="lbtn" id="tfaVerifyBtn" type="submit">Masuk</button>
 <button type="button" class="tfaLink" id="tfaToggle">Pakai kode cadangan</button>
 <button type="button" class="tfaLink" data-tfa-back>Kembali ke login</button>
</form>
<div id="tfaS1" hidden>
 <p class="tfaP"><b>Aktifkan Authenticator</b> (wajib untuk ASM ke atas). Langkah 1 dari 3.</p>
 <div class="tfaStep"><b>1</b><span>Pasang <u>Google Authenticator</u> atau Microsoft Authenticator di HP.</span></div>
 <div class="tfaStep"><b>2</b><span>Tambahkan akun DSM dengan salah satu cara:</span></div>
 <div class="tfaQr" id="tfaQr" aria-label="QR code Authenticator"></div>
 <div class="tfaCap">Scan dari layar laptop</div>
 <div class="tfaCap">atau kalau memakai HP yang sama dengan yang membuka DSM:</div>
 <a class="lbtn tfaA" id="tfaOpen" href="#">Buka di Authenticator</a>
 <div class="lfield"><label>Atau masukkan kunci manual</label><div class="tfaKey" id="tfaKey">…</div></div>
 <button type="button" class="tfaGhost" id="tfaCopyKey">Salin kunci</button>
 <div class="tfaNote">Di Google Authenticator: <b>Tambah kode → Masukkan kunci setelan</b>, lalu tempel kunci di atas.</div>
 <button type="button" class="lbtn" id="tfaNext">Lanjut, saya sudah menambahkan</button>
 <button type="button" class="tfaLink" data-tfa-back>Batal</button>
</div>
<form id="tfaS2" hidden autocomplete="off">
 <p class="tfaP"><b>Verifikasi</b>. Langkah 2 dari 3. Ketik kode 6 digit yang sekarang tampil di aplikasi Authenticator.</p>
 <div class="lfield"><label for="tfaSetCode">Kode 6 digit</label><input id="tfaSetCode" class="tfaOtp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required placeholder="000000"></div>
 <button class="lbtn" id="tfaEnableBtn" type="submit">Verifikasi &amp; aktifkan</button>
 <button type="button" class="tfaLink" id="tfaPrev">Kembali ke QR</button>
</form>
<div id="tfaCodes" hidden>
 <p class="tfaP"><b>Simpan kode cadangan</b>. Langkah 3 dari 3. Dipakai kalau HP hilang atau ganti. Tiap kode hanya sekali pakai.</p>
 <div class="tfaCodes" id="tfaCodeList"></div>
 <div class="tfaNote warn"><b>Hanya muncul sekali.</b> Catat di kertas atau simpan di tempat selain HP ini.</div>
 <div class="tfaRow"><button type="button" class="tfaGhost" id="tfaCopyCodes">Salin semua</button><button type="button" class="tfaGhost" id="tfaDl">Unduh .txt</button></div>
 <label class="tfaAck"><input type="checkbox" id="tfaAck"><span>Saya sudah menyimpan kode cadangan ini</span></label>
 <button type="button" class="lbtn" id="tfaGo" disabled>Lanjutkan</button>
</div>
</div></div>
<div id="app" hidden><div class="topbar"><div class="logo"><img class="navBrand" src="assets/dsm-logo-crop.jpg" alt="DSM"></div><button class="out mobileMenu" id="menuToggle" aria-expanded="false" aria-controls="navigation">Menu</button><nav class="nav" id="navigation"></nav><div class="who"><span class="rolechip" id="roleChip"></span><span id="namaTop"></span><span class="avatar" id="avatarTop"></span><button class="out" onclick="showPin()">Ganti PIN</button><button class="out" onclick="logout()">Keluar</button></div></div><div class="updbar"><button class="mobileDataStatus" type="button" id="mobileDataStatus" aria-expanded="false" aria-controls="processDetail"><span class="dot"></span><span>Data lengkap s/d <b id="updData">—</b></span><span class="mobileChevron" aria-hidden="true">⌄</span></button><span class="sep">|</span><span class="processDetail" id="processDetail">Diproses <b id="updProses">—</b></span><button class="chip refresh" onclick="refresh()" aria-label="Perbarui tampilan" title="Perbarui tampilan"><span class="refreshIcon" aria-hidden="true">↻</span><span class="refreshLabel">Perbarui tampilan</span></button></div><div class="subbar"><div id="crumb"></div><div class="periodControls"><label for="scopeSelect">Cakupan</label><select class="fi hl" id="scopeSelect"></select><label for="periodSelect">Periode</label><select class="fi hl" id="periodSelect"></select></div></div><div id="busy" hidden role="status"><span class="v6load">Memuat data…</span></div><div id="error" hidden role="alert"></div><div class="wrap" id="main"></div><div class="wrap footnote">DSM · Distribution Sales Monitoring · Penjualan dan target tim menghitung setiap NIK sales satu kali. Aksesori hanya masuk omzet.</div></div>

<div id="zoomSuggestOverlay" class="zoomSuggestOverlay" role="dialog" aria-modal="true" aria-labelledby="zoomSuggestTitle" aria-describedby="zoomSuggestText" hidden>
  <section class="zoomSuggestCard">
    <div class="zoomSuggestIcon" aria-hidden="true">80%</div>
    <h3 id="zoomSuggestTitle">Pengaturan Tampilan Mobile</h3>
    <p id="zoomSuggestText">Untuk menampilkan lebih banyak kolom tabel, disarankan mengatur zoom browser ke <strong>80%</strong>. Sesuaikan kembali apabila teks kurang nyaman dibaca.</p>
    <div class="zoomSuggestInstruction"><strong>Chrome:</strong> ketuk <strong>⋮ → Zoom</strong>, lalu pilih <strong>80%</strong>.</div>
    <label class="zoomSuggestCheck">
      <input type="checkbox" id="dontShowZoomAgain">
      <span>Jangan tampilkan kembali di browser ini</span>
    </label>
    <button type="button" class="zoomSuggestButton" id="zoomSuggestContinue">Lanjutkan</button>
  </section>
</div>

<img id="mobileAiLogoSource" src="assets/dsm-mark.jpg" alt="" hidden>
<div class="mask" id="mask"><section class="modal" role="dialog" aria-modal="true" aria-labelledby="mtitle"><div class="mhead"><h3 id="mtitle"></h3><button class="x" aria-label="Tutup detail" onclick="closeModal()">×</button></div><div class="mbody" id="mbody"></div></section></div>
<link rel="stylesheet" href="assets/telemarketing/tm.css?v=<?=@filemtime(__DIR__.'/assets/telemarketing/tm.css')?>">
<link rel="stylesheet" href="assets/telemarketing/tm_steps.css?v=<?=@filemtime(__DIR__.'/assets/telemarketing/tm_steps.css')?>">
<link rel="stylesheet" href="assets/telemarketing/tm_order_mockup.css?v=<?=@filemtime(__DIR__.'/assets/telemarketing/tm_order_mockup.css')?>">
<script src="assets/telemarketing/tm.js?v=<?=@filemtime(__DIR__.'/assets/telemarketing/tm.js')?>"></script>
<script src="assets/telemarketing/pdf.js?v=<?=@filemtime(__DIR__.'/assets/telemarketing/pdf.js')?>"></script>
<script src="assets/telemarketing/tm_update.js?v=<?=@filemtime(__DIR__.'/assets/telemarketing/tm_update.js')?>"></script>
<script src="assets/kpikhusus.js?v=<?=@filemtime(__DIR__.'/assets/kpikhusus.js')?>"></script>
<script src="assets/notif.js?v=<?=@filemtime(__DIR__.'/assets/notif.js')?>"></script>
<link rel="stylesheet" href="assets/presentasi.css?v=<?=@filemtime(__DIR__.'/assets/presentasi.css')?>">
<script src="assets/presentasi.js?v=<?=@filemtime(__DIR__.'/assets/presentasi.js')?>"></script>
<script src="assets/searchable.js?v=<?=@filemtime(__DIR__.'/assets/searchable.js')?>"></script>
<script src="assets/kpi_export.js?v=<?=@filemtime(__DIR__.'/assets/kpi_export.js')?>"></script>
<script src="assets/telemarketing/tm_order_mockup.js?v=<?=@filemtime(__DIR__.'/assets/telemarketing/tm_order_mockup.js')?>"></script>
<script src="assets/telemarketing/tm_popup.js?v=<?=@filemtime(__DIR__.'/assets/telemarketing/tm_popup.js')?>"></script>
<script src="assets/telemarketing/tm_steps.js?v=<?=@filemtime(__DIR__.'/assets/telemarketing/tm_steps.js')?>"></script>
<script src="assets/scope_picker.js?v=<?=@filemtime(__DIR__.'/assets/scope_picker.js')?>"></script>
<script src="assets/dsm.js?v=<?=@filemtime(__DIR__.'/assets/dsm.js')?>"></script><script src="assets/ai_teach.js?v=<?=@filemtime(__DIR__.'/assets/ai_teach.js')?>"></script><script src="assets/region_labels.js?v=<?=@filemtime(__DIR__.'/assets/region_labels.js')?>"></script><script src="assets/tim_v2.js?v=<?=@filemtime(__DIR__.'/assets/tim_v2.js')?>"></script><script src="assets/dealer_status.js?v=<?=@filemtime(__DIR__.'/assets/dealer_status.js')?>"></script><script src="assets/ui_prefs.js?v=<?=@filemtime(__DIR__.'/assets/ui_prefs.js')?>"></script><script src="assets/topnav.js?v=<?=@filemtime(__DIR__.'/assets/topnav.js')?>"></script><script src="assets/publish_queue.js?v=<?=@filemtime(__DIR__.'/assets/publish_queue.js')?>"></script><script src="assets/form_kit.js?v=<?=@filemtime(__DIR__.'/assets/form_kit.js')?>"></script><script src="assets/dealer_detail.js?v=<?=@filemtime(__DIR__.'/assets/dealer_detail.js')?>"></script><script src="assets/page_tidy.js?v=<?=@filemtime(__DIR__.'/assets/page_tidy.js')?>"></script><script src="assets/page_header.js?v=<?=@filemtime(__DIR__.'/assets/page_header.js')?>"></script>
<script>
/* Tooltip desktop bergaya aplikasi: menggantikan title bawaan browser (hanya perangkat dengan hover). */
(function(){try{if(!matchMedia('(hover:hover) and (pointer:fine)').matches)return;}catch(e){return;}
 const tip=document.createElement('div');tip.id='v6tip';tip.setAttribute('role','tooltip');document.body.appendChild(tip);let cur=null,timer=0;
 const find=t=>t&&t.closest?t.closest('[title],[data-tip]'):null;
 const hide=()=>{clearTimeout(timer);tip.classList.remove('on');cur=null;};
 const place=el=>{const r=el.getBoundingClientRect(),w=tip.offsetWidth,h=tip.offsetHeight;let x=r.left+r.width/2-w/2,y=r.top-h-9;if(y<8)y=r.bottom+9;x=Math.max(8,Math.min(x,innerWidth-w-8));tip.style.left=x+'px';tip.style.top=y+'px';};
 const show=el=>{if(el.hasAttribute('title')){const t=el.getAttribute('title');if(!t.trim())return;el.dataset.tip=t;el.removeAttribute('title');if(!el.textContent.trim()&&!el.getAttribute('aria-label'))el.setAttribute('aria-label',t);}const txt=el.dataset.tip;if(!txt)return;cur=el;clearTimeout(timer);timer=setTimeout(()=>{if(cur!==el)return;tip.textContent=txt;tip.classList.add('on');place(el);},180);};
 document.addEventListener('mouseover',e=>{const el=find(e.target);if(!el){if(cur)hide();return;}if(el!==cur)show(el);},true);
 document.addEventListener('mouseout',e=>{if(cur&&!cur.contains(e.relatedTarget))hide();},true);
 document.addEventListener('focusin',e=>{const el=find(e.target);if(el)show(el);});document.addEventListener('focusout',hide);
 document.addEventListener('mousedown',hide,true);window.addEventListener('scroll',hide,true);document.addEventListener('keydown',e=>{if(e.key==='Escape')hide();});
})();
</script>
<script>
/* Tabel: kolom pertama menempel saat digeser (sticky), lebar kolom bisa ditarik. */
(function(){let queued=false;
 const enhance=()=>{queued=false;document.querySelectorAll('.table-scroll>table:not([data-v6t])').forEach(t=>{t.dataset.v6t='1';const ths=t.tHead?[...t.tHead.rows[t.tHead.rows.length-1].cells]:[];if(ths.length<4)return;t.classList.add('v6wide');ths.forEach((th,i)=>{if(i===ths.length-1&&ths.length>1&&!th.textContent.trim())return;if(getComputedStyle(th).position==='static')th.style.position='relative';const h=document.createElement('i');h.className='v6rz';h.setAttribute('aria-hidden','true');h.title='';th.appendChild(h);h.addEventListener('click',e=>e.stopPropagation());h.addEventListener('pointerdown',e=>{e.preventDefault();e.stopPropagation();const x0=e.clientX,w0=th.getBoundingClientRect().width;h.setPointerCapture(e.pointerId);document.body.classList.add('v6resizing');const mv=ev=>{const w=Math.max(44,w0+ev.clientX-x0);th.style.width=w+'px';th.style.minWidth=w+'px';th.style.maxWidth=w+'px';};const up=()=>{h.removeEventListener('pointermove',mv);h.removeEventListener('pointerup',up);document.body.classList.remove('v6resizing');};h.addEventListener('pointermove',mv);h.addEventListener('pointerup',up);});h.addEventListener('dblclick',e=>{e.stopPropagation();th.style.width=th.style.minWidth=th.style.maxWidth='';});});});};
 const q=()=>{if(queued)return;queued=true;requestAnimationFrame(enhance);};
 new MutationObserver(q).observe(document.body,{childList:true,subtree:true});q();
})();
</script>
<script>/* qrcode-generator 2.0.4 (MIT, Kazuhiko Arase) */
var qrcode=function(){var t=function(t,r){var e=t,n=g[r],o=null,i=0,a=null,u=[],f={},c=function(t,r){o=function(t){for(var r=new Array(t),e=0;e<t;e+=1){r[e]=new Array(t);for(var n=0;n<t;n+=1)r[e][n]=null}return r}(i=4*e+17),l(0,0),l(i-7,0),l(0,i-7),s(),h(),d(t,r),e>=7&&v(t),null==a&&(a=p(e,n,u)),w(a,r)},l=function(t,r){for(var e=-1;e<=7;e+=1)if(!(t+e<=-1||i<=t+e))for(var n=-1;n<=7;n+=1)r+n<=-1||i<=r+n||(o[t+e][r+n]=0<=e&&e<=6&&(0==n||6==n)||0<=n&&n<=6&&(0==e||6==e)||2<=e&&e<=4&&2<=n&&n<=4)},h=function(){for(var t=8;t<i-8;t+=1)null==o[t][6]&&(o[t][6]=t%2==0);for(var r=8;r<i-8;r+=1)null==o[6][r]&&(o[6][r]=r%2==0)},s=function(){for(var t=B.getPatternPosition(e),r=0;r<t.length;r+=1)for(var n=0;n<t.length;n+=1){var i=t[r],a=t[n];if(null==o[i][a])for(var u=-2;u<=2;u+=1)for(var f=-2;f<=2;f+=1)o[i+u][a+f]=-2==u||2==u||-2==f||2==f||0==u&&0==f}},v=function(t){for(var r=B.getBCHTypeNumber(e),n=0;n<18;n+=1){var a=!t&&1==(r>>n&1);o[Math.floor(n/3)][n%3+i-8-3]=a}for(n=0;n<18;n+=1){a=!t&&1==(r>>n&1);o[n%3+i-8-3][Math.floor(n/3)]=a}},d=function(t,r){for(var e=n<<3|r,a=B.getBCHTypeInfo(e),u=0;u<15;u+=1){var f=!t&&1==(a>>u&1);u<6?o[u][8]=f:u<8?o[u+1][8]=f:o[i-15+u][8]=f}for(u=0;u<15;u+=1){f=!t&&1==(a>>u&1);u<8?o[8][i-u-1]=f:u<9?o[8][15-u-1+1]=f:o[8][15-u-1]=f}o[i-8][8]=!t},w=function(t,r){for(var e=-1,n=i-1,a=7,u=0,f=B.getMaskFunction(r),c=i-1;c>0;c-=2)for(6==c&&(c-=1);;){for(var g=0;g<2;g+=1)if(null==o[n][c-g]){var l=!1;u<t.length&&(l=1==(t[u]>>>a&1)),f(n,c-g)&&(l=!l),o[n][c-g]=l,-1==(a-=1)&&(u+=1,a=7)}if((n+=e)<0||i<=n){n-=e,e=-e;break}}},p=function(t,r,e){for(var n=A.getRSBlocks(t,r),o=b(),i=0;i<e.length;i+=1){var a=e[i];o.put(a.getMode(),4),o.put(a.getLength(),B.getLengthInBits(a.getMode(),t)),a.write(o)}var u=0;for(i=0;i<n.length;i+=1)u+=n[i].dataCount;if(o.getLengthInBits()>8*u)throw"code length overflow. ("+o.getLengthInBits()+">"+8*u+")";for(o.getLengthInBits()+4<=8*u&&o.put(0,4);o.getLengthInBits()%8!=0;)o.putBit(!1);for(;!(o.getLengthInBits()>=8*u||(o.put(236,8),o.getLengthInBits()>=8*u));)o.put(17,8);return function(t,r){for(var e=0,n=0,o=0,i=new Array(r.length),a=new Array(r.length),u=0;u<r.length;u+=1){var f=r[u].dataCount,c=r[u].totalCount-f;n=Math.max(n,f),o=Math.max(o,c),i[u]=new Array(f);for(var g=0;g<i[u].length;g+=1)i[u][g]=255&t.getBuffer()[g+e];e+=f;var l=B.getErrorCorrectPolynomial(c),h=k(i[u],l.getLength()-1).mod(l);for(a[u]=new Array(l.getLength()-1),g=0;g<a[u].length;g+=1){var s=g+h.getLength()-a[u].length;a[u][g]=s>=0?h.getAt(s):0}}var v=0;for(g=0;g<r.length;g+=1)v+=r[g].totalCount;var d=new Array(v),w=0;for(g=0;g<n;g+=1)for(u=0;u<r.length;u+=1)g<i[u].length&&(d[w]=i[u][g],w+=1);for(g=0;g<o;g+=1)for(u=0;u<r.length;u+=1)g<a[u].length&&(d[w]=a[u][g],w+=1);return d}(o,n)};f.addData=function(t,r){var e=null;switch(r=r||"Byte"){case"Numeric":e=M(t);break;case"Alphanumeric":e=x(t);break;case"Byte":e=m(t);break;case"Kanji":e=L(t);break;default:throw"mode:"+r}u.push(e),a=null},f.isDark=function(t,r){if(t<0||i<=t||r<0||i<=r)throw t+","+r;return o[t][r]},f.getModuleCount=function(){return i},f.make=function(){if(e<1){for(var t=1;t<40;t++){for(var r=A.getRSBlocks(t,n),o=b(),i=0;i<u.length;i++){var a=u[i];o.put(a.getMode(),4),o.put(a.getLength(),B.getLengthInBits(a.getMode(),t)),a.write(o)}var g=0;for(i=0;i<r.length;i++)g+=r[i].dataCount;if(o.getLengthInBits()<=8*g)break}e=t}c(!1,function(){for(var t=0,r=0,e=0;e<8;e+=1){c(!0,e);var n=B.getLostPoint(f);(0==e||t>n)&&(t=n,r=e)}return r}())},f.createTableTag=function(t,r){t=t||2;var e="";e+='<table style="',e+=" border-width: 0px; border-style: none;",e+=" border-collapse: collapse;",e+=" padding: 0px; margin: "+(r=void 0===r?4*t:r)+"px;",e+='">',e+="<tbody>";for(var n=0;n<f.getModuleCount();n+=1){e+="<tr>";for(var o=0;o<f.getModuleCount();o+=1)e+='<td style="',e+=" border-width: 0px; border-style: none;",e+=" border-collapse: collapse;",e+=" padding: 0px; margin: 0px;",e+=" width: "+t+"px;",e+=" height: "+t+"px;",e+=" background-color: ",e+=f.isDark(n,o)?"#000000":"#ffffff",e+=";",e+='"/>';e+="</tr>"}return e+="</tbody>",e+="</table>"},f.createSvgTag=function(t,r,e,n){var o={};"object"==typeof arguments[0]&&(t=(o=arguments[0]).cellSize,r=o.margin,e=o.alt,n=o.title),t=t||2,r=void 0===r?4*t:r,(e="string"==typeof e?{text:e}:e||{}).text=e.text||null,e.id=e.text?e.id||"qrcode-description":null,(n="string"==typeof n?{text:n}:n||{}).text=n.text||null,n.id=n.text?n.id||"qrcode-title":null;var i,a,u,c,g=f.getModuleCount()*t+2*r,l="";for(c="l"+t+",0 0,"+t+" -"+t+",0 0,-"+t+"z ",l+='<svg version="1.1" xmlns="http://www.w3.org/2000/svg"',l+=o.scalable?"":' width="'+g+'px" height="'+g+'px"',l+=' viewBox="0 0 '+g+" "+g+'" ',l+=' preserveAspectRatio="xMinYMin meet"',l+=n.text||e.text?' role="img" aria-labelledby="'+y([n.id,e.id].join(" ").trim())+'"':"",l+=">",l+=n.text?'<title id="'+y(n.id)+'">'+y(n.text)+"</title>":"",l+=e.text?'<description id="'+y(e.id)+'">'+y(e.text)+"</description>":"",l+='<rect width="100%" height="100%" fill="white" cx="0" cy="0"/>',l+='<path d="',a=0;a<f.getModuleCount();a+=1)for(u=a*t+r,i=0;i<f.getModuleCount();i+=1)f.isDark(a,i)&&(l+="M"+(i*t+r)+","+u+c);return l+='" stroke="transparent" fill="black"/>',l+="</svg>"},f.createDataURL=function(t,r){t=t||2,r=void 0===r?4*t:r;var e=f.getModuleCount()*t+2*r,n=r,o=e-r;return I(e,e,function(r,e){if(n<=r&&r<o&&n<=e&&e<o){var i=Math.floor((r-n)/t),a=Math.floor((e-n)/t);return f.isDark(a,i)?0:1}return 1})},f.createImgTag=function(t,r,e){t=t||2,r=void 0===r?4*t:r;var n=f.getModuleCount()*t+2*r,o="";return o+="<img",o+=' src="',o+=f.createDataURL(t,r),o+='"',o+=' width="',o+=n,o+='"',o+=' height="',o+=n,o+='"',e&&(o+=' alt="',o+=y(e),o+='"'),o+="/>"};var y=function(t){for(var r="",e=0;e<t.length;e+=1){var n=t.charAt(e);switch(n){case"<":r+="&lt;";break;case">":r+="&gt;";break;case"&":r+="&amp;";break;case'"':r+="&quot;";break;default:r+=n}}return r};return f.createASCII=function(t,r){if((t=t||1)<2)return function(t){t=void 0===t?2:t;var r,e,n,o,i,a=1*f.getModuleCount()+2*t,u=t,c=a-t,g={"██":"█","█ ":"▀"," █":"▄","  ":" "},l={"██":"▀","█ ":"▀"," █":" ","  ":" "},h="";for(r=0;r<a;r+=2){for(n=Math.floor((r-u)/1),o=Math.floor((r+1-u)/1),e=0;e<a;e+=1)i="█",u<=e&&e<c&&u<=r&&r<c&&f.isDark(n,Math.floor((e-u)/1))&&(i=" "),u<=e&&e<c&&u<=r+1&&r+1<c&&f.isDark(o,Math.floor((e-u)/1))?i+=" ":i+="█",h+=t<1&&r+1>=c?l[i]:g[i];h+="\n"}return a%2&&t>0?h.substring(0,h.length-a-1)+Array(a+1).join("▀"):h.substring(0,h.length-1)}(r);t-=1,r=void 0===r?2*t:r;var e,n,o,i,a=f.getModuleCount()*t+2*r,u=r,c=a-r,g=Array(t+1).join("██"),l=Array(t+1).join("  "),h="",s="";for(e=0;e<a;e+=1){for(o=Math.floor((e-u)/t),s="",n=0;n<a;n+=1)i=1,u<=n&&n<c&&u<=e&&e<c&&f.isDark(o,Math.floor((n-u)/t))&&(i=0),s+=i?g:l;for(o=0;o<t;o+=1)h+=s+"\n"}return h.substring(0,h.length-1)},f.renderTo2dContext=function(t,r){r=r||2;for(var e=f.getModuleCount(),n=0;n<e;n++)for(var o=0;o<e;o++)t.fillStyle=f.isDark(n,o)?"black":"white",t.fillRect(o*r,n*r,r,r)},f};t.stringToBytes=(t.stringToBytesFuncs={default:function(t){for(var r=[],e=0;e<t.length;e+=1){var n=t.charCodeAt(e);r.push(255&n)}return r}}).default,t.createStringToBytes=function(t,r){var e=function(){for(var e=S(t),n=function(){var t=e.read();if(-1==t)throw"eof";return t},o=0,i={};;){var a=e.read();if(-1==a)break;var u=n(),f=n()<<8|n();i[String.fromCharCode(a<<8|u)]=f,o+=1}if(o!=r)throw o+" != "+r;return i}(),n="?".charCodeAt(0);return function(t){for(var r=[],o=0;o<t.length;o+=1){var i=t.charCodeAt(o);if(i<128)r.push(i);else{var a=e[t.charAt(o)];"number"==typeof a?(255&a)==a?r.push(a):(r.push(a>>>8),r.push(255&a)):r.push(n)}}return r}};var r,e,n,o,i,a=1,u=2,f=4,c=8,g={L:1,M:0,Q:3,H:2},l=0,h=1,s=2,v=3,d=4,w=5,p=6,y=7,B=(r=[[],[6,18],[6,22],[6,26],[6,30],[6,34],[6,22,38],[6,24,42],[6,26,46],[6,28,50],[6,30,54],[6,32,58],[6,34,62],[6,26,46,66],[6,26,48,70],[6,26,50,74],[6,30,54,78],[6,30,56,82],[6,30,58,86],[6,34,62,90],[6,28,50,72,94],[6,26,50,74,98],[6,30,54,78,102],[6,28,54,80,106],[6,32,58,84,110],[6,30,58,86,114],[6,34,62,90,118],[6,26,50,74,98,122],[6,30,54,78,102,126],[6,26,52,78,104,130],[6,30,56,82,108,134],[6,34,60,86,112,138],[6,30,58,86,114,142],[6,34,62,90,118,146],[6,30,54,78,102,126,150],[6,24,50,76,102,128,154],[6,28,54,80,106,132,158],[6,32,58,84,110,136,162],[6,26,54,82,110,138,166],[6,30,58,86,114,142,170]],e=1335,n=7973,i=function(t){for(var r=0;0!=t;)r+=1,t>>>=1;return r},(o={}).getBCHTypeInfo=function(t){for(var r=t<<10;i(r)-i(e)>=0;)r^=e<<i(r)-i(e);return 21522^(t<<10|r)},o.getBCHTypeNumber=function(t){for(var r=t<<12;i(r)-i(n)>=0;)r^=n<<i(r)-i(n);return t<<12|r},o.getPatternPosition=function(t){return r[t-1]},o.getMaskFunction=function(t){switch(t){case l:return function(t,r){return(t+r)%2==0};case h:return function(t,r){return t%2==0};case s:return function(t,r){return r%3==0};case v:return function(t,r){return(t+r)%3==0};case d:return function(t,r){return(Math.floor(t/2)+Math.floor(r/3))%2==0};case w:return function(t,r){return t*r%2+t*r%3==0};case p:return function(t,r){return(t*r%2+t*r%3)%2==0};case y:return function(t,r){return(t*r%3+(t+r)%2)%2==0};default:throw"bad maskPattern:"+t}},o.getErrorCorrectPolynomial=function(t){for(var r=k([1],0),e=0;e<t;e+=1)r=r.multiply(k([1,C.gexp(e)],0));return r},o.getLengthInBits=function(t,r){if(1<=r&&r<10)switch(t){case a:return 10;case u:return 9;case f:case c:return 8;default:throw"mode:"+t}else if(r<27)switch(t){case a:return 12;case u:return 11;case f:return 16;case c:return 10;default:throw"mode:"+t}else{if(!(r<41))throw"type:"+r;switch(t){case a:return 14;case u:return 13;case f:return 16;case c:return 12;default:throw"mode:"+t}}},o.getLostPoint=function(t){for(var r=t.getModuleCount(),e=0,n=0;n<r;n+=1)for(var o=0;o<r;o+=1){for(var i=0,a=t.isDark(n,o),u=-1;u<=1;u+=1)if(!(n+u<0||r<=n+u))for(var f=-1;f<=1;f+=1)o+f<0||r<=o+f||0==u&&0==f||a==t.isDark(n+u,o+f)&&(i+=1);i>5&&(e+=3+i-5)}for(n=0;n<r-1;n+=1)for(o=0;o<r-1;o+=1){var c=0;t.isDark(n,o)&&(c+=1),t.isDark(n+1,o)&&(c+=1),t.isDark(n,o+1)&&(c+=1),t.isDark(n+1,o+1)&&(c+=1),0!=c&&4!=c||(e+=3)}for(n=0;n<r;n+=1)for(o=0;o<r-6;o+=1)t.isDark(n,o)&&!t.isDark(n,o+1)&&t.isDark(n,o+2)&&t.isDark(n,o+3)&&t.isDark(n,o+4)&&!t.isDark(n,o+5)&&t.isDark(n,o+6)&&(e+=40);for(o=0;o<r;o+=1)for(n=0;n<r-6;n+=1)t.isDark(n,o)&&!t.isDark(n+1,o)&&t.isDark(n+2,o)&&t.isDark(n+3,o)&&t.isDark(n+4,o)&&!t.isDark(n+5,o)&&t.isDark(n+6,o)&&(e+=40);var g=0;for(o=0;o<r;o+=1)for(n=0;n<r;n+=1)t.isDark(n,o)&&(g+=1);return e+=Math.abs(100*g/r/r-50)/5*10},o),C=function(){for(var t=new Array(256),r=new Array(256),e=0;e<8;e+=1)t[e]=1<<e;for(e=8;e<256;e+=1)t[e]=t[e-4]^t[e-5]^t[e-6]^t[e-8];for(e=0;e<255;e+=1)r[t[e]]=e;var n={glog:function(t){if(t<1)throw"glog("+t+")";return r[t]},gexp:function(r){for(;r<0;)r+=255;for(;r>=256;)r-=255;return t[r]}};return n}();function k(t,r){if(void 0===t.length)throw t.length+"/"+r;var e=function(){for(var e=0;e<t.length&&0==t[e];)e+=1;for(var n=new Array(t.length-e+r),o=0;o<t.length-e;o+=1)n[o]=t[o+e];return n}(),n={getAt:function(t){return e[t]},getLength:function(){return e.length},multiply:function(t){for(var r=new Array(n.getLength()+t.getLength()-1),e=0;e<n.getLength();e+=1)for(var o=0;o<t.getLength();o+=1)r[e+o]^=C.gexp(C.glog(n.getAt(e))+C.glog(t.getAt(o)));return k(r,0)},mod:function(t){if(n.getLength()-t.getLength()<0)return n;for(var r=C.glog(n.getAt(0))-C.glog(t.getAt(0)),e=new Array(n.getLength()),o=0;o<n.getLength();o+=1)e[o]=n.getAt(o);for(o=0;o<t.getLength();o+=1)e[o]^=C.gexp(C.glog(t.getAt(o))+r);return k(e,0).mod(t)}};return n}var A=function(){var t=[[1,26,19],[1,26,16],[1,26,13],[1,26,9],[1,44,34],[1,44,28],[1,44,22],[1,44,16],[1,70,55],[1,70,44],[2,35,17],[2,35,13],[1,100,80],[2,50,32],[2,50,24],[4,25,9],[1,134,108],[2,67,43],[2,33,15,2,34,16],[2,33,11,2,34,12],[2,86,68],[4,43,27],[4,43,19],[4,43,15],[2,98,78],[4,49,31],[2,32,14,4,33,15],[4,39,13,1,40,14],[2,121,97],[2,60,38,2,61,39],[4,40,18,2,41,19],[4,40,14,2,41,15],[2,146,116],[3,58,36,2,59,37],[4,36,16,4,37,17],[4,36,12,4,37,13],[2,86,68,2,87,69],[4,69,43,1,70,44],[6,43,19,2,44,20],[6,43,15,2,44,16],[4,101,81],[1,80,50,4,81,51],[4,50,22,4,51,23],[3,36,12,8,37,13],[2,116,92,2,117,93],[6,58,36,2,59,37],[4,46,20,6,47,21],[7,42,14,4,43,15],[4,133,107],[8,59,37,1,60,38],[8,44,20,4,45,21],[12,33,11,4,34,12],[3,145,115,1,146,116],[4,64,40,5,65,41],[11,36,16,5,37,17],[11,36,12,5,37,13],[5,109,87,1,110,88],[5,65,41,5,66,42],[5,54,24,7,55,25],[11,36,12,7,37,13],[5,122,98,1,123,99],[7,73,45,3,74,46],[15,43,19,2,44,20],[3,45,15,13,46,16],[1,135,107,5,136,108],[10,74,46,1,75,47],[1,50,22,15,51,23],[2,42,14,17,43,15],[5,150,120,1,151,121],[9,69,43,4,70,44],[17,50,22,1,51,23],[2,42,14,19,43,15],[3,141,113,4,142,114],[3,70,44,11,71,45],[17,47,21,4,48,22],[9,39,13,16,40,14],[3,135,107,5,136,108],[3,67,41,13,68,42],[15,54,24,5,55,25],[15,43,15,10,44,16],[4,144,116,4,145,117],[17,68,42],[17,50,22,6,51,23],[19,46,16,6,47,17],[2,139,111,7,140,112],[17,74,46],[7,54,24,16,55,25],[34,37,13],[4,151,121,5,152,122],[4,75,47,14,76,48],[11,54,24,14,55,25],[16,45,15,14,46,16],[6,147,117,4,148,118],[6,73,45,14,74,46],[11,54,24,16,55,25],[30,46,16,2,47,17],[8,132,106,4,133,107],[8,75,47,13,76,48],[7,54,24,22,55,25],[22,45,15,13,46,16],[10,142,114,2,143,115],[19,74,46,4,75,47],[28,50,22,6,51,23],[33,46,16,4,47,17],[8,152,122,4,153,123],[22,73,45,3,74,46],[8,53,23,26,54,24],[12,45,15,28,46,16],[3,147,117,10,148,118],[3,73,45,23,74,46],[4,54,24,31,55,25],[11,45,15,31,46,16],[7,146,116,7,147,117],[21,73,45,7,74,46],[1,53,23,37,54,24],[19,45,15,26,46,16],[5,145,115,10,146,116],[19,75,47,10,76,48],[15,54,24,25,55,25],[23,45,15,25,46,16],[13,145,115,3,146,116],[2,74,46,29,75,47],[42,54,24,1,55,25],[23,45,15,28,46,16],[17,145,115],[10,74,46,23,75,47],[10,54,24,35,55,25],[19,45,15,35,46,16],[17,145,115,1,146,116],[14,74,46,21,75,47],[29,54,24,19,55,25],[11,45,15,46,46,16],[13,145,115,6,146,116],[14,74,46,23,75,47],[44,54,24,7,55,25],[59,46,16,1,47,17],[12,151,121,7,152,122],[12,75,47,26,76,48],[39,54,24,14,55,25],[22,45,15,41,46,16],[6,151,121,14,152,122],[6,75,47,34,76,48],[46,54,24,10,55,25],[2,45,15,64,46,16],[17,152,122,4,153,123],[29,74,46,14,75,47],[49,54,24,10,55,25],[24,45,15,46,46,16],[4,152,122,18,153,123],[13,74,46,32,75,47],[48,54,24,14,55,25],[42,45,15,32,46,16],[20,147,117,4,148,118],[40,75,47,7,76,48],[43,54,24,22,55,25],[10,45,15,67,46,16],[19,148,118,6,149,119],[18,75,47,31,76,48],[34,54,24,34,55,25],[20,45,15,61,46,16]],r=function(t,r){var e={};return e.totalCount=t,e.dataCount=r,e},e={};return e.getRSBlocks=function(e,n){var o=function(r,e){switch(e){case g.L:return t[4*(r-1)+0];case g.M:return t[4*(r-1)+1];case g.Q:return t[4*(r-1)+2];case g.H:return t[4*(r-1)+3];default:return}}(e,n);if(void 0===o)throw"bad rs block @ typeNumber:"+e+"/errorCorrectionLevel:"+n;for(var i=o.length/3,a=[],u=0;u<i;u+=1)for(var f=o[3*u+0],c=o[3*u+1],l=o[3*u+2],h=0;h<f;h+=1)a.push(r(c,l));return a},e}(),b=function(){var t=[],r=0,e={getBuffer:function(){return t},getAt:function(r){var e=Math.floor(r/8);return 1==(t[e]>>>7-r%8&1)},put:function(t,r){for(var n=0;n<r;n+=1)e.putBit(1==(t>>>r-n-1&1))},getLengthInBits:function(){return r},putBit:function(e){var n=Math.floor(r/8);t.length<=n&&t.push(0),e&&(t[n]|=128>>>r%8),r+=1}};return e},M=function(t){var r=a,e=t,n={getMode:function(){return r},getLength:function(t){return e.length},write:function(t){for(var r=e,n=0;n+2<r.length;)t.put(o(r.substring(n,n+3)),10),n+=3;n<r.length&&(r.length-n==1?t.put(o(r.substring(n,n+1)),4):r.length-n==2&&t.put(o(r.substring(n,n+2)),7))}},o=function(t){for(var r=0,e=0;e<t.length;e+=1)r=10*r+i(t.charAt(e));return r},i=function(t){if("0"<=t&&t<="9")return t.charCodeAt(0)-"0".charCodeAt(0);throw"illegal char :"+t};return n},x=function(t){var r=u,e=t,n={getMode:function(){return r},getLength:function(t){return e.length},write:function(t){for(var r=e,n=0;n+1<r.length;)t.put(45*o(r.charAt(n))+o(r.charAt(n+1)),11),n+=2;n<r.length&&t.put(o(r.charAt(n)),6)}},o=function(t){if("0"<=t&&t<="9")return t.charCodeAt(0)-"0".charCodeAt(0);if("A"<=t&&t<="Z")return t.charCodeAt(0)-"A".charCodeAt(0)+10;switch(t){case" ":return 36;case"$":return 37;case"%":return 38;case"*":return 39;case"+":return 40;case"-":return 41;case".":return 42;case"/":return 43;case":":return 44;default:throw"illegal char :"+t}};return n},m=function(r){var e=f,n=t.stringToBytes(r),o={getMode:function(){return e},getLength:function(t){return n.length},write:function(t){for(var r=0;r<n.length;r+=1)t.put(n[r],8)}};return o},L=function(r){var e=c,n=t.stringToBytesFuncs.SJIS;if(!n)throw"sjis not supported.";!function(){var t=n("友");if(2!=t.length||38726!=(t[0]<<8|t[1]))throw"sjis not supported."}();var o=n(r),i={getMode:function(){return e},getLength:function(t){return~~(o.length/2)},write:function(t){for(var r=o,e=0;e+1<r.length;){var n=(255&r[e])<<8|255&r[e+1];if(33088<=n&&n<=40956)n-=33088;else{if(!(57408<=n&&n<=60351))throw"illegal char at "+(e+1)+"/"+n;n-=49472}n=192*(n>>>8&255)+(255&n),t.put(n,13),e+=2}if(e<r.length)throw"illegal char at "+(e+1)}};return i},D=function(){var t=[],r={writeByte:function(r){t.push(255&r)},writeShort:function(t){r.writeByte(t),r.writeByte(t>>>8)},writeBytes:function(t,e,n){e=e||0,n=n||t.length;for(var o=0;o<n;o+=1)r.writeByte(t[o+e])},writeString:function(t){for(var e=0;e<t.length;e+=1)r.writeByte(t.charCodeAt(e))},toByteArray:function(){return t},toString:function(){var r="";r+="[";for(var e=0;e<t.length;e+=1)e>0&&(r+=","),r+=t[e];return r+="]"}};return r},S=function(t){var r=t,e=0,n=0,o=0,i={read:function(){for(;o<8;){if(e>=r.length){if(0==o)return-1;throw"unexpected end of file./"+o}var t=r.charAt(e);if(e+=1,"="==t)return o=0,-1;t.match(/^\s$/)||(n=n<<6|a(t.charCodeAt(0)),o+=6)}var i=n>>>o-8&255;return o-=8,i}},a=function(t){if(65<=t&&t<=90)return t-65;if(97<=t&&t<=122)return t-97+26;if(48<=t&&t<=57)return t-48+52;if(43==t)return 62;if(47==t)return 63;throw"c:"+t};return i},I=function(t,r,e){for(var n=function(t,r){var e=t,n=r,o=new Array(t*r),i={setPixel:function(t,r,n){o[r*e+t]=n},write:function(t){t.writeString("GIF87a"),t.writeShort(e),t.writeShort(n),t.writeByte(128),t.writeByte(0),t.writeByte(0),t.writeByte(0),t.writeByte(0),t.writeByte(0),t.writeByte(255),t.writeByte(255),t.writeByte(255),t.writeString(","),t.writeShort(0),t.writeShort(0),t.writeShort(e),t.writeShort(n),t.writeByte(0);var r=a(2);t.writeByte(2);for(var o=0;r.length-o>255;)t.writeByte(255),t.writeBytes(r,o,255),o+=255;t.writeByte(r.length-o),t.writeBytes(r,o,r.length-o),t.writeByte(0),t.writeString(";")}},a=function(t){for(var r=1<<t,e=1+(1<<t),n=t+1,i=u(),a=0;a<r;a+=1)i.add(String.fromCharCode(a));i.add(String.fromCharCode(r)),i.add(String.fromCharCode(e));var f,c,g,l=D(),h=(f=l,c=0,g=0,{write:function(t,r){if(t>>>r!=0)throw"length over";for(;c+r>=8;)f.writeByte(255&(t<<c|g)),r-=8-c,t>>>=8-c,g=0,c=0;g|=t<<c,c+=r},flush:function(){c>0&&f.writeByte(g)}});h.write(r,n);var s=0,v=String.fromCharCode(o[s]);for(s+=1;s<o.length;){var d=String.fromCharCode(o[s]);s+=1,i.contains(v+d)?v+=d:(h.write(i.indexOf(v),n),i.size()<4095&&(i.size()==1<<n&&(n+=1),i.add(v+d)),v=d)}return h.write(i.indexOf(v),n),h.write(e,n),h.flush(),l.toByteArray()},u=function(){var t={},r=0,e={add:function(n){if(e.contains(n))throw"dup key:"+n;t[n]=r,r+=1},size:function(){return r},indexOf:function(r){return t[r]},contains:function(r){return void 0!==t[r]}};return e};return i}(t,r),o=0;o<r;o+=1)for(var i=0;i<t;i+=1)n.setPixel(i,o,e(i,o));var a=D();n.write(a);for(var u=function(){var t=0,r=0,e=0,n="",o={},i=function(t){n+=String.fromCharCode(a(63&t))},a=function(t){if(t<0);else{if(t<26)return 65+t;if(t<52)return t-26+97;if(t<62)return t-52+48;if(62==t)return 43;if(63==t)return 47}throw"n:"+t};return o.writeByte=function(n){for(t=t<<8|255&n,r+=8,e+=1;r>=6;)i(t>>>r-6),r-=6},o.flush=function(){if(r>0&&(i(t<<6-r),t=0,r=0),e%3!=0)for(var o=3-e%3,a=0;a<o;a+=1)n+="="},o.toString=function(){return n},o}(),f=a.toByteArray(),c=0;c<f.length;c+=1)u.writeByte(f[c]);return u.flush(),"data:image/gif;base64,"+u};return t}();qrcode.stringToBytesFuncs["UTF-8"]=function(t){return function(t){for(var r=[],e=0;e<t.length;e++){var n=t.charCodeAt(e);n<128?r.push(n):n<2048?r.push(192|n>>6,128|63&n):n<55296||n>=57344?r.push(224|n>>12,128|n>>6&63,128|63&n):(e++,n=65536+((1023&n)<<10|1023&t.charCodeAt(e)),r.push(240|n>>18,128|n>>12&63,128|n>>6&63,128|63&n))}return r}(t)},function(t){"function"==typeof define&&define.amd?define([],t):"object"==typeof exports&&(module.exports=t())}(function(){return qrcode});
</script>
<script id="v7ux">/* =====================================================================
   v7 UX — FASE 1 (revisi UX 2026-10)
   Enhancer idempoten: sidebar tree + menu akun, status data di header,
   tabel (bayangan geser), catatan ⓘ, chip filter + auto-apply, bottom
   sheet filter di HP. Semua berjalan ulang setiap #main dirender ulang.
   ===================================================================== */
(function(){
 'use strict';
 const $q=(s,r=document)=>r.querySelector(s),$$=(s,r=document)=>[...r.querySelectorAll(s)];
 const isDesk=()=>window.matchMedia('(min-width:761px)').matches;
 const SVG=p=>`<svg viewBox="0 0 24 24" aria-hidden="true">${p}</svg>`;
 const ICON={eye:SVG('<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>'),chart:SVG('<path d="M3 20h18M6 16l4-5 3 3 5-7"/>'),dots:SVG('<circle cx="5" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="19" cy="12" r="1.3"/>'),dev:SVG('<rect x="7" y="3" width="10" height="18" rx="2"/><path d="M11 18h2"/>'),key:SVG('<circle cx="8" cy="14" r="4"/><path d="M11 11l9-9M17 5l2 2"/>'),out:SVG('<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h11"/>'),play:SVG('<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M10 9l4 2.5-4 2.5zM8 21h8"/>'),user:SVG('<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>')};
 /* warna chip ikon per menu (pastel) */
 const CHIP={beranda:['#E6F0FB','#1F7FC2'],customer:['#FFF0DD','#D9770B'],brand:['#F0EBFD','#6D3FD8'],tim:['#E5F6EE','#12866A'],dos:['#E5F4F9','#1593B5'],analisa:['#F0EBFD','#6D3FD8'],kpi:['#E6F0FB','#1F7FC2'],peringkat:['#FFF0DD','#D9770B'],transaksi:['#FDE8EE','#D23A68'],informasi:['#EEF1F5','#5B6778'],ai:['#E5F4F9','#1593B5'],admin:['#EEF1F5','#5B6778'],perangkat:['#EEF1F5','#5B6778']};
 const GROUPS=[{key:'pantau',label:'Pantau',icon:'eye',grad:'linear-gradient(135deg,#1F7FC2,#3B9BE0)',line:'#D7E6F5',pages:['customer','brand','tim','dos','tmfu','tmorder','tmactivity']},{key:'analisa',label:'Analisa',icon:'chart',grad:'linear-gradient(135deg,#6D3FD8,#9A72F0)',line:'#E6DDFB',pages:['ai','analisa','kpi','peringkat','transaksi']},{key:'lain',label:'Lainnya',icon:'dots',grad:'#9AA6B5',line:'#E3E8EF',pages:['informasi','transfer','koreksi','targetso','ai','admin']}];

 /* ---------- Sidebar tree (desktop) ---------- */
 let navBusy=false;
 function v7Nav(){
  const nav=$q('#navigation');if(!nav||navBusy)return;
  const btns=$$('button[data-page]',nav);if(!btns.length)return;
  btns.forEach(b=>{const c=CHIP[b.dataset.page];if(c){b.style.setProperty('--v7c-bg',c[0]);b.style.setProperty('--v7c-fg',c[1]);}});
  if(!isDesk()){unNav(nav);return;}
  if(nav.dataset.v7tree===btns.map(b=>b.dataset.page).join(','))return;
  navBusy=true;
  unNav(nav);
  const by=Object.fromEntries(btns.map(b=>[b.dataset.page,b]));
  const frag=document.createDocumentFragment();
  if(by.beranda)frag.append(by.beranda);
  GROUPS.forEach(g=>{const items=g.pages.map(p=>by[p]).filter(Boolean);if(!items.length)return;const h=document.createElement('div');h.className='v7grp';h.innerHTML=`<i style="background:${g.grad}">${ICON[g.icon]}</i><span>${g.label}</span>`;const t=document.createElement('div');t.className='v7tree';t.style.setProperty('--v7tl',g.line);t.append(...items);frag.append(h,t);});
  /* menu yang tidak masuk grup (selain Perangkat Saya) tetap tampil di bawah */
  btns.filter(b=>b.dataset.page!=='beranda'&&b.dataset.page!=='perangkat'&&b.dataset.page!=='telegram'&&!GROUPS.some(g=>g.pages.includes(b.dataset.page))).forEach(b=>frag.append(b));
  if(by.perangkat){by.perangkat.hidden=true;frag.append(by.perangkat);}if(by.telegram){by.telegram.hidden=true;frag.append(by.telegram);}
  nav.append(frag);
  nav.dataset.v7tree=btns.map(b=>b.dataset.page).join(',');
  navBusy=false;
 }
 function unNav(nav){
  if(!$q('.v7tree,.v7grp',nav)){const p=$q('button[data-page="perangkat"]',nav);if(p&&!isDesk())p.hidden=false;const t=$q('button[data-page="telegram"]',nav);if(t&&!isDesk())t.hidden=false;return;}
  $$('.v7tree',nav).forEach(t=>t.replaceWith(...t.children));$$('.v7grp',nav).forEach(g=>g.remove());
  const p=$q('button[data-page="perangkat"]',nav);if(p)p.hidden=false;const t=$q('button[data-page="telegram"]',nav);if(t)t.hidden=false;delete nav.dataset.v7tree;
 }

 /* ---------- Menu akun di bawah sidebar (desktop) ---------- */
 function v7Acct(){
  const who=$q('.topbar .who');if(!who)return;
  if(!isDesk()){$q('.v7acct',who)?.remove();$q('.v7menu',who)?.remove();return;}
  let acct=$q('.v7acct',who),menu=$q('.v7menu',who);
  const name=($q('#namaTop')?.textContent||'').trim(),role=($q('#roleChip')?.textContent||'').trim();
  if(!acct){
   acct=document.createElement('div');acct.className='v7acct';acct.setAttribute('role','button');acct.tabIndex=0;acct.setAttribute('aria-haspopup','menu');acct.setAttribute('aria-expanded','false');
   menu=document.createElement('div');menu.className='v7menu';menu.setAttribute('role','menu');menu.hidden=true;
   who.append(menu,acct);
   const toggle=open=>{menu.hidden=!open;acct.setAttribute('aria-expanded',String(open));};
   acct.addEventListener('click',e=>{if(e.target.closest('.v7col'))return;toggle(menu.hidden);});
   acct.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();toggle(menu.hidden);}if(e.key==='Escape')toggle(false);});
   document.addEventListener('click',e=>{if(!who.contains(e.target))toggle(false);});
   menu.addEventListener('click',e=>{if(e.target.closest('button'))toggle(false);});
  }
  const pr=$q('#navigation button[data-page="perangkat"]'),tgb=$q('#navigation button[data-page="telegram"]');
  const outs=$$('.who > button.out').filter(b=>!b.hidden&&b.style.display!=='none'&&b.textContent.trim());
  const sig=name+'|'+role+'|'+(pr?1:0)+(tgb?1:0)+'|'+outs.map(b=>b.textContent.trim()).join(',');
  if(acct.dataset.sig===sig)return;acct.dataset.sig=sig;
  acct.innerHTML=`<span class="v7av">${esc1((name||'?').slice(0,1))}</span><span class="v7nm"><b>${esc1(name||'—')}</b><small>${esc1(role)}</small></span><button type="button" class="v7col" title="Perkecil / buka sidebar" aria-label="Perkecil atau buka sidebar">«</button>`;
  $q('.v7col',acct).onclick=e=>{e.stopPropagation();const c=$q('#desktopCollapse');if(c)c.click();else $q('#app').classList.toggle('desktopCollapsed');$q('.v7col',acct).textContent=$q('#app').classList.contains('desktopCollapsed')?'»':'«';};
  /* isi menu: tombol asli dipindah supaya handler lama tetap jalan */
  const items=[];
  if(pr)items.push(mk('Perangkat Saya',ICON.dev,()=>pr.click()));if(tgb)items.push(mk('Telegram Agent',SVG('<path d="M21 4 3 11l6 2 2 6 3-4 5 4 2-15zM9 13l8-6"/>'),()=>tgb.click()));
  outs.forEach(b=>{const t=b.textContent.trim();const nb=mk(t,/keluar/i.test(t)?ICON.out:/pin/i.test(t)?ICON.key:/demo|present/i.test(t)?ICON.play:ICON.user,()=>b.click());if(/keluar/i.test(t))nb.classList.add('v7out');items.push(nb);});
  menu.replaceChildren(...items.sort((a,b)=>(a.classList.contains('v7out')?1:0)-(b.classList.contains('v7out')?1:0)));
 }
 function mk(label,ic,fn){const b=document.createElement('button');b.type='button';b.setAttribute('role','menuitem');b.innerHTML=`<span style="width:18px;height:18px;display:inline-grid;place-items:center">${ic.replace('<svg','<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"')}</span>${esc1(label)}`;b.addEventListener('click',fn);return b;}
 function esc1(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

 /* ---------- Status data + refresh di bar konteks ---------- */
 function v7Status(){
  /* desktop: di bar konteks; HP: di topbar yang sticky (status + tombol perbarui selalu kelihatan) */
  const desk=isDesk(),host=desk?$q('.subbar'):$q('.topbar');if(!host)return;
  $$('.v7status').forEach(x=>{if(x.parentElement!==host)x.remove();});
  $$('.v7refresh').forEach(x=>{if(!host.contains(x))x.remove();});
  let st=$q(':scope > .v7status',host);
  const upd=($q('#updData')?.textContent||'').trim();
  const partial=typeof REPORT!=='undefined'&&REPORT&&REPORT.partial;
  if(!st){st=document.createElement('span');st.className='v7status';
   if(desk){const crumb=$q('#crumb',host);(crumb||host.firstChild).after(st);}else{const logo=$q('.logo',host);(logo||host.firstChild).after(st);}
   const r=document.createElement('button');r.type='button';r.className='v7refresh';r.title='Perbarui tampilan';r.setAttribute('aria-label','Perbarui tampilan');r.textContent='↻';r.onclick=()=>{try{refresh();}catch(e){}};
   if(desk)host.append(r);else{const tx=document.createElement('span');tx.className='v7stx';st.append(tx,r);st.classList.add('v7mob');}}
  const txt=desk?`<i aria-hidden="true"></i>Data lengkap s.d. <b>${esc1(upd||'—')}</b>`:`<i aria-hidden="true"></i><span><small>Data lengkap s.d.</small><b>${esc1(upd||'—')}</b></span>`;
  if(st.dataset.t!==txt){const tx=$q(':scope > .v7stx',st);if(tx)tx.innerHTML=txt;else st.innerHTML=txt;st.dataset.t=txt;}
  st.classList.add('ok');
 }

 /* ---------- Tabel: bayangan petunjuk geser ---------- */
 function v7Tables(){
  $$('#main .table-scroll').forEach(ts=>{
   if(ts.dataset.v7)return;ts.dataset.v7='1';
   let w=ts.parentElement;
   if(!w.classList.contains('v7tw')){w=document.createElement('div');w.className='v7tw';ts.before(w);w.append(ts);}
   const sh=document.createElement('div');sh.className='v7shade';sh.setAttribute('aria-hidden','true');w.append(sh);
   const upd=()=>w.classList.toggle('v7more',ts.scrollWidth-ts.clientWidth-ts.scrollLeft>4);
   ts.addEventListener('scroll',upd,{passive:true});requestAnimationFrame(upd); /* v7 perf: jangan paksa layout sinkron */
   if(window.ResizeObserver)new ResizeObserver(upd).observe(ts);
  });
  $$('#main table, #mbody table').forEach(alignTable);$$('#main table, #mbody table').forEach(t=>{try{v7TblStyle(t);}catch(e){}});$$('#mbody .table-scroll').forEach(ts=>{if(!ts.dataset.v7){ts.dataset.v7='1';}});
 }

 /* REVISI #4 · header tabel per brand: tiap brand beda warna, menyatu sampai header bulan */
 const BRC={IPHONE:['#5B3FD6','#EFEBFC','#F8F6FE','#D9CFFA'],ITEL:['#0E6E64','#E2F4F1','#F2FAF8','#BFE6DE'],MOTOPAD:['#B33D0B','#FCEEE4','#FEF7F1','#F6D2BC'],MOTOROLA:['#1E5FB8','#E3EEFB','#F3F8FE','#C3D8F4'],REALME:['#2E7D32','#E5F4E6','#F4FAF4','#C6E5C8'],TECNO:['#B4204F','#FCE7EE','#FEF5F8','#F3C3D3'],VILLAON:['#455A64','#E9EEF1','#F6F8F9','#CDD8DE'],INFINIX:['#7A5B00','#FFF4D6','#FFFAEC','#F0DC9E'],SAMSUNG:['#1B3F8F','#E4EAF7','#F4F7FC','#C5D2EE'],OPPO:['#1C7A4A','#E3F4EB','#F3FAF6','#C1E5D1'],VIVO:['#2149A8','#E5EBF9','#F5F7FD','#C7D3F1'],XIAOMI:['#C2410C','#FDEDE3','#FEF7F2','#F6CCB4']};window.V8BRC=BRC;
 function brandOf(t){t=(t||'').replace(/[↕▲▼]/g,'').trim().toUpperCase();if(BRC[t])return t;const k=Object.keys(BRC).find(b=>t.startsWith(b+' ')||t.endsWith(' '+b)||t===b);return k||null;}
 function v7BrandHeads(tb){
  if(!tb.tHead||tb.dataset.v7br)return;const hr=[...tb.tHead.rows];if(hr.length<2)return;
  /* peta posisi kolom setiap sel header */
  const occ=[],pos=new Map();hr.forEach((r,ri)=>{let c=0;[...r.cells].forEach(th=>{while(occ[ri]&&occ[ri][c])c++;const cs=th.colSpan||1,rs=th.rowSpan||1;for(let i=0;i<rs;i++){occ[ri+i]=occ[ri+i]||[];for(let j=0;j<cs;j++)occ[ri+i][c+j]=1;}pos.set(th,[ri,c,cs]);c+=cs;});});
  const ranges=[];hr.forEach(r=>[...r.cells].forEach(th=>{const b=brandOf(th.textContent);const [ri,c,cs]=pos.get(th);if(b&&(cs>1||ri===0&&hr.length>1&&cs>=1&&th.rowSpan<hr.length))ranges.push({b,from:c,to:c+cs-1,ri,th});}));
  if(!ranges.length)return;tb.dataset.v7br='1';tb.classList.add('v7brand');
  const find=c=>ranges.find(x=>c>=x.from&&c<=x.to);
  hr.forEach(r=>[...r.cells].forEach(th=>{const [ri,c]=pos.get(th);const rg=find(c);if(!rg||ri<rg.ri)return;const C=BRC[rg.b];th.style.setProperty('--v7bc',C[0]);th.style.setProperty('--v7bb',ri===rg.ri?C[1]:C[2]);th.classList.add('v7bh');if(c===rg.from)th.classList.add('v7bs');}));
  /* garis pemisah brand di isi tabel */
  const starts=new Map(ranges.map(x=>[x.from,BRC[x.b][3]]));
  [...tb.tBodies].forEach(b=>[...b.rows].forEach(r=>{let c=0;[...r.cells].forEach(td=>{if(starts.has(c)){td.classList.add('v7bs');td.style.setProperty('--v7bd',starts.get(c));}c+=td.colSpan||1;});}));
 }

 /* REVISI #6 · kolom SELL THRU = biru, SELL OUT = orange (header, isi, baris total) */
 function v7MetricCols(tb){
  if(!tb.tHead||tb.dataset.v7mc)return;tb.dataset.v7mc='1';const hr=[...tb.tHead.rows];
  const occ=[],cols=new Map();hr.forEach((r,ri)=>{let c=0;[...r.cells].forEach(th=>{while(occ[ri]&&occ[ri][c])c++;const cs=th.colSpan||1,rs=th.rowSpan||1;for(let i=0;i<rs;i++){occ[ri+i]=occ[ri+i]||[];for(let j=0;j<cs;j++)occ[ri+i][c+j]=1;}
   if(!th.classList.contains('v7bh')){const t=th.textContent.replace(/[↕▲▼ⓘ]/g,'').replace(/\s+/g,' ').trim();const k=/^(total )?sell ?thru$/i.test(t)?'v7cST':/^(total )?sell ?out$/i.test(t)?'v7cSO':null;
    if(k){th.classList.add(k);for(let j=0;j<cs;j++)cols.set(c+j,k);}}c+=cs;});});
  if(!cols.size)return;
  hr.forEach(r=>{let c=0;[...r.cells].forEach(th=>{const k=cols.get(c);if(k&&(th.colSpan||1)===1)th.classList.add(k);c+=th.colSpan||1;});});
  [...tb.tBodies,...(tb.tFoot?[tb.tFoot]:[])].forEach(b=>[...b.rows].forEach(r=>{let c=0;[...r.cells].forEach(td=>{const k=(td.colSpan||1)===1&&cols.get(c);if(k)td.classList.add(k);c+=td.colSpan||1;});}));
 }

 /* REVISI TABEL · warna per indikator (SELL THRU biru, SELL OUT oranye, DA teal, NOO ungu, OMZET emas), brand pakai warna brand,
    grup lain (mis. nama type) dapat warna bergantian; header baris ke-2 tidak boleh geser sendiri (fix overlap) */
 const TIND={st:['#1E5FB8','#E3EEFB','#F5F9FE'],so:['#C25A12','#FDEBDD','#FFF8F2'],da:['#0E7C6B','#DDF3EE','#F3FBF9'],noo:['#6D3FD8','#EEE8FD','#F8F5FE'],om:['#9A6400','#FFF0C7','#FFFBEF']};
 const TCYC=[['#1E5FB8','#E3EEFB','#F7FAFE'],['#0E7C6B','#DDF3EE','#F5FBF9'],['#6D3FD8','#EEE8FD','#FAF8FE'],['#C25A12','#FDEBDD','#FFF9F4'],['#B4204F','#FCE7EE','#FEF7F9'],['#9A6400','#FFF0C7','#FFFBF0'],['#2E7D32','#E5F4E6','#F6FBF6'],['#455A64','#E9EEF1','#F7F9FA']];
 const tClean=t=>(t||'').replace(/[↕▲▼ⓘ]/g,'').replace(/\s+/g,' ').trim();
 const tInd=t=>{t=tClean(t).toUpperCase();if(/^(TOTAL )?SELL ?THRU|^SELLTHRU|^ST\b|^QTY$|^UNIT DEVICE/.test(t))return 'st';if(/^(TOTAL )?SELL ?OUT|^SO\b|^SO 30/.test(t))return 'so';if(/^DEALER AKTIF|^DA\b/.test(t))return 'da';if(/^NOO/.test(t))return 'noo';if(/^(TOTAL )?OM[ZS]ET/.test(t))return 'om';return null;};
 const tBrand=t=>{t=tClean(t).toUpperCase();return BRC[t]?t:Object.keys(BRC).find(b=>t===b||t.startsWith(b+' '))||null;};
 const tTitleInd=tb=>{const p=tb.closest('details,section,.panel');const h=p&&p.querySelector('summary,h3,h4,.ph');const t=tClean(h?h.textContent:'').toUpperCase();return /SELL ?THRU/.test(t)?'st':/SELL ?OUT/.test(t)?'so':/DEALER AKTIF/.test(t)?'da':/\bNOO\b/.test(t)?'noo':/OM[ZS]ET/.test(t)?'om':null;};
 function v7TblRows(tb){const col=tb._tpCol;if(!col)return;const starts=tb._tpStarts;
  [...tb.tBodies].flatMap(b=>[...b.rows]).concat(tb.tFoot?[...tb.tFoot.rows]:[]).forEach(r=>{if(r.dataset.tp)return;r.dataset.tp='1';
   const tot=r.classList.contains('totalRow')||/^(total|rata)/i.test(tClean(r.cells[0]?.textContent))||r.parentElement.tagName==='TFOOT';if(tot)r.classList.add('tpTot');
   let c=0;[...r.cells].forEach(td=>{const info=col[c];if(info&&(td.colSpan||1)===1){const P=info.sub||info.top;td.classList.add('tpC');td.style.setProperty('--d',P[0]);td.style.setProperty('--l',P[1]);td.style.setProperty('--x',P[2]);if(starts.has(c))td.classList.add('tpS');}c+=td.colSpan||1;});});}
 function v7TblStyle(tb){
  if(!tb.tHead||tb.closest('.v6xheat')||tb.matches('.v7kc,.rgT,.kcA,.kcR,.rkT,.trxD,.v7gtT'))return;
  if(tb.dataset.tp){v7TblRows(tb);return;}
  const cd=tb.closest('details');if(cd&&!cd.open)return;
  tb.dataset.tp='1';tb.classList.add('tpT');{const f=tb.tHead.rows[0]?.cells[0];if(f&&/^(#|no\.?|peringkat)$/i.test(tClean(f.textContent)))tb.classList.add('tpNoStick');}
  const hr=[...tb.tHead.rows],occ=[],pos=new Map(),col={},nrow=hr.length;let cyc=0;
  hr.forEach((r,ri)=>{let c=0;[...r.cells].forEach(th=>{while(occ[ri]&&occ[ri][c])c++;const cs=th.colSpan||1,rs=th.rowSpan||1;for(let i=0;i<rs;i++){occ[ri+i]=occ[ri+i]||[];for(let j=0;j<cs;j++)occ[ri+i][c+j]=1;}pos.set(th,[ri,c,cs,rs]);c+=cs;});});
  hr.forEach(r=>[...r.cells].forEach(th=>{const [ri,c,cs,rs]=pos.get(th);const k=tInd(th.textContent),b=tBrand(th.textContent);
   let P=k?TIND[k]:b?BRC[b]:null;if(!P&&ri===0&&cs>1&&nrow>1&&c>0)P=TCYC[cyc++%TCYC.length];if(!P)return;
   for(let j=0;j<cs;j++){col[c+j]=col[c+j]||{};if(!col[c+j].top){col[c+j].top=P;col[c+j].start=c;}else if(!col[c+j].sub)col[c+j].sub=P;}}));
  const tk=Object.keys(col).length?null:tTitleInd(tb);
  hr.forEach(r=>[...r.cells].forEach(th=>{const [ri,c,cs,rs]=pos.get(th);th.classList.add('tpH');
   {const w=document.createTreeWalker(th,NodeFilter.SHOW_TEXT);let n;while(n=w.nextNode())if(/KONTRIBUSI/i.test(n.nodeValue))n.nodeValue=n.nodeValue.replace(/KONTRIBUSI/i,'KONTRIB.');}
   const info=col[c];
   if(!info){if(tk){const P=TIND[tk];th.style.setProperty('--d',P[0]);th.style.setProperty('--l',P[1]);th.classList.add('tpL');}return;}
   const leaf=ri+rs===nrow,P=info.sub&&ri>0?info.sub:info.top;th.style.setProperty('--d',P[0]);th.style.setProperty('--l',P[1]);
   if(!leaf){th.classList.add(ri===0?'tpG':'tpG2');if(cs>6)th.classList.add('tpWide');if(!th.querySelector(':scope > .tpLbl')){const s=document.createElement('span');s.className='tpLbl';while(th.firstChild)s.append(th.firstChild);th.append(s);}}else th.classList.add('tpL');
   if(c===info.start)th.classList.add('tpS');}));
  hr.forEach(r=>[...r.cells].forEach(th=>{if((th.rowSpan||1)>1)th.classList.add('tpMid');}));
  [...(hr[0]?.cells||[])].forEach(th=>{const cs=getComputedStyle(th);if(cs.position==='sticky'&&cs.left!=='auto')th.classList.add('tpFrz');});
  tb._tpCol=col;tb._tpStarts=new Set(Object.values(col).map(x=>x.start));v7TblRows(tb);v7BandBind(tb);
 }
 /* label pita grup: pita brand (baris atas) selalu di tengah bagian yang kelihatan, pita indikator nempel di kiri bagian yang kelihatan */
 function v7BandPlace(tb){const sc=tb._tpSc;if(!sc||!tb.isConnected)return;const sr=sc.getBoundingClientRect();if(!sr.width)return;
  let fr=sr.left;tb.querySelectorAll('thead th.tpFrz').forEach(th=>{fr=Math.max(fr,th.getBoundingClientRect().right);});
  const visL=Math.max(sr.left,fr),visR=sr.right;
  tb.querySelectorAll('thead th.tpG>.tpLbl,thead th.tpG2>.tpLbl').forEach(l=>{const th=l.parentElement,r=th.getBoundingClientRect(),pl=parseFloat(getComputedStyle(th).paddingLeft)||0,pr=parseFloat(getComputedStyle(th).paddingRight)||0,inner=r.width-pl-pr,lw=l.offsetWidth;
   const a=Math.max(r.left+pl,visL),b=Math.min(r.right-pr,visR);let x;
   if(th.classList.contains('tpG')){if(b-a>=lw)x=(a+b)/2-(r.left+pl)-lw/2;else x=a>r.left+pl?inner-lw:0;}
   else x=a-(r.left+pl);
   x=Math.max(0,Math.min(inner-lw,x));l.style.transform=x?`translateX(${Math.round(x)}px)`:'';});}
 function v7BandBind(tb){if(tb.dataset.tpb)return;if(!tb.querySelector('thead th.tpG>.tpLbl,thead th.tpG2>.tpLbl'))return;const sc=tb.closest('.table-scroll,.v6gttable');if(!sc)return;tb.dataset.tpb='1';tb._tpSc=sc;
  let raf=0;const go=()=>{cancelAnimationFrame(raf);raf=requestAnimationFrame(()=>v7BandPlace(tb));};sc.addEventListener('scroll',go,{passive:true});window.addEventListener('resize',go);if(window.ResizeObserver)new ResizeObserver(go).observe(sc);go();}

 /* v7: chart Tren bulanan Beranda — klik bulan untuk detail, di HP mulai dari bulan terbaru */
 function v7TrendDet(box,i){const R=JSON.parse(box.dataset.rows||'[]'),r=R[i];if(!r)return;const f=v=>Math.round(v).toLocaleString('id-ID'),p=v=>v.toLocaleString('id-ID',{minimumFractionDigits:1,maximumFractionDigits:1});
  box.querySelectorAll('.v7tp').forEach(g=>{const on=+g.dataset.i===i;g.classList.toggle('on',on);});
  const tier=c=>c==null?'':c>=100?'gr':c>=80?'y':'r',gc=r.g==null?'<b>–</b>':`<span class="v7rgChip ${r.g>=0?'gr':'r'}">${r.g>=0?'▲':'▼'} ${p(Math.abs(r.g))}%</span>`;
  box.querySelector('.v7tdet').innerHTML=`<div class="t">${esc1(r.m.toUpperCase())} · ${r.cur?'MTD':'BULAN PENUH'}</div><div><small>Aktual</small><b>${f(r.a)}</b><i>unit</i></div><div><small>Estimasi akhir bulan</small><b>${r.cur?f(r.e):'–'}</b><i>${r.cur?'kalau ritme tetap':'bulan sudah selesai'}</i></div><div><small>Target · % capai</small><b>${r.t?f(r.t):'–'}</b><i>${r.c!=null?`<span class="v7rgChip ${tier(r.c)}">${Math.round(r.c)}%</span>${r.cur?' dari estimasi':''}`:'target belum diisi'}</i></div><div><small>Growth</small>${gc}<i>${r.g==null?'pembanding belum ada':r.cur?'MTD vs LMTD (tanggal sama)':'vs '+esc1(r.p)}</i></div>`;}
 document.addEventListener('click',e=>{if(e.target.closest('.v7btabs,.chip,[data-v6xbrand]'))setTimeout(()=>{try{v7Trend();}catch(x){}},80);},true);
 function v7Trend(){$$('#main .v7trend').forEach(box=>{if(box.dataset.v7)return;box.dataset.v7='1';const R=JSON.parse(box.dataset.rows||'[]');let cur=R.findIndex(r=>r.cur);if(cur<0)cur=R.length-1;v7TrendDet(box,cur);
  box.addEventListener('click',e=>{const g=e.target.closest('.v7tp');if(g)v7TrendDet(box,+g.dataset.i);});
  box.addEventListener('keydown',e=>{const g=e.target.closest('.v7tp');if(g&&(e.key==='Enter'||e.key===' ')){e.preventDefault();v7TrendDet(box,+g.dataset.i);}});
});
  $$('#main .v7tscroll').forEach(sc=>{if(sc.dataset.sc||!sc.offsetWidth)return;if(sc.scrollWidth>sc.clientWidth){sc.dataset.sc='1';sc.scrollLeft=sc.scrollWidth;}});}

 /* kolom yang isinya sama di semua baris → jadi 1 catatan di atas tabel (poin 13) */
 const BLN=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
 const isoTxt=t=>t.replace(/(\d{4})-(\d{2})-(\d{2})/g,(m,y,mo,d)=>`${+d} ${BLN[+mo-1]} ${y}`);
 function uniformCols(tb){
  if(!tb.tHead||tb.tHead.rows.length!==1||tb.dataset.v7uni)return;
  /* tabel berhalaman: isi kolom bisa beda di halaman lain → jangan disembunyikan */
  {let e=tb.parentElement;for(let i=0;i<6&&e;i++,e=e.parentElement)if(e.querySelector(':scope > .pager'))return;}
  const rows=[...tb.tBodies[0].rows].filter(r=>!r.classList.contains('totalRow')&&r.cells.length===tb.tHead.rows[0].cells.length);if(rows.length<5)return;
  const ths=[...tb.tHead.rows[0].cells];const notes=[];
  ths.forEach((th,k)=>{const h=th.textContent.replace(/[↕▲▼]/g,'').trim();if(!/^(status|kelompok berlaku)$/i.test(h))return;const vals=new Set(rows.map(r=>r.cells[k].textContent.replace(/\s+/g,' ').trim()));
   if(vals.size>1&&/status/i.test(h)){/* bagian yang sama di semua baris (mis. "Sementara", "s.d. tanggal") dipindah ke catatan, sisanya tetap di kolom */
    const parts=[...vals].map(v=>v.split(' · '));const common=parts[0].filter(p=>parts.every(q=>q.includes(p)));const sd=new Set(parts.flat().filter(p=>/^s\.d\./i.test(p)));if(sd.size===1&&!common.includes([...sd][0]))common.push([...sd][0]);if(!common.length)return;
    [...tb.tBodies[0].rows].forEach(r=>{const c=r.cells[k];if(!c||r.classList.contains('totalRow'))return;const rest=c.textContent.replace(/\s+/g,' ').trim().split(' · ').filter(p=>!common.includes(p));c.innerHTML=rest.length?`<span class="v7st">${esc1(isoTxt(rest.join(' · ')))}</span>`:'-';});
    notes.push([h,isoTxt(common.join(' · '))]);return;}
   if(vals.size!==1)return;const v=[...vals][0];
   th.classList.add('v7hidecol');[...tb.tBodies[0].rows].forEach(r=>{if(r.cells.length===ths.length)r.cells[k].classList.add('v7hidecol');});notes.push([h,isoTxt(v)]);});
  tb.dataset.v7uni='1';if(!notes.length)return;
  const host=tb.closest('.v7tw')||tb.closest('.table-scroll')||tb;const n=document.createElement('div');n.className='v7uni';n.innerHTML=notes.map(([h,v])=>`<span>${esc1(h)}:</span><b>${esc1(v)}</b>`).join(' ');host.before(n);
 }
 /* kolom teks rata kiri, kolom angka rata kanan (poin 05 / 12) */
 const isTxt=t=>{t=t.replace(/\s+/g,' ').trim();if(!t||/^[—–-]$/.test(t))return null;if(/^(rp|basis)\b/i.test(t))return false;if(/^0\d|^[A-Z]{2,}\d|^\d{3}\.\d{3}$/i.test(t))return true; /* kode dealer */if(/^[+\-−]?[\d.,]+\s*(%|unit|dealer|hari|x|×)?(\s|$)/i.test(t)&&!/[A-Za-z]{3,}/.test(t.replace(/unit|dealer|hari|dari|bln|basis/gi,'')))return false;return /[A-Za-zÀ-ÿ]{2,}/.test(t);};
 function alignTable(tb){
  if(!tb.tBodies[0]||tb.matches('.v7kc,.rgT,.kcA,.kcR,.rkT,.trxD,.v7gtT'))return;
  /* v7 perf: tabel di dalam panel tertutup diproses nanti saat panel dibuka */
  const cd=tb.closest('details');if(cd&&!cd.open)return;const rows=[...tb.tBodies[0].rows].filter(r=>!r.classList.contains('totalRow')).slice(0,15);if(!rows.length)return;
  const sig=rows.length+':'+(rows[0].textContent.length);if(tb.dataset.v7al===sig)return;tb.dataset.v7al=sig;
  const vote={};
  rows.forEach(r=>{let c=0;[...r.cells].forEach(td=>{const span=td.colSpan||1;if(span===1){const v=isTxt(td.textContent);if(v!==null){vote[c]=vote[c]||[0,0];vote[c][v?0:1]++;}}c+=span;});});
  const txt=new Set(Object.entries(vote).filter(([k,v])=>v[0]>v[1]).map(([k])=>+k));
  const doRow=r=>{let c=0;[...r.cells].forEach(td=>{{const tx=td.textContent.trim();if((!td.children.length&&tx==='0')||(td.children.length<=1&&/^([—–-]|0)\s*\/\s*0$/.test(tx))){td.textContent='-';td.classList.add('v7zero');}}const span=td.colSpan||1;td.classList.toggle('v7txt',span===1&&txt.has(c));td.classList.toggle('v7numc',span===1&&!txt.has(c)&&!!vote[c]&&c>0);c+=span;});};
  /* v7 perf: tabel besar diproses bertahap supaya browser tidak freeze */
  const all=[];[...tb.tBodies].forEach(b=>all.push(...b.rows));
  const per=Math.max(4,Math.floor(1500/Math.max(1,(all[0]?.cells.length||1))));
  if(all.length<=per){all.forEach(doRow);}else{let k=0;const step=()=>{const e2=Math.min(k+per,all.length);for(;k<e2;k++)doRow(all[k]);if(k<all.length)setTimeout(step,0);};setTimeout(step,0);}
  uniformCols(tb);
  v7BrandHeads(tb);v7TblStyle(tb);
  if(tb.tHead){const hr=[...tb.tHead.rows];const last=hr[hr.length-1];
   /* hitung posisi kolom header dengan rowspan/colspan */
   const occ=[];hr.forEach((r,ri)=>{let c=0;[...r.cells].forEach(th=>{while(occ[ri]&&occ[ri][c])c++;const cs=th.colSpan||1,rs=th.rowSpan||1;for(let i=0;i<rs;i++){occ[ri+i]=occ[ri+i]||[];for(let j=0;j<cs;j++)occ[ri+i][c+j]=1;}if(cs===1&&(rs>1||r===last)){th.classList.toggle('v7txt',txt.has(c));th.classList.toggle('v7num',!txt.has(c)&&!!vote[c]);}c+=cs;});});}
 }

 /* ---------- Catatan kaki panjang → ikon ⓘ ---------- */
 let pop=null;
 function closePop(){if(pop){pop.remove();pop=null;$$('.v7info[aria-expanded="true"]').forEach(b=>b.setAttribute('aria-expanded','false'));}}
 function v7Notes(){
  $$('#main :is(p.hint,.v6note)').forEach(n=>{
   if(n.dataset.v7n)return;n.dataset.v7n='1';
   const txt=n.textContent.trim();if(txt.length<110)return;
   if(n.closest('.freshness,.empty,.v7pop,.insightLead,.v6popHead'))return;
   const host=n.closest('details.foldPanel,details,section.panel,.panel');
   let anchor=host&&($q(':scope > summary',host)||$q(':scope > .ph',host));
   if(anchor&&anchor.tagName==='SUMMARY'&&$q('h3',anchor))anchor=$q('h3',anchor);
   else if(anchor&&anchor.classList.contains('ph')&&$q('h3',anchor))anchor=$q('h3',anchor);
   const b=document.createElement('button');b.type='button';b.className='v7info';b.textContent='i';b.title='Keterangan';b.setAttribute('aria-label','Keterangan');b.setAttribute('aria-expanded','false');
   b.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();const was=b.getAttribute('aria-expanded')==='true';closePop();if(was)return;pop=document.createElement('div');pop.className='v7pop';pop.innerHTML=n.innerHTML;document.body.append(pop);const r=b.getBoundingClientRect();const pw=pop.offsetWidth;let left=r.left+window.scrollX-12;left=Math.max(12,Math.min(left,window.scrollX+document.documentElement.clientWidth-pw-12));pop.style.left=left+'px';pop.style.top=(r.bottom+window.scrollY+8)+'px';b.setAttribute('aria-expanded','true');});
   if(anchor){
    if($q(':scope > .v7info',anchor))return; /* satu ikon per judul, catatan lain tetap tampil */
    anchor.append(b);n.classList.add('v7noteHidden');
   }else{
    const wrap=document.createElement('div');wrap.style.cssText='display:flex;align-items:center;gap:6px;margin:6px 0;font:600 11.5px Inter,sans-serif;color:#7C8899';wrap.append(b,document.createTextNode('Keterangan'));n.before(wrap);n.classList.add('v7noteHidden');
   }
  });
 }
 document.addEventListener('click',e=>{if(pop&&!e.target.closest('.v7pop,.v7info'))closePop();});
 window.addEventListener('scroll',()=>{if(pop&&!isDesk())closePop();},{passive:true});

 /* ---------- Filter: chip aktif, auto-apply, bottom sheet HP ---------- */
 const FSEL='.v6fbar,.filterToolbar,.customerFilterCard,.v6gtctrl,.historyFilterCard';
 const V7={openKey:null};
 function labelOf(el){if(el.dataset.v7lab!=null)return el.dataset.v7lab;const l=el.closest('label');let t='';if(l){/* v7 perf: ambil teks label tanpa clone (select bisa punya ribuan option) */const w=document.createTreeWalker(l,NodeFilter.SHOW_TEXT,{acceptNode:n=>n.parentElement.closest('select,option,button')?NodeFilter.FILTER_REJECT:NodeFilter.FILTER_ACCEPT});let n;while((n=w.nextNode()))t+=n.textContent;t=t.trim();}if(!t){const p=el.previousElementSibling;if(p&&/LABEL|SPAN|SMALL|B/.test(p.tagName))t=p.textContent.trim();}if(!t&&el.id){const lb=$q(`label[for="${el.id}"]`);if(lb)t=lb.textContent.trim();}t=t.replace(/\s+/g,' ').slice(0,28);el.dataset.v7lab=t;return t;}
 function activeOf(c){return $$('select',c).filter(s=>!s.disabled&&s.selectedIndex>0&&!s.classList.contains('hl')&&!s.closest('[hidden]')).map(s=>({el:s,lab:labelOf(s),val:(s.options[s.selectedIndex]||{}).text||''}));}
 function v7Filters(){
  $$('#main '+FSEL.split(',').join(',#main ')).forEach((c,idx)=>{
   if(c.parentElement&&c.parentElement.closest(FSEL))return; /* hanya kontainer terluar */
   if(!isDesk()&&c.closest('.transactionDetailPanel'))return; /* Transaksi HP: tanggal awal/akhir tampil langsung di tab RINCIAN */
   const key=(typeof PAGE!=='undefined'?PAGE:'')+':'+[...c.classList].join('.')+':'+idx;
   /* auto-apply: tombol "Terapkan" dipencet otomatis */
   const apply=$$('button',c).find(b=>/^terapkan$/i.test(b.textContent.trim()));
   if(apply&&!c.dataset.v7auto){c.dataset.v7auto='1';apply.classList.add('v7autoApply');let t;
    c.addEventListener('change',e=>{if(e.target.matches('select,input[type=date],input[type=month]')){clearTimeout(t);t=setTimeout(()=>apply.click(),60);}});
    c.addEventListener('input',e=>{if(e.target.matches('input[type=text],input[type=search],input:not([type])')){clearTimeout(t);t=setTimeout(()=>apply.click(),450);}});
    c.addEventListener('keydown',e=>{if(e.key==='Enter'&&e.target.matches('input')){clearTimeout(t);apply.click();}});}
   /* tandai select aktif */
   $$('select',c).forEach(s=>s.classList.toggle('v7on',s.selectedIndex>0&&!s.classList.contains('hl')));
   if(!c.dataset.v7chg){c.dataset.v7chg='1';c.addEventListener('change',()=>setTimeout(run,30));}
   /* chip filter aktif */
   let bar=c.previousElementSibling&&c.previousElementSibling.classList.contains('v7fbar')?c.previousElementSibling:null;
   let chips=bar?$q('.v7chips',bar):(c.nextElementSibling&&c.nextElementSibling.classList.contains('v7chips')?c.nextElementSibling:null);
   const act=activeOf(c);
   if(!isDesk()){
    /* HP: kontainer jadi bottom sheet, diganti tombol FILTER + chip */
    if(!bar){if(chips)chips.remove();bar=document.createElement('div');bar.className='v7fbar';bar.innerHTML='<button type="button" class="v7fbtn"><span aria-hidden="true">⚲</span>FILTER</button><div class="v7chips"></div>';c.before(bar);chips=$q('.v7chips',bar);
     $q('.v7fbtn',bar).onclick=()=>openSheet(c,key);}
    c.classList.add('v7sheet');
    if(!$q(':scope > .v7sheetHead',c)){const h=document.createElement('div');h.className='v7sheetHead';h.innerHTML='<b>FILTER</b><button type="button" class="v7chipReset">Reset</button>';c.prepend(h);$q('.v7chipReset',h).onclick=()=>resetAll(c);
     const bb=document.createElement('div');bb.className='v7sheetBar';const rs=document.createElement('button');rs.type='button';rs.className='v7sheetReset';rs.textContent='RESET';rs.onclick=()=>resetAll(c);const d=document.createElement('button');d.type='button';d.className='v7sheetDone';d.textContent='LIHAT HASIL';d.onclick=()=>closeSheet(c);bb.append(rs,d);c.append(bb);}
    const fb=$q('.v7fbtn',bar);fb.innerHTML='<span aria-hidden="true">⚲</span>FILTER'+(act.length?` <b>${act.length}</b>`:'');
    if(V7.openKey===key&&!c.classList.contains('v7open'))openSheet(c,key);
   }else{
    if(bar){bar.replaceWith(...($q('.v7chips',bar)?[$q('.v7chips',bar)]:[]));chips=c.nextElementSibling&&c.nextElementSibling.classList.contains('v7chips')?c.nextElementSibling:null;}
    c.classList.remove('v7sheet','v7open');$q(':scope > .v7sheetHead',c)?.remove();$q(':scope > .v7sheetBar',c)?.remove();
    if(!chips){chips=document.createElement('div');chips.className='v7chips';c.after(chips);}
   }
   const sig=act.map(a=>a.lab+'='+a.val).join('|');
   if(chips.dataset.sig===sig)return;chips.dataset.sig=sig;chips.replaceChildren();
   if(!act.length)return;
   if(isDesk()){const l=document.createElement('span');l.className='v7chipLbl';l.textContent='Filter aktif:';chips.append(l);}
   act.forEach(a=>{const b=document.createElement('button');b.type='button';b.className='v7chip';b.innerHTML=`${esc1(a.lab?a.lab+': ':'')}${esc1(a.val)} <i aria-hidden="true">×</i>`;b.title='Hapus filter ini';b.onclick=()=>{a.el.selectedIndex=0;a.el.dispatchEvent(new Event('input',{bubbles:true}));a.el.dispatchEvent(new Event('change',{bubbles:true}));};chips.append(b);});
   if(isDesk()&&act.length>1){const r=document.createElement('button');r.type='button';r.className='v7chipReset';r.textContent='Hapus semua';r.onclick=()=>resetAll(c);chips.append(r);}
  });
 }
 function resetAll(c){const rb=$$('button',c).find(b=>/^reset( filter)?$/i.test(b.textContent.trim()));if(rb){rb.click();return;}activeOf(c).forEach(a=>{a.el.selectedIndex=0;a.el.dispatchEvent(new Event('change',{bubbles:true}));});}
 function openSheet(c,key){V7.openKey=key;c.classList.add('v7open');if(!$q('.v7dim')){const d=document.createElement('div');d.className='v7dim';d.onclick=()=>closeSheet();document.body.append(d);}document.documentElement.style.overflow='hidden';}
 function closeSheet(c){V7.openKey=null;$$('.v7sheet.v7open').forEach(x=>x.classList.remove('v7open'));$$('.v7dim').forEach(d=>d.remove());document.documentElement.style.overflow='';}
 document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeSheet();closePop();}});


 /* =================== FASE 2 · BERANDA =================== */
 const BTABS=[['r','RINGKASAN'],['w','WILAYAH'],['t','TREN'],['m','TIM & KPI'],['a','AKSI']];
 function secTab(t){t=t.toUpperCase();if(/HEATMAP|BRAND PER|SUB REGION|PER ASM|PER SALES/.test(t))return 'w';if(/TREN BULANAN|RATA-RATA HARIAN|KALENDER/.test(t))return 't';if(/^ASM|ASM ·|KPI ·|KPI\b.*OMZET|OMZET PER/.test(t))return 'm';if(/RINGKASAN|PROYEKSI|KESEHATAN/.test(t))return 'r';return null;}
 function v7Beranda(){
  if(typeof PAGE==='undefined'||PAGE!=='beranda')return;
  const wrap=$q('#view .v6xwrap');if(!wrap)return;
  /* sub-tab hanya di level nasional (bukan saat drill ke region) */
  if($q(':scope > .v6xback',wrap)){$q(':scope > .v7btabs',wrap)?.remove();delete wrap.dataset.v7tab;v7Heat();v7RegionCards();return;} /* drill-down region: tampil penuh tanpa sub-tab */
  let cur='r';try{cur=localStorage.getItem('v7btab')||'r';}catch(e){}
  let tabs=$q(':scope > .v7btabs',wrap);
  if(!tabs){tabs=document.createElement('div');tabs.className='v7btabs';tabs.setAttribute('role','tablist');tabs.innerHTML=BTABS.map(([k,l])=>`<button type="button" role="tab" data-v7bt="${k}">${l}</button>`).join('');wrap.prepend(tabs);
   tabs.addEventListener('click',e=>{const b=e.target.closest('[data-v7bt]');if(!b)return;try{localStorage.setItem('v7btab',b.dataset.v7bt);}catch(x){}wrap.dataset.v7tab=b.dataset.v7bt;markTabs(tabs,b.dataset.v7bt);window.scrollTo({top:Math.max(0,wrap.getBoundingClientRect().top+window.scrollY-70)});});}
  let last='r';
  [...wrap.children].forEach(el=>{if(el===tabs||el.classList.contains('v6xaksi'))return;let t=null;const sec=el.matches('.v6xsec')?el:$q(':scope > .v6xsec',el);if(sec)t=secTab(sec.textContent);
   if(!t){if(el.matches('.v6xalert,.v6xhero'))t='r';else if(el.matches('.v6xbox'))t=last;else t=last;}
   el.dataset.v7tab=t;last=t;});
  try{if(window.dsmAksiMount)window.dsmAksiMount(wrap);}catch(e){}
  if(!wrap.dataset.v7tab)wrap.dataset.v7tab=cur;
  markTabs(tabs,wrap.dataset.v7tab);
  /* hero ganda di Brand per Big Region disembunyikan saat "Semua" brand */
  /* REVISI #1: kartu ringkasan brand tetap tampil juga saat "Semua" */
  v7Heat();v7RegionCards();
 }
 function markTabs(tabs,k){$$('[data-v7bt]',tabs).forEach(b=>{const on=b.dataset.v7bt===k;b.classList.toggle('on',on);b.setAttribute('aria-selected',String(on));});}

 /* Heatmap: sembunyikan brand kosong (kecuali full screen), gradasi 5 tingkat */
 function v7Heat(){
  $$('#view .v6xheat').forEach(g=>{
   const panel=g.closest('.v6xfsPanel');const fs=!!(document.fullscreenElement&&(document.fullscreenElement===panel||document.fullscreenElement.contains(g)))||(panel&&panel.classList.contains('v6xFocus')&&document.fullscreenElement);
   const kids=[...g.children];const hds=kids.filter(e=>e.classList.contains('hd'));const nb=hds.length;if(!nb)return;
   /* susun ulang per kolom: urutan anak = [kosong, hd*nb, (rn, hc*nb | hsep)...] */
   const cols=Array.from({length:nb},()=>[]);let i=0;
   kids.forEach(e=>{if(e.classList.contains('hc')){cols[i%nb].push(e);i++;}});
   const empty=cols.map(c=>c.length>0&&c.every(x=>x.classList.contains('hempty')||(!$q('.hu',x)&&x.classList.contains('na'))));
   const hide=!fs&&empty.some(Boolean)&&!empty.every(Boolean);
   hds.forEach((h,k)=>{const hid=hide&&empty[k];h.classList.toggle('v7hcol',hid);cols[k].forEach(x=>x.classList.toggle('v7hcol',hid));});
   const shown=hide?empty.filter(x=>!x).length:nb;
   if(!g.dataset.v7gtc)g.dataset.v7gtc=g.style.gridTemplateColumns;
   {const gtc=g.dataset.v7gtc.replace(/repeat\(\d+,/,`repeat(${shown},`);if(g.style.gridTemplateColumns!==gtc)g.style.gridTemplateColumns=gtc;}
   /* info kolom disembunyikan */
   const nHid=hide?empty.filter(Boolean).length:0;let note=g.previousElementSibling&&g.previousElementSibling.classList.contains('v7hnote')?g.previousElementSibling:null;
   if(nHid){if(!note){note=document.createElement('div');note.className='v7hnote';g.before(note);}{const tx=`${nHid} brand tanpa data disembunyikan · tampil lengkap di layar penuh`;if(note.textContent!==tx)note.textContent=tx;}}else if(note)note.remove();
   kids.forEach(e=>{if(!e.classList.contains('hc'))return;const hp=$q('.hp',e);const v=hp?parseFloat(hp.textContent.replace(/\./g,'').replace(',','.')):NaN;e.classList.remove('v7t1','v7t2','v7t3','v7t4','v7t5');if(isNaN(v))return;e.classList.add(v<50?'v7t1':v<80?'v7t2':v<100?'v7t3':v<110?'v7t4':'v7t5');});
   if(!$q(':scope ~ .v7hleg',g.parentElement)&&!g.parentElement.querySelector('.v7hleg')){const l=document.createElement('div');l.className='v7hleg';l.innerHTML='<span class="v7t1">&lt;50%</span><span class="v7t2">50–79%</span><span class="v7t3">80–99%</span><span class="v7t4">100–109%</span><span class="v7t5">≥110%</span>';g.after(l);}
  });
 }
 document.addEventListener('fullscreenchange',()=>setTimeout(()=>{try{v7Heat();}catch(e){}},80));

 /* Kartu region (Brand per Big Region) */
 const pnum=t=>{const m=String(t||'').match(/-?[\d.]+(,\d+)?/);return m?parseFloat(m[0].replace(/\./g,'').replace(',','.')):null;};
 function v7RegionCards(){
  $$('#view .v6xhierCard').forEach(c=>{
   if(c.dataset.v7rc)return;c.dataset.v7rc='1';
   const met=$$('.v6xhierMetrics > div',c);
   met.forEach(d=>{const sm=$q('small',d);if(sm&&/^aktual$/i.test(sm.textContent.trim()))sm.textContent='MTD';});
   const val=k=>{const d=met.find(d=>new RegExp('^'+k+'$','i').test(($q('small',d)?.textContent||'').trim()));return d?($q('b',d)?.textContent||'').trim():'';};
   let proj=$q('.scores .proj',c)?.textContent||'',act=$q('.scores .actual',c)?.textContent||'';if(!/%/.test(proj))proj='';if(!/%/.test(act))act='';
   const pv=pnum(proj),av=pnum(act),tone=v=>v==null?'na':v>=100?'up':v>=80?'md':'dn';
   const tgt=val('target'),mtd=val('MTD'),tod=val('today'),lm=val('lmtd'),gr=$q('.v6xhierMetrics .growth',c);
   const tN=pnum(tgt),mN=pnum(mtd);const gap=tN!=null&&mN!=null?Math.max(tN-mN,0):null;
   const nm=$q('.nm',c)?.textContent||'';
   const sc=document.createElement('div');sc.className='v7sc';sc.innerHTML=`<span><b class="${tone(av)}">${act?esc1(act.replace(/\s*aktual/i,'')):'-'}</b><small>capai</small></span><span><b class="${tone(pv)}">${proj?esc1(proj.replace(/\s*proyeksi/i,'')):'-'}</b><small>proy.</small></span>`;
   const pct=(gr?.textContent||'').match(/[▲▼•]?\s*[+-−]?[\d.,]+%/);const unit=(gr?.textContent||'').match(/[+-−][\d.]+\s*unit/);
   const up=gr&&(gr.classList.contains('v6up')||/▲/.test(gr.textContent));
   const mg=document.createElement('div');mg.className='v7mg';mg.innerHTML=`<div><span>TARGET</span><b>${esc1(tgt||'-')}</b></div><div><span>MTD</span><b>${esc1(mtd||'-')}${tod&&tod!=='0'?`<em>+${esc1(tod)}</em>`:''}</b></div><div><span>LMTD</span><b>${esc1(lm||'-')}</b></div><div class="v7g">${pct?`<i class="${up?'up':'dn'}">${esc1(pct[0].replace(/\s+/g,' '))}</i>`:'<i class="na">-</i>'}</div>`;
   const ext=document.createElement('div');ext.className='v7ext';ext.innerHTML=`<div><span>% CAPAI</span><b class="${tone(av)}">${act?esc1(act.replace(/\s*aktual/i,'')):'-'}</b></div><div><span>% PROYEKSI</span><b class="${tone(pv)}">${proj?esc1(proj.replace(/\s*proyeksi/i,'')):'-'}</b></div><div><span>GAP TARGET</span><b>${gap==null?'-':gap.toLocaleString('id-ID')}</b></div><div><span>GROWTH</span><b class="${up?'up':'dn'}">${unit?esc1(unit[0]):'-'}</b></div>`;
   const go=document.createElement('button');go.type='button';go.className='v7go';go.innerHTML=`${esc1(nm)} <span aria-hidden="true">›</span>`;go.title='Buka Sub Region';
   const head=$q('.v6xhierHead',c);if(head)head.after(sc);const prog=$q('.v6xhierProg',c);(prog||sc).after(mg);const mt=$q('.v6xhierMetrics',c);(mt||mg).after(ext);c.prepend(go);
  });
 }
 /* HP: tap kartu = buka/tutup detail, tap judul = pindah ke Sub Region */
 window.addEventListener('click',e=>{
  if(isDesk())return;const c=e.target.closest&&e.target.closest('#view .v6xhierCard');if(!c)return;
  if(e.target.closest('.v7go')){c.classList.remove('v7open');return;} /* biarkan handler asli drill-down */
  e.preventDefault();e.stopImmediatePropagation();
  const was=c.classList.contains('v7open');$$('#view .v6xhierCard.v7open').forEach(x=>x.classList.remove('v7open'));if(!was)c.classList.add('v7open');
 },true);


 /* =================== FASE 2 · HALAMAN LAIN =================== */
 /* Login: pesan batas perangkat jadi langkah bernomor (poin 19) */
 function v7Login(){
  const e=$q('#lerr');if(!e||e.dataset.v7===e.textContent)return;
  const t=e.textContent.trim();const m=t.match(/^Sudah ada (\d+) perangkat terdaftar/);
  if(m){e.innerHTML=`<b>Batas ${m[1]} perangkat tercapai</b><ol><li>Buka DSM di HP/laptop lama → menu <b>Perangkat Saya</b> → hapus satu.</li><li>Atau minta Admin menghapusnya.</li></ol><small>Perangkat yang tidak dipakai lebih dari 30 hari terhapus otomatis.</small>`;e.classList.add('v7lerr');}
  else e.classList.remove('v7lerr');
  e.dataset.v7=e.textContent;
 }
 /* Peringkat HP: kartu ringkas, yang belum ada kebijakan KPI dilipat (M4) */
 function v7RankMobile(){
  if(isDesk()||PAGE!=='peringkat')return;
  const cards=$$('#view article.mobileCard').filter(c=>$q('[data-rank-person], .rankTag, .v6gold,.v6silver,.v6bronze',c)||$q('.mobilePair',c));if(!cards.length)return;
  const host=cards[0].parentElement;if(host.dataset.v7rk===String(cards.length))return;host.dataset.v7rk=String(cards.length);
  const nop=[];
  cards.forEach(c=>{c.classList.add('v7rank');const sm=$q(':scope > small',c);if(sm&&!sm.dataset.v7){const parts=sm.textContent.split(' · ');sm.dataset.v7='1';if(/BELUM DIISI/i.test(sm.textContent))nop.push(c);sm.textContent=parts[0];}});
  if(nop.length&&!$q('.v7nopol',host)){const d=document.createElement('details');d.className='v7nopol';d.innerHTML=`<summary>${nop.length} SALES BELUM ADA KEBIJAKAN KPI</summary>`;nop.forEach(c=>d.append(c));host.append(d);}
 }
 /* Brand HP: pertumbuhan type per sales dilipat + cari (M6) */
 function v7TypeSales(){
  $$('#view .v6gtpanel .v6cmpbody').forEach(body=>{
   const cards=$$(':scope > .v6gtcard',body);if(!cards.length)return;
   if(!body.dataset.v7f){body.dataset.v7f='1';const box=document.createElement('div');box.className='v7search';box.innerHTML='<input type="search" placeholder="Cari nama sales…" aria-label="Cari nama sales">';body.before(box);
    $q('input',box).addEventListener('input',ev=>{const q=ev.target.value.trim().toLowerCase();$$(':scope > .v6gtcard',body).forEach(c=>{c.hidden=!!q&&!($q('.h',c)?.textContent||'').toLowerCase().includes(q);});v7TypeFilter(body,q);});}
   cards.forEach(c=>{if(c.dataset.v7f)return;c.dataset.v7f='1';c.classList.add('v7fold');const n=$$('.r',c).length-1;const h=$q('.h',c);if(h){const s=document.createElement('span');s.className='v7cnt';s.textContent=n+' type';h.append(s);}
    c.addEventListener('click',e=>{if(isDesk()||e.target.closest('a,button,input'))return;c.classList.toggle('v7fold');});});
   if(!isDesk()&&$q(':scope > .v7tInsight',body))v7TypePivot(body,cards);
  });
 }

 /* v7: Per Type di HP — toggle PER TYPE / PER SALES (default PER TYPE), dibangun dari kartu per sales yang sudah ada */
 function v7TypePivot(body,cards){
  const nm=t=>{t=(t||'').replace(/\s+/g,'').replace(/\./g,'').replace(',','.');const v=parseFloat(t);return isFinite(v)?v:0;};
  const sig=cards.length+':'+cards.map(c=>c.textContent.length).join(',');
  let seg=body.parentElement.querySelector(':scope > .v7tseg');
  if(!seg){seg=document.createElement('div');seg.className='v7tseg';seg.setAttribute('role','group');seg.setAttribute('aria-label','Kelompokkan');seg.innerHTML='<button type="button" data-m="T">PER TYPE</button><button type="button" data-m="S">PER SALES</button>';body.before(seg);
   seg.addEventListener('click',e=>{const b=e.target.closest('button[data-m]');if(!b)return;try{localStorage.setItem('dsmTypeMode',b.dataset.m);}catch(x){}body.dataset.v7sig='';v7TypePivot(body,$$(':scope > .v6gtcard',body));});}
  let mode='T';try{mode=localStorage.getItem('dsmTypeMode')||'T';}catch(x){}
  $$('button',seg).forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.m===mode)));
  body.classList.toggle('v7byType',mode==='T');
  let list=$q(':scope > .v7tlist',body);
  if(mode!=='T'){if(list)list.hidden=true;return;}
  if(list&&body.dataset.v7sig===sig){list.hidden=false;return;}
  body.dataset.v7sig=sig;
  const T=new Map();
  cards.forEach(c=>{const h=$q('.h',c);if(!h)return;const nameEl=h.firstElementChild,name=(nameEl?.textContent||'').trim(),asm=nameEl?.classList.contains('v6gself'),sub=($q('small',h)?.textContent||'').trim();
   $$('.r',c).forEach(r=>{if(r.classList.contains('b'))return;const cs=r.children;if(cs.length<3)return;const ty=cs[0].textContent.trim();const a=nm(cs[1].textContent),b=nm(cs[2].textContent);
    if(!T.has(ty))T.set(ty,{a:0,b:0,s:[]});const o=T.get(ty);o.a+=a;o.b+=b;o.s.push({name,asm,sub,a,b});});});
  const f=v=>v?Math.round(v).toLocaleString('id-ID'):'-',pc=v=>v.toLocaleString('id-ID',{minimumFractionDigits:1,maximumFractionDigits:1});
  const chip=(a,b)=>!b&&a?'<span class="v6gp nw">BARU</span>':b&&!a?'<span class="v6gp gone">HILANG</span>':!a&&!b?'<span class="v6gp">-</span>':(()=>{const g=(a-b)/b*100;return `<span class="v6gp ${g>=0?'up':'dn'}">${g>=0?'▲ +':'▼ '}${pc(g)}%</span>`;})();
  const gv=(a,b)=>!b?(a?1e9:0):(a-b)/b;
  const html=[...T.entries()].sort((x,y)=>y[1].a-x[1].a).map(([ty,o])=>`<div class="v6gtcard v7tcard v7fold"><div class="h"><span>${esc1(ty)}</span><small>Total semua sales</small><span class="v7cnt">${o.s.length} sales</span></div><div class="r b"><span>Total</span><span>${f(o.a)}</span><span class="v6mut">${f(o.b)}</span>${chip(o.a,o.b)}</div>${o.s.sort((x,y)=>gv(x.a,x.b)-gv(y.a,y.b)).map(x=>`<div class="r v7ts" data-n="${esc1(x.name.toLowerCase())}"><span><b class="${x.asm?'v6gself':''}">${esc1(x.name)}</b><small>${esc1(x.sub)}</small></span><span>${f(x.a)}</span><span class="v6mut">${f(x.b)}</span>${chip(x.a,x.b)}</div>`).join('')}</div>`).join('');
  if(!list){list=document.createElement('div');list.className='v7tlist';body.append(list);
   list.addEventListener('click',e=>{const c=e.target.closest('.v7tcard');if(c&&!e.target.closest('a,button,input'))c.classList.toggle('v7fold');});}
  list.hidden=false;list.innerHTML=html||'<div class="empty">Belum ada data type.</div>';
  const first=$q('.v7tcard',list);if(first)first.classList.remove('v7fold');
  const q=(body.parentElement.querySelector('.v7search input')?.value||'').trim().toLowerCase();if(q)v7TypeFilter(body,q);
 }
 function v7TypeFilter(body,q){$$('.v7tlist .v7tcard',body).forEach(c=>{let any=false;$$('.v7ts',c).forEach(r=>{const ok=!q||r.dataset.n.includes(q);r.hidden=!ok;any=any||ok;});c.hidden=!!q&&!any;});}
 /* Transaksi HP: dikelompokkan per tanggal (M8) */
 function v7TrxMobile(){
  if(isDesk())return;
  const items=$$('#view button.mobileTransaction');if(!items.length)return;
  const host=items[0].parentElement;if(host.dataset.v7trx===String(items.length)+items[0].dataset.trxPopup)return;host.dataset.v7trx=String(items.length)+items[0].dataset.trxPopup;
  $$('.v7trxDay,.v8colhd',host).forEach(x=>x.remove());
  let curDate=null,head=null,sumU=0,cnt=0;const flush=()=>{if(!head)return;const t=(window.V8BYDATE||{})[head.dataset.d];head.querySelector('em').textContent=t?`${Math.round(t.u).toLocaleString('id-ID')} unit · ${t.n} transaksi`+(t.n>cnt?` · tampil ${cnt}`:''):`${sumU.toLocaleString('id-ID')} unit · ${cnt} transaksi`;};
  window.V8OPEN=window.V8OPEN||new Set();
  items.forEach(b=>{const bt=$q('b',b);if(!bt)return;let full=bt.dataset.v7full||bt.textContent;bt.dataset.v7full=full;const i=full.indexOf(' · ');const d=b.dataset.v8dl||(i>0?full.slice(0,i):''),nm=i>0?full.slice(i+3):full;bt.textContent=nm;b.classList.add('v7trx');
   const sm=$q('small',b),stxt=sm?sm.dataset.v8full||sm.textContent:'';if(sm)sm.dataset.v8full=stxt;
   const u=parseInt((b.dataset.v8q||(stxt.match(/([\d.]+)\s*unit/)||[0,'0'])[1]).replace(/\./g,''),10)||0;
   if(b.dataset.v8c&&!b.dataset.v8done){b.dataset.v8done='1';b.innerHTML='<i class="v8k">'+esc1(b.dataset.v8c)+'</i><b>'+esc1(b.dataset.v8n)+'</b><span class="v8u'+(b.dataset.v8h==='1'?' h':'')+'">'+esc1(b.dataset.v8q)+'</span><span class="v8m">'+esc1(b.dataset.v8a)+'</span>';}
   if(d!==curDate){flush();curDate=d;sumU=0;cnt=0;head=document.createElement('div');head.className='v7trxDay v8dh';head.dataset.d=d;head.innerHTML=`<b><u class="v8ar"></u>${esc1(d)}</b><em></em>`;b.before(head);
    head.onclick=()=>{const o=!window.V8OPEN.has(d);o?window.V8OPEN.add(d):window.V8OPEN.delete(d);head.classList.toggle('v8o',o);$$('button.mobileTransaction[data-v8d="'+d+'"]',host).forEach(x=>x.hidden=!o);};
    head.classList.toggle('v8o',window.V8OPEN.has(d));}
   b.dataset.v8d=d;b.hidden=!window.V8OPEN.has(d);
   sumU+=u;cnt++;});
  flush();
  const f1=$q('.v7trxDay',host);if(f1){const ch=document.createElement('div');ch.className='v8colhd';ch.innerHTML='<span>KODE - NAMA DEALER</span><span>QTY - OMZET</span>';f1.before(ch);}
 }
 function v7Page(){const a=$q('#app');if(a&&typeof PAGE!=='undefined'&&a.dataset.v7page!==PAGE)a.dataset.v7page=PAGE;}

 /* ---------- Penjadwal: jalan ulang tiap DOM berubah ---------- */
 let tmr=null,running=false;
 const FN=()=>[v7Trend,v7Nav,v7Acct,v7Status,v7Tables,v7Notes,v7Filters,v7Beranda,v7Page,v7Login,v7RankMobile,v7TypeSales,v7TrxMobile];
 let OBS=null;
 function run(){if(running)return;running=true;try{for(const f of FN()){try{f();}catch(e){console.warn('v7ux',f.name,e);}}}finally{if(OBS)OBS.takeRecords();running=false;}}
 function sched(){clearTimeout(tmr);tmr=setTimeout(run,40);}
 function boot(){
  const app=$q('#app');if(!app)return;
  OBS=new MutationObserver(muts=>{if(running)return;for(const m of muts){const t=m.target;if(t.closest&&t.closest('.v7pop,.v7menu,.v7chips,.v7acct,.v7status,.v7hnote,.v7hleg,.v7btabs'))continue;/* abaikan perubahan teks kecil: cukup elemen baru */if(![...m.addedNodes].some(n=>n.nodeType===1))continue;sched();return;}});OBS.observe(document.body,{childList:true,subtree:true});
  ['#updData','#namaTop','#roleChip'].forEach(s=>{const el=$q(s);if(el)new MutationObserver(sched).observe(el,{childList:true,characterData:true,subtree:true});});
  window.matchMedia('(min-width:761px)').addEventListener('change',()=>{closeSheet();const n=$q('#navigation');if(n)delete n.dataset.v7tree;run();});
  run();
 }
 document.addEventListener('toggle',e=>{if(e.target.open)sched();},true);
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
</script></body></html>
