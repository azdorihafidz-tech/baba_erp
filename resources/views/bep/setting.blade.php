@extends('layouts.app')

@section('title', 'Setting BEP')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-gear me-2 text-primary"></i>Setting BEP</h5>
        <p class="text-muted mb-0 small">
            {{ $cabang?->nama_cabang ?? 'Pilih Cabang' }} — Periode: {{ $periode ? \Carbon\Carbon::parse($periode.'-01')->format('F Y') : '-' }}
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if($setting)
        {{-- Tombol Isi Otomatis --}}
        @can('bep.manage')
        <form method="POST" action="{{ route('bep.auto-fill', $setting) }}"
              onsubmit="return confirm('Isi otomatis biaya tetap & produk dari data sistem?\nData yang sudah ada dengan nama sama akan diperbarui.')">
            @csrf
            <button type="submit" class="btn btn-sm btn-warning">
                <i class="bi bi-magic me-1"></i>Isi Otomatis
            </button>
        </form>
        @endcan
        {{-- Bug7 FIX: arahkan ke laporan.bep (bukan bep.laporan yang sudah dihapus) --}}
        @can('laporan.bep.view')
        <a href="{{ route('laporan.bep', ['cabang_id' => $cabangId, 'periode' => $periode]) }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-graph-up me-1"></i>Lihat Laporan
        </a>
        @endcan
        @endif
        <a href="{{ route('bep.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

{{-- Bug4 FIX: Warning jika cabang yang dipilih = Head Office --}}
@if($cabang && $cabang->tipe?->value === 'head_office')
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
    <div>
        <strong>Head Office dipilih.</strong>
        BEP umumnya digunakan untuk menganalisis operasional cabang produksi, bukan kantor pusat.
        Setting BEP untuk HO <strong>tidak akan muncul</strong> di laporan perbandingan antar cabang.
    </div>
</div>
@endif

{{-- Pilih Cabang + Periode jika belum ada setting --}}
@if(!$setting)
@can('bep.manage')
<div class="card mb-4">
    <div class="card-header">Buat Setting BEP</div>
    <div class="card-body">
        <form method="POST" action="{{ route('bep.setting.store') }}">
            @csrf
            <div class="row g-3 align-items-end">
                @if($cabangs->count())
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Cabang <span class="text-danger">*</span></label>
                    <select name="cabang_id" class="form-select" required>
                        <option value="">-- Pilih Cabang --</option>
                        @foreach($cabangs as $c)
                        <option value="{{ $c->id }}" @selected($cabangId == $c->id)>{{ $c->nama_cabang }}</option>
                        @endforeach
                    </select>
                </div>
                @else
                <input type="hidden" name="cabang_id" value="{{ $cabangId }}">
                @endif
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Periode <span class="text-danger">*</span> <x-tooltip key="bep.periode" /></label>
                    <input type="month" name="periode" class="form-control" value="{{ $periode }}" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Catatan</label>
                    <input type="text" name="catatan" class="form-control" placeholder="Opsional...">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Buat Setting
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endcan
@can('bep.view')
@cannot('bep.manage')
<div class="alert alert-secondary">
    <i class="bi bi-info-circle me-1"></i>Belum ada setting BEP untuk cabang/periode ini.
