<?php
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();
requireRole([ROLE_ADMIN]);

require_once __DIR__ . '/../../app/helpers/sms.php';

$pageTitle = 'SMS Settings';
$errors    = [];
$success   = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please try again.';
    } else {
        $action = $_POST['action'] ?? 'save';

        if ($action === 'test_sms') {
            // Send test SMS to admin's phone
            $pdo = db();
            $stmt = $pdo->prepare("SELECT phone FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => currentUserId()]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (empty($admin['phone'])) {
                $errors[] = 'No phone number found for your account. Please update your profile first.';
            } else {
                $profile    = getMasjidProfile();
                $masjidName = $profile['name'] ?? 'Masjid ERP';
                $testMsg    = "Test SMS from {$masjidName} ERP. If you received this, SMS is working correctly.";
                $sent = sendSMS($admin['phone'], $testMsg, 'test', 0);
                if ($sent) {
                    $success = 'Test SMS sent successfully to ' . htmlspecialchars($admin['phone']) . '.';
                } else {
                    $errors[] = 'Failed to send test SMS. Please check your API key and SMS settings.';
                }
            }
        } else {
            // Save settings
            $settings = [
                'sms_provider'      => sanitize($_POST['sms_provider'] ?? 'fast2sms'),
                'sms_api_key'       => trim($_POST['sms_api_key'] ?? ''),
                'sms_sender_id'     => sanitize($_POST['sms_sender_id'] ?? ''),
                'sms_enabled'       => isset($_POST['sms_enabled']) ? '1' : '0',
                'sms_on_receipt'    => isset($_POST['sms_on_receipt']) ? '1' : '0',
                'sms_on_salary'     => isset($_POST['sms_on_salary']) ? '1' : '0',
                'sms_on_rent_due'   => isset($_POST['sms_on_rent_due']) ? '1' : '0',
                'sms_on_approval'   => isset($_POST['sms_on_approval']) ? '1' : '0',
            ];

            $pdo = db();
            foreach ($settings as $key => $value) {
                $stmt = $pdo->prepare(
                    "INSERT INTO settings (`key`, `value`) VALUES (:key, :value)
                     ON DUPLICATE KEY UPDATE `value` = :value2"
                );
                $stmt->execute([':key' => $key, ':value' => $value, ':value2' => $value]);
            }

            logAudit('sms_settings_updated', 'settings', 0, 'SMS settings updated by admin');
            $success = 'SMS settings saved successfully.';
        }
    }
}

// Load current settings
$currentSettings = [
    'sms_provider'    => getSetting('sms_provider') ?: 'fast2sms',
    'sms_api_key'     => getSetting('sms_api_key') ?: '',
    'sms_sender_id'   => getSetting('sms_sender_id') ?: '',
    'sms_enabled'     => getSetting('sms_enabled'),
    'sms_on_receipt'  => getSetting('sms_on_receipt'),
    'sms_on_salary'   => getSetting('sms_on_salary'),
    'sms_on_rent_due' => getSetting('sms_on_rent_due'),
    'sms_on_approval' => getSetting('sms_on_approval'),
];

// Load last 50 SMS logs
$pdo      = db();
$logStmt  = $pdo->query(
    "SELECT id, mobile, message, status, provider, reference_type, reference_id, created_at
     FROM sms_log
     ORDER BY created_at DESC
     LIMIT 50"
);
$smsLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/../../templates/sidebar.php';
require_once __DIR__ . '/../../templates/navbar.php';
?>

