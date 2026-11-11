@extends('layouts.app')

@section('title', 'Scan Absensi Wajah')

@push('styles')
<style>
    #main-content { padding: 0 !important; }

    #scanWrap {
        display: flex;
        height: calc(100vh - 60px);
        background: #0f172a;
        color: white;
        overflow: hidden;
        position: relative;
    }

    /* ===== TIPE OVERLAY (ditampilkan pertama kali) ===== */
    #tipeOverlay {
        position: absolute; inset: 0; z-index: 50;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        padding: 1.5rem;
    }
    .tipe-title {
        text-align: center; margin-bottom: 2rem;
    }
    .tipe-title h2 { font-size: 1.4rem; font-weight: 700; margin-bottom: .3rem; }
    .tipe-title p  { color: #64748b; font-size: .9rem; }
    .tipe-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        width: 100%;
        max-width: 680px;
    }
    .tipe-btn {
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        gap: .5rem;
        padding: 1.5rem 1rem;
        border-radius: 16px;
        border: 2px solid transparent;
        cursor: pointer;
        transition: all .2s;
        font-weight: 700;
        font-size: 1.05rem;
        min-height: 110px;
        background: rgba(255,255,255,.05);
        color: white;
        text-align: center;
        line-height: 1.3;
    }
    .tipe-btn .tipe-icon { font-size: 2rem; line-height: 1; }
    .tipe-btn.masuk        { border-color: #22c55e; }
    .tipe-btn.masuk:hover  { background: rgba(34,197,94,.18); box-shadow: 0 0 24px rgba(34,197,94,.3); }
    .tipe-btn.keluar       { border-color: #60a5fa; }
    .tipe-btn.keluar:hover { background: rgba(96,165,250,.18); box-shadow: 0 0 24px rgba(96,165,250,.3); }
    .tipe-btn.lembur-masuk       { border-color: #fbbf24; }
    .tipe-btn.lembur-masuk:hover { background: rgba(251,191,36,.18); box-shadow: 0 0 24px rgba(251,191,36,.3); }
    .tipe-btn.lembur-keluar       { border-color: #fb923c; }
    .tipe-btn.lembur-keluar:hover { background: rgba(251,146,60,.18); box-shadow: 0 0 24px rgba(251,146,60,.3); }
    .tipe-btn .tipe-label { font-size: .75rem; color: #94a3b8; font-weight: 400; margin-top: 2px; }
    @media (max-width: 480px) {
        .tipe-grid { max-width: 100%; gap: .75rem; }
        .tipe-btn { min-height: 90px; padding: 1rem .75rem; font-size: .95rem; }
        .tipe-btn .tipe-icon { font-size: 1.6rem; }
    }

    /* ===== SELECTED TYPE BAR ===== */
    #selectedTypeBar {
        display: none;
        align-items: center; justify-content: space-between; gap: .5rem;
        margin-bottom: .75rem;
        background: rgba(255,255,255,.06);
        border: 1px solid rgba(255,255,255,.1);
        border-radius: 10px;
        padding: .5rem .75rem;
    }
    #selectedTypeBadge {
        font-weight: 700; font-size: .85rem;
        padding: 3px 12px; border-radius: 20px;
    }
    #btnGantiTipe {
        background: none; border: none; color: #64748b; font-size: .78rem;
        cursor: pointer; padding: 3px 6px; border-radius: 6px;
        transition: color .15s, background .15s;
    }
    #btnGantiTipe:hover { color: #e2e8f0; background: rgba(255,255,255,.08); }

    /* ===== VIDEO AREA ===== */
    #videoArea {
        flex: 1; position: relative; background: #000; min-width: 0;
    }
    #videoEl {
        width: 100%; height: 100%;
        object-fit: cover; transform: scaleX(-1);
    }
    #canvasOverlay {
        position: absolute; inset: 0; width: 100%; height: 100%;
        pointer-events: none; transform: scaleX(-1);
    }
    .scan-guide {
        position: absolute;
        top: 50%; left: 50%;
        transform: translate(-50%, -55%);
        width: min(260px, 55vw);
        aspect-ratio: 3/4;
        border: 3px solid rgba(255,255,255,.35);
        border-radius: 50%;
        pointer-events: none;
        transition: border-color .3s, box-shadow .3s;
    }
    .scan-guide.active {
        border-color: #22c55e;
        box-shadow: 0 0 30px rgba(34,197,94,.5);
    }
    #scanStatusBar {
        position: absolute;
        bottom: 1.5rem; left: 50%;
        transform: translateX(-50%);
        background: rgba(0,0,0,.75);
        backdrop-filter: blur(8px);
        padding: 8px 22px;
        border-radius: 20px;
        font-size: .9rem; font-weight: 600;
        white-space: nowrap; transition: background .25s;
        z-index: 5;
    }
    #scanStatusBar.ok   { background: rgba(34,197,94,.85); }
    #scanStatusBar.err  { background: rgba(239,68,68,.85); }
    #scanStatusBar.warn { background: rgba(245,158,11,.85); }

    #cooldownOverlay {
        position: absolute; inset: 0;
        background: rgba(0,0,0,.45);
        display: none; align-items: center; justify-content: center; z-index: 10;
    }
    .cd-circle {
        width: 90px; height: 90px; border-radius: 50%;
        background: rgba(0,0,0,.85);
        display: flex; align-items: center; justify-content: center;
        font-size: 2.5rem; font-weight: 800;
    }
    #modelOverlay {
        position: absolute; inset: 0;
        background: rgba(0,0,0,.88);
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        gap: 1rem; z-index: 20;
    }
    .spin-ring {
        width: 52px; height: 52px;
        border: 4px solid rgba(255,255,255,.15);
        border-top-color: #22c55e;
        border-radius: 50%;
        animation: spin .9s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ===== SIDE PANEL ===== */
    #sidePanel {
        width: 320px;
        background: rgba(15,23,42,.96);
        border-left: 1px solid rgba(255,255,255,.08);
        display: flex; flex-direction: column;
        padding: 1.25rem;
        overflow-y: auto; flex-shrink: 0;
    }
    .panel-title {
        font-size: .75rem; color: #64748b;
        text-transform: uppercase; letter-spacing: .08em;
        margin-bottom: .75rem;
    }
    #idleBox {
        flex: 1; display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        text-align: center; gap: .75rem;
    }
    #idleBox .icon { font-size: 3.5rem; opacity: .25; }
    #resultBox { display: none; flex: 1; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: .5rem; }
    #resultAvatar { width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(255,255,255,.15); }
    .r-name   { font-size: 1.3rem; font-weight: 700; }
    .r-jabatan { color: #94a3b8; font-size: .85rem; }
    .r-badge  { display: inline-block; padding: 6px 18px; border-radius: 20px; font-weight: 700; font-size: .95rem; margin: .5rem 0; }
    .r-badge.ci { background: rgba(34,197,94,.2); color: #22c55e; border: 2px solid #22c55e; }
    .r-badge.co { background: rgba(59,130,246,.2); color: #60a5fa; border: 2px solid #60a5fa; }
    .r-badge.lm { background: rgba(251,191,36,.2); color: #fbbf24; border: 2px solid #fbbf24; }
    .r-badge.lk { background: rgba(251,146,60,.2); color: #fb923c; border: 2px solid #fb923c; }
    .r-time   { font-size: 2.2rem; font-weight: 800; font-variant-numeric: tabular-nums; }
    .r-date   { color: #64748b; font-size: .8rem; }
    #clockBar {
        background: rgba(255,255,255,.04);
        border: 1px solid rgba(255,255,255,.07);
        border-radius: 10px; padding: .6rem 1rem;
        text-align: center; margin-bottom: 1rem;
    }
    #clockEl { font-size: 1.6rem; font-weight: 800; font-variant-numeric: tabular-nums; }
    #dateEl  { font-size: .75rem; color: #64748b; }
    #actionBar { margin-top: 1rem; display: flex; flex-direction: column; gap: .5rem; }
    #actionBar a, #actionBar button {
        display: flex; align-items: center; gap: .5rem;
        padding: .5rem .75rem; border-radius: 8px;
        font-size: .82rem; text-decoration: none; border: none; cursor: pointer;
        background: rgba(255,255,255,.05); color: #94a3b8;
        transition: background .15s;
    }
    #actionBar a:hover, #actionBar button:hover { background: rgba(255,255,255,.1); color: #e2e8f0; }
    #gpsStatusBox {
        display: flex; align-items: center; gap: 6px;
        margin-top: 8px; font-size: .75rem; line-height: 1.4;
    }
    #gpsText { color: #64748b; transition: color .3s; }
    #gpsText.ok     { color: #22c55e; }
    #gpsText.denied { color: #f87171; font-weight: 600; }
    #gpsText.pending { color: #94a3b8; }

    /* ===== SCANNING ACTIVE: smart compact saat scan berlangsung ===== */
    /* Sembunyikan elemen noise, fokuskan ke panel anti-spoofing */
    body.scanning-active #idleBox     { display: none !important; }
    body.scanning-active .panel-title { display: none !important; }
    body.scanning-active #dateEl      { display: none !important; }
    body.scanning-active #clockBar {
        padding: .35rem .75rem !important;
        margin-bottom: .5rem !important;
    }
    body.scanning-active #clockEl {
        font-size: 1.1rem !important;
        font-weight: 600 !important;
    }
    body.scanning-active #selectedTypeBar {
        padding: .35rem .6rem !important;
        margin-bottom: .5rem !important;
    }
    body.scanning-active .liveness-panel {
        margin: 6px 0 !important;
        border-width: 2px !important;
    }
    body.scanning-active #actionBar {
        margin-top: .5rem !important;
        gap: .3rem !important;
    }
    body.scanning-active #actionBar a,
    body.scanning-active #actionBar button {
        padding: .4rem .6rem !important;
        font-size: .78rem !important;
    }

    /* ===== LIVENESS PANEL (static di info panel kanan) ===== */
    .liveness-panel {
        background: rgba(0,0,0,.4);
        border: 1px solid rgba(255,193,7,.5);
        border-radius: 12px;
        padding: 14px;
        margin: 12px 0;
        text-align: center;
        max-height: 200px;
        overflow: hidden;
        transition: opacity .3s, max-height .3s, padding .3s, margin .3s, border-width .3s;
    }
    .liveness-panel.hidden {
        opacity: 0;
        max-height: 0;
        padding: 0;
        margin: 0;
        border-width: 0;
        pointer-events: none;
    }
    .lp-title {
        font-size: .75rem; font-weight: 700; color: #ffc107;
        margin-bottom: 6px; letter-spacing: .05em; text-transform: uppercase;
    }
    .lp-instruction { font-size: .85rem; color: rgba(255,255,255,.85); margin-bottom: 10px; }
    .lp-counter {
        font-size: 1.6rem; font-weight: 700; color: white;
        margin-bottom: 6px; font-variant-numeric: tabular-nums;
    }
    .lp-counter span.done { color: #22c55e; }
    .lp-state {
        display: inline-block; padding: 2px 12px; border-radius: 12px;
        font-size: .75rem; color: rgba(255,255,255,.85);
        background: rgba(255,255,255,.12); margin-bottom: 10px;
    }
    .lp-timer {
        height: 4px; background: rgba(255,255,255,.1);
        border-radius: 4px; overflow: hidden; margin-bottom: 4px;
    }
    .liveness-timer-bar { height: 100%; background: #ffc107; border-radius: 4px; transition: width .1s linear, background .3s; }
    .liveness-timer-bar.critical { background: #ef4444; }
    .liveness-time-left { font-size: .7rem; color: rgba(255,255,255,.6); text-align: center; }
    .lp-debug { font-size: .65rem; opacity: .6; margin-top: 8px; font-family: monospace; color: #94a3b8; line-height: 1.4; }

    @media (max-width: 767px) {
        #scanWrap { flex-direction: column; }
        #sidePanel { width: 100%; border-left: none; border-top: 1px solid rgba(255,255,255,.08); padding: .75rem 1rem; }
        #videoArea { flex: 1; min-height: 45vh; }
        #idleBox, #resultBox { flex-direction: row; justify-content: flex-start; text-align: left; gap: 1rem; }
        #resultBox { display: none; }
        #resultBox.show { display: flex !important; }
        #resultAvatar { width: 60px; height: 60px; }
        .r-time { font-size: 1.5rem; }
        #clockBar { display: none; }
        #actionBar { flex-direction: row; flex-wrap: wrap; }
        .tipe-grid { grid-template-columns: 1fr 1fr; }
    }
    @media (min-width: 1200px) {
        #sidePanel { width: 360px; }
    }

    /* ===== SUCCESS OVERLAY ===== */
    #successOverlay {
        display: none;
        position: fixed; inset: 0; z-index: 100;
        background: rgba(0,0,0,.92);
        backdrop-filter: blur(6px);
        align-items: center; justify-content: center;
        padding: 1rem;
    }
    #successOverlay.show { display: flex; }
    #successContent {
        background: #1e293b;
        border: 1px solid rgba(255,255,255,.1);
        border-radius: 20px;
        padding: 2rem 1.75rem;
        width: 100%;
        max-width: 420px;
        text-align: center;
        position: relative;
        animation: successPop .35s cubic-bezier(.175,.885,.32,1.275) both;
    }
    @keyframes successPop {
        from { opacity: 0; transform: scale(.8); }
        to   { opacity: 1; transform: scale(1);  }
    }
    .sc-check {
        font-size: 4rem;
        line-height: 1;
        margin-bottom: .75rem;
        animation: checkBounce .5s .1s cubic-bezier(.175,.885,.32,1.275) both;
    }
    @keyframes checkBounce {
        0%   { transform: scale(0) rotate(-20deg); opacity: 0; }
        60%  { transform: scale(1.2) rotate(5deg); }
        100% { transform: scale(1) rotate(0deg);   opacity: 1; }
    }
    .sc-foto {
        width: 80px; height: 80px;
        border-radius: 50%; object-fit: cover;
        border: 3px solid rgba(255,255,255,.2);
        margin: 0 auto .75rem;
        display: block;
    }
    .sc-name    { font-size: 1.5rem; font-weight: 800; color: #f1f5f9; line-height: 1.2; }
    .sc-jabatan { color: #64748b; font-size: .85rem; margin-bottom: .6rem; }
    .sc-badge   {
        display: inline-block;
        padding: 5px 18px; border-radius: 20px;
        font-weight: 700; font-size: 1rem;
        margin-bottom: .75rem;
    }
    .sc-badge.ci { background: rgba(34,197,94,.2); color: #22c55e; border: 2px solid #22c55e; }
    .sc-badge.co { background: rgba(59,130,246,.2); color: #60a5fa; border: 2px solid #60a5fa; }
    .sc-badge.lm { background: rgba(251,191,36,.2); color: #fbbf24; border: 2px solid #fbbf24; }
    .sc-badge.lk { background: rgba(251,146,60,.2); color: #fb923c; border: 2px solid #fb923c; }
    .sc-time  { font-size: 2.8rem; font-weight: 800; font-variant-numeric: tabular-nums; color: #f1f5f9; line-height: 1; }
    .sc-date  { color: #64748b; font-size: .8rem; margin-bottom: 1rem; }

    /* Status hari ini grid */
    .sc-status-box {
        background: rgba(0,0,0,.3);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 12px;
        padding: .75rem 1rem;
        margin-bottom: 1rem;
        text-align: left;
    }
    .sc-status-title {
        font-size: .7rem; color: #64748b;
        text-transform: uppercase; letter-spacing: .08em;
        margin-bottom: .5rem; text-align: center;
    }
    .sc-status-grid {
        display: grid; grid-template-columns: 1fr 1fr; gap: .4rem;
    }
    .sc-status-row {
        display: flex; align-items: center; justify-content: space-between;
        background: rgba(255,255,255,.04); border-radius: 8px;
        padding: .35rem .6rem; font-size: .8rem;
    }
    .sc-status-label { color: #94a3b8; }
    .sc-status-jam   { font-weight: 700; color: #f1f5f9; font-variant-numeric: tabular-nums; }
    .sc-status-jam.empty { color: #475569; font-weight: 400; }

    /* Countdown bar */
    .sc-countdown-wrap {
        display: flex; align-items: center; gap: .5rem;
        margin-top: .5rem;
    }
    .sc-countdown-track {
        flex: 1; height: 4px; background: rgba(255,255,255,.1); border-radius: 4px; overflow: hidden;
    }
    #scCountdownBar {
        height: 100%; background: #22c55e; border-radius: 4px;
        width: 100%; transition: width .1s linear;
    }
    #scCountdownText { font-size: .75rem; color: #64748b; min-width: 20px; text-align: right; }

    /* Close button */
    #scCloseBtn {
        position: absolute; top: .75rem; right: .75rem;
        background: rgba(255,255,255,.08); border: none; color: #64748b;
        width: 28px; height: 28px; border-radius: 50%; font-size: .9rem;
        cursor: pointer; display: flex; align-items: center; justify-content: center;
        transition: background .15s, color .15s;
    }
    #scCloseBtn:hover { background: rgba(255,255,255,.15); color: #e2e8f0; }

    @media (max-width: 480px) {
        #successContent { padding: 1.5rem 1rem; max-width: 100%; }
        .sc-time { font-size: 2.2rem; }
        .sc-status-grid { grid-template-columns: 1fr; }
    }

    /* ===== SCAN ABSENSI FULLSCREEN MODE ===== */
    body.scan-absensi-mode #sidebar,
    body.scan-absensi-mode #sidebar-overlay,
    body.scan-absensi-mode #topbar { display: none !important; }

    body.scan-absensi-mode,
    html:has(body.scan-absensi-mode) {
        margin: 0 !important;
        padding: 0 !important;
    }

    body.scan-absensi-mode #main-content {
        margin: 0 !important;      /* hapus margin-top: var(--topbar-height) = 60px */
        padding: 0 !important;
        min-height: 0 !important;
    }
    body.scan-absensi-mode #scanWrap {
        margin: 0 !important;
        padding: 0 !important;
        height: 100vh !important;
        height: 100dvh !important; /* dvh: dynamic viewport height, lebih akurat di iOS */
    }

    /* Tablet portrait & landscape: stack vertical */
    @media (max-width: 991.98px) {
        body.scan-absensi-mode #scanWrap {
            flex-direction: column;
        }
        body.scan-absensi-mode #videoArea {
            flex: none !important;
            height: 52vh;
            min-height: 0;
        }
        body.scan-absensi-mode #sidePanel {
            width: 100% !important;
            flex: 1;
            overflow-y: auto;
            border-left: none !important;
            border-top: 1px solid rgba(255,255,255,.08);
            padding: .75rem 1rem;
        }
        body.scan-absensi-mode #clockBar { display: none; }
        body.scan-absensi-mode #idleBox,
        body.scan-absensi-mode #resultBox {
            flex-direction: row;
            text-align: left;
            gap: 1rem;
        }
    }

    /* Exit button (absolute, top-right of video area) */
    .scan-exit-btn {
        position: absolute;
        top: 10px; right: 10px;
        z-index: 1000;
        opacity: .75;
        border-radius: 50%;
        width: 36px; height: 36px;
        padding: 0;
        background: rgba(0,0,0,.6);
        border: 1px solid rgba(255,255,255,.2);
        color: #e2e8f0;
        display: flex; align-items: center; justify-content: center;
        transition: opacity .2s, background .2s;
        cursor: pointer;
    }
    .scan-exit-btn:hover { opacity: 1; background: rgba(0,0,0,.85); }
</style>
@endpush

@section('content')
<x-back-button-pwa />
<div id="scanWrap">

    {{-- ===== TYPE SELECTION OVERLAY ===== --}}
    <div id="tipeOverlay">
        <div class="tipe-title">
            <h2><i class="bi bi-person-bounding-box me-2"></i>Absensi Wajah</h2>
            <p>Pilih tipe absensi, lalu hadapkan wajah ke kamera</p>
            <div style="font-size:.75rem;color:#475569;margin-top:.2rem">
                <i class="bi bi-fullscreen me-1"></i>Mode fullscreen aktif — klik <strong style="color:#64748b">✕</strong> di pojok untuk keluar
            </div>
            <div style="font-size:.8rem;color:#475569;margin-top:.25rem">
                <i class="bi bi-building me-1"></i>{{ $cabang?->nama_cabang ?? 'Cabang tidak diset' }}
            </div>
            <div class="mt-2">
                <x-panduan-button slug="scan-absensi" label="Panduan Absensi" variant="outline-secondary" />
            </div>
        </div>
        <div class="tipe-grid">
            <button class="tipe-btn masuk" onclick="pilihTipe('masuk')">
                <span class="tipe-icon">🟢</span>
                <span>MASUK</span>
                <span class="tipe-label">Clock In Kerja</span>
            </button>
            <button class="tipe-btn keluar" onclick="pilihTipe('keluar')">
                <span class="tipe-icon">🔵</span>
                <span>KELUAR</span>
                <span class="tipe-label">Clock Out Kerja</span>
            </button>
            <button class="tipe-btn lembur-masuk" onclick="pilihTipe('lembur_masuk')">
                <span class="tipe-icon">🟡</span>
                <span>LEMBUR MASUK</span>
                <span class="tipe-label">Mulai Lembur</span>
            </button>
            <button class="tipe-btn lembur-keluar" onclick="pilihTipe('lembur_keluar')">
                <span class="tipe-icon">🟠</span>
                <span>LEMBUR KELUAR</span>
                <span class="tipe-label">Selesai Lembur</span>
            </button>
        </div>
    </div>

    {{-- VIDEO AREA (hidden until type selected) --}}
    <div id="videoArea" style="display:none">
        <video id="videoEl" autoplay playsinline muted></video>
        <canvas id="canvasOverlay"></canvas>
        <div class="scan-guide" id="scanGuide"></div>
        <div id="scanStatusBar">Memuat model AI...</div>
        {{-- Exit fullscreen button --}}
        <button class="scan-exit-btn" onclick="exitScanMode()"
                title="Keluar mode scan &amp; kembali ke dashboard"
                data-bs-toggle="tooltip" data-bs-placement="left">
            <i class="bi bi-x-lg"></i>
        </button>
        <div id="cooldownOverlay">
            <div class="cd-circle" id="cdNum">5</div>
        </div>
        <div id="modelOverlay">
            <div class="spin-ring"></div>
            <div id="modelText" style="color:#94a3b8;font-size:.9rem">Memuat model AI...</div>
        </div>
    </div>

    {{-- SIDE PANEL (hidden until type selected) --}}
    <div id="sidePanel" style="display:none">

        {{-- Selected type indicator --}}
        <div id="selectedTypeBar">
            <div style="display:flex;align-items:center;gap:.5rem;font-size:.8rem;color:#94a3b8">
                <i class="bi bi-check-circle-fill" style="color:#22c55e"></i>
                Tipe:
                <span id="selectedTypeBadge"></span>
            </div>
            <button id="btnGantiTipe" onclick="gantiTipe()" title="Pilih tipe lain">
                <i class="bi bi-arrow-left me-1"></i>Ganti
            </button>
        </div>

        {{-- Jam --}}
        <div id="clockBar">
            <div id="clockEl">--:--:--</div>
            <div id="dateEl">-- --- ----</div>
        </div>

        <div class="panel-title">Status Absensi</div>

        {{-- Idle --}}
        <div id="idleBox">
            <div class="icon"><i class="bi bi-person-bounding-box"></i></div>
            <div>
                <div class="fw-semibold" style="font-size:1rem">Siap Scan</div>
                <div style="color:#64748b;font-size:.83rem;margin-top:4px">
                    Hadapkan wajah ke kamera
                </div>
                <div style="color:#64748b;font-size:.75rem;margin-top:8px">
                    <i class="bi bi-building me-1"></i>{{ $cabang?->nama_cabang ?? 'Cabang tidak diset' }}
                </div>
                <div id="gpsStatusBox">
                    <span>📍</span>
                    <span id="gpsText" class="pending">Mendeteksi lokasi...</span>
                </div>
            </div>
        </div>

        {{-- Panel Anti-Spoofing (muncul saat liveness checking) --}}
        <div class="liveness-panel hidden" id="livenessPanel">
            <div class="lp-title">⚠️ Verifikasi Anti-Spoofing</div>
            <div class="lp-instruction">Geleng kepala ke kiri ATAU kanan</div>
            <div class="lp-counter">
                <span id="headMoveCounter">0</span> / 1
            </div>
            <div class="lp-state" id="headDirStatus">⏳ tengah</div>
            <div class="lp-timer">
                <div id="livenessTimerBar" class="liveness-timer-bar" style="width:100%"></div>
            </div>
            <div id="livenessTimeLeft" class="liveness-time-left">15s</div>
            <div id="debugInfo" class="lp-debug" style="display:none">noseX=- delta=- state=- mvmt=-</div>
        </div>

        {{-- Error fatal (server 500 / network error) --}}
        <div id="errorBox" style="display:none;padding:1rem;text-align:center;">
            <div style="color:#ef4444;font-size:2rem;margin-bottom:.5rem;">⚠️</div>
            <div id="errorBoxMsg" style="color:#ef4444;font-weight:600;margin-bottom:1rem;font-size:.9rem;">Gagal menyimpan absensi.</div>
            <button onclick="resetFatalError()" class="btn btn-outline-light btn-sm w-100">
                <i class="bi bi-arrow-clockwise me-1"></i>Coba Lagi
            </button>
        </div>

        {{-- Result --}}
        <div id="resultBox">
            <img id="resultAvatar" src="" alt="">
            <div>
                <div class="r-name" id="rName">---</div>
                <div class="r-jabatan" id="rJabatan">---</div>
                <div><span class="r-badge" id="rBadge">---</span></div>
                <div class="r-time" id="rTime">--:--</div>
                <div class="r-date" id="rDate">--</div>
            </div>
        </div>

        {{-- Action buttons --}}
        <div id="actionBar">
            <a href="{{ route('face-attendance.today') }}">
                <i class="bi bi-clock-history"></i> Absensi Hari Ini
            </a>
            <a href="{{ route('face-attendance.log') }}">
                <i class="bi bi-list-ul"></i> Lihat Log
            </a>
            @canany(['karyawan.create','karyawan.edit'])
            <a href="{{ route('face-registration.index') }}">
                <i class="bi bi-person-video3"></i> Registrasi Wajah
            </a>
            @endcanany
        </div>

    </div>
</div>

{{-- ===== SUCCESS OVERLAY (full-screen, shown after successful scan) ===== --}}
<div id="successOverlay">
    <div id="successContent">
        <button id="scCloseBtn" onclick="closeSuccessOverlay()" title="Tutup">×</button>
        <div class="sc-check" id="scCheck">✅</div>
        <img class="sc-foto" id="scFoto" src="" alt="Foto absen">
        <div class="sc-name" id="scName"></div>
        <div class="sc-jabatan" id="scJabatan"></div>
        <span class="sc-badge" id="scBadge"></span>
        <div class="sc-time" id="scTime"></div>
        <div class="sc-date" id="scDate"></div>

        <div class="sc-status-box">
            <div class="sc-status-title">Status Absensi Hari Ini</div>
            <div class="sc-status-grid">
                <div class="sc-status-row">
                    <span class="sc-status-label">🟢 Masuk</span>
                    <span class="sc-status-jam" id="scStatMasuk">—</span>
                </div>
                <div class="sc-status-row">
                    <span class="sc-status-label">🔵 Keluar</span>
                    <span class="sc-status-jam" id="scStatKeluar">—</span>
                </div>
                <div class="sc-status-row">
                    <span class="sc-status-label">🟡 Lembur ↑</span>
                    <span class="sc-status-jam" id="scStatLemburMasuk">—</span>
                </div>
                <div class="sc-status-row">
                    <span class="sc-status-label">🟠 Lembur ↓</span>
                    <span class="sc-status-jam" id="scStatLemburKeluar">—</span>
                </div>
            </div>
        </div>

        <div class="sc-countdown-wrap">
            <div class="sc-countdown-track">
                <div id="scCountdownBar"></div>
            </div>
            <span id="scCountdownText">8</span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/dist/face-api.js"></script>
<script>
const PROSES_URL  = "{{ route('face-attendance.proses') }}";
const FACE_URL    = "{{ route('api.face-data.cabang') }}";
const CSRF        = document.querySelector('meta[name="csrf-token"]').content;
const MODEL_LOCAL = '/models';
const MODEL_CDN   = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/model';
const THRESHOLD   = 0.45;
const COOLDOWN    = 5;

// ── FULLSCREEN MODE ────────────────────────────────────────────────────
document.body.classList.add('scan-absensi-mode');

// viewport-fit=cover: hilangkan safe-area gap di iOS/iPad (scoped ke halaman ini saja)
const _vpMeta    = document.querySelector('meta[name="viewport"]');
const _vpOriginal = _vpMeta?.getAttribute('content') ?? 'width=device-width, initial-scale=1.0';
if (_vpMeta) _vpMeta.setAttribute('content', _vpOriginal + ', viewport-fit=cover');

window.addEventListener('beforeunload', () => {
    document.body.classList.remove('scan-absensi-mode', 'scanning-active');
    if (_vpMeta) _vpMeta.setAttribute('content', _vpOriginal); // restore saat leave
});
function exitScanMode() {
    document.body.classList.remove('scan-absensi-mode', 'scanning-active');
    if (_vpMeta) _vpMeta.setAttribute('content', _vpOriginal);
    if (stream) stream.getTracks().forEach(t => t.stop());
    if (scanLoopId) clearInterval(scanLoopId);
    window.location.href = '{{ route("dashboard") }}';
}

// Debug mode: tambah ?debug=1 di URL untuk tampilkan info real-time di panel
window._debugMode = new URLSearchParams(window.location.search).has('debug');
if (window._debugMode) {
    const el = document.getElementById('debugInfo');
    if (el) el.style.display = 'block';
}

const REQUIRED_HEAD_MOVEMENTS   = 1;     // 1 geleng cukup untuk anti-spoofing (UX tablet)
const HEAD_MOVEMENT_THRESHOLD   = 18;    // pixel pergeseran X minimum dari initial
const HEAD_RESET_THRESHOLD      = 20;    // longgar — dianggap kembali ke center
const LIVENESS_TIMEOUT_MS       = 15000; // 15 detik (lebih lega)
const EAR_BUFFER_SIZE           = 3;     // retained untuk monitoring
const MIN_LOW_FRAMES            = 2;
const CALIBRATION_FRAMES        = 10;    // ~1.2 detik di ~8fps (kalibrasi diam)
const EAR_DROP_RATIO            = 0.85;
const EAR_THRESHOLD_MIN         = 0.10;

let modelsLoaded  = false;
let faceMatcher   = null;
let allKaryawan   = [];
let stream        = null;
let isProcessing  = false;
let isDetecting   = false;
let isCooldown    = false;
let isSubmitting  = false; // guard: 1 fetch at a time
let isFatalError  = false; // set true on 500/network error, stops loop
let scanLoopId    = null;

// GPS state
let gpsLatitude  = null;
let gpsLongitude = null;
let gpsStatus    = 'pending';
let gpsWatchId   = null;

// Liveness state
let livenessState      = 'idle';
let blinkCount         = 0;            // monitoring saja (tidak jadi syarat)
let headMovementCount  = 0;            // syarat utama liveness pass
let headDirectionState = 'center';     // 'center' | 'left' | 'right'
let headStateChangedAt = null;         // waktu masuk ke state non-center
let lastNoseX          = null;
let livenessStartTime  = null;
let initialNoseX       = null;         // set lazily setelah kalibrasi
let initialNoseY       = null;
let earWasLow          = false;
let earBuffer          = [];
let lowFrameCount      = 0;
let earBaseline        = null;
let earCalibrationBuffer = [];
let lastMatchedId      = null;
let lastMatchedConf    = null;

// ── TIPE ABSENSI STATE ──────────────────────────────────────────────────
let selectedTipeAbsensi = null; // masuk | keluar | lembur_masuk | lembur_keluar

const TIPE_CONFIG = {
    masuk:         { label: '🟢 MASUK',         badgeCls: 'ci', color: '#22c55e' },
    keluar:        { label: '🔵 KELUAR',         badgeCls: 'co', color: '#60a5fa' },
    lembur_masuk:  { label: '🟡 LEMBUR MASUK',  badgeCls: 'lm', color: '#fbbf24' },
    lembur_keluar: { label: '🟠 LEMBUR KELUAR', badgeCls: 'lk', color: '#fb923c' },
};

function pilihTipe(tipe) {
    selectedTipeAbsensi = tipe;
    const cfg = TIPE_CONFIG[tipe];

    // Update selected type badge in side panel
    const badge = document.getElementById('selectedTypeBadge');
    badge.textContent = cfg.label;
    badge.style.background = cfg.color + '22';
    badge.style.color = cfg.color;
    badge.style.border = '1px solid ' + cfg.color + '55';
    badge.style.borderRadius = '20px';
    badge.style.padding = '2px 10px';

    // Show main UI, hide overlay
    document.getElementById('tipeOverlay').style.display = 'none';
    document.getElementById('videoArea').style.display   = 'block';
    document.getElementById('sidePanel').style.display   = 'flex';
    document.getElementById('selectedTypeBar').style.display = 'flex';

    init();
}

function gantiTipe() {
    // Stop all running processes
    if (stream) {
        stream.getTracks().forEach(t => t.stop());
        stream = null;
    }
    if (gpsWatchId !== null) {
        navigator.geolocation.clearWatch(gpsWatchId);
        gpsWatchId = null;
    }

    selectedTipeAbsensi = null;
    modelsLoaded = false;
    faceMatcher  = null;
    isProcessing = false;
    isCooldown   = false;
    gpsStatus    = 'pending';
    gpsLatitude  = null;
    gpsLongitude = null;
    resetLiveness();

    document.getElementById('tipeOverlay').style.display = 'flex';
    document.getElementById('videoArea').style.display   = 'none';
    document.getElementById('sidePanel').style.display   = 'none';
    document.getElementById('selectedTypeBar').style.display = 'none';

    // Reset idle UI
    document.getElementById('idleBox').style.display   = 'flex';
    document.getElementById('resultBox').style.display = 'none';
    document.getElementById('resultBox').classList.remove('show');
}

// ── CLOCK (server timezone via PHP, displayed as local time) ─────────────
function updateClock() {
    const now = new Date();
    document.getElementById('clockEl').textContent = now.toLocaleTimeString('id-ID');
    document.getElementById('dateEl').textContent  = now.toLocaleDateString('id-ID',
        {weekday:'long', day:'2-digit', month:'long', year:'numeric'});
}
setInterval(updateClock, 1000);
updateClock();

// ── GPS ────────────────────────────────────────────────────────────────
function initGPS() {
    if (!navigator.geolocation) {
        gpsStatus = 'denied';
        updateGPSUI();
        return;
    }
    updateGPSUI();
    gpsWatchId = navigator.geolocation.watchPosition(
        (pos) => {
            gpsLatitude  = pos.coords.latitude;
            gpsLongitude = pos.coords.longitude;
            if (gpsStatus !== 'ok') { gpsStatus = 'ok'; updateGPSUI(); }
        },
        () => { gpsStatus = 'denied'; updateGPSUI(); },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
    );
}

function updateGPSUI() {
    const textEl = document.getElementById('gpsText');
    if (!textEl) return;
    const map = {
        pending: { text: 'Mendeteksi lokasi...', cls: 'pending' },
        ok:      { text: 'Lokasi terdeteksi ✓',  cls: 'ok'      },
        denied:  { text: 'GPS ditolak — absen diblokir', cls: 'denied' },
        error:   { text: 'GPS error',            cls: 'pending' },
    };
    const cfg = map[gpsStatus] || map.error;
    textEl.textContent = cfg.text;
    textEl.className   = cfg.cls;
}

// ── LIVENESS: Eye Aspect Ratio ─────────────────────────────────────────
function dist(p1, p2) {
    return Math.sqrt((p1.x - p2.x) ** 2 + (p1.y - p2.y) ** 2);
}
function calcEAR(positions) {
    const r   = [36,37,38,39,40,41].map(i => positions[i]);
    const earR = (dist(r[1],r[5]) + dist(r[2],r[4])) / (2 * dist(r[0],r[3]));
    const l   = [42,43,44,45,46,47].map(i => positions[i]);
    const earL = (dist(l[1],l[5]) + dist(l[2],l[4])) / (2 * dist(l[0],l[3]));
    return (earR + earL) / 2;
}
function updateLivenessUI() {
    const overlay = document.getElementById('livenessPanel');
    if (!overlay) return;
    if (livenessState !== 'checking') {
        overlay.classList.add('hidden');
        document.body.classList.remove('scanning-active');
        return;
    }
    overlay.classList.remove('hidden');
    document.body.classList.add('scanning-active');
    const bEl = document.getElementById('headMoveCounter');
    bEl.textContent = headMovementCount;
    bEl.className   = headMovementCount >= REQUIRED_HEAD_MOVEMENTS ? 'done' : '';
    const mEl = document.getElementById('headDirStatus');
    const dirMap = { center: '⏳ tengah', left: '← kiri', right: '→ kanan' };
    mEl.textContent = dirMap[headDirectionState] ?? '⏳';
    mEl.style.color = headDirectionState !== 'center' ? '#fbbf24' : '#94a3b8';
    const elapsed   = Date.now() - livenessStartTime;
    const remaining = Math.max(0, LIVENESS_TIMEOUT_MS - elapsed);
    const pct       = (remaining / LIVENESS_TIMEOUT_MS) * 100;
    const bar = document.getElementById('livenessTimerBar');
    bar.style.width = pct + '%';
    bar.className   = 'liveness-timer-bar' + (pct < 30 ? ' critical' : '');
    document.getElementById('livenessTimeLeft').textContent = Math.ceil(remaining / 1000) + 's';
}
function resetLiveness() {
    livenessState      = 'idle';
    blinkCount         = 0;
    headMovementCount  = 0;
    headDirectionState = 'center';
    headStateChangedAt = null;
    lastNoseX          = null;
    earWasLow          = false;
    earBuffer          = [];
    lowFrameCount      = 0;
    earBaseline        = null;
    earCalibrationBuffer = [];
    lastMatchedId      = null;
    lastMatchedConf    = null;
    initialNoseX       = null;
    initialNoseY       = null;
    const overlay = document.getElementById('livenessPanel');
    if (overlay) overlay.classList.add('hidden');
    document.body.classList.remove('scanning-active');
}

// ── INIT ───────────────────────────────────────────────────────────────
async function init() {
    initGPS();
    await loadModels();
    await loadFaceData();
    await startCamera();
    startLoop();
}

async function loadModels() {
    const modelOverlay = document.getElementById('modelOverlay');
    const modelText    = document.getElementById('modelText');
    const set = t => { if (modelText) modelText.textContent = t; };
    try {
        set('Memuat tinyFaceDetector...');
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_LOCAL);
        set('Memuat faceLandmark68Net...');
        await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_LOCAL);
        set('Memuat faceRecognitionNet...');
        await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_LOCAL);
        modelsLoaded = true;
        if (modelOverlay) modelOverlay.style.display = 'none';
    } catch {
        set('Model lokal gagal, coba CDN...');
        try {
            await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_CDN);
            await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_CDN);
            await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_CDN);
            modelsLoaded = true;
            if (modelOverlay) modelOverlay.style.display = 'none';
        } catch {
            set('✗ Gagal memuat model. Periksa koneksi.');
            if (modelText) modelText.style.color = '#f87171';
        }
    }
}

