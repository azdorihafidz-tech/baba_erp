@extends('layouts.app')

@section('title', 'Pesanan ' . $p->nomor)

@section('content')
@php
    $rp = fn ($n) => $n === null ? '—' : 'Rp ' . number_format((float) $n, 0, ',', '.');
    $bukti = $p->bayarMenunggu();
    $bolehBatal = ! $p->stok_keluar && in_array($p->status, ['menunggu_ongkir', 'menunggu_bayar', 'menunggu_cek', 'disiapkan'], true);
    $kurang = $p->item->filter(fn ($i) => ($stok[$i->id] ?? 0) < $i->qty);
@endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-bag-check me-2 text-success"></i>Pesanan {{ $p->nomor }}
        <span class="badge bg-{{ $p->status_warna }} align-middle ms-1">{{ $p->status_label }}</span></h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="pesanan-online" />
        <a href="{{ route('toko-online.pesanan.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="row g-3">
<div class="col-12 col-lg-7">
    <div class="card mb-3"><div class="card-body">
        <div class="row g-2 small">
            <div class="col-6 col-md-4"><div class="text-muted">Pembeli</div><div class="fw-semibold">{{ $p->member?->nama ?? '#' . $p->member_id }}</div><div>{{ $p->member?->no_id }} · {{ $p->member?->no_member }}</div></div>
            <div class="col-6 col-md-4"><div class="text-muted">Jabatan · Cabang</div><div>{{ $p->member?->jabatan ?? '—' }} · {{ $p->member?->cabang ?? '—' }}</div></div>
            <div class="col-6 col-md-4"><div class="text-muted">Tanggal</div><div>{{ $p->created_at->format('d/m/Y H:i') }}</div></div>
            <div class="col-6 col-md-4"><div class="text-muted">Gudang</div><div>{{ $p->cabang?->nama_cabang }}</div></div>
            <div class="col-6 col-md-4"><div class="text-muted">Cara bayar</div><div>{{ $p->cara_bayar === 'tagihan' ? 'Tagihan' : 'Transfer' }} · <span class="{{ $p->status_bayar === 'lunas' ? 'text-success fw-semibold' : '' }}">{{ $p->status_bayar_label }}</span></div>
                @if($p->jatuh_tempo)<div class="{{ $p->status_bayar !== 'lunas' && $p->jatuh_tempo->isPast() ? 'text-danger fw-semibold' : 'text-muted' }}">Jatuh tempo {{ $p->jatuh_tempo->format('d/m/Y') }}</div>@endif
                @if($p->status === 'menunggu_bayar' && $p->batas_bayar_at)<div class="text-muted">Batas bayar {{ $p->batas_bayar_at->format('d/m/Y H:i') }}</div>@endif
            </div>
            <div class="col-6 col-md-4"><div class="text-muted">Cara terima</div><div>{{ $p->cara_ambil === 'kirim' ? 'Dikirim' : 'Ambil sendiri di gudang' }}</div>
                @if($p->kurir)<div>{{ $p->kurir }}{{ $p->nomor_resi ? ' · ' . $p->nomor_resi : '' }}</div>@endif</div>
            @if($p->cara_ambil === 'kirim')
            <div class="col-12"><div class="text-muted">Kirim ke</div><div><strong>{{ $p->kirim_nama }}</strong> · {{ $p->kirim_hp }}</div><div style="white-space:pre-line">{{ $p->kirim_alamat }}</div></div>
            @endif
            @if($p->catatan)<div class="col-12"><div class="text-muted">Catatan pembeli</div><div style="white-space:pre-line">{{ $p->catatan }}</div></div>@endif
            @if($p->status === 'batal')<div class="col-12 text-danger"><strong>Dibatalkan:</strong> {{ $p->batal_alasan }}</div>@endif
        </div>
    </div></div>

    <div class="card mb-3"><div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Barang</th><th class="text-end">Jumlah</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th>@if(! $p->stok_keluar && $p->status !== 'batal')<th class="text-end">Stok gudang</th>@endif</tr></thead>
        <tbody>
        @foreach($p->item as $i)
        <tr>
            <td>{{ $i->nama }}<div class="small text-muted">{{ \App\Services\HargaOnlineService::LABEL[$i->dasar_harga] ?? $i->dasar_harga }}</div></td>
            <td class="text-end">{{ $i->qty }} {{ $i->satuan }}</td>
            <td class="text-end">{{ $rp($i->harga) }}</td>
            <td class="text-end">{{ $rp($i->subtotal) }}</td>
            @if(! $p->stok_keluar && $p->status !== 'batal')<td class="text-end {{ ($stok[$i->id] ?? 0) < $i->qty ? 'text-danger fw-semibold' : '' }}">{{ (float) ($stok[$i->id] ?? 0) }}</td>@endif
        </tr>
        @endforeach
        </tbody>
        <tfoot class="small">
            <tr><td colspan="3" class="text-end">Subtotal</td><td class="text-end">{{ $rp($p->subtotal) }}</td>@if(! $p->stok_keluar && $p->status !== 'batal')<td></td>@endif</tr>
            <tr><td colspan="3" class="text-end">Ongkir</td><td class="text-end">{{ $p->ongkir === null ? 'belum diisi' : $rp($p->ongkir) }}</td>@if(! $p->stok_keluar && $p->status !== 'batal')<td></td>@endif</tr>
            <tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end">{{ $rp($p->total) }}</td>@if(! $p->stok_keluar && $p->status !== 'batal')<td></td>@endif</tr>
        </tfoot>
    </table>
    </div></div>

    <div class="card mb-3"><div class="card-header small fw-semibold">Riwayat</div><ul class="list-group list-group-flush small">
        @foreach($p->log as $l)
        <li class="list-group-item"><span class="text-muted">{{ $l->created_at?->format('d/m/Y H:i') }}</span> · <strong>{{ \App\Models\PesananOnline::STATUS[$l->status] ?? $l->status }}</strong>
            — {{ $l->catatan }} <span class="text-muted">({{ $l->oleh_nama ?: $l->oleh_jenis }})</span></li>
        @endforeach
    </ul></div>
