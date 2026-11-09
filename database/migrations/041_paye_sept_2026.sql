-- GRA monthly PAYE bands for resident individuals, effective 1 September 2026:
-- first 588 at 0%, next 80 at 5%, next 100 at 10%, next 2,900 at 17.5%, next 16,000 at 25%, next 30,332 at 30%, above 50,000 at 35%.
-- Only replaces the old built-in bands; bands the bursar typed in are kept.
UPDATE settings SET value = '588:0,80:5,100:10,2900:17.5,16000:25,30332:30,0:35'
WHERE `key` = 'paye_bands' AND REPLACE(value, ' ', '') = '490:0,110:5,130:10,3166.67:17.5,16000:25,30520:30,0:35';
