<?php
$pageTitle = 'Salary Report';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

require_once __DIR__ . '/../../app/helpers/functions.php';

$pdo = db();

// Filters
$filter_month  = isset($_GET['month'])       ? (int)$_GET['month']             : (int)date('m');
$filter_year   = isset($_GET['year'])        ? (int)$_GET['year']              : (int)date('Y');
$filter_desig  = isset($_GET['designation']) ? sanitize($_GET['designation'])   : '';
$filter_status = isset($_GET['status'])      ? sanitize($_GET['status'])        : '';

$month_str = sprintf('%04d-%02d', $filter_year, $filter_month);

// Fetch distinct designations for dropdown
$designations = $pdo->query("SELECT DISTINCT designation FROM staff WHERE designation IS NOT NULL ORDER BY designation")->fetchAll(PDO::FETCH_COLUMN);

// Build query
$sql = "SELECT ss.id,
               s.staff_name,
               s.designation,
               ss.salary_month,
               ss.basic_salary,
               ss.allowance,
               ss.deduction,
               ss.net_salary,
               ss.status,
               ss.payment_date
        FROM salary_sheet ss
        INNER JOIN staff s ON s.id = ss.staff_id
        WHERE DATE_FORMAT(ss.salary_month, '%Y-%m') = :month_str";

$params = [':month_str' => $month_str];

if ($filter_desig !== '') {
    $sql .= " AND s.designation = :designation";
    $params[':designation'] = $filter_desig;
}
if ($filter_status !== '') {
    $sql .= " AND ss.status = :status";
    $params[':status'] = $filter_status;
}
$sql .= " ORDER BY s.staff_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totals
$total_basic     = 0.0;
$total_allowance = 0.0;
$total_deduction = 0.0;
$total_net       = 0.0;
$total_paid_amt  = 0.0;
$total_pend_amt  = 0.0;
$count_paid      = 0;
$count_pending   = 0;

foreach ($rows as $r) {
    $total_basic     += (float)$r['basic_salary'];
    $total_allowance += (float)$r['allowance'];
    $total_deduction += (float)$r['deduction'];
    $total_net       += (float)$r['net_salary'];
    if (strtolower($r['status']) === 'paid') {
        $total_paid_amt += (float)$r['net_salary'];
        $count_paid++;
    } else {
        $total_pend_amt += (float)$r['net_salary'];
        $count_pending++;
    }
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
        <h4 class="mb-0">Salary Report</h4>
        <small>Month: <?= date('F Y', mktime(0, 0, 0, $filter_month, 1, $filter_year)) ?></small>
    </div>

    <!-- Page heading -->
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h4 class="mb-0"><i class="bi bi-cash-stack text-info me-2"></i>Salary Report</h4>
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
                    <label class="form-label form-label-sm mb-1">Designation</label>
                    <select name="designation" class="form-select form-select-sm">
                        <option value="">All Designations</option>
                        <?php foreach ($designations as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>"
                            <?= $filter_desig === $d ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="Paid"    <?= $filter_status === 'Paid'    ? 'selected' : '' ?>>Paid</option>
                        <option value="Pending" <?= $filter_status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search"></i> Generate
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="salary_report.php" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($rows)): ?>
    <!-- Stats cards -->
    <div class="row g-3 mb-4 no-print">
        <div class="col-md-3">
            <div class="card border-primary text-center p-3">
                <div class="fs-5 fw-bold text-primary"><?= formatCurrency($total_net) ?></div>
                <div class="small text-muted">Total Payroll</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success text-center p-3">
                <div class="fs-5 fw-bold text-success"><?= formatCurrency($total_paid_amt) ?></div>
                <div class="small text-muted">Total Paid (<?= $count_paid ?> staff)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger text-center p-3">
                <div class="fs-5 fw-bold text-danger"><?= formatCurrency($total_pend_amt) ?></div>
                <div class="small text-muted">Pending (<?= $count_pending ?> staff)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning text-center p-3">
                <div class="fs-5 fw-bold text-warning"><?= formatCurrency($total_deduction) ?></div>
                <div class="small text-muted">Total Deductions</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-bordered table-sm mb-0" id="salaryTable">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Staff Name</th>
                        <th>Designation</th>
                        <th>Month</th>
                        <th class="text-end">Basic</th>
                        <th class="text-end">Allowance</th>
                        <th class="text-end">Deduction</th>
                        <th class="text-end">Net Salary</th>
                        <th class="text-center">Status</th>
                        <th>Payment Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $i => $row):
                    $isPaid = strtolower($row['status']) === 'paid';
                ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($row['staff_name']) ?></td>
                        <td><?= htmlspecialchars($row['designation'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(date('M Y', strtotime($row['salary_month']))) ?></td>
                        <td class="text-end"><?= formatCurrency($row['basic_salary']) ?></td>
                        <td class="text-end"><?= formatCurrency($row['allowance']) ?></td>
                        <td class="text-end text-danger"><?= formatCurrency($row['deduction']) ?></td>
                        <td class="text-end fw-semibold"><?= formatCurrency($row['net_salary']) ?></td>
                        <td class="text-center">
                            <span class="badge <?= $isPaid ? 'bg-success' : 'bg-warning text-dark' ?>">
                                <?= htmlspecialchars($row['status']) ?>
                            </span>
                        </td>
                        <td><?= $row['payment_date'] ? htmlspecialchars(formatDate($row['payment_date'])) : '<span class="text-muted">-</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td colspan="4" class="text-end">Totals:</td>
                        <td class="text-end"><?= formatCurrency($total_basic) ?></td>
                        <td class="text-end"><?= formatCurrency($total_allowance) ?></td>
                        <td class="text-end text-danger"><?= formatCurrency($total_deduction) ?></td>
                        <td class="text-end"><?= formatCurrency($total_net) ?></td>
                        <td class="text-center">
                            <span class="badge bg-success me-1"><?= $count_paid ?> Paid</span>
                            <span class="badge bg-warning text-dark"><?= $count_pending ?> Pending</span>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>No salary records found for the selected criteria.
    </div>
    <?php endif; ?>

</div><!-- content-area -->
</div><!-- main-content -->
</div><!-- wrapper -->

<script>
document.getElementById('exportExcel')?.addEventListener('click', function () {
    const table = document.getElementById('salaryTable');
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
    a.download = 'salary_report_<?= sprintf('%04d-%02d', $filter_year, $filter_month) ?>.csv';
    a.click();
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
