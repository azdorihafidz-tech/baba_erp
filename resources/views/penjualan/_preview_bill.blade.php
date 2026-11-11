<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Preview Bill</title>
    <style>
        body { font-family: 'Courier New', monospace; font-size: 12px; width: 72mm; margin: 0 auto; padding: 4mm; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .line { border-top: 1px dashed #000; margin: 4px 0; }
        .row { display: flex; justify-content: space-between; }
        .watermark { text-align: center; color: #dc2626; font-weight: bold; border: 2px solid #dc2626; padding: 4px; margin: 6px 0; }
        .no-print { text-align: center; margin-top: 10mm; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="center bold" style="font-size:14px">{{ mb_strtoupper($brand->namaSingkat()) }}</div>
    <div class="center">{{ $cabangAktif?->nama_cabang }}</div>
    <div class="watermark">PREVIEW — BELUM DIBAYAR</div>
    <div class="line"></div>
    @if(!empty($data['tipe_transaksi']))
        <div class="row"><span>Tipe</span><span>{{ ucfirst(str_replace('_',' ',$data['tipe_transaksi'])) }}</span></div>
    @endif
    @if(!empty($data['nomor_meja']))
        <div class="row"><span>Meja</span><span>{{ $data['nomor_meja'] }}</span></div>
    @endif
    @if(!empty($data['nama_pelanggan']))
        <div class="row"><span>Pelanggan</span><span>{{ $data['nama_pelanggan'] }}</span></div>
    @endif
    <div class="line"></div>
    @php $total = 0; @endphp
    @foreach($data['items'] as $item)
        @php $subtotal = $item['qty'] * $item['harga_satuan']; $total += $subtotal; @endphp
        <div class="bold">{{ $item['nama_item'] }}</div>
        <div class="row"><span>{{ rtrim(rtrim(number_format($item['qty'],3,'.',''),'0'),'.') }} {{ $item['satuan'] ?? '' }} x {{ number_format($item['harga_satuan'],0,',','.') }}</span><span>{{ number_format($subtotal,0,',','.') }}</span></div>
    @endforeach
    <div class="line"></div>
    @php $diskon = (float) ($data['diskon'] ?? 0); @endphp
    @if($diskon > 0)
        <div class="row"><span>Diskon</span><span>-{{ number_format($diskon,0,',','.') }}</span></div>
    @endif
    <div class="row bold" style="font-size:14px"><span>TOTAL (sementara)</span><span>{{ number_format($total - $diskon,0,',','.') }}</span></div>
    <div class="line"></div>
    <div class="center" style="font-size:10px">*Belum termasuk service charge/fee — dihitung final saat Bayar*</div>

    <div class="no-print">
        <button onclick="window.print()">🖨️ Cetak</button>
    </div>
</body>
</html>
