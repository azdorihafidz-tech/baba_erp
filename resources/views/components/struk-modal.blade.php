{{--
    Component: <x-struk-modal />
    Modal shell untuk menampilkan struk POS tanpa membuka tab baru.
    Konten diisi via AJAX oleh window.openStrukModal(orderId).
    Letakkan sekali di layouts/app.blade.php.
--}}
<div class="modal fade" id="strukModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-md-down">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-receipt me-2 text-success"></i>Struk Pembelian
                </h5>
                {{-- Tombol X sengaja dihilangkan — modal cuma bisa ditutup
                     via tombol "Selesai" di dalam konten, supaya reset form
                     POS selalu lewat jalur yang benar. --}}
            </div>
            <div class="modal-body p-2 text-center" id="strukModalBody">
                <div class="py-5">
                    <div class="spinner-border text-success mb-2"></div>
                    <p class="text-muted small">Memuat struk...</p>
                </div>
            </div>
        </div>
    </div>
</div>
