<?php
// includes/loan_helper.php

/**
 * Checks and applies the 10% penalty if the loan is >= 6 months old and not fully paid.
 */
function checkAndApply6MonthPenalty($conn, $loan_id) {
    // Get loan details
    $stmt = $conn->prepare("SELECT principal_amount, release_date, outstanding_penalty, loan_status FROM loans WHERE loan_id = ?");
    if (!$stmt) return;
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $loan = $res->fetch_assoc();
    $stmt->close();

    if (!$loan) return;
    if ($loan['loan_status'] === 'Fully Paid' || $loan['loan_status'] === 'Cancelled' || $loan['loan_status'] === 'Written Off') return;

    // Check age
    $release_date = new DateTime($loan['release_date']);
    $today = new DateTime();
    $interval = $release_date->diff($today);
    // Calculate total months
    $months_elapsed = ($interval->y * 12) + $interval->m;
    // We do NOT round up days here for the penalty trigger. It triggers when 6 full months have passed.
    
    if ($months_elapsed >= 6) {
        // Check if penalty already applied using audit logs
        $check_audit = $conn->prepare("SELECT log_id FROM audit_logs WHERE entity_type = 'LOAN' AND entity_id = ? AND action_type = 'PENALTY_6_MONTHS'");
        $check_audit->bind_param("i", $loan_id);
        $check_audit->execute();
        if ($check_audit->get_result()->num_rows == 0) {
            // Apply Penalty: 10% of Principal Amount (user requirement: "10% penalty if the loan reaches 6 months")
            // Assuming this is on the Original Principal or Outstanding?
            // "10% penalty" usually refers to the loan amount or outstanding. 
            // Let's use Outstanding Principal to be fairer, but if not specified, usually implies Principal.
            // Given "still not fully paid", outstanding makes sense.
            // However, sticking to consistent business logic often favors Principal unless specified "on outstanding balance".
            // I'll use Outstanding Principal as it's friendlier and safer for "not fully paid" context.
            // Actually, in `make_payment.php` lines 104, I saw `$outstanding_principal * 0.10`. Use that.
            
            // Re-fetch outstanding_principal to be sure (it was not in first query)
            $stmt_p = $conn->prepare("SELECT outstanding_principal FROM loans WHERE loan_id = ?");
            $stmt_p->bind_param("i", $loan_id);
            $stmt_p->execute();
            $lp = $stmt_p->get_result()->fetch_assoc();
            $stmt_p->close();
            
            $penalty_amount = $lp['outstanding_principal'] * 0.10;
            
            if ($penalty_amount > 0) {
                // Update loan
                $update = $conn->prepare("UPDATE loans SET outstanding_penalty = outstanding_penalty + ? WHERE loan_id = ?");
                $update->bind_param("di", $penalty_amount, $loan_id);
                $update->execute();
                $update->close();
                
                // Log it
                $log = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, description, created_at) VALUES (NULL, 'PENALTY_6_MONTHS', 'LOAN', ?, 'Applied 10% penalty for reaching 6 months', NOW())");
                $log->bind_param("i", $loan_id);
                $log->execute();
                $log->close();
            }
        }
        $check_audit->close();
    }
}

/**
 * Calculates the early settlement amounts.
 */
function calculateEarlySettlement($loan_data) {
    // $loan_data must contain: release_date, principal_amount, outstanding_principal, outstanding_penalty, interest_paid, interest_rate
    
    $release_date = new DateTime($loan_data['release_date']);
    $today = new DateTime();
    $interval = $release_date->diff($today);
    $months_elapsed = ($interval->y * 12) + $interval->m;
    if ($interval->d > 0) $months_elapsed++; // Part of month is full month
    if ($months_elapsed < 1) $months_elapsed = 1;
    
    // Pro-rated Interest: Based on Original Principal * Monthly Rate * Months Used
    $monthly_rate = $loan_data['interest_rate'] / 100;
    $interest_due_total = $loan_data['principal_amount'] * $monthly_rate * $months_elapsed;
    
    // Deduct already paid interest
    $interest_already_paid = floatval($loan_data['interest_paid']);
    $interest_payable = max(0, $interest_due_total - $interest_already_paid);
    
    // Termination Fee: 0.06% of Outstanding Principal
    $termination_fee = floatval($loan_data['outstanding_principal']) * 0.0006;
    
    // Outstanding Penalty (includes the 6-month penalty if already applied)
    $penalty_due = floatval($loan_data['outstanding_penalty']);
    
    // Note: If 6-month penalty wasn't applied yet (e.g. called before checkAndApply), 
    // it won't be here. Ensure checkAndApply is called first.
    
    $outstanding_principal = floatval($loan_data['outstanding_principal']);
    
    $total_amount = $outstanding_principal + $interest_payable + $termination_fee + $penalty_due;
    
    return [
        'principal' => $outstanding_principal,
        'interest_due' => $interest_payable,
        'termination_fee' => $termination_fee,
        'penalty' => $penalty_due,
        'total_amount' => $total_amount,
        'months_elapsed' => $months_elapsed
    ];
}
?>

