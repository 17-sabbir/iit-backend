-- Safe pre-registration schema setup.
-- No sample users are inserted and existing rows are not overwritten.

-- Create separate database for pre-registration
CREATE DATABASE IF NOT EXISTS iit_shelf_prereg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Switch to pre-registration database
USE iit_shelf_prereg;

-- Create PreReg_Students table
CREATE TABLE IF NOT EXISTS PreReg_Students (
    email VARCHAR(255) NOT NULL,
    roll VARCHAR(50) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    contact VARCHAR(20),
    session VARCHAR(50),
    PRIMARY KEY (email),
    UNIQUE KEY uq_prereg_student_roll (roll)
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

-- Verify table creation without displaying private preregistered identities.
SELECT 'PreReg_Students' as table_name, COUNT(*) as record_count FROM PreReg_Students
UNION ALL
SELECT 'PreReg_Teachers' as table_name, COUNT(*) as record_count FROM PreReg_Teachers
UNION ALL
SELECT 'PreReg_Librarians' as table_name, COUNT(*) as record_count FROM PreReg_Librarians
UNION ALL
SELECT 'PreReg_Directors' as table_name, COUNT(*) as record_count FROM PreReg_Directors;
