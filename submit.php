<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/sheets.php";

if (!isset($_SESSION["user"])) {
  header("Location: index.php?status=error&msg=" . urlencode("Not logged in."));
  exit;
}
if (($_POST["csrf"] ?? "") !== ($_SESSION["csrf"] ?? "")) {
  header("Location: index.php?status=error&msg=" . urlencode("Security check failed (CSRF)."));
  exit;
}

// From Google session
$name  = $_SESSION["user"]["name"]  ?? "";
$email = $_SESSION["user"]["email"] ?? "";
if (!$email) {
  header("Location: index.php?status=error&msg=" . urlencode("Session email missing."));
  exit;
}

$ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";

// Student inputs
$course     = trim($_POST["course_code"] ?? "");
$section    = trim($_POST["section"] ?? "");
$student_id = trim($_POST["student_id"] ?? "");

if ($course === "" || $section === "" || $student_id === "") {
  header("Location: index.php?status=error&msg=" . urlencode("Please fill all required fields."));
  exit;
}

// Optional: enforce 8-digit ID format on server as well
if (!preg_match('/^[0-9]{8,}$/', $student_id)) {
  header("Location: index.php?status=error&msg=" . urlencode("Student ID must be at least 8 digits."));
  exit;
}

// Timestamp (Dhaka)
$dt = new DateTime("now", new DateTimeZone("Asia/Dhaka"));
$timestamp = $dt->format("Y-m-d H:i:s");
$today = $dt->format("Y-m-d");

// Row: Course, Section, Name, ID, Email, Timestamp, IP
$row = [$course, $section, $name, $student_id, $email, $timestamp, $ip];

try {
  append_row($row, $email, $today);

  // Redirect to success page with summary data
  $query = http_build_query([
    "name" => $name,
    "id" => $student_id,
    "course" => $course,
    "section" => $section,
    "email" => $email,
    "ts" => $timestamp
  ]);

  header("Location: success.php?$query");
  exit;

} catch (Throwable $e) {
  header("Location: index.php?status=error&msg=" . urlencode($e->getMessage()));
  exit;
}

