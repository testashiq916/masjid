<?php
$pageTitle = 'Rent Collection';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

$pdo = db();

// Auto-generate pending entries for current month
$currentMonth = date('Y-m');
$activeTenants = $pdo->query("SELECT id, rent_amount FROM waqf_tenants WHERE deleted_at IS NULL AND status = 'active'")->fetchAll();
foreach ($activeTenants as $at) {
    $check = $pdo->prepare("SELECT id FROM rent_collections WHERE tenant_id = ? AND rent_month = ? AND deleted_at IS NULL");
    $check->execute([$at['id'], $currentMonth]);
    if (!$check->fetch()) {
        $pdo->prepare("INSERT INTO rent_collections (tenant_id, rent_month, due_amount, paid_amount, status, created_at, updated_at) VALUES (?,?,?,0,'pending',NOW(),NOW())")
            ->execute([$at['id'], $currentMonth, $at['rent_amount']]);
    }
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/modules/waqf/rent_collection.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'collect') {
        $collection_id  = (int)($_POST['collection_id'] ?? 0);
        $paid_amount    = (float)($_POST['paid_amount'] ?? 0);
        $payment_date   = sanitize($_POST['payment_date'] ?? date('Y-m-d'));
        $payment_mode   = sanitize($_POST['payment_mode'] ?? 'cash');
        $bank_ref       = sanitize($_POST['bank_ref'] ?? '');
        $remarks        = sanitize($_POST['remarks'] ?? '');

        if ($collection_id > 0 && $paid_amount > 0) {
            // Get current record
            $rec = $pdo->prepare("SELECT * FROM rent_collections WHERE id = ? AND deleted_at IS NULL");
            $rec->execute([$collection_id]);
            $record = $rec->fetch();

            if ($record) {
                $receipt_no = generateNumber('RNT', 'rent_collections', 'receipt_no');
                $total_paid = (float)$record['paid_amount'] + $paid_amount;
                $balance    = (float)$record['due_amount'] - $total_paid;
                $status     = ($balance <= 0) ? 'paid' : 'partial';

                $pdo->prepare("UPDATE rent_collections SET paid_amount=?, payment_date=?, payment_mode=?, bank_ref=?, receipt_no=?, remarks=?, status=?, updated_at=NOW() WHERE id=?")
                    ->execute([$total_paid, $payment_date, $payment_mode, $bank_ref, $receipt_no, $remarks, $status, $collection_id]);

                setFlash('success', "Payment recorded. Receipt No: $receipt_no");
            } else {
                setFlash('danger', 'Record not found.');
            }
        } else {
            setFlash('danger', 'Invalid payment amount.');
        }

        $qs = http_build_query(['month' => $_POST['filter_month'] ?? $currentMonth, 'tenant_id' => $_POST['filter_tenant'] ?? '', 'status' => $_POST['filter_status'] ?? '']);
        redirect(BASE_PATH . '/modules/waqf/rent_collection.php?' . $qs);
    }
}

// Filters
$filterMonth    = sanitize($_GET['month'] ?? $currentMonth);
$filterTenant   = (int)($_GET['tenant_id'] ?? 0);
$filterStatus   = sanitize($_GET['status'] ?? '');

// Build query
$where = "rc.deleted_at IS NULL AND rc.rent_month = ?";
$params = [$filterMonth];

if ($filterTenant > 0) {
    $where .= " AND rc.tenant_id = ?";
    $params[] = $filterTenant;
}
if (!empty($filterStatus)) {
    $where .= " AND rc.status = ?";
    $params[] = $filterStatus;
}

