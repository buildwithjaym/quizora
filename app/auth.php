<?php
// app/auth.php

require_once __DIR__ . '/bootstrap.php';

function teacher_is_logged_in()
{
    return isset($_SESSION['teacher_id']) && is_numeric($_SESSION['teacher_id']) && (int)$_SESSION['teacher_id'] > 0;
}

function teacher_id()
{
    if (!teacher_is_logged_in()) {
        return null;
    }
    return (int)$_SESSION['teacher_id'];
}

function require_teacher()
{
    if (!teacher_is_logged_in()) {
        flash_set('error', 'Please log in to continue.');
        redirect('/login.php');
    }
}

function auth_login_teacher($id)
{
    $_SESSION['teacher_id'] = (int)$id;
}

function auth_logout_teacher()
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function auth_require_guest()
{
    if (teacher_is_logged_in()) {
        redirect('/teacher_dashboard.php');
    }
}
