<?php
// Standard authenticated page layout wrapper
// Usage: include this after setting $pageTitle, then output your page content
require_once dirname(__DIR__) . '/app/middleware/auth_check.php';
requireLogin();
require_once dirname(__DIR__) . '/templates/header.php';
?>
<div class="wrapper d-flex">
    <?php require_once dirname(__DIR__) . '/templates/sidebar.php'; ?>
    <div class="main-content flex-grow-1">
        <?php require_once dirname(__DIR__) . '/templates/navbar.php'; ?>
        <div class="content-area p-3 p-md-4" style="margin-top:56px;">
            <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
