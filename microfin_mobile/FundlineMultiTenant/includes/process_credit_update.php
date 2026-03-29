<?php
/**
 * Credit Limit Update Hook
 * This script should be called when a loan status changes to "Fully Paid"
 * It checks for penalties and updates the credit limit accordingly
 */

require_once '../config/db.php';
require_once 'calculate_credit_limit.php';

/**
 * Process credit limit update when loan is fully paid
 * @param int $loan_id
 * @param mysqli $conn
 */
function processLoanCompletionCreditUpdate($loan_id, $conn) {
    // Get loan details
    $stmt = $conn->prepare("
        SELECT l.client_id, l.loan_status, l.penalty_paid, 
               c.monthly_income, c.credit_limit_tier, c.credit_limit
        FROM loans l
        JOIN clients c ON l.client_id = c.client_id
        WHERE l.loan_id = ?
    ");
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $client_id = $row['client_id'];
        $loan_status = $row['loan_status'];
        $penalty_paid = floatval($row['penalty_paid']);
        $monthly_income = floatval($row['monthly_income']);
        $current_tier = intval($row['credit_limit_tier']);
        $current_limit = floatval($row['credit_limit']);
        
        // Only process if loan is fully paid
        if ($loan_status === 'Fully Paid') {
            // Check if there were penalties
            $had_penalties = $penalty_paid > 0;
            
            // Update credit limit
            $success = updateCreditLimitAfterLoanCompletion($client_id, $had_penalties, $conn);
            
            if ($success) {
                // Get new credit limit for logging
                $new_stmt = $conn->prepare("SELECT credit_limit, credit_limit_tier FROM clients WHERE client_id = ?");
                $new_stmt->bind_param("i", $client_id);
                $new_stmt->execute();
                $new_result = $new_stmt->get_result();
                $new_data = $new_result->fetch_assoc();
                $new_limit = $new_data['credit_limit'];
                $new_tier = $new_data['credit_limit_tier'];
                $new_stmt->close();
                
                $change = $had_penalties ? "decreased/frozen" : "increased";
                error_log("Credit limit {$change} for client {$client_id}: ₱{$current_limit} (tier {$current_tier}) → ₱{$new_limit} (tier {$new_tier})");
                
                return [
                    'success' => true,
                    'had_penalties' => $had_penalties,
                    'old_limit' => $current_limit,
                    'new_limit' => $new_limit,
                    'old_tier' => $current_tier,
                    'new_tier' => $new_tier
                ];
            }
        }
    }
    $stmt->close();
    
    return ['success' => false];
}

// If called directly with loan_id parameter
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    $loan_id = intval($argv[1]);
    $result = processLoanCompletionCreditUpdate($loan_id, $conn);
    
    if ($result['success']) {
        echo "✅ Credit limit updated successfully!\n";
        echo "   Old: ₱" . number_format($result['old_limit'], 2) . " (Tier {$result['old_tier']})\n";
        echo "   New: ₱" . number_format($result['new_limit'], 2) . " (Tier {$result['new_tier']})\n";
        echo "   Penalties: " . ($result['had_penalties'] ? "Yes (frozen/decreased)" : "No (increased)") . "\n";
    } else {
        echo "❌ Failed to update credit limit\n";
    }
    
    $conn->close();
}

