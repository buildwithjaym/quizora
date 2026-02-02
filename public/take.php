<?php
// public/take.php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/quiz_repo.php';
require_once __DIR__ . '/../app/attempt_repo.php';

$attemptUuid = isset($_GET['attempt']) ? trim((string)$_GET['attempt']) : '';
if ($attemptUuid === '') {
    flash_set('error', 'Missing attempt.');
    redirect('/index.php');
}

if (!isset($_SESSION['attempt_uuid']) || (string)$_SESSION['attempt_uuid'] !== $attemptUuid) {
    flash_set('error', 'Attempt session mismatch. Please join the quiz again.');
    redirect('/index.php');
}

$attempt = attempt_get_by_uuid($attemptUuid);
if (!$attempt) {
    flash_set('error', 'Attempt not found.');
    redirect('/index.php');
}

if (!isset($attempt['status']) || (string)$attempt['status'] !== 'published') {
    flash_set('error', 'Quiz is not available.');
    redirect('/index.php');
}

if (isset($attempt['submitted']) && (int)$attempt['submitted'] === 1) {
    redirect('/results.php?attempt=' . urlencode($attemptUuid));
}

$quizId = (int)$attempt['quiz_id'];
$timeLimitMin = isset($attempt['time_limit_minutes']) ? (int)$attempt['time_limit_minutes'] : 1;
if ($timeLimitMin < 1) $timeLimitMin = 1;
if ($timeLimitMin > (int)MAX_QUIZ_TIME_MIN) $timeLimitMin = (int)MAX_QUIZ_TIME_MIN;

$totalSeconds = $timeLimitMin * 60;

$startedAt = isset($_SESSION['attempt_started_at']) ? (int)$_SESSION['attempt_started_at'] : 0;
if ($startedAt <= 0) {
    $startedAt = time();
    $_SESSION['attempt_started_at'] = $startedAt;
}

$elapsed = time() - $startedAt;
if ($elapsed < 0) $elapsed = 0;
$remaining = $totalSeconds - $elapsed;
if ($remaining < 0) $remaining = 0;

$questions = attempt_questions_for_quiz($quizId);
if (count($questions) === 0) {
    flash_set('error', 'Quiz has no questions.');
    redirect('/index.php');
}

function take_json_decode($raw, $fallback)
{
    if (!is_string($raw)) return $fallback;
    $raw = trim($raw);
    if ($raw === '') return $fallback;
    $val = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) return $fallback;
    return $val;
}

function take_shuffle_copy($arr)
{
    $copy = [];
    if (is_array($arr)) {
        foreach ($arr as $v) { $copy[] = $v; }
    }
    if (count($copy) > 1) {
        shuffle($copy);
    }
    return $copy;
}

$page_title = 'Take Quiz • ' . APP_NAME;

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/toast.php';

$studentName = isset($attempt['full_name']) ? (string)$attempt['full_name'] : 'Student';
$quizTitle = isset($attempt['quiz_title']) ? (string)$attempt['quiz_title'] : 'Quiz';
?>

