{{--
    Modal cheat sheet Kode Akun COA — murni referensi visual, tidak
    mempengaruhi field form manapun. Data hardcoded (skenario umum bisnis
    Berkah Mulyo), dipakai bareng oleh form Tambah/Edit Transaksi Keuangan.
--}}
@php
$coaCheatSheet = [
    ['skenario' => 'Terima uang dari pelanggan jasa giling', 'kategori' => 'Jasa Giling', 'kode' => '4-1101'],
    ['skenario' => 'Jual produk jadi (bakso/sosis/tempura)', 'kategori' => 'Penjualan Produk', 'kode' => '4-1102'],
    ['skenario' => 'Bayar sewa gedung bulanan', 'kategori' => 'Sewa Gedung', 'kode' => '6-1102'],
    ['skenario' => 'Bayar gaji karyawan', 'kategori' => 'Gaji Karyawan', 'kode' => '6-1101'],
    ['skenario' => 'Bayar listrik / air / pulsa', 'kategori' => 'Listrik & Air', 'kode' => '6-1103'],
    ['skenario' => 'Beli tepung tapioka / daging (bahan baku produksi)', 'kategori' => 'Pembelian Bahan Baku', 'kode' => '5-1101'],
    ['skenario' => 'Beli bumbu (masako, lada) & kemasan/plastik/label', 'kategori' => 'Pembelian Bahan Baku', 'kode' => '5-1102'],
    ['skenario' => 'Beli mesin bakso baru > Rp 500rb', 'kategori' => 'Pembelian Aset', 'kode' => '1-2101 (ASET!)'],
    ['skenario' => 'Beli sapu/kemoceng/tong air (peralatan kecil)', 'kategori' => 'Biaya Operasional', 'kode' => '6-1106'],
    ['skenario' => 'Beli baterai/lampu/ATK (perlengkapan kantor)', 'kategori' => 'Biaya Operasional', 'kode' => '6-1106'],
    ['skenario' => 'Beli sarung tangan/masker produksi', 'kategori' => 'Biaya Operasional', 'kode' => '6-1106'],
    ['skenario' => 'Isi bensin motor operasional', 'kategori' => 'Biaya Operasional', 'kode' => '6-1302'],
    ['skenario' => 'Bayar ongkos kirim bahan/barang', 'kategori' => 'Biaya Operasional', 'kode' => '6-1302'],
    ['skenario' => 'Konsumsi karyawan (nasi bungkus, aqua)', 'kategori' => 'Biaya Operasional', 'kode' => '6-1303'],
    ['skenario' => 'Servis / reparasi mesin giling', 'kategori' => 'Perawatan Aset', 'kode' => '6-1105'],
    ['skenario' => 'Kerugian stok (susut/rusak/hilang)', 'kategori' => 'Beban Kerugian Stok', 'kode' => '6-1401'],
    ['skenario' => 'Depresiasi bulanan (otomatis via sistem)', 'kategori' => 'Penyusutan Aset', 'kode' => '6-1104'],
    ['skenario' => 'Terima bunga tabungan bank', 'kategori' => 'Pemasukan Lainnya', 'kode' => '7-1101'],
    ['skenario' => 'Bayar cicilan pinjaman bank', 'kategori' => '—', 'kode' => '8-1102 (bunga) + 2-2101 (pokok)'],
    ['skenario' => 'Jual aset lama untung dari harga buku', 'kategori' => 'Pemasukan Lainnya', 'kode' => '7-1102'],
    ['skenario' => 'Jual aset lama rugi dari harga buku', 'kategori' => 'Biaya Operasional', 'kode' => '8-1101'],
    ['skenario' => 'Setoran modal Owner ke sistem', 'kategori' => '—', 'kode' => '3-1101 (Modal Owner)'],
    ['skenario' => 'Prive Owner (ambil uang untuk pribadi)', 'kategori' => '—', 'kode' => '3-1102 (Prive Owner)'],
    ['skenario' => 'Terima pembayaran piutang pelanggan lama', 'kategori' => '—', 'kode' => '1-1201 (Piutang Usaha, bukan pendapatan baru)'],
    ['skenario' => 'Bayar pajak usaha (PPh/PPN)', 'kategori' => 'Biaya Operasional', 'kode' => '6-1999 (atau 2-1103 kalau melunasi hutang pajak)'],
    ['skenario' => 'Transfer HO ke Cabang untuk operasional', 'kategori' => 'Transfer / Perpindahan Dana', 'kode' => 'BUKAN transaksi akuntansi (internal transfer)'],
    ['skenario' => 'Pindah kas tunai ke bank (cabang sama)', 'kategori' => 'Transfer / Perpindahan Dana', 'kode' => 'BUKAN transaksi akuntansi (internal transfer)'],
    ['skenario' => 'Bayar marketing / promosi', 'kategori' => 'Lainnya', 'kode' => '6-1999'],
];
@endphp
<div class="modal fade" id="coaCheatSheetModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-journal-text me-2 text-primary"></i>Cheat Sheet Kode Akun COA</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">Referensi visual saja — tidak mempengaruhi input form. Kategori simple yang Anda pilih di form sudah otomatis dipetakan ke kode akun COA di belakang layar.</p>
                <input type="text" id="coaCheatSheetSearch" class="form-control form-control-sm mb-3"
                    placeholder="Cari skenario... (contoh: bensin, gaji, mesin)">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" id="coaCheatSheetTable">
                        <thead class="table-light">
                            <tr>
                                <th>Skenario</th>
                                <th>Kategori Simple</th>
                                <th>Kode Akun COA</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($coaCheatSheet as $row)
                            <tr>
                                <td>{{ $row['skenario'] }}</td>
                                <td><span class="text-muted">{{ $row['kategori'] }}</span></td>
                                <td><code style="font-size:0.78rem">{{ $row['kode'] }}</code></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div id="coaCheatSheetEmpty" class="text-center text-muted py-4" style="display:none">
                        <i class="bi bi-search me-1"></i>Tidak ada skenario yang cocok.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('coaCheatSheetSearch');
    if (!searchInput) return;
    searchInput.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        const rows = document.querySelectorAll('#coaCheatSheetTable tbody tr');
        let visibleCount = 0;
        rows.forEach(function (row) {
            const match = row.textContent.toLowerCase().includes(q);
            row.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });
        document.getElementById('coaCheatSheetEmpty').style.display = visibleCount === 0 ? '' : 'none';
    });
});
</script>
@endpush
@endonce
