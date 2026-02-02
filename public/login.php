<?php
// public/login.php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';

auth_require_guest();

$page_title = 'Teacher Login • ' . APP_NAME;

$email = '';
$errors = [];

if (is_post()) {
    require_csrf();

    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $pass  = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($email === '' || mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email.';
    }
    if ($pass === '') {
        $errors[] = 'Password is required.';
    }

    if (count($errors) === 0) {
        $sql = "SELECT id, password_hash FROM users WHERE email = ? LIMIT 1";
        $stmt = mysqli_prepare(db(), $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $id, $hash);

            if (mysqli_stmt_fetch($stmt)) {
                mysqli_stmt_close($stmt);

                if (is_string($hash) && password_verify($pass, $hash)) {
                    auth_login_teacher((int)$id);

                    $up = mysqli_prepare(db(), "UPDATE users SET last_login_at = NOW() WHERE id = ?");
                    if ($up) {
                        mysqli_stmt_bind_param($up, "i", $id);
                        mysqli_stmt_execute($up);
                        mysqli_stmt_close($up);
                    }

                    flash_set('success', 'Welcome back!');
                    redirect('/teacher_dashboard.php');
                } else {
                    $errors[] = 'Incorrect email or password.';
                }
            } else {
                mysqli_stmt_close($stmt);
                $errors[] = 'Incorrect email or password.';
            }
        } else {
            $errors[] = 'Login failed. Please try again.';
        }
    }
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/toast.php';
?>

<div class="card">
  <div class="card__pad" style="max-width:720px; margin:0 auto;">
    <h1 class="card__title">Teacher Login</h1>
    <p class="card__subtitle">Access your quizzes and results.</p>

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

    <form class="form" method="post" action="<?php echo e(BASE_URL); ?>/login.php" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">

      <div class="field">
        <label class="label" for="email">Email</label>
        <input class="input" id="email" name="email" type="email" value="<?php echo e($email); ?>" required>
      </div>

      <div class="field">
        <label class="label" for="password">Password</label>
        <input class="input" id="password" name="password" type="password" required>
      </div>

      <button class="btn btn--primary btn--block" type="submit">Login</button>

      <div class="help" style="text-align:center;">
        New to QUIZORA?
        <a href="<?php echo e(BASE_URL); ?>/register.php" style="color:var(--primary); font-weight:800;">Create an account</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
