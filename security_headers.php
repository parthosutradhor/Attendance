<?php
declare(strict_types=1);

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

/**
 * CSP that works with Google Identity Services (GSI).
 * If you can remove inline JS, later you can remove 'unsafe-inline'.
 */
$csp = implode('; ', [
  "default-src 'self'",
  "base-uri 'self'",
  "form-action 'self'",
  "frame-ancestors 'none'",
  "object-src 'none'",

  // Google Sign-In scripts
  "script-src 'self' 'unsafe-inline' https://accounts.google.com https://www.gstatic.com",

  // Allow Google’s iframe/popup flow if used
  "frame-src https://accounts.google.com",

  // Token exchange / identity endpoints
  "connect-src 'self' https://accounts.google.com https://oauth2.googleapis.com https://www.googleapis.com",

  // Styles + images (Google profile pics come from lh3.googleusercontent.com)
  "style-src 'self' 'unsafe-inline'",
  "img-src 'self' data: https://lh3.googleusercontent.com https://*.googleusercontent.com",
]);

header('Content-Security-Policy: ' . $csp);
