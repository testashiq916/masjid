<?php
$pageTitle = 'Income & Expenditure Report';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

require_once __DIR__ . '/../../app/helpers/functions.php';

$pdo = db();

// Financial year helpers (April–March cycle)
$current_fy_start = (date('m') >= 4) ? date('Y') . '-04-01' : (date('Y') - 1) . '-04-01';
$current_fy_end   = (date('m') >= 4) ? (date('Y') + 1) . '-03-31' : date('Y') . '-03-31';

$from_date = isset($_GET['from_date']) ? sanitize($_GET['from_date']) : $current_fy_start;
$to_date   = isset($_GET['to_date'])   ? sanitize($_GET['to_date'])   : date('Y-m-d');

// Quick FY presets
$fy_years = [];
for ($y = (int)date('Y') - 3; $y <= (int)date('Y'); $y++) {
    $fy_years[$y] = $y . '-' . ($y + 1);
}

// ---- INCOME: group by income category ----
$incStmt = $pdo->prepare(
    "SELECT ic.category_name, SUM(r.amount) AS total
     FROM receipts r
     LEFT JOIN income_categories ic ON ic.id = r.category_id
     WHERE r.deleted_at IS NULL
       AND r.date BETWEEN :from_date AND :to_date
     GROUP BY r.category_id, ic.category_name
     ORDER BY ic.category_name"
);
$incStmt->execute([':from_date' => $from_date, ':to_date' => $to_date]);
$income_rows = $incStmt->fetchAll(PDO::FETCH_ASSOC);
$total_income = array_sum(array_column($income_rows, 'total'));

// ---- EXPENDITURE: group by expense category ----
$expStmt = $pdo->prepare(
    "SELECT ec.category_name, SUM(p.amount) AS total
     FROM payments p
     LEFT JOIN expense_categories ec ON ec.id = p.category_id
     WHERE p.deleted_at IS NULL
       AND p.date BETWEEN :from_date AND :to_date
     GROUP BY p.category_id, ec.category_name
     ORDER BY ec.category_name"
);
$expStmt->execute([':from_date' => $from_date, ':to_date' => $to_date]);
$expense_rows = $expStmt->fetchAll(PDO::FETCH_ASSOC);
$total_expense = array_sum(array_column($expense_rows, 'total'));

$surplus_deficit = $total_income - $total_expense;

