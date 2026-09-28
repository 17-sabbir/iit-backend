#!/bin/bash

# IIT Shelf API Test Script
BASE_URL="http://localhost:8000"

echo "================================"
echo "IIT Shelf API Testing"
echo "================================"
echo

# Test 1: Get all books
echo "1. Testing GET /api/books/get_books.php"
curl -s "$BASE_URL/api/books/get_books.php" | python -m json.tool 2>/dev/null || curl -s "$BASE_URL/api/books/get_books.php"
echo -e "\n"

# Test 2: Register new user
echo "2. Testing POST /api/auth/register.php"
curl -X POST "$BASE_URL/api/auth/register.php" \
  -H "Content-Type: application/json" \
  -d '{"email":"newuser@iit.edu","password":"test123","phone":"+8801111111111","role":"student"}' \
  -s | python -m json.tool 2>/dev/null || curl -X POST "$BASE_URL/api/auth/register.php" -H "Content-Type: application/json" -d '{"email":"newuser@iit.edu","password":"test123","phone":"+8801111111111","role":"student"}' -s
echo -e "\n"

# Test 3: Login
echo "3. Testing POST /api/auth/login.php"
curl -X POST "$BASE_URL/api/auth/login.php" \
  -H "Content-Type: application/json" \
  -d '{"email":"student@iit.edu","password":"password123"}' \
  -s | python -m json.tool 2>/dev/null || curl -X POST "$BASE_URL/api/auth/login.php" -H "Content-Type: application/json" -d '{"email":"student@iit.edu","password":"password123"}' -s
echo -e "\n"

# Test 4: Add a book
echo "4. Testing POST /api/books/add_book.php"
curl -X POST "$BASE_URL/api/books/add_book.php" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Introduction to Algorithms",
    "author": "Thomas H. Cormen",
    "isbn": "978-0262033848",
    "category": "Computer Science",
    "quantity": 5,
    "shelf_id": 1,
    "cover_image": "https://example.com/cover.jpg",
    "pdf_url": "",
    "description": "Comprehensive algorithms textbook"
  }' \
  -s | python -m json.tool 2>/dev/null || curl -X POST "$BASE_URL/api/books/add_book.php" -H "Content-Type: application/json" -d '{"title":"Introduction to Algorithms","author":"Thomas H. Cormen","isbn":"978-0262033848","category":"Computer Science","quantity":5,"shelf_id":1}' -s
echo -e "\n"

# Test 5: Get books again (should show the added book)
echo "5. Testing GET /api/books/get_books.php (after adding book)"
curl -s "$BASE_URL/api/books/get_books.php" | python -m json.tool 2>/dev/null || curl -s "$BASE_URL/api/books/get_books.php"
echo -e "\n"

# Test 6: Borrow a book
echo "6. Testing POST /api/borrow/borrow_book.php"
curl -X POST "$BASE_URL/api/borrow/borrow_book.php" \
  -H "Content-Type: application/json" \
  -d '{"user_id": 3, "book_id": 1}' \
  -s | python -m json.tool 2>/dev/null || curl -X POST "$BASE_URL/api/borrow/borrow_book.php" -H "Content-Type: application/json" -d '{"user_id":3,"book_id":1}' -s
echo -e "\n"

echo "================================"
echo "All tests completed!"
echo "================================"
