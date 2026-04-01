import 'package:flutter/material.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter/foundation.dart';
import '../config/build_config.dart';
import '../utils/api_config.dart';

/// Holds all branding data for a single tenant.
/// Fetched from the database via the API.
class TenantBranding {
  final String id;
  final String slug;
  final String appName;
  final String logoPath;
  final String fontFamily;
  final Color themePrimaryColor;
  final Color themeSecondaryColor;
  final Color themeTextMain;
  final Color themeTextMuted;
  final Color themeBgBody;
  final Color themeBgCard;
  final Color themeBorderColor;
  final double cardBorderWidth;
  final String cardShadowStr;

  TenantBranding({
    required this.id,
    required this.slug,
    required this.appName,
    required this.logoPath,
    required this.fontFamily,
    required this.themePrimaryColor,
    required this.themeSecondaryColor,
    required this.themeTextMain,
    required this.themeTextMuted,
    required this.themeBgBody,
    required this.themeBgCard,
    required this.themeBorderColor,
    required this.cardBorderWidth,
    required this.cardShadowStr,
  });

  // Dynamic tenants list
  static List<TenantBranding> tenants = [];

  /// Parse hex color (e.g., "#dc2626") to Flutter Color object.
  static Color _parseColor(String? hexColor, Color fallback) {
    if (hexColor == null || hexColor.isEmpty) return fallback;
    hexColor = hexColor.toUpperCase().replaceAll("#", "");
    if (hexColor.length == 6) {
      hexColor = "FF$hexColor";
    }
    return Color(int.tryParse(hexColor, radix: 16) ?? fallback.value);
  }
  
  static double _parseDouble(String? val, double fallback) {
    if (val == null || val.isEmpty) return fallback;
    // Strip "px" if present
    String cleanVal = val.toLowerCase().replaceAll('px', '').trim();
    return double.tryParse(cleanVal) ?? fallback;
  }

  // Parse CSS-like box shadow into Flutter BoxShadow
  List<BoxShadow> get cardShadow {
    if (cardShadowStr == 'none' || cardShadowStr.isEmpty) {
      return [];
    }
    // Simple basic parsing. Real parsing could get complex.
    // E.g., '0px 4px 6px rgba(0, 0, 0, 0.1)' -> simplified to a fallback if complex
    // If it has "rgba" or "black", we try to approximate
    return [
      BoxShadow(
        color: Color(0x1A000000), // Default generic subtle shadow
        blurRadius: 16,
        offset: Offset(0, 4),
        spreadRadius: 0,
      )
    ];
  }

  factory TenantBranding.fromJson(Map<String, dynamic> json) {
    final rawId = (json['id'] ?? json['tenant_id'] ?? BuildConfig.tenantId)
        .toString()
        .trim();
    final rawSlug = (json['slug'] ?? json['tenant_slug'] ?? rawId)
        .toString()
        .trim();
    final resolvedAppName = (json['appName'] ??
            json['tenant_name'] ??
            (BuildConfig.hasTenantId ? BuildConfig.appName : 'Bank App'))
        .toString()
        .trim();

    return TenantBranding(
      id: rawId.isEmpty ? 'default' : rawId,
      slug: rawSlug.isEmpty ? 'default' : rawSlug,
      appName: resolvedAppName.isEmpty ? 'Bank App' : resolvedAppName,
      logoPath: ApiConfig.resolveAssetUrl((json['logo_path'] ?? '').toString()),
      fontFamily: json['font_family'] ?? 'Inter',
      themePrimaryColor: _parseColor(json['theme_primary_color'], Color(0xFF1D4ED8)),
      themeSecondaryColor: _parseColor(json['theme_secondary_color'], Color(0xFF1E40AF)),
      themeTextMain: _parseColor(json['theme_text_main'], Color(0xFF0F172A)),
      themeTextMuted: _parseColor(json['theme_text_muted'], Color(0xFF64748B)),
      themeBgBody: _parseColor(json['theme_bg_body'], Color(0xFFF8FAFC)),
      themeBgCard: _parseColor(json['theme_bg_card'], Color(0xFFFFFFFF)),
      themeBorderColor: _parseColor(json['theme_border_color'], Color(0xFFE2E8F0)),
      cardBorderWidth: _parseDouble(json['card_border_width']?.toString(), 0.0),
      cardShadowStr: json['card_shadow'] ?? 'none',
    );
  }

