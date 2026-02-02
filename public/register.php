<?php
// public/register.php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';

auth_require_guest();

$page_title = 'Teacher Register • ' . APP_NAME;

$fullName = '';
$email = '';
$errors = [];

if (is_post()) {
    require_csrf();

    $fullName = isset($_POST['full_name']) ? trim((string)$_POST['full_name']) : '';
    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $pass = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $pass2 = isset($_POST['password_confirm']) ? (string)$_POST['password_confirm'] : '';

    if ($fullName === '' || mb_strlen($fullName) > 120) {
        $errors[] = 'Full name is required (max 120 chars).';
    }

    if ($email === '' || mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email.';
    }

    if ($pass === '' || mb_strlen($pass) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($pass !== $pass2) {
        $errors[] = 'Passwords do not match.';
    }

    if (count($errors) === 0) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare(db(), $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sss", $fullName, $email, $hash);
            $ok = mysqli_stmt_execute($stmt);

            if ($ok) {
                $newId = (int)mysqli_insert_id(db());
                mysqli_stmt_close($stmt);

                auth_login_teacher($newId);

                flash_set('success', 'Account created! Welcome to QUIZORA.');
                redirect('/teacher_dashboard.php');
            } else {
                $errno = (int)mysqli_errno(db());
                mysqli_stmt_close($stmt);

                if ($errno === 1062) {
                    $errors[] = 'That email is already registered. Please login instead.';
                } else {
                    $errors[] = 'Registration failed. Please try again.';
                }
            }
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
    <p class="card__subtitle">Create your teacher account to build and publish quizzes.</p>

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

      <div class="field">
        <label class="label" for="full_name">Full Name</label>
        <input class="input" id="full_name" name="full_name" value="<?php echo e($fullName); ?>" placeholder="e.g., Juan Dela Cruz" required>
      </div>

      <div class="field">
        <label class="label" for="email">Email</label>
        <input class="input" id="email" name="email" type="email" value="<?php echo e($email); ?>" required>
      </div>

      <div class="grid" style="grid-template-columns:1fr 1fr; gap:14px;">
        <div class="field">
          <label class="label" for="password">Password</label>
          <input class="input" id="password" name="password" type="password" required>
          <div class="help">At least 8 characters.</div>
        </div>

        <div class="field">
          <label class="label" for="password_confirm">Confirm Password</label>
          <input class="input" id="password_confirm" name="password_confirm" type="password" required>
        </div>
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
