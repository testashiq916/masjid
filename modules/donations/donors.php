<?php
$pageTitle = 'Donors';
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

    // ── Save (Add / Edit) ──
    if ($action === 'save') {
        $id            = (int)($_POST['id'] ?? 0);
        $donorCode     = sanitize($_POST['donor_code']        ?? '');
        $donorName     = sanitize($_POST['donor_name']        ?? '');
        $phone         = sanitize($_POST['phone']             ?? '');
        $whatsapp      = sanitize($_POST['whatsapp']          ?? '');
        $email         = sanitize($_POST['email']             ?? '');
        $address       = sanitize($_POST['address']           ?? '');
        $city          = sanitize($_POST['city']              ?? '');
        $donationPurp  = sanitize($_POST['donation_purpose']  ?? '');
        $isRecurring   = (int)($_POST['is_recurring']         ?? 0);
        $notes         = sanitize($_POST['notes']             ?? '');
        $createdBy     = currentUserId();

        if (empty($donorName)) {
            echo json_encode(['success' => false, 'message' => 'Donor name is required.']);
            exit;
        }

        try {
            if ($id > 0) {
                $stmt = db()->prepare("
                    UPDATE donors SET donor_code=?, donor_name=?, phone=?, whatsapp=?, email=?,
                        address=?, city=?, donation_purpose=?, is_recurring=?, notes=?, updated_at=NOW()
                    WHERE id = ? AND deleted_at IS NULL
                ");
                $stmt->execute([$donorCode, $donorName, $phone, $whatsapp, $email,
                                $address, $city, $donationPurp, $isRecurring, $notes, $id]);
                logAudit('update', 'donors', $id);
                echo json_encode(['success' => true, 'message' => 'Donor updated successfully.']);
            } else {
                // Auto-generate code if blank
                if (empty($donorCode)) {
                    $donorCode = generateNumber('DNR', 'donors', 'donor_code');
                }
                $stmt = db()->prepare("
                    INSERT INTO donors
                        (donor_code, donor_name, phone, whatsapp, email, address, city,
                         donation_purpose, is_recurring, notes, total_donated, created_by, created_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,0,?,NOW())
                ");
                $stmt->execute([$donorCode, $donorName, $phone, $whatsapp, $email,
                                $address, $city, $donationPurp, $isRecurring, $notes, $createdBy]);
                $newId = (int)db()->lastInsertId();
                logAudit('create', 'donors', $newId);
                echo json_encode(['success' => true, 'message' => 'Donor added successfully.', 'id' => $newId]);
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
            db()->prepare("UPDATE donors SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
            logAudit('delete', 'donors', $id);
            echo json_encode(['success' => true, 'message' => 'Donor deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid donor ID.']);
        }
        exit;
    }

    // ── Get single donor for edit ──
    if ($action === 'get') {
        $id   = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare("SELECT * FROM donors WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $donor = $stmt->fetch(\PDO::FETCH_ASSOC);
        echo json_encode($donor ?: ['error' => 'Not found']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// ── Load donors with ledger summary ───────────────────────────
$donors = db()->query("
    SELECT d.*,
           COALESCE((SELECT COUNT(*) FROM receipts r WHERE r.donor_id = d.id AND r.deleted_at IS NULL), 0) AS receipt_count
    FROM donors d
    WHERE d.deleted_at IS NULL
    ORDER BY d.donor_name
")->fetchAll();

// Auto-generate next donor code
$nextCode = generateNumber('DNR', 'donors', 'donor_code');

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
            <li class="breadcrumb-item active">Donors</li>
        </ol>
    </nav>

    <!-- Flash (for non-AJAX operations) -->
    <?php $flash = getFlash(); if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Donors List Card -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-people me-1"></i> Donors</span>
            <button class="btn btn-success btn-sm" onclick="openDonorModal(0)">
                <i class="bi bi-plus-circle me-1"></i> Add Donor
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="donorsTable" class="table table-hover table-striped mb-0 align-middle" style="width:100%;">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Donor Name</th>
                            <th>Phone</th>
                            <th>City</th>
                            <th>Total Donated (₹)</th>
                            <th>Receipts</th>
                            <th>Last Donation</th>
                            <th>Recurring</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donors as $i => $d): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($d['donor_code'] ?? '') ?></span></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($d['donor_name']) ?></div>
                                <?php if ($d['email']): ?>
                                <small class="text-muted"><?= htmlspecialchars($d['email']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($d['phone'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($d['city'] ?? '-') ?></td>
                            <td class="fw-semibold text-success">
                                <?= number_format((float)($d['total_donated'] ?? 0), 2) ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info"><?= (int)$d['receipt_count'] ?></span>
                            </td>
                            <td><?= $d['last_donation_date'] ? formatDate($d['last_donation_date']) : '-' ?></td>
                            <td class="text-center">
                                <?php if ($d['is_recurring']): ?>
                                <span class="badge bg-success"><i class="bi bi-arrow-repeat"></i> Yes</span>
                                <?php else: ?>
                                <span class="badge bg-light text-muted">No</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-info" title="View Ledger"
                                            onclick="viewLedger(<?= $d['id'] ?>, '<?= htmlspecialchars($d['donor_name'], ENT_QUOTES) ?>')">
                                        <i class="bi bi-journal-text"></i>
                                    </button>
                                    <button class="btn btn-outline-primary" title="Edit"
                                            onclick="openDonorModal(<?= $d['id'] ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" title="Delete"
                                            onclick="deleteDonor(<?= $d['id'] ?>, '<?= htmlspecialchars($d['donor_name'], ENT_QUOTES) ?>')">
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

<!-- ── Add / Edit Donor Modal ──────────────────────────────── -->
<div class="modal fade" id="donorModal" tabindex="-1" aria-labelledby="donorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="donorModalLabel">
                    <i class="bi bi-person-plus me-1"></i> <span id="modalTitleText">Add Donor</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="donorFormAlert" class="d-none alert"></div>
                <form id="donorForm" novalidate>
                    <input type="hidden" id="donorId" name="id" value="0">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Donor Code</label>
                            <input type="text" class="form-control" id="donor_code" name="donor_code"
                                   placeholder="Auto-generated if blank" value="<?= htmlspecialchars($nextCode) ?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Donor Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="donor_name_f" name="donor_name"
                                   placeholder="Full name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" class="form-control" id="phone_f" name="phone"
                                   placeholder="+91 9999999999">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">WhatsApp</label>
                            <input type="text" class="form-control" id="whatsapp_f" name="whatsapp"
                                   placeholder="+91 9999999999">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control" id="email_f" name="email"
                                   placeholder="email@example.com">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Address</label>
                            <input type="text" class="form-control" id="address_f" name="address"
                                   placeholder="Street / Locality">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">City</label>
                            <input type="text" class="form-control" id="city_f" name="city"
                                   placeholder="City">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Donation Purpose</label>
                            <input type="text" class="form-control" id="donation_purpose_f" name="donation_purpose"
                                   placeholder="e.g. Masjid construction, Zakat, Sadaqa">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Recurring Donor?</label>
                            <select class="form-select" id="is_recurring_f" name="is_recurring">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control" id="notes_f" name="notes" rows="2"
                                      placeholder="Internal notes..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveDonorBtn">
                    <i class="bi bi-save me-1"></i> Save Donor
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Ledger Modal ─────────────────────────────────────────── -->
<div class="modal fade" id="ledgerModal" tabindex="-1" aria-labelledby="ledgerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="ledgerModalLabel">
                    <i class="bi bi-journal-text me-1"></i> Donor Ledger: <span id="ledgerDonorName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="ledgerContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-info"></div>
                        <p class="mt-2">Loading ledger...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
<script>
var BASE_PATH_JS = BASE_PATH;
var nextDonorCode = document.getElementById('donor_code').value;

$(function () {
    // DataTable
    $('#donorsTable').DataTable({
        pageLength: 25,
        order: [[2, 'asc']],
        language: { emptyTable: 'No donors found.' }
    });
});

function openDonorModal(id) {
    var modal = new bootstrap.Modal(document.getElementById('donorModal'));
    $('#donorFormAlert').addClass('d-none').removeClass('alert-success alert-danger');
    $('#donorForm')[0].reset();
    $('#donorId').val(0);
    $('#donor_code').val(nextDonorCode);
    $('#modalTitleText').text('Add Donor');

    if (id > 0) {
        $('#modalTitleText').text('Edit Donor');
        $.post(window.location.pathname, {
            action: 'get', id: id,
            csrf_token: $('input[name=csrf_token]').first().val()
        }, function (data) {
            if (data && !data.error) {
                $('#donorId').val(data.id);
                $('#donor_code').val(data.donor_code);
                $('#donor_name_f').val(data.donor_name);
                $('#phone_f').val(data.phone);
                $('#whatsapp_f').val(data.whatsapp);
                $('#email_f').val(data.email);
                $('#address_f').val(data.address);
                $('#city_f').val(data.city);
                $('#donation_purpose_f').val(data.donation_purpose);
                $('#is_recurring_f').val(data.is_recurring);
                $('#notes_f').val(data.notes);
            }
        }, 'json');
    }
    modal.show();
}

$('#saveDonorBtn').on('click', function () {
    var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');
    var formData = $('#donorForm').serialize();

    $.post(window.location.pathname, formData, function (res) {
        $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Save Donor');
        if (res.success) {
            $('#donorFormAlert').removeClass('d-none alert-danger').addClass('alert-success')
                .text(res.message);
            setTimeout(function () { location.reload(); }, 1200);
        } else {
            $('#donorFormAlert').removeClass('d-none alert-success').addClass('alert-danger')
                .text(res.message);
        }
    }, 'json').fail(function () {
        $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Save Donor');
        $('#donorFormAlert').removeClass('d-none alert-success').addClass('alert-danger')
            .text('Server error. Please try again.');
    });
});

function deleteDonor(id, name) {
    if (!confirm('Delete donor "' + name + '"?\n\nThis will also prevent linking future receipts to this donor.')) return;
    $.post(window.location.pathname, {
        action: 'delete', id: id,
        csrf_token: $('input[name=csrf_token]').first().val()
    }, function (res) {
        if (res.success) { location.reload(); }
        else { alert(res.message); }
    }, 'json');
}

function viewLedger(donorId, donorName) {
    $('#ledgerDonorName').text(donorName);
    $('#ledgerContent').html('<div class="text-center py-4"><div class="spinner-border text-info"></div><p class="mt-2">Loading...</p></div>');
    var modal = new bootstrap.Modal(document.getElementById('ledgerModal'));
    modal.show();

    $.get(BASE_PATH + '/modules/donations/receipt_list.php', { donor_ledger: donorId }, function () {}, 'html');

    // Load via inline query
    $.post(window.location.pathname, {
        action: 'ledger', id: donorId,
        csrf_token: $('input[name=csrf_token]').first().val()
    }, function (res) {
        if (res.html) {
            $('#ledgerContent').html(res.html);
        } else {
            loadLedgerDirect(donorId);
        }
    }, 'json').fail(function () {
        loadLedgerDirect(donorId);
    });
}

function loadLedgerDirect(donorId) {
    // Fallback: build ledger from receipts
    $.ajax({
        url: BASE_PATH + '/modules/donations/receipt_list.php',
        data: { donor_id: donorId, format: 'json' },
        success: function (data) {
            $('#ledgerContent').html('<p class="text-muted">Ledger not available inline. <a href="' + BASE_PATH + '/modules/donations/receipt_list.php?donor_id=' + donorId + '" target="_blank">View receipts for this donor</a></p>');
        },
        error: function () {
            $('#ledgerContent').html('<p class="text-danger">Could not load ledger.</p>');
        }
    });
}
</script>
JS;
require_once __DIR__ . '/../../templates/footer.php';
