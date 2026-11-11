{{--
    4.46 — Pilihan periode bersama Laporan Produk & Kategori (menu Penjualan).
    Params: $route (nama route), $periode (App\Support\PeriodeLaporan::baca),
            $simpan (array query lain yang ikut dibawa, mis. kategori/cari/urut).
--}}
@php $simpan = array_filter($simpan ?? [], fn ($v) => filled($v)); @endphp
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 d-flex flex-wrap align-items-end gap-2">
        <div>
            <div class="small text-muted mb-1">Periode cepat</div>
            <div class="btn-group flex-wrap" role="group">
                @foreach(\App\Support\PeriodeLaporan::CEPAT as $key => $label)
                <a href="{{ route($route, ['periode' => $key] + $simpan) }}"
                   class="btn btn-sm {{ $periode['cepat'] === $key ? 'btn-dark' : 'btn-outline-dark' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <form method="GET" action="{{ route($route) }}" class="d-flex flex-wrap align-items-end gap-2 ms-lg-auto" id="formRentang">
            @foreach($simpan as $k => $v)
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach
            <div>
                <label class="small text-muted mb-1 d-block" for="rentangDari">Dari tanggal</label>
                <input type="date" id="rentangDari" name="dari" class="form-control form-control-sm" required
                       value="{{ $periode['dari']->toDateString() }}" max="{{ today()->toDateString() }}">
            </div>
            <div>
                <label class="small text-muted mb-1 d-block" for="rentangSampai">Sampai tanggal</label>
                <input type="date" id="rentangSampai" name="sampai" class="form-control form-control-sm" required
                       value="{{ $periode['sampai']->toDateString() }}" max="{{ today()->toDateString() }}">
            </div>
            <button type="submit" class="btn btn-sm" style="background:var(--brand-utama);color:var(--brand-utama-teks)">
                <i class="bi bi-search me-1"></i>Tampilkan
            </button>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const dari = document.getElementById('rentangDari');
    const sampai = document.getElementById('rentangSampai');
    if (!dari || !sampai) return;
    const sinkron = () => { sampai.min = dari.value || ''; if (sampai.value && dari.value && sampai.value < dari.value) sampai.value = dari.value; };
    dari.addEventListener('change', sinkron);
    sinkron();
})();
</script>
@endpush
