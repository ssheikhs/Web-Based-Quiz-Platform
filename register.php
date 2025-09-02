<?php
session_start();
include 'db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $name = $_POST['name'];
    $role = $_POST['role'];

    $stmt = $conn->prepare("INSERT INTO users (email, password_hash, name, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $email, $password, $name, $role);

    if ($stmt->execute()) {
        header("Location: login.php");
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <form method="post">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="text" name="name" placeholder="Name" required>
        <select name="role">
            <option value="Instructor">Instructor</option>
            <option value="Student">Student</option>
        </select>
        <button type="submit">Register</button>
    </form>
</body>
</html>