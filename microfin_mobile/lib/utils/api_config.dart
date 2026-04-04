class ApiConfig {
  static const String _defaultAppBaseUrl =
      'http://localhost/admin-draft-withmobile/admin-draft';
  static const String _defaultApiPath = '/microfin_mobile/api';
  static const String _apiBaseUrlOverride = String.fromEnvironment(
    'API_BASE_URL',
  );
  static const String _appBaseUrlOverride = String.fromEnvironment(
    'APP_BASE_URL',
  );

  static String get appBaseUrl {
    final raw = _appBaseUrlOverride.isNotEmpty
        ? _appBaseUrlOverride
        : _defaultAppBaseUrl;
    return _stripTrailingSlashes(raw);
  }

  static String get baseUrl {
    final raw = _apiBaseUrlOverride.isNotEmpty
        ? _apiBaseUrlOverride
        : '$appBaseUrl$_defaultApiPath';
    final normalized = _stripTrailingSlashes(raw);
    return normalized.endsWith('/api') ? normalized : '$normalized/api';
  }

  static String getUrl(String endpoint) {
    if (endpoint.startsWith('http')) return endpoint;

    final path = endpoint.startsWith('/') ? endpoint : '/$endpoint';
    return '$baseUrl$path';
  }

  static String _stripTrailingSlashes(String value) {
    return value.trim().replaceFirst(RegExp(r'/+$'), '');
  }
}
