@extends('layouts.app')

@section('title', $karyawan->nama_lengkap)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold">Detail Karyawan</h4>
    </div>
    <div class="d-flex gap-2">
        @can('karyawan.edit')
        <a href="{{ route('karyawan.edit', $karyawan) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        @endcan
        <a href="{{ route('karyawan.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

{{-- Header Profil --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            @if($karyawan->foto)
                <img src="{{ Storage::url($karyawan->foto) }}"
                     style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid #e2e8f0">
            @else
                <div style="width:80px;height:80px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-size:2rem;color:#94a3b8">
                    <i class="bi bi-person"></i>
                </div>
            @endif
            <div>
                <h5 class="mb-1">{{ $karyawan->nama_lengkap }}</h5>
                <div class="text-muted small">{{ $karyawan->jabatan }} &bull; {{ $karyawan->nik ?? '-' }}</div>
                <div class="text-muted small">{{ $karyawan->cabang?->nama_cabang }}</div>
                <div class="mt-1">
                    @if($karyawan->status === 'aktif')
                        <span class="badge bg-success">Aktif</span>
                    @elseif($karyawan->status === 'tidak_aktif')
                        <span class="badge bg-warning text-dark">Tidak Aktif</span>
                    @else
                        <span class="badge bg-secondary">Keluar</span>
                    @endif
                    <span class="badge bg-light text-dark border ms-1">
                        {{ ucfirst($karyawan->tipe_karyawan) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- Info Pribadi --}}
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header">Informasi Pribadi</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted" width="45%">Jenis Kelamin</td><td>{{ $karyawan->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</td></tr>
                    <tr><td class="text-muted">Tanggal Lahir</td><td>{{ $karyawan->tanggal_lahir?->format('d/m/Y') ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Telepon</td><td>{{ $karyawan->telepon ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Alamat</td><td>{{ $karyawan->alamat ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Tanggal Masuk</td><td>{{ $karyawan->tanggal_masuk?->format('d/m/Y') ?? '-' }}</td></tr>
                    @if($karyawan->tanggal_keluar)
                    <tr><td class="text-muted">Tanggal Keluar</td><td>{{ $karyawan->tanggal_keluar->format('d/m/Y') }}</td></tr>
                    @endif
                    <tr><td class="text-muted">Atasan</td><td>{{ $karyawan->atasan?->nama_lengkap ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Gaji Pokok</td><td>Rp {{ number_format($karyawan->gaji_pokok, 0, ',', '.') }}</td></tr>
                    <tr><td class="text-muted">No. Rekening</td><td>{{ $karyawan->no_rekening ? "{$karyawan->nama_bank} - {$karyawan->no_rekening}" : '-' }}</td></tr>
                </table>
                @if($karyawan->catatan)
                <div class="mt-2 p-2 bg-light rounded small">
                    <strong>Catatan:</strong> {{ $karyawan->catatan }}
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Absensi Bulan Ini --}}
    <div class="col-12 col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Absensi Bulan Ini</span>
                <a href="{{ route('absensi.rekap', ['bulan' => now()->month, 'tahun' => now()->year]) }}"
                   class="btn btn-xs btn-outline-secondary btn-sm">Lihat Rekap</a>
            </div>
            <div class="card-body">
                <div class="row g-2 text-center">
                    <div class="col-4 col-sm-2">
                        <div class="p-2 rounded bg-success bg-opacity-10">
                            <div class="h5 mb-0 text-success">{{ $absensiSummary['hadir'] }}</div>
                            <small class="text-muted">Hadir</small>
                        </div>
                    </div>
                    <div class="col-4 col-sm-2">
                        <div class="p-2 rounded bg-warning bg-opacity-10">
                            <div class="h5 mb-0 text-warning">{{ $absensiSummary['izin'] }}</div>
                            <small class="text-muted">Izin</small>
                        </div>
                    </div>
                    <div class="col-4 col-sm-2">
                        <div class="p-2 rounded bg-info bg-opacity-10">
                            <div class="h5 mb-0 text-info">{{ $absensiSummary['sakit'] }}</div>
                            <small class="text-muted">Sakit</small>
                        </div>
                    </div>
                    <div class="col-4 col-sm-2">
                        <div class="p-2 rounded bg-danger bg-opacity-10">
                            <div class="h5 mb-0 text-danger">{{ $absensiSummary['alpha'] }}</div>
                            <small class="text-muted">Alpha</small>
                        </div>
                    </div>
                    <div class="col-4 col-sm-2">
                        <div class="p-2 rounded bg-secondary bg-opacity-10">
                            <div class="h5 mb-0 text-secondary">{{ $absensiSummary['cuti'] }}</div>
                            <small class="text-muted">Cuti</small>
                        </div>
                    </div>
                    <div class="col-4 col-sm-2">
                        <div class="p-2 rounded bg-primary bg-opacity-10">
                            <div class="h5 mb-0 text-primary">{{ number_format($absensiSummary['jam_lembur'], 1) }}</div>
                            <small class="text-muted">Jam Lembur</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Penggajian Terakhir --}}
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Penggajian Terakhir</span>
                @can('role:owner,admin_pusat,manajer_cabang')
                <a href="{{ route('penggajian.index') }}" class="btn btn-xs btn-outline-secondary btn-sm">Lihat Semua</a>
                @endcan
            </div>
            <div class="card-body p-0">
                @forelse($penggajians as $p)
                <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold small">{{ $p->periode }}</div>
                        <small class="text-muted">Hadir {{ $p->jumlah_hadir }} hari &bull; Alpha {{ $p->jumlah_alpha }} hari</small>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold">Rp {{ number_format($p->total_gaji, 0, ',', '.') }}</div>
                        <span class="badge {{ $p->status === 'dibayar' ? 'bg-success' : ($p->status === 'disetujui' ? 'bg-primary' : 'bg-secondary') }} small">
                            {{ ucfirst($p->status) }}
                        </span>
                    </div>
                </div>
                @empty
                <div class="p-3 text-muted text-center small">Belum ada data penggajian</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Evaluasi Terakhir --}}
@if($evaluasiTerakhir)
<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Evaluasi Terakhir — {{ $evaluasiTerakhir->period?->nama_periode }}</span>
        @if(auth()->user()->can('evaluasi.view') || $karyawan->user_id === auth()->id())
        <a href="{{ route('evaluasi.result', $evaluasiTerakhir) }}" class="btn btn-sm btn-outline-primary">
            Lihat Detail
        </a>
        @endif
    </div>
    <div class="card-body">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="text-center">
                <div style="font-size:2rem;font-weight:800;color:var(--bs-primary)">
                    {{ number_format($evaluasiTerakhir->skor_akhir, 2) }}
                </div>
                <small class="text-muted">Skor Akhir</small>
            </div>
            @if($evaluasiTerakhir->predikat)
            <div>
                <span class="badge fs-6 {{ $evaluasiTerakhir->predikat->badgeClass() }}">
                    {{ $evaluasiTerakhir->predikat->label() }}
                </span>
            </div>
            @endif
        </div>
    </div>
</div>
@endif
@endsection
