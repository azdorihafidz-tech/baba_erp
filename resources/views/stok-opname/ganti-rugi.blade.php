@extends('layouts.app')

@section('title', 'Ganti Rugi Karyawan')

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.'); @endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-person-exclamation me-2 text-danger"></i>Ganti Rugi Karyawan</h5>
    <div class="d-flex gap-2 flex-wrap">
        <x-panduan-button slug="ganti-rugi-karyawan" />
        <a href="{{ route('stok-opname.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clipboard-check"></i> Stok Opname</a>
    </div>
</div>
<div class="row g-2 mb-3">
    @foreach([['Dibebankan', $ringkas['dibebankan'], ''], ['Dibayar tunai', $ringkas['tunai'], 'text-success'], ['Dipotong gaji', $ringkas['potong_gaji'], 'text-success'], ['Sisa tagihan', $ringkas['sisa'], 'text-danger']] as [$l, $v, $c])
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body p-2"><div class="small text-muted">{{ $l }}</div><div class="fw-semibold {{ $c }}" data-uji="gr-{{ \Illuminate\Support\Str::slug($l) }}">{{ $rp($v) }}</div></div></div></div>
    @endforeach
</div>
<div class="d-flex flex-wrap gap-1 mb-2">
    @foreach(\App\Http\Controllers\GantiRugiStokController::TAB as $k => $label)
    <a href="{{ route('stok-opname.ganti-rugi', ['tab' => $k]) }}" class="btn btn-sm {{ $tab === $k ? 'btn-dark' : 'btn-outline-secondary' }}" data-uji="tab-{{ $k }}">{{ $label }} <span class="badge bg-secondary ms-1">{{ $jumlah[$k] }}</span></a>
    @endforeach
</div>

<div class="d-flex flex-column gap-2">
@forelse($daftar as $g)
<div class="card" data-uji="gr-{{ $g->nomor }}"><div class="card-body p-2 px-3">
    <div class="d-flex justify-content-between gap-2 flex-wrap">
        <div><span class="fw-semibold">{{ $g->karyawan?->nama_lengkap }}</span> <span class="small text-muted">· {{ $g->nomor }} · {{ $g->cabang?->nama_cabang }}</span></div>
        <span class="badge bg-{{ $g->status_warna }}">{{ $g->status_label }}</span>
    </div>
    <div class="small">{{ $g->rincian?->itemOpname?->barang?->nama_item ?? 'Barang' }} hilang @if($g->opname)· <a href="{{ route('stok-opname.show', $g->opname) }}">{{ $g->opname->nomor }}</a>@endif
        · {{ \App\Models\GantiRugiStok::CARA[$g->cara] }}@if($g->cara === 'potong_gaji') {{ $g->cicilan_bulan }}× {{ $rp($g->cicilan) }} mulai {{ $g->mulai_periode }}@endif</div>
    <div class="small">Nilai <strong>{{ $rp($g->nilai) }}</strong>@if(abs($g->nilai - $g->nilai_pokok) > 0.5) <span class="text-muted">(harga pokok {{ $rp($g->nilai_pokok) }} — {{ $g->alasan }})</span>@endif
        · terbayar <span class="text-success" data-uji="terbayar">{{ $rp($g->terbayar) }}</span> · sisa <span class="text-danger" data-uji="sisa">{{ $rp($g->sisa) }}</span></div>
    @if($g->bayar->isNotEmpty())
    <ul class="small mb-1 ps-3">@foreach($g->bayar as $b)
        <li>{{ $b->created_at?->format('d/m/Y') }} · {{ $b->jenis === 'tunai' ? 'Tunai' . ($b->kas ? ' ke ' . $b->kas->nama_kas : '') : 'Potong gaji ' . $b->periode }} · {{ $rp($b->jumlah) }}
            @if($b->status === 'menunggu')<span class="badge bg-light text-dark border">menunggu slip dibayar</span>@endif</li>
    @endforeach</ul>
    @endif
    @if($g->status === 'batal')<div class="small text-muted">Dibatalkan: {{ $g->alasan_batal }}</div>@endif
    @if($g->status === 'berjalan')
    <div class="d-flex gap-2 mt-1">
        <button type="button" class="btn btn-sm btn-outline-success gr-bayar" data-url="{{ route('stok-opname.ganti-rugi.tunai', $g) }}" data-cabang="{{ $g->cabang_id }}" data-sisa="{{ $g->sisa }}" data-uji="bayar-tunai">Bayar tunai</button>
        @if($g->terbayar <= 0)<button type="button" class="btn btn-sm btn-outline-secondary gr-batal" data-url="{{ route('stok-opname.ganti-rugi.batal', $g) }}" data-uji="batal-gr">Batalkan</button>@endif
    </div>
    @endif
