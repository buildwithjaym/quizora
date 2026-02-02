<?php
// app/attempt_repo.php

require_once __DIR__ . '/bootstrap.php';

function student_normalize_name($first, $last)
{
    $first = trim((string)$first);
    $last  = trim((string)$last);

    $full = trim($first . ' ' . $last);
    $full = preg_replace('/\s+/', ' ', $full);

    $key = mb_strtolower($full, 'UTF-8');

    return ['first' => $first, 'last' => $last, 'full' => $full, 'key' => $key];
}

function student_get_or_create($first, $last)
{
    $n = student_normalize_name($first, $last);
    if ($n['full'] === '' || $n['key'] === '') {
        return null;
    }

    $sql = "SELECT id FROM students WHERE name_key = ? AND full_name = ? LIMIT 1";
    $stmt = mysqli_prepare(db(), $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $n['key'], $n['full']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $id);
        if (mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);
            return (int)$id;
        }
        mysqli_stmt_close($stmt);
    }

    $ins = mysqli_prepare(db(), "INSERT INTO students (first_name, last_name, full_name, name_key) VALUES (?, ?, ?, ?)");
    if (!$ins) {
        return null;
    }

    mysqli_stmt_bind_param($ins, "ssss", $n['first'], $n['last'], $n['full'], $n['key']);
    $ok = mysqli_stmt_execute($ins);
    mysqli_stmt_close($ins);

    if (!$ok) {
        return null;
    }

    return (int)mysqli_insert_id(db());
}

function attempt_uuid_v4()
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    $hex = bin2hex($data);

    return substr($hex, 0, 8) . '-' .
        substr($hex, 8, 4) . '-' .
        substr($hex, 12, 4) . '-' .
        substr($hex, 16, 4) . '-' .
        substr($hex, 20, 12);
}

function attempt_find_existing($quiz_id, $student_id, $student_name_key)
{
    $quiz_id = (int)$quiz_id;
    $student_id = (int)$student_id;
    $student_name_key = (string)$student_name_key;

    $sql = "SELECT id, attempt_uuid, submitted
            FROM attempts
            WHERE quiz_id = ? AND (student_id = ? OR student_name_key = ?)
            LIMIT 1";
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "iis", $quiz_id, $student_id, $student_name_key);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $row = null;
    if ($res) {
        $row = mysqli_fetch_assoc($res);
    }

    mysqli_stmt_close($stmt);
    return $row ? $row : null;
}

function attempt_create($quiz_id, $student_id, $student_name_key)
{
    $quiz_id = (int)$quiz_id;
    $student_id = (int)$student_id;
    $student_name_key = (string)$student_name_key;

    $existing = attempt_find_existing($quiz_id, $student_id, $student_name_key);
    if ($existing) {
        return ['ok' => false, 'error' => 'already_attempted', 'attempt' => $existing];
    }

    $uuid = attempt_uuid_v4();

    $sql = "INSERT INTO attempts (attempt_uuid, quiz_id, student_id, student_name_key, total_score, time_seconds, submitted)
            VALUES (?, ?, ?, ?, 0.00, 0, 0)";
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => 'db_prepare'];
    }

    mysqli_stmt_bind_param($stmt, "siis", $uuid, $quiz_id, $student_id, $student_name_key);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        return ['ok' => false, 'error' => 'db_execute'];
    }

    return ['ok' => true, 'attempt_uuid' => $uuid, 'attempt_id' => (int)mysqli_insert_id(db())];
}

function attempt_get_by_uuid($attempt_uuid)
{
    $attempt_uuid = trim((string)$attempt_uuid);
    if ($attempt_uuid === '') {
        return null;
    }

    $sql = "SELECT a.id AS attempt_id, a.attempt_uuid, a.quiz_id, a.student_id, a.student_name_key,
                   a.total_score, a.scores_json, a.time_seconds, a.submitted, a.submitted_at, a.compliment,
                   s.first_name, s.last_name, s.full_name,
                   z.title AS quiz_title, z.subject AS quiz_subject, z.time_limit_minutes, z.total_points, z.quiz_code, z.status
            FROM attempts a
            INNER JOIN students s ON s.id = a.student_id
            INNER JOIN quizzes z ON z.id = a.quiz_id
            WHERE a.attempt_uuid = ?
            LIMIT 1";
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "s", $attempt_uuid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $row = null;
    if ($res) {
        $row = mysqli_fetch_assoc($res);
    }

    mysqli_stmt_close($stmt);
    return $row ? $row : null;
}

