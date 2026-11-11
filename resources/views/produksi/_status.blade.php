{{-- Lencana status batch produksi. BABA: Diperam (siap tanggal X) / Siap disaring --}}
@if($b->status === 'diperam')
    @if($b->siap_diselesaikan)
    <span class="badge bg-info text-dark" title="Pemeraman selesai, tinggal Selesai Peram & Saring"><i class="bi bi-hourglass-bottom me-1"></i>Siap disaring</span>
    @else
    <span class="badge bg-warning text-dark" title="Bahan sudah dipotong, hasil belum masuk stok"><i class="bi bi-hourglass-split me-1"></i>Diperam · siap {{ $b->tanggal_siap?->format('d/m') }}</span>
    @endif
@elseif($b->status === 'selesai')
    <span class="badge bg-success">Selesai</span>
@else
    <span class="badge bg-danger">Dibatalkan</span>
@endif
