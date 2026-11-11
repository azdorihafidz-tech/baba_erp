@extends('layouts.app')

@section('title', 'POS - Kasir')

@push('styles')
<style>
.pos-item-row { border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.75rem; margin-bottom: 0.5rem; background: #fff; }
.pos-item-row:hover { border-color: #3b82f6; }
.btn-tipe { min-width: 90px; }
.payment-btn { min-height: 56px; font-size: 0.95rem; }
#totalBayarDisplay { font-size: 2rem; font-weight: 700; color: #1e293b; }
.stok-badge { font-size: 0.7rem; }

/* Tahap 3 D'mentai — Grid Produk & Item Tambahan */
.pos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.6rem; }
.pos-card { border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; cursor: pointer; overflow: hidden; transition: box-shadow .15s, transform .15s; }
.pos-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); transform: translateY(-2px); }
.pos-card.habis { opacity: .45; cursor: not-allowed; pointer-events: none; }
.pos-card .thumb { width: 100%; aspect-ratio: 1/1; background: var(--brand-latar); display: flex; align-items: center; justify-content: center; font-size: 2rem; overflow: hidden; }
.pos-card .thumb img { width: 100%; height: 100%; object-fit: cover; }
.pos-card .body { padding: .4rem .5rem; }
.pos-card .nama { font-size: .78rem; font-weight: 600; line-height: 1.2; min-height: 2em; }
.pos-card .harga { font-size: .76rem; color: var(--brand-utama); font-weight: 700; }
.cat-pill { border-radius: 20px; }
.item-tambahan-scroll { display: flex; gap: .5rem; overflow-x: auto; padding-bottom: .25rem; }
.item-tambahan-card { flex: 0 0 auto; width: 100px; border: 1px solid #e2e8f0; border-radius: 8px; padding: .4rem; text-align: center; cursor: pointer; background: #fff; }
.item-tambahan-card:hover { border-color: var(--brand-utama); }
.btn-tipe-transaksi.active { background: var(--brand-sidebar) !important; color: rgb(var(--brand-sidebar-teks-rgb)) !important; border-color: var(--brand-sidebar) !important; }

/* ══ 4.49 Tahap C — Tampilan HP (≤ 575px), porting erp-coffeeshop 512ccb2. Tablet & PC tidak terpengaruh. ══ */
#posBarHp { display: none; }
@media (max-width: 575.98px) {
    /* Header ringkas: teks tombol disembunyikan, mode Desktop/Tablet tidak relevan di HP */
    #posHeader { flex-wrap: wrap; gap: .5rem; }
    #posHeader .pos-mode-toggle, #posHeader .pos-hp-sembunyi { display: none !important; }

    /* Kartu produk 3 kolom, gambar pendek supaya daftar tidak terlalu panjang */
    .pos-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .4rem; }
    .pos-card .thumb { aspect-ratio: auto; height: 56px; font-size: 1.4rem; }
    .pos-card .body { padding: .3rem .35rem; }
    .pos-card .nama { font-size: .72rem; min-height: 2.4em; }
    .pos-card .harga { font-size: .72rem; }
    .pos-card .badge { font-size: .6rem; }
    #filterKategoriGrid { flex-wrap: nowrap !important; overflow-x: auto; padding-bottom: 2px; }
    #filterKategoriGrid .cat-pill { flex: 0 0 auto; }

    /* Kartu Pelanggan dilipat (biasanya kosong untuk pembeli walk-in) */
    #cardPelanggan .card-header { cursor: pointer; }
    #cardPelanggan .card-header::after { content: ' ▾ (opsional)'; color: #6c757d; font-size: .8rem; }
    #cardPelanggan.hp-buka .card-header::after { content: ' ▴'; }
    #cardPelanggan:not(.hp-buka) .card-body { display: none; }

    /* Ringkasan tidak sticky saat ditumpuk di bawah */
    #panelBayar { position: static !important; }

    /* Bilah keranjang melayang di bawah layar */
    #posBarHp {
        display: flex; position: fixed; left: 0; right: 0; bottom: 0; z-index: 1040;
        align-items: center; gap: .5rem; padding: .55rem .75rem calc(.55rem + env(safe-area-inset-bottom));
        background: var(--brand-sidebar); color: rgb(var(--brand-sidebar-teks-rgb)); box-shadow: 0 -4px 14px rgba(0,0,0,.25);
    }
    #posBarHp .info { flex: 1; line-height: 1.15; min-width: 0; }
    #posBarHp .info .jml { font-size: .75rem; opacity: .8; }
    #posBarHp .info .total { font-size: 1.05rem; font-weight: 700; }
    #posBarHp.kosong .btn-bayar { opacity: .5; }
    #posBarHp.denyut { animation: posBarDenyut .35s ease; }
    @keyframes posBarDenyut { 50% { transform: translateY(-4px); } }
    body.pos-ada-bar #main-content { padding-bottom: 84px !important; }
}

/* Dropdown salinan — stack vertikal di layar sangat sempit */
@media (max-width: 360px) {
    #sectionSalinan { flex-wrap: wrap; }
}

