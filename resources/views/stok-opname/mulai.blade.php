@extends('layouts.app')

@section('title', 'Mulai Stok Opname')

@section('content')
<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-plus me-2 text-primary"></i>Mulai Stok Opname</h5>
    <x-panduan-button slug="stok-opname" />
</div>

<form method="POST" action="{{ route('stok-opname.mulai.store') }}" class="card" style="max-width:720px" id="formMulai">
    @csrf
    <div class="card-body d-flex flex-column gap-3">
        <div>
            <label class="form-label fw-semibold small" for="cabang_id">Lokasi</label>
            <select name="cabang_id" id="cabang_id" class="form-select" required data-uji="lokasi">
                @foreach($lokasi as $l)
                <option value="{{ $l->id }}" @selected((int) old('cabang_id', \App\Support\CabangAktif::id()) === $l->id)>{{ $l->nama_cabang }}@if(\App\Services\StokOpnameService::lokasiMitra($l)) (toko mitra)@endif</option>
                @endforeach
            </select>
        </div>
        <div>
            <div class="form-label fw-semibold small mb-1">Cakupan <x-tooltip key="stok_opname.cakupan" /></div>
            @foreach(\App\Models\StokOpname::CAKUPAN as $k => $label)
            <div class="form-check">
                <input class="form-check-input" type="radio" name="cakupan" id="cak_{{ $k }}" value="{{ $k }}" @checked(old('cakupan', 'semua') === $k) data-uji="cakupan-{{ $k }}">
                <label class="form-check-label" for="cak_{{ $k }}">{{ $label }}</label>
            </div>
            @endforeach
        </div>
        <div id="pilihKategori" class="border rounded p-2" style="display:none">
            <div class="small text-muted mb-1">Centang kategori yang dihitung:</div>
            <div class="d-flex flex-wrap gap-2">
                @foreach($kategori as $k)
                <div class="form-check me-2"><input class="form-check-input" type="checkbox" name="kategori[]" value="{{ $k->id }}" id="kat{{ $k->id }}" @checked(in_array($k->id, (array) old('kategori', [])))>
                    <label class="form-check-label small" for="kat{{ $k->id }}">{{ $k->nama_kategori }}</label></div>
                @endforeach
            </div>
        </div>
        <div id="pilihBarang" class="border rounded p-2" style="display:none">
            <input type="search" id="cariBarang" class="form-control form-control-sm mb-2" placeholder="Cari nama / kode / barcode barang" data-uji="cari-barang">
            <div id="daftarBarang" class="d-flex flex-column gap-1" style="max-height:300px;overflow:auto"><div class="small text-muted">Memuat…</div></div>
            <div class="small text-muted mt-1"><span id="jmlPilih">0</span> barang dipilih</div>
        </div>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="hitung_buta" value="1" id="hitung_buta" @checked(old('hitung_buta', $hitungButa)) data-uji="hitung-buta">
            <label class="form-check-label" for="hitung_buta">Hitung buta — penghitung tidak melihat stok sistem <x-tooltip key="stok_opname.hitung_buta" /></label>
        </div>
        <div>
            <label class="form-label fw-semibold small" for="catatan">Catatan (opsional)</label>
            <input type="text" name="catatan" id="catatan" class="form-control" maxlength="500" value="{{ old('catatan') }}" placeholder="mis. Opname akhir bulan November">
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" data-uji="mulai-simpan"><i class="bi bi-play-fill me-1"></i>Mulai Opname</button>
            <a href="{{ route('stok-opname.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(() => {
    const dipilih = new Set(@json(array_map('intval', (array) old('barang', []))));
    const lokasi = document.getElementById('cabang_id');
    const cari = document.getElementById('cariBarang');
    const daftar = document.getElementById('daftarBarang');
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    function tampilCakupan() {
        const c = document.querySelector('input[name=cakupan]:checked')?.value;
        document.getElementById('pilihKategori').style.display = c === 'kategori' ? '' : 'none';
        document.getElementById('pilihBarang').style.display = c === 'barang' ? '' : 'none';
        if (c === 'barang') muat();
    }
    let t = null, minta = 0;
    async function muat() {
        const n = ++minta;
        const r = await fetch(`{{ route('stok-opname.barang') }}?lokasi=${lokasi.value}&q=${encodeURIComponent(cari.value)}`, { headers: { Accept: 'application/json' } });
        if (n !== minta) return;
        const data = r.ok ? await r.json() : [];
        daftar.innerHTML = data.length ? data.map((b) => `<label class="d-flex align-items-center gap-2 small border-bottom py-1">
            <input type="checkbox" class="form-check-input m-0" name="barang[]" value="${b.id}" ${dipilih.has(b.id) ? 'checked' : ''} data-uji="barang-${b.id}">
            <span class="flex-grow-1">${esc(b.nama_item)} <span class="text-muted">· ${esc(b.kode_item)} · ${esc(b.satuan)}</span></span></label>`).join('')
            : '<div class="small text-muted">Tidak ada barang ber-stok yang cocok.</div>';
        // barang terpilih yang tidak tampil di hasil cari tetap dikirim
        dipilih.forEach((id) => { if (!data.some((b) => b.id === id)) daftar.insertAdjacentHTML('beforeend', `<input type="hidden" name="barang[]" value="${id}">`); });
    }
    daftar.addEventListener('change', (e) => { if (e.target.name === 'barang[]') { e.target.checked ? dipilih.add(+e.target.value) : dipilih.delete(+e.target.value); document.getElementById('jmlPilih').textContent = dipilih.size; } });
    cari.addEventListener('input', () => { clearTimeout(t); t = setTimeout(muat, 300); });
    lokasi.addEventListener('change', () => { dipilih.clear(); document.getElementById('jmlPilih').textContent = 0; if (document.getElementById('cak_barang').checked) muat(); });
    document.querySelectorAll('input[name=cakupan]').forEach((r) => r.addEventListener('change', tampilCakupan));
    document.getElementById('jmlPilih').textContent = dipilih.size;
    tampilCakupan();
})();
</script>
@endpush
