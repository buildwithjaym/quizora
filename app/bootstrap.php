<?php
// app/bootstrap.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

session_name(SESSION_NAME);

$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookieParams['path'],
    'domain' => $cookieParams['domain'],
    'secure' => SESSION_COOKIE_SECURE ? true : false,
    'httponly' => SESSION_COOKIE_HTTPONLY ? true : false,
    'samesite' => SESSION_COOKIE_SAMESITE
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect($path)
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

function is_post()
{
    return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
}

function csrf_token()
{
    if (!isset($_SESSION[CSRF_KEY]) || !is_string($_SESSION[CSRF_KEY]) || $_SESSION[CSRF_KEY] === '') {
        $_SESSION[CSRF_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_KEY];
}

function csrf_check()
{
    if (!is_post()) {
        return false;
    }

    if (!isset($_POST['_csrf'])) {
        return false;
    }

    if (!isset($_SESSION[CSRF_KEY])) {
        return false;
    }

    $a = (string)$_POST['_csrf'];
    $b = (string)$_SESSION[CSRF_KEY];

    if ($a === '' || $b === '') {
        return false;
    }

    return hash_equals($b, $a);
}

function require_csrf()
{
    if (!csrf_check()) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

function flash_set($key, $value)
{
    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        $_SESSION['_flash'] = [];
    }
    $_SESSION['_flash'][$key] = $value;
}

function flash_get($key)
{
    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        return null;
    }
    if (!array_key_exists($key, $_SESSION['_flash'])) {
        return null;
    }
    $val = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $val;
}
