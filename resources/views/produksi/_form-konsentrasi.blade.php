{{-- Form tambah/ubah konsentrasi parfum. $k = null untuk tambah. --}}
<form method="POST" action="{{ $k ? route('produksi.konsentrasi.update', $k) : route('produksi.konsentrasi.store') }}" class="row g-2 align-items-end">
    @csrf
    @if($k) @method('PUT') @endif
    <div class="col-6 col-md-2">
        <label class="form-label small fw-semibold mb-1">Nama *</label>
        <input type="text" name="nama" maxlength="30" class="form-control form-control-sm" value="{{ $k?->nama }}" placeholder="EDP" required>
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small fw-semibold mb-1">Keterangan</label>
        <input type="text" name="keterangan" maxlength="150" class="form-control form-control-sm" value="{{ $k?->keterangan }}" placeholder="Eau de Parfum">
    </div>
    <div class="col-4 col-md-1">
        <label class="form-label small fw-semibold mb-1">% Bibit *</label>
        <input type="number" step="0.01" min="0.01" max="99" name="persen_bibit" class="form-control form-control-sm" value="{{ $k ? (float) $k->persen_bibit : '' }}" required>
    </div>
    <div class="col-4 col-md-1">
        <label class="form-label small fw-semibold mb-1">% Fixative</label>
        <input type="number" step="0.01" min="0" max="99" name="persen_fixative" class="form-control form-control-sm" value="{{ $k ? (float) $k->persen_fixative : 0 }}">
    </div>
    <div class="col-4 col-md-2">
        <label class="form-label small fw-semibold mb-1">Lama peram (hari) *</label>
        <input type="number" step="1" min="0" max="365" name="lama_peram_hari" class="form-control form-control-sm" value="{{ $k?->lama_peram_hari ?? 0 }}" required>
    </div>
    <div class="col-6 col-md-1">
        <label class="form-label small fw-semibold mb-1">Urutan</label>
        <input type="number" step="1" min="0" max="999" name="urutan" class="form-control form-control-sm" value="{{ $k?->urutan ?? 0 }}">
    </div>
    <div class="col-6 col-md-1">
        <div class="form-check form-switch mb-1">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="aktif{{ $k?->id ?? 'baru' }}" @checked($k?->is_active ?? true)>
            <label class="form-check-label small" for="aktif{{ $k?->id ?? 'baru' }}">Aktif</label>
        </div>
    </div>
    <div class="col-12 col-md-1">
        <button class="btn btn-primary btn-sm w-100">{{ $k ? 'Simpan' : 'Tambah' }}</button>
    </div>
</form>
