<?php
$pageTitle = 'Funds Management';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

$pdo = db();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/modules/funds/funds.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $fund_name   = sanitize($_POST['fund_name'] ?? '');
        $fund_type   = sanitize($_POST['fund_type'] ?? 'general');
        $account_id  = (int)($_POST['account_id'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');
        $status      = sanitize($_POST['status'] ?? 'active');

        if (empty($fund_name)) {
            setFlash('danger', 'Fund name is required.');
            redirect(BASE_PATH . '/modules/funds/funds.php');
        }

        if ($id > 0) {
            $pdo->prepare("UPDATE funds SET fund_name=?, fund_type=?, account_id=?, description=?, status=?, updated_at=NOW() WHERE id=? AND deleted_at IS NULL")
                ->execute([$fund_name, $fund_type, $account_id ?: null, $description, $status, $id]);
            setFlash('success', 'Fund updated successfully.');
        } else {
            $pdo->prepare("INSERT INTO funds (fund_name, fund_type, account_id, description, status, created_at, updated_at) VALUES (?,?,?,?,?,NOW(),NOW())")
                ->execute([$fund_name, $fund_type, $account_id ?: null, $description, $status]);
            setFlash('success', 'Fund created successfully.');
        }
        redirect(BASE_PATH . '/modules/funds/funds.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE funds SET deleted_at=NOW() WHERE id=?")->execute([$id]);
            setFlash('success', 'Fund deleted successfully.');
        }
        redirect(BASE_PATH . '/modules/funds/funds.php');
    }
}

// Fetch all funds
$stmt = $pdo->query("SELECT f.*, coa.account_name, coa.account_code FROM funds f LEFT JOIN chart_of_accounts coa ON coa.id = f.account_id WHERE f.deleted_at IS NULL ORDER BY f.fund_name");
$funds = $stmt->fetchAll();

// Calculate balance for each fund
$fundsWithBalance = [];
foreach ($funds as $fund) {
    $balance = 0;
    if ($fund['account_id']) {
        // Income for this fund's account
        $incStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM receipts WHERE account_id=? AND deleted_at IS NULL");
        $incStmt->execute([$fund['account_id']]);
        $income = (float)$incStmt->fetchColumn();

        // Expense from this fund's account
        $expStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE account_id=? AND deleted_at IS NULL");
        $expStmt->execute([$fund['account_id']]);
        $expense = (float)$expStmt->fetchColumn();

        $balance = $income - $expense;
    }
    $fund['balance'] = $balance;
    $fundsWithBalance[] = $fund;
}

// Totals
$totalBalance = array_sum(array_column($fundsWithBalance, 'balance'));
$totalFunds   = count($funds);
$activeFunds  = count(array_filter($funds, fn($f) => $f['status'] === 'active'));

// Fetch accounts for dropdown
$accounts = $pdo->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE deleted_at IS NULL ORDER BY account_code")->fetchAll();

$fundTypeColors = [
    'general'    => 'primary',
    'restricted' => 'danger',
    'designated' => 'warning',
];

require_once __DIR__ . '/../../templates/header.php';
?>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><i class="bi bi-wallet2 me-2 text-primary"></i>Funds Management</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Home</a></li>
            <li class="breadcrumb-item active">Funds</li>
        </ol></nav>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#fundModal" onclick="resetForm()">
        <i class="bi bi-plus-circle me-1"></i>Create Fund
    </button>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-primary"><?= $totalFunds ?></div>
                <div class="small text-muted">Total Funds</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-success"><?= $activeFunds ?></div>
                <div class="small text-muted">Active Funds</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-6">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold <?= $totalBalance >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatCurrency($totalBalance) ?></div>
                <div class="small text-muted">Total Fund Balance</div>
            </div>
        </div>
    </div>
</div>

<!-- Fund Type Legend -->
<div class="d-flex gap-3 mb-3 small">
    <span><span class="badge bg-primary">General</span> Unrestricted funds</span>
    <span><span class="badge bg-danger">Restricted</span> Cannot be mixed - specific use only</span>
    <span><span class="badge bg-warning text-dark">Designated</span> Board-designated purpose</span>
</div>

