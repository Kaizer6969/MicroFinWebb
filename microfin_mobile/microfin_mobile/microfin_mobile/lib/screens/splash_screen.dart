import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config/app_config.dart';
import '../main.dart';
import '../models/tenant_branding.dart';
import '../theme.dart';
import 'login_screen.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  bool _isLoading = true;
  String? _error;
  List<TenantBranding> _activeTenants = [];

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    if (mounted) {
      setState(() {
        _isLoading = true;
        _error = null;
      });
    }

    try {
      final tenants = await _loadActiveTenants();
      final prefs = await SharedPreferences.getInstance();

      String tenantHint = prefs.getString('locked_tenant_id') ?? '';
      if (tenantHint.isEmpty && kIsWeb) {
        tenantHint = Uri.base.queryParameters['tenant']?.trim() ?? '';
      }

      TenantBranding? resolvedTenant;
      if (tenantHint.isNotEmpty) {
        resolvedTenant = TenantBranding.fromTenantId(tenantHint);
      }

      resolvedTenant ??= await _identifyTenant(tenantHint: tenantHint);

      if (resolvedTenant == null && tenants.length == 1) {
        resolvedTenant = tenants.first;
      }

      if (resolvedTenant != null) {
        await _activateTenant(resolvedTenant);
        return;
      }

      if (!mounted) {
        return;
      }

      setState(() {
        _activeTenants = tenants;
        _isLoading = false;
        _error = tenants.isEmpty
            ? 'No active tenant banks are available yet.'
            : null;
      });
    } catch (e) {
      if (!mounted) {
        return;
      }

      setState(() {
        _isLoading = false;
        _error = e.toString().replaceFirst('Exception: ', '');
      });
    }
  }

  Future<List<TenantBranding>> _loadActiveTenants() async {
    final response = await http.get(AppConfig.apiUri('api_get_tenants.php'));
    if (response.statusCode != 200) {
      throw Exception('HTTP ${response.statusCode} while loading tenants');
    }

    final decoded = jsonDecode(response.body);
    if (decoded is! Map<String, dynamic>) {
      throw Exception('Unexpected response format while loading tenants.');
    }
    if (decoded['success'] != true || decoded['data'] is! List) {
      throw Exception((decoded['message'] ?? 'Failed to load tenants').toString());
    }

    final tenants = (decoded['data'] as List)
        .whereType<Map>()
        .map((row) => TenantBranding.fromApiTenant(Map<String, dynamic>.from(row)))
        .whereType<TenantBranding>()
        .toList();

    if (tenants.isNotEmpty) {
      TenantBranding.tenants = tenants;
    }

    return tenants;
  }

  Future<TenantBranding?> _identifyTenant({String tenantHint = ''}) async {
    final response = await http.post(
      Uri.parse(AppConfig.apiUrl('api_identify_install.php')),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-App-Platform': _platformHint(),
      },
      body: jsonEncode({
        'platform': _platformHint(),
        'device_label': _deviceLabel(),
        if (tenantHint.trim().isNotEmpty) 'tenant_hint': tenantHint.trim(),
      }),
    );

    if (response.statusCode != 200) {
      return null;
    }

    final decoded = jsonDecode(response.body);
    if (decoded is! Map<String, dynamic> ||
        decoded['success'] != true ||
        decoded['tenant'] is! Map) {
      return null;
    }

    final tenant = TenantBranding.fromApiTenant(
      Map<String, dynamic>.from(decoded['tenant'] as Map),
    );
    if (tenant != null) {
      final existingIndex = TenantBranding.tenants.indexWhere(
        (item) =>
            item.slug.toLowerCase() == tenant.slug.toLowerCase() ||
            item.tenantId.toLowerCase() == tenant.tenantId.toLowerCase(),
      );
      if (existingIndex >= 0) {
        TenantBranding.tenants[existingIndex] = tenant;
      } else {
        TenantBranding.tenants = [...TenantBranding.tenants, tenant];
      }
    }

    return tenant;
  }

  String _platformHint() {
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

  String _deviceLabel() {
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

  Future<void> _activateTenant(TenantBranding tenant) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('locked_tenant_id', tenant.slug);
    await prefs.setString(
      'locked_tenant_payload',
      jsonEncode(tenant.toStorageMap()),
    );
    activeTenant.value = tenant;

    if (!mounted) {
      return;
    }

    await Future<void>.delayed(const Duration(milliseconds: 250));
    if (!mounted) {
      return;
    }

    Navigator.of(context).pushReplacement(
      PageRouteBuilder(
        transitionDuration: const Duration(milliseconds: 350),
        pageBuilder: (_, __, ___) => const LoginScreen(),
        transitionsBuilder: (_, animation, __, child) => FadeTransition(
          opacity: CurvedAnimation(parent: animation, curve: Curves.easeOut),
          child: child,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0B1020),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: Center(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 24),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        width: 88,
                        height: 88,
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: [Color(0xFF3B82F6), Color(0xFF1D4ED8)],
                          ),
                          borderRadius: BorderRadius.circular(28),
                          boxShadow: [
                            BoxShadow(
                              color: const Color(0xFF1D4ED8).withOpacity(0.35),
                              blurRadius: 18,
                              offset: const Offset(0, 8),
                            ),
                          ],
                        ),
                        child: const Icon(
                          Icons.account_balance_rounded,
                          color: Colors.white,
                          size: 42,
                        ),
                      ),
                      const SizedBox(height: 22),
                      const Text(
                        'Preparing your bank app',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 28,
                          fontWeight: FontWeight.w800,
                          letterSpacing: -0.8,
                        ),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        _isLoading
                            ? 'Matching this installation to your institution.'
                            : _error ??
                                'Select your institution below to continue.',
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: Color(0xFFCBD5E1),
                          fontSize: 14,
                          height: 1.5,
                        ),
                      ),
                      const SizedBox(height: 18),
                      if (_isLoading)
                        const CircularProgressIndicator(
                          color: Color(0xFF60A5FA),
                        ),
                    ],
                  ),
                ),
              ),
            ),
            if (!_isLoading)
              Expanded(
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.fromLTRB(20, 18, 20, 24),
                  decoration: const BoxDecoration(
                    color: AppColors.bg,
                    borderRadius: BorderRadius.vertical(
                      top: Radius.circular(30),
                    ),
                  ),
                  child: _buildBottomPanel(),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildBottomPanel() {
    if (_error != null && _activeTenants.isEmpty) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Unable to identify your institution',
            style: TextStyle(
              color: AppColors.textMain,
              fontSize: 20,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            _error!,
            style: const TextStyle(
              color: AppColors.textMuted,
              fontSize: 14,
              height: 1.5,
            ),
          ),
          const SizedBox(height: 20),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _bootstrap,
              child: const Text('Try again'),
            ),
          ),
        ],
      );
    }

    if (_activeTenants.isEmpty) {
      return const Center(
        child: Text(
          'No institution is available right now.',
          style: TextStyle(color: AppColors.textMuted, fontSize: 14),
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Center(
          child: SizedBox(
            width: 42,
            child: Divider(
              thickness: 4,
              color: AppColors.separatorStrong,
              height: 4,
            ),
          ),
        ),
        const SizedBox(height: 18),
        const Text(
          'Choose your institution',
          style: TextStyle(
            color: AppColors.textMain,
            fontSize: 22,
            fontWeight: FontWeight.w800,
            letterSpacing: -0.5,
          ),
        ),
        const SizedBox(height: 6),
        const Text(
          'We could not match the install automatically, so pick your bank to continue.',
          style: TextStyle(
            color: AppColors.textMuted,
            fontSize: 13,
            height: 1.5,
          ),
        ),
        const SizedBox(height: 18),
        Expanded(
          child: ListView.separated(
            padding: EdgeInsets.zero,
            itemCount: _activeTenants.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, index) {
              final tenant = _activeTenants[index];
              return _TenantCard(
                tenant: tenant,
                onTap: () => _activateTenant(tenant),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _TenantCard extends StatelessWidget {
  final TenantBranding tenant;
  final VoidCallback onTap;

  const _TenantCard({
    required this.tenant,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(18),
      onTap: onTap,
      child: Ink(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.card,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: tenant.primaryColor.withOpacity(0.16)),
          boxShadow: AppColors.cardShadow,
        ),
        child: Row(
          children: [
            Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(
                color: tenant.primaryColor.withOpacity(0.10),
                borderRadius: BorderRadius.circular(14),
              ),
              child: _TenantLogo(tenant: tenant),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    tenant.appName,
                    style: const TextStyle(
                      color: AppColors.textMain,
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    tenant.tagline,
                    style: const TextStyle(
                      color: AppColors.textMuted,
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [tenant.primaryColor, tenant.secondaryColor],
                ),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Icon(
                Icons.arrow_forward_rounded,
                color: Colors.white,
                size: 18,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TenantLogo extends StatelessWidget {
  final TenantBranding tenant;

  const _TenantLogo({required this.tenant});

  @override
  Widget build(BuildContext context) {
    if (tenant.logo.startsWith('http://') || tenant.logo.startsWith('https://')) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(14),
        child: Image.network(
          tenant.logo,
          fit: BoxFit.cover,
          errorBuilder: (_, __, ___) => Center(
            child: Text(tenant.emoji, style: const TextStyle(fontSize: 24)),
          ),
        ),
      );
    }

    if (tenant.logo.isNotEmpty) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(14),
        child: Image.asset(
          tenant.logo,
          fit: BoxFit.cover,
          errorBuilder: (_, __, ___) => Center(
            child: Text(tenant.emoji, style: const TextStyle(fontSize: 24)),
          ),
        ),
      );
    }

    return Center(
      child: Text(tenant.emoji, style: const TextStyle(fontSize: 24)),
    );
  }
}
