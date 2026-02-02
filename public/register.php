<?php
// public/register.php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';

auth_require_guest();

$page_title = 'Teacher Register • ' . APP_NAME;

$first = '';
$last = '';
$email = '';
$errors = [];

if (is_post()) {
    require_csrf();

    $first = isset($_POST['first_name']) ? trim((string)$_POST['first_name']) : '';
    $last  = isset($_POST['last_name']) ? trim((string)$_POST['last_name']) : '';
    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $pass  = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($first === '' || mb_strlen($first) > 80) {
        $errors[] = 'First name is required (max 80 chars).';
    }
    if ($last === '' || mb_strlen($last) > 80) {
        $errors[] = 'Last name is required (max 80 chars).';
    }
    if ($email === '' || mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if ($pass === '' || mb_strlen($pass) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (count($errors) === 0) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare(db(), $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssss", $first, $last, $email, $hash);
            $ok = mysqli_stmt_execute($stmt);

            if ($ok) {
                $newId = mysqli_insert_id(db());
                auth_login_teacher($newId);
                flash_set('success', 'Welcome! Your teacher account is ready.');
                redirect('/teacher_dashboard.php');
            } else {
                $errno = mysqli_errno(db());
                if ($errno === 1062) {
                    $errors[] = 'That email is already registered.';
                } else {
                    $errors[] = 'Registration failed. Please try again.';
                }
            }

            mysqli_stmt_close($stmt);
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/toast.php';
?>

<div class="card">
  <div class="card__pad" style="max-width:720px; margin:0 auto;">
    <h1 class="card__title">Teacher Register</h1>
    <p class="card__subtitle">Create your QUIZORA teacher account.</p>

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

    <form class="form" method="post" action="<?php echo e(BASE_URL); ?>/register.php" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">

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

      <div class="field">
        <label class="label" for="email">Email</label>
        <input class="input" id="email" name="email" type="email" value="<?php echo e($email); ?>" required>
      </div>

      <div class="field">
        <label class="label" for="password">Password</label>
        <input class="input" id="password" name="password" type="password" minlength="8" required>
        <div class="help">Use at least 8 characters. You can change this later (future feature).</div>
      </div>

      <button class="btn btn--primary btn--block" type="submit">Create Account</button>

      <div class="help" style="text-align:center;">
        Already have an account?
        <a href="<?php echo e(BASE_URL); ?>/login.php" style="color:var(--primary); font-weight:800;">Login</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
