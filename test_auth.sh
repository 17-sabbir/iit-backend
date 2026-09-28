#!/bin/bash
# Test auth endpoints

BASE_URL="http://localhost:8000/api/auth"

echo "=================================================="
echo "Testing IIT Shelf Auth System"
echo "=================================================="
echo ""

# Test 1: Register new user
echo "1. Testing Registration (new user: test@iit.edu)..."
curl -s -X POST "$BASE_URL/register.php" \
  -H "Content-Type: application/json" \
  -d '{"email":"test@iit.edu","password":"test123","name":"Test User","phone":"01712345678"}' \
  | python3 -m json.tool
echo ""
echo ""

# Test 2: Try login before verification (should fail)
echo "2. Testing Login BEFORE verification (should fail)..."
curl -s -X POST "$BASE_URL/login.php" \
  -H "Content-Type: application/json" \
  -d '{"email":"test@iit.edu","password":"test123"}' \
  | python3 -m json.tool
echo ""
echo ""

# Get OTP from registration response (in real scenario, check console/email)
echo "3. Verify email with OTP (check console output for OTP)..."
echo "   Note: In the registration response above, look for 'otp' field"
echo "   Then manually run: curl -X POST $BASE_URL/verify_email.php -H 'Content-Type: application/json' -d '{\"email\":\"test@iit.edu\",\"otp\":\"YOUR_OTP\"}'"
echo ""

# Test 3: Login with verified account (demo account)
echo "4. Testing Login with verified account (student@iit.edu)..."
curl -s -X POST "$BASE_URL/login.php" \
  -H "Content-Type: application/json" \
  -d '{"email":"student@iit.edu","password":"123"}' \
  | python3 -m json.tool
echo ""
echo ""

# Test 4: Send password reset OTP
echo "5. Testing Password Reset OTP (student@iit.edu)..."
curl -s -X POST "$BASE_URL/send_reset_otp.php" \
  -H "Content-Type: application/json" \
  -d '{"email":"student@iit.edu"}' \
  | python3 -m json.tool
echo ""
echo ""

echo "=================================================="
echo "Test complete! Check responses above."
echo "=================================================="
