<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../config/db.php';

/** ---------- URL & Redirect Helpers---------- */

/**
 * Returns scheme://host
 */
function http_origin(): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $scheme . '://' . $host;
}

/**
 * Returns the app's root path portion derived from SCRIPT_NAME.
 */
function app_root_path(): string {
  $script = $_SERVER['SCRIPT_NAME'] ?? '/';
  $dirOfScript = rtrim(dirname($script), '/\\');
  if (preg_match('~/admin$~', $dirOfScript)) {
    return rtrim(dirname($dirOfScript), '/\\');
  }
  return $dirOfScript === '/' ? '' : $dirOfScript;
}

/**
 * Build a full absolute URL for a given app-relative $path.
 */
function app_url(string $path = ''): string {
  $origin = http_origin();

  if ($path === '') {
    return $origin . (app_root_path() ?: '/');
  }

  if ($path[0] === '/') {
    $root = app_root_path();
    return $origin . ($root ? $root : '') . $path;
  }

  $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
  return $origin . ($base === '/' ? '' : $base) . '/' . $path;
}

/**
 * Safe redirect
 */
function redirect_to(string $path): void {
  header('Location: ' . app_url($path));
  exit;
}

/** ---------- Path Helpers ---------- */
function current_path(): string {
  $uri = $_SERVER['REQUEST_URI'] ?? '/';
  $path = parse_url($uri, PHP_URL_PATH) ?: '/';
  return $path;
}

function in_admin_area(): bool {
  $path = current_path();
  return (bool)preg_match('~/admin(/|$)~', $path);
}

/** ---------- Auth Helpers ---------- */
function is_admin(): bool {
  return !empty($_SESSION['admin_user']);
}

/** ---------- CSRF ---------- */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function csrf_token(): string {
  return $_SESSION['csrf'];
}
function csrf_check(): void {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $token)) {
      http_response_code(400);
      die('Invalid CSRF token.');
    }
  }
}

/** ---------- Validation helpers ---------- */
function v_email($s): bool {
  return filter_var($s, FILTER_VALIDATE_EMAIL) !== false;
}
function v_varchar($s, $max): bool {
  return is_string($s) && $s !== '' && mb_strlen($s) <= $max;
}
function v_char_nullable($s, $max): bool {
  if ($s === null || $s === '') return true;
  return is_string($s) && mb_strlen($s) <= $max;
}
function v_enum_exists(PDO $pdo, string $table, string $col, $value): bool {
  $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$col}=? LIMIT 1");
  $stmt->execute([$value]);
  return (bool)$stmt->fetchColumn();
}
