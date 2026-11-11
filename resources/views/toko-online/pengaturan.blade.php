@extends('layouts.app')

@section('title', 'Pengaturan Toko Online')

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.'); @endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-sliders me-2 text-success"></i>Pengaturan Toko Online</h5>
    <x-panduan-button slug="pengaturan-toko-online" />
</div>

@if($errors->any())
<div class="alert alert-danger small">{{ $errors->first() }}</div>
@endif

<div class="row g-3">
<div class="col-12 col-xl-5">
    <div class="card mb-3"><div class="card-header fw-semibold small">Waktu otomatis</div><div class="card-body">
        <form method="POST" action="{{ route('toko-online.pengaturan.umum') }}">
            @csrf @method('PUT')
            <label class="form-label small fw-semibold">Diterima otomatis setelah (hari) <x-tooltip key="toko_online.terima_otomatis" /></label>
            <input type="number" name="hari_terima_otomatis" min="0" max="60" class="form-control form-control-sm mb-2" value="{{ old('hari_terima_otomatis', $set->hari_terima_otomatis) }}" required>
            <label class="form-label small fw-semibold">Batas waktu bayar transfer (jam) <x-tooltip key="toko_online.batas_bayar" /></label>
            <input type="number" name="jam_batas_bayar" min="0" max="720" class="form-control form-control-sm mb-2" value="{{ old('jam_batas_bayar', $set->jam_batas_bayar) }}" required>
            <label class="form-label small fw-semibold">Batas pengajuan retur (hari setelah diterima) <x-tooltip key="toko_online.batas_retur" /></label>
            <input type="number" name="hari_batas_retur" min="0" max="90" class="form-control form-control-sm mb-2" value="{{ old('hari_batas_retur', $set->hari_batas_retur) }}" required>
            <div class="form-text mb-2">0 = tidak pernah otomatis / tanpa batas.</div>
            <button class="btn btn-primary btn-sm">Simpan</button>
        </form>
    </div></div>

    <div class="card mb-3"><div class="card-header fw-semibold small">Rekening tujuan transfer <x-tooltip key="toko_online.rekening" /></div>
        <ul class="list-group list-group-flush small">
        @forelse($rekening as $r)
            <li class="list-group-item d-flex justify-content-between align-items-center gap-2 {{ $r->aktif ? '' : 'text-muted' }}">
                <span><strong>{{ $r->bank }} {{ $r->nomor }}</strong> a.n. {{ $r->atas_nama }}<br><span class="text-muted">{{ $r->cabang?->nama_cabang }}{{ $r->aktif ? '' : ' · disembunyikan' }}</span></span>
                <form method="POST" action="{{ route('toko-online.pengaturan.rekening.aktif', $r) }}">@csrf
                    <button class="btn btn-sm {{ $r->aktif ? 'btn-outline-secondary' : 'btn-outline-success' }}">{{ $r->aktif ? 'Sembunyikan' : 'Tampilkan' }}</button></form>
            </li>
        @empty
            <li class="list-group-item text-danger">Belum ada rekening. Member belum bisa memilih bayar transfer.</li>
        @endforelse
        </ul>
        <div class="card-body border-top">
            <form method="POST" action="{{ route('toko-online.pengaturan.rekening') }}" class="row g-2">
                @csrf
                <div class="col-12"><select name="cabang_id" class="form-select form-select-sm" required>
                    @foreach($gudang as $g)<option value="{{ $g->id }}">{{ $g->nama_cabang }}</option>@endforeach
                </select></div>
                <div class="col-4"><input type="text" name="bank" maxlength="50" class="form-control form-control-sm" placeholder="Bank" value="{{ old('bank') }}" required></div>
                <div class="col-8"><input type="text" name="nomor" maxlength="40" inputmode="numeric" class="form-control form-control-sm" placeholder="Nomor rekening" value="{{ old('nomor') }}" required></div>
                <div class="col-12"><input type="text" name="atas_nama" maxlength="100" class="form-control form-control-sm" placeholder="Atas nama" value="{{ old('atas_nama') }}" required></div>
                <div class="col-12"><button class="btn btn-primary btn-sm">Tambah rekening</button></div>
            </form>
        </div>
    </div>
