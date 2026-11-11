@extends('layouts.app')

@section('title', 'Transfer / Perpindahan Dana')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Transfer / Perpindahan Dana</h5>
        <p class="text-muted mb-0 small">Transfer kas antar cabang dengan konfirmasi penerimaan</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        @can('setoran.create')
        <a href="{{ route('setoran.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Buat Transfer Dana
        </a>
        @endcan
        <x-panduan-button slug="transfer-dana" />
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    {!! session('success') !!}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error_info'))
<div class="alert alert-warning alert-dismissible fade show py-2" role="alert">
    {!! session('error_info') !!}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    {!! session('error') !!}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Filter cabang (Owner only) + Search --}}
<form method="GET" class="mb-3 d-flex flex-wrap gap-2 align-items-center">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div style="width:220px;max-width:100%">
        <x-search-box placeholder="Nomor / keterangan / kas / cabang..." col="" />
    </div>
    @if($cabangOptions->count())
    <select name="cabang_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <option value="">Semua Cabang Asal</option>
        @foreach($cabangOptions as $c)
        <option value="{{ $c->id }}" @selected(request('cabang_id') == $c->id)>{{ $c->nama_cabang }}</option>
        @endforeach
    </select>
    @endif
    <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
    @if(request('cabang_id') || request('search'))
    <a href="{{ route('setoran.index', ['tab' => $tab]) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-x"></i> Reset
    </a>
    @endif
</form>

