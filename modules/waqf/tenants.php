<?php
$pageTitle = 'Waqf Tenants';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

$pdo = db();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/modules/waqf/tenants.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id               = (int)($_POST['id'] ?? 0);
        $tenant_code      = sanitize($_POST['tenant_code'] ?? '');
        $tenant_name      = sanitize($_POST['tenant_name'] ?? '');
        $phone            = sanitize($_POST['phone'] ?? '');
        $whatsapp         = sanitize($_POST['whatsapp'] ?? '');
        $email            = sanitize($_POST['email'] ?? '');
        $address          = sanitize($_POST['address'] ?? '');
        $property_id      = (int)($_POST['property_id'] ?? 0);
        $shop_number      = sanitize($_POST['shop_number'] ?? '');
        $rent_amount      = (float)($_POST['rent_amount'] ?? 0);
        $advance_deposit  = (float)($_POST['advance_deposit'] ?? 0);
        $due_day          = (int)($_POST['due_day'] ?? 1);
        $agreement_start  = sanitize($_POST['agreement_start'] ?? '');
        $agreement_end    = sanitize($_POST['agreement_end'] ?? '');
        $status           = sanitize($_POST['status'] ?? 'active');
        $notes            = sanitize($_POST['notes'] ?? '');

        if (empty($tenant_name)) {
            setFlash('danger', 'Tenant name is required.');
            redirect(BASE_PATH . '/modules/waqf/tenants.php');
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE waqf_tenants SET tenant_code=?, tenant_name=?, phone=?, whatsapp=?, email=?, address=?, property_id=?, shop_number=?, rent_amount=?, advance_deposit=?, due_day=?, agreement_start=?, agreement_end=?, status=?, notes=?, updated_at=NOW() WHERE id=? AND deleted_at IS NULL");
            $stmt->execute([$tenant_code, $tenant_name, $phone, $whatsapp, $email, $address, $property_id ?: null, $shop_number, $rent_amount, $advance_deposit, $due_day, $agreement_start ?: null, $agreement_end ?: null, $status, $notes, $id]);
            setFlash('success', 'Tenant updated successfully.');
        } else {
            if (empty($tenant_code)) {
                $tenant_code = generateNumber('TNT', 'waqf_tenants', 'tenant_code');
            }
            $stmt = $pdo->prepare("INSERT INTO waqf_tenants (tenant_code, tenant_name, phone, whatsapp, email, address, property_id, shop_number, rent_amount, advance_deposit, due_day, agreement_start, agreement_end, status, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
            $stmt->execute([$tenant_code, $tenant_name, $phone, $whatsapp, $email, $address, $property_id ?: null, $shop_number, $rent_amount, $advance_deposit, $due_day, $agreement_start ?: null, $agreement_end ?: null, $status, $notes]);
            setFlash('success', 'Tenant added successfully.');
        }
        redirect(BASE_PATH . '/modules/waqf/tenants.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE waqf_tenants SET deleted_at=NOW() WHERE id=?")->execute([$id]);
            setFlash('success', 'Tenant deleted successfully.');
        }
        redirect(BASE_PATH . '/modules/waqf/tenants.php');
    }
}

// Fetch tenants with property name
$stmt = $pdo->query("SELECT t.*, p.property_name, p.property_code FROM waqf_tenants t LEFT JOIN waqf_properties p ON p.id = t.property_id WHERE t.deleted_at IS NULL ORDER BY t.created_at DESC");
$tenants = $stmt->fetchAll();

// Fetch properties for dropdown
$propStmt = $pdo->query("SELECT id, property_code, property_name FROM waqf_properties WHERE deleted_at IS NULL ORDER BY property_name");
$waqfProperties = $propStmt->fetchAll();

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
        <h4 class="mb-0"><i class="bi bi-people me-2 text-primary"></i>Waqf Tenants</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Home</a></li>
            <li class="breadcrumb-item">Waqf</li>
            <li class="breadcrumb-item active">Tenants</li>
        </ol></nav>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tenantModal" onclick="resetForm()">
        <i class="bi bi-plus-circle me-1"></i>Add Tenant
    </button>
</div>

