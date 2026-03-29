import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../main.dart';
import '../theme.dart';

class LoanApplicationScreen extends StatefulWidget {
  const LoanApplicationScreen({super.key});
  @override
  State<LoanApplicationScreen> createState() => _LoanApplicationScreenState();
}

class _LoanApplicationScreenState extends State<LoanApplicationScreen> {
  final PageController _pageCtrl = PageController();
  int _currentStep = 0;
  final int _totalSteps = 3;

  // ──── STEP 0: Loan Setup ────
  int _selectedProduct = 0;
  double _loanAmount = 5000;
  int _selectedTerm = 6;

  static const _products = [
    {'name': 'Personal Loan', 'rate': 2.0, 'min_term': 6, 'max_term': 24, 'min': 5000.0, 'max': 100000.0, 'icon': Icons.person_outline_rounded},
    {'name': 'Business Loan', 'rate': 3.0, 'min_term': 12, 'max_term': 60, 'min': 10000.0, 'max': 500000.0, 'icon': Icons.business_center_outlined},
    {'name': 'Emergency Loan', 'rate': 1.5, 'min_term': 3, 'max_term': 12, 'min': 1000.0, 'max': 30000.0, 'icon': Icons.local_hospital_outlined},
  ];

  List<int> get _terms {
    int minT = (_product['min_term'] ?? 6) as int;
    int maxT = (_product['max_term'] ?? 24) as int;
    return [3, 6, 9, 12, 18, 24, 36, 48, 60].where((t) => t >= minT && t <= maxT).toList();
  }

  Map<String, dynamic> get _product => _products[_selectedProduct];
  double get _rate => (_product['rate'] as num).toDouble() / 100;
  double get _monthly => (_loanAmount + (_loanAmount * _rate * _selectedTerm)) / _selectedTerm;

  // ──── STEP 1: Purpose & Docs ────
  String _purposeCategory = 'Personal';
  final _purposeDescCtrl = TextEditingController();
  final Map<int, String?> _selectedDocs = {};
  final Map<String, TextEditingController> _appDataCtrls = {};

  bool _isSubmitting = false;

  @override
  void dispose() {
    _pageCtrl.dispose();
    _purposeDescCtrl.dispose();
    for (var c in _appDataCtrls.values) { c.dispose(); }
    super.dispose();
  }

  TextEditingController _getDynamicCtrl(String key) {
    if (!_appDataCtrls.containsKey(key)) _appDataCtrls[key] = TextEditingController();
    return _appDataCtrls[key]!;
  }

  void _goNext() {
    if (_currentStep < _totalSteps - 1) {
      if (_currentStep == 0 && _loanAmount <= 0) return;
      if (_currentStep == 1) {
        if (_purposeDescCtrl.text.isEmpty) {
          _showSnack('Please provide a specific purpose description.');
          return;
        }
        final reqDocs = _getRequiredDocs();
        for (var d in reqDocs) {
          int id = d['id'] as int;
          if (_selectedDocs[id] == null) {
            _showSnack('Please upload all required documents.');
            return;
          }
        }
      }
      HapticFeedback.lightImpact();
      setState(() => _currentStep++);
      _pageCtrl.animateToPage(_currentStep, duration: const Duration(milliseconds: 300), curve: Curves.easeOut);
    }
  }

  void _goBack() {
    if (_currentStep > 0) {
      setState(() => _currentStep--);
      _pageCtrl.animateToPage(_currentStep, duration: const Duration(milliseconds: 300), curve: Curves.easeOut);
    } else {
      Navigator.pop(context);
    }
  }

  List<Map<String, dynamic>> _getRequiredDocs() {
    if (_purposeCategory == 'Business') return [{'id': 1, 'name': 'Business Permit'}];
    if (_purposeCategory == 'Medical') return [{'id': 2, 'name': 'Medical Certificate'}];
    if (_purposeCategory == 'Education') return [{'id': 3, 'name': 'Enrollment Form'}];
    if (_purposeCategory == 'Housing') return [{'id': 4, 'name': 'Bill of Materials'}];
    if (_purposeCategory == 'Agricultural') return [{'id': 5, 'name': 'Land Title / Brgy. Certification'}];
    return [];
  }

