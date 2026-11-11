{{--
    Isi laporan closing — dipakai halaman preview (closing.index) dan arsip
    (closing.show). Variabel: $data (hasil ClosingService::hitung() atau
    snapshot JSON), $cabangId (null = keseluruhan), $kasFisik (opsional,
    koleksi ClosingKas dgn saldo_fisik utk closing harian).
--}}
@php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $pj = $data['penjualan'];
    $lr = $data['laba_rugi'];
    $ak = $data['arus_kas'];
    $sk = $data['saldo_kas'];
    $mt = $data['mutasi'];
    $st = $data['stok'];
    $se = $data['setoran'];
    $kasFisik = $kasFisik ?? collect();
@endphp

{{-- Ringkasan --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="text-muted small">Omzet Bersih</div>
            <div class="fw-bold fs-5">{{ $rp($pj['omzet_bersih']) }}</div>
            <div class="text-muted small">{{ number_format($pj['jumlah_order']) }} transaksi</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="text-muted small">HPP</div>
            <div class="fw-bold fs-5">{{ $rp($lr['hpp']) }}</div>
            <div class="text-muted small">Laba kotor {{ $rp($lr['laba_kotor']) }}</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="text-muted small">Laba Bersih</div>
            <div class="fw-bold fs-5 {{ $lr['laba_bersih'] < 0 ? 'text-danger' : 'text-success' }}">{{ $rp($lr['laba_bersih']) }}</div>
            <div class="text-muted small">Beban {{ $rp($lr['total_beban']) }}</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="text-muted small">Saldo Kas Akhir</div>
            <div class="fw-bold fs-5">{{ $rp($sk['total']['saldo_akhir']) }}</div>
            <div class="text-muted small">Tunai {{ $rp($sk['total_tunai']['saldo_akhir']) }} · Bank {{ $rp($sk['total_bank']['saldo_akhir']) }}</div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- 1. Penjualan --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-cart-check me-1"></i> 1. Penjualan</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr><td>Jumlah transaksi</td><td class="text-end">{{ number_format($pj['jumlah_order']) }}</td></tr>
                    <tr><td>Omzet kotor (sebelum diskon)</td><td class="text-end">{{ $rp($pj['omzet_kotor']) }}</td></tr>
                    <tr><td>Diskon</td><td class="text-end text-danger">− {{ $rp($pj['diskon']) }}</td></tr>
                    @if($pj['service_charge'] > 0)
                    <tr><td>Service charge</td><td class="text-end">+ {{ $rp($pj['service_charge']) }}</td></tr>
                    @endif
                    @if($pj['take_away_fee'] > 0)
                    <tr><td>Take away fee</td><td class="text-end">+ {{ $rp($pj['take_away_fee']) }}</td></tr>
                    @endif
                    <tr class="fw-bold table-light"><td>Omzet bersih</td><td class="text-end">{{ $rp($pj['omzet_bersih']) }}</td></tr>
                </table>
                <div class="row g-0 border-top">
                    <div class="col-6 border-end">
                        <div class="px-2 pt-2 small text-muted fw-semibold">Per metode bayar</div>
                        <table class="table table-sm mb-0 small">
                            @forelse($pj['per_metode'] as $m)
                            <tr><td>{{ $m['label'] }}</td><td class="text-end">{{ $rp($m['total']) }}</td></tr>
                            @empty
                            <tr><td class="text-muted">—</td></tr>
                            @endforelse
                        </table>
                    </div>
                    <div class="col-6">
                        <div class="px-2 pt-2 small text-muted fw-semibold">Per tipe transaksi</div>
                        <table class="table table-sm mb-0 small">
                            @forelse($pj['per_tipe'] as $t)
                            <tr><td>{{ $t['label'] }} <span class="text-muted">({{ $t['jumlah_order'] }})</span></td><td class="text-end">{{ $rp($t['total']) }}</td></tr>
                            @empty
                            <tr><td class="text-muted">—</td></tr>
                            @endforelse
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. Laba Rugi --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-graph-up me-1"></i> 2. Laba Rugi <x-tooltip key="closing.laba_rugi" /></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr><td>Omzet bersih</td><td class="text-end">{{ $rp($lr['omzet']) }}</td></tr>
                    <tr><td>HPP (bahan terpakai)</td><td class="text-end text-danger">− {{ $rp($lr['hpp']) }}</td></tr>
                    @if(((float) ($lr['eliminasi_omzet'] ?? 0)) > 0)
                    <tr class="small text-muted"><td colspan="2"><i class="bi bi-info-circle me-1"></i>Kiriman Gudang → cabang tidak dihitung di Keseluruhan (omzet {{ $rp($lr['eliminasi_omzet']) }}, HPP {{ $rp($lr['eliminasi_hpp']) }}) supaya tidak dobel dengan penjualan cabang.</td></tr>
                    @endif
                    <tr class="fw-bold table-light">
                        <td>Laba kotor @if($lr['margin_kotor_persen'] !== null)<span class="text-muted fw-normal">({{ $lr['margin_kotor_persen'] }}%)</span>@endif</td>
                        <td class="text-end">{{ $rp($lr['laba_kotor']) }}</td>
                    </tr>
                    @foreach($lr['pendapatan_lain'] as $row)
                    <tr><td class="ps-3">+ {{ $row['label'] }}</td><td class="text-end">{{ $rp($row['total']) }}</td></tr>
                    @endforeach
                    @forelse($lr['beban'] as $row)
                    <tr><td class="ps-3">− {{ $row['label'] }}</td><td class="text-end text-danger">{{ $rp($row['total']) }}</td></tr>
                    @empty
                    <tr><td class="ps-3 text-muted" colspan="2">Tidak ada beban operasional</td></tr>
                    @endforelse
                    <tr class="fw-bold {{ $lr['laba_bersih'] < 0 ? 'table-danger' : 'table-success' }}">
                        <td>Laba bersih</td><td class="text-end">{{ $rp($lr['laba_bersih']) }}</td>
                    </tr>
                </table>
                <div class="small text-muted px-2 py-2">
                    Pembelian bahan & aset tidak dihitung di sini (sudah diwakili HPP & penyusutan) — lihat Arus Kas.
                </div>
            </div>
        </div>
    </div>

    {{-- 3. Arus Kas --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-arrow-left-right me-1"></i> 3. Arus Kas (uang masuk & keluar) <x-tooltip key="closing.arus_kas" /></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr class="table-light"><th colspan="2">Pemasukan</th></tr>
                    @forelse($ak['pemasukan'] as $row)
                    <tr><td class="ps-3">{{ $row['label'] }}</td><td class="text-end">{{ $rp($row['total']) }}</td></tr>
                    @empty
                    <tr><td class="ps-3 text-muted" colspan="2">—</td></tr>
                    @endforelse
                    <tr class="fw-semibold"><td>Total pemasukan</td><td class="text-end text-success">{{ $rp($ak['total_pemasukan']) }}</td></tr>
                    <tr class="table-light"><th colspan="2">Pengeluaran</th></tr>
                    @forelse($ak['pengeluaran'] as $row)
                    <tr><td class="ps-3">{{ $row['label'] }}</td><td class="text-end">{{ $rp($row['total']) }}</td></tr>
                    @empty
                    <tr><td class="ps-3 text-muted" colspan="2">—</td></tr>
                    @endforelse
                    <tr class="fw-semibold"><td>Total pengeluaran</td><td class="text-end text-danger">{{ $rp($ak['total_pengeluaran']) }}</td></tr>
                    <tr class="fw-bold table-light"><td>Arus kas bersih</td><td class="text-end">{{ $rp($ak['net']) }}</td></tr>
                </table>
            </div>
        </div>
    </div>

    {{-- 4. Mutasi Internal --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-shuffle me-1"></i> 4. Mutasi Internal <x-tooltip key="closing.mutasi" /></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Jenis</th><th class="text-end">Keluar</th><th class="text-end">Masuk</th></tr></thead>
                    @foreach($mt['ringkasan'] as $g)
                    <tr>
                        <td>{{ $g['label'] }}
                            @if($g['dalam_perjalanan'] > 0)
                            <div class="small text-warning">Belum diterima: {{ $rp($g['dalam_perjalanan']) }}</div>
                            @endif
                        </td>
                        <td class="text-end">{{ $g['keluar'] > 0 ? $rp($g['keluar']) : '—' }}</td>
                        <td class="text-end">{{ $g['masuk'] > 0 ? $rp($g['masuk']) : '—' }}</td>
                    </tr>
                    @endforeach
                </table>
                <div class="small text-muted px-2 py-2">
                    Mutasi internal = uang pindah tempat di dalam usaha, <strong>bukan</strong> omzet atau pengeluaran.
                    @if($cabangId === null)
                    Di laporan keseluruhan keluar & masuk saling meniadakan.
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- 5. Saldo Kas --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-wallet2 me-1"></i> 5. Saldo Kas <x-tooltip key="closing.saldo_kas" /></div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Kas</th>
                            @if($cabangId === null)<th>Cabang</th>@endif
                            <th class="text-end">Saldo Awal</th>
                            <th class="text-end">Masuk</th>
                            <th class="text-end">Keluar</th>
                            <th class="text-end">Mutasi Masuk</th>
                            <th class="text-end">Mutasi Keluar</th>
                            <th class="text-end">Saldo Akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(['tunai' => 'Kas Tunai', 'bank' => 'Kas Bank / QRIS / Transfer'] as $grup => $judul)
                        @php $rowsGrup = collect($sk['rows'])->where('grup', $grup); $tot = $sk['total_' . $grup]; @endphp
                        @if($rowsGrup->isNotEmpty())
                        <tr class="table-secondary"><td colspan="{{ $cabangId === null ? 8 : 7 }}" class="fw-semibold small">{{ $judul }}</td></tr>
                        @foreach($rowsGrup as $r)
                        <tr>
                            <td>{{ $r['nama_kas'] }}</td>
                            @if($cabangId === null)<td class="small text-muted">{{ $r['cabang'] }}</td>@endif
                            <td class="text-end">{{ $rp($r['saldo_awal']) }}</td>
                            <td class="text-end">{{ $rp($r['masuk']) }}</td>
                            <td class="text-end">{{ $rp($r['keluar']) }}</td>
                            <td class="text-end">{{ $rp($r['mutasi_masuk']) }}</td>
                            <td class="text-end">{{ $rp($r['mutasi_keluar']) }}</td>
                            <td class="text-end fw-semibold">{{ $rp($r['saldo_akhir']) }}</td>
                        </tr>
                        @endforeach
                        <tr class="fw-semibold small">
                            <td @if($cabangId === null) colspan="2" @endif>Subtotal {{ $judul }}</td>
                            <td class="text-end">{{ $rp($tot['saldo_awal']) }}</td>
                            <td class="text-end">{{ $rp($tot['masuk']) }}</td>
                            <td class="text-end">{{ $rp($tot['keluar']) }}</td>
                            <td class="text-end">{{ $rp($tot['mutasi_masuk']) }}</td>
                            <td class="text-end">{{ $rp($tot['mutasi_keluar']) }}</td>
                            <td class="text-end">{{ $rp($tot['saldo_akhir']) }}</td>
                        </tr>
                        @endif
                        @endforeach
                        @if(empty($sk['rows']))
                        <tr><td colspan="8" class="text-center text-muted py-3">Belum ada kas</td></tr>
                        @endif
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td @if($cabangId === null) colspan="2" @endif>Total semua kas</td>
                            <td class="text-end">{{ $rp($sk['total']['saldo_awal']) }}</td>
                            <td class="text-end">{{ $rp($sk['total']['masuk']) }}</td>
                            <td class="text-end">{{ $rp($sk['total']['keluar']) }}</td>
                            <td class="text-end">{{ $rp($sk['total']['mutasi_masuk']) }}</td>
                            <td class="text-end">{{ $rp($sk['total']['mutasi_keluar']) }}</td>
                            <td class="text-end">{{ $rp($sk['total']['saldo_akhir']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Hitung uang fisik (closing harian) --}}
    @php $rowsFisik = $kasFisik->whereNotNull('saldo_fisik'); @endphp
    @if($rowsFisik->isNotEmpty())
    <div class="col-12">
        <div class="card border-warning">
            <div class="card-header fw-semibold"><i class="bi bi-cash-stack me-1"></i> Hitung Uang Fisik Laci</div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>Kas</th><th class="text-end">Saldo Sistem</th><th class="text-end">Uang Fisik</th><th class="text-end">Selisih</th></tr>
                    </thead>
                    <tbody>
                        @foreach($rowsFisik as $f)
                        <tr>
                            <td>{{ $f->nama_kas }}</td>
                            <td class="text-end">{{ $rp($f->saldo_akhir) }}</td>
                            <td class="text-end">{{ $rp($f->saldo_fisik) }}</td>
                            <td class="text-end fw-semibold {{ (float) $f->selisih < 0 ? 'text-danger' : ((float) $f->selisih > 0 ? 'text-success' : '') }}">
                                {{ (float) $f->selisih > 0 ? '+' : '' }}{{ $rp($f->selisih) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="small text-muted px-2 py-2">Selisih = uang fisik − saldo sistem. Minus = uang kurang, plus = uang lebih.</div>
            </div>
        </div>
    </div>
    @endif

    {{-- 6. Detail mutasi --}}
    @if(!empty($mt['detail']))
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-list-ul me-1"></i> Rincian Mutasi Internal</div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm mb-0 small align-middle">
                    <thead class="table-light">
                        <tr><th>Tanggal</th><th>Jenis</th><th>Dari</th><th>Ke</th><th class="text-end">Jumlah</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach($mt['detail'] as $d)
                        <tr>
                            <td class="text-nowrap">{{ \Carbon\Carbon::parse($d['tanggal'])->format('d/m/Y') }}</td>
                            <td>{{ $d['jenis'] }}</td>
                            <td>{{ $d['dari'] }}</td>
                            <td>{{ $d['ke'] }}</td>
                            <td class="text-end text-nowrap">{{ $rp($d['jumlah']) }}</td>
                            <td>
                                <span class="badge {{ $d['status'] === 'Selesai' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $d['status'] }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- 7. Stok --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-box-seam me-1"></i> 6. Nilai Persediaan Stok <x-tooltip key="closing.stok" /></div>
            <div class="card-body p-0">
                @if(empty($snapshot) && !$data['meta']['is_realtime_stok'])
                <div class="alert alert-info small m-2 mb-0">
                    Periode ini sudah lewat. Sistem hanya menyimpan stok <strong>saat ini</strong>, jadi angka di bawah
                    adalah nilai hari ini, bukan nilai akhir periode. Setelah fitur simpan closing aktif, nilai stok
                    akan dibekukan saat closing.
                </div>
                @endif
                <table class="table table-sm mb-0">
                    <tr><td>Bahan baku & tambahan</td><td class="text-end">{{ $rp($st['bahan_baku']) }}</td></tr>
                    <tr><td>Kemasan</td><td class="text-end">{{ $rp($st['kemasan']) }}</td></tr>
                    @if($st['barang_jadi'] > 0)
                    <tr><td>Barang jadi</td><td class="text-end">{{ $rp($st['barang_jadi']) }}</td></tr>
                    @endif
                    <tr class="fw-bold table-light"><td>Total nilai persediaan</td><td class="text-end">{{ $rp($st['total']) }}</td></tr>
                </table>
            </div>
        </div>
    </div>

    {{-- 8. Setoran --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-cash-coin me-1"></i> 7. Setoran</div>
            <div class="card-body p-0">
                @if($se['pakai_setoran_kasir'])
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Setoran Kasir</th><th class="text-center">Jumlah</th><th class="text-end">Total Disetor</th></tr></thead>
                    @foreach($se['per_status'] as $s)
                    <tr><td>{{ $s['label'] }}</td><td class="text-center">{{ $s['jumlah'] }}</td><td class="text-end">{{ $rp($s['total']) }}</td></tr>
                    @endforeach
                </table>
                @if($se['hari_belum_setor'] > 0)
                <div class="alert alert-warning small m-2 mb-0">
                    <i class="bi bi-exclamation-triangle"></i>
                    Ada <strong>{{ $se['hari_belum_setor'] }}</strong> hari (per cabang) yang ada penjualannya tapi belum dibuat Setoran Kasir.
                </div>
                @endif
                @else
                <div class="small text-muted p-2">
                    Usaha ini tidak memakai Gudang Pusat, jadi setoran dicatat lewat <strong>Transfer Antar Kas</strong>
                    (lihat section Mutasi Internal).
                </div>
                @endif
                @if($se['transfer_dana_belum_diterima'] > 0)
                <div class="alert alert-warning small m-2">
                    Transfer Dana belum diterima: <strong>{{ $rp($se['transfer_dana_belum_diterima']) }}</strong>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- 9. Per cabang (keseluruhan) --}}
    @if($cabangId === null && !empty($data['per_cabang']))
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-shop me-1"></i> Ringkasan per Cabang</div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Cabang</th><th class="text-end">Transaksi</th><th class="text-end">Omzet</th><th class="text-end">HPP</th>
                            <th class="text-end">Laba Kotor</th><th class="text-end">Pendapatan Lain</th><th class="text-end">Beban</th><th class="text-end">Laba Bersih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['per_cabang'] as $c)
                        <tr>
                            <td>{{ $c['nama_cabang'] }}</td>
                            <td class="text-end">{{ number_format($c['jumlah_order']) }}</td>
                            <td class="text-end">{{ $rp($c['omzet']) }}</td>
                            <td class="text-end">{{ $rp($c['hpp']) }}</td>
                            <td class="text-end">{{ $rp($c['laba_kotor']) }}</td>
                            <td class="text-end">{{ $rp($c['pendapatan_lain']) }}</td>
                            <td class="text-end">{{ $rp($c['beban']) }}</td>
                            <td class="text-end fw-semibold {{ $c['laba_bersih'] < 0 ? 'text-danger' : '' }}">{{ $rp($c['laba_bersih']) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>

<div class="small text-muted mt-3">Dihitung pada {{ \Carbon\Carbon::parse($data['meta']['dihitung_pada'])->format('d/m/Y H:i') }}</div>
