<?php
/**
 * Language / i18n Helper
 * Supports: English (en), Malayalam (ml)
 */

$GLOBALS['_LANG'] = [];

function loadLang(): void {
    $lang = $_SESSION['lang'] ?? 'en';
    $file = __DIR__ . '/../../lang/' . preg_replace('/[^a-z]/', '', $lang) . '.php';
    if (!file_exists($file)) {
        $file = __DIR__ . '/../../lang/en.php';
    }
    $GLOBALS['_LANG'] = require $file;
}

/**
 * Translate a key, with optional :placeholder replacements
 */
function __(string $key, array $replace = []): string {
    $str = $GLOBALS['_LANG'][$key] ?? $key;
    foreach ($replace as $k => $v) {
        $str = str_replace(':' . $k, $v, $str);
    }
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Raw translation (no htmlspecialchars)
 */
function _r(string $key, array $replace = []): string {
    $str = $GLOBALS['_LANG'][$key] ?? $key;
    foreach ($replace as $k => $v) {
        $str = str_replace(':' . $k, $v, $str);
    }
    return $str;
}

function setLanguage(string $lang): void {
    $allowed = ['en', 'ml'];
    if (!in_array($lang, $allowed)) $lang = 'en';
    $_SESSION['lang'] = $lang;
    if (!empty($_SESSION['user']['id'])) {
        try {
            db()->prepare("UPDATE users SET language = ? WHERE id = ?")
                ->execute([$lang, (int)$_SESSION['user']['id']]);
        } catch (Exception $e) {}
    }
}

function currentLang(): string {
    return $_SESSION['lang'] ?? 'en';
}

loadLang();