{{-- Tabs --}}
<ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto">
    <li class="nav-item">
        <a class="nav-link text-nowrap {{ $tab === 'semua' ? 'active' : '' }}"
           href="{{ request()->fullUrlWithQuery(['tab' => 'semua']) }}">
            <i class="bi bi-list-ul me-1"></i>Semua
            <span class="badge bg-secondary ms-1">{{ array_sum($counts) }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-nowrap {{ $tab === 'menunggu' ? 'active' : '' }}"
           href="{{ request()->fullUrlWithQuery(['tab' => 'menunggu']) }}">
            <i class="bi bi-hourglass-split me-1"></i>Menunggu
            @if($counts['menunggu'] > 0)
            <span class="badge bg-warning text-dark ms-1">{{ $counts['menunggu'] }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-nowrap {{ $tab === 'diterima' ? 'active' : '' }}"
           href="{{ request()->fullUrlWithQuery(['tab' => 'diterima']) }}">
            <i class="bi bi-check-circle me-1"></i>Diterima
            @if($counts['diterima'] > 0)
            <span class="badge bg-success ms-1">{{ $counts['diterima'] }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-nowrap {{ $tab === 'ditolak' ? 'active' : '' }}"
           href="{{ request()->fullUrlWithQuery(['tab' => 'ditolak']) }}">
            <i class="bi bi-x-circle me-1"></i>Ditolak
            @if($counts['ditolak'] > 0)
            <span class="badge bg-danger ms-1">{{ $counts['ditolak'] }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-nowrap {{ $tab === 'dibatalkan' ? 'active' : '' }}"
           href="{{ request()->fullUrlWithQuery(['tab' => 'dibatalkan']) }}">
            <i class="bi bi-slash-circle me-1"></i>Dibatalkan
            @if($counts['dibatalkan'] > 0)
            <span class="badge bg-secondary ms-1">{{ $counts['dibatalkan'] }}</span>
            @endif
        </a>
    </li>
</ul>

{{-- Desktop Table --}}
<div class="card d-none d-md-block">
    <div class="card-header d-flex justify-content-between">
        <span class="fw-semibold">Daftar Transfer Dana</span>
        <small class="text-muted">{{ $setorans->total() }} transaksi</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Tanggal</th>
                    <th>Keterangan</th>
                    <th>Dari → Ke</th>
                    <th class="text-end">Jumlah</th>
                    <th class="text-center">Status</th>
                    <th class="text-center d-none d-lg-table-cell">Bukti</th>
                    <th class="text-center" style="min-width:130px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($setorans as $trx)
                @php
                    $pair           = $trx->setoranPasangan;
                    $isMenunggu     = $trx->status_setoran === \App\Enums\StatusSetoran::MenungguDiterima;
                    $userCabangId   = session('active_cabang_id') ?? auth()->user()->defaultCabangId();
                    $isSender       = $trx->cabang_id == $userCabangId || auth()->user()->canAccessAllBranches();
                @endphp
                <tr>
                    <td class="text-nowrap">{{ $trx->tanggal_transaksi->format('d/m/Y') }}</td>
                    <td>
                        <div class="fw-semibold small">{{ $trx->keterangan }}</div>
                        @if($trx->catatan)
                        <small class="text-muted">{{ Str::limit($trx->catatan, 50) }}</small>
                        @endif
                    </td>
                    <td>
                        <div class="small">
                            <span class="fw-semibold">{{ $trx->cabang?->nama_cabang ?? '-' }}</span>
                            @if($trx->kas)<br><span class="text-muted">{{ $trx->kas->nama_kas }}</span>@endif
                        </div>
                        <div class="text-muted small mt-1">
                            <i class="bi bi-arrow-right text-primary"></i>
                            @if($pair)
                            <span class="fw-semibold">{{ $pair->cabang?->nama_cabang ?? '-' }}</span>
                            @if($pair->kas)<span class="text-muted"> / {{ $pair->kas->nama_kas }}</span>@endif
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </div>
                    </td>
                    <td class="text-end fw-bold text-danger text-nowrap">
                        -Rp {{ number_format($trx->jumlah, 0, ',', '.') }}
                    </td>
                    <td class="text-center">
                        @if($trx->status_setoran)
                        <span class="badge {{ $trx->status_setoran->badgeClass() }}">
                            <i class="bi {{ $trx->status_setoran->icon() }} me-1"></i>
                            {{ $trx->status_setoran->label() }}
                        </span>
                        @else
                        <span class="badge bg-light text-secondary border">Legacy</span>
                        @endif
                    </td>
                    <td class="text-center d-none d-lg-table-cell">
                        @if($trx->bukti_path)
                        <a href="/img/{{ $trx->bukti_path }}" target="_blank" class="btn btn-xs btn-outline-info py-0 px-1" title="Lihat Bukti" style="font-size:0.72rem">
                            <i class="bi bi-paperclip"></i>
                        </a>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center flex-wrap">
                            <a href="{{ route('setoran.show', $trx) }}"
                               class="btn btn-xs btn-outline-primary py-0 px-1" title="Detail" style="font-size:0.72rem">
                                <i class="bi bi-eye"></i>
                            </a>
                            @can('setoran.terima')
                            @if($isMenunggu)
                            <button type="button"
                                onclick="confirmTerima({{ $trx->id }}, '{{ addslashes($trx->nomor_transaksi) }}', {{ $trx->jumlah }})"
                                class="btn btn-xs btn-success py-0 px-1" title="Terima" style="font-size:0.72rem">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <button type="button"
                                onclick="openTolakModal({{ $trx->id }}, '{{ addslashes($trx->nomor_transaksi) }}')"
                                class="btn btn-xs btn-warning py-0 px-1" title="Tolak" style="font-size:0.72rem">
                                <i class="bi bi-x-lg"></i>
                            </button>
                            @endif
                            @endcan
                            @can('setoran.batal')
                            @if($isMenunggu && $isSender)
                            <button type="button"
                                onclick="confirmBatal({{ $trx->id }}, '{{ addslashes($trx->nomor_transaksi) }}', {{ $trx->jumlah }})"
                                class="btn btn-xs btn-outline-secondary py-0 px-1" title="Batalkan" style="font-size:0.72rem">
                                <i class="bi bi-slash-circle"></i>
                            </button>
                            @endif
                            @endcan
                            @can('setoran.delete')
                            <button type="button"
                                onclick="confirmHapus({{ $trx->id }}, '{{ addslashes($trx->nomor_transaksi) }}')"
                                class="btn btn-xs btn-outline-danger py-0 px-1" title="Hapus Permanen" style="font-size:0.72rem">
                                <i class="bi bi-trash"></i>
                            </button>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-send fs-2 d-block mb-2"></i>
                        @if($tab !== 'semua')
                        Tidak ada setoran dengan status "{{ match($tab) { 'menunggu' => 'Menunggu Diterima', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak', 'dibatalkan' => 'Dibatalkan', default => $tab } }}"
                        @else
                        Belum ada setoran
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($setorans->hasPages())
    <div class="card-footer">{{ $setorans->links() }}</div>
    @endif
</div>

{{-- Mobile Cards --}}
<div class="d-md-none">
    @forelse($setorans as $trx)
    @php
        $pair       = $trx->setoranPasangan;
        $isMenunggu = $trx->status_setoran === \App\Enums\StatusSetoran::MenungguDiterima;
        $userCabangId = session('active_cabang_id') ?? auth()->user()->defaultCabangId();
        $isSender   = $trx->cabang_id == $userCabangId || auth()->user()->canAccessAllBranches();
    @endphp
    <div class="card mb-2">
        <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-start mb-1">
                <div class="flex-fill me-2">
                    <div class="fw-semibold small">{{ $trx->keterangan }}</div>
                    <div class="text-muted small">{{ $trx->tanggal_transaksi->format('d M Y') }}</div>
                </div>
                <div class="text-end">
                    <div class="fw-bold text-danger small">-Rp {{ number_format($trx->jumlah, 0, ',', '.') }}</div>
                    @if($trx->status_setoran)
                    <span class="badge {{ $trx->status_setoran->badgeClass() }} mt-1" style="font-size:0.65rem">
                        {{ $trx->status_setoran->label() }}
                    </span>
                    @endif
                </div>
            </div>
            <div class="text-muted small mb-2">
                <i class="bi bi-send me-1"></i>
                {{ $trx->cabang?->nama_cabang ?? '-' }} ({{ $trx->kas?->nama_kas ?? '-' }})
                <i class="bi bi-arrow-right mx-1 text-primary"></i>
                {{ $pair?->cabang?->nama_cabang ?? '-' }} ({{ $pair?->kas?->nama_kas ?? '-' }})
            </div>
            <div class="d-flex gap-2 flex-wrap pt-2 border-top">
                <a href="{{ route('setoran.show', $trx) }}" class="btn btn-sm btn-outline-primary py-1 flex-fill" style="font-size:0.78rem">
                    <i class="bi bi-eye me-1"></i>Detail
                </a>
                @can('setoran.terima')
                @if($isMenunggu)
                <button type="button" onclick="confirmTerima({{ $trx->id }}, '{{ addslashes($trx->nomor_transaksi) }}', {{ $trx->jumlah }})"
                    class="btn btn-sm btn-success py-1" style="font-size:0.78rem">
                    <i class="bi bi-check-lg me-1"></i>Terima
                </button>
                <button type="button" onclick="openTolakModal({{ $trx->id }}, '{{ addslashes($trx->nomor_transaksi) }}')"
                    class="btn btn-sm btn-warning py-1" style="font-size:0.78rem">
                    <i class="bi bi-x-lg me-1"></i>Tolak
                </button>
                @endif
                @endcan
                @can('setoran.batal')
                @if($isMenunggu && $isSender)
                <button type="button" onclick="confirmBatal({{ $trx->id }}, '{{ addslashes($trx->nomor_transaksi) }}', {{ $trx->jumlah }})"
                    class="btn btn-sm btn-outline-secondary py-1" style="font-size:0.78rem">
                    <i class="bi bi-slash-circle me-1"></i>Batal
                </button>
                @endif
                @endcan
                @can('setoran.delete')
                <button type="button" onclick="confirmHapus({{ $trx->id }}, '{{ addslashes($trx->nomor_transaksi) }}')"
                    class="btn btn-sm btn-outline-danger py-1" style="font-size:0.78rem">
                    <i class="bi bi-trash me-1"></i>Hapus
                </button>
                @endcan
            </div>
        </div>
    </div>
    @empty
    <div class="text-center text-muted py-5">
        <i class="bi bi-send fs-2 d-block mb-2"></i>Tidak ada data
    </div>
    @endforelse
    @if($setorans->hasPages())
    <div class="mt-3">{{ $setorans->links() }}</div>
    @endif
</div>

{{-- Modal Terima --}}
<div class="modal fade" id="terimaModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-check-circle me-2"></i>Konfirmasi Terima Transfer Dana</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Konfirmasi penerimaan setoran <strong id="terimaNomor"></strong>?</p>
                <p class="mb-0">Jumlah: <strong class="text-success" id="terimaJumlah"></strong></p>
                <p class="text-muted small mt-2 mb-0">Saldo kas tujuan akan bertambah sesuai jumlah setoran.</p>
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

{{-- Modal Tolak --}}
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
                    <p class="mb-2">Tolak transfer dana <strong id="tolakNomor"></strong>?</p>
                    <p class="text-muted small mb-3">Saldo kas asal (pengirim) akan dikembalikan otomatis.</p>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea name="alasan_tolak" class="form-control" rows="3" required minlength="5"
                            placeholder="Contoh: Bukti transfer tidak jelas, jumlah tidak sesuai..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kembali</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-x-lg me-1"></i>Tolak Transfer Dana</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Batal --}}
<div class="modal fade" id="batalModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-slash-circle me-2"></i>Batalkan Transfer Dana</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Batalkan transfer dana <strong id="batalNomor"></strong>?</p>
                <p class="mb-0">Jumlah: <strong id="batalJumlah"></strong></p>
                <p class="text-muted small mt-2 mb-0">Saldo kas asal akan dikembalikan. Transfer dana tidak bisa diaktifkan ulang setelah dibatalkan.</p>
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

{{-- Modal Hapus Permanen --}}
<div class="modal fade" id="hapusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-trash me-2"></i>Hapus Permanen Transfer Dana</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Hapus permanen transfer dana <strong id="hapusNomor"></strong>?</p>
                <p class="text-danger small mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Aksi ini tidak dapat dibatalkan. Saldo akan disesuaikan otomatis.</p>
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

function confirmTerima(id, nomor, jumlah) {
    document.getElementById('terimaNomor').textContent = nomor;
    document.getElementById('terimaJumlah').textContent = fmtRp(jumlah);
    document.getElementById('terimaForm').action = '/setoran/' + id + '/terima';
    new bootstrap.Modal(document.getElementById('terimaModal')).show();
}

function openTolakModal(id, nomor) {
    document.getElementById('tolakNomor').textContent = nomor;
    document.getElementById('tolakForm').action = '/setoran/' + id + '/tolak';
    document.querySelector('#tolakForm textarea[name="alasan_tolak"]').value = '';
    new bootstrap.Modal(document.getElementById('tolakModal')).show();
}

function confirmBatal(id, nomor, jumlah) {
    document.getElementById('batalNomor').textContent = nomor;
    document.getElementById('batalJumlah').textContent = fmtRp(jumlah);
    document.getElementById('batalForm').action = '/setoran/' + id + '/batal';
    new bootstrap.Modal(document.getElementById('batalModal')).show();
}

function confirmHapus(id, nomor) {
    document.getElementById('hapusNomor').textContent = nomor;
    document.getElementById('hapusForm').action = '/setoran/' + id;
    new bootstrap.Modal(document.getElementById('hapusModal')).show();
}
</script>
@endpush
