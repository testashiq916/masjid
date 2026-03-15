<?php
$pageTitle = 'Income Report';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

require_once __DIR__ . '/../../app/helpers/functions.php';

$pdo = db();

// Filters
$from_date   = isset($_GET['from_date'])   ? sanitize($_GET['from_date'])   : date('Y-m-01');
$to_date     = isset($_GET['to_date'])     ? sanitize($_GET['to_date'])     : date('Y-m-d');
$category_id = isset($_GET['category_id']) ? sanitize($_GET['category_id']) : '';
$payment_mode = isset($_GET['payment_mode']) ? sanitize($_GET['payment_mode']) : '';

// Fetch categories for filter dropdown
$categories = $pdo->query("SELECT id, category_name FROM income_categories ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);

// Build query
$sql = "SELECT r.id, r.receipt_no, r.date, r.donor_name, r.payment_mode, r.amount,
               ic.category_name
        FROM receipts r
        LEFT JOIN income_categories ic ON ic.id = r.category_id
        WHERE r.deleted_at IS NULL
          AND r.date BETWEEN :from_date AND :to_date";
$params = [':from_date' => $from_date, ':to_date' => $to_date];

if ($category_id !== '') {
    $sql .= " AND r.category_id = :category_id";
    $params[':category_id'] = $category_id;
}
if ($payment_mode !== '') {
    $sql .= " AND r.payment_mode = :payment_mode";
    $params[':payment_mode'] = $payment_mode;
}
$sql .= " ORDER BY ic.category_name, r.date, r.id";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by category
$grouped    = [];
$grand_total = 0.0;
foreach ($rows as $row) {
    $cat = $row['category_name'] ?? 'Uncategorised';
    $grouped[$cat][] = $row;
    $grand_total += (float)$row['amount'];
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
        <h4 class="mb-0">Income Report</h4>
        <small>Period: <?= htmlspecialchars(formatDate($from_date)) ?> to <?= htmlspecialchars(formatDate($to_date)) ?></small>
    </div>

    <!-- Page heading -->
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h4 class="mb-0"><i class="bi bi-arrow-down-circle-fill text-success me-2"></i>Income Report</h4>
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
                    <label class="form-label form-label-sm mb-1">From Date</label>
                    <input type="date" name="from_date" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($from_date) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">To Date</label>
                    <input type="date" name="to_date" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($to_date) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm mb-1">Category</label>
                    <select name="category_id" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"
                                <?= ($category_id == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Payment Mode</label>
                    <select name="payment_mode" class="form-select form-select-sm">
                        <option value="">All Modes</option>
                        <option value="Cash"   <?= $payment_mode === 'Cash'   ? 'selected' : '' ?>>Cash</option>
                        <option value="Bank"   <?= $payment_mode === 'Bank'   ? 'selected' : '' ?>>Bank</option>
                        <option value="Cheque" <?= $payment_mode === 'Cheque' ? 'selected' : '' ?>>Cheque</option>
                        <option value="Online" <?= $payment_mode === 'Online' ? 'selected' : '' ?>>Online</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search"></i> Generate
                    </button>
                </div>
                <div class="col-md-1">
                    <a href="income_report.php" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($rows)): ?>
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-bordered table-sm mb-0" id="incomeTable">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Receipt No</th>
                        <th>Donor</th>
                        <th>Category</th>
                        <th>Payment Mode</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $serial = 1;
                foreach ($grouped as $cat_name => $cat_rows):
                    $cat_total = array_sum(array_column($cat_rows, 'amount'));
                ?>
                    <tr class="table-light">
                        <td colspan="7" class="fw-semibold text-primary">
                            <i class="bi bi-tag-fill me-1"></i><?= htmlspecialchars($cat_name) ?>
                        </td>
                    </tr>
                    <?php foreach ($cat_rows as $row): ?>
                    <tr>
                        <td><?= $serial++ ?></td>
                        <td><?= htmlspecialchars(formatDate($row['date'])) ?></td>
                        <td><?= htmlspecialchars($row['receipt_no']) ?></td>
                        <td><?= htmlspecialchars($row['donor_name']) ?></td>
                        <td><?= htmlspecialchars($row['category_name'] ?? '-') ?></td>
                        <td>
                            <span class="badge <?= $row['payment_mode'] === 'Cash' ? 'bg-success' : ($row['payment_mode'] === 'Bank' ? 'bg-primary' : 'bg-secondary') ?>">
                                <?= htmlspecialchars($row['payment_mode']) ?>
                            </span>
                        </td>
                        <td class="text-end"><?= formatCurrency($row['amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-warning fw-semibold">
                        <td colspan="6" class="text-end">Subtotal — <?= htmlspecialchars($cat_name) ?>:</td>
                        <td class="text-end"><?= formatCurrency($cat_total) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-dark fw-bold">
                        <td colspan="6" class="text-end fs-6">Grand Total:</td>
                        <td class="text-end fs-6"><?= formatCurrency($grand_total) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>No income records found for the selected criteria.
    </div>
    <?php endif; ?>

</div><!-- content-area -->
</div><!-- main-content -->
</div><!-- wrapper -->

<script>
document.getElementById('exportExcel')?.addEventListener('click', function () {
    const table = document.getElementById('incomeTable');
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
    a.download = 'income_report_<?= $from_date ?>_<?= $to_date ?>.csv';
    a.click();
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
