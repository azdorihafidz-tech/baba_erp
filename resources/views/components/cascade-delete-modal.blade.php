{{--
    Reusable cascade delete confirmation modal.
    Props:
      $entity     — label singular entity (misal "Barang", "Supplier", "Karyawan")
      $childList  — array string daftar data yang akan ikut terhapus
--}}
<div class="modal fade" id="cascadeDeleteModal" tabindex="-1" aria-labelledby="cascadeDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-danger" id="cascadeDeleteModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus {{ $entity }}
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="mb-2">Anda akan menghapus {{ strtolower($entity) }}:</p>
                <div class="alert alert-danger py-2 px-3 mb-3">
                    <strong id="cascadeModalEntityName"></strong>
                </div>
                @if(!empty($childList))
                <p class="text-muted mb-2" style="font-size:0.875rem">
                    <i class="bi bi-info-circle me-1"></i>
                    Data berikut akan ikut dihapus secara otomatis:
                </p>
                <ul class="list-unstyled mb-3 ps-1" style="font-size:0.8rem;color:#64748b">
                    @foreach($childList as $child)
                    <li><i class="bi bi-dot"></i> {{ $child }}</li>
                    @endforeach
                </ul>
                @endif
                <div class="alert alert-warning py-2 px-3 mb-0" style="font-size:0.8rem">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>
                    Data yang dihapus dapat dipulihkan melalui menu <strong>Data Terhapus</strong>.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <form id="cascadeModalForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash3 me-1"></i>Ya, Hapus {{ $entity }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
