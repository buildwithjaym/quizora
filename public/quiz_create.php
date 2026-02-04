<?php


require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';

require_teacher();




$tid = (int) teacher_id();
$page_title = 'Create Quiz • ' . APP_NAME;

function qc_bind_params($stmt, $types, &$params)
{
    $bind = [];
    $bind[] = $stmt;
    $bind[] = &$types;

    for ($i = 0; $i < count($params); $i++) {
        $bind[] = &$params[$i];
    }

    return call_user_func_array('mysqli_stmt_bind_param', $bind);
}


function qc_exec($sql, $types, $params)
{
    $conn = db();
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return [false, null];

    if ($types !== '') qc_bind_params($stmt, $types, $params);

    $ok = mysqli_stmt_execute($stmt);
    $id = null;
    if ($ok) $id = mysqli_insert_id($conn);

    mysqli_stmt_close($stmt);
    return [$ok, $id];
}

function qc_scalar($sql, $types, $params)
{
    $conn = db();
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return null;

    if ($types !== '') qc_bind_params($stmt, $types, $params);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }

    $val = null;

    if (function_exists('mysqli_stmt_get_result')) {
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            $row = mysqli_fetch_row($res);
            if (is_array($row) && isset($row[0])) $val = $row[0];
        }
    } else {
        mysqli_stmt_bind_result($stmt, $out);
        if (mysqli_stmt_fetch($stmt)) $val = $out;
    }

    mysqli_stmt_close($stmt);
    return $val;
}

function qc_post($k)
{
    return isset($_POST[$k]) ? trim((string) $_POST[$k]) : '';
}

function qc_post_int($k, $min, $max, $def)
{
    $raw = qc_post($k);
    if ($raw === '') return $def;
    $n = (int) $raw;
    if ($n < $min) $n = $min;
    if ($n > $max) $n = $max;
    return $n;
}

function qc_post_bool($k)
{
    return isset($_POST[$k]) ? 1 : 0;
}

function qc_make_code($len)
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    $max = strlen($chars) - 1;

    for ($i = 0; $i < $len; $i++) {
        $idx = function_exists('random_int') ? random_int(0, $max) : mt_rand(0, $max);
        $out .= $chars[$idx];
    }

    return $out;
}


$teacherFirst = 'Teacher';
$teacherLast  = '';
$tr = [];
// Fetch teacher's first and last name
$conn = db();
$stmt = mysqli_prepare($conn, "SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1");
if (!$stmt) {
    $tr = [];
} else {
    mysqli_stmt_bind_param($stmt, "i", $tid);
    if (!mysqli_stmt_execute($stmt)) {
        $tr = [];
    } else {
        $tr = [];
        if (function_exists('mysqli_stmt_get_result')) {
            $res = mysqli_stmt_get_result($stmt);
            if ($res) {
                while ($r = mysqli_fetch_assoc($res)) {
                    $tr[] = $r;
                }
            }
        }
    }
    mysqli_stmt_close($stmt);
}

$teacherFirst = 'Teacher';
$teacherLast = '';

if (count($tr) === 1) {
    if (isset($tr[0]['first_name'])) $teacherFirst = (string) $tr[0]['first_name'];
    if (isset($tr[0]['last_name'])) $teacherLast = (string) $tr[0]['last_name'];
}

$displayName = trim($teacherFirst . ' ' . $teacherLast);
if ($displayName === '') $displayName = 'Teacher';

$tr = ['displayName' => $displayName];
$displayName = $tr['displayName'];

$err = '';
$title = '';
$subject = '';
$instructions = '';
$time_limit = '0';
$due_at = '';

