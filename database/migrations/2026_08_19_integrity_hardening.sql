-- MIM SFA integrity hardening
-- Idempotent: safe to run more than once on the same database.
USE mim_sfa;

INSERT INTO permissions(code,name,module)
SELECT 'orders.view','View Orders','orders'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='orders.view');

INSERT INTO permissions(code,name,module)
SELECT 'orders.approve','Approve Orders','orders'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='orders.approve');

INSERT INTO permissions(code,name,module)
SELECT 'visits.checkout','Checkout Visit','visits'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='visits.checkout');

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id
FROM roles r CROSS JOIN permissions p
WHERE r.code IN ('OWNER','ADMIN','SUPERVISOR') AND p.code='orders.view';

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id
FROM roles r CROSS JOIN permissions p
WHERE r.code='SALES' AND p.code='orders.view';

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id
FROM roles r CROSS JOIN permissions p
WHERE r.code IN ('OWNER','ADMIN','SUPERVISOR','SALES') AND p.code='visits.checkout';

-- Approval is a management action. Sales and warehouse users must not receive it.
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id
FROM roles r CROSS JOIN permissions p
WHERE r.code IN ('OWNER','ADMIN','SUPERVISOR') AND p.code='orders.approve';

-- The base visit/order schemas also declare these indexes for fresh installs.
-- These guarded ALTERs keep the migration safe for existing installations.
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema=DATABASE() AND table_name='visits'
     AND index_name='idx_visits_sales_status') = 0,
  'ALTER TABLE visits ADD INDEX idx_visits_sales_status (sales_id,status)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema=DATABASE() AND table_name='orders'
     AND index_name='idx_orders_sales_status') = 0,
  'ALTER TABLE orders ADD INDEX idx_orders_sales_status (sales_id,status)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
