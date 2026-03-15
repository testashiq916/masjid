<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/lang.php';

$lang = preg_replace('/[^a-z]/', '', $_GET['lang'] ?? $_POST['lang'] ?? 'en');
setLanguage($lang);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'lang' => $lang]);
    exit;
}

$back = $_SERVER['HTTP_REFERER'] ?? BASE_PATH . '/dashboard.php';
// Sanitize referer
if (!str_starts_with($back, '/') && !str_starts_with($back, 'http')) {
    $back = BASE_PATH . '/dashboard.php';
}
header('Location: ' . $back);
exit;
