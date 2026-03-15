<?php
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo '<p class="text-muted">Invalid request</p>'; exit; }

$stmt = db()->prepare("SELECT je.*, u.name as created_by_name FROM journal_entries je
    LEFT JOIN users u ON u.id=je.created_by WHERE je.id=?");
$stmt->execute([$id]);
$journal = $stmt->fetch();
if (!$journal) { echo '<p class="text-muted">Journal not found</p>'; exit; }

$details = db()->prepare("SELECT jd.*, coa.account_code, coa.account_name FROM journal_details jd
    JOIN chart_of_accounts coa ON coa.id=jd.account_id WHERE jd.journal_id=?");
$details->execute([$id]);
$rows = $details->fetchAll();
?>
<table class="table table-bordered mb-0">
    <tr><th>Journal No</th><td><?= htmlspecialchars($journal['journal_no']) ?></td>
        <th>Date</th><td><?= formatDate($journal['date']) ?></td></tr>
    <tr><th>Narration</th><td colspan="3"><?= htmlspecialchars($journal['narration']) ?></td></tr>
    <tr><th>Created By</th><td colspan="3"><?= htmlspecialchars($journal['created_by_name'] ?? '') ?></td></tr>
</table>
<table class="table table-bordered mt-3">
    <thead class="table-light"><tr><th>Account</th><th class="text-end">Debit (₹)</th><th class="text-end">Credit (₹)</th></tr></thead>
    <tbody>
    <?php $tD=0;$tC=0; foreach ($rows as $r): $tD+=$r['debit_amount']; $tC+=$r['credit_amount']; ?>
    <tr>
        <td>[<?= $r['account_code'] ?>] <?= htmlspecialchars($r['account_name']) ?></td>
        <td class="text-end text-success"><?= $r['debit_amount'] > 0 ? number_format($r['debit_amount'],2) : '-' ?></td>
        <td class="text-end text-danger"><?= $r['credit_amount'] > 0 ? number_format($r['credit_amount'],2) : '-' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr class="fw-bold table-light"><td>Total</td>
        <td class="text-end text-success">₹<?= number_format($tD,2) ?></td>
        <td class="text-end text-danger">₹<?= number_format($tC,2) ?></td>
    </tr></tfoot>
</table>
