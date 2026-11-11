{{--
    Widget ringkasan status PO — dipakai dashboard/cabang.blade.php dan
    dashboard/pusat.blade.php. Murni tampilan, badge umur visual only
    (bukan alert/notifikasi). Data dari PoDashboardService::getRingkasanStatus().

    Props:
        ringkasan – array 5 bucket dari PoDashboardService::getRingkasanStatus()
--}}
@props(['ringkasan'])
@php
    $items = [
        ['key' => 'menunggu_approval', 'label' => 'Menunggu Approval', 'icon' => 'hourglass-split'],
        ['key' => 'perlu_dikirim', 'label' => 'Perlu Dikirim', 'icon' => 'box-arrow-up-right'],
        ['key' => 'dalam_perjalanan', 'label' => 'Dalam Perjalanan', 'icon' => 'truck'],
        ['key' => 'belum_diterima', 'label' => 'Belum Diterima', 'icon' => 'hourglass-split'],
        ['key' => 'belum_dibayar', 'label' => 'Belum Dibayar', 'icon' => 'cash-coin'],
    ];
@endphp
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
                <span><i class="bi bi-clipboard-data me-2 text-primary"></i>Status PO</span>
                <a href="{{ route('pembelian.po-dashboard.index') }}" class="btn btn-sm btn-outline-primary" style="font-size:0.75rem">
                    Lihat Detail
                </a>
            </div>
            <div class="card-body">
                <div class="row g-2 text-center">
                    @foreach($items as $it)
                    @php $r = $ringkasan[$it['key']]; @endphp
                    <div class="col-6 col-md">
                        <div class="p-2 border rounded h-100">
                            <i class="bi bi-{{ $it['icon'] }} text-muted"></i>
                            <div class="fw-bold fs-5">{{ $r['count'] }}</div>
                            <div class="text-muted" style="font-size:0.7rem">{{ $it['label'] }}</div>
                            <div class="small text-muted">Rp {{ number_format($r['total_nilai'], 0, ',', '.') }}</div>
                            @if($r['count'] > 0)
                            <span class="badge bg-{{ $r['badge_umur'] }} bg-opacity-75 mt-1" style="font-size:0.65rem">
                                Maks {{ $r['umur_maks_hari'] }} hari
                            </span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
