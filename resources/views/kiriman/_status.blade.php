@php
    $lbl = ['menunggu' => ['warning text-dark', 'Menunggu diterima'], 'diterima' => ['success', 'Diterima'], 'ditolak' => ['danger', 'Ditolak'], 'dibatalkan' => ['secondary', 'Dibatalkan']][$s] ?? ['light text-dark', $s];
@endphp
<span class="badge bg-{{ $lbl[0] }}">{{ $lbl[1] }}</span>
