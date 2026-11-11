@extends('layouts.app')

@section('title', 'Daftarkan Wajah - '.$karyawan->nama_lengkap)

@push('styles')
<style>
.camera-container {
    position: relative;
    width: 100%;
    max-width: 480px;
    margin: 0 auto;
    background: #000;
    border-radius: 12px;
    overflow: hidden;
}
#videoEl {
    width: 100%;
    display: block;
    transform: scaleX(-1); /* mirror */
}
#canvasOverlay {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    pointer-events: none;
    transform: scaleX(-1);
}
.camera-guide {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 60%; aspect-ratio: 1;
    border: 3px solid rgba(255,255,255,0.5);
    border-radius: 50%;
    pointer-events: none;
}
.camera-status {
    position: absolute;
    bottom: 1rem;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 4px 16px;
    border-radius: 20px;
    font-size: 0.85rem;
    white-space: nowrap;
}
.foto-preview-item {
    position: relative;
    width: 80px; height: 80px;
    border-radius: 8px;
    overflow: hidden;
    border: 2px solid #e2e8f0;
}
.foto-preview-item img {
    width: 100%; height: 100%; object-fit: cover;
}
.foto-preview-item .delete-btn {
    position: absolute; top: 2px; right: 2px;
    background: rgba(220,53,69,0.9);
    color: white; border: none;
    border-radius: 50%;
    width: 22px; height: 22px;
    font-size: 0.7rem;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
}
.loading-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.7);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999;
    flex-direction: column;
    gap: 1rem;
}
.loading-overlay .spinner {
    width: 48px; height: 48px;
    border: 4px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
@endpush

@section('content')
<div class="container-fluid px-3 px-md-4">

    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('face-registration.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h4 class="mb-0 fw-semibold">Daftarkan Wajah</h4>
            <div class="text-muted small">{{ $karyawan->nama_lengkap }} — {{ $karyawan->jabatan }}</div>
        </div>
    </div>

    {{-- Alert model loading --}}
    <div id="modelAlert" class="alert alert-info border-0" role="alert">
        <i class="bi bi-cpu me-2"></i>
        <span id="modelAlertText">Memuat model AI face recognition...</span>
    </div>

    <div class="row g-4">
        {{-- Kamera --}}
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pb-0">
                    <h6 class="fw-semibold mb-0"><i class="bi bi-camera me-2"></i>Kamera</h6>
                </div>
                <div class="card-body">
                    <div class="camera-container mb-3" id="cameraContainer">
                        <video id="videoEl" autoplay playsinline muted></video>
                        <canvas id="canvasOverlay"></canvas>
                        <div class="camera-guide"></div>
                        <div class="camera-status" id="cameraStatus">Memuat kamera...</div>
                    </div>

                    {{-- Petunjuk --}}
                    <div class="mb-3">
                        <div class="alert alert-light border mb-0 py-2 small">
                            <strong>Petunjuk:</strong>
                            <ol class="mb-0 mt-1 ps-3">
                                <li>Posisikan wajah di dalam lingkaran</li>
                                <li>Ambil foto dari <strong>3-5 sudut berbeda</strong> (depan, kiri, kanan)</li>
                                <li>Pastikan pencahayaan cukup dan wajah jelas</li>
                                <li>Klik "Simpan" setelah cukup foto</li>
                            </ol>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button class="btn btn-primary btn-lg" id="btnAmbilFoto" disabled>
                            <i class="bi bi-camera-fill me-2"></i>
                            <span>Ambil Foto (<span id="fotoCount">0</span>/5)</span>
                        </button>
                        <div class="row g-2">
                            <div class="col">
                                <button class="btn btn-outline-secondary w-100" id="btnSwitchCamera">
                                    <i class="bi bi-arrow-repeat me-1"></i> Ganti Kamera
                                </button>
                            </div>
                            <div class="col">
                                <button class="btn btn-outline-danger w-100" id="btnReset">
                                    <i class="bi bi-trash me-1"></i> Ulang Semua
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Preview Foto --}}
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pb-0">
                    <h6 class="fw-semibold mb-0"><i class="bi bi-images me-2"></i>Foto yang Diambil</h6>
                </div>
                <div class="card-body">

                    {{-- Preview grid --}}
                    <div class="d-flex flex-wrap gap-2 mb-4" id="fotoPreviewContainer">
                        <div class="text-muted small" id="noFotoText">
                            <i class="bi bi-camera d-block display-6 mb-2"></i>
                            Belum ada foto yang diambil.
                        </div>
                    </div>

                    {{-- Progress --}}
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Foto terkumpul</span>
                            <span id="progressText">0 / 5</span>
                        </div>
                        <div class="progress" style="height:8px">
                            <div class="progress-bar bg-success" id="progressBar" style="width:0%" role="progressbar"></div>
                        </div>
                        <div class="small text-muted mt-1">Minimal 3 foto dari sudut berbeda</div>
                    </div>

                    {{-- Kualitas --}}
                    <div id="faceQualityInfo" class="alert alert-secondary py-2 small mb-3" style="display:none">
                        <i class="bi bi-info-circle me-1"></i>
                        <span id="faceQualityText"></span>
                    </div>

                    {{-- Tombol Simpan --}}
                    <button class="btn btn-success btn-lg w-100" id="btnSimpan" disabled>
                        <i class="bi bi-floppy me-2"></i>Simpan & Selesai
                    </button>

                    <div class="text-center mt-2 small text-muted">
                        Data wajah diproses di browser, tidak dikirim ke server selain descriptor-nya
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- Loading overlay --}}
<div class="loading-overlay d-none" id="loadingOverlay">
    <div class="spinner"></div>
    <div class="text-white fw-semibold" id="loadingText">Memproses...</div>
