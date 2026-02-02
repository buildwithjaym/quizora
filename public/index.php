<?php
// public/index.php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';

$page_title = 'Welcome • ' . APP_NAME;

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/toast.php';
?>

<div class="grid grid--2">
  <section class="card">
    <div class="card__pad">
      <h1 class="card__title">Build quizzes. Share a code. Track results.</h1>
      <p class="card__subtitle">
        QUIZORA is an offline-friendly quiz platform for teachers and students.
        Create quizzes, publish them, and let students join via a simple code.
      </p>

      <div class="pills" style="margin-bottom:14px;">
        <span class="pill">Fast</span>
        <span class="pill">Mobile-first</span>
        <span class="pill">One attempt</span>
        <span class="pill">Timer + Auto-submit</span>
      </div>

      <?php if (teacher_is_logged_in()) { ?>
        <a class="btn btn--primary" href="<?php echo e(BASE_URL); ?>/teacher_dashboard.php">Go to Dashboard</a>
      <?php } else { ?>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <a class="btn btn--primary" href="<?php echo e(BASE_URL); ?>/register.php">Teacher Register</a>
          <a class="btn btn--ghost" href="<?php echo e(BASE_URL); ?>/login.php">Teacher Login</a>
        </div>
      <?php } ?>

      <div class="hr"></div>

      <h2 style="margin:0 0 8px 0; font-size:18px;">Student</h2>
      <p class="help" style="margin:0 0 12px 0;">
        If you have a quiz code, join here.
      </p>

      <form class="form" method="get" action="<?php echo e(BASE_URL); ?>/join.php" autocomplete="off">
        <div class="field">
          <label class="label" for="code">Quiz Code</label>
          <input class="input" id="code" name="code" maxlength="6" placeholder="e.g., A1B2C3" required>
          <div class="help">Codes are 6 characters. Ask your teacher for the code or link.</div>
        </div>
        <button class="btn btn--success btn--block" type="submit">Join Quiz</button>
      </form>
    </div>
  </section>

  <aside class="card">
    <div class="card__pad">
      <h2 style="margin:0 0 8px 0; font-size:18px;">How it works</h2>
      <div class="stack">
        <div>
          <div style="font-weight:900;">1) Teacher creates a quiz</div>
          <div class="help">Add MCQ, Identification, Matching, and Enumeration questions.</div>
        </div>
        <div>
          <div style="font-weight:900;">2) Publish to get a code</div>
          <div class="help">QUIZORA generates a unique 6-character quiz code and link.</div>
        </div>
        <div>
          <div style="font-weight:900;">3) Student joins and takes the quiz</div>
          <div class="help">Name only (MVP). One attempt. Timer auto-submits when time ends.</div>
        </div>
        <div>
          <div style="font-weight:900;">4) Results + leaderboard</div>
          <div class="help">Rank by score, tie-breaker by time used.</div>
        </div>
      </div>

      <div class="hr"></div>

      <div class="help">
        Tip: Save the publish link and share it to your class group chat.
      </div>
    </div>
  </aside>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
