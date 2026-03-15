<?php
$pageTitle = 'Donor Report';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

require_once __DIR__ . '/../../app/helpers/functions.php';

$pdo = db();

// Filters
$from_date = isset($_GET['from_date']) ? sanitize($_GET['from_date']) : date('Y-m-01');
$to_date   = isset($_GET['to_date'])   ? sanitize($_GET['to_date'])   : date('Y-m-d');

// Donor summary query
$summarySql = "SELECT d.id AS donor_id,
                      d.donor_name,
                      d.phone,
                      COUNT(r.id)        AS total_transactions,
                      SUM(r.amount)      AS total_donated,
                      MAX(r.date)        AS last_donation_date
               FROM donors d
               INNER JOIN receipts r ON r.donor_id = d.id
               WHERE r.deleted_at IS NULL
                 AND r.date BETWEEN :from_date AND :to_date
               GROUP BY d.id, d.donor_name, d.phone
               ORDER BY total_donated DESC";

$stmtSum = $pdo->prepare($summarySql);
$stmtSum->execute([':from_date' => $from_date, ':to_date' => $to_date]);
$donors = $stmtSum->fetchAll(PDO::FETCH_ASSOC);

// Per-donor detail (lazy loaded via JS toggle; fetch all at once and index by donor_id)
$detailSql = "SELECT r.donor_id, r.receipt_no, r.date, r.amount, r.payment_mode,
                     ic.category_name
              FROM receipts r
              LEFT JOIN income_categories ic ON ic.id = r.category_id
              WHERE r.deleted_at IS NULL
                AND r.date BETWEEN :from_date AND :to_date
              ORDER BY r.donor_id, r.date";
$stmtDet = $pdo->prepare($detailSql);
$stmtDet->execute([':from_date' => $from_date, ':to_date' => $to_date]);
$detailRows = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

$details = [];
foreach ($detailRows as $dr) {
    $details[$dr['donor_id']][] = $dr;
}

