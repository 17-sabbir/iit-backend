-- ================================
-- ITSHELF COMPLETE DATABASE SCHEMA
-- Updated: January 8, 2026
-- ================================

-- 1. USERS & ROLES
CREATE TABLE users (
    email VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Student','Teacher','Librarian','Director') NOT NULL,
    contact VARCHAR(15),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME,
    email_verified TINYINT(1) DEFAULT 0,
    email_verified_at DATETIME
);

CREATE TABLE students (
    email VARCHAR(255) PRIMARY KEY,
    roll VARCHAR(50) UNIQUE NOT NULL,
    session VARCHAR(10),
    FOREIGN KEY (email) REFERENCES users(email)
);

CREATE TABLE teachers (
    email VARCHAR(255) PRIMARY KEY,
    designation VARCHAR(100),
    FOREIGN KEY (email) REFERENCES users(email)
);

CREATE TABLE librarians (
    email VARCHAR(255) PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    contact VARCHAR(15),
    FOREIGN KEY (email) REFERENCES users(email)
);

CREATE TABLE directors (
    email VARCHAR(255) PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    contact VARCHAR(15),
    FOREIGN KEY (email) REFERENCES users(email)
);

-- 2. COURSES
CREATE TABLE courses (
    course_id VARCHAR(50) PRIMARY KEY,
    course_name VARCHAR(255) NOT NULL,
    semester VARCHAR(50)
);

CREATE TABLE course_prerequisites (
    course_id VARCHAR(50),
    prerequisite_course_id VARCHAR(50),
    PRIMARY KEY (course_id, prerequisite_course_id),
    FOREIGN KEY (course_id) REFERENCES courses(course_id),
    FOREIGN KEY (prerequisite_course_id) REFERENCES courses(course_id)
);

-- 3. BOOKS & STORAGE
CREATE TABLE books (
    isbn VARCHAR(20) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255),
    category VARCHAR(100),
    publisher VARCHAR(255),
    publication_year YEAR(4),
    edition VARCHAR(50),
    description TEXT,
    pic_path VARCHAR(255)
);

CREATE TABLE shelves (
    shelf_id INT AUTO_INCREMENT PRIMARY KEY,
    total_compartments INT NOT NULL,
    total_subcompartments INT NOT NULL,
    is_deleted TINYINT(1) DEFAULT 0
);

CREATE TABLE book_copies (
    copy_id VARCHAR(50) PRIMARY KEY,
    isbn VARCHAR(20) NOT NULL,
    shelf_id INT NOT NULL,
    compartment_no INT,
    subcompartment_no INT,
    status ENUM('Available','Borrowed','Reserved','Lost','Discarded') DEFAULT 'Available',
    condition_note VARCHAR(255),
    FOREIGN KEY (isbn) REFERENCES books(isbn),
    FOREIGN KEY (shelf_id) REFERENCES shelves(shelf_id)
);

CREATE TABLE digital_resources (
    resource_id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(20),
    file_name VARCHAR(255),
    file_path VARCHAR(255),
    resource_type ENUM('PDF','E-Book','Other') DEFAULT 'PDF',
    edition VARCHAR(50),
    uploaded_by VARCHAR(255),
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (isbn) REFERENCES books(isbn),
    FOREIGN KEY (uploaded_by) REFERENCES users(email)
);

CREATE TABLE book_courses (
    isbn VARCHAR(20),
    course_id VARCHAR(50),
    PRIMARY KEY (isbn, course_id),
    FOREIGN KEY (isbn) REFERENCES books(isbn),
    FOREIGN KEY (course_id) REFERENCES courses(course_id)
);

-- 4. TRANSACTIONS
CREATE TABLE transaction_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(20),
    requested_copy_id VARCHAR(50),
    requester_email VARCHAR(255),
    request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    reviewed_by VARCHAR(255),
    reviewed_at DATETIME,
    FOREIGN KEY (isbn) REFERENCES books(isbn),
    FOREIGN KEY (requested_copy_id) REFERENCES book_copies(copy_id),
    FOREIGN KEY (requester_email) REFERENCES users(email),
    FOREIGN KEY (reviewed_by) REFERENCES users(email)
);

CREATE TABLE approved_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNIQUE,
    copy_id VARCHAR(50),
    issued_by VARCHAR(255),
    issue_date DATE,
    due_date DATE,
    return_date DATE,
    status ENUM('Borrowed','Returned','Overdue','Lost'),
    FOREIGN KEY (request_id) REFERENCES transaction_requests(request_id),
    FOREIGN KEY (copy_id) REFERENCES book_copies(copy_id),
    FOREIGN KEY (issued_by) REFERENCES users(email)
);

