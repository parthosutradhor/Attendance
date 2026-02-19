<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . "/config.php";
header("Content-Type: application/json");

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

// Restrict domain: bracu.ac.bd or g.bracu.ac.bd
$domain = substr(strrchr($email, "@") ?: "", 1);
if (!$domain || !in_array($domain, ALLOWED_EMAIL_DOMAINS, true)) {
  http_response_code(403);
  echo json_encode(["error" => "Only @g.bracu.ac.bd allowed"]);
  exit;
}

$_SESSION["user"] = [
  "email" => $email,
  "name"  => $name
];

echo json_encode(["ok" => true]);
