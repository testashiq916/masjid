<?php
$pageTitle = 'Payment Vouchers';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

// ── Soft-delete handler ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
    } else {
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId > 0) {
            db()->prepare("UPDATE payments SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL")
                ->execute([$delId]);
            logAudit('delete', 'payments', $delId);
            setFlash('success', 'Payment deleted successfully.');
        }
    }
    redirect(BASE_PATH . '/modules/expenses/payment_list.php');
}

// ── Filters ───────────────────────────────────────────────────
$fromDate    = sanitize($_GET['from_date']    ?? date('Y-m-01'));
$toDate      = sanitize($_GET['to_date']      ?? date('Y-m-d'));
$categoryId  = (int)($_GET['category_id']     ?? 0);
$paymentMode = sanitize($_GET['payment_mode'] ?? '');

$where  = "p.deleted_at IS NULL";
$params = [];

if ($fromDate)        { $where .= " AND p.date >= ?"; $params[] = $fromDate; }
if ($toDate)          { $where .= " AND p.date <= ?"; $params[] = $toDate; }
if ($categoryId > 0)  { $where .= " AND p.category_id = ?"; $params[] = $categoryId; }
if ($paymentMode)     { $where .= " AND p.payment_mode = ?"; $params[] = $paymentMode; }

$sql = "
    SELECT p.*, ec.category_name, coa.account_name,
           u.full_name AS approved_by_name
    FROM payments p
    LEFT JOIN expense_categories ec ON ec.id = p.category_id
    LEFT JOIN chart_of_accounts  coa ON coa.id = p.account_id
    LEFT JOIN users              u   ON u.id   = p.approved_by
    WHERE $where
    ORDER BY p.date DESC, p.id DESC
";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Grand total
$tStmt = db()->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE $where");
$tStmt->execute($params);
$grandTotal = (float)$tStmt->fetchColumn();

// Dropdown data
$categories = db()->query("SELECT id, category_name FROM expense_categories WHERE deleted_at IS NULL ORDER BY category_name")->fetchAll();

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
            <li class="breadcrumb-item active">Payment Vouchers</li>
        </ol>
    </nav>

    <!-- Flash -->
    <?php $flash = getFlash(); if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Filter Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-funnel me-1"></i> Filter Payments
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="text" class="form-control datepicker" name="from_date"
                           value="<?= htmlspecialchars($fromDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="text" class="form-control datepicker" name="to_date"
                           value="<?= htmlspecialchars($toDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category_id">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Mode</label>
                    <select class="form-select" name="payment_mode">
                        <option value="">All Modes</option>
                        <?php foreach (['cash'=>'Cash','bank'=>'Bank','cheque'=>'Cheque','online'=>'Online','upi'=>'UPI'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= $paymentMode === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>
    </div>

    <!-- List Card -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-cash-coin me-1"></i> Payment Vouchers</span>
            <a href="<?= BASE_PATH ?>/modules/expenses/add_payment.php" class="btn btn-danger btn-sm">
                <i class="bi bi-plus-circle me-1"></i> Add Payment
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="paymentsTable" class="table table-hover table-striped mb-0 align-middle" style="width:100%;">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Voucher No</th>
                            <th>Date</th>
                            <th>Payee</th>
                            <th>Category</th>
                            <th>Mode</th>
                            <th class="text-end">Amount (₹)</th>
                            <th>Approved By</th>
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
                            <td>
                                <?php
                                $mc = ['cash'=>'success','bank'=>'primary','cheque'=>'warning',
                                       'online'=>'info','upi'=>'purple'];
                                $mc2 = $mc[$p['payment_mode']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $mc2 ?> text-capitalize">
                                    <?= htmlspecialchars($p['payment_mode']) ?>
                                </span>
                            </td>
                            <td class="text-end fw-semibold text-danger">
                                <?= number_format($p['amount'], 2) ?>
                            </td>
                            <td class="text-muted small"><?= htmlspecialchars($p['approved_by_name'] ?? '-') ?></td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= BASE_PATH ?>/modules/expenses/view_payment.php?id=<?= $p['id'] ?>"
                                       class="btn btn-outline-info" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= BASE_PATH ?>/modules/expenses/add_payment.php?edit=<?= $p['id'] ?>"
                                       class="btn btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="<?= BASE_PATH ?>/modules/expenses/view_payment.php?id=<?= $p['id'] ?>&print=1"
                                       class="btn btn-outline-secondary" title="Print" target="_blank">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <button class="btn btn-outline-danger" title="Delete"
                                            onclick="confirmDelete(<?= $p['id'] ?>, '<?= htmlspecialchars($p['voucher_no'], ENT_QUOTES) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-danger fw-bold">
                            <td colspan="6" class="text-end">Grand Total:</td>
                            <td class="text-end">₹ <?= number_format($grandTotal, 2) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Hidden delete form -->
    <form id="deleteForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
        <input type="hidden" name="id" id="deleteId">
    </form>

</div><!-- content-area -->
</div><!-- main-content -->
</div><!-- wrapper -->

<?php
$extraJs = <<<'JS'
<script>
$(function () {
    flatpickr('.datepicker', { dateFormat: 'Y-m-d', allowInput: true });

    $('#paymentsTable').DataTable({
        pageLength: 25,
        order: [[2, 'desc']],
        dom: '<"d-flex justify-content-between align-items-center mb-2"lB>frtip',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="bi bi-file-earmark-excel me-1"></i> Excel',
                className: 'btn btn-sm btn-success',
                exportOptions: { columns: [0,1,2,3,4,5,6,7] }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="bi bi-file-pdf me-1"></i> PDF',
                className: 'btn btn-sm btn-danger',
                orientation: 'landscape',
                exportOptions: { columns: [0,1,2,3,4,5,6,7] }
            },
            {
                extend: 'print',
                text: '<i class="bi bi-printer me-1"></i> Print',
                className: 'btn btn-sm btn-secondary',
                exportOptions: { columns: [0,1,2,3,4,5,6,7] }
            }
        ],
        language: { emptyTable: 'No payments found for the selected filters.' }
    });
});

function confirmDelete(id, vcNo) {
    if (confirm('Delete payment voucher ' + vcNo + '?\n\nThis action cannot be undone.')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>
JS;
require_once __DIR__ . '/../../templates/footer.php';
