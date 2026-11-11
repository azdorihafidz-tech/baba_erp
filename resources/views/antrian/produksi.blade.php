@extends('layouts.app')

@section('title', 'Mode Produksi — ' . $cabang->nama_cabang)

@push('styles')
<style>
/* ===== PRODUKSI MODE — hide sidebar/navbar, full-width layout ===== */
body.produksi-mode #sidebar,
body.produksi-mode .sidebar-overlay,
body.produksi-mode .breadcrumb-section,
body.produksi-mode footer,
body.produksi-mode .app-footer,
body.produksi-mode .navbar { display: none !important; }

body.produksi-mode .main-content,
body.produksi-mode #mainContent { margin-left: 0 !important; padding: 0 !important; }

/* ── Header ── */
.pm-header {
    background: #1e293b;
    color: #fff;
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    position: sticky;
    top: 0;
    z-index: 100;
}
.pm-title-block .pm-brand  { font-weight: 700; font-size: 1.05rem; display: block; }
.pm-title-block .pm-hint   { font-size: 0.75rem; color: #64748b; display: block; margin-top: 1px; }
.pm-header .pm-spacer { flex: 1; }
.pm-header .pm-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

/* ── Stats bar ── */
.pm-stats {
    background: #0f172a;
    color: #cbd5e1;
    padding: 8px 16px;
    font-size: 0.82rem;
    display: flex;
    gap: 20px;
    align-items: center;
    flex-wrap: wrap;
}
.pm-stats .stat-item { display: flex; gap: 5px; align-items: center; }
.pm-stats .stat-num  { font-weight: 700; font-size: 1.1rem; }
.pm-stats .stat-menunggu   { color: #fbbf24; }
.pm-stats .stat-dikerjakan { color: #34d399; }

/* ── Card list ── */
.pm-body { padding: 14px 12px; }

.pm-card {
    border-radius: 14px;
    margin-bottom: 14px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,.10);
    border: 2px solid transparent;
}
.pm-card.status-menunggu   { background: #fffbeb; border-color: #fde68a; }
.pm-card.status-dikerjakan { background: #f0fdf4; border-color: #86efac; }
.pm-card-body { padding: 14px 16px; }

.pm-no-antrian {
    font-size: 2.8rem; font-weight: 900; line-height: 1;
    min-width: 72px; text-align: center; flex-shrink: 0;
}
.status-menunggu   .pm-no-antrian { color: #92400e; }
.status-dikerjakan .pm-no-antrian { color: #166534; }

.pm-nama { font-size: 1.15rem; font-weight: 700; }
.pm-meta { font-size: 0.85rem; color: #64748b; margin-top: 3px; }

.pm-status-tag {
    display: inline-block; font-size: 0.78rem; font-weight: 600;
    padding: 2px 9px; border-radius: 20px; margin-bottom: 4px;
}
.status-menunggu   .pm-status-tag { background: #fde68a; color: #78350f; }
.status-dikerjakan .pm-status-tag { background: #bbf7d0; color: #14532d; }

/* ── Big action buttons ── */
.btn-produksi {
    min-height: 70px; font-size: 1.2rem; font-weight: 700;
    border-radius: 12px; padding: 14px 20px; width: 100%;
    box-shadow: 0 3px 8px rgba(0,0,0,.15); border: none; cursor: pointer;
    transition: transform .1s, box-shadow .1s;
}
.btn-produksi:active { transform: scale(.98); box-shadow: 0 1px 4px rgba(0,0,0,.15); }
.btn-mulai   { background: #16a34a; color: #fff; }
.btn-mulai:hover   { background: #15803d; color: #fff; }
.btn-selesai { background: #2563eb; color: #fff; }
.btn-selesai:hover { background: #1d4ed8; color: #fff; }

/* ── Ganti Operator button (kecil) ── */
.btn-ganti-op {
    font-size: 0.8rem; padding: 5px 12px; border-radius: 8px;
    background: #fef9c3; color: #713f12; border: 1px solid #fde68a;
    cursor: pointer; white-space: nowrap; margin-top: 8px;
}
.btn-ganti-op:hover { background: #fef08a; }

/* ── Info dikerjakan ── */
.pm-dikerjakan-info {
    font-size: 0.88rem; color: #166534; font-weight: 600; margin-bottom: 10px;
}

/* ── Pilih Karyawan modal: tombol besar ── */
.btn-pilih-karyawan {
    display: block; width: 100%; min-height: 60px;
    font-size: 1.05rem; font-weight: 600; text-align: left;
    padding: 12px 16px; border-radius: 10px; border: 2px solid #e2e8f0;
    background: #f8fafc; color: #1e293b; cursor: pointer; margin-bottom: 8px;
    transition: background .1s, border-color .1s;
}
.btn-pilih-karyawan:hover  { background: #dbeafe; border-color: #3b82f6; }
.btn-pilih-karyawan:active { background: #bfdbfe; }

/* ── Empty state ── */
.pm-empty { text-align: center; padding: 60px 20px; color: #94a3b8; }
.pm-empty .pm-empty-icon { font-size: 3.5rem; display: block; margin-bottom: 12px; }
.pm-empty p { font-size: 1.1rem; margin: 0; }

/* ── Toast ── */
.pm-toast {
    position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
    background: #1e293b; color: #fff; padding: 12px 24px;
    border-radius: 10px; font-size: 0.95rem; font-weight: 600;
    z-index: 9999; box-shadow: 0 4px 16px rgba(0,0,0,.3);
    display: none; max-width: 90vw; text-align: center;
}
.pm-toast.show { display: block; animation: fadeInUp .2s; }
.pm-toast.toast-success { background: #166534; }
.pm-toast.toast-error   { background: #991b1b; }

@keyframes fadeInUp {
    from { opacity: 0; transform: translateX(-50%) translateY(10px); }
    to   { opacity: 1; transform: translateX(-50%) translateY(0); }
}

/* ── Sound button ── */
.btn-sound-off { background: #374151; color: #9ca3af; border: 1px solid #4b5563; }
.btn-sound-on  { background: #166534; color: #fff;    border: 1px solid #16a34a; }

/* ── Landscape tablet ── */
@media (min-width: 768px) {
    .pm-body { padding: 16px; }
    .pm-card-inner  { display: flex; gap: 16px; align-items: flex-start; }
    .pm-card-info   { flex: 1; }
    .pm-card-action { min-width: 220px; }
}

/* ── Landscape phone / small tablet ── */
@media (min-width: 480px) and (max-height: 600px) {
    .pm-card-body { padding: 10px 14px; }
    .pm-no-antrian { font-size: 2.2rem; }
    .btn-produksi  { min-height: 58px; font-size: 1.05rem; }
    .pm-header { padding: 8px 12px; }
}
</style>
@endpush

@section('content')
<x-back-button-pwa />
{{-- Header Mode Produksi --}}
<div class="pm-header">
    <div class="pm-title-block">
        <span class="pm-brand">🏭 Mode Produksi — {{ $cabang->nama_cabang }}</span>
        <span class="pm-hint">Tablet bersama · pilih nama operator saat mulai mengerjakan</span>
    </div>
    <div class="pm-spacer"></div>
    <div class="pm-actions">
        <button type="button" id="btnSound" class="btn btn-sm btn-sound-off" onclick="toggleSound()"
                title="Aktifkan notifikasi suara saat antrian baru masuk">
            🔇 Aktifkan Suara
        </button>
        <a href="{{ route('antrian.operator') }}" class="btn btn-sm btn-outline-light">
            📋 Mode Manajer
        </a>
    </div>
</div>

{{-- Stats bar --}}
<div class="pm-stats">
    <div class="stat-item">
        <span class="stat-num stat-menunggu" id="statMenunggu">–</span>
        <span>Menunggu</span>
    </div>
    <div class="stat-item">
        <span class="stat-num stat-dikerjakan" id="statDikerjakan">–</span>
        <span>Dikerjakan</span>
    </div>
    <div class="stat-item ms-auto" id="statLastRefresh" style="color:#475569;font-size:.78rem;">
        Memuat...
    </div>
    <div class="stat-item" id="realtimeIndicator" style="font-size:.78rem;display:none;">
        <span id="realtimeDot" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#fbbf24;margin-right:4px;"></span>
        <span id="realtimeLabel">Menghubungkan...</span>
    </div>
</div>

{{-- Card list --}}
<div class="pm-body" id="pmCardList">
    <div class="pm-empty">
        <span class="pm-empty-icon">⏳</span>
        <p>Memuat antrian...</p>
    </div>
</div>

{{-- Toast --}}
<div class="pm-toast" id="pmToast"></div>

{{-- Modal Pilih Karyawan (shared: Mulai + Ganti Operator) --}}
<div class="modal fade" id="modalPilihKaryawan" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="pkModalTitle">Siapa yang mengerjakan?</h5>
                    <p class="text-muted small mb-0 mt-1" id="pkModalSubtitle"></p>
                </div>
            </div>
            <div class="modal-body py-3" id="pkKaryawanList">
                <p class="text-center text-muted">Memuat daftar karyawan...</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary w-100 py-3 fw-bold fs-5"
                        data-bs-dismiss="modal">Batal</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal Konfirmasi Selesai --}}
<div class="modal fade" id="modalSelesai" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">✅ Konfirmasi Selesai</h5>
            </div>
            <div class="modal-body py-3">
                <p class="fs-5 mb-1">Yakin antrian <strong id="modalSelesaiNo"></strong> sudah selesai dikerjakan?</p>
                <p class="text-muted small mb-0">Pelanggan akan diberitahu untuk mengambil pesanannya.</p>
            </div>
            <div class="modal-footer border-0 gap-2">
                <button type="button" class="btn btn-secondary flex-fill py-3 fw-bold fs-5"
                        data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary flex-fill py-3 fw-bold fs-5"
                        id="btnSelesaiConfirm" onclick="doSelesai()">
                    ✅ Ya, Selesai
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
{{-- Pusher JS & Laravel Echo via CDN (sama seperti test-pusher) --}}
@if(config('broadcasting.connections.pusher.key'))
<script src="https://js.pusher.com/8.4.0-rc2/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js"></script>
@endif

<script>
(function () {
    'use strict';

    /* ── Config ── */
    const REFRESH_INTERVAL_CONNECTED    = 30000; // 30 detik saat Pusher connected
    const REFRESH_INTERVAL_DISCONNECTED = 10000; // 10 detik fallback saat offline
    const KARYAWAN_REFRESH_INTERVAL     = 300000; // karyawan list refresh (5 menit)
    const PUSHER_KEY     = @json(config('broadcasting.connections.pusher.key') ?? '');
    const PUSHER_CLUSTER = @json(config('broadcasting.connections.pusher.options.cluster') ?? 'ap1');
    const CABANG_ID      = {{ $cabang->id }};
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const ROUTES = {
        data:         '{{ route("antrian.produksi.data") }}',
        karyawan:     '{{ route("antrian.produksi.karyawan") }}',
        mulai:        '/antrian/{id}/mulai-kerja',
        selesai:      '/antrian/{id}/selesai',
        ambilAlih:    '/antrian/{id}/ambil-alih',
    };

    /* ── State ── */
    let knownIds        = new Set();
    let pendingOrder    = null;       // { id, no, action: 'mulai'|'selesai'|'ambilAlih' }
    let karyawanList    = [];         // cache dari endpoint
    let soundEnabled    = localStorage.getItem('antrian_sound_enabled') === 'true';
    let refreshTimer    = null;
    let pusherConnected = false;

    /* ── Helpers ── */
    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function durLabel(menit) {
        if (menit === null || menit === undefined) return '';
        if (menit < 1) return 'baru saja';
        if (menit < 60) return menit + ' menit';
        var j = Math.floor(menit / 60), m = menit % 60;
        return j + ' jam' + (m ? ' ' + m + ' menit' : '');
    }

    /* ── Sound ── */
    function playBell() {
        if (!soundEnabled) return;
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            [{ f: 880, t: 0, dur: 0.8 }, { f: 660, t: 0.2, dur: 0.6 }].forEach(function (n) {
                var osc = ctx.createOscillator(), gain = ctx.createGain();
                osc.connect(gain); gain.connect(ctx.destination);
                osc.type = 'sine'; osc.frequency.value = n.f;
                gain.gain.setValueAtTime(n.t === 0 ? 0.4 : 0.3, ctx.currentTime + n.t);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + n.t + n.dur);
                osc.start(ctx.currentTime + n.t);
                osc.stop(ctx.currentTime + n.t + n.dur);
            });
        } catch (e) { console.warn('Audio not available', e); }
    }

    window.toggleSound = function () {
        soundEnabled = !soundEnabled;
        localStorage.setItem('antrian_sound_enabled', soundEnabled);
        updateSoundBtn();
        if (soundEnabled) playBell();
    };

    function updateSoundBtn() {
        var btn = document.getElementById('btnSound');
        if (!btn) return;
        if (soundEnabled) {
            btn.textContent = '🔊 Suara Aktif';
            btn.className   = btn.className.replace('btn-sound-off', '').trim() + ' btn-sound-on';
        } else {
            btn.textContent = '🔇 Aktifkan Suara';
            btn.className   = btn.className.replace('btn-sound-on', '').trim() + ' btn-sound-off';
        }
    }

    /* ── Toast ── */
    function showToast(msg, type) {
        var t = document.getElementById('pmToast');
        t.textContent = msg;
        t.className = 'pm-toast show toast-' + (type || 'success');
        clearTimeout(t._timer);
        t._timer = setTimeout(function () { t.className = 'pm-toast'; }, 3500);
    }

    /* ── AJAX POST helper ── */
    function apiPost(url, extraData, onSuccess, onError) {
        var body = new FormData();
        body.append('_token', CSRF);
        body.append('mode', 'produksi');
        if (extraData) {
            Object.keys(extraData).forEach(function (k) { body.append(k, extraData[k]); });
        }
        fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body: body })
        .then(function (r) {
            return r.json().then(function (d) { return { ok: r.ok, data: d }; });
        })
        .then(function (r) {
            if (r.ok && r.data.success) { onSuccess(r.data); }
            else { onError(r.data.error || 'Terjadi kesalahan.'); }
        })
        .catch(function () { onError('Koneksi gagal.'); });
    }

    /* ── Karyawan list ── */
    function loadKaryawan() {
        fetch(ROUTES.karyawan, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF } })
        .then(function (r) { return r.json(); })
        .then(function (data) { karyawanList = data; })
        .catch(function (e) { console.warn('loadKaryawan error', e); });
    }

    /* ── Modal Pilih Karyawan ── */
    function openPilihKaryawan(action, orderId, noAntrian, operatorLamaNama) {
        pendingOrder = { id: orderId, no: noAntrian, action: action };

        var title    = document.getElementById('pkModalTitle');
        var subtitle = document.getElementById('pkModalSubtitle');
        var listEl   = document.getElementById('pkKaryawanList');

        if (action === 'mulai') {
            title.textContent    = 'Siapa yang mengerjakan?';
            subtitle.textContent = 'Antrian #' + noAntrian;
        } else {
            title.textContent    = '🔄 Ganti Operator';
            subtitle.textContent = 'Antrian #' + noAntrian + ' · sebelumnya: ' + (operatorLamaNama || '–');
        }

        if (karyawanList.length === 0) {
            listEl.innerHTML = '<p class="text-center text-muted">Belum ada karyawan aktif di cabang ini.</p>';
        } else {
            listEl.innerHTML = karyawanList.map(function (k) {
                var jabatan = k.jabatan ? '<small class="text-muted d-block">' + escHtml(k.jabatan) + '</small>' : '';
                return '<button class="btn-pilih-karyawan" onclick="pilihKaryawan(' + k.id + ')">'
                     + '👤 ' + escHtml(k.nama_lengkap) + jabatan
                     + '</button>';
            }).join('');
        }

        bootstrap.Modal.getInstance(document.getElementById('modalPilihKaryawan'))?.hide();
        new bootstrap.Modal(document.getElementById('modalPilihKaryawan')).show();
    }

    window.pilihKaryawan = function (karyawanId) {
        if (!pendingOrder) return;
        var action = pendingOrder.action;

        // Tutup modal pilih karyawan
        bootstrap.Modal.getInstance(document.getElementById('modalPilihKaryawan'))?.hide();

        if (action === 'mulai') {
            apiPost(
                ROUTES.mulai.replace('{id}', pendingOrder.id),
                { karyawan_id: karyawanId },
                function (data) { showToast(data.message, 'success'); fetchData(); },
                function (err)  { showToast(err, 'error'); }
            );
        } else if (action === 'ambilAlih') {
            apiPost(
                ROUTES.ambilAlih.replace('{id}', pendingOrder.id),
                { karyawan_id: karyawanId },
                function (data) { showToast(data.message, 'success'); fetchData(); },
                function (err)  { showToast(err, 'error'); }
            );
        }
        pendingOrder = null;
    };

    /* ── Action handlers ── */
    window.konfirmasiMulai = function (orderId, noAntrian) {
        openPilihKaryawan('mulai', orderId, noAntrian, null);
    };

    window.konfirmasiGantiOperator = function (orderId, noAntrian, operatorLamaNama) {
        openPilihKaryawan('ambilAlih', orderId, noAntrian, operatorLamaNama);
    };

    window.konfirmasiSelesai = function (orderId, noAntrian) {
        pendingOrder = { id: orderId, no: noAntrian, action: 'selesai' };
        document.getElementById('modalSelesaiNo').textContent = '#' + noAntrian;
        new bootstrap.Modal(document.getElementById('modalSelesai')).show();
    };

    window.doSelesai = function () {
        if (!pendingOrder) return;
        var btn = document.getElementById('btnSelesaiConfirm');
        btn.disabled = true;
        apiPost(
            ROUTES.selesai.replace('{id}', pendingOrder.id),
            null,
            function (data) {
                bootstrap.Modal.getInstance(document.getElementById('modalSelesai')).hide();
                showToast(data.message, 'success');
                fetchData();
                btn.disabled = false;
                pendingOrder = null;
            },
            function (err) {
                bootstrap.Modal.getInstance(document.getElementById('modalSelesai')).hide();
                showToast(err, 'error');
                btn.disabled = false;
                pendingOrder = null;
            }
        );
    };

    /* ── Build card HTML ── */
    function buildCard(o) {
        var isMenunggu   = o.status === 'menunggu';
        var isDikerjakan = o.status === 'dikerjakan';

        var statusTag = isMenunggu
            ? '<span class="pm-status-tag">⏳ Menunggu</span>'
            : '<span class="pm-status-tag">🔧 Sedang Dikerjakan</span>';

        // Meta info (berat, catatan, durasi — server-side, no frontend calc)
        var meta = '';
        if (o.berat_daging_kg)  meta += '<span>⚖️ ' + escHtml(o.berat_daging_kg) + '</span> ';
        if (o.catatan_produksi) meta += '<span>📝 ' + escHtml(o.catatan_produksi) + '</span>';
        if (isMenunggu && o.durasi_menunggu_menit !== null) {
            meta += '<br><span class="text-warning-emphasis">🕐 Menunggu ' + durLabel(o.durasi_menunggu_menit) + '</span>';
        }

        // Action block
        var actionHtml = '';
        if (isMenunggu) {
            actionHtml = '<button class="btn-produksi btn-mulai" onclick="konfirmasiMulai(' + o.id + ',\'' + escHtml(o.nomor_antrian_pad) + '\')">'
                       + '▶ MULAI KERJAKAN</button>';
        } else if (isDikerjakan) {
            var infoHtml = '';
            if (o.dikerjakan_oleh_nama) {
                infoHtml = '<div class="pm-dikerjakan-info">'
                         + '👤 Dikerjakan oleh: <strong>' + escHtml(o.dikerjakan_oleh_nama) + '</strong>';
                if (o.waktu_mulai_label)  infoHtml += ' · mulai ' + o.waktu_mulai_label;
                if (o.durasi_kerja_menit !== null) infoHtml += ' <span style="color:#4ade80">(' + durLabel(o.durasi_kerja_menit) + ')</span>';
                infoHtml += '</div>';
            }

            var escNama = escHtml(o.dikerjakan_oleh_nama || '');
            actionHtml = infoHtml
                       + '<button class="btn-produksi btn-selesai" onclick="konfirmasiSelesai(' + o.id + ',\'' + escHtml(o.nomor_antrian_pad) + '\')">'
                       + '✅ SELESAIKAN</button>'
                       + '<button class="btn-ganti-op" onclick="konfirmasiGantiOperator(' + o.id + ',\'' + escHtml(o.nomor_antrian_pad) + '\',\'' + escNama + '\')">'
                       + '🔄 Ganti Operator</button>';
        }

        return '<div class="pm-card status-' + o.status + '" data-id="' + o.id + '">'
             + '<div class="pm-card-body">'
             + '<div class="pm-card-inner">'
             + '<div class="pm-no-antrian">#' + escHtml(o.nomor_antrian_pad) + '</div>'
             + '<div class="pm-card-info">'
             + statusTag
             + '<div class="pm-nama">' + escHtml(o.nama_pelanggan) + '</div>'
             + '<div class="pm-meta">' + meta + '</div>'
             + '</div>'
             + '</div>'
             + '<div class="pm-card-action" style="margin-top:12px;">' + actionHtml + '</div>'
             + '</div>'
             + '</div>';
    }

    /* ── Fetch antrian data ── */
    function fetchData() {
        fetch(ROUTES.data, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF } })
        .then(function (r) { return r.json(); })
        .then(function (resp) {
            var newIds = new Set(resp.orders.map(function (o) { return o.id; }));
            var hasNew = false;
            newIds.forEach(function (id) { if (!knownIds.has(id)) hasNew = true; });
            if (hasNew && knownIds.size > 0) playBell();
            knownIds = newIds;

            // Render cards
            var list = document.getElementById('pmCardList');
            if (!resp.orders || resp.orders.length === 0) {
                list.innerHTML = '<div class="pm-empty"><span class="pm-empty-icon">🎉</span><p>Tidak ada antrian aktif saat ini.</p></div>';
            } else {
                list.innerHTML = resp.orders.map(buildCard).join('');
            }

            // Stats
            document.getElementById('statMenunggu').textContent   = resp.menunggu;
            document.getElementById('statDikerjakan').textContent = resp.dikerjakan;
            var now = new Date();
            document.getElementById('statLastRefresh').textContent =
                'Update: ' + now.getHours().toString().padStart(2,'0') + ':' +
                now.getMinutes().toString().padStart(2,'0') + ':' +
                now.getSeconds().toString().padStart(2,'0');
        })
        .catch(function (e) { console.warn('fetchData error', e); });
    }

    /* ── Realtime indicator helper ── */
    function setRealtimeStatus(connected) {
        pusherConnected = connected;
        var el  = document.getElementById('realtimeIndicator');
        var dot = document.getElementById('realtimeDot');
        var lbl = document.getElementById('realtimeLabel');
        if (!el) return;
        el.style.display = '';
        if (connected) {
            dot.style.background = '#4ade80';
            lbl.textContent = 'Real-time aktif';
        } else {
            dot.style.background = '#f87171';
            lbl.textContent = 'Polling fallback';
        }
        // Sesuaikan interval polling
        clearInterval(refreshTimer);
        if (!document.hidden) {
            var interval = connected ? REFRESH_INTERVAL_CONNECTED : REFRESH_INTERVAL_DISCONNECTED;
            refreshTimer = setInterval(fetchData, interval);
        }
    }

    /* ── Pusher / Echo real-time ── */
    function initEcho() {
        if (!PUSHER_KEY || typeof Echo === 'undefined') return;

        var echo = new Echo({
            broadcaster:  'pusher',
            key:          PUSHER_KEY,
            cluster:      PUSHER_CLUSTER,
            forceTLS:     true,
            authEndpoint: '/broadcasting/auth',
            auth: { headers: { 'X-CSRF-TOKEN': CSRF } },
        });

        echo.private('antrian.' + CABANG_ID)
            .listen('.antrian.created', function (data) {
                if (!knownIds.has(data.id)) {
                    knownIds.add(data.id);
                    // Tambahkan card baru di atas list, play bell
                    var list = document.getElementById('pmCardList');
                    var empty = list.querySelector('.pm-empty');
                    if (empty) list.innerHTML = '';
                    var tmp = document.createElement('div');
                    tmp.innerHTML = buildCard(data);
                    list.insertBefore(tmp.firstChild, list.firstChild);
                    updateStats();
                    playBell();
                }
            })
            .listen('.antrian.updated', function (data) {
                // Ganti card yang ada dengan data terbaru
                var existing = document.querySelector('[data-id="' + data.id + '"]');
                if (existing) {
                    var tmp = document.createElement('div');
                    tmp.innerHTML = buildCard(data);
                    existing.replaceWith(tmp.firstChild);
                } else {
                    // Belum ada di list (edge case: muncul dari status lain) — fetch ulang
                    fetchData();
                }
                updateStats();
            })
            .listen('.antrian.removed', function (data) {
                var existing = document.querySelector('[data-id="' + data.order_id + '"]');
                if (existing) {
                    existing.remove();
                    knownIds.delete(data.order_id);
                }
                var list = document.getElementById('pmCardList');
                if (list && !list.querySelector('.pm-card')) {
                    list.innerHTML = '<div class="pm-empty"><span class="pm-empty-icon">🎉</span><p>Tidak ada antrian aktif saat ini.</p></div>';
                }
                updateStats();
            });

        echo.connector.pusher.connection.bind('connected', function () {
            setRealtimeStatus(true);
        });
        echo.connector.pusher.connection.bind('disconnected', function () {
            setRealtimeStatus(false);
        });
        echo.connector.pusher.connection.bind('unavailable', function () {
            setRealtimeStatus(false);
        });
    }

    /* Hitung ulang stats dari DOM */
    function updateStats() {
        var cards      = document.querySelectorAll('.pm-card');
        var menunggu   = document.querySelectorAll('.pm-card.status-menunggu').length;
        var dikerjakan = document.querySelectorAll('.pm-card.status-dikerjakan').length;
        document.getElementById('statMenunggu').textContent   = menunggu;
        document.getElementById('statDikerjakan').textContent = dikerjakan;
    }

    /* ── Init ── */
    document.body.classList.add('produksi-mode');
    updateSoundBtn();
    loadKaryawan();
    fetchData();

    // Polling: mulai dengan interval disconnected, Echo akan ubah jadi 30s saat connected
    refreshTimer = setInterval(fetchData, REFRESH_INTERVAL_DISCONNECTED);
    setInterval(loadKaryawan, KARYAWAN_REFRESH_INTERVAL);

    // Pusher real-time (jika key tersedia)
    initEcho();

    // Pause refresh saat tab tidak aktif, resume saat kembali
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            clearInterval(refreshTimer);
        } else {
            fetchData();
            var interval = pusherConnected ? REFRESH_INTERVAL_CONNECTED : REFRESH_INTERVAL_DISCONNECTED;
            refreshTimer = setInterval(fetchData, interval);
        }
    });

})();
</script>
@endpush
