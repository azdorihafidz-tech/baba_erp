@extends('layouts.app')

@section('title', 'Input Absensi Harian')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold">Absensi Harian</h4>
        <small class="text-muted">Input kehadiran karyawan</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('absensi.rekap') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-table me-1"></i>Rekap Bulanan
        </a>
        <x-panduan-button slug="absensi-manual" />
    </div>
</div>

{{-- Pilih Tanggal --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
            <label class="form-label mb-0 fw-semibold">Tanggal:</label>
            <input type="date" name="tanggal" class="form-control form-control-sm"
                   style="width:auto" value="{{ $tanggal->format('Y-m-d') }}">
            <button type="submit" class="btn btn-sm btn-secondary">Tampilkan</button>
            <span class="ms-auto text-muted small">
                {{ $tanggal->translatedFormat('l, d F Y') }}
                &bull; {{ $karyawans->count() }} karyawan aktif
            </span>
        </form>
    </div>
</div>

{{-- Cari Karyawan — filter tampilan client-side saja (tidak menghapus baris dari form submit) --}}
<div class="mb-3" style="max-width:320px">
    <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" id="filterKaryawanAbsensi" class="form-control form-control-sm" placeholder="Cari nama / jabatan...">
    </div>
</div>

<form action="{{ route('absensi.store') }}" method="POST">
    @csrf
    <input type="hidden" name="tanggal" value="{{ $tanggal->format('Y-m-d') }}">

    {{-- Tabel Desktop --}}
    <div class="card d-none d-lg-block">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:30px">#</th>
                        <th>Karyawan</th>
                        <th style="min-width:130px">Status</th>
                        <th style="min-width:110px">Jam Masuk</th>
                        <th style="min-width:110px">Jam Keluar</th>
                        <th style="min-width:110px">Lembur Masuk</th>
                        <th style="min-width:110px">Lembur Keluar</th>
                        <th>Keterangan</th>
                        <th class="text-center" style="width:70px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($karyawans as $i => $k)
                    @php $existing = $existingAbsensi->get($k->id); @endphp
                    <tr class="{{ $existing ? 'table-light' : '' }}" data-search-row="{{ strtolower($k->nama_lengkap . ' ' . $k->jabatan) }}">
                        <td class="text-muted small">{{ $i + 1 }}</td>
                        <td>
                            <div class="fw-semibold small">
                                {{ $k->nama_lengkap }}
                                @if($existing?->dicatat_oleh)
                                <span class="badge bg-info-subtle text-info border border-info-subtle ms-1" style="font-size:.65rem">Manual</span>
                                @endif
                                @if($existing?->is_telat)
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1" style="font-size:.65rem">Telat {{ $existing->menit_telat }}m</span>
                                @endif
                            </div>
                            <small class="text-muted">{{ $k->jabatan }}</small>
                        </td>
                        <td>
                            <select name="absensi[{{ $i }}][status]" class="form-select form-select-sm absensi-status"
                                    data-index="{{ $i }}">
                                <option value="hadir" {{ ($existing?->status ?? 'hadir') === 'hadir' ? 'selected' : '' }}>Hadir</option>
                                <option value="izin" {{ ($existing?->status) === 'izin' ? 'selected' : '' }}>Izin</option>
                                <option value="sakit" {{ ($existing?->status) === 'sakit' ? 'selected' : '' }}>Sakit</option>
                                <option value="alpha" {{ ($existing?->status) === 'alpha' ? 'selected' : '' }}>Alpha</option>
                                <option value="libur" {{ ($existing?->status) === 'libur' ? 'selected' : '' }}>Libur</option>
                                <option value="cuti" {{ ($existing?->status) === 'cuti' ? 'selected' : '' }}>Cuti</option>
                            </select>
                            <input type="hidden" name="absensi[{{ $i }}][karyawan_id]" value="{{ $k->id }}">
                        </td>
                        <td>
                            <input type="time" name="absensi[{{ $i }}][jam_masuk]"
                                   class="form-control form-control-sm jam-field-{{ $i }}"
                                   value="{{ $existing?->jam_masuk?->format('H:i') }}">
                        </td>
                        <td>
                            <input type="time" name="absensi[{{ $i }}][jam_keluar]"
                                   class="form-control form-control-sm jam-field-{{ $i }}"
                                   value="{{ $existing?->jam_keluar?->format('H:i') }}">
                        </td>
                        <td>
                            <input type="time" name="absensi[{{ $i }}][jam_lembur_masuk]"
                                   class="form-control form-control-sm"
                                   value="{{ $existing?->jam_lembur_masuk?->format('H:i') }}">
                        </td>
                        <td>
                            <input type="time" name="absensi[{{ $i }}][jam_lembur_keluar]"
                                   class="form-control form-control-sm"
                                   value="{{ $existing?->jam_lembur_keluar?->format('H:i') }}">
                        </td>
                        <td>
                            <input type="text" name="absensi[{{ $i }}][keterangan]"
                                   class="form-control form-control-sm"
                                   value="{{ $existing?->keterangan }}"
                                   placeholder="Opsional">
                        </td>
                        <td class="text-center">
                            @if($existing)
                            <div class="d-flex gap-1 justify-content-center">
                                <a href="{{ route('absensi.edit', $existing) }}" class="btn btn-outline-secondary btn-sm" title="Edit langsung">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @can('hapus_absensi')
                                <button type="button" class="btn btn-outline-danger btn-sm" title="Hapus absensi"
                                        onclick="hapusAbsensi('{{ route('absensi.destroy', $existing) }}','{{ addslashes($k->nama_lengkap) }}','{{ $existing->tanggal->format('d/m/Y') }}')">
                                    <i class="bi bi-trash"></i>
                                </button>
                                @endif
                            </div>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-muted">Data akan disimpan atau diperbarui (updateOrCreate)</small>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i>Simpan Absensi
            </button>
        </div>
    </div>

    {{-- Card View Mobile/Tablet --}}
    <div class="d-lg-none">
        @foreach($karyawans as $i => $k)
        @php $existing = $existingAbsensi->get($k->id); @endphp
        <div class="card mb-2 {{ $existing ? 'border-primary border-opacity-25' : '' }}" data-search-row="{{ strtolower($k->nama_lengkap . ' ' . $k->jabatan) }}">
            <div class="card-body py-2 px-3">
                <input type="hidden" name="absensi[{{ $i }}][karyawan_id]" value="{{ $k->id }}">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-semibold">
                            {{ $k->nama_lengkap }}
                            @if($existing?->dicatat_oleh)
                            <span class="badge bg-info-subtle text-info border border-info-subtle ms-1" style="font-size:.65rem">Manual</span>
                            @endif
                        </div>
                        <small class="text-muted">{{ $k->jabatan }}</small>
                    </div>
                    @if($existing)
                    <div class="d-flex align-items-center gap-1">
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 small">Sudah Isi</span>
                        @can('hapus_absensi')
                        <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" title="Hapus" style="min-height:28px"
                                onclick="hapusAbsensi('{{ route('absensi.destroy', $existing) }}','{{ addslashes($k->nama_lengkap) }}','{{ $existing->tanggal->format('d/m/Y') }}')">
                            <i class="bi bi-trash" style="font-size:.7rem"></i>
                        </button>
                        @endif
                    </div>
                    @endif
                </div>
                <div class="row g-2">
                    <div class="col-12">
                        <select name="absensi[{{ $i }}][status]" class="form-select form-select-sm">
                            <option value="hadir" {{ ($existing?->status ?? 'hadir') === 'hadir' ? 'selected' : '' }}>Hadir</option>
                            <option value="izin" {{ ($existing?->status) === 'izin' ? 'selected' : '' }}>Izin</option>
                            <option value="sakit" {{ ($existing?->status) === 'sakit' ? 'selected' : '' }}>Sakit</option>
                            <option value="alpha" {{ ($existing?->status) === 'alpha' ? 'selected' : '' }}>Alpha</option>
                            <option value="libur" {{ ($existing?->status) === 'libur' ? 'selected' : '' }}>Libur</option>
                            <option value="cuti" {{ ($existing?->status) === 'cuti' ? 'selected' : '' }}>Cuti</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label form-label-sm mb-1 text-muted">Masuk</label>
                        <input type="time" name="absensi[{{ $i }}][jam_masuk]"
                               class="form-control form-control-sm"
                               value="{{ $existing?->jam_masuk?->format('H:i') }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label form-label-sm mb-1 text-muted">Keluar</label>
                        <input type="time" name="absensi[{{ $i }}][jam_keluar]"
                               class="form-control form-control-sm"
                               value="{{ $existing?->jam_keluar?->format('H:i') }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label form-label-sm mb-1 text-muted">Lembur Masuk</label>
                        <input type="time" name="absensi[{{ $i }}][jam_lembur_masuk]"
                               class="form-control form-control-sm"
                               value="{{ $existing?->jam_lembur_masuk?->format('H:i') }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label form-label-sm mb-1 text-muted">Lembur Keluar</label>
                        <input type="time" name="absensi[{{ $i }}][jam_lembur_keluar]"
                               class="form-control form-control-sm"
                               value="{{ $existing?->jam_lembur_keluar?->format('H:i') }}">
                    </div>
                    <div class="col-12">
                        <input type="text" name="absensi[{{ $i }}][keterangan]"
                               class="form-control form-control-sm"
                               value="{{ $existing?->keterangan }}"
                               placeholder="Keterangan (opsional)">
                    </div>
                </div>
            </div>
        </div>
        @endforeach

        @if($karyawans->count() > 0)
        <div class="d-grid mt-3">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check-lg me-1"></i>Simpan Semua Absensi
            </button>
        </div>
        @endif
    </div>
</form>

@can('hapus_absensi')
{{-- Form hapus absensi — di luar batch form agar tidak nested --}}
<form id="form-hapus-absensi" method="POST" action="" style="display:none">
    @csrf
    @method('DELETE')
</form>
@endcan

@if($karyawans->isEmpty())
<div class="text-center text-muted py-5">
    <i class="bi bi-people" style="font-size:3rem"></i>
    <p class="mt-2">Tidak ada karyawan aktif di cabang ini.</p>
</div>
@endif

@push('scripts')
<script>
function hapusAbsensi(url, nama, tgl) {
    if (!confirm('Hapus absensi ' + nama + ' tanggal ' + tgl + '? Data jam masuk/keluar/lembur akan terhapus.')) return;
    const f = document.getElementById('form-hapus-absensi');
    if (!f) return;
    f.action = url;
    f.submit();
}

// Filter tampilan client-side — baris tetap ada di DOM (cuma disembunyikan)
// supaya form batch submit tidak kehilangan data karyawan yang tidak match.
(function() {
    var input = document.getElementById('filterKaryawanAbsensi');
    if (!input) return;
    input.addEventListener('input', function() {
        var keyword = input.value.trim().toLowerCase();
        document.querySelectorAll('[data-search-row]').forEach(function(row) {
            var match = row.dataset.searchRow.includes(keyword);
            row.style.display = match ? '' : 'none';
        });
    });
})();
</script>
@endpush
@endsection
