-- ============================================
-- Migration Script: From Current Schema to Team Schema
-- WARNING: This will modify existing data structure
-- BACKUP YOUR DATABASE BEFORE RUNNING THIS!
-- ============================================

USE iit_shelf;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================
-- Step 1: Rename tables to team naming convention
-- =========================================

-- Rename existing tables if they exist
ALTER TABLE temp_user_verification RENAME TO Temp_User_Verification_old;
ALTER TABLE users RENAME TO Users_old;
ALTER TABLE students RENAME TO Students_old;
ALTER TABLE teachers RENAME TO Teachers_old;
ALTER TABLE courses RENAME TO Courses_old;
ALTER TABLE course_prerequisites RENAME TO Course_Prerequisites_old;
ALTER TABLE shelves RENAME TO Shelves_old;
ALTER TABLE books RENAME TO Books_old;
ALTER TABLE book_courses RENAME TO Book_Courses_old;
ALTER TABLE book_copies RENAME TO Book_Copies_old;
ALTER TABLE digital_resources RENAME TO Digital_Resources_old;
ALTER TABLE transaction_requests RENAME TO Transaction_Requests_old;
ALTER TABLE approved_transactions RENAME TO Approved_Transactions_old;
ALTER TABLE reservations RENAME TO Reservations_old;
ALTER TABLE fines RENAME TO Fines_old;
ALTER TABLE payments RENAME TO Payments_old;
ALTER TABLE reports RENAME TO Reports_old;
ALTER TABLE notifications RENAME TO Notifications_old;
ALTER TABLE requests RENAME TO Requests_old;

-- =========================================
-- Step 2: Create new team schema tables
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

