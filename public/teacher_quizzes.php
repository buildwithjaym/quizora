<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';

require_teacher();

$tid = (int) teacher_id();
$page_title = 'Quizzes • ' . APP_NAME;

function tq_bind_params($stmt, $types, &$params)
{
    $bind = [];
    $bind[] = $stmt;
    $bind[] = &$types;
    for ($i = 0; $i < count($params); $i++) $bind[] = &$params[$i];
    return call_user_func_array('mysqli_stmt_bind_param', $bind);
}

function tq_rows($sql, $types, $params)
{
    $conn = db();
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return [];

    if ($types !== '') tq_bind_params($stmt, $types, $params);
    if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); return []; }

    $rows = [];

    if (function_exists('mysqli_stmt_get_result')) {
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }

    // Fallback (no mysqlnd): bind result manually
    $meta = mysqli_stmt_result_metadata($stmt);
    if (!$meta) { mysqli_stmt_close($stmt); return []; }

    $fields = [];
    $row = [];
    $bind = [];

    while ($f = mysqli_fetch_field($meta)) {
        $fields[] = $f->name;
        $row[$f->name] = null;
        $bind[] = &$row[$f->name];
    }

    call_user_func_array('mysqli_stmt_bind_result', array_merge([$stmt], $bind));

    while (mysqli_stmt_fetch($stmt)) {
        $copy = [];
        for ($i = 0; $i < count($fields); $i++) {
            $k = $fields[$i];
            $copy[$k] = $row[$k];
        }
        $rows[] = $copy;
    }

    mysqli_stmt_close($stmt);
    return $rows;
}

// toast (from redirect)
$toastSuccess = flash_get('success');
$toastError   = flash_get('error');

$created_id = isset($_GET['created']) ? (int) $_GET['created'] : 0;

