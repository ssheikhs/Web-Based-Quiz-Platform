<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Student') {
    header("Location: login.php");
    exit;
}
include 'db_config.php';

// --- Validate & load quiz
$access_code = isset($_GET['access_code']) ? trim($_GET['access_code']) : '';
if ($access_code === '') {
    die("Missing access code");
}

$stmt = $conn->prepare("SELECT * FROM quizzes WHERE access_code = ? AND is_published = 1 LIMIT 1");
$stmt->bind_param("s", $access_code);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quiz) {
    die("Invalid or unpublished quiz");
}

$quiz_id = (int)$quiz['quiz_id'];

// --- Notification
if (isset($_SESSION['user_id'])) {
    $currentUserId = (int)$_SESSION['user_id'];
    $quizTitleForNotif = $quiz['title'] ?? '';
    $startMsg = sprintf('You started quiz \"%s\".', $quizTitleForNotif);
    $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $notifStmt->bind_param("is", $currentUserId, $startMsg);
    $notifStmt->execute();
    $notifStmt->close();
}

// --- Load questions
$qstmt = $conn->prepare("SELECT question_id, text, type, options FROM questions WHERE quiz_id = ? ORDER BY question_id ASC");
$qstmt->bind_param("i", $quiz_id);
$qstmt->execute();
$res = $qstmt->get_result();

$questions = [];
while ($q = $res->fetch_assoc()) {
    $opts = json_decode($q['options'] ?? '[]', true);
    if (!is_array($opts)) $opts = [];
    $q['options'] = $opts;
    $questions[] = $q;
}
$qstmt->close();

$_SESSION['start_time'] = time();
$_SESSION['quiz_id']    = $quiz_id;

