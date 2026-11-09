-- NISDAS SMS — Campus app removed (blocks, room booking, repair requests).
-- Rooms & resources (classrooms and what is in them) stays.
ALTER TABLE rooms DROP FOREIGN KEY IF EXISTS fk_room_block;
ALTER TABLE rooms DROP COLUMN IF EXISTS block_id, DROP COLUMN IF EXISTS floor, DROP COLUMN IF EXISTS bookable;
DROP TABLE IF EXISTS room_bookings;
DROP TABLE IF EXISTS maintenance_requests;
DROP TABLE IF EXISTS campus_blocks;
DELETE FROM role_permissions WHERE permission IN ('campus.view', 'campus.manage');
