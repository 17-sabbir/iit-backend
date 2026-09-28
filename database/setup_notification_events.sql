-- Setup automated notification events for due dates and fine reminders
-- These events run periodically to check and send notifications

USE iit_shelf;

-- ============================================
-- Create Notifications Table (if not exists)
-- ============================================
CREATE TABLE IF NOT EXISTS Notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM(
        'BorrowRequestApproved',
        'ReturnRequestApproved',
        'ReservedBookAvailable',
        'ReservationQueueUpdate',
        'DueDateReminder',
        'FineReminder',
        'AdditionRequestApproved',
        'PaymentConfirmation',
        'System'
    ) DEFAULT 'System',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_email (user_email),
    INDEX idx_sent_at (sent_at)
);

-- Update existing table if needed (add missing enum values)
ALTER TABLE Notifications 
MODIFY COLUMN type ENUM(
    'BorrowRequestApproved',
    'ReturnRequestApproved',
    'ReservedBookAvailable',
    'ReservationQueueUpdate',
    'DueDateReminder',
    'FineReminder',
    'AdditionRequestApproved',
    'PaymentConfirmation',
    'System'
) DEFAULT 'System';

-- Enable the Event Scheduler (if not already enabled)
SET GLOBAL event_scheduler = ON;

-- ============================================
-- Event: Send Due Date Reminders (runs daily)
-- ============================================
DROP EVENT IF EXISTS send_due_date_reminders;

DELIMITER //
CREATE EVENT send_due_date_reminders
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURDATE(), '08:00:00')  -- Run at 8 AM daily
DO
BEGIN
    -- Insert notifications for books due within 24 hours
    INSERT INTO Notifications (user_email, message, type, sent_at)
    SELECT DISTINCT
        tr.requester_email,
        CONCAT('Reminder: Your borrowed book "', b.title, '" is due tomorrow (', 
               DATE_FORMAT(at.due_date, '%M %d, %Y'), '). Please return it to avoid late fees.'),
        'DueDateReminder',
        NOW()
    FROM Approved_Transactions at
    JOIN Transaction_Requests tr ON at.request_id = tr.request_id
    JOIN Book_Copies bc ON at.copy_id = bc.copy_id
    JOIN Books b ON bc.isbn = b.isbn
    WHERE at.status = 'Borrowed'
    AND at.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
    AND NOT EXISTS (
        SELECT 1 FROM Notifications n
        WHERE n.user_email = tr.requester_email
        AND n.type = 'DueDateReminder'
        AND n.message LIKE CONCAT('%', b.title, '%')
        AND n.sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    );
END//
DELIMITER ;

-- ============================================
-- Event: Send Fine Reminders (runs every 3 days)
-- ============================================
DROP EVENT IF EXISTS send_fine_reminders;

DELIMITER //
CREATE EVENT send_fine_reminders
ON SCHEDULE EVERY 3 DAY
STARTS TIMESTAMP(CURDATE(), '09:00:00')  -- Run at 9 AM every 3 days
DO
BEGIN
    -- Insert notifications for unpaid fines older than 3 days
    INSERT INTO Notifications (user_email, message, type, sent_at)
    SELECT 
        f.user_email,
        CONCAT('You have an unpaid fine of $', f.amount, '. Reason: ', f.description, 
               '. Please pay at your earliest convenience to avoid further penalties.'),
        'PaymentConfirmation',
        NOW()
    FROM Fines f
    WHERE f.paid = 0
    AND f.created_at <= DATE_SUB(NOW(), INTERVAL 3 DAY)
    AND NOT EXISTS (
        SELECT 1 FROM Notifications n
        WHERE n.user_email = f.user_email
        AND n.type = 'PaymentConfirmation'
        AND n.message LIKE CONCAT('%$', f.amount, '%')
        AND n.sent_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
    );
END//
DELIMITER ;

-- ============================================
-- Event: Cleanup old notifications (monthly)
-- ============================================
DROP EVENT IF EXISTS cleanup_old_notifications;

DELIMITER //
CREATE EVENT cleanup_old_notifications
ON SCHEDULE EVERY 1 MONTH
STARTS TIMESTAMP(CURDATE(), '02:00:00')  -- Run at 2 AM monthly
DO
BEGIN
    -- Delete notifications older than 90 days
    DELETE FROM Notifications
    WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
END//
DELIMITER ;

-- Verify events were created
SELECT 
    event_name,
    event_definition,
    interval_value,
    interval_field,
    starts,
    status
FROM information_schema.events
WHERE event_schema = 'iit_shelf'
AND event_name IN ('send_due_date_reminders', 'send_fine_reminders', 'cleanup_old_notifications');

-- Show current statistics
SELECT 
    COUNT(*) as total_notifications,
    COUNT(DISTINCT user_email) as unique_users,
    type,
    DATE(sent_at) as notification_date
FROM Notifications
WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY type, DATE(sent_at)
ORDER BY notification_date DESC, type;
