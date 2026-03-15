<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top shadow-sm" id="mainNavbar">
    <div class="container-fluid">
        <button class="btn btn-primary me-2" id="sidebarToggle" type="button">
            <i class="bi bi-list fs-5"></i>
        </button>
        <a class="navbar-brand fw-bold" href="<?= BASE_PATH ?>/dashboard.php">
            <i class="bi bi-building me-1"></i>
            <?= htmlspecialchars($_masjidProfile['masjid_name'] ?? APP_NAME) ?>
        </a>

        <div class="d-flex align-items-center ms-auto gap-2">
            <!-- Notifications -->
            <div class="dropdown">
                <button class="btn btn-primary position-relative" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-bell fs-5"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-count d-none">0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow" style="width:320px;">
                    <div class="dropdown-header d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Notifications</span>
                    </div>
                    <div class="dropdown-divider"></div>
                    <div id="notificationList" class="px-2 py-1 text-muted small">Loading...</div>
                </div>
            </div>

            <!-- User menu -->
            <div class="dropdown">
                <button class="btn btn-primary d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle fs-5"></i>
                    <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['user']['name'] ?? 'User') ?></span>
                    <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><h6 class="dropdown-header"><?= htmlspecialchars($_SESSION['user']['name'] ?? '') ?></h6></li>
                    <li><span class="dropdown-item-text text-muted small"><?= htmlspecialchars($_SESSION['user']['role_name'] ?? '') ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= BASE_PATH ?>/pages/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_PATH ?>/pages/change_password.php"><i class="bi bi-key me-2"></i>Change Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_PATH ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
