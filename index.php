<?php
declare(strict_types=1);

require_once __DIR__ . '/security_headers.php'; 
require_once __DIR__ . '/settings_lib.php';

// Load admin-controlled settings
$settings = settings_load();
$user_ip  = get_real_ip();

/* ===============================
   Production UI (White + Bootstrap-ish Blue)
   =============================== */
$ui_css = <<<CSS
:root{
  --bg:#f6f8fb;
  --card:#ffffff;
  --text:#0f172a;
  --muted:#64748b;
  --border:#e5e7eb;

  --primary:#0d6efd;
  --primary-hover:#0b5ed7;

  --danger:#dc3545;
  --danger-bg:#f8d7da;

  --success:#198754;
  --success-bg:#d1e7dd;

  --shadow: 0 18px 45px rgba(15,23,42,.10);
  --radius: 14px;
}

*{ box-sizing:border-box; }
html,body{ height:100%; }
body{
  margin:0;
  font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
  background: var(--bg);
  color: var(--text);
}

.container{
  min-height:100%;
  display:flex;
  align-items:center;
  justify-content:center;
  padding: 28px 14px;
}

.card{
  width: min(760px, 100%);
  background: var(--card);
  border:1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow:hidden;
}

/* ===============================
   ✅ PROFESSIONAL HEADER
   =============================== */
