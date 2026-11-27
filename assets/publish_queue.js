/* ANTREAN PUBLIKASI (Tahap 3, 2026-11-18) — mockup doc/mockup/2026-11-18/dsm-publikasi-data.html
 * - Banner status (biru proses / kuning ditahan / merah gagal) untuk Admin & Super Admin, di atas halaman.
 * - Tombol PUBLIKASIKAN di bar atas (desktop) / header (HP), KHUSUS Super Admin, muncul bila ada antrean.
 * - Panel Admin › Data Harian › Publikasi Data.
 * Server: lib/publish_queue.php. */
(function () {
  let PQ = null, timer = null, pop = false, busy = false;
  const G = () => { try { return { B: typeof BOOT !== 'undefined' ? BOOT : null, T: typeof TOKEN !== 'undefined' ? TOKEN : '' }; } catch (x) { return { B: null, T: '' }; } };
  const role = () => (G().B?.user?.role || '');
  const isAdm = () => ['ADMIN', 'SUPER ADMIN'].includes(role());
  const isSA = () => role() === 'SUPER ADMIN';
  const e = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const jam = t => t ? new Date(t).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') : '—';
  const dur = ms => { if (ms == null) return '—'; const s = Math.round(ms / 1000); return s < 60 ? s + ' dtk' : Math.floor(s / 60) + ' mnt ' + (s % 60) + ' dtk'; };
  const pct = () => { if (!PQ || PQ.status !== 'run') return 0; const el = Date.now() - (PQ.started || Date.now()); return Math.max(3, Math.min(95, Math.round(el / (PQ.avgMs || 120000) * 100))); };
  const ICON = { master: '🗂️', import: '📤' };

  async function load() {
    if (!isAdm() || typeof rpc !== 'function' || !G().T) return;
    try { PQ = await rpc('apiPublishStatus', G().T); } catch (x) { return; }
    draw(); schedule();
  }
  function schedule() { clearTimeout(timer); timer = setTimeout(load, PQ && (PQ.status === 'run' || PQ.queue.length) ? 6000 : 30000); }
  window.pqRefresh = load;

  function banner() {
    if (!PQ) return '';
    if (PQ.status === 'run') return `<div class="pqBan run"><span class="pqSp"></span><div>Data sedang diperbarui (mulai ${jam(PQ.started)}${PQ.queue.length ? ' · ' + PQ.queue.length + ' perubahan' : ''}). Data lama tetap tampil sampai selesai.</div></div>`;
    if (PQ.hold && PQ.queue.length) return `<div class="pqBan hold">⏸<div><b>Publikasi ditahan</b> oleh ${e(PQ.hold.by)} sejak ${jam(PQ.hold.at)} · ${PQ.queue.length} perubahan menunggu. Sales masih melihat data lama.</div></div>`;
    if (PQ.status === 'err') return `<div class="pqBan err">⚠<div><b>Publikasi gagal</b> ${jam(PQ.errAt)} · data lama tetap aktif. Buka Admin › Publikasi Data untuk detail.</div></div>`;
    return '';
  }
  function topBtn(hp) {
    if (!PQ || !isSA()) return '';
    if (PQ.status === 'run') return `<button type="button" class="pqTop run" data-pqpop><span class="pqSp"></span>${hp ? '' : 'MEMPERBARUI DATA…'}<em>${pct()}%</em></button>`;
    if (!PQ.queue.length && PQ.status !== 'err') return '';
    const cls = PQ.status === 'err' ? 'err' : PQ.hold ? 'hold' : '';
    const lab = PQ.status === 'err' ? 'PUBLIKASI GAGAL' : PQ.hold ? 'DITAHAN' : 'PUBLIKASIKAN';
    return `<button type="button" class="pqTop ${cls}" data-pqpop>${hp ? (PQ.status === 'err' ? '⚠' : '⇪') : '⇪ ' + lab}<em>${PQ.status === 'err' ? 'ULANG' : PQ.queue.length}</em></button>`;
  }
  function popHtml() {
    if (!pop || !PQ || !isSA()) return '';
    const t = PQ.status === 'err' ? 'Publikasi gagal ' + jam(PQ.errAt) : PQ.status === 'run' ? 'Sedang diproses · ' + pct() + '%' : PQ.queue.length + ' perubahan menunggu';
    const s = PQ.hold ? 'Ditahan sejak ' + jam(PQ.hold.at) : PQ.status === 'run' ? e(PQ.label) : 'Diproses otomatis ≤1 menit';
    return `<div class="pqPop" id="pqPop"><b>${t}</b><small>${s}</small>${PQ.queue.slice(0, 6).map(q => `<div>${ICON[q.kind] || '•'} ${e(q.label)} <span>· ${e(q.nama)} ${jam(q.at)}</span></div>`).join('')}${PQ.queue.length > 6 ? `<div><span>+${PQ.queue.length - 6} lainnya</span></div>` : ''}
      <button type="button" class="pqBtn" data-pqnow ${PQ.status === 'run' || !PQ.queue.length ? 'disabled' : ''}>${PQ.status === 'run' ? 'SEDANG DIPROSES…' : 'PUBLIKASIKAN SEKARANG'}</button><a href="#" data-pqopen>Buka Publikasi Data ›</a></div>`;
  }
  function place() {
    const desk = window.matchMedia('(min-width:821px)').matches;
    // banner: tepat sebelum #view supaya tidak hilang saat halaman dirender ulang
    const v = document.getElementById('view');
    let b = document.getElementById('pqBanWrap');
    if (v) { if (!b) { b = document.createElement('div'); b.id = 'pqBanWrap'; } if (b.nextSibling !== v) v.parentNode.insertBefore(b, v); b.innerHTML = banner(); }
    // tombol atas (Super Admin)
    let t = document.getElementById('pqTopWrap');
    const host = desk ? document.getElementById('tnWarnWrap') : document.querySelector('#app .topbar');
    if (!host) { if (t) t.remove(); return; }
    if (!t) { t = document.createElement('div'); t.id = 'pqTopWrap'; }
    if (desk) { if (t.nextSibling !== host) host.parentNode.insertBefore(t, host); } else if (t.parentNode !== host) host.appendChild(t);
    t.className = desk ? 'pqTW' : 'pqTW hp';
    t.innerHTML = topBtn(!desk) + popHtml();
  }
  function draw() { try { place(); adm(); } catch (x) { } }

  /* ---------- Panel Admin ---------- */
  window.pqAdminPanel = function (v) {
    if (!isAdm()) return;
    v.insertAdjacentHTML('beforeend', panel('Publikasi Data', '<div id="pqAdm"><p class="hint v6load">Memuat…</p></div>'));
    load();
  };
  function adm() {
    const box = document.getElementById('pqAdm'); if (!box || !PQ) return;
    const run = PQ.status === 'run', pend = PQ.queue.length, sa = isSA();
    const hist = (PQ.hist || []).map(h => `<div class="pqHi"><i style="background:${h.ok ? '#12866A' : '#B4332E'}"></i><div>${e(h.label)}${h.ok ? '' : ' <b style="color:#B4332E">(gagal, data lama tetap aktif)</b>'}<small>${e(h.by || '')} · ${jam(h.at)}${h.err ? ' · ' + e(h.err) : ''}</small></div><em>${dur(h.ms)}</em></div>`).join('') || '<div class="pqEmpty">Belum ada riwayat.</div>';
    box.innerHTML = `<div class="pqGrid"><div>
      <div class="pqCard"><div class="pqH">STATUS DATA <small>diperbarui otomatis</small></div><div class="pqSt">
        <div><small>DATA AKTIF</small><b class="${PQ.status === 'err' || pend ? '' : 'ok'}">${pend ? 'Menunggu publikasi' : '✓ Terbaru'}</b><em>dipublikasi ${jam(PQ.last)}</em></div>
        <div><small>ANTREAN</small><b>${pend ? pend + ' perubahan' : 'Kosong'}</b><em>${PQ.hold ? 'ditahan' : run ? 'sedang diproses' : pend ? 'diproses ≤1 menit' : '—'}</em></div>
        <div><small>PUBLIKASI TERAKHIR</small><b>${dur(PQ.lastMs)}</b><em>${e(PQ.lastLabel || '—')}</em></div>
        <div><small>MODE</small><b style="color:${PQ.hold ? '#8A5A00' : '#12866A'}">${!PQ.on ? 'Langsung (antrean mati)' : PQ.hold ? '⏸ Ditahan' : 'Otomatis'}</b><em>${PQ.hold ? 'sejak ' + jam(PQ.hold.at) + ' · lepas otomatis ' + jam(PQ.hold.until) : 'cron tiap menit'}</em></div></div>
        ${run ? `<div class="pqProg"><div class="bar"><i style="width:${pct()}%"></i></div><div class="pqStep"><span>${e(PQ.label)}</span><span>${pct()}% · perkiraan ${dur(PQ.avgMs)}</span></div></div>` : ''}
        ${PQ.status === 'err' ? `<div class="pqWarn"><b>Gagal:</b> ${e(PQ.err)}. Perubahan masih di antrean, dicoba lagi otomatis ${jam(PQ.retryAt)}.${sa ? ' Bisa juga tekan PUBLIKASIKAN SEKARANG.' : ''}</div>` : ''}
        ${sa ? `<button type="button" class="pqBtn" data-pqnow ${pend && !run ? '' : 'disabled'}>${run ? 'SEDANG DIPROSES…' : 'PUBLIKASIKAN SEKARANG' + (pend ? ' (' + pend + ')' : '')}</button>` : '<p class="hint">Antrean diproses otomatis. Tombol publikasi manual khusus Super Admin.</p>'}</div>
      ${sa ? `<div class="pqCard"><div class="pqH">TAHAN PUBLIKASI <span class="pqSA">SUPER ADMIN</span></div><div class="pqHold"><div><b>Tahan publikasi otomatis</b><small>Nyalakan saat mau edit banyak sekaligus (transfer, region, dealer). Matikan = semua perubahan dipublikasi sekali jalan.</small></div><button type="button" class="pqSw${PQ.hold ? ' on' : ''}" data-pqhold aria-label="Tahan publikasi"></button></div>${PQ.hold ? '<div class="pqWarn">Selama ditahan, sales &amp; atasan masih melihat data lama. Ditahan lebih dari <b>2 jam</b> → otomatis dilepas supaya tidak lupa.</div>' : ''}</div>` : ''}
      </div><div>
      <div class="pqCard"><div class="pqH">PERUBAHAN MENUNGGU <small>${pend > 1 ? 'digabung jadi 1 publikasi' : ''}</small></div>${pend ? PQ.queue.map(q => `<div class="pqQ"><div class="ic">${ICON[q.kind] || '•'}</div><div><b>${e(q.label)}</b><small>${e(q.nama)} · ${jam(q.at)}</small></div><span>${q.kind === 'import' ? 'UPLOAD' : 'BERAT'}</span></div>`).join('') : '<div class="pqEmpty">Tidak ada perubahan yang menunggu.<br>Simpan ringan (KPI, target, kontak, PIN) langsung tampil tanpa antre.</div>'}</div>
      <div class="pqCard"><div class="pqH">RIWAYAT PUBLIKASI <small>10 terakhir</small></div>${hist}</div></div></div>`;
  }

  document.addEventListener('click', async ev => {
    const t = ev.target;
    if (t.closest('[data-pqpop]')) { pop = !pop; draw(); return; }
    if (t.closest('[data-pqopen]')) { ev.preventDefault(); pop = false; draw(); try { const b = document.querySelector('#navigation button[data-page="admin"]'); if (b) b.click(); let n = 0; const tryOpen = () => { const p = [...document.querySelectorAll('details.adminFeature')].find(d => /publikasi data/i.test(d.dataset.feature || '')); const w = document.querySelector('.dsmAdm8'); if (p && w && w._dsmGoPanel) { w._dsmGoPanel(p); setTimeout(() => p.scrollIntoView({ behavior: 'smooth' }), 150); } else if (p) { p.open = true; p.scrollIntoView({ behavior: 'smooth' }); } else if (n++ < 12) setTimeout(tryOpen, 400); }; setTimeout(tryOpen, 500); } catch (x) { } return; }
    if (t.closest('[data-pqnow]')) {
      if (busy) return; if (!await dsmConfirm('Publikasikan semua perubahan sekarang? Data aktif akan dihitung ulang di belakang layar.')) return;
      busy = true; try { PQ = await rpc('apiPublishNow', G().T); pop = false; draw(); schedule(); } catch (x) { (typeof fail === 'function' ? fail : alert)(x); } finally { busy = false; } return;
    }
    if (t.closest('[data-pqhold]')) {
      if (busy) return; const on = !PQ?.hold; if (!on && PQ.queue.length && !await dsmConfirm('Lepas tahan? ' + PQ.queue.length + ' perubahan akan langsung dipublikasi.')) return;
      busy = true; try { PQ = await rpc('apiPublishHold', G().T, on); draw(); schedule(); } catch (x) { (typeof fail === 'function' ? fail : alert)(x); } finally { busy = false; } return;
    }
    if (pop && !t.closest('#pqTopWrap')) { pop = false; draw(); }
  });
  // mulai setelah aplikasi siap (BOOT terisi), lalu pasang ulang tiap ada render
  let tries = 0;
  (function wait() { if (G().B?.user) { load(); setInterval(() => { if (PQ) place(); }, 2000); } else if (tries++ < 120) setTimeout(wait, 1000); })();
})();
