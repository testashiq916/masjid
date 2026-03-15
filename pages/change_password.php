<?php
$pageTitle = 'Change Password';
require_once __DIR__ . '/../app/middleware/auth_check.php';
requireLogin();

$pdo    = db();
$user   = currentUser();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/pages/change_password.php');
    }

    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($currentPass)) $errors[] = 'Current password is required.';
    if (strlen($newPass) < 6)  $errors[] = 'New password must be at least 6 characters.';
    if ($newPass !== $confirmPass) $errors[] = 'New password and confirm password do not match.';
    if (!empty($currentPass) && !empty($newPass) && $currentPass === $newPass) {
        $errors[] = 'New password cannot be the same as the current password.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([currentUserId()]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($currentPass, $row['password'])) {
            $errors[] = 'Current password is incorrect.';
        } else {
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=?, updated_at=NOW() WHERE id=?")
                ->execute([$hashed, currentUserId()]);
            logAudit('PASSWORD_CHANGE', 'users', currentUserId());
            setFlash('success', 'Password changed successfully. Please log in again.');
            session_destroy();
            redirect(BASE_PATH . '/login.php');
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-key me-2 text-primary"></i>Change Password</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/pages/profile.php">Profile</a></li>
            <li class="breadcrumb-item active">Change Password</li>
        </ol></nav>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-5">

        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-shield-lock me-2"></i>Update Your Password
            </div>
            <div class="card-body">
                <?php foreach ($errors as $e): ?>
                <div class="alert alert-danger small py-2">
                    <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?>
                </div>
                <?php endforeach; ?>

                <form method="POST" id="changePasswordForm">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="current_password" id="current_password" class="form-control" required autocomplete="current-password">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('current_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="new_password" id="new_password" class="form-control" required
                                   autocomplete="new-password" minlength="6" oninput="checkStrength(this.value)">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('new_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="mt-2">
                            <div class="progress" style="height:5px;">
                                <div id="strengthBar" class="progress-bar" role="progressbar" style="width:0%"></div>
                            </div>
                            <small id="strengthText" class="text-muted"></small>
                        </div>
                        <small class="text-muted">Minimum 6 characters.</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Confirm New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required
                                   autocomplete="new-password" oninput="checkMatch()">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirm_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <small id="matchText" class="text-muted"></small>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i>Update Password
                        </button>
                        <a href="<?= BASE_PATH ?>/pages/profile.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Back to Profile
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3 border-0 bg-light">
            <div class="card-body small">
                <h6 class="fw-semibold mb-2"><i class="bi bi-info-circle text-info me-1"></i>Password Tips</h6>
                <ul class="mb-0 text-muted ps-3">
                    <li>Use at least 8 characters for better security</li>
                    <li>Mix uppercase, lowercase, numbers, and symbols</li>
                    <li>Avoid using your name or common words</li>
                    <li>Do not reuse passwords from other services</li>
                </ul>
            </div>
        </div>

    </div>
</div>

</div></div></div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
<script>
function togglePassword(fieldId, btn) {
    var field = document.getElementById(fieldId);
    var icon  = btn.querySelector('i');
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

function checkStrength(password) {
    var bar  = document.getElementById('strengthBar');
    var text = document.getElementById('strengthText');
    var score = 0;
    if (password.length >= 6)  score++;
    if (password.length >= 10) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    var levels = [
        {pct: 0,   cls: 'bg-secondary', label: ''},
        {pct: 20,  cls: 'bg-danger',    label: 'Very Weak'},
        {pct: 40,  cls: 'bg-warning',   label: 'Weak'},
        {pct: 60,  cls: 'bg-info',      label: 'Fair'},
        {pct: 80,  cls: 'bg-primary',   label: 'Strong'},
        {pct: 100, cls: 'bg-success',   label: 'Very Strong'},
    ];
    var level = levels[score] || levels[0];
    bar.style.width  = level.pct + '%';
    bar.className    = 'progress-bar ' + level.cls;
    text.textContent = level.label;
    text.className   = 'small ' + (score >= 3 ? 'text-success' : 'text-warning');
}

function checkMatch() {
    var newPwd  = document.getElementById('new_password').value;
    var confPwd = document.getElementById('confirm_password').value;
    var text    = document.getElementById('matchText');
    if (confPwd.length === 0) { text.textContent = ''; return; }
    if (newPwd === confPwd) {
        text.textContent = '✓ Passwords match';
        text.className   = 'small text-success';
    } else {
        text.textContent = '✗ Passwords do not match';
        text.className   = 'small text-danger';
    }
}

document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    var newPwd  = document.getElementById('new_password').value;
    var confPwd = document.getElementById('confirm_password').value;
    if (newPwd !== confPwd) {
        e.preventDefault();
        document.getElementById('matchText').textContent = '✗ Passwords do not match';
        document.getElementById('matchText').className   = 'small text-danger';
    }
});
</script>
