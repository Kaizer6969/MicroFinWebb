<?php
/**
 * Paymongo Return Page
 * The browser is redirected here after GCash/Maya payment attempt.
 * For web-app: We show a result page and post a message to the Flutter web app.
 */
$status  = $_GET['status']  ?? 'failed';
$loan_id = intval($_GET['loan_id'] ?? 0);
$amount  = floatval($_GET['amount'] ?? 0);
$method  = $_GET['method']  ?? 'GCash';

$isSuccess = ($status === 'success');
$color     = $isSuccess ? '#10B981' : '#EF4444';
$icon      = $isSuccess ? '✅' : '❌';
$title     = $isSuccess ? 'Payment Successful!' : 'Payment Failed';
$message   = $isSuccess
    ? "Your payment of ₱" . number_format($amount, 2) . " via $method has been received."
    : "Your payment could not be processed. Please try again.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Closing Secure Gateway...</title>
<style>
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: #F8FAFC;
    height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    margin: 0;
  }
  .loader { margin-bottom: 20px; border: 4px solid #E2E8F0; border-top: 4px solid #10B981; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; }
  @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
  .btn { display: none; margin-top: 24px; background: #10B981; color: white; border: none; padding: 12px 24px; border-radius: 12px; font-weight: 700; cursor: pointer; text-decoration: none; }
</style>
</head>
<body>
  <div class="loader"></div>
  <h2 style="color:#0F172A; margin: 0; font-size: 18px;">Payment Authorized!</h2>
  <p style="color:#64748B; font-size: 14px; max-width: 300px; line-height: 1.5; margin-top: 10px;">
    Returning to the main app...
  </p>
  <a id="closeBtn" class="btn" href="javascript:window.close()">Close this Tab</a>

  <script>
    try {
      var payResult = {
        status: '<?= $status ?>',
        loan_id: <?= $loan_id ?>,
        amount: <?= $amount ?>,
        method: '<?= addslashes($method) ?>',
        timestamp: Date.now()
      };
      localStorage.setItem('paymongo_result', JSON.stringify(payResult));
      
      // Auto-close aggressively
      if (window.opener && !window.opener.closed) {
        window.opener.postMessage(payResult, '*');
      }
      
      // Attempt close
      window.close();
      setTimeout(function() { window.close(); }, 500);
      setTimeout(function() { 
        // If it didn't close due to browser security blocking scripts, show the manual close button
        document.getElementById('closeBtn').style.display = 'inline-block';
        document.querySelector('.loader').style.display = 'none';
        document.querySelector('p').innerText = "Please close this window to return to your application.";
      }, 1500);

    } catch(e) {}
  </script>
</body>
</html>
