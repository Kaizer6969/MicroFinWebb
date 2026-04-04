import 'package:flutter/material.dart';
import 'dart:convert';
import 'dart:math' as math;
import 'package:http/http.dart' as http;
import 'package:google_fonts/google_fonts.dart';
import 'package:flutter/gestures.dart';
import '../main.dart';
import '../theme.dart';
import '../utils/api_config.dart';
import 'main_layout.dart';
import 'splash_screen.dart';

// ─────────────────────────────────────────────────────────────────────────────
// Floating decorative orb painter (blue-style circles like inspiration)
// ─────────────────────────────────────────────────────────────────────────────
class _OrbPainter extends CustomPainter {
  final double progress;
  final Color color;
  _OrbPainter({required this.progress, required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..style = PaintingStyle.fill;

    final orbs = [
      _OrbData(cx: 0.85, cy: 0.06, radius: 0.22, phase: 0.0),
      _OrbData(cx: 0.70, cy: 0.22, radius: 0.14, phase: 0.5),
      _OrbData(cx: -0.05, cy: 0.28, radius: 0.18, phase: 0.8),
    ];

    for (final orb in orbs) {
      final floatY = math.sin((progress * 2 * math.pi) + orb.phase) * 8.0;
      final cx = orb.cx * size.width;
      final cy = orb.cy * size.height + floatY;
      final r = orb.radius * size.width;

      paint.shader = RadialGradient(
        colors: [
          color.withOpacity(0.22),
          color.withOpacity(0.0),
        ],
      ).createShader(Rect.fromCircle(center: Offset(cx, cy), radius: r));
      canvas.drawCircle(Offset(cx, cy), r, paint);
    }
  }

  @override
  bool shouldRepaint(_OrbPainter old) => old.progress != progress;
}

class _OrbData {
  final double cx, cy, radius, phase;
  const _OrbData({required this.cx, required this.cy, required this.radius, required this.phase});
}

// ─────────────────────────────────────────────────────────────────────────────
// LOGIN SCREEN
// ─────────────────────────────────────────────────────────────────────────────
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen>
    with TickerProviderStateMixin {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;
  bool _isLoading = false;

  late AnimationController _entryController;
  late AnimationController _orbController;
  late Animation<double> _fadeAnim;
  late Animation<Offset> _cardSlideAnim;
  late Animation<double> _headerFadeAnim;

  @override
  void initState() {
    super.initState();

    _entryController = AnimationController(
      duration: const Duration(milliseconds: 900),
      vsync: this,
    );
    _orbController = AnimationController(
      duration: const Duration(seconds: 8),
      vsync: this,
    )..repeat();

    _fadeAnim = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(parent: _entryController, curve: const Interval(0.0, 0.5, curve: Curves.easeOut)),
    );
    _headerFadeAnim = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(parent: _entryController, curve: const Interval(0.1, 0.6, curve: Curves.easeOut)),
    );
    _cardSlideAnim = Tween<Offset>(begin: const Offset(0, 0.12), end: Offset.zero).animate(
      CurvedAnimation(parent: _entryController, curve: const Interval(0.3, 1.0, curve: Curves.easeOutCubic)),
    );

