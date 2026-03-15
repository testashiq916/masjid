<?php
$pageTitle = 'Audit Report';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

require_once __DIR__ . '/../../app/helpers/functions.php';

$pdo = db();

// Financial year selection (April-March)
$selected_fy = isset($_GET['fy']) ? (int)$_GET['fy'] : ((date('m') >= 4) ? (int)date('Y') : (int)date('Y') - 1);
$fy_start    = $selected_fy . '-04-01';
$fy_end      = ($selected_fy + 1) . '-03-31';
$fy_label    = $selected_fy . '-' . ($selected_fy + 1);

$fy_options = [];
for ($y = (int)date('Y') - 5; $y <= (int)date('Y'); $y++) {
    $fy_options[$y] = $y . '-' . ($y + 1);
}

// SECTION 1 - SUMMARY
$incTotal = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM receipts WHERE deleted_at IS NULL AND date BETWEEN :s AND :e");
$incTotal->execute([':s' => $fy_start, ':e' => $fy_end]);
$total_income = (float)$incTotal->fetchColumn();

$expTotal = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE deleted_at IS NULL AND date BETWEEN :s AND :e");
$expTotal->execute([':s' => $fy_start, ':e' => $fy_end]);
$total_expense = (float)$expTotal->fetchColumn();

$surplus_deficit = $total_income - $total_expense;

// SECTION 2 - RECEIPT REGISTER
$rcptStmt = $pdo->prepare(
    "SELECT r.receipt_no, r.date, r.donor_name, r.payment_mode, r.amount,
            ic.category_name
     FROM receipts r
     LEFT JOIN income_categories ic ON ic.id = r.category_id
     WHERE r.deleted_at IS NULL AND r.date BETWEEN :s AND :e
     ORDER BY r.date, r.id"
);
$rcptStmt->execute([':s' => $fy_start, ':e' => $fy_end]);
$receipts = $rcptStmt->fetchAll(PDO::FETCH_ASSOC);

// SECTION 3 - PAYMENT REGISTER
$pmtStmt = $pdo->prepare(
    "SELECT p.voucher_no, p.date, p.payee_name, p.payment_mode, p.amount,
            ec.category_name
     FROM payments p
     LEFT JOIN expense_categories ec ON ec.id = p.category_id
     WHERE p.deleted_at IS NULL AND p.date BETWEEN :s AND :e
     ORDER BY p.date, p.id"
);
$pmtStmt->execute([':s' => $fy_start, ':e' => $fy_end]);
$payments = $pmtStmt->fetchAll(PDO::FETCH_ASSOC);

// SECTION 4 - CASH BOOK
$cashIn = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM receipts WHERE deleted_at IS NULL AND payment_mode = 'Cash' AND date BETWEEN :s AND :e");
$cashIn->execute([':s' => $fy_start, ':e' => $fy_end]);
$cash_receipts = (float)$cashIn->fetchColumn();

$cashOut = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE deleted_at IS NULL AND payment_mode = 'Cash' AND date BETWEEN :s AND :e");
$cashOut->execute([':s' => $fy_start, ':e' => $fy_end]);
$cash_payments = (float)$cashOut->fetchColumn();
$cash_balance  = $cash_receipts - $cash_payments;

$cashMonthStmt = $pdo->prepare(
    "SELECT DATE_FORMAT(date,'%Y-%m') AS mon,
            SUM(CASE WHEN t='in'  THEN amount ELSE 0 END) AS cash_in,
            SUM(CASE WHEN t='out' THEN amount ELSE 0 END) AS cash_out
     FROM (
        SELECT date, amount, 'in'  AS t FROM receipts  WHERE deleted_at IS NULL AND payment_mode='Cash' AND date BETWEEN :s1 AND :e1
        UNION ALL
        SELECT date, amount, 'out' AS t FROM payments  WHERE deleted_at IS NULL AND payment_mode='Cash' AND date BETWEEN :s2 AND :e2
     ) x
     GROUP BY mon ORDER BY mon"
);
$cashMonthStmt->execute([':s1'=>$fy_start,':e1'=>$fy_end,':s2'=>$fy_start,':e2'=>$fy_end]);
$cash_monthly = $cashMonthStmt->fetchAll(PDO::FETCH_ASSOC);

