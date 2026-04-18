<?php

if (!function_exists('policy_console_system_defaults')) {
    function policy_console_system_defaults(): array
    {
        return [
            'credit_limits' => [
                'scoring_setup' => [
                    'core' => [
                        'starting_credit_score' => 320,
                        'repayment_score_bonus' => 10,
                        'late_payment_score_penalty' => 15,
                    ],
                    'detailed_rules' => [
                        'upgrade' => [
                            'successful_repayment_cycles' => [
                                'enabled' => true,
                                'required_cycles' => 3,
                                'score_points' => 5,
                            ],
                            'maximum_late_payments_review' => [
                                'enabled' => true,
                                'maximum_allowed' => 1,
                                'review_period_days' => 90,
                                'score_points' => 5,
                            ],
                            'no_active_overdue' => [
                                'enabled' => true,
                                'score_points' => 5,
                            ],
                        ],
                        'downgrade' => [
                            'late_payments_review' => [
                                'enabled' => true,
                                'trigger_count' => 2,
                                'review_period_days' => 90,
                                'score_points' => 12,
                            ],
                            'overdue_days_threshold' => [
                                'enabled' => true,
                                'days' => 15,
                                'score_points' => 25,
                            ],
                        ],
                    ],
                ],
                'score_bands' => [
                    'rows' => [
                        [
                            'id' => 'band_at_risk',
                            'label' => 'At-Risk',
                            'min_score' => 50,
                            'max_score' => 249,
                            'base_growth_percent' => 1.0,
                            'micro_percent_per_point' => 0.020,
                        ],
                        [
                            'id' => 'band_entry',
                            'label' => 'Entry',
                            'min_score' => 250,
                            'max_score' => 449,
                            'base_growth_percent' => 5.0,
                            'micro_percent_per_point' => 0.034,
                        ],
                        [
                            'id' => 'band_standard',
                            'label' => 'Standard',
                            'min_score' => 450,
                            'max_score' => 649,
                            'base_growth_percent' => 10.0,
                            'micro_percent_per_point' => 0.025,
                        ],
                        [
                            'id' => 'band_plus',
                            'label' => 'Plus',
                            'min_score' => 650,
                            'max_score' => 849,
                            'base_growth_percent' => 15.0,
                            'micro_percent_per_point' => 0.020,
                        ],
                        [
                            'id' => 'band_premium',
                            'label' => 'Premium',
                            'min_score' => 850,
                            'max_score' => 1000,
                            'base_growth_percent' => 18.0,
                            'micro_percent_per_point' => 0.010,
                        ],
                    ],
                ],
                'limit_assignment' => [
                    'initial_limit_percent_of_income' => 45,
                    'use_default_lending_cap' => false,
                    'default_lending_cap_amount' => 0,
                    'score_change_steps' => [5, 10, 15, 20],
                    'apply_score_changes_immediately' => true,
                ],
            ],
            'decision_rules' => [
                'workflow' => [
                    'approval_mode' => 'semi_automatic',
                ],
                'decision_rules' => [
                    'score_thresholds' => [
                        'enabled' => true,
                        'auto_reject_floor' => 250,
                        'hard_approval_threshold' => 650,
                    ],
                    'ci' => [
                        'enabled' => true,
                        'mandatory_ci_above_amount' => 0,
                        'auto_approve_ci_values' => [],
                        'review_ci_values' => [],
                    ],
                    'borrowing_access_rules' => [
                        'enabled' => true,
                        'allow_multiple_active_loans_within_remaining_limit' => false,
                        'stop_application_if_requested_amount_exceeds_remaining_limit' => true,
                    ],
                    'manual_review_overrides' => [
                        'enabled' => true,
                        'review_if_score_within_points_of_approval_threshold' => true,
                        'points_window' => 25,
                    ],
                    'borrower_safeguards' => [
                        'enabled' => true,
                        'guarantor_required_above_amount' => 50000,
                        'collateral_enabled' => true,
                        'risk_based_security_requirements' => '',
                    ],
                ],
            ],
            'compliance_documents' => [
                'validity_rules' => [
                    'default_validity_days' => 365,
                    'renewal_reminder_days' => 30,
                    'verification_owner' => 'compliance_team',
                ],
                'document_requirements' => array_map(
                    static function (array $category): array {
                        return [
                            'category_key' => $category['category_key'],
                            'label' => $category['label'],
                            'requirement' => $category['default_requirement'],
                            'document_options' => [],
                        ];
                    },
                    policy_console_compliance_document_categories()
                ),
            ],
        ];
    }
}

if (!function_exists('policy_console_credit_limits_system_defaults')) {
    function policy_console_credit_limits_system_defaults(): array
    {
        $defaults = policy_console_system_defaults();
        return isset($defaults['credit_limits']) && is_array($defaults['credit_limits'])
            ? $defaults['credit_limits']
            : [];
    }
}

if (!function_exists('policy_console_decision_rules_system_defaults')) {
    function policy_console_decision_rules_system_defaults(): array
    {
        $defaults = policy_console_system_defaults();
        return isset($defaults['decision_rules']) && is_array($defaults['decision_rules'])
            ? $defaults['decision_rules']
            : [];
    }
}

