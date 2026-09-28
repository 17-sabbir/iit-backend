-- ============================================
-- IIT SHELF - Refined Database Schema
-- Complete MySQL/MariaDB setup script
-- ============================================

-- Drop existing database if needed (use with caution!)
-- DROP DATABASE IF EXISTS iit_shelf;
-- CREATE DATABASE iit_shelf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE iit_shelf;

-- Clear existing tables (for fresh setup)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS temp_user_verification;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS course_prerequisites;
DROP TABLE IF EXISTS course_enrollments;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS book_courses;
DROP TABLE IF EXISTS digital_resources;
DROP TABLE IF EXISTS transaction_requests;
DROP TABLE IF EXISTS approved_transactions;
DROP TABLE IF EXISTS reservations;
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
-- Users & Authentication
-- =========================================

CREATE TABLE users (
  email VARCHAR(150) PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('Student','Teacher','Librarian','Director') NOT NULL DEFAULT 'Student',
  phone VARCHAR(20),
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
  department VARCHAR(120),
  session VARCHAR(50),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_roll (roll),
  CONSTRAINT fk_students_email FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE teachers (
  email VARCHAR(150) PRIMARY KEY,
  designation VARCHAR(120),
  department VARCHAR(120),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_teachers_email FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- Courses
-- =========================================

CREATE TABLE courses (
  course_id VARCHAR(50) PRIMARY KEY,
  course_name VARCHAR(200) NOT NULL,
  semester ENUM('11','12','21','22','31','32','42') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_semester (semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE course_prerequisites (
  course_id VARCHAR(50) NOT NULL,
  prerequisite_course_id VARCHAR(50) NOT NULL,
  PRIMARY KEY (course_id, prerequisite_course_id),
  CONSTRAINT fk_pr_course FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
  CONSTRAINT fk_pr_prereq FOREIGN KEY (prerequisite_course_id) REFERENCES courses(course_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE course_enrollments (
  email VARCHAR(150) NOT NULL,
  course_id VARCHAR(50) NOT NULL,
  role_in_course ENUM('Student','Instructor','TA') DEFAULT 'Student',
  PRIMARY KEY (email, course_id),
  CONSTRAINT fk_enroll_user FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE,
  CONSTRAINT fk_enroll_course FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- Shelves & Books
-- =========================================

CREATE TABLE shelves (
  shelf_id INT PRIMARY KEY AUTO_INCREMENT,
  total_compartments INT DEFAULT 0,
  total_subcompartments INT DEFAULT 0,
  capacity INT DEFAULT 0,
  current_count INT DEFAULT 0,
  is_deleted BOOLEAN DEFAULT FALSE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE books (
  isbn VARCHAR(30) PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  author VARCHAR(255) NOT NULL,
  category VARCHAR(120),
  publisher VARCHAR(255),
  publication_year YEAR,
  edition VARCHAR(50),
  description TEXT,
  pic_path VARCHAR(500),
  language VARCHAR(50) DEFAULT 'English',
  keywords VARCHAR(500),
  copies_total INT DEFAULT 0,
  copies_available INT DEFAULT 0,
  is_deleted BOOLEAN DEFAULT FALSE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_title (title),
  INDEX idx_author (author),
  FULLTEXT INDEX idx_books_ft (title, author, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE book_courses (
  isbn VARCHAR(30) NOT NULL,
  course_id VARCHAR(50) NOT NULL,
  PRIMARY KEY (isbn, course_id),
  CONSTRAINT fk_bc_isbn FOREIGN KEY (isbn) REFERENCES books(isbn) ON DELETE CASCADE,
  CONSTRAINT fk_bc_course FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE book_copies (
  copy_id VARCHAR(60) PRIMARY KEY,
  isbn VARCHAR(30) NOT NULL,
  shelf_id INT,
  compartment_no INT,
  subcompartment_no INT,
  status ENUM('Available','Borrowed','Reserved','Lost','Discarded') DEFAULT 'Available',
  condition_note VARCHAR(255),
  is_deleted BOOLEAN DEFAULT FALSE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_copy_isbn FOREIGN KEY (isbn) REFERENCES books(isbn) ON DELETE CASCADE,
  CONSTRAINT fk_copy_shelf FOREIGN KEY (shelf_id) REFERENCES shelves(shelf_id) ON DELETE SET NULL,
  INDEX idx_copy_status (status, isbn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE digital_resources (
  resource_id INT PRIMARY KEY AUTO_INCREMENT,
  isbn VARCHAR(30),
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  resource_type ENUM('PDF','E-Book','Other') NOT NULL,
  mime_type VARCHAR(100),
  size_bytes BIGINT,
  checksum VARCHAR(128),
  visibility ENUM('Public','RoleRestricted','Private') DEFAULT 'Public',
  uploaded_by VARCHAR(150),
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_deleted BOOLEAN DEFAULT FALSE,
  CONSTRAINT fk_dr_isbn FOREIGN KEY (isbn) REFERENCES books(isbn) ON DELETE SET NULL,
  INDEX idx_dr_isbn_type (isbn, resource_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- Transactions & Reservations
-- =========================================

CREATE TABLE transaction_requests (
  request_id INT PRIMARY KEY AUTO_INCREMENT,
  isbn VARCHAR(30) NOT NULL,
  requester_email VARCHAR(150) NOT NULL,
  request_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  reviewed_by VARCHAR(150),
  reviewed_at DATETIME,
  notes TEXT,
  CONSTRAINT fk_tr_isbn FOREIGN KEY (isbn) REFERENCES books(isbn) ON DELETE CASCADE,
  CONSTRAINT fk_tr_requester FOREIGN KEY (requester_email) REFERENCES users(email) ON DELETE CASCADE,
  CONSTRAINT fk_tr_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(email) ON DELETE SET NULL,
  INDEX idx_tr_status (status),
  INDEX idx_tr_requester (requester_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE approved_transactions (
  transaction_id INT PRIMARY KEY AUTO_INCREMENT,
  request_id INT NOT NULL,
  isbn VARCHAR(30) NOT NULL,
  copy_id VARCHAR(60) NOT NULL,
  issued_by VARCHAR(150) NOT NULL,
  issue_date DATE NOT NULL,
  due_date DATE NOT NULL,
  return_date DATE,
  status ENUM('Borrowed','Returned','Overdue','Lost') DEFAULT 'Borrowed',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_at_request FOREIGN KEY (request_id) REFERENCES transaction_requests(request_id) ON DELETE CASCADE,
  CONSTRAINT fk_at_isbn FOREIGN KEY (isbn) REFERENCES books(isbn) ON DELETE CASCADE,
  CONSTRAINT fk_at_copy FOREIGN KEY (copy_id) REFERENCES book_copies(copy_id) ON DELETE CASCADE,
  CONSTRAINT fk_at_issuer FOREIGN KEY (issued_by) REFERENCES users(email) ON DELETE SET NULL,
  INDEX idx_at_user_status (issued_by, status),
  INDEX idx_at_due (due_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reservations (
  reservation_id INT PRIMARY KEY AUTO_INCREMENT,
  isbn VARCHAR(30) NOT NULL,
  user_email VARCHAR(150) NOT NULL,
  queue_position INT NOT NULL,
  status ENUM('Active','Cancelled','Completed','Expired') DEFAULT 'Active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notified_at DATETIME,
  expires_at DATETIME,
  fulfilled_copy_id VARCHAR(60),
  fulfilled_txn_id INT,
  CONSTRAINT fk_res_isbn FOREIGN KEY (isbn) REFERENCES books(isbn) ON DELETE CASCADE,
  CONSTRAINT fk_res_user FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE CASCADE,
  CONSTRAINT fk_res_copy FOREIGN KEY (fulfilled_copy_id) REFERENCES book_copies(copy_id) ON DELETE SET NULL,
  CONSTRAINT fk_res_txn FOREIGN KEY (fulfilled_txn_id) REFERENCES approved_transactions(transaction_id) ON DELETE SET NULL,
  INDEX idx_res_status (status),
  INDEX idx_res_user (user_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- Fines & Payments
-- =========================================

CREATE TABLE fines (
  fine_id INT PRIMARY KEY AUTO_INCREMENT,
  transaction_id INT NOT NULL,
  user_email VARCHAR(150) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  description VARCHAR(255),
  paid BOOLEAN DEFAULT FALSE,
  payment_date DATETIME,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_fine_txn FOREIGN KEY (transaction_id) REFERENCES approved_transactions(transaction_id) ON DELETE CASCADE,
  CONSTRAINT fk_fine_user FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE CASCADE,
  INDEX idx_fine_user_paid (user_email, paid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  payment_id INT PRIMARY KEY AUTO_INCREMENT,
  fine_id INT NOT NULL,
  user_email VARCHAR(150) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  status ENUM('Pending','Completed','Failed','Refunded') DEFAULT 'Pending',
  payment_method ENUM('Cash','Card','Online','Other') DEFAULT 'Cash',
  currency VARCHAR(10) DEFAULT 'BDT',
  gateway_txn_id VARCHAR(100),
  gateway_payload TEXT,
  paid_at DATETIME,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_fine FOREIGN KEY (fine_id) REFERENCES fines(fine_id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_user FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE CASCADE,
  INDEX idx_pay_user_status (user_email, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- Other tables
-- =========================================

CREATE TABLE requests (
  request_id INT PRIMARY KEY AUTO_INCREMENT,
  requester_identifier VARCHAR(150) NOT NULL,
  isbn VARCHAR(30),
  title VARCHAR(255),
  author VARCHAR(255),
  pdf_path VARCHAR(500),
  description TEXT,
  status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  approved_by VARCHAR(150),
  approved_at DATETIME,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_req_requester FOREIGN KEY (requester_identifier) REFERENCES users(email) ON DELETE CASCADE,
  CONSTRAINT fk_req_isbn FOREIGN KEY (isbn) REFERENCES books(isbn) ON DELETE SET NULL,
  CONSTRAINT fk_req_approver FOREIGN KEY (approved_by) REFERENCES users(email) ON DELETE SET NULL,
  INDEX idx_req_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  notification_id INT PRIMARY KEY AUTO_INCREMENT,
  user_email VARCHAR(150) NULL,
  message TEXT NOT NULL,
  type ENUM('DueDateReminder','ReservedBookAvailable','PaymentConfirmation','System') DEFAULT 'System',
  action_url VARCHAR(500),
  is_read BOOLEAN DEFAULT FALSE,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE CASCADE,
  INDEX idx_notif_user_read (user_email, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports (
  report_id INT PRIMARY KEY AUTO_INCREMENT,
  type ENUM('MostBorrowed','Overdue','FinesCollected','MostRequested') NOT NULL,
  generated_by VARCHAR(150) NOT NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('Generating','Completed','Failed') DEFAULT 'Generating',
  file_path VARCHAR(500),
  filters JSON,
  CONSTRAINT fk_report_user FOREIGN KEY (generated_by) REFERENCES users(email) ON DELETE SET NULL,
  INDEX idx_report_type (type),
  INDEX idx_report_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transaction_history (
  history_id INT PRIMARY KEY AUTO_INCREMENT,
  transaction_type ENUM('Borrow','Return','Reserve','FinePayment','BookAdd','BookRemove','PdfUpload') NOT NULL,
  user_email VARCHAR(150) NOT NULL,
  book_id VARCHAR(30),
  copy_id VARCHAR(60),
  amount DECIMAL(10,2),
  status VARCHAR(50),
  description TEXT,
  performed_by VARCHAR(150),
  transaction_date DATE NOT NULL,
  transaction_time TIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_th_user FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE CASCADE,
  CONSTRAINT fk_th_book FOREIGN KEY (book_id) REFERENCES books(isbn) ON DELETE SET NULL,
  CONSTRAINT fk_th_copy FOREIGN KEY (copy_id) REFERENCES book_copies(copy_id) ON DELETE SET NULL,
  CONSTRAINT fk_th_actor FOREIGN KEY (performed_by) REFERENCES users(email) ON DELETE SET NULL,
  INDEX idx_th_user_date (user_email, transaction_date),
  INDEX idx_th_type (transaction_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- Sample Data
-- =========================================

-- Insert sample users (passwords will be set via registration)
-- Note: These are placeholders; use the registration API to create real accounts
INSERT INTO users (email, name, password_hash, role, email_verified_at) VALUES
('student@iit.edu', 'Tamal Mazumder', '$2y$10$dummy', 'Student', NOW()),
('teacher@iit.edu', 'Md. Eusha Kadir', '$2y$10$dummy', 'Teacher', NOW()),
('librarian@iit.edu', 'Jamal Uddin', '$2y$10$dummy', 'Librarian', NOW()),
('director@iit.edu', 'Nizam Uddin', '$2y$10$dummy', 'Director', NOW());

-- Sample courses
INSERT INTO courses (course_id, course_name, semester) VALUES
('CSE101', 'Introduction to Computer Science', '11'),
('CSE201', 'Data Structures', '21'),
('CSE301', 'Algorithms', '31'),
('CSE401', 'Database Systems', '42');

-- Sample shelves
INSERT INTO shelves (total_compartments, total_subcompartments, capacity) VALUES
(10, 5, 50),
(15, 8, 80);

-- Sample books
INSERT INTO books (isbn, title, author, category, publisher, publication_year, copies_total, copies_available) VALUES
('978-0-13-110362-7', 'Introduction to Algorithms', 'Cormen et al.', 'Computer Science', 'MIT Press', 2009, 5, 5),
('978-0-13-235088-4', 'Clean Code', 'Robert C. Martin', 'Software Engineering', 'Prentice Hall', 2008, 3, 3);

-- =========================================
-- Success message
-- =========================================
SELECT 'Database schema created successfully!' AS Status;
