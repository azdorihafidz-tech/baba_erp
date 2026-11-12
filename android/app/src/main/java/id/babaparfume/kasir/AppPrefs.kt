package id.babaparfume.kasir

import android.content.Context
import android.net.Uri
import java.io.File

/**
 * Pengaturan per tablet (disimpan di SharedPreferences, tidak hilang saat
 * update APK). Printer disimpan berdasarkan alamat MAC Bluetooth.
 *
 * Aplikasi dipakai banyak usaha: alamat ERP diketik di Pengaturan, lalu nama
 * & logo usaha diambil dari ERP itu (endpoint /app-info) — tidak ada alamat
 * atau nama usaha yang ditanam di kode.
 */
class AppPrefs(private val context: Context) {

    private val sp = context.getSharedPreferences("baba_kasir", Context.MODE_PRIVATE)

    companion object {
        const val HALAMAN_AWAL = "/penjualan/pos"
        const val NAMA_BAWAAN = "BABA Kasir"
        private const val MAKS_RIWAYAT = 5

        const val PROTOKOL_TSPL = "tspl"
        const val PROTOKOL_ESCPOS = "escpos"
    }

    /** Alamat ERP tanpa garis miring di akhir. Kosong = belum diatur. */
    var baseUrl: String
        get() = (sp.getString("base_url", "") ?: "").trimEnd('/')
        set(v) {
            val url = v.trim().trimEnd('/')
            sp.edit().putString("base_url", url).apply()
            if (url.isNotEmpty()) tambahRiwayat(url)
        }

    fun sudahAdaAlamat(): Boolean = baseUrl.isNotEmpty()

    /** Alamat yang pernah dipakai (terbaru di depan) — untuk pindah demo/go-live dengan cepat. */
    val riwayatAlamat: List<String>
        get() = (sp.getString("riwayat_alamat", "") ?: "").split('\n').filter { it.isNotBlank() }

    private fun tambahRiwayat(url: String) {
        val baru = (listOf(url) + riwayatAlamat.filter { it != url }).take(MAKS_RIWAYAT)
        sp.edit().putString("riwayat_alamat", baru.joinToString("\n")).apply()
    }

    // ---- Identitas usaha (dari ERP /app-info), disimpan untuk tampil offline

    var usahaNama: String?
        get() = sp.getString("usaha_nama", null)
        set(v) = sp.edit().putString("usaha_nama", v).apply()

    var usahaNamaSingkat: String?
        get() = sp.getString("usaha_nama_singkat", null)
        set(v) = sp.edit().putString("usaha_nama_singkat", v).apply()

    /** Alamat ERP yang identitasnya tersimpan — kalau alamat diganti, identitas lama tidak dipakai. */
    var usahaUntukUrl: String?
        get() = sp.getString("usaha_url", null)
        set(v) = sp.edit().putString("usaha_url", v).apply()

    fun identitasBerlaku(): Boolean = usahaUntukUrl == baseUrl && !usahaNama.isNullOrBlank()

    /** Nama untuk ditampilkan: nama singkat usaha → nama usaha → "BABA Kasir". */
    fun namaTampil(): String =
        if (identitasBerlaku()) usahaNamaSingkat?.takeIf { it.isNotBlank() } ?: usahaNama!! else NAMA_BAWAAN

    /** File ikon usaha (PNG dari ERP). */
    val fileLogo: File get() = File(context.filesDir, "logo_usaha.png")

    // ---- Printer

    var strukMac: String?
        get() = sp.getString("struk_mac", null)
        set(v) = sp.edit().putString("struk_mac", v).apply()

    var strukNama: String?
        get() = sp.getString("struk_nama", null)
        set(v) = sp.edit().putString("struk_nama", v).apply()

    var labelMac: String?
        get() = sp.getString("label_mac", null)
        set(v) = sp.edit().putString("label_mac", v).apply()

    var labelNama: String?
        get() = sp.getString("label_nama", null)
        set(v) = sp.edit().putString("label_nama", v).apply()

    var labelProtokol: String
        get() = sp.getString("label_protokol", PROTOKOL_TSPL) ?: PROTOKOL_TSPL
        set(v) = sp.edit().putString("label_protokol", v).apply()

    var autoStruk: Boolean
        get() = sp.getBoolean("auto_struk", true)
        set(v) = sp.edit().putBoolean("auto_struk", v).apply()

    var autoLabel: Boolean
        get() = sp.getBoolean("auto_label", true)
        set(v) = sp.edit().putBoolean("auto_label", v).apply()

    /** false = aplikasi baru pertama kali dibuka → tampilkan Pengaturan dulu. */
    var sudahSetup: Boolean
        get() = sp.getBoolean("sudah_setup", false)
        set(v) = sp.edit().putBoolean("sudah_setup", v).apply()

    /** Host ERP — hanya halaman dari host ini yang boleh memakai printer. */
    fun baseHost(): String? = if (sudahAdaAlamat()) Uri.parse(baseUrl).host else null

    fun urlAwal(): String = baseUrl + HALAMAN_AWAL
}
