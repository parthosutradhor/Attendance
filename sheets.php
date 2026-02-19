<?php
declare(strict_types=1);
require_once __DIR__ . "/config.php";

function base64url_encode(string $data): string {
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function curl_post_form(string $url, array $fields): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($fields),
    CURLOPT_HTTPHEADER => ["Content-Type: application/x-www-form-urlencoded"],
    CURLOPT_TIMEOUT => 20
  ]);
  $res = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$code, $res, $err];
}

function curl_get_json(string $url, string $token): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => [
      "Authorization: Bearer $token",
      "Accept: application/json"
    ]
  ]);
  $res = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$code, $res, $err];
}

function curl_post_json(string $url, string $token, array $body): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($body),
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => [
      "Authorization: Bearer $token",
      "Content-Type: application/json"
    ]
  ]);
  $res = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$code, $res, $err];
}

function get_access_token(): string {
  if (!file_exists(SERVICE_ACCOUNT_JSON_PATH)) {
    throw new Exception("service-account.json not found: " . SERVICE_ACCOUNT_JSON_PATH);
  }
  $sa = json_decode(file_get_contents(SERVICE_ACCOUNT_JSON_PATH), true);
  if (!$sa || empty($sa["client_email"]) || empty($sa["private_key"]) || empty($sa["token_uri"])) {
    throw new Exception("Invalid service-account.json content (must be a Service Account KEY json)");
  }
  if (!function_exists("openssl_sign")) throw new Exception("OpenSSL not enabled (openssl_sign missing)");
  if (!function_exists("curl_init")) throw new Exception("cURL not enabled (curl_init missing)");

  $now = time();
  $header = ["alg"=>"RS256","typ"=>"JWT"];
  $claims = [
    "iss" => $sa["client_email"],
    "scope" => "https://www.googleapis.com/auth/spreadsheets",
    "aud" => $sa["token_uri"],
    "iat" => $now,
    "exp" => $now + 3600
  ];

  $jwtBase = base64url_encode(json_encode($header)) . "." . base64url_encode(json_encode($claims));
  $signature = "";
  if (!openssl_sign($jwtBase, $signature, $sa["private_key"], "sha256WithRSAEncryption")) {
    throw new Exception("openssl_sign failed");
  }
  $jwt = $jwtBase . "." . base64url_encode($signature);

  [$code, $res, $err] = curl_post_form($sa["token_uri"], [
    "grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
    "assertion" => $jwt
  ]);
  if ($err) throw new Exception("Token cURL error: $err");
  if ($code !== 200) throw new Exception("Token HTTP $code: $res");

  $data = json_decode($res, true);
  if (empty($data["access_token"])) throw new Exception("No access_token: $res");
  return $data["access_token"];
}

function has_submitted_today(string $token, string $email, string $date): bool {
  // Read only Email+Date columns for efficiency (E and F in our layout)
  // Layout: A Course, B Section, C Name, D ID, E Email, F Date, G IP
  $range = rawurlencode(SHEET_TAB_NAME . "!E:F");
  $url = "https://sheets.googleapis.com/v4/spreadsheets/" . SPREADSHEET_ID . "/values/" . $range;

  [$code, $res, $err] = curl_get_json($url, $token);
  if ($err) throw new Exception("Sheets read cURL error: $err");
  if ($code < 200 || $code >= 300) throw new Exception("Sheets read HTTP $code: $res");

  $data = json_decode($res, true);
  $values = $data["values"] ?? [];

  $email = strtolower(trim($email));
  $date = trim($date);

  // values rows look like: [email, date]
  foreach ($values as $row) {
    $rEmail = strtolower(trim($row[0] ?? ""));
    $rDateTime = trim($row[1] ?? "");
	$rDate = substr($rDateTime, 0, 10); // YYYY-MM-DD

	if ($rEmail === $email && $rDate === $date) {
      return true;
    }
  }
  return false;
}

function append_row(array $row, string $email, string $date): void {
  if (!SPREADSHEET_ID || !SHEET_TAB_NAME) {
    throw new Exception("Missing SPREADSHEET_ID or SHEET_TAB_NAME in config.php");
  }

  $token = get_access_token();

  // ✅ One submission per day per Google email
  if (has_submitted_today($token, $email, $date)) {
    // use 409-like behavior by throwing
    throw new Exception("You have already submitted attendance.");
  }

  // Append 7 columns A:G
  $range = rawurlencode(SHEET_TAB_NAME . "!A:G");
  $url = "https://sheets.googleapis.com/v4/spreadsheets/" . SPREADSHEET_ID .
         "/values/" . $range . ":append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS";

  [$code, $res, $err] = curl_post_json($url, $token, ["values" => [$row]]);

  if ($err) throw new Exception("Sheets append cURL error: $err");
  if ($code < 200 || $code >= 300) throw new Exception("Sheets append HTTP $code: $res");
}
