<?php
// For dashboard live updates
require_once __DIR__ . '/../partials/site_header.php';
header('Content-Type: application/json');

if (!is_admin()) {
  http_response_code(403);
  echo json_encode(['error' => 'forbidden']);
  exit;
}

$after = isset($_GET['after']) ? (int)$_GET['after'] : 0;

// Return up to 10 newest rows AFTER the given ID (descending so newest first)
$stmt = $pdo->prepare("
  SELECT ID, CREATED_AT, ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE
  FROM AUDIT_LOG
  WHERE ID > ?
  ORDER BY ID DESC
  LIMIT 10
");
$stmt->execute([$after]);
$rows = $stmt->fetchAll();

echo json_encode($rows);
