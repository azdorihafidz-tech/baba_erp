@extends('layouts.app')

@section('title', 'Kelola Device Absensi')

@push('styles')
<style>
.device-card {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem;
    background: white;
    transition: box-shadow .2s;
}
.device-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.device-card.inactive { opacity: 0.6; background: #fafafa; }
.device-icon {
    width: 48px; height: 48px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; flex-shrink: 0;
}
.token-box {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
    padding: 6px 10px; font-family: monospace; font-size: 0.78rem;
    word-break: break-all;
}
.qr-badge {
    display: inline-flex; align-items: center; gap: 4px;
    background: #eff6ff; border: 1px solid #bfdbfe;
    color: #1d4ed8; border-radius: 6px; padding: 3px 8px;
    font-size: 0.75rem; cursor: pointer;
    transition: background .15s;
}
.qr-badge:hover { background: #dbeafe; }
</style>
@endpush

@section('content')
<div class="container-fluid px-3 px-md-4">

    {{-- Header --}}
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2">
        <div>
            <h4 class="mb-1 fw-semibold">Kelola Device Absensi</h4>
            <p class="text-muted mb-0 small">Registrasi dan kelola device yang bisa digunakan untuk scan absensi wajah</p>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
            <i class="bi bi-plus-lg me-1"></i> Tambah Device
        </button>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show small" role="alert">
        <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Info Cara Pakai --}}
    <div class="alert alert-info border-0 small mb-4">
        <strong><i class="bi bi-info-circle me-1"></i>Cara menggunakan device absensi:</strong>
        <ol class="mb-0 mt-1 ps-4">
            <li>Tambahkan device baru dengan nama yang mudah dikenali</li>
            <li>Salin URL atau scan QR code untuk membuka halaman absen di device tablet/HP</li>
            <li>Device dapat diakses tanpa login — cukup buka URL dengan token yang ada</li>
            <li>Nonaktifkan device jika tidak dipakai untuk keamanan</li>
        </ol>
    </div>

    {{-- Grid Device --}}
    @forelse($devices as $device)
    @php $scanUrl = route('face-attendance.scan', ['token' => $device->device_token]); @endphp
    <div class="device-card mb-3 {{ !$device->is_active ? 'inactive' : '' }}">
        <div class="d-flex align-items-start gap-3">

            {{-- Icon --}}
            <div class="device-icon {{ $device->is_active ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' }}">
                <i class="bi bi-tablet-landscape"></i>
            </div>

            {{-- Info --}}
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="fw-semibold">{{ $device->device_name }}</span>
                    @if($device->is_active)
                    <span class="badge bg-success-subtle text-success border border-success-subtle small">Aktif</span>
                    @else
                    <span class="badge bg-secondary-subtle text-secondary border small">Nonaktif</span>
                    @endif
                </div>

                <div class="text-muted small mb-2">
                    <i class="bi bi-building me-1"></i>{{ $device->cabang?->nama_cabang ?? '—' }}
                    @if($device->last_used_at)
                    &nbsp;·&nbsp;
                    <i class="bi bi-clock me-1"></i>Terakhir digunakan: {{ $device->last_used_at->diffForHumans() }}
                    @endif
                    &nbsp;·&nbsp;
                    Terdaftar: {{ $device->registered_at->format('d/m/Y') }}
                </div>

                {{-- Token & URL --}}
                <div class="mb-2">
                    <div class="token-box mb-2">
                        <span class="text-muted">Token: </span>
                        <span id="token_{{ $device->id }}">{{ Str::limit($device->device_token, 20, '...') }}</span>
                        <button class="btn btn-link btn-sm p-0 ms-1"
                                onclick="toggleToken({{ $device->id }}, '{{ $device->device_token }}')"
                                title="Tampilkan/Sembunyikan token">
                            <i class="bi bi-eye" id="tokenIcon_{{ $device->id }}"></i>
                        </button>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="qr-badge" onclick="copyUrl('{{ $scanUrl }}')">
                            <i class="bi bi-clipboard"></i> Salin URL
                        </span>
                        <a href="{{ $scanUrl }}" target="_blank" class="qr-badge">
                            <i class="bi bi-box-arrow-up-right"></i> Buka di Tab Baru
                        </a>
                        <span class="qr-badge" onclick="showQR('{{ $scanUrl }}', '{{ $device->device_name }}')">
                            <i class="bi bi-qr-code"></i> Tampilkan QR
                        </span>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="d-flex flex-column gap-2 flex-shrink-0">
                <form method="POST" action="{{ route('absen-device.toggle', $device) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-sm {{ $device->is_active ? 'btn-outline-warning' : 'btn-outline-success' }} w-100">
                        <i class="bi bi-{{ $device->is_active ? 'pause' : 'play' }}"></i>
                        <span class="d-none d-sm-inline">{{ $device->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('absen-device.reset-token', $device) }}"
                      onsubmit="return confirm('Reset token device {{ $device->device_name }}? URL lama akan tidak berlaku.')">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-arrow-repeat"></i>
                        <span class="d-none d-sm-inline">Reset Token</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('absen-device.destroy', $device) }}"
                      onsubmit="return confirm('Hapus device {{ $device->device_name }}?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                        <i class="bi bi-trash"></i>
                        <span class="d-none d-sm-inline">Hapus</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="text-center py-5 text-muted">
        <i class="bi bi-tablet-landscape display-4 d-block mb-3 opacity-25"></i>
        <p class="mb-1">Belum ada device terdaftar.</p>
        <p class="small">Klik "Tambah Device" untuk mendaftarkan tablet atau HP sebagai mesin absen.</p>
    </div>
    @endforelse

