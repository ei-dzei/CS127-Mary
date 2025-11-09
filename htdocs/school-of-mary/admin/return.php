<?php
require_once __DIR__ . '/../config/db.php';
session_start();
if (empty($_SESSION['admin_user'])) { http_response_code(403); exit; }
$recent = $pdo->query("SELECT * FROM AUDIT_LOG ORDER BY LOG_ID DESC LIMIT 12")->fetchAll();
foreach ($recent as $log) {
  echo '<div class="meta">#'.(int)$log['LOG_ID'].' · '.htmlspecialchars($log['ACTION_ENUM']).' on '.htmlspecialchars($log['TABLE_NAME']).' ('.htmlspecialchars($log['PK_VALUE']).') — '.htmlspecialchars($log['CREATED_AT']).'</div>';
}
