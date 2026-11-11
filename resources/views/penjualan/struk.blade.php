<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk - {{ $order->nomor_order }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { height: auto; }
        body { font-family: 'Courier New', monospace; font-size: 11px; width: 72mm; margin: 0 auto; padding: 4mm; height: auto; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .line { border-top: 1px dashed #000; margin: 4px 0; }
        .line-solid { border-top: 1px solid #000; margin: 4px 0; }
        .row { display: flex; justify-content: space-between; }
        .row-3 { display: flex; gap: 4px; }
        .item-name { flex: 1; }
        .item-qty { width: 30px; text-align: right; }
        .item-price { width: 65px; text-align: right; }
        .total-section { font-weight: bold; font-size: 12px; }
        .header-store { font-size: 14px; font-weight: bold; }
        .footer-gap { margin-bottom: 4mm; }

        /* ---- Tombol aksi ---- */
        .no-print { font-family: system-ui, sans-serif; text-align: center; margin-top: 10mm; }
        .btn-aksi {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 18px; border: none; border-radius: 8px;
            cursor: pointer; font-size: 13px; font-weight: 600;
            margin: 4px; transition: opacity .15s;
        }
        .btn-aksi:hover { opacity: .85; }
        .btn-print     { background: #2563eb; color: #fff; min-height: 50px; font-size: 1rem; }
        .btn-bt        { background: #0284c7; color: #fff; min-height: 50px; font-size: 1rem; }
        .btn-bt-forget { background: #dc2626; color: #fff; font-size: 12px; padding: 10px 12px; }
        .btn-close     { background: #e2e8f0; color: #1e293b; }
        .btn-aksi:disabled { opacity: .4; cursor: not-allowed; }
        @media (max-width: 480px) {
            .btn-aksi:not(.btn-close):not(.btn-bt-forget) {
                display: flex; width: 100%; justify-content: center; margin: 4px 0;
            }
        }

        /* ---- Status bar ---- */
        #btStatus {
            display: none; margin: 10px auto 0; max-width: 340px;
            padding: 9px 14px; border-radius: 8px; font-size: 12px;
            font-family: system-ui, sans-serif; text-align: left;
            white-space: pre-line; line-height: 1.5;
        }
        #btStatus.info    { background: #dbeafe; color: #1d4ed8; display: block; border: 1px solid #bfdbfe; }
        #btStatus.success { background: #dcfce7; color: #15803d; display: block; border: 1px solid #bbf7d0; }
        #btStatus.error   { background: #fee2e2; color: #dc2626; display: block; border: 1px solid #fecaca; }
        #btStatus.warning { background: #fef9c3; color: #854d0e; display: block; border: 1px solid #fde68a; }

        /* ---- Panel panduan ---- */
        #btGuide {
            display: none; margin: 12px auto 0; max-width: 340px;
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;
            padding: 12px 14px; font-family: system-ui, sans-serif;
            font-size: 12px; text-align: left; color: #334155;
        }
        #btGuide.show { display: block; }
        #btGuide h4 { font-size: 13px; margin-bottom: 6px; color: #1e293b; }
        #btGuide ul { padding-left: 16px; margin: 4px 0 8px; }
        #btGuide li { margin-bottom: 3px; }
        #btGuide .ok   { color: #16a34a; }
        #btGuide .fail { color: #dc2626; }
        #btGuide .warn { color: #d97706; }
        #btGuide a { color: #2563eb; word-break: break-all; }
        #btGuide .section { margin-top: 8px; padding-top: 8px; border-top: 1px solid #e2e8f0; }
        .toggle-guide { background: none; border: none; color: #64748b; font-size: 11px;
                        cursor: pointer; margin-top: 6px; text-decoration: underline;
                        font-family: system-ui, sans-serif; }

        @media print {
            @page { margin: 0; size: 80mm auto; }
            html, body {
                width: 80mm; height: auto !important;
                min-height: 0 !important; margin: 0;
                padding: 2mm; overflow: visible;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

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
    <div class="center footer-gap" style="font-size:10px">
        {!! nl2br(e(($order->cabang?->footer_struk && trim($order->cabang->footer_struk) !== '')
            ? $order->cabang->footer_struk
            : implode("\n", $brand->footerStrukBaris()))) !!}
    </div>

    {{-- Tombol Aksi --}}
    <div class="no-print">
        {{-- BT 1x: hanya tampil jika browser support, diinisialisasi JS --}}
        <button class="btn-aksi btn-bt" id="btnBluetooth"
                onclick="cetakBluetooth()" style="display:none">
            <svg width="15" height="15" fill="currentColor" viewBox="0 0 384 512">
                <path d="M292.6 171.4L196.7 67.9V222.9l95.9-51.5zm-95.9 217.7l95.9-51.5-95.9-103.5v155zm128.3-148L215.8 192l109.2-49.2L192 32v448l133-107.3L215.8 320l109.2-48.9z"/>
            </svg>
            📶 Pilih Printer Bluetooth 1x
        </button>
        {{-- BT 2x: tombol baru, tampil bersamaan dengan BT 1x --}}
        <button class="btn-aksi btn-bt" id="btnBluetooth2x"
                onclick="printBluetooth2x()" style="display:none">
            <svg width="15" height="15" fill="currentColor" viewBox="0 0 384 512">
                <path d="M292.6 171.4L196.7 67.9V222.9l95.9-51.5zm-95.9 217.7l95.9-51.5-95.9-103.5v155zm128.3-148L215.8 192l109.2-49.2L192 32v448l133-107.3L215.8 320l109.2-48.9z"/>
            </svg>
            📶 Pilih Printer Bluetooth 2x
        </button>
        {{-- Tombol cetak ke printer terakhir, muncul setelah cetak pertama berhasil --}}
        <button class="btn-aksi btn-bt" id="btnLastPrinter"
                onclick="cetakBluetooth(true)" style="display:none">
        </button>
        {{-- Tombol lupakan printer, muncul bersamaan dengan btnLastPrinter --}}
        <button class="btn-aksi btn-bt-forget" id="btnForgetPrinter"
                onclick="forgetPrinter()" style="display:none">
            🗑️ Lupakan Printer
        </button>
        {{-- Browser 1x --}}
        <button class="btn-aksi btn-print" onclick="window.print()">
            🖨️ Cetak Browser 1x
        </button>
        {{-- Browser 2x: tombol baru --}}
        <button class="btn-aksi btn-print" id="btnPrint2x" onclick="printBrowser2x()">
            🖨️ Cetak Browser 2x
        </button>
        <button class="btn-aksi btn-close" onclick="window.close()">
            Tutup
        </button>

        <div id="btStatus"></div>

        <button class="toggle-guide" id="toggleGuide" onclick="toggleGuide()" style="display:none">
            ℹ️ Lihat panduan &amp; troubleshooting
        </button>

        {{-- Panel panduan (muncul jika ada masalah) --}}
        <div id="btGuide">
            <h4>📋 Syarat Cetak Bluetooth (Web Bluetooth API)</h4>
            <ul>
                <li id="chkBrowser">🔄 Browser: Chrome atau Edge (≥ versi 56)</li>
                <li id="chkHttps">🔄 Protokol: HTTPS atau localhost</li>
                <li id="chkBt">🔄 Bluetooth komputer/HP aktif</li>
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

</body>
@php
$items = $order->items->map(function($item) {
    $ket = '';
    if ($item->berat_daging) {
        $ket = 'Berat: ' . rtrim(rtrim(number_format($item->berat_daging, 3, '.', ''), '0'), '.')
             . 'kg - ' . ucfirst($item->jenis_olahan ?? '');
    }
    return [
        'nama'   => $item->nama_item,
        'qty'    => rtrim(rtrim(number_format($item->qty, 3, '.', ''), '0'), '.'),
        'satuan' => $item->satuan,
        'harga'  => number_format($item->harga_satuan, 0, ',', '.'),
        'total'  => number_format($item->total_harga, 0, ',', '.'),
        'ket'    => $ket,
    ];
})->values()->toArray();

$footerLines = ($order->cabang?->footer_struk && trim($order->cabang->footer_struk) !== '')
    ? array_values(array_filter(explode("\n", $order->cabang->footer_struk), fn($l) => trim($l) !== ''))
    : $brand->footerStrukBaris();

$struData = [
    'toko'         => $brand->namaSingkat(),
    'cabang'       => $order->cabang?->nama_cabang ?? '',
    'alamat'       => $order->cabang?->alamat ?? '',
    'telepon'      => $order->cabang?->telepon ?? '',
    'nomor'        => $order->nomor_order,
    'tanggal'      => $order->tanggal_order->format('d/m/Y'),
    'kasir'        => $order->kasir?->name ?? '-',
    'pelanggan'    => $order->nama_pelanggan ?? $order->pelanggan?->nama_pelanggan ?? '',
    'items'        => $items,
    'subtotal'     => number_format($order->total_harga, 0, ',', '.'),
    'diskon'       => $order->diskon > 0 ? number_format($order->diskon, 0, ',', '.') : '',
    'total'        => number_format($order->total_bayar, 0, ',', '.'),
    'tipe_bayar'   => $order->tipe_pembayaran->label(),
    'bayar'        => number_format($order->jumlah_bayar, 0, ',', '.'),
    'kembalian'    => $order->kembalian > 0 ? number_format($order->kembalian, 0, ',', '.') : '',
    'antrian'      => $order->nomor_antrian ? str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT) : null,
    'footer_lines' => $footerLines,
    'show_harga'   => $showItemPrice,
];
@endphp
<script>
// ============================================================
//  ESC/POS Bluetooth Printer — Web Bluetooth API
//  Mendukung: Xprinter, GOOJPRT, RPP series, Nordic UART, dll.
// ============================================================
const STRUK           = @json($struData);
const COLS            = 32;   // karakter per baris (aman untuk 58mm & 80mm)
const LAST_PRINTER_KEY = 'btLastPrinterName';

// Semua service UUID yang mungkin digunakan printer thermal BLE.
// SEMUA harus ada di optionalServices saat requestDevice()
// supaya Chrome mengizinkan akses ke service-nya.
const BT_SERVICE_UUIDS = [
    '0000ae30-0000-1000-8000-00805f9b34fb',  // RPP02N_BLE — service utama (AE01/AE03 write)
    '0000ae3a-0000-1000-8000-00805f9b34fb',  // RPP02N_BLE — service alternatif
    '000018f0-0000-1000-8000-00805f9b34fb',  // Xprinter, GOOJPRT, RPP series umum
    '49535343-fe7d-4ae5-8fa9-9fafd205e455',  // Microchip SPP
    '0000ff00-0000-1000-8000-00805f9b34fb',  // Generic FF00
    '0000fee7-0000-1000-8000-00805f9b34fb',  // Generic FEE7
    'e7810a71-73ae-499d-8c15-faa9aef0c3f2',  // HPRT, PT-280
    '6e400001-b5a3-f393-e0a9-e50e24dcca9e',  // Nordic UART (NUS)
];

// ---- Diagnostik saat halaman load --------------------------
(function initBtUI() {
    const url      = window.location;
    const isSecure = url.protocol === 'https:' || url.hostname === 'localhost' || url.hostname === '127.0.0.1';
    const isChrome = /Chrome|Chromium|Edg/.test(navigator.userAgent) && !/Firefox/.test(navigator.userAgent);
    const hasBtApi = 'bluetooth' in navigator;

    console.group('[BT Printer] Diagnostik awal');
    console.log('URL          :', url.href);
    console.log('Secure       :', isSecure);
    console.log('Chrome/Edge  :', isChrome);
    console.log('BT API       :', hasBtApi);
    console.log('Service UUIDs:', BT_SERVICE_UUIDS);
    console.groupEnd();

    _chk('chkBrowser', isChrome,  'Browser: ' + browserName() + ' ✓',  'Browser: ' + browserName() + ' — gunakan Chrome/Edge');
    _chk('chkHttps',   isSecure,  'Protokol: ' + url.protocol + ' ✓',  'Protokol: ' + url.protocol + ' — perlu HTTPS atau localhost');
    _chk('chkBt',      hasBtApi,  'Web Bluetooth API: tersedia ✓',     'Web Bluetooth API: tidak tersedia di browser ini');

    if (hasBtApi && isSecure) {
        document.getElementById('btnBluetooth').style.display   = '';
        document.getElementById('btnBluetooth2x').style.display = '';

        // Tampilkan tombol "Cetak ke printer terakhir" jika pernah cetak sebelumnya
        const savedName = localStorage.getItem(LAST_PRINTER_KEY);
        if (savedName) {
            const btnLast   = document.getElementById('btnLastPrinter');
            const btnForget = document.getElementById('btnForgetPrinter');
            btnLast.textContent   = '🔁 Cetak ke "' + savedName + '"';
            btnLast.style.display = '';
            if (btnForget) btnForget.style.display = '';
        }
        console.log('[BT Printer] Siap — tombol ditampilkan.');
    } else {
        let msg = '';
        if (!isChrome)   msg = 'Browser Anda (' + browserName() + ') tidak mendukung Web Bluetooth.\nGunakan Chrome atau Edge.';
        else if (!isSecure) msg = 'Web Bluetooth membutuhkan HTTPS atau localhost.\nSedang akses via: ' + url.origin;
        else             msg = 'Web Bluetooth API tidak tersedia di browser ini.';
        status(msg, 'warning');
        showGuide(true);
    }
})();

function browserName() {
    const ua = navigator.userAgent;
    if (/Edg\//.test(ua))     return 'Edge';
    if (/Chrome\//.test(ua))  return 'Chrome';
    if (/Firefox\//.test(ua)) return 'Firefox';
    if (/Safari\//.test(ua))  return 'Safari';
    return 'Browser tidak dikenal';
}
function _chk(id, ok, okText, failText) {
    const el = document.getElementById(id);
    if (!el) return;
    el.innerHTML = (ok ? '<span class="ok">✓</span> ' : '<span class="fail">✗</span> ') + (ok ? okText : failText);
}

// ---- ESC/POS Commands --------------------------------------
const ESC = 0x1B, GS = 0x1D, LF = 0x0A;
const CMD = {
    init   : [ESC, 0x40],
    left   : [ESC, 0x61, 0x00],
    center : [ESC, 0x61, 0x01],
    boldOn : [ESC, 0x45, 0x01],
    boldOff: [ESC, 0x45, 0x00],
    bigOn  : [GS,  0x21, 0x11],
    bigOff : [GS,  0x21, 0x00],
    cut    : [GS,  0x56, 0x41, 0x10],
};

// ---- Builder ESC/POS ----------------------------------------
function buildReceipt() {
    const enc     = new TextEncoder();
    const buf     = [];
    const push    = (...b)    => buf.push(...b);
    const cmd     = (c)       => push(...c);
    const text    = (s)       => buf.push(...enc.encode(s));
    const nl      = (n = 1)   => { for (let i = 0; i < n; i++) push(LF); };
    const divider = (ch = '-')=> { text(ch.repeat(COLS)); nl(); };
    const padRow  = (l, r)    => {
        const pad = Math.max(1, COLS - l.length - r.length);
        text(l + ' '.repeat(pad) + r); nl();
    };

    cmd(CMD.init);
    cmd(CMD.center); cmd(CMD.boldOn); cmd(CMD.bigOn);
    text(STRUK.toko); nl();
    cmd(CMD.bigOff); cmd(CMD.boldOff);
    if (STRUK.cabang)  { text(STRUK.cabang);  nl(); }
    if (STRUK.alamat)  { text(STRUK.alamat);  nl(); }
    if (STRUK.telepon) { text('Telp: ' + STRUK.telepon); nl(); }

    // Nomor antrian — cetak besar jika ada
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
    if (STRUK.pelanggan) padRow('Pelanggan', STRUK.pelanggan);

    divider();
    for (const item of STRUK.items) {
        cmd(CMD.boldOn); text(item.nama); nl(); cmd(CMD.boldOff);
        if (STRUK.show_harga) {
            padRow(item.qty + ' ' + item.satuan + ' x Rp ' + item.harga, 'Rp ' + item.total);
        } else {
            text(item.qty + ' ' + item.satuan); nl();
        }
        if (item.ket) { text('  ' + item.ket); nl(); }
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
    for (const line of STRUK.footer_lines) {
        text(line); nl();
    }
    nl(3);
    cmd(CMD.cut);

    return new Uint8Array(buf);
}

// ---- Scan seluruh characteristic di satu service -----------
// Kunci perbaikan: jangan hardcode UUID char, scan semua & cari yang writable
async function findWritableChar(service) {
    const chars = await service.getCharacteristics();
    console.log(
        '[BT Printer] Characteristics di service', service.uuid, ':',
        chars.map(c => c.uuid
            + ' | write=' + c.properties.write
            + ' | writeNoResp=' + c.properties.writeWithoutResponse)
    );
    for (const chr of chars) {
        if (chr.properties.writeWithoutResponse || chr.properties.write) {
            console.log('[BT Printer] ✓ Writable char ditemukan:', chr.uuid,
                '(writeNoResp=' + chr.properties.writeWithoutResponse + ')');
            return chr;
        }
    }
    console.warn('[BT Printer] Tidak ada writable char di service', service.uuid);
    return null;
}

// ---- Iterasi semua service UUID sampai menemukan yang cocok -
async function findPrinterChar(server) {
    for (let i = 0; i < BT_SERVICE_UUIDS.length; i++) {
        const svcUuid = BT_SERVICE_UUIDS[i];
        console.log('[BT Printer] Mencoba service ' + (i + 1) + '/' + BT_SERVICE_UUIDS.length + ':', svcUuid);
        status('Mencari service printer... (' + (i + 1) + '/' + BT_SERVICE_UUIDS.length + ')', 'info');
        try {
            const svc = await server.getPrimaryService(svcUuid);
            console.log('[BT Printer] ✓ Service ditemukan!', svcUuid);
            const chr = await findWritableChar(svc);
            if (chr) return chr;
            // service ada tapi tidak ada writable char — lanjut ke UUID berikutnya
        } catch (e) {
            // service tidak ada di printer ini — normal, lanjut berikutnya
            console.log('[BT Printer] Service tidak ada:', svcUuid, '→', e.message);
        }
    }
    return null;
}

// ---- Kirim data ke printer dalam chunk ----------------------
async function sendToChar(characteristic, data) {
    const CHUNK     = 200;
    const useNoResp = characteristic.properties.writeWithoutResponse;
    console.log('[BT Printer] Mengirim', data.length, 'bytes | mode:',
        useNoResp ? 'writeWithoutResponse' : 'writeWithResponse');

    for (let i = 0; i < data.length; i += CHUNK) {
        const chunk = data.slice(i, i + CHUNK);
        try {
            // Gunakan API baru (tidak deprecated)
            if (useNoResp) {
                await characteristic.writeValueWithoutResponse(chunk);
            } else {
                await characteristic.writeValueWithResponse(chunk);
            }
        } catch (_) {
            // Fallback ke writeValue (deprecated) untuk browser/firmware lama
            await characteristic.writeValue(chunk);
        }
        if (i + CHUNK < data.length) {
            await new Promise(r => setTimeout(r, 30));
        }
    }
}

// ---- Cek Bluetooth OS aktif ---------------------------------
async function isBtAvailable() {
    try { return await navigator.bluetooth.getAvailability(); }
    catch (_) { return true; }
}

// ---- Main: cetak via Bluetooth ------------------------------
// useLastPrinter: true → tampilkan nama printer tersimpan sebagai hint di status
async function cetakBluetooth(useLastPrinter) {
    const btn     = document.getElementById('btnBluetooth');
    const btnLast = document.getElementById('btnLastPrinter');

    // Pre-check HTTPS
    const url      = window.location;
    const isSecure = url.protocol === 'https:' || url.hostname === 'localhost' || url.hostname === '127.0.0.1';
    if (!isSecure) {
        status('Web Bluetooth membutuhkan HTTPS atau localhost.\nSedang akses via: ' + url.origin, 'error');
        showGuide(true); return;
    }
    // Pre-check API
    if (!navigator.bluetooth) {
        status('Browser ' + browserName() + ' tidak mendukung Web Bluetooth.\nGunakan Chrome atau Edge.', 'error');
        showGuide(true); return;
    }
    // Pre-check BT aktif
    if (!await isBtAvailable()) {
        status('Bluetooth HP/komputer tidak aktif.\nNyalakan Bluetooth di Settings lalu coba lagi.', 'error');
        showGuide(true); return;
    }

    btn.disabled = true;
    if (btnLast) btnLast.disabled = true;
    const btnForget  = document.getElementById('btnForgetPrinter');
    if (btnForget) btnForget.disabled = true;

    const savedName = localStorage.getItem(LAST_PRINTER_KEY);

    // ---- Coba silent reconnect via getDevices() (Chrome 85+ permissions backend) ----
    let device = null;
    if (useLastPrinter && savedName && typeof navigator.bluetooth.getDevices === 'function') {
        status('Menghubungkan ke printer terakhir "' + savedName + '"...', 'info');
        try {
            const grantedDevices = await navigator.bluetooth.getDevices();
            console.log('[BT Printer] getDevices() →', grantedDevices.map(d => d.name));
            device = grantedDevices.find(d => d.name === savedName) || null;
            if (device) {
                console.log('[BT Printer] ✓ Printer terakhir ditemukan via getDevices():', device.name);
            } else {
                console.log('[BT Printer] Printer "' + savedName + '" tidak ada di getDevices(), fallback ke requestDevice()');
                status('Printer terakhir tidak ditemukan, silakan pilih dari daftar...', 'warning');
            }
        } catch (e) {
            console.warn('[BT Printer] getDevices() gagal:', e.message, '— fallback ke requestDevice()');
        }
    }

    // ---- Jika belum dapat device, tampilkan popup pemilihan ----
    if (!device) {
        if (useLastPrinter && savedName) {
            status('Pilih "' + savedName + '" dari daftar yang muncul...', 'info');
        } else {
            status('Mencari printer Bluetooth...', 'info');
        }
        try {
            device = await navigator.bluetooth.requestDevice({
                acceptAllDevices: true,
                optionalServices: BT_SERVICE_UUIDS,
            });
            console.log('[BT Printer] Device dipilih:', device.name, '| id:', device.id);
        } catch (e) {
            console.warn('[BT Printer] requestDevice dibatalkan:', e.message);
            status('Pemilihan printer dibatalkan.', 'warning');
            btn.disabled = false;
            if (btnLast)   btnLast.disabled   = false;
            if (btnForget) btnForget.disabled = false;
            return;
        }
    }

    const printerName = device.name || 'Printer';
    status('Menghubungkan ke "' + printerName + '"...', 'info');

    // Connect GATT
    let server;
    try {
        server = await device.gatt.connect();
        console.log('[BT Printer] ✓ GATT terhubung:', printerName);
    } catch (e) {
        console.error('[BT Printer] Gagal GATT connect:', e.message);
        status('Gagal terhubung ke "' + printerName + '".\nError: ' + e.message, 'error');
        showGuide(true);
        btn.disabled = false;
        if (btnLast)   btnLast.disabled   = false;
        if (btnForget) btnForget.disabled = false;
        return;
    }

    // Scan service & characteristic
    const writeChar = await findPrinterChar(server);

    if (!writeChar) {
        console.error('[BT Printer] Tidak ada writable characteristic ditemukan di semua service UUID.');
        status(
            'Service printer tidak ditemukan di "' + printerName + '".\n' +
            'Semua ' + BT_SERVICE_UUIDS.length + ' service UUID sudah dicoba.\n' +
            'Buka DevTools → Console untuk melihat UUID yang terdeteksi.\n' +
            'Info ini bisa membantu developer menambahkan dukungan printer ini.',
            'error'
        );
        showGuide(true);
        server.disconnect();
        btn.disabled = false;
        if (btnLast)   btnLast.disabled   = false;
        if (btnForget) btnForget.disabled = false;
        return;
    }

    // Kirim data struk
    status('Mengirim data struk ke "' + printerName + '"...', 'info');
    try {
        const receipt = buildReceipt();
        console.log('[BT Printer] Struk dibangun:', receipt.length, 'bytes');
        await sendToChar(writeChar, receipt);
        console.log('[BT Printer] ✓ Struk berhasil terkirim!');

        // Simpan nama printer terakhir
        localStorage.setItem(LAST_PRINTER_KEY, printerName);
        if (btnLast) {
            btnLast.textContent   = '🔁 Cetak ke "' + printerName + '"';
            btnLast.style.display = '';
        }
        if (btnForget) btnForget.style.display = '';

        status('✓ Struk berhasil dicetak ke "' + printerName + '"!', 'success');
    } catch (e) {
        console.error('[BT Printer] Gagal kirim data:', e.message);
        status('Gagal mengirim data ke printer.\nError: ' + e.message, 'error');
        showGuide(true);
    } finally {
        server.disconnect();
        btn.disabled = false;
        if (btnLast)   btnLast.disabled   = false;
        if (btnForget) btnForget.disabled = false;
    }
}

// ---- Lupakan printer yang disimpan -------------------------
function forgetPrinter() {
    localStorage.removeItem(LAST_PRINTER_KEY);
    const btnLast   = document.getElementById('btnLastPrinter');
    const btnForget = document.getElementById('btnForgetPrinter');
    if (btnLast)   btnLast.style.display   = 'none';
    if (btnForget) btnForget.style.display = 'none';
    status('Printer dihapus dari daftar tersimpan.', 'info');
    console.log('[BT Printer] Printer terlupakan dari localStorage.');
}

// ---- Cetak Browser 2x --------------------------------------
function printBrowser2x() {
    const btn = document.getElementById('btnPrint2x');
    if (btn) btn.disabled = true;

    let count = 0;

    function doPrint() { window.print(); }

    function listener() {
        count++;
        if (count < 2) {
            setTimeout(doPrint, 500);
        } else {
            window.removeEventListener('afterprint', listener);
            if (btn) btn.disabled = false;
        }
    }

    window.addEventListener('afterprint', listener);

    // Fallback: afterprint tidak fire dalam 5 detik → paksa print kedua
    setTimeout(function() {
        if (count < 2) {
            count = 1;
            window.removeEventListener('afterprint', listener);
            doPrint();
            if (btn) btn.disabled = false;
        }
    }, 5000);

    doPrint();
}

// ---- Cetak Bluetooth 2x ------------------------------------
async function printBluetooth2x() {
    const btn2x = document.getElementById('btnBluetooth2x');
    if (btn2x) btn2x.disabled = true;
    try {
        await cetakBluetooth();          // print pertama (akan memilih printer)
        await new Promise(r => setTimeout(r, 2000)); // jeda agar printer buffer kosong
        await cetakBluetooth(true);      // print kedua (pakai printer yang sama)
    } finally {
        if (btn2x) btn2x.disabled = false;
    }
}

// ---- UI helpers --------------------------------------------
function status(msg, type) {
    const el = document.getElementById('btStatus');
    el.textContent = msg;
    el.className   = type;
}
function showGuide(show) {
    document.getElementById('btGuide').classList.toggle('show', show);
    document.getElementById('toggleGuide').style.display = '';
    document.getElementById('toggleGuide').textContent =
        show ? '▲ Sembunyikan panduan' : 'ℹ️ Lihat panduan & troubleshooting';
}
function toggleGuide() {
    showGuide(!document.getElementById('btGuide').classList.contains('show'));
}
</script>
</html>
