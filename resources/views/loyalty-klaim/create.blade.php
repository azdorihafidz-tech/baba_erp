@extends('layouts.app')

@section('title', 'Buat Klaim Event Loyalty')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold"><i class="bi bi-megaphone me-2 text-primary"></i>Buat Klaim Event Loyalty</h4>
    <a href="{{ route('loyalty-klaim.index') }}" class="btn btn-outline-secondary btn-sm">
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

@if($programs->isEmpty())
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-1"></i>
    Belum ada program loyalty event-based yang aktif. Minta Owner/Admin Pusat buat programnya dulu di menu Program Loyalty.
</div>
@else
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('loyalty-klaim.store') }}" enctype="multipart/form-data">
            @csrf
            @if($orderId)
            <input type="hidden" name="order_id" value="{{ $orderId }}">
            @endif
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Program Loyalty <span class="text-danger">*</span></label>
                    <select name="loyalty_program_id" class="form-select" required>
                        <option value="">-- Pilih Program --</option>
                        @foreach($programs as $p)
                        <option value="{{ $p->id }}" {{ (old('loyalty_program_id', $selectedProgramId) == $p->id) ? 'selected' : '' }}>
                            {{ $p->nama }} @if($p->nominal_voucher) (voucher ~Rp {{ number_format($p->nominal_voucher,0,',','.') }}) @endif
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Pelanggan <span class="text-danger">*</span></label>
                    <select name="pelanggan_id" class="form-select" required>
                        <option value="">-- Pilih Pelanggan --</option>
                        @foreach($pelanggans as $p)
                        <option value="{{ $p->id }}" {{ (old('pelanggan_id', $selectedPelangganId) == $p->id) ? 'selected' : '' }}>
                            {{ $p->nama_pelanggan }} @if($p->telepon)({{ $p->telepon }})@endif [{{ $p->kode_pelanggan }}]
                        </option>
                        @endforeach
                    </select>
                    <small class="text-muted">1 pelanggan cuma bisa klaim 1x per program (kecuali klaim sebelumnya ditolak).</small>
                </div>

                <div class="col-12">
                    <label class="form-label">Link Bukti (post sosmed/website)</label>
                    <input type="url" name="bukti_link" class="form-control" placeholder="https://..." value="{{ old('bukti_link') }}">
                </div>
                <div class="col-12">
                    <div class="text-center text-muted small my-1">— atau —</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Upload Foto Bukti</label>
                    <input type="file" name="bukti_file" accept="image/*" class="form-control">
                    <small class="text-muted">Wajib isi salah satu: Link Bukti ATAU Upload Foto.</small>
                </div>

                <div class="col-12">
                    <label class="form-label">Catatan Tambahan</label>
                    <textarea name="bukti_catatan" class="form-control" rows="2" placeholder="mis. Username IG @nama_pelanggan, post tanggal sekian">{{ old('bukti_catatan') }}</textarea>
                </div>
            </div>

            <hr>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-send-check me-1"></i>Ajukan Klaim
            </button>
            <a href="{{ route('loyalty-klaim.index') }}" class="btn btn-outline-secondary ms-2">Batal</a>
        </form>
    </div>
</div>
@endif
@endsection
