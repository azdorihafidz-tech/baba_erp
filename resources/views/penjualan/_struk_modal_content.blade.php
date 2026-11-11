{{--
    Partial: konten struk untuk ditampilkan dalam modal Bootstrap.
    Semua ID/class menggunakan prefix "struk" agar tidak konflik
    dengan Bootstrap maupun halaman induk yang sedang buka.
    JS dibungkus IIFE sehingga aman dijalankan ulang saat modal
    dibuka berkali-kali.

    Params:
      $order  (App\Models\Order) — sudah di-load items & cabang
      $copies (int, default 1)   — jumlah salinan: 1 atau 2
--}}
@php $copies = isset($copies) && in_array((int)$copies, [1, 2]) ? (int)$copies : 1; @endphp

<style>
/* ======================================================
   STRUK CONTENT — semua class dipakai di dalam .struk-wrap
   agar tidak konflik dengan Bootstrap (terutama .row)
   ====================================================== */
.struk-wrap {
    font-family: 'Courier New', monospace;
    font-size: 11px;
    width: 72mm;
    margin: 0 auto;
    padding: 4mm;
    box-sizing: border-box;
    page-break-inside: avoid; /* cegah 1 struk pecah jadi 2 halaman */
}
.struk-wrap .center { text-align: center; }
.struk-wrap .right  { text-align: right; }
.struk-wrap .bold   { font-weight: bold; }
.struk-wrap .line       { border-top: 1px dashed #000; margin: 4px 0; }
.struk-wrap .line-solid { border-top: 1px solid #000;  margin: 4px 0; }
/* Override Bootstrap .row (margin negatif) */
.struk-wrap .row   { display: flex; justify-content: space-between; margin: 0; }
.struk-wrap .row-3 { display: flex; gap: 4px; margin: 0; }
.struk-wrap .item-name     { flex: 1; }
.struk-wrap .item-qty      { width: 30px; text-align: right; }
.struk-wrap .item-price    { width: 65px; text-align: right; }
.struk-wrap .total-section { font-weight: bold; font-size: 12px; }
.struk-wrap .header-store  { font-size: 14px; font-weight: bold; }
.struk-wrap .footer-gap    { margin-bottom: 4mm; }

/* ---- Garis pemotong salinan (antar 2 struk) ---- */
.struk-cut-line {
    text-align: center;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    padding: 14px 0;
    border-top: 2px dashed #999;
    border-bottom: 2px dashed #999;
    margin: 12px auto;
    width: 72mm;
    color: #666;
    background: #fafafa;
}

/* ---- Wrapper tombol aksi (pakai class Bootstrap standar utk tombolnya) ---- */
.struk-no-print {
    font-family: system-ui, sans-serif;
    text-align: center;
    margin-top: 10mm;
    padding-bottom: 4mm;
}

/* ---- Status BT ---- */
.struk-bt-status {
    display: none; margin: 10px auto 0; max-width: 340px;
    padding: 9px 14px; border-radius: 8px; font-size: 12px;
    font-family: system-ui, sans-serif; text-align: left;
    white-space: pre-line; line-height: 1.5;
}
.struk-bt-status.info    { background:#dbeafe; color:#1d4ed8; display:block; border:1px solid #bfdbfe; }
.struk-bt-status.success { background:#dcfce7; color:#15803d; display:block; border:1px solid #bbf7d0; }
.struk-bt-status.error   { background:#fee2e2; color:#dc2626; display:block; border:1px solid #fecaca; }
.struk-bt-status.warning { background:#fef9c3; color:#854d0e; display:block; border:1px solid #fde68a; }

/* ---- Panel panduan ---- */
.struk-bt-guide {
    display: none; margin: 12px auto 0; max-width: 340px;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 12px 14px; font-family: system-ui, sans-serif;
    font-size: 12px; text-align: left; color: #334155;
}
.struk-bt-guide.show { display: block; }
.struk-bt-guide h4   { font-size: 13px; margin-bottom: 6px; color: #1e293b; }
.struk-bt-guide ul   { padding-left: 16px; margin: 4px 0 8px; }
.struk-bt-guide li   { margin-bottom: 3px; }
.struk-bt-guide .ok   { color: #16a34a; }
.struk-bt-guide .fail { color: #dc2626; }
.struk-bt-guide a    { color: #2563eb; word-break: break-all; }
.struk-bt-guide .section { margin-top: 8px; padding-top: 8px; border-top: 1px solid #e2e8f0; }
.struk-toggle-guide {
    background: none; border: none; color: #64748b; font-size: 11px;
    cursor: pointer; margin-top: 6px; text-decoration: underline;
    font-family: system-ui, sans-serif;
}

/* ---- Print ---- */
@media print {
    @page { margin: 0; size: 80mm auto; }
    .struk-wrap     { width: 80mm !important; padding: 2mm !important; }
    .struk-cut-line { width: 80mm !important; }  /* tetap muncul di print */
    .struk-no-print { display: none !important; }
}
</style>

{{-- ==================== LOOP RENDER SALINAN ==================== --}}
@for ($i = 1; $i <= $copies; $i++)

<div class="struk-wrap">

    {{-- Header --}}
    <div class="center bold header-store">{{ $brand->namaSingkat() }}</div>
    <div class="center">{{ $order->cabang?->nama_cabang ?? 'Cabang' }}</div>
    @if($order->cabang?->alamat)
    <div class="center" style="font-size:10px">{{ $order->cabang->alamat }}</div>
    @endif
    @if($order->cabang?->telepon)
    <div class="center" style="font-size:10px">Telp: {{ $order->cabang->telepon }}</div>
    @endif

    {{-- Tahap 3 D'mentai — Tipe Transaksi & Nomor Meja / Expired Frozen --}}
    @if($order->tipe_transaksi)
    <div class="center bold" style="font-size:11px">{{ $order->tipe_transaksi->label() }}@if($order->nomor_meja) — Meja {{ $order->nomor_meja }}@endif</div>
    @endif
    @if($order->tanggal_expired_frozen)
    <div class="center" style="font-size:10px">Baik s/d: {{ $order->tanggal_expired_frozen->format('d/m/Y') }}</div>
    @endif

    <div class="line-solid"></div>

    {{-- Nomor Antrian (jika ada) --}}
    @if($order->nomor_antrian)
    <div class="center" style="font-size:48px;font-weight:900;line-height:1;margin:4px 0">
        {{ str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT) }}
    </div>
    <div class="line"></div>
    @endif

    {{-- Info Order --}}
    <div class="row"><span>No Order</span><span>{{ $order->nomor_order }}</span></div>
    <div class="row"><span>Tanggal</span><span>{{ $order->tanggal_order->format('d/m/Y') }}</span></div>
    <div class="row"><span>Kasir</span><span>{{ $order->kasir?->name ?? '-' }}</span></div>
    @if($order->nama_pelanggan || $order->pelanggan)
    <div class="row"><span>Pelanggan</span><span>{{ $order->nama_pelanggan ?? $order->pelanggan?->nama_pelanggan }}</span></div>
    @endif

    <div class="line"></div>

    {{-- Items — harga per item disembunyikan kalau cabang mengizinkan &
         kasir tidak centang "Tampilkan harga per item" saat checkout
         (lihat $showItemPrice). Total tetap selalu tercetak di bawah. --}}
    @foreach($order->items as $item)
    <div class="item-name bold">{{ $item->nama_item }}</div>
    @if($showItemPrice)
    <div class="row-3">
        <span class="item-qty">{{ fmt_qty($item->qty) }} {{ $item->satuan }}</span>
        <span>x</span>
        <span class="item-price">{{ number_format($item->harga_satuan, 0, ',', '.') }}</span>
        <span class="item-price right">{{ number_format($item->total_harga, 0, ',', '.') }}</span>
    </div>
    @else
    <div class="row">
        <span class="item-qty">{{ fmt_qty($item->qty) }} {{ $item->satuan }}</span>
    </div>
    @endif
    @if($item->berat_daging)
    <div style="font-size:10px;color:#666">  Berat: {{ fmt_qty($item->berat_daging) }}kg - {{ ucfirst($item->jenis_olahan ?? '') }}</div>
    @endif
    @endforeach

    <div class="line"></div>

    {{-- Subtotal & Total --}}
    <div class="row"><span>Subtotal</span><span>Rp {{ number_format($order->total_harga, 0, ',', '.') }}</span></div>
    @if($order->diskon > 0)
    <div class="row"><span>Diskon</span><span>-Rp {{ number_format($order->diskon, 0, ',', '.') }}</span></div>
    @endif

    <div class="line-solid"></div>

    <div class="row total-section">
        <span>TOTAL</span>
        <span>Rp {{ number_format($order->total_bayar, 0, ',', '.') }}</span>
    </div>

    <div class="line"></div>

    <div class="row"><span>Bayar ({{ $order->tipe_pembayaran->label() }})</span><span>Rp {{ number_format($order->jumlah_bayar, 0, ',', '.') }}</span></div>
    @if($order->kembalian > 0)
    <div class="row bold"><span>Kembalian</span><span>Rp {{ number_format($order->kembalian, 0, ',', '.') }}</span></div>
    @endif

    <div class="line"></div>

    {{-- Footer --}}
    @php
        $footerLines = ($order->cabang?->footer_struk && trim($order->cabang->footer_struk) !== '')
            ? explode("\n", $order->cabang->footer_struk)
            : $brand->footerStrukBaris();
    @endphp
    <div class="center footer-gap" style="font-size:10px">
        {!! nl2br(e(($order->cabang?->footer_struk && trim($order->cabang->footer_struk) !== '')
            ? $order->cabang->footer_struk
            : implode("\n", $brand->footerStrukBaris()))) !!}
    </div>

</div>{{-- /.struk-wrap --}}

{{-- Garis pemotong antar salinan (hanya di antara, bukan sesudah yang terakhir) --}}
@if ($i < $copies)
<div class="struk-cut-line">
    <span>✂ - - - - - - - - - POTONG - - - - - - - - - ✂</span>
</div>
@endif

@endfor {{-- /.loop salinan --}}

{{-- ==================== TOMBOL AKSI (di luar loop, tidak ikut cetak) ==================== --}}
<div class="struk-no-print">

    {{-- 4.44 Poin 1 — aplikasi kasir Android (APK): cetak langsung ke printer struk yang diatur
         di aplikasi. Disembunyikan di browser biasa; ditampilkan script di bawah kalau window.KDApk ada. --}}
    <div class="d-grid gap-2 mb-3" id="strukApkGroup" style="display:none">
        <button class="btn btn-primary btn-lg" id="strukBtnApk" onclick="window.strukCetakApk()">🖨️ Cetak Struk</button>
        <button class="btn btn-sm btn-outline-secondary" onclick="window.KDApk && window.KDApk.bukaPengaturan()">⚙️ Pengaturan Printer Aplikasi</button>
    </div>

    {{-- Grup 1: PRIMARY — cetak Bluetooth --}}
    <div class="d-grid gap-2 mb-3" id="strukWebBtGroup">
        {{-- Cetak ke printer terakhir — muncul kalau ada printer tersimpan --}}
        <button class="btn btn-primary btn-lg" id="strukBtnLastPrinter"
                onclick="window.strukCetakBluetooth(true)" style="display:none">
        </button>
        {{-- Pilih Printer Bluetooth — muncul jika browser support, diinit JS --}}
        <button class="btn btn-outline-primary" id="strukBtnBluetooth"
                onclick="window.strukCetakBluetooth()" style="display:none">
            📶 Pilih Printer Bluetooth
        </button>
    </div>

    {{-- Grup 2: FINISH --}}
    <div class="d-flex gap-2 mb-3">
        {{-- Selesai: tutup modal + reset POS (kalau dipanggil dari halaman POS) --}}
        <button class="btn btn-success btn-lg flex-fill" onclick="window.closeStrukModal()">
            ✅ Selesai
        </button>
        {{-- Lihat Detail Order --}}
        <a class="btn btn-outline-secondary flex-fill" href="{{ route('penjualan.show', $order) }}">
            👁️ Lihat Detail
        </a>
    </div>

    {{-- 4.44 Poin 2 — Label Produk (stiker), dicetak terpisah dari struk --}}
    @if(($jumlahLabelCup ?? 0) > 0)
    <div class="d-grid mb-3">
        <button class="btn btn-warning" type="button"
                onclick="window.openCupLabel && window.openCupLabel('{{ route('label-cup.order', $order) }}')">
            🏷️ Cetak Label ({{ $jumlahLabelCup }} stiker)
        </button>
    </div>
    @endif

    {{-- Grup 2b: OPSIONAL — Buat Klaim Loyalty (event-based), cuma tampil
         kalau pelanggan terdaftar & ada program event_based aktif --}}
    @if($adaEventBasedAktif ?? false)
    <div class="d-grid mb-3">
        <a class="btn btn-outline-info" href="{{ route('loyalty-klaim.create', ['pelanggan_id' => $order->pelanggan_id, 'order_id' => $order->id]) }}">
            📣 Buat Klaim Loyalty untuk Order Ini
        </a>
    </div>
    @endif

    {{-- Grup 3: UTILITY (kecil) --}}
    <div class="d-flex justify-content-center gap-3 mb-2">
        {{-- Lupakan printer — muncul kalau ada printer tersimpan --}}
        <button class="btn btn-sm btn-outline-danger" id="strukBtnForgetPrinter"
                onclick="window.strukForgetPrinter()" style="display:none">
            🗑️ Lupakan Printer
        </button>
        {{-- Browser Print (mencetak semua salinan sekaligus) --}}
        <button class="btn btn-sm btn-outline-secondary" id="strukBtnBrowserPrint" onclick="window.print()">
            🖨️ Cetak Browser
        </button>
    </div>

    <hr class="my-2">

    <div class="struk-bt-status" id="strukBtStatus"></div>

    <button class="struk-toggle-guide" id="strukToggleGuide"
            onclick="window.strukToggleGuide()" style="display:none">
        ℹ️ Lihat panduan &amp; troubleshooting
    </button>

    <div class="struk-bt-guide" id="strukBtGuide">
        <h4>📋 Syarat Cetak Bluetooth (Web Bluetooth API)</h4>
        <ul>
            <li id="strukChkBrowser">🔄 Browser: Chrome atau Edge (≥ versi 56)</li>
            <li id="strukChkHttps">🔄 Protokol: HTTPS atau localhost</li>
            <li id="strukChkBt">🔄 Bluetooth komputer/HP aktif</li>
        </ul>
        <div class="section">
            <strong>Jika tetap tidak bisa di Chrome:</strong>
            <ul>
                <li>Buka <a href="chrome://flags/#enable-experimental-web-platform-features" target="_blank">chrome://flags/#enable-experimental-web-platform-features</a></li>
                <li>Set ke <strong>Enabled</strong>, lalu Relaunch</li>
            </ul>
        </div>
        <div class="section">
            <strong>Langkah troubleshooting:</strong>
            <ul>
                <li>Pastikan printer sudah di-<em>pair</em> di pengaturan Bluetooth OS</li>
                <li>Matikan &amp; nyalakan ulang printer</li>
                <li>Coba close tab lain yang mungkin sedang connect ke printer</li>
                <li>Jika akses via HTTP, gunakan <code>localhost</code> atau aktifkan HTTPS</li>
            </ul>
        </div>
    </div>
</div>

@php
// Untuk buffer ESC/POS (buildOneCopy di JS): qty/harga/total dikirim
// sebagai angka mentah supaya JS bisa hitung lebar baris (adaptive
// layout) dan format ulang (kg/ons/gram) sendiri.
$_items = $order->items->map(function($item) {
    return [
        'nama'   => $item->nama_item,
        'qty'    => (float) $item->qty,
        'satuan' => $item->satuan,
        'harga'  => (float) $item->harga_satuan,
        'total'  => (float) $item->total_harga,
    ];
})->values()->toArray();

// 1 order = 1 jenis olahan (dikonfirmasi user) — ambil dari item jasa
// giling pertama yang punya jenis_olahan, tampil sekali di header.
$_jasaItem = $order->items->first(fn($i) => !empty($i->jenis_olahan));
$_jenisOlahanLabel = $_jasaItem ? ucwords(str_replace('_', ' ', $_jasaItem->jenis_olahan)) : null;

$_footerLines = ($order->cabang?->footer_struk && trim($order->cabang->footer_struk) !== '')
    ? array_values(array_filter(explode("\n", $order->cabang->footer_struk), fn($l) => trim($l) !== ''))
    : $brand->footerStrukBaris();

$_struData = [
    'toko'         => $brand->namaSingkat(),
    'cabang'       => $order->cabang?->nama_cabang ?? '',
    'alamat'       => $order->cabang?->alamat ?? '',
    'telepon'      => $order->cabang?->telepon ?? '',
    'nomor'        => $order->nomor_order,
    'tanggal'      => $order->tanggal_order->format('d/m/Y'),
    'kasir'        => $order->kasir?->name ?? '-',
    'pelanggan'    => $order->nama_pelanggan ?? $order->pelanggan?->nama_pelanggan ?? '',
    'jenis_olahan' => $_jenisOlahanLabel,
    'items'        => $_items,
    'subtotal'     => number_format($order->total_harga, 0, ',', '.'),
    'diskon'       => $order->diskon > 0 ? number_format($order->diskon, 0, ',', '.') : '',
    'total'        => number_format($order->total_bayar, 0, ',', '.'),
    'tipe_bayar'   => $order->tipe_pembayaran->label(),
    'metode'       => $order->tipe_pembayaran->value,
    'bayar'        => number_format($order->jumlah_bayar, 0, ',', '.'),
    'kembalian'    => $order->kembalian > 0 ? number_format($order->kembalian, 0, ',', '.') : '',
    'antrian'      => $order->nomor_antrian ? str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT) : null,
    'copies'       => $copies,
    'footer_lines' => $_footerLines,
    'show_harga'   => $showItemPrice,
];
@endphp

<script>
// ============================================================
//  STRUK MODAL — ESC/POS Bluetooth + Browser Print
//  Dibungkus IIFE: aman dijalankan ulang tiap modal dibuka.
//  Semua fungsi di-expose via window.struk* untuk onclick.
//  copies: jumlah salinan. buildReceipt() mengulangi ESC/POS
//          sebanyak copies agar 1 BT job = n salinan.
// ============================================================
(function () {
    var STRUK            = @json($_struData);
    var COLS             = 32;
    var LAST_PRINTER_KEY = 'btLastPrinterName';
    var BT_SERVICE_UUIDS = [
        '0000ae30-0000-1000-8000-00805f9b34fb',
        '0000ae3a-0000-1000-8000-00805f9b34fb',
        '000018f0-0000-1000-8000-00805f9b34fb',
        '49535343-fe7d-4ae5-8fa9-9fafd205e455',
        '0000ff00-0000-1000-8000-00805f9b34fb',
        '0000fee7-0000-1000-8000-00805f9b34fb',
        'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
        '6e400001-b5a3-f393-e0a9-e50e24dcca9e',
    ];

    function el(id) { return document.getElementById(id); }

    // ---- Status bar ----
    function btStatus(msg, type) {
        var s = el('strukBtStatus');
        if (!s) return;
        s.textContent = msg;
        s.className   = 'struk-bt-status ' + type;
    }

    // ---- Panel panduan ----
    function showGuide(show) {
        var g = el('strukBtGuide');
        var t = el('strukToggleGuide');
        if (g) g.classList.toggle('show', show);
        if (t) {
            t.style.display = '';
            t.textContent   = show ? '▲ Sembunyikan panduan' : 'ℹ️ Lihat panduan & troubleshooting';
        }
    }

    function toggleGuide() {
        var g = el('strukBtGuide');
        if (g) showGuide(!g.classList.contains('show'));
    }

    // ---- Init UI Bluetooth ----
    function browserName() {
        var ua = navigator.userAgent;
        if (/Edg\//.test(ua))     return 'Edge';
        if (/Chrome\//.test(ua))  return 'Chrome';
        if (/Firefox\//.test(ua)) return 'Firefox';
        if (/Safari\//.test(ua))  return 'Safari';
        return 'Browser tidak dikenal';
    }

    function chk(id, ok, okText, failText) {
        var e = el(id);
        if (!e) return;
        e.innerHTML = (ok ? '<span class="ok">✓</span> ' : '<span class="fail">✗</span> ')
                    + (ok ? okText : failText);
    }

    (function initBtUI() {
        var url      = window.location;
        var isSecure = url.protocol === 'https:' || url.hostname === 'localhost' || url.hostname === '127.0.0.1';
        var isChrome = /Chrome|Chromium|Edg/.test(navigator.userAgent) && !/Firefox/.test(navigator.userAgent);
        var hasBtApi = 'bluetooth' in navigator;

        // Aplikasi kasir Android: UI Web Bluetooth tidak dipakai (lihat initApk di bawah)
        if (window.KDApk && window.KDApk.ada()) return;

        chk('strukChkBrowser', isChrome,  'Browser: ' + browserName() + ' ✓',         'Browser: ' + browserName() + ' — gunakan Chrome/Edge');
        chk('strukChkHttps',   isSecure,  'Protokol: ' + url.protocol + ' ✓',         'Protokol: ' + url.protocol + ' — perlu HTTPS atau localhost');
        chk('strukChkBt',      hasBtApi,  'Web Bluetooth API: tersedia ✓',             'Web Bluetooth API: tidak tersedia di browser ini');

        if (hasBtApi && isSecure) {
            var btnBt = el('strukBtnBluetooth');
            if (btnBt) btnBt.style.display = '';

            var savedName = localStorage.getItem(LAST_PRINTER_KEY);
            if (savedName) {
                var btnLast   = el('strukBtnLastPrinter');
                var btnForget = el('strukBtnForgetPrinter');
                if (btnLast) {
                    btnLast.textContent   = '🔁 Cetak ke "' + savedName + '"';
                    btnLast.style.display = '';
                }
                if (btnForget) btnForget.style.display = '';
            }
        } else {
            var msg = !isChrome
                ? 'Browser Anda (' + browserName() + ') tidak mendukung Web Bluetooth.\nGunakan Chrome atau Edge.'
                : (!isSecure
                    ? 'Web Bluetooth membutuhkan HTTPS atau localhost.\nSedang akses via: ' + url.origin
                    : 'Web Bluetooth API tidak tersedia di browser ini.');
            btStatus(msg, 'warning');
            showGuide(true);
        }
    })();

    // ---- ESC/POS Commands ----
    var ESC = 0x1B, GS = 0x1D, LF = 0x0A;
    var CMD = {
        init   : [ESC, 0x40],
        left   : [ESC, 0x61, 0x00],
        center : [ESC, 0x61, 0x01],
        boldOn : [ESC, 0x45, 0x01],
        boldOff: [ESC, 0x45, 0x00],
        bigOn  : [GS,  0x21, 0x11],
        bigOff : [GS,  0x21, 0x00],
        cut    : [GS,  0x56, 0x41, 0x10],
    };
    // ESC p 0 25 250 — pin=0, on=25*2ms=50ms, off=250*2ms=500ms
    var OPEN_DRAWER = [ESC, 0x70, 0x00, 0x19, 0xFA];

    // ---- Format angka rupiah tanpa prefix "Rp" (hemat lebar baris) ----
    function formatRupiah(n) {
        return Math.round(n).toLocaleString('id-ID');
    }

    // ---- Convert kg ke satuan paling readable (kg/ons/gram) untuk baris
    // item struk — mirror logic formatBerat() di pos.blade.php, disalin
    // ke sini karena script struk modal ini IIFE terpisah/tidak share scope.
    // Beda dgn versi POS: ada spasi sebelum satuan + satuan dikapitalisasi
    // (mis. "7 Ons" bukan "7ons") supaya lebih rapi di kertas struk. ----
    function formatBerat(kg) {
        kg = parseFloat(kg);
        if (isNaN(kg) || kg <= 0) return '0 Gram';
        if (kg >= 1) {
            return (kg % 1 === 0 ? kg.toFixed(0) : kg.toFixed(2).replace(/\.?0+$/, '')) + ' Kg';
        } else if (kg >= 0.1) {
            var ons = kg * 10;
            return (ons % 1 === 0 ? ons.toFixed(0) : ons.toFixed(1).replace(/\.?0+$/, '')) + ' Ons';
        } else {
            var gram = kg * 1000;
            return (gram % 1 === 0 ? gram.toFixed(0) : gram.toFixed(1).replace(/\.?0+$/, '')) + ' Gram';
        }
    }

    function capitalize(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
    }

    // ---- Ringkasan qty x harga per item — pakai format berat kg/ons/gram
    // kalau satuannya kg (jasa giling), else qty+satuan apa adanya (produk). ----
    function formatQtyHarga(item) {
        if (item.satuan === 'kg') {
            return formatBerat(item.qty) + ' x Rp ' + formatRupiah(item.harga);
        }
        var qty    = parseFloat(item.qty.toFixed(3));
        var satuan = capitalize(item.satuan);
        return qty + ' ' + satuan + ' x Rp ' + formatRupiah(item.harga);
    }

    // ---- Sama seperti formatQtyHarga() tapi TANPA harga — dipakai saat
    // cabang mengizinkan sembunyi harga & kasir tidak centang tampilkan. ----
    function formatQtyOnly(item) {
        if (item.satuan === 'kg') {
            return formatBerat(item.qty);
        }
        var qty    = parseFloat(item.qty.toFixed(3));
        var satuan = capitalize(item.satuan);
        return qty + ' ' + satuan;
    }

    // ---- Builder ESC/POS satu salinan ----
    function buildOneCopy(enc) {
        var buf  = [];
        var push = function() { for (var i=0;i<arguments.length;i++) buf.push(arguments[i]); };
        var cmd  = function(c) { push.apply(null, c); };
        var text = function(s) { buf.push.apply(buf, enc.encode(s)); };
        var nl   = function(n) { for (var i=0;i<(n||1);i++) push(LF); };
        var divider = function(ch) { text((ch||'-').repeat(COLS)); nl(); };
        var padRow  = function(l, r) {
            var pad = Math.max(1, COLS - l.length - r.length);
            text(l + ' '.repeat(pad) + r); nl();
        };

        cmd(CMD.init);
        cmd(CMD.center); cmd(CMD.boldOn); cmd(CMD.bigOn);
        text(STRUK.toko); nl();
        cmd(CMD.bigOff); cmd(CMD.boldOff);
        if (STRUK.cabang)  { text(STRUK.cabang);  nl(); }
        if (STRUK.alamat)  { text(STRUK.alamat);  nl(); }
        if (STRUK.telepon) { text('Telp: ' + STRUK.telepon); nl(); }

        if (STRUK.antrian) {
            cmd(CMD.left); divider('=');
            cmd(CMD.center); cmd(CMD.boldOn); cmd(CMD.bigOn);
            text(STRUK.antrian); nl();
            cmd(CMD.bigOff); cmd(CMD.boldOff);
        }

        cmd(CMD.left); divider('=');
        padRow('No Order', STRUK.nomor);
        padRow('Tanggal',  STRUK.tanggal);
        padRow('Kasir',    STRUK.kasir);
        if (STRUK.pelanggan)    padRow('Pelanggan', STRUK.pelanggan);
        if (STRUK.jenis_olahan) padRow('Jenis Menu', STRUK.jenis_olahan);

        divider();
        for (var i = 0; i < STRUK.items.length; i++) {
            var item = STRUK.items[i];
            if (!STRUK.show_harga) {
                // Sembunyi harga per item — cuma nama + qty, tanpa harga/subtotal.
                // TOTAL order tetap tercetak di bawah terlepas dari mode ini.
                text(item.nama + '  ' + formatQtyOnly(item)); nl();
                continue;
            }
            var qtyHarga    = formatQtyHarga(item);
            var subtotalStr = 'Rp' + formatRupiah(item.total);
            var line1       = item.nama + '  ' + qtyHarga;

            if ((line1 + '  ' + subtotalStr).length <= COLS) {
                padRow(line1, subtotalStr);
            } else {
                padRow(item.nama, subtotalStr);
                text(' ' + qtyHarga); nl();
            }
        }

        divider();
        padRow('Subtotal', 'Rp ' + STRUK.subtotal);
        if (STRUK.diskon) padRow('Diskon', '-Rp ' + STRUK.diskon);
        divider('=');
        cmd(CMD.boldOn); padRow('TOTAL', 'Rp ' + STRUK.total); cmd(CMD.boldOff);
        divider();
        padRow('Bayar (' + STRUK.tipe_bayar + ')', 'Rp ' + STRUK.bayar);
        if (STRUK.kembalian) {
            cmd(CMD.boldOn); padRow('Kembalian', 'Rp ' + STRUK.kembalian); cmd(CMD.boldOff);
        }
        divider();
        cmd(CMD.center);
        for (var fi = 0; fi < STRUK.footer_lines.length; fi++) {
            text(STRUK.footer_lines[fi]); nl();
        }
        nl(3); cmd(CMD.cut);

        return buf;
    }

    // ---- Separator antar salinan (garis potong ASCII, tanpa jeda baris
    // kosong — printer thermal 58mm tidak support Unicode ✂) ----
    function buildSeparator(enc) {
        var buf  = [];
        var push = function() { for (var i=0;i<arguments.length;i++) buf.push(arguments[i]); };
        var cmd  = function(c) { push.apply(null, c); };
        var text = function(s) { buf.push.apply(buf, enc.encode(s)); };
        var nl   = function(n) { for (var i=0;i<(n||1);i++) push(LF); };

        cmd(CMD.center);
        text('- - - - - - - - - - -'); nl();
        text('  POTONG DI SINI  '); nl();
        text('- - - - - - - - - - -'); nl();
        cmd(CMD.left);

        return buf;
    }

    // ---- Build seluruh receipt (n salinan, 1 BT job) ----
    // Laci hanya dibuka untuk pembayaran tunai, dan cuma 1x meski copies > 1
    // (perintah disisipkan sekali di awal buffer, dikirim dalam 1 sesi BT yang sama).
    // Antar salinan (kalau copies >= 2) disisipkan separator garis potong.
    function buildReceipt() {
        var enc    = new TextEncoder();
        var copies = STRUK.copies || 1;
        var all    = (STRUK.metode === 'tunai') ? OPEN_DRAWER.slice() : [];
        for (var c = 0; c < copies; c++) {
            var part = buildOneCopy(enc);
            all = all.concat(part);
            if (c < copies - 1) {
                all = all.concat(buildSeparator(enc));
            }
        }
        return new Uint8Array(all);
    }

    // ---- Scan characteristic writable ----
    async function findWritableChar(service) {
        var chars = await service.getCharacteristics();
        for (var i = 0; i < chars.length; i++) {
            var chr = chars[i];
            if (chr.properties.writeWithoutResponse || chr.properties.write) return chr;
        }
        return null;
    }

    async function findPrinterChar(server) {
        for (var i = 0; i < BT_SERVICE_UUIDS.length; i++) {
            btStatus('Mencari service printer... (' + (i+1) + '/' + BT_SERVICE_UUIDS.length + ')', 'info');
            try {
                var svc = await server.getPrimaryService(BT_SERVICE_UUIDS[i]);
                var chr = await findWritableChar(svc);
                if (chr) return chr;
            } catch (e) { /* service tidak ada, lanjut */ }
        }
        return null;
    }

    // ---- Kirim data dalam chunk ----
    async function sendToChar(characteristic, data) {
        var CHUNK     = 200;
        var useNoResp = characteristic.properties.writeWithoutResponse;
        for (var offset = 0; offset < data.length; offset += CHUNK) {
            var chunk = data.slice(offset, offset + CHUNK);
            if (useNoResp) {
                await characteristic.writeValueWithoutResponse(chunk);
            } else {
                await characteristic.writeValue(chunk);
            }
            if (data.length > CHUNK) await new Promise(function(r) { setTimeout(r, 30); });
        }
    }

    async function isBtAvailable() {
        try { return await navigator.bluetooth.getAvailability(); }
        catch (_) { return true; }
    }

    // ---- Main: cetak via Bluetooth ----
    async function cetakBluetooth(useLastPrinter) {
        var btn     = el('strukBtnBluetooth');
        var btnLast = el('strukBtnLastPrinter');

        var url      = window.location;
        var isSecure = url.protocol === 'https:' || url.hostname === 'localhost' || url.hostname === '127.0.0.1';
        if (!isSecure) {
            btStatus('Web Bluetooth membutuhkan HTTPS atau localhost.\nSedang akses via: ' + url.origin, 'error');
            showGuide(true); return;
        }
        if (!navigator.bluetooth) {
            btStatus('Browser ' + browserName() + ' tidak mendukung Web Bluetooth.\nGunakan Chrome atau Edge.', 'error');
            showGuide(true); return;
        }
        if (!await isBtAvailable()) {
            btStatus('Bluetooth HP/komputer tidak aktif.\nNyalakan Bluetooth di Settings lalu coba lagi.', 'error');
            showGuide(true); return;
        }

        if (btn)     btn.disabled     = true;
        if (btnLast) btnLast.disabled = true;
        var btnForget = el('strukBtnForgetPrinter');
        if (btnForget) btnForget.disabled = true;

        var savedName = localStorage.getItem(LAST_PRINTER_KEY);
        var device    = null;

        if (savedName && typeof navigator.bluetooth.getDevices === 'function') {
            btStatus('Menghubungkan ke printer terakhir "' + savedName + '"...', 'info');
            try {
                var grantedDevices = await navigator.bluetooth.getDevices();
                device = grantedDevices.find(function(d) { return d.name === savedName; }) || null;
                if (!device) btStatus('Printer terakhir tidak ditemukan, silakan pilih dari daftar...', 'warning');
            } catch (e) { /* fallback ke requestDevice */ }
        }

        var hasTriedFresh = false;
        var printerName   = '';
        var server = null;
        var stage  = 'connect';
        try {
            // Attempt 0: pakai device dari silent reconnect (kalau ada).
            // Kalau connect gagal (device stale/di luar jangkauan), otomatis
            // fallback ke requestDevice() di attempt 1 — tanpa nampilin error dulu.
            for (var attempt = 0; attempt < 2; attempt++) {
                if (!device) {
                    btStatus(useLastPrinter && savedName
                        ? 'Pilih "' + savedName + '" dari daftar yang muncul...'
                        : 'Mencari printer Bluetooth...', 'info');
                    try {
                        device = await navigator.bluetooth.requestDevice({
                            acceptAllDevices: true,
                            optionalServices: BT_SERVICE_UUIDS,
                        });
                        hasTriedFresh = true;
                    } catch (e) {
                        stage = 'cancelled';
                        throw new Error('Pemilihan printer dibatalkan.');
                    }
                }

                printerName = device.name || 'Printer';
                btStatus('Menghubungkan ke "' + printerName + '"...', 'info');

                try {
                    server = await device.gatt.connect();
                    break;
                } catch (e) {
                    console.log('Connect attempt ' + (attempt + 1) + ' gagal:', e.message);
                    if (attempt === 0 && !hasTriedFresh) {
                        device = null;
                        continue;
                    }
                    throw e;
                }
            }

            stage = 'service';
            var writeChar = await findPrinterChar(server);
            if (!writeChar) {
                throw new Error('Service printer tidak ditemukan di "' + printerName + '".\nSemua ' + BT_SERVICE_UUIDS.length + ' service UUID sudah dicoba.');
            }

            stage = 'send';
            var copies  = STRUK.copies || 1;
            var copyMsg = copies > 1 ? ' (' + copies + ' salinan)' : '';
            btStatus('Mengirim data struk ke "' + printerName + '"' + copyMsg + '...', 'info');

            await sendToChar(writeChar, buildReceipt());
            localStorage.setItem(LAST_PRINTER_KEY, printerName);
            if (btnLast) {
                btnLast.textContent   = '🔁 Cetak ke "' + printerName + '"';
                btnLast.style.display = '';
            }
            if (btnForget) btnForget.style.display = '';
            var ok = copies > 1
                ? '✓ ' + copies + ' salinan struk berhasil dicetak ke "' + printerName + '"!'
                : '✓ Struk berhasil dicetak ke "' + printerName + '"!';
            btStatus(ok, 'success');
        } catch (e) {
            if (stage === 'cancelled') {
                btStatus(e.message, 'warning');
            } else if (stage === 'connect') {
                btStatus('Gagal terhubung ke "' + printerName + '".\nError: ' + e.message, 'error');
                showGuide(true);
            } else if (stage === 'send') {
                btStatus('Gagal mengirim data ke printer.\nError: ' + e.message, 'error');
                showGuide(true);
            } else {
                btStatus(e.message, 'error');
                showGuide(true);
            }
        } finally {
            // Delay untuk flush buffer printer sebelum disconnect
            await new Promise(function(resolve) { setTimeout(resolve, 200); });

            if (server && server.connected) {
                try {
                    server.disconnect();
                } catch (e) {
                    console.log('Disconnect error (ignored):', e);
                }
            }

            if (btn)     btn.disabled     = false;
            if (btnLast) btnLast.disabled = false;
            if (btnForget) btnForget.disabled = false;
        }
    }

    // ---- Lupakan printer ----
    function forgetPrinter() {
        localStorage.removeItem(LAST_PRINTER_KEY);
        var btnLast   = el('strukBtnLastPrinter');
        var btnForget = el('strukBtnForgetPrinter');
        if (btnLast)   btnLast.style.display   = 'none';
        if (btnForget) btnForget.style.display = 'none';
        btStatus('Printer dihapus dari daftar tersimpan.', 'info');
    }

    // ---- Tutup: close modal jika ada, else close tab ----
    function tutup() {
        try {
            var m = document.getElementById('strukModal');
            if (m && m.classList.contains('show') && window.bootstrap) {
                var bsModal = window.bootstrap.Modal.getInstance(m);
                if (bsModal) { bsModal.hide(); return; }
            }
        } catch (e) {}
        window.close();
    }

    // ---- Selesai: tutup modal. Reset form POS TIDAK dipanggil di sini
    // langsung — cukup lewat listener 'hidden.bs.modal' yang dipasang
    // openStrukModal() (layouts/app.blade.php), supaya resetPosForm()
    // terjamin jalan tepat 1x apapun jalur modal ditutup, tanpa duplikat. ----
    function closeStrukModal() {
        tutup();
    }

    // ---- Expose ke onclick handlers ----
    window.strukCetakBluetooth   = cetakBluetooth;
    window.strukForgetPrinter    = forgetPrinter;
    window.strukToggleGuide      = toggleGuide;
    window.strukTutup            = tutup;
    window.closeStrukModal       = closeStrukModal;

    // ============================================================
    //  APLIKASI KASIR ANDROID (4.44 Poin 1)
    //  Data struk sama persis (buildReceipt, termasuk buka laci untuk
    //  tunai) — hanya jalur kirimnya lewat jembatan Bluetooth aplikasi,
    //  tanpa memilih printer. Cetak otomatis kalau diaktifkan di aplikasi,
    //  lalu Label Produk menyusul (4.44 Poin 2, kalau ada item berlabel).
    // ============================================================
    var LABEL_URL = @json(($jumlahLabelCup ?? 0) > 0 ? route('label-cup.order', $order) : null);

    async function cetakApk() {
        var btn = el('strukBtnApk');
        if (btn) btn.disabled = true;
        var copies = STRUK.copies || 1;
        btStatus('Mengirim struk ke printer…', 'info');
        try {
            await window.KDApk.cetak('struk', buildReceipt());
            btStatus('✓ Struk tercetak' + (copies > 1 ? ' (' + copies + ' salinan)' : '') + '.', 'success');
            return true;
        } catch (e) {
            btStatus('Gagal cetak struk: ' + e.message + '\nCek printer lalu tekan "Cetak Struk" lagi, atau buka Pengaturan Printer Aplikasi.', 'error');
            return false;
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    window.strukCetakApk = cetakApk;

    (function initApk() {
        if (!(window.KDApk && window.KDApk.ada())) return;
        ['strukWebBtGroup', 'strukBtnForgetPrinter', 'strukBtnBrowserPrint', 'strukToggleGuide', 'strukBtGuide'].forEach(function (id) {
            var x = el(id);
            if (x) x.style.display = 'none';
        });
        var grup = el('strukApkGroup');
        if (grup) grup.style.display = '';

        var info = window.KDApk.info();
        if (!info.struk) {
            btStatus('Printer struk belum dipilih di aplikasi. Tekan "Pengaturan Printer Aplikasi".', 'warning');
            return;
        }
        if (!info.auto_struk) return;

        // Label otomatis hanya untuk transaksi baru di halaman POS (ada resetPosForm),
        // bukan saat struk dibuka ulang dari Detail Order / Riwayat (cetak ulang).
        var transaksiBaru = typeof window.resetPosForm === 'function';
        cetakApk().then(function (ok) {
            if (ok && transaksiBaru && LABEL_URL && info.auto_label && window.openCupLabel) {
                window.openCupLabel(LABEL_URL, { otomatis: true });
            }
        });
    })();

})();
</script>
