<?php
require_once '../config/db.php';

echo "Starting Credit Score Calculation...\n";

// Get all clients without a recent score (or all clients to refresh)
$sql = "SELECT c.client_id, c.monthly_income, c.employment_status, c.civil_status, 
        (SELECT COUNT(*) FROM loans WHERE client_id = c.client_id AND loan_status = 'Fully Paid') as paid_loans,
        (SELECT COUNT(*) FROM loans WHERE client_id = c.client_id AND loan_status IN ('Overdue', 'Written Off')) as bad_loans
        FROM clients c";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $client_id = $row['client_id'];
        $income = floatval($row['monthly_income']);
        $employment = $row['employment_status'];
        $paid = intval($row['paid_loans']);
        $bad = intval($row['bad_loans']);
        
        // 1. Income Score (Max 30)
        $income_score = 10;
        if ($income >= 50000) $income_score = 30;
        elseif ($income >= 30000) $income_score = 25;
        elseif ($income >= 10000) $income_score = 15;
        
        // 2. Employment Score (Max 25)
        $emp_score = 0;
        if ($employment === 'Employed') $emp_score = 25;
        elseif ($employment === 'Self-Employed') $emp_score = 20;
        elseif ($employment === 'Retired') $emp_score = 15;
        // Unemployed = 0
        
        // 3. History Score (Base 35)
        // Start with 15 base
        $history_score = 15;
        // +5 for every paid loan (cap at 20 bonus -> 35 total)
        $history_score += ($paid * 5);
        if ($history_score > 35) $history_score = 35;
        
        // -15 for every bad loan
        $history_score -= ($bad * 15);
        if ($history_score < 0) $history_score = 0;
        
        // 4. Character/Demographic (Base 10)
        $char_score = 10; 
        
        // Total (Max 100)
        $total = $income_score + $emp_score + $history_score + $char_score;
        if ($total > 100) $total = 100; // Hard cap
        
        // Rating
        $rating = 'High Risk';
        if ($total >= 90) $rating = 'Excellent';
        elseif ($total >= 75) $rating = 'Good';
        elseif ($total >= 60) $rating = 'Fair';
        elseif ($total >= 50) $rating = 'Poor';
        
        echo "Client $client_id: Total Score: $total ($rating)\n";
        
        // Insert or Update
        // Check if exists
        $check = $conn->query("SELECT score_id FROM credit_scores WHERE client_id = $client_id");
        if ($check->num_rows > 0) {
            $update = $conn->prepare("UPDATE credit_scores SET 
                income_score=?, employment_score=?, credit_history_score=?, character_score=?, total_score=?, credit_rating=?, computation_date=NOW() 
                WHERE client_id=?");
            $update->bind_param("iiiiisi", $income_score, $emp_score, $history_score, $char_score, $total, $rating, $client_id);
            $update->execute();
        } else {
            $insert = $conn->prepare("INSERT INTO credit_scores (client_id, income_score, employment_score, credit_history_score, character_score, total_score, credit_rating) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insert->bind_param("iiiiiss", $client_id, $income_score, $emp_score, $history_score, $char_score, $total, $rating);
            $insert->execute();
        }
    }
} else {
    echo "No clients found.\n";
}

echo "Calculation Complete.";
$conn->close();
?>