async function loadFaceData() {
    try {
        const resp = await fetch(FACE_URL, { credentials: 'include' });
        if (!resp.ok) { setStatus('Gagal load data wajah (' + resp.status + ')', 'err'); return; }
        const data = await resp.json();
        if (!data.length) { setStatus('Belum ada wajah terdaftar di cabang ini', 'warn'); return; }
        allKaryawan = data;
        faceMatcher = new faceapi.FaceMatcher(
            data.map(k => new faceapi.LabeledFaceDescriptors(
                String(k.id),
                (k.descriptors || []).map(d => new Float32Array(d))
            )),
            THRESHOLD
        );
        setStatus(data.length + ' wajah terdaftar — Siap scan', '');
    } catch (e) {
        setStatus('Error: ' + e.message, 'err');
    }
}

async function startCamera() {
    const videoEl  = document.getElementById('videoEl');
    const canvasEl = document.getElementById('canvasOverlay');
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }
        });
        videoEl.srcObject = stream;
        await new Promise(r => videoEl.onloadedmetadata = r);
        videoEl.play();
        canvasEl.width  = videoEl.videoWidth;
        canvasEl.height = videoEl.videoHeight;
    } catch (e) {
        setStatus('Kamera tidak bisa diakses: ' + e.message, 'err');
    }
}