$grand_total = array_sum(array_column($donors, 'total_donated'));

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
    .donor-detail { display: table-row-group !important; }
}
.page-header-print { display: none; }
.donor-detail { display: none; }
.donor-row { cursor: pointer; }
.donor-row:hover { background-color: #f0f4ff !important; }
</style>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

    <!-- Print header -->
    <div class="page-header-print text-center mb-3">
        <h4 class="mb-0">Donor Report</h4>
        <small>Period: <?= htmlspecialchars(formatDate($from_date)) ?> to <?= htmlspecialchars(formatDate($to_date)) ?></small>
    </div>

    <!-- Page heading -->
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h4 class="mb-0"><i class="bi bi-people-fill text-primary me-2"></i>Donor Report</h4>
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
                <div class="col-md-3">
                    <label class="form-label form-label-sm mb-1">From Date</label>
                    <input type="date" name="from_date" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($from_date) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm mb-1">To Date</label>
                    <input type="date" name="to_date" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($to_date) ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search"></i> Generate Report
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="donor_report.php" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($donors)): ?>
    <!-- Summary stats -->
    <div class="row g-3 mb-4 no-print">
        <div class="col-md-3">
            <div class="card border-primary text-center p-3">
                <div class="fs-4 fw-bold text-primary"><?= count($donors) ?></div>
                <div class="small text-muted">Total Donors</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success text-center p-3">
                <div class="fs-4 fw-bold text-success"><?= formatCurrency($grand_total) ?></div>
                <div class="small text-muted">Total Donations</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info text-center p-3">
                <div class="fs-4 fw-bold text-info"><?= array_sum(array_column($donors, 'total_transactions')) ?></div>
                <div class="small text-muted">Total Transactions</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning text-center p-3">
                <div class="fs-4 fw-bold text-warning"><?= count($donors) > 0 ? formatCurrency($grand_total / count($donors)) : formatCurrency(0) ?></div>
                <div class="small text-muted">Avg per Donor</div>
            </div>
        </div>
    </div>

    <div class="alert alert-info alert-sm no-print py-2 mb-3">
        <i class="bi bi-hand-index me-1"></i>Click on any donor row to expand/collapse donation details.
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-bordered table-sm mb-0" id="donorTable">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Donor Name</th>
                        <th>Phone</th>
                        <th class="text-center">No. of Transactions</th>
                        <th class="text-end">Total Donations</th>
                        <th>Last Donation Date</th>
                        <th class="no-print text-center">Details</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($donors as $i => $donor): ?>
                    <tr class="donor-row" data-donor="<?= $donor['donor_id'] ?>" title="Click to expand">
                        <td><?= $i + 1 ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($donor['donor_name']) ?></td>
                        <td><?= htmlspecialchars($donor['phone'] ?? '-') ?></td>
                        <td class="text-center">
                            <span class="badge bg-secondary"><?= (int)$donor['total_transactions'] ?></span>
                        </td>
                        <td class="text-end fw-semibold text-success"><?= formatCurrency($donor['total_donated']) ?></td>
                        <td><?= htmlspecialchars(formatDate($donor['last_donation_date'])) ?></td>
                        <td class="text-center no-print">
                            <i class="bi bi-chevron-down expand-icon" id="icon-<?= $donor['donor_id'] ?>"></i>
                        </td>
                    </tr>
                    <!-- Expandable detail row -->
                    <tr class="donor-detail" id="detail-<?= $donor['donor_id'] ?>">
                        <td colspan="7" class="p-0">
                            <div class="bg-light p-3">
                                <table class="table table-sm table-striped mb-0 border">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>Date</th>
                                            <th>Receipt No</th>
                                            <th>Category</th>
                                            <th>Payment Mode</th>
                                            <th class="text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (isset($details[$donor['donor_id']])): ?>
                                        <?php foreach ($details[$donor['donor_id']] as $det): ?>
                                        <tr>
                                            <td><?= htmlspecialchars(formatDate($det['date'])) ?></td>
                                            <td><?= htmlspecialchars($det['receipt_no']) ?></td>
                                            <td><?= htmlspecialchars($det['category_name'] ?? '-') ?></td>
                                            <td>
                                                <span class="badge <?= $det['payment_mode'] === 'Cash' ? 'bg-success' : 'bg-primary' ?> bg-opacity-75">
                                                    <?= htmlspecialchars($det['payment_mode']) ?>
                                                </span>
                                            </td>
                                            <td class="text-end"><?= formatCurrency($det['amount']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center text-muted">No detail records.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-dark fw-bold">
                        <td colspan="4" class="text-end">Grand Total:</td>
                        <td class="text-end"><?= formatCurrency($grand_total) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>No donor records found for the selected date range.
    </div>
    <?php endif; ?>

</div><!-- content-area -->
</div><!-- main-content -->
</div><!-- wrapper -->

<script>
// Toggle donor detail rows
document.querySelectorAll('.donor-row').forEach(function (row) {
    row.addEventListener('click', function () {
        const donorId = this.dataset.donor;
        const detailRow = document.getElementById('detail-' + donorId);
        const icon = document.getElementById('icon-' + donorId);
        if (!detailRow) return;
        const isVisible = detailRow.style.display === 'table-row';
        detailRow.style.display = isVisible ? 'none' : 'table-row';
        if (icon) {
            icon.classList.toggle('bi-chevron-down', isVisible);
            icon.classList.toggle('bi-chevron-up', !isVisible);
        }
    });
});

// Export to CSV (summary only)
document.getElementById('exportExcel')?.addEventListener('click', function () {
    const rows = document.querySelectorAll('#donorTable tr:not(.donor-detail)');
    let csv = [];
    rows.forEach(function (row) {
        let cols = [];
        row.querySelectorAll('th, td').forEach(function (cell) {
            if (!cell.classList.contains('no-print')) {
                let text = cell.innerText.replace(/"/g, '""');
                cols.push('"' + text + '"');
            }
        });
        if (cols.length) csv.push(cols.join(','));
    });
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'donor_report_<?= $from_date ?>_<?= $to_date ?>.csv';
    a.click();
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
