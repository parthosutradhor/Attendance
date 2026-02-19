<?php
declare(strict_types=1);
session_start();
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
    : 'Your email domain is not allowed.';
  echo json_encode(["error" => $msg]);
  exit;
}

$_SESSION["user"] = [
  "email" => $email,
  "name"  => $name
];

echo json_encode(["ok" => true]);
