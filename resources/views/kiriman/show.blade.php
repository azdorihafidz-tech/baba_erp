@extends('layouts.app')

@section('title', 'Kiriman ' . $order->nomor_order)

@section('content')
@php
    $angka = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $menunggu = $order->status_kirim === 'menunggu';
@endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-truck me-2 text-success"></i>Kiriman {{ $order->nomor_order }}
        <span class="ms-2">@include('kiriman._status', ['s' => $order->status_kirim])</span></h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="kiriman-cabang" />
        <a href="{{ route('kiriman.index', ['arah' => \App\Support\CabangAktif::bolehAkses($order->outlet_tujuan_id) ? 'masuk' : 'keluar']) }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card mb-3"><div class="card-body">
            <div class="row small g-2">
                <div class="col-6 col-md-3"><div class="text-muted">Dari</div><div class="fw-semibold">{{ $order->cabang?->nama_cabang }}</div></div>
                <div class="col-6 col-md-3"><div class="text-muted">Ke</div><div class="fw-semibold">{{ $order->outletTujuan?->nama_cabang }}</div></div>
                <div class="col-6 col-md-3"><div class="text-muted">Dikirim</div><div>{{ $order->created_at->format('d/m/Y H:i') }}<br><span class="text-muted">{{ $order->kasir?->name }}</span></div></div>
                <div class="col-6 col-md-3"><div class="text-muted">{{ $order->status_kirim === 'ditolak' ? 'Ditolak' : 'Diterima' }}</div>
                    <div>@if($order->diterima_at){{ $order->diterima_at->format('d/m/Y H:i') }}<br><span class="text-muted">{{ $order->diterimaOleh?->name }}</span>@else - @endif</div></div>
            </div>
            @if($order->alasan_tolak)
            <div class="alert alert-secondary small mt-3 mb-0">Alasan {{ $order->status_kirim === 'ditolak' ? 'ditolak' : 'dibatalkan' }}: {{ $order->alasan_tolak }}</div>
            @endif
        </div></div>

        <form method="POST" action="{{ route('kiriman.terima', $order) }}" id="formTerima">
            @csrf
            <div class="card">
                <div class="card-header bg-white small fw-semibold">Barang</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr>
                            <th>Produk</th><th class="text-end">{{ $menunggu || $order->status_kirim !== 'diterima' ? 'Dikirim' : 'Diterima' }}</th>
                            @if($bisaTerima)<th style="width:150px">Jumlah sampai <x-tooltip key="kiriman.qty_diterima" /></th>@endif
                            <th class="text-end">Harga</th><th class="text-end">Subtotal</th>
                        </tr></thead>
                        <tbody>
                        @foreach($order->items as $oi)
                        @php $rows = $order->kirimanItems->where('order_item_id', $oi->id); @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $oi->nama_item }}</div>
                                <div class="small text-muted">
                                    @if($order->status_kirim === 'diterima')
                                    {{ $rows->map(fn ($r) => (($r->itemTujuan ?? $r->item)?->nama_item ?? '?') . ': dikirim ' . $angka($r->qty_kirim) . ', diterima ' . $angka($r->qty_terima) . ' ' . ($r->itemTujuan ?? $r->item)?->satuan)->implode('; ') }}
                                    @else
                                    Masuk stok cabang sebagai: {{ $rows->map(fn ($r) => ($r->item?->nama_item ?? '?') . ' ' . $angka($r->qty_kirim) . ' ' . $r->item?->satuan)->implode(', ') }}
                                    @endif
                                </div>
                            </td>
                            <td class="text-end">{{ $angka($oi->qty) }} {{ $oi->satuan }}</td>
                            @if($bisaTerima)
                            <td><input type="number" step="0.001" min="0" max="{{ (float) $oi->qty }}" name="qty_diterima[{{ $oi->id }}]" value="{{ old('qty_diterima.' . $oi->id, (float) $oi->qty) }}" class="form-control form-control-sm text-end qtyTerima" data-harga="{{ (float) $oi->harga_satuan }}"></td>
                            @endif
                            <td class="text-end">{{ $rp($oi->harga_satuan) }}</td>
                            <td class="text-end subtotalTerima">{{ $rp($oi->total_harga) }}</td>
                        </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                            @if((float) $order->diskon > 0)
                            <tr><td colspan="{{ $bisaTerima ? 4 : 3 }}" class="text-end small">Diskon</td><td class="text-end small">- {{ $rp($order->diskon) }}</td></tr>
                            @endif
                            <tr class="fw-semibold"><td colspan="{{ $bisaTerima ? 4 : 3 }}" class="text-end">Total {{ $menunggu ? 'kiriman' : 'dibayar cabang' }}</td><td class="text-end" id="totalTerima">{{ $rp($order->total_bayar) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </form>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card"><div class="card-body">
            @if($bisaTerima)
                <h6 class="fw-bold">Terima kiriman</h6>
                <p class="small text-muted">Cek barang yang datang. Kalau ada yang kurang, ubah <strong>Jumlah sampai</strong> — sisanya kembali ke stok pengirim.</p>
                @php $kasBawaan = $kasList->firstWhere('default_untuk', 'tunai') ?? $kasList->first(); @endphp
                <label class="form-label small fw-semibold mb-1">Bayar dari kas <x-tooltip key="kiriman.kas" /></label>
                <select name="kas_id" form="formTerima" class="form-select form-select-sm mb-2" id="kasTerima" @disabled($kasList->isEmpty())>
                    @foreach($kasList as $k)
                    <option value="{{ $k->id }}" data-saldo="{{ (float) $k->saldo_sekarang }}" @selected(old('kas_id', $kasBawaan?->id) == $k->id)>{{ $k->nama_kas }} (saldo {{ $rp($k->saldo_sekarang) }})</option>
                    @endforeach
                </select>
                <div class="alert alert-warning small py-2 d-none" id="peringatanKas"><i class="bi bi-exclamation-triangle me-1"></i>Saldo kas lebih kecil dari nilai kiriman. Kiriman tetap bisa diterima, saldo kas akan minus.</div>
                @if($kasList->isEmpty())
                <div class="alert alert-danger small py-2">Cabang ini belum punya Kas aktif — buat dulu di menu Kas sebelum menerima.</div>
                @endif
                @if($barangBaru->isNotEmpty())
                <div class="alert alert-info small py-2"><i class="bi bi-info-circle me-1"></i><strong>Barang baru akan dibuat di cabang ini</strong> (belum ada barang dengan Kode Item yang sama):
                    <ul class="mb-0 ps-3">@foreach($barangBaru as $b)<li>{{ $b->nama_item }} <span class="text-muted">({{ $b->kode_item }})</span></li>@endforeach</ul>
                    <div class="mt-1">Kalau sebenarnya cabang sudah punya barang ini dengan kode lain, samakan Kode Item-nya dulu sebelum Terima.</div>
                </div>
                @endif
                <button type="submit" form="formTerima" class="btn btn-success w-100 mb-3" @disabled($kasList->isEmpty())
                    onclick="return confirm('Terima kiriman ini? Stok masuk ke cabang dan kas terpotong sesuai total.')">
                    <i class="bi bi-check2-circle me-1"></i>Terima
                </button>

                <form method="POST" action="{{ route('kiriman.tolak', $order) }}" onsubmit="return confirm('Tolak kiriman ini? Semua barang kembali ke stok pengirim.')">
                    @csrf
                    <label class="form-label small fw-semibold">Tolak kiriman <x-tooltip key="kiriman.tolak" /></label>
                    <textarea name="alasan" rows="2" maxlength="500" required class="form-control form-control-sm mb-2 @error('alasan') is-invalid @enderror" placeholder="Alasan, mis. salah kirim"></textarea>
                    @error('alasan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <button class="btn btn-outline-danger btn-sm w-100">Tolak</button>
                </form>
            @elseif($menunggu)
                <div class="small text-muted mb-3"><i class="bi bi-hourglass-split me-1"></i>Menunggu {{ $order->outletTujuan?->nama_cabang }} menekan <strong>Terima</strong>. Stok sudah keluar dari {{ $order->cabang?->nama_cabang }}; uang dan pendapatan tercatat saat diterima.</div>
            @elseif($order->status_kirim === 'diterima')
                <div class="text-success small"><i class="bi bi-check-circle-fill me-1"></i>Sudah diterima. Stok masuk ke {{ $order->outletTujuan?->nama_cabang }} dan kasnya terpotong {{ $rp($order->total_bayar) }}.</div>
                <div class="small text-muted mt-2">Ada barang basi/rusak/kelebihan? Kembalikan lewat <strong>Retur</strong>.</div>
                @if(\App\Support\CabangAktif::bolehAkses($order->outlet_tujuan_id))
                @if(\Illuminate\Support\Facades\Route::has('retur-cabang.create'))
                @can('retur.create')
                <a href="{{ route('retur-cabang.create', $order) }}" class="btn btn-outline-warning btn-sm w-100 mt-2"><i class="bi bi-arrow-return-left me-1"></i>Buat Retur</a>
                @endcan
                @endif
                @endif
            @else
                <div class="small text-muted">Kiriman ini {{ $order->status_kirim }} — barang sudah kembali ke stok {{ $order->cabang?->nama_cabang }}.</div>
            @endif

            @if($bisaBatal)
            <hr>
            <form method="POST" action="{{ route('kiriman.batalkan', $order) }}" onsubmit="return confirm('Batalkan kiriman ini? Barang kembali ke stok.')">
                @csrf
                <label class="form-label small fw-semibold">Batalkan kiriman (pengirim)</label>
                <textarea name="alasan" rows="2" maxlength="500" required class="form-control form-control-sm mb-2" placeholder="Alasan pembatalan"></textarea>
                <button class="btn btn-outline-danger btn-sm w-100">Batalkan Kiriman</button>
            </form>
            @endif

            <a href="{{ route('penjualan.show', $order) }}" class="btn btn-link btn-sm px-0 mt-2">Lihat order & struk</a>
        </div></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const inputs = document.querySelectorAll('.qtyTerima');
    if (!inputs.length) return;
    const totalKirim = {{ (float) $order->total_harga }};
    const diskon = {{ (float) $order->diskon }};
    const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');
    function hitung() {
        let total = 0;
        inputs.forEach(i => {
            const sub = (parseFloat(i.value) || 0) * parseFloat(i.dataset.harga);
            total += sub;
            i.closest('tr').querySelector('.subtotalTerima').textContent = rp(sub);
        });
        const net = totalKirim > 0 ? total * Math.max(0, totalKirim - diskon) / totalKirim : total;
        document.getElementById('totalTerima').textContent = rp(net);
        const kas = document.getElementById('kasTerima');
        const opt = kas?.selectedOptions[0];
        document.getElementById('peringatanKas')?.classList.toggle('d-none', !opt || parseFloat(opt.dataset.saldo) >= net);
    }
    inputs.forEach(i => i.addEventListener('input', hitung));
    document.getElementById('kasTerima')?.addEventListener('change', hitung);
    hitung();
})();
</script>
@endpush
