#!/bin/bash
# Database Migration Verification Script
# Run this after migration to verify everything is working

echo "======================================"
echo "Database Migration Verification"
echo "======================================"
echo ""

DB_NAME="iit_shelf"
DB_USER="iit_user"
DB_PASS="iit_password"

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

check_passed=0
check_failed=0

# Function to run SQL query
run_query() {
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$1" 2>/dev/null
}

# Function to check table exists
check_table() {
    result=$(run_query "SHOW TABLES LIKE '$1';" | grep -c "$1")
    if [ "$result" -eq 1 ]; then
        echo -e "${GREEN}✓${NC} Table $1 exists"
        ((check_passed++))
        return 0
    else
        echo -e "${RED}✗${NC} Table $1 NOT FOUND"
        ((check_failed++))
        return 1
    fi
}

# Function to check field exists
check_field() {
    result=$(run_query "DESCRIBE $1 $2;" | grep -c "$2")
    if [ "$result" -eq 1 ]; then
        echo -e "${GREEN}✓${NC} Field $1.$2 exists"
        ((check_passed++))
        return 0
    else
        echo -e "${RED}✗${NC} Field $1.$2 NOT FOUND"
        ((check_failed++))
        return 1
    fi
}

# Function to check field does NOT exist (should be removed)
check_field_removed() {
    result=$(run_query "DESCRIBE $1 $2;" 2>/dev/null | grep -c "$2")
    if [ "$result" -eq 0 ]; then
        echo -e "${GREEN}✓${NC} Field $1.$2 correctly removed"
        ((check_passed++))
        return 0
    else
        echo -e "${YELLOW}⚠${NC} Field $1.$2 still exists (should be removed)"
        ((check_failed++))
        return 1
    fi
}

echo "1. Checking Core Tables..."
echo "-----------------------------------"
check_table "Users"
check_table "Temp_User_Verification"
check_table "Students"
check_table "Teachers"
check_table "Books"
check_table "Book_Copies"
check_table "Book_Courses"
check_table "Courses"
check_table "Shelves"
check_table "Digital_Resources"
check_table "Transaction_Requests"
check_table "Approved_Transactions"
check_table "Reservations"
check_table "Fines"
check_table "Payments"
check_table "fine_payment"
check_table "Reports"
check_table "Notifications"
check_table "Requests"
echo ""

echo "2. Checking Users Table Fields..."
echo "-----------------------------------"
check_field "Users" "email"
check_field "Users" "name"
check_field "Users" "password_hash"
check_field "Users" "role"
check_field "Users" "contact"
check_field "Users" "created_at"
check_field "Users" "last_login"
check_field_removed "Users" "phone"
check_field_removed "Users" "email_verified_at"
check_field_removed "Users" "is_active"
check_field_removed "Users" "updated_at"
echo ""

echo "3. Checking Books Table Fields..."
echo "-----------------------------------"
check_field "Books" "isbn"
check_field "Books" "title"
check_field "Books" "author"
check_field "Books" "category"
check_field_removed "Books" "copies_total"
check_field_removed "Books" "copies_available"
check_field_removed "Books" "language"
check_field_removed "Books" "keywords"
check_field_removed "Books" "is_deleted"
check_field_removed "Books" "updated_at"
echo ""

echo "4. Checking Book_Copies Table Fields..."
echo "-----------------------------------"
check_field "Book_Copies" "copy_id"
check_field "Book_Copies" "isbn"
check_field "Book_Copies" "shelf_id"
check_field "Book_Copies" "status"
check_field_removed "Book_Copies" "is_deleted"
check_field_removed "Book_Copies" "updated_at"
echo ""

echo "5. Checking Approved_Transactions Table..."
echo "-----------------------------------"
check_field "Approved_Transactions" "transaction_id"
check_field "Approved_Transactions" "request_id"
check_field "Approved_Transactions" "copy_id"
check_field "Approved_Transactions" "issue_date"
check_field "Approved_Transactions" "due_date"
check_field "Approved_Transactions" "status"
check_field_removed "Approved_Transactions" "user_email"
check_field_removed "Approved_Transactions" "isbn"
check_field_removed "Approved_Transactions" "updated_at"
echo ""

echo "6. Checking Data Integrity..."
echo "-----------------------------------"

# Check if Users table has data
user_count=$(run_query "SELECT COUNT(*) FROM Users;" | tail -1)
if [ "$user_count" -gt 0 ]; then
    echo -e "${GREEN}✓${NC} Users table has $user_count records"
    ((check_passed++))
else
    echo -e "${YELLOW}⚠${NC} Users table is empty (consider adding test data)"
fi

# Check if Books table has data
book_count=$(run_query "SELECT COUNT(*) FROM Books;" | tail -1)
if [ "$book_count" -gt 0 ]; then
    echo -e "${GREEN}✓${NC} Books table has $book_count records"
    ((check_passed++))
else
    echo -e "${YELLOW}⚠${NC} Books table is empty"
fi

# Check foreign key constraints
fk_count=$(run_query "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_TYPE='FOREIGN KEY' AND TABLE_SCHEMA='$DB_NAME';" | tail -1)
if [ "$fk_count" -gt 15 ]; then
    echo -e "${GREEN}✓${NC} Foreign key constraints in place ($fk_count found)"
    ((check_passed++))
else
    echo -e "${RED}✗${NC} Missing foreign key constraints (found $fk_count, expected 15+)"
    ((check_failed++))
fi

echo ""
echo "7. Testing API Compatibility..."
echo "-----------------------------------"

# Check if backend is running
if curl -s http://localhost:8000/api/books/get_books.php > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} Backend server is running"
    ((check_passed++))
    
    # Test books API
    books_response=$(curl -s http://localhost:8000/api/books/get_books.php)
    if echo "$books_response" | grep -q "success"; then
        echo -e "${GREEN}✓${NC} Books API responding correctly"
        ((check_passed++))
    else
        echo -e "${RED}✗${NC} Books API error"
        ((check_failed++))
    fi
else
    echo -e "${YELLOW}⚠${NC} Backend server not running (start with ./start_server.sh)"
fi

echo ""
echo "======================================"
echo "Verification Summary"
echo "======================================"
echo -e "${GREEN}Passed: $check_passed${NC}"
echo -e "${RED}Failed: $check_failed${NC}"
echo ""

if [ "$check_failed" -eq 0 ]; then
    echo -e "${GREEN}✓ Migration verification PASSED!${NC}"
    echo "Your database is ready to use with the team schema."
    exit 0
else
    echo -e "${RED}✗ Migration verification FAILED!${NC}"
    echo "Please review the errors above and check:"
    echo "  1. Migration script ran successfully"
    echo "  2. Database credentials are correct"
    echo "  3. All SQL files executed without errors"
    exit 1
fi
