<?php
$pageTitle = 'User Management';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();
requireRole([ROLE_ADMIN]);

$db = db();
$errors = [];
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/modules/admin/users.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name     = sanitize($_POST['name'] ?? '');
        $username = sanitize($_POST['username'] ?? '');
        $email    = sanitize($_POST['email'] ?? '');
        $roleId   = (int)($_POST['role_id'] ?? 0);
        $status   = sanitize($_POST['status'] ?? 'active');
        $password = $_POST['password'] ?? '';
        $userId   = (int)($_POST['user_id'] ?? 0);

        if (empty($name) || empty($username) || empty($email) || !$roleId) {
            setFlash('danger', 'Name, username, email and role are required.');
            redirect(BASE_PATH . '/modules/admin/users.php');
        }

        // Check duplicate username/email
        if ($action === 'add') {
            $chk = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND deleted_at IS NULL");
            $chk->execute([$username, $email]);
            if ($chk->fetch()) {
                setFlash('danger', 'Username or email already exists.');
                redirect(BASE_PATH . '/modules/admin/users.php');
            }
            if (empty($password)) {
                setFlash('danger', 'Password is required for new users.');
                redirect(BASE_PATH . '/modules/admin/users.php');
            }
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (name, username, email, role_id, status, password, created_at)
                                  VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $username, $email, $roleId, $status, $hashedPassword]);
            $newId = (int)$db->lastInsertId();
            logAudit('CREATE', 'users', $newId, [], ['name' => $name, 'username' => $username, 'email' => $email]);
            setFlash('success', 'User created successfully.');
        } else {
            $oldStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $oldStmt->execute([$userId]);
            $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET name=?, username=?, email=?, role_id=?, status=?, password=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$name, $username, $email, $roleId, $status, $hashedPassword, $userId]);
            } else {
                $stmt = $db->prepare("UPDATE users SET name=?, username=?, email=?, role_id=?, status=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$name, $username, $email, $roleId, $status, $userId]);
            }
            logAudit('UPDATE', 'users', $userId, $oldData, ['name' => $name, 'username' => $username, 'email' => $email]);
            setFlash('success', 'User updated successfully.');
        }
        redirect(BASE_PATH . '/modules/admin/users.php');
    }

    if ($action === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === currentUserId()) {
            setFlash('danger', 'You cannot deactivate your own account.');
            redirect(BASE_PATH . '/modules/admin/users.php');
        }
        $stmt = $db->prepare("UPDATE users SET status='inactive', updated_at=NOW() WHERE id=?");
        $stmt->execute([$userId]);
        logAudit('DELETE', 'users', $userId, [], ['status' => 'inactive']);
        setFlash('success', 'User deactivated successfully.');
        redirect(BASE_PATH . '/modules/admin/users.php');
    }
}

// Fetch users with role name
$users = $db->query("
    SELECT u.*, r.role_name
    FROM users u
    LEFT JOIN roles r ON r.id = u.role_id
    WHERE u.deleted_at IS NULL
    ORDER BY u.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch roles for dropdown
$roles = $db->query("SELECT id, role_name FROM roles ORDER BY role_name")->fetchAll(PDO::FETCH_ASSOC);

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
            <li class="breadcrumb-item active">User Management</li>
        </ol>
    </nav>

    <!-- Flash Messages -->
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><i class="bi bi-people-fill me-2 text-primary"></i>User Management</h4>
            <small class="text-muted">Manage system users and their roles</small>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openAddModal()">
            <i class="bi bi-plus-circle me-1"></i> Add User
        </button>
    </div>

    <!-- Users Table -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="usersTable" class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $i => $user): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-primary rounded-circle text-white d-flex align-items-center justify-content-center"
                                         style="width:36px;height:36px;font-size:0.85rem;flex-shrink:0;">
                                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                    </div>
                                    <span><?= htmlspecialchars($user['name']) ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($user['role_name'] ?? '-') ?></span></td>
                            <td>
                                <?php if ($user['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $user['last_login'] ? formatDate($user['last_login'], 'd/m/Y H:i') : '<span class="text-muted">Never</span>' ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary me-1"
                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($user)) ?>)"
                                        data-bs-toggle="modal" data-bs-target="#userModal"
                                        title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if ($user['id'] !== currentUserId()): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Deactivate this user?');">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Deactivate">
                                        <i class="bi bi-person-x"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="userForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="user_id" id="formUserId" value="">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="userModalLabel">
                        <i class="bi bi-person-plus me-2"></i>Add User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="fieldName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" id="fieldUsername" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" id="fieldEmail" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role_id" id="fieldRole" required>
                                <option value="">-- Select Role --</option>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" name="status" id="fieldStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password <span class="text-danger" id="passwordRequired">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="password" id="fieldPassword" autocomplete="new-password">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                    <i class="bi bi-eye" id="passwordEyeIcon"></i>
                                </button>
                            </div>
                            <div class="form-text" id="passwordHint">Leave blank to keep existing password (edit mode).</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Save User
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$extraJs = <<<JS
<script>
function openAddModal() {
    document.getElementById('userModalLabel').innerHTML = '<i class="bi bi-person-plus me-2"></i>Add New User';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formUserId').value = '';
    document.getElementById('userForm').reset();
    document.getElementById('passwordRequired').style.display = 'inline';
    document.getElementById('passwordHint').style.display = 'none';
}

function openEditModal(user) {
    document.getElementById('userModalLabel').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit User';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formUserId').value = user.id;
    document.getElementById('fieldName').value = user.name;
    document.getElementById('fieldUsername').value = user.username;
    document.getElementById('fieldEmail').value = user.email;
    document.getElementById('fieldRole').value = user.role_id;
    document.getElementById('fieldStatus').value = user.status;
    document.getElementById('fieldPassword').value = '';
    document.getElementById('passwordRequired').style.display = 'none';
    document.getElementById('passwordHint').style.display = 'block';
}

function togglePassword() {
    const f = document.getElementById('fieldPassword');
    const icon = document.getElementById('passwordEyeIcon');
    if (f.type === 'password') {
        f.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        f.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

$(document).ready(function () {
    $('#usersTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 25,
        dom: 'Bfrtip',
        buttons: ['copy', 'excel', 'pdf', 'print']
    });
});
</script>
JS;
require_once __DIR__ . '/../../templates/footer.php';
?>
