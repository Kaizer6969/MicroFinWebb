<?php
require 'microfin_mobile/api/db.php';
$res = $conn->query("SHOW TRIGGERS");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
