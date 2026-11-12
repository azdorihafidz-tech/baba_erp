package id.babaparfume.kasir

import android.Manifest
import android.annotation.SuppressLint
import android.bluetooth.BluetoothDevice
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Typeface
import android.os.Build
import android.os.Bundle
import android.provider.Settings
import android.text.InputType
import android.view.Gravity
import android.view.View
import android.view.ViewGroup
import android.widget.AdapterView
import android.widget.ArrayAdapter
import android.widget.Button
import android.widget.EditText
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.RadioButton
import android.widget.RadioGroup
import android.widget.ScrollView
import android.widget.Spinner
import android.widget.Switch
import android.widget.TextView
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import java.util.concurrent.Executors

/**
 * Pengaturan per tablet: alamat ERP, printer struk, printer label (+ bahasa
 * TSPL/ESC-POS), cetak otomatis, tes cetak & tes laci. Dibuat tanpa XML
 * layout supaya proyek tetap kecil.
 */
@SuppressLint("UseSwitchCompatOrMaterialCode")
class SettingsActivity : AppCompatActivity() {

    private lateinit var prefs: AppPrefs
    private val executor = Executors.newSingleThreadExecutor()

    private lateinit var isi: LinearLayout
    private lateinit var spinnerStruk: Spinner
    private lateinit var spinnerLabel: Spinner
    private lateinit var inputAlamat: EditText
    private lateinit var logoUsaha: ImageView
    private lateinit var namaUsaha: TextView
    private var perangkat: List<BluetoothDevice> = emptyList()

    private val izinBluetooth = registerForActivityResult(ActivityResultContracts.RequestPermission()) { ok ->
        if (ok) muatPerangkat() else pesan("Izin Bluetooth ditolak — printer tidak bisa dipilih.")
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        prefs = AppPrefs(this)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)

