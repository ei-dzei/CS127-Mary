<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../auth.php';
require_admin();

header('Content-Type: application/json; charset=utf-8');

$counts = [
  'faculty'    => (int)$pdo->query("SELECT COUNT(*) FROM FACULTY")->fetchColumn(),
  'research'   => (int)$pdo->query("SELECT COUNT(*) FROM RESEARCH")->fetchColumn(),
  'assignment' => (int)$pdo->query("SELECT COUNT(*) FROM ASSIGNMENT")->fetchColumn(),
  'funding_total' => (float)$pdo->query("SELECT COALESCE(SUM(FUNDING_AMOUNT),0) FROM FUNDING")->fetchColumn(),
];
$audit = $pdo->query("SELECT LOG_ID, ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE, CREATED_AT
                      FROM AUDIT_LOG ORDER BY LOG_ID DESC LIMIT 10")->fetchAll();
echo json_encode(['counts'=>$counts,'audit'=>$audit]);