</div>
@endsection

@push('scripts')
{{-- face-api.js dari CDN --}}
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/dist/face-api.js"></script>
<script>
const KARYAWAN_ID   = {{ $karyawan->id }};
const STORE_URL     = "{{ route('face-registration.store', $karyawan) }}";
const CSRF_TOKEN    = document.querySelector('meta[name="csrf-token"]').content;
const MODEL_URL     = '/models';

// ---- State ----
let fotoDataURLs    = [];     // base64 foto
let faceDescriptors = [];     // Float32Array per foto
let stream          = null;
let facingMode      = 'user'; // 'user' = kamera depan
let detectionInterval = null;
let modelsLoaded    = false;

const videoEl   = document.getElementById('videoEl');
const canvas    = document.getElementById('canvasOverlay');
const statusEl  = document.getElementById('cameraStatus');
const btnAmbil  = document.getElementById('btnAmbilFoto');
const btnSimpan = document.getElementById('btnSimpan');
const btnReset  = document.getElementById('btnReset');
const btnSwitch = document.getElementById('btnSwitchCamera');
const countEl   = document.getElementById('fotoCount');
const progressBar  = document.getElementById('progressBar');
const progressText = document.getElementById('progressText');
const previewContainer = document.getElementById('fotoPreviewContainer');
const noFotoText   = document.getElementById('noFotoText');
const modelAlert   = document.getElementById('modelAlert');
const modelAlertText = document.getElementById('modelAlertText');

// ---- Inisialisasi ----
async function init() {
    await loadModels();
    await startCamera();
}

async function loadModels() {
    try {
        modelAlertText.textContent = 'Memuat model face detection...';
        await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL);
        modelAlertText.textContent = 'Memuat model face landmarks...';
        await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
        modelAlertText.textContent = 'Memuat model face recognition...';
        await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
        modelsLoaded = true;
        modelAlert.className = 'alert alert-success border-0';
        modelAlertText.textContent = '✓ Model AI berhasil dimuat. Kamera siap digunakan.';
    } catch (err) {
        console.error('Model load error:', err);
        modelAlert.className = 'alert alert-warning border-0';
        modelAlertText.innerHTML = '⚠️ Model tidak ditemukan di <code>/models</code>. '
            + '<a href="#" id="cdnFallbackLink">Coba gunakan CDN</a>';
        document.getElementById('cdnFallbackLink')?.addEventListener('click', async (e) => {
            e.preventDefault();
            await loadModelsFromCDN();
        });
    }
}

