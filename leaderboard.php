<?php
include 'db_config.php';

$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

/* ---------------------------
   NO QUIZ ID → SHOW QUIZ LIST
---------------------------- */
if (!$quiz_id) {
    $quizzes = $conn->query("
        SELECT q.quiz_id, q.title, COUNT(l.user_id) AS participants
        FROM quizzes q
        LEFT JOIN leaderboards l ON q.quiz_id = l.quiz_id
        GROUP BY q.quiz_id, q.title
        ORDER BY q.quiz_id DESC
    ");
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8" />
        <title>Select Quiz - Leaderboard</title>
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <style>
            :root{
                --bg1:#0e1630;
                --bg2:#1c2841;
                --brand1:#7c8cff; /* indigo */
                --brand2:#ec4899; /* pink */
                --card: rgba(20,28,56,0.78);
                --cardBorder: rgba(255,255,255,0.12);
                --text:#e9eefc;
                --muted:#b9c3e6;
                --row:#18233f;
                --rowAlt:#1b2746;
                --rowHover: rgba(124,140,255,0.16);
                --thead: rgba(255,255,255,0.04);
                --shadow: 0 12px 30px rgba(0,0,0,0.35);
                --radius:16px;
                --radiusSm:10px;
                --focus: 0 0 0 3px rgba(124,140,255,0.45);
            }
            *{box-sizing:border-box}
            body{
                margin:0;
                font-family:"Segoe UI", Tahoma, sans-serif;
                background: radial-gradient(1200px 700px at 20% 10%, #2a3470 0%, transparent 60%),
                            radial-gradient(900px 700px at 80% 20%, #3d155f 0%, transparent 60%),
                            linear-gradient(120deg, var(--bg1), var(--bg2));
                color:var(--text);
                padding:40px 20px;
            }
            .wrap{max-width:920px;margin:0 auto}
            .card{
                background:var(--card);
                border:1px solid var(--cardBorder);
                border-radius:var(--radius);
                box-shadow:var(--shadow);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                overflow:hidden;
            }

            /* ======= HEADER (centered title, non-overlapping back) ======= */
            .cardHeader{
                display:grid;
                grid-template-columns:auto 1fr auto; /* back | title | spacer */
                align-items:center;
                gap:12px;
                padding:18px 24px;
                background: linear-gradient(90deg, var(--brand1), var(--brand2));
                color:#fff;
                letter-spacing:.3px;
            }
            .headerTitle{
                font-size:22px;
                font-weight:800;
                text-align:center; /* truly centered */
                white-space:nowrap;
                overflow:hidden;
                text-overflow:ellipsis;
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
            .back:focus-visible{outline:none;box-shadow: var(--focus)}
            /* ghost keeps the title centered but is invisible & not interactive */
            .back--ghost{visibility:hidden; pointer-events:none}

            .cardBody{padding:18px}

            /* unified gradient button (used on list page toolbar if needed) */
            .btn{
                display:inline-flex;align-items:center;gap:10px;
                padding:10px 14px;border-radius:12px;
                background:linear-gradient(90deg, var(--brand1), var(--brand2));
                color:#fff;text-decoration:none;font-weight:700;
                border:1px solid rgba(255,255,255,0.15);
                box-shadow: 0 6px 16px rgba(0,0,0,0.25);
                transition: transform .12s ease, box-shadow .12s ease, opacity .2s ease;
            }
            .btn:hover{transform: translateY(-1px)}

            .quizList{list-style:none;margin:0;padding:20px}
            .quizList li{margin-bottom:12px}
            .quizLink{
                display:flex;justify-content:space-between;align-items:center;
                padding:14px 16px;border-radius:var(--radiusSm);
                background:linear-gradient(180deg, var(--row), var(--rowAlt));
                color:var(--text);text-decoration:none;
                border:1px solid var(--cardBorder);
                transition: background .2s ease, border-color .2s ease;
            }
            .quizLink span{color:var(--muted);font-size:13px}
            .quizLink:hover{background: linear-gradient(180deg, var(--rowAlt), var(--row)); border-color: rgba(124,140,255,0.35)}
        </style>
    </head>
    <body>
    <div class="wrap">
        <div class="card">
            <div class="cardHeader">
                <a class="back" href="student_dashboard.php">⬅ Back</a>
                <div class="headerTitle">🏆 Select a Quiz to View Leaderboard</div>
                <span class="back back--ghost" aria-hidden="true">⬅ Back</span>
            </div>

            <ul class="quizList">
                <?php while ($q = $quizzes->fetch_assoc()) { ?>
                    <li>
                        <a class="quizLink" href="leaderboard.php?quiz_id=<?php echo $q['quiz_id']; ?>">
                            <strong><?php echo htmlspecialchars($q['title']); ?></strong>
                            <span><?php echo (int)$q['participants']; ?> participants</span>
                        </a>
                    </li>
                <?php } ?>
            </ul>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

/* ---------------------------
   QUIZ ID → SHOW LEADERBOARD
---------------------------- */

/* quiz title */
$stmtTitle = $conn->prepare("SELECT title FROM quizzes WHERE quiz_id = ?");
$stmtTitle->bind_param("i", $quiz_id);
$stmtTitle->execute();
$stmtTitle->bind_result($quizTitle);
$stmtTitle->fetch();
$stmtTitle->close();

/* AJAX rows */
if (isset($_GET['ajax'])) {
    $stmt = $conn->prepare("
        SELECT u.name, l.score, l.completion_time
        FROM leaderboards l
        JOIN users u ON l.user_id = u.user_id
        WHERE l.quiz_id = ?
        ORDER BY l.score DESC, l.completion_time ASC
    ");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $rank = 1;
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        if ($rank == 1) $badge = "<span class='rank rank-1'>#1</span>";
        elseif ($rank == 2) $badge = "<span class='rank rank-2'>#2</span>";
        elseif ($rank == 3) $badge = "<span class='rank rank-3'>#3</span>";
        else $badge = "<span class='rank rank-default'>#$rank</span>";

        echo "<td>$badge</td>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        $formattedScore = number_format((float)$row['score'], 2) . "%";
        echo "<td>$formattedScore</td>";
        echo "<td>" . htmlspecialchars($row['completion_time']) . "s</td>";
        echo "</tr>";
        $rank++;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Leaderboard - <?php echo htmlspecialchars($quizTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        :root{
            --bg1:#0e1630;
            --bg2:#1c2841;
            --brand1:#7c8cff;
            --brand2:#ec4899;
            --card: rgba(20,28,56,0.78);
            --cardBorder: rgba(255,255,255,0.12);
            --text:#e9eefc;
            --muted:#b9c3e6;
            --row:#18233f;
            --rowAlt:#1b2746;
            --thead: rgba(255,255,255,0.04);
            --shadow: 0 12px 30px rgba(0,0,0,0.35);
            --radius:16px;
            --focus: 0 0 0 3px rgba(124,140,255,0.45);
        }
        body{
            margin:0;
            font-family:"Segoe UI", Tahoma, sans-serif;
            background: radial-gradient(1200px 700px at 20% 10%, #2a3470 0%, transparent 60%),
                        radial-gradient(900px 700px at 80% 20%, #3d155f 0%, transparent 60%),
                        linear-gradient(120deg, var(--bg1), var(--bg2));
            color:var(--text);
            padding:40px 20px;
        }
        .wrap{max-width:920px;margin:0 auto}
        .card{
            background:var(--card);
            border:1px solid var(--cardBorder);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            overflow:hidden;
        }

        /* ======= HEADER (centered title, non-overlapping back) ======= */
        .cardHeader{
            display:grid;
            grid-template-columns:auto 1fr auto;
            align-items:center;
            gap:12px;
            padding:18px 24px;
            background: linear-gradient(90deg, var(--brand1), var(--brand2));
            color:#fff;
        }
        .headerTitle{
            font-size:22px;
            font-weight:800;
            text-align:center;
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
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
        .back:focus-visible{outline:none;box-shadow: var(--focus)}
        .back--ghost{visibility:hidden; pointer-events:none}

        .cardBody{padding:18px}

        table{width:100%;border-collapse:separate;border-spacing:0}
        thead th{
            background: var(--thead);
            color:var(--muted);
            font-weight:700;font-size:14px;
            padding:14px 10px;text-align:center;
            border-bottom:1px solid var(--cardBorder);
        }
        tbody td{
            padding:14px 10px;text-align:center;
            color:var(--text);font-size:15px;
            border-bottom:1px solid rgba(255,255,255,0.06);
        }
        tbody tr{background: linear-gradient(180deg, var(--row), var(--rowAlt))}
        tbody tr:nth-child(even){background: linear-gradient(180deg, var(--rowAlt), var(--row))}
        tbody tr:hover{
            background: linear-gradient(180deg, var(--rowAlt), var(--row));
            outline:1px solid rgba(124,140,255,0.35);
            outline-offset:-1px;
        }

        .rank{
            display:inline-block;min-width:40px;
            padding:6px 12px;border-radius:999px;font-weight:800;
            border:1px solid rgba(0,0,0,0.15);
        }
        .rank-1{background: linear-gradient(90deg,#ffd54d,#ffb300); color:#2b2200}
        .rank-2{background: linear-gradient(90deg,#e5e7eb,#c0c4cc); color:#1f2937}
        .rank-3{background: linear-gradient(90deg,#d08b46,#b1662a); color:#fff}
        .rank-default{background: rgba(255,255,255,0.14); color:#e9eefc}

        @media (max-width:650px){
            body{padding:18px}
            .headerTitle{font-size:18px}
            .back{padding:7px 10px}
            thead th, tbody td{padding:10px 8px;font-size:14px}
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="cardHeader">
            <a class="back" href="student_dashboard.php">⬅ Back</a>
            <div class="headerTitle">🏆 Leaderboard – <?php echo htmlspecialchars($quizTitle); ?></div>
            <span class="back back--ghost" aria-hidden="true">⬅ Back</span>
        </div>
        <div class="cardBody">
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Name</th>
                        <th>Score</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody id="leaderboard-body"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
async function fetchLeaderboard(){
    const res = await fetch("leaderboard.php?quiz_id=<?php echo $quiz_id; ?>&ajax=1", {cache:'no-store'});
    const html = await res.text();
    document.getElementById("leaderboard-body").innerHTML = html;
}
fetchLeaderboard();
setInterval(fetchLeaderboard, 10000);
</script>
</body>
</html>
