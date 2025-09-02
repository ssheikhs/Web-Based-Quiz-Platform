<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Instructor') {
    header("Location: login.php");
    exit;
}
include 'db_config.php';

$user_id = (int)$_SESSION['user_id'];
$result = $conn->query("SELECT * FROM quizzes WHERE created_by = $user_id ORDER BY quiz_id DESC");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>My Quizzes | QuickQuiz</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --bg1:#0e1630;
            --bg2:#1c2841;
            --bg3:#2a3470;
            --brand1:#7c8cff;
            --brand2:#60a5fa;
            --accent:#22d3ee;
            --card:rgba(20,28,56,0.78);
            --cardBorder:rgba(255,255,255,0.12);
            --softBorder:rgba(255,255,255,0.08);
            --text:#e9eefc;
            --muted:#b9c3e6;
            --shadow:0 12px 30px rgba(0,0,0,0.35);
            --radius:16px;
            --thead:rgba(255,255,255,0.06);
            --row:#18233f;
            --rowAlt:#1b2746;
            --rowHover:rgba(124,140,255,0.18);
            --focus:0 0 0 3px rgba(124,140,255,0.45);
        }
        @keyframes gradientShift {
            0%{background-position:0% 50%}
            50%{background-position:100% 50%}
            100%{background-position:0% 50%}
        }
        html,body{
            margin:0;
            height:100%;
            background:
              radial-gradient(1200px 700px at 20% 10%, var(--bg3) 0%, transparent 60%),
              radial-gradient(900px 700px at 80% 20%, #3d155f 0%, transparent 60%),
              linear-gradient(120deg, var(--bg1), var(--bg2));
            background-size:400% 400%;
            animation:gradientShift 20s ease infinite;
            color:var(--text);
            font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
        }

        .container{max-width:1100px;margin:40px auto;padding:0 20px;position:relative;}

        .header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
        .title{font-size:28px;font-weight:800;letter-spacing:.2px}
        .subtitle{color:var(--muted);font-size:14px;margin-top:6px}

        .toolbar {display:flex;gap:12px;}
        .btn {
            border:1px solid var(--softBorder);
            background:linear-gradient(90deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02));
            color:var(--text);
            padding:10px 18px;
            border-radius:12px;
            font-weight:700;
            text-decoration:none;
            cursor:pointer;
            box-shadow:0 6px 16px rgba(0,0,0,0.25);
            transition:transform .12s ease, box-shadow .12s ease, background .2s ease, border-color .2s ease;
        }
        .btn:hover{transform:translateY(-1px); border-color:rgba(124,140,255,0.35); background:rgba(255,255,255,0.10)}
        .btn.primary{
            background:linear-gradient(90deg, var(--brand1), var(--brand2));
            border-color:transparent; color:#fff;
        }
        .btn.primary:hover{transform:translateY(-1px); box-shadow:0 10px 22px rgba(0,0,0,0.35)}

        .card{
            background:var(--card);
            border:1px solid var(--cardBorder);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding:22px;
        }

        .table-wrap{overflow:auto;border:1px solid var(--softBorder);border-radius:12px}
        table{width:100%;border-collapse:separate;border-spacing:0;min-width:760px}
        thead th{
            background:var(--thead);
            color:var(--muted);
            font-weight:700;text-align:left;font-size:13px;
            padding:14px;border-bottom:1px solid var(--softBorder)
        }
        tbody td{
            padding:14px;border-bottom:1px solid var(--softBorder);
            vertical-align:middle;font-size:14px;color:var(--text)
        }
        tbody tr{
            background:linear-gradient(180deg, var(--row), var(--rowAlt));
        }
        tbody tr:nth-child(even){
            background:linear-gradient(180deg, var(--rowAlt), var(--row));
        }
        tbody tr:hover{
            background:linear-gradient(180deg, var(--rowAlt), var(--row));
            outline:1px solid rgba(124,140,255,0.35);
            outline-offset:-1px;
        }
        tbody tr:last-child td{border-bottom:none}

        .badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:800}
        .badge.green{background:rgba(34,211,238,0.15); color:#7ee0f0; border:1px solid rgba(34,211,238,0.35)}
        .badge.gray{background:rgba(255,255,255,0.08); color:#d1d5db; border:1px solid var(--softBorder)}

        .actions{display:flex;gap:10px;flex-wrap:wrap}
        .link-btn{
            padding:8px 12px;border-radius:10px;text-decoration:none;font-weight:700;
            border:1px solid var(--softBorder); color:var(--text);
            background:rgba(255,255,255,0.04);
            transition:transform .12s ease, border-color .2s ease, background .2s ease;
        }
        .link-btn:hover{transform:translateY(-1px); border-color:rgba(124,140,255,0.35); background:rgba(255,255,255,0.10)}
        .link-btn.primary{background:linear-gradient(90deg, var(--brand1), var(--brand2)); color:#fff; border-color:transparent}

        .copy-wrap{display:flex;align-items:center;gap:8px}
        .code{
            font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            background:rgba(255,255,255,0.08);
            border:1px solid var(--softBorder);
            color:#e5ecff;
            border-radius:8px;padding:6px 10px
        }

        .empty{padding:26px;text-align:center;color:var(--muted)}

        .search{display:flex;gap:10px;margin:12px 0 18px}
        .search input{
            flex:1;padding:10px 12px;border:1px solid var(--softBorder);border-radius:12px;
            background:rgba(255,255,255,0.06); color:var(--text)
        }
        .search input::placeholder{color:#aab4de}
        .search input:focus{outline:none; box-shadow:var(--focus); border-color:transparent}
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <div class="title">My Quizzes</div>
            <div class="subtitle">Manage the quizzes you’ve created.</div>
        </div>
        <div class="toolbar">
            <!-- Back button placed on the right -->
            <a href="javascript:history.back()" class="btn">← Back</a>
            <a href="create_quiz.php" class="btn primary">+ New Quiz</a>
            <a href="instructor_dashboard.php" class="btn">Dashboard</a>
        </div>
    </div>

    <div class="card">
        <div class="search">
            <input id="searchBox" type="text" placeholder="Search by title…">
        </div>
        <div class="table-wrap">
            <table id="quizTable">
                <thead>
                    <tr>
                        <th style="width:46%;">Title</th>
                        <th style="width:120px;">Published</th>
                        <th style="width:260px;">Access Code</th>
                        <th style="width:220px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result && $result->num_rows): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo h($row['title']); ?></td>
                            <td>
                                <?php if (!empty($row['is_published'])): ?>
                                    <span class="badge green">Yes</span>
                                <?php else: ?>
                                    <span class="badge gray">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="copy-wrap">
                                    <span class="code" id="code-<?php echo (int)$row['quiz_id']; ?>">
                                        <?php echo h($row['access_code']); ?>
                                    </span>
                                    <button class="btn" onclick="copyCode('code-<?php echo (int)$row['quiz_id']; ?>')">Copy</button>
                                </div>
                            </td>
                            <td>
                                <div class="actions">
                                    <a class="link-btn" href="edit_quiz.php?id=<?php echo (int)$row['quiz_id']; ?>">Edit</a>
                                    <?php if (empty($row['is_published'])): ?>
                                        <a class="link-btn primary" href="publish_quiz.php?id=<?php echo (int)$row['quiz_id']; ?>">Publish</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="empty">You haven’t created any quizzes yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Copy access code helper
    function copyCode(id){
        const el = document.getElementById(id);
        const txt = el ? el.textContent.trim() : '';
        if (!txt) return;
        navigator.clipboard.writeText(txt).then(()=>{
            const old = el.textContent;
            el.textContent = txt + '  ✓ Copied';
            setTimeout(()=>{ el.textContent = old; }, 1200);
        });
    }

    // Simple client-side filter
    const searchBox = document.getElementById('searchBox');
    const rows = Array.from(document.querySelectorAll('#quizTable tbody tr'));
    searchBox.addEventListener('input', function(){
        const q = this.value.toLowerCase();
        rows.forEach(r=>{
            const title = r.cells[0]?.innerText.toLowerCase() || '';
            r.style.display = title.includes(q) ? '' : 'none';
        });
    });
</script>
</body>
</html>
