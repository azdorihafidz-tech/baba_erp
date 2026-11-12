@extends('layouts.app')

@section('title', 'Produk Parfum Toko')

@section('content')
@php $nilai = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); @endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-shop me-2 text-success"></i>Produk Parfum Toko</h5>
    <x-panduan-button slug="produk-parfum-toko" />
</div>

@if($daftarToko->isEmpty())
<div class="alert alert-warning">Belum ada toko. Buat cabang bertipe <strong>Cabang</strong> (isi Jenis toko) di menu Cabang dulu.</div>
@else
<form method="GET" class="mb-3" style="max-width:420px">
    <label class="form-label small fw-semibold">Toko</label>
    <select name="toko" class="form-select form-select-sm" onchange="this.form.submit()" data-uji="pilih-toko">
        @foreach($daftarToko as $t)<option value="{{ $t->id }}" @selected($toko?->id === $t->id)>{{ $t->nama_cabang }}{{ $t->jenis_toko ? ' · ' . (\App\Models\Cabang::JENIS_TOKO[$t->jenis_toko] ?? '') : '' }}</option>@endforeach
    </select>
</form>

<div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>
    Centang parfum yang dijual di kasir toko ini lalu isi harganya. <strong>Botol</strong> memotong 1 botol dari stok toko; <strong>isi ulang</strong> dijual per ml dan memotong cairan dari stok toko.
    Stok toko diisi lewat <strong>Permintaan Stok / Transfer Stok</strong> dari gudang.</div>

@if($errors->any())<div class="alert alert-danger small">{{ $errors->first() }}</div>@endif

<form method="POST" action="{{ route('produk-toko.simpan') }}">
    @csrf
    <input type="hidden" name="toko" value="{{ $toko->id }}">
    @foreach(['botol' => ['Botol', 'Harga jual per botol'], 'cairan' => ['Isi ulang per ml', 'Harga per ml']] as $jenis => [$judul, $labelHarga])
    <div class="card mb-3"><div class="card-header fw-semibold small">{{ $judul }} <x-tooltip key="produk_toko.{{ $jenis }}" /></div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th style="width:40px">Jual</th><th>Produk di kasir</th><th class="text-end">Stok toko</th>@if($jenis === 'botol')<th style="width:190px">Barcode <x-tooltip key="produk_toko.barcode" /></th>@endif<th style="width:170px">{{ $labelHarga }}</th></tr></thead>
            <tbody>
            @forelse($rencana[$jenis] as $r)
            @php $id = $r['sumber']->id; @endphp
            <tr>
                <td><input type="checkbox" class="form-check-input" name="produk[{{ $id }}][aktif]" value="1" data-uji="jual-{{ $r['kode'] }}" @checked(old("produk.$id.aktif", $r['aktif']))></td>
                <td>{{ $r['nama'] }}<div class="small text-muted">{{ $r['kode'] }}</div></td>
                <td class="text-end small">{{ fmt_qty($r['stok_toko']) }} {{ $jenis === 'botol' ? 'pcs' : 'ml' }}</td>
                @if($jenis === 'botol')
                <td><input type="text" class="form-control form-control-sm" name="produk[{{ $id }}][barcode]" data-uji="barcode-{{ $r['kode'] }}" maxlength="64"
                    value="{{ old("produk.$id.barcode", $r['barcode']) }}" placeholder="Scan / ketik" autocomplete="off" onkeydown="if(event.key==='Enter'){event.preventDefault();}"></td>
                @endif
                <td><input type="number" min="0" step="any" class="form-control form-control-sm text-end" name="produk[{{ $id }}][harga]" data-uji="harga-{{ $r['kode'] }}"
                    value="{{ old("produk.$id.harga", $nilai($r['harga'])) }}" placeholder="Rp"></td>
            </tr>
            @empty
            <tr><td colspan="{{ $jenis === 'botol' ? 5 : 4 }}" class="text-center text-muted py-3">Belum ada {{ strtolower($judul) }} di gudang. Buat lewat Produksi › Buat Parfum.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    @endforeach
    <button class="btn btn-primary" data-uji="simpan-produk-toko">Simpan</button>
</form>
@endif
@endsection
