-- ============================================================================
-- IIT SHELF - CIRCULATION & PAYMENT TABLES MIGRATION
-- Incremental Migration - Adds missing tables while preserving existing data
-- Date: January 4, 2026
-- ============================================================================

USE iit_shelf;

-- ============================================================================
-- PHASE 1: CIRCULATION MANAGEMENT TABLES
-- ============================================================================

-- Table: approved_transactions
-- Tracks actual book borrowing, returns, and overdue status
CREATE TABLE IF NOT EXISTS approved_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NULL,
    copy_id VARCHAR(100) NOT NULL,
    user_email VARCHAR(150) NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE NULL,
    status ENUM('Borrowed', 'Returned', 'Overdue', 'Lost') NOT NULL DEFAULT 'Borrowed',
    issued_by VARCHAR(150) NULL COMMENT 'Librarian who issued the book',
    returned_to VARCHAR(150) NULL COMMENT 'Librarian who received the return',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_email (user_email),
    INDEX idx_copy_id (copy_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    INDEX idx_issue_date (issue_date),
    
    FOREIGN KEY (request_id) REFERENCES transaction_requests(request_id) ON DELETE SET NULL,
    FOREIGN KEY (copy_id) REFERENCES book_copies(copy_id) ON DELETE RESTRICT,
    FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE RESTRICT,
    FOREIGN KEY (issued_by) REFERENCES users(email) ON DELETE SET NULL,
    FOREIGN KEY (returned_to) REFERENCES users(email) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks approved book transactions - borrowed, returned, overdue, lost';

-- Table: reservations
-- Book reservation queue system
CREATE TABLE IF NOT EXISTS reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(50) NOT NULL,
    user_email VARCHAR(150) NOT NULL,
    queue_position INT NOT NULL DEFAULT 1,
    status ENUM('Active', 'Cancelled', 'Completed', 'Expired') NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notified_at DATETIME NULL COMMENT 'When user was notified that book is available',
    expires_at DATETIME NULL COMMENT 'Reservation expires if not picked up',
    completed_at DATETIME NULL,
    
    INDEX idx_isbn (isbn),
    INDEX idx_user_email (user_email),
    INDEX idx_status (status),
    INDEX idx_queue_position (queue_position),
    INDEX idx_expires_at (expires_at),
    
    FOREIGN KEY (isbn) REFERENCES books(isbn) ON DELETE CASCADE,
    FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE CASCADE,
    
    UNIQUE KEY unique_active_reservation (isbn, user_email, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Book reservation queue - users can reserve books that are currently borrowed';

-- Table: fines
-- Late return fines and penalties
CREATE TABLE IF NOT EXISTS fines (
    fine_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    user_email VARCHAR(150) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    fine_type ENUM('Late Return', 'Lost Book', 'Damaged Book', 'Other') NOT NULL DEFAULT 'Late Return',
    days_overdue INT NULL,
    description TEXT NULL,
    paid BOOLEAN NOT NULL DEFAULT FALSE,
    payment_date DATETIME NULL,
    waived BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Fine waived by librarian',
    waived_by VARCHAR(150) NULL,
    waived_reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_user_email (user_email),
    INDEX idx_paid (paid),
    INDEX idx_amount (amount),
    
    FOREIGN KEY (transaction_id) REFERENCES approved_transactions(transaction_id) ON DELETE CASCADE,
    FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE RESTRICT,
    FOREIGN KEY (waived_by) REFERENCES users(email) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks fines for late returns, lost books, and damages';


-- ============================================================================
-- PHASE 2: PAYMENT SYSTEM TABLES
-- ============================================================================

-- Table: payments
-- Payment gateway integration for fine payments
CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    user_email VARCHAR(150) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('Cash', 'Card', 'Online', 'Bank Transfer', 'Mobile Banking') NOT NULL DEFAULT 'Cash',
    status ENUM('Pending', 'Completed', 'Failed', 'Refunded', 'Cancelled') NOT NULL DEFAULT 'Pending',
    gateway_txn_id VARCHAR(255) NULL COMMENT 'Transaction ID from payment gateway',
    gateway_name VARCHAR(100) NULL COMMENT 'Payment gateway name (e.g., bKash, Nagad, SSLCommerz)',
    paid_at DATETIME NULL,
    refunded_at DATETIME NULL,
    refund_amount DECIMAL(10, 2) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_email (user_email),
    INDEX idx_status (status),
    INDEX idx_paid_at (paid_at),
    INDEX idx_gateway_txn_id (gateway_txn_id),
    
    FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Payment records for fine payments and other library fees';

-- Table: fine_payment
-- Junction table linking fines to payments (one payment can cover multiple fines)
CREATE TABLE IF NOT EXISTS fine_payment (
    fine_id INT NOT NULL,
    payment_id INT NOT NULL,
    amount_paid DECIMAL(10, 2) NOT NULL COMMENT 'Amount of this payment allocated to this fine',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (fine_id, payment_id),
    INDEX idx_payment_id (payment_id),
    
    FOREIGN KEY (fine_id) REFERENCES fines(fine_id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Links fines to payments - one payment can cover multiple fines';


-- ============================================================================
-- PHASE 3: SYSTEM FEATURE TABLES
-- ============================================================================

-- Table: notifications
-- User notification system
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_email VARCHAR(150) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('DueDateReminder', 'OverdueNotice', 'ReservedBookAvailable', 'PaymentConfirmation', 'FineNotice', 'System', 'Other') NOT NULL DEFAULT 'System',
    related_id INT NULL COMMENT 'ID of related entity (transaction_id, reservation_id, etc.)',
    related_type VARCHAR(50) NULL COMMENT 'Type of related entity',
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    read_at DATETIME NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_email (user_email),
    INDEX idx_type (type),
    INDEX idx_is_read (is_read),
    INDEX idx_sent_at (sent_at),
    
    FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User notifications for due dates, reservations, payments, etc.';

-- Table: reports
-- Generated analytics reports
CREATE TABLE IF NOT EXISTS reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    type ENUM('MostBorrowed', 'Overdue', 'FinesCollected', 'MostRequested', 'UserActivity', 'BookAvailability', 'Custom') NOT NULL,
    date_from DATE NULL,
    date_to DATE NULL,
    generated_by VARCHAR(150) NOT NULL,
    file_path VARCHAR(500) NULL COMMENT 'Path to generated report file (PDF/Excel)',
    report_data JSON NULL COMMENT 'Report data in JSON format',
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_type (type),
    INDEX idx_generated_by (generated_by),
    INDEX idx_generated_at (generated_at),
    
    FOREIGN KEY (generated_by) REFERENCES users(email) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Generated analytics and statistics reports';

-- Table: requests (book acquisition requests)
-- Users can request new books to be added to the library
CREATE TABLE IF NOT EXISTS requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    requester_email VARCHAR(150) NOT NULL,
    isbn VARCHAR(50) NULL,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NULL,
    category VARCHAR(100) NULL,
    publisher VARCHAR(255) NULL,
    publication_year YEAR NULL,
    edition VARCHAR(50) NULL,
    description TEXT NULL,
    pic_path VARCHAR(500) NULL,
    pdf_path VARCHAR(500) NULL COMMENT 'User can attach PDF if available',
    status ENUM('Pending', 'UnderReview', 'Approved', 'Rejected', 'Purchased', 'Added') NOT NULL DEFAULT 'Pending',
    priority ENUM('Low', 'Medium', 'High', 'Urgent') NOT NULL DEFAULT 'Medium',
    approved_by VARCHAR(150) NULL,
    approved_at DATETIME NULL,
    rejection_reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_requester_email (requester_email),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_isbn (isbn),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (requester_email) REFERENCES users(email) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(email) ON DELETE SET NULL,
    FOREIGN KEY (isbn) REFERENCES books(isbn) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Book acquisition requests from users';


-- ============================================================================
-- VERIFICATION
-- ============================================================================

-- Show all tables
SHOW TABLES;

-- Verify new tables structure
DESCRIBE approved_transactions;
DESCRIBE reservations;
DESCRIBE fines;
DESCRIBE payments;
DESCRIBE fine_payment;
DESCRIBE notifications;
DESCRIBE reports;
DESCRIBE requests;

-- Count tables
SELECT 
    COUNT(*) as total_tables,
    (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'iit_shelf') as expected_tables
FROM information_schema.tables 
WHERE table_schema = 'iit_shelf';

-- Show table sizes
SELECT 
    table_name,
    table_rows,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.tables
WHERE table_schema = 'iit_shelf'
ORDER BY table_name;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Current tables: 13 → 21 tables (8 new tables added)
-- New tables:
--   1. approved_transactions (circulation)
--   2. reservations (queue system)
--   3. fines (late fees)
--   4. payments (payment gateway)
--   5. fine_payment (fine-payment link)
--   6. notifications (user alerts)
--   7. reports (analytics)
--   8. requests (book acquisition)
-- ============================================================================
