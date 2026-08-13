USE mim_sfa;

CREATE TABLE delivery_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  delivery_number VARCHAR(60) NOT NULL UNIQUE,
  order_id BIGINT UNSIGNED NOT NULL,
  sales_id BIGINT UNSIGNED NOT NULL,
  outlet_id BIGINT UNSIGNED NOT NULL,
  status ENUM('DRAFT','DELIVERED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  delivered_at DATETIME NULL,
  recipient_name VARCHAR(150) NULL,
  notes VARCHAR(500) NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (sales_id) REFERENCES sales(id),
  FOREIGN KEY (outlet_id) REFERENCES outlets(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_delivery_order(order_id),
  INDEX idx_delivery_sales_date(sales_id, created_at)
);

CREATE TABLE delivery_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  delivery_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  qty DECIMAL(15,3) NOT NULL,
  FOREIGN KEY (delivery_id) REFERENCES delivery_documents(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id),
  UNIQUE KEY uq_delivery_product(delivery_id, product_id)
);

CREATE TABLE return_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  return_number VARCHAR(60) NOT NULL UNIQUE,
  delivery_id BIGINT UNSIGNED NULL,
  sales_id BIGINT UNSIGNED NOT NULL,
  outlet_id BIGINT UNSIGNED NULL,
  status ENUM('DRAFT','POSTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  reason VARCHAR(255) NULL,
  notes VARCHAR(500) NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  posted_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (delivery_id) REFERENCES delivery_documents(id),
  FOREIGN KEY (sales_id) REFERENCES sales(id),
  FOREIGN KEY (outlet_id) REFERENCES outlets(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_return_sales_date(sales_id, created_at)
);

CREATE TABLE return_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  return_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  qty DECIMAL(15,3) NOT NULL,
  FOREIGN KEY (return_id) REFERENCES return_documents(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id),
  UNIQUE KEY uq_return_product(return_id, product_id)
);
