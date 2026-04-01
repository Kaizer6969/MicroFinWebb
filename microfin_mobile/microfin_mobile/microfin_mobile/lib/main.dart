import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'models/tenant_branding.dart';
import 'screens/splash_screen.dart';

// Global active tenant - drives all UI theming across the app.
final ValueNotifier<TenantBranding> activeTenant =
    ValueNotifier(TenantBranding.defaultTenant);

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
  await _restoreCachedTenant();
  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.light,
    ),
  );
  runApp(const MicroFinApp());
}

Future<void> _restoreCachedTenant() async {
  final prefs = await SharedPreferences.getInstance();
  final rawTenant = prefs.getString('locked_tenant_payload')?.trim() ?? '';
  if (rawTenant.isEmpty) {
    return;
  }

  try {
    final decoded = jsonDecode(rawTenant);
    if (decoded is! Map) {
      return;
    }

    final cachedTenant = TenantBranding.fromStoredMap(
      Map<String, dynamic>.from(decoded),
    );
    if (cachedTenant != null) {
      activeTenant.value = cachedTenant;
    }
  } catch (_) {
    // Ignore malformed cached branding and continue with the neutral default.
  }
}

class MicroFinApp extends StatelessWidget {
  const MicroFinApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<TenantBranding>(
      valueListenable: activeTenant,
      builder: (context, tenant, _) {
        SystemChrome.setApplicationSwitcherDescription(
          ApplicationSwitcherDescription(
            label: tenant.appName,
            primaryColor: tenant.primaryColor.value,
          ),
        );

        return MaterialApp(
          title: tenant.appName,
          debugShowCheckedModeBanner: false,
          theme: _buildTheme(tenant),
          home: const SplashScreen(),
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
