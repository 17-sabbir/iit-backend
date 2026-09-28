#!/bin/bash
BASE_URL="http://localhost:8000"
EMAIL="eusha@nstu.edu.bd"

set -e

echo "Approving a sample return request (transaction_id=1)..."
curl -s -X POST "$BASE_URL/api/librarian/approve_return_request.php" \
  -H "Content-Type: application/json" \
  -d '{"transaction_id": 1}' | jq '.'

sleep 1

echo "Fetching notifications for $EMAIL..."
curl -s "$BASE_URL/auth/get_notifications.php?email=$EMAIL&limit=10" | jq '.'