<?php
$pageTitle = 'Assets Register';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

$pdo = db();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/modules/assets_register/assets_list.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id                 = (int)($_POST['id'] ?? 0);
        $asset_code         = sanitize($_POST['asset_code'] ?? '');
        $asset_name         = sanitize($_POST['asset_name'] ?? '');
        $category           = sanitize($_POST['category'] ?? '');
        $purchase_date      = sanitize($_POST['purchase_date'] ?? '');
        $purchase_value     = (float)($_POST['purchase_value'] ?? 0);
        $supplier           = sanitize($_POST['supplier'] ?? '');
        $condition_status   = sanitize($_POST['condition_status'] ?? 'good');
        $location           = sanitize($_POST['location'] ?? '');
        $depreciation_rate  = (float)($_POST['depreciation_rate'] ?? 0);
        $current_value      = (float)($_POST['current_value'] ?? 0);
        $notes              = sanitize($_POST['notes'] ?? '');

        if (empty($asset_name)) {
            setFlash('danger', 'Asset name is required.');
            redirect(BASE_PATH . '/modules/assets_register/assets_list.php');
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE assets SET asset_code=?, asset_name=?, category=?, purchase_date=?, purchase_value=?, supplier=?, condition_status=?, location=?, depreciation_rate=?, current_value=?, notes=?, updated_at=NOW() WHERE id=? AND deleted_at IS NULL");
            $stmt->execute([$asset_code, $asset_name, $category, $purchase_date ?: null, $purchase_value, $supplier, $condition_status, $location, $depreciation_rate, $current_value, $notes, $id]);
            setFlash('success', 'Asset updated successfully.');
        } else {
            if (empty($asset_code)) {
                $asset_code = generateNumber('AST', 'assets', 'asset_code');
            }
            $stmt = $pdo->prepare("INSERT INTO assets (asset_code, asset_name, category, purchase_date, purchase_value, supplier, condition_status, location, depreciation_rate, current_value, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
            $stmt->execute([$asset_code, $asset_name, $category, $purchase_date ?: null, $purchase_value, $supplier, $condition_status, $location, $depreciation_rate, $current_value, $notes]);
            setFlash('success', 'Asset added successfully.');
        }
        redirect(BASE_PATH . '/modules/assets_register/assets_list.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE assets SET deleted_at=NOW() WHERE id=?")->execute([$id]);
            setFlash('success', 'Asset deleted successfully.');
        }
        redirect(BASE_PATH . '/modules/assets_register/assets_list.php');
    }
}

// Fetch all assets
$stmt = $pdo->query("SELECT * FROM assets WHERE deleted_at IS NULL ORDER BY asset_name");
$assets = $stmt->fetchAll();

// Summary by condition
$conditionCounts = ['excellent'=>0, 'good'=>0, 'fair'=>0, 'poor'=>0, 'disposed'=>0];
$totalAssets = count($assets);
$totalPurchaseValue = 0;
$totalCurrentValue  = 0;
$categoryGroups = [];

foreach ($assets as $a) {
    $cond = $a['condition_status'];
    if (isset($conditionCounts[$cond])) $conditionCounts[$cond]++;
    $totalPurchaseValue += (float)$a['purchase_value'];
    $totalCurrentValue  += (float)$a['current_value'];
    $cat = $a['category'] ?: 'Uncategorized';
    if (!isset($categoryGroups[$cat])) $categoryGroups[$cat] = 0;
    $categoryGroups[$cat]++;
}
arsort($categoryGroups);

$conditionColors = [
    'excellent' => 'success',
    'good'      => 'primary',
    'fair'      => 'warning',
    'poor'      => 'danger',
    'disposed'  => 'secondary',
];

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
        <h4 class="mb-0"><i class="bi bi-archive me-2 text-primary"></i>Assets Register</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Home</a></li>
            <li class="breadcrumb-item active">Assets</li>
        </ol></nav>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assetModal" onclick="resetForm()">
        <i class="bi bi-plus-circle me-1"></i>Add Asset
    </button>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-primary"><?= $totalAssets ?></div>
                <div class="small text-muted">Total Assets</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-info"><?= formatCurrency($totalPurchaseValue) ?></div>
                <div class="small text-muted">Total Purchase Value</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-success"><?= formatCurrency($totalCurrentValue) ?></div>
                <div class="small text-muted">Total Current Value</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-danger"><?= formatCurrency($totalPurchaseValue - $totalCurrentValue) ?></div>
                <div class="small text-muted">Total Depreciation</div>
            </div>
        </div>
    </div>
</div>

