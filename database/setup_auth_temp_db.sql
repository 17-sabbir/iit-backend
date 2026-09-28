-- Separate database for temporary authentication data (OTP, verification codes)
-- Created: 2026-01-10

CREATE DATABASE IF NOT EXISTS iit_shelf_auth_temp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE iit_shelf_auth_temp;

-- Temporary user verification table (OTP codes for registration and password reset)
CREATE TABLE IF NOT EXISTS Temp_User_Verification (
    email VARCHAR(150) NOT NULL,
    otp_code VARCHAR(20) NOT NULL,
    purpose ENUM('EmailVerification','PasswordReset') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (email, purpose),
    INDEX idx_created_at (created_at),
    INDEX idx_expires_at (expires_at)
);

-- Create user for this database
-- Grant privileges (run as root or with sufficient privileges)
-- GRANT ALL PRIVILEGES ON iit_shelf_auth_temp.* TO 'iit_user'@'localhost' IDENTIFIED BY 'iit_password';
-- FLUSH PRIVILEGES;
