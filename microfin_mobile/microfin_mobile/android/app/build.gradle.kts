import java.util.Base64

plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

fun readDartDefine(key: String): String? {
    val encoded = project.findProperty("dart-defines") as String? ?: return null

    return encoded
        .split(",")
        .mapNotNull { entry ->
            runCatching {
                String(Base64.getDecoder().decode(entry))
            }.getOrNull()
        }
        .mapNotNull { decoded ->
            val separator = decoded.indexOf('=')
            if (separator <= 0) {
                null
            } else {
                decoded.substring(0, separator) to decoded.substring(separator + 1)
            }
        }
        .toMap()[key]
}

fun sanitizeApplicationIdSegment(value: String): String? {
    val normalized = value
        .trim()
        .lowercase()
        .replace(Regex("[^a-z0-9_]"), "_")
        .trim('_')

    if (normalized.isBlank()) {
        return null
    }

    return if (normalized.first().isDigit()) {
        "tenant_$normalized"
    } else {
        normalized
    }
}

val tenantIdDefine = readDartDefine("TENANT_ID").orEmpty()
val appNameDefine = readDartDefine("APP_NAME").orEmpty()
val resolvedAppName = appNameDefine.ifBlank { "Bank App" }
val resolvedApplicationId = sanitizeApplicationIdSegment(tenantIdDefine)
    ?.let { "com.example.microfin_mobile.$it" }
    ?: "com.example.microfin_mobile"

android {
    namespace = "com.example.microfin_mobile"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = resolvedApplicationId
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
        manifestPlaceholders["appName"] = resolvedAppName
    }

    buildTypes {
        release {
            // TODO: Add your own signing config for the release build.
            // Signing with the debug keys for now, so `flutter run --release` works.
            signingConfig = signingConfigs.getByName("debug")
        }
    }
}

flutter {
    source = "../.."
}
