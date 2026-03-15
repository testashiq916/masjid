<?php
$pageTitle = 'Balance Sheet';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

require_once __DIR__ . '/../../app/helpers/functions.php';

$pdo = db();

$as_of_date = isset($_GET['as_of_date']) ? sanitize($_GET['as_of_date']) : date('Y-m-d');

// Financial year start (April 1) for surplus/deficit calculation
$fy_start = (date('m', strtotime($as_of_date)) >= 4)
    ? date('Y', strtotime($as_of_date)) . '-04-01'
    : (date('Y', strtotime($as_of_date)) - 1) . '-04-01';

/*
 * Helper: sum account balances for given account_type(s) and optional name patterns.
 * We derive balances from:
 *   opening_balance + journal_details movements + receipts credits + payments debits.
 */
function getAccountBalance(PDO $pdo, string $as_of_date, array $types, array $namePatterns = []): array
{
    $typePlaceholders = implode(',', array_fill(0, count($types), '?'));
    $sql = "SELECT coa.id, coa.account_name, coa.account_type, coa.opening_balance
            FROM chart_of_accounts coa
            WHERE coa.account_type IN ($typePlaceholders)";
    $bindParams = $types;

    if (!empty($namePatterns)) {
        $likeClauses = array_map(fn($p) => "coa.account_name LIKE ?", $namePatterns);
        $sql .= " AND (" . implode(' OR ', $likeClauses) . ")";
        foreach ($namePatterns as $p) {
            $bindParams[] = '%' . $p . '%';
        }
    }
    $sql .= " ORDER BY coa.account_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindParams);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($accounts as $acc) {
        // Journal movements
        $jrnStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(jd.debit_amount),0) AS td, COALESCE(SUM(jd.credit_amount),0) AS tc
             FROM journal_details jd
             INNER JOIN journal_entries je ON je.id = jd.journal_id
             WHERE jd.account_id = ? AND je.entry_date <= ?"
        );
        $jrnStmt->execute([$acc['id'], $as_of_date]);
        $jrn = $jrnStmt->fetch(PDO::FETCH_ASSOC);

        // Receipts (credit side — income/fund accounts)
        $rcptStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(amount),0) AS total FROM receipts
             WHERE account_id = ? AND deleted_at IS NULL AND date <= ?"
        );
        $rcptStmt->execute([$acc['id'], $as_of_date]);
        $rcpt = $rcptStmt->fetchColumn();

        // Payments (debit side — asset/cash accounts)
        $pmtStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(amount),0) AS total FROM payments
             WHERE account_id = ? AND deleted_at IS NULL AND date <= ?"
        );
        $pmtStmt->execute([$acc['id'], $as_of_date]);
        $pmt = $pmtStmt->fetchColumn();

        $ob = (float)$acc['opening_balance'];
        // Asset-type: balance = OB + debits - credits
        // Liability/Fund: balance = OB + credits - debits
        if (in_array($acc['account_type'], ['Asset', 'Expense'])) {
            $balance = $ob + (float)$jrn['td'] + (float)$pmt - (float)$jrn['tc'] - (float)$rcpt;
        } else {
            $balance = $ob + (float)$jrn['tc'] + (float)$rcpt - (float)$jrn['td'] - (float)$pmt;
        }

        $result[] = [
            'account_name' => $acc['account_name'],
            'balance'      => $balance,
        ];
    }
    return $result;
}

// ============================================================
// ASSETS
// ============================================================

// Fixed Assets
$fixed_assets_accounts = getAccountBalance($pdo, $as_of_date, ['Asset'], ['Building', 'Land', 'Furniture', 'Equipment', 'Vehicle', 'Machinery', 'Fixed']);
// Current Assets — Cash
$cash_accounts  = getAccountBalance($pdo, $as_of_date, ['Asset'], ['Cash']);
// Current Assets — Bank
$bank_accounts  = getAccountBalance($pdo, $as_of_date, ['Asset'], ['Bank']);
// Other current assets (receivables etc.) — generic Asset excluding already fetched
$other_assets_accounts = getAccountBalance($pdo, $as_of_date, ['Asset'], ['Receivable', 'Advance', 'Prepaid', 'Deposit', 'Stock', 'Inventory']);

