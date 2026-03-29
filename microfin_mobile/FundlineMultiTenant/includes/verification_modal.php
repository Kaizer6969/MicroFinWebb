<!-- Document Verification Modal -->
<div id="verificationModal" class="modal-overlay">
    <div class="modal-card-large">
        <div class="modal-header">
            <h3 class="heading-3 text-main">Complete Your Profile & Submit Documents</h3>
            <button type="button" class="modal-close" onclick="closeVerificationModal()">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step-item active" id="step1-indicator">
                <div class="step-number">1</div>
                <div class="step-label">Profile</div>
            </div>
            <div class="step-divider"></div>
            <div class="step-item" id="step2-indicator">
                <div class="step-number">2</div>
                <div class="step-label">Documents</div>
            </div>
        </div>
        
        <form id="verificationForm" enctype="multipart/form-data">
            <!-- Step 1: Complete Profile Information -->
            <div class="verification-step active" id="step1">
                <h4 class="heading-4 text-main mb-4">Complete Your Profile</h4>
                <p class="body-small text-muted mb-4">Please fill in all required information</p>
                
                <!-- Personal Information -->
                <div style="margin-bottom: 2rem;">
                    <h5 style="font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <span class="material-symbols-outlined">person</span>
                        Personal Information
                    </h5>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-input" value="<?php echo htmlspecialchars($client_data['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middle_name" class="form-input" value="<?php echo htmlspecialchars($client_data['middle_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-input" value="<?php echo htmlspecialchars($client_data['last_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Suffix</label>
                            <select name="suffix" class="form-input">
                                <option value="">Select Suffix</option>
                                <option value="Jr." <?php echo ($client_data['suffix'] ?? '') == 'Jr.' ? 'selected' : ''; ?>>Jr.</option>
                                <option value="Sr." <?php echo ($client_data['suffix'] ?? '') == 'Sr.' ? 'selected' : ''; ?>>Sr.</option>
                                <option value="II" <?php echo ($client_data['suffix'] ?? '') == 'II' ? 'selected' : ''; ?>>II</option>
                                <option value="III" <?php echo ($client_data['suffix'] ?? '') == 'III' ? 'selected' : ''; ?>>III</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth *</label>
                            <input type="date" name="date_of_birth" class="form-input" value="<?php echo htmlspecialchars($client_data['date_of_birth'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Gender *</label>
                            <select name="gender" class="form-input" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo ($client_data['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($client_data['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Civil Status *</label>
                            <select name="civil_status" class="form-input" required>
                                <option value="">Select Status</option>
                                <option value="Single" <?php echo ($client_data['civil_status'] ?? '') == 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo ($client_data['civil_status'] ?? '') == 'Married' ? 'selected' : ''; ?>>Married</option>
                                <option value="Widowed" <?php echo ($client_data['civil_status'] ?? '') == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div style="margin-bottom: 2rem;">
                    <h5 style="font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <span class="material-symbols-outlined">phone</span>
                        Contact Information
                    </h5>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Contact Number *</label>
                            <input type="tel" name="contact_number" class="form-input" value="<?php echo htmlspecialchars($client_data['contact_number'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Alternate Contact</label>
                            <input type="tel" name="alternate_contact" class="form-input" value="<?php echo htmlspecialchars($client_data['alternate_contact'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Present Address -->
                <div style="margin-bottom: 2rem;">
                    <h5 style="font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <span class="material-symbols-outlined">home</span>
                        Present Address
                    </h5>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">House/Unit No. *</label>
                            <input type="text" name="present_house_no" class="form-input" value="<?php echo htmlspecialchars($client_data['present_house_no'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Street *</label>
                            <input type="text" name="present_street" class="form-input" value="<?php echo htmlspecialchars($client_data['present_street'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Barangay *</label>
                            <input type="text" name="present_barangay" class="form-input" value="<?php echo htmlspecialchars($client_data['present_barangay'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">City *</label>
                            <input type="text" name="present_city" class="form-input" value="<?php echo htmlspecialchars($client_data['present_city'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Province *</label>
                            <input type="text" name="present_province" class="form-input" value="<?php echo htmlspecialchars($client_data['present_province'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Postal Code *</label>
                            <input type="text" name="present_postal_code" class="form-input" value="<?php echo htmlspecialchars($client_data['present_postal_code'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>
                
                <!-- Employment Information -->
                <div style="margin-bottom: 2rem;">
                    <h5 style="font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <span class="material-symbols-outlined">work</span>
                        Employment Information
                    </h5>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Employment Status *</label>
                            <select name="employment_status" class="form-input" required>
                                <option value="">Select Status</option>
                                <option value="Employed" <?php echo ($client_data['employment_status'] ?? '') == 'Employed' ? 'selected' : ''; ?>>Employed</option>
                                <option value="Self-Employed" <?php echo ($client_data['employment_status'] ?? '') == 'Self-Employed' ? 'selected' : ''; ?>>Self-Employed</option>
                                <option value="Unemployed" <?php echo ($client_data['employment_status'] ?? '') == 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Employer Name</label>
                            <input type="text" name="employer_name" class="form-input" value="<?php echo htmlspecialchars($client_data['employer_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Occupation</label>
                            <input type="text" name="occupation" class="form-input" value="<?php echo htmlspecialchars($client_data['occupation'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Monthly Income *</label>
                            <input type="number" name="monthly_income" class="form-input" value="<?php echo htmlspecialchars($client_data['monthly_income'] ?? ''); ?>" step="0.01" required>
                        </div>
                    </div>
                </div>
                
                <!-- Co-Maker Information -->
                <div style="margin-bottom: 2rem;">
                    <h5 style="font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <span class="material-symbols-outlined">group</span>
                        Co-Maker Information
                    </h5>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Co-Maker Name *</label>
                            <input type="text" name="comaker_name" class="form-input" value="<?php echo htmlspecialchars($client_data['comaker_name'] ?? ''); ?>" required placeholder="Full Name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Relationship *</label>
                            <select name="comaker_relationship" class="form-input" required>
                                <option value="">Select Relationship</option>
                                <option value="Spouse" <?php echo ($client_data['comaker_relationship'] ?? '') == 'Spouse' ? 'selected' : ''; ?>>Spouse</option>
                                <option value="Parent" <?php echo ($client_data['comaker_relationship'] ?? '') == 'Parent' ? 'selected' : ''; ?>>Parent</option>
                                <option value="Sibling" <?php echo ($client_data['comaker_relationship'] ?? '') == 'Sibling' ? 'selected' : ''; ?>>Sibling</option>
                                <option value="Relative" <?php echo ($client_data['comaker_relationship'] ?? '') == 'Relative' ? 'selected' : ''; ?>>Relative</option>
                                <option value="Colleague" <?php echo ($client_data['comaker_relationship'] ?? '') == 'Colleague' ? 'selected' : ''; ?>>Colleague</option>
                                <option value="Friend" <?php echo ($client_data['comaker_relationship'] ?? '') == 'Friend' ? 'selected' : ''; ?>>Friend</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number *</label>
                            <input type="tel" name="comaker_contact" class="form-input" value="<?php echo htmlspecialchars($client_data['comaker_contact'] ?? ''); ?>" required placeholder="09xxxxxxxxx">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Monthly Income</label>
                            <input type="number" name="comaker_income" class="form-input" value="<?php echo htmlspecialchars($client_data['comaker_income'] ?? ''); ?>" step="0.01">
                        </div>
                        
                        <div class="form-group" style="grid-column: 1 / -1; margin-top: 0.5rem;">
                             <label class="form-label small text-muted text-uppercase fw-bold">Co-Maker Address</label>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">House/Unit No.</label>
                            <input type="text" name="comaker_house_no" class="form-input" value="<?php echo htmlspecialchars($client_data['comaker_house_no'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Street</label>
                            <input type="text" name="comaker_street" class="form-input" value="<?php echo htmlspecialchars($client_data['comaker_street'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Province</label>
                             <select name="comaker_province" id="comaker_province" class="form-input" data-current="<?php echo htmlspecialchars($client_data['comaker_province'] ?? ''); ?>">
                                <option value="">Select Province</option>
                                <?php if(!empty($client_data['comaker_province'])): ?>
                                    <option value="<?php echo htmlspecialchars($client_data['comaker_province']); ?>" selected><?php echo htmlspecialchars($client_data['comaker_province']); ?></option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">City</label>
                             <select name="comaker_city" id="comaker_city" class="form-input" data-current="<?php echo htmlspecialchars($client_data['comaker_city'] ?? ''); ?>">
                                <option value="">Select City</option>
                                <?php if(!empty($client_data['comaker_city'])): ?>
                                    <option value="<?php echo htmlspecialchars($client_data['comaker_city']); ?>" selected><?php echo htmlspecialchars($client_data['comaker_city']); ?></option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Barangay</label>
                             <select name="comaker_barangay" id="comaker_barangay" class="form-input" data-current="<?php echo htmlspecialchars($client_data['comaker_barangay'] ?? ''); ?>">
                                <option value="">Select Barangay</option>
                                <?php if(!empty($client_data['comaker_barangay'])): ?>
                                    <option value="<?php echo htmlspecialchars($client_data['comaker_barangay']); ?>" selected><?php echo htmlspecialchars($client_data['comaker_barangay']); ?></option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Postal Code</label>
                            <input type="text" name="comaker_postal_code" class="form-input" value="<?php echo htmlspecialchars($client_data['comaker_postal_code'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary btn-lg" onclick="goToStep2()">
                        <span>Next: Upload Documents</span>
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </button>
                </div>
            </div>
            
            <!-- Step 2: Document Upload -->
            <div class="verification-step" id="step2">
                <h4 class="heading-4 text-main mb-4">Submit Required Documents</h4>
                <p class="body-small text-muted mb-4">Please upload the following documents for verification</p>
                
                <div class="document-upload-list">
                    <div class="document-upload-item">
                        <label class="form-label">
                            <span class="material-symbols-outlined" style="color: var(--color-primary);">description</span>
                            Proof of Income *
                        </label>
                        <p class="caption text-muted mb-2">Upload payslip, ITR, certificate of employment, etc.</p>
                        <input type="file" name="proof_of_income" class="form-input" accept=".pdf,.jpg,.jpeg,.png" required>
                    </div>
                    
                    <div class="document-upload-item">
                        <label class="form-label">
                            <span class="material-symbols-outlined" style="color: var(--color-primary);">home</span>
                            Proof of Address *
                        </label>
                        <p class="caption text-muted mb-2">Upload utility bill, barangay certificate, etc.</p>
                        <input type="file" name="proof_of_address" class="form-input" accept=".pdf,.jpg,.jpeg,.png" required>
                    </div>
                    
                    <div class="document-upload-item">
                        <label class="form-label">
                            <span class="material-symbols-outlined" style="color: var(--color-primary);">badge</span>
                            Valid ID *
                        </label>
                        <p class="caption text-muted mb-2">Upload a valid government-issued ID</p>
                        <input type="file" name="valid_id" class="form-input" accept=".pdf,.jpg,.jpeg,.png" required>
                    </div>
                </div>
                
                <div class="modal-footer" style="gap: 1rem;">
                    <button type="button" class="btn btn-secondary btn-lg" onclick="goToStep1()">
                        <span class="material-symbols-outlined">arrow_back</span>
                        <span>Back</span>
                    </button>
                    <button type="submit" class="btn btn-primary btn-lg" id="submitVerificationBtn">
                        <span>Submit for Verification</span>
                        <span class="material-symbols-outlined">send</span>
                    </button>
                </div>
            </div>
        </form>
        
        <!-- Pending Status Message -->
        <div class="verification-step" id="stepPending" style="display: none;">
            <div style="text-align: center; padding: 2rem;">
                <div style="width: 5rem; height: 5rem; background-color: rgba(245, 158, 11, 0.1); color: #f59e0b; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                    <span class="material-symbols-outlined" style="font-size: 3rem;">pending</span>
                </div>
                <h4 class="heading-4 text-main mb-2">Documents Under Review</h4>
                <p class="body-medium text-muted mb-4">Your documents have been submitted and are currently being reviewed by our admin team. You will be notified once approved.</p>
                <button type="button" class="btn btn-secondary btn-lg" onclick="window.location.href='dashboard.php'">
                    <span>Return to Dashboard</span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.modal-card-large {
    background-color: var(--color-surface-light);
    border-radius: var(--radius-2xl);
    padding: 2rem;
    max-width: 900px;
    width: 95%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: var(--shadow-xl);
    animation: modalSlideUp 0.3s ease-out;
}

.dark .modal-card-large {
    background-color: var(--color-surface-dark);
}

.modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--color-text-muted);
    padding: 0.5rem;
    border-radius: 50%;
    transition: all var(--transition-fast);
}

.modal-close:hover {
    background-color: var(--color-hover-light);
}

.step-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 2rem 0;
    gap: 1rem;
}

.step-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.step-number {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    background-color: var(--color-border-subtle);
    color: var(--color-text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: var(--font-weight-semibold);
    transition: all var(--transition-fast);
}

.step-item.active .step-number {
    background-color: var(--color-primary);
    color: white;
}

.step-label {
    font-size: 0.875rem;
    color: var(--color-text-muted);
}

.step-item.active .step-label {
    color: var(--color-primary);
    font-weight: var(--font-weight-semibold);
}

.step-divider {
    width: 3rem;
    height: 2px;
    background-color: var(--color-border-subtle);
}

.verification-step {
    display: none;
}

.verification-step.active {
    display: block;
}

.document-upload-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.document-upload-item {
    padding: 1.5rem;
    border: 2px dashed var(--color-border-subtle);
    border-radius: var(--radius-lg);
}

.document-upload-item .form-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: var(--font-weight-semibold);
    margin-bottom: 0.5rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-card-large {
        width: 100%;
        max-height: 100vh;
        border-radius: 0;
    }
}
</style>
<script src="../assets/js/address-selector.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize Address Selector for Co-Maker
        // Note: Present address in this modal uses different IDs (present_province etc) so we might need to init that too if not already handled
        // Based on the fields: present_province, present_city, present_barangay
        // We need to check if they have IDs. The current code doesn't show IDs for them, only names.
        // I will add IDs to them in a separate chunk if needed, but for now let's focus on Co-Maker which I added IDs to.
        
        new AddressSelector('comaker_'); 
    });
</script>

