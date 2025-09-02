<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Instructor') {
    header("Location: login.php");
    exit;
}
include 'db_config.php';

$user_id = (int)$_SESSION['user_id'];
$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($quiz_id <= 0) {
    die("Invalid quiz.");
}

/* ---------- Handle POST actions ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = isset($_POST['action']) ? $_POST['action'] : 'update';

    // Ensure the quiz belongs to the logged in instructor before any write
    $ownStmt = $conn->prepare("SELECT 1 FROM quizzes WHERE quiz_id = ? AND created_by = ? LIMIT 1");
    $ownStmt->bind_param("ii", $quiz_id, $user_id);
    $ownStmt->execute();
    $ownStmt->store_result();
    $isOwner = $ownStmt->num_rows === 1;
    $ownStmt->close();

    if (!$isOwner) {
        die("Unauthorized.");
    }

    if ($action === 'delete') {
        // Delete dependent rows first (FK order can vary)
        if ($stmt = $conn->prepare("DELETE FROM questions WHERE quiz_id = ?")) {
            $stmt->bind_param("i", $quiz_id);
            $stmt->execute();
            $stmt->close();
        }
        if ($stmt = $conn->prepare("DELETE FROM leaderboards WHERE quiz_id = ?")) {
            $stmt->bind_param("i", $quiz_id);
            $stmt->execute();
            $stmt->close();
        }
        if ($stmt = $conn->prepare("DELETE FROM quizzes WHERE quiz_id = ? AND created_by = ?")) {
            $stmt->bind_param("ii", $quiz_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: my_quizzes.php");
        exit;
    }

    // ----- Update flow (default) -----
    // Remove old questions
    if ($stmt = $conn->prepare("DELETE FROM questions WHERE quiz_id = ?")) {
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $stmt->close();
    }

    $title       = $_POST['title'];
    $time_limit  = ((int)$_POST['time_limit']) * 60; // minutes -> seconds
    $category_id = (int)$_POST['category_id'];

    if ($stmt = $conn->prepare("UPDATE quizzes SET title = ?, time_limit = ?, category_id = ? WHERE quiz_id = ? AND created_by = ?")) {
        $stmt->bind_param("siiii", $title, $time_limit, $category_id, $quiz_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    // Re-insert questions
    if (!empty($_POST['questions']) && is_array($_POST['questions'])) {
        foreach ($_POST['questions'] as $q) {
            $text    = $q['text'] ?? '';
            $type    = $q['type'] ?? 'MCQ';
            $correct = $q['correct'] ?? '';

            if ($type === 'MCQ') {
                $options = json_encode($q['options'] ?? []);
            } else { // True/False fallback
                $options = json_encode(['True' => 'True', 'False' => 'False']);
            }

            if ($stmt = $conn->prepare("INSERT INTO questions (quiz_id, text, type, options, correct_answer) VALUES (?, ?, ?, ?, ?)")) {
                $stmt->bind_param("issss", $quiz_id, $text, $type, $options, $correct);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    header("Location: my_quizzes.php");
    exit;
}

/* ---------- Fetch current quiz + questions (read-only) ---------- */
$qstmt = $conn->prepare("SELECT * FROM quizzes WHERE quiz_id = ? AND created_by = ? LIMIT 1");
$qstmt->bind_param("ii", $quiz_id, $user_id);
$qstmt->execute();
$quiz = $qstmt->get_result()->fetch_assoc();
$qstmt->close();

if (!$quiz) {
    die("Quiz not found or you do not have access.");
}

