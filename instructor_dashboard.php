<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Instructor') {
    header("Location: login.php");
    exit;
}
include 'db_config.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$instructor_id = (int)$_SESSION['user_id'];
$teacherName   = isset($_SESSION['name']) && $_SESSION['name'] !== '' ? $_SESSION['name'] : 'Instructor';
$avatarUrl     = isset($_SESSION['photo']) && is_string($_SESSION['photo']) && $_SESSION['photo'] !== '' ? $_SESSION['photo'] : '';

function initials_from_name($name) {
    $name = trim((string)$name);
    if ($name === '') return 'IN';
    $parts = preg_split('/\s+/', $name);
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $second = mb_substr($parts[1] ?? '', 0, 1);
    return mb_strtoupper($first . ($second ?: ''));
}

/* -------- Stats: quizzes & participants for THIS instructor -------- */
$quizzes_count = 0;
$participants_total = 0;

$stmt = $conn->prepare("SELECT COUNT(*) FROM quizzes WHERE created_by = ?");
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$stmt->bind_result($quizzes_count);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM leaderboards l 
    JOIN quizzes q ON q.quiz_id = l.quiz_id
    WHERE q.created_by = ?
");
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$stmt->bind_result($participants_total);
$stmt->fetch();
$stmt->close();

/* -------- Per-quiz aggregates for charts -------- */
$chart_labels = [];
$chart_participants = [];
$chart_avg_scores = [];

