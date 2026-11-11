{{-- Partial: dipakai index.blade.php (dan direuse print.blade.php) untuk 1 kelompok tanggal --}}
<div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
            <tr>
                <th>Waktu</th>
                <th>No. Order</th>
                <th>Kategori</th>
                <th>Item</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Omzet</th>
                <th class="text-end">HPP</th>
                <th class="text-end">Untung</th>
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
            <td class="small">
                @php $badgeKategori = ['bahan_baku' => 'bg-primary-subtle text-primary', 'kemasan' => 'bg-warning-subtle text-warning', 'produk_jadi' => 'bg-success-subtle text-success', 'jasa' => 'bg-info-subtle text-info']; @endphp
                <span class="badge {{ $badgeKategori[$r->kategori] ?? 'bg-secondary-subtle text-secondary' }}">{{ ucwords(str_replace('_', ' ', $r->kategori)) }}</span>
            </td>
            <td class="small">{{ $r->nama_item }}</td>
            <td class="text-end small">{{ rtrim(rtrim(number_format($r->qty, 3, ',', '.'), '0'), ',') }} {{ $r->satuan }}</td>
            <td class="text-end small">Rp {{ number_format($r->omzet, 0, ',', '.') }}</td>
            <td class="text-end small">Rp {{ number_format($r->hpp, 0, ',', '.') }}</td>
            <td class="text-end small fw-semibold {{ $r->untung >= 0 ? 'text-success' : 'text-danger' }}">Rp {{ number_format($r->untung, 0, ',', '.') }}</td>
            <td class="small">{{ $r->kasir_nama ?? '-' }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
