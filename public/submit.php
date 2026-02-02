<?php
// public/submit.php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/attempt_repo.php';

$attemptUuid = isset($_GET['attempt']) ? trim((string)$_GET['attempt']) : '';
if ($attemptUuid === '') {
    flash_set('error', 'Missing attempt.');
    redirect('/index.php');
}

if (!is_post()) {
    redirect('/take.php?attempt=' . urlencode($attemptUuid));
}

require_csrf();

if (!isset($_SESSION['attempt_uuid']) || (string)$_SESSION['attempt_uuid'] !== $attemptUuid) {
    flash_set('error', 'Attempt session mismatch. Please join the quiz again.');
    redirect('/index.php');
}

$timeSeconds = isset($_POST['time_seconds']) ? (int)$_POST['time_seconds'] : 0;
if ($timeSeconds < 0) $timeSeconds = 0;

$res = attempt_score_and_submit($attemptUuid, $_POST, $timeSeconds);

if (!$res['ok']) {
    $err = isset($res['error']) ? (string)$res['error'] : 'unknown';
    if ($err === 'already_submitted') {
        redirect('/results.php?attempt=' . urlencode($attemptUuid));
    }

    flash_set('error', 'Submit failed. Please try again.');
    redirect('/take.php?attempt=' . urlencode($attemptUuid));
}

$_SESSION['toast_title'] = 'Submitted!';
$_SESSION['toast_msg'] = isset($res['compliment']) ? (string)$res['compliment'] : 'Nice work!';

redirect('/results.php?attempt=' . urlencode($attemptUuid));
