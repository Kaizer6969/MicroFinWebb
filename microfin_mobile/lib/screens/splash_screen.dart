import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../main.dart';
import '../models/tenant_branding.dart';
import '../theme.dart';
import 'login_screen.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _fadeAnimation;
  late final Animation<Offset> _slideAnimation;

  @override
  void initState() {
    super.initState();
    currentUser.value = null;
    activeTenant.value = TenantBranding.defaultTenant;

    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    )..forward();
    _fadeAnimation = CurvedAnimation(
      parent: _controller,
      curve: Curves.easeOutCubic,
    );
    _slideAnimation = Tween<Offset>(
      begin: const Offset(0, 0.08),
      end: Offset.zero,
    ).animate(
      CurvedAnimation(parent: _controller, curve: Curves.easeOutCubic),
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _openLogin({required bool openRegistration}) {
    Navigator.of(context).pushReplacement(
      PageRouteBuilder(
        transitionDuration: const Duration(milliseconds: 350),
        pageBuilder: (_, __, ___) =>
            LoginScreen(openRegistrationOnLoad: openRegistration),
        transitionsBuilder: (_, animation, __, child) {
          return FadeTransition(opacity: animation, child: child);
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final primary = AppColors.primary;
    final secondary = AppColors.secondary;

    return Scaffold(
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              primary,
              Color.lerp(primary, secondary, 0.55) ?? secondary,
              secondary,
            ],
          ),
        ),
        child: SafeArea(
          child: FadeTransition(
            opacity: _fadeAnimation,
            child: SlideTransition(
              position: _slideAnimation,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(24, 24, 24, 32),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Spacer(),
                    Container(
                      width: 82,
                      height: 82,
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.16),
                        borderRadius: BorderRadius.circular(28),
                        border: Border.all(
                          color: Colors.white.withOpacity(0.18),
                        ),
                      ),
                      child: const Icon(
                        Icons.account_balance_rounded,
                        color: Colors.white,
                        size: 38,
                      ),
                    ),
                    const SizedBox(height: 28),
                    Text(
                      'One shared MicroFin app for every institution.',
                      style: GoogleFonts.outfit(
                        color: Colors.white,
                        fontSize: 36,
                        fontWeight: FontWeight.w800,
                        height: 1.08,
                        letterSpacing: -0.8,
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Install once, then use your institution QR or referral code to unlock registration with the correct tenant suffix.',
                      style: GoogleFonts.inter(
                        color: Colors.white.withOpacity(0.82),
                        fontSize: 15,
                        height: 1.65,
                      ),
                    ),
                    const SizedBox(height: 28),
                    _SharedFlowCard(
                      title: 'Download App',
                      copy:
                          'The shared company-branded app is the only supported mobile app.',
                      icon: Icons.download_rounded,
                    ),
                    const SizedBox(height: 12),
                    _SharedFlowCard(
                      title: 'Bind Your Institution',
                      copy:
                          'Create Account stays locked until a valid tenant QR or referral code is verified.',
                      icon: Icons.qr_code_2_rounded,
                    ),
                    const SizedBox(height: 12),
                    _SharedFlowCard(
                      title: 'Sign In With username@tenant',
                      copy:
                          'Login and password recovery now use your full tenant-bound username.',
                      icon: Icons.verified_user_outlined,
                    ),
                    const Spacer(),
                    _SplashActionButton(
                      label: 'Sign In',
                      filled: true,
                      onPressed: () => _openLogin(openRegistration: false),
                    ),
                    const SizedBox(height: 12),
                    _SplashActionButton(
                      label: 'Create Account',
                      filled: false,
                      onPressed: () => _openLogin(openRegistration: true),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _SharedFlowCard extends StatelessWidget {
  final String title;
  final String copy;
  final IconData icon;

  const _SharedFlowCard({
    required this.title,
    required this.copy,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.12),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: Colors.white.withOpacity(0.12)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.14),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(icon, color: Colors.white, size: 22),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: GoogleFonts.outfit(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  copy,
                  style: GoogleFonts.inter(
                    color: Colors.white.withOpacity(0.74),
                    fontSize: 13,
                    height: 1.55,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SplashActionButton extends StatelessWidget {
  final String label;
  final bool filled;
  final VoidCallback onPressed;

  const _SplashActionButton({
    required this.label,
    required this.filled,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton(
        onPressed: onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: filled ? Colors.white : Colors.transparent,
          foregroundColor: filled ? AppColors.primary : Colors.white,
          elevation: 0,
          side: filled
              ? null
              : BorderSide(color: Colors.white.withOpacity(0.48)),
          padding: const EdgeInsets.symmetric(vertical: 18),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
        ),
        child: Text(
          label,
          style: GoogleFonts.outfit(
            fontSize: 16,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
    );
  }
}
