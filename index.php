<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Quiz Hero</title>
  <style>
    :root{
      /* you can tweak these two to shift the whole vibe */
      --g1:#5b21b6;   /* deep violet */
      --g2:#0ea5e9;   /* sky blue */
      --g3:#9333ea;   /* purple */
      --g4:#22d3ee;   /* cyan */
    }

    html{ scroll-behavior:smooth; }

    /* Subtle animated site background */
    body{
      margin:0;
      font-family:'Segoe UI', Tahoma, sans-serif;
      color:#0f172a;
      background:
        radial-gradient(900px 600px at 15% 15%, rgba(147,51,234,.18), transparent 60%),
        radial-gradient(900px 600px at 85% 20%, rgba(14,165,233,.22), transparent 60%),
        linear-gradient(120deg, #f4f8fb, #eef4ff);
      animation:bgPan 22s ease infinite;
      background-size: 120% 120%;
    }
    @keyframes bgPan{
      0%,100%{ background-position: 0% 50%, 100% 50%, 0% 0% }
      50%   { background-position: 100% 50%, 0% 50%, 100% 100% }
    }

    /* Nav with animated gradient edge */
    nav{
      position:sticky; top:0; z-index:1000;
      display:flex; justify-content:space-between; align-items:center;
      padding:14px 40px; color:#fff;
      background:linear-gradient(90deg, var(--g1), var(--g2));
      box-shadow:0 8px 24px rgba(2,6,23,.18);
      border-bottom:1px solid rgba(255,255,255,.15);
    }
    nav .logo{ font-size:26px; font-weight:800; letter-spacing:.5px; }
    nav ul{ list-style:none; display:flex; gap:26px; margin:0; padding:0; }
    nav a{
      color:#fff; text-decoration:none; font-weight:600;
      opacity:.95; transition:opacity .18s ease;
    }
    nav a:hover{ opacity:1; }

    /* HERO */
    .hero{
      text-align:center;
      padding:90px 20px 70px;
      color:#fff;
      position:relative;
      overflow:hidden;
      border-bottom-left-radius:36px;
      border-bottom-right-radius:36px;
      /* animated multi-stop gradient */
      background:
        radial-gradient(1200px 700px at 20% 20%, rgba(255,255,255,.14), transparent 60%),
        linear-gradient(130deg, var(--g1), var(--g3), var(--g2), var(--g4));
      background-size: 160% 160%;
      animation:heroShift 16s ease-in-out infinite;
      box-shadow:0 22px 60px rgba(2,6,23,.25) inset;
    }
    @keyframes heroShift{
      0%   { background-position: 0% 0% }
      50%  { background-position: 100% 100% }
      100% { background-position: 0% 0% }
    }
    /* floating aurora blobs */
    .hero::before,.hero::after{
      content:"";
      position:absolute; inset:auto;
      width:480px; height:480px; border-radius:50%;
      filter:blur(60px); opacity:.25; pointer-events:none;
      mix-blend-mode:screen;
    }
    .hero::before{ left:-120px; top:-120px; background:radial-gradient(circle, #a78bfa, transparent 60%); }
    .hero::after { right:-140px; bottom:-140px; background:radial-gradient(circle, #22d3ee, transparent 60%); }

    .hero h1{
      margin:0;
      font-size:62px; font-weight:900; letter-spacing:1px;
      text-shadow:0 6px 24px rgba(0,0,0,.25);
      animation:slideIn 1.1s ease forwards;
    }
    .hero p{
      margin:18px auto 0;
      max-width:760px; font-size:20px; line-height:1.6;
      opacity:0; animation:fadeIn 1.2s ease forwards .6s;
    }

    /* Larger dynamic marquee text */
    .dynamic-text{
      position:relative;
      height:56px;              /* visible line height */
      margin:22px auto 0;
      overflow:hidden; color:#fff; font-weight:900;
      font-size:36px;           /* bigger text */
      letter-spacing:.5px;
      text-shadow:0 4px 16px rgba(0,0,0,.25);
    }
    .dynamic-text span{
      position:absolute; left:0; right:0;
      line-height:56px;         /* must match container height */
      animation:marquee 10s cubic-bezier(.4,.0,.2,1) infinite;
      will-change:transform;
    }
    /* 4 lines => move by 56px each step */
    @keyframes marquee{
      0%   { transform:translateY(0) }
      22%  { transform:translateY(0) }
      25%  { transform:translateY(-56px) }
      47%  { transform:translateY(-56px) }
      50%  { transform:translateY(-112px) }
      72%  { transform:translateY(-112px) }
      75%  { transform:translateY(-168px) }
      97%  { transform:translateY(-168px) }
      100% { transform:translateY(0) }
    }

    .cta-buttons{ margin-top:28px; display:flex; justify-content:center; gap:14px; }
    .hero button{
      padding:14px 26px; font-size:16px; border:none; cursor:pointer;
      border-radius:12px; font-weight:800; letter-spacing:.4px;
      transition:transform .18s ease, box-shadow .18s ease, background .25s ease, color .25s ease;
    }
    .hero button:first-child{
      background:#fff; color:#0f172a;
      box-shadow:0 10px 26px rgba(2,6,23,.25);
    }
    .hero button:first-child:hover{ transform:translateY(-3px) scale(1.02); }

    .hero button:last-child{
      background:transparent; color:#fff; border:2px solid rgba(255,255,255,.85);
      box-shadow:0 10px 24px rgba(2,6,23,.2) inset;
    }
    .hero button:last-child:hover{
      background:#ffffff; color:#0f172a; transform:translateY(-3px) scale(1.02);
    }

    .hero img{
      max-width:420px; margin-top:40px;
      animation:float 4.5s ease-in-out infinite;
      filter:drop-shadow(0 16px 32px rgba(0,0,0,.35));
    }

    /* Features */
    section.features{ padding:70px 20px 90px; text-align:center; }
    section.features h2{
      font-size:34px; margin:0 0 22px;
      background:linear-gradient(90deg, var(--g3), var(--g2));
      -webkit-background-clip:text; background-clip:text; color:transparent;
    }
    .features-grid{
      display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
      gap:22px; max-width:1100px; margin:0 auto;
    }
    .feature-card{
      background:#fff; border-radius:16px; padding:22px;
      box-shadow:0 12px 28px rgba(2,6,23,.08);
      transition:transform .24s ease, box-shadow .24s ease;
      border:1px solid #eef2ff;
    }
    .feature-card:hover{ transform:translateY(-6px); box-shadow:0 18px 38px rgba(2,6,23,.12); }
    .feature-card h3{ margin:0 0 8px; color:#1e293b; }
    .feature-card p{ color:#475569; margin:0; }

    /* Animations used above */
    @keyframes slideIn{ from{opacity:0; transform:translateY(-42px)} to{opacity:1; transform:translateY(0)} }
    @keyframes fadeIn{ from{opacity:0} to{opacity:1} }
    @keyframes float{ 0%,100%{transform:translateY(0)} 50%{transform:translateY(-14px)} }

    /* Responsive tweaks */
    @media (max-width:720px){
      .hero h1{ font-size:42px; }
      .dynamic-text{ font-size:28px; height:48px; }
      .dynamic-text span{ line-height:48px; }
      @keyframes marquee{
        0%,22%   { transform:translateY(0) }
        25%,47%  { transform:translateY(-48px) }
        50%,72%  { transform:translateY(-96px) }
        75%,97%  { transform:translateY(-144px) }
        100%     { transform:translateY(0) }
      }
      .hero img{ max-width:320px; }
    }
  </style>
</head>
<body>
  <nav>
    <div class="logo">Quiz Hero</div>
    <ul>
      <li><a href="index.php">Home</a></li>
      <li><a href="browse_quizzes.php">Browse Quizzes</a></li>
      <li><a href="leaderboard.php">Leaderboard</a></li>
      <li><a href="login.php">Login</a></li>
    </ul>
  </nav>

  <section class="hero">
    <h1>QUIZ HERO</h1>
    <p>Challenge yourself with exciting quizzes, sharpen your skills, and climb the leaderboard!</p>

    <!-- Much larger rotating copy -->
    <div class="dynamic-text" aria-live="polite">
      <span>
        🧠 Test Your Knowledge<br>
        ⚡ Compete with Friends<br>
        🏆 Become a Quiz Champion<br>
        📈 Track Your Progress
      </span>
    </div>

    <div class="cta-buttons">
      <button onclick="document.getElementById('features').scrollIntoView({behavior:'smooth'})">Get Started</button>
      <button onclick="window.location.href='learn_more.php'">Learn More</button>
    </div>

    <img src="quiz_hero.png" alt="Illustration">
  </section>

  <section id="features" class="features">
    <h2>Why Choose QuizHero?</h2>
    <div class="features-grid">
      <div class="feature-card">
        <h3>🌍 Global Leaderboards</h3>
        <p>See how you rank against players worldwide in real time.</p>
      </div>
      <div class="feature-card">
        <h3>⚡ Instant Feedback</h3>
        <p>Get immediate feedback after each question to improve faster.</p>
      </div>
      <div class="feature-card">
        <h3>📊 Track Performance</h3>
        <p>Monitor your progress with detailed performance analytics.</p>
      </div>
      <div class="feature-card">
        <h3>🎯 Multiple Categories</h3>
        <p>Pick from various topics and play quizzes tailored to your interests.</p>
      </div>
    </div>
  </section>
</body>
</html>