<!-- Funds Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>All Funds</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="fundsTable" class="table table-hover align-middle small">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Fund Name</th>
                        <th>Type</th>
                        <th>Linked Account</th>
                        <th>Balance</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($fundsWithBalance as $i => $f): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($f['fund_name']) ?></div>
                        <?php if ($f['fund_type'] === 'restricted'): ?>
                        <small class="text-danger"><i class="bi bi-lock-fill"></i> Restricted Fund</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-<?= $fundTypeColors[$f['fund_type']] ?? 'secondary' ?>">
                            <?= ucfirst($f['fund_type']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($f['account_name']): ?>
                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($f['account_code']) ?></span>
                        <?= htmlspecialchars($f['account_name']) ?>
                        <?php else: ?><span class="text-muted">No account linked</span><?php endif; ?>
                    </td>
                    <td>
                        <span class="fw-bold <?= $f['balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= formatCurrency($f['balance']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($f['description'] ?: '-') ?></td>
                    <td>
                        <?php $sb = $f['status'] === 'active' ? 'success' : 'secondary'; ?>
                        <span class="badge bg-<?= $sb ?>"><?= ucfirst($f['status']) ?></span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="editFund(<?= htmlspecialchars(json_encode($f)) ?>)" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this fund?')">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $f['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Restricted Funds Notice -->
<div class="alert alert-warning mt-3 small">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Important:</strong> Restricted funds must be used only for their designated purpose. They cannot be mixed with General or other funds. Ensure all transactions linked to restricted fund accounts are compliant.
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="fundModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="fundForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="fund_id" value="0">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="fundModalTitle"><i class="bi bi-wallet2 me-2"></i>Create Fund</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Fund Name <span class="text-danger">*</span></label>
                            <input type="text" name="fund_name" id="fund_name" class="form-control" required placeholder="e.g. Zakat Fund, Building Fund">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Fund Type</label>
                            <select name="fund_type" id="fund_type" class="form-select" onchange="showFundTypeHint(this.value)">
                                <option value="general">General (Unrestricted)</option>
                                <option value="restricted">Restricted</option>
                                <option value="designated">Designated</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" id="fund_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div id="restricted_notice" class="col-12 d-none">
                            <div class="alert alert-danger small mb-0">
                                <i class="bi bi-lock-fill me-1"></i>
                                <strong>Restricted Fund:</strong> This fund cannot be mixed with other funds. All transactions must be strictly for the designated purpose.
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Link to Account (Chart of Accounts)</label>
                            <select name="account_id" id="fund_account" class="form-select">
                                <option value="">-- No Account Linked --</option>
                                <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>">[<?= htmlspecialchars($acc['account_code']) ?>] <?= htmlspecialchars($acc['account_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Link an account to track income and expenses for this fund.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" id="fund_desc" class="form-control" rows="3" placeholder="Describe the purpose of this fund..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Fund</button>
                </div>
            </div>
        </form>
    </div>
</div>

</div></div></div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
<script>
$('#fundsTable').DataTable({
    order: [[1, 'asc']],
    pageLength: 25,
    language: { search: 'Search funds:' }
});

function resetForm() {
    document.getElementById('fundModalTitle').innerHTML = '<i class="bi bi-wallet2 me-2"></i>Create Fund';
    document.getElementById('fund_id').value      = '0';
    document.getElementById('fund_name').value    = '';
    document.getElementById('fund_type').value    = 'general';
    document.getElementById('fund_status').value  = 'active';
    document.getElementById('fund_account').value = '';
    document.getElementById('fund_desc').value    = '';
    document.getElementById('restricted_notice').classList.add('d-none');
}

function showFundTypeHint(val) {
    var notice = document.getElementById('restricted_notice');
    if (val === 'restricted') {
        notice.classList.remove('d-none');
    } else {
        notice.classList.add('d-none');
    }
}

function editFund(f) {
    document.getElementById('fundModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Fund';
    document.getElementById('fund_id').value      = f.id;
    document.getElementById('fund_name').value    = f.fund_name || '';
    document.getElementById('fund_type').value    = f.fund_type || 'general';
    document.getElementById('fund_status').value  = f.status || 'active';
    document.getElementById('fund_account').value = f.account_id || '';
    document.getElementById('fund_desc').value    = f.description || '';
    showFundTypeHint(f.fund_type || 'general');
    var modal = new bootstrap.Modal(document.getElementById('fundModal'));
    modal.show();
}
</script>
