<?php
/**
 * Debug Credit Calculation
 * This page shows detailed information about credit usage
 */

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Client') {
    die("Access denied. Please log in as a client.");
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];

// Get client info
$stmt = $conn->prepare("SELECT client_id, first_name, last_name, credit_limit FROM clients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();
$stmt->close();

if (!$client) {
    die("Client not found");
}

$client_id = $client['client_id'];
$credit_limit = floatval($client['credit_limit']);

echo "<h1>Credit Debug for {$client['first_name']} {$client['last_name']}</h1>";
echo "<h2>Total Credit Limit: ₱" . number_format($credit_limit, 2) . "</h2>";

// Get active loans
echo "<h3>Active Loans:</h3>";
$loan_query = "
    SELECT l.loan_id, l.loan_number, l.principal_amount, l.loan_status, lp.product_name, lp.product_type
    FROM loans l
    JOIN loan_products lp ON l.product_id = lp.product_id
    WHERE l.client_id = ? AND l.loan_status IN ('Active', 'Overdue', 'Restructured')
";
$stmt = $conn->prepare($loan_query);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();

$total_loans = 0;
if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Loan #</th><th>Product</th><th>Type</th><th>Amount</th><th>Status</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $total_loans += floatval($row['principal_amount']);
        echo "<tr>";
        echo "<td>{$row['loan_number']}</td>";
        echo "<td>{$row['product_name']}</td>";
        echo "<td>{$row['product_type']}</td>";
        echo "<td>₱" . number_format($row['principal_amount'], 2) . "</td>";
        echo "<td>{$row['loan_status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p><strong>Total from Active Loans: ₱" . number_format($total_loans, 2) . "</strong></p>";
} else {
    echo "<p>No active loans found.</p>";
}
$stmt->close();

// Get pending applications
echo "<h3>Pending Applications (Not Yet Disbursed):</h3>";
$app_query = "
    SELECT la.application_id, la.application_number, la.requested_amount, la.application_status, lp.product_name, lp.product_type
    FROM loan_applications la
    JOIN loan_products lp ON la.product_id = lp.product_id
    WHERE la.client_id = ? AND la.application_status IN ('Submitted', 'Pending', 'Under Review', 'Document Verification', 'Credit Investigation', 'For Approval')
";
$stmt = $conn->prepare($app_query);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();

$total_apps = 0;
if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Application #</th><th>Product</th><th>Type</th><th>Amount</th><th>Status</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $total_apps += floatval($row['requested_amount']);
        echo "<tr>";
        echo "<td>{$row['application_number']}</td>";
        echo "<td>{$row['product_name']}</td>";
        echo "<td>{$row['product_type']}</td>";
        echo "<td>₱" . number_format($row['requested_amount'], 2) . "</td>";
        echo "<td>{$row['application_status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p><strong>Total from Pending Applications: ₱" . number_format($total_apps, 2) . "</strong></p>";
} else {
    echo "<p>No pending applications found.</p>";
}
$stmt->close();

// Calculate totals
$used_credit = $total_loans + $total_apps;
$remaining_credit = $credit_limit - $used_credit;

echo "<hr>";
echo "<h2>Summary:</h2>";
echo "<p>Total Credit Limit: ₱" . number_format($credit_limit, 2) . "</p>";
echo "<p>Used by Active Loans: ₱" . number_format($total_loans, 2) . "</p>";
echo "<p>Used by Pending Applications: ₱" . number_format($total_apps, 2) . "</p>";
echo "<p><strong>Total Used Credit: ₱" . number_format($used_credit, 2) . "</strong></p>";
echo "<p style='font-size: 1.5em; color: " . ($remaining_credit > 0 ? 'green' : 'red') . ";'><strong>Remaining Credit: ₱" . number_format($remaining_credit, 2) . "</strong></p>";

$conn->close();
?>
<br>
<a href="apply_loan.php">Back to Apply Loan</a>

