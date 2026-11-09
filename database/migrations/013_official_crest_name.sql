-- NISDAS SMS — official name and crest (Naylor International S.D.A School).
-- The crest is built into the app (assets/img/crest.png and the browser icons); an uploaded logo still takes priority.
-- The full name is used on screens, printouts and report cards; SMS keep the short name to save credit.

INSERT IGNORE INTO settings (`key`, `value`) VALUES ('school_short_name', 'NISDAS');

UPDATE settings SET `value` = 'Naylor International S.D.A School'
WHERE `key` = 'school_name' AND (`value` = '' OR UPPER(TRIM(`value`)) IN ('NISDAS', 'NISDAS SMS', 'NISDAS SCHOOL'));
