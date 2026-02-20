<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Default settings (used if settings.json missing/corrupt)
 */
function settings_default(): array {
    return [
        // Network controls
        'accepting_responses' => true,
        'allow_all_ip'     => false,
        'asn_allowlist'    => ['AS151981'],
        'ip_whitelist'     => [],

        // Email controls
        // email_mode: 'all_gmail' or 'domains'
        'email_mode'       => 'domains',
        'domain_allowlist' => ALLOWED_EMAIL_DOMAINS,

        // Form controls
        'course_codes'     => ['MAT120 LAB'],
        'sections'         => ['14', '15', '16'],

        'updated_at'       => date('c'),
        
        'admin_password_sha1' => ''
    ];
}

/**
 * Load settings from SETTINGS_FILE and merge with defaults.
 */
function settings_load(): array {
    $d = settings_default();

    $path = defined('SETTINGS_FILE') ? SETTINGS_FILE : (__DIR__ . '/settings.json');
    if (!is_string($path) || $path === '' || !file_exists($path)) {
        return $d;
    }

    $raw = @file_get_contents($path);
    if ($raw === false) return $d;

    $json = json_decode($raw, true);
    if (!is_array($json)) return $d;

    // Merge shallowly
    $s = array_merge($d, $json);

    // Normalize arrays
    foreach (['asn_allowlist','ip_whitelist','domain_allowlist','course_codes','sections'] as $k) {
        if (!isset($s[$k]) || !is_array($s[$k])) $s[$k] = $d[$k];
        $s[$k] = settings_normalize_list($s[$k]);
    }

    $s['accepting_responses'] = !empty($s['accepting_responses']);
    $s['allow_all_ip'] = !empty($s['allow_all_ip']);
    $s['email_mode'] = ($s['email_mode'] ?? 'domains') === 'all_gmail' ? 'all_gmail' : 'domains';

    return $s;
}

function settings_normalize_list(array $items): array {
    $out = [];
    foreach ($items as $v) {
        $v = trim((string)$v);
        if ($v === '') continue;
        $out[] = $v;
    }
    $out = array_values(array_unique($out));
    return $out;
}

/**
 * Best-effort real client IP.
 */
function get_real_ip(): string {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return (string)$_SERVER['HTTP_CF_CONNECTING_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

/**
 * Exact IP whitelist match (no CIDR parsing here).
 */
function ip_is_whitelisted(string $ip, array $whitelist): bool {
    if ($ip === '') return false;
    foreach ($whitelist as $w) {
        if ($w !== '' && hash_equals($w, $ip)) return true;
    }
    return false;
}

/**
 * Check ASN via ipinfo.io (same logic as your index.php used).
 */
function ip_is_from_allowed_asn(string $ip, array $allowed_asns): bool {
    if ($ip === '' || empty($allowed_asns)) return false;

    $url = "https://ipinfo.io/{$ip}/json";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_USERAGENT => 'BRACU-Attendance/1.0',
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return false;
    $data = json_decode($response, true);
    if (!is_array($data)) return false;

    $org = (string)($data['org'] ?? ''); // e.g. "AS151981 ..."
    if ($org === '') return false;

    foreach ($allowed_asns as $asn) {
        $asn = trim((string)$asn);
        if ($asn !== '' && stripos($org, $asn) !== false) return true;
    }

    return false;
}

/**
 * Apply network policy:
 * - If IP in whitelist => allow
 * - Else if allow_all_ip => allow
 * - Else require ASN match
 */


 
 
function network_is_allowed(string $ip, array $settings): bool {
    $whitelist = $settings['ip_whitelist'] ?? [];
    $allow_all = !empty($settings['allow_all_ip']);
    $asns      = $settings['asn_allowlist'] ?? [];

    if (ip_is_whitelisted($ip, is_array($whitelist) ? $whitelist : [])) return true;
    if ($allow_all) return true;

    return ip_is_from_allowed_asn($ip, is_array($asns) ? $asns : []);
}

/**
 * Email policy:
 * - all_gmail => only *@gmail.com
 * - domains  => domain must be in domain_allowlist
 */
 
function email_is_allowed(string $email, array $settings): bool {
    $email = strtolower(trim($email));
    if ($email === '' || strpos($email, '@') === false) return false;

    $domain = substr(strrchr($email, '@') ?: '', 1);
    if ($domain === '') return false;

    $mode = ($settings['email_mode'] ?? 'domains') === 'all_gmail' ? 'all_gmail' : 'domains';

    if ($mode === 'all_gmail') {
        return true;
    }

    $allowed = $settings['domain_allowlist'] ?? ALLOWED_EMAIL_DOMAINS;
    if (!is_array($allowed)) $allowed = ALLOWED_EMAIL_DOMAINS;

    foreach ($allowed as $d) {
        if ($d !== '' && strtolower((string)$d) === $domain) return true;
    }

    return false;
}

/**
 * Validate course/section against allowlists if lists are non-empty.
 */
function form_value_allowed(string $value, array $allowlist): bool {
    $value = trim($value);
    if ($value === '') return false;
    if (empty($allowlist)) return true; // if admin left list empty, do not restrict

    foreach ($allowlist as $a) {
        if (trim((string)$a) === $value) return true;
    }
    return false;
}

function get_admin_password_sha1(): string {
    $s = load_settings();
    $h = (string)($s['admin_password_sha1'] ?? '');
    return strtolower(trim($h));
}

function set_admin_password_sha1(string $sha1): bool {
    $s = load_settings();
    $s['admin_password_sha1'] = strtolower(trim($sha1));
    return save_settings($s);
}

/**
 * Save settings to SETTINGS_FILE (pretty JSON).
 */
function settings_save(array $s): bool {
    $s['updated_at'] = date('c');

    $path = defined('SETTINGS_FILE') ? SETTINGS_FILE : (__DIR__ . '/settings.json');
    if (!is_string($path) || $path === '') return false;

    $json = json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;

    return file_put_contents($path, $json) !== false;
}

