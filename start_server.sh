#!/bin/bash

# IIT Shelf Backend Startup Script

echo "================================"
echo "Starting IIT Shelf Backend"
echo "================================"
echo

# Check if MariaDB is running
if ! systemctl is-active --quiet mariadb; then
    echo "Starting MariaDB..."
    sudo systemctl start mariadb
    sleep 2
fi

# Create the database and apply pending migrations.
echo "Running database migrations..."
if ! php "$(dirname "$0")/migrate.php"; then
    echo "✗ Database migrations failed"
    exit 1
fi

# Kill any existing PHP server
if pgrep -f "php -S.*8000" > /dev/null; then
    echo "Stopping existing PHP server..."
    killall php 2>/dev/null
    sleep 1
fi

# Start PHP development server with router.php
echo "Starting PHP development server on http://localhost:8000..."
cd "$(dirname "$0")"
nohup php -S 0.0.0.0:8000 router.php > /tmp/php_server.log 2>&1 &

sleep 2

# Check if server started successfully
if pgrep -f "php -S.*8000" > /dev/null; then
    echo "✓ PHP server started successfully!"
    echo "✓ API is available at: http://localhost:8000"
    echo
    echo "Test endpoints:"
    echo "  - GET  http://localhost:8000/api/books/get_books.php"
    echo "  - POST http://localhost:8000/api/auth/login.php"
    echo "  - POST http://localhost:8000/api/auth/register.php"
    echo
    echo "Run './test_api.sh' to test all endpoints"
    echo
    echo "Server logs: tail -f /tmp/php_server.log"
else
    echo "✗ Failed to start PHP server"
    echo "Check logs: cat /tmp/php_server.log"
    exit 1
fi

echo "================================"