$stmt = $pdo->prepare("
    SELECT rc.*, t.tenant_name, t.tenant_code, t.phone,
           p.property_name, p.property_code
    FROM rent_collections rc
    JOIN waqf_tenants t ON t.id = rc.tenant_id
    LEFT JOIN waqf_properties p ON p.id = t.property_id
    WHERE $where
    ORDER BY t.tenant_name
");
$stmt->execute($params);
$collections = $stmt->fetchAll();

// Summary
$totalDue = 0; $totalPaid = 0;
foreach ($collections as $c) {
    $totalDue  += (float)$c['due_amount'];
    $totalPaid += (float)$c['paid_amount'];
}
$totalBalance = $totalDue - $totalPaid;

// Tenants for filter
$tenantsAll = $pdo->query("SELECT id, tenant_code, tenant_name FROM waqf_tenants WHERE deleted_at IS NULL ORDER BY tenant_name")->fetchAll();

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
        <h4 class="mb-0"><i class="bi bi-cash-coin me-2 text-primary"></i>Rent Collection</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Home</a></li>
            <li class="breadcrumb-item">Waqf</li>
            <li class="breadcrumb-item active">Rent Collection</li>
        </ol></nav>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Month</label>
                <input type="month" name="month" class="form-control form-control-sm" value="<?= htmlspecialchars($filterMonth) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Tenant</label>
                <select name="tenant_id" class="form-select form-select-sm">
                    <option value="">All Tenants</option>
                    <?php foreach ($tenantsAll as $tn): ?>
                    <option value="<?= $tn['id'] ?>" <?= $filterTenant == $tn['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tn['tenant_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="partial" <?= $filterStatus === 'partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="paid" <?= $filterStatus === 'paid' ? 'selected' : '' ?>>Paid</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-filter me-1"></i>Filter</button>
            </div>
            <div class="col-md-2">
                <a href="<?= BASE_PATH ?>/modules/waqf/rent_collection.php" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-x-circle me-1"></i>Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-dark"><?= count($collections) ?></div>
                <div class="small text-muted">Total Records</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-primary"><?= formatCurrency($totalDue) ?></div>
                <div class="small text-muted">Total Due</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-success"><?= formatCurrency($totalPaid) ?></div>
                <div class="small text-muted">Total Collected</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-danger"><?= formatCurrency($totalBalance) ?></div>
                <div class="small text-muted">Outstanding</div>
            </div>
        </div>
    </div>
</div>

<!-- Collections Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0"><i class="bi bi-table me-2"></i>Rent Collections - <?= date('F Y', strtotime($filterMonth . '-01')) ?></h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="collectionsTable" class="table table-hover align-middle small">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Tenant</th>
                        <th>Property</th>
                        <th>Month</th>
                        <th>Due Amount</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Receipt No.</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($collections as $i => $c):
                    $balance = (float)$c['due_amount'] - (float)$c['paid_amount'];
                ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($c['tenant_name']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($c['tenant_code']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($c['property_name'] ?? '-') ?></td>
                    <td><?= date('M Y', strtotime($c['rent_month'] . '-01')) ?></td>
                    <td class="fw-semibold"><?= formatCurrency((float)$c['due_amount']) ?></td>
                    <td class="text-success"><?= formatCurrency((float)$c['paid_amount']) ?></td>
                    <td class="<?= $balance > 0 ? 'text-danger fw-semibold' : 'text-success' ?>"><?= formatCurrency($balance) ?></td>
                    <td><?= $c['receipt_no'] ? '<span class="badge bg-secondary">' . htmlspecialchars($c['receipt_no']) . '</span>' : '-' ?></td>
                    <td>
                        <?php
                        $stMap = ['pending'=>'warning','partial'=>'info','paid'=>'success'];
                        $sb = $stMap[$c['status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?= $sb ?>"><?= ucfirst($c['status']) ?></span>
                    </td>
                    <td>
                        <?php if ($c['status'] !== 'paid'): ?>
                        <button class="btn btn-sm btn-success"
                            onclick="collectRent(<?= $c['id'] ?>, '<?= htmlspecialchars($c['tenant_name']) ?>', <?= (float)$c['due_amount'] ?>, <?= (float)$c['paid_amount'] ?>)"
                            title="Collect Payment">
                            <i class="bi bi-cash me-1"></i>Collect
                        </button>
                        <?php else: ?>
                        <span class="text-muted small"><i class="bi bi-check-circle text-success"></i> Paid</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Collect Payment Modal -->
<div class="modal fade" id="collectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="collectForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="action" value="collect">
            <input type="hidden" name="collection_id" id="col_id">
            <input type="hidden" name="filter_month" value="<?= htmlspecialchars($filterMonth) ?>">
            <input type="hidden" name="filter_tenant" value="<?= $filterTenant ?>">
            <input type="hidden" name="filter_status" value="<?= htmlspecialchars($filterStatus) ?>">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Collect Rent Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small mb-3" id="col_info"></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Amount Paid <span class="text-danger">*</span></label>
                            <input type="number" name="paid_amount" id="col_amount" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" id="col_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Mode</label>
                            <select name="payment_mode" id="col_mode" class="form-select">
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                                <option value="upi">UPI</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Bank Ref / Cheque No.</label>
                            <input type="text" name="bank_ref" id="col_ref" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" id="col_remarks" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Record Payment</button>
                </div>
            </div>
        </form>
    </div>
</div>

</div></div></div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
<script>
$('#collectionsTable').DataTable({
    order: [[1, 'asc']],
    pageLength: 25,
    language: { search: 'Search:' }
});

function collectRent(id, name, dueAmt, paidAmt) {
    document.getElementById('col_id').value = id;
    var balance = (dueAmt - paidAmt).toFixed(2);
    document.getElementById('col_info').innerHTML =
        '<strong>Tenant:</strong> ' + name + '<br>' +
        '<strong>Due:</strong> ₹' + parseFloat(dueAmt).toFixed(2) +
        ' | <strong>Already Paid:</strong> ₹' + parseFloat(paidAmt).toFixed(2) +
        ' | <strong>Balance:</strong> ₹' + balance;
    document.getElementById('col_amount').value = balance;
    document.getElementById('col_ref').value = '';
    document.getElementById('col_remarks').value = '';
    document.getElementById('col_mode').value = 'cash';
    document.getElementById('col_date').value = '<?= date('Y-m-d') ?>';
    var modal = new bootstrap.Modal(document.getElementById('collectModal'));
    modal.show();
}
</script>
