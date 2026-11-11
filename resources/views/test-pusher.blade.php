@extends('layouts.app')

@section('title', 'Test Pusher Connection')

@push('styles')
<style>
.log-area {
    background: #0f172a; color: #e2e8f0;
    font-family: 'Courier New', monospace; font-size: 0.85rem;
    padding: 16px; border-radius: 8px;
    min-height: 200px; max-height: 400px; overflow-y: auto;
    border: 1px solid #334155;
}
.log-entry { padding: 4px 0; border-bottom: 1px solid #1e293b; }
.log-entry .ts { color: #64748b; margin-right: 8px; }
.log-entry.received { color: #4ade80; }
.log-entry.info     { color: #60a5fa; }
.log-entry.err      { color: #f87171; }
.status-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; }
.dot-connecting { background: #fbbf24; animation: pulse 1s infinite; }
.dot-connected  { background: #4ade80; }
.dot-error      { background: #f87171; }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: .4; } }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">

            <h5 class="fw-bold mb-1">🔌 Test Pusher Connection</h5>
            <p class="text-muted small mb-4">
                Halaman ini untuk memverifikasi bahwa broadcasting Pusher berjalan dengan benar.
                <strong>Hapus halaman ini setelah test selesai.</strong>
            </p>

            {{-- Status card --}}
            <div class="card mb-3">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="status-dot dot-connecting" id="statusDot"></span>
                    <div>
                        <div class="fw-semibold" id="statusText">Menghubungkan ke Pusher...</div>
                        <div class="text-muted small" id="statusDetail">Inisialisasi Echo...</div>
                    </div>
                    <div class="ms-auto">
                        <span class="badge bg-secondary" id="channelBadge">–</span>
                    </div>
                </div>
            </div>

            {{-- Config info --}}
            <div class="card mb-3">
                <div class="card-header py-2"><small class="fw-semibold text-muted">KONFIGURASI PUSHER</small></div>
                <div class="card-body py-2">
                    <div class="row g-2 small">
                        <div class="col-6 col-md-3"><span class="text-muted">App Key:</span><br><code>{{ substr(config('broadcasting.connections.pusher.key') ?? '(belum set)', 0, 8) }}...</code></div>
                        <div class="col-6 col-md-3"><span class="text-muted">Cluster:</span><br><code>{{ config('broadcasting.connections.pusher.options.cluster') ?? '(belum set)' }}</code></div>
                        <div class="col-6 col-md-3"><span class="text-muted">Connection:</span><br><code>{{ config('broadcasting.default') }}</code></div>
                        <div class="col-6 col-md-3"><span class="text-muted">Channel:</span><br><code>test-pusher</code></div>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="d-flex gap-2 mb-3">
                <button type="button" class="btn btn-success" id="btnFire" onclick="fireEvent()">
                    <i class="bi bi-lightning-fill me-1"></i>Fire Event
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="clearLog()">
                    <i class="bi bi-trash me-1"></i>Clear Log
                </button>
            </div>

            {{-- Log area --}}
            <div class="card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <small class="fw-semibold text-muted">EVENT LOG</small>
                    <span class="badge bg-primary" id="eventCount">0 events</span>
                </div>
                <div class="card-body p-0">
                    <div class="log-area" id="logArea">
                        <div class="log-entry info"><span class="ts">–</span>Menunggu koneksi...</div>
                    </div>
                </div>
            </div>

            <p class="text-muted small mt-3">
                <i class="bi bi-info-circle me-1"></i>
                Buka <strong>Pusher Dashboard → Debug Console</strong> untuk melihat event secara real-time dari sisi server.
                Buka halaman ini di 2 tab/device — fire dari satu, tab lain ikut update.
            </p>

        </div>
    </div>
</div>
@endsection

@push('scripts')
{{-- Pusher JS & Laravel Echo via CDN --}}
<script src="https://js.pusher.com/8.4.0-rc2/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js"></script>

<script>
(function () {
    'use strict';

    const PUSHER_KEY     = @json(config('broadcasting.connections.pusher.key') ?? '');
    const PUSHER_CLUSTER = @json(config('broadcasting.connections.pusher.options.cluster') ?? 'ap1');
    const FIRE_URL       = '{{ route("test.pusher.fire") }}';
    const CSRF           = document.querySelector('meta[name="csrf-token"]').content;

    let eventCount = 0;

    /* ── DOM refs ── */
    const $dot    = document.getElementById('statusDot');
    const $text   = document.getElementById('statusText');
    const $detail = document.getElementById('statusDetail');
    const $badge  = document.getElementById('channelBadge');
    const $log    = document.getElementById('logArea');
    const $count  = document.getElementById('eventCount');

    function ts() {
        return new Date().toLocaleTimeString('id-ID', { hour12: false });
    }

    function addLog(msg, type) {
        var entry = document.createElement('div');
        entry.className = 'log-entry ' + (type || 'info');
        entry.innerHTML = '<span class="ts">[' + ts() + ']</span>' + msg;
        $log.appendChild(entry);
        $log.scrollTop = $log.scrollHeight;
    }

    function setStatus(state, text, detail) {
        $dot.className  = 'status-dot dot-' + state;
        $text.textContent   = text;
        $detail.textContent = detail || '';
    }

    /* ── Init Echo ── */
    if (!PUSHER_KEY) {
        setStatus('error', 'Konfigurasi Pusher tidak ditemukan', 'Pastikan .env sudah di-set dan config:cache dijalankan ulang.');
        addLog('ERROR: PUSHER_APP_KEY kosong — cek .env dan jalankan php artisan config:clear', 'err');
        return;
    }

    Pusher.logToConsole = true;

    var echo = new Echo({
        broadcaster:  'pusher',
        key:          PUSHER_KEY,
        cluster:      PUSHER_CLUSTER,
        forceTLS:     true,
    });

    /* ── Subscribe ── */
    addLog('Menghubungkan ke Pusher (cluster: ' + PUSHER_CLUSTER + ')...', 'info');

    var channel = echo.channel('test-pusher');

    channel.listen('.test.message', function (data) {
        eventCount++;
        $count.textContent = eventCount + ' event' + (eventCount > 1 ? 's' : '');
        addLog(
            '✅ <strong>Event diterima!</strong> '
            + 'message: <em>' + data.message + '</em> | '
            + 'time: ' + data.time + ' | '
            + 'fired_by: ' + data.fired_by,
            'received'
        );
    });

    /* ── Pusher connection events ── */
    echo.connector.pusher.connection.bind('connected', function () {
        setStatus('connected', 'Terhubung ke Pusher ✓', 'Socket ID: ' + echo.socketId());
        $badge.textContent = 'test-pusher';
        $badge.className   = 'badge bg-success';
        addLog('Koneksi berhasil. Socket ID: ' + echo.socketId(), 'info');
    });

    echo.connector.pusher.connection.bind('disconnected', function () {
        setStatus('error', 'Terputus dari Pusher', 'Mencoba reconnect...');
        $badge.className = 'badge bg-danger';
        addLog('Koneksi terputus.', 'err');
    });

    echo.connector.pusher.connection.bind('error', function (err) {
        setStatus('error', 'Error koneksi Pusher', JSON.stringify(err?.error?.data || err));
        addLog('ERROR: ' + JSON.stringify(err), 'err');
    });

    /* ── Fire event ── */
    window.fireEvent = function () {
        var btn = document.getElementById('btnFire');
        btn.disabled = true;
        btn.textContent = 'Mengirim...';

        fetch(FIRE_URL, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                addLog('🚀 Event dikirim ke Pusher. Tunggu echo balik...', 'info');
            } else {
                addLog('ERROR dari server: ' + (d.error || JSON.stringify(d)), 'err');
            }
        })
        .catch(function (e) {
            addLog('Fetch error: ' + e.message, 'err');
        })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-lightning-fill me-1"></i>Fire Event';
        });
    };

    window.clearLog = function () {
        $log.innerHTML = '';
        eventCount = 0;
        $count.textContent = '0 events';
    };

})();
</script>
@endpush
