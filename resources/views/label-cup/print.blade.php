{{-- 4.44 Poin 2 — halaman stiker Label Produk siap cetak browser (porting erp-coffeeshop label-cup/print). --}}
@php
    $w = $hasil['ukuran']['lebar'];
    $h = $hasil['ukuran']['tinggi'];
    // Skala font mengikuti tinggi stiker (acuan 30 mm)
    $s = max(0.7, min(1.6, $h / 30));
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Label Produk</title>
    <style>
        @page { size: {{ $w }}mm {{ $h }}mm; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; color: #000; background: #fff; }
        .label {
            width: {{ $w }}mm; height: {{ $h }}mm; padding: 1.5mm 2mm;
            overflow: hidden; page-break-after: always; break-after: page;
            display: flex; flex-direction: column;
        }
        .label:last-child { page-break-after: auto; break-after: auto; }
        .head { display: flex; justify-content: space-between; gap: 1mm; font-size: {{ round(7 * $s, 1) }}pt; font-weight: bold; border-bottom: 0.3mm solid #000; padding-bottom: 0.5mm; }
        .head span:first-child { overflow: hidden; white-space: nowrap; }
        .head span:last-child { white-space: nowrap; }
        .nama { font-size: {{ round(9.5 * $s, 1) }}pt; font-weight: bold; line-height: 1.1; margin-top: 0.8mm; }
        .opsi, .catatan, .pelanggan, .exp { font-size: {{ round(7 * $s, 1) }}pt; line-height: 1.2; }
        .catatan { font-style: italic; }
        .exp { font-weight: bold; }
        .foot { margin-top: auto; font-size: {{ round(6.5 * $s, 1) }}pt; display: flex; justify-content: space-between; }
        .no-print { font-family: system-ui, sans-serif; text-align: center; padding: 12px; }
        .no-print button { font-size: 15px; padding: 8px 18px; margin: 4px; }
        @media print { .no-print { display: none; } }
        @media screen { body { background: #e5e7eb; } .label { background: #fff; margin: 8px auto; box-shadow: 0 1px 3px rgba(0,0,0,.25); } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">🖨️ Cetak {{ count($hasil['labels']) }} Label</button>
        <button onclick="window.close()">Tutup</button>
        @if(!$hasil['aktif'])
        <p>Label produk dinonaktifkan untuk cabang ini (Cabang → Edit → Label Produk).</p>
        @elseif(empty($hasil['labels']))
        <p>Tidak ada item yang perlu label (cek kategori di Cabang → Edit → Label Produk).</p>
        @endif
    </div>

    @foreach($hasil['labels'] as $l)
    <div class="label">
        <div class="head"><span>{{ mb_strtoupper($l['toko']) }}</span><span>#{{ $l['nomor'] }} · {{ $l['ke'] }}/{{ $l['dari'] }}</span></div>
        <div class="nama">{{ $l['nama'] }}</div>
        @if(!empty($l['opsi']))
        <div class="opsi">{{ implode(' · ', $l['opsi']) }}</div>
        @endif
        @if($l['catatan'])
        <div class="catatan">{{ $l['catatan'] }}</div>
        @endif
        @if(!empty($l['catatan_order']))
        <div class="catatan">{{ $l['catatan_order'] }}</div>
        @endif
        @if($l['pelanggan'])
        <div class="pelanggan">a.n. {{ $l['pelanggan'] }}</div>
        @endif
        @if(!empty($l['exp']))
        <div class="exp">Exp: {{ $l['exp'] }}</div>
        @endif
        <div class="foot"><span>{{ $l['info'] }}</span></div>
    </div>
    @endforeach

    <script>
        // Cetak otomatis begitu halaman dibuka dari tombol "Cetak Browser",
        // lalu tutup tab setelah dialog print selesai.
        if (new URLSearchParams(location.search).get('autoprint') === '1' && {{ count($hasil['labels']) }} > 0) {
            window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 250); });
            window.addEventListener('afterprint', function () { setTimeout(function () { window.close(); }, 300); });
        }
    </script>
</body>
</html>
