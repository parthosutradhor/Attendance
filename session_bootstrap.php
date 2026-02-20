<?php
declare(strict_types=1);

/**
 * Call this BEFORE session_start().
 */
function secure_session_start(): void {
    // Use HTTPS in production. If you are still on http://localhost, keep this false.
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    // PHP 7.3+ supports SameSite via array options
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,     // true only when HTTPS
        'httponly' => true,
        'samesite' => 'Lax',      // 'Strict' may break some flows
    ]);

    // Extra hardening
    ini_set('session.use_strict_mode', '1'); // prevents session fixation
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');

    session_start();
}

/**
 * Call this right after a successful login (user/admin).
 */
function session_login_regenerate(): void {
    session_regenerate_id(true);
}
