USE mim_sfa;

-- Finance transaction management is required by invoice, payment, and
-- settlement endpoints. Keep this permission explicit so those APIs do not
-- silently become unreachable after a fresh database installation.
INSERT INTO permissions (code,name,module)
SELECT 'finance.manage','Manage Finance Transactions','finance'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='finance.manage');

INSERT INTO permissions (code,name,module)
SELECT 'finance.approve','Approve Finance Settlement','finance'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='finance.approve');

INSERT INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.code='finance.manage'
WHERE r.code IN ('OWNER','ADMIN','SUPERVISOR')
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=r.id AND rp.permission_id=p.id);

INSERT INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.code='finance.approve'
WHERE r.code IN ('OWNER','ADMIN','SUPERVISOR')
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=r.id AND rp.permission_id=p.id);
