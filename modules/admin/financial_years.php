<?php
$pageTitle = 'Financial Years';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();
requireRole([ROLE_ADMIN]);

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/modules/admin/financial_years.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $yearLabel = sanitize($_POST['year_label'] ?? '');
        $startDate = sanitize($_POST['start_date'] ?? '');
        $endDate   = sanitize($_POST['end_date'] ?? '');
        $fyId      = (int)($_POST['fy_id'] ?? 0);

        if (empty($yearLabel) || empty($startDate) || empty($endDate)) {
            setFlash('danger', 'All fields are required.');
            redirect(BASE_PATH . '/modules/admin/financial_years.php');
        }
        if ($startDate >= $endDate) {
            setFlash('danger', 'End date must be after start date.');
            redirect(BASE_PATH . '/modules/admin/financial_years.php');
        }

        if ($action === 'add') {
            $stmt = $db->prepare("INSERT INTO financial_years (year_label, start_date, end_date, status, created_at)
                                  VALUES (?, ?, ?, 'inactive', NOW())");
            $stmt->execute([$yearLabel, $startDate, $endDate]);
            $newId = (int)$db->lastInsertId();
            logAudit('CREATE', 'financial_years', $newId, [], ['year_label' => $yearLabel, 'start_date' => $startDate, 'end_date' => $endDate]);
            setFlash('success', 'Financial year added successfully.');
        } else {
            $old = $db->prepare("SELECT * FROM financial_years WHERE id = ?");
            $old->execute([$fyId]);
            $oldData = $old->fetch(PDO::FETCH_ASSOC) ?: [];
            $stmt = $db->prepare("UPDATE financial_years SET year_label=?, start_date=?, end_date=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$yearLabel, $startDate, $endDate, $fyId]);
            logAudit('UPDATE', 'financial_years', $fyId, $oldData, ['year_label' => $yearLabel]);
            setFlash('success', 'Financial year updated successfully.');
        }
        redirect(BASE_PATH . '/modules/admin/financial_years.php');
    }

    if ($action === 'activate') {
        $fyId = (int)($_POST['fy_id'] ?? 0);
        // Deactivate all first
        $db->exec("UPDATE financial_years SET status='inactive'");
        $stmt = $db->prepare("UPDATE financial_years SET status='active', updated_at=NOW() WHERE id=?");
        $stmt->execute([$fyId]);
        logAudit('UPDATE', 'financial_years', $fyId, [], ['status' => 'active']);
        setFlash('success', 'Financial year activated.');
        redirect(BASE_PATH . '/modules/admin/financial_years.php');
    }

    if ($action === 'close') {
        $fyId = (int)($_POST['fy_id'] ?? 0);
        $stmt = $db->prepare("UPDATE financial_years SET status='closed', updated_at=NOW() WHERE id=?");
        $stmt->execute([$fyId]);
        logAudit('UPDATE', 'financial_years', $fyId, [], ['status' => 'closed']);
        setFlash('success', 'Financial year closed.');
        redirect(BASE_PATH . '/modules/admin/financial_years.php');
    }

    if ($action === 'delete') {
        $fyId = (int)($_POST['fy_id'] ?? 0);
        // Only allow delete if status is inactive
        $check = $db->prepare("SELECT status FROM financial_years WHERE id = ?");
        $check->execute([$fyId]);
        $fy = $check->fetch();
        if ($fy && $fy['status'] === 'active') {
            setFlash('danger', 'Cannot delete an active financial year. Close or deactivate it first.');
        } else {
            $stmt = $db->prepare("UPDATE financial_years SET deleted_at=NOW() WHERE id=?");
            $stmt->execute([$fyId]);
            logAudit('DELETE', 'financial_years', $fyId, [], []);
            setFlash('success', 'Financial year deleted.');
        }
        redirect(BASE_PATH . '/modules/admin/financial_years.php');
    }
}

