CREATE DATABASE IF NOT EXISTS maison_aurelle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE maison_aurelle;

CREATE TABLE room_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  tier ENUM('room','suite') NOT NULL DEFAULT 'room',
  description TEXT,
  size_sqm SMALLINT UNSIGNED,
  max_adults TINYINT UNSIGNED NOT NULL DEFAULT 2,
  max_children TINYINT UNSIGNED NOT NULL DEFAULT 1,
  bed_config VARCHAR(80),
  `view` VARCHAR(80),
  base_price DECIMAL(10,2) NOT NULL,
  total_units SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  amenities JSON,
  hero_image VARCHAR(200),
  gallery JSON,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE addons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  category ENUM('dining','spa','transport','experience','bundle') NOT NULL,
  description TEXT,
  price DECIMAL(10,2) NOT NULL,
  pricing_type ENUM('per_stay','per_night','per_person','per_person_per_night') NOT NULL DEFAULT 'per_stay',
  image VARCHAR(200),
  savings_note VARCHAR(120),
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE guests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(40),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (email)
) ENGINE=InnoDB;

CREATE TABLE bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_ref CHAR(11) NOT NULL UNIQUE,
  guest_id INT UNSIGNED NOT NULL,
  room_type_id INT UNSIGNED NOT NULL,
  check_in DATE NOT NULL,
  check_out DATE NOT NULL,
  nights SMALLINT UNSIGNED NOT NULL,
  adults TINYINT UNSIGNED NOT NULL DEFAULT 2,
  children TINYINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('pending','confirmed','cancelled','completed','no_show') NOT NULL DEFAULT 'confirmed',
  payment_status ENUM('unpaid','authorized','paid','refunded') NOT NULL DEFAULT 'unpaid',
  subtotal DECIMAL(10,2) NOT NULL,
  addons_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  service_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  cancellation_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  special_requests TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  cancelled_at TIMESTAMP NULL,
  CONSTRAINT fk_b_guest FOREIGN KEY (guest_id) REFERENCES guests(id),
  CONSTRAINT fk_b_room FOREIGN KEY (room_type_id) REFERENCES room_types(id),
  CONSTRAINT chk_dates CHECK (check_out > check_in),
  INDEX idx_avail (room_type_id, check_in, check_out)
) ENGINE=InnoDB;

CREATE TABLE booking_addons (
  booking_id INT UNSIGNED NOT NULL,
  addon_id INT UNSIGNED NOT NULL,
  quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (booking_id, addon_id),
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (addon_id) REFERENCES addons(id)
) ENGINE=InnoDB;

CREATE TABLE booking_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  event_type ENUM('created','modified','cancelled','inquiry') NOT NULL,
  payload JSON,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE inquiries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  topic VARCHAR(60) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('new','read','replied') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE rate_limits (
  bucket_key VARCHAR(120) NOT NULL PRIMARY KEY,
  hits SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  window_start TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
