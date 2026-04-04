import 'package:flutter/material.dart';
import 'dart:math' as math;
import 'package:google_fonts/google_fonts.dart';
import '../main.dart';
import '../models/tenant_branding.dart';
import '../theme.dart';
import '../widgets/microfin_logo.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'login_screen.dart';

// ─────────────────────────────────────────────────────────────────────────────
// Animated floating orb painter for welcome background
// ─────────────────────────────────────────────────────────────────────────────
class _WelcomeOrbPainter extends CustomPainter {
  final double progress;
  _WelcomeOrbPainter({required this.progress});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..style = PaintingStyle.fill;

    final orbs = [
      _OrbData(cx: 0.78, cy: 0.08, radius: 0.30, phase: 0.0),
      _OrbData(cx: 0.65, cy: 0.25, radius: 0.16, phase: 0.55),
      _OrbData(cx: -0.08, cy: 0.30, radius: 0.22, phase: 0.85),
    ];

    for (final orb in orbs) {
      final floatY = math.sin((progress * 2 * math.pi) + orb.phase) * 10.0;
      final cx = orb.cx * size.width;
      final cy = orb.cy * size.height + floatY;
      final r = orb.radius * size.width;

      paint.shader = RadialGradient(
        colors: [Colors.white.withOpacity(0.18), Colors.white.withOpacity(0.0)],
      ).createShader(Rect.fromCircle(center: Offset(cx, cy), radius: r));
      canvas.drawCircle(Offset(cx, cy), r, paint);
    }
  }

  @override
  bool shouldRepaint(_WelcomeOrbPainter old) => old.progress != progress;
}

class _OrbData {
  final double cx, cy, radius, phase;
  const _OrbData({
    required this.cx,
    required this.cy,
    required this.radius,
    required this.phase,
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// SPLASH / WELCOME SCREEN
// ─────────────────────────────────────────────────────────────────────────────
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with TickerProviderStateMixin {
  static const String _buildTenantId = String.fromEnvironment('TENANT_ID');

  // Animation controllers
  late AnimationController _logoController;
  late AnimationController _contentController;
  late AnimationController _welcomeController;
  late AnimationController _orbController;

  late Animation<double> _fadeLogo;
  late Animation<double> _scaleLogo;
  late Animation<double> _fadePicker;
  late Animation<Offset> _slidePicker;
  late Animation<double> _fadeWelcome;
  late Animation<Offset> _slideWelcome;
  late Animation<double> _fadeButtons;
  late Animation<Offset> _slideButtons;

  // Step 1: show welcome panel after bank selected
  bool _showWelcomePanel = false;
  bool _tenantsLoaded = false;
  TenantBranding? _selectedTenant;
  String? _startupErrorTitle;
  String? _startupErrorMessage;

  bool get _isLockedTenantBuild => _buildTenantId.trim().isNotEmpty;

  @override
  void initState() {
    super.initState();

    _logoController = AnimationController(
      duration: const Duration(milliseconds: 1000),
      vsync: this,
    );
    _welcomeController = AnimationController(
      duration: const Duration(milliseconds: 700),
      vsync: this,
    );
    _orbController = AnimationController(
      duration: const Duration(seconds: 7),
      vsync: this,
    )..repeat();

    _fadeLogo = Tween<double>(begin: 0, end: 1).animate(
      CurvedAnimation(
        parent: _logoController,
        curve: const Interval(0.0, 0.7, curve: Curves.easeOut),
      ),
    );
    _scaleLogo = Tween<double>(begin: 0.65, end: 1.0).animate(
      CurvedAnimation(
        parent: _logoController,
        curve: const Interval(0.0, 0.8, curve: Curves.easeOutBack),
      ),
    );

    // Welcome panel animations
    _fadeWelcome = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: _welcomeController,
        curve: const Interval(0.0, 0.6, curve: Curves.easeOut),
      ),
    );
    _slideWelcome =
        Tween<Offset>(begin: const Offset(0, 0.08), end: Offset.zero).animate(
          CurvedAnimation(
            parent: _welcomeController,
            curve: const Interval(0.0, 0.7, curve: Curves.easeOutCubic),
          ),
        );
    _fadeButtons = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: _welcomeController,
        curve: const Interval(0.3, 1.0, curve: Curves.easeOut),
      ),
    );
    _slideButtons =
        Tween<Offset>(begin: const Offset(0, 0.15), end: Offset.zero).animate(
          CurvedAnimation(
            parent: _welcomeController,
            curve: const Interval(0.3, 1.0, curve: Curves.easeOutCubic),
          ),
        );

