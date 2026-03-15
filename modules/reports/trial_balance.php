<?php
$pageTitle = 'Trial Balance';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

require_once __DIR__ . '/../../app/helpers/functions.php';

$pdo = db();

$as_of_date = isset($_GET['as_of_date']) ? sanitize($_GET['as_of_date']) : date('Y-m-d');

/*
 * Strategy:
 * 1. Start with opening_balance from chart_of_accounts.
 * 2. Add movements from receipts  (credit account_id).
 * 3. Add movements from payments  (debit  account_id).
 * 4. Add journal_details debits/credits.
 * Final debit/credit per account is determined by account_type convention:
 *   Asset / Expense  → normal debit balance
 *   Liability / Income / Equity → normal credit balance
 */

// Fetch all accounts
$acctStmt = $pdo->query(
    "SELECT id, account_code, account_name, account_type, opening_balance
     FROM chart_of_accounts
     ORDER BY account_type, account_code"
);
$accounts = $acctStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($accounts)) {
    $accounts = [];
}

// Index by account id for quick lookup
$accountMap = [];
foreach ($accounts as $acc) {
    $accountMap[$acc['id']] = [
        'account_code'    => $acc['account_code'],
        'account_name'    => $acc['account_name'],
        'account_type'    => $acc['account_type'],
        'opening_balance' => (float)$acc['opening_balance'],
        'debit_total'     => 0.0,
        'credit_total'    => 0.0,
    ];
}

// Receipts: credit the account_id (income side)
$rcptStmt = $pdo->prepare(
    "SELECT account_id, SUM(amount) AS total
     FROM receipts
     WHERE deleted_at IS NULL AND date <= :as_of_date AND account_id IS NOT NULL
     GROUP BY account_id"
);
$rcptStmt->execute([':as_of_date' => $as_of_date]);
foreach ($rcptStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (isset($accountMap[$r['account_id']])) {
        $accountMap[$r['account_id']]['credit_total'] += (float)$r['total'];
    }
}

// Payments: debit the account_id (expense side)
$pmtStmt = $pdo->prepare(
    "SELECT account_id, SUM(amount) AS total
     FROM payments
     WHERE deleted_at IS NULL AND date <= :as_of_date AND account_id IS NOT NULL
     GROUP BY account_id"
);
$pmtStmt->execute([':as_of_date' => $as_of_date]);
foreach ($pmtStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (isset($accountMap[$r['account_id']])) {
        $accountMap[$r['account_id']]['debit_total'] += (float)$r['total'];
    }
}

// Journal details: explicit debit/credit amounts
$jrnStmt = $pdo->prepare(
    "SELECT jd.account_id,
            SUM(jd.debit_amount)  AS total_debit,
            SUM(jd.credit_amount) AS total_credit
     FROM journal_details jd
     INNER JOIN journal_entries je ON je.id = jd.journal_id
     WHERE je.entry_date <= :as_of_date
     GROUP BY jd.account_id"
);
$jrnStmt->execute([':as_of_date' => $as_of_date]);
foreach ($jrnStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (isset($accountMap[$r['account_id']])) {
        $accountMap[$r['account_id']]['debit_total']  += (float)$r['total_debit'];
        $accountMap[$r['account_id']]['credit_total'] += (float)$r['total_credit'];
    }
}

/*
 * Determine final debit/credit balance per account:
 * opening_balance is stored as a signed value (positive = debit balance for asset/expense,
 * positive = credit balance for liability/income/equity).
 * We add it to the respective side based on account_type.
 */
$debit_account_types  = ['Asset', 'Expense'];
$credit_account_types = ['Liability', 'Income', 'Equity', 'Fund'];

$grouped_accounts = [];
$grand_debit  = 0.0;
$grand_credit = 0.0;