async function loadModelsFromCDN() {
    const CDN = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/model';
    try {
        modelAlertText.textContent = 'Memuat model dari CDN...';
        await faceapi.nets.ssdMobilenetv1.loadFromUri(CDN);
        await faceapi.nets.faceLandmark68Net.loadFromUri(CDN);
        await faceapi.nets.faceRecognitionNet.loadFromUri(CDN);
        modelsLoaded = true;
        modelAlert.className = 'alert alert-success border-0';
        modelAlertText.textContent = '✓ Model AI berhasil dimuat dari CDN.';
    } catch (err) {
        modelAlert.className = 'alert alert-danger border-0';
        modelAlertText.textContent = '✗ Gagal memuat model. Pastikan koneksi internet tersedia.';
    }
}

async function startCamera() {
    if (stream) {
        stream.getTracks().forEach(t => t.stop());
    }
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode, width: { ideal: 640 }, height: { ideal: 480 } }
        });
        videoEl.srcObject = stream;
        videoEl.onloadedmetadata = () => {
            videoEl.play();
            canvas.width  = videoEl.videoWidth;
            canvas.height = videoEl.videoHeight;
            startDetection();
            if (modelsLoaded) {
                btnAmbil.disabled = false;
                statusEl.textContent = 'Posisikan wajah di lingkaran';
            }
        };
    } catch (err) {
        statusEl.textContent = 'Kamera tidak dapat diakses: ' + err.message;
        console.error(err);
    }
}

function startDetection() {
    if (detectionInterval) clearInterval(detectionInterval);
    detectionInterval = setInterval(async () => {
        if (!modelsLoaded || videoEl.paused || videoEl.ended) return;
        try {
            const det = await faceapi
                .detectSingleFace(videoEl, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }))
                .withFaceLandmarks();
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            if (det) {
                const box = det.detection.box;
                ctx.strokeStyle = '#22c55e';
                ctx.lineWidth = 3;
                ctx.strokeRect(box.x, box.y, box.width, box.height);
                statusEl.textContent = 'Wajah terdeteksi ✓';
                statusEl.style.background = 'rgba(34,197,94,0.8)';
                btnAmbil.disabled = !modelsLoaded;
            } else {
                statusEl.textContent = 'Arahkan wajah ke kamera...';
                statusEl.style.background = 'rgba(0,0,0,0.7)';
            }
        } catch(e) {}
    }, 400);
}

// ---- Ambil Foto ----
btnAmbil.addEventListener('click', async () => {
    if (fotoDataURLs.length >= 5) {
        alert('Maksimal 5 foto. Hapus beberapa foto dulu.');
        return;
    }

    btnAmbil.disabled = true;
    statusEl.textContent = 'Memproses wajah...';

    try {
        // Deteksi wajah & ambil descriptor
        const det = await faceapi
            .detectSingleFace(videoEl, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.4 }))
            .withFaceLandmarks()
            .withFaceDescriptor();

        if (!det) {
            alert('Wajah tidak terdeteksi. Pastikan wajah Anda terlihat jelas.');
            btnAmbil.disabled = false;
            return;
        }

        // Capture foto dari video
        const captureCanvas = document.createElement('canvas');
        captureCanvas.width  = videoEl.videoWidth;
        captureCanvas.height = videoEl.videoHeight;
        captureCanvas.getContext('2d').drawImage(videoEl, 0, 0);
        const dataURL = captureCanvas.toDataURL('image/jpeg', 0.85);

        fotoDataURLs.push(dataURL);
        faceDescriptors.push(Array.from(det.descriptor));

        addPreview(dataURL, fotoDataURLs.length - 1);
        updateUI();

    } catch (err) {
        console.error(err);
        alert('Gagal mendeteksi wajah: ' + err.message);
    }

    btnAmbil.disabled = false;
    statusEl.textContent = 'Posisikan wajah di lingkaran';
});

