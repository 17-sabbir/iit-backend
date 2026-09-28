#!/bin/bash

# Pre-registration Database Setup Script
# Creates a separate database for pre-registration tables

DB_HOST="localhost"
DB_USER="iit_user"
DB_PASSWORD="iit_password"
MAIN_DB="iit_shelf"
PREREG_DB="iit_shelf_prereg"

echo "Setting up pre-registration database..."

# Create the new pre-registration database
mysql -h $DB_HOST -u $DB_USER -p$DB_PASSWORD -e "
CREATE DATABASE IF NOT EXISTS $PREREG_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE $PREREG_DB;

-- PreReg_Students table
CREATE TABLE IF NOT EXISTS PreReg_Students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    roll VARCHAR(50) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    contact VARCHAR(20),
    session VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
);

-- PreReg_Teachers table (with designation column)
CREATE TABLE IF NOT EXISTS PreReg_Teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    designation VARCHAR(100) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    contact VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
);

-- PreReg_Librarians table
CREATE TABLE IF NOT EXISTS PreReg_Librarians (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    contact VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
);

-- PreReg_Directors table
CREATE TABLE IF NOT EXISTS PreReg_Directors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    contact VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
);
"

echo "Pre-registration database created successfully at: $PREREG_DB"

# Check if old tables exist in main database and migrate data
echo "Checking for existing pre-registration data in main database..."

mysql -h $DB_HOST -u $DB_USER -p$DB_PASSWORD $MAIN_DB -e "
SELECT COUNT(*) as student_count FROM PreReg_Students;
" 2>/dev/null | tail -n 1 | read COUNT

if [ $COUNT -gt 0 ]; then
    echo "Found $COUNT existing pre-registration records. Migrating to new database..."
    
    # Migrate data
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASSWORD -e "
    INSERT INTO $PREREG_DB.PreReg_Students SELECT * FROM $MAIN_DB.PreReg_Students;
    INSERT INTO $PREREG_DB.PreReg_Teachers SELECT * FROM $MAIN_DB.PreReg_Teachers;
    INSERT INTO $PREREG_DB.PreReg_Librarians SELECT * FROM $MAIN_DB.PreReg_Librarians;
    INSERT INTO $PREREG_DB.PreReg_Directors SELECT * FROM $MAIN_DB.PreReg_Directors;
    "
    
    echo "Data migration completed!"
else
    echo "No existing pre-registration data found in main database."
fi

echo "Setup complete! Pre-registration database is ready."
