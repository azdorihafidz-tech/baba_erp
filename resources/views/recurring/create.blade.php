@extends('layouts.app')
@section('title', 'Tambah Transaksi Berulang')
@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('recurring.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h4 class="fw-bold mb-0">Tambah Template Transaksi Berulang</h4>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('recurring.store') }}" method="POST" id="formRecurring">
            @csrf
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label">Nama Template <span class="text-danger">*</span></label>
                    <input type="text" name="nama_template" class="form-control @error('nama_template') is-invalid @enderror"
                           value="{{ old('nama_template') }}" placeholder="Contoh: Gaji Pokok, Sewa Tempat" required>
                    @error('nama_template')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label">Tipe <span class="text-danger">*</span> <x-tooltip key="recurring.tipe" /></label>
                    <select name="tipe" id="tipe" class="form-select @error('tipe') is-invalid @enderror" required onchange="loadKategori(this.value)">
                        <option value="">— Pilih —</option>
                        <option value="pemasukan"   @selected(old('tipe')==='pemasukan')>Pemasukan</option>
                        <option value="pengeluaran" @selected(old('tipe')==='pengeluaran')>Pengeluaran</option>
                    </select>
                    @error('tipe')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label">Frekuensi <span class="text-danger">*</span> <x-tooltip key="recurring.frekuensi" /></label>
                    <select name="frekuensi" class="form-select @error('frekuensi') is-invalid @enderror" required>
                        <option value="bulanan" @selected(old('frekuensi','bulanan')==='bulanan')>Bulanan</option>
                        <option value="mingguan" @selected(old('frekuensi')==='mingguan')>Mingguan</option>
                    </select>
                    @error('frekuensi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label">Jumlah (Rp) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="text" id="display_jumlah" class="form-control" placeholder="0" inputmode="numeric">
                        <input type="hidden" name="jumlah" id="raw_jumlah" value="{{ old('jumlah') }}">
                    </div>
                    @error('jumlah')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label">Tanggal Jatuh Tempo <span class="text-danger">*</span> <x-tooltip key="recurring.tanggal_jatuh_tempo" /></label>
                    <div class="input-group">
                        <span class="input-group-text">Tgl</span>
                        <input type="number" name="tanggal_jatuh_tempo" class="form-control @error('tanggal_jatuh_tempo') is-invalid @enderror"
                               value="{{ old('tanggal_jatuh_tempo', 1) }}" min="1" max="28" required>
                    </div>
                    <div class="form-text">Hari dalam sebulan (1–28)</div>
                    @error('tanggal_jatuh_tempo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label">Tanggal Mulai <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal_mulai" class="form-control @error('tanggal_mulai') is-invalid @enderror"
                           value="{{ old('tanggal_mulai', today()->toDateString()) }}" required>
                    @error('tanggal_mulai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label">Tanggal Akhir <span class="text-muted small">(opsional)</span></label>
                    <input type="date" name="tanggal_akhir" class="form-control @error('tanggal_akhir') is-invalid @enderror"
                           value="{{ old('tanggal_akhir') }}">
                    <div class="form-text">Kosongkan jika tidak ada batas waktu</div>
                    @error('tanggal_akhir')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label">Kategori</label>
                    <select name="kategori_id" id="kategoriSelect" class="form-select @error('kategori_id') is-invalid @enderror">
                        <option value="">— Pilih Kategori —</option>
                        @foreach($kategoris as $k)
                        <option value="{{ $k->id }}" data-tipe="{{ $k->tipe }}" @selected(old('kategori_id')==$k->id)>
                            {{ $k->parent ? $k->parent->nama.' › ' : '' }}{{ $k->nama }}
                        </option>
                        @endforeach
                    </select>
                    @error('kategori_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label">Kas Tujuan <x-tooltip key="recurring.kas_id" /></label>
                    <select name="kas_id" class="form-select @error('kas_id') is-invalid @enderror">
                        <option value="">— Pilih Kas —</option>
                        @foreach($kasOptions as $k)
                        <option value="{{ $k->id }}" @selected(old('kas_id')==$k->id)>
                            {{ $k->nama_kas }}{{ $k->cabang?->nama_cabang ? ' ('.$k->cabang?->nama_cabang.')' : '' }}
                        </option>
                        @endforeach
                    </select>
                    @error('kas_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label">Keterangan</label>
                    <textarea name="keterangan" class="form-control" rows="2">{{ old('keterangan') }}</textarea>
                </div>

                <div class="col-12 col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" @checked(old('is_active', true))>
                        <label class="form-check-label" for="isActive">Aktifkan template ini</label>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="auto_approve" id="autoApprove" value="1" @checked(old('auto_approve', true))>
                        <label class="form-check-label" for="autoApprove">Auto-approve transaksi yang di-generate <x-tooltip key="recurring.auto_approve" /></label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2 justify-content-end">
                    <a href="{{ route('recurring.index') }}" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Template</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
function syncRupiah(display, raw) {
    display.addEventListener('input', function () {
        const digits = this.value.replace(/\D/g,'');
        this.value = digits ? new Intl.NumberFormat('id-ID').format(parseInt(digits)) : '';
        raw.value = digits || '';
    });
    if (raw.value) {
        display.value = new Intl.NumberFormat('id-ID').format(parseInt(raw.value));
    }
}
syncRupiah(document.getElementById('display_jumlah'), document.getElementById('raw_jumlah'));

function loadKategori(tipe) {
    const sel = document.getElementById('kategoriSelect');
    Array.from(sel.options).forEach(opt => {
        if (!opt.value) { opt.hidden = false; return; }
        const t = opt.dataset.tipe;
        opt.hidden = tipe && t !== tipe && t !== 'keduanya';
    });
    if (sel.selectedOptions[0]?.hidden) sel.value = '';
}
loadKategori(document.getElementById('tipe').value);
</script>
@endpush
