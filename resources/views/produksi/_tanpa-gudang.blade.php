@if($gudang->isEmpty())
<div class="alert alert-warning small">
    <i class="bi bi-exclamation-triangle me-1"></i>
    Belum ada <strong>gudang</strong> yang bisa Anda kelola. Produksi hanya dicatat di cabang bertipe <strong>Gudang Pusat</strong>.
    @can('cabang.view') Buat lewat menu <a href="{{ route('cabang.index') }}">Cabang</a> (pilih tipe Gudang Pusat).@endcan
</div>
@endif