/* Dark mode overrides */
body.dark-mode .pos-item-row { background: #1e293b; border-color: #334155; }
body.dark-mode .pos-item-row .form-label { color: #cbd5e1; }
body.dark-mode #totalBayarDisplay { color: #f1f5f9; }
body.dark-mode .bahan-dropdown { background: #1e293b !important; border-color: #334155 !important; }
body.dark-mode .bahan-option { color: #e2e8f0; }
body.dark-mode .bahan-option:hover { background: #334155 !important; }

/* ── Mode Tampilan Toggle ── */
#btnModeDesktop.active, #btnModeTablet.active {
    background-color: #0d6efd !important;
    border-color: #0d6efd !important;
    color: #fff !important;
}

/* ── Mode Tablet POS: sembunyikan topbar + sidebar ── */
body.pos-tablet-mode #topbar         { display: none !important; }  /* header app hilang */
body.pos-tablet-mode #sidebar        { display: none !important; }  /* sidebar kiri hilang */
body.pos-tablet-mode #sidebar-overlay { display: none !important; }

/* ── Mode Tablet POS: reset main-content offset ── */
body.pos-tablet-mode #main-content {
    margin-left:  0 !important;
    margin-top:   0 !important;   /* kompensasi topbar yang hilang */
    padding:      0.5rem !important;
    min-height:   100vh !important;
}

/* ── Mode Tablet POS: background ── */
body.pos-tablet-mode { background-image: none !important; }

/* ── Mode Tablet POS: container padding ── */
body.pos-tablet-mode .container-fluid,
body.pos-tablet-mode .container { padding-left: 4px !important; padding-right: 4px !important; }

/* ── Mode Tablet POS: card compact ── */
body.pos-tablet-mode .card           { margin-bottom: 4px !important; }
body.pos-tablet-mode .card-header    { padding: 5px 10px !important; font-size: 0.82rem !important; }
body.pos-tablet-mode .card-body      { padding: 7px 10px !important; }

/* ── Mode Tablet POS: form compact ── */
body.pos-tablet-mode .form-label { font-size: 0.73rem !important; margin-bottom: 2px !important; }
body.pos-tablet-mode .form-control,
body.pos-tablet-mode .form-select {
    padding: 3px 7px !important;
    font-size: 0.82rem !important;
    height: 30px !important;
    min-height: 30px !important;
}
/* form-control-lg (jumlahBayarInput) — lebih besar tapi tetap compact */
body.pos-tablet-mode .form-control-lg {
    font-size: 0.95rem !important;
    padding: 4px 10px !important;
    height: auto !important;
    min-height: 34px !important;
}

/* ── Mode Tablet POS: row gutter ── */
body.pos-tablet-mode .row { --bs-gutter-x: 0.4rem !important; }
body.pos-tablet-mode .g-1,
body.pos-tablet-mode .g-2 { --bs-gutter-x: 0.3rem !important; --bs-gutter-y: 0.3rem !important; }
body.pos-tablet-mode .g-3 { --bs-gutter-x: 0.4rem !important; --bs-gutter-y: 0.3rem !important; }

/* ── Mode Tablet POS: spacing ── */
body.pos-tablet-mode .mb-3 { margin-bottom: 4px !important; }
body.pos-tablet-mode .mb-2 { margin-bottom: 3px !important; }
body.pos-tablet-mode .mt-2 { margin-top: 3px !important; }
body.pos-tablet-mode .mb-1 { margin-bottom: 2px !important; }
body.pos-tablet-mode .py-3 { padding-top: 4px !important; padding-bottom: 4px !important; }
body.pos-tablet-mode h5,
body.pos-tablet-mode h6    { font-size: 0.88rem !important; margin-bottom: 3px !important; }
body.pos-tablet-mode small,
body.pos-tablet-mode .small { font-size: 0.72rem !important; }
body.pos-tablet-mode .pos-item-row { padding: 5px 8px !important; margin-bottom: 4px !important; }

/* ── Mode Tablet POS: payment section ── */
body.pos-tablet-mode #totalBayarDisplay   { font-size: 1.25rem !important; margin: 2px 0 !important; }
body.pos-tablet-mode #kembalianDisplay    { font-size: 0.95rem !important; }
body.pos-tablet-mode .payment-btn         { min-height: 34px !important; font-size: 0.73rem !important; padding: 3px 4px !important; }
body.pos-tablet-mode .payment-btn i.d-block { font-size: 0.85rem !important; margin-bottom: 0 !important; }
/* Quick-amount buttons (Rp 50.000, Rp 100.000, dll) */
body.pos-tablet-mode #sectionJumlahBayar .btn { padding: 2px 6px !important; font-size: 0.72rem !important; min-height: 24px !important; }
/* Tombol Proses Transaksi */
body.pos-tablet-mode #btnProses { padding: 0.45rem 0.75rem !important; font-size: 0.85rem !important; }

/* ── 4.47 Panel Ringkasan & Pembayaran ──
   Desktop: kaki (tombol aksi) tampil seperti dulu, di bawah isi panel.
   Tablet (layar ≥768px): 2 kolom berdampingan; panel kanan setinggi layar dan
   tidak ikut tergeser saat produk di kiri di-scroll; isinya scroll sendiri,
   Total + PROSES ORDER + Save Bill selalu terlihat di bawah. */
.pos-bayar-kaki { padding: 0 1rem 1rem; }
/* 4.47 — kedip singkat saat qty baris bertambah karena produk yang sama diklik lagi */
@keyframes posItemTambah { from { background: var(--brand-latar); border-color: var(--brand-utama); } to { background: #fff; } }
.pos-item-row.pos-item-tambah { animation: posItemTambah .6s ease-out; }
body.pos-tablet-mode .pos-bayar-kaki { padding: 0 10px 7px; }
.pos-kaki-total { display: none; }
@media (min-width: 768px) {
    body.pos-tablet-mode #posKolomKiri  { width: 58.333%; }
    body.pos-tablet-mode #posKolomKanan { width: 41.667%; }
    body.pos-tablet-mode #panelBayar {
        position: sticky !important;
        top: 0.5rem !important;
        max-height: calc(100vh - 1rem);
        display: flex;
        flex-direction: column;
        margin-bottom: 0 !important;
    }
    body.pos-tablet-mode #panelBayarIsi {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
    }
    body.pos-tablet-mode #panelBayarKaki {
        flex-shrink: 0;
        padding: 6px 10px 8px !important;
        border-top: 1px solid #e2e8f0;
        box-shadow: 0 -4px 10px rgba(0,0,0,.06);
        background: inherit;
        border-radius: 0 0 10px 10px;
    }
    body.pos-tablet-mode .pos-kaki-total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 4px;
    }
    body.pos-tablet-mode #kakiTotalNilai { font-size: 1.1rem; }
    body.pos-tablet-mode #panelBayarKaki .btn.mt-2 { margin-top: 4px !important; padding-top: 3px; padding-bottom: 3px; font-size: .8rem; }
}

</style>
@endpush

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3" id="posHeader">
    <div>
        <h5 class="mb-0 fw-bold"><i class="bi bi-cart3 me-2 text-success"></i>POS - Kasir</h5>
        <small class="text-muted">Cabang aktif: {{ session('cabang_aktif_nama') ?? 'Cabang' }}</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        @can('antrian.lihat')
        <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalCekAntrian">
            <i class="bi bi-search me-1"></i><span class="pos-hp-sembunyi">Cek Antrian</span>
        </button>
        @endcan
        @can('kas.buka_laci')
        <button type="button" class="btn btn-sm btn-outline-warning" onclick="bukaLaciManual()">
            <i class="bi bi-unlock me-1"></i><span class="pos-hp-sembunyi">Buka Laci</span>
        </button>
        @endcan
        {{-- Toggle Mode Tampilan --}}
        <div class="d-flex align-items-center gap-1 pos-mode-toggle">
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" id="btnModeDesktop" class="btn btn-outline-secondary"
                        onclick="setModeTampilan('desktop')" title="Mode Desktop">
                    <i class="bi bi-display"></i><span class="d-none d-sm-inline ms-1">Desktop</span>
                </button>
                <button type="button" id="btnModeTablet" class="btn btn-outline-secondary"
                        onclick="setModeTampilan('tablet')" title="Mode Tablet">
                    <i class="bi bi-tablet"></i><span class="d-none d-sm-inline ms-1">Tablet</span>
                </button>
            </div>
            <x-tooltip key="pos.mode_tampilan" placement="bottom" />
        </div>
        <x-panduan-button slug="pos" />
    </div>
</div>

{{-- Tahap C 4.38 — PO cabang ke supplier internal lokasi ini (mis. Gudang Pusat).
     Klik "Isi Keranjang" → item & qty dari PO masuk keranjang dgn HARGA LOKASI INI. --}}
@if($poMasuk->isNotEmpty())
<div class="card mb-3 border-primary">
    <div class="card-header d-flex align-items-center gap-2 py-2">
        <i class="bi bi-inbox"></i>
        <span class="fw-semibold">Pesanan PO dari Cabang</span>
        <span class="badge bg-primary">{{ $poMasuk->count() }}</span>
        <x-tooltip key="pos.po_masuk" />
    </div>
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            @foreach($poMasuk as $po)
            <div class="list-group-item d-flex flex-wrap align-items-center gap-2">
                <div class="flex-grow-1">
                    <div class="fw-semibold small">{{ $po->nomor_po }} · {{ $po->cabang?->nama_cabang ?? '-' }}</div>
                    <div class="text-muted" style="font-size:.75rem">
                        {{ $po->tanggal_po?->format('d/m/Y') }} ·
                        {{ $po->items->map(fn ($i) => ($i->item?->nama_item ?? '-') . ' × ' . rtrim(rtrim(number_format((float) $i->qty_pesan, 3, ',', '.'), '0'), ','))->implode(', ') }}
                        · Total PO Rp {{ number_format((float) $po->total_harga, 0, ',', '.') }}
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-primary" onclick="isiDariPo({{ $po->id }})">
                    <i class="bi bi-cart-plus me-1"></i>Isi Keranjang
                </button>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

<form method="POST" action="{{ route('penjualan.store') }}" id="formPos" enctype="multipart/form-data">
    @csrf
    {{-- Tahap C 4.38 — diisi saat keranjang diisi dari PO cabang (supplier internal) --}}
    <input type="hidden" name="purchase_order_id" id="inputPurchaseOrderId" value="">
    <div id="infoPoAktif" class="alert alert-info small py-2 d-none"></div>
    <div class="row g-3">
        {{-- Kiri: Form Order --}}
        <div class="col-12 col-lg-7" id="posKolomKiri">

            {{-- 4.49 Tahap B2 — Kirim ke Cabang: hanya di POS Gudang Pusat --}}
            @if($outletList->isNotEmpty())
            <div class="card mb-3 border-info" id="cardJenisPembeli">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small mb-1">Jenis Pembeli <x-tooltip key="pos.customer_type" /></label>
                            <select name="customer_type" id="selectCustomerType" class="form-select form-select-sm">
                                <option value="retail" selected>Penjualan biasa</option>
                                <option value="internal">Kirim ke Cabang (menunggu diterima)</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 d-none" id="wrapOutletTujuan">
                            <label class="form-label small mb-1">Cabang Tujuan <x-tooltip key="pos.outlet_tujuan" /></label>
                            <select name="outlet_tujuan_id" id="selectOutletTujuan" class="form-select form-select-sm">
                                <option value="">-- Pilih Cabang --</option>
                                @foreach($outletList as $outlet)
                                <option value="{{ $outlet->id }}">{{ $outlet->nama_cabang }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- ===== Tahap 3 D'mentai — Tipe Transaksi + Grid Produk ===== --}}
            {{-- 4.41 — pilihan tipe bisa disembunyikan per cabang (Edit Cabang → Config POS);
                 saat disembunyikan transaksi memakai tipe bawaan cabang --}}
            <div class="card mb-3">
                @if($tampilPilihanTipe)
                <div class="card-header">Tipe Transaksi <x-tooltip key="pos.tipe_transaksi" /></div>
                @endif
                <div class="card-body {{ $tampilPilihanTipe ? '' : 'py-2' }}">
                    <input type="hidden" name="tipe_transaksi" id="tipeTransaksiInput" value="{{ $tipeOtomatis }}">
                    @unless($tampilPilihanTipe)
                        <div class="small text-muted" id="infoTipeOtomatis">
                            <i class="bi bi-bag-check me-1"></i>Tipe transaksi: <strong class="text-body">{{ \App\Enums\TipeTransaksi::tryFrom((string) $tipeOtomatis)?->label() }}</strong> (otomatis)
                        </div>
                    @endunless
                    <div class="d-flex flex-wrap gap-2 mb-2 {{ $tampilPilihanTipe ? '' : 'd-none' }}" id="tabTipeTransaksi">
                        @foreach($tipeTransaksiAktif as $tipe)
                            <button type="button" class="btn btn-outline-dark btn-sm btn-tipe-transaksi"
                                data-tipe="{{ $tipe->value }}" onclick="pilihTipeTransaksi('{{ $tipe->value }}')">
                                {{ $tipe->label() }}
                            </button>
                        @endforeach
                    </div>
                    @if($tampilPilihanTipe && $tipeTransaksiAktif->isEmpty())
                        <div class="alert alert-warning py-2 small mb-2">Tidak ada tipe transaksi aktif untuk outlet ini — aktifkan di menu Edit Cabang.</div>
                    @endif
                    <div class="d-none" id="wrapNomorMeja" style="max-width:220px">
                        <label class="form-label small">Nomor Meja <x-tooltip key="pos.nomor_meja" /></label>
                        <input type="text" name="nomor_meja" id="inputNomorMeja" class="form-control form-control-sm">
                    </div>
                </div>
            </div>

            {{-- Pelanggan --}}
            <div class="card mb-3" id="cardPelanggan">
                <div class="card-header">Pelanggan</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <label class="form-label small">Pelanggan Terdaftar</label>
                            <select name="pelanggan_id" id="pelangganSelect" class="form-select form-select-sm"
                                onchange="onPelangganChange(this)">
                                <option value="">-- Walk-in / Umum --</option>
                                @foreach($pelanggans as $p)
                                <option value="{{ $p->id }}" data-nama="{{ $p->nama_pelanggan }}"
                                    data-telepon="{{ $p->telepon }}" data-kode="{{ $p->kode_pelanggan }}">
                                    {{ $p->nama_pelanggan }} @if($p->telepon)({{ $p->telepon }})@endif [{{ $p->kode_pelanggan }}]
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small">Nama</label>
                            <input type="text" name="nama_pelanggan" id="namaPelangganInput"
                                class="form-control form-control-sm" placeholder="Nama pelanggan / walk-in"
                                value="{{ old('nama_pelanggan') }}">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small">Telepon</label>
                            <input type="text" name="telepon_pelanggan" id="teleponPelangganInput"
                                class="form-control form-control-sm" placeholder="08xx-xxxx-xxxx"
                                value="{{ old('telepon_pelanggan') }}">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small">Catatan</label>
                            <input type="text" name="catatan" class="form-control form-control-sm"
                                placeholder="Catatan order...">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Pilih Produk</div>
                <div class="card-body">
                    <input type="text" class="form-control form-control-sm mb-2" id="searchProdukGrid" placeholder="🔍 Cari produk...">
                    <div class="d-flex flex-wrap gap-2 mb-2" id="filterKategoriGrid">
                        <button type="button" class="btn btn-sm btn-dark cat-pill active" data-kategori="all" onclick="filterKategoriGrid('all', this)">Semua</button>
                        @foreach($categories as $cat)
                            <button type="button" class="btn btn-sm btn-outline-dark cat-pill" data-kategori="{{ $cat->id }}" onclick="filterKategoriGrid('{{ $cat->id }}', this)">{{ $cat->nama_kategori }}</button>
                        @endforeach
                    </div>
                    <div class="pos-grid" id="gridProduk">
                        @foreach($produkJadi as $item)
                            <div class="pos-card {{ $item->bisa_dijual ? '' : 'habis' }}"
                                 data-id="{{ $item->id }}" data-kategori="{{ $item->item_category_id }}"
                                 data-nama="{{ strtolower($item->nama_item) }}"
                                 onclick="klikProdukGrid({{ $item->id }})">
                                <div class="thumb">
                                    @if($item->foto)
                                        <img src="{{ asset('storage/'.$item->foto) }}" alt="{{ $item->nama_item }}">
                                    @else
                                        🥟
                                    @endif
                                </div>
                                <div class="body">
                                    <div class="nama">{{ $item->nama_item }}</div>
                                    <div class="harga">Rp {{ number_format($item->harga_jual, 0, ',', '.') }}</div>
                                    @if(!$item->bisa_dijual)
                                        <span class="badge bg-danger mt-1">Stok Habis</span>
                                    @elseif($item->punya_varian)
                                        <span class="badge bg-secondary mt-1">Ada Varian</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if($produkJadi->isEmpty())
                        <p class="text-muted mb-0">Belum ada produk aktif. Tambahkan lewat menu <strong>Produk Jual</strong>.</p>
                    @endif

                    @if($itemTambahan->isNotEmpty())
                    <hr>
                    <div class="fw-semibold small mb-2">Item Tambahan <x-tooltip key="pos.item_tambahan" /></div>
                    <div class="item-tambahan-scroll">
                        @foreach($itemTambahan as $item)
                            <div class="item-tambahan-card {{ $item->bisa_dijual ? '' : 'opacity-50' }}"
                                 onclick="{{ $item->bisa_dijual ? 'klikItemTambahanGrid('.$item->id.')' : '' }}">
                                <div style="font-size:1.4rem">➕</div>
                                <div class="small fw-semibold">{{ $item->nama_item }}</div>
                                <div class="small text-muted">Rp {{ number_format($item->harga_jual, 0, ',', '.') }}</div>
                                @if(!$item->bisa_dijual)<span class="badge bg-danger">Habis</span>@endif
                            </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>

            {{-- Item Order (keranjang) — diisi otomatis lewat klik grid produk
                 di atas. Tombol "+ Tambah Manual" tetap ada sebagai fallback
                 (mis. produk belum sempat difoto/di-setup di grid). --}}
            <div class="card" id="cardItemOrder">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Item Order <x-tooltip key="pos.harga_unit" /></span>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="tambahProduk()">
                        <i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Tambah Manual</span>
                    </button>
                </div>
                <div class="card-body">
                    @error('items')<div class="alert alert-danger py-2 mb-3">{{ $message }}</div>@enderror

                    <div id="itemContainer"></div>
                    <div id="emptyItems" class="text-center text-muted py-4">
                        <i class="bi bi-cart-x fs-2 d-block mb-2"></i>
                        Klik produk di grid atas untuk menambah item
                    </div>
                </div>
            </div>
        </div>

        {{-- Kanan: Ringkasan & Bayar --}}
        <div class="col-12 col-lg-5" id="posKolomKanan">
            <div class="card sticky-top" style="top:70px" id="panelBayar">
                <div class="card-header fw-bold">Ringkasan & Pembayaran</div>
                <div class="card-body" id="panelBayarIsi">
                    {{-- Subtotal list --}}
                    <div id="subtotalList" class="mb-3"></div>

                    <hr>

                    {{-- Diskon --}}
                    <div class="row g-2 align-items-center mb-2">
                        <div class="col-6">
                            <label class="form-label mb-0">Diskon (Rp)</label>
                        </div>
                        <div class="col-6">
                            <input type="text" inputmode="numeric" data-rupiah name="diskon" id="diskonInput" class="form-control form-control-sm text-end"
                                value="0" onchange="hitungTotal()">
                        </div>
                    </div>

                    {{-- Total --}}
                    <div class="bg-light rounded p-3 mb-3 text-center">
                        <div class="text-muted small">TOTAL BAYAR</div>
                        <div id="totalBayarDisplay">Rp 0</div>
                    </div>

                    {{-- 4.49 Tahap B2 — Kirim ke Cabang: tidak ada pembayaran di gudang, uang dipotong dari kas cabang saat Terima --}}
                    <div id="infoKirimCabang" class="alert alert-info small d-none">
                        <i class="bi bi-truck me-1"></i><strong>Kirim ke Cabang.</strong> Stok keluar dari gudang sekarang. Cabang tujuan mengecek barang lalu menekan <strong>Terima</strong> di menu <em>Kiriman Cabang</em>: stok masuk ke cabang, kas cabang terpotong, dan pendapatan tercatat di gudang.
                    </div>
                    <div id="wrapPembayaran">
                    {{-- Tipe Pembayaran --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipe Pembayaran</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <input type="hidden" name="tipe_pembayaran" id="tipePembayaranInput" value="tunai">
                            <button type="button" class="btn btn-primary payment-btn flex-fill active" id="btnTunai"
                                onclick="setTipePembayaran('tunai')">
                                <i class="bi bi-cash d-block fs-4"></i>Tunai
                            </button>
                            <button type="button" class="btn btn-outline-primary payment-btn flex-fill" id="btnTransfer"
                                onclick="setTipePembayaran('transfer')">
                                <i class="bi bi-bank d-block fs-4"></i>Transfer
                            </button>
                            <button type="button" class="btn btn-outline-primary payment-btn flex-fill" id="btnQris"
                                onclick="setTipePembayaran('qris')">
                                <i class="bi bi-qr-code d-block fs-4"></i>QRIS
                            </button>
                        </div>

                        {{-- Tahap 3 D'mentai — Split Payment --}}
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="splitPaymentCheck" onchange="toggleSplitPayment()">
                            <label class="form-check-label small" for="splitPaymentCheck">
                                <i class="bi bi-layers-half me-1"></i>Split Payment (bayar dengan 2 metode)
                            </label> <x-tooltip key="pos.split_payment" />
                        </div>
                        <div class="d-none border rounded p-2 mt-2" id="sectionSplitPayment">
                            <label class="form-label small fw-semibold mb-1">Metode Bayar ke-2</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <select class="form-select form-select-sm" id="splitMetode2" onchange="hitungTotal(); updateBuktiSection()">
                                        <option value="tunai">Tunai</option>
                                        <option value="transfer">Transfer</option>
                                        <option value="qris">QRIS</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <input type="text" inputmode="numeric" data-rupiah class="form-control form-control-sm text-end"
                                        id="splitJumlah2" placeholder="Jumlah" value="0" onchange="hitungTotal()">
                                </div>
                            </div>
                            <div class="form-text mb-0">Metode utama di atas jadi bagian pertama, ini bagian kedua. Total keduanya harus &ge; Total Bayar.</div>
                        </div>
                    </div>

                    {{-- Kas Terpilih (auto dari default_untuk) --}}
                    <input type="hidden" name="kas_id" id="kasIdInput" value="">
                    <div id="kasDefaultInfo" class="mb-2 mt-n1 ps-1" style="min-height:1.4rem"></div>
                    @if($kasList->isNotEmpty())
                    <div class="mb-2">
                        <a class="small text-muted text-decoration-none" style="cursor:pointer"
                           data-bs-toggle="collapse" href="#kasManualSection">
                            <i class="bi bi-chevron-down me-1"></i>Pilih Kas Manual
                        </a>
                        <div class="collapse" id="kasManualSection">
                            <select id="kasManualSelect" class="form-select form-select-sm mt-1"
                                    onchange="onKasManual()">
                                <option value="">-- Gunakan Default --</option>
                                @foreach($kasList as $k)
                                <option value="{{ $k->id }}" data-nama="{{ $k->nama_kas }}">
                                    {{ $k->nama_kas }}
                                    @if($k->default_untuk)
                                    (default {{ $k->default_untuk }})
                                    @endif
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @endif

                    {{-- Bukti Pembayaran (Transfer / QRIS) --}}
                    <div id="sectionBuktiPembayaran" class="mb-3" style="display:none">
                        <label class="form-label fw-semibold">
                            Bukti Pembayaran
                            <span class="text-danger" id="buktiWajibMark">*</span>
                        </label>

                        {{-- Preview --}}
                        <div id="buktiPreviewWrap" class="mb-2" style="display:none">
                            <img id="buktiPreview" src="" alt="Bukti"
                                 class="img-fluid rounded border"
                                 style="max-height:180px;object-fit:contain;cursor:pointer"
                                 onclick="document.getElementById('buktiFotoInput').click()">
                            <button type="button" class="btn btn-sm btn-outline-danger mt-1 d-block"
                                    onclick="hapusBukti()">
                                <i class="bi bi-trash me-1"></i>Hapus Foto
                            </button>
                        </div>

                        {{-- Tombol Upload & Kamera --}}
                        <div id="buktiButtons" class="d-flex gap-2">
                            {{-- Upload dari galeri/file --}}
                            <label class="btn btn-outline-secondary flex-fill mb-0" style="cursor:pointer">
                                <i class="bi bi-image d-block fs-4 mb-1"></i>
                                <span style="font-size:0.8rem">Pilih Foto</span>
                                <input type="file" id="buktiFotoInput" name="bukti_pembayaran"
                                       accept="image/*" class="d-none"
                                       onchange="onBuktiFileChange(this)">
                            </label>
                            {{-- Ambil dari kamera langsung --}}
                            <label class="btn btn-outline-primary flex-fill mb-0" style="cursor:pointer">
                                <i class="bi bi-camera d-block fs-4 mb-1"></i>
                                <span style="font-size:0.8rem">Ambil Foto</span>
                                <input type="file" id="buktiKameraInput" accept="image/*"
                                       capture="environment" class="d-none"
                                       onchange="onBuktiKameraChange(this)">
                            </label>
                        </div>
                        <div class="form-text text-muted">Format: JPG/PNG/WEBP, maks 5 MB</div>
                    </div>

                    {{-- Jumlah Bayar (tunai) --}}
                    <div id="sectionJumlahBayar" class="mb-3">
                        <label class="form-label fw-semibold">Jumlah Bayar</label>
                        <input type="text" inputmode="numeric" data-rupiah name="jumlah_bayar" id="jumlahBayarInput"
                            class="form-control form-control-lg text-end fw-bold"
                            value="0" oninput="hitungKembalian()"
                            placeholder="0">
                        {{-- 4.41 — Uang Pas (= total belanja) + nominal pintar menyesuaikan total --}}
                        <div class="row mt-2 g-1" id="nominalCepat"></div>
                    </div>

                    {{-- Kembalian --}}
                    <div id="sectionKembalian" class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Kembalian</span>
                            <span id="kembalianDisplay" class="fs-5 fw-bold text-success">Rp 0</span>
                        </div>
                    </div>
                    </div>{{-- /wrapPembayaran --}}

                    {{-- Tampil di Antrian Produksi — default centang. Uncheck utk order
                         tambahan yang dikerjakan barengan order asli (tidak perlu nomor
                         antrian baru sendiri). Hidden input value=0 DI ATAS checkbox:
                         checkbox unchecked -> browser tidak kirim field-nya sama sekali,
                         jadi hidden input inilah yang mengisi nilai "0" ke server. Kalau
                         checkbox checked, submisinya "0" lalu "1" (PHP ambil value terakhir). --}}
                    <div class="form-check mb-2">
                        <input type="hidden" name="tampil_di_antrian" value="0">
                        <input class="form-check-input" type="checkbox" name="tampil_di_antrian"
                            value="1" id="tampilAntrianCheck" checked>
                        <label class="form-check-label small" for="tampilAntrianCheck">
                            <i class="bi bi-list-ol me-1"></i>Tampilkan di Antrian Produksi
                        </label>
                    </div>

                    {{-- Order pengganti — diisi otomatis (auto-detect) atau manual dari
                         detail order. Kosong = order berdiri sendiri. --}}
                    <input type="hidden" name="parent_order_id" id="parentOrderIdInput" value="">

                    {{-- Auto-print setelah order selesai --}}
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="auto_print_struk"
                            value="1" id="autoPrintCheck">
                        <label class="form-check-label small" for="autoPrintCheck">
                            <i class="bi bi-printer me-1"></i>Cetak struk otomatis setelah proses
                        </label>
                        <x-tooltip key="pos.cetak_otomatis" placement="top" />
                    </div>

                    {{-- Sembunyi harga per item di struk — cuma muncul kalau cabang mengizinkan
                         (setting di Edit Cabang). Default UNCHECKED = sembunyi harga per item,
                         total tetap selalu tercetak. Murni preferensi tampilan struk, tidak
                         mengubah data order/harga yang tersimpan sama sekali. --}}
                    @if($cabangAktif?->izinkan_sembunyi_harga_struk)
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="tampil_harga_struk"
                            value="1" id="tampilHargaStrukCheck">
                        <label class="form-check-label small" for="tampilHargaStrukCheck">
                            <i class="bi bi-eye me-1"></i>Tampilkan harga per item di struk (default: sembunyi)
                        </label>
                        <x-tooltip key="pos.tampil_harga_struk" placement="top" />
                    </div>
                    @endif

                    {{-- Salinan struk --}}
                    <div class="d-flex align-items-center gap-2 mb-3" id="sectionSalinan" style="display:none !important">
                        <label for="printCopies" class="form-label mb-0 small">
                            <i class="bi bi-files me-1"></i>Salinan:
                        </label>
                        <x-tooltip key="pos.salinan_struk" placement="top" />
                        <select id="printCopies" name="print_copies" class="form-select form-select-sm"
                                style="width:75px;" onchange="onCopiesChange(this.value)">
                            <option value="1">1x</option>
                            <option value="2">2x</option>
                        </select>
                        <span class="text-muted small">struk</span>
                    </div>
                </div>

                {{-- 4.47 — Tombol aksi dipisah dari isi panel. Mode Desktop: tampil
                     persis seperti dulu (di bawah isi). Mode Tablet: menempel di bawah
                     layar, isi panel di atasnya scroll sendiri. --}}
                <div class="pos-bayar-kaki" id="panelBayarKaki">
                    <div class="pos-kaki-total" id="kakiTotal">
                        <span class="text-muted small">TOTAL BAYAR</span>
                        <span class="fw-bold" id="kakiTotalNilai">Rp 0</span>
                    </div>

                    {{-- Tombol Proses --}}
                    <button type="submit" class="btn btn-success btn-lg w-100 py-3" id="btnProses"
                        style="font-size:1.1rem">
                        <i class="bi bi-check-circle-fill me-2"></i>PROSES ORDER
                    </button>

                    {{-- Tahap 3 D'mentai — Save Bill: simpan dulu, bayar nanti --}}
                    <button type="button" class="btn btn-outline-dark w-100 mt-2" id="btnSaveBill" onclick="simpanBill()">
                        <i class="bi bi-save2 me-1"></i>Save Bill
                    </button>

                    <a href="{{ route('penjualan.index') }}" class="btn btn-outline-secondary w-100 mt-2">
                        Lihat Riwayat
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

{{-- 4.49 Tahap C — Tampilan HP: bilah keranjang melayang (CSS hanya menampilkannya di layar ≤ 575px) --}}
<div id="posBarHp" class="kosong">
    <div class="info"><div class="jml" id="posBarJml">Keranjang kosong</div><div class="total" id="posBarTotal">Rp 0</div></div>
    <button type="button" class="btn btn-sm btn-outline-light" id="posBarKeranjang"><i class="bi bi-cart3"></i> Keranjang</button>
    <button type="button" class="btn btn-sm btn-success fw-semibold btn-bayar" id="posBarBayar">Bayar ▸</button>
</div>

{{--
    Tahap 7 D'mentai (Bug 2 fix) — Bill Tersimpan dipindah ke PALING BAWAH
    halaman (setelah form transaksi utama + tombol aksi), di luar <form
    id="formPos"> sama sekali (dia punya AJAX sendiri, tidak submit lewat
    form utama). Alasan UX: bill tersimpan itu menu sekunder, jangan
    mengganggu alur bikin transaksi baru yang jadi fokus utama kasir.
--}}
@if($billTersimpan->isNotEmpty())
<div class="card mb-3">
    <div class="card-header d-flex align-items-center gap-2">
        <span>Bill Tersimpan (Belum Dibayar)</span>
        <x-tooltip key="pos.bill_tersimpan" />
    </div>
    <div class="list-group list-group-flush">
        @foreach($billTersimpan as $bill)
            <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong>{{ $bill->nomor_order }}</strong>
                    <div class="small text-muted">{{ $bill->tipe_transaksi?->label() }}@if($bill->nomor_meja) — Meja {{ $bill->nomor_meja }}@endif</div>
                </div>
                <div class="text-end">
                    <div class="fw-bold mb-1">Rp {{ number_format($bill->total_bayar, 0, ',', '.') }}</div>
                    <div class="d-flex gap-1 justify-content-end flex-wrap">
                        <button type="button" class="btn btn-sm btn-success" onclick="bayarBillTersimpan({{ $bill->id }}, {{ $bill->total_bayar }})">
                            Tunai Pas
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="bukaModalBayarBill({{ $bill->id }}, {{ $bill->total_bayar }}, '{{ addslashes($bill->nomor_order) }}')">
                            <i class="bi bi-credit-card me-1"></i>Bayar...
                        </button>
                        @can('order.bill_tersimpan.batalkan')
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="batalkanBillTersimpan({{ $bill->id }}, '{{ addslashes($bill->nomor_order) }}')"
                            title="Hanya user dengan izin yang bisa batalkan bill tersimpan">
                            <i class="bi bi-x-circle"></i>
                        </button>
                        @endcan
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif

{{--
    Modal Pembayaran Lengkap utk Bill Tersimpan (Bug 2c) — REUSE opsi metode
    bayar (Tunai/Transfer/QRIS) + Split Payment + Pilih Kas yang
    sama dengan form utama, dikemas sebagai modal supaya bisa dipanggil dari
    kartu Bill Tersimpan mana pun tanpa mengganggu form transaksi baru yang
    sedang diisi kasir. Submit -> AJAX ke endpoint charge-bill yang SAMA
    dengan tombol "Tunai Pas" (penjualan.charge-bill), cuma payments[]-nya
    dibangun dari pilihan user di modal ini (bisa split 2 metode).
--}}
@can('order.create')
<div class="modal fade" id="modalBayarBill" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Bayar Bill <span id="modalBayarBillNomor"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="bg-light rounded p-3 mb-3 text-center">
                    <div class="text-muted small">TOTAL TAGIHAN</div>
                    <div id="modalBayarBillTotal" class="fs-4 fw-bold">Rp 0</div>
                </div>

                <label class="form-label fw-semibold">Metode Pembayaran</label>
                <div class="d-flex gap-2 flex-wrap mb-2">
                    <input type="hidden" id="modalBillMetode1" value="tunai">
                    @foreach(['tunai' => ['Tunai','bi-cash'], 'transfer' => ['Transfer','bi-bank'], 'qris' => ['QRIS','bi-qr-code']] as $kode => [$label, $icon])
                    <button type="button" class="btn btn-outline-primary btn-sm modal-bill-metode-btn {{ $kode === 'tunai' ? 'active' : '' }}"
                        data-metode="{{ $kode }}" onclick="setModalBillMetode('{{ $kode }}')">
                        <i class="bi {{ $icon }} me-1"></i>{{ $label }}
                    </button>
                    @endforeach
                </div>

                <div class="form-check mt-2 mb-2">
                    <input class="form-check-input" type="checkbox" id="modalBillSplitCheck" onchange="toggleModalBillSplit()">
                    <label class="form-check-label small" for="modalBillSplitCheck">
                        <i class="bi bi-layers-half me-1"></i>Split Payment (bayar dengan 2 metode)
                    </label>
                </div>
                <div class="d-none border rounded p-2 mb-2" id="modalBillSplitSection">
                    <label class="form-label small fw-semibold mb-1">Metode ke-2 & Jumlah</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <select class="form-select form-select-sm" id="modalBillMetode2" onchange="updateModalBillBukti()">
                                <option value="tunai">Tunai</option>
                                <option value="transfer">Transfer</option>
                                <option value="qris">QRIS</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <input type="number" min="0" step="1" class="form-control form-control-sm text-end" id="modalBillJumlah2" placeholder="Jumlah metode ke-2" value="0">
                        </div>
                    </div>
                    <div class="form-text mb-0">Metode utama di atas jadi bagian pertama (sisa total), ini bagian kedua.</div>
                </div>

                <div id="modalBillBuktiSection" class="mt-2 d-none">
                    <label class="form-label small fw-semibold mb-1">Bukti Pembayaran <span class="text-danger" id="modalBillBuktiMark">*</span></label>
                    <input type="file" class="form-control form-control-sm" id="modalBillBukti" accept="image/*">
                    <div class="form-text">Untuk Transfer / QRIS. JPG/PNG/WEBP, maks 5 MB.</div>
                </div>
                @if($kasList->isNotEmpty())
                <label class="form-label small fw-semibold mt-2">Kas (opsional, default otomatis)</label>
                <select class="form-select form-select-sm" id="modalBillKas">
                    <option value="">-- Gunakan Default --</option>
                    @foreach($kasList as $k)
                    <option value="{{ $k->id }}">{{ $k->nama_kas }}@if($k->default_untuk) (default {{ $k->default_untuk }})@endif</option>
                    @endforeach
                </select>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" id="btnKonfirmasiBayarBill" onclick="prosesModalBayarBill()">
                    <i class="bi bi-check-lg me-1"></i>Konfirmasi Bayar
                </button>
            </div>
        </div>
    </div>
</div>
@endcan

{{-- Modal Cek Antrian --}}
@can('antrian.lihat')
<div class="modal fade" id="modalCekAntrian" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-search me-2 text-info"></i>Cek Antrian Cepat</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 mb-3">
                    <div class="col">
                        <input type="text" id="cekAntrianInput" class="form-control"
                            placeholder="Nomor antrian, nama pelanggan, atau nomor order..."
                            autocomplete="off">
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-primary" onclick="cekAntrianCari()">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
                <div id="cekAntrianResults">
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-search fs-2 d-block mb-1"></i>
                        Ketik nama atau nomor antrian untuk mencari.
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <a href="{{ route('antrian.cek') }}" class="btn btn-outline-info btn-sm me-auto">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Buka Halaman Cek Antrian
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endcan

{{-- Tahap 3 D'mentai — Modal Pilih Varian --}}
<div class="modal fade" id="modalVarian" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalVarianNamaProduk">Pilih Varian</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalVarianBody"></div>
            <div class="modal-footer">
                <div class="me-auto fw-bold" id="modalVarianHargaPreview"></div>
                <button type="button" class="btn" style="background:var(--brand-utama);color:var(--brand-utama-teks)" onclick="konfirmasiVarian()" id="btnKonfirmasiVarian" disabled>
                    Tambah ke Keranjang
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
@php
// Tahap 3 D'mentai — $produkJadiJson dipakai UNTUK DUA hal: (1) opsi dropdown
// "+ Tambah Manual" (tambahProduk(), fallback lama), (2) lookup data saat
// klik grid/item-tambahan (klikProdukGrid/klikItemTambahanGrid). Makanya
// gabungan produkJadi + itemTambahan, bukan cuma produkJadi murni.
$semuaItemDropdown = $produkJadi->concat($itemTambahan);
$produkJadiJson = $semuaItemDropdown->map(fn($i) => [
    'id'             => $i->id,
    'nama_item'      => $i->nama_item,
    'kode_item'      => $i->kode_item,
    'satuan'         => $i->satuan,
    'harga_jual'     => $i->harga_jual ?? 0,
    'stok_cabang'    => $i->stok_cabang,
    'stok_info'      => $i->stok_info ?? '',
    'stok_singkat'   => $i->stok_singkat ?? '',
    'stok_menipis'   => (bool) ($i->stok_menipis ?? false),
    'item_category_id' => $i->item_category_id,
    'punya_varian'   => (bool) $i->punya_varian,
    'bisa_dijual'    => $i->bisa_dijual,
    'variants'       => $i->punya_varian ? $i->variants->map(fn($v) => [
        'id' => $v->id,
        'harga_efektif' => (float) ($v->harga_cabang ?? $v->harga_efektif),
        'label' => $v->label,
        'attribute_value_ids' => $v->attributeValues->pluck('id'),
    ]) : [],
    'attributes'     => $i->punya_varian ? $i->attributes()->with('values')->get()->map(fn($a) => [
        'id' => $a->id, 'nama' => $a->nama,
        'values' => $a->values->map(fn($v) => ['id' => $v->id, 'nilai' => $v->nilai]),
    ]) : [],
])->values()->toArray();
@endphp
<script>
const produkJadi = @json($produkJadiJson);
window.POS_NOMOR_MEJA_AKTIF = {{ $cabangAktif?->nomor_meja_aktif ? 'true' : 'false' }};
// 4.44 Poin 2 — kolom catatan per item (ikut tercetak di Label Produk) hanya kalau label aktif di cabang ini
window.POS_LABEL_AKTIF = {{ $cabangAktif?->label_cup_aktif ? 'true' : 'false' }};
// 4.47 — kolom Harga/Unit hanya bisa diubah pemegang izin "Ubah Harga Jual di POS" (server ikut mengunci)
window.POS_BOLEH_UBAH_HARGA = {{ auth()->user()?->can('order.ubah_harga') ? 'true' : 'false' }};
window.POS_SERVICE_CHARGE_PERSEN = {{ (float) ($cabangAktif?->service_charge_persen ?? 0) }};
window.POS_TAKE_AWAY_FEE = {{ (float) ($cabangAktif?->take_away_fee ?? 0) }};
// Tahap B 4.38 — wajib bukti bayar per cabang
window.POS_WAJIB_BUKTI = { transfer: {{ ($cabangAktif?->wajib_bukti_transfer ?? true) ? 'true' : 'false' }}, qris: {{ ($cabangAktif?->wajib_bukti_qris ?? true) ? 'true' : 'false' }} };
// Tahap C 4.38 — PO cabang (supplier internal) → isi keranjang otomatis
@php
    $poMasukJson = $poMasuk->map(fn ($po) => [
        'id' => $po->id,
        'nomor' => $po->nomor_po,
        'cabang' => $po->cabang?->nama_cabang ?? '-',
        'items' => $po->items->map(fn ($i) => [
            // 4.39 — barang di PO milik cabang pemesan → pasangkan ke produk lokasi ini lewat Kode Item
            'item_id' => $i->item ? (app(\App\Services\PadananBarangService::class)->cari($i->item, (int) $cabangId)?->id ?? $i->item_id) : $i->item_id,
            'nama' => $i->item?->nama_item ?? '-',
            'qty' => (float) $i->qty_pesan,
            'harga_po' => (float) $i->harga_satuan,
        ])->values(),
    ])->values();
@endphp
window.POS_PO_MASUK = @json($poMasukJson);

async function isiDariPo(poId) {
    const po = window.POS_PO_MASUK.find(p => p.id === poId);
    if (!po) return;
    if (document.querySelectorAll('.pos-item-row').length) {
        const ok = await window.showConfirm('Ganti isi keranjang?', 'Keranjang sekarang akan dikosongkan dan diisi dari ' + po.nomor + '.',
            { icon: 'question', confirmText: 'Ya, Isi dari PO', cancelText: 'Batal' });
        if (!ok || (ok.isConfirmed === false)) return;
        document.getElementById('itemContainer').innerHTML = '';
    }

    const dilewati = [], bedaHarga = [];
    po.items.forEach(row => {
        const item = produkJadi.find(p => p.id === row.item_id);
        if (!item || !item.bisa_dijual) { dilewati.push(row.nama); return; }
        tambahkanItemKeForm(item, null, false); // baris PO selalu baris sendiri (qty diisi dari PO)
        const idx = itemIdx - 1;
        const qtyEl = document.querySelector(`[name="items[${idx}][qty]"]`);
        if (qtyEl) qtyEl.value = row.qty;
        hitungSubtotal(idx);
        if (Math.round(Number(item.harga_jual)) !== Math.round(row.harga_po)) {
            bedaHarga.push(`${row.nama}: PO Rp ${Math.round(row.harga_po).toLocaleString('id-ID')} ≠ harga di sini Rp ${Math.round(item.harga_jual).toLocaleString('id-ID')}`);
        }
    });
    showEmptyMsg(!document.querySelectorAll('.pos-item-row').length);
    hitungTotal();

    setPelangganSelectValue('');
    document.getElementById('namaPelangganInput').value = po.cabang;
    document.getElementById('inputPurchaseOrderId').value = po.id;

    let html = `<i class="bi bi-inbox me-1"></i>Keranjang diisi dari <strong>${escHtml(po.nomor)}</strong> (${escHtml(po.cabang)}). `
        + `<a href="#" onclick="batalPakaiPo(); return false;">Lepas tautan PO</a>`;
    if (bedaHarga.length) html += `<div class="text-danger mt-1"><i class="bi bi-exclamation-triangle me-1"></i>Harga berbeda dgn PO — cabang perlu mengedit PO-nya:<br>${bedaHarga.map(escHtml).join('<br>')}</div>`;
    if (dilewati.length) html += `<div class="text-warning mt-1">Tidak dijual di POS lokasi ini (dilewati): ${dilewati.map(escHtml).join(', ')}</div>`;
    const info = document.getElementById('infoPoAktif');
    info.innerHTML = html;
    info.classList.remove('d-none');
    document.getElementById('formPos').scrollIntoView({ behavior: 'smooth' });
}

function batalPakaiPo() {
    document.getElementById('inputPurchaseOrderId').value = '';
    const info = document.getElementById('infoPoAktif');
    info.innerHTML = '';
    info.classList.add('d-none');
}

function butuhBukti(metodes) { return metodes.some(m => (m === 'transfer' && window.POS_WAJIB_BUKTI.transfer) || (m === 'qris' && window.POS_WAJIB_BUKTI.qris)); }
function metodePosAktif() {
    const tipe = document.getElementById('tipePembayaranInput').value;
    const split = document.getElementById('splitPaymentCheck')?.checked;
    return split ? [tipe, document.getElementById('splitMetode2')?.value] : [tipe];
}
function updateBuktiSection() {
    const ms = metodePosAktif();
    document.getElementById('sectionBuktiPembayaran').style.display = ms.some(m => m && m !== 'tunai') ? '' : 'none';
    const mark = document.getElementById('buktiWajibMark');
    if (mark) mark.style.display = butuhBukti(ms) ? '' : 'none';
}

let itemIdx = 0;

function tambahProduk() {
    showEmptyMsg(false);
    const idx = itemIdx++;
    const opts = produkJadi.map(p =>
        `<option value="${p.id}" data-harga="${p.harga_jual}" data-satuan="${p.satuan}" data-stok="${p.stok_cabang}" data-stok-info="${escHtml(p.stok_info)}" data-menipis="${p.stok_menipis ? 1 : 0}">
            ${p.nama_item} (${p.kode_item})${p.stok_singkat ? ' - Stok: ' + p.stok_singkat : ''}
        </option>`
    ).join('');

    appendItemRow(idx, `
        <input type="hidden" name="items[${idx}][tipe]" value="produk_jadi">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-sm-6">
                <label class="form-label small fw-semibold">Produk Jadi</label>
                <select name="items[${idx}][item_id]" class="form-select form-select-sm"
                    onchange="onProdukChange(this, ${idx})" required>
                    <option value="">-- Pilih Produk --</option>
                    ${opts}
                </select>
                <input type="hidden" name="items[${idx}][nama_item]" id="namaItem_${idx}" value="">
            </div>
            <div class="col-4 col-sm-2">
                <label class="form-label small">Qty</label>
                <input type="number" name="items[${idx}][qty]" class="form-control form-control-sm qty-input"
                    step="0.001" min="0.001" value="1" required onchange="hitungSubtotal(${idx})">
                <input type="hidden" name="items[${idx}][satuan]" id="satuan_${idx}" value="pack">
            </div>
            <div class="col-8 col-sm-3">
                <label class="form-label small">Harga/Unit${window.POS_BOLEH_UBAH_HARGA ? '' : ' <i class="bi bi-lock-fill text-muted" title="Harga terkunci — hanya akun berizin Ubah Harga Jual di POS"></i>'}</label>
                <input type="number" name="items[${idx}][harga_satuan]" class="form-control form-control-sm harga-input${window.POS_BOLEH_UBAH_HARGA ? '' : ' bg-light'}"
                    step="1" min="0" value="0" required onchange="hitungSubtotal(${idx})"${window.POS_BOLEH_UBAH_HARGA ? '' : ' readonly tabindex="-1"'}>
            </div>
            <div class="col-12 col-sm-1 d-flex align-items-end justify-content-end">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="hapusItem(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            ${window.POS_LABEL_AKTIF ? `<div class="col-12">
                <input type="text" name="items[${idx}][catatan]" class="form-control form-control-sm" maxlength="255"
                    placeholder="Catatan item (opsional) — ikut tercetak di label" title="Ikut tercetak di Label Produk">
            </div>` : ''}
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted" id="stokInfo_${idx}"></small>
                    <small class="fw-semibold text-primary subtotal-display" id="subtotal_${idx}">Rp 0</small>
                </div>
            </div>
        </div>
    `);
}

// ===== Tahap 3 D'mentai — Tipe Transaksi =====
function pilihTipeTransaksi(tipe) {
    document.getElementById('tipeTransaksiInput').value = tipe;
    document.querySelectorAll('.btn-tipe-transaksi').forEach(b => b.classList.toggle('active', b.dataset.tipe === tipe));
    const wrapMeja = document.getElementById('wrapNomorMeja');
    if (tipe === 'dine_in' && window.POS_NOMOR_MEJA_AKTIF) {
        wrapMeja.classList.remove('d-none');
    } else {
        wrapMeja.classList.add('d-none');
        const inputMeja = document.getElementById('inputNomorMeja');
        if (inputMeja) inputMeja.value = '';
    }
    hitungTotal(); // takeaway fee bergantung tipe transaksi
}

// ===== Tahap 3 D'mentai — Filter Grid Produk =====
function filterKategoriGrid(kategoriId, btnEl) {
    document.querySelectorAll('#filterKategoriGrid .btn').forEach(b => {
        b.classList.remove('active', 'btn-dark');
        b.classList.add('btn-outline-dark');
    });
    btnEl.classList.add('active', 'btn-dark');
    btnEl.classList.remove('btn-outline-dark');
    applyGridFilter(kategoriId, document.getElementById('searchProdukGrid')?.value || '');
}

document.getElementById('searchProdukGrid')?.addEventListener('input', function () {
    const activeBtn = document.querySelector('#filterKategoriGrid .btn.active');
    applyGridFilter(activeBtn ? activeBtn.dataset.kategori : 'all', this.value);
});

function applyGridFilter(kategoriId, search) {
    const term = (search || '').toLowerCase().trim();
    document.querySelectorAll('#gridProduk .pos-card').forEach(card => {
        const matchKategori = kategoriId === 'all' || card.dataset.kategori === String(kategoriId);
        const matchSearch = !term || card.dataset.nama.includes(term);
        card.style.display = (matchKategori && matchSearch) ? '' : 'none';
    });
}

// ===== Tahap 3 D'mentai — Klik Grid Produk / Item Tambahan (1-klik ke keranjang) =====
// Prinsip reuse: fungsi ini MEMANGGIL tambahProduk() existing untuk bikin
// row, lalu program isi dropdown item_id-nya + trigger onProdukChange()
// existing — supaya logic subtotal/stok-info yang sudah teruji TIDAK
// diduplikasi/ditulis ulang sama sekali.
function klikProdukGrid(itemId) {
    const item = produkJadi.find(p => p.id === itemId);
    if (!item || !item.bisa_dijual) return;

    if (item.punya_varian) {
        bukaModalVarian(item);
        return;
    }
    tambahkanItemKeForm(item, null);
}

function klikItemTambahanGrid(itemId) {
    const item = produkJadi.find(p => p.id === itemId);
    if (!item || !item.bisa_dijual) return;
    tambahkanItemKeForm(item, null);
}

// 4.47 — produk (+ varian) yang sudah ada di Item Order dan belum diberi
// catatan item → qty +1, bukan baris baru. Mengembalikan true kalau digabung.
function gabungKeBarisSama(item, variant) {
    const varianId = variant ? String(variant.id) : '';
    const rows = document.querySelectorAll('#itemContainer .pos-item-row');
    for (const row of rows) {
        const idx = row.dataset.idx;
        const sel = row.querySelector(`select[name="items[${idx}][item_id]"]`);
        if (!sel || String(sel.value) !== String(item.id)) continue;
        const v = row.querySelector(`input[name="items[${idx}][item_variant_id]"]`);
        if ((v ? String(v.value) : '') !== varianId) continue;
        const cat = row.querySelector(`input[name="items[${idx}][catatan]"]`);
        if (cat && cat.value.trim() !== '') continue;
        const qty = row.querySelector('.qty-input');
        if (!qty) continue;
        qty.value = Math.round(((parseFloat(qty.value) || 0) + 1) * 1000) / 1000;
        hitungSubtotal(idx);
        row.classList.remove('pos-item-tambah');
        void row.offsetWidth;
        row.classList.add('pos-item-tambah');
        return true;
    }
    return false;
}

function tambahkanItemKeForm(item, variant, gabung = true) {
    if (gabung && gabungKeBarisSama(item, variant)) return;
    tambahProduk();
    const idx = itemIdx - 1;
    const row = document.querySelector(`.pos-item-row[data-idx="${idx}"]`);
    if (!row) return;
    const select = row.querySelector(`select[name="items[${idx}][item_id]"]`);
    if (select) {
        select.value = item.id;
        onProdukChange(select, idx);
    }

    if (variant) {
        const hargaInput = row.querySelector('.harga-input');
        if (hargaInput) hargaInput.value = variant.harga_efektif;
        const namaEl = document.getElementById(`namaItem_${idx}`);
        if (namaEl) namaEl.value = `${item.nama_item} - ${variant.label}`;

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = `items[${idx}][item_variant_id]`;
        hidden.value = variant.id;
        row.appendChild(hidden);

        hitungSubtotal(idx);
    }
}

// ===== Tahap 3 D'mentai — Modal Pilih Varian =====
let varianState = { item: null, pilihan: {}, matched: null };

function bukaModalVarian(item) {
    varianState = { item, pilihan: {}, matched: null };
    document.getElementById('modalVarianNamaProduk').textContent = item.nama_item;
    const body = document.getElementById('modalVarianBody');
    body.innerHTML = item.attributes.map(attr => `
        <div class="mb-2">
            <label class="form-label small fw-semibold">${attr.nama}</label>
            <select class="form-select" onchange="pilihAtributVarian(${attr.id}, this.value)">
                <option value="">-- Pilih ${attr.nama} --</option>
                ${attr.values.map(v => `<option value="${v.id}">${v.nilai}</option>`).join('')}
            </select>
        </div>
    `).join('');
    document.getElementById('modalVarianHargaPreview').textContent = '';
    document.getElementById('btnKonfirmasiVarian').disabled = true;
    new bootstrap.Modal(document.getElementById('modalVarian')).show();
}

function pilihAtributVarian(attrId, valueId) {
    varianState.pilihan[attrId] = parseInt(valueId) || null;
    const totalAttr = varianState.item.attributes.length;
    const dipilih = Object.values(varianState.pilihan).filter(v => v).length;

    if (dipilih === totalAttr) {
        const idsPilihan = Object.values(varianState.pilihan).sort();
        const match = varianState.item.variants.find(v => {
            const idsVarian = [...v.attribute_value_ids].sort();
            return JSON.stringify(idsVarian) === JSON.stringify(idsPilihan);
        });
        if (match) {
            varianState.matched = match;
            document.getElementById('modalVarianHargaPreview').textContent = 'Rp ' + match.harga_efektif.toLocaleString('id-ID');
            document.getElementById('btnKonfirmasiVarian').disabled = false;
            return;
        }
    }
    varianState.matched = null;
    document.getElementById('modalVarianHargaPreview').textContent = '';
    document.getElementById('btnKonfirmasiVarian').disabled = true;
}

function konfirmasiVarian() {
    if (!varianState.matched) return;
    tambahkanItemKeForm(varianState.item, varianState.matched);
    bootstrap.Modal.getInstance(document.getElementById('modalVarian')).hide();
}

// ===== Tahap 3 D'mentai — Bayar Bill Tersimpan (quick-pay tunai pas) =====
async function bayarBillTersimpan(orderId, total) {
    const result = await window.showConfirm(
        'Bayar Bill',
        `Total: Rp ${Number(total).toLocaleString('id-ID')}<br>Metode: Tunai (pas, tanpa kembalian)`,
        { icon: 'question', confirmText: 'Ya, Bayar', cancelText: 'Batal' }
    );
    if (!result.isConfirmed) return;

    try {
        const response = await fetch(`{{ url('/penjualan') }}/${orderId}/charge`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ payments: [{ metode: 'tunai', jumlah: total }] }),
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            await window.showAlert('error', 'Gagal', data.message || 'Gagal memproses pembayaran.');
            return;
        }
        window.location.href = data.redirect_url;
    } catch (err) {
        await window.showAlert('error', 'Gagal', 'Gagal terhubung ke server.');
    }
}

// ===== Tahap 7 D'mentai (Bug 2b) — Batalkan Bill Tersimpan =====
async function batalkanBillTersimpan(orderId, nomorOrder) {
    const result = await window.showConfirm(
        'Batalkan Bill?',
        `Bill <strong>${nomorOrder}</strong> akan dibatalkan dan tidak jadi diproses. `
        + `Bill ini belum pernah dibayar dan stok belum terpotong sama sekali, jadi tidak ada yang perlu dikembalikan.`,
        { icon: 'warning', confirmText: 'Ya, Batalkan', cancelText: 'Tidak' }
    );
    if (!result.isConfirmed) return;

    try {
        const response = await fetch(`{{ url('/penjualan') }}/${orderId}/batalkan-bill`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            await window.showAlert('error', 'Gagal', data.message || 'Gagal membatalkan bill.');
            return;
        }
        await window.showAlert('success', 'Bill Dibatalkan', data.message);
        window.location.reload();
    } catch (err) {
        await window.showAlert('error', 'Gagal', 'Gagal terhubung ke server.');
    }
}

// ===== Tahap 7 D'mentai (Bug 2c) — Modal Pembayaran Lengkap utk Bill Tersimpan =====
let modalBillState = { orderId: null, total: 0, metode1: 'tunai' };

function bukaModalBayarBill(orderId, total, nomorOrder) {
    modalBillState = { orderId, total, metode1: 'tunai' };
    document.getElementById('modalBayarBillNomor').textContent = nomorOrder;
    document.getElementById('modalBayarBillTotal').textContent = 'Rp ' + Number(total).toLocaleString('id-ID');
    document.querySelectorAll('.modal-bill-metode-btn').forEach(b => b.classList.toggle('active', b.dataset.metode === 'tunai'));
    document.getElementById('modalBillSplitCheck').checked = false;
    document.getElementById('modalBillSplitSection').classList.add('d-none');
    document.getElementById('modalBillJumlah2').value = 0;
    const kasSelect = document.getElementById('modalBillKas');
    if (kasSelect) kasSelect.value = '';
    document.getElementById('modalBillBukti').value = '';
    updateModalBillBukti();
    new bootstrap.Modal(document.getElementById('modalBayarBill')).show();
}

function setModalBillMetode(kode) {
    modalBillState.metode1 = kode;
    document.querySelectorAll('.modal-bill-metode-btn').forEach(b => b.classList.toggle('active', b.dataset.metode === kode));
    updateModalBillBukti();
}

function toggleModalBillSplit() {
    const checked = document.getElementById('modalBillSplitCheck').checked;
    document.getElementById('modalBillSplitSection').classList.toggle('d-none', !checked);
    updateModalBillBukti();
}

// Tahap B 4.38 — bukti bayar di modal Bayar Bill mengikuti saklar cabang.
function metodeModalBill() {
    const split = document.getElementById('modalBillSplitCheck').checked;
    return split ? [modalBillState.metode1, document.getElementById('modalBillMetode2').value] : [modalBillState.metode1];
}
function updateModalBillBukti() {
    const ms = metodeModalBill();
    document.getElementById('modalBillBuktiSection').classList.toggle('d-none', !ms.some(m => m && m !== 'tunai'));
    document.getElementById('modalBillBuktiMark').style.display = butuhBukti(ms) ? '' : 'none';
}

async function prosesModalBayarBill() {
    const isSplit = document.getElementById('modalBillSplitCheck').checked;
    let payments = [];

    if (isSplit) {
        const metode2 = document.getElementById('modalBillMetode2').value;
        const jumlah2 = Number(document.getElementById('modalBillJumlah2').value) || 0;
        if (jumlah2 <= 0 || jumlah2 >= modalBillState.total) {
            await window.showAlert('error', 'Jumlah Tidak Valid', 'Jumlah metode ke-2 harus lebih dari 0 dan kurang dari total tagihan.');
            return;
        }
        const jumlah1 = modalBillState.total - jumlah2;
        payments = [
            { metode: modalBillState.metode1, jumlah: jumlah1 },
            { metode: metode2, jumlah: jumlah2 },
        ];
    } else {
        payments = [{ metode: modalBillState.metode1, jumlah: modalBillState.total }];
    }

    const kasSelect = document.getElementById('modalBillKas');
    const kasId = kasSelect ? kasSelect.value : '';
    if (kasId) {
        payments = payments.map(p => ({ ...p, kas_id: Number(kasId) }));
    }

    const buktiFile = document.getElementById('modalBillBukti').files[0] || null;
    if (butuhBukti(payments.map(p => p.metode)) && !buktiFile) {
        await window.showAlert('error', 'Bukti Pembayaran Wajib', 'Harap upload bukti pembayaran untuk metode Transfer/QRIS di outlet ini!');
        return;
    }

    const btn = document.getElementById('btnKonfirmasiBayarBill');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';

    try {
        // FormData supaya file bukti ikut terkirim (payments tetap array).
        const fd = new FormData();
        payments.forEach((p, i) => Object.entries(p).forEach(([k, v]) => fd.append(`payments[${i}][${k}]`, v)));
        if (buktiFile) fd.append('bukti_pembayaran', buktiFile);
        const response = await fetch(`{{ url('/penjualan') }}/${modalBillState.orderId}/charge`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: fd,
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            await window.showAlert('error', 'Gagal', data.message || 'Gagal memproses pembayaran.');
            return;
        }
        window.location.href = data.redirect_url;
    } catch (err) {
        await window.showAlert('error', 'Gagal', 'Gagal terhubung ke server.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

// ===== Tahap 3 D'mentai — Save Bill =====
async function simpanBill() {
    const tipeTransaksi = document.getElementById('tipeTransaksiInput').value;
    if (!tipeTransaksi) {
        await window.showAlert('error', 'Tipe Transaksi Belum Dipilih', 'Pilih Dine-in / Takeaway / Frozen dulu.');
        return;
    }
    if (tipeTransaksi === 'dine_in' && window.POS_NOMOR_MEJA_AKTIF) {
        const mejaEl = document.getElementById('inputNomorMeja');
        if (!mejaEl.value.trim()) {
            await window.showAlert('error', 'Nomor Meja Belum Diisi', 'Nomor meja wajib diisi untuk transaksi Dine-in.');
            mejaEl.focus();
            return;
        }
    }
    if (!document.querySelectorAll('.pos-item-row').length) {
        await window.showAlert('warning', 'Item Kosong', 'Harap tambahkan minimal 1 item!');
        return;
    }

    const confirmResult = await window.showConfirm(
        'Simpan Bill?',
        'Order akan disimpan TANPA potong stok/bayar. Bisa dilanjutkan nanti dari daftar Bill Tersimpan.',
        { icon: 'question', confirmText: 'Ya, Simpan', cancelText: 'Batal' }
    );
    if (!confirmResult.isConfirmed) return;

    const btn = document.getElementById('btnSaveBill');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';

    try {
        const formData = new FormData(document.getElementById('formPos'));
        const response = await fetch('{{ route("penjualan.simpan-bill") }}', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            await window.showAlert('error', 'Gagal Simpan Bill', data.message || 'Terjadi kesalahan.');
            return;
        }
        await window.showAlert('success', 'Bill Tersimpan', data.message);
        window.location.reload();
    } catch (err) {
        await window.showAlert('error', 'Gagal', 'Gagal terhubung ke server.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

function appendItemRow(idx, html) {
    const container = document.getElementById('itemContainer');
    const div = document.createElement('div');
    div.className = 'pos-item-row';
    div.setAttribute('data-idx', idx);
    div.innerHTML = html;
    container.appendChild(div);
    hitungTotal();
}

function onProdukChange(sel, idx) {
    const opt = sel.selectedOptions[0];
    if (!opt || !opt.value) return;
    const harga = opt.dataset.harga || 0;
    const satuan = opt.dataset.satuan || 'pcs';
    const row = sel.closest('.pos-item-row');
    row.querySelector('.harga-input').value = harga;
    const namaEl = document.getElementById(`namaItem_${idx}`);
    if (namaEl) namaEl.value = opt.text.split(' (')[0].trim();
    const satEl = document.getElementById(`satuan_${idx}`);
    if (satEl) satEl.value = satuan;
    const stokEl = document.getElementById(`stokInfo_${idx}`);
    // 4.40 — dari bahan resep di outlet ini (produk tanpa resep: stoknya sendiri)
    if (stokEl) {
        stokEl.textContent = opt.dataset.stokInfo || '';
        stokEl.className = opt.dataset.menipis === '1' ? 'text-warning fw-semibold' : 'text-muted';
    }
    hitungSubtotal(idx);
}

function hitungSubtotal(idx) {
    const row = document.querySelector(`[data-idx="${idx}"]`);
    if (!row) return;
    const qty = parseFloat(row.querySelector('.qty-input')?.value) || 0;
    const harga = parseFloat(row.querySelector('.harga-input')?.value) || 0;
    const sub = document.getElementById(`subtotal_${idx}`);
    if (sub) sub.textContent = 'Rp ' + (qty * harga).toLocaleString('id-ID');
    hitungTotal();
}

function hapusItem(btn) {
    btn.closest('.pos-item-row').remove();
    hitungTotal();
    if (!document.querySelectorAll('.pos-item-row').length) showEmptyMsg(true);
}

function showEmptyMsg(show) {
    document.getElementById('emptyItems').style.display = show ? '' : 'none';
}

// Convert kg ke satuan paling readable untuk tampilan ringkasan POS saja
// (data yang disimpan ke server tetap dalam kg, tidak disentuh di sini).
function formatBerat(kg) {
    kg = parseFloat(kg);
    if (isNaN(kg) || kg <= 0) return '0 gram';

    if (kg >= 1) {
        return (kg % 1 === 0 ? kg.toFixed(0) : kg.toFixed(2).replace(/\.?0+$/, '')) + ' kg';
    } else if (kg >= 0.1) {
        const ons = kg * 10;
        return (ons % 1 === 0 ? ons.toFixed(0) : ons.toFixed(1).replace(/\.?0+$/, '')) + ' ons';
    } else {
        const gram = kg * 1000;
        return (gram % 1 === 0 ? gram.toFixed(0) : gram.toFixed(1).replace(/\.?0+$/, '')) + ' gram';
    }
}

function hitungTotal() {
    let subtotal = 0;
    const rows = document.querySelectorAll('.pos-item-row');
    let listHtml = '';

    rows.forEach(row => {
        const idx = row.dataset.idx;
        const tipeInput = row.querySelector(`[name="items[${idx}][tipe]"]`);
        const tipe = tipeInput?.value || '';
        let namaEl = row.querySelector(`[name="items[${idx}][nama_item]"]`);
        let nama = namaEl?.value || '-';

        if (tipe === 'produk_jadi') {
            const sel = row.querySelector(`select[name="items[${idx}][item_id]"]`);
            if (sel?.selectedOptions[0]?.value) {
                nama = sel.selectedOptions[0].text.split(' (')[0].trim();
            }
        }

        const qty = parseFloat(row.querySelector('.qty-input')?.value || document.getElementById(`qtyJasa_${idx}`)?.value || 1) || 0;
        const harga = parseFloat(row.querySelector('.harga-input')?.value) || 0;
        const sub = qty * harga;
        subtotal += sub;

        // Label 1 baris: qty + satuan master. Tidak mempengaruhi hitungan
        // sub/total -- murni label tampilan.
        const satuan = document.getElementById(`satuan_${idx}`)?.value || '';
        const qtyLabel = parseFloat(qty.toFixed(3));
        // 4.47 — seperti struk: nama, lalu "qty satuan x harga satuan" + subtotal
        listHtml += `<div class="mb-2">
            <div class="fw-medium">${escHtml(nama)}</div>
            <div class="d-flex justify-content-between align-items-center small">
                <div class="text-muted me-2">${qtyLabel.toLocaleString('id-ID')} ${escHtml(satuan)} x Rp ${harga.toLocaleString('id-ID')}</div>
                <div class="text-end fw-medium text-nowrap">Rp ${sub.toLocaleString('id-ID')}</div>
            </div>
        </div>`;
    });

    document.getElementById('subtotalList').innerHTML = listHtml || '<div class="text-muted small text-center">Belum ada item</div>';

    const diskon = rupiahParse(document.getElementById('diskonInput').value);
    const subtotalSetelahDiskon = Math.max(0, subtotal - diskon);

    // Tahap 3 D'mentai — service charge & takeaway fee, dihitung sama persis
    // formula backend (PenjualanService::simpanBillInternal) supaya total
    // yang ditampilkan ke kasir konsisten dengan yang benar-benar tersimpan.
    const tipeTransaksi = document.getElementById('tipeTransaksiInput')?.value || '';
    const serviceCharge = Math.round(subtotalSetelahDiskon * (window.POS_SERVICE_CHARGE_PERSEN / 100));
    const takeAwayFee = tipeTransaksi === 'takeaway' ? window.POS_TAKE_AWAY_FEE : 0;
    const total = subtotalSetelahDiskon + serviceCharge + takeAwayFee;

    document.getElementById('totalBayarDisplay').textContent = 'Rp ' + total.toLocaleString('id-ID');
    renderNominalCepat(total);

    hitungKembalian();
}

// Tahap 3 D'mentai — Split Payment
function toggleSplitPayment() {
    const on = document.getElementById('splitPaymentCheck').checked;
    document.getElementById('sectionSplitPayment').classList.toggle('d-none', !on);
    if (!on) document.getElementById('splitJumlah2').value = '0';
    updateBuktiSection();
    hitungKembalian();
}

function hitungKembalian() {
    const totalText = document.getElementById('totalBayarDisplay').textContent.replace(/[^0-9]/g, '');
    const total = parseInt(totalText) || 0;
    const tipe = document.getElementById('tipePembayaranInput').value;
    const isSplit = document.getElementById('splitPaymentCheck')?.checked;

    const bayarUtama = tipe === 'tunai' ? rupiahParse(document.getElementById('jumlahBayarInput').value) : total;
    const bayarKedua = isSplit ? rupiahParse(document.getElementById('splitJumlah2')?.value || '0') : 0;
    const totalDibayar = bayarUtama + bayarKedua;

    if (tipe !== 'tunai' && !isSplit) {
        document.getElementById('kembalianDisplay').textContent = '-';
        return;
    }

    const kembalian = Math.max(0, totalDibayar - total);
    document.getElementById('kembalianDisplay').textContent = 'Rp ' + kembalian.toLocaleString('id-ID');
    document.getElementById('kembalianDisplay').className = totalDibayar >= total ? 'fs-5 fw-bold text-success' : 'fs-5 fw-bold text-danger';
}

function setTipePembayaran(tipe) {
    document.getElementById('tipePembayaranInput').value = tipe;
    ['tunai','transfer','qris'].forEach(t => {
        const btn = document.getElementById('btn' + t.charAt(0).toUpperCase() + t.slice(1));
        if (btn) {
            btn.className = t === tipe
                ? 'btn btn-primary payment-btn flex-fill'
                : 'btn btn-outline-primary payment-btn flex-fill';
        }
    });

    const isTunai = tipe === 'tunai';
    document.getElementById('sectionJumlahBayar').style.display = isTunai ? '' : 'none';
    document.getElementById('sectionKembalian').style.display = isTunai ? '' : 'none';
    updateBuktiSection();

    if (!isTunai) {
        // set jumlah_bayar = total untuk non-tunai
        const totalText = document.getElementById('totalBayarDisplay').textContent.replace(/[^0-9]/g, '');
        document.getElementById('jumlahBayarInput').value = rupiahFmt(totalText);
    }
    hitungKembalian();

    // Reset manual override dan update kas default
    const kasManual = document.getElementById('kasManualSelect');
    if (kasManual) kasManual.value = '';
    updateKasDefault(tipe);
}

// ===== BUKTI PEMBAYARAN =====
function onBuktiFileChange(input) {
    if (input.files && input.files[0]) {
        showBuktiPreview(input.files[0]);
        // Sync ke input kamera agar form hanya kirim 1 file
        document.getElementById('buktiKameraInput').value = '';
    }
}

function onBuktiKameraChange(input) {
    if (input.files && input.files[0]) {
        showBuktiPreview(input.files[0]);
        // Pindahkan file ke input utama (bukti_pembayaran)
        const dt = new DataTransfer();
        dt.items.add(input.files[0]);
        document.getElementById('buktiFotoInput').files = dt.files;
        input.value = '';
    }
}

function showBuktiPreview(file) {
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('buktiPreview').src = e.target.result;
        document.getElementById('buktiPreviewWrap').style.display = '';
        document.getElementById('buktiButtons').style.display = 'none';
    };
    reader.readAsDataURL(file);
}

function hapusBukti() {
    document.getElementById('buktiFotoInput').value = '';
    document.getElementById('buktiKameraInput').value = '';
    document.getElementById('buktiPreview').src = '';
    document.getElementById('buktiPreviewWrap').style.display = 'none';
    document.getElementById('buktiButtons').style.display = '';
}

function setBayar(nominal) {
    document.getElementById('jumlahBayarInput').value = rupiahFmt(nominal);
    hitungKembalian();
}

// 4.41 — tombol "Uang Pas" (= total belanja) + nominal pintar: pembulatan ke atas
// 5rb/10rb/50rb/100rb yang lebih besar dari total, maks 3 tombol.
function renderNominalCepat(total) {
    const wrap = document.getElementById('nominalCepat');
    if (!wrap) return;
    if (!total || total <= 0) { wrap.innerHTML = ''; return; }
    const saran = [];
    [5000, 10000, 50000, 100000].forEach(k => {
        const n = Math.ceil(total / k) * k;
        if (n > total && !saran.includes(n)) saran.push(n);
    });
    const rp = n => 'Rp ' + n.toLocaleString('id-ID');
    let html = `<div class="col-12"><button type="button" class="btn btn-success w-100 fw-semibold" id="btnUangPas" onclick="setBayar(${total})">
        <i class="bi bi-cash-coin me-1"></i>Uang Pas · ${rp(total)}</button></div>`;
    saran.slice(0, 3).forEach(n => {
        html += `<div class="col-4"><button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="setBayar(${n})">${rp(n)}</button></div>`;
    });
    wrap.innerHTML = html;
}

// 4.41 — tipe otomatis (pilihan disembunyikan / hanya 1 tipe aktif) langsung terpilih
document.addEventListener('DOMContentLoaded', () => {
    const tipeOtomatis = @json($tipeOtomatis);
    if (tipeOtomatis) pilihTipeTransaksi(tipeOtomatis);
});

function onPelangganChange(sel) {
    const opt = sel.selectedOptions[0];
    if (!opt || !opt.value) return;
    document.getElementById('namaPelangganInput').value = opt.dataset.nama || '';
    document.getElementById('teleponPelangganInput').value = opt.dataset.telepon || '';
}

// Searchable dropdown pelanggan (Select2) — cari by nama, telepon, atau kode.
// Select2 tetap menyinkronkan <select> asli & memicu event 'change' native,
// jadi onchange="onPelangganChange(this)" di atas tidak perlu diubah sama
// sekali. Kalau jQuery/Select2 gagal load (CDN down dsb), fallback otomatis
// ke dropdown native HTML biasa (tetap bisa dipakai, cuma tanpa search box).
try {
    if (typeof $ !== 'undefined' && $.fn && $.fn.select2) {
        $('#pelangganSelect').select2({
            theme: 'bootstrap-5',
            placeholder: 'Ketik nama, telepon, atau kode pelanggan...',
            allowClear: true,
            width: '100%',
            language: {
                noResults: function () { return 'Pelanggan tidak ditemukan'; },
                searching: function () { return 'Mencari...'; },
            },
            matcher: function (params, data) {
                if ($.trim(params.term) === '') return data;
                if (typeof data.text === 'undefined') return null;

                const term    = params.term.toLowerCase();
                const text    = data.text.toLowerCase();
                const $option = $(data.element);
                const telepon = ($option.data('telepon') || '').toString().toLowerCase();
                const kode    = ($option.data('kode') || '').toString().toLowerCase();

                if (text.indexOf(term) > -1 || telepon.indexOf(term) > -1 || kode.indexOf(term) > -1) {
                    return data;
                }
                return null;
            },
        });
    }
} catch (e) {
    console.warn('Select2 gagal load, pakai dropdown pelanggan native:', e);
}


// Set value + reset dropdown pelanggan — dipakai resetPosForm(). Kalau
// Select2 aktif, .value assignment biasa tidak mengupdate tampilan widget-nya
// (Select2 merender elemen <span> terpisah di atas <select> asli), jadi perlu
// lewat API jQuery supaya UI ikut ter-refresh. Fallback native tetap jalan
// kalau Select2 tidak aktif.
function setPelangganSelectValue(value) {
    const el = document.getElementById('pelangganSelect');
    if (!el) return;
    if (typeof $ !== 'undefined' && $.fn && $.fn.select2 && $(el).hasClass('select2-hidden-accessible')) {
        $(el).val(value).trigger('change');
    } else {
        el.value = value;
    }
}

// Focus dropdown pelanggan — kalau Select2 aktif, .focus() ke <select> asli
// tidak memindahkan fokus visual (elemen aslinya disembunyikan Select2),
// jadi fokuskan elemen widget-nya langsung.
function focusPelangganSelect() {
    const el = document.getElementById('pelangganSelect');
    if (!el) return;
    const container = el.nextElementSibling;
    if (container && container.classList.contains('select2-container')) {
        const target = container.querySelector('.select2-selection');
        if (target) { target.focus(); return; }
    }
    el.focus();
}

// Submit via Ajax — tidak redirect, supaya gesture user tetap hidup untuk
// auto-print Bluetooth (modal struk dibuka di halaman POS yang sama).
document.getElementById('formPos').addEventListener('submit', async function(e) {
    e.preventDefault();

    // Tahap 3 D'mentai — Nama/Telepon pelanggan sekarang OPSIONAL (walk-in
    // dine-in/takeaway/frozen tidak selalu punya data pelanggan). Field tetap
    // ada di form (dipertahankan sesuai instruksi), cuma tidak lagi wajib.
    const namaEl = document.getElementById('namaPelangganInput');
    const telpEl = document.getElementById('teleponPelangganInput');

    // 4.49 Tahap B2 — Kirim ke Cabang (Gudang → cabang sendiri): tanpa pembayaran —
    // kas cabang tujuan terpotong saat cabang menekan Terima. Alur bayar biasa di bawah tidak tersentuh.
    if (document.getElementById('selectCustomerType')?.value === 'internal') {
        await kirimKeCabang(e.target);
        return;
    }

    // 1. Tipe transaksi wajib dipilih
    const tipeTransaksi = document.getElementById('tipeTransaksiInput').value;
    if (!tipeTransaksi) {
        await window.showAlert('error', 'Tipe Transaksi Belum Dipilih', 'Pilih Dine-in / Takeaway / Frozen dulu.');
        return;
    }
    // 1b. Nomor meja wajib kalau dine-in & outlet mewajibkan
    if (tipeTransaksi === 'dine_in' && window.POS_NOMOR_MEJA_AKTIF) {
        const mejaEl = document.getElementById('inputNomorMeja');
        if (!mejaEl.value.trim()) {
            await window.showAlert('error', 'Nomor Meja Belum Diisi', 'Nomor meja wajib diisi untuk transaksi Dine-in.');
            mejaEl.focus();
            return;
        }
    }

    // 2. Item order minimal 1
    if (!document.querySelectorAll('.pos-item-row').length) {
        await window.showAlert('warning', 'Item Kosong', 'Harap tambahkan minimal 1 item!');
        return;
    }

    const tipe = document.getElementById('tipePembayaranInput').value;
    const isSplit = document.getElementById('splitPaymentCheck')?.checked;
    // Tahap B 4.38 — bukti wajib mengikuti saklar cabang (Transfer / QRIS),
    // termasuk split payment. Server juga mengecek aturan yang sama.
    const fileFoto = document.getElementById('buktiFotoInput').files;
    if (butuhBukti(metodePosAktif()) && (!fileFoto || fileFoto.length === 0)) {
        await window.showAlert('error', 'Bukti Pembayaran Wajib', 'Harap upload bukti pembayaran untuk metode Transfer/QRIS di outlet ini!');
        document.getElementById('sectionBuktiPembayaran').scrollIntoView({behavior:'smooth'});
        return;
    }
    if (tipe !== 'tunai' && !isSplit) {
        const totalText = document.getElementById('totalBayarDisplay').textContent.replace(/[^0-9]/g, '');
        document.getElementById('jumlahBayarInput').value = rupiahFmt(totalText);
    }

    // 3. Jumlah bayar (+ split kalau aktif) wajib >= total (pakai rupiahParse
    // — value input berformat titik ribuan, bukan angka murni)
    const total = parseInt(document.getElementById('totalBayarDisplay').textContent.replace(/[^0-9]/g, '')) || 0;
    const bayarUtama = tipe === 'tunai' ? rupiahParse(document.getElementById('jumlahBayarInput').value) : total;
    const bayarKedua = isSplit ? rupiahParse(document.getElementById('splitJumlah2')?.value || '0') : 0;
    const bayar = bayarUtama + bayarKedua;
    if (bayar < total) {
        const kurang = total - bayar;
        await window.showAlert('error', 'Pembayaran Kurang', `Jumlah bayar kurang Rp ${kurang.toLocaleString('id-ID')}.`);
        document.getElementById('jumlahBayarInput').focus();
        return;
    }

    // 6. Auto-detect order pengganti: cek ada order dibatalkan HARI INI dari
    // pelanggan yang sama & belum ada penggantinya. Kalau ada, tawarkan link
    // ke parent_order_id. Gagal cek (mis. network) TIDAK memblokir submit —
    // order tetap bisa diproses berdiri sendiri.
    document.getElementById('parentOrderIdInput').value = '';
    try {
        const params = new URLSearchParams({
            pelanggan_id: document.getElementById('pelangganSelect')?.value || '',
            nama_pelanggan: namaEl.value.trim(),
            telepon_pelanggan: telpEl.value.trim(),
        });
        const cekResp = await fetch(`{{ route('penjualan.cek-pengganti') }}?${params.toString()}`, {
            headers: { 'Accept': 'application/json' },
        });
        if (cekResp.ok) {
            const cekData = await cekResp.json();
            if (cekData.has_pengganti && cekData.orders.length) {
                const kandidat = cekData.orders[0];
                const penggantiResult = await window.showConfirm(
                    'Order Pengganti?',
                    `Ditemukan order dibatalkan hari ini dari pelanggan yang sama:<br>`
                        + `<strong>${kandidat.nomor_order}</strong> (${kandidat.jam}, Rp ${kandidat.total})<br><br>`
                        + `Order ini pengganti order tersebut?`,
                    { icon: 'question', confirmText: 'Ya, Pengganti', cancelText: 'Tidak, Baru' }
                );
                if (penggantiResult.isConfirmed) {
                    document.getElementById('parentOrderIdInput').value = kandidat.id;
                }
            }
        }
    } catch (err) {
        console.warn('Cek order pengganti gagal (dilanjutkan sebagai order baru):', err);
    }

    // Konfirmasi terakhir sebelum submit
    const kembalian = bayar - total;
    const confirmResult = await window.showConfirm(
        'Yakin Proses Order?',
        `<div style="text-align:left">`
            + `<div>Total &nbsp;&nbsp;&nbsp;: <strong>Rp ${total.toLocaleString('id-ID')}</strong></div>`
            + `<div>Bayar &nbsp;&nbsp;: <strong>Rp ${bayar.toLocaleString('id-ID')}</strong></div>`
            + `<div>Kembalian : <strong>Rp ${kembalian.toLocaleString('id-ID')}</strong></div>`
        + `</div>`,
        { icon: 'question', confirmText: 'Ya, Proses', cancelText: 'Cek Kembali' }
    );
    if (!confirmResult.isConfirmed) {
        return;
    }

    const formEl       = e.target;
    const submitBtn     = document.getElementById('btnProses');
    const originalHtml  = submitBtn.innerHTML;
    submitBtn.disabled  = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';

    try {
        const formData = new FormData(formEl);

        // Tahap 3 D'mentai — Split Payment: bungkus jadi array payments[]
        // (backend butuh ini, tipe_pembayaran/jumlah_bayar tetap dikirim
        // sebagai ringkasan tapi bukan lagi sumber kebenaran pembagian bayar).
        if (isSplit && bayarKedua > 0) {
            formData.append('payments[0][metode]', tipe);
            formData.append('payments[0][jumlah]', bayarUtama);
            formData.append('payments[1][metode]', document.getElementById('splitMetode2').value);
            formData.append('payments[1][jumlah]', bayarKedua);
        } else {
            formData.append('payments[0][metode]', tipe);
            formData.append('payments[0][jumlah]', bayar);
        }

        const response = await fetch(formEl.action, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Terjadi kesalahan saat memproses order.');
        }

        showToast('success', data.message || 'Order berhasil diproses!');

        if (data.auto_print) {
            // Masih dalam gesture klik "Proses" -> silent reconnect + cetak
            // Bluetooth bisa jalan tanpa popup ekstra (kecuali cache stale).
            // Reset form DITUNDA sampai user klik "Selesai" di modal —
            // lihat closeStrukModal() di _struk_modal_content.blade.php.
            openStrukModal(data.order_id, data.copies, data.tampil_harga_struk);
        } else {
            // Tidak ada modal yang perlu ditunggu -> reset sekarang.
            resetPosForm();
        }
    } catch (err) {
        await window.showAlert('error', 'Gagal Proses Order', err.message);
    } finally {
        submitBtn.disabled  = false;
        submitBtn.innerHTML = originalHtml;
    }
});

// ══ 4.49 Tahap B2 — Kirim ke Cabang (POS Gudang Pusat) ══
async function kirimKeCabang(formEl) {
    if (!document.querySelectorAll('.pos-item-row').length) {
        await window.showAlert('warning', 'Item Kosong', 'Harap tambahkan minimal 1 item!');
        return;
    }
    const tujuanEl = document.getElementById('selectOutletTujuan');
    if (!tujuanEl?.value) {
        await window.showAlert('error', 'Cabang Tujuan Belum Dipilih', 'Pilih cabang tujuan kiriman dulu.');
        tujuanEl?.focus();
        return;
    }
    const totalKirim = parseInt(document.getElementById('totalBayarDisplay').textContent.replace(/[^0-9]/g, '')) || 0;
    const namaTujuan = tujuanEl.options[tujuanEl.selectedIndex].text;
    const ok = await window.showConfirm(
        'Kirim ke ' + namaTujuan + '?',
        `<div style="text-align:left">`
            + `<div>Nilai kiriman: <strong>Rp ${totalKirim.toLocaleString('id-ID')}</strong></div>`
            + `<div class="small text-muted mt-2">Stok keluar dari gudang sekarang. Uang dipotong dari kas ${escHtml(namaTujuan)} saat cabang menekan Terima.</div>`
        + `</div>`,
        { icon: 'question', confirmText: 'Ya, Kirim', cancelText: 'Cek Kembali' }
    );
    if (!ok.isConfirmed) return;

    const btn = document.getElementById('btnProses');
    const htmlAsli = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengirim...';
    try {
        const fd = new FormData(formEl);
        fd.set('tipe_transaksi', 'takeaway');
        fd.set('tipe_pembayaran', 'tunai');
        fd.set('jumlah_bayar', String(totalKirim));
        fd.delete('purchase_order_id');
        fd.append('payments[0][metode]', 'tunai');
        fd.append('payments[0][jumlah]', String(Math.max(totalKirim, 1)));
        const resp = await fetch(formEl.action, {
            method: 'POST', body: fd,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.message || 'Gagal mengirim ke cabang.');
        showToast('success', data.message || 'Kiriman tercatat.');
        if (data.auto_print) {
            openStrukModal(data.order_id, data.copies, data.tampil_harga_struk);
        } else {
            resetPosForm();
        }
    } catch (err) {
        await window.showAlert('error', 'Gagal Kirim ke Cabang', err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = htmlAsli;
    }
}

(function () {
    const sel = document.getElementById('selectCustomerType');
    if (!sel) return; // hanya ada di POS Gudang Pusat
    sel.addEventListener('change', function () {
        const kirim = this.value === 'internal';
        document.getElementById('wrapOutletTujuan')?.classList.toggle('d-none', !kirim);
        if (!kirim) document.getElementById('selectOutletTujuan').value = '';
        // Tanpa pembayaran & tanpa Save Bill (uang pindah saat cabang menekan Terima)
        document.getElementById('wrapPembayaran')?.classList.toggle('d-none', kirim);
        document.getElementById('infoKirimCabang')?.classList.toggle('d-none', !kirim);
        document.getElementById('btnSaveBill')?.classList.toggle('d-none', kirim);
        document.getElementById('tampilAntrianCheck')?.closest('.form-check')?.classList.toggle('d-none', kirim);
        const btn = document.getElementById('btnProses');
        if (btn) btn.innerHTML = kirim
            ? '<i class="bi bi-truck me-2"></i>KIRIM KE CABANG'
            : '<i class="bi bi-check-circle-fill me-2"></i>PROSES ORDER';
        const barBayar = document.getElementById('posBarBayar');
        if (barBayar) barBayar.textContent = kirim ? 'Kirim ▸' : 'Bayar ▸';
    });
})();

// Reset seluruh state POS untuk transaksi berikutnya
function resetPosForm() {
    // Cart item
    document.getElementById('itemContainer').innerHTML = '';
    showEmptyMsg(true);
    batalPakaiPo(); // Tahap C 4.38

    // Pelanggan
    setPelangganSelectValue('');
    document.getElementById('namaPelangganInput').value = '';
    document.getElementById('teleponPelangganInput').value = '';
    const catatanInput = document.querySelector('#formPos [name="catatan"]');
    if (catatanInput) catatanInput.value = '';

    // Diskon + total (recompute setelah cart kosong)
    document.getElementById('diskonInput').value = '0';
    hitungTotal();

    // Tipe pembayaran -> tunai (juga reset kas manual + kas default +
    // visibility section bukti/jumlah bayar/kembalian)
    setTipePembayaran('tunai');

    // Jumlah bayar & kembalian (setTipePembayaran tidak menyentuh ini utk tunai)
    document.getElementById('jumlahBayarInput').value = '0';
    hitungKembalian();

    // Bukti pembayaran
    hapusBukti();

    // Tampil di Antrian & order pengganti — reset ke default per transaksi
    // (beda dgn auto-print/salinan yg persist sbg preferensi kasir)
    const tampilAntrianEl = document.getElementById('tampilAntrianCheck');
    if (tampilAntrianEl) tampilAntrianEl.checked = true;
    const parentOrderEl = document.getElementById('parentOrderIdInput');
    if (parentOrderEl) parentOrderEl.value = '';

    // NOTE: checkbox "Cetak Struk Otomatis" & salinan struk SENGAJA
    // tidak direset di sini — preferensi kasir persist via localStorage
    // (lihat initCopies() di bawah), bukan per-transaksi.

    // Siap untuk transaksi berikutnya
    focusPelangganSelect();
}
window.resetPosForm = resetPosForm;

// ===== KAS DEFAULT (auto-sync saldo) =====
const kasListData = @json($kasList->map(fn($k) => ['id' => $k->id, 'nama_kas' => $k->nama_kas, 'default_untuk' => $k->default_untuk]));

function updateKasDefault(tipe) {
    const kasInput = document.getElementById('kasIdInput');
    const kasManual = document.getElementById('kasManualSelect');
    // Jika ada override manual, biarkan
    if (kasManual && kasManual.value) return;

    const info = document.getElementById('kasDefaultInfo');
    const def = kasListData.find(k => k.default_untuk === tipe);
    if (def) {
        kasInput.value = def.id;
        info.innerHTML = `<small class="text-muted"><i class="bi bi-wallet2 me-1 text-success"></i>Kas: <strong>${def.nama_kas}</strong></small>`;
    } else {
        kasInput.value = '';
        const label = {'tunai':'Tunai','transfer':'Transfer','qris':'QRIS'}[tipe] || tipe;
        info.innerHTML = kasListData.length
            ? `<small class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Belum ada kas default untuk ${label}.</small>`
            : '';
    }
}

function onKasManual() {
    const sel = document.getElementById('kasManualSelect');
    const kasInput = document.getElementById('kasIdInput');
    const info = document.getElementById('kasDefaultInfo');
    if (sel && sel.value) {
        kasInput.value = sel.value;
        const nama = sel.options[sel.selectedIndex]?.dataset.nama || '';
        info.innerHTML = `<small class="text-info"><i class="bi bi-wallet2 me-1"></i>Kas manual: <strong>${nama}</strong></small>`;
    } else {
        updateKasDefault(document.getElementById('tipePembayaranInput').value);
    }
}

// Init saat load
updateKasDefault(document.getElementById('tipePembayaranInput').value);

// ===== SALINAN STRUK =====
// Dropdown muncul hanya saat auto_print_struk dicentang
function updateSalinanVisibility() {
    var checked = document.getElementById('autoPrintCheck').checked;
    var section = document.getElementById('sectionSalinan');
    if (section) section.style.display = checked ? '' : 'none';
}

function onCopiesChange(val) {
    try { localStorage.setItem('pos_print_copies', val); } catch(e) {}
}

// Restore preferensi kasir dari localStorage (persist lintas
// refresh/logout — bukan direset per transaksi, lihat resetPosForm()).
(function initCopies() {
    var chk = document.getElementById('autoPrintCheck');
    if (chk) {
        var savedAutoPrint = false;
        try { savedAutoPrint = localStorage.getItem('pos_auto_print_struk') === 'true'; } catch(e) {}
        chk.checked = savedAutoPrint;
        chk.addEventListener('change', function () {
            try { localStorage.setItem('pos_auto_print_struk', chk.checked); } catch(e) {}
            updateSalinanVisibility();
        });
    }

    var saved = '1';
    try { saved = localStorage.getItem('pos_print_copies') || '1'; } catch(e) {}
    var sel = document.getElementById('printCopies');
    if (sel) {
        sel.value = (saved === '2') ? '2' : '1';
    }

    updateSalinanVisibility();
})();
</script>

<script>
// ==== Cek Antrian Modal ====
const CEK_ANTRIAN_URL = '{{ route('antrian.cek.search') }}';
const CEK_ANTRIAN_CSRF = '{{ csrf_token() }}';
let cekTimer = null;

document.getElementById('cekAntrianInput')?.addEventListener('input', function() {
    clearTimeout(cekTimer);
    cekTimer = setTimeout(cekAntrianCari, 400);
});
document.getElementById('cekAntrianInput')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') cekAntrianCari();
});
document.getElementById('modalCekAntrian')?.addEventListener('shown.bs.modal', function() {
    document.getElementById('cekAntrianInput')?.focus();
    cekAntrianCari();
});

async function cekAntrianCari() {
    const q = (document.getElementById('cekAntrianInput')?.value || '').trim();
    const resultsEl = document.getElementById('cekAntrianResults');
    if (!resultsEl) return;

    resultsEl.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Mencari...</div>';

    try {
        const url = CEK_ANTRIAN_URL + '?q=' + encodeURIComponent(q) + '&tanggal=' + encodeURIComponent(new Date().toISOString().slice(0, 10));
        const resp = await fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CEK_ANTRIAN_CSRF } });
        const data = await resp.json();
        if (!data || data.length === 0) {
            resultsEl.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-1"></i>Tidak ditemukan.</div>';
            return;
        }
        const statusCls = { menunggu:'text-secondary', dikerjakan:'text-warning', selesai:'text-success', disimpan:'text-info', diambil:'text-muted' };
        const statusBadgeCls = { menunggu:'bg-secondary', dikerjakan:'bg-warning text-dark', selesai:'bg-success', disimpan:'bg-info text-dark', diambil:'bg-light text-muted border' };
        const html = data.map(o => {
            const parts = [];
            if (o.berat)        parts.push(`<i class="bi bi-boxes me-1"></i>${escHtml(o.berat)}`);
            if (o.lokasi_rak)   parts.push(`<i class="bi bi-archive me-1"></i>Rak: <strong>${escHtml(o.lokasi_rak)}</strong>`);
            if (o.operator)     parts.push(`<i class="bi bi-person me-1"></i>${escHtml(o.operator)}`);
            if (o.selesai)      parts.push(`<i class="bi bi-check-circle me-1"></i>Selesai: ${escHtml(o.selesai)}`);
            if (o.disimpan_jam) parts.push(`<i class="bi bi-archive me-1"></i>Disimpan: ${escHtml(o.disimpan_jam)}`);
            return `<div class="d-flex align-items-center gap-2 py-2 border-bottom">
                <div class="fw-black ${statusCls[o.status] || 'text-muted'}" style="font-size:1.5rem;font-weight:900;min-width:48px;text-align:center">${escHtml(o.nomor_antrian)}</div>
                <div class="flex-fill">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="fw-semibold">${escHtml(o.nama_pelanggan)}</span>
                        <span class="badge ${statusBadgeCls[o.status] || ''}">${escHtml(o.status_label || o.status)}</span>
                    </div>
                    <div class="small text-muted d-flex flex-wrap gap-2">${parts.join('')}</div>
                </div>
            </div>`;
        }).join('');
        resultsEl.innerHTML = `<div class="px-1">${html}</div>`;
    } catch (e) {
        resultsEl.innerHTML = '<div class="alert alert-danger mb-0">Gagal memuat data.</div>';
    }
}
function escHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<script>
// ── Mode Tampilan (Desktop / Tablet) ─────────────────────────
function setModeTampilan(mode) { _applyMode(mode, true); }

function _applyMode(mode, save) {
    var isTablet = (mode === 'tablet');
    document.body.classList.toggle('pos-tablet-mode', isTablet);
    var dBtn = document.getElementById('btnModeDesktop');
    var tBtn = document.getElementById('btnModeTablet');
    if (dBtn) dBtn.classList.toggle('active', !isTablet);
    if (tBtn) tBtn.classList.toggle('active',  isTablet);
    if (save) {
        try { localStorage.setItem('pos_mode_tampilan', mode); } catch(e) {}
    }
}

(function() {
    var m = 'desktop';
    try { m = localStorage.getItem('pos_mode_tampilan') || 'desktop'; } catch(e) {}
    _applyMode(m, false);
})();

// 4.47 — Total di bagian bawah panel bayar (mode Tablet) ikut angka TOTAL BAYAR.
(function() {
    var sumber = document.getElementById('totalBayarDisplay');
    var salin  = document.getElementById('kakiTotalNilai');
    if (!sumber || !salin) return;
    var ikut = function() { salin.textContent = sumber.textContent; };
    ikut();
    if (window.MutationObserver) {
        new MutationObserver(ikut).observe(sumber, { childList: true, characterData: true, subtree: true });
    }
})();
</script>

<script>
// ==== Buka Laci Kasir Manual (ESC/POS via Web Bluetooth) ====
const LACI_URL  = '{{ route('kas.log-buka-laci') }}';
const LACI_CSRF = '{{ csrf_token() }}';
const LACI_SERVICE_UUIDS = [
    '0000ae30-0000-1000-8000-00805f9b34fb',
    '0000ae3a-0000-1000-8000-00805f9b34fb',
    '000018f0-0000-1000-8000-00805f9b34fb',
    '49535343-fe7d-4ae5-8fa9-9fafd205e455',
    '0000ff00-0000-1000-8000-00805f9b34fb',
    '0000fee7-0000-1000-8000-00805f9b34fb',
    'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
    '6e400001-b5a3-f393-e0a9-e50e24dcca9e',
];
const LACI_OPEN_DRAWER = new Uint8Array([0x1B, 0x70, 0x00, 0x19, 0xFA]);

async function laciFindWritableChar(service) {
    const chars = await service.getCharacteristics();
    for (const chr of chars) {
        if (chr.properties.writeWithoutResponse || chr.properties.write) return chr;
    }
    return null;
}

async function bukaLaciManual() {
    const alasan = prompt('Alasan buka laci?', 'Ambil kembalian');
    if (!alasan) return;

    // 4.44 Poin 1 — aplikasi kasir Android: buka laci lewat printer struk yang diatur di aplikasi
    if (window.KDApk && window.KDApk.ada()) {
        try {
            await window.KDApk.bukaLaci();
            await fetch(LACI_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': LACI_CSRF },
                body: JSON.stringify({ alasan }),
            });
            await window.showAlert('success', 'Berhasil', 'Laci berhasil dibuka.');
        } catch (e) {
            await window.showAlert('error', 'Gagal Buka Laci', e.message);
        }
        return;
    }

    if (!navigator.bluetooth) {
        await window.showAlert('error', 'Bluetooth Tidak Didukung', 'Browser tidak mendukung Web Bluetooth. Gunakan Chrome atau Edge.');
        return;
    }

    let success = false;
    let server  = null;
    try {
        // Coba silent-reconnect ke printer yang sudah pernah dipair
        let device = null;
        const savedName = localStorage.getItem('btLastPrinterName');
        if (savedName && typeof navigator.bluetooth.getDevices === 'function') {
            try {
                const devices = await navigator.bluetooth.getDevices();
                device = devices.find(d => d.name === savedName) || null;
            } catch (e) { /* getDevices() gagal, fallback ke popup */ }
        }

        let hasTriedFresh = false;

        // Attempt 0: pakai device dari silent reconnect (kalau ada). Kalau
        // connect gagal (device stale/di luar jangkauan), otomatis fallback
        // ke requestDevice() di attempt 1 — tanpa nampilin error dulu.
        for (let attempt = 0; attempt < 2; attempt++) {
            if (!device) {
                try {
                    device = await navigator.bluetooth.requestDevice({
                        acceptAllDevices: true,
                        optionalServices: LACI_SERVICE_UUIDS,
                    });
                    hasTriedFresh = true;
                } catch (e) {
                    throw new Error('Pemilihan printer dibatalkan.');
                }
            }

            if (device.name && !localStorage.getItem('btLastPrinterName')) {
                localStorage.setItem('btLastPrinterName', device.name);
            }

            try {
                server = await device.gatt.connect();
                break;
            } catch (e) {
                console.log('Connect attempt ' + (attempt + 1) + ' gagal:', e.message);
                if (attempt === 0 && !hasTriedFresh) {
                    device = null;
                    continue;
                }
                throw e;
            }
        }

        let writeChar = null;
        for (const uuid of LACI_SERVICE_UUIDS) {
            try {
                const svc = await server.getPrimaryService(uuid);
                writeChar = await laciFindWritableChar(svc);
                if (writeChar) break;
            } catch (e) { /* service tidak ada di printer ini, lanjut */ }
        }
        if (!writeChar) throw new Error('Service printer tidak ditemukan di perangkat ini.');

        try {
            if (writeChar.properties.writeWithoutResponse) {
                await writeChar.writeValueWithoutResponse(LACI_OPEN_DRAWER);
            } else {
                await writeChar.writeValueWithResponse(LACI_OPEN_DRAWER);
            }
        } catch (_) {
            await writeChar.writeValue(LACI_OPEN_DRAWER);
        }

        await fetch(LACI_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': LACI_CSRF,
            },
            body: JSON.stringify({ alasan }),
        });

        success = true;
    } catch (e) {
        await window.showAlert('error', 'Gagal Buka Laci', e.message);
    } finally {
        // Delay untuk flush buffer printer sebelum disconnect
        await new Promise(resolve => setTimeout(resolve, 200));

        if (server && server.connected) {
            try {
                server.disconnect();
            } catch (e) {
                console.log('Disconnect error (ignored):', e);
            }
        }
    }

    if (success) {
        await window.showAlert('success', 'Berhasil', 'Laci berhasil dibuka.');
    }
}
// ══ 4.49 Tahap C — Tampilan HP (≤ 575px): bilah keranjang melayang + kartu Pelanggan dilipat ══
// Porting erp-coffeeshop 512ccb2. Hanya MEMBACA keranjang & total yang sudah ada (MutationObserver) —
// alur POS tidak diubah. Jumlah = total qty (produk sama digabung jadi qty sejak 4.47).
(function () {
    const bar = document.getElementById('posBarHp');
    if (!bar) return;
    document.body.classList.add('pos-ada-bar');
    const isHp = () => window.matchMedia('(max-width: 575.98px)').matches;
    const ke = id => {
        const el = document.getElementById(id);
        if (!el) return;
        const atas = el.getBoundingClientRect().top + window.scrollY - 64;
        window.scrollTo({ top: Math.max(0, atas), behavior: 'smooth' });
    };
    document.getElementById('posBarKeranjang')?.addEventListener('click', () => ke('cardItemOrder'));
    document.getElementById('posBarBayar')?.addEventListener('click', () => ke('panelBayar'));

    const kartuPelanggan = document.getElementById('cardPelanggan');
    kartuPelanggan?.querySelector('.card-header')?.addEventListener('click', () => {
        if (isHp()) kartuPelanggan.classList.toggle('hp-buka');
    });

    let jmlLama = 0;
    function perbarui() {
        let jml = 0;
        document.querySelectorAll('#itemContainer .pos-item-row .qty-input').forEach(q => { jml += parseFloat(q.value) || 0; });
        jml = Math.round(jml * 100) / 100;
        document.getElementById('posBarJml').textContent = jml ? jml.toLocaleString('id-ID') + ' item di keranjang' : 'Keranjang kosong';
        document.getElementById('posBarTotal').textContent = document.getElementById('totalBayarDisplay')?.textContent || 'Rp 0';
        bar.classList.toggle('kosong', jml === 0);
        if (jml > jmlLama) { bar.classList.remove('denyut'); void bar.offsetWidth; bar.classList.add('denyut'); }
        jmlLama = jml;
    }
    const amati = new MutationObserver(perbarui);
    ['itemContainer', 'totalBayarDisplay'].forEach(id => {
        const el = document.getElementById(id);
        if (el) amati.observe(el, { childList: true, subtree: true, characterData: true });
    });
    document.getElementById('itemContainer')?.addEventListener('input', perbarui);
    perbarui();
})();
</script>
@endpush
@endsection
