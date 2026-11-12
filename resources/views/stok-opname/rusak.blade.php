@extends('layouts.app')

@section('title', 'Stok Rusak')

@section('content')
@php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $ang = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
@endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-heartbreak me-2 text-danger"></i>Stok Rusak</h5>
    <div class="d-flex gap-2 flex-wrap">
        <x-panduan-button slug="stok-rusak" />
        <a href="{{ route('stok-opname.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clipboard-check"></i> Stok Opname</a>
    </div>
</div>
<div class="alert alert-light border small py-2"><i class="bi bi-info-circle me-1"></i>Barang yang dicatat <strong>rusak</strong> saat stok opname keluar dari stok jual dan ditahan di sini. Kerugiannya sudah tercatat; tindak lanjut di bawah hanya memulihkan bila ada yang kembali. Nilai yang masih ditahan: <strong data-uji="nilai-ditahan">{{ $rp($nilaiDitahan) }}</strong>.</div>

<div class="d-flex flex-wrap gap-1 mb-2">
    @foreach(\App\Http\Controllers\StokRusakController::TAB as $k => $label)
    <a href="{{ route('stok-opname.rusak', array_filter(['tab' => $k, 'lokasi' => $pilihLokasi])) }}" class="btn btn-sm {{ $tab === $k ? 'btn-dark' : 'btn-outline-secondary' }}" data-uji="tab-{{ $k }}">
        {{ $label }} <span class="badge {{ $k === 'ditahan' && $jumlah[$k] ? 'bg-danger' : 'bg-secondary' }} ms-1">{{ $jumlah[$k] }}</span></a>
    @endforeach
</div>
@if($lokasi->count() > 1)
<form method="GET" class="mb-3" style="max-width:320px"><input type="hidden" name="tab" value="{{ $tab }}">
    <select name="lokasi" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Lokasi"><option value="">Semua lokasi</option>
        @foreach($lokasi as $l)<option value="{{ $l->id }}" @selected($pilihLokasi === $l->id)>{{ $l->nama_cabang }}</option>@endforeach</select></form>
@endif

<div class="d-flex flex-column gap-2">
@forelse($rusak as $r)
<div class="card" data-uji="rusak-{{ $r->barang?->kode_item }}"><div class="card-body p-2 px-3">
    <div class="d-flex justify-content-between gap-2 flex-wrap">
        <div class="fw-semibold">{{ $r->barang?->nama_item }} <span class="small text-muted">· {{ $r->cabang?->nama_cabang }}</span></div>
        <span class="badge bg-{{ $r->status === 'ditahan' ? 'warning text-dark' : 'success' }}">{{ $r->status === 'ditahan' ? 'Ditahan ' . $r->umur_hari . ' hari' : 'Selesai' }}</span>
    </div>
    <div class="small">Sisa <strong data-uji="sisa">{{ $ang($r->qty_sisa) }}</strong> dari {{ $ang($r->qty) }} {{ $r->satuan }} · nilai pokok {{ $rp($r->nilai_sisa) }}
        @if($r->opname)· dari <a href="{{ route('stok-opname.show', $r->opname) }}">{{ $r->opname->nomor }}</a>@endif</div>
    @if($r->catatan)<div class="small text-muted">{{ $r->catatan }}</div>@endif
    @if($r->tindakan->isNotEmpty())
    <ul class="small mb-1 mt-1 ps-3">
        @foreach($r->tindakan as $t)
        <li>{{ $t->created_at?->format('d/m/Y') }} · {{ $t->jenis_label }} {{ $ang($t->qty) }} {{ $r->satuan }}@if($t->nilai_pulih > 0) · dipulihkan <span class="text-success">{{ $rp($t->nilai_pulih) }}</span>@endif
            @if($t->supplier) · {{ $t->supplier->nama_supplier }}@endif @if($t->kas) · {{ $t->kas->nama_kas }}@endif · {{ $t->user?->name }}
            @if($t->catatan)<span class="text-muted">— {{ $t->catatan }}</span>@endif
            @if($t->foto)<a href="{{ route('stok-opname.rusak.foto', $t) }}" target="_blank" class="ms-1"><i class="bi bi-image"></i></a>@endif
            @if($t->jenis === 'musnahkan')<a href="{{ route('stok-opname.rusak.musnah', $t) }}" class="ms-1" data-uji="ba-musnah"><i class="bi bi-file-earmark-pdf"></i> berita acara</a>@endif</li>
        @endforeach
    </ul>
    @endif
    @if($boleh[$r->id] ?? false)
    <button type="button" class="btn btn-sm btn-outline-primary mt-1 so-tindak" data-id="{{ $r->id }}" data-nama="{{ $r->barang?->nama_item }}" data-sisa="{{ $r->qty_sisa }}"
            data-satuan="{{ $r->satuan }}" data-desimal="{{ \App\Services\StokOpnameService::bolehDesimal($r->satuan) ? 1 : 0 }}" data-cabang="{{ $r->cabang_id }}" data-uji="tindak">Tindak lanjut</button>
    @endif
</div></div>
@empty
<div class="text-center text-muted py-4">Tidak ada stok rusak di daftar ini.</div>
@endforelse
</div>
<div class="mt-3">{{ $rusak->links() }}</div>

