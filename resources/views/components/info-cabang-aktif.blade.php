{{--
    Penanda cabang data yang tampil (CLAUDE.md 4.39) — data barang mengikuti
    pilihan cabang di pojok kanan atas. Props: $cabang (Cabang|null = Semua Cabang).
--}}
@props(['cabang' => null])
<div class="small text-muted mb-2">
    <i class="bi bi-geo-alt-fill text-primary"></i>
    @if($cabang)
        Data milik <strong class="text-body">{{ $cabang->nama_cabang }}</strong>
        <span class="d-none d-sm-inline">· ganti cabang lewat pilihan cabang di pojok kanan atas</span>
    @else
        Menampilkan <strong class="text-body">semua cabang</strong> (katalog lengkap)
        <span class="d-none d-sm-inline">· pilih satu cabang di pojok kanan atas untuk melihat milik cabang itu saja</span>
    @endif
</div>
