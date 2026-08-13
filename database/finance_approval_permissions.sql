USE mim_sfa;

INSERT INTO permissions (code,name,module)
SELECT 'finance.approve','Approve Finance Settlement','finance'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='finance.approve');

INSERT INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.code='finance.approve'
WHERE r.code IN ('OWNER','ADMIN','SUPERVISOR')
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=r.id AND rp.permission_id=p.id);
