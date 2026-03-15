<?php
$pageTitle = 'Staff List';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

$pdo = db();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/modules/staff/staff_list.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id             = (int)($_POST['id'] ?? 0);
        $staff_code     = sanitize($_POST['staff_code'] ?? '');
        $staff_name     = sanitize($_POST['staff_name'] ?? '');
        $designation    = sanitize($_POST['designation'] ?? '');
        $phone          = sanitize($_POST['phone'] ?? '');
        $whatsapp       = sanitize($_POST['whatsapp'] ?? '');
        $email          = sanitize($_POST['email'] ?? '');
        $address        = sanitize($_POST['address'] ?? '');
        $joining_date   = sanitize($_POST['joining_date'] ?? '');
        $basic_salary   = (float)($_POST['basic_salary'] ?? 0);
        $allowance      = (float)($_POST['allowance'] ?? 0);
        $bank_name      = sanitize($_POST['bank_name'] ?? '');
        $bank_account   = sanitize($_POST['bank_account'] ?? '');
        $bank_ifsc      = sanitize($_POST['bank_ifsc'] ?? '');
        $status         = sanitize($_POST['status'] ?? 'active');
        $notes          = sanitize($_POST['notes'] ?? '');

        if (empty($staff_name)) {
            setFlash('danger', 'Staff name is required.');
            redirect(BASE_PATH . '/modules/staff/staff_list.php');
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE staff SET staff_code=?, staff_name=?, designation=?, phone=?, whatsapp=?, email=?, address=?, joining_date=?, basic_salary=?, allowance=?, bank_name=?, bank_account=?, bank_ifsc=?, status=?, notes=?, updated_at=NOW() WHERE id=? AND deleted_at IS NULL");
            $stmt->execute([$staff_code, $staff_name, $designation, $phone, $whatsapp, $email, $address, $joining_date ?: null, $basic_salary, $allowance, $bank_name, $bank_account, $bank_ifsc, $status, $notes, $id]);
            setFlash('success', 'Staff updated successfully.');
        } else {
            if (empty($staff_code)) {
                $staff_code = generateNumber('STF', 'staff', 'staff_code');
            }
            $stmt = $pdo->prepare("INSERT INTO staff (staff_code, staff_name, designation, phone, whatsapp, email, address, joining_date, basic_salary, allowance, bank_name, bank_account, bank_ifsc, status, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
            $stmt->execute([$staff_code, $staff_name, $designation, $phone, $whatsapp, $email, $address, $joining_date ?: null, $basic_salary, $allowance, $bank_name, $bank_account, $bank_ifsc, $status, $notes]);
            setFlash('success', 'Staff added successfully.');
        }
        redirect(BASE_PATH . '/modules/staff/staff_list.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE staff SET deleted_at=NOW() WHERE id=?")->execute([$id]);
            setFlash('success', 'Staff deleted successfully.');
        }
        redirect(BASE_PATH . '/modules/staff/staff_list.php');
    }
}

// Fetch all active staff
$stmt = $pdo->query("SELECT * FROM staff WHERE deleted_at IS NULL ORDER BY staff_name");
$staffList = $stmt->fetchAll();

// Summary
$total = count($staffList);
$active = 0; $inactive = 0; $totalSalary = 0;
foreach ($staffList as $s) {
    if ($s['status'] === 'active') { $active++; $totalSalary += (float)$s['basic_salary'] + (float)$s['allowance']; }
    else $inactive++;
}

$designations = ['imam','khatib','muazzin','teacher','office_staff','cleaner','security','other'];

require_once __DIR__ . '/../../templates/header.php';
?>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><i class="bi bi-person-badge me-2 text-primary"></i>Staff List</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Home</a></li>
            <li class="breadcrumb-item">Staff</li>
            <li class="breadcrumb-item active">Staff List</li>
        </ol></nav>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#staffModal" onclick="resetForm()">
        <i class="bi bi-person-plus me-1"></i>Add Staff
    </button>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-primary"><?= $total ?></div>
                <div class="small text-muted">Total Staff</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-success"><?= $active ?></div>
                <div class="small text-muted">Active</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-secondary"><?= $inactive ?></div>
                <div class="small text-muted">Inactive</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-info"><?= formatCurrency($totalSalary) ?></div>
                <div class="small text-muted">Monthly Payroll</div>
            </div>
        </div>
    </div>
</div>

