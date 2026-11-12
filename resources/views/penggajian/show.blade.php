@extends('layouts.app')

@section('title', 'Slip Gaji - ' . $penggajian->karyawan?->nama_lengkap)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-bold">Detail Slip Gaji</h4>
    <div class="d-flex gap-2 flex-wrap">
        @if($penggajian->status === 'draft')
        @can('penggajian.manage')
        <a href="{{ route('penggajian.edit', $penggajian) }}" class="btn btn-sm btn-outline-warning">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        @endcan
        @endif
        <a href="{{ route('penggajian.cetak', $penggajian) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Cetak
        </a>
        @if($penggajian->status === 'draft')
        @can('penggajian.manage')
        <form action="{{ route('penggajian.approve', $penggajian) }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Setujui</button>
        </form>
        @endcan
        @endif
        @if(in_array($penggajian->status, ['draft','disetujui']))
        @can('penggajian.manage')
        <form action="{{ route('penggajian.bayar', $penggajian) }}" method="POST" class="d-inline-flex gap-1 align-items-center"
              onsubmit="return confirm('Tandai gaji ini sudah dibayar? Pengeluaran gaji dicatat di kas yang dipilih.')">
            @csrf
            <select name="kas_id" class="form-select form-select-sm" style="max-width:220px" title="Kas pembayaran gaji" data-uji="kas-gaji">
                @forelse(($kasList ?? collect()) as $k)
                <option value="{{ $k->id }}" @selected($k->default_untuk === 'transfer')>{{ $k->nama_kas }} (Rp {{ number_format($k->saldo_sekarang, 0, ',', '.') }})</option>
                @empty
                <option value="">Kas Transfer {{ $penggajian->cabang?->nama_cabang }} (dibuat otomatis)</option>
                @endforelse
            </select>
            <button class="btn btn-sm btn-success text-nowrap"><i class="bi bi-cash me-1"></i>Tandai Dibayar</button>
        </form>
        @endcan
        @endif
        <a href="{{ route('penggajian.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

@php
    $statusClass = match($penggajian->status) {
        'dibayar'   => 'bg-success',
        'disetujui' => 'bg-primary',
        default     => 'bg-secondary',
    };
    $statusLabel = match($penggajian->status) {
        'dibayar'   => 'Dibayar',
        'disetujui' => 'Disetujui',
        default     => 'Draft',
    };
@endphp

