import 'package:flutter/foundation.dart';
import 'dart:io';

class ApiConfig {
  /// Base API URL structure including the directory prefix.
  /// 127.0.0.1 for Web/Chrome.
  /// 10.0.2.2 for Android Emulator.
  /// 192.168.x.x for Physical Devices on the same Wi-Fi.
  
  static String get baseUrl {
    // Production URL via Railway
    return 'https://microfinwebb.up.railway.app/microfin_mobile/api';
  }

  /// Helper to build a full URL for a specific endpoint.
  static String getUrl(String endpoint) {
    // If the endpoint already starts with http, return it as is
    if (endpoint.startsWith('http')) return endpoint;
    
    // Ensure the endpoint starts with / if not already present
    final path = endpoint.startsWith('/') ? endpoint : '/$endpoint';
    return '$baseUrl$path';
  }
}


