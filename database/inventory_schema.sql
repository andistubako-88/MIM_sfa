USE mim_sfa;

CREATE TABLE warehouses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  address TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE stock_locations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  warehouse_id BIGINT UNSIGNED NULL,
  sales_id BIGINT UNSIGNED NULL,
  code VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  location_type ENUM('WAREHOUSE','SALES') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  FOREIGN KEY (sales_id) REFERENCES sales(id),
  INDEX idx_stock_location_type(location_type)
);

CREATE TABLE stock_balances (
  stock_location_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  qty DECIMAL(15,3) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (stock_location_id, product_id),
  FOREIGN KEY (stock_location_id) REFERENCES stock_locations(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE stock_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  movement_number VARCHAR(60) NOT NULL UNIQUE,
  product_id BIGINT UNSIGNED NOT NULL,
  from_location_id BIGINT UNSIGNED NULL,
  to_location_id BIGINT UNSIGNED NULL,
  movement_type ENUM('OPENING','LOADING','SALE','RETURN','ADJUSTMENT','TRANSFER') NOT NULL,
  reference_type VARCHAR(50) NULL,
  reference_id BIGINT UNSIGNED NULL,
  qty DECIMAL(15,3) NOT NULL,
  notes VARCHAR(500) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (from_location_id) REFERENCES stock_locations(id),
  FOREIGN KEY (to_location_id) REFERENCES stock_locations(id),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_stock_movement_product(product_id, created_at),
  INDEX idx_stock_movement_reference(reference_type, reference_id)
);

CREATE TABLE loading_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  loading_number VARCHAR(60) NOT NULL UNIQUE,
  warehouse_location_id BIGINT UNSIGNED NOT NULL,
  sales_location_id BIGINT UNSIGNED NOT NULL,
  status ENUM('DRAFT','POSTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  notes VARCHAR(500) NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  posted_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (warehouse_location_id) REFERENCES stock_locations(id),
  FOREIGN KEY (sales_location_id) REFERENCES stock_locations(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_loading_status(status)
);

CREATE TABLE loading_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  loading_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  qty DECIMAL(15,3) NOT NULL,
  FOREIGN KEY (loading_id) REFERENCES loading_documents(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id),
  UNIQUE KEY uq_loading_product(loading_id, product_id)
);
