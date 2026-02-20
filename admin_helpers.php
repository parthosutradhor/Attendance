<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Admin CSRF helpers.
 * Uses the same session key as before: $_SESSION['csrf_admin'].
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_admin'])) {
        $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
    }
    return (string)$_SESSION['csrf_admin'];
}

function require_csrf(): void {
    $t = (string)($_POST['csrf'] ?? '');
    if ($t === '' || empty($_SESSION['csrf_admin']) || !hash_equals((string)$_SESSION['csrf_admin'], $t)) {
        http_response_code(403);
        die('CSRF failed');
    }
}

/**
 * Require admin auth (set by admin.php on successful login).
 */
function require_admin(): void {
    if (empty($_SESSION['admin_authed'])) {
        header('Location: admin.php');
        exit;
    }
}