</div>

<div class="col-12 col-lg-5">
    {{-- Isi ongkir --}}
    @if($p->status === 'menunggu_ongkir')
    <div class="card mb-3 border-warning"><div class="card-body">
        <h6 class="fw-bold">Isi ongkir</h6>
        <p class="small text-muted mb-2">Member memilih <strong>dikirim</strong>. Isi ongkir (0 bila gratis). {{ $p->cara_bayar === 'transfer' ? 'Setelah itu member diminta transfer.' : 'Pesanan tagihan langsung masuk Disiapkan.' }}</p>
        <form method="POST" action="{{ route('toko-online.pesanan.ongkir', $p) }}" class="d-flex gap-2">
            @csrf
            <input type="text" inputmode="numeric" name="ongkir" class="form-control form-control-sm" placeholder="mis. 25000" required>
            <button class="btn btn-primary btn-sm text-nowrap">Simpan ongkir</button>
        </form>
    </div></div>
    @endif

    {{-- Cek bukti --}}
    @if($bukti)
    <div class="card mb-3 border-info"><div class="card-body">
        <h6 class="fw-bold">Cek bukti transfer</h6>
        <p class="small mb-2">Nominal yang harus masuk: <strong>{{ $rp($bukti->nominal) }}</strong>. Diunggah {{ $bukti->created_at->format('d/m/Y H:i') }}.</p>
        @php $urlBukti = route('toko-online.pesanan.bukti.file', [$p, $bukti]); @endphp
        @if(str_ends_with($bukti->file, '.pdf'))
            <a href="{{ $urlBukti }}" target="_blank" class="btn btn-outline-secondary btn-sm mb-2"><i class="bi bi-file-earmark-pdf me-1"></i>Buka bukti (PDF)</a>
        @else
            <a href="{{ $urlBukti }}" target="_blank"><img src="{{ $urlBukti }}" alt="Bukti transfer" class="img-fluid rounded border mb-2" style="max-height:320px"></a>
        @endif
        @if($rekening)<div class="small text-muted mb-2">Rekening gudang: {{ collect($rekening)->map(fn ($r) => $r['bank'] . ' ' . $r['nomor'])->implode(' / ') }}</div>@endif
        <div class="d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('toko-online.pesanan.bukti.terima', [$p, $bukti]) }}" onsubmit="return confirm('Uang sudah masuk ke rekening? Pembayaran akan dicatat lunas.')">
                @csrf<button class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Terima pembayaran</button>
            </form>
            <button class="btn btn-outline-danger btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#tolakBukti">Tolak…</button>
        </div>
        <form method="POST" action="{{ route('toko-online.pesanan.bukti.tolak', [$p, $bukti]) }}" class="collapse mt-2" id="tolakBukti">
            @csrf
            <input type="text" name="alasan" maxlength="255" class="form-control form-control-sm mb-2" placeholder="Alasan (mis. nominal kurang, bukti tidak terbaca)" required minlength="3">
            <button class="btn btn-danger btn-sm">Tolak bukti</button>
        </form>
    </div></div>
    @endif

    {{-- Kirim / siap diambil --}}
    @if($p->status === 'disiapkan')
    <div class="card mb-3 border-primary"><div class="card-body">
        <h6 class="fw-bold">{{ $p->cara_ambil === 'kirim' ? 'Kirim pesanan' : 'Siapkan untuk diambil' }}</h6>
        @if($kurang->isNotEmpty())
        <div class="alert alert-danger small py-2 mb-2">Stok gudang kurang untuk: {{ $kurang->pluck('nama')->implode(', ') }}. Tambah stok (Produksi / penyesuaian) dulu.</div>
        @endif
        <p class="small text-muted mb-2">Stok gudang dikurangi saat tombol ini ditekan.</p>
        <form method="POST" action="{{ route('toko-online.pesanan.keluarkan', $p) }}" onsubmit="return confirm('Keluarkan barang dari stok gudang?')">
            @csrf
            @if($p->cara_ambil === 'kirim')
            <div class="row g-2 mb-2">
                <div class="col-6"><input type="text" name="kurir" maxlength="50" class="form-control form-control-sm" placeholder="Kurir / ekspedisi *" required></div>
                <div class="col-6"><input type="text" name="nomor_resi" maxlength="60" class="form-control form-control-sm" placeholder="Nomor resi"></div>
            </div>
            <button class="btn btn-primary btn-sm" @disabled($kurang->isNotEmpty())><i class="bi bi-truck me-1"></i>Kirim</button>
            @else
            <button class="btn btn-primary btn-sm" @disabled($kurang->isNotEmpty())><i class="bi bi-box-seam me-1"></i>Siap diambil</button>
            @endif
        </form>
    </div></div>
    @endif

    @if($p->status === 'siap_diambil')
    <div class="card mb-3"><div class="card-body">
        <h6 class="fw-bold">Menunggu diambil</h6>
        <p class="small text-muted mb-2">Tekan bila barang sudah diserahkan ke pembeli (member juga bisa menekan Diterima di aplikasinya).</p>
        <form method="POST" action="{{ route('toko-online.pesanan.diambil', $p) }}">@csrf<button class="btn btn-success btn-sm">Sudah diambil</button></form>
    </div></div>
    @endif

    @if($p->status === 'menunggu_bayar')
    <div class="alert alert-warning small">Menunggu member transfer {{ $rp($p->total) }} dan mengunggah bukti{{ $p->batas_bayar_at ? ' (batas ' . $p->batas_bayar_at->format('d/m/Y H:i') . ', lewat itu batal otomatis)' : '' }}.</div>
    @endif

    {{-- Bukti sebelumnya --}}
    @if($p->bayar->where('status', '!=', 'menunggu')->isNotEmpty())
    <div class="card mb-3"><div class="card-header small fw-semibold">Bukti transfer sebelumnya</div><ul class="list-group list-group-flush small">
        @foreach($p->bayar->where('status', '!=', 'menunggu') as $b)
        <li class="list-group-item d-flex justify-content-between gap-2">
            <span>{{ $b->created_at->format('d/m/Y H:i') }} · {{ $rp($b->nominal) }} · <span class="{{ $b->status === 'diterima' ? 'text-success' : 'text-danger' }}">{{ \App\Models\PesananOnlineBayar::STATUS[$b->status] }}</span>{{ $b->alasan ? ' — ' . $b->alasan : '' }}</span>
            <a href="{{ route('toko-online.pesanan.bukti.file', [$p, $b]) }}" target="_blank">Lihat</a>
        </li>
        @endforeach
    </ul></div>
    @endif

    @if($bolehBatal)
    <div class="card mb-3"><div class="card-body">
        <button class="btn btn-outline-danger btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#batalPesanan">Batalkan pesanan…</button>
        <form method="POST" action="{{ route('toko-online.pesanan.batal', $p) }}" class="collapse mt-2" id="batalPesanan" onsubmit="return confirm('Batalkan pesanan ini?')">
            @csrf
            @if($p->status_bayar === 'lunas')<div class="alert alert-warning small py-2 mb-2">Pesanan ini sudah dibayar — kembalikan dana ke pembeli setelah dibatalkan.</div>@endif
            <input type="text" name="alasan" maxlength="255" class="form-control form-control-sm mb-2" placeholder="Alasan pembatalan (dikirim ke member)" required minlength="3">
            <button class="btn btn-danger btn-sm">Batalkan</button>
        </form>
    </div></div>
    @endif
</div>
</div>
@endsection
