-- Create Library Settings table
USE iit_shelf_prereg;

CREATE TABLE IF NOT EXISTS Library_Settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
);

-- Insert default library contact information
INSERT INTO Library_Settings (setting_key, setting_value) VALUES
    ('library_email', 'library@nstu.edu.bd'),
    ('library_phone', '+880 1234-567890'),
    ('library_hours', 'Mon-Fri: 9:00 AM - 5:00 PM'),
    ('library_location', 'Central Library, NSTU Campus')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value);

-- Verify insertion
SELECT * FROM Library_Settings;
