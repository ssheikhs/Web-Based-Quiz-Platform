<?php
session_start();
include 'db_config.php';

// Dynamic quiz listing based on filters and search.
$category_id = isset($_GET['category']) ? trim($_GET['category']) : '';
$search      = isset($_GET['search'])   ? trim($_GET['search'])   : '';
$sort        = isset($_GET['sort'])     ? trim($_GET['sort'])     : 'newest';

// Base SQL selects quizzes with aggregate counts for questions and attempts.
$sql = "SELECT q.quiz_id, q.title, q.access_code, q.time_limit,
               c.name AS category, u.name AS instructor,
               COUNT(DISTINCT ques.question_id) AS question_count,
               COUNT(DISTINCT lb.entry_id) AS attempts
        FROM quizzes q
        JOIN quiz_categories c ON q.category_id = c.category_id
        JOIN users u ON q.created_by = u.user_id
        LEFT JOIN questions ques ON q.quiz_id = ques.quiz_id
        LEFT JOIN leaderboards lb ON q.quiz_id = lb.quiz_id
        WHERE q.is_published = 1";

$conditions = [];
$params = [];
$types = '';

if ($category_id !== '') {
    $conditions[] = 'q.category_id = ?';
    $params[] = (int)$category_id;
    $types .= 'i';
}
if ($search !== '') {
    $conditions[] = '(q.title LIKE ? OR u.name LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
if (!empty($conditions)) {
    $sql .= ' AND ' . implode(' AND ', $conditions);
}
$sql .= ' GROUP BY q.quiz_id';

switch ($sort) {
    case 'oldest':  $sql .= ' ORDER BY q.quiz_id ASC';  break;
    case 'popular': $sql .= ' ORDER BY attempts DESC';  break;
    default:        $sql .= ' ORDER BY q.quiz_id DESC'; break;
}

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $quizResult = $stmt->get_result();
    $stmt->close();
} else {
    $quizResult = $conn->query($sql);
}

