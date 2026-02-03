<?php
date_default_timezone_set('Asia/Manila');

$cfg1 = __DIR__ . '/config.php';
$cfg2 = __DIR__ . '/../config/config.php';
if (file_exists($cfg1)) {
    require_once $cfg1;
} elseif (file_exists($cfg2)) {
    require_once $cfg2;
}

if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string)
    {
        $known_string = (string)$known_string;
        $user_string  = (string)$user_string;

        $len1 = strlen($known_string);
        $len2 = strlen($user_string);

        $diff = $len1 ^ $len2;
        $max  = ($len1 > $len2) ? $len1 : $len2;

        for ($i = 0; $i < $max; $i++) {
            $c1 = ($i < $len1) ? ord($known_string[$i]) : 0;
            $c2 = ($i < $len2) ? ord($user_string[$i]) : 0;
            $diff |= ($c1 ^ $c2);
        }
        return $diff === 0;
    }
}

if (!defined('APP_NAME')) define('APP_NAME', 'QUIZORA');

$scriptName = '';
if (isset($_SERVER['SCRIPT_NAME'])) {
    $scriptName = str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']);
}

$rootPath = '';
$publicPath = '';

$pos = strpos($scriptName, '/public/');
if ($pos !== false) {
    $rootPath = rtrim(substr($scriptName, 0, $pos), '/');
    if ($rootPath === '/') $rootPath = '';
    $publicPath = $rootPath . '/public';
} else {
    $dir = str_replace('\\', '/', dirname($scriptName));
    if ($dir === '/' || $dir === '.' || $dir === '\\') $dir = '';
    $publicPath = rtrim($dir, '/');
    $rootPath = preg_replace('#/public$#', '', $publicPath);
    $rootPath = rtrim((string)$rootPath, '/');
    if ($rootPath === '/') $rootPath = '';
}

if (!defined('ROOT_PATH')) define('ROOT_PATH', $rootPath);
if (!defined('PUBLIC_PATH')) define('PUBLIC_PATH', $publicPath);

if (!defined('BASE_URL')) define('BASE_URL', PUBLIC_PATH);

$assetsUrl = (ROOT_PATH === '') ? '/assets' : (ROOT_PATH . '/assets');
if (!defined('ASSETS_URL')) define('ASSETS_URL', $assetsUrl);

if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_NAME')) define('DB_NAME', 'quizora');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_PORT')) define('DB_PORT', 3306);

if (!defined('QUIZ_CODE_LEN')) define('QUIZ_CODE_LEN', 6);
if (!defined('MAX_QUIZ_TIME_MIN')) define('MAX_QUIZ_TIME_MIN', 240);

$secure = false;
if (defined('SESSION_COOKIE_SECURE')) {
    $secure = SESSION_COOKIE_SECURE ? true : false;
} else {
    if (isset($_SERVER['HTTPS'])) {
        $v = (string)$_SERVER['HTTPS'];
        if ($v !== '' && $v !== 'off') $secure = true;
    }
}

if (defined('SESSION_NAME') && is_string(SESSION_NAME) && SESSION_NAME !== '') {
    @session_name(SESSION_NAME);
}

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $secure ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');

if (session_status() !== PHP_SESSION_ACTIVE) {
    $cookiePath = (ROOT_PATH === '') ? '/' : (ROOT_PATH . '/');
    session_set_cookie_params(0, $cookiePath, '', $secure, true);
    session_start();
}

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function is_post()
{
    $m = 'GET';
    if (isset($_SERVER['REQUEST_METHOD'])) {
        $m = (string)$_SERVER['REQUEST_METHOD'];
    }
    return strtoupper($m) === 'POST';
}

function redirect($path)
{
    if (is_array($path)) {
        http_response_code(500);
        echo 'Redirect path error.';
        exit;
    }

    $path = (string)$path;

    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        header('Location: ' . $path);
        exit;
    }

    if ($path === '') $path = '/';
    if ($path[0] !== '/') $path = '/' . $path;

    header('Location: ' . rtrim((string)PUBLIC_PATH, '/') . $path);
    exit;
}

function flash_set($key, $msg)
{
    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) $_SESSION['_flash'] = [];
    $_SESSION['_flash'][(string)$key] = (string)$msg;
}

function flash_get($key)
{
    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) return '';
    $k = (string)$key;
    if (!isset($_SESSION['_flash'][$k])) return '';
    $val = (string)$_SESSION['_flash'][$k];
    unset($_SESSION['_flash'][$k]);
    return $val;
}

function _csrf_random_hex($bytes)
{
    $bytes = (int)$bytes;
    if ($bytes < 8) $bytes = 8;

    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($bytes));
    }

    if (function_exists('openssl_random_pseudo_bytes')) {
        $strong = false;
        $raw = openssl_random_pseudo_bytes($bytes, $strong);
        if (is_string($raw) && $raw !== '') {
            return bin2hex($raw);
        }
    }

    $raw = '';
    for ($i = 0; $i < $bytes; $i++) {
        $raw .= chr(mt_rand(0, 255));
    }
    return bin2hex($raw);
}

function csrf_token()
{
    $k = '_csrf';
    if (defined('CSRF_KEY') && is_string(CSRF_KEY) && CSRF_KEY !== '') $k = CSRF_KEY;

    if (!isset($_SESSION[$k]) || !is_string($_SESSION[$k]) || $_SESSION[$k] === '') {
        $_SESSION[$k] = _csrf_random_hex(16);
    }
    return (string)$_SESSION[$k];
}

function require_csrf()
{
    $k = '_csrf';
    if (defined('CSRF_KEY') && is_string(CSRF_KEY) && CSRF_KEY !== '') $k = CSRF_KEY;

    $sent = '';
    if (isset($_POST['_csrf'])) $sent = (string)$_POST['_csrf'];
    if ($sent === '' && isset($_POST[$k])) $sent = (string)$_POST[$k];

    $sess = '';
    if (isset($_SESSION[$k])) $sess = (string)$_SESSION[$k];

    if ($sent === '' || $sess === '' || !hash_equals($sess, $sent)) {
        http_response_code(400);
        echo 'Bad Request';
        exit;
    }
}

function db()
{
    static $conn = null;

    if ($conn instanceof mysqli) return $conn;

    $conn = mysqli_init();
    if (!$conn) {
        http_response_code(500);
        echo 'Database init failed.';
        exit;
    }

    mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);

    $ok = mysqli_real_connect($conn, DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
    if (!$ok) {
        http_response_code(500);
        echo 'Database connection failed.';
        exit;
    }

    mysqli_set_charset($conn, 'utf8mb4');
    return $conn;
}
// End of file app/bootstrap.php