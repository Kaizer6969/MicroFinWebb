<?php
/**
 * Lazy Billing Resolver
 * Simulates a cron job by checking and processing pending tenant subscription 
 * charges when an admin accesses the dashboard.
 *
 * Uses system_settings to track next_billing_date per tenant (no schema changes to tenants table).
 */
function resolve_tenant_billing($pdo) {
    if (!$pdo) return;

    $today = date('Y-m-d');

    // 1. Find all active tenants with an MRR > 0
    $stmt = $pdo->prepare("
        SELECT t.tenant_id, t.plan_tier, t.mrr
        FROM tenants t
        WHERE t.deleted_at IS NULL 
          AND t.status = 'Active' 
          AND t.mrr > 0
    ");
    $stmt->execute();
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($tenants)) return;

    foreach ($tenants as $t) {
        $tenant_id = $t['tenant_id'];

        try {
            // Check the next_billing_date from system_settings
            $nbd_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = 'next_billing_date' LIMIT 1");
            $nbd_stmt->execute([$tenant_id]);
            $next_billing_date = $nbd_stmt->fetchColumn();

            // If no billing date set yet, skip this tenant (billing not initialized via setup_billing)
            if (!$next_billing_date) continue;

            // Only process if the billing date is today or in the past
            if ($next_billing_date > $today) continue;

            $pdo->beginTransaction();

            $current_mrr = (float) $t['mrr'];

            // A. Get default payment method (if any)
            $pm_stmt = $pdo->prepare("SELECT card_brand, last_four_digits FROM tenant_billing_payment_methods WHERE tenant_id = ? AND is_default = 1 LIMIT 1");
            $pm_stmt->execute([$tenant_id]);
            $pm = $pm_stmt->fetch(PDO::FETCH_ASSOC);

            $payment_method_desc = 'System Auto-Billing';
            if ($pm) {
                $payment_method_desc = $pm['card_brand'] . ' ending in ' . $pm['last_four_digits'];
            }

            // B. Check if already billed for this period (prevent double-billing)
            $dup_stmt = $pdo->prepare("
                SELECT COUNT(*) FROM payments 
                WHERE tenant_id = ? 
                  AND payment_reference LIKE 'SUB-%' 
                  AND DATE(payment_date) = ?
            ");
            $dup_stmt->execute([$tenant_id, $next_billing_date]);
            if ((int)$dup_stmt->fetchColumn() > 0) {
                // Already billed, just advance the date
                $this_skip = true;
            } else {
                $this_skip = false;
            }

            // C. Insert Simulated Payment + Invoice
            if (!$this_skip && $current_mrr > 0) {
                $ref = 'SUB-' . strtoupper(substr(md5($tenant_id . time() . rand()), 0, 10));
                
                $ins_pay = $pdo->prepare("
                    INSERT INTO payments (tenant_id, payment_amount, payment_status, payment_date, payment_reference, payment_method)
                    VALUES (?, ?, 'Posted', ?, ?, ?)
                ");
                $payment_timestamp = $next_billing_date . ' 00:00:00';
                $ins_pay->execute([$tenant_id, $current_mrr, $payment_timestamp, $ref, $payment_method_desc]);

                // Also record in tenant_billing_invoices for super admin revenue tracking
                $inv_number = 'INV-' . date('Ymd', strtotime($next_billing_date)) . '-' . strtoupper(substr(uniqid(), -4));
                $period_start = $next_billing_date;
                $period_end_dt = new DateTime($next_billing_date);
                $period_end_dt->modify('+1 month');
                $period_end_dt->modify('-1 day');
                $period_end = $period_end_dt->format('Y-m-d');

                $inv_stmt = $pdo->prepare("
                    INSERT INTO tenant_billing_invoices
                    (tenant_id, invoice_number, amount, billing_period_start, billing_period_end, due_date, status, paid_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'Paid', NOW())
                ");
                $inv_stmt->execute([$tenant_id, $inv_number, $current_mrr, $period_start, $period_end, $period_start]);
            }

            // D. Compute next billing date based on billing_anchor_date preference
            $pref_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = 'billing_anchor_date' LIMIT 1");
            $pref_stmt->execute([$tenant_id]);
            $anchor = $pref_stmt->fetchColumn() ?: '1';

            $old_next = new DateTime($next_billing_date);
            $old_next->modify('+1 month');
            
            if ($anchor === '1') {
                $new_next = $old_next->format('Y-m-01');
            } elseif ($anchor === '15') {
                $new_next = $old_next->format('Y-m-15');
            } else {
                $new_next = $old_next->format('Y-m-d');
            }

            // E. Update next_billing_date in system_settings
            $upd = $pdo->prepare("
                INSERT INTO system_settings (tenant_id, setting_key, setting_value, setting_category, data_type) 
                VALUES (?, 'next_billing_date', ?, 'Billing', 'String') 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $upd->execute([$tenant_id, $new_next]);

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Lazy billing resolver error for tenant {$tenant_id}: " . $e->getMessage());
        }
    }
}
