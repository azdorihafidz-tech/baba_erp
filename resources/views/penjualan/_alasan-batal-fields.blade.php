{{-- Isian alasan pembatalan — dipakai modal Batalkan di Detail Order & Riwayat Penjualan. --}}
<p class="small text-muted">Stok bahan dan uang di kas dikembalikan otomatis setelah order dibatalkan.</p>
<div class="mb-3">
    <label class="form-label">Kategori Alasan <span class="text-danger">*</span></label>
    <select name="alasan_pembatalan_kategori" class="form-select" required>
        <option value="">-- Pilih Alasan --</option>
        <option value="Pengurangan Item">Pengurangan Item</option>
        <option value="Salah Input Kasir">Salah Input Kasir</option>
        <option value="Pelanggan Ganti Menu">Pelanggan Ganti Menu</option>
        <option value="Pelanggan Batal Datang">Pelanggan Batal Datang</option>
        <option value="Lainnya">Lainnya</option>
    </select>
</div>
<div class="mb-0">
    <label class="form-label">Detail Tambahan (opsional)</label>
    <textarea name="alasan_pembatalan_detail" class="form-control" rows="2" maxlength="500"></textarea>
</div>
