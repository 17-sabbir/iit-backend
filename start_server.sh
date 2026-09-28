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

# Kill any existing PHP server
if pgrep -f "php -S.*8000" > /dev/null; then
    echo "Stopping existing PHP server..."
    killall php 2>/dev/null
    sleep 1
fi

# Start Laravel's front controller so middleware cannot be bypassed.
echo "Starting PHP development server on http://localhost:8000..."
cd "$(dirname "$0")"
nohup php artisan serve --host=0.0.0.0 --port=8000 > /tmp/php_server.log 2>&1 &

sleep 2

# Check if server started successfully
if pgrep -f "artisan serve.*8000" > /dev/null; then
    echo "✓ PHP server started successfully!"
    echo "✓ API is available at: http://localhost:8000"
    echo
    echo "Test endpoints:"
    echo "  - GET  http://localhost:8000/api/books/get_books.php"
    echo "  - POST http://localhost:8000/api/auth/login.php"
    echo "  - POST http://localhost:8000/api/auth/register.php"
    echo
    echo "Run 'php artisan test' to run the Laravel test suite"
    echo
    echo "Server logs: tail -f /tmp/php_server.log"
else
    echo "✗ Failed to start PHP server"
    echo "Check logs: cat /tmp/php_server.log"
    exit 1
fi

echo "================================"
