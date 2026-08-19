-- Settlement integrity hardening
-- Prevents duplicate settlement records for the same salesman/date.
ALTER TABLE settlement_documents
    ADD UNIQUE KEY uq_settlement_sales_date (sales_id, settlement_date);
