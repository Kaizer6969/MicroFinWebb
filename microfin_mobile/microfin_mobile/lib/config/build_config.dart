class BuildConfig {
  static const String tenantId = String.fromEnvironment('TENANT_ID');
  static const String appName = String.fromEnvironment(
    'APP_NAME',
    defaultValue: 'Bank App',
  );

  static bool get hasTenantId => tenantId.trim().isNotEmpty;
}