// SECTION 5 - BANK BOOK
$bankIn = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM receipts WHERE deleted_at IS NULL AND payment_mode IN ('Bank','Cheque','Online') AND date BETWEEN :s AND :e");
$bankIn->execute([':s' => $fy_start, ':e' => $fy_end]);
$bank_receipts = (float)$bankIn->fetchColumn();

$bankOut = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE deleted_at IS NULL AND payment_mode IN ('Bank','Cheque','Online') AND date BETWEEN :s AND :e");
$bankOut->execute([':s' => $fy_start, ':e' => $fy_end]);
$bank_payments = (float)$bankOut->fetchColumn();
$bank_balance  = $bank_receipts - $bank_payments;

$bankMonthStmt = $pdo->prepare(
    "SELECT DATE_FORMAT(date,'%Y-%m') AS mon,
            SUM(CASE WHEN t='in'  THEN amount ELSE 0 END) AS bank_in,
            SUM(CASE WHEN t='out' THEN amount ELSE 0 END) AS bank_out
     FROM (
        SELECT date, amount, 'in'  AS t FROM receipts  WHERE deleted_at IS NULL AND payment_mode IN ('Bank','Cheque','Online') AND date BETWEEN :s1 AND :e1
        UNION ALL
        SELECT date, amount, 'out' AS t FROM payments  WHERE deleted_at IS NULL AND payment_mode IN ('Bank','Cheque','Online') AND date BETWEEN :s2 AND :e2
     ) x
     GROUP BY mon ORDER BY mon"
);
$bankMonthStmt->execute([':s1'=>$fy_start,':e1'=>$fy_end,':s2'=>$fy_start,':e2'=>$fy_end]);
$bank_monthly = $bankMonthStmt->fetchAll(PDO::FETCH_ASSOC);

