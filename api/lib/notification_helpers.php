<?php
/**
 * Notification Management System
 * Handles creating and managing notifications for users
 */

/**
 * Create a notification for a user
 * @param PDO $db Database connection
 * @param string $userEmail User's email
 * @param string $message Notification message
 * @param string $type Notification type (DueDateReminder, ReservedBookAvailable, PaymentConfirmation, RequestApproved, System)
 * @return int|false Notification ID or false on failure
 */
function createNotification(PDO $db, string $userEmail, string $message, string $type = 'System') {
    try {
        $stmt = $db->prepare('INSERT INTO Notifications (user_email, message, type, sent_at)
            VALUES (:email, :message, :type, NOW())');
        $stmt->execute([
            ':email' => $userEmail,
            ':message' => $message,
            ':type' => $type
        ]);
        return $db->lastInsertId();
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify user when their borrow request is approved
 */
function notifyBorrowRequestApproved(PDO $db, string $userEmail, string $bookTitle, string $isbn, string $dueDate) {
    $message = "Your borrow request for '$bookTitle' (ISBN: $isbn) has been approved. Due date: $dueDate. Please collect it from the library.";
    return createNotification($db, $userEmail, $message, 'BorrowRequestApproved');
}

/**
 * Notify user when their return request is approved
 */
function notifyReturnRequestApproved(PDO $db, string $userEmail, string $bookTitle) {
    $message = "Your return request for '$bookTitle' has been approved. Thank you for returning the book on time.";
    return createNotification($db, $userEmail, $message, 'ReturnRequestApproved');
}

/**
 * Notify user when their book addition request is approved
 */
function notifyAdditionRequestApproved(PDO $db, string $userEmail, string $bookTitle) {
    $message = "Your book addition request for '$bookTitle' has been approved and added to the library collection. Thank you for your suggestion!";
    return createNotification($db, $userEmail, $message, 'AdditionRequestApproved');
}

/**
 * Notify user when their reserved book becomes available (12-hour window starts)
 */
function notifyReservedBookAvailable(PDO $db, string $userEmail, string $bookTitle, string $isbn, string $expiresAt) {
    $expiryTime = (new DateTime($expiresAt))->format('M d, Y h:i A');
    $message = "Good news! '$bookTitle' (ISBN: $isbn) is now available for you to borrow. You have until $expiryTime (12 hours) to request this book. Please visit the library or request online.";
    return createNotification($db, $userEmail, $message, 'ReservedBookAvailable');
}

/**
 * Notify user when their reservation queue position changes
 */
function notifyReservationQueueUpdate(PDO $db, string $userEmail, string $bookTitle, int $newPosition) {
    $message = "Your reservation position for '$bookTitle' has been updated. You are now #$newPosition in the queue.";
    return createNotification($db, $userEmail, $message, 'System');
}

/**
 * Notify user when book due date is approaching (1 day before)
 */
function notifyDueDateApproaching(PDO $db, string $userEmail, string $bookTitle, string $dueDate) {
    $dueDateFormatted = (new DateTime($dueDate))->format('M d, Y');
    $message = "Reminder: Your borrowed book '$bookTitle' is due tomorrow ($dueDateFormatted). Please return it to avoid late fees.";
    return createNotification($db, $userEmail, $message, 'DueDateReminder');
}

/**
 * Notify user about unpaid fines
 */
function notifyUnpaidFine(PDO $db, string $userEmail, float $amount, string $description) {
    $message = "You have an unpaid fine of $$amount. Reason: $description. Please pay at your earliest convenience to avoid further penalties.";
    return createNotification($db, $userEmail, $message, 'FineReminder');
}

/**
 * Check and send due date reminders for books due within 24 hours
 * Returns number of notifications sent
 */
function sendDueDateReminders(PDO $db): int {
    $stmt = $db->prepare('
        SELECT DISTINCT
            tr.requester_email,
            b.title,
            b.isbn,
            at.due_date
        FROM Approved_Transactions at
        JOIN Transaction_Requests tr ON at.request_id = tr.request_id
        JOIN Book_Copies bc ON at.copy_id = bc.copy_id
        JOIN Books b ON bc.isbn = b.isbn
        WHERE at.status = "Borrowed"
        AND at.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
        AND NOT EXISTS (
            SELECT 1 FROM Notifications n
            WHERE n.user_email = tr.requester_email
            AND n.type = "DueDateReminder"
            AND n.message LIKE CONCAT("%", b.title, "%")
            AND n.sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        )
    ');
    $stmt->execute();
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count = 0;
    foreach ($books as $book) {
        if (notifyDueDateApproaching($db, $book['requester_email'], $book['title'], $book['due_date'])) {
            $count++;
        }
    }
    
    return $count;
}

/**
 * Send fine reminders for unpaid fines older than 3 days
 * Returns number of notifications sent
 */
function sendFineReminders(PDO $db): int {
    $stmt = $db->prepare('
        SELECT 
            f.fine_id,
            f.user_email,
            f.amount,
            f.description
        FROM Fines f
        WHERE f.paid = 0
        AND NOT EXISTS (
            SELECT 1 FROM Notifications n
            WHERE n.user_email = f.user_email
            AND n.type = "FineReminder"
            AND n.message LIKE CONCAT("%$", f.amount, "%")
            AND n.sent_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
        )
    ');
    $stmt->execute();
    $fines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count = 0;
    foreach ($fines as $fine) {
        if (notifyUnpaidFine($db, $fine['user_email'], $fine['amount'], $fine['description'])) {
            $count++;
        }
    }
    
    return $count;
}

/**
 * Get notifications for a user
 * @param PDO $db Database connection
 * @param string $userEmail User's email
 * @param int $limit Maximum number of notifications to retrieve
 * @return array Array of notifications
 */
function getUserNotifications(PDO $db, string $userEmail, int $limit = 50): array {
    $stmt = $db->prepare('
        SELECT notification_id, message, type, sent_at
        FROM Notifications
        WHERE user_email = :email
        ORDER BY sent_at DESC
        LIMIT :limit
    ');
    $stmt->bindParam(':email', $userEmail);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Track reservation queue positions and notify users of changes
 */
function notifyQueuePositionChanges(PDO $db, string $isbn): void {
    $stmt = $db->prepare('
        SELECT reservation_id, user_email, queue_position
        FROM Reservations
        WHERE isbn = :isbn AND status = "Active"
        ORDER BY queue_position ASC
    ');
    $stmt->execute([':isbn' => $isbn]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get book title
    $bookStmt = $db->prepare('SELECT title FROM Books WHERE isbn = :isbn');
    $bookStmt->execute([':isbn' => $isbn]);
    $bookTitle = $bookStmt->fetchColumn() ?: 'Unknown Book';
    
    foreach ($reservations as $reservation) {
        // Check if we've already notified about this position recently
        $checkStmt = $db->prepare('
            SELECT 1 FROM Notifications
            WHERE user_email = :email
            AND message LIKE :pattern
            AND sent_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ');
        $pattern = "%You are now #" . $reservation['queue_position'] . " in the queue%";
        $checkStmt->execute([
            ':email' => $reservation['user_email'],
            ':pattern' => $pattern
        ]);
        
        if (!$checkStmt->fetch()) {
            notifyReservationQueueUpdate($db, $reservation['user_email'], $bookTitle, $reservation['queue_position']);
        }
    }
}
?>
