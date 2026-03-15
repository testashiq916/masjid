<?php
$pageTitle = 'View Payment Voucher';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

// ── Number to Indian English words ────────────────────────────
function numberToWords(float $number): string {
    $number = abs(round($number, 2));
    $rupees = (int)$number;
    $paise  = (int)round(($number - $rupees) * 100);

    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
             'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
             'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    function convertHundreds(int $n, array $ones, array $tens): string {
        $result = '';
        if ($n >= 100) {
            $result .= $ones[(int)($n / 100)] . ' Hundred ';
            $n %= 100;
        }
        if ($n >= 20) {
            $result .= $tens[(int)($n / 10)] . ' ';
            $n %= 10;
        }
        if ($n > 0) {
            $result .= $ones[$n] . ' ';
        }
        return $result;
    }

    function convertIndian(int $n, array $ones, array $tens): string {
        if ($n === 0) return 'Zero';
        $result = '';
        if ($n >= 10000000) {
            $result .= convertHundreds((int)($n / 10000000), $ones, $tens) . 'Crore ';
            $n %= 10000000;
        }
        if ($n >= 100000) {
            $result .= convertHundreds((int)($n / 100000), $ones, $tens) . 'Lakh ';
            $n %= 100000;
        }
        if ($n >= 1000) {
            $result .= convertHundreds((int)($n / 1000), $ones, $tens) . 'Thousand ';
            $n %= 1000;
        }
        $result .= convertHundreds($n, $ones, $tens);
        return trim($result);
    }

    $words = convertIndian($rupees, $ones, $tens) . ' Rupees';
    if ($paise > 0) {
        $words .= ' and ' . convertIndian($paise, $ones, $tens) . ' Paise';
    }
    return $words . ' Only';
}

// ── Load payment ───────────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('danger', 'Invalid payment ID.');
    redirect(BASE_PATH . '/modules/expenses/payment_list.php');
}

