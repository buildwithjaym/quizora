<?php
// public/teacher_dashboard.php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';

require_teacher();

$tid = (int)teacher_id();
$page_title = 'Dashboard • ' . APP_NAME;

// Teacher name
$teacherFirst = 'Teacher';
$teacherLast  = '';
$stmtT = mysqli_prepare(db(), "SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1");
if ($stmtT) {
    mysqli_stmt_bind_param($stmtT, "i", $tid);
    mysqli_stmt_execute($stmtT);

    if (function_exists('mysqli_stmt_get_result')) {
        $resT = mysqli_stmt_get_result($stmtT);
        if ($resT) {
            $rowT = mysqli_fetch_assoc($resT);
            if (is_array($rowT)) {
                if (isset($rowT['first_name'])) $teacherFirst = (string)$rowT['first_name'];
                if (isset($rowT['last_name']))  $teacherLast  = (string)$rowT['last_name'];
            }
        }
    } else {
        mysqli_stmt_bind_result($stmtT, $fn, $ln);
        if (mysqli_stmt_fetch($stmtT)) {
            $teacherFirst = (string)$fn;
            $teacherLast  = (string)$ln;
        }
    }
    mysqli_stmt_close($stmtT);
}

// Quizzes
$quizzes = [];
$sql = "SELECT id, title, subject, time_limit_minutes, total_questions, total_points, quiz_code, status, updated_at, created_at
        FROM quizzes
        WHERE teacher_id = ?
        ORDER BY updated_at DESC, id DESC";

$stmt = mysqli_prepare(db(), $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $tid);
    mysqli_stmt_execute($stmt);

    if (function_exists('mysqli_stmt_get_result')) {
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) $quizzes[] = $row;
        }
    } else {
        mysqli_stmt_bind_result(
            $stmt,
            $id,$title,$subject,$time_limit_minutes,$total_questions,$total_points,$quiz_code,$status,$updated_at,$created_at
        );
        while (mysqli_stmt_fetch($stmt)) {
            $quizzes[] = [
                'id' => (int)$id,
                'title' => (string)$title,
                'subject' => (string)$subject,
                'time_limit_minutes' => (int)$time_limit_minutes,
                'total_questions' => (int)$total_questions,
                'total_points' => (string)$total_points,
                'quiz_code' => (string)$quiz_code,
                'status' => (string)$status,
                'updated_at' => (string)$updated_at,
                'created_at' => (string)$created_at
            ];
        }
    }
    mysqli_stmt_close($stmt);
}

$totalQuizzes = count($quizzes);
$activeQuizzes = 0;
for ($i = 0; $i < $totalQuizzes; $i++) {
    $st = isset($quizzes[$i]['status']) ? (string)$quizzes[$i]['status'] : 'draft';
    if ($st === 'published') $activeQuizzes++;
}

$recent = [];
$maxRecent = 4;
for ($i = 0; $i < $totalQuizzes && $i < $maxRecent; $i++) $recent[] = $quizzes[$i];

$toastSuccess = flash_get('success');
$toastError   = flash_get('error');

function dash_subject_icon($subject)
{
    $s = strtolower(trim((string)$subject));
    if (strpos($s, 'sci') !== false) return '🧪';
    if (strpos($s, 'math') !== false) return '🧮';
    if (strpos($s, 'eng') !== false) return '📘';
    if (strpos($s, 'fil') !== false) return '📗';
    if (strpos($s, 'ict') !== false || strpos($s, 'comp') !== false) return '💻';
    return '💡';
}

function dash_fmt_date($raw)
{
    $ts = strtotime((string)$raw);
    if (!$ts) return (string)$raw;
    return date('M d, Y', $ts);
}

$displayName = trim($teacherFirst . ' ' . $teacherLast);
if ($displayName === '') $displayName = 'Teacher';
$clockText = date('H.i');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($page_title); ?></title>

  <!-- From /public -> ../assets -->
  <link rel="stylesheet" href="../assets/css/app.css?v=1">
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=1">
  <link rel="icon" href="../assets/img/remove_logo.png" type="image/png">
</head>

