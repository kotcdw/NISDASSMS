-- NISDAS SMS — simpler Finance pages: plainer names for two accounts in the books.
UPDATE gl_accounts SET name = 'School fund (built up over the years)' WHERE sys_key = 'fund' AND name = 'Accumulated fund';
UPDATE gl_accounts SET name = 'Assets given or paid for by others (PTA, donors, Government)' WHERE sys_key = 'capital_grants' AND name LIKE 'Assets given or funded by others%';
