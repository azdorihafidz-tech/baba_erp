@extends('layouts.app')

@section('title', 'Pemeriksaan Komposisi')

@php
    $labelStatus = [
        'cocok'     => ['bg-success', 'Cocok', 'Kode & nama sama'],
        'nama_sama' => ['bg-warning text-dark', 'Kode beda, nama sama', 'Dipakai bahan bernama sama milik cabang tujuan — ganti kalau keliru'],
        'kode_sama' => ['bg-danger', 'Kode sama, nama beda', 'Bisa jadi barang berbeda — silakan pilih'],
        'baru'      => ['bg-primary', 'Belum ada', 'Dibuat baru di cabang tujuan, stok 0'],
        'umum'      => ['bg-secondary', 'Data lama', 'Dipakai semua cabang (belum dipecah)'],
    ];
    $totalPerluPilih = $rencana->sum('perlu_pilih');
@endphp

@section('content')

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-check me-2 text-success"></i>Pemeriksaan Komposisi</h5>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('master.produk-jual.ambil.index', ['sumber' => $sumber->id]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        <x-panduan-button slug="ambil-produk-cabang" />
    </div>
</div>

<div class="alert alert-info small py-2">
    <i class="bi bi-info-circle me-1"></i>
    Produk dari <strong>{{ $sumber->nama_cabang }}</strong> akan disalin jadi milik cabang tujuan. Setiap bahan komposisi
    dicocokkan ke <strong>bahan milik cabang tujuan</strong> lewat Kode Item, lalu dicek namanya. Periksa baris berwarna kuning/merah sebelum menyimpan.
</div>

@if($errors->any())
    <div class="alert alert-danger small">
        <i class="bi bi-exclamation-triangle me-1"></i>{{ $errors->first() }}
    </div>
@endif
@if($totalPerluPilih > 0)
    <div class="alert alert-warning small py-2" id="peringatanPerluPilih">
        <i class="bi bi-exclamation-circle me-1"></i>Ada <strong>{{ $totalPerluPilih }}</strong> baris yang kodenya sama tapi namanya berbeda — wajib dipilih dulu.
    </div>
@endif

