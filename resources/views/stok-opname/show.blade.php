@extends('layouts.app')

@section('title', 'Stok Opname ' . $o->nomor)

@push('styles')
<style>
.so-baris { display:flex; align-items:center; gap:.5rem; padding:.45rem .25rem; border-bottom:1px solid #eee; flex-wrap:wrap; }
.so-baris.ulang { background:#fff7e0; }
.so-baris.baru { animation: soKilat 1.2s; }
@keyframes soKilat { from { background:#d9f7e4; } to { background:transparent; } }
.so-nama { flex:1 1 180px; min-width:0; }
.so-kendali { display:flex; align-items:center; gap:.25rem; }
.so-kendali input { width:92px; text-align:right; }
.so-angka { min-width:64px; text-align:right; }
.so-sticky { position:sticky; top:0; z-index:5; background:var(--bs-body-bg, #fff); padding-bottom:.5rem; }
</style>
@endpush

@section('content')
@php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $ang = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
    $svc = app(\App\Services\StokOpnameService::class);
    $dihitung = $rows->whereNotNull('qty_fisik')->count();
    $selisihRows = $rows->filter(fn ($r) => $r->qty_fisik !== null && abs((float) $r->selisih) >= 0.0005);
    $dataPenyebab = $rows->filter(fn ($r) => $r->qty_fisik !== null && (float) $r->selisih < 0)->mapWithKeys(fn ($r) => [$r->id => [
        'nama' => $r->barang?->nama_item, 'satuan' => $r->satuan, 'kurang' => abs((float) $r->selisih), 'desimal' => \App\Services\StokOpnameService::bolehDesimal($r->satuan),
        'rincian' => $r->rincian->map(fn ($x) => ['id' => $x->id, 'penyebab' => $x->penyebab, 'qty' => $x->qty, 'catatan' => $x->catatan, 'foto' => (bool) $x->foto])->values(),
    ]]);
    $labelPenyebab = collect(\App\Services\StokOpnameService::PENYEBAB_KURANG)->mapWithKeys(fn ($k) => [$k => \App\Services\StokOpnameService::PENYEBAB[$k]['label']]);
    $tabAwal = request('tab', $o->status === 'selesai' ? 'ringkasan' : 'hitung');
@endphp

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start gap-2 mb-2">
    <div>
        <h5 class="mb-1 fw-bold"><i class="bi bi-clipboard-check me-2 text-primary"></i>{{ $o->nomor }} <span class="badge bg-{{ $o->status_warna }} align-middle" data-uji="status">{{ $o->status_label }}</span></h5>
        <div class="small text-muted">{{ $o->cabang?->nama_cabang }}@if($o->milik_mitra) (toko mitra)@endif · {{ \App\Models\StokOpname::CAKUPAN[$o->cakupan] ?? $o->cakupan }}@if($o->cakupan_ket) — {{ \Illuminate\Support\Str::limit($o->cakupan_ket, 120) }}@endif</div>
        <div class="small text-muted">Dimulai {{ $o->pembuat?->name ?? '-' }} · {{ $o->created_at->format('d/m/Y H:i') }}@if($o->hitung_buta) · <span title="Penghitung tidak melihat stok sistem"><i class="bi bi-eye-slash"></i> hitung buta</span>@endif</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <x-panduan-button slug="stok-opname" />
        @if($lihatSistem)
        <a href="{{ route('stok-opname.berita-acara', $o) }}" class="btn btn-sm btn-outline-danger" data-uji="ba-pdf"><i class="bi bi-file-earmark-pdf me-1"></i>Berita Acara</a>
        <a href="{{ route('stok-opname.berita-acara.excel', $o) }}" class="btn btn-sm btn-outline-success" data-uji="ba-excel"><i class="bi bi-file-earmark-excel"></i></a>
        @endif
        <a href="{{ route('stok-opname.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Daftar</a>
    </div>
</div>

@if($o->status === 'hitung' && $o->alasan_tolak)
<div class="alert alert-warning py-2 small mb-2" data-uji="alasan-tolak"><i class="bi bi-arrow-counterclockwise me-1"></i><strong>Ditolak:</strong> {{ $o->alasan_tolak }}</div>
@endif
@if($o->status === 'diajukan')
<div class="alert alert-info py-2 small mb-2"><i class="bi bi-hourglass-split me-1"></i>Diajukan {{ $o->pengaju?->name }} · {{ $o->diajukan_at?->format('d/m/Y H:i') }}. Hitungan terkunci sampai disetujui atau ditolak.
    @if($sebabSetujui)<div class="mt-1 text-danger" data-uji="sebab-setujui">{{ $sebabSetujui }}</div>@endif</div>
@endif
@if($o->status === 'batal')
<div class="alert alert-secondary py-2 small mb-2"><i class="bi bi-x-circle me-1"></i>Dibatalkan {{ $o->pembatal?->name }}: {{ $o->alasan_batal }}. Stok tidak berubah.</div>
@endif

@if($o->status !== 'selesai')
<div class="d-flex align-items-center gap-2 small mb-2"><div class="progress flex-grow-1" style="height:8px"><div class="progress-bar" id="soProgres" style="width:{{ $rows->count() ? round($dihitung / $rows->count() * 100) : 0 }}%"></div></div>
    <span class="text-nowrap"><span id="soDihitung">{{ $dihitung }}</span>/{{ $rows->count() }} dihitung</span></div>
@endif

@if($boleh['periksa'] || $boleh['setujui'] || $boleh['batal'])
<div class="d-flex flex-wrap gap-2 mb-2">
    @if($boleh['periksa'])
    <form method="POST" action="{{ route('stok-opname.ajukan', $o) }}" onsubmit="return confirm('Ajukan opname ini? Hitungan akan dikunci sampai disetujui.')">@csrf
        <button class="btn btn-sm btn-primary" data-uji="ajukan" @disabled($masalah)><i class="bi bi-send me-1"></i>Ajukan</button></form>
    @endif
    @if($boleh['setujui'])
    <form method="POST" action="{{ route('stok-opname.setujui', $o) }}" onsubmit="return confirm('Setujui opname {{ $o->nomor }}? Stok dan keuangan langsung disesuaikan dan opname dikunci.')">@csrf
        <button class="btn btn-sm btn-success" data-uji="setujui"><i class="bi bi-check2-circle me-1"></i>Setujui</button></form>
    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalTolak" data-uji="tolak"><i class="bi bi-x-lg me-1"></i>Tolak</button>
    @endif
    @if($boleh['batal'])
    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalBatal" data-uji="batal"><i class="bi bi-trash me-1"></i>Batalkan opname</button>
    @endif
</div>
@endif
@if($masalah)
<div class="alert alert-light border small py-2 mb-2" data-uji="masalah"><div class="fw-semibold mb-1"><i class="bi bi-list-check me-1"></i>Sebelum diajukan:</div>
    <ul class="mb-0 ps-3">@foreach(array_slice($masalah, 0, 8) as $m)<li>{{ $m }}</li>@endforeach @if(count($masalah) > 8)<li>… {{ count($masalah) - 8 }} lagi</li>@endif</ul></div>
@endif

<ul class="nav nav-pills nav-fill small mb-2" role="tablist">
    @if($o->status === 'selesai')<li class="nav-item"><button class="nav-link {{ $tabAwal === 'ringkasan' ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#tRingkasan" type="button" data-uji="tab-ringkasan">Ringkasan</button></li>@endif
    <li class="nav-item"><button class="nav-link {{ $tabAwal === 'hitung' ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#tHitung" type="button" data-uji="tab-hitung">Hitung</button></li>
    @if($lihatSistem)<li class="nav-item"><button class="nav-link {{ $tabAwal === 'selisih' ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#tSelisih" type="button" data-uji="tab-selisih">Selisih <span class="badge bg-secondary">{{ $selisihRows->count() }}</span></button></li>@endif
    <li class="nav-item"><button class="nav-link {{ $tabAwal === 'riwayat' ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#tRiwayat" type="button">Riwayat</button></li>
</ul>

<div class="tab-content">
@if($o->status === 'selesai')
@php $rg = $o->ringkasan ?? []; @endphp
<div class="tab-pane fade {{ $tabAwal === 'ringkasan' ? 'show active' : '' }}" id="tRingkasan" data-uji="ringkasan">
    <div class="row g-2 mb-2">
        @foreach([['Barang dihitung', $rg['dihitung'] ?? 0, ''], ['Cocok', $rg['cocok'] ?? 0, 'text-success'], ['Kurang', $rg['kurang'] ?? 0, 'text-danger'], ['Lebih', $rg['lebih'] ?? 0, 'text-primary'],
            ['Nilai kurang', $rp($rg['nilai_kurang'] ?? 0), 'text-danger'], ['Nilai lebih', $rp($rg['nilai_lebih'] ?? 0), 'text-primary'], ['Selisih bersih', $rp($rg['nilai_bersih'] ?? 0), ($rg['nilai_bersih'] ?? 0) < 0 ? 'text-danger' : ''], ['Akurasi stok', ($rg['akurasi'] ?? 0) . '%', 'fw-bold']] as [$l, $v, $c])
        <div class="col-6 col-md-3"><div class="card"><div class="card-body p-2"><div class="small text-muted">{{ $l }}</div><div class="fs-6 fw-semibold {{ $c }}">{{ $v }}</div></div></div></div>
        @endforeach
    </div>
    @if(! empty($rg['per_penyebab']))
    <div class="card mb-2"><div class="card-body p-2">
        <div class="fw-semibold small mb-1">Per penyebab (tercatat di keuangan, tanpa kas)</div>
        <table class="table table-sm mb-0 small"><tbody>
        @foreach($rg['per_penyebab'] as $k => $p)
            <tr><td>{{ \App\Services\StokOpnameService::PENYEBAB[$k]['label'] ?? $k }} <span class="text-muted">→ {{ \App\Services\StokOpnameService::PENYEBAB[$k]['nama'] ?? '' }}</span></td><td class="text-end">{{ $p['barang'] }} barang</td><td class="text-end fw-semibold">{{ $rp($p['nilai']) }}</td></tr>
        @endforeach
        </tbody></table>
    </div></div>
    @endif
    <div class="small text-muted">Disetujui {{ $o->penyetuju?->name }} · {{ $o->disetujui_at?->format('d/m/Y H:i') }}. Opname terkunci.</div>
</div>
@endif

<div class="tab-pane fade {{ $tabAwal === 'hitung' ? 'show active' : '' }}" id="tHitung">
    <div class="so-sticky">
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
            <input type="search" id="soScan" class="form-control" placeholder="{{ $boleh['hitung'] ? 'Scan barcode (Enter = +1) / cari barang' : 'Cari barang' }}" autocomplete="off" data-uji="scan">
        </div>
        <div class="d-flex gap-1 mt-1 small">
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 so-saring active" data-saring="semua">Semua</button>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 so-saring" data-saring="belum" data-uji="saring-belum">Belum dihitung</button>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 so-saring" data-saring="ulang">Hitung ulang</button>
        </div>
        <div id="soPesan" class="small mt-1" role="status"></div>
    </div>
    <div id="soDaftar">
    @foreach($rows as $r)
    <div class="so-baris {{ $r->minta_hitung_ulang ? 'ulang' : '' }}" data-baris="{{ $r->id }}" data-cari="{{ \Illuminate\Support\Str::lower($r->barang?->nama_item . ' ' . $r->barang?->kode_item . ' ' . $r->barang?->barcode) }}"
         data-dihitung="{{ $r->qty_fisik !== null ? 1 : 0 }}" data-ulang="{{ $r->minta_hitung_ulang ? 1 : 0 }}" data-uji="baris-{{ $r->barang?->kode_item }}">
        <div class="so-nama">
            <div class="fw-semibold text-truncate"><span class="so-tanda">@if($r->qty_fisik !== null)<i class="bi bi-check-circle-fill text-success"></i> @endif</span>{{ $r->barang?->nama_item }}</div>
            <div class="small text-muted">{{ $r->barang?->kode_item }} · {{ $r->satuan }}@if($r->barang?->category) · {{ $r->barang->category->nama_kategori }}@endif
                @if($r->minta_hitung_ulang)<span class="badge bg-warning text-dark">hitung ulang</span>@endif
                @if($r->hitung_ke > 1)<span class="badge bg-light text-dark border" title="Sudah dihitung {{ $r->hitung_ke }} kali; hitungan pertama {{ $ang($r->qty_fisik_awal) }}">×{{ $r->hitung_ke }}</span>@endif</div>
        </div>
        @if($lihatSistem)<div class="small text-muted so-angka" title="Stok sistem saat dihitung">sistem <span class="so-sistem">{{ $ang($r->qty_sistem ?? ($stokKini[$r->item_id] ?? 0)) }}</span></div>@endif
        @if($boleh['hitung'])
        <div class="so-kendali">
            <button type="button" class="btn btn-sm btn-outline-secondary so-kurang" aria-label="Kurangi 1">−</button>
            <input type="number" class="form-control form-control-sm so-fisik" min="0" step="{{ \App\Services\StokOpnameService::bolehDesimal($r->satuan) ? 'any' : '1' }}"
                   value="{{ $r->qty_fisik !== null ? (\App\Services\StokOpnameService::bolehDesimal($r->satuan) ? round($r->qty_fisik, 3) : (int) $r->qty_fisik) : '' }}" placeholder="fisik" inputmode="decimal" aria-label="Fisik {{ $r->barang?->nama_item }}" data-uji="fisik">
            <button type="button" class="btn btn-sm btn-outline-secondary so-tambah" aria-label="Tambah 1">+</button>
        </div>
        @else
        <div class="so-angka fw-semibold">{{ $r->qty_fisik !== null ? $ang($r->qty_fisik) : '–' }}</div>
        @endif
    </div>
    @endforeach
    </div>
</div>

@if($lihatSistem)
<div class="tab-pane fade {{ $tabAwal === 'selisih' ? 'show active' : '' }}" id="tSelisih">
    @if($selisihRows->isEmpty())
    <div class="text-center text-muted py-4">{{ $dihitung ? 'Semua barang yang sudah dihitung cocok dengan stok sistem.' : 'Belum ada barang yang dihitung.' }}</div>
    @else
    <form method="POST" action="{{ route('stok-opname.hitung-ulang', $o) }}" id="formUlang">@csrf</form>
    @if($boleh['periksa'])<div class="d-flex justify-content-end mb-1"><button form="formUlang" class="btn btn-sm btn-outline-warning" data-uji="minta-ulang"><i class="bi bi-arrow-repeat me-1"></i>Minta hitung ulang yang dicentang</button></div>@endif
    @foreach($selisihRows as $r)
    @php
        $s = (float) $r->selisih;
        $harga = $o->status === 'selesai' ? (float) $r->harga_pokok : app(\App\Services\StokService::class)->hargaPokokRata((int) $r->item_id, (int) $o->cabang_id);
        $nilai = $o->status === 'selesai' ? (float) $r->nilai_selisih : $s * $harga;
        $jmlRincian = round((float) $r->rincian->where('penyebab', '!=', 'lebih')->sum('qty'), 3);
        $lengkap = $s > 0 || abs($jmlRincian - abs($s)) < 0.0005;
    @endphp
    <div class="card mb-2" data-uji="selisih-{{ $r->barang?->kode_item }}"><div class="card-body p-2">
        <div class="d-flex gap-2 align-items-start">
            @if($boleh['periksa'])<input type="checkbox" class="form-check-input mt-1" form="formUlang" name="baris[]" value="{{ $r->id }}" aria-label="Hitung ulang {{ $r->barang?->nama_item }}">@endif
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold">{{ $r->barang?->nama_item }} @if($r->minta_hitung_ulang)<span class="badge bg-warning text-dark">hitung ulang</span>@endif</div>
                <div class="small">Sistem {{ $ang($r->qty_sistem) }} · Fisik {{ $ang($r->qty_fisik) }} {{ $r->satuan }} ·
                    <strong class="{{ $s < 0 ? 'text-danger' : 'text-primary' }}">{{ $s > 0 ? '+' : '' }}{{ $ang($s) }}</strong>
                    · <span class="{{ $nilai < 0 ? 'text-danger' : 'text-primary' }}">{{ $o->status === 'selesai' ? '' : '≈ ' }}{{ $rp($nilai) }}</span></div>
                @if($s > 0)
                <div class="small text-muted">Lebih → tercatat otomatis sebagai Koreksi Stok Masuk.</div>
                @else
                <div class="small mt-1">
                    @forelse($r->rincian as $x)
                    <span class="badge bg-light text-dark border me-1">{{ $x->penyebab_label }} {{ $ang($x->qty) }}@if($x->nilai !== null) · {{ $rp($x->nilai) }}@endif
                        @if($x->foto)<a href="{{ route('stok-opname.foto', [$o, $x->id]) }}" target="_blank" class="ms-1" data-uji="foto-link"><i class="bi bi-image"></i></a>@endif
                        @if($x->penyebab === 'rusak' && $o->status === 'selesai')@can('stok_opname.stok_rusak')<a href="{{ route('stok-opname.rusak', ['lokasi' => $o->cabang_id]) }}" class="ms-1" data-uji="ke-rusak">→ Stok Rusak</a>@endcan @endif</span>
                    @if($x->catatan)<span class="small text-muted me-2">{{ $x->catatan }}</span>@endif
                    @empty
                    <span class="text-danger"><i class="bi bi-exclamation-circle"></i> Penyebab belum diisi</span>
                    @endforelse
                    @if($r->rincian->isNotEmpty() && ! $lengkap)<span class="text-danger small">— jumlah penyebab {{ $ang($jmlRincian) }} belum sama dengan {{ $ang(abs($s)) }}</span>@endif
                </div>
                @endif
            </div>
            @if($boleh['periksa'] && $s < 0)
            <button type="button" class="btn btn-sm {{ $lengkap ? 'btn-outline-primary' : 'btn-primary' }} so-penyebab text-nowrap" data-baris="{{ $r->id }}" data-uji="isi-penyebab">Isi penyebab</button>
            @endif
        </div>
    </div></div>
    @endforeach
    @endif
</div>
@endif

<div class="tab-pane fade {{ $tabAwal === 'riwayat' ? 'show active' : '' }}" id="tRiwayat">
    <ul class="list-group list-group-flush small">
    @foreach($o->log as $l)
        <li class="list-group-item px-1"><span class="fw-semibold">{{ $l->aksi_label }}</span> · {{ $l->user?->name ?? 'Sistem' }} · <span class="text-muted">{{ $l->created_at?->format('d/m/Y H:i') }}</span>
            @if($l->catatan)<div class="text-muted">{{ $l->catatan }}</div>@endif</li>
    @endforeach
    </ul>
</div>
</div>

{{-- Modal: isi penyebab --}}
@if($boleh['periksa'])
<div class="modal fade" id="modalPenyebab" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content">
    <form method="POST" enctype="multipart/form-data" id="formPenyebab">@csrf
    <div class="modal-header py-2"><h6 class="modal-title fw-bold">Penyebab selisih <x-tooltip key="stok_opname.penyebab" /></h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
    <div class="modal-body">
        <div class="fw-semibold" id="pnNama"></div>
        <div class="small mb-2">Kurang <strong id="pnSelisih"></strong> · sisa belum diberi penyebab: <strong id="pnSisa"></strong></div>
        <div id="pnBaris" class="d-flex flex-column gap-2"></div>
        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="pnTambah"><i class="bi bi-plus"></i> Tambah penyebab</button>
        <div class="small text-muted mt-2">Foto wajib untuk barang <strong>rusak</strong>, dan untuk barang <strong>hilang</strong> bila nilainya ≥ {{ $rp($batasFoto) }}.</div>
    </div>
    <div class="modal-footer py-2"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-sm btn-primary" data-uji="simpan-penyebab">Simpan</button></div>
    </form>
</div></div></div>
@endif

@if($boleh['setujui'])
<div class="modal fade" id="modalTolak" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('stok-opname.tolak', $o) }}">@csrf
    <div class="modal-header py-2"><h6 class="modal-title fw-bold">Tolak opname {{ $o->nomor }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
    <div class="modal-body"><label class="form-label small" for="alasanTolak">Alasan (opname kembali ke tahap hitung)</label>
        <textarea name="alasan" id="alasanTolak" class="form-control" rows="3" minlength="5" maxlength="500" required data-uji="alasan-tolak-isi"></textarea></div>
    <div class="modal-footer py-2"><button class="btn btn-sm btn-danger" data-uji="tolak-kirim">Tolak</button></div>
    </form>
</div></div></div>
@endif
@if($boleh['batal'])
<div class="modal fade" id="modalBatal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('stok-opname.batal', $o) }}">@csrf
    <div class="modal-header py-2"><h6 class="modal-title fw-bold">Batalkan opname {{ $o->nomor }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
    <div class="modal-body"><label class="form-label small" for="alasanBatal">Alasan pembatalan (stok tidak berubah)</label>
        <textarea name="alasan" id="alasanBatal" class="form-control" rows="3" minlength="5" maxlength="500" required data-uji="alasan-batal-isi"></textarea></div>
    <div class="modal-footer py-2"><button class="btn btn-sm btn-secondary" data-uji="batal-kirim">Batalkan opname</button></div>
    </form>
</div></div></div>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const URL_HITUNG = @json(route('stok-opname.hitung', $o));
    const CSRF = @json(csrf_token());
    const BOLEH_HITUNG = @json($boleh['hitung']);
    const LIHAT = @json($lihatSistem);
    const TOTAL = {{ $rows->count() }};
    const pesan = document.getElementById('soPesan');
    const kabar = (t, ok = true) => { pesan.className = 'small mt-1 ' + (ok ? 'text-success' : 'text-danger'); pesan.textContent = t; };
    const fmt = (n) => (n === null || n === undefined) ? '–' : Number(n).toLocaleString('id-ID', { maximumFractionDigits: 3 });

    async function kirim(data) {
        const r = await fetch(URL_HITUNG, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data) });
        let j = {}; try { j = await r.json(); } catch (e) { j = { ok: false, pesan: 'Gagal menyimpan (' + r.status + '). Muat ulang halaman.' }; }
        if (!r.ok || !j.ok) { kabar(j.pesan || j.message || 'Gagal menyimpan.', false); return null; }
        terapkan(j);
        return j;
    }
    function terapkan(j) {
        const b = j.baris, el = document.querySelector(`.so-baris[data-baris="${b.id}"]`);
        if (!el) return;
        const inp = el.querySelector('.so-fisik'); if (inp) inp.value = b.fisik;
        el.dataset.dihitung = 1; el.dataset.ulang = 0; el.classList.remove('ulang');
        el.querySelector('.so-tanda').innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> ';
        if (LIHAT && el.querySelector('.so-sistem')) el.querySelector('.so-sistem').textContent = fmt(b.sistem);
        el.classList.remove('baru'); void el.offsetWidth; el.classList.add('baru');
        document.getElementById('soDihitung') && (document.getElementById('soDihitung').textContent = j.dihitung);
        document.getElementById('soProgres') && (document.getElementById('soProgres').style.width = Math.round(j.dihitung / TOTAL * 100) + '%');
        kabar(`${b.nama}: ${b.fisik_teks}` + (LIHAT && b.selisih ? ` (selisih ${b.selisih > 0 ? '+' : ''}${fmt(b.selisih)})` : '') + ' — tersimpan');
    }

    if (BOLEH_HITUNG) {
        document.querySelectorAll('.so-baris').forEach((el) => {
            const id = +el.dataset.baris, inp = el.querySelector('.so-fisik');
            inp?.addEventListener('change', () => { if (inp.value === '') return; kirim({ baris: id, qty: inp.value }); });
            el.querySelector('.so-tambah')?.addEventListener('click', () => kirim({ baris: id, qty: 1, tambah: 1 }));
            el.querySelector('.so-kurang')?.addEventListener('click', () => { const v = Number(inp.value || 0); if (v <= 0) return; kirim({ baris: id, qty: Math.max(0, v - 1) }); });
        });
    }

    // cari & scan: ketik = saring daftar; Enter = scan barcode (+1)
    const scan = document.getElementById('soScan');
    let saring = 'semua';
    function tampil() {
        const q = scan.value.trim().toLowerCase();
        document.querySelectorAll('.so-baris').forEach((el) => {
            const cocok = (!q || el.dataset.cari.includes(q)) && (saring === 'semua' || (saring === 'belum' && el.dataset.dihitung === '0') || (saring === 'ulang' && el.dataset.ulang === '1'));
            el.style.display = cocok ? '' : 'none';
        });
    }
    scan.addEventListener('input', tampil);
    scan.addEventListener('keydown', async (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const kode = scan.value.trim();
        if (!kode || !BOLEH_HITUNG) return;
        const j = await kirim({ kode, tambah: 1 });
        scan.value = ''; tampil();
        if (j) document.querySelector(`.so-baris[data-baris="${j.baris.id}"]`)?.scrollIntoView({ block: 'center' });
        scan.focus();
    });
    document.querySelectorAll('.so-saring').forEach((b) => b.addEventListener('click', () => {
        document.querySelectorAll('.so-saring').forEach((x) => x.classList.remove('active')); b.classList.add('active'); saring = b.dataset.saring; tampil();
    }));

    // modal penyebab
    const DATA = @json($dataPenyebab);
    const PENYEBAB = @json($labelPenyebab);
    const URL_RINCIAN = @json(route('stok-opname.rincian', [$o, 0]));
    const modalEl = document.getElementById('modalPenyebab');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        const wadah = document.getElementById('pnBaris');
        let aktif = null, n = 0;
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        function hitungSisa() {
            let t = 0; wadah.querySelectorAll('.pn-qty').forEach((i) => { t += Number(i.value || 0); });
            const sisa = Math.round((aktif.kurang - t) * 1000) / 1000;
            const el = document.getElementById('pnSisa'); el.textContent = fmt(sisa) + ' ' + aktif.satuan; el.className = sisa === 0 ? 'text-success' : 'text-danger';
        }
        function tambahBaris(r = {}) {
            const i = n++;
            wadah.insertAdjacentHTML('beforeend', `<div class="border rounded p-2 pn-baris">
                <div class="d-flex gap-2"><select name="r[${i}][penyebab]" class="form-select form-select-sm" required data-uji="pn-penyebab">${Object.entries(PENYEBAB).map(([k, l]) => `<option value="${k}" ${r.penyebab === k ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select>
                <input type="number" name="r[${i}][qty]" class="form-control form-control-sm pn-qty" style="width:100px" min="0" step="${aktif.desimal ? 'any' : '1'}" value="${r.qty ?? ''}" placeholder="jumlah" required data-uji="pn-qty">
                <button type="button" class="btn btn-sm btn-outline-danger pn-hapus" aria-label="Hapus">×</button></div>
                <input type="text" name="r[${i}][catatan]" class="form-control form-control-sm mt-1" maxlength="255" value="${esc(r.catatan || '')}" placeholder="catatan (opsional)">
                <div class="mt-1 small">${r.foto ? `<input type="hidden" name="r[${i}][foto_dari]" value="${r.id}"><span class="text-success"><i class="bi bi-image"></i> foto tersimpan</span> · ganti: ` : 'Foto: '}
                <input type="file" name="r[${i}][foto]" accept="image/jpeg,image/png,image/webp" capture="environment" class="form-control form-control-sm d-inline-block" style="max-width:240px" data-uji="pn-foto"></div></div>`);
            hitungSisa();
        }
        wadah.addEventListener('input', hitungSisa);
        wadah.addEventListener('click', (e) => { if (e.target.classList.contains('pn-hapus')) { e.target.closest('.pn-baris').remove(); hitungSisa(); } });
        document.getElementById('pnTambah').addEventListener('click', () => tambahBaris());
        document.querySelectorAll('.so-penyebab').forEach((b) => b.addEventListener('click', () => {
            aktif = DATA[b.dataset.baris]; if (!aktif) return;
            document.getElementById('formPenyebab').action = URL_RINCIAN.replace(/\/0$/, '/' + b.dataset.baris);
            document.getElementById('pnNama').textContent = aktif.nama;
            document.getElementById('pnSelisih').textContent = fmt(aktif.kurang) + ' ' + aktif.satuan;
            wadah.innerHTML = ''; n = 0;
            (aktif.rincian.length ? aktif.rincian : [{ qty: aktif.kurang }]).forEach((r) => tambahBaris(r));
            modal.show();
        }));
    }
})();
</script>
@endpush
