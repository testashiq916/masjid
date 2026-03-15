<?php
$pageTitle = 'Expense Categories';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $name      = sanitize($_POST['category_name'] ?? '');
        $accountId = (int)($_POST['account_id'] ?? 0) ?: null;
        $desc      = sanitize($_POST['description'] ?? '');
        if ($id) {
            db()->prepare("UPDATE expense_categories SET category_name=?,account_id=?,description=? WHERE id=?")->execute([$name, $accountId, $desc, $id]);
            setFlash('success', 'Category updated.');
        } else {
            db()->prepare("INSERT INTO expense_categories (category_name,account_id,description) VALUES (?,?,?)")->execute([$name, $accountId, $desc]);
            setFlash('success', 'Category created.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['del_id'] ?? 0);
        db()->prepare("UPDATE expense_categories SET status='inactive' WHERE id=?")->execute([$id]);
        setFlash('success', 'Category deactivated.');
    }
    redirect(BASE_PATH . '/modules/expenses/expense_categories.php');
}

$categories = db()->query("SELECT ec.*, coa.account_name FROM expense_categories ec LEFT JOIN chart_of_accounts coa ON coa.id=ec.account_id WHERE ec.status='active' ORDER BY ec.category_name")->fetchAll();
$accounts   = db()->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE account_type='Expense' AND status='active' ORDER BY account_code")->fetchAll();

require_once __DIR__ . '/../../templates/header.php';
?>
<div class="wrapper d-flex">
<?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php require_once __DIR__ . '/../../templates/navbar.php'; ?>
<div class="content-area p-3 p-md-4" style="margin-top:56px;">

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show"><?= htmlspecialchars($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="page-header">
    <div><h4><i class="bi bi-tags me-2 text-primary"></i>Expense Categories</h4>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Home</a></li>
        <li class="breadcrumb-item active">Expense Categories</li>
    </ol></nav></div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#catModal" onclick="resetCatForm()">
        <i class="bi bi-plus-circle me-1"></i>Add Category
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover datatable mb-0">
            <thead><tr><th>#</th><th>Category Name</th><th>Linked Account</th><th>Description</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($categories as $i => $c): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($c['category_name']) ?></strong></td>
                    <td class="small text-muted"><?= htmlspecialchars($c['account_name'] ?? '-') ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($c['description'] ?? '-') ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary btn-edit-cat"
                            data-id="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['category_name']) ?>"
                            data-account="<?= $c['account_id'] ?? '' ?>" data-desc="<?= htmlspecialchars($c['description'] ?? '') ?>"
                            data-bs-toggle="modal" data-bs-target="#catModal"><i class="bi bi-pencil"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Deactivate?')">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="del_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="catId">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-tags me-2"></i>Expense Category</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="category_name" id="catName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Linked Account (COA)</label>
                        <select name="account_id" id="catAccount" class="form-select select2">
                            <option value="">-- None --</option>
                            <?php foreach ($accounts as $a): ?>
                            <option value="<?= $a['id'] ?>">[<?= $a['account_code'] ?>] <?= htmlspecialchars($a['account_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" id="catDesc" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-save me-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

</div></div></div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
<script>
function resetCatForm() {
    ['catId','catName','catDesc'].forEach(id => document.getElementById(id).value='');
    document.getElementById('catAccount').value='';
}
document.querySelectorAll('.btn-edit-cat').forEach(function(btn){
    btn.addEventListener('click', function(){
        document.getElementById('catId').value = this.dataset.id;
        document.getElementById('catName').value = this.dataset.name;
        document.getElementById('catAccount').value = this.dataset.account || '';
        document.getElementById('catDesc').value = this.dataset.desc;
    });
});
</script>
