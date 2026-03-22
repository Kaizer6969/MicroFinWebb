import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

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
    _loadActiveTenants();
  }

  Future<void> _loadActiveTenants() async {
    if (mounted) {
      setState(() {
        _isLoading = true;
        _error = null;
      });
    }

    try {
      final response = await http.get(
        AppConfig.apiUri('api_active_tenants.php'),
      );

      if (response.statusCode != 200) {
        throw Exception('HTTP ${response.statusCode} while loading tenants');
      }

      final decoded = jsonDecode(response.body);
      if (decoded is! Map<String, dynamic>) {
        throw Exception('Unexpected response format');
      }
      if (decoded['success'] != true) {
        throw Exception(
          (decoded['message'] ?? 'Failed to load tenants').toString(),
        );
      }

      final rawTenants = decoded['tenants'];
      if (rawTenants is! List) {
        throw Exception('Tenants payload is not a list');
      }

      final tenants = rawTenants
          .whereType<Map>()
          .map(
            (row) =>
                TenantBranding.fromApiTenant(Map<String, dynamic>.from(row)),
          )
          .whereType<TenantBranding>()
          .toList();

      if (!mounted) return;
      setState(() {
        _activeTenants = tenants;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString().replaceFirst('Exception: ', '');
      });
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  void _selectTenant(TenantBranding tenant) {
    activeTenant.value = tenant;

    Navigator.of(context).pushReplacement(
      PageRouteBuilder(
        transitionDuration: const Duration(milliseconds: 350),
        pageBuilder: (context, animation, secondaryAnimation) =>
            const LoginScreen(),
        transitionsBuilder: (context, animation, secondaryAnimation, child) =>
            FadeTransition(
              opacity: CurvedAnimation(
                parent: animation,
                curve: Curves.easeOut,
              ),
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
            Container(
              width: double.infinity,
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
              decoration: const BoxDecoration(
                border: Border(
                  bottom: BorderSide(color: Color(0xFF1F2937), width: 1),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Icon(
                        Icons.bug_report_rounded,
                        color: Color(0xFF38BDF8),
                        size: 22,
                      ),
                      const SizedBox(width: 8),
                      const Expanded(
                        child: Text(
                          'MicroFin Debug Launcher',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      IconButton(
                        onPressed: _isLoading ? null : _loadActiveTenants,
                        icon: const Icon(
                          Icons.refresh_rounded,
                          color: Color(0xFF93C5FD),
                        ),
                        tooltip: 'Reload active tenants',
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Source: ${AppConfig.apiBaseUrl}/api_active_tenants.php',
                    style: const TextStyle(
                      color: Color(0xFF94A3B8),
                      fontSize: 12,
                      fontFamily: 'monospace',
                    ),
                  ),
                  const SizedBox(height: 2),
                  const Text(
                    'Showing tenants where status = Active',
                    style: TextStyle(
                      color: Color(0xFF67E8F9),
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
            Expanded(child: _buildBody()),
          ],
        ),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CircularProgressIndicator(color: Color(0xFF38BDF8)),
            SizedBox(height: 10),
            Text(
              'Loading active tenants...',
              style: TextStyle(color: Color(0xFFCBD5E1)),
            ),
          ],
        ),
      );
    }

    if (_error != null) {
      return Center(
        child: Container(
          margin: const EdgeInsets.all(20),
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: const Color(0xFF111827),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFEF4444), width: 1),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Tenant load failed',
                style: TextStyle(
                  color: Color(0xFFFCA5A5),
                  fontWeight: FontWeight.w700,
                  fontSize: 16,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                _error!,
                style: const TextStyle(color: Color(0xFFE2E8F0), fontSize: 13),
              ),
              const SizedBox(height: 14),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _loadActiveTenants,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF2563EB),
                    foregroundColor: Colors.white,
                  ),
                  child: const Text('Retry'),
                ),
              ),
            ],
          ),
        ),
      );
    }

    if (_activeTenants.isEmpty) {
      return const Center(
        child: Text(
          'No active tenant banks found.',
          style: TextStyle(color: Color(0xFFCBD5E1), fontSize: 14),
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
      itemCount: _activeTenants.length,
      separatorBuilder: (context, index) => const SizedBox(height: 10),
      itemBuilder: (context, index) {
        final tenant = _activeTenants[index];
        return _TenantDebugCard(
          tenant: tenant,
          onLaunch: () => _selectTenant(tenant),
        );
      },
    );
  }
}

class _TenantDebugCard extends StatelessWidget {
  final TenantBranding tenant;
  final VoidCallback onLaunch;

  const _TenantDebugCard({required this.tenant, required this.onLaunch});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFF111827),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFF1F2937), width: 1),
        boxShadow: AppColors.cardShadow,
      ),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: tenant.primaryColor.withOpacity(0.14),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Center(
              child: Text(tenant.emoji, style: const TextStyle(fontSize: 24)),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  tenant.appName,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'slug: ${tenant.slug}',
                  style: const TextStyle(
                    color: Color(0xFF94A3B8),
                    fontSize: 12,
                    fontFamily: 'monospace',
                  ),
                ),
                const SizedBox(height: 4),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 7,
                    vertical: 3,
                  ),
                  decoration: BoxDecoration(
                    color: const Color(0xFF052E16),
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(
                      color: const Color(0xFF166534),
                      width: 1,
                    ),
                  ),
                  child: const Text(
                    'ACTIVE',
                    style: TextStyle(
                      color: Color(0xFF86EFAC),
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 0.4,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          ElevatedButton(
            onPressed: onLaunch,
            style: ElevatedButton.styleFrom(
              backgroundColor: tenant.primaryColor,
              foregroundColor: Colors.white,
              elevation: 0,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            ),
            child: const Text('Launch'),
          ),
        ],
      ),
    );
  }
}
