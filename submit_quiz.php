<?php
// submit_quiz.php — PHP 7.x/8.0 compatible (no array_is_list dependency)

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Student') {
    die("Error: Not authorized.");
}
require 'db_config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

/* -------- Polyfill for array_is_list (PHP < 8.1) -------- */
if (!function_exists('array_is_list')) {
    function array_is_list(array $array): bool {
        $i = 0;
        foreach ($array as $k => $_) {
            if ($k !== $i) return false;
            $i++;
        }
        return true;
    }
}

try {
    $user_id = (int)$_SESSION['user_id'];

    // quiz_id can come from POST or session
    $quiz_id = isset($_POST['quiz_id']) ? (int)$_POST['quiz_id']
              : (isset($_SESSION['quiz_id']) ? (int)$_SESSION['quiz_id'] : 0);
    if ($quiz_id <= 0) throw new Exception("Quiz session data is missing. Please start a quiz first.");

    // get quiz meta (also gives us teacher id)
    $stmt = $conn->prepare("SELECT title, time_limit, created_by FROM quizzes WHERE quiz_id = ? AND is_published = 1 LIMIT 1");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $quiz = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$quiz) throw new Exception("Invalid or unpublished quiz.");

    $time_limit = (int)$quiz['time_limit'];   // seconds
    $teacherId  = (int)$quiz['created_by'];
    $quizTitle  = $quiz['title'];

    // load questions
    $qstmt = $conn->prepare("SELECT question_id, type, text, options, correct_answer FROM questions WHERE quiz_id = ?");
    $qstmt->bind_param("i", $quiz_id);
    $qstmt->execute();
    $res = $qstmt->get_result();
    $questions = [];
    while ($row = $res->fetch_assoc()) {
        $opts = json_decode($row['options'] ?? '[]', true);
        if (!is_array($opts)) $opts = [];

        // normalize options to flat list
        if (!array_is_list($opts)) {
            if (!empty($opts)) {
                ksort($opts);
                $opts = array_values($opts);
            } else {
                $opts = [];
            }
        }
        // ensure strings
        $opts = array_map(function($v){
            return is_string($v) ? $v : (is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE));
        }, $opts);

        $row['options'] = $opts;
        $questions[] = $row;
    }
    $qstmt->close();
    if (!$questions) throw new Exception("This quiz has no questions.");

    // answers[question_id] from POST
    $answers = (isset($_POST['answers']) && is_array($_POST['answers'])) ? $_POST['answers'] : [];

    // helpers
    $toIndex = function ($s) {
        $s = trim((string)$s);
        if ($s === '') return null;
        if (ctype_digit($s)) return (int)$s;         // "0","1","2"
        if (strlen($s) === 1 && ctype_alpha($s)) {   // "A","b"
            return ord(strtolower($s)) - 97;         // a->0,b->1
        }
        return $s; // treat as text
    };
    $norm = function ($s) { return mb_strtolower(trim((string)$s), 'UTF-8'); };

    // scoring
    $total = count($questions);
    $correctCount = 0;

    foreach ($questions as $q) {
        $qid  = (int)$q['question_id'];
        $type = $q['type'];
        $corr = (string)$q['correct_answer'];
        $opts = $q['options'];

        $given = isset($answers[$qid]) ? (string)$answers[$qid] : '';
        if ($given === '') continue;

        if (strcasecmp($type, 'MCQ') === 0) {
            $g = $toIndex($given);
            $c = $toIndex($corr);

            $match = false;
            if (is_int($g) && is_int($c)) {
                $match = ($g === $c);
            } elseif (is_int($g) && is_string($c)) {
                $match = isset($opts[$g]) && ($norm($opts[$g]) === $norm($c));
            } elseif (is_string($g) && is_int($c)) {
                $match = isset($opts[$c]) && ($norm($g) === $norm($opts[$c]));
            } else {
                $match = ($norm((string)$g) === $norm((string)$c));
            }
            if ($match) $correctCount++;

        } elseif (strcasecmp($type, 'TrueFalse') === 0 || strcasecmp($type, 'TF') === 0) {
            $tf = function ($v) use ($norm) {
                $v = $norm($v);
                if (in_array($v, ['t','true','1','yes','y'], true)) return 'true';
                if (in_array($v, ['f','false','0','no','n'], true)) return 'false';
                return $v;
            };
            if ($tf($given) === $tf($corr)) $correctCount++;

        } else {
            if ($norm($given) === $norm($corr)) $correctCount++;
        }
    }

    $scoreInt = $total > 0 ? (int)round(($correctCount / $total) * 100) : 0;

    /* === Time tracking ===
       Use the *raw* elapsed time for “late” detection, so it can exceed the limit.
       Store a clamped value if you want the DB column (completion_time) never to exceed the limit. */
    $started     = isset($_SESSION['start_time']) ? (int)$_SESSION['start_time'] : time();
    $elapsed_raw = max(0, time() - $started);              // real seconds spent (can exceed limit)
    $completion_time_to_save = ($time_limit > 0) ? min($elapsed_raw, $time_limit) : $elapsed_raw;
    $isLate = ($time_limit > 0 && $elapsed_raw > $time_limit);

    // save leaderboard
    $ins = $conn->prepare(
        "INSERT INTO leaderboards (quiz_id, user_id, score, completion_time)
         VALUES (?, ?, ?, ?)"
    );
    $ins->bind_param("iiii", $quiz_id, $user_id, $scoreInt, $completion_time_to_save);
    $ins->execute();
    $ins->close();

    /* ---------------- Notifications ---------------- */

    // student name (for messages)
    $studentName = '';
    $studentStmt = $conn->prepare("SELECT name FROM users WHERE user_id = ? LIMIT 1");
    $studentStmt->bind_param("i", $user_id);
    $studentStmt->execute();
    $stu = $studentStmt->get_result()->fetch_assoc();
    $studentStmt->close();
    if ($stu) {
        $studentName = $stu['name'];
    }

    // 1) Notify teacher if late (use $elapsed_raw so it shows the real overage)
    if ($isLate && $teacherId > 0 && $teacherId != $user_id) {
        $teacherLateMsg = sprintf(
            '%s submitted quiz "%s" late (took %d sec, limit %d sec).',
            $studentName, $quizTitle, $elapsed_raw, $time_limit
        );
        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notifStmt->bind_param("is", $teacherId, $teacherLateMsg);
        $notifStmt->execute();
        $notifStmt->close();
    }

    // 2) Always notify teacher of score
    if ($teacherId > 0 && $teacherId != $user_id) {
        $teacherScoreMsg = sprintf('%s completed your quiz "%s" with score %d%%.', $studentName, $quizTitle, $scoreInt);
        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notifStmt->bind_param("is", $teacherId, $teacherScoreMsg);
        $notifStmt->execute();
        $notifStmt->close();
    }

    // 3) Notify student (their own score)
    $studentMsg = sprintf('You completed quiz "%s" with score %d%%.', $quizTitle, $scoreInt);
    $notifStmt2 = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $notifStmt2->bind_param("is", $user_id, $studentMsg);
    $notifStmt2->execute();
    $notifStmt2->close();

    // cleanup
    unset($_SESSION['quiz_id'], $_SESSION['start_time']);
    $titleSafe = htmlspecialchars($quizTitle, ENT_QUOTES, 'UTF-8');

} catch (Throwable $e) {
    $msg = $e->getMessage();
    $dbMsg = $conn->error ?? '';
    echo "<!doctype html><meta charset='utf-8'><title>Submission Error</title>
    <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:24px;background:#fff7f7}</style>
    <h2 style='margin:0 0 6px;color:#b91c1c'>Couldn't submit your quiz</h2>
    <p style='margin:0 0 14px'>".htmlspecialchars($msg,ENT_QUOTES,'UTF-8')."</p>";
    if ($dbMsg) {
        echo "<pre style='color:#b91c1c;background:#ffe8e8;padding:12px;border-radius:10px;border:1px solid #fecaca'>DB error: "
           .htmlspecialchars($dbMsg,ENT_QUOTES,'UTF-8')."</pre>";
    }
    echo "<p style='margin-top:16px'><a href='javascript:history.back()' style='text-decoration:none;color:#1f2937;border:1px solid #e5e7eb;padding:8px 12px;border-radius:10px'>&larr; Go back</a></p>";
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Quiz Submitted</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  :root{
    /* Calm colour palette for result page */
    --bg:#f7f8fc;
    --card:#ffffff;
    --text:#2f3e4e;
    --muted:#7f8fa6;
    --primary:#6c9bd9;
    --border:#e5e7eb;
    --shadow:0 22px 50px rgba(15,23,42,.12);
    --radius:22px;
  }
  *{box-sizing:border-box}
  body{
    margin:0; background:var(--bg); color:var(--text);
    font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Helvetica,Arial,sans-serif;
    min-height:100vh; display:flex; align-items:center; justify-content:center;
    padding:32px;
  }
  .wrap{width:min(1200px, 96vw);}
  .card{
    background:var(--card); border:1px solid var(--border);
    border-radius:var(--radius); box-shadow:var(--shadow);
    padding:40px;
  }
  .head{display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:18px;}
  .title{font-size:28px;font-weight:900;letter-spacing:.2px}
  .subtitle{color:var(--muted);margin-top:4px;font-size:15px}
  .grid{display:grid; grid-template-columns: 420px 1fr; gap:36px; align-items:center}
  @media (max-width: 980px){ .grid{grid-template-columns:1fr} .card{padding:28px;} }
  .ring{
    --pct: 0;
    width: 340px; height: 340px; border-radius: 50%;
    background: conic-gradient(var(--primary) calc(var(--pct)*1%), #e9edf6 0);
    display:grid; place-items:center; position:relative;
    box-shadow: inset 0 0 0 12px #eef2f7;
    margin-inline:auto;
  }
  .ring::after{ content:""; position:absolute; inset:28px; background:#fff; border-radius:50%; box-shadow: inset 0 1px 0 rgba(0,0,0,.03); }
  .ring .value{ position:relative; z-index:1; text-align:center; }
  .percent{font-size:72px;font-weight:900;line-height:1}
  .badge{display:inline-block;padding:8px 14px;border-radius:999px;font-size:13px;font-weight:800;margin-top:10px;background:#eef2ff;color:#3730a3}
  .facts{display:grid; gap:16px;}
  .fact{
    display:flex; align-items:center; gap:12px; background:#f8fafc;
    border:1px solid var(--border); padding:16px 18px; border-radius:14px;
    font-size:15px;
  }
  .fact .ico{
    width:40px;height:40px;border-radius:12px;display:grid;place-items:center;font-weight:900;color:#fff;
    background:linear-gradient(135deg, #8bbcd9, #6c9bd9);
    flex:0 0 auto;
  }
  .fact .lbl{font-weight:800}
  .fact .val{color:var(--muted);margin-left:auto;font-weight:700}
  .actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px}
  .btn{
    display:inline-flex;align-items:center;gap:8px; padding:14px 18px; border-radius:14px;
    border:1px solid var(--border); background:#fff; color:#0f172a; text-decoration:none; font-weight:800;
  }
  .btn:hover{transform:translateY(-1px); box-shadow:0 12px 24px rgba(15,23,42,.10)}
  .btn.primary{background:var(--primary); border-color:transparent; color:#fff}
  .hint{color:var(--muted); font-size:13px; margin-top:12px}
</style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="head">
        <div>
          <div class="title">Submission Successful</div>
          <div class="subtitle">Quiz: <strong><?php echo $titleSafe; ?></strong></div>
        </div>
      </div>

      <div class="grid">
        <div class="ring" style="--pct: <?php echo (int)$scoreInt; ?>;">
          <div class="value">
            <div class="percent"><?php echo number_format($scoreInt); ?>%</div>
            <?php $badgeText = $scoreInt >= 80 ? 'Excellent' : ($scoreInt >= 50 ? 'Good job' : 'Keep practicing'); ?>
            <div class="badge"><?php echo $badgeText; ?></div>
          </div>
        </div>

        <div>
          <div class="facts">
            <div class="fact">
              <div class="ico">✓</div>
              <div class="lbl">Correct Answers</div>
              <div class="val"><?php echo $correctCount; ?> / <?php echo $total; ?></div>
            </div>
            <div class="fact">
              <div class="ico">⏱</div>
              <div class="lbl">Time Spent</div>
              <div class="val"><?php echo (int)$completion_time_to_save; ?> sec</div>
            </div>
          </div>

          <div class="actions">
            <a class="btn" href="student_dashboard.php">← Back to Dashboard</a>
            <a class="btn primary" href="leaderboard.php?quiz_id=<?php echo (int)$quiz_id; ?>">View Leaderboard</a>
          </div>
          <div class="hint">Your score has been recorded. You can revisit the leaderboard any time.</div>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function(){
      // clear any saved progress for this quiz in localStorage
      var key = 'quizProgress:' + <?php echo (int)$quiz_id; ?>;
      try { localStorage.removeItem(key); } catch(e) {}
    })();
  </script>
</body>
</html>
