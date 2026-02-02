<?php
// db.php

require_once __DIR__ . '/config.php';

function db()
{
    static $mysqli = null;

    if ($mysqli !== null) {
        return $mysqli;
    }

    $mysqli = mysqli_init();
    if ($mysqli === false) {
        http_response_code(500);
        exit('Database init failed.');
    }

    mysqli_options($mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, 5);

    $ok = mysqli_real_connect(
        $mysqli,
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME,
        DB_PORT,
        null,
        MYSQLI_CLIENT_FOUND_ROWS
    );

    if (!$ok) {
        http_response_code(500);
        exit('Database connection failed.');
    }

    mysqli_set_charset($mysqli, 'utf8mb4');

    return $mysqli;
}
