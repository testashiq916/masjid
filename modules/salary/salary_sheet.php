<?php
$pageTitle = 'Salary Sheet';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

$pdo = db();

$selectedMonth = sanitize($_GET['month'] ?? date('Y-m'));
$staffFilter   = (int)($_GET['staff_id'] ?? 0);

// Handle Generate: Create salary records for all active staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/modules/salary/salary_sheet.php?month=' . $selectedMonth);
    }

    $genMonth = sanitize($_POST['gen_month'] ?? date('Y-m'));
    $activeStaff = $pdo->query("SELECT id, basic_salary, allowance FROM staff WHERE deleted_at IS NULL AND status = 'active'")->fetchAll();
    $created = 0;
    foreach ($activeStaff as $s) {
        $exists = $pdo->prepare("SELECT id FROM salary_sheet WHERE staff_id = ? AND salary_month = ? AND deleted_at IS NULL");
        $exists->execute([$s['id'], $genMonth]);
        if (!$exists->fetch()) {
            $pdo->prepare("INSERT INTO salary_sheet (staff_id, salary_month, basic_salary, allowance, deduction, net_salary, status, created_at, updated_at) VALUES (?,?,?,?,0,?,  'pending',NOW(),NOW())")
                ->execute([$s['id'], $genMonth, $s['basic_salary'], $s['allowance'], (float)$s['basic_salary'] + (float)$s['allowance']]);
            $created++;
        }
    }
    setFlash('success', "$created salary record(s) generated for " . date('F Y', strtotime($genMonth . '-01')) . ".");
    redirect(BASE_PATH . '/modules/salary/salary_sheet.php?month=' . $genMonth);
}

// Handle Save Deductions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_deductions'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/modules/salary/salary_sheet.php?month=' . $selectedMonth);
    }

    $deductions = $_POST['deduction'] ?? [];
    $saveMonth  = sanitize($_POST['save_month'] ?? date('Y-m'));

    foreach ($deductions as $ssId => $dedAmt) {
        $ssId   = (int)$ssId;
        $dedAmt = (float)$dedAmt;

        $rec = $pdo->prepare("SELECT basic_salary, allowance FROM salary_sheet WHERE id = ? AND deleted_at IS NULL");
        $rec->execute([$ssId]);
        $row = $rec->fetch();

        if ($row) {
            $net = (float)$row['basic_salary'] + (float)$row['allowance'] - $dedAmt;
            $pdo->prepare("UPDATE salary_sheet SET deduction=?, net_salary=?, updated_at=NOW() WHERE id=?")
                ->execute([$dedAmt, $net, $ssId]);
        }
    }
    setFlash('success', 'Deductions saved successfully.');
    redirect(BASE_PATH . '/modules/salary/salary_sheet.php?month=' . $saveMonth);
}

// Fetch salary sheet for selected month
$where = "ss.deleted_at IS NULL AND ss.salary_month = ?";
$params = [$selectedMonth];
if ($staffFilter > 0) {
    $where .= " AND ss.staff_id = ?";
    $params[] = $staffFilter;
}

