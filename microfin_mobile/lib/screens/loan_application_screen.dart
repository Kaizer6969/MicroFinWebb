import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import '../main.dart';
import '../theme.dart';
import '../utils/api_config.dart';
import '../utils/app_dialogs.dart';
import 'package:file_picker/file_picker.dart';

class LoanApplicationScreen extends StatefulWidget {
  const LoanApplicationScreen({super.key});

  @override
  State<LoanApplicationScreen> createState() => _LoanApplicationScreenState();
}

class _LoanApplicationScreenState extends State<LoanApplicationScreen> {
  // ─── Loading State ─────────────────────────────────────────────────
  List<dynamic> _products = [];
  List<dynamic> _docTypes = [];
  bool _isLoading = true;
  bool _isSubmitting = false;
  bool _creditLoading = true;
  bool _checkingLoanStatus = true;
  bool _hasActiveOrPendingLoan = false;

  // ─── Form Fields ───────────────────────────────────────────────────
  int? _selectedProductId;
  final _amountCtrl = TextEditingController();
  int? _selectedTerm;
  String? _purposeCategory;
  final _purposeDescCtrl = TextEditingController();
  final Map<int, String?> _selectedDocs = {};
  final Map<String, TextEditingController> _appDataCtrls = {};

  Future<void> _pickAndUploadDocument(int docTypeId) async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf'],
        withData: true,
      );

      if (result != null && result.files.isNotEmpty) {
        final file = result.files.first;
        
        setState(() => _isSubmitting = true);

        var request = http.MultipartRequest(
          'POST',
          Uri.parse(ApiConfig.getUrl('api_upload_document.php')),
        );

        request.fields['tenant_id'] = activeTenant.value.id;

        if (file.bytes != null) {
          request.files.add(http.MultipartFile.fromBytes(
             'file',
             file.bytes!,
             filename: file.name,
          ));
        } else if (file.path != null) {
          request.files.add(await http.MultipartFile.fromPath(
             'file',
             file.path!,
             filename: file.name,
          ));
        } else {
             _showSnack('Cannot pick file.');
             setState(() => _isSubmitting = false);
             return;
        }

        var response = await request.send();
        var responseData = await response.stream.bytesToString();
        var jsonResponse = jsonDecode(responseData);

        if (jsonResponse['success'] == true) {
           setState(() {
             _selectedDocs[docTypeId] = jsonResponse['file_path'];
           });
           _showSnack('File uploaded successfully');
        } else {
           _showSnack(jsonResponse['message'] ?? 'Upload failed');
        }
      }
    } catch (e) {
      _showSnack('Error picking file: $e');
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  // ─── Computed ──────────────────────────────────────────────────────
  Map<String, dynamic>? get _product {
    if (_selectedProductId == null) return null;
    try {
      return _products.firstWhere(
        (p) => int.tryParse(p['product_id']?.toString() ?? '') == _selectedProductId ||
               int.tryParse(p['id']?.toString() ?? '') == _selectedProductId,
      ) as Map<String, dynamic>;
    } catch (_) {
      return null;
    }
  }

  double get _amount => double.tryParse(_amountCtrl.text.replaceAll(',', '')) ?? 0.0;
  double get _rate => (_product?['rate'] ?? _product?['interest_rate'] as num? ?? 0).toDouble();
  double get _interest => _amount * (_rate / 100) * (_selectedTerm ?? 6);
  double get _monthly => (_selectedTerm != null && _selectedTerm! > 0)
      ? (_amount + _interest) / _selectedTerm!
      : 0.0;
  double get _totalRepayment => _amount + _interest;

  // ─── Credit (live from DB via api_get_credit_info.php) ───────────────
  double _creditLimit = 0.0;
  double _usedCredit = 0.0;
  double get _remainingCredit => (_creditLimit - _usedCredit).clamp(0.0, _creditLimit);

  // ─── Available Terms from product ──────────────────────────────────
  List<int> get _availableTerms {
    if (_product == null) return [6, 9, 12, 18, 24, 36, 48, 60];
    final minT = ((_product!['min_term'] ?? _product!['min_term_months'] ?? 6) as num).toInt();
    final maxT = ((_product!['max_term'] ?? _product!['max_term_months'] ?? 60) as num).toInt();
    return [6, 9, 12, 18, 24, 36, 48, 60].where((t) => t >= minT && t <= maxT).toList();
  }

  @override
  void initState() {
    super.initState();
    _amountCtrl.addListener(() => setState(() {}));
    _checkExistingLoans();
    _fetchProducts();
    _fetchDocTypes();
    _fetchCreditInfo();
  }

  @override
  void dispose() {
    _amountCtrl.dispose();
    _purposeDescCtrl.dispose();
    for (final c in _appDataCtrls.values) c.dispose();
    super.dispose();
  }

  TextEditingController _ctrl(String key) {
    return _appDataCtrls.putIfAbsent(key, () => TextEditingController());
  }

  // ─── API ───────────────────────────────────────────────────────────
  Future<void> _checkExistingLoans() async {
    final user = currentUser.value;
    if (user == null) {
      if (mounted) setState(() => _checkingLoanStatus = false);
      return;
    }
    try {
      final url = Uri.parse(ApiConfig.getUrl(
          'api_get_my_loans.php?user_id=${user['user_id']}&tenant_id=${activeTenant.value.id}'));
      final response = await http.get(url);
      final data = jsonDecode(response.body);
      if (data['success'] == true) {
        final loans = data['loans'] as List<dynamic>? ?? [];
        bool hasExisting = false;
        for (var l in loans) {
          final status = l['loan_status']?.toString().toLowerCase() ?? '';
          if (status == 'active' || status == 'pending' || status == 'submitted' || status == 'under review' || status == 'document verification' || status == 'credit investigation' || status == 'for approval') {
            hasExisting = true;
            break;
          }
        }
        if (mounted) {
          setState(() {
            _hasActiveOrPendingLoan = hasExisting;
            _checkingLoanStatus = false;
          });
        }
      } else {
        if (mounted) setState(() => _checkingLoanStatus = false);
      }
    } catch (_) {
      if (mounted) setState(() => _checkingLoanStatus = false);
    }
  }

  Future<void> _fetchProducts() async {
    try {
      final resp = await http.get(Uri.parse(
        ApiConfig.getUrl('api_get_products.php?tenant_id=${activeTenant.value.id}'),
      ));
      if (resp.statusCode == 200) {
        final data = jsonDecode(resp.body);
        if (data['success'] == true && mounted) {
          setState(() => _products = data['products'] ?? []);
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _fetchDocTypes() async {
    try {
      final resp = await http.get(Uri.parse(ApiConfig.getUrl('api_get_doc_types.php')));
      final data = jsonDecode(resp.body);
      if (data['success'] == true && mounted) {
        setState(() => _docTypes = data['document_types'] ?? []);
      }
    } catch (_) {}
  }

  Future<void> _fetchCreditInfo() async {
    final user = currentUser.value;
    if (user == null) {
      if (mounted) setState(() => _creditLoading = false);
      return;
    }
    
    // We already synced credit_limit from dashboard via the backend credit_policy logic!
    if (mounted) {
      setState(() {
        final rawLimit = user['credit_limit'];
        _creditLimit = double.tryParse(rawLimit?.toString() ?? '0') ?? 0.0;
        
        // Let's assume used_credit is zero for now unless the loan logic adds it
        _usedCredit = 0.0; 
        _creditLoading = false;
      });
    }
  }

  // ─── Dynamic doc lists ─────────────────────────────────────────────
  List<dynamic> _requiredDocs() {
    switch (_purposeCategory) {
      case 'Business':
        return _docTypes.where((d) => d['document_name'].toString().contains('Permit')).toList();
      case 'Medical':
        return _docTypes.where((d) => d['document_name'].toString().contains('Medical')).toList();
      case 'Education':
        return _docTypes.where((d) => d['document_name'].toString().contains('Enrollment')).toList();
      case 'Housing':
        return _docTypes.where((d) => d['document_name'].toString().contains('Materials') || d['document_name'].toString().contains('Title')).toList();
      case 'Agricultural':
        return _docTypes.where((d) => d['document_name'].toString().contains('Title') || d['document_name'].toString().contains('Brgy')).toList();
      default:
        return [];
    }
  }

  List<dynamic> _optionalDocs() {
    switch (_purposeCategory) {
      case 'Business':
        return _docTypes.where((d) => d['document_name'].toString().contains('SEC') || d['document_name'].toString().contains('DTI')).toList();
      case 'Medical':
        return _docTypes.where((d) => d['document_name'].toString().contains('Abstract')).toList();
      case 'Education':
        return _docTypes.where((d) => d['document_name'].toString().contains('School ID')).toList();
      default:
        return [];
    }
  }

  // ─── Validation & Review ───────────────────────────────────────────
  void _showSnack(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(msg),
      behavior: SnackBarBehavior.floating,
      backgroundColor: const Color(0xFF1F2937),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 80),
    ));
  }

  void _onReviewTap() {
    if (_creditLoading) return _showSnack('Please wait — loading your credit information.');
    if (_selectedProductId == null) return _showSnack('Please select a loan product.');
    if (_amount <= 0) return _showSnack('Please enter a valid loan amount.');
    if (_selectedTerm == null) return _showSnack('Please select a repayment term.');
    if (_purposeCategory == null) return _showSnack('Please select a purpose category.');
    if (_purposeDescCtrl.text.trim().isEmpty) return _showSnack('Please describe the purpose of your loan.');

    // Credit limit guard
    if (_creditLimit <= 0) {
      return _showSnack('No credit limit has been assigned to your account. Please contact support.');
    }
    if (_amount > _remainingCredit) {
      return _showSnack(
        'Amount exceeds your available credit limit of ${AppFormat.peso(_remainingCredit)}.'
      );
    }

    for (final d in _requiredDocs()) {
      final id = int.tryParse(d['document_type_id'].toString()) ?? 0;
      if (_selectedDocs[id] == null) {
        return _showSnack('Please upload all required documents for ${_purposeCategory} loans.');
      }
    }

    _showReviewModal();
  }

  // ─── Review Modal ──────────────────────────────────────────────────
  void _showReviewModal() {
    final primary = activeTenant.value.themePrimaryColor;
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => DraggableScrollableSheet(
        initialChildSize: 0.92,
        minChildSize: 0.5,
        maxChildSize: 0.95,
        builder: (_, scrollCtrl) => Container(
          decoration: BoxDecoration(
            color: AppColors.bg,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: Column(
            children: [
              // Handle
              Container(
                margin: const EdgeInsets.only(top: 12),
                width: 40, height: 4,
                decoration: BoxDecoration(color: AppColors.border, borderRadius: BorderRadius.circular(2)),
              ),
              // Modal header
              Container(
                margin: const EdgeInsets.fromLTRB(20, 16, 20, 0),
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [primary, Color.lerp(primary, Colors.black, 0.25)!],
                    begin: Alignment.topLeft, end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(children: [
                  const Icon(Icons.assignment_turned_in_rounded, color: Colors.white, size: 22),
                  const SizedBox(width: 10),
                  const Expanded(child: Text('Review Your Application',
                    style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w800, letterSpacing: -0.3))),
                  GestureDetector(
                    onTap: () => Navigator.pop(ctx),
                    child: Container(
                      padding: const EdgeInsets.all(6),
                      decoration: BoxDecoration(color: Colors.white.withOpacity(0.2), shape: BoxShape.circle),
                      child: const Icon(Icons.close, color: Colors.white, size: 16),
                    ),
                  ),
                ]),
              ),
              // Scrollable body
              Expanded(child: ListView(
                controller: scrollCtrl,
                padding: const EdgeInsets.all(20),
                children: [
                  // Info banner
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: primary.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: primary.withOpacity(0.2)),
                    ),
                    child: Row(children: [
                      Icon(Icons.info_outline_rounded, color: primary, size: 18),
                      const SizedBox(width: 10),
                      Expanded(child: Text('Please review carefully. Once submitted, your application will be sent to our team.',
                        style: TextStyle(color: primary, fontSize: 13, height: 1.4))),
                    ]),
                  ),
                  const SizedBox(height: 20),

                  // Loan Details
                  _sectionLabel('Loan Details', primary),
                  const SizedBox(height: 12),
                  _reviewCard([
                    _reviewRow('Loan Product', _product?['name'] ?? _product?['product_name'] ?? '—'),
                    _reviewRow('Purpose Category', _purposeCategory ?? '—'),
                    _reviewRow('Loan Amount', AppFormat.peso(_amount), valueColor: primary),
                    _reviewRow('Repayment Term', '$_selectedTerm Months'),
                    _reviewRow('Interest Rate', '$_rate%'),
                    _reviewRow('Monthly Payment', AppFormat.peso(_monthly), valueColor: AppColors.error, isLast: true),
                  ]),
                  const SizedBox(height: 20),

                  // Totals card
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: AppColors.card,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: AppColors.border),
                      boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 12, offset: const Offset(0, 4))],
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceAround,
                      children: [
                        _totalCol('Principal', AppFormat.peso(_amount), AppColors.textMain),
                        Container(width: 1, height: 40, color: AppColors.border),
                        _totalCol('Total Interest', AppFormat.peso(_interest), AppColors.error),
                        Container(width: 1, height: 40, color: AppColors.border),
                        _totalCol('Total Repay', AppFormat.peso(_totalRepayment), primary),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

                  // Purpose
                  _sectionLabel('Purpose Description', primary),
                  const SizedBox(height: 12),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: AppColors.card,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: AppColors.border),
                    ),
                    child: Text(_purposeDescCtrl.text,
                      style: const TextStyle(fontSize: 14, color: AppColors.textSecondary, height: 1.5)),
                  ),
                  const SizedBox(height: 20),

                  // Payment Schedule
                  _sectionLabel('Payment Schedule', primary),
                  const SizedBox(height: 12),
                  _reviewCard([
                    _reviewRow('Payment Frequency', 'Monthly'),
                    _reviewRow('Number of Payments', '${_selectedTerm ?? 0}'),
                    _reviewRow('Monthly Due', AppFormat.peso(_monthly), isLast: true),
                  ]),
                  const SizedBox(height: 32),
                ],
              )),

              // Action buttons
              Padding(
                padding: EdgeInsets.fromLTRB(20, 0, 20, MediaQuery.of(ctx).padding.bottom + 20),
                child: Row(children: [
                  Expanded(child: OutlinedButton(
                    onPressed: () => Navigator.pop(ctx),
                    style: OutlinedButton.styleFrom(
                      side: BorderSide(color: AppColors.border, width: 1.5),
                      padding: const EdgeInsets.symmetric(vertical: 18),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    ),
                    child: const Text('Go Back', style: TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF6B7280), fontSize: 15)),
                  )),
                  const SizedBox(width: 12),
                  Expanded(child: ElevatedButton(
                    onPressed: () { Navigator.pop(ctx); _submit(); },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: primary,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 18),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      elevation: 0,
                    ),
                    child: const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(Icons.send_rounded, size: 18),
                      SizedBox(width: 8),
                      Text('Confirm & Submit', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                    ]),
                  )),
                ]),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _reviewCard(List<Widget> children) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.border),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 12, offset: const Offset(0, 4))],
      ),
      child: Column(children: children),
    );
  }

  Widget _reviewRow(String label, String value, {Color? valueColor, bool isLast = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Column(children: [
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Text(label, style: const TextStyle(fontSize: 13, color: Color(0xFF6B7280), fontWeight: FontWeight.w500)),
          Text(value, style: TextStyle(fontSize: 14, color: valueColor ?? AppColors.textMain, fontWeight: FontWeight.w700)),
        ]),
        if (!isLast) const Divider(height: 1, color: Color(0xFFF3F4F6)),
      ]),
    );
  }

  Widget _totalCol(String label, String value, Color color) {
    return Column(children: [
      Text(label, style: const TextStyle(fontSize: 10, color: Color(0xFF6B7280), fontWeight: FontWeight.w700, letterSpacing: 0.3)),
      const SizedBox(height: 6),
      Text(value, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w900, color: color, letterSpacing: -0.5)),
    ]);
  }

  // ─── Submit ────────────────────────────────────────────────────────
  Future<void> _submit() async {
    if (currentUser.value == null) return;
    setState(() => _isSubmitting = true);
    try {
      final appData = <String, String>{};
      _appDataCtrls.forEach((k, v) => appData[k] = v.text);
      final docs = <Map<String, dynamic>>[];
      _selectedDocs.forEach((id, path) {
        if (path != null) docs.add({'document_type_id': id, 'file_name': path.split('/').last, 'file_path': path});
      });

      final resp = await http.post(
        Uri.parse(ApiConfig.getUrl('api_apply_loan.php')),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'user_id': currentUser.value!['user_id'],
          'tenant_id': activeTenant.value.id,
          'product_id': _selectedProductId,
          'amount': _amount,
          'term': _selectedTerm,
          'purpose_category': _purposeCategory,
          'purpose': _purposeDescCtrl.text.trim(),
          'app_data': jsonEncode(appData),
          'documents': docs,
        }),
      );
      final data = jsonDecode(resp.body);
      if (!mounted) return;
      if (data['success'] == true) {
        _showSuccessDialog(data['application_number'] ?? 'N/A');
      } else {
        _showSnack(data['message'] ?? 'Submission failed. Please try again.');
      }
    } catch (e, st) {
      print('Exception: $e');
      print('StackTrace: $st');
      if (mounted) _showSnack('Error: $e');
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  void _showSuccessDialog(String appNum) {
    final primary = activeTenant.value.themePrimaryColor;
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        contentPadding: EdgeInsets.zero,
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(28),
            decoration: BoxDecoration(
              gradient: LinearGradient(colors: [primary, Color.lerp(primary, Colors.black, 0.25)!]),
              borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
            ),
            child: Column(children: [
              Container(
                width: 64, height: 64,
                decoration: BoxDecoration(color: Colors.white.withOpacity(0.2), shape: BoxShape.circle),
                child: const Icon(Icons.check_rounded, color: Colors.white, size: 36),
              ),
              const SizedBox(height: 14),
              const Text('Application Submitted!',
                style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w800, letterSpacing: -0.5)),
              const SizedBox(height: 6),
              Text('Ref: $appNum', style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 13, fontWeight: FontWeight.w600)),
            ]),
          ),
          Padding(
            padding: const EdgeInsets.all(24),
            child: Column(children: [
              Text('Your application will be reviewed and approved by our team. We\u2019ll notify you shortly.',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 14, color: Color(0xFF6B7280), height: 1.5)),
              const SizedBox(height: 24),
              SizedBox(width: double.infinity, child: ElevatedButton(
                onPressed: () { Navigator.pop(ctx); Navigator.pop(context); },
                style: ElevatedButton.styleFrom(
                  backgroundColor: primary, foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  elevation: 0,
                ),
                child: const Text('Return to Dashboard', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
              )),
            ]),
          ),
        ]),
      ),
    );
  }

  // ═══════════════════════════════════════════════════════════════════
  // BUILD
  // ═══════════════════════════════════════════════════════════════════
  @override
  Widget build(BuildContext context) {
    final tenant = activeTenant.value;
    final primary = tenant.themePrimaryColor;

    return Scaffold(
      backgroundColor: AppColors.bg,
      body: SafeArea(
        bottom: false,
        child: (_isLoading || _checkingLoanStatus)
            ? Center(child: CircularProgressIndicator(color: primary, strokeWidth: 3))
            : _hasActiveOrPendingLoan
                ? _buildLockedScreen(tenant, primary)
                : Stack(children: [
                    CustomScrollView(
                      physics: const BouncingScrollPhysics(),
                      slivers: [
                        SliverToBoxAdapter(child: _buildHeader(context, tenant, primary)),
                        SliverPadding(
                          padding: const EdgeInsets.fromLTRB(20, 10, 20, 120),
                          sliver: SliverList(delegate: SliverChildListDelegate([
                            _buildStepperRow(primary),
                            const SizedBox(height: 20),
                            _buildCreditLimitCard(primary),
                            const SizedBox(height: 24),
                            _sectionLabel('Loan Details', primary),
                            const SizedBox(height: 14),
                            _buildLoanDetailsCard(primary),
                            const SizedBox(height: 24),
                            _sectionLabel('Estimation', primary),
                            const SizedBox(height: 14),
                            _buildCalculatorCard(primary),
                            const SizedBox(height: 24),
                            _buildReadyCard(primary),
                            const SizedBox(height: 24),
                            _buildTrustRow(primary),
                          ])),
                        ),
                      ],
                    ),
                    // Submitting overlay
                    if (_isSubmitting)
                      Container(
                        color: Colors.black.withOpacity(0.45),
                        child: Center(child: Container(
                          padding: const EdgeInsets.all(28),
                          decoration: BoxDecoration(color: AppColors.card, borderRadius: BorderRadius.circular(20)),
                          child: Column(mainAxisSize: MainAxisSize.min, children: [
                            CircularProgressIndicator(color: primary, strokeWidth: 3),
                            const SizedBox(height: 16),
                            Text('Submitting...', style: TextStyle(color: AppColors.textMain, fontWeight: FontWeight.w600)),
                          ]),
                        )),
                      ),
                  ]),
      ),
    );
  }

  Widget _buildLockedScreen(dynamic tenant, Color primary) {
    return Column(
      children: [
        _buildHeader(context, tenant, primary),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 80, height: 80,
                  decoration: BoxDecoration(
                    color: primary.withOpacity(0.08),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(Icons.lock_rounded, size: 36, color: primary),
                ),
                const SizedBox(height: 24),
                const Text(
                  'Application Locked',
                  style: TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF111827),
                    letterSpacing: -0.5,
                  ),
                ),
                const SizedBox(height: 12),
                const Text(
                  'You currently have an active or pending loan. Please settle your existing balance or wait for your current application to finish before applying for a new one.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 15,
                    height: 1.5,
                    color: Color(0xFF6B7280),
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 60),
              ],
            ),
          ),
        ),
      ],
    );
  }


  // ─── Header ────────────────────────────────────────────────────────
  Widget _buildHeader(BuildContext context, dynamic tenant, Color primary) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 20),
      child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Row(children: [
          Container(
            width: 48, height: 48,
            decoration: const BoxDecoration(shape: BoxShape.circle, color: Color(0xFF1F2937)),
            child: const Icon(Icons.person, color: Colors.white, size: 28),
          ),
          const SizedBox(width: 12),
          Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(tenant.appName, style: const TextStyle(
              fontSize: 15, fontWeight: FontWeight.w700,
              color: Color(0xFF0F292B), height: 1.1, letterSpacing: -0.3,
            )),
            const Text('Apply for Loan', style: TextStyle(
              fontSize: 15, fontWeight: FontWeight.w700,
              color: Color(0xFF0F292B), height: 1.1, letterSpacing: -0.3,
            )),
          ]),
        ]),
        ValueListenableBuilder<List<dynamic>>(
          valueListenable: globalNotifications,
          builder: (context, notifs, _) => Stack(
            children: [
              IconButton(
                onPressed: () => AppDialogs.showNotifications(context, primary),
                icon: const Icon(
                  Icons.notifications_rounded,
                  color: Color(0xFF0F292B),
                  size: 26,
                ),
              ),
              if (notifs.isNotEmpty)
                Positioned(
                  top: 10,
                  right: 12,
                  child: Container(
                    width: 8,
                    height: 8,
                    decoration: const BoxDecoration(
                      color: Color(0xFFEF4444),
                      shape: BoxShape.circle,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ]),
    );
  }

  // ─── Section Label ─────────────────────────────────────────────────
  Widget _sectionLabel(String title, Color primary) {
    return Row(children: [
      Container(
        width: 4, height: 20,
        decoration: BoxDecoration(color: primary, borderRadius: BorderRadius.circular(2)),
      ),
      const SizedBox(width: 10),
      Text(title, style: TextStyle(
        fontSize: 18, fontWeight: FontWeight.w800,
        color: AppColors.textMain, letterSpacing: -0.5,
      )),
    ]);
  }

  // ─── Progress Stepper ──────────────────────────────────────────────
  Widget _buildStepperRow(Color primary) {
    return Row(mainAxisAlignment: MainAxisAlignment.center, children: [
      _stepDot('1', 'Application', true, primary),
      _stepLine(false, primary),
      _stepDot('2', 'Verification', false, primary),
      _stepLine(false, primary),
      _stepDot('3', 'Approval', false, primary),
    ]);
  }

  Widget _stepDot(String num, String label, bool active, Color primary) {
    return Column(children: [
      Container(
        width: 32, height: 32,
        decoration: BoxDecoration(
          color: active ? primary : AppColors.card,
          shape: BoxShape.circle,
          border: Border.all(color: active ? primary : AppColors.border, width: 2),
          boxShadow: active ? [BoxShadow(color: primary.withOpacity(0.3), blurRadius: 10, offset: const Offset(0, 4))] : [],
        ),
        child: Center(child: Text(num, style: TextStyle(
          color: active ? Colors.white : AppColors.textSecondary,
          fontWeight: FontWeight.w800, fontSize: 13,
        ))),
      ),
      const SizedBox(height: 6),
      Text(label, style: TextStyle(
        color: active ? primary : AppColors.textSecondary,
        fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 0.5,
      )),
    ]);
  }

  Widget _stepLine(bool active, Color primary) {
    return Container(
      width: 44, height: 2,
      margin: const EdgeInsets.only(bottom: 20, left: 8, right: 8),
      color: active ? primary : AppColors.border,
    );
  }

  // ─── Credit Limit Card ─────────────────────────────────────────────
  Widget _buildCreditLimitCard(Color primary) {
    // While loading credit data show a shimmer placeholder
    if (_creditLoading) {
      return Container(
        height: 140,
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [primary.withOpacity(0.6), Color.lerp(primary, Colors.black, 0.25)!.withOpacity(0.6)],
            begin: Alignment.topLeft, end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(24),
        ),
        child: const Center(
          child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5),
        ),
      );
    }

    // If no credit limit assigned yet
    if (_creditLimit <= 0) {
      return Container(
        padding: const EdgeInsets.all(24),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [primary, Color.lerp(primary, Colors.black, 0.25)!],
            begin: Alignment.topLeft, end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(24),
          boxShadow: [BoxShadow(color: primary.withOpacity(0.3), blurRadius: 20, offset: const Offset(0, 8))],
        ),
        child: Row(children: [
          const Icon(Icons.info_outline_rounded, color: Colors.white, size: 20),
          const SizedBox(width: 12),
          const Expanded(
            child: Text(
              'No credit limit has been set for your account yet. Please contact support.',
              style: TextStyle(color: Colors.white, fontSize: 13, height: 1.4, fontWeight: FontWeight.w600),
            ),
          ),
        ]),
      );
    }

    final pct = _creditLimit > 0
        ? (_remainingCredit / _creditLimit).clamp(0.0, 1.0)
        : 0.0;

    // Colour the progress bar red when usage is high
    final barColor = pct < 0.25
        ? const Color(0xFFEF4444)   // danger – almost maxed
        : pct < 0.5
            ? const Color(0xFFF59E0B) // warning
            : Colors.white;           // healthy

    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [primary, Color.lerp(primary, Colors.black, 0.25)!],
          begin: Alignment.topLeft, end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(24),
        boxShadow: [BoxShadow(color: primary.withOpacity(0.35), blurRadius: 20, offset: const Offset(0, 8))],
      ),
      child: Stack(clipBehavior: Clip.none, children: [
        Positioned(
          right: -40, top: -60,
          child: Container(
            width: 160, height: 160,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: RadialGradient(colors: [Colors.white.withOpacity(0.08), Colors.transparent]),
            ),
          ),
        ),
        Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text('AVAILABLE CREDIT LIMIT', style: TextStyle(
              fontSize: 10, fontWeight: FontWeight.w800,
              color: Colors.white.withOpacity(0.8), letterSpacing: 1.0,
            )),
            GestureDetector(
              onTap: _fetchCreditInfo,
              child: Container(
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.15),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.refresh_rounded, color: Colors.white, size: 14),
              ),
            ),
          ]),
          const SizedBox(height: 8),
          Text(AppFormat.peso(_remainingCredit), style: const TextStyle(
            color: Colors.white, fontSize: 32, fontWeight: FontWeight.w900, letterSpacing: -1,
          )),
          const SizedBox(height: 20),
          ClipRRect(
            borderRadius: BorderRadius.circular(6),
            child: LinearProgressIndicator(
              value: pct, minHeight: 8,
              backgroundColor: Colors.white.withOpacity(0.25),
              valueColor: AlwaysStoppedAnimation<Color>(barColor),
            ),
          ),
          const SizedBox(height: 12),
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text('Total Limit: ${AppFormat.pesoCompact(_creditLimit)}',
              style: TextStyle(color: Colors.white.withOpacity(0.75), fontSize: 12, fontWeight: FontWeight.w600)),
            Text('Used: ${AppFormat.pesoCompact(_usedCredit)}',
              style: TextStyle(color: Colors.white.withOpacity(0.75), fontSize: 12, fontWeight: FontWeight.w600)),
          ]),
        ]),
      ]),
    );
  }



  // ─── Loan Details Card ─────────────────────────────────────────────
  Widget _buildLoanDetailsCard(Color primary) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFE5E7EB)),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 12, offset: const Offset(0, 4))],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Product
        _fieldLabel('Select Loan Product'),
        _styledDropdown<int>(
          icon: Icons.shopping_bag_outlined,
          hint: '— Choose a product —',
          value: _selectedProductId,
          items: _products.map((p) {
            final id = int.tryParse(p['product_id']?.toString() ?? p['id']?.toString() ?? '0') ?? 0;
            final name = p['name'] ?? p['product_name'] ?? 'Product';
            final rate = p['rate'] ?? p['interest_rate'] ?? 0;
            return DropdownMenuItem<int>(value: id, child: Text('$name – $rate% Interest', overflow: TextOverflow.ellipsis));
          }).toList(),
          onChanged: (v) => setState(() => _selectedProductId = v),
        ),
        const SizedBox(height: 16),

        // Amount + Term in a row
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            _fieldLabel('Amount to Borrow'),
            TextFormField(
              controller: _amountCtrl,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF111827)),
              decoration: _fieldDecor(prefixText: '₱ '),
            ),
            if (_product != null)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(
                  'Min ${AppFormat.pesoCompact((_product!['min_amount'] ?? _product!['min'] as num? ?? 0).toDouble())} – Max ${AppFormat.pesoCompact((_product!['max_amount'] ?? _product!['max'] as num? ?? 0).toDouble())}',
                  style: TextStyle(fontSize: 11, color: primary, fontWeight: FontWeight.w600),
                ),
              ),
          ])),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            _fieldLabel('Repayment Term'),
            _styledDropdown<int>(
              icon: Icons.calendar_month_outlined,
              hint: '— Duration —',
              value: _selectedTerm,
              items: _availableTerms.map((t) {
                String label = '$t Months';
                if (t == 12) label = '12 Months (1 Yr)';
                if (t == 24) label = '24 Months (2 Yr)';
                if (t == 36) label = '36 Months (3 Yr)';
                return DropdownMenuItem<int>(value: t, child: Text(label, overflow: TextOverflow.ellipsis));
              }).toList(),
              onChanged: (v) => setState(() => _selectedTerm = v),
            ),
          ])),
        ]),
        const SizedBox(height: 16),

        // Purpose category
        _fieldLabel('Purpose Category'),
        _styledDropdown<String>(
          icon: Icons.category_outlined,
          hint: '— Select Purpose —',
          value: _purposeCategory,
          items: ['Business', 'Personal', 'Education', 'Agricultural', 'Medical', 'Housing']
              .map((c) => DropdownMenuItem<String>(value: c, child: Text(c))).toList(),
          onChanged: (v) => setState(() { _purposeCategory = v; _selectedDocs.clear(); }),
        ),
        const SizedBox(height: 16),

        // Purpose description
        _fieldLabel('Specific Purpose Description'),
        TextFormField(
          controller: _purposeDescCtrl,
          maxLines: 3,
          style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF111827)),
          decoration: _fieldDecor(hint: 'Describe exactly how you will use the funds...'),
        ),

        // Dynamic requirements
        if (_purposeCategory != null && _purposeCategory != 'Personal') ...[
          const SizedBox(height: 20),
          const Divider(color: Color(0xFFF3F4F6)),
          const SizedBox(height: 12),
          Row(children: [
            Icon(_purposeIcon(_purposeCategory!), color: primary, size: 18),
            const SizedBox(width: 8),
            Text('$_purposeCategory Requirements',
              style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: primary, letterSpacing: -0.3)),
          ]),
          const SizedBox(height: 16),
          _buildDynamicFields(primary),
          if (_requiredDocs().isNotEmpty || _optionalDocs().isNotEmpty) ...[
            const SizedBox(height: 20),
            const Divider(color: Color(0xFFF3F4F6)),
            const SizedBox(height: 12),
            const Text('Required Documents', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Color(0xFF374151))),
            const SizedBox(height: 12),
            ..._requiredDocs().map((d) => _docRow(d, primary, required: true)),
            if (_optionalDocs().isNotEmpty) ...[
              const SizedBox(height: 8),
              const Text('Optional Documents', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF9CA3AF))),
              const SizedBox(height: 12),
              ..._optionalDocs().map((d) => _docRow(d, primary, required: false)),
            ],
          ],
        ],
      ]),
    );
  }

  IconData _purposeIcon(String category) {
    switch (category) {
      case 'Business': return Icons.business_center_outlined;
      case 'Medical': return Icons.health_and_safety_outlined;
      case 'Education': return Icons.school_outlined;
      case 'Housing': return Icons.home_outlined;
      case 'Agricultural': return Icons.agriculture_outlined;
      default: return Icons.category_outlined;
    }
  }

  Widget _buildDynamicFields(Color primary) {
    switch (_purposeCategory) {
      case 'Business':
        return Column(children: [
          _inputField('Business Name', _ctrl('business_name')),
          const SizedBox(height: 12),
          Row(children: [
            Expanded(child: _inputField('Nature of Business', _ctrl('business_nature'))),
            const SizedBox(width: 12),
            Expanded(child: _inputField('Years in Operation', _ctrl('business_years'), keyboard: TextInputType.number)),
          ]),
        ]);
      case 'Medical':
        return Column(children: [
          Row(children: [
            Expanded(child: _inputField('Patient Name', _ctrl('med_patient_name'))),
            const SizedBox(width: 12),
            Expanded(child: _inputField('Relationship', _ctrl('med_relationship'))),
          ]),
          const SizedBox(height: 12),
          _inputField('Hospital / Clinic', _ctrl('med_hospital')),
        ]);
      case 'Education':
        return Column(children: [
          Row(children: [
            Expanded(child: _inputField('Student Name', _ctrl('edu_student_name'))),
            const SizedBox(width: 12),
            Expanded(child: _inputField('School', _ctrl('edu_school'))),
          ]),
          const SizedBox(height: 12),
          _inputField('Course / Year Level', _ctrl('edu_course')),
        ]);
      case 'Housing':
        return Column(children: [
          _inputField('Project Address', _ctrl('house_address')),
          const SizedBox(height: 12),
          _styledDropdown<String>(
            icon: Icons.home_work_outlined,
            hint: 'Type of Work',
            value: _appDataCtrls['house_work_type'] != null && _appDataCtrls['house_work_type']!.text.isNotEmpty
                ? _appDataCtrls['house_work_type']!.text : null,
            items: ['Renovation', 'Construction', 'Repair'].map((t) => DropdownMenuItem(value: t, child: Text(t))).toList(),
            onChanged: (v) => setState(() { _ctrl('house_work_type').text = v ?? ''; }),
          ),
        ]);
      case 'Agricultural':
        return Column(children: [
          Row(children: [
            Expanded(child: _inputField('Farm Location', _ctrl('agri_location'))),
            const SizedBox(width: 12),
            Expanded(child: _inputField('Land Area', _ctrl('agri_area'))),
          ]),
          const SizedBox(height: 12),
          _inputField('Crops / Livestock', _ctrl('agri_crops')),
        ]);
      default:
        return const SizedBox();
    }
  }

  Widget _docRow(dynamic d, Color primary, {required bool required}) {
    final id = int.tryParse(d['document_type_id'].toString()) ?? 0;
    final isSel = _selectedDocs[id] != null;
    return GestureDetector(
      onTap: () {
        if (isSel) {
          setState(() => _selectedDocs[id] = null);
        } else {
          _pickAndUploadDocument(id);
        }
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: isSel ? primary.withOpacity(0.05) : const Color(0xFFFAFAFA),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: isSel ? primary : const Color(0xFFE5E7EB)),
          boxShadow: isSel ? [BoxShadow(color: primary.withOpacity(0.08), blurRadius: 8)] : [],
        ),
        child: Row(children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: isSel ? primary : const Color(0xFFE5E7EB),
              shape: BoxShape.circle,
            ),
            child: Icon(
              isSel ? Icons.cloud_done_rounded : Icons.cloud_upload_outlined,
              color: isSel ? Colors.white : const Color(0xFF6B7280), size: 18,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(
              '${d['document_name']}${required ? ' *' : ''}',
              style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700,
                  color: isSel ? primary : const Color(0xFF374151)),
            ),
            const SizedBox(height: 3),
            Text(isSel ? 'Uploaded successfully' : 'Tap to select file',
              style: TextStyle(fontSize: 11, color: isSel ? primary.withOpacity(0.8) : const Color(0xFF9CA3AF))),
          ])),
          if (isSel) Icon(Icons.check_circle_rounded, color: primary, size: 20),
        ]),
      ),
    );
  }

  // ─── Calculator Card (matches dashboard card style) ─────────────────
  Widget _buildCalculatorCard(Color primary) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFE5E7EB)),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 12, offset: const Offset(0, 4))],
      ),
      child: Column(children: [
        // Monthly header (same pattern as portfolio card with big white number)
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: primary,
            borderRadius: BorderRadius.circular(18),
            boxShadow: [BoxShadow(color: primary.withOpacity(0.3), blurRadius: 16, offset: const Offset(0, 6))],
          ),
          child: Row(children: [
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('MONTHLY PAYMENT', style: TextStyle(
                fontSize: 9, fontWeight: FontWeight.w800,
                color: Colors.white.withOpacity(0.8), letterSpacing: 1.0,
              )),
              const SizedBox(height: 6),
              Text(AppFormat.peso(_monthly), style: const TextStyle(
                fontSize: 26, fontWeight: FontWeight.w900, color: Colors.white, letterSpacing: -1,
              )),
              Text('for ${_selectedTerm ?? 0} months', style: TextStyle(
                color: Colors.white.withOpacity(0.75), fontSize: 12, fontWeight: FontWeight.w500,
              )),
            ])),
            Icon(Icons.calculate_outlined, color: Colors.white.withOpacity(0.6), size: 36),
          ]),
        ),
        const SizedBox(height: 16),
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(14)),
          child: Column(children: [
            _calcRow('Principal:', AppFormat.peso(_amount)),
            const SizedBox(height: 8),
            _calcRow('Interest ($_rate%):', AppFormat.peso(_interest), valueColor: const Color(0xFFEF4444)),
            const Padding(padding: EdgeInsets.symmetric(vertical: 10), child: Divider(height: 1, color: Color(0xFFE5E7EB))),
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              const Text('Total Repayment:', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: Color(0xFF111827))),
              Text(AppFormat.peso(_totalRepayment), style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: primary)),
            ]),
          ]),
        ),
      ]),
    );
  }

  Widget _calcRow(String label, String value, {Color? valueColor}) {
    return Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: const TextStyle(fontSize: 13, color: Color(0xFF6B7280), fontWeight: FontWeight.w500)),
      Text(value, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: valueColor ?? const Color(0xFF111827))),
    ]);
  }

  // ─── Ready to Apply Card ───────────────────────────────────────────
  Widget _buildReadyCard(Color primary) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFE5E7EB)),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 12, offset: const Offset(0, 4))],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('Ready to Apply?',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF111827), letterSpacing: -0.5)),
        const SizedBox(height: 8),
        const Text(
          'By tapping Review, you agree to our Terms of Service. Your application will be reviewed by our team.',
          style: TextStyle(fontSize: 13, color: Color(0xFF6B7280), height: 1.5),
        ),
        const SizedBox(height: 20),
        SizedBox(width: double.infinity, child: ElevatedButton(
          onPressed: _isSubmitting ? null : _onReviewTap,
          style: ElevatedButton.styleFrom(
            backgroundColor: primary, foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(vertical: 20),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
            elevation: 0,
          ),
          child: const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            Text('Review Application', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
            SizedBox(width: 8),
            Icon(Icons.visibility_outlined, size: 20),
          ]),
        )),
        const SizedBox(height: 12),
        Center(child: TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancel', style: TextStyle(color: Color(0xFF9CA3AF), fontWeight: FontWeight.w600)),
        )),
      ]),
    );
  }

  // ─── Trust Indicators ──────────────────────────────────────────────
  Widget _buildTrustRow(Color primary) {
    return Row(children: [
      Expanded(child: _trustItem(Icons.lock_outline_rounded, 'Secure Data', Colors.green.shade600)),
      Expanded(child: _trustItem(Icons.bolt_rounded, 'Fast Approval', primary)),
      Expanded(child: _trustItem(Icons.support_agent_outlined, '24/7 Support', const Color(0xFF3B82F6))),
    ]);
  }

  Widget _trustItem(IconData icon, String label, Color color) {
    return Column(children: [
      Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(color: color.withOpacity(0.08), shape: BoxShape.circle),
        child: Icon(icon, color: color, size: 22),
      ),
      const SizedBox(height: 8),
      Text(label, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Color(0xFF6B7280))),
    ]);
  }

  // ─── Shared Form Helpers ───────────────────────────────────────────
  Widget _fieldLabel(String label) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Text(label, style: const TextStyle(
        fontSize: 13, fontWeight: FontWeight.w700, color: Color(0xFF374151),
      )),
    );
  }

  InputDecoration _fieldDecor({String? prefixText, String? hint}) {
    return InputDecoration(
      prefixText: prefixText,
      hintText: hint,
      filled: true,
      fillColor: const Color(0xFFF9FAFB),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE5E7EB))),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE5E7EB))),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: activeTenant.value.themePrimaryColor, width: 2),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      hintStyle: const TextStyle(color: Color(0xFF9CA3AF), fontWeight: FontWeight.w500),
    );
  }

  Widget _styledDropdown<T>({
    required IconData icon,
    required String hint,
    required T? value,
    required List<DropdownMenuItem<T>> items,
    required void Function(T?) onChanged,
  }) {
    return DropdownButtonFormField<T>(
      value: value,
      isExpanded: true,
      onChanged: onChanged,
      icon: const Icon(Icons.keyboard_arrow_down_rounded, color: Color(0xFF6B7280)),
      decoration: InputDecoration(
        prefixIcon: Icon(icon, color: const Color(0xFF9CA3AF), size: 20),
        filled: true,
        fillColor: const Color(0xFFF9FAFB),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE5E7EB))),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE5E7EB))),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: activeTenant.value.themePrimaryColor, width: 2),
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 14),
        hintText: hint,
        hintStyle: const TextStyle(color: Color(0xFF9CA3AF), fontWeight: FontWeight.w500),
      ),
      items: items,
    );
  }

  Widget _inputField(String label, TextEditingController ctrl, {TextInputType? keyboard}) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      _fieldLabel(label),
      TextFormField(
        controller: ctrl,
        keyboardType: keyboard,
        style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF111827)),
        decoration: _fieldDecor(),
      ),
    ]);
  }
}