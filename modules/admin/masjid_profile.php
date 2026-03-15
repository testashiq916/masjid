<?php
$pageTitle = 'Masjid Profile';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();
requireRole([ROLE_ADMIN]);

$db = db();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/modules/admin/masjid_profile.php');
    }

    $fields = [
        'masjid_name'         => sanitize($_POST['masjid_name'] ?? ''),
        'registration_number' => sanitize($_POST['registration_number'] ?? ''),
        'waqf_registration'   => sanitize($_POST['waqf_registration'] ?? ''),
        'address'             => sanitize($_POST['address'] ?? ''),
        'city'                => sanitize($_POST['city'] ?? ''),
        'state'               => sanitize($_POST['state'] ?? ''),
        'pincode'             => sanitize($_POST['pincode'] ?? ''),
        'phone'               => sanitize($_POST['phone'] ?? ''),
        'email'               => sanitize($_POST['email'] ?? ''),
        'website'             => sanitize($_POST['website'] ?? ''),
        'committee_period'    => sanitize($_POST['committee_period'] ?? ''),
        'established_year'    => sanitize($_POST['established_year'] ?? ''),
        'bank_name'           => sanitize($_POST['bank_name'] ?? ''),
        'bank_account'        => sanitize($_POST['bank_account'] ?? ''),
        'bank_ifsc'           => sanitize($_POST['bank_ifsc'] ?? ''),
        'bank_branch'         => sanitize($_POST['bank_branch'] ?? ''),
    ];

    // Handle logo upload
    $logoPath = sanitize($_POST['existing_logo'] ?? '');
    if (!empty($_FILES['logo']['name'])) {
        $allowed    = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType   = mime_content_type($_FILES['logo']['tmp_name']);
        if (!in_array($fileType, $allowed)) {
            setFlash('danger', 'Invalid image type. Only JPG, PNG, GIF, WEBP allowed.');
            redirect(BASE_PATH . '/modules/admin/masjid_profile.php');
        }
        $uploadDir = dirname(__DIR__, 2) . '/assets/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $ext      = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $filename = 'masjid_logo_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $filename)) {
            $logoPath = 'assets/uploads/' . $filename;
        } else {
            setFlash('danger', 'Failed to upload logo. Check folder permissions.');
            redirect(BASE_PATH . '/modules/admin/masjid_profile.php');
        }
    }
    $fields['logo'] = $logoPath;

    // Check if record exists
    $existing = $db->query("SELECT id FROM masjid_profile LIMIT 1")->fetch();

    if ($existing) {
        $setClauses = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $stmt = $db->prepare("UPDATE masjid_profile SET $setClauses, updated_at=NOW() WHERE id = ?");
        $stmt->execute([...array_values($fields), $existing['id']]);
        logAudit('UPDATE', 'masjid_profile', $existing['id'], [], $fields);
    } else {
        $cols = implode(', ', array_keys($fields));
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $stmt = $db->prepare("INSERT INTO masjid_profile ($cols, created_at) VALUES ($placeholders, NOW())");
        $stmt->execute(array_values($fields));
        logAudit('CREATE', 'masjid_profile', (int)$db->lastInsertId(), [], $fields);
    }

    setFlash('success', 'Masjid profile updated successfully.');
    redirect(BASE_PATH . '/modules/admin/masjid_profile.php');
}

// Load existing profile
$profile = $db->query("SELECT * FROM masjid_profile LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];

$v = function(string $key) use ($profile): string {
    return htmlspecialchars($profile[$key] ?? '');
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
            <li class="breadcrumb-item active">Masjid Profile</li>
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
            <h4 class="mb-0"><i class="bi bi-building-fill me-2 text-primary"></i>Masjid Profile</h4>
            <small class="text-muted">Update your masjid's details and branding</small>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
        <input type="hidden" name="existing_logo" value="<?= $v('logo') ?>">

        <!-- Logo & Basic Info -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="bi bi-image me-2"></i>Logo & Basic Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-start">
                    <div class="col-md-3 text-center">
                        <?php if (!empty($profile['logo'])): ?>
                            <img src="<?= BASE_PATH . '/' . $v('logo') ?>" alt="Masjid Logo"
                                 class="img-thumbnail mb-2" style="max-height:150px;max-width:200px;">
                        <?php else: ?>
                            <div class="border rounded d-flex align-items-center justify-content-center bg-light mb-2"
                                 style="height:120px;">
                                <i class="bi bi-building fs-1 text-muted"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <label class="form-label fw-semibold">Upload Logo</label>
                            <input type="file" class="form-control" name="logo" accept="image/*">
                            <div class="form-text">JPG, PNG, GIF, WEBP. Max 2MB.</div>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Masjid Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="masjid_name" value="<?= $v('masjid_name') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Registration Number</label>
                                <input type="text" class="form-control" name="registration_number" value="<?= $v('registration_number') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Waqf Registration</label>
                                <input type="text" class="form-control" name="waqf_registration" value="<?= $v('waqf_registration') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Established Year</label>
                                <input type="number" class="form-control" name="established_year" value="<?= $v('established_year') ?>" min="1800" max="<?= date('Y') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Committee Period</label>
                                <input type="text" class="form-control" name="committee_period" value="<?= $v('committee_period') ?>" placeholder="e.g. 2023-2026">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact & Address -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-secondary text-white">
                <h6 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Address & Contact</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Address</label>
                        <textarea class="form-control" name="address" rows="2"><?= $v('address') ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">City</label>
                        <input type="text" class="form-control" name="city" value="<?= $v('city') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">State</label>
                        <input type="text" class="form-control" name="state" value="<?= $v('state') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Pincode</label>
                        <input type="text" class="form-control" name="pincode" value="<?= $v('pincode') ?>" maxlength="10">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Phone</label>
                        <input type="text" class="form-control" name="phone" value="<?= $v('phone') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" class="form-control" name="email" value="<?= $v('email') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Website</label>
                        <input type="url" class="form-control" name="website" value="<?= $v('website') ?>" placeholder="https://">
                    </div>
                </div>
            </div>
        </div>

        <!-- Bank Details -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="bi bi-bank me-2"></i>Bank Details</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Bank Name</label>
                        <input type="text" class="form-control" name="bank_name" value="<?= $v('bank_name') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Account Number</label>
                        <input type="text" class="form-control" name="bank_account" value="<?= $v('bank_account') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">IFSC Code</label>
                        <input type="text" class="form-control" name="bank_ifsc" value="<?= $v('bank_ifsc') ?>" style="text-transform:uppercase;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Branch</label>
                        <input type="text" class="form-control" name="bank_branch" value="<?= $v('bank_branch') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i> Save Profile
            </button>
            <a href="<?= BASE_PATH ?>/dashboard.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>

</div>
</div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
