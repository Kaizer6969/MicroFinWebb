<?php
require 'db.php';
$stmt = $conn->prepare("SELECT document_verification_status FROM clients WHERE user_id = ?");
$user_id = 11;
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    while($row = $res->fetch_assoc()) echo "Status: " . $row['document_verification_status'] . "\n";
} else {
    echo "No client record found for user 11.\n";
}
?>
