/* DSM Klaim - popup tanda tangan. Dipanggil otomatis oleh api() saat sebuah aksi butuh TTD tetapi pengguna belum punya. */
(function () {
  const W = 600, H = 220;
  function crop(cv, ink) {
    if (!ink) return null; const g = cv.getContext('2d'), d = g.getImageData(0, 0, W, H).data; let x0 = W, y0 = H, x1 = 0, y1 = 0;
    for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) if (d[(y * W + x) * 4 + 3] > 20) { if (x < x0) x0 = x; if (x > x1) x1 = x; if (y < y0) y0 = y; if (y > y1) y1 = y; }
    const m = 8; x0 = Math.max(0, x0 - m); y0 = Math.max(0, y0 - m); x1 = Math.min(W, x1 + m); y1 = Math.min(H, y1 + m);
    const o = document.createElement('canvas'); o.width = x1 - x0; o.height = y1 - y0; o.getContext('2d').drawImage(cv, x0, y0, o.width, o.height, 0, 0, o.width, o.height); return o.toDataURL('image/png');
  }
  function photo(f, cb) {
    const im = new Image();
    im.onload = () => {
      const s = Math.min(1, 520 / im.width, 220 / im.height), w = Math.max(1, Math.round(im.width * s)), h = Math.max(1, Math.round(im.height * s));
      const cv = document.createElement('canvas'); cv.width = w; cv.height = h; const g = cv.getContext('2d'); g.drawImage(im, 0, 0, w, h);
      const id = g.getImageData(0, 0, w, h), d = id.data;
      for (let i = 0; i < d.length; i += 4) { const lum = .299 * d[i] + .587 * d[i + 1] + .114 * d[i + 2]; const a = Math.max(0, Math.min(255, (225 - lum) * 4)); d[i] = 30; d[i + 1] = 63; d[i + 2] = 138; d[i + 3] = a; }
      g.putImageData(id, 0, 0); cb(cv.toDataURL('image/png'));
    };
    im.src = URL.createObjectURL(f);
  }
  function padBind(cv, st) {
    const g = cv.getContext('2d'); g.lineWidth = 3.4; g.lineCap = g.lineJoin = 'round'; g.strokeStyle = '#1E3F8A'; let down = false; st.ink = false;
    const pt = e => { const r = cv.getBoundingClientRect(); return [(e.clientX - r.left) * W / r.width, (e.clientY - r.top) * H / r.height]; };
    cv.onpointerdown = e => { e.preventDefault(); cv.setPointerCapture(e.pointerId); down = true; st.ink = true; const [x, y] = pt(e); g.beginPath(); g.moveTo(x, y); g.lineTo(x + .1, y + .1); g.stroke(); };
    cv.onpointermove = e => { if (!down) return; e.preventDefault(); const [x, y] = pt(e); g.lineTo(x, y); g.stroke(); };
    cv.onpointerup = cv.onpointercancel = () => { down = false; };
    return () => { g.clearRect(0, 0, W, H); st.ink = false; };
  }
  /** Popup tanda tangan. Selesai = tersimpan di profil (resolve), dibatalkan = reject. */
  window.askSign = function () {
    return new Promise((ok, no) => {
      let mode = 'draw', draft = null, clr = null, finished = false; const st = { ink: false };
      const bg = sheet('<div id="sgbox"></div>');
      const fin = e => { if (finished) return; finished = true; bg.remove(); e ? no(e) : ok(); };
      bg.addEventListener('click', e => { if (e.target === bg) fin(new Error('Tanda tangan dibatalkan.')); });
      const box = $('#sgbox', bg);
      const paint = () => {
        box.innerHTML = `<h3>Tanda tangan Anda</h3><div class="note">Anda belum punya tanda tangan tersimpan. Isi sekali di sini, tersimpan di profil dan dipakai otomatis untuk BA berikutnya.</div>
         <div class="two" style="margin-bottom:10px"><span class="chip${mode === 'draw' ? ' on' : ''}" data-sm="draw" style="text-align:center;padding:11px">✍ Gambar di layar</span><span class="chip${mode === 'photo' ? ' on' : ''}" data-sm="photo" style="text-align:center;padding:11px">📷 Unggah foto</span></div>
         ${mode === 'draw' ? `<canvas id="sgpad" width="${W}" height="${H}" style="width:100%;height:auto;aspect-ratio:${W}/${H};border:2px dashed #9DB6CC;border-radius:14px;background:#FAFCFE;touch-action:none;display:block"></canvas><div class="mut" style="margin-top:6px">Tanda tangan dengan jari atau stylus di dalam kotak.</div>`
         : `<input type="file" id="sgf" accept="image/*" style="margin:6px 0"><div id="sgpv" style="text-align:center;background:#FAFCFE;border:1px solid #D3DEE8;border-radius:14px;min-height:80px;padding:6px">${draft ? `<img src="${draft}" style="max-width:100%;max-height:110px">` : '<span class="mut">Belum ada foto dipilih</span>'}</div>`}
         <div class="two" style="margin-top:12px"><button class="btn g" id="sgclr">HAPUS</button><button class="btn" id="sgok">SIMPAN &amp; LANJUTKAN</button></div><button class="btn g sm" id="sgno" style="margin-top:8px">Batal</button>`;
        $$('[data-sm]', box).forEach(o => o.onclick = () => { mode = o.dataset.sm; draft = null; paint(); });
        if (mode === 'draw') clr = padBind($('#sgpad', box), st);
        else $('#sgf', box).onchange = e => { const f = e.target.files[0]; if (f) photo(f, d => { draft = d; $('#sgpv', box).innerHTML = `<img src="${d}" style="max-width:100%;max-height:110px">`; }); };
        $('#sgclr', box).onclick = () => { if (mode === 'draw') clr(); else { draft = null; paint(); } };
        $('#sgno', box).onclick = () => fin(new Error('Tanda tangan dibatalkan.'));
        $('#sgok', box).onclick = () => guard(async () => {
          const img = mode === 'draw' ? crop($('#sgpad', box), st.ink) : draft;
          if (!img) throw new Error(mode === 'draw' ? 'Gambar tanda tangan dulu di dalam kotak.' : 'Pilih foto tanda tangan dulu.');
          await api('sign_save', { img }); if (S.me) S.me.has_sign = true; toast('Tanda tangan tersimpan.'); fin();
        });
      };
      paint();
    });
  };
})();
