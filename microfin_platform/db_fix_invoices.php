<?php
/**
 * One-time fix: Back-fill missing initial billing invoices for active tenants.
 *
 * Safe to re-run — it skips tenants that already have at least one invoice.
 */
require_once __DIR__ . '/backend/db_connect.php';

echo "<h3>Back-filling missing tenant billing invoices...</h3><pre>\n";

$stmt = $pdo->query("
    SELECT t.tenant_id, t.tenant_name, t.plan_tier, t.mrr, t.created_at, t.next_billing_date
    FROM tenants t
    WHERE t.deleted_at IS NULL
      AND t.status = 'Active'
      AND t.setup_completed = 1
      AND t.mrr > 0
      AND t.tenant_id NOT IN (SELECT DISTINCT tenant_id FROM tenant_billing_invoices)
");
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tenants)) {
    echo "All active tenants already have billing invoices. Nothing to do.\n";
} else {
    foreach ($tenants as $t) {
        $tenant_id   = $t['tenant_id'];
        $tenant_name = $t['tenant_name'];
        $mrr         = (float) $t['mrr'];
        $created_ts  = strtotime($t['created_at']);

        // Determine prorated amount from creation day to next billing date
        $anchor_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = 'billing_anchor_date' LIMIT 1");
        $anchor_stmt->execute([$tenant_id]);
        $anchor = $anchor_stmt->fetchColumn() ?: '1';

        $created_day        = (int) date('j', $created_ts);
        $created_month_days = (int) date('t', $created_ts);

        if ($anchor === '1') {
            $prorated_days = $created_month_days - $created_day + 1;
            $is_full = ($created_day === 1);
        } elseif ($anchor === '15') {
            if ($created_day <= 15) {
                $prorated_days = 15 - $created_day + 1;
                $is_full = ($created_day === 15);
            } else {
                $prorated_days = ($created_month_days - $created_day + 1) + 14;
                $is_full = false;
            }
        } else {
            $prorated_days = 30;
            $is_full = true;
        }

        if ($is_full) {
            $amount = $mrr;
        } else {
            $daily_rate = $mrr / 30;
            $amount = round($daily_rate * $prorated_days, 2);
        }

        $period_start = date('Y-m-d', $created_ts);
        $next_billing = $t['next_billing_date'];
        if (!$next_billing || $next_billing < $period_start) {
            $next_billing = date('Y-m-01', strtotime('+1 month', $created_ts));
        }
        $period_end = date('Y-m-d', strtotime($next_billing . ' -1 day'));
        $invoice_number = 'INV-' . date('Ymd', $created_ts) . '-' . strtoupper(substr(md5($tenant_id), 0, 4));
        $paid_at = date('Y-m-d H:i:s', $created_ts);

        try {
            $ins = $pdo->prepare("
                INSERT INTO tenant_billing_invoices
                (tenant_id, invoice_number, amount, billing_period_start, billing_period_end, due_date, status, paid_at)
                VALUES (?, ?, ?, ?, ?, ?, 'Paid', ?)
            ");
            $ins->execute([$tenant_id, $invoice_number, $amount, $period_start, $period_end, $period_start, $paid_at]);

            echo "✓ {$tenant_name} ({$tenant_id}): Inserted invoice {$invoice_number} for ₱" . number_format($amount, 2) . " ({$prorated_days} days)\n";
        } catch (PDOException $e) {
            echo "✗ {$tenant_name} ({$tenant_id}): " . $e->getMessage() . "\n";
        }
    }
}

echo "\nDone.\n</pre>";
