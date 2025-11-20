<?php
/**
 * Central PDO connection (XAMPP defaults). Reuse via: require_once __DIR__ . '/config/db.php';
 */
define('BASE_URL', '/school-of-mary');

$DB_HOST = '127.0.0.1';
$DB_NAME = 'mary127';
$DB_USER = 'root';
$DB_PASS = '';

$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

try {
  $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
  http_response_code(500);
  die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}
