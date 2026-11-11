@extends('layouts.app')

@section('title', 'Buat Transfer Dana')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-arrow-left-right me-2 text-primary"></i>Buat Transfer Dana
    </h5>
    <a href="{{ route('setoran.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    {!! session('error') !!}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row justify-content-center g-4">
    <div class="col-12 col-xl-7">

        {{-- Ringkasan Kas (tampil setelah pilih kas) --}}
        <div id="kasSummaryCard" class="card border-info mb-3 d-none">
            <div class="card-header bg-info bg-opacity-10 text-info fw-semibold">
                <i class="bi bi-bar-chart me-1"></i>Ringkasan Kas Hari Ini
                <span id="kasSummaryNama" class="ms-1 fw-normal text-muted small"></span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted ps-3">Saldo Awal Hari</td>
                            <td class="text-end pe-3 fw-semibold" id="ks_awal">—</td>
                        </tr>
                        <tbody id="ks_pemasukan_rows"></tbody>
                        <tbody id="ks_pengeluaran_rows"></tbody>
                        <tr class="table-light fw-bold border-top-2">
                            <td class="ps-3">Saldo Sekarang</td>
                            <td class="text-end pe-3 fs-6 text-primary" id="ks_sekarang">—</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-transparent py-2">
                <button type="button" class="btn btn-sm btn-outline-info" id="btnIsiEstimasi">
                    <i class="bi bi-lightning-fill me-1"></i>
                    <span id="btnIsiEstimasiLabel">Isi Estimasi Setor</span>
                </button>
                <small class="text-muted ms-2" id="ks_reserved_info"></small>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-light">
                <i class="bi bi-info-circle me-1 text-info"></i>
                Transfer dari <strong>{{ $cabangAsal?->nama_cabang ?? 'Cabang Aktif' }}</strong>
                — Saldo baru ditambahkan ke tujuan setelah <strong>dikonfirmasi diterima</strong>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('setoran.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-3">

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal" id="inputTanggal"
                                class="form-control @error('tanggal') is-invalid @enderror"
                                value="{{ old('tanggal', date('Y-m-d')) }}" required>
                            @error('tanggal')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Kas Asal <span class="text-danger">*</span></label>
                            <select name="kas_asal_id" id="kasAsalSelect"
                                class="form-select @error('kas_asal_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Kas Asal --</option>
                                @foreach($kasAsal as $kas)
                                <option value="{{ $kas->id }}" @selected(old('kas_asal_id') == $kas->id)
                                    data-saldo="{{ $kas->saldo_sekarang }}">
                                    {{ $kas->nama_kas }} — Saldo: Rp {{ number_format($kas->saldo_sekarang, 0, ',', '.') }}
                                </option>
                                @endforeach
                            </select>
                            @error('kas_asal_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Cabang Tujuan <span class="text-danger">*</span></label>
                            <select name="cabang_tujuan_id" id="cabangTujuanSelect"
                                class="form-select @error('cabang_tujuan_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Cabang Tujuan --</option>
                                @foreach($cabangTujuans as $c)
                                <option value="{{ $c->id }}" @selected(old('cabang_tujuan_id') == $c->id)>
                                    {{ $c->nama_cabang }}
                                </option>
                                @endforeach
                            </select>
                            @error('cabang_tujuan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Kas Tujuan <span class="text-danger">*</span></label>
                            <select name="kas_tujuan_id" id="kasTujuanSelect"
                                class="form-select @error('kas_tujuan_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Cabang dulu --</option>
                            </select>
                            @error('kas_tujuan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Keterangan <span class="text-danger">*</span></label>
                            <input type="text" name="keterangan"
                                class="form-control @error('keterangan') is-invalid @enderror"
                                value="{{ old('keterangan') }}" placeholder="Transfer kas harian bulan Juni..." maxlength="255" required>
                            @error('keterangan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <x-input-rupiah name="jumlah" label="Jumlah Transfer" :value="old('jumlah', 0)" required />
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">
                                Bukti Transfer / Foto <span class="text-danger">*</span>
                                <small class="text-muted fw-normal">(jpg/png/pdf, maks 5MB)</small>
                            </label>
                            <input type="file" name="bukti"
                                class="form-control @error('bukti') is-invalid @enderror"
                                accept=".jpg,.jpeg,.png,.pdf" required>
                            @error('bukti')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Catatan</label>
                            <textarea name="catatan" class="form-control" rows="2"
                                placeholder="Catatan tambahan...">{{ old('catatan') }}</textarea>
                        </div>

                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-1"></i>Kirim Transfer Dana
                            </button>
                            <a href="{{ route('setoran.index') }}" class="btn btn-outline-secondary ms-2">Batal</a>
                        </div>

                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
const KAS_TUJUAN_URL  = '{{ route('setoran.kas-tujuan') }}';
const KAS_SUMMARY_URL = '/setoran/kas-summary'; // /{kasId}/{tanggal}

function fmtRp(n) {
    return 'Rp ' + Number(n).toLocaleString('id-ID');
}

// ---- Kas Tujuan cascade ----
document.getElementById('cabangTujuanSelect').addEventListener('change', async function() {
    const cabangId = this.value;
    const select   = document.getElementById('kasTujuanSelect');
    select.innerHTML = '<option value="">Memuat...</option>';

    if (!cabangId) {
        select.innerHTML = '<option value="">-- Pilih Cabang dulu --</option>';
        return;
    }

    try {
        const resp = await fetch(KAS_TUJUAN_URL + '?cabang_id=' + cabangId, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        });
        const data = await resp.json();
        if (!data.length) {
            select.innerHTML = '<option value="">-- Cabang ini belum punya kas aktif --</option>';
            return;
        }
        select.innerHTML = '<option value="">-- Pilih Kas --</option>';
        data.forEach(k => {
            select.innerHTML += `<option value="${k.id}">${k.nama_kas} — Saldo: Rp ${Number(k.saldo_sekarang).toLocaleString('id-ID')}</option>`;
        });
    } catch(e) {
        select.innerHTML = '<option value="">Gagal memuat kas</option>';
    }
});

// ---- Kas Summary ----
let estimasiSetor  = 0;
let estimasiReserved = 300000;

async function fetchKasSummary() {
    const kasId   = document.getElementById('kasAsalSelect').value;
    const tanggal = document.getElementById('inputTanggal').value;
    const card    = document.getElementById('kasSummaryCard');

    if (!kasId || !tanggal) {
        card.classList.add('d-none');
        return;
    }

    try {
        const resp = await fetch(`${KAS_SUMMARY_URL}/${kasId}/${tanggal}`, {
            headers: { 'Accept': 'application/json' }
        });
        if (!resp.ok) { card.classList.add('d-none'); return; }
        const d = await resp.json();

        document.getElementById('kasSummaryNama').textContent = '— ' + d.kas_nama;
        document.getElementById('ks_awal').textContent       = fmtRp(d.saldo_awal_hari);
        document.getElementById('ks_sekarang').textContent   = fmtRp(d.saldo_sekarang);

        // Pemasukan rows
        let pHtml = '';
        if (d.pemasukan && d.pemasukan.length) {
            pHtml += `<tr><td class="ps-4 text-muted small" colspan="2"><em>Pemasukan hari ini:</em></td></tr>`;
            d.pemasukan.forEach(item => {
                pHtml += `<tr><td class="ps-4 small">${item.label}</td><td class="text-end pe-3 small text-success">+ ${fmtRp(item.total)}</td></tr>`;
            });
            if (d.pemasukan.length > 1) {
                pHtml += `<tr><td class="ps-4 text-muted small">Total Pemasukan</td><td class="text-end pe-3 small fw-semibold text-success">+ ${fmtRp(d.total_pemasukan)}</td></tr>`;
            }
        }
        document.getElementById('ks_pemasukan_rows').innerHTML = pHtml;

        // Pengeluaran rows
        let kHtml = '';
        if (d.pengeluaran && d.pengeluaran.length) {
            kHtml += `<tr><td class="ps-4 text-muted small" colspan="2"><em>Pengeluaran hari ini:</em></td></tr>`;
            d.pengeluaran.forEach(item => {
                kHtml += `<tr><td class="ps-4 small">${item.label}</td><td class="text-end pe-3 small text-danger">- ${fmtRp(item.total)}</td></tr>`;
            });
            if (d.pengeluaran.length > 1) {
                kHtml += `<tr><td class="ps-4 text-muted small">Total Pengeluaran</td><td class="text-end pe-3 small fw-semibold text-danger">- ${fmtRp(d.total_pengeluaran)}</td></tr>`;
            }
        }
        document.getElementById('ks_pengeluaran_rows').innerHTML = kHtml;

        estimasiSetor   = d.estimasi_setor;
        estimasiReserved = d.reserved;
        const btnLabel  = document.getElementById('btnIsiEstimasiLabel');
        btnLabel.textContent = `Isi Estimasi Setor ${fmtRp(d.estimasi_setor)}`;
        document.getElementById('ks_reserved_info').textContent =
            estimasiSetor > 0
                ? `(Saldo ${fmtRp(d.saldo_sekarang)} – Reserved ${fmtRp(d.reserved)})`
                : 'Saldo tidak cukup untuk disetorkan';

        card.classList.remove('d-none');
    } catch(e) {
        card.classList.add('d-none');
    }
}

document.getElementById('kasAsalSelect').addEventListener('change', fetchKasSummary);
document.getElementById('inputTanggal').addEventListener('change', fetchKasSummary);

document.getElementById('btnIsiEstimasi').addEventListener('click', function() {
    if (estimasiSetor <= 0) return;
    // Set hidden (raw) input dan display input dari x-input-rupiah component
    const rawInput     = document.getElementById('raw_jumlah');
    const displayInput = document.getElementById('display_jumlah');
    if (rawInput)     rawInput.value     = estimasiSetor;
    if (displayInput) displayInput.value = Number(estimasiSetor).toLocaleString('id-ID');
});

// Auto-fetch jika sudah ada kas terpilih (misalnya dari old() setelah validation fail)
if (document.getElementById('kasAsalSelect').value) {
    fetchKasSummary();
}
</script>
@endpush
