<?php
// public/leaderboard.php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/quiz_repo.php';
require_once __DIR__ . '/../app/attempt_repo.php';

$page_title = 'Leaderboard • ' . APP_NAME;

$code = isset($_GET['code']) ? strtoupper(trim((string)$_GET['code'])) : '';
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

$rows = leaderboard_for_quiz($quizId, 500);
$totalAttempts = count($rows);

function lb_fetch_scores($quiz_id)
{
    $quiz_id = (int)$quiz_id;

    $sql = "SELECT total_score FROM attempts WHERE quiz_id = ? AND submitted = 1";
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, "i", $quiz_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $scores = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            if ($r && isset($r['total_score'])) {
                $scores[] = (float)$r['total_score'];
            }
        }
    }

    mysqli_stmt_close($stmt);
    return $scores;
}

function lb_bucket_label($i)
{
    if ($i === 9) {
        return '90–100%';
    }
    $a = $i * 10;
    $b = ($i * 10) + 9;
    return $a . '–' . $b . '%';
}

function lb_build_distribution($scores, $maxPoints)
{
    $buckets = [];
    for ($i = 0; $i < 10; $i++) {
        $buckets[$i] = 0;
    }

    if ($maxPoints <= 0) {
        return $buckets;
    }

    foreach ($scores as $s) {
        $pct = (int)floor(($s / $maxPoints) * 100);
        if ($pct < 0) $pct = 0;
        if ($pct > 100) $pct = 100;

        $idx = (int)floor($pct / 10);
        if ($idx < 0) $idx = 0;
        if ($idx > 9) $idx = 9;

        $buckets[$idx] = (int)$buckets[$idx] + 1;
    }

    return $buckets;
}

$scores = lb_fetch_scores($quizId);
$dist = lb_build_distribution($scores, $maxPoints);

$maxCount = 1;
foreach ($dist as $c) {
    if ((int)$c > $maxCount) {
        $maxCount = (int)$c;
    }
}

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
        <h1 class="card__title" style="margin:0; font-size:24px;">Leaderboard</h1>
        <p class="card__subtitle" style="margin-top:6px;"><?php echo e($quiz['title']); ?></p>
      </div>

      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn btn--ghost" href="<?php echo e(BASE_URL); ?>/index.php">Home</a>
      </div>
    </div>

    <div class="hr"></div>

    <div class="pills" style="margin-bottom:12px;">
      <span class="pill">Attempts: <strong style="color:var(--text);"><?php echo e((string)$totalAttempts); ?></strong></span>
      <span class="pill">Tie-breaker: <strong style="color:var(--text);">Time ASC</strong></span>
      <span class="pill">Top 5 highlighted</span>
    </div>

    <div class="grid" style="grid-template-columns:1fr; gap:18px;">
      <div class="card" style="box-shadow:none;">
        <div class="card__pad">
          <h2 style="margin:0 0 10px 0; font-size:18px;">Rankings</h2>

          <?php if ($totalAttempts === 0) { ?>
            <div class="alert">
              <div style="font-weight:900; margin-bottom:4px;">No submissions yet.</div>
              <div class="help">Once students submit, rankings will appear here.</div>
            </div>
          <?php } else { ?>
            <div style="overflow:auto; border:1px solid var(--border); border-radius:16px;">
              <table style="width:100%; border-collapse:separate; border-spacing:0; min-width:760px; background:#fff;">
                <thead>
                  <tr style="background:rgba(17,24,39,.03);">
                    <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Rank</th>
                    <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Student</th>
                    <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Score</th>
                    <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Time</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                    $rank = 1;
                    foreach ($rows as $r) {
                        $name = isset($r['full_name']) ? (string)$r['full_name'] : '';
                        $score = isset($r['total_score']) ? (float)$r['total_score'] : 0.0;
                        $time = isset($r['time_seconds']) ? (int)$r['time_seconds'] : 0;

                        $mm = (int)floor($time / 60);
                        $ss = (int)($time % 60);
                        $timeFmt = str_pad((string)$mm, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string)$ss, 2, '0', STR_PAD_LEFT);

                        $isTop = $rank <= 5;
                        $bg = $isTop ? 'rgba(245,158,11,.10)' : '#fff';
                        $border = $isTop ? 'rgba(245,158,11,.22)' : 'var(--border)';
                        $rankColor = $isTop ? 'var(--accent)' : 'var(--muted)';
                  ?>
                    <tr style="background:<?php echo e($bg); ?>;">
                      <td style="padding:12px 14px; border-top:1px solid <?php echo e($border); ?>;">
                        <span style="font-weight:900; color:<?php echo e($rankColor); ?>;">#<?php echo e((string)$rank); ?></span>
                      </td>
                      <td style="padding:12px 14px; border-top:1px solid <?php echo e($border); ?>;">
                        <div style="font-weight:900;"><?php echo e($name); ?></div>
                        <?php if ($isTop) { ?>
                          <div class="help">Top <?php echo e((string)$rank); ?></div>
                        <?php } ?>
                      </td>
                      <td style="padding:12px 14px; border-top:1px solid <?php echo e($border); ?>;">
                        <span class="kbd"><?php echo e((string)$score); ?></span>
                        <span class="muted">/</span>
                        <span class="muted"><?php echo e((string)$maxPoints); ?></span>
                      </td>
                      <td style="padding:12px 14px; border-top:1px solid <?php echo e($border); ?>;">
                        <span class="kbd"><?php echo e($timeFmt); ?></span>
                      </td>
                    </tr>
                  <?php
                        $rank++;
                    }
                  ?>
                </tbody>
              </table>
            </div>

            <div class="help" style="margin-top:10px;">
              Mobile tip: swipe horizontally to see the full table.
            </div>
          <?php } ?>
        </div>
      </div>

      <div class="card" style="box-shadow:none;">
        <div class="card__pad">
          <h2 style="margin:0 0 10px 0; font-size:18px;">Score Distribution</h2>
          <div class="help" style="margin:0 0 12px 0;">
            Shows how scores are spread across percentage ranges.
          </div>

          <div style="overflow:auto; border:1px solid var(--border); border-radius:16px; background:#fff;">
            <div style="min-width:860px; padding:14px;">
              <?php for ($i = 0; $i < 10; $i++) { ?>
                <?php
                  $count = (int)$dist[$i];
                  $pctWidth = (int)floor(($count / $maxCount) * 100);
                  $label = lb_bucket_label($i);
                ?>
                <div style="display:grid; grid-template-columns:120px 1fr 70px; gap:12px; align-items:center; margin-bottom:10px;">
                  <div class="muted" style="font-weight:900;"><?php echo e($label); ?></div>
                  <div style="height:14px; background:rgba(17,24,39,.08); border-radius:999px; overflow:hidden;">
                    <div style="height:100%; width:<?php echo e((string)$pctWidth); ?>%; background:var(--primary); border-radius:999px;"></div>
                  </div>
                  <div style="text-align:right; font-weight:900;"><?php echo e((string)$count); ?></div>
                </div>
              <?php } ?>
            </div>
          </div>

          <div class="help" style="margin-top:10px;">
            Mobile tip: swipe to scroll the chart.
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
