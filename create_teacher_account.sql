-- Add teacher to pre-registration database and create account
-- Teacher: Md. Eusha Kadir (eusha@nstu.edu.bd)
-- Role: Assistant Professor
-- Password: 123

USE iit_shelf_prereg;

-- Add to PreReg_Teachers table (pre-registration)
INSERT INTO PreReg_Teachers (email, designation, full_name, contact) VALUES
('eusha@nstu.edu.bd', 'Assistant Professor', 'Md. Eusha Kadir', '01521407156')
ON DUPLICATE KEY UPDATE 
    designation = 'Assistant Professor',
    full_name = 'Md. Eusha Kadir',
    contact = '01521407156';

-- Switch to main database
USE iit_shelf;

-- Create the user account in Users table
-- Password '123' hashed using bcrypt (cost 10)
-- Note: The Users table uses 'name' and 'password_hash' columns
INSERT INTO Users (email, name, password_hash, contact, role) VALUES
('eusha@nstu.edu.bd', 'Md. Eusha Kadir', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '01521407156', 'Teacher')
ON DUPLICATE KEY UPDATE 
    name = 'Md. Eusha Kadir',
    password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    contact = '01521407156',
    role = 'Teacher';

-- Verify the account was created
SELECT 'Pre-registration entry:' as status;
SELECT * FROM iit_shelf_prereg.PreReg_Teachers WHERE email = 'eusha@nstu.edu.bd';

SELECT 'User account:' as status;
SELECT email, name, contact, role, created_at FROM Users WHERE email = 'eusha@nstu.edu.bd';
