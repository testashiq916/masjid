<?php
require_once __DIR__ . '/config/app.php';
session_name(SESSION_NAME);
session_start();
if (isset($_SESSION['user'])) {
    header('Location: ' . BASE_PATH . '/dashboard.php');
} else {
    header('Location: ' . BASE_PATH . '/login.php');
}
exit;