function repo_json_decode($raw, $fallback)
{
    if (!is_string($raw)) {
        return $fallback;
    }
    $raw = trim($raw);
    if ($raw === '') {
        return $fallback;
    }
    $val = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $fallback;
    }
    return $val;
}

function repo_json_encode($value, $fallback)
{
    $enc = json_encode($value, JSON_UNESCAPED_UNICODE);
    if (!is_string($enc) || $enc === '' || json_last_error() !== JSON_ERROR_NONE) {
        return $fallback;
    }
    return $enc;
}

function attempt_choose_compliment($score_pct)
{
    $score_pct = (int)$score_pct;

    $great = [
        "Legendary work!",
        "That was impressive!",
        "Quiz master vibes!",
        "Top-tier performance!",
        "Excellent job!"
    ];
    $good = [
        "Nice job!",
        "Good effort!",
        "Solid work!",
        "You’re getting there!",
        "Well done!"
    ];
    $keep = [
        "Good try—keep going!",
        "Nice attempt!",
        "Practice makes perfect!",
        "Keep improving!",
        "You’ve got this!"
    ];

    if ($score_pct >= 85) {
        return $great[random_int(0, count($great) - 1)];
    }
    if ($score_pct >= 60) {
        return $good[random_int(0, count($good) - 1)];
    }
    return $keep[random_int(0, count($keep) - 1)];
}

function attempt_questions_for_quiz($quiz_id)
{
    $quiz_id = (int)$quiz_id;

    $sql = "SELECT id, type, prompt, points, choices_json, answer, settings_json
            FROM questions
            WHERE quiz_id = ?
            ORDER BY position ASC, id ASC";
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, "i", $quiz_id);
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

function attempt_clear_answers($attempt_id)
{
    $attempt_id = (int)$attempt_id;

    $stmt = mysqli_prepare(db(), "DELETE FROM attempt_answers WHERE attempt_id = ?");
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "i", $attempt_id);
    $ok = mysqli_stmt_execute($stmt) ? true : false;
    mysqli_stmt_close($stmt);

    return $ok;
}

function attempt_insert_answer($attempt_id, $question_id, $answer_text, $answer_json, $is_correct, $points_awarded)
{
    $attempt_id = (int)$attempt_id;
    $question_id = (int)$question_id;
    $is_correct = $is_correct ? 1 : 0;
    $points_awarded = (float)$points_awarded;

    $answer_text = is_null($answer_text) ? null : (string)$answer_text;
    $answer_json = is_null($answer_json) ? null : (string)$answer_json;

    $sql = "INSERT INTO attempt_answers (attempt_id, question_id, answer_text, answer_json, is_correct, points_awarded)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "iissid", $attempt_id, $question_id, $answer_text, $answer_json, $is_correct, $points_awarded);
    $ok = mysqli_stmt_execute($stmt) ? true : false;
    mysqli_stmt_close($stmt);

    return $ok;
}

function scoring_norm($s)
{
    $s = trim((string)$s);
    $s = preg_replace('/\s+/', ' ', $s);
    $s = mb_strtolower($s, 'UTF-8');
    return $s;
}