// ── DETECTION LOOP ────────────────────────────────────────────────────
function startLoop() {
    const videoEl  = document.getElementById('videoEl');
    const canvasEl = document.getElementById('canvasOverlay');
    const guideEl  = document.getElementById('scanGuide');
    const statusEl = document.getElementById('scanStatusBar');

    if (scanLoopId) clearInterval(scanLoopId);
    scanLoopId = setInterval(async () => {
        if (isDetecting || !modelsLoaded || isProcessing || isCooldown || isFatalError || !faceMatcher) return;
        if (!videoEl || videoEl.readyState < 2) return;
        isDetecting = true;
        try {
            const det = await faceapi
                .detectSingleFace(videoEl, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 }))
                .withFaceLandmarks()
                .withFaceDescriptor();
            const ctx = canvasEl.getContext('2d');
            ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);
            if (!det) {
                guideEl.classList.remove('active');
                if (livenessState === 'checking') resetLiveness();
                const hint = gpsStatus === 'denied'
                    ? '❌ GPS ditolak — aktifkan lokasi untuk absen'
                    : 'Arahkan wajah ke kamera';
                setStatus(hint, gpsStatus === 'denied' ? 'err' : '');
                return;
            }
            const b = det.detection.box;
            ctx.strokeStyle = livenessState === 'passed' ? '#22c55e' : '#f59e0b';
            ctx.lineWidth   = 3;
            ctx.strokeRect(b.x, b.y, b.width, b.height);
            guideEl.classList.add('active');

            if (livenessState === 'idle') {
                livenessState        = 'checking';
                livenessStartTime    = Date.now();
                blinkCount           = 0;
                headMovementCount    = 0;
                headDirectionState   = 'center';
                headStateChangedAt   = null;
                lastNoseX            = null;
                earWasLow            = false;
                earBuffer            = [];
                lowFrameCount        = 0;
                earBaseline          = null;
                earCalibrationBuffer = [];
                initialNoseX         = null;
                initialNoseY         = null;
                lastMatchedId        = null;
                lastMatchedConf      = null;
                setStatus('🔄 Kalibrasi wajah... (diam sebentar)', 'warn');
                updateLivenessUI();
            }
            if (livenessState === 'checking') {
                const elapsed = Date.now() - livenessStartTime;
                if (elapsed > LIVENESS_TIMEOUT_MS) {
                    livenessState = 'failed';
                    updateLivenessUI();
                    sendLivenessFail();
                    setStatus('❌ Liveness gagal. Silakan coba lagi.', 'err');
                    beep('err');
                    startCooldown(3);
                    return;
                }
                const ear = calcEAR(det.landmarks.positions);

                // ── FASE KALIBRASI: diam ~1.2 detik untuk set EAR baseline ──
                if (earBaseline === null) {
                    earCalibrationBuffer.push(ear);
                    if (earCalibrationBuffer.length >= CALIBRATION_FRAMES) {
                        earBaseline = earCalibrationBuffer.reduce((a, b) => a + b, 0) / earCalibrationBuffer.length;
                        console.log('[Liveness] EAR baseline:', earBaseline.toFixed(3));
                        setStatus(`👤 Geleng kepala kiri-kanan 0/${REQUIRED_HEAD_MOVEMENTS}`, 'warn');
                    }
                    return; // belum proses head movement selama kalibrasi
                }

                // ── EAR monitoring (tidak jadi syarat, untuk debug) ──────────
                earBuffer.push(ear);
                if (earBuffer.length > EAR_BUFFER_SIZE) earBuffer.shift();
                const earAvg = earBuffer.reduce((a, b) => a + b, 0) / earBuffer.length;

                // ── FASE DETEKSI HEAD MOVEMENT ───────────────────────────────
                const noseX = det.landmarks.positions[30].x;
                if (initialNoseX === null) {
                    // frame pertama setelah kalibrasi — catat posisi awal
                    initialNoseX = noseX;
                    lastNoseX    = noseX;
                } else {
                    const deltaFromInitial = noseX - initialNoseX;
                    if (headDirectionState === 'center') {
                        if (Math.abs(deltaFromInitial) > HEAD_MOVEMENT_THRESHOLD) {
                            headDirectionState  = deltaFromInitial > 0 ? 'right' : 'left';
                            headStateChangedAt  = Date.now();
                        }
                    } else {
                        // sudah bergerak ke samping, tunggu kembali ke center
                        if (Math.abs(deltaFromInitial) < HEAD_RESET_THRESHOLD) {
                            headMovementCount++;
                            headDirectionState = 'center';
                            headStateChangedAt = null;
                            console.log('[Liveness] Head movement:', headMovementCount);
                        } else if (headStateChangedAt && Date.now() - headStateChangedAt > 3000) {
                            // stuck di samping > 3 detik — force reset ke center & re-anchor initial
                            console.log('[Liveness] Force reset: stuck in', headDirectionState, '> 3s');
                            headDirectionState = 'center';
                            headStateChangedAt = null;
                            initialNoseX       = noseX;
                        }
                    }
                    lastNoseX = noseX;
                }
                const dbgDelta = initialNoseX !== null ? (noseX - initialNoseX).toFixed(1) : '-';
                console.log('Head: noseX:', noseX.toFixed(1),
                    'delta:', dbgDelta,
                    'state:', headDirectionState, 'movements:', headMovementCount,
                    '| EAR raw:', ear.toFixed(3), 'avg:', earAvg.toFixed(3));

                // ── DEBUG VISUAL (hanya jika ?debug=1) ─────────────────────
                if (window._debugMode) {
                    const el = document.getElementById('debugInfo');
                    if (el) el.textContent = `noseX=${noseX.toFixed(0)} delta=${dbgDelta} state=${headDirectionState} mvmt=${headMovementCount}`;
                }

                if (faceMatcher) {
                    const m = faceMatcher.findBestMatch(det.descriptor);
                    if (m.label !== 'unknown') {
                        const c = (1 - m.distance) * 100;
                        if (c >= 60) { lastMatchedId = parseInt(m.label); lastMatchedConf = c; }
                    }
                }
                updateLivenessUI();
                if (headMovementCount >= REQUIRED_HEAD_MOVEMENTS) {
                    livenessState = 'passed';
                    updateLivenessUI();
                    ctx.strokeStyle = '#22c55e';
                    setStatus('✅ Verifikasi anti-spoofing lolos', 'ok');
                } else {
                    setStatus(`👤 Geleng kepala ${headMovementCount}/${REQUIRED_HEAD_MOVEMENTS}`, 'warn');
                    return;
                }
            }
            if (livenessState === 'failed') return;

            const match      = faceMatcher.findBestMatch(det.descriptor);
            const confidence = (1 - match.distance) * 100;
            if (match.label !== 'unknown' && confidence >= 60) {
                setStatus('Mencocokkan... ' + confidence.toFixed(0) + '%', 'ok');
                await prosesAbsensi(parseInt(match.label), confidence);
            } else {
                setStatus('Wajah tidak dikenali', 'err');
            }
        } catch {
        } finally {
            isDetecting = false;
        }
    }, 120);
}

