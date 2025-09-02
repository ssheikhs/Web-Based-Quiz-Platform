<?php
// mark_notifications_read.php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

require_once 'db_config.php';

$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

// No body, just 204
http_response_code(204);
