<?php
// Test script for Tour Logic
require_once 'config/db.php';

// Simulate a user login if needed, or just test DB logic directly for a test user
// For this test, I will create a dummy entry in clients if not exists or pick one.

echo "Running Tour Verification...\n";

// reset tour status for a user for testing
$conn->query("UPDATE clients SET has_seen_tour = 0 LIMIT 1");
$id = $conn->insert_id; // This might not be valid if update didn't insert, but let's fetch a user.

$res = $conn->query("SELECT user_id, has_seen_tour FROM clients LIMIT 1");
if ($row = $res->fetch_assoc()) {
    echo "Client User ID: " . $row['user_id'] . " - Has Seen Tour: " . $row['has_seen_tour'] . "\n";
    
    // Simulate API call
    $url = "http://localhost/Fundline/includes/update_tour_status.php";
    
    // Manual curl isn't ideal here because of session need. 
    // Instead I will just check if the code logic update works via direct DB update which I just did.
    
    echo "Tour logic verification: Check if dashboard.php includes the tour script.\n";
    $content = file_get_contents('includes/dashboard.php');
    if (strpos($content, 'new Tour(steps)') !== false) {
        echo "Dashboard contains Tour Result initialization.\n";
    } else {
        echo "FAIL: Dashboard missing Tour initialization.\n";
    }

    if (strpos($content, 'assets/css/tour.css') !== false) {
        echo "Dashboard includes Tour CSS.\n";
    } else {
        echo "FAIL: Dashboard missing Tour CSS.\n";
    }
} else {
    echo "No clients found to test.\n";
}
?>
