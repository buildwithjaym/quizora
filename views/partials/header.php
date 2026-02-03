<?php
// views/partials/header.php

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/auth.php';

$title = (isset($page_title) && is_string($page_title) && $page_title !== '') ? $page_title : APP_NAME;
$flashError = flash_get('error');
$flashSuccess = flash_get('success');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo e($title); ?></title>

  <link rel="stylesheet" href="./../assets/css/app.css" />
  <link rel="icon" href="./../assets/img/remove_logo.png" type="image/png" />

  <style>
    .logo{
      height:40px;width:auto;margin-right:8px;vertical-align:middle;border:none;border-radius:100px;
    }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="container topbar__inner">
      <a class="brand" href="./../public/index.php">
        <img src="./../assets/img/remove_logo.png" alt="Quizora Logo" class="logo" />
        <span class="brand__text"><?php echo e(APP_NAME); ?></span>
      </a>

      <div class="topbar__right">
        <?php if (teacher_is_logged_in()) { ?>
          <a class="btn btn--ghost" href="./../public/teacher_dashboard.php">Dashboard</a>
          <a class="btn btn--ghost" href="./../public/logout.php">Logout</a>
        <?php } else { ?>
          <a class="btn btn--ghost" href="./../public/login.php">Login</a>
          <a class="btn btn--ghost" href="./../public/register.php">Register</a>
        <?php } ?>
      </div>
    </div>
  </header>

  <main class="container page">
    <?php if (is_string($flashError) && $flashError !== '') { ?>
      <div class="alert alert--error"><?php echo e($flashError); ?></div>
    <?php } ?>
    <?php if (is_string($flashSuccess) && $flashSuccess !== '') { ?>
      <div class="alert alert--success"><?php echo e($flashSuccess); ?></div>
    <?php } ?>
