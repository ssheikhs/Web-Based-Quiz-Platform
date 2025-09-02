<?php
session_start();
include 'db_config.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Fetch plain-text password from password_hash column
    $stmt = $conn->prepare("SELECT user_id, password_hash, role, name FROM users WHERE email = ?");
    if (!$stmt) { die("SQL Error: " . $conn->error); }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($user_id, $dbPassword, $role, $name);

    if ($stmt->fetch()) {
        // Direct comparison (plain text)
        if ($password === $dbPassword) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role'] = $role;
            $_SESSION['name'] = $name;

            if ($role === 'Instructor') {
                header("Location: instructor_dashboard.php");
            } else {
                header("Location: student_dashboard.php");
            }
            exit;
        } else {
            $message = "❌ Invalid email or password!";
        }
    } else {
        $message = "⚠️ No account found with that email!";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - QuickQuiz</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-box {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            width: 360px;
            text-align: center;
            animation: fadeIn 1s ease;
        }
        .login-box h2 {
            margin: 0 0 20px;
            color: #0077ff;
            font-size: 26px;
        }
        .login-box input {
            width: 100%;
            padding: 12px;
            margin: 12px 0;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 15px;
            transition: border 0.2s;
        }
        .login-box input:focus {
            border-color: #0077ff;
            outline: none;
            box-shadow: 0 0 6px rgba(0,119,255,0.3);
        }
        .login-box button {
            width: 100%;
            padding: 14px;
            margin-top: 10px;
            background: #0077ff;
            color: #fff;
            font-size: 16px;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
        }
        .login-box button:hover {
            background: #005fcc;
            transform: translateY(-2px);
        }
        .message {
            margin: 10px 0;
            color: red;
            font-size: 14px;
        }
        .extra-links {
            margin-top: 15px;
            font-size: 14px;
        }
        .extra-links a {
            color: #0077ff;
            text-decoration: none;
            font-weight: 500;
        }
        .extra-links a:hover {
            text-decoration: underline;
        }
        @keyframes fadeIn {
            from {opacity: 0; transform: translateY(-30px);}
            to {opacity: 1; transform: translateY(0);}
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>🔐 Quiz Hero Login</h2>
        <?php if ($message) echo "<p class='message'>$message</p>"; ?>
        <form method="post">
            <input type="email" name="email" placeholder="Enter your Email" required>
            <input type="password" name="password" placeholder="Enter your Password" required>
            <button type="submit">Login</button>
        </form>
        <div class="extra-links">
            Don’t have an account? <a href="signup.php">Sign up here</a>
        </div>
    </div>
</body>
</html>
