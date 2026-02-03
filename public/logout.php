<?php
// public/logout.php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';

auth_logout_teacher();

flash_set('success', 'Logged out successfully.');
redirect('/index.php');
