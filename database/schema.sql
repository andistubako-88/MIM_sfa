CREATE DATABASE IF NOT EXISTS mim_sfa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mim_sfa;

CREATE TABLE company_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_name VARCHAR(150) NOT NULL DEFAULT 'PT Mahameru Insan Mandiri',
  timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Jakarta',
  operational_start TIME NOT NULL DEFAULT '08:00:00',
  operational_end TIME NOT NULL DEFAULT '21:00:00',
  checkin_radius_meters DECIMAL(8,2) NOT NULL DEFAULT 100.00,
  minimum_visit_minutes INT UNSIGNED NOT NULL DEFAULT 5,
  fake_gps_block_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(100) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  module VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE role_permissions (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE TABLE areas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id BIGINT UNSIGNED NOT NULL,
  username VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE sales (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  employee_code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NULL,
  channel VARCHAR(100) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE sales_area (
  sales_id BIGINT UNSIGNED NOT NULL,
  area_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (sales_id, area_id),
  FOREIGN KEY (sales_id) REFERENCES sales(id) ON DELETE CASCADE,
  FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE
);

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(100) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  old_data JSON NULL,
  new_data JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_entity (entity_type, entity_id),
  INDEX idx_audit_user (user_id),
  INDEX idx_audit_created (created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO company_settings (company_name) VALUES ('PT Mahameru Insan Mandiri');

INSERT INTO roles (code, name, description) VALUES
('OWNER', 'Owner', 'Full management access'),
('ADMIN', 'Administrator', 'System and master data administration'),
('SUPERVISOR', 'Supervisor', 'Sales supervision and approval'),
('SALES', 'Salesman', 'Field sales operations'),
('WAREHOUSE', 'Warehouse', 'Warehouse and stock operations');

INSERT INTO permissions (code, name, module) VALUES
('dashboard.view','View Dashboard','dashboard'),
('users.manage','Manage Users','users'),
('masters.manage','Manage Master Data','masters'),
('sales.manage','Manage Salesmen','sales'),
('outlets.view','View Outlets','outlets'),
('outlets.manage','Manage Outlets','outlets'),
('visits.create','Create Visit','visits'),
('visits.checkout','Checkout Visit','visits'),
('orders.create','Create Order','orders'),
('inventory.manage','Manage Inventory','inventory'),
('reports.view','View Reports','reports'),
('reports.export','Export Reports','reports'),
('audit.view','View Audit Log','audit');
