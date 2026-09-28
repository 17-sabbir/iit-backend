#!/bin/bash

# Test script for notification system
BASE_URL="http://localhost:8000"

echo "=================================================="
echo "Testing Notification System"
echo "=================================================="
echo ""

# Test 1: Get notifications for a user
echo "Test 1: Fetching notifications for eusha@nstu.edu.bd"
curl -s "$BASE_URL/auth/get_notifications.php?email=eusha@nstu.edu.bd&limit=10" | jq '.'
echo ""
echo ""

# Test 2: Send due date reminders manually
echo "Test 2: Triggering due date reminders"
curl -s -X POST "$BASE_URL/auth/send_notifications.php" \
  -H "Content-Type: application/json" \
  -d '{"type": "due_dates"}' | jq '.'
echo ""
echo ""

# Test 3: Send fine reminders manually
echo "Test 3: Triggering fine reminders"
curl -s -X POST "$BASE_URL/auth/send_notifications.php" \
  -H "Content-Type: application/json" \
  -d '{"type": "fines"}' | jq '.'
echo ""
echo ""

# Test 4: Check all notifications after manual triggers
echo "Test 4: Fetching all notifications after triggers"
curl -s "$BASE_URL/auth/get_notifications.php?email=eusha@nstu.edu.bd&limit=20" | jq '.'
echo ""
echo ""

# Test 5: Check notification events status
echo "Test 5: Checking MySQL notification events"
mysql -u root -proot -e "
USE iit_shelf;
SELECT 
  EVENT_NAME,
  STATUS,
  LAST_EXECUTED,
  INTERVAL_VALUE,
  INTERVAL_FIELD,
  EXECUTE_AT
FROM information_schema.EVENTS 
WHERE EVENT_SCHEMA = 'iit_shelf' 
  AND EVENT_NAME IN ('send_due_date_reminders', 'send_fine_reminders', 'cleanup_old_notifications');
" 2>/dev/null
echo ""

echo "=================================================="
echo "Notification System Test Complete"
echo "=================================================="
