{{-- Partial: dipakai index.blade.php (dan bisa direuse print.blade.php) untuk 1 kelompok tanggal --}}
<div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
            <tr>
                <th>Waktu</th>
                <th>Kategori</th>
                <th>Keterangan</th>
                <th class="text-end">Jumlah</th>
            </tr>
        </thead>
        <tbody>
        @foreach($transaksis as $t)
        <tr>
            <td class="small">{{ $t->created_at?->format('H:i') }}</td>
            <td class="small">{{ $t->label_kategori_pengeluaran }}</td>
            <td class="small">{{ $t->keterangan }}</td>
            <td class="text-end small fw-semibold">Rp {{ number_format($t->jumlah, 0, ',', '.') }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
