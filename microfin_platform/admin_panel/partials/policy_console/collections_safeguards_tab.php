<div class="policy-console-workspace">
    <div class="policy-console-story-card policy-console-story-card-hero">
        <div class="policy-console-story-copy">
            <span class="policy-console-eyebrow">Collections &amp; Safeguards</span>
            <h3>Delinquency handling, penalties, and protection rules</h3>
            <p class="text-muted">This page copies the newer modular flow for collections-related policy, but stays UI-only until the real backend contract for penalties and safeguards is intentionally wired.</p>
        </div>
        <div class="policy-console-story-meta">
            <span class="policy-console-chip">Prototype flow adapted</span>
            <span class="policy-console-chip">Backend deferred</span>
        </div>
    </div>

    <div class="policy-console-section-stack">
        <section class="policy-console-section-card">
            <div class="policy-console-section-header">
                <div>
                    <span class="policy-console-eyebrow">Section 1</span>
                    <h4>Delinquency Handling</h4>
                    <p class="text-muted">Keep the delinquency timing visible first so penalties and recovery logic build on top of it.</p>
                </div>
                <span class="policy-console-chip">Draft validation</span>
            </div>
            <div class="policy-console-section-body">
                <div class="policy-console-field-grid">
                    <label class="policy-console-field">
                        <span>Grace Period Days</span>
                        <input type="number" min="0" max="365" value="7" required>
                        <small>Example UI-only value for when a borrower becomes officially late.</small>
                    </label>
                    <label class="policy-console-field">
                        <span>Collection Fee Trigger Days</span>
                        <input type="number" min="0" max="365" value="30" required>
                        <small>UI-only trigger used to model when recovery fees would begin.</small>
                    </label>
                </div>
            </div>
        </section>

        <section class="policy-console-section-card">
            <div class="policy-console-section-header">
                <div>
                    <span class="policy-console-eyebrow">Section 2</span>
                    <h4>Penalty Rules</h4>
                    <p class="text-muted">Separate money penalties from credit score penalties so credit policy and collections policy do not blend together.</p>
                </div>
                <span class="policy-console-chip">Draft validation</span>
            </div>
            <div class="policy-console-section-body">
                <div class="policy-console-field-grid">
                    <label class="policy-console-field">
                        <span>Late Penalty Type</span>
                        <select>
                            <option selected>Fixed Amount</option>
                            <option>Percentage</option>
                        </select>
                        <small>UI-only draft choice based on the newer workspace structure.</small>
                    </label>
                    <label class="policy-console-field">
                        <span>Late Penalty Value</span>
                        <input type="number" min="0" step="0.01" value="250" required>
                        <small>Visible validation keeps this from going negative.</small>
                    </label>
                    <label class="policy-console-field">
                        <span>Penalty Cap Amount</span>
                        <input type="number" min="0" step="0.01" value="1500" required>
                        <small>UI-only ceiling for accumulated late charges.</small>
                    </label>
                </div>
            </div>
        </section>

        <section class="policy-console-section-card">
            <div class="policy-console-section-header">
                <div>
                    <span class="policy-console-eyebrow">Section 3</span>
                    <h4>Borrower Safeguards</h4>
                    <p class="text-muted">This section holds guarantor and collateral-oriented protections without dragging them into core decision routing.</p>
                </div>
                <span class="policy-console-chip">Preview only</span>
            </div>
            <div class="policy-console-section-body">
                <div class="policy-console-split-grid">
                    <div class="policy-console-card">
                        <div class="policy-console-card-head">
                            <strong>Guarantor requirement</strong>
                            <span class="policy-console-chip">Draft</span>
                        </div>
                        <ul class="policy-console-list">
                            <li>Allow a threshold for when guarantors become required.</li>
                            <li>Keep this separate from the score-based approval workflow.</li>
                        </ul>
                    </div>
                    <div class="policy-console-card">
                        <div class="policy-console-card-head">
                            <strong>Collateral guardrail</strong>
                            <span class="policy-console-chip">Draft</span>
                        </div>
                        <ul class="policy-console-list">
                            <li>Prepare the UI for higher-exposure security rules.</li>
                            <li>Leave runtime enforcement for the later backend pass.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
