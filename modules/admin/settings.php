<?php
$pageTitle = 'System Settings';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();
requireRole([ROLE_ADMIN]);

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/modules/admin/settings.php');
    }

    $settingsToSave = [
        // General
        'currency_symbol'   => sanitize($_POST['currency_symbol'] ?? '₹'),
        'date_format'        => sanitize($_POST['date_format'] ?? 'd/m/Y'),
        'app_name'           => sanitize($_POST['app_name'] ?? ''),
        'timezone'           => sanitize($_POST['timezone'] ?? 'Asia/Kolkata'),
        // Accounting
        'receipt_prefix'     => sanitize($_POST['receipt_prefix'] ?? 'REC'),
        'voucher_prefix'     => sanitize($_POST['voucher_prefix'] ?? 'VCH'),
        'journal_prefix'     => sanitize($_POST['journal_prefix'] ?? 'JNL'),
        'payment_prefix'     => sanitize($_POST['payment_prefix'] ?? 'PAY'),
        // Notifications
        'enable_whatsapp'    => isset($_POST['enable_whatsapp']) ? '1' : '0',
        'enable_sms'         => isset($_POST['enable_sms']) ? '1' : '0',
        'enable_email'       => isset($_POST['enable_email']) ? '1' : '0',
        'whatsapp_api_key'   => sanitize($_POST['whatsapp_api_key'] ?? ''),
        'sms_api_key'        => sanitize($_POST['sms_api_key'] ?? ''),
        'smtp_host'          => sanitize($_POST['smtp_host'] ?? ''),
        'smtp_port'          => sanitize($_POST['smtp_port'] ?? '587'),
        'smtp_user'          => sanitize($_POST['smtp_user'] ?? ''),
        'smtp_pass'          => $_POST['smtp_pass'] ?? '',
        'smtp_from_name'     => sanitize($_POST['smtp_from_name'] ?? ''),
    ];

    $upsertStmt = $db->prepare("
        INSERT INTO settings (setting_key, setting_value, updated_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
    ");

    foreach ($settingsToSave as $key => $value) {
        $upsertStmt->execute([$key, $value]);
    }

    logAudit('UPDATE', 'settings', 0, [], $settingsToSave);
    setFlash('success', 'Settings saved successfully.');
    redirect(BASE_PATH . '/modules/admin/settings.php');
}

// Load all settings
$settingsRaw = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$s = function(string $key, string $default = '') use ($settingsRaw): string {
    return htmlspecialchars($settingsRaw[$key] ?? $default);
};
$sBool = function(string $key) use ($settingsRaw): bool {
    return !empty($settingsRaw[$key]) && $settingsRaw[$key] === '1';
};

require_once __DIR__ . '/../../templates/header.php';
?>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item">Admin</li>
            <li class="breadcrumb-item active">Settings</li>
        </ol>
    </nav>

    <!-- Flash Messages -->
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex align-items-center mb-4">
        <div>
            <h4 class="mb-0"><i class="bi bi-sliders me-2 text-primary"></i>System Settings</h4>
            <small class="text-muted">Configure application-wide settings</small>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#generalTab" type="button">
                    <i class="bi bi-gear me-1"></i>General
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#accountingTab" type="button">
                    <i class="bi bi-calculator me-1"></i>Accounting
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#notificationsTab" type="button">
                    <i class="bi bi-bell me-1"></i>Notifications
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#emailTab" type="button">
                    <i class="bi bi-envelope me-1"></i>Email (SMTP)
                </button>
            </li>
        </ul>

        <div class="tab-content">

            <!-- General Settings -->
            <div class="tab-pane fade show active" id="generalTab">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="bi bi-gear me-2"></i>General Settings</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Application Name</label>
                                <input type="text" class="form-control" name="app_name"
                                       value="<?= $s('app_name', 'Masjid ERP') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Currency Symbol</label>
                                <input type="text" class="form-control" name="currency_symbol"
                                       value="<?= $s('currency_symbol', '₹') ?>" maxlength="5">
                                <div class="form-text">e.g. ₹, $, £</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Date Format</label>
                                <select class="form-select" name="date_format">
                                    <?php
                                    $formats = ['d/m/Y' => 'DD/MM/YYYY', 'm/d/Y' => 'MM/DD/YYYY', 'Y-m-d' => 'YYYY-MM-DD', 'd-m-Y' => 'DD-MM-YYYY'];
                                    foreach ($formats as $fmt => $label):
                                    ?>
                                    <option value="<?= $fmt ?>" <?= ($settingsRaw['date_format'] ?? 'd/m/Y') === $fmt ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Timezone</label>
                                <select class="form-select" name="timezone">
                                    <?php
                                    $timezones = DateTimeZone::listIdentifiers(DateTimeZone::ASIA);
                                    $currentTz = $settingsRaw['timezone'] ?? 'Asia/Kolkata';
                                    foreach ($timezones as $tz):
                                    ?>
                                    <option value="<?= $tz ?>" <?= $currentTz === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Accounting Settings -->
            <div class="tab-pane fade" id="accountingTab">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="bi bi-calculator me-2"></i>Accounting Settings</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">These prefixes are used when auto-generating receipt, voucher, and journal numbers.</p>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Receipt Prefix</label>
                                <input type="text" class="form-control text-uppercase" name="receipt_prefix"
                                       value="<?= $s('receipt_prefix', 'REC') ?>" maxlength="10">
                                <div class="form-text">e.g. REC-202412-0001</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Payment Voucher Prefix</label>
                                <input type="text" class="form-control text-uppercase" name="voucher_prefix"
                                       value="<?= $s('voucher_prefix', 'VCH') ?>" maxlength="10">
                                <div class="form-text">e.g. VCH-202412-0001</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Journal Prefix</label>
                                <input type="text" class="form-control text-uppercase" name="journal_prefix"
                                       value="<?= $s('journal_prefix', 'JNL') ?>" maxlength="10">
                                <div class="form-text">e.g. JNL-202412-0001</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Payment Prefix</label>
                                <input type="text" class="form-control text-uppercase" name="payment_prefix"
                                       value="<?= $s('payment_prefix', 'PAY') ?>" maxlength="10">
                                <div class="form-text">e.g. PAY-202412-0001</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notification Settings -->
            <div class="tab-pane fade" id="notificationsTab">
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="bi bi-bell me-2"></i>Notification Settings</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="enable_whatsapp"
                                           id="enableWhatsapp" value="1"
                                           <?= $sBool('enable_whatsapp') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="enableWhatsapp">
                                        <i class="bi bi-whatsapp text-success me-1"></i>Enable WhatsApp Notifications
                                    </label>
                                </div>
                                <div class="col-md-8 mb-3" id="whatsappApiDiv">
                                    <label class="form-label">WhatsApp API Key</label>
                                    <input type="text" class="form-control" name="whatsapp_api_key"
                                           value="<?= $s('whatsapp_api_key') ?>" placeholder="Enter API key">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="enable_sms"
                                           id="enableSms" value="1"
                                           <?= $sBool('enable_sms') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="enableSms">
                                        <i class="bi bi-chat-dots text-primary me-1"></i>Enable SMS Notifications
                                    </label>
                                </div>
                                <div class="col-md-8 mb-3" id="smsApiDiv">
                                    <label class="form-label">SMS API Key</label>
                                    <input type="text" class="form-control" name="sms_api_key"
                                           value="<?= $s('sms_api_key') ?>" placeholder="Enter SMS API key">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="enable_email"
                                           id="enableEmail" value="1"
                                           <?= $sBool('enable_email') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="enableEmail">
                                        <i class="bi bi-envelope text-info me-1"></i>Enable Email Notifications
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Email (SMTP) Settings -->
            <div class="tab-pane fade" id="emailTab">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="bi bi-envelope me-2"></i>Email (SMTP) Configuration</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">SMTP Host</label>
                                <input type="text" class="form-control" name="smtp_host"
                                       value="<?= $s('smtp_host') ?>" placeholder="smtp.gmail.com">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">SMTP Port</label>
                                <input type="number" class="form-control" name="smtp_port"
                                       value="<?= $s('smtp_port', '587') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">From Name</label>
                                <input type="text" class="form-control" name="smtp_from_name"
                                       value="<?= $s('smtp_from_name') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">SMTP Username</label>
                                <input type="email" class="form-control" name="smtp_user"
                                       value="<?= $s('smtp_user') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">SMTP Password</label>
                                <input type="password" class="form-control" name="smtp_pass"
                                       value="<?= $s('smtp_pass') ?>" autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- end tab-content -->

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-save me-1"></i> Save All Settings
            </button>
            <a href="<?= BASE_PATH ?>/dashboard.php" class="btn btn-secondary btn-lg">Cancel</a>
        </div>
    </form>

</div>
</div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
