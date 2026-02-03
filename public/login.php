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

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email.';
    }
    if ($pass === '') {
        $errors[] = 'Password is required.';
    }

    if (count($errors) === 0) {
        $user = auth_find_teacher_by_email($email);

        $ok = false;
        if (is_array($user) && isset($user['id']) && isset($user['password_hash'])) {
            $hash = (string)$user['password_hash'];
            if ($hash !== '' && password_verify($pass, $hash)) {
                $ok = true;

                auth_login_teacher((int)$user['id']);

                $up = mysqli_prepare(db(), "UPDATE users SET last_login_at = NOW() WHERE id = ? LIMIT 1");
                if ($up) {
                    $uid = (int)$user['id'];
                    mysqli_stmt_bind_param($up, "i", $uid);
                    mysqli_stmt_execute($up);
                    mysqli_stmt_close($up);
                }

                flash_set('success', 'Welcome back!');
                redirect('/teacher_dashboard.php');
            }
        }

        if (!$ok) {
            $errors[] = 'Email or password is incorrect. Try again.';
        }
    }
}

$flashError   = flash_get('error');
$flashSuccess = flash_get('success');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($page_title); ?></title>

  <link rel="stylesheet" href="./../assets/css/app.css?v=2">
  <link rel="stylesheet" href="./../assets/css/auth.css?v=2">
  <link rel="icon" href="./../assets/img/remove_logo.png" type="image/png">
</head>
<body class="auth-page">
  <div class="auth-shell">
    <div class="auth-card">

      <div class="auth-brand">
        <img class="auth-logo" src="./../assets/img/logo.png" alt="Quizora Logo">
        <div class="auth-brand-name">Quizora</div>
      </div>

      <div class="auth-panel">
        <h1 class="auth-title">Log In</h1>

        <?php if (is_string($flashError) && $flashError !== '') { ?>
          <div class="auth-alert auth-alert--danger"><?php echo e($flashError); ?></div>
        <?php } ?>

        <?php if (is_string($flashSuccess) && $flashSuccess !== '') { ?>
          <div class="auth-alert auth-alert--success"><?php echo e($flashSuccess); ?></div>
        <?php } ?>

        <?php if (count($errors) > 0) { ?>
          <div class="auth-alert auth-alert--danger">
            <ul style="margin:0; padding-left:18px;">
              <?php foreach ($errors as $err) { ?>
                <li><?php echo e($err); ?></li>
              <?php } ?>
            </ul>
          </div>
        <?php } ?>

        <form method="post" action="login.php" autocomplete="off">
          <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">

          <label class="auth-field">
            <span class="auth-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="20" height="20">
                <path d="M4 6h16v12H4z" fill="none" stroke="currentColor" stroke-width="2" />
                <path d="M4 7l8 6 8-6" fill="none" stroke="currentColor" stroke-width="2" />
              </svg>
            </span>
            <input class="auth-input" name="email" type="email" placeholder="Email"
                   value="<?php echo e($email); ?>" required autocomplete="email">
          </label>

          <label class="auth-field">
            <span class="auth-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="20" height="20">
                <path d="M7 11V8a5 5 0 0 1 10 0v3" fill="none" stroke="currentColor" stroke-width="2" />
                <path d="M6 11h12v10H6z" fill="none" stroke="currentColor" stroke-width="2" />
              </svg>
            </span>
            <input class="auth-input" name="password" type="password" placeholder="Password"
                   required autocomplete="current-password">
          </label>

          <button class="auth-btn auth-btn--blue" type="submit">Log In</button>
        </form>

        <p class="auth-foot">
          Don't have an account?
          <a class="auth-link auth-link--accent" href="register.php">Sign Up</a>
        </p>
      </div>

    </div>
  </div>
</body>
</html>
