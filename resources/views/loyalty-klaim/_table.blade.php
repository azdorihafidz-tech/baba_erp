@php $showProgramColumn = $showProgramColumn ?? false; @endphp

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    @if($showProgramColumn)
                    <th>Program</th>
                    @endif
                    <th>Pelanggan</th>
                    <th class="d-none d-md-table-cell">Bukti</th>
                    <th class="text-center">Status</th>
                    <th class="d-none d-md-table-cell text-end">Nominal Voucher</th>
                    <th class="d-none d-md-table-cell">Tanggal</th>
                    <th class="text-center" style="min-width:140px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($klaims as $i => $klaim)
                @php
                    $isExternalLink = $klaim->bukti_url && str_starts_with($klaim->bukti_url, 'http');
                @endphp
                <tr>
                    <td class="small text-muted">{{ $i + 1 }}</td>
                    @if($showProgramColumn)
                    <td class="small">{{ $klaim->loyaltyProgram->nama ?? '-' }}</td>
                    @endif
                    <td class="fw-semibold">
                        <a href="{{ route('pelanggan.show', $klaim->pelanggan_id) }}">{{ $klaim->pelanggan->nama_pelanggan ?? '-' }}</a>
                        @if($klaim->bukti_catatan)
                        <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($klaim->bukti_catatan, 60) }}</small>
                        @endif
                    </td>
                    <td class="d-none d-md-table-cell">
                        @if(!$klaim->bukti_url)
                        <span class="text-muted small">-</span>
                        @elseif($isExternalLink)
                        <a href="{{ $klaim->bukti_url }}" target="_blank" class="btn btn-sm btn-outline-primary px-2 py-1">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Link
                        </a>
                        @else
                        <a href="{{ '/img/' . $klaim->bukti_url }}" target="_blank" class="btn btn-sm btn-outline-secondary px-2 py-1">
                            <i class="bi bi-image me-1"></i>Foto
                        </a>
                        @endif
                    </td>
                    <td class="text-center">
                        @switch($klaim->status)
                            @case('pending')
                                <span class="badge bg-warning-subtle text-warning">Pending</span>
                                @break
                            @case('approved')
                                <span class="badge bg-info-subtle text-info">Approved</span>
                                @break
                            @case('rejected')
                                <span class="badge bg-danger-subtle text-danger">Rejected</span>
                                @break
                            @case('issued')
                                <span class="badge bg-success-subtle text-success">Issued</span>
                                @break
                        @endswitch
                    </td>
                    <td class="d-none d-md-table-cell text-end">
                        {{ $klaim->nominal_voucher ? 'Rp '.number_format($klaim->nominal_voucher,0,',','.') : '-' }}
                    </td>
                    <td class="d-none d-md-table-cell small text-muted">{{ $klaim->created_at->format('d M Y') }}</td>
                    <td class="text-center">
                        @can('loyalty.klaim.approve')
                        @if($klaim->status === 'pending')
                        <button type="button" class="btn btn-sm btn-outline-success px-2 py-1 mb-1" data-bs-toggle="modal" data-bs-target="#approveModal{{ $klaim->id }}" title="Setujui">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        @endif
                        @endcan
                        @can('loyalty.klaim.reject')
                        @if($klaim->status === 'pending')
                        <button type="button" class="btn btn-sm btn-outline-danger px-2 py-1 mb-1" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $klaim->id }}" title="Tolak">
                            <i class="bi bi-x-lg"></i>
                        </button>
                        @endif
                        @endcan
                        @can('loyalty.klaim.issued')
                        @if($klaim->status === 'approved')
                        <button type="button" class="btn btn-sm btn-outline-primary px-2 py-1 mb-1" data-bs-toggle="modal" data-bs-target="#issuedModal{{ $klaim->id }}" title="Tandai Diberikan">
                            <i class="bi bi-gift"></i>
                        </button>
                        @endif
                        @endcan
                        @if($klaim->status === 'rejected' && $klaim->rejected_reason)
                        <div class="small text-danger" style="max-width:160px" title="{{ $klaim->rejected_reason }}">{{ \Illuminate\Support\Str::limit($klaim->rejected_reason, 40) }}</div>
                        @endif
                        @if($klaim->status === 'issued')
                        <span class="text-muted small"><i class="bi bi-check-circle-fill text-success"></i> {{ $klaim->issued_at?->format('d/m/y') }}</span>
                        @endif
                    </td>
                </tr>

                {{-- Modal Approve --}}
                @can('loyalty.klaim.approve')
                @if($klaim->status === 'pending')
                <div class="modal fade" id="approveModal{{ $klaim->id }}" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('loyalty-klaim.approve', $klaim) }}">
                                @csrf
                                <div class="modal-header">
                                    <h6 class="modal-title">Setujui Klaim — {{ $klaim->pelanggan->nama_pelanggan ?? '-' }}</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <label class="form-label">Nominal Voucher (Rp) <span class="text-danger">*</span></label>
                                    <input type="number" step="1" min="0" name="nominal_voucher" class="form-control" required
                                        value="{{ $klaim->loyaltyProgram->nominal_voucher ?? '' }}">
                                    <small class="text-muted">Default dari nominal voucher program — bisa diubah manual.</small>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Setujui</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endif
                @endcan

                {{-- Modal Reject --}}
                @can('loyalty.klaim.reject')
                @if($klaim->status === 'pending')
                <div class="modal fade" id="rejectModal{{ $klaim->id }}" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('loyalty-klaim.reject', $klaim) }}">
                                @csrf
                                <div class="modal-header">
                                    <h6 class="modal-title">Tolak Klaim — {{ $klaim->pelanggan->nama_pelanggan ?? '-' }}</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                                    <textarea name="rejected_reason" class="form-control" rows="2" required placeholder="mis. Bukti tidak valid / tidak menandai akun resmi"></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg me-1"></i>Tolak</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endif
                @endcan

                {{-- Modal Issued --}}
                @can('loyalty.klaim.issued')
                @if($klaim->status === 'approved')
                <div class="modal fade" id="issuedModal{{ $klaim->id }}" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('loyalty-klaim.issued', $klaim) }}">
                                @csrf
                                <div class="modal-header">
                                    <h6 class="modal-title">Tandai Voucher Diberikan — {{ $klaim->pelanggan->nama_pelanggan ?? '-' }}</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="small text-muted mb-2">Nominal voucher: <strong>Rp {{ number_format($klaim->nominal_voucher,0,',','.') }}</strong></p>
                                    <label class="form-label">Catatan (opsional)</label>
                                    <input type="text" name="catatan_issued" class="form-control" placeholder="mis. Diberikan tunai di kasir">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-gift me-1"></i>Tandai Diberikan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endif
                @endcan
                @empty
                <tr>
                    <td colspan="{{ $showProgramColumn ? 8 : 7 }}" class="text-center text-muted py-4">Belum ada klaim.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($klaims, 'hasPages') && $klaims->hasPages())
    <div class="card-footer">{{ $klaims->links() }}</div>
    @endif
</div>
