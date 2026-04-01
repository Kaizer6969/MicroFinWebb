import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../config/build_config.dart';
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
  bool _hasResolvedTenant = false;
  String? _error;
  List<TenantBranding> _tenants = [];

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    if (!mounted) {
      return;
    }

    setState(() {
      _isLoading = true;
      _hasResolvedTenant = false;
      _error = null;
    });

    try {
      await TenantBranding.loadTenants();
      final tenants = TenantBranding.tenants;
      final prefs = await SharedPreferences.getInstance();

      String tenantHint = prefs.getString('locked_tenant_id')?.trim() ?? '';
      if (tenantHint.isEmpty && BuildConfig.hasTenantId) {
        tenantHint = BuildConfig.tenantId.trim();
      }
      if (tenantHint.isEmpty && kIsWeb) {
        tenantHint = Uri.base.queryParameters['tenant']?.trim() ?? '';
      }

      TenantBranding? resolvedTenant;
      if (tenantHint.isNotEmpty) {
        resolvedTenant = TenantBranding.fromTenantId(tenantHint);
      }

      resolvedTenant ??= await TenantBranding.identifyInstall(
        tenantHint: tenantHint,
      );

      if (resolvedTenant == null && BuildConfig.hasTenantId) {
        resolvedTenant = TenantBranding.defaultTenant;
      }

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
        _isLoading = false;
        _tenants = tenants;
        _error = tenants.isEmpty
            ? 'No active institution is available for this build yet.'
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

  Future<void> _activateTenant(TenantBranding tenant) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('locked_tenant_id', tenant.slug);
    activeTenant.value = tenant;

    if (!mounted) {
      return;
    }

    setState(() {
      _hasResolvedTenant = true;
    });

    await Future<void>.delayed(const Duration(milliseconds: 350));
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
    final tenant = activeTenant.value;
    final primary = tenant.themePrimaryColor;
    final secondary = tenant.themeSecondaryColor;
    final displayName = _hasResolvedTenant
        ? tenant.appName
        : (BuildConfig.hasTenantId ? BuildConfig.appName : 'Bank App');

    return Scaffold(
      backgroundColor: const Color(0xFF081121),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: Center(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 28),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      _TenantMark(
                        tenant: tenant,
                        fallbackLabel: displayName,
                        primary: primary,
                        secondary: secondary,
                      ),
                      const SizedBox(height: 24),
                      Text(
                        displayName,
                        textAlign: TextAlign.center,
                        style: GoogleFonts.outfit(
                          color: Colors.white,
                          fontSize: 32,
                          fontWeight: FontWeight.w800,
                          letterSpacing: -0.8,
                        ),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        _isLoading
                            ? 'Preparing your banking app and matching it to the correct institution.'
                            : _error ??
                                'Select your institution below to continue.',
                        textAlign: TextAlign.center,
                        style: GoogleFonts.inter(
                          color: const Color(0xFFCBD5E1),
                          fontSize: 14,
                          height: 1.55,
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
                    color: Colors.white,
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
    if (_error != null && _tenants.isEmpty) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Unable to prepare this app',
            style: GoogleFonts.outfit(
              color: AppColors.textMain,
              fontSize: 22,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            _error!,
            style: GoogleFonts.inter(
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

    if (_tenants.isEmpty) {
      return Center(
        child: Text(
          'No institution is available right now.',
          style: GoogleFonts.inter(
            color: AppColors.textMuted,
            fontSize: 14,
          ),
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
              color: Color(0xFFE2E8F0),
              height: 4,
            ),
          ),
        ),
        const SizedBox(height: 18),
        Text(
          'Choose your institution',
          style: GoogleFonts.outfit(
            color: AppColors.textMain,
            fontSize: 22,
            fontWeight: FontWeight.w800,
            letterSpacing: -0.4,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          'Automatic identification was not available for this install, so choose your bank to continue.',
          style: GoogleFonts.inter(
            color: AppColors.textMuted,
            fontSize: 13,
            height: 1.5,
          ),
        ),
        const SizedBox(height: 18),
        Expanded(
          child: ListView.separated(
            padding: EdgeInsets.zero,
            itemCount: _tenants.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, index) {
              final tenant = _tenants[index];
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

class _TenantMark extends StatelessWidget {
  final TenantBranding tenant;
  final String fallbackLabel;
  final Color primary;
  final Color secondary;

  const _TenantMark({
    required this.tenant,
    required this.fallbackLabel,
    required this.primary,
    required this.secondary,
  });

  @override
  Widget build(BuildContext context) {
    final hasLogo = tenant.logoPath.isNotEmpty;

    return Container(
      width: 96,
      height: 96,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [primary, secondary],
        ),
        borderRadius: BorderRadius.circular(28),
        boxShadow: [
          BoxShadow(
            color: primary.withOpacity(0.35),
            blurRadius: 22,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(28),
        child: hasLogo
            ? Image.network(
                tenant.logoPath,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => _fallback(),
              )
            : _fallback(),
      ),
    );
  }

  Widget _fallback() {
    final initials = fallbackLabel.trim().isEmpty
        ? 'BA'
        : fallbackLabel
            .trim()
            .split(RegExp(r'\s+'))
            .where((part) => part.isNotEmpty)
            .take(2)
            .map((part) => part[0].toUpperCase())
            .join();

    return Center(
      child: Text(
        initials,
        style: GoogleFonts.outfit(
          color: Colors.white,
          fontSize: 30,
          fontWeight: FontWeight.w800,
        ),
      ),
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
          border: Border.all(color: tenant.themeBorderColor),
          boxShadow: tenant.cardShadow,
        ),
        child: Row(
          children: [
            Container(
              width: 54,
              height: 54,
              decoration: BoxDecoration(
                color: tenant.themePrimaryColor.withOpacity(0.10),
                borderRadius: BorderRadius.circular(16),
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(16),
                child: tenant.logoPath.isNotEmpty
                    ? Image.network(
                        tenant.logoPath,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => _fallbackBadge(tenant),
                      )
                    : _fallbackBadge(tenant),
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    tenant.appName,
                    style: GoogleFonts.outfit(
                      color: AppColors.textMain,
                      fontSize: 17,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Open ${tenant.appName} mobile banking',
                    style: GoogleFonts.inter(
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
                  colors: [
                    tenant.themePrimaryColor,
                    tenant.themeSecondaryColor,
                  ],
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

  Widget _fallbackBadge(TenantBranding tenant) {
    final label = tenant.appName.trim().isEmpty ? 'BA' : tenant.appName.trim();
    final initials = label
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .take(2)
        .map((part) => part[0].toUpperCase())
        .join();

    return Center(
      child: Text(
        initials,
        style: GoogleFonts.outfit(
          color: tenant.themePrimaryColor,
          fontSize: 20,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}