$stmt = $pdo->prepare("
    SELECT ss.*, s.staff_name, s.staff_code, s.designation, s.phone
    FROM salary_sheet ss
    JOIN staff s ON s.id = ss.staff_id
    WHERE $where
    ORDER BY s.staff_name
");
$stmt->execute($params);
$salaryRecords = $stmt->fetchAll();

// Totals
$totBasic = $totAllowance = $totDeduction = $totNet = 0;
foreach ($salaryRecords as $r) {
    $totBasic     += (float)$r['basic_salary'];
    $totAllowance += (float)$r['allowance'];
    $totDeduction += (float)$r['deduction'];
    $totNet       += (float)$r['net_salary'];
}

// Staff for filter
$allStaff = $pdo->query("SELECT id, staff_code, staff_name FROM staff WHERE deleted_at IS NULL ORDER BY staff_name")->fetchAll();

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
        <h4 class="mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Salary Sheet</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Home</a></li>
            <li class="breadcrumb-item">Salary</li>
            <li class="breadcrumb-item active">Salary Sheet</li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>

<!-- Month Selector & Generate -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Select Month</label>
                <input type="month" id="monthPicker" class="form-control" value="<?= htmlspecialchars($selectedMonth) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Filter by Staff</label>
                <select id="staffPicker" class="form-select">
                    <option value="0">All Staff</option>
                    <?php foreach ($allStaff as $sf): ?>
                    <option value="<?= $sf['id'] ?>" <?= $staffFilter == $sf['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sf['staff_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" onclick="applyFilter()">
                    <i class="bi bi-filter me-1"></i>View
                </button>
            </div>
            <div class="col-md-4">
                <form method="POST" class="d-inline" onsubmit="return confirm('Generate salary sheet for all active staff for this month?')">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                    <input type="hidden" name="generate" value="1">
                    <input type="hidden" name="gen_month" id="gen_month_hidden" value="<?= htmlspecialchars($selectedMonth) ?>">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-magic me-1"></i>Generate Salary Sheet for <?= date('F Y', strtotime($selectedMonth . '-01')) ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Summary -->
<?php if (!empty($salaryRecords)): ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-primary"><?= count($salaryRecords) ?></div>
                <div class="small text-muted">Staff Count</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-info"><?= formatCurrency($totBasic + $totAllowance) ?></div>
                <div class="small text-muted">Gross Payable</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-danger"><?= formatCurrency($totDeduction) ?></div>
                <div class="small text-muted">Total Deductions</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-success"><?= formatCurrency($totNet) ?></div>
                <div class="small text-muted">Net Payable</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Salary Sheet Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-table me-2"></i>Salary Sheet - <?= date('F Y', strtotime($selectedMonth . '-01')) ?></h6>
        <?php if (!empty($salaryRecords)): ?>
        <span class="badge bg-primary"><?= count($salaryRecords) ?> records</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($salaryRecords)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-file-earmark-text fs-1 d-block mb-3 text-light-emphasis"></i>
            <p>No salary records for <?= date('F Y', strtotime($selectedMonth . '-01')) ?>.</p>
            <p class="small">Use the "Generate Salary Sheet" button to create records for all active staff.</p>
        </div>
        <?php else: ?>
        <form method="POST" id="deductionForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="save_deductions" value="1">
            <input type="hidden" name="save_month" value="<?= htmlspecialchars($selectedMonth) ?>">
            <div class="table-responsive">
                <table class="table table-hover align-middle small" id="salaryTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Designation</th>
                            <th>Basic</th>
                            <th>Allowance</th>
                            <th>Deduction</th>
                            <th>Net Salary</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($salaryRecords as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($r['staff_code']) ?></span></td>
                        <td class="fw-semibold"><?= htmlspecialchars($r['staff_name']) ?></td>
                        <td><span class="text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $r['designation'])) ?></span></td>
                        <td><?= formatCurrency((float)$r['basic_salary']) ?></td>
                        <td><?= formatCurrency((float)$r['allowance']) ?></td>
                        <td>
                            <?php if ($r['status'] === 'pending'): ?>
                            <input type="number" name="deduction[<?= $r['id'] ?>]" class="form-control form-control-sm deduction-input" style="width:120px;"
                                value="<?= number_format((float)$r['deduction'], 2, '.', '') ?>"
                                min="0" step="0.01"
                                data-basic="<?= (float)$r['basic_salary'] ?>"
                                data-allowance="<?= (float)$r['allowance'] ?>"
                                data-id="<?= $r['id'] ?>"
                                onchange="recalcNet(this)">
                            <?php else: ?>
                            <?= formatCurrency((float)$r['deduction']) ?>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold net-cell-<?= $r['id'] ?>"><?= formatCurrency((float)$r['net_salary']) ?></td>
                        <td>
                            <?php
                            $stMap = ['pending'=>'warning','paid'=>'success','processing'=>'info'];
                            $sb = $stMap[$r['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $sb ?>"><?= ucfirst($r['status']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-secondary fw-bold">
                        <tr>
                            <td colspan="4" class="text-end">Totals</td>
                            <td><?= formatCurrency($totBasic) ?></td>
                            <td><?= formatCurrency($totAllowance) ?></td>
                            <td><?= formatCurrency($totDeduction) ?></td>
                            <td><?= formatCurrency($totNet) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Save Deductions
                </button>
                <a href="<?= BASE_PATH ?>/modules/salary/salary_payment.php?month=<?= urlencode($selectedMonth) ?>" class="btn btn-success ms-2">
                    <i class="bi bi-cash me-1"></i>Process Payments
                </a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

</div></div></div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
<script>
function applyFilter() {
    var month = document.getElementById('monthPicker').value;
    var staffId = document.getElementById('staffPicker').value;
    window.location.href = '<?= BASE_PATH ?>/modules/salary/salary_sheet.php?month=' + month + '&staff_id=' + staffId;
}

document.getElementById('monthPicker').addEventListener('change', function() {
    document.getElementById('gen_month_hidden').value = this.value;
});

function recalcNet(input) {
    var basic     = parseFloat(input.dataset.basic) || 0;
    var allowance = parseFloat(input.dataset.allowance) || 0;
    var deduction = parseFloat(input.value) || 0;
    var net       = basic + allowance - deduction;
    var id        = input.dataset.id;
    var cells     = document.querySelectorAll('.net-cell-' + id);
    cells.forEach(function(cell) {
        cell.textContent = '₹ ' + net.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    });
}
</script>
