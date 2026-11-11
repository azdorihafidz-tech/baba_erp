@extends('layouts.app')

@section('title', 'Manajemen Kas')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-wallet2 me-2 text-primary"></i>Manajemen Kas</h5>
        <p class="text-muted mb-0 small">Kelola rekening dan kas tunai per cabang</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('keuangan.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
        @can('keuangan.kas_create')
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahKas">
            <i class="bi bi-plus-lg me-1"></i>Tambah Kas
        </button>
        @endcan
    </div>
</div>

{{-- List Kas --}}
<div class="row g-3">
    @forelse($kass as $kas)
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="badge {{ $kas->tipe_kas === 'tunai' ? 'bg-success' : 'bg-primary' }} mb-1">
                            <i class="bi bi-{{ $kas->tipe_kas === 'tunai' ? 'cash' : 'bank' }} me-1"></i>
                            {{ $kas->tipe_kas === 'tunai' ? 'Tunai' : 'Bank' }}
                        </span>
                        <h6 class="fw-bold mb-0">{{ $kas->nama_kas }}</h6>
                        @if($kas->tipe_kas === 'bank')
                        <small class="text-muted">{{ $kas->nama_bank }} - {{ $kas->nomor_rekening }}</small>
                        @endif
                    </div>
                    <span class="badge {{ $kas->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                        {{ $kas->is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
                <p class="text-muted small mb-1">{{ $kas->cabang?->nama_cabang ?? '-' }}</p>
                <div class="mt-3">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Saldo Awal</span>
                        <span class="small">Rp {{ number_format($kas->saldo_awal, 0, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <span class="fw-semibold">Saldo Sekarang</span>
                        <span class="fw-bold fs-5 text-primary">Rp {{ number_format($kas->saldo_sekarang, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent pt-0 d-flex gap-2 flex-wrap">
                @can('keuangan.kas_edit')
                <button class="btn btn-sm btn-outline-secondary flex-fill"
                    data-bs-toggle="modal" data-bs-target="#modalEditKas{{ $kas->id }}">
                    <i class="bi bi-pencil me-1"></i>Edit
                </button>
                @endcan
                @can('kas.sinkron_saldo.action')
                <button class="btn btn-sm btn-outline-warning flex-fill"
                    onclick="bukaSinkronSaldo({{ $kas->id }}, '{{ addslashes($kas->nama_kas) }}')"
                    title="Sinkron Saldo dari Riwayat Transaksi">
                    <i class="bi bi-arrow-repeat me-1"></i>Sinkron Saldo
                </button>
                @endcan
                @can('keuangan.kas_delete')
                <button class="btn btn-sm btn-outline-danger"
                    onclick="confirmHapusKas({{ $kas->id }}, '{{ addslashes($kas->nama_kas) }}')"
                    title="Hapus Kas">
                    <i class="bi bi-trash"></i>
                </button>
                @endcan
            </div>
        </div>
    </div>

    {{-- Modal Edit Kas --}}
    @php
        // Cuma tampilkan old()/error DI MODAL kas ini kalau error yang di-flash
        // memang berasal dari submit form Edit kas ini — supaya field kas lain
        // di halaman yang sama (tiap kas punya modal Edit sendiri) tidak ikut
        // kebawa pesan error/old-input milik kas yang berbeda.
        $isEditingThis = old('_form_source') === 'edit_' . $kas->id;
    @endphp
    <div class="modal fade" id="modalEditKas{{ $kas->id }}" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('keuangan.kas.update', $kas) }}">
                @csrf @method('PUT')
                <input type="hidden" name="_form_source" value="edit_{{ $kas->id }}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Kas: {{ $kas->nama_kas }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nama Kas</label>
                            <input type="text" name="nama_kas"
                                class="form-control {{ $isEditingThis && $errors->has('nama_kas') ? 'is-invalid' : '' }}"
                                value="{{ $isEditingThis ? old('nama_kas', $kas->nama_kas) : $kas->nama_kas }}" required>
                            @if($isEditingThis) @error('nama_kas')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tipe</label>
                            @php $tipeVal = $isEditingThis ? old('tipe_kas', $kas->tipe_kas) : $kas->tipe_kas; @endphp
                            <select name="tipe_kas" class="form-select {{ $isEditingThis && $errors->has('tipe_kas') ? 'is-invalid' : '' }}">
                                <option value="tunai" @selected($tipeVal === 'tunai')>Tunai</option>
                                <option value="bank" @selected($tipeVal === 'bank')>Bank</option>
                            </select>
                            @if($isEditingThis) @error('tipe_kas')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Default untuk Pembayaran</label>
                            @php $defaultVal = $isEditingThis ? old('default_untuk', $kas->default_untuk) : $kas->default_untuk; @endphp
                            <select name="default_untuk" class="form-select {{ $isEditingThis && $errors->has('default_untuk') ? 'is-invalid' : '' }}">
                                <option value="" @selected(!$defaultVal)>-- Tidak jadi default --</option>
                                <option value="tunai" @selected($defaultVal === 'tunai')>Tunai</option>
                                <option value="transfer" @selected($defaultVal === 'transfer')>Transfer Bank</option>
                                <option value="qris" @selected($defaultVal === 'qris')>QRIS</option>
                            </select>
                            <small class="form-text text-muted">Otomatis dipilih saat order dengan metode ini. Maks. 1 per tipe per cabang.</small>
                            @if($isEditingThis) @error('default_untuk')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nama Bank</label>
                            <input type="text" name="nama_bank"
                                class="form-control {{ $isEditingThis && $errors->has('nama_bank') ? 'is-invalid' : '' }}"
                                value="{{ $isEditingThis ? old('nama_bank', $kas->nama_bank) : $kas->nama_bank }}">
                            @if($isEditingThis) @error('nama_bank')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nomor Rekening</label>
                            <input type="text" name="nomor_rekening"
                                class="form-control {{ $isEditingThis && $errors->has('nomor_rekening') ? 'is-invalid' : '' }}"
                                value="{{ $isEditingThis ? old('nomor_rekening', $kas->nomor_rekening) : $kas->nomor_rekening }}">
                            @if($isEditingThis) @error('nomor_rekening')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="aktif{{ $kas->id }}"
                                @checked($isEditingThis ? old('is_active', $kas->is_active) : $kas->is_active)>
                            <label class="form-check-label" for="aktif{{ $kas->id }}">Aktif</label>
                        </div>
                        <x-input-rupiah name="saldo_minimum" label="Saldo Minimum (Alert)"
                            :value="$isEditingThis ? old('saldo_minimum', (int)$kas->saldo_minimum) : (int)$kas->saldo_minimum" />
                        <small class="form-text text-muted d-block mt-n2 mb-3">Notifikasi tampil di dashboard jika saldo di bawah angka ini. Isi 0 untuk nonaktifkan.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="text-center py-5 text-muted">
            <i class="bi bi-wallet2" style="font-size:2.5rem"></i>
            <p class="mt-2">Belum ada kas. Tambahkan kas baru.</p>
        </div>
    </div>
    @endforelse
</div>

{{-- Modal Tambah Kas --}}
<div class="modal fade" id="modalTambahKas" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('keuangan.kas.store') }}">
            @csrf
            <input type="hidden" name="_form_source" value="tambah">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Kas Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Kas <span class="text-danger">*</span></label>
                        <input type="text" name="nama_kas" class="form-control @error('nama_kas') is-invalid @enderror"
                            value="{{ old('nama_kas') }}" placeholder="Kas Tunai / Kas Bank BRI..." required>
                        @error('nama_kas')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipe <span class="text-danger">*</span></label>
                        <select name="tipe_kas" class="form-select @error('tipe_kas') is-invalid @enderror" required>
                            <option value="tunai" @selected(old('tipe_kas', 'tunai') === 'tunai')>Tunai</option>
                            <option value="bank" @selected(old('tipe_kas') === 'bank')>Bank</option>
                        </select>
                        @error('tipe_kas')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Default untuk Pembayaran</label>
                        <select name="default_untuk" class="form-select @error('default_untuk') is-invalid @enderror">
                            <option value="" @selected(old('default_untuk', '') === '')>-- Tidak jadi default --</option>
                            <option value="tunai" @selected(old('default_untuk') === 'tunai')>Tunai</option>
                            <option value="transfer" @selected(old('default_untuk') === 'transfer')>Transfer Bank</option>
                            <option value="qris" @selected(old('default_untuk') === 'qris')>QRIS</option>
                        </select>
                        <small class="form-text text-muted">Otomatis dipilih saat order dengan metode pembayaran ini. Maks. 1 per tipe per cabang.</small>
                        @error('default_untuk')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Bank</label>
                        <input type="text" name="nama_bank" class="form-control @error('nama_bank') is-invalid @enderror"
                            value="{{ old('nama_bank') }}" placeholder="BRI / BCA / Mandiri...">
                        @error('nama_bank')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nomor Rekening</label>
                        <input type="text" name="nomor_rekening" class="form-control @error('nomor_rekening') is-invalid @enderror"
                            value="{{ old('nomor_rekening') }}" placeholder="123456789">
                        @error('nomor_rekening')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <x-input-rupiah name="saldo_awal" label="Saldo Awal" :value="old('saldo_awal', 0)" required />
                    </div>
                    <div class="mb-1">
                        <x-input-rupiah name="saldo_minimum" label="Saldo Minimum (Alert)" :value="old('saldo_minimum', 0)" />
                    </div>
                    <small class="form-text text-muted d-block mb-3">Notifikasi tampil di dashboard jika saldo di bawah angka ini. Isi 0 untuk nonaktifkan.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>
{{-- Modal Hapus Kas --}}
<div class="modal fade" id="hapusKasModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-trash me-2"></i>Hapus Kas</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Hapus kas <strong id="hapusKasNama"></strong>?</p>
                <p class="text-muted small mb-0">Kas hanya bisa dihapus jika saldo = 0. Data bisa dipulihkan dari menu <strong>Data Terhapus</strong>.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <form id="hapusKasForm" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

@can('kas.sinkron_saldo.action')
{{-- Modal Sinkron Saldo — Cleanup Tool B3 --}}
<div class="modal fade" id="modalSinkronSaldo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-arrow-repeat me-2"></i>Sinkron Saldo — <span id="sinkronNamaKas"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="sinkronLoading" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                    <div class="text-muted small mt-2">Menghitung dari riwayat transaksi...</div>
                </div>
                <div id="sinkronPreview" style="display:none">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Saldo Sekarang (di sistem)</span>
                        <span id="sinkronSaldoLama" class="fw-semibold"></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Seharusnya (dihitung dari riwayat)</span>
                        <span id="sinkronSaldoBaru" class="fw-bold text-primary"></span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold">Selisih</span>
                        <span id="sinkronSelisih" class="fw-bold"></span>
                    </div>
                    <div id="sinkronTanpaSelisih" class="alert alert-success py-1 px-2 mt-3 mb-0 small" style="display:none">
                        <i class="bi bi-check-circle me-1"></i>Tidak ada selisih — saldo sudah sinkron.
                    </div>
                    <div id="sinkronAdaSelisih" class="alert alert-warning py-1 px-2 mt-3 mb-0 small" style="display:none">
                        <i class="bi bi-exclamation-triangle me-1"></i>Klik "Sinkronkan" untuk update saldo ke nilai hasil hitungan riwayat.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <form id="sinkronSaldoForm" method="POST">
                    @csrf
                    <button type="submit" id="sinkronSubmitBtn" class="btn btn-warning" style="display:none">
                        <i class="bi bi-arrow-repeat me-1"></i>Sinkronkan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endcan
@endsection

@push('scripts')
<script>
// Auto-buka modal Tambah/Edit Kas kalau ada error validasi tersisa dari
// submit sebelumnya — sebelum fix ini, form gagal validasi cuma redirect
// diam-diam (modal ketutup, error tidak pernah kelihatan). _form_source
// dikirim tiap form (Tambah/Edit) supaya kita tahu modal MANA yang perlu
// dibuka ulang (khusus Edit: ada 1 modal per kas, harus tepat sasaran).
@if($errors->any() && old('_form_source'))
document.addEventListener('DOMContentLoaded', function() {
    var source = @json(old('_form_source'));
    var modalId = source === 'tambah' ? 'modalTambahKas' : ('modalEditKas' + source.replace('edit_', ''));
    var el = document.getElementById(modalId);
    if (el) {
        new bootstrap.Modal(el).show();
    }
});
@endif

function confirmHapusKas(id, nama) {
    document.getElementById('hapusKasNama').textContent = nama;
    document.getElementById('hapusKasForm').action = '/keuangan/kas/' + id;
    new bootstrap.Modal(document.getElementById('hapusKasModal')).show();
}

@can('kas.sinkron_saldo.action')
function bukaSinkronSaldo(id, nama) {
    document.getElementById('sinkronNamaKas').textContent = nama;
    document.getElementById('sinkronSaldoForm').action = '/keuangan/kas/' + id + '/sinkron-saldo';

    const loading = document.getElementById('sinkronLoading');
    const preview = document.getElementById('sinkronPreview');
    const submitBtn = document.getElementById('sinkronSubmitBtn');
    const tanpaSelisih = document.getElementById('sinkronTanpaSelisih');
    const adaSelisih = document.getElementById('sinkronAdaSelisih');

    loading.style.display = '';
    preview.style.display = 'none';
    submitBtn.style.display = 'none';

    new bootstrap.Modal(document.getElementById('modalSinkronSaldo')).show();

    const fmt = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n));

    fetch('/keuangan/kas/' + id + '/preview-sinkron', { headers: { 'Accept': 'application/json' } })
        .then(res => res.json())
        .then(json => {
            loading.style.display = 'none';
            preview.style.display = '';

            document.getElementById('sinkronSaldoLama').textContent = fmt(json.saldo_sekarang);
            document.getElementById('sinkronSaldoBaru').textContent = fmt(json.expected_saldo);

            const selisihEl = document.getElementById('sinkronSelisih');
            const adaBeda = Math.abs(json.selisih) >= 1;
            selisihEl.textContent = (json.selisih < 0 ? '-' : '') + fmt(Math.abs(json.selisih));
            selisihEl.className = 'fw-bold ' + (adaBeda ? 'text-danger' : 'text-success');

            tanpaSelisih.style.display = adaBeda ? 'none' : '';
            adaSelisih.style.display = adaBeda ? '' : 'none';
            submitBtn.style.display = adaBeda ? '' : 'none';
        })
        .catch(() => {
            loading.style.display = 'none';
        });
}
@endcan
</script>
@endpush
