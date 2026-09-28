#!/bin/bash
echo "=== Testing All Critical Endpoints ==="
echo ""

echo "1. Testing Borrow Request (at limit - should fail):"
curl -s -X POST http://localhost:8000/borrow/request_borrow.php \
  -H "Content-Type: application/json" \
  -d '{"user_email":"tamal2517@student.nstu.edu.bd","isbn":"978-0321570512"}' | python -m json.tool 2>/dev/null
echo ""

echo "2. Testing Get Borrow Requests:"
curl -s "http://localhost:8000/librarian/get_requests.php?type=borrow" | python -m json.tool 2>/dev/null | head -10
echo ""

echo "3. Testing Get Return Requests:"
curl -s "http://localhost:8000/librarian/get_requests.php?type=return" | python -m json.tool 2>/dev/null | head -10
echo ""

echo "4. Testing Dashboard Stats:"
curl -s "http://localhost:8000/librarian/dashboard_stats.php" | python -m json.tool 2>/dev/null | head -15
echo ""

echo "5. Testing Get User Transactions:"
curl -s "http://localhost:8000/borrow/get_user_transactions.php?email=tamal2517@student.nstu.edu.bd&status=borrowed" | python -m json.tool 2>/dev/null | head -15
echo ""

echo "=== All Tests Complete ==="
