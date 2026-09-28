-- Alternative: Create pre-registration tables in iit_shelf database
-- Use if separate pre_registration_database cannot be created

USE iit_shelf;

-- ========================================
-- Table: PreReg_Students
-- Pre-registered students
-- ========================================
CREATE TABLE IF NOT EXISTS PreReg_Students (
  email VARCHAR(255) NOT NULL PRIMARY KEY,
  roll VARCHAR(15) NOT NULL UNIQUE,
  full_name VARCHAR(100) NOT NULL,
  contact VARCHAR(15),
  session VARCHAR(10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: PreReg_Teachers
-- Pre-registered teachers
-- ========================================
CREATE TABLE IF NOT EXISTS PreReg_Teachers (
  email VARCHAR(255) NOT NULL PRIMARY KEY,
  designation VARCHAR(100) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  contact VARCHAR(15)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: PreReg_Librarians
-- Pre-registered librarians
-- ========================================
CREATE TABLE IF NOT EXISTS PreReg_Librarians (
  email VARCHAR(255) NOT NULL PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  contact VARCHAR(15)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: PreReg_Directors
-- Pre-registered directors
-- ========================================
CREATE TABLE IF NOT EXISTS PreReg_Directors (
  email VARCHAR(255) NOT NULL PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  contact VARCHAR(15)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Sample Data (FOR TESTING)
-- ========================================

-- Sample Students
INSERT INTO PreReg_Students (email, roll, full_name, contact, session) VALUES
('student1@iit.edu', 'CS2023001', 'Ahmed Hassan', '+8801712345671', '2023-2024'),
('student2@iit.edu', 'CS2023002', 'Fatima Rahman', '+8801712345672', '2023-2024'),
('student3@iit.edu', 'EE2023001', 'Karim Abdullah', '+8801712345673', '2023-2024'),
('tamal2517@student.nstu.edu.bd', 'CS2021017', 'Tamal Ahmed', '+8801712345678', '2021-2022')
ON DUPLICATE KEY UPDATE email=email;

-- Sample Teachers
INSERT INTO PreReg_Teachers (email, designation, full_name, contact) VALUES
('teacher1@iit.edu', 'Associate Professor', 'Dr. Ayesha Khan', '+8801812345671'),
('teacher2@iit.edu', 'Assistant Professor', 'Dr. Mohammad Ali', '+8801812345672'),
('teacher3@iit.edu', 'Professor', 'Dr. Sarah Ahmed', '+8801812345673')
ON DUPLICATE KEY UPDATE email=email;

-- Sample Librarians
INSERT INTO PreReg_Librarians (email, full_name, contact) VALUES
('librarian@iit.edu', 'Zainab Hossain', '+8801912345671'),
('librarian2@iit.edu', 'Ibrahim Khan', '+8801912345672')
ON DUPLICATE KEY UPDATE email=email;

-- Sample Directors
INSERT INTO PreReg_Directors (email, full_name, contact) VALUES
('director@iit.edu', 'Prof. Dr. Rahman Mahmud', '+8801612345671')
ON DUPLICATE KEY UPDATE email=email;

-- Verification
SELECT 'Student' as role, email, full_name, contact FROM PreReg_Students
UNION ALL
SELECT 'Teacher' as role, email, full_name, contact FROM PreReg_Teachers
UNION ALL
SELECT 'Librarian' as role, email, full_name, contact FROM PreReg_Librarians
UNION ALL
SELECT 'Director' as role, email, full_name, contact FROM PreReg_Directors;
