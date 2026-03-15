<?php
// ============================================================
// Common Helper Functions
// ============================================================

/**
 * Generate a unique receipt/voucher number
 */
function generateNumber(string $prefix, string $table, string $column): string {
    $year = date('Y');
    $month = date('m');
    $stmt = db()->prepare("SELECT MAX(CAST(SUBSTRING_INDEX($column, '-', -1) AS UNSIGNED)) as max_num
                            FROM $table WHERE $column LIKE ?");
    $stmt->execute(["$prefix-$year$month-%"]);
    $row = $stmt->fetch();
    $next = ($row['max_num'] ?? 0) + 1;
    return $prefix . '-' . $year . $month . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
}

/**
 * Format currency
 */
function formatCurrency(float $amount, string $symbol = '₹'): string {
    return $symbol . ' ' . number_format($amount, 2);
}

/**
 * Format date for display
 */
function formatDate(string $date, string $format = 'd/m/Y'): string {
    if (empty($date) || $date === '0000-00-00') return '-';
    return date($format, strtotime($date));
}

/**
 * Get current financial year ID
 */
function getCurrentFinancialYear(): ?array {
    $stmt = db()->prepare("SELECT * FROM financial_years WHERE status = 'active' ORDER BY start_date DESC LIMIT 1");
    $stmt->execute();
    return $stmt->fetch() ?: null;
}

/**
 * Get setting value
 */
function getSetting(string $key, string $default = ''): string {
    static $settings = [];
    if (empty($settings)) {
        $stmt = db()->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $settings[$key] ?? $default;
}

/**
 * Get masjid profile
 */
function getMasjidProfile(): array {
    static $profile = null;
    if ($profile === null) {
        $stmt = db()->query("SELECT * FROM masjid_profile LIMIT 1");
        $profile = $stmt->fetch() ?: ['masjid_name' => 'Masjid ERP'];
    }
    return $profile;
}

/**
 * Get account balance (Assets/Income = debit-credit, Liabilities/Expense = credit-debit)
 */
function getAccountBalance(int $accountId): float {
    $stmt = db()->prepare("
        SELECT coa.account_type,
               COALESCE((SELECT SUM(r.amount) FROM receipts r WHERE r.account_id = ? AND r.deleted_at IS NULL), 0) as total_income,
               COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.account_id = ? AND p.deleted_at IS NULL), 0) as total_expense,
               COALESCE((SELECT SUM(jd.debit_amount) FROM journal_details jd
                         JOIN journal_entries je ON je.id = jd.journal_id WHERE jd.account_id = ? AND je.deleted_at IS NULL), 0) as total_debit,
               COALESCE((SELECT SUM(jd.credit_amount) FROM journal_details jd
                         JOIN journal_entries je ON je.id = jd.journal_id WHERE jd.account_id = ? AND je.deleted_at IS NULL), 0) as total_credit,
               opening_balance
        FROM chart_of_accounts coa WHERE coa.id = ?
    ");
    $stmt->execute([$accountId, $accountId, $accountId, $accountId, $accountId]);
    $row = $stmt->fetch();
    if (!$row) return 0;

    $opening = (float)($row['opening_balance'] ?? 0);
    $income  = (float)$row['total_income'];
    $expense = (float)$row['total_expense'];
    $debit   = (float)$row['total_debit'];
    $credit  = (float)$row['total_credit'];

    if (in_array($row['account_type'], ['Assets', 'Expense'])) {
        return $opening + $expense + $debit - $income - $credit;
    } else {
        return $opening + $income + $credit - $expense - $debit;
    }
}

/**
 * Get cash in hand balance
 */
function getCashBalance(): float {
    $stmt = db()->prepare("SELECT id FROM chart_of_accounts WHERE account_code = '1001' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch();
    if (!$row) return 0;
    return getAccountBalance($row['id']);
}

/**
 * Get bank balance
 */
function getBankBalance(): float {
    $stmt = db()->prepare("SELECT id FROM chart_of_accounts WHERE account_code = '1002' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch();
    if (!$row) return 0;
    return getAccountBalance($row['id']);
}

/**
 * Sanitize input
 */
function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect helper
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Flash message
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * CSRF token
 */
function generateCSRF(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if user has permission
 */
function hasPermission(string $module, string $action = 'view'): bool {
    if (!isset($_SESSION['user'])) return false;
    if ($_SESSION['user']['role_id'] == ROLE_ADMIN) return true;

    $roleId = $_SESSION['user']['role_id'];
    $colMap = ['view' => 'can_view', 'add' => 'can_add', 'edit' => 'can_edit', 'delete' => 'can_delete'];
    $col = $colMap[$action] ?? 'can_view';

    $stmt = db()->prepare("SELECT $col FROM permissions WHERE role_id = ? AND module = ?");
    $stmt->execute([$roleId, $module]);
    $row = $stmt->fetch();
    return $row ? (bool)$row[$col] : false;
}

/**
 * Log audit trail
 */
function logAudit(string $action, string $module, int $recordId = 0, array $old = [], array $new = []): void {
    $userId = $_SESSION['user']['id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt = db()->prepare("INSERT INTO audit_logs (user_id, action, module, record_id, old_values, new_values, ip_address, user_agent) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$userId, $action, $module, $recordId, json_encode($old), json_encode($new), $ip, substr($ua, 0, 500)]);
}

/**
 * Get total income for a period
 */
function getTotalIncome(string $startDate = '', string $endDate = ''): float {
    $where = "deleted_at IS NULL";
    $params = [];
    if ($startDate) { $where .= " AND date >= ?"; $params[] = $startDate; }
    if ($endDate)   { $where .= " AND date <= ?"; $params[] = $endDate; }
    $stmt = db()->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM receipts WHERE $where");
    $stmt->execute($params);
    return (float)$stmt->fetchColumn();
}

/**
 * Get total expense for a period
 */
function getTotalExpense(string $startDate = '', string $endDate = ''): float {
    $where = "deleted_at IS NULL";
    $params = [];
    if ($startDate) { $where .= " AND date >= ?"; $params[] = $startDate; }
    if ($endDate)   { $where .= " AND date <= ?"; $params[] = $endDate; }
    $stmt = db()->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE $where");
    $stmt->execute($params);
    return (float)$stmt->fetchColumn();
}

/**
 * Paginate results
 */
function paginate(int $total, int $perPage = 20, int $currentPage = 1): array {
    $totalPages = (int)ceil($total / $perPage);
    $offset = ($currentPage - 1) * $perPage;
    return [
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => $offset,
    ];
}

// Load language helper if not already loaded
if (!function_exists('__')) {
    require_once __DIR__ . '/lang.php';
}