</div>
@endcannot
@endcan
@else
<div class="row g-3">

    {{-- BIAYA TETAP --}}
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <span>Biaya Tetap (Fixed Cost)</span>
                <strong class="text-primary">Rp {{ number_format($setting->total_biaya_tetap, 0, ',', '.') }}</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Komponen</th><th>Kategori</th><th class="text-end">Jumlah</th><th></th></tr></thead>
                        <tbody>
                            @forelse($setting->fixedCostItems as $fc)
                            <tr>
                                <td>{{ $fc->nama_komponen }}</td>
                                <td><span class="badge bg-secondary">{{ $kategoriBiayaTetap[$fc->kategori] ?? $fc->kategori }}</span></td>
                                <td class="text-end">Rp {{ number_format($fc->jumlah, 0, ',', '.') }}</td>
                                <td>
                                    @can('bep.manage')
                                    <form method="POST" action="{{ route('bep.fixed-cost.delete', $fc) }}" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger" style="font-size:.7rem;padding:.15rem .4rem"
                                            onclick="return confirm('Hapus item ini?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-2">Belum ada komponen biaya tetap</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @can('bep.manage')
            <div class="card-footer">
                <form method="POST" action="{{ route('bep.fixed-cost.store', $setting) }}">
                    @csrf
                    <div class="row g-2">
                        <div class="col-12 col-sm-5">
                            <input type="text" name="nama_komponen" class="form-control form-control-sm" placeholder="Nama komponen..." required>
                        </div>
                        <div class="col-6 col-sm-3">
                            <select name="kategori" class="form-select form-select-sm" required>
                                @foreach($kategoriBiayaTetap as $val => $lbl)
                                <option value="{{ $val }}">{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-sm-3">
                            <x-input-rupiah name="jumlah" :value="0" size="sm" placeholder="Jumlah (Rp)" required />
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus-circle me-1"></i>Tambah Biaya Tetap
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            @endcan
        </div>
    </div>

    {{-- PRODUK / JASA --}}
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header">Produk / Jasa & BEP</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th class="text-end">BEP Unit</th>
                                <th class="text-end">BEP Rupiah</th>
                                <th class="text-end">Margin</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($setting->products as $p)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $p->nama_produk }}</div>
                                    <span class="badge bg-{{ $p->tipe === 'produk' ? 'primary' : 'info' }} badge-sm">{{ $p->tipe === 'produk' ? 'Produk' : 'Jasa Giling' }}</span>
                                    <div class="text-muted small">Jual: Rp {{ number_format($p->harga_jual_per_unit, 0, ',', '.') }} | Var: Rp {{ number_format($p->biaya_variabel_per_unit, 0, ',', '.') }}</div>
                                    @if($p->catatan)
                                    <small class="text-muted d-block" style="font-size:0.72rem;color:#94a3b8!important">
                                        <i class="bi bi-info-circle me-1"></i>{{ $p->catatan }}
                                    </small>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">{{ number_format($p->bep_unit ?? 0, 1, ',', '.') }}</td>
                                <td class="text-end text-primary">Rp {{ number_format($p->bep_rupiah ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end text-success">Rp {{ number_format($p->margin_kontribusi ?? 0, 0, ',', '.') }}</td>
                                <td>
                                    @can('bep.manage')
                                    <form method="POST" action="{{ route('bep.product.delete', $p) }}" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger" style="font-size:.7rem;padding:.15rem .4rem"
                                            onclick="return confirm('Hapus produk ini?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-2">Belum ada produk</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @can('bep.manage')
            <div class="card-footer">
                <form method="POST" action="{{ route('bep.product.store', $setting) }}">
                    @csrf
                    <div class="row g-2">
                        <div class="col-12 col-sm-6">
                            <input type="text" name="nama_produk" class="form-control form-control-sm" placeholder="Nama produk/jasa..." required>
                        </div>
                        <div class="col-6 col-sm-3">
                            <select name="tipe" class="form-select form-select-sm">
                                <option value="produk">Produk</option>
                                <option value="jasa_giling">Jasa Giling</option>
                            </select>
                        </div>
                        <div class="col-6 col-sm-3">
                            <input type="number" name="target_penjualan_unit" class="form-control form-control-sm" placeholder="Target unit" min="0" value="0">
                        </div>
                        <div class="col-6">
                            <x-input-rupiah name="harga_jual_per_unit" :value="0" size="sm" placeholder="Harga jual/unit (Rp)" required />
                        </div>
                        <div class="col-6">
                            <x-input-rupiah name="biaya_variabel_per_unit" :value="0" size="sm" placeholder="Biaya variabel/unit (Rp)" required />
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus-circle me-1"></i>Tambah Produk & Hitung BEP
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            @endcan
        </div>
    </div>

    {{-- Hitung Ulang --}}
    @can('bep.manage')
    <div class="col-12">
        <form method="POST" action="{{ route('bep.hitung-ulang', $setting) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-warning">
                <i class="bi bi-arrow-clockwise me-1"></i>Hitung Ulang Semua BEP
            </button>
        </form>
    </div>
    @endcan

</div>
@endif
@endsection