// ── PROSES ABSENSI ────────────────────────────────────────────────────
async function prosesAbsensi(karyawanId, confidence) {
    if (isSubmitting) return; // guard: satu fetch sekaligus
    isSubmitting = true;
    isProcessing = true;
    // overlayShown: jika true, showSuccessOverlay() sudah ambil alih isProcessing
    // jangan reset di finally agar detection loop tidak jalan lagi selama overlay tampil
    let overlayShown = false;
    if (gpsStatus === 'denied') {
        isProcessing = false;
        isSubmitting = false;
        setStatus('❌ Aktifkan izin lokasi GPS untuk melakukan absensi', 'err');
        beep('err');
        startCooldown(3);
        return;
    }
    if (gpsStatus === 'pending') {
        isProcessing = false;
        isSubmitting = false;
        setStatus('📍 Menunggu GPS...', 'warn');
        return;
    }
    setStatus('Memproses...', '');
    const foto = capturePhoto();
    try {
        const resp = await fetch(PROSES_URL, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({
                karyawan_id:        karyawanId,
                confidence_score:   confidence.toFixed(2),
                foto_absen:         foto,
                latitude:           gpsLatitude,
                longitude:          gpsLongitude,
                is_liveness_passed: true,
                tipe_absensi:       selectedTipeAbsensi,
            }),
            credentials: 'include',
        });
        if (!resp.ok) {
            // HTTP 4xx/5xx — stop loop, user harus klik Coba Lagi
            showFatalError(`Server error (${resp.status}). Hubungi admin atau coba lagi.`);
            return;
        }
        const data = await resp.json();
        if (data.success) {
            beep('ok');
            showSuccessOverlay(data); // sets isProcessing = true internally
            overlayShown = true;      // signal: jangan override isProcessing di finally
        } else {
            setStatus(data.message || 'Gagal', 'err');
            beep('err');
            startCooldown(2);
        }
    } catch {
        showFatalError('Error jaringan. Periksa koneksi internet dan coba lagi.');
    } finally {
        // Hanya reset isProcessing kalau overlay TIDAK mengambil alih.
        // Kalau overlayShown=true, closeSuccessOverlay() yang akan reset.
        if (!overlayShown) isProcessing = false;
        isSubmitting = false;
    }
}

