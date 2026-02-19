<?php
declare(strict_types=1);

// Google OAuth Client ID (Web)
const GOOGLE_CLIENT_ID = "152898827405-5o9mng0eumjler1dr715onuq99ms9sl2.apps.googleusercontent.com";

// Google Sheet settings
const SPREADSHEET_ID = "1UCUvcLmxjSmX9mXWYijhlsL5GTh4lfMasXtGvyjwEkY";
const SHEET_TAB_NAME = "Attendance";

// Service account JSON
const SERVICE_ACCOUNT_JSON_PATH = __DIR__ . "/service-account.json";

// Settings JSON (created/edited via admin.php)
const SETTINGS_FILE = __DIR__ . "/settings.json";

// Legacy fallback (used only if settings.json is missing)
const ALLOWED_EMAIL_DOMAINS = ["bracu.ac.bd", "g.bracu.ac.bd"];