        isi = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(dp(20), dp(12), dp(20), dp(32))
        }
        setContentView(ScrollView(this).apply { addView(isi) })

        // Tombol kembali HP: tanpa alamat ERP aplikasi tidak bisa dipakai → tutup aplikasi
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (prefs.sudahAdaAlamat()) finish() else finishAffinity()
            }
        })

        bagianUsaha()
        bagianAlamat()
        bagianStruk()
        bagianLabel()
        bagianOtomatis()

        judul("Bluetooth")
        teksKecil("Printer harus di-pair dulu di pengaturan Bluetooth tablet (PIN biasanya 0000 atau 1234), lalu kembali ke sini.")
        tombol("Buka Pengaturan Bluetooth Tablet") { startActivity(Intent(Settings.ACTION_BLUETOOTH_SETTINGS)) }
        tombol("Muat Ulang Daftar Printer") { cekIzinLaluMuat() }

        judul("Selesai")
        tombol("Selesai — Buka ERP") { if (bolehTutup()) finish() }
        teksKecil("Pengaturan ini bisa dibuka lagi kapan saja dari menu \"Pengaturan Printer Aplikasi\" di sidebar ERP.")

        teksKecil("Versi aplikasi ${BuildConfigInfo.versionName(this)}")
    }

    override fun onPause() {
        super.onPause()
        // Pengaturan sudah pernah dibuka → berikutnya aplikasi langsung ke ERP
        prefs.sudahSetup = true
    }

    override fun onResume() {
        super.onResume()
        cekIzinLaluMuat()
    }

    override fun onSupportNavigateUp(): Boolean {
        if (bolehTutup()) finish()
        return true
    }

    // ---------------------------------------------------------------- bagian

    /** Kepala halaman: logo & nama usaha dari ERP yang terhubung. */
    private fun bagianUsaha() {
        val baris = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            setPadding(0, dp(8), 0, dp(4))
        }
        logoUsaha = ImageView(this).apply { scaleType = ImageView.ScaleType.FIT_CENTER }
        baris.addView(logoUsaha, LinearLayout.LayoutParams(dp(56), dp(56)).apply { marginEnd = dp(14) })
        namaUsaha = TextView(this).apply { textSize = 18f; setTypeface(typeface, Typeface.BOLD) }
        baris.addView(namaUsaha, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f))
        isi.addView(baris)
        tampilkanUsaha()
    }

    private fun tampilkanUsaha() {
        val logo = IdentitasUsaha.logo(prefs)
        if (logo != null) logoUsaha.setImageBitmap(logo) else logoUsaha.setImageResource(R.mipmap.ic_launcher)
        namaUsaha.text = when {
            prefs.identitasBerlaku() -> "Terhubung ke:\n${prefs.usahaNama}"
            prefs.sudahAdaAlamat() -> "Terhubung ke:\n${prefs.baseUrl}"
            else -> "Belum terhubung ke ERP"
        }
    }

    private fun bagianAlamat() {
        judul("Alamat ERP")
        teksKecil("Alamat ERP BABA sudah terisi (erp.babaparfume.id). Tekan Hubungkan — nama & logo BABA diambil otomatis dari ERP.")

        inputAlamat = EditText(this).apply {
            hint = "https://erp.babaparfume.id"
            inputType = InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_URI
            setSingleLine()
            setText(prefs.baseUrl.ifEmpty { "https://erp.babaparfume.id" })
        }
        isi.addView(inputAlamat)
        tombol("Hubungkan") { hubungkan(inputAlamat.text.toString()) }

        // Alamat yang pernah dipakai (mis. demo & go-live) — sekali tekan untuk pindah
        val riwayat = prefs.riwayatAlamat.filter { it != prefs.baseUrl }
        if (riwayat.isNotEmpty()) {
            teksKecil("Pernah dipakai:")
            riwayat.forEach { url -> tombol(url) { inputAlamat.setText(url); hubungkan(url) } }
        }
    }

    private fun hubungkan(alamat: String) {
        if (alamat.isBlank()) {
            pesan("Isi alamat ERP dulu.")
            return
        }
        pesan("Menghubungkan…")
        executor.execute {
            val hasil = try {
                "Terhubung ke ${IdentitasUsaha.hubungkan(prefs, alamat)}."
            } catch (e: Exception) {
                e.message ?: "Gagal terhubung."
            }
            runOnUiThread {
                inputAlamat.setText(prefs.baseUrl)
                tampilkanUsaha()
                pesan(hasil)
            }
        }
    }

    /** Pengaturan tidak bisa ditutup sebelum alamat ERP diisi. */
    private fun bolehTutup(): Boolean {
        if (prefs.sudahAdaAlamat()) return true
        pesan("Isi alamat ERP lalu tekan Hubungkan dulu.")
        return false
    }

    private fun bagianStruk() {
        judul("Printer Struk")
        teksKecil("Mis. IWARE C58BT (58 mm). Laci kasir juga dibuka lewat printer ini.")
        spinnerStruk = Spinner(this)
        isi.addView(spinnerStruk)
        baris(
            tombolKecil("Tes Cetak Struk") { kirimTes(prefs.strukMac, "struk") { TesCetak.struk(prefs.namaTampil()) } },
            tombolKecil("Tes Buka Laci") { kirimTes(prefs.strukMac, "struk (laci)") { PrinterBridge.BUKA_LACI } },
        )
    }

    private fun bagianLabel() {
        judul("Printer Label")
        teksKecil("Mis. Blueprint ECO80BT. Ukuran stiker diatur di ERP: Cabang → Edit → Label Produk (opsional; toko parfum biasanya cukup printer struk).")
        spinnerLabel = Spinner(this)
        isi.addView(spinnerLabel)

        val grup = RadioGroup(this).apply { orientation = RadioGroup.HORIZONTAL }
        val tspl = RadioButton(this).apply { text = "TSPL (printer label)"; id = View.generateViewId() }
        val escpos = RadioButton(this).apply { text = "ESC/POS"; id = View.generateViewId() }
        grup.addView(tspl); grup.addView(escpos)
        if (prefs.labelProtokol == AppPrefs.PROTOKOL_ESCPOS) escpos.isChecked = true else tspl.isChecked = true
        grup.setOnCheckedChangeListener { _, id ->
            prefs.labelProtokol = if (id == escpos.id) AppPrefs.PROTOKOL_ESCPOS else AppPrefs.PROTOKOL_TSPL
        }
        isi.addView(grup)
        teksKecil("Kalau hasil label berantakan/kosong, coba ganti bahasa printer lalu tes lagi.")

        tombol("Tes Cetak Label (40×30 mm)") {
            kirimTes(prefs.labelMac, "label") {
                if (prefs.labelProtokol == AppPrefs.PROTOKOL_ESCPOS) TesCetak.labelEscpos(prefs.namaTampil())
                else TesCetak.labelTspl(prefs.namaTampil()).toByteArray(Charsets.US_ASCII)
            }
        }
    }

    private fun bagianOtomatis() {
        judul("Cetak Otomatis")
        isi.addView(Switch(this).apply {
            text = "Cetak struk otomatis setelah bayar"
            isChecked = prefs.autoStruk
            setOnCheckedChangeListener { _, v -> prefs.autoStruk = v }
        })
        isi.addView(Switch(this).apply {
            text = "Cetak label produk otomatis"
            isChecked = prefs.autoLabel
            setOnCheckedChangeListener { _, v -> prefs.autoLabel = v }
        })
        teksKecil("Kalau dimatikan, cetak tetap bisa lewat tombol di layar ERP.")
    }

    // ------------------------------------------------------------- printer

    private fun cekIzinLaluMuat() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S &&
            ContextCompat.checkSelfPermission(this, Manifest.permission.BLUETOOTH_CONNECT) != PackageManager.PERMISSION_GRANTED
        ) {
            izinBluetooth.launch(Manifest.permission.BLUETOOTH_CONNECT)
        } else {
            muatPerangkat()
        }
    }

    @SuppressLint("MissingPermission")
    private fun muatPerangkat() {
        perangkat = try {
            BluetoothPrinter.pairedDevices(this)
        } catch (_: SecurityException) {
            emptyList()
        }
        val label = listOf("— Belum dipilih —") + perangkat.map { "${it.name ?: "(tanpa nama)"}  ·  ${it.address}" }

        isiSpinner(spinnerStruk, label, prefs.strukMac) { d ->
            prefs.strukMac = d?.address
            prefs.strukNama = d?.name
        }
        isiSpinner(spinnerLabel, label, prefs.labelMac) { d ->
            prefs.labelMac = d?.address
            prefs.labelNama = d?.name
        }
        if (perangkat.isEmpty()) pesan("Belum ada printer yang di-pair. Pair dulu di pengaturan Bluetooth tablet.")
    }

    @SuppressLint("MissingPermission")
    private fun isiSpinner(spinner: Spinner, label: List<String>, macTerpilih: String?, simpan: (BluetoothDevice?) -> Unit) {
        spinner.adapter = ArrayAdapter(this, android.R.layout.simple_spinner_dropdown_item, label)
        val idx = perangkat.indexOfFirst { it.address == macTerpilih }
        spinner.setSelection(if (idx >= 0) idx + 1 else 0, false)
        spinner.onItemSelectedListener = object : AdapterView.OnItemSelectedListener {
            override fun onItemSelected(parent: AdapterView<*>?, view: View?, position: Int, id: Long) {
                // Daftar kosong (Bluetooth mati / izin belum ada) — jangan hapus printer yang tersimpan
                if (perangkat.isEmpty()) return
                simpan(if (position == 0) null else perangkat[position - 1])
            }

            override fun onNothingSelected(parent: AdapterView<*>?) {}
        }
    }

    private fun kirimTes(mac: String?, nama: String, data: () -> ByteArray) {
        if (mac.isNullOrBlank()) {
            pesan("Pilih printer $nama dulu.")
            return
        }
        pesan("Mengirim ke printer $nama…")
        executor.execute {
            val hasil = try {
                BluetoothPrinter.kirim(this, mac, data(), if (nama == "label") 40 else 20)
                "Berhasil dikirim ke printer $nama."
            } catch (e: Exception) {
                e.message ?: "Gagal mengirim ke printer $nama."
            }
            runOnUiThread { pesan(hasil) }
        }
    }

    // ---------------------------------------------------------------- util

    private fun dp(v: Int): Int = (v * resources.displayMetrics.density).toInt()

    private fun judul(t: String) {
        isi.addView(TextView(this).apply {
            text = t
            textSize = 18f
            setTypeface(typeface, Typeface.BOLD)
            setPadding(0, dp(20), 0, dp(6))
        })
    }

    private fun teksKecil(t: String) {
        isi.addView(TextView(this).apply {
            text = t
            textSize = 13f
            setPadding(0, dp(2), 0, dp(6))
        })
    }

    private fun tombol(t: String, aksi: () -> Unit) {
        isi.addView(Button(this).apply {
            text = t
            setOnClickListener { aksi() }
        }, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT))
    }

    private fun tombolKecil(t: String, aksi: () -> Unit): Button = Button(this).apply {
        text = t
        setOnClickListener { aksi() }
    }

    private fun baris(vararg v: View) {
        val row = LinearLayout(this).apply { orientation = LinearLayout.HORIZONTAL }
        v.forEach { row.addView(it, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f)) }
        isi.addView(row)
    }

    private fun pesan(t: String) = Toast.makeText(this, t, Toast.LENGTH_LONG).show()
}