function showFatalError(msg) {
    isFatalError = true;
    const box = document.getElementById('errorBox');
    const msgEl = document.getElementById('errorBoxMsg');
    if (box) {
        msgEl.textContent = msg;
        box.style.display = 'block';
    }
    setStatus('⚠️ ' + msg, 'err');
    beep('err');
}

function resetFatalError() {
    isFatalError  = false;
    isSubmitting  = false;
    isProcessing  = false;
    const box = document.getElementById('errorBox');
    if (box) box.style.display = 'none';
    resetLiveness();
    setStatus('Pilih jenis absensi untuk memulai', '');
}

async function sendLivenessFail() {
    try {
        await fetch(PROSES_URL, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({
                karyawan_id:        lastMatchedId  || null,
                confidence_score:   lastMatchedConf || 0,
                is_liveness_passed: false,
                latitude:           gpsLatitude,
                longitude:          gpsLongitude,
                tipe_absensi:       selectedTipeAbsensi,
            }),
            credentials: 'include',
        });
    } catch {}
}

// ── UI HELPERS ────────────────────────────────────────────────────────
function showResult(data) {
    document.getElementById('idleBox').style.display = 'none';
    const rb = document.getElementById('resultBox');
    rb.style.display = 'flex';
    rb.classList.add('show');
    const av = document.getElementById('resultAvatar');
    av.src = data.karyawan.foto ||
        `https://ui-avatars.com/api/?name=${encodeURIComponent(data.karyawan.nama)}&background=1e293b&color=94a3b8&size=200`;
    document.getElementById('rName').textContent    = data.karyawan.nama;
    document.getElementById('rJabatan').textContent = data.karyawan.jabatan || '-';
    document.getElementById('rTime').textContent    = data.waktu;
    document.getElementById('rDate').textContent    = data.tanggal;
    const badge = document.getElementById('rBadge');
    badge.textContent = data.message;
    const badgeClsMap = {
        clock_in:      'ci',
        clock_out:     'co',
        lembur_masuk:  'lm',
        lembur_keluar: 'lk',
    };
    badge.className = 'r-badge ' + (badgeClsMap[data.tipe] || 'ci');
    if (data.jarak !== null && data.jarak !== undefined) {
        document.getElementById('rDate').textContent = data.tanggal + ' · ' + data.jarak + 'm';
    }
    setStatus(data.message, 'ok');
}

