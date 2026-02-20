<?php
declare(strict_types=1);

require_once __DIR__ . '/security_headers.php'; 
require_once __DIR__ . '/session_bootstrap.php';
secure_session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/settings_lib.php';

require_once __DIR__ . '/admin_helpers.php';

require_admin();

$err = '';
$ok  = '';

$settings = settings_load();
$stored = strtolower(trim((string)($settings['admin_password_sha1'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $current = (string)($_POST['current_password'] ?? '');
    $new1    = (string)($_POST['new_password'] ?? '');
    $new2    = (string)($_POST['new_password2'] ?? '');

    if ($stored === '') {
        $err = 'Admin password is not configured in settings.json.';
    } elseif ($current === '' || $new1 === '' || $new2 === '') {
        $err = 'All fields are required.';
    } elseif (!hash_equals($stored, sha1($current))) {
        $err = 'Current password is incorrect.';
    } elseif ($new1 !== $new2) {
        $err = 'New passwords do not match.';
    } elseif (strlen($new1) < 8) {
        $err = 'New password must be at least 8 characters.';
    } else {
        $settings['admin_password_sha1'] = sha1($new1);

        if (!settings_save($settings)) {
            $err = 'Failed to save settings.json (permission issue).';
        } else {
            $ok = 'Password changed successfully.';
            unset($_SESSION['csrf_admin']); // rotate CSRF
        }
    }
}

$token = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Change Admin Password</title>
  <style>
    body { font-family: system-ui, Arial; margin: 32px; max-width: 720px; }
    .box { padding: 16px; border: 1px solid #ddd; border-radius: 12px; }
    label { display:block; margin-top: 12px; }
    input { width: 96%; padding: 10px; margin-top: 6px; }
    button { margin-top: 16px; padding: 10px 14px; cursor: pointer; }
    .err { background:#ffecec; border:1px solid #ffb2b2; padding:10px; border-radius:10px; }
    .ok  { background:#ecffef; border:1px solid #9ae6a0; padding:10px; border-radius:10px; }
    a { text-decoration:none; }
  </style>
</head>
<body>
  <h2>Change Admin Password</h2>
  <p><a href="admin.php">← Back to Admin</a></p>

  <?php if ($err !== ''): ?><div class="err"><?=h($err)?></div><?php endif; ?>
  <?php if ($ok  !== ''): ?><div class="ok"><?=h($ok)?></div><?php endif; ?>

  <div class="box">
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?=h($token)?>">

      <label>Current password</label>
      <input type="password" name="current_password" required>

      <label>New password</label>
      <input type="password" name="new_password" required>

      <label>Confirm new password</label>
      <input type="password" name="new_password2" required>

      <button type="submit">Update Password</button>
    </form>
  </div>
</body>
</html>