<section class="card">
  <div class="card__pad" style="padding:18px;">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
      <div>
        <div class="help" style="margin-bottom:6px;">
          <span class="kbd"><?php echo e($studentName); ?></span>
          <span class="muted">•</span>
          <span class="muted"><?php echo e($attempt['quiz_subject']); ?></span>
        </div>
        <h1 class="card__title" style="margin:0; font-size:22px;"><?php echo e($quizTitle); ?></h1>
      </div>

      <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <div class="pill" style="border-color:rgba(245,158,11,.25); color:var(--text);">
          Time left: <strong id="timer" style="margin-left:6px;">--:--</strong>
        </div>
        <div class="pill">
          Progress: <strong id="progress-label" style="margin-left:6px;">0/<?php echo e((string)count($questions)); ?></strong>
        </div>
      </div>
    </div>

    <div style="margin-top:12px;">
      <div class="progress">
        <div id="progress-bar" class="progress__bar" style="width:0%"></div>
      </div>
      <div class="help" style="margin-top:8px;">
        One attempt only. When time reaches 00:00, the quiz auto-submits.
      </div>
    </div>

    <div class="hr"></div>

    <div class="card" style="box-shadow:none;">
      <div class="card__pad" style="padding:16px;">
        <div style="font-weight:900; margin-bottom:10px;">Question Navigation</div>
        <div id="nav-grid" style="display:grid; grid-template-columns:repeat(10, minmax(0, 1fr)); gap:8px;">
          <?php for ($i = 0; $i < count($questions); $i++) { ?>
            <button
              type="button"
              class="btn btn--ghost"
              data-nav="<?php echo e((string)($i+1)); ?>"
              style="padding:10px 0; border-radius:12px; font-weight:900;">
              <?php echo e((string)($i+1)); ?>
            </button>
          <?php } ?>
        </div>
        <div class="help" style="margin-top:10px;">
          Tip: answered questions will highlight automatically.
        </div>
      </div>
    </div>

    <div class="hr"></div>

    <form id="quiz-form" class="form" method="post" action="<?php echo e(BASE_URL); ?>/submit.php?attempt=<?php echo e(urlencode($attemptUuid)); ?>" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
      <input type="hidden" id="time_seconds" name="time_seconds" value="0">
      <input type="hidden" id="auto_submit" name="auto_submit" value="0">

      <div id="questions" style="display:grid; gap:12px;">
        <?php $n = 1; foreach ($questions as $q) { ?>
          <?php
            $qid = isset($q['id']) ? (int)$q['id'] : 0;
            $type = isset($q['type']) ? (string)$q['type'] : '';
            $prompt = isset($q['prompt']) ? (string)$q['prompt'] : '';
            $points = isset($q['points']) ? (string)$q['points'] : '0';

            $choices = take_json_decode(isset($q['choices_json']) ? $q['choices_json'] : '', []);
            $settings = take_json_decode(isset($q['settings_json']) ? $q['settings_json'] : '', []);
          ?>

          <div class="card" id="q-<?php echo e((string)$n); ?>" data-qindex="<?php echo e((string)$n); ?>" data-question>
            <div class="card__pad" style="padding:16px;">
              <div class="help" style="margin-bottom:8px;">
                <span class="kbd">#<?php echo e((string)$n); ?></span>
                <span class="muted">•</span>
                <span class="kbd"><?php echo e(strtoupper($type)); ?></span>
                <span class="muted">•</span>
                <span class="muted"><?php echo e($points); ?> pts</span>
              </div>

              <div style="font-weight:900; margin-bottom:12px;"><?php echo e($prompt); ?></div>

              <?php if ($type === 'mcq') { ?>
                <?php
                  $labels = ['A','B','C','D'];
                  foreach ($labels as $lab) {
                      $txt = isset($choices[$lab]) ? (string)$choices[$lab] : '';
                      $name = 'mcq[' . $qid . ']';
                ?>
                  <label style="display:flex; gap:10px; align-items:flex-start; padding:10px 12px; border:1px solid var(--border); border-radius:14px; background:#fff;">
                    <input type="radio" name="<?php echo e($name); ?>" value="<?php echo e($lab); ?>" data-answer>
                    <div>
                      <div style="font-weight:900;"><?php echo e($lab); ?></div>
                      <div class="help" style="margin:0; color:var(--text);"><?php echo e($txt); ?></div>
                    </div>
                  </label>
                <?php } ?>
              <?php } ?>

              <?php if ($type === 'identification') { ?>
                <input class="input" name="identification[<?php echo e((string)$qid); ?>]" placeholder="Type your answer..." data-answer>
                <div class="help" style="margin-top:8px;">
                  Case Sensitive: <strong><?php echo e(isset($settings['case_sensitive']) && (int)$settings['case_sensitive'] === 1 ? 'ON' : 'OFF'); ?></strong>
                </div>
              <?php } ?>

              <?php if ($type === 'matching') { ?>
                <?php
                  $pairs = is_array($choices) ? $choices : [];
                  $shuffleOn = isset($settings['shuffle']) && (int)$settings['shuffle'] === 1 ? true : false;

                  $bOptions = [];
                  foreach ($pairs as $pair) {
                      if (isset($pair['b'])) {
                          $bOptions[] = (string)$pair['b'];
                      }
                  }
                  if ($shuffleOn) {
                      $bOptions = take_shuffle_copy($bOptions);
                  }
                ?>

                <div class="help" style="margin-bottom:10px;">
                  Select the matching answer for each item in Column A.
                </div>

                <div style="display:grid; gap:10px;">
                  <?php for ($i = 0; $i < count($pairs); $i++) { ?>
                    <?php
                      $aVal = isset($pairs[$i]['a']) ? (string)$pairs[$i]['a'] : '';
                      $selectName = 'matching[' . $qid . '][' . $i . ']';
                    ?>
                    <div class="card" style="box-shadow:none;">
                      <div class="card__pad" style="padding:12px; display:grid; gap:10px;">
                        <div style="font-weight:900;"><?php echo e($aVal); ?></div>
                        <select class="select" name="<?php echo e($selectName); ?>" data-answer>
                          <option value="">Select answer</option>
                          <?php foreach ($bOptions as $opt) { ?>
                            <option value="<?php echo e($opt); ?>"><?php echo e($opt); ?></option>
                          <?php } ?>
                        </select>
                      </div>
                    </div>
                  <?php } ?>
                </div>
              <?php } ?>

              <?php if ($type === 'enumeration') { ?>
                <?php
                  $expected = is_array($choices) ? $choices : [];
                  $countExpected = count($expected);
                  if ($countExpected < 1) $countExpected = 3;

                  $partial = isset($settings['partial_credit']) && (int)$settings['partial_credit'] === 1 ? 'ON' : 'OFF';
                ?>

                <div class="help" style="margin-bottom:10px;">
                  Enter as many correct items as you can. Partial Credit: <strong><?php echo e($partial); ?></strong>
                </div>

                <div class="grid" style="grid-template-columns:1fr 1fr; gap:12px;">
                  <?php for ($i = 0; $i < $countExpected; $i++) { ?>
                    <div class="field" style="gap:6px;">
                      <label class="label">Answer <?php echo e((string)($i+1)); ?></label>
                      <input class="input" name="enumeration[<?php echo e((string)$qid); ?>][]" placeholder="Type answer..." data-answer>
                    </div>
                  <?php } ?>
                </div>
              <?php } ?>
            </div>
          </div>

        <?php $n++; } ?>
      </div>

      <div class="hr"></div>

      <div class="center">
        <button id="submit-btn" class="btn btn--success" type="submit" style="min-width:220px;">
          Submit Quiz
        </button>
      </div>

      <div class="help" style="text-align:center; margin-top:10px;">
        Make sure you’re done before submitting. This cannot be undone.
      </div>
    </form>
  </div>