$stmt = db()->prepare("
    SELECT p.*, ec.category_name, coa.account_name,
           u.full_name AS created_by_name,
           ua.full_name AS approved_by_name
    FROM payments p
    LEFT JOIN expense_categories ec ON ec.id = p.category_id
    LEFT JOIN chart_of_accounts coa ON coa.id = p.account_id
    LEFT JOIN users u               ON u.id   = p.created_by
    LEFT JOIN users ua              ON ua.id  = p.approved_by
    WHERE p.id = ? AND p.deleted_at IS NULL
");
$stmt->execute([$id]);
$payment = $stmt->fetch();

if (!$payment) {
    setFlash('danger', 'Payment not found or has been deleted.');
    redirect(BASE_PATH . '/modules/expenses/payment_list.php');
}

$masjid      = getMasjidProfile();
$amountWords = numberToWords((float)$payment['amount']);
$isPrint     = isset($_GET['print']);

require_once __DIR__ . '/../../templates/header.php';
?>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

    <!-- Breadcrumb (hidden on print) -->
    <nav aria-label="breadcrumb" class="mb-3 d-print-none">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/modules/expenses/payment_list.php">Payments</a></li>
            <li class="breadcrumb-item active">View Voucher</li>
        </ol>
    </nav>

    <!-- Action buttons (hidden on print) -->
    <div class="d-flex gap-2 mb-3 d-print-none">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="bi bi-printer me-1"></i> Print Voucher
        </button>
        <a href="<?= BASE_PATH ?>/modules/expenses/add_payment.php?edit=<?= $payment['id'] ?>"
           class="btn btn-outline-warning">
            <i class="bi bi-pencil me-1"></i> Edit
        </a>
        <a href="<?= BASE_PATH ?>/modules/expenses/payment_list.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>
    </div>

    <!-- Payment Voucher -->
    <div class="card shadow payment-voucher" id="paymentVoucher">
        <div class="card-body p-4 p-md-5">

            <!-- Header -->
            <div class="row align-items-center mb-4 border-bottom pb-3">
                <div class="col-auto">
                    <?php if (!empty($masjid['logo'])): ?>
                    <img src="<?= BASE_PATH ?>/assets/images/<?= htmlspecialchars($masjid['logo']) ?>"
                         alt="Logo" style="height:70px;">
                    <?php else: ?>
                    <div class="bg-danger text-white rounded d-flex align-items-center justify-content-center"
                         style="width:70px;height:70px;font-size:1.8rem;">
                        <i class="bi bi-building"></i>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col text-center">
                    <h3 class="mb-0 fw-bold text-danger">
                        <?= htmlspecialchars($masjid['masjid_name'] ?? 'Masjid ERP') ?>
                    </h3>
                    <?php if (!empty($masjid['address'])): ?>
                    <p class="text-muted mb-0 small"><?= htmlspecialchars($masjid['address']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($masjid['phone'])): ?>
                    <p class="text-muted mb-0 small">Tel: <?= htmlspecialchars($masjid['phone']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-auto text-end">
                    <div class="bg-danger text-white px-3 py-2 rounded text-center">
                        <div class="small fw-semibold">VOUCHER</div>
                        <div class="fs-6 fw-bold"><?= htmlspecialchars($payment['voucher_no']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Ribbon -->
            <div class="bg-light border rounded p-2 mb-4 text-center">
                <span class="text-uppercase fw-bold text-secondary" style="letter-spacing:2px;">
                    Payment Voucher
                </span>
            </div>

            <!-- Details Grid -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <td class="text-muted fw-semibold" style="width:40%">Voucher No</td>
                            <td class="fw-bold">: <?= htmlspecialchars($payment['voucher_no']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold">Date</td>
                            <td class="fw-bold">: <?= formatDate($payment['date'], 'd/m/Y') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold">Payee Name</td>
                            <td class="fw-bold">: <?= htmlspecialchars($payment['payee_name']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold">Category</td>
                            <td>: <?= htmlspecialchars($payment['category_name'] ?? '-') ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <td class="text-muted fw-semibold" style="width:40%">Paid From</td>
                            <td>: <?= htmlspecialchars($payment['account_name'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold">Payment Mode</td>
                            <td class="text-capitalize">: <?= htmlspecialchars($payment['payment_mode']) ?></td>
                        </tr>
                        <?php if ($payment['cheque_no']): ?>
                        <tr>
                            <td class="text-muted fw-semibold">Cheque No</td>
                            <td>: <?= htmlspecialchars($payment['cheque_no']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($payment['bank_ref']): ?>
                        <tr>
                            <td class="text-muted fw-semibold">Bank Ref / UTR</td>
                            <td>: <?= htmlspecialchars($payment['bank_ref']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="text-muted fw-semibold">Approved By</td>
                            <td>: <?= htmlspecialchars($payment['approved_by_name'] ?? '-') ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Amount Box -->
            <div class="border border-2 border-danger rounded p-3 mb-4">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <div class="text-muted small fw-semibold mb-1">Amount in Words</div>
                        <div class="fw-bold text-dark fs-6">
                            <?= htmlspecialchars($amountWords) ?>
                        </div>
                    </div>
                    <div class="col-md-5 text-md-end mt-2 mt-md-0">
                        <div class="text-muted small fw-semibold mb-1">Amount Paid</div>
                        <div class="display-6 fw-bold text-danger">
                            ₹ <?= number_format((float)$payment['amount'], 2) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Narration -->
            <?php if (!empty($payment['remarks'])): ?>
            <div class="mb-4">
                <span class="fw-semibold text-muted">Narration:</span>
                <span><?= htmlspecialchars($payment['remarks']) ?></span>
            </div>
            <?php endif; ?>

            <!-- Signatures -->
            <div class="row mt-5 pt-4 border-top">
                <div class="col-md-4 text-center">
                    <div style="border-top:1px solid #333;display:inline-block;width:160px;padding-top:4px;">
                        Prepared By
                    </div>
                    <div class="small text-muted mt-1">
                        <?= htmlspecialchars($payment['created_by_name'] ?? 'System') ?>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div style="border-top:1px solid #333;display:inline-block;width:160px;padding-top:4px;">
                        Approved By
                    </div>
                    <div class="small text-muted mt-1">
                        <?= htmlspecialchars($payment['approved_by_name'] ?? '-') ?>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div style="border-top:1px solid #333;display:inline-block;width:160px;padding-top:4px;">
                        Receiver's Signature
                    </div>
                </div>
            </div>

            <div class="text-center mt-4 text-muted small">
                <em>This is a computer-generated voucher. No physical signature required unless signed above.</em>
            </div>

        </div>
    </div>

</div><!-- content-area -->
</div><!-- main-content -->
</div><!-- wrapper -->

<?php
$extraJs = <<<'JS'
<script>
$(function () {
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('print') === '1') {
        setTimeout(function () { window.print(); }, 500);
    }
});
</script>
<style>
@media print {
    .d-print-none  { display: none !important; }
    .main-content  { margin: 0 !important; }
    .content-area  { margin-top: 0 !important; padding: 0 !important; }
    .wrapper       { display: block !important; }
    .payment-voucher { box-shadow: none !important; border: none !important; }
    body { font-size: 13px; }
}
</style>
JS;
require_once __DIR__ . '/../../templates/footer.php';
