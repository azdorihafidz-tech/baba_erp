{{-- Modal Hapus Permanen — 2 step, dipakai bareng oleh trash/index.blade.php
     dan trash/detail.blade.php. 1 instance per halaman (data diisi via JS). --}}
<div class="modal fade" id="modalHapusPermanen" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <form id="formHapusPermanen" method="POST">
                @csrf
                @method('DELETE')

                {{-- STEP 1: Preview + Warning --}}
                <div id="hapusPermanenStep1">
                    <div class="modal-header bg-danger-subtle">
                        <h6 class="modal-title mb-0 text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Hapus Permanen</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small mb-3">
                            Anda akan menghapus permanen: <span id="hapusPermanenNama" class="fw-semibold text-dark"></span>
                        </p>
                        <div class="alert alert-danger py-2 small mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Aksi ini <strong>TIDAK BISA dibatalkan</strong>. Data akan hilang selamanya dari database
                            (bukan sekadar disembunyikan seperti soft-delete). Pastikan Anda sudah cek detail data
                            sebelum melanjutkan.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="hapusPermanenLanjutStep2()">
                            Lanjut <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>

                {{-- STEP 2: Ketik konfirmasi --}}
                <div id="hapusPermanenStep2" style="display:none">
                    <div class="modal-header bg-danger-subtle">
                        <h6 class="modal-title mb-0 text-danger"><i class="bi bi-shield-exclamation me-2"></i>Konfirmasi Terakhir</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label fw-semibold small">
                            Ketik <span class="text-danger">HAPUS PERMANEN</span> untuk mengaktifkan tombol konfirmasi:
                        </label>
                        <input type="text" id="hapusPermanenInput" class="form-control" autocomplete="off" placeholder="Ketik di sini...">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="hapusPermanenKembaliStep1()">
                            <i class="bi bi-arrow-left me-1"></i>Kembali
                        </button>
                        <button type="submit" id="btnKonfirmasiHapusPermanen" class="btn btn-danger btn-sm" disabled>
                            <i class="bi bi-trash3-fill me-1"></i>Konfirmasi Hapus Permanen
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
const HAPUS_PERMANEN_TEXT = 'HAPUS PERMANEN';

function bukaModalHapusPermanen(actionUrl, namaData) {
    const form = document.getElementById('formHapusPermanen');
    if (!form) return;
    form.action = actionUrl;
    document.getElementById('hapusPermanenNama').textContent = namaData;

    // Selalu mulai dari step 1
    document.getElementById('hapusPermanenStep1').style.display = '';
    document.getElementById('hapusPermanenStep2').style.display = 'none';
    const input = document.getElementById('hapusPermanenInput');
    input.value = '';
    document.getElementById('btnKonfirmasiHapusPermanen').disabled = true;

    new bootstrap.Modal(document.getElementById('modalHapusPermanen')).show();
}

function hapusPermanenLanjutStep2() {
    document.getElementById('hapusPermanenStep1').style.display = 'none';
    document.getElementById('hapusPermanenStep2').style.display = '';
    setTimeout(() => document.getElementById('hapusPermanenInput').focus(), 150);
}

function hapusPermanenKembaliStep1() {
    document.getElementById('hapusPermanenStep2').style.display = 'none';
    document.getElementById('hapusPermanenStep1').style.display = '';
}

const hapusPermanenInputEl = document.getElementById('hapusPermanenInput');
if (hapusPermanenInputEl) {
    hapusPermanenInputEl.addEventListener('input', function () {
        document.getElementById('btnKonfirmasiHapusPermanen').disabled = (this.value.trim() !== HAPUS_PERMANEN_TEXT);
    });
}
</script>
@endpush