$quizzes = [];
if ($quizResult && $quizResult->num_rows > 0) {
    while ($row = $quizResult->fetch_assoc()) {
        $quizzes[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Browse Quizzes | Quiz Hero</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root{
          /* SAME palette & vibe as your hero file */
          --g1:#5b21b6;   /* deep violet */
          --g2:#0ea5e9;   /* sky blue */
          --g3:#9333ea;   /* purple */
          --g4:#22d3ee;   /* cyan */

          --light: #ffffff;
          --dark: #0f172a;
          --muted:#64748b;
          --cardBorder:#eef2ff;
          --shadow:0 10px 30px rgba(2,6,23,.12);
          --radius:14px;
        }

        html{ scroll-behavior:smooth; }

        /* Animated site background (same as your hero page body) */
        body{
          margin:0;
          font-family:'Segoe UI', Tahoma, sans-serif;
          color:var(--dark);
          background:
            radial-gradient(900px 600px at 15% 15%, rgba(147,51,234,.18), transparent 60%),
            radial-gradient(900px 600px at 85% 20%, rgba(14,165,233,.22), transparent 60%),
            linear-gradient(120deg, #f4f8fb, #eef4ff);
          animation:bgPan 22s ease infinite;
          background-size: 120% 120%;
          min-height:100vh;
        }
        @keyframes bgPan{
          0%,100%{ background-position: 0% 50%, 100% 50%, 0% 0% }
          50%   { background-position: 100% 50%, 0% 50%, 100% 100% }
        }

        /* NAV — same gradient bar */
        header.navbar{
          position:sticky; top:0; z-index:1000;
          background:linear-gradient(90deg, var(--g1), var(--g2));
          color:#fff;
          border-bottom:1px solid rgba(255,255,255,.15);
          box-shadow:0 8px 24px rgba(2,6,23,.18);
        }
        .nav-inner{
          max-width:1200px; margin:0 auto;
          padding:14px 20px; display:flex; justify-content:space-between; align-items:center;
        }
        .logo{ font-size:26px; font-weight:800; letter-spacing:.4px; display:flex; align-items:center; gap:10px; }
        .logo i{ font-size:20px; }
        nav ul{ list-style:none; display:flex; gap:26px; margin:0; padding:0; }
        nav a{ color:#fff; text-decoration:none; font-weight:700; opacity:.95; transition:opacity .18s; }
        nav a:hover{ opacity:1; }

        /* TOP STRIP (gradient mast) to match hero’s gradient but shorter */
        .mast{
          position:relative; overflow:hidden;
          padding:70px 20px 60px;
          color:#fff; text-align:center;
          background:
            radial-gradient(1200px 700px at 20% 20%, rgba(255,255,255,.14), transparent 60%),
            linear-gradient(130deg, var(--g1), var(--g3), var(--g2), var(--g4));
          background-size:160% 160%;
          animation:heroShift 16s ease-in-out infinite;
          box-shadow:0 22px 60px rgba(2,6,23,.15) inset;
          border-bottom-left-radius:30px;
          border-bottom-right-radius:30px;
        }
        @keyframes heroShift{
          0% { background-position:0% 0% }
          50%{ background-position:100% 100% }
          100%{ background-position:0% 0% }
        }
        .mast::before,.mast::after{
          content:""; position:absolute; inset:auto;
          width:420px; height:420px; border-radius:50%;
          filter:blur(60px); opacity:.25; pointer-events:none; mix-blend-mode:screen;
        }
        .mast::before{ left:-120px; top:-120px; background:radial-gradient(circle, #a78bfa, transparent 60%); }
        .mast::after { right:-140px; bottom:-140px; background:radial-gradient(circle, #22d3ee, transparent 60%); }

        .mast h1{
          margin:0; font-size:40px; font-weight:900; letter-spacing:.4px;
          text-shadow:0 6px 24px rgba(0,0,0,.25);
        }
        .mast p{ margin:10px auto 0; max-width:760px; color:#eef6ff; }

        /* CONTAINER */
        .container{ max-width:1200px; padding:0 20px; margin:26px auto 40px; }

        /* Filters panel (white card for readability) */
        .filters{
          background:var(--light);
          border:1px solid var(--cardBorder);
          border-radius:var(--radius);
          box-shadow:var(--shadow);
          padding:18px;
          margin-bottom:26px;
        }
        .filter-form{ display:flex; flex-wrap:wrap; gap:14px; align-items:end; }
        .form-group{ flex:1; min-width:220px; }
        .form-group label{ display:block; margin-bottom:8px; font-weight:700; color:var(--dark); }
        .form-group select, .form-group input{
          width:100%; padding:12px 14px; border:1px solid #e5e7eb;
          border-radius:12px; background:#f8fafc; color:var(--dark);
          outline:none; transition:box-shadow .18s,border-color .18s;
        }
        .form-group select:focus, .form-group input:focus{
          border-color:#c7d2fe; box-shadow:0 0 0 3px rgba(99,102,241,.25);
        }
        .btn{
          padding:12px 20px; border:none; border-radius:12px; cursor:pointer;
          font-weight:800; color:#fff; background:linear-gradient(90deg, var(--g1), var(--g2));
          box-shadow:0 8px 16px rgba(2,6,23,.18); transition:transform .15s;
          display:inline-flex; align-items:center; gap:8px;
        }
        .btn:hover{ transform:translateY(-1px); }

        /* Cards grid */
        .quiz-grid{
          display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
          gap:22px;
        }
        .quiz-card{
          background:#fff; border:1px solid var(--cardBorder);
          border-radius:16px; box-shadow:var(--shadow); overflow:hidden;
          transition:transform .18s, box-shadow .18s;
        }
        .quiz-card:hover{ transform:translateY(-4px); box-shadow:0 14px 34px rgba(2,6,23,.14); }
        .quiz-header{ padding:18px 18px 12px; border-bottom:1px solid #eef2ff; }
        .quiz-category{
          display:inline-block; padding:5px 10px; border-radius:999px; font-size:12px; font-weight:800;
          background:rgba(99,102,241,.12); color:#4f46e5; margin-bottom:12px;
        }
        .quiz-title{ font-size:20px; font-weight:800; margin:0 0 4px; color:var(--dark); }
        .quiz-meta{ display:flex; justify-content:space-between; color:#64748b; font-size:14px; }
        .quiz-body{ padding:16px 18px; color:#475569; }
        .quiz-footer{ height:12px; background:#f8fafc; }

        /* Empty state */
        .empty-state{
          grid-column:1/-1; text-align:center; background:#fff; border:1px solid var(--cardBorder);
          border-radius:16px; box-shadow:var(--shadow); padding:40px 20px;
        }
        .empty-state i{ font-size:60px; color:#cbd5e1; }
        .empty-state h3{ margin:12px 0 6px; color:#0f172a; }
        .empty-state p{ color:#64748b; }

        /* Footer (kept subtle dark) */
        footer{
          background:#0f172a; color:#fff; margin-top:50px; padding:36px 0;
        }
        .footer-content{
          max-width:1200px; margin:0 auto; padding:0 20px;
          display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:26px;
        }
        .footer-section h3{ margin:0 0 12px; position:relative; padding-bottom:8px; }
        .footer-section h3::after{
          content:""; position:absolute; left:0; bottom:0; width:50px; height:2px;
          background:linear-gradient(90deg, var(--g1), var(--g2));
        }
        .footer-links{ list-style:none; margin:0; padding:0; }
        .footer-links li{ margin:8px 0; }
        .footer-links a{ color:#d1d5db; text-decoration:none; }
        .footer-links a:hover{ color:#93c5fd; }
        .copyright{ text-align:center; color:#cbd5e1; margin-top:16px; font-size:14px; }

        /* Responsive */
        @media (max-width:768px){
          .nav-inner{ flex-direction:column; gap:10px; }
          nav ul{ flex-wrap:wrap; justify-content:center; }
          .filter-form{ flex-direction:column; align-items:stretch; }
          .form-group{ width:100%; }
        }
        @media (max-width:560px){
          .quiz-grid{ grid-template-columns:1fr; }
        }
    </style>
</head>
<body>

    <!-- NAV -->
    <header class="navbar">
      <div class="nav-inner">
        <div class="logo"><i class="fas fa-brain"></i> QuizHero</div>
        <nav>
          <ul>
            <li><a href="instructor_dashboard.php">Dashboard</a></li>
            <li><a href="create_quiz.php">Create Quiz</a></li>
            <li><a href="browse_quizzes.php" class="active">Browse Quizzes</a></li>
            <li><a href="analytics.php">Analytics</a></li>
          </ul>
        </nav>
      </div>
    </header>

    <!-- TOP GRADIENT STRIP (readable white text) -->
    <section class="mast">
      <h1>Browse Quizzes</h1>
      <p>Find published quizzes by searching titles or instructors</p>
    </section>

    <div class="container">
        <!-- FILTERS -->
        <section class="filters">
            <form method="get" class="filter-form">
                <div class="form-group">
                    <label for="category">Category</label>
                    <select name="category" id="category">
                        <option value="">All Categories</option>
                        <?php
                        $cat_result = $conn->query("SELECT * FROM quiz_categories ORDER BY name");
                        while ($cat = $cat_result->fetch_assoc()) {
                            $selected = ($category_id !== '' && (int)$category_id === (int)$cat['category_id']) ? 'selected' : '';
                            echo "<option value='{$cat['category_id']}' $selected>".htmlspecialchars($cat['name'])."</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" placeholder="Search quizzes…" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <label for="sort">Sort By</label>
                    <select name="sort" id="sort">
                        <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo ($sort === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="popular" <?php echo ($sort === 'popular') ? 'selected' : ''; ?>>Most Popular</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn"><i class="fas fa-filter"></i> Apply Filters</button>
                </div>
            </form>
        </section>

        <!-- GRID -->
        <section class="quiz-grid">
            <?php if (!empty($quizzes)): ?>
                <?php foreach ($quizzes as $quiz): ?>
                    <div class="quiz-card">
                        <div class="quiz-header">
                            <span class="quiz-category"><?php echo htmlspecialchars($quiz['category']); ?></span>
                            <h3 class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></h3>
                            <div class="quiz-meta">
                                <span><i class="far fa-question-circle"></i> <?php echo (int)$quiz['question_count']; ?> questions</span>
                                <span><i class="far fa-user"></i> <?php echo (int)$quiz['attempts']; ?> participants</span>
                            </div>
                        </div>
                        <div class="quiz-body">
                            Instructor: <?php echo htmlspecialchars($quiz['instructor']); ?>
                        </div>
                        <div class="quiz-footer"></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>No quizzes found</h3>
                    <p>Try adjusting your search or create a new quiz.</p>
                    <a class="btn" href="create_quiz.php"><i class="fas fa-plus"></i> Create New Quiz</a>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>About QuizPlatform</h3>
                <p>A modern platform for creating, managing and taking quizzes for educational and training purposes.</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="instructor_dashboard.php">Dashboard</a></li>
                    <li><a href="create_quiz.php">Create Quiz</a></li>
                    <li><a href="browse_quizzes.php">Browse Quizzes</a></li>
                    <li><a href="analytics.php">Analytics</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Support</h3>
                <ul class="footer-links">
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">FAQ</a></li>
                    <li><a href="#">Contact Us</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="copyright">
            &copy; 2025 Quiz Hero. All rights reserved.
        </div>
    </footer>
</body>
</html>
