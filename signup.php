<?php
session_start();
include 'db_config.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    // Password strength validation
    if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password)) {
        $message = "⚠️ Password must be at least 8 characters long, include 1 uppercase letter, 1 number, and 1 special character.";
    } elseif ($password !== $confirm) {
        $message = "❌ Passwords do not match!";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        if (!$stmt) { die("SQL Error: " . $conn->error); }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message = "⚠️ Email already registered. Please log in.";
        } else {
            // Store password as PLAIN TEXT in password_hash column
            $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'Student')");
            if (!$stmt) { die("SQL Error: " . $conn->error); }
            $stmt->bind_param("sss", $name, $email, $password);

            if ($stmt->execute()) {
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['role'] = "Student";
                $_SESSION['name'] = $name;
                header("Location: student_dashboard.php");
                exit;
            } else {
                $message = "❌ Error: Could not sign up. Please try again.";
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Sign Up</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f8fb; }
        .signup-container {
            max-width:400px; margin:60px auto; padding:30px;
            background:#fff; border-radius:10px; box-shadow:0 6px 15px rgba(0,0,0,0.1);
        }
        h2 { text-align:center; color:#0077ff; }
        .input-group { position: relative; margin: 10px 0; }
        input {
            width:100%; padding:10px; border:1px solid #ccc; border-radius:6px;
        }
        .toggle-password {
            position:absolute; right:10px; top:50%; transform:translateY(-50%);
            cursor:pointer; font-size:14px; color:#0077ff; user-select:none;
        }
        button {
            width:100%; padding:12px; background:#0077ff; color:#fff;
            font-size:16px; border:none; border-radius:6px; cursor:pointer;
        }
        button:hover { background:#005fcc; }
        .message { color:red; text-align:center; }
        .link { text-align:center; margin-top:10px; }
    </style>
</head>
<body>
    <div class="signup-container">
        <h2>Student Sign Up</h2>
        <?php if ($message) echo "<p class='message'>$message</p>"; ?>
        <form method="post">
            <input type="text" name="name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email" required>

            <div class="input-group">
                <input type="password" name="password" id="password" placeholder="Password"
                       pattern="^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$"
                       title="Must be 8+ chars with 1 uppercase, 1 number, and 1 special character" required>
                <span class="toggle-password" onclick="togglePassword('password', this)">👁️</span>
            </div>

            <div class="input-group">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
                <span class="toggle-password" onclick="togglePassword('confirm_password', this)">👁️</span>
            </div>

            <button type="submit">Sign Up</button>
        </form>
        <div class="link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, icon) {
            const field = document.getElementById(fieldId);
            if (field.type === "password") { field.type = "text"; icon.textContent = "🙈"; }
            else { field.type = "password"; icon.textContent = "👁️"; }
        }
    </script>
</body>
</html>
