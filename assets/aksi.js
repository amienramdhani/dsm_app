/* Tab AKSI di Beranda — A1: KPI + pencapaian SELL THRU per brand (bisa dibuka).
   Hanya membaca V6X.data (hasil apiExec yang sudah dimuat Beranda). Tidak ada perhitungan baru di server. */
(function () {
  'use strict';
  const E = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const N = n => (typeof num === 'function' ? num(Math.round(n)) : String(Math.round(n)));
  const P = n => Math.round(n) + '%';
  const IND = [['st', 'SELL THRU', 'unit'], ['da', 'DEALER AKTIF', 'dealer'], ['noo', 'NOO (DEALER BARU)', '']];

  function css() {
    if (document.getElementById('aksiCss')) return;
    const s = document.createElement('style'); s.id = 'aksiCss';
    s.textContent = `
.v6xaksi{display:grid;gap:12px}
.v6xaksi[hidden]{display:none}
html body #app #view .aksiOldHide{display:none!important}
.aksiHd{border:1px solid #dbe6fb;border-radius:18px;padding:12px 14px;background:linear-gradient(135deg,#f1f6ff,#fff)}
.aksiHd h2{margin:0;font-size:16px}.aksiHd small{color:#6b7a99;font-weight:700;font-size:11.5px}
.aksiBox{border:1px solid #e3eaf7;border-radius:18px;padding:12px 14px;background:#fff;box-shadow:0 6px 16px rgba(21,86,184,.06)}
.aksiBox>h3{margin:0 0 9px;font-size:12px;letter-spacing:.6px;text-transform:uppercase;color:#44557a;display:flex;justify-content:space-between;gap:8px;align-items:center}
.aksiBox>h3 em{font-style:normal;font-size:10px;color:#0b5cad;background:#e6f0ff;border-radius:999px;padding:3px 8px;text-transform:none;letter-spacing:0}
.aksiGrp{font-size:11px;font-weight:900;color:#0b5cad;margin:2px 0 6px}
.aksiK3{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:8px;margin-bottom:8px}
.aksiK{border:1px solid #dbe6fb;border-left:4px solid #2f80ed;border-radius:14px;padding:9px 12px;background:linear-gradient(135deg,#fff,#eef5ff)}
.aksiK small{display:block;font-size:10px;font-weight:900;color:#6b7a99;letter-spacing:.5px}
.aksiK b{display:block;font-size:21px;margin-top:2px}
.aksiK span{display:block;font-size:11px;font-weight:700;color:#6b7a99;margin-top:2px}
.aksiK dl{margin:6px 0 0;display:grid;gap:3px}
.aksiK dl>div{display:flex;justify-content:space-between;gap:8px;font-size:11px;border-top:1px dashed #e3eaf7;padding-top:3px}
.aksiK dt{color:#6b7a99;font-weight:700}.aksiK dd{margin:0;font-weight:800;text-align:right}
.aksiK dd span{display:inline;margin:0;font-size:inherit}
.aksiK dd small{color:#6b7a99;font-weight:700}.aksiK dd .aksiUp{color:#0b7a45;font-weight:900}.aksiK dd .aksiRed{color:#c62828;font-weight:900}
.aksiK dd i.aksiRed{color:#c62828;font-style:normal;font-weight:900}
.aksiOm dl>div{flex-wrap:nowrap;align-items:baseline}.aksiOm dd{white-space:nowrap}.aksiOm dt{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}
.aksiBUh{font-size:10px;font-weight:900;color:#6b7a99;letter-spacing:.5px;margin:8px 0 4px;border-top:1px solid #e3eaf7;padding-top:6px}
@media(min-width:1000px){.aksiK3:has(.aksiOm){grid-template-columns:repeat(5,minmax(0,1fr))}}
@media(max-width:999px){.aksiOm{grid-column:1/-1}}
.aksiK.r{border-left-color:#e5484d}.aksiK.a{border-left-color:#f59e0b}.aksiK.g{border-left-color:#12a05c}
.aksiBg{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;align-items:start}@media(min-width:1000px){.aksiBg{grid-template-columns:repeat(4,minmax(0,1fr))}}.aksiBg>*{min-width:0}
details.aksiBr{border:1px solid #e3eaf7;border-radius:14px;background:#fbfdff}
details.aksiBr summary{list-style:none;cursor:pointer;padding:9px 12px;display:grid;gap:6px}
details.aksiBr summary::-webkit-details-marker{display:none}
.aksiBr .top{display:flex;justify-content:space-between;gap:8px;font-size:12.5px;font-weight:800}
.aksiBr .top i{font-style:normal;color:#6b7a99;margin-right:4px}
.aksiBr .top span{font-size:11.5px;white-space:nowrap}
details.aksiBr[open]{grid-column:1/-1;border-color:#2f80ed;border-width:1.5px;background:linear-gradient(180deg,#eaf3ff 0%,#fff 38%);box-shadow:0 10px 24px rgba(47,128,237,.22),0 0 0 3px rgba(47,128,237,.12)}
details.aksiBr[open] summary{background:linear-gradient(135deg,#dcebff,#eef5ff);border-radius:13px 13px 0 0}
details.aksiBr[open] .top>span:first-child{color:#0b5cad}
.aksiBr .top i{display:inline-block;transition:transform .15s}
details.aksiBr[open] .top i{transform:rotate(90deg);color:#2f80ed}
@media(max-width:700px){
details.aksiBr summary{padding:7px 7px;gap:4px}
.aksiBr .top{flex-direction:column;gap:1px;font-size:11px}
.aksiBr .top span:last-child{font-size:9.5px;white-space:normal;line-height:1.25}
.aksiTd{font-size:9px!important;line-height:1.25}
.aksiTdD{display:none}
.aksiBar{height:8px!important}
}
.aksiTd{font-size:11px;font-weight:700;color:#6b7a99}.aksiTd b{color:#16202E}
.aksiBar{height:12px;border-radius:7px;background:#e8eef9;position:relative}
.aksiBar i{display:block;height:100%;border-radius:7px}
.aksiBar u{position:absolute;top:-3px;bottom:-3px;width:2px;background:#0f1f3d66}
.aksiSub{padding:2px 10px 10px;overflow-x:auto}
html body #app #view .aksiSub table{width:100%;border-collapse:collapse;table-layout:auto;font-size:10.5px}
html body #app #view .aksiSub th,html body #app #view .aksiSub td{padding:3px 4px;border-bottom:1px solid #eef2fa;font-size:10.5px!important;font-weight:600;letter-spacing:0!important;text-transform:none!important;line-height:1.25;white-space:nowrap;background:transparent!important;color:inherit}
html body #app #view .aksiSub th:not(:first-child),html body #app #view .aksiSub td:not(:first-child){text-align:right!important}
html body #app #view .aksiSub th:first-child,html body #app #view .aksiSub td:first-child{text-align:left!important;white-space:normal;max-width:120px;word-break:break-word}
html body #app #view .aksiSub th{color:#6b7a99;font-size:9.5px!important;font-weight:800}
.aksiDuo{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:8px}@media(max-width:700px){.aksiDuo{grid-template-columns:1fr}}
.aksiAct{display:grid;gap:8px}
.aksiA{border:1px solid #e3eaf7;border-left:4px solid #12a05c;border-radius:12px;padding:8px 12px;background:#fbfdff}
.aksiA.r{border-left-color:#e5484d;background:#fff8f8}.aksiA.a{border-left-color:#f59e0b;background:#fffbf2}
.aksiA b{display:block;font-size:12.5px}
.aksiWhy{display:block;color:#6b7a99;font-size:11px;font-weight:700;margin:2px 0 4px}
.aksiA ul{margin:0;padding-left:16px;font-size:11.5px;font-weight:600;color:#2a3a5c}
.aksiCmWrap{display:grid;gap:12px}
.aksiSeg{display:none}.aksiFab,.aksiDim,.aksiMore,.aksiSheetH{display:none}
.aksiAh{display:flex;gap:8px;align-items:center;justify-content:space-between}.aksiAh i{font-style:normal;color:#6b7a99}
.aksiTg .aksiAd{margin-top:3px}
.aksiCmH{display:flex;gap:8px;align-items:flex-start;justify-content:space-between}.aksiCmH .aksiCmT{flex:1}
.aksiCmD{margin-top:6px}.aksiSt{display:flex;gap:6px;flex-wrap:wrap}
.aksiSt button,.aksiCmB button{border:1px solid #cfdcf3;background:#fff;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:800;color:#0b5cad;cursor:pointer}
.aksiTel{text-decoration:none;font-size:15px}
.aksiAh{cursor:pointer}.aksiAh i{display:block;transition:.2s}.aksiTg.open .aksiAh i{transform:rotate(180deg)}.aksiTg .aksiAd{display:none}.aksiTg.open .aksiAd{display:block}
.aksiCmH{cursor:pointer}.aksiCm .aksiCmD{display:none}.aksiCm.open .aksiCmD{display:block}
@media(max-width:700px){
.aksiSeg{display:flex;gap:6px}.aksiSeg span{flex:1;position:relative;text-align:center;font-size:11.5px;font-weight:900;padding:9px 4px;border-radius:12px;background:#fff;border:1px solid #e3eaf7;color:#6b7a99;cursor:pointer}
.aksiSeg i{position:absolute;top:-5px;right:5px;background:#e5484d;color:#fff;border-radius:999px;font-style:normal;font-size:9px;padding:1px 5px}
.v6xaksiTab[data-sec=p] .aksiSeg [data-seg=p],.v6xaksiTab[data-sec=k] .aksiSeg [data-seg=k],.v6xaksiTab[data-sec=d] .aksiSeg [data-seg=d],.v6xaksiTab[data-sec=a] .aksiSeg [data-seg=a]{color:#0b5cad;border-color:#9ec3ff;background:#eaf2ff}
.v6xaksiTab[data-sec=p] .aksiSec:not([data-sec=p]),.v6xaksiTab[data-sec=k] .aksiSec:not([data-sec=k]),.v6xaksiTab[data-sec=d] .aksiSec:not([data-sec=d]),.v6xaksiTab[data-sec=a] .aksiSec:not([data-sec=a]){display:none}
.aksiSt{display:grid;grid-template-columns:repeat(3,1fr)}.aksiSt button{min-height:44px;font-size:12px;border-radius:12px}
.aksiCmB button{min-height:36px}
.aksiCIn,.aksiNoteIn,.aksiCbIn,.aksiTxt{font-size:16px!important;min-height:42px}
.aksiFab{display:block;position:fixed;right:14px;bottom:78px;z-index:40;border:0;background:linear-gradient(135deg,#2f80ed,#5b9dff);color:#fff;border-radius:999px;width:54px;height:54px;padding:0;font-weight:900;font-size:24px;line-height:1;box-shadow:0 10px 22px rgba(47,128,237,.4)}
.v6xaksiTab:not([data-sec=k]) .aksiFab{display:none}
.aksiDim.open{display:block;position:fixed;inset:0;background:rgba(22,32,46,.4);z-index:55}
.aksiAddWrap,.aksiTeamWrap{position:fixed;left:0;right:0;bottom:0;z-index:56;background:#fff;border-radius:24px 24px 0 0;padding:14px;box-shadow:0 -12px 30px rgba(0,0,0,.18);transform:translateY(110%);transition:transform .22s}
.aksiAddWrap.open,.aksiTeamWrap.open{transform:none}
.aksiSheetH{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;font-size:14px}.aksiSheetH button{border:0;background:#eef2fa;border-radius:999px;width:30px;height:30px;font-weight:900}
.aksiAddWrap .aksiAdd{display:grid;gap:8px}.aksiAddWrap .aksiCb{flex:none;width:100%}.aksiAddWrap .aksiCbList{top:auto;bottom:calc(100% + 3px)}
.aksiAddWrap .aksiAdd>button{min-height:46px;border:0;background:linear-gradient(135deg,#2f80ed,#5b9dff);color:#fff;border-radius:14px;font-size:14px}
.aksiCbO{padding:13px 10px;font-size:14px}
.aksiTeamWrap .aksiAdd>button{min-height:46px;border:0;background:linear-gradient(135deg,#2f80ed,#5b9dff);color:#fff;border-radius:14px;font-size:14px}
.aksiTeamWrap .aksiAdd{display:grid;gap:8px}.aksiTeamWrap .aksiCb{flex:none;width:100%}.aksiTeamWrap .aksiCbList{top:auto;bottom:calc(100% + 3px)}
.aksiFab2{bottom:140px;background:linear-gradient(135deg,#0b5cad,#2f80ed)}
.aksiDlr .aksiK3{grid-template-columns:1fr 1fr}
.aksiFold:not(.showAll) tbody tr:nth-child(n+6){display:none}
.aksiMore{display:block;width:100%;margin-top:8px;border:1px solid #cfdcf3;background:#fff;border-radius:12px;padding:10px;font-size:12px;font-weight:900;color:#0b5cad}
}
.aksiAdd{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px}.aksiAdd input,.aksiAdd select,.aksiNoteIn{border:1px solid #cfdcf3;border-radius:10px;padding:7px 9px;font-size:12px;font-family:inherit}
.aksiAdd .aksiTxt{flex:1 1 260px;min-width:0}.aksiNoteIn{width:100%;margin-top:5px;box-sizing:border-box}
.aksiCm{border:1px solid #e3eaf7;border-left:4px solid #9fb2d6;border-radius:12px;padding:8px 10px;margin-top:6px;background:#fbfdff}
.aksiCm.g{border-left-color:#12a05c}.aksiCm.a{border-left-color:#f59e0b}.aksiCm.r{border-left-color:#e5484d}
.aksiCmT{font-size:12.5px;font-weight:700}.aksiWho{display:inline-block;margin-left:6px;color:#0b5cad;background:#e6f0ff;border-radius:999px;padding:1px 7px;font-size:10px;font-weight:800}
.aksiCmB{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:6px}
.aksiCmB button,.aksiAdd button{border:1px solid #cfdcf3;background:#fff;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:800;color:#0b5cad;cursor:pointer}
.aksiCmB button:hover,.aksiAdd button:hover{background:#e6f0ff}.aksiCmB .aksiDel{color:#c62828}
.aksiChip{font-style:normal;font-size:10px;font-weight:900;border-radius:999px;padding:2px 8px;background:#eef2fa;color:#44557a}
.aksiChip.g{background:#e3f7ec;color:#0b7a45}.aksiChip.a{background:#fff3dc;color:#9a6200}.aksiChip.r{background:#ffe8e8;color:#c62828}
.aksiCmC{margin-top:6px;border-top:1px dashed #e3eaf7;padding-top:5px}
.aksiC{font-size:11.5px;font-weight:600;color:#2a3a5c;margin:2px 0}.aksiC b{color:#0b5cad;margin-right:4px}
.aksiCmIn{display:flex;gap:6px;margin-top:4px}.aksiCIn{flex:1;min-width:0;border:1px solid #cfdcf3;border-radius:10px;padding:5px 9px;font-size:11.5px;font-family:inherit}
.aksiCmIn button{border:1px solid #cfdcf3;background:#fff;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:800;color:#0b5cad;cursor:pointer}
.aksiCb{position:relative;flex:0 1 250px;min-width:170px}.aksiCb.aksiView{flex:1 1 280px}
.aksiCbIn{width:100%;box-sizing:border-box;border:1px solid #cfdcf3;border-radius:10px;padding:7px 26px 7px 9px;font-size:12px;font-family:inherit;background:#fff}
.aksiCbArr{position:absolute;right:9px;top:50%;transform:translateY(-50%);color:#6b7a99;font-size:11px;cursor:pointer}
.aksiCbList{position:absolute;z-index:30;left:0;right:0;top:calc(100% + 3px);max-height:240px;overflow:auto;background:#fff;border:1px solid #cfdcf3;border-radius:12px;box-shadow:0 10px 24px rgba(21,86,184,.16);padding:4px}
.aksiCbList[hidden],.aksiCbO[hidden],.aksiCbNone[hidden]{display:none}
.aksiCbO{display:flex;justify-content:space-between;gap:8px;padding:6px 9px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer}
.aksiCbO:hover{background:#e6f0ff}.aksiCbO small{color:#6b7a99;font-weight:700;font-size:10px}
.aksiCbNone{padding:6px 9px;color:#6b7a99;font-size:11.5px;font-weight:700}
.aksiEmpty{color:#6b7a99;font-size:12px;font-weight:700;padding:4px 0}
.aksiNote{color:#6b7a99;font-size:11px;font-weight:700}
`;
    document.head.appendChild(s);
  }

  const X = () => (typeof V6X !== 'undefined' ? V6X : null);
  const colOf = p => (typeof v6XCol === 'function' ? v6XCol(p) : '#2f80ed');
  const clsOf = p => (p == null ? '' : p >= 100 ? 'g' : p >= 80 ? 'a' : 'r');

  /* jumlahkan beberapa node untuk satu brand/indikator */
  function sumM(keys, nodes, brand, ind) {
    const o = { a: 0, lm: 0, td: 0, t: 0 };
    keys.forEach(k => { const m = v6XM(nodes[k], brand, ind); o.a += m.a; o.lm += m.lm; o.td += m.td; o.t += m.t; });
    return o;
  }

  /* kelompok cakupan per role */
  function groups(d) {
    const nodes = d.nodes, L = d.level, out = [];
    if (L === 'SALES') out.push({ name: 'Cakupan saya', keys: ['O'] });
    else if (L === 'ASM') {
      out.push({ name: 'Tim saya', keys: ['O'] });
      if (nodes['P|' + d.me]) out.unshift({ name: 'Saya sendiri', keys: ['P|' + d.me] });
    } else if (L === 'RGM') {
      const own = Object.keys(nodes).filter(k => k.startsWith('B|') && (d.ownBig || []).map(String).includes(nodes[k].name));
      if (own.length) out.push({ name: 'Area saya', keys: own });
      out.push({ name: 'Nasional (pembanding)', keys: ['N'] });
    } else out.push({ name: 'Nasional', keys: ['N'] });
    return out.filter(g => g.keys.every(k => nodes[k]));
  }

  function kpiCards(d, g) {
    const left = Math.max(0, d.days - d.run);
    return '<div class="aksiK3">' + IND.map(([ind, label, unit]) => {
      const m = sumM(g.keys, d.nodes, 'ALL', ind), c = v6XCalc(m, ind, d), noT = ind === 'noo', cl = noT ? '' : clsOf(c.pp);
      const rows = [];
      if (noT) {
        const df = m.a - m.lm;
        const gn = m.lm ? df / m.lm * 100 : null;
        rows.push(['LMTD', N(m.lm) + (gn == null ? '' : ' <small style="display:inline">|</small> <span style="display:inline;white-space:nowrap" class="' + (gn >= 0 ? 'aksiUp' : 'aksiRed') + '">' + (gn >= 0 ? '▲ +' : '▼ ') + (typeof decimal === 'function' ? decimal(Math.abs(gn)) : Math.abs(gn).toFixed(1)) + '%</span>') + ' <small style="display:inline">|</small> ' + (df > 0 ? '+' : df < 0 ? '−' : '') + N(Math.abs(df))]);
      } else if (c.hasT) {
        rows.push(['Target', N(m.t) + ' · ' + P(c.cap)]);
        rows.push(['Proyeksi', N(c.proj) + ' · ' + P(c.pp)]);
        const gap = Math.max(0, m.t - m.a);
        rows.push(['Gap ke target', gap > 0 ? '<i class="aksiRed">' + N(gap) + '</i> ' + unit : 'tercapai']);
      } else rows.push(['Target', 'belum ada']);
      if (ind !== 'da') {
        rows.push(['Run rate / hari kerja', (d.run ? (m.a / d.run > 0 && m.a / d.run < 1 ? '<1' : N(m.a / d.run)) : '0') + ' ' + unit]);
        if (c.hasT && !noT) rows.push(['Butuh / hari kerja', left ? N(Math.max(0, m.t - m.a) / left) + ' ' + unit + ' <small>(sisa ' + left + ' hari)</small>' : 'periode selesai']);
      }
      if (ind === 'st') {
        const gr = m.lm ? (m.a - m.lm) / m.lm * 100 : null;
        rows.push(['LMTD', N(m.lm) + ' unit' + (gr == null ? '' : ' · <span class="' + (gr >= 0 ? 'aksiUp' : 'aksiRed') + '">' + (gr >= 0 ? '▲ +' : '▼ ') + (typeof decimal === 'function' ? decimal(Math.abs(gr)) : Math.abs(gr).toFixed(1)) + '%</span>')]);
      }
      if (ind === 'da' && d.level === 'TOP' && g.keys.length === 1 && g.keys[0] === 'N' && d.dealer) {
        const du = +(d.dealer.aktifUniq || 0), dl = +(d.dealer.aktifUniqLmtd || 0), dg = dl ? (du - dl) / dl * 100 : null;
        rows.push(['Dealer unik', N(du) + (dg == null ? '' : ' · <span class="' + (dg >= 0 ? 'aksiUp' : 'aksiRed') + '">' + (dg >= 0 ? '▲ +' : '▼ ') + (typeof decimal === 'function' ? decimal(Math.abs(dg)) : Math.abs(dg).toFixed(1)) + '%</span>')]);
        rows.push(['Dealer unik LMTD', N(dl)]);
      }
      rows.push(['Hari ini', N(m.td) + ' ' + unit]);
      return `<div class="aksiK ${cl}"><small>${label}</small><b>${N(m.a)}</b><dl>${rows.map(r => `<div><dt>${r[0]}</dt><dd>${r[1]}</dd></div>`).join('')}</dl></div>`;
    }).join('') + soCard(d, g) + omzCard(d, g) + '</div>';
  }

  /* kartu OMZET (total, tanpa target) + omzet per badan usaha (total saja) */
  function omzCard(d, g) {
    const rp = n => (typeof moneyShort === 'function' ? moneyShort(n) : 'Rp ' + N(n));
    const m = sumM(g.keys, d.nodes, 'ALL', 'om');
    const df = m.a - m.lm, gr = m.lm ? df / m.lm * 100 : null, up = df >= 0;
    const rows = [
      ['LMTD', rp(m.lm) + (gr == null ? '' : ' <small style="display:inline">|</small> <span style="display:inline;white-space:nowrap" class="' + (up ? 'aksiUp' : 'aksiRed') + '">' + (up ? '▲ +' : '▼ ') + (typeof decimal === 'function' ? decimal(Math.abs(gr)) : Math.abs(gr).toFixed(1)) + '%</span>') + ' <small style="display:inline">|</small> ' + (up ? '+' : '−') + rp(Math.abs(df))],
      ['Run rate / hari kerja', rp(d.run ? m.a / d.run : 0)],
      ['Hari ini', rp(m.td)]
    ];
    const agg = {};
    g.keys.forEach(k => Object.entries((d.co || {})[k] || {}).forEach(([c, v]) => { const o = agg[c] || (agg[c] = [0, 0]); o[0] += v[0] || 0; o[1] += v[1] || 0; }));
    const sum = Object.values(agg).reduce((t, v) => t + v[0], 0), rest = m.a - sum;
    const list = Object.entries(agg).filter(([, v]) => v[0] > 0).sort((a, b) => b[1][0] - a[1][0]);
    if (list.length && rest > Math.max(1000, Math.abs(m.a) * 0.005)) list.push(['Selisih (koreksi/atribusi)', [rest, 0]]);
    const bu = list.length ? '<div class="aksiBUh">OMZET PER BADAN USAHA</div><dl>' + list.map(([c, v]) => {
      const pc = m.a ? v[0] / m.a * 100 : 0;
      return `<div><dt>${E(c)}</dt><dd>${rp(v[0])} · ${P(pc)}</dd></div>`;
    }).join('') + '</dl>' : '';
    return `<div class="aksiK aksiOm"><small>OMZET (SEMUA BRAND)</small><b>${rp(m.a)}</b><dl>${rows.map(r => `<div style="flex-wrap:nowrap"><dt style="white-space:nowrap">${r[0]}</dt><dd style="white-space:nowrap;display:block">${r[1]}</dd></div>`).join('')}</dl>${bu}</div>`;
  }

  /* kartu SELL OUT + % SO/ST (tanpa target; SO belum punya target di DSM) */
  function soCard(d, g) {
    if (!d.soReady) return '<div class="aksiK"><small>SELL OUT</small><b>—</b><dl><div><dt>Status</dt><dd>data SO belum lengkap</dd></div></dl></div>';
    const so = sumM(g.keys, d.nodes, 'ALL', 'so'), st = sumM(g.keys, d.nodes, 'ALL', 'st');
    const p = st.a > 0 ? so.a / st.a * 100 : null, pl = st.lm > 0 ? so.lm / st.lm * 100 : null;
    const cl = p == null ? '' : (typeof stsoCls === 'function' ? ({ r: 'r', a: 'a', g: 'g', b: 'g', n: '' }[stsoCls(p)] || '') : '');
    const rows = [['% SO/ST', p == null ? '—' : P(p)], ['% SO/ST LMTD', pl == null ? '—' : P(pl)], ['SO LMTD', N(so.lm) + ' unit'], ['Hari ini', N(so.td) + ' unit']];
    return `<div class="aksiK ${cl}"><small>SELL OUT</small><b>${N(so.a)}</b><dl>${rows.map(r => `<div><dt>${r[0]}</dt><dd>${r[1]}</dd></div>`).join('')}</dl></div>`;
  }

  function kpiScore(d) {
    const k = d.kpiMe; if (!k || k.score == null) return '';
    const sc = Number(k.score), cl = sc >= 100 ? 'g' : sc >= 80 ? 'a' : 'r';
    return `<div class="aksiK3"><div class="aksiK ${cl}"><small>SKOR KPI</small><b>${E(typeof pct === 'function' ? pct(sc) : sc + '%')}</b><span>${E(k.status || '')}</span></div></div>`;
  }

  /* baris rincian saat brand dibuka */
  function detailRows(d, g, brand) {
    const nodes = d.nodes, L = d.level, rows = [];
    const push = (name, k) => { const m = v6XM(nodes[k], brand, 'st'); if (m.a || m.t) rows.push({ name, m, c: v6XCalc(m, 'st', d) }); };
    if (L === 'ASM') Object.keys(nodes).filter(k => k.startsWith('P|') && k !== 'P|' + d.me).forEach(k => push(nodes[k].name, k));
    else if (L === 'RGM') Object.keys(nodes).filter(k => g.keys.some(bk => nodes[bk] && k.startsWith('S|' + nodes[bk].name + '|'))).forEach(k => push(nodes[k].name, k));
    else if (L !== 'SALES') Object.keys(nodes).filter(k => k.startsWith('B|')).forEach(k => push(nodes[k].name, k));
    if (L === 'SALES') {
      const ds = (d.dealers || []).map(x => ({ n: x.nama, a: (x.br || {})[brand] || 0, l: (x.lmBr || {})[brand] || 0 })).filter(x => x.a || x.l).sort((a, b) => b.a - a.a || b.l - a.l).slice(0, 12);
      if (!ds.length) return '<div class="aksiEmpty">Belum ada dealer dengan penjualan brand ini.</div>';
      return `<table><tr><th>Dealer</th><th>MTD</th><th>Bulan lalu</th></tr>${ds.map(x => `<tr><td>${E(x.n)}</td><td>${N(x.a)}</td><td>${N(x.l)}</td></tr>`).join('')}</table>`;
    }
    if (!rows.length) return '<div class="aksiEmpty">Tidak ada rincian di bawah level ini.</div>';
    rows.sort((a, b) => (a.c.pp == null ? 999 : a.c.pp) - (b.c.pp == null ? 999 : b.c.pp));
    return `<table><tr><th>${L === 'ASM' ? 'Sales' : L === 'RGM' ? 'Sub region' : 'Big region'}</th><th>ST</th><th>Hari ini</th><th>Target</th><th>Capai</th><th>Proy.</th></tr>${rows.map(r => `<tr><td>${E(r.name)}</td><td>${N(r.m.a)}</td><td>${N(r.m.td)}</td><td>${r.m.t ? N(r.m.t) : '—'}</td><td>${r.c.hasT ? P(r.c.cap) : '—'}</td><td style="color:${colOf(r.c.pp)};font-weight:800">${r.c.pp == null ? '—' : P(r.c.pp)}</td></tr>`).join('')}</table>`;
  }

  function brandCards(d, g) {
    const list = d.brands.map(b => { const m = sumM(g.keys, d.nodes, b, 'st'); return { b, m, c: v6XCalc(m, 'st', d) }; })
      .filter(x => x.m.a || x.m.t).sort((a, b) => (a.c.pp == null ? 999 : a.c.pp) - (b.c.pp == null ? 999 : b.c.pp));
    if (!list.length) return '<div class="aksiEmpty">Belum ada penjualan atau target brand pada cakupan ini.</div>';
    const hint = d.level === 'SALES' ? 'dealer' : d.level === 'ASM' ? 'sales' : d.level === 'RGM' ? 'sub region' : 'big region';
    return `<div class="aksiBg">${list.map(x => {
      const w = Math.min(120, x.c.cap || 0) / 120 * 100, tg = Math.min(100, x.c.tg) / 120 * 100;
      return `<details class="aksiBr"><summary><div class="top"><span><i>▸</i>${E(x.b)}</span><span>${N(x.m.a)}${x.m.t ? ' / ' + N(x.m.t) : ''}${x.c.hasT ? ' · ' + P(x.c.cap) : ''}</span></div><div class="aksiBar"><i style="width:${w}%;background:${colOf(x.c.pp)}"></i><u style="left:${tg}%"></u></div><div class="aksiTd">Hari ini<span class="aksiTdD"> (${E(typeof tgl === 'function' ? tgl(d.cutoff) : d.cutoff)})</span>: <b>${N(x.m.td)}</b> unit</div></summary><div class="aksiSub" data-aksi-brand="${E(x.b)}">${detailRows(d, g, x.b)}</div></details>`;
    }).join('')}</div><div class="aksiNote" style="margin-top:6px">Klik brand untuk membuka ${hint}. Garis tipis = posisi time gone (${P(d.run / d.days * 100)}).</div>`;
  }

  function renderMain(d) {
    const gs = groups(d);
    if (!gs.length) return '<div class="aksiBox"><div class="aksiEmpty">Belum ada data untuk cakupan ini.</div></div>';
    const kpi = `<div class="aksiBox"><h3>Ringkasan SELLTHRU<em>data s.d. ${E(typeof tgl === 'function' ? tgl(d.cutoff) : d.cutoff)} · time gone ${P(d.run / d.days * 100)}</em></h3>${kpiScore(d)}${gs.map(g => `<div class="aksiGrp">${E(g.name)}</div>${kpiCards(d, g)}`).join('')}</div>`;
    const g0 = gs.find(g => /Area saya|Tim saya|Cakupan saya|Nasional$/.test(g.name)) || gs[0];
    const brands = `<div class="aksiBox"><h3>Pencapaian SELL THRU per brand<em>${E(g0.name)}</em></h3>${brandCards(d, g0)}</div>`;
    return kpi + brands;
  }

  /* ---------- A2: dealer (NOO + repeat order) ---------- */
  const mainGroup = d => { const gs = groups(d); return gs.find(g => /Area saya|Tim saya|Cakupan saya|Nasional$/.test(g.name)) || gs[0]; };
  const repOf = (d, keys) => keys.reduce((o, k) => { const r = (d.rep || {})[k]; if (r) { o[0] += r[0]; o[1] += r[1]; } return o; }, [0, 0]);
  const gTxt = (g, dec) => g == null ? '' : ' <small style="display:inline">|</small> <span style="display:inline;white-space:nowrap" class="' + (g >= 0 ? 'aksiUp' : 'aksiRed') + '">' + (g >= 0 ? '▲ +' : '▼ ') + (typeof decimal === 'function' ? decimal(Math.abs(g)) : Math.abs(g).toFixed(1)) + '%</span>';

  function dealerBlock(d, g) {
    const noo = sumM(g.keys, d.nodes, 'ALL', 'noo'), ng = noo.lm ? (noo.a - noo.lm) / noo.lm * 100 : null;
    const [base, rep] = repOf(d, g.keys), pr = base ? rep / base * 100 : null, belum = base - rep;
    const cards = `<div class="aksiK3">
      <div class="aksiK"><small>NOO (DEALER BARU)</small><b>${N(noo.a)}</b><dl><div><dt>LMTD</dt><dd>${N(noo.lm)}${gTxt(ng)}</dd></div><div><dt>Hari ini</dt><dd>${N(noo.td)}</dd></div></dl></div>
      <div class="aksiK ${pr == null ? '' : pr >= 70 ? 'g' : pr >= 50 ? 'a' : 'r'}"><small>REPEAT ORDER</small><b>${pr == null ? '—' : P(pr)}</b><dl><div><dt>Order bulan lalu</dt><dd>${N(base)} dealer</dd></div><div><dt>Sudah order lagi</dt><dd>${N(rep)} dealer</dd></div><div><dt>Belum repeat</dt><dd><i class="aksiRed">${N(belum)}</i> dealer</dd></div></dl></div></div>`;
    let lists = '';
    if (Array.isArray(d.dealers) && d.dealers.length) {
      const idle = d.dealers.filter(x => !x.mtd && x.lm > 0).sort((a, b) => b.lm - a.lm).slice(0, 10);
      const nw = d.dealers.filter(x => x.noo).sort((a, b) => String(b.noo).localeCompare(String(a.noo))).slice(0, 8);
      const tel = x => { const h = String(x.hp || '').replace(/[^0-9+]/g, ''); return h ? `<a class="aksiTel" href="tel:${E(h)}" title="Telepon">📞</a>` : ''; };
      const row = (x, v) => `<tr><td>${E(x.nama)}</td><td>${E(x.kota || '')}</td><td>${v}</td><td>${tel(x)}</td></tr>`;
      lists = `<div class="aksiDuo"><div class="aksiSub aksiFold"><div class="aksiGrp">Belum repeat — order bulan lalu, belum order bulan ini</div>${idle.length ? `<table><thead><tr><th>Dealer</th><th>Kota</th><th>Bulan lalu</th><th></th></tr></thead><tbody>${idle.map(x => row(x, N(x.lm) + ' unit')).join('')}</tbody></table><button class="aksiMore" data-a="more">Lihat dealer lainnya</button>` : '<div class="aksiEmpty">Semua dealer sudah order lagi.</div>'}</div>
        <div class="aksiSub"><div class="aksiGrp">Dealer baru (NOO) bulan ini</div>${nw.length ? `<table><thead><tr><th>Dealer</th><th>Kota</th><th>Order pertama</th><th></th></tr></thead><tbody>${nw.map(x => row(x, E(typeof tgl === 'function' ? tgl(x.noo) : x.noo))).join('')}</tbody></table>` : '<div class="aksiEmpty">Belum ada dealer baru.</div>'}</div></div>`;
    } else if (d.level === 'RGM' || d.level === 'TOP') {
      const ks = Object.keys(d.nodes).filter(k => (d.level === 'TOP' ? k.startsWith('B|') : k.startsWith('S|')) && (d.rep || {})[k]);
      const rows = ks.map(k => { const r = d.rep[k], n = d.nodes[k]; return { n: n.name, b: r[0], r: r[1], p: r[0] ? r[1] / r[0] * 100 : 0 }; }).sort((a, b) => a.p - b.p);
      if (rows.length) lists = `<div class="aksiSub"><div class="aksiGrp">Repeat order per ${d.level === 'TOP' ? 'Big Region' : 'region'} (terendah dulu)</div><table><thead><tr><th>${d.level === 'TOP' ? 'Big Region' : 'Region'}</th><th>Bulan lalu</th><th>Repeat</th><th>%</th></tr></thead><tbody>${rows.map(x => `<tr><td>${E(x.n)}</td><td>${N(x.b)}</td><td>${N(x.r)}</td><td>${P(x.p)}</td></tr>`).join('')}</tbody></table></div>`;
    }
    return `<div class="aksiBox aksiDlr"><h3>Dealer<em>${E(g.name)}</em></h3>${cards}${lists}<div class="aksiNote" style="margin-top:6px">Repeat order = dealer yang order bulan lalu dan juga order bulan ini. Bulan ini masih berjalan, angka masih bisa naik.</div></div>`;
  }

  /* ---------- A3: poin ringkasan + prioritas tindakan (berbasis aturan) ---------- */
  function actions(d, g) {
    const out = [], tg = d.days ? d.run / d.days * 100 : 0, left = Math.max(0, d.days - d.run);
    const add = (sev, title, why, steps) => out.push({ sev, title, why, steps });
    const st = sumM(g.keys, d.nodes, 'ALL', 'st'), c = v6XCalc(st, 'st', d);
    const lag = d.brands.map(b => { const m = sumM(g.keys, d.nodes, b, 'st'); return { b, m, c: v6XCalc(m, 'st', d) }; }).filter(x => x.c.hasT && x.c.pp < 85).sort((a, b) => a.c.pp - b.c.pp);
    const lagArr = lag.length ? ['Brand tertinggal: ' + lag.slice(0, 5).map(x => x.b + ' ' + P(x.c.pp)).join(' · ')] : [];
    if (c.hasT) {
      const gap = Math.max(0, st.t - st.a), need = left ? gap / left : gap, rr = d.run ? st.a / d.run : 0;
      if (c.pp < 85) add('r', `Proyeksi SELL THRU baru ${P(c.pp)} dari target`, [`Gap ${N(gap)} unit;`, `butuh ${N(need)} unit/hari kerja, run rate sekarang ${N(rr)} unit/hari.`], [...lagArr, 'Buka brand yang tertinggal di tab Ringkasan (klik kartu brand) dan cek region penyebabnya.', 'Kejar dealer yang belum order brand tersebut bulan ini.', 'Tetapkan target harian tim sebesar kebutuhan per hari kerja.']);
      else if (c.pp < 100) add('a', `Proyeksi SELL THRU ${P(c.pp)} — belum sampai target`, [`Gap ${N(gap)} unit;`, `butuh ${N(need)} unit/hari kerja vs run rate ${N(rr)}.`], [...lagArr, 'Pastikan brand yang tertinggal dikejar sebelum akhir periode.']);
    }
    if (d.soReady) {
      const so = sumM(g.keys, d.nodes, 'ALL', 'so'), p = st.a > 0 ? so.a / st.a * 100 : null;
      if (p != null && p < 70) add('a', `% SO/ST rendah: ${P(p)}`, `Sell out ${N(so.a)} dari sell thru ${N(st.a)} — stok menumpuk di dealer.`, ['Tahan penambahan ST ke dealer dengan stok tinggi.', 'Dorong sell out (promo/aktivitas toko) di dealer berstok besar.']);
    }
    const [base, rep] = repOf(d, g.keys), belum = base - rep;
    if (base && belum / base > 0.3) add('a', `${N(belum)} dari ${N(base)} dealer belum repeat order`, `Baru ${P(rep / base * 100)} dealer bulan lalu yang order lagi bulan ini.`, ['Hubungi dealer di daftar "Belum repeat" (tab ini), mulai dari volume bulan lalu terbesar.', 'Cari tahu alasan: stok masih ada, harga, atau kunjungan kurang.']);
    if (Array.isArray(d.dealers) && d.run) {
      const hi = d.dealers.filter(x => x.stok > 0 && x.mtd > 0 && x.stok / (x.mtd / d.run) > 45);
      if (hi.length) add('a', `${N(hi.length)} dealer berstok lebih dari 45 hari`, 'Perkiraan hari stok dihitung dari stok dibagi rata-rata ST harian bulan ini.', ['Jangan dorong ST tambahan ke dealer ini; fokus sell out.', 'Cek stok model lambat dan pertimbangkan tukar/alihkan.']);
    }
    /* unit bawahan yang tertinggal jauh dari time gone */
    const kids = Object.keys(d.nodes).filter(k => (d.level === 'ASM' ? k.startsWith('P|') && k !== 'P|' + d.me : (d.level === 'RGM' ? k.startsWith('S|') : (d.level === 'TOP' ? k.startsWith('B|') : false))));
    const lagK = kids.map(k => { const m = v6XM(d.nodes[k], 'ALL', 'st'); return { n: d.nodes[k].name, m, pct: m.t ? m.a / m.t * 100 : null }; }).filter(x => x.m.t > 0 && x.pct < tg * 0.8).sort((a, b) => a.pct - b.pct).slice(0, 3);
    if (lagK.length) add('r', `${lagK.length} ${d.level === 'ASM' ? 'sales' : d.level === 'TOP' ? 'Big Region' : 'region'} jauh di bawah time gone (${P(tg)})`, lagK.map(x => `${x.n} ${P(x.pct)}`).join(' · '), ['Jadwalkan review mingguan dengan yang tertinggal.', 'Cek kendala: dealer idle, stok, atau target belum realistis.']);
    return out.sort((a, b) => ({ r: 0, a: 1 }[a.sev] - { r: 0, a: 1 }[b.sev]));
  }

  /* ---------- Perubahan harian: hari data terakhir vs hari data sebelumnya ---------- */
  function dailyBlock(d, g) {
    if (!d.pd) return '';
    const Y = ind => g.keys.reduce((t, k) => { const n = d.nodes[k], x = n && n.m && n.m.ALL; return t + (x && x[ind] ? (x[ind][3] || 0) : 0); }, 0);
    const T = ind => sumM(g.keys, d.nodes, 'ALL', ind).td;
    const rp = n => (typeof moneyShort === 'function' ? moneyShort(n) : 'Rp ' + N(n));
    const items = [['SELL THRU', 'st', N, 'unit'], ['OMZET', 'om', rp, '']];
    items.push(['DEALER BARU (NOO)', 'noo', N, '']);
    if (d.soReady) items.push(['SELL OUT', 'so', N, 'unit']);
    const fm = x => (typeof tgl === 'function' ? tgl(x) : x);
    const cards = items.map(([lb, ind, f, u]) => {
      const t = T(ind), y = Y(ind), df = t - y, gr = y > 0 ? df / y * 100 : null, up = df >= 0;
      const dd = df === 0 ? 'sama' : (up ? '▲ +' : '▼ −') + f(Math.abs(df));
      return `<div class="aksiK ${df === 0 ? '' : up ? 'g' : 'r'}"><small>${lb}</small><b>${f(t)}${u ? ' <small style="display:inline;font-size:11px">' + u + '</small>' : ''}</b><dl><div><dt>Kemarin</dt><dd>${f(y)}</dd></div><div><dt>Selisih</dt><dd><span style="display:inline;white-space:nowrap" class="${df === 0 ? '' : up ? 'aksiUp' : 'aksiRed'}">${dd}</span>${gr == null || df === 0 ? '' : ' <small style="display:inline">|</small> ' + (up ? '+' : '−') + (typeof decimal === 'function' ? decimal(Math.abs(gr)) : Math.abs(gr).toFixed(1)) + '%'}</dd></div></dl></div>`;
    }).join('');
    return `<div class="aksiBox"><h3>Dibanding kemarin<em>${E(fm(d.cutoff))} vs ${E(fm(d.pd))}</em></h3><div class="aksiK3">${cards}</div><div class="aksiNote" style="margin-top:6px">Kemarin = hari data sebelumnya yang ada transaksi. Angka per hari, bukan akumulasi bulan.</div></div>`;
  }

  function actionBlock(d, g) {
    const list = actions(d, g);
    if (!list.length) return '<div class="aksiBox"><h3>Prioritas tindakan</h3><div class="aksiEmpty">Tidak ada yang melewati ambang peringatan. Pertahankan.</div></div>';
    return `<div class="aksiBox"><h3>Prioritas tindakan<em>${list.length} poin</em></h3><div class="aksiAct">${list.map((x, i) => `<div class="aksiA aksiTg ${x.sev}${i === 0 ? ' open' : ''}"><div class="aksiAh" data-tg><b>${i + 1}. ${E(x.title)}</b><i>▾</i></div><div class="aksiAd"><span class="aksiWhy">${[].concat(x.why).map(E).join('<br>')}</span><ul>${x.steps.map(t => `<li>${E(t)}</li>`).join('')}</ul></div></div>`).join('')}</div><div class="aksiNote" style="margin-top:6px">Aturan: proyeksi &lt;85% merah, % SO/ST &lt;70% kuning, hari stok &gt;45 kuning, bawahan &lt;80% dari time gone merah.</div></div>`;
  }

  /* ---------- A4: kesehatan data & operasional (Admin) ---------- */
  function adminBlock(d) {
    const a = d.admin; if (!a) return '';
    const today = new Date(), lag = c => { if (!c) return null; const x = new Date(c + 'T00:00:00'); return Math.max(0, Math.round((new Date(today.getFullYear(), today.getMonth(), today.getDate()) - x) / 864e5)); };
    const lg = lag(a.stCut), ls = lag(a.soCut);
    const it = (cls, t, v, hint) => `<div class="aksiA ${cls}"><b>${E(t)}: ${v}</b>${hint ? `<span class="aksiWhy">${E(hint)}</span>` : ''}</div>`;
    return `<div class="aksiBox"><h3>Kesehatan data &amp; operasional<em>Admin · ${E(a.period)}</em></h3><div class="aksiAct">
      ${it(!a.stCut ? 'r' : lg > 2 ? 'a' : 'g', 'Upload SELL THRU', a.stCut ? 's.d. ' + E(typeof tgl === 'function' ? tgl(a.stCut) : a.stCut) + (lg ? ' (' + lg + ' hari lalu)' : ' (terbaru)') : 'belum ada', lg > 2 ? 'Data tertinggal; upload ST terbaru agar angka Beranda tidak basi.' : '')}
      ${it(!a.soCut ? 'a' : ls > 2 ? 'a' : 'g', 'Upload SELL OUT', a.soCut ? 's.d. ' + E(typeof tgl === 'function' ? tgl(a.soCut) : a.soCut) + (ls ? ' (' + ls + ' hari lalu)' : ' (terbaru)') : 'belum ada', !a.soCut || ls > 2 ? 'SO belum lengkap, kartu % SO/ST bisa kosong.' : '')}
      ${it(a.review > 0 ? 'a' : 'g', 'Antrean Perlu Ditinjau (Koreksi)', N(a.review) + ' baris', a.review > 0 ? 'Tinjau di menu Koreksi sebelum periode dikunci.' : '')}
      ${it('g', 'Transaksi terkoreksi bulan ini', N(a.corrected) + ' baris', '')}
      ${it(a.locked ? 'g' : 'a', 'Kunci periode', a.locked ? 'terkunci' : 'belum dikunci', a.locked ? a.lockReason : 'Kunci setelah data final & antrean Koreksi kosong.')}
    </div></div>`;
  }

  /* ---------- Komitmen mingguan + review minggu lalu ---------- */
  let CM = null;
  const stLbl = { OPEN: 'Berjalan', DONE: 'Selesai', PARTIAL: 'Sebagian', NOT: 'Belum' };
  const stCls = { OPEN: '', DONE: 'g', PARTIAL: 'a', NOT: 'r' };
  const wkTxt = w => { const t = new Date(w + 'T00:00:00'), e = new Date(t.getTime() + 6 * 864e5), f = x => x.getDate() + '/' + (x.getMonth() + 1); return f(t) + '–' + f(e); };

  function commitHtml() {
    if (!CM) return '<div class="aksiBox"><h3>Komitmen mingguan</h3><div class="aksiEmpty">Memuat…</div></div>';
    if (CM.err) return '<div class="aksiBox"><h3>Komitmen mingguan</h3><div class="aksiEmpty">Belum bisa dimuat: ' + E(CM.err) + '</div></div>';
    const cur = CM.rows.filter(r => r.week === CM.week), prv = CM.rows.filter(r => r.week === CM.lastWeek);
    const mine = cur.filter(r => r.mine), mineP = prv.filter(r => r.mine);
    const done = a => a.filter(r => r.status === 'DONE').length + a.filter(r => r.status === 'PARTIAL').length * 0.5;
    const dn = x => Number.isInteger(x) ? String(x) : x.toFixed(1).replace('.', ',');
    const who = r => (r.mine ? '' : `<small class="aksiWho">${E(r.ownerName)}</small>`) + (r.fromBoss && r.mine ? `<small class="aksiWho">dari ${E(r.authorName)}</small>` : '');
    const cmts = r => `<div class="aksiCmC">${((CM.cms || {})[r.id] || []).map(c => `<div class="aksiC"><b>${E(c.byName)}</b> ${E(c.text)}</div>`).join('')}<div class="aksiCmIn"><input class="aksiCIn" maxlength="400" placeholder="Tulis komentar / alasan…"><button data-a="cm">Kirim</button></div></div>`;
    const stBtns = '<div class="aksiSt"><button data-a="st" data-s="DONE">Selesai</button><button data-a="st" data-s="PARTIAL">Sebagian</button><button data-a="st" data-s="NOT">Belum</button></div>';
    const cmHead = r => `<div class="aksiCmH" data-tg><div class="aksiCmT"><span>${E(r.text)}</span>${who(r)}</div><em class="aksiChip ${stCls[r.status]}">${stLbl[r.status]}</em></div>`;
    const item = r => `<div class="aksiCm ${stCls[r.status]}" data-id="${r.id}">${cmHead(r)}<div class="aksiCmD">${stBtns}${r.status === 'OPEN' && r.canEdit ? '<div class="aksiCmB"><button data-a="del" class="aksiDel">Hapus</button></div>' : ''}${r.note ? `<div class="aksiNote">Catatan: ${E(r.note)}</div>` : ''}${cmts(r)}</div></div>`;
    const reviewItem = r => `<div class="aksiCm ${stCls[r.status]}" data-id="${r.id}">${cmHead(r)}<div class="aksiCmD">${stBtns}${r.status !== 'DONE' && r.mine ? '<div class="aksiCmB"><button data-a="carry">Bawa ke minggu ini</button></div>' : ''}<input class="aksiNoteIn" placeholder="Catatan penilaian (opsional)" value="${E(r.note || '')}">${cmts(r)}</div></div>`;
    const RS = { 'HEAD OF SALES': 'HOS', 'CHIEF OPERATING OFFICER': 'COO', 'CHIEF COMMERCIAL OFFICER': 'CCO', 'BOARD OF DIRECTOR': 'BOD' };
    const lbl = p => p.nama + ' (' + (RS[p.role] || p.role) + ')';
    const cb = (cls, items, selNik, selLbl, ph) => `<div class="aksiCb ${cls}"><input class="aksiCbIn" autocomplete="off" placeholder="${E(ph)}" value="${E(selLbl)}" data-nik="${E(selNik)}" data-lbl="${E(selLbl)}"><span class="aksiCbArr">▾</span><div class="aksiCbList" hidden>${items.map(i => `<div class="aksiCbO" data-nik="${E(i.nik)}" data-lbl="${E(i.label)}"><span>${E(i.label)}</span>${i.sub ? `<small>${E(i.sub)}</small>` : ''}</div>`).join('')}<div class="aksiCbNone" hidden>Tidak ada yang cocok</div></div></div>`;
    const others = CM.people.filter(p => p.nik !== CM.me);
    const opt = others.length ? cb('aksiOwn', [{ nik: CM.me, label: 'Saya sendiri' }].concat(others.map(p => ({ nik: p.nik, label: lbl(p) }))), CM.me, 'Saya sendiri', 'Untuk siapa…') : '';
    const rateOf = a => a.length ? `${dn(done(a))} dari ${a.length} terselesaikan (${P(done(a) / a.length * 100)})` : '';
    const rate = rateOf(mineP);
    /* komitmen tim: pilih orangnya dulu, baru tampil */
    const vp = others.find(p => p.nik === CM.view);
    let teamBox = '';
    if (others.length) {
      const cnt = p => { const n = cur.filter(r => r.owner === p.nik).length; return n ? n + ' komitmen minggu ini' : ''; };
      const vc = vp ? cur.filter(r => r.owner === vp.nik) : [], vl = vp ? prv.filter(r => r.owner === vp.nik) : [];
      teamBox = `<div class="aksiBox aksiCmBox"><h3>Komitmen tim<em data-a="openteam" style="cursor:pointer">${vp ? E(vp.nama) : 'pilih anggota'} ▾</em></h3>
        <div class="aksiTeamWrap"><div class="aksiSheetH"><b>Cari komitmen tim</b><button data-a="closeteam">✕</button></div><div class="aksiAdd">${cb('aksiView', others.map(p => ({ nik: p.nik, label: lbl(p), sub: cnt(p) })), vp ? vp.nik : '', vp ? lbl(vp) : '', 'Ketik / pilih nama anggota tim…')}<button data-a="doteam" title="Cari" aria-label="Cari">🔍 Cari</button></div></div>
        ${vp ? `<div class="aksiGrp">${E(vp.nama)} · minggu ini ${wkTxt(CM.week)}</div>${vc.length ? vc.map(item).join('') : '<div class="aksiEmpty">Belum ada komitmen minggu ini.</div>'}
          <div class="aksiGrp" style="margin-top:8px">Minggu lalu ${wkTxt(CM.lastWeek)}${vl.length ? ' · ' + rateOf(vl) : ''}</div>${vl.length ? vl.map(reviewItem).join('') : '<div class="aksiEmpty">Tidak ada komitmen minggu lalu.</div>'}` : '<div class="aksiEmpty">Pilih anggota tim untuk melihat komitmennya.</div>'}</div><div class="aksiDim aksiDimT" data-a="closeteam"></div><button class="aksiFab aksiFab2" data-a="openteam" title="Cari komitmen tim" aria-label="Cari komitmen tim">🔍</button>`;
    }
    return `<div class="aksiBox aksiCmBox"><h3>Komitmen minggu ini<em>${wkTxt(CM.week)}</em></h3>
      <div class="aksiAddWrap"><div class="aksiSheetH"><b>Komitmen baru</b><button data-a="closeadd">✕</button></div><div class="aksiAdd"><input class="aksiTxt" maxlength="400" placeholder="Tulis komitmen minggu ini, contoh: kunjungi 5 dealer yang belum repeat order">${opt}<button data-a="add">Tambah</button></div></div>
      ${mine.length ? mine.map(item).join('') : '<div class="aksiEmpty">Belum ada komitmen minggu ini.</div>'}</div><div class="aksiDim" data-a="closeadd"></div><button class="aksiFab" data-a="openadd" title="Tambah komitmen" aria-label="Tambah komitmen">＋</button>
      <div class="aksiBox aksiCmBox"><h3>Review minggu lalu<em>${wkTxt(CM.lastWeek)}${rate ? ' · ' + rate : ''}</em></h3>
      ${mineP.length ? mineP.map(reviewItem).join('') : '<div class="aksiEmpty">Tidak ada komitmen minggu lalu.</div>'}</div>${teamBox}`;
  }

  function commitLoad(root) {
    if (typeof rpc !== 'function' || typeof TOKEN === 'undefined') return;
    rpc('apiCommitBoot', TOKEN).then(r => { r.view = CM && CM.view || ''; CM = r; }).catch(e => { CM = { err: e && e.message || 'gagal' }; }).then(() => { const h = root.querySelector('[data-aksi-commit]'); if (h) h.innerHTML = commitHtml(); });
  }

  function commitBind(root) {
    if (root.dataset.cmBound) return; root.dataset.cmBound = '1';
    root.addEventListener('keydown', ev => { if (ev.key === 'Enter' && ev.target.classList && (ev.target.classList.contains('aksiCIn') || ev.target.classList.contains('aksiTxt'))) { const b = ev.target.parentElement.querySelector('button[data-a]'); if (b) b.click(); } });
    /* combobox: klik = tampil daftar, ketik = saring, pilih dari daftar (tidak menerima teks bebas) */
    const closeAll = except => root.querySelectorAll('.aksiCbList').forEach(l => { if (l !== except) l.hidden = true; });
    const filt = inp => { const q = inp.value.trim().toLowerCase(), l = inp.parentElement.querySelector('.aksiCbList'); let n = 0; l.querySelectorAll('.aksiCbO').forEach(o => { const ok = !q || inp.dataset.typed !== '1' || o.dataset.lbl.toLowerCase().includes(q); o.hidden = !ok; if (ok) n++; }); l.querySelector('.aksiCbNone').hidden = n > 0; };
    const openCb = inp => { const l = inp.parentElement.querySelector('.aksiCbList'); closeAll(l); inp.dataset.typed = '0'; filt(inp); l.hidden = false; };
    root.addEventListener('focusin', ev => { const i = ev.target; if (i.classList && i.classList.contains('aksiCbIn')) { openCb(i); i.select(); } });
    root.addEventListener('input', ev => { const i = ev.target; if (i.classList && i.classList.contains('aksiCbIn')) { i.dataset.typed = '1'; i.parentElement.querySelector('.aksiCbList').hidden = false; filt(i); } });
    root.addEventListener('focusout', ev => { const i = ev.target; if (i.classList && i.classList.contains('aksiCbIn')) setTimeout(() => { i.value = i.dataset.lbl || ''; const l = i.parentElement && i.parentElement.querySelector('.aksiCbList'); if (l) l.hidden = true; }, 150); });
    root.addEventListener('mousedown', ev => {
      const o = ev.target.closest('.aksiCbO'); if (o) {
        ev.preventDefault(); const cbx = o.closest('.aksiCb'), i = cbx.querySelector('.aksiCbIn');
        i.dataset.nik = o.dataset.nik; i.dataset.lbl = o.dataset.lbl; i.value = o.dataset.lbl; cbx.querySelector('.aksiCbList').hidden = true;
        return;
      }
      const arr = ev.target.closest('.aksiCbArr'); if (arr) { ev.preventDefault(); const i = arr.parentElement.querySelector('.aksiCbIn'); i.focus(); }
    });
    root.addEventListener('keydown', ev => { if (ev.key === 'Escape') closeAll(null); });
    root.addEventListener('click', ev => {
      const sg = ev.target.closest('[data-seg]'); if (sg && root.contains(sg)) { root.dataset.sec = sg.dataset.seg; try { root.scrollIntoView({ block: 'start' }); } catch (e) {} return; }
      const tg = ev.target.closest('[data-tg]'); if (tg && root.contains(tg)) { tg.parentElement.classList.toggle('open'); return; }
      const ma = ev.target.closest('button[data-a="more"]'); if (ma) { ma.closest('.aksiFold').classList.toggle('showAll'); ma.textContent = ma.closest('.aksiFold').classList.contains('showAll') ? 'Ringkas' : 'Lihat dealer lainnya'; return; }
      const ot = ev.target.closest('[data-a="openteam"],[data-a="closeteam"]'); if (ot && root.contains(ot)) {
        const w = root.querySelector('.aksiTeamWrap'), dm = root.querySelector('.aksiDimT'), on = ot.dataset.a === 'openteam';
        if (w) w.classList.toggle('open', on); if (dm) dm.classList.toggle('open', on); if (!on) closeAll(null);
        if (on) { const i = root.querySelector('.aksiView .aksiCbIn'); if (i) setTimeout(() => i.focus(), 50); }
        return;
      }
      const dt = ev.target.closest('[data-a="doteam"]'); if (dt && root.contains(dt)) {
        const i = root.querySelector('.aksiView .aksiCbIn'); const nk = i && i.dataset.nik;
        if (!nk) { const m = 'Pilih anggota tim dari daftar dulu.'; if (typeof toast === 'function') toast(m); else alert(m); return; }
        CM.view = nk; const h = root.querySelector('[data-aksi-commit]'); if (h) h.innerHTML = commitHtml(); return;
      }
      const oa = ev.target.closest('[data-a="openadd"],[data-a="closeadd"]'); if (oa && root.contains(oa)) { const w = root.querySelector('.aksiAddWrap'), dm = root.querySelector('.aksiDim'), on = oa.dataset.a === 'openadd'; if (w) w.classList.toggle('open', on); if (dm) dm.classList.toggle('open', on); if (on) { const t = root.querySelector('.aksiTxt'); if (t) setTimeout(() => t.focus(), 50); } return; }
      const b = ev.target.closest('button[data-a]'); if (!b || !root.contains(b)) return;
      const box = b.closest('[data-aksi-commit]'); if (!box) return;
      const it = b.closest('.aksiCm'), id = it ? +it.dataset.id : 0, a = b.dataset.a, note = it && it.querySelector('.aksiNoteIn') ? it.querySelector('.aksiNoteIn').value : null;
      let p;
      if (a === 'add') {
        const t = box.querySelector('.aksiTxt'), o = box.querySelector('.aksiOwn .aksiCbIn'); if (!t || !t.value.trim()) return;
        const owner = o && o.dataset.nik && o.dataset.nik !== CM.me ? o.dataset.nik : '';
        p = rpc('apiCommitSave', TOKEN, { text: t.value.trim(), owner });
      }
      else if (a === 'st') p = rpc('apiCommitStatus', TOKEN, id, b.dataset.s, note == null ? (CM.rows.find(r => r.id === id) || {}).note || '' : note);
      else if (a === 'cm') { const ci = it.querySelector('.aksiCIn'); if (!ci || !ci.value.trim()) return; p = rpc('apiCommitComment', TOKEN, id, ci.value.trim()); }
      else if (a === 'del') p = rpc('apiCommitDelete', TOKEN, id);
      else if (a === 'carry') { const r = CM.rows.find(x => x.id === id); if (!r) return; p = rpc('apiCommitSave', TOKEN, { text: r.text, owner: r.owner }); }
      else return;
      b.disabled = true;
      p.then(() => { root.querySelectorAll('.aksiAddWrap,.aksiDim').forEach(e => e.classList.remove('open')); commitLoad(root); }).catch(e => { b.disabled = false; if (typeof toast === 'function') toast(e.message || 'Gagal'); else alert(e.message || 'Gagal'); });
    });
  }

  function renderTab(d) {
    const roleName = { SALES: 'Sales', ASM: 'ASM', RGM: 'RGM', TOP: 'Nasional' }[d.level] || 'Nasional';
    const g = mainGroup(d);
    const hd = `<div class="aksiHd"><h2>Aksi — ${E(roleName)}</h2><small>Data s.d. ${E(typeof tgl === 'function' ? tgl(d.cutoff) : d.cutoff)} · time gone ${P(d.run / d.days * 100)}</small></div>`;
    if (!g) return hd + '<div class="aksiBox"><div class="aksiEmpty">Belum ada data untuk cakupan ini.</div></div>';
    const adm = adminBlock(d), nAct = actions(d, g).length;
    const seg = `<div class="aksiSeg"><span data-seg="p">PRIORITAS${nAct ? `<i>${nAct}</i>` : ''}</span><span data-seg="k">KOMITMEN</span><span data-seg="d">DEALER</span>${adm ? '<span data-seg="a">DATA</span>' : ''}</div>`;
    return hd + seg + '<div class="aksiSec" data-sec="p">' + dailyBlock(d, g) + actionBlock(d, g) + '</div><div class="aksiSec aksiCmWrap" data-sec="k" data-aksi-commit>' + commitHtml() + '</div><div class="aksiSec" data-sec="d">' + dealerBlock(d, g) + '</div>' + (adm ? '<div class="aksiSec" data-sec="a">' + adm + '</div>' : '');
  }

  function hideOldSummary(wrap) {
    const secs = [...wrap.querySelectorAll(':scope > .v6xsec')].filter(e => e.textContent.trim().toUpperCase() === 'RINGKASAN SELLTHRU');
    let last = null;
    secs.forEach(sec => { sec.classList.add('aksiOldHide'); const bx = sec.nextElementSibling; if (bx && bx.querySelector('.v6xsummary')) { bx.classList.add('aksiOldHide'); last = bx; } else last = sec; });
    return last;
  }

  window.dsmAksiMount = function (wrap) {
    const x = X(); if (!wrap || !x || !x.data || !x.data.nodes) return;
    css();
    const d = x.data, sig = [d.period, d.cutoff, d.level, d.me].join('|');
    /* tab AKSI */
    let tab = wrap.querySelector(':scope > .v6xaksiTab');
    if (!tab) { tab = document.createElement('div'); tab.className = 'v6xaksi v6xaksiTab'; tab.dataset.v7tab = 'a'; wrap.appendChild(tab); }
    if (tab.dataset.sig !== sig) { try { tab.innerHTML = renderTab(d); tab.dataset.sig = sig; if (!tab.dataset.sec) tab.dataset.sec = 'p'; commitBind(tab); commitLoad(tab); } catch (e) { tab.innerHTML = '<div class="aksiBox"><div class="aksiEmpty">Tab AKSI belum bisa ditampilkan: ' + E(e.message) + '</div></div>'; } }
    /* ringkasan SELLTHRU di tab RINGKASAN (menggantikan panel lama yang disembunyikan) */
    const old = hideOldSummary(wrap);
    let main = wrap.querySelector(':scope > .v6xaksiMain');
    if (!main) {
      main = document.createElement('div'); main.className = 'v6xaksi v6xaksiMain'; main.dataset.v7tab = 'r';
      const anchor = old || wrap.querySelector(':scope > .v6xhero') || wrap.querySelector(':scope > .v7btabs');
      if (anchor) anchor.after(main); else wrap.prepend(main);
    }
    if (main.dataset.sig !== sig) { try { main.innerHTML = renderMain(d); main.dataset.sig = sig; } catch (e) { main.innerHTML = '<div class="aksiBox"><div class="aksiEmpty">Ringkasan belum bisa ditampilkan: ' + E(e.message) + '</div></div>'; wrap.querySelectorAll(':scope > .aksiOldHide').forEach(e => e.classList.remove('aksiOldHide')); } }
  };
})();
