<?php
$pageTitle = 'Audit Logs';
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();
requireRole([ROLE_ADMIN]);

$db = db();

// Filters
$fromDate = sanitize($_GET['from_date'] ?? date('Y-m-01'));
$toDate   = sanitize($_GET['to_date'] ?? date('Y-m-d'));
$module   = sanitize($_GET['module'] ?? '');
$userId   = (int)($_GET['user_id'] ?? 0);

$params = [];
$where  = ["al.created_at >= ?", "al.created_at <= DATE_ADD(?, INTERVAL 1 DAY)"];
$params[] = $fromDate;
$params[] = $toDate;

if (!empty($module)) {
    $where[]  = "al.module = ?";
    $params[] = $module;
}
if ($userId > 0) {
    $where[]  = "al.user_id = ?";
    $params[] = $userId;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

$logs = $db->prepare("
    SELECT al.*, u.name AS user_name, u.username
    FROM audit_logs al
    LEFT JOIN users u ON u.id = al.user_id
    $whereClause
    ORDER BY al.created_at DESC
    LIMIT 1000
");
$logs->execute($params);
$logs = $logs->fetchAll(PDO::FETCH_ASSOC);

// Get distinct modules for filter
$modules = $db->query("SELECT DISTINCT module FROM audit_logs ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);

// Get users for filter
$users = $db->query("SELECT id, name FROM users WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$actionBadge = [
    'CREATE' => 'success',
    'UPDATE' => 'primary',
    'DELETE' => 'danger',
    'LOGIN'  => 'info',
    'LOGOUT' => 'secondary',
    'VIEW'   => 'light',
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
            <li class="breadcrumb-item">Admin</li>
            <li class="breadcrumb-item active">Audit Logs</li>
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
            <h4 class="mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Audit Logs</h4>
            <small class="text-muted">Read-only trail of all system actions</small>
        </div>
        <button class="btn btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print
        </button>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">From Date</label>
                    <input type="date" class="form-control" name="from_date" value="<?= htmlspecialchars($fromDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">To Date</label>
                    <input type="date" class="form-control" name="to_date" value="<?= htmlspecialchars($toDate) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Module</label>
                    <select class="form-select" name="module">
                        <option value="">All Modules</option>
                        <?php foreach ($modules as $mod): ?>
                        <option value="<?= htmlspecialchars($mod) ?>" <?= $module === $mod ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $mod))) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">User</label>
                    <select class="form-select" name="user_id">
                        <option value="">All Users</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $userId === (int)$u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="bi bi-search me-1"></i> Filter
                    </button>
                    <a href="<?= BASE_PATH ?>/modules/admin/audit_logs.php" class="btn btn-outline-secondary" title="Reset">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <?php
        $counts = array_count_values(array_column($logs, 'action'));
        $summary = [
            'CREATE' => ['label' => 'Created', 'color' => 'success', 'icon' => 'plus-circle'],
            'UPDATE' => ['label' => 'Updated', 'color' => 'primary', 'icon' => 'pencil-square'],
            'DELETE' => ['label' => 'Deleted', 'color' => 'danger', 'icon' => 'trash'],
            'LOGIN'  => ['label' => 'Logins',  'color' => 'info',    'icon' => 'box-arrow-in-right'],
        ];
        foreach ($summary as $act => $info):
        ?>
        <div class="col-6 col-md-3">
            <div class="card border-<?= $info['color'] ?>">
                <div class="card-body py-2 d-flex align-items-center gap-3">
                    <i class="bi bi-<?= $info['icon'] ?> fs-4 text-<?= $info['color'] ?>"></i>
                    <div>
                        <div class="fw-bold fs-5"><?= $counts[$act] ?? 0 ?></div>
                        <div class="text-muted small"><?= $info['label'] ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Logs Table -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="auditTable" class="table table-hover align-middle table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Date &amp; Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Record ID</th>
                            <th>IP Address</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $i => $log): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td class="text-nowrap"><?= formatDate($log['created_at'], 'd/m/Y H:i:s') ?></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($log['username'] ?? '') ?></small>
                            </td>
                            <td>
                                <?php $badge = $actionBadge[$log['action']] ?? 'secondary'; ?>
                                <span class="badge bg-<?= $badge ?> <?= $badge === 'light' ? 'text-dark' : '' ?>">
                                    <?= htmlspecialchars($log['action']) ?>
                                </span>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars(str_replace('_', ' ', $log['module'])) ?></span></td>
                            <td><?= $log['record_id'] > 0 ? '#' . $log['record_id'] : '-' ?></td>
                            <td><code><?= htmlspecialchars($log['ip_address'] ?? '') ?></code></td>
                            <td>
                                <?php if (!empty($log['new_values']) && $log['new_values'] !== '[]' && $log['new_values'] !== 'null'): ?>
                                <button class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="modal" data-bs-target="#logDetailModal"
                                        onclick="showLogDetail(<?= htmlspecialchars(json_encode([
                                            'action'     => $log['action'],
                                            'module'     => $log['module'],
                                            'record_id'  => $log['record_id'],
                                            'old_values' => $log['old_values'],
                                            'new_values' => $log['new_values'],
                                            'created_at' => $log['created_at'],
                                            'user_name'  => $log['user_name'],
                                        ])) ?>)">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No audit logs found for the selected filters.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
</div>

<!-- Log Detail Modal -->
<div class="modal fade" id="logDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i>Audit Log Detail</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm table-bordered mb-3">
                    <tr><th style="width:130px;">Module</th><td id="detailModule"></td></tr>
                    <tr><th>Action</th><td id="detailAction"></td></tr>
                    <tr><th>Record ID</th><td id="detailRecordId"></td></tr>
                    <tr><th>Date</th><td id="detailDate"></td></tr>
                    <tr><th>User</th><td id="detailUser"></td></tr>
                </table>
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="text-muted">Old Values</h6>
                        <pre class="bg-light border rounded p-2 small" id="detailOld" style="max-height:200px;overflow:auto;"></pre>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-success">New Values</h6>
                        <pre class="bg-light border rounded p-2 small" id="detailNew" style="max-height:200px;overflow:auto;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extraJs = <<<JS
<script>
function showLogDetail(log) {
    document.getElementById('detailModule').textContent = log.module.replace(/_/g,' ');
    document.getElementById('detailAction').textContent = log.action;
    document.getElementById('detailRecordId').textContent = log.record_id > 0 ? '#' + log.record_id : '-';
    document.getElementById('detailDate').textContent = log.created_at;
    document.getElementById('detailUser').textContent = log.user_name || 'System';
    try {
        document.getElementById('detailOld').textContent = JSON.stringify(JSON.parse(log.old_values), null, 2);
    } catch(e) { document.getElementById('detailOld').textContent = log.old_values || '-'; }
    try {
        document.getElementById('detailNew').textContent = JSON.stringify(JSON.parse(log.new_values), null, 2);
    } catch(e) { document.getElementById('detailNew').textContent = log.new_values || '-'; }
}

$(document).ready(function () {
    $('#auditTable').DataTable({
        order: [[1, 'desc']],
        pageLength: 50,
        dom: 'Bfrtip',
        buttons: ['copy', 'excel', 'pdf', 'print']
    });
});
</script>
JS;
require_once __DIR__ . '/../../templates/footer.php';
?>