</section>

<script>
(function(){
  var totalQuestions = <?php echo (int)count($questions); ?>;
  var secondsTotal = <?php echo (int)$totalSeconds; ?>;
  var secondsRemaining = <?php echo (int)$remaining; ?>;

  var form = document.getElementById('quiz-form');
  var timeEl = document.getElementById('time_seconds');
  var autoEl = document.getElementById('auto_submit');

  function setNavAnswered(qIndex, answered){
    var btn = document.querySelector('[data-nav="' + qIndex + '"]');
    if (!btn) return;

    if (answered) {
      btn.style.borderColor = 'rgba(16,185,129,.35)';
      btn.style.background = 'rgba(16,185,129,.10)';
      btn.style.color = 'var(--success)';
    } else {
      btn.style.borderColor = 'var(--border)';
      btn.style.background = 'transparent';
      btn.style.color = 'var(--text)';
    }
  }

  function isQuestionAnswered(card){
    var inputs = card.querySelectorAll('[data-answer]');
    if (!inputs || inputs.length === 0) return false;

    var any = false;
    for (var i=0; i<inputs.length; i++){
      var el = inputs[i];
      if (el.type === 'radio') {
        if (el.checked) return true;
      } else if (el.tagName === 'SELECT') {
        if (String(el.value || '').trim() !== '') any = true;
      } else {
        if (String(el.value || '').trim() !== '') any = true;
      }
    }
    return any;
  }

  function updateProgress(){
    var cards = document.querySelectorAll('[data-question]');
    var answered = 0;

    for (var i=0; i<cards.length; i++){
      var qIndex = cards[i].getAttribute('data-qindex');
      var ok = isQuestionAnswered(cards[i]);
      if (ok) answered++;
      setNavAnswered(qIndex, ok);
    }

    var pct = totalQuestions > 0 ? Math.floor((answered / totalQuestions) * 100) : 0;

    if (window.Quizora && window.Quizora.setText) {
      window.Quizora.setText('progress-label', answered + '/' + totalQuestions);
    }
    if (window.Quizora && window.Quizora.setWidth) {
      window.Quizora.setWidth('progress-bar', pct);
    }
  }

  document.addEventListener('change', function(e){
    var t = e.target;
    if (!t) return;
    if (t.hasAttribute && t.hasAttribute('data-answer')) {
      updateProgress();
    }
  });

  document.getElementById('nav-grid').addEventListener('click', function(e){
    var btn = e.target.closest ? e.target.closest('button[data-nav]') : null;
    if (!btn) return;
    var idx = btn.getAttribute('data-nav');
    var el = document.getElementById('q-' + idx);
    if (el && el.scrollIntoView) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });

  function submitOnce(auto){
    if (!form) return;
    if (form.getAttribute('data-submitting') === '1') return;

    form.setAttribute('data-submitting', '1');
    if (autoEl) autoEl.value = auto ? '1' : '0';

    form.submit();
  }

  updateProgress();

  if (secondsRemaining <= 0) {
    if (timeEl) timeEl.value = String(secondsTotal);
    submitOnce(true);
    return;
  }

  if (window.Quizora && window.Quizora.countdown) {
    window.Quizora.countdown({
      secondsTotal: secondsRemaining,
      onTick: function(s){
        var used = secondsTotal - s.secondsLeft;
        if (used < 0) used = 0;
        if (used > secondsTotal) used = secondsTotal;

        if (window.Quizora && window.Quizora.setText) {
          window.Quizora.setText('timer', s.mmss);
        }
        if (timeEl) timeEl.value = String(used);
      },
      onExpire: function(){
        if (window.Quizora && window.Quizora.setText) {
          window.Quizora.setText('timer', '00:00');
        }
        submitOnce(true);
      }
    });
  }
})();
</script>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
