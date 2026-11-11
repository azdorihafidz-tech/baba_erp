@extends('layouts.app')

@section('title', 'Transfer Antar Kas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-arrow-down-up me-2 text-primary"></i>Transfer Antar Kas
    </h5>
    <a href="{{ route('transfer-antar-kas.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    {!! session('error') !!}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row justify-content-center g-4">
    <div class="col-12 col-xl-7">
        <div class="card">
            <div class="card-header bg-light">
                <i class="bi bi-info-circle me-1 text-info"></i>
                Mutasi dana <strong>dalam 1 cabang</strong> — saldo kedua Kas langsung terpengaruh saat disimpan
                (tidak perlu konfirmasi/approval, beda dari <em>Transfer/Perpindahan Dana</em> antar cabang).
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('transfer-antar-kas.store') }}" id="formTransferAntarKas">
                    @csrf
                    <div class="row g-3">

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal"
                                class="form-control @error('tanggal') is-invalid @enderror"
                                value="{{ old('tanggal', date('Y-m-d')) }}" required>
                            @error('tanggal')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <x-input-rupiah name="jumlah" label="Jumlah Transfer" :value="old('jumlah', 0)" required />
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Kas Asal <span class="text-danger">*</span></label>
                            <select name="kas_asal_id" id="kasAsalSelect"
                                class="form-select @error('kas_asal_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Kas Asal --</option>
                                @foreach($kasList as $kas)
                                <option value="{{ $kas->id }}" @selected(old('kas_asal_id') == $kas->id)>
                                    {{ $kas->nama_kas }} — Saldo: Rp {{ number_format($kas->saldo_sekarang, 0, ',', '.') }}
                                </option>
                                @endforeach
                            </select>
                            @error('kas_asal_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Kas Tujuan <span class="text-danger">*</span></label>
                            <select name="kas_tujuan_id" id="kasTujuanSelect"
                                class="form-select @error('kas_tujuan_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Kas Tujuan --</option>
                                @foreach($kasList as $kas)
                                <option value="{{ $kas->id }}" @selected(old('kas_tujuan_id') == $kas->id)>
                                    {{ $kas->nama_kas }} — Saldo: Rp {{ number_format($kas->saldo_sekarang, 0, ',', '.') }}
                                </option>
                                @endforeach
                            </select>
                            @error('kas_tujuan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small class="text-muted" id="kasSamaWarning" style="display:none">
                                <i class="bi bi-exclamation-triangle text-warning"></i> Kas asal dan tujuan tidak boleh sama.
                            </small>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Keterangan</label>
                            <input type="text" name="keterangan"
                                class="form-control @error('keterangan') is-invalid @enderror"
                                value="{{ old('keterangan') }}" placeholder="mis. Setor tunai ke rekening bank..." maxlength="255">
                            @error('keterangan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-primary" id="btnSubmitTransfer">
                                <i class="bi bi-arrow-down-up me-1"></i>Proses Transfer
                            </button>
                            <a href="{{ route('transfer-antar-kas.index') }}" class="btn btn-outline-secondary ms-2">Batal</a>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function cekKasSama() {
    const asal   = document.getElementById('kasAsalSelect').value;
    const tujuan = document.getElementById('kasTujuanSelect').value;
    const warning = document.getElementById('kasSamaWarning');
    const btn     = document.getElementById('btnSubmitTransfer');
    const sama = asal && tujuan && asal === tujuan;
    warning.style.display = sama ? 'block' : 'none';
    btn.disabled = !!sama;
}
document.getElementById('kasAsalSelect').addEventListener('change', cekKasSama);
document.getElementById('kasTujuanSelect').addEventListener('change', cekKasSama);
cekKasSama();
</script>
@endpush
