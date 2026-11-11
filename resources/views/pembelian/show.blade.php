@extends('layouts.app')

@section('title', 'Detail PO: ' . $po->nomor_po)

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-bag-check me-2 text-primary"></i>Detail Purchase Order</h5>
    <a href="{{ route('pembelian.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="row g-3">
    {{-- Info PO --}}
    <div class="col-12 col-md-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Informasi PO</span>
                <span class="badge {{ $po->status->badgeClass() }} fs-6">{{ $po->status->label() }}</span>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted">Nomor PO</td><td class="fw-bold">{{ $po->nomor_po }}</td></tr>
                    <tr><td class="text-muted">Tanggal</td><td>{{ $po->tanggal_po->format('d M Y') }}</td></tr>
                    <tr><td class="text-muted">Supplier</td><td>{{ $po->supplier?->nama_supplier ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Lokasi</td><td>{{ $po->cabang?->nama_cabang ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Total</td><td class="fw-bold text-primary">Rp {{ number_format($po->total_harga, 0, ',', '.') }}</td></tr>
                    <tr><td class="text-muted">Dibuat oleh</td><td>{{ $po->createdBy?->name ?? '-' }}</td></tr>
                    @if($po->approvedBy)
                    <tr><td class="text-muted">Disetujui oleh</td><td>{{ $po->approvedBy->name }}</td></tr>
                    @endif
                    @if($po->tanggal_terima)
                    <tr><td class="text-muted">Tanggal Terima</td><td>{{ $po->tanggal_terima->format('d M Y') }}</td></tr>
                    @endif
                </table>
                @if($po->pembelian_langsung)
                <div class="alert alert-warning py-2 mt-2 mb-0 small">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <strong>Pembelian Langsung/Mendesak</strong>
                    @if($po->alasan_langsung)<br>{{ $po->alasan_langsung }}@endif
                </div>
                @endif
                @if($po->catatan)
                <p class="text-muted small mt-2 mb-0"><i class="bi bi-chat-left-text me-1"></i>{{ $po->catatan }}</p>
                @endif
            </div>
        </div>

        {{-- Tahap C 4.38 — PO ke supplier internal: nota POS lokasi supplier --}}
        @if($po->supplier?->cabang_id)
        @php
            $notaGudang = $po->ordersGudang()->where('status', '!=', \App\Enums\StatusOrder::Dibatalkan->value)->latest('id')->first();
        @endphp
        <div class="card mt-3 {{ $notaGudang && abs((float) $notaGudang->total_bayar - (float) $po->total_harga) >= 1 ? 'border-warning' : '' }}">
            <div class="card-header"><i class="bi bi-shop me-1"></i>Nota dari {{ $po->supplier->nama_supplier }} <x-tooltip key="pembelian.nota_internal" /></div>
            <div class="card-body small">
                @if($notaGudang)
                    Diproses di POS: <strong>{{ $notaGudang->nomor_order }}</strong>
                    ({{ $notaGudang->tanggal_order?->format('d/m/Y') }}) — total nota
                    <strong>Rp {{ number_format((float) $notaGudang->total_bayar, 0, ',', '.') }}</strong>.
                    @if(abs((float) $notaGudang->total_bayar - (float) $po->total_harga) >= 1)
                    <div class="alert alert-warning py-2 mt-2 mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>Total PO (Rp {{ number_format((float) $po->total_harga, 0, ',', '.') }}) berbeda dengan nota.
                        Sesuaikan PO ini (edit atau buat ulang) sebelum diterima & dibayar.
                    </div>
                    @endif
                @else
                    <span class="text-muted">Belum diproses di POS {{ $po->supplier->nama_supplier }}.</span>
                @endif
            </div>
        </div>
        @endif

        {{-- Timeline Status --}}
        <div class="card mt-3">
            <div class="card-header">Timeline Status</div>
            <div class="card-body">
                @php
                    $steps = [
                        ['status' => 'draft', 'label' => 'Draft', 'icon' => 'file-earmark'],
                        ['status' => 'disetujui', 'label' => 'Disetujui', 'icon' => 'check-circle'],
                        ['status' => 'dikirim_supplier', 'label' => 'Dikirim Supplier', 'icon' => 'truck'],
                        ['status' => 'diterima', 'label' => 'Diterima', 'icon' => 'box-seam'],
                    ];
                    $statusOrder = ['draft' => 0, 'disetujui' => 1, 'dikirim_supplier' => 2, 'diterima' => 3, 'dibatalkan' => -1];
                    $currentOrder = $statusOrder[$po->status->value] ?? 0;
                @endphp

                @if($po->status->value === 'dibatalkan')
                <div class="text-center text-danger py-2">
                    <i class="bi bi-x-circle fs-3 d-block mb-1"></i>
                    <strong>Dibatalkan</strong>
                </div>
                @else
                <div class="d-flex flex-column gap-2">
                    @foreach($steps as $i => $step)
                    @php $done = $i <= $currentOrder; @endphp
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                            style="width:32px;height:32px;background:{{ $done ? '#3b82f6' : '#e2e8f0' }};color:{{ $done ? 'white' : '#94a3b8' }}">
                            <i class="bi bi-{{ $step['icon'] }}" style="font-size:0.85rem"></i>
                        </div>
                        <span class="{{ $done ? 'fw-semibold text-dark' : 'text-muted' }}">{{ $step['label'] }}</span>
                        @if($i === $currentOrder && $po->status->value !== 'diterima')
                        <span class="badge bg-primary ms-auto">Sekarang</span>
                        @endif
                    </div>
                    @if($i < count($steps) - 1)
                    <div class="ms-4 ps-1" style="border-left:2px solid {{ $i < $currentOrder ? '#3b82f6' : '#e2e8f0' }};height:16px;"></div>
                    @endif
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="card mt-3">
            <div class="card-body">
                @if($po->status->value === 'draft')
                    @can('pembelian.approve')
                    <form method="POST" action="{{ route('pembelian.approve', $po) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-success w-100 mb-2">
                            <i class="bi bi-check-circle me-1"></i>Setujui PO
                        </button>
                    </form>
                    @endcan
                    @can('pembelian.delete')
                    <form method="POST" action="{{ route('pembelian.batalkan', $po) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100 mb-2"
                            onclick="return confirm('Batalkan PO ini?')">
                            <i class="bi bi-x-circle me-1"></i>Batalkan
                        </button>
                    </form>
                    <form method="POST" action="{{ route('pembelian.destroy', $po) }}" class="d-inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger w-100"
                            onclick="return confirm('Hapus PO ini permanen?')">
                            <i class="bi bi-trash me-1"></i>Hapus
                        </button>
                    </form>
                    @endcan

                @elseif($po->status->value === 'disetujui')
                    @can('pembelian.kirim-supplier')
                    <form method="POST" action="{{ route('pembelian.kirim-supplier', $po) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary w-100 mb-2">
                            <i class="bi bi-truck me-1"></i>Kirim ke Supplier
                        </button>
                    </form>
                    @endcan
                    @can('pembelian.delete')
                    <form method="POST" action="{{ route('pembelian.batalkan', $po) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100"
                            onclick="return confirm('Batalkan PO ini?')">
                            <i class="bi bi-x-circle me-1"></i>Batalkan
                        </button>
                    </form>
                    @endcan

                @elseif($po->status->value === 'dikirim_supplier')
                    @can('pembelian.terima')
                    <button type="button" class="btn btn-success w-100" data-bs-toggle="collapse" data-bs-target="#formTerima">
                        <i class="bi bi-box-seam me-1"></i>Proses Penerimaan Barang
                    </button>
                    @endcan

                @elseif($po->status->value === 'diterima')
                    <div class="text-center text-success">
                        <i class="bi bi-check-circle-fill fs-3 d-block mb-1"></i>
                        <strong>PO Telah Diterima</strong>
                        <div class="text-muted small">{{ $po->tanggal_terima?->format('d M Y') }}</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Status Pembayaran — murni tampilan, dari referensi_type/referensi_id
             di transaksi_keuangans (reuse pola polymorphic yang sudah dipakai
             order), tidak menambah kolom apapun di purchase_orders --}}
        @if($po->status->value === 'diterima')
        <div class="card mt-3">
            <div class="card-header">Status Pembayaran</div>
            <div class="card-body text-center">
                @if($transaksiPembayaran)
                <span class="badge bg-success fs-6 mb-2 d-inline-block">
                    <i class="bi bi-check-circle me-1"></i>Sudah Dibayar
                </span>
                <div class="text-muted small">
                    {{ $transaksiPembayaran->nomor_transaksi }} — {{ \Carbon\Carbon::parse($transaksiPembayaran->tanggal_transaksi)->format('d M Y') }}
                </div>
                @can('keuangan.view')
                <a href="{{ route('keuangan.show', $transaksiPembayaran->id) }}" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="bi bi-eye me-1"></i>Lihat Transaksi
                </a>
                @endcan

                {{-- Batal Bayar PO (Fase 4) — kasus salah kas/salah PO/PO
                     cancelled. Query terpisah dari $transaksiPembayaran
                     (stdClass dari PoDashboardService::cekSudahDibayar(),
                     tidak select kas_id) supaya bisa tampilkan nama kas di
                     modal tanpa mengubah method existing yang dipakai
                     bareng Cleanup Tool & List PO. --}}
                @can('po.batal_bayar.action')
                    @php
                        $trxBatalBayar = \App\Models\TransaksiKeuangan::with('kas')
                            ->where('referensi_type', 'purchase_order')
                            ->where('referensi_id', $po->id)
                            ->whereNull('deleted_at')
                            ->first();
                    @endphp
                    @if($trxBatalBayar)
                    <div class="mt-2">
                        <button type="button" class="btn btn-outline-danger btn-sm"
                                data-bs-toggle="modal" data-bs-target="#modalBatalBayarPo">
                            <i class="bi bi-x-circle me-1"></i>Batal Bayar PO
                        </button>
                    </div>

                    <div class="modal fade" id="modalBatalBayarPo" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header bg-warning-subtle">
                                    <h5 class="modal-title">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Konfirmasi Batal Bayar PO
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body text-start">
                                    <p>Aksi ini akan:</p>
                                    <ul class="mb-3">
                                        <li>
                                            Hapus transaksi Kas: <strong>{{ $trxBatalBayar->nomor_transaksi }}</strong>
                                            — Rp {{ number_format($trxBatalBayar->jumlah, 0, ',', '.') }}
                                        </li>
                                        <li>
                                            Kembalikan saldo <strong>{{ $trxBatalBayar->kas?->nama_kas ?? 'Kas' }}</strong>:
                                            <span class="text-success">+Rp {{ number_format($trxBatalBayar->jumlah, 0, ',', '.') }}</span>
                                        </li>
                                        <li>
                                            Status pembayaran PO ini:
                                            <span class="badge bg-success">Sudah Dibayar</span>
                                            → <span class="badge bg-warning text-dark">Belum Dibayar</span>
                                        </li>
                                    </ul>
                                    <div class="alert alert-info small mb-0">
                                        <i class="bi bi-info-circle me-1"></i>
                                        PO tidak dihapus, hanya status pembayaran yang direset. Bisa dibayar ulang
                                        dengan Kas/PO yang benar lewat menu Kas Keluar setelah ini.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <form method="POST" action="{{ route('pembelian.batal-bayar', $po->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-danger">Ya, Batal Bayar</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                @endcan
                @else
                <span class="badge bg-warning-subtle text-warning fs-6 mb-2 d-inline-block">
                    <i class="bi bi-clock-history me-1"></i>Belum Dibayar
                </span>
                @can('kas.pilih_po.view')
                <div>
                    <a href="{{ route('keuangan.create', ['po_id' => $po->id]) }}" class="btn btn-sm btn-success mt-2">
                        <i class="bi bi-cash-coin me-1"></i>Catat Pembayaran
                    </a>
                </div>
                @endcan
                @can('transaksi.link_po.action')
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="cariTransaksiExisting()">
                        <i class="bi bi-search me-1"></i>Cari Transaksi Existing
                    </button>
                    <div class="form-text mt-1">Untuk PO yang fisiknya sudah dibayar tapi belum ter-link (dicatat manual tanpa "Pilih PO").</div>
                </div>
                @endcan
                @endif
            </div>
        </div>
        @endif
    </div>

    {{-- Item & Form Terima --}}
    <div class="col-12 col-md-7">
        {{-- Tabel Items --}}
        <div class="card">
            <div class="card-header">Daftar Item PO</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Barang</th>
                            <th class="text-end">Qty Pesan</th>
                            <th class="text-end">Qty Terima</th>
                            <th class="text-end">Harga</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($po->items as $i => $item)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>
                                <div class="fw-semibold">{{ $item->item?->nama_item ?? '-' }}</div>
                                <small class="text-muted">{{ $item->item?->satuan ?? '-' }}</small>
                            </td>
                            <td class="text-end">
                                {{ fmt_qty($item->qty_pesan) }} {{ $item->item?->satuan }}
                                @if($item->unit_input && $item->qty_input)
                                    <div class="small text-muted">(input: {{ fmt_qty($item->qty_input) }} {{ $item->unit_input }})</div>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($item->qty_terima !== null)
                                    {{ fmt_qty($item->qty_terima) }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-end">Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($item->total_harga, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="5" class="text-end">Total</td>
                            <td class="text-end text-primary">Rp {{ number_format($po->total_harga, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Form Penerimaan --}}
        @can('pembelian.terima')
        @if($po->status->value === 'dikirim_supplier')
        <div class="card mt-3 collapse" id="formTerima">
            <div class="card-header bg-success text-white">Form Penerimaan Barang</div>
            <div class="card-body">
                <form method="POST" action="{{ route('pembelian.terima', $po) }}">
                    @csrf
                    <p class="text-muted small mb-3">
                        Isi qty yang benar-benar diterima. Jika sama dengan qty pesan, bisa langsung submit.
                    </p>
                    @foreach($po->items as $item)
                    <div class="row g-2 align-items-center mb-2">
                        <div class="col-7">
                            <span class="fw-semibold">{{ $item->item?->nama_item ?? '-' }}</span>
                            <span class="text-muted small ms-1">(Pesan: {{ fmt_qty($item->qty_pesan) }} {{ $item->item?->satuan }})</span>
                        </div>
                        <div class="col-5">
                            <input type="number" name="qty_terima[{{ $item->id }}]"
                                class="form-control form-control-sm"
                                value="{{ $item->qty_pesan }}"
                                step="0.001" min="0"
                                placeholder="Qty terima">
                        </div>
                    </div>
                    @endforeach
                    <button type="submit" class="btn btn-success mt-3">
                        <i class="bi bi-check-circle me-1"></i>Konfirmasi Penerimaan & Update Stok
                    </button>
                </form>
            </div>
        </div>
        @endif
        @endcan
    </div>
</div>

@can('transaksi.link_po.action')
{{-- Modal Cari Transaksi Existing — Cleanup Tool B1 --}}
<div class="modal fade" id="modalCariTransaksi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-search me-2"></i>Cari Transaksi Existing — PO {{ $po->nomor_po }}
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">
                    Menampilkan transaksi <strong>Pengeluaran</strong> di cabang ini yang belum ter-link ke sumber
                    manapun, dalam rentang ± 3 hari dari tanggal terima PO ({{ $po->tanggal_terima?->format('d M Y') ?? '-' }}).
                    Verifikasi manual nominal &amp; keterangan sebelum klik <strong>Link</strong>.
                </p>
                <div id="cariTransaksiLoading" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                    <div class="text-muted small mt-2">Memuat data...</div>
                </div>
                <div id="cariTransaksiEmpty" class="text-center text-muted py-4" style="display:none">
                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>Tidak ada transaksi kandidat ditemukan di rentang tanggal ini.
                </div>
                <div id="cariTransaksiList" class="table-responsive" style="display:none">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Nomor</th>
                                <th>Keterangan</th>
                                <th>Kategori</th>
                                <th class="text-end">Jumlah</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="cariTransaksiTbody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<form id="linkTransaksiForm" method="POST" action="{{ route('pembelian.link-transaksi', $po) }}" style="display:none">
    @csrf
    <input type="hidden" name="transaksi_id" id="linkTransaksiIdInput">
</form>
@endcan
@endsection

@can('transaksi.link_po.action')
@push('scripts')
<script>
const totalPoNominal = {{ (float) $po->total_harga }};

function cariTransaksiExisting() {
    const modalEl = document.getElementById('modalCariTransaksi');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    const loading = document.getElementById('cariTransaksiLoading');
    const empty   = document.getElementById('cariTransaksiEmpty');
    const list    = document.getElementById('cariTransaksiList');
    const tbody   = document.getElementById('cariTransaksiTbody');

    loading.style.display = '';
    empty.style.display   = 'none';
    list.style.display    = 'none';
    tbody.innerHTML = '';

    fetch('{{ route('pembelian.cari-transaksi', $po) }}', {
        headers: { 'Accept': 'application/json' }
    })
        .then(res => res.json())
        .then(json => {
            loading.style.display = 'none';
            const data = json.data || [];
            if (data.length === 0) {
                empty.style.display = '';
                if (json.message) empty.querySelector('span')?.remove();
                return;
            }
            data.forEach(t => {
                const tr = document.createElement('tr');
                if (t.exact_match) tr.classList.add('table-success');
                // Poin 4: tombol "Link" cuma aktif untuk nominal exact match ke
                // total PO — baris non-exact TETAP ditampilkan (Owner masih perlu
                // konteksnya), tapi tombolnya disabled + tooltip supaya tidak
                // ke-klik tanpa sadar. Backend (linkTransaksi()) tetap re-verify
                // exact_match sendiri sebagai defense-in-depth kalau di-bypass.
                const tombolLink = t.exact_match
                    ? `<button type="button" class="btn btn-sm btn-success" onclick="linkTransaksi(${t.id}, '${(t.nomor_transaksi + '').replace(/'/g, "\\'")}')">
                        <i class="bi bi-link-45deg me-1"></i>Link
                      </button>`
                    : `<button type="button" class="btn btn-sm btn-secondary" disabled
                        data-bs-toggle="tooltip" data-bs-placement="top"
                        title="Nominal tidak sama persis dengan total PO — verifikasi manual dulu, edit transaksi kalau perlu">
                        <i class="bi bi-link-45deg me-1"></i>Link
                      </button>`;
                tr.innerHTML = `
                    <td>${t.tanggal_transaksi}</td>
                    <td><code style="font-size:0.78rem">${t.nomor_transaksi}</code></td>
                    <td>${t.keterangan}${t.exact_match ? ' <span class="badge bg-success ms-1">Nominal &amp; cocok</span>' : ''}</td>
                    <td><span class="badge bg-secondary bg-opacity-15 text-dark">${t.kategori}</span></td>
                    <td class="text-end">Rp ${new Intl.NumberFormat('id-ID').format(t.jumlah)}</td>
                    <td class="text-center">${tombolLink}</td>`;
                tbody.appendChild(tr);
            });
            list.style.display = '';

            // Aktifkan tooltip Bootstrap utk tombol Link yang disabled
            document.querySelectorAll('#cariTransaksiTbody [data-bs-toggle="tooltip"]')
                .forEach(el => new bootstrap.Tooltip(el));
        })
        .catch(() => {
            loading.style.display = 'none';
            empty.style.display = '';
        });
}

function linkTransaksi(transaksiId, nomor) {
    if (!confirm('Link transaksi ' + nomor + ' ke PO {{ $po->nomor_po }}? Status pembayaran PO akan otomatis berubah jadi "Sudah Dibayar".')) {
        return;
    }
    document.getElementById('linkTransaksiIdInput').value = transaksiId;
    document.getElementById('linkTransaksiForm').submit();
}
</script>
@endpush
@endcan
