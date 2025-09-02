<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Student') {
    header("Location: login.php");
    exit;
}
include 'db_config.php';

$user_id = (int)$_SESSION['user_id'];

/* Get student name + photo (photo can be null) */
$uRes = $conn->query("SELECT name, photo FROM users WHERE user_id = $user_id");
$user = $uRes ? $uRes->fetch_assoc() : null;
$student_name = $user && $user['name'] ? $user['name'] : "Student";

/* Prefer DB photo; fall back to session if you like */
$photoPath = '';
if ($user && !empty($user['photo'])) {
    $photoPath = $user['photo'];
} elseif (!empty($_SESSION['photo'])) {
    $photoPath = $_SESSION['photo'];
}

/* Performance avg score */
$avgRow = $conn->query("SELECT AVG(score) AS avg FROM leaderboards WHERE user_id = $user_id")->fetch_assoc();
$avg_score = $avgRow && isset($avgRow['avg']) ? (float)$avgRow['avg'] : 0;

/* Recently completed quizzes */
$recent = $conn->query("
    SELECT q.title, l.score 
    FROM leaderboards l 
    JOIN quizzes q ON l.quiz_id = q.quiz_id 
    WHERE l.user_id = $user_id 
    ORDER BY l.entry_id DESC 
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard | Quiz Hero</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #8b5cf6;
            --secondary: #ec4899;
            --accent: #22d3ee;
            --bg-start: #0e1630;
            --bg-end: #380036;
            --card-bg: rgba(255,255,255,0.05);
            --card-border: rgba(255,255,255,0.1);
            --card-radius: 20px;
            --shadow: 0 8px 24px rgba(0,0,0,0.25);
            --sidebar-bg: rgba(10,10,20,0.6);
        }
        @keyframes gradientShift {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body {
            display:flex;
            min-height:100vh;
            color:#fff;
            background: linear-gradient(-45deg, var(--bg-start), var(--bg-end), #3f51b5, #1c2841);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
            overflow-x:hidden;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            backdrop-filter: blur(20px);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--card-border);
            padding: 24px 18px;
        }
        .sidebar .logo {
            font-size: 22px;
            font-weight: 800;
            color: var(--primary);
            display:flex;
            align-items:center;
            gap:10px;
            margin-bottom: 16px;
            letter-spacing:.5px;
        }

        /* Profile block (top-left) */
        .profile {
            display:flex;
            align-items:center;
            gap:12px;
            padding:12px;
            border-radius:14px;
            background: rgba(255,255,255,0.06);
            border:1px solid var(--card-border);
            margin-bottom: 18px;
        }
        .avatar {
            width:48px; height:48px; border-radius:50%;
            overflow:hidden; flex:0 0 auto;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            display:flex; align-items:center; justify-content:center;
            font-weight:800; color:#fff;
        }
        .avatar img{ width:100%; height:100%; object-fit:cover; display:block; }
        .p-meta { display:flex; flex-direction:column; }
        .p-name { font-weight:700; font-size:15px; line-height:1.2; }
        .p-actions { margin-top:6px; }
        .p-actions button{
            border:0; padding:6px 10px; border-radius:8px; cursor:pointer;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color:#fff; font-weight:700; font-size:12px;
            box-shadow: 0 4px 10px rgba(0,0,0,.2);
        }
        .p-actions button:hover{ filter: brightness(0.95); }

        .sidebar ul { list-style:none; margin: 10px 0 0; padding: 0; }
        .sidebar ul li { margin: 10px 0; }
        .sidebar ul li a {
            display:flex;
            align-items:center;
            gap: 12px;
            padding: 12px 12px;
            border-radius: 12px;
            color:#eee;
            text-decoration:none;
            position:relative;
            transition:all 0.3s ease;
        }
        .sidebar ul li a::before {
            content:'';
            position:absolute;
            inset:0;
            border-radius:12px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            opacity:0;
            transition:opacity 0.3s ease;
            z-index:-1;
        }
        .sidebar ul li a:hover::before,
        .sidebar ul li.active a::before { opacity:0.2; }
        .sidebar ul li a i { font-size:20px; }
        .sidebar ul li a span { font-size: 16px; }

        /* Main */
        .main { flex:1; padding: 40px; overflow-y:auto; }
        .header { margin-bottom: 40px; display:flex; justify-content:space-between; align-items:center; }
        .header h1 { font-size:32px; font-weight: 600; }

        /* Stats */
        .stats-container {
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(220px,1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: var(--card-bg);
            border:1px solid var(--card-border);
            border-radius: var(--card-radius);
            padding: 24px;
            text-align:center;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position:relative;
            overflow:hidden;
        }
        .stat-card::after {
            content:'';
            position:absolute;
            top:-50%; left:-50%;
            width:200%; height:200%;
            background: radial-gradient(circle at center, rgba(255,255,255,0.25), transparent 60%);
            transform: rotate(45deg);
            transition: opacity 0.6s ease;
            opacity:0;
            pointer-events:none;
        }
        .stat-card:hover::after { opacity:0.1; }
        .stat-card:hover {
            transform:translateY(-4px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.35);
        }
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 8px;
            color: var(--secondary);
        }
        .stat-title {
            font-size: 14px;
            color: #b3b3b3;
        }

        /* Dashboard content */
        .dashboard-grid {
            display:grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        .card {
            background: var(--card-bg);
            border:1px solid var(--card-border);
            border-radius: var(--card-radius);
            padding: 24px;
            box-shadow: var(--shadow);
            margin-bottom: 24px;
            position:relative;
            overflow:hidden;
        }
        .card h2 {
            font-size: 20px;
            color: var(--primary);
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing:1px;
        }
        .available-quizzes form { display:flex; gap:12px; }
        .available-quizzes input {
            flex:1;
            padding: 10px 14px;
            border-radius:12px;
            border:1px solid var(--card-border);
            background: rgba(255,255,255,0.1);
            color:#fff;
            font-size: 14px;
            outline:none;
            transition:background 0.3s ease, border-color 0.3s ease;
        }
        .available-quizzes input::placeholder { color: #aaa; }
        .available-quizzes input:focus {
            background: rgba(255,255,255,0.2);
            border-color: var(--primary);
        }
        .available-quizzes button {
            padding: 10px 18px;
            border:none;
            border-radius:12px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: #fff;
            font-weight: bold;
            cursor:pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .available-quizzes button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.35);
        }

        /* Recently Completed */
        .recently-completed ul { 
            list-style:none; 
            padding:0; 
            margin:0;
        }
        .recently-completed li {
            display:flex;
            justify-content:space-between;
            align-items:center;
            background: rgba(255,255,255,0.05);
            border:1px solid var(--card-border);
            border-radius:10px;
            padding:10px 14px;
            margin-bottom:10px;
            font-size:15px;
            color:#eee;
            transition: background 0.3s ease;
        }
        .recently-completed li:hover { background: rgba(255,255,255,0.12); }
        .recently-completed .quiz-title {
            display:flex; align-items:center; gap:8px;
        }
        .recently-completed .quiz-title i { color: var(--accent); }
        .recently-completed .quiz-score { font-weight:600; color: var(--accent); }

        @media (max-width: 900px){
            .dashboard-grid { grid-template-columns: 1fr; }
        }
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
        <div class="logo"><i class="fas fa-graduation-cap"></i> Quiz Hero</div>

        <!-- Profile block: dynamic name + change photo -->
        <div class="profile">
            <div class="avatar">
                <?php if (!empty($photoPath)): ?>
                    <img src="<?php echo htmlspecialchars($photoPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile">
                <?php else: ?>
                    <span><?php echo strtoupper(substr($student_name,0,1)); ?></span>
                <?php endif; ?>
            </div>
            <div class="p-meta">
                <div class="p-name"><?php echo htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="p-actions">
                    <form action="upload_photo.php" method="post" enctype="multipart/form-data">
                        <input type="file" name="photo" accept="image/*" id="photoInput" style="display:none" onchange="this.form.submit()">
                        <button type="button" onclick="document.getElementById('photoInput').click()">
                            Change Photo
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <ul>
            <li class="active"><a href="student_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="leaderboard.php"><i class="fas fa-trophy"></i><span>Leaderboard</span></a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </aside>

    <!-- Main -->
    <div class="main">
        <div class="header">
            <h1>👋 Welcome, <?php echo htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8'); ?></h1>
                            <!-- Notification bell top-right -->
        <div class="notif-wrap">
            <button id="notif-bell" class="notif-btn" type="button">
            <span class="bell">🔔</span>
            <span id="notif-count" class="badge" hidden>0</span>
            </button>
            <div id="notif-dropdown" class="notif-dropdown" hidden>
            <div class="notif-head">
                <strong>Notifications</strong>
                <button id="notif-mark-read" class="mark-read" type="button">Mark all read</button>
            </div>
            <div id="notif-list" class="notif-list"></div>
            </div>
        </div>
        </div>

        <!-- Stats -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-value"><?php echo round($avg_score, 1); ?>%</div>
                <div class="stat-title">Average Score</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $recent->num_rows; ?></div>
                <div class="stat-title">Recent Quizzes</div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-grid">
            <div>
                <!-- Available Quizzes -->
                <div class="card available-quizzes">
                    <h2><i class="fas fa-key"></i> Join a Quiz</h2>
                    <form action="take_quiz.php" method="get">
                        <input type="text" name="access_code" placeholder="Enter Access Code" required>
                        <button type="submit">Join</button>
                    </form>
                </div>

                <!-- Performance -->
                <div class="card">
                    <h2><i class="fas fa-chart-line"></i> Performance Overview</h2>
                    <canvas id="scoreChart" height="120"></canvas>
                </div>
            </div>


            <div>
                <!-- Recently Completed -->
                <div class="card recently-completed">
                    <h2><i class="fas fa-check-circle"></i> Recently Completed</h2>
                    <ul>
                        <?php 
                        if ($recent && $recent->num_rows > 0) {
                            while ($row = $recent->fetch_assoc()) {
                                echo "<li>
                                        <div class='quiz-title'><i class='fas fa-book'></i> ".htmlspecialchars($row['title'])."</div>
                                        <div class='quiz-score'>".htmlspecialchars($row['score'])."%</div>
                                    </li>";
                            }
                        } else {
                            echo "<li>No quizzes completed yet.</li>";
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
    let scoreChart;
    async function fetchScores() {
        const response = await fetch("get_student_scores.php");
        if (!response.ok) return;
        const data = await response.json();
        const labels = data.map(d => d.quiz_title);
        const scores = data.map(d => d.score);

        const ctx = document.getElementById("scoreChart").getContext("2d");
        if (!scoreChart) {
            scoreChart = new Chart(ctx, {
                type: "bar",
                data: {
                    labels: labels,
                    datasets: [{
                        label: "Quiz Scores",
                        data: scores,
                        backgroundColor: "rgba(139, 92, 246, 0.6)",
                        borderColor: "rgba(139, 92, 246, 1)",
                        borderWidth: 1,
                        hoverBackgroundColor: "rgba(236, 72, 153, 0.8)",
                        hoverBorderColor: "rgba(236, 72, 153, 1)"
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: { color: 'rgba(255,255,255,0.1)' },
                            ticks: { color:'#ccc' }
                        },
                        x: {
                            ticks: { color:'#ccc' },
                            grid: { color:'rgba(255,255,255,0.05)' }
                        }
                    },
                    plugins: {
                        legend: { labels: { color:'#ccc' } }
                    }
                }
            });
        } else {
            scoreChart.data.labels = labels;
            scoreChart.data.datasets[0].data = scores;
            scoreChart.update();
        }
    }
    fetchScores();
    setInterval(fetchScores, 30000);
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
