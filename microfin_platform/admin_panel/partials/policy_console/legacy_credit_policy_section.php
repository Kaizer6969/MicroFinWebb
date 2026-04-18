                <section id="credit_settings" class="view-section <?php echo $active_view === 'credit_settings' ? 'active' : ''; ?>">

                    <div class="credit-policy-shell">

                        <div class="credit-settings-stack">

                            <form method="POST" action="admin.php" class="card credit-scoring-card credit-policy-form-card" id="credit-policy-form" data-credit-policy-defaults="<?php echo $credit_policy_defaults_json; ?>">

                                <input type="hidden" name="action" value="save_credit_policy">
                                <input type="hidden" name="credit_policy_tab" id="credit-policy-active-tab-input" value="<?php echo htmlspecialchars((string)$credit_policy_subtab); ?>">



                                <?php

                                $credit_policy_header_map = [

                                    'overview' => ['Overview', 'Review the full flow.'],

                                    'eligibility' => ['Borrower Eligibility', 'Set entry requirements.'],

                                    'score' => ['Score Classification', 'Set bands and routing.'],

                                    'limit' => ['Limit Engine', 'Set estimate logic and guardrails.'],

                                ];

                                $credit_policy_header = $credit_policy_header_map[$credit_policy_subtab] ?? $credit_policy_header_map['overview'];

                                ?>





                                <div class="credit-policy-tab-panels">

                                    <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="overview" <?php echo $credit_policy_subtab === 'overview' ? '' : 'hidden'; ?>>
                                        <?php require __DIR__ . '/overview_tab.php'; ?>
                                    </div>

                                    <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="credit_limits" <?php echo $credit_policy_subtab === 'credit_limits' ? '' : 'hidden'; ?>>
                                        <?php require __DIR__ . '/credit_limits_tab.php'; ?>
                                    </div>

                                    <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="decision_rules" <?php echo $credit_policy_subtab === 'decision_rules' ? '' : 'hidden'; ?>>
                                        <?php require __DIR__ . '/decision_rules_tab.php'; ?>
                                    </div>

                                    <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="collections_safeguards" <?php echo $credit_policy_subtab === 'collections_safeguards' ? '' : 'hidden'; ?>>
                                        <?php require __DIR__ . '/collections_safeguards_tab.php'; ?>
                                    </div>

                                    <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="compliance_documents" <?php echo $credit_policy_subtab === 'compliance_documents' ? '' : 'hidden'; ?>>
                                        <?php require __DIR__ . '/compliance_documents_tab.php'; ?>
                                    </div>

                                    <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="legacy_overview" <?php echo $credit_policy_subtab === 'legacy_overview' ? '' : 'hidden'; ?>>

                                        <div class="credit-engine-panel credit-engine-panel-span-2 credit-policy-panel" id="credit-policy-builder">

                                            <div class="credit-engine-panel-head credit-policy-panel-headline">

                                                <div class="credit-policy-head-main">

                                                    <div class="credit-policy-head-icon"><span class="material-symbols-rounded">dashboard_customize</span></div>

                                                    <div class="credit-policy-panel-title">

                                                        <span class="credit-policy-section-step">Workspace</span>

                                                        <h4>Overview</h4>

                                                        <p class="text-muted">Review the current credit control flow.</p>

                                                    </div>

                                                </div>

                                                <div class="credit-policy-section-meta">

                                                    <span class="badge badge-gray">Overview</span>

                                                    <span class="badge badge-blue">Live policy snapshot</span>

                                                </div>

                                            </div>


                                            <?php
                                            $credit_policy_sim_employment_default = $credit_policy_allowed_employment_values[0] ?? ($credit_policy_employment_options[0] ?? '');
                                            $credit_policy_limit_income_multiplier_display = ((float)($credit_policy['credit_limit']['income_multiplier'] ?? 0) <= 0) ? 1.5 : (float)$credit_policy['credit_limit']['income_multiplier'];
                                            $credit_policy_limit_calc_scenarios = [
                                                'high' => [
                                                    'label' => 'High Credit Scenario',
                                                    'score' => max((int)$credit_policy_highly_recommended_from, 820),
                                                ],
                                                'good' => [
                                                    'label' => 'Good Credit Scenario',
                                                    'score' => max((int)$credit_policy_recommended_from, (int) round((((int)$credit_policy_recommended_from) + ((int)$credit_policy_recommended_end)) / 2)),
                                                ],
                                                'standard' => [
                                                    'label' => 'Standard Credit Scenario',
                                                    'score' => max((int)$credit_policy_conditional_from, (int) round((((int)$credit_policy_conditional_from) + ((int)$credit_policy_conditional_end)) / 2)),
                                                ],
                                                'fair' => [
                                                    'label' => 'Fair Credit Scenario',
                                                    'score' => max((int)$credit_policy_not_recommended_from, (int) round((((int)$credit_policy_not_recommended_from) + ((int)$credit_policy_not_recommended_end)) / 2)),
                                                ],
                                                'at_risk' => [
                                                    'label' => 'At-Risk Credit Scenario',
                                                    'score' => max(0, (int)$credit_policy_not_recommended_from - 50),
                                                ],
                                            ];
                                            $credit_policy_limit_calc_default_scenario_key = 'good';
                                            $credit_policy_limit_calc_default_scenario = $credit_policy_limit_calc_scenarios[$credit_policy_limit_calc_default_scenario_key];
                                            $credit_policy_limit_calc_income_default = 25000;
                                            $credit_policy_limit_calc_score_default = (int)($credit_policy_limit_calc_default_scenario['score'] ?? 650);
                                            $credit_policy_limit_calc_recommendation = mf_credit_policy_score_recommendation($credit_policy, (float)$credit_policy_limit_calc_score_default);
                                            $credit_policy_limit_calc_multiplier = mf_credit_policy_band_multiplier($credit_policy, (float)$credit_policy_limit_calc_score_default);
                                            $credit_policy_limit_calc_limit = $credit_policy_limit_calc_income_default
                                                * $credit_policy_limit_income_multiplier_display
                                                * $credit_policy_limit_calc_multiplier;
                                            $credit_policy_limit_calc_cap = (float)($credit_policy['credit_limit']['max_credit_limit_cap'] ?? 0);
                                            if ($credit_policy_limit_calc_cap > 0) {
                                                $credit_policy_limit_calc_limit = min($credit_policy_limit_calc_limit, $credit_policy_limit_calc_cap);
                                            }
                                            $credit_policy_limit_calc_limit = mf_credit_policy_round_limit(
                                                (float)$credit_policy_limit_calc_limit,
                                                (float)($credit_policy['credit_limit']['round_to_nearest'] ?? 0)
                                            );
                                            ?>

                                            <div class="credit-policy-builder-shell">

                                                <div class="credit-policy-overview-layout">

                                                    <div class="credit-policy-overview-main">

                                                        <div class="credit-policy-note-card credit-policy-builder-summary-card">

                                                            <strong>Live policy snapshot</strong>

                                                            <div class="credit-policy-builder-summary-list">

                                                                <div class="credit-policy-builder-summary-item">

                                                                    <span>Income Floor</span>

                                                                    <strong id="credit-policy-builder-income-floor"><?php echo '&#8369;' . number_format((float)($credit_policy['eligibility']['min_monthly_income'] ?? 0), 2); ?></strong>

                                                                </div>

                                                                <div class="credit-policy-builder-summary-item">

                                                                    <span>New Client Score</span>

                                                                    <strong id="credit-policy-builder-default-score"><?php echo (int)($credit_policy['score_thresholds']['new_client_default_score'] ?? 500); ?></strong>

                                                                </div>

                                                                <div class="credit-policy-builder-summary-item">

                                                                    <span>Approval Starts</span>

                                                                    <strong id="credit-policy-builder-approval-start"><?php echo (int)$credit_policy_recommended_from; ?> and above</strong>

                                                                </div>

                                                                <div class="credit-policy-builder-summary-item">

                                                                    <span>Offer Ceiling</span>

                                                                    <strong id="credit-policy-builder-cap"><?php echo ((float)($credit_policy['credit_limit']['max_credit_limit_cap'] ?? 0) > 0) ? '&#8369;' . number_format((float)($credit_policy['credit_limit']['max_credit_limit_cap'] ?? 0), 2) : 'No cap'; ?></strong>

                                                                </div>

                                                            </div>

                                                            <p class="credit-policy-field-hint" style="margin: 0;">Unsaved changes update this summary and the simulator immediately.</p>

                                                        </div>



                                                        <div class="credit-policy-builder-grid">

                                                            <div class="credit-policy-builder-step">

                                                                <div class="credit-policy-builder-step-head">

                                                                    <div class="credit-policy-builder-step-icon"><span class="material-symbols-rounded">verified_user</span></div>

                                                                    <div class="credit-policy-builder-step-copy">

                                                                        <strong>Borrower Eligibility</strong>

                                                                        <p>Gate borrowers before scoring.</p>

                                                                    </div>

                                                                </div>

                                                                <div class="credit-policy-builder-step-summary">

                                                                    <span>Current Rule</span>

                                                                    <strong id="credit-policy-builder-eligibility-summary">Minimum income and employment checks are active.</strong>

                                                                </div>

                                                                <button type="button" class="btn btn-outline" data-credit-policy-nav-action="eligibility">Edit Eligibility Rules</button>

                                                            </div>



                                                            <div class="credit-policy-builder-step">

                                                                <div class="credit-policy-builder-step-head">

                                                                    <div class="credit-policy-builder-step-icon"><span class="material-symbols-rounded">query_stats</span></div>

                                                                    <div class="credit-policy-builder-step-copy">

                                                                        <strong>Score Classification</strong>

                                                                        <p>Map score bands to routing.</p>

                                                                    </div>

                                                                </div>

                                                                <div class="credit-policy-builder-step-summary">

                                                                    <span>Current Rule</span>

                                                                    <strong id="credit-policy-builder-score-summary">At-Risk, Fair, Standard, Good, and High bands define the score ladder.</strong>

                                                                </div>

                                                                <button type="button" class="btn btn-outline" data-credit-policy-nav-action="score">Edit Score Bands</button>

                                                            </div>



                                                            <div class="credit-policy-builder-step">

                                                                <div class="credit-policy-builder-step-head">

                                                                    <div class="credit-policy-builder-step-icon"><span class="material-symbols-rounded">payments</span></div>

                                                                    <div class="credit-policy-builder-step-copy">

                                                                        <strong>Limit Engine</strong>

                                                                        <p>Shape the recommended offer.</p>

                                                                    </div>

                                                                </div>

                                                                <div class="credit-policy-builder-step-summary">

                                                                    <span>Current Rule</span>

                                                                    <strong id="credit-policy-builder-limit-summary">Income multiplier, band multipliers, cap, and rounding shape the estimate.</strong>

                                                                </div>

                                                                <button type="button" class="btn btn-outline" data-credit-policy-nav-action="limit">Edit Limit Logic</button>

                                                            </div>

                                                        </div>



                                                        <div class="credit-policy-builder-summary-note">

                                                            <div class="credit-policy-builder-summary-note-row">

                                                                <span>Decision Routing</span>

                                                                <strong id="credit-policy-builder-routing-summary">Below <?php echo (int)$credit_policy_conditional_from; ?> = Reject, <?php echo (int)$credit_policy_conditional_from; ?>-<?php echo (int)$credit_policy_conditional_end; ?> = Manual Review, <?php echo (int)$credit_policy_recommended_from; ?>+ = Approval Candidate.</strong>

                                                            </div>

                                                            <div class="credit-policy-builder-summary-note-row">

                                                                <span>Offer Logic</span>

                                                                <strong id="credit-policy-builder-offer-summary">Start with monthly income x <?php echo htmlspecialchars((string)($credit_policy['credit_limit']['income_multiplier'] ?? 0)); ?> x classification multiplier.</strong>

                                                            </div>

                                                            <p class="credit-policy-field-hint" id="credit-policy-builder-rounding-summary" style="margin: 0;">Maximum offer <?php echo ((float)($credit_policy['credit_limit']['max_credit_limit_cap'] ?? 0) > 0) ? '&#8369;' . number_format((float)($credit_policy['credit_limit']['max_credit_limit_cap'] ?? 0), 2) : 'No cap'; ?>. Rounded to nearest <?php echo ((float)($credit_policy['credit_limit']['round_to_nearest'] ?? 0) > 0) ? '&#8369;' . number_format((float)($credit_policy['credit_limit']['round_to_nearest'] ?? 0), 2) : 'No rounding'; ?>.</p>

                                                        </div>

                                                    </div>



                                                    <aside class="credit-policy-overview-simulator">

                                                        <div class="credit-policy-subpanel">

                                                            <div class="credit-policy-subpanel-header">

                                                                <div>

                                                                    <strong>Credit Control Simulator</strong>

                                                                    <p>Test borrower eligibility, score routing, and offer logic together.</p>

                                                                </div>

                                                                <span class="badge badge-blue">Live</span>

                                                            </div>



                                                            <div class="credit-policy-simulator-grid">

                                                                <div class="form-group">

                                                                    <label for="credit-policy-sim-employment">Employment Status</label>

                                                                    <select class="form-control" id="credit-policy-sim-employment">

                                                                        <?php foreach ($credit_policy_employment_options as $employment_option): ?>

                                                                            <option value="<?php echo htmlspecialchars($employment_option); ?>" <?php echo $employment_option === $credit_policy_sim_employment_default ? 'selected' : ''; ?>><?php echo htmlspecialchars($employment_option); ?></option>

                                                                        <?php endforeach; ?>

                                                                    </select>

                                                                </div>

                                                                <div class="form-group">

                                                                    <label for="credit-policy-sim-income">Monthly Income</label>

                                                                    <div class="credit-input-with-prefix">

                                                                        <span class="credit-input-prefix">&#8369;</span>

                                                                        <input type="number" class="form-control" id="credit-policy-sim-income" value="25000" min="0" step="0.01">

                                                                    </div>

                                                                </div>

                                                                <div class="form-group">

                                                                    <label for="credit-policy-sim-score">Credit Score</label>

                                                                    <input type="number" class="form-control" id="credit-policy-sim-score" value="820" min="0" step="1">

                                                                </div>

                                                                <div class="form-group">

                                                                    <label for="credit-policy-sim-requested">Requested Amount</label>

                                                                    <div class="credit-input-with-prefix">

                                                                        <span class="credit-input-prefix">&#8369;</span>

                                                                        <input type="number" class="form-control" id="credit-policy-sim-requested" value="60000" min="0" step="0.01">

                                                                    </div>

                                                                </div>

                                                            </div>



                                                            <div class="credit-policy-simulator-hero is-review" id="credit-policy-sim-decision-card">

                                                                <span>Likely Decision</span>

                                                                <strong id="credit-policy-sim-decision">Review</strong>

                                                                <p id="credit-policy-sim-caption">Shows the entry result, score route, and estimated offer for the sample borrower.</p>

                                                            </div>



                                                            <div class="credit-policy-simulator-metrics">

                                                                <div class="credit-policy-output-card">

                                                                    <span>Eligibility</span>

                                                                    <strong id="credit-policy-sim-eligibility">Passes Entry Checks</strong>

                                                                </div>

                                                                <div class="credit-policy-output-card">

                                                                    <span>Score Class</span>

                                                                    <strong id="credit-policy-sim-class">High Credit Score</strong>

                                                                </div>

                                                                <div class="credit-policy-output-card">

                                                                    <span>Estimated Limit</span>

                                                                    <strong id="credit-policy-sim-limit">&#8369;0.00</strong>

                                                                </div>

                                                                <div class="credit-policy-output-card">

                                                                    <span>Suggested Offer</span>

                                                                    <strong id="credit-policy-sim-offer">&#8369;0.00</strong>

                                                                </div>

                                                            </div>



                                                            <div class="credit-policy-simulator-formula">

                                                                <span>Estimate Logic</span>

                                                                <strong id="credit-policy-sim-formula">Income x classification multiplier</strong>

                                                            </div>



                                                            <div class="credit-policy-note" id="credit-policy-sim-note">Visual guide only. This simulator uses the current unsaved borrower eligibility, score classification, and limit settings.</div>

                                                        </div>

                                                    </aside>

                                                </div>

                                            </div>

                                        </div>

                                    </div>



                                    <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="eligibility" <?php echo $credit_policy_subtab === 'eligibility' ? '' : 'hidden'; ?>>

                                        <div class="credit-engine-panel credit-engine-panel-span-2 credit-policy-panel" id="credit-policy-eligibility">

                                            <div class="credit-engine-panel-head credit-policy-panel-headline">

                                                <div class="credit-policy-head-main">

                                                    <div class="credit-policy-head-icon"><span class="material-symbols-rounded">verified_user</span></div>

                                                    <div class="credit-policy-panel-title">

                                                        <span class="credit-policy-section-step">Section 1</span>

                                                        <h4>Borrower Eligibility</h4>

                                                        <p class="text-muted">Set the first-pass requirements that determine which borrowers can move forward into score classification and offer sizing.</p>

                                                    </div>

                                                </div>

                                                <div class="credit-policy-section-meta">

                                                    <span class="badge badge-gray">Borrower checks</span>

                                                    <span class="badge badge-blue" id="credit-policy-employment-count-badge"><?php echo (int)$credit_policy_allowed_employment_count; ?> statuses enabled</span>

                                                </div>

                                            </div>



                                            <div class="credit-policy-mini-grid">

                                                <div class="credit-policy-subpanel" style="gap: 12px;">

                                                    <input type="hidden" name="cp_allow_no_minimum_income" value="0">

                                                    <label class="credit-toggle-row" for="cp-allow-no-minimum-income">

                                                        <span class="credit-toggle-copy">

                                                            <strong>No Minimum Income Requirement</strong>

                                                            <small>Turn this on if you want applications to move forward even when no income floor is enforced.</small>

                                                        </span>

                                                        <input type="checkbox" id="cp-allow-no-minimum-income" name="cp_allow_no_minimum_income" value="1" <?php echo !empty($credit_policy['eligibility']['allow_no_minimum_income']) ? 'checked' : ''; ?>>

                                                    </label>

                                                </div>

                                                <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                    <label for="cp-min-monthly-income">Minimum Monthly Income</label>

                                                    <div class="credit-input-with-prefix">

                                                        <span class="credit-input-prefix">&#8369;</span>

                                                        <input type="number" class="form-control" id="cp-min-monthly-income" name="cp_min_monthly_income" min="0" step="0.01" value="<?php echo htmlspecialchars((string)(!empty($credit_policy['eligibility']['allow_no_minimum_income']) ? 0 : ($credit_policy['eligibility']['min_monthly_income'] ?? 0))); ?>" <?php echo !empty($credit_policy['eligibility']['allow_no_minimum_income']) ? 'disabled aria-disabled="true"' : ''; ?>>

                                                    </div>

                                                    <p class="credit-policy-field-hint" id="credit-policy-income-floor-hint">Applications below this amount are filtered out before the offer is estimated.</p>

                                                </div>

                                                <div class="credit-policy-note-card">

                                                    <strong>What this section controls</strong>

                                                    <ul>

                                                        <li>Uses borrower income and allowed employment types as the entry gate.</li>

                                                        <li>Stops ineligible borrowers before score classification or limit calculation runs.</li>

                                                        <li>Works best when at least one employment status remains enabled.</li>

                                                    </ul>

                                                </div>

                                            </div>



                                            <div class="credit-policy-choice-block">

                                                <div class="credit-policy-subhead">

                                                    <strong>Allowed employment statuses</strong>

                                                    <span class="text-muted" id="credit-policy-employment-count-text"><?php echo (int)$credit_policy_allowed_employment_count; ?> selected</span>

                                                </div>

                                                <p class="credit-policy-field-hint">Check the employment statuses that are allowed to proceed.</p>

                                            </div>



                                            <div class="credit-policy-table-map credit-policy-employment-list">

                                                <?php foreach ($credit_policy_employment_options as $employment_option): ?>

                                                    <label class="credit-policy-table-row credit-policy-employment-row">

                                                        <input type="checkbox" name="cp_allowed_employment_statuses[]" value="<?php echo htmlspecialchars($employment_option); ?>" <?php echo in_array($employment_option, $credit_policy_allowed_employment_values, true) ? 'checked' : ''; ?>>

                                                        <div class="credit-policy-employment-row-copy">

                                                            <strong><?php echo htmlspecialchars($employment_option); ?></strong>

                                                            <span>Allow this employment status to proceed.</span>

                                                        </div>

                                                    </label>

                                                <?php endforeach; ?>

                                            </div>

                                        </div>



                                    </div>



                                    <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="score" <?php echo $credit_policy_subtab === 'score' ? '' : 'hidden'; ?>>

                                        <div class="credit-engine-panel credit-policy-panel" id="credit-policy-thresholds">

                                            <div class="credit-engine-panel-head credit-policy-panel-headline">

                                                <div class="credit-policy-head-main">

                                                    <div class="credit-policy-head-icon"><span class="material-symbols-rounded">query_stats</span></div>

                                                    <div class="credit-policy-panel-title">

                                                        <span class="credit-policy-section-step">Section 2</span>

                                                        <h4>Score Thresholds</h4>

                                                        <p class="text-muted">Set the score ranges used to classify borrower credit scores. Decision routing is shown separately below.</p>

                                                    </div>

                                                </div>

                                                <div class="credit-policy-section-meta">

                                                    <span class="badge badge-gray">Score classification</span>

                                                </div>

                                            </div>



                                            <div class="credit-policy-score-shell">

                                                <div class="credit-policy-score-main">

                                                    <div class="credit-policy-note-card">

                                                        <strong>How to set the score bands</strong>

                                                        <ul>

                                                            <li>Each band must start higher than the band before it.</li>

                                                            <li>These thresholds classify the borrower score only; routing is shown separately.</li>

                                                            <li>The top band stays open above the <code>High Credit Score</code> threshold.</li>

                                                        </ul>

                                                    </div>



                                                    <div class="credit-policy-threshold-grid">

                                                        <div class="credit-policy-threshold-card">

                                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                                <label for="cp-not-recommended-score">Fair Credit Score From</label>

                                                                <input type="number" class="form-control" id="cp-not-recommended-score" name="cp_not_recommended_min_score" min="0" step="1" value="<?php echo htmlspecialchars((string)($credit_policy['score_thresholds']['not_recommended_min_score'] ?? 200)); ?>">

                                                                <p class="credit-policy-field-hint">Scores from this point up to the next band are classified as <code>Fair Credit Score</code>. Anything below this remains <code>At-Risk Credit Score</code>.</p>

                                                            </div>

                                                        </div>

                                                        <div class="credit-policy-threshold-card">

                                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                                <label for="cp-conditional-score">Standard Credit Score From</label>

                                                                <input type="number" class="form-control" id="cp-conditional-score" name="cp_conditional_min_score" min="1" step="1" value="<?php echo htmlspecialchars((string)($credit_policy['score_thresholds']['conditional_min_score'] ?? 400)); ?>">

                                                                <p class="credit-policy-field-hint">Scores from this point up to the next band are classified as <code>Standard Credit Score</code>.</p>

                                                            </div>

                                                        </div>

                                                        <div class="credit-policy-threshold-card">

                                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                                <label for="cp-recommended-score">Good Credit Score From</label>

                                                                <input type="number" class="form-control" id="cp-recommended-score" name="cp_recommended_min_score" min="2" step="1" value="<?php echo htmlspecialchars((string)($credit_policy['score_thresholds']['recommended_min_score'] ?? 600)); ?>">

                                                                <p class="credit-policy-field-hint">Scores from this point up to the next band are classified as <code>Good Credit Score</code>.</p>

                                                            </div>

                                                        </div>

                                                        <div class="credit-policy-threshold-card">

                                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                                <label for="cp-highly-recommended-score">High Credit Score From</label>

                                                                <input type="number" class="form-control" id="cp-highly-recommended-score" name="cp_highly_recommended_min_score" min="3" step="1" value="<?php echo htmlspecialchars((string)($credit_policy['score_thresholds']['highly_recommended_min_score'] ?? 800)); ?>">

                                                                <p class="credit-policy-field-hint">Scores at or above this are classified as <code>High Credit Score</code>. The score range stays open above this point.</p>

                                                                <p class="credit-policy-field-hint is-warning" id="credit-policy-threshold-warning" hidden>Each score band must start higher than the band before it.</p>

                                                            </div>

                                                        </div>

                                                    </div>



                                                    <div class="credit-policy-subpanel">

                                                        <div class="credit-policy-subpanel-header">

                                                            <div>

                                                                <strong>Score Growth Rules</strong>

                                                                <p>Set the score points added for good behavior and deducted for risk signals.</p>

                                                            </div>

                                                            <span class="badge badge-blue">Policy form</span>

                                                        </div>



                                                        <div class="credit-policy-growth-inline-grid">

                                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                                <label for="cp-verified-documents-bonus" class="credit-policy-growth-field-label">
                                                                    <span>Verified Docs</span>
                                                                    <span class="credit-policy-growth-field-type is-bonus">Bonus</span>
                                                                </label>

                                                                <input type="number" class="form-control" id="cp-verified-documents-bonus" name="cp_verified_documents_bonus" min="0" step="1" value="<?php echo htmlspecialchars((string)($credit_policy['score_growth']['verified_documents_bonus'] ?? 20)); ?>">

                                                                <p class="credit-policy-field-hint">Fully verified profile.</p>

                                                            </div>

                                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                                <label for="cp-completed-loan-bonus" class="credit-policy-growth-field-label">
                                                                    <span>Completed Loan</span>
                                                                    <span class="credit-policy-growth-field-type is-bonus">Bonus</span>
                                                                </label>

                                                                <input type="number" class="form-control" id="cp-completed-loan-bonus" name="cp_completed_loan_bonus" min="0" step="1" value="<?php echo htmlspecialchars((string)($credit_policy['score_growth']['completed_loan_bonus'] ?? 30)); ?>">

                                                                <p class="credit-policy-field-hint">Clean loan payoff.</p>

                                                            </div>

                                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                                <label for="cp-on-time-payment-bonus" class="credit-policy-growth-field-label">
                                                                    <span>On-Time Payment</span>
                                                                    <span class="credit-policy-growth-field-type is-bonus">Bonus</span>
                                                                </label>

                                                                <input type="number" class="form-control" id="cp-on-time-payment-bonus" name="cp_on_time_payment_bonus" min="0" step="1" value="<?php echo htmlspecialchars((string)($credit_policy['score_growth']['on_time_payment_bonus'] ?? 8)); ?>">

                                                                <p class="credit-policy-field-hint">Per on-time repayment.</p>

                                                            </div>

                                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                                <label for="cp-late-payment-penalty" class="credit-policy-growth-field-label">
                                                                    <span>Late Payment</span>
                                                                    <span class="credit-policy-growth-field-type is-deduction">Deduct</span>
                                                                </label>

                                                                <input type="number" class="form-control" id="cp-late-payment-penalty" name="cp_late_payment_penalty" min="0" step="1" value="<?php echo htmlspecialchars((string)($credit_policy['score_growth']['late_payment_penalty'] ?? 18)); ?>">

                                                                <p class="credit-policy-field-hint">Per late repayment.</p>

                                                            </div>

                                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                                <label for="cp-missed-payment-penalty" class="credit-policy-growth-field-label">
                                                                    <span>Missed Payment</span>
                                                                    <span class="credit-policy-growth-field-type is-deduction">Deduct</span>
                                                                </label>

                                                                <input type="number" class="form-control" id="cp-missed-payment-penalty" name="cp_missed_payment_penalty" min="0" step="1" value="<?php echo htmlspecialchars((string)($credit_policy['score_growth']['missed_payment_penalty'] ?? 40)); ?>">

                                                                <p class="credit-policy-field-hint">Per missed schedule.</p>

                                                            </div>

                                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                                <label for="cp-active-loan-penalty" class="credit-policy-growth-field-label">
                                                                    <span>Active Exposure</span>
                                                                    <span class="credit-policy-growth-field-type is-deduction">Deduct</span>
                                                                </label>

                                                                <input type="number" class="form-control" id="cp-active-loan-penalty" name="cp_active_loan_penalty" min="0" step="1" value="<?php echo htmlspecialchars((string)($credit_policy['score_growth']['active_loan_penalty'] ?? 12)); ?>">

                                                                <p class="credit-policy-field-hint">While loans are still active.</p>

                                                            </div>

                                                        </div>



                                                        <div class="credit-policy-note-card">

                                                            <strong>Usage</strong>

                                                            <p class="credit-policy-field-hint" style="margin: 0;">These values belong to the tenant policy and are used when a borrower score is recalculated from profile or repayment events.</p>

                                                        </div>

                                                    </div>

                                                </div>



                                                <aside class="credit-policy-score-rail">

                                                    <div class="credit-policy-threshold-card">

                                                        <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                            <label for="cp-new-client-default-score">Default Credit Score</label>

                                                            <input type="number" class="form-control" id="cp-new-client-default-score" name="cp_new_client_default_score" min="0" step="1" value="<?php echo htmlspecialchars((string)($credit_policy['score_thresholds']['new_client_default_score'] ?? 500)); ?>">

                                                            <p class="credit-policy-field-hint">Use the starting score you want to assign when no recorded score exists yet.</p>

                                                            <p class="credit-policy-field-hint" id="credit-policy-default-score-band-hint" style="font-weight: 500; margin: 0;"></p>

                                                        </div>

                                                    </div>



                                                    <div class="credit-policy-note-card">

                                                        <strong>Score Classification</strong>

                                                        <ul>

                                                            <li><span id="credit-policy-threshold-copy-at-risk">Below <?php echo (int)$credit_policy_not_recommended_from; ?></span> = At-Risk Credit Score.</li>

                                                            <li><span id="credit-policy-threshold-copy-not-recommended"><?php echo (int)$credit_policy_not_recommended_from; ?>-<?php echo (int)$credit_policy_not_recommended_end; ?></span> = Fair Credit Score.</li>

                                                            <li><span id="credit-policy-threshold-copy-conditional"><?php echo (int)$credit_policy_conditional_from; ?>-<?php echo (int)$credit_policy_conditional_end; ?></span> = Standard Credit Score.</li>

                                                            <li><span id="credit-policy-threshold-copy-recommended"><?php echo (int)$credit_policy_recommended_from; ?>-<?php echo (int)$credit_policy_recommended_end; ?></span> = Good Credit Score.</li>

                                                            <li><span id="credit-policy-threshold-copy-highly-recommended"><?php echo (int)$credit_policy_highly_recommended_from; ?> and above</span> = High Credit Score.</li>

                                                        </ul>

                                                    </div>



                                                    <div class="credit-policy-note-card">

                                                        <strong>Decision Routing</strong>

                                                        <p class="credit-policy-field-hint" id="credit-policy-threshold-routing-copy" style="margin: 0;">Decision routing: Below <?php echo (int)$credit_policy_conditional_from; ?> = Reject, <?php echo (int)$credit_policy_conditional_from; ?>-<?php echo (int)$credit_policy_conditional_end; ?> = Manual Review, <?php echo (int)$credit_policy_recommended_from; ?> and above = Approval Candidate.</p>

                                                    </div>

                                                </aside>

                                            </div>

                                        </div>



                                    </div>



                                    <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="ci" hidden>

                                        <div class="credit-engine-panel credit-engine-panel-span-2 credit-policy-panel" id="credit-policy-ci">

                                            <div class="credit-engine-panel-head credit-policy-panel-headline">

                                                <div class="credit-policy-head-main">

                                                    <div class="credit-policy-head-icon"><span class="material-symbols-rounded">fact_check</span></div>

                                                    <div class="credit-policy-panel-title">

                                                        <span class="credit-policy-section-step">Section 3</span>

                                                        <h4>Investigation Decision Flow</h4>

                                                        <p class="text-muted">Decide when a completed investigation should affect the application decision.</p>

                                                    </div>

                                                </div>

                                                <div class="credit-policy-section-meta">

                                                    <span class="badge badge-gray">Investigation settings</span>

                                                    <span class="badge badge-blue" id="credit-policy-ci-count-badge"><?php echo (int)$credit_policy_auto_ci_count; ?> approve / <?php echo (int)$credit_policy_review_ci_count; ?> review</span>

                                                </div>

                                            </div>



                                            <div class="credit-engine-inline-grid">

                                                <div class="form-group" style="margin-bottom: 0;">

                                                    <input type="hidden" name="cp_require_ci" value="0">

                                                    <label class="credit-toggle-row" for="cp-require-ci">

                                                        <span class="credit-toggle-copy">

                                                            <strong>Always Require Investigation</strong>

                                                            <small>Step 1. Turn this on when every application must wait for a completed investigation before it can continue.</small>

                                                        </span>

                                                        <input type="checkbox" id="cp-require-ci" name="cp_require_ci" value="1" <?php echo !empty($credit_policy['ci_rules']['require_ci']) ? 'checked' : ''; ?>>

                                                    </label>

                                                </div>

                                                <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                    <label for="cp-ci-required-above-amount">Require Investigation Above Amount</label>

                                                    <div class="credit-input-with-prefix">

                                                        <span class="credit-input-prefix">&#8369;</span>

                                                        <input type="number" class="form-control" id="cp-ci-required-above-amount" name="cp_ci_required_above_amount" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($credit_policy['ci_rules']['ci_required_above_amount'] ?? 0)); ?>">

                                                    </div>

                                                    <p class="credit-policy-field-hint">Step 1 alternative. Requests above this amount must have a completed investigation before they can continue.</p>

                                                </div>

                                            </div>



                                            <div class="credit-policy-note-card" style="margin-top: 16px;">

                                                <strong>How This Flow Works</strong>

                                                <ol>

                                                    <li>If an investigation is required and none is completed yet, the application moves to <code>Pending Review</code>.</li>

                                                    <li><code>Not Recommended</code> always rejects the application. It is not controlled by the checkboxes below.</li>

                                                    <li>Anything checked under <code>Review Investigation Results</code> always moves to <code>Pending Review</code>.</li>

                                                    <li>Anything checked under <code>Auto-Approve Investigation Results</code> may continue through automatic approval checks if the other rules also pass.</li>

                                                    <li>If a completed result is left unchecked in both lists, it is treated as review, not automatic approval.</li>

                                                </ol>

                                            </div>



                                            <?php

                                            $credit_policy_ci_auto_copy = [

                                                'Highly Recommended' => 'Check this if a Highly Recommended result is allowed to continue through automatic approval checks.',

                                                'Recommended' => 'Check this if a Recommended result is allowed to continue through automatic approval checks.',

                                                'Conditional' => 'Check this if a Conditional result is still allowed to continue automatically instead of being stopped for review.',

                                            ];

                                            $credit_policy_ci_review_copy = [

                                                'Highly Recommended' => 'Check this if a Highly Recommended result should still stop and go to Pending Review for staff handling.',

                                                'Recommended' => 'Check this if a Recommended result should stop and go to Pending Review for staff handling.',

                                                'Conditional' => 'Check this if a Conditional result should stop and go to Pending Review for a manual decision.',

                                            ];

                                            ?>

                                            <div class="credit-policy-ci-columns" style="margin-top: 16px;">

                                                <div class="form-group credit-policy-ci-column" style="margin-bottom: 0;">

                                                    <div class="credit-policy-subhead">

                                                        <strong>Step 2. Let These Results Continue Automatically</strong>

                                                        <span class="text-muted" id="credit-policy-auto-ci-count-text"><?php echo (int)$credit_policy_auto_ci_count; ?> selected</span>

                                                    </div>

                                                    <p class="credit-policy-field-hint" style="margin-bottom: 10px;">Check the completed investigation results that may stay eligible for automatic approval when the rest of the policy still passes.</p>

                                                    <div class="credit-policy-table-map">

                                                        <?php foreach ($credit_policy_ci_configurable_options as $ci_option): ?>

                                                            <label class="credit-policy-table-row credit-policy-ci-row">

                                                                <span class="credit-policy-ci-row-copy">

                                                                    <strong><?php echo htmlspecialchars($ci_option); ?></strong>

                                                                    <span><?php echo htmlspecialchars($credit_policy_ci_auto_copy[$ci_option] ?? 'Allow this investigation outcome to remain approval-eligible when the rest of the policy still passes.'); ?></span>

                                                                </span>

                                                                <input type="checkbox" name="cp_auto_approve_ci_values[]" value="<?php echo htmlspecialchars($ci_option); ?>" <?php echo in_array($ci_option, $credit_policy_auto_ci_values, true) ? 'checked' : ''; ?>>

                                                            </label>

                                                        <?php endforeach; ?>

                                                    </div>

                                                </div>



                                                <div class="form-group credit-policy-ci-column" style="margin-bottom: 0;">

                                                    <div class="credit-policy-subhead">

                                                        <strong>Step 3. Send These Results To Pending Review</strong>

                                                        <span class="text-muted" id="credit-policy-review-ci-count-text"><?php echo (int)$credit_policy_review_ci_count; ?> selected</span>

                                                    </div>

                                                    <p class="credit-policy-field-hint" style="margin-bottom: 10px;">Check the completed investigation results that should always stop automatic progress and move to <code>Pending Review</code>.</p>

                                                    <div class="credit-policy-table-map">

                                                        <?php foreach ($credit_policy_ci_configurable_options as $ci_option): ?>

                                                            <label class="credit-policy-table-row credit-policy-ci-row">

                                                                <span class="credit-policy-ci-row-copy">

                                                                    <strong><?php echo htmlspecialchars($ci_option); ?></strong>

                                                                    <span><?php echo htmlspecialchars($credit_policy_ci_review_copy[$ci_option] ?? 'Move this investigation outcome to manual review so staff can decide the next step.'); ?></span>

                                                                </span>

                                                                <input type="checkbox" name="cp_review_ci_values[]" value="<?php echo htmlspecialchars($ci_option); ?>" <?php echo in_array($ci_option, $credit_policy_review_ci_values, true) ? 'checked' : ''; ?>>

                                                            </label>

                                                        <?php endforeach; ?>

                                                    </div>

                                                </div>

                                            </div>



                                            <div class="credit-policy-table-row credit-policy-static-rule">

                                                <span class="credit-policy-ci-row-copy">

                                                    <strong>Fixed Rule: Not Recommended</strong>

                                                    <span>A <code>Not Recommended</code> investigation result always rejects the application. It is not controlled by the review or automatic-approval checkboxes above.</span>

                                                </span>

                                                <span class="credit-policy-static-badge">Always Reject</span>

                                            </div>

                                        </div>



                                    </div>



                                    <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="limit" <?php echo $credit_policy_subtab === 'limit' ? '' : 'hidden'; ?>>

                                        <div class="credit-engine-panel credit-engine-panel-span-2 credit-policy-panel" id="credit-policy-limit">

                                            <div class="credit-engine-panel-head credit-policy-panel-headline">

                                                <div class="credit-policy-head-main">

                                                    <div class="credit-policy-head-icon"><span class="material-symbols-rounded">payments</span></div>

                                                    <div class="credit-policy-panel-title">

                                                        <span class="credit-policy-section-step">Section 3</span>

                                                        <h4>Credit Limit Rules</h4>

                                                        <p class="text-muted">Estimate the offer using income and the score-class band multiplier before cap and rounding. Backend checks can still refine the final offer.</p>

                                                    </div>

                                                </div>

                                                <div class="credit-policy-section-meta">

                                                    <span class="badge badge-gray">Automatic estimate</span>

                                                </div>

                                            </div>



                                            <div class="credit-policy-limit-shell">

                                                <div class="credit-policy-note-card">

                                                    <strong>How the offer is estimated</strong>

                                                    <ul>

                                                        <li><span id="credit-policy-formula-preview">Start with monthly income x <?php echo htmlspecialchars((string)($credit_policy['credit_limit']['income_multiplier'] ?? 0)); ?> x classification multiplier</span></li>

                                                        <li>Keep the final estimate within <span id="credit-policy-formula-cap"><?php echo ((float)($credit_policy['credit_limit']['max_credit_limit_cap'] ?? 0) > 0) ? '&#8369;' . number_format((float)($credit_policy['credit_limit']['max_credit_limit_cap'] ?? 0), 2) : 'No cap'; ?></span>.</li>

                                                        <li>Round the result down to the nearest <span id="credit-policy-formula-round"><?php echo ((float)($credit_policy['credit_limit']['round_to_nearest'] ?? 0) > 0) ? '&#8369;' . number_format((float)($credit_policy['credit_limit']['round_to_nearest'] ?? 0), 2) : 'No rounding'; ?></span>.</li>

                                                    </ul>

                                                </div>



                                                <div class="credit-policy-limit-groups">

                                                    <div class="credit-policy-subpanel">

                                                        <div class="credit-policy-subpanel-header">

                                                            <div>

                                                                <strong>Core Estimate Inputs</strong>

                                                                <p>These values drive the main formula before the app checks the offer limits and rounding rules.</p>

                                                            </div>

                                                            <span class="badge badge-blue">Core math</span>

                                                        </div>

                                                        <div class="credit-policy-config-grid">

                                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                                <label for="cp-income-multiplier">Income Multiplier</label>

                                                                <input type="number" class="form-control" id="cp-income-multiplier" name="cp_income_multiplier" min="0" step="0.01" value="<?php echo ((float)($credit_policy['credit_limit']['income_multiplier'] ?? 0) <= 0) ? '1.5' : htmlspecialchars((string)$credit_policy['credit_limit']['income_multiplier']); ?>">

                                                                <p class="credit-policy-field-hint">Base multiplier applied to monthly income before the classification multiplier, cap, and rounding.</p>

                                                            </div>

                                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                                <label for="cp-approve-band-multiplier">Good / High Band Multiplier</label>

                                                                <input type="number" class="form-control" id="cp-approve-band-multiplier" name="cp_approve_band_multiplier" min="0" step="0.01" value="<?php echo ((float)($credit_policy['credit_limit']['approve_band_multiplier'] ?? 0) <= 0) ? '1.10' : htmlspecialchars((string)$credit_policy['credit_limit']['approve_band_multiplier']); ?>">

                                                                <p class="credit-policy-field-hint">Applied when the score classification is <code>Good Credit Score</code> or <code>High Credit Score</code>.</p>

                                                            </div>

                                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                                <label for="cp-review-band-multiplier">Standard Band Multiplier</label>

                                                                <input type="number" class="form-control" id="cp-review-band-multiplier" name="cp_review_band_multiplier" min="0" step="0.01" value="<?php echo ((float)($credit_policy['credit_limit']['review_band_multiplier'] ?? 0) <= 0) ? '1.00' : htmlspecialchars((string)$credit_policy['credit_limit']['review_band_multiplier']); ?>">

                                                                <p class="credit-policy-field-hint">Applied when the score classification is <code>Standard Credit Score</code>.</p>

                                                            </div>

                                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                                <label for="cp-fair-band-multiplier">Fair Band Multiplier</label>

                                                                <input type="number" class="form-control" id="cp-fair-band-multiplier" name="cp_fair_band_multiplier" min="0" step="0.01" value="<?php echo ((float)($credit_policy['credit_limit']['fair_band_multiplier'] ?? 0) <= 0) ? '0.75' : htmlspecialchars((string)$credit_policy['credit_limit']['fair_band_multiplier']); ?>">

                                                                <p class="credit-policy-field-hint">Applied when the score classification is <code>Fair Credit Score</code>.</p>

                                                            </div>

                                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                                <label for="cp-at-risk-band-multiplier">At-Risk Band Multiplier</label>

                                                                <input type="number" class="form-control" id="cp-at-risk-band-multiplier" name="cp_at_risk_band_multiplier" min="0" step="0.01" value="<?php echo ((float)($credit_policy['credit_limit']['at_risk_band_multiplier'] ?? 0) <= 0) ? '0.50' : htmlspecialchars((string)$credit_policy['credit_limit']['at_risk_band_multiplier']); ?>">

                                                                <p class="credit-policy-field-hint">Applied when the score classification is <code>At-Risk Credit Score</code>.</p>

                                                            </div>

                                                        </div>

                                                    </div>



                                                    <div class="credit-policy-subpanel">

                                                        <div class="credit-policy-subpanel-header">

                                                            <div>

                                                                <strong>Limit Cap &amp; Rounding</strong>

                                                                <p>Control the top end and cleanup of the estimated limit.</p>

                                                            </div>

                                                            <span class="badge badge-gray">Boundaries</span>

                                                        </div>

                                                        <div class="credit-policy-config-grid" style="grid-template-columns: 1fr;">

                                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                                <label for="cp-max-credit-limit-cap">Maximum Estimated Limit</label>

                                                                <div class="credit-input-with-prefix">

                                                                    <span class="credit-input-prefix">&#8369;</span>

                                                                    <input type="number" class="form-control" id="cp-max-credit-limit-cap" name="cp_max_credit_limit_cap" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($credit_policy['credit_limit']['max_credit_limit_cap'] ?? 0)); ?>">

                                                                </div>

                                                                <p class="credit-policy-field-hint">If set, the estimated limit will not go above this amount.</p>

                                                            </div>

                                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">

                                                                <label for="cp-round-to-nearest">Round To Nearest</label>

                                                                <div class="credit-input-with-prefix">

                                                                    <span class="credit-input-prefix">&#8369;</span>

                                                                    <input type="number" class="form-control" id="cp-round-to-nearest" name="cp_round_to_nearest" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($credit_policy['credit_limit']['round_to_nearest'] ?? 0)); ?>">

                                                                </div>

                                                                <p class="credit-policy-field-hint">Use values like <code>500</code> or <code>1000</code> to round the estimated limit into cleaner numbers.</p>

                                                            </div>

                                                        </div>

                                                        <div class="credit-policy-note-card">

                                                            <strong>How these limits work</strong>

                                                            <ul>

                                                                <li>Leave the cap at <code>0</code> if you do not want the estimate clipped.</li>

                                                                <li>Rounding only cleans the displayed limit. It does not change the borrower score class.</li>

                                                            </ul>

                                                        </div>

                                                    </div>

                                                </div>



                                                <div class="credit-policy-subpanel">

                                                    <div class="credit-policy-subpanel-header">

                                                        <div>

                                                            <strong>Limit Engine Calculator</strong>

                                                            <p>Test the estimated limit only. This updates live from the current form, even before saving.</p>

                                                        </div>

                                                        <span class="badge badge-gray">Math only</span>

                                                    </div>



                                                    <div class="credit-policy-limit-calculator-grid">

                                                        <div class="form-group">

                                                            <label for="credit-policy-limit-calc-scenario">Scenario</label>

                                                            <select class="form-control" id="credit-policy-limit-calc-scenario">
                                                                <option value="custom">Custom Scenario</option>
                                                                <?php foreach ($credit_policy_limit_calc_scenarios as $scenario_key => $scenario): ?>
                                                                    <option value="<?php echo htmlspecialchars($scenario_key); ?>" <?php echo $scenario_key === $credit_policy_limit_calc_default_scenario_key ? 'selected' : ''; ?>><?php echo htmlspecialchars($scenario['label']); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>

                                                        </div>

                                                        <div class="form-group">

                                                            <label for="credit-policy-limit-calc-income">Monthly Income</label>

                                                            <div class="credit-input-with-prefix">

                                                                <span class="credit-input-prefix">&#8369;</span>

                                                                <input type="number" class="form-control" id="credit-policy-limit-calc-income" value="<?php echo htmlspecialchars((string)$credit_policy_limit_calc_income_default); ?>" min="0" step="0.01">

                                                            </div>

                                                        </div>

                                                        <div class="form-group">

                                                            <label for="credit-policy-limit-calc-score">Credit Score</label>

                                                            <input type="number" class="form-control" id="credit-policy-limit-calc-score" value="<?php echo htmlspecialchars((string)$credit_policy_limit_calc_score_default); ?>" min="0" step="1">

                                                        </div>

                                                    </div>



                                                    <div class="credit-policy-simulator-metrics">

                                                        <div class="credit-policy-output-card">

                                                            <span>Scenario</span>

                                                            <strong id="credit-policy-limit-calc-scenario-label"><?php echo htmlspecialchars((string)$credit_policy_limit_calc_default_scenario['label']); ?></strong>

                                                        </div>

                                                        <div class="credit-policy-output-card">

                                                            <span>Score Class</span>

                                                            <strong id="credit-policy-limit-calc-class"><?php echo htmlspecialchars((string)($credit_policy_limit_calc_recommendation['label'] ?? 'Good Credit Score')); ?></strong>

                                                        </div>

                                                        <div class="credit-policy-output-card">

                                                            <span>Initial Multiplier</span>

                                                            <strong id="credit-policy-limit-calc-initial-multiplier"><?php echo htmlspecialchars(number_format((float)$credit_policy_limit_income_multiplier_display, 2, '.', '')); ?>x</strong>

                                                        </div>

                                                        <div class="credit-policy-output-card">

                                                            <span>Applied Band Multiplier</span>

                                                            <strong id="credit-policy-limit-calc-multiplier"><?php echo htmlspecialchars(number_format((float)$credit_policy_limit_calc_multiplier, 2, '.', '')); ?>x</strong>

                                                        </div>

                                                        <div class="credit-policy-output-card">

                                                            <span>Estimated Limit</span>

                                                            <strong id="credit-policy-limit-calc-limit"><?php echo '&#8369;' . number_format((float)$credit_policy_limit_calc_limit, 2); ?></strong>

                                                        </div>

                                                    </div>



                                                    <div class="credit-policy-simulator-formula">

                                                        <span>Limit Formula</span>

                                                        <strong id="credit-policy-limit-calc-formula">Income x <?php echo htmlspecialchars(number_format((float)$credit_policy_limit_income_multiplier_display, 2, '.', '')); ?> x <?php echo htmlspecialchars((string)($credit_policy_limit_calc_recommendation['label'] ?? 'Good Credit Score')); ?> multiplier <?php echo htmlspecialchars(number_format((float)$credit_policy_limit_calc_multiplier, 2, '.', '')); ?></strong>

                                                    </div>



                                                    <div class="credit-policy-note" id="credit-policy-limit-calc-note">Visual guide only. Scenario: <?php echo htmlspecialchars((string)$credit_policy_limit_calc_default_scenario['label']); ?>. This calculator updates from the current unsaved limit settings and does not check borrower eligibility.</div>

                                                </div>

                                            </div>

                                        </div>



                                    </div>



                                    <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="product" hidden>

                                        <div class="credit-engine-panel credit-policy-panel" id="credit-policy-product">

                                            <div class="credit-engine-panel-head credit-policy-panel-headline">

                                                <div class="credit-policy-head-main">

                                                    <div class="credit-policy-head-icon"><span class="material-symbols-rounded">inventory_2</span></div>

                                                    <div class="credit-policy-panel-title">

                                                        <span class="credit-policy-section-step">Section 5</span>

                                                        <h4>Product Checks</h4>

                                                        <p class="text-muted">Use product guardrails to keep the final offer within the selected loan option.</p>

                                                    </div>

                                                </div>

                                                <div class="credit-policy-section-meta">

                                                    <span class="badge badge-gray">Product guardrails</span>

                                                </div>

                                            </div>



                                            <div class="credit-engine-inline-grid">

                                                <div class="form-group" style="margin-bottom: 0;">

                                                    <input type="hidden" name="cp_use_product_minimum_credit_score" value="0">

                                                    <label class="credit-toggle-row" for="cp-use-product-min-score">

                                                        <span class="credit-toggle-copy">

                                                            <strong>Use Product Minimum Credit Score</strong>

                                                            <small>Reject when the borrower is below the product score floor.</small>

                                                        </span>

                                                        <input type="checkbox" id="cp-use-product-min-score" name="cp_use_product_minimum_credit_score" value="1" <?php echo !empty($credit_policy['product_checks']['use_product_minimum_credit_score']) ? 'checked' : ''; ?>>

                                                    </label>

                                                </div>

                                                <div class="form-group" style="margin-bottom: 0;">

                                                    <input type="hidden" name="cp_use_product_min_amount" value="0">

                                                    <label class="credit-toggle-row" for="cp-use-product-min-amount">

                                                        <span class="credit-toggle-copy">

                                                            <strong>Use Product Minimum Amount</strong>

                                                            <small>Reject when the request or estimated offer falls below the product minimum.</small>

                                                        </span>

                                                        <input type="checkbox" id="cp-use-product-min-amount" name="cp_use_product_min_amount" value="1" <?php echo !empty($credit_policy['product_checks']['use_product_min_amount']) ? 'checked' : ''; ?>>

                                                    </label>

                                                </div>

                                                <div class="form-group" style="margin-bottom: 0;">

                                                    <input type="hidden" name="cp_use_product_max_amount" value="0">

                                                    <label class="credit-toggle-row" for="cp-use-product-max-amount">

                                                        <span class="credit-toggle-copy">

                                                            <strong>Use Product Maximum Amount</strong>

                                                            <small>Reject when the request exceeds the product maximum.</small>

                                                        </span>

                                                        <input type="checkbox" id="cp-use-product-max-amount" name="cp_use_product_max_amount" value="1" <?php echo !empty($credit_policy['product_checks']['use_product_max_amount']) ? 'checked' : ''; ?>>

                                                    </label>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>



                                <div class="credit-policy-action-bar">

                                    <div class="credit-policy-action-copy">

                                        <strong>Save when the flow feels right</strong>

                                        <span>This policy controls automatic limit estimates and application decisions for your app.</span>

                                    </div>

                                    <div class="credit-policy-primary-actions">

                                        <button type="button" class="btn btn-outline" id="credit-policy-reset-trigger-bottom">

                                            <span class="material-symbols-rounded">restart_alt</span>

                                            Reset to Defaults

                                        </button>

                                        <button type="submit" class="btn btn-primary">

                                            <span class="material-symbols-rounded">save</span>

                                            Save Credit Policy

                                        </button>

                                    </div>

                                </div>

                            </form>

                        </div>

                    </div>

                </section>
