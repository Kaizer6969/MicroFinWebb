-- Fix loans that should be marked as Fully Paid
-- This updates any loans with remaining_balance = 0 to status 'Fully Paid'

UPDATE loans 
SET loan_status = 'Fully Paid',
    updated_at = NOW()
WHERE remaining_balance <= 0 
  AND loan_status NOT IN ('Fully Paid', 'Cancelled', 'Rejected');

-- Show the updated loans
SELECT loan_id, loan_number, client_id, remaining_balance, loan_status 
FROM loans 
WHERE loan_status = 'Fully Paid' 
ORDER BY updated_at DESC 
LIMIT 10;
