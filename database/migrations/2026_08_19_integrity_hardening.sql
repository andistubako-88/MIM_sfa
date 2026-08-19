-- MIM SFA integrity hardening
-- Adds missing order-view permission and supporting indexes without changing existing business data.
USE mim_sfa;

INSERT INTO permissions(code,name,module)
SELECT 'orders.view','View Orders','orders'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='orders.view');

INSERT INTO permissions(code,name,module)
SELECT 'orders.approve','Approve Orders','orders'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='orders.approve');

-- OWNER/ADMIN/SUPERVISOR can view orders; approval remains restricted to roles already
-- configured by the application's approval permission seed.
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id
FROM roles r CROSS JOIN permissions p
WHERE r.code IN ('OWNER','ADMIN','SUPERVISOR') AND p.code='orders.view';

-- Sales need order visibility for their own orders; endpoint-level ownership filtering
-- remains enforced in api/order.php.
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id
FROM roles r CROSS JOIN permissions p
WHERE r.code='SALES' AND p.code='orders.view';

CREATE INDEX idx_visits_sales_status ON visits(sales_id,status);
CREATE INDEX idx_orders_visit ON orders(visit_id);
CREATE INDEX idx_orders_sales_status ON orders(sales_id,status);
