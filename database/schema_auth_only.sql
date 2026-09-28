-- ============================================
-- IIT SHELF - Minimal Auth Schema
-- MySQL/MariaDB setup script (simplified for auth only)
-- ============================================

USE iit_shelf;

-- Clear existing tables
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS temp_user_verification;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- =========================================
-- Users & Authentication
-- =========================================

CREATE TABLE users (
  email VARCHAR(150) PRIMARY KEY,
  name VARCHAR(150) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('Student','Teacher','Librarian','Director') NOT NULL DEFAULT 'Student',
  phone VARCHAR(20) DEFAULT '',
  is_active BOOLEAN DEFAULT TRUE,
  email_verified_at DATETIME NULL,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_role (role),
  INDEX idx_verified (email_verified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE temp_user_verification (
  email VARCHAR(150) NOT NULL,
  otp_code VARCHAR(20) NOT NULL,
  purpose ENUM('EmailVerification','PasswordReset') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (email, purpose),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE students (
  email VARCHAR(150) PRIMARY KEY,
  roll VARCHAR(50) NOT NULL UNIQUE,
  department VARCHAR(120) DEFAULT '',
  session VARCHAR(50) DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_roll (roll)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE teachers (
  email VARCHAR(150) PRIMARY KEY,
  designation VARCHAR(120) DEFAULT '',
  department VARCHAR(120) DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- Sample Data
-- =========================================

-- Insert demo verified users (password: 123)
INSERT INTO users (email, name, password_hash, role, email_verified_at) VALUES
('student@iit.edu', 'Tamal Mazumder', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Student', NOW()),
('teacher@iit.edu', 'Md. Eusha Kadir', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Teacher', NOW()),
('librarian@iit.edu', 'Jamal Uddin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Librarian', NOW()),
('director@iit.edu', 'Nizam Uddin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Director', NOW());

-- Success
SELECT 'Auth schema created successfully!' AS Status;
SELECT email, name, role, IF(email_verified_at IS NULL, 'Unverified', 'Verified') AS status FROM users;