    _entryController.forward();
  }

  @override
  void dispose() {
    _entryController.dispose();
    _orbController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _isLoading = true);
    try {
      final url = Uri.parse(ApiConfig.getUrl('api_login.php'));
      final response = await http.post(
        url,
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'tenant_id': activeTenant.value.id,
          'username': _emailController.text,
          'password': _passwordController.text,
        }),
      );
      String loginBody = response.body;
      final loginJsonStart = loginBody.indexOf('{');
      if (loginJsonStart > 0) loginBody = loginBody.substring(loginJsonStart);
      final data = jsonDecode(loginBody);
      if (data['success'] == true) {
        currentUser.value = {
          'user_id': data['user_id'],
          'username': _emailController.text,
          'first_name': data['first_name'],
          'last_name': data['last_name'],
          'verification_status': data['verification_status'] ?? 'Unverified',
          'credit_limit': data['credit_limit'] ?? 0,
        };
        if (mounted) {
          Navigator.of(context).pushReplacement(PageRouteBuilder(
            transitionDuration: const Duration(milliseconds: 700),
            pageBuilder: (_, __, ___) => const MainLayout(),
            transitionsBuilder: (_, animation, __, child) => FadeTransition(
              opacity: CurvedAnimation(parent: animation, curve: Curves.easeInOut),
              child: child,
            ),
          ));
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Text(data['message'] ?? 'Login failed'),
            backgroundColor: AppColors.error,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ));
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Failed to connect to the server'),
          backgroundColor: AppColors.error,
          behavior: SnackBarBehavior.floating,
        ));
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _goBack() {
    Navigator.of(context).pushReplacement(PageRouteBuilder(
      transitionDuration: const Duration(milliseconds: 400),
      pageBuilder: (_, __, ___) => const SplashScreen(),
      transitionsBuilder: (_, animation, __, child) =>
          FadeTransition(opacity: animation, child: child),
    ));
  }

  @override
  Widget build(BuildContext context) {
    final tenant = activeTenant.value;
    final primary = tenant.themePrimaryColor;
    final secondary = tenant.themeSecondaryColor;
    final size = MediaQuery.of(context).size;
    // Header takes ~38% of screen height
    final headerHeight = size.height * 0.38;

    return Scaffold(
      backgroundColor: Colors.white,
      body: Stack(
        children: [
          // ── Blue gradient header ──────────────────────────────────────
          Positioned(
            top: 0, left: 0, right: 0,
            height: headerHeight,
            child: AnimatedBuilder(
              animation: _orbController,
              builder: (_, __) => Container(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      Color.lerp(primary, secondary, 0.15)!,
                      primary,
                    ],
                  ),
                ),
                child: Stack(
                  children: [
                    // Animated orbs
                    CustomPaint(
                      painter: _OrbPainter(progress: _orbController.value, color: Colors.white),
                      size: Size(size.width, headerHeight),
                    ),
                  ],
                ),
              ),
            ),
          ),

          // ── Safe area content ─────────────────────────────────────────
          SafeArea(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // ── Back button row ──────────────────────────────────
                FadeTransition(
                  opacity: _fadeAnim,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
                    child: GestureDetector(
                      onTap: _goBack,
                      child: Container(
                        width: 40,
                        height: 40,
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.18),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: Colors.white.withOpacity(0.3), width: 1),
                        ),
                        child: const Icon(Icons.arrow_back_rounded, color: Colors.white, size: 20),
                      ),
                    ),
                  ),
                ),

                // ── "Sign in" label ──────────────────────────────────
                FadeTransition(
                  opacity: _headerFadeAnim,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(24, 20, 24, 0),
                    child: Text(
                      'Sign in',
                      style: GoogleFonts.outfit(
                        fontSize: 32,
                        fontWeight: FontWeight.w800,
                        color: Colors.white,
                        letterSpacing: -0.5,
                      ),
                    ),
                  ),
                ),

                // ── White card slides up ─────────────────────────────
                Expanded(
                  child: SlideTransition(
                    position: _cardSlideAnim,
                    child: FadeTransition(
                      opacity: _fadeAnim,
                      child: Container(
                        width: double.infinity,
                        margin: EdgeInsets.only(top: headerHeight * 0.14),
                        decoration: const BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
                          boxShadow: [
                            BoxShadow(
                              color: Color(0x1A000000),
                              blurRadius: 30,
                              offset: Offset(0, -6),
                            ),
                          ],
                        ),
                        child: SingleChildScrollView(
                          physics: const BouncingScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(28, 36, 28, 32),
                          child: Form(
                            key: _formKey,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                // Drag handle
                                Center(
                                  child: Container(
                                    width: 44,
                                    height: 4,
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFE2E8F0),
                                      borderRadius: BorderRadius.circular(4),
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 28),

                                // "Welcome Back" heading
                                Text(
                                  'Welcome Back',
                                  style: GoogleFonts.outfit(
                                    fontSize: 26,
                                    fontWeight: FontWeight.w800,
                                    color: const Color(0xFF1A1A2E),
                                    letterSpacing: -0.4,
                                  ),
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  'Hello there, sign in to continue!',
                                  style: GoogleFonts.inter(
                                    fontSize: 14,
                                    color: AppColors.textMuted,
                                  ),
                                ),
                                const SizedBox(height: 32),

                                // Username or email label
                                Text(
                                  'Username or email',
                                  style: GoogleFonts.inter(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w600,
                                    color: const Color(0xFF6B7280),
                                  ),
                                ),
                                const SizedBox(height: 8),
                                _LoginField(
                                  hint: 'Enter your username or email',
                                  controller: _emailController,
                                  primary: primary,
                                  keyboardType: TextInputType.emailAddress,
                                  validator: (v) => v?.isEmpty ?? true ? 'Please enter your username or email' : null,
                                ),
                                const SizedBox(height: 20),

                                // Password label
                                Text(
                                  'Password',
                                  style: GoogleFonts.inter(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w600,
                                    color: const Color(0xFF6B7280),
                                  ),
                                ),
                                const SizedBox(height: 8),
                                _LoginField(
                                  hint: 'Enter your password',
                                  controller: _passwordController,
                                  primary: primary,
                                  obscureText: _obscurePassword,
                                  suffixIcon: GestureDetector(
                                    onTap: () => setState(() => _obscurePassword = !_obscurePassword),
                                    child: Icon(
                                      _obscurePassword ? Icons.visibility_off_rounded : Icons.visibility_rounded,
                                      color: primary,
                                      size: 20,
                                    ),
                                  ),
                                  validator: (v) => v?.isEmpty ?? true ? 'Please enter your password' : null,
                                ),

                                // Forgot password
                                Align(
                                  alignment: Alignment.centerLeft,
                                  child: TextButton(
                                    onPressed: () => _showForgotPasswordModal(context),
                                    style: TextButton.styleFrom(
                                      padding: const EdgeInsets.symmetric(vertical: 10),
                                      minimumSize: Size.zero,
                                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                    ),
                                    child: Text(
                                      'Forgot Password?',
                                      style: GoogleFonts.inter(
                                        color: primary,
                                        fontSize: 13,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 20),

                                // Sign In button
                                _SignInButton(
                                  label: 'Sign In',
                                  isLoading: _isLoading,
                                  primary: primary,
                                  secondary: secondary,
                                  onPressed: _handleLogin,
                                ),
                                const SizedBox(height: 32),

                                // Don't have an account? Sign up
                                Center(
                                  child: Text.rich(
                                    TextSpan(
                                      text: "Don't have an account? ",
                                      style: GoogleFonts.inter(
                                        fontSize: 14,
                                        color: AppColors.textMuted,
                                      ),
                                      children: [
                                        WidgetSpan(
                                          child: GestureDetector(
                                            onTap: () => _showRegistrationModal(context),
                                            child: Text(
                                              'Sign up',
                                              style: GoogleFonts.inter(
                                                fontSize: 14,
                                                color: primary,
                                                fontWeight: FontWeight.w700,
                                              ),
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 16),
                              ],
                            ),
                          ),
                        ),
                      ),
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

  void _showRegistrationModal(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const _RegistrationModal(),
    );
  }

  void _showForgotPasswordModal(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const _ForgotPasswordModal(),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Clean input field (matches inspiration: light grey fill, rounded, no icon)
// ─────────────────────────────────────────────────────────────────────────────
class _LoginField extends StatefulWidget {
  final String hint;
  final TextEditingController controller;
  final Color primary;
  final bool obscureText;
  final Widget? suffixIcon;
  final String? Function(String?)? validator;
  final TextInputType? keyboardType;

  const _LoginField({
    required this.hint,
    required this.controller,
    required this.primary,
    this.obscureText = false,
    this.suffixIcon,
    this.validator,
    this.keyboardType,
  });

  @override
  State<_LoginField> createState() => _LoginFieldState();
}

class _LoginFieldState extends State<_LoginField> {
  bool _focused = false;

  @override
  Widget build(BuildContext context) {
    return Focus(
      onFocusChange: (f) => setState(() => _focused = f),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: _focused ? widget.primary.withOpacity(0.55) : const Color(0xFFE8EDF3),
            width: _focused ? 1.8 : 1.2,
          ),
          boxShadow: _focused
              ? [BoxShadow(color: widget.primary.withOpacity(0.10), blurRadius: 10, offset: const Offset(0, 3))]
              : [],
        ),
        child: TextFormField(
          controller: widget.controller,
          obscureText: widget.obscureText,
          keyboardType: widget.keyboardType,
          style: GoogleFonts.inter(fontSize: 15, fontWeight: FontWeight.w500, color: const Color(0xFF1A1A2E)),
          validator: widget.validator,
          decoration: InputDecoration(
            hintText: widget.hint,
            hintStyle: GoogleFonts.inter(fontSize: 14, color: const Color(0xFFADB5BD)),
            suffixIcon: widget.suffixIcon != null
                ? Padding(padding: const EdgeInsets.only(right: 14), child: widget.suffixIcon)
                : null,
            suffixIconConstraints: const BoxConstraints(minWidth: 0, minHeight: 0),
            filled: true,
            fillColor: _focused ? widget.primary.withOpacity(0.03) : const Color(0xFFF8FAFC),
            contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 18),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
            errorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AppColors.error, width: 1.2)),
            focusedErrorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AppColors.error, width: 1.5)),
          ),
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Sign In button (solid primary color, full-width, pill)
// ─────────────────────────────────────────────────────────────────────────────
class _SignInButton extends StatefulWidget {
  final String label;
  final bool isLoading;
  final Color primary;
  final Color secondary;
  final VoidCallback onPressed;

  const _SignInButton({
    required this.label,
    required this.isLoading,
    required this.primary,
    required this.secondary,
    required this.onPressed,
  });

  @override
  State<_SignInButton> createState() => _SignInButtonState();
}

class _SignInButtonState extends State<_SignInButton> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTapDown: (_) => setState(() => _pressed = true),
      onTapUp: (_) {
        setState(() => _pressed = false);
        if (!widget.isLoading) widget.onPressed();
      },
      onTapCancel: () => setState(() => _pressed = false),
      child: AnimatedScale(
        scale: _pressed ? 0.97 : 1.0,
        duration: const Duration(milliseconds: 100),
        child: Container(
          width: double.infinity,
          height: 56,
          decoration: BoxDecoration(
            color: widget.primary,
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: widget.primary.withOpacity(0.35),
                blurRadius: 18,
                offset: const Offset(0, 7),
              ),
            ],
          ),
          child: Center(
            child: widget.isLoading
                ? const SizedBox(
                    width: 24,
                    height: 24,
                    child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5),
                  )
                : Text(
                    widget.label,
                    style: GoogleFonts.outfit(
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                      color: Colors.white,
                      letterSpacing: 0.2,
                    ),
                  ),
          ),
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Shared premium field (used by modals)
// ─────────────────────────────────────────────────────────────────────────────
class _PremiumField extends StatefulWidget {
  final String label;
  final String hint;
  final IconData icon;
  final Color primary;
  final TextEditingController controller;
  final bool obscureText;
  final Widget? suffixIcon;
  final String? Function(String?)? validator;
  final TextInputType? keyboardType;

  const _PremiumField({
    required this.label,
    required this.hint,
    required this.icon,
    required this.primary,
    required this.controller,
    this.obscureText = false,
    this.suffixIcon,
    this.validator,
    this.keyboardType,
  });

  @override
  State<_PremiumField> createState() => _PremiumFieldState();
}

class _PremiumFieldState extends State<_PremiumField> {
  bool _focused = false;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          widget.label,
          style: GoogleFonts.inter(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: _focused ? widget.primary : AppColors.textMain.withOpacity(0.75),
          ),
        ),
        const SizedBox(height: 8),
        Focus(
          onFocusChange: (f) => setState(() => _focused = f),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: _focused ? widget.primary.withOpacity(0.6) : const Color(0xFFE8EDF3),
                width: _focused ? 1.8 : 1.2,
              ),
              boxShadow: _focused
                  ? [BoxShadow(color: widget.primary.withOpacity(0.12), blurRadius: 12, offset: const Offset(0, 4))]
                  : [],
            ),
            child: TextFormField(
              controller: widget.controller,
              obscureText: widget.obscureText,
              keyboardType: widget.keyboardType,
              style: GoogleFonts.inter(fontSize: 15, fontWeight: FontWeight.w500, color: AppColors.textMain),
              validator: widget.validator,
              decoration: InputDecoration(
                hintText: widget.hint,
                hintStyle: GoogleFonts.inter(fontSize: 14, color: AppColors.textMuted.withOpacity(0.5)),
                prefixIcon: Padding(
                  padding: const EdgeInsets.only(left: 14, right: 10),
                  child: Icon(widget.icon, size: 20, color: _focused ? widget.primary : AppColors.textMuted),
                ),
                prefixIconConstraints: const BoxConstraints(minWidth: 0, minHeight: 0),
                suffixIcon: widget.suffixIcon,
                filled: true,
                fillColor: _focused ? widget.primary.withOpacity(0.03) : const Color(0xFFF9FAFC),
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
                focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
                errorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: AppColors.error, width: 1.2)),
                focusedErrorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: AppColors.error, width: 1.5)),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _PremiumButton extends StatefulWidget {
  final String label;
  final bool isLoading;
  final Color primary;
  final Color secondary;
  final VoidCallback onPressed;

  const _PremiumButton({
    required this.label,
    required this.isLoading,
    required this.primary,
    required this.secondary,
    required this.onPressed,
  });

  @override
  State<_PremiumButton> createState() => _PremiumButtonState();
}

class _PremiumButtonState extends State<_PremiumButton> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTapDown: (_) => setState(() => _pressed = true),
      onTapUp: (_) {
        setState(() => _pressed = false);
        if (!widget.isLoading) widget.onPressed();
      },
      onTapCancel: () => setState(() => _pressed = false),
      child: AnimatedScale(
        scale: _pressed ? 0.97 : 1.0,
        duration: const Duration(milliseconds: 100),
        child: Container(
          width: double.infinity,
          height: 56,
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: [widget.primary, Color.lerp(widget.primary, widget.secondary, 0.6)!],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: widget.primary.withOpacity(0.38),
                blurRadius: 20,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Center(
            child: widget.isLoading
                ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                : Text(widget.label, style: GoogleFonts.outfit(fontSize: 17, fontWeight: FontWeight.w800, color: Colors.white, letterSpacing: 0.2)),
          ),
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// REGISTRATION MODAL
// ─────────────────────────────────────────────────────────────────────────────
class _RegistrationModal extends StatefulWidget {
  const _RegistrationModal();

  @override
  State<_RegistrationModal> createState() => _RegistrationModalState();
}

class _RegistrationModalState extends State<_RegistrationModal> {
  bool _obscurePassword = true;
  bool _obscureConfirm = true;
  bool _agreed = false;
  bool _isLoading = false;

  String? _errorMessage;
  String? _successMessage;

  final _formKey = GlobalKey<FormState>();
  final _firstNameController = TextEditingController();
  final _lastNameController = TextEditingController();
  final _usernameController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  final _otpController = TextEditingController();

  int _currentStep = 1;

  int _passwordStrength = 0;
  late TapGestureRecognizer _termsRecognizer;
  late TapGestureRecognizer _privacyRecognizer;

  @override
  void initState() {
    super.initState();
    _passwordController.addListener(_updatePasswordStrength);
    _termsRecognizer = TapGestureRecognizer()..onTap = _showCombinedPolicyDialog;
    _privacyRecognizer = TapGestureRecognizer()..onTap = _showCombinedPolicyDialog;
  }

  void _updatePasswordStrength() {
    final password = _passwordController.text;
    int strength = 0;
    if (password.isNotEmpty) {
      if (password.length >= 8) strength = 1;
      if (password.length >= 8 && RegExp(r'[A-Za-z]').hasMatch(password) && RegExp(r'[0-9]').hasMatch(password)) strength = 2;
      if (password.length >= 10 && RegExp(r'[A-Z]').hasMatch(password) && RegExp(r'[0-9]').hasMatch(password) && RegExp(r'[^a-zA-Z0-9]').hasMatch(password)) strength = 3;
      if (password.length >= 12 && RegExp(r'[A-Z]').hasMatch(password) && RegExp(r'[a-z]').hasMatch(password) && RegExp(r'[0-9]').hasMatch(password) && RegExp(r'[^a-zA-Z0-9]').hasMatch(password)) strength = 4;
    }
    if (_passwordStrength != strength && mounted) setState(() => _passwordStrength = strength);
  }

  Color _getStrengthColor(int index) {
    if (index >= _passwordStrength) return const Color(0xFFE2E8F0);
    if (_passwordStrength == 1) return AppColors.error;
    if (_passwordStrength == 2) return Colors.orange;
    if (_passwordStrength == 3) return Colors.amber;
    return const Color(0xFF10B981);
  }

  String get _strengthLabel {
    switch (_passwordStrength) {
      case 1: return 'Weak';
      case 2: return 'Fair';
      case 3: return 'Good';
      case 4: return 'Strong';
      default: return '';
    }
  }

  @override
  void dispose() {
    _firstNameController.dispose();
    _lastNameController.dispose();
    _usernameController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    _otpController.dispose();
    _termsRecognizer.dispose();
    _privacyRecognizer.dispose();
    super.dispose();
  }

  void _showCombinedPolicyDialog() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
        height: MediaQuery.of(context).size.height * 0.85,
        padding: const EdgeInsets.only(top: 24),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
        ),
        child: Column(
          children: [
            // Handle
            Container(
              width: 44,
              height: 4,
              decoration: BoxDecoration(
                color: const Color(0xFFE2E8F0),
                borderRadius: BorderRadius.circular(4),
              ),
            ),
            const SizedBox(height: 24),
            // Header
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: activeTenant.value.themePrimaryColor.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(Icons.shield_outlined, color: activeTenant.value.themePrimaryColor, size: 24),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Terms & Privacy',
                          style: GoogleFonts.outfit(fontSize: 24, fontWeight: FontWeight.w700, color: AppColors.textMain, letterSpacing: -0.5),
                        ),
                        Text(
                          'Please read carefully before proceeding.',
                          style: GoogleFonts.inter(fontSize: 13, color: AppColors.textMuted),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.pop(ctx),
                    icon: Icon(Icons.close_rounded, color: AppColors.textMuted),
                    style: IconButton.styleFrom(
                      backgroundColor: const Color(0xFFF1F5F9),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            const Divider(color: Color(0xFFF1F5F9), thickness: 1.5),
            // Content
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(24, 16, 24, 24),
                physics: const BouncingScrollPhysics(),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _buildPolicySection(
                      context,
                      'Terms of Service',
                      Icons.gavel_rounded,
                      [
                        _buildPolicyItem('Acceptance of Terms', 'By creating an account and using our services, you agree to comply with our stated policies and guidelines.'),
                        _buildPolicyItem('User Responsibilities', 'You are responsible for safeguarding your account credentials. We are not liable for unauthorized access resulting from your negligence.'),
                        _buildPolicyItem('Financial Agreements', 'Any financial product or loan provided through this platform is subject to a formal contract, credit approval, and agreed terms and conditions.'),
                      ]
                    ),
                    const SizedBox(height: 32),
                    _buildPolicySection(
                      context,
                      'Privacy Policy',
                      Icons.privacy_tip_outlined,
                      [
                        _buildPolicyItem('Data Collection', 'We securely collect personal and financial information necessarily for providing our core services, such as identity verification and processing applications.'),
                        _buildPolicyItem('Data Usage & Sharing', 'Your data is strictly used for evaluating applications and providing our services. We do not sell your personal data to third parties under any circumstances.'),
                        _buildPolicyItem('Data Protection', 'We employ industry-standard encryption and security measures to protect your information and ensure confidentiality.'),
                      ]
                    ),
                    const SizedBox(height: 48),
                    // Action Buttons
                    Container(
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF8FAFC),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Column(
                        children: [
                          Text(
                            'By accepting, you confirm that you have read, understood, and agreed to our Terms of Service and Privacy Policy.',
                            style: GoogleFonts.inter(fontSize: 13, color: AppColors.textMuted, height: 1.5),
                            textAlign: TextAlign.center,
                          ),
                          const SizedBox(height: 20),
                          Row(
                            children: [
                              Expanded(
                                child: OutlinedButton(
                                  onPressed: () => Navigator.pop(ctx),
                                  style: OutlinedButton.styleFrom(
                                    padding: const EdgeInsets.symmetric(vertical: 16),
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                    side: const BorderSide(color: Color(0xFFE2E8F0)),
                                  ),
                                  child: Text('Cancel', style: GoogleFonts.inter(fontWeight: FontWeight.w600, color: AppColors.textMain)),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                flex: 2,
                                child: ElevatedButton(
                                  onPressed: () {
                                    setState(() => _agreed = true);
                                    Navigator.pop(ctx);
                                  },
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: activeTenant.value.themePrimaryColor,
                                    padding: const EdgeInsets.symmetric(vertical: 16),
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                    elevation: 0,
                                  ),
                                  child: Text('I Agree & Continue', style: GoogleFonts.inter(fontWeight: FontWeight.w700, color: Colors.white)),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPolicySection(BuildContext context, String title, IconData icon, List<Widget> items) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: const Color(0xFFF1F5F9),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(icon, size: 20, color: activeTenant.value.themePrimaryColor),
            ),
            const SizedBox(width: 12),
            Text(
              title,
              style: GoogleFonts.outfit(fontSize: 18, fontWeight: FontWeight.w700, color: AppColors.textMain),
            ),
          ],
        ),
        const SizedBox(height: 20),
        ...items,
      ],
    );
  }

  Widget _buildPolicyItem(String title, String description) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 20),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            margin: const EdgeInsets.only(top: 6),
            width: 8,
            height: 8,
            decoration: BoxDecoration(
              color: activeTenant.value.themePrimaryColor.withOpacity(0.5),
              shape: BoxShape.circle,
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: GoogleFonts.inter(fontSize: 15, fontWeight: FontWeight.w600, color: AppColors.textMain),
                ),
                const SizedBox(height: 6),
                Text(
                  description,
                  style: GoogleFonts.inter(fontSize: 14, color: AppColors.textMuted, height: 1.5),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _register() async {
    setState(() { _errorMessage = null; _successMessage = null; });
    if (!_formKey.currentState!.validate()) return;
    if (!_agreed) {
      setState(() => _errorMessage = 'Please agree to the Terms of Service and Privacy Policy.');
      return;
    }
    setState(() => _isLoading = true);
    try {
      final url = Uri.parse(ApiConfig.getUrl('api_register.php'));
      final response = await http.post(
        url,
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'tenant_id': activeTenant.value.id,
          'username': _usernameController.text,
          'email': _emailController.text,
          'password': _passwordController.text,
          'first_name': _firstNameController.text,
          'last_name': _lastNameController.text,
        }),
      );
      final data = jsonDecode(response.body);
      if (data['success'] == true) {
        if (data['requires_otp'] == true) {
          setState(() {
            _currentStep = 2;
            _successMessage = data['message'];
          });
        } else {
          setState(() => _successMessage = data['message'] ?? 'Registration successful!');
          Future.delayed(const Duration(seconds: 2), () {
            if (mounted) Navigator.pop(context);
          });
        }
      } else {
        setState(() => _errorMessage = data['message'] ?? 'Registration failed');
      }
    } catch (e) {
      setState(() => _errorMessage = 'Failed to connect to the server. Please try again.');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _verifyRegistrationOtp() async {
    if (_otpController.text.isEmpty) {
      setState(() => _errorMessage = 'Please enter the verification code.');
      return;
    }
    setState(() { _isLoading = true; _errorMessage = null; _successMessage = null; });
    try {
      final response = await http.post(
        Uri.parse(ApiConfig.getUrl('api_verify_registration_otp.php')),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'email': _emailController.text, 
          'tenant_id': activeTenant.value.id, 
          'otp': _otpController.text
        }),
      );
      final data = jsonDecode(response.body);
      if (data['success'] == true) {
        setState(() => _successMessage = data['message']);
        Future.delayed(const Duration(seconds: 2), () {
          if (mounted) Navigator.pop(context);
        });
      } else {
        setState(() => _errorMessage = data['message']);
      }
    } catch (_) {
      setState(() => _errorMessage = 'Failed to connect to the server');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final tenant = activeTenant.value;
    final primary = tenant.themePrimaryColor;
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    return Container(
      margin: EdgeInsets.only(top: MediaQuery.of(context).padding.top + 20),
      padding: EdgeInsets.only(bottom: bottomInset),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
      ),
      child: CustomScrollView(
        shrinkWrap: true,
        physics: const BouncingScrollPhysics(),
        slivers: [
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(24, 20, 24, 0),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Handle
                    Center(
                      child: Container(
                        width: 44, height: 4,
                        decoration: BoxDecoration(color: const Color(0xFFE2E8F0), borderRadius: BorderRadius.circular(4)),
                      ),
                    ),
                    const SizedBox(height: 24),

                    // Header
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _currentStep == 1 ? 'Create Account' : 'Verify Email',
                                style: GoogleFonts.outfit(fontSize: 26, fontWeight: FontWeight.w800, color: AppColors.textMain, letterSpacing: -0.6),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                _currentStep == 1 ? 'Join ${tenant.appName} today.' : 'Enter the code sent to your email.',
                                style: GoogleFonts.inter(fontSize: 14, color: AppColors.textMuted),
                              ),
                            ],
                          ),
                        ),
                        IconButton(
                          onPressed: () => Navigator.pop(context),
                          icon: Icon(Icons.close_rounded, color: AppColors.textMuted, size: 26),
                          splashRadius: 24,
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),

                    // Alert banners
                    _buildAlert(_errorMessage, isError: true),
                    _buildAlert(_successMessage, isError: false),

                    // Form Fields
                    if (_currentStep == 1) ...[
                      // Name row
                      Row(
                        children: [
                          Expanded(child: _buildModalField('First Name', 'First Name', false, primary, _firstNameController)),
                          const SizedBox(width: 12),
                          Expanded(child: _buildModalField('Last Name', 'Last Name', false, primary, _lastNameController)),
                        ],
                      ),
                      const SizedBox(height: 16),
                      _buildModalField('Username', 'username', false, primary, _usernameController),
                      const SizedBox(height: 16),
                      _buildModalField('Email Address', 'email@example.com', false, primary, _emailController, keyboardType: TextInputType.emailAddress),
                      const SizedBox(height: 16),
                      _buildPasswordField('Password', 'Enter password', _obscurePassword, () => setState(() => _obscurePassword = !_obscurePassword), primary, _passwordController),
                      const SizedBox(height: 8),

                      // Strength meter
                      Row(
                        children: [
                          ...List.generate(4, (i) => Expanded(
                            child: Container(
                              height: 4,
                              margin: EdgeInsets.only(right: i < 3 ? 4 : 0),
                              decoration: BoxDecoration(color: _getStrengthColor(i), borderRadius: BorderRadius.circular(2)),
                            ),
                          )),
                          if (_passwordStrength > 0) ...[
                            const SizedBox(width: 8),
                            Text(_strengthLabel, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: _getStrengthColor(_passwordStrength - 1))),
                          ],
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text('At least 12 chars, mixed case, number & symbol.', style: GoogleFonts.inter(fontSize: 11, color: AppColors.textMuted)),
                      const SizedBox(height: 16),
                      _buildPasswordField('Confirm Password', 'Confirm password', _obscureConfirm, () => setState(() => _obscureConfirm = !_obscureConfirm), primary, _confirmController,
                        validator: (v) {
                          if (v == null || v.isEmpty) return 'Required';
                          if (v != _passwordController.text) return 'Passwords do not match';
                          return null;
                        }),
                      const SizedBox(height: 24),

                      // Terms
                      Row(
                        children: [
                          SizedBox(
                            width: 24, height: 24,
                            child: Checkbox(
                              value: _agreed,
                              onChanged: (v) => setState(() => _agreed = v ?? false),
                              activeColor: primary,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
                              side: BorderSide(color: AppColors.textMuted.withOpacity(0.5)),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text.rich(TextSpan(
                              text: 'I agree to the ',
                              style: GoogleFonts.inter(fontSize: 13, color: AppColors.textMuted),
                              children: [
                                TextSpan(
                                  text: 'Terms of Service', 
                                  style: TextStyle(color: primary, fontWeight: FontWeight.w600, decoration: TextDecoration.underline),
                                  recognizer: _termsRecognizer,
                                ),
                                const TextSpan(text: ' and '),
                                TextSpan(
                                  text: 'Privacy Policy', 
                                  style: TextStyle(color: primary, fontWeight: FontWeight.w600, decoration: TextDecoration.underline),
                                  recognizer: _privacyRecognizer,
                                ),
                              ],
                            )),
                          ),
                        ],
                      ),
                      const SizedBox(height: 24),

                      // CTA Button
                      _PremiumButton(
                        label: 'Create Account',
                        isLoading: _isLoading,
                        primary: primary,
                        secondary: tenant.themeSecondaryColor,
                        onPressed: _register,
                      ),
                      const SizedBox(height: 20),

                      // Footer
                      Center(
                        child: Text.rich(TextSpan(
                          text: 'Already have an account? ',
                          style: GoogleFonts.inter(fontSize: 13, color: AppColors.textMuted),
                          children: [
                            TextSpan(
                              text: 'Log In',
                              style: TextStyle(color: primary, fontWeight: FontWeight.w700),
                            ),
                          ],
                        )),
                      ),
                    ] else ...[
                      // Step 2: OTP Verification
                      _buildModalField('Verification Code', 'Enter 6-digit code', false, primary, _otpController, keyboardType: TextInputType.number),
                      const SizedBox(height: 24),
                      _PremiumButton(
                        label: 'Verify Email',
                        isLoading: _isLoading,
                        primary: primary,
                        secondary: tenant.themeSecondaryColor,
                        onPressed: _verifyRegistrationOtp,
                      ),
                      const SizedBox(height: 20),
                      Center(
                        child: TextButton(
                          onPressed: () => setState(() { _currentStep = 1; _errorMessage = null; _successMessage = null; }),
                          child: Text('Back to Registration', style: TextStyle(color: primary, fontWeight: FontWeight.w600)),
                        ),
                      ),
                    ],
                    const SizedBox(height: 32),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAlert(String? message, {required bool isError}) {
    if (message == null) return const SizedBox.shrink();
    final color = isError ? AppColors.error : const Color(0xFF10B981);
    final icon = isError ? Icons.error_outline_rounded : Icons.check_circle_outline_rounded;
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withOpacity(0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withOpacity(0.25)),
      ),
      child: Row(children: [
        Icon(icon, color: color, size: 20),
        const SizedBox(width: 8),
        Expanded(child: Text(message, style: TextStyle(color: color, fontSize: 13, fontWeight: FontWeight.w500))),
      ]),
    );
  }

  Widget _buildModalField(String label, String hint, bool isFilled, Color primary, TextEditingController controller, {TextInputType? keyboardType}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: GoogleFonts.inter(fontSize: 12, fontWeight: FontWeight.w600, color: AppColors.textMain)),
        const SizedBox(height: 6),
        TextFormField(
          controller: controller,
          keyboardType: keyboardType,
          style: GoogleFonts.inter(fontSize: 14, color: AppColors.textMain, fontWeight: FontWeight.w500),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: GoogleFonts.inter(fontSize: 14, color: AppColors.textMuted.withOpacity(0.5)),
            filled: true,
            fillColor: const Color(0xFFF8FAFC),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: primary.withOpacity(0.6), width: 1.5)),
          ),
          validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
        ),
      ],
    );
  }

  Widget _buildPasswordField(String label, String hint, bool obscure, VoidCallback onToggle, Color primary, TextEditingController controller, {String? Function(String?)? validator}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: GoogleFonts.inter(fontSize: 12, fontWeight: FontWeight.w600, color: AppColors.textMain)),
        const SizedBox(height: 6),
        TextFormField(
          controller: controller,
          obscureText: obscure,
          style: GoogleFonts.inter(fontSize: 14, color: AppColors.textMain, fontWeight: FontWeight.w500),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: GoogleFonts.inter(fontSize: 14, color: AppColors.textMuted.withOpacity(0.5)),
            filled: true,
            fillColor: const Color(0xFFF8FAFC),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            suffixIcon: GestureDetector(
              onTap: onToggle,
              child: Icon(obscure ? Icons.visibility_off_outlined : Icons.visibility_outlined, color: AppColors.textMuted, size: 20),
            ),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: primary.withOpacity(0.6), width: 1.5)),
          ),
          validator: validator ?? ((v) => v == null || v.isEmpty ? 'Required' : null),
        ),
      ],
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// FORGOT PASSWORD MODAL
// ─────────────────────────────────────────────────────────────────────────────
class _ForgotPasswordModal extends StatefulWidget {
  const _ForgotPasswordModal();

