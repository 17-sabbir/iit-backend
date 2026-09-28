-- ============================================
-- IIT SHELF - Team Database Schema Migration
-- Aligned with Web Team Schema (January 2026)
-- ============================================

-- Drop existing database if needed (use with caution!)
-- DROP DATABASE IF EXISTS iit_shelf;
-- CREATE DATABASE iit_shelf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE iit_shelf;

-- Clear existing tables (for fresh setup)
SET FOREIGN_KEY_CHECKS = 0;

-- Drop all old tables
DROP TABLE IF EXISTS temp_user_verification;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS course_prerequisites;
DROP TABLE IF EXISTS course_enrollments;
DROP TABLE IF EXISTS book_courses;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS digital_resources;
DROP TABLE IF EXISTS transaction_requests;
DROP TABLE IF EXISTS approved_transactions;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS fine_payment;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS fines;
DROP TABLE IF EXISTS book_copies;
DROP TABLE IF EXISTS books;
DROP TABLE IF EXISTS shelves;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS requests;
DROP TABLE IF EXISTS transaction_history;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================
-- AUTHENTICATION & USERS
-- =========================================

CREATE TABLE Temp_User_Verification (
  email VARCHAR(150) NOT NULL,
  otp_code VARCHAR(20) NOT NULL,
  purpose ENUM('EmailVerification','PasswordReset') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (email, purpose),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Users (
  email VARCHAR(150) PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('Student','Teacher','Librarian','Director') NOT NULL DEFAULT 'Student',
  contact VARCHAR(20),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login DATETIME NULL,
  INDEX idx_role (role),
  INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Students (
  email VARCHAR(150) PRIMARY KEY,
  roll VARCHAR(50) NOT NULL UNIQUE,
  session VARCHAR(50),
  CONSTRAINT fk_students_users FOREIGN KEY (email) REFERENCES Users(email) ON DELETE CASCADE,
  INDEX idx_roll (roll)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Teachers (
  email VARCHAR(150) PRIMARY KEY,
  designation VARCHAR(120),
  CONSTRAINT fk_teachers_users FOREIGN KEY (email) REFERENCES Users(email) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- COURSES
-- =========================================

CREATE TABLE Courses (
  course_id VARCHAR(50) PRIMARY KEY,
  course_name VARCHAR(200) NOT NULL,
  semester VARCHAR(50) NOT NULL,
  INDEX idx_semester (semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Course_Prerequisites (
  course_id VARCHAR(50) NOT NULL,
  prerequisite_course_id VARCHAR(50) NOT NULL,
  PRIMARY KEY (course_id, prerequisite_course_id),
  CONSTRAINT fk_cp_course FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE CASCADE,
  CONSTRAINT fk_cp_prereq FOREIGN KEY (prerequisite_course_id) REFERENCES Courses(course_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- SHELVES
-- =========================================

CREATE TABLE Shelves (
  shelf_id INT PRIMARY KEY AUTO_INCREMENT,
  total_compartments INT DEFAULT 0,
  total_subcompartments INT DEFAULT 0,
  is_deleted BOOLEAN DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- BOOKS
-- =========================================

CREATE TABLE Books (
  isbn VARCHAR(30) PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  author VARCHAR(255) NOT NULL,
  category VARCHAR(120),
  publisher VARCHAR(255),
  publication_year YEAR,
  edition VARCHAR(50),
  description TEXT,
  pic_path VARCHAR(500),
  INDEX idx_title (title),
  INDEX idx_author (author),
  INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Book_Courses (
  isbn VARCHAR(30) NOT NULL,
  course_id VARCHAR(50) NOT NULL,
  PRIMARY KEY (isbn, course_id),
  CONSTRAINT fk_bc_isbn FOREIGN KEY (isbn) REFERENCES Books(isbn) ON DELETE CASCADE,
  CONSTRAINT fk_bc_course FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Book_Copies (
  copy_id VARCHAR(60) PRIMARY KEY,
  isbn VARCHAR(30) NOT NULL,
  shelf_id INT,
  compartment_no INT,
  subcompartment_no INT,
  status ENUM('Available','Borrowed','Reserved','Lost','Discarded') DEFAULT 'Available',
  condition_note VARCHAR(255),
  CONSTRAINT fk_copy_isbn FOREIGN KEY (isbn) REFERENCES Books(isbn) ON DELETE CASCADE,
  CONSTRAINT fk_copy_shelf FOREIGN KEY (shelf_id) REFERENCES Shelves(shelf_id) ON DELETE SET NULL,
  INDEX idx_copy_status (status),
  INDEX idx_copy_isbn (isbn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- DIGITAL RESOURCES
-- =========================================

CREATE TABLE Digital_Resources (
  resource_id INT PRIMARY KEY AUTO_INCREMENT,
  isbn VARCHAR(30),
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  resource_type ENUM('PDF','E-Book','Other') NOT NULL,
  uploaded_by VARCHAR(150),
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_dr_isbn FOREIGN KEY (isbn) REFERENCES Books(isbn) ON DELETE SET NULL,
  CONSTRAINT fk_dr_uploader FOREIGN KEY (uploaded_by) REFERENCES Users(email) ON DELETE SET NULL,
  INDEX idx_dr_isbn (isbn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- TRANSACTIONS & CIRCULATION
-- =========================================

CREATE TABLE Transaction_Requests (
  request_id INT PRIMARY KEY AUTO_INCREMENT,
  isbn VARCHAR(30) NOT NULL,
  requester_email VARCHAR(150) NOT NULL,
  request_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  reviewed_by VARCHAR(150),
  reviewed_at DATETIME,
  CONSTRAINT fk_tr_isbn FOREIGN KEY (isbn) REFERENCES Books(isbn) ON DELETE CASCADE,
  CONSTRAINT fk_tr_requester FOREIGN KEY (requester_email) REFERENCES Users(email) ON DELETE CASCADE,
  CONSTRAINT fk_tr_reviewer FOREIGN KEY (reviewed_by) REFERENCES Users(email) ON DELETE SET NULL,
  INDEX idx_tr_status (status),
  INDEX idx_tr_requester (requester_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Approved_Transactions (
  transaction_id INT PRIMARY KEY AUTO_INCREMENT,
  request_id INT NOT NULL,
  copy_id VARCHAR(60) NOT NULL,
  issue_date DATE NOT NULL,
  due_date DATE NOT NULL,
  return_date DATE,
  status ENUM('Borrowed','Returned','Overdue','Lost') DEFAULT 'Borrowed',
  CONSTRAINT fk_at_request FOREIGN KEY (request_id) REFERENCES Transaction_Requests(request_id) ON DELETE CASCADE,
  CONSTRAINT fk_at_copy FOREIGN KEY (copy_id) REFERENCES Book_Copies(copy_id) ON DELETE CASCADE,
  INDEX idx_at_status (status),
  INDEX idx_at_due (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Reservations (
  reservation_id INT PRIMARY KEY AUTO_INCREMENT,
  isbn VARCHAR(30) NOT NULL,
  user_email VARCHAR(150) NOT NULL,
  queue_position INT NOT NULL,
  status ENUM('Active','Cancelled','Completed') DEFAULT 'Active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notified_at DATETIME,
  expires_at DATETIME,
  CONSTRAINT fk_res_isbn FOREIGN KEY (isbn) REFERENCES Books(isbn) ON DELETE CASCADE,
  CONSTRAINT fk_res_user FOREIGN KEY (user_email) REFERENCES Users(email) ON DELETE CASCADE,
  INDEX idx_res_status (status),
  INDEX idx_res_user (user_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- FINES & PAYMENTS
-- =========================================

CREATE TABLE Fines (
  fine_id INT PRIMARY KEY AUTO_INCREMENT,
  transaction_id INT NOT NULL,
  user_email VARCHAR(150) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  description VARCHAR(255),
  paid BOOLEAN DEFAULT FALSE,
  payment_date DATETIME,
  CONSTRAINT fk_fine_txn FOREIGN KEY (transaction_id) REFERENCES Approved_Transactions(transaction_id) ON DELETE CASCADE,
  CONSTRAINT fk_fine_user FOREIGN KEY (user_email) REFERENCES Users(email) ON DELETE CASCADE,
  INDEX idx_fine_user (user_email),
  INDEX idx_fine_paid (paid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Payments (
  payment_id INT PRIMARY KEY AUTO_INCREMENT,
  amount DECIMAL(10,2) NOT NULL,
  status ENUM('Pending','Completed','Failed','Refunded') DEFAULT 'Pending',
  gateway_txn_id VARCHAR(100),
  paid_at DATETIME,
  INDEX idx_payment_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fine_payment (
  fine_id INT NOT NULL,
  payment_id INT NOT NULL,
  PRIMARY KEY (fine_id, payment_id),
  CONSTRAINT fk_fp_fine FOREIGN KEY (fine_id) REFERENCES Fines(fine_id) ON DELETE CASCADE,
  CONSTRAINT fk_fp_payment FOREIGN KEY (payment_id) REFERENCES Payments(payment_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- REPORTS & NOTIFICATIONS
-- =========================================

CREATE TABLE Reports (
  report_id INT PRIMARY KEY AUTO_INCREMENT,
  type ENUM('MostBorrowed','Overdue','FinesCollected','MostRequested') NOT NULL,
  generated_by VARCHAR(150) NOT NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_report_user FOREIGN KEY (generated_by) REFERENCES Users(email) ON DELETE CASCADE,
  INDEX idx_report_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Notifications (
  notification_id INT PRIMARY KEY AUTO_INCREMENT,
  user_email VARCHAR(150),
  message TEXT NOT NULL,
  type ENUM('DueDateReminder','ReservedBookAvailable','PaymentConfirmation','System') DEFAULT 'System',
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_email) REFERENCES Users(email) ON DELETE CASCADE,
  INDEX idx_notif_user (user_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- BOOK REQUESTS (from users)
-- =========================================

CREATE TABLE Requests (
  request_id INT PRIMARY KEY AUTO_INCREMENT,
  requester_identifier VARCHAR(150) NOT NULL,
  isbn VARCHAR(30),
  title VARCHAR(255),
  author VARCHAR(255),
  pdf_path VARCHAR(500),
  category VARCHAR(120),
  publisher VARCHAR(255),
  publication_year YEAR,
  edition VARCHAR(50),
  description TEXT,
  pic_path VARCHAR(500),
  status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  approved_by VARCHAR(150),
  approved_at DATETIME,
  CONSTRAINT fk_req_requester FOREIGN KEY (requester_identifier) REFERENCES Users(email) ON DELETE CASCADE,
  CONSTRAINT fk_req_isbn FOREIGN KEY (isbn) REFERENCES Books(isbn) ON DELETE SET NULL,
  CONSTRAINT fk_req_approver FOREIGN KEY (approved_by) REFERENCES Users(email) ON DELETE SET NULL,
  INDEX idx_req_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- SAMPLE DATA FOR TESTING
-- =========================================

-- Sample users (use proper password hashing in production)
INSERT INTO Users (email, name, password_hash, role, contact) VALUES
('student@iit.edu', 'Test Student', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Student', '01712345678'),
('teacher@iit.edu', 'Test Teacher', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Teacher', '01787654321'),
('librarian@iit.edu', 'Test Librarian', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Librarian', '01756789012'),
('director@iit.edu', 'Test Director', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Director', '01798765432');

-- Sample student
INSERT INTO Students (email, roll, session) VALUES
('student@iit.edu', '2021001', '2021-22');

-- Sample teacher
INSERT INTO Teachers (email, designation) VALUES
('teacher@iit.edu', 'Professor');

-- Sample courses
INSERT INTO Courses (course_id, course_name, semester) VALUES
('CSE101', 'Introduction to Programming', '1'),
('CSE201', 'Data Structures', '2'),
('CSE301', 'Algorithms', '3'),
('CSE401', 'Database Systems', '4');

-- Sample shelves
INSERT INTO Shelves (total_compartments, total_subcompartments) VALUES
(10, 5),
(15, 8),
(12, 6);

-- Sample books
INSERT INTO Books (isbn, title, author, category, publisher, publication_year, edition, description) VALUES
('978-0-13-110362-7', 'Introduction to Algorithms', 'Cormen, Leiserson, Rivest, Stein', 'Computer Science', 'MIT Press', 2009, '3rd', 'Comprehensive guide to algorithms'),
('978-0-13-235088-4', 'Clean Code', 'Robert C. Martin', 'Software Engineering', 'Prentice Hall', 2008, '1st', 'A Handbook of Agile Software Craftsmanship'),
('978-0-134-68599-1', 'Operating System Concepts', 'Silberschatz, Galvin, Gagne', 'Computer Science', 'Wiley', 2018, '10th', 'Operating system fundamentals');

-- Sample book copies
INSERT INTO Book_Copies (copy_id, isbn, shelf_id, compartment_no, subcompartment_no, status) VALUES
('978-0-13-110362-7-001', '978-0-13-110362-7', 1, 1, 1, 'Available'),
('978-0-13-110362-7-002', '978-0-13-110362-7', 1, 1, 2, 'Available'),
('978-0-13-235088-4-001', '978-0-13-235088-4', 1, 2, 1, 'Available'),
('978-0-134-68599-1-001', '978-0-134-68599-1', 2, 1, 1, 'Available');

-- Sample book-course associations
INSERT INTO Book_Courses (isbn, course_id) VALUES
('978-0-13-110362-7', 'CSE301'),
('978-0-134-68599-1', 'CSE401');

-- =========================================
-- SUCCESS MESSAGE
-- =========================================
SELECT 'Team database schema migration completed successfully!' AS Status;
SELECT 'Total tables created: 19' AS Info;
