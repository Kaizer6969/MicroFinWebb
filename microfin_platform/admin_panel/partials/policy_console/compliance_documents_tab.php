<div class="policy-console-workspace">
    <div class="policy-console-story-card policy-console-story-card-hero">
        <div class="policy-console-story-copy">
            <span class="policy-console-eyebrow">Compliance &amp; Documents</span>
            <h3>Document ownership, validity, and governance</h3>
            <p class="text-muted">This page follows the newer compliance-focused flow so document rules stay separate from credit scoring and collections behavior. It is intentionally UI-only until document-governance wiring is defined.</p>
        </div>
        <div class="policy-console-story-meta">
            <span class="policy-console-chip">Separated from credit scoring</span>
            <span class="policy-console-chip">Governance-first UI</span>
        </div>
    </div>

    <div class="policy-console-section-stack">
        <section class="policy-console-section-card">
            <div class="policy-console-section-header">
                <div>
                    <span class="policy-console-eyebrow">Section 1</span>
                    <h4>Required Document Categories</h4>
                    <p class="text-muted">Use one visible matrix to communicate what categories are mandatory before deeper workflow logic applies.</p>
                </div>
                <span class="policy-console-chip">Draft validation</span>
            </div>
            <div class="policy-console-section-body">
                <div class="policy-console-table-wrap">
                    <table class="policy-console-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Required</th>
                                <th>Accepted Types</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Identity</td>
                                <td>Yes</td>
                                <td>Government ID, Passport, Driver's License</td>
                            </tr>
                            <tr>
                                <td>Address</td>
                                <td>Yes</td>
                                <td>Utility Bill, Barangay Certificate</td>
                            </tr>
                            <tr>
                                <td>Income / Capacity</td>
                                <td>Yes</td>
                                <td>Payslip, Business Proof, Bank Statement</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="policy-console-section-card">
            <div class="policy-console-section-header">
                <div>
                    <span class="policy-console-eyebrow">Section 2</span>
                    <h4>Validity &amp; Expiration Rules</h4>
                    <p class="text-muted">Keep document validity windows visible even before the final backend renewal engine is connected.</p>
                </div>
                <span class="policy-console-chip">Draft validation</span>
            </div>
            <div class="policy-console-section-body">
                <div class="policy-console-field-grid">
                    <label class="policy-console-field">
                        <span>Validity Window (days)</span>
                        <input type="number" min="1" max="3650" value="365" required>
                        <small>UI-only value showing how freshness rules could be presented.</small>
                    </label>
                    <label class="policy-console-field">
                        <span>Renewal Reminder Lead Time</span>
                        <input type="number" min="0" max="365" value="30" required>
                        <small>How early the platform should signal renewal attention.</small>
                    </label>
                </div>
            </div>
        </section>

        <section class="policy-console-section-card">
            <div class="policy-console-section-header">
                <div>
                    <span class="policy-console-eyebrow">Section 3</span>
                    <h4>Governance Matrix</h4>
                    <p class="text-muted">This area keeps document ownership and verification responsibility centralized in one place.</p>
                </div>
                <span class="policy-console-chip">Preview only</span>
            </div>
            <div class="policy-console-section-body">
                <div class="policy-console-split-grid">
                    <div class="policy-console-card">
                        <div class="policy-console-card-head">
                            <strong>Verification ownership</strong>
                            <span class="policy-console-chip">Draft</span>
                        </div>
                        <ul class="policy-console-list">
                            <li>Prepare role ownership for verification and review.</li>
                            <li>Keep this separated from the credit-scoring workflow.</li>
                        </ul>
                    </div>
                    <div class="policy-console-card">
                        <div class="policy-console-card-head">
                            <strong>Governance note</strong>
                            <span class="policy-console-chip">Draft</span>
                        </div>
                        <p class="text-muted">This new flow is intentionally governance-first so required documents, validity, and accountability remain easier to maintain later.</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
