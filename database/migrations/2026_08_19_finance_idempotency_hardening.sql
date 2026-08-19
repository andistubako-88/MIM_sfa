-- MIM SFA finance idempotency hardening
-- Idempotent: safe to run repeatedly on an existing database.
USE mim_sfa;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema=DATABASE() AND table_name='payments' AND column_name='idempotency_key'
    ),
    'SELECT 1',
    'ALTER TABLE payments ADD COLUMN idempotency_key VARCHAR(100) NULL AFTER payment_method'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.statistics
      WHERE table_schema=DATABASE() AND table_name='payments' AND index_name='uq_payments_idempotency_key'
    ),
    'SELECT 1',
    'CREATE UNIQUE INDEX uq_payments_idempotency_key ON payments(idempotency_key)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Existing settlement schema already enforces one settlement per salesman/date.
-- Keep the constraint as the final concurrency guard in addition to the API lock.
SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.statistics
      WHERE table_schema=DATABASE() AND table_name='settlement_documents' AND index_name='uq_settlement_sales_date'
    ),
    'SELECT 1',
    'CREATE UNIQUE INDEX uq_settlement_sales_date ON settlement_documents(sales_id, settlement_date)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
