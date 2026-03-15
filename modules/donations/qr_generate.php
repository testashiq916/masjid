<?php
/**
 * QR Code Generate AJAX endpoint (logged-in users)
 */
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

header('Content-Type: application/json');

$amount  = (float)($_GET['amount'] ?? 0);
$upiId   = getSetting('upi_id');
$upiName = getSetting('upi_name') ?: (getMasjidProfile()['masjid_name'] ?? 'Masjid');

if (empty($upiId)) {
    echo json_encode(['success' => false, 'message' => 'UPI ID not configured.']);
    exit;
}

$upiStr = 'upi://pay?pa=' . urlencode($upiId)
        . '&pn=' . urlencode($upiName)
        . '&cu=INR';
if ($amount > 0) {
    $upiStr .= '&am=' . number_format($amount, 2, '.', '');
}

$qrUrl = 'https://chart.googleapis.com/chart?cht=qr&chs=300x300&chld=M|0&chl=' . urlencode($upiStr);

echo json_encode([
    'success' => true,
    'qr_url'  => $qrUrl,
    'upi_id'  => $upiId,
    'upi_str' => $upiStr,
]);
exit;
