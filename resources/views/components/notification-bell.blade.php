@php
    $user          = auth()->user();
    $unreadCount   = $user ? $user->unreadNotifications()->count() : 0;
    $notifications = $user ? $user->notifications()->latest()->take(10)->get() : collect();

    $colorMap = [
        'danger'  => ['bg' => 'bg-danger',  'text' => 'text-danger',  'light' => 'bg-danger-subtle'],
        'warning' => ['bg' => 'bg-warning', 'text' => 'text-warning', 'light' => 'bg-warning-subtle'],
        'success' => ['bg' => 'bg-success', 'text' => 'text-success', 'light' => 'bg-success-subtle'],
        'info'    => ['bg' => 'bg-info',    'text' => 'text-info',    'light' => 'bg-info-subtle'],
    ];
@endphp

<div class="dropdown" id="notif-bell-dropdown">
    {{-- Tombol Lonceng --}}
    <button class="btn btn-sm btn-light rounded-circle position-relative notif-bell-btn"
            style="width:36px;height:36px;padding:0"
            data-bs-toggle="dropdown" data-bs-auto-close="outside"
            aria-expanded="false" title="Notifikasi">
        <i class="bi bi-bell" style="font-size:0.9rem"></i>
        @if($unreadCount > 0)
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
              id="notif-badge"
              style="font-size:0.6rem;min-width:16px;height:16px;display:flex;align-items:center;justify-content:center;transform:translate(-60%,-30%)!important">
            {{ $unreadCount > 99 ? '99+' : $unreadCount }}
        </span>
        @else
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"
              id="notif-badge"
              style="font-size:0.6rem;min-width:16px;height:16px;display:flex;align-items:center;justify-content:center;transform:translate(-60%,-30%)!important">
        </span>
        @endif
    </button>

    {{-- Dropdown Panel --}}
    <div class="dropdown-menu dropdown-menu-end shadow p-0"
         style="width:360px;max-width:calc(100vw - 1rem);border-radius:12px;overflow:hidden">

        {{-- Header (diupdate via JS) --}}
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom bg-white" id="notif-header">
            <div class="d-flex align-items-center">
                <span class="fw-bold" style="font-size:0.9rem">Notifikasi</span>
                <span id="notif-header-badge" class="badge bg-danger ms-1" style="font-size:0.65rem;{{ $unreadCount > 0 ? '' : 'display:none' }}">
                    {{ $unreadCount }} baru
                </span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" id="notif-sound-toggle"
                        class="btn btn-link btn-sm p-0 text-decoration-none"
                        style="font-size:0.95rem;line-height:1;color:#94a3b8;border:none;background:none"
                        title="Aktifkan Suara Notifikasi">
                    🔕
                </button>
                <form method="POST" action="{{ route('notifikasi.read-all') }}" class="mb-0"
                      id="notif-read-all-form" style="{{ $unreadCount > 0 ? '' : 'display:none' }}">
                    @csrf
                    <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none"
                            style="font-size:0.75rem;color:#3b82f6">
                        Tandai Semua Dibaca
                    </button>
                </form>
            </div>
        </div>

        {{-- Daftar Notifikasi (diupdate via JS polling) --}}
        <div id="notif-list" style="max-height:380px;overflow-y:auto">
            @forelse($notifications as $notif)
            @php
                $data   = is_array($notif->data) ? $notif->data : json_decode($notif->data, true);
                $color  = $data['color'] ?? 'info';
                $colors = $colorMap[$color] ?? $colorMap['info'];
                $isRead = $notif->read_at !== null;
                $diff   = $notif->created_at->diffForHumans();
                $goUrl  = route('notifikasi.go', $notif->id);
            @endphp
            <a href="{{ $goUrl }}"
               class="notif-item d-flex gap-2 px-3 py-2 border-bottom text-decoration-none {{ $isRead ? '' : 'bg-primary-subtle' }}"
               style="transition:background 0.15s;color:inherit">
                <div class="flex-shrink-0 d-flex align-items-start pt-1">
                    <div class="{{ $colors['light'] }} {{ $colors['text'] }} rounded-circle d-flex align-items-center justify-content-center"
                         style="width:32px;height:32px;font-size:0.85rem">
                        <i class="bi {{ $data['icon'] ?? 'bi-bell' }}"></i>
                    </div>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex align-items-start justify-content-between gap-1">
                        <p class="mb-0 {{ $isRead ? '' : 'fw-semibold' }}"
                           style="font-size:0.8rem;line-height:1.3;color:#1e293b">
                            {{ $data['title'] ?? 'Notifikasi' }}
                        </p>
                        @if(!$isRead)
                        <span style="width:7px;height:7px;margin-top:4px;border-radius:50%;display:inline-block;background:#3b82f6;flex-shrink:0"></span>
                        @endif
                    </div>
                    <p class="mb-0 text-secondary" style="font-size:0.73rem;line-height:1.4;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">
                        {{ $data['message'] ?? '' }}
                    </p>
                    <p class="mb-0 mt-1" style="font-size:0.7rem;color:#94a3b8">
                        <i class="bi bi-clock me-1"></i>{{ $diff }}
                        @if(isset($data['kategori']))
                        <span class="ms-2 badge {{ $colors['light'] }} {{ $colors['text'] }}" style="font-size:0.6rem">
                            {{ ucfirst($data['kategori']) }}
                        </span>
                        @endif
                    </p>
                </div>
            </a>
            @empty
            <div class="text-center py-5 text-secondary">
                <i class="bi bi-bell-slash fs-2 d-block mb-2 opacity-50"></i>
                <small>Belum ada notifikasi</small>
            </div>
            @endforelse
        </div>

        {{-- Footer --}}
        <div class="px-3 py-2 bg-white border-top text-center">
            <a href="{{ route('notifikasi.index') }}"
               class="text-decoration-none" style="font-size:0.78rem;color:#3b82f6;font-weight:500">
                Lihat Semua Notifikasi
                <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>

