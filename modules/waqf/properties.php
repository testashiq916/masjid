<?php
$pageTitle = 'Waqf Properties';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

$pdo = db();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/modules/waqf/properties.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id               = (int)($_POST['id'] ?? 0);
        $property_code    = sanitize($_POST['property_code'] ?? '');
        $property_name    = sanitize($_POST['property_name'] ?? '');
        $property_type    = sanitize($_POST['property_type'] ?? '');
        $survey_number    = sanitize($_POST['survey_number'] ?? '');
        $location         = sanitize($_POST['location'] ?? '');
        $city             = sanitize($_POST['city'] ?? '');
        $ownership_details = sanitize($_POST['ownership_details'] ?? '');
        $estimated_value  = (float)($_POST['estimated_value'] ?? 0);
        $monthly_income   = (float)($_POST['monthly_income'] ?? 0);
        $status           = sanitize($_POST['status'] ?? 'vacant');
        $notes            = sanitize($_POST['notes'] ?? '');

        if (empty($property_name)) {
            setFlash('danger', 'Property name is required.');
            redirect(BASE_PATH . '/modules/waqf/properties.php');
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE waqf_properties SET property_code=?, property_name=?, property_type=?, survey_number=?, location=?, city=?, ownership_details=?, estimated_value=?, monthly_income=?, status=?, notes=?, updated_at=NOW() WHERE id=? AND deleted_at IS NULL");
            $stmt->execute([$property_code, $property_name, $property_type, $survey_number, $location, $city, $ownership_details, $estimated_value, $monthly_income, $status, $notes, $id]);
            setFlash('success', 'Property updated successfully.');
        } else {
            // Auto-generate property code if empty
            if (empty($property_code)) {
                $property_code = generateNumber('WQF', 'waqf_properties', 'property_code');
            }
            $stmt = $pdo->prepare("INSERT INTO waqf_properties (property_code, property_name, property_type, survey_number, location, city, ownership_details, estimated_value, monthly_income, status, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
            $stmt->execute([$property_code, $property_name, $property_type, $survey_number, $location, $city, $ownership_details, $estimated_value, $monthly_income, $status, $notes]);
            setFlash('success', 'Property added successfully.');
        }
        redirect(BASE_PATH . '/modules/waqf/properties.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE waqf_properties SET deleted_at=NOW() WHERE id=?")->execute([$id]);
            setFlash('success', 'Property deleted successfully.');
        }
        redirect(BASE_PATH . '/modules/waqf/properties.php');
    }
}

// Fetch all active properties
$stmt = $pdo->query("SELECT * FROM waqf_properties WHERE deleted_at IS NULL ORDER BY created_at DESC");
$properties = $stmt->fetchAll();

