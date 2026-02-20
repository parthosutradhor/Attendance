# Attendance Portal (PHP + Google Sign-In + Google Sheets)

A lightweight attendance portal:
- Students **sign in with Google** (Google Identity Services)
- Attendance is submitted to a **Google Sheet** using a **Service Account**
- Admin panel to control **IP/ASN restrictions**, **email domain rules**, and **allowed course/section lists**

---

## 1) Requirements

### Server
- **Nginx** or **Apache**
- **PHP 8+** with:
  - `curl`
  - `openssl`
  - `json`
  - `session`

### Google
- A Google Cloud project with:
  - **OAuth Client ID (Web)** for Google Sign-In
  - **Service Account key JSON** for Google Sheets API
  - **Google Sheets API enabled**
- A Google Sheet where the service account has access

---

## 2) Project Files (what does what)

- `index.php` — main UI (Google sign-in + attendance form)
- `login.php` — verifies Google ID token (tokeninfo endpoint) and creates session
- `submit.php` — validates CSRF, network/email policy, and appends to the Sheet
- `sheets.php` — Google Sheets API calls (Service Account → JWT → access token)
- `admin.php` — admin panel to update allowlists and toggles
- `change_password.php` — change admin password (requires being logged into admin)
- `settings.json` — runtime settings edited by admin panel
- `settings_lib.php` — settings loader + policy checks (network/email/allowlists)
- `session_bootstrap.php` / `security_headers.php` / `rate_limit.php` — security helpers

---

## 3) Quick Setup (Step-by-step)

### Step A — Put the project on your server
Example location:
```
/var/www/attendance-portal/
```

### Step B — Configure `config.php`
Open `config.php` and fill:

```php
// Google OAuth Client ID (Web)
const GOOGLE_CLIENT_ID = "YOUR_WEB_CLIENT_ID.apps.googleusercontent.com";

// Google Sheet settings
const SPREADSHEET_ID = "YOUR_SPREADSHEET_ID";
const SHEET_TAB_NAME = "Attendance";

// Service account JSON
const SERVICE_ACCOUNT_JSON_PATH = __DIR__ . "/service-account.json";

// Settings JSON (created/edited via admin.php)
const SETTINGS_FILE = __DIR__ . "/settings.json";
```

**Where to get values**
- `GOOGLE_CLIENT_ID`: from Google Cloud → OAuth Client IDs
- `SPREADSHEET_ID`: from the Google Sheet URL
- `SHEET_TAB_NAME`: the exact tab name inside the spreadsheet (default: `Attendance`)

### Step C — Add your Service Account key
1. Create a Service Account key JSON (see Section 4)
2. Save it as:
```
service-account.json
```
in the project root (same folder as `config.php`)

> IMPORTANT: Do **NOT** commit `service-account.json` to GitHub.

### Step D — Make sure the server can write `settings.json`
Your admin panel edits `settings.json`, so PHP must be able to write it.

Example:
```bash
sudo chown www-data:www-data /var/www/attendance-portal/settings.json
sudo chmod 640 /var/www/attendance-portal/settings.json
```

(Your PHP user may be `www-data`, `nginx`, or something else depending on distro.)

### Step E — Set the first admin password
Admin login checks `settings.json` key: `admin_password_sha1`.

Default password is '123456'

You can set it manually like this:

1) Generate SHA1 (Linux):
```bash
php -r 'echo sha1("YOUR_NEW_PASSWORD"), PHP_EOL;'
```

2) Paste into `settings.json`:
```json
"admin_password_sha1": "PUT_SHA1_HASH_HERE"
```

Now open:
- `/admin.php` → login with the password
- Optionally use `/change_password.php` after login

---

## 4) Google Cloud Setup

### A) Enable Google Sheets API
Google Cloud Console:
- APIs & Services → Library → Enable **Google Sheets API**

