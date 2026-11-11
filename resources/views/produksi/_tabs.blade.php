{{-- Tab Riwayat Produksi / Resep Produksi --}}
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('produksi.index', 'produksi.create', 'produksi.show') ? 'active' : '' }}" href="{{ route('produksi.index') }}">
            <i class="bi bi-clock-history me-1"></i>Riwayat Produksi
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('produksi.resep.*') ? 'active' : '' }}" href="{{ route('produksi.resep.index') }}">
            <i class="bi bi-journal-text me-1"></i>Resep Produksi
        </a>
    </li>
    @can('produksi.resep.kelola')
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('produksi.konsentrasi.*') ? 'active' : '' }}" href="{{ route('produksi.konsentrasi.index') }}">
            <i class="bi bi-droplet-half me-1"></i>Konsentrasi
        </a>
    </li>
    @endcan
</ul>
