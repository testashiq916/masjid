<?php
$pageTitle = 'Change Password';
require_once __DIR__ . '/../app/middleware/auth_check.php';
requireLogin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($currentPass)) $errors[] = 'Current password is required.';
    if (strlen($newPass) < 6)  $errors[] = 'New password must be at least 6 characters.';
    if ($newPass !== $confirmPass) $errors[] = 'New passwords do not match.';

    if (empty($errors)) {
        $stmt = db()->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([currentUserId()]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($currentPass, $row['password'])) {
            $errors[] = 'Current password is incorrect.';
        } else {
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            db()->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$hashed, currentUserId()]);
            logAudit('PASSWORD_CHANGE', 'users', currentUserId());
            setFlash('success', 'Password changed successfully.');
            redirect(BASE_PATH . '/pages/profile.php');
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

<div class="page-header">
    <div><h4><i class="bi bi-key me-2 text-primary"></i>Change Password</h4>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/pages/profile.php">Profile</a></li>
        <li class="breadcrumb-item active">Change Password</li>
    </ol></nav></div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-warning text-dark"><i class="bi bi-shield-lock me-2"></i>Change Password</div>
            <div class="card-body">
                <?php foreach ($errors as $e): ?>
                <div class="alert alert-danger small"><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Password <span class="text-danger">*</span></label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control" minlength="6" required>
                        <div class="form-text">Minimum 6 characters.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-warning fw-semibold"><i class="bi bi-lock me-1"></i>Change Password</button>
                    <a href="<?= BASE_PATH ?>/pages/profile.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

</div></div></div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
