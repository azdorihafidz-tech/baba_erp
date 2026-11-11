<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @laravelPWA
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ $brand->namaSingkat() }}">
    <title>ERP {{ $brand->namaSingkat() }} - @yield('title', 'Dashboard')</title>
    <link rel="icon" type="image/png" href="{{ $brand->faviconUrl() }}">
    <link rel="shortcut icon" type="image/png" href="{{ $brand->faviconUrl() }}">
    <link rel="apple-touch-icon" href="{{ $brand->ikonUrl(192) }}">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Select2 (searchable dropdown, dipakai di POS) + tema Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <style>
        .select2-container--bootstrap-5 .select2-selection { min-height: 31px; }
    </style>
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <x-brand-warna />
    <style>
        :root {
            --sidebar-width: 260px;
            /* 4.48 — warna dari Pengaturan Umum → Warna Aplikasi (komponen brand-warna) */
            --sidebar-bg: var(--brand-sidebar);

            --sidebar-hover: var(--brand-utama);
            --sidebar-active: var(--brand-utama);
            --topbar-height: 60px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--brand-latar);
            min-height: 100vh;
        }

        /* ===== SIDEBAR ===== */
        #sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            transition: transform 0.3s ease;
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* Scrollbar tipis di sidebar */
        #sidebar::-webkit-scrollbar {
            width: 4px;
        }
        #sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        #sidebar::-webkit-scrollbar-thumb {
            background: rgba(var(--brand-sidebar-teks-rgb),0.2);
            border-radius: 2px;
        }
        #sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(var(--brand-sidebar-teks-rgb),0.4);
        }

        #sidebar .sidebar-brand {
            height: var(--topbar-height);
            display: flex;
            align-items: center;
            padding: 0 1rem;
            background: rgba(0,0,0,0.2);
            border-bottom: 1px solid rgba(var(--brand-sidebar-teks-rgb),0.1);
        }

        #sidebar .sidebar-brand .brand-logo {
            width: 36px;
            height: 36px;
            background: var(--sidebar-active);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: var(--brand-utama-teks);
            font-weight: bold;
            flex-shrink: 0;
        }

        #sidebar .sidebar-brand .brand-text {
            margin-left: 0.75rem;
            color: rgb(var(--brand-sidebar-teks-rgb));
        }

        #sidebar .sidebar-brand .brand-text .brand-name {
            font-size: 0.95rem;
            font-weight: 700;
            line-height: 1.1;
        }

        #sidebar .sidebar-brand .brand-text .brand-sub {
            font-size: 0.7rem;
            color: rgba(var(--brand-sidebar-teks-rgb),0.5);
        }

        #sidebar .nav-section-title {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(var(--brand-sidebar-teks-rgb),0.35);
            padding: 1rem 1rem 0.25rem;
            font-weight: 600;
        }

        #sidebar .nav-link {
            color: rgba(var(--brand-sidebar-teks-rgb),0.7);
            padding: 0.55rem 1rem;
            border-radius: 0;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            font-size: 0.875rem;
            transition: all 0.15s;
        }

        #sidebar .nav-link:hover {
            color: var(--brand-utama-teks);
            background: var(--sidebar-hover);
        }

        #sidebar .nav-link.active {
            color: var(--brand-utama-teks);
            background: var(--sidebar-active);
        }

        #sidebar .nav-link i {
            width: 18px;
            font-size: 1rem;
            flex-shrink: 0;
        }

        #sidebar .nav-link .nav-arrow {
            margin-left: auto;
            font-size: 0.7rem;
            transition: transform 0.2s;
        }

        #sidebar .nav-link[aria-expanded="true"] .nav-arrow {
            transform: rotate(90deg);
        }

        #sidebar .collapse .nav-link {
            padding-left: 2.8rem;
            font-size: 0.825rem;
        }

        /* ===== TOPBAR ===== */
        #topbar {
            height: var(--topbar-height);
            background: white;
            border-bottom: 1px solid var(--brand-utama);
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            z-index: 1030;
            display: flex;
            align-items: center;
            padding: 0 1.25rem;
            gap: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: left 0.3s ease;
        }

        #topbar .sidebar-toggle {
            border: none;
            background: none;
            font-size: 1.25rem;
            color: #64748b;
            padding: 0.25rem;
            cursor: pointer;
            display: block;
        }

        #topbar .page-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0;
        }

        #topbar .topbar-right {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ===== MAIN CONTENT ===== */
        #main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--topbar-height);
            padding: 1.5rem;
            min-height: calc(100vh - var(--topbar-height));
            transition: margin-left 0.3s ease;
        }

        /* ===== SIDEBAR OVERLAY (mobile) ===== */
        #sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1039;
        }

        /* ===== DESKTOP: sidebar hidden state ===== */
        body.sidebar-hidden #sidebar {
            transform: translateX(-100%);
        }
        body.sidebar-hidden #topbar {
            left: 0;
        }
        body.sidebar-hidden #main-content {
            margin-left: 0;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 991.98px) {
            #sidebar {
                transform: translateX(-100%);
            }

            #sidebar.show {
                transform: translateX(0);
            }

            #topbar {
                left: 0;
            }

            #main-content {
                margin-left: 0;
            }

            #sidebar-overlay.show {
                display: block;
            }
        }

        @media (max-width: 767.98px) {
            #main-content {
                padding: 1rem;
            }
        }

        /* ===== CABANG BADGE ===== */
        .cabang-badge {
            font-size: 0.75rem;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
            border-radius: 20px;
            padding: 0.2rem 0.65rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            max-width: 180px;
            white-space: nowrap;
        }
        .cabang-badge-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 120px;
        }
        @media (max-width: 575.98px) {
            .cabang-badge-text { max-width: 80px; }
        }

        /* ===== CABANG SWITCHER DROPDOWN — Tahap 7 D'mentai Bug 1 fix (2026-09-14) =====
           Root cause: dropdown "Pilih Cabang Aktif" tidak punya scroll internal,
           jadi kalau daftar cabang (HO + N outlet + header + footer) lebih
           tinggi dari sisa viewport di bawah topbar (position:fixed), sisanya
           terpotong viewport tanpa cara discroll. Fix: max-height + overflow-y
           auto supaya list SELALU bisa discroll berapapun jumlah outlet-nya
           ke depan (aturan fleksibilitas jumlah outlet, CLAUDE.md 1.4). */
        .cabang-switcher-menu {
            max-height: min(70vh, 480px);
            overflow-y: auto;
            overscroll-behavior: contain;
        }
        @media (max-width: 575.98px) {
            .cabang-switcher-menu { max-height: 60vh; }
        }

        /* ===== STAT CARDS ===== */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid #e2e8f0;
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }

        /* ===== CARDS ===== */
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: none;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
        }

        /* ===== TABLES ===== */
        .table th {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 600;
            background: #f8fafc;
        }

        /* ===== ALERTS ===== */
        .alert {
            border-radius: 10px;
        }

        /* ===== DARK MODE ===== */
        body.dark-mode {
            background-color: #0f172a;
            color: #e2e8f0;
        }
        body.dark-mode #topbar {
            background: #1e293b;
            border-bottom-color: #334155;
        }
        body.dark-mode #topbar .page-title { color: #f1f5f9; }
        body.dark-mode #topbar .sidebar-toggle { color: #94a3b8; }
        body.dark-mode #main-content {
            background-color: #0f172a;
        }
        body.dark-mode .card {
            background: #1e293b;
            border-color: #334155;
            color: #e2e8f0;
        }
        body.dark-mode .card-header {
            background: #1e293b;
            border-bottom-color: #334155;
            color: #f1f5f9;
        }
        body.dark-mode .card-body { color: #e2e8f0; }
        body.dark-mode .table {
            color: #e2e8f0;
            border-color: #334155;
        }
        body.dark-mode .table th {
            background: #0f172a;
            color: #94a3b8;
            border-color: #334155;
        }
        body.dark-mode .table td { border-color: #334155; }
        body.dark-mode .table-hover tbody tr:hover {
            background-color: rgba(255,255,255,0.04);
            color: #f1f5f9;
        }
        body.dark-mode .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(255,255,255,0.03);
        }
        body.dark-mode .form-control,
        body.dark-mode .form-select {
            background-color: #0f172a;
            border-color: #475569;
            color: #e2e8f0;
        }
        body.dark-mode .form-control:focus,
        body.dark-mode .form-select:focus {
            background-color: #0f172a;
            border-color: #3b82f6;
            color: #e2e8f0;
            box-shadow: 0 0 0 0.2rem rgba(59,130,246,0.25);
        }
        body.dark-mode .form-control::placeholder { color: #64748b; }
        body.dark-mode .input-group-text {
            background-color: #1e293b;
            border-color: #475569;
            color: #94a3b8;
        }
        body.dark-mode .dropdown-menu {
            background-color: #1e293b;
            border-color: #334155;
        }
        body.dark-mode .dropdown-item {
            color: #e2e8f0;
        }
        body.dark-mode .dropdown-item:hover {
            background-color: #334155;
            color: #f1f5f9;
        }
        body.dark-mode .dropdown-divider { border-color: #334155; }
        body.dark-mode .dropdown-header { color: #94a3b8; }
        body.dark-mode .modal-content {
            background-color: #1e293b;
            border-color: #334155;
            color: #e2e8f0;
        }
        body.dark-mode .modal-header,
        body.dark-mode .modal-footer {
            border-color: #334155;
        }
        body.dark-mode .alert { border-color: transparent; }
        body.dark-mode .bg-light { background-color: #1e293b !important; }
        body.dark-mode .bg-white { background-color: #1e293b !important; }
        body.dark-mode .text-muted { color: #94a3b8 !important; }
        body.dark-mode .text-dark { color: #e2e8f0 !important; }
        body.dark-mode .border { border-color: #334155 !important; }
        body.dark-mode .border-bottom { border-bottom-color: #334155 !important; }
        body.dark-mode .border-top { border-top-color: #334155 !important; }
        body.dark-mode hr { border-color: #334155; }
        body.dark-mode .stat-card {
            background: #1e293b;
            border-color: #334155;
        }
        body.dark-mode .cabang-badge {
            background: #1e3a5f;
            border-color: #1d4ed8;
            color: #93c5fd;
        }
        body.dark-mode .pagination .page-link {
            background-color: #1e293b;
            border-color: #334155;
            color: #94a3b8;
        }
        body.dark-mode .pagination .page-item.active .page-link {
            background-color: #3b82f6;
            border-color: #3b82f6;
        }
        body.dark-mode .nav-tabs .nav-link {
            color: #94a3b8;
        }
        body.dark-mode .nav-tabs .nav-link.active {
            background-color: #1e293b;
            border-color: #334155 #334155 #1e293b;
            color: #f1f5f9;
        }
        body.dark-mode .list-group-item {
            background-color: #1e293b;
            border-color: #334155;
            color: #e2e8f0;
        }
        body.dark-mode .breadcrumb-item,
        body.dark-mode .breadcrumb-item a { color: #94a3b8; }

        /* Dark mode toggle button */
        #darkModeToggle {
            width: 36px; height: 36px; padding: 0;
            border-radius: 50%;
            transition: background 0.2s;
        }

        /* ===== PRINT ===== */
        @media print {
            #sidebar, #topbar, #sidebar-overlay { display: none !important; }
            #main-content { margin: 0 !important; padding: 0 !important; }
        }

        /* ===== CETAK STRUK DARI MODAL =====
           Saat modal strukModal aktif, Bootstrap menambah class modal-open
           ke <body>. CSS ini menyembunyikan semua kecuali konten struk.   */
        @media print {
            body.modal-open > *:not(#strukModal) { display: none !important; }
            body.modal-open #strukModal           { display: block !important; position: static !important; }
            body.modal-open .modal-dialog         { margin: 0; max-width: none; box-shadow: none; }
            body.modal-open .modal-header,
            body.modal-open .struk-no-print       { display: none !important; }
            body.modal-open .modal-content        { border: none; box-shadow: none; }
            body.modal-open .modal-body           { padding: 0; }
            /* Pertahankan font monospace (thermal printer) */
            body.modal-open .modal-body,
            body.modal-open .modal-body *         { font-family: 'Courier New', monospace !important; }
            /* Dimensi thermal 80mm */
            body.modal-open .struk-wrap           { width: 80mm !important; padding: 2mm !important; }
            @page { margin: 0; size: 80mm auto; }
        }
    </style>

    @stack('styles')
</head>
<body>

<!-- Sidebar Overlay (mobile) -->
<div id="sidebar-overlay" onclick="closeSidebar()"></div>

<!-- ===== SIDEBAR ===== -->
<nav id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">
            <img src="{{ $brand->logoUrl() }}" alt="{{ $brand->namaSingkat() }}" style="width:100%;height:100%;object-fit:contain;border-radius:8px;padding:3px;">
        </div>
        <div class="brand-text">
            <div class="brand-name">{{ $brand->namaSingkat() }}</div>
            @if($brand->tagline())<div class="brand-sub">{{ $brand->tagline() }}</div>@endif
        </div>
    </div>

    <div class="py-2">

        <!-- Dashboard -->
        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard','dashboard.cabang','dashboard.gudang','dashboard.pusat') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        @if(isset($authUser) && $authUser->canAccessAllBranches())
        <a href="{{ route('dashboard.pusat') }}" class="nav-link {{ request()->routeIs('dashboard.pusat') ? 'active' : '' }}" style="padding-left:2.8rem;font-size:0.825rem">
            <i class="bi bi-globe"></i><span>Semua Cabang</span>
        </a>
        @endif

        {{-- 4.44 Poin 1 — hanya tampil kalau ERP dibuka dari aplikasi Android "Kasir POS" (lihat components/apk-printer) --}}
        <a href="#" id="kdApkSettingsLink" class="nav-link" style="display:none"
           onclick="window.KDApk && window.KDApk.bukaPengaturan(); return false;">
            <i class="bi bi-printer"></i>
            <span>Pengaturan Printer Aplikasi</span>
        </a>

        <!-- PENJUALAN -->
        @canany(['order.view', 'order.create', 'pelanggan.view', 'loyalty.view',
                 'penjualan.laporan_transaksi.view', 'penjualan.laporan_produk.view', 'penjualan.laporan_kategori.view'])
        <div class="nav-section-title">Penjualan</div>
        @can('order.create')
        <a href="{{ route('penjualan.pos') }}"
           class="nav-link {{ request()->routeIs('penjualan.pos') ? 'active' : '' }}">
            <i class="bi bi-cart-plus"></i>
            <span>POS / Kasir</span>
        </a>
        @endcan
        @can('order.view')
        <a href="{{ route('penjualan.index') }}"
           class="nav-link {{ request()->routeIs('penjualan.index','penjualan.show') ? 'active' : '' }}">
            <i class="bi bi-cart3"></i>
            <span>Riwayat Order</span>
        </a>
        @endcan
        {{-- 4.46 — laporan penjualan yang bisa dibuka kasir --}}
        @can('penjualan.laporan_transaksi.view')
        <a href="{{ route('penjualan.laporan.transaksi') }}"
           class="nav-link {{ request()->routeIs('penjualan.laporan.transaksi') ? 'active' : '' }}">
            <i class="bi bi-graph-up-arrow"></i>
            <span>Laporan Transaksi</span>
        </a>
        @endcan
        @can('penjualan.laporan_produk.view')
        <a href="{{ route('penjualan.laporan.produk') }}"
           class="nav-link {{ request()->routeIs('penjualan.laporan.produk') ? 'active' : '' }}">
            <i class="bi bi-box-seam"></i>
            <span>Laporan Produk</span>
        </a>
        @endcan
        @can('penjualan.laporan_kategori.view')
        <a href="{{ route('penjualan.laporan.kategori') }}"
           class="nav-link {{ request()->routeIs('penjualan.laporan.kategori') ? 'active' : '' }}">
            <i class="bi bi-tags"></i>
            <span>Laporan Kategori</span>
        </a>
        @endcan
        @can('pelanggan.view')
        <a href="{{ route('pelanggan.index') }}"
           class="nav-link {{ request()->routeIs('pelanggan.*') ? 'active' : '' }}">
            <i class="bi bi-person-heart"></i>
            <span>Pelanggan</span>
        </a>
        @endcan
        {{-- BABA: menu Jenis Menu & Master Bumbu Pusat (khusus dimsum) dihapus — diganti Resep Parfum (Tahap 1) --}}
        @can('loyalty.view')
        <a href="{{ route('loyalty-program.index') }}"
           class="nav-link {{ request()->routeIs('loyalty-program.*') ? 'active' : '' }}">
            <i class="bi bi-award"></i>
            <span>Program Loyalty</span>
        </a>
        @endcan
        @endcanany

        {{-- BABA: grup Antrian Produksi (Cek/Kelola Antrian, Mode Produksi, Display TV — khusus dimsum) dihapus;
             antrian mati sebagai bawaan per cabang (migration 2026_11_11_000001) --}}

        <!-- TOKO ONLINE (BABA Tahap 2: produk, harga, pesanan menu Belanja aplikasi member) -->
        @if(auth()->check() && (auth()->user()->can('toko_online.kelola') || auth()->user()->can('toko_online.pesanan')))
        <div class="nav-section-title">Toko Online</div>
        @can('toko_online.pesanan')
        @php
            try {
                $jmlPesananPerlu = \App\Models\PesananOnline::whereIn('sumber_cabang_id', \App\Services\ProduksiService::gudangUntuk()->pluck('id'))
                    ->where(fn ($w) => $w->whereIn('status', ['menunggu_ongkir', 'menunggu_cek', 'disiapkan'])->orWhere(fn ($t) => $t->where('status_bayar', 'menunggu_cek')->where('status', '!=', 'batal')))->count();
            } catch (\Throwable $e) { $jmlPesananPerlu = 0; }
        @endphp
        <a href="{{ route('toko-online.pesanan.index') }}"
           class="nav-link {{ request()->routeIs('toko-online.pesanan.*') ? 'active' : '' }}">
            <i class="bi bi-bag-check"></i>
            <span>Pesanan Online</span>
            @if($jmlPesananPerlu > 0)<span class="badge rounded-pill bg-danger ms-auto" title="Perlu tindakan">{{ $jmlPesananPerlu }}</span>@endif
        </a>
        @php
            try {
                $jmlReturDiajukan = \App\Models\ReturOnline::where('sumber', 'gudang')->where('status', 'diajukan')
                    ->whereIn('sumber_cabang_id', \App\Services\ProduksiService::gudangUntuk()->pluck('id'))->count();
            } catch (\Throwable $e) { $jmlReturDiajukan = 0; }
        @endphp
        <a href="{{ route('toko-online.retur.index') }}"
           class="nav-link {{ request()->routeIs('toko-online.retur.*') ? 'active' : '' }}">
            <i class="bi bi-arrow-return-left"></i>
            <span>Retur Online</span>
            @if($jmlReturDiajukan > 0)<span class="badge rounded-pill bg-danger ms-auto" title="Retur diajukan">{{ $jmlReturDiajukan }}</span>@endif
        </a>
        @endcan
        @can('toko_online.kelola')
        <a href="{{ route('toko-online.produk.index') }}"
           class="nav-link {{ request()->routeIs('toko-online.produk.*') ? 'active' : '' }}">
            <i class="bi bi-bag-heart"></i>
            <span>Produk Online</span>
        </a>
        <a href="{{ route('toko-online.stok-member.index') }}"
           class="nav-link {{ request()->routeIs('toko-online.stok-member.*') ? 'active' : '' }}">
            <i class="bi bi-people"></i>
            <span>Stok Member</span>
        </a>
        @php try { $jmlRoGagal = \App\Models\RoCatatan::where('status', 'gagal')->count(); } catch (\Throwable $e) { $jmlRoGagal = 0; } @endphp
        <a href="{{ route('toko-online.ro.index') }}"
           class="nav-link {{ request()->routeIs('toko-online.ro.*') ? 'active' : '' }}">
            <i class="bi bi-star"></i>
            <span>Catatan RO</span>
            @if($jmlRoGagal > 0)<span class="badge rounded-pill bg-danger ms-auto" title="Gagal terkirim">{{ $jmlRoGagal }}</span>@endif
        </a>
        <a href="{{ route('toko-online.pengaturan') }}"
           class="nav-link {{ request()->routeIs('toko-online.pengaturan*') ? 'active' : '' }}">
            <i class="bi bi-sliders"></i>
            <span>Pengaturan Toko Online</span>
        </a>
        @endcan
        @endif

        <!-- STOK & GUDANG -->
        @canany(['stok.view', 'stok.request', 'stok.transfer', 'master.bahan_baku.view', 'master.produk_jual.view', 'item.view', 'pemakaian_perlengkapan.view', 'produksi.view', 'kiriman.view', 'retur.view'])
        <div class="nav-section-title">Stok & Gudang</div>
        @can('stok.view')
        <a href="{{ route('stok.dashboard') }}"
           class="nav-link {{ request()->routeIs('stok.dashboard','stok.dashboard.item-batches') ? 'active' : '' }}">
            <i class="bi bi-graph-up"></i>
            <span>Dashboard Stok</span>
        </a>
        <a href="{{ route('stok.index') }}"
           class="nav-link {{ request()->routeIs('stok.index','stok.kartu','stok.adjustment') ? 'active' : '' }}">
            <i class="bi bi-boxes"></i>
            <span>Stok Barang</span>
        </a>
        @endcan
        @can('stok.request')
        <a href="{{ route('stock-request.index') }}"
           class="nav-link {{ request()->routeIs('stock-request.*') ? 'active' : '' }}">
            <i class="bi bi-arrow-left-right"></i>
            <span>Permintaan Stok</span>
        </a>
        @endcan
        @can('stok.transfer')
        <a href="{{ route('stock-transfer.index') }}"
           class="nav-link {{ request()->routeIs('stock-transfer.*') ? 'active' : '' }}">
            <i class="bi bi-truck"></i>
            <span>Transfer Stok</span>
        </a>
        @endcan
        {{-- 4.49 Tahap B1 — Produksi bahan setengah jadi di gudang --}}
        @can('produksi.view')
        <a href="{{ route('produksi.index') }}"
           class="nav-link {{ request()->routeIs('produksi.*') ? 'active' : '' }}">
            <i class="bi bi-fire"></i>
            <span>Produksi</span>
        </a>
        @endcan
        {{-- 4.49 Tahap B2 — Kiriman Gudang → cabang (badge = menunggu diterima cabang aktif) --}}
        @can('kiriman.view')
        @php $jumlahKirimanMenunggu = \App\Http\Controllers\KirimanCabangController::jumlahMenunggu(); @endphp
        <a href="{{ route('kiriman.index') }}"
           class="nav-link {{ request()->routeIs('kiriman.*') ? 'active' : '' }}">
            <i class="bi bi-truck-flatbed"></i>
            <span>Kiriman Cabang</span>
            @if($jumlahKirimanMenunggu > 0)<span class="badge bg-danger ms-auto">{{ $jumlahKirimanMenunggu }}</span>@endif
        </a>
        @endcan
        {{-- 4.49 Tahap B3 — Retur cabang → Gudang (badge = retur menunggu diterima Gudang aktif) --}}
        @can('retur.view')
        @php $returMenunggu = \App\Services\ReturInternalService::jumlahMenunggu(); @endphp
        <a href="{{ route('retur-cabang.index') }}"
           class="nav-link {{ request()->routeIs('retur-cabang.*') ? 'active' : '' }}">
            <i class="bi bi-arrow-return-left"></i>
            <span>Retur Cabang</span>
            @if($returMenunggu > 0)<span class="badge bg-danger ms-auto">{{ $returMenunggu }}</span>@endif
        </a>
        @endcan
        @can('master.bahan_baku.view')
        <a href="{{ route('master.bahan-baku.index') }}"
           class="nav-link {{ request()->routeIs('master.bahan-baku.*') ? 'active' : '' }}">
            <i class="bi bi-box-seam"></i>
            <span>Bahan Baku &amp; Kemasan</span>
        </a>
        @endcan
        @can('master.produk_jual.view')
        <a href="{{ route('master.produk-jual.index') }}"
           class="nav-link {{ request()->routeIs('master.produk-jual.*') ? 'active' : '' }}">
            <i class="bi bi-cup-hot"></i>
            <span>Produk Jual</span>
        </a>
        @endcan
        @can('item.view')
        <a href="{{ route('item.index') }}"
           class="nav-link {{ request()->routeIs('item.*') ? 'active' : '' }}"
           title="Overview semua barang lintas tipe (read-only). CRUD lewat menu Bahan Baku atau Produk Jual.">
            <i class="bi bi-card-list"></i>
            <span>Semua Barang (Overview)</span>
        </a>
        @endcan
        @can('pemakaian_perlengkapan.view')
        <a href="{{ route('pemakaian-perlengkapan.index') }}"
           class="nav-link {{ request()->routeIs('pemakaian-perlengkapan.*') ? 'active' : '' }}">
            <i class="bi bi-box-arrow-up"></i>
            <span>Pemakaian Perlengkapan</span>
        </a>
        @endcan
        @endcanany

        <!-- PEMBELIAN -->
        @canany(['pembelian.view', 'po_dashboard.view', 'supplier.view'])
        <div class="nav-section-title">Pembelian</div>
        @can('pembelian.view')
        <a href="{{ route('pembelian.index') }}"
           class="nav-link {{ request()->routeIs('pembelian.index','pembelian.create','pembelian.show') ? 'active' : '' }}">
            <i class="bi bi-bag-check"></i>
            <span>Purchase Order</span>
        </a>
        @endcan
        @can('supplier.view')
        <a href="{{ route('supplier.index') }}"
           class="nav-link {{ request()->routeIs('supplier.*') ? 'active' : '' }}">
            <i class="bi bi-people"></i>
            <span>Supplier</span>
        </a>
        @endcan
        @can('po_dashboard.view')
        <a href="{{ route('pembelian.po-dashboard.index') }}"
           class="nav-link {{ request()->routeIs('pembelian.po-dashboard.*') ? 'active' : '' }}">
            <i class="bi bi-clipboard-data"></i>
            <span>Dashboard PO</span>
        </a>
        @endcan
        @endcanany

        <!-- KEUANGAN -->
        @canany(['keuangan.view', 'keuangan.create', 'setoran.view', 'setoran_kasir.view', 'kategori.view', 'bep.view', 'coa.view', 'recurring.view', 'transfer_antar_kas.view', 'closing.view'])
        <div class="nav-section-title">Keuangan</div>
        @can('keuangan.view')
        <a href="{{ route('keuangan.dashboard') }}" class="nav-link {{ request()->routeIs('keuangan.dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard Keuangan</span>
        </a>
        <a href="{{ route('keuangan.index') }}" class="nav-link {{ request()->routeIs('keuangan.index','keuangan.create','keuangan.edit') ? 'active' : '' }}">
            <i class="bi bi-cash-stack"></i>
            <span>Kas & Transaksi</span>
        </a>
        @can('laporan.keuangan.view')
        <a href="{{ route('keuangan.laporan') }}" class="nav-link {{ request()->routeIs('keuangan.laporan') ? 'active' : '' }}">
            <i class="bi bi-file-earmark-text"></i>
            <span>Laporan Keuangan</span>
        </a>
        @endcan
        @endcan
        @can('transfer_antar_kas.view')
        <a href="{{ route('transfer-antar-kas.index') }}" class="nav-link {{ request()->routeIs('transfer-antar-kas.*') ? 'active' : '' }}">
            <i class="bi bi-arrow-down-up"></i>
            <span>Transfer Antar Kas</span>
        </a>
        @endcan
        @can('setoran.view')
        <a href="{{ route('setoran.index') }}" class="nav-link {{ request()->routeIs('setoran.*') ? 'active' : '' }} d-flex align-items-center">
            <i class="bi bi-arrow-left-right"></i>
            <span>Transfer / Perpindahan Dana</span>
            @can('setoran.terima')
            @php
                $pendingSetoran = \App\Models\TransaksiKeuangan::withoutGlobalScopes()
                    ->whereHas('kategoriDinamis', fn($q) => $q->where('kode', 'SETOR-OUT'))
                    ->where('status_setoran', 'menunggu_diterima')
                    ->count();
            @endphp
            @if($pendingSetoran > 0)
            <span class="badge bg-danger rounded-pill ms-auto" style="font-size:0.65rem">{{ $pendingSetoran }}</span>
            @endif
            @endcan
        </a>
        @endcan
        @can('setoran_kasir.view')
        <a href="{{ route('setoran-kasir.index') }}" class="nav-link {{ request()->routeIs('setoran-kasir.*') ? 'active' : '' }} d-flex align-items-center">
            <i class="bi bi-cash-coin"></i>
            <span>Setoran Kasir</span>
            @can('setoran_kasir.approve')
            @php
                $pendingSetoranKasir = \App\Models\Setoran::where('status', 'menunggu')->count();
            @endphp
            @if($pendingSetoranKasir > 0)
            <span class="badge bg-danger rounded-pill ms-auto" style="font-size:0.65rem">{{ $pendingSetoranKasir }}</span>
            @endif
            @endcan
        </a>
        @endcan
        @can('closing.view')
        <a href="{{ route('closing.index') }}" class="nav-link {{ request()->routeIs('closing.*') ? 'active' : '' }}">
            <i class="bi bi-journal-check"></i>
            <span>Closing / Tutup Buku</span>
        </a>
        @endcan
        @can('kategori.view')
        <a href="{{ route('kategori-transaksi.index') }}" class="nav-link {{ request()->routeIs('kategori-transaksi.*') ? 'active' : '' }}">
            <i class="bi bi-tags"></i>
            <span>Kategori Transaksi</span>
        </a>
        @endcan
        @can('coa.view')
        <a href="{{ route('coa.index') }}" class="nav-link {{ request()->routeIs('coa.*') ? 'active' : '' }}">
            <i class="bi bi-diagram-2"></i>
            <span>Chart of Accounts</span>
        </a>
        @endcan
        @can('bep.view')
        <a href="{{ route('bep.index') }}" class="nav-link {{ request()->routeIs('bep.*') ? 'active' : '' }}">
            <i class="bi bi-graph-up-arrow"></i>
            <span>Analisis BEP</span>
        </a>
        @endcan
        @can('recurring.view')
        <a href="{{ route('recurring.index') }}" class="nav-link {{ request()->routeIs('recurring.*') ? 'active' : '' }}">
            <i class="bi bi-arrow-repeat"></i>
            <span>Transaksi Berulang</span>
        </a>
        @endcan
        @endcanany

        <!-- HR / SDM -->
        @canany(['karyawan.view', 'absensi.view', 'absensi.manage', 'scan_absensi', 'dashboard_absensi', 'laporan_absensi', 'registrasi_wajah', 'kelola_shift', 'kelola_hari_libur', 'penggajian.view', 'cuti.view', 'evaluasi.view', 'evaluasi.fill'])
        <div class="nav-section-title">SDM & Karyawan</div>
        @can('karyawan.view')
        <a href="{{ route('karyawan.index') }}"
           class="nav-link {{ request()->routeIs('karyawan.*') ? 'active' : '' }}">
            <i class="bi bi-people"></i>
            <span>Karyawan</span>
        </a>
        @endcan
        {{-- ABSENSI (collapsible) --}}
        @php
        $absensiActive = request()->routeIs(
            'absensi.index','absensi.store','absensi.edit','absensi.update','absensi.rekap',
            'absensi-saya.index','dashboard.absensi',
            'face-attendance.scan','face-attendance.today','face-attendance.log',
            'face-registration.*','shift.*','hari-libur.*','laporan.absensi'
        );
        @endphp
        <a href="#absensiMenu" data-bs-toggle="collapse"
           class="nav-link {{ $absensiActive ? 'active' : '' }}"
           aria-expanded="{{ $absensiActive ? 'true' : 'false' }}">
            <i class="bi bi-clipboard-check"></i>
            <span>Absensi</span>
            <i class="bi bi-chevron-right nav-arrow"></i>
        </a>
        <div class="collapse {{ $absensiActive ? 'show' : '' }}" id="absensiMenu">
            @can('dashboard_absensi')
            <a href="{{ route('dashboard.absensi') }}" class="nav-link {{ request()->routeIs('dashboard.absensi') ? 'active' : '' }}">
                <i class="bi bi-graph-up"></i><span>Dashboard Absensi</span>
            </a>
            @endcan
            @can('scan_absensi')
            <a href="{{ route('face-attendance.scan') }}" class="nav-link {{ request()->routeIs('face-attendance.scan') ? 'active' : '' }}">
                <i class="bi bi-person-bounding-box"></i><span>Scan Absensi</span>
            </a>
            @endcan
            @can('absensi.manage')
            <a href="{{ route('absensi.index') }}" class="nav-link {{ request()->routeIs('absensi.index','absensi.store','absensi.edit','absensi.update') ? 'active' : '' }}">
                <i class="bi bi-calendar-check"></i><span>Absensi Manual</span>
            </a>
            @endcan
            <a href="{{ route('absensi-saya.index') }}" class="nav-link {{ request()->routeIs('absensi-saya.*') ? 'active' : '' }}">
                <i class="bi bi-person-check"></i><span>Absensi Saya</span>
            </a>
            @can('absensi.manage')
            <a href="{{ route('face-attendance.today') }}" class="nav-link {{ request()->routeIs('face-attendance.today','face-attendance.log') ? 'active' : '' }}">
                <i class="bi bi-clock-history"></i><span>Log Absensi Wajah</span>
            </a>
            <a href="{{ route('absensi.rekap') }}" class="nav-link {{ request()->routeIs('absensi.rekap') ? 'active' : '' }}">
                <i class="bi bi-calendar3"></i><span>Rekap Bulanan</span>
            </a>
            @endcan
            @can('laporan_absensi')
            <a href="{{ route('laporan.absensi') }}" class="nav-link {{ request()->routeIs('laporan.absensi*') ? 'active' : '' }}">
                <i class="bi bi-camera-video"></i><span>Laporan Absensi</span>
            </a>
            @endcan
            @can('registrasi_wajah')
            <a href="{{ route('face-registration.index') }}" class="nav-link {{ request()->routeIs('face-registration.*') ? 'active' : '' }}">
                <i class="bi bi-person-video3"></i><span>Registrasi Wajah</span>
            </a>
            @endcan
            @can('kelola_shift')
            <a href="{{ route('shift.index') }}" class="nav-link {{ request()->routeIs('shift.*') ? 'active' : '' }}">
                <i class="bi bi-clock"></i><span>Kelola Shift</span>
            </a>
            @endcan
            @can('kelola_hari_libur')
            <a href="{{ route('hari-libur.index') }}" class="nav-link {{ request()->routeIs('hari-libur.*') ? 'active' : '' }}">
                <i class="bi bi-calendar2-x"></i><span>Hari Libur</span>
            </a>
            @endcan
        </div>
        @can('penggajian.view')
        <a href="{{ route('penggajian.index') }}"
           class="nav-link {{ request()->routeIs('penggajian.*') ? 'active' : '' }}">
            <i class="bi bi-cash-stack"></i>
            <span>Penggajian</span>
        </a>
        @endcan
        @can('cuti.view')
        <a href="{{ route('cuti.index') }}"
           class="nav-link {{ request()->routeIs('cuti.*') ? 'active' : '' }}">
            <i class="bi bi-calendar-x"></i>
            <span>Cuti & Izin</span>
        </a>
        @endcan
        @canany(['evaluasi.view', 'evaluasi.fill'])
        @can('evaluasi.view')
        <a href="{{ route('evaluasi.periods') }}"
           class="nav-link {{ request()->routeIs('evaluasi.periods') || request()->routeIs('evaluasi.period-*') || request()->routeIs('evaluasi.ranking') ? 'active' : '' }}">
            <i class="bi bi-star-half"></i>
            <span>Penilaian 360°</span>
        </a>
        @endcan
        <a href="{{ route('evaluasi.my-reviews') }}"
           class="nav-link {{ request()->routeIs('evaluasi.my-reviews') ? 'active' : '' }}">
            <i class="bi bi-clipboard2-check"></i>
            <span class="d-flex align-items-center gap-2">
                Penilaian Saya
                @php
                    $pendingReviewCount = \App\Models\EvaluationReviewer::where('reviewer_id', auth()->id())
                        ->where('status', 'belum_isi')
                        ->whereHas('evaluation.period', fn($q) => $q->whereIn('status', ['dibuka','ditutup']))
                        ->count();
                @endphp
                @if($pendingReviewCount > 0)
                <span class="badge bg-warning text-dark rounded-pill" style="font-size:.65rem">{{ $pendingReviewCount }}</span>
                @endif
            </span>
        </a>
        @endcanany
        @endcanany

        <!-- ASET -->
        @can('aset.view')
        <div class="nav-section-title">Aset</div>
        <a href="{{ route('aset.index') }}" class="nav-link {{ request()->routeIs('aset.*') ? 'active' : '' }}">
            <i class="bi bi-building-gear"></i>
            <span>Manajemen Aset</span>
        </a>
        @endcan

        <!-- LAPORAN -->
        @canany(\App\Support\HakAksesMenu::IZIN_LAPORAN)
        <div class="nav-section-title">Laporan</div>
        @php $laporanActive = request()->routeIs('laporan.*'); @endphp
        <a href="#laporanMenu" data-bs-toggle="collapse" class="nav-link {{ $laporanActive ? 'active' : '' }}" aria-expanded="{{ $laporanActive ? 'true' : 'false' }}">
            <i class="bi bi-file-earmark-bar-graph"></i>
            <span>Laporan</span>
            <i class="bi bi-chevron-right nav-arrow"></i>
        </a>
        <div class="collapse {{ $laporanActive ? 'show' : '' }}" id="laporanMenu">
            @can('laporan.penjualan.view')
            <a href="{{ route('laporan.penjualan') }}" class="nav-link {{ request()->routeIs('laporan.penjualan') ? 'active' : '' }}">
                <i class="bi bi-cart3"></i><span>Penjualan</span>
            </a>
            @endcan
            @can('laporan.stok.view')
            <a href="{{ route('laporan.stok') }}" class="nav-link {{ request()->routeIs('laporan.stok','laporan.stok.pergerakan','laporan.stok.minimum') ? 'active' : '' }}">
                <i class="bi bi-boxes"></i><span>Stok</span>
            </a>
            @endcan
            @can('laporan.keuangan.view')
            <a href="{{ route('laporan.keuangan.laba-rugi') }}" class="nav-link {{ request()->routeIs('laporan.keuangan.*') ? 'active' : '' }}">
                <i class="bi bi-cash-stack"></i><span>Keuangan</span>
            </a>
            @endcan
            @canany(['laporan.hr_absensi.view', 'laporan.hr_penggajian.view', 'laporan.hr_evaluasi.view'])
            <a href="{{ route(auth()->user()->can('laporan.hr_absensi.view') ? 'laporan.hr.absensi' : (auth()->user()->can('laporan.hr_penggajian.view') ? 'laporan.hr.penggajian' : 'laporan.hr.evaluasi')) }}" class="nav-link {{ request()->routeIs('laporan.hr.*') ? 'active' : '' }}">
                <i class="bi bi-people"></i><span>HR / SDM</span>
            </a>
            @endcanany
            @can('laporan.aset.view')
            <a href="{{ route('laporan.aset') }}" class="nav-link {{ request()->routeIs('laporan.aset','laporan.aset.penyusutan','laporan.aset.maintenance') ? 'active' : '' }}">
                <i class="bi bi-building-gear"></i><span>Aset</span>
            </a>
            @endcan
            @can('laporan.bep.view')
            <a href="{{ route('laporan.bep') }}" class="nav-link {{ request()->routeIs('laporan.bep','laporan.bep.per-cabang') ? 'active' : '' }}">
                <i class="bi bi-graph-up-arrow"></i><span>BEP</span>
            </a>
            @endcan
            @can('laporan.bep_otomatis.view')
            <a href="{{ route('laporan.bep-otomatis.index') }}" class="nav-link {{ request()->routeIs('laporan.bep-otomatis.*') ? 'active' : '' }}">
                <i class="bi bi-speedometer2"></i><span>BEP Otomatis</span>
            </a>
            @endcan
            @can('laporan.transfer_dana.view')
            <a href="{{ route('laporan.setoran') }}" class="nav-link {{ request()->routeIs('laporan.setoran') ? 'active' : '' }}">
                <i class="bi bi-arrow-left-right"></i><span>Transfer / Perpindahan Dana</span>
            </a>
            @endcan
            @can('laporan.kategori.view')
            <a href="{{ route('laporan.kategori') }}" class="nav-link {{ request()->routeIs('laporan.kategori') ? 'active' : '' }}">
                <i class="bi bi-tags"></i><span>Per Kategori</span>
            </a>
            @endcan
            @can('laporan.audit_bukti.view')
            <a href="{{ route('laporan.audit-bukti') }}" class="nav-link {{ request()->routeIs('laporan.audit-bukti') ? 'active' : '' }}">
                <i class="bi bi-shield-check"></i><span>Audit Bukti</span>
            </a>
            @endcan
            @can('laporan.saldo_kas.view')
            <a href="{{ route('laporan.saldo-kas') }}" class="nav-link {{ request()->routeIs('laporan.saldo-kas') ? 'active' : '' }}">
                <i class="bi bi-wallet2"></i><span>Saldo Kas</span>
            </a>
            @endcan
            @can('laporan.setoran_harian.view')
            <a href="{{ route('laporan.setoran-harian.index') }}" class="nav-link {{ request()->routeIs('laporan.setoran-harian.*') ? 'active' : '' }}">
                <i class="bi bi-cash-coin"></i><span>Setoran Harian</span>
            </a>
            @endcan
            @can('laporan.konsumsi_bahan.view')
            <a href="{{ route('laporan.konsumsi-bahan.index') }}" class="nav-link {{ request()->routeIs('laporan.konsumsi-bahan.*') ? 'active' : '' }}">
                <i class="bi bi-basket3"></i><span>Konsumsi Bahan Baku</span>
            </a>
            @endcan
            @can('laporan.laba_rugi.view')
            <a href="{{ route('laporan.laba-rugi.index') }}" class="nav-link {{ request()->routeIs('laporan.laba-rugi.*') ? 'active' : '' }}">
                <i class="bi bi-graph-up-arrow"></i><span>Laba Rugi</span>
            </a>
            @endcan
            @can('laporan.laba_rugi_formal.view')
            <a href="{{ route('laporan.laba-rugi-formal.index') }}" class="nav-link {{ request()->routeIs('laporan.laba-rugi-formal.*') ? 'active' : '' }}">
                <i class="bi bi-journal-text"></i><span>Laba Rugi Formal</span>
            </a>
            @endcan
            @can('laporan.neraca.view')
            <a href="{{ route('laporan.neraca.index') }}" class="nav-link {{ request()->routeIs('laporan.neraca.*') ? 'active' : '' }}">
                <i class="bi bi-clipboard2-data"></i><span>Neraca</span>
            </a>
            @endcan
            @can('laporan.buku_besar.view')
            <a href="{{ route('laporan.buku-besar.index') }}" class="nav-link {{ request()->routeIs('laporan.buku-besar.*') ? 'active' : '' }}">
                <i class="bi bi-journal-bookmark"></i><span>Buku Besar</span>
            </a>
            @endcan
            @can('laporan.eksekutif.view')
            <a href="{{ route('laporan.eksekutif.index') }}" class="nav-link {{ request()->routeIs('laporan.eksekutif.*') ? 'active' : '' }}">
                <i class="bi bi-easel"></i><span>Laporan Eksekutif</span>
            </a>
            @endcan
            @can('laporan.simulator.view')
            <a href="{{ route('laporan.simulator-bep.index') }}" class="nav-link {{ request()->routeIs('laporan.simulator-bep.*') ? 'active' : '' }}">
                <i class="bi bi-sliders"></i><span>Simulator BEP</span>
            </a>
            @endcan
            @can('laporan.jam_ramai.view')
            <a href="{{ route('laporan.jam-ramai.index') }}" class="nav-link {{ request()->routeIs('laporan.jam-ramai.*') ? 'active' : '' }}">
                <i class="bi bi-clock-history"></i><span>Analisa Jam Ramai</span>
            </a>
            @endcan
            @can('laporan.perlengkapan.view')
            <a href="{{ route('laporan.perlengkapan.index') }}" class="nav-link {{ request()->routeIs('laporan.perlengkapan.*') ? 'active' : '' }}">
                <i class="bi bi-clipboard-data"></i><span>Pemakaian Perlengkapan</span>
            </a>
            @endcan
            @can('laporan.setoran_kasir.view')
            <a href="{{ route('laporan.setoran-kasir.index') }}" class="nav-link {{ request()->routeIs('laporan.setoran-kasir.*') ? 'active' : '' }}">
                <i class="bi bi-cash-coin"></i><span>Laporan Setoran Kasir</span>
            </a>
            @endcan
            @if(auth()->check() && auth()->user()->can('laporan.cabang_vs_cabang.view') && auth()->user()->canAccessAllBranches())
            <a href="{{ route('laporan.cabang-vs-cabang') }}" class="nav-link {{ request()->routeIs('laporan.cabang-vs-cabang') ? 'active' : '' }}">
                <i class="bi bi-bar-chart-steps"></i><span>Cabang vs Cabang</span>
            </a>
            @endif
        </div>
        @endcanany

        <!-- PENGATURAN -->
        @if(auth()->check() && (auth()->user()->canAny(['cabang.view', 'user.view', 'pengaturan_penggajian', 'panduan.kelola']) || (isset($authUser) && $authUser->role?->value === 'owner')))
        <div class="nav-section-title">Pengaturan</div>
        @endif
        @can('cabang.view')
        <a href="{{ route('cabang.index') }}"
           class="nav-link {{ request()->routeIs('cabang.*') ? 'active' : '' }}">
            <i class="bi bi-diagram-3"></i>
            <span>Cabang & Gudang</span>
        </a>
        @endcan
        @can('user.view')
        <a href="{{ route('user.index') }}"
           class="nav-link {{ request()->routeIs('user.*') ? 'active' : '' }}">
            <i class="bi bi-people-fill"></i>
            <span>Manajemen User</span>
        </a>
        @endcan
        @if(isset($authUser) && $authUser->role?->value === 'owner')
        <a href="{{ route('role.index') }}"
           class="nav-link {{ request()->routeIs('role.*') ? 'active' : '' }}">
            <i class="bi bi-shield-lock"></i>
            <span>Role & Hak Akses</span>
        </a>
        @endif
        {{-- 4.43: Pengaturan Umum tetap khusus Owner --}}
        @if(isset($authUser) && $authUser->role?->value === 'owner')
        <a href="{{ route('pengaturan.umum') }}"
           class="nav-link {{ request()->routeIs('pengaturan.umum*') ? 'active' : '' }}">
            <i class="bi bi-building-gear"></i>
            <span>Pengaturan Umum</span>
        </a>
        @endif
        @can('pengaturan_penggajian')
        <a href="{{ route('pengaturan.penggajian') }}"
           class="nav-link {{ request()->routeIs('pengaturan.penggajian*') ? 'active' : '' }}">
            <i class="bi bi-sliders"></i>
            <span>Pengaturan Penggajian</span>
        </a>
        @endcan
        @can('panduan.kelola')
        <a href="{{ route('admin.tooltips.index') }}"
           class="nav-link {{ request()->routeIs('admin.tooltips.*') ? 'active' : '' }}">
            <i class="bi bi-question-circle"></i>
            <span>Tooltip Helper</span>
        </a>
        <a href="{{ route('admin.panduan.index') }}"
           class="nav-link {{ request()->routeIs('admin.panduan.*') ? 'active' : '' }}">
            <i class="bi bi-book"></i>
            <span>Panduan Helper</span>
        </a>
        @endcan

        <!-- BANTUAN -->
        <div class="nav-section-title mt-2">Bantuan</div>
        <a href="{{ route('panduan.index') }}"
           class="nav-link {{ request()->routeIs('panduan.*') ? 'active' : '' }}">
            <i class="bi bi-journal-text"></i>
            <span>Panduan</span>
        </a>

        <!-- KEAMANAN -->
        @if(isset($authUser) && $authUser->canAny(['lihat_audit_log', 'lihat_data_terhapus', 'lihat_backup']))
        <div class="nav-section-title mt-2">Keamanan</div>
        @can('lihat_audit_log')
        <a href="{{ route('audit-log.index') }}"
           class="nav-link {{ request()->routeIs('audit-log.*') ? 'active' : '' }}">
            <i class="bi bi-journal-text"></i>
            <span>Audit Log</span>
        </a>
        @endcan
        @can('lihat_data_terhapus')
        <a href="{{ route('trash.index') }}"
           class="nav-link {{ request()->routeIs('trash.*') ? 'active' : '' }}">
            <i class="bi bi-trash3"></i>
            <span>Data Terhapus</span>
        </a>
        @endcan
        @can('lihat_backup')
        <a href="{{ route('backup.index') }}"
           class="nav-link {{ request()->routeIs('backup.*') ? 'active' : '' }}">
            <i class="bi bi-hdd"></i>
            <span>Backup Database</span>
        </a>
        @endcan
        @endif

    </div>
</nav>

<!-- ===== TOPBAR ===== -->
<header id="topbar">
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>

    <h1 class="page-title d-none d-sm-block">@yield('title', 'Dashboard')</h1>

    <div class="topbar-right">

        <!-- Cabang Switcher Component -->
        <x-cabang-switcher />

        <!-- Dark Mode Toggle -->
        <button id="darkModeToggle" class="btn btn-sm btn-light"
                onclick="toggleDarkMode()" title="Ganti tema">
            <i class="bi bi-moon-stars" id="darkModeIcon" style="font-size:0.9rem"></i>
        </button>

        <!-- Bell Notification Component -->
        <x-notification-bell />

        <!-- User dropdown -->
        <div class="dropdown">
            <button class="btn btn-sm p-0 border-0 bg-transparent d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <div style="width:34px;height:34px;border-radius:50%;background:#3b82f6;color:white;display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:600">
                    {{ substr(auth()->user()?->name ?? 'U', 0, 1) }}
                </div>
                <div class="d-none d-md-block text-start">
                    <div style="font-size:0.8rem;font-weight:600;color:#1e293b;line-height:1.2">{{ auth()->user()?->name ?? '' }}</div>
                    <div style="font-size:0.7rem;color:#64748b">{{ auth()->user()?->role?->label() ?? '' }}</div>
                </div>
                <i class="bi bi-chevron-down d-none d-md-block" style="font-size:0.65rem;color:#64748b"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><h6 class="dropdown-header">{{ auth()->user()?->name ?? '' }}</h6></li>
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>Profil Saya</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>Keluar
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

<!-- ===== MAIN CONTENT ===== -->
<main id="main-content">

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
        <i class="bi bi-check-circle me-2"></i>{!! session('success') !!}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>{!! session('error') !!}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
        <i class="bi bi-exclamation-circle me-2"></i>{!! session('warning') !!}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @yield('content')
</main>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<!-- jQuery + Select2 (searchable dropdown, dipakai di POS) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Bootstrap Tooltip auto-init (digunakan oleh komponen tooltip.blade.php) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el, { trigger: 'hover focus', sanitize: false });
    });
});
</script>
<!-- Rupiah Formatter -->
<script>
/* resources/js/rupiah-formatter.js — inlined for zero-build-step delivery */
window.RupiahFormatter=(function(){'use strict';function fmt(v){v=String(v??'').trim();if(/^\d+\.\d{1,2}$/.test(v)){v=String(Math.round(parseFloat(v)));}v=v.replace(/\D/g,'');return v?v.replace(/\B(?=(\d{3})+(?!\d))/g,'.'):'';} function parse(v){return parseInt(String(v??'0').replace(/\D/g,''),10)||0;} function initEl(el){if(el._rpInit)return;el._rpInit=true;const forName=el.dataset.rupiahFor;const scope=el.closest('form')||el.closest('.modal')||document;const hiddenEl=forName?scope.querySelector('input[type="hidden"][name="'+forName+'"]'):null;el.value=fmt(el.value);if(hiddenEl)hiddenEl.value=parse(el.value)||0;el.addEventListener('input',function(){const cfe=this.value.length-(this.selectionStart||0);const raw=this.value.replace(/\D/g,'');this.value=raw?raw.replace(/\B(?=(\d{3})+(?!\d))/g,'.'):'';const nc=Math.max(0,this.value.length-cfe);try{this.setSelectionRange(nc,nc);}catch(_){}if(hiddenEl)hiddenEl.value=raw||'0';this.dispatchEvent(new Event('rupiah:change',{bubbles:true}));});el.addEventListener('focus',function(){const s=this;setTimeout(function(){s.select();},0);});} function init(root){(root||document).querySelectorAll('[data-rupiah]').forEach(initEl);} function onSubmit(form){form.querySelectorAll('[data-rupiah]').forEach(function(el){if(!el.dataset.rupiahFor){el.value=el.value.replace(/\D/g,'')||'0';}});} document.addEventListener('DOMContentLoaded',function(){init();document.querySelectorAll('form').forEach(function(f){f.addEventListener('submit',function(){onSubmit(this);});});});return{fmt:fmt,parse:parse,init:init,initEl:initEl};}());
window.rupiahFmt=window.RupiahFormatter.fmt;
window.rupiahParse=window.RupiahFormatter.parse;
</script>

<script>
const SIDEBAR_KEY  = 'sidebarHidden';
const DARKMODE_KEY = 'darkMode';

function toggleDarkMode() {
    const isDark = document.body.classList.toggle('dark-mode');
    localStorage.setItem(DARKMODE_KEY, isDark ? '1' : '0');
    updateDarkModeIcon(isDark);
}

function updateDarkModeIcon(isDark) {
    const icon = document.getElementById('darkModeIcon');
    const btn  = document.getElementById('darkModeToggle');
    if (!icon || !btn) return;
    if (isDark) {
        icon.className = 'bi bi-sun';
        btn.classList.replace('btn-light', 'btn-secondary');
    } else {
        icon.className = 'bi bi-moon-stars';
        btn.classList.replace('btn-secondary', 'btn-light');
    }
}

// Terapkan dark mode sebelum render (hindari flash)
(function () {
    if (localStorage.getItem(DARKMODE_KEY) === '1') {
        document.body.classList.add('dark-mode');
    }
})();
const isMobile = () => window.innerWidth < 992;

function toggleSidebar() {
    if (isMobile()) {
        // Mobile: toggle show class + overlay
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
        document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
    } else {
        // Desktop: toggle sidebar-hidden on body + simpan ke localStorage
        const hidden = document.body.classList.toggle('sidebar-hidden');
        localStorage.setItem(SIDEBAR_KEY, hidden ? '1' : '0');
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    sidebar.classList.remove('show');
    overlay.classList.remove('show');
    document.body.style.overflow = '';
}

// Restore state dari localStorage saat halaman load (desktop only)
(function () {
    if (!isMobile() && localStorage.getItem(SIDEBAR_KEY) === '1') {
        document.body.classList.add('sidebar-hidden');
    }
    // Sinkron ikon dark mode
    updateDarkModeIcon(document.body.classList.contains('dark-mode'));
})();

// Saat resize ke mobile, hapus sidebar-hidden agar tidak konflik
window.addEventListener('resize', function () {
    if (isMobile()) {
        document.body.classList.remove('sidebar-hidden');
        closeSidebar();
        document.body.style.overflow = '';
    }
});

// ===== SIDEBAR SCROLL POSITION =====
(function () {
    const SCROLL_KEY = 'sidebarScrollTop';
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;

    // Restore posisi scroll setelah halaman load
    const saved = sessionStorage.getItem(SCROLL_KEY);
    if (saved !== null) {
        sidebar.scrollTop = parseInt(saved, 10);
    } else {
        // Jika belum ada saved position, scroll ke menu aktif
        const activeItem = sidebar.querySelector('.nav-link.active');
        if (activeItem) {
            const itemTop    = activeItem.offsetTop;
            const sidebarH   = sidebar.clientHeight;
            const scrollTo   = Math.max(0, itemTop - sidebarH / 2);
            sidebar.scrollTop = scrollTo;
        }
    }

    // Simpan posisi scroll setiap kali user scroll (throttled)
    let scrollTimer;
    sidebar.addEventListener('scroll', function () {
        clearTimeout(scrollTimer);
        scrollTimer = setTimeout(function () {
            sessionStorage.setItem(SCROLL_KEY, sidebar.scrollTop);
        }, 50);
    }, { passive: true });

    // Simpan tepat sebelum halaman berpindah (fallback)
    window.addEventListener('beforeunload', function () {
        sessionStorage.setItem(SCROLL_KEY, sidebar.scrollTop);
    });
})();
</script>

{{-- ===== TOAST CONTAINER ===== --}}
<div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1080"></div>

<script>
// ---- Helper global: tampilkan toast singkat (success/error/warning) ----
// Pakai SweetAlert2 toast kalau tersedia, fallback ke Bootstrap Toast
// (bukan diam saja) supaya aplikasi tidak pernah "bisu" kalau CDN gagal.
window.showToast = function (type, message) {
    if (typeof Swal !== 'undefined') {
        var swalIconMap = { success: 'success', error: 'error', warning: 'warning' };
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: swalIconMap[type] || 'info',
            title: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
        });
        return;
    }

    // Fallback: Bootstrap Toast kalau SweetAlert2 gagal load dari CDN
    var container = document.getElementById('toastContainer');
    if (!container) return;

    var colorMap = { success: 'text-bg-success', error: 'text-bg-danger', warning: 'text-bg-warning' };
    var iconMap  = { success: 'bi-check-circle-fill', error: 'bi-exclamation-triangle-fill', warning: 'bi-exclamation-circle-fill' };
    var cls  = colorMap[type] || 'text-bg-dark';
    var icon = iconMap[type] || 'bi-info-circle-fill';

    var el = document.createElement('div');
    el.className = 'toast align-items-center ' + cls + ' border-0';
    el.setAttribute('role', 'alert');
    el.innerHTML = '<div class="d-flex">'
        + '<div class="toast-body"><i class="bi ' + icon + ' me-2"></i>' + message + '</div>'
        + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>'
        + '</div>';
    container.appendChild(el);

    var toast = new window.bootstrap.Toast(el, { delay: 3000 });
    toast.show();
    el.addEventListener('hidden.bs.toast', function () { el.remove(); });
};

// ---- Helper global: dialog alert (error/warning/success/info) ----
// Fallback ke alert() native kalau SweetAlert2 gagal load dari CDN.
window.showAlert = function (type, title, text) {
    if (typeof Swal === 'undefined') {
        alert(text || title);
        return Promise.resolve({ isConfirmed: true });
    }

    return Swal.fire({
        icon: type,
        title: title,
        text: text,
        confirmButtonText: 'OK',
        confirmButtonColor: '#0d6efd',
    });
};

// ---- Helper global: dialog konfirmasi (Ya/Batal) ----
// Fallback ke confirm() native kalau SweetAlert2 gagal load dari CDN.
window.showConfirm = function (title, html, options) {
    options = options || {};

    if (typeof Swal === 'undefined') {
        var stripHtml = String(html).replace(/<[^>]*>/g, '\n');
        return Promise.resolve({
            isConfirmed: confirm(title + '\n\n' + stripHtml),
        });
    }

    return Swal.fire({
        icon: options.icon || 'question',
        title: title,
        html: html,
        showCancelButton: true,
        confirmButtonText: options.confirmText || 'Ya, Proses',
        cancelButtonText: options.cancelText || 'Batal',
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        reverseButtons: true,
    });
};

// ---- Helper global: deteksi PWA berjalan dalam mode standalone
// (di-install ke homescreen, tanpa browser chrome) ----
window.isPWAStandalone = function () {
    return window.matchMedia('(display-mode: standalone)').matches ||
           window.navigator.standalone === true;
};
</script>

{{-- ===== STRUK MODAL ===== --}}
<x-struk-modal />

<script>
// ---- Helper global: buka struk sebagai modal (tidak buka tab baru) ----
// copies: 1 (default) atau 2 — render ganda dalam 1 modal
// tampilHargaStruk: opsional, cuma relevan utk cabang yang mengizinkan
// sembunyi harga per item (lihat checkbox di POS) — kalau tidak dikirim
// (mis. dipanggil dari Riwayat Order "cetak ulang"), default sembunyi
// harga (perilaku by-design, bukan bug — lihat CLAUDE.md).
window.openStrukModal = function (orderId, copies, tampilHargaStruk) {
    copies = (copies === 2) ? 2 : 1;
    var tampilHargaQuery = tampilHargaStruk ? '1' : '0';

    var modalEl = document.getElementById('strukModal');
    var body    = document.getElementById('strukModalBody');
    if (!modalEl || !body) return;

    var modal;
    try {
        // backdrop:'static' + keyboard:false -> modal cuma bisa ditutup via
        // tombol "Selesai" (klik backdrop / Escape diblok), supaya state
        // POS tidak ke-reset tanpa sengaja sebelum kasir selesai cetak.
        modal = window.bootstrap.Modal.getOrCreateInstance(modalEl, {
            backdrop: 'static',
            keyboard: false,
        });
        body.innerHTML = '<div class="py-5"><div class="spinner-border text-success mb-2"></div>'
                       + '<p class="text-muted small">Memuat struk...</p></div>';
        modal.show();
    } catch (e) {
        console.error('openStrukModal error:', e);
        alert('Gagal buka modal struk: ' + e.message);
        return;
    }

    // Safety net: reset form POS setiap kali modal ini benar-benar
    // tertutup, apapun jalurnya (no-op di halaman tanpa resetPosForm,
    // mis. Riwayat). { once: true } supaya tidak menumpuk listener kalau
    // openStrukModal() dipanggil lagi untuk order berikutnya.
    modalEl.addEventListener('hidden.bs.modal', function () {
        if (typeof window.resetPosForm === 'function') {
            window.resetPosForm();
        }
    }, { once: true });

    fetch('/penjualan/' + orderId + '/struk-modal?copies=' + copies + '&tampil_harga_struk=' + tampilHargaQuery, {
        headers: {
            'Accept'       : 'text/html',
            'X-CSRF-TOKEN' : document.querySelector('meta[name="csrf-token"]').content,
        }
    })
    .then(function(r) {
        if (!r.ok) throw new Error('Gagal memuat struk (HTTP ' + r.status + ')');
        return r.text();
    })
    .then(function(html) {
        body.innerHTML = html;
        // Re-eksekusi <script> yang diinject agar IIFE struk berjalan
        body.querySelectorAll('script').forEach(function(oldScript) {
            var newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach(function(attr) {
                newScript.setAttribute(attr.name, attr.value);
            });
            newScript.textContent = oldScript.textContent;
            oldScript.replaceWith(newScript);
        });
    })
    .catch(function(err) {
        body.innerHTML = '<div class="alert alert-danger m-3">'
                       + '<i class="bi bi-exclamation-triangle me-2"></i>'
                       + err.message + '</div>';
    });
};

// ---- Helper: tunggu Bootstrap JS siap sebelum buka modal struk ----
// Dipakai auto-print POS (dipanggil tanpa user gesture, langsung setelah
// redirect) supaya tidak race dengan bootstrap.bundle.min.js dari CDN
// yang masih loading. Polling tiap 100ms, timeout 2 detik (20x).
window.waitForBootstrapAndOpenStruk = function (orderId, copies, retries) {
    retries = (typeof retries === 'number') ? retries : 20;

    if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
        openStrukModal(orderId, copies);
    } else if (retries > 0) {
        setTimeout(function () {
            waitForBootstrapAndOpenStruk(orderId, copies, retries - 1);
        }, 100);
    } else {
        alert('Gagal load Bootstrap. Coba refresh halaman.');
    }
};
</script>

{{-- 4.44 Poin 1 — jembatan printer aplikasi Android (tidak berbuat apa-apa di browser biasa) --}}
@auth
<x-apk-printer />
{{-- 4.44 Poin 2 — overlay cetak Label Produk (struk, detail order) --}}
@can('label_cup.print')
<x-cup-label-overlay />
@endcan
@endauth

@stack('scripts')
</body>
</html>