if (!function_exists('policy_console_compliance_documents_system_defaults')) {
    function policy_console_compliance_documents_system_defaults(): array
    {
        $defaults = policy_console_system_defaults();
        return isset($defaults['compliance_documents']) && is_array($defaults['compliance_documents'])
            ? $defaults['compliance_documents']
            : [];
    }
}

if (!function_exists('policy_console_compliance_document_excluded_names')) {
    function policy_console_compliance_document_excluded_names(): array
    {
        return [];
    }
}

if (!function_exists('policy_console_compliance_document_categories')) {
    function policy_console_compliance_document_categories(): array
    {
        return [
            [
                'category_key' => 'identity_document',
                'label' => 'Identity Document',
                'default_requirement' => 'required',
                'allowed_document_names' => [
                    'Valid ID Front',
                    'Valid ID Back',
                    'National ID (PhilID/ePhilID)',
                    'Passport',
                    'Driver\'s License',
                    'UMID',
                    'SSS ID',
                    'GSIS e-Card',
                    'PRC ID',
                    'Postal ID',
                    'Alien Certificate of Registration (ACR I-Card)',
                    'Seaman\'s Book / SIRB',
                    'Senior Citizen ID',
                    'PWD ID',
                    'Voter\'s ID',
                    'NBI Clearance',
                    'Police Clearance',
                    'TIN ID',
                    'School ID',
                    'Company ID',
                    'Barangay ID',
                    'OFW ID',
                    'OWWA ID',
                    'IBP ID',
                    'MARINA ID',
                    'Government Office / GOCC ID',
                ],
            ],
            [
                'category_key' => 'proof_of_income',
                'label' => 'Proof of Income',
                'default_requirement' => 'required',
                'allowed_document_names' => [
                    'Proof of Income',
                    'Certificate of Employment',
                    'Latest Payslip',
                    'Bank Statement',
                    'Income Tax Return (ITR)',
                    'Remittance Record',
                    'Business Financial Statements',
                    'Sales Records',
                ],
            ],
            [
                'category_key' => 'proof_of_address',
                'label' => 'Proof of Address',
                'default_requirement' => 'required',
                'allowed_document_names' => [
                    'Proof of Billing',
                    'Utility Bill',
                    'Barangay Certificate',
                    'Lease Agreement',
                    'Barangay Clearance',
                ],
            ],
            [
                'category_key' => 'personal_civil_document',
                'label' => 'Personal Civil Document',
                'default_requirement' => 'not_needed',
                'allowed_document_names' => [
                    'Marriage Certificate',
                    'Birth Certificate',
                ],
            ],
            [
                'category_key' => 'business_document',
                'label' => 'Business Document',
                'default_requirement' => 'not_needed',
                'allowed_document_names' => [
                    'Proof of Legitimacy Document',
                    'Business Permit',
                    'DTI Registration',
                    'SEC Registration',
                    'BIR Registration',
                    'Business Plan',
                    'DTI/SEC Registration',
                    'Business Financial Statements',
                ],
            ],
            [
                'category_key' => 'education_document',
                'label' => 'Education Document',
                'default_requirement' => 'not_needed',
                'allowed_document_names' => [
                    'School Enrollment Certificate',
                    'School ID',
                    'Certificate of Enrollment',
                    'Admission Letter',
                    'Tuition Fee Assessment',
                    'Statement of Account',
                ],
            ],
            [
                'category_key' => 'agricultural_document',
                'label' => 'Agricultural Document',
                'default_requirement' => 'not_needed',
                'allowed_document_names' => [
                    'Land Title/Lease Agreement',
                    'Land Title',
                    'Farm Lease Agreement',
                    'Farm Plan',
                    'Farm Certification',
                ],
            ],
            [
                'category_key' => 'medical_document',
                'label' => 'Medical Document',
                'default_requirement' => 'not_needed',
                'allowed_document_names' => [
                    'Medical Certificate',
                    'Hospital Bill',
                    'Prescription',
                    'Treatment Plan',
                    'Prescription/Treatment Plan',
                ],
            ],
            [
                'category_key' => 'housing_property_document',
                'label' => 'Housing / Property Document',
                'default_requirement' => 'not_needed',
                'allowed_document_names' => [
                    'Property Documents',
                    'Tax Declaration',
                    'Contract to Sell',
                    'Construction Estimate',
                    'Building Permit',
                    'Contractor Quotation',
                ],
            ],
            [
                'category_key' => 'guarantor_document',
                'label' => 'Guarantor Document',
                'default_requirement' => 'not_needed',
                'allowed_document_names' => [
                    'Guarantor Proof of Income',
                    'Guarantor Proof of Address',
                    'Guarantor Consent Letter',
                    'Guarantor Valid ID Front',
                    'Guarantor Valid ID Back',
                ],
            ],
            [
                'category_key' => 'collateral_document',
                'label' => 'Collateral Document',
                'default_requirement' => 'not_needed',
                'allowed_document_names' => [
                    'Collateral Ownership Document',
                    'Official Receipt (OR)',
                    'Certificate of Registration (CR)',
                    'Appraisal Report',
                    'Collateral Photos',
                ],
            ],
        ];
    }
}
