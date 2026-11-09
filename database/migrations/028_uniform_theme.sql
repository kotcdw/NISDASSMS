-- New look from the school uniform: the school photo becomes the background, and the phone app bar uses the new navy.
UPDATE settings SET `value` = 'school' WHERE `key` = 'bg_mode' AND `value` = 'illustration';
UPDATE settings SET `value` = '#1b2f86' WHERE `key` = 'app_theme_color' AND `value` = '#1f4e8c';
UPDATE settings SET `value` = '#121f5c' WHERE `key` = 'app_theme_color' AND `value` = '#16324f';