$total_fixed    = array_sum(array_column($fixed_assets_accounts, 'balance'));
$total_cash     = array_sum(array_column($cash_accounts, 'balance'));
$total_bank     = array_sum(array_column($bank_accounts, 'balance'));
$total_other_ca = array_sum(array_column($other_assets_accounts, 'balance'));
$total_current_assets = $total_cash + $total_bank + $total_other_ca;
$total_assets   = $total_fixed + $total_current_assets;

// ============================================================
// LIABILITIES
// ============================================================

// Current Liabilities: payables
$payable_accounts = getAccountBalance($pdo, $as_of_date, ['Liability'], ['Payable', 'Creditor', 'Accrued', 'Outstanding']);
// Tenant deposits
$deposit_accounts = getAccountBalance($pdo, $as_of_date, ['Liability'], ['Deposit', 'Security']);
// Other liabilities
$other_liab_accounts = getAccountBalance($pdo, $as_of_date, ['Liability'], ['Loan', 'Advance Received', 'Deferred', 'Other']);

$total_payables  = array_sum(array_column($payable_accounts, 'balance'));
$total_deposits  = array_sum(array_column($deposit_accounts, 'balance'));
$total_other_liab = array_sum(array_column($other_liab_accounts, 'balance'));
$total_current_liabilities = $total_payables + $total_deposits + $total_other_liab;

// ============================================================
// FUND BALANCES / EQUITY
// ============================================================
$fund_accounts = getAccountBalance($pdo, $as_of_date, ['Equity', 'Fund']);
$total_funds = array_sum(array_column($fund_accounts, 'balance'));

// ============================================================
// SURPLUS / DEFICIT for current FY
// ============================================================
$incStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount),0) FROM receipts
     WHERE deleted_at IS NULL AND date BETWEEN :fy_start AND :as_of_date"
);
$incStmt->execute([':fy_start' => $fy_start, ':as_of_date' => $as_of_date]);
$fy_income = (float)$incStmt->fetchColumn();

$expStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount),0) FROM payments
     WHERE deleted_at IS NULL AND date BETWEEN :fy_start AND :as_of_date"
);
$expStmt->execute([':fy_start' => $fy_start, ':as_of_date' => $as_of_date]);
$fy_expense = (float)$expStmt->fetchColumn();

$surplus_deficit = $fy_income - $fy_expense;

$total_liabilities_funds = $total_current_liabilities + $total_funds + $surplus_deficit;

