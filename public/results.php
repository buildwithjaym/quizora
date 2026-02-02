<?php
// public/results.php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/quiz_repo.php';
require_once __DIR__ . '/../app/attempt_repo.php';

function fmt_num($n)
{
    $n = (float)$n;
    if (floor($n) == $n) return (string)((int)$n);
    return number_format($n, 2, '.', '');
}

function fmt_mmss($sec)
{
    $sec = (int)$sec;
    if ($sec < 0) $sec = 0;
    $mm = (int)floor($sec / 60);
    $ss = (int)($sec % 60);
    return str_pad((string)$mm, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string)$ss, 2, '0', STR_PAD_LEFT);
}

function safe_type_score($arr, $key)
{
    if (!is_array($arr) || !array_key_exists($key, $arr)) return 0.0;
    return (float)$arr[$key];
}

$attemptUuid = isset($_GET['attempt']) ? trim((string)$_GET['attempt']) : '';
$code = isset($_GET['code']) ? strtoupper(trim((string)$_GET['code'])) : '';

if ($attemptUuid === '' && $code === '') {
    flash_set('error', 'Missing results parameter.');
    redirect('/index.php');
}

$page_title = 'Results • ' . APP_NAME;

$toastTitle = isset($_SESSION['toast_title']) ? (string)$_SESSION['toast_title'] : '';
$toastMsg = isset($_SESSION['toast_msg']) ? (string)$_SESSION['toast_msg'] : '';
unset($_SESSION['toast_title']);
unset($_SESSION['toast_msg']);

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/toast.php';

if ($toastTitle !== '' && $toastMsg !== '') {
?>
  <div data-toast-title="<?php echo e($toastTitle); ?>" data-toast-msg="<?php echo e($toastMsg); ?>"></div>
<?php
}

if ($attemptUuid !== '') {
    $attempt = attempt_get_by_uuid($attemptUuid);
    if (!$attempt) {
        flash_set('error', 'Results not found.');
        redirect('/index.php');
    }

    if (!isset($attempt['submitted']) || (int)$attempt['submitted'] !== 1) {
        redirect('/take.php?attempt=' . urlencode($attemptUuid));
    }

    $scores = repo_json_decode(isset($attempt['scores_json']) ? $attempt['scores_json'] : '', []);
    $byType = isset($scores['by_type']) && is_array($scores['by_type']) ? $scores['by_type'] : [];
    $maxByType = isset($scores['max_by_type']) && is_array($scores['max_by_type']) ? $scores['max_by_type'] : [];

    $total = isset($attempt['total_score']) ? (float)$attempt['total_score'] : 0.0;
    $maxTotal = isset($attempt['total_points']) ? (float)$attempt['total_points'] : 0.0;

    $pct = 0;
    if ($maxTotal > 0) {
        $pct = (int)floor(($total / $maxTotal) * 100);
    }

    $compliment = isset($attempt['compliment']) ? (string)$attempt['compliment'] : 'Nice work!';
    $timeSeconds = isset($attempt['time_seconds']) ? (int)$attempt['time_seconds'] : 0;

    $quizCode = isset($attempt['quiz_code']) ? (string)$attempt['quiz_code'] : '';
?>
<section class="card">
  <div class="card__pad">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
      <div>
        <div class="help" style="margin-bottom:6px;">
          <span class="kbd"><?php echo e($attempt['full_name']); ?></span>
          <span class="muted">•</span>
          <span class="muted"><?php echo e($attempt['quiz_subject']); ?></span>
        </div>
        <h1 class="card__title" style="margin:0; font-size:24px;">Your Results</h1>
        <p class="card__subtitle" style="margin-top:6px;"><?php echo e($attempt['quiz_title']); ?></p>
      </div>

      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <?php if ($quizCode !== '') { ?>
          <a class="btn btn--success" href="<?php echo e(BASE_URL); ?>/leaderboard.php?code=<?php echo e($quizCode); ?>">Leaderboard</a>
          <a class="btn btn--ghost" href="<?php echo e(BASE_URL); ?>/results.php?code=<?php echo e($quizCode); ?>">All Results</a>
        <?php } ?>
        <a class="btn btn--ghost" href="<?php echo e(BASE_URL); ?>/index.php">Home</a>
      </div>
    </div>

    <div class="hr"></div>

    <div class="grid" style="grid-template-columns: 1fr 1fr; gap:18px;">
      <div class="card" style="box-shadow:none;">
        <div class="card__pad">
          <h2 style="margin:0 0 10px 0; font-size:18px;">Score</h2>

          <div class="stack">
            <div style="display:flex; justify-content:space-between; gap:10px;">
              <div class="muted" style="font-weight:800;">Total score</div>
              <div style="font-weight:900;"><?php echo e(fmt_num($total)); ?> / <?php echo e(fmt_num($maxTotal)); ?></div>
            </div>

            <div style="display:flex; justify-content:space-between; gap:10px;">
              <div class="muted" style="font-weight:800;">Percent</div>
              <div style="font-weight:900;"><?php echo e((string)$pct); ?>%</div>
            </div>

            <div style="display:flex; justify-content:space-between; gap:10px;">
              <div class="muted" style="font-weight:800;">Time used</div>
              <div style="font-weight:900;"><?php echo e(fmt_mmss($timeSeconds)); ?></div>
            </div>
          </div>

          <div class="hr"></div>

          <div class="alert alert--success" style="margin:0;">
            <div style="font-weight:900; margin-bottom:4px;">Compliment</div>
            <div class="help" style="margin:0; color:var(--text);"><?php echo e($compliment); ?></div>
          </div>
        </div>
      </div>

      <div class="card" style="box-shadow:none;">
        <div class="card__pad">
          <h2 style="margin:0 0 10px 0; font-size:18px;">Breakdown by type</h2>

          <div style="display:grid; gap:10px;">
            <?php
              $types = [
                ['key' => 'mcq', 'label' => 'MCQ'],
                ['key' => 'identification', 'label' => 'Identification'],
                ['key' => 'matching', 'label' => 'Matching Type'],
                ['key' => 'enumeration', 'label' => 'Enumeration'],
              ];

              foreach ($types as $t) {
                $k = $t['key'];
                $got = safe_type_score($byType, $k);
                $mx  = safe_type_score($maxByType, $k);
                $p = 0;
                if ($mx > 0) {
                  $p = (int)floor(($got / $mx) * 100);
                }
            ?>
              <div class="card" style="box-shadow:none;">
                <div class="card__pad" style="padding:12px;">
                  <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                    <div style="font-weight:900;"><?php echo e($t['label']); ?></div>
                    <div class="muted" style="font-weight:800;"><?php echo e(fmt_num($got)); ?> / <?php echo e(fmt_num($mx)); ?></div>
                  </div>
                  <div style="margin-top:10px;">
                    <div class="progress">
                      <div class="progress__bar" style="width:<?php echo e((string)$p); ?>%"></div>
                    </div>
                    <div class="help" style="margin-top:6px;"><?php echo e((string)$p); ?>%</div>
                  </div>
                </div>
              </div>
            <?php } ?>
          </div>

          <div class="help" style="margin-top:10px;">
            Matching is scored per correct pair. Enumeration can use partial credit depending on the setting.
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php
    include __DIR__ . '/../views/partials/footer.php';
    exit;
}

