USE mim_sfa;

INSERT INTO permissions (code, name, module)
SELECT 'inventory.view', 'View Inventory', 'inventory'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code = 'inventory.view');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'inventory.view'
WHERE r.code IN ('OWNER','ADMIN','SUPERVISOR','WAREHOUSE','SALES')
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);
