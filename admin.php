<?php
declare(strict_types=1);

require_once __DIR__ . '/security_headers.php'; 
require_once __DIR__ . '/session_bootstrap.php';
secure_session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/settings_lib.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function csrf_token(): string {
    if (empty($_SESSION['csrf_admin'])) {
        $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_admin'];
}

function require_csrf(): void {
    $t = (string)($_POST['csrf'] ?? '');
    if ($t === '' || empty($_SESSION['csrf_admin']) || !hash_equals($_SESSION['csrf_admin'], $t)) {
        http_response_code(403);
        die('CSRF failed');
    }
}

function settings_path(): string {
    return defined('SETTINGS_FILE') ? (string)SETTINGS_FILE : (__DIR__ . '/settings.json');
}

function save_settings(array $s): bool {
    $s['updated_at'] = date('c');
    $json = json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    return file_put_contents(settings_path(), $json) !== false;
}

function normalize_list(array $list): array {
    $out = [];
    foreach ($list as $v) {
        $v = trim((string)$v);
        if ($v === '') continue;
        $out[] = $v;
    }
    $out = array_values(array_unique($out));
    sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

// ---- Auth ----
$flash = '';

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_authed']);
    header('Location: admin.php');
    exit;
}

if (($_POST['action'] ?? '') === 'login') {
    require_csrf();
	
    require_once __DIR__ . '/rate_limit.php';
    [$ok, $retryAfter, $remaining] = rate_limit_allow('admin_login', 8, 300); // 8 tries / 5 min
    if (!$ok) {
        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        $flash = "Too many attempts. Try again in {$retryAfter} seconds.";
    } else {
        // Continue normal login check
        $pw = (string)($_POST['password'] ?? '');

        $auth_settings = settings_load();
        $stored = strtolower(trim((string)($auth_settings['admin_password_sha1'] ?? '')));

        if ($stored === '') {
            $flash = 'Admin password is not configured. Set admin_password_sha1 in settings.json.';
        } elseif (hash_equals($stored, sha1($pw))) {
            session_regenerate_id(true);     // ✅ recommended here too
            $_SESSION['admin_authed'] = true;
            header('Location: admin.php');
            exit;
        } else {
            rate_limit_sleep_on_fail(500);   // ✅ slow down brute-force
            $flash = 'Wrong password.';
        }
    }
}


$authed = !empty($_SESSION['admin_authed']);

// ---- Load current settings ----
$settings = settings_load();

// ---- Update operations ----
if ($authed && !empty($_POST['action']) && $_POST['action'] !== 'login') {
    require_csrf();

    $action = (string)$_POST['action'];

    if ($action === 'save_toggles') {
        $settings['allow_all_ip'] = !empty($_POST['allow_all_ip']);
        $mode = (string)($_POST['email_mode'] ?? 'domains');
        $settings['email_mode'] = ($mode === 'all_gmail') ? 'all_gmail' : 'domains';

        $flash = save_settings($settings) ? 'Saved.' : 'Failed to save settings.json (permission issue).';
    }

    if ($action === 'add_item') {
        $key = (string)($_POST['list_key'] ?? '');
        $val = trim((string)($_POST['value'] ?? ''));
        $allowed = ['asn_allowlist','ip_whitelist','domain_allowlist','course_codes','sections'];

        if (!in_array($key, $allowed, true)) {
            $flash = 'Invalid list.';
        } else {
            if (!isset($settings[$key]) || !is_array($settings[$key])) $settings[$key] = [];
            $settings[$key][] = $val;
            $settings[$key] = normalize_list($settings[$key]);
            $flash = save_settings($settings) ? 'Added.' : 'Failed to save settings.json.';
        }
    }

    if ($action === 'remove_item') {
        $key = (string)($_POST['list_key'] ?? '');
        $val = (string)($_POST['value'] ?? '');
        $allowed = ['asn_allowlist','ip_whitelist','domain_allowlist','course_codes','sections'];

        if (!in_array($key, $allowed, true)) {
            $flash = 'Invalid list.';
        } else {
            $settings[$key] = array_values(array_filter($settings[$key] ?? [], fn($x) => (string)$x !== $val));
            $settings[$key] = normalize_list($settings[$key] ?? []);
            $flash = save_settings($settings) ? 'Removed.' : 'Failed to save settings.json.';
        }
    }

    // Reload after save
    $settings = settings_load();
}

$token = csrf_token();

function render_list(string $title, string $key, array $items, string $placeholder, string $token): void {
    ?>
    <div class="block">
        <h3><?=h($title)?></h3>

        <form method="post" class="row">
            <input type="hidden" name="csrf" value="<?=h($token)?>">
            <input type="hidden" name="action" value="add_item">
            <input type="hidden" name="list_key" value="<?=h($key)?>">
            <input type="text" name="value" placeholder="<?=h($placeholder)?>" required>
            <button type="submit">Add</button>
        </form>

        <?php if (empty($items)): ?>
            <div class="muted">No items yet.</div>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($items as $v): $v = (string)$v; ?>
                    <li>
                        <span class="pill"><?=h($v)?></span>
                        <form method="post" class="inline">
                            <input type="hidden" name="csrf" value="<?=h($token)?>">
                            <input type="hidden" name="action" value="remove_item">
                            <input type="hidden" name="list_key" value="<?=h($key)?>">
                            <input type="hidden" name="value" value="<?=h($v)?>">
                            <button type="submit" class="danger">Remove</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Settings</title>
<style>
:root{
  --bg: #f5f9ff;
  --card: #ffffff;
  --text: #0f172a;
  --muted: #64748b;

  --primary: #1d9bf0;          /* twitter blue-ish */
  --primary-600: #1786cf;
  --primary-50: #e8f5ff;

  --border: #e2e8f0;
  --border-2: #d7e3f4;

  --danger: #ef4444;
  --danger-50: #ffeaea;

  --shadow: 0 10px 30px rgba(2, 8, 23, .08);
  --shadow-sm: 0 6px 18px rgba(2, 8, 23, .06);
  --radius: 16px;
}

*{ box-sizing:border-box; }

body{
  font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  margin: 0;
  background: radial-gradient(1200px 700px at 20% 0%, #e9f4ff 0%, rgba(233,244,255,0) 55%),
              radial-gradient(1200px 700px at 100% 10%, #eafcff 0%, rgba(234,252,255,0) 55%),
              var(--bg);
  color: var(--text);
}

.container{
  max-width: 1080px;
  margin: 28px auto;
  padding: 0 18px;
}

header{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap: 14px;
  flex-wrap:wrap;
  margin-bottom: 18px;
}

.brand{
  display:flex;
  align-items:center;
  gap: 12px;
}

.logo{
  width: 44px;
  height: 44px;
  border-radius: 14px;
  background: linear-gradient(135deg, var(--primary), #55d6ff);
  box-shadow: var(--shadow-sm);
}

h2{
  margin:0;
  font-size: 22px;
  letter-spacing: .2px;
}

.subtitle{
  margin-top: 4px;
  color: var(--muted);
  font-size: 13px;
}

.muted{ color: var(--muted); font-size: 13px; }
a{ color: var(--primary); text-decoration: none; }
a:hover{ text-decoration: underline; }

.block{
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 16px;
  box-shadow: var(--shadow-sm);
  margin-bottom: 16px;
}

.block h3{
  margin: 0 0 10px 0;
  font-size: 16px;
}

.flash{
  padding: 12px 14px;
  border: 1px solid var(--border-2);
  border-radius: 14px;
  background: linear-gradient(0deg, #ffffff, var(--primary-50));
  box-shadow: var(--shadow-sm);
  margin: 12px 0 16px 0;
}

.grid{
  display:grid;
  grid-template-columns: 1fr;
  gap: 14px;
}
@media(min-width: 900px){
  .grid{ grid-template-columns: 1fr 1fr; }
}

/* Inputs */
label{
  display:block;
  margin: 10px 0 6px 0;
  font-size: 13px;
  color: var(--muted);
}

input[type="text"],
input[type="password"],
select{
  width: 100%;
  padding: 11px 12px;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: #fff;
  outline: none;
  transition: border-color .15s ease, box-shadow .15s ease, transform .05s ease;
}

input[type="text"]:focus,
input[type="password"]:focus,
select:focus{
  border-color: rgba(29,155,240,.55);
  box-shadow: 0 0 0 4px rgba(29,155,240,.15);
}

select{ cursor:pointer; }

/* Buttons */
button{
  padding: 11px 14px;
  border: 0;
  border-radius: 14px;
  cursor: pointer;
  font-weight: 600;
  transition: transform .06s ease, filter .15s ease, box-shadow .15s ease;
  box-shadow: 0 10px 20px rgba(29,155,240,.18);
  background: var(--primary);
  color: #fff;
}

button:hover{ filter: brightness(0.98); }
button:active{ transform: translateY(1px); }

button.danger{
  background: #fff;
  color: var(--danger);
  border: 1px solid rgba(239,68,68,.35);
  box-shadow: none;
}
button.danger:hover{
  background: var(--danger-50);
}

/* Rows */
.row{
  display:flex;
  gap: 10px;
  align-items:center;
}
.row input[type="text"]{ flex: 1; }

/* List */
.list{
  list-style: none;
  padding: 0;
  margin: 12px 0 0 0;
}
.list li{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap: 10px;
  padding: 10px 0;
  border-top: 1px dashed var(--border);
}

.pill{
  display:inline-flex;
  align-items:center;
  gap: 8px;
  padding: 7px 10px;
  border: 1px solid var(--border);
  border-radius: 999px;
  background: #fff;
  font-size: 13px;
}

/* Core switch row */
.toggles{
  display:flex;
  gap: 26px;              /* ✅ gap between Allow IP and Email policy */
  flex-wrap:wrap;
  align-items:center;
}

.toggle{
  display:flex;
  align-items:center;
  gap: 10px;
  flex-wrap: nowrap;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: #fff;
}

.toggle label{
  margin:0;
  white-space: nowrap;
  color: var(--text);
  font-size: 14px;
}

.toggle input[type="checkbox"]{
  width: 18px;
  height: 18px;
  accent-color: var(--primary);
}

.toggle select{
  width:auto;
  min-width: 220px;
  padding: 10px 12px;
  border-radius: 12px;
}

/* Make Save button align nicely on wrap */
.toggles button{
  box-shadow: 0 10px 22px rgba(29,155,240,.22);
}

/* Login card */
.login-wrap{
  max-width: 520px;
  padding: 18px;
}

/* Small helper line */
.policy-note{
  margin-top: 10px;
  color: var(--muted);
  font-size: 13px;
}

/* Code style */
code{
  background: #f1f5f9;
  border: 1px solid var(--border);
  padding: 2px 6px;
  border-radius: 10px;
  font-size: 12px;
}
</style>
</head>
<body>
<div class="container">

<header>
  <div class="brand">
    <div>
      <h2>Admin Settings</h2>
      <div class="subtitle">File: <code><?=h(settings_path())?></code></div>
    </div>
  </div>

  <div class="muted">
    <?php if ($authed): ?>
      Last update: <?=h((string)($settings['updated_at'] ?? '—'))?>
      • <a href="change_password.php">Change password</a>
      • <a href="?logout=1">Logout</a>
    <?php endif; ?>
  </div>
</header>

<?php if ($flash !== ''): ?>
  <div class="flash"><?=h($flash)?></div>
<?php endif; ?>

<?php if (!$authed): ?>
  <div class="block login-wrap">
    <h3>Login</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=h($token)?>">
      <input type="hidden" name="action" value="login">
      <label>Password</label>
      <input type="password" name="password" autocomplete="current-password" required>
      <button type="submit" style="margin-top: 10px;">Login</button>
    </form>
  </div>
<?php else: ?>

  <div class="block">
    <h3>Core Switches</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=h($token)?>">
      <input type="hidden" name="action" value="save_toggles">

      <div class="toggles">
        <div class="toggle">
          <input id="allow_all_ip" type="checkbox" name="allow_all_ip" value="1" <?=!empty($settings['allow_all_ip']) ? 'checked' : ''?>>
          <label for="allow_all_ip">Allow all IP</label>
        </div>

        <div class="toggle">
          <label for="email_mode">Email policy</label>
          <select id="email_mode" name="email_mode">
            <option value="all_gmail" <?=($settings['email_mode'] ?? '') === 'all_gmail' ? 'selected' : ''?>>Allow all Gmail</option>
            <option value="domains" <?=($settings['email_mode'] ?? '') !== 'all_gmail' ? 'selected' : ''?>>Allow only Whitelisted domains</option>
          </select>
        </div>

        <button type="submit">Save</button>
      </div>

      <div class="policy-note">
        Policy order: <b>IP whitelist</b> → <b>Allow all IP</b> → <b>ASN allowlist</b>.
      </div>
    </form>
  </div>

  <div class="grid">
    <?php
      render_list('ASN Whitelist', 'asn_allowlist', $settings['asn_allowlist'] ?? [], 'Example: AS151981', $token);
      render_list('IP Whitelist', 'ip_whitelist', $settings['ip_whitelist'] ?? [], 'Example: 103.12.34.56', $token);
      render_list('Witelisted Email Domains', 'domain_allowlist', $settings['domain_allowlist'] ?? [], 'Example: g.bracu.ac.bd', $token);
      render_list('Course Codes', 'course_codes', $settings['course_codes'] ?? [], 'Example: MAT215', $token);
      render_list('Sections', 'sections', $settings['sections'] ?? [], 'Example: 01 / A / 14', $token);
    ?>
  </div>

<?php endif; ?>

</div>
</body>
</html>