$financialYears = $db->query("
    SELECT * FROM financial_years
    WHERE deleted_at IS NULL
    ORDER BY start_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

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
            <li class="breadcrumb-item active">Financial Years</li>
        </ol>
    </nav>

    <!-- Flash Messages -->
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><i class="bi bi-calendar-range me-2 text-primary"></i>Financial Years</h4>
            <small class="text-muted">Manage accounting periods</small>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#fyModal" onclick="openAddFyModal()">
            <i class="bi bi-plus-circle me-1"></i> Add Financial Year
        </button>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="fyTable" class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Year Label</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($financialYears as $i => $fy): ?>
                        <tr class="<?= $fy['status'] === 'active' ? 'table-success' : ($fy['status'] === 'closed' ? 'table-secondary' : '') ?>">
                            <td><?= $i + 1 ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($fy['year_label']) ?></td>
                            <td><?= formatDate($fy['start_date']) ?></td>
                            <td><?= formatDate($fy['end_date']) ?></td>
                            <td>
                                <?php if ($fy['status'] === 'active'): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                                <?php elseif ($fy['status'] === 'closed'): ?>
                                    <span class="badge bg-danger"><i class="bi bi-lock me-1"></i>Closed</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><i class="bi bi-pause-circle me-1"></i>Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= formatDate($fy['created_at']) ?></td>
                            <td>
                                <?php if ($fy['status'] !== 'active' && $fy['status'] !== 'closed'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="fy_id" value="<?= $fy['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success me-1" title="Activate"
                                            onclick="return confirm('Activate this financial year? Current active year will be deactivated.')">
                                        <i class="bi bi-play-circle"></i> Activate
                                    </button>
                                </form>
                                <?php endif; ?>

                                <?php if ($fy['status'] === 'active'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                                    <input type="hidden" name="action" value="close">
                                    <input type="hidden" name="fy_id" value="<?= $fy['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-warning me-1" title="Close Year"
                                            onclick="return confirm('Close this financial year? This cannot be undone easily.')">
                                        <i class="bi bi-lock"></i> Close
                                    </button>
                                </form>
                                <?php endif; ?>

                                <?php if ($fy['status'] !== 'active'): ?>
                                <button class="btn btn-sm btn-outline-primary me-1"
                                        onclick="openEditFyModal(<?= htmlspecialchars(json_encode($fy)) ?>)"
                                        data-bs-toggle="modal" data-bs-target="#fyModal"
                                        title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="fy_id" value="<?= $fy['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"
                                            onclick="return confirm('Delete this financial year?')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($financialYears)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No financial years found. Add one to get started.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
</div>

<!-- Add/Edit Financial Year Modal -->
<div class="modal fade" id="fyModal" tabindex="-1" aria-labelledby="fyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="fyForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="action" id="fyAction" value="add">
            <input type="hidden" name="fy_id" id="fyId" value="">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="fyModalLabel">
                        <i class="bi bi-calendar-plus me-2"></i>Add Financial Year
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Year Label <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="year_label" id="fyYearLabel"
                               placeholder="e.g. 2024-25" required>
                        <div class="form-text">A descriptive label for this financial year.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="start_date" id="fyStartDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="end_date" id="fyEndDate" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Save
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$extraJs = <<<JS
<script>
function openAddFyModal() {
    document.getElementById('fyModalLabel').innerHTML = '<i class="bi bi-calendar-plus me-2"></i>Add Financial Year';
    document.getElementById('fyAction').value = 'add';
    document.getElementById('fyId').value = '';
    document.getElementById('fyForm').reset();
}

function openEditFyModal(fy) {
    document.getElementById('fyModalLabel').innerHTML = '<i class="bi bi-calendar-check me-2"></i>Edit Financial Year';
    document.getElementById('fyAction').value = 'edit';
    document.getElementById('fyId').value = fy.id;
    document.getElementById('fyYearLabel').value = fy.year_label;
    document.getElementById('fyStartDate').value = fy.start_date;
    document.getElementById('fyEndDate').value = fy.end_date;
}

$(document).ready(function () {
    $('#fyTable').DataTable({ order: [[2, 'desc']], pageLength: 25 });
});
</script>
JS;
require_once __DIR__ . '/../../templates/footer.php';
?>
