{{--
    Widget snapshot Aset — dipakai dashboard/cabang.blade.php dan
    dashboard/pusat.blade.php. Murni tampilan, data dari
    AssetDepreciationService::getSnapshotDashboard().

    Props:
        data – array (total_harga_perolehan, total_akumulasi_depresiasi,
               total_nilai_buku, depresiasi_bulan_ini)
--}}
@props(['data'])
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
                <span><i class="bi bi-building-gear me-2 text-primary"></i>Total Nilai Aset</span>
                <a href="{{ route('aset.index') }}" class="btn btn-sm btn-outline-primary" style="font-size:0.75rem">
                    Lihat Detail
                </a>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-4">
                        <div class="text-muted" style="font-size:0.72rem">Harga Beli Kumulatif</div>
                        <div class="fw-bold fs-6 fs-md-5">Rp {{ number_format($data['total_harga_perolehan'], 0, ',', '.') }}</div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted" style="font-size:0.72rem">Akumulasi Depresiasi</div>
                        <div class="fw-bold fs-6 fs-md-5 text-warning">Rp {{ number_format($data['total_akumulasi_depresiasi'], 0, ',', '.') }}</div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted" style="font-size:0.72rem">Nilai Buku Sekarang</div>
                        <div class="fw-bold fs-6 fs-md-5 text-success">Rp {{ number_format($data['total_nilai_buku'], 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header py-3 px-4">
                <span><i class="bi bi-calculator me-2 text-warning"></i>Depresiasi Bulan Ini</span>
            </div>
            <div class="card-body text-center d-flex flex-column justify-content-center">
                <div class="fw-bold fs-4">Rp {{ number_format($data['depresiasi_bulan_ini'], 0, ',', '.') }}</div>
                <div class="text-muted" style="font-size:0.72rem">{{ now()->translatedFormat('F Y') }}</div>
            </div>
        </div>
    </div>
</div>