<!-- Condition Summary -->
<div class="row g-3 mb-4">
    <?php foreach ($conditionCounts as $cond => $cnt): if ($cnt > 0): ?>
    <div class="col-auto">
        <span class="badge bg-<?= $conditionColors[$cond] ?> fs-6 px-3 py-2">
            <?= ucfirst($cond) ?>: <?= $cnt ?>
        </span>
    </div>
    <?php endif; endforeach; ?>
    <?php if (!empty($categoryGroups)): ?>
    <div class="col-12">
        <div class="d-flex flex-wrap gap-2">
        <?php foreach ($categoryGroups as $cat => $cnt): ?>
            <span class="badge bg-secondary"><?= htmlspecialchars($cat) ?>: <?= $cnt ?></span>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Assets Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>All Assets</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="assetsTable" class="table table-hover align-middle small">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Asset Name</th>
                        <th>Category</th>
                        <th>Purchase Date</th>
                        <th>Purchase Value</th>
                        <th>Current Value</th>
                        <th>Location</th>
                        <th>Condition</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($assets as $i => $a): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($a['asset_code']) ?></span></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($a['asset_name']) ?></div>
                        <?php if ($a['supplier']): ?><small class="text-muted">Supplier: <?= htmlspecialchars($a['supplier']) ?></small><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($a['category'] ?: '-') ?></td>
                    <td><?= $a['purchase_date'] ? formatDate($a['purchase_date']) : '-' ?></td>
                    <td><?= formatCurrency((float)$a['purchase_value']) ?></td>
                    <td><?= formatCurrency((float)$a['current_value']) ?></td>
                    <td><?= htmlspecialchars($a['location'] ?: '-') ?></td>
                    <td>
                        <span class="badge bg-<?= $conditionColors[$a['condition_status']] ?? 'secondary' ?>">
                            <?= ucfirst($a['condition_status']) ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="editAsset(<?= htmlspecialchars(json_encode($a)) ?>)" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this asset?')">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
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
<div class="modal fade" id="assetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="assetForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="ast_id" value="0">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="assetModalTitle"><i class="bi bi-archive me-2"></i>Add Asset</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Asset Code</label>
                            <input type="text" name="asset_code" id="ast_code" class="form-control" placeholder="Auto-generated">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Asset Name <span class="text-danger">*</span></label>
                            <input type="text" name="asset_name" id="ast_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Category</label>
                            <input type="text" name="category" id="ast_category" class="form-control" placeholder="e.g. Furniture, Electronics">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Condition</label>
                            <select name="condition_status" id="ast_condition" class="form-select">
                                <option value="excellent">Excellent</option>
                                <option value="good" selected>Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                                <option value="disposed">Disposed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Location</label>
                            <input type="text" name="location" id="ast_location" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Purchase Date</label>
                            <input type="date" name="purchase_date" id="ast_purchase_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Purchase Value (₹)</label>
                            <input type="number" name="purchase_value" id="ast_purchase_val" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Current Value (₹)</label>
                            <input type="number" name="current_value" id="ast_current_val" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Depreciation Rate (%/yr)</label>
                            <input type="number" name="depreciation_rate" id="ast_dep_rate" class="form-control" step="0.01" min="0" max="100" value="0">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Supplier</label>
                            <input type="text" name="supplier" id="ast_supplier" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" id="ast_notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Asset</button>
                </div>
            </div>
        </form>
    </div>
</div>

</div></div></div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
<script>
$('#assetsTable').DataTable({
    order: [[2, 'asc']],
    pageLength: 25,
    language: { search: 'Search assets:' }
});

function resetForm() {
    document.getElementById('assetModalTitle').innerHTML = '<i class="bi bi-archive me-2"></i>Add Asset';
    document.getElementById('ast_id').value = '0';
    ['ast_code','ast_name','ast_category','ast_location','ast_purchase_date','ast_supplier','ast_notes'].forEach(id => {
        document.getElementById(id).value = '';
    });
    document.getElementById('ast_condition').value = 'good';
    document.getElementById('ast_purchase_val').value = '0';
    document.getElementById('ast_current_val').value  = '0';
    document.getElementById('ast_dep_rate').value     = '0';
}

function editAsset(a) {
    document.getElementById('assetModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Asset';
    document.getElementById('ast_id').value            = a.id;
    document.getElementById('ast_code').value          = a.asset_code || '';
    document.getElementById('ast_name').value          = a.asset_name || '';
    document.getElementById('ast_category').value      = a.category || '';
    document.getElementById('ast_condition').value     = a.condition_status || 'good';
    document.getElementById('ast_location').value      = a.location || '';
    document.getElementById('ast_purchase_date').value = a.purchase_date || '';
    document.getElementById('ast_purchase_val').value  = a.purchase_value || '0';
    document.getElementById('ast_current_val').value   = a.current_value || '0';
    document.getElementById('ast_dep_rate').value      = a.depreciation_rate || '0';
    document.getElementById('ast_supplier').value      = a.supplier || '';
    document.getElementById('ast_notes').value         = a.notes || '';
    var modal = new bootstrap.Modal(document.getElementById('assetModal'));
    modal.show();
}
</script>
