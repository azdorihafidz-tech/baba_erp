{{-- Partial: dipakai index.blade.php (dan direuse print.blade.php) untuk 1 kelompok tanggal --}}
<div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
            <tr>
                <th>Waktu</th>
                <th>No. Order</th>
                <th>Item</th>
                <th class="text-end">Qty</th>
                <th class="text-end">HPP</th>
                <th>Kasir</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $r)
        <tr>
            <td class="small">{{ \Carbon\Carbon::parse($r->order_created_at)->format('H:i') }}</td>
            <td class="small">
                <a href="{{ route('penjualan.show', $r->order_id) }}">{{ $r->nomor_order }}</a>
            </td>
            <td class="small">{{ $r->nama_item }}</td>
            <td class="text-end small">{{ rtrim(rtrim(number_format($r->qty, 3, ',', '.'), '0'), ',') }} {{ $r->satuan }}</td>
            <td class="text-end small fw-semibold">Rp {{ number_format($r->hpp, 0, ',', '.') }}</td>
            <td class="small">{{ $r->kasir_nama ?? '-' }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
