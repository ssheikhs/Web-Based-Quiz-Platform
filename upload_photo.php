<?php
// upload_photo.php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Not authorized";
    exit;
}

include 'db_config.php'; // include DB connection

if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['photo'];

    // Validation: type + size
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (in_array($file['type'], $allowedTypes, true) && $file['size'] <= 2 * 1024 * 1024) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $newName = 'user_' . (int)$_SESSION['user_id'] . '_' . time() . '.' . $ext;

        $uploadDir = __DIR__ . '/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $target = $uploadDir . '/' . $newName;

        if (move_uploaded_file($file['tmp_name'], $target)) {
            // Save relative path
            $photoPath = 'uploads/' . $newName;

            // Update in database
            $stmt = $conn->prepare("UPDATE users SET photo = ? WHERE user_id = ?");
            $stmt->bind_param("si", $photoPath, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();

            // Update in session too
            $_SESSION['photo'] = $photoPath;
        }
    }
}

// Redirect back
$redirectTo = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header("Location: " . $redirectTo);
exit;