<div class="modal fade" id="modalTindak" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content">
    <form method="POST" enctype="multipart/form-data" id="formTindak">@csrf
    <div class="modal-header py-2"><h6 class="modal-title fw-bold">Tindak lanjut stok rusak</h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
    <div class="modal-body d-flex flex-column gap-2">
        <div><span class="fw-semibold" id="tdNama"></span> <span class="small text-muted">sisa <span id="tdSisa"></span></span></div>
        <div>@foreach(\App\Models\StokRusakTindakan::JENIS as $k => $l)
            <div class="form-check"><input class="form-check-input" type="radio" name="jenis" id="jenis_{{ $k }}" value="{{ $k }}" @checked($loop->first) data-uji="jenis-{{ $k }}">
                <label class="form-check-label small" for="jenis_{{ $k }}">{{ $l }}</label></div>
        @endforeach</div>
        <div><label class="form-label small mb-0" for="tdQty">Jumlah</label><input type="number" name="qty" id="tdQty" class="form-control form-control-sm" min="0" required data-uji="td-qty"></div>
        <div class="td-pemasok"><label class="form-label small mb-0" for="tdSupplier">Pemasok (opsional)</label>
            <select name="supplier_id" id="tdSupplier" class="form-select form-select-sm"><option value="">—</option>@foreach($supplier as $s)<option value="{{ $s->id }}">{{ $s->nama_supplier }}</option>@endforeach</select></div>
        <div class="td-uang"><label class="form-label small mb-0" for="tdNominal">Uang dikembalikan (Rp)</label><input type="number" name="nominal" id="tdNominal" class="form-control form-control-sm" min="0" data-uji="td-nominal"></div>
        <div class="td-uang"><label class="form-label small mb-0" for="tdKas">Diterima di kas</label>
            <select name="kas_id" id="tdKas" class="form-select form-select-sm" data-uji="td-kas">@foreach($kas as $k)<option value="{{ $k->id }}" data-cabang="{{ $k->cabang_id }}">{{ $k->nama_kas }}</option>@endforeach</select></div>
        <div><label class="form-label small mb-0" for="tdFoto">Foto <span class="td-wajib-foto text-danger">(wajib untuk dimusnahkan)</span></label>
            <input type="file" name="foto" id="tdFoto" accept="image/jpeg,image/png,image/webp" capture="environment" class="form-control form-control-sm" data-uji="td-foto"></div>
        <div><label class="form-label small mb-0" for="tdCatatan">Catatan <span class="td-wajib-catatan text-danger">(wajib: nomor PO / faktur yang dipotong)</span></label>
            <input type="text" name="catatan" id="tdCatatan" class="form-control form-control-sm" maxlength="500" data-uji="td-catatan"></div>
        <div class="small text-muted" id="tdKet"></div>
    </div>
    <div class="modal-footer py-2"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-sm btn-primary" data-uji="td-simpan">Simpan</button></div>
    </form>
</div></div></div>
@endsection

@push('scripts')
<script>
(() => {
    const el = document.getElementById('modalTindak'); if (!el) return;
    const modal = new bootstrap.Modal(el), form = document.getElementById('formTindak');
    const URL = @json(route('stok-opname.rusak.tindak', 0));
    const KET = {
        pakai_lagi: 'Barang kembali ke stok jual dengan harga pokok asal; kerugian rusak dibatalkan.',
        pemasok_ganti: 'Barang pengganti masuk ke stok jual; kerugian rusak dibatalkan.',
        pemasok_uang: 'Uang dari pemasok masuk ke kas yang dipilih sebagai Pemulihan Kerugian Stok.',
        pemasok_potong: 'Tidak ada jurnal: penghematannya terlihat saat membayar tagihan pemasok yang sudah dipotong.',
        musnahkan: 'Barang dibuang. Kerugian sudah tercatat saat opname; berita acara pemusnahan bisa dicetak.',
    };
    let cabang = null;
    function atur() {
        const j = form.querySelector('input[name=jenis]:checked').value;
        form.querySelectorAll('.td-pemasok').forEach((x) => x.style.display = j.startsWith('pemasok') ? '' : 'none');
        form.querySelectorAll('.td-uang').forEach((x) => x.style.display = j === 'pemasok_uang' ? '' : 'none');
        form.querySelector('.td-wajib-foto').style.display = j === 'musnahkan' ? '' : 'none';
        form.querySelector('.td-wajib-catatan').style.display = j === 'pemasok_potong' ? '' : 'none';
        document.getElementById('tdNominal').required = j === 'pemasok_uang';
        document.getElementById('tdFoto').required = j === 'musnahkan';
        document.getElementById('tdCatatan').required = j === 'pemasok_potong';
        document.getElementById('tdKet').textContent = KET[j] || '';
        const kas = document.getElementById('tdKas');
        [...kas.options].forEach((o) => { o.hidden = o.dataset.cabang !== cabang; });
        if (kas.selectedOptions[0]?.hidden) kas.value = [...kas.options].find((o) => !o.hidden)?.value || '';
    }
    form.querySelectorAll('input[name=jenis]').forEach((r) => r.addEventListener('change', atur));
    document.querySelectorAll('.so-tindak').forEach((b) => b.addEventListener('click', () => {
        form.action = URL.replace(/\/0\/tindak$/, '/' + b.dataset.id + '/tindak');
        cabang = b.dataset.cabang;
        document.getElementById('tdNama').textContent = b.dataset.nama;
        document.getElementById('tdSisa').textContent = Number(b.dataset.sisa).toLocaleString('id-ID') + ' ' + b.dataset.satuan;
        const q = document.getElementById('tdQty'); q.max = b.dataset.sisa; q.step = b.dataset.desimal === '1' ? 'any' : '1'; q.value = b.dataset.sisa;
        form.reset(); q.value = b.dataset.sisa; form.querySelector('input[name=jenis]').checked = true; atur();
        modal.show();
    }));
})();
</script>
@endpush
