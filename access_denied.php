<?php
declare(strict_types=1);

require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/settings_lib.php';

$settings = settings_load();

/*
  Reuse the same “WiFi Access Denied” card design from index.php,
  but with the “responses closed” instructions.
*/
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

.badge{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding: 7px 10px;
  border-radius: 999px;
  border:1px solid rgba(220,53,69,.22);
  background: rgba(220,53,69,.10);
  color: var(--danger);
  font-weight: 900;
  font-size: 12.5px;
  white-space:nowrap;
}
.badge .dot{
  width:8px;
  height:8px;
  border-radius:999px;
  background: var(--danger);
}

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
  border-radius: 12px;
  padding: 12px 14px;
  background:#fff;
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
  text-decoration:none;
  display:inline-flex;
  align-items:center;
  justify-content:center;
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

$course_title = (string)($settings['course_title'] ?? 'Attendance Portal');
$course_sub   = (string)($settings['course_subtitle'] ?? 'Responses are currently closed');

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
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
            <h1><?= h($course_title) ?></h1>
            <p class="subtitle"><?= h($course_sub) ?></p>
          </div>
          <div class="badge"><span class="dot"></span> CLOSED</div>
        </div>
      </div>

      <div class="content">
        <div class="notice error">
          <strong>Access denied.</strong> This portal is not accepting responses right now.
          <div class="sub">Please contact your instructor for the next available response window.</div>
        </div>

        <div class="panel">
          <div style="font-weight:950; margin-bottom:6px;">What you can do</div>
          <div style="color:var(--muted); font-weight:650; font-size:13px; line-height:1.6;">
            1) Wait until your instructor opens the portal again.<br>
            2) If you think this is unexpected, message your instructor with a screenshot of this page.
          </div>
        </div>

        <div class="actions">
          <a class="btn" href="index.php">Back to portal</a>
        </div>
      </div>

      <div class="footer">
        Developed by <strong style="color:var(--text); margin-left:6px;">Partho Sutra Dhor</strong>
      </div>
    </div>
  </div>
</body>
</html>