if ($code === '' || strlen($code) !== (int)QUIZ_CODE_LEN) {
    flash_set('error', 'Invalid quiz code.');
    redirect('/index.php');
}

$quiz = quiz_get_published_by_code($code);
if (!$quiz) {
    flash_set('error', 'Quiz not found or not published.');
    redirect('/index.php');
}

$quizId = (int)$quiz['id'];
$maxPoints = isset($quiz['total_points']) ? (float)$quiz['total_points'] : 0.0;

$sql = "SELECT a.attempt_uuid, a.total_score, a.time_seconds, a.scores_json, a.submitted_at, s.full_name
        FROM attempts a
        INNER JOIN students s ON s.id = a.student_id
        WHERE a.quiz_id = ? AND a.submitted = 1
        ORDER BY a.total_score DESC, a.time_seconds ASC, a.submitted_at ASC";
$stmt = mysqli_prepare(db(), $sql);
$rows = [];

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $quizId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = $r;
        }
    }
    mysqli_stmt_close($stmt);
}

$count = count($rows);

$sum = 0.0;
$best = null;
$worst = null;

foreach ($rows as $r) {
    $s = isset($r['total_score']) ? (float)$r['total_score'] : 0.0;
    $sum += $s;

    if ($best === null || $s > $best) $best = $s;
    if ($worst === null || $s < $worst) $worst = $s;
}

$avg = $count > 0 ? ($sum / (float)$count) : 0.0;

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/toast.php';
?>