  @override
  State<_ForgotPasswordModal> createState() => _ForgotPasswordModalState();
}

class _ForgotPasswordModalState extends State<_ForgotPasswordModal> {
  final _emailController = TextEditingController();
  final _codeController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  bool _isLoading = false;
  int _currentStep = 1;
  String? _errorMessage;
  String? _successMessage;
  bool _obscurePassword = true;
  bool _obscureConfirm = true;
  int _passwordStrength = 0;

  @override
  void initState() {
    super.initState();
    _passwordController.addListener(_updatePasswordStrength);
  }

  void _updatePasswordStrength() {
    final password = _passwordController.text;
    int strength = 0;
    if (password.isNotEmpty) {
      if (password.length >= 8) strength = 1;
      if (password.length >= 8 && RegExp(r'[A-Za-z]').hasMatch(password) && RegExp(r'[0-9]').hasMatch(password)) strength = 2;
      if (password.length >= 10 && RegExp(r'[A-Z]').hasMatch(password) && RegExp(r'[0-9]').hasMatch(password) && RegExp(r'[^a-zA-Z0-9]').hasMatch(password)) strength = 3;
      if (password.length >= 12 && RegExp(r'[A-Z]').hasMatch(password) && RegExp(r'[a-z]').hasMatch(password) && RegExp(r'[0-9]').hasMatch(password) && RegExp(r'[^a-zA-Z0-9]').hasMatch(password)) strength = 4;
    }
    if (_passwordStrength != strength && mounted) setState(() => _passwordStrength = strength);
  }

