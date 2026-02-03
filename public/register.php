<?php
// public/register.php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';

if (teacher_is_logged_in()) {
    header('Location: ./teacher_dashboard.php');
    exit;
}

$page_title = 'Teacher Register • ' . APP_NAME;

$firstName = '';
$lastName  = '';
$email     = '';
$errors    = [];

$flashError = flash_get('error');
$flashSuccess = flash_get('success');

if (is_post()) {
    require_csrf();

    $firstName = isset($_POST['first_name']) ? trim((string)$_POST['first_name']) : '';
    $lastName  = isset($_POST['last_name']) ? trim((string)$_POST['last_name']) : '';
    $email     = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $pass      = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $pass2     = isset($_POST['password_confirm']) ? (string)$_POST['password_confirm'] : '';

    if ($firstName === '' || strlen($firstName) > 80) {
        $errors[] = 'First name is required (max 80 chars).';
    }

    if ($lastName === '' || strlen($lastName) > 80) {
        $errors[] = 'Last name is required (max 80 chars).';
    }

    if ($email === '' || strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email.';
    }

    if ($pass === '' || strlen($pass) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($pass !== $pass2) {
        $errors[] = 'Passwords do not match.';
    }

    if (count($errors) === 0) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare(db(), $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssss", $firstName, $lastName, $email, $hash);
            $ok = mysqli_stmt_execute($stmt);

            if ($ok) {
                $newId = (int)mysqli_insert_id(db());
                mysqli_stmt_close($stmt);

                auth_login_teacher($newId);

                flash_set('success', 'Account created! Welcome to QUIZORA.');
                header('Location: ./teacher_dashboard.php');
                exit;
            } else {
                $errno = (int)mysqli_errno(db());
                mysqli_stmt_close($stmt);

                if ($errno === 1062) {
                    $errors[] = 'That email is already registered. Please log in instead.';
                } else {
                    $errors[] = 'Registration failed. Please try again.';
                }
            }
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($page_title); ?></title>

  <link rel="stylesheet" href="./../assets/css/app.css?v=1">
  <link rel="stylesheet" href="./../assets/css/auth.css?v=1">
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
        <h1 class="auth-title">Register</h1>

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

        <form method="post" action="" autocomplete="off">
          <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">

          <label class="auth-field">
            <span class="auth-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="20" height="20">
                <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4z" fill="none" stroke="currentColor" stroke-width="2"/>
                <path d="M4 20a8 8 0 0 1 16 0" fill="none" stroke="currentColor" stroke-width="2"/>
              </svg>
            </span>
            <input class="auth-input" name="first_name" type="text" placeholder="First Name"
                   value="<?php echo e($firstName); ?>" required autocomplete="given-name">
          </label>

          <label class="auth-field">
            <span class="auth-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="20" height="20">
                <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4z" fill="none" stroke="currentColor" stroke-width="2"/>
                <path d="M4 20a8 8 0 0 1 16 0" fill="none" stroke="currentColor" stroke-width="2"/>
              </svg>
            </span>
            <input class="auth-input" name="last_name" type="text" placeholder="Last Name"
                   value="<?php echo e($lastName); ?>" required autocomplete="family-name">
          </label>

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
                   required autocomplete="new-password">
          </label>

          <label class="auth-field">
            <span class="auth-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="20" height="20">
                <path d="M7 11V8a5 5 0 0 1 10 0v3" fill="none" stroke="currentColor" stroke-width="2" />
                <path d="M6 11h12v10H6z" fill="none" stroke="currentColor" stroke-width="2" />
              </svg>
            </span>
            <input class="auth-input" name="password_confirm" type="password" placeholder="Confirm Password"
                   required autocomplete="new-password">
          </label>

          <button class="auth-btn auth-btn--green" type="submit">Sign Up</button>
        </form>

        <p class="auth-foot">
          Already have an account?
          <a class="auth-link" href="./login.php">Log In</a>
        </p>
      </div>

    </div>
  </div>
</body>
</html>
