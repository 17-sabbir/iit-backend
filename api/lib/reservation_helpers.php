<?php
/**
 * Reservation helper utilities for queue management and 12-hour borrow windows.
 */

/**
 * Remove expired reservation windows (expires_at in past) for a specific ISBN.
 * Returns number of rows cancelled.
 */
function cleanupExpiredReservationsForIsbn(PDO $db, string $isbn): int {
    $stmt = $db->prepare('UPDATE Reservations
        SET status = "Cancelled"
        WHERE isbn = :isbn
          AND status = "Active"
          AND expires_at IS NOT NULL
          AND expires_at < NOW()');
    $stmt->execute([':isbn' => $isbn]);
    return $stmt->rowCount();
}

/**
 * Re-number queue positions sequentially (1,2,3,...) for active reservations of an ISBN.
 */
function renumberReservationQueue(PDO $db, string $isbn): void {
    $stmt = $db->prepare('SELECT reservation_id FROM Reservations
        WHERE isbn = :isbn AND status = "Active"
        ORDER BY queue_position ASC, reservation_id ASC');
    $stmt->execute([':isbn' => $isbn]);
    $reservations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $pos = 1;
    $update = $db->prepare('UPDATE Reservations SET queue_position = :pos WHERE reservation_id = :rid');
    foreach ($reservations as $rid) {
        $update->execute([':pos' => $pos++, ':rid' => $rid]);
    }
    
    // Notify users of queue position changes
    require_once __DIR__ . '/notification_helpers.php';
    notifyQueuePositionChanges($db, $isbn);
}

/**
 * Activate the next reservation (queue #1) by assigning a 12-hour window if needed.
 * Returns the active top reservation row or null if none.
 */
function activateNextReservation(PDO $db, string $isbn): ?array {
    // Get current top
    $stmt = $db->prepare('SELECT * FROM Reservations
        WHERE isbn = :isbn AND status = "Active"
        ORDER BY queue_position ASC, reservation_id ASC
        LIMIT 1');
    $stmt->execute([':isbn' => $isbn]);
    $top = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$top) {
        return null;
    }

    // If no window or already expired, assign fresh 12-hour window
    $needsWindow = false;
    if (empty($top['expires_at'])) {
        $needsWindow = true;
    } else {
        $expiresAt = new DateTime($top['expires_at']);
        if ($expiresAt < new DateTime()) {
            $needsWindow = true;
        }
    }

    if ($needsWindow) {
        $upd = $db->prepare('UPDATE Reservations
            SET notified_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL 12 HOUR)
            WHERE reservation_id = :rid');
        $upd->execute([':rid' => $top['reservation_id']]);

        // Send notification to user about their 12-hour window
        $bookStmt = $db->prepare('SELECT title FROM Books WHERE isbn = :isbn');
        $bookStmt->execute([':isbn' => $isbn]);
        $bookTitle = $bookStmt->fetchColumn() ?: 'Unknown Book';
        
        require_once __DIR__ . '/notification_helpers.php';
        notifyReservedBookAvailable($db, $top['user_email'], $bookTitle, $isbn, date('Y-m-d H:i:s', strtotime('+12 hours')));

        // Reload to return updated fields
        $stmt->execute([':isbn' => $isbn]);
        $top = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    return $top ?: null;
}

/**
 * Convenience: clean expired, renumber, activate next; return current top reservation row (or null).
 */
function ensureReservationWindow(PDO $db, string $isbn): ?array {
    cleanupExpiredReservationsForIsbn($db, $isbn);
    renumberReservationQueue($db, $isbn);
    return activateNextReservation($db, $isbn);
}

/**
 * Mark a reservation as completed for a user and ISBN, then renumber and activate the next.
 */
function completeReservationForUser(PDO $db, string $isbn, string $userEmail): void {
    $upd = $db->prepare('UPDATE Reservations
        SET status = "Completed"
        WHERE isbn = :isbn AND user_email = :email AND status = "Active"');
    $upd->execute([':isbn' => $isbn, ':email' => $userEmail]);

    renumberReservationQueue($db, $isbn);
    activateNextReservation($db, $isbn);
}
?>
