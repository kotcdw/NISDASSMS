-- NISDAS SMS — one school-wide switch for 2-step sign-in (Roles & security → Sign-in rules).
-- Off while the system is being built and tested; switch it on before go-live
-- (the go-live checklist and the top-bar alerts remind the Super Admin and Administrator).
INSERT INTO settings (`key`, `value`) VALUES ('mfa_enforced', '0')
ON DUPLICATE KEY UPDATE `value` = '0';
