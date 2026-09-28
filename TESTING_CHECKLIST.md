# OTP Verification System - Testing Checklist

## ✅ Core Functionality Tests

### Registration Flow
- [ ] User can request registration OTP with valid email
- [ ] OTP is generated and logged to `/tmp/iit_shelf_otp.log`
- [ ] User can verify email with correct OTP
- [ ] Email verification sets `email_verified_at` in database
- [ ] User can set password after email verification
- [ ] Password is bcrypt hashed in database
- [ ] User can login with new credentials

### Password Reset Flow
- [ ] Verified user can request password reset OTP
- [ ] Reset OTP is logged to `/tmp/iit_shelf_otp.log`
- [ ] User can verify reset OTP with correct code
- [ ] User can reset password with verified OTP
- [ ] New password is bcrypt hashed
- [ ] User can login with new password

### Login Security
- [ ] Unverified users cannot login
- [ ] Invalid passwords are rejected
- [ ] Verified users with correct password can login
- [ ] Login returns user role and token

---

## 🔒 Security Tests

### Brute Force Protection
- [ ] After 5 failed OTP attempts, account is locked
- [ ] Locked account cannot verify until 15 minutes pass
- [ ] Failed attempts are logged in `/tmp/iit_shelf_otp_attempts.log`
- [ ] Success attempts are also logged for audit trail
- [ ] Each attempt shows remaining attempts counter

### OTP Expiration
- [ ] OTP is valid for 5 minutes
- [ ] Expired OTP is rejected with proper error message
- [ ] Expired OTP cannot be reused

### OTP Cooldown
- [ ] User cannot request new OTP within 60 seconds
- [ ] API returns cooldown remaining time
- [ ] After 60 seconds, new OTP can be requested

### Password Security
- [ ] Passwords are bcrypt hashed (cost 12)
- [ ] Plain passwords are never stored in database
- [ ] Password hashes cannot be reversed

---

## 🐛 Edge Cases & Error Handling

### Invalid Inputs
- [ ] Empty email is rejected
- [ ] Empty OTP is rejected  
- [ ] Empty password is rejected
- [ ] Missing fields in request return 400 error

### User State Transitions
- [ ] Cannot register with already-verified email
- [ ] Cannot reset password for unverified email
- [ ] Cannot verify OTP for non-existent user
- [ ] Cannot set password without prior email verification

### Database Consistency
- [ ] OTP records are deleted after successful verification
- [ ] Expired OTPs are cleaned up (checked on validation)
- [ ] User record has correct role after registration
- [ ] last_login timestamp is updated on login

---

## 📱 Flutter App Tests

### Registration UI
- [ ] Email input field accepts text
- [ ] "Send OTP" button is disabled when email is empty
- [ ] Loading spinner shows while sending OTP
- [ ] Success message displays after OTP sent
- [ ] Password fields are disabled until OTP verified
- [ ] "Verify OTP" button works after OTP entered
- [ ] Password fields enable after successful verification
- [ ] "Set Password" button creates account

### Login UI  
- [ ] Email and password inputs accept text
- [ ] Login button calls API with credentials
- [ ] Loading spinner shows during login
- [ ] Error message shows for invalid credentials
- [ ] Successful login navigates to dashboard

### Forgot Password UI
- [ ] Email input accepts text
- [ ] "Send Reset OTP" button requests reset OTP
- [ ] OTP input field accepts verification code
- [ ] "Verify OTP" button validates reset code
- [ ] New password fields enable after OTP verified
- [ ] Password reset completes and navigates to login

### Error Display
- [ ] "No OTP found" error shows when OTP doesn't exist
- [ ] Remaining attempts counter displays
- [ ] Lockout message shows with retry time
- [ ] Invalid OTP error message is clear
- [ ] Network errors are handled gracefully

---

## 📊 Database Verification

### Users Table
```sql
SELECT email, email_verified_at, password_hash, last_login FROM users WHERE email = 'test@example.com';
```
- [ ] `email_verified_at` is NULL for unverified users
- [ ] `email_verified_at` is set after verification
- [ ] `password_hash` starts with `$2y$` (bcrypt format)
- [ ] `last_login` updates after successful login

### temp_user_verification Table
```sql
SELECT email, otp_code, purpose, expires_at FROM temp_user_verification WHERE email = 'test@example.com';
```
- [ ] Record created when OTP sent
- [ ] `expires_at` is 5 minutes from creation
- [ ] Record deleted after successful verification
- [ ] Multiple purposes (EmailVerification, PasswordReset) can coexist

---

## 📝 Log Verification

### OTP Log (`/tmp/iit_shelf_otp.log`)
```bash
cat /tmp/iit_shelf_otp.log
```
- [ ] Each OTP generation creates a timestamped log entry
- [ ] Format shows: timestamp, purpose, email, OTP code
- [ ] Entries are in chronological order
- [ ] Both EmailVerification and PasswordReset are logged

### Attempt Log (`/tmp/iit_shelf_otp_attempts.log`)
```bash
cat /tmp/iit_shelf_otp_attempts.log
```
- [ ] Successful verifications logged as "SUCCESS"
- [ ] Failed verifications logged as "FAILED"
- [ ] Reason for failure included (e.g., "Invalid OTP", "Account locked")
- [ ] Timestamp and email included for audit trail

---

## 🚀 Performance Tests

### API Response Times
- [ ] Send OTP completes in < 500ms
- [ ] Verify OTP completes in < 300ms
- [ ] Set Password completes in < 500ms
- [ ] Login completes in < 300ms

### Load Testing (Optional)
- [ ] System handles 100 concurrent registration requests
- [ ] Cooldown prevents OTP spam attacks
- [ ] Brute force protection prevents lockout abuse

---

## 🔄 Integration Tests

### Full User Lifecycle
- [ ] User registers → verifies → logs in → requests password reset → resets → logs in with new password
- [ ] User cannot access verified-only features before verification
- [ ] Multiple users can register independently

### API Contract
- [ ] All endpoints return valid JSON
- [ ] HTTP status codes are correct (200, 400, 401, 429)
- [ ] Error responses include meaningful messages
- [ ] Success responses include required fields

---

## ✨ Production Readiness

### Code Quality
- [ ] No hardcoded credentials in code
- [ ] Error messages are user-friendly (don't leak internal details)
- [ ] SQL queries use parameterized statements (no injection risk)
- [ ] File permissions are secure (OTP log not world-readable in production)

### Deployment Checklist
- [ ] All dependencies are documented
- [ ] Database schema is migrated
- [ ] Config files are ready (database.php, mail settings)
- [ ] SSL/HTTPS is configured for production
- [ ] Email service is configured (SMTP credentials set up)
- [ ] OTP logs are rotated to prevent disk space issues

### Security Audit
- [ ] Rate limiting is in place
- [ ] Brute force protection is active
- [ ] Passwords are never logged
- [ ] OTP codes are not in URL parameters (POST only)
- [ ] CORS is configured appropriately
- [ ] SQL injection is prevented
- [ ] XSS attacks are mitigated

---

## 📞 Test Results Summary

After running all tests, fill in:

```
Date: _______________
Tested By: _______________

Total Tests: ___ 
Passed: ___ (Should be all)
Failed: ___ (Should be 0)
Skipped: ___ 

Critical Issues: NONE EXPECTED
Minor Issues: _________

Sign-off: _______________
```

---

**If all checkboxes pass → System is production ready! 🚀**