CREATE TABLE Shelves (
  shelf_id INT PRIMARY KEY AUTO_INCREMENT,
  total_compartments INT DEFAULT 0,
  total_subcompartments INT DEFAULT 0,
  is_deleted BOOLEAN DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Step 3: Migrate data from old tables to new
-- =========================================

-- Migrate Temp_User_Verification
INSERT INTO Temp_User_Verification (email, otp_code, purpose, created_at, expires_at)
SELECT email, otp_code, purpose, created_at, expires_at
FROM Temp_User_Verification_old;

-- Migrate Users (map phone -> contact)
INSERT INTO Users (email, name, password_hash, role, contact, created_at, last_login)
SELECT email, name, password_hash, role, phone, created_at, last_login
FROM Users_old;

-- Migrate Students
INSERT INTO Students (email, roll, session)
SELECT email, roll, session
FROM Students_old;

-- Migrate Teachers
INSERT INTO Teachers (email, designation)
SELECT email, designation
FROM Teachers_old;

-- Migrate Courses
INSERT INTO Courses (course_id, course_name, semester)
SELECT course_id, course_name, semester
FROM Courses_old;

-- Migrate Course_Prerequisites
INSERT INTO Course_Prerequisites (course_id, prerequisite_course_id)
SELECT course_id, prerequisite_course_id
FROM Course_Prerequisites_old;

-- Migrate Shelves
INSERT INTO Shelves (shelf_id, total_compartments, total_subcompartments, is_deleted)
SELECT shelf_id, total_compartments, total_subcompartments, is_deleted
FROM Shelves_old;

-- Migrate Books (exclude extra fields)
INSERT INTO Books (isbn, title, author, category, publisher, publication_year, edition, description, pic_path)
SELECT isbn, title, author, category, publisher, publication_year, edition, description, pic_path
FROM Books_old;

-- Migrate Book_Courses
INSERT INTO Book_Courses (isbn, course_id)
SELECT isbn, course_id
FROM Book_Courses_old;

-- Migrate Book_Copies
INSERT INTO Book_Copies (copy_id, isbn, shelf_id, compartment_no, subcompartment_no, status, condition_note)
SELECT copy_id, isbn, shelf_id, compartment_no, subcompartment_no, status, condition_note
FROM Book_Copies_old;

-- Migrate Digital_Resources
INSERT INTO Digital_Resources (resource_id, isbn, file_name, file_path, resource_type, uploaded_by, uploaded_at)
SELECT resource_id, isbn, file_name, file_path, resource_type, uploaded_by, uploaded_at
FROM Digital_Resources_old;

-- Migrate Transaction_Requests
INSERT INTO Transaction_Requests (request_id, isbn, requester_email, request_date, status, reviewed_by, reviewed_at)
SELECT request_id, isbn, requester_email, request_date, status, reviewed_by, reviewed_at
FROM Transaction_Requests_old;

-- Migrate Approved_Transactions (need to get requester from Transaction_Requests)
INSERT INTO Approved_Transactions (transaction_id, request_id, copy_id, issue_date, due_date, return_date, status)
SELECT 
    at_old.transaction_id,
    COALESCE(at_old.request_id, 0) as request_id,
    at_old.copy_id,
    at_old.issue_date,
    at_old.due_date,
    at_old.return_date,
    at_old.status
FROM Approved_Transactions_old at_old;

-- Migrate Reservations
INSERT INTO Reservations (reservation_id, isbn, user_email, queue_position, status, created_at, notified_at, expires_at)
SELECT reservation_id, isbn, user_email, queue_position, status, created_at, notified_at, expires_at
FROM Reservations_old;

-- Migrate Fines
INSERT INTO Fines (fine_id, transaction_id, user_email, amount, description, paid, payment_date)
SELECT fine_id, transaction_id, user_email, amount, description, paid, payment_date
FROM Fines_old;

-- Migrate Payments
INSERT INTO Payments (payment_id, amount, status, gateway_txn_id, paid_at)
SELECT payment_id, amount, status, gateway_txn_id, paid_at
FROM Payments_old;

-- Migrate Reports
INSERT INTO Reports (report_id, type, generated_by, generated_at)
SELECT report_id, type, generated_by, generated_at
FROM Reports_old;

-- Migrate Notifications
INSERT INTO Notifications (notification_id, user_email, message, type, sent_at)
SELECT notification_id, user_email, message, type, sent_at
FROM Notifications_old;

-- Migrate Requests
INSERT INTO Requests (request_id, requester_identifier, isbn, title, author, pdf_path, category, publisher, publication_year, edition, description, pic_path, status, approved_by, approved_at)
SELECT request_id, requester_identifier, isbn, title, author, pdf_path, category, publisher, publication_year, edition, description, pic_path, status, approved_by, approved_at
FROM Requests_old;

-- =========================================
-- Step 4: Drop old tables
-- =========================================
DROP TABLE IF EXISTS Temp_User_Verification_old;
DROP TABLE IF EXISTS Students_old;
DROP TABLE IF EXISTS Teachers_old;
DROP TABLE IF EXISTS Users_old;
DROP TABLE IF EXISTS Course_Prerequisites_old;
DROP TABLE IF EXISTS Book_Courses_old;
DROP TABLE IF EXISTS Courses_old;
DROP TABLE IF EXISTS Digital_Resources_old;
DROP TABLE IF EXISTS Transaction_Requests_old;
DROP TABLE IF EXISTS Approved_Transactions_old;
DROP TABLE IF EXISTS Reservations_old;
DROP TABLE IF EXISTS Fines_old;
DROP TABLE IF EXISTS Payments_old;
DROP TABLE IF EXISTS Book_Copies_old;
DROP TABLE IF EXISTS Books_old;
DROP TABLE IF EXISTS Shelves_old;
DROP TABLE IF EXISTS Reports_old;
DROP TABLE IF EXISTS Notifications_old;
DROP TABLE IF EXISTS Requests_old;
DROP TABLE IF EXISTS course_enrollments;
DROP TABLE IF EXISTS transaction_history;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Migration completed successfully!' AS Status;