require_once __DIR__ . '/../../templates/header.php';
?>
<style>
@media print {
    .no-print { display: none !important; }
    .main-content { margin: 0 !important; }
    .content-area { margin-top: 0 !important; padding: 0 !important; }
    .wrapper { display: block !important; }
    body { font-size: 12px; }
    .bs-report { font-size: 12px; }
    .page-header-print { display: block !important; }
    .bs-card { box-shadow: none !important; border: 1px solid #dee2e6 !important; }
}
.page-header-print { display: none; }
.bs-section-title {
    padding: 8px 12px;
    font-weight: 700;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    border-bottom: 1px solid #dee2e6;
}
.bs-group-title td {
    padding: 6px 12px;
    font-weight: 600;
    font-size: 0.85rem;
    background: #f1f3f5;
    border-top: 1px solid #dee2e6;
}
.bs-item td { padding: 3px 12px 3px 24px; font-size: 0.875rem; }
.bs-subtotal td { padding: 5px 12px; font-weight: 600; border-top: 1px solid #dee2e6; }
.bs-grand-total td { padding: 8px 12px; font-weight: 700; font-size: 1rem; border-top: 2px solid #495057; }
.balance-ok  { background: #d1e7dd; }
.balance-bad { background: #f8d7da; }
</style>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

    <!-- Print header -->
    <div class="page-header-print text-center mb-4">
        <h3 class="mb-1">Balance Sheet</h3>
        <p class="mb-0">As at <?= htmlspecialchars(formatDate($as_of_date)) ?></p>
        <hr>
    </div>

    <!-- Page heading -->
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h4 class="mb-0">
            <i class="bi bi-bank2 text-dark me-2"></i>Balance Sheet
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
                    <a href="balance_sheet.php" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Balance check -->
    <?php $diff = abs($total_assets - $total_liabilities_funds); ?>
    <div class="alert py-2 <?= $diff < 0.01 ? 'alert-success' : 'alert-danger' ?> no-print">
        <i class="bi <?= $diff < 0.01 ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-1"></i>
        <?php if ($diff < 0.01): ?>
            <strong>Balance Sheet Balances.</strong> Total Assets = Total Liabilities &amp; Funds = <?= formatCurrency($total_assets) ?>
        <?php else: ?>
            <strong>Balance Sheet Does NOT Balance.</strong>
            Assets: <?= formatCurrency($total_assets) ?> | Liabilities+Funds: <?= formatCurrency($total_liabilities_funds) ?> | Diff: <?= formatCurrency($diff) ?>
        <?php endif; ?>
    </div>

    <!-- Balance Sheet two-column layout -->
    <div class="bs-report">
        <div class="row g-4">

            <!-- LEFT: ASSETS -->
            <div class="col-lg-6">
                <div class="card bs-card h-100">
                    <div class="bs-section-title bg-primary text-white">Assets</div>
                    <table class="table table-sm mb-0">
                        <tbody>

                            <!-- Fixed Assets -->
                            <tr class="bs-group-title">
                                <td colspan="2"><i class="bi bi-buildings me-1"></i>Fixed Assets</td>
                            </tr>
                            <?php foreach ($fixed_assets_accounts as $row): ?>
                            <tr class="bs-item">
                                <td><?= htmlspecialchars($row['account_name']) ?></td>
                                <td class="text-end"><?= formatCurrency($row['balance']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($fixed_assets_accounts)): ?>
                            <tr class="bs-item"><td colspan="2" class="text-muted">No fixed asset accounts.</td></tr>
                            <?php endif; ?>
                            <tr class="bs-subtotal">
                                <td>Total Fixed Assets</td>
                                <td class="text-end fw-bold"><?= formatCurrency($total_fixed) ?></td>
                            </tr>

                            <!-- Current Assets -->
                            <tr class="bs-group-title">
                                <td colspan="2"><i class="bi bi-cash-coin me-1"></i>Current Assets</td>
                            </tr>
                            <!-- Cash -->
                            <?php foreach ($cash_accounts as $row): ?>
                            <tr class="bs-item">
                                <td><?= htmlspecialchars($row['account_name']) ?></td>
                                <td class="text-end"><?= formatCurrency($row['balance']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <!-- Bank -->
                            <?php foreach ($bank_accounts as $row): ?>
                            <tr class="bs-item">
                                <td><?= htmlspecialchars($row['account_name']) ?></td>
                                <td class="text-end"><?= formatCurrency($row['balance']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <!-- Other CA -->
                            <?php foreach ($other_assets_accounts as $row): ?>
                            <tr class="bs-item">
                                <td><?= htmlspecialchars($row['account_name']) ?></td>
                                <td class="text-end"><?= formatCurrency($row['balance']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($cash_accounts) && empty($bank_accounts) && empty($other_assets_accounts)): ?>
                            <tr class="bs-item"><td colspan="2" class="text-muted">No current asset accounts.</td></tr>
                            <?php endif; ?>
                            <tr class="bs-subtotal">
                                <td>Total Current Assets</td>
                                <td class="text-end fw-bold"><?= formatCurrency($total_current_assets) ?></td>
                            </tr>

                        </tbody>
                        <tfoot>
                            <tr class="bs-grand-total table-primary">
                                <td>TOTAL ASSETS</td>
                                <td class="text-end"><?= formatCurrency($total_assets) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- RIGHT: LIABILITIES & FUNDS -->
            <div class="col-lg-6">
                <div class="card bs-card h-100">
                    <div class="bs-section-title bg-danger text-white">Liabilities &amp; Fund Balances</div>
                    <table class="table table-sm mb-0">
                        <tbody>

                            <!-- Current Liabilities -->
                            <tr class="bs-group-title">
                                <td colspan="2"><i class="bi bi-exclamation-circle me-1"></i>Current Liabilities</td>
                            </tr>
                            <?php foreach ($payable_accounts as $row): ?>
                            <tr class="bs-item">
                                <td><?= htmlspecialchars($row['account_name']) ?></td>
                                <td class="text-end"><?= formatCurrency($row['balance']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php foreach ($deposit_accounts as $row): ?>
                            <tr class="bs-item">
                                <td><?= htmlspecialchars($row['account_name']) ?> <small class="text-muted">(Deposit)</small></td>
                                <td class="text-end"><?= formatCurrency($row['balance']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php foreach ($other_liab_accounts as $row): ?>
                            <tr class="bs-item">
                                <td><?= htmlspecialchars($row['account_name']) ?></td>
                                <td class="text-end"><?= formatCurrency($row['balance']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($payable_accounts) && empty($deposit_accounts) && empty($other_liab_accounts)): ?>
                            <tr class="bs-item"><td colspan="2" class="text-muted">No liability accounts.</td></tr>
                            <?php endif; ?>
                            <tr class="bs-subtotal">
                                <td>Total Current Liabilities</td>
                                <td class="text-end fw-bold"><?= formatCurrency($total_current_liabilities) ?></td>
                            </tr>

                            <!-- Fund Balances -->
                            <tr class="bs-group-title">
                                <td colspan="2"><i class="bi bi-wallet2 me-1"></i>Fund Balances</td>
                            </tr>
                            <?php foreach ($fund_accounts as $row): ?>
                            <tr class="bs-item">
                                <td><?= htmlspecialchars($row['account_name']) ?></td>
                                <td class="text-end"><?= formatCurrency($row['balance']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($fund_accounts)): ?>
                            <tr class="bs-item"><td colspan="2" class="text-muted">No fund accounts.</td></tr>
                            <?php endif; ?>
                            <tr class="bs-subtotal">
                                <td>Total Fund Balances</td>
                                <td class="text-end fw-bold"><?= formatCurrency($total_funds) ?></td>
                            </tr>

                            <!-- Surplus / Deficit -->
                            <tr class="bs-group-title">
                                <td colspan="2"><i class="bi bi-graph-up me-1"></i>Current Period Surplus / Deficit</td>
                            </tr>
                            <tr class="bs-item">
                                <td>
                                    Income (<?= htmlspecialchars(formatDate($fy_start)) ?> to <?= htmlspecialchars(formatDate($as_of_date)) ?>)
                                </td>
                                <td class="text-end text-success"><?= formatCurrency($fy_income) ?></td>
                            </tr>
                            <tr class="bs-item">
                                <td>Expenditure</td>
                                <td class="text-end text-danger">(<?= formatCurrency($fy_expense) ?>)</td>
                            </tr>
                            <tr class="bs-subtotal <?= $surplus_deficit >= 0 ? 'table-success' : 'table-danger' ?>">
                                <td><?= $surplus_deficit >= 0 ? 'Surplus' : 'Deficit' ?></td>
                                <td class="text-end"><?= formatCurrency(abs($surplus_deficit)) ?></td>
                            </tr>

                        </tbody>
                        <tfoot>
                            <tr class="bs-grand-total table-danger">
                                <td>TOTAL LIABILITIES &amp; FUNDS</td>
                                <td class="text-end"><?= formatCurrency($total_liabilities_funds) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div><!-- row -->

        <!-- Balance confirmation -->
        <div class="mt-3 p-3 rounded text-center fw-bold fs-5 <?= $diff < 0.01 ? 'balance-ok' : 'balance-bad' ?>">
            <?php if ($diff < 0.01): ?>
                Balance Sheet Agrees &mdash; Assets = Liabilities + Funds = <?= formatCurrency($total_assets) ?>
            <?php else: ?>
                Balance Sheet Does NOT Agree &mdash; Difference: <?= formatCurrency($diff) ?>
            <?php endif; ?>
        </div>

    </div><!-- bs-report -->

</div><!-- content-area -->
</div><!-- main-content -->
</div><!-- wrapper -->

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