// SECTION 6 - FUND WISE
$fundStmt = $pdo->prepare(
    "SELECT ic.category_name AS fund_name, SUM(r.amount) AS total
     FROM receipts r
     LEFT JOIN income_categories ic ON ic.id = r.category_id
     WHERE r.deleted_at IS NULL AND r.date BETWEEN :s AND :e
     GROUP BY r.category_id, ic.category_name
     ORDER BY ic.category_name"
);
$fundStmt->execute([':s' => $fy_start, ':e' => $fy_end]);
$fund_summary = $fundStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../templates/header.php';
?>
<style>
@media print {
    .no-print { display: none !important; }
    .main-content { margin: 0 !important; }
    .content-area { margin-top: 0 !important; padding: 0 !important; }
    .wrapper { display: block !important; }
    body { font-size: 11px; }
    .table { font-size: 10px; }
    .page-header-print { display: block !important; }
    .audit-section { page-break-inside: avoid; }
    .audit-section-break { page-break-before: always; }
}
.page-header-print { display: none; }
.audit-section-title {
    background: #343a40;
    color: #fff;
    padding: 8px 16px;
    font-weight: 600;
    font-size: 0.9rem;
    letter-spacing: 0.04em;
    border-radius: 4px 4px 0 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.section-number {
    background: rgba(255,255,255,0.25);
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    flex-shrink: 0;
}
</style>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

    <!-- Print header -->
    <div class="page-header-print text-center mb-4">
        <h3 class="mb-1">Financial Year Audit Report</h3>
        <h5 class="mb-1">Year: <?= htmlspecialchars($fy_label) ?></h5>
        <p class="mb-0 text-muted">Period: <?= htmlspecialchars(formatDate($fy_start)) ?> to <?= htmlspecialchars(formatDate($fy_end)) ?></p>
        <hr>
        <p class="mb-0 small">Generated on: <?= date('d M Y, h:i A') ?></p>
    </div>

    <!-- Page heading -->
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h4 class="mb-0">
            <i class="bi bi-clipboard2-data-fill text-dark me-2"></i>Audit Report
        </h4>
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="bi bi-printer"></i> Print / PDF
        </button>
    </div>

    <!-- Filter form -->
    <div class="card mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm mb-1">Financial Year</label>
                    <select name="fy" class="form-select form-select-sm">
                        <?php foreach (array_reverse($fy_options, true) as $y => $lbl): ?>
                        <option value="<?= $y ?>" <?= $y === $selected_fy ? 'selected' : '' ?>>
                            FY <?= htmlspecialchars($lbl) ?>
                        </option>
                        <?php endforeach; ?>
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

    <!-- SECTION 1: SUMMARY -->
    <div class="audit-section mb-4">
        <div class="audit-section-title">
            <span class="section-number">1</span>
            Financial Summary &mdash; FY <?= htmlspecialchars($fy_label) ?>
        </div>
        <div class="card border-top-0 rounded-top-0">
            <div class="card-body">
                <div class="row g-3 text-center mb-3">
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-success bg-opacity-10">
                            <div class="fs-4 fw-bold text-success"><?= formatCurrency($total_income) ?></div>
                            <div class="small text-muted">Total Income</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-danger bg-opacity-10">
                            <div class="fs-4 fw-bold text-danger"><?= formatCurrency($total_expense) ?></div>
                            <div class="small text-muted">Total Expenditure</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded <?= $surplus_deficit >= 0 ? 'bg-primary bg-opacity-10' : 'bg-warning bg-opacity-10' ?>">
                            <div class="fs-4 fw-bold <?= $surplus_deficit >= 0 ? 'text-primary' : 'text-warning' ?>">
                                <?= formatCurrency(abs($surplus_deficit)) ?>
                            </div>
                            <div class="small text-muted"><?= $surplus_deficit >= 0 ? 'Surplus' : 'Deficit' ?></div>
                        </div>
                    </div>
                </div>
                <table class="table table-bordered table-sm mb-0">
                    <tbody>
                        <tr>
                            <td class="fw-semibold" width="50%">Total Receipts (Count)</td>
                            <td><?= count($receipts) ?> transactions</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Total Payments (Count)</td>
                            <td><?= count($payments) ?> transactions</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Cash Receipts</td>
                            <td><?= formatCurrency($cash_receipts) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Bank / Cheque / Online Receipts</td>
                            <td><?= formatCurrency($bank_receipts) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Cash Payments</td>
                            <td><?= formatCurrency($cash_payments) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Bank / Cheque / Online Payments</td>
                            <td><?= formatCurrency($bank_payments) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SECTION 2: RECEIPT REGISTER -->
    <div class="audit-section mb-4 audit-section-break">
        <div class="audit-section-title">
            <span class="section-number">2</span> Receipt Register
        </div>
        <div class="card border-top-0 rounded-top-0">
            <div class="card-body p-0">
                <div class="no-print" style="max-height:400px;overflow-y:auto;">
                    <table class="table table-bordered table-sm mb-0" id="receiptRegTable">
                        <thead class="table-success sticky-top">
                            <tr>
                                <th>#</th><th>Date</th><th>Receipt No</th><th>Donor</th>
                                <th>Category</th><th>Payment Mode</th><th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($receipts)): ?>
                            <?php foreach ($receipts as $i => $r): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars(formatDate($r['date'])) ?></td>
                                <td><?= htmlspecialchars($r['receipt_no']) ?></td>
                                <td><?= htmlspecialchars($r['donor_name']) ?></td>
                                <td><?= htmlspecialchars($r['category_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($r['payment_mode']) ?></td>
                                <td class="text-end"><?= formatCurrency($r['amount']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted">No receipts for this period.</td></tr>
                        <?php endif; ?>
                        </tbody>
                        <tfoot class="table-success fw-bold">
                            <tr>
                                <td colspan="6" class="text-end">Total Receipts:</td>
                                <td class="text-end"><?= formatCurrency($total_income) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <!-- Print version (flat, no scroll) -->
                <table class="table table-bordered table-sm mb-0 d-none d-print-table">
                    <thead class="table-success">
                        <tr>
                            <th>#</th><th>Date</th><th>Receipt No</th><th>Donor</th>
                            <th>Category</th><th>Mode</th><th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($receipts as $i => $r): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars(formatDate($r['date'])) ?></td>
                            <td><?= htmlspecialchars($r['receipt_no']) ?></td>
                            <td><?= htmlspecialchars($r['donor_name']) ?></td>
                            <td><?= htmlspecialchars($r['category_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['payment_mode']) ?></td>
                            <td class="text-end"><?= formatCurrency($r['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="fw-bold"><tr>
                        <td colspan="6" class="text-end">Total:</td>
                        <td class="text-end"><?= formatCurrency($total_income) ?></td>
                    </tr></tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- SECTION 3: PAYMENT REGISTER -->
    <div class="audit-section mb-4 audit-section-break">
        <div class="audit-section-title">
            <span class="section-number">3</span> Payment Register
        </div>
        <div class="card border-top-0 rounded-top-0">
            <div class="card-body p-0">
                <div class="no-print" style="max-height:400px;overflow-y:auto;">
                    <table class="table table-bordered table-sm mb-0" id="paymentRegTable">
                        <thead class="table-danger sticky-top">
                            <tr>
                                <th>#</th><th>Date</th><th>Voucher No</th><th>Payee</th>
                                <th>Category</th><th>Payment Mode</th><th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($payments)): ?>
                            <?php foreach ($payments as $i => $p): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars(formatDate($p['date'])) ?></td>
                                <td><?= htmlspecialchars($p['voucher_no']) ?></td>
                                <td><?= htmlspecialchars($p['payee_name']) ?></td>
                                <td><?= htmlspecialchars($p['category_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($p['payment_mode']) ?></td>
                                <td class="text-end"><?= formatCurrency($p['amount']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted">No payments for this period.</td></tr>
                        <?php endif; ?>
                        </tbody>
                        <tfoot class="table-danger fw-bold">
                            <tr>
                                <td colspan="6" class="text-end">Total Payments:</td>
                                <td class="text-end"><?= formatCurrency($total_expense) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <table class="table table-bordered table-sm mb-0 d-none d-print-table">
                    <thead class="table-danger">
                        <tr>
                            <th>#</th><th>Date</th><th>Voucher No</th><th>Payee</th>
                            <th>Category</th><th>Mode</th><th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payments as $i => $p): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars(formatDate($p['date'])) ?></td>
                            <td><?= htmlspecialchars($p['voucher_no']) ?></td>
                            <td><?= htmlspecialchars($p['payee_name']) ?></td>
                            <td><?= htmlspecialchars($p['category_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($p['payment_mode']) ?></td>
                            <td class="text-end"><?= formatCurrency($p['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="fw-bold"><tr>
                        <td colspan="6" class="text-end">Total:</td>
                        <td class="text-end"><?= formatCurrency($total_expense) ?></td>
                    </tr></tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- SECTION 4: CASH BOOK SUMMARY -->
    <div class="audit-section mb-4">
        <div class="audit-section-title">
            <span class="section-number">4</span> Cash Book Summary
        </div>
        <div class="card border-top-0 rounded-top-0">
            <div class="card-body p-0">
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-secondary">
                        <tr>
                            <th>Month</th>
                            <th class="text-end">Cash Receipts</th>
                            <th class="text-end">Cash Payments</th>
                            <th class="text-end">Net Cash</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($cash_monthly)):
                        foreach ($cash_monthly as $cm):
                            $net = (float)$cm['cash_in'] - (float)$cm['cash_out'];
                    ?>
                        <tr>
                            <td><?= htmlspecialchars(date('M Y', strtotime($cm['mon'] . '-01'))) ?></td>
                            <td class="text-end text-success"><?= formatCurrency($cm['cash_in']) ?></td>
                            <td class="text-end text-danger"><?= formatCurrency($cm['cash_out']) ?></td>
                            <td class="text-end <?= $net >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatCurrency($net) ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center text-muted">No cash transactions.</td></tr>
                    <?php endif; ?>
                    </tbody>
                    <tfoot class="table-dark fw-bold">
                        <tr>
                            <td>Total</td>
                            <td class="text-end"><?= formatCurrency($cash_receipts) ?></td>
                            <td class="text-end"><?= formatCurrency($cash_payments) ?></td>
                            <td class="text-end <?= $cash_balance >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatCurrency($cash_balance) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- SECTION 5: BANK BOOK SUMMARY -->
    <div class="audit-section mb-4">
        <div class="audit-section-title">
            <span class="section-number">5</span> Bank Book Summary
        </div>
        <div class="card border-top-0 rounded-top-0">
            <div class="card-body p-0">
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-secondary">
                        <tr>
                            <th>Month</th>
                            <th class="text-end">Bank Receipts</th>
                            <th class="text-end">Bank Payments</th>
                            <th class="text-end">Net Bank</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($bank_monthly)):
                        foreach ($bank_monthly as $bm):
                            $net = (float)$bm['bank_in'] - (float)$bm['bank_out'];
                    ?>
                        <tr>
                            <td><?= htmlspecialchars(date('M Y', strtotime($bm['mon'] . '-01'))) ?></td>
                            <td class="text-end text-success"><?= formatCurrency($bm['bank_in']) ?></td>
                            <td class="text-end text-danger"><?= formatCurrency($bm['bank_out']) ?></td>
                            <td class="text-end <?= $net >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatCurrency($net) ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center text-muted">No bank transactions.</td></tr>
                    <?php endif; ?>
                    </tbody>
                    <tfoot class="table-dark fw-bold">
                        <tr>
                            <td>Total</td>
                            <td class="text-end"><?= formatCurrency($bank_receipts) ?></td>
                            <td class="text-end"><?= formatCurrency($bank_payments) ?></td>
                            <td class="text-end <?= $bank_balance >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatCurrency($bank_balance) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- SECTION 6: FUND-WISE SUMMARY -->
    <div class="audit-section mb-4">
        <div class="audit-section-title">
            <span class="section-number">6</span> Fund-wise Income Summary
        </div>
        <div class="card border-top-0 rounded-top-0">
            <div class="card-body p-0">
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-secondary">
                        <tr>
                            <th>#</th>
                            <th>Fund / Category</th>
                            <th class="text-end">Total Received</th>
                            <th class="text-end">% of Total Income</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($fund_summary)):
                        foreach ($fund_summary as $i => $fund):
                            $pct = $total_income > 0 ? round($fund['total'] / $total_income * 100, 1) : 0;
                    ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($fund['fund_name'] ?? 'Uncategorised') ?></td>
                            <td class="text-end"><?= formatCurrency($fund['total']) ?></td>
                            <td class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <span><?= $pct ?>%</span>
                                    <div class="progress no-print" style="width:80px;height:8px;">
                                        <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center text-muted">No fund data available.</td></tr>
                    <?php endif; ?>
                    </tbody>
                    <tfoot class="table-dark fw-bold">
                        <tr>
                            <td colspan="2" class="text-end">Grand Total:</td>
                            <td class="text-end"><?= formatCurrency($total_income) ?></td>
                            <td class="text-end">100%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Audit pack footer -->
    <div class="card mt-4 border-secondary">
        <div class="card-body text-center text-muted small">
            <p class="mb-1">
                <strong>Audit Pack &mdash; Financial Year <?= htmlspecialchars($fy_label) ?></strong><br>
                Period: <?= htmlspecialchars(formatDate($fy_start)) ?> to <?= htmlspecialchars(formatDate($fy_end)) ?>
            </p>
            <p class="mb-0">Generated on: <?= date('d M Y, h:i A') ?> &nbsp;|&nbsp; System-generated report.</p>
        </div>
    </div>

</div><!-- content-area -->
</div><!-- main-content -->
</div><!-- wrapper -->

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
