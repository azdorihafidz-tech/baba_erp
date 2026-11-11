@extends('layouts.app')

@section('title', 'Buat Parfum')

@section('content')
@php
    $nama = fn ($i) => $i->nama_item . ' (' . $i->satuan . ')';
    // tebakan awal dari nama barang (bisa diganti)
    $cari = fn (string $pola) => $barang->first(fn ($i) => preg_match($pola, $i->nama_item))?->id;
    $botolUntuk = fn (int $ml) => $barang->first(fn ($i) => preg_match('/botol/i', $i->nama_item) && preg_match('/\b' . $ml . '\s*ml\b/i', $i->nama_item))?->id;
    $ukuranAwal = old('ukuran', collect([30, 50, 100])->map(fn ($ml) => ['ml' => $ml, 'botol_id' => $botolUntuk($ml)])->all());
    $kemasanAwal = old('kemasan_ids', $barang->filter(fn ($i) => preg_match('/sprayer|spray|label|box|dus|segel/i', $i->nama_item))->pluck('id')->all());
@endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-droplet me-2 text-success"></i>Produksi Parfum</h5>
    <div class="d-flex gap-2 align-items-center"><x-panduan-button slug="produksi" /></div>
</div>

@include('produksi._tabs')
@include('produksi._tanpa-gudang')

@if($gudangPilih)
<div class="alert alert-light border small">
    <i class="bi bi-magic me-1"></i>
    Sekali simpan, sistem membuat <strong>cairan parfum</strong> + resep racik (dari persen konsentrasi), dan untuk setiap ukuran
    <strong>parfum botol</strong> + resep isi botol. Bahan (bibit, fixative, alkohol, botol, kemasan) harus sudah terdaftar di
    <strong>Bahan Baku &amp; Kemasan</strong> gudang ini.
</div>

