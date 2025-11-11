<?php
require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing research ID']);
  exit;
}

/* --- Fetch core research info --- */
$stmt = $pdo->prepare("
  SELECT re.RESEARCH_ID, re.RESEARCH_TITLE, re.RESEARCH_STATUS,
         re.RESEARCH_STARTDATE, re.RESEARCH_ENDDATE
  FROM RESEARCH re
  WHERE re.RESEARCH_ID = ?
  LIMIT 1
");
$stmt->execute([$id]);
$research = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$research) {
  http_response_code(404);
  echo json_encode(['error' => 'Research not found']);
  exit;
}

/* --- Fetch assigned faculty --- */
$as = $pdo->prepare("
  SELECT f.FACULTY_ID, f.FACULTY_FNAME, f.FACULTY_LNAME, f.FACULTY_INITIAL,
         r.RANK_DESCRIPTION, a.ROLE_ID, a.DATE_ASSIGNED
  FROM ASSIGNMENT a
  JOIN FACULTY f ON f.FACULTY_ID = a.FACULTY_ID
  JOIN `RANK` r ON r.RANK_ID = f.RANK_ID
  WHERE a.RESEARCH_ID = ?
  ORDER BY a.DATE_ASSIGNED DESC
");
$as->execute([$id]);
$people = $as->fetchAll(PDO::FETCH_ASSOC);

/* --- Fetch funding info --- */
$fs = $pdo->prepare("
  SELECT ag.AGENCY_NAME, ag.AGENCY_TYPE,
         fu.FUNDING_AMOUNT, fu.DATE_FUNDED
  FROM FUNDING fu
  JOIN AGENCY ag ON ag.AGENCY_ID = fu.AGENCY_ID
  WHERE fu.RESEARCH_ID = ?
  ORDER BY fu.DATE_FUNDED DESC
");
$fs->execute([$id]);
$funding = $fs->fetchAll(PDO::FETCH_ASSOC);

/* --- Compute total funding --- */
$totalFunding = 0;
foreach ($funding as $f) {
  $totalFunding += (float)($f['FUNDING_AMOUNT'] ?? 0);
}

/* --- Return structured JSON --- */
header('Content-Type: application/json');
echo json_encode([
  'RESEARCH_ID' => $research['RESEARCH_ID'],
  'RESEARCH_TITLE' => $research['RESEARCH_TITLE'],
  'RESEARCH_STATUS' => $research['RESEARCH_STATUS'],
  'RESEARCH_STARTDATE' => $research['RESEARCH_STARTDATE'],
  'RESEARCH_ENDDATE' => $research['RESEARCH_ENDDATE'],
  'people' => $people,
  'funding' => $funding,
  'total_funding' => $totalFunding
]);
