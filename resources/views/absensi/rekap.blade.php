@extends('layouts.app')

@section('title', 'Rekap Absensi Bulanan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold">Rekap Absensi Bulanan</h4>
        <small class="text-muted">Ringkasan kehadiran per karyawan</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('absensi.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Input Absensi
        </a>
        <x-panduan-button slug="rekap-absensi" />
    </div>
</div>

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Bulan</label>
                <select name="bulan" class="form-select form-select-sm">
                    @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ $bulan == $m ? 'selected' : '' }}>
                        {{ Carbon\Carbon::create(null, $m)->translatedFormat('F') }}
                    </option>
                    @endfor
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Tahun</label>
                <select name="tahun" class="form-select form-select-sm">
                    @for($y = now()->year; $y >= now()->year - 3; $y--)
                    <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            @if(isset($authUser) && $authUser->canAccessAllBranches())
            <div class="col-12 col-md-3">
                <label class="form-label small mb-1">Cabang</label>
                <select name="cabang_id" class="form-select form-select-sm">
                    <option value="">Semua Cabang</option>
                    @foreach($cabangs as $c)
                    <option value="{{ $c->id }}" {{ $cabangId == $c->id ? 'selected' : '' }}>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-search me-1"></i>Tampilkan</button>
            </div>
        </form>
    </div>
</div>

{{-- Tabel --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead>
                <tr>
                    <th>Karyawan</th>
                    <th class="text-center text-success">Hadir</th>
                    <th class="text-center text-warning d-none d-md-table-cell">Izin</th>
                    <th class="text-center text-info d-none d-md-table-cell">Sakit</th>
                    <th class="text-center text-danger">Alpha</th>
                    <th class="text-center text-muted d-none d-sm-table-cell">Libur</th>
                    <th class="text-center text-secondary d-none d-sm-table-cell">Cuti</th>
                    <th class="text-center text-primary">Lembur (jam)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rekap as $row)
                <tr>
                    <td>
                        <div class="fw-semibold small">{{ $row['karyawan']->nama_lengkap }}</div>
                        <small class="text-muted">{{ $row['karyawan']->jabatan }}</small>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-success">{{ $row['hadir'] }}</span>
                    </td>
                    <td class="text-center d-none d-md-table-cell">
                        <span class="badge bg-warning text-dark">{{ $row['izin'] }}</span>
                    </td>
                    <td class="text-center d-none d-md-table-cell">
                        <span class="badge bg-info">{{ $row['sakit'] }}</span>
                    </td>
                    <td class="text-center">
                        @if($row['alpha'] > 0)
                            <span class="badge bg-danger">{{ $row['alpha'] }}</span>
                        @else
                            <span class="text-muted">0</span>
                        @endif
                    </td>
                    <td class="text-center d-none d-sm-table-cell text-muted">{{ $row['libur'] }}</td>
                    <td class="text-center d-none d-sm-table-cell text-muted">{{ $row['cuti'] }}</td>
                    <td class="text-center">
                        @if($row['jam_lembur'] > 0)
                            <span class="fw-bold text-primary">{{ number_format($row['jam_lembur'], 1) }}</span>
                        @else
                            <span class="text-muted">0</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data untuk periode ini.</td></tr>
                @endforelse
            </tbody>
            @if($rekap->count() > 0)
            <tfoot class="table-secondary fw-bold">
                <tr>
                    <td>Total</td>
                    <td class="text-center">{{ $rekap->sum('hadir') }}</td>
                    <td class="text-center d-none d-md-table-cell">{{ $rekap->sum('izin') }}</td>
                    <td class="text-center d-none d-md-table-cell">{{ $rekap->sum('sakit') }}</td>
                    <td class="text-center">{{ $rekap->sum('alpha') }}</td>
                    <td class="text-center d-none d-sm-table-cell">{{ $rekap->sum('libur') }}</td>
                    <td class="text-center d-none d-sm-table-cell">{{ $rekap->sum('cuti') }}</td>
                    <td class="text-center">{{ number_format($rekap->sum('jam_lembur'), 1) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
