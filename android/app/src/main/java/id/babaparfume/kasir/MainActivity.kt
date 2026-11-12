package id.babaparfume.kasir

import android.Manifest
import android.annotation.SuppressLint
import android.app.ActivityManager
import android.app.AlertDialog
import android.app.DownloadManager
import android.content.ActivityNotFoundException
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Environment
import android.provider.MediaStore
import android.view.View
import android.view.ViewGroup
import android.webkit.CookieManager
import android.webkit.GeolocationPermissions
import android.webkit.PermissionRequest
import android.webkit.URLUtil
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.FrameLayout
import android.widget.ProgressBar
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import java.io.File

/**
 * Layar utama: menampilkan ERP usaha (alamat dari Pengaturan, langsung ke POS) di WebView, plus
 * jembatan printer Bluetooth `window.KopiDripPrinter` (lihat PrinterBridge).
 * Seluruh fitur & tampilan tetap dari website — APK tidak menyimpan logika ERP.
 */
class MainActivity : AppCompatActivity() {

    private lateinit var webView: WebView
    private lateinit var progress: ProgressBar
    private lateinit var bridge: PrinterBridge
    private lateinit var prefs: AppPrefs

    /** Alamat ERP yang sedang dimuat — kalau diganti di Pengaturan, halaman dimuat ulang. */
    private var baseUrlTermuat: String? = null

    // Upload file (foto bukti transfer, dll)
    private var fileCallback: ValueCallback<Array<Uri>>? = null
    private var fotoKameraUri: Uri? = null

    // Izin yang diminta halaman (kamera absensi wajah, lokasi absensi)
    private var permintaanKamera: PermissionRequest? = null
    private var permintaanLokasi: Pair<String, GeolocationPermissions.Callback>? = null

    private val pilihFile = registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { hasil ->
        val cb = fileCallback ?: return@registerForActivityResult
        fileCallback = null
        val data = hasil.data
        val uris: Array<Uri>? = when {
            hasil.resultCode != RESULT_OK -> null
            data?.clipData != null -> Array(data.clipData!!.itemCount) { data.clipData!!.getItemAt(it).uri }
            data?.data != null -> arrayOf(data.data!!)
            fotoKameraUri != null -> arrayOf(fotoKameraUri!!) // hasil kamera tidak mengembalikan data
            else -> null
        }
        cb.onReceiveValue(uris)
    }

    private val izinKamera = registerForActivityResult(ActivityResultContracts.RequestPermission()) { ok ->
        val req = permintaanKamera ?: return@registerForActivityResult
        permintaanKamera = null
        if (ok) req.grant(req.resources) else req.deny()
    }

    private val izinLokasi = registerForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { hasil ->
        val (origin, cb) = permintaanLokasi ?: return@registerForActivityResult
        permintaanLokasi = null
        cb.invoke(origin, hasil.values.any { it }, false)
    }

    private val izinBluetooth = registerForActivityResult(ActivityResultContracts.RequestPermission()) { }

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        // Build debug saja: izinkan DevTools memeriksa WebView (uji otomatis). APK rilis tidak terpengaruh.
        if ((applicationInfo.flags and android.content.pm.ApplicationInfo.FLAG_DEBUGGABLE) != 0) android.webkit.WebView.setWebContentsDebuggingEnabled(true)
        prefs = AppPrefs(this)

