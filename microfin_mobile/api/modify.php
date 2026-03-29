<?php
$file = 'c:/xampp/htdocs/Integ/config/Model/Activity3_5PageUp/microfin_mobile/microfin_mobile/lib/screens/client_verification_screen.dart';
$text = file_get_contents($file);

// 1. Update _totalSteps and _stepLabels
$text = str_replace("final int _totalSteps = 6;", "final int _totalSteps = 4;", $text);
$text = str_replace(
    "final List<String> _stepLabels = ['Personal', 'Address', 'Co-maker', 'ID Verification', 'Documents', 'Review'];",
    "final List<String> _stepLabels = ['Profile & ID', 'Co-maker', 'Documents', 'Review'];",
    $text
);

// 2. Update PageView
$pageViewOld = "children: [
                _buildStep0(primary),
                _buildStep1(primary),
                _buildStep2(primary),
                _buildStepID(primary),
                _buildStepDocs(primary),
                _buildStep4(primary),
              ],";
$pageViewNew = "children: [
                _buildCombinedStep0(primary),
                _buildStep2(primary),
                _buildStepDocs(primary),
                _buildStep4(primary),
              ],";
$text = str_replace($pageViewOld, $pageViewNew, $text);

// 3. Rename Step functions and remove their SingleScrollViews
$text = preg_replace('/Widget _buildStep0\(Color primary\) \{(.*?return )SingleChildScrollView\(\s*padding:.*?child: Column\(crossAxisAlignment: CrossAxisAlignment\.start, children: \[(.*?)\]\),\s*\);/s', 
              "Widget _buildPersonalSection(Color primary) {\n$1Column(crossAxisAlignment: CrossAxisAlignment.start, children: [$2]);", 
              $text, 1);

$text = preg_replace('/Widget _buildStep1\(Color primary\) \{(.*?return )SingleChildScrollView\(\s*padding:.*?child: Column\(crossAxisAlignment: CrossAxisAlignment\.start, children: \[(.*?)\]\),\s*\);/s', 
              "Widget _buildAddressSection(Color primary) {\n$1Column(crossAxisAlignment: CrossAxisAlignment.start, children: [$2]);", 
              $text, 1);

$text = preg_replace('/Widget _buildStepID\(Color primary\) \{(.*?return )SingleChildScrollView\(\s*padding:.*?child: Column\(crossAxisAlignment: CrossAxisAlignment\.start, children: \[(.*?)\]\),\s*\);/s', 
              "Widget _buildIdVerificationSection(Color primary) {\n$1Column(crossAxisAlignment: CrossAxisAlignment.start, children: [$2]);", 
              $text, 1);

// Add _buildCombinedStep0
$combined_func = "
  Widget _buildCombinedStep0(Color primary) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _buildIdVerificationSection(primary),
        const SizedBox(height: 24),
        _buildPersonalSection(primary),
        const SizedBox(height: 24),
        _buildAddressSection(primary),
      ]),
    );
  }
";
$text = str_replace("  // ── Steps ──────────────────────────────────────────────────────────────\n", "  // ── Steps ──────────────────────────────────────────────────────────────\n$combined_func", $text);

// 4. _pickUploadAndVerifyId -> Add address mapping
$uploadOld = "          _idNameCtrl.text   = _idExtractedName!;
          _idDobCtrl.text    = _idExtractedDob!;
          _idGenderCtrl.text = verifyJson['gender'] ?? '';
          
          _showScannedFields = true;";
$uploadNew = "          _idNameCtrl.text   = _idExtractedName!;
          _idDobCtrl.text    = _idExtractedDob!;
          _idGenderCtrl.text = verifyJson['gender'] ?? '';
          
          if ((_idExtractedDob ?? '').isNotEmpty) _dobCtrl.text = _idExtractedDob!;
          if ((verifyJson['gender'] ?? '').isNotEmpty) {
               _gender = (verifyJson['gender'] == 'F' || verifyJson['gender'] == 'Female') ? 'Female' : 'Male';
          }
          if ((verifyJson['address_street'] ?? '').isNotEmpty) _streetCtrl.text = verifyJson['address_street'];
          if ((verifyJson['address_barangay'] ?? '').isNotEmpty) _barangayCtrl.text = verifyJson['address_barangay'];
          if ((verifyJson['address_city'] ?? '').isNotEmpty) _cityCtrl.text = verifyJson['address_city'];
          if ((verifyJson['address_province'] ?? '').isNotEmpty) _provinceCtrl.text = verifyJson['address_province'];
          if ((verifyJson['address_postal_code'] ?? '').isNotEmpty) _postalCtrl.text = verifyJson['address_postal_code'];
          
          _showScannedFields = true;";
$text = str_replace($uploadOld, $uploadNew, $text);

// 5. Overhaul _validateStep
$validate_new = "  bool _validateStep() {
    switch (_currentStep) {
      case 0: // Profile & ID
        bool contactValid = _phoneCtrl.text.isNotEmpty && _monthlyIncomeCtrl.text.isNotEmpty;
        bool addrValid = _cityCtrl.text.isNotEmpty && _provinceCtrl.text.isNotEmpty;
        bool idValid = false;
        if (_selectedIdentityType != null && _idPath != null) {
          idValid = _idNumberCtrl.text.trim().isNotEmpty;
           switch (_selectedIdentityType) {
             case 'passport':
               idValid = idValid && _idExpiryCtrl.text.isNotEmpty && _idIssueDateCtrl.text.isNotEmpty; break;
             case 'dl':
               idValid = idValid && _idExpiryCtrl.text.isNotEmpty; break;
             case 'umid':
               idValid = idValid && _idCrnCtrl.text.trim().isNotEmpty; break;
             case 'sss':
               idValid = idValid && _idSssNumberCtrl.text.trim().isNotEmpty; break;
             case 'prc':
               idValid = idValid && _idExpiryCtrl.text.isNotEmpty && _idProfessionCtrl.text.trim().isNotEmpty; break;
             case 'postal':
               idValid = idValid && _idIssueDateCtrl.text.isNotEmpty; break;
             case 'pagibig':
               idValid = idValid && _idMidNumberCtrl.text.trim().isNotEmpty; break;
           }
        }
        return contactValid && addrValid && idValid;
      case 1: // Co-maker
        return !_hasComaker || (_comakerNameCtrl.text.isNotEmpty && _comakerContactCtrl.text.isNotEmpty);
      case 2: // Documents
        final otherDocs = _docTypes.where((d) => !d['document_name'].toString().toLowerCase().contains('id')).toList();
        return otherDocs.every((d) => _selectedDocs[int.tryParse(d['document_type_id'].toString())] != null);
      default: return true;
    }
  }";
$text = preg_replace('/bool _validateStep\(\) \{.*?default: return true;\n    \}\n  \}/s', $validate_new, $text, 1);

file_put_contents($file, $text);
echo "done!\n";
