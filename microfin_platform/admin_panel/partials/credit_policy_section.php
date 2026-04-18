<?php
$credit_policy_legacy_tabs = ['eligibility', 'score', 'limit'];

if (in_array((string)$credit_policy_subtab, $credit_policy_legacy_tabs, true)) {
    require __DIR__ . '/policy_console/legacy_credit_policy_section.php';
    return;
}
?>
<section id="credit_settings" class="view-section <?php echo $active_view === 'credit_settings' ? 'active' : ''; ?>">
    <div class="policy-console-shell">
        <div class="section-intro policy-console-intro">
            <div>
                <h2>Policy Console</h2>
                <p class="text-muted">This workspace is the branded admin adaptation of the newer credit policy flow. Legacy credit tabs remain available in the sidebar for side-by-side comparison while the new experience is rebuilt without backend rewiring.</p>
            </div>
            <div class="policy-console-intro-badges">
                <span class="badge badge-blue">UI Migration</span>
                <span class="badge badge-gray">Tenant Style Preserved</span>
                <span class="badge badge-gray">Legacy Comparison Available</span>
            </div>
        </div>

        <div class="policy-console-stage">
            <?php
            $policy_console_tab_partial_map = [
                'overview' => __DIR__ . '/policy_console/overview_tab.php',
                'credit_limits' => __DIR__ . '/policy_console/credit_limits_tab.php',
                'decision_rules' => __DIR__ . '/policy_console/decision_rules_tab.php',
                'collections_safeguards' => __DIR__ . '/policy_console/collections_safeguards_tab.php',
                'compliance_documents' => __DIR__ . '/policy_console/compliance_documents_tab.php',
            ];

            $policy_console_active_tab = $policy_console_tab_partial_map[$credit_policy_subtab] ?? $policy_console_tab_partial_map['overview'];
            require $policy_console_active_tab;
            ?>
        </div>
    </div>
</section>
