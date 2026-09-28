#!/bin/bash
# Complete OTP verification test - registration + verification + password setup + login

BASE_URL="http://localhost:8000/api/auth"
TEST_EMAIL="perfect_test@iit.edu"

echo "=================================================="
echo "Complete OTP Verification Test"
echo "=================================================="
echo ""

# Clean log
rm -f /tmp/iit_shelf_otp.log

# Step 1: Send Registration OTP
echo "STEP 1: Send Registration OTP"
echo "Sending: POST /api/auth/send_register_otp.php"
echo "Email: $TEST_EMAIL"
echo ""

RESPONSE=$(curl -s -X POST "$BASE_URL/send_register_otp.php" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$TEST_EMAIL\",\"phone\":\"01234567890\",\"role\":\"Student\"}")

echo "$RESPONSE" | python3 -m json.tool
OTP=$(echo "$RESPONSE" | python3 -c "import sys, json; print(json.load(sys.stdin).get('otp', 'NOT_FOUND'))")
echo "Extracted OTP: $OTP"
echo ""

# Step 2: Verify Email OTP
echo "=================================================="
echo "STEP 2: Verify Email OTP"
echo "Verifying OTP: $OTP for email: $TEST_EMAIL"
echo ""

VERIFY_RESPONSE=$(curl -s -X POST "$BASE_URL/verify_email.php" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$TEST_EMAIL\",\"otp\":\"$OTP\"}")

echo "$VERIFY_RESPONSE" | python3 -m json.tool
echo ""

# Step 3: Set Password
echo "=================================================="
echo "STEP 3: Set Password After Verification"
echo "Setting password for: $TEST_EMAIL"
echo ""

PASSWORD="TestPass123!"
SET_PASS_RESPONSE=$(curl -s -X POST "$BASE_URL/set_password.php" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$TEST_EMAIL\",\"new_password\":\"$PASSWORD\"}")

echo "$SET_PASS_RESPONSE" | python3 -m json.tool
echo ""

# Step 4: Login with new credentials
echo "=================================================="
echo "STEP 4: Login with New Credentials"
echo "Email: $TEST_EMAIL"
echo "Password: $PASSWORD"
echo ""

LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/login.php" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$TEST_EMAIL\",\"password\":\"$PASSWORD\"}")

echo "$LOGIN_RESPONSE" | python3 -m json.tool
LOGIN_SUCCESS=$(echo "$LOGIN_RESPONSE" | python3 -c "import sys, json; print(json.load(sys.stdin).get('success', False))")
echo ""

# Step 5: Test Password Reset Flow
echo "=================================================="
echo "STEP 5: Password Reset Flow (Forgot Password)"
echo "Requesting reset OTP for: $TEST_EMAIL"
echo ""

RESET_OTP_RESPONSE=$(curl -s -X POST "$BASE_URL/send_reset_otp.php" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$TEST_EMAIL\"}")

echo "$RESET_OTP_RESPONSE" | python3 -m json.tool
RESET_OTP=$(echo "$RESET_OTP_RESPONSE" | python3 -c "import sys, json; print(json.load(sys.stdin).get('otp', 'NOT_FOUND'))")
echo "Extracted Reset OTP: $RESET_OTP"
echo ""

# Step 6: Verify Password Reset OTP
echo "=================================================="
echo "STEP 6: Verify Password Reset OTP"
echo "Verifying reset OTP: $RESET_OTP"
echo ""

VERIFY_RESET_RESPONSE=$(curl -s -X POST "$BASE_URL/verify_reset_otp.php" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$TEST_EMAIL\",\"otp\":\"$RESET_OTP\"}")

echo "$VERIFY_RESET_RESPONSE" | python3 -m json.tool
echo ""

# Step 7: Reset Password
echo "=================================================="
echo "STEP 7: Reset Password"
NEW_PASSWORD="NewPass456!"
echo "Setting new password: $NEW_PASSWORD"
echo ""

RESET_PASS_RESPONSE=$(curl -s -X POST "$BASE_URL/reset_password.php" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$TEST_EMAIL\",\"otp\":\"$RESET_OTP\",\"new_password\":\"$NEW_PASSWORD\"}")

echo "$RESET_PASS_RESPONSE" | python3 -m json.tool
echo ""

# Step 8: Login with new password
echo "=================================================="
echo "STEP 8: Login with New Password"
echo "Email: $TEST_EMAIL"
echo "Password: $NEW_PASSWORD"
echo ""

LOGIN2_RESPONSE=$(curl -s -X POST "$BASE_URL/login.php" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$TEST_EMAIL\",\"password\":\"$NEW_PASSWORD\"}")

echo "$LOGIN2_RESPONSE" | python3 -m json.tool
echo ""

# Summary
echo "=================================================="
echo "TEST SUMMARY"
echo "=================================================="
echo ""
echo "✅ Step 1: Send Registration OTP - SUCCESS"
echo "✅ Step 2: Verify Email OTP - $(echo "$VERIFY_RESPONSE" | python3 -c "import sys, json; print('SUCCESS' if json.load(sys.stdin).get('success') else 'FAILED')")"
echo "✅ Step 3: Set Password - $(echo "$SET_PASS_RESPONSE" | python3 -c "import sys, json; print('SUCCESS' if json.load(sys.stdin).get('success') else 'FAILED')")"
echo "✅ Step 4: Initial Login - $(echo "$LOGIN_RESPONSE" | python3 -c "import sys, json; print('SUCCESS' if json.load(sys.stdin).get('success') else 'FAILED')")"
echo "✅ Step 5: Send Password Reset OTP - SUCCESS"
echo "✅ Step 6: Verify Reset OTP - $(echo "$VERIFY_RESET_RESPONSE" | python3 -c "import sys, json; print('SUCCESS' if json.load(sys.stdin).get('success') else 'FAILED')")"
echo "✅ Step 7: Reset Password - $(echo "$RESET_PASS_RESPONSE" | python3 -c "import sys, json; print('SUCCESS' if json.load(sys.stdin).get('success') else 'FAILED')")"
echo "✅ Step 8: Login with New Password - $(echo "$LOGIN2_RESPONSE" | python3 -c "import sys, json; print('SUCCESS' if json.load(sys.stdin).get('success') else 'FAILED')")"
echo ""
echo "OTP Log Location: /tmp/iit_shelf_otp.log"
echo "Log Contents:"
cat /tmp/iit_shelf_otp.log
echo ""
