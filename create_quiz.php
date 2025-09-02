<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Instructor') {
    header("Location: login.php");
    exit;
}
include 'db_config.php';

// ... your PHP insert logic stays the same ...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Quiz - QuizHero</title>
    <script src="script.js"></script>
    <style>
        :root {
            --bg1:#0e1630;
            --bg2:#1c2841;
            --brand1:#7c8cff;
            --brand2:#60a5fa;
            --accent:#22d3ee;
            --card:rgba(20,28,56,0.78);
            --cardBorder:rgba(255,255,255,0.12);
            --text:#e9eefc;
            --muted:#b9c3e6;
            --shadow:0 12px 30px rgba(0,0,0,0.35);
            --radius:16px;
        }
        @keyframes gradientShift {
            0%{background-position:0% 50%}
            50%{background-position:100% 50%}
            100%{background-position:0% 50%}
        }
        body {
            margin:0;
            min-height:100vh;
            font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background:
              radial-gradient(1200px 700px at 20% 10%, #2a3470 0%, transparent 60%),
              radial-gradient(900px 700px at 80% 20%, #3d155f 0%, transparent 60%),
              linear-gradient(120deg, var(--bg1), var(--bg2));
            background-size:400% 400%;
            animation:gradientShift 20s ease infinite;
            display:flex;
            justify-content:center;
            align-items:flex-start;
            padding:40px 20px;
            color:var(--text);
        }
        .quiz-form-container {
            position:relative;
            background:var(--card);
            border:1px solid var(--cardBorder);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            width:100%;
            max-width:750px;
            padding:50px 35px 35px; /* top padding extra for back button */
            animation:fadeIn 0.8s ease;
        }
        h2 {
            text-align:center;
            font-size:26px;
            font-weight:800;
            margin-bottom:25px;
            background:linear-gradient(90deg,var(--brand1),var(--brand2));
            -webkit-background-clip:text;
            -webkit-text-fill-color:transparent;
        }
        input, select, textarea {
            width:100%;
            padding:12px;
            margin:10px 0 20px 0;
            border:1px solid var(--cardBorder);
            border-radius:10px;
            font-size:15px;
            background:rgba(255,255,255,0.06);
            color:var(--text);
            transition:border .2s, box-shadow .2s, background .2s;
        }
        input::placeholder, textarea::placeholder { color:var(--muted); }
        input:focus, select:focus, textarea:focus {
            border-color:var(--brand1);
            outline:none;
            background:rgba(255,255,255,0.1);
            box-shadow:0 0 0 3px rgba(124,140,255,0.25);
        }

        select{
            appearance:none; -webkit-appearance:none; -moz-appearance:none;
            background-image:
              linear-gradient(90deg, transparent 0%, transparent calc(100% - 2.2rem), rgba(255,255,255,0.08) calc(100% - 2.2rem)),
              url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="%23e9eefc"><path d="M7 10l5 5 5-5z"/></svg>');
            background-repeat:no-repeat, no-repeat;
            background-position: right 10px center, right 10px center;
            background-size: 100% 100%, 22px;
        }
        select::-ms-expand{ display:none; }
        option{
            background:var(--bg2);
            color:var(--text);
        }
        option:checked,
        option:hover{
            background:var(--brand1);
            color:#fff;
        }

        .question-controls {
            display:flex;
            flex-wrap:wrap;
            gap:12px;
            margin:20px 0;
            justify-content:center;
        }
        .question-controls button {
            padding:10px 18px;
            border:1px solid var(--brand1);
            border-radius:12px;
            background:rgba(255,255,255,0.06);
            color:var(--brand1);
            font-weight:700;
            cursor:pointer;
            transition:all .25s;
            font-size:14px;
        }
        .question-controls button:hover {
            background:var(--brand1);
            color:#fff;
            transform:translateY(-2px);
        }
        button[type="submit"] {
            width:100%;
            padding:14px;
            background:linear-gradient(90deg, var(--brand1), var(--brand2));
            color:#fff;
            font-size:17px;
            border:none;
            border-radius:12px;
            cursor:pointer;
            font-weight:700;
            box-shadow:0 6px 16px rgba(0,0,0,0.25);
            transition:background .3s, transform .2s;
        }
        button[type="submit"]:hover {
            transform:translateY(-2px);
            box-shadow:0 10px 20px rgba(0,0,0,0.35);
        }
        @keyframes fadeIn {
            from { opacity:0; transform:translateY(-25px); }
            to { opacity:1; transform:translateY(0); }
        }

        /* Back button styling */
        .back-btn-top {
            position:absolute;
            top:15px; left:15px;
            padding:8px 14px;
            border:none;
            border-radius:10px;
            background:linear-gradient(90deg,var(--brand1),var(--brand2));
            color:#fff;
            font-weight:700;
            cursor:pointer;
            box-shadow:0 4px 12px rgba(0,0,0,0.25);
            transition:transform .2s, box-shadow .2s;
        }
        .back-btn-top:hover {
            transform:translateY(-2px);
            box-shadow:0 6px 16px rgba(0,0,0,0.35);
        }
    </style>
</head>
<body>
    <div class="quiz-form-container">
        <!-- Back button at top left -->
        <button type="button" class="back-btn-top" onclick="window.history.back()">← Back</button>

        <h2>Create a New Quiz</h2>
        <form method="post" onsubmit="return validateForm()">
            <input type="text" name="title" placeholder="Quiz Title" required>
            <input type="number" name="time_limit" placeholder="Time Limit (minutes)" min="1" value="5" required>

            <select name="category_id">
                <?php
                $result = $conn->query("SELECT * FROM quiz_categories");
                while ($row = $result->fetch_assoc()) {
                    echo "<option value='".htmlspecialchars($row['category_id'])."'>".htmlspecialchars($row['name'])."</option>";
                }
                ?>
            </select>

            <div id="questions"></div>

            <div class="question-controls">
                <button type="button" onclick="addQuestion()">Add Question</button>
                <button type="button" onclick="addMCQSet()">Add MCQ Set</button>
                <button type="button" onclick="addTFSet()">Add True/False Set</button>
                <button type="button" onclick="addFIBSet()">Add Fill-in-the-Blank Set</button>
                <button type="button" onclick="addMixedSet()">Add Mixed Set</button>
            </div>

            <button type="submit">Create Quiz</button>
        </form>
    </div>

    <script>
        function validateForm() {
            const timeInput = document.querySelector('input[name="time_limit"]');
            if (timeInput.value <= 0) {
                alert("Time limit must be at least 1 minute.");
                timeInput.focus();
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
