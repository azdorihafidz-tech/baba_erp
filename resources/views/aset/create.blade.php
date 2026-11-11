@extends('layouts.app')

@section('title', 'Tambah Aset')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Aset Baru</h5>
    <a href="{{ route('aset.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<form method="POST" action="{{ route('aset.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="row g-3">

        <div class="col-12 col-lg-8">
            <div class="card mb-3">
                <div class="card-header">Informasi Aset</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Kode Aset <span class="text-danger">*</span> <x-tooltip key="aset.kode_aset" /></label>
                            <input type="text" name="kode_aset" class="form-control @error('kode_aset') is-invalid @enderror"
                                value="{{ old('kode_aset', $kodeAset) }}" required>
                            @error('kode_aset')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label fw-semibold">Nama Aset <span class="text-danger">*</span></label>
                            <input type="text" name="nama_aset" class="form-control @error('nama_aset') is-invalid @enderror"
                                value="{{ old('nama_aset') }}" placeholder="Mesin Giling, Freezer, Kendaraan..." required>
                            @error('nama_aset')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Kategori <span class="text-danger">*</span> <x-tooltip key="aset.kategori_aset_id" /></label>
                            <select name="kategori_aset_id" class="form-select @error('kategori_aset_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Kategori --</option>
                                @foreach($kategoris as $k)
                                <option value="{{ $k->id }}" @selected(old('kategori_aset_id') == $k->id)>{{ $k->nama_kategori }}</option>
                                @endforeach
                            </select>
                            @error('kategori_aset_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Lokasi <span class="text-danger">*</span> <x-tooltip key="aset.lokasi_id" /></label>
                            <select name="lokasi_id" class="form-select @error('lokasi_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Lokasi --</option>
                                @foreach($lokasis as $l)
                                <option value="{{ $l->id }}" @selected(old('lokasi_id') == $l->id)>{{ $l->nama_cabang }}</option>
                                @endforeach
                            </select>
                            @error('lokasi_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Tanggal Perolehan <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_perolehan" class="form-control @error('tanggal_perolehan') is-invalid @enderror"
                                value="{{ old('tanggal_perolehan', date('Y-m-d')) }}" required>
                            @error('tanggal_perolehan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <x-input-rupiah name="harga_perolehan" label="Harga Perolehan" :value="old('harga_perolehan', 0)" required />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-input-rupiah name="nilai_residu" label="Nilai Residu" :value="old('nilai_residu', 0)" />
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Kondisi <span class="text-danger">*</span> <x-tooltip key="aset.kondisi" /></label>
                            <select name="kondisi" class="form-select @error('kondisi') is-invalid @enderror" required>
                                @foreach($kondisis as $k)
                                <option value="{{ $k->value }}" @selected(old('kondisi', 'baik') === $k->value)>{{ $k->label() }}</option>
                                @endforeach
                            </select>
                            @error('kondisi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Foto Aset</label>
                            <input type="file" name="foto" class="form-control" accept="image/*">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Catatan</label>
                            <textarea name="catatan" class="form-control" rows="2">{{ old('catatan') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-header">Metode Penyusutan</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Metode <span class="text-danger">*</span> <x-tooltip key="aset.metode_penyusutan" /></label>
                        <select name="metode_penyusutan" id="metodeSelect" class="form-select" required onchange="toggleMetode(this.value)">
                            @foreach($metodes as $m)
                            <option value="{{ $m->value }}" @selected(old('metode_penyusutan', 'garis_lurus') === $m->value)>{{ $m->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Umur Ekonomis (Bulan) <span class="text-danger">*</span> <x-tooltip key="aset.umur_ekonomis_bulan" /></label>
                        <input type="number" name="umur_ekonomis_bulan" class="form-control"
                            value="{{ old('umur_ekonomis_bulan', 60) }}" min="1" required>
                    </div>
                    <div id="sectionTarif" class="mb-3" style="display:none">
                        <label class="form-label fw-semibold">Tarif Penyusutan (%/bulan) <x-tooltip key="aset.tarif_penyusutan" /></label>
                        <input type="number" name="tarif_penyusutan" class="form-control" step="0.01"
                            value="{{ old('tarif_penyusutan', 0) }}" min="0" max="100">
                    </div>
                    <div id="sectionProduksi" class="mb-3" style="display:none">
                        <label class="form-label fw-semibold">Estimasi Total Produksi (unit) <x-tooltip key="aset.estimasi_produksi_total" /></label>
                        <input type="number" name="estimasi_produksi_total" class="form-control"
                            value="{{ old('estimasi_produksi_total', 0) }}" min="0">
                    </div>
                    <button type="button" class="btn btn-outline-info btn-sm w-100" onclick="bukaModalPenyusutan()">
                        <i class="bi bi-book me-1"></i>Panduan Metode Penyusutan
                    </button>

                    <x-asset-calculator />
                </div>
            </div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle me-1"></i>Simpan Aset
            </button>
            <a href="{{ route('aset.index') }}" class="btn btn-outline-secondary ms-2">Batal</a>
        </div>
    </div>
</form>

{{-- Modal Panduan Metode Penyusutan --}}
<div class="modal fade" id="modalPenyusutan" tabindex="-1" aria-labelledby="modalPenyusutanLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalPenyusutanLabel">
                    <i class="bi bi-book me-2"></i>Panduan Metode Penyusutan Aset
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                {{-- Tab Navigation --}}
                <ul class="nav nav-pills gap-2 p-3 pb-0 border-bottom" id="tabPenyusutan">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-garisLurus">
                            <i class="bi bi-arrow-right me-1"></i>Garis Lurus
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-saldoMenurun">
                            <i class="bi bi-graph-down-arrow me-1"></i>Saldo Menurun
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-unitProduksi">
                            <i class="bi bi-gear me-1"></i>Satuan Produksi
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-4">
                    {{-- Tab 1: Garis Lurus --}}
                    <div class="tab-pane fade show active" id="tab-garisLurus">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:42px;height:42px;flex-shrink:0">
                                <i class="bi bi-arrow-right fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0">Metode Garis Lurus <span class="text-muted fw-normal">(Straight Line)</span></h6>
                                <small class="text-muted">Paling sederhana dan paling banyak dipakai</small>
                            </div>
                        </div>

                        <p class="text-muted small mb-3">Nilai penyusutan <strong>sama setiap bulan/tahun</strong> sepanjang umur manfaat aset. Cocok untuk aset yang nilainya turun merata seiring waktu.</p>

                        <div class="card bg-light border-0 mb-3">
                            <div class="card-body py-3">
                                <div class="small text-muted mb-1 fw-semibold">RUMUS</div>
                                <div class="text-center py-2">
                                    <span class="fw-bold">Penyusutan per Bulan</span>
                                    <span class="mx-2">=</span>
                                    <span class="border-bottom border-dark px-2 d-inline-block text-center">
                                        Harga Perolehan &minus; Nilai Residu
                                    </span>
                                    <span class="mx-1">&divide;</span>
                                    <span class="fw-semibold">Umur Ekonomis (bulan)</span>
                                </div>
                            </div>
                        </div>

                        <div class="card border-primary border-opacity-25 mb-3">
                            <div class="card-header bg-primary bg-opacity-10 py-2 small fw-semibold text-primary">
                                <i class="bi bi-calculator me-1"></i>Contoh Perhitungan
                            </div>
                            <div class="card-body py-3 small">
                                <div class="row g-2 mb-3">
                                    <div class="col-6"><span class="text-muted">Harga beli mesin</span></div>
                                    <div class="col-6 fw-semibold">Rp 10.000.000</div>
                                    <div class="col-6"><span class="text-muted">Nilai residu</span></div>
                                    <div class="col-6 fw-semibold">Rp 0</div>
                                    <div class="col-6"><span class="text-muted">Umur ekonomis</span></div>
                                    <div class="col-6 fw-semibold">60 bulan (5 tahun)</div>
                                </div>
                                <div class="alert alert-primary py-2 mb-2">
                                    <strong>Penyusutan per bulan</strong> = (10.000.000 &minus; 0) &divide; 60 = <strong>Rp 166.667</strong>
                                </div>
                                <div class="text-muted">Setiap bulan nilai buku berkurang Rp 166.667 selama 5 tahun.</div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3">
                                <i class="bi bi-building me-1"></i>Bangunan
                            </span>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3">
                                <i class="bi bi-display me-1"></i>Peralatan Kantor
                            </span>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3">
                                <i class="bi bi-tools me-1"></i>Furniture
                            </span>
                        </div>
                    </div>

                    {{-- Tab 2: Saldo Menurun --}}
                    <div class="tab-pane fade" id="tab-saldoMenurun">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width:42px;height:42px;flex-shrink:0">
                                <i class="bi bi-graph-down-arrow fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0">Metode Saldo Menurun <span class="text-muted fw-normal">(Declining Balance)</span></h6>
                                <small class="text-muted">Penyusutan besar di awal, makin kecil di akhir</small>
                            </div>
                        </div>

                        <p class="text-muted small mb-3">Nilai penyusutan <strong>lebih besar di tahun-tahun awal</strong> lalu semakin kecil. Mencerminkan kenyataan bahwa aset baru lebih produktif dan nilainya turun lebih cepat di awal.</p>

                        <div class="card bg-light border-0 mb-3">
                            <div class="card-body py-3">
                                <div class="small text-muted mb-1 fw-semibold">RUMUS</div>
                                <div class="text-center py-2 fw-bold">
                                    Penyusutan = Nilai Buku Awal Bulan &times; Tarif (%)
                                </div>
                            </div>
                        </div>

                        <div class="card border-warning border-opacity-25 mb-3">
                            <div class="card-header bg-warning bg-opacity-10 py-2 small fw-semibold text-warning">
                                <i class="bi bi-calculator me-1"></i>Contoh Perhitungan
                            </div>
                            <div class="card-body py-3 small">
                                <div class="row g-2 mb-3">
                                    <div class="col-6"><span class="text-muted">Nilai awal</span></div>
                                    <div class="col-6 fw-semibold">Rp 10.000.000</div>
                                    <div class="col-6"><span class="text-muted">Tarif penyusutan</span></div>
                                    <div class="col-6 fw-semibold">20% / tahun</div>
                                </div>
                                <table class="table table-sm table-bordered mb-2">
                                    <thead class="table-warning">
                                        <tr>
                                            <th>Tahun</th>
                                            <th>Nilai Buku Awal</th>
                                            <th>Penyusutan (20%)</th>
                                            <th>Nilai Buku Akhir</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>1</td><td>10.000.000</td><td class="text-danger fw-semibold">2.000.000</td><td>8.000.000</td></tr>
                                        <tr><td>2</td><td>8.000.000</td><td class="text-danger fw-semibold">1.600.000</td><td>6.400.000</td></tr>
                                        <tr><td>3</td><td>6.400.000</td><td class="text-danger fw-semibold">1.280.000</td><td>5.120.000</td></tr>
                                        <tr class="text-muted"><td>...</td><td>...</td><td>...</td><td>...</td></tr>
                                    </tbody>
                                </table>
                                <div class="text-muted">Nilai penyusutan terus mengecil karena dihitung dari nilai buku yang semakin kecil.</div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 py-2 px-3">
                                <i class="bi bi-truck me-1"></i>Kendaraan
                            </span>
                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 py-2 px-3">
                                <i class="bi bi-gear-wide-connected me-1"></i>Mesin Produksi
                            </span>
                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 py-2 px-3">
                                <i class="bi bi-cpu me-1"></i>Elektronik
                            </span>
                        </div>
                    </div>

                    {{-- Tab 3: Satuan Produksi --}}
                    <div class="tab-pane fade" id="tab-unitProduksi">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="rounded-circle bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center" style="width:42px;height:42px;flex-shrink:0">
                                <i class="bi bi-gear fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0">Metode Satuan Produksi <span class="text-muted fw-normal">(Units of Production)</span></h6>
                                <small class="text-muted">Penyusutan berdasarkan pemakaian / output aktual</small>
                            </div>
                        </div>

                        <p class="text-muted small mb-3">Nilai penyusutan <strong>tergantung seberapa banyak aset dipakai</strong>. Bulan ramai produksi = susut lebih banyak. Bulan sepi = susut lebih sedikit. Paling akurat untuk mesin produksi.</p>

                        <div class="card bg-light border-0 mb-3">
                            <div class="card-body py-3">
                                <div class="small text-muted mb-1 fw-semibold">RUMUS</div>
                                <div class="text-center py-2">
                                    <span class="fw-bold">Penyusutan</span>
                                    <span class="mx-2">=</span>
                                    <span class="border-bottom border-dark px-2 d-inline-block text-center">
                                        Harga &minus; Residu
                                    </span>
                                    <span class="mx-1">&divide;</span>
                                    <span class="fw-semibold">Total Estimasi Produksi</span>
                                    <span class="mx-1">&times;</span>
                                    <span class="fw-semibold text-info">Produksi Bulan Ini</span>
                                </div>
                            </div>
                        </div>

                        <div class="card border-info border-opacity-25 mb-3">
                            <div class="card-header bg-info bg-opacity-10 py-2 small fw-semibold text-info">
                                <i class="bi bi-calculator me-1"></i>Contoh Perhitungan
                            </div>
                            <div class="card-body py-3 small">
                                <div class="row g-2 mb-3">
                                    <div class="col-7"><span class="text-muted">Harga mesin giling</span></div>
                                    <div class="col-5 fw-semibold">Rp 10.000.000</div>
                                    <div class="col-7"><span class="text-muted">Nilai residu</span></div>
                                    <div class="col-5 fw-semibold">Rp 0</div>
                                    <div class="col-7"><span class="text-muted">Estimasi total produksi</span></div>
                                    <div class="col-5 fw-semibold">10.000 kg</div>
                                </div>
                                <div class="alert alert-secondary py-2 mb-2">
                                    <strong>Tarif per kg</strong> = 10.000.000 &divide; 10.000 = <strong>Rp 1.000 / kg</strong>
                                </div>
                                <div class="row g-2">
                                    <div class="col-7 text-muted">Bulan produksi 800 kg</div>
                                    <div class="col-5">= <strong class="text-danger">Rp 800.000</strong></div>
                                    <div class="col-7 text-muted">Bulan produksi 200 kg</div>
                                    <div class="col-5">= <strong class="text-success">Rp 200.000</strong></div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info py-2 small mb-3">
                            <i class="bi bi-lightbulb me-1"></i>
                            <strong>Tip untuk Berkah Mulyo:</strong> Cocok untuk mesin giling karena penyusutan langsung proporsional dengan kg daging yang digiling.
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 py-2 px-3">
                                <i class="bi bi-fan me-1"></i>Mesin Giling
                            </span>
                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 py-2 px-3">
                                <i class="bi bi-wrench me-1"></i>Alat Produksi
                            </span>
                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 py-2 px-3">
                                <i class="bi bi-box-seam me-1"></i>Mesin Pabrik
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function toggleMetode(val) {
    document.getElementById('sectionTarif').style.display = (val === 'saldo_menurun') ? '' : 'none';
    document.getElementById('sectionProduksi').style.display = (val === 'satuan_produksi') ? '' : 'none';
}
toggleMetode(document.getElementById('metodeSelect').value);

function bukaModalPenyusutan() {
    const metode = document.getElementById('metodeSelect').value;
    const tabMap = {
        'garis_lurus':     '#tab-garisLurus',
        'saldo_menurun':   '#tab-saldoMenurun',
        'satuan_produksi': '#tab-unitProduksi',
    };
    const target = tabMap[metode] || '#tab-garisLurus';
    const tabEl = document.querySelector('[data-bs-target="' + target + '"]');
    if (tabEl) bootstrap.Tab.getOrCreateInstance(tabEl).show();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPenyusutan')).show();
}
</script>
@endpush
@endsection
