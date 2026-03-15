<?php
$pageTitle = 'Rent Due Report';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

$pdo = db();

// Filters
$filterMonth  = sanitize($_GET['month'] ?? '');
$filterTenant = (int)($_GET['tenant_id'] ?? 0);
$filterStatus = sanitize($_GET['status'] ?? 'pending');

// Build query for pending/partial records
$where = "rc.deleted_at IS NULL AND rc.status IN ('pending','partial')";
$params = [];

if (!empty($filterMonth)) {
    $where .= " AND rc.rent_month = ?";
    $params[] = $filterMonth;
}
if ($filterTenant > 0) {
    $where .= " AND rc.tenant_id = ?";
    $params[] = $filterTenant;
}
if (!empty($filterStatus) && $filterStatus !== 'all') {
    $where = str_replace("rc.status IN ('pending','partial')", "rc.status = ?", $where);
    array_unshift($params, $filterStatus);
}

$stmt = $pdo->prepare("
    SELECT rc.*,
           t.tenant_name, t.tenant_code, t.phone, t.due_day,
           p.property_name, p.property_code,
           (rc.due_amount - rc.paid_amount) AS outstanding
    FROM rent_collections rc
    JOIN waqf_tenants t ON t.id = rc.tenant_id
    LEFT JOIN waqf_properties p ON p.id = t.property_id
    WHERE $where
    ORDER BY rc.rent_month ASC, t.tenant_name ASC
");
$stmt->execute($params);
$dueRecords = $stmt->fetchAll();

// Summary totals
$totalDue = 0; $totalPaid = 0; $totalOutstanding = 0;
$byTenant = [];
foreach ($dueRecords as $r) {
    $totalDue         += (float)$r['due_amount'];
    $totalPaid        += (float)$r['paid_amount'];
    $totalOutstanding += (float)$r['outstanding'];
}

// Tenants for filter
$tenantsAll = $pdo->query("SELECT id, tenant_code, tenant_name FROM waqf_tenants WHERE deleted_at IS NULL ORDER BY tenant_name")->fetchAll();

// Months with due records (for month filter dropdown)
$monthsStmt = $pdo->query("SELECT DISTINCT rent_month FROM rent_collections WHERE deleted_at IS NULL ORDER BY rent_month DESC");
$availableMonths = $monthsStmt->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../../templates/header.php';
?>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><i class="bi bi-exclamation-circle me-2 text-danger"></i>Rent Due Report</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Home</a></li>
            <li class="breadcrumb-item">Waqf</li>
            <li class="breadcrumb-item active">Rent Due</li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <button class="btn btn-outline-success btn-sm" id="exportExcel">
            <i class="bi bi-file-earmark-excel me-1"></i>Export
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Month</label>
                <select name="month" class="form-select form-select-sm">
                    <option value="">All Months</option>
                    <?php foreach ($availableMonths as $m): ?>
                    <option value="<?= $m ?>" <?= $filterMonth === $m ? 'selected' : '' ?>>
                        <?= date('F Y', strtotime($m . '-01')) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
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
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending Only</option>
                    <option value="partial" <?= $filterStatus === 'partial' ? 'selected' : '' ?>>Partial Only</option>
                    <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Pending + Partial</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-filter me-1"></i>Filter</button>
            </div>
            <div class="col-md-2">
                <a href="<?= BASE_PATH ?>/modules/waqf/rent_due.php" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-x-circle me-1"></i>Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm text-center border-start border-primary border-4">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-primary"><?= count($dueRecords) ?></div>
                <div class="small text-muted">Pending Records</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm text-center border-start border-warning border-4">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-warning"><?= formatCurrency($totalDue) ?></div>
                <div class="small text-muted">Total Due</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm text-center border-start border-danger border-4">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-danger"><?= formatCurrency($totalOutstanding) ?></div>
                <div class="small text-muted">Total Outstanding</div>
            </div>
        </div>
    </div>
</div>

<!-- Due Records Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-table me-2 text-danger"></i>Pending / Overdue Rent</h6>
        <span class="badge bg-danger"><?= count($dueRecords) ?> records</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="dueTable" class="table table-hover align-middle small">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Tenant</th>
                        <th>Property</th>
                        <th>Month</th>
                        <th>Due Amount</th>
                        <th>Paid</th>
                        <th>Outstanding</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($dueRecords)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">
                    <i class="bi bi-check-circle text-success fs-3 d-block mb-2"></i>
                    No pending rent records found.
                </td></tr>
                <?php endif; ?>
                <?php foreach ($dueRecords as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($r['tenant_name']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($r['tenant_code']) ?></small>
                        <?php if ($r['phone']): ?>
                        <br><small class="text-muted"><i class="bi bi-telephone"></i> <?= htmlspecialchars($r['phone']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($r['property_name'] ?? '-') ?></td>
                    <td>
                        <?= date('F Y', strtotime($r['rent_month'] . '-01')) ?>
                        <?php
                        // Check if overdue (past month)
                        if (strtotime($r['rent_month'] . '-01') < strtotime(date('Y-m') . '-01')) {
                            echo '<br><span class="badge bg-danger">Overdue</span>';
                        }
                        ?>
                    </td>
                    <td class="fw-semibold"><?= formatCurrency((float)$r['due_amount']) ?></td>
                    <td class="text-success"><?= formatCurrency((float)$r['paid_amount']) ?></td>
                    <td class="text-danger fw-bold"><?= formatCurrency((float)$r['outstanding']) ?></td>
                    <td>
                        <?php $sb = $r['status'] === 'partial' ? 'info' : 'warning'; ?>
                        <span class="badge bg-<?= $sb ?>"><?= ucfirst($r['status']) ?></span>
                    </td>
                    <td>
                        <a href="<?= BASE_PATH ?>/modules/waqf/rent_collection.php?month=<?= $r['rent_month'] ?>&tenant_id=<?= $r['tenant_id'] ?>" class="btn btn-sm btn-success">
                            <i class="bi bi-cash me-1"></i>Collect
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td colspan="4" class="text-end">Total</td>
                        <td><?= formatCurrency($totalDue) ?></td>
                        <td class="text-success"><?= formatCurrency($totalPaid) ?></td>
                        <td class="text-danger"><?= formatCurrency($totalOutstanding) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

</div></div></div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
<script>
var dueTable = $('#dueTable').DataTable({
    order: [[3, 'asc'], [1, 'asc']],
    pageLength: 25,
    dom: 'Bfrtip',
    buttons: [
        { extend: 'excelHtml5', text: '<i class="bi bi-file-earmark-excel me-1"></i>Excel', className: 'btn btn-sm btn-success d-none', title: 'Rent Due Report' },
        { extend: 'print', text: '<i class="bi bi-printer me-1"></i>Print', className: 'btn btn-sm btn-secondary d-none', title: 'Rent Due Report' }
    ],
    language: { search: 'Search:' }
});

document.getElementById('exportExcel').addEventListener('click', function () {
    dueTable.button('.buttons-excel').trigger();
});
</script>
