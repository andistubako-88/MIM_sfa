USE mim_sfa;

-- OWNER receives all current permissions.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'OWNER'
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

-- ADMIN receives operational/master/report permissions, excluding ownership of users/audit by default.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ADMIN' AND p.code IN (
  'dashboard.view','users.manage','masters.manage','sales.manage','outlets.view','outlets.manage',
  'visits.create','visits.checkout','orders.create','inventory.manage','reports.view','reports.export','audit.view'
)
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

-- SUPERVISOR manages sales execution and reporting.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SUPERVISOR' AND p.code IN (
  'dashboard.view','sales.manage','outlets.view','visits.create','visits.checkout','orders.create','reports.view'
)
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

-- SALES gets field execution permissions.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN (
  'dashboard.view','outlets.view','visits.create','visits.checkout','orders.create'
)
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

-- WAREHOUSE gets inventory operations.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'WAREHOUSE' AND p.code IN ('dashboard.view','inventory.manage','reports.view')
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);
