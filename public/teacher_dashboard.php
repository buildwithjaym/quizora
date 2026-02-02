<?php
// public/teacher_dashboard.php

require_once __DIR__ . '/../app/auth.php';

require_teacher();

$page_title = 'Dashboard • ' . APP_NAME;

$tid = teacher_id();

$quizzes = [];
$sql = "SELECT id, title, subject, time_limit_minutes, total_questions, total_points, quiz_code, status, updated_at
        FROM quizzes
        WHERE teacher_id = ?
        ORDER BY updated_at DESC, id DESC";
$stmt = mysqli_prepare(db(), $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $tid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $quizzes[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/toast.php';
?>

<div class="grid" style="grid-template-columns: 1fr; gap:18px;">
  <section class="card">
    <div class="card__pad" style="display:flex; align-items:flex-start; justify-content:space-between; gap:14px; flex-wrap:wrap;">
      <div>
        <h1 class="card__title" style="margin-bottom:6px;">Teacher Dashboard</h1>
        <p class="card__subtitle" style="margin-bottom:0;">Create quizzes, publish codes, and view results.</p>
      </div>
      <a class="btn btn--primary" href="<?php echo e(BASE_URL); ?>/quiz_create.php">+ Create New Quiz</a>
    </div>
  </section>

  <section class="card">
    <div class="card__pad">
      <h2 style="margin:0 0 10px 0; font-size:18px;">Your Quizzes</h2>

      <?php if (count($quizzes) === 0) { ?>
        <div class="alert">
          <div style="font-weight:900; margin-bottom:4px;">No quizzes yet.</div>
          <div class="help">Click “Create New Quiz” to start building your first quiz.</div>
        </div>
      <?php } else { ?>
        <div style="overflow:auto; border:1px solid var(--border); border-radius:16px;">
          <table style="width:100%; border-collapse:separate; border-spacing:0; min-width:860px; background:#fff;">
            <thead>
              <tr style="background:rgba(17,24,39,.03);">
                <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Title</th>
                <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Subject</th>
                <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Duration</th>
                <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Questions</th>
                <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Points</th>
                <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Status</th>
                <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Code</th>
                <th style="text-align:left; padding:12px 14px; font-size:12px; color:var(--muted);">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($quizzes as $q) { ?>
                <?php
                  $status = isset($q['status']) ? (string)$q['status'] : 'draft';
                  $code = isset($q['quiz_code']) ? (string)$q['quiz_code'] : '';
                  $qid = isset($q['id']) ? (int)$q['id'] : 0;

                  $badgeBg = 'rgba(17,24,39,.06)';
                  $badgeBorder = 'rgba(17,24,39,.12)';
                  $badgeText = 'var(--muted)';

                  if ($status === 'published') {
                      $badgeBg = 'rgba(16,185,129,.10)';
                      $badgeBorder = 'rgba(16,185,129,.22)';
                      $badgeText = 'var(--success)';
                  } elseif ($status === 'archived') {
                      $badgeBg = 'rgba(224,36,36,.08)';
                      $badgeBorder = 'rgba(224,36,36,.18)';
                      $badgeText = 'var(--error)';
                  }
                ?>
                <tr>
                  <td style="padding:12px 14px; border-top:1px solid var(--border);">
                    <div style="font-weight:900;"><?php echo e($q['title']); ?></div>
                    <div class="help">Updated: <?php echo e($q['updated_at']); ?></div>
                  </td>
                  <td style="padding:12px 14px; border-top:1px solid var(--border);"><?php echo e($q['subject']); ?></td>
                  <td style="padding:12px 14px; border-top:1px solid var(--border);"><?php echo e($q['time_limit_minutes']); ?> min</td>
                  <td style="padding:12px 14px; border-top:1px solid var(--border);"><?php echo e($q['total_questions']); ?></td>
                  <td style="padding:12px 14px; border-top:1px solid var(--border);"><?php echo e($q['total_points']); ?></td>
                  <td style="padding:12px 14px; border-top:1px solid var(--border);">
                    <span style="display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-weight:900; font-size:12px; background:<?php echo e($badgeBg); ?>; border:1px solid <?php echo e($badgeBorder); ?>; color:<?php echo e($badgeText); ?>;">
                      <?php echo e(ucfirst($status)); ?>
                    </span>
                  </td>
                  <td style="padding:12px 14px; border-top:1px solid var(--border);">
                    <?php if ($status === 'published' && $code !== '') { ?>
                      <span class="kbd"><?php echo e($code); ?></span>
                    <?php } else { ?>
                      <span class="help">—</span>
                    <?php } ?>
                  </td>
                  <td style="padding:12px 14px; border-top:1px solid var(--border);">
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                      <a class="btn btn--ghost" href="<?php echo e(BASE_URL); ?>/quiz_builder.php?quiz_id=<?php echo e($qid); ?>">Edit</a>
                      <a class="btn btn--ghost" href="<?php echo e(BASE_URL); ?>/quiz_preview.php?quiz_id=<?php echo e($qid); ?>">Preview</a>
                      <a class="btn btn--primary" href="<?php echo e(BASE_URL); ?>/quiz_publish.php?quiz_id=<?php echo e($qid); ?>">Publish</a>
                      <?php if ($status === 'published' && $code !== '') { ?>
                        <a class="btn btn--success" href="<?php echo e(BASE_URL); ?>/leaderboard.php?code=<?php echo e($code); ?>">Leaderboard</a>
                      <?php } ?>
                    </div>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>

        <div class="help" style="margin-top:10px;">
          Mobile tip: swipe horizontally to see the full table.
        </div>
      <?php } ?>
    </div>
  </section>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
