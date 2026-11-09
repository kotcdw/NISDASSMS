-- Clean-up 9 Nov 2026: the old drag-and-drop dashboard (each person's own widget layout) was replaced by the
-- Analytic Dashboard, which needs no saved layout. Nothing reads or writes this table any more.
DROP TABLE IF EXISTS dashboard_layouts;
