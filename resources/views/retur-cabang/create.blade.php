@extends('layouts.app')

@section('title', 'Buat Retur')

@section('content')
@php $angka = fn ($n) => \App\Services\ProduksiService::angka((float) $n); @endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-return-left me-2 text-success"></i>Retur ke {{ $order->cabang?->nama_cabang }}
        <span class="text-muted fw-normal small">— kiriman {{ $order->nomor_order }}</span></h5>
    <x-panduan-button slug="retur-cabang" />
</div>

<form method="POST" action="{{ route('retur-cabang.store', $order) }}">
    @csrf
    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header bg-white small fw-semibold">Barang yang dikembalikan</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Barang</th><th class="text-end">Diterima</th><th class="text-end">Sudah diretur</th><th style="width:150px">Jumlah retur</th><th class="text-end">Harga / unit</th></tr></thead>
                        <tbody>
                        @foreach($order->kirimanItems as $r)
                        @php $sisa = \App\Services\ReturInternalService::sisaBolehRetur($r); @endphp
                        <tr class="{{ $sisa <= 0 ? 'text-muted' : '' }}">
                            <td>{{ ($r->itemTujuan ?? $r->item)?->nama_item }}<div class="small text-muted">{{ $r->orderItem?->nama_item }}</div></td>
                            <td class="text-end">{{ $angka($r->qty_terima) }} {{ ($r->itemTujuan ?? $r->item)?->satuan }}</td>
                            <td class="text-end">{{ $angka($r->qty_retur) }}</td>
                            <td><input type="number" step="0.001" min="0" max="{{ $sisa }}" name="qty[{{ $r->id }}]" value="{{ old('qty.' . $r->id) }}" class="form-control form-control-sm text-end" @disabled($sisa <= 0) placeholder="maks {{ $angka($sisa) }}"></td>
                            <td class="text-end">Rp {{ number_format($r->harga_terima_per_unit, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card"><div class="card-body">
                <label class="form-label small fw-semibold">Alasan *</label>
                <select name="alasan" class="form-select form-select-sm mb-3" required>
                    @foreach(\App\Models\ReturInternal::ALASAN as $k => $v)
                    <option value="{{ $k }}" @selected(old('alasan') === $k)>{{ $v }}</option>
                    @endforeach
                </select>

                <label class="form-label small fw-semibold">Uang dikembalikan? * <x-tooltip key="retur_cabang.uang_kembali" /></label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="uang_kembali" id="uangYa" value="1" @checked(old('uang_kembali', '1') === '1')>
                    <label class="form-check-label small" for="uangYa">Ya — kas cabang dapat uang kembali, rugi ditanggung {{ $order->cabang?->nama_cabang }}</label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="uang_kembali" id="uangTidak" value="0" @checked(old('uang_kembali') === '0')>
                    <label class="form-check-label small" for="uangTidak">Tidak — barang dikembalikan saja, rugi ditanggung cabang ini</label>
                </div>

                <label class="form-label small fw-semibold">Catatan</label>
                <textarea name="catatan" rows="2" maxlength="500" class="form-control form-control-sm mb-3" placeholder="Mis. 2 kg isian basi">{{ old('catatan') }}</textarea>

                <div class="small text-muted mb-3">Stok cabang langsung berkurang saat retur dikirim. Uang (kalau dikembalikan) berpindah saat {{ $order->cabang?->nama_cabang }} menekan Terima.</div>
                <button class="btn btn-primary w-100" onclick="return confirm('Kirim retur ini?')"><i class="bi bi-send me-1"></i>Kirim Retur</button>
                <a href="{{ route('kiriman.show', $order) }}" class="btn btn-outline-secondary w-100 mt-2">Batal</a>
            </div></div>
        </div>
    </div>
</form>
@endsection
