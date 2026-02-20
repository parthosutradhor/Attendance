<?php
declare(strict_types=1);

// Google OAuth Client ID (Web)
const GOOGLE_CLIENT_ID = "";

// Google Sheet settings
const SPREADSHEET_ID = "";
const SHEET_TAB_NAME = "Attendance";

// Service account JSON
const SERVICE_ACCOUNT_JSON_PATH = __DIR__ . "/service-account.json";

// Settings JSON (created/edited via admin.php)
const SETTINGS_FILE = __DIR__ . "/settings.json";

// Legacy fallback (used only if settings.json is missing)
const ALLOWED_EMAIL_DOMAINS = ["bracu.ac.bd", "g.bracu.ac.bd"];