<form method="POST" action="{{ route('produksi.parfum.store') }}" id="formParfum">
@csrf
<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card"><div class="card-body"><div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold">Gudang</label>
                @if($gudang->count() > 1)
                <select name="cabang_id" class="form-select" onchange="location.href='{{ route('produksi.parfum.create') }}?cabang_id=' + this.value">
                    @foreach($gudang as $g)<option value="{{ $g->id }}" @selected($g->id === $gudangPilih->id)>{{ $g->nama_cabang }}</option>@endforeach
                </select>
                @else
                <input type="hidden" name="cabang_id" value="{{ $gudangPilih->id }}">
                <div class="form-control bg-light">{{ $gudangPilih->nama_cabang }}</div>
                @endif
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold">Konsentrasi * <x-tooltip key="konsentrasi.persen_bibit" /></label>
                <select name="konsentrasi_id" id="konsentrasi" class="form-select" required>
                    @foreach($konsentrasi as $k)
                    <option value="{{ $k->id }}" data-nama="{{ $k->nama }}" data-bibit="{{ (float) $k->persen_bibit }}" data-fixative="{{ (float) $k->persen_fixative }}" data-alkohol="{{ $k->persen_alkohol }}" data-peram="{{ $k->lama_peram_hari }}" @selected(old('konsentrasi_id') == $k->id)>
                        {{ $k->nama }}{{ $k->keterangan ? ' — ' . $k->keterangan : '' }}</option>
                    @endforeach
                </select>
                <div class="form-text"><a href="{{ route('produksi.konsentrasi.index') }}">Atur persen &amp; lama peram</a></div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold">Bibit (aroma) *</label>
                <select name="bibit_id" id="bibit" class="form-select" required>
                    <option value="">— pilih —</option>
                    @foreach($barang as $i)<option value="{{ $i->id }}" @selected(old('bibit_id', $cari('/bibit/i')) == $i->id)>{{ $nama($i) }}</option>@endforeach
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold">Nama aroma * <x-tooltip key="parfum.nama_aroma" /></label>
                <input type="text" name="nama_aroma" id="namaAroma" maxlength="60" class="form-control" value="{{ old('nama_aroma') }}" placeholder="mis. Ocean Breeze" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold">Fixative</label>
                <select name="fixative_id" id="fixative" class="form-select">
                    <option value="">— tidak pakai —</option>
                    @foreach($barang as $i)<option value="{{ $i->id }}" @selected(old('fixative_id', $cari('/fixative|fiksatif/i')) == $i->id)>{{ $nama($i) }}</option>@endforeach
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold">Alkohol *</label>
                <select name="alkohol_id" id="alkohol" class="form-select" required>
                    <option value="">— pilih —</option>
                    @foreach($barang as $i)<option value="{{ $i->id }}" @selected(old('alkohol_id', $cari('/alkohol|alcohol|ethanol/i')) == $i->id)>{{ $nama($i) }}</option>@endforeach
                </select>
            </div>
        </div></div></div>

        <div class="card mt-3">
            <div class="card-header bg-white small fw-semibold d-flex justify-content-between align-items-center">
                <span>Ukuran botol <x-tooltip key="parfum.ukuran" /></span>
                <button type="button" class="btn btn-outline-primary btn-sm" id="tambahUkuran"><i class="bi bi-plus"></i> Ukuran</button>
            </div>
            <div class="card-body" id="daftarUkuran">
                @foreach($ukuranAwal as $n => $u)
                <div class="row g-2 align-items-center mb-2 baris-ukuran">
                    <div class="col-4 col-md-3">
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.01" min="0.01" name="ukuran[{{ $n }}][ml]" class="form-control ukuran-ml" value="{{ $u['ml'] ?? '' }}">
                            <span class="input-group-text">ml</span>
                        </div>
                    </div>
                    <div class="col-7 col-md-8">
                        <select name="ukuran[{{ $n }}][botol_id]" class="form-select form-select-sm ukuran-botol">
                            <option value="">— pilih botol —</option>
                            @foreach($barang as $i)<option value="{{ $i->id }}" @selected(($u['botol_id'] ?? null) == $i->id)>{{ $nama($i) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-1"><button type="button" class="btn btn-link text-danger p-0 hapus-ukuran" title="Hapus"><i class="bi bi-x-lg"></i></button></div>
                </div>
                @endforeach
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header bg-white small fw-semibold">Kemasan tambahan per botol <span class="text-muted fw-normal">(1 buah per botol, mis. sprayer, label, box)</span></div>
            <div class="card-body" style="max-height: 220px; overflow-y: auto;">
                @forelse($barang as $i)
                <div class="form-check">
                    <input class="form-check-input kemasan" type="checkbox" name="kemasan_ids[]" value="{{ $i->id }}" id="km{{ $i->id }}" data-nama="{{ $i->nama_item }}" @checked(in_array($i->id, array_map('intval', (array) $kemasanAwal), true))>
                    <label class="form-check-label small" for="km{{ $i->id }}">{{ $nama($i) }}</label>
                </div>
                @empty
                <div class="text-muted small">Belum ada barang di gudang ini.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card position-sticky" style="top: 80px;"><div class="card-body">
            <div class="fw-semibold mb-2"><i class="bi bi-eye me-1"></i>Yang akan dibuat</div>
            <div id="pratinjau" class="small"></div>
            <button class="btn btn-primary w-100 mt-3"><i class="bi bi-magic me-1"></i>Buat Parfum</button>
        </div></div>
    </div>
</div>
</form>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const el = id => document.getElementById(id);
    if (!el('formParfum')) return;
    const VOL = {{ $volumeRacik }};
    const esc = v => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
    const angka = n => (Math.round(n * 100) / 100).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    const teksPilihan = s => s.selectedOptions[0] && s.value ? s.selectedOptions[0].textContent.trim() : '';
    let aromaDiubah = !!el('namaAroma').value;
    el('namaAroma').addEventListener('input', () => { aromaDiubah = true; gambar(); });
    el('bibit').addEventListener('change', () => {
        if (!aromaDiubah) el('namaAroma').value = teksPilihan(el('bibit')).replace(/\s*\([^)]*\)\s*$/, '').replace(/^bibit\s+/i, '');
        gambar();
    });
    if (!aromaDiubah && el('bibit').value) el('bibit').dispatchEvent(new Event('change'));
    let nomor = {{ count($ukuranAwal) }};
    el('tambahUkuran').addEventListener('click', () => {
        const contoh = document.querySelector('.baris-ukuran');
        const baru = contoh.cloneNode(true);
        baru.querySelectorAll('input, select').forEach(f => { f.name = f.name.replace(/ukuran\[\d+\]/, `ukuran[${nomor}]`); f.value = ''; });
        nomor++;
        el('daftarUkuran').appendChild(baru);
        gambar();
    });
    el('daftarUkuran').addEventListener('click', e => {
        const t = e.target.closest('.hapus-ukuran');
        if (t && document.querySelectorAll('.baris-ukuran').length > 1) { t.closest('.baris-ukuran').remove(); gambar(); }
    });
    el('formParfum').addEventListener('input', gambar);
    el('formParfum').addEventListener('change', gambar);

    function gambar() {
        const k = el('konsentrasi').selectedOptions[0];
        if (!k) { el('pratinjau').innerHTML = '<span class="text-danger">Belum ada konsentrasi aktif.</span>'; return; }
        const kn = k.dataset.nama, aroma = el('namaAroma').value.trim() || '…';
        const bibit = teksPilihan(el('bibit')) || '<span class="text-danger">bibit belum dipilih</span>';
        const fix = teksPilihan(el('fixative')), alk = teksPilihan(el('alkohol')) || '<span class="text-danger">alkohol belum dipilih</span>';
        const pb = +k.dataset.bibit, pf = +k.dataset.fixative, pa = +k.dataset.alkohol, peram = +k.dataset.peram;
        const kemasan = [...document.querySelectorAll('.kemasan:checked')].map(c => c.dataset.nama);
        let h = `<div class="border rounded p-2 mb-2"><div class="fw-semibold">Cairan ${esc(aroma)} ${esc(kn)} <span class="text-muted">(ml)</span></div>
            <div class="text-muted">Resep racik per ${angka(VOL)} ml · diperam ${peram} hari</div>
            <ul class="mb-0 ps-3"><li>${bibit}: ${angka(VOL * pb / 100)} ml (${angka(pb)}%)</li>
            ${pf > 0 ? `<li>${fix || '<span class="text-danger">fixative belum dipilih</span>'}: ${angka(VOL * pf / 100)} ml (${angka(pf)}%)</li>` : ''}
            <li>${alk}: ${angka(VOL * pa / 100)} ml (${angka(pa)}%)</li></ul></div>`;
        document.querySelectorAll('.baris-ukuran').forEach(r => {
            const ml = parseFloat(r.querySelector('.ukuran-ml').value);
            if (!(ml > 0)) return;
            const botol = teksPilihan(r.querySelector('.ukuran-botol')) || '<span class="text-danger">botol belum dipilih</span>';
            h += `<div class="border rounded p-2 mb-2"><div class="fw-semibold">${esc(aroma)} ${esc(kn)} ${angka(ml)} ml <span class="text-muted">(pcs)</span></div>
                <div class="text-muted">Resep isi botol per 1 botol</div>
                <ul class="mb-0 ps-3"><li>Cairan ${esc(aroma)} ${esc(kn)}: ${angka(ml)} ml</li><li>${botol}: 1</li>
                ${kemasan.map(n => `<li>${esc(n)}: 1</li>`).join('')}</ul></div>`;
        });
        el('pratinjau').innerHTML = h;
    }
    gambar();
})();
</script>
@endpush
