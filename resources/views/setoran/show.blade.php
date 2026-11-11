@extends('layouts.app')

@section('title', 'Detail Transfer Dana')

@section('content')
@php
    $pair       = $setoran->setoranPasangan;
    $isMenunggu = $setoran->status_setoran === \App\Enums\StatusSetoran::MenungguDiterima;
    $isDiterima = $setoran->status_setoran === \App\Enums\StatusSetoran::Diterima;
    $isDitolak  = $setoran->status_setoran === \App\Enums\StatusSetoran::Ditolak;
    $userCabangId = session('active_cabang_id') ?? auth()->user()->defaultCabangId();
    $isSender   = $setoran->cabang_id == $userCabangId || auth()->user()->canAccessAllBranches();
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Detail Transfer Dana</h5>
        <small class="text-muted"><code>{{ $setoran->nomor_transaksi }}</code></small>
    </div>
    <a href="{{ route('setoran.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

{{-- Status Banner --}}
<div class="alert {{ $setoran->status_setoran?->badgeClass() === 'bg-warning text-dark' ? 'alert-warning' : ($setoran->status_setoran?->badgeClass() === 'bg-success' ? 'alert-success' : ($setoran->status_setoran?->badgeClass() === 'bg-danger' ? 'alert-danger' : 'alert-secondary')) }} d-flex align-items-center gap-3 mb-4">
    @if($setoran->status_setoran)
    <i class="bi {{ $setoran->status_setoran->icon() }} fs-3"></i>
    <div>
        <div class="fw-bold fs-5">{{ $setoran->status_setoran->label() }}</div>
        @if($isDiterima && $setoran->waktu_diterima)
        <div class="small">Diterima oleh <strong>{{ $setoran->diterimaOleh?->name ?? '-' }}</strong>
            pada {{ $setoran->waktu_diterima->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</div>
        @elseif($isDitolak)
        <div class="small">Alasan: <em>{{ $setoran->alasan_tolak_setoran ?? '-' }}</em></div>
        @elseif($isMenunggu)
        <div class="small">Menunggu konfirmasi dari penerima</div>
        @endif
    </div>
    <div class="ms-auto fw-bold fs-4">
        Rp {{ number_format($setoran->jumlah, 0, ',', '.') }}
    </div>
    @endif
</div>

{{-- Action buttons (top) --}}
@if($isMenunggu)
<div class="d-flex flex-wrap gap-2 mb-4">
    @can('setoran.terima')
    <button type="button" onclick="confirmTerima({{ $setoran->id }}, {{ $setoran->jumlah }})"
        class="btn btn-success">
        <i class="bi bi-check-circle me-1"></i>Terima Transfer Dana
    </button>
    <button type="button" onclick="openTolakModal({{ $setoran->id }})"
        class="btn btn-warning">
        <i class="bi bi-x-circle me-1"></i>Tolak Transfer Dana
    </button>
    @endcan
    @can('setoran.batal')
    @if($isSender)
    <button type="button" onclick="confirmBatal({{ $setoran->id }}, {{ $setoran->jumlah }})"
        class="btn btn-outline-secondary">
        <i class="bi bi-slash-circle me-1"></i>Batalkan Transfer Dana
    </button>
    @endif
    @endcan
</div>
@endif

<div class="row g-3">
    {{-- Pengiriman --}}
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold bg-danger bg-opacity-10">
                <i class="bi bi-arrow-up-circle text-danger me-1"></i>Pengiriman (Cabang Asal)
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 fw-semibold">Tanggal Kirim</dt>
                    <dd class="col-7">{{ $setoran->tanggal_transaksi->format('d M Y') }}</dd>

                    <dt class="col-5 fw-semibold">Pengirim</dt>
                    <dd class="col-7">{{ $setoran->createdBy?->name ?? '-' }}</dd>

                    <dt class="col-5 fw-semibold">Cabang Asal</dt>
                    <dd class="col-7">{{ $setoran->cabang?->nama_cabang ?? '-' }}</dd>

                    <dt class="col-5 fw-semibold">Kas Asal</dt>
                    <dd class="col-7">{{ $setoran->kas?->nama_kas ?? '—' }}</dd>

                    <dt class="col-5 fw-semibold">Saldo Kas Asal</dt>
                    <dd class="col-7">Rp {{ number_format($setoran->kas?->saldo_sekarang ?? 0, 0, ',', '.') }}
                        <small class="text-muted">(saldo saat ini)</small>
                    </dd>

                    <dt class="col-5 fw-semibold">Keterangan</dt>
                    <dd class="col-7">{{ $setoran->keterangan }}</dd>

                    @if($setoran->catatan)
                    <dt class="col-5 fw-semibold">Catatan</dt>
                    <dd class="col-7 text-muted">{{ $setoran->catatan }}</dd>
                    @endif

                    <dt class="col-5 fw-semibold">Jumlah</dt>
                    <dd class="col-7 fw-bold text-danger fs-5">-Rp {{ number_format($setoran->jumlah, 0, ',', '.') }}</dd>

                    <dt class="col-5 fw-semibold">Nomor</dt>
                    <dd class="col-7"><code>{{ $setoran->nomor_transaksi }}</code></dd>

                    <dt class="col-5 fw-semibold">Dibuat</dt>
                    <dd class="col-7 text-muted">{{ $setoran->created_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</dd>
                </dl>
            </div>
        </div>
    </div>

    {{-- Penerimaan --}}
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold {{ $isDiterima ? 'bg-success bg-opacity-10' : 'bg-light' }}">
                <i class="bi bi-arrow-down-circle {{ $isDiterima ? 'text-success' : 'text-secondary' }} me-1"></i>
                Penerimaan (Cabang Tujuan)
            </div>
            <div class="card-body">
                @if($pair)
                <dl class="row mb-0 small">
                    <dt class="col-5 fw-semibold">Cabang Tujuan</dt>
                    <dd class="col-7">{{ $pair->cabang?->nama_cabang ?? '-' }}</dd>

                    <dt class="col-5 fw-semibold">Kas Tujuan</dt>
                    <dd class="col-7">{{ $pair->kas?->nama_kas ?? '—' }}</dd>

                    @if($isDiterima)
                    <dt class="col-5 fw-semibold">Diterima Oleh</dt>
                    <dd class="col-7">{{ $setoran->diterimaOleh?->name ?? '-' }}</dd>

                    <dt class="col-5 fw-semibold">Waktu Diterima</dt>
                    <dd class="col-7">{{ $setoran->waktu_diterima?->setTimezone('Asia/Jakarta')->format('d M Y H:i') ?? '-' }}</dd>

                    <dt class="col-5 fw-semibold">Saldo Kas Tujuan</dt>
                    <dd class="col-7">Rp {{ number_format($pair->kas?->saldo_sekarang ?? 0, 0, ',', '.') }}
                        <small class="text-muted">(saat ini)</small>
                    </dd>

                    <dt class="col-5 fw-semibold">Jumlah Diterima</dt>
                    <dd class="col-7 fw-bold text-success fs-5">+Rp {{ number_format($pair->jumlah, 0, ',', '.') }}</dd>
                    @endif

                    @if($isDitolak)
                    <dt class="col-5 fw-semibold text-danger">Alasan Tolak</dt>
                    <dd class="col-7 text-danger">{{ $setoran->alasan_tolak_setoran ?? '-' }}</dd>
                    @endif

                    <dt class="col-5 fw-semibold">Nomor</dt>
                    <dd class="col-7"><code>{{ $pair->nomor_transaksi }}</code></dd>
                </dl>
                @if(!$isDiterima && !$isDitolak)
                <div class="text-center text-muted py-3">
                    <i class="bi bi-hourglass-split fs-2 d-block mb-2 text-warning"></i>
                    Menunggu konfirmasi penerimaan
                </div>
                @endif
                @else
                <div class="text-center text-muted py-3">
                    <i class="bi bi-question-circle fs-2 d-block mb-2"></i>
                    Data pasangan tidak ditemukan
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Bukti --}}
    @if($setoran->bukti_path)
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-paperclip me-1"></i>Bukti Transfer</div>
            <div class="card-body text-center">
                @php
                    $ext    = strtolower(pathinfo($setoran->bukti_path, PATHINFO_EXTENSION));
                    $imgUrl = '/img/' . $setoran->bukti_path;
                @endphp
                @if(in_array($ext, ['jpg', 'jpeg', 'png']))
                <img src="{{ $imgUrl }}" alt="Bukti Transfer" class="img-fluid rounded shadow-sm" style="max-height:400px; cursor:pointer"
                    onclick="window.open('{{ $imgUrl }}', '_blank')">
                <div class="mt-2">
                    <a href="{{ $imgUrl }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Buka di Tab Baru
                    </a>
                </div>
                @else
                <a href="{{ $imgUrl }}" target="_blank" class="btn btn-outline-primary">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Lihat File PDF
                </a>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Admin: hapus permanen --}}
    @can('setoran.delete')
    <div class="col-12">
        <div class="card border-danger border-opacity-25">
            <div class="card-body">
                <h6 class="text-danger mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Hapus Permanen (Admin Only)</h6>
                <p class="text-muted small mb-3">Menghapus transfer dana akan membalik saldo kas yang terpengaruh berdasarkan status saat ini.</p>
                <button type="button" onclick="confirmHapus({{ $setoran->id }})"
                    class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash me-1"></i>Hapus Permanen
                </button>
            </div>
        </div>
    </div>
    @endcan
