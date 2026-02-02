<?php
// app/quiz_repo.php

require_once __DIR__ . '/bootstrap.php';

function quiz_teacher_owns($quiz_id, $teacher_id)
{
    $quiz_id = (int)$quiz_id;
    $teacher_id = (int)$teacher_id;

    $sql = "SELECT id FROM quizzes WHERE id = ? AND teacher_id = ? LIMIT 1";
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "ii", $quiz_id, $teacher_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $id);

    $ok = mysqli_stmt_fetch($stmt) ? true : false;
    mysqli_stmt_close($stmt);

    return $ok;
}

function quiz_get($quiz_id, $teacher_id)
{
    $quiz_id = (int)$quiz_id;
    $teacher_id = (int)$teacher_id;

    $sql = "SELECT id, teacher_id, title, subject, time_limit_minutes, total_questions, total_points, quiz_code, status, created_at, updated_at
            FROM quizzes
            WHERE id = ? AND teacher_id = ?
            LIMIT 1";
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "ii", $quiz_id, $teacher_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $row = null;
    if ($res) {
        $row = mysqli_fetch_assoc($res);
    }

    mysqli_stmt_close($stmt);
    return $row ? $row : null;
}

function quiz_list_questions($quiz_id, $teacher_id)
{
    $quiz_id = (int)$quiz_id;
    $teacher_id = (int)$teacher_id;

    $sql = "SELECT q.id, q.quiz_id, q.type, q.prompt, q.points, q.position, q.choices_json, q.answer, q.settings_json
            FROM questions q
            INNER JOIN quizzes z ON z.id = q.quiz_id
            WHERE q.quiz_id = ? AND z.teacher_id = ?
            ORDER BY q.position ASC, q.id ASC";
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, "ii", $quiz_id, $teacher_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $items = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $items[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
    return $items;
}

function quiz_next_position($quiz_id)
{
    $quiz_id = (int)$quiz_id;

    $sql = "SELECT IFNULL(MAX(position), 0) AS mx FROM questions WHERE quiz_id = ?";
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        return 1;
    }

    mysqli_stmt_bind_param($stmt, "i", $quiz_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $mx = 0;
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        if ($row && isset($row['mx'])) {
            $mx = (int)$row['mx'];
        }
    }

    mysqli_stmt_close($stmt);
    return $mx + 1;
}

function quiz_json_value($value, $fallback)
{
    if (is_string($value)) {
        $v = trim($value);
        if ($v !== '') {
            return $v;
        }
    }

    $enc = json_encode($value, JSON_UNESCAPED_UNICODE);
    if (is_string($enc) && $enc !== '' && json_last_error() === JSON_ERROR_NONE) {
        return $enc;
    }

    return $fallback;
}

function quiz_add_question($quiz_id, $teacher_id, $type, $prompt, $choices, $answer, $settings, $points)
{
    $quiz_id = (int)$quiz_id;
    $teacher_id = (int)$teacher_id;

    if (!quiz_teacher_owns($quiz_id, $teacher_id)) {
        return ['ok' => false, 'error' => 'not_allowed'];
    }

    $type = (string)$type;
    $prompt = trim((string)$prompt);
    $answer = is_null($answer) ? null : (string)$answer;

    $points = (float)$points;
    if ($points <= 0) {
        $points = 1.0;
    }

    $pos = quiz_next_position($quiz_id);

    $choices_json = is_null($choices) ? null : quiz_json_value($choices, '[]');
    $settings_json = is_null($settings) ? null : quiz_json_value($settings, '{}');

    $sql = "INSERT INTO questions (quiz_id, type, prompt, points, position, choices_json, answer, settings_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => 'db_prepare'];
    }

    mysqli_stmt_bind_param(
        $stmt,
        "issdisss",
        $quiz_id,
        $type,
        $prompt,
        $points,
        $pos,
        $choices_json,
        $answer,
        $settings_json
    );

    $ok = mysqli_stmt_execute($stmt);
    $newId = $ok ? (int)mysqli_insert_id(db()) : 0;

    mysqli_stmt_close($stmt);

    if (!$ok) {
        return ['ok' => false, 'error' => 'db_execute'];
    }

    quiz_recompute_totals($quiz_id);

    return ['ok' => true, 'id' => $newId];
}

function quiz_delete_question($quiz_id, $teacher_id, $question_id)
{
    $quiz_id = (int)$quiz_id;
    $teacher_id = (int)$teacher_id;
    $question_id = (int)$question_id;

    if (!quiz_teacher_owns($quiz_id, $teacher_id)) {
        return false;
    }

    $sql = "DELETE q FROM questions q
            INNER JOIN quizzes z ON z.id = q.quiz_id
            WHERE q.id = ? AND q.quiz_id = ? AND z.teacher_id = ?";
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "iii", $question_id, $quiz_id, $teacher_id);
    $ok = mysqli_stmt_execute($stmt) ? true : false;
    mysqli_stmt_close($stmt);

    if ($ok) {
        quiz_recompute_totals($quiz_id);
    }

    return $ok;
}