  Color _getStrengthColor(int index) {
    if (index >= _passwordStrength) return const Color(0xFFE2E8F0);
    if (_passwordStrength == 1) return AppColors.error;
    if (_passwordStrength == 2) return Colors.orange;
    if (_passwordStrength == 3) return Colors.amber;
    return const Color(0xFF10B981);
  }

  Future<void> _verifyEmail() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() { _isLoading = true; _errorMessage = null; _successMessage = null; });
    try {
      final response = await http.post(
        Uri.parse(ApiConfig.getUrl('api_forgot_password.php')),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({'email': _emailController.text, 'tenant_id': activeTenant.value.id}),
      );
      final data = jsonDecode(response.body);
      if (data['success'] == true) {
        setState(() { _currentStep = 2; _successMessage = data['message']; });
      } else {
        setState(() => _errorMessage = data['message']);
      }
    } catch (_) {
      setState(() => _errorMessage = 'Failed to connect to the server');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _verifyOtp() async {
    if (_codeController.text.isEmpty) {
      setState(() => _errorMessage = 'Please enter the verification code.');
      return;
    }
    setState(() { _isLoading = true; _errorMessage = null; _successMessage = null; });
    try {
      final response = await http.post(
        Uri.parse(ApiConfig.getUrl('api_verify_otp.php')),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({'email': _emailController.text, 'tenant_id': activeTenant.value.id, 'reset_code': _codeController.text}),
      );
      final data = jsonDecode(response.body);
      if (data['success'] == true) {
        setState(() { _currentStep = 3; _successMessage = data['message']; });
      } else {
        setState(() => _errorMessage = data['message']);
      }
    } catch (_) {
      setState(() => _errorMessage = 'Failed to connect to the server');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _resetPassword() async {
    if (!_formKey.currentState!.validate()) return;
    if (_passwordStrength < 4) { setState(() => _errorMessage = 'Please choose a stronger password.'); return; }
    if (_passwordController.text != _confirmController.text) { setState(() => _errorMessage = 'Passwords do not match'); return; }
    setState(() { _isLoading = true; _errorMessage = null; _successMessage = null; });
    try {
      final response = await http.post(
        Uri.parse(ApiConfig.getUrl('api_reset_password.php')),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({'email': _emailController.text, 'tenant_id': activeTenant.value.id, 'reset_code': _codeController.text, 'new_password': _passwordController.text}),
      );
      final data = jsonDecode(response.body);
      if (data['success'] == true) {
        setState(() => _successMessage = data['message']);
        Future.delayed(const Duration(seconds: 2), () { if (mounted) Navigator.pop(context); });
      } else {
        setState(() => _errorMessage = data['message']);
      }
    } catch (_) {
      setState(() => _errorMessage = 'Failed to connect to the server');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  void dispose() {
    _emailController.dispose();
    _codeController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final tenant = activeTenant.value;
    final primary = tenant.themePrimaryColor;
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    final stepTitles = ['Forgot Password', 'Verify Code', 'Reset Password'];
    final stepSubs = [
      'Enter your email to receive a reset code.',
      'Enter the 6-digit code sent to your email.',
      'Choose a strong new password.',
    ];

    return Container(
      margin: EdgeInsets.only(top: MediaQuery.of(context).padding.top + 100),
      padding: EdgeInsets.fromLTRB(24, 20, 24, bottomInset + 24),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
      ),
      child: Material(
        color: Colors.transparent,
        child: SingleChildScrollView(
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 44, height: 4,
                    decoration: BoxDecoration(color: const Color(0xFFE2E8F0), borderRadius: BorderRadius.circular(4)),
                  ),
                ),
                const SizedBox(height: 20),

                // Step indicator dots
                Row(
                  children: List.generate(3, (i) => Container(
                    width: i == _currentStep - 1 ? 24 : 8,
                    height: 8,
                    margin: const EdgeInsets.only(right: 6),
                    decoration: BoxDecoration(
                      color: i == _currentStep - 1 ? primary : primary.withOpacity(0.25),
                      borderRadius: BorderRadius.circular(4),
                    ),
                  )),
                ),
                const SizedBox(height: 16),

                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      stepTitles[_currentStep - 1],
                      style: GoogleFonts.outfit(fontSize: 24, fontWeight: FontWeight.w800, color: AppColors.textMain),
                    ),
                    IconButton(
                      onPressed: () => Navigator.pop(context),
                      icon: Icon(Icons.close_rounded, color: AppColors.textMuted),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(stepSubs[_currentStep - 1], style: GoogleFonts.inter(color: AppColors.textMuted, fontSize: 14)),
                const SizedBox(height: 20),

                // Alerts
                if (_errorMessage != null)
                  _buildAlert(_errorMessage!, isError: true, primary: primary),
                if (_successMessage != null)
                  _buildAlert(_successMessage!, isError: false, primary: primary),

                if (_currentStep == 1) ...[
                  _buildSimpleField('Email Address', 'email@example.com', _emailController, primary,
                    icon: Icons.email_outlined,
                    keyboardType: TextInputType.emailAddress,
                    validator: (v) => v == null || !v.contains('@') ? 'Enter a valid email' : null,
                  ),
                  const SizedBox(height: 24),
                  _PremiumButton(label: 'Send Reset Code', isLoading: _isLoading, primary: primary, secondary: tenant.themeSecondaryColor, onPressed: _verifyEmail),
                ] else if (_currentStep == 2) ...[
                  _buildSimpleField('Verification Code', 'Enter 6-digit code', _codeController, primary,
                    icon: Icons.pin_outlined,
                    keyboardType: TextInputType.number,
                    validator: (v) => v == null || v.isEmpty ? 'Required' : null,
                  ),
                  const SizedBox(height: 24),
                  _PremiumButton(label: 'Verify Code', isLoading: _isLoading, primary: primary, secondary: tenant.themeSecondaryColor, onPressed: _verifyOtp),
                ] else ...[
                  _buildSimpleField('New Password', 'Enter new password', _passwordController, primary,
                    icon: Icons.lock_outline,
                    obscureText: _obscurePassword,
                    suffix: IconButton(
                      icon: Icon(_obscurePassword ? Icons.visibility_off : Icons.visibility, size: 20),
                      onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                    ),
                    validator: (v) => v == null || v.isEmpty ? 'Required' : null,
                  ),
                  const SizedBox(height: 8),
                  Row(children: List.generate(4, (i) => Expanded(
                    child: Container(
                      height: 4,
                      margin: EdgeInsets.only(right: i < 3 ? 4 : 0),
                      decoration: BoxDecoration(color: _getStrengthColor(i), borderRadius: BorderRadius.circular(2)),
                    ),
                  ))),
                  const SizedBox(height: 6),
                  Text('At least 12 chars, mixed case, number & symbol.', style: GoogleFonts.inter(fontSize: 11, color: AppColors.textMuted)),
                  const SizedBox(height: 16),
                  _buildSimpleField('Confirm Password', 'Confirm new password', _confirmController, primary,
                    icon: Icons.lock_outline,
                    obscureText: _obscureConfirm,
                    suffix: IconButton(
                      icon: Icon(_obscureConfirm ? Icons.visibility_off : Icons.visibility, size: 20),
                      onPressed: () => setState(() => _obscureConfirm = !_obscureConfirm),
                    ),
                    validator: (v) => v != _passwordController.text ? 'Passwords do not match' : null,
                  ),
                  const SizedBox(height: 24),
                  _PremiumButton(label: 'Reset Password', isLoading: _isLoading, primary: primary, secondary: tenant.themeSecondaryColor, onPressed: _resetPassword),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildAlert(String message, {required bool isError, required Color primary}) {
    final color = isError ? AppColors.error : const Color(0xFF10B981);
    final icon = isError ? Icons.error_outline_rounded : Icons.check_circle_outline_rounded;
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withOpacity(0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withOpacity(0.25)),
      ),
      child: Row(children: [
        Icon(icon, color: color, size: 20),
        const SizedBox(width: 8),
        Expanded(child: Text(message, style: TextStyle(color: color, fontSize: 13, fontWeight: FontWeight.w500))),
      ]),
    );
  }

  Widget _buildSimpleField(String label, String hint, TextEditingController controller, Color primary, {
    IconData? icon,
    bool obscureText = false,
    Widget? suffix,
    TextInputType? keyboardType,
    String? Function(String?)? validator,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: GoogleFonts.inter(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.textMain)),
        const SizedBox(height: 8),
        TextFormField(
          controller: controller,
          obscureText: obscureText,
          keyboardType: keyboardType,
          style: GoogleFonts.inter(fontSize: 14, color: AppColors.textMain, fontWeight: FontWeight.w500),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: GoogleFonts.inter(fontSize: 14, color: AppColors.textMuted.withOpacity(0.5)),
            prefixIcon: icon != null ? Icon(icon, color: primary, size: 20) : null,
            suffixIcon: suffix,
            filled: true,
            fillColor: const Color(0xFFF8FAFC),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: primary.withOpacity(0.6), width: 1.5)),
          ),
          validator: validator,
        ),
      ],
    );
  }
}
