<?php
$pageTitle = 'View Payment';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('danger', 'Invalid request.'); redirect(BASE_PATH . '/modules/expenses/payment_list.php'); }

$stmt = db()->prepare("SELECT p.*, ec.category_name, coa.account_name, u.name as approver_name, cu.name as created_by_name
    FROM payments p
    LEFT JOIN expense_categories ec ON ec.id=p.category_id
    LEFT JOIN chart_of_accounts coa ON coa.id=p.account_id
    LEFT JOIN users u ON u.id=p.approved_by
    LEFT JOIN users cu ON cu.id=p.created_by
    WHERE p.id=? AND p.deleted_at IS NULL");
$stmt->execute([$id]);
$payment = $stmt->fetch();
if (!$payment) { setFlash('danger', 'Payment not found.'); redirect(BASE_PATH . '/modules/expenses/payment_list.php'); }

$profile = getMasjidProfile();

function numberToWords(float $num): string {
    $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
             'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
             'Seventeen','Eighteen','Nineteen'];
    $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    if ($num == 0) return 'Zero';
    $intPart  = (int)$num;
    $fracPart = round(($num - $intPart) * 100);
    $convert = function($n) use (&$convert, $ones, $tens) {
        if ($n < 20) return $ones[$n];
        if ($n < 100) return $tens[(int)($n/10)] . ($n%10 ? ' ' . $ones[$n%10] : '');
        if ($n < 1000) return $ones[(int)($n/100)] . ' Hundred' . ($n%100 ? ' ' . $convert($n%100) : '');
        if ($n < 100000) return $convert((int)($n/1000)) . ' Thousand' . ($n%1000 ? ' ' . $convert($n%1000) : '');
        if ($n < 10000000) return $convert((int)($n/100000)) . ' Lakh' . ($n%100000 ? ' ' . $convert($n%100000) : '');
        return $convert((int)($n/10000000)) . ' Crore' . ($n%10000000 ? ' ' . $convert($n%10000000) : '');
    };
    $words = $convert($intPart) . ' Rupees';
    if ($fracPart > 0) $words .= ' and ' . $convert($fracPart) . ' Paise';
    return $words . ' Only';
}

$autoPrint = isset($_GET['print']);
require_once __DIR__ . '/../../templates/header.php';
?>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

<div class="page-header no-print">
    <div><h4><i class="bi bi-receipt me-2 text-danger"></i>Payment Voucher</h4>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Home</a></li>
        <li class="breadcrumb-item"><a href="payment_list.php">Payments</a></li>
        <li class="breadcrumb-item active"><?= htmlspecialchars($payment['voucher_no']) ?></li>
    </ol></nav></div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
        <a href="payment_list.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>

<!-- Voucher -->
<div class="row justify-content-center">
    <div class="col-12 col-md-8">
        <div class="card shadow voucher-box">
            <div class="card-body p-4">
                <!-- Header -->
                <div class="text-center border-bottom pb-3 mb-3">
                    <?php if (!empty($profile['logo'])): ?>
                    <img src="<?= BASE_PATH ?>/assets/uploads/<?= htmlspecialchars($profile['logo']) ?>" height="60" class="mb-2">
                    <?php endif; ?>
                    <h4 class="fw-bold mb-0"><?= htmlspecialchars($profile['masjid_name'] ?? 'Masjid ERP') ?></h4>
                    <p class="text-muted small mb-0"><?= htmlspecialchars($profile['address'] ?? '') ?></p>
                    <p class="text-muted small mb-0"><?= htmlspecialchars($profile['phone'] ?? '') ?></p>
                    <h5 class="mt-2 fw-bold text-danger">PAYMENT VOUCHER</h5>
                </div>

                <!-- Details -->
                <div class="row mb-3">
                    <div class="col-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td class="fw-semibold">Voucher No:</td><td><?= htmlspecialchars($payment['voucher_no']) ?></td></tr>
                            <tr><td class="fw-semibold">Date:</td><td><?= formatDate($payment['date']) ?></td></tr>
                            <tr><td class="fw-semibold">Payee:</td><td><?= htmlspecialchars($payment['payee_name']) ?></td></tr>
                        </table>
                    </div>
                    <div class="col-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td class="fw-semibold">Category:</td><td><?= htmlspecialchars($payment['category_name'] ?? '-') ?></td></tr>
                            <tr><td class="fw-semibold">Account:</td><td><?= htmlspecialchars($payment['account_name'] ?? '-') ?></td></tr>
                            <tr><td class="fw-semibold">Mode:</td><td><?= strtoupper($payment['payment_mode']) ?></td></tr>
                        </table>
                    </div>
                </div>

                <?php if ($payment['cheque_no']): ?>
                <div class="mb-2"><strong>Cheque No:</strong> <?= htmlspecialchars($payment['cheque_no']) ?></div>
                <?php endif; ?>

                <div class="bg-light rounded p-3 mb-3 text-center">
                    <div class="small text-muted">Amount</div>
                    <div class="fs-3 fw-bold text-danger">₹ <?= number_format($payment['amount'], 2) ?></div>
                    <div class="small text-muted fst-italic"><?= numberToWords($payment['amount']) ?></div>
                </div>

                <?php if ($payment['remarks']): ?>
                <div class="mb-3"><strong>Narration:</strong> <?= htmlspecialchars($payment['remarks']) ?></div>
                <?php endif; ?>

                <!-- Signatures -->
                <div class="row mt-4 pt-3 border-top">
                    <div class="col-4 text-center">
                        <div class="border-top pt-2 mt-4 small">Prepared By</div>
                        <div class="small text-muted"><?= htmlspecialchars($payment['created_by_name'] ?? '') ?></div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="border-top pt-2 mt-4 small">Approved By</div>
                        <div class="small text-muted"><?= htmlspecialchars($payment['approver_name'] ?? '') ?></div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="border-top pt-2 mt-4 small">Received By</div>
                    </div>
                </div>

                <div class="text-center mt-3 text-muted" style="font-size:0.7rem;">
                    This is a computer-generated voucher. Printed on <?= date('d/m/Y H:i') ?>
                </div>
            </div>
        </div>
    </div>
</div>

</div></div></div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
<?php if ($autoPrint): ?><script>window.onload = function() { window.print(); }</script><?php endif; ?>
