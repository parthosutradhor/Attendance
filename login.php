<?php
declare(strict_types=1);

require_once __DIR__ . '/security_headers.php'; 
require_once __DIR__ . '/session_bootstrap.php';
secure_session_start();

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/settings_lib.php";
header("Content-Type: application/json");

$settings = settings_load();

$data = json_decode(file_get_contents("php://input"), true);
$token = $data["credential"] ?? "";

if (!$token) {
  http_response_code(400);
  echo json_encode(["error" => "Missing token"]);
  exit;
}

require_once __DIR__ . '/rate_limit.php';
[$ok, $retryAfter] = rate_limit_allow('google_login', 30, 300);
if (!$ok) {
  http_response_code(429);
  header('Retry-After: ' . $retryAfter);
  echo json_encode(["error" => "Too many requests. Try again in {$retryAfter} seconds."]);
  exit;
}

// Verify token using Google's tokeninfo endpoint (simple raw PHP approach)
$verifyUrl = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($token);
$response = @file_get_contents($verifyUrl);

if (!$response) {
  http_response_code(401);
  echo json_encode(["error" => "Invalid token"]);
  exit;
}

$info = json_decode($response, true);

// Check audience (must match your client ID)
if (($info["aud"] ?? "") !== GOOGLE_CLIENT_ID) {
  http_response_code(401);
  echo json_encode(["error" => "Client ID mismatch (audience mismatch)"]);
  exit;
}

$email = strtolower(trim($info["email"] ?? ""));
$name  = trim($info["name"] ?? "");

if (!$email) {
  http_response_code(401);
  echo json_encode(["error" => "No email found in Google token"]);
  exit;
}

// Enforce email policy from settings.json
if (!email_is_allowed($email, $settings)) {
  http_response_code(403);
  $mode = ($settings['email_mode'] ?? 'domains') === 'all_gmail' ? 'all_gmail' : 'domains';
  $msg = ($mode === 'all_gmail')
    ? 'Only @gmail.com accounts are allowed right now.'
    : 'Use your official BRACU Google account to access the portal.';
  echo json_encode(["error" => $msg]);
  exit;
}

session_login_regenerate();

$_SESSION["user"] = [
  "email" => $email,
  "name"  => $name
];

echo json_encode(["ok" => true]);