  /// Default fallback in case the API fails or no tenants are active
  static TenantBranding defaultTenant = TenantBranding(
    id: BuildConfig.hasTenantId ? BuildConfig.tenantId : 'default',
    slug: BuildConfig.hasTenantId ? BuildConfig.tenantId : 'default',
    appName: BuildConfig.appName,
    logoPath: '',
    fontFamily: 'Inter',
    themePrimaryColor: Color(0xFF1D4ED8),
    themeSecondaryColor: Color(0xFF1E40AF),
    themeTextMain: Color(0xFF0F172A),
    themeTextMuted: Color(0xFF64748B),
    themeBgBody: Color(0xFFF8FAFC),
    themeBgCard: Color(0xFFFFFFFF),
    themeBorderColor: Color(0xFFE2E8F0),
    cardBorderWidth: 0.0,
    cardShadowStr: 'none',
  );

  /// Fetch dynamic tenants from API
  static Future<void> loadTenants() async {
    try {
      final url = Uri.parse(ApiConfig.getUrl('api_get_tenants.php'));
      final response = await http.get(url).timeout(Duration(seconds: 10));

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true) {
          final List dynamicList = data['data'];
          tenants = dynamicList.map((j) => TenantBranding.fromJson(j)).toList();
          
          if (tenants.isEmpty) {
             tenants = [defaultTenant];
          }
        }
      }
    } catch (e) {
      debugPrint('Error loading tenants: $e');
      if (tenants.isEmpty) {
         tenants = [defaultTenant];
      }
    }
  }

  static String currentPlatformHint() {
    if (kIsWeb) {
      return 'web';
    }

    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return 'android';
      case TargetPlatform.iOS:
        return 'ios';
      case TargetPlatform.macOS:
        return 'macos';
      case TargetPlatform.windows:
        return 'windows';
      case TargetPlatform.linux:
        return 'linux';
      case TargetPlatform.fuchsia:
        return 'fuchsia';
    }
  }

  static String currentDeviceLabel() {
    if (kIsWeb) {
      return 'web-browser';
    }

    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return 'android-device';
      case TargetPlatform.iOS:
        return 'ios-device';
      case TargetPlatform.macOS:
        return 'macos-device';
      case TargetPlatform.windows:
        return 'windows-device';
      case TargetPlatform.linux:
        return 'linux-device';
      case TargetPlatform.fuchsia:
        return 'fuchsia-device';
    }
  }

  static Future<TenantBranding?> identifyInstall({String tenantHint = ''}) async {
    try {
      final response = await http
          .post(
            Uri.parse(ApiConfig.getUrl('api_identify_install.php')),
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-App-Platform': currentPlatformHint(),
            },
            body: jsonEncode({
              'platform': currentPlatformHint(),
              'device_label': currentDeviceLabel(),
              if (tenantHint.trim().isNotEmpty) 'tenant_hint': tenantHint.trim(),
            }),
          )
          .timeout(const Duration(seconds: 10));

      if (response.statusCode != 200) {
        return null;
      }

      final data = json.decode(response.body);
      if (data is! Map<String, dynamic> || data['success'] != true || data['tenant'] is! Map) {
        return null;
      }

      final tenant = TenantBranding.fromJson(Map<String, dynamic>.from(data['tenant'] as Map));
      final existingIndex = tenants.indexWhere((item) =>
          item.slug.toLowerCase() == tenant.slug.toLowerCase() ||
          item.id.toLowerCase() == tenant.id.toLowerCase());
      if (existingIndex >= 0) {
        tenants[existingIndex] = tenant;
      } else {
        tenants = [...tenants, tenant];
      }

      return tenant;
    } catch (e) {
      debugPrint('Error identifying install tenant: $e');
      return null;
    }
  }

  static TenantBranding? fromTenantId(String id) {
    final normalized = id.trim().toLowerCase();
    if (normalized.isEmpty) {
      return null;
    }

    try {
      return tenants.firstWhere((t) =>
          t.slug.toLowerCase() == normalized || t.id.toLowerCase() == normalized);
    } catch (_) {
      return null;
    }
  }
}


