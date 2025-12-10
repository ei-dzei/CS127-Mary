<?php
// Central PDO connection configuration
// Reuse via: require_once __DIR__ . '/config/db.php';

define('BASE_URL', '/school-of-mary');

// Default credentials for XAMPP
$DB_HOST = '127.0.0.1';
$DB_NAME = 'mary127';
$DB_USER = 'root';
$DB_PASS = '';

// PDO options
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

// Attempt connection
try {
  $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
  // Return HTTP 500 (Internal Server Error) to user
  http_response_code(500);
  // Return error message
  die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}
