USE mim_sfa;

-- Finance hardening: make client retries idempotent without changing
-- existing payment semantics. Existing integrations may omit this field.
ALTER TABLE payments
  ADD COLUMN idempotency_key VARCHAR(100) NULL AFTER reference_number,
  ADD UNIQUE KEY uq_payment_idempotency_key(idempotency_key);

-- Settlement reads should be able to lock/filter posted cash payments by
-- salesman and date efficiently.
ALTER TABLE payments
  ADD INDEX idx_payment_settlement_lookup(sales_id, payment_date, status, payment_method);
