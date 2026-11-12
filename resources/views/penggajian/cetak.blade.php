<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slip Gaji - {{ $penggajian->karyawan?->nama_lengkap }} - {{ $penggajian->periode }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        .slip { max-width: 720px; margin: 20px auto; border: 1px solid #333; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 12px; margin-bottom: 15px; }
        .header h2 { margin: 0; font-size: 18px; }
        .header p { margin: 2px 0; font-size: 11px; color: #666; }
        .slip-title { text-align: center; font-size: 14px; font-weight: bold; margin-bottom: 15px; letter-spacing: 1px; }
        .info-table { width: 100%; margin-bottom: 15px; }
        .info-table td { padding: 3px 5px; vertical-align: top; }
        .info-table td:first-child { width: 35%; color: #666; }
        .columns { display: flex; gap: 20px; margin-bottom: 15px; }
        .col-half { flex: 1; }
        .section-title { font-weight: bold; padding: 5px 8px; background: #f0f0f0; color: #555;
                         font-size: 11px; text-transform: uppercase; margin-bottom: 4px; border-left: 3px solid #333; }
        .item-row { display: flex; justify-content: space-between; padding: 3px 8px; border-bottom: 1px solid #eee; }
        .item-row .label { color: #555; }
        .item-row .amount { font-weight: 500; }
        .total-row { display: flex; justify-content: space-between; padding: 5px 8px;
                     font-weight: bold; background: #f8f9fa; margin-top: 4px; }
        .total-row.green { background: #d4edda; }
        .total-row.red { background: #f8d7da; }
        .grand-total { border: 2px solid #333; margin-top: 15px; padding: 10px; text-align: center; }
        .grand-total .label { font-size: 12px; color: #666; }
        .grand-total .amount { font-size: 20px; font-weight: bold; color: #1e40af; }
        .footer { margin-top: 30px; display: flex; justify-content: space-between; }
        .ttd { text-align: center; width: 45%; }
        .ttd .garis { border-top: 1px solid #333; margin-top: 60px; padding-top: 5px; font-size: 11px; }
        .absensi-box { display: flex; gap: 10px; margin-bottom: 15px; }
        .absensi-item { flex: 1; text-align: center; border: 1px solid #ddd; padding: 8px 4px; border-radius: 4px; }
        .absensi-item .num { font-size: 18px; font-weight: bold; }
        .absensi-item .lbl { font-size: 10px; color: #666; }
        .no-print { text-align: center; padding: 15px; background: #f8f9fa; border-radius: 5px; margin-bottom: 15px; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
            .slip { border: none; max-width: 100%; margin: 0; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" style="padding:8px 20px;background:#3b82f6;color:white;border:none;border-radius:5px;cursor:pointer;margin-right:10px">
        🖨️ Cetak Slip Gaji
    </button>
    <a href="{{ route('penggajian.show', $penggajian) }}" style="color:#666;text-decoration:none">← Kembali</a>
</div>

<div class="slip">
    {{-- Header --}}
    <div class="header">
        <h2>{{ mb_strtoupper($brand->namaSingkat()) }}</h2>
        @if($brand->tagline())<p>{{ $brand->tagline() }}</p>@endif
        <p>{{ $penggajian->cabang?->alamat ?? '' }}</p>
    </div>

    <div class="slip-title">SLIP GAJI KARYAWAN</div>
    <div style="text-align:center;font-size:11px;margin-bottom:15px;color:#666">
        Periode: {{ \Carbon\Carbon::createFromFormat('Y-m', $penggajian->periode)->translatedFormat('F Y') }}
        &nbsp;|&nbsp;
        Status: <strong>{{ ucfirst($penggajian->status) }}</strong>
        @if($penggajian->tanggal_bayar)
        &nbsp;|&nbsp; Dibayar: {{ $penggajian->tanggal_bayar->format('d/m/Y') }}
        @endif
    </div>

    {{-- Data Karyawan --}}
    <table class="info-table">
        <tr>
            <td>Nama Karyawan</td>
            <td>: <strong>{{ $penggajian->karyawan?->nama_lengkap }}</strong></td>
            <td>Cabang</td>
            <td>: {{ $penggajian->cabang?->nama_cabang }}</td>
        </tr>
        <tr>
            <td>NIK</td>
            <td>: {{ $penggajian->karyawan?->nik ?? '-' }}</td>
            <td>No. Rekening</td>
            <td>: {{ $penggajian->karyawan?->no_rekening ? ($penggajian->karyawan->nama_bank . ' - ' . $penggajian->karyawan->no_rekening) : '-' }}</td>
        </tr>
        <tr>
            <td>Jabatan</td>
            <td>: {{ $penggajian->karyawan?->jabatan }}</td>
            <td>Tipe</td>
            <td>: {{ ucfirst($penggajian->karyawan?->tipe_karyawan ?? '-') }}</td>
        </tr>
    </table>

    {{-- Rekap Absensi --}}
    <div class="section-title">Rekap Absensi</div>
    <div class="absensi-box">
        <div class="absensi-item">
            <div class="num">{{ $penggajian->jumlah_hari_kerja }}</div>
            <div class="lbl">Hari Kerja</div>
        </div>
        <div class="absensi-item">
            <div class="num" style="color:#166534">{{ $penggajian->jumlah_hadir }}</div>
            <div class="lbl">Hadir</div>
        </div>
        <div class="absensi-item">
            <div class="num" style="color:#991b1b">{{ $penggajian->jumlah_alpha }}</div>
            <div class="lbl">Alpha</div>
        </div>
        <div class="absensi-item">
            <div class="num" style="color:#1e40af">{{ number_format($penggajian->jam_lembur_total, 1) }}</div>
            <div class="lbl">Jam Lembur</div>
        </div>
        @php $pctHadir = $penggajian->jumlah_hari_kerja > 0 ? round($penggajian->jumlah_hadir / $penggajian->jumlah_hari_kerja * 100) : 0; @endphp
        <div class="absensi-item">
            <div class="num" style="color:{{ $pctHadir >= 80 ? '#166534' : '#991b1b' }}">{{ $pctHadir }}%</div>
            <div class="lbl">Kehadiran</div>
        </div>
    </div>

    {{-- Komponen Gaji (2 kolom) --}}
    <div class="columns">
        {{-- PENAMBAHAN --}}
        <div class="col-half">
            <div class="section-title" style="border-left-color:#166534">Penambahan</div>

            @php
                $penambahanRows = [
                    ['Gaji Pokok', $penggajian->gaji_pokok, true],
                    ['Tunjangan Jabatan', $penggajian->tunjangan_jabatan, false],
                    ['Tunjangan Makan', $penggajian->tunjangan_makan, false],
                    ['Tunjangan Transport', $penggajian->tunjangan_transport, false],
                    ['Premi Kehadiran', $penggajian->tunjangan_kehadiran, false],
                    ['Uang Lembur (' . number_format($penggajian->jam_lembur_total, 1) . ' jam)', $penggajian->uang_lembur, false],
                    ['Bonus', $penggajian->bonus, false],
                    ['Insentif', $penggajian->insentif, false],
                    ['THR', $penggajian->thr, false],
                    ['Komisi', $penggajian->komisi, false],
                    ['Tunjangan Lain', $penggajian->tunjangan, false],
                ];
                $totalPenambahan = array_sum(array_column($penambahanRows, 1));
            @endphp

            @foreach($penambahanRows as [$label, $nilai, $alwaysShow])
            @if($alwaysShow || (float)$nilai > 0)
            <div class="item-row">
                <span class="label">{{ $label }}</span>
                <span class="amount">{{ number_format($nilai, 0, ',', '.') }}</span>
            </div>
            @endif
            @endforeach

            <div class="total-row green">
                <span>Total Pendapatan</span>
                <span>{{ number_format($totalPenambahan, 0, ',', '.') }}</span>
            </div>
        </div>

        {{-- PENGURANGAN --}}
        <div class="col-half">
            <div class="section-title" style="border-left-color:#991b1b">Potongan</div>

            @php
                $potonganRows = [
                    ['Potongan Alpha (' . $penggajian->jumlah_alpha . ' hari)', $penggajian->potongan_absensi],
                    ['BPJS Kesehatan', $penggajian->bpjs_kesehatan],
                    ['BPJS TK (JHT)', $penggajian->bpjs_ketenagakerjaan],
                    ['PPh21', $penggajian->pph21],
                    ['Kasbon', $penggajian->kasbon],
                    ['Potongan Lain', $penggajian->potongan_lain],
                    ['Ganti rugi barang hilang', $penggajian->potongan_ganti_rugi],
                ];
                $totalPotongan = array_sum(array_column($potonganRows, 1));
            @endphp

            @foreach($potonganRows as [$label, $nilai])
            @if((float)$nilai > 0)
            <div class="item-row">
                <span class="label">{{ $label }}</span>
                <span class="amount">{{ number_format($nilai, 0, ',', '.') }}</span>
            </div>
            @endif
            @endforeach

            @if($totalPotongan == 0)
            <div class="item-row"><span class="label" style="color:#aaa">Tidak ada potongan</span><span></span></div>
            @endif

            <div class="total-row red">
                <span>Total Potongan</span>
                <span>{{ number_format($totalPotongan, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    {{-- TOTAL GAJI BERSIH --}}
    <div class="grand-total">
        <div class="label">TAKE HOME PAY / GAJI BERSIH</div>
        <div class="amount">Rp {{ number_format($penggajian->total_gaji, 0, ',', '.') }}</div>
        @if($penggajian->catatan)
        <div style="margin-top:5px;font-size:11px;color:#666">Catatan: {{ $penggajian->catatan }}</div>
        @endif
    </div>

    {{-- TTD --}}
    <div class="footer">
        <div class="ttd">
            <div>Karyawan</div>
            <div class="garis">{{ $penggajian->karyawan?->nama_lengkap }}</div>
        </div>
        <div class="ttd">
            <div>Disetujui oleh</div>
            <div class="garis">{{ $penggajian->approvedBy?->name ?? 'Manager / Owner' }}</div>
        </div>
    </div>

    <div style="margin-top:20px;font-size:10px;color:#aaa;text-align:center">
        Dicetak: {{ now()->format('d/m/Y H:i') }} &mdash; Dokumen resmi {{ $brand->namaSingkat() }}. Simpan dengan baik.
    </div>
</div>

</body>
</html>
