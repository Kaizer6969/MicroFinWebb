<?php
$url = 'https://microfinwebb-production.up.railway.app/microfin_mobile/api/api_apply_loan.php';
$data = file_get_contents('test_payload.json');
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);
echo $result;
?>
