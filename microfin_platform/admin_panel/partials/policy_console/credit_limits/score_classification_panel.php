<?php
$scoreBands = [
    [
        'label' => 'Fair Credit Score',
        'from' => (int)($credit_policy['score_thresholds']['not_recommended_min_score'] ?? 200),
    ],
    [
        'label' => 'Standard Credit Score',
        'from' => (int)($credit_policy['score_thresholds']['conditional_min_score'] ?? 400),
    ],
    [
        'label' => 'Good Credit Score',
        'from' => (int)($credit_policy['score_thresholds']['recommended_min_score'] ?? 600),
    ],
    [
        'label' => 'High Credit Score',
        'from' => (int)($credit_policy['score_thresholds']['highly_recommended_min_score'] ?? 800),
    ],
];
?>
<div class="policy-console-content-grid">
    <div class="policy-console-story-card">
        <div class="policy-console-story-copy">
            <span class="policy-console-eyebrow">Credit &amp; Limits</span>
            <h4>Score Classification</h4>
            <p class="text-muted">This is the cleaner shell for score bands, routing, and growth logic. The visual flow is being migrated first, while the existing scoring engine stays live behind the scenes.</p>
        </div>
        <div class="policy-console-story-meta">
            <span class="policy-console-chip">Bands</span>
            <span class="policy-console-chip">Routing</span>
        </div>
    </div>

    <div class="policy-console-band-grid">
        <?php foreach ($scoreBands as $band): ?>
            <div class="policy-console-band-card">
                <span><?php echo htmlspecialchars($band['label']); ?></span>
                <strong><?php echo number_format((int)$band['from']); ?>+</strong>
                <small>Current live threshold start</small>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="policy-console-split-grid">
        <div class="policy-console-card">
            <div class="policy-console-card-head">
                <strong>Current Scoring Weights</strong>
                <span class="policy-console-chip">Live snapshot</span>
            </div>
            <div class="policy-console-stat-list">
                <div><span>Verified Documents</span><strong>+<?php echo (int)($credit_policy['score_growth']['verified_documents_bonus'] ?? 0); ?></strong></div>
                <div><span>Completed Loan</span><strong>+<?php echo (int)($credit_policy['score_growth']['completed_loan_bonus'] ?? 0); ?></strong></div>
                <div><span>On-Time Payment</span><strong>+<?php echo (int)($credit_policy['score_growth']['on_time_payment_bonus'] ?? 0); ?></strong></div>
                <div><span>Late Payment</span><strong>-<?php echo (int)($credit_policy['score_growth']['late_payment_penalty'] ?? 0); ?></strong></div>
                <div><span>Missed Payment</span><strong>-<?php echo (int)($credit_policy['score_growth']['missed_payment_penalty'] ?? 0); ?></strong></div>
                <div><span>Active Loan Exposure</span><strong>-<?php echo (int)($credit_policy['score_growth']['active_loan_penalty'] ?? 0); ?></strong></div>
            </div>
        </div>

        <div class="policy-console-card">
            <div class="policy-console-card-head">
                <strong>Migration Note</strong>
                <span class="policy-console-chip">Next step</span>
            </div>
            <p class="text-muted">When we reconnect editing later, this tab should become the dedicated home for thresholds, score growth behavior, and score-to-routing interpretation.</p>
        </div>
    </div>
</div>
