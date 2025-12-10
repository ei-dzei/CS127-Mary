<?php
// Check if active session, if not start one
// User login, CSRF, and other shared helpers
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// DB connection
require_once __DIR__ . '/../config/db.php';

// Returns scheme://host (no path)
function http_origin(): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $scheme . '://' . $host;
}

// Returns app base path
function app_base(): string {
  static $base = null;
  if ($base !== null) return $base;

  $env = getenv('SOM_APP_BASE');
  if (is_string($env) && $env !== '') {
    $env = '/' . ltrim($env, '/');
    $env = rtrim($env, '/');
    $base = $env === '' ? '/' : $env;
    return $base;
  }

  // Default hardcoded base (project folder)
  $base = '/school-of-mary';
  return $base;
}

// Build a full app URL
function app_url(string $path = ''): string {
  // Pass through full URLs
  if (preg_match('~^https?://~i', $path)) {
    return $path;
  }

  $origin = http_origin();
  $base   = app_base();

  if ($path === '' || $path === '/') {
    return $origin . $base . '/';
  }

  // Ensure single slash between base and path
  if ($path[0] !== '/') {
    $path = '/' . $path;
  }
  return $origin . $base . $path;
}

// Build a relative app path
function app_path(string $path = ''): string {
  $base = app_base();
  if ($path === '' || $path === '/') return $base . '/';
  if ($path[0] !== '/') $path = '/' . $path;
  return $base . $path;
}

// Redirects the user to a new page and exits the script immediately
function redirect_to(string $path): void {
  if (preg_match('~^https?://~i', $path)) {
    header('Location: ' . $path);
  } else {
    header('Location: ' . app_url($path));
  }
  exit;
}

// Returns current URI path
function current_path(): string {
  $uri = $_SERVER['REQUEST_URI'] ?? '/';
  $path = parse_url($uri, PHP_URL_PATH) ?: '/';
  return $path;
}
// Checks if current path is in admin area
function in_admin_area(): bool {
  $path = current_path();
  $base = app_base();
  if (strpos($path, $base) === 0) {
    $path = substr($path, strlen($base));
    if ($path === false) $path = '/';
  }
  return (bool)preg_match('~^/admin(/|$)~', $path);
}

// Checks if user is logged in as admin
function is_admin(): bool {
  return !empty($_SESSION['admin_user']);
}

// CSRF (Cross-Site Request Forgery) Protection
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function csrf_token(): string {
  return $_SESSION['csrf'];
}
// Verifies the CSRF token on POST requests
// Stops execution with a 400 error if the token is missing or invalid
function csrf_check(): void {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $token)) {
      http_response_code(400);
      die('Invalid CSRF token.');
    }
  }
}

// Validate email address format
function v_email($s): bool {
  return filter_var($s, FILTER_VALIDATE_EMAIL) !== false;
}
// Validate string length (Varchar)
// Checks if input is a string, not empty, and within the max length
function v_varchar($s, $max): bool {
  return is_string($s) && $s !== '' && mb_strlen($s) <= $max;
}
// Validate nullable string length
// Allows null or empty string, but if content exists, checks max length
function v_char_nullable($s, $max): bool {
  if ($s === null || $s === '') return true;
  return is_string($s) && mb_strlen($s) <= $max;
}
// Validate if a value exists in a specific database table column
function v_enum_exists(PDO $pdo, string $table, string $col, $value): bool {
  $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$col}=? LIMIT 1");
  $stmt->execute([$value]);
  return (bool)$stmt->fetchColumn();
}
