<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Instructor') {
    header("Location: login.php");
}
include 'db_config.php';

$quiz_id = $_GET['id'];

// Generate unique access code
do {
    $access_code = substr(md5(rand()), 0, 8);
    $check = $conn->query("SELECT * FROM quizzes WHERE access_code = '$access_code'")->num_rows;
} while ($check > 0);

$stmt = $conn->prepare("UPDATE quizzes SET is_published = 1, access_code = ? WHERE quiz_id = ? AND created_by = ?");
$stmt->bind_param("sii", $access_code, $quiz_id, $_SESSION['user_id']);
$stmt->execute();
$stmt->close();

header("Location: my_quizzes.php");
?>