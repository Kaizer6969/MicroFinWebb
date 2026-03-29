import 'package:flutter/material.dart';
import 'main.dart';

/// Static color constants are removed in favor of strict DB-driven colors.
/// All colors MUST come from activeTenant.value.
class AppColors {
  AppColors._();

  // ─── Surface & Background ───────────────────────────────────────────────────
  static Color get bg => activeTenant.value.themeBgBody;
  static Color get card => activeTenant.value.themeBgCard;

  // ─── Text ───────────────────────────────────────────────────────────────────
  static Color get textMain => activeTenant.value.themeTextMain;
  static Color get textMuted => activeTenant.value.themeTextMuted;

  // ─── Brand Colors ───────────────────────────────────────────────────────────
  static Color get primary => activeTenant.value.themePrimaryColor;
  static Color get secondary => activeTenant.value.themeSecondaryColor;

  // ─── Semantic / Utility ─────────────────────────────────────────────────────
  /// Error / destructive red — used for interest amounts, danger states, etc.
  static const Color error = Color(0xFFEF4444);
  /// Secondary text / muted labels
  static const Color textSecondary = Color(0xFF6B7280);
  /// Thin divider lines inside cards
  static const Color divider = Color(0xFFF3F4F6);

  // ─── Border ─────────────────────────────────────────────────────────────────
  static Color get border => activeTenant.value.themeBorderColor;
  
  // ─── Shadows & Borders ──────────────────────────────────────────────────────
  static List<BoxShadow> get cardShadow => activeTenant.value.cardShadow;
  static double get cardBorderWidth => activeTenant.value.cardBorderWidth;
}

/// Formatting helpers for the app
class AppFormat {
  AppFormat._();

  static String peso(double amount) {
    final parts = amount.toStringAsFixed(2).split('.');
    final intPart = parts[0];
    final decPart = parts[1];
    final buffer = StringBuffer();
    int count = 0;
    for (int i = intPart.length - 1; i >= 0; i--) {
      if (count > 0 && count % 3 == 0) buffer.write(',');
      buffer.write(intPart[i]);
      count++;
    }
    return '₱${buffer.toString().split('').reversed.join()}.$decPart';
  }

  static String pesoCompact(double amount) {
    if (amount >= 1000000) {
      return '₱${(amount / 1000000).toStringAsFixed(1)}M';
    } else if (amount >= 1000) {
      return '₱${(amount / 1000).toStringAsFixed(1)}K';
    }
    return peso(amount);
  }
}


