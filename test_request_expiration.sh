#!/bin/bash
# Test script for 24-hour borrow request expiration

echo "==================================="
echo "Borrow Request Expiration Test"
echo "==================================="
echo ""

# Database credentials
DB_USER="iit_user"
DB_PASS="iit_password"
DB_NAME="iit_shelf"

echo "1. Checking current pending requests..."
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "
SELECT 
    request_id,
    isbn,
    requester_email,
    request_date,
    TIMESTAMPDIFF(HOUR, request_date, NOW()) as hours_old,
    TIMESTAMPDIFF(MINUTE, NOW(), DATE_ADD(request_date, INTERVAL 24 HOUR)) as minutes_until_expiry,
    CASE 
        WHEN TIMESTAMPDIFF(HOUR, request_date, NOW()) >= 24 THEN 'EXPIRED'
        ELSE 'VALID'
    END as status
FROM Transaction_Requests
WHERE status = 'Pending'
ORDER BY request_date DESC;
"

echo ""
echo "2. Checking event scheduler status..."
mysql -u root -p -e "
SELECT 
    event_name,
    status,
    last_executed,
    interval_value,
    interval_field
FROM information_schema.events
WHERE event_schema = '$DB_NAME'
AND event_name = 'cleanup_expired_borrow_requests';
"

echo ""
echo "3. Creating a test expired request (backdated by 25 hours)..."
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "
-- First, check if we can insert a backdated request
INSERT INTO Transaction_Requests (isbn, requester_email, request_date, status)
VALUES ('978-0-201-53174-9', 'eusha@nstu.edu.bd', DATE_SUB(NOW(), INTERVAL 25 HOUR), 'Pending')
ON DUPLICATE KEY UPDATE request_date = request_date;
"

echo ""
echo "4. Verifying the test request was created..."
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "
SELECT 
    request_id,
    isbn,
    requester_email,
    request_date,
    TIMESTAMPDIFF(HOUR, request_date, NOW()) as hours_old,
    CASE 
        WHEN TIMESTAMPDIFF(HOUR, request_date, NOW()) >= 24 THEN 'EXPIRED'
        ELSE 'VALID'
    END as status
FROM Transaction_Requests
WHERE status = 'Pending'
ORDER BY request_date DESC
LIMIT 5;
"

echo ""
echo "5. Running manual cleanup to remove expired requests..."
mysql -u root -p -e "USE $DB_NAME; CALL cleanup_expired_requests_now();"

echo ""
echo "6. Verifying expired requests were removed..."
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "
SELECT 
    request_id,
    isbn,
    requester_email,
    request_date,
    TIMESTAMPDIFF(HOUR, request_date, NOW()) as hours_old,
    CASE 
        WHEN TIMESTAMPDIFF(HOUR, request_date, NOW()) >= 24 THEN 'EXPIRED'
        ELSE 'VALID'
    END as status
FROM Transaction_Requests
WHERE status = 'Pending'
ORDER BY request_date DESC;
"

echo ""
echo "==================================="
echo "Test Complete!"
echo "==================================="
echo ""
echo "Summary:"
echo "- Automatic cleanup runs every 1 hour via MySQL event"
echo "- Runtime cleanup runs on every relevant API call"
echo "- Manual cleanup available via: CALL cleanup_expired_requests_now();"
echo "- All pending borrow requests expire after exactly 24 hours"
echo "- Applies to all roles: Student, Teacher, Librarian, Director"
