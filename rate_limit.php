<?php
declare(strict_types=1);

function client_ip(): string {
    // If you are NOT behind a proxy, REMOTE_ADDR is safest.
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Rate limit using a small file keyed by endpoint + IP.
 * Example: max 8 attempts per 5 minutes.
 *
 * Returns: [allowed(bool), retry_after_seconds(int), remaining(int)]
 */
function rate_limit_allow(string $key, int $max, int $windowSeconds): array {
    $ip = client_ip();
    $safeKey = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $key);
    $safeIp  = preg_replace('/[^0-9a-fA-F:\.]/', '_', $ip);

    $dir = __DIR__ . '/.ratelimit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    $file = $dir . "/{$safeKey}__{$safeIp}.json";
    $now  = time();

    $data = [
        'start' => $now,
        'count' => 0
    ];

    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $tmp = json_decode((string)$raw, true);
        if (is_array($tmp) && isset($tmp['start'], $tmp['count'])) {
            $data['start'] = (int)$tmp['start'];
            $data['count'] = (int)$tmp['count'];
        }
    }

    // Window expired → reset
    if ($now - $data['start'] >= $windowSeconds) {
        $data['start'] = $now;
        $data['count'] = 0;
    }

    if ($data['count'] >= $max) {
        $retry = $windowSeconds - ($now - $data['start']);
        if ($retry < 1) $retry = 1;
        return [false, $retry, 0];
    }

    // Allow, increment
    $data['count']++;
    @file_put_contents($file, json_encode($data), LOCK_EX);

    $remaining = max(0, $max - $data['count']);
    return [true, 0, $remaining];
}

/** Optional: small slowdown on failures to make brute-force painful */
function rate_limit_sleep_on_fail(int $millis = 400): void {
    usleep($millis * 1000);
}