<form method="POST" action="{{ route('master.produk-jual.ambil.simpan') }}" id="formAmbilProduk">
    @csrf
    <input type="hidden" name="sumber" value="{{ $sumber->id }}">
    @foreach($produkIds as $pid)
        <input type="hidden" name="produk[]" value="{{ $pid }}">
    @endforeach

    @foreach($rencana as $r)
    @php $t = $r['tujuan']; @endphp
    <input type="hidden" name="tujuan[]" value="{{ $t->id }}">
    <div class="card mb-3">
        <div class="card-header fw-semibold"><i class="bi bi-geo-alt-fill text-primary me-1"></i>Ke cabang: {{ $t->nama_cabang }}</div>
        <div class="card-body p-0">

            {{-- Produk --}}
            <div class="px-3 pt-2 small fw-semibold text-muted">PRODUK</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-2">
                    <thead class="table-light"><tr><th style="width:40px">Salin</th><th>Produk</th><th>Keterangan</th></tr></thead>
                    <tbody>
                    @foreach($r['produk'] as $pid => $row)
                        @php $p = $row['sumber']; @endphp
                        <tr class="{{ $row['status'] === 'kode_ada' ? 'text-muted' : '' }}">
                            <td>
                                <input class="form-check-input" type="checkbox" name="salin[{{ $t->id }}][{{ $pid }}]" value="1"
                                    @checked(old("salin.{$t->id}.{$pid}", old('_token') ? null : $row['salin'])) @disabled($row['status'] === 'kode_ada')>
                            </td>
                            <td><div class="fw-semibold small">{{ $p->nama_item }}</div><div class="text-muted" style="font-size:0.75rem">{{ $p->kode_item }}</div></td>
                            <td class="small">
                                @if($row['status'] === 'kode_ada')
                                    <span class="badge bg-secondary">Dilewati</span> Kode {{ $p->kode_item }} sudah ada di cabang ini
                                    @if($row['target']) ({{ $row['target']->nama_item }}@if($row['target']->trashed()), di Data Terhapus @endif)@endif
                                @elseif($row['status'] === 'nama_ada')
                                    <span class="badge bg-warning text-dark">Nama sama</span> Sudah ada produk "{{ $row['target']->nama_item }}" (kode {{ $row['target']->kode_item }}). Centang kalau tetap mau disalin.
                                @else
                                    <span class="badge bg-success">Baru</span> {{ $p->resep ? $p->resep->items->count() . ' baris komposisi' : 'Tanpa komposisi' }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Bumbu Pusat --}}
            @if($r['bumbu'])
            <div class="px-3 pt-1 small fw-semibold text-muted">BUMBU PUSAT DI KOMPOSISI</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-2">
                    <thead class="table-light"><tr><th>Di {{ $sumber->nama_cabang }}</th><th>Dipakai di</th><th style="min-width:220px">Dicocokkan ke</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach($r['bumbu'] as $bid => $row)
                        @php $b = $row['sumber']; [$warna, $judul, $ket] = $labelStatus[$row['status']]; @endphp
                        <tr class="{{ in_array($row['status'], ['kode_sama', 'nama_sama'], true) ? 'table-warning' : '' }}">
                            <td><div class="fw-semibold small">🧂 {{ $b->nama }}</div><div class="text-muted" style="font-size:0.75rem">{{ $b->kode }}</div></td>
                            <td class="small">{{ implode(', ', $row['dipakai']) }}</td>
                            <td>
                                @if($row['status'] === 'umum')
                                    <span class="small">{{ $b->nama }}</span>
                                @else
                                    @php $pilih = old("bumbu.{$t->id}.{$bid}", $row['default']); @endphp
                                    <select name="bumbu[{{ $t->id }}][{{ $bid }}]" class="form-select form-select-sm pilihan-wajib" @required($row['default'] === null)>
                                        @if($row['default'] === null)<option value="">-- Pilih --</option>@endif
                                        <option value="baru" @selected($pilih === 'baru')>➕ Salin jadi Bumbu Pusat baru</option>
                                        @foreach($r['opsi_bumbu'] as $o)
                                            <option value="{{ $o->id }}" @selected((string) $pilih === (string) $o->id)>{{ $o->kode }} — {{ $o->nama }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </td>
                            <td><span class="badge {{ $warna }}">{{ $judul }}</span><div class="text-muted" style="font-size:0.72rem">{{ $ket }}</div></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Bahan --}}
            <div class="px-3 pt-1 small fw-semibold text-muted">BAHAN KOMPOSISI <x-tooltip key="ambil_produk.pencocokan" /></div>
            @if($r['bahan'])
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Di {{ $sumber->nama_cabang }}</th><th>Dipakai di</th><th style="min-width:240px">Dicocokkan ke {{ $t->nama_cabang }}</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach($r['bahan'] as $iid => $row)
                        @php $s = $row['sumber']; [$warna, $judul, $ket] = $labelStatus[$row['status']]; @endphp
                        <tr class="{{ in_array($row['status'], ['kode_sama', 'nama_sama'], true) ? 'table-warning' : '' }}">
                            <td><div class="fw-semibold small">{{ $s->nama_item }}</div><div class="text-muted" style="font-size:0.75rem">{{ $s->kode_item }} · {{ $s->satuan }}</div></td>
                            <td class="small">{{ implode(', ', $row['dipakai']) }}</td>
                            <td>
                                @if($row['status'] === 'umum')
                                    <span class="small">{{ $s->kode_item }} — {{ $s->nama_item }}</span>
                                @else
                                    @php $pilih = old("bahan.{$t->id}.{$iid}", $row['default']); @endphp
                                    <select name="bahan[{{ $t->id }}][{{ $iid }}]" class="form-select form-select-sm pilihan-wajib" @required($row['default'] === null)>
                                        @if($row['default'] === null)<option value="">-- Pilih --</option>@endif
                                        <option value="baru" @selected($pilih === 'baru')>➕ Buat bahan baru (stok 0)</option>
                                        @foreach($r['opsi_bahan'] as $o)
                                            <option value="{{ $o->id }}" @selected((string) $pilih === (string) $o->id)>{{ $o->kode_item }} — {{ $o->nama_item }} ({{ $o->satuan }})</option>
                                        @endforeach
                                    </select>
                                @endif
                            </td>
                            <td><span class="badge {{ $warna }}">{{ $judul }}</span><div class="text-muted" style="font-size:0.72rem">{{ $ket }}</div></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @else
                <div class="px-3 pb-3 small text-muted">Produk yang dipilih tidak punya komposisi bahan.</div>
            @endif
        </div>
    </div>
    @endforeach

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="{{ route('master.produk-jual.ambil.index', ['sumber' => $sumber->id]) }}" class="btn btn-outline-secondary btn-sm">Batal</a>
        <button type="submit" class="btn btn-primary btn-sm" id="btnSalinProduk"><i class="bi bi-check2-circle me-1"></i>Salin Produk</button>
    </div>
</form>

@endsection

@push('scripts')
<script>
document.getElementById('formAmbilProduk')?.addEventListener('submit', function (e) {
    const kosong = Array.from(this.querySelectorAll('select.pilihan-wajib')).find(s => s.required && !s.value);
    if (kosong) {
        e.preventDefault();
        kosong.focus();
        Swal.fire({ icon: 'warning', title: 'Belum dipilih', text: 'Ada bahan yang kodenya sama tapi namanya berbeda. Pilih dulu bahan yang dipakai.' });
        return;
    }
    document.getElementById('btnSalinProduk').disabled = true;
});
</script>
@endpush