function addPreview(dataURL, index) {
    noFotoText.style.display = 'none';
    const wrap = document.createElement('div');
    wrap.className  = 'foto-preview-item';
    wrap.dataset.index = index;
    wrap.innerHTML  = `
        <img src="${dataURL}" alt="Foto ${index+1}">
        <button class="delete-btn" onclick="hapusFoto(${index})"><i class="bi bi-x"></i></button>
    `;
    previewContainer.appendChild(wrap);
}

function hapusFoto(index) {
    // Hapus dari array
    fotoDataURLs.splice(index, 1);
    faceDescriptors.splice(index, 1);
    // Rebuild preview
    previewContainer.innerHTML = '';
    previewContainer.appendChild(noFotoText);
    if (fotoDataURLs.length === 0) {
        noFotoText.style.display = '';
    } else {
        noFotoText.style.display = 'none';
        fotoDataURLs.forEach((url, i) => addPreview(url, i));
    }
    updateUI();
}

function updateUI() {
    const count = fotoDataURLs.length;
    countEl.textContent      = count;
    progressText.textContent = `${count} / 5`;
    progressBar.style.width  = `${(count / 5) * 100}%`;
    btnSimpan.disabled       = count < 3;
    btnAmbil.disabled        = count >= 5 || !modelsLoaded;
    if (count >= 3) {
        const qEl = document.getElementById('faceQualityInfo');
        qEl.style.display = '';
        document.getElementById('faceQualityText').textContent =
            `${count} foto wajah berhasil diambil. Klik "Simpan" untuk menyimpan.`;
    }
}

// ---- Reset ----
btnReset.addEventListener('click', () => {
    if (!confirm('Hapus semua foto yang sudah diambil?')) return;
    fotoDataURLs    = [];
    faceDescriptors = [];
    previewContainer.innerHTML = '';
    previewContainer.appendChild(noFotoText);
    noFotoText.style.display = '';
    document.getElementById('faceQualityInfo').style.display = 'none';
    updateUI();
});

// ---- Switch Camera ----
btnSwitch.addEventListener('click', () => {
    facingMode = facingMode === 'user' ? 'environment' : 'user';
    startCamera();
});

// ---- Simpan ----
btnSimpan.addEventListener('click', async () => {
    if (fotoDataURLs.length < 3) {
        alert('Minimal 3 foto diperlukan.');
        return;
    }

    const overlay   = document.getElementById('loadingOverlay');
    const loadingTx = document.getElementById('loadingText');
    overlay.classList.remove('d-none');
    loadingTx.textContent = 'Menyimpan data wajah...';

    try {
        const formData = new FormData();
        formData.append('_token', CSRF_TOKEN);
        formData.append('face_descriptors', JSON.stringify(faceDescriptors));
        fotoDataURLs.forEach((url, i) => formData.append(`face_photos[${i}]`, url));

        const resp = await fetch(STORE_URL, { method: 'POST', body: formData });
        const data = await resp.json();

        overlay.classList.add('d-none');

        if (data.success) {
            // Stop kamera
            if (stream) stream.getTracks().forEach(t => t.stop());
            if (detectionInterval) clearInterval(detectionInterval);
            // Redirect
            window.location.href = "{{ route('face-registration.index') }}?success=1";
        } else {
            alert('Gagal menyimpan: ' + (data.message ?? 'Unknown error'));
        }
    } catch (err) {
        overlay.classList.add('d-none');
        alert('Error: ' + err.message);
        console.error(err);
    }
});

// ---- Start ----
init();
</script>
@endpush
