<?php
$path = 'microfin_mobile/lib/screens/client_verification_screen.dart';
$content = file_get_contents($path);

$search1 = "  // ── Lifecycle ──────────────────────────────────────────────────────────\n  @override\n  void initState() {\n    super.initState();\n    _fetchDocTypes();\n    _prefillFromUser();\n  }";

$replace1 = "  // ── Lifecycle ──────────────────────────────────────────────────────────\n  @override\n  void initState() {\n    super.initState();\n    _fetchTenantConfig();\n    _fetchDocTypes();\n    _prefillFromUser();\n  }\n\n  Future<void> _fetchTenantConfig() async {\n    try {\n      final resp = await http.get(Uri.parse(\n        ApiConfig.getUrl('api_get_tenant_config.php?tenant_id=\${activeTenant.value.id}'),\n      ));\n      if (resp.statusCode == 200) {\n        final data = jsonDecode(resp.body);\n        if (data['success'] == true && data['allowed_employment_statuses'] != null) {\n          final List<dynamic> list = data['allowed_employment_statuses'];\n          if (mounted) {\n            setState(() {\n              _allowedEmploymentStatuses = list.map((e) => e.toString()).toList();\n              if (_allowedEmploymentStatuses.isNotEmpty && !_allowedEmploymentStatuses.contains(_employmentStatus)) {\n                _employmentStatus = _allowedEmploymentStatuses.first;\n              }\n            });\n          }\n        }\n      }\n    } catch (_) {}\n  }";

$content = str_replace(str_replace("\n", "\r\n", $search1), str_replace("\n", "\r\n", $replace1), $content);
$content = str_replace($search1, $replace1, $content);

$search2 = "          _dropdownField('Employment Status', _employmentStatus,\n              ['Employed', 'Self-Employed', 'Unemployed', 'Retired'],\n              (v) => setState(() => _employmentStatus = v!), icon: Icons.work_outline_rounded),";

$replace2 = "          _dropdownField('Employment Status', _employmentStatus,\n              _allowedEmploymentStatuses.isNotEmpty ? _allowedEmploymentStatuses : ['Employed'],\n              (v) => setState(() => _employmentStatus = v!), icon: Icons.work_outline_rounded),";

$content = str_replace(str_replace("\n", "\r\n", $search2), str_replace("\n", "\r\n", $replace2), $content);
$content = str_replace($search2, $replace2, $content);

file_put_contents($path, $content);
echo "DONE";
?>
