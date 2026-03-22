import 'package:flutter/material.dart';

/// Holds branding data for a single tenant.
class TenantBranding {
  final String slug;
  final String appName;
  final String tagline;
  final Color primaryColor;
  final Color secondaryColor;
  final String emoji;
  final String description;
  final String logo;

  const TenantBranding({
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

  static const TenantBranding fundline = TenantBranding(
    slug: 'fundline',
    appName: 'Fundline Mobile',
    tagline: 'Your trusted lending partner',
    primaryColor: Color(0xFFDC2626),
    secondaryColor: Color(0xFF991B1B),
    emoji: '💳',
    description: 'Personal and Business Loans at low rates',
    logo: 'images/fundline_logo.png',
  );

  static const TenantBranding plaridel = TenantBranding(
    slug: 'plaridel',
    appName: 'PlaridelMFB',
    tagline: 'Banking for every Filipino',
    primaryColor: Color(0xFF1D4ED8),
    secondaryColor: Color(0xFF1E40AF),
    emoji: '🏦',
    description: 'Agricultural and Rural Financing Solutions',
    logo: 'images/plaridel_logo.png',
  );

  static const TenantBranding sacredheart = TenantBranding(
    slug: 'sacredheart',
    appName: 'Sacred Heart Coop',
    tagline: 'Community-driven microfinance',
    primaryColor: Color(0xFF059669),
    secondaryColor: Color(0xFF065F46),
    emoji: '🌿',
    description: 'Cooperative loans for the community',
    logo: 'images/sacred_logo.jpg',
  );

  static const List<TenantBranding> tenants = [fundline, plaridel, sacredheart];

  static TenantBranding? fromTenantId(String id) {
    final normalized = id.toLowerCase();
    try {
      return tenants.firstWhere((t) => t.slug == normalized);
    } catch (_) {
      return null;
    }
  }

  /// Builds branding from API tenant rows while preserving known defaults.
  static TenantBranding fromApiTenant(Map<String, dynamic> row) {
    final slug = (row['tenant_slug'] ?? '').toString().trim().toLowerCase();
    final tenantName = (row['tenant_name'] ?? slug).toString().trim();
    final staticMatch = fromTenantId(slug);
    final primaryFromApi = _parseHexColor(row['primary_color']);
    final secondaryFromApi = _parseHexColor(row['secondary_color']);

    if (staticMatch != null) {
      return TenantBranding(
        slug: staticMatch.slug,
        appName: tenantName.isNotEmpty ? tenantName : staticMatch.appName,
        tagline: staticMatch.tagline,
        primaryColor: primaryFromApi ?? staticMatch.primaryColor,
        secondaryColor: secondaryFromApi ?? staticMatch.secondaryColor,
        emoji: staticMatch.emoji,
        description: staticMatch.description,
        logo: staticMatch.logo,
      );
    }

    const fallbackPrimary = Color(0xFF2563EB);
    const fallbackSecondary = Color(0xFF1E3A8A);
    return TenantBranding(
      slug: slug.isEmpty ? 'tenant' : slug,
      appName: tenantName.isEmpty ? 'Unknown Tenant' : tenantName,
      tagline: 'Active tenant',
      primaryColor: primaryFromApi ?? fallbackPrimary,
      secondaryColor: secondaryFromApi ?? fallbackSecondary,
      emoji: '🏦',
      description: 'Tenant slug: ${slug.isEmpty ? 'n/a' : slug}',
      logo: '',
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
}