function startCooldown(sec) {
    isCooldown = true;
    let n = sec;
    const cooldownEl = document.getElementById('cooldownOverlay');
    const cdNum      = document.getElementById('cdNum');
    cooldownEl.style.display = 'flex';
    cdNum.textContent = n;
    const iv = setInterval(() => {
        n--;
        cdNum.textContent = n;
        if (n <= 0) {
            clearInterval(iv);
            isCooldown = false;
            cooldownEl.style.display = 'none';
            resetLiveness();
            document.getElementById('idleBox').style.display   = 'flex';
            document.getElementById('resultBox').style.display = 'none';
            document.getElementById('resultBox').classList.remove('show');
            document.getElementById('scanGuide').classList.remove('active');
            setStatus(allKaryawan.length + ' wajah terdaftar — Siap scan', '');
        }
    }, 1000);
}

function setStatus(txt, type = '') {
    const statusEl = document.getElementById('scanStatusBar');
    if (!statusEl) return;
    statusEl.textContent = txt;
    const bg = { ok: 'rgba(34,197,94,.85)', err: 'rgba(239,68,68,.85)', warn: 'rgba(245,158,11,.85)', '': 'rgba(0,0,0,.75)' };
    statusEl.style.background = bg[type] ?? bg[''];
}

function capturePhoto() {
    const videoEl = document.getElementById('videoEl');
    const c = document.createElement('canvas');
    c.width  = videoEl.videoWidth;
    c.height = videoEl.videoHeight;
    c.getContext('2d').drawImage(videoEl, 0, 0);
    return c.toDataURL('image/jpeg', .7);
}

