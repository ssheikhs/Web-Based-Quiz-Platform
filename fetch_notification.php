<?php
// fetch_notifications.php
// Provide a JSON feed of notifications for the currently logged in user.

session_start();
header('Content-Type: application/json');

// Default response
$response = [
    'count' => 0,
    'notifications' => []
];

// Ensure the user is authenticated
if (!isset($_SESSION['user_id'])) {
    echo json_encode($response);
    exit;
}

require_once 'db_config.php';

$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';

// Fetch notifications from database
try {
    $stmt = $conn->prepare("
        SELECT id, message, link, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC, id DESC 
        LIMIT 20
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => (int)$row['id'],
            'message' => $row['message'],
            'link' => $row['link'] ?? '',
            'is_read' => (int)$row['is_read'],
            'created_at' => $row['created_at']
        ];
    }
    $stmt->close();
    
    // Count unread notifications
    $unreadCount = 0;
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $countStmt->bind_param("i", $userId);
    $countStmt->execute();
    $countStmt->bind_result($unreadCount);
    $countStmt->fetch();
    $countStmt->close();
    
    $response = [
        'count' => $unreadCount,
        'notifications' => $notifications
    ];
    
} catch (Exception $e) {
    // If database fetch fails, return empty response
    error_log("Error fetching notifications: " . $e->getMessage());
}

echo json_encode($response);
exit;