<div class="content-wrapper">
    <div class="container-fluid py-4">

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0"><i class="fas fa-sms me-2 text-primary"></i>SMS Settings</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/modules/admin/">Admin</a></li>
                        <li class="breadcrumb-item active">SMS Settings</li>
                    </ol>
                </nav>
            </div>
            <a href="<?= BASE_PATH ?>/modules/admin/sms_log.php" class="btn btn-outline-secondary">
                <i class="fas fa-list me-1"></i>View Full SMS Log
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Settings Form -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-cog me-2"></i>SMS Configuration</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="smsSettingsForm">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                            <input type="hidden" name="action" value="save">

                            <!-- Provider -->
                            <div class="mb-3">
                                <label for="sms_provider" class="form-label fw-semibold">SMS Provider</label>
                                <select name="sms_provider" id="sms_provider" class="form-select">
                                    <option value="fast2sms" <?= $currentSettings['sms_provider'] === 'fast2sms' ? 'selected' : '' ?>>Fast2SMS</option>
                                    <option value="msg91"    <?= $currentSettings['sms_provider'] === 'msg91'    ? 'selected' : '' ?>>MSG91</option>
                                    <option value="textlocal" <?= $currentSettings['sms_provider'] === 'textlocal' ? 'selected' : '' ?>>Textlocal</option>
                                </select>
                                <div class="form-text">Currently only Fast2SMS (bulkV2) integration is active.</div>
                            </div>

                            <!-- API Key -->
                            <div class="mb-3">
                                <label for="sms_api_key" class="form-label fw-semibold">API Key</label>
                                <div class="input-group">
                                    <input type="password"
                                           name="sms_api_key"
                                           id="sms_api_key"
                                           class="form-control"
                                           value="<?= htmlspecialchars($currentSettings['sms_api_key']) ?>"
                                           placeholder="Enter your Fast2SMS API key"
                                           autocomplete="off">
                                    <button class="btn btn-outline-secondary"
                                            type="button"
                                            id="toggleApiKey"
                                            title="Show/Hide API Key">
                                        <i class="fas fa-eye" id="toggleApiKeyIcon"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    Get your API key from
                                    <a href="https://www.fast2sms.com/dashboard/api-info" target="_blank" rel="noopener">
                                        Fast2SMS Dashboard
                                    </a>
                                </div>
                            </div>

                            <!-- Sender ID -->
                            <div class="mb-3">
                                <label for="sms_sender_id" class="form-label fw-semibold">Sender ID</label>
                                <input type="text"
                                       name="sms_sender_id"
                                       id="sms_sender_id"
                                       class="form-control"
                                       value="<?= htmlspecialchars($currentSettings['sms_sender_id']) ?>"
                                       placeholder="e.g. MASJID"
                                       maxlength="11">
                                <div class="form-text">6-character sender ID (if applicable to your plan).</div>
                            </div>

                            <hr>

                            <!-- Enable SMS -->
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           role="switch"
                                           name="sms_enabled"
                                           id="sms_enabled"
                                           value="1"
                                           <?= $currentSettings['sms_enabled'] === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="sms_enabled">
                                        Enable SMS Notifications
                                    </label>
                                </div>
                                <div class="form-text">Master switch — disabling this stops all SMS notifications.</div>
                            </div>

                            <div class="card bg-light mb-3" id="smsEventToggles">
                                <div class="card-header fw-semibold">Notification Events</div>
                                <div class="card-body">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               role="switch"
                                               name="sms_on_receipt"
                                               id="sms_on_receipt"
                                               value="1"
                                               <?= $currentSettings['sms_on_receipt'] === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="sms_on_receipt">
                                            <i class="fas fa-receipt text-success me-1"></i>
                                            Send SMS on Donation Receipt
                                        </label>
                                    </div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               role="switch"
                                               name="sms_on_salary"
                                               id="sms_on_salary"
                                               value="1"
                                               <?= $currentSettings['sms_on_salary'] === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="sms_on_salary">
                                            <i class="fas fa-money-bill-wave text-primary me-1"></i>
                                            Send SMS on Salary Payment
                                        </label>
                                    </div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               role="switch"
                                               name="sms_on_rent_due"
                                               id="sms_on_rent_due"
                                               value="1"
                                               <?= $currentSettings['sms_on_rent_due'] === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="sms_on_rent_due">
                                            <i class="fas fa-building text-warning me-1"></i>
                                            Send SMS on Rent Due Reminder
                                        </label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               role="switch"
                                               name="sms_on_approval"
                                               id="sms_on_approval"
                                               value="1"
                                               <?= $currentSettings['sms_on_approval'] === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="sms_on_approval">
                                            <i class="fas fa-check-double text-info me-1"></i>
                                            Send SMS on Voucher Approval/Rejection
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Save Settings
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="testSmsBtn">
                                    <i class="fas fa-paper-plane me-1"></i>Send Test SMS
                                </button>
                            </div>
                        </form>

                        <!-- Hidden test SMS form -->
                        <form method="POST" action="" id="testSmsForm" class="d-none">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                            <input type="hidden" name="action" value="test_sms">
                        </form>
                    </div>
                </div>
            </div>

            <!-- Info Panel -->
            <div class="col-lg-4">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Integration Info</h6>
                    </div>
                    <div class="card-body small">
                        <p><strong>Provider:</strong> Fast2SMS (India)</p>
                        <p><strong>API Endpoint:</strong><br>
                            <code class="text-break">fast2sms.com/dev/bulkV2</code>
                        </p>
                        <p><strong>Route:</strong> Quick SMS (q)</p>
                        <p class="mb-0"><strong>Supports:</strong> All Indian mobile numbers (10 digits)</p>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>SMS Stats (Today)</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $statsStmt = $pdo->query(
                            "SELECT
                                SUM(status = 'sent')   AS sent_today,
                                SUM(status = 'failed') AS failed_today,
                                COUNT(*)               AS total_today
                             FROM sms_log
                             WHERE DATE(created_at) = CURDATE()"
                        );
                        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
                        ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Sent Today</span>
                            <span class="badge bg-success"><?= (int)($stats['sent_today'] ?? 0) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Failed Today</span>
                            <span class="badge bg-danger"><?= (int)($stats['failed_today'] ?? 0) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Total Today</span>
                            <span class="badge bg-secondary"><?= (int)($stats['total_today'] ?? 0) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SMS Log Table -->
        <div class="card shadow-sm mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent SMS Log (Last 50)</h5>
                <a href="<?= BASE_PATH ?>/modules/admin/sms_log.php" class="btn btn-sm btn-outline-primary">
                    View All Logs
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="smsLogTable" class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Date &amp; Time</th>
                                <th>Mobile</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($smsLogs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>No SMS logs found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($smsLogs as $log): ?>
                                    <tr>
                                        <td><?= (int)$log['id'] ?></td>
                                        <td><?= htmlspecialchars(formatDate($log['created_at'])) ?></td>
                                        <td>
                                            <?php
                                            $m = preg_replace('/\D/', '', $log['mobile']);
                                            echo htmlspecialchars(strlen($m) >= 6
                                                ? substr($m, 0, 3) . 'XXXX' . substr($m, -3)
                                                : $log['mobile']);
                                            ?>
                                        </td>
                                        <td>
                                            <span title="<?= htmlspecialchars($log['message']) ?>">
                                                <?= htmlspecialchars(mb_strimwidth($log['message'], 0, 60, '...')) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($log['status'] === 'sent'): ?>
                                                <span class="badge bg-success">Sent</span>
                                            <?php elseif ($log['status'] === 'failed'): ?>
                                                <span class="badge bg-danger">Failed</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($log['reference_type'])): ?>
                                                <span class="badge bg-secondary">
                                                    <?= htmlspecialchars(ucfirst($log['reference_type'])) ?>
                                                    <?= $log['reference_id'] ? '#' . (int)$log['reference_id'] : '' ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /.container-fluid -->
</div><!-- /.content-wrapper -->

<script>
// Toggle API key visibility
document.getElementById('toggleApiKey').addEventListener('click', function () {
    const input = document.getElementById('sms_api_key');
    const icon  = document.getElementById('toggleApiKeyIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
});

// Test SMS button
document.getElementById('testSmsBtn').addEventListener('click', function () {
    if (confirm('Send a test SMS to your registered phone number?')) {
        document.getElementById('testSmsForm').submit();
    }
});

// DataTables
document.addEventListener('DOMContentLoaded', function () {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#smsLogTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 15,
            responsive: true,
            columnDefs: [{ orderable: false, targets: [3] }],
            language: { emptyTable: 'No SMS logs found.' }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