foreach ($accountMap as $id => $acc) {
    $ob = $acc['opening_balance'];
    if (in_array($acc['account_type'], $debit_account_types)) {
        $net_debit  = $ob + $acc['debit_total'] - $acc['credit_total'];
        $net_credit = 0.0;
        if ($net_debit < 0) {
            $net_credit = abs($net_debit);
            $net_debit  = 0.0;
        }
    } else {
        $net_credit = $ob + $acc['credit_total'] - $acc['debit_total'];
        $net_debit  = 0.0;
        if ($net_credit < 0) {
            $net_debit  = abs($net_credit);
            $net_credit = 0.0;
        }
    }

    if ($net_debit == 0.0 && $net_credit == 0.0) {
        continue; // skip zero-balance accounts from display
    }

    $grand_debit  += $net_debit;
    $grand_credit += $net_credit;

    $type = $acc['account_type'] ?: 'Other';
    $grouped_accounts[$type][] = [
        'account_code' => $acc['account_code'],
        'account_name' => $acc['account_name'],
        'debit'        => $net_debit,
        'credit'       => $net_credit,
    ];
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
.balance-check { font-size: 0.85rem; }
</style>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

    <!-- Print header -->
    <div class="page-header-print text-center mb-3">
        <h4 class="mb-0">Trial Balance</h4>
        <small>As of: <?= htmlspecialchars(formatDate($as_of_date)) ?></small>
    </div>

    <!-- Page heading -->
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h4 class="mb-0"><i class="bi bi-bar-chart-line-fill text-secondary me-2"></i>Trial Balance</h4>
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
                    <label class="form-label form-label-sm mb-1">As of Date</label>
                    <input type="date" name="as_of_date" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($as_of_date) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search"></i> Generate
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="trial_balance.php" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($grouped_accounts)): ?>

    <!-- Balance check alert -->
    <?php $diff = abs($grand_debit - $grand_credit); ?>
    <?php if ($diff < 0.01): ?>
    <div class="alert alert-success py-2 balance-check">
        <i class="bi bi-check-circle-fill me-1"></i>
        <strong>Trial Balance Agrees.</strong> Total Debit = Total Credit = <?= formatCurrency($grand_debit) ?>
    </div>
    <?php else: ?>
    <div class="alert alert-danger py-2 balance-check">
        <i class="bi bi-exclamation-triangle-fill me-1"></i>
        <strong>Trial Balance Does NOT Agree.</strong>
        Debit: <?= formatCurrency($grand_debit) ?> &nbsp;|&nbsp;
        Credit: <?= formatCurrency($grand_credit) ?> &nbsp;|&nbsp;
        Difference: <?= formatCurrency($diff) ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-bordered table-sm mb-0" id="trialTable">
                <thead class="table-dark">
                    <tr>
                        <th>Account Code</th>
                        <th>Account Name</th>
                        <th>Account Type</th>
                        <th class="text-end">Debit Balance</th>
                        <th class="text-end">Credit Balance</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($grouped_accounts as $type => $type_rows):
                    $type_debit  = array_sum(array_column($type_rows, 'debit'));
                    $type_credit = array_sum(array_column($type_rows, 'credit'));
                ?>
                    <tr class="table-light">
                        <td colspan="5" class="fw-semibold text-primary">
                            <i class="bi bi-folder-fill me-1"></i><?= htmlspecialchars($type) ?>
                        </td>
                    </tr>
                    <?php foreach ($type_rows as $acc): ?>
                    <tr>
                        <td class="text-muted small"><?= htmlspecialchars($acc['account_code']) ?></td>
                        <td><?= htmlspecialchars($acc['account_name']) ?></td>
                        <td><?= htmlspecialchars($type) ?></td>
                        <td class="text-end"><?= $acc['debit']  > 0 ? formatCurrency($acc['debit'])  : '-' ?></td>
                        <td class="text-end"><?= $acc['credit'] > 0 ? formatCurrency($acc['credit']) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-warning fw-semibold">
                        <td colspan="3" class="text-end">Subtotal — <?= htmlspecialchars($type) ?>:</td>
                        <td class="text-end"><?= $type_debit  > 0 ? formatCurrency($type_debit)  : '-' ?></td>
                        <td class="text-end"><?= $type_credit > 0 ? formatCurrency($type_credit) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-dark fw-bold">
                        <td colspan="3" class="text-end fs-6">Grand Total:</td>
                        <td class="text-end fs-6"><?= formatCurrency($grand_debit) ?></td>
                        <td class="text-end fs-6"><?= formatCurrency($grand_credit) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>No account balances found as of <?= htmlspecialchars(formatDate($as_of_date)) ?>.
    </div>
    <?php endif; ?>

</div><!-- content-area -->
</div><!-- main-content -->
</div><!-- wrapper -->

<script>
document.getElementById('exportExcel')?.addEventListener('click', function () {
    const table = document.getElementById('trialTable');
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
    a.download = 'trial_balance_<?= $as_of_date ?>.csv';
    a.click();
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
