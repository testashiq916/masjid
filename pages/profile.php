<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/../app/middleware/auth_check.php';
requireLogin();

$pdo  = db();
$user = currentUser();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/pages/profile.php');
    }

    $name  = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');

    if (empty($name)) {
        $errors[] = 'Full name is required.';
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }

    if (empty($errors)) {
        $pdo->prepare("UPDATE users SET name=?, email=?, phone=?, updated_at=NOW() WHERE id=?")
            ->execute([$name, $email, $phone, $user['id']]);
        $_SESSION['user']['name']  = $name;
        $_SESSION['user']['email'] = $email;
        setFlash('success', 'Profile updated successfully.');
        redirect(BASE_PATH . '/pages/profile.php');
    }
}

// Fetch fresh user data
$stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?");
$stmt->execute([$user['id']]);
$userData = $stmt->fetch();

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
        <h4 class="mb-0"><i class="bi bi-person-circle me-2 text-primary"></i>My Profile</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Home</a></li>
            <li class="breadcrumb-item active">Profile</li>
        </ol></nav>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">

        <!-- Profile Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-body text-center py-4">
                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                     style="width:90px;height:90px;font-size:2.2rem;font-weight:bold;">
                    <?= strtoupper(substr($userData['name'], 0, 1)) ?>
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($userData['name']) ?></h5>
                <span class="badge bg-info fs-6 mb-2"><?= htmlspecialchars($userData['role_name']) ?></span>
                <div class="text-muted small">
                    <i class="bi bi-person me-1"></i>@<?= htmlspecialchars($userData['username']) ?>
                    <?php if ($userData['last_login']): ?>
                    &nbsp;|&nbsp;<i class="bi bi-clock me-1"></i>Last login: <?= formatDate($userData['last_login'], 'd/m/Y H:i') ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-pencil-square me-2"></i>Edit Profile
            </div>
            <div class="card-body">
                <?php foreach ($errors as $e): ?>
                <div class="alert alert-danger small"><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($userData['name']) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-at"></i></span>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($userData['username']) ?>" disabled>
                        </div>
                        <small class="text-muted">Username cannot be changed.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($userData['email'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Save Changes
                        </button>
                        <a href="<?= BASE_PATH ?>/pages/change_password.php" class="btn btn-outline-secondary">
                            <i class="bi bi-key me-1"></i>Change Password
                        </a>
                        <a href="<?= BASE_PATH ?>/dashboard.php" class="btn btn-outline-dark ms-auto">
                            <i class="bi bi-arrow-left me-1"></i>Back
                        </a>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

</div></div></div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
