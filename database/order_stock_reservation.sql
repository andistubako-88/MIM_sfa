USE mim_sfa;

CREATE TABLE order_stock_reservations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  stock_location_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  qty DECIMAL(15,3) NOT NULL,
  status ENUM('RESERVED','RELEASED','COMMITTED') NOT NULL DEFAULT 'RESERVED',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (stock_location_id) REFERENCES stock_locations(id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  UNIQUE KEY uq_order_stock_product (order_id, stock_location_id, product_id),
  INDEX idx_reservation_status (stock_location_id, product_id, status)
);
