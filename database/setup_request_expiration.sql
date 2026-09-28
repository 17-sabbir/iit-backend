-- Setup automatic expiration of borrow requests after 24 hours
-- This applies to all roles (Student, Teacher, Librarian, Director)

USE iit_shelf;

-- Enable the Event Scheduler (if not already enabled)
SET GLOBAL event_scheduler = ON;

-- Drop the event if it already exists
DROP EVENT IF EXISTS cleanup_expired_borrow_requests;

-- Create an event that runs every hour to clean up expired requests
DELIMITER //
CREATE EVENT cleanup_expired_borrow_requests
ON SCHEDULE EVERY 1 HOUR
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    -- Delete pending borrow requests older than 24 hours
    DELETE FROM Transaction_Requests
    WHERE status = 'Pending'
    AND request_date < DATE_SUB(NOW(), INTERVAL 24 HOUR);
END//
DELIMITER ;

-- Also create a stored procedure for manual cleanup if needed
DROP PROCEDURE IF EXISTS cleanup_expired_requests_now;

DELIMITER //
CREATE PROCEDURE cleanup_expired_requests_now()
BEGIN
    DELETE FROM Transaction_Requests
    WHERE status = 'Pending'
    AND request_date < DATE_SUB(NOW(), INTERVAL 24 HOUR);
    
    SELECT ROW_COUNT() as deleted_requests;
END//
DELIMITER ;

-- Verify the event was created
SELECT 
    event_name,
    event_definition,
    interval_value,
    interval_field,
    status
FROM information_schema.events
WHERE event_schema = 'iit_shelf'
AND event_name = 'cleanup_expired_borrow_requests';

-- Show current pending requests with their age
SELECT 
    request_id,
    isbn,
    requester_email,
    request_date,
    TIMESTAMPDIFF(HOUR, request_date, NOW()) as hours_old,
    CASE 
        WHEN TIMESTAMPDIFF(HOUR, request_date, NOW()) >= 24 THEN 'EXPIRED'
        ELSE 'VALID'
    END as validity_status
FROM Transaction_Requests
WHERE status = 'Pending'
ORDER BY request_date DESC;
