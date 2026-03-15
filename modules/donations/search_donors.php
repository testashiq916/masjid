<?php
/**
 * AJAX endpoint: Search donors for Select2
 * GET ?q=searchterm
 * Returns JSON: [{"id": 1, "text": "Name", "donor_name": "Name", "donor_phone": "..."}, ...]
 */
require_once __DIR__ . '/../../app/middleware/auth_check.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$search = '%' . $q . '%';

$stmt = db()->prepare("
    SELECT id, donor_code, donor_name, phone, city
    FROM donors
    WHERE deleted_at IS NULL
      AND (donor_name LIKE ? OR phone LIKE ? OR donor_code LIKE ? OR email LIKE ?)
    ORDER BY donor_name
    LIMIT 30
");
$stmt->execute([$search, $search, $search, $search]);
$donors = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$results = [];
foreach ($donors as $d) {
    $label = htmlspecialchars($d['donor_name']);
    if ($d['city']) {
        $label .= ' (' . htmlspecialchars($d['city']) . ')';
    }
    if ($d['phone']) {
        $label .= ' - ' . htmlspecialchars($d['phone']);
    }
    $results[] = [
        'id'          => $d['id'],
        'text'        => $label,
        'donor_name'  => $d['donor_name'],
        'donor_phone' => $d['phone'] ?? '',
        'donor_code'  => $d['donor_code'] ?? '',
    ];
}

echo json_encode($results);
