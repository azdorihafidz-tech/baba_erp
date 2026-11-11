{{--
    FIFO Batch Detail (CLAUDE.md 4.39) — batch stok yang MASIH ADA sisanya,
    urut tertua → terbaru (urutan terpakai saat penjualan). Batch habis tidak
    ditampilkan. Tampilan mengikuti FIFO Batch Detail di Dashboard Stok.
    Props: $batches (Collection<StockBatch> dgn relasi lokasi), $item (Item).
--}}
@props(['batches', 'item'])
@php
    $asal = ['purchase_order' => 'PO', 'stock_transfer' => 'Transfer', 'adjustment_masuk' => 'Adjustment', 'adjustment' => 'Adjustment', 'stok_awal' => 'Stok awal'];
    $totalQty = $batches->sum(fn ($b) => (float) $b->qty_sisa);
    $totalNilai = $batches->sum(fn ($b) => (float) $b->qty_sisa * (float) $b->harga_beli_per_unit);
@endphp
<div class="card mb-3">
    <div class="card-header fw-semibold d-flex flex-wrap align-items-center gap-2">
        <span><i class="bi bi-stack text-primary me-1"></i>FIFO Batch Detail</span>
        <span class="badge bg-primary-subtle text-primary">{{ $batches->count() }} batch</span>
        <span class="text-muted small fw-normal ms-auto">Urutan terpakai: tertua → terbaru <x-tooltip key="master_bahan_baku.fifo_batch" /></span>
    </div>
    @if($batches->isEmpty())
        <div class="card-body text-muted small">
            Stok habis — belum ada batch yang tersisa. Harga di resep memakai harga PO terakhir
            @if($item->harga_po_terakhir !== null)(Rp {{ number_format((float) $item->harga_po_terakhir, 0, ',', '.') }})@else(belum ada PO, memakai Harga Standar)@endif.
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0" style="font-size:0.82rem">
                <thead class="table-light">
                    <tr>
                        <th>Batch</th><th>Tanggal Masuk</th><th>Umur</th>
                        <th class="text-end">Qty Awal</th><th class="text-end">Qty Sisa</th>
                        <th class="text-end">Harga/{{ $item->satuan }}</th><th class="text-end">Nilai</th><th>Asal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($batches as $i => $b)
                    @php $persen = (float) $b->qty_awal > 0 ? min(100, round((float) $b->qty_sisa / (float) $b->qty_awal * 100)) : 0; @endphp
                    <tr class="{{ $i === 0 ? 'table-warning' : '' }}">
                        <td>
                            <span class="badge bg-secondary">#{{ $b->id }}</span>
                            @if($i === 0)<span class="badge bg-warning text-dark">↑ NEXT</span>@endif
                        </td>
                        <td>{{ \Carbon\Carbon::parse($b->tanggal_masuk)->format('d/m/Y') }}</td>
                        <td class="text-muted">{{ (int) \Carbon\Carbon::parse($b->tanggal_masuk)->diffInDays(now()) }} hari</td>
                        <td class="text-end">{{ fmt_qty($b->qty_awal) }}</td>
                        <td class="text-end fw-semibold">
                            {{ fmt_qty($b->qty_sisa) }}
                            <div class="progress mt-1" style="height:3px"><div class="progress-bar bg-success" style="width:{{ $persen }}%"></div></div>
                        </td>
                        <td class="text-end">Rp {{ number_format((float) $b->harga_beli_per_unit, 0, ',', '.') }}</td>
                        <td class="text-end fw-semibold">Rp {{ number_format((float) $b->qty_sisa * (float) $b->harga_beli_per_unit, 0, ',', '.') }}</td>
                        <td class="small text-muted">{{ $asal[$b->referensi_type] ?? ($b->referensi_type ?: '—') }}@if(! $item->cabang_id && $b->lokasi) · {{ $b->lokasi->nama_cabang }}@endif</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td colspan="4" class="text-end">Total</td>
                        <td class="text-end">{{ fmt_qty($totalQty) }}</td>
                        <td></td>
                        <td class="text-end">Rp {{ number_format($totalNilai, 0, ',', '.') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="card-body py-2 small text-muted">
            Batch bertanda <strong>↑ NEXT</strong> terpakai duluan; harganya dipakai sebagai harga bahan di resep Produk Jual.
        </div>
    @endif
</div>
