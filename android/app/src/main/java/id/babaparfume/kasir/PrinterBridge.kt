package id.babaparfume.kasir

import android.content.Intent
import android.util.Base64
import android.webkit.JavascriptInterface
import android.webkit.WebView
import android.widget.Toast
import org.json.JSONObject
import java.util.concurrent.Executors

/**
 * Jembatan JS ↔ Android, terdaftar di WebView sebagai `window.KopiDripPrinter`.
 *
 * Dipanggil ERP (POS, label cup, laci). Semua fungsi cetak berjalan di thread
 * background; hasilnya dikirim balik ke halaman lewat
 * `window.KopiDripPrinterResult(callbackId, ok, pesan)`.
 *
 * Keamanan: hanya halaman dari host ERP yang diatur di Pengaturan yang boleh
 * memakai printer (`hostSaatIni` diperbarui MainActivity tiap halaman dimuat).
 */
class PrinterBridge(
    private val activity: MainActivity,
    private val webView: WebView,
) {
    @Volatile
    var hostSaatIni: String? = null

    private val prefs = AppPrefs(activity)
    private val executor = Executors.newSingleThreadExecutor()

    companion object {
        const val VERSI_BRIDGE = 1

        /** ESC p 0 25 250 — pulsa buka laci kasir (sama dengan perintah di ERP). */
        val BUKA_LACI = byteArrayOf(0x1B, 0x70, 0x00, 0x19, 0xFA.toByte())
    }

    private fun diizinkan(): Boolean {
        val host = hostSaatIni ?: return false
        return host.equals(prefs.baseHost(), ignoreCase = true)
    }

    /** Info printer untuk ERP: dipakai menentukan tombol & cetak otomatis. */
    @JavascriptInterface
    fun info(): String {
        if (!diizinkan()) return "{}"
        return JSONObject()
            .put("versi", VERSI_BRIDGE)
            .put("struk", prefs.strukNama ?: JSONObject.NULL)
            .put("label", prefs.labelNama ?: JSONObject.NULL)
            .put("label_protokol", prefs.labelProtokol)
            .put("auto_struk", prefs.autoStruk)
            .put("auto_label", prefs.autoLabel)
            .toString()
    }

    /**
     * @param target "struk" atau "label"
     * @param base64 data printer (ESC/POS atau TSPL) dalam base64
     */
    @JavascriptInterface
    fun print(target: String, base64: String, callbackId: String) {
        if (!diizinkan()) {
            balas(callbackId, false, "Halaman ini tidak diizinkan memakai printer.")
            return
        }
        val mac = if (target == "label") prefs.labelMac else prefs.strukMac
        val namaTarget = if (target == "label") "label" else "struk"
        if (mac.isNullOrBlank()) {
            balas(callbackId, false, "Printer $namaTarget belum dipilih. Buka Pengaturan Printer di aplikasi.")
            return
        }
        val data = try {
            Base64.decode(base64, Base64.DEFAULT)
        } catch (e: IllegalArgumentException) {
            balas(callbackId, false, "Data cetak tidak valid.")
            return
        }
        executor.execute {
            try {
                // Printer label menerima data lebih lambat — beri jeda antar potongan lebih panjang
                BluetoothPrinter.kirim(activity, mac, data, if (target == "label") 40 else 20)
                balas(callbackId, true, "Tercetak di printer $namaTarget.")
            } catch (e: Exception) {
                balas(callbackId, false, e.message ?: "Gagal mencetak.")
            }
        }
    }

    @JavascriptInterface
    fun openDrawer(callbackId: String) {
        if (!diizinkan()) {
            balas(callbackId, false, "Halaman ini tidak diizinkan memakai printer.")
            return
        }
        val mac = prefs.strukMac
        if (mac.isNullOrBlank()) {
            balas(callbackId, false, "Printer struk belum dipilih — laci kasir terhubung ke printer struk.")
            return
        }
        executor.execute {
            try {
                BluetoothPrinter.kirim(activity, mac, BUKA_LACI)
                balas(callbackId, true, "Laci kasir dibuka.")
            } catch (e: Exception) {
                balas(callbackId, false, e.message ?: "Gagal membuka laci.")
            }
        }
    }

    @JavascriptInterface
    fun openSettings() {
        activity.runOnUiThread {
            activity.startActivity(Intent(activity, SettingsActivity::class.java))
        }
    }

    private fun balas(callbackId: String, ok: Boolean, pesan: String) {
        activity.runOnUiThread {
            if (!ok) Toast.makeText(activity, pesan, Toast.LENGTH_LONG).show()
            val js = "window.KopiDripPrinterResult && window.KopiDripPrinterResult(" +
                JSONObject.quote(callbackId) + "," + ok + "," + JSONObject.quote(pesan) + ");"
            webView.evaluateJavascript(js, null)
        }
    }
}
