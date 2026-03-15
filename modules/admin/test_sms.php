<?php
/**
 * Test SMS AJAX Endpoint
 */
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();
requireRole([ROLE_ADMIN]);
require_once __DIR__ . '/../../app/helpers/sms.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

$mobile = sanitize($_POST['test_mobile'] ?? '');
if (empty($mobile) || strlen(preg_replace('/\D/', '', $mobile)) < 10) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid 10-digit mobile number.']);
    exit;
}

$masjid  = getMasjidProfile();
$appName = $masjid['masjid_name'] ?? getSetting('app_name') ?? 'Masjid ERP';
$message = "This is a test SMS from {$appName}. Your SMS configuration is working correctly. - Masjid ERP";

$result = sendSMS($mobile, $message, 'test', 0);

if ($result) {
    echo json_encode(['success' => true, 'message' => "Test SMS sent to {$mobile} successfully."]);
} else {
    // Check if SMS is disabled
    if (getSetting('sms_enabled') !== '1') {
        echo json_encode(['success' => false, 'message' => 'SMS is disabled. Enable it in settings first.']);
    } elseif (empty(getSetting('sms_api_key'))) {
        echo json_encode(['success' => false, 'message' => 'SMS API key is not configured.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send SMS. Check your API key and mobile number.']);
    }
}
exit;
