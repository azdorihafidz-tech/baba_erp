{{--
    Pengingat Closing / Tutup Buku — hari (7 hari terakhir) yang ada
    penjualannya tapi belum di-closing. :cabang-id diisi = 1 cabang,
    kosong = semua cabang yang boleh diakses user.
--}}
@props(['cabangId' => null])
@php
    $user = auth()->user();
    $belum = [];
    if ($user && $user->can('closing.harian')) {
        $ids = $cabangId
            ? [(int) $cabangId]
            : ($user->canAccessAllBranches()
                ? \App\Models\Cabang::aktif()->pluck('id')->all()
                : $user->cabangs()->pluck('cabangs.id')->all());
        $belum = app(\App\Services\ClosingService::class)->hariBelumClosing($ids);
    }
@endphp
@if(!empty($belum))
<div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
    <i class="bi bi-journal-x fs-5"></i>
    <div class="small flex-grow-1">
        <strong>{{ count($belum) }} hari belum di-closing</strong> (7 hari terakhir):
        {{ collect($belum)->take(6)->map(fn ($b) => \Carbon\Carbon::parse($b['tanggal'])->format('d/m') . ($cabangId ? '' : ' ' . $b['nama_cabang']))->implode(', ') }}{{ count($belum) > 6 ? ', …' : '' }}
    </div>
    @php $pertama = $belum[0]; @endphp
    <a href="{{ route('closing.index', ['jenis' => 'harian', 'tanggal' => $pertama['tanggal'], 'cabang_id' => $pertama['cabang_id']]) }}" class="btn btn-warning btn-sm">
        Closing sekarang
    </a>
</div>
@endif
