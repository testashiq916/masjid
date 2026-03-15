<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/app/helpers/functions.php';

session_name(SESSION_NAME);
session_start();

// Already logged in
if (isset($_SESSION['user'])) {
    redirect(BASE_PATH . '/dashboard.php');
}

$error = '';
$timeout = isset($_GET['timeout']) ? true : false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter username and password.';
        } else {
            $stmt = db()->prepare("
                SELECT u.*, r.role_name FROM users u
                JOIN roles r ON r.id = u.role_id
                WHERE u.username = ? AND u.status = 'active' LIMIT 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Update last login
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                db()->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?")
                   ->execute([$ip, $user['id']]);

                // Set session
                $_SESSION['user'] = [
                    'id'        => $user['id'],
                    'name'      => $user['name'],
                    'username'  => $user['username'],
                    'email'     => $user['email'],
                    'role_id'   => $user['role_id'],
                    'role_name' => $user['role_name'],
                    'photo'     => $user['profile_image'],
                ];
                $_SESSION['login_time'] = time();

                logAudit('LOGIN', 'users', $user['id']);

                redirect(BASE_PATH . '/dashboard.php');
            } else {
                $error = 'Invalid username or password.';
                // Log failed attempt
                logAudit('LOGIN_FAILED', 'users', 0, [], ['username' => $username]);
            }
        }
    }
}

$csrf = generateCSRF();
$masjidName = 'Masjid ERP';
try {
    $stmt = db()->query("SELECT masjid_name FROM masjid_profile LIMIT 1");
    $r = $stmt->fetch();
    if ($r) $masjidName = $r['masjid_name'];
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($masjidName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">
            <i class="bi bi-building-fill"></i>
        </div>
        <h4 class="text-center fw-bold mb-1"><?= htmlspecialchars($masjidName) ?></h4>
        <p class="text-center text-muted small mb-4">ERP Management System</p>

        <?php if ($timeout): ?>
        <div class="alert alert-warning small">
            <i class="bi bi-clock me-1"></i> Your session has expired. Please login again.
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger small">
            <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="mb-3">
                <label class="form-label fw-semibold">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="Enter username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePass">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                <i class="bi bi-box-arrow-in-right me-2"></i>Login
            </button>
        </form>

        <p class="text-center text-muted small mt-4 mb-0">
            Default: admin / admin123
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('togglePass').addEventListener('click', function () {
    const pwd = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
});
</script>
</body>
</html>
