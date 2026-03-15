<?php
$pageTitle = 'Account Ledger';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

$db = db();

$accountId = (int)($_GET['account_id'] ?? 0);
$fromDate  = sanitize($_GET['from_date'] ?? date('Y-m-01'));
$toDate    = sanitize($_GET['to_date']   ?? date('Y-m-t'));

// All accounts for dropdown
$allAccounts = $db->query("
    SELECT id, account_code, account_name, account_type
    FROM chart_of_accounts
    WHERE deleted_at IS NULL AND status = 'active'
    ORDER BY account_code
")->fetchAll(PDO::FETCH_ASSOC);

$transactions   = [];
$openingBalance = 0;
$accountInfo    = null;
$totalDebit     = 0;
$totalCredit    = 0;

if ($accountId) {
    $stmt = $db->prepare("SELECT * FROM chart_of_accounts WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$accountId]);
    $accountInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($accountInfo) {
        // Calculate opening balance (all activity before fromDate)
        $openingI = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM receipts WHERE account_id = ? AND date < ? AND deleted_at IS NULL");
        $openingI->execute([$accountId, $fromDate]);

        $openingE = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE account_id = ? AND date < ? AND deleted_at IS NULL");
        $openingE->execute([$accountId, $fromDate]);

        $openingJD = $db->prepare("SELECT COALESCE(SUM(jd.debit_amount), 0) FROM journal_details jd JOIN journal_entries je ON je.id = jd.journal_id WHERE jd.account_id = ? AND je.date < ? AND je.deleted_at IS NULL");
        $openingJD->execute([$accountId, $fromDate]);

        $openingJC = $db->prepare("SELECT COALESCE(SUM(jd.credit_amount), 0) FROM journal_details jd JOIN journal_entries je ON je.id = jd.journal_id WHERE jd.account_id = ? AND je.date < ? AND je.deleted_at IS NULL");
        $openingJC->execute([$accountId, $fromDate]);

        $openingBalance  = (float)($accountInfo['opening_balance'] ?? 0);
        $openingBalance += (float)$openingI->fetchColumn();
        $openingBalance -= (float)$openingE->fetchColumn();
        $openingBalance += (float)$openingJD->fetchColumn();
        $openingBalance -= (float)$openingJC->fetchColumn();

        // Transactions in period
        $stmt = $db->prepare("
            SELECT 'receipt' AS txn_type, date, receipt_no AS ref_no,
                   COALESCE(donor_name, 'N/A') AS party, ic.category_name AS description, amount
            FROM receipts r
            LEFT JOIN income_categories ic ON ic.id = r.category_id
            WHERE r.account_id = ? AND r.date BETWEEN ? AND ? AND r.deleted_at IS NULL

            UNION ALL

            SELECT 'payment' AS txn_type, date, voucher_no AS ref_no,
                   COALESCE(payee_name, 'N/A') AS party, ec.category_name AS description, amount
            FROM payments p
            LEFT JOIN expense_categories ec ON ec.id = p.category_id
            WHERE p.account_id = ? AND p.date BETWEEN ? AND ? AND p.deleted_at IS NULL

            UNION ALL

            SELECT IF(jd.debit_amount > 0, 'debit', 'credit') AS txn_type,
                   je.date, je.journal_no AS ref_no, 'Journal Entry' AS party,
                   je.narration AS description,
                   IF(jd.debit_amount > 0, jd.debit_amount, jd.credit_amount) AS amount
            FROM journal_details jd
            JOIN journal_entries je ON je.id = jd.journal_id
            WHERE jd.account_id = ? AND je.date BETWEEN ? AND ? AND je.deleted_at IS NULL

            ORDER BY date, ref_no
        ");
        $stmt->execute([$accountId, $fromDate, $toDate, $accountId, $fromDate, $toDate, $accountId, $fromDate, $toDate]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($transactions as $t) {
            if (in_array($t['txn_type'], ['receipt', 'debit'])) {
                $totalDebit += (float)$t['amount'];
            } else {
                $totalCredit += (float)$t['amount'];
            }
        }
    }
}

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
            <li class="breadcrumb-item active">Ledger</li>
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
            <h4 class="mb-0"><i class="bi bi-book me-2 text-primary"></i>Account Ledger</h4>
            <small class="text-muted">View all transactions for a selected account</small>
        </div>
        <?php if ($accountId && $accountInfo): ?>
        <button class="btn btn-outline-secondary no-print" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print
        </button>
        <?php endif; ?>
    </div>

    <!-- Filter Form -->
    <div class="card shadow-sm mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Select Account <span class="text-danger">*</span></label>
                    <select name="account_id" class="form-select select2" required>
                        <option value="">-- Select Account --</option>
                        <?php
                        $currentType = '';
                        foreach ($allAccounts as $a):
                            if ($a['account_type'] !== $currentType) {
                                if ($currentType !== '') echo '</optgroup>';
                                echo '<optgroup label="' . htmlspecialchars($a['account_type']) . '">';
                                $currentType = $a['account_type'];
                            }
                        ?>
                        <option value="<?= $a['id'] ?>" <?= $a['id'] == $accountId ? 'selected' : '' ?>>
                            [<?= htmlspecialchars($a['account_code']) ?>] <?= htmlspecialchars($a['account_name']) ?>
                        </option>
                        <?php endforeach; if ($currentType !== '') echo '</optgroup>'; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?= $fromDate ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?= $toDate ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search me-1"></i>View Ledger</button>
                    <a href="<?= BASE_PATH ?>/modules/accounts/ledger.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <?php if (!$accountId): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>Select an account above and click <strong>View Ledger</strong> to see its transactions.
    </div>

    <?php elseif (!$accountInfo): ?>
    <div class="alert alert-danger">Account not found.</div>

    <?php else: ?>

    <!-- Account Info Header -->
    <div class="card border-primary mb-3">
        <div class="card-body py-2">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0">
                        <code>[<?= htmlspecialchars($accountInfo['account_code']) ?>]</code>
                        <?= htmlspecialchars($accountInfo['account_name']) ?>
                        <span class="badge bg-primary ms-2"><?= htmlspecialchars($accountInfo['account_type']) ?></span>
                    </h5>
                    <small class="text-muted">
                        Period: <?= formatDate($fromDate) ?> to <?= formatDate($toDate) ?>
                    </small>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="me-3">
                        <span class="text-muted small">Opening:</span>
                        <strong class="<?= $openingBalance < 0 ? 'text-danger' : '' ?>"><?= formatCurrency($openingBalance) ?></strong>
                    </span>
                    <span class="me-3">
                        <span class="text-muted small">Closing:</span>
                        <?php $closingBal = $openingBalance + $totalDebit - $totalCredit; ?>
                        <strong class="<?= $closingBal < 0 ? 'text-danger' : 'text-success' ?>"><?= formatCurrency($closingBal) ?></strong>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-secondary">
                <div class="card-body py-2">
                    <div class="text-muted small">Opening Balance</div>
                    <div class="fw-bold fs-6 <?= $openingBalance < 0 ? 'text-danger' : '' ?>"><?= formatCurrency($openingBalance) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body py-2">
                    <div class="text-muted small">Total Debit</div>
                    <div class="fw-bold fs-6 text-success"><?= formatCurrency($totalDebit) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body py-2">
                    <div class="text-muted small">Total Credit</div>
                    <div class="fw-bold fs-6 text-danger"><?= formatCurrency($totalCredit) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body py-2">
                    <div class="text-muted small">Closing Balance</div>
                    <div class="fw-bold fs-6 <?= $closingBal < 0 ? 'text-danger' : 'text-primary' ?>"><?= formatCurrency($closingBal) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ledger Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-book me-2 text-primary"></i>Ledger Statement</span>
            <span class="text-muted small"><?= count($transactions) ?> entries</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Particulars</th>
                            <th>Ref. No.</th>
                            <th class="text-end text-success">Debit</th>
                            <th class="text-end text-danger">Credit</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Opening Balance Row -->
                        <tr class="fw-bold table-light">
                            <td>-</td>
                            <td><?= formatDate($fromDate) ?></td>
                            <td colspan="2"><em>Opening Balance b/f</em></td>
                            <td class="text-end text-success"><?= $openingBalance > 0 ? formatCurrency($openingBalance) : '' ?></td>
                            <td class="text-end text-danger"><?= $openingBalance < 0 ? formatCurrency(abs($openingBalance)) : '' ?></td>
                            <td class="text-end fw-bold"><?= formatCurrency($openingBalance) ?></td>
                        </tr>

                        <?php
                        $running = $openingBalance;
                        foreach ($transactions as $i => $t):
                            $isDebit = in_array($t['txn_type'], ['receipt', 'debit']);
                            if ($isDebit) {
                                $running += (float)$t['amount'];
                            } else {
                                $running -= (float)$t['amount'];
                            }
                        ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td class="text-nowrap"><?= formatDate($t['date']) ?></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($t['description'] ?? 'N/A') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($t['party'] ?? '') ?></small>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($t['ref_no']) ?></td>
                            <td class="text-end text-success"><?= $isDebit ? formatCurrency((float)$t['amount']) : '<span class="text-muted">-</span>' ?></td>
                            <td class="text-end text-danger"><?= !$isDebit ? formatCurrency((float)$t['amount']) : '<span class="text-muted">-</span>' ?></td>
                            <td class="text-end fw-semibold <?= $running < 0 ? 'text-danger' : '' ?>"><?= formatCurrency($running) ?></td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (empty($transactions)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No transactions found for this account in the selected period.</td></tr>
                        <?php endif; ?>

                        <!-- Totals Row -->
                        <tr class="table-secondary fw-bold">
                            <td colspan="4" class="text-end">Period Total</td>
                            <td class="text-end text-success"><?= formatCurrency($totalDebit) ?></td>
                            <td class="text-end text-danger"><?= formatCurrency($totalCredit) ?></td>
                            <td class="text-end"><?= formatCurrency($running ?? $openingBalance) ?></td>
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