// Summary counts
$totalCount   = count($properties);
$occupiedCount = 0; $vacantCount = 0; $maintenanceCount = 0; $inactiveCount = 0;
$totalValue = 0; $totalIncome = 0;
foreach ($properties as $p) {
    if ($p['status'] === 'occupied') $occupiedCount++;
    elseif ($p['status'] === 'vacant') $vacantCount++;
    elseif ($p['status'] === 'under_maintenance') $maintenanceCount++;
    else $inactiveCount++;
    $totalValue  += (float)$p['estimated_value'];
    $totalIncome += (float)$p['monthly_income'];
}

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
        <h4 class="mb-0"><i class="bi bi-building me-2 text-primary"></i>Waqf Properties</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Home</a></li>
            <li class="breadcrumb-item">Waqf</li>
            <li class="breadcrumb-item active">Properties</li>
        </ol></nav>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#propertyModal" onclick="resetForm()">
        <i class="bi bi-plus-circle me-1"></i>Add Property
    </button>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-primary"><?= $totalCount ?></div>
                <div class="small text-muted">Total Properties</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-success"><?= $occupiedCount ?></div>
                <div class="small text-muted">Occupied</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-info"><?= $vacantCount ?></div>
                <div class="small text-muted">Vacant</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-warning"><?= $maintenanceCount ?></div>
                <div class="small text-muted">Under Maintenance</div>
            </div>
        </div>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 rounded-circle p-3"><i class="bi bi-cash-stack fs-4 text-primary"></i></div>
                <div>
                    <div class="small text-muted">Total Estimated Value</div>
                    <div class="fw-bold fs-5"><?= formatCurrency($totalValue) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-success bg-opacity-10 rounded-circle p-3"><i class="bi bi-graph-up-arrow fs-4 text-success"></i></div>
                <div>
                    <div class="small text-muted">Total Monthly Income</div>
                    <div class="fw-bold fs-5"><?= formatCurrency($totalIncome) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Properties Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>All Properties</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="propertiesTable" class="table table-hover align-middle small">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Property Name</th>
                        <th>Type</th>
                        <th>City</th>
                        <th>Est. Value</th>
                        <th>Monthly Income</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($properties as $i => $p): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($p['property_code']) ?></span></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($p['property_name']) ?></div>
                        <?php if ($p['location']): ?><small class="text-muted"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($p['location']) ?></small><?php endif; ?>
                    </td>
                    <td><span class="text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $p['property_type'])) ?></span></td>
                    <td><?= htmlspecialchars($p['city']) ?></td>
                    <td><?= formatCurrency((float)$p['estimated_value']) ?></td>
                    <td><?= formatCurrency((float)$p['monthly_income']) ?></td>
                    <td>
                        <?php
                        $statusMap = [
                            'occupied'         => 'success',
                            'vacant'           => 'info',
                            'under_maintenance'=> 'warning',
                            'inactive'         => 'secondary',
                        ];
                        $badge = $statusMap[$p['status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?= $badge ?>"><?= ucwords(str_replace('_', ' ', $p['status'])) ?></span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="editProperty(<?= htmlspecialchars(json_encode($p)) ?>)" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this property?')">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
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
<div class="modal fade" id="propertyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="propertyForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="prop_id" value="0">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalTitle"><i class="bi bi-building me-2"></i>Add Property</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Property Code</label>
                            <input type="text" name="property_code" id="prop_code" class="form-control" placeholder="Auto-generated">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Property Name <span class="text-danger">*</span></label>
                            <input type="text" name="property_name" id="prop_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Property Type</label>
                            <select name="property_type" id="prop_type" class="form-select">
                                <option value="land">Land</option>
                                <option value="shop">Shop</option>
                                <option value="building">Building</option>
                                <option value="hall">Hall</option>
                                <option value="classroom">Classroom</option>
                                <option value="room">Room</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Survey Number</label>
                            <input type="text" name="survey_number" id="prop_survey" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" id="prop_status" class="form-select">
                                <option value="vacant">Vacant</option>
                                <option value="occupied">Occupied</option>
                                <option value="under_maintenance">Under Maintenance</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Location</label>
                            <input type="text" name="location" id="prop_location" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">City</label>
                            <input type="text" name="city" id="prop_city" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Ownership Details</label>
                            <textarea name="ownership_details" id="prop_ownership" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Estimated Value (₹)</label>
                            <input type="number" name="estimated_value" id="prop_value" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Monthly Income (₹)</label>
                            <input type="number" name="monthly_income" id="prop_income" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" id="prop_notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Property</button>
                </div>
            </div>
        </form>
    </div>
</div>

</div></div></div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
<script>
$('#propertiesTable').DataTable({
    order: [[0, 'asc']],
    pageLength: 25,
    language: { search: 'Search properties:' }
});

function resetForm() {
    document.getElementById('modalTitle').innerHTML = '<i class="bi bi-building me-2"></i>Add Property';
    document.getElementById('prop_id').value = '0';
    document.getElementById('prop_code').value = '';
    document.getElementById('prop_name').value = '';
    document.getElementById('prop_type').value = 'land';
    document.getElementById('prop_survey').value = '';
    document.getElementById('prop_status').value = 'vacant';
    document.getElementById('prop_location').value = '';
    document.getElementById('prop_city').value = '';
    document.getElementById('prop_ownership').value = '';
    document.getElementById('prop_value').value = '0';
    document.getElementById('prop_income').value = '0';
    document.getElementById('prop_notes').value = '';
}

function editProperty(p) {
    document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Property';
    document.getElementById('prop_id').value      = p.id;
    document.getElementById('prop_code').value    = p.property_code || '';
    document.getElementById('prop_name').value    = p.property_name || '';
    document.getElementById('prop_type').value    = p.property_type || 'other';
    document.getElementById('prop_survey').value  = p.survey_number || '';
    document.getElementById('prop_status').value  = p.status || 'vacant';
    document.getElementById('prop_location').value= p.location || '';
    document.getElementById('prop_city').value    = p.city || '';
    document.getElementById('prop_ownership').value = p.ownership_details || '';
    document.getElementById('prop_value').value   = p.estimated_value || '0';
    document.getElementById('prop_income').value  = p.monthly_income || '0';
    document.getElementById('prop_notes').value   = p.notes || '';
    var modal = new bootstrap.Modal(document.getElementById('propertyModal'));
    modal.show();
}
</script>
