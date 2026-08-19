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

SET @sql = (
  SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='visits' AND index_name='idx_visits_sales_status'),
    'SELECT 1',
    'CREATE INDEX idx_visits_sales_status ON visits(sales_id,status)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='orders' AND index_name='idx_orders_sales_status'),
    'SELECT 1',
    'CREATE INDEX idx_orders_sales_status ON orders(sales_id,status)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
