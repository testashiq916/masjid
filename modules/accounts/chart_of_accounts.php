<?php
$pageTitle = 'Chart of Accounts';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/modules/accounts/chart_of_accounts.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id      = (int)($_POST['id'] ?? 0);
        $code    = sanitize($_POST['account_code'] ?? '');
        $name    = sanitize($_POST['account_name'] ?? '');
        $groupId = (int)($_POST['group_id'] ?? 0);
        $type    = sanitize($_POST['account_type'] ?? '');
        $opening = (float)($_POST['opening_balance'] ?? 0);
        $desc    = sanitize($_POST['description'] ?? '');

        if (empty($code) || empty($name) || empty($type)) {
            setFlash('danger', 'Account code, name and type are required.');
            redirect(BASE_PATH . '/modules/accounts/chart_of_accounts.php');
        }

        if ($id) {
            $old = $db->prepare("SELECT * FROM chart_of_accounts WHERE id=?");
            $old->execute([$id]);
            $oldData = $old->fetch(PDO::FETCH_ASSOC) ?: [];
            $db->prepare("UPDATE chart_of_accounts SET account_code=?, account_name=?, group_id=?, account_type=?, opening_balance=?, description=?, updated_at=NOW() WHERE id=? AND is_system=0")
               ->execute([$code, $name, $groupId ?: null, $type, $opening, $desc, $id]);
            logAudit('UPDATE', 'chart_of_accounts', $id, $oldData, ['account_code' => $code, 'account_name' => $name]);
            setFlash('success', 'Account updated successfully.');
        } else {
            // Check duplicate code
            $chk = $db->prepare("SELECT id FROM chart_of_accounts WHERE account_code=? AND deleted_at IS NULL");
            $chk->execute([$code]);
            if ($chk->fetch()) {
                setFlash('danger', 'Account code already exists.');
                redirect(BASE_PATH . '/modules/accounts/chart_of_accounts.php');
            }
            $db->prepare("INSERT INTO chart_of_accounts (account_code, account_name, group_id, account_type, opening_balance, description, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, NOW())")
               ->execute([$code, $name, $groupId ?: null, $type, $opening, $desc]);
            $newId = (int)$db->lastInsertId();
            logAudit('CREATE', 'chart_of_accounts', $newId, [], ['account_code' => $code, 'account_name' => $name]);
            setFlash('success', 'Account created successfully.');
        }
        redirect(BASE_PATH . '/modules/accounts/chart_of_accounts.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['del_id'] ?? 0);
        $db->prepare("UPDATE chart_of_accounts SET deleted_at=NOW() WHERE id=? AND is_system=0")->execute([$id]);
        logAudit('DELETE', 'chart_of_accounts', $id, [], []);
        setFlash('success', 'Account deactivated.');
        redirect(BASE_PATH . '/modules/accounts/chart_of_accounts.php');
    }
}

