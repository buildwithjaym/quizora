<?php
// public/quiz_builder.php

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

$page_title = 'Quiz Builder • ' . APP_NAME;

$activeType = isset($_GET['type']) ? strtolower(trim((string)$_GET['type'])) : 'mcq';
$allowedTypes = ['mcq', 'identification', 'matching', 'enumeration'];
$isAllowed = false;
foreach ($allowedTypes as $t) {
    if ($activeType === $t) {
        $isAllowed = true;
        break;
    }
}
if (!$isAllowed) {
    $activeType = 'mcq';
}

$errors = [];
$added = false;

if (is_post()) {
    require_csrf();

    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';

    if ($action === 'add_question') {
        $type = isset($_POST['q_type']) ? strtolower(trim((string)$_POST['q_type'])) : '';
        $points = isset($_POST['points']) ? (float)$_POST['points'] : 1;

        if ($type === 'mcq') {
            $prompt = isset($_POST['prompt']) ? trim((string)$_POST['prompt']) : '';
            $a = isset($_POST['choice_a']) ? trim((string)$_POST['choice_a']) : '';
            $b = isset($_POST['choice_b']) ? trim((string)$_POST['choice_b']) : '';
            $c = isset($_POST['choice_c']) ? trim((string)$_POST['choice_c']) : '';
            $d = isset($_POST['choice_d']) ? trim((string)$_POST['choice_d']) : '';
            $correct = isset($_POST['correct']) ? (string)$_POST['correct'] : '';

            if ($prompt === '') $errors[] = 'Question is required.';
            if ($a === '' || $b === '' || $c === '' || $d === '') $errors[] = 'All choices A–D are required.';
            if (!in_array($correct, ['A','B','C','D'], true)) $errors[] = 'Select the correct answer.';

            if (count($errors) === 0) {
                $choices = ['A' => $a, 'B' => $b, 'C' => $c, 'D' => $d];
                $res = quiz_add_question($quizId, $teacherId, 'mcq', $prompt, $choices, $correct, ['mode' => 'single'], $points);
                if ($res['ok']) {
                    $added = true;
                    flash_set('success', 'MCQ added.');
                    redirect('/quiz_builder.php?quiz_id=' . $quizId . '&type=mcq');
                } else {
                    $errors[] = 'Could not add MCQ.';
                }
            }
        }

        if ($type === 'identification') {
            $prompt = isset($_POST['prompt']) ? trim((string)$_POST['prompt']) : '';
            $answer = isset($_POST['answer']) ? trim((string)$_POST['answer']) : '';
            $case = isset($_POST['case_sensitive']) ? (string)$_POST['case_sensitive'] : '0';
            $caseOn = $case === '1' ? true : false;

            if ($prompt === '') $errors[] = 'Question is required.';
            if ($answer === '') $errors[] = 'Correct answer is required.';

            if (count($errors) === 0) {
                $settings = ['case_sensitive' => $caseOn ? 1 : 0];
                $res = quiz_add_question($quizId, $teacherId, 'identification', $prompt, null, $answer, $settings, $points);
                if ($res['ok']) {
                    flash_set('success', 'Identification question added.');
                    redirect('/quiz_builder.php?quiz_id=' . $quizId . '&type=identification');
                } else {
                    $errors[] = 'Could not add Identification question.';
                }
            }
        }

        if ($type === 'matching') {
            $instruction = isset($_POST['instruction']) ? trim((string)$_POST['instruction']) : '';
            $shuffle = isset($_POST['shuffle']) ? (string)$_POST['shuffle'] : '0';
            $shuffleOn = $shuffle === '1' ? true : false;

            $aItems = isset($_POST['col_a']) && is_array($_POST['col_a']) ? $_POST['col_a'] : [];
            $bItems = isset($_POST['col_b']) && is_array($_POST['col_b']) ? $_POST['col_b'] : [];

            $pairs = [];
            $i = 0;
            $max = max(count($aItems), count($bItems));
            while ($i < $max) {
                $la = isset($aItems[$i]) ? trim((string)$aItems[$i]) : '';
                $lb = isset($bItems[$i]) ? trim((string)$bItems[$i]) : '';
                if ($la !== '' || $lb !== '') {
                    if ($la === '' || $lb === '') {
                        $errors[] = 'Each matching pair must have both Column A and Column B.';
                        break;
                    }
                    $pairs[] = ['a' => $la, 'b' => $lb];
                }
                $i++;
            }

            if ($instruction === '') $errors[] = 'Instruction text is required.';
            if (count($pairs) < 1) $errors[] = 'Add at least one pair.';
            if ($points <= 0) $errors[] = 'Points per pair must be at least 1.';

            if (count($errors) === 0) {
                $settings = ['shuffle' => $shuffleOn ? 1 : 0, 'points_per_pair' => (float)$points];
                $totalPoints = (float)$points * (float)count($pairs);

                $res = quiz_add_question($quizId, $teacherId, 'matching', $instruction, $pairs, json_encode(['pairs' => count($pairs)]), $settings, $totalPoints);
                if ($res['ok']) {
                    flash_set('success', 'Matching type added.');
                    redirect('/quiz_builder.php?quiz_id=' . $quizId . '&type=matching');
                } else {
                    $errors[] = 'Could not add Matching type.';
                }
            }
        }

        if ($type === 'enumeration') {
            $prompt = isset($_POST['prompt']) ? trim((string)$_POST['prompt']) : '';
            $partial = isset($_POST['partial_credit']) ? (string)$_POST['partial_credit'] : '0';
            $partialOn = $partial === '1' ? true : false;

            $answers = isset($_POST['answers']) && is_array($_POST['answers']) ? $_POST['answers'] : [];
            $clean = [];
            foreach ($answers as $ans) {
                $v = trim((string)$ans);
                if ($v !== '') {
                    $clean[] = $v;
                }
            }

            if ($prompt === '') $errors[] = 'Question is required.';
            if (count($clean) < 1) $errors[] = 'Add at least one expected answer.';
            if ($points <= 0) $errors[] = 'Points must be at least 1.';

            if (count($errors) === 0) {
                $settings = ['partial_credit' => $partialOn ? 1 : 0];
                $res = quiz_add_question($quizId, $teacherId, 'enumeration', $prompt, $clean, json_encode(['count' => count($clean)]), $settings, $points);
                if ($res['ok']) {
                    flash_set('success', 'Enumeration question added.');
                    redirect('/quiz_builder.php?quiz_id=' . $quizId . '&type=enumeration');
                } else {
                    $errors[] = 'Could not add Enumeration question.';
                }
            }
        }
    }

    if ($action === 'delete_question') {
        $qid = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
        if ($qid > 0) {
            $ok = quiz_delete_question($quizId, $teacherId, $qid);
            if ($ok) {
                flash_set('success', 'Question deleted.');
                redirect('/quiz_builder.php?quiz_id=' . $quizId . '&type=' . urlencode($activeType));
            } else {
                $errors[] = 'Could not delete question.';
            }
        }
    }
}

