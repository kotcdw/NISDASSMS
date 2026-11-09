-- NISDAS SMS — least privilege review: each built-in role keeps only what its job needs.
-- Only removes permissions from the built-in roles; custom roles and anything the Super Admin added are untouched.

DELETE FROM role_permissions WHERE role_key = 'academic_coordinator'
  AND permission IN ('health.manage', 'health.view', 'library.manage', 'library.view', 'transport.view', 'admission.view', 'documents.manage');

DELETE FROM role_permissions WHERE role_key = 'admissions_officer'
  AND permission IN ('canteen.sell', 'health.view', 'library.view', 'transport.manage', 'transport.view', 'timetable.view');

DELETE FROM role_permissions WHERE role_key = 'bursar'
  AND permission IN ('library.view', 'timetable.view', 'resources.manage', 'resources.view');

DELETE FROM role_permissions WHERE role_key = 'class_teacher'
  AND permission IN ('library.manage', 'transport.view');

DELETE FROM role_permissions WHERE role_key = 'subject_teacher'
  AND permission IN ('transport.view');

DELETE FROM role_permissions WHERE role_key = 'head_teacher'
  AND permission IN ('imports.manage');
