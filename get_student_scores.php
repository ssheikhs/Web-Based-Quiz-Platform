<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Student') {
    http_response_code(403);
    exit;
}
include 'db_config.php';

$user_id = $_SESSION['user_id'];

$result = $conn->query("
    SELECT q.title AS quiz_title, l.score, l.completion_time
    FROM leaderboards l
    JOIN quizzes q ON l.quiz_id = q.quiz_id
    WHERE l.user_id = $user_id
    ORDER BY l.entry_id DESC
    LIMIT 10
");

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        "quiz_title" => $row['quiz_title'],
        "score" => (float)$row['score'], // ensure numeric
        "completion_time" => $row['completion_time']
    ];
}

header("Content-Type: application/json");
echo json_encode(array_reverse($data));
