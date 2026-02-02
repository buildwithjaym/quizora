<?php
// public/quiz_publish.php

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/quiz_repo.php';

require_teacher();

$teacherId = teacher_id();
$quizId = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

$quiz = $quizId > 0 ? quiz_get($quizId, $teacherId) : null;
if (!$quiz) {
    flash_set('error', 'Quiz not found.');
    redirect('/teacher_dashboard.php');
}

$page_title = 'Final Controls • ' . APP_NAME;

$errors = [];
$publishResult = null;

if (is_post()) {
    require_csrf();

    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
    if ($action === 'publish') {
        $res = quiz_publish($quizId, $teacherId);
        if ($res['ok']) {
            $publishResult = $res;
            flash_set('success', 'Quiz published! Share the code with your students.');
            redirect('/quiz_publish.php?quiz_id=' . $quizId);
        } else {
            $err = isset($res['error']) ? (string)$res['error'] : 'unknown';
            if ($err === 'no_questions') {
                $errors[] = 'Add at least one question before publishing.';
            } elseif ($err === 'code_gen_failed') {
                $errors[] = 'Could not generate a unique quiz code. Try again.';
            } else {
                $errors[] = 'Publish failed. Please try again.';
            }
        }
    }
}

$quiz = quiz_get($quizId, $teacherId);

$status = isset($quiz['status']) ? (string)$quiz['status'] : 'draft';
$code = isset($quiz['quiz_code']) ? (string)$quiz['quiz_code'] : '';
$timeLimit = isset($quiz['time_limit_minutes']) ? (int)$quiz['time_limit_minutes'] : 0;
$totalQ = isset($quiz['total_questions']) ? (int)$quiz['total_questions'] : 0;
$totalP = isset($quiz['total_points']) ? (float)$quiz['total_points'] : 0.0;

$joinLink = '';
if ($status === 'published' && $code !== '') {
    $joinLink = BASE_URL . '/join.php?code=' . urlencode($code);
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/toast.php';
?>

<section class="card">
  <div class="card__pad">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
      <div>
        <h1 class="card__title" style="margin-bottom:6px;">Final Teacher Controls</h1>
        <p class="card__subtitle" style="margin-bottom:0;">
          <strong><?php echo e($quiz['title']); ?></strong>
          <span class="muted">• <?php echo e($quiz['subject']); ?> • <?php echo e((string)$timeLimit); ?> min</span>
        </p>
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn btn--ghost" href="<?php echo e(BASE_URL); ?>/quiz_builder.php?quiz_id=<?php echo e((string)$quizId); ?>">Back to Builder</a>
        <a class="btn btn--ghost" href="<?php echo e(BASE_URL); ?>/quiz_preview.php?quiz_id=<?php echo e((string)$quizId); ?>">Preview Quiz</a>
      </div>
    </div>

    <div class="hr"></div>

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

    <div class="grid" style="grid-template-columns:1fr 1fr; gap:18px;">
      <div class="card" style="box-shadow:none;">
        <div class="card__pad">
          <h2 style="margin:0 0 10px 0; font-size:18px;">Quiz Summary</h2>

          <div class="stack">
            <div style="display:flex; justify-content:space-between; gap:10px;">
              <div class="muted" style="font-weight:800;">Total questions</div>
              <div style="font-weight:900;"><?php echo e((string)$totalQ); ?></div>
            </div>

            <div style="display:flex; justify-content:space-between; gap:10px;">
              <div class="muted" style="font-weight:800;">Total points</div>
              <div style="font-weight:900;"><?php echo e((string)$totalP); ?></div>
            </div>

            <div style="display:flex; justify-content:space-between; gap:10px;">
              <div class="muted" style="font-weight:800;">Time limit</div>
              <div style="font-weight:900;"><?php echo e((string)$timeLimit); ?> min</div>
            </div>

            <div style="display:flex; justify-content:space-between; gap:10px;">
              <div class="muted" style="font-weight:800;">Offline ready</div>
              <div style="font-weight:900; color:var(--success);">Yes</div>
            </div>

            <div style="display:flex; justify-content:space-between; gap:10px;">
              <div class="muted" style="font-weight:800;">Status</div>
              <div style="font-weight:900;"><?php echo e(ucfirst($status)); ?></div>
            </div>
          </div>

          <div class="hr"></div>

          <div class="center" style="gap:10px; flex-wrap:wrap;">
            <a class="btn btn--ghost" href="<?php echo e(BASE_URL); ?>/quiz_preview.php?quiz_id=<?php echo e((string)$quizId); ?>">Preview Quiz</a>

            <?php if ($status !== 'published') { ?>
              <form method="post" action="<?php echo e(BASE_URL); ?>/quiz_publish.php?quiz_id=<?php echo e((string)$quizId); ?>" style="margin:0;">
                <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="publish">
                <button class="btn btn--primary" type="submit">Publish Quiz</button>
              </form>
            <?php } else { ?>
              <span class="pill" style="border-color:rgba(16,185,129,.22); color:var(--success);">Published</span>
            <?php } ?>
          </div>

          <?php if ($status !== 'published') { ?>
            <div class="help" style="text-align:center; margin-top:10px;">
              Publishing generates a unique code and shareable link.
            </div>
          <?php } ?>
        </div>
      </div>

      <div class="card" style="box-shadow:none;">
        <div class="card__pad">
          <h2 style="margin:0 0 10px 0; font-size:18px;">Share</h2>

          <?php if ($status === 'published' && $code !== '') { ?>
            <div class="alert alert--success">
              <div style="font-weight:900; margin-bottom:6px;">Quiz Code</div>
              <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <span class="kbd" style="font-size:14px;"><?php echo e($code); ?></span>
                <span class="help" style="margin:0;">Students can join via code or link.</span>
              </div>
            </div>

            <div class="field" style="margin-top:12px;">
              <label class="label" for="share_link">Shareable Link</label>
              <input class="input" id="share_link" value="<?php echo e($joinLink); ?>" readonly>
              <div class="help">Copy and send this link: /join.php?code=XXXXXX</div>
            </div>

            <div class="hr"></div>

            <div class="center" style="gap:10px; flex-wrap:wrap;">
              <a class="btn btn--success" href="<?php echo e(BASE_URL); ?>/leaderboard.php?code=<?php echo e($code); ?>">View Leaderboard</a>
              <a class="btn btn--ghost" href="<?php echo e(BASE_URL); ?>/results.php?code=<?php echo e($code); ?>">View Results</a>
            </div>

            <div class="help" style="text-align:center; margin-top:10px;">
              Mobile tip: students can use the link to avoid typing the code.
            </div>
          <?php } else { ?>
            <div class="alert">
              <div style="font-weight:900; margin-bottom:4px;">Not published yet.</div>
              <div class="help">Publish the quiz to generate a code and share link.</div>
            </div>

            <div class="help" style="margin-top:12px;">
              Why you might publish now:
              <ul style="margin:8px 0 0 0; padding-left:18px;">
                <li>Students can join instantly via code/link</li>
                <li>QUIZORA enforces one attempt per student</li>
                <li>Leaderboard and results become available per-quiz</li>
              </ul>
            </div>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
