USE mim_sfa;

CREATE TABLE attendance (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sales_id BIGINT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  status ENUM('PRESENT','LATE','ABSENT','LEAVE') NOT NULL DEFAULT 'PRESENT',
  checkin_at DATETIME NULL,
  checkout_at DATETIME NULL,
  checkin_latitude DECIMAL(10,7) NULL,
  checkin_longitude DECIMAL(10,7) NULL,
  checkin_accuracy_meters DECIMAL(8,2) NULL,
  notes VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (sales_id) REFERENCES sales(id),
  UNIQUE KEY uq_attendance_sales_date (sales_id, attendance_date),
  INDEX idx_attendance_date_status (attendance_date, status)
);

CREATE TABLE plan_calls (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sales_id BIGINT UNSIGNED NOT NULL,
  plan_date DATE NOT NULL,
  outlet_id BIGINT UNSIGNED NOT NULL,
  sequence_no INT UNSIGNED NOT NULL,
  status ENUM('PLANNED','VISITED','MISSED','CANCELLED') NOT NULL DEFAULT 'PLANNED',
  notes VARCHAR(500) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (sales_id) REFERENCES sales(id),
  FOREIGN KEY (outlet_id) REFERENCES outlets(id),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uq_plan_sales_date_seq (sales_id, plan_date, sequence_no),
  UNIQUE KEY uq_plan_sales_date_outlet (sales_id, plan_date, outlet_id),
  INDEX idx_plan_date_status (plan_date, status)
);

CREATE TABLE route_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  route_type ENUM('ODD','EVEN','DAILY','CUSTOM') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE route_template_outlets (
  route_template_id BIGINT UNSIGNED NOT NULL,
  outlet_id BIGINT UNSIGNED NOT NULL,
  sequence_no INT UNSIGNED NOT NULL,
  PRIMARY KEY (route_template_id, outlet_id),
  FOREIGN KEY (route_template_id) REFERENCES route_templates(id) ON DELETE CASCADE,
  FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE CASCADE
);
