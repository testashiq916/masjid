<?php
$pageTitle = 'Rent Collection Report';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

require_once __DIR__ . '/../../app/helpers/functions.php';

$pdo = db();

// Filters
$filter_month  = isset($_GET['month'])     ? (int)$_GET['month']               : (int)date('m');
$filter_year   = isset($_GET['year'])      ? (int)$_GET['year']                : (int)date('Y');
$filter_tenant = isset($_GET['tenant_id']) ? sanitize($_GET['tenant_id'])       : '';
$filter_prop   = isset($_GET['property_id']) ? sanitize($_GET['property_id'])   : '';
$filter_status = isset($_GET['status'])    ? sanitize($_GET['status'])          : '';

// Month string used in DB (YYYY-MM or full date)
$month_str = sprintf('%04d-%02d', $filter_year, $filter_month);

// Dropdowns
$tenants    = $pdo->query("SELECT id, tenant_name FROM tenants ORDER BY tenant_name")->fetchAll(PDO::FETCH_ASSOC);
$properties = $pdo->query("SELECT id, property_name FROM waqf_properties ORDER BY property_name")->fetchAll(PDO::FETCH_ASSOC);

// Build query
$sql = "SELECT rc.id,
               t.tenant_name,
               wp.property_name,
               rc.collection_month,
               rc.due_amount,
               rc.paid_amount,
               (rc.due_amount - rc.paid_amount) AS outstanding,
               rc.status
        FROM rent_collections rc
        INNER JOIN tenants t ON t.id = rc.tenant_id
        LEFT  JOIN waqf_properties wp ON wp.id = t.property_id
        WHERE DATE_FORMAT(rc.collection_month, '%Y-%m') = :month_str";

$params = [':month_str' => $month_str];

if ($filter_tenant !== '') {
    $sql .= " AND rc.tenant_id = :tenant_id";
    $params[':tenant_id'] = $filter_tenant;
}
if ($filter_prop !== '') {
    $sql .= " AND t.property_id = :property_id";
    $params[':property_id'] = $filter_prop;
}
if ($filter_status !== '') {
    $sql .= " AND rc.status = :status";
    $params[':status'] = $filter_status;
}
$sql .= " ORDER BY t.tenant_name, rc.collection_month";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aggregate totals
$total_due         = 0.0;
$total_paid        = 0.0;
$total_outstanding = 0.0;
$count_paid        = 0;
$count_pending     = 0;

foreach ($rows as $r) {
    $total_due         += (float)$r['due_amount'];
    $total_paid        += (float)$r['paid_amount'];
    $total_outstanding += (float)$r['outstanding'];
    if (strtolower($r['status']) === 'paid')    $count_paid++;
    else                                         $count_pending++;
}

