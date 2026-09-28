-- ============================================
-- Add PDF Uploads Tracking Table
-- This table tracks student PDF uploads for library books
-- ============================================

USE iit_shelf;

-- PDF Uploads Table for tracking student/user uploads
CREATE TABLE IF NOT EXISTS PDF_Uploads (
  upload_id INT PRIMARY KEY AUTO_INCREMENT,
  uploader_email VARCHAR(150) NOT NULL,
  book_isbn VARCHAR(30),
  book_id INT,
  pdf_url VARCHAR(500) NOT NULL,
  upload_type ENUM('Update','New Request') NOT NULL,
  notes TEXT,
  status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_by VARCHAR(150),
  reviewed_at DATETIME,
  rejection_reason TEXT,
  CONSTRAINT fk_pdf_uploader FOREIGN KEY (uploader_email) REFERENCES Users(email) ON DELETE CASCADE,
  CONSTRAINT fk_pdf_isbn FOREIGN KEY (book_isbn) REFERENCES Books(isbn) ON DELETE SET NULL,
  INDEX idx_uploader (uploader_email),
  INDEX idx_status (status),
  INDEX idx_submitted (submitted_at),
  INDEX idx_isbn (book_isbn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