<div class="row g-3">
    {{-- Kolom Kiri: Slip Gaji --}}
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Slip Gaji — {{ \Carbon\Carbon::createFromFormat('Y-m', $penggajian->periode)->translatedFormat('F Y') }}</span>
                <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
            </div>
            <div class="card-body">
                {{-- Info Karyawan --}}
                <div class="row g-2 mb-3">
                    <div class="col-6 col-md-3">
                        <small class="text-muted">Nama Karyawan</small>
                        <div class="fw-semibold">{{ $penggajian->karyawan?->nama_lengkap }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <small class="text-muted">NIK</small>
                        <div>{{ $penggajian->karyawan?->nik ?? '-' }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <small class="text-muted">Jabatan</small>
                        <div>{{ $penggajian->karyawan?->jabatan }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <small class="text-muted">Cabang</small>
                        <div>{{ $penggajian->cabang?->nama_cabang }}</div>
                    </div>
                </div>

                <hr>

                <div class="row g-3">
                    {{-- PENAMBAHAN --}}
                    <div class="col-12 col-md-6">
                        <div class="fw-semibold text-success mb-2">
                            <i class="bi bi-plus-circle me-1"></i>Penambahan
                        </div>

                        @php
                            $penambahanItems = [
                                'Gaji Pokok'              => $penggajian->gaji_pokok,
                                'Tunjangan Jabatan'       => $penggajian->tunjangan_jabatan,
                                'Tunjangan Makan'         => $penggajian->tunjangan_makan,
                                'Tunjangan Transport'     => $penggajian->tunjangan_transport,
                                'Tunjangan Kehadiran'     => $penggajian->tunjangan_kehadiran,
                                'Uang Lembur (' . number_format($penggajian->jam_lembur_total, 1) . ' jam)' => $penggajian->uang_lembur,
                                'Bonus'                   => $penggajian->bonus,
                                'Insentif'                => $penggajian->insentif,
                                'THR'                     => $penggajian->thr,
                                'Komisi'                  => $penggajian->komisi,
                                'Tunjangan Lain'          => $penggajian->tunjangan,
                            ];
                            $totalPenambahan = array_sum(array_map('floatval', $penambahanItems));
                        @endphp

                        @foreach($penambahanItems as $label => $nilai)
                        @if((float)$nilai > 0)
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">{{ $label }}</span>
                            <span>Rp {{ number_format($nilai, 0, ',', '.') }}</span>
                        </div>
                        @endif
                        @endforeach

                        <div class="d-flex justify-content-between py-1 fw-semibold text-success mt-1">
                            <span>Total Pendapatan</span>
                            <span>Rp {{ number_format($totalPenambahan, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- PENGURANGAN --}}
                    <div class="col-12 col-md-6">
                        <div class="fw-semibold text-danger mb-2">
                            <i class="bi bi-dash-circle me-1"></i>Potongan
                        </div>

                        @php
                            $potonganItems = [
                                'Potongan Alpha (' . $penggajian->jumlah_alpha . ' hari)' => $penggajian->potongan_absensi,
                                'BPJS Kesehatan'           => $penggajian->bpjs_kesehatan,
                                'BPJS Ketenagakerjaan JHT' => $penggajian->bpjs_ketenagakerjaan,
                                'PPh21'                    => $penggajian->pph21,
                                'Kasbon'                   => $penggajian->kasbon,
                                'Potongan Lain'            => $penggajian->potongan_lain,
                            ];
                            $totalPotongan = array_sum(array_map('floatval', $potonganItems));
                        @endphp

                        @foreach($potonganItems as $label => $nilai)
                        @if((float)$nilai > 0)
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">{{ $label }}</span>
                            <span class="text-danger">- Rp {{ number_format($nilai, 0, ',', '.') }}</span>
                        </div>
                        @endif
                        @endforeach

                        @if($totalPotongan == 0)
                        <div class="text-muted small py-1">Tidak ada potongan</div>
                        @else
                        <div class="d-flex justify-content-between py-1 fw-semibold text-danger mt-1">
                            <span>Total Potongan</span>
                            <span>- Rp {{ number_format($totalPotongan, 0, ',', '.') }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold fs-5">TOTAL GAJI BERSIH</span>
                    <span class="fw-bold fs-5 text-primary">Rp {{ number_format($penggajian->total_gaji, 0, ',', '.') }}</span>
                </div>

                @if($penggajian->tanggal_bayar)
                <div class="mt-2 text-muted small">Dibayar pada: {{ $penggajian->tanggal_bayar->format('d/m/Y') }}
                    @if($trxGaji ?? null)<span data-uji="jurnal-gaji"> — dicatat sebagai pengeluaran Gaji di {{ $trxGaji->kas?->nama_kas }} ({{ $trxGaji->nomor_transaksi }})</span>@endif</div>
                @endif
                @if($penggajian->approvedBy)
                <div class="text-muted small">Disetujui oleh: {{ $penggajian->approvedBy->name }}</div>
                @endif
                @if($penggajian->catatan)
                <div class="mt-2 p-2 bg-light rounded"><small class="text-muted">Catatan:</small> {{ $penggajian->catatan }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Kolom Kanan --}}
    <div class="col-12 col-lg-4">
        {{-- Rekap Absensi --}}
        <div class="card mb-3">
            <div class="card-header">Rekap Absensi</div>
            <div class="card-body">
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="p-2 bg-light rounded">
                            <div class="fw-bold h5 mb-0">{{ $penggajian->jumlah_hari_kerja }}</div>
                            <small class="text-muted">Hari Kerja</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 bg-success bg-opacity-10 rounded">
                            <div class="fw-bold h5 mb-0 text-success">{{ $penggajian->jumlah_hadir }}</div>
                            <small class="text-muted">Hadir</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 bg-danger bg-opacity-10 rounded">
                            <div class="fw-bold h5 mb-0 text-danger">{{ $penggajian->jumlah_alpha }}</div>
                            <small class="text-muted">Alpha</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 bg-primary bg-opacity-10 rounded">
                            <div class="fw-bold h5 mb-0 text-primary">{{ number_format($penggajian->jam_lembur_total, 1) }}</div>
                            <small class="text-muted">Jam Lembur</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Ringkasan --}}
        <div class="card mb-3">
            <div class="card-header">Ringkasan Komponen</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr>
                        <td class="px-3 py-2 text-muted">Gaji Pokok</td>
                        <td class="px-3 py-2 text-end fw-semibold">Rp {{ number_format($penggajian->gaji_pokok, 0, ',', '.') }}</td>
                    </tr>
                    @if((float)$penggajian->tunjangan_jabatan + (float)$penggajian->tunjangan_makan + (float)$penggajian->tunjangan_transport > 0)
                    <tr>
                        <td class="px-3 py-2 text-muted">Tunjangan</td>
                        <td class="px-3 py-2 text-end text-success">
                            + Rp {{ number_format((float)$penggajian->tunjangan_jabatan + (float)$penggajian->tunjangan_makan + (float)$penggajian->tunjangan_transport, 0, ',', '.') }}
                        </td>
                    </tr>
                    @endif
                    @if((float)$penggajian->uang_lembur > 0)
                    <tr>
                        <td class="px-3 py-2 text-muted">Lembur</td>
                        <td class="px-3 py-2 text-end text-success">+ Rp {{ number_format($penggajian->uang_lembur, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    @if((float)$penggajian->bonus + (float)$penggajian->insentif + (float)$penggajian->thr + (float)$penggajian->komisi > 0)
                    <tr>
                        <td class="px-3 py-2 text-muted">Bonus/Insentif/THR</td>
                        <td class="px-3 py-2 text-end text-success">
                            + Rp {{ number_format((float)$penggajian->bonus + (float)$penggajian->insentif + (float)$penggajian->thr + (float)$penggajian->komisi, 0, ',', '.') }}
                        </td>
                    </tr>
                    @endif
                    @if((float)$penggajian->bpjs_kesehatan + (float)$penggajian->bpjs_ketenagakerjaan + (float)$penggajian->pph21 > 0)
                    <tr>
                        <td class="px-3 py-2 text-muted">BPJS + PPh21</td>
                        <td class="px-3 py-2 text-end text-danger">
                            - Rp {{ number_format((float)$penggajian->bpjs_kesehatan + (float)$penggajian->bpjs_ketenagakerjaan + (float)$penggajian->pph21, 0, ',', '.') }}
                        </td>
                    </tr>
                    @endif
                    @if((float)$penggajian->potongan_absensi > 0)
                    <tr>
                        <td class="px-3 py-2 text-muted">Pot. Absensi</td>
                        <td class="px-3 py-2 text-end text-danger">- Rp {{ number_format($penggajian->potongan_absensi, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr class="table-primary">
                        <td class="px-3 py-2 fw-bold">Take Home Pay</td>
                        <td class="px-3 py-2 text-end fw-bold text-primary">Rp {{ number_format($penggajian->total_gaji, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- Rekening --}}
        @if($penggajian->karyawan?->no_rekening)
        <div class="card">
            <div class="card-header">Rekening Pembayaran</div>
            <div class="card-body">
                <div class="fw-semibold">{{ $penggajian->karyawan->nama_bank ?? '-' }}</div>
                <div>{{ $penggajian->karyawan->no_rekening }}</div>
                <div class="text-muted small">a.n. {{ $penggajian->karyawan->nama_lengkap }}</div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
