<?php
$policy_console_compliance_config = isset($policy_console_compliance_documents) && is_array($policy_console_compliance_documents)
    ? $policy_console_compliance_documents
    : policy_console_compliance_documents_system_defaults();
$policy_console_compliance_rows = $policy_console_compliance_config['document_requirements'] ?? [];
$policy_console_validity_rules = $policy_console_compliance_config['validity_rules'] ?? [];
$policy_console_required_count = count(array_filter(
    $policy_console_compliance_rows,
    static fn(array $row): bool => ($row['requirement'] ?? 'not_needed') === 'required'
));
$policy_console_accepted_count = 0;
foreach ($policy_console_compliance_rows as $policy_console_row) {
    foreach ((array)($policy_console_row['document_options'] ?? []) as $policy_console_option) {
        if (!empty($policy_console_option['is_accepted'])) {
            $policy_console_accepted_count++;
        }
    }
}
$policy_console_help = static function (string $text, string ...$label): string {
    $labelText = $label[0] ?? 'More info';
    return '<span class="policy-help" tabindex="0" role="button" aria-label="'
        . htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8')
        . '" data-help="'
        . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        . '">!</span>';
};
$policy_console_owner_labels = [
    'compliance_team' => 'Compliance Team',
    'operations_team' => 'Operations Team',
    'branch_team' => 'Branch Team',
];
?>
<style>
.policy-governance-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.policy-governance-pill {
    cursor: pointer;
    margin: 0;
}
.policy-governance-pill input[type="checkbox"] {
    display: none;
}
.policy-governance-pill__label {
    display: inline-block;
    padding: 6px 12px;
    border: 1px solid var(--input-border, #4a5568);
    background-color: transparent;
    color: var(--text-muted, #a0aec0);
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    user-select: none;
    transition: all 0.2s ease;
}
.policy-governance-pill input[type="checkbox"]:checked + .policy-governance-pill__label {
    background-color: var(--primary-color, #0d6efd);
    border-color: var(--primary-color, #0d6efd);
    color: #ffffff;
}
.policy-governance-pill input[type="checkbox"]:disabled + .policy-governance-pill__label {
    opacity: 0.4;
    cursor: not-allowed;
}
</style>

<form method="POST" action="admin.php" class="policy-tab-form">
    <input type="hidden" name="action" value="save_policy_console_compliance_documents">
    <input type="hidden" name="credit_policy_tab" value="compliance_documents">

    <div class="policy-compact-stack">
        <section class="policy-compact-card">
            <div class="policy-save-row">
                <div class="policy-compact-toolbar-copy">
                    <h3>Compliance &amp; Documents</h3>
                    <p class="text-muted">Keep document governance, validity timing, and accepted submission types cleanly separated from scoring and collections.</p>
                </div>
            </div>

            <div class="policy-metric-grid">
                <div class="policy-metric-card">
                    <span>Governed Categories</span>
                    <strong><?php echo number_format(count($policy_console_compliance_rows)); ?></strong>
                    <small>Rows currently present in the governance matrix.</small>
                </div>
                <div class="policy-metric-card">
                    <span>Required Categories</span>
                    <strong><?php echo number_format($policy_console_required_count); ?></strong>
                    <small>Categories that must be submitted by default.</small>
                </div>
                <div class="policy-metric-card">
                    <span>Accepted Document Types</span>
                    <strong><?php echo number_format($policy_console_accepted_count); ?></strong>
                    <small>Accepted document options across all configured rows.</small>
                </div>
            </div>
        </section>

        <section class="policy-compact-card">
            <div class="policy-compact-card-head">
                <div class="policy-compact-card-title">
                    <h4>Document Validity Rules</h4>
                    <p class="text-muted">Keep renewal timing and verification ownership in this tab so the governance matrix stays operationally complete.</p>
                </div>
            </div>

            <div class="policy-form-grid policy-form-grid--three">
                <label class="policy-field">
                    <span class="policy-field-label">Default Validity Window <?php echo $policy_console_help('Number of days a submitted document remains valid before renewal is expected.'); ?></span>
                    <input type="number" class="form-control" name="pcd_default_validity_days" min="1" max="3650" step="1" value="<?php echo htmlspecialchars((string)($policy_console_validity_rules['default_validity_days'] ?? 365)); ?>" required>
                </label>
                <label class="policy-field">
                    <span class="policy-field-label">Renewal Reminder Lead Time <?php echo $policy_console_help('How many days before expiry the tenant should start surfacing renewal reminders.'); ?></span>
                    <input type="number" class="form-control" name="pcd_renewal_reminder_days" min="0" max="3650" step="1" value="<?php echo htmlspecialchars((string)($policy_console_validity_rules['renewal_reminder_days'] ?? 30)); ?>" required>
                </label>
                <label class="policy-field">
                    <span class="policy-field-label">Verification Owner <?php echo $policy_console_help('Primary team accountable for reviewing submitted documents and handling renewals.'); ?></span>
                    <select class="form-control" name="pcd_verification_owner">
                        <?php foreach ($policy_console_owner_labels as $policy_console_owner_key => $policy_console_owner_label): ?>
                            <option value="<?php echo htmlspecialchars($policy_console_owner_key); ?>" <?php echo (($policy_console_validity_rules['verification_owner'] ?? 'compliance_team') === $policy_console_owner_key) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($policy_console_owner_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </section>

        <section class="policy-compact-card">
            <div class="policy-compact-card-head">
                <div class="policy-compact-card-title">
                    <h4>Governance Matrix Editor</h4>
                    <p class="text-muted">Edit requirement status and accepted document types from one table instead of scattering document rules across multiple tabs.</p>
                </div>
            </div>

            <div class="policy-console-table-wrap">
                <table class="policy-console-table policy-governance-table">
                    <colgroup>
                        <col class="policy-governance-col-category">
                        <col class="policy-governance-col-requirement">
                        <col class="policy-governance-col-documents">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Category <?php echo $policy_console_help('Document category controlled by this governance row.'); ?></th>
                            <th>Requirement <?php echo $policy_console_help('Choose whether the category is required or not needed by default.'); ?></th>
                            <th>Accepted Document Types <?php echo $policy_console_help('Mark which uploaded document types count as valid submissions for the selected category.'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($policy_console_compliance_rows as $policy_console_row): ?>
                            <?php
                            $policy_console_category_key = (string)($policy_console_row['category_key'] ?? '');
                            $policy_console_requirement = (string)($policy_console_row['requirement'] ?? 'not_needed');
                            $policy_console_row_disabled = $policy_console_requirement === 'not_needed';
                            ?>
                            <tr class="<?php echo $policy_console_row_disabled ? 'policy-governance-row-muted' : ''; ?>">
                                <td>
                                    <div class="policy-table-title"><?php echo htmlspecialchars((string)($policy_console_row['label'] ?? 'Document Category')); ?></div>
                                    <div class="policy-table-subtext"><?php echo $policy_console_row_disabled ? 'Currently excluded from the default required-document set.' : 'Active in the tenant document-governance baseline.'; ?></div>
                                </td>
                                <td>
                                    <select class="form-control compliance-req-select" name="pcd_requirement[<?php echo htmlspecialchars($policy_console_category_key); ?>]">
                                        <option value="required" <?php echo $policy_console_requirement === 'required' ? 'selected' : ''; ?>>Required</option>
                                        <option value="conditional" <?php echo $policy_console_requirement === 'conditional' ? 'selected' : ''; ?>>Conditional</option>
                                        <option value="not_needed" <?php echo $policy_console_requirement === 'not_needed' ? 'selected' : ''; ?>>Not Needed</option>
                                    </select>
                                </td>
                                <td>
                                    <div class="policy-governance-options">
                                        <?php foreach ((array)($policy_console_row['document_options'] ?? []) as $policy_console_option): ?>
                                            <label class="policy-governance-pill <?php echo $policy_console_row_disabled ? 'is-disabled' : ''; ?>">
                                                <input
                                                    type="checkbox"
                                                    class="compliance-doc-checkbox"
                                                    name="pcd_docs[<?php echo htmlspecialchars($policy_console_category_key); ?>][]"
                                                    value="<?php echo htmlspecialchars((string)($policy_console_option['document_name'] ?? '')); ?>"
                                                    <?php echo !empty($policy_console_option['is_accepted']) ? 'checked' : ''; ?>
                                                    <?php echo $policy_console_row_disabled ? 'disabled' : ''; ?>
                                                >
                                                <span class="policy-governance-pill__label"><?php echo htmlspecialchars((string)($policy_console_option['document_name'] ?? 'Document Type')); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="floating-save-container">
            <button type="submit" class="btn btn-primary" id="policy-compliance-save-btn">
                <span class="material-symbols-rounded">save</span>
                Save Changes
            </button>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selects = document.querySelectorAll('.compliance-req-select');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            const tr = this.closest('tr');
            const subtext = tr.querySelector('.policy-table-subtext');
            const checkboxes = tr.querySelectorAll('.compliance-doc-checkbox');
            
            if (this.value === 'not_needed') {
                tr.classList.add('policy-governance-row-muted');
                if(subtext) subtext.textContent = 'Currently excluded from the default required-document set.';
                checkboxes.forEach(cb => {
                    cb.dataset.wasChecked = cb.checked;
                    cb.checked = false;
                    cb.disabled = true;
                });
            } else {
                tr.classList.remove('policy-governance-row-muted');
                if(subtext) subtext.textContent = 'Active in the tenant document-governance baseline.';
                checkboxes.forEach(cb => {
                    cb.disabled = false;
                    if(cb.dataset.wasChecked === 'true') {
                        cb.checked = true;
                    }
                });
            }
        });
    });
});
</script>
