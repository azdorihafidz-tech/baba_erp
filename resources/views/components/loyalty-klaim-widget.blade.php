{{--
    Widget "Klaim Menunggu Approval" (Program Loyalty Fase 2, event-based) —
    dipakai dashboard/cabang.blade.php dan dashboard/pusat.blade.php. Murni
    tampilan, data dari LoyaltyKlaimService::getWidgetData(). Klaim TIDAK
    di-scope per cabang (lintas cabang by design, sama seperti widget Loyalty
    Fase 1), jadi widget ini tampil sama di kedua dashboard.

    Props:
        data – array (jumlah_pending, klaim_terbaru)
--}}
@props(['data'])
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
        <span><i class="bi bi-megaphone me-2 text-primary"></i>Klaim Menunggu Approval</span>
        <div class="d-flex align-items-center gap-2">
            @if($data['jumlah_pending'] > 0)
            <span class="badge bg-warning text-dark">
                {{ $data['jumlah_pending'] }} pending
            </span>
            @endif
            <a href="{{ route('loyalty-klaim.index', ['status' => 'pending']) }}" class="btn btn-sm btn-outline-primary" style="font-size:0.75rem">
                Lihat Semua
            </a>
        </div>
    </div>
    <div class="card-body">
        @if($data['klaim_terbaru']->isEmpty())
        <p class="text-muted text-center mb-0 py-2">Tidak ada klaim event loyalty yang menunggu approval.</p>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Pelanggan</th>
                        <th class="d-none d-md-table-cell">Program</th>
                        <th class="d-none d-md-table-cell">Diajukan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['klaim_terbaru'] as $klaim)
                    <tr>
                        <td class="fw-semibold">
                            <a href="{{ route('pelanggan.show', $klaim->pelanggan_id) }}">{{ $klaim->pelanggan->nama_pelanggan ?? '-' }}</a>
                        </td>
                        <td class="d-none d-md-table-cell small text-muted">{{ $klaim->loyaltyProgram->nama ?? '-' }}</td>
                        <td class="d-none d-md-table-cell small text-muted">{{ $klaim->created_at->diffForHumans() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