### B) Create OAuth Client ID (Web) for Google Sign-In
Google Cloud Console:
- APIs & Services → Credentials → Create Credentials → **OAuth client ID**
- Application type: **Web application**

Add:
- **Authorized JavaScript origins**
  - `https://your-domain.com`
  - (and `http://localhost` only for local testing)

This portal uses Google Identity Services in the browser and sends the **ID token** to `login.php` for verification.

### C) Create Service Account + Key (for Sheets write access)
1. IAM & Admin → Service Accounts → Create service account
2. Create a **Key** → JSON
3. Download the JSON and save it as:
   - `service-account.json` in the project root

### D) Share your Google Sheet with the Service Account
Open your Google Sheet → Share → add the **service account email**:
- It looks like: `something@your-project.iam.gserviceaccount.com`
Give **Editor** access.

---

## 5) Google Sheet Format

This portal appends 7 columns (A:G):

| Column | Value |
|-------:|------|
| A | Course |
| B | Section |
| C | Name |
| D | Student ID |
| E | Email |
| F | Timestamp (Asia/Dhaka) |
| G | IP |

One submission per day per email is enforced by reading `E:F` and comparing the date.

---

## 6) Admin Panel Options (settings.json)

Open `/admin.php` (after setting `admin_password_sha1`).

### Network policy
- `allow_all_ip` (true/false)
- `ip_whitelist` (exact IP matches)
- `asn_allowlist` (ASN string match via ipinfo lookup)

Logic:
1) if IP is whitelisted → allow
2) else if `allow_all_ip` → allow
3) else require ASN match

### Email policy
- `email_mode`
  - `domains` (default): only domains in `domain_allowlist`
  - `all_gmail`: your current code allows any domain here (see note below)
- `domain_allowlist`: e.g. `bracu.ac.bd`, `g.bracu.ac.bd`

> NOTE: Your current `settings_lib.php` implements `all_gmail` as “allow everything”.
> If you later want “only @gmail.com”, update that function accordingly.

### Form allowlists
- `course_codes`: dropdown list shown on the form
- `sections`: dropdown list shown on the form

If these arrays are empty, the form is not restricted.

---

## 7) Nginx Example Config

Put inside your `server { ... }` block:

```nginx
root /var/www/attendance-portal;
index index.php;

location / {
  try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
  include fastcgi_params;
  fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
  fastcgi_pass unix:/run/php/php8.2-fpm.sock;  # adjust version/path
}

# Security: block dotfiles except .well-known
location ~* (^|/)\.(?!well-known/) { deny all; }

# Security: block sensitive extensions
location ~* \.(json|lock|log|ini|env|bak|old|swp|sql|sqlite|sqlite3|yml|yaml|dist)$ { deny all; }

# Security: block known sensitive files
location = /service-account.json { deny all; }
location = /settings.json        { deny all; }
location = /secrets.txt          { deny all; }
```

After editing:
```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

## 8) Troubleshooting

### “403 Forbidden” after changing server configs
- Nginx ignores `.htaccess`
- Check Nginx error log:
```bash
sudo tail -n 80 /var/log/nginx/error.log
```

### Google sign-in fails (Client ID mismatch)
- Ensure `GOOGLE_CLIENT_ID` in `config.php` exactly matches the OAuth client ID
- Ensure Authorized JavaScript origins include your domain

### Sheets append fails
- Check that `service-account.json` is a **Service Account KEY** JSON (not OAuth client secret)
- Ensure Sheets API is enabled
- Ensure the sheet is shared with the service account email (Editor)

### Admin settings don't save
- `settings.json` must be writable by the PHP user (`www-data` / `nginx`)

---

## 9) Security Notes

- Never commit:
  - `service-account.json`
  - `settings.json` (if it contains admin hash or private allowlists)
  - `secrets.txt`
- Consider moving secrets outside the web root and loading them by absolute path.
- Enable HTTPS.
- Keep `admin_password_sha1` strong (8+ chars minimum; longer is better).

---