<!-- Tenants Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>All Tenants</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tenantsTable" class="table table-hover align-middle small">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Tenant Name</th>
                        <th>Phone</th>
                        <th>Property</th>
                        <th>Shop No.</th>
                        <th>Rent Amount</th>
                        <th>Due Day</th>
                        <th>Agreement</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tenants as $i => $t): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($t['tenant_code']) ?></span></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($t['tenant_name']) ?></div>
                        <?php if ($t['email']): ?><small class="text-muted"><?= htmlspecialchars($t['email']) ?></small><?php endif; ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($t['phone']) ?>
                        <?php if ($t['whatsapp']): ?>
                        <br><a href="https://wa.me/<?= preg_replace('/\D/', '', $t['whatsapp']) ?>" target="_blank" class="small text-success"><i class="bi bi-whatsapp"></i> WA</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($t['property_name']): ?>
                        <div><?= htmlspecialchars($t['property_name']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($t['property_code']) ?></small>
                        <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($t['shop_number']) ?: '-' ?></td>
                    <td class="fw-semibold"><?= formatCurrency((float)$t['rent_amount']) ?></td>
                    <td><?= $t['due_day'] ?><?= in_array($t['due_day'], [1,21,31]) ? 'st' : (in_array($t['due_day'], [2,22]) ? 'nd' : (in_array($t['due_day'], [3,23]) ? 'rd' : 'th')) ?></td>
                    <td>
                        <?php if ($t['agreement_start'] && $t['agreement_end']): ?>
                        <small><?= formatDate($t['agreement_start']) ?> -<br><?= formatDate($t['agreement_end']) ?></small>
                        <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $sMap = ['active'=>'success','inactive'=>'secondary','terminated'=>'danger','pending'=>'warning'];
                        $sb = $sMap[$t['status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?= $sb ?>"><?= ucfirst($t['status']) ?></span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="editTenant(<?= htmlspecialchars(json_encode($t)) ?>)" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this tenant?')">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
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
<div class="modal fade" id="tenantModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="tenantForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="ten_id" value="0">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="tenantModalTitle"><i class="bi bi-person-plus me-2"></i>Add Tenant</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tenant Code</label>
                            <input type="text" name="tenant_code" id="ten_code" class="form-control" placeholder="Auto-generated">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Tenant Name <span class="text-danger">*</span></label>
                            <input type="text" name="tenant_name" id="ten_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" id="ten_phone" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">WhatsApp</label>
                            <input type="text" name="whatsapp" id="ten_whatsapp" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" id="ten_email" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address</label>
                            <textarea name="address" id="ten_address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Property</label>
                            <select name="property_id" id="ten_property" class="form-select">
                                <option value="">-- Select Property --</option>
                                <?php foreach ($waqfProperties as $wp): ?>
                                <option value="<?= $wp['id'] ?>">[<?= htmlspecialchars($wp['property_code']) ?>] <?= htmlspecialchars($wp['property_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Shop / Unit No.</label>
                            <input type="text" name="shop_number" id="ten_shop" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" id="ten_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="terminated">Terminated</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Rent Amount (₹)</label>
                            <input type="number" name="rent_amount" id="ten_rent" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Advance Deposit (₹)</label>
                            <input type="number" name="advance_deposit" id="ten_deposit" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Due Day of Month</label>
                            <input type="number" name="due_day" id="ten_due_day" class="form-control" min="1" max="31" value="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Agreement Start</label>
                            <input type="date" name="agreement_start" id="ten_ag_start" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Agreement End</label>
                            <input type="date" name="agreement_end" id="ten_ag_end" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" id="ten_notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Tenant</button>
                </div>
            </div>
        </form>
    </div>
</div>

</div></div></div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
<script>
$('#tenantsTable').DataTable({
    order: [[0, 'asc']],
    pageLength: 25,
    language: { search: 'Search tenants:' }
});

function resetForm() {
    document.getElementById('tenantModalTitle').innerHTML = '<i class="bi bi-person-plus me-2"></i>Add Tenant';
    document.getElementById('ten_id').value = '0';
    ['ten_code','ten_name','ten_phone','ten_whatsapp','ten_email','ten_address','ten_shop','ten_notes','ten_ag_start','ten_ag_end'].forEach(id => {
        document.getElementById(id).value = '';
    });
    document.getElementById('ten_property').value = '';
    document.getElementById('ten_status').value = 'active';
    document.getElementById('ten_rent').value = '0';
    document.getElementById('ten_deposit').value = '0';
    document.getElementById('ten_due_day').value = '1';
}

function editTenant(t) {
    document.getElementById('tenantModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Tenant';
    document.getElementById('ten_id').value         = t.id;
    document.getElementById('ten_code').value       = t.tenant_code || '';
    document.getElementById('ten_name').value       = t.tenant_name || '';
    document.getElementById('ten_phone').value      = t.phone || '';
    document.getElementById('ten_whatsapp').value   = t.whatsapp || '';
    document.getElementById('ten_email').value      = t.email || '';
    document.getElementById('ten_address').value    = t.address || '';
    document.getElementById('ten_property').value   = t.property_id || '';
    document.getElementById('ten_shop').value       = t.shop_number || '';
    document.getElementById('ten_status').value     = t.status || 'active';
    document.getElementById('ten_rent').value       = t.rent_amount || '0';
    document.getElementById('ten_deposit').value    = t.advance_deposit || '0';
    document.getElementById('ten_due_day').value    = t.due_day || '1';
    document.getElementById('ten_ag_start').value  = t.agreement_start || '';
    document.getElementById('ten_ag_end').value    = t.agreement_end || '';
    document.getElementById('ten_notes').value      = t.notes || '';
    var modal = new bootstrap.Modal(document.getElementById('tenantModal'));
    modal.show();
}
</script>
