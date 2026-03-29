import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'models/tenant_branding.dart';
import 'screens/splash_screen.dart';
import 'screens/login_screen.dart';

// Global active tenant — drives all UI theming across the app
final ValueNotifier<TenantBranding> activeTenant =
    ValueNotifier(TenantBranding.fundline);

/// The tenant slug passed via URL (Flutter Web only).
/// e.g. site.php?site=fundline  →  Flutter app URL?tenant=fundline
String? _urlTenantSlug;

void main() {
  WidgetsFlutterBinding.ensureInitialized();

  // ── Web deep-link: read ?tenant= from the browser URL ──────────────────────
  if (kIsWeb) {
    final uri = Uri.base;
    final slug = uri.queryParameters['tenant'];
    if (slug != null && slug.isNotEmpty) {
      final found = TenantBranding.fromTenantId(slug.trim().toLowerCase());
      if (found != null) {
        activeTenant.value = found;
        _urlTenantSlug = found.slug;
      }
    }
  }

  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.light,
    ),
  );
  runApp(const MicroFinApp());
}

class MicroFinApp extends StatelessWidget {
  const MicroFinApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<TenantBranding>(
      valueListenable: activeTenant,
      builder: (context, tenant, _) {
        return MaterialApp(
          title: tenant.appName,
          debugShowCheckedModeBanner: false,
          theme: _buildTheme(tenant),
          // If a tenant slug was injected via URL, skip the picker and go
          // straight to LoginScreen with that company already active.
          home: _urlTenantSlug != null
              ? const LoginScreen()
              : const SplashScreen(),
        );
      },
    );
  }

  ThemeData _buildTheme(TenantBranding tenant) {
    return ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.light(
        primary: tenant.primaryColor,
        secondary: tenant.secondaryColor,
        surface: Colors.white,
        onPrimary: Colors.white,
        onSecondary: Colors.white,
        onSurface: const Color(0xFF0F172A),
      ),
      scaffoldBackgroundColor: const Color(0xFFF8FAFC),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: tenant.primaryColor,
          foregroundColor: Colors.white,
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 15),
          textStyle: const TextStyle(
            fontWeight: FontWeight.w700,
            fontSize: 16,
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: const Color(0xFFF1F5F9),
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: tenant.primaryColor, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFEF4444)),
        ),
        hintStyle: const TextStyle(
          color: Color(0xFF94A3B8),
          fontSize: 15,
          fontWeight: FontWeight.w400,
        ),
      ),
    );
  }
}

