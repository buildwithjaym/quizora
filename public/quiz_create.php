<?php
// public/quiz_create.php

require_once __DIR__ . '/../app/auth.php';

require_teacher();

$page_title = 'Create Quiz • ' . APP_NAME;

$title = '';
$subject = '';
$duration = '10';
$errors = [];

$subjects = [
    'Math',
    'Science',
    'English',
    'Filipino',
    'Araling Panlipunan',
    'ICT / Computer',
    'Values Education',
    'General'
];

if (is_post()) {
    require_csrf();

    $title = isset($_POST['title']) ? trim((string)$_POST['title']) : '';
    $subject = isset($_POST['subject']) ? trim((string)$_POST['subject']) : '';
    $duration = isset($_POST['duration']) ? trim((string)$_POST['duration']) : '';

    if ($title === '' || mb_strlen($title) > 180) {
        $errors[] = 'Quiz title is required (max 180 chars).';
    }

    $validSubject = false;
    foreach ($subjects as $s) {
        if ($subject === $s) {
            $validSubject = true;
            break;
        }
    }
    if (!$validSubject) {
        $errors[] = 'Please choose a valid subject.';
    }

    $mins = (int)$duration;
    if ($mins < 1 || $mins > MAX_QUIZ_TIME_MIN) {
        $errors[] = 'Duration must be between 1 and ' . (int)MAX_QUIZ_TIME_MIN . ' minutes.';
    }

    if (count($errors) === 0) {
        $teacherId = teacher_id();

        $sql = "INSERT INTO quizzes (teacher_id, title, subject, time_limit_minutes, quiz_code, status)
                VALUES (?, ?, ?, ?, '', 'draft')";
        $stmt = mysqli_prepare(db(), $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "issi", $teacherId, $title, $subject, $mins);
            $ok = mysqli_stmt_execute($stmt);

            if ($ok) {
                $quizId = (int)mysqli_insert_id(db());
                flash_set('success', 'Quiz created! Now add your questions.');
                redirect('/quiz_builder.php?quiz_id=' . $quizId);
            } else {
                $errors[] = 'Could not create quiz. Please try again.';
            }

            mysqli_stmt_close($stmt);
        } else {
            $errors[] = 'Could not create quiz. Please try again.';
        }
    }
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/toast.php';
?>

<div class="card">
  <div class="card__pad" style="max-width:820px; margin:0 auto;">
    <h1 class="card__title">Create New Quiz</h1>
    <p class="card__subtitle">Fill in the basics, then continue to the quiz builder.</p>

    <?php if (count($errors) > 0) { ?>
      <div class="alert alert--error">
        <div style="font-weight:900; margin-bottom:6px;">Please fix the following:</div>
        <ul style="margin:0; padding-left:18px;">
          <?php foreach ($errors as $err) { ?>
            <li><?php echo e($err); ?></li>
          <?php } ?>
        </ul>
      </div>
    <?php } ?>

    <form class="form" method="post" action="<?php echo e(BASE_URL); ?>/quiz_create.php" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">

      <div class="field">
        <label class="label" for="title">Quiz Title</label>
        <input class="input" id="title" name="title" value="<?php echo e($title); ?>" placeholder="e.g., Chapter 2 Quiz" required>
      </div>

      <div class="grid" style="grid-template-columns:1fr 1fr; gap:14px;">
        <div class="field">
          <label class="label" for="subject">Subject</label>
          <select class="select" id="subject" name="subject" required>
            <option value="" disabled <?php echo $subject === '' ? 'selected' : ''; ?>>Select a subject</option>
            <?php foreach ($subjects as $s) { ?>
              <option value="<?php echo e($s); ?>" <?php echo $subject === $s ? 'selected' : ''; ?>>
                <?php echo e($s); ?>
              </option>
            <?php } ?>
          </select>
        </div>

        <div class="field">
          <label class="label" for="duration">Duration (minutes)</label>
          <input class="input" id="duration" name="duration" type="number" min="1" max="<?php echo e((string)MAX_QUIZ_TIME_MIN); ?>"
                 value="<?php echo e($duration); ?>" required>
          <div class="help">Timer will auto-submit when time expires.</div>
        </div>
      </div>

      <div class="field">
        <label class="label">Total Points</label>
        <div class="input" style="display:flex; align-items:center; justify-content:space-between;">
          <span class="muted">Auto-computed after you add questions</span>
          <span style="font-weight:900;">0</span>
        </div>
      </div>

      <div class="field">
        <label class="label">Question Type</label>
        <div class="pills">
          <span class="pill" title="Multiple Choice">MCQ</span>
          <span class="pill" title="Identification">Identification</span>
          <span class="pill" title="Matching Type">Matching Type</span>
          <span class="pill" title="Enumeration">Enumeration</span>
        </div>
        <div class="help">You can mix question types in the builder.</div>
      </div>

      <button class="btn btn--primary btn--block" type="submit">Continue</button>

      <div class="help" style="text-align:center;">
        <a href="<?php echo e(BASE_URL); ?>/teacher_dashboard.php" style="color:var(--primary); font-weight:800;">Back to Dashboard</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
