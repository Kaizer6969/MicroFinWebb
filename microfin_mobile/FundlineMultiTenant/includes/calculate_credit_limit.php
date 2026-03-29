<?php
/**
 * Credit Limit Calculation Utility
 * Handles automatic credit limit calculation based on income and payment history
 */

require_once '../config/db.php';

/**
 * Get income bracket information
 * @param float $monthly_income
 * @return array|null Bracket info with min, max, credit_min, credit_max
 */
function getIncomeBracket($monthly_income) {
    $brackets = [
        ['min' => 10000, 'max' => 19999, 'credit_min' => 5000, 'credit_max' => 15000],
        ['min' => 20000, 'max' => 29999, 'credit_min' => 10000, 'credit_max' => 30000],
        ['min' => 30000, 'max' => 39999, 'credit_min' => 20000, 'credit_max' => 50000],
        ['min' => 40000, 'max' => 49999, 'credit_min' => 30000, 'credit_max' => 70000],
        ['min' => 50000, 'max' => 59999, 'credit_min' => 40000, 'credit_max' => 90000],
        ['min' => 60000, 'max' => 69999, 'credit_min' => 50000, 'credit_max' => 110000],
        ['min' => 70000, 'max' => 79999, 'credit_min' => 60000, 'credit_max' => 130000],
        ['min' => 80000, 'max' => 89999, 'credit_min' => 70000, 'credit_max' => 150000],
        ['min' => 90000, 'max' => 99999, 'credit_min' => 80000, 'credit_max' => 170000],
        ['min' => 100000, 'max' => PHP_INT_MAX, 'credit_min' => 90000, 'credit_max' => 200000],
    ];
    
    foreach ($brackets as $bracket) {
        if ($monthly_income >= $bracket['min'] && $monthly_income <= $bracket['max']) {
            return $bracket;
        }
    }
    
    // If income is below minimum, return lowest bracket
    if ($monthly_income < 10000) {
        return ['min' => 0, 'max' => 9999, 'credit_min' => 3000, 'credit_max' => 10000];
    }
    
    return null;
}

/**
 * Calculate initial credit limit (for new users)
 * @param float $monthly_income
 * @return float Initial credit limit (minimum of bracket)
 */
function getInitialCreditLimit($monthly_income) {
    $bracket = getIncomeBracket($monthly_income);
    return $bracket ? $bracket['credit_min'] : 0;
}

/**
 * Calculate progressive credit limit based on tier
 * @param float $base_min Minimum credit for income bracket
 * @param float $base_max Maximum credit for income bracket
 * @param int $tier Current tier level
 * @return float Calculated credit limit
 */
function calculateProgressiveCredit($base_min, $base_max, $tier) {
    // Start at minimum
    $current_limit = $base_min;
    
    // Each tier adds 10% or ₱5,000 (whichever is greater)
    for ($i = 0; $i < $tier; $i++) {
        $increase = max($current_limit * 0.10, 5000);
        $current_limit += $increase;
        
        // Don't exceed maximum
        if ($current_limit > $base_max) {
            $current_limit = $base_max;
            break;
        }
    }
    
    return round($current_limit, 2);
}

/**
 * Calculate credit limit for a client
 * @param int $client_id
 * @param float $monthly_income
 * @param mysqli $conn Database connection
 * @return float Calculated credit limit
 */
function calculateCreditLimit($client_id, $monthly_income, $conn) {
    // Get client's current tier
    $stmt = $conn->prepare("SELECT credit_limit_tier FROM clients WHERE client_id = ?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $tier = 0;
    
    if ($row = $result->fetch_assoc()) {
        $tier = intval($row['credit_limit_tier']);
    }
    $stmt->close();
    
    // Get income bracket
    $bracket = getIncomeBracket($monthly_income);
    if (!$bracket) {
        return 0;
    }
    
    // Calculate progressive credit
    return calculateProgressiveCredit($bracket['credit_min'], $bracket['credit_max'], $tier);
}

/**
 * Update credit limit after loan completion
 * @param int $client_id
 * @param bool $had_penalties Whether the loan had any penalties
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function updateCreditLimitAfterLoanCompletion($client_id, $had_penalties, $conn) {
    // Get current tier and income
    $stmt = $conn->prepare("SELECT credit_limit_tier, monthly_income FROM clients WHERE client_id = ?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$row = $result->fetch_assoc()) {
        $stmt->close();
        return false;
    }
    
    $current_tier = intval($row['credit_limit_tier']);
    $monthly_income = floatval($row['monthly_income']);
    $stmt->close();
    
    $new_tier = $current_tier;
    
    if ($had_penalties) {
        // Freeze or decrease tier
        if ($current_tier > 0) {
            $new_tier = max(0, $current_tier - 1); // Decrease by 1, minimum 0
        }
    } else {
        // Increase tier for good payment behavior
        $new_tier = $current_tier + 1;
    }
    
    // Recalculate credit limit
    $bracket = getIncomeBracket($monthly_income);
    if (!$bracket) {
        return false;
    }
    
    $new_credit_limit = calculateProgressiveCredit($bracket['credit_min'], $bracket['credit_max'], $new_tier);
    
    // Update database
    $update_stmt = $conn->prepare("UPDATE clients SET credit_limit_tier = ?, credit_limit = ? WHERE client_id = ?");
    $update_stmt->bind_param("idi", $new_tier, $new_credit_limit, $client_id);
    $success = $update_stmt->execute();
    $update_stmt->close();
    
    return $success;
}

/**
 * Set initial credit limit for a new client
 * @param int $client_id
 * @param float $monthly_income
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function setInitialCreditLimit($client_id, $monthly_income, $conn) {
    $initial_credit = getInitialCreditLimit($monthly_income);
    
    $stmt = $conn->prepare("UPDATE clients SET credit_limit = ?, credit_limit_tier = 0 WHERE client_id = ?");
    $stmt->bind_param("di", $initial_credit, $client_id);
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

/**
 * Recalculate credit limit when income changes
 * @param int $client_id
 * @param float $new_monthly_income
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function recalculateCreditLimitOnIncomeChange($client_id, $new_monthly_income, $conn) {
    // Get current tier
    $stmt = $conn->prepare("SELECT credit_limit_tier FROM clients WHERE client_id = ?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $tier = 0;
    
    if ($row = $result->fetch_assoc()) {
        $tier = intval($row['credit_limit_tier']);
    }
    $stmt->close();
    
    // Calculate new credit limit based on new income and current tier
    $bracket = getIncomeBracket($new_monthly_income);
    if (!$bracket) {
        return false;
    }
    
    $new_credit_limit = calculateProgressiveCredit($bracket['credit_min'], $bracket['credit_max'], $tier);
    
    // Update credit limit
    $update_stmt = $conn->prepare("UPDATE clients SET credit_limit = ? WHERE client_id = ?");
    $update_stmt->bind_param("di", $new_credit_limit, $client_id);
    $success = $update_stmt->execute();
    $update_stmt->close();
    
    return $success;
}

