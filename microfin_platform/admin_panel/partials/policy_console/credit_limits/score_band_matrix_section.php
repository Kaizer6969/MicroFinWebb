<section class="policy-blueprint-card" id="policy-credit-limits-bands">
    <div class="policy-blueprint-card-head">
        <div class="policy-blueprint-card-title">
            <h4>Score Band Matrix</h4>
            <p class="text-muted">Define score bands and the growth settings used after onboarding when credit limit movement becomes score-based.</p>
        </div>
        <div class="policy-blueprint-card-actions" style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px; width: 100%; max-width: 220px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; width: 100%;">
                <button type="button" class="btn btn-outline" id="policy-score-band-cancel-btn" style="display: none; text-align: center; justify-content: center; padding: 10px 14px;">Cancel</button>
                <button type="button" class="btn btn-outline" id="policy-score-band-customize-btn" style="grid-column: 2; text-align: center; justify-content: center; padding: 10px 14px; margin-right: 0;">Customize</button>
            </div>
            <button type="button" class="btn btn-outline" data-policy-score-band-add style="display: none; justify-content: center; width: 100%; padding: 10px 14px;">
                <span class="material-symbols-rounded" style="margin-right: 6px;">add</span>
                Add Score Band
            </button>
        </div>
    </div>

    <div class="policy-blueprint-panel">
        <div class="policy-blueprint-panel-head">
            <div>
                <span class="policy-blueprint-panel-kicker">Band Setup</span>
                <h5>Ordered score bands</h5>
                <p class="text-muted">Growth percentage per cycle uses: Base Growth + ((Current CS - Band Min) × Micro % per point).</p>
            </div>
        </div>

        <div class="policy-band-table-wrap" data-policy-score-band-wrap data-next-index="<?php echo count($policy_console_score_band_rows); ?>">
            <table class="policy-band-table" id="policy-score-band-table">
                <thead>
                    <tr>
                        <th>Band <?php echo $policy_console_help('Display label for the score band.'); ?></th>
                        <th>Min <?php echo $policy_console_help('Lowest score included in the band.'); ?></th>
                        <th>Max <?php echo $policy_console_help('Highest score included in the band.'); ?></th>
                        <th>Base Growth % <?php echo $policy_console_help('Default per-cycle growth rate before score-position sensitivity is added.'); ?></th>
                        <th>Micro % / Point <?php echo $policy_console_help('Additional growth sensitivity applied for every point above the band minimum.'); ?></th>
                        <th class="policy-band-col-actions" style="display: none;"></th>
                    </tr>
                </thead>
                <tbody data-policy-score-band-body>
                    <?php foreach ($policy_console_score_band_rows as $policy_console_row): ?>
                        <tr class="policy-band-row" data-policy-score-band-row data-policy-row-index="<?php echo $policy_console_row_index; ?>">
                            <td>
                                <input type="hidden" name="pcc_score_band_id[]" value="<?php echo htmlspecialchars((string)($policy_console_row['id'] ?? ('band_' . ($policy_console_row_index + 1)))); ?>">
                                <input type="text" class="form-control" name="pcc_score_band_label[]" value="<?php echo htmlspecialchars((string)($policy_console_row['label'] ?? '')); ?>" maxlength="60" required readonly>
                            </td>
                            <td><input type="number" class="form-control" name="pcc_score_band_min[]" min="0" max="<?php echo (int)$credit_policy_score_ceiling; ?>" value="<?php echo htmlspecialchars((string)($policy_console_row['min_score'] ?? 0)); ?>" required readonly></td>
                            <td><input type="number" class="form-control" name="pcc_score_band_max[]" min="0" max="<?php echo (int)$credit_policy_score_ceiling; ?>" value="<?php echo htmlspecialchars((string)($policy_console_row['max_score'] ?? 0)); ?>" required readonly></td>
                            <td><input type="number" class="form-control" name="pcc_score_band_base_growth[]" min="0" max="100" step="0.001" value="<?php echo htmlspecialchars((string)($policy_console_row['base_growth_percent'] ?? 0)); ?>" required readonly></td>
                            <td><input type="number" class="form-control" name="pcc_score_band_micro_growth[]" min="0" max="10" step="0.001" value="<?php echo htmlspecialchars((string)($policy_console_row['micro_percent_per_point'] ?? 0)); ?>" required readonly></td>
                            <td class="policy-band-actions policy-band-col-actions" style="display: none;">
                                <button type="button" class="btn btn-ghost-danger" data-policy-score-band-delete aria-label="Delete score band">
                                    <span class="material-symbols-rounded">close</span>
                                </button>
                            </td>
                        </tr>
                        <?php $policy_console_row_index++; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="policy-empty-note" data-policy-score-band-empty <?php echo count($policy_console_score_band_rows) > 0 ? 'hidden' : ''; ?>>No score bands added yet.</p>
        </div>

        <div class="policy-blueprint-note">
            <strong>Onboarding vs growth</strong>
            <span>Initial credit assignment uses the onboarding income percentage once. After that, these score-band growth settings take over for future limit movement.</span>
        </div>
    </div>
</section>
