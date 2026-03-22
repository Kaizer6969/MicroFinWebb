class AppConfig {
  static const String apiBaseUrl =
      'http://127.0.0.1/admin-draft-withmobile/admin-draft/microfin_mobile/api';

  static Uri apiUri(String endpoint) => Uri.parse('$apiBaseUrl/$endpoint');
}