$quizzes = tq_rows(
    "SELECT id, title, subject, status, total_questions, total_points, time_limit_minutes, due_at, created_at, updated_at
     FROM quizzes
     WHERE teacher_id = ?
     ORDER BY created_at DESC, id DESC",
    "i",
    [$tid]
);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($page_title); ?></title>
  <link rel="stylesheet" href="./../assets/css/app.css">
  <link rel="stylesheet" href="./../assets/css/dashboard.css">
  <link rel="stylesheet" href="./../assets/css/teacher_quizzes.css">
  <link rel="icon" href="./../assets/img/remove_logo.png" type="image/png">
  <style>
    .tq-wrap{padding-top:18px}
    .tq-head{display:flex; gap:12px; align-items:flex-end; justify-content:space-between; flex-wrap:wrap}
    .tq-title{font-size:28px; margin:0}
    .tq-sub{margin:6px 0 0; color:var(--muted)}
    .tq-actions{display:flex; gap:10px; align-items:center; flex-wrap:wrap}
    .tq-btn{display:inline-flex; align-items:center; justify-content:center; padding:10px 14px; border-radius:999px; font-weight:900; text-decoration:none; border:1px solid rgba(0,0,0,.08)}
    .tq-btn--primary{background:#17a34a; color:#fff; border-color:transparent}
    .tq-btn--ghost{background:#fff; color:#111}
    .tq-card{margin-top:16px; background:#fff; border:1px solid rgba(0,0,0,.08); border-radius:16px; overflow:hidden}
    .tq-table{width:100%; border-collapse:collapse}
    .tq-table th,.tq-table td{padding:14px 12px; border-top:1px solid rgba(0,0,0,.06); vertical-align:top}
    .tq-table th{border-top:none; color:var(--muted); font-size:12px; letter-spacing:.04em; text-transform:uppercase}
    .tq-badge{display:inline-flex; padding:6px 10px; border-radius:999px; font-weight:900; font-size:12px; border:1px solid rgba(0,0,0,.08)}
    .tq-badge--draft{background:#fff7ed}
    .tq-badge--published{background:#ecfdf5}
    .tq-badge--archived{background:#f3f4f6}
    .tq-row-actions{display:flex; gap:8px; flex-wrap:wrap}
    .tq-link{font-weight:900; text-decoration:none}
    .tq-muted{color:var(--muted); font-size:13px}
    .tq-toast{margin-top:14px; padding:12px 14px; border-radius:12px; font-weight:900}
    .tq-toast--ok{background:#ecfdf5; border:1px solid rgba(16,185,129,.25)}
    .tq-toast--bad{background:#fef2f2; border:1px solid rgba(239,68,68,.25)}
    @media (max-width: 860px){
      .tq-table th:nth-child(3), .tq-table td:nth-child(3),
      .tq-table th:nth-child(4), .tq-table td:nth-child(4){
        display:none;
      }
    }
  </style>
</head>

<body class="dash-body">

<header class="dash-top">
  <div class="dash-wrap dash-top__inner">
    <a class="dash-brand" href="./../public/teacher_dashboard.php">
      <img class="dash-brand__logo" src="./../assets/img/remove_logo.png" alt="Quizora">
      <span class="dash-brand__text">Quizora</span>
    </a>

    <div class="dash-right">
      <a class="dash-logout dash-logout--desk" href="./../public/logout.php">Logout</a>
    </div>
  </div>
</header>

<main class="dash-wrap dash-main tq-wrap">
  <div class="tq-head">
    <div>
      <h1 class="tq-title">Quizzes</h1>
      <p class="tq-sub">Create quizzes, then build questions in any format (MCQ, ID, Matching, Enum, True/False, Essay).</p>
    </div>

    <div class="tq-actions">
      <a class="tq-btn tq-btn--ghost" href="./../public/teacher_dashboard.php">Back to Dashboard</a>
      <a class="tq-btn tq-btn--primary" href="./../public/quiz_create.php">+ Create Quiz</a>
    </div>
  </div>

  <?php if ($toastSuccess) { ?>
    <div class="tq-toast tq-toast--ok"><?php echo e($toastSuccess); ?></div>
  <?php } ?>

  <?php if ($toastError) { ?>
    <div class="tq-toast tq-toast--bad"><?php echo e($toastError); ?></div>
  <?php } ?>

  <?php if ($created_id > 0) { ?>
    <div class="tq-toast tq-toast--ok">
      Quiz created (ID: <?php echo e((string)$created_id); ?>). Click <b>Build Questions</b> to start adding items.
    </div>
  <?php } ?>

  <div class="tq-card">
    <?php if (count($quizzes) === 0) { ?>
      <div style="padding:18px;">
        <div style="font-weight:900; font-size:18px;">No quizzes yet</div>
        <div class="tq-muted" style="margin-top:6px;">Click “Create Quiz” to make your first quiz draft.</div>
      </div>
    <?php } else { ?>
      <table class="tq-table">
        <thead>
          <tr>
            <th>Quiz</th>
            <th>Status</th>
            <th>Items / Points</th>
            <th>Time / Due</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php for ($i = 0; $i < count($quizzes); $i++) {
            $q = $quizzes[$i];
            $id = (int) $q['id'];
            $title = (string) $q['title'];
            $subject = (string) $q['subject'];
            $status = (string) $q['status'];
            $tq = (int) $q['total_questions'];
            $tp = (float) $q['total_points'];
            $tl = (int) $q['time_limit_minutes'];
            $due = $q['due_at'];

            $badgeClass = 'tq-badge--draft';
            if ($status === 'published') $badgeClass = 'tq-badge--published';
            if ($status === 'archived') $badgeClass = 'tq-badge--archived';

            $dueText = 'No due date';
            if ($due !== null && (string)$due !== '') $dueText = date('M d, Y h:i A', strtotime((string)$due));

            $timeText = ($tl > 0) ? ($tl . ' min') : 'No limit';
        ?>
          <tr>
            <td>
              <div style="font-weight:900;"><?php echo e($title); ?></div>
              <div class="tq-muted"><?php echo e($subject); ?></div>
            </td>
            <td>
              <span class="tq-badge <?php echo $badgeClass; ?>"><?php echo e(ucfirst($status)); ?></span>
            </td>
            <td>
              <div style="font-weight:900;"><?php echo e((string)$tq); ?> items</div>
              <div class="tq-muted"><?php echo e(number_format($tp, 2)); ?> points</div>
            </td>
            <td>
              <div style="font-weight:900;"><?php echo e($timeText); ?></div>
              <div class="tq-muted"><?php echo e($dueText); ?></div>
            </td>
            <td>
              <div class="tq-row-actions">
                <a class="tq-btn tq-btn--primary" href="./../public/quiz_builder.php?quiz_id=<?php echo (int)$id; ?>">Build Questions</a>
                <a class="tq-btn tq-btn--ghost" href="./../public/quiz_create.php?edit=<?php echo (int)$id; ?>">Edit Settings</a>
              </div>
              <div class="tq-muted" style="margin-top:6px;">ID: <?php echo e((string)$id); ?></div>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    <?php } ?>
  </div>

</main>

<footer class="dash-footer">
  <div class="dash-wrap dash-footer__inner">© <?php echo e((string) date('Y')); ?> <?php echo e(APP_NAME); ?></div>
</footer>

</body>
</html>