function quiz_recompute_totals($quiz_id)
{
    $quiz_id = (int)$quiz_id;

    $sql = "SELECT COUNT(*) AS c, IFNULL(SUM(points), 0) AS p
            FROM questions
            WHERE quiz_id = ?";
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "i", $quiz_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $c = 0;
    $p = 0.0;

    if ($res) {
        $row = mysqli_fetch_assoc($res);
        if ($row) {
            $c = isset($row['c']) ? (int)$row['c'] : 0;
            $p = isset($row['p']) ? (float)$row['p'] : 0.0;
        }
    }

    mysqli_stmt_close($stmt);

    $up = mysqli_prepare(db(), "UPDATE quizzes SET total_questions = ?, total_points = ? WHERE id = ?");
    if (!$up) {
        return false;
    }

    mysqli_stmt_bind_param($up, "idi", $c, $p, $quiz_id);
    $ok = mysqli_stmt_execute($up) ? true : false;
    mysqli_stmt_close($up);

    return $ok;
}

function quiz_generate_code()
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $len = (int)QUIZ_CODE_LEN;
    if ($len < 4) {
        $len = 6;
    }

    $out = '';
    $i = 0;
    while ($i < $len) {
        $idx = random_int(0, strlen($alphabet) - 1);
        $out .= $alphabet[$idx];
        $i++;
    }

    return $out;
}

function quiz_code_exists($code)
{
    $code = strtoupper(trim((string)$code));
    if ($code === '') {
        return true;
    }

    $sql = "SELECT id FROM quizzes WHERE quiz_code = ? LIMIT 1";
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        return true;
    }

    mysqli_stmt_bind_param($stmt, "s", $code);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $id);

    $exists = mysqli_stmt_fetch($stmt) ? true : false;
    mysqli_stmt_close($stmt);

    return $exists;
}

function quiz_publish($quiz_id, $teacher_id)
{
    $quiz_id = (int)$quiz_id;
    $teacher_id = (int)$teacher_id;

    if (!quiz_teacher_owns($quiz_id, $teacher_id)) {
        return ['ok' => false, 'error' => 'not_allowed'];
    }

    quiz_recompute_totals($quiz_id);

    $quiz = quiz_get($quiz_id, $teacher_id);
    if (!$quiz) {
        return ['ok' => false, 'error' => 'not_found'];
    }

    $tq = isset($quiz['total_questions']) ? (int)$quiz['total_questions'] : 0;
    if ($tq <= 0) {
        return ['ok' => false, 'error' => 'no_questions'];
    }

    $status = isset($quiz['status']) ? (string)$quiz['status'] : 'draft';
    if ($status === 'published' && isset($quiz['quiz_code']) && (string)$quiz['quiz_code'] !== '') {
        return ['ok' => true, 'code' => (string)$quiz['quiz_code']];
    }

    $tries = 0;
    $code = '';
    while ($tries < 20) {
        $candidate = quiz_generate_code();
        if (!quiz_code_exists($candidate)) {
            $code = $candidate;
            break;
        }
        $tries++;
    }

    if ($code === '') {
        return ['ok' => false, 'error' => 'code_gen_failed'];
    }

    $sql = "UPDATE quizzes
            SET quiz_code = ?, status = 'published', published_at = NOW()
            WHERE id = ? AND teacher_id = ?";
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => 'db_prepare'];
    }

    mysqli_stmt_bind_param($stmt, "sii", $code, $quiz_id, $teacher_id);
    $ok = mysqli_stmt_execute($stmt) ? true : false;
    mysqli_stmt_close($stmt);

    if (!$ok) {
        return ['ok' => false, 'error' => 'db_execute'];
    }

    return ['ok' => true, 'code' => $code];
}

function quiz_get_published_by_code($code)
{
    $code = strtoupper(trim((string)$code));
    if ($code === '' || strlen($code) !== (int)QUIZ_CODE_LEN) {
        return null;
    }

    $sql = "SELECT id, teacher_id, title, subject, time_limit_minutes, total_questions, total_points, quiz_code, status
            FROM quizzes
            WHERE quiz_code = ? AND status = 'published'
            LIMIT 1";
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "s", $code);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $row = null;
    if ($res) {
        $row = mysqli_fetch_assoc($res);
    }

    mysqli_stmt_close($stmt);
    return $row ? $row : null;
}
