<?php
// public/join.php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/quiz_repo.php';
require_once __DIR__ . '/../app/attempt_repo.php';

$page_title = 'Join Quiz • ' . APP_NAME;

$code = isset($_GET['code']) ? strtoupper(trim((string)$_GET['code'])) : '';
$errors = [];

$quiz = null;
if ($code !== '') {
    $quiz = quiz_get_published_by_code($code);
    if (!$quiz) {
        $errors[] = 'Quiz not found or not published. Check the code and try again.';
    }
}

$first = '';
$last = '';

if (is_post()) {
    require_csrf();

    $code = isset($_POST['code']) ? strtoupper(trim((string)$_POST['code'])) : '';
    $first = isset($_POST['first_name']) ? trim((string)$_POST['first_name']) : '';
    $last  = isset($_POST['last_name']) ? trim((string)$_POST['last_name']) : '';

    if ($code === '' || strlen($code) !== (int)QUIZ_CODE_LEN) {
        $errors[] = 'Valid quiz code is required.';
    }

    $quiz = $code !== '' ? quiz_get_published_by_code($code) : null;
    if (!$quiz) {
        $errors[] = 'Quiz not found or not published.';
    }

    if ($first === '' || mb_strlen($first) > 80) {
        $errors[] = 'First name is required (max 80 chars).';
    }
    if ($last === '' || mb_strlen($last) > 80) {
        $errors[] = 'Last name is required (max 80 chars).';
    }

    if (count($errors) === 0) {
        $studentId = student_get_or_create($first, $last);
        if (!$studentId) {
            $errors[] = 'Could not create student profile. Try again.';
        } else {
            $n = student_normalize_name($first, $last);
            $quizId = (int)$quiz['id'];

            $existing = attempt_find_existing($quizId, $studentId, $n['key']);
            if ($existing) {
                flash_set('error', 'One attempt only. You already joined or submitted this quiz.');
                if (isset($existing['attempt_uuid']) && is_string($existing['attempt_uuid']) && $existing['attempt_uuid'] !== '') {
                    redirect('/results.php?attempt=' . urlencode($existing['attempt_uuid']));
                }
                redirect('/index.php');
            }

            $_SESSION['join_quiz_code'] = $code;
            $_SESSION['join_student_id'] = (int)$studentId;
            $_SESSION['join_student_name'] = $n['full'];
            $_SESSION['join_student_key'] = $n['key'];

            redirect('/start.php?code=' . urlencode($code));
        }
    }
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/toast.php';
?>

<div class="card">
  <div class="card__pad" style="max-width:820px; margin:0 auto;">
    <h1 class="card__title">Join Quiz</h1>
    <p class="card__subtitle">Enter your name to start. One attempt only.</p>

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

    <div class="grid" style="grid-template-columns:1fr; gap:14px;">
      <?php if ($quiz) { ?>
        <div class="alert">
          <div style="font-weight:900; margin-bottom:4px;"><?php echo e($quiz['title']); ?></div>
          <div class="help">
            Subject: <strong><?php echo e($quiz['subject']); ?></strong>
            • Time limit: <strong><?php echo e((string)$quiz['time_limit_minutes']); ?> min</strong>
            • Total points: <strong><?php echo e((string)$quiz['total_points']); ?></strong>
          </div>
        </div>
      <?php } else { ?>
        <div class="alert">
          <div style="font-weight:900; margin-bottom:4px;">Enter a quiz code</div>
          <div class="help">If you opened this page without a code, type it below.</div>
        </div>
      <?php } ?>

      <form class="form" method="post" action="<?php echo e(BASE_URL); ?>/join.php<?php echo $code !== '' ? '?code=' . urlencode($code) : ''; ?>" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">

        <div class="field">
          <label class="label" for="code">Quiz Code</label>
          <input class="input" id="code" name="code" maxlength="6" value="<?php echo e($code); ?>" placeholder="e.g., A1B2C3" required>
        </div>

        <div class="grid" style="grid-template-columns:1fr 1fr; gap:14px;">
          <div class="field">
            <label class="label" for="first_name">First name</label>
            <input class="input" id="first_name" name="first_name" value="<?php echo e($first); ?>" required>
          </div>
          <div class="field">
            <label class="label" for="last_name">Last name</label>
            <input class="input" id="last_name" name="last_name" value="<?php echo e($last); ?>" required>
          </div>
        </div>

        <button class="btn btn--success btn--block" type="submit">Continue</button>

        <div class="help" style="text-align:center;">
          Tip: If you have a share link, open it to auto-fill the code.
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
