<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrian Produksi — {{ $cabang->nama_cabang }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%; width: 100%;
            background: #0f172a; color: #f1f5f9;
            font-family: 'Segoe UI', system-ui, sans-serif;
            overflow: hidden;
        }

        /* ====== LAYOUT ====== */
        .screen {
            display: flex; flex-direction: column;
            height: 100vh;
            padding: 14px 16px 0;
            gap: 10px;
        }

        /* ====== HEADER ====== */
        .header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 16px;
            background: #1e293b;
            border-radius: 14px;
            flex-shrink: 0;
        }
        .header-left {
            display: flex; align-items: center; gap: 16px;
        }
        .header-logo {
            max-height: 56px; max-width: 140px;
            object-fit: contain; flex-shrink: 0;
        }
        .header-title {
            font-size: clamp(1.1rem, 2.2vw, 1.6rem);
            font-weight: 800; color: #fbbf24;
            line-height: 1.1;
        }
        .header-cabang {
            font-size: clamp(0.75rem, 1.5vw, 1rem);
            color: #94a3b8; margin-top: 2px;
        }
        .header-right { text-align: right; flex-shrink: 0; }
        .clock {
            font-size: clamp(1.6rem, 3.2vw, 2.6rem);
            font-weight: 700; color: #34d399;
            font-variant-numeric: tabular-nums;
            font-family: 'Courier New', monospace;
            line-height: 1;
        }
        .date-text {
            font-size: clamp(0.65rem, 1.2vw, 0.9rem);
            color: #64748b; margin-top: 3px;
        }

        /* ====== 4 COLUMNS ====== */
        .columns {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 10px;
            flex: 1;
            min-height: 0;
        }

        .col {
            background: #1e293b;
            border-radius: 14px;
            display: flex; flex-direction: column;
            overflow: hidden;
        }
        .col-header {
            padding: 10px 14px;
            font-size: clamp(0.75rem, 1.4vw, 1rem);
            font-weight: 700;
            display: flex; align-items: center; gap: 8px;
            flex-shrink: 0;
        }
        .col-header .dot {
            width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
        }
        .col-header .badge-count {
            margin-left: auto;
            border-radius: 20px; padding: 2px 8px;
            font-size: 0.75em; font-weight: 700;
        }

        /* Kolom Menunggu */
        .col-menunggu .col-header  { background: #1a1f2e; color: #94a3b8; }
        .col-menunggu .dot         { background: #475569; }
        .col-menunggu .badge-count { background: #334155; }
        .col-menunggu .antrian-number { color: #94a3b8; }

        /* Kolom Dikerjakan */
        .col-dikerjakan .col-header { background: #451a03; color: #fbbf24; }
        .col-dikerjakan .dot        { background: #fbbf24; }
        .col-dikerjakan .badge-count { background: #78350f; }
        .col-dikerjakan .antrian-number { color: #fbbf24; }

        /* Kolom Selesai */
        .col-selesai .col-header   { background: #052e16; color: #34d399; }
        .col-selesai .dot          { background: #34d399; }
        .col-selesai .badge-count  { background: #14532d; }
        .col-selesai .antrian-number { color: #34d399; }

        /* Kolom Disimpan */
        .col-disimpan .col-header  { background: #0c1a2e; color: #60a5fa; }
        .col-disimpan .dot         { background: #60a5fa; }
        .col-disimpan .badge-count { background: #1e3a5f; }
        .col-disimpan .antrian-number { color: #60a5fa; }

        .col-body {
            flex: 1; overflow-y: auto; padding: 8px;
            scrollbar-width: thin; scrollbar-color: #334155 transparent;
        }
        .col-body::-webkit-scrollbar { width: 4px; }
        .col-body::-webkit-scrollbar-thumb { background: #334155; border-radius: 2px; }

        /* ====== ANTRIAN CARD ====== */
        .antrian-card {
            background: #0f172a;
            border-radius: 10px;
            padding: 8px 10px;
            margin-bottom: 7px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .antrian-card.pulse {
            animation: pulseGlow 2s ease-in-out infinite;
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(52, 211, 153, 0); }
            50%       { box-shadow: 0 0 12px 3px rgba(52, 211, 153, 0.25); }
        }
        .antrian-number {
            font-size: clamp(1.8rem, 3.5vw, 2.8rem);
            font-weight: 900;
            line-height: 1;
            min-width: 60px;
            text-align: center;
            flex-shrink: 0;
            font-variant-numeric: tabular-nums;
        }
        .antrian-info { flex: 1; min-width: 0; }
        .antrian-pelanggan {
            font-size: clamp(0.8rem, 1.4vw, 1rem);
            font-weight: 700;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            color: #f1f5f9;
            margin-bottom: 2px;
        }
        .antrian-order {
            font-size: clamp(0.6rem, 1vw, 0.72rem);
            color: #475569;
            margin-bottom: 3px;
        }
        .antrian-meta {
            font-size: clamp(0.62rem, 1.05vw, 0.78rem);
            color: #64748b;
            line-height: 1.5;
        }
        .antrian-meta .row-meta {
            display: flex; align-items: center; gap: 4px;
        }
        .antrian-meta .icon { font-style: normal; }
        .rak-highlight {
            display: inline-block;
            background: #1d4ed8;
            color: #eff6ff;
            border-radius: 6px;
            padding: 1px 8px;
            font-weight: 800;
            font-size: clamp(0.72rem, 1.2vw, 0.9rem);
            border: 1px solid #3b82f6;
        }

        .empty-state {
            text-align: center; color: #334155;
            padding: 20px 8px;
            font-size: clamp(0.72rem, 1.2vw, 0.85rem);
        }

        /* ====== FOOTER ====== */
        .footer {
            flex-shrink: 0;
            background: #1e293b;
            border-radius: 12px 12px 0 0;
        }
        .footer-stats {
            display: flex; align-items: center; gap: 16px;
            padding: 7px 16px;
            font-size: clamp(0.65rem, 1.1vw, 0.8rem);
            color: #64748b;
            border-bottom: 1px solid #334155;
            flex-wrap: wrap;
        }
        .footer-stats .stat { display: flex; align-items: center; gap: 4px; }
        .footer-stats .stat-val { color: #94a3b8; font-weight: 600; }
        .online-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #34d399; display: inline-block;
            animation: blink 2s ease-in-out infinite;
        }
        .offline-dot { width: 8px; height: 8px; border-radius: 50%; background: #ef4444; display: inline-block; }
        .realtime-dot {
            width: 8px; height: 8px; border-radius: 50%; display: inline-block;
        }
        .realtime-dot.connected    { background: #4ade80; animation: blink 2s ease-in-out infinite; }
        .realtime-dot.disconnected { background: #f59e0b; }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.3; }
        }

        /* ====== RUNNING TEXT ====== */
        .running-text-bar {
            overflow: hidden;
            white-space: nowrap;
            padding: 8px 0;
            background: linear-gradient(90deg, #b45309 0%, #dc2626 100%);
            border-radius: 0 0 0 0;
        }
        .running-text-inner {
            display: inline-block;
            padding-left: 100%;
            animation: marquee 30s linear infinite;
            font-size: clamp(0.85rem, 1.5vw, 1.1rem);
            font-weight: 700;
            color: #fff;
            white-space: nowrap;
        }
        .running-text-inner:hover { animation-play-state: paused; }
        @keyframes marquee {
            0%   { transform: translateX(0); }
            100% { transform: translateX(-100%); }
        }
    </style>
</head>
<body>
<div class="screen">

    <!-- HEADER -->
    <div class="header">
        <div class="header-left">
            @if($brand->adaLogoUpload())
            <img src="{{ $brand->logoUrl() }}" alt="Logo" class="header-logo">
            @endif
            <div>
                <div class="header-title">{{ mb_strtoupper($brand->nama()) }}</div>
                <div class="header-cabang">{{ $cabang->nama_cabang }}</div>
            </div>
        </div>
        <div class="header-right">
            <div class="clock" id="clock">--:--:--</div>
            <div class="date-text" id="dateText">--</div>
        </div>
    </div>

    <!-- 4 COLUMNS -->
    <div class="columns">

        <!-- Menunggu -->
        <div class="col col-menunggu">
            <div class="col-header">
                <span class="dot"></span>
                <span>⏳ MENUNGGU</span>
                <span class="badge-count" id="badge-menunggu">0</span>
            </div>
            <div class="col-body" id="list-menunggu">
                <div class="empty-state">Antrian kosong</div>
            </div>
        </div>

        <!-- Dikerjakan -->
        <div class="col col-dikerjakan">
            <div class="col-header">
                <span class="dot"></span>
                <span>🔥 DIKERJAKAN</span>
                <span class="badge-count" id="badge-dikerjakan">0</span>
            </div>
            <div class="col-body" id="list-dikerjakan">
                <div class="empty-state">Tidak ada</div>
            </div>
        </div>

        <!-- Siap Diambil -->
        <div class="col col-selesai">
            <div class="col-header">
                <span class="dot"></span>
                <span>✨ SIAP DIAMBIL</span>
                <span class="badge-count" id="badge-selesai">0</span>
            </div>
            <div class="col-body" id="list-selesai">
                <div class="empty-state">Tidak ada</div>
            </div>
        </div>

        <!-- Di Rak -->
        <div class="col col-disimpan">
            <div class="col-header">
                <span class="dot"></span>
                <span>📦 DI RAK</span>
                <span class="badge-count" id="badge-disimpan">0</span>
            </div>
            <div class="col-body" id="list-disimpan">
                <div class="empty-state">Tidak ada</div>
            </div>
        </div>

    </div>

    <!-- FOOTER -->
    <div class="footer">
        <div class="footer-stats">
            <div class="stat">
                <span id="onlineDot" class="online-dot"></span>
                <span id="onlineLabel" class="stat-val">Online</span>
            </div>
            <div class="stat" id="realtimeStat" style="display:none;">
                <span id="realtimeDot" class="realtime-dot disconnected"></span>
                <span id="realtimeLabel" class="stat-val" style="color:#64748b;">Menghubungkan...</span>
            </div>
            <div class="stat">📊 Total hari ini: <span class="stat-val" id="stat-total">0</span></div>
            <div class="stat">✅ Selesai: <span class="stat-val" id="stat-selesai">0</span></div>
            <div class="stat" style="margin-left:auto">🕐 Update: <span class="stat-val" id="lastUpdate">--</span></div>
        </div>
        @if($cabang->running_text_aktif && $cabang->running_text)
        <div class="running-text-bar">
            <span class="running-text-inner">
                📢&nbsp;&nbsp;{{ $cabang->running_text }}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;📢&nbsp;&nbsp;{{ $cabang->running_text }}
            </span>
        </div>
        @endif
    </div>

</div>

{{-- Pusher CDN — hanya load kalau key tersedia (injected via Blade) --}}
@php $pusherKey     = config('broadcasting.connections.pusher.key'); @endphp
@php $pusherCluster = config('broadcasting.connections.pusher.options.cluster', 'ap1'); @endphp
@if($pusherKey)
<script src="https://js.pusher.com/8.4.0-rc2/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js"></script>
@endif

<script>
(function () {
'use strict';

// ── Config ──
const DATA_URL       = '{{ route('antrian.data', $cabang) }}';
const CABANG_ID      = {{ $cabang->id }};
const PUSHER_KEY     = @json($pusherKey ?? '');
const PUSHER_CLUSTER = @json($pusherCluster);
const POLL_CONNECTED    = 30000;   // 30 detik saat Pusher connected
const POLL_DISCONNECTED = 5000;    // 5 detik fallback (sama seperti sebelumnya)
const AUTO_RELOAD_MS    = 6 * 60 * 60 * 1000; // reload 6 jam sekali (anti memory leak)

// ── State ──
let prevSelesaiNomors = [];
let isOnline          = true;
let fetchInFlight     = false;    // guard — cegah concurrent fetch
let debounceTimer     = null;     // debounce untuk Pusher-triggered fetch
let pollTimer         = null;
let pusherConnected   = false;

// ── Jam lokal — update setiap detik ──
function tickClock() {
    const now = new Date();
    const hh  = String(now.getHours()).padStart(2, '0');
    const mm  = String(now.getMinutes()).padStart(2, '0');
    const ss  = String(now.getSeconds()).padStart(2, '0');
    document.getElementById('clock').textContent = `${hh}:${mm}:${ss}`;
}
setInterval(tickClock, 1000);
tickClock();

// ── Render functions (tidak diubah) ──
function escHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderMenunggu(items) {
    if (!items || items.length === 0) return '<div class="empty-state">Antrian kosong</div>';
    return items.map(item => `
        <div class="antrian-card">
            <div class="antrian-number">${escHtml(item.nomor)}</div>
            <div class="antrian-info">
                <div class="antrian-pelanggan">${escHtml(item.pelanggan)}</div>
                ${item.nomor_order ? `<div class="antrian-order">${escHtml(item.nomor_order)}</div>` : ''}
                <div class="antrian-meta">
                    ${item.berat ? `<div class="row-meta"><span class="icon">🥩</span> ${escHtml(item.berat)}</div>` : ''}
                </div>
            </div>
        </div>`).join('');
}

function renderDikerjakan(items) {
    if (!items || items.length === 0) return '<div class="empty-state">Tidak ada</div>';
    return items.map(item => `
        <div class="antrian-card">
            <div class="antrian-number">${escHtml(item.nomor)}</div>
            <div class="antrian-info">
                <div class="antrian-pelanggan">${escHtml(item.pelanggan)}</div>
                ${item.nomor_order ? `<div class="antrian-order">${escHtml(item.nomor_order)}</div>` : ''}
                <div class="antrian-meta">
                    ${item.berat ? `<div class="row-meta"><span class="icon">🥩</span> ${escHtml(item.berat)}</div>` : ''}
                    ${item.mulai ? `<div class="row-meta"><span class="icon">⏱️</span> Mulai: ${escHtml(item.mulai)}</div>` : ''}
                    ${item.operator ? `<div class="row-meta"><span class="icon">👷</span> ${escHtml(item.operator)}</div>` : ''}
                </div>
            </div>
        </div>`).join('');
}

function renderSelesai(items) {
    if (!items || items.length === 0) return '<div class="empty-state">Tidak ada</div>';
    return items.map(item => `
        <div class="antrian-card pulse">
            <div class="antrian-number">${escHtml(item.nomor)}</div>
            <div class="antrian-info">
                <div class="antrian-pelanggan">${escHtml(item.pelanggan)}</div>
                ${item.nomor_order ? `<div class="antrian-order">${escHtml(item.nomor_order)}</div>` : ''}
                <div class="antrian-meta">
                    ${item.berat ? `<div class="row-meta"><span class="icon">🥩</span> ${escHtml(item.berat)}</div>` : ''}
                    ${item.mulai ? `<div class="row-meta"><span class="icon">⏱️</span> Mulai: ${escHtml(item.mulai)}</div>` : ''}
                    ${item.selesai ? `<div class="row-meta"><span class="icon">✅</span> Selesai: ${escHtml(item.selesai)}</div>` : ''}
                </div>
            </div>
        </div>`).join('');
}

function renderDisimpan(items) {
    if (!items || items.length === 0) return '<div class="empty-state">Tidak ada</div>';
    return items.map(item => `
        <div class="antrian-card">
            <div class="antrian-number">${escHtml(item.nomor)}</div>
            <div class="antrian-info">
                <div class="antrian-pelanggan">${escHtml(item.pelanggan)}</div>
                ${item.nomor_order ? `<div class="antrian-order">${escHtml(item.nomor_order)}</div>` : ''}
                <div class="antrian-meta">
                    ${item.berat ? `<div class="row-meta"><span class="icon">🥩</span> ${escHtml(item.berat)}</div>` : ''}
                    ${item.lokasi_rak ? `<div class="row-meta"><span class="icon">📦</span> Rak: <span class="rak-highlight">${escHtml(item.lokasi_rak)}</span></div>` : ''}
                    ${item.mulai ? `<div class="row-meta"><span class="icon">⏱️</span> Mulai: ${escHtml(item.mulai)}</div>` : ''}
                    ${item.selesai ? `<div class="row-meta"><span class="icon">✅</span> Selesai: ${escHtml(item.selesai)}</div>` : ''}
                    ${item.disimpan_jam ? `<div class="row-meta"><span class="icon">📦</span> Disimpan: ${escHtml(item.disimpan_jam)}</div>` : ''}
                </div>
            </div>
        </div>`).join('');
}

function setBadge(id, count) {
    document.getElementById(id).textContent = count;
}

// ── fetchData — guard concurrent + deteksi ding ──
async function fetchData() {
    if (fetchInFlight) return;
    fetchInFlight = true;
    try {
        const resp = await fetch(DATA_URL, { cache: 'no-store' });
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        const data = await resp.json();

        if (data.tanggal) {
            document.getElementById('dateText').textContent = data.tanggal;
        }

        document.getElementById('list-menunggu').innerHTML   = renderMenunggu(data.menunggu);
        document.getElementById('list-dikerjakan').innerHTML = renderDikerjakan(data.dikerjakan);
        document.getElementById('list-selesai').innerHTML    = renderSelesai(data.selesai);
        document.getElementById('list-disimpan').innerHTML   = renderDisimpan(data.disimpan);

        setBadge('badge-menunggu',   data.menunggu.length);
        setBadge('badge-dikerjakan', data.dikerjakan.length);
        setBadge('badge-selesai',    data.selesai.length);
        setBadge('badge-disimpan',   data.disimpan.length);

        const totalHariIni = data.menunggu.length + data.dikerjakan.length +
                             data.selesai.length  + data.disimpan.length;
        document.getElementById('stat-total').textContent   = totalHariIni;
        document.getElementById('stat-selesai').textContent = data.selesai.length + data.disimpan.length;

        const now = new Date();
        document.getElementById('lastUpdate').textContent =
            String(now.getHours()).padStart(2,'0') + ':' +
            String(now.getMinutes()).padStart(2,'0') + ':' +
            String(now.getSeconds()).padStart(2,'0');

        // ── Ding logic: bandingkan selesai lama vs baru ──
        const curSelesaiNomors = data.selesai.map(i => i.nomor);
        const newSelesai = curSelesaiNomors.filter(n => !prevSelesaiNomors.includes(n));
        if (newSelesai.length > 0) playDing();
        prevSelesaiNomors = curSelesaiNomors;

        if (!isOnline) {
            isOnline = true;
            document.getElementById('onlineDot').className     = 'online-dot';
            document.getElementById('onlineLabel').textContent = 'Online';
        }
    } catch (e) {
        isOnline = false;
        document.getElementById('onlineDot').className     = 'offline-dot';
        document.getElementById('onlineLabel').textContent = 'Offline';
    } finally {
        fetchInFlight = false;
    }
}

// ── Debounced fetch — Pusher events trigger ini ──
// Beberapa event bisa tiba dalam milidetik, debounce 350ms biar satu request saja
function fetchDataDebounced() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fetchData, 350);
}

// ── Polling — adaptive interval ──
function startPoll(interval) {
    clearInterval(pollTimer);
    pollTimer = setInterval(fetchData, interval);
}

// ── Realtime indicator ──
function setRealtimeIndicator(connected) {
    pusherConnected = connected;
    const stat  = document.getElementById('realtimeStat');
    const dot   = document.getElementById('realtimeDot');
    const label = document.getElementById('realtimeLabel');
    if (!stat) return;
    stat.style.display = '';
    if (connected) {
        dot.className        = 'realtime-dot connected';
        label.textContent    = 'Real-time aktif';
        label.style.color    = '#4ade80';
        startPoll(POLL_CONNECTED);
    } else {
        dot.className        = 'realtime-dot disconnected';
        label.textContent    = 'Polling fallback';
        label.style.color    = '#f59e0b';
        startPoll(POLL_DISCONNECTED);
    }
}

// ── playDing (tidak diubah) ──
function playDing() {
    try {
        const ctx  = new (window.AudioContext || window.webkitAudioContext)();
        const osc  = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.3);
        gain.gain.setValueAtTime(0.4, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.6);
    } catch (_) {}
}

// ── Pusher Echo init ──
function initEcho() {
    if (!PUSHER_KEY || typeof Echo === 'undefined') {
        // Pusher tidak tersedia — tetap pakai polling 5 detik
        setRealtimeIndicator(false);
        return;
    }

    const echo = new Echo({
        broadcaster: 'pusher',
        key:         PUSHER_KEY,
        cluster:     PUSHER_CLUSTER,
        forceTLS:    true,
        // Public channel — tidak perlu authEndpoint
    });

    // Subscribe public channel tv-antrian.{cabang_id}
    echo.channel('tv-antrian.' + CABANG_ID)
        .listen('.antrian.created', fetchDataDebounced)
        .listen('.antrian.updated', fetchDataDebounced)
        .listen('.antrian.removed', fetchDataDebounced);

    echo.connector.pusher.connection.bind('connected', function () {
        setRealtimeIndicator(true);
    });

    echo.connector.pusher.connection.bind('disconnected', function () {
        setRealtimeIndicator(false);
    });

    echo.connector.pusher.connection.bind('unavailable', function () {
        setRealtimeIndicator(false);
    });
}

// ── Inisialisasi ──
fetchData();
startPoll(POLL_DISCONNECTED);  // mulai dengan 5s, Echo akan ubah ke 30s saat connected
initEcho();

// Auto-reload setiap 6 jam (anti memory leak browser)
setTimeout(function () { window.location.reload(); }, AUTO_RELOAD_MS);

})();
</script>
</body>
</html>
