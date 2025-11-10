<?php
// CSV Import endpoint (admin only)
require_once __DIR__ . '/../../partials/site_header.php';

if (!is_admin()) {
  http_response_code(403);
  echo "Forbidden";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo "Use POST.";
  exit;
}

$table = strtoupper(trim($_POST['table'] ?? ''));
$allowed = [
  'FACULTY'    => ['FACULTY_ID','FACULTY_FNAME','FACULTY_INITIAL','FACULTY_LNAME','FACULTY_EMAIL','RANK_ID','DEPT_ID'],
  'RESEARCH'   => ['RESEARCH_ID','RESEARCH_TITLE','RESEARCH_STARTDATE','RESEARCH_ENDDATE','RESEARCH_STATUS'],
  'AGENCY'     => ['AGENCY_ID','AGENCY_NAME','AGENCY_TYPE','AGENCY_CONTACTINFO'],
  'FUNDING'    => ['FUNDING_ID','RESEARCH_ID','AGENCY_ID','FUNDING_AMOUNT','DATE_FUNDED'],
  'ASSIGNMENT' => ['ASSIGNMENT_ID','FACULTY_ID','RESEARCH_ID','ROLE_ID','DATE_ASSIGNED'],
];

if (!isset($allowed[$table])) {
  http_response_code(400);
  echo "Invalid table.";
  exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
  http_response_code(400);
  echo "Upload a CSV file under 'file'.";
  exit;
}

$path = $_FILES['file']['tmp_name'];
$cols = $allowed[$table];

// Open CSV
$fh = fopen($path, 'r');
if (!$fh) {
  http_response_code(400);
  echo "Cannot open upload.";
  exit;
}

// Read header
$header = fgetcsv($fh);
if (!$header) {
  http_response_code(400);
  echo "Empty CSV.";
  exit;
}

// Validate header (must contain only allowed columns; order can vary)
$header = array_map('trim', $header);
foreach ($header as $h) {
  if (!in_array($h, $cols, true)) {
    http_response_code(400);
    echo "Unexpected column in CSV: $h";
    exit;
  }
}

// Build INSERT statement using provided columns
$insertCols = $header;
$placeholders = array_map(fn() => '?', $insertCols);
$sql = "INSERT INTO $table (" . implode(',', $insertCols) . ") VALUES (" . implode(',', $placeholders) . ")";
$stmt = $pdo->prepare($sql);

// Basic validators for a few fields
function valid_email($s) {
  return (bool)filter_var($s, FILTER_VALIDATE_EMAIL);
}
function normalize_empty($v) {
  $v = trim($v);
  return ($v === '' ? null : $v);
}

$pdo->beginTransaction();

$line = 1;
$inserted = 0;
try {
  while (($row = fgetcsv($fh)) !== false) {
    $line++;
    // map row to assoc by header
    $data = [];
    foreach ($header as $i => $colName) {
      $data[$colName] = $row[$i] ?? null;
    }

    // normalize empty strings to null for nullable columns
    foreach ($data as $k => $v) {
      $data[$k] = normalize_empty($v);
    }

    // Simple per-table validations
    if ($table === 'FACULTY') {
      if (!empty($data['FACULTY_EMAIL']) && !valid_email($data['FACULTY_EMAIL'])) {
        throw new RuntimeException("Line $line: invalid email");
      }
      if (empty($data['FACULTY_FNAME']) || empty($data['FACULTY_LNAME']) || empty($data['RANK_ID']) || empty($data['DEPT_ID'])) {
        throw new RuntimeException("Line $line: missing required FACULTY fields");
      }
    }

    if ($table === 'RESEARCH') {
      if (empty($data['RESEARCH_TITLE']) || empty($data['RESEARCH_STARTDATE']) || empty($data['RESEARCH_STATUS'])) {
        throw new RuntimeException("Line $line: missing required RESEARCH fields");
      }
    }

    if ($table === 'AGENCY') {
      if (empty($data['AGENCY_NAME']) || empty($data['AGENCY_TYPE']) || empty($data['AGENCY_CONTACTINFO'])) {
        throw new RuntimeException("Line $line: missing required AGENCY fields");
      }
    }

    if ($table === 'FUNDING') {
      if (empty($data['RESEARCH_ID']) || empty($data['AGENCY_ID'])) {
        throw new RuntimeException("Line $line: missing RESEARCH_ID/AGENCY_ID in FUNDING");
      }
      if (isset($data['FUNDING_AMOUNT']) && $data['FUNDING_AMOUNT'] !== null) {
        if (!is_numeric($data['FUNDING_AMOUNT'])) {
          throw new RuntimeException("Line $line: FUNDING_AMOUNT must be numeric");
        }
      }
    }

    if ($table === 'ASSIGNMENT') {
      if (empty($data['FACULTY_ID']) || empty($data['RESEARCH_ID']) || empty($data['ROLE_ID']) || empty($data['DATE_ASSIGNED'])) {
        throw new RuntimeException("Line $line: missing required ASSIGNMENT fields");
      }
    }

    // Build a values array matching $insertCols
    $values = [];
    foreach ($insertCols as $c) {
      $values[] = $data[$c] ?? null;
    }

    $stmt->execute($values);
    $inserted++;
  }

  $pdo->commit();
  fclose($fh);

  // Audit trail
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'IMPORT', $table, $inserted]);

  header('Content-Type: application/json');
  echo json_encode(['ok' => true, 'inserted' => $inserted]);

} catch (Throwable $e) {
  $pdo->rollBack();
  fclose($fh);
  http_response_code(400);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'line' => $line]);
}
