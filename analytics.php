<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Instructor') {
    header("Location: login.php");
    exit;
}
include 'db_config.php';

$user_id = (int)$_SESSION['user_id'];
$result = $conn->query("
    SELECT q.title, AVG(l.score) AS avg_score
    FROM quizzes q
    LEFT JOIN leaderboards l ON q.quiz_id = l.quiz_id
    WHERE q.created_by = $user_id
    GROUP BY q.quiz_id
    ORDER BY q.quiz_id DESC
");

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Analytics | QuickQuiz</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root{
            --primary:#3b82f6;     /* blue */
            --primary-2:#06b6d4;   /* cyan */
            --muted:#cbd5e1;
            --text:#ffffff;
            --glass:rgba(255,255,255,0.10);
            --border:rgba(255,255,255,0.18);
        }
        *{box-sizing:border-box}
        html,body{
            margin:0; padding:0;
            font-family:'Inter', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            color:var(--text);
            background: linear-gradient(-45deg, #0f172a, #1e3a8a, #2563eb, #0c4a6e);
            background-size: 400% 400%;
            animation: gradientShift 18s ease infinite;
            min-height:100vh;
        }
        @keyframes gradientShift{
            0%{background-position:0% 50%}
            50%{background-position:100% 50%}
            100%{background-position:0% 50%}
        }

        .outer{
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .glass-card{
            background: var(--glass);
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
            backdrop-filter: blur(20px);
            overflow: hidden;
        }
        .header-bar{
            background: linear-gradient(45deg, var(--primary), var(--primary-2));
            padding: 22px 24px;
            display:flex;
            align-items:center;
            justify-content:space-between;
        }
        .title-wrap .title{
            font-size: 24px;
            font-weight: 800;
            letter-spacing: .2px;
        }
        .title-wrap .subtitle{
            color: #f1f5f9;
            font-size: 13px;
            margin-top: 6px;
            opacity:.95;
        }
        .toolbar{
            display:flex; gap:10px; align-items:center;
        }
        .btn{
            border: 2px solid rgba(255,255,255,.65);
            background: transparent;
            color:#fff;
            padding: 8px 12px;
            border-radius: 10px;
            cursor:pointer;
            font-weight: 700;
            font-size: 13px;
            text-decoration:none;
        }
        .btn:hover{ background: rgba(255,255,255,.12); }
        .btn.primary{
            border-color: transparent;
            background:#ffffff;
            color:#1e293b;
        }
        .btn.primary:hover{ filter: brightness(0.92); }

        .card-body{ padding: 22px; }

        .table-wrap{
            overflow:auto;
            border:1px solid var(--border);
            border-radius: 12px;
            background: rgba(255,255,255,0.04);
        }
        table{ width:100%; border-collapse: separate; border-spacing:0; min-width:700px; }
        thead th{
            background: rgba(255,255,255,0.12);
            color:#e5e7eb;
            font-weight:700;
            text-align:left;
            font-size:13px;
            padding:14px;
            border-bottom:1px solid var(--border);
        }
        tbody td{
            padding:14px;
            border-bottom:1px solid var(--border);
            vertical-align:middle;
            font-size:14px;
            color:#f8fafc;
        }
        tbody tr:last-child td{ border-bottom:none; }
        tbody tr:nth-child(even){ background: rgba(255,255,255,0.03); }
        tbody tr:hover{ background: rgba(255,255,255,0.08); }

        .score-cell{ display:flex; align-items:center; gap:12px; }
        .bar{
            flex:1; height:10px;
            background: rgba(255,255,255,0.18);
            border-radius:999px;
            position:relative; overflow:hidden;
        }
        .bar > span{
            position:absolute; left:0; top:0; bottom:0; width:0;
            border-radius:999px;
            background: linear-gradient(90deg, var(--primary), var(--primary-2));
        }
        .badge{
            display:inline-block;
            padding:4px 10px;
            border-radius: 999px;
            font-size:12px;
            font-weight:800;
        }
        .badge.green{  background: rgba(34,197,94,0.25);  color:#eafff3; }
        .badge.yellow{ background: rgba(245,158,11,0.25); color:#fff7e6; }
        .badge.red{    background: rgba(239,68,68,0.25);  color:#ffecec; }

        .empty{ text-align:center; color: var(--muted); padding: 28px; }

        @media (max-width:640px){
            .title-wrap .title{ font-size:20px; }
            .header-bar{ flex-direction:column; align-items:flex-start; gap:12px; }
        }
    </style>
</head>
<body>
    <div class="outer">
        <div class="glass-card">
            <div class="header-bar">
                <div class="title-wrap">
                    <div class="title">Analytics Dashboard</div>
                    <div class="subtitle">Average score per quiz (based on your participants).</div>
                </div>
                <div class="toolbar">
                    <button class="btn" onclick="history.back()">← Back</button>
                    <a class="btn primary" href="create_quiz.php">+ New Quiz</a>
                </div>
            </div>

            <div class="card-body">
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th style="width:48px;">#</th>
                            <th>Quiz</th>
                            <th style="width:65%;">Performance</th>
                            <th style="width:120px;">Avg Score</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        if ($result && $result->num_rows) {
                            $i = 1;
                            while ($row = $result->fetch_assoc()) {
                                $title = $row['title'] ?? 'Untitled';
                                $avg = $row['avg_score'] === null ? 0 : (float)$row['avg_score'];
                                $pct = max(0, min(100, round($avg, 2)));
                                if ($pct >= 80)      $badgeClass = 'green';
                                elseif ($pct >= 50) $badgeClass = 'yellow';
                                else                $badgeClass = 'red';
                                ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><?php echo h($title); ?></td>
                                    <td>
                                        <div class="score-cell">
                                            <div class="bar"><span style="width: <?php echo $pct; ?>%"></span></div>
                                        </div>
                                    </td>
                                    <td><span class="badge <?php echo $badgeClass; ?>"><?php echo number_format($pct, 2); ?>%</span></td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="4" class="empty">No quizzes yet — create one to see analytics.</td></tr>';
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div><!-- /.card-body -->
        </div><!-- /.glass-card -->
    </div><!-- /.outer -->
</body>
</html>