        val root = FrameLayout(this)
        webView = WebView(this)
        progress = ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal).apply {
            isIndeterminate = false
            max = 100
        }
        root.addView(webView, FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT))
        root.addView(progress, FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, 8))
        setContentView(root)

        webView.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
            databaseEnabled = true
            mediaPlaybackRequiresUserGesture = false
            setGeolocationEnabled(true)
            loadWithOverviewMode = true
            useWideViewPort = true
            cacheMode = WebSettings.LOAD_DEFAULT
            // Penanda supaya ERP tahu sedang dibuka dari aplikasi kasir
            userAgentString = "$userAgentString KopiDripKasir/${BuildConfigInfo.versionName(this@MainActivity)} BabaKasir/${BuildConfigInfo.versionName(this@MainActivity)}"
        }
        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true)

        bridge = PrinterBridge(this, webView)
        webView.addJavascriptInterface(bridge, "KopiDripPrinter")

        webView.webViewClient = KasirWebViewClient()
        webView.webChromeClient = KasirChromeClient()
        webView.setDownloadListener { url, userAgent, contentDisposition, mimeType, _ ->
            unduh(url, userAgent, contentDisposition, mimeType)
        }

        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (webView.canGoBack()) webView.goBack() else moveTaskToBack(true)
            }
        })

        mintaIzinBluetooth()

        // Pertama kali dibuka / alamat ERP belum diisi: atur alamat & printer dulu
        if (savedInstanceState == null && (!prefs.sudahSetup || !prefs.sudahAdaAlamat())) {
            startActivity(Intent(this, SettingsActivity::class.java))
        }

        if (savedInstanceState != null) {
            webView.restoreState(savedInstanceState)
            baseUrlTermuat = prefs.baseUrl
        } else if (prefs.sudahAdaAlamat()) {
            muatAwal()
        }

        // Nama & logo usaha bisa berubah di ERP — perbarui diam-diam tiap aplikasi dibuka
        if (savedInstanceState == null) {
            Thread {
                if (IdentitasUsaha.perbarui(prefs)) runOnUiThread { tampilkanUsaha() }
            }.start()
        }
    }

    override fun onResume() {
        super.onResume()
        // Alamat ERP baru diisi / diganti di Pengaturan → muat ulang dari halaman POS
        if (prefs.sudahAdaAlamat() && baseUrlTermuat != prefs.baseUrl) muatAwal()
        tampilkanUsaha()
    }

    /** Nama & logo usaha di daftar aplikasi terbuka (recent apps). */
    @Suppress("DEPRECATION")
    private fun tampilkanUsaha() {
        setTaskDescription(ActivityManager.TaskDescription(prefs.namaTampil(), IdentitasUsaha.logo(prefs)))
    }

    override fun onPause() {
        super.onPause()
        CookieManager.getInstance().flush()
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        webView.saveState(outState)
    }

    private fun muatAwal() {
        baseUrlTermuat = prefs.baseUrl
        webView.loadUrl(prefs.urlAwal())
    }

    private fun mintaIzinBluetooth() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S &&
            ContextCompat.checkSelfPermission(this, Manifest.permission.BLUETOOTH_CONNECT) != PackageManager.PERMISSION_GRANTED
        ) {
            izinBluetooth.launch(Manifest.permission.BLUETOOTH_CONNECT)
        }
    }

    private fun hostIzin(url: String?): Boolean {
        val host = url?.let { Uri.parse(it).host } ?: return false
        return host.equals(prefs.baseHost(), ignoreCase = true)
    }

    private fun unduh(url: String, userAgent: String, contentDisposition: String?, mimeType: String?) {
        try {
            val nama = URLUtil.guessFileName(url, contentDisposition, mimeType)
            val req = DownloadManager.Request(Uri.parse(url)).apply {
                setMimeType(mimeType)
                addRequestHeader("Cookie", CookieManager.getInstance().getCookie(url))
                addRequestHeader("User-Agent", userAgent)
                setTitle(nama)
                setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
                setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, nama)
            }
            (getSystemService(DOWNLOAD_SERVICE) as DownloadManager).enqueue(req)
            Toast.makeText(this, "Mengunduh $nama…", Toast.LENGTH_SHORT).show()
        } catch (e: Exception) {
            Toast.makeText(this, "Gagal mengunduh: ${e.message}", Toast.LENGTH_LONG).show()
        }
    }

    private fun tampilkanGagalMuat(pesan: String) {
        AlertDialog.Builder(this)
            .setTitle("ERP tidak bisa dibuka")
            .setMessage("$pesan\n\nAlamat: ${prefs.baseUrl}\nPeriksa koneksi internet tablet.")
            .setPositiveButton("Coba Lagi") { _, _ -> webView.reload() }
            .setNeutralButton("Pengaturan") { _, _ -> startActivity(Intent(this, SettingsActivity::class.java)) }
            .setCancelable(false)
            .show()
    }

    private inner class KasirWebViewClient : WebViewClient() {
        override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
            val url = request.url.toString()
            // Halaman ERP tetap di dalam aplikasi; tautan lain (WhatsApp, Maps, dll) dibuka aplikasi lain
            if (request.url.scheme in listOf("http", "https") && hostIzin(url)) return false
            try {
                startActivity(Intent(Intent.ACTION_VIEW, request.url))
            } catch (_: ActivityNotFoundException) {
                Toast.makeText(this@MainActivity, "Tidak ada aplikasi untuk membuka tautan ini.", Toast.LENGTH_SHORT).show()
            }
            return true
        }

        override fun onPageStarted(view: WebView, url: String?, favicon: Bitmap?) {
            bridge.hostSaatIni = url?.let { Uri.parse(it).host }
            progress.visibility = View.VISIBLE
        }

        override fun onPageFinished(view: WebView, url: String?) {
            bridge.hostSaatIni = url?.let { Uri.parse(it).host }
            progress.visibility = View.GONE
        }

        override fun onReceivedError(view: WebView, request: WebResourceRequest, error: WebResourceError) {
            if (request.isForMainFrame) tampilkanGagalMuat(error.description?.toString() ?: "Gagal memuat halaman.")
        }
    }

    private inner class KasirChromeClient : WebChromeClient() {
        override fun onProgressChanged(view: WebView, newProgress: Int) {
            progress.progress = newProgress
        }

        // Absensi wajah (getUserMedia)
        override fun onPermissionRequest(request: PermissionRequest) {
            runOnUiThread {
                if (!hostIzin(request.origin.toString())) {
                    request.deny()
                    return@runOnUiThread
                }
                if (ContextCompat.checkSelfPermission(this@MainActivity, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
                    request.grant(request.resources)
                } else {
                    permintaanKamera = request
                    izinKamera.launch(Manifest.permission.CAMERA)
                }
            }
        }

        // Absensi lokasi (radius cabang)
        override fun onGeolocationPermissionsShowPrompt(origin: String, callback: GeolocationPermissions.Callback) {
            if (!hostIzin(origin)) {
                callback.invoke(origin, false, false)
                return
            }
            val sudah = ContextCompat.checkSelfPermission(this@MainActivity, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED
            if (sudah) {
                callback.invoke(origin, true, false)
            } else {
                permintaanLokasi = origin to callback
                izinLokasi.launch(arrayOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION))
            }
        }

        // Upload file / foto (bukti transfer, foto produk, dll)
        override fun onShowFileChooser(
            webView: WebView,
            filePathCallback: ValueCallback<Array<Uri>>,
            fileChooserParams: FileChooserParams,
        ): Boolean {
            fileCallback?.onReceiveValue(null)
            fileCallback = filePathCallback
            fotoKameraUri = null

            val pilih = fileChooserParams.createIntent()
            val kamera = buatIntentKamera()
            val intent = if (fileChooserParams.isCaptureEnabled && kamera != null) {
                kamera
            } else {
                Intent.createChooser(pilih, "Pilih file").apply {
                    if (kamera != null) putExtra(Intent.EXTRA_INITIAL_INTENTS, arrayOf(kamera))
                }
            }
            return try {
                pilihFile.launch(intent)
                true
            } catch (_: ActivityNotFoundException) {
                fileCallback = null
                false
            }
        }
    }

    /** Intent kamera yang menyimpan foto ke cache aplikasi (dibagikan lewat FileProvider). */
    private fun buatIntentKamera(): Intent? {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) != PackageManager.PERMISSION_GRANTED) {
            // Minta izin untuk berikutnya; kali ini cukup pilih dari galeri/file
            izinKamera.launch(Manifest.permission.CAMERA)
            return null
        }
        return try {
            val dir = File(cacheDir, "foto").apply { mkdirs() }
            val file = File.createTempFile("foto_", ".jpg", dir)
            val uri = FileProvider.getUriForFile(this, "$packageName.fileprovider", file)
            fotoKameraUri = uri
            Intent(MediaStore.ACTION_IMAGE_CAPTURE).apply {
                putExtra(MediaStore.EXTRA_OUTPUT, uri)
                addFlags(Intent.FLAG_GRANT_WRITE_URI_PERMISSION or Intent.FLAG_GRANT_READ_URI_PERMISSION)
            }
        } catch (_: Exception) {
            null
        }
    }
}