$shuffle = 0;
$retake = 0;
$show_score = 1;
$show_correct = 'never';
$require_login = 1;
$access_link = 0;
$case_default = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = qc_post('title');
    $subject = qc_post('subject');
    $instructions = qc_post('instructions');

    $time_limit_n = qc_post_int('time_limit_minutes', 0, 1440, 0);
    $time_limit = (string) $time_limit_n;

    $due_at_raw = qc_post('due_at');
    $due_at = $due_at_raw;

    $shuffle = qc_post_bool('shuffle_questions');
    $retake = qc_post_bool('allow_retake');
    $show_score = qc_post_bool('show_score_after_submission');

    $show_correct = qc_post('show_correct_answers_mode');
    if ($show_correct !== 'never' && $show_correct !== 'immediate' && $show_correct !== 'after_due') {
        $show_correct = 'never';
    }

    $require_login = qc_post_bool('require_login');
    $access_link = qc_post_bool('access_via_link');
    $case_default = qc_post_bool('case_sensitive_default');

    if ($title === '') $err = 'Title is required.';
    else if ($subject === '') $err = 'Subject is required.';

    $due_at_sql = null;
    if ($err === '' && $due_at_raw !== '') {
        $ts = strtotime($due_at_raw);
        if ($ts) $due_at_sql = date('Y-m-d H:i:s', $ts);
        else $err = 'Invalid due date.';
    }

    if ($err === '') {
        // Generate unique quiz code
        $code = '';
        for ($try = 0; $try < 8; $try++) {
            $candidate = qc_make_code(6);
            $exists = qc_scalar("SELECT COUNT(*) FROM quizzes WHERE quiz_code = ? LIMIT 1", "s", [$candidate]);
            if ((int) $exists === 0) { $code = $candidate; break; }
        }
        if ($code === '') $code = qc_make_code(8);

        // Prepare SQL and parameters
        $sql = "INSERT INTO quizzes
            (teacher_id, title, subject, instructions, time_limit_minutes, due_at,
             shuffle_questions, allow_retake, show_score_after_submission, show_correct_answers_mode,
             require_login, access_via_link, case_sensitive_default,
             total_questions, total_points, quiz_code, status, created_at, updated_at)
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, 'draft', NOW(), NOW())";

        $tl = $time_limit_n;

        $params = [
            $tid, 
            $title, 
            $subject, 
            $instructions,
            $tl, 
            $due_at_sql, 
            $shuffle, 
            $retake, 
            $show_score, 
            $show_correct,
            $require_login, 
            $access_link, 
            $case_default, 
            $code
        ];

        $types = "isssisiiisiiis"; // Corrected the types string

        list($ok, $newId) = qc_exec($sql, $types, $params);
        if (!$ok || !$newId) {
    http_response_code(500);
    echo "<pre>";
    echo "INSERT FAILED\n";
    echo "ok = " . var_export($ok, true) . "\n";
    echo "newId = " . var_export($newId, true) . "\n";
    echo "mysqli_error = " . mysqli_error(db()) . "\n";
    echo "</pre>";
    exit;
}

    if ($ok && $newId) {
    flash_set('success', 'Quiz created! Add questions next.');
    header('Location: ./../public/teacher_quizzes.php?created=' . (int) $newId);
    exit;
}

 else {
            $err = 'Failed to create quiz. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo e($page_title); ?></title>
  <link rel="stylesheet" href="./../assets/css/app.css" />
  <link rel="stylesheet" href="./../assets/css/dashboard.css" />
  <link rel="stylesheet" href="./../assets/css/quiz_create.css" />

  <link rel="icon" href="./../assets/img/remove_logo.png" type="image/png" />
</head>
<body class="dash-body">

<form id="qcForm" method="post" action="">
  <header class="dash-top">
    <div class="dash-wrap dash-top__inner qc-topbar">
      <a class="dash-brand" href="./../public/teacher_dashboard.php">...</a>

      <div class="qc-actions">
        <a class="qc-btn qc-btn--ghost" href="./../public/teacher_dashboard.php">Cancel</a>
        <button class="qc-btn qc-btn--primary" type="submit" form="qcForm">Save Draft & Continue</button>

      </div>
    </div>
  </header>

<main class="dash-wrap dash-main">
  <section class="dash-hero qc-hero">
    <h1 class="dash-hero__title">Create New Quiz</h1>
    <p class="dash-hero__sub">
      Welcome, Teacher <?php echo e($teacherLast !== '' ? $teacherLast : $displayName); ?> — set up your quiz, then add questions (any formats you want).
    </p>
  </section>

  <?php if ($err !== '') { ?>
    <div class="qc-alert qc-alert--bad"><?php echo e($err); ?></div>
  <?php } ?>

  <form id="qcForm" method="post" action="" class="qc-grid">
    <section class="qc-card">
      <div class="qc-card__head">
        <div>
          <h2 class="qc-card__title">Quiz Information</h2>
          <div class="qc-card__hint">Title and subject are required</div>
        </div>
      </div>

      <div class="qc-card__body">
        <div class="qc-field">
          <label class="qc-label" for="qcTitle">Quiz Title</label>
          <input id="qcTitle" class="qc-input" name="title" type="text" value="<?php echo e($title); ?>" placeholder="Enter quiz title" required />
        </div>

        <div class="qc-field">
          <label class="qc-label" for="qcSubject">Subject / Course</label>
          <input id="qcSubject" class="qc-input" name="subject" type="text" value="<?php echo e($subject); ?>" placeholder="Select / type subject" required />
        </div>

        <div class="qc-field">
          <label class="qc-label" for="qcInstr">Description / Instructions (optional)</label>
          <textarea id="qcInstr" class="qc-input qc-textarea" name="instructions" rows="5" placeholder="Enter instructions..."><?php echo e($instructions); ?></textarea>
        </div>

        <div class="qc-row">
          <div class="qc-field">
            <label class="qc-label" for="qcTime">Time Limit (minutes)</label>
            <input id="qcTime" class="qc-input" name="time_limit_minutes" type="number" min="0" max="1440" value="<?php echo e($time_limit); ?>" placeholder="0" />
            <div class="qc-help">0 = no time limit</div>
          </div>

          <div class="qc-field">
            <label class="qc-label" for="qcDue">Due Date (optional)</label>
            <input id="qcDue" class="qc-input" name="due_at" type="datetime-local" value="<?php echo e($due_at); ?>" />
            <div class="qc-help">Leave blank for no due date</div>
          </div>
        </div>

        <div class="qc-note">
          <div class="qc-note__title">Supported Question Formats (in Quiz Builder)</div>
          <div class="qc-chips">
            <span class="qc-chip">MCQ</span>
            <span class="qc-chip">Identification</span>
            <span class="qc-chip">Matching</span>
            <span class="qc-chip">Enumeration</span>
            <span class="qc-chip">True/False</span>
            <span class="qc-chip">Essay</span>
          </div>
          <div class="qc-note__sub">You can mix formats freely (or use only one format). Points per question default to 1 in the builder.</div>
        </div>
      </div>
    </section>

    <aside class="qc-card">
      <div class="qc-card__head">
        <div>
          <h2 class="qc-card__title">Quiz Settings</h2>
          <div class="qc-card__hint">Quick toggles (editable anytime)</div>
        </div>
      </div>

      <div class="qc-card__body qc-settings">
        <label class="qc-toggle">
          <span class="qc-toggle__left">
            <span class="qc-toggle__title">Shuffle Questions</span>
            <span class="qc-toggle__sub">Randomize question order</span>
          </span>
          <input type="checkbox" name="shuffle_questions" <?php echo $shuffle ? 'checked' : ''; ?> />
          <span class="qc-switch" aria-hidden="true"></span>
        </label>

        <label class="qc-toggle">
          <span class="qc-toggle__left">
            <span class="qc-toggle__title">Allow Retake</span>
            <span class="qc-toggle__sub">Students can retry the quiz</span>
          </span>
          <input type="checkbox" name="allow_retake" <?php echo $retake ? 'checked' : ''; ?> />
          <span class="qc-switch" aria-hidden="true"></span>
        </label>

        <label class="qc-toggle">
          <span class="qc-toggle__left">
            <span class="qc-toggle__title">Show Score After Submission</span>
            <span class="qc-toggle__sub">Reveal score after submit</span>
          </span>
          <input type="checkbox" name="show_score_after_submission" <?php echo $show_score ? 'checked' : ''; ?> />
          <span class="qc-switch" aria-hidden="true"></span>
        </label>

        <div class="qc-field">
          <div class="qc-label">Show Correct Answers</div>
          <div class="qc-seg">
            <label class="qc-seg__item">
              <input type="radio" name="show_correct_answers_mode" value="never" <?php echo $show_correct === 'never' ? 'checked' : ''; ?> />
              <span>Never</span>
            </label>
            <label class="qc-seg__item">
              <input type="radio" name="show_correct_answers_mode" value="immediate" <?php echo $show_correct === 'immediate' ? 'checked' : ''; ?> />
              <span>Immediately</span>
            </label>
            <label class="qc-seg__item">
              <input type="radio" name="show_correct_answers_mode" value="after_due" <?php echo $show_correct === 'after_due' ? 'checked' : ''; ?> />
              <span>After Due Date</span>
            </label>
          </div>
        </div>

        <div class="qc-divider"></div>

        <label class="qc-toggle">
          <span class="qc-toggle__left">
            <span class="qc-toggle__title">Require Login</span>
            <span class="qc-toggle__sub">Students must be logged in</span>
          </span>
          <input type="checkbox" name="require_login" <?php echo $require_login ? 'checked' : ''; ?> />
          <span class="qc-switch" aria-hidden="true"></span>
        </label>

        <label class="qc-toggle">
          <span class="qc-toggle__left">
            <span class="qc-toggle__title">Access via Link</span>
            <span class="qc-toggle__sub">Allow link-based access</span>
          </span>
          <input type="checkbox" name="access_via_link" <?php echo $access_link ? 'checked' : ''; ?> />
          <span class="qc-switch" aria-hidden="true"></span>
        </label>

        <label class="qc-toggle">
          <span class="qc-toggle__left">
            <span class="qc-toggle__title">Case Sensitive Default</span>
            <span class="qc-toggle__sub">For text answers (ID/Enum)</span>
          </span>
          <input type="checkbox" name="case_sensitive_default" <?php echo $case_default ? 'checked' : ''; ?> />
          <span class="qc-switch" aria-hidden="true"></span>
        </label>
      </div>
    </aside>
  </form>
</main>

<footer class="dash-footer">
  <div class="dash-wrap dash-footer__inner">© <?php echo e((string) date('Y')); ?> <?php echo e(APP_NAME); ?></div>
</footer>

</body>
</html>