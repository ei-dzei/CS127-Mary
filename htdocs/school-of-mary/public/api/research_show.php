<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../partials/init.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid id']); exit; }

$st = $pdo->prepare("
  SELECT RESEARCH_ID, RESEARCH_TITLE, RESEARCH_STATUS, RESEARCH_STARTDATE, RESEARCH_ENDDATE
  FROM RESEARCH WHERE RESEARCH_ID = :id LIMIT 1
");
$st->execute([':id'=>$id]);
$r = $st->fetch(PDO::FETCH_ASSOC);
if (!$r) { http_response_code(404); echo json_encode(['error'=>'Not found']); exit; }

$pf = $pdo->prepare("
  SELECT CONCAT(f.FACULTY_FNAME,' ',IFNULL(f.FACULTY_INITIAL,''),' ',f.FACULTY_LNAME) AS name,
         rnk.RANK_DESCRIPTION AS rank_name,
         rl.ROLE_DESCRIPTION  AS role_name,
         a.DATE_ASSIGNED,
         f.FACULTY_ID
  FROM ASSIGNMENT a
  JOIN FACULTY f  ON f.FACULTY_ID = a.FACULTY_ID
  JOIN `RANK` rnk ON rnk.RANK_ID = f.RANK_ID
  LEFT JOIN ROLE rl ON rl.ROLE_ID = a.ROLE_ID
  WHERE a.RESEARCH_ID = :id
  ORDER BY a.DATE_ASSIGNED DESC, a.ASSIGNMENT_ID DESC
");
$pf->execute([':id'=>$id]);
$people = $pf->fetchAll(PDO::FETCH_ASSOC);

$fd = $pdo->prepare("
  SELECT ag.AGENCY_NAME, ag.AGENCY_TYPE, ag.AGENCY_CONTACTINFO,
         fu.FUNDING_AMOUNT, fu.DATE_FUNDED
  FROM FUNDING fu
  JOIN AGENCY ag ON ag.AGENCY_ID = fu.AGENCY_ID
  WHERE fu.RESEARCH_ID = :id
  ORDER BY fu.DATE_FUNDED DESC, fu.FUNDING_ID DESC
");
$fd->execute([':id'=>$id]);
$fundingRows = $fd->fetchAll(PDO::FETCH_ASSOC);

$totalFunding = 0.0;
foreach ($fundingRows as $f) {
  if ($f['FUNDING_AMOUNT'] !== null) $totalFunding += (float)$f['FUNDING_AMOUNT'];
}

$funding = array_map(function($f){
  return [
    'AGENCY_NAME' => $f['AGENCY_NAME'],
    'AGENCY_TYPE' => $f['AGENCY_TYPE'],
    'CONTACT'     => $f['AGENCY_CONTACTINFO'],
    'AMOUNT'      => $f['FUNDING_AMOUNT'],
    'AMOUNT_FMT'  => $f['FUNDING_AMOUNT'] !== null ? number_format((float)$f['FUNDING_AMOUNT'],2) : '—',
    'DATE_FUNDED' => $f['DATE_FUNDED'] ?: '—',
  ];
}, $fundingRows);

echo json_encode([
  'title' => $research['RESEARCH_TITLE'],
  'status' => $research['RESEARCH_STATUS'],
  'start_date' => $research['RESEARCH_STARTDATE'],
  'end_date' => $research['RESEARCH_ENDDATE'],
  'people' => $people,
  'funding' => $funds,
  'total_funding' => number_format($totalFunding, 2)
]);