</div>

<div class="col-12 col-xl-7">
    <div class="card mb-3"><div class="card-header fw-semibold small">Sumber barang (belanja ke gudang / upline) <x-tooltip key="toko_online.sumber" /></div>
        <div class="card-body small pb-0">
            <p class="text-muted mb-2">Yang berlaku: <strong>aturan akun</strong> → <strong>aturan jabatan</strong> → <strong>semua member</strong>. Tanpa aturan = belanja ke <strong>gudang saja</strong>. Belanja ke upline memakai stok upline (bertambah saat pesanan upline diterima).</p>
        </div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
            <thead><tr><th>Untuk</th><th class="text-center">Gudang</th><th class="text-center">Upline</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($aturanSumber as $a)
            <tr class="{{ $a->aktif ? '' : 'text-muted' }}">
                <td>@if($a->cakupan === 'semua')Semua member@elseif($a->cakupan === 'jabatan')Jabatan {{ $jabatan[$a->jabatan_id]['nama'] ?? '#' . $a->jabatan_id }}@else{{ $a->akun?->label ?? 'Akun #' . $a->member_id }}@endif
                    @if($a->catatan)<div class="text-muted">{{ $a->catatan }}</div>@endif</td>
                <td class="text-center">{!! $a->boleh_gudang ? '<i class="bi bi-check-lg text-success"></i>' : '<i class="bi bi-x-lg text-danger"></i>' !!}</td>
                <td class="text-center">{!! $a->boleh_upline ? '<i class="bi bi-check-lg text-success"></i>' : '<i class="bi bi-x-lg text-danger"></i>' !!}</td>
                <td><span class="badge bg-{{ $a->aktif ? 'success' : 'secondary' }}">{{ $a->aktif ? 'Aktif' : 'Nonaktif' }}</span></td>
                <td class="text-end">@if($a->aktif)<form method="POST" action="{{ route('toko-online.pengaturan.sumber.nonaktif', $a) }}" onsubmit="return confirm('Nonaktifkan aturan ini?')">@csrf<button class="btn btn-outline-secondary btn-sm">Nonaktifkan</button></form>@endif</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted py-3">Belum ada aturan — semua member belanja ke gudang saja.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="card-body border-top">
            <form method="POST" action="{{ route('toko-online.pengaturan.sumber') }}" class="row g-2 form-sasaran" data-awalan="S">
                @csrf
                <div class="col-12 col-md-4"><label class="form-label small fw-semibold">Untuk</label>
                    <select name="cakupan" class="form-select form-select-sm pilih-cakupan">
                        @foreach(\App\Models\AturanTagihan::CAKUPAN as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select></div>
                <div class="col-12 col-md-8 d-none kotak-jabatan"><label class="form-label small fw-semibold">Jabatan</label>
                    <select name="jabatan_id" class="form-select form-select-sm">
                        <option value="">— pilih jabatan —</option>
                        @foreach($jabatan as $j)<option value="{{ $j['id'] }}">{{ $j['nama'] }}</option>@endforeach
                    </select></div>
                <div class="col-12 col-md-8 d-none position-relative kotak-akun"><label class="form-label small fw-semibold">Akun</label>
                    <input type="hidden" name="member_id" class="isi-member">
                    <input type="text" class="form-control form-control-sm cari-akun" placeholder="Cari nama / No ID / BP-xxxxx" autocomplete="off">
                    <div class="list-group position-absolute w-100 shadow-sm hasil-akun" style="z-index:5"></div>
                    <div class="form-text akun-terpilih"></div></div>
                <div class="col-12 d-flex flex-wrap gap-3">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="boleh_gudang" value="1" id="bolehGudang" checked><label class="form-check-label small" for="bolehGudang">Boleh ke gudang</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="boleh_upline" value="1" id="bolehUpline" checked><label class="form-check-label small" for="bolehUpline">Boleh ke upline</label></div>
                </div>
                <div class="col-12 col-md-8"><input type="text" name="catatan" maxlength="255" class="form-control form-control-sm" placeholder="Catatan (opsional)"></div>
                <div class="col-12 col-md-4"><button class="btn btn-primary btn-sm w-100">Simpan aturan</button></div>
            </form>
        </div>
    </div>

    <div class="card mb-3"><div class="card-header fw-semibold small">Bayar dengan tagihan (tempo) <x-tooltip key="toko_online.tagihan" /></div>
        <div class="card-body small pb-0">
            <p class="text-muted mb-2">Yang berlaku untuk seorang member: <strong>aturan akun</strong> → <strong>aturan jabatan</strong> → <strong>semua member</strong>. Tanpa aturan = hanya bisa transfer. Batas = total tagihan belum lunas maksimal.</p>
            @if($galatJabatan)<div class="alert alert-warning py-2">Daftar jabatan belum bisa diambil: {{ $galatJabatan }}</div>@endif
        </div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
            <thead><tr><th>Untuk</th><th class="text-end">Batas</th><th class="text-end">Tempo</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($aturan as $a)
            <tr class="{{ $a->aktif ? '' : 'text-muted' }}">
                <td>@if($a->cakupan === 'semua')Semua member@elseif($a->cakupan === 'jabatan')Jabatan {{ $jabatan[$a->jabatan_id]['nama'] ?? '#' . $a->jabatan_id }}@else{{ $a->akun?->label ?? 'Akun #' . $a->member_id }}@endif
                    @if($a->catatan)<div class="text-muted">{{ $a->catatan }}</div>@endif
                    @if($a->cakupan === 'akun' && $a->aktif)<div class="text-muted">Terpakai {{ $rp($terpakai[$a->id] ?? 0) }}</div>@endif</td>
                <td class="text-end text-nowrap">{{ $rp($a->batas_nominal) }}</td>
                <td class="text-end text-nowrap">{{ $a->tempo_hari }} hari</td>
                <td><span class="badge bg-{{ $a->aktif ? 'success' : 'secondary' }}">{{ $a->aktif ? 'Aktif' : 'Nonaktif' }}</span></td>
                <td class="text-end">@if($a->aktif)<form method="POST" action="{{ route('toko-online.pengaturan.tagihan.nonaktif', $a) }}" onsubmit="return confirm('Nonaktifkan aturan ini?')">@csrf<button class="btn btn-outline-secondary btn-sm">Nonaktifkan</button></form>@endif</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted py-3">Belum ada aturan — semua member bayar dengan transfer.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="card-body border-top">
            <form method="POST" action="{{ route('toko-online.pengaturan.tagihan') }}" class="row g-2 form-sasaran" id="formTagihan">
                @csrf
                <div class="col-12 col-md-4"><label class="form-label small fw-semibold">Untuk</label>
                    <select name="cakupan" class="form-select form-select-sm pilih-cakupan">
                        @foreach(\App\Models\AturanTagihan::CAKUPAN as $k => $v)<option value="{{ $k }}" @selected(old('cakupan') === $k)>{{ $v }}</option>@endforeach
                    </select></div>
                <div class="col-12 col-md-8 d-none kotak-jabatan"><label class="form-label small fw-semibold">Jabatan</label>
                    <select name="jabatan_id" class="form-select form-select-sm">
                        <option value="">— pilih jabatan —</option>
                        @foreach($jabatan as $j)<option value="{{ $j['id'] }}" @selected(old('jabatan_id') == $j['id'])>{{ $j['nama'] }}</option>@endforeach
                    </select></div>
                <div class="col-12 col-md-8 d-none position-relative kotak-akun"><label class="form-label small fw-semibold">Akun</label>
                    <input type="hidden" name="member_id" class="isi-member" value="{{ old('member_id') }}">
                    <input type="text" class="form-control form-control-sm cari-akun" placeholder="Cari nama / No ID / BP-xxxxx" autocomplete="off">
                    <div class="list-group position-absolute w-100 shadow-sm hasil-akun" style="z-index:5"></div>
                    <div class="form-text akun-terpilih"></div></div>
                <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Batas nominal (Rp)</label>
                    <input type="number" name="batas_nominal" min="1" step="1" class="form-control form-control-sm" value="{{ old('batas_nominal') }}" required></div>
                <div class="col-6 col-md-3"><label class="form-label small fw-semibold">Tempo (hari)</label>
                    <input type="number" name="tempo_hari" min="1" max="180" class="form-control form-control-sm" value="{{ old('tempo_hari', 14) }}" required></div>
                <div class="col-12 col-md-5"><label class="form-label small fw-semibold">Catatan</label>
                    <input type="text" name="catatan" maxlength="255" class="form-control form-control-sm" value="{{ old('catatan') }}"></div>
                <div class="col-12"><button class="btn btn-primary btn-sm">Simpan aturan</button>
                    <span class="form-text ms-2">Aturan aktif lama untuk sasaran yang sama otomatis dinonaktifkan.</span></div>
            </form>
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
// Formulir bersasaran (aturan sumber & aturan tagihan): Untuk = semua / jabatan / akun (akun dicari lewat aplikasi member)
(() => {
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const urlCari = @json(route('toko-online.cari-akun'));
    document.querySelectorAll('.form-sasaran').forEach((f) => {
        const q = (sel) => f.querySelector(sel);
        const tampil = () => {
            const c = q('.pilih-cakupan').value;
            q('.kotak-jabatan').classList.toggle('d-none', c !== 'jabatan');
            q('.kotak-akun').classList.toggle('d-none', c !== 'akun');
        };
        q('.pilih-cakupan').addEventListener('change', tampil);
        tampil();
        let t = null;
        q('.cari-akun').addEventListener('input', (e) => {
            clearTimeout(t);
            const kata = e.target.value.trim();
            if (kata.length < 2) { q('.hasil-akun').innerHTML = ''; return; }
            t = setTimeout(async () => {
                try {
                    const r = await fetch(urlCari + '?q=' + encodeURIComponent(kata), { headers: { Accept: 'application/json' } });
                    const j = await r.json();
                    if (!j.ok) { q('.hasil-akun').innerHTML = '<div class="list-group-item small text-danger">' + esc(j.pesan || 'Gagal mencari akun.') + '</div>'; return; }
                    q('.hasil-akun').innerHTML = j.data.length ? j.data.map((a) => '<button type="button" class="list-group-item list-group-item-action small pilih-akun" data-id="' + a.id + '" data-label="'
                        + esc((a.no_id ? a.no_id + ' · ' : '') + a.nama + ' (' + a.no_member + ')') + '"><strong>' + esc(a.no_id || '') + '</strong> ' + esc(a.nama)
                        + ' <span class="text-muted">' + esc(a.jabatan || '') + ' · ' + esc(a.no_member) + '</span></button>').join('')
                        : '<div class="list-group-item small text-muted">Tidak ada akun aktif yang cocok.</div>';
                } catch (err) { q('.hasil-akun').innerHTML = '<div class="list-group-item small text-danger">Gagal mencari akun.</div>'; }
            }, 350);
        });
        q('.hasil-akun').addEventListener('click', (e) => {
            const b = e.target.closest('.pilih-akun');
            if (!b) return;
            q('.isi-member').value = b.dataset.id;
            q('.akun-terpilih').textContent = 'Dipilih: ' + b.dataset.label;
            q('.hasil-akun').innerHTML = ''; q('.cari-akun').value = '';
        });
    });
})();
</script>
@endpush
