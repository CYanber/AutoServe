CREATE DATABASE IF NOT EXISTS autoserve_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE autoserve_db;

CREATE TABLE IF NOT EXISTS `Owner` (
    owner_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30),
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `Mechanic` (
    mechanic_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('MECHANIC','MANAGER') NOT NULL DEFAULT 'MECHANIC',
    phone VARCHAR(30)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ServiceType` (
    service_type_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    est_minutes INT NOT NULL,
    base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    CONSTRAINT chk_service_minutes CHECK (est_minutes > 0),
    CONSTRAINT chk_service_price CHECK (base_price >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `Vehicle` (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    plate_no VARCHAR(20) NOT NULL UNIQUE,
    make VARCHAR(60) NOT NULL,
    model VARCHAR(60) NOT NULL,
    `year` INT NOT NULL,
    color VARCHAR(40),
    mileage_km INT DEFAULT 0,
    CONSTRAINT fk_vehicle_owner FOREIGN KEY (owner_id)
      REFERENCES `Owner`(owner_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_vehicle_year CHECK (`year` BETWEEN 1980 AND 2100),
    CONSTRAINT chk_vehicle_mileage CHECK (mileage_km >= 0),
    INDEX idx_vehicle_owner (owner_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ServiceSlot` (
    slot_id INT AUTO_INCREMENT PRIMARY KEY,
    mechanic_id INT NOT NULL,
    slot_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    capacity TINYINT NOT NULL DEFAULT 1,
    status ENUM('OPEN','FULL','CLOSED') NOT NULL DEFAULT 'OPEN',
    CONSTRAINT fk_slot_mechanic FOREIGN KEY (mechanic_id)
      REFERENCES `Mechanic`(mechanic_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_slot_time CHECK (end_time > start_time),
    CONSTRAINT chk_slot_capacity CHECK (capacity BETWEEN 1 AND 3),
    CONSTRAINT uq_mechanic_slot UNIQUE (mechanic_id, slot_date, start_time),
    INDEX idx_slot_date_status (slot_date, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ServiceBooking` (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    slot_id INT NOT NULL,
    service_type_id INT NOT NULL,
    status ENUM('PENDING','CONFIRMED','IN_PROGRESS','COMPLETED','CANCELLED')
      NOT NULL DEFAULT 'PENDING',
    notes TEXT,
    booked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    CONSTRAINT fk_booking_owner FOREIGN KEY (owner_id)
      REFERENCES `Owner`(owner_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_booking_vehicle FOREIGN KEY (vehicle_id)
      REFERENCES `Vehicle`(vehicle_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_booking_slot FOREIGN KEY (slot_id)
      REFERENCES `ServiceSlot`(slot_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_booking_service FOREIGN KEY (service_type_id)
      REFERENCES `ServiceType`(service_type_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_booking_owner_status (owner_id, status),
    INDEX idx_booking_slot_status (slot_id, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `BookingAudit` (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    old_status VARCHAR(20),
    new_status VARCHAR(20) NOT NULL,
    changed_by VARCHAR(150) NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    remarks VARCHAR(255),
    CONSTRAINT fk_audit_booking FOREIGN KEY (booking_id)
      REFERENCES `ServiceBooking`(booking_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_audit_booking (booking_id)
) ENGINE=InnoDB;
