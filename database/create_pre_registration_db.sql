-- Create Pre-Registration Database
-- This database contains pre-approved users who can register in the system
-- Only emails in these tables can create accounts

CREATE DATABASE IF NOT EXISTS pre_registration_database;
USE pre_registration_database;

-- ========================================
-- Table: student_info
-- Pre-registered students
-- ========================================
CREATE TABLE IF NOT EXISTS student_info (
  email VARCHAR(255) NOT NULL PRIMARY KEY,
  roll VARCHAR(15) NOT NULL UNIQUE,
  full_name VARCHAR(100) NOT NULL,
  contact VARCHAR(15),
  session VARCHAR(10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: teacher_info
-- Pre-registered teachers
-- ========================================
CREATE TABLE IF NOT EXISTS teacher_info (
  email VARCHAR(255) NOT NULL PRIMARY KEY,
  designation VARCHAR(100) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  contact VARCHAR(15)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: librarian_info
-- Pre-registered librarians
-- ========================================
CREATE TABLE IF NOT EXISTS librarian_info (
  email VARCHAR(255) NOT NULL PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  contact VARCHAR(15)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: director_info
-- Pre-registered directors
-- ========================================
CREATE TABLE IF NOT EXISTS director_info (
  email VARCHAR(255) NOT NULL PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  contact VARCHAR(15)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Sample Data (FOR TESTING ONLY)
-- Replace with actual institutional data
-- ========================================

-- Sample Students
INSERT INTO student_info (email, roll, full_name, contact, session) VALUES
('student1@iit.edu', 'CS2023001', 'Ahmed Hassan', '+8801712345671', '2023-2024'),
('student2@iit.edu', 'CS2023002', 'Fatima Rahman', '+8801712345672', '2023-2024'),
('student3@iit.edu', 'EE2023001', 'Karim Abdullah', '+8801712345673', '2023-2024'),
('tamal2517@student.nstu.edu.bd', 'CS2021017', 'Tamal Ahmed', '+8801712345678', '2021-2022')
ON DUPLICATE KEY UPDATE email=email;

-- Sample Teachers
INSERT INTO teacher_info (email, designation, full_name, contact) VALUES
('teacher1@iit.edu', 'Associate Professor', 'Dr. Ayesha Khan', '+8801812345671'),
('teacher2@iit.edu', 'Assistant Professor', 'Dr. Mohammad Ali', '+8801812345672'),
('teacher3@iit.edu', 'Professor', 'Dr. Sarah Ahmed', '+8801812345673')
ON DUPLICATE KEY UPDATE email=email;

-- Sample Librarians
INSERT INTO librarian_info (email, full_name, contact) VALUES
('librarian@iit.edu', 'Zainab Hossain', '+8801912345671'),
('librarian2@iit.edu', 'Ibrahim Khan', '+8801912345672')
ON DUPLICATE KEY UPDATE email=email;

-- Sample Directors
INSERT INTO director_info (email, full_name, contact) VALUES
('director@iit.edu', 'Prof. Dr. Rahman Mahmud', '+8801612345671')
ON DUPLICATE KEY UPDATE email=email;

-- ========================================
-- Verification Queries
-- ========================================

-- View all pre-registered users
SELECT 'Student' as role, email, full_name, contact FROM student_info
UNION ALL
SELECT 'Teacher' as role, email, full_name, contact FROM teacher_info
UNION ALL
SELECT 'Librarian' as role, email, full_name, contact FROM librarian_info
UNION ALL
SELECT 'Director' as role, email, full_name, contact FROM director_info;

-- Show counts
SELECT 
  (SELECT COUNT(*) FROM student_info) as students,
  (SELECT COUNT(*) FROM teacher_info) as teachers,
  (SELECT COUNT(*) FROM librarian_info) as librarians,
  (SELECT COUNT(*) FROM director_info) as directors;