$stmt = $conn->prepare("
    SELECT q.quiz_id, q.title,
           COUNT(l.user_id) AS cnt,
           COALESCE(ROUND(AVG(l.score),2), 0) AS avg_score
    FROM quizzes q
    LEFT JOIN leaderboards l ON l.quiz_id = q.quiz_id
    WHERE q.created_by = ?
    GROUP BY q.quiz_id, q.title
    ORDER BY q.quiz_id DESC
");
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$res = $stmt->get_result();
$quiz_meta = []; // quiz_id => title
while ($row = $res->fetch_assoc()) {
    $chart_labels[]       = $row['title'];
    $chart_participants[] = (int)$row['cnt'];
    $chart_avg_scores[]   = (float)$row['avg_score'];
    $quiz_meta[(int)$row['quiz_id']] = $row['title'];
}
$stmt->close();

/* -------- Participant rows (per quiz) -------- */
$participants_by_quiz = []; // quiz_id => rows
$stmt = $conn->prepare("
    SELECT q.quiz_id, q.title, u.name AS student_name, l.score, l.entry_id
    FROM leaderboards l
    JOIN quizzes q ON q.quiz_id = l.quiz_id
    JOIN users u ON u.user_id = l.user_id
    WHERE q.created_by = ?
    ORDER BY q.quiz_id DESC, l.entry_id DESC
");
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $qid = (int)$row['quiz_id'];
    if (!isset($participants_by_quiz[$qid])) $participants_by_quiz[$qid] = [];
    $participants_by_quiz[$qid][] = [
        'student_name' => $row['student_name'],
        'score'        => $row['score']
    ];
}
$stmt->close();

/* For the quiz filter dropdown */
$my_quizzes = [];
if (!empty($quiz_meta)) {
    foreach ($quiz_meta as $qid => $title) {
        $my_quizzes[] = ['id'=>$qid, 'title'=>$title];
    }
} else {
    // Fallback: fetch titles in case there were no leaderboard entries yet
    $stmt = $conn->prepare("SELECT quiz_id, title FROM quizzes WHERE created_by = ? ORDER BY quiz_id DESC");
    $stmt->bind_param("i", $instructor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $my_quizzes[] = ['id'=>(int)$r['quiz_id'], 'title'=>$r['title']];
    }
    $stmt->close();
}

$teacherInitials = initials_from_name($teacherName);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Instructor Dashboard | QuickQuiz</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Futuristic Navy-Blue Dynamic Theme -->
    <style>
        :root{
            /* Core brand for charts & accents (matches student bar style but bluer) */
            --brand1:#6366f1;  /* indigo-500 */
            --brand2:#60a5fa;  /* sky-400 */
            --accent:#22d3ee;  /* cyan-400 */

            /* Futuristic navy gradient + glass */
            --bg1:#070d1a;
            --bg2:#0b1e3a;
            --bg3:#0e2a54;
            --bg4:#0b1e3a;
            --glass: rgba(255,255,255,0.06);
            --glassBorder: rgba(255,255,255,0.12);

            /* Texts */
            --text:#e6eefc;
            --muted:#a7b4d6;

            /* Neutrals */
            --dark:#0b1220;
            --light:#122138;
            --line:#20324d;

            --radius:12px;
            --shadow: 0 10px 30px rgba(0,0,0,.45);
            --trans: all .25s ease;
        }

        @keyframes galaxyShift{
            0%  { background-position: 0% 50%; }
            50% { background-position:100% 50%; }
            100%{ background-position: 0% 50%; }
        }

        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;}
        body{
            min-height:100vh; display:flex; color:var(--text);
            background:
              radial-gradient(1000px 600px at 15% 10%, #133c7a 0%, transparent 60%),
              radial-gradient(900px 600px at 85% 20%, #0a445a 0%, transparent 60%),
              linear-gradient(120deg, var(--bg1), var(--bg2), var(--bg3), var(--bg4));
            background-size: 400% 400%;
            animation: galaxyShift 22s ease infinite;
        }

        /* Sidebar (glass/dark) */
        .sidebar{
            width:280px; padding:22px 0; backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            background: rgba(8,14,28,.6); border-right:1px solid var(--glassBorder);
            display:flex; flex-direction:column; box-shadow:var(--shadow);
        }
        .sidebar .logo{
            padding:0 22px 18px; margin-bottom:16px; border-bottom:1px solid var(--glassBorder);
            font-size:22px; font-weight:800; display:flex; align-items:center; color:#fff;
            letter-spacing:.3px;
        }
        .logo i{ margin-right:10px; color: var(--accent); }
        .sidebar ul{list-style:none; flex-grow:1;}
        .sidebar ul li{
            margin:6px 0;
        }
        .sidebar ul li a{
            display:flex; align-items:center; gap:12px;
            color:#eaf1ff; text-decoration:none; padding:12px 22px; position:relative;
            border-left:3px solid transparent; transition:var(--trans);
        }
        .sidebar ul li a i{ width:20px; text-align:center; }
        .sidebar ul li a:hover{
            background: rgba(255,255,255,0.06);
            border-left-color: var(--brand2);
        }
        .sidebar ul li.active a{
            background: linear-gradient(90deg, rgba(99,102,241,.18), rgba(96,165,250,.18));
            border-left-color: var(--brand1);
        }

        /* Main */
        .main-content{ flex:1; padding:26px; overflow-y:auto; }

        /* Header */
        .header{
            display:flex; justify-content:space-between; align-items:center;
            margin-bottom:26px; padding-bottom:16px; border-bottom:1px dashed var(--glassBorder);
        }
        .welcome h1{ font-size:28px; font-weight:800; color:#fff; margin-bottom:6px; }
        .welcome p{ color:var(--muted); }
        .user-info{ display:flex; align-items:center; }
        .user-profile{
            display:flex; align-items:center; gap:12px;
            background: var(--glass); border:1px solid var(--glassBorder);
            padding:8px 12px; border-radius:999px;
        }
        .user-avatar{
            width:40px; height:40px; border-radius:50%; overflow:hidden;
            display:flex; align-items:center; justify-content:center; font-weight:800; color:#fff;
            background: linear-gradient(135deg, var(--brand1), var(--brand2));
        }
        .user-details .user-name{ font-weight:700; }
        .user-details .photo-upload button{
            border:0; background:transparent; color:var(--brand2); cursor:pointer; font-size:12px; padding:0;
        }

        /* Cards / sections */
        .card{
            background: var(--glass);
            border:1px solid var(--glassBorder);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding:22px;
        }
        .card + .card{ margin-top:22px; }

        /* Stats */
        .stats-container{
            display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:22px; margin-bottom:26px;
        }
        .stat-card{ position:relative; overflow:hidden; }
        .stat-card::before{
            content:''; position:absolute; inset:0;
            background: radial-gradient(600px 220px at -10% -20%, rgba(96,165,250,.18), transparent 60%);
            pointer-events:none;
        }
        .stat-header{ display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
        .stat-icon{
            width:52px; height:52px; border-radius:12px; display:flex; align-items:center; justify-content:center;
            background: rgba(99,102,241,.18); color:#dbeafe; font-size:22px; border:1px solid var(--glassBorder);
        }
        .stat-value{ font-size:30px; font-weight:900; color:#fff; }
        .stat-title{ color:var(--muted); }

        /* Dashboard grid */
        .dashboard-content{ display:grid; grid-template-columns:2fr 1.2fr; gap:22px; }
        .card-header{
            display:flex; justify-content:space-between; align-items:center;
            margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--glassBorder);
        }
        .card-title{ font-size:18px; font-weight:800; color:#fff; }

        .quick-actions{ display:grid; grid-template-columns:repeat(2,1fr); gap:14px; }
        .action-btn{
            display:flex; flex-direction:column; align-items:center; justify-content:center;
            padding:18px 12px; text-align:center; text-decoration:none; color:#eaf1ff;
            background: rgba(255,255,255,0.05); border:1px solid var(--glassBorder);
            border-radius: var(--radius); transition:var(--trans);
        }
        .action-btn:hover{
            transform: translateY(-2px);
            background: rgba(255,255,255,0.1);
            border-color: rgba(96,165,250,.5);
            box-shadow: 0 10px 20px rgba(0,0,0,.25);
        }
        .action-btn i{ font-size:24px; margin-bottom:8px; }

        /* Participants list */
        .filter-row{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
        .filter-row select{
            padding:8px 10px; border-radius:10px; border:1px solid var(--glassBorder);
            background: rgba(255,255,255,0.06); color:#eaf1ff; outline:none;
        }
        .filter-row select option{ color:#0b1220; } /* dropdown menu items */

        .quiz-section{ margin-bottom:16px; border:1px solid var(--glassBorder); border-radius:12px; overflow:hidden; }
        .quiz-header{
            background: rgba(255,255,255,0.06); padding:12px 14px; display:flex; justify-content:space-between; align-items:center;
            border-bottom:1px solid var(--glassBorder);
        }
        .quiz-header h3{ margin:0; font-size:16px; color:#fff; }
        .quiz-header small{ color:var(--muted); }
        .quiz-body{ background: rgba(0,0,0,0.18); padding:12px 14px; }
        .participant-table{ width:100%; border-collapse:collapse; color:#eaf1ff; }
        .participant-table th,.participant-table td{ padding:10px; border-bottom:1px solid var(--glassBorder); }
        .participant-table th{ color:#c8d3ee; text-align:left; }

        /* Charts */
        .charts-grid{ display:grid; grid-template-columns:1fr; gap:16px; }
        @media (min-width:1100px){ .charts-grid{ grid-template-columns:1fr 1fr; } }
        .chart-card{ background: rgba(255,255,255,0.05); border:1px solid var(--glassBorder); border-radius:12px; padding:14px; }
        .chart-card h4{ margin:0 0 8px; color:#dbeafe; }
        canvas{ max-width:100%; }

        /* Scrollbar (subtle) */
        ::-webkit-scrollbar{ width:10px; height:10px; }
        ::-webkit-scrollbar-thumb{ background: rgba(255,255,255,0.15); border-radius:8px; }
        ::-webkit-scrollbar-track{ background: transparent; }
        /* --- Notifications --- */
        .notif-wrap { position: relative; }
        .notif-btn{
        display:flex; align-items:center; justify-content:center;
        width:42px; height:42px; border-radius:50%;
        border:1px solid rgba(255,255,255,0.15); background:rgba(255,255,255,0.06);
        color:#fff; cursor:pointer;
        }
        .notif-btn:hover{ background:rgba(255,255,255,0.12); }
        .notif-btn .bell{ font-size:18px; line-height:1; }
        .badge{
        position:absolute; top:-6px; right:-6px; min-width:18px;
        padding:0 6px; height:18px; border-radius:999px; font-size:12px;
        display:inline-flex; align-items:center; justify-content:center;
        background:#ef4444; color:#fff; border:2px solid rgba(0,0,0,0.25);
        }

        .notif-dropdown{
        position:absolute; right:0; top:50px; width:320px;
        background:rgba(15,23,42,0.92); color:#e5edff;
        border:1px solid rgba(255,255,255,0.12);
        border-radius:12px; box-shadow:0 12px 30px rgba(0,0,0,.35);
        backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
        overflow:hidden; z-index:50;
        }
        .notif-head{
        display:flex; align-items:center; justify-content:space-between;
        padding:10px 12px; border-bottom:1px solid rgba(255,255,255,0.12);
        }
        .notif-head .mark-read{
        background:transparent; border:0; color:#93c5fd; font-weight:700; cursor:pointer;
        }
        .notif-list{ max-height:360px; overflow:auto; }
        .notif-item{
        display:block; padding:10px 12px; border-bottom:1px solid rgba(255,255,255,0.08);
        text-decoration:none; color:#e5edff;
        }
        .notif-item.unread{ background:rgba(99,102,241,0.12); }
        .notif-item:hover{ background:rgba(255,255,255,0.08); }
        .notif-item small{ display:block; color:#a5b4fc; opacity:.9; margin-top:4px; }
        .notif-empty{ padding:16px; text-align:center; color:#a3aed0; }

    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo">
            <i class="fas fa-brain"></i>
            <span>QuickQuiz</span>
        </div>
        <ul>
            <li class="active">
                <a href="instructor_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a>
            </li>
            <li><a href="create_quiz.php"><i class="fas fa-plus-circle"></i><span>Create Quiz</span></a></li>
            <li><a href="my_quizzes.php"><i class="fas fa-tasks"></i><span>My Quizzes</span></a></li>
            <li><a href="analytics.php"><i class="fas fa-chart-line"></i><span>Analytics</span></a></li>
            <li><a href="#"><i class="fas fa-cog"></i><span>Settings</span></a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </aside>

    <!-- Main -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="welcome">
                <h1>Welcome, <?php echo h($teacherName); ?>!</h1>
                <p>Your quizzes, participants, and performance at a glance.</p>
            </div>
                        <!-- Notifications Bell -->
            <div class="notif-wrap">
            <button id="notif-bell" class="notif-btn" type="button" aria-label="Notifications">
                <span class="bell">🔔</span>
                <span id="notif-count" class="badge" hidden>0</span>
            </button>

            <div id="notif-dropdown" class="notif-dropdown" hidden>
                <div class="notif-head">
                <strong>Notifications</strong>
                <button id="notif-mark-read" class="mark-read" type="button">Mark all read</button>
                </div>
                <div id="notif-list" class="notif-list">
                <!-- items injected by JS -->
                </div>
            </div>
            </div>
            <div class="user-info">
                <div class="user-profile">
                    <?php if ($avatarUrl): ?>
                        <div class="user-avatar"><img src="<?php echo h($avatarUrl); ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;"></div>
                    <?php else: ?>
                        <div class="user-avatar"><?php echo h($teacherInitials); ?></div>
                    <?php endif; ?>
                    <div class="user-details">
                        <div class="user-name"><?php echo h($teacherName); ?></div>
                        <div class="photo-upload">
                            <form action="upload_photo.php" method="post" enctype="multipart/form-data">
                                <input type="file" name="photo" accept="image/*" id="teacherPhotoInput" style="display:none;" onchange="this.form.submit()">
                                <button type="button" onclick="document.getElementById('teacherPhotoInput').click()">Change Photo</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-container">
            <div class="card stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo (int)$quizzes_count; ?></div>
                        <div class="stat-title">Quizzes Created</div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                </div>
            </div>
            <div class="card stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo (int)$participants_total; ?></div>
                        <div class="stat-title">Total Participants</div>
                    </div>
                    <div class="stat-icon" style="background:rgba(96,165,250,.18)"><i class="fas fa-users"></i></div>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Left -->
            <div class="left-column">
                <div class="charts-grid">
                    <div class="chart-card">
                        <h4>Participants per Quiz</h4>
                        <canvas id="participantsChart" height="350"></canvas>
                    </div>
                    <div class="chart-card">
                        <h4>Average Score per Quiz</h4>
                        <canvas id="avgScoreChart" height="350"></canvas>
                    </div>
                </div>
            </div>


            <!-- Right -->
            <div class="right-column">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">My Quiz Participants</div>
                        <div class="filter-row">
                            <label for="quizFilter"><small style="color:#c8d3ee;">Filter by quiz:</small></label>
                            <select id="quizFilter">
                                <option value="all">All</option>
                                <?php foreach ($my_quizzes as $q): ?>
                                    <option value="<?php echo (int)$q['id']; ?>"><?php echo h($q['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="participantsWrap">
                        <?php if (empty($my_quizzes)): ?>
                            <p style="color:#9fb0d5">You haven’t created any quizzes yet.</p>
                        <?php else: ?>
                            <?php foreach ($my_quizzes as $q): 
                                $qid = (int)$q['id'];
                                $rows = $participants_by_quiz[$qid] ?? [];
                                ?>
                                <section class="quiz-section" data-quiz-id="<?php echo $qid; ?>">
                                    <div class="quiz-header">
                                        <h3><?php echo h($q['title']); ?></h3>
                                        <small><?php echo count($rows); ?> participant<?php echo count($rows)===1?'':'s'; ?></small>
                                    </div>
                                    <div class="quiz-body">
                                        <?php if (empty($rows)): ?>
                                            <p style="color:#9fb0d5">No participants yet.</p>
                                        <?php else: ?>
                                            <table class="participant-table">
                                                <thead>
                                                    <tr><th>Name</th><th>Score</th></tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($rows as $r): ?>
                                                    <tr>
                                                        <td><?php echo h($r['student_name']); ?></td>
                                                        <td><?php echo h($r['score']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php endif; ?>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div><!-- /dashboard-content -->
    </div><!-- /main-content -->

    <!-- JS: Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <script>
        // Data for charts
        const CHART_LABELS = <?php echo json_encode($chart_labels, JSON_UNESCAPED_UNICODE); ?>;
        const CHART_PARTICIPANTS = <?php echo json_encode($chart_participants, JSON_NUMERIC_CHECK); ?>;
        const CHART_AVG_SCORES = <?php echo json_encode($chart_avg_scores, JSON_NUMERIC_CHECK); ?>;

        // Shared chart options to match student "graph model" (dark theme)
        const baseGridColor = 'rgba(255,255,255,0.1)';
        const baseTickColor = '#cbd5e1';
        const barBg = 'rgba(99, 102, 241, 0.65)';     // indigo
        const barBorder = 'rgba(99, 102, 241, 1)';
        const barHoverBg = 'rgba(96, 165, 250, 0.85)'; // sky
        const barHoverBorder = 'rgba(96, 165, 250, 1)';

        document.addEventListener('DOMContentLoaded', () => {
            // Participants per Quiz chart
            const pcEl = document.getElementById('participantsChart');
            if (pcEl) {
                new Chart(pcEl, {
                    type: 'bar',
                    data: {
                        labels: CHART_LABELS,
                        datasets: [{
                            label: 'Participants',
                            data: CHART_PARTICIPANTS,
                            backgroundColor: barBg,
                            borderColor: barBorder,
                            borderWidth: 1,
                            hoverBackgroundColor: barHoverBg,
                            hoverBorderColor: barHoverBorder
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: baseGridColor },
                                ticks: { color: baseTickColor, precision: 0 }
                            },
                            x: {
                                grid: { color: 'rgba(255,255,255,0.06)' },
                                ticks: { color: baseTickColor }
                            }
                        }
                    }
                });
            }

            // Average Score per Quiz chart
            const acEl = document.getElementById('avgScoreChart');
            if (acEl) {
                new Chart(acEl, {
                    type: 'bar',
                    data: {
                        labels: CHART_LABELS,
                        datasets: [{
                            label: 'Average Score (%)',
                            data: CHART_AVG_SCORES,
                            backgroundColor: 'rgba(96, 165, 250, 0.65)', // sky
                            borderColor: 'rgba(96, 165, 250, 1)',
                            borderWidth: 1,
                            hoverBackgroundColor: 'rgba(99, 102, 241, 0.85)', // indigo
                            hoverBorderColor: 'rgba(99, 102, 241, 1)'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                grid: { color: baseGridColor },
                                ticks: { color: baseTickColor }
                            },
                            x: {
                                grid: { color: 'rgba(255,255,255,0.06)' },
                                ticks: { color: baseTickColor }
                            }
                        }
                    }
                });
            }

            // Filter: show one quiz, or all
            const filter = document.getElementById('quizFilter');
            const sections = Array.from(document.querySelectorAll('.quiz-section'));
            if (filter) {
                filter.addEventListener('change', () => {
                    const val = filter.value;
                    sections.forEach(sec => {
                        const match = (val === 'all') || (sec.getAttribute('data-quiz-id') === val);
                        sec.style.display = match ? '' : 'none';
                    });
                });
            }
        });
    </script>
    <script>
    (function(){
    const bellBtn   = document.getElementById('notif-bell');
    const badge     = document.getElementById('notif-count');
    const dropdown  = document.getElementById('notif-dropdown');
    const listEl    = document.getElementById('notif-list');
    const markBtn   = document.getElementById('notif-mark-read');

    let open = false;
    let timer = null;

    async function fetchNotifications(){
        try{
        const res = await fetch('get_notifications.php', {cache:'no-store'});
        if(!res.ok) return;
        const data = await res.json();
        // badge
        if (data.unread && data.unread > 0) {
            badge.textContent = data.unread;
            badge.hidden = false;
        } else {
            badge.hidden = true;
        }
        // list
        if (!data.items || data.items.length === 0) {
            listEl.innerHTML = `<div class="notif-empty">No notifications</div>`;
            return;
        }
        listEl.innerHTML = data.items.map(n => {
            const time = new Date(n.created_at.replace(' ', 'T'));
            const tstr = time.toLocaleString();
            const href = n.link && n.link.length ? n.link : 'javascript:void(0)';
            const cls  = n.is_read == 0 ? 'notif-item unread' : 'notif-item';
            const safeMsg = (n.message || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            return `<a class="${cls}" href="${href}">
                    <div>${safeMsg}</div>
                    <small>${tstr}</small>
                    </a>`;
        }).join('');
        }catch(e){
        // silent
        }
    }

    async function markAllRead(){
        try{
        await fetch('mark_notifications_read.php', {method:'POST'});
        // refresh UI quickly
        fetchNotifications();
        }catch(e){}
    }

    function toggle(){
        open = !open;
        dropdown.hidden = !open;
    }

    // Close if clicking outside
    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target) && !bellBtn.contains(e.target)) {
        open = false; dropdown.hidden = true;
        }
    });

    bellBtn.addEventListener('click', toggle);
    markBtn.addEventListener('click', markAllRead);

    // Initial + poll
    fetchNotifications();
    timer = setInterval(fetchNotifications, 15000); // every 15s
    })();
    </script>

</body>
</html>
