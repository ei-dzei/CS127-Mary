<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../partials/init.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid id']); exit; }

$sql = "
SELECT f.FACULTY_ID, f.FACULTY_FNAME, f.FACULTY_INITIAL, f.FACULTY_LNAME, f.FACULTY_EMAIL,
       r.RANK_DESCRIPTION,
       d.DEPT_SPECIALIZATION, d.DEPT_CLASSIFICATION, d.DEPT_CONTACTINFO
FROM FACULTY f
JOIN `RANK` r ON r.RANK_ID = f.RANK_ID
JOIN DEPARTMENT d ON d.DEPT_ID = f.DEPT_ID
WHERE f.FACULTY_ID = :id
LIMIT 1";
$st = $pdo->prepare($sql);
$st->execute([':id'=>$id]);
$fac = $st->fetch(PDO::FETCH_ASSOC);
if (!$fac) { http_response_code(404); echo json_encode(['error'=>'Not found']); exit; }

$full = trim($fac['FACULTY_FNAME'].' '.($fac['FACULTY_INITIAL']?:'').' '.$fac['FACULTY_LNAME']);

$as = $pdo->prepare("
  SELECT re.RESEARCH_ID, re.RESEARCH_TITLE, re.RESEARCH_STATUS, re.RESEARCH_STARTDATE, re.RESEARCH_ENDDATE,
         ro.ROLE_DESCRIPTION, a.DATE_ASSIGNED
  FROM ASSIGNMENT a
  JOIN RESEARCH re ON re.RESEARCH_ID = a.RESEARCH_ID
  LEFT JOIN ROLE ro ON ro.ROLE_ID = a.ROLE_ID
  WHERE a.FACULTY_ID = :id
  ORDER BY re.RESEARCH_STARTDATE DESC, a.DATE_ASSIGNED DESC, re.RESEARCH_ID DESC
");
$as->execute([':id'=>$id]);
$assignments = $as->fetchAll(PDO::FETCH_ASSOC);

$cntAll = 0; $cntOngoing = 0; $cntCompleted = 0;
foreach ($assignments as $p) {
  $cntAll++;
  if (strtoupper($p['RESEARCH_STATUS']) === 'COMPLETED') $cntCompleted++;
  else $cntOngoing++;
}

foreach ($assignments as &$a) {
  $fundStmt = $pdo->prepare("
    SELECT ag.AGENCY_NAME, fu.FUNDING_AMOUNT
    FROM FUNDING fu
    JOIN AGENCY ag ON ag.AGENCY_ID = fu.AGENCY_ID
    WHERE fu.RESEARCH_ID = ?
  ");
  $fundStmt->execute([$a['RESEARCH_ID']]);
  $a['funding'] = $fundStmt->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode([
  'full_name' => "{$f['FACULTY_LNAME']}, {$f['FACULTY_FNAME']}" . ($f['FACULTY_INITIAL'] ? " {$f['FACULTY_INITIAL']}" : ''),
  'email'     => $f['FACULTY_EMAIL'],
  'rank_name' => $f['RANK_DESCRIPTION'],
  'dept_name' => $f['DEPT_SPECIALIZATION'],
  'dept_classification' => $f['DEPT_CLASSIFICATION'],
  'dept_contact' => $f['DEPT_CONTACTINFO'],
  'assignments' => $assignments
]);

