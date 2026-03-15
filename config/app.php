<?php
// ============================================================
// Application Configuration
// ============================================================

define('APP_NAME', 'Masjid ERP');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/masjid');
define('APP_ROOT', dirname(__DIR__));
define('BASE_PATH', '/masjid');

// Session settings
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('SESSION_NAME', 'masjid_erp_session');

// Upload settings
define('UPLOAD_PATH', APP_ROOT . '/assets/uploads/');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