$questions = [];
$qs = $conn->prepare("SELECT * FROM questions WHERE quiz_id = ?");
$qs->bind_param("i", $quiz_id);
$qs->execute();
$res = $qs->get_result();
while ($row = $res->fetch_assoc()) {
    $questions[] = $row;
}
$qs->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Quiz</title>
    <link rel="stylesheet" href="style.css">
    <script src="script.js"></script>
    <style>
        /* light touch so it stays consistent with your current UI */
        .toolbar { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:16px; }
        /* Calm colors for buttons */
        .danger {
            background: #f6a6b2;
            color: #fff;
            border: none;
            padding: 10px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .danger:hover {
            background: #e58fa0;
        }
        .btn {
            background: #8e97fd;
            color: #fff;
            border: none;
            padding: 10px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn:hover {
            background: #7a82e8;
        }
        .field { margin-bottom:12px; }
        .field input, .field select { width:100%; padding:10px; border:1px solid #ccc; border-radius:8px; }
        .card { max-width:900px; margin:20px auto; padding:20px; background:#fff; border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,.08); }
        #questions { margin-top:16px; }
    </style>
</head>
<body>
<div class="card">
    <div class="toolbar">
        <h2>Edit Quiz</h2>

        <!-- Delete quiz (separate form for safety) -->
        <form method="post" onsubmit="return confirm('Delete this quiz permanently? This will remove its questions and leaderboard entries.');">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="danger">Delete Quiz</button>
        </form>
    </div>

    <!-- Update form -->
    <form method="post">
        <input type="hidden" name="action" value="update">

        <div class="field">
            <input type="text" name="title" value="<?php echo htmlspecialchars($quiz['title']); ?>" placeholder="Quiz Title" required>
        </div>

        <div class="field">
            <input type="number" name="time_limit" value="<?php echo (int)$quiz['time_limit'] / 60; ?>" min="1" placeholder="Time Limit (minutes)" required>
        </div>

        <div class="field">
            <select name="category_id" required>
                <?php
                $cat_result = $conn->query("SELECT * FROM quiz_categories ORDER BY name");
                while ($cat = $cat_result->fetch_assoc()) {
                    $selected = ((int)$cat['category_id'] === (int)$quiz['category_id']) ? 'selected' : '';
                    echo "<option value='".(int)$cat['category_id']."' $selected>".htmlspecialchars($cat['name'])."</option>";
                }
                ?>
            </select>
        </div>

        <div id="questions">
            <!-- You can preload existing questions here or let your JS add/edit -->
            <!-- Example preload (optional, keep simple as your note suggests): -->
            <?php if (!empty($questions)): ?>
                <?php foreach ($questions as $index => $q): ?>
                    <script>
                        // Pre-add with JS helpers so names & indexes match your addQuestion structure
                        window.addEventListener('DOMContentLoaded', function() {
                            addQuestion('<?php echo $q['type'] === 'TrueFalse' ? 'TF' : 'MCQ'; ?>');
                            const container = document.querySelector('.question:last-of-type');
                            if (!container) return;
                            container.querySelector('textarea[name$="[text]"]').value = <?php echo json_encode($q['text']); ?>;
                            const typeSel = container.querySelector('select[name$="[type]"]');
                            if (typeSel) typeSel.value = <?php echo json_encode($q['type'] === 'TrueFalse' ? 'TF' : 'MCQ'); ?>;

                            <?php
                            $opts = json_decode($q['options'], true);
                            if (!is_array($opts)) $opts = [];
                            ?>

                            <?php if ($q['type'] === 'MCQ'): ?>
                                // populate MCQ options
                                const opts = <?php echo json_encode(array_values($opts)); ?>;
                                const optWrap = container.querySelector('.option-inputs');
                                // Ensure at least two inputs exist already then append remainder
                                opts.forEach((val, i) => {
                                    if (i < 2) {
                                        const inp = optWrap.children[i]?.querySelector('input');
                                        if (inp) inp.value = val;
                                    } else {
                                        addOption(container.dataset.questionId);
                                        const last = optWrap.lastElementChild.querySelector('input');
                                        if (last) last.value = val;
                                    }
                                });
                                // set correct
                                const correctSel = container.querySelector('select[name$="[correct]"]');
                                if (correctSel) {
                                    correctSel.value = <?php echo json_encode($q['correct_answer']); ?>;
                                }
                            <?php else: ?>
                                // True/False: just set correct
                                const correctSelTF = container.querySelector('select[name$="[correct]"]');
                                if (correctSelTF) {
                                    correctSelTF.value = <?php echo json_encode($q['correct_answer']); ?>;
                                }
                            <?php endif; ?>
                        });
                    </script>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="display:flex; gap:10px; margin-top:12px;">
            <button type="button" class="btn" onclick="addQuestion()">Add Question</button>
            <button type="submit" class="btn">Update Quiz</button>
        </div>
    </form>
</div>
</body>
</html>
