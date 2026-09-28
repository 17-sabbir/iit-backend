#!/bin/bash
# Test the borrow request workflow

echo "=== Testing Borrow Request Workflow ==="
echo ""

BASE_URL="http://localhost:8000"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Step 1: Submit a borrow request${NC}"
echo "POST $BASE_URL/borrow/request_borrow.php"
echo ""

# Test data - replace with actual values from your database
TEST_EMAIL="student@iit.ac.lk"
TEST_ISBN="0078815967"

REQUEST_RESPONSE=$(curl -s -X POST "$BASE_URL/borrow/request_borrow.php" \
  -H "Content-Type: application/json" \
  -d "{\"user_email\":\"$TEST_EMAIL\",\"isbn\":\"$TEST_ISBN\"}")

echo "$REQUEST_RESPONSE" | jq '.' 2>/dev/null || echo "$REQUEST_RESPONSE"
echo ""

# Extract request_id from response
REQUEST_ID=$(echo "$REQUEST_RESPONSE" | jq -r '.request_id' 2>/dev/null)

if [ "$REQUEST_ID" != "null" ] && [ -n "$REQUEST_ID" ]; then
    echo -e "${GREEN}✓ Borrow request created successfully!${NC}"
    echo "Request ID: $REQUEST_ID"
    echo ""
    
    echo -e "${YELLOW}Step 2: Check pending requests in librarian view${NC}"
    echo "GET $BASE_URL/librarian/get_requests.php?type=borrow"
    echo ""
    
    BORROW_REQUESTS=$(curl -s "$BASE_URL/librarian/get_requests.php?type=borrow")
    echo "$BORROW_REQUESTS" | jq '.' 2>/dev/null || echo "$BORROW_REQUESTS"
    echo ""
    
    echo -e "${YELLOW}Step 3: Approve the request${NC}"
    echo "POST $BASE_URL/librarian/approve_borrow_request.php"
    echo ""
    
    APPROVE_RESPONSE=$(curl -s -X POST "$BASE_URL/librarian/approve_borrow_request.php" \
      -H "Content-Type: application/json" \
      -d "{\"request_id\":$REQUEST_ID}")
    
    echo "$APPROVE_RESPONSE" | jq '.' 2>/dev/null || echo "$APPROVE_RESPONSE"
    echo ""
    
    if echo "$APPROVE_RESPONSE" | grep -q '"success":true'; then
        echo -e "${GREEN}✓ Request approved successfully!${NC}"
        echo ""
        echo -e "${YELLOW}Step 4: Verify the borrow request is no longer pending${NC}"
        
        UPDATED_REQUESTS=$(curl -s "$BASE_URL/librarian/get_requests.php?type=borrow")
        PENDING_COUNT=$(echo "$UPDATED_REQUESTS" | jq '.count' 2>/dev/null)
        
        echo "Pending requests remaining: $PENDING_COUNT"
        echo ""
    else
        echo -e "${RED}✗ Failed to approve request${NC}"
    fi
else
    echo -e "${RED}✗ Failed to create borrow request${NC}"
    echo "Response: $REQUEST_RESPONSE"
fi

echo ""
echo "=== Workflow Test Complete ===="
echo ""
echo -e "${YELLOW}Summary of the new flow:${NC}"
echo "1. User clicks 'Request to Borrow' → Creates pending request in Transaction_Requests"
echo "2. Librarian sees request in Requests tab (Borrow)"
echo "3. Librarian clicks 'Approve' → Creates Approved_Transaction, updates book status"
echo "4. Book is marked as Borrowed and assigned to user"
