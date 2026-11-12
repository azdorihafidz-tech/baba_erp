{{--
    4.44 Poin 1 — helper JS `window.KDApk` untuk aplikasi Android "Kasir POS"
    (porting erp-coffeeshop 097a577 + b5155eb). Aplikasi menyuntikkan
    `window.KopiDripPrinter` (jembatan Bluetooth). Kalau halaman dibuka di
    browser biasa (Chrome/PC), objek itu tidak ada → KDApk.ada() = false dan
    semua alur cetak lama (Web Bluetooth / Cetak Browser) berjalan persis
    seperti sebelumnya.

    KONTRAK DENGAN APK — JANGAN DIUBAH: window.KopiDripPrinter.info(),
    .print(target, base64, cbId), .openDrawer(cbId), .openSettings(), dan
    callback window.KopiDripPrinterResult(id, ok, pesan).

    BABA Tahap 6b: helper `window.BabaAgent` untuk agen printer PC Windows
    (BABA Printer Agent, tools/printer-agent, port 17331) — lihat bagian AGEN PRINTER PC.
--}}
<script>
(function () {
    var antrean = {};
    var seq = 0;

    function bridge() { return window.KopiDripPrinter || null; }

    // Dipanggil aplikasi Android setelah cetak selesai/gagal
    window.KopiDripPrinterResult = function (id, ok, pesan) {
        var cb = antrean[id];
        if (!cb) return;
        delete antrean[id];
        ok ? cb.resolve(pesan) : cb.reject(new Error(pesan || 'Gagal mencetak.'));
    };

    function keBase64(bytes) {
        if (!(bytes instanceof Uint8Array)) bytes = new Uint8Array(bytes);
        var bin = '';
        for (var i = 0; i < bytes.length; i += 8192) {
            bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 8192));
        }
        return btoa(bin);
    }

    function panggil(fn) {
        return new Promise(function (resolve, reject) {
            var id = 'kd' + (++seq) + '_' + Date.now();
            antrean[id] = { resolve: resolve, reject: reject };
            try { fn(id); } catch (e) { delete antrean[id]; reject(e); }
            // Pengaman: Bluetooth yang menggantung tidak boleh mengunci layar kasir
            setTimeout(function () {
                if (antrean[id]) { delete antrean[id]; reject(new Error('Printer tidak merespons (timeout).')); }
            }, 30000);
        });
    }

    var infoCache = null;

    window.KDApk = {
        ada: function () { return !!bridge(); },

        /** {versi, struk, label, label_protokol, auto_struk, auto_label} — {} kalau bukan APK. */
        info: function () {
            if (!bridge()) return {};
            if (infoCache) return infoCache;
            try { infoCache = JSON.parse(bridge().info() || '{}'); } catch (e) { infoCache = {}; }
            return infoCache;
        },

        /** target: 'struk' | 'label'; bytes: Uint8Array / array angka data printer. */
        cetak: function (target, bytes) {
            if (!bridge()) return Promise.reject(new Error('Bukan aplikasi kasir.'));
            var b64 = keBase64(bytes);
            return panggil(function (id) { bridge().print(target, b64, id); });
        },

        bukaLaci: function () {
            if (!bridge()) return Promise.reject(new Error('Bukan aplikasi kasir.'));
            return panggil(function (id) { bridge().openDrawer(id); });
        },

        bukaPengaturan: function () { if (bridge()) bridge().openSettings(); },

        /** Notifikasi kecil di pojok bawah (tidak menghalangi kasir). */
        toast: function (pesan, ok) {
            var el = document.createElement('div');
            el.textContent = pesan;
            el.style.cssText = 'position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:3000;'
                + 'padding:10px 16px;border-radius:8px;color:#fff;font-size:14px;box-shadow:0 2px 8px rgba(0,0,0,.25);'
                + 'background:' + (ok === false ? '#b91c1c' : '#1A1A1A');
            document.body.appendChild(el);
            setTimeout(function () { el.remove(); }, ok === false ? 5000 : 2500);
        },
    };

    // Menu sidebar "Pengaturan Printer Aplikasi" hanya muncul di aplikasi kasir
    if (bridge()) {
        var link = document.getElementById('kdApkSettingsLink');
        if (link) link.style.display = '';
    }

    // ============================================================
    //  AGEN PRINTER PC (BABA Printer Agent, tools/printer-agent)
    //  Program kecil di PC kasir Windows yang mendengar HANYA di
    //  127.0.0.1:17331 dan meneruskan byte ESC/POS ke printer
    //  (Bluetooth via port COM / printer USB Windows).
    //  Opt-in per PC (localStorage) — ERP TIDAK menghubungi 127.0.0.1
    //  sebelum diaktifkan, supaya browser lain (tablet/HP) tidak
    //  menampilkan izin "akses perangkat di jaringan lokal".
    // ============================================================
    var AGENT_URL = 'http://127.0.0.1:17331';
    var AGENT_KEY = 'baba_print_agent';

    function agentFetch(path, opt, ms) {
        var ctrl = ('AbortController' in window) ? new AbortController() : null;
        var t = ctrl ? setTimeout(function () { ctrl.abort(); }, ms) : null;
        opt = opt || {};
        if (ctrl) opt.signal = ctrl.signal;
        return fetch(AGENT_URL + path, opt).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) {
                if (!r.ok || j.ok === false) {
                    var e = new Error(j.pesan || ('Agen printer menjawab HTTP ' + r.status));
                    e.agentMenjawab = true;
                    throw e;
                }
                return j;
            });
        }, function (err) {
            var e = new Error(err && err.name === 'AbortError'
                ? 'Agen printer PC tidak merespons (timeout).'
                : 'Agen printer PC tidak menyala di komputer ini.');
            e.agentMati = true;
            throw e;
        }).finally(function () { if (t) clearTimeout(t); });
    }

    function kirimAgent(path, body) {
        return agentFetch(path, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body || {}),
        }, 30000);
    }

    window.BabaAgent = {
        url: AGENT_URL,

        /** Sudah diaktifkan di PC/browser ini? */
        aktif: function () {
            try { return localStorage.getItem(AGENT_KEY) === '1'; } catch (e) { return false; }
        },

        setAktif: function (v) {
            try { v ? localStorage.setItem(AGENT_KEY, '1') : localStorage.removeItem(AGENT_KEY); } catch (e) {}
            tampilLinkAgent();
        },

        /** {agent, versi, siap, tipe, printer} — throw kalau agen tidak menyala. */
        cek: function () {
            return agentFetch('/status', {}, 2500).then(function (j) {
                if (j.agent !== 'baba-printer-agent') {
                    var e = new Error('Agen printer PC tidak menyala di komputer ini.');
                    e.agentMati = true;
                    throw e;
                }
                return j;
            });
        },

        /** bytes: Uint8Array data printer (sama dengan jalur Bluetooth). */
        cetak: function (bytes) { return kirimAgent('/print', { data: keBase64(bytes) }); },

        bukaLaci: function () { return kirimAgent('/drawer'); },

        bukaPengaturan: function () { window.open(AGENT_URL + '/', '_blank'); },
    };

    function tampilLinkAgent() {
        var l = document.getElementById('babaAgentSettingsLink');
        if (l) l.style.display = (!bridge() && window.BabaAgent.aktif()) ? '' : 'none';
    }
    tampilLinkAgent();
})();
</script>
