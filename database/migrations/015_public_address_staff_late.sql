-- NISDAS SMS — Public web address for SMS links, and the staff late-arrival time
-- * Public web address = https://nisdasms.academy (only when none was saved yet; change it in Fees → Settings)
-- * Staff who sign in after 07:30 are marked late (the Head can change the time on the Staff attendance page)

INSERT IGNORE INTO settings (`key`, `value`) VALUES ('public_url', 'https://nisdasms.academy');
UPDATE settings SET `value` = 'https://nisdasms.academy' WHERE `key` = 'public_url' AND TRIM(`value`) = '';
INSERT IGNORE INTO settings (`key`, `value`) VALUES ('staff_late_after', '07:30');
