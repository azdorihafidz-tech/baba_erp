{{-- Partial: dipakai index.blade.php (dan bisa direuse print.blade.php) untuk 1 kelompok tanggal --}}
<div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
            <tr>
                <th>Waktu</th>
                <th>No. Order</th>
                <th>Pelanggan</th>
                <th>Kasir</th>
                <th>Tipe Bayar</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
        @foreach($orders as $o)
        <tr>
            <td class="small">{{ $o->created_at?->format('H:i') }}</td>
            <td class="small">
                <a href="{{ route('penjualan.show', $o) }}">{{ $o->nomor_order }}</a>
            </td>
            <td class="small">{{ $o->nama_pelanggan ?? $o->pelanggan?->nama_pelanggan ?? 'Umum' }}</td>
            <td class="small">{{ $o->kasir?->name ?? '-' }}</td>
            <td class="small"><span class="badge bg-light text-dark border">{{ $o->tipe_pembayaran?->label() }}</span></td>
            <td class="text-end small fw-semibold">Rp {{ number_format($o->total_bayar, 0, ',', '.') }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