<!-- Staff Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>All Staff Members</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="staffTable" class="table table-hover align-middle small">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Designation</th>
                        <th>Phone</th>
                        <th>Joining Date</th>
                        <th>Basic Salary</th>
                        <th>Allowance</th>
                        <th>Net</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($staffList as $i => $s): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($s['staff_code']) ?></span></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($s['staff_name']) ?></div>
                        <?php if ($s['email']): ?><small class="text-muted"><?= htmlspecialchars($s['email']) ?></small><?php endif; ?>
                    </td>
                    <td><span class="text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $s['designation'])) ?></span></td>
                    <td>
                        <?= htmlspecialchars($s['phone']) ?>
                        <?php if ($s['whatsapp']): ?>
                        <br><a href="https://wa.me/<?= preg_replace('/\D/', '', $s['whatsapp']) ?>" target="_blank" class="small text-success"><i class="bi bi-whatsapp"></i></a>
                        <?php endif; ?>
                    </td>
                    <td><?= $s['joining_date'] ? formatDate($s['joining_date']) : '-' ?></td>
                    <td><?= formatCurrency((float)$s['basic_salary']) ?></td>
                    <td><?= formatCurrency((float)$s['allowance']) ?></td>
                    <td class="fw-semibold"><?= formatCurrency((float)$s['basic_salary'] + (float)$s['allowance']) ?></td>
                    <td>
                        <?php $sb = $s['status'] === 'active' ? 'success' : 'secondary'; ?>
                        <span class="badge bg-<?= $sb ?>"><?= ucfirst($s['status']) ?></span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="editStaff(<?= htmlspecialchars(json_encode($s)) ?>)" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <a href="<?= BASE_PATH ?>/modules/salary/salary_sheet.php?staff_id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-info" title="Salary History">
                            <i class="bi bi-clock-history"></i>
                        </a>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this staff member?')">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="staffForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="stf_id" value="0">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="staffModalTitle"><i class="bi bi-person-plus me-2"></i>Add Staff</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3" id="staffTabs">
                        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-basic">Basic Info</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-salary">Salary</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-bank">Bank Details</a></li>
                    </ul>
                    <div class="tab-content">
                        <!-- Basic Info Tab -->
                        <div class="tab-pane fade show active" id="tab-basic">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Staff Code</label>
                                    <input type="text" name="staff_code" id="stf_code" class="form-control" placeholder="Auto-generated">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Staff Name <span class="text-danger">*</span></label>
                                    <input type="text" name="staff_name" id="stf_name" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Designation</label>
                                    <select name="designation" id="stf_desig" class="form-select">
                                        <?php foreach ($designations as $d): ?>
                                        <option value="<?= $d ?>"><?= ucwords(str_replace('_', ' ', $d)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Joining Date</label>
                                    <input type="date" name="joining_date" id="stf_joining" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Status</label>
                                    <select name="status" id="stf_status" class="form-select">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="resigned">Resigned</option>
                                        <option value="terminated">Terminated</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Phone</label>
                                    <input type="text" name="phone" id="stf_phone" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">WhatsApp</label>
                                    <input type="text" name="whatsapp" id="stf_whatsapp" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Email</label>
                                    <input type="email" name="email" id="stf_email" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Address</label>
                                    <textarea name="address" id="stf_address" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Notes</label>
                                    <textarea name="notes" id="stf_notes" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                        <!-- Salary Tab -->
                        <div class="tab-pane fade" id="tab-salary">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Basic Salary (₹)</label>
                                    <input type="number" name="basic_salary" id="stf_basic" class="form-control" step="0.01" min="0" value="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Allowance (₹)</label>
                                    <input type="number" name="allowance" id="stf_allowance" class="form-control" step="0.01" min="0" value="0">
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-info small">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Deductions are recorded monthly in the Salary Sheet module.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Bank Details Tab -->
                        <div class="tab-pane fade" id="tab-bank">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Bank Name</label>
                                    <input type="text" name="bank_name" id="stf_bank" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Account Number</label>
                                    <input type="text" name="bank_account" id="stf_acc" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">IFSC Code</label>
                                    <input type="text" name="bank_ifsc" id="stf_ifsc" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Staff</button>
                </div>
            </div>
        </form>
    </div>
</div>

</div></div></div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
<script>
$('#staffTable').DataTable({
    order: [[1, 'asc']],
    pageLength: 25,
    language: { search: 'Search staff:' }
});

function resetForm() {
    document.getElementById('staffModalTitle').innerHTML = '<i class="bi bi-person-plus me-2"></i>Add Staff';
    document.getElementById('stf_id').value = '0';
    ['stf_code','stf_name','stf_phone','stf_whatsapp','stf_email','stf_address','stf_notes','stf_joining','stf_bank','stf_acc','stf_ifsc'].forEach(id => {
        document.getElementById(id).value = '';
    });
    document.getElementById('stf_desig').value = 'imam';
    document.getElementById('stf_status').value = 'active';
    document.getElementById('stf_basic').value = '0';
    document.getElementById('stf_allowance').value = '0';
    // Switch to first tab
    new bootstrap.Tab(document.querySelector('#staffTabs .nav-link')).show();
}

function editStaff(s) {
    document.getElementById('staffModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Staff';
    document.getElementById('stf_id').value       = s.id;
    document.getElementById('stf_code').value     = s.staff_code || '';
    document.getElementById('stf_name').value     = s.staff_name || '';
    document.getElementById('stf_desig').value    = s.designation || 'imam';
    document.getElementById('stf_phone').value    = s.phone || '';
    document.getElementById('stf_whatsapp').value = s.whatsapp || '';
    document.getElementById('stf_email').value    = s.email || '';
    document.getElementById('stf_address').value  = s.address || '';
    document.getElementById('stf_joining').value  = s.joining_date || '';
    document.getElementById('stf_basic').value    = s.basic_salary || '0';
    document.getElementById('stf_allowance').value= s.allowance || '0';
    document.getElementById('stf_bank').value     = s.bank_name || '';
    document.getElementById('stf_acc').value      = s.bank_account || '';
    document.getElementById('stf_ifsc').value     = s.bank_ifsc || '';
    document.getElementById('stf_status').value   = s.status || 'active';
    document.getElementById('stf_notes').value    = s.notes || '';
    new bootstrap.Tab(document.querySelector('#staffTabs .nav-link')).show();
    var modal = new bootstrap.Modal(document.getElementById('staffModal'));
    modal.show();
}
</script>