require_once __DIR__ . '/../../templates/header.php';
?>
<style>
@media print {
    .no-print { display: none !important; }
    .main-content { margin: 0 !important; }
    .content-area { margin-top: 0 !important; padding: 0 !important; }
    .wrapper { display: block !important; }
    body { font-size: 12px; }
    .ie-report { font-size: 12px; }
    .page-header-print { display: block !important; }
    .ie-section-card { box-shadow: none !important; border: 1px solid #dee2e6 !important; }
}
.page-header-print { display: none; }
.ie-section-title {
    background: #f8f9fa;
    padding: 8px 16px;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.ie-row-cat td { padding: 4px 16px; }
.ie-row-subtotal td { padding: 6px 16px; font-weight: 600; border-top: 1px solid #dee2e6; background: #fffbeb; }
.ie-row-grand td { padding: 10px 16px; font-weight: 700; font-size: 1.05em; }
.surplus-positive { color: #198754; }
.surplus-negative { color: #dc3545; }
</style>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

    <!-- Print header -->
    <div class="page-header-print text-center mb-4">
        <h3 class="mb-1">Income &amp; Expenditure Account</h3>
        <p class="mb-0">For the period from <?= htmlspecialchars(formatDate($from_date)) ?> to <?= htmlspecialchars(formatDate($to_date)) ?></p>
        <hr>
    </div>

    <!-- Page heading -->
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h4 class="mb-0">
            <i class="bi bi-graph-up-arrow text-success me-2"></i>Income &amp; Expenditure Report
        </h4>
        <div>
            <button class="btn btn-outline-secondary btn-sm me-1" onclick="window.print()">
                <i class="bi bi-printer"></i> Print
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
                    <label class="form-label form-label-sm mb-1">Quick — Financial Year</label>
                    <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($fy_years as $fy_start => $fy_label):
                        $fy_from = $fy_start . '-04-01';
                        $fy_to   = ($fy_start + 1) . '-03-31';
                    ?>
                        <a href="?from_date=<?= $fy_from ?>&to_date=<?= $fy_to ?>"
                           class="btn btn-outline-primary btn-sm">FY <?= $fy_label ?></a>
                    <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search"></i> Generate
                    </button>
                </div>
                <div class="col-md-1">
                    <a href="income_expenditure.php" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- IE Report layout: two-column on large screens, stacked on print -->
    <div class="ie-report">
        <div class="row g-4">

            <!-- INCOME SECTION -->
            <div class="col-lg-6">
                <div class="card ie-section-card h-100">
                    <div class="ie-section-title text-success">
                        <i class="bi bi-plus-circle me-2"></i>Income
                    </div>
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr class="table-light">
                                <th>Category</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($income_rows)): ?>
                            <?php foreach ($income_rows as $row): ?>
                            <tr class="ie-row-cat">
                                <td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorised') ?></td>
                                <td class="text-end"><?= formatCurrency($row['total']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="2" class="text-center text-muted py-3">No income for this period.</td></tr>
                        <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="ie-row-subtotal table-success">
                                <td class="fw-bold">Total Income</td>
                                <td class="text-end fw-bold"><?= formatCurrency($total_income) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- EXPENDITURE SECTION -->
            <div class="col-lg-6">
                <div class="card ie-section-card h-100">
                    <div class="ie-section-title text-danger">
                        <i class="bi bi-dash-circle me-2"></i>Expenditure
                    </div>
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr class="table-light">
                                <th>Category</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($expense_rows)): ?>
                            <?php foreach ($expense_rows as $row): ?>
                            <tr class="ie-row-cat">
                                <td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorised') ?></td>
                                <td class="text-end"><?= formatCurrency($row['total']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="2" class="text-center text-muted py-3">No expenditure for this period.</td></tr>
                        <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="ie-row-subtotal table-danger">
                                <td class="fw-bold">Total Expenditure</td>
                                <td class="text-end fw-bold"><?= formatCurrency($total_expense) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div><!-- row -->

        <!-- Surplus / Deficit bar -->
        <div class="card mt-4 border-0 shadow-sm">
            <div class="card-body p-0">
                <table class="table table-bordered mb-0">
                    <tbody>
                        <tr class="ie-row-grand <?= $surplus_deficit >= 0 ? 'table-success' : 'table-danger' ?>">
                            <td class="fw-bold fs-5">
                                <?= $surplus_deficit >= 0 ? 'Surplus (Excess of Income over Expenditure)' : 'Deficit (Excess of Expenditure over Income)' ?>
                            </td>
                            <td class="text-end fw-bold fs-5 <?= $surplus_deficit >= 0 ? 'surplus-positive' : 'surplus-negative' ?>">
                                <?= formatCurrency(abs($surplus_deficit)) ?>
                            </td>
                        </tr>
                        <tr class="table-secondary fw-semibold">
                            <td>Total Income</td>
                            <td class="text-end"><?= formatCurrency($total_income) ?></td>
                        </tr>
                        <tr class="table-secondary fw-semibold">
                            <td>Total Expenditure</td>
                            <td class="text-end"><?= formatCurrency($total_expense) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Visual progress bar (screen only) -->
        <?php if ($total_income > 0): ?>
        <div class="mt-3 no-print">
            <div class="d-flex justify-content-between small mb-1">
                <span class="text-success">Income: <?= formatCurrency($total_income) ?></span>
                <span class="text-danger">Expenditure: <?= formatCurrency($total_expense) ?></span>
            </div>
            <div class="progress" style="height:20px;">
                <?php $pct = min(100, ($total_income > 0 ? ($total_expense / $total_income * 100) : 0)); ?>
                <div class="progress-bar bg-success" style="width:100%">Income</div>
            </div>
            <div class="progress mt-1" style="height:20px;">
                <div class="progress-bar bg-danger" style="width:<?= round($pct) ?>%">Expenditure (<?= round($pct) ?>%)</div>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- ie-report -->

</div><!-- content-area -->
</div><!-- main-content -->
</div><!-- wrapper -->

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
