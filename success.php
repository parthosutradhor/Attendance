<?php
declare(strict_types=1);

require_once __DIR__ . '/security_headers.php'; 
require_once __DIR__ . '/session_bootstrap.php';
secure_session_start();

$last = $_SESSION['last_submission'] ?? [];
if (!is_array($last)) $last = [];

$name    = $last['name'] ?? '';
$id      = $last['id'] ?? '';
$course  = $last['course'] ?? '';
$section = $last['section'] ?? '';
$email   = $last['email'] ?? '';
$ts      = $last['ts'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Attendance Recorded</title>

<style>
:root{
  --bg:#f3f5fb;
  --card:#ffffff;
  --border:#e5e7eb;
  --shadow:0 18px 45px rgba(2,6,23,.10);
  --brand:#16a34a;
  --text:#0f172a;
  --muted:#64748b;
}
*{box-sizing:border-box}
body{
  margin:0;
  font-family:system-ui,Segoe UI,Roboto;
  background: radial-gradient(900px 520px at 50% -10%, rgba(16,185,129,.15), transparent 60%), var(--bg);
  min-height:100vh;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:20px;
}
.card{
  width:min(520px,100%);
  background:var(--card);
  border:1px solid var(--border);
  border-radius:20px;
  box-shadow:var(--shadow);
  padding:30px;
  text-align:center;
  animation:fadeIn .3s ease;
}
.icon{
  font-size:48px;
  margin-bottom:10px;
}
h1{
  margin:0 0 8px;
  font-size:22px;
  color:var(--brand);
}
.sub{
  color:var(--muted);
  font-size:14px;
  margin-bottom:20px;
}
.summary{
  text-align:left;
  margin-top:15px;
  border-top:1px solid var(--border);
  padding-top:15px;
}
.row{
  display:flex;
  justify-content:space-between;
  margin-bottom:8px;
  font-size:14px;
}
.row span:first-child{
  color:var(--muted);
}
.btn{
  margin-top:20px;
  padding:10px 16px;
  border:none;
  border-radius:12px;
  background:#2563eb;
  color:#fff;
  font-weight:600;
  cursor:pointer;
}
.btn:hover{background:#1d4ed8}
@keyframes fadeIn{
  from{opacity:0; transform:translateY(-6px);}
  to{opacity:1; transform:translateY(0);}
}
</style>
</head>

<body>

<div class="card">
  <div class="icon">✅</div>
  <h1>Attendance Recorded Successfully</h1>
  <div class="sub">Your attendance has been saved in the system.</div>

  <div class="summary">
    <div class="row"><span>Name</span><span><?= htmlspecialchars($name) ?></span></div>
    <div class="row"><span>Student ID</span><span><?= htmlspecialchars($id) ?></span></div>
    <div class="row"><span>Course</span><span><?= htmlspecialchars($course) ?></span></div>
    <div class="row"><span>Section</span><span><?= htmlspecialchars($section) ?></span></div>
    <div class="row"><span>Email</span><span><?= htmlspecialchars($email) ?></span></div>
    <div class="row"><span>Timestamp</span><span><?= htmlspecialchars($ts) ?></span></div>
  </div>

</div>

</body>
</html>
