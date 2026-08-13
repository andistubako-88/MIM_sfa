USE mim_sfa;

INSERT INTO permissions (code, name, module)
SELECT 'orders.view', 'View Orders', 'orders'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code = 'orders.view');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('orders.view', 'orders.create')
WHERE r.code IN ('OWNER','ADMIN','SUPERVISOR','SALES')
AND NOT EXISTS (
  SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
);
