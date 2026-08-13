USE mim_sfa;

CREATE TABLE visits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sales_id BIGINT UNSIGNED NOT NULL,
  outlet_id BIGINT UNSIGNED NOT NULL,
  visit_date DATE NOT NULL,
  status ENUM('ACTIVE','COMPLETED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
  checkin_at DATETIME NOT NULL,
  checkout_at DATETIME NULL,
  checkin_latitude DECIMAL(10,7) NOT NULL,
  checkin_longitude DECIMAL(10,7) NOT NULL,
  checkin_accuracy_meters DECIMAL(8,2) NULL,
  distance_meters DECIMAL(10,2) NOT NULL,
  checkin_photo_path VARCHAR(500) NOT NULL,
  mock_location_detected TINYINT(1) NOT NULL DEFAULT 0,
  mock_location_reason VARCHAR(255) NULL,
  checkout_latitude DECIMAL(10,7) NULL,
  checkout_longitude DECIMAL(10,7) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (sales_id) REFERENCES sales(id),
  FOREIGN KEY (outlet_id) REFERENCES outlets(id),
  INDEX idx_visits_sales_date (sales_id, visit_date),
  INDEX idx_visits_outlet_date (outlet_id, visit_date),
  INDEX idx_visits_status (status)
);

CREATE UNIQUE INDEX uq_active_visit_per_sales ON visits (sales_id, status);

CREATE TABLE visit_activities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visit_id BIGINT UNSIGNED NOT NULL,
  activity_type VARCHAR(80) NOT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  INDEX idx_visit_activity (visit_id, activity_type)
);

CREATE TABLE visit_photos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visit_id BIGINT UNSIGNED NOT NULL,
  photo_type ENUM('CHECKIN','ACTIVITY','CHECKOUT') NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  captured_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE
);
