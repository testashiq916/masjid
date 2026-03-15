<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/app/middleware/auth_check.php';
requireLogin();
require_once __DIR__ . '/config/constants.php';

// Dashboard stats
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

$todayIncome  = getTotalIncome($today, $today);
$todayExpense = getTotalExpense($today, $today);
$monthIncome  = getTotalIncome($monthStart, $monthEnd);
$monthExpense = getTotalExpense($monthStart, $monthEnd);
$cashBalance  = getCashBalance();
$bankBalance  = getBankBalance();

// Pending rent
$stmt = db()->query("SELECT COUNT(*) as cnt, COALESCE(SUM(due_amount - paid_amount), 0) as total
                     FROM rent_collections WHERE status IN ('pending','partial','overdue')");
$pendingRent = $stmt->fetch();

// Pending salary
$stmt = db()->query("SELECT COUNT(*) as cnt, COALESCE(SUM(net_salary), 0) as total
                     FROM salary_sheet WHERE status = 'pending'");
$pendingSalary = $stmt->fetch();

// Pending approvals
$pendingApprovalCount = (int)db()->query(
    "SELECT COUNT(*) FROM payments WHERE approval_status='pending_approval' AND deleted_at IS NULL"
)->fetchColumn();

// Recent receipts
$stmt = db()->query("SELECT r.*, ic.category_name FROM receipts r
                     LEFT JOIN income_categories ic ON ic.id = r.category_id
                     WHERE r.deleted_at IS NULL ORDER BY r.created_at DESC LIMIT 8");
$recentReceipts = $stmt->fetchAll();

// Recent payments
$stmt = db()->query("SELECT p.*, ec.category_name FROM payments p
                     LEFT JOIN expense_categories ec ON ec.id = p.category_id
                     WHERE p.deleted_at IS NULL ORDER BY p.created_at DESC LIMIT 8");
$recentPayments = $stmt->fetchAll();

// Monthly income chart data (last 6 months)
$chartData = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $start = $m . '-01';
    $end   = date('Y-m-t', strtotime($start));
    $income  = getTotalIncome($start, $end);
    $expense = getTotalExpense($start, $end);
    $chartData['labels'][]   = date('M Y', strtotime($start));
    $chartData['income'][]   = $income;
    $chartData['expense'][]  = $expense;
}

require_once __DIR__ . '/templates/header.php';
?>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">
<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h4><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">Home</li>
            </ol>
        </nav>
    </div>
    <div class="text-muted small"><i class="bi bi-calendar3 me-1"></i><?= date('d F Y') ?></div>
</div>

<!-- Stat Cards Row 1 -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-card-icon bg-success bg-opacity-15 text-success rounded-3 p-3">
                    <i class="bi bi-arrow-down-circle fs-4"></i>
                </div>
                <div>
                    <div class="stat-value text-success">₹<?= number_format($todayIncome, 0) ?></div>
                    <div class="stat-label text-muted">Today's Income</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-card-icon bg-danger bg-opacity-15 text-danger rounded-3 p-3">
                    <i class="bi bi-arrow-up-circle fs-4"></i>
                </div>
                <div>
                    <div class="stat-value text-danger">₹<?= number_format($todayExpense, 0) ?></div>
                    <div class="stat-label text-muted">Today's Expense</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-card-icon bg-primary bg-opacity-15 text-primary rounded-3 p-3">
                    <i class="bi bi-cash fs-4"></i>
                </div>
                <div>
                    <div class="stat-value text-primary">₹<?= number_format($cashBalance, 0) ?></div>
                    <div class="stat-label text-muted">Cash in Hand</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-card-icon bg-info bg-opacity-15 text-info rounded-3 p-3">
                    <i class="bi bi-bank fs-4"></i>
                </div>
                <div>
                    <div class="stat-value text-info">₹<?= number_format($bankBalance, 0) ?></div>
                    <div class="stat-label text-muted">Bank Balance</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stat Cards Row 2 -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-1"><i class="bi bi-graph-up-arrow text-success me-1"></i>Monthly Income</div>
                <div class="fw-bold fs-5 text-success">₹<?= number_format($monthIncome, 2) ?></div>
                <div class="text-muted" style="font-size:0.75rem;"><?= date('F Y') ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-1"><i class="bi bi-graph-down-arrow text-danger me-1"></i>Monthly Expense</div>
                <div class="fw-bold fs-5 text-danger">₹<?= number_format($monthExpense, 2) ?></div>
                <div class="text-muted" style="font-size:0.75rem;"><?= date('F Y') ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-1"><i class="bi bi-house-exclamation text-warning me-1"></i>Pending Rent</div>
                <div class="fw-bold fs-5 text-warning">₹<?= number_format($pendingRent['total'], 2) ?></div>
                <div class="text-muted" style="font-size:0.75rem;"><?= $pendingRent['cnt'] ?> tenant(s)</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-1"><i class="bi bi-wallet2 text-secondary me-1"></i>Pending Salary</div>
                <div class="fw-bold fs-5 text-secondary">₹<?= number_format($pendingSalary['total'], 2) ?></div>
                <div class="text-muted" style="font-size:0.75rem;"><?= $pendingSalary['cnt'] ?> record(s)</div>
            </div>
        </div>
    </div>
    <?php if ($pendingApprovalCount > 0 && in_array(currentUserRole(), [ROLE_ADMIN, ROLE_ACCOUNTANT, ROLE_COMMITTEE])): ?>
    <div class="col-6 col-md-3">
        <a href="<?= BASE_PATH ?>/modules/expenses/pending_approvals.php" class="text-decoration-none">
            <div class="card stat-card shadow-sm h-100 border-warning">
                <div class="card-body">
                    <div class="text-muted small mb-1"><i class="bi bi-shield-exclamation text-warning me-1"></i>Pending Approvals</div>
                    <div class="fw-bold fs-5 text-warning"><?= $pendingApprovalCount ?> voucher(s)</div>
                    <div class="text-muted" style="font-size:0.75rem;">Click to review</div>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Chart + Surplus/Deficit -->
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-bar-chart-line me-2 text-primary"></i>Income vs Expense (Last 6 Months)
            </div>
            <div class="card-body">
                <canvas id="incomeExpenseChart" height="90"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-pie-chart me-2 text-primary"></i>Monthly Summary
            </div>
            <div class="card-body">
                <?php
                $surplus = $monthIncome - $monthExpense;
                $surplusClass = $surplus >= 0 ? 'text-success' : 'text-danger';
                $surplusLabel = $surplus >= 0 ? 'Surplus' : 'Deficit';
                ?>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="text-muted">Total Income</span>
                    <span class="fw-semibold text-success">₹<?= number_format($monthIncome, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="text-muted">Total Expense</span>
                    <span class="fw-semibold text-danger">₹<?= number_format($monthExpense, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="fw-bold"><?= $surplusLabel ?></span>
                    <span class="fw-bold <?= $surplusClass ?>">₹<?= number_format(abs($surplus), 2) ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Cash Balance</span>
                    <span class="fw-semibold">₹<?= number_format($cashBalance, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Bank Balance</span>
                    <span class="fw-semibold">₹<?= number_format($bankBalance, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-top mt-2">
                    <span class="fw-bold">Total Funds</span>
                    <span class="fw-bold text-primary">₹<?= number_format($cashBalance + $bankBalance, 2) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-lightning me-2 text-warning"></i>Quick Actions
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= BASE_PATH ?>/modules/donations/add_receipt.php" class="btn btn-success btn-sm">
                        <i class="bi bi-plus-circle me-1"></i>Add Receipt
                    </a>
                    <a href="<?= BASE_PATH ?>/modules/expenses/add_payment.php" class="btn btn-danger btn-sm">
                        <i class="bi bi-plus-circle me-1"></i>Add Payment
                    </a>
                    <a href="<?= BASE_PATH ?>/modules/accounts/journal_entry.php" class="btn btn-secondary btn-sm">
                        <i class="bi bi-pencil-square me-1"></i>Journal Entry
                    </a>
                    <a href="<?= BASE_PATH ?>/modules/waqf/rent_collection.php" class="btn btn-warning btn-sm">
                        <i class="bi bi-house me-1"></i>Collect Rent
                    </a>
                    <a href="<?= BASE_PATH ?>/modules/salary/salary_sheet.php" class="btn btn-info btn-sm text-white">
                        <i class="bi bi-wallet2 me-1"></i>Salary Sheet
                    </a>
                    <a href="<?= BASE_PATH ?>/modules/reports/income_expenditure.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-file-earmark-bar-graph me-1"></i>I&E Report
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-arrow-down-circle text-success me-2"></i>Recent Receipts</span>
                <a href="<?= BASE_PATH ?>/modules/donations/receipt_list.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Date</th>
                                <th>Category</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentReceipts)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No receipts yet</td></tr>
                            <?php else: ?>
                            <?php foreach ($recentReceipts as $r): ?>
                            <tr>
                                <td><a href="<?= BASE_PATH ?>/modules/donations/view_receipt.php?id=<?= $r['id'] ?>" class="text-decoration-none fw-semibold"><?= htmlspecialchars($r['receipt_no']) ?></a></td>
                                <td class="text-muted small"><?= formatDate($r['date']) ?></td>
                                <td class="small"><?= htmlspecialchars($r['category_name'] ?? '-') ?></td>
                                <td class="text-end fw-semibold text-success">₹<?= number_format($r['amount'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-arrow-up-circle text-danger me-2"></i>Recent Payments</span>
                <a href="<?= BASE_PATH ?>/modules/expenses/payment_list.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Voucher #</th>
                                <th>Date</th>
                                <th>Category</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentPayments)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No payments yet</td></tr>
                            <?php else: ?>
                            <?php foreach ($recentPayments as $p): ?>
                            <tr>
                                <td><a href="<?= BASE_PATH ?>/modules/expenses/view_payment.php?id=<?= $p['id'] ?>" class="text-decoration-none fw-semibold"><?= htmlspecialchars($p['voucher_no']) ?></a></td>
                                <td class="text-muted small"><?= formatDate($p['date']) ?></td>
                                <td class="small"><?= htmlspecialchars($p['category_name'] ?? '-') ?></td>
                                <td class="text-end fw-semibold text-danger">₹<?= number_format($p['amount'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- content-area -->
</div><!-- main-content -->
</div><!-- wrapper -->

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
<script>
(function () {
    const ctx = document.getElementById('incomeExpenseChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartData['labels']) ?>,
            datasets: [
                {
                    label: 'Income',
                    data: <?= json_encode($chartData['income']) ?>,
                    backgroundColor: 'rgba(25, 135, 84, 0.7)',
                    borderColor: 'rgba(25, 135, 84, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                },
                {
                    label: 'Expense',
                    data: <?= json_encode($chartData['expense']) ?>,
                    backgroundColor: 'rgba(220, 53, 69, 0.7)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: (ctx) => '₹ ' + ctx.raw.toLocaleString('en-IN', {minimumFractionDigits: 2})
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (val) => '₹' + (val >= 1000 ? (val/1000).toFixed(0) + 'K' : val)
                    }
                }
            }
        }
    });
})();
</script>
