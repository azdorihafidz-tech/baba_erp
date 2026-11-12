package id.babaparfume.kasir

import android.graphics.Bitmap
import android.graphics.BitmapFactory
import org.json.JSONObject
import java.io.ByteArrayOutputStream
import java.io.FileOutputStream
import java.net.HttpURLConnection
import java.net.URL

/**
 * Ambil nama & logo usaha dari ERP (`GET {alamat}/app-info`). Dipakai saat
 * menghubungkan alamat baru di Pengaturan dan diperbarui diam-diam tiap
 * aplikasi dibuka. Semua fungsi di sini BLOCKING — panggil dari thread background.
 */
object IdentitasUsaha {

    private const val MAKS_LOGO = 2 * 1024 * 1024

    /** Lengkapi alamat yang diketik kasir: tambah https:// kalau belum ada, buang "/" di akhir. */
    fun rapikanAlamat(input: String): String {
        val t = input.trim().trimEnd('/')
        if (t.isEmpty()) return t
        return if (t.startsWith("http://") || t.startsWith("https://")) t else "https://$t"
    }

    /**
     * Cek alamat, simpan alamat + identitas usaha. Return nama usaha.
     * @throws Exception dengan pesan yang bisa langsung ditampilkan ke kasir
     */
    fun hubungkan(prefs: AppPrefs, alamat: String): String {
        val url = rapikanAlamat(alamat)
        val info = ambilInfo(url)
        prefs.baseUrl = url
        simpan(prefs, url, info)
        return info.optString("nama")
    }

    /** Perbarui identitas untuk alamat yang sedang dipakai (gagal = diam, pakai yang tersimpan). */
    fun perbarui(prefs: AppPrefs): Boolean {
        if (!prefs.sudahAdaAlamat()) return false
        return try {
            simpan(prefs, prefs.baseUrl, ambilInfo(prefs.baseUrl))
            true
        } catch (_: Exception) {
            false
        }
    }

    fun logo(prefs: AppPrefs): Bitmap? =
        if (prefs.identitasBerlaku() && prefs.fileLogo.isFile) BitmapFactory.decodeFile(prefs.fileLogo.path) else null

    // ------------------------------------------------------------------

    private fun ambilInfo(url: String): JSONObject {
        val teks = try {
            String(unduh("$url/app-info", 64 * 1024), Charsets.UTF_8)
        } catch (e: HttpGagal) {
            throw Exception(
                if (e.kode == 404) "Alamat ini tidak dikenali sebagai ERP (atau ERP-nya belum diperbarui)."
                else "ERP membalas kode ${e.kode}. Coba lagi nanti."
            )
        } catch (e: java.net.UnknownHostException) {
            throw Exception("Alamat tidak ditemukan. Periksa ejaan alamat & koneksi internet.")
        } catch (e: java.io.IOException) {
            throw Exception("Tidak bisa terhubung: ${e.message ?: "periksa koneksi internet"}")
        }
        val json = try {
            JSONObject(teks)
        } catch (_: Exception) {
            throw Exception("Alamat ini tidak dikenali sebagai ERP.")
        }
        if (!json.optBoolean("erp") || json.optString("nama").isBlank()) {
            throw Exception("Alamat ini tidak dikenali sebagai ERP.")
        }
        return json
    }

    private fun simpan(prefs: AppPrefs, url: String, info: JSONObject) {
        prefs.usahaNama = info.optString("nama")
        prefs.usahaNamaSingkat = info.optString("nama_singkat").takeIf { it.isNotBlank() && it != "null" }
        prefs.usahaUntukUrl = url

        // Logo gagal diunduh tidak menggagalkan koneksi — aplikasi tetap jalan tanpa logo
        val ikon = info.optString("ikon_url").ifBlank { info.optString("logo_url") }
        try {
            val data = unduh(ikon, MAKS_LOGO)
            val bmp = BitmapFactory.decodeByteArray(data, 0, data.size) ?: throw Exception()
            FileOutputStream(prefs.fileLogo).use { bmp.compress(Bitmap.CompressFormat.PNG, 100, it) }
        } catch (_: Exception) {
            prefs.fileLogo.delete()
        }
    }

    private class HttpGagal(val kode: Int) : java.io.IOException("HTTP $kode")

    private fun unduh(alamat: String, maks: Int): ByteArray {
        val conn = URL(alamat).openConnection() as HttpURLConnection
        try {
            conn.connectTimeout = 10_000
            conn.readTimeout = 15_000
            conn.instanceFollowRedirects = true
            conn.setRequestProperty("Accept", "application/json, image/*")
            conn.setRequestProperty("User-Agent", "KasirPOS-App")
            val kode = conn.responseCode
            if (kode !in 200..299) throw HttpGagal(kode)

            val out = ByteArrayOutputStream()
            conn.inputStream.use { inp ->
                val buf = ByteArray(8192)
                while (true) {
                    val n = inp.read(buf)
                    if (n < 0) break
                    out.write(buf, 0, n)
                    if (out.size() > maks) throw java.io.IOException("Data terlalu besar")
                }
            }
            return out.toByteArray()
        } finally {
            conn.disconnect()
        }
    }
}
