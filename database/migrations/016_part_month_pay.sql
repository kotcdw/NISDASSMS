-- NISDAS SMS — Part-month pay
-- Staff who join or leave during a month are paid for the calendar days they worked
-- (basic salary and allowances split by days; the payslip shows "paid for 15 of 30 days").

ALTER TABLE payslips ADD COLUMN days_paid TINYINT UNSIGNED NULL AFTER staff_id;
ALTER TABLE payslips ADD COLUMN days_in_month TINYINT UNSIGNED NULL AFTER days_paid;