</div>

{{-- Modal Tambah Device --}}
<div class="modal fade" id="addDeviceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-semibold"><i class="bi bi-plus-circle me-2"></i>Tambah Device Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('absen-device.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Cabang <span class="text-danger">*</span></label>
                        <select name="cabang_id" class="form-select" required>
                            <option value="">-- Pilih Cabang --</option>
                            @foreach($cabangs as $c)
                            <option value="{{ $c->id }}"
                                {{ (session('cabang_id') == $c->id) ? 'selected' : '' }}>
                                {{ $c->nama_cabang }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nama Device <span class="text-danger">*</span></label>
                        <input type="text" name="device_name" class="form-control"
                               placeholder="Contoh: Tablet Kasir Depan, iPad Produksi"
                               required maxlength="100">
                        <div class="form-text">Nama yang mudah dikenali untuk identifikasi device</div>
                    </div>
                    <div class="alert alert-light border small">
                        <i class="bi bi-shield-check me-1"></i>
                        Token unik akan digenerate otomatis. Gunakan token/URL ini untuk membuka halaman scan di device tersebut.
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>Daftarkan Device
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal QR Code --}}
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h6 class="modal-title fw-semibold" id="qrModalTitle">QR Code</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qrContainer" class="mb-3"></div>
                <div class="small text-muted mb-2">Scan QR ini untuk membuka halaman absen di device</div>
                <input type="text" class="form-control form-control-sm text-center" id="qrUrlInput" readonly>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
{{-- QRCode.js dari CDN --}}
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
const tokenFull = {};

function toggleToken(id, full) {
    const el   = document.getElementById('token_' + id);
    const icon = document.getElementById('tokenIcon_' + id);
    if (tokenFull[id]) {
        el.textContent = full.substring(0, 20) + '...';
        icon.className = 'bi bi-eye';
        tokenFull[id]  = false;
    } else {
        el.textContent = full;
        icon.className = 'bi bi-eye-slash';
        tokenFull[id]  = true;
    }
}

function copyUrl(url) {
    navigator.clipboard.writeText(url).then(() => {
        showToast('URL disalin ke clipboard!');
    }).catch(() => {
        prompt('Salin URL ini:', url);
    });
}

function showQR(url, deviceName) {
    document.getElementById('qrModalTitle').textContent = 'QR Code — ' + deviceName;
    document.getElementById('qrUrlInput').value = url;
    const container = document.getElementById('qrContainer');
    container.innerHTML = '';
    new QRCode(container, { text: url, width: 200, height: 200 });
    new bootstrap.Modal(document.getElementById('qrModal')).show();
}

function showToast(msg) {
    const d = document.createElement('div');
    d.className = 'position-fixed bottom-0 start-50 translate-middle-x p-3';
    d.style.zIndex = '9999';
    d.innerHTML = `<div class="toast show align-items-center bg-dark text-white border-0">
        <div class="d-flex"><div class="toast-body">${msg}</div></div></div>`;
    document.body.appendChild(d);
    setTimeout(() => d.remove(), 2000);
}
</script>
@endpush
