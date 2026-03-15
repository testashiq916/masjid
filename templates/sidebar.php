<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

function isActiveMenu(array $pages, string $current): string {
    return in_array($current, $pages) ? 'active' : '';
}

function isActiveSection(array $dirs, string $current): string {
    return in_array($current, $dirs) ? 'show' : '';
}
?>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand d-flex align-items-center px-3 py-2">
        <i class="bi bi-building-fill me-2 text-warning fs-4"></i>
        <div>
            <div class="fw-bold text-white small"><?= APP_NAME ?></div>
            <div class="text-white-50" style="font-size:0.7rem;">v<?= APP_VERSION ?></div>
        </div>
    </div>
    <hr class="border-secondary my-1">

    <div class="sidebar-menu overflow-auto">
        <ul class="nav flex-column px-2">

            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?= isActiveMenu(['dashboard.php'], $currentPage) ?>"
                   href="<?= BASE_PATH ?>/dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>

            <!-- Accounts -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-toggle="collapse" href="#accountsMenu">
                    <i class="bi bi-journals me-2"></i> Accounts
                    <i class="bi bi-chevron-down ms-auto small"></i>
                </a>
                <div class="collapse <?= isActiveSection(['accounts'], $currentDir) ?>" id="accountsMenu">
                    <ul class="nav flex-column ms-3">
                        <li><a class="nav-link small <?= isActiveMenu(['chart_of_accounts.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/accounts/chart_of_accounts.php">
                            <i class="bi bi-diagram-3 me-2"></i>Chart of Accounts</a></li>
                        <li><a class="nav-link small <?= isActiveMenu(['journal_entry.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/accounts/journal_entry.php">
                            <i class="bi bi-pencil-square me-2"></i>Journal Entry</a></li>
                        <li><a class="nav-link small <?= isActiveMenu(['cash_book.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/accounts/cash_book.php">
                            <i class="bi bi-cash me-2"></i>Cash Book</a></li>
                        <li><a class="nav-link small <?= isActiveMenu(['bank_book.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/accounts/bank_book.php">
                            <i class="bi bi-bank me-2"></i>Bank Book</a></li>
                        <li><a class="nav-link small <?= isActiveMenu(['ledger.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/accounts/ledger.php">
                            <i class="bi bi-book me-2"></i>Ledger</a></li>
                    </ul>
                </div>
            </li>

            <!-- Income / Donations -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-toggle="collapse" href="#donationsMenu">
                    <i class="bi bi-arrow-down-circle me-2 text-success"></i> Income
                    <i class="bi bi-chevron-down ms-auto small"></i>
                </a>
                <div class="collapse <?= isActiveSection(['donations'], $currentDir) ?>" id="donationsMenu">
                    <ul class="nav flex-column ms-3">
                        <li><a class="nav-link small <?= isActiveMenu(['add_receipt.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/donations/add_receipt.php">
                            <i class="bi bi-plus-circle me-2"></i>Add Receipt</a></li>
                        <li><a class="nav-link small <?= isActiveMenu(['receipt_list.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/donations/receipt_list.php">
                            <i class="bi bi-list-ul me-2"></i>Receipt List</a></li>
                        <li><a class="nav-link small <?= isActiveMenu(['donors.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/donations/donors.php">
                            <i class="bi bi-people me-2"></i>Donors</a></li>
                        <li><a class="nav-link small <?= isActiveMenu(['income_categories.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/donations/income_categories.php">
                            <i class="bi bi-tags me-2"></i>Categories</a></li>
                    </ul>
                </div>
            </li>

            <!-- Expenses -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-toggle="collapse" href="#expensesMenu">
                    <i class="bi bi-arrow-up-circle me-2 text-danger"></i> Expenses
                    <i class="bi bi-chevron-down ms-auto small"></i>
                </a>
                <div class="collapse <?= isActiveSection(['expenses'], $currentDir) ?>" id="expensesMenu">
                    <ul class="nav flex-column ms-3">
                        <li><a class="nav-link small <?= isActiveMenu(['add_payment.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/expenses/add_payment.php">
                            <i class="bi bi-plus-circle me-2"></i>Add Payment</a></li>
                        <li><a class="nav-link small <?= isActiveMenu(['payment_list.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/expenses/payment_list.php">
                            <i class="bi bi-list-ul me-2"></i>Payment List</a></li>
                        <li><a class="nav-link small <?= isActiveMenu(['expense_categories.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/expenses/expense_categories.php">
                            <i class="bi bi-tags me-2"></i>Categories</a></li>
                        <?php if (getSetting('approval_enabled') === '1'): ?>
                        <li>
                            <a class="nav-link small <?= isActiveMenu(['pending_approvals.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/expenses/pending_approvals.php">
                                <i class="bi bi-shield-check me-2 text-warning"></i>Approvals
                                <?php
                                $pendingApprCnt = (int)db()->query("SELECT COUNT(*) FROM payments WHERE approval_status='pending_approval' AND deleted_at IS NULL")->fetchColumn();
                                if ($pendingApprCnt > 0): ?>
                                <span class="badge bg-danger ms-1"><?= $pendingApprCnt ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </li>

            <!-- Waqf -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-toggle="collapse" href="#waqfMenu">
                    <i class="bi bi-houses me-2 text-warning"></i> Waqf
                    <i class="bi bi-chevron-down ms-auto small"></i>
                </a>
                <div class="collapse <?= isActiveSection(['waqf'], $currentDir) ?>" id="waqfMenu">
                    <ul class="nav flex-column ms-3">
                        <li><a class="nav-link small <?= isActiveMenu(['properties.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/waqf/properties.php">
                            <i class="bi bi-building me-2"></i>Properties</a></li>
                        <li><a class="nav-link small <?= isActiveMenu(['tenants.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/waqf/tenants.php">
                            <i class="bi bi-person-badge me-2"></i>Tenants</a></li>
                        <li><a class="nav-link small <?= isActiveMenu(['rent_collection.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/waqf/rent_collection.php">
                            <i class="bi bi-collection me-2"></i>Rent Collection</a></li>
                        <li><a class="nav-link small <?= isActiveMenu(['rent_due.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/waqf/rent_due.php">
                            <i class="bi bi-exclamation-circle me-2"></i>Rent Due</a></li>
                    </ul>
                </div>
            </li>

            <!-- Staff -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-toggle="collapse" href="#staffMenu">
                    <i class="bi bi-person-workspace me-2"></i> Staff
                    <i class="bi bi-chevron-down ms-auto small"></i>
                </a>
                <div class="collapse <?= isActiveSection(['staff', 'salary'], $currentDir) ?>" id="staffMenu">
                    <ul class="nav flex-column ms-3">
                        <li><a class="nav-link small <?= isActiveMenu(['staff_list.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/staff/staff_list.php">
                            <i class="bi bi-people me-2"></i>Staff List</a></li>
                        <li><a class="nav-link small <?= isActiveMenu(['salary_sheet.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/salary/salary_sheet.php">
                            <i class="bi bi-wallet2 me-2"></i>Salary Sheet</a></li>
                        <li><a class="nav-link small <?= isActiveMenu(['salary_payment.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/salary/salary_payment.php">
                            <i class="bi bi-cash-stack me-2"></i>Pay Salary</a></li>
                    </ul>
                </div>
            </li>

            <!-- Assets -->
            <li class="nav-item">
                <a class="nav-link <?= isActiveMenu(['assets_list.php'], $currentPage) ?>"
                   href="<?= BASE_PATH ?>/modules/assets_register/assets_list.php">
                    <i class="bi bi-boxes me-2"></i> Asset Register
                </a>
            </li>

            <!-- Funds -->
            <li class="nav-item">
                <a class="nav-link <?= isActiveMenu(['funds.php'], $currentPage) ?>"
                   href="<?= BASE_PATH ?>/modules/funds/funds.php">
                    <i class="bi bi-piggy-bank me-2"></i> Fund Management
                </a>
            </li>

            <!-- Reports -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-toggle="collapse" href="#reportsMenu">
                    <i class="bi bi-bar-chart-line me-2 text-info"></i> Reports
                    <i class="bi bi-chevron-down ms-auto small"></i>
                </a>
                <div class="collapse <?= isActiveSection(['reports'], $currentDir) ?>" id="reportsMenu">
                    <ul class="nav flex-column ms-3">
                        <li><a class="nav-link small" href="<?= BASE_PATH ?>/modules/reports/income_report.php">
                            <i class="bi bi-graph-up-arrow me-2"></i>Income Report</a></li>
                        <li><a class="nav-link small" href="<?= BASE_PATH ?>/modules/reports/expense_report.php">
                            <i class="bi bi-graph-down-arrow me-2"></i>Expense Report</a></li>
                        <li><a class="nav-link small" href="<?= BASE_PATH ?>/modules/reports/donor_report.php">
                            <i class="bi bi-person-lines-fill me-2"></i>Donor Report</a></li>
                        <li><a class="nav-link small" href="<?= BASE_PATH ?>/modules/reports/rent_report.php">
                            <i class="bi bi-house-check me-2"></i>Rent Report</a></li>
                        <li><a class="nav-link small" href="<?= BASE_PATH ?>/modules/reports/salary_report.php">
                            <i class="bi bi-file-person me-2"></i>Salary Report</a></li>
                        <li><a class="nav-link small" href="<?= BASE_PATH ?>/modules/reports/trial_balance.php">
                            <i class="bi bi-balance-scale me-2"></i>Trial Balance</a></li>
                        <li><a class="nav-link small" href="<?= BASE_PATH ?>/modules/reports/income_expenditure.php">
                            <i class="bi bi-file-earmark-bar-graph me-2"></i>Income & Expenditure</a></li>
                        <li><a class="nav-link small" href="<?= BASE_PATH ?>/modules/reports/balance_sheet.php">
                            <i class="bi bi-file-spreadsheet me-2"></i>Balance Sheet</a></li>
                        <li><a class="nav-link small" href="<?= BASE_PATH ?>/modules/reports/audit_report.php">
                            <i class="bi bi-shield-check me-2"></i>Audit Report</a></li>
                    </ul>
                </div>
            </li>

            <!-- Admin -->
            <?php if (isAdmin() || currentUserRole() == ROLE_COMMITTEE): ?>
            <li class="nav-item mt-2">
                <div class="nav-link text-white-50 text-uppercase small px-2">Administration</div>
            </li>
            <?php endif; ?>

            <?php if (isAdmin()): ?>
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-toggle="collapse" href="#adminMenu">
                    <i class="bi bi-gear me-2"></i> Admin
                    <i class="bi bi-chevron-down ms-auto small"></i>
                </a>
                <div class="collapse <?= isActiveSection(['admin'], $currentDir) ?>" id="adminMenu">
                    <ul class="nav flex-column ms-3">
                        <li><a class="nav-link small" href="<?= BASE_PATH ?>/modules/admin/users.php">
                            <i class="bi bi-people-fill me-2"></i>User Management</a></li>
                        <li><a class="nav-link small" href="<?= BASE_PATH ?>/modules/admin/masjid_profile.php">
                            <i class="bi bi-building-fill me-2"></i>Masjid Profile</a></li>
                        <li><a class="nav-link small" href="<?= BASE_PATH ?>/modules/admin/financial_years.php">
                            <i class="bi bi-calendar-range me-2"></i>Financial Years</a></li>
                        <li><a class="nav-link small" href="<?= BASE_PATH ?>/modules/admin/settings.php">
                            <i class="bi bi-sliders me-2"></i>Settings</a></li>
                        <li><a class="nav-link small <?= isActiveMenu(['sms_settings.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/admin/sms_settings.php">
                            <i class="bi bi-chat-dots me-2"></i>SMS Settings</a></li>
                        <li><a class="nav-link small <?= isActiveMenu(['qr_settings.php'], $currentPage) ?>"
                               href="<?= BASE_PATH ?>/modules/admin/qr_settings.php">
                            <i class="bi bi-qr-code me-2 text-success"></i>QR & UPI</a></li>
                        <li><a class="nav-link small" href="<?= BASE_PATH ?>/public/qr_donation.php" target="_blank">
                            <i class="bi bi-box-arrow-up-right me-2 text-success"></i>QR Donation Page</a></li>
                        <li><a class="nav-link small" href="<?= BASE_PATH ?>/modules/admin/audit_logs.php">
                            <i class="bi bi-clock-history me-2"></i>Audit Logs</a></li>
                    </ul>
                </div>
            </li>
            <?php endif; ?>

        </ul>
    </div>
</div>
