<?php
$incomeMultiplier = (float)($credit_policy['credit_limit']['income_multiplier'] ?? 0);
$approveMultiplier = (float)($credit_policy['credit_limit']['approve_band_multiplier'] ?? 0);
$reviewMultiplier = (float)($credit_policy['credit_limit']['review_band_multiplier'] ?? 0);
$fairMultiplier = (float)($credit_policy['credit_limit']['fair_band_multiplier'] ?? 0);
$atRiskMultiplier = (float)($credit_policy['credit_limit']['at_risk_band_multiplier'] ?? 0);
$maxCap = (float)($credit_policy['credit_limit']['max_credit_limit_cap'] ?? 0);
$roundTo = (float)($credit_policy['credit_limit']['round_to_nearest'] ?? 0);
?>
<div class="policy-console-content-grid">
    <div class="policy-console-story-card">
        <div class="policy-console-story-copy">
            <span class="policy-console-eyebrow">Credit &amp; Limits</span>
            <h4>Limit Engine</h4>
            <p class="text-muted">This shell keeps the limit story visible while the old live form is parked. It summarizes the current formula instead of exposing the old dense controls.</p>
        </div>
        <div class="policy-console-story-meta">
            <span class="policy-console-chip">Formula view</span>
            <span class="policy-console-chip">Display only</span>
        </div>
    </div>

    <div class="policy-console-formula-card">
        <span>Current Formula</span>
        <strong>Monthly income x <?php echo htmlspecialchars(number_format($incomeMultiplier, 2)); ?> x score-class multiplier</strong>
        <small><?php echo $maxCap > 0 ? 'Cap at PHP ' . number_format($maxCap, 2) : 'No maximum cap'; ?> | <?php echo $roundTo > 0 ? 'Round to nearest PHP ' . number_format($roundTo, 2) : 'No rounding rule'; ?></small>
    </div>

    <div class="policy-console-kpi-grid">
        <div class="policy-console-kpi-card">
            <span>Good / High</span>
            <strong><?php echo htmlspecialchars(number_format($approveMultiplier, 2)); ?>x</strong>
            <small>Approval band multiplier</small>
        </div>
        <div class="policy-console-kpi-card">
            <span>Standard</span>
            <strong><?php echo htmlspecialchars(number_format($reviewMultiplier, 2)); ?>x</strong>
            <small>Review band multiplier</small>
        </div>
        <div class="policy-console-kpi-card">
            <span>Fair</span>
            <strong><?php echo htmlspecialchars(number_format($fairMultiplier, 2)); ?>x</strong>
            <small>Fair band multiplier</small>
        </div>
        <div class="policy-console-kpi-card">
            <span>At-Risk</span>
            <strong><?php echo htmlspecialchars(number_format($atRiskMultiplier, 2)); ?>x</strong>
            <small>At-risk band multiplier</small>
        </div>
    </div>

    <div class="policy-console-card">
        <div class="policy-console-card-head">
            <strong>Why This Looks Different Now</strong>
            <span class="policy-console-chip">UX cleanup</span>
        </div>
        <p class="text-muted">The visible workspace now prioritizes clarity and structure over dumping every live control at once. The parked form logic is still available in code while we rebuild editing intentionally.</p>
    </div>
</div>
