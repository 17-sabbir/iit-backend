# IIT Shelf Backend - PHP + MySQL

## Overview
This is the backend API for the IIT Shelf library management system. It provides RESTful endpoints for authentication, book management, borrowing, and more.

## Technology Stack
- **Language**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Architecture**: REST API

## Prerequisites

### Install Required Software:

1. **Install PHP**:
   ```bash
   # On Ubuntu/Debian
   sudo apt update
   sudo apt install php php-mysql php-mbstring php-json
   
   # On macOS
   brew install php
   
   # Verify installation
   php -v
   ```

2. **Install MySQL**:
   ```bash
   # On Ubuntu/Debian
   sudo apt install mysql-server
   
   # On macOS
   brew install mysql
   brew services start mysql
   
   # Secure MySQL installation
   sudo mysql_secure_installation
   ```

3. **Install Apache (Optional - for production-like setup)**:
   ```bash
   # On Ubuntu/Debian
   sudo apt install apache2
   sudo systemctl start apache2
   
   # On macOS
   brew install httpd
   ```

## Quick Setup

### 1. Database Setup

```bash
# Login to MySQL
mysql -u root -p

# Create the database and apply all pending migrations
php backend/migrate.php

# The runner uses DB_HOST, DB_NAME, DB_USER and DB_PASSWORD when provided.
# Otherwise it uses the local development defaults.
```

### 2. Configure Database Connection

Set database environment variables instead of editing source files:
```bash
DB_HOST=localhost
DB_NAME=iit_shelf
DB_USER=iit_user
DB_PASSWORD=your_mysql_password
```

To add a schema change, create a new SQL file in `backend/migrations/` with the
next numeric prefix, then run `php backend/migrate.php` again. Applied migrations
are recorded in the `schema_migrations` table and their checksums are verified.

### 3. Start PHP Development Server

```bash
# Navigate to backend directory
cd backend

# Start PHP built-in server
php -S localhost:8000

# Your API will be available at: http://localhost:8000
```

## API Endpoints

### Authentication

#### Register User
```
POST http://localhost:8000/api/auth/register.php

Body (JSON):
{
  "email": "student@iit.edu",
  "password": "password123",
  "phone": "+8801234567890",
  "role": "student"
}
```

#### Login
```
POST http://localhost:8000/api/auth/login.php

Body (JSON):
{
  "email": "student@iit.edu",
  "password": "password123"
}
```

### Books

#### Get All Books
```
GET http://localhost:8000/api/books/get_books.php
GET http://localhost:8000/api/books/get_books.php?search=java
GET http://localhost:8000/api/books/get_books.php?category=Computer Science
```

#### Add Book (Librarian/Director only)
```
POST http://localhost:8000/api/books/add_book.php

Body (JSON):
{
  "title": "Introduction to Algorithms",
  "author": "Thomas H. Cormen",
  "isbn": "978-0262033848",
  "category": "Computer Science",
  "quantity": 5,
  "shelf_id": 1,
  "cover_image": "https://example.com/cover.jpg",
  "pdf_url": "",
  "description": "Comprehensive algorithms textbook"
}
```

### Borrow/Return

#### Borrow Book
```
POST http://localhost:8000/api/borrow/borrow_book.php

Body (JSON):
{
  "user_id": 1,
  "book_id": 3
}
```

#### Return Book
```
POST http://localhost:8000/api/borrow/return_book.php

Body (JSON):
{
  "borrow_id": 1
}
```

## Directory Structure

```
backend/
├── api/
│   ├── auth/
│   │   ├── login.php
│   │   └── register.php
│   ├── books/
│   │   ├── get_books.php
│   │   └── add_book.php
│   └── borrow/
│       ├── borrow_book.php
│       └── return_book.php
├── config/
│   └── database.php
├── migrations/
│   └── 000_initial_schema.sql
├── migrate.php
└── README.md
```

## Testing the API

### Using curl:
```bash
# Test registration
curl -X POST http://localhost:8000/api/auth/register.php \
  -H "Content-Type: application/json" \
  -d '{"email":"test@iit.edu","password":"password123","phone":"+8801234567890"}'

# Test login
curl -X POST http://localhost:8000/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"test@iit.edu","password":"password123"}'

# Test get books
curl http://localhost:8000/api/books/get_books.php
```

### Using Postman:
1. Download and install Postman
2. Create a new request
3. Set the method (GET/POST)
4. Enter the URL
5. For POST requests, add JSON body in the Body tab (select "raw" and "JSON")

## Integrating with Flutter

In your Flutter app, use the `http` package:

```dart
import 'package:http/http.dart' as http;
import 'dart:convert';

// Login example
Future<void> login(String email, String password) async {
  final response = await http.post(
    Uri.parse('http://localhost:8000/api/auth/login.php'),
    headers: {'Content-Type': 'application/json'},
    body: jsonEncode({
      'email': email,
      'password': password,
    }),
  );
  
  if (response.statusCode == 200) {
    final data = jsonDecode(response.body);
    print('Login successful: ${data['message']}');
  }
}
```

## Deployment

For production deployment:

1. **Use Apache/Nginx** instead of PHP built-in server
2. **Enable HTTPS** for secure communication
3. **Update CORS settings** in `config/database.php`
4. **Set strong database passwords**
5. **Implement JWT tokens** for authentication
6. **Add rate limiting** and security measures

## Default Credentials

- **Librarian**: librarian@iit.edu / password
- **Director**: director@iit.edu / password

## Troubleshooting

### Connection refused
- Make sure PHP server is running: `php -S localhost:8000`
- Check if port 8000 is available: `netstat -an | grep 8000`

### Database connection error
- Verify MySQL is running: `sudo systemctl status mysql`
- Check credentials in `config/database.php`
- Ensure database exists: `mysql -u root -p -e "SHOW DATABASES;"`

### CORS errors in Flutter
- Headers are already set in `config/database.php`
- For production, update the `Access-Control-Allow-Origin` to your specific domain

## Next Steps

You can extend this backend by adding:
- User profile management
- Book reservation system
- Notifications API
- Reports and analytics
- Image upload handling
- PDF file storage
- Email notifications
- JWT authentication

## Support

For issues or questions, refer to:
- PHP Documentation: https://www.php.net/docs.php
- MySQL Documentation: https://dev.mysql.com/doc/
- Flutter HTTP package: https://pub.dev/packages/http
