<?php
require_once __DIR__ . '/../app/middleware/auth_check.php';
requireLogin();

if (!isset($_GET['ajax'])) {
    redirect(BASE_PATH . '/dashboard.php');
}

$notifications = [];
$count = 0;

// Pending rent
$stmt = db()->query("SELECT COUNT(*) as cnt FROM rent_collections WHERE status IN ('pending','overdue')");
$rent = $stmt->fetch();
if ($rent['cnt'] > 0) {
    $notifications[] = ['icon' => 'bi-house-exclamation text-warning', 'text' => $rent['cnt'] . ' pending rent collection(s)', 'url' => BASE_PATH . '/modules/waqf/rent_due.php'];
    $count += $rent['cnt'];
}

// Pending salary
$stmt = db()->query("SELECT COUNT(*) as cnt FROM salary_sheet WHERE status = 'pending'");
$sal = $stmt->fetch();
if ($sal['cnt'] > 0) {
    $notifications[] = ['icon' => 'bi-wallet2 text-info', 'text' => $sal['cnt'] . ' salary payment(s) pending', 'url' => BASE_PATH . '/modules/salary/salary_payment.php'];
    $count += $sal['cnt'];
}

// Expiring agreements (next 30 days)
$stmt = db()->query("SELECT COUNT(*) as cnt FROM tenants WHERE agreement_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status='active'");
$exp = $stmt->fetch();
if ($exp['cnt'] > 0) {
    $notifications[] = ['icon' => 'bi-calendar-x text-danger', 'text' => $exp['cnt'] . ' tenant agreement(s) expiring soon', 'url' => BASE_PATH . '/modules/waqf/tenants.php'];
    $count += $exp['cnt'];
}

$html = '';
if (empty($notifications)) {
    $html = '<p class="mb-0 text-muted small">No new notifications</p>';
} else {
    foreach ($notifications as $n) {
        $html .= '<a href="' . $n['url'] . '" class="d-flex align-items-start gap-2 py-2 text-decoration-none text-dark border-bottom">';
        $html .= '<i class="bi ' . $n['icon'] . ' fs-5 mt-1"></i>';
        $html .= '<span class="small">' . htmlspecialchars($n['text']) . '</span></a>';
    }
}

echo json_encode(['count' => $count, 'html' => $html]);
