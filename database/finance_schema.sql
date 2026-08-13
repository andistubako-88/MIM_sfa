USE mim_sfa;

CREATE TABLE invoices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(60) NOT NULL UNIQUE,
  delivery_id BIGINT UNSIGNED NOT NULL,
  outlet_id BIGINT UNSIGNED NOT NULL,
  sales_id BIGINT UNSIGNED NOT NULL,
  status ENUM('OPEN','PARTIAL','PAID','VOID') NOT NULL DEFAULT 'OPEN',
  invoice_date DATE NOT NULL,
  due_date DATE NULL,
  subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
  discount_total DECIMAL(15,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(15,2) NOT NULL DEFAULT 0,
  paid_total DECIMAL(15,2) NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (delivery_id) REFERENCES delivery_documents(id),
  FOREIGN KEY (outlet_id) REFERENCES outlets(id),
  FOREIGN KEY (sales_id) REFERENCES sales(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_invoice_outlet(outlet_id, invoice_date),
  INDEX idx_invoice_sales(sales_id, invoice_date),
  INDEX idx_invoice_status(status)
);

CREATE TABLE payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_number VARCHAR(60) NOT NULL UNIQUE,
  invoice_id BIGINT UNSIGNED NOT NULL,
  outlet_id BIGINT UNSIGNED NOT NULL,
  sales_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  payment_method ENUM('CASH','TRANSFER','GIRO','OTHER') NOT NULL,
  payment_date DATE NOT NULL,
  reference_number VARCHAR(100) NULL,
  notes VARCHAR(500) NULL,
  status ENUM('PENDING','POSTED','CANCELLED') NOT NULL DEFAULT 'POSTED',
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id),
  FOREIGN KEY (outlet_id) REFERENCES outlets(id),
  FOREIGN KEY (sales_id) REFERENCES sales(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_payment_invoice(invoice_id),
  INDEX idx_payment_sales(invoice_id, payment_date)
);

CREATE TABLE settlement_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  settlement_number VARCHAR(60) NOT NULL UNIQUE,
  sales_id BIGINT UNSIGNED NOT NULL,
  settlement_date DATE NOT NULL,
  expected_cash DECIMAL(15,2) NOT NULL DEFAULT 0,
  submitted_cash DECIMAL(15,2) NOT NULL DEFAULT 0,
  difference DECIMAL(15,2) NOT NULL DEFAULT 0,
  status ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED') NOT NULL DEFAULT 'DRAFT',
  notes VARCHAR(500) NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sales_id) REFERENCES sales(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uq_settlement_sales_date(sales_id, settlement_date),
  INDEX idx_settlement_status(status)
);