  List<Map<String, dynamic>> _getOptionalDocs() {
    if (_purposeCategory == 'Business') return [{'id': 6, 'name': 'DTI/SEC Registration'}];
    if (_purposeCategory == 'Medical') return [{'id': 7, 'name': 'Clinical Abstract'}];
    if (_purposeCategory == 'Education') return [{'id': 8, 'name': 'School ID'}];
    return [];
  }

  Future<void> _submit() async {
    HapticFeedback.mediumImpact();
    setState(() => _isSubmitting = true);
    
    // Mock network request
    await Future.delayed(const Duration(milliseconds: 1800));
    
    if (!mounted) return;
    setState(() => _isSubmitting = false);
    _showSuccessDialog('APP2026-003841');
  }

  void _showSnack(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(msg), behavior: SnackBarBehavior.floating));
  }

  void _showSuccessDialog(String appNum) {
    final primary = activeTenant.value.primaryColor;
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        backgroundColor: AppColors.card,
        contentPadding: const EdgeInsets.all(28),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(
            width: 72, height: 72,
            decoration: BoxDecoration(color: primary.withOpacity(0.1), shape: BoxShape.circle),
            child: Icon(Icons.check_rounded, color: primary, size: 36),
          ),
          const SizedBox(height: 20),
          const Text('Application Submitted!',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.textMain, letterSpacing: -0.4)),
          const SizedBox(height: 10),
          Text('Ref No: $appNum',
              style: TextStyle(fontWeight: FontWeight.w700, color: primary)),
          const SizedBox(height: 10),
          const Text('Your loan application will be reviewed matching your verified profile.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 14, color: AppColors.textMuted, height: 1.5)),
          const SizedBox(height: 24),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: () {
                Navigator.pop(context); // dialog
                Navigator.pop(context); // screen
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: primary,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
              child: const Text('Back to Dashboard', style: TextStyle(fontWeight: FontWeight.bold)),
            ),
          ),
        ]),
      ),
    );
  }

  // ── BUILD ──────────────────────────────────────────────────────────────
  @override
  Widget build(BuildContext context) {
    final primary = activeTenant.value.primaryColor;
    final steps = ['Loan Details', 'Purpose', 'Review'];

    return Scaffold(
      backgroundColor: AppColors.bg,
      body: SafeArea(
        bottom: false,
        child: Column(children: [
          _buildHeader(context, primary, steps),
          Expanded(
            child: PageView(
              controller: _pageCtrl,
              physics: const NeverScrollableScrollPhysics(),
              children: [
                _buildStep0(primary),
                _buildStep1(primary),
                _buildStep2(primary),
              ],
            ),
          ),
          _buildBottomNav(primary),
        ]),
      ),
    );
  }

  // ── Consistent flat header ─────────────────────────────────────────────
  Widget _buildHeader(BuildContext context, Color primary, List<String> steps) {
    final tenant = activeTenant.value;
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 12),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  GestureDetector(
                    onTap: _goBack,
                    child: Container(
                      width: 48, height: 48,
                      decoration: const BoxDecoration(
                          shape: BoxShape.circle, color: Color(0xFF1F2937)),
                      child: const Icon(Icons.arrow_back_rounded,
                          color: Colors.white, size: 22),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(tenant.appName,
                          style: const TextStyle(
                              fontSize: 15, fontWeight: FontWeight.w700,
                              color: Color(0xFF0F292B), height: 1.1, letterSpacing: -0.3)),
                      Text(steps[_currentStep],
                          style: const TextStyle(
                              fontSize: 15, fontWeight: FontWeight.w700,
                              color: Color(0xFF0F292B), height: 1.1, letterSpacing: -0.3)),
                    ],
                  ),
                ],
              ),
              // Step counter badge
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(
                  color: primary.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  '${_currentStep + 1} / $_totalSteps',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: primary),
                ),
              ),
            ],
          ),
        ),
        // Progress bar
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: LinearProgressIndicator(
                  value: (_currentStep + 1) / _totalSteps,
                  minHeight: 6,
                  backgroundColor: AppColors.separator,
                  valueColor: AlwaysStoppedAnimation<Color>(primary),
                ),
              ),
              const SizedBox(height: 8),
              // Step labels
              Row(
                children: List.generate(_totalSteps, (i) {
                  final active = i == _currentStep;
                  final done = i < _currentStep;
                  return Expanded(
                    child: Text(
                      ['Loan Details', 'Purpose', 'Review'][i],
                      textAlign: i == 0 ? TextAlign.left : i == _totalSteps - 1 ? TextAlign.right : TextAlign.center,
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: active ? FontWeight.w800 : FontWeight.w500,
                        color: active ? primary : done ? primary.withOpacity(0.6) : AppColors.textMuted,
                      ),
                    ),
                  );
                }),
              ),
            ],
          ),
        ),
        const Divider(height: 1, color: AppColors.separator),
      ],
    );
  }

  Widget _buildBottomNav(Color primary) {
    final isLast = _currentStep == _totalSteps - 1;
    return Container(
      padding: EdgeInsets.fromLTRB(20, 14, 20, MediaQuery.of(context).padding.bottom + 14),
      decoration: const BoxDecoration(
          color: AppColors.card, border: Border(top: BorderSide(color: AppColors.separator))),
      child: Row(children: [
        if (_currentStep > 0)
          Expanded(
            flex: 1,
            child: OutlinedButton(
              onPressed: _goBack,
              style: OutlinedButton.styleFrom(
                side: BorderSide(color: primary),
                padding: const EdgeInsets.symmetric(vertical: 15),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              child: Text('Back', style: TextStyle(color: primary, fontWeight: FontWeight.w700)),
            ),
          ),
        if (_currentStep > 0) const SizedBox(width: 12),
        Expanded(
          flex: 2,
          child: ElevatedButton(
            onPressed: _isSubmitting ? null : () {
              if (isLast) { _submit(); } else { _goNext(); }
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: primary,
              foregroundColor: Colors.white,
              disabledBackgroundColor: primary.withOpacity(0.5),
              elevation: 0,
              padding: const EdgeInsets.symmetric(vertical: 15),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            child: _isSubmitting
                ? const SizedBox(width: 22, height: 22,
                    child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                : Text(isLast ? 'Submit Application' : 'Continue →',
                    style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
          ),
        ),
      ]),
    );
  }

  // ── Steps ──────────────────────────────────────────────────────────────
  Widget _buildStep0(Color primary) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _sectionLabel('Select Loan Product'),
        const SizedBox(height: 12),
        Column(children: _products.asMap().entries
            .map((e) => _productCard(e.key, e.value, primary)).toList()),
        const SizedBox(height: 24),
        _sectionLabel('Loan Amount'),
        const SizedBox(height: 12),
        Center(
          child: Column(children: [
            Text('₱${_loanAmount.toStringAsFixed(0)}',
                style: TextStyle(fontSize: 36, fontWeight: FontWeight.w900, color: primary, letterSpacing: -1)),
            const Text('Requested Amount',
                style: TextStyle(fontSize: 12, color: AppColors.textMuted)),
          ]),
        ),
        const SizedBox(height: 6),
        SliderTheme(
          data: SliderTheme.of(context).copyWith(
            activeTrackColor: primary,
            inactiveTrackColor: AppColors.separator,
            thumbColor: primary,
            overlayColor: primary.withOpacity(0.15),
            trackHeight: 5,
          ),
          child: Slider(
            value: _loanAmount,
            min: (_product['min'] as num).toDouble(),
            max: (_product['max'] as num).toDouble(),
            onChanged: (v) => setState(() => _loanAmount = v),
          ),
        ),
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Text('Min: ₱${(_product['min'] as num).toInt()}',
              style: const TextStyle(fontSize: 11, color: AppColors.textMuted)),
          Text('Max: ₱${(_product['max'] as num).toInt()}',
              style: const TextStyle(fontSize: 11, color: AppColors.textMuted)),
        ]),
        const SizedBox(height: 24),
        _sectionLabel('Repayment Duration'),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(4),
          decoration: BoxDecoration(color: AppColors.card, borderRadius: BorderRadius.circular(16)),
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(children: _terms.map((t) {
              final sel = _selectedTerm == t;
              return GestureDetector(
                onTap: () => setState(() => _selectedTerm = t),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                  decoration: BoxDecoration(
                    color: sel ? primary : Colors.transparent,
                    borderRadius: BorderRadius.circular(12),
                    boxShadow: sel ? [BoxShadow(color: primary.withOpacity(0.3), blurRadius: 8, offset: const Offset(0, 3))] : [],
                  ),
                  child: Text('$t mos',
                      style: TextStyle(
                        color: sel ? Colors.white : AppColors.textMain,
                        fontWeight: sel ? FontWeight.bold : FontWeight.w600,
                        fontSize: 13,
                      )),
                ),
              );
            }).toList()),
          ),
        ),
      ]),
    );
  }

  Widget _buildStep1(Color primary) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _formCard([
          _dropdownField('Purpose Category', _purposeCategory,
              ['Personal', 'Business', 'Education', 'Medical', 'Housing', 'Agricultural'],
              (v) => setState(() { _purposeCategory = v!; _selectedDocs.clear(); }),
              icon: Icons.category_outlined),
          const SizedBox(height: 14),
          _inputField('Specific Purpose Description *', _purposeDescCtrl,
              maxLines: 3, icon: Icons.description_outlined),
        ]),
        const SizedBox(height: 16),
        if (_purposeCategory == 'Business') _formCard([
          _sectionLabel('Business Details'),
          const SizedBox(height: 12),
          _inputField('Business Name', _getDynamicCtrl('business_name')),
          const SizedBox(height: 12),
          _inputField('Nature of Business', _getDynamicCtrl('business_nature')),
          const SizedBox(height: 12),
          _inputField('Years in Operation', _getDynamicCtrl('business_years'),
              keyboard: TextInputType.number),
        ]),
        if (_purposeCategory == 'Medical') _formCard([
          _sectionLabel('Medical Emergency Details'),
          const SizedBox(height: 12),
          _inputField('Patient Name', _getDynamicCtrl('med_patient_name')),
          const SizedBox(height: 12),
          _inputField('Hospital/Clinic', _getDynamicCtrl('med_hospital')),
        ]),
        if (_purposeCategory == 'Education') _formCard([
          _sectionLabel('Educational Details'),
          const SizedBox(height: 12),
          _inputField('Student Name', _getDynamicCtrl('edu_student_name')),
          const SizedBox(height: 12),
          _inputField('School Name', _getDynamicCtrl('edu_school')),
        ]),
        const SizedBox(height: 16),
        if (_getRequiredDocs().isNotEmpty || _getOptionalDocs().isNotEmpty) ...[
          _sectionLabel('Supporting Documents'),
          const SizedBox(height: 12),
          ..._getRequiredDocs().map((d) => _docPicker(d, primary, required: true)),
          ..._getOptionalDocs().map((d) => _docPicker(d, primary, required: false)),
        ] else ...[
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.card, borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppColors.separator.withOpacity(0.6)),
            ),
            child: Row(children: [
              const Icon(Icons.info_outline, color: AppColors.textMuted),
              const SizedBox(width: 8),
              Expanded(child: const Text(
                'No additional documents required for this category. Your verified KYC documents will be used.',
                style: TextStyle(fontSize: 12, color: AppColors.textMuted),
              )),
            ]),
          ),
        ],
      ]),
    );
  }

  Widget _buildStep2(Color primary) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            gradient: AppColors.primaryGradient(primary, activeTenant.value.secondaryColor),
            borderRadius: BorderRadius.circular(20),
            boxShadow: AppColors.elevatedShadow(primary),
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Loan Summary',
                style: TextStyle(color: Colors.white.withOpacity(0.75), fontSize: 12, fontWeight: FontWeight.w600, letterSpacing: 0.5)),
            const SizedBox(height: 10),
            Text(_product['name'] ?? '',
                style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800)),
            const SizedBox(height: 6),
            Text('₱${_loanAmount.toStringAsFixed(0)} • ${_selectedTerm} months',
                style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 14)),
            const SizedBox(height: 16),
            Row(children: [
              Expanded(child: _reviewStat('Monthly', '₱${_monthly.toStringAsFixed(0)}')),
              Container(width: 1, height: 28, color: Colors.white.withOpacity(0.24)),
              Expanded(child: _reviewStat('Interest', '${_rate * 100 ~/ 1}%/mo')),
              Container(width: 1, height: 28, color: Colors.white.withOpacity(0.24)),
              Expanded(child: _reviewStat('Total', '₱${(_monthly * _selectedTerm).toStringAsFixed(0)}')),
            ]),
          ]),
        ),
        const SizedBox(height: 16),
        _reviewCard('Purpose Details', Icons.description_outlined, primary, [
          _reviewRow('Category', _purposeCategory),
          _reviewRow('Description', _purposeDescCtrl.text),
        ]),
        const SizedBox(height: 12),
        if (_getRequiredDocs().isNotEmpty || _getOptionalDocs().isNotEmpty)
          _reviewCard('Documents', Icons.folder_copy_outlined, primary, [
            _reviewRow('Uploaded',
                '${_selectedDocs.values.where((v) => v != null).length} Purpose-specific documents'),
          ]),
        const SizedBox(height: 16),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.bg,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: AppColors.separator.withOpacity(0.6)),
          ),
          child: Row(children: [
            const Icon(Icons.info_outline_rounded, color: AppColors.info, size: 18),
            const SizedBox(width: 10),
            const Expanded(child: Text(
              'By submitting, you agree to have this loan application reviewed alongside your verified profile credentials.',
              style: TextStyle(fontSize: 12, color: AppColors.textMain, height: 1.5),
            )),
          ]),
        ),
      ]),
    );
  }

  // ── Shared widgets ─────────────────────────────────────────────────────
  Widget _sectionLabel(String t) =>
      Text(t, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.textMain));

  Widget _formCard(List<Widget> children) => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: AppColors.card,
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: AppColors.separator.withOpacity(0.6)),
      boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 16, offset: const Offset(0, 4))],
    ),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: children),
  );

  Widget _inputField(String label, TextEditingController ctrl,
      {IconData? icon, TextInputType keyboard = TextInputType.text, int maxLines = 1}) {
    return TextFormField(
      controller: ctrl,
      keyboardType: keyboard,
      maxLines: maxLines,
      style: const TextStyle(fontSize: 14, color: AppColors.textMain),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: const TextStyle(fontSize: 13, color: AppColors.textMuted),
        prefixIcon: icon != null ? Icon(icon, size: 18, color: AppColors.textMuted) : null,
      ),
    );
  }

  Widget _dropdownField(String label, String value, List<String> items,
      void Function(String?) onChanged, {IconData? icon}) {
    return DropdownButtonFormField<String>(
      value: value,
      onChanged: onChanged,
      style: const TextStyle(fontSize: 14, color: AppColors.textMain),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: const TextStyle(fontSize: 13, color: AppColors.textMuted),
        prefixIcon: icon != null ? Icon(icon, size: 18, color: AppColors.textMuted) : null,
      ),
      items: items.map((e) => DropdownMenuItem(value: e, child: Text(e))).toList(),
    );
  }

  Widget _productCard(int i, Map<String, dynamic> p, Color primary) {
    if (!p.containsKey('icon')) {
      if (p['name'] == 'Business Loan') p['icon'] = Icons.business_center_outlined;
      else if (p['name'] == 'Emergency Loan') p['icon'] = Icons.local_hospital_outlined;
      else p['icon'] = Icons.person_outline_rounded;
    }
    final sel = _selectedProduct == i;
    return GestureDetector(
      onTap: () => setState(() {
        _selectedProduct = i;
        _loanAmount = ((p['min'] as num) + (p['max'] as num)) / 2;
        final t = _terms;
        if (t.isNotEmpty && !t.contains(_selectedTerm)) _selectedTerm = t.first;
      }),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: sel ? primary.withOpacity(0.06) : AppColors.card,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: sel ? primary : AppColors.separator, width: sel ? 2 : 1),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 12, offset: const Offset(0, 4))],
        ),
        child: Row(children: [
          Container(
            width: 44, height: 44,
            decoration: BoxDecoration(
              color: sel ? primary.withOpacity(0.12) : AppColors.surfaceVariant,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(p['icon'] as IconData, color: sel ? primary : AppColors.textMuted, size: 22),
          ),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(p['name'] as String,
                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700,
                    color: sel ? primary : AppColors.textMain)),
            const SizedBox(height: 4),
            Text('${p['rate']}% interest',
                style: const TextStyle(fontSize: 12, color: AppColors.textMuted, fontWeight: FontWeight.w600)),
          ])),
          Container(
            width: 22, height: 22,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: sel ? primary : AppColors.separator, width: 2),
            ),
            child: sel
                ? Center(child: Container(width: 10, height: 10,
                    decoration: BoxDecoration(color: primary, shape: BoxShape.circle)))
                : null,
          ),
        ]),
      ),
    );
  }

  Widget _docPicker(Map<String, dynamic> d, Color primary, {required bool required}) {
    int id = d['id'] as int;
    bool isSel = _selectedDocs[id] != null;
    return GestureDetector(
      onTap: () => setState(() => _selectedDocs[id] = '/mock/path/to/image_$id.jpg'),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: isSel ? primary.withOpacity(0.05) : AppColors.card,
          border: Border.all(color: isSel ? primary : AppColors.separator),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Row(children: [
          Container(
            width: 40, height: 40,
            decoration: BoxDecoration(
              color: isSel ? primary : AppColors.bg,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              isSel ? Icons.check_circle_rounded : Icons.camera_alt_outlined,
              color: isSel ? Colors.white : AppColors.textMuted, size: 20,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('${d['name']}${required ? ' *' : ''}',
                style: TextStyle(fontWeight: FontWeight.w700,
                    color: isSel ? primary : AppColors.textMain, fontSize: 13)),
            const SizedBox(height: 2),
            Text(isSel ? 'Image selected' : 'Tap to select document',
                style: TextStyle(color: isSel ? primary.withOpacity(0.8) : AppColors.textMuted, fontSize: 11)),
          ])),
        ]),
      ),
    );
  }

  Widget _reviewStat(String label, String value) {
    return Column(children: [
      Text(label, style: TextStyle(color: Colors.white.withOpacity(0.7), fontSize: 11)),
      const SizedBox(height: 2),
      Text(value, style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w700)),
    ]);
  }

  Widget _reviewCard(String title, IconData icon, Color primary, List<Widget> children) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppColors.card, borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.separator.withOpacity(0.6)),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Icon(icon, size: 18, color: primary),
          const SizedBox(width: 8),
          Text(title, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: primary)),
        ]),
        const SizedBox(height: 14),
        ...children,
      ]),
    );
  }

  Widget _reviewRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        SizedBox(width: 100, child: Text(label, style: const TextStyle(fontSize: 12, color: AppColors.textMuted))),
        Expanded(child: Text(value, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.textMain))),
      ]),
    );
  }
}
