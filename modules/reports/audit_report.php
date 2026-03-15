<?php
$pageTitle = 'Audit Report';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

$fyId   = (int)($_GET['fy_id'] ?? 0);
$fyList = db()->query("SELECT * FROM financial_years ORDER BY start_date DESC")->fetchAll();

$fy = null;
if ($fyId) {
    $stmt = db()->prepare("SELECT * FROM financial_years WHERE id=?");
    $stmt->execute([$fyId]);
    $fy = $stmt->fetch();
}

if (!$fy && !empty($fyList)) {
    $fy   = $fyList[0];
    $fyId = $fy['id'];
}

$data = null;
if ($fy) {
    $s = $fy['start_date']; $e = $fy['end_date'];

    // Summary
    $totalIncome  = getTotalIncome($s, $e);
    $totalExpense = getTotalExpense($s, $e);
    $surplus      = $totalIncome - $totalExpense;

    // Receipts
    $receipts = db()->prepare("SELECT r.*, ic.category_name FROM receipts r
        LEFT JOIN income_categories ic ON ic.id=r.category_id
        WHERE r.date BETWEEN ? AND ? AND r.deleted_at IS NULL ORDER BY r.date");
    $receipts->execute([$s, $e]);
    $receiptList = $receipts->fetchAll();

    // Payments
    $payments = db()->prepare("SELECT p.*, ec.category_name FROM payments p
        LEFT JOIN expense_categories ec ON ec.id=p.category_id
        WHERE p.date BETWEEN ? AND ? AND p.deleted_at IS NULL ORDER BY p.date");
    $payments->execute([$s, $e]);
    $paymentList = $payments->fetchAll();

    // Income by category
    $incomeBycat = db()->prepare("SELECT ic.category_name, SUM(r.amount) as total
        FROM receipts r LEFT JOIN income_categories ic ON ic.id=r.category_id
        WHERE r.date BETWEEN ? AND ? AND r.deleted_at IS NULL GROUP BY r.category_id ORDER BY total DESC");
    $incomeBycat->execute([$s, $e]);
    $incomeSummary = $incomeBycat->fetchAll();

    // Expense by category
    $expByCat = db()->prepare("SELECT ec.category_name, SUM(p.amount) as total
        FROM payments p LEFT JOIN expense_categories ec ON ec.id=p.category_id
        WHERE p.date BETWEEN ? AND ? AND p.deleted_at IS NULL GROUP BY p.category_id ORDER BY total DESC");
    $expByCat->execute([$s, $e]);
    $expenseSummary = $expByCat->fetchAll();

    // Cash & bank closing
    $cashBalance = getCashBalance();
    $bankBalance = getBankBalance();

    $data = compact('totalIncome','totalExpense','surplus','receiptList','paymentList','incomeSummary','expenseSummary','cashBalance','bankBalance');
}

require_once __DIR__ . '/../../templates/header.php';
?>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

<div class="page-header">
    <div><h4><i class="bi bi-shield-check me-2 text-primary"></i>Audit Report</h4>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Home</a></li>
        <li class="breadcrumb-item">Reports</li>
        <li class="breadcrumb-item active">Audit Report</li>
    </ol></nav></div>
    <?php if ($fy): ?>
    <button class="btn btn-sm btn-outline-secondary no-print" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
    <?php endif; ?>
</div>

<!-- FY Selector -->
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Financial Year</label>
                <select name="fy_id" class="form-select">
                    <?php foreach ($fyList as $f): ?>
                    <option value="<?= $f['id'] ?>" <?= $f['id'] == $fyId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($f['year_label']) ?> (<?= $f['status'] ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>View</button>
            </div>
        </form>
    </div>
</div>

<?php if ($fy && $data): ?>
<!-- Header -->
<div class="card shadow-sm mb-3">
    <div class="card-body text-center">
        <h5 class="fw-bold"><?= htmlspecialchars(getMasjidProfile()['masjid_name'] ?? 'Masjid ERP') ?></h5>
        <h6>ANNUAL AUDIT REPORT</h6>
        <p class="text-muted">Financial Year: <?= htmlspecialchars($fy['year_label']) ?> | Period: <?= formatDate($fy['start_date']) ?> to <?= formatDate($fy['end_date']) ?></p>
    </div>
</div>

<!-- Section 1: Financial Summary -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-primary text-white fw-semibold">1. Financial Summary</div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4"><div class="card bg-success text-white"><div class="card-body text-center"><div class="small">Total Income</div><div class="fw-bold fs-4">₹<?= number_format($data['totalIncome'], 2) ?></div></div></div></div>
            <div class="col-md-4"><div class="card bg-danger text-white"><div class="card-body text-center"><div class="small">Total Expense</div><div class="fw-bold fs-4">₹<?= number_format($data['totalExpense'], 2) ?></div></div></div></div>
            <div class="col-md-4"><div class="card <?= $data['surplus'] >= 0 ? 'bg-info' : 'bg-warning' ?> text-white"><div class="card-body text-center"><div class="small"><?= $data['surplus'] >= 0 ? 'Surplus' : 'Deficit' ?></div><div class="fw-bold fs-4">₹<?= number_format(abs($data['surplus']), 2) ?></div></div></div></div>
        </div>
    </div>
</div>

<!-- Section 2: Income Summary -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-success text-white fw-semibold">2. Income Summary</div>
    <div class="card-body p-0">
        <table class="table table-bordered mb-0">
            <thead class="table-light"><tr><th>Income Category</th><th class="text-end">Amount (₹)</th></tr></thead>
            <tbody>
                <?php foreach ($data['incomeSummary'] as $row): ?>
                <tr><td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></td>
                    <td class="text-end">₹<?= number_format($row['total'], 2) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="fw-bold table-success"><td>Total Income</td><td class="text-end">₹<?= number_format($data['totalIncome'], 2) ?></td></tr></tfoot>
        </table>
    </div>
</div>

<!-- Section 3: Expense Summary -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-danger text-white fw-semibold">3. Expense Summary</div>
    <div class="card-body p-0">
        <table class="table table-bordered mb-0">
            <thead class="table-light"><tr><th>Expense Category</th><th class="text-end">Amount (₹)</th></tr></thead>
            <tbody>
                <?php foreach ($data['expenseSummary'] as $row): ?>
                <tr><td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></td>
                    <td class="text-end">₹<?= number_format($row['total'], 2) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="fw-bold table-danger"><td>Total Expense</td><td class="text-end">₹<?= number_format($data['totalExpense'], 2) ?></td></tr></tfoot>
        </table>
    </div>
</div>

<!-- Section 4: Cash & Bank -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-info text-white fw-semibold">4. Cash & Bank Position</div>
    <div class="card-body">
        <table class="table table-bordered">
            <tr><td>Cash in Hand</td><td class="text-end">₹<?= number_format($data['cashBalance'], 2) ?></td></tr>
            <tr><td>Bank Balance</td><td class="text-end">₹<?= number_format($data['bankBalance'], 2) ?></td></tr>
            <tr class="fw-bold table-light"><td>Total Available</td><td class="text-end">₹<?= number_format($data['cashBalance'] + $data['bankBalance'], 2) ?></td></tr>
        </table>
    </div>
</div>

<!-- Section 5: Receipt Register -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">5. Receipt Register (<?= count($data['receiptList']) ?> records)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0">
                <thead class="table-light"><tr><th>Receipt No</th><th>Date</th><th>Donor</th><th>Category</th><th>Mode</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($data['receiptList'] as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['receipt_no']) ?></td>
                        <td><?= formatDate($r['date']) ?></td>
                        <td><?= htmlspecialchars($r['donor_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['category_name'] ?? '-') ?></td>
                        <td><?= strtoupper($r['payment_mode']) ?></td>
                        <td class="text-end">₹<?= number_format($r['amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="fw-bold table-success"><td colspan="5">Total</td><td class="text-end">₹<?= number_format($data['totalIncome'], 2) ?></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Section 6: Payment Register -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">6. Payment Register (<?= count($data['paymentList']) ?> records)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0">
                <thead class="table-light"><tr><th>Voucher No</th><th>Date</th><th>Payee</th><th>Category</th><th>Mode</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($data['paymentList'] as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['voucher_no']) ?></td>
                        <td><?= formatDate($p['date']) ?></td>
                        <td><?= htmlspecialchars($p['payee_name']) ?></td>
                        <td><?= htmlspecialchars($p['category_name'] ?? '-') ?></td>
                        <td><?= strtoupper($p['payment_mode']) ?></td>
                        <td class="text-end">₹<?= number_format($p['amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="fw-bold table-danger"><td colspan="5">Total</td><td class="text-end">₹<?= number_format($data['totalExpense'], 2) ?></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>

<div class="text-center text-muted small mt-3 no-print">
    Report generated on <?= date('d/m/Y H:i') ?> by <?= htmlspecialchars(currentUser()['name'] ?? '') ?>
</div>

<?php endif; ?>

</div></div></div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
