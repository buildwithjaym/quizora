<?php
// public/quiz_preview.php

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

$questions = quiz_list_questions($quizId, $teacherId);

$page_title = 'Preview • ' . APP_NAME;

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/toast.php';

function preview_decode_json($raw, $fallback)
{
    if (!is_string($raw) || trim($raw) === '') {
        return $fallback;
    }
    $val = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $fallback;
    }
    return $val;
}
?>

<section class="card">
  <div class="card__pad">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
      <div>
        <h1 class="card__title" style="margin-bottom:6px;">Preview Quiz</h1>
        <p class="card__subtitle" style="margin-bottom:0;">
          <strong><?php echo e($quiz['title']); ?></strong>
          <span class="muted">• <?php echo e($quiz['subject']); ?> • <?php echo e($quiz['time_limit_minutes']); ?> min</span>
        </p>
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn btn--ghost" href="<?php echo e(BASE_URL); ?>/quiz_builder.php?quiz_id=<?php echo e((string)$quizId); ?>">Back to Builder</a>
        <a class="btn btn--primary" href="<?php echo e(BASE_URL); ?>/quiz_publish.php?quiz_id=<?php echo e((string)$quizId); ?>">Final Controls</a>
      </div>
    </div>

    <div class="hr"></div>

    <div class="pills" style="margin-bottom:12px;">
      <span class="pill">Questions: <strong style="color:var(--text);"><?php echo e((string)$quiz['total_questions']); ?></strong></span>
      <span class="pill">Total Points: <strong style="color:var(--text);"><?php echo e((string)$quiz['total_points']); ?></strong></span>
      <span class="pill">Offline Ready: <strong style="color:var(--success);">Yes</strong></span>
    </div>

    <?php if (count($questions) === 0) { ?>
      <div class="alert">
        <div style="font-weight:900; margin-bottom:4px;">No questions to preview.</div>
        <div class="help">Add questions in the builder first.</div>
      </div>
    <?php } else { ?>
      <div style="display:grid; gap:12px;">
        <?php $n = 1; foreach ($questions as $q) { ?>
          <?php
            $type = isset($q['type']) ? (string)$q['type'] : '';
            $prompt = isset($q['prompt']) ? (string)$q['prompt'] : '';
            $points = isset($q['points']) ? (string)$q['points'] : '0';
            $choices = preview_decode_json(isset($q['choices_json']) ? $q['choices_json'] : null, []);
            $settings = preview_decode_json(isset($q['settings_json']) ? $q['settings_json'] : null, []);
          ?>
          <div class="card" style="box-shadow:none;">
            <div class="card__pad" style="padding:16px;">
              <div class="help" style="margin-bottom:8px;">
                <span class="kbd">#<?php echo e((string)$n); ?></span>
                <span class="muted">•</span>
                <span class="kbd"><?php echo e(strtoupper($type)); ?></span>
                <span class="muted">•</span>
                <span class="muted"><?php echo e($points); ?> pts</span>
              </div>

              <div style="font-weight:900; margin-bottom:10px;"><?php echo e($prompt); ?></div>

              <?php if ($type === 'mcq') { ?>
                <div class="grid" style="grid-template-columns:1fr; gap:10px;">
                  <?php
                    $labels = ['A','B','C','D'];
                    foreach ($labels as $lab) {
                      $txt = isset($choices[$lab]) ? (string)$choices[$lab] : '';
                  ?>
                    <label style="display:flex; gap:10px; align-items:flex-start; padding:10px 12px; border:1px solid var(--border); border-radius:14px; background:#fff;">
                      <input type="radio" disabled>
                      <div>
                        <div style="font-weight:900;"><?php echo e($lab); ?></div>
                        <div class="help" style="margin:0; color:var(--text);"><?php echo e($txt); ?></div>
                      </div>
                    </label>
                  <?php } ?>
                </div>
              <?php } ?>

              <?php if ($type === 'identification') { ?>
                <input class="input" disabled placeholder="Student will type the answer here">
                <div class="help" style="margin-top:8px;">
                  Case Sensitive: <strong><?php echo e(isset($settings['case_sensitive']) && (int)$settings['case_sensitive'] === 1 ? 'ON' : 'OFF'); ?></strong>
                </div>
              <?php } ?>

              <?php if ($type === 'matching') { ?>
                <?php
                  $pairs = is_array($choices) ? $choices : [];
                  $pp = isset($settings['points_per_pair']) ? (float)$settings['points_per_pair'] : 1.0;
                  $sh = isset($settings['shuffle']) && (int)$settings['shuffle'] === 1 ? 'ON' : 'OFF';
                ?>
                <div class="help" style="margin-bottom:10px;">
                  Shuffle: <strong><?php echo e($sh); ?></strong> • Points per pair: <strong><?php echo e((string)$pp); ?></strong>
                </div>
                <div style="overflow:auto;">
                  <table style="width:100%; border-collapse:separate; border-spacing:0; min-width:560px; background:#fff; border:1px solid var(--border); border-radius:16px;">
                    <thead>
                      <tr style="background:rgba(17,24,39,.03);">
                        <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Column A</th>
                        <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Column B</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($pairs as $pair) { ?>
                        <tr>
                          <td style="padding:12px 14px; border-top:1px solid var(--border);"><?php echo e(isset($pair['a']) ? (string)$pair['a'] : ''); ?></td>
                          <td style="padding:12px 14px; border-top:1px solid var(--border);"><?php echo e(isset($pair['b']) ? (string)$pair['b'] : ''); ?></td>
                        </tr>
                      <?php } ?>
                    </tbody>
                  </table>
                </div>
              <?php } ?>

              <?php if ($type === 'enumeration') { ?>
                <?php
                  $expected = is_array($choices) ? $choices : [];
                  $partial = isset($settings['partial_credit']) && (int)$settings['partial_credit'] === 1 ? 'ON' : 'OFF';
                ?>
                <div class="help" style="margin-bottom:10px;">
                  Partial Credit: <strong><?php echo e($partial); ?></strong> • Expected answers: <strong><?php echo e((string)count($expected)); ?></strong>
                </div>
                <div class="grid" style="grid-template-columns:1fr; gap:10px;">
                  <?php for ($i = 0; $i < max(1, count($expected)); $i++) { ?>
                    <input class="input" disabled placeholder="Answer #<?php echo e((string)($i+1)); ?>">
                  <?php } ?>
                </div>
              <?php } ?>
            </div>
          </div>
        <?php $n++; } ?>
      </div>
    <?php } ?>
  </div>
</section>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
