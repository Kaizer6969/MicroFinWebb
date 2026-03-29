<?php
// make_payment.php - UPDATED WITH PROFESSIONAL UI
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Client') {
    header("Location: login.php");
    exit();
}

require_once '../config/db.php';

if (!isset($_GET['loan_id']) || empty($_GET['loan_id'])) {
    $_SESSION['error_message'] = "Loan ID is required";
    header("Location: my_loans.php");
    exit();
}

require_once 'loan_helper.php';

$user_id = $_SESSION['user_id'];
$loan_id = intval($_GET['loan_id']);

// Get client_id
$stmt = $conn->prepare("SELECT client_id FROM clients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("ERROR: Client not found");
}
$client_data = $result->fetch_assoc();
$client_id = $client_data['client_id'];
$stmt->close();

// Check and Apply 6-Month Penalty if applicable (before fetching loan details)
checkAndApply6MonthPenalty($conn, $loan_id);

// Get loan details
$loan_stmt = $conn->prepare("
    SELECT 
        l.*,
        lp.product_name,
        c.first_name,
        c.last_name
    FROM loans l
    JOIN loan_products lp ON l.product_id = lp.product_id
    JOIN clients c ON l.client_id = c.client_id
    WHERE l.loan_id = ? AND l.client_id = ? AND l.loan_status = 'Active'
");
$loan_stmt->bind_param("ii", $loan_id, $client_id);
$loan_stmt->execute();
$loan_result = $loan_stmt->get_result();

if ($loan_result->num_rows === 0) {
    die("ERROR: Loan not found or not active");
}

$loan = $loan_result->fetch_assoc();
$loan_stmt->close();

$minimum_amount = floatval($loan['monthly_amortization']);
$maximum_amount = floatval($loan['remaining_balance']);
$default_amount = $minimum_amount;

$apiSecretKey = 'YOUR_SECRET_KEY';

$payment_error = '';
$processing = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_amount'])) {
    $processing = true;
    $payment_amount = floatval($_POST['payment_amount']);
    $payment_method = $_POST['payment_method'] ?? 'gcash';
    
    // Calculate Early Settlement Amount via Helper
    $settlement_data = calculateEarlySettlement($loan);
    $early_settlement_amount = $settlement_data['total_amount'];
    
    $payment_type = $_POST['payment_type'] ?? 'regular';
    
    // Validate payment amount
    if ($payment_type === 'early_settlement') {
        $payment_amount = $early_settlement_amount;
        // Override strict max check for float variance, but ensure it matches
    } else {
        if ($payment_amount < $minimum_amount) {
            $payment_error = "Payment amount must be at least ₱" . number_format($minimum_amount, 2);
        } elseif ($payment_amount > $maximum_amount) {
             // Allow full payment if it matches max amount exactly
        }
    }

    if (empty($payment_error)) {
        // Generate redirect URLs
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $base_path = dirname($_SERVER['SCRIPT_NAME']);
        $base_url = $protocol . "://" . $host . $base_path;
        
        $redirectSuccess = $base_url . "/payment_success.php?loan_id=" . $loan_id;
        $redirectFailed = $base_url . "/payment_failed.php?loan_id=" . $loan_id;
        
        // Create Paymongo source
        $url = 'https://api.paymongo.com/v1/sources';
        $amountInCents = round($payment_amount * 100);
        
        $payload = [
            'data' => [
                'attributes' => [
                    'amount' => $amountInCents,
                    'redirect' => [
                        'success' => $redirectSuccess,
                        'failed' => $redirectFailed
                    ],
                    'type' => 'gcash',
                    'currency' => 'PHP',
                    'metadata' => [
                        'loan_id' => (string)$loan_id,
                        'client_id' => (string)$client_id,
                        'transaction_type' => 'loan_payment',
                        'payment_type' => $payment_type
                    ]
                ]
            ]
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($apiSecretKey . ':')
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $source_response = json_decode($response, true);
            
            if (isset($source_response['data']['id'])) {
                $source_id = $source_response['data']['id'];
                $checkout_url = $source_response['data']['attributes']['redirect']['checkout_url'];
                
                // Save to database
                $transaction_ref = 'TRX' . date('YmdHis') . rand(1000, 9999);
                
                $insert_query = "
                    INSERT INTO payment_transactions 
                    (transaction_ref, client_id, loan_id, source_id, amount, payment_method, payment_type, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
                ";
                
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("siisdss", 
                    $transaction_ref, $client_id, $loan_id, $source_id, $payment_amount, $payment_method, $payment_type
                );
                
                if ($insert_stmt->execute()) {
                    $insert_stmt->close();
                    $conn->close();
                    
                    // SUCCESS - Redirect to Paymongo
                    ?>
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="utf-8">
                        <title>Redirecting to Payment - Fundline</title>
                        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
                        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
                        <style>
                            * { margin: 0; padding: 0; box-sizing: border-box; }
                            body {
                                font-family: 'Manrope', sans-serif;
                                background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                min-height: 100vh;
                                padding: 20px;
                            }
                            .redirect-container {
                                max-width: 500px;
                                width: 100%;
                                text-align: center;
                                background: white;
                                padding: 3rem 2rem;
                                border-radius: 24px;
                                box-shadow: 0 20px 60px rgba(220, 38, 38, 0.15);
                            }
                            .spinner {
                                width: 80px;
                                height: 80px;
                                border: 5px solid #fecaca;
                                border-top: 5px solid #dc2626;
                                border-radius: 50%;
                                animation: spin 1s linear infinite;
                                margin: 0 auto 2rem;
                            }
                            @keyframes spin {
                                0% { transform: rotate(0deg); }
                                100% { transform: rotate(360deg); }
                            }
                            h1 {
                                font-size: 1.75rem;
                                font-weight: 700;
                                color: #dc2626;
                                margin-bottom: 1rem;
                            }
                            p {
                                color: #6b7280;
                                font-size: 1rem;
                                line-height: 1.6;
                                margin-bottom: 1rem;
                            }
                            a {
                                color: #dc2626;
                                text-decoration: none;
                                font-weight: 600;
                            }
                            a:hover {
                                text-decoration: underline;
                            }
                            .pulse {
                                animation: pulse 2s ease-in-out infinite;
                            }
                            @keyframes pulse {
                                0%, 100% { opacity: 1; }
                                50% { opacity: 0.5; }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="redirect-container">
                            <div class="spinner"></div>
                            <h1>Payment Initiated</h1>
                            <p class="pulse">
                                Redirecting you to GCash payment gateway...
                            </p>
                            <p style="font-size: 0.875rem;">
                                If you are not redirected automatically, 
                                <a href="<?php echo htmlspecialchars($checkout_url); ?>">
                                    click here
                                </a>.
                            </p>
                        </div>
                        <script>
                            setTimeout(function() {
                                window.location.href = '<?php echo addslashes($checkout_url); ?>';
                            }, 1500);
                        </script>
                    </body>
                    </html>
                    <?php
                    exit();
                } else {
                    $payment_error = "Database error: " . $insert_stmt->error;
                    $insert_stmt->close();
                }
            } else {
                $payment_error = "Invalid response from payment gateway";
            }
        } else {
            $error_data = json_decode($response, true);
            if (isset($error_data['errors'])) {
                $error_messages = [];
                foreach ($error_data['errors'] as $error) {
                    $error_messages[] = $error['detail'] ?? 'Unknown error';
                }
                $payment_error = "Payment Error: " . implode("; ", $error_messages);
            } else {
                $payment_error = "Payment gateway error (HTTP " . $httpCode . ")";
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Make Payment - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="../assets/css/main_style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .dashboard-layout { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: 0; display: flex; flex-direction: column; }
        
        .top-bar {
            background-color: var(--color-surface-light);
            border-bottom: 1px solid var(--color-border-subtle);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: var(--z-sticky);
        }
        .dark .top-bar { background-color: var(--color-surface-dark); border-bottom-color: var(--color-border-dark); }
        .content-area { flex: 1; padding: 2rem; background-color: var(--color-background-light); }
        .dark .content-area { background-color: var(--color-background-dark); }
        
        .status-header {
            background-color: var(--color-surface-light);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            box-shadow: var(--shadow-card);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dark .status-header { background-color: var(--color-surface-dark); }
        
        .info-section {
            background-color: var(--color-surface-light);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            box-shadow: var(--shadow-card);
            margin-bottom: 1.5rem;
        }
        .dark .info-section { background-color: var(--color-surface-dark); }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .info-item { display: flex; flex-direction: column; gap: 0.25rem; }
        .info-label { font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .info-value { font-size: 1rem; font-weight: var(--font-weight-semibold); color: var(--color-text-main); }
        .dark .info-value { color: var(--color-text-dark); }
        
        .badge {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-full);
            font-size: 0.875rem;
            font-weight: var(--font-weight-semibold);
            display: inline-block;
        }
        .badge-success { background-color: rgba(16, 185, 129, 0.1); color: #10b981; }
        .badge-error { background-color: rgba(239, 68, 68, 0.1); color: #ef4444; }
        
        /* Specific Styles for Form */
        .amount-input-container { position: relative; margin-bottom: 1.5rem; }
        .amount-prefix { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-size: 1.5rem; font-weight: bold; color: var(--color-primary); }
        .amount-input { padding-left: 3rem !important; font-size: 1.5rem; height: 4rem; width: 100%; border: 1px solid var(--color-border-subtle); border-radius: var(--radius-lg); }
        .amount-range { display: flex; justify-content: space-between; margin-top: 0.5rem; font-size: 0.875rem; color: var(--color-text-muted); }
        
        .payment-method {
            display: flex; align-items: center; gap: 1rem; padding: 1.5rem;
            background-color: var(--color-background-light); border-radius: var(--radius-lg);
            border: 1px solid var(--color-border-subtle); margin-bottom: 2rem;
        }
        .method-icon { width: 48px; height: 48px; background: #e0f2f1; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        
        @media (max-width: 768px) {
            .info-grid { grid-template-columns: 1fr; }
            .status-header { flex-direction: column; align-items: start; gap: 1rem; }
        }
    </style>
</head>
<body class="light" style="background-color: #f8fafc;">
    <div class="min-vh-100 d-flex flex-column">
        <!-- Minimal Header -->
        <header class="py-3 px-4 bg-white border-bottom shadow-sm">
            <div class="container d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                  
                </div>
                <button onclick="window.history.back()" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                    <span class="material-symbols-outlined" style="font-size: 1.2rem;">arrow_back</span>
                    Cancel
                </button>
            </div>
        </header>

        <main class="flex-grow-1 py-5">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <div class="card border-0 shadow-lg overflow-hidden rounded-4">
                            <div class="row g-0">
                                <!-- Left Side: Loan Context (Colorful) -->
                                <div class="col-md-5 text-white p-4 p-md-5 d-flex flex-column justify-content-between position-relative overflow-hidden" style="background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);">
                                    <!-- Decorative Circles -->
                                    <div style="position: absolute; top: -50px; left: -50px; width: 200px; height: 200px; border-radius: 50%; background: rgba(255,255,255,0.1);"></div>
                                    <div style="position: absolute; bottom: -30px; right: -30px; width: 150px; height: 150px; border-radius: 50%; background: rgba(255,255,255,0.1);"></div>
                                    
                                    <div class="position-relative">
                                        <h6 class="text-white-50 text-uppercase letter-spacing-2 mb-2">Loan Reference</h6>
                                        <h3 class="fw-bold mb-4"><?php echo htmlspecialchars($loan['loan_number']); ?></h3>
                                        
                                        <div class="mb-4">
                                            <div class="text-white-50 small mb-1">Product Type</div>
                                            <div class="fs-5 fw-semibold"><?php echo htmlspecialchars($loan['product_name']); ?></div>
                                        </div>

                                        <div class="mb-4">
                                            <div class="text-white-50 small mb-1">Total Remaining Balance</div>
                                            <div class="display-6 fw-bold">₱<?php echo number_format($maximum_amount, 2); ?></div>
                                        </div>
                                    </div>

                                    <div class="mt-4 pt-4 border-top border-white-50 position-relative">
                                        <div class="d-flex align-items-center gap-3 mb-2">
                                            <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                                <span class="material-symbols-outlined">calendar_month</span>
                                            </div>
                                            <div>
                                                <div class="text-white-50 small">Next Due Date</div>
                                                <div class="fw-semibold"><?php echo $loan['next_payment_due'] ? date('M d, Y', strtotime($loan['next_payment_due'])) : 'N/A'; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Right Side: Payment Form -->
                                <div class="col-md-7 bg-white p-4 p-md-5">
                                    <h4 class="fw-bold text-dark mb-4">Make a Payment</h4>
                                    
                                    <?php if (!empty($payment_error)): ?>
                                    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                                        <span class="material-symbols-outlined me-2">error</span>
                                        <div><?php echo htmlspecialchars($payment_error); ?></div>
                                    </div>
                                    <?php endif; ?>

                                    <form method="POST" action="" id="paymentForm">
                                        
                                        <!-- Payment Type Selection -->
                                        <div class="mb-4">
                                            <label class="form-label text-muted small fw-bold text-uppercase">Payment Type</label>
                                            <div class="d-flex flex-column gap-2">
                                                <!-- Regular Payment -->
                                                <label class="payment-card-option selected p-3 border rounded-3 cursor-pointer d-flex align-items-start gap-3 transition-all" onclick="selectPaymentType('regular', this)">
                                                    <input type="radio" name="payment_type" value="regular" checked class="form-check-input mt-1">
                                                    <div>
                                                        <div class="fw-bold text-dark">Regular Payment</div>
                                                        <div class="small text-muted">Pay your monthly amortization or a custom partial amount.</div>
                                                    </div>
                                                </label>

                                                <!-- Early Settlement -->
                                                <?php
                                                    $settlement_view = calculateEarlySettlement($loan);
                                                    $early_amt_v = $settlement_view['total_amount'];
                                                ?>
                                                <label class="payment-card-option p-3 border rounded-3 cursor-pointer d-flex align-items-start gap-3 transition-all" onclick="selectPaymentType('early_settlement', this)">
                                                    <input type="radio" name="payment_type" value="early_settlement" class="form-check-input mt-1">
                                                    <div class="w-100">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div class="fw-bold text-dark">Early Full Settlement</div>
                                                            <span class="badge" style="background-color: rgba(220, 38, 38, 0.1); color: #dc2626;">Save on Interest</span>
                                                        </div>
                                                        <div class="small text-muted mb-2">Pay off the loan completely. Calculation includes remaining principal + accrued interest + fees.</div>
                                                        
                                                        <div id="fullSettlementDetails" class="bg-light p-2 rounded small mt-2" style="display: none;">
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span>Principal Balance:</span>
                                                                <span>₱<?php echo number_format($settlement_view['principal'], 2); ?></span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span>Accrued Interest:</span>
                                                                <span>₱<?php echo number_format($settlement_view['interest_due'], 2); ?></span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span>Termination Fee:</span>
                                                                <span>₱<?php echo number_format($settlement_view['termination_fee'], 2); ?></span>
                                                            </div>
                                                            <?php if($settlement_view['penalty'] > 0): ?>
                                                            <div class="d-flex justify-content-between mb-1 text-danger">
                                                                <span>Penalty:</span>
                                                                <span>₱<?php echo number_format($settlement_view['penalty'], 2); ?></span>
                                                            </div>
                                                            <?php endif; ?>
                                                            <div class="border-top pt-1 mt-1 d-flex justify-content-between fw-bold">
                                                                <span>Total Amount:</span>
                                                                <span style="color: #dc2626;">₱<?php echo number_format($early_amt_v, 2); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Amount Input -->
                                        <div class="mb-4">
                                            <label class="form-label text-muted small fw-bold text-uppercase">Payment Amount</label>
                                            <div class="input-group input-group-lg border rounded-3 overflow-hidden">
                                                <span class="input-group-text bg-white border-0 ps-3 text-muted">₱</span>
                                                <input type="number" name="payment_amount" id="payment_amount" 
                                                       class="form-control border-0 fw-bold fs-4" 
                                                       value="<?php echo $minimum_amount; ?>"
                                                       step="0.01" min="<?php echo $minimum_amount; ?>" max="<?php echo $maximum_amount; ?>" required>
                                            </div>
                                            <div class="d-flex justify-content-between mt-1 px-1">
                                                <small class="text-muted">Min: ₱<?php echo number_format($minimum_amount, 2); ?></small>
                                                <small class="text-muted">Max: ₱<?php echo number_format($maximum_amount, 2); ?></small>
                                            </div>
                                        </div>
                                        
                                        <!-- Payment Method -->
                                        <div class="mb-4">
                                            <label class="form-label text-muted small fw-bold text-uppercase">Payment Method</label>
                                            <div class="p-3 border rounded-3 bg-light d-flex align-items-center justify-content-between">
                                                <div class="d-flex align-items-center gap-3">
                                                    <img src="../assets/image/gcash_logo.png" alt="GCash" style="height: 30px;">
                                                    <span class="fw-semibold text-dark">GCash e-Wallet</span>
                                                </div>
                                                <span class="material-symbols-outlined" style="color: #dc2626;">check_circle</span>
                                            </div>
                                            <input type="hidden" name="payment_method" value="gcash">
                                        </div>

                                        <button type="submit" id="submitBtn" class="btn d-block w-100 py-3 fw-bold shadow-sm transition-all hover-lift" style="background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); border: none; color: white;">
                                            Pay Now
                                        </button>
                                        
                                        <div class="text-center mt-3">
                                            <small class="text-muted d-flex align-items-center justify-content-center gap-1">
                                                <span class="material-symbols-outlined" style="font-size: 14px;">lock</span>
                                                Secured by Paymongo
                                            </small>
                                        </div>

                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
        .hover-lift:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
        .transition-all { transition: all 0.2s ease; }
        .payment-card-option.selected { border-color: #dc2626 !important; background-color: rgba(220, 38, 38, 0.05); }
        .letter-spacing-2 { letter-spacing: 2px; }
    </style>

    <script>
        function selectPaymentType(type, element) {
            // Visual selection
            document.querySelectorAll('.payment-card-option').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
            
            // Logic
            const amountInput = document.getElementById('payment_amount');
            const details = document.getElementById('fullSettlementDetails');
            
            if (type === 'early_settlement') {
                amountInput.value = <?php echo round($early_amt_v, 2); ?>;
                amountInput.readOnly = true;
                details.style.display = 'block';
            } else {
                amountInput.value = <?php echo $minimum_amount; ?>;
                amountInput.readOnly = false;
                details.style.display = 'none';
            }
        }
        
        // Form Validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            const amount = parseFloat(document.getElementById('payment_amount').value);
            const type = document.querySelector('input[name="payment_type"]:checked').value;
            
            if (type === 'regular') {
                const min = <?php echo $minimum_amount; ?>;
                const max = <?php echo $maximum_amount; ?>;
                
                // Allow some float tolerance
                if (amount < min - 0.1 || amount > max + 1) {
                    e.preventDefault();
                    alert('Please enter a valid amount between ₱' + min.toFixed(2) + ' and ₱' + max.toFixed(2));
                    return;
                }
            }
            
            btn.innerHTML = 'Processing...';
            btn.disabled = true;
            btn.style.opacity = '0.7';
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
