-- Mahameru integration-test fixture. Run against an isolated TEST database only.
-- This script intentionally contains assertions that fail loudly when core invariants are broken.

START TRANSACTION;

-- 1) Reservation must never allow a negative physical balance.
SELECT CASE WHEN EXISTS (
  SELECT 1 FROM stock_balances WHERE qty < 0
) THEN 1/0 ELSE 1 END AS stock_non_negative;

-- 2) A committed reservation must have a corresponding SALE stock movement.
SELECT CASE WHEN EXISTS (
  SELECT r.id
  FROM order_stock_reservations r
  LEFT JOIN stock_movements m
    ON m.reference_type = 'ORDER'
   AND m.reference_id = r.order_id
   AND m.product_id = r.product_id
   AND m.movement_type = 'SALE'
  WHERE r.status = 'COMMITTED' AND m.id IS NULL
) THEN 1/0 ELSE 1 END AS committed_reservation_audited;

-- 3) Posted payments must never exceed invoice grand total.
SELECT CASE WHEN EXISTS (
  SELECT i.id
  FROM invoices i
  WHERE i.status <> 'VOID' AND i.paid_total > i.grand_total
) THEN 1/0 ELSE 1 END AS payments_not_over_invoice;

-- 4) Posted returns must never exceed delivered quantity.
SELECT CASE WHEN EXISTS (
  SELECT rd.delivery_id, ri.product_id
  FROM return_documents rd
  JOIN return_items ri ON ri.return_id = rd.id
  JOIN delivery_items di ON di.delivery_id = rd.delivery_id AND di.product_id = ri.product_id
  WHERE rd.status = 'POSTED'
  GROUP BY rd.delivery_id, ri.product_id
  HAVING SUM(ri.qty) > SUM(di.qty)
) THEN 1/0 ELSE 1 END AS returns_not_over_delivery;

ROLLBACK;
