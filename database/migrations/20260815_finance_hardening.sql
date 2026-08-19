USE mim_sfa;

-- Finance hardening: make client retries idempotent without changing
-- existing payment semantics. Existing integrations may omit this field.
--
-- This migration is intentionally idempotent because finance_schema.sql already
-- contains the hardened column/index for fresh installations, while existing
-- production databases may still need the ALTER statements.
SET @sql = IF(
  (SELECT COUNT(*)
   FROM information_schema.columns
   WHERE table_schema = DATABASE()
     AND table_name = 'payments'
     AND column_name = 'idempotency_key') = 0,
  'ALTER TABLE payments ADD COLUMN idempotency_key VARCHAR(100) NULL AFTER payment_method',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Accept either legacy hardened index name or the canonical fresh-schema name.
-- Never create a second unique index over the same idempotency column.
SET @sql = IF(
  (SELECT COUNT(*)
   FROM information_schema.statistics
   WHERE table_schema = DATABASE()
     AND table_name = 'payments'
     AND index_name IN ('uq_payments_idempotency_key','uq_payment_idempotency_key')
     AND non_unique = 0) = 0,
  'ALTER TABLE payments ADD UNIQUE KEY uq_payments_idempotency_key(idempotency_key)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Settlement reads should be able to lock/filter posted cash payments by
-- salesman and date efficiently.
SET @sql = IF(
  (SELECT COUNT(*)
   FROM information_schema.statistics
   WHERE table_schema = DATABASE()
     AND table_name = 'payments'
     AND index_name = 'idx_payment_settlement_lookup') = 0,
  'ALTER TABLE payments ADD INDEX idx_payment_settlement_lookup(sales_id, payment_date, status, payment_method)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
