<?php
// CSV Export endpoint (admin only)

require_once __DIR__ . '/../../partials/init.php';

// Must be logged-in admin
if (!is_admin()) {
  http_response_code(403);
  header('Content-Type: text/plain; charset=UTF-8');
  echo "Forbidden";
  exit;
}

// Validate table param
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
  header('Content-Type: text/plain; charset=UTF-8');
  echo "Invalid or missing 'table' parameter.";
  exit;
}

$cols = $allowed[$table];

if (function_exists('ob_get_level')) {
  while (ob_get_level() > 0) { ob_end_clean(); }
}

// Headers for CSV download (Excel-friendly)
$filename = $table . '_export_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";

// Open output stream
$out = fopen('php://output', 'w');

// CSV header row
fputcsv($out, $cols);

// Query rows and stream
$colList = implode(',', $cols);
$sql = "SELECT $colList FROM $table ORDER BY 1 ASC";
$stmt = $pdo->query($sql);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  // CSV injection hardening for text cells
  foreach ($row as $k => $v) {
    if (is_string($v) && $v !== '') {
      $first = $v[0];
      if (in_array($first, ['=', '+', '-', '@'])) {
        $row[$k] = "'" . $v;
      }
    }
  }
  fputcsv($out, $row);
}

fclose($out);
exit;
