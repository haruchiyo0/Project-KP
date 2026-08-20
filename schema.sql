-- ====================================================================
-- INDIHOME FIELD - DATABASE SCHEMA (MySQL / MariaDB / phpMyAdmin)
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `indihome_field` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `indihome_field`;

-- 1. TABEL PENGGUNA / AKUN (Users & Role Access)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `nik` VARCHAR(50) NOT NULL UNIQUE,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `email` VARCHAR(100) DEFAULT '',
    `phone` VARCHAR(30) DEFAULT '',
    `role` ENUM('admin', 'teknisi') NOT NULL DEFAULT 'teknisi',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABEL MASTER JENIS PEKERJAAN & TARIF BASE (Work Types)
CREATE TABLE IF NOT EXISTS `work_types` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `base_tariff` INT NOT NULL DEFAULT 125000,
    `description` TEXT,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABEL TRANSAKSI PEKERJAAN (Jobs Record)
CREATE TABLE IF NOT EXISTS `jobs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reporter_name` VARCHAR(150) NOT NULL,
    `reporter_nik` VARCHAR(50) NOT NULL,
    `work_type` VARCHAR(50) NOT NULL,
    `work_order` VARCHAR(100) NOT NULL UNIQUE,
    `no_inet` VARCHAR(100) DEFAULT '',
    `customer_name` VARCHAR(150) NOT NULL,
    `ps_date` DATE NOT NULL,
    `base_amount` INT NOT NULL DEFAULT 125000,
    `status` VARCHAR(30) NOT NULL DEFAULT 'Selesai',
    `description` TEXT,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TABEL RELASI TEKNISI & PEMBAGIAN REKAPAN (Job Technicians)
CREATE TABLE IF NOT EXISTS `job_technicians` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `job_id` INT NOT NULL,
    `technician_name` VARCHAR(150) NOT NULL,
    `technician_nik` VARCHAR(50) NOT NULL,
    `share_amount` INT NOT NULL,
    FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. TABEL MASTER PELANGGAN (Customers)
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `no_inet` VARCHAR(100) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `address` TEXT,
    `phone` VARCHAR(30) DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TABEL RIWAYAT AKTIVITAS SYSTEM (Audit Log Trail)
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `user_name` VARCHAR(150) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. TABEL PENGATURAN KONFIGURASI GLOBAL (Settings)
CREATE TABLE IF NOT EXISTS `app_settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` TEXT NOT NULL,
    `description` VARCHAR(255) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- SEED DATA AWAL (Initial Admin & Technicians & Master Tarif)
-- ====================================================================

-- Admin & Default Technicians (Password: admin123 / teknisi123)
INSERT INTO `users` (`name`, `nik`, `username`, `password_hash`, `role`, `status`) VALUES
('Dewi Larasati', 'ADM-001', 'admin', '$2y$10$wBqXb0D4E7h9h7yJz4k1ee/7y9K.8Qz8Qz8Qz8Qz8Qz8Qz8Qz8Qz8', 'admin', 'active'),
('Andi Pratama', 'TK-24017', 'andi', '$2y$10$wBqXb0D4E7h9h7yJz4k1ee/7y9K.8Qz8Qz8Qz8Qz8Qz8Qz8Qz8Qz8', 'teknisi', 'active'),
('Rizky Saputra', 'TK-24022', 'rizky', '$2y$10$wBqXb0D4E7h9h7yJz4k1ee/7y9K.8Qz8Qz8Qz8Qz8Qz8Qz8Qz8Qz8', 'teknisi', 'active')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Master Jenis Pekerjaan Default
INSERT INTO `work_types` (`code`, `name`, `base_tariff`, `description`, `is_active`) VALUES
('PDA', 'Pasang Baru PDA', 125000, 'Pekerjaan Pasang Baru PDA', 1),
('IH', 'IndiHome Standard', 125000, 'Pemasangan Layanan IndiHome', 1),
('HSI', 'High Speed Internet', 125000, 'Pemasangan Internet HSI', 1),
('DATIN', 'Data & Internet Corporate', 125000, 'Layanan Datin Korporat', 1),
('MOK', 'Migrasi OK / Perbaikan', 30000, 'Migrasi atau Perbaikan Kabel/Perangkat', 1)
ON DUPLICATE KEY UPDATE `id`=`id`;
