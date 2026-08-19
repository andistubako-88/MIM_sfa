USE mim_sfa;

INSERT INTO permissions (code, name, module)
SELECT 'finance.manage', 'Manage Finance', 'finance'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='finance.manage');

INSERT INTO permissions (code, name, module)
SELECT 'reports.view', 'View Reports', 'reports'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='reports.view');

INSERT INTO permissions (code, name, module)
SELECT 'reports.export', 'Export Reports', 'reports'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='reports.export');

INSERT INTO permissions (code, name, module)
SELECT 'audit.view', 'View Audit Log', 'audit'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='audit.view');

-- Report Center is strictly Owner-only. Remove any legacy report grants first.
DELETE rp FROM role_permissions rp
JOIN roles r ON r.id=rp.role_id
JOIN permissions p ON p.id=rp.permission_id
WHERE r.code<>'OWNER' AND p.code IN ('reports.view','reports.export');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.code IN ('finance.manage','reports.view','reports.export','audit.view')
WHERE r.code='OWNER'
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=r.id AND rp.permission_id=p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.code='finance.manage'
WHERE r.code IN ('ADMIN','SUPERVISOR','SALES')
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=r.id AND rp.permission_id=p.id);
