package main

// Halaman pengaturan agen — dibuka di http://127.0.0.1:17331/ (hanya dari PC ini).
const halamanPengaturan = `<!doctype html>
<html lang="id"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BABA Printer Agent</title>
<style>
:root{--hijau:#2D6A4F;--abu:#6b7280;--garis:#e5e7eb;--merah:#b91c1c}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#f3f4f6;color:#111827}
.wrap{max-width:640px;margin:0 auto;padding:16px}
h1{font-size:20px;margin:8px 0 2px}
.sub{color:var(--abu);font-size:14px;margin:0 0 16px}
.card{background:#fff;border:1px solid var(--garis);border-radius:10px;padding:16px;margin-bottom:12px}
.card h2{font-size:15px;margin:0 0 10px}
label{display:block;font-size:14px;margin:10px 0 4px;font-weight:600}
select,textarea,input[type=text]{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;font-size:15px;background:#fff}
textarea{min-height:80px;font-family:monospace;font-size:13px}
.pilih{display:flex;gap:8px;flex-wrap:wrap}
.pilih label{flex:1 1 200px;font-weight:400;border:1px solid #d1d5db;border-radius:8px;padding:10px;margin:0;cursor:pointer;min-height:44px}
.pilih input{margin-right:6px}
.btn{border:0;border-radius:8px;padding:11px 16px;font-size:15px;cursor:pointer;min-height:44px}
.btn-utama{background:var(--hijau);color:#fff}
.btn-garis{background:#fff;border:1px solid #d1d5db}
.btn-merah{background:#fff;border:1px solid var(--merah);color:var(--merah)}
.aksi{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
.status{padding:10px 12px;border-radius:8px;font-size:14px;margin-bottom:12px;display:none}
.status.ok{display:block;background:#dcfce7;color:#166534}
.status.err{display:block;background:#fee2e2;color:var(--merah)}
.status.info{display:block;background:#dbeafe;color:#1e40af}
.badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px;font-weight:600}
.badge.ok{background:#dcfce7;color:#166534}.badge.no{background:#fef3c7;color:#92400e}
.kecil{font-size:13px;color:var(--abu);margin:6px 0 0}
ol{padding-left:20px;margin:6px 0;font-size:14px}
</style></head>
<body><div class="wrap">
<h1>🖨️ BABA Printer Agent</h1>
<p class="sub">Cetak struk ERP BABA PARFUME langsung ke printer, tanpa memilih printer setiap transaksi. <span id="versi"></span></p>

<div id="status" class="status"></div>

<div class="card">
  <h2>Status: <span id="badge" class="badge no">memuat…</span></h2>
  <div class="kecil" id="ringkas"></div>
</div>

<div class="card">
  <h2>1. Cara printer tersambung ke PC ini</h2>
  <div class="pilih">
    <label><input type="radio" name="tipe" value="com"> Bluetooth / Serial (port COM)</label>
    <label><input type="radio" name="tipe" value="windows"> Printer USB terpasang di Windows</label>
  </div>

  <div id="bagCom" style="display:none">
    <label for="com">Port COM printer</label>
    <select id="com"></select>
    <p class="kecil">Untuk Bluetooth pilih port <b>Outgoing / Keluar</b> milik printer (mis. C58BT). Cek di <b>Settings → Bluetooth & devices → Devices → More Bluetooth settings → tab COM Ports</b>. Kalau ragu, coba satu per satu dengan tombol <b>Tes Cetak</b>.</p>
  </div>

  <div id="bagWin" style="display:none">
    <label for="printer">Printer Windows</label>
    <select id="printer"></select>
    <p class="kecil">Pilih printer thermal (mis. POS-58 / C58). Jangan pilih printer PDF.</p>
  </div>

  <div class="aksi">
    <button class="btn btn-utama" id="btnSimpan">💾 Simpan</button>
    <button class="btn btn-garis" id="btnTes">🧾 Tes Cetak</button>
    <button class="btn btn-garis" id="btnLaci">🔓 Tes Laci</button>
    <button class="btn btn-garis" id="btnMuat">🔄 Muat Ulang Daftar</button>
  </div>
</div>

<div class="card">
  <h2>2. Alamat ERP yang boleh mencetak</h2>
  <textarea id="origins"></textarea>
  <p class="kecil">Satu alamat per baris. Website lain tidak bisa memakai printer ini.</p>
</div>

<div class="card">
  <h2>3. Lainnya</h2>
  <label style="font-weight:400"><input type="checkbox" id="autostart"> Jalankan otomatis saat Windows menyala (disarankan)</label>
  <div class="aksi">
    <button class="btn btn-merah" id="btnKeluar">⏹️ Hentikan Agen</button>
  </div>
  <p class="kecil">Agen berjalan diam-diam di latar (tidak ada jendela). Buka halaman ini lagi dengan menjalankan <b>baba-printer-agent.exe</b> atau ketik <b>127.0.0.1:17331</b> di browser.</p>
</div>
</div>

<script>
var $ = function (id) { return document.getElementById(id); };
var cfg = {};

function status(teks, jenis) { var s = $('status'); s.textContent = teks; s.className = 'status ' + (jenis || 'info'); }

async function api(url, body) {
  var opt = body === undefined ? {} : { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) };
  var r = await fetch(url, opt);
  var j = await r.json().catch(function () { return { ok: false, pesan: 'HTTP ' + r.status }; });
  if (!r.ok || j.ok === false) throw new Error(j.pesan || ('HTTP ' + r.status));
  return j;
}

function tipe() { var x = document.querySelector('input[name=tipe]:checked'); return x ? x.value : ''; }
function tampilTipe() { $('bagCom').style.display = tipe() === 'com' ? '' : 'none'; $('bagWin').style.display = tipe() === 'windows' ? '' : 'none'; }

function isiSelect(sel, items, nilai, kosong) {
  sel.innerHTML = '';
  if (!items.length) { var o = document.createElement('option'); o.value = ''; o.textContent = kosong; sel.appendChild(o); return; }
  items.forEach(function (it) { var o = document.createElement('option'); o.value = it.v; o.textContent = it.t; sel.appendChild(o); });
  if (nilai && !items.some(function (it) { return it.v === nilai; })) {
    var o = document.createElement('option'); o.value = nilai; o.textContent = nilai + ' (tidak terdeteksi)'; sel.appendChild(o);
  }
  if (nilai) sel.value = nilai;
}

async function muat() {
  var c = await api('/api/config');
  cfg = c.config;
  $('versi').textContent = 'Versi ' + c.versi;
  var d = await api('/api/perangkat');
  isiSelect($('com'), d.com.map(function (p) { return { v: p.port, t: p.port + ' — ' + p.keterangan }; }), cfg.com, 'Tidak ada port COM — pairing printer Bluetooth dulu');
  isiSelect($('printer'), d.windows.map(function (n) { return { v: n, t: n }; }), cfg.printer, 'Tidak ada printer Windows');
  document.querySelectorAll('input[name=tipe]').forEach(function (r) { r.checked = r.value === cfg.tipe; });
  $('origins').value = (cfg.origins || []).join('\n');
  $('autostart').checked = !!c.autostart;
  tampilTipe();
  var b = $('badge');
  b.textContent = c.siap ? 'Siap' : 'Printer belum diatur';
  b.className = 'badge ' + (c.siap ? 'ok' : 'no');
  $('ringkas').textContent = c.siap ? ('Struk dicetak ke ' + (cfg.tipe === 'com' ? cfg.com : cfg.printer) + '.') : 'Pilih cara tersambung & printer, lalu Simpan dan Tes Cetak.';
}

async function simpan() {
  var t = tipe();
  if (!t) throw new Error('Pilih dulu cara printer tersambung.');
  var body = { tipe: t, com: $('com').value, printer: $('printer').value, baud: cfg.baud || 9600,
    origins: $('origins').value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean) };
  if (t === 'com' && !body.com) throw new Error('Pilih port COM.');
  if (t === 'windows' && !body.printer) throw new Error('Pilih printer Windows.');
  await api('/api/config', body);
  await muat();
}

function tombol(id, fn) {
  $(id).addEventListener('click', async function () {
    var b = this; b.disabled = true;
    try { await fn(); } catch (e) { status('Gagal: ' + e.message, 'err'); } finally { b.disabled = false; }
  });
}

document.querySelectorAll('input[name=tipe]').forEach(function (r) { r.addEventListener('change', tampilTipe); });
tombol('btnSimpan', async function () { await simpan(); status('Pengaturan tersimpan.', 'ok'); });
tombol('btnTes', async function () { await simpan(); status('Mengirim tes cetak…', 'info'); var j = await api('/api/tes', {}); status('✓ ' + j.pesan + '. Cek kertas yang keluar.', 'ok'); });
tombol('btnLaci', async function () { await simpan(); var j = await api('/api/tes?laci=1', {}); status('✓ Perintah buka laci terkirim.', 'ok'); });
tombol('btnMuat', async function () { await muat(); status('Daftar diperbarui.', 'ok'); });
$('autostart').addEventListener('change', async function () {
  try { await api('/api/autostart', { aktif: this.checked }); status(this.checked ? 'Agen akan jalan otomatis saat Windows menyala.' : 'Jalan otomatis dimatikan.', 'ok'); }
  catch (e) { status('Gagal: ' + e.message, 'err'); }
});
tombol('btnKeluar', async function () {
  if (!confirm('Hentikan agen? Struk tidak bisa dicetak lewat agen sampai dijalankan lagi.')) return;
  await api('/api/keluar', {});
  status('Agen dihentikan. Jalankan baba-printer-agent.exe untuk menyalakan lagi.', 'info');
});

muat().catch(function (e) { status('Gagal memuat: ' + e.message, 'err'); });
</script>
</body></html>`