-- **NEW: RETURN REQUESTS**
-- Tracks user-initiated return requests that require librarian approval
CREATE TABLE return_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    requester_email VARCHAR(255) NOT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending','Processed','Rejected') DEFAULT 'Pending',
    processed_at DATETIME,
    processed_by VARCHAR(255),
    INDEX idx_rr_transaction (transaction_id),
    INDEX idx_rr_status (status),
    INDEX idx_rr_requester (requester_email),
    FOREIGN KEY (transaction_id) REFERENCES approved_transactions(transaction_id),
    FOREIGN KEY (requester_email) REFERENCES users(email),
    FOREIGN KEY (processed_by) REFERENCES users(email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. RESERVATIONS
CREATE TABLE reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(20),
    user_email VARCHAR(255),
    queue_position INT,
    status ENUM('Active','Cancelled','Completed') DEFAULT 'Active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notified_at DATETIME,
    expires_at DATETIME,
    FOREIGN KEY (isbn) REFERENCES books(isbn),
    FOREIGN KEY (user_email) REFERENCES users(email)
);

-- 6. FINES & PAYMENTS
CREATE TABLE fines (
    fine_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT,
    user_email VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL,
    description VARCHAR(255),
    paid TINYINT(1) DEFAULT 0,
    payment_date DATETIME,
    FOREIGN KEY (transaction_id) REFERENCES approved_transactions(transaction_id),
    FOREIGN KEY (user_email) REFERENCES users(email)
);

CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    fine_id INT,
    user_email VARCHAR(255),
    amount DECIMAL(10,2),
    status ENUM('Pending','Completed','Failed','Refunded'),
    gateway_txn_id VARCHAR(255),
    paid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fine_id) REFERENCES fines(fine_id),
    FOREIGN KEY (user_email) REFERENCES users(email)
);

CREATE TABLE fine_payment (
    fine_id INT,
    payment_id INT,
    PRIMARY KEY (fine_id, payment_id),
    FOREIGN KEY (fine_id) REFERENCES fines(fine_id),
    FOREIGN KEY (payment_id) REFERENCES payments(payment_id)
);

-- 7. UPLOAD REQUESTS
CREATE TABLE requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    requester_identifier VARCHAR(255),
    isbn VARCHAR(20),
    title VARCHAR(255),
    author VARCHAR(255),
    publisher VARCHAR(255),
    publication_year YEAR(4),
    edition VARCHAR(50),
    pdf_path VARCHAR(255),
    file_name VARCHAR(255),
    resource_type ENUM('PDF','E-Book','Other') DEFAULT 'PDF',
    description TEXT,
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    approved_by VARCHAR(255),
    approved_at DATETIME,
    FOREIGN KEY (requester_identifier) REFERENCES users(email),
    FOREIGN KEY (approved_by) REFERENCES users(email)
);

-- 8. AUTH & OTP
CREATE TABLE otp_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    user_data TEXT,
    verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at DATETIME,
    expires_at DATETIME NOT NULL
);

CREATE TABLE temp_user_verification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    verified TINYINT(1) DEFAULT 0,
    name VARCHAR(255),
    role ENUM('Student','Teacher','Librarian','Director') DEFAULT 'Student',
    password_hash VARCHAR(255),
    full_name VARCHAR(100),
    contact VARCHAR(15),
    roll VARCHAR(15),
    session VARCHAR(10),
    designation VARCHAR(100),
    purpose ENUM('EmailVerification','PasswordReset') DEFAULT 'EmailVerification',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME
);

-- 9. NOTIFICATIONS & REPORTS
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_email VARCHAR(255),
    message TEXT,
    type ENUM('DueDateReminder','ReservedBookAvailable','PaymentConfirmation','System'),
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_email) REFERENCES users(email)
);

CREATE TABLE reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('MostBorrowed','Overdue','FinesCollected','MostRequested'),
    generated_by VARCHAR(255),
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(email)
);

-- ================================
-- SUMMARY OF CHANGES
-- ================================
-- 1. Added return_requests table to track user-initiated return requests
--    - Users must explicitly request to return books
--    - Librarians approve return requests before processing
--    - Includes indexes for performance optimization
