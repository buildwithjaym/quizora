<?php
// public/results.php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/attempt_repo.php';

$attemptUuid = isset($_GET['attempt']) ? trim((string)$_GET['attempt']) : '';
if ($attemptUuid === '') {
    flash_set('error', 'Missing attempt.');
    redirect('/index.php');
}

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

$toastTitle = isset($_SESSION['toast_title']) ? (string)$_SESSION['toast_title'] : '';
$toastMsg = isset($_SESSION['toast_msg']) ? (string)$_SESSION['toast_msg'] : '';
unset($_SESSION['toast_title']);
unset($_SESSION['toast_msg']);

$page_title = 'Results • ' . APP_NAME;

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/toast.php';

function safe_type_score($arr, $key)
{
    if (!is_array($arr) || !array_key_exists($key, $arr)) return 0.0;
    return (float)$arr[$key];
}

function fmt_num($n)
{
    $n = (float)$n;
    if (floor($n) == $n) return (string)((int)$n);
    return number_format($n, 2, '.', '');
}

$compliment = isset($attempt['compliment']) ? (string)$attempt['compliment'] : 'Nice work!';
$timeSeconds = isset($attempt['time_seconds']) ? (int)$attempt['time_seconds'] : 0;
$mm = (int)floor($timeSeconds / 60);
$ss = (int)($timeSeconds % 60);
$timeFmt = str_pad((string)$mm, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string)$ss, 2, '0', STR_PAD_LEFT);

$code = isset($attempt['quiz_code']) ? (string)$attempt['quiz_code'] : '';
?>

<?php if ($toastTitle !== '' && $toastMsg !== '') { ?>
  <div data-toast-title="<?php echo e($toastTitle); ?>" data-toast-msg="<?php echo e($toastMsg); ?>"></div>
<?php } ?>

<section class="card">
  <div class="card__pad">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
      <div>
        <div class="help" style="margin-bottom:6px;">
          <span class="kbd"><?php echo e($attempt['full_name']); ?></span>
          <span class="muted">•</span>
          <span class="muted"><?php echo e($attempt['quiz_subject']); ?></span>
        </div>
        <h1 class="card__title" style="margin:0; font-size:24px;">Results</h1>
        <p class="card__subtitle" style="margin-top:6px;">
          <?php echo e($attempt['quiz_title']); ?>
        </p>
      </div>

      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <?php if ($code !== '') { ?>
          <a class="btn btn--success" href="<?php echo e(BASE_URL); ?>/leaderboard.php?code=<?php echo e($code); ?>">Leaderboard</a>
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
              <div style="font-weight:900;"><?php echo e($timeFmt); ?></div>
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
            Note: For Matching, points are computed per correct pair. For Enumeration, partial credit depends on the quiz setting.
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
