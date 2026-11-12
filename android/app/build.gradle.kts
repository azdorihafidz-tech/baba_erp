plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

// Nomor versi naik otomatis tiap build GitHub Actions (GITHUB_RUN_NUMBER),
// supaya APK baru bisa dipasang di atas versi lama tanpa uninstall.
val buildNumber = System.getenv("GITHUB_RUN_NUMBER")?.toIntOrNull() ?: 1

// Kunci tanda tangan tetap — diisi dari GitHub Secrets (lihat
// .github/workflows/android-apk.yml). Tanpa kunci, build release tidak dibuat.
val keystorePath: String? = System.getenv("BABA_KEYSTORE_PATH")

android {
    namespace = "id.babaparfume.kasir"
    compileSdk = 34

    defaultConfig {
        applicationId = "id.babaparfume.kasir"
        minSdk = 26
        targetSdk = 34
        versionCode = buildNumber
        versionName = "1.0.$buildNumber"
    }

    signingConfigs {
        if (keystorePath != null) {
            create("release") {
                storeFile = file(keystorePath)
                storeType = "pkcs12"
                storePassword = System.getenv("BABA_KEYSTORE_PASSWORD")
                keyAlias = System.getenv("BABA_KEY_ALIAS") ?: "babaparfume"
                keyPassword = System.getenv("BABA_KEYSTORE_PASSWORD")
            }
        }
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            if (keystorePath != null) {
                signingConfig = signingConfigs.getByName("release")
            }
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions {
        jvmTarget = "17"
    }
}

dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
}
