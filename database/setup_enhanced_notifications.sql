-- Expand notification types and add new automated events
USE iit_shelf;

-- Update Notifications table with new notification types
ALTER TABLE Notifications 
MODIFY COLUMN type ENUM(
    'BorrowRequestApproved',
    'BorrowRequestPending',
    'ReturnRequestApproved',
    'ReturnRequestPending',
    'ReservedBookAvailable',
    'ReservationQueueUpdate',
    'ReservationMissed',
    'DueDateReminder',
    'DueDateToday',
    'FineReminder',
    'FineLimitReached',
    'AdditionRequestApproved',
    'PaymentConfirmation',
    'BookAdded',
    'InventoryUpdate',
    'UserTransaction',
    'ReportGenerated',
    'System'
) DEFAULT 'System';

-- ============================================
-- Event: Send Due Date Today Reminders (runs daily at 7 AM)
-- ============================================
DROP EVENT IF EXISTS send_due_date_today_reminders;

DELIMITER //
CREATE EVENT send_due_date_today_reminders
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURDATE(), '07:00:00')
DO
BEGIN
    INSERT INTO Notifications (user_email, message, type, sent_at)
    SELECT DISTINCT
        tr.requester_email,
        CONCAT('Your borrowed book "', b.title, '" is due TODAY (', 
               DATE_FORMAT(at.due_date, '%M %d, %Y'), '). Please return it to avoid late fees.'),
        'DueDateToday',
        NOW()
    FROM Approved_Transactions at
    JOIN Transaction_Requests tr ON at.request_id = tr.request_id
    JOIN Book_Copies bc ON at.copy_id = bc.copy_id
    JOIN Books b ON bc.isbn = b.isbn
    WHERE at.status = 'Borrowed'
    AND at.due_date = CURDATE()
    AND NOT EXISTS (
        SELECT 1 FROM Notifications n
        WHERE n.user_email = tr.requester_email
        AND n.type = 'DueDateToday'
        AND n.message LIKE CONCAT('%', b.title, '%')
        AND DATE(n.sent_at) = CURDATE()
    );
END//
DELIMITER ;

-- ============================================
-- Event: Check Reservation Expiry (runs every 6 hours)
-- ============================================
DROP EVENT IF EXISTS check_reservation_expiry;

DELIMITER //
CREATE EVENT check_reservation_expiry
ON SCHEDULE EVERY 6 HOUR
DO
BEGIN
    -- Notify users who missed their reservation pickup window
    INSERT INTO Notifications (user_email, message, type, sent_at)
    SELECT 
        r.user_email,
        CONCAT('You missed your reservation pickup window for "', b.title, 
               '". The book is now available for others.'),
        'ReservationMissed',
        NOW()
    FROM Reservations r
    JOIN Books b ON r.isbn = b.isbn
    WHERE r.status = 'Active'
    AND r.expires_at IS NOT NULL
    AND r.expires_at < NOW()
    AND NOT EXISTS (
        SELECT 1 FROM Notifications n
        WHERE n.user_email = r.user_email
        AND n.type = 'ReservationMissed'
        AND n.message LIKE CONCAT('%', b.title, '%')
        AND n.sent_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    );
    
    -- Mark expired reservations as Expired
    UPDATE Reservations
    SET status = 'Expired'
    WHERE status = 'Active'
    AND expires_at IS NOT NULL
    AND expires_at < NOW();
END//
DELIMITER ;

-- ============================================
-- Event: Check Fine Limits (runs daily at 6 AM)
-- ============================================
DROP EVENT IF EXISTS check_fine_limits;

DELIMITER //
CREATE EVENT check_fine_limits
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURDATE(), '06:00:00')
DO
BEGIN
    -- Notify users whose unpaid fines exceed 200 TK
    INSERT INTO Notifications (user_email, message, type, sent_at)
    SELECT 
        user_email,
        CONCAT('Your total unpaid fines (', SUM(amount), ' TK) have exceeded the limit. ',
               'Please pay your fines to continue borrowing books.'),
        'FineLimitReached',
        NOW()
    FROM Fines
    WHERE paid = 0
    GROUP BY user_email
    HAVING SUM(amount) >= 200
    AND NOT EXISTS (
        SELECT 1 FROM Notifications n
        WHERE n.user_email = Fines.user_email
        AND n.type = 'FineLimitReached'
        AND n.sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    );
END//
DELIMITER ;

-- Verify all events are created
SELECT 
    event_name,
    interval_value,
    interval_field,
    starts,
    status
FROM information_schema.events
WHERE event_schema = 'iit_shelf'
ORDER BY event_name;
