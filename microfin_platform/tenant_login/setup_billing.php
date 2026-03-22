<?php
session_start();
require_once "../backend/db_connect.php";

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["user_logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Check current setup step — this page is step 5 (billing - final)
$step_stmt = $pdo->prepare('SELECT setup_current_step, setup_completed, mrr FROM tenants WHERE tenant_id = ?');
$step_stmt->execute([$tenant_id]);
$step_data = $step_stmt->fetch(PDO::FETCH_ASSOC);
$current_step = (int)($step_data['setup_current_step'] ?? 0);
$monthly_price = (float)($step_data['mrr'] ?? 0);

if ($step_data && (bool)$step_data['setup_completed']) {
    header('Location: ../admin_panel/admin.php');
    exit;
}

if ($current_step !== 5) {
    if (in_array($current_step, [1, 2])) {
        // Upgrade any tenants stuck on removed steps 1 or 2 up to step 5
        $pdo->prepare('UPDATE tenants SET setup_current_step = 5 WHERE tenant_id = ?')->execute([$tenant_id]);
        $current_step = 5;
    } else {
        $setup_routes = [0 => 'force_change_password.php', 3 => 'setup_website.php', 4 => 'setup_branding.php'];
        if (isset($setup_routes[$current_step])) {
            header('Location: ' . $setup_routes[$current_step]);
        } else {
            header('Location: ../admin_panel/admin.php');
        }
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cardholder_name = trim($_POST['cardholder_name'] ?? '');
    $card_number = trim($_POST['card_number'] ?? '');
    $exp_month = (int) ($_POST['exp_month'] ?? 0);
    $exp_year = (int) ($_POST['exp_year'] ?? 0);
    $card_brand = trim($_POST['card_brand'] ?? '');
    $billing_cycle_preference = trim($_POST['billing_cycle_preference'] ?? '1');

    // Validate
    $card_clean = preg_replace('/\s+/', '', $card_number);
    if ($cardholder_name === '' || $card_clean === '') {
        $error = 'Cardholder name and card number are required.';
    } elseif (strlen($card_clean) < 13 || strlen($card_clean) > 19 || !ctype_digit($card_clean)) {
        $error = 'Please enter a valid card number (13-19 digits).';
    } elseif ($exp_month < 1 || $exp_month > 12) {
        $error = 'Please select a valid expiration month.';
    } elseif ($exp_year < (int) date('Y')) {
        $error = 'Expiration year cannot be in the past.';
    } else {
        $last_four = substr($card_clean, -4);

        // Encrypt the full card number with AES-256
        $encryption_key = defined('ENCRYPTION_KEY') ? constant('ENCRYPTION_KEY') : 'microfin_default_encryption_key_32b';
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($card_clean, 'aes-256-cbc', $encryption_key, 0, $iv);
        $encrypted_with_iv = base64_encode($iv . '::' . base64_decode($encrypted));

        // Auto-detect card brand
        if ($card_brand === '') {
            $first_digit = $card_clean[0];
            $first_two = substr($card_clean, 0, 2);
            if ($first_digit === '4') $card_brand = 'Visa';
            elseif (in_array($first_two, ['51','52','53','54','55'])) $card_brand = 'Mastercard';
            elseif (in_array($first_two, ['34','37'])) $card_brand = 'Amex';
            else $card_brand = 'Other';
        }

        $stmt = $pdo->prepare('INSERT INTO tenant_billing_payment_methods (tenant_id, last_four_digits, card_brand, cardholder_name, exp_month, exp_year, card_number_encrypted, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)');
        $stmt->execute([$tenant_id, $last_four, $card_brand, $cardholder_name, $exp_month, $exp_year, $encrypted_with_iv]);

        if (!in_array($billing_cycle_preference, ['1', '15'], true)) {
            $billing_cycle_preference = '1';
        }
        
        $today_ts = time();
        $current_day = (int) date('j', $today_ts);
        $current_month_days = (int) date('t', $today_ts);
        $is_full_month = false;

        if ($billing_cycle_preference === '1') {
            if ($current_day === 1) {
                // Exactly the 1st
                $next_billing = date('Y-m-01', strtotime('+1 month', $today_ts));
                $prorated_days = 30;
                $is_full_month = true;
            } else {
                $next_billing = date('Y-m-01', strtotime('+1 month', $today_ts));
                $prorated_days = $current_month_days - $current_day + 1;
            }
        } else {
            if ($current_day === 15) {
                // Exactly the 15th
                $next_billing = date('Y-m-15', strtotime('+1 month', $today_ts));
                $prorated_days = 30;
                $is_full_month = true;
            } elseif ($current_day < 15) {
                $next_billing = date('Y-m-15', $today_ts);
                $prorated_days = 15 - $current_day;
            } else {
                $next_billing = date('Y-m-15', strtotime('+1 month', $today_ts));
                $prorated_days = ($current_month_days - $current_day + 1) + 14;
            }
        }

        $pdo->prepare('UPDATE tenants SET billing_cycle = ?, next_billing_date = ?, setup_current_step = 6, setup_completed = TRUE WHERE tenant_id = ? AND setup_current_step = 5')->execute([$billing_cycle_preference, $next_billing, $tenant_id]);

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'BILLING_SETUP', 'tenant', 'Payment method added during onboarding', ?)");
        $log->execute([$user_id, $tenant_id]);

        // Generate the initial/prorated invoice immediately
        $tenant_stmt = $pdo->prepare("SELECT mrr FROM tenants WHERE tenant_id = ?");
        $tenant_stmt->execute([$tenant_id]);
        $monthly_price = (float) $tenant_stmt->fetchColumn();

        if ($monthly_price > 0) {
            if ($is_full_month) {
                $prorated_amount = $monthly_price;
            } else {
                $daily_rate = $monthly_price / 30;
                $prorated_amount = round($daily_rate * $prorated_days, 2);
            }

            $invoice_number = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $period_start = date('Y-m-d', $today_ts);
            $period_end = date('Y-m-d', strtotime($next_billing . ' -1 day'));

            $inv_stmt = $pdo->prepare("
                INSERT INTO tenant_billing_invoices 
                (tenant_id, invoice_number, amount, billing_period_start, billing_period_end, due_date, status, paid_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'Paid', NOW())
            ");
            $inv_stmt->execute([
                $tenant_id,
                $invoice_number,
                $prorated_amount,
                $period_start,
                $period_end,
                $period_start // due immediately on setup
            ]);

            $log2 = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'BILLING_PRORATION', 'invoice', ?, ?)");
            $log2->execute([$user_id, "Generated initial invoice {$invoice_number}. Amount: {$prorated_amount} for {$prorated_days} days.", $tenant_id]);
        }

        header('Location: ../admin_panel/admin.php');
        exit;
    }
}

// Fetch branding for styling
$brand_stmt = $pdo->prepare('SELECT theme_primary_color, theme_text_main, theme_text_muted, theme_bg_body, theme_bg_card, font_family FROM tenant_branding WHERE tenant_id = ?');
$brand_stmt->execute([$tenant_id]);
$brand = $brand_stmt->fetch(PDO::FETCH_ASSOC);
$accent = ($brand && $brand['theme_primary_color']) ? $brand['theme_primary_color'] : '#0284c7';
$t_text = ($brand && $brand['theme_text_main']) ? $brand['theme_text_main'] : '#0f172a';
$t_muted = ($brand && $brand['theme_text_muted']) ? $brand['theme_text_muted'] : '#64748b';
$t_bg = ($brand && $brand['theme_bg_body']) ? $brand['theme_bg_body'] : '#f1f5f9';
$t_card = ($brand && $brand['theme_bg_card']) ? $brand['theme_bg_card'] : '#ffffff';
$t_font = ($brand && $brand['font_family']) ? $brand['font_family'] : 'Inter';

$tenant_name = $_SESSION['tenant_name'] ?? 'Your Organization';
$current_year = (int) date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Billing - MicroFin</title>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($t_font); ?>:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: '<?php echo htmlspecialchars($t_font); ?>', sans-serif; background: <?php echo htmlspecialchars($t_bg); ?>; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .wizard-card { background: <?php echo htmlspecialchars($t_card); ?>; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 10px 15px -3px rgba(0,0,0,0.05); width: 100%; max-width: 560px; overflow: hidden; }
        .wizard-header { background: linear-gradient(135deg, <?php echo htmlspecialchars($accent); ?>, #8b5cf6); padding: 32px; color: white; }
        .wizard-header h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 4px; }
        .wizard-header p { opacity: 0.85; font-size: 0.9rem; }
        .step-indicator { display: flex; gap: 8px; margin-top: 16px; }
        .step { width: 40px; height: 4px; border-radius: 2px; background: rgba(255,255,255,0.3); }
        .step.active { background: white; }
        .wizard-body { padding: 32px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 8px; color: #475569; font-size: 0.9rem; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 0.95rem; color: #0f172a; transition: border-color 0.2s; }
        .form-control:focus { outline: none; border-color: <?php echo htmlspecialchars($accent); ?>; box-shadow: 0 0 0 3px rgba(2,132,199,0.1); }
        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .card-preview { background: linear-gradient(135deg, #1e293b, #334155); border-radius: 12px; padding: 24px; color: white; margin-bottom: 24px; position: relative; overflow: hidden; }
        .card-preview::after { content: ''; position: absolute; top: -30px; right: -30px; width: 100px; height: 100px; background: rgba(255,255,255,0.05); border-radius: 50%; }
        .card-preview .card-number { font-size: 1.3rem; letter-spacing: 3px; font-weight: 600; margin: 20px 0 16px; font-family: monospace; }
        .card-preview .card-name { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8; }
        .card-preview .card-expiry { font-size: 0.85rem; opacity: 0.8; position: absolute; bottom: 24px; right: 24px; }
        .card-preview .card-brand-display { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.6; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 28px; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; font-family: inherit; font-size: 0.95rem; transition: all 0.2s; }
        .btn-primary { background: <?php echo htmlspecialchars($accent); ?>; color: white; width: 100%; justify-content: center; }
        .btn-primary:hover { filter: brightness(0.9); }
        .error { color: #ef4444; background: #fef2f2; border: 1px solid #fecaca; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
        .security-note { display: flex; align-items: center; gap: 8px; padding: 12px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; margin-bottom: 24px; font-size: 0.85rem; color: #166534; }
        small { color: #94a3b8; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="wizard-card">
        <div class="wizard-header">
            <h1>Payment Method</h1>
            <p>Add a payment method for your <?php echo htmlspecialchars($tenant_name); ?> subscription.</p>
            <div class="step-indicator">
                <div class="step active"></div>
                <div class="step active"></div>
                <div class="step active"></div>
                <div class="step active"></div>
            </div>
        </div>
        <div class="wizard-body">
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Card Preview -->
            <div class="card-preview">
                <div class="card-brand-display" id="preview-brand">VISA</div>
                <div class="card-number" id="preview-number">&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;</div>
                <div class="card-name" id="preview-name">CARDHOLDER NAME</div>
                <div class="card-expiry" id="preview-expiry">MM/YY</div>
            </div>

            <div class="security-note">
                <span class="material-symbols-rounded" style="font-size: 18px;">lock</span>
                Your card details are encrypted with AES-256. We never store your CVC.
            </div>

            <form method="POST">
                <div class="form-group">
                    <label>Cardholder Name</label>
                    <input type="text" class="form-control" name="cardholder_name" id="cardholder_name" placeholder="Juan Dela Cruz" required oninput="updateCardPreview();">
                </div>

                <div class="form-group">
                    <label>Card Number</label>
                    <input type="text" class="form-control" name="card_number" id="card_number" placeholder="4242 4242 4242 4242" maxlength="24" required oninput="formatCardNumber(this); updateCardPreview();">
                </div>

                <input type="hidden" name="card_brand" id="card_brand" value="">

                <div class="form-group" style="padding: 16px; background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 8px;">
                    <label>Preferred Billing Cycle</label>
                    <select class="form-control" name="billing_cycle_preference" id="billing_cycle_preference" required style="margin-bottom: 8px;">
                        <option value="1">1st of the Month (Start of Month)</option>
                        <option value="15">15th of the Month (Middle of Month)</option>
                    </select>
                    <div style="font-size: 0.8rem; color: #b45309; display: flex; gap: 6px; align-items: flex-start; padding-top: 4px;">
                        <span class="material-symbols-rounded" style="font-size: 16px;">warning</span>
                        <p style="margin:0; line-height: 1.4;">Warning: This anchors your permanent billing cycle date and <strong>cannot be changed again</strong> after completion.</p>
                    </div>
                </div>

                <div class="row-2" style="grid-template-columns: 1fr 1fr 1fr;">
                    <div class="form-group">
                        <label>Expiration Month</label>
                        <select class="form-control" name="exp_month" id="exp_month" required onchange="updateCardPreview();">
                            <option value="">Month</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>"><?php echo str_pad($m, 2, '0', STR_PAD_LEFT) . ' - ' . date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Expiration Year</label>
                        <select class="form-control" name="exp_year" id="exp_year" required onchange="updateCardPreview();">
                            <option value="">Year</option>
                            <?php for ($y = $current_year; $y <= $current_year + 15; $y++): ?>
                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>CVV / CVC</label>
                        <input type="password" class="form-control" name="cvv" id="cvv" placeholder="123" maxlength="4" required style="letter-spacing: 2px;">
                    </div>
                </div>

                <div id="proration-summary" style="display: none; padding: 14px; background: #e0f2fe; border: 1px solid #7dd3fc; border-radius: 8px; margin-top: 10px; margin-bottom: 24px;">
                    <h4 style="margin: 0 0 6px 0; color: #0369a1; font-size: 0.95rem;">&#128274; Checkout Summary</h4>
                    <p id="proration-text" style="margin: 0; color: #0c4a6e; font-size: 0.85rem; line-height: 1.5;"></p>
                </div>

                <div class="form-group" style="padding: 12px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; margin-top: 4px;">
                    <label style="display: flex; align-items: flex-start; gap: 8px; margin: 0; cursor: pointer; font-size: 0.85rem; font-weight: 500; color: #166534;">
                        <input type="checkbox" name="agree_billing" id="agree_billing" required style="margin-top: 3px; accent-color: <?php echo htmlspecialchars($accent); ?>;">
                        <span style="line-height: 1.4;">I authorize MicroFin to save this payment method, charge the prorated amount immediately, and continue recurring billing. I agree to the <a href="#" id="open-billing-tos" style="color: #0369a1; text-decoration: underline; font-weight: 600;">Billing Terms &amp; No-Refund Policy</a>.</span>
                    </label>
                </div>

                <button type="button" id="btn-pay-submit" class="btn btn-primary" style="display:flex; align-items:center; gap:8px; width:100%; justify-content:center; margin-top:16px; font-size:1rem; padding:12px;">
                    <span class="material-symbols-rounded" style="font-size:1.2rem;">lock</span> Authorize &amp; Activate Subscription
                </button>
                <input type="submit" id="real-submit" style="display:none;">
            </form>
        </div>
    </div>

    <!-- Payment Processing Overlay -->
    <div id="payment-overlay" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.92); z-index:9998; align-items:center; justify-content:center; flex-direction:column;">
        <div style="background:#1e293b; border:1px solid rgba(147,197,253,0.2); border-radius:16px; padding:40px 48px; text-align:center; max-width:400px; width:90%; position:relative;">
            <div id="pay-spinner" style="width:52px; height:52px; border:4px solid rgba(147,197,253,0.2); border-top-color:<?php echo htmlspecialchars($accent); ?>; border-radius:50%; animation:spin 0.8s linear infinite; margin:0 auto 20px;"></div>
            <h3 id="pay-status-title" style="color:#f8fbff; font-size:1.1rem; margin:0 0 8px;">Authorizing Payment...</h3>
            <p id="pay-status-sub" style="color:#94a3b8; font-size:0.85rem; margin:0;">Please wait while we securely process your charge.</p>
            <div id="pay-steps" style="margin-top:20px; text-align:left; display:flex; flex-direction:column; gap:6px;">
                <div id="pstep-1" style="display:flex; align-items:center; gap:8px; color:#64748b; font-size:0.85rem; transition:color 0.3s;"><span style="font-size:16px;">&#9675;</span> Encrypting card details</div>
                <div id="pstep-2" style="display:flex; align-items:center; gap:8px; color:#64748b; font-size:0.85rem; transition:color 0.3s;"><span style="font-size:16px;">&#9675;</span> Validating payment method</div>
                <div id="pstep-3" style="display:flex; align-items:center; gap:8px; color:#64748b; font-size:0.85rem; transition:color 0.3s;"><span style="font-size:16px;">&#9675;</span> Processing initial charge</div>
                <div id="pstep-4" style="display:flex; align-items:center; gap:8px; color:#64748b; font-size:0.85rem; transition:color 0.3s;"><span style="font-size:16px;">&#9675;</span> Activating subscription</div>
            </div>
        </div>
    </div>
    <style>@keyframes spin { 100% { transform: rotate(360deg); } }</style>

    <!-- Billing ToS Modal -->
    <div id="billing-tos-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; overflow-y:auto; padding:40px 20px;">
        <div style="background:#fff; border-radius:14px; max-width:620px; margin:0 auto; padding:32px; color:#1e293b; line-height:1.7; box-shadow:0 20px 60px rgba(0,0,0,0.35);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
                <h2 style="margin:0; font-size:1.15rem;">Billing Terms &amp; Conditions</h2>
                <button type="button" id="close-billing-tos" style="background:none; border:none; cursor:pointer; font-size:1.5rem; color:#64748b; line-height:1;">&times;</button>
            </div>
            <p style="font-size:0.78rem; color:#64748b; margin-bottom:14px;">Effective Date: <?php echo date('F d, Y'); ?> &mdash; MicroFin Platform</p>
            <h3 style="color:#0369a1; font-size:0.87rem; margin:14px 0 5px;">1. Prorated Initial Charge</h3>
            <p style="font-size:0.83rem;">You will be charged a prorated amount from today until your next selected billing date. This aligns your account billing cycle.</p>
            <h3 style="color:#0369a1; font-size:0.87rem; margin:14px 0 5px;">2. Recurring Monthly Billing</h3>
            <p style="font-size:0.83rem;">Your subscription renews automatically on your billing date each month. The full monthly fee is deducted from your payment method.</p>
            <h3 style="color:#b91c1c; font-size:0.87rem; margin:14px 0 5px;">3. No-Refund Policy</h3>
            <p style="font-size:0.83rem; background:#fef2f2; padding:10px 12px; border-radius:6px; border-left:3px solid #f87171;"><strong>All fees are strictly non-refundable.</strong> This includes the prorated initial charge, monthly fees, and any fees incurred before cancellation. No exceptions are made for partial usage.</p>
            <div style="margin-top:22px; text-align:right;">
                <button type="button" id="close-billing-tos-btn" style="background:<?php echo htmlspecialchars($accent); ?>; color:#fff; border:none; border-radius:8px; padding:9px 22px; font-weight:600; cursor:pointer;">Got it &mdash; I Agree</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tosBtn = document.getElementById('open-billing-tos');
            const tosModal = document.getElementById('billing-tos-backdrop');
            const closeTos1 = document.getElementById('close-billing-tos');
            const closeTos2 = document.getElementById('close-billing-tos-btn');
            const payBtn = document.getElementById('btn-pay-submit');
            const realSubmit = document.getElementById('real-submit');
            const overlay = document.getElementById('payment-overlay');
            const form = document.querySelector('form');
            
            if (tosBtn) tosBtn.addEventListener('click', e => { e.preventDefault(); tosModal.style.display = 'block'; });
            if (closeTos1) closeTos1.addEventListener('click', () => tosModal.style.display = 'none');
            if (closeTos2) closeTos2.addEventListener('click', () => tosModal.style.display = 'none');
            if (tosModal) tosModal.addEventListener('click', e => { if (e.target === tosModal) tosModal.style.display = 'none'; });

            if (payBtn) payBtn.addEventListener('click', async (e) => {
                if (!form.reportValidity()) return;
                e.preventDefault();
                overlay.style.display = 'flex';
                
                const steps = [
                    document.getElementById('pstep-1'),
                    document.getElementById('pstep-2'),
                    document.getElementById('pstep-3'),
                    document.getElementById('pstep-4')
                ];
                
                for(let i=0; i<steps.length; i++) {
                    await new Promise(r => setTimeout(r, 600 + Math.random()*400));
                    steps[i].style.color = '#34d399';
                    steps[i].innerHTML = '<span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">check_circle</span>' + steps[i].innerHTML.substring(steps[i].innerHTML.indexOf('</span>') + 7);
                }
                await new Promise(r => setTimeout(r, 400));
                document.getElementById('pay-spinner').style.borderColor = '#10b981';
                document.getElementById('pay-status-title').textContent = 'Payment Successful';
                document.getElementById('pay-status-title').style.color = '#10b981';
                document.getElementById('pay-status-sub').textContent = 'Redirecting to your dashboard...';
                await new Promise(r => setTimeout(r, 800));
                realSubmit.click();
            });
        });

        function formatCardNumber(input) {
            let v = input.value.replace(/\D/g, '');
            let formatted = v.match(/.{1,4}/g)?.join(' ') || v;
            input.value = formatted;
        }

        function updateCardPreview() {
            const name = document.getElementById('cardholder_name').value.toUpperCase() || 'CARDHOLDER NAME';
            const number = document.getElementById('card_number').value.replace(/\D/g, '');
            const month = document.getElementById('exp_month').value;
            const year = document.getElementById('exp_year').value;
            
            document.getElementById('preview-name').textContent = name;

            // Format card number for display
            let display = '';
            for (let i = 0; i < 16; i++) {
                if (i > 0 && i % 4 === 0) display += ' ';
                display += i < number.length ? number[i] : '\u2022';
            }
            document.getElementById('preview-number').textContent = display;

            // Expiry
            const mm = month ? month.toString().padStart(2, '0') : 'MM';
            const yy = year ? year.toString().slice(-2) : 'YY';
            document.getElementById('preview-expiry').textContent = mm + '/' + yy;

            // Auto-detect brand
            let brand = 'CARD';
            if (number.length > 0) {
                const first = number[0];
                const firstTwo = number.substring(0, 2);
                if (first === '4') brand = 'VISA';
                else if (['51','52','53','54','55'].includes(firstTwo)) brand = 'MASTERCARD';
                else if (['34','37'].includes(firstTwo)) brand = 'AMEX';
                else if (firstTwo === '36' || firstTwo === '38') brand = 'DINERS';
            }
            document.getElementById('preview-brand').textContent = brand;
            document.getElementById('card_brand').value = brand;
        }

        const mrr = <?php echo json_encode($monthly_price); ?>;
        
        function updateProrationPreview() {
            const cycle = document.getElementById('billing_cycle_preference').value;
            const summaryDiv = document.getElementById('proration-summary');
            const summaryText = document.getElementById('proration-text');
            
            if (!mrr || mrr <= 0) {
                summaryDiv.style.display = 'none';
                return;
            }

            const today = new Date();
            const currentDay = today.getDate();
            const currentMonthDays = new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate();
            let proratedDays = 0;
            let nextBillingDateStr = '';

            const formatNextDate = (d) => d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            
            if (cycle === '1') {
                if (currentDay === 1) {
                    proratedDays = 30; // standard full month
                    const nextDate = new Date(today.getFullYear(), today.getMonth() + 1, 1);
                    nextBillingDateStr = formatNextDate(nextDate);
                } else {
                    proratedDays = currentMonthDays - currentDay + 1;
                    const nextDate = new Date(today.getFullYear(), today.getMonth() + 1, 1);
                    nextBillingDateStr = formatNextDate(nextDate);
                }
            } else { // cycle === '15'
                if (currentDay === 15) {
                    proratedDays = 30; // standard full month
                    const nextDate = new Date(today.getFullYear(), today.getMonth() + 1, 15);
                    nextBillingDateStr = formatNextDate(nextDate);
                } else if (currentDay < 15) {
                    proratedDays = 15 - currentDay;
                    const nextDate = new Date(today.getFullYear(), today.getMonth(), 15);
                    nextBillingDateStr = formatNextDate(nextDate);
                } else { // currentDay > 15
                    proratedDays = (currentMonthDays - currentDay + 1) + 14;
                    const nextDate = new Date(today.getFullYear(), today.getMonth() + 1, 15);
                    nextBillingDateStr = formatNextDate(nextDate);
                }
            }

            const isFullMonth = (proratedDays === 30 && (currentDay === 1 || currentDay === 15));
            let amount = 0;
            if (isFullMonth) {
                amount = mrr;
            } else {
                const dailyRate = mrr / 30;
                amount = (dailyRate * proratedDays);
            }
            
            const amountFormatted = amount.toLocaleString('en-US', { style: 'currency', currency: 'PHP' });
            const mrrFormatted = mrr.toLocaleString('en-US', { style: 'currency', currency: 'PHP' });

            summaryDiv.style.display = 'block';
            summaryText.innerHTML = `You will be charged an initial prorated amount of <strong>${amountFormatted}</strong> today for <strong>${proratedDays} days</strong> of service to align your account. Your standard recurring billing of <strong>${mrrFormatted}</strong> will begin on <strong>${nextBillingDateStr}</strong>.`;
        }

        document.getElementById('billing_cycle_preference').addEventListener('change', updateProrationPreview);
        
        // Initial call
        updateProrationPreview();
    </script>
</body>
</html>