@php
    $bellPusherKey     = config('broadcasting.connections.pusher.key');
    $bellPusherCluster = config('broadcasting.connections.pusher.options.cluster', 'ap1');
@endphp
@once
@push('scripts')
@if($bellPusherKey)
<script src="https://js.pusher.com/8.4.0-rc2/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js"></script>
@endif
<script>
(function () {
    // ── Config ──
    const PUSHER_KEY     = @json($bellPusherKey ?? '');
    const PUSHER_CLUSTER = @json($bellPusherCluster ?? 'ap1');
    const AUTH_USER_ID   = {{ auth()->id() ?? 'null' }};

    // ── Sound state (opt-in, default OFF) ──
    let soundEnabled    = localStorage.getItem('notif_sound_enabled') === '1';
    let audioCtx        = null;
    let prevUnreadCount = {{ $unreadCount }};  // start dari count saat render

    const colorClasses = {
        danger:  { text: 'text-danger',  light: 'bg-danger-subtle'  },
        warning: { text: 'text-warning', light: 'bg-warning-subtle' },
        success: { text: 'text-success', light: 'bg-success-subtle' },
        info:    { text: 'text-info',    light: 'bg-info-subtle'    },
    };

    const kategoriLabel = {
        stok: 'Stok', pembelian: 'Pembelian', penjualan: 'Penjualan',
        hr: 'HR', evaluasi: 'Evaluasi', aset: 'Aset', keuangan: 'Keuangan',
    };

    function renderItem(n) {
        const c = colorClasses[n.color] || colorClasses.info;
        const dot = n.is_read ? '' :
            `<span style="width:7px;height:7px;margin-top:4px;border-radius:50%;display:inline-block;background:#3b82f6;flex-shrink:0"></span>`;
        const katBadge = n.kategori
            ? `<span class="ms-2 badge ${c.light} ${c.text}" style="font-size:0.6rem">${kategoriLabel[n.kategori] || n.kategori}</span>`
            : '';
        return `
        <a href="${n.go_url}"
           class="notif-item d-flex gap-2 px-3 py-2 border-bottom text-decoration-none ${n.is_read ? '' : 'bg-primary-subtle'}"
           style="transition:background 0.15s;color:inherit">
            <div class="flex-shrink-0 d-flex align-items-start pt-1">
                <div class="${c.light} ${c.text} rounded-circle d-flex align-items-center justify-content-center"
                     style="width:32px;height:32px;font-size:0.85rem">
                    <i class="bi ${n.icon}"></i>
                </div>
            </div>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-items-start justify-content-between gap-1">
                    <p class="mb-0 ${n.is_read ? '' : 'fw-semibold'}"
                       style="font-size:0.8rem;line-height:1.3;color:#1e293b">${n.title}</p>
                    ${dot}
                </div>
                <p class="mb-0 text-secondary"
                   style="font-size:0.73rem;line-height:1.4;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">
                    ${n.message}
                </p>
                <p class="mb-0 mt-1" style="font-size:0.7rem;color:#94a3b8">
                    <i class="bi bi-clock me-1"></i>${n.diff}${katBadge}
                </p>
            </div>
        </a>`;
    }

    function updateBell(data) {
        const count        = data.unread_count || 0;
        const badge        = document.getElementById('notif-badge');
        const headerBadge  = document.getElementById('notif-header-badge');
        const readAllForm  = document.getElementById('notif-read-all-form');
        const list         = document.getElementById('notif-list');

        // Update badge lonceng
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        }

        // Update badge header dropdown
        if (headerBadge) {
            if (count > 0) {
                headerBadge.textContent = count + ' baru';
                headerBadge.style.display = '';
            } else {
                headerBadge.style.display = 'none';
            }
        }

        // Tampil/sembunyikan tombol "Tandai Semua"
        if (readAllForm) {
            readAllForm.style.display = count > 0 ? '' : 'none';
        }

        // Re-render daftar notifikasi
        if (list && data.items !== undefined) {
            if (data.items.length === 0) {
                list.innerHTML = `
                    <div class="text-center py-5 text-secondary">
                        <i class="bi bi-bell-slash fs-2 d-block mb-2 opacity-50"></i>
                        <small>Belum ada notifikasi</small>
                    </div>`;
            } else {
                list.innerHTML = data.items.map(renderItem).join('');
            }
        }

        // Tink saat ada notif baru (unread naik dari sebelumnya)
        if (count > prevUnreadCount) {
            playTink();
        }
        prevUnreadCount = count;
    }

    // ── Sound functions ──
    function playTink() {
        if (!soundEnabled) return;
        try {
            if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const osc  = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(1200, audioCtx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(900, audioCtx.currentTime + 0.15);
            gain.gain.setValueAtTime(0.18, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.25);
            osc.start(audioCtx.currentTime);
            osc.stop(audioCtx.currentTime + 0.25);
        } catch (_) {}
    }

    function updateSoundBtn() {
        const btn = document.getElementById('notif-sound-toggle');
        if (!btn) return;
        btn.textContent = soundEnabled ? '🔔' : '🔕';
        btn.title       = soundEnabled ? 'Matikan Suara Notifikasi' : 'Aktifkan Suara Notifikasi';
        btn.style.color = soundEnabled ? '#3b82f6' : '#94a3b8';
    }

    function toggleSound() {
        if (!soundEnabled && !audioCtx) {
            // Init AudioContext on first enable — must be inside user gesture handler
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        soundEnabled = !soundEnabled;
        localStorage.setItem('notif_sound_enabled', soundEnabled ? '1' : '0');
        updateSoundBtn();
        if (soundEnabled) playTink(); // demo tink langsung saat aktifkan
    }

    // Inisialisasi tombol suara
    updateSoundBtn();
    const soundBtn = document.getElementById('notif-sound-toggle');
    if (soundBtn) {
        soundBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation(); // jangan tutup dropdown Bootstrap
            toggleSound();
        });
    }

    // ── Polling ──
    const pollUrl = '{{ route('notifikasi.latest') }}';

    function poll() {
        fetch(pollUrl, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.ok ? r.json() : null)
        .then(data => { if (data) updateBell(data); })
        .catch(() => {});
    }

    // Poll pertama setelah 5 detik page load
    setTimeout(poll, 5000);

    // Polling 60 detik — Pusher adalah trigger utama, polling sebagai safety net
    setInterval(poll, 60000);

    // Poll saat lonceng diklik (fresh saat dibuka)
    const bellBtn = document.querySelector('.notif-bell-btn');
    if (bellBtn) {
        bellBtn.addEventListener('click', poll);
    }

    // ── Pusher Echo: subscribe private channel per user ──
    function initEcho() {
        if (!PUSHER_KEY || !AUTH_USER_ID || typeof Echo === 'undefined') return;

        const echo = new Echo({
            broadcaster:  'pusher',
            key:          PUSHER_KEY,
            cluster:      PUSHER_CLUSTER,
            forceTLS:     true,
            authEndpoint: '/broadcasting/auth',
        });

        echo.private('App.Models.User.' + AUTH_USER_ID)
            .listen('.bell.new', function () {
                poll();
            });
    }

    initEcho();
})();
</script>
@endpush
@endonce
