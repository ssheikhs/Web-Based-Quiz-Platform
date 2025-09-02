<?php
// notification_helper.php
// Common helpers for creating notifications.

if (!function_exists('notify_user')) {
    function notify_user(mysqli $conn, int $receiver_user_id, string $message, ?string $link = null): bool {
        $sql = "INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("iss", $receiver_user_id, $message, $link);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

/**
 * Create a notification to the quiz’s Instructor when a student submits late.
 * Late means completion_time > time_limit (both in seconds).
 */
if (!function_exists('notify_late_submission')) {
    function notify_late_submission(mysqli $conn, int $student_id, int $quiz_id, int $completion_time): void {
        // Fetch quiz meta: time_limit & instructor
        $sql = "SELECT q.title, q.time_limit, q.created_by AS instructor_id,
                       u.name AS student_name
                FROM quizzes q
                JOIN users u ON u.user_id = ?
                WHERE q.quiz_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $student_id, $quiz_id);
        $stmt->execute();
        $stmt->bind_result($quiz_title, $time_limit, $instructor_id, $student_name);
        if ($stmt->fetch()) {
            $stmt->close();

            if ((int)$completion_time > (int)$time_limit) {
                $over = (int)$completion_time - (int)$time_limit;
                // show minutes:seconds
                $mins = floor($over / 60);
                $secs = $over % 60;
                $over_str = sprintf("%d:%02d", $mins, $secs);

                $msg  = sprintf("Late submission: %s submitted '%s' %s late.",
                                $student_name ?: "A student",
                                $quiz_title ?: "a quiz",
                                $over_str);

                // Optional deep link to your leaderboard / quiz detail:
                $link = "analytics.php"; // change if you have a per-quiz page, e.g. analytics.php?quiz_id={$quiz_id}

                notify_user($conn, (int)$instructor_id, $msg, $link);
            }
        } else {
            $stmt->close();
        }
    }
}

/**
 * Optional: notify student with their score after submit (non-late).
 * Call from the same place you log to leaderboard.
 */
if (!function_exists('notify_student_score')) {
    function notify_student_score(mysqli $conn, int $student_id, int $quiz_id, int $score): void {
        $sql = "SELECT title FROM quizzes WHERE quiz_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $stmt->bind_result($quiz_title);
        $stmt->fetch();
        $stmt->close();

        $msg  = sprintf("Your submission for '%s' is recorded. Score: %d%%.", $quiz_title ?: "Quiz", $score);
        $link = "student_dashboard.php"; // or a quiz result page
        notify_user($conn, $student_id, $msg, $link);
    }
}
