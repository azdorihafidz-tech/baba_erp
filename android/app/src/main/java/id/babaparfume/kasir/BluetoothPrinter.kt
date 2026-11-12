package id.babaparfume.kasir

import android.annotation.SuppressLint
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothManager
import android.bluetooth.BluetoothSocket
import android.content.Context
import java.io.IOException
import java.util.UUID

/**
 * Kirim byte mentah ke printer thermal lewat Bluetooth Klasik (SPP).
 * Hampir semua printer thermal (struk & label) mendukung SPP, termasuk yang
 * tidak bisa dipakai Web Bluetooth di Chrome (yang hanya mendukung BLE).
 *
 * Isi data (ESC/POS struk, TSPL label) dibuat oleh ERP — APK hanya
 * meneruskan, supaya format bisa diperbaiki lewat deploy ERP tanpa update APK.
 */
object BluetoothPrinter {

    private val SPP_UUID: UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")
    private const val CHUNK = 512

    /** Satu kunci per printer supaya 2 cetakan ke printer yang sama tidak bertabrakan. */
    private val locks = mutableMapOf<String, Any>()

    private fun lockFor(mac: String): Any = synchronized(locks) { locks.getOrPut(mac) { Any() } }

    fun adapter(context: Context): BluetoothAdapter? =
        (context.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager)?.adapter

    /** Daftar printer yang sudah di-pair di pengaturan Bluetooth Android. */
    @SuppressLint("MissingPermission")
    fun pairedDevices(context: Context): List<BluetoothDevice> =
        adapter(context)?.bondedDevices?.sortedBy { it.name ?: it.address } ?: emptyList()

    /**
     * Kirim data ke printer. Dipanggil dari thread background (blocking).
     * @throws IOException kalau printer tidak bisa dihubungi
     */
    @SuppressLint("MissingPermission")
    fun kirim(context: Context, mac: String, data: ByteArray, jedaChunkMs: Long = 20) {
        val adapter = adapter(context) ?: throw IOException("Bluetooth tidak tersedia di perangkat ini")
        if (!adapter.isEnabled) throw IOException("Bluetooth tablet sedang mati. Nyalakan Bluetooth lalu coba lagi.")

        synchronized(lockFor(mac)) {
            val device = adapter.getRemoteDevice(mac)
            try {
                adapter.cancelDiscovery()
            } catch (_: SecurityException) {
            }

            val socket = sambung(device)
            try {
                val out = socket.outputStream
                var offset = 0
                while (offset < data.size) {
                    val len = minOf(CHUNK, data.size - offset)
                    out.write(data, offset, len)
                    out.flush()
                    offset += len
                    if (jedaChunkMs > 0) Thread.sleep(jedaChunkMs)
                }
                // Beri waktu printer menerima sisa buffer sebelum koneksi ditutup
                Thread.sleep(300)
            } finally {
                try {
                    socket.close()
                } catch (_: IOException) {
                }
            }
        }
    }

    /** Coba beberapa cara koneksi — tiap merek printer kadang butuh cara berbeda. */
    @SuppressLint("MissingPermission")
    private fun sambung(device: BluetoothDevice): BluetoothSocket {
        val percobaan: List<() -> BluetoothSocket> = listOf(
            { device.createRfcommSocketToServiceRecord(SPP_UUID) },
            { device.createInsecureRfcommSocketToServiceRecord(SPP_UUID) },
            {
                val m = device.javaClass.getMethod("createRfcommSocket", Int::class.javaPrimitiveType)
                m.invoke(device, 1) as BluetoothSocket
            },
        )

        var errorTerakhir: Exception? = null
        for (buat in percobaan) {
            var socket: BluetoothSocket? = null
            try {
                socket = buat()
                socket.connect()
                return socket
            } catch (e: Exception) {
                errorTerakhir = e
                try {
                    socket?.close()
                } catch (_: IOException) {
                }
            }
        }
        throw IOException(
            "Tidak bisa terhubung ke printer \"${device.name ?: device.address}\". " +
                "Pastikan printer menyala, kertas terisi, dan dekat dengan tablet.",
            errorTerakhir,
        )
    }
}