    _startSequence();
  }

  void _startSequence() async {
    await _logoController.forward();
    await TenantBranding.loadTenants(
      tenantFilter: _isLockedTenantBuild ? _buildTenantId : null,
    );
    _tenantsLoaded = true;

    await Future.delayed(const Duration(milliseconds: 200));
    if (!mounted) return;

    final prefs = await SharedPreferences.getInstance();

    // ── DEBUG MODE: always show the tenant picker so you can freely choose ──
    if (kDebugMode && !_isLockedTenantBuild) {
      // Clear any previously locked tenant so the picker is always fresh
      await prefs.remove('locked_tenant_id');

      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          _showTenantPicker(context);
        }
      });
      return;
    }

    // ── PRODUCTION / RELEASE MODE ────────────────────────────────────────────
    String? identifiedTenant;

    // 1. Check if we already locked in a tenant locally from a previous session
    if (_isLockedTenantBuild) {
      identifiedTenant = _buildTenantId.trim();
    } else {
      identifiedTenant = prefs.getString('locked_tenant_id');
    }

    // 2. Generic builds may still pick up a web query parameter
    if (!_isLockedTenantBuild &&
        kIsWeb &&
        (identifiedTenant == null || identifiedTenant.isEmpty)) {
      try {
        final uriTenant = Uri.base.queryParameters['tenant'];
        if (uriTenant != null && uriTenant.isNotEmpty) {
          identifiedTenant = uriTenant;
        }
      } catch (_) {}
    }

    if (!_isLockedTenantBuild &&
        (identifiedTenant == null || identifiedTenant.isEmpty)) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          _showTenantPicker(context);
        }
      });
      return;
    }

    // 4. Persist identified tenant
    if (identifiedTenant != null && identifiedTenant.isNotEmpty) {
      await prefs.setString('locked_tenant_id', identifiedTenant);
    }

    // 5. Try to match identified tenant
    if (identifiedTenant != null && identifiedTenant.isNotEmpty) {
      TenantBranding? matchingTenant;
      for (final t in TenantBranding.tenants) {
        if (t.slug.toLowerCase() == identifiedTenant.toLowerCase() ||
            t.id.toLowerCase() == identifiedTenant.toLowerCase()) {
          matchingTenant = t;
          break;
        }
      }
      if (matchingTenant != null) {
        _selectedTenant = matchingTenant;
        activeTenant.value =
            TenantBranding.fromTenantId(_selectedTenant!.slug) ??
            _selectedTenant!;
        setState(() {
          _startupErrorTitle = null;
          _startupErrorMessage = null;
          _showWelcomePanel = true;
        });
        _welcomeController.forward(from: 0);
        return;
      }
    }

    // 6. Single tenant or release fallback → auto-select first
    if (_isLockedTenantBuild) {
      await prefs.remove('locked_tenant_id');
      _showStartupError(
        title: 'Tenant Lock Failed',
        message: TenantBranding.lastLoadSucceeded
            ? 'This app is locked to "${identifiedTenant ?? _buildTenantId}", but that tenant was not found or is inactive.'
            : (TenantBranding.lastLoadError ??
                  'Unable to validate the locked tenant right now.'),
      );
      return;
    }

    await prefs.remove('locked_tenant_id');

    if (TenantBranding.lastLoadSucceeded &&
        TenantBranding.tenants.length == 1 &&
        TenantBranding.tenants.first.id != TenantBranding.defaultTenant.id) {
      _selectedTenant = TenantBranding.tenants.first;
      activeTenant.value =
          TenantBranding.fromTenantId(_selectedTenant!.slug) ??
          _selectedTenant!;
      setState(() {
        _startupErrorTitle = null;
        _startupErrorMessage = null;
        _showWelcomePanel = true;
      });
      _welcomeController.forward(from: 0);
      return;
    }

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        _showTenantPicker(context);
      }
    });
  }

  void _showStartupError({required String title, required String message}) {
    if (!mounted) return;
    setState(() {
      _startupErrorTitle = title;
      _startupErrorMessage = message;
      _showWelcomePanel = false;
    });
  }

  Future<void> _retryStartup() async {
    if (!mounted) return;
    _welcomeController.reset();
    setState(() {
      _startupErrorTitle = null;
      _startupErrorMessage = null;
      _selectedTenant = null;
      _showWelcomePanel = false;
      _tenantsLoaded = false;
    });
    _startSequence();
  }

  void _showTenantPicker(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isDismissible: false,
      enableDrag: false,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => PopScope(
        canPop: false,
        child: _TenantPickerSheet(onSelect: _onBankSelected),
      ),
    );
  }

  void _onBankSelected(TenantBranding tenant) {
    Navigator.of(context).pop(); // Close the bottom sheet
    final mapped = TenantBranding.fromTenantId(tenant.slug) ?? tenant;
    activeTenant.value = mapped;
    setState(() {
      _selectedTenant = mapped;
      _showWelcomePanel = true;
    });
    _welcomeController.forward();
  }

  void _goToLogin() {
    Navigator.of(context).pushReplacement(
      PageRouteBuilder(
        transitionDuration: const Duration(milliseconds: 500),
        pageBuilder: (_, __, ___) => LoginScreen(),
        transitionsBuilder: (_, animation, __, child) => FadeTransition(
          opacity: CurvedAnimation(parent: animation, curve: Curves.easeOut),
          child: child,
        ),
      ),
    );
  }

  @override
  void dispose() {
    _logoController.dispose();
    _welcomeController.dispose();
    _orbController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;

    // Use selected tenant color if available, else first tenant, else fallback blue
    final Color primaryColor =
        _selectedTenant?.themePrimaryColor ??
        (_tenantsLoaded && TenantBranding.tenants.isNotEmpty
            ? TenantBranding.tenants.first.themePrimaryColor
            : const Color(0xFF1A4FD6));
    final Color secondaryColor =
        _selectedTenant?.themeSecondaryColor ??
        (_tenantsLoaded && TenantBranding.tenants.isNotEmpty
            ? TenantBranding.tenants.first.themeSecondaryColor
            : const Color(0xFF2563EB));

    return Scaffold(
      body: Stack(
        children: [
          // ── Full-screen gradient background ──────────────────────────
          Positioned.fill(
            child: AnimatedBuilder(
              animation: _orbController,
              builder: (_, __) => AnimatedContainer(
                duration: const Duration(milliseconds: 600),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      Color.lerp(primaryColor, secondaryColor, 0.1)!,
                      primaryColor,
                    ],
                  ),
                ),
                child: CustomPaint(
                  painter: _WelcomeOrbPainter(progress: _orbController.value),
                  size: size,
                ),
              ),
            ),
          ),

          // ── Main content ─────────────────────────────────────────────
          SafeArea(
            child: Column(
              children: [
                // ── Illustration area ─────────────────────────────────
                Expanded(
                  flex: _showWelcomePanel ? 5 : 4,
                  child: AnimatedBuilder(
                    animation: _logoController,
                    builder: (_, __) => FadeTransition(
                      opacity: _fadeLogo,
                      child: ScaleTransition(
                        scale: _scaleLogo,
                        child: Center(
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 24),
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                _FinanceIllustration(
                                  primaryColor: primaryColor,
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),

                if (!_showWelcomePanel &&
                    !_tenantsLoaded &&
                    _startupErrorTitle == null)
                  // Loading state
                  const Padding(
                    padding: EdgeInsets.only(bottom: 60),
                    child: CircularProgressIndicator(
                      color: Colors.white,
                      strokeWidth: 2.5,
                    ),
                  ),

                // ── STEP 2: Welcome panel with Get Started ────────────
                if (_startupErrorTitle != null && _startupErrorMessage != null)
                  Expanded(
                    flex: 4,
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(28, 0, 28, 36),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.end,
                        children: [
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(24),
                            decoration: BoxDecoration(
                              color: Colors.white.withOpacity(0.12),
                              borderRadius: BorderRadius.circular(28),
                              border: Border.all(
                                color: Colors.white.withOpacity(0.18),
                              ),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Container(
                                  width: 56,
                                  height: 56,
                                  decoration: BoxDecoration(
                                    color: Colors.white.withOpacity(0.14),
                                    borderRadius: BorderRadius.circular(18),
                                  ),
                                  child: const Icon(
                                    Icons.shield_outlined,
                                    color: Colors.white,
                                    size: 28,
                                  ),
                                ),
                                const SizedBox(height: 18),
                                Text(
                                  _startupErrorTitle!,
                                  style: GoogleFonts.outfit(
                                    color: Colors.white,
                                    fontSize: 28,
                                    fontWeight: FontWeight.w800,
                                    letterSpacing: -0.7,
                                  ),
                                ),
                                const SizedBox(height: 10),
                                Text(
                                  _startupErrorMessage!,
                                  style: GoogleFonts.inter(
                                    color: Colors.white.withOpacity(0.86),
                                    fontSize: 14,
                                    height: 1.6,
                                  ),
                                ),
                                const SizedBox(height: 24),
                                SizedBox(
                                  width: double.infinity,
                                  child: ElevatedButton(
                                    onPressed: _retryStartup,
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: Colors.white,
                                      foregroundColor: primaryColor,
                                      padding: const EdgeInsets.symmetric(
                                        vertical: 18,
                                      ),
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(18),
                                      ),
                                    ),
                                    child: Text(
                                      'Retry Tenant Check',
                                      style: GoogleFonts.outfit(
                                        fontSize: 17,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                if (_showWelcomePanel)
                  Expanded(
                    flex: 4,
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 28),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.end,
                        children: [
                          // "Welcome" heading
                          FadeTransition(
                            opacity: _fadeWelcome,
                            child: SlideTransition(
                              position: _slideWelcome,
                              child: Column(
                                children: [
                                  Text(
                                    'Welcome',
                                    style: GoogleFonts.outfit(
                                      fontSize: 38,
                                      fontWeight: FontWeight.w900,
                                      color: Colors.white,
                                      letterSpacing: -1.0,
                                    ),
                                  ),
                                  const SizedBox(height: 10),
                                  Text(
                                    'Manage your expenses',
                                    style: GoogleFonts.inter(
                                      fontSize: 15,
                                      color: Colors.white.withOpacity(0.80),
                                      fontWeight: FontWeight.w400,
                                    ),
                                  ),
                                  Text(
                                    'seamlessly & intuitively',
                                    style: GoogleFonts.outfit(
                                      fontSize: 16,
                                      color: Colors.white,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),

                          const SizedBox(height: 36),

                          // Buttons section
                          FadeTransition(
                            opacity: _fadeButtons,
                            child: SlideTransition(
                              position: _slideButtons,
                              child: Column(
                                children: [
                                  // Primary: Get Started → Login
                                  _WelcomeButton(
                                    onTap: _goToLogin,
                                    label: 'Get Started',
                                    icon: Icons.arrow_forward_rounded,
                                    filled: true,
                                    primaryColor: primaryColor,
                                  ),

                                  const SizedBox(height: 14),

                                  // Secondary: Create account → Login
                                  _WelcomeButton(
                                    onTap: _goToLogin,
                                    label: 'Create an account',
                                    icon: null,
                                    filled: false,
                                    primaryColor: primaryColor,
                                  ),

                                  const SizedBox(height: 20),

                                  // Sign in link
                                  GestureDetector(
                                    onTap: _goToLogin,
                                    child: Text.rich(
                                      TextSpan(
                                        text: 'Already have an account? ',
                                        style: GoogleFonts.inter(
                                          fontSize: 13,
                                          color: Colors.white.withOpacity(0.70),
                                        ),
                                        children: [
                                          TextSpan(
                                            text: 'Sign in',
                                            style: GoogleFonts.inter(
                                              fontSize: 13,
                                              color: Colors.white,
                                              fontWeight: FontWeight.w700,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                  const SizedBox(height: 32),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
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

// ─────────────────────────────────────────────────────────────────────────────
// Finance Illustration Widget (custom drawn - matches inspiration style)
// ─────────────────────────────────────────────────────────────────────────────
class _FinanceIllustration extends StatelessWidget {
  final Color primaryColor;
  const _FinanceIllustration({required this.primaryColor});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 280,
      height: 220,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          // Dashboard card (behind)
          Positioned(
            top: 20,
            left: 0,
            right: 30,
            child: Container(
              height: 130,
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.15),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(
                  color: Colors.white.withOpacity(0.3),
                  width: 1,
                ),
              ),
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Chart bars
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      _Bar(
                        height: 40,
                        color: Colors.greenAccent.withOpacity(0.8),
                      ),
                      const SizedBox(width: 6),
                      _Bar(
                        height: 60,
                        color: Colors.cyanAccent.withOpacity(0.8),
                      ),
                      const SizedBox(width: 6),
                      _Bar(height: 35, color: Colors.white.withOpacity(0.6)),
                      const SizedBox(width: 6),
                      _Bar(
                        height: 55,
                        color: Colors.cyanAccent.withOpacity(0.8),
                      ),
                      const SizedBox(width: 6),
                      _Bar(
                        height: 45,
                        color: Colors.greenAccent.withOpacity(0.8),
                      ),
                    ],
                  ),
                  const Spacer(),
                  // Card chip rows
                  Row(
                    children: [
                      Container(
                        width: 40,
                        height: 8,
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.6),
                          borderRadius: BorderRadius.circular(4),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Container(
                        width: 60,
                        height: 8,
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.3),
                          borderRadius: BorderRadius.circular(4),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Container(
                        width: 30,
                        height: 8,
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.3),
                          borderRadius: BorderRadius.circular(4),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Container(
                        width: 50,
                        height: 8,
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.4),
                          borderRadius: BorderRadius.circular(4),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),

          // Dollar coin / target badge (bottom left)
          Positioned(
            bottom: 0,
            left: 10,
            child: Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.orange.withOpacity(0.9),
                boxShadow: [
                  BoxShadow(
                    color: Colors.orange.withOpacity(0.4),
                    blurRadius: 12,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: const Center(
                child: Text('🎯', style: TextStyle(fontSize: 26)),
              ),
            ),
          ),

          // Money bag (bottom center)
          Positioned(
            bottom: 12,
            left: 70,
            child: Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withOpacity(0.20),
                border: Border.all(color: Colors.white.withOpacity(0.35)),
              ),
              child: const Center(
                child: Text('💰', style: TextStyle(fontSize: 22)),
              ),
            ),
          ),

          // Stick figure person (right side)
          Positioned(right: 0, top: 10, bottom: 0, child: _PersonFigure()),
        ],
      ),
    );
  }
}

class _Bar extends StatelessWidget {
  final double height;
  final Color color;
  const _Bar({required this.height, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 16,
      height: height,
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(4),
      ),
    );
  }
}

class _PersonFigure extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 80,
      height: 190,
      child: CustomPaint(painter: _PersonPainter()),
    );
  }
}

class _PersonPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.white
      ..strokeWidth = 3.0
      ..strokeCap = StrokeCap.round
      ..style = PaintingStyle.stroke;

    final fillPaint = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.fill;

    final cx = size.width / 2 + 10;

    // Head
    canvas.drawCircle(Offset(cx, 30), 16, fillPaint);
    canvas.drawCircle(
      Offset(cx, 30),
      16,
      paint..color = Colors.white.withOpacity(0.15),
    );

    paint.color = Colors.white;

    // Body
    canvas.drawLine(Offset(cx, 46), Offset(cx, 110), paint);

    // Left arm (pointing at chart)
    canvas.drawLine(Offset(cx, 68), Offset(cx - 30, 55), paint);

    // Right arm
    canvas.drawLine(Offset(cx, 68), Offset(cx + 18, 80), paint);

    // Left leg
    canvas.drawLine(Offset(cx, 110), Offset(cx - 20, 145), paint);

    // Right leg
    canvas.drawLine(Offset(cx, 110), Offset(cx + 20, 145), paint);

    // Shoes
    canvas.drawCircle(Offset(cx - 20, 148), 5, fillPaint);
    canvas.drawCircle(Offset(cx + 20, 148), 5, fillPaint);
  }

  @override
  bool shouldRepaint(_PersonPainter old) => false;
}

// ─────────────────────────────────────────────────────────────────────────────
// Welcome action button
// ─────────────────────────────────────────────────────────────────────────────
class _WelcomeButton extends StatefulWidget {
  final VoidCallback onTap;
  final String label;
  final IconData? icon;
  final bool filled;
  final Color primaryColor;

  const _WelcomeButton({
    required this.onTap,
    required this.label,
    required this.filled,
    required this.primaryColor,
    this.icon,
  });

  @override
  State<_WelcomeButton> createState() => _WelcomeButtonState();
}

class _WelcomeButtonState extends State<_WelcomeButton> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTapDown: (_) => setState(() => _pressed = true),
      onTapUp: (_) {
        setState(() => _pressed = false);
        widget.onTap();
      },
      onTapCancel: () => setState(() => _pressed = false),
      child: AnimatedScale(
        scale: _pressed ? 0.96 : 1.0,
        duration: const Duration(milliseconds: 100),
        child: Container(
          width: double.infinity,
          height: 56,
          decoration: BoxDecoration(
            color: widget.filled ? Colors.white : Colors.transparent,
            borderRadius: BorderRadius.circular(16),
            border: widget.filled
                ? null
                : Border.all(color: Colors.white, width: 1.8),
            boxShadow: widget.filled
                ? [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.15),
                      blurRadius: 20,
                      offset: const Offset(0, 6),
                    ),
                  ]
                : null,
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                widget.label,
                style: GoogleFonts.outfit(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                  color: widget.filled ? widget.primaryColor : Colors.white,
                  letterSpacing: 0.1,
                ),
              ),
              if (widget.icon != null) ...[
                const SizedBox(width: 8),
                Icon(
                  widget.icon,
                  size: 20,
                  color: widget.filled ? widget.primaryColor : Colors.white,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Tenant Picker bottom sheet
// ─────────────────────────────────────────────────────────────────────────────
class _TenantPickerSheet extends StatelessWidget {
  final void Function(TenantBranding) onSelect;
  const _TenantPickerSheet({required this.onSelect});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.only(top: MediaQuery.of(context).padding.top + 80),
      decoration: BoxDecoration(
        color: AppColors.bg,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(32)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.20),
            blurRadius: 40,
            offset: const Offset(0, -10),
          ),
        ],
      ),
      padding: const EdgeInsets.fromLTRB(24, 16, 24, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 36,
              height: 4,
              decoration: BoxDecoration(
                color: AppColors.border,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          const SizedBox(height: 22),
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFF3B82F6).withOpacity(0.1),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: const Text(
                  'SELECT',
                  style: TextStyle(
                    color: Color(0xFF3B82F6),
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.5,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Text(
                'Choose your institution',
                style: GoogleFonts.outfit(
                  color: AppColors.textMain,
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.5,
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            'Select the institution you belong to',
            style: GoogleFonts.inter(color: AppColors.textMuted, fontSize: 13),
          ),
          const SizedBox(height: 20),
          Expanded(
            child: ListView.separated(
              padding: EdgeInsets.zero,
              itemCount: TenantBranding.tenants.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                return _TenantCard(
                  tenant: TenantBranding.tenants[index],
                  onTap: () => onSelect(TenantBranding.tenants[index]),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Tenant Card Widget ────────────────────────────────────────────────────────
class _TenantCard extends StatefulWidget {
  final TenantBranding tenant;
  final VoidCallback onTap;

  const _TenantCard({required this.tenant, required this.onTap});

  @override
  State<_TenantCard> createState() => _TenantCardState();
}

class _TenantCardState extends State<_TenantCard>
    with SingleTickerProviderStateMixin {
  late AnimationController _pressController;
  late Animation<double> _scaleAnim;

  @override
  void initState() {
    super.initState();
    _pressController = AnimationController(
      duration: const Duration(milliseconds: 120),
      vsync: this,
    );
    _scaleAnim = Tween<double>(
      begin: 1.0,
      end: 0.96,
    ).animate(CurvedAnimation(parent: _pressController, curve: Curves.easeOut));
  }

  @override
  void dispose() {
    _pressController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = widget.tenant;
    return GestureDetector(
      onTapDown: (_) => _pressController.forward(),
      onTapUp: (_) {
        _pressController.reverse();
        widget.onTap();
      },
      onTapCancel: () => _pressController.reverse(),
      child: ScaleTransition(
        scale: _scaleAnim,
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.card,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: t.themePrimaryColor.withOpacity(0.2)),
            boxShadow: [
              BoxShadow(
                color: t.themePrimaryColor.withOpacity(0.07),
                blurRadius: 16,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Row(
            children: [
              // Tenant icon
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [
                      t.themePrimaryColor.withOpacity(0.2),
                      t.themeSecondaryColor.withOpacity(0.1),
                    ],
                  ),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Center(
                  child: Text('🏦', style: TextStyle(fontSize: 26)),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      t.appName,
                      style: GoogleFonts.outfit(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textMain,
                        letterSpacing: -0.3,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      'Your trusted lending partner',
                      style: GoogleFonts.inter(
                        fontSize: 12,
                        color: AppColors.textMuted,
                        fontWeight: FontWeight.w400,
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
                    colors: [t.themePrimaryColor, t.themeSecondaryColor],
                  ),
                  borderRadius: BorderRadius.circular(12),
                  boxShadow: [
                    BoxShadow(
                      color: t.themePrimaryColor.withOpacity(0.38),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
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
      ),
    );
  }
}
