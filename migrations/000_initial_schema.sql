-- IIT Shelf initial schema
-- This is the baseline for the migration runner.

CREATE TABLE IF NOT EXISTS Users (
    email VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Student','Teacher','Librarian','Director') NOT NULL DEFAULT 'Student',
    contact VARCHAR(20) DEFAULT NULL,
    profile_image VARCHAR(500) DEFAULT NULL,
    email_verified_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Students (
    email VARCHAR(255) PRIMARY KEY,
    roll VARCHAR(50) NOT NULL UNIQUE,
    session VARCHAR(50) DEFAULT NULL,
    FOREIGN KEY (email) REFERENCES Users(email) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Teachers (
    email VARCHAR(255) PRIMARY KEY,
    designation VARCHAR(120) DEFAULT NULL,
    FOREIGN KEY (email) REFERENCES Users(email) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Courses (
    course_id VARCHAR(50) PRIMARY KEY,
    course_name VARCHAR(255) NOT NULL,
    semester VARCHAR(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Course_Prerequisites (
    course_id VARCHAR(50) NOT NULL,
    prerequisite_course_id VARCHAR(50) NOT NULL,
    PRIMARY KEY (course_id, prerequisite_course_id),
    FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (prerequisite_course_id) REFERENCES Courses(course_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Shelves (
    shelf_id INT AUTO_INCREMENT PRIMARY KEY,
    compartment INT NOT NULL,
    subcompartment INT NOT NULL,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_shelf_location (shelf_id, compartment, subcompartment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Books (
    isbn VARCHAR(30) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    category VARCHAR(120) DEFAULT NULL,
    publisher VARCHAR(255) DEFAULT NULL,
    publication_year YEAR DEFAULT NULL,
    edition VARCHAR(50) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    pic_path VARCHAR(500) DEFAULT NULL,
    INDEX idx_books_title (title),
    INDEX idx_books_author (author),
    INDEX idx_books_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Book_Copies (
    copy_id VARCHAR(50) PRIMARY KEY,
    isbn VARCHAR(30) NOT NULL,
    shelf_id INT DEFAULT NULL,
    compartment_no INT DEFAULT NULL,
    subcompartment_no INT DEFAULT NULL,
    status ENUM('Available','Borrowed','Reserved','Lost','Discarded') NOT NULL DEFAULT 'Available',
    condition_note VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (isbn) REFERENCES Books(isbn) ON DELETE CASCADE,
    FOREIGN KEY (shelf_id) REFERENCES Shelves(shelf_id) ON DELETE SET NULL,
    INDEX idx_copies_isbn_status (isbn, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Digital_Resources (
    resource_id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(30) DEFAULT NULL,
    file_name VARCHAR(255) DEFAULT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    resource_type ENUM('PDF','E-Book','Other') NOT NULL DEFAULT 'PDF',
    edition VARCHAR(50) DEFAULT NULL,
    uploaded_by VARCHAR(255) DEFAULT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (isbn) REFERENCES Books(isbn) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES Users(email) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Book_Courses (
    isbn VARCHAR(30) NOT NULL,
    course_id VARCHAR(50) NOT NULL,
    PRIMARY KEY (isbn, course_id),
    FOREIGN KEY (isbn) REFERENCES Books(isbn) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Transaction_Requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(30) NOT NULL,
    requested_copy_id VARCHAR(50) DEFAULT NULL,
    requester_email VARCHAR(255) NOT NULL,
    request_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
    reviewed_by VARCHAR(255) DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (isbn) REFERENCES Books(isbn),
    FOREIGN KEY (requested_copy_id) REFERENCES Book_Copies(copy_id) ON DELETE SET NULL,
    FOREIGN KEY (requester_email) REFERENCES Users(email) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES Users(email) ON DELETE SET NULL,
    INDEX idx_requests_user_status (requester_email, status),
    INDEX idx_requests_isbn_status (isbn, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Approved_Transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL UNIQUE,
    copy_id VARCHAR(50) NOT NULL,
    issued_by VARCHAR(255) DEFAULT NULL,
    issue_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    due_date DATETIME NOT NULL,
    return_date DATETIME DEFAULT NULL,
    status ENUM('Borrowed','Returned','Overdue','Lost') NOT NULL DEFAULT 'Borrowed',
    FOREIGN KEY (request_id) REFERENCES Transaction_Requests(request_id),
    FOREIGN KEY (copy_id) REFERENCES Book_Copies(copy_id),
    FOREIGN KEY (issued_by) REFERENCES Users(email) ON DELETE SET NULL,
    INDEX idx_transactions_status_due (status, due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Return_Requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    requester_email VARCHAR(255) NOT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending','Processed','Rejected') NOT NULL DEFAULT 'Pending',
    processed_at DATETIME DEFAULT NULL,
    processed_by VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (transaction_id) REFERENCES Approved_Transactions(transaction_id),
    FOREIGN KEY (requester_email) REFERENCES Users(email) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES Users(email) ON DELETE SET NULL,
    INDEX idx_return_requests_status (status),
    INDEX idx_return_requests_user (requester_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(30) NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    queue_position INT NOT NULL,
    status ENUM('Active','Cancelled','Completed') NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notified_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    FOREIGN KEY (isbn) REFERENCES Books(isbn) ON DELETE CASCADE,
    FOREIGN KEY (user_email) REFERENCES Users(email) ON DELETE CASCADE,
    UNIQUE KEY uq_active_reservation (isbn, user_email, status),
    INDEX idx_reservations_queue (isbn, status, queue_position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Fines (
    fine_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT DEFAULT NULL,
    user_email VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    paid TINYINT(1) NOT NULL DEFAULT 0,
    payment_date DATETIME DEFAULT NULL,
    FOREIGN KEY (transaction_id) REFERENCES Approved_Transactions(transaction_id) ON DELETE SET NULL,
    FOREIGN KEY (user_email) REFERENCES Users(email) ON DELETE CASCADE,
    INDEX idx_fines_user_paid (user_email, paid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    fine_id INT DEFAULT NULL,
    user_email VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('Pending','Completed','Failed','Refunded') NOT NULL DEFAULT 'Pending',
    gateway_txn_id VARCHAR(255) DEFAULT NULL,
    paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fine_id) REFERENCES Fines(fine_id) ON DELETE SET NULL,
    FOREIGN KEY (user_email) REFERENCES Users(email) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    requester_identifier VARCHAR(255) NOT NULL,
    isbn VARCHAR(30) DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) DEFAULT NULL,
    category VARCHAR(120) DEFAULT NULL,
    publisher VARCHAR(255) DEFAULT NULL,
    publication_year YEAR DEFAULT NULL,
    edition VARCHAR(50) DEFAULT NULL,
    pdf_path VARCHAR(500) DEFAULT NULL,
    file_name VARCHAR(255) DEFAULT NULL,
    resource_type ENUM('PDF','E-Book','Other') NOT NULL DEFAULT 'PDF',
    description TEXT DEFAULT NULL,
    status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    approved_by VARCHAR(255) DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    FOREIGN KEY (requester_identifier) REFERENCES Users(email) ON DELETE CASCADE,
    FOREIGN KEY (isbn) REFERENCES Books(isbn) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES Users(email) ON DELETE SET NULL,
    INDEX idx_book_requests_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Temp_User_Verification (
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(20) NOT NULL,
    purpose ENUM('EmailVerification','PasswordReset') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (email, purpose),
    INDEX idx_otp_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'System',
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_email) REFERENCES Users(email) ON DELETE CASCADE,
    INDEX idx_notifications_user_date (user_email, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    generated_by VARCHAR(255) DEFAULT NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES Users(email) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
