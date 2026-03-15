<?php
$pageTitle = 'Salary Payment';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

$pdo = db();

$filterMonth = sanitize($_GET['month'] ?? date('Y-m'));

// Handle Pay action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/modules/salary/salary_payment.php?month=' . $filterMonth);
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'pay') {
        $ss_id        = (int)($_POST['ss_id'] ?? 0);
        $payment_date = sanitize($_POST['payment_date'] ?? date('Y-m-d'));
        $payment_mode = sanitize($_POST['payment_mode'] ?? 'bank_transfer');
        $bank_ref     = sanitize($_POST['bank_ref'] ?? '');
        $voucher_no   = generateNumber('SAL', 'salary_sheet', 'voucher_no');
        $remarks      = sanitize($_POST['remarks'] ?? '');

        if ($ss_id > 0) {
            $pdo->prepare("UPDATE salary_sheet SET status='paid', payment_date=?, payment_mode=?, bank_ref=?, voucher_no=?, remarks=?, updated_at=NOW() WHERE id=? AND deleted_at IS NULL")
                ->execute([$payment_date, $payment_mode, $bank_ref, $voucher_no, $remarks, $ss_id]);
            setFlash('success', "Salary paid. Voucher No: $voucher_no");
        }

        $postMonth = sanitize($_POST['filter_month'] ?? $filterMonth);
        redirect(BASE_PATH . '/modules/salary/salary_payment.php?month=' . $postMonth);
    }

    if ($action === 'pay_all') {
        $payMonth     = sanitize($_POST['pay_month'] ?? date('Y-m'));
        $payment_date = sanitize($_POST['bulk_payment_date'] ?? date('Y-m-d'));
        $payment_mode = sanitize($_POST['bulk_payment_mode'] ?? 'bank_transfer');
        $remarks      = sanitize($_POST['bulk_remarks'] ?? '');

        $pending = $pdo->prepare("SELECT id FROM salary_sheet WHERE salary_month=? AND status='pending' AND deleted_at IS NULL");
        $pending->execute([$payMonth]);
        $rows = $pending->fetchAll();
        $count = 0;
        foreach ($rows as $row) {
            $voucher_no = generateNumber('SAL', 'salary_sheet', 'voucher_no');
            $pdo->prepare("UPDATE salary_sheet SET status='paid', payment_date=?, payment_mode=?, bank_ref='', voucher_no=?, remarks=?, updated_at=NOW() WHERE id=?")
                ->execute([$payment_date, $payment_mode, $voucher_no, $remarks, $row['id']]);
            $count++;
        }
        setFlash('success', "$count salary payment(s) processed.");
        redirect(BASE_PATH . '/modules/salary/salary_payment.php?month=' . $payMonth);
    }
}

