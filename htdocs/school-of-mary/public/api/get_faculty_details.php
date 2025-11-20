<?php
require_once __DIR__ . '/../../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing faculty ID']);
  exit;
}

/* --- Fetch main faculty info --- */
$stmt = $pdo->prepare("
  SELECT f.FACULTY_ID, f.FACULTY_FNAME, f.FACULTY_LNAME, f.FACULTY_INITIAL,
         f.FACULTY_EMAIL, r.RANK_DESCRIPTION, 
         d.DEPT_SPECIALIZATION AS DEPARTMENT, d.DEPT_CLASSIFICATION
  FROM FACULTY f
  JOIN `RANK` r ON r.RANK_ID = f.RANK_ID
  JOIN DEPARTMENT d ON d.DEPT_ID = f.DEPT_ID
  WHERE f.FACULTY_ID = ?
  LIMIT 1
");
$stmt->execute([$id]);
$faculty = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$faculty) {
  http_response_code(404);
  echo json_encode(['error' => 'Faculty not found']);
  exit;
}

/* --- Fetch research assignments --- */
$rs = $pdo->prepare("
  SELECT re.RESEARCH_ID, re.RESEARCH_TITLE, re.RESEARCH_STATUS,
         re.RESEARCH_STARTDATE, re.RESEARCH_ENDDATE,
         ag.AGENCY_NAME, fu.FUNDING_AMOUNT
  FROM ASSIGNMENT a
  JOIN RESEARCH re ON re.RESEARCH_ID = a.RESEARCH_ID
  LEFT JOIN FUNDING fu ON fu.RESEARCH_ID = re.RESEARCH_ID
  LEFT JOIN AGENCY ag ON ag.AGENCY_ID = fu.AGENCY_ID
  WHERE a.FACULTY_ID = ?
  ORDER BY re.RESEARCH_STARTDATE DESC
");
$rs->execute([$id]);
$projects = $rs->fetchAll(PDO::FETCH_ASSOC);

/* --- Optional computed name for readability --- */
$faculty['FULL_NAME'] = $faculty['FACULTY_LNAME'] . ', ' . $faculty['FACULTY_FNAME'] .
  (!empty($faculty['FACULTY_INITIAL']) ? ' ' . $faculty['FACULTY_INITIAL'] : '');//(!empty($faculty['FACULTY_INITIAL']) ? ' ' . $faculty['FACULTY_INITIAL'] . '.' : ''); removed dot since may dot na sa database, need to fix database if ever

/* --- Return structured JSON --- */
header('Content-Type: application/json');
echo json_encode([
  'faculty' => $faculty,
  'projects' => $projects
]);
