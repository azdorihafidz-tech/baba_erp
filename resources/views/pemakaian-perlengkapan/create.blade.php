@extends('layouts.app')

@section('title', 'Catat Pemakaian Perlengkapan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-box-arrow-up me-2 text-primary"></i>Catat Pemakaian Perlengkapan
    </h5>
    <a href="{{ route('pemakaian-perlengkapan.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header">Form Pemakaian</div>
            <div class="card-body">

                @if($items->isEmpty())
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Belum ada item berjenis <strong>Perlengkapan</strong> dengan Lacak Stok aktif.
                    Tambah dulu lewat menu <a href="{{ route('master.bahan-baku.create') }}">Bahan Baku &amp; Kemasan</a> (pilih Jenis = Perlengkapan).
                </div>
                @else
                <form method="POST" action="{{ route('pemakaian-perlengkapan.store') }}">
                    @csrf

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Item <span class="text-danger">*</span></label>
                            <select name="item_id" class="form-select @error('item_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Item Perlengkapan --</option>
                                @foreach($items as $item)
                                <option value="{{ $item->id }}" {{ old('item_id') == $item->id ? 'selected' : '' }}>
                                    {{ $item->nama_dengan_cabang }} ({{ $item->satuan }})
                                </option>
                                @endforeach
                            </select>
                            @error('item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Cabang <span class="text-danger">*</span></label>
                            <select name="cabang_id" class="form-select @error('cabang_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Cabang --</option>
                                @foreach($lokasiList as $lokasi)
                                <option value="{{ $lokasi->id }}" {{ old('cabang_id', session('active_cabang_id')) == $lokasi->id ? 'selected' : '' }}>
                                    {{ $lokasi->nama_cabang }}
                                </option>
                                @endforeach
                            </select>
                            @error('cabang_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Qty Dipakai <span class="text-danger">*</span></label>
                            <input type="number" name="qty" class="form-control @error('qty') is-invalid @enderror"
                                min="0.001" step="0.001" value="{{ old('qty') }}" required>
                            @error('qty')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Tanggal Pemakaian <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_pemakaian" class="form-control @error('tanggal_pemakaian') is-invalid @enderror"
                                value="{{ old('tanggal_pemakaian', date('Y-m-d')) }}" required>
                            @error('tanggal_pemakaian')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Keterangan</label>
                            <textarea name="keterangan" class="form-control @error('keterangan') is-invalid @enderror" rows="2"
                                placeholder="cth: Dipakai untuk stock opname cabang Tembung">{{ old('keterangan') }}</textarea>
                            @error('keterangan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>Simpan Pemakaian
                            </button>
                            <a href="{{ route('pemakaian-perlengkapan.index') }}" class="btn btn-outline-secondary ms-2">Batal</a>
                        </div>
                    </div>
                </form>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection
