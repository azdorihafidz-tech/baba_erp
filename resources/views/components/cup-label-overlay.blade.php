{{--
    4.44 Poin 2 — overlay cetak Label Produk (stiker), porting erp-coffeeshop
    cup-label-overlay. 4.49 Tahap D: diperbarui ke versi coffeeshop 38bcfde —
    label dicetak sebagai GAMBAR (BITMAP TSPL), atur posisi per perangkat +
    geser per cabang, label uji, catatan pesanan, tanggal; baris Exp (Frozen)
    khusus Dimsum tetap ada. Dipasang sekali di layouts/app. Dipanggil lewat JS:
    await window.openCupLabel(url) (url = route label-cup.order, respons JSON).
    Kalau tidak ada label (kategori tidak dicentang / fitur nonaktif di cabang)
    overlay tidak muncul.

    Overlay sengaja bukan Bootstrap modal supaya bisa tampil di atas modal
    struk tanpa masalah tumpukan modal. Printer label Bluetooth disimpan
    terpisah dari printer struk (localStorage 'btLastLabelPrinterName'),
    jadi tablet bisa memakai 2 printer berbeda.
--}}
<div id="cupLabelOverlay" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;padding:16px">
    <div class="card shadow" style="width:100%;max-width:440px;max-height:90vh;display:flex;flex-direction:column">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-tag me-1"></i>Label Produk — <span id="cupLabelCount">0</span> stiker</span>
            <x-panduan-button slug="label-produk" label="" />
        </div>
        <div class="card-body" style="overflow:auto">
            <div id="cupLabelPreview" class="d-flex flex-column gap-2 mb-3"></div>

            {{-- Aplikasi kasir Android: cetak langsung ke printer label yang diatur di aplikasi --}}
            <div class="d-grid gap-2" id="cupLabelApkControls" style="display:none">
                <button type="button" class="btn btn-primary btn-lg" id="cupLabelBtnApk" onclick="window.cupLabelCetakApk()">🖨️ Cetak Label</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.KDApk && window.KDApk.bukaPengaturan()">⚙️ Pengaturan Printer Aplikasi</button>
            </div>

            <div class="d-grid gap-2" id="cupLabelWebControls">
                <button type="button" class="btn btn-primary" onclick="window.cupLabelCetakBrowser()">🖨️ Cetak Browser</button>
                <button type="button" class="btn btn-outline-primary" id="cupLabelBtnLast" style="display:none" onclick="window.cupLabelCetakBluetooth(true)"></button>
                <div class="input-group">
                    <select id="cupLabelProtocol" class="form-select" title="Bahasa printer label Bluetooth">
                        <option value="tspl">Printer label TSPL (mis. Blueprint ECO80BT) — disarankan</option>
                        <option value="escpos">Printer struk / ESC/POS</option>
                    </select>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.cupLabelCetakBluetooth(false)">📶 Pilih Printer Bluetooth</button>
                </div>
            </div>
            <details class="mt-3 border rounded px-2 py-1" id="cupLabelPosisi">
                <summary class="small fw-semibold">⚙️ Atur posisi label (perangkat ini) <x-tooltip key="label_cup.posisi" /></summary>
                <div class="row g-2 mt-1 small">
                    <div class="col-12 text-muted" id="cupLabelGeserCabang"></div>
                    <div class="col-6"><label class="form-label mb-0">Tambahan geser kiri/kanan (mm)</label>
                        <input type="number" step="0.5" min="-10" max="10" class="form-control form-control-sm" id="cupLabelGeserX" onchange="window.cupLabelSimpanPosisi()">
                        <div class="text-muted">+ ke kanan, − ke kiri</div></div>
                    <div class="col-6"><label class="form-label mb-0">Tambahan geser atas/bawah (mm)</label>
                        <input type="number" step="0.5" min="-10" max="10" class="form-control form-control-sm" id="cupLabelGeserY" onchange="window.cupLabelSimpanPosisi()">
                        <div class="text-muted">+ ke bawah, − ke atas</div></div>
                    <div class="col-12"><label class="form-label mb-0">Cara cetak</label>
                        <select class="form-select form-select-sm" id="cupLabelMode" onchange="window.cupLabelSimpanPosisi()">
                            <option value="gambar">Gambar — rapi, ukuran huruf pasti (disarankan)</option>
                            <option value="teks">Teks printer — huruf bawaan printer</option>
                        </select></div>
                    <div class="col-12"><div class="form-check">
                        <input class="form-check-input" type="checkbox" id="cupLabelBalik" onchange="window.cupLabelSimpanPosisi()">
                        <label class="form-check-label" for="cupLabelBalik">Balik hitam-putih (centang hanya kalau stiker tercetak hitam semua)</label></div></div>
                    <div class="col-12"><div class="form-check">
                        <input class="form-check-input" type="checkbox" id="cupLabelPutar" onchange="window.cupLabelSimpanPosisi()">
                        <label class="form-check-label" for="cupLabelPutar">Putar tulisan 180°</label></div></div>
                    <div class="col-12 d-grid"><button type="button" class="btn btn-sm btn-outline-dark" onclick="window.cupLabelUji()">🧪 Cetak Label Uji (bingkai tepi stiker)</button></div>
                    <div class="col-12 text-muted">Cetak label uji: bingkai tebal harus pas di tepi stiker. Kalau terpotong di kiri, isi Geser kiri/kanan dengan angka plus (mis. 2), lalu cetak uji lagi. Hanya untuk printer label (TSPL).</div>
                </div>
            </details>
            <div class="small mt-2" id="cupLabelStatus" style="white-space:pre-line"></div>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <button type="button" class="btn btn-sm btn-outline-danger" id="cupLabelBtnForget" style="display:none" onclick="window.cupLabelLupakanPrinter()">Lupakan printer label</button>
            <button type="button" class="btn btn-success ms-auto" onclick="window.cupLabelTutup()">Selesai</button>
        </div>
    </div>
