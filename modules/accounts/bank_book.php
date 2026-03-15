<?php
$pageTitle = 'Bank Book';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

$db = db();

$fromDate = sanitize($_GET['from_date'] ?? date('Y-m-01'));
$toDate   = sanitize($_GET['to_date']   ?? date('Y-m-t'));

// Get bank account (account_code = 1002)
$bankAcc = $db->query("SELECT id, account_name, opening_balance FROM chart_of_accounts WHERE account_code = '1002' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$bankId  = (int)($bankAcc['id'] ?? 0);

$openingBalance = 0;
$transactions   = [];

if ($bankId) {
    // Opening balance calculation
    $openingIncome = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM receipts WHERE account_id = ? AND date < ? AND deleted_at IS NULL");
    $openingIncome->execute([$bankId, $fromDate]);

    $openingExpense = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE account_id = ? AND date < ? AND deleted_at IS NULL");
    $openingExpense->execute([$bankId, $fromDate]);

    $openingJDebit = $db->prepare("SELECT COALESCE(SUM(jd.debit_amount), 0) FROM journal_details jd JOIN journal_entries je ON je.id = jd.journal_id WHERE jd.account_id = ? AND je.date < ? AND je.deleted_at IS NULL");
    $openingJDebit->execute([$bankId, $fromDate]);

    $openingJCredit = $db->prepare("SELECT COALESCE(SUM(jd.credit_amount), 0) FROM journal_details jd JOIN journal_entries je ON je.id = jd.journal_id WHERE jd.account_id = ? AND je.date < ? AND je.deleted_at IS NULL");
    $openingJCredit->execute([$bankId, $fromDate]);

    $openingBalance  = (float)($bankAcc['opening_balance'] ?? 0);
    $openingBalance += (float)$openingIncome->fetchColumn();
    $openingBalance -= (float)$openingExpense->fetchColumn();
    $openingBalance += (float)$openingJDebit->fetchColumn();
    $openingBalance -= (float)$openingJCredit->fetchColumn();

    // Get transactions in range
    $stmt = $db->prepare("
        SELECT 'receipt' AS txn_type, date, receipt_no AS ref_no,
               COALESCE(donor_name, 'N/A') AS party, ic.category_name AS description,
               amount, COALESCE(payment_mode, '') AS payment_mode, COALESCE(bank_ref, '') AS bank_ref
        FROM receipts r
        LEFT JOIN income_categories ic ON ic.id = r.category_id
        WHERE r.account_id = ? AND r.date BETWEEN ? AND ? AND r.deleted_at IS NULL

        UNION ALL

        SELECT 'payment' AS txn_type, date, voucher_no AS ref_no,
               COALESCE(payee_name, 'N/A') AS party, ec.category_name AS description,
               amount, COALESCE(payment_mode, '') AS payment_mode, COALESCE(bank_ref, '') AS bank_ref
        FROM payments p
        LEFT JOIN expense_categories ec ON ec.id = p.category_id
        WHERE p.account_id = ? AND p.date BETWEEN ? AND ? AND p.deleted_at IS NULL

        UNION ALL

        SELECT IF(jd.debit_amount > 0, 'receipt', 'payment') AS txn_type,
               je.date, je.journal_no AS ref_no, 'Journal' AS party,
               je.narration AS description,
               IF(jd.debit_amount > 0, jd.debit_amount, jd.credit_amount) AS amount,
               '' AS payment_mode, '' AS bank_ref
        FROM journal_details jd
        JOIN journal_entries je ON je.id = jd.journal_id
        WHERE jd.account_id = ? AND je.date BETWEEN ? AND ? AND je.deleted_at IS NULL

        ORDER BY date, txn_type
    ");
    $stmt->execute([$bankId, $fromDate, $toDate, $bankId, $fromDate, $toDate, $bankId, $fromDate, $toDate]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$totalDebit  = 0;
$totalCredit = 0;
foreach ($transactions as $t) {
    if ($t['txn_type'] === 'receipt') $totalDebit  += (float)$t['amount'];
    else                               $totalCredit += (float)$t['amount'];
}
$closingBalance = $openingBalance + $totalDebit - $totalCredit;

require_once __DIR__ . '/../../templates/header.php';
?>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3 no-print">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item">Accounts</li>
            <li class="breadcrumb-item active">Bank Book</li>
        </ol>
    </nav>

    <!-- Flash Messages -->
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show no-print" role="alert">
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><i class="bi bi-bank me-2 text-primary"></i>Bank Book</h4>
            <small class="text-muted">
                <?= htmlspecialchars($bankAcc['account_name'] ?? 'Bank Account') ?> &mdash;
                <?= formatDate($fromDate) ?> to <?= formatDate($toDate) ?>
            </small>
        </div>
        <button class="btn btn-outline-secondary no-print" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print
        </button>
    </div>

    <!-- Filter -->
    <div class="card shadow-sm mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?= $fromDate ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?= $toDate ?>">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search me-1"></i>Filter</button>
                    <a href="<?= BASE_PATH ?>/modules/accounts/bank_book.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <?php if (!$bankId): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Bank account (account_code = '1002') not found. Please create it in Chart of Accounts.
    </div>
    <?php else: ?>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-secondary">
                <div class="card-body py-2">
                    <div class="text-muted small">Opening Balance</div>
                    <div class="fw-bold fs-5 <?= $openingBalance < 0 ? 'text-danger' : '' ?>"><?= formatCurrency($openingBalance) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body py-2">
                    <div class="text-muted small">Total Deposits (Dr)</div>
                    <div class="fw-bold fs-5 text-success"><?= formatCurrency($totalDebit) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body py-2">
                    <div class="text-muted small">Total Withdrawals (Cr)</div>
                    <div class="fw-bold fs-5 text-danger"><?= formatCurrency($totalCredit) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body py-2">
                    <div class="text-muted small">Closing Balance</div>
                    <div class="fw-bold fs-5 <?= $closingBalance < 0 ? 'text-danger' : 'text-info' ?>"><?= formatCurrency($closingBalance) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bank Book Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-bank me-2 text-primary"></i>Bank Book Statement</span>
            <span class="text-muted small"><?= count($transactions) ?> transactions</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead class="table-info">
                        <tr>
                            <th>Date</th>
                            <th>Particulars</th>
                            <th>Ref. No.</th>
                            <th>Mode / Bank Ref</th>
                            <th class="text-end text-success">Deposits (Dr)</th>
                            <th class="text-end text-danger">Withdrawals (Cr)</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Opening Balance Row -->
                        <tr class="table-light fw-bold">
                            <td><?= formatDate($fromDate) ?></td>
                            <td colspan="3"><em>Opening Balance b/f</em></td>
                            <td class="text-end text-success"><?= $openingBalance >= 0 ? formatCurrency($openingBalance) : '' ?></td>
                            <td class="text-end text-danger"><?= $openingBalance < 0 ? formatCurrency(abs($openingBalance)) : '' ?></td>
                            <td class="text-end fw-bold"><?= formatCurrency($openingBalance) ?></td>
                        </tr>

                        <?php
                        $runningBalance = $openingBalance;
                        foreach ($transactions as $t):
                            if ($t['txn_type'] === 'receipt') {
                                $runningBalance += (float)$t['amount'];
                                $debitAmt  = (float)$t['amount'];
                                $creditAmt = null;
                            } else {
                                $runningBalance -= (float)$t['amount'];
                                $debitAmt  = null;
                                $creditAmt = (float)$t['amount'];
                            }
                            $modeInfo = '';
                            if (!empty($t['payment_mode'])) {
                                $modeInfo .= '<span class="badge bg-light text-dark border me-1">' . strtoupper(htmlspecialchars($t['payment_mode'])) . '</span>';
                            }
                            if (!empty($t['bank_ref'])) {
                                $modeInfo .= '<small class="text-muted">' . htmlspecialchars($t['bank_ref']) . '</small>';
                            }
                        ?>
                        <tr>
                            <td class="text-nowrap"><?= formatDate($t['date']) ?></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($t['description'] ?? 'N/A') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($t['party'] ?? '') ?></small>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($t['ref_no']) ?></td>
                            <td><?= $modeInfo ?: '<span class="text-muted">-</span>' ?></td>
                            <td class="text-end text-success"><?= $debitAmt !== null ? formatCurrency($debitAmt) : '<span class="text-muted">-</span>' ?></td>
                            <td class="text-end text-danger"><?= $creditAmt !== null ? formatCurrency($creditAmt) : '<span class="text-muted">-</span>' ?></td>
                            <td class="text-end fw-semibold <?= $runningBalance < 0 ? 'text-danger' : '' ?>"><?= formatCurrency($runningBalance) ?></td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (empty($transactions)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No bank transactions in this period.</td></tr>
                        <?php endif; ?>

                        <!-- Totals Row -->
                        <tr class="table-secondary fw-bold">
                            <td colspan="4" class="text-end">Period Total</td>
                            <td class="text-end text-success"><?= formatCurrency($totalDebit) ?></td>
                            <td class="text-end text-danger"><?= formatCurrency($totalCredit) ?></td>
                            <td></td>
                        </tr>
                        <!-- Closing Balance Row -->
                        <tr class="table-warning fw-bold">
                            <td colspan="4" class="text-end">Closing Balance c/f</td>
                            <td></td>
                            <td></td>
                            <td class="text-end <?= $closingBalance < 0 ? 'text-danger' : '' ?>"><?= formatCurrency($closingBalance) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
