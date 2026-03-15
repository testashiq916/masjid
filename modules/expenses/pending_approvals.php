<?php
$pageTitle = 'Pending Approvals';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();
// Committee, Admin, Accountant can view/approve
requireRole([ROLE_ADMIN, ROLE_ACCOUNTANT, ROLE_COMMITTEE]);

// Handle approve/reject via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/modules/expenses/pending_approvals.php');
    }

    $action    = $_POST['action'] ?? '';
    $paymentId = (int)($_POST['payment_id'] ?? 0);
    $comment   = sanitize($_POST['comment'] ?? '');

    if (in_array($action, ['approve', 'reject']) && $paymentId > 0) {
        $newStatus = ($action === 'approve') ? 'approved' : 'rejected';

        db()->prepare("
            UPDATE payments
            SET approval_status  = ?,
                approval_comment = ?,
                approval_date    = NOW(),
                approval_by      = ?,
                updated_at       = NOW()
            WHERE id = ? AND deleted_at IS NULL
        ")->execute([$newStatus, $comment, currentUserId(), $paymentId]);

        // Also update payment_approvals table
        db()->prepare("
            UPDATE payment_approvals
            SET status = ?, comment = ?, reviewed_by = ?, reviewed_at = NOW()
            WHERE payment_id = ? AND status = 'pending'
        ")->execute([$newStatus, $comment, currentUserId(), $paymentId]);

        logAudit(strtoupper($action) . '_PAYMENT', 'payments', $paymentId);

        // Send SMS notification to the requester
        $payment = db()->prepare("SELECT p.*, u.full_name AS creator_name, us.phone AS creator_phone
            FROM payments p LEFT JOIN users u ON u.id = p.created_by
            LEFT JOIN users us ON us.id = p.created_by
            WHERE p.id = ?")->execute([$paymentId]);

        setFlash('success', "Payment voucher has been {$newStatus} successfully.");
    }

    redirect(BASE_PATH . '/modules/expenses/pending_approvals.php');
}

// Load pending/recent approvals
$statusFilter = sanitize($_GET['status'] ?? 'pending_approval');
$validStatuses = ['pending_approval', 'approved', 'rejected', 'all'];
if (!in_array($statusFilter, $validStatuses)) $statusFilter = 'pending_approval';

$where = $statusFilter === 'all' ? "p.deleted_at IS NULL AND p.approval_status != 'draft'"
       : "p.deleted_at IS NULL AND p.approval_status = ?";

$stmt = db()->prepare("
    SELECT p.*,
           ec.category_name,
           coa.account_name,
           uc.full_name AS created_by_name,
           ua.full_name AS approved_by_name,
           ur.full_name AS reviewer_name
    FROM payments p
    LEFT JOIN expense_categories ec ON ec.id = p.category_id
    LEFT JOIN chart_of_accounts  coa ON coa.id = p.account_id
    LEFT JOIN users uc ON uc.id = p.created_by
    LEFT JOIN users ua ON ua.id = p.approved_by
    LEFT JOIN users ur ON ur.id = p.approval_by
    WHERE $where
    ORDER BY p.created_at DESC
");
if ($statusFilter === 'all') {
    $stmt->execute();
} else {
    $stmt->execute([$statusFilter]);
}
$payments = $stmt->fetchAll();

// Count pending
$pendingCount = (int)db()->query(
    "SELECT COUNT(*) FROM payments WHERE approval_status='pending_approval' AND deleted_at IS NULL"
)->fetchColumn();

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
        <li class="breadcrumb-item">Expenses</li>
        <li class="breadcrumb-item active">Payment Approvals</li>
    </ol>
</nav>

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">
            <i class="bi bi-shield-check me-2 text-warning"></i>Payment Approvals
            <?php if ($pendingCount > 0): ?>
            <span class="badge bg-danger"><?= $pendingCount ?></span>
            <?php endif; ?>
        </h4>
        <small class="text-muted">Review and approve/reject payment vouchers</small>
    </div>
</div>

<!-- Status Filter Tabs -->
<ul class="nav nav-tabs mb-4">
    <?php foreach ([
        'pending_approval' => ['Pending', 'warning'],
        'approved'         => ['Approved', 'success'],
        'rejected'         => ['Rejected', 'danger'],
        'all'              => ['All', 'secondary'],
    ] as $s => [$label, $color]): ?>
    <li class="nav-item">
        <a class="nav-link <?= $statusFilter === $s ? 'active' : '' ?>"
           href="?status=<?= $s ?>">
            <?= $label ?>
            <?php if ($s === 'pending_approval' && $pendingCount > 0): ?>
            <span class="badge bg-danger ms-1"><?= $pendingCount ?></span>
            <?php endif; ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="approvalsTable" class="table table-hover table-striped align-middle mb-0" style="width:100%;">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Voucher No</th>
                        <th>Date</th>
                        <th>Payee</th>
                        <th>Category</th>
                        <th class="text-end">Amount (₹)</th>
                        <th>Requested By</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $i => $p): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><span class="badge bg-danger"><?= htmlspecialchars($p['voucher_no']) ?></span></td>
                        <td><?= formatDate($p['date']) ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($p['payee_name']) ?></td>
                        <td><?= htmlspecialchars($p['category_name'] ?? '-') ?></td>
                        <td class="text-end fw-semibold text-danger">
                            <?= number_format((float)$p['amount'], 2) ?>
                        </td>
                        <td class="small text-muted"><?= htmlspecialchars($p['created_by_name'] ?? '-') ?></td>
                        <td>
                            <?php
                            $statusMap = [
                                'pending_approval' => ['warning', 'bi-hourglass-split', 'Pending'],
                                'approved'         => ['success', 'bi-check-circle',    'Approved'],
                                'rejected'         => ['danger',  'bi-x-circle',        'Rejected'],
                                'draft'            => ['secondary','bi-pencil',          'Draft'],
                            ];
                            $sm = $statusMap[$p['approval_status']] ?? ['secondary', 'bi-question', ucfirst($p['approval_status'])];
                            ?>
                            <span class="badge bg-<?= $sm[0] ?>">
                                <i class="bi <?= $sm[1] ?> me-1"></i><?= $sm[2] ?>
                            </span>
                            <?php if (!empty($p['reviewer_name'])): ?>
                            <div class="small text-muted mt-1"><?= htmlspecialchars($p['reviewer_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="<?= BASE_PATH ?>/modules/expenses/view_payment.php?id=<?= $p['id'] ?>"
                                   class="btn btn-outline-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if ($p['approval_status'] === 'pending_approval'): ?>
                                <button class="btn btn-outline-success" title="Approve"
                                        onclick="reviewPayment(<?= $p['id'] ?>, 'approve', '<?= htmlspecialchars($p['voucher_no'], ENT_QUOTES) ?>', <?= $p['amount'] ?>)">
                                    <i class="bi bi-check-circle"></i>
                                </button>
                                <button class="btn btn-outline-danger" title="Reject"
                                        onclick="reviewPayment(<?= $p['id'] ?>, 'reject', '<?= htmlspecialchars($p['voucher_no'], ENT_QUOTES) ?>', <?= $p['amount'] ?>)">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php if (!empty($p['approval_comment']) && $p['approval_status'] !== 'pending_approval'): ?>
                    <tr class="table-light">
                        <td colspan="9" class="small text-muted ps-5">
                            <i class="bi bi-chat-left-text me-1"></i>
                            <strong>Comment:</strong> <?= htmlspecialchars($p['approval_comment']) ?>
                            <?php if ($p['approval_date']): ?>
                            — <em><?= formatDate($p['approval_date'], 'd/m/Y H:i') ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="reviewModalHeader">
                <h5 class="modal-title" id="reviewModalTitle">Review Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="reviewPaymentInfo" class="alert alert-info mb-3"></div>
                <form id="reviewForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                    <input type="hidden" name="action" id="reviewAction" value="">
                    <input type="hidden" name="payment_id" id="reviewPaymentId" value="">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Comments (Optional)</label>
                        <textarea class="form-control" name="comment" id="reviewComment" rows="3"
                                  placeholder="Add your review comments here..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn" id="confirmReviewBtn" onclick="submitReview()">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form -->
<form id="actionForm" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
    <input type="hidden" name="action" id="actionAction">
    <input type="hidden" name="payment_id" id="actionPaymentId">
    <input type="hidden" name="comment" id="actionComment">
</form>

</div></div></div>
<?php
$extraJs = <<<'JS'
<script>
$(function () {
    $('#approvalsTable').DataTable({
        pageLength: 25,
        order: [[2, 'desc']],
        language: { emptyTable: 'No payments found.' }
    });
});

function reviewPayment(id, action, voucherNo, amount) {
    document.getElementById('reviewAction').value = action;
    document.getElementById('reviewPaymentId').value = id;
    document.getElementById('reviewComment').value = '';

    var isApprove = action === 'approve';
    document.getElementById('reviewModalHeader').className =
        'modal-header ' + (isApprove ? 'bg-success text-white' : 'bg-danger text-white');
    document.getElementById('reviewModalTitle').textContent =
        (isApprove ? 'Approve' : 'Reject') + ' Payment';
    document.getElementById('reviewPaymentInfo').innerHTML =
        '<strong>Voucher:</strong> ' + voucherNo + ' &nbsp;|&nbsp; <strong>Amount:</strong> ₹' +
        parseFloat(amount).toLocaleString('en-IN', {minimumFractionDigits: 2});

    var btn = document.getElementById('confirmReviewBtn');
    btn.className = 'btn ' + (isApprove ? 'btn-success' : 'btn-danger');
    btn.textContent = isApprove ? 'Approve Payment' : 'Reject Payment';

    new bootstrap.Modal(document.getElementById('reviewModal')).show();
}

function submitReview() {
    document.getElementById('actionAction').value = document.getElementById('reviewAction').value;
    document.getElementById('actionPaymentId').value = document.getElementById('reviewPaymentId').value;
    document.getElementById('actionComment').value = document.getElementById('reviewComment').value;
    document.getElementById('actionForm').submit();
}
</script>
JS;
require_once __DIR__ . '/../../templates/footer.php';
