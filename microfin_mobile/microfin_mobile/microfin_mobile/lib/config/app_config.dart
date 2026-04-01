class AppConfig {
  static const String baseHostUrl =
      'https://microfinwebb-production.up.railway.app';
  static const String apiBaseUrl = '$baseHostUrl/microfin_mobile/api';

  static Uri apiUri(String endpoint) => Uri.parse('$apiBaseUrl/$endpoint');

  static String apiUrl(String endpoint) => '$apiBaseUrl/$endpoint';

  static String resolveAssetUrl(String path) {
    final trimmed = path.trim();
    if (trimmed.isEmpty) {
      return '';
    }

    if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
      return trimmed;
    }

    if (trimmed.startsWith('/')) {
      return '$baseHostUrl$trimmed';
    }

    return '$baseHostUrl/$trimmed';
  }
}

