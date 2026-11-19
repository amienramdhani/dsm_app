/* DSM Telegram Agent (Paket 2)
   - window.tgAgentView(view,seq): halaman "Telegram Agent" (menu akun / drawer Lainnya di HP), semua role.
   - window.dsmTgAdminPanel(v): panel Admin "Sambungan Telegram" (Admin & Super Admin),
     ditambah BOT_KEY, username bot, dan Putuskan Semua (khusus Super Admin).
   Backend: lib/agent.php (apiTg*, apiAgentKey*). Mockup acuan: dsm-agent-telegram-v2.html. */
(function(){
'use strict';
const E=s=>(typeof esc==='function'?esc(s):String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));
const TZ={timeZone:'Asia/Jakarta'};
const tgl=ms=>ms?new Date(ms).toLocaleDateString('id-ID',{...TZ,day:'numeric',month:'short',year:'numeric'}):'—';
const tglp=ms=>ms?new Date(ms).toLocaleDateString('id-ID',{...TZ,day:'numeric',month:'short'}):'—';
const jam=ms=>{if(!ms)return '—';const d=new Date(ms),now=new Date();const t=d.toLocaleTimeString('id-ID',{...TZ,hour:'2-digit',minute:'2-digit'});const day=x=>x.toLocaleDateString('id-ID',TZ);if(day(d)===day(now))return 'Hari ini, '+t;const y=new Date(now-86400000);if(day(d)===day(y))return 'Kemarin, '+t;return tglp(ms)+' '+t;};
const ROLE=r=>String(r||'').toLowerCase().replace(/\b\w/g,c=>c.toUpperCase());
const TG_SVG='<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 4 3 11l6 2 2 6 3-4 5 4 2-15zM9 13l8-6"/></svg>';
const css=`
.tgW{display:grid;gap:14px;max-width:560px}
.tgC{background:rgba(255,255,255,.82);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border:1px solid #fff;border-radius:18px;padding:16px;box-shadow:0 6px 20px rgba(16,57,78,.08)}
.tgHd{display:flex;gap:12px;align-items:center}
.tgIc{width:44px;height:44px;flex:0 0 44px;border-radius:14px;background:linear-gradient(135deg,#37aee2,#1e88d0);display:grid;place-items:center;color:#fff}
.tgT{font-weight:800;font-size:14px;letter-spacing:.3px}.tgM{font-size:12px;color:#6b7c88;margin-top:2px;word-break:break-all}
.tgPill{display:inline-block;font-size:11px;font-weight:800;border-radius:20px;padding:3px 9px;white-space:nowrap;margin-left:auto}
.tgPill.OK{background:#e2f5ef;color:#12866A}.tgPill.WARN{background:#fff3dc;color:#9A6400}.tgPill.OFF{background:#fde6e5;color:#D2453F}.tgPill.NONE{background:#eef2f5;color:#6b7c88}
.tgCode{display:flex;gap:7px;justify-content:center;margin:14px 0 8px}
.tgCode b{width:42px;height:52px;border-radius:12px;background:#fff;border:2px solid #cfe3f3;display:grid;place-items:center;font-size:24px;font-weight:900;color:#10394E}
.tgBar{height:6px;border-radius:6px;background:#eef2f5;margin:10px 0 4px;overflow:hidden}.tgBar i{display:block;height:100%;background:linear-gradient(90deg,#FFB020,#F07A0C);transition:width 1s linear}
.tgTimer{text-align:center;font-size:12px;color:#D2453F;font-weight:700}
.tgBtn{display:block;width:100%;border:0;border-radius:14px;padding:13px;font:inherit;font-weight:800;font-size:13px;letter-spacing:.5px;color:#fff;background:linear-gradient(90deg,#FFB020,#F07A0C);box-shadow:0 6px 14px rgba(240,122,12,.3);margin-top:12px;cursor:pointer;text-transform:uppercase}
.tgBtn.s{background:#fff;color:#10394E;border:1.5px solid #cfe3f3;box-shadow:none}.tgBtn.g{background:#fff;color:#D2453F;border:1.5px solid #f3c6c4;box-shadow:none}
.tgBtn:disabled{opacity:.55;cursor:default}
.tgSteps{font-size:12px;line-height:1.8;padding-left:18px;margin:6px 0 0}
.tgKv{display:flex;justify-content:space-between;gap:10px;font-size:12px;padding:7px 0;border-bottom:1px dashed #e6edf2}.tgKv:last-of-type{border:0}.tgKv span{color:#6b7c88}
.tgInfo{font-size:11px;color:#6b7c88;display:flex;gap:6px;line-height:1.5}
.tgMsg{border-radius:12px;padding:8px 10px;font-size:12px;font-weight:700}.tgMsg.ok{background:#e2f5ef;color:#12866A}.tgMsg.bad{background:#fde6e5;color:#D2453F}
.tgK{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:12px}
.tgK div{border-radius:14px;padding:10px 12px;background:#f7fafc;border-left:4px solid #2E6E9E}.tgK b{font-size:20px;display:block}.tgK span{font-size:10.5px;color:#6b7c88;font-weight:800;letter-spacing:.3px}
.tgTb{overflow-x:auto}.tgTb table{width:100%;border-collapse:collapse;font-size:12px;min-width:720px}
.tgTb th{text-align:left;font-size:10.5px;letter-spacing:.5px;color:#6b7c88;padding:8px;border-bottom:2px solid #e6edf2;white-space:nowrap}
.tgTb td{padding:8px;border-bottom:1px solid #e6edf2;white-space:nowrap}
.tgX{font:inherit;font-size:11px;font-weight:800;color:#D2453F;border:1.5px solid #f3c6c4;border-radius:8px;padding:4px 9px;background:#fff;cursor:pointer}
.tgNote{margin-top:12px;background:#fff8ec;border:1px solid #ffe2b0;border-radius:12px;padding:9px 12px;font-size:12px;color:#7a5200}
.tgBox{margin-top:14px;border:1.5px solid #cfe3f3;border-radius:14px;padding:12px;background:#f8fbfe}
.tgSec{font-size:12.5px;font-weight:900;color:#10394E;letter-spacing:.4px;margin:0 0 8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.tgSec small{font-weight:700;color:#6b7c88;font-size:11px;letter-spacing:0}
.tgSa{font-size:9.5px;font-weight:900;color:#fff;background:#10394E;border-radius:6px;padding:2px 6px}
.tgRow{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:8px}
.tgRow .fi{flex:1 1 200px;min-width:0}
.tgS{border:0;border-radius:10px;padding:9px 13px;font:inherit;font-weight:800;font-size:11.5px;color:#fff;background:linear-gradient(90deg,#FFB020,#F07A0C);cursor:pointer}
.tgNew{margin-top:10px;background:#fff;border:1.5px dashed #F0A400;border-radius:12px;padding:10px 12px;font-size:12px}
.tgNew code{display:block;font-size:12.5px;font-weight:800;color:#10394E;margin:6px 0;word-break:break-all;user-select:all}
.tgDanger{margin-top:14px;background:#fdeeed;border:1.5px solid #f6c9c6;border-radius:14px;padding:12px;color:#D2453F}
.tgDanger p{font-size:12px;color:#8b2f2b;margin:0}
.tgD{border:0;border-radius:10px;padding:9px 13px;font:inherit;font-weight:900;font-size:12px;color:#fff;background:#D2453F;cursor:pointer}
@media(max-width:760px){.tgK{grid-template-columns:repeat(2,minmax(0,1fr))}.tgCode b{width:38px;height:48px;font-size:22px}}
`;
function addCss(){if(document.getElementById('tgAgentCss'))return;const s=document.createElement('style');s.id='tgAgentCss';s.textContent=css;document.head.appendChild(s);}
async function copy(t){try{await navigator.clipboard.writeText(t);return true;}catch(e){try{const a=document.createElement('textarea');a.value=t;a.style.position='fixed';a.style.opacity='0';document.body.append(a);a.select();const ok=document.execCommand('copy');a.remove();return ok;}catch(_){return false;}}}

/* ================= Halaman Telegram Agent (semua role) ================= */
let timer=null;
async function view(v,seq){
 addCss();clearInterval(timer);
 const token=TOKEN;
 v.innerHTML='<p class="v6load">Memuat Telegram Agent…</p>';
 let st;try{st=await rpc('apiTgState',token);}catch(e){if(seq===SEQUENCE)v.innerHTML=`<div class="tgMsg bad">${E(e.message||e)}</div>`;return;}
 if(seq!==SEQUENCE||token!==TOKEN)return;
 draw(v,st,null,null);
}
function draw(v,st,code,msg){
 clearInterval(timer);
 const akses=ROLE(st.role)+(st.cakupan?' · '+st.cakupan:'');
 const head=`<div class="tgC"><div class="tgHd"><div class="tgIc">${TG_SVG}</div><div style="min-width:0"><div class="tgT">STATUS</div><div class="tgM">${st.linked?(st.username?'@'+E(st.username):'ID '+E(st.telegramId)):'Akun Telegram belum terhubung'}</div></div><span class="tgPill ${st.linked?(st.sisaHari<=3?'WARN':'OK'):'NONE'}">${st.linked?'TERSAMBUNG':'BELUM'}</span></div></div>`;
 const m=msg?`<div class="tgMsg ${msg.t}">${E(msg.m)}</div>`:'';
 if(st.linked){
  v.innerHTML=`<div class="tgW">${m}${head}<div class="tgC">
   <div class="tgKv"><span>Tersambung sejak</span><b>${E(tgl(st.linkedMs))}</b></div>
   <div class="tgKv"><span>Berlaku sampai</span><b>${E(tgl(st.expiresMs))}</b></div>
   <div class="tgKv"><span>Sisa</span><b style="color:${st.sisaHari<=3?'#9A6400':'#12866A'}">${E(st.sisaHari)} hari</b></div>
   <div class="tgKv"><span>Terakhir dipakai</span><b>${E(jam(st.lastUsedMs))}</b></div>
   <div class="tgKv"><span>Akses data</span><b>${E(akses)}</b></div>
   <button class="tgBtn g" id="tgUnlink">Putuskan Telegram</button></div>
   <div class="tgC" style="background:rgba(255,248,236,.9)"><div class="tgT" style="font-size:12.5px">MAU PINDAH AKUN TELEGRAM?</div><div class="tgM">Putuskan dulu, lalu buat kode baru dari halaman ini.</div></div>
   <div class="tgInfo">ⓘ <span>Berlaku ${E(st.hari)} hari sejak terakhir dipakai (maksimal ${E(st.maks||180)} hari sejak dihubungkan). Terputus otomatis bila akun dinonaktifkan atau PIN diganti/direset.</span></div></div>`;
  v.querySelector('#tgUnlink').onclick=async e=>{
   if(!await dsmConfirm('Putuskan Telegram dari akun DSM ini? Bot tidak bisa membaca data Anda sampai dihubungkan ulang.'))return;
   e.target.disabled=true;
   try{const ns=await rpc('apiTgUnlink',TOKEN);draw(v,ns,null,{t:'ok',m:'Telegram sudah diputus.'});}catch(err){draw(v,st,null,{t:'bad',m:err.message||String(err)});}
  };
  return;
 }
 const digits=code?String(code.code).split('').map(d=>`<b>${E(d)}</b>`).join(''):'';
 const bot=st.bot?`https://t.me/${encodeURIComponent(st.bot)}`:'';
 v.innerHTML=`<div class="tgW">${m}${head}<div class="tgC">
  <div class="tgT" style="text-align:center">KODE HUBUNGKAN</div>
  ${code?`<div class="tgCode">${digits}</div><div class="tgBar"><i id="tgBarI" style="width:100%"></i></div><div class="tgTimer" id="tgTimer">Berlaku 05:00 · sekali pakai</div>
   <button class="tgBtn" id="tgCopy">${bot?'Salin kode &amp; buka bot':'Salin kode'}</button><button class="tgBtn s" id="tgNew">Buat kode baru</button>`
  :`<p class="tgM" style="text-align:center;margin:10px 0 0">Buat kode, lalu kirim ke bot Telegram dalam 5 menit.</p><button class="tgBtn" id="tgNew">Buat kode</button>`}
  </div>
  <div class="tgC"><div class="tgT">CARA MENGHUBUNGKAN</div><ol class="tgSteps"><li>Buat dan salin kode di atas</li><li>Buka bot Telegram${st.bot?` <b>@${E(st.bot)}</b>`:' dari tim IT'}</li><li>Kirim kode ke bot (berlaku 5 menit)</li><li>Selesai, berlaku ${E(st.hari)} hari</li></ol></div>
  <div class="tgInfo">ⓘ <span>Jangan pernah kirim PIN DSM ke Telegram. Bot hanya butuh kode ini. Data yang tampil di bot mengikuti hak akses Anda (${E(akses)}). Nomor telepon dealer disamarkan.</span></div></div>`;
 v.querySelector('#tgNew').onclick=async e=>{
  e.target.disabled=true;
  try{const c=await rpc('apiTgCode',TOKEN);draw(v,st,c,null);}catch(err){draw(v,st,null,{t:'bad',m:err.message||String(err)});}
 };
 if(!code)return;
 const cp=v.querySelector('#tgCopy');
 cp.onclick=async()=>{const ok=await copy(String(code.code));cp.textContent=ok?'Kode tersalin ✓':'Salin manual: '+code.code;if(bot)window.open(bot,'_blank','noopener');};
 const tick=()=>{
  const left=Math.max(0,code.untilMs-Date.now());
  const t=document.getElementById('tgTimer'),b=document.getElementById('tgBarI');
  if(!t||!b){clearInterval(timer);return;}
  if(left<=0){clearInterval(timer);draw(v,st,null,{t:'bad',m:'Kode kedaluwarsa. Buat kode baru.'});return;}
  const s=Math.ceil(left/1000);t.textContent=`Berlaku ${String(Math.floor(s/60)).padStart(2,'0')}:${String(s%60).padStart(2,'0')} · sekali pakai`;
  b.style.width=(left/code.ttlMs*100).toFixed(1)+'%';
  /* cek apakah sudah dipakai bot (tiap 5 detik) */
  if(s%5===0)rpc('apiTgState',TOKEN).then(ns=>{if(ns.linked&&document.getElementById('tgTimer')){clearInterval(timer);draw(v,ns,null,{t:'ok',m:'Berhasil terhubung ke Telegram.'});}}).catch(()=>{});
 };
 tick();timer=setInterval(tick,1000);
}
window.tgAgentView=view;

/* ================= Panel Admin: Sambungan Telegram (mockup v3) ================= */
const cssA=`
#tgAdminPanel>.ph{display:none!important}
.tgA{container-type:inline-size;display:grid;gap:14px}
.tgHero{display:flex;align-items:center;gap:14px;background:linear-gradient(120deg,#1e88d0 0%,#37aee2 55%,#6fd0f0 100%);border-radius:20px;padding:16px 18px;color:#fff;box-shadow:0 12px 28px rgba(30,136,208,.25);position:relative;overflow:hidden}
.tgHero:after{content:"";position:absolute;right:-40px;top:-60px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.12);pointer-events:none}
.tgHero .hic{width:50px;height:50px;border-radius:16px;background:rgba(255,255,255,.22);display:grid;place-items:center;flex:0 0 50px}
.tgHero h2{font-size:16px;letter-spacing:.6px;margin:0;color:#fff}.tgHero p{font-size:12px;opacity:.92;margin:3px 0 0}
.tgHero .ro{margin-left:auto;font-size:10.5px;font-weight:900;letter-spacing:.5px;background:rgba(255,255,255,.95);color:#1e88d0;border-radius:20px;padding:6px 11px;white-space:nowrap;position:relative;z-index:1}
.tgKp{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.tgKp>div{background:rgba(255,255,255,.88);border:1px solid #fff;border-radius:16px;padding:12px 14px;display:flex;gap:11px;align-items:center;box-shadow:0 6px 18px rgba(16,57,78,.07);min-width:0}
.tgKp i{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;flex:0 0 38px;color:#fff;font-style:normal;font-weight:900;font-size:15px}
.tgKp b{font-size:22px;line-height:1;display:block}.tgKp span{font-size:10.5px;font-weight:800;letter-spacing:.4px;color:#6b7c88}
.tgGr{display:grid;gap:14px;align-items:start;grid-template-columns:minmax(0,1fr)}
@container (min-width:900px){.tgGr{grid-template-columns:minmax(0,1fr) 330px}}
@container (max-width:620px){.tgKp{grid-template-columns:repeat(2,minmax(0,1fr))}.tgHero .ro{display:none}.tgHero p .lg,.tgKp .lg{display:none}}
.tgCd{background:rgba(255,255,255,.9);border:1px solid #fff;border-radius:18px;padding:16px;box-shadow:0 6px 20px rgba(16,57,78,.08);min-width:0}
.tgCt{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:13px;font-weight:900;letter-spacing:.4px;color:#10394E;margin-bottom:12px}
.tgCt small{font-weight:700;color:#6b7c88;font-size:11px;letter-spacing:0}
.tgSide{display:grid;gap:14px;min-width:0}
.tgTool{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
.tgSr{flex:1 1 220px;min-width:0;border:1px solid #dfe8f0;background:#f4f8fb;border-radius:12px;padding:9px 12px;font:inherit;font-size:12.5px}
.tgCh{display:flex;gap:6px;overflow-x:auto;max-width:100%}
.tgCh button{font:inherit;font-size:11px;font-weight:800;padding:7px 11px;border-radius:20px;border:0;background:#f1f5f8;color:#6b7c88;white-space:nowrap;cursor:pointer}
.tgCh button.on{background:#10394E;color:#fff}.tgCh button em{font-style:normal;opacity:.75;margin-left:3px}
.tgLs{overflow-x:auto;margin:0 -4px}
.tgLs table{width:100%;border-collapse:collapse;font-size:12px;min-width:640px}
.tgLs th{text-align:left;font-size:10px;font-weight:900;letter-spacing:.5px;color:#6b7c88;padding:4px 10px 8px;border-bottom:1px solid #e8eef3;white-space:nowrap;background:transparent}
.tgLs td{padding:10px;border-bottom:1px solid #e8eef3;vertical-align:middle;white-space:nowrap}
.tgLs tr:last-child td{border-bottom:0}.tgLs tbody tr:hover td{background:#f6fafd}
.tgLs td:first-child,.tgLs th:first-child{position:sticky;left:0;background:#fff;z-index:1}
.tgWho{display:flex;gap:10px;align-items:center}
.tgAv{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;font-weight:900;font-size:13px;flex:0 0 36px;color:#fff}
.tgWho b{font-size:12.5px;display:block}.tgWho small{font-size:10.5px;color:#6b7c88}
.tgRl{font-size:10.5px;font-weight:800;border-radius:8px;padding:3px 8px;background:#eef3f8;color:#10394E;display:inline-block}
.tgU{font-size:11.5px;color:#1e88d0;font-weight:700;margin-top:4px}
.tgLf{min-width:150px}.tgLf small{display:flex;justify-content:space-between;gap:8px;font-size:10.5px;color:#6b7c88;margin-bottom:4px}.tgLf small b{color:#1c2b36}
.tgTr{height:7px;border-radius:7px;background:#edf2f6;overflow:hidden}.tgTr i{display:block;height:100%;border-radius:7px}
.tgUs{font-size:11px;color:#6b7c88}
.tgFt{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:10px;font-size:11px;color:#6b7c88}
.tgMore{font:inherit;font-size:11px;font-weight:800;padding:7px 11px;border-radius:20px;border:0;background:#f1f5f8;color:#10394E;cursor:pointer}
.tgNt{margin-top:12px;display:flex;gap:8px;font-size:11.5px;color:#5d6d79;background:#f4f8fb;border-radius:12px;padding:9px 11px;line-height:1.5}
.tgKy{display:flex;gap:10px;align-items:center;padding:10px;border-radius:13px;background:#f6fafd;border:1px solid #e3edf5;margin-bottom:8px}
.tgKy.dim{opacity:.6}
.tgKy .kk{width:34px;height:34px;border-radius:11px;background:linear-gradient(135deg,#FFB020,#F07A0C);display:grid;place-items:center;color:#fff;flex:0 0 34px}
.tgKy.dim .kk{background:#c7d2db}
.tgKy .nm{min-width:0;flex:1}.tgKy .nm b{font-size:12px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.tgKy .nm small{font-size:10.5px;color:#6b7c88}
.tgIn{display:flex;gap:8px;margin-top:6px}
.tgIn input{flex:1;min-width:0;border:1.5px solid #dbe6ef;background:#fff;border-radius:12px;padding:10px 12px;font:inherit;font-size:12.5px}
.tgB{border:0;border-radius:12px;padding:10px 13px;font:inherit;font-weight:900;font-size:11.5px;letter-spacing:.3px;color:#fff;background:linear-gradient(90deg,#FFB020,#F07A0C);box-shadow:0 6px 14px rgba(240,122,12,.25);white-space:nowrap;cursor:pointer}
.tgB.p{background:linear-gradient(90deg,#10394E,#2E6E9E);box-shadow:0 6px 14px rgba(16,57,78,.2)}
.tgB:disabled{opacity:.55;cursor:default}
.tgRv{margin-top:10px;border-radius:14px;padding:12px;background:linear-gradient(135deg,#fff9ee,#fff);border:1.5px dashed #F0A400}
.tgRv .t{font-size:11.5px;font-weight:800;color:#8a5a00}
.tgRv .cd{display:flex;gap:8px;align-items:center;margin-top:8px;background:#10394E;border-radius:11px;padding:9px 10px}
.tgRv code{flex:1;min-width:0;color:#fff;font-size:11.5px;font-weight:700;word-break:break-all;user-select:all}
.tgRv .tgB{padding:7px 10px;font-size:10.5px;box-shadow:none}
.tgRv p{font-size:10.5px;color:#8a6a2a;margin:7px 0 0;line-height:1.5}
.tgBot{font-size:12px;margin-top:8px;color:#6b7c88}.tgBot b{color:#1e88d0}
.tgDg{border-radius:18px;padding:14px;background:linear-gradient(135deg,#fff3f2,#fde6e5);border:1.5px solid #f6c9c6}
.tgDg .tgCt{color:#D2453F;margin-bottom:6px}.tgDg p{font-size:11.5px;color:#8b2f2b;line-height:1.5;margin:0}
.tgDb{width:100%;margin-top:10px;border:0;border-radius:12px;padding:11px;font:inherit;font-weight:900;font-size:12px;letter-spacing:.5px;color:#fff;background:#D2453F;box-shadow:0 6px 14px rgba(210,69,63,.25);cursor:pointer}
`;
const AV={'SUPER ADMIN':'#10394E,#2E6E9E','ADMIN':'#10394E,#2E6E9E','SALES':'#12866A,#2bb38f','TELEMARKETING':'#1593B5,#37aee2','SUPERVISOR':'#6D3FD8,#9b7af0','ASM':'#F0A400,#F07A0C','RGM':'#C25A12,#e08a3c'};
const avBg=(role,off)=>off?'#c7d2db':'linear-gradient(135deg,'+(AV[role]||'#6D3FD8,#9b7af0')+')';
const TG_W='<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 4 3 11l6 2 2 6 3-4 5 4 2-15zM9 13l8-6"/></svg>';
function addCssA(){if(document.getElementById('tgAdminCss'))return;const s=document.createElement('style');s.id='tgAdminCss';s.textContent=cssA;document.head.appendChild(s);}
function adminPanel(v){
 const role=(typeof BOOT!=='undefined'&&BOOT)?BOOT.user?.role:'';if(!['ADMIN','SUPER ADMIN'].includes(role))return;
 addCss();addCssA();
 v.insertAdjacentHTML('beforeend',`<section class="panel" id="tgAdminPanel"><div class="ph"><h3>Sambungan Telegram</h3><span class="hint">Akun yang terhubung ke bot Telegram (baca-saja)</span></div><div class="pb" id="tgAdmin"><p class="v6load">Memuat…</p></div></section>`);
 const root=v.querySelector('#tgAdmin');let L=null,K=null,newKey=null,msg=null,q='',flt='ALL',lim=20;
 const isSa=role==='SUPER ADMIN';
 const load=async()=>{try{const [l,k]=await Promise.all([rpc('apiTgAdminList',TOKEN),isSa?rpc('apiAgentKeyList',TOKEN):Promise.resolve(null)]);L=l;K=k;render();}catch(e){root.innerHTML=`<div class="tgMsg bad">${E(e.message||e)}</div>`;}};
 const act=async(fn,...args)=>{try{return await rpc(fn,TOKEN,...args);}catch(e){msg={t:'bad',m:e.message||String(e)};render();return null;}};
 const grp=r=>r.status.kode==='OFF'?'OFF':r.status.kode==='WARN'?'WARN':'OK';
 function life(r){
  if(r.status.kode==='OFF')return `<span class="tgPill OFF" style="margin:0;${/HABIS/.test(r.status.label)?'background:#eef2f5;color:#6b7c88':''}">${E(r.status.label==='HABIS'?'HABIS '+tglp(r.expiresMs).toUpperCase():r.status.label)}</span>`;
  const left=Math.max(0,Math.ceil((r.expiresMs-Date.now())/86400000)),pct=Math.max(3,Math.min(100,left/60*100)),warn=r.status.kode==='WARN';
  return `<div class="tgLf"><small><span>${E(tglp(r.linkedMs))} → <b>${E(tglp(r.expiresMs))}</b></span><span style="${warn?'color:#9A6400;font-weight:800':''}">${left} hr</span></small><div class="tgTr"><i style="width:${pct.toFixed(0)}%;background:${warn?'#F0A400':'linear-gradient(90deg,#12866A,#2bb38f)'}"></i></div></div>`;
 }
 function listHtml(){
  const ql=q.toLowerCase();
  const all=L.rows.filter(r=>!ql||[r.nama,r.nik,r.username,r.telegramId].some(x=>String(x||'').toLowerCase().includes(ql)));
  const n={ALL:all.length,OK:all.filter(r=>grp(r)!=='OFF').length,WARN:all.filter(r=>grp(r)==='WARN').length,OFF:all.filter(r=>grp(r)==='OFF').length};
  const rows=all.filter(r=>flt==='ALL'||(flt==='OK'?grp(r)!=='OFF':grp(r)===flt));
  const shown=rows.slice(0,lim);
  const tr=shown.map(r=>{const off=grp(r)==='OFF';return `<tr><td><div class="tgWho"><div class="tgAv" style="background:${avBg(r.role,off)}">${E((r.nama||'?').trim().slice(0,1).toUpperCase())}</div><div><b>${E(String(r.nama).toUpperCase())}</b><small>${E(r.nik)}</small></div></div></td>
   <td><span class="tgRl">${E(ROLE(r.role)||'-')}</span><div class="tgU">${r.username?'@'+E(r.username):'ID '+E(r.telegramId)}</div></td>
   <td>${life(r)}</td><td class="tgUs">${E(jam(r.lastUsedMs))}</td>
   <td>${off?'':`<button class="tgX" data-tg-cut="${E(r.telegramId)}" data-tg-nm="${E(r.nama)}">PUTUSKAN</button>`}</td></tr>`;}).join('');
  return `<div class="tgTool"><input class="tgSr" id="tgSr" type="search" placeholder="Cari nama, NIK, atau @telegram…" value="${E(q)}">
   <div class="tgCh">${[['ALL','SEMUA'],['OK','AKTIF'],['WARN','SEGERA HABIS'],['OFF','TERPUTUS']].map(([k,l])=>`<button data-flt="${k}" class="${flt===k?'on':''}">${l}<em>${n[k]}</em></button>`).join('')}</div></div>
   <div class="tgLs"><table><thead><tr><th>USER DSM</th><th>ROLE · TELEGRAM</th><th>MASA BERLAKU</th><th>TERAKHIR DIPAKAI</th><th></th></tr></thead><tbody>${tr||`<tr><td colspan="5" style="color:#6b7c88;position:static">${L.rows.length?'Tidak ada yang cocok.':'Belum ada akun yang terhubung.'}</td></tr>`}</tbody></table></div>
   <div class="tgFt"><span>Menampilkan ${shown.length} dari ${rows.length}</span>${rows.length>shown.length?'<button class="tgMore" id="tgMore">MUAT LAGI</button>':''}</div>`;
 }
 function render(){
  const c=L.count;
  let side='';
  if(isSa&&K){
   const keys=K.keys.map(k=>`<div class="tgKy${k.active?'':' dim'}"><div class="kk">🔑</div><div class="nm"><b>${E(k.label)}</b><small>${k.active?`Dibuat ${E(tglp(Date.parse(k.createdAt)))} · ${E(k.createdBy)} · ${k.lastUsedMs?"dipakai "+E(jam(k.lastUsedMs)):"belum dipakai"}`:`Dicabut ${E(tglp(Date.parse(k.revokedAt)))}`}</small></div>${k.active?`<button class="tgX" data-key-cut="${E(k.id)}" data-key-nm="${E(k.label)}">CABUT</button>`:''}</div>`).join('');
   side=`<div class="tgSide">
    <div class="tgCd"><div class="tgCt">BOT_KEY <span class="tgSa">SUPER ADMIN</span></div>${keys||'<p class="tgM" style="margin:0 0 8px">Belum ada BOT_KEY.</p>'}
     <div class="tgIn"><input id="tgKeyLbl" placeholder="Label key baru, contoh: Bot Telegram IT" maxlength="100"><button class="tgB" id="tgKeyNew">+ BUAT</button></div>
     ${newKey?`<div class="tgRv"><div class="t">✅ Key "${E(newKey.label)}" dibuat · salin sekarang</div><div class="cd"><code>${E(newKey.key)}</code><button class="tgB" id="tgKeyCopy">SALIN</button></div><p>Key hanya tampil sekali. Kirim ke IT lewat chat pribadi, jangan di grup dan jangan di-screenshot.</p></div>`:''}</div>
    <div class="tgCd"><div class="tgCt">USERNAME BOT <small>· tombol "Buka bot"</small></div>
     <div class="tgIn" style="margin-top:0"><input id="tgBotName" placeholder="@DSM_Agent_bot" value="${K.bot?'@'+E(K.bot):''}" maxlength="64"><button class="tgB p" id="tgBotSave">SIMPAN</button></div>
     <div class="tgBot">${K.bot?`Terpasang: <b>t.me/${E(K.bot)}</b>`:'Belum diisi. Minta username bot ke tim IT.'}</div></div>
    <div class="tgDg"><div class="tgCt">⚠ PUTUSKAN SEMUA <span class="tgSa">SUPER ADMIN</span></div>
     <p>Memutus ${E(c.aktif)} sambungan aktif sekaligus. Pakai kalau ada indikasi kebocoran. Semua user harus hubungkan ulang.</p>
     <div class="tgIn"><input id="tgAllWhy" placeholder="Alasan (wajib)…" maxlength="150"></div><button class="tgDb" id="tgAll">PUTUSKAN SEMUA</button></div></div>`;
  }
  root.innerHTML=`<div class="tgA">${msg?`<div class="tgMsg ${msg.t}">${E(msg.m)}</div>`:''}
   <div class="tgHero"><div class="hic">${TG_W}</div><div><h2>SAMBUNGAN TELEGRAM</h2><p>Akun DSM yang terhubung ke bot Telegram<span class="lg"> · data mengikuti hak akses masing-masing user</span></p></div><span class="ro">🔒 BACA-SAJA</span></div>
   <div class="tgKp"><div><i style="background:linear-gradient(135deg,#12866A,#2bb38f)">✓</i><div><b>${E(c.aktif)}</b><span>TERSAMBUNG</span></div></div><div><i style="background:linear-gradient(135deg,#F0A400,#F07A0C)">⏳</i><div><b>${E(c.segera)}</b><span><span class="lg">HABIS </span>≤ 3 HARI</span></div></div><div><i style="background:linear-gradient(135deg,#D2453F,#ec6b5f)">✕</i><div><b>${E(c.off)}</b><span>TERPUTUS<span class="lg"> / HABIS</span></span></div></div><div><i style="background:linear-gradient(135deg,#1e88d0,#37aee2)">💬</i><div><b>${E(c.hariIni)}</b><span><span class="lg">PERTANYAAN </span>HARI INI</span></div></div></div>
   <div class="tgGr"><div class="tgCd"><div class="tgCt">DAFTAR SAMBUNGAN <small>· urut terakhir dipakai</small></div><div id="tgList">${listHtml()}</div>
    <div class="tgNt">ⓘ <span>Bot hanya bisa <b>membaca</b>. Tidak dibuka: user/PIN, panel Super Admin, approval, upload ST/SO, edit dealer, koreksi, Klaim, config. Nomor telepon dealer disamarkan.</span></div></div>${side}</div></div>`;
  bindList();
  if(!isSa||!K)return;
  root.querySelectorAll('[data-key-cut]').forEach(b=>b.onclick=async()=>{if(!await dsmConfirm('Cabut BOT_KEY "'+b.dataset.keyNm+'"? Bot yang memakai key ini langsung berhenti bisa membaca data.'))return;b.disabled=true;const r=await act('apiAgentKeyRevoke',+b.dataset.keyCut);if(r){K=r;newKey=null;msg={t:'ok',m:'BOT_KEY dicabut.'};render();}});
  root.querySelector('#tgKeyNew').onclick=async e=>{const lbl=root.querySelector('#tgKeyLbl').value.trim();if(!lbl){msg={t:'bad',m:'Isi label BOT_KEY dulu.'};render();return;}e.target.disabled=true;const r=await act('apiAgentKeyCreate',lbl);if(r){K=r.list;newKey=r.baru;msg=null;render();}};
  const kc=root.querySelector('#tgKeyCopy');if(kc)kc.onclick=async()=>{kc.textContent=(await copy(newKey.key))?'TERSALIN ✓':'SALIN MANUAL';};
  root.querySelector('#tgBotSave').onclick=async e=>{e.target.disabled=true;const r=await act('apiTgBotSave',root.querySelector('#tgBotName').value);if(r){K.bot=r.bot;msg={t:'ok',m:r.bot?'Username bot disimpan: @'+r.bot:'Username bot dikosongkan.'};render();}};
  root.querySelector('#tgAll').onclick=async e=>{const why=root.querySelector('#tgAllWhy').value.trim();if(why.length<5){msg={t:'bad',m:'Alasan wajib diisi (minimal 5 huruf).'};render();return;}if(!await dsmConfirm('PUTUSKAN SEMUA sambungan Telegram? Semua user harus hubungkan ulang.'))return;e.target.disabled=true;const r=await act('apiTgAdminUnlinkAll',why);if(r){L=r;msg={t:'ok',m:r.putus+' sambungan diputus.'};render();}};
 }
 function bindList(){
  const box=root.querySelector('#tgList');if(!box)return;
  const sr=box.querySelector('#tgSr');
  sr.oninput=()=>{q=sr.value;lim=20;const pos=sr.selectionStart;box.innerHTML=listHtml();bindList();const n=box.querySelector('#tgSr');n.focus();try{n.setSelectionRange(pos,pos);}catch(_){}};
  box.querySelectorAll('[data-flt]').forEach(b=>b.onclick=()=>{flt=b.dataset.flt;lim=20;box.innerHTML=listHtml();bindList();});
  const m=box.querySelector('#tgMore');if(m)m.onclick=()=>{lim+=20;box.innerHTML=listHtml();bindList();};
  box.querySelectorAll('[data-tg-cut]').forEach(b=>b.onclick=async()=>{if(!await dsmConfirm('Putuskan Telegram milik '+b.dataset.tgNm+'?'))return;b.disabled=true;const r=await act('apiTgAdminUnlink',b.dataset.tgCut);if(r){L=r;msg={t:'ok',m:'Sambungan '+b.dataset.tgNm+' diputus.'};render();}});
 }
 load();
}
window.dsmTgAdminPanel=adminPanel;
})();
