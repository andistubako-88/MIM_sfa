USE mim_sfa;

-- Settlement integrity hardening.
-- Idempotent for fresh installs (finance_schema.sql already has the constraint)
-- and for existing installations that still need the database-level guard.
SET @sql = IF(
  (SELECT COUNT(*)
   FROM information_schema.statistics
   WHERE table_schema = DATABASE()
     AND table_name = 'settlement_documents'
     AND index_name = 'uq_settlement_sales_date'
     AND non_unique = 0) = 0,
  'ALTER TABLE settlement_documents ADD UNIQUE KEY uq_settlement_sales_date(sales_id, settlement_date)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