.header{
  padding: 16px 22px;
  border-bottom: 1px solid var(--border);
  background:
    radial-gradient(900px 180px at 10% 0%, rgba(13,110,253,.10), transparent 55%),
    radial-gradient(700px 160px at 90% 20%, rgba(13,110,253,.06), transparent 55%),
    linear-gradient(180deg, #ffffff, #fbfdff);
}

.brand-row{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
}

.brand-title{
  display:flex;
  flex-direction:column;
  gap:3px;
  min-width: 0;
}

.brand-title h1{
  margin:0;
  font-size: 18px;
  font-weight: 950;
  letter-spacing: .2px;
  line-height: 1.15;
}

.brand-title .subtitle{
  margin:0;
  font-size: 12.8px;
  color: var(--muted);
  font-weight: 700;
}

/* Right side pill date */
.badge{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding: 7px 10px;
  border-radius: 999px;
  border:1px solid rgba(13,110,253,.22);
  background: rgba(13,110,253,.10);
  color: #0b5ed7;
  font-weight: 900;
  font-size: 12.5px;
  white-space:nowrap;
}
.badge .dot{
  width:8px;
  height:8px;
  border-radius:999px;
  background: var(--primary);
}

/* Logged-in strip */
.user-row{
  margin-top: 10px;
  display:flex;
  align-items:center;
  justify-content:flex-end;
  gap:10px;
  flex-wrap:wrap;
}

.user-chip{
  display:inline-flex;
  align-items:center;
  gap:10px;
  padding: 7px 10px;
  border: 1px solid var(--border);
  border-radius: 999px;
  background: #fff;
  font-size: 12.8px;
  color: var(--muted);
  font-weight: 750;
  max-width: 100%;
}
.user-chip .email{
  color: var(--text);
  font-weight: 850;
  overflow:hidden;
  text-overflow:ellipsis;
  white-space:nowrap;
  max-width: 320px;
}
@media (max-width: 640px){
  .user-chip .email{ max-width: 210px; }
  .badge{
    align-self: center;   /* center the date */
  }
}

@media (max-width: 640px){
  .badge{
    align-self: center;   /* center the date */
  }
}

.link{
  color: var(--primary);
  text-decoration:none;
  font-weight: 900;
}
.link:hover{ text-decoration: underline; }

/* Mobile header layout:
   - title centered
   - date on next line right aligned
*/
@media (max-width: 640px){
  .brand-row{
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
  }
  .brand-title{
    align-items: center;
    text-align: center;
  }
  .badge{
    align-self: flex-end;
  }
  .user-row{
    justify-content: center;
  }
}

/* ---- content ---- */
.content{
  padding: 18px 22px 20px;
}

.notice{
  border-radius: 12px;
  padding: 12px 14px;
  border: 1px solid var(--border);
  margin-bottom: 14px;
  font-weight: 750;
}
.notice.success{
  background: var(--success-bg);
  border-color: rgba(25,135,84,.35);
}
.notice.error{
  background: var(--danger-bg);
  border-color: rgba(220,53,69,.35);
}
.notice .sub{
  margin-top: 4px;
  color: var(--muted);
  font-weight: 650;
  font-size: 13px;
}

.panel{
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 16px;
  background: #fff;
}

.form-grid{
  display:grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
@media (max-width: 640px){
  .form-grid{ grid-template-columns: 1fr; }
}

label{
  display:block;
  margin: 10px 0 6px;
  font-size: 13px;
  color: var(--muted);
  font-weight: 800;
}

input, select{
  width:100%;
  padding: 11px 12px;
  border-radius: 12px;
  border:1px solid var(--border);
  background:#fff;
  color: var(--text);
  outline:none;
  transition: .12s ease;
}
input::placeholder{ color:#94a3b8; }
input:focus, select:focus{
  border-color: rgba(13,110,253,.55);
  box-shadow: 0 0 0 4px rgba(13,110,253,.12);
}
input[readonly]{
  background:#f8fafc;
  color:#334155;
}

.actions{
  margin-top: 14px;
  display:flex;
  align-items:center;
  justify-content:flex-end;
  gap: 12px;
  flex-wrap:wrap;
}

.btn{
  border:none;
  cursor:pointer;
  padding: 11px 14px;
  border-radius: 12px;
  font-weight: 950;
  background: var(--primary);
  color:#fff;
  transition: .12s ease;
}
.btn:hover{ background: var(--primary-hover); }
.btn:active{ transform: translateY(1px); }

.footer{
  padding: 14px 22px;
  border-top: 1px solid var(--border);
  color: var(--muted);
  font-size: 12.5px;
  display:flex;
  justify-content:center;
  align-items:center;
  text-align:center;
}
CSS;

/* ---------- Block if not allowed by settings.json (whitelist / allow-all / ASN allowlist) ---------- */
if (!network_is_allowed($user_ip, $settings)) {
    $safe_ip = htmlspecialchars($user_ip, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Access Denied</title>
  <style><?= $ui_css ?></style>
</head>
<body>
  <div class="container">
    <div class="card" role="alert" aria-live="polite">
      <div class="header">
        <div class="brand-row">
          <div class="brand-title">
            <h1>Access Denied</h1>
            <p class="subtitle">BRACU campus network required</p>
          </div>
        </div>
      </div>

      <div class="content">
        <div class="notice error">
          You are not allowed to access this portal from your current network.
          <div class="sub">Your IP: <strong><?= $safe_ip ?></strong></div>
        </div>

        <div class="panel">
          <div style="font-weight:950; margin-bottom:6px;">What to do</div>
          <div style="color:var(--muted); font-weight:650; font-size:13px; line-height:1.6;">
            Connect to BRACU WiFi and refresh this page. If you are using a VPN/proxy, disable it and try again.
            If you believe this is a mistake, contact the course instructor/admin.
          </div>
        </div>
      </div>

      <div class="footer">
        Developed by <strong style="color:var(--text); margin-left:6px;">Partho Sutra Dhor</strong>
      </div>
    </div>
  </div>
</body>
</html>
<?php
exit();
}

/* ===============================
   ATTENDANCE SYSTEM STARTS HERE
   =============================== */

require_once __DIR__ . '/session_bootstrap.php';
secure_session_start();                 // ✅ hardened session start
require_once __DIR__ . "/config.php";

$user = $_SESSION["user"] ?? null;

// Lists for dropdowns (from settings.json)
$course_codes = $settings['course_codes'] ?? [];
$sections     = $settings['sections'] ?? [];
if (!is_array($course_codes)) $course_codes = [];
if (!is_array($sections)) $sections = [];

/* ---------- CSRF ---------- */
if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["csrf"];

/* ---------- Date ---------- */
$dt = new DateTime("now", new DateTimeZone("Asia/Dhaka"));
$today_badge = $dt->format("j-n-Y");

/* ---------- Status Messages ---------- */
$status = $_GET["status"] ?? "";
$msg    = $_GET["msg"] ?? "";
$ts     = $_GET["ts"] ?? "";
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>BRACU Attendance</title>

  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <style><?= $ui_css ?></style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="header">
        <div class="brand-row">
          <div class="brand-title">
            <h1>BRACU Attendance Portal</h1>
            <p class="subtitle">Use your BRACU Google account</p>
          </div>

          <div class="badge" title="Today's date" style="align-self: center;">
            <span class="dot" aria-hidden="true"></span>
            Today: <?= htmlspecialchars($today_badge) ?>
          </div>
        </div>

        <?php if ($user): ?>
          <div class="user-row">
            <span class="user-chip">
              <span class="email"><?= htmlspecialchars($user["email"]) ?></span>
              <span style="opacity:.5;">|</span>
              <a class="link" href="logout.php">Logout</a>
            </span>
          </div>
        <?php endif; ?>
      </div>

      <div class="content">

        <?php if ($status === "success"): ?>
          <div class="notice success">
            Attendance submitted successfully.
            <?php if ($ts): ?><div class="sub"><?= htmlspecialchars($ts) ?></div><?php endif; ?>
          </div>
        <?php elseif ($status === "error"): ?>
          <div class="notice error">
            <?= htmlspecialchars($msg ?: "Submission failed.") ?>
          </div>
        <?php endif; ?>

        <?php if (!$user): ?>

          <div class="panel" style="text-align:center;">
            <div style="font-weight:950; font-size:16px; margin-bottom:6px;">Sign in to continue</div>
            <div style="color:var(--muted); font-weight:650; font-size:13px; margin-bottom:14px;">
              Use your BRAC Google account to access the portal.
            </div>

            <div id="g_id_onload"
                 data-client_id="<?= htmlspecialchars(GOOGLE_CLIENT_ID) ?>"
                 data-callback="onGoogleCredential"
                 data-auto_prompt="false"></div>

            <div class="g_id_signin"
                 data-type="standard"
                 data-size="large"
                 data-theme="outline"
                 data-text="signin_with"
                 data-shape="pill"></div>
          </div>

          <script>
          async function onGoogleCredential(response){
            const r = await fetch("login.php", {
              method:"POST",
              headers:{"Content-Type":"application/json"},
              body: JSON.stringify({credential: response.credential})
            });

            let out = {};
            try { out = await r.json(); } catch(e) {}

            if(r.ok){
              location.reload();
            } else {
              alert(out.error || "Login failed");
            }
          }
          </script>

        <?php else: ?>

          <div class="panel">
            <form method="POST" action="submit.php" style="margin:0;">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"/>

              <div class="form-grid">
                <div>
                  <label>Course</label>
                  <select name="course_code" required>
                    <option value="" disabled selected>Select course</option>
                    <?php foreach ($course_codes as $c): $c = trim((string)$c); if ($c==='') continue; ?>
                      <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div>
                  <label>Section</label>
                  <select name="section" required>
                    <option value="" disabled selected>Select section</option>
                    <?php foreach ($sections as $s): $s = trim((string)$s); if ($s==='') continue; ?>
                      <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <label>Name</label>
              <input value="<?= htmlspecialchars($user["name"]) ?>" readonly>

              <label>Email</label>
              <input value="<?= htmlspecialchars($user["email"]) ?>" readonly>

              <label>Student ID</label>
              <input name="student_id" required pattern="[0-9]{8,}" inputmode="numeric" placeholder="Student ID">

              <div class="actions">
                <button class="btn" type="submit">Submit Attendance</button>
              </div>
            </form>
          </div>

        <?php endif; ?>

      </div>

      <div class="footer">
        Developed by <strong style="color:var(--text); margin-left:6px;">Partho Sutra Dhor</strong>
      </div>
    </div>
  </div>
</body>
</html>
