<?php
declare(strict_types=1);

/**
 * Shared small helpers (keep logic/output identical).
 */

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
