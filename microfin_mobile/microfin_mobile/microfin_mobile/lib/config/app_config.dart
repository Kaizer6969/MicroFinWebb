class AppConfig {
  static const String apiBaseUrl =
      'http://127.0.0.1/Integ/config/Model/Activity3_5PageUp/microfin_mobile/api';

  static Uri apiUri(String endpoint) => Uri.parse('$apiBaseUrl/$endpoint');
}