</div></div>
@empty
<div class="text-center text-muted py-4">Belum ada ganti rugi di daftar ini. Ganti rugi dibebankan dari halaman stok opname yang sudah disetujui (barang hilang → <strong>Bebankan ke karyawan</strong>).</div>
@endforelse
</div>
<div class="mt-3">{{ $daftar->links() }}</div>

<div class="modal fade" id="modalBayar" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-fullscreen-sm-down"><div class="modal-content">
    <form method="POST" id="formBayar">@csrf
    <div class="modal-header py-2"><h6 class="modal-title fw-bold">Terima pembayaran tunai</h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
    <div class="modal-body d-flex flex-column gap-2">
        <div><label class="form-label small mb-0" for="byJumlah">Jumlah (Rp)</label><input type="number" name="jumlah" id="byJumlah" class="form-control form-control-sm" min="1" required data-uji="by-jumlah"></div>
        <div><label class="form-label small mb-0" for="byKas">Diterima di kas</label><select name="kas_id" id="byKas" class="form-select form-select-sm" data-uji="by-kas">
            @foreach($kas as $k)<option value="{{ $k->id }}" data-cabang="{{ $k->cabang_id }}">{{ $k->nama_kas }}</option>@endforeach</select></div>
        <div class="small text-muted">Uang masuk ke kas yang dipilih dan dicatat sebagai Pemulihan Kerugian Stok.</div>
    </div>
    <div class="modal-footer py-2"><button class="btn btn-sm btn-success" data-uji="by-simpan">Simpan</button></div>
    </form>
</div></div></div>
<div class="modal fade" id="modalBatalGr" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" id="formBatalGr">@csrf
    <div class="modal-header py-2"><h6 class="modal-title fw-bold">Batalkan ganti rugi</h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
    <div class="modal-body"><label class="form-label small" for="grAlasan">Alasan</label><textarea name="alasan" id="grAlasan" class="form-control" rows="2" minlength="5" maxlength="500" required data-uji="gr-alasan"></textarea>
        <div class="small text-muted mt-1">Potongan yang sudah masuk slip gaji yang belum dibayar ikut dikeluarkan dari slip itu.</div></div>
    <div class="modal-footer py-2"><button class="btn btn-sm btn-secondary" data-uji="gr-batal-kirim">Batalkan ganti rugi</button></div>
    </form>
</div></div></div>
@endsection

@push('scripts')
<script>
(() => {
    const mb = document.getElementById('modalBayar'), mt = document.getElementById('modalBatalGr');
    if (!mb) return;
    const bayar = new bootstrap.Modal(mb), batal = new bootstrap.Modal(mt);
    document.querySelectorAll('.gr-bayar').forEach((b) => b.addEventListener('click', () => {
        document.getElementById('formBayar').action = b.dataset.url;
        const j = document.getElementById('byJumlah'); j.max = b.dataset.sisa; j.value = Math.round(Number(b.dataset.sisa));
        const k = document.getElementById('byKas'); [...k.options].forEach((o) => { o.hidden = o.dataset.cabang !== b.dataset.cabang; });
        k.value = [...k.options].find((o) => !o.hidden)?.value || '';
        bayar.show();
    }));
    document.querySelectorAll('.gr-batal').forEach((b) => b.addEventListener('click', () => { document.getElementById('formBatalGr').action = b.dataset.url; batal.show(); }));
})();
</script>
@endpush