// Fetch pending salary records for selected month
$stmt = $pdo->prepare("
    SELECT ss.*, s.staff_name, s.staff_code, s.designation, s.bank_name, s.bank_account
    FROM salary_sheet ss
    JOIN staff s ON s.id = ss.staff_id
    WHERE ss.deleted_at IS NULL AND ss.salary_month = ?
    ORDER BY ss.status ASC, s.staff_name ASC
");
$stmt->execute([$filterMonth]);
$allRecords = $stmt->fetchAll();

$pendingRecords = array_filter($allRecords, fn($r) => $r['status'] === 'pending');
$paidRecords    = array_filter($allRecords, fn($r) => $r['status'] === 'paid');

$totPending = array_sum(array_column(array_values($pendingRecords), 'net_salary'));
$totPaid    = array_sum(array_column(array_values($paidRecords), 'net_salary'));

// Available months
$monthsStmt = $pdo->query("SELECT DISTINCT salary_month FROM salary_sheet WHERE deleted_at IS NULL ORDER BY salary_month DESC");
$months = $monthsStmt->fetchAll(PDO::FETCH_COLUMN);

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
        <h4 class="mb-0"><i class="bi bi-cash-stack me-2 text-primary"></i>Salary Payment</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Home</a></li>
            <li class="breadcrumb-item">Salary</li>
            <li class="breadcrumb-item active">Payment</li>
        </ol></nav>
    </div>
</div>

<!-- Month Filter -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Select Month</label>
                <select id="monthSelect" class="form-select form-select-sm" onchange="filterMonth(this.value)">
                    <?php foreach ($months as $m): ?>
                    <option value="<?= $m ?>" <?= $filterMonth === $m ? 'selected' : '' ?>><?= date('F Y', strtotime($m . '-01')) ?></option>
                    <?php endforeach; ?>
                    <?php if (empty($months)): ?>
                    <option value="<?= $filterMonth ?>"><?= date('F Y', strtotime($filterMonth . '-01')) ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary btn-sm" onclick="filterMonth(document.getElementById('monthSelect').value)">
                    <i class="bi bi-filter me-1"></i>View
                </button>
            </div>
            <?php if (!empty($pendingRecords)): ?>
            <div class="col-md-7 text-end">
                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#bulkPayModal">
                    <i class="bi bi-lightning me-1"></i>Pay All Pending (<?= count($pendingRecords) ?>) - <?= formatCurrency($totPending) ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-warning"><?= count($pendingRecords) ?></div>
                <div class="small text-muted">Pending</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-success"><?= count($paidRecords) ?></div>
                <div class="small text-muted">Paid</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-danger"><?= formatCurrency($totPending) ?></div>
                <div class="small text-muted">Pending Amount</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-5 fw-bold text-success"><?= formatCurrency($totPaid) ?></div>
                <div class="small text-muted">Paid Amount</div>
            </div>
        </div>
    </div>
</div>

<!-- Pending Records -->
<?php if (!empty($pendingRecords)): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-warning bg-opacity-10 py-3">
        <h6 class="mb-0 text-warning"><i class="bi bi-clock me-2"></i>Pending Salary Payments</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle small">
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
                        <th>Bank</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pendingRecords as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($r['staff_code']) ?></span></td>
                    <td class="fw-semibold"><?= htmlspecialchars($r['staff_name']) ?></td>
                    <td><span class="text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $r['designation'])) ?></span></td>
                    <td><?= formatCurrency((float)$r['basic_salary']) ?></td>
                    <td><?= formatCurrency((float)$r['allowance']) ?></td>
                    <td class="text-danger"><?= formatCurrency((float)$r['deduction']) ?></td>
                    <td class="fw-bold text-success"><?= formatCurrency((float)$r['net_salary']) ?></td>
                    <td>
                        <?php if ($r['bank_name']): ?>
                        <small><?= htmlspecialchars($r['bank_name']) ?><br><?= htmlspecialchars($r['bank_account']) ?></small>
                        <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-success"
                            onclick="openPayModal(<?= $r['id'] ?>, '<?= htmlspecialchars($r['staff_name']) ?>', <?= (float)$r['net_salary'] ?>)">
                            <i class="bi bi-cash me-1"></i>Pay
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Paid Records -->
<?php if (!empty($paidRecords)): ?>
<div class="card shadow-sm">
    <div class="card-header bg-success bg-opacity-10 py-3">
        <h6 class="mb-0 text-success"><i class="bi bi-check-circle me-2"></i>Paid Salaries</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle small">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Net Salary</th>
                        <th>Payment Date</th>
                        <th>Mode</th>
                        <th>Voucher No.</th>
                        <th>Bank Ref</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($paidRecords as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($r['staff_code']) ?></span></td>
                    <td class="fw-semibold"><?= htmlspecialchars($r['staff_name']) ?></td>
                    <td class="fw-bold text-success"><?= formatCurrency((float)$r['net_salary']) ?></td>
                    <td><?= $r['payment_date'] ? formatDate($r['payment_date']) : '-' ?></td>
                    <td><span class="text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $r['payment_mode'] ?? '')) ?></span></td>
                    <td><?= $r['voucher_no'] ? '<span class="badge bg-success">' . htmlspecialchars($r['voucher_no']) . '</span>' : '-' ?></td>
                    <td><?= htmlspecialchars($r['bank_ref'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($allRecords)): ?>
<div class="card shadow-sm">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-calendar-x fs-1 d-block mb-3 text-light-emphasis"></i>
        <p>No salary records found for <?= date('F Y', strtotime($filterMonth . '-01')) ?>.</p>
        <a href="<?= BASE_PATH ?>/modules/salary/salary_sheet.php?month=<?= urlencode($filterMonth) ?>" class="btn btn-primary">
            <i class="bi bi-file-earmark-text me-1"></i>Generate Salary Sheet
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Pay Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="action" value="pay">
            <input type="hidden" name="ss_id" id="pay_ss_id">
            <input type="hidden" name="filter_month" value="<?= htmlspecialchars($filterMonth) ?>">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-cash me-2"></i>Process Salary Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small mb-3" id="pay_info"></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Mode</label>
                            <select name="payment_mode" class="form-select">
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                                <option value="upi">UPI</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Bank Ref / Transaction ID</label>
                            <input type="text" name="bank_ref" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Confirm Payment</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Pay Modal -->
<div class="modal fade" id="bulkPayModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="action" value="pay_all">
            <input type="hidden" name="pay_month" value="<?= htmlspecialchars($filterMonth) ?>">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-lightning me-2"></i>Pay All Pending Salaries</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small">
                        This will mark all <?= count($pendingRecords) ?> pending salary records as paid for <strong><?= date('F Y', strtotime($filterMonth . '-01')) ?></strong>.
                        Total Amount: <strong><?= formatCurrency($totPending) ?></strong>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" name="bulk_payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Mode</label>
                            <select name="bulk_payment_mode" class="form-select">
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="bulk_remarks" class="form-control" rows="2" placeholder="e.g. Monthly salary for <?= date('F Y', strtotime($filterMonth . '-01')) ?>"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Confirm paying all pending salaries?')">
                        <i class="bi bi-check-circle me-1"></i>Pay All
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

</div></div></div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
<script>
function filterMonth(m) {
    window.location.href = '<?= BASE_PATH ?>/modules/salary/salary_payment.php?month=' + m;
}

function openPayModal(id, name, net) {
    document.getElementById('pay_ss_id').value = id;
    document.getElementById('pay_info').innerHTML =
        '<strong>Staff:</strong> ' + name + ' | <strong>Net Salary:</strong> ₹' + parseFloat(net).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    var modal = new bootstrap.Modal(document.getElementById('payModal'));
    modal.show();
}
</script>
