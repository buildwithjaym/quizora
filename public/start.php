<?php
// public/start.php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/quiz_repo.php';
require_once __DIR__ . '/../app/attempt_repo.php';

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

if (!isset($_SESSION['join_quiz_code']) || !isset($_SESSION['join_student_id']) || !isset($_SESSION['join_student_key'])) {
    flash_set('error', 'Please join the quiz first.');
    redirect('/join.php?code=' . urlencode($code));
}

if ((string)$_SESSION['join_quiz_code'] !== $code) {
    flash_set('error', 'Join session mismatch. Please join again.');
    redirect('/join.php?code=' . urlencode($code));
}

$studentId = (int)$_SESSION['join_student_id'];
$studentKey = (string)$_SESSION['join_student_key'];

$res = attempt_create((int)$quiz['id'], $studentId, $studentKey);

if (!$res['ok']) {
    if (isset($res['error']) && (string)$res['error'] === 'already_attempted' && isset($res['attempt']['attempt_uuid'])) {
        flash_set('error', 'One attempt only. You already joined or submitted this quiz.');
        redirect('/results.php?attempt=' . urlencode((string)$res['attempt']['attempt_uuid']));
    }

    flash_set('error', 'Could not start attempt. Please try again.');
    redirect('/join.php?code=' . urlencode($code));
}

$_SESSION['attempt_uuid'] = (string)$res['attempt_uuid'];
$_SESSION['attempt_started_at'] = time();

redirect('/take.php?attempt=' . urlencode((string)$res['attempt_uuid']));
