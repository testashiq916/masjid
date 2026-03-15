<?php
// ============================================================
// Authentication Middleware
// ============================================================

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__) . '/helpers/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Check if user is logged in
function requireLogin(): void {
    if (!isset($_SESSION['user']) || !isset($_SESSION['login_time'])) {
        setFlash('warning', 'Please login to continue.');
        redirect(BASE_PATH . '/login.php');
    }

    // Check session timeout
    if ((time() - $_SESSION['login_time']) > SESSION_TIMEOUT) {
        session_destroy();
        redirect(BASE_PATH . '/login.php?timeout=1');
    }

    // Refresh last activity
    $_SESSION['login_time'] = time();
}

// Check role access
function requireRole(array $allowedRoles): void {
    requireLogin();
    if (!in_array($_SESSION['user']['role_id'], $allowedRoles)) {
        setFlash('danger', 'You do not have permission to access this page.');
        redirect(BASE_PATH . '/dashboard.php');
    }
}

// Get current user
function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function currentUserId(): int {
    return $_SESSION['user']['id'] ?? 0;
}

function currentUserRole(): int {
    return $_SESSION['user']['role_id'] ?? 0;
}

function isAdmin(): bool {
    return currentUserRole() === ROLE_ADMIN;
}
