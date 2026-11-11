{{--
    Widget Ringkasan Neraca (Per Hari Ini) — FASE 2 Akuntansi. Dipakai
    dashboard/cabang.blade.php dan dashboard/pusat.blade.php. Murni tampilan,
    data dari NeracaService::hitungNeraca().

    Props:
        neraca – array hasil NeracaService::hitungNeraca()
--}}
@props(['neraca'])
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
                <span><i class="bi bi-clipboard2-data me-2 text-primary"></i>Ringkasan Neraca (Per Hari Ini)</span>
                <a href="{{ route('laporan.neraca.index') }}" class="btn btn-sm btn-outline-primary" style="font-size:0.75rem">
                    Lihat Detail
                </a>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-6 col-md-3">
                        <div class="text-muted" style="font-size:0.72rem">Total Aset</div>
                        <div class="fw-bold fs-6 fs-md-5">Rp {{ number_format($neraca['aset']['total_aset'], 0, ',', '.') }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted" style="font-size:0.72rem">Total Kewajiban</div>
                        <div class="fw-bold fs-6 fs-md-5 text-warning">Rp {{ number_format($neraca['kewajiban']['total_kewajiban'], 0, ',', '.') }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted" style="font-size:0.72rem">Total Modal</div>
                        <div class="fw-bold fs-6 fs-md-5 text-success">Rp {{ number_format($neraca['modal']['total_modal'], 0, ',', '.') }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted" style="font-size:0.72rem">Status Balance</div>
                        <div class="fw-bold fs-6 fs-md-5 {{ $neraca['balance_check'] ? 'text-success' : 'text-danger' }}">
                            <i class="bi bi-{{ $neraca['balance_check'] ? 'check-circle-fill' : 'exclamation-triangle-fill' }} me-1"></i>{{ $neraca['balance_check'] ? 'Balance' : 'Belum Balance' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
