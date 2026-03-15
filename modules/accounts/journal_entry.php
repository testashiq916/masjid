<?php
$pageTitle = 'Journal Entry';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_PATH . '/modules/accounts/journal_entry.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $date       = sanitize($_POST['date'] ?? date('Y-m-d'));
        $narration  = sanitize($_POST['narration'] ?? '');
        $accountIds = $_POST['account_id'] ?? [];
        $debits     = $_POST['debit_amount'] ?? [];
        $credits    = $_POST['credit_amount'] ?? [];
        $fyRow      = getCurrentFinancialYear();
        $fyId       = $fyRow['id'] ?? null;

        if (empty($narration)) {
            setFlash('danger', 'Narration is required.');
            redirect(BASE_PATH . '/modules/accounts/journal_entry.php');
        }

        $totalDebit  = array_sum(array_map('floatval', $debits));
        $totalCredit = array_sum(array_map('floatval', $credits));

        if ($totalDebit <= 0) {
            setFlash('danger', 'Please enter at least one debit entry.');
            redirect(BASE_PATH . '/modules/accounts/journal_entry.php');
        }
        if (abs($totalDebit - $totalCredit) > 0.01) {
            setFlash('danger', 'Debit and Credit totals must be equal. Debit: ' . formatCurrency($totalDebit) . ' | Credit: ' . formatCurrency($totalCredit));
            redirect(BASE_PATH . '/modules/accounts/journal_entry.php');
        }

        $prefix = getSetting('journal_prefix', 'JNL');
        $jNo    = generateNumber($prefix, 'journal_entries', 'journal_no');

        $db->prepare("INSERT INTO journal_entries (journal_no, date, narration, financial_year_id, created_by, created_at)
                      VALUES (?, ?, ?, ?, ?, NOW())")
           ->execute([$jNo, $date, $narration, $fyId, currentUserId()]);
        $jId = (int)$db->lastInsertId();

        $detailStmt = $db->prepare("INSERT INTO journal_details (journal_id, account_id, debit_amount, credit_amount) VALUES (?, ?, ?, ?)");
        foreach ($accountIds as $i => $aId) {
            $d = (float)($debits[$i] ?? 0);
            $c = (float)($credits[$i] ?? 0);
            if ($aId && ($d > 0 || $c > 0)) {
                $detailStmt->execute([$jId, (int)$aId, $d, $c]);
            }
        }
        logAudit('CREATE', 'journal_entries', $jId, [], ['journal_no' => $jNo, 'date' => $date, 'narration' => $narration]);
        setFlash('success', "Journal entry {$jNo} saved successfully.");
        redirect(BASE_PATH . '/modules/accounts/journal_entry.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['del_id'] ?? 0);
        // Check if audited
        $chk = $db->prepare("SELECT is_audited FROM journal_entries WHERE id=?");
        $chk->execute([$id]);
        $entry = $chk->fetch();
        if ($entry && $entry['is_audited']) {
            setFlash('danger', 'Cannot delete an audited journal entry.');
        } else {
            $db->prepare("UPDATE journal_entries SET deleted_at=NOW() WHERE id=?")->execute([$id]);
            logAudit('DELETE', 'journal_entries', $id, [], []);
            setFlash('success', 'Journal entry deleted.');
        }
        redirect(BASE_PATH . '/modules/accounts/journal_entry.php');
    }
}

// Filters
$fromDate = sanitize($_GET['from_date'] ?? date('Y-m-01'));
$toDate   = sanitize($_GET['to_date'] ?? date('Y-m-d'));