$timeLimitSeconds = (int)$quiz['time_limit'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Take Quiz: <?php echo htmlspecialchars($quiz['title']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        :root{
            --bg1:#1a0b2e;  /* deep violet */
            --bg2:#3b0a45;  /* plum */
            --brand1:#a855f7; /* purple */
            --brand2:#ec4899; /* pink-magenta */
            --accent:#d946ef; /* fuchsia */
            --card: rgba(40,20,60,0.78);
            --cardBorder: rgba(255,255,255,0.12);
            --text:#f5e9ff;
            --muted:#c4a5e1;
            --row:#291a3f;
            --rowAlt:#331b4d;
            --rowHover: rgba(168,85,247,0.18);
            --thead: rgba(255,255,255,0.05);
            --shadow: 0 12px 30px rgba(0,0,0,0.45);
            --radius:16px;
            --radiusSm:10px;
            --focus: 0 0 0 3px rgba(168,85,247,0.45);
        }
        @keyframes gradientShift {
            0%{background-position:0% 50%}
            50%{background-position:100% 50%}
            100%{background-position:0% 50%}
        }
        *{box-sizing:border-box}
        body{
            margin:0;
            font-family:"Segoe UI", Tahoma, sans-serif;
            background:
              radial-gradient(1200px 700px at 20% 10%, #6b21a8 0%, transparent 60%),
              radial-gradient(900px 700px at 80% 20%, #9d174d 0%, transparent 60%),
              linear-gradient(120deg, var(--bg1), var(--bg2));
            background-size:400% 400%;
            animation:gradientShift 20s ease infinite;
            color:var(--text);
            padding:40px 20px;
        }

        .wrap{max-width:980px;margin:0 auto}
        .card{
            background:var(--card);
            border:1px solid var(--cardBorder);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            overflow:hidden;
        }

        /* Header */
        .cardHeader{
            display:grid;
            grid-template-columns:auto 1fr auto;
            align-items:center;
            gap:12px;
            padding:18px 24px;
            background: linear-gradient(90deg, var(--brand1), var(--brand2));
            color:#fff;
        }
        .back{
            display:inline-flex;align-items:center;gap:10px;
            padding:8px 12px;border-radius:12px;
            background: rgba(255,255,255,0.12);
            color:#fff;text-decoration:none;font-weight:700;
            border:1px solid rgba(255,255,255,0.18);
            transition: transform .12s ease, background .2s ease;
        }
        .back:hover{background: rgba(255,255,255,0.18); transform: translateY(-1px)}

        .headerTitle{
            font-size:22px;
            font-weight:800;
            text-align:center;
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        }
        .timerBadge{
            display:inline-flex;align-items:center;gap:8px;
            padding:8px 12px;border-radius:999px;
            background: rgba(0,0,0,0.28);
            border:1px solid rgba(255,255,255,0.22);
            font-weight:800;
        }
        .timerBadge.time-warning{ background: rgba(220,38,38,0.28); }

        .cardBody{padding:18px}

        #question-nav{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px;}
        #question-nav .nav-item{
            padding:8px 12px;border-radius:999px;cursor:pointer;
            background:linear-gradient(180deg, var(--row), var(--rowAlt));
            color:var(--text);border:1px solid var(--cardBorder);
        }
        #question-nav .nav-item.active{
            background: linear-gradient(90deg, var(--brand1), var(--brand2));
            border-color:transparent;
        }

        .quiz-question{
            display:none;
            padding:16px;
            border:1px solid var(--cardBorder);
            border-radius:12px;
            background:linear-gradient(180deg, var(--row), var(--rowAlt));
            margin-bottom:14px;
        }
        .quiz-question.active{display:block;}
        .quiz-question p{margin:0 0 12px;font-weight:700;color:#fff}

        .options label{
            display:flex;align-items:center;gap:10px;
            padding:10px 12px;margin:8px 0;border-radius:10px;
            background:rgba(255,255,255,0.06);
            border:1px solid var(--cardBorder);cursor:pointer;
        }
        .options label:hover{background:rgba(255,255,255,0.1);}
        .options input{accent-color: var(--brand1);}

        .actions{text-align:center;margin-top:18px;}
        .btn-primary{
            padding:12px 22px;border-radius:12px;
            background: linear-gradient(90deg,var(--brand1),var(--brand2));
            color:#fff;border:none;font-weight:800;cursor:pointer;
            box-shadow:0 6px 16px rgba(0,0,0,0.25);
        }
        .btn-primary:hover{transform:translateY(-1px);}
    </style>
    <script src="quiz_script.js"></script>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="cardHeader">
            <a class="back" href="student_dashboard.php">⬅ Back</a>
            <div class="headerTitle">Take Quiz – <?php echo htmlspecialchars($quiz['title']); ?></div>
            <div id="timer" class="timerBadge">Time left: <?php echo $timeLimitSeconds; ?>s</div>
        </div>
        <div class="cardBody">
            <div id="question-nav"></div>
            <form id="quiz-form" method="post" action="submit_quiz.php">
                <input type="hidden" name="quiz_id" value="<?php echo (int)$quiz_id; ?>">
                <div id="questions_container"></div>
                <div class="actions">
                    <button type="submit" class="btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const questions = <?php echo json_encode($questions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
const timeLimit = <?php echo $timeLimitSeconds; ?>;
const normalized = (questions||[]).map(q=>{
    const opts=q.options||[]; let arr=[];
    if(Array.isArray(opts)){arr=opts.map(v=>typeof v==='string'?v:(v?.text??String(v)));}
    else if(typeof opts==='object'){arr=Object.keys(opts).map(k=>String(opts[k]));}
    q.options=arr;return q;
});
function startWhenReady(){
    if(typeof renderQuiz==='function'){
        renderQuiz(normalized,timeLimit);
        let timeLeft=timeLimit; const timerEl=document.getElementById('timer');
        window.quizTimer={timer:setInterval(()=>{
            timeLeft--; timerEl.textContent=`Time left: ${timeLeft}s`;
            if(timeLeft<60) timerEl.classList.add('time-warning');
            if(timeLeft<=0){clearInterval(window.quizTimer.timer);
                alert("⏰ Time is up! Submitting your quiz...");
                document.getElementById('quiz-form').submit();}
        },1000)};
    } else setTimeout(startWhenReady,50);
}
startWhenReady();
</script>
</body>
</html>