function beep(type) {
    try {
        const ac = new (window.AudioContext || window.webkitAudioContext)();
        const o  = ac.createOscillator();
        const g  = ac.createGain();
        o.connect(g); g.connect(ac.destination);
        o.frequency.value = type === 'ok' ? 880 : 280;
        g.gain.value = .25;
        o.start(); o.stop(ac.currentTime + .18);
    } catch {}
}

// ── SUCCESS OVERLAY ────────────────────────────────────────────────────
let scCountdownTimer = null;
const SC_DURATION    = 8; // seconds

function showSuccessOverlay(data) {
    const overlay = document.getElementById('successOverlay');
    const tipe    = data.tipe;
    const badgeClsMap = {
        clock_in:      'ci',
        clock_out:     'co',
        lembur_masuk:  'lm',
        lembur_keluar: 'lk',
    };
    const badgeLblMap = {
        clock_in:      '🟢 MASUK',
        clock_out:     '🔵 KELUAR',
        lembur_masuk:  '🟡 LEMBUR MASUK',
        lembur_keluar: '🟠 LEMBUR KELUAR',
    };

    // Karyawan info
    const avatarUrl = data.karyawan.foto ||
        `https://ui-avatars.com/api/?name=${encodeURIComponent(data.karyawan.nama)}&background=1e293b&color=94a3b8&size=200`;

    // Prefer captured foto_absen over avatar
    const scFotoEl = document.getElementById('scFoto');
    scFotoEl.src = data.foto_absen || avatarUrl;
    scFotoEl.onerror = () => { scFotoEl.src = avatarUrl; };

    document.getElementById('scName').textContent    = data.karyawan.nama;
    document.getElementById('scJabatan').textContent = data.karyawan.jabatan || '';

    const badge = document.getElementById('scBadge');
    badge.textContent = badgeLblMap[tipe] || tipe;
    badge.className   = 'sc-badge ' + (badgeClsMap[tipe] || 'ci');

    document.getElementById('scTime').textContent = data.waktu;
    document.getElementById('scDate').textContent = data.tanggal + (data.jarak !== null && data.jarak !== undefined ? ' · ' + data.jarak + 'm' : '');

    // Attendance status
    const fmt = (val) => val
        ? `<span class="sc-status-jam">${val}</span>`
        : `<span class="sc-status-jam empty">Belum</span>`;
    const ahi = data.absensi_hari_ini || {};
    document.getElementById('scStatMasuk').outerHTML       = fmt(ahi.jam_masuk);
    document.getElementById('scStatKeluar').outerHTML      = fmt(ahi.jam_keluar);
    document.getElementById('scStatLemburMasuk').outerHTML = fmt(ahi.jam_lembur_masuk);
    document.getElementById('scStatLemburKeluar').outerHTML= fmt(ahi.jam_lembur_keluar);

    // Show overlay
    overlay.classList.add('show');
    isProcessing = true;

    // Countdown bar
    const bar      = document.getElementById('scCountdownBar');
    const textEl   = document.getElementById('scCountdownText');
    let remaining  = SC_DURATION;
    bar.style.width = '100%';
    textEl.textContent = remaining;

    if (scCountdownTimer) clearInterval(scCountdownTimer);
    scCountdownTimer = setInterval(() => {
        remaining -= 0.1;
        const pct = Math.max(0, (remaining / SC_DURATION) * 100);
        bar.style.width = pct + '%';
        textEl.textContent = Math.ceil(remaining);
        if (remaining <= 0) {
            clearInterval(scCountdownTimer);
            scCountdownTimer = null;
            closeSuccessOverlay();
        }
    }, 100);
}

function closeSuccessOverlay() {
    if (scCountdownTimer) { clearInterval(scCountdownTimer); scCountdownTimer = null; }
    document.getElementById('successOverlay').classList.remove('show');
    isProcessing = false;
    // Return to type selection
    gantiTipe();
}
</script>
@endpush
