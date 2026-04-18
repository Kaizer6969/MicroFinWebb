<?php
$policy_console_income_floor = !empty($credit_policy['eligibility']['allow_no_minimum_income'])
    ? 'No minimum income'
    : 'PHP ' . number_format((float)($credit_policy['eligibility']['min_monthly_income'] ?? 0), 2);
$policy_console_employment_count = count((array)($credit_policy['eligibility']['allowed_employment_statuses'] ?? []));
$policy_console_ci_mode = !empty($credit_policy['ci_rules']['require_ci'])
    ? 'Always required'
    : (((float)($credit_policy['ci_rules']['ci_required_above_amount'] ?? 0) > 0)
        ? 'Required above PHP ' . number_format((float)($credit_policy['ci_rules']['ci_required_above_amount'] ?? 0), 2)
        : 'Optional');
?>
<div class="policy-console-content-grid">
    <div class="policy-console-story-card">
        <div class="policy-console-story-copy">
            <span class="policy-console-eyebrow">Credit &amp; Limits</span>
            <h4>Borrower Eligibility</h4>
            <p class="text-muted">This shell keeps the same policy area but shifts the experience toward clearer admin review before we reconnect editing logic.</p>
        </div>
        <div class="policy-console-story-meta">
            <span class="policy-console-chip">Legacy logic preserved</span>
            <span class="policy-console-chip">UI-only</span>
        </div>
    </div>

    <div class="policy-console-kpi-grid">
        <div class="policy-console-kpi-card">
            <span>Income Floor</span>
            <strong><?php echo htmlspecialchars($policy_console_income_floor); ?></strong>
            <small>Current live backend setting</small>
        </div>
        <div class="policy-console-kpi-card">
            <span>Allowed Employment Types</span>
            <strong><?php echo number_format((int)$policy_console_employment_count); ?></strong>
            <small>Statuses currently allowed to proceed</small>
        </div>
        <div class="policy-console-kpi-card">
            <span>CI Requirement</span>
            <strong><?php echo htmlspecialchars($policy_console_ci_mode); ?></strong>
            <small>Live investigation rule snapshot</small>
        </div>
    </div>

    <div class="policy-console-split-grid">
        <div class="policy-console-card">
            <div class="policy-console-card-head">
                <strong>What This Tab Will Own</strong>
                <span class="policy-console-chip">Planned</span>
            </div>
            <ul class="policy-console-list">
                <li>Entry gate rules before score classification starts.</li>
                <li>Income and employment requirement flow.</li>
                <li>Clearer admin copy around who proceeds and who stops.</li>
            </ul>
        </div>

        <div class="policy-console-card">
            <div class="policy-console-card-head">
                <strong>Migration Note</strong>
                <span class="policy-console-chip">Safe mode</span>
            </div>
            <p class="text-muted">The editable live form is parked during this migration so the backend contract stays untouched. This panel is intentionally presentation-first for now.</p>
        </div>
    </div>
</div>
