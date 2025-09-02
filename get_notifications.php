<?php
// get_notifications.php (mysqlnd not required)
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['unread' => 0, 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once 'db_config.php';

$user_id = (int)$_SESSION['user_id'];

// Unread count
$unread = 0;
if ($stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($unread);
    $stmt->fetch();
    $stmt->close();
}

// Recent items (no get_result)
$items = [];
$sql = "SELECT id, COALESCE(link,'') AS link, message, is_read, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT 30";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($id, $link, $message, $is_read, $created_at);
    while ($stmt->fetch()) {
        $items[] = [
            'id'         => (int)$id,
            'message'    => (string)$message,
            'link'       => (string)$link,
            'is_read'    => (int)$is_read,
            'created_at' => (string)$created_at,
        ];
    }
    $stmt->close();
}

echo json_encode(['unread' => (int)$unread, 'items' => $items], JSON_UNESCAPED_UNICODE);
