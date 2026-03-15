<?php
$pageTitle = 'Add Payment Voucher';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

$errors  = [];
$success = false;
$newId   = null;
$newVcNo = null;

// ── POST handler ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please try again.';
    } else {
        $voucherNo   = sanitize($_POST['voucher_no']   ?? '');
        $date        = sanitize($_POST['date']          ?? date('Y-m-d'));
        $payeeName   = sanitize($_POST['payee_name']    ?? '');
        $categoryId  = (int)($_POST['category_id']     ?? 0);
        $accountId   = (int)($_POST['account_id']      ?? 0);
        $paymentMode = sanitize($_POST['payment_mode'] ?? '');
        $chequeNo    = sanitize($_POST['cheque_no']    ?? '');
        $bankRef     = sanitize($_POST['bank_ref']     ?? '');
        $amount      = (float)($_POST['amount']        ?? 0);
        $remarks     = sanitize($_POST['remarks']      ?? '');
        $approvedBy  = (int)($_POST['approved_by']     ?? 0);
        $createdBy   = currentUserId();

        if (empty($voucherNo))   $errors[] = 'Voucher number is required.';
        if (empty($date))        $errors[] = 'Date is required.';
        if (empty($payeeName))   $errors[] = 'Payee name is required.';
        if ($categoryId <= 0)    $errors[] = 'Please select an expense category.';
        if ($accountId  <= 0)    $errors[] = 'Please select a payment account.';
        if (empty($paymentMode)) $errors[] = 'Payment mode is required.';
        if ($amount    <= 0)     $errors[] = 'Amount must be greater than zero.';
        if ($paymentMode === 'cheque' && empty($chequeNo)) $errors[] = 'Cheque number is required.';

        if (empty($errors)) {
            try {
                $pdo = db();
                $pdo->beginTransaction();

                // 1. Insert into payments
                $stmt = $pdo->prepare("
                    INSERT INTO payments
                        (voucher_no, date, payee_name, category_id, account_id,
                         payment_mode, cheque_no, bank_ref, amount, remarks,
                         approved_by, created_by, created_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())
                ");
                $stmt->execute([
                    $voucherNo, $date, $payeeName, $categoryId, $accountId,
                    $paymentMode, $chequeNo ?: null, $bankRef ?: null,
                    $amount, $remarks,
                    $approvedBy > 0 ? $approvedBy : null,
                    $createdBy
                ]);
                $newId = (int)$pdo->lastInsertId();

                // 2. Get expense category COA account
                $catStmt = $pdo->prepare("SELECT category_name, account_id FROM expense_categories WHERE id = ?");
                $catStmt->execute([$categoryId]);
                $catRow = $catStmt->fetch();
                $expenseAccountId = $catRow ? (int)$catRow['account_id'] : null;

                // 3. Journal entry: Debit Expense, Credit Cash/Bank
                $jeNo      = generateNumber('JV', 'journal_entries', 'entry_no');
                $narration = "Payment {$voucherNo} - {$payeeName} - " . ($catRow['category_name'] ?? '');

                $jeStmt = $pdo->prepare("
                    INSERT INTO journal_entries
                        (entry_no, date, narration, reference_type, reference_id, created_by, created_at)
                    VALUES (?,?,?,?,?,?,NOW())
                ");
                $jeStmt->execute([$jeNo, $date, $narration, 'payment', $newId, $createdBy]);
                $jeId = (int)$pdo->lastInsertId();

                $jdStmt = $pdo->prepare("
                    INSERT INTO journal_details (journal_id, account_id, debit_amount, credit_amount, narration)
                    VALUES (?,?,?,?,?)
                ");

                // Debit: Expense account
                if ($expenseAccountId) {
                    $jdStmt->execute([$jeId, $expenseAccountId, $amount, 0, $narration]);
                }
                // Credit: Cash/Bank account
                $jdStmt->execute([$jeId, $accountId, 0, $amount, $narration]);

                // 4. Cash / Bank book entry
                if ($paymentMode === 'cash') {
                    $pdo->prepare("
                        INSERT INTO cash_book
                            (date, particulars, receipt_amount, payment_amount, account_id,
                             reference_type, reference_id, created_by, created_at)
                        VALUES (?,?,?,?,?,?,?,?,NOW())
                    ")->execute([$date, $narration, 0, $amount, $accountId, 'payment', $newId, $createdBy]);
                } else {
                    $pdo->prepare("
                        INSERT INTO bank_book
                            (date, particulars, deposit_amount, withdrawal_amount, account_id,
                             reference_type, reference_id, cheque_no, bank_ref, created_by, created_at)
                        VALUES (?,?,?,?,?,?,?,?,?,?,NOW())
                    ")->execute([$date, $narration, 0, $amount, $accountId,
                                 'payment', $newId, $chequeNo ?: null, $bankRef ?: null, $createdBy]);
                }

                $pdo->commit();
                logAudit('create', 'payments', $newId, [], ['voucher_no' => $voucherNo, 'amount' => $amount]);
                $newVcNo = $voucherNo;
                $success = true;
                setFlash('success', "Payment voucher {$voucherNo} saved successfully.");
            } catch (\Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// ── Dropdown data ──────────────────────────────────────────────
$voucherNo = generateNumber('PV', 'payments', 'voucher_no');
$expCats   = db()->query("SELECT id, category_name FROM expense_categories WHERE deleted_at IS NULL ORDER BY category_name")->fetchAll();
$accounts  = db()->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE account_type = 'Assets' AND deleted_at IS NULL ORDER BY account_name")->fetchAll();
$usersList = db()->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/modules/expenses/payment_list.php">Payments</a></li>
            <li class="breadcrumb-item active">Add Payment</li>
        </ol>
    </nav>

    <?php if ($success): ?>
    <div class="card border-success mb-4">
        <div class="card-body text-center py-5">
            <i class="bi bi-check-circle-fill text-success" style="font-size:3rem;"></i>
            <h4 class="mt-3 text-success">Payment Voucher Saved!</h4>
            <p class="text-muted mb-4">Voucher No: <strong><?= htmlspecialchars($newVcNo) ?></strong></p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="<?= BASE_PATH ?>/modules/expenses/view_payment.php?id=<?= $newId ?>&print=1"
                   class="btn btn-primary" target="_blank">
                    <i class="bi bi-printer me-1"></i> Print Voucher
                </a>
                <a href="<?= BASE_PATH ?>/modules/expenses/add_payment.php" class="btn btn-success">
                    <i class="bi bi-plus-circle me-1"></i> Add Another
                </a>
                <a href="<?= BASE_PATH ?>/modules/expenses/payment_list.php" class="btn btn-outline-secondary">
                    <i class="bi bi-list me-1"></i> View All Payments
                </a>
            </div>
        </div>
    </div>
    <?php else: ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <strong><i class="bi bi-exclamation-triangle me-1"></i> Please fix the following errors:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-danger text-white d-flex align-items-center">
            <i class="bi bi-cash-coin me-2 fs-5"></i>
            <h5 class="mb-0">Add Payment Voucher</h5>
        </div>
        <div class="card-body">
            <form method="POST" id="paymentForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">

                <div class="row g-3">
                    <!-- Voucher No -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Voucher No <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="voucher_no"
                                   value="<?= htmlspecialchars($_POST['voucher_no'] ?? $voucherNo) ?>" readonly>
                            <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                        </div>
                        <div class="form-text">Auto-generated</div>
                    </div>

                    <!-- Date -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                        <input type="text" class="form-control datepicker" name="date"
                               value="<?= htmlspecialchars($_POST['date'] ?? date('Y-m-d')) ?>" required>
                    </div>

                    <!-- Payment Mode -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Payment Mode <span class="text-danger">*</span></label>
                        <select class="form-select" id="payment_mode" name="payment_mode" required>
                            <option value="">-- Select Mode --</option>
                            <?php
                            $modes   = ['cash'=>'Cash','bank'=>'Bank Transfer','cheque'=>'Cheque','online'=>'Online','upi'=>'UPI'];
                            $selMode = $_POST['payment_mode'] ?? '';
                            foreach ($modes as $val => $label):
                            ?>
                            <option value="<?= $val ?>" <?= $selMode === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Payee Name -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Payee Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="payee_name"
                               value="<?= htmlspecialchars($_POST['payee_name'] ?? '') ?>"
                               placeholder="Vendor / person paid" required>
                    </div>

                    <!-- Category -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Expense Category <span class="text-danger">*</span></label>
                        <select class="form-select select2-basic" name="category_id" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($expCats as $cat): ?>
                            <option value="<?= $cat['id'] ?>"
                                <?= (($_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Account -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Pay From Account <span class="text-danger">*</span></label>
                        <select class="form-select select2-basic" name="account_id" required>
                            <option value="">-- Select Account --</option>
                            <?php foreach ($accounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"
                                <?= (($_POST['account_id'] ?? '') == $acc['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($acc['account_code'] . ' - ' . $acc['account_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Approved By -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Approved By</label>
                        <select class="form-select select2-basic" name="approved_by">
                            <option value="">-- Select Approver --</option>
                            <?php foreach ($usersList as $u): ?>
                            <option value="<?= $u['id'] ?>"
                                <?= (($_POST['approved_by'] ?? '') == $u['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['full_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Cheque No (conditional) -->
                    <div class="col-md-4" id="chequeRow" style="display:none;">
                        <label class="form-label fw-semibold">Cheque No <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cheque_no" name="cheque_no"
                               value="<?= htmlspecialchars($_POST['cheque_no'] ?? '') ?>"
                               placeholder="Cheque number">
                    </div>

                    <!-- Bank Reference -->
                    <div class="col-md-4" id="bankRefRow">
                        <label class="form-label fw-semibold">Bank Reference / UTR</label>
                        <input type="text" class="form-control" name="bank_ref"
                               value="<?= htmlspecialchars($_POST['bank_ref'] ?? '') ?>"
                               placeholder="Transaction / UTR reference">
                    </div>

                    <!-- Amount -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Amount (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" class="form-control" name="amount"
                                   value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
                                   step="0.01" min="0.01" placeholder="0.00" required>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">Remarks / Narration</label>
                        <textarea class="form-control" name="remarks" rows="2"
                                  placeholder="Optional remarks..."><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
                    </div>
                </div>

                <hr>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-save me-1"></i> Save Payment
                    </button>
                    <a href="<?= BASE_PATH ?>/modules/expenses/payment_list.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div><!-- content-area -->
</div><!-- main-content -->
</div><!-- wrapper -->

<?php
$extraJs = <<<'JS'
<script>
$(function () {
    flatpickr('.datepicker', { dateFormat: 'Y-m-d', allowInput: true });
    $('.select2-basic').select2({ theme: 'bootstrap-5', width: '100%' });

    function togglePaymentFields() {
        var mode = $('#payment_mode').val();
        if (mode === 'cheque') {
            $('#chequeRow').show();
            $('#cheque_no').prop('required', true);
        } else {
            $('#chequeRow').hide();
            $('#cheque_no').prop('required', false).val('');
        }
        $('#bankRefRow').toggle(mode !== 'cash');
    }
    $('#payment_mode').on('change', togglePaymentFields);
    togglePaymentFields();
});
</script>
JS;
require_once __DIR__ . '/../../templates/footer.php';
