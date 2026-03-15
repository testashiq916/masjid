<?php
$pageTitle = 'Income Categories';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

// ── AJAX / POST handlers ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    // ── Save ──
    if ($action === 'save') {
        $id           = (int)($_POST['id']            ?? 0);
        $catName      = sanitize($_POST['category_name'] ?? '');
        $accountId    = (int)($_POST['account_id']    ?? 0);
        $description  = sanitize($_POST['description']   ?? '');
        $createdBy    = currentUserId();

        if (empty($catName)) {
            echo json_encode(['success' => false, 'message' => 'Category name is required.']);
            exit;
        }

        try {
            if ($id > 0) {
                $stmt = db()->prepare("
                    UPDATE income_categories
                    SET category_name=?, account_id=?, description=?, updated_at=NOW()
                    WHERE id = ? AND deleted_at IS NULL
                ");
                $stmt->execute([$catName, $accountId ?: null, $description, $id]);
                logAudit('update', 'income_categories', $id);
                echo json_encode(['success' => true, 'message' => 'Category updated successfully.']);
            } else {
                $stmt = db()->prepare("
                    INSERT INTO income_categories
                        (category_name, account_id, description, created_by, created_at)
                    VALUES (?,?,?,?,NOW())
                ");
                $stmt->execute([$catName, $accountId ?: null, $description, $createdBy]);
                $newId = (int)db()->lastInsertId();
                logAudit('create', 'income_categories', $newId);
                echo json_encode(['success' => true, 'message' => 'Category added successfully.', 'id' => $newId]);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Delete ──
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Check if in use
            $cnt = db()->prepare("SELECT COUNT(*) FROM receipts WHERE category_id = ? AND deleted_at IS NULL");
            $cnt->execute([$id]);
            if ((int)$cnt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete: category is used in existing receipts.']);
                exit;
            }
            db()->prepare("UPDATE income_categories SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
            logAudit('delete', 'income_categories', $id);
            echo json_encode(['success' => true, 'message' => 'Category deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        }
        exit;
    }

    // ── Get single ──
    if ($action === 'get') {
        $id   = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare("SELECT * FROM income_categories WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        echo json_encode($row ?: ['error' => 'Not found']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// ── Load categories with COA name ─────────────────────────────
$categories = db()->query("
    SELECT ic.*, coa.account_name,
           (SELECT COUNT(*) FROM receipts r WHERE r.category_id = ic.id AND r.deleted_at IS NULL) AS receipt_count
    FROM income_categories ic
    LEFT JOIN chart_of_accounts coa ON coa.id = ic.account_id
    WHERE ic.deleted_at IS NULL
    ORDER BY ic.category_name
")->fetchAll();

// Income accounts for dropdown
$incomeAccounts = db()->query("
    SELECT id, account_code, account_name
    FROM chart_of_accounts
    WHERE account_type IN ('Income', 'Revenue')
      AND deleted_at IS NULL
    ORDER BY account_name
")->fetchAll();

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
            <li class="breadcrumb-item active">Income Categories</li>
        </ol>
    </nav>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-tags me-1"></i> Income Categories</span>
            <button class="btn btn-success btn-sm" onclick="openCatModal(0)">
                <i class="bi bi-plus-circle me-1"></i> Add Category
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="catTable" class="table table-hover table-striped mb-0 align-middle" style="width:100%;">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Category Name</th>
                            <th>Linked Account</th>
                            <th>Description</th>
                            <th class="text-center">Used In</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $i => $cat): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($cat['category_name']) ?></td>
                            <td><?= htmlspecialchars($cat['account_name'] ?? '<span class="text-muted">None</span>') ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($cat['description'] ?? '') ?></td>
                            <td class="text-center">
                                <span class="badge bg-info"><?= (int)$cat['receipt_count'] ?> receipts</span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" title="Edit"
                                            onclick="openCatModal(<?= $cat['id'] ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" title="Delete"
                                            onclick="deleteCat(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['category_name'], ENT_QUOTES) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- content-area -->
</div><!-- main-content -->
</div><!-- wrapper -->

<!-- ── Add / Edit Category Modal ────────────────────────────── -->
<div class="modal fade" id="catModal" tabindex="-1" aria-labelledby="catModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="catModalLabel">
                    <i class="bi bi-tag me-1"></i> <span id="catModalTitle">Add Income Category</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="catFormAlert" class="d-none alert"></div>
                <form id="catForm" novalidate>
                    <input type="hidden" id="catId" name="id" value="0">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cat_name" name="category_name"
                               placeholder="e.g. Zakat, Sadaqa, Donation" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Linked Income Account (COA)</label>
                        <select class="form-select select2-modal" id="cat_account" name="account_id">
                            <option value="">-- Select Account --</option>
                            <?php foreach ($incomeAccounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>">
                                <?= htmlspecialchars($acc['account_code'] . ' - ' . $acc['account_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Used for double-entry journal posting.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" id="cat_desc" name="description" rows="2"
                                  placeholder="Optional description..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveCatBtn">
                    <i class="bi bi-save me-1"></i> Save Category
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
<script>
$(function () {
    $('#catTable').DataTable({
        pageLength: 25,
        order: [[1, 'asc']],
        language: { emptyTable: 'No income categories found.' }
    });
});

function openCatModal(id) {
    var modal = new bootstrap.Modal(document.getElementById('catModal'));
    $('#catFormAlert').addClass('d-none').removeClass('alert-success alert-danger');
    $('#catForm')[0].reset();
    $('#catId').val(0);
    $('#catModalTitle').text('Add Income Category');

    if (id > 0) {
        $('#catModalTitle').text('Edit Income Category');
        $.post(window.location.pathname, {
            action: 'get', id: id,
            csrf_token: $('input[name=csrf_token]').first().val()
        }, function (data) {
            if (data && !data.error) {
                $('#catId').val(data.id);
                $('#cat_name').val(data.category_name);
                $('#cat_account').val(data.account_id).trigger('change');
                $('#cat_desc').val(data.description);
            }
        }, 'json');
    }

    // Init Select2 inside modal
    $('.select2-modal').select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $('#catModal')
    });

    modal.show();
}

$('#saveCatBtn').on('click', function () {
    var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');
    $.post(window.location.pathname, $('#catForm').serialize(), function (res) {
        $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Save Category');
        if (res.success) {
            $('#catFormAlert').removeClass('d-none alert-danger').addClass('alert-success').text(res.message);
            setTimeout(function () { location.reload(); }, 1000);
        } else {
            $('#catFormAlert').removeClass('d-none alert-success').addClass('alert-danger').text(res.message);
        }
    }, 'json').fail(function () {
        $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Save Category');
        $('#catFormAlert').removeClass('d-none alert-success').addClass('alert-danger').text('Server error.');
    });
});

function deleteCat(id, name) {
    if (!confirm('Delete category "' + name + '"?\nThis will fail if there are existing receipts linked to it.')) return;
    $.post(window.location.pathname, {
        action: 'delete', id: id,
        csrf_token: $('input[name=csrf_token]').first().val()
    }, function (res) {
        if (res.success) { location.reload(); }
        else { alert(res.message); }
    }, 'json');
}
</script>
JS;
require_once __DIR__ . '/../../templates/footer.php';