$questions = quiz_list_questions($quizId, $teacherId);

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/toast.php';

$totalQ = isset($quiz['total_questions']) ? (int)$quiz['total_questions'] : 0;
$totalP = isset($quiz['total_points']) ? (float)$quiz['total_points'] : 0.0;
?>

<section class="card">
  <div class="card__pad">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
      <div>
        <h1 class="card__title" style="margin-bottom:6px;">Quiz Builder</h1>
        <p class="card__subtitle" style="margin-bottom:0;">
          <strong><?php echo e($quiz['title']); ?></strong>
          <span class="muted">• <?php echo e($quiz['subject']); ?> • <?php echo e($quiz['time_limit_minutes']); ?> min</span>
        </p>
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn btn--ghost" href="<?php echo e(BASE_URL); ?>/teacher_dashboard.php">Back</a>
        <a class="btn btn--ghost" href="<?php echo e(BASE_URL); ?>/quiz_preview.php?quiz_id=<?php echo e((string)$quizId); ?>">Preview</a>
        <a class="btn btn--primary" href="<?php echo e(BASE_URL); ?>/quiz_publish.php?quiz_id=<?php echo e((string)$quizId); ?>">Final Controls</a>
      </div>
    </div>

    <div class="hr"></div>

    <div class="pills">
      <a class="pill" href="<?php echo e(BASE_URL); ?>/quiz_builder.php?quiz_id=<?php echo e((string)$quizId); ?>&type=mcq" style="<?php echo $activeType==='mcq'?'color:var(--primary); border-color:rgba(29,78,137,.28);':''; ?>">MCQ</a>
      <a class="pill" href="<?php echo e(BASE_URL); ?>/quiz_builder.php?quiz_id=<?php echo e((string)$quizId); ?>&type=identification" style="<?php echo $activeType==='identification'?'color:var(--primary); border-color:rgba(29,78,137,.28);':''; ?>">Identification</a>
      <a class="pill" href="<?php echo e(BASE_URL); ?>/quiz_builder.php?quiz_id=<?php echo e((string)$quizId); ?>&type=matching" style="<?php echo $activeType==='matching'?'color:var(--primary); border-color:rgba(29,78,137,.28);':''; ?>">Matching Type</a>
      <a class="pill" href="<?php echo e(BASE_URL); ?>/quiz_builder.php?quiz_id=<?php echo e((string)$quizId); ?>&type=enumeration" style="<?php echo $activeType==='enumeration'?'color:var(--primary); border-color:rgba(29,78,137,.28);':''; ?>">Enumeration</a>
    </div>

    <?php if (count($errors) > 0) { ?>
      <div class="alert alert--error" style="margin-top:14px;">
        <div style="font-weight:900; margin-bottom:6px;">Please fix the following:</div>
        <ul style="margin:0; padding-left:18px;">
          <?php foreach ($errors as $err) { ?>
            <li><?php echo e($err); ?></li>
          <?php } ?>
        </ul>
      </div>
    <?php } ?>

    <div class="grid" style="grid-template-columns: 1fr; gap:18px; margin-top:14px;">
      <div class="card" style="box-shadow:none;">
        <div class="card__pad">
          <h2 style="margin:0 0 8px 0; font-size:18px;">Add <?php echo e(ucfirst($activeType)); ?></h2>
          <p class="help" style="margin:0 0 12px 0;">Add questions one-by-one. Total points updates automatically.</p>

          <?php if ($activeType === 'mcq') { ?>
            <form class="form" method="post" action="<?php echo e(BASE_URL); ?>/quiz_builder.php?quiz_id=<?php echo e((string)$quizId); ?>&type=mcq" autocomplete="off">
              <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
              <input type="hidden" name="action" value="add_question">
              <input type="hidden" name="q_type" value="mcq">

              <div class="field">
                <label class="label" for="prompt">Question</label>
                <textarea class="textarea" id="prompt" name="prompt" placeholder="Type your question..." required></textarea>
              </div>

              <div class="grid" style="grid-template-columns:1fr 1fr; gap:14px;">
                <div class="field">
                  <label class="label" for="choice_a">Choice A</label>
                  <input class="input" id="choice_a" name="choice_a" required>
                </div>
                <div class="field">
                  <label class="label" for="choice_b">Choice B</label>
                  <input class="input" id="choice_b" name="choice_b" required>
                </div>
                <div class="field">
                  <label class="label" for="choice_c">Choice C</label>
                  <input class="input" id="choice_c" name="choice_c" required>
                </div>
                <div class="field">
                  <label class="label" for="choice_d">Choice D</label>
                  <input class="input" id="choice_d" name="choice_d" required>
                </div>
              </div>

              <div class="grid" style="grid-template-columns:1fr 1fr; gap:14px;">
                <div class="field">
                  <label class="label" for="correct">Correct Answer</label>
                  <select class="select" id="correct" name="correct" required>
                    <option value="" disabled selected>Select</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                  </select>
                </div>
                <div class="field">
                  <label class="label" for="points">Points</label>
                  <input class="input" id="points" name="points" type="number" min="1" step="1" value="1" required>
                </div>
              </div>

              <div class="center" style="gap:10px; flex-wrap:wrap;">
                <button class="btn btn--ghost" type="submit">+ Add Question</button>
                <a class="btn btn--primary" href="<?php echo e(BASE_URL); ?>/quiz_publish.php?quiz_id=<?php echo e((string)$quizId); ?>">Save Quiz</a>
              </div>
            </form>
          <?php } ?>

          <?php if ($activeType === 'identification') { ?>
            <form class="form" method="post" action="<?php echo e(BASE_URL); ?>/quiz_builder.php?quiz_id=<?php echo e((string)$quizId); ?>&type=identification" autocomplete="off">
              <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
              <input type="hidden" name="action" value="add_question">
              <input type="hidden" name="q_type" value="identification">

              <div class="field">
                <label class="label" for="prompt">Question</label>
                <textarea class="textarea" id="prompt" name="prompt" placeholder="Type your question..." required></textarea>
              </div>

              <div class="field">
                <label class="label" for="answer">Correct Answer</label>
                <input class="input" id="answer" name="answer" placeholder="Exact answer..." required>
              </div>

              <div class="grid" style="grid-template-columns:1fr 1fr; gap:14px;">
                <div class="field">
                  <label class="label" for="case_sensitive">Case Sensitive</label>
                  <select class="select" id="case_sensitive" name="case_sensitive">
                    <option value="1">ON</option>
                    <option value="0" selected>OFF</option>
                  </select>
                </div>
                <div class="field">
                  <label class="label" for="points">Points</label>
                  <input class="input" id="points" name="points" type="number" min="1" step="1" value="1" required>
                </div>
              </div>

              <div class="center" style="gap:10px; flex-wrap:wrap;">
                <button class="btn btn--ghost" type="submit">+ Add Question</button>
                <a class="btn btn--primary" href="<?php echo e(BASE_URL); ?>/quiz_publish.php?quiz_id=<?php echo e((string)$quizId); ?>">Save Quiz</a>
              </div>
            </form>
          <?php } ?>

          <?php if ($activeType === 'matching') { ?>
            <form class="form" method="post" action="<?php echo e(BASE_URL); ?>/quiz_builder.php?quiz_id=<?php echo e((string)$quizId); ?>&type=matching" autocomplete="off">
              <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
              <input type="hidden" name="action" value="add_question">
              <input type="hidden" name="q_type" value="matching">

              <div class="field">
                <label class="label" for="instruction">Instruction</label>
                <textarea class="textarea" id="instruction" name="instruction" placeholder="e.g., Match Column A with Column B" required></textarea>
              </div>

              <div class="grid" style="grid-template-columns:1fr 1fr; gap:14px;">
                <?php for ($i = 0; $i < 6; $i++) { ?>
                  <div class="field">
                    <label class="label">Column A (<?php echo e((string)($i+1)); ?>)</label>
                    <input class="input" name="col_a[]" placeholder="Left item">
                  </div>
                  <div class="field">
                    <label class="label">Column B (<?php echo e((string)($i+1)); ?>)</label>
                    <input class="input" name="col_b[]" placeholder="Right item">
                  </div>
                <?php } ?>
              </div>

              <div class="grid" style="grid-template-columns:1fr 1fr; gap:14px;">
                <div class="field">
                  <label class="label" for="shuffle">Shuffle Answers</label>
                  <select class="select" id="shuffle" name="shuffle">
                    <option value="1">ON</option>
                    <option value="0" selected>OFF</option>
                  </select>
                </div>
                <div class="field">
                  <label class="label" for="points">Points per pair</label>
                  <input class="input" id="points" name="points" type="number" min="1" step="1" value="1" required>
                </div>
              </div>

              <div class="center" style="gap:10px; flex-wrap:wrap;">
                <button class="btn btn--ghost" type="submit">Save Quiz</button>
              </div>

              <div class="help" style="text-align:center;">
                For MVP, add up to 6 pairs. We can make it dynamic next.
              </div>
            </form>
          <?php } ?>

          <?php if ($activeType === 'enumeration') { ?>
            <form class="form" method="post" action="<?php echo e(BASE_URL); ?>/quiz_builder.php?quiz_id=<?php echo e((string)$quizId); ?>&type=enumeration" autocomplete="off">
              <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
              <input type="hidden" name="action" value="add_question">
              <input type="hidden" name="q_type" value="enumeration">

              <div class="field">
                <label class="label" for="prompt">Question</label>
                <textarea class="textarea" id="prompt" name="prompt" placeholder="Type your question..." required></textarea>
              </div>

              <div class="grid" style="grid-template-columns:1fr 1fr; gap:14px;">
                <?php for ($i = 0; $i < 8; $i++) { ?>
                  <div class="field">
                    <label class="label">Expected Answer (<?php echo e((string)($i+1)); ?>)</label>
                    <input class="input" name="answers[]" placeholder="Answer">
                  </div>
                <?php } ?>
              </div>

              <div class="grid" style="grid-template-columns:1fr 1fr; gap:14px;">
                <div class="field">
                  <label class="label" for="partial_credit">Partial Credit</label>
                  <select class="select" id="partial_credit" name="partial_credit">
                    <option value="1">ON</option>
                    <option value="0" selected>OFF</option>
                  </select>
                </div>
                <div class="field">
                  <label class="label" for="points">Points</label>
                  <input class="input" id="points" name="points" type="number" min="1" step="1" value="1" required>
                </div>
              </div>

              <div class="center" style="gap:10px; flex-wrap:wrap;">
                <button class="btn btn--ghost" type="submit">+ Add Question</button>
                <a class="btn btn--primary" href="<?php echo e(BASE_URL); ?>/quiz_publish.php?quiz_id=<?php echo e((string)$quizId); ?>">Save Quiz</a>
              </div>

              <div class="help" style="text-align:center;">
                For MVP, add up to 8 expected answers. We can make it dynamic next.
              </div>
            </form>
          <?php } ?>
        </div>
      </div>

      <div class="card" style="box-shadow:none;">
        <div class="card__pad">
          <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
            <h2 style="margin:0; font-size:18px;">Current Questions</h2>
            <div class="pills">
              <span class="pill">Total: <strong style="color:var(--text);"><?php echo e((string)$totalQ); ?></strong></span>
              <span class="pill">Points: <strong style="color:var(--text);"><?php echo e((string)$totalP); ?></strong></span>
            </div>
          </div>

          <?php if (count($questions) === 0) { ?>
            <div class="alert" style="margin-top:14px;">
              <div style="font-weight:900; margin-bottom:4px;">No questions yet.</div>
              <div class="help">Pick a type above and add your first question.</div>
            </div>
          <?php } else { ?>
            <div style="margin-top:14px; display:grid; gap:12px;">
              <?php foreach ($questions as $q) { ?>
                <?php
                  $qt = isset($q['type']) ? (string)$q['type'] : '';
                  $qp = isset($q['prompt']) ? (string)$q['prompt'] : '';
                  $pts = isset($q['points']) ? (string)$q['points'] : '0';
                  $qid = isset($q['id']) ? (int)$q['id'] : 0;
                ?>
                <div class="card" style="box-shadow:none;">
                  <div class="card__pad" style="padding:16px;">
                    <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                      <div>
                        <div class="help" style="margin-bottom:6px;">
                          <span class="kbd"><?php echo e(strtoupper($qt)); ?></span>
                          <span class="muted">•</span>
                          <span class="muted"><?php echo e($pts); ?> pts</span>
                        </div>
                        <div style="font-weight:900;"><?php echo e($qp); ?></div>
                      </div>

                      <form method="post" action="<?php echo e(BASE_URL); ?>/quiz_builder.php?quiz_id=<?php echo e((string)$quizId); ?>&type=<?php echo e($activeType); ?>" onsubmit="return confirm('Delete this question?');">
                        <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="delete_question">
                        <input type="hidden" name="question_id" value="<?php echo e((string)$qid); ?>">
                        <button class="btn btn--danger" type="submit">Delete</button>
                      </form>
                    </div>
                  </div>
                </div>
              <?php } ?>
            </div>
          <?php } ?>
        </div>
      </div>
    </div>

    <div class="hr"></div>

    <div class="center" style="gap:10px; flex-wrap:wrap;">
      <a class="btn btn--ghost" href="<?php echo e(BASE_URL); ?>/quiz_preview.php?quiz_id=<?php echo e((string)$quizId); ?>">Preview Quiz</a>
      <a class="btn btn--primary" href="<?php echo e(BASE_URL); ?>/quiz_publish.php?quiz_id=<?php echo e((string)$quizId); ?>">Final Teacher Controls</a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