$journals = $db->prepare("
    SELECT je.*, u.name AS created_by_name,
        (SELECT SUM(debit_amount) FROM journal_details WHERE journal_id = je.id) AS total_amount
    FROM journal_entries je
    LEFT JOIN users u ON u.id = je.created_by
    WHERE je.deleted_at IS NULL
      AND je.date BETWEEN ? AND ?
    ORDER BY je.date DESC, je.id DESC
");
$journals->execute([$fromDate, $toDate]);
$journals = $journals->fetchAll(PDO::FETCH_ASSOC);

$accounts = $db->query("
    SELECT id, account_code, account_name
    FROM chart_of_accounts
    WHERE deleted_at IS NULL AND status='active'
    ORDER BY account_code
")->fetchAll(PDO::FETCH_ASSOC);

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
            <li class="breadcrumb-item active">Journal Entry</li>
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
            <h4 class="mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i>Journal Entry</h4>
            <small class="text-muted">Record double-entry accounting transactions</small>
        </div>
        <?php if (isAdmin() || hasPermission('accounts', 'add')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#journalModal">
            <i class="bi bi-plus-circle me-1"></i> New Journal Entry
        </button>
        <?php endif; ?>
    </div>

    <!-- Date Filter -->
    <div class="card shadow-sm mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?= $fromDate ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?= $toDate ?>">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search me-1"></i>Filter</button>
                    <a href="<?= BASE_PATH ?>/modules/accounts/journal_entry.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Journal Entries Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between">
            <span class="fw-semibold">Journal Entries</span>
            <span class="text-muted small"><?= count($journals) ?> entries</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="journalTable" class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Journal No</th>
                            <th>Date</th>
                            <th>Narration</th>
                            <th class="text-end">Amount</th>
                            <th>Created By</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($journals as $i => $j): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($j['journal_no']) ?></strong></td>
                        <td><?= formatDate($j['date']) ?></td>
                        <td><?= htmlspecialchars($j['narration']) ?></td>
                        <td class="text-end fw-semibold"><?= formatCurrency((float)($j['total_amount'] ?? 0)) ?></td>
                        <td class="small text-muted"><?= htmlspecialchars($j['created_by_name'] ?? '-') ?></td>
                        <td>
                            <?php if (!empty($j['is_audited'])): ?>
                                <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>Audited</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Draft</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-info me-1"
                                    data-bs-toggle="modal" data-bs-target="#viewJournalModal"
                                    onclick="loadJournalDetail(<?= $j['id'] ?>)"
                                    title="View">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if (!$j['is_audited'] && (isAdmin() || hasPermission('accounts', 'delete'))): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this journal entry?');">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="del_id" value="<?= $j['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($journals)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No journal entries found for the selected period.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
</div>

<!-- New Journal Entry Modal -->
<div class="modal fade" id="journalModal" tabindex="-1" aria-labelledby="journalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" id="journalForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                <input type="hidden" name="action" value="save">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="journalModalLabel">
                        <i class="bi bi-pencil-square me-2"></i>New Journal Entry
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Journal No</label>
                            <input type="text" class="form-control bg-light" value="Auto-generated" disabled>
                            <div class="form-text">Will be assigned on save.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Financial Year</label>
                            <input type="text" class="form-control bg-light"
                                   value="<?= htmlspecialchars(getCurrentFinancialYear()['year_label'] ?? 'No active year') ?>" disabled>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Narration <span class="text-danger">*</span></label>
                            <input type="text" name="narration" class="form-control" required
                                   placeholder="Brief description of this journal entry">
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong><i class="bi bi-table me-1"></i>Journal Details</strong>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addJournalRow">
                            <i class="bi bi-plus"></i> Add Row
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="journalDetailsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:50%">Account</th>
                                    <th class="text-end">Debit (<?= htmlspecialchars(getSetting('currency_symbol', '₹')) ?>)</th>
                                    <th class="text-end">Credit (<?= htmlspecialchars(getSetting('currency_symbol', '₹')) ?>)</th>
                                    <th style="width:50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="journalRows">
                                <?php for ($i = 0; $i < 3; $i++): ?>
                                <tr>
                                    <td>
                                        <select name="account_id[]" class="form-select select2">
                                            <option value="">-- Select Account --</option>
                                            <?php foreach ($accounts as $a): ?>
                                            <option value="<?= $a['id'] ?>">[<?= $a['account_code'] ?>] <?= htmlspecialchars($a['account_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="number" name="debit_amount[]" class="form-control text-end journal-debit" step="0.01" min="0" value="0"></td>
                                    <td><input type="number" name="credit_amount[]" class="form-control text-end journal-credit" step="0.01" min="0" value="0"></td>
                                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-x"></i></button></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold table-light">
                                    <td class="text-end">Total</td>
                                    <td class="text-end text-success" id="totalDebit">0.00</td>
                                    <td class="text-end text-danger" id="totalCredit">0.00</td>
                                    <td class="text-center" id="balanceCheck"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveJournalBtn">
                        <i class="bi bi-save me-1"></i> Save Journal Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Journal Detail Modal -->
<div class="modal fade" id="viewJournalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Journal Entry Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewJournalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p class="text-muted mt-2">Loading...</p>
                </div>
            </div>
            <div class="modal-footer no-print">
                <button class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
$accountOptionsHtml = '';
foreach ($accounts as $a) {
    $accountOptionsHtml .= '<option value="' . $a['id'] . '">[' . htmlspecialchars($a['account_code']) . '] ' . htmlspecialchars($a['account_name'], ENT_QUOTES) . '</option>';
}

$extraJs = <<<JS
<script>
const accountOptions = `{$accountOptionsHtml}`;

function updateTotals() {
    let debit = 0, credit = 0;
    document.querySelectorAll('.journal-debit').forEach(el => debit += parseFloat(el.value || 0));
    document.querySelectorAll('.journal-credit').forEach(el => credit += parseFloat(el.value || 0));
    document.getElementById('totalDebit').textContent = debit.toFixed(2);
    document.getElementById('totalCredit').textContent = credit.toFixed(2);
    const diff = Math.abs(debit - credit);
    const check = document.getElementById('balanceCheck');
    const btn = document.getElementById('saveJournalBtn');
    if (diff < 0.01 && debit > 0) {
        check.innerHTML = '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Balanced</span>';
        btn.disabled = false;
    } else {
        check.innerHTML = '<span class="badge bg-danger">Diff: ' + diff.toFixed(2) + '</span>';
        btn.disabled = (debit > 0);
    }
}

document.getElementById('addJournalRow').addEventListener('click', function() {
    const tbody = document.getElementById('journalRows');
    const row = document.createElement('tr');
    row.innerHTML = `<td><select name="account_id[]" class="form-select select2"><option value="">-- Select Account --</option>\${accountOptions}</select></td>
        <td><input type="number" name="debit_amount[]" class="form-control text-end journal-debit" step="0.01" min="0" value="0"></td>
        <td><input type="number" name="credit_amount[]" class="form-control text-end journal-credit" step="0.01" min="0" value="0"></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-x"></i></button></td>`;
    tbody.appendChild(row);
    if ($.fn.select2) $(row).find('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
    row.querySelectorAll('input').forEach(el => el.addEventListener('input', updateTotals));
    updateTotals();
});

document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-row')) {
        const rows = document.getElementById('journalRows').querySelectorAll('tr');
        if (rows.length > 2) {
            e.target.closest('tr').remove();
            updateTotals();
        }
    }
});

document.querySelectorAll('.journal-debit, .journal-credit').forEach(el => el.addEventListener('input', updateTotals));
updateTotals();

function loadJournalDetail(id) {
    document.getElementById('viewJournalBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="text-muted mt-2">Loading...</p></div>';
    fetch(`<?= BASE_PATH ?>/modules/accounts/get_journal.php?id=` + id)
        .then(r => r.ok ? r.text() : Promise.reject('Failed'))
        .then(html => { document.getElementById('viewJournalBody').innerHTML = html; })
        .catch(() => { document.getElementById('viewJournalBody').innerHTML = '<div class="alert alert-danger">Failed to load journal details.</div>'; });
}

$(document).ready(function() {
    $('#journalTable').DataTable({
        order: [[2, 'desc']],
        pageLength: 25,
        dom: 'Bfrtip',
        buttons: ['copy', 'excel', 'pdf', 'print']
    });
    // Init select2 on existing modal selects
    $('#journalModal').on('shown.bs.modal', function() {
        $(this).find('.select2').select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $('#journalModal') });
    });
});
</script>
JS;
require_once __DIR__ . '/../../templates/footer.php';
?>
