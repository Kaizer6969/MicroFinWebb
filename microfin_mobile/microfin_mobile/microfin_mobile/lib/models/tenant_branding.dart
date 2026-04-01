import 'package:flutter/material.dart';
import '../config/app_config.dart';

/// Holds branding data for a single tenant.
class TenantBranding {
  final String tenantId;
  final String slug;
  final String appName;
  final String tagline;
  final Color primaryColor;
  final Color secondaryColor;
  final String emoji;
  final String description;
  final String logo;

  const TenantBranding({
    required this.tenantId,
    required this.slug,
    required this.appName,
    required this.tagline,
    required this.primaryColor,
    required this.secondaryColor,
    required this.emoji,
    required this.description,
    required this.logo,
  });

  Color get primaryLight => primaryColor.withOpacity(0.12);
  Color get primaryVeryLight => primaryColor.withOpacity(0.06);
  Color get primaryExtraLight => primaryColor.withOpacity(0.08);

  static const TenantBranding defaultTenant = TenantBranding(
    tenantId: 'default',
    slug: 'default',
    appName: 'Bank App',
    tagline: 'Preparing your secure banking experience',
    primaryColor: Color(0xFF2563EB),
    secondaryColor: Color(0xFF1E3A8A),
    emoji: '\u{1F3E6}',
    description: 'Secure multi-tenant banking platform',
    logo: '',
  );

  static const TenantBranding fundline = TenantBranding(
    tenantId: 'fundline',
    slug: 'fundline',
    appName: 'Fundline Mobile',
    tagline: 'Your trusted lending partner',
    primaryColor: Color(0xFFDC2626),
    secondaryColor: Color(0xFF991B1B),
    emoji: '\u{1F4B3}',
    description: 'Personal and Business Loans at low rates',
    logo: 'images/fundline_logo.png',
  );

  static const TenantBranding plaridel = TenantBranding(
    tenantId: 'plaridel',
    slug: 'plaridel',
    appName: 'PlaridelMFB',
    tagline: 'Banking for every Filipino',
    primaryColor: Color(0xFF1D4ED8),
    secondaryColor: Color(0xFF1E40AF),
    emoji: '\u{1F3E6}',
    description: 'Agricultural and Rural Financing Solutions',
    logo: 'images/plaridel_logo.png',
  );

  static const TenantBranding sacredheart = TenantBranding(
    tenantId: 'sacredheart',
    slug: 'sacredheart',
    appName: 'Sacred Heart Coop',
    tagline: 'Community-driven microfinance',
    primaryColor: Color(0xFF059669),
    secondaryColor: Color(0xFF065F46),
    emoji: '\u{1F33F}',
    description: 'Cooperative loans for the community',
    logo: 'images/sacred_logo.jpg',
  );

  static List<TenantBranding> tenants = [fundline, plaridel, sacredheart];

  Map<String, dynamic> toStorageMap() {
    return {
      'tenant_id': tenantId,
      'tenant_slug': slug,
      'tenant_name': appName,
      'tagline': tagline,
      'primary_color': _colorToHex(primaryColor),
      'secondary_color': _colorToHex(secondaryColor),
      'emoji': emoji,
      'description': description,
      'logo_path': logo,
    };
  }

  static TenantBranding? fromStoredMap(Map<String, dynamic> row) {
    return fromApiTenant(row);
  }

  static TenantBranding? fromTenantId(String id) {
    final normalized = id.toLowerCase();
    try {
      return tenants.firstWhere(
        (t) =>
            t.slug.toLowerCase() == normalized ||
            t.tenantId.toLowerCase() == normalized,
      );
    } catch (_) {
      return null;
    }
  }

  /// Returns null when API row has no real tenant_id.
  static TenantBranding? fromApiTenant(Map<String, dynamic> row) {
    final tenantId = (row['tenant_id'] ?? row['id'] ?? '').toString().trim();
    if (tenantId.isEmpty) {
      return null;
    }

    final rawSlug =
        (row['tenant_slug'] ?? row['slug'] ?? '').toString().trim().toLowerCase();
    final slug = rawSlug.isEmpty ? tenantId.toLowerCase() : rawSlug;
    final tenantName = (row['tenant_name'] ?? row['appName'] ?? slug)
        .toString()
        .trim();
    final tagline = (row['tagline'] ?? '').toString().trim();
    final emoji = (row['emoji'] ?? '').toString().trim();
    final description = (row['description'] ?? '').toString().trim();
    final staticMatch = fromTenantId(slug);
    final primaryFromApi = _parseHexColor(
      row['primary_color'] ?? row['theme_primary_color'],
    );
    final secondaryFromApi = _parseHexColor(
      row['secondary_color'] ?? row['theme_secondary_color'],
    );
    final logoPath = AppConfig.resolveAssetUrl(
      (row['logo_path'] ?? row['logo'] ?? '').toString(),
    );

    if (staticMatch != null) {
      return TenantBranding(
        tenantId: tenantId,
        slug: staticMatch.slug,
        appName: tenantName.isNotEmpty ? tenantName : staticMatch.appName,
        tagline: tagline.isNotEmpty ? tagline : staticMatch.tagline,
        primaryColor: primaryFromApi ?? staticMatch.primaryColor,
        secondaryColor: secondaryFromApi ?? staticMatch.secondaryColor,
        emoji: emoji.isNotEmpty ? emoji : staticMatch.emoji,
        description: description.isNotEmpty ? description : staticMatch.description,
        logo: logoPath.isNotEmpty ? logoPath : staticMatch.logo,
      );
    }

    const fallbackPrimary = Color(0xFF2563EB);
    const fallbackSecondary = Color(0xFF1E3A8A);
    return TenantBranding(
      tenantId: tenantId,
      slug: slug,
      appName: tenantName.isEmpty ? 'Unknown Tenant' : tenantName,
      tagline: tagline.isEmpty ? 'Your trusted lending partner' : tagline,
      primaryColor: primaryFromApi ?? fallbackPrimary,
      secondaryColor: secondaryFromApi ?? fallbackSecondary,
      emoji: emoji.isEmpty ? '\u{1F3E6}' : emoji,
      description: description.isEmpty ? 'Tenant slug: $slug' : description,
      logo: logoPath,
    );
  }

  static Color? _parseHexColor(dynamic raw) {
    if (raw == null) return null;
    final input = raw.toString().trim().replaceAll('#', '');
    if (input.isEmpty) return null;

    final normalized = input.length == 6 ? 'FF$input' : input;
    if (normalized.length != 8) return null;

    final value = int.tryParse(normalized, radix: 16);
    if (value == null) return null;
    return Color(value);
  }

  static String _colorToHex(Color color) {
    final value = color.value.toRadixString(16).padLeft(8, '0').toUpperCase();
    return '#${value.substring(2)}';
  }
}