require_once __DIR__ . '/../../templates/header.php';
?>
<style>
@media print {
    .no-print { display: none !important; }
    .main-content { margin: 0 !important; }
    .content-area { margin-top: 0 !important; padding: 0 !important; }
    .wrapper { display: block !important; }
    body { font-size: 12px; }
    .table { font-size: 11px; }
    .page-header-print { display: block !important; }
}
.page-header-print { display: none; }
</style>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

    <!-- Print header -->
    <div class="page-header-print text-center mb-3">
        <h4 class="mb-0">Rent Collection Report</h4>
        <small>Month: <?= date('F Y', mktime(0, 0, 0, $filter_month, 1, $filter_year)) ?></small>
    </div>

    <!-- Page heading -->
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h4 class="mb-0"><i class="bi bi-building-fill text-warning me-2"></i>Rent Collection Report</h4>
        <div>
            <button class="btn btn-outline-secondary btn-sm me-1" onclick="window.print()">
                <i class="bi bi-printer"></i> Print
            </button>
            <button class="btn btn-outline-success btn-sm" id="exportExcel">
                <i class="bi bi-file-earmark-excel"></i> Export
            </button>
        </div>
    </div>

    <!-- Filter form -->
    <div class="card mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Month</label>
                    <select name="month" class="form-select form-select-sm">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m === $filter_month ? 'selected' : '' ?>>
                            <?= date('F', mktime(0,0,0,$m,1)) ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label form-label-sm mb-1">Year</label>
                    <select name="year" class="form-select form-select-sm">
                        <?php for ($y = date('Y') - 3; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= $y === $filter_year ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm mb-1">Tenant</label>
                    <select name="tenant_id" class="form-select form-select-sm">
                        <option value="">All Tenants</option>
                        <?php foreach ($tenants as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $filter_tenant == $t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['tenant_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Property</label>
                    <select name="property_id" class="form-select form-select-sm">
                        <option value="">All Properties</option>
                        <?php foreach ($properties as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $filter_prop == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['property_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="Paid"    <?= $filter_status === 'Paid'    ? 'selected' : '' ?>>Paid</option>
                        <option value="Pending" <?= $filter_status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Partial" <?= $filter_status === 'Partial' ? 'selected' : '' ?>>Partial</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search"></i> Generate
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats cards -->
    <?php if (!empty($rows)): ?>
    <div class="row g-3 mb-4 no-print">
        <div class="col-md-3">
            <div class="card border-primary text-center p-3">
                <div class="fs-5 fw-bold text-primary"><?= formatCurrency($total_due) ?></div>
                <div class="small text-muted">Total Due</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success text-center p-3">
                <div class="fs-5 fw-bold text-success"><?= formatCurrency($total_paid) ?></div>
                <div class="small text-muted">Total Collected</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger text-center p-3">
                <div class="fs-5 fw-bold text-danger"><?= formatCurrency($total_outstanding) ?></div>
                <div class="small text-muted">Outstanding</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center p-3">
                <div class="fs-5 fw-bold">
                    <span class="text-success"><?= $count_paid ?> Paid</span>
                    &nbsp;/&nbsp;
                    <span class="text-danger"><?= $count_pending ?> Pending</span>
                </div>
                <div class="small text-muted">Collection Status</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-bordered table-sm mb-0" id="rentTable">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Tenant</th>
                        <th>Property</th>
                        <th>Month</th>
                        <th class="text-end">Due Amount</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Outstanding</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $i => $row):
                    $statusClass = match(strtolower($row['status'])) {
                        'paid'    => 'bg-success',
                        'partial' => 'bg-warning text-dark',
                        default   => 'bg-danger'
                    };
                ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($row['tenant_name']) ?></td>
                        <td><?= htmlspecialchars($row['property_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(date('M Y', strtotime($row['collection_month']))) ?></td>
                        <td class="text-end"><?= formatCurrency($row['due_amount']) ?></td>
                        <td class="text-end text-success"><?= formatCurrency($row['paid_amount']) ?></td>
                        <td class="text-end <?= (float)$row['outstanding'] > 0 ? 'text-danger' : '' ?>">
                            <?= formatCurrency($row['outstanding']) ?>
                        </td>
                        <td class="text-center">
                            <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-warning fw-bold">
                    <tr>
                        <td colspan="4" class="text-end">Totals:</td>
                        <td class="text-end"><?= formatCurrency($total_due) ?></td>
                        <td class="text-end text-success"><?= formatCurrency($total_paid) ?></td>
                        <td class="text-end text-danger"><?= formatCurrency($total_outstanding) ?></td>
                        <td class="text-center">
                            <span class="badge bg-success me-1"><?= $count_paid ?> Paid</span>
                            <span class="badge bg-danger"><?= $count_pending ?> Pending</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>No rent records found for the selected criteria.
    </div>
    <?php endif; ?>

</div><!-- content-area -->
</div><!-- main-content -->
</div><!-- wrapper -->

<script>
document.getElementById('exportExcel')?.addEventListener('click', function () {
    const table = document.getElementById('rentTable');
    if (!table) return;
    let csv = [];
    for (let row of table.rows) {
        let cols = [];
        for (let cell of row.cells) {
            let text = cell.innerText.replace(/"/g, '""');
            cols.push('"' + text + '"');
        }
        csv.push(cols.join(','));
    }
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'rent_report_<?= sprintf('%04d-%02d', $filter_year, $filter_month) ?>.csv';
    a.click();
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