</div>

<script>
(function () {
    var KEY_PRINTER  = 'btLastLabelPrinterName';
    var KEY_PROTOCOL = 'labelCupProtocol';
    var BT_SERVICE_UUIDS = [
        '0000ae30-0000-1000-8000-00805f9b34fb', '0000ae3a-0000-1000-8000-00805f9b34fb',
        '000018f0-0000-1000-8000-00805f9b34fb', '49535343-fe7d-4ae5-8fa9-9fafd205e455',
        '0000ff00-0000-1000-8000-00805f9b34fb', '0000fee7-0000-1000-8000-00805f9b34fb',
        'e7810a71-73ae-499d-8c15-faa9aef0c3f2', '6e400001-b5a3-f393-e0a9-e50e24dcca9e',
    ];
    var state = null, resolver = null;

    function el(id) { return document.getElementById(id); }
    function ls(key, val) {
        try { if (val === undefined) return localStorage.getItem(key); if (val === null) localStorage.removeItem(key); else localStorage.setItem(key, val); } catch (e) { return null; }
    }
    function status(msg, cls) { var s = el('cupLabelStatus'); s.textContent = msg || ''; s.className = 'small mt-2 text-' + (cls || 'muted'); }
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    // Printer thermal tidak paham UTF-8: buang aksen & simbol non-ASCII
    function ascii(s) { return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/·/g, '-').replace(/[^\x20-\x7E]/g, ''); }

    function refreshPrinterUi() {
        var saved = ls(KEY_PRINTER);
        var last = el('cupLabelBtnLast');
        last.style.display = saved ? '' : 'none';
        last.textContent = saved ? '🔁 Cetak ke "' + saved + '"' : '';
        el('cupLabelBtnForget').style.display = saved ? '' : 'none';
    }

    function renderPreview(labels) {
        el('cupLabelCount').textContent = labels.length;
        if (modeGambar() && state && state.ukuran) {
            // Mode gambar: contoh di layar = persis yang dikirim ke printer
            el('cupLabelPreview').innerHTML = '';
            labels.forEach(function (l) {
                var img = new Image();
                img.src = gambarLabel(l, state.ukuran.lebar, state.ukuran.tinggi, 0, 0).toDataURL();
                img.alt = 'Label ' + l.ke; img.className = 'border rounded bg-white';
                img.style.cssText = 'width:100%;max-width:320px;align-self:center';
                el('cupLabelPreview').appendChild(img);
            });
            return;
        }
        el('cupLabelPreview').innerHTML = labels.map(function (l) {
            return '<div class="border rounded p-2 small" style="font-family:Arial,sans-serif">'
                + '<div class="d-flex justify-content-between fw-bold border-bottom pb-1"><span>' + esc(l.toko.toUpperCase()) + '</span><span>#' + esc(l.nomor) + ' · ' + l.ke + '/' + l.dari + '</span></div>'
                + '<div class="fw-bold mt-1">' + esc(l.nama) + '</div>'
                + (l.opsi && l.opsi.length ? '<div>' + esc(l.opsi.join(' · ')) + '</div>' : '')
                + (l.catatan ? '<div class="fst-italic">' + esc(l.catatan) + '</div>' : '')
                + (l.catatan_order ? '<div class="fst-italic">' + esc(l.catatan_order) + '</div>' : '')
                + (l.pelanggan ? '<div>a.n. ' + esc(l.pelanggan) + '</div>' : '')
                + (l.exp ? '<div class="fw-bold">Exp: ' + esc(l.exp) + '</div>' : '')
                + '<div class="text-muted">' + esc(l.info) + '</div></div>';
        }).join('');
    }

    // ---- Buka overlay. Resolve true kalau overlay tampil, false kalau tidak ada label ----
    // opsi.otomatis = dipanggil otomatis (setelah struk) — di aplikasi kasir dengan "cetak label otomatis" aktif, label
    // langsung dicetak tanpa membuka jendela.
    window.openCupLabel = async function (url, opsi) {
        opsi = opsi || {};
        try {
            var res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return false;
            var data = await res.json();
            if (!data.aktif || !data.labels || !data.labels.length) return false;
            state = data;
        } catch (e) { return false; }

        var apk = window.KDApk && window.KDApk.ada();
        if (apk && opsi.otomatis && window.KDApk.info().auto_label) {
            await cetakApk(true);
            return true;
        }

        renderPreview(state.labels);
        el('cupLabelApkControls').style.display = apk ? '' : 'none';
        el('cupLabelWebControls').style.display = apk ? 'none' : '';
        if (!apk) {
            el('cupLabelProtocol').value = ls(KEY_PROTOCOL) || 'tspl';
            refreshPrinterUi();
        } else {
            el('cupLabelBtnForget').style.display = 'none';
        }
        isiPosisiUi();
        status('');
        el('cupLabelOverlay').style.display = 'flex';
        return new Promise(function (resolve) { resolver = resolve; });
    };

    // ---- Aplikasi kasir: kirim ke printer label lewat jembatan Android ----
    async function cetakApk(diam) {
        if (!state) return;
        var proto = (window.KDApk.info().label_protokol === 'escpos') ? 'escpos' : 'tspl';
        var btn = el('cupLabelBtnApk');
        if (btn) btn.disabled = true;
        if (!diam) status('Mengirim ke printer label…', 'muted');
        try {
            var bytes = proto === 'tspl' ? buildTspl() : buildEscpos();
            await window.KDApk.cetak('label', bytes);
            var ok = '✓ ' + state.labels.length + ' label tercetak.';
            diam ? window.KDApk.toast(ok, true) : status(ok, 'success');
        } catch (e) {
            diam ? window.KDApk.toast('Label gagal: ' + e.message, false) : status('Gagal cetak label: ' + e.message, 'danger');
        } finally {
            if (btn) btn.disabled = false;
        }
    }
    window.cupLabelCetakApk = function () { return cetakApk(false); };

    window.cupLabelTutup = function () {
        el('cupLabelOverlay').style.display = 'none';
        if (resolver) { var r = resolver; resolver = null; r(true); }
    };

    window.cupLabelCetakBrowser = function () {
        if (!state) return;
        var u = state.print_url + (state.print_url.indexOf('?') >= 0 ? '&' : '?') + 'autoprint=1';
        var w = window.open(u, '_blank');
        if (!w) status('Popup diblokir browser. Izinkan popup untuk situs ini lalu coba lagi.', 'danger');
        else status('Halaman label dibuka di tab baru — pilih printer label di dialog print.', 'success');
    };

    function isiPosisiUi() {
        var p = posisiPerangkat();
        if (!el('cupLabelGeserX')) return;
        el('cupLabelGeserX').value = p.gx; el('cupLabelGeserY').value = p.gy; el('cupLabelPutar').checked = p.arah === 0;
        var tot = posisi();
        el('cupLabelGeserCabang').textContent = 'Geser dari pengaturan cabang: ' + tot.cx + ' / ' + tot.cy + ' mm (ubah di Cabang → Edit → Label Produk). Kolom di bawah = tambahan khusus perangkat ini, biasanya 0.';
        el('cupLabelMode').value = ls(KEY_MODE) === 'teks' ? 'teks' : 'gambar';
        el('cupLabelBalik').checked = ls(KEY_BALIK) === '1';
    }
    window.cupLabelSimpanPosisi = function () {
        var gx = parseFloat(el('cupLabelGeserX').value), gy = parseFloat(el('cupLabelGeserY').value);
        ls(KEY_GESER_X, isFinite(gx) ? String(Math.max(-10, Math.min(10, gx))) : null);
        ls(KEY_GESER_Y, isFinite(gy) ? String(Math.max(-10, Math.min(10, gy))) : null);
        ls(KEY_PUTAR, el('cupLabelPutar').checked ? '0' : null);
        ls(KEY_MODE, el('cupLabelMode').value === 'teks' ? 'teks' : null);
        ls(KEY_BALIK, el('cupLabelBalik').checked ? '1' : null);
        if (state && state.labels) renderPreview(state.labels);
        isiPosisiUi();
        status('Posisi label disimpan di perangkat ini.', 'muted');
    };
    window.cupLabelUji = async function () {
        window.cupLabelSimpanPosisi();
        if (window.KDApk && window.KDApk.ada()) {
            if (window.KDApk.info().label_protokol === 'escpos') { status('Label uji hanya untuk printer label (TSPL). Ganti bahasa label di Pengaturan Printer Aplikasi.', 'warning'); return; }
            try { await window.KDApk.cetak('label', buildTsplUji()); status('✓ Label uji terkirim — cek bingkainya.', 'success'); }
            catch (e) { status('Gagal cetak label uji: ' + e.message, 'danger'); }
            return;
        }
        return window.cupLabelCetakBluetooth(true, true);
    };

    window.cupLabelLupakanPrinter = function () { ls(KEY_PRINTER, null); refreshPrinterUi(); status('Printer label dihapus.', 'muted'); };

    // ---- Builder data printer ----
    function wrap(text, n) {
        var words = ascii(text).split(/\s+/), lines = [], cur = '';
        words.forEach(function (w) {
            if ((cur + ' ' + w).trim().length > n) { if (cur) lines.push(cur); cur = w.slice(0, n); }
            else cur = (cur + ' ' + w).trim();
        });
        if (cur) lines.push(cur);
        return lines;
    }
    function bodyLines(l) {
        var out = [];
        if (l.opsi && l.opsi.length) out.push(l.opsi.join(' - '));
        if (l.catatan) out.push(l.catatan);
        if (l.catatan_order) out.push(l.catatan_order);
        if (l.pelanggan) out.push('a.n. ' + l.pelanggan);
        if (l.exp) out.push('Exp: ' + l.exp);
        return out;
    }

    // ---- Posisi label per perangkat (printer & gulungan stiker beda-beda) ----
    // 2026-11-06: foto Owner (Blueprint ECO80BT, stiker 40×30): isi bergeser ±3 mm
    // sehingga huruf awal terpotong, dan font "2" printer itu 16 titik (bukan 12)
    // sehingga nomor rata kanan menabrak nama toko. Sekarang: tidak ada teks rata
    // kanan, lebar huruf dihitung longgar, margin kiri 4 mm, plus geser manual.
    var KEY_GESER_X = 'labelCupGeserX', KEY_GESER_Y = 'labelCupGeserY', KEY_PUTAR = 'labelCupPutar';
    // Geser yang diisi DI PERANGKAT INI saja (localStorage)
    function posisiPerangkat() {
        var gx = parseFloat(ls(KEY_GESER_X)), gy = parseFloat(ls(KEY_GESER_Y));
        return {
            gx: isFinite(gx) ? Math.max(-10, Math.min(10, gx)) : 0,
            gy: isFinite(gy) ? Math.max(-10, Math.min(10, gy)) : 0,
            arah: ls(KEY_PUTAR) === '0' ? 0 : 1,
        };
    }
    // Geser total = setelan cabang (Cabang → Edit → Label Produk, berlaku semua perangkat) + perangkat ini
    function posisi() {
        var p = posisiPerangkat(), c = (state && state.geser) ? state.geser : { x: 0, y: 0 };
        var batas = function (v) { return Math.max(-15, Math.min(15, Math.round(v * 2) / 2)); };
        return { gx: batas(p.gx + (parseFloat(c.x) || 0)), gy: batas(p.gy + (parseFloat(c.y) || 0)), arah: p.arah, cx: parseFloat(c.x) || 0, cy: parseFloat(c.y) || 0 };
    }
    // Lebar huruf TERBESAR yang pernah ditemui per font TSPL (dot), supaya teks
    // tidak keluar stiker walau printer memakai huruf lebih lebar dari standar.
    var LEBAR_FONT = { '1': 10, '2': 16, '3': 16 }; // font 1 standar 8 (Blueprint terukur 8), font 2 Blueprint terukur 16

    function tsplAwal(w, h, p) {
        // Geser diterapkan langsung ke koordinat (bukan REFERENCE) — nilai minus aman di semua printer
        return ['SIZE ' + w + ' mm,' + h + ' mm', 'GAP 2 mm,0 mm', 'DIRECTION ' + p.arah, 'REFERENCE 0,0', 'CLS'];
    }
    function q(t) { return '"' + ascii(t).replace(/"/g, "'") + '"'; }

    // ---- Label sebagai GAMBAR (2026-11-06) ----
    // Foto Owner: huruf bawaan printer beda-beda ukurannya (Blueprint: semua font ±12×24 titik)
    // sehingga baris bertabrakan & ukuran huruf tidak bisa diatur. Label digambar di canvas
    // seukuran stiker (8 titik/mm) lalu dikirim sebagai BITMAP TSPL: ukuran huruf, tebal &
    // posisi pasti sama di semua printer, dan contoh di layar = hasil cetak.
    var KEY_MODE = 'labelCupMode', KEY_BALIK = 'labelCupBalik';
    function modeGambar() { return ls(KEY_MODE) !== 'teks' && !!document.createElement('canvas').getContext; }

    function gambarLabel(l, w, h, ox, oy) {
        var W = w * 8, H = h * 8, cv = document.createElement('canvas');
        cv.width = W; cv.height = H;
        var g = cv.getContext('2d');
        g.fillStyle = '#fff'; g.fillRect(0, 0, W, H);
        var M = 24, kanan = W - M, lebar = kanan - M;        // margin 3 mm kiri & kanan
        // Geser ke kiri/atas dibatasi supaya huruf tidak keluar dari gambar (printer tidak bisa
        // mencetak di kiri titik nolnya): sisakan minimal 2 titik.
        ox = Math.max(2 - M, ox || 0); oy = Math.max(-8, oy || 0);
        g.translate(ox, oy);                                 // geser posisi (mm × 8)
        g.fillStyle = '#000'; g.textBaseline = 'top';
        var F = 'Arial, Helvetica, sans-serif';
        var font = function (px, tebal) { g.font = (tebal ? 'bold ' : '') + px + 'px ' + F; };
        var potong = function (t, maks) {
            t = String(t || '');
            if (g.measureText(t).width <= maks) return t;
            while (t.length && g.measureText(t + '…').width > maks) t = t.slice(0, -1);
            return t + '…';
        };
        var bungkus = function (t, maks, maksBaris) {
            if (maksBaris <= 0) { var k = []; k.terpotong = !!String(t || '').trim(); return k; }
            var out = [], cur = '';
            String(t || '').split(/\s+/).filter(Boolean).forEach(function (kata) {
                var c = cur ? cur + ' ' + kata : kata;
                if (g.measureText(c).width <= maks) cur = c; else { if (cur) out.push(cur); cur = kata; }
            });
            if (cur) out.push(cur);
            out = out.map(function (s) { return potong(s, maks); });
            var terpotong = out.length > maksBaris;
            if (terpotong) { var sisa = out.slice(maksBaris - 1).join(' '); out = out.slice(0, maksBaris - 1); out.push(potong(sisa, maks)); }
            out.terpotong = terpotong;
            return out;
        };

        // 1. Nama toko di paling atas (permintaan Owner), lalu nomor antrean besar + urutan stiker di kanan
        var y = 10, kd = l.ke + '/' + l.dari;
        font(14, true); g.fillText(potong(String(l.toko).toUpperCase(), lebar), M, y); y += 18;
        // Urutan stiker (1/2) rata kanan TANPA kotak (permintaan Owner); posisi tengah stiker diatur
        // lewat geser per cabang. Nomor dipotong supaya tidak pernah menimpa "1/2".
        font(18, true); var wKd = g.measureText(kd).width; g.fillText(kd, kanan - wKd, y + 9);
        font(30, true); g.fillText(potong('#' + l.nomor, lebar - wKd - 10), M, y);
        y += 33; g.fillRect(M, y, lebar, 2); y += 6;

        // Batas bawah: baris info (15 px) + nama pelanggan (bila ada) + Exp Frozen (bila ada, khusus Dimsum —
        // tanggal kedaluwarsa punya tempat tetap supaya tidak pernah terpotong)
        var yInfo = H - 14 - 15, yPelanggan = yInfo - 4 - (l.pelanggan ? 24 : 0);
        var yExp = yPelanggan - (l.exp ? 20 : 0), batas = yExp;

        // 2. Nama menu tebal, 2 baris, dikecilkan sampai muat
        var uk = 26, baris = [];
        for (; uk >= 18; uk -= 2) { font(uk, true); baris = bungkus(l.nama, lebar, 2); if (!baris.terpotong) break; }
        baris.forEach(function (t) { if (y + uk <= batas) { g.fillText(t, M, y); y += uk + 3; } });
        y += 2;

        // 3. Opsi (Size/Gula/Es…) & catatan
        font(17, false);
        var badan = [];
        if (l.opsi && l.opsi.length) badan.push(l.opsi.join(' · '));
        if (l.catatan) badan.push(l.catatan);
        if (l.catatan_order) badan.push(l.catatan_order);   // catatan pesanan (kolom Catatan di POS)
        // Jatah baris: hanya ruang antara nama menu dan nama pelanggan. Setiap teks dapat minimal
        // 1 baris (kalau ada tempat), sisa baris dibagi berurutan; kelebihan dipotong "…" —
        // tidak pernah menimpa nama pelanggan / baris jam.
        var sisaBaris = Math.max(0, Math.floor((batas - y) / 20));
        if (badan.length > sisaBaris && sisaBaris > 0) badan = badan.slice(0, sisaBaris - 1).concat([badan.slice(sisaBaris - 1).join(' · ')]);
        var perlu = badan.map(function (t) { return bungkus(t, lebar, 99).length; });
        var jatah = badan.map(function () { return sisaBaris > 0 ? 1 : 0; });
        var sisa = sisaBaris - jatah.reduce(function (a, b) { return a + b; }, 0);
        for (var bi = 0; bi < badan.length && sisa > 0; bi++) { var tambah = Math.min(sisa, perlu[bi] - jatah[bi]); if (tambah > 0) { jatah[bi] += tambah; sisa -= tambah; } }
        badan.forEach(function (t, i) {
            bungkus(t, lebar, jatah[i]).forEach(function (s) { g.fillText(s, M, y); y += 20; });
        });

        // 4. Exp (Frozen) + nama pelanggan (tebal)
        if (l.exp) { font(17, true); g.fillText(potong('Exp: ' + l.exp, lebar), M, yExp + 1); }
        if (l.pelanggan) { font(20, true); g.fillText(potong(String(l.pelanggan).toUpperCase(), lebar), M, yPelanggan + 2); }

        // 5. Baris bawah: jam + tanggal rata kanan (selalu utuh), tipe pesanan/meja di kiri dipotong bila panjang
        font(15, false);
        var info = String(l.info || ''), pisah = info.lastIndexOf(' · ');
        var waktu = pisah >= 0 ? info.slice(pisah + 3) : '', tipe = pisah >= 0 ? info.slice(0, pisah) : info;
        var wWaktu = waktu ? g.measureText(waktu).width : 0;
        if (waktu) g.fillText(waktu, kanan - wWaktu, yInfo);
        g.fillText(potong(tipe, lebar - wWaktu - (waktu ? 10 : 0)), M, yInfo);
        return cv;
    }

    // Canvas → data BITMAP TSPL (1 bit/titik). Standar TSPL: bit 0 = titik hitam.
    function canvasKeBitmap(cv, balik) {
        var W = cv.width, H = cv.height, wb = Math.ceil(W / 8);
        var d = cv.getContext('2d').getImageData(0, 0, W, H).data, out = new Uint8Array(wb * H);
        out.fill(balik ? 0x00 : 0xFF);
        for (var y = 0; y < H; y++) {
            for (var x = 0; x < W; x++) {
                var i = (y * W + x) * 4;
                if ((d[i] * 299 + d[i + 1] * 587 + d[i + 2] * 114) / 1000 < 140) {
                    var b = y * wb + (x >> 3), m = 0x80 >> (x & 7);
                    if (balik) out[b] |= m; else out[b] &= ~m;
                }
            }
        }
        return { data: out, wb: wb, h: H };
    }

    function gabung(bagian) {
        var n = 0; bagian.forEach(function (b) { n += b.length; });
        var out = new Uint8Array(n), o = 0;
        bagian.forEach(function (b) { out.set(b, o); o += b.length; });
        return out;
    }

    function buildTsplGambar() {
        var w = state.ukuran.lebar, h = state.ukuran.tinggi, p = posisi(), enc = new TextEncoder();
        var balik = ls(KEY_BALIK) === '1', bagian = [];
        state.labels.forEach(function (l) {
            var bmp = canvasKeBitmap(gambarLabel(l, w, h, Math.round(p.gx * 8), Math.round(p.gy * 8)), balik);
            bagian.push(enc.encode(tsplAwal(w, h, p).join('\r\n') + '\r\nBITMAP 0,0,' + bmp.wb + ',' + bmp.h + ',0,'));
            bagian.push(bmp.data);
            bagian.push(enc.encode('\r\nPRINT 1,1\r\n'));
        });
        return gabung(bagian);
    }

    function buildTspl() { return modeGambar() ? buildTsplGambar() : buildTsplTeks(); }

    // Mode teks (huruf bawaan printer) — cadangan kalau printer tidak mendukung BITMAP
    function buildTsplTeks() {
        var w = state.ukuran.lebar, h = state.ukuran.tinggi, dotsW = w * 8, dotsH = h * 8, p = posisi();
        var ox = Math.round(p.gx * 8), oy = Math.round(p.gy * 8);
        // 2026-11-06 (Owner: tulisan dikecilkan, stiker 40×30): margin 3 mm KIRI & KANAN sama besar —
        // saat tulisan diputar 180° sisi yang terpotong ikut berpindah, jadi keduanya harus aman.
        var x = Math.max(0, 24 + ox), lebar = dotsW - 48;
        var muat = function (font) { return Math.max(4, Math.floor(lebar / LEBAR_FONT[font])); };
        var out = '';
        state.labels.forEach(function (l) {
            var cmds = tsplAwal(w, h, p);
            var y = Math.max(0, 16 + oy), batas = dotsH - 34 + oy; // atas 2 mm, baris terakhir untuk info
            var tulis = function (font, t, tinggi) {
                if (y + tinggi <= batas) { cmds.push('TEXT ' + x + ',' + y + ',"' + font + '",0,1,1,' + q(t)); y += tinggi; }
            };
            // Setiap elemen di barisnya sendiri — tidak ada yang bisa saling menabrak
            // Tulisan kecil: hanya nomor & nama menu yang font sedang (2), sisanya font kecil (1)
            tulis('1', ascii(l.toko.toUpperCase()).slice(0, muat('1')), 16);
            tulis('2', ('#' + l.nomor + '  ' + l.ke + '/' + l.dari).slice(0, muat('2')), 24);
            cmds.push('BAR ' + x + ',' + y + ',' + lebar + ',2'); y += 6;
            wrap(l.nama, muat('2')).slice(0, 2).forEach(function (t) { tulis('2', t, 24); });
            bodyLines(l).forEach(function (b) { wrap(b, muat('1')).forEach(function (t) { tulis('1', t, 16); }); });
            cmds.push('TEXT ' + x + ',' + Math.max(0, dotsH - 30 + oy) + ',"1",0,1,1,' + q(ascii(l.info).slice(0, muat('1'))));
            cmds.push('PRINT 1,1');
            out += cmds.join('\r\n') + '\r\n';
        });
        return new TextEncoder().encode(out);
    }

    // Label uji: bingkai tepat di tepi stiker + tanda margin, untuk mengatur posisi.
    function buildTsplUji() {
        var w = (state && state.ukuran) ? state.ukuran.lebar : 40, h = (state && state.ukuran) ? state.ukuran.tinggi : 30;
        var dotsW = w * 8, dotsH = h * 8, p = posisi();
        var ox = Math.round(p.gx * 8), oy = Math.round(p.gy * 8), c = function (v) { return Math.max(0, v); };
        var cmds = tsplAwal(w, h, p);
        cmds.push('BOX ' + c(ox) + ',' + c(oy) + ',' + c(dotsW - 1 + ox) + ',' + c(dotsH - 1 + oy) + ',3');
        cmds.push('BOX ' + c(24 + ox) + ',' + c(16 + oy) + ',' + c(dotsW - 24 + ox) + ',' + c(dotsH - 16 + oy) + ',1'); // batas tulisan
        cmds.push('TEXT ' + c(40 + ox) + ',' + c(24 + oy) + ',"2",0,1,1,' + q('LABEL UJI ' + w + 'x' + h));
        cmds.push('TEXT ' + c(40 + ox) + ',' + c(56 + oy) + ',"1",0,1,1,' + q('Geser X ' + p.gx + ' Y ' + p.gy + ' (cabang ' + p.cx + '/' + p.cy + ')'));
        cmds.push('TEXT ' + c(40 + ox) + ',' + c(80 + oy) + ',"1",0,1,1,' + q('Bingkai tebal = tepi stiker'));
        cmds.push('TEXT ' + c(40 + ox) + ',' + c(104 + oy) + ',"1",0,1,1,' + q('ABCDEFGHIJKLMNOPQRSTUVWXYZ'));
        cmds.push('PRINT 1,1');
        return new TextEncoder().encode(cmds.join('\r\n') + '\r\n');
    }

    // Printer ESC/POS (printer struk/label yang tidak mengerti TSPL, mis. Blueprint
    // ECO80BT). Lebar baris mengikuti lebar stiker (font A = 12 dot ≈ 1,5 mm),
    // tinggi tiap stiker dihitung dalam dot lalu sisa dimajukan dengan ESC J
    // supaya stiker berikutnya mulai di posisi yang sama (tinggi + celah 2 mm).
    function buildEscpos() {
        var ESC = 0x1B, bytes = [];
        var SP = 24;                                             // jarak baris = tinggi huruf font A
        var cols = Math.max(16, Math.floor((state.ukuran.lebar - 2) * 8 / 12));
        var pitch = Math.round((state.ukuran.tinggi + 2) * 8);   // dot per stiker (+ celah 2 mm)
        var maksBaris = Math.max(4, Math.floor((pitch - 32) / SP));
        var enc = new TextEncoder();
        var push = function (arr) { for (var i = 0; i < arr.length; i++) bytes.push(arr[i]); };

        state.labels.forEach(function (l) {
            var baris = 0;
            var line = function (t) { if (baris < maksBaris) { push(enc.encode(ascii(t).slice(0, cols) + '\n')); baris++; } };

            push([ESC, 0x40, ESC, 0x61, 0x00, ESC, 0x33, SP]);   // reset, rata kiri, jarak baris
            line(l.toko.toUpperCase());
            var kanan = l.ke + '/' + l.dari, kiri = '#' + l.nomor;
            push([ESC, 0x45, 0x01]);
            line(kiri + ' '.repeat(Math.max(1, cols - kiri.length - kanan.length)) + kanan);
            wrap(l.nama, cols).slice(0, 2).forEach(line);
            push([ESC, 0x45, 0x00]);
            // Sisakan 1 baris terakhir untuk tipe pesanan + jam
            var badan = [];
            bodyLines(l).forEach(function (b) { badan = badan.concat(wrap(b, cols)); });
            badan.slice(0, Math.max(0, maksBaris - baris - 1)).forEach(line);
            line(l.info);

            var sisa = pitch - baris * SP;                       // maju tepat ke stiker berikutnya
            while (sisa > 0) { var n = Math.min(255, sisa); push([ESC, 0x4A, n]); sisa -= n; }
        });
        return new Uint8Array(bytes);
    }

    // ---- Bluetooth (pola sama dengan cetak struk, printer disimpan terpisah) ----
    async function findChar(server) {
        for (var i = 0; i < BT_SERVICE_UUIDS.length; i++) {
            try {
                var svc = await server.getPrimaryService(BT_SERVICE_UUIDS[i]);
                var chars = await svc.getCharacteristics();
                for (var j = 0; j < chars.length; j++) {
                    if (chars[j].properties.writeWithoutResponse || chars[j].properties.write) return chars[j];
                }
            } catch (e) { /* lanjut */ }
        }
        return null;
    }
    async function send(chr, data) {
        for (var off = 0; off < data.length; off += 180) {
            var chunk = data.slice(off, off + 180);
            if (chr.properties.writeWithoutResponse) await chr.writeValueWithoutResponse(chunk); else await chr.writeValue(chunk);
            await new Promise(function (r) { setTimeout(r, 30); });
        }
    }

    window.cupLabelCetakBluetooth = async function (pakaiTersimpan, uji) {
        if (!state && !uji) return;
        var proto = el('cupLabelProtocol').value;
        if (uji && proto !== 'tspl') { status('Label uji hanya untuk printer label (TSPL).', 'warning'); return; }
        ls(KEY_PROTOCOL, proto);
        var secure = location.protocol === 'https:' || ['localhost', '127.0.0.1'].indexOf(location.hostname) >= 0;
        if (!secure || !navigator.bluetooth) {
            status('Cetak Bluetooth butuh Chrome/Edge dan HTTPS. Gunakan "Cetak Browser".', 'danger');
            return;
        }
        var device = null, server = null, saved = ls(KEY_PRINTER);
        try {
            if (pakaiTersimpan && saved && navigator.bluetooth.getDevices) {
                var list = await navigator.bluetooth.getDevices();
                device = list.find(function (d) { return d.name === saved; }) || null;
            }
            if (!device) {
                status('Pilih printer label dari daftar...', 'muted');
                device = await navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: BT_SERVICE_UUIDS });
            }
            status('Menghubungkan ke "' + (device.name || 'Printer') + '"...', 'muted');
            server = await device.gatt.connect();
            var chr = await findChar(server);
            if (!chr) throw new Error('Service printer tidak ditemukan.');
            await send(chr, uji ? buildTsplUji() : (proto === 'tspl' ? buildTspl() : buildEscpos()));
            ls(KEY_PRINTER, device.name || 'Printer');
            refreshPrinterUi();
            status(uji ? '✓ Label uji terkirim — cek bingkainya.' : '✓ ' + state.labels.length + ' label terkirim ke "' + (device.name || 'Printer') + '".', 'success');
        } catch (e) {
            status(e && e.name === 'NotFoundError' ? 'Pemilihan printer dibatalkan.' : 'Gagal cetak Bluetooth: ' + (e && e.message ? e.message : e), 'danger');
        } finally {
            await new Promise(function (r) { setTimeout(r, 200); });
            if (server && server.connected) { try { server.disconnect(); } catch (e) {} }
        }
    };
})();
</script>