$accounts = $db->query("
    SELECT c.*, g.group_name
    FROM chart_of_accounts c
    LEFT JOIN account_groups g ON g.id = c.group_id
    WHERE c.deleted_at IS NULL AND c.status = 'active'
    ORDER BY c.account_type, c.account_code
")->fetchAll(PDO::FETCH_ASSOC);

$groups = $db->query("SELECT * FROM account_groups ORDER BY account_type, group_name")->fetchAll(PDO::FETCH_ASSOC);

$types     = ['Assets', 'Liabilities', 'Income', 'Expense', 'Capital'];
$typeColors = [
    'Assets'      => 'success',
    'Liabilities' => 'danger',
    'Income'      => 'primary',
    'Expense'     => 'warning',
    'Capital'     => 'info',
];
$typeIcons = [
    'Assets'      => 'bi-safe',
    'Liabilities' => 'bi-arrow-left-circle',
    'Income'      => 'bi-arrow-down-circle',
    'Expense'     => 'bi-arrow-up-circle',
    'Capital'     => 'bi-bank',
];

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
            <li class="breadcrumb-item">Accounts</li>
            <li class="breadcrumb-item active">Chart of Accounts</li>
        </ol>
    </nav>

    <!-- Flash Messages -->
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><i class="bi bi-diagram-3 me-2 text-primary"></i>Chart of Accounts</h4>
            <small class="text-muted">Manage all ledger accounts grouped by type</small>
        </div>
        <?php if (isAdmin() || hasPermission('accounts', 'add')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#coaModal" onclick="resetCoaForm()">
            <i class="bi bi-plus-circle me-1"></i> Add Account
        </button>
        <?php endif; ?>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <?php foreach ($types as $type):
            $count = count(array_filter($accounts, fn($a) => $a['account_type'] === $type));
        ?>
        <div class="col-6 col-md">
            <div class="card border-<?= $typeColors[$type] ?>">
                <div class="card-body py-2 text-center">
                    <i class="bi <?= $typeIcons[$type] ?> fs-4 text-<?= $typeColors[$type] ?>"></i>
                    <div class="fw-bold fs-5"><?= $count ?></div>
                    <div class="text-muted small"><?= $type ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Accounts by Type -->
    <?php foreach ($types as $type):
        $typeAccounts = array_filter($accounts, fn($a) => $a['account_type'] === $type);
        if (empty($typeAccounts)) continue;
    ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-<?= $typeColors[$type] ?> <?= in_array($type, ['Expense','Income']) ? 'text-dark' : 'text-white' ?> d-flex justify-content-between align-items-center">
            <span><i class="bi <?= $typeIcons[$type] ?> me-2"></i><?= $type ?> Accounts</span>
            <span class="badge bg-white text-dark"><?= count($typeAccounts) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Account Name</th>
                            <th>Group</th>
                            <th class="text-end">Opening Balance</th>
                            <th class="text-end">Current Balance</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($typeAccounts as $a): ?>
                    <?php $balance = getAccountBalance((int)$a['id']); ?>
                    <tr>
                        <td><code class="fw-semibold"><?= htmlspecialchars($a['account_code']) ?></code></td>
                        <td class="fw-semibold"><?= htmlspecialchars($a['account_name']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($a['group_name'] ?? '-') ?></span></td>
                        <td class="text-end"><?= formatCurrency((float)$a['opening_balance']) ?></td>
                        <td class="text-end fw-semibold <?= $balance < 0 ? 'text-danger' : 'text-success' ?>">
                            <?= formatCurrency(abs($balance)) ?>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars(mb_strimwidth($a['description'] ?? '', 0, 50, '...')) ?></td>
                        <td>
                            <?php if (isAdmin() || hasPermission('accounts', 'edit')): ?>
                            <button class="btn btn-sm btn-outline-primary me-1 btn-edit-coa"
                                    data-id="<?= $a['id'] ?>"
                                    data-code="<?= htmlspecialchars($a['account_code']) ?>"
                                    data-name="<?= htmlspecialchars($a['account_name']) ?>"
                                    data-group="<?= $a['group_id'] ?>"
                                    data-type="<?= $a['account_type'] ?>"
                                    data-opening="<?= $a['opening_balance'] ?>"
                                    data-desc="<?= htmlspecialchars($a['description'] ?? '') ?>"
                                    data-bs-toggle="modal" data-bs-target="#coaModal"
                                    title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ((isAdmin() || hasPermission('accounts', 'delete')) && !$a['is_system']): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Deactivate this account?');">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="del_id" value="<?= $a['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Deactivate">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php elseif ($a['is_system']): ?>
                            <span class="badge bg-light text-muted border">System</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($accounts)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>No accounts found.
        <a href="#" data-bs-toggle="modal" data-bs-target="#coaModal" onclick="resetCoaForm()">Add your first account.</a>
    </div>
    <?php endif; ?>

</div>
</div>
</div>

<!-- Add/Edit COA Modal -->
<div class="modal fade" id="coaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="coaForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="coaId">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="coaModalTitle">
                        <i class="bi bi-diagram-3 me-2"></i>Add Account
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Account Code <span class="text-danger">*</span></label>
                            <input type="text" name="account_code" id="coaCode" class="form-control" required placeholder="e.g. 1001">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Account Name <span class="text-danger">*</span></label>
                            <input type="text" name="account_name" id="coaName" class="form-control" required placeholder="e.g. Cash in Hand">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Account Type <span class="text-danger">*</span></label>
                            <select name="account_type" id="coaType" class="form-select" required>
                                <option value="">-- Select Type --</option>
                                <?php foreach ($types as $t): ?>
                                <option value="<?= $t ?>"><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Group</label>
                            <select name="group_id" id="coaGroup" class="form-select">
                                <option value="">-- Select Group (optional) --</option>
                                <?php foreach ($groups as $g): ?>
                                <option value="<?= $g['id'] ?>" data-type="<?= htmlspecialchars($g['account_type'] ?? $g['group_type'] ?? '') ?>">
                                    <?= htmlspecialchars($g['group_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Opening Balance</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= htmlspecialchars(getSetting('currency_symbol', '₹')) ?></span>
                                <input type="number" name="opening_balance" id="coaOpening" class="form-control" step="0.01" min="0" value="0">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" id="coaDesc" class="form-control" rows="2" placeholder="Optional description"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJs = <<<JS
<script>
function resetCoaForm() {
    document.getElementById('coaModalTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Add Account';
    ['coaId','coaCode','coaName','coaDesc'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('coaOpening').value = '0';
    document.getElementById('coaGroup').value = '';
    document.getElementById('coaType').value = '';
}

document.querySelectorAll('.btn-edit-coa').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('coaModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit Account';
        document.getElementById('coaId').value      = this.dataset.id;
        document.getElementById('coaCode').value    = this.dataset.code;
        document.getElementById('coaName').value    = this.dataset.name;
        document.getElementById('coaType').value    = this.dataset.type;
        document.getElementById('coaGroup').value   = this.dataset.group || '';
        document.getElementById('coaOpening').value = this.dataset.opening;
        document.getElementById('coaDesc').value    = this.dataset.desc;
    });
});

document.getElementById('coaGroup').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const type = opt.dataset.type;
    if (type && document.getElementById('coaType').value === '') {
        document.getElementById('coaType').value = type;
    }
});
</script>
JS;
require_once __DIR__ . '/../../templates/footer.php';
?>