<section class="card">
  <div class="card__pad">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
      <div>
        <div class="help" style="margin-bottom:6px;">
          <span class="kbd"><?php echo e($code); ?></span>
          <span class="muted">•</span>
          <span class="muted"><?php echo e($quiz['subject']); ?></span>
        </div>
        <h1 class="card__title" style="margin:0; font-size:24px;">All Results</h1>
        <p class="card__subtitle" style="margin-top:6px;"><?php echo e($quiz['title']); ?></p>
      </div>

      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn btn--success" href="<?php echo e(BASE_URL); ?>/leaderboard.php?code=<?php echo e($code); ?>">Leaderboard</a>
        <a class="btn btn--ghost" href="<?php echo e(BASE_URL); ?>/index.php">Home</a>
      </div>
    </div>

    <div class="hr"></div>

    <div class="pills" style="margin-bottom:12px;">
      <span class="pill">Submissions: <strong style="color:var(--text);"><?php echo e((string)$count); ?></strong></span>
      <span class="pill">Max points: <strong style="color:var(--text);"><?php echo e(fmt_num($maxPoints)); ?></strong></span>
      <span class="pill">Avg: <strong style="color:var(--text);"><?php echo e(fmt_num($avg)); ?></strong></span>
      <span class="pill">Best: <strong style="color:var(--text);"><?php echo e(fmt_num($best === null ? 0 : $best)); ?></strong></span>
    </div>

    <div class="card" style="box-shadow:none;">
      <div class="card__pad">
        <h2 style="margin:0 0 10px 0; font-size:18px;">Results Table</h2>

        <?php if ($count === 0) { ?>
          <div class="alert">
            <div style="font-weight:900; margin-bottom:4px;">No submissions yet.</div>
            <div class="help">When students submit, their results will appear here.</div>
          </div>
        <?php } else { ?>
          <div style="overflow:auto; border:1px solid var(--border); border-radius:16px;">
            <table style="width:100%; border-collapse:separate; border-spacing:0; min-width:980px; background:#fff;">
              <thead>
                <tr style="background:rgba(17,24,39,.03);">
                  <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Rank</th>
                  <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Student</th>
                  <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Total</th>
                  <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Time</th>
                  <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">MCQ</th>
                  <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">ID</th>
                  <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Match</th>
                  <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Enum</th>
                  <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">View</th>
                </tr>
              </thead>
              <tbody>
                <?php $rank = 1; foreach ($rows as $r) { ?>
                  <?php
                    $name = isset($r['full_name']) ? (string)$r['full_name'] : '';
                    $score = isset($r['total_score']) ? (float)$r['total_score'] : 0.0;
                    $time = isset($r['time_seconds']) ? (int)$r['time_seconds'] : 0;
                    $uuid = isset($r['attempt_uuid']) ? (string)$r['attempt_uuid'] : '';

                    $sj = isset($r['scores_json']) ? (string)$r['scores_json'] : '';
                    $payload = repo_json_decode($sj, []);
                    $by = isset($payload['by_type']) && is_array($payload['by_type']) ? $payload['by_type'] : [];

                    $mcq = safe_type_score($by, 'mcq');
                    $idn = safe_type_score($by, 'identification');
                    $mat = safe_type_score($by, 'matching');
                    $enu = safe_type_score($by, 'enumeration');

                    $isTop = $rank <= 5;
                    $bg = $isTop ? 'rgba(245,158,11,.10)' : '#fff';
                    $border = $isTop ? 'rgba(245,158,11,.22)' : 'var(--border)';
                  ?>
                  <tr style="background:<?php echo e($bg); ?>;">
                    <td style="padding:12px 14px; border-top:1px solid <?php echo e($border); ?>;">
                      <span style="font-weight:900;"><?php echo e((string)$rank); ?></span>
                    </td>
                    <td style="padding:12px 14px; border-top:1px solid <?php echo e($border); ?>;">
                      <div style="font-weight:900;"><?php echo e($name); ?></div>
                      <?php if ($isTop) { ?><div class="help">Top <?php echo e((string)$rank); ?></div><?php } ?>
                    </td>
                    <td style="padding:12px 14px; border-top:1px solid <?php echo e($border); ?>;">
                      <span class="kbd"><?php echo e(fmt_num($score)); ?></span>
                      <span class="muted">/</span>
                      <span class="muted"><?php echo e(fmt_num($maxPoints)); ?></span>
                    </td>
                    <td style="padding:12px 14px; border-top:1px solid <?php echo e($border); ?>;">
                      <span class="kbd"><?php echo e(fmt_mmss($time)); ?></span>
                    </td>
                    <td style="padding:12px 14px; border-top:1px solid <?php echo e($border); ?>;"><?php echo e(fmt_num($mcq)); ?></td>
                    <td style="padding:12px 14px; border-top:1px solid <?php echo e($border); ?>;"><?php echo e(fmt_num($idn)); ?></td>
                    <td style="padding:12px 14px; border-top:1px solid <?php echo e($border); ?>;"><?php echo e(fmt_num($mat)); ?></td>
                    <td style="padding:12px 14px; border-top:1px solid <?php echo e($border); ?>;"><?php echo e(fmt_num($enu)); ?></td>
                    <td style="padding:12px 14px; border-top:1px solid <?php echo e($border); ?>;">
                      <?php if ($uuid !== '') { ?>
                        <a class="btn btn--ghost" href="<?php echo e(BASE_URL); ?>/results.php?attempt=<?php echo e(urlencode($uuid)); ?>">Open</a>
                      <?php } else { ?>
                        <span class="help">—</span>
                      <?php } ?>
                    </td>
                  </tr>
                <?php $rank++; } ?>
              </tbody>
            </table>
          </div>

          <div class="help" style="margin-top:10px;">
            Mobile tip: swipe horizontally to see the full table.
          </div>
        <?php } ?>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
