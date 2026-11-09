-- NISDAS SMS — Administrator role. Only Super Admins and Administrators see and open Administration
-- (School setup, Users, Roles & security, Parent & student logins, Backups, Phone app, Audit log, Governance & risk).
INSERT INTO roles (role_key, name, description, base_role, kind, is_system, data_scope, require_2fa, idle_minutes, sort_order, is_active)
VALUES ('administrator', 'Administrator', 'Runs the system: school setup, users, roles & security, logins, backups, audit log, governance. Always with 2-step sign-in.',
        'administrator', 'staff', 1, 'all', 1, 15, 2, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- The Administration permissions now belong to the Administrator only (Super Admin always has everything)
DELETE FROM role_permissions
 WHERE permission IN ('setup.manage','users.manage','security.manage','logins.manage','backups.manage','audit.view','governance.view','governance.manage')
   AND role_key <> 'administrator';

INSERT IGNORE INTO role_permissions (role_key, permission) VALUES
('administrator', 'staff'), ('administrator', 'payslips.own'), ('administrator', 'calendar.view'), ('administrator', 'notices.view'),
('administrator', 'setup.manage'), ('administrator', 'users.manage'), ('administrator', 'security.manage'), ('administrator', 'logins.manage'),
('administrator', 'backups.manage'), ('administrator', 'audit.view'), ('administrator', 'governance.view'), ('administrator', 'governance.manage'),
('administrator', 'imports.manage'), ('administrator', 'golive.manage'), ('administrator', 'promotion.manage');
