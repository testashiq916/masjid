<?php
$pageTitle = 'Add Donation Receipt';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

$errors   = [];
$success  = false;
$newId    = null;
$newRcpNo = null;

// ── POST handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please try again.';
    } else {
        $receiptNo   = sanitize($_POST['receipt_no']   ?? '');
        $date        = sanitize($_POST['date']          ?? date('Y-m-d'));
        $donorId     = (int)($_POST['donor_id']         ?? 0);
        $donorName   = sanitize($_POST['donor_name']    ?? '');
        $donorPhone  = sanitize($_POST['donor_phone']   ?? '');
        $categoryId  = (int)($_POST['category_id']      ?? 0);
        $accountId   = (int)($_POST['account_id']       ?? 0);
        $paymentMode = sanitize($_POST['payment_mode']  ?? '');
        $chequeNo    = sanitize($_POST['cheque_no']     ?? '');
        $bankRef     = sanitize($_POST['bank_ref']      ?? '');
        $amount      = (float)($_POST['amount']         ?? 0);
        $remarks     = sanitize($_POST['remarks']       ?? '');
        $createdBy   = currentUserId();

        // Validation
        if (empty($receiptNo))   $errors[] = 'Receipt number is required.';
        if (empty($date))        $errors[] = 'Date is required.';
        if (empty($donorName))   $errors[] = 'Donor name is required.';
        if ($categoryId <= 0)    $errors[] = 'Please select a category.';
        if ($accountId  <= 0)    $errors[] = 'Please select an account.';
        if (empty($paymentMode)) $errors[] = 'Payment mode is required.';
        if ($amount    <= 0)     $errors[] = 'Amount must be greater than zero.';
        if ($paymentMode === 'cheque' && empty($chequeNo)) $errors[] = 'Cheque number is required for cheque payments.';

        if (empty($errors)) {
            try {
                $pdo = db();
                $pdo->beginTransaction();

                // 1. Insert into receipts
                $stmt = $pdo->prepare("
                    INSERT INTO receipts
                        (receipt_no, date, donor_id, donor_name, donor_phone,
                         category_id, account_id, payment_mode, cheque_no,
                         bank_ref, amount, remarks, created_by, created_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
                ");
                $stmt->execute([
                    $receiptNo, $date,
                    $donorId > 0 ? $donorId : null,
                    $donorName, $donorPhone,
                    $categoryId, $accountId,
                    $paymentMode, $chequeNo ?: null,
                    $bankRef ?: null, $amount, $remarks, $createdBy
                ]);
                $newId = (int)$pdo->lastInsertId();

                // 2. Determine account type for book entry
                $accStmt = $pdo->prepare("SELECT account_name, account_type FROM chart_of_accounts WHERE id = ?");
                $accStmt->execute([$accountId]);
                $accRow = $accStmt->fetch();

                // 3. Get income category COA account
                $catStmt = $pdo->prepare("SELECT category_name, account_id FROM income_categories WHERE id = ?");
                $catStmt->execute([$categoryId]);
                $catRow = $catStmt->fetch();
                $incomeAccountId = $catRow ? (int)$catRow['account_id'] : null;

                // 4. Journal entry: Debit Cash/Bank, Credit Income
                $jeStmt = $pdo->prepare("
                    INSERT INTO journal_entries
                        (entry_no, date, narration, reference_type, reference_id, created_by, created_at)
                    VALUES (?,?,?,?,?,?,NOW())
                ");
                $jeNo = generateNumber('JV', 'journal_entries', 'entry_no');
                $narration = "Receipt {$receiptNo} - {$donorName} - " . ($catRow['category_name'] ?? '');
                $jeStmt->execute([$jeNo, $date, $narration, 'receipt', $newId, $createdBy]);
                $jeId = (int)$pdo->lastInsertId();

                // Debit: Cash/Bank account
                $jdStmt = $pdo->prepare("
                    INSERT INTO journal_details (journal_id, account_id, debit_amount, credit_amount, narration)
                    VALUES (?,?,?,?,?)
                ");
                $jdStmt->execute([$jeId, $accountId, $amount, 0, $narration]);

                // Credit: Income account
                if ($incomeAccountId) {
                    $jdStmt->execute([$jeId, $incomeAccountId, 0, $amount, $narration]);
                }

                // 5. Cash book entry if cash payment
                if (in_array($paymentMode, ['cash'])) {
                    $cbStmt = $pdo->prepare("
                        INSERT INTO cash_book
                            (date, particulars, receipt_amount, payment_amount, account_id,
                             reference_type, reference_id, created_by, created_at)
                        VALUES (?,?,?,?,?,?,?,?,NOW())
                    ");
                    $cbStmt->execute([$date, $narration, $amount, 0, $accountId, 'receipt', $newId, $createdBy]);
                } else {
                    // Bank book entry for non-cash
                    $bbStmt = $pdo->prepare("
                        INSERT INTO bank_book
                            (date, particulars, deposit_amount, withdrawal_amount, account_id,
                             reference_type, reference_id, cheque_no, bank_ref, created_by, created_at)
                        VALUES (?,?,?,?,?,?,?,?,?,?,NOW())
                    ");
                    $bbStmt->execute([$date, $narration, $amount, 0, $accountId,
                                      'receipt', $newId, $chequeNo ?: null, $bankRef ?: null, $createdBy]);
                }

                // 6. Update donor last donation date if donor selected
                if ($donorId > 0) {
                    $pdo->prepare("UPDATE donors SET last_donation_date = ?, total_donated = total_donated + ?, updated_at = NOW() WHERE id = ?")
                        ->execute([$date, $amount, $donorId]);
                }

                $pdo->commit();
                logAudit('create', 'receipts', $newId, [], ['receipt_no' => $receiptNo, 'amount' => $amount]);
                $newRcpNo = $receiptNo;
                $success  = true;
                setFlash('success', "Receipt {$receiptNo} saved successfully.");
            } catch (\Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// ── Data for dropdowns ─────────────────────────────────────────
$receiptNo = generateNumber('RCP', 'receipts', 'receipt_no');

$categories = db()->query("SELECT id, category_name FROM income_categories WHERE deleted_at IS NULL ORDER BY category_name")->fetchAll();
$accounts   = db()->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE account_type = 'Assets' AND deleted_at IS NULL ORDER BY account_name")->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/modules/donations/receipt_list.php">Receipts</a></li>
            <li class="breadcrumb-item active">Add Receipt</li>
        </ol>
    </nav>

    <?php if ($success): ?>
    <!-- Success card after save -->
    <div class="card border-success mb-4">
        <div class="card-body text-center py-5">
            <i class="bi bi-check-circle-fill text-success" style="font-size:3rem;"></i>
            <h4 class="mt-3 text-success">Receipt Saved Successfully!</h4>
            <p class="text-muted mb-4">Receipt No: <strong><?= htmlspecialchars($newRcpNo) ?></strong></p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="<?= BASE_PATH ?>/modules/donations/view_receipt.php?id=<?= $newId ?>&print=1"
                   class="btn btn-primary" target="_blank">
                    <i class="bi bi-printer me-1"></i> Print Receipt
                </a>
                <a href="<?= BASE_PATH ?>/modules/donations/add_receipt.php"
                   class="btn btn-success">
                    <i class="bi bi-plus-circle me-1"></i> Add Another
                </a>
                <a href="<?= BASE_PATH ?>/modules/donations/receipt_list.php"
                   class="btn btn-outline-secondary">
                    <i class="bi bi-list me-1"></i> View All Receipts
                </a>
            </div>
        </div>
    </div>
    <?php else: ?>

    <!-- Error alert -->
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
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
        <div class="card-header bg-primary text-white d-flex align-items-center">
            <i class="bi bi-receipt me-2 fs-5"></i>
            <h5 class="mb-0">Add Donation Receipt</h5>
        </div>
        <div class="card-body">
            <form method="POST" id="receiptForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">

                <div class="row g-3">
                    <!-- Receipt No -->
                    <div class="col-md-4">
                        <label for="receipt_no" class="form-label fw-semibold">Receipt No <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="receipt_no" name="receipt_no"
                                   value="<?= htmlspecialchars($_POST['receipt_no'] ?? $receiptNo) ?>" readonly>
                            <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                        </div>
                        <div class="form-text">Auto-generated</div>
                    </div>

                    <!-- Date -->
                    <div class="col-md-4">
                        <label for="date" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                        <input type="text" class="form-control datepicker" id="date" name="date"
                               value="<?= htmlspecialchars($_POST['date'] ?? date('Y-m-d')) ?>" required>
                    </div>

                    <!-- Payment Mode -->
                    <div class="col-md-4">
                        <label for="payment_mode" class="form-label fw-semibold">Payment Mode <span class="text-danger">*</span></label>
                        <select class="form-select" id="payment_mode" name="payment_mode" required>
                            <option value="">-- Select Mode --</option>
                            <?php
                            $modes = ['cash' => 'Cash', 'bank' => 'Bank Transfer', 'cheque' => 'Cheque',
                                      'online' => 'Online', 'upi' => 'UPI'];
                            $selMode = $_POST['payment_mode'] ?? '';
                            foreach ($modes as $val => $label):
                            ?>
                            <option value="<?= $val ?>" <?= $selMode === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Donor Search -->
                    <div class="col-md-6">
                        <label for="donor_id" class="form-label fw-semibold">Search Donor</label>
                        <select class="form-select select2-donor" id="donor_id" name="donor_id"
                                style="width:100%;" data-placeholder="Type to search donor...">
                            <option value=""></option>
                            <?php if (!empty($_POST['donor_id']) && $_POST['donor_id'] > 0): ?>
                            <option value="<?= (int)$_POST['donor_id'] ?>" selected>
                                <?= htmlspecialchars($_POST['donor_name'] ?? '') ?>
                            </option>
                            <?php endif; ?>
                        </select>
                        <div class="form-text">Leave blank for walk-in / anonymous donor</div>
                    </div>

                    <!-- Donor Name -->
                    <div class="col-md-6">
                        <label for="donor_name" class="form-label fw-semibold">Donor Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="donor_name" name="donor_name"
                               value="<?= htmlspecialchars($_POST['donor_name'] ?? '') ?>"
                               placeholder="Enter or auto-filled from donor" required>
                    </div>

                    <!-- Donor Phone -->
                    <div class="col-md-4">
                        <label for="donor_phone" class="form-label fw-semibold">Donor Phone</label>
                        <input type="text" class="form-control" id="donor_phone" name="donor_phone"
                               value="<?= htmlspecialchars($_POST['donor_phone'] ?? '') ?>"
                               placeholder="+91 9999999999">
                    </div>

                    <!-- Category -->
                    <div class="col-md-4">
                        <label for="category_id" class="form-label fw-semibold">Income Category <span class="text-danger">*</span></label>
                        <select class="form-select select2-basic" id="category_id" name="category_id" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"
                                <?= (($_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Account -->
                    <div class="col-md-4">
                        <label for="account_id" class="form-label fw-semibold">Receive Into Account <span class="text-danger">*</span></label>
                        <select class="form-select select2-basic" id="account_id" name="account_id" required>
                            <option value="">-- Select Account --</option>
                            <?php foreach ($accounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"
                                <?= (($_POST['account_id'] ?? '') == $acc['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($acc['account_code'] . ' - ' . $acc['account_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Cheque No (conditional) -->
                    <div class="col-md-4" id="chequeRow" style="display:none;">
                        <label for="cheque_no" class="form-label fw-semibold">Cheque No <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cheque_no" name="cheque_no"
                               value="<?= htmlspecialchars($_POST['cheque_no'] ?? '') ?>"
                               placeholder="Cheque number">
                    </div>

                    <!-- Bank Reference -->
                    <div class="col-md-4" id="bankRefRow">
                        <label for="bank_ref" class="form-label fw-semibold">Bank Reference / UTR</label>
                        <input type="text" class="form-control" id="bank_ref" name="bank_ref"
                               value="<?= htmlspecialchars($_POST['bank_ref'] ?? '') ?>"
                               placeholder="Transaction / UTR reference">
                    </div>

                    <!-- Amount -->
                    <div class="col-md-4">
                        <label for="amount" class="form-label fw-semibold">Amount (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" class="form-control" id="amount" name="amount"
                                   value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
                                   step="0.01" min="0.01" placeholder="0.00" required>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="col-12">
                        <label for="remarks" class="form-label fw-semibold">Remarks / Narration</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="2"
                                  placeholder="Optional remarks..."><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
                    </div>
                </div>

                <hr>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Save Receipt
                    </button>
                    <a href="<?= BASE_PATH ?>/modules/donations/receipt_list.php" class="btn btn-outline-secondary">
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
    // Flatpickr date picker
    flatpickr('.datepicker', { dateFormat: 'Y-m-d', allowInput: true });

    // Select2 for basic dropdowns
    $('.select2-basic').select2({ theme: 'bootstrap-5', width: '100%' });

    // Select2 AJAX donor search
    $('.select2-donor').select2({
        theme: 'bootstrap-5',
        width: '100%',
        minimumInputLength: 2,
        ajax: {
            url: BASE_PATH + '/modules/donations/search_donors.php',
            dataType: 'json',
            delay: 300,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data }; },
            cache: true
        }
    }).on('select2:select', function (e) {
        var d = e.params.data;
        $('#donor_name').val(d.donor_name || d.text);
        $('#donor_phone').val(d.donor_phone || '');
    }).on('select2:clear', function () {
        $('#donor_name').val('');
        $('#donor_phone').val('');
    });

    // Payment mode toggle
    function togglePaymentFields() {
        var mode = $('#payment_mode').val();
        if (mode === 'cheque') {
            $('#chequeRow').show();
            $('#cheque_no').prop('required', true);
        } else {
            $('#chequeRow').hide();
            $('#cheque_no').prop('required', false).val('');
        }
        if (mode === 'cash') {
            $('#bankRefRow').hide();
        } else {
            $('#bankRefRow').show();
        }
    }
    $('#payment_mode').on('change', togglePaymentFields);
    togglePaymentFields();
});
</script>
JS;
require_once __DIR__ . '/../../templates/footer.php';
