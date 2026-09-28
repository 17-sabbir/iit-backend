<?php
/**
 * Helper function to clean up expired borrow requests
 * Removes pending requests older than 24 hours
 * This provides runtime cleanup in addition to the scheduled MySQL event
 */

function cleanupExpiredBorrowRequests($db) {
    try {
        $stmt = $db->prepare('
            DELETE FROM Transaction_Requests
            WHERE status = "Pending"
            AND request_date < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ');
        $stmt->execute();
        
        $deletedCount = $stmt->rowCount();
        
        // Log if any requests were deleted
        if ($deletedCount > 0) {
            error_log("Cleaned up $deletedCount expired borrow requests");
        }
        
        return $deletedCount;
    } catch (Exception $e) {
        error_log("Error cleaning up expired requests: " . $e->getMessage());
        return 0;
    }
}

/**
 * Check if a specific request has expired (older than 24 hours)
 */
function isRequestExpired($requestDate) {
    $requestTime = strtotime($requestDate);
    $currentTime = time();
    $hoursDiff = ($currentTime - $requestTime) / 3600;
    
    return $hoursDiff >= 24;
}

/**
 * Get time remaining for a request before expiration
 * Returns array with hours and minutes remaining
 */
function getTimeUntilExpiration($requestDate) {
    $requestTime = strtotime($requestDate);
    $expirationTime = $requestTime + (24 * 3600); // 24 hours from request
    $currentTime = time();
    
    $secondsRemaining = max(0, $expirationTime - $currentTime);
    
    $hours = floor($secondsRemaining / 3600);
    $minutes = floor(($secondsRemaining % 3600) / 60);
    
    return [
        'hours' => $hours,
        'minutes' => $minutes,
        'expired' => $secondsRemaining <= 0
    ];
}
?>
