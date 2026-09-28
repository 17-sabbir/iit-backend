-- Pre-registration Database Setup
-- Run this with root/admin privileges in DBeaver or MariaDB

-- Create separate database for pre-registration
CREATE DATABASE IF NOT EXISTS iit_shelf_prereg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant privileges to iit_user
GRANT ALL PRIVILEGES ON iit_shelf_prereg.* TO 'iit_user'@'localhost' IDENTIFIED BY 'iit_password';
FLUSH PRIVILEGES;

-- Switch to pre-registration database
USE iit_shelf_prereg;

-- Create PreReg_Students table
CREATE TABLE IF NOT EXISTS PreReg_Students (
    email VARCHAR(255) NOT NULL,
    roll VARCHAR(50) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    contact VARCHAR(20),
    session VARCHAR(50),
    PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create PreReg_Teachers table (with designation column)
CREATE TABLE IF NOT EXISTS PreReg_Teachers (
    email VARCHAR(255) NOT NULL,
    designation VARCHAR(100) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    contact VARCHAR(20),
    PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create PreReg_Librarians table
CREATE TABLE IF NOT EXISTS PreReg_Librarians (
    email VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    contact VARCHAR(20),
    PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create PreReg_Directors table
CREATE TABLE IF NOT EXISTS PreReg_Directors (
    email VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    contact VARCHAR(20),
    PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrate existing data from iit_shelf database (if any exists)
INSERT INTO PreReg_Students (email, roll, full_name, contact, session)
SELECT email, roll, full_name, contact, session
FROM iit_shelf.PreReg_Students
ON DUPLICATE KEY UPDATE
    roll = VALUES(roll),
    full_name = VALUES(full_name),
    contact = VALUES(contact),
    session = VALUES(session);

INSERT INTO PreReg_Teachers (email, designation, full_name, contact)
SELECT email, designation, full_name, contact
FROM iit_shelf.PreReg_Teachers
ON DUPLICATE KEY UPDATE
    designation = VALUES(designation),
    full_name = VALUES(full_name),
    contact = VALUES(contact);

INSERT INTO PreReg_Librarians (email, full_name, contact)
SELECT email, full_name, contact
FROM iit_shelf.PreReg_Librarians
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    contact = VALUES(contact);

INSERT INTO PreReg_Directors (email, full_name, contact)
SELECT email, full_name, contact
FROM iit_shelf.PreReg_Directors
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    contact = VALUES(contact);

-- Verify migration
SELECT 'PreReg_Students' as table_name, COUNT(*) as record_count FROM PreReg_Students
UNION ALL
SELECT 'PreReg_Teachers' as table_name, COUNT(*) as record_count FROM PreReg_Teachers
UNION ALL
SELECT 'PreReg_Librarians' as table_name, COUNT(*) as record_count FROM PreReg_Librarians
UNION ALL
SELECT 'PreReg_Directors' as table_name, COUNT(*) as record_count FROM PreReg_Directors;
