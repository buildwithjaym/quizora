<?php
// config.php

/**
 * QUIZORA - Global Config
 * PHP 8.3 + MySQL (mysqli)
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');

date_default_timezone_set('UTC');

define('APP_NAME', 'QUIZORA');
define('APP_ENV', 'local'); // local | prod

// Base URL (for localhost). Adjust if you run in a subfolder like /quizora
define('BASE_URL', 'http://localhost/quizora');

// Database
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'quizora');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);

// Security
define('SESSION_NAME', 'quizora_session');
define('CSRF_KEY', 'quizora_csrf');
define('SESSION_COOKIE_SECURE', false); // set true if using https
define('SESSION_COOKIE_HTTPONLY', true);
define('SESSION_COOKIE_SAMESITE', 'Lax');

// Misc
define('QUIZ_CODE_LEN', 6);
define('MAX_QUIZ_TIME_MIN', 240);

// Brand convenience
define('COLOR_PRIMARY', '#1D4E89');
define('COLOR_ACCENT',  '#F59E0B');
define('COLOR_BG',      '#F3F4F6');
define('COLOR_TEXT',    '#111827');
define('COLOR_SUCCESS', '#10B981');
define('COLOR_ERROR',   '#E02424');
define('COLOR_MUTED',   '#6B7280');
