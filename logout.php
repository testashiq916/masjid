<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/app/helpers/functions.php';

session_name(SESSION_NAME);
session_start();

if (isset($_SESSION['user'])) {
    logAudit('LOGOUT', 'users', $_SESSION['user']['id']);
}

session_unset();
session_destroy();

redirect(BASE_PATH . '/login.php');
