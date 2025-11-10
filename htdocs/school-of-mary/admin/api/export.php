<?php
// CSV Export endpoint
require_once __DIR__ . '/../../partials/site_header.php';

if (!is_admin()) {
  http_response_code(403);
  echo "Forbidden";
  exit;
}

$table = strtoupper(trim($_GET['table'] ?? ''));
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
$cols = $allowed[$table];

$filename = $table . '_export_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'. $filename . '"');

// Excel-friendly BOM
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// header
fputcsv($out, $cols);

// query
$colList = implode(',', $cols);
$stmt = $pdo->query("SELECT $colList FROM $table ORDER BY 1 ASC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  // CSV injection hardening: prefix dangerous leading chars in text cells
  foreach ($row as $k => $v) {
    if (is_string($v) && strlen($v)) {
      $first = $v[0];
      if (in_array($first, ['=', '+', '-', '@'])) {
        $row[$k] = "'" . $v;
      }
    }
  }
  fputcsv($out, $row);
}
fclose($out);
