<?php
$pageTitle = 'QR & UPI Settings';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();
requireRole([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/modules/admin/qr_settings.php');
    }

    $keys = [
        'upi_id'              => sanitize($_POST['upi_id'] ?? ''),
        'upi_name'            => sanitize($_POST['upi_name'] ?? ''),
        'qr_donation_enabled' => isset($_POST['qr_donation_enabled']) ? '1' : '0',
        'qr_donation_message' => sanitize($_POST['qr_donation_message'] ?? ''),
        'approval_enabled'    => isset($_POST['approval_enabled']) ? '1' : '0',
        'approval_threshold'  => (string)(int)($_POST['approval_threshold'] ?? 5000),
    ];

    $stmt = db()->prepare("INSERT INTO settings (setting_key, setting_value, updated_at)
        VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=NOW()");
    foreach ($keys as $k => $v) $stmt->execute([$k, $v]);

    // Also update masjid_profile upi fields if columns exist
    try {
        db()->prepare("UPDATE masjid_profile SET upi_id=?, upi_name=? WHERE id=1")
            ->execute([$keys['upi_id'], $keys['upi_name']]);
    } catch (Exception $e) {}

    logAudit('UPDATE', 'settings', 0);
    setFlash('success', 'QR & UPI settings saved successfully.');
    redirect(BASE_PATH . '/modules/admin/qr_settings.php');
}

$get = function(string $key, string $default = '') {
    return htmlspecialchars(getSetting($key) ?: $default, ENT_QUOTES);
};

require_once __DIR__ . '/../../templates/header.php';
?>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item">Admin</li>
        <li class="breadcrumb-item active">QR & UPI Settings</li>
    </ol>
</nav>

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">

            <!-- UPI Settings -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white fw-semibold">
                    <i class="bi bi-qr-code me-2"></i>UPI / QR Donation Settings
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">UPI ID <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-wallet2"></i></span>
                            <input type="text" name="upi_id" class="form-control"
                                   value="<?= $get('upi_id') ?>"
                                   placeholder="e.g. masjid@upi or 9876543210@paytm">
                        </div>
                        <div class="form-text">Your registered UPI handle for receiving donations.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">UPI Display Name</label>
                        <input type="text" name="upi_name" class="form-control"
                               value="<?= $get('upi_name') ?>"
                               placeholder="e.g. Jamia Masjid">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Donation Page Message</label>
                        <textarea name="qr_donation_message" class="form-control" rows="2"
                                  placeholder="Scan to donate. Jazakallah Khair!"><?= $get('qr_donation_message') ?></textarea>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="qr_donation_enabled"
                               id="qrEnabled" <?= $get('qr_donation_enabled') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="qrEnabled">Enable Public QR Donation Page</label>
                    </div>
                </div>
            </div>

            <!-- Approval Threshold -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-warning fw-semibold">
                    <i class="bi bi-shield-check me-2"></i>Payment Approval Settings
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="approval_enabled"
                               id="approvalEnabled" <?= $get('approval_enabled') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="approvalEnabled">
                            Enable Committee Approval Workflow
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Auto-Approve Below Amount (₹)</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" name="approval_threshold" class="form-control"
                                   value="<?= $get('approval_threshold', '5000') ?>" min="0">
                        </div>
                        <div class="form-text">Payments below this amount are auto-approved. Above requires committee approval.</div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-success">
                <i class="bi bi-save me-1"></i> Save Settings
            </button>
        </form>
    </div>

    <div class="col-lg-5">
        <!-- QR Preview -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light fw-semibold">
                <i class="bi bi-eye me-2"></i>QR Code Preview
            </div>
            <div class="card-body text-center">
                <?php if (!empty(getSetting('upi_id'))): ?>
                <?php
                    $upiStr = 'upi://pay?pa=' . urlencode(getSetting('upi_id'))
                            . '&pn=' . urlencode(getSetting('upi_name') ?: 'Masjid')
                            . '&cu=INR';
                    $qrUrl  = 'https://chart.googleapis.com/chart?cht=qr&chs=220x220&chld=M|0&chl=' . urlencode($upiStr);
                ?>
                <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR Preview"
                     style="border:3px solid #198754;border-radius:8px;padding:6px;" class="mb-2">
                <div class="small text-muted font-monospace"><?= htmlspecialchars(getSetting('upi_id')) ?></div>
                <?php else: ?>
                <div class="text-muted py-4">
                    <i class="bi bi-qr-code" style="font-size:3rem;"></i>
                    <p class="mt-2">Enter UPI ID to preview QR</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Public Page Link -->
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="fw-semibold mb-2"><i class="bi bi-link-45deg me-1"></i>Public Donation Page</h6>
                <div class="input-group">
                    <input type="text" class="form-control form-control-sm font-monospace"
                           id="publicUrl" readonly
                           value="<?= htmlspecialchars(rtrim(APP_URL, '/') . BASE_PATH . '/public/qr_donation.php') ?>">
                    <button class="btn btn-sm btn-outline-secondary" onclick="copyLink()">
                        <i class="bi bi-clipboard"></i>
                    </button>
                    <a href="<?= BASE_PATH ?>/public/qr_donation.php" target="_blank"
                       class="btn btn-sm btn-success">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
                <p class="text-muted small mt-2 mb-0">Share this link or embed on your website for public donations.</p>
            </div>
        </div>
    </div>
</div>

</div></div></div>
<?php
$extraJs = <<<'JS'
<script>
function copyLink() {
    var el = document.getElementById('publicUrl');
    el.select();
    navigator.clipboard.writeText(el.value).then(function() {
        alert('Link copied to clipboard!');
    });
}
</script>
JS;
require_once __DIR__ . '/../../templates/footer.php';
