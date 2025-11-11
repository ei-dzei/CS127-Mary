<?php
// For dashboard live updates (JSON)
require_once __DIR__ . '/../partials/init.php';

header('Content-Type: application/json');

if (!is_admin()) {
  http_response_code(403);
  echo json_encode(['error' => 'forbidden']);
  exit;
}

// Resolve audit table PK/timestamp columns and alias them
function audit_resolve_cols(PDO $pdo): array {
  $idCandidates   = ['ID','id','log_id','audit_id'];
  $timeCandidates = ['CREATED_AT','created_at','logged_at','timestamp','createdOn'];

  foreach ($idCandidates as $idCol) {
    foreach ($timeCandidates as $tCol) {
      try {
        $pdo->query("SELECT {$idCol} AS ID, {$tCol} AS CREATED_AT FROM AUDIT_LOG ORDER BY {$idCol} DESC LIMIT 1");
        return [$idCol, $tCol];
      } catch (Throwable $e) {}
    }
  }
  return ['ID', 'CREATED_AT'];
}
[$AUDIT_ID, $AUDIT_TIME] = audit_resolve_cols($pdo);

$after = isset($_GET['after']) ? (int)$_GET['after'] : 0;

// Return up to 10 newest rows AFTER the given ID (newest first)
$stmt = $pdo->prepare("
  SELECT {$AUDIT_ID} AS ID, {$AUDIT_TIME} AS CREATED_AT, ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE
  FROM AUDIT_LOG
  WHERE {$AUDIT_ID} > ?
  ORDER BY {$AUDIT_ID} DESC
  LIMIT 10
");
$stmt->execute([$after]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows);
exit;
