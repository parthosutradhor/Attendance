<?php
declare(strict_types=1);

require_once __DIR__ . '/security_headers.php'; 
require_once __DIR__ . '/session_bootstrap.php';
secure_session_start();

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/settings_lib.php";
require_once __DIR__ . "/sheets.php";

$settings = settings_load();

// Admin gate: stop accepting responses
if (empty($settings['accepting_responses'])) {
  header('Location: access_denied.php');
  exit;
}


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

$ip = get_real_ip();

// Enforce network policy server-side (no bypass by direct POST)
if (!network_is_allowed($ip, $settings)) {
  header("Location: index.php?status=error&msg=" . urlencode("Access denied: network not allowed."));
  exit;
}

// Enforce email policy server-side
if (!email_is_allowed($email, $settings)) {
  header("Location: index.php?status=error&msg=" . urlencode("Access denied: email not allowed."));
  exit;
}

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

// Enforce course/section allowlists (if admin configured them)
$allowed_courses = $settings['course_codes'] ?? [];
$allowed_sections = $settings['sections'] ?? [];
if (!is_array($allowed_courses)) $allowed_courses = [];
if (!is_array($allowed_sections)) $allowed_sections = [];

if (!form_value_allowed($course, $allowed_courses)) {
  header("Location: index.php?status=error&msg=" . urlencode("Invalid course code."));
  exit;
}
if (!form_value_allowed($section, $allowed_sections)) {
  header("Location: index.php?status=error&msg=" . urlencode("Invalid section."));
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

  // Store summary in session (avoid leaking data in URL)
  $_SESSION['last_submission'] = [
    'name' => $name,
    'id' => $student_id,
    'course' => $course,
    'section' => $section,
    'email' => $email,
    'ts' => $timestamp,
  ];

  header("Location: success.php");
  exit;

} catch (Throwable $e) {
  $status = 'error';
    $msg = $e->getMessage();
    ?>
    <form id="redir" action="index.php" method="post">
      <input type="hidden" name="status" value="<?= htmlspecialchars($status, ENT_QUOTES) ?>">
      <input type="hidden" name="msg" value="<?= htmlspecialchars($msg, ENT_QUOTES) ?>">
    </form>
    <script>document.getElementById('redir').submit();</script>
    <?php
  exit;
}