function attempt_score_and_submit($attempt_uuid, $post, $time_seconds)
{
    $attempt = attempt_get_by_uuid($attempt_uuid);
    if (!$attempt) {
        return ['ok' => false, 'error' => 'attempt_not_found'];
    }

    if (!isset($attempt['status']) || (string)$attempt['status'] !== 'published') {
        return ['ok' => false, 'error' => 'quiz_not_published'];
    }

    if (isset($attempt['submitted']) && (int)$attempt['submitted'] === 1) {
        return ['ok' => false, 'error' => 'already_submitted', 'attempt' => $attempt];
    }

    $attempt_id = (int)$attempt['attempt_id'];
    $quiz_id = (int)$attempt['quiz_id'];

    $questions = attempt_questions_for_quiz($quiz_id);
    if (count($questions) === 0) {
        return ['ok' => false, 'error' => 'no_questions'];
    }

    mysqli_begin_transaction(db());

    try {
        attempt_clear_answers($attempt_id);

        $total = 0.0;

        $byType = ['mcq' => 0.0, 'identification' => 0.0, 'matching' => 0.0, 'enumeration' => 0.0];
        $maxByType = ['mcq' => 0.0, 'identification' => 0.0, 'matching' => 0.0, 'enumeration' => 0.0];

        foreach ($questions as $q) {
            $qid = isset($q['id']) ? (int)$q['id'] : 0;
            $type = isset($q['type']) ? (string)$q['type'] : '';
            $points = isset($q['points']) ? (float)$q['points'] : 0.0;

            if (!array_key_exists($type, $maxByType)) {
                $maxByType[$type] = 0.0;
                $byType[$type] = 0.0;
            }
            $maxByType[$type] += $points;

            $choices = repo_json_decode(isset($q['choices_json']) ? $q['choices_json'] : '', []);
            $settings = repo_json_decode(isset($q['settings_json']) ? $q['settings_json'] : '', []);
            $correct = isset($q['answer']) ? (string)$q['answer'] : '';

            $awarded = 0.0;
            $isCorrect = false;
            $ansText = null;
            $ansJson = null;

            if ($type === 'mcq') {
                $pick = '';
                if (isset($post['mcq']) && is_array($post['mcq']) && isset($post['mcq'][$qid])) {
                    $pick = strtoupper(trim((string)$post['mcq'][$qid]));
                }
                $ansText = $pick;

                if ($pick !== '' && $pick === strtoupper(trim($correct))) {
                    $isCorrect = true;
                    $awarded = $points;
                }
            }

            if ($type === 'identification') {
                $pick = '';
                if (isset($post['identification']) && is_array($post['identification']) && isset($post['identification'][$qid])) {
                    $pick = trim((string)$post['identification'][$qid]);
                }
                $ansText = $pick;

                $case = isset($settings['case_sensitive']) ? (int)$settings['case_sensitive'] : 0;

                if ($case === 1) {
                    if ($pick !== '' && $pick === (string)$correct) {
                        $isCorrect = true;
                        $awarded = $points;
                    }
                } else {
                    if ($pick !== '' && scoring_norm($pick) === scoring_norm($correct)) {
                        $isCorrect = true;
                        $awarded = $points;
                    }
                }
            }

            if ($type === 'matching') {
                $pairs = is_array($choices) ? $choices : [];
                $pp = isset($settings['points_per_pair']) ? (float)$settings['points_per_pair'] : 1.0;
                if ($pp <= 0) {
                    $pp = 1.0;
                }

                $studentMap = [];
                if (isset($post['matching']) && is_array($post['matching']) && isset($post['matching'][$qid]) && is_array($post['matching'][$qid])) {
                    $studentMap = $post['matching'][$qid];
                }

                $correctCount = 0;
                $i = 0;
                foreach ($pairs as $pair) {
                    $a = isset($pair['a']) ? (string)$pair['a'] : '';
                    $b = isset($pair['b']) ? (string)$pair['b'] : '';

                    $pickB = '';
                    if (isset($studentMap[$i])) {
                        $pickB = (string)$studentMap[$i];
                    }

                    if ($a !== '' && $b !== '' && $pickB !== '') {
                        if (scoring_norm($pickB) === scoring_norm($b)) {
                            $correctCount++;
                        }
                    }

                    $i++;
                }

                $awarded = (float)$correctCount * $pp;
                if ($awarded > $points) {
                    $awarded = $points;
                }

                $isCorrect = ($awarded >= $points && $points > 0) ? true : false;
                $ansJson = repo_json_encode($studentMap, '[]');
            }

            if ($type === 'enumeration') {
                $expected = is_array($choices) ? $choices : [];
                $expectedCount = count($expected);

                $studentAns = [];
                if (isset($post['enumeration']) && is_array($post['enumeration']) && isset($post['enumeration'][$qid]) && is_array($post['enumeration'][$qid])) {
                    $studentAns = $post['enumeration'][$qid];
                }

                $expectedSet = [];
                foreach ($expected as $ex) {
                    $k = scoring_norm($ex);
                    if ($k !== '' && !isset($expectedSet[$k])) {
                        $expectedSet[$k] = 1;
                    }
                }

                $seen = [];
                $correctCount = 0;
                foreach ($studentAns as $sa) {
                    $k = scoring_norm($sa);
                    if ($k === '') {
                        continue;
                    }
                    if (isset($seen[$k])) {
                        continue;
                    }
                    $seen[$k] = 1;
                    if (isset($expectedSet[$k])) {
                        $correctCount++;
                    }
                }

                $partial = isset($settings['partial_credit']) ? (int)$settings['partial_credit'] : 0;

                if ($expectedCount <= 0) {
                    $awarded = 0.0;
                } else {
                    if ($partial === 1) {
                        $awarded = ($points * ((float)$correctCount / (float)$expectedCount));
                    } else {
                        $awarded = ($correctCount >= $expectedCount) ? $points : 0.0;
                    }
                }

                if ($awarded < 0) $awarded = 0.0;
                if ($awarded > $points) $awarded = $points;

                $isCorrect = ($awarded >= $points && $points > 0) ? true : false;
                $ansJson = repo_json_encode($studentAns, '[]');
            }

            $awarded = round($awarded, 2);

            attempt_insert_answer($attempt_id, $qid, $ansText, $ansJson, $isCorrect, $awarded);

            $total += $awarded;
            if (array_key_exists($type, $byType)) {
                $byType[$type] += $awarded;
            } else {
                $byType[$type] = $awarded;
                $maxByType[$type] = $points;
            }
        }

        $total = round($total, 2);

        $time_seconds = (int)$time_seconds;
        if ($time_seconds < 0) $time_seconds = 0;

        $quizMax = isset($attempt['total_points']) ? (float)$attempt['total_points'] : 0.0;
        $pct = 0;
        if ($quizMax > 0) {
            $pct = (int)floor(($total / $quizMax) * 100);
        }

        $compliment = attempt_choose_compliment($pct);

        $scoresPayload = [
            'total' => $total,
            'percent' => $pct,
            'by_type' => [
                'mcq' => round($byType['mcq'], 2),
                'identification' => round($byType['identification'], 2),
                'matching' => round($byType['matching'], 2),
                'enumeration' => round($byType['enumeration'], 2),
            ],
            'max_by_type' => [
                'mcq' => round($maxByType['mcq'], 2),
                'identification' => round($maxByType['identification'], 2),
                'matching' => round($maxByType['matching'], 2),
                'enumeration' => round($maxByType['enumeration'], 2),
            ]
        ];

        $scores_json = repo_json_encode($scoresPayload, '{}');

        $up = mysqli_prepare(db(), "UPDATE attempts SET total_score = ?, scores_json = ?, time_seconds = ?, submitted = 1, submitted_at = NOW(), compliment = ? WHERE id = ?");
        if (!$up) {
            throw new Exception('db_prepare_update');
        }

        mysqli_stmt_bind_param($up, "dsisi", $total, $scores_json, $time_seconds, $compliment, $attempt_id);
        $ok = mysqli_stmt_execute($up);
        mysqli_stmt_close($up);

        if (!$ok) {
            throw new Exception('db_execute_update');
        }

        mysqli_commit(db());

        return [
            'ok' => true,
            'attempt_uuid' => $attempt_uuid,
            'total_score' => $total,
            'percent' => $pct,
            'compliment' => $compliment,
            'scores' => $scoresPayload
        ];
    } catch (Exception $e) {
        mysqli_rollback(db());
        return ['ok' => false, 'error' => 'submit_failed'];
    }
}

function leaderboard_for_quiz($quiz_id, $limit)
{
    $quiz_id = (int)$quiz_id;
    $limit = (int)$limit;
    if ($limit <= 0) {
        $limit = 100;
    }
    if ($limit > 500) {
        $limit = 500;
    }

    $sql = "SELECT a.total_score, a.time_seconds, a.submitted_at, s.full_name
            FROM attempts a
            INNER JOIN students s ON s.id = a.student_id
            WHERE a.quiz_id = ? AND a.submitted = 1
            ORDER BY a.total_score DESC, a.time_seconds ASC, a.submitted_at ASC
            LIMIT " . $limit;
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, "i", $quiz_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $rows = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = $r;
        }
    }

    mysqli_stmt_close($stmt);
    return $rows;
}
