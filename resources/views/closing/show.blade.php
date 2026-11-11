@extends('layouts.app')
@section('title', 'Arsip Closing')
@section('content')

@php $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.'); @endphp

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">{{ $closing->jenis_label }} — {{ $closing->label_periode }}</h4>
        <small class="text-muted">
            <i class="bi bi-geo-alt"></i> {{ $closing->nama_cabang }} · versi {{ $closing->versi }} ·
            {{ $closing->periode_mulai->format('d/m/Y') }} s/d {{ $closing->periode_selesai->format('d/m/Y') }}
        </small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('closing.riwayat') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock-history me-1"></i>Riwayat</a>
        @can('closing.export')
        <a href="{{ route('closing.show.export', [$closing, 'format' => 'excel']) }}" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i><span class="d-none d-sm-inline">Excel</span>
        </a>
        <a href="{{ route('closing.show.export', [$closing, 'format' => 'pdf']) }}" class="btn btn-danger btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i><span class="d-none d-sm-inline">PDF</span>
        </a>
        @endcan
        @if($closing->isFinal())
        @can('closing.buka_kunci')
        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalBukaKunci">
            <i class="bi bi-unlock me-1"></i>Buka Kunci
        </button>
        @endcan
        @endif
        <x-panduan-button slug="closing" />
    </div>
</div>

@if($closing->isFinal())
<div class="alert alert-success small d-flex gap-2">
    <i class="bi bi-lock-fill fs-5"></i>
    <div>
        <strong>Terkunci.</strong> Ditutup oleh {{ $closing->ditutupOleh?->name ?? '-' }} pada {{ $closing->ditutup_pada->format('d/m/Y H:i') }}.
        Angka di bawah adalah angka saat closing disimpan dan tidak akan berubah.
        @if($closing->catatan)<br>Catatan: {{ $closing->catatan }}@endif
    </div>
</div>
@else
<div class="alert alert-secondary small d-flex gap-2">
    <i class="bi bi-unlock fs-5"></i>
    <div>
        <strong>Kunci sudah dibuka</strong> oleh {{ $closing->dibukaOleh?->name ?? '-' }} pada {{ $closing->dibuka_pada?->format('d/m/Y H:i') }}.<br>
        Alasan: {{ $closing->alasan_buka }}<br>
        Arsip versi ini tetap disimpan sebagai riwayat. Closing ulang akan tersimpan sebagai versi baru.
        @if($closing->catatan)<br>Catatan closing: {{ $closing->catatan }}@endif
    </div>
</div>
@endif

@if($versiLain->isNotEmpty())
<div class="card mb-3">
    <div class="card-header fw-semibold small">Versi lain periode ini</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0 small">
            @foreach($versiLain as $v)
            <tr>
                <td>Versi {{ $v->versi }}</td>
                <td>{{ $v->ditutup_pada->format('d/m/Y H:i') }}</td>
                <td class="text-end">Omzet {{ $rp($v->omzet) }}</td>
                <td class="text-end">Laba {{ $rp($v->laba_bersih) }}</td>
                <td>{!! $v->status === 'final' ? '<span class="badge bg-success">Terkunci</span>' : '<span class="badge bg-secondary">Dibuka</span>' !!}</td>
                <td><a href="{{ route('closing.show', $v->id) }}">Lihat</a></td>
            </tr>
            @endforeach
        </table>
    </div>
</div>
@endif

@include('closing._laporan', ['data' => $closing->data, 'cabangId' => $closing->cabang_id, 'kasFisik' => $closing->kasRows, 'snapshot' => true])

<div class="small text-muted mt-1">Nilai stok adalah nilai persediaan saat closing disimpan.</div>

@if($closing->isFinal())
@can('closing.buka_kunci')
<div class="modal fade" id="modalBukaKunci" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down">
        <form method="POST" action="{{ route('closing.buka', $closing) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-unlock me-1"></i> Buka Kunci Periode</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="small">{{ $closing->jenis_label }} {{ $closing->label_periode }} · {{ $closing->nama_cabang }}</p>
                <label class="form-label form-label-sm mb-1">Alasan membuka kunci <span class="text-danger">*</span> <x-tooltip key="closing.alasan_buka" /></label>
                <textarea name="alasan" class="form-control form-control-sm @error('alasan') is-invalid @enderror" rows="3" minlength="10" maxlength="1000" required
                    placeholder="Contoh: ada nota pembelian tanggal 12 yang belum diinput">{{ old('alasan') }}</textarea>
                @error('alasan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="alert alert-warning small mt-2 mb-0">
                    Setelah dibuka, transaksi periode ini bisa diubah lagi.
                    @if($closing->jenis === 'bulanan' && $closing->cabang_id)
                    Closing harian di dalam periode ini dan tutup buku keseluruhan periode ini <strong>ikut dibuka</strong>.
                    @endif
                    Jangan lupa closing ulang setelah koreksi selesai.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-unlock me-1"></i>Buka Kunci</button>
            </div>
        </form>
    </div>
</div>
@endcan
@endif
@endsection