<body class="dash-body">
  <!-- TOP NAV -->
  <header class="dash-top">
    <div class="dash-wrap dash-top__inner">
      <a class="dash-brand" href="index.php">
        <img class="dash-brand__logo" src="../assets/img/logo.png" alt="Quizora">
        <span class="dash-brand__text">Quizora</span>
      </a>

      <nav class="dash-nav" aria-label="Primary">
        <a class="dash-nav__link is-active" href="teacher_dashboard.php">Dashboard</a>
        <a class="dash-nav__link" href="#quizzes">Quizzes</a>
        <a class="dash-nav__link" href="#results">Results</a>
        <a class="dash-nav__link" href="#students">Students</a>
      </nav>

      <div class="dash-right">
        <div class="dash-chip" title="Time">
          <span class="dash-chip__ic">🕒</span>
          <span class="dash-chip__tx"><?php echo e($clockText); ?></span>
        </div>

        <div class="dash-user" title="<?php echo e($displayName); ?>">
          <div class="dash-user__pic">👩‍🏫</div>
          <span class="dash-user__badge">2</span>
        </div>

        <a class="dash-logout" href="logout.php">Logout</a>
      </div>
    </div>
  </header>

  <main class="dash-wrap dash-main">

    <?php if (is_string($toastSuccess) && $toastSuccess !== '') { ?>
      <div class="dash-toast dash-toast--ok" data-toast><?php echo e($toastSuccess); ?></div>
    <?php } ?>
    <?php if (is_string($toastError) && $toastError !== '') { ?>
      <div class="dash-toast dash-toast--bad" data-toast><?php echo e($toastError); ?></div>
    <?php } ?>

    <!-- HERO -->
    <section class="dash-hero">
      <h1 class="dash-hero__title">Welcome back, <?php echo e($displayName); ?>!</h1>
      <p class="dash-hero__sub">Here is what’s happening in your class today:</p>
    </section>

    <div class="dash-layout">
      <!-- LEFT -->
      <section class="dash-left">

        <!-- STATS -->
        <div class="dash-stats">
          <div class="dstat">
            <div class="dstat__ic blue">📋</div>
            <div class="dstat__meta">
              <div class="dstat__label">Active Quizzes</div>
              <div class="dstat__value"><?php echo e((string)$activeQuizzes); ?></div>
            </div>
          </div>

          <div class="dstat">
            <div class="dstat__ic green">✅</div>
            <div class="dstat__meta">
              <div class="dstat__label">Students Passed</div>
              <div class="dstat__value">—</div>
              <div class="dstat__hint">Connect results table later</div>
            </div>
          </div>

          <div class="dstat">
            <div class="dstat__ic amber">🏆</div>
            <div class="dstat__meta">
              <div class="dstat__label">Top Scorer</div>
              <div class="dstat__value">—</div>
              <div class="dstat__hint">Coming soon</div>
            </div>
          </div>
        </div>

        <!-- ACTIONS ROW -->
        <div class="dash-actions">
          <a class="dash-btn dash-btn--green" href="quiz_create.php">
            <span class="dash-btn__plus">＋</span>
            Create Quiz
          </a>

          <label class="dash-search" aria-label="Search quizzes">
            <span class="dash-search__ic">🔎</span>
            <input id="quizSearch" class="dash-search__in" type="text" placeholder="Search quizzes..." autocomplete="off">
          </label>

          <button class="dash-btn dash-btn--icon" type="button" title="Download (soon)" aria-label="Download">
            ⬇
          </button>
        </div>

        <!-- RECENT QUIZZES -->
        <section class="dash-card" id="quizzes">
          <div class="dash-card__head">
            <h2 class="dash-card__title">Recent Quizzes</h2>
            <a class="dash-card__link" href="#quizzes">View All →</a>
          </div>

          <?php if (count($recent) === 0) { ?>
            <div class="dash-empty">
              <div class="dash-empty__t">No quizzes yet.</div>
              <div class="dash-empty__s">Click “Create Quiz” to build your first quiz.</div>
            </div>
          <?php } else { ?>
            <div class="q-list" id="quizList">
              <?php foreach ($recent as $q) { ?>
                <?php
                  $qid = isset($q['id']) ? (int)$q['id'] : 0;
                  $title = isset($q['title']) ? (string)$q['title'] : 'Untitled';
                  $subject = isset($q['subject']) ? (string)$q['subject'] : 'Subject';
                  $dateTxt = dash_fmt_date(isset($q['updated_at']) ? (string)$q['updated_at'] : (isset($q['created_at']) ? (string)$q['created_at'] : ''));
                  $questions = isset($q['total_questions']) ? (int)$q['total_questions'] : 0;
                  $status = isset($q['status']) ? (string)$q['status'] : 'draft';
                  $code = isset($q['quiz_code']) ? (string)$q['quiz_code'] : '';

                  $barPct = 12;
                  if ($questions <= 0) $barPct = 10;
                  else if ($questions >= 20) $barPct = 92;
                  else $barPct = 16 + (int)($questions * 3.6);
                ?>

                <article class="q-item" data-title="<?php echo e(strtolower($title . ' ' . $subject)); ?>">
                  <div class="q-ic"><?php echo e(dash_subject_icon($subject)); ?></div>

                  <div class="q-info">
                    <div class="q-title"><?php echo e($title); ?></div>
                    <div class="q-sub"><?php echo e($subject); ?> <span class="dot">•</span> <?php echo e($dateTxt); ?></div>
                  </div>

                  <div class="q-mid">
                    <div class="q-qcount">
                      <span class="q-qnum"><?php echo e((string)$questions); ?></span>
                      <span class="q-qlbl">Questions</span>
                    </div>
                    <div class="q-bar"><span style="width:<?php echo e((string)$barPct); ?>%"></span></div>
                  </div>

                  <div class="q-act">
                    <a class="dash-btn dash-btn--blue" href="quiz_builder.php?quiz_id=<?php echo e((string)$qid); ?>">
                      Manage <span class="arr">→</span>
                    </a>

                    <div class="q-pill <?php echo e($status === 'published' ? 'is-good' : ''); ?>">
                      <?php echo e(ucfirst($status)); ?>
                      <?php if ($status === 'published' && $code !== '') { ?>
                        <span class="kbdx"><?php echo e($code); ?></span>
                      <?php } ?>
                    </div>
                  </div>
                </article>
              <?php } ?>
            </div>
          <?php } ?>
        </section>
      </section>

      <!-- RIGHT -->
      <aside class="dash-rightcol">
        <!-- LEADERBOARD (placeholder until you add results table) -->
        <section class="dash-card">
          <div class="dash-card__head">
            <h2 class="dash-card__title">Class Leaderboard</h2>
            <a class="dash-card__link" href="#results">View All →</a>
          </div>

          <div class="lb-top">
            <div class="lb-ava">🧑‍🎓</div>
            <div class="lb-top__meta">
              <div class="lb-top__name">Top Student</div>
              <div class="lb-top__sub">Score: — Points</div>
            </div>
          </div>

          <div class="lb-list">
            <div class="lb-row"><span class="lb-ava sm">👩‍🎓</span><span class="lb-name">Student A</span><span class="lb-score">—</span></div>
            <div class="lb-row"><span class="lb-ava sm">🧑‍🎓</span><span class="lb-name">Student B</span><span class="lb-score">—</span></div>
            <div class="lb-row"><span class="lb-ava sm">👩‍🎓</span><span class="lb-name">Student C</span><span class="lb-score">—</span></div>
            <div class="lb-row"><span class="lb-ava sm">🧑‍🎓</span><span class="lb-name">Student D</span><span class="lb-score">—</span></div>
          </div>
        </section>

        <!-- PERFORMANCE (placeholder chart) -->
        <section class="dash-card" id="results">
          <div class="dash-card__head">
            <h2 class="dash-card__title">Performance Overview</h2>
          </div>

          <div class="perf-legend">
            <span class="pillmini ok">● Passed</span>
            <span class="pillmini bad">● Failed</span>
          </div>

          <div class="perf-box">
            <svg viewBox="0 0 320 160" width="100%" height="160" aria-label="Performance chart">
              <path class="grid" d="M20 20H300M20 60H300M20 100H300M20 140H300" />
              <path class="axis" d="M20 20V140H300" />
              <path class="line a" d="M20 120 L90 110 L160 120 L230 95 L300 80" />
              <path class="line b" d="M20 130 L90 130 L160 120 L230 85 L300 95" />
              <circle class="dot a" cx="300" cy="80" r="5"/>
              <circle class="dot b" cx="300" cy="95" r="5"/>
            </svg>
            <div class="wk">
              <span>Week 1</span><span>Wk-2</span><span>Wk-3</span><span>Wk-4</span><span>Wk-5</span>
            </div>
          </div>
        </section>

        <section class="dash-card" id="students">
          <div class="dash-card__head">
            <h2 class="dash-card__title">Students</h2>
          </div>
          <div class="dash-empty">
            <div class="dash-empty__t">Students panel</div>
            <div class="dash-empty__s">We’ll connect this when student attempts table is ready.</div>
          </div>
        </section>
      </aside>
    </div>
  </main>

  <script>
  (function () {
    // toast auto-hide
    var t = document.querySelectorAll('[data-toast]');
    for (var i = 0; i < t.length; i++) {
      (function (el) {
        setTimeout(function () { el.classList.add('hide'); }, 3600);
      })(t[i]);
    }

    // search filter
    var input = document.getElementById('quizSearch');
    var list = document.getElementById('quizList');
    if (!input || !list) return;

    input.addEventListener('input', function () {
      var q = (input.value || '').toLowerCase();
      var items = list.querySelectorAll('.q-item');
      for (var j = 0; j < items.length; j++) {
        var t = items[j].getAttribute('data-title') || '';
        items[j].style.display = (q === '' || t.indexOf(q) !== -1) ? '' : 'none';
      }
    });
  })();
  </script>
</body>
</html>