</div>

{{-- Modals --}}
<div class="modal fade" id="terimaModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-check-circle me-2"></i>Konfirmasi Terima</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Terima transfer dana sebesar <strong class="text-success" id="terimaJumlah"></strong>?</p>
                <p class="text-muted small mb-0">Saldo kas tujuan akan bertambah sesuai jumlah.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <form id="terimaForm" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Ya, Terima</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="tolakModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h6 class="modal-title fw-bold"><i class="bi bi-x-circle me-2"></i>Tolak Transfer Dana</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="tolakForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small mb-3">Saldo kas asal pengirim akan dikembalikan otomatis.</p>
                    <label class="form-label fw-semibold">Alasan Penolakan <span class="text-danger">*</span></label>
                    <textarea name="alasan_tolak" class="form-control" rows="3" required minlength="5"
                        placeholder="Contoh: Bukti tidak jelas, jumlah tidak sesuai..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kembali</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-x-lg me-1"></i>Tolak</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="batalModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-slash-circle me-2"></i>Batalkan Transfer Dana</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Batalkan transfer dana sebesar <strong id="batalJumlah"></strong>?</p>
                <p class="text-muted small mb-0">Saldo kas asal akan dikembalikan. Tidak bisa diaktifkan ulang.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                <form id="batalForm" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-secondary"><i class="bi bi-slash-circle me-1"></i>Ya, Batalkan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="hapusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-trash me-2"></i>Hapus Permanen</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger small mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Aksi ini tidak dapat dibatalkan. Saldo akan disesuaikan otomatis berdasarkan status saat ini.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <form id="hapusForm" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function fmtRp(n) {
    return 'Rp ' + Number(n).toLocaleString('id-ID');
}
function confirmTerima(id, jumlah) {
    document.getElementById('terimaJumlah').textContent = fmtRp(jumlah);
    document.getElementById('terimaForm').action = '/setoran/' + id + '/terima';
    new bootstrap.Modal(document.getElementById('terimaModal')).show();
}
function openTolakModal(id) {
    document.getElementById('tolakForm').action = '/setoran/' + id + '/tolak';
    document.querySelector('#tolakForm textarea').value = '';
    new bootstrap.Modal(document.getElementById('tolakModal')).show();
}
function confirmBatal(id, jumlah) {
    document.getElementById('batalJumlah').textContent = fmtRp(jumlah);
    document.getElementById('batalForm').action = '/setoran/' + id + '/batal';
    new bootstrap.Modal(document.getElementById('batalModal')).show();
}
function confirmHapus(id) {
    document.getElementById('hapusForm').action = '/setoran/' + id;
    new bootstrap.Modal(document.getElementById('hapusModal')).show();
}
</script>
@endpush
