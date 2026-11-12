package id.babaparfume.kasir

import android.content.Context
import android.content.pm.PackageManager
import android.os.Build

/** Versi aplikasi dari PackageManager (tanpa perlu mengaktifkan BuildConfig). */
object BuildConfigInfo {
    fun versionName(context: Context): String = try {
        val info = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            context.packageManager.getPackageInfo(context.packageName, PackageManager.PackageInfoFlags.of(0))
        } else {
            @Suppress("DEPRECATION")
            context.packageManager.getPackageInfo(context.packageName, 0)
        }
        info.versionName ?: "1.0"
    } catch (_: Exception) {
        "1.0"
    }
}
