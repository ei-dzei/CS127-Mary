<?php
// Load shared helpers
require_once __DIR__ . '/partials/init.php';

// Integer validator (used by CRUD pages)
if (!function_exists('v_int')) {
  // Validates if the input is a valid integer
  function v_int($s): bool {
    // If integer
    if (is_int($s)) return true;
    // If non-empty string with only digits
    if (is_string($s) && $s !== '' && preg_match('/^-?\d+$/', $s)) return true;
    // Php filter
    return filter_var($s, FILTER_VALIDATE_INT) !== false;
  }
}

// Validation failure handler
if (!function_exists('guardFail')) {
  // Stop execution and show error message
  function guardFail(string $msg): void {
    http_response_code(422);
    echo '<section class="panel" style="max-width:720px;margin:24px auto;background:#fff3f3;border:1px solid #f3c2c2;color:#7a1111;">';
    echo '<h3 style="margin:0 0 8px;">Validation failed</h3>';
    echo '<p style="margin:0;">' . htmlspecialchars($msg) . '</p>';
    echo '</section>';
    exit;
  }
}
