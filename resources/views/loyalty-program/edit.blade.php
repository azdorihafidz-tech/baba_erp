@extends('layouts.app')

@section('title', 'Edit Program Loyalty')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold"><i class="bi bi-award me-2 text-primary"></i>Edit Program Loyalty</h4>
    <a href="{{ route('loyalty-program.show', $loyaltyProgram) }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('loyalty-program.update', $loyaltyProgram) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Tipe Program <span class="text-danger">*</span></label>
                    <select name="tipe_program" id="tipeProgram" class="form-select" required>
                        <option value="auto_track" {{ old('tipe_program',$loyaltyProgram->tipe_program)==='auto_track'?'selected':'' }}>Auto-Track (kumulatif otomatis dari transaksi pelanggan)</option>
                        <option value="event_based" {{ old('tipe_program',$loyaltyProgram->tipe_program)==='event_based'?'selected':'' }}>Event-Based (klaim manual + bukti, 1x per pelanggan)</option>
                    </select>
                    <small class="text-muted">Auto-Track: progress dihitung sistem otomatis dari transaksi POS pelanggan (default: Total Belanja Rp — bisa diganti ke Jumlah Transaksi di "Basis Perhitungan" di bawah). Event-Based: pelanggan klaim manual (mis. post di sosmed), Owner approve/reject.</small>
                </div>

                <div class="col-12 col-md-8">
                    <label class="form-label">Nama Program <span class="text-danger">*</span></label>
                    <input type="text" name="nama" class="form-control" value="{{ old('nama', $loyaltyProgram->nama) }}" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="aktif" {{ old('status',$loyaltyProgram->status)==='aktif'?'selected':'' }}>Aktif</option>
                        <option value="nonaktif" {{ old('status',$loyaltyProgram->status)==='nonaktif'?'selected':'' }}>Nonaktif</option>
                    </select>
                </div>

                <div class="col-6 col-md-2 field-auto-track">
                    <label class="form-label">Target ({{ $loyaltyProgram->satuan_qty }}) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="target_qty_kg" id="targetQtyKg" class="form-control" value="{{ old('target_qty_kg', $loyaltyProgram->target_qty_kg) }}">
                </div>

                <div class="col-12">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" class="form-control" rows="2">{{ old('deskripsi', $loyaltyProgram->deskripsi) }}</textarea>
                </div>

                <div class="col-12">
                    <label class="form-label">Hadiah <span class="text-danger">*</span></label>
                    <textarea name="hadiah" class="form-control" rows="2" required>{{ old('hadiah', $loyaltyProgram->hadiah) }}</textarea>
                </div>

                <div class="col-12 col-md-4 field-event-based">
                    <label class="form-label">Nominal Voucher Default (Rp)</label>
                    <input type="number" step="1" min="0" name="nominal_voucher" class="form-control" value="{{ old('nominal_voucher', $loyaltyProgram->nominal_voucher) }}" placeholder="mis. 15000">
                    <small class="text-muted">Nilai default saat Owner approve klaim — bisa diubah manual per klaim saat approve.</small>
                </div>

                <div class="col-12 col-md-4 field-auto-track">
                    <label class="form-label">Tipe Order Dihitung</label>
                    <select name="tipe_item" class="form-select">
                        <option value="penjualan" {{ old('tipe_item',$loyaltyProgram->tipe_item)==='penjualan'?'selected':'' }}>Penjualan ({{ $brand->namaSingkat() }})</option>
                        <option value="jasa_giling" {{ old('tipe_item',$loyaltyProgram->tipe_item)==='jasa_giling'?'selected':'' }}>Jasa Giling (legacy)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4 field-auto-track">
                    <label class="form-label">Basis Perhitungan</label>
                    <select name="sumber_data" class="form-select">
                        <option value="orders.total_bayar" {{ old('sumber_data',$loyaltyProgram->sumber_data)==='orders.total_bayar'?'selected':'' }}>Total Belanja (Rp)</option>
                        <option value="orders.count" {{ old('sumber_data',$loyaltyProgram->sumber_data)==='orders.count'?'selected':'' }}>Jumlah Transaksi</option>
                        <option value="orders.berat_daging_kg" {{ old('sumber_data',$loyaltyProgram->sumber_data)==='orders.berat_daging_kg'?'selected':'' }}>Berat Gilingan (kg, legacy)</option>
                    </select>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label">Periode Mulai <span class="text-muted small">(kosongkan = all-time)</span></label>
                    <input type="date" name="periode_mulai" class="form-control" value="{{ old('periode_mulai', $loyaltyProgram->periode_mulai?->format('Y-m-d')) }}">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label">Periode Akhir</label>
                    <input type="date" name="periode_akhir" class="form-control" value="{{ old('periode_akhir', $loyaltyProgram->periode_akhir?->format('Y-m-d')) }}">
                </div>

                <div class="col-12 field-auto-track">
                    <div class="form-check">
                        <input type="checkbox" name="berulang" id="berulang" class="form-check-input" value="1" {{ old('berulang', $loyaltyProgram->berulang)?'checked':'' }}>
                        <label class="form-check-label" for="berulang">
                            Bisa berulang kali per pelanggan (mis. dapat hadiah lagi tiap kelipatan {{ number_format($loyaltyProgram->target_qty_kg,0,',','.') }} {{ $loyaltyProgram->satuan_qty }})
                        </label>
                    </div>
                </div>
            </div>

            <hr>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle me-1"></i>Simpan Perubahan
            </button>
            <a href="{{ route('loyalty-program.show', $loyaltyProgram) }}" class="btn btn-outline-secondary ms-2">Batal</a>
        </form>
    </div>
</div>

<script>
function toggleTipeProgramFields() {
    const tipe = document.getElementById('tipeProgram').value;
    const isAutoTrack = tipe === 'auto_track';
    document.querySelectorAll('.field-auto-track').forEach(el => el.style.display = isAutoTrack ? '' : 'none');
    document.querySelectorAll('.field-event-based').forEach(el => el.style.display = isAutoTrack ? 'none' : '');
    const targetInput = document.getElementById('targetQtyKg');
    if (targetInput) targetInput.required = isAutoTrack;
}
document.getElementById('tipeProgram').addEventListener('change', toggleTipeProgramFields);
document.addEventListener('DOMContentLoaded', toggleTipeProgramFields);
</script>
@endsection
