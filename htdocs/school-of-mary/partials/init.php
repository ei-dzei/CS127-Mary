<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../config/db.php';

/** ---------- Helpers ---------- */
function is_admin(): bool {
  return !empty($_SESSION['admin_user']);
}

function current_path(): string {
  $uri = $_SERVER['REQUEST_URI'] ?? '/';
  $path = parse_url($uri, PHP_URL_PATH) ?: '/';
  return $path;
}

function in_admin_area(): bool {
  $path = current_path();
  return substr($path, 0, 7) === '/admin/';
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